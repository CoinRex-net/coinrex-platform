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
    if (!tableHasColumn('users', 'referral_abuse_detected')) {
        $db->exec("ALTER TABLE users ADD COLUMN referral_abuse_detected TINYINT(1) NOT NULL DEFAULT 0 AFTER referral_reviewed_by");
    }
    if (!tableHasColumn('users', 'referral_abuse_reason')) {
        $db->exec("ALTER TABLE users ADD COLUMN referral_abuse_reason VARCHAR(255) NULL AFTER referral_abuse_detected");
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
            status ENUM('pending','locked','available','claimed','expired') NOT NULL DEFAULT 'pending',
            reference_id VARCHAR(100) NULL,
            expires_at DATETIME NULL,
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

    if (!tableHasColumn('reward_ledger', 'expires_at')) {
        $db->exec("ALTER TABLE reward_ledger ADD COLUMN expires_at DATETIME NULL AFTER user_level_at_time");
    }

    try {
        $status_definition = $db->query("SHOW COLUMNS FROM reward_ledger LIKE 'status'")->fetch();
        if ($status_definition && stripos((string) ($status_definition['Type'] ?? ''), 'expired') === false) {
            $db->exec("ALTER TABLE reward_ledger MODIFY status ENUM('pending','locked','available','claimed','expired') NOT NULL DEFAULT 'pending'");
        }
    } catch (Throwable $e) {
        error_log('Could not expand reward ledger status enum: ' . $e->getMessage());
    }

    try {
        $db->exec("ALTER TABLE reward_ledger ADD KEY idx_reward_ledger_expires (expires_at)");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1061') === false && stripos($e->getMessage(), 'Duplicate key name') === false) {
            error_log('Could not add reward ledger expiry index: ' . $e->getMessage());
        }
    }

    try {
        $db->exec("
            UPDATE reward_ledger
            SET expires_at = DATE_ADD(created_at, INTERVAL " . (int) EARLY_AIRDROP_UNLOCK_DAYS . " DAY)
            WHERE action_type = 'early_adopter_airdrop'
              AND status = 'pending'
              AND expires_at IS NULL
        ");
    } catch (Throwable $e) {
        error_log('Could not backfill early airdrop expiry dates: ' . $e->getMessage());
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

    if (!tableHasColumn('mini_tasks', 'learning_title')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN learning_title VARCHAR(255) NOT NULL DEFAULT '' AFTER cta_label");
    }

    if (!tableHasColumn('mini_tasks', 'learning_url')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN learning_url VARCHAR(500) NOT NULL DEFAULT '' AFTER learning_title");
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

    // DB tasks are now the single source of truth.
    // No hardcoded seeding needed - tasks are managed via admin panel.
    // If the mini_tasks table is empty, a seed script (tools/seed_taskhub_tasks.php) should be run.

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
    $allowed_statuses = ['pending', 'locked', 'available', 'claimed', 'expired'];
    return in_array($status, $allowed_statuses, true) ? $status : 'pending';
}

function getLedgerDisplayBalance($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND status IN ('available', 'locked')
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

function addRewardLedgerEntry($user_id, $amount, $source, $action_type = 'credit', $status = 'available', $reference_id = null, PDO $db = null, $reward_phase = null, $user_level_at_time = null, $expires_at = null) {
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
    $expires_at = $expires_at !== null && trim((string) $expires_at) !== ''
        ? substr(trim((string) $expires_at), 0, 19)
        : null;

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
        INSERT INTO reward_ledger (user_id, source, reward_phase, action_type, amount, status, reference_id, user_level_at_time, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $source, $reward_phase, $action_type, $amount, $status, $reference_id, $user_level_at_time, $expires_at]);
    // Capture this immediately: notification creation below performs its own
    // INSERT and would otherwise replace PDO::lastInsertId().
    $ledger_entry_id = (int) $db->lastInsertId();

    if ($amount > 0 && $action_type === 'early_adopter_airdrop' && $status === 'pending') {
        $expiry_label = $expires_at ? date('M d, Y', strtotime($expires_at)) : ((int) EARLY_AIRDROP_UNLOCK_DAYS . ' days');
        createNotification('user', $user_id, [
            'template_key' => null,
            'event_key' => 'early_airdrop.reserved',
            'title' => 'Early Adopter reward reserved',
            'message' => number_format($amount, 0) . ' $REX has been reserved for you. Complete LearnHub and reach PRO Level within ' . (int) EARLY_AIRDROP_UNLOCK_DAYS . ' days, by ' . $expiry_label . ', to unlock it permanently. If not completed, it returns to the Early Adopter pool.',
            'action_url' => '/dashboard.php',
            'priority' => 'high',
            'meta' => [
                'source' => $source,
                'reference_id' => $reference_id,
                'status' => $status,
                'expires_at' => $expires_at,
            ],
        ], $db);
    } elseif ($amount > 0) {
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
        'id' => $ledger_entry_id,
        'user_id' => $user_id,
        'amount' => number_format($amount, 8, '.', ''),
        'source' => $source,
        'reward_phase' => $reward_phase,
        'action_type' => $action_type,
        'status' => $status,
        'reference_id' => $reference_id,
        'user_level_at_time' => $user_level_at_time,
        'expires_at' => $expires_at,
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

function generateClaimSnapshotForUser($user_id, PDO $db = null, $claim_amount = null) {
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

        $available_balance = getRewardLedgerBalance($user_id, 'available', $db);
        $requested_amount = $claim_amount !== null ? round((float) $claim_amount, 8) : $available_balance;
        if ($requested_amount <= 0) {
            throw new RuntimeException('Claim amount must be greater than zero.');
        }
        if ($requested_amount > $available_balance) {
            throw new RuntimeException('Claim amount cannot exceed your available REX balance.');
        }

        $ledger_stmt = $db->prepare("
            SELECT id, source, reward_phase, action_type, amount, reference_id, user_level_at_time
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

        $remaining_amount = $requested_amount;
        $lock_rows = [];
        $split_row = null;
        foreach ($rows as $row) {
            if ($remaining_amount <= 0) {
                break;
            }

            $row_amount = round((float) ($row['amount'] ?? 0), 8);
            if ($row_amount <= 0) {
                continue;
            }

            if ($row_amount <= $remaining_amount + 0.00000001) {
                $lock_rows[] = $row;
                $remaining_amount = round($remaining_amount - $row_amount, 8);
                continue;
            }

            $split_row = [
                'row' => $row,
                'claim_amount' => $remaining_amount,
                'remaining_amount' => round($row_amount - $remaining_amount, 8),
            ];
            $remaining_amount = 0.0;
        }

        if ($remaining_amount > 0.00000001) {
            throw new RuntimeException('Claim amount cannot exceed your available REX balance.');
        }

        $total_amount = $requested_amount;
        $nonce = generateUniqueClaimNonce($db);
        $insert_snapshot = $db->prepare("
            INSERT INTO claim_snapshots (user_id, total_amount, nonce, status)
            VALUES (?, ?, ?, 'generated')
        ");
        $insert_snapshot->execute([$user_id, $total_amount, $nonce]);
        $snapshot_id = (int) $db->lastInsertId();
        $snapshot_reference = 'claim_snapshot:' . $snapshot_id;

        if (!empty($lock_rows)) {
            $ledger_ids = array_map(static function ($row) {
                return (int) $row['id'];
            }, $lock_rows);
            $placeholders = implode(',', array_fill(0, count($ledger_ids), '?'));
            $update_params = array_merge([$snapshot_reference, $user_id], $ledger_ids);
            $lock_rewards = $db->prepare("
                UPDATE reward_ledger
                SET status = 'locked',
                    reference_id = ?
                WHERE user_id = ?
                  AND status = 'available'
                  AND id IN ($placeholders)
            ");
            $lock_rewards->execute($update_params);

            if ($lock_rewards->rowCount() !== count($ledger_ids)) {
                throw new RuntimeException('Unable to lock every reward row for this claim.');
            }
        }

        if ($split_row) {
            $source_row = $split_row['row'];
            $reduce_row = $db->prepare("
                UPDATE reward_ledger
                SET amount = ?
                WHERE id = ?
                  AND user_id = ?
                  AND status = 'available'
            ");
            $reduce_row->execute([
                number_format((float) $split_row['remaining_amount'], 8, '.', ''),
                (int) $source_row['id'],
                $user_id,
            ]);
            if ($reduce_row->rowCount() !== 1) {
                throw new RuntimeException('Unable to split the selected reward row.');
            }

            $insert_locked = $db->prepare("
                INSERT INTO reward_ledger
                    (user_id, source, reward_phase, action_type, amount, status, reference_id, user_level_at_time)
                VALUES
                    (?, ?, ?, ?, ?, 'locked', ?, ?)
            ");
            $insert_locked->execute([
                $user_id,
                (string) ($source_row['source'] ?? 'manual'),
                (string) ($source_row['reward_phase'] ?? 'phase1'),
                (string) ($source_row['action_type'] ?? 'claim_split'),
                number_format((float) $split_row['claim_amount'], 8, '.', ''),
                $snapshot_reference,
                (string) ($source_row['user_level_at_time'] ?? 'beginner'),
            ]);
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

function markClaimSnapshotSubmitted($snapshot_id, $user_id, $tx_hash = '', PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $snapshot_id = (int) $snapshot_id;
    $user_id = (int) $user_id;
    $tx_hash = trim((string) $tx_hash);
    if ($snapshot_id <= 0 || $user_id <= 0) {
        throw new InvalidArgumentException('Invalid claim snapshot.');
    }

    try {
        $db->beginTransaction();

        $snapshot_stmt = $db->prepare("
            SELECT id, status
            FROM claim_snapshots
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $snapshot_stmt->execute([$snapshot_id, $user_id]);
        $snapshot = $snapshot_stmt->fetch();
        if (!$snapshot) {
            throw new RuntimeException('Claim snapshot not found.');
        }

        $was_already_used = (string) ($snapshot['status'] ?? '') === 'used';
        if (!$was_already_used) {
            $snapshot_update = $db->prepare("
                UPDATE claim_snapshots
                SET status = 'used'
                WHERE id = ?
                  AND user_id = ?
            ");
            $snapshot_update->execute([$snapshot_id, $user_id]);
        }

        $snapshot_reference = 'claim_snapshot:' . $snapshot_id;
        $reference_id = $tx_hash !== '' ? 'claim_tx:' . $tx_hash : $snapshot_reference;
        $ledger_update = $db->prepare("
            UPDATE reward_ledger
            SET status = 'claimed',
                reference_id = ?
            WHERE user_id = ?
              AND status = 'locked'
              AND reference_id = ?
        ");
        $ledger_update->execute([$reference_id, $user_id, $snapshot_reference]);

        if ($ledger_update->rowCount() <= 0 && !$was_already_used) {
            $legacy_update = $db->prepare("
                UPDATE reward_ledger
                SET status = 'claimed',
                    reference_id = ?
                WHERE user_id = ?
                  AND status = 'locked'
            ");
            $legacy_update->execute([$reference_id, $user_id]);
            $claimed_rows = (int) $legacy_update->rowCount();
        } else {
            $claimed_rows = (int) $ledger_update->rowCount();
        }

        $db->commit();
        syncLegacyRewardCache($user_id, $db);

        return [
            'snapshot_id' => $snapshot_id,
            'ledger_rows_claimed' => $claimed_rows,
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function expireClaimSnapshotForUser($snapshot_id, $user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $snapshot_id = (int) $snapshot_id;
    $user_id = (int) $user_id;
    if ($snapshot_id <= 0 || $user_id <= 0) {
        throw new InvalidArgumentException('Invalid claim snapshot.');
    }

    try {
        $db->beginTransaction();

        $snapshot_stmt = $db->prepare("
            SELECT id, status
            FROM claim_snapshots
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $snapshot_stmt->execute([$snapshot_id, $user_id]);
        $snapshot = $snapshot_stmt->fetch();
        if (!$snapshot) {
            throw new RuntimeException('Claim snapshot not found.');
        }

        if ((string) ($snapshot['status'] ?? '') === 'used') {
            $db->commit();
            return [
                'snapshot_id' => $snapshot_id,
                'ledger_rows_released' => 0,
                'snapshot_status' => 'used',
            ];
        }

        $snapshot_update = $db->prepare("
            UPDATE claim_snapshots
            SET status = 'expired'
            WHERE id = ?
              AND user_id = ?
              AND status = 'generated'
        ");
        $snapshot_update->execute([$snapshot_id, $user_id]);

        $snapshot_reference = 'claim_snapshot:' . $snapshot_id;
        $release_stmt = $db->prepare("
            UPDATE reward_ledger
            SET status = 'available',
                reference_id = NULL
            WHERE user_id = ?
              AND status = 'locked'
              AND reference_id = ?
        ");
        $release_stmt->execute([$user_id, $snapshot_reference]);
        $released_rows = (int) $release_stmt->rowCount();

        $db->commit();
        syncLegacyRewardCache($user_id, $db);

        return [
            'snapshot_id' => $snapshot_id,
            'ledger_rows_released' => $released_rows,
            'snapshot_status' => 'expired',
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function syncSubmittedClaimTransactionsForUser($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    if ($user_id <= 0 || !tableExists('rex_signer_approval_requests')) {
        return 0;
    }

    $stmt = $db->prepare("
        SELECT result_json, tx_hash
        FROM rex_signer_approval_requests
        WHERE user_id = ?
          AND request_type = 'claim'
          AND status = 'approved'
          AND tx_hash IS NOT NULL
          AND tx_hash <> ''
        ORDER BY id DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);

    $synced = 0;
    foreach ($stmt->fetchAll() as $row) {
        $result = !empty($row['result_json']) ? json_decode((string) $row['result_json'], true) : [];
        if (!is_array($result) || empty($result['snapshot_id'])) {
            continue;
        }

        try {
            markClaimSnapshotSubmitted((int) $result['snapshot_id'], $user_id, (string) ($row['tx_hash'] ?? ''), $db);
            $synced++;
        } catch (Throwable $e) {
            continue;
        }
    }

    return $synced;
}

function syncStaleClaimApprovalsForUser($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    if ($user_id <= 0 || !tableExists('rex_signer_approval_requests')) {
        return 0;
    }

    $stmt = $db->prepare("
        SELECT id, result_json, expires_at
        FROM rex_signer_approval_requests
        WHERE user_id = ?
          AND request_type = 'claim'
          AND status = 'approved'
          AND (tx_hash IS NULL OR tx_hash = '')
          AND expires_at <= NOW()
        ORDER BY id DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);

    $released = 0;
    foreach ($stmt->fetchAll() as $row) {
        $result = !empty($row['result_json']) ? json_decode((string) $row['result_json'], true) : [];
        if (!is_array($result) || empty($result['snapshot_id'])) {
            continue;
        }

        if (!empty($result['tx_status']) && (string) $result['tx_status'] !== 'pending') {
            continue;
        }

        try {
            $claim_update = expireClaimSnapshotForUser((int) $result['snapshot_id'], $user_id, $db);
            $result['tx_status'] = 'failed';
            $result['tx_error'] = 'Claim transaction was not submitted. Add POL for gas, then try again.';
            $result['tx_reported_at'] = date('c');
            $result['claim_snapshot_status'] = 'expired';
            $result['ledger_status'] = 'available';
            $result['claim_update'] = $claim_update;

            $update = $db->prepare("
                UPDATE rex_signer_approval_requests
                SET result_json = ?,
                    completed_at = COALESCE(completed_at, NOW())
                WHERE id = ?
                  AND user_id = ?
            ");
            $update->execute([
                json_encode($result, JSON_UNESCAPED_SLASHES),
                (int) $row['id'],
                $user_id,
            ]);
            $released++;
        } catch (Throwable $e) {
            continue;
        }
    }

    return $released;
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

    $testing_claim_mode = defined('TESTING_MODE') && TESTING_MODE;
    $level_state = getUserLevelState($user, $db);
    if (!$testing_claim_mode && !in_array((string) ($level_state['level'] ?? 'beginner'), ['pro', 'expert'], true)) {
        return ['eligible' => false, 'message' => 'Claim unlocks once your account reaches Pro level.'];
    }

    $balance = getRewardLedgerBalance((int) $user_id, 'available', $db);
    if ($testing_claim_mode && $balance <= 0) {
        return ['eligible' => false, 'message' => 'TESTING_MODE: Add any available REX reward before testing claim.'];
    }
    if (!$testing_claim_mode && $balance < (float) REWARD_CLAIM_MINIMUM_REX) {
        return ['eligible' => false, 'message' => 'Minimum claim threshold has not been reached yet.'];
    }

    $signals = getUserSecuritySignals((int) $user_id, $db);
    if (!empty($signals['is_suspicious'])) {
        return ['eligible' => false, 'message' => 'Claim is temporarily unavailable. We detected activity that may violate our abuse-prevention rules. If you believe this is a mistake, please contact support.', 'signals' => $signals];
    }

    return [
        'eligible' => true,
        'message' => $testing_claim_mode ? 'TESTING_MODE: Claim snapshot can be generated for contract testing.' : 'Claim snapshot can be generated.',
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

// ============================================================
// EARLY ADOPTER AIRDROP FUNCTIONS
// ============================================================

/**
 * Ensure the early airdrop schema tables exist.
 */
function ensureEarlyAirdropSchema(PDO $db = null) {
    static $schema_ready = false;
    if ($schema_ready) {
        return;
    }
    $db = $db ?: getDBConnection();

    if (!tableExists('early_airdrop_pool')) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS early_airdrop_pool (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                remaining_rex DECIMAL(18,8) NOT NULL DEFAULT 80000000.00000000,
                total_allocated_signup DECIMAL(18,8) NOT NULL DEFAULT 0,
                total_allocated_referral DECIMAL(18,8) NOT NULL DEFAULT 0,
                signup_count INT UNSIGNED NOT NULL DEFAULT 0,
                referral_count INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        // Seed the pool
        $db->exec("INSERT IGNORE INTO early_airdrop_pool (id, remaining_rex) VALUES (1, " . EARLY_AIRDROP_POOL_TOTAL . ")");
    }

    if (!tableExists('early_airdrop_claims')) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS early_airdrop_claims (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT UNSIGNED NOT NULL,
                claim_type ENUM('signup_bonus', 'referral_bonus') NOT NULL,
                amount DECIMAL(18,8) NOT NULL,
                reference_id VARCHAR(100) NULL,
                claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_early_claims_user (user_id),
                KEY idx_early_claims_type (claim_type),
                KEY idx_early_claims_claimed_at (claimed_at),
                CONSTRAINT fk_early_claims_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    try {
        $db->exec("ALTER TABLE early_airdrop_claims ADD UNIQUE KEY uq_early_claims_reference (reference_id)");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1061') === false && stripos($e->getMessage(), 'Duplicate key name') === false) {
            error_log('Could not add early airdrop reference unique key: ' . $e->getMessage());
        }
    }

    try {
        $pool_stmt = $db->query("SELECT remaining_rex, total_allocated_signup, total_allocated_referral FROM early_airdrop_pool WHERE id = 1");
        $pool = $pool_stmt ? $pool_stmt->fetch() : null;
        if ($pool) {
            $allocated = round((float) ($pool['total_allocated_signup'] ?? 0) + (float) ($pool['total_allocated_referral'] ?? 0), 8);
            $target_remaining = max(0, round((float) EARLY_AIRDROP_POOL_TOTAL - $allocated, 8));
            $current_remaining = round((float) ($pool['remaining_rex'] ?? 0), 8);
            if (abs($current_remaining - $target_remaining) > 0.000001) {
                $active = $target_remaining >= (float) EARLY_AIRDROP_SIGNUP_BONUS ? 1 : 0;
                $sync_stmt = $db->prepare("UPDATE early_airdrop_pool SET remaining_rex = ?, is_active = ?, updated_at = NOW() WHERE id = 1");
                $sync_stmt->execute([$target_remaining, $active]);
            }
        }
    } catch (Throwable $e) {
        error_log('Could not sync early airdrop pool total: ' . $e->getMessage());
    }

    $schema_ready = true;
}

/**
 * Check if the early airdrop pool is still active and has enough funds.
 */
function isEarlyAirdropActive(PDO $db = null): bool {
    $db = $db ?: getDBConnection();
    ensureEarlyAirdropSchema($db);

    try {
        $stmt = $db->query("SELECT remaining_rex, is_active FROM early_airdrop_pool WHERE id = 1");
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return (int) ($row['is_active'] ?? 0) === 1 && (float) ($row['remaining_rex'] ?? 0) >= EARLY_AIRDROP_SIGNUP_BONUS;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Get the remaining amount in the early airdrop pool.
 */
function getEarlyAirdropPoolRemaining(PDO $db = null): float {
    $db = $db ?: getDBConnection();
    ensureEarlyAirdropSchema($db);

    try {
        $stmt = $db->query("SELECT remaining_rex FROM early_airdrop_pool WHERE id = 1");
        $row = $stmt->fetch();
        return $row ? (float) ($row['remaining_rex'] ?? 0) : (float) EARLY_AIRDROP_POOL_TOTAL;
    } catch (Throwable $e) {
        return (float) EARLY_AIRDROP_POOL_TOTAL;
    }
}

/**
 * Get the current early airdrop pool state.
 */
function getEarlyAirdropPoolState(PDO $db = null): array {
    $db = $db ?: getDBConnection();
    ensureEarlyAirdropSchema($db);

    try {
        $stmt = $db->query("SELECT * FROM early_airdrop_pool WHERE id = 1");
        $row = $stmt->fetch();
        if (!$row) {
            return [
                'remaining_rex' => EARLY_AIRDROP_POOL_TOTAL,
                'total_allocated_signup' => 0,
                'total_allocated_referral' => 0,
                'signup_count' => 0,
                'referral_count' => 0,
                'is_active' => 1,
            ];
        }
        return $row;
    } catch (Throwable $e) {
        return [
            'remaining_rex' => 0,
            'total_allocated_signup' => 0,
            'total_allocated_referral' => 0,
            'signup_count' => 0,
            'referral_count' => 0,
            'is_active' => 0,
        ];
    }
}

function earlyAirdropClaimExists(?string $reference_id, PDO $db = null): bool {
    $reference_id = $reference_id !== null ? trim($reference_id) : '';
    if ($reference_id === '') {
        return false;
    }

    $db = $db ?: getDBConnection();
    ensureEarlyAirdropSchema($db);

    $stmt = $db->prepare("SELECT id FROM early_airdrop_claims WHERE reference_id = ? LIMIT 1");
    $stmt->execute([substr($reference_id, 0, 100)]);
    return (bool) $stmt->fetch();
}

function reverseEarlyAirdropReservation(int $ledger_id, PDO $db = null): bool {
    $db = $db ?: getDBConnection();
    ensureEarlyAirdropSchema($db);

    $started_transaction = !$db->inTransaction();
    $savepoint = $started_transaction ? null : 'early_airdrop_pool_op';
    try {
        if ($started_transaction) {
            $db->beginTransaction();
        } else {
            $db->exec("SAVEPOINT {$savepoint}");
        }

        $stmt = $db->prepare("
            SELECT id, user_id, amount, reference_id
            FROM reward_ledger
            WHERE id = ?
              AND action_type = 'early_adopter_airdrop'
              AND status = 'pending'
            FOR UPDATE
        ");
        $stmt->execute([(int) $ledger_id]);
        $ledger = $stmt->fetch();
        if (!$ledger) {
            if ($started_transaction && $db->inTransaction()) {
                $db->rollBack();
            } elseif ($savepoint !== null) {
                $db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            }
            return false;
        }

        $amount = round((float) ($ledger['amount'] ?? 0), 8);
        $reference_id = trim((string) ($ledger['reference_id'] ?? ''));
        if ($amount <= 0 || $reference_id === '') {
            $db->prepare("UPDATE reward_ledger SET status = 'expired' WHERE id = ?")->execute([(int) $ledger_id]);
            if ($started_transaction && $db->inTransaction()) {
                $db->commit();
            } elseif ($savepoint !== null) {
                $db->exec("RELEASE SAVEPOINT {$savepoint}");
            }
            return true;
        }

        $claim_stmt = $db->prepare("
            SELECT id
            FROM early_airdrop_claims
            WHERE reference_id = ?
              AND claim_type = 'signup_bonus'
            LIMIT 1
            FOR UPDATE
        ");
        $claim_stmt->execute([substr($reference_id, 0, 100)]);
        $claim = $claim_stmt->fetch();

        if ($claim) {
            $db->prepare("DELETE FROM early_airdrop_claims WHERE id = ?")->execute([(int) $claim['id']]);
            $db->prepare("
                UPDATE early_airdrop_pool
                SET remaining_rex = LEAST(?, remaining_rex + ?),
                    total_allocated_signup = GREATEST(0, total_allocated_signup - ?),
                    signup_count = GREATEST(0, signup_count - 1),
                    is_active = CASE WHEN LEAST(?, remaining_rex + ?) >= ? THEN 1 ELSE is_active END,
                    updated_at = NOW()
                WHERE id = 1
            ")->execute([
                (float) EARLY_AIRDROP_POOL_TOTAL,
                $amount,
                $amount,
                (float) EARLY_AIRDROP_POOL_TOTAL,
                $amount,
                (float) EARLY_AIRDROP_SIGNUP_BONUS,
            ]);
        }

        $db->prepare("UPDATE reward_ledger SET status = 'expired' WHERE id = ?")->execute([(int) $ledger_id]);
        syncLegacyRewardCache((int) ($ledger['user_id'] ?? 0), $db);

        if ($started_transaction && $db->inTransaction()) {
            $db->commit();
        } elseif ($savepoint !== null) {
            $db->exec("RELEASE SAVEPOINT {$savepoint}");
        }
        return true;
    } catch (Throwable $e) {
        if ($started_transaction && $db->inTransaction()) {
            $db->rollBack();
        } elseif ($savepoint !== null && $db->inTransaction()) {
            try {
                $db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            } catch (Throwable $rollback_error) {
                error_log('Early airdrop reverse savepoint rollback failed: ' . $rollback_error->getMessage());
            }
        }
        error_log('Early airdrop reservation reverse failed: ' . $e->getMessage());
        return false;
    }
}

function expireEarlyAirdropReservations(PDO $db = null, ?int $user_id = null): int {
    $db = $db ?: getDBConnection();
    ensureEarlyAirdropSchema($db);

    $where_user = $user_id !== null && $user_id > 0 ? ' AND user_id = ?' : '';
    $stmt = $db->prepare("
        SELECT id
        FROM reward_ledger
        WHERE action_type = 'early_adopter_airdrop'
          AND status = 'pending'
          AND expires_at IS NOT NULL
          AND expires_at < NOW()
          {$where_user}
        ORDER BY id ASC
        LIMIT 500
    ");
    $params = $where_user !== '' ? [(int) $user_id] : [];
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $expired = 0;
    foreach ($rows as $row) {
        if (reverseEarlyAirdropReservation((int) ($row['id'] ?? 0), $db)) {
            $expired++;
        }
    }

    return $expired;
}

function unlockPendingEarlyAirdropForUser(int $user_id, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);
    expireEarlyAirdropReservations($db, $user_id);

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['unlocked' => false, 'amount' => 0.0, 'message' => 'Invalid user.'];
    }

    $user = getUserById($user_id);
    if (!$user) {
        return ['unlocked' => false, 'amount' => 0.0, 'message' => 'User account not found.'];
    }

    $level = normalizeUserLevel($user['level'] ?? 'beginner');
    if (!in_array($level, ['pro', 'expert'], true)) {
        return ['unlocked' => false, 'amount' => 0.0, 'message' => 'Airdrop unlocks at Pro level.'];
    }

    if (!taskHubMissionCompleted($user_id, $db)) {
        return ['unlocked' => false, 'amount' => 0.0, 'message' => 'Complete all TaskHub days to unlock the early airdrop.'];
    }

    $pending_stmt = $db->prepare("
        SELECT id, amount, action_type, reference_id
        FROM reward_ledger
        WHERE user_id = ?
          AND status = 'pending'
          AND action_type IN ('early_adopter_airdrop', 'early_adopter_referral')
        ORDER BY id ASC
    ");
    $pending_stmt->execute([$user_id]);
    $pending_rows = $pending_stmt->fetchAll();
    if (empty($pending_rows)) {
        syncLegacyRewardCache($user_id, $db);
        return ['unlocked' => false, 'amount' => 0.0, 'message' => 'No pending early airdrop rewards.'];
    }

    $unlocked_amount = 0.0;
    foreach ($pending_rows as $row) {
        $amount = round((float) ($row['amount'] ?? 0), 8);
        if ($amount <= 0) {
            continue;
        }

        $claim_type = (string) ($row['action_type'] ?? '') === 'early_adopter_referral'
            ? 'referral_bonus'
            : 'signup_bonus';
        $reference_id = trim((string) ($row['reference_id'] ?? ''));

        if (!earlyAirdropClaimExists($reference_id, $db) && !deductEarlyAirdropPool($user_id, $claim_type, $amount, $db, $reference_id !== '' ? $reference_id : null)) {
            continue;
        }

        $update_stmt = $db->prepare("
            UPDATE reward_ledger
            SET status = 'available',
                user_level_at_time = ?
            WHERE id = ?
              AND user_id = ?
              AND status = 'pending'
        ");
        $update_stmt->execute([$level, (int) $row['id'], $user_id]);
        if ($update_stmt->rowCount() > 0) {
            $unlocked_amount = round($unlocked_amount + $amount, 8);
        }
    }

    syncLegacyRewardCache($user_id, $db);

    return [
        'unlocked' => $unlocked_amount > 0,
        'amount' => $unlocked_amount,
        'message' => $unlocked_amount > 0 ? 'Early airdrop unlocked.' : 'No early airdrop rewards were unlocked.',
    ];
}

/**
 * Deduct from the early airdrop pool and log the claim.
 * Returns true if deduction succeeded, false if pool is insufficient.
 */
function deductEarlyAirdropPool(int $user_id, string $claim_type, float $amount, PDO $db = null, ?string $reference_id = null): bool {
    $db = $db ?: getDBConnection();
    ensureEarlyAirdropSchema($db);

    $started_transaction = !$db->inTransaction();
    $savepoint = $started_transaction ? null : 'early_airdrop_pool_op';
    try {
        if ($started_transaction) {
            $db->beginTransaction();
        } else {
            $db->exec("SAVEPOINT {$savepoint}");
        }

        // Lock the pool row for update
        $stmt = $db->query("SELECT remaining_rex, is_active FROM early_airdrop_pool WHERE id = 1 FOR UPDATE");
        $pool = $stmt->fetch();

        if (!$pool) {
            if ($started_transaction && $db->inTransaction()) {
                $db->rollBack();
            } elseif ($savepoint !== null) {
                $db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            }
            return false;
        }

        if ((int) ($pool['is_active'] ?? 0) !== 1) {
            if ($started_transaction && $db->inTransaction()) {
                $db->rollBack();
            } elseif ($savepoint !== null) {
                $db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            }
            return false;
        }

        $remaining = (float) ($pool['remaining_rex'] ?? 0);
        if ($remaining < $amount) {
            // Deactivate pool since it's exhausted
            $db->exec("UPDATE early_airdrop_pool SET is_active = 0 WHERE id = 1");
            if ($started_transaction && $db->inTransaction()) {
                $db->rollBack();
            } elseif ($savepoint !== null) {
                $db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            }
            return false;
        }

        $reference_id = $reference_id !== null && trim($reference_id) !== ''
            ? substr(trim($reference_id), 0, 100)
            : 'early_airdrop:' . $claim_type . ':' . $user_id;
        $existing_claim = $db->prepare("SELECT id FROM early_airdrop_claims WHERE reference_id = ? LIMIT 1");
        $existing_claim->execute([$reference_id]);
        if ($existing_claim->fetch()) {
            if ($started_transaction && $db->inTransaction()) {
                $db->rollBack();
            } elseif ($savepoint !== null) {
                $db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            }
            return false;
        }

        // Deduct from pool
        if ($claim_type === 'signup_bonus') {
            $update_sql = "UPDATE early_airdrop_pool SET 
                remaining_rex = remaining_rex - ?,
                total_allocated_signup = total_allocated_signup + ?,
                signup_count = signup_count + 1
                WHERE id = 1";
        } else {
            $update_sql = "UPDATE early_airdrop_pool SET 
                remaining_rex = remaining_rex - ?,
                total_allocated_referral = total_allocated_referral + ?,
                referral_count = referral_count + 1
                WHERE id = 1";
        }
        $stmt = $db->prepare($update_sql);
        $stmt->execute([$amount, $amount]);

        // Log the claim
        $stmt = $db->prepare("INSERT INTO early_airdrop_claims (user_id, claim_type, amount, reference_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $claim_type, $amount, $reference_id]);

        // Check if pool is now exhausted
        $stmt = $db->query("SELECT remaining_rex FROM early_airdrop_pool WHERE id = 1");
        $new_remaining = (float) ($stmt->fetch()['remaining_rex'] ?? 0);
        if ($new_remaining < EARLY_AIRDROP_SIGNUP_BONUS) {
            $db->exec("UPDATE early_airdrop_pool SET is_active = 0 WHERE id = 1");
        }

        if ($started_transaction && $db->inTransaction()) {
            $db->commit();
        } elseif ($savepoint !== null) {
            $db->exec("RELEASE SAVEPOINT {$savepoint}");
        }
        return true;
    } catch (Throwable $e) {
        if ($started_transaction && $db->inTransaction()) {
            $db->rollBack();
        } elseif ($savepoint !== null && $db->inTransaction()) {
            try {
                $db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            } catch (Throwable $rollback_error) {
                error_log('Early airdrop savepoint rollback failed: ' . $rollback_error->getMessage());
            }
        }
        return false;
    }
}

/**
 * Get the pending airdrop amount for a user (status = 'pending' in reward_ledger).
 */
function getPendingAirdropAmount(int $user_id, PDO $db = null): float {
    $db = $db ?: getDBConnection();
    try {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total
            FROM reward_ledger
            WHERE user_id = ?
              AND status = 'pending'
              AND action_type IN ('early_adopter_airdrop', 'early_adopter_referral')
        ");
        $stmt->execute([$user_id]);
        return (float) ($stmt->fetch()['total'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function getPendingAirdropDetails(int $user_id, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    expireEarlyAirdropReservations($db, $user_id);

    try {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total,
                   MIN(expires_at) AS expires_at
            FROM reward_ledger
            WHERE user_id = ?
              AND status = 'pending'
              AND action_type = 'early_adopter_airdrop'
        ");
        $stmt->execute([(int) $user_id]);
        $row = $stmt->fetch() ?: [];
        $expires_at = (string) ($row['expires_at'] ?? '');
        $expires_ts = $expires_at !== '' ? strtotime($expires_at) : false;
        $seconds_remaining = $expires_ts ? max(0, $expires_ts - time()) : null;

        return [
            'amount' => (float) ($row['total'] ?? 0),
            'expires_at' => $expires_at,
            'days_remaining' => $seconds_remaining !== null ? (int) ceil($seconds_remaining / 86400) : null,
        ];
    } catch (Throwable $e) {
        return [
            'amount' => 0.0,
            'expires_at' => '',
            'days_remaining' => null,
        ];
    }
}

/**
 * Get the user's TaskHub progress for airdrop unlock display.
 */
function getAirdropProgress(int $user_id, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    try {
        $stmt = $db->prepare("SELECT current_day, last_day_completed_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        $current_day = (int) ($user['current_day'] ?? 1);
        $completed_days = max(0, $current_day - 1);
        $total_days = TASKHUB_TOTAL_DAYS;
        $progress = $total_days > 0 ? round(($completed_days / $total_days) * 100, 1) : 0;

        return [
            'completed_days' => $completed_days,
            'total_days' => $total_days,
            'progress' => $progress,
            'is_completed' => $completed_days >= $total_days,
        ];
    } catch (Throwable $e) {
        return [
            'completed_days' => 0,
            'total_days' => TASKHUB_TOTAL_DAYS,
            'progress' => 0,
            'is_completed' => false,
        ];
    }
}
