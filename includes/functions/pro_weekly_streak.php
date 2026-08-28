<?php
/** Repeatable seven-day check-in streak for PRO and Expert users. */
defined('PRO_WEEKLY_STREAK_DAYS') || define('PRO_WEEKLY_STREAK_DAYS', 7);
defined('PRO_WEEKLY_STREAK_BOX_MIN') || define('PRO_WEEKLY_STREAK_BOX_MIN', 10);
defined('PRO_WEEKLY_STREAK_BOX_MAX') || define('PRO_WEEKLY_STREAK_BOX_MAX', 20);

function ensureProWeeklyStreakSchema(PDO $db = null): void {
    static $ready = false;
    if ($ready) return;
    $db = $db ?: getDBConnection();
    if (!tableExists('users') || !tableExists('reward_ledger')) return;
    $db->exec("CREATE TABLE IF NOT EXISTS pro_weekly_streak_cycles (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, user_id INT UNSIGNED NOT NULL,
        cycle_number INT UNSIGNED NOT NULL,
        status ENUM('active','box_pending','completed','missed') NOT NULL DEFAULT 'active',
        current_day TINYINT UNSIGNED NOT NULL DEFAULT 0, started_on DATE NULL,
        last_checkin_on DATE NULL, box_reward TINYINT UNSIGNED NULL,
        box_unlocked_at DATETIME NULL, box_claimed_at DATETIME NULL,
        restart_available_at DATETIME NULL, ended_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_pro_weekly_cycle_number (user_id, cycle_number),
        KEY idx_pro_weekly_cycle_state (user_id, status, id),
        CONSTRAINT fk_pro_weekly_cycle_user FOREIGN KEY (user_id) REFERENCES users(id)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS pro_weekly_streak_checkins (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, cycle_id BIGINT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL, streak_day TINYINT UNSIGNED NOT NULL,
        checkin_date DATE NOT NULL, reward_amount DECIMAL(18,8) NOT NULL,
        ledger_entry_id INT UNSIGNED NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_pro_weekly_cycle_day (cycle_id, streak_day),
        UNIQUE KEY uq_pro_weekly_user_date (user_id, checkin_date),
        KEY idx_pro_weekly_checkins_user (user_id, created_at),
        CONSTRAINT fk_pro_weekly_checkin_cycle FOREIGN KEY (cycle_id)
            REFERENCES pro_weekly_streak_cycles(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pro_weekly_checkin_user FOREIGN KEY (user_id)
            REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_pro_weekly_checkin_ledger FOREIGN KEY (ledger_entry_id)
            REFERENCES reward_ledger(id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function proWeeklyStreakUserIsEligible(array $user): bool {
    return in_array(normalizeUserLevel((string)($user['level'] ?? 'beginner')), ['pro','expert'], true);
}
function proWeeklyStreakTestMode(): bool {
    return (defined('TESTING_MODE') && TESTING_MODE)
        || (defined('LOCAL_TEST_MODE') && LOCAL_TEST_MODE);
}
function proWeeklyStreakCheckinDate(array $cycle, int $day, int $now, ?bool $test_mode = null): string {
    if(!($test_mode ?? proWeeklyStreakTestMode())) return date('Y-m-d',$now);
    // A deterministic slot preserves the production unique user/date guard
    // while allowing same-session Day 1-7 and repeated-cycle testing.
    $offset=max(0,(((int)$cycle['cycle_number']-1)*PRO_WEEKLY_STREAK_DAYS)+$day-1);
    return date('Y-m-d',strtotime('2000-01-01 +'.$offset.' days'));
}
function proWeeklyStreakLatestCycle(int $uid, PDO $db, bool $lock = false): ?array {
    $sql = 'SELECT * FROM pro_weekly_streak_cycles WHERE user_id=? ORDER BY cycle_number DESC,id DESC LIMIT 1';
    if ($lock) $sql .= ' FOR UPDATE';
    $stmt=$db->prepare($sql); $stmt->execute([$uid]); $row=$stmt->fetch();
    return $row ?: null;
}
function proWeeklyStreakCreateCycle(int $uid, PDO $db): array {
    $stmt=$db->prepare('SELECT COALESCE(MAX(cycle_number),0)+1 FROM pro_weekly_streak_cycles WHERE user_id=?');
    $stmt->execute([$uid]); $number=(int)$stmt->fetchColumn();
    $stmt=$db->prepare("INSERT INTO pro_weekly_streak_cycles(user_id,cycle_number,status,current_day) VALUES(?,?,'active',0)");
    $stmt->execute([$uid,$number]);
    $cycle=proWeeklyStreakLatestCycle($uid,$db,true);
    if (!$cycle) throw new RuntimeException('Weekly streak could not be started.');
    return $cycle;
}
function proWeeklyStreakIsMissed(array $cycle, int $now, bool $honor_test_mode = true, ?bool $test_mode = null): bool {
    if($honor_test_mode && ($test_mode ?? proWeeklyStreakTestMode())) return false;
    if (($cycle['status'] ?? '') !== 'active' || empty($cycle['last_checkin_on'])) return false;
    $expected=date('Y-m-d',strtotime($cycle['last_checkin_on'].' +1 day'));
    return date('Y-m-d',$now)>$expected;
}
function proWeeklyStreakCanRestart(array $cycle, int $now, bool $honor_test_mode = true, ?bool $test_mode = null): bool {
    if(($cycle['status'] ?? '')!=='completed') return false;
    if($honor_test_mode && ($test_mode ?? proWeeklyStreakTestMode())) return true;
    return !empty($cycle['restart_available_at']) && $now>=strtotime($cycle['restart_available_at']);
}
function proWeeklyStreakBaseState(bool $eligible, int $now): array {
    $test_mode=proWeeklyStreakTestMode();
    $rewards=[]; for($day=1;$day<=7;$day++) $rewards[]=['day'=>$day,'reward'=>$day];
    return ['eligible'=>$eligible,'cycle_id'=>null,'cycle_number'=>1,
        'status'=>$eligible?'ready':'ineligible','current_day'=>0,'next_day'=>1,
        'completed_days'=>[],'can_checkin'=>$eligible,'checked_in_today'=>false,
        'box_pending'=>false,'box_reward'=>null,
        'next_reset_at'=>$test_mode?null:date('Y-m-d H:i:s',getTaskHubNextResetTimestamp($now)),
        'today_reward'=>1,'daily_rewards'=>$rewards,'reset_after_miss'=>false,
        'test_mode'=>$test_mode,
        'message'=>$eligible?($test_mode?'Test mode: reset wait is bypassed.':'Start your seven-day streak.'):'PRO or Expert access is required.'];
}

function proWeeklyStreakGetState(int $uid, PDO $db=null, ?int $now=null): array {
    $db=$db?:getDBConnection(); ensureProWeeklyStreakSchema($db); $now=$now??time();
    $user=getUserById($uid); $eligible=$user && proWeeklyStreakUserIsEligible($user);
    $state=proWeeklyStreakBaseState((bool)$eligible,$now);
    if (!$eligible || !($cycle=proWeeklyStreakLatestCycle($uid,$db))) return $state;
    $state['cycle_id']=(int)$cycle['id']; $state['cycle_number']=(int)$cycle['cycle_number'];
    $state['current_day']=(int)$cycle['current_day'];
    $state['next_day']=min(7,$state['current_day']+1); $state['today_reward']=$state['next_day'];
    $state['box_reward']=$cycle['box_reward']!==null?(int)$cycle['box_reward']:null;
    $stmt=$db->prepare('SELECT streak_day FROM pro_weekly_streak_checkins WHERE cycle_id=? ORDER BY streak_day');
    $stmt->execute([(int)$cycle['id']]);
    $state['completed_days']=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN,0)?:[]);
    $status=(string)$cycle['status'];
    if ($status==='box_pending') {
        $state['status']='box_pending'; $state['can_checkin']=false; $state['box_pending']=true;
        $state['message']='Your mystery box is ready to reveal.'; return $state;
    }
    if ($status==='completed') {
        $state['can_checkin']=false;
        if (proWeeklyStreakCanRestart($cycle,$now)) {
            $state=proWeeklyStreakBaseState(true,$now);
            $state['cycle_number']=((int)$cycle['cycle_number'])+1;
            $state['message']='A new weekly streak is ready.';
        } else {
            $state['status']='waiting_reset'; $state['next_reset_at']=$cycle['restart_available_at'];
            $state['message']='Your next weekly streak starts after the next reset.';
        }
        return $state;
    }
    if ($status==='missed' || proWeeklyStreakIsMissed($cycle,$now)) {
        $state=proWeeklyStreakBaseState(true,$now);
        $state['cycle_number']=((int)$cycle['cycle_number'])+1; $state['reset_after_miss']=true;
        $state['message']='A day was missed, so your streak reset to Day 1.'; return $state;
    }
    $checked=!proWeeklyStreakTestMode() && ($cycle['last_checkin_on']??'')===date('Y-m-d',$now);
    $state['checked_in_today']=$checked; $state['can_checkin']=!$checked;
    $state['status']=$checked?'checked_in_today':'ready';
    $state['message']=$checked?"Today's streak is secured. Come back after the reset."
        :'Day '.$state['next_day'].' is ready to check in.';
    return $state;
}

function proWeeklyStreakRequireEligibleUser(int $uid, PDO $db): array {
    $stmt=$db->prepare('SELECT * FROM users WHERE id=? LIMIT 1 FOR UPDATE'); $stmt->execute([$uid]);
    $user=$stmt->fetch(); if(!$user) throw new RuntimeException('User not found.');
    if(!proWeeklyStreakUserIsEligible($user)) throw new DomainException('PRO or Expert access is required.');
    return $user;
}

function proWeeklyStreakCheckIn(int $uid, PDO $db=null, ?int $now=null): array {
    $db=$db?:getDBConnection(); ensureProWeeklyStreakSchema($db); $now=$now??time();
    $stamp=date('Y-m-d H:i:s',$now); $today=date('Y-m-d',$now); $db->beginTransaction();
    try {
        $user=proWeeklyStreakRequireEligibleUser($uid,$db);
        $cycle=proWeeklyStreakLatestCycle($uid,$db,true);
        if($cycle && $cycle['status']==='box_pending')
            throw new RuntimeException('Open your pending mystery box before starting another cycle.');
        if($cycle && $cycle['status']==='completed' && !proWeeklyStreakCanRestart($cycle,$now))
            throw new RuntimeException('Your next weekly streak starts after the next server reset.');
        if(!proWeeklyStreakTestMode() && $cycle && $cycle['status']==='active' && ($cycle['last_checkin_on']??'')===$today)
            throw new RuntimeException("Today's check-in has already been completed.");
        $new=!$cycle || $cycle['status']==='missed' || proWeeklyStreakIsMissed($cycle,$now)
            || proWeeklyStreakCanRestart($cycle,$now);
        if($new) {
            if($cycle && $cycle['status']==='active') {
                $stmt=$db->prepare("UPDATE pro_weekly_streak_cycles SET status='missed',ended_at=? WHERE id=?");
                $stmt->execute([$stamp,(int)$cycle['id']]);
            }
            $cycle=proWeeklyStreakCreateCycle($uid,$db);
        }
        $day=((int)$cycle['current_day'])+1;
        if($day<1 || $day>7) throw new RuntimeException('This weekly streak cannot accept another check-in.');
        $checkin_date=proWeeklyStreakCheckinDate($cycle,$day,$now);
        $stmt=$db->prepare('INSERT INTO pro_weekly_streak_checkins(cycle_id,user_id,streak_day,checkin_date,reward_amount) VALUES(?,?,?,?,?)');
        $stmt->execute([(int)$cycle['id'],$uid,$day,$checkin_date,$day]); $checkin_id=(int)$db->lastInsertId();
        $ledger=addRewardLedgerEntry($uid,(float)$day,'bonus','pro_weekly_checkin','available',
            'pro_weekly:cycle:'.(int)$cycle['id'].':day:'.$day,$db,'phase2',$user['level']??'pro');
        $stmt=$db->prepare('UPDATE pro_weekly_streak_checkins SET ledger_entry_id=? WHERE id=?');
        $stmt->execute([(int)$ledger['id'],$checkin_id]);
        $status=$day===7?'box_pending':'active';
        $stmt=$db->prepare("UPDATE pro_weekly_streak_cycles SET status=?,current_day=?,
            started_on=COALESCE(started_on,?),last_checkin_on=?,
            box_unlocked_at=CASE WHEN ?='box_pending' THEN ? ELSE box_unlocked_at END WHERE id=?");
        $stmt->execute([$status,$day,$checkin_date,$checkin_date,$status,$stamp,(int)$cycle['id']]);
        $db->commit();
        return ['reward'=>$day,'box_unlocked'=>$status==='box_pending',
            'state'=>proWeeklyStreakGetState($uid,$db,$now)];
    } catch(Throwable $e) {
        if($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

function proWeeklyStreakClaimBox(int $uid, PDO $db=null, ?int $now=null): array {
    $db=$db?:getDBConnection(); ensureProWeeklyStreakSchema($db); $now=$now??time();
    $stamp=date('Y-m-d H:i:s',$now); $db->beginTransaction();
    try {
        $user=proWeeklyStreakRequireEligibleUser($uid,$db);
        $cycle=proWeeklyStreakLatestCycle($uid,$db,true);
        if(!$cycle || $cycle['status']!=='box_pending')
            throw new RuntimeException('No mystery box is ready to claim.');
        $reward=random_int(PRO_WEEKLY_STREAK_BOX_MIN,PRO_WEEKLY_STREAK_BOX_MAX);
        $ledger=addRewardLedgerEntry($uid,(float)$reward,'bonus','pro_weekly_mystery_box','available',
            'pro_weekly:cycle:'.(int)$cycle['id'].':mystery_box',$db,'phase2',$user['level']??'pro');
        $restart=date('Y-m-d H:i:s',getTaskHubNextResetTimestamp($now));
        $stmt=$db->prepare("UPDATE pro_weekly_streak_cycles SET status='completed',box_reward=?,
            box_claimed_at=?,restart_available_at=?,ended_at=? WHERE id=? AND status='box_pending'");
        $stmt->execute([$reward,$stamp,$restart,$stamp,(int)$cycle['id']]);
        if($stmt->rowCount()!==1) throw new RuntimeException('This mystery box has already been claimed.');
        $db->commit();
        return ['reward'=>$reward,'ledger_entry_id'=>(int)$ledger['id'],
            'state'=>proWeeklyStreakGetState($uid,$db,$now)];
    } catch(Throwable $e) {
        if($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}
