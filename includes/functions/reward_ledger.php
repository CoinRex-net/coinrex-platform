<?php
/** Auto-split from legacy functions.php */

function ensureRewardClaimSchema(PDO $db = null) {
    static $schema_ready = false;

    if ($schema_ready) {
        return;
    }

    $db = $db ?: getDBConnection();

    if (!tableExists('users')) {
        return;
    }

    if (!tableHasColumn('users', 'wallet_address')) {
        $db->exec("ALTER TABLE users ADD COLUMN wallet_address VARCHAR(100) NULL AFTER country");
    }

    if (!tableHasColumn('users', 'reward_frozen')) {
        $db->exec("ALTER TABLE users ADD COLUMN reward_frozen TINYINT(1) NOT NULL DEFAULT 0 AFTER wallet_address");
    }

    if (!tableHasColumn('users', 'security_flagged')) {
        $db->exec("ALTER TABLE users ADD COLUMN security_flagged TINYINT(1) NOT NULL DEFAULT 0 AFTER reward_frozen");
    }
    if (!tableHasColumn('users', 'security_flag_reason')) {
        $db->exec("ALTER TABLE users ADD COLUMN security_flag_reason VARCHAR(255) NULL AFTER security_flagged");
    }
    if (!tableHasColumn('users', 'security_warning_count')) {
        $db->exec("ALTER TABLE users ADD COLUMN security_warning_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER security_flag_reason");
    }
    if (!tableHasColumn('users', 'security_suspended')) {
        $db->exec("ALTER TABLE users ADD COLUMN security_suspended TINYINT(1) NOT NULL DEFAULT 0 AFTER security_warning_count");
    }
    if (!tableHasColumn('users', 'taskhub_blocked_until')) {
        $db->exec("ALTER TABLE users ADD COLUMN taskhub_blocked_until DATETIME NULL AFTER security_suspended");
    }
    if (!tableHasColumn('users', 'boosthub_blocked_until')) {
        $db->exec("ALTER TABLE users ADD COLUMN boosthub_blocked_until DATETIME NULL AFTER taskhub_blocked_until");
    }
    if (!tableHasColumn('users', 'review_blocked_until')) {
        $db->exec("ALTER TABLE users ADD COLUMN review_blocked_until DATETIME NULL AFTER boosthub_blocked_until");
    }
    if (!tableHasColumn('users', 'referral_review_status')) {
        $db->exec("ALTER TABLE users ADD COLUMN referral_review_status VARCHAR(30) NOT NULL DEFAULT 'pending' AFTER review_blocked_until");
    }
    if (!tableHasColumn('users', 'referral_flag_reason')) {
        $db->exec("ALTER TABLE users ADD COLUMN referral_flag_reason VARCHAR(255) NULL AFTER referral_review_status");
    }
    if (!tableHasColumn('users', 'referral_reviewed_at')) {
        $db->exec("ALTER TABLE users ADD COLUMN referral_reviewed_at DATETIME NULL AFTER referral_flag_reason");
    }
    if (!tableHasColumn('users', 'referral_reviewed_by')) {
        $db->exec("ALTER TABLE users ADD COLUMN referral_reviewed_by INT UNSIGNED NULL AFTER referral_reviewed_at");
    }

    if (!tableHasColumn('users', 'current_day')) {
        $db->exec("ALTER TABLE users ADD COLUMN current_day TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER reward_frozen");
    }

    if (!tableHasColumn('users', 'last_day_completed_at')) {
        $db->exec("ALTER TABLE users ADD COLUMN last_day_completed_at DATETIME NULL AFTER current_day");
    }

    if (!tableHasColumn('users', 'profile_completed_at')) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_completed_at DATETIME NULL AFTER last_day_completed_at");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS reward_ledger (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            source VARCHAR(50) NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            amount DECIMAL(18,8) NOT NULL,
            status ENUM('pending','locked','available','claimed') NOT NULL DEFAULT 'pending',
            reference_id VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_reward_ledger_user_status (user_id, status),
            KEY idx_reward_ledger_source (source),
            KEY idx_reward_ledger_reference (reference_id),
            KEY idx_reward_ledger_created_at (created_at),
            CONSTRAINT fk_reward_ledger_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!tableHasColumn('reward_ledger', 'reward_phase')) {
        $db->exec("ALTER TABLE reward_ledger ADD COLUMN reward_phase ENUM('phase1','phase2') NOT NULL DEFAULT 'phase1' AFTER source");
    }

    if (!tableHasColumn('reward_ledger', 'user_level_at_time')) {
        $db->exec("ALTER TABLE reward_ledger ADD COLUMN user_level_at_time VARCHAR(20) NULL AFTER reference_id");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS claim_snapshots (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            total_amount DECIMAL(18,8) NOT NULL,
            nonce BIGINT UNSIGNED NOT NULL,
            status ENUM('generated','used','expired') NOT NULL DEFAULT 'generated',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_claim_snapshots_nonce (nonce),
            KEY idx_claim_snapshots_user_status (user_id, status),
            KEY idx_claim_snapshots_created_at (created_at),
            CONSTRAINT fk_claim_snapshots_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS mini_tasks (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(100) NOT NULL,
            description TEXT NULL,
            reward DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            daily_limit INT NOT NULL DEFAULT 1,
            cooldown_seconds INT NOT NULL DEFAULT 86400,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY idx_mini_tasks_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!tableHasColumn('mini_tasks', 'task_key')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN task_key VARCHAR(120) NULL AFTER title");
    }

    if (!tableHasColumn('mini_tasks', 'task_group')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN task_group VARCHAR(30) NOT NULL DEFAULT 'legacy' AFTER task_key");
    }

    if (!tableHasColumn('mini_tasks', 'mission_day')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN mission_day TINYINT UNSIGNED NULL AFTER task_group");
    }

    if (!tableHasColumn('mini_tasks', 'mission_step')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN mission_step TINYINT UNSIGNED NULL AFTER mission_day");
    }

    if (!tableHasColumn('mini_tasks', 'unlock_after_hours')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN unlock_after_hours INT NOT NULL DEFAULT 0 AFTER mission_step");
    }

    if (!tableHasColumn('mini_tasks', 'verification_mode')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN verification_mode VARCHAR(30) NOT NULL DEFAULT 'instant' AFTER unlock_after_hours");
    }

    if (!tableHasColumn('mini_tasks', 'requires_quiz')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN requires_quiz TINYINT(1) NOT NULL DEFAULT 0 AFTER verification_mode");
    }

    if (!tableHasColumn('mini_tasks', 'requires_manual_review')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN requires_manual_review TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_quiz");
    }

    if (!tableHasColumn('mini_tasks', 'min_quiz_score')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN min_quiz_score TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER requires_manual_review");
    }

    if (!tableHasColumn('mini_tasks', 'task_category')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN task_category VARCHAR(40) NOT NULL DEFAULT 'custom' AFTER min_quiz_score");
    }

    if (!tableHasColumn('mini_tasks', 'task_link')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN task_link VARCHAR(255) NULL AFTER task_category");
    }

    if (!tableHasColumn('mini_tasks', 'completion_steps')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN completion_steps TEXT NULL AFTER task_link");
    }

    if (!tableHasColumn('mini_tasks', 'proof_notes')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN proof_notes TEXT NULL AFTER completion_steps");
    }

    if (!tableHasColumn('mini_tasks', 'cta_label')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN cta_label VARCHAR(80) NULL AFTER proof_notes");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS user_task_logs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            task_id INT UNSIGNED NOT NULL,
            completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status ENUM('completed','blocked') NOT NULL DEFAULT 'completed',
            PRIMARY KEY (id),
            KEY idx_user_task_logs_user_status (user_id, status),
            KEY idx_user_task_logs_task_status (task_id, status),
            KEY idx_user_task_logs_completed_at (completed_at),
            CONSTRAINT fk_user_task_logs_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_user_task_logs_task
                FOREIGN KEY (task_id) REFERENCES mini_tasks(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $status_definition = $db->query("SHOW COLUMNS FROM user_task_logs LIKE 'status'")->fetch();
    if ($status_definition && strpos((string) ($status_definition['Type'] ?? ''), "'pending'") === false) {
        $db->exec("ALTER TABLE user_task_logs MODIFY COLUMN status ENUM('pending','submitted','completed','blocked','failed') NOT NULL DEFAULT 'completed'");
    }

    if (!tableHasColumn('user_task_logs', 'task_completed_at')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN task_completed_at DATETIME NULL AFTER completed_at");
    }

    if (!tableHasColumn('user_task_logs', 'task_available_at')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN task_available_at DATETIME NULL AFTER task_completed_at");
    }

    if (!tableHasColumn('user_task_logs', 'mission_day')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN mission_day TINYINT UNSIGNED NULL AFTER task_available_at");
    }

    if (!tableHasColumn('user_task_logs', 'mission_step')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN mission_step TINYINT UNSIGNED NULL AFTER mission_day");
    }

    if (!tableHasColumn('user_task_logs', 'attempt_no')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN attempt_no INT UNSIGNED NOT NULL DEFAULT 1 AFTER mission_step");
    }

    if (!tableHasColumn('user_task_logs', 'proof_data')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN proof_data TEXT NULL AFTER attempt_no");
    }

    if (!tableHasColumn('user_task_logs', 'score')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN score TINYINT UNSIGNED NULL AFTER proof_data");
    }

    if (!tableHasColumn('user_task_logs', 'metadata')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN metadata LONGTEXT NULL AFTER score");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS taskhub_quiz_attempts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            task_id INT UNSIGNED NOT NULL,
            mission_day TINYINT UNSIGNED NOT NULL,
            score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            total_questions TINYINT UNSIGNED NOT NULL DEFAULT 0,
            answers_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_taskhub_quiz_attempts_user_task (user_id, task_id),
            CONSTRAINT fk_taskhub_quiz_attempts_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_taskhub_quiz_attempts_task
                FOREIGN KEY (task_id) REFERENCES mini_tasks(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $task_count = (int) ($db->query("SELECT COUNT(*) FROM mini_tasks")->fetchColumn() ?: 0);
    if ($task_count === 0) {
        $db->exec("
            INSERT INTO mini_tasks (title, description, reward, daily_limit, cooldown_seconds, is_active) VALUES
            ('Daily Check-In', 'Return to CoinRex and keep your beginner streak alive.', 1.0000, 1, 86400, 1),
            ('Explore Projects', 'Browse listed projects and stay active in the ecosystem.', 1.5000, 1, 86400, 1),
            ('Profile Warmup', 'Keep your profile active while your account builds trust.', 2.0000, 1, 86400, 1)
        ");
    }

    $mission_tasks = getTaskHubMissionDefinitions();
    foreach ($mission_tasks as $mission_task) {
        $select_task = $db->prepare("SELECT id FROM mini_tasks WHERE task_key = ? LIMIT 1");
        $select_task->execute([(string) $mission_task['task_key']]);
        $existing_task = $select_task->fetch();

        $params = [
            (string) $mission_task['title'],
            (string) $mission_task['task_key'],
            'mission',
            (int) $mission_task['day'],
            (int) $mission_task['step'],
            (float) $mission_task['reward'],
            (int) $mission_task['daily_limit'],
            (int) $mission_task['cooldown_seconds'],
            (int) $mission_task['unlock_after_hours'],
            (string) $mission_task['verification_mode'],
            !empty($mission_task['requires_quiz']) ? 1 : 0,
            !empty($mission_task['requires_manual_review']) ? 1 : 0,
            (int) ($mission_task['min_quiz_score'] ?? 0),
            !empty($mission_task['is_active']) ? 1 : 0,
            (string) $mission_task['description'],
        ];

        if (!$existing_task) {
            $insert_task = $db->prepare("
                INSERT INTO mini_tasks (
                    title, task_key, task_group, mission_day, mission_step, reward, daily_limit, cooldown_seconds,
                    unlock_after_hours, verification_mode, requires_quiz, requires_manual_review, min_quiz_score, is_active, description
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_task->execute($params);
        }
    }

    $deprecated_task_keys = [
        'day4_boosthub',
        'day5_boosthub',
        'day6_boosthub',
        'day7_boosthub',
        'day8_boosthub',
        'day9_boosthub',
        'day10_boosthub',
        'day6_claim_awareness',
        'day8_wallet_familiarity',
        'day9_wallet_familiarity'
    ];
    $placeholders = implode(',', array_fill(0, count($deprecated_task_keys), '?'));
    $deprecate_stmt = $db->prepare("UPDATE mini_tasks SET is_active = 0 WHERE task_key IN ($placeholders)");
    $deprecate_stmt->execute($deprecated_task_keys);

    $schema_ready = true;
}

function normalizeLedgerText($value, $max_length) {
    return substr(trim((string) $value), 0, (int) $max_length);
}

function normalizeRewardLedgerSource($source) {
    $source = strtolower(trim((string) $source));
    $allowed_sources = ['mini_task', 'referral', 'review', 'bonus'];
    return in_array($source, $allowed_sources, true) ? $source : 'bonus';
}

function normalizeRewardPhase($phase) {
    $phase = strtolower(trim((string) $phase));
    return in_array($phase, ['phase1', 'phase2'], true) ? $phase : 'phase1';
}

function resolveRewardPhase($source, $user_level = null) {
    $source = normalizeRewardLedgerSource($source);
    $user_level = normalizeUserLevel($user_level ?? 'beginner');

    if ($source === 'mini_task' || $user_level === 'beginner') {
        return 'phase1';
    }

    return 'phase2';
}

function normalizeLedgerStatus($status) {
    $status = strtolower(trim((string) $status));
    $allowed_statuses = ['pending', 'locked', 'available', 'claimed'];
    return in_array($status, $allowed_statuses, true) ? $status : 'pending';
}

function getLedgerDisplayBalance($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND status IN ('available', 'locked', 'claimed')
    ");
    $stmt->execute([(int) $user_id]);
    return round((float) ($stmt->fetch()['total'] ?? 0), 8);
}

function syncLegacyRewardCache($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $available_display = getLedgerDisplayBalance($user_id, $db);

    $earned_stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND status IN ('available', 'locked', 'claimed')
    ");
    $earned_stmt->execute([$user_id]);
    $earned_total = round((float) ($earned_stmt->fetch()['total'] ?? 0), 8);

    $update = $db->prepare("
        UPDATE users
        SET rex_balance = ?,
            total_rex_earned = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$available_display, max(0, $earned_total), $user_id]);
    return true;
}

function addRewardLedgerEntry($user_id, $amount, $source, $action_type = 'credit', $status = 'available', $reference_id = null, PDO $db = null, $reward_phase = null, $user_level_at_time = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    $amount = round((float) $amount, 8);
    $source = normalizeRewardLedgerSource($source);
    $action_type = normalizeLedgerText($action_type, 50);
    $status = normalizeLedgerStatus($status);
    $reference_id = $reference_id !== null && trim((string) $reference_id) !== ''
        ? normalizeLedgerText($reference_id, 100)
        : null;
    $user_level_at_time = normalizeUserLevel($user_level_at_time ?? (getUserById($user_id)['level'] ?? 'beginner'));
    $reward_phase = normalizeRewardPhase($reward_phase ?? resolveRewardPhase($source, $user_level_at_time));

    if ($user_id <= 0) {
        throw new InvalidArgumentException('Invalid user ID.');
    }

    if ($amount == 0.0) {
        throw new InvalidArgumentException('Reward amount must not be zero.');
    }

    if ($source === '') {
        throw new InvalidArgumentException('Reward source is required.');
    }

    if ($action_type === '') {
        $action_type = 'credit';
    }

    $stmt = $db->prepare("
        INSERT INTO reward_ledger (user_id, source, reward_phase, action_type, amount, status, reference_id, user_level_at_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $source, $reward_phase, $action_type, $amount, $status, $reference_id, $user_level_at_time]);

    if ($amount > 0) {
        createTemplatedNotification('reward.added', 'user', $user_id, [
            'amount' => number_format($amount, 2),
            'action_type' => $action_type,
        ], [
            'event_key' => 'reward.added',
            'meta' => [
                'source' => $source,
                'reference_id' => $reference_id,
                'status' => $status,
            ],
        ], $db);
    }

    syncLegacyRewardCache($user_id, $db);

    return [
        'id' => (int) $db->lastInsertId(),
        'user_id' => $user_id,
        'amount' => number_format($amount, 8, '.', ''),
        'source' => $source,
        'reward_phase' => $reward_phase,
        'action_type' => $action_type,
        'status' => $status,
        'reference_id' => $reference_id,
        'user_level_at_time' => $user_level_at_time,
    ];
}

function getRewardLedgerBalance($user_id, $status = 'available', PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0.0;
    }

    $status = normalizeLedgerStatus($status);
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND status = ?
    ");
    $stmt->execute([$user_id, $status]);

    return round((float) ($stmt->fetch()['total'] ?? 0), 8);
}

function generateUniqueClaimNonce(PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    do {
        $nonce = (string) random_int(1000000000000000000, 9223372036854775807);
        $stmt = $db->prepare("SELECT id FROM claim_snapshots WHERE nonce = ? LIMIT 1");
        $stmt->execute([$nonce]);
        $exists = $stmt->fetch();
    } while ($exists);

    return $nonce;
}

function generateClaimSnapshotForUser($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        throw new InvalidArgumentException('Invalid user ID.');
    }

    $eligibility = getClaimEligibility($user_id, $db);
    if (empty($eligibility['eligible'])) {
        throw new RuntimeException((string) ($eligibility['message'] ?? 'Claim requirements are not met.'));
    }

    try {
        $db->beginTransaction();

        $claim_check = $db->prepare("
            SELECT id, nonce, total_amount, status, created_at
            FROM claim_snapshots
            WHERE user_id = ?
              AND status = 'generated'
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $claim_check->execute([$user_id]);
        $open_snapshot = $claim_check->fetch();

        if ($open_snapshot) {
            throw new RuntimeException('A claim is already prepared for this account.');
        }

        $ledger_stmt = $db->prepare("
            SELECT id, amount
            FROM reward_ledger
            WHERE user_id = ?
              AND status = 'available'
            ORDER BY id ASC
            FOR UPDATE
        ");
        $ledger_stmt->execute([$user_id]);
        $rows = $ledger_stmt->fetchAll();

        if (empty($rows)) {
            throw new RuntimeException('No available rewards found for claim preparation.');
        }

        $total_amount = 0.0;
        $ledger_ids = [];
        foreach ($rows as $row) {
            $total_amount += (float) ($row['amount'] ?? 0);
            $ledger_ids[] = (int) $row['id'];
        }
        $total_amount = round($total_amount, 8);

        if ($total_amount <= 0) {
            throw new RuntimeException('Claim amount must be greater than zero.');
        }

        $nonce = generateUniqueClaimNonce($db);
        $insert_snapshot = $db->prepare("
            INSERT INTO claim_snapshots (user_id, total_amount, nonce, status)
            VALUES (?, ?, ?, 'generated')
        ");
        $insert_snapshot->execute([$user_id, $total_amount, $nonce]);
        $snapshot_id = (int) $db->lastInsertId();

        $placeholders = implode(',', array_fill(0, count($ledger_ids), '?'));
        $update_params = array_merge([$user_id], $ledger_ids);
        $lock_rewards = $db->prepare("
            UPDATE reward_ledger
            SET status = 'locked'
            WHERE user_id = ?
              AND status = 'available'
              AND id IN ($placeholders)
        ");
        $lock_rewards->execute($update_params);

        if ($lock_rewards->rowCount() !== count($ledger_ids)) {
            throw new RuntimeException('Unable to lock every reward row for this claim.');
        }

        $db->commit();

        return [
            'snapshot_id' => $snapshot_id,
            'user_id' => $user_id,
            'amount' => number_format($total_amount, 8, '.', ''),
            'nonce' => $nonce,
            'status' => 'generated',
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function getClaimSnapshotStatus($snapshot_id, $user_id = null, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $snapshot_id = (int) $snapshot_id;
    if ($snapshot_id <= 0) {
        throw new InvalidArgumentException('Invalid snapshot ID.');
    }

    $sql = "
        SELECT id, user_id, total_amount, nonce, status, created_at
        FROM claim_snapshots
        WHERE id = ?
    ";
    $params = [$snapshot_id];

    if ($user_id !== null) {
        $sql .= " AND user_id = ?";
        $params[] = (int) $user_id;
    }

    $sql .= " LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $snapshot = $stmt->fetch();

    if (!$snapshot) {
        throw new RuntimeException('Claim snapshot not found.');
    }

    return [
        'id' => (int) $snapshot['id'],
        'user_id' => (int) $snapshot['user_id'],
        'amount' => number_format((float) $snapshot['total_amount'], 8, '.', ''),
        'nonce' => (string) $snapshot['nonce'],
        'status' => (string) $snapshot['status'],
        'created_at' => (string) $snapshot['created_at'],
    ];
}

function getClaimEligibility($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = getUserById((int) $user_id);
    if (!$user) {
        return ['eligible' => false, 'message' => 'User account not found.'];
    }

    if (!empty($user['reward_frozen'])) {
        return ['eligible' => false, 'message' => 'Rewards are temporarily frozen by the admin team for this account.'];
    }

    $level_state = getUserLevelState($user, $db);
    if (!in_array((string) ($level_state['level'] ?? 'beginner'), ['pro', 'expert'], true)) {
        return ['eligible' => false, 'message' => 'Claim unlocks once your account reaches Pro level.'];
    }

    $balance = getRewardLedgerBalance((int) $user_id, 'available', $db);
    if ($balance < (float) REWARD_CLAIM_MINIMUM_REX) {
        return ['eligible' => false, 'message' => 'Minimum claim threshold has not been reached yet.'];
    }

    $signals = getUserSecuritySignals((int) $user_id, $db);
    if (!empty($signals['is_suspicious'])) {
        return ['eligible' => false, 'message' => 'Claim is temporarily unavailable while account activity is reviewed.', 'signals' => $signals];
    }

    return [
        'eligible' => true,
        'message' => 'Claim snapshot can be generated.',
        'balance' => number_format($balance, 8, '.', ''),
        'level' => (string) ($level_state['level'] ?? 'beginner'),
    ];
}

function calculateRewardFromFinalScore($final_score, $project_max_reward, $wallet_type) {
    $final_score = (float) $final_score;
    $project_max_reward = (float) $project_max_reward;
    $wallet_type = strtolower(trim((string) $wallet_type));

    if ($final_score < 50 || $project_max_reward <= 0) {
        return 0;
    }

    $reward = ($final_score / 100) * $project_max_reward;
    if ($wallet_type === 'custodial') {
        $reward *= 0.5;
    }

    return (int) round($reward, 0);
}
