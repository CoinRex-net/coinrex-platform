<?php
/** Investor metrics and lightweight activity tracking. */

function coinrexMetricsTableExists(string $table): bool {
    return function_exists('tableExists') && tableExists($table);
}

function coinrexMetricsColumnExists(string $table, string $column): bool {
    return function_exists('tableHasColumn') && tableHasColumn($table, $column);
}

function coinrexMetricsScalar(PDO $db, string $sql, array $params = [], $default = 0) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null ? $default : $value;
    } catch (Throwable $e) {
        error_log('CoinRex metrics scalar failed: ' . $e->getMessage());
        return $default;
    }
}

function coinrexMetricsRows(PDO $db, string $sql, array $params = []): array {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        error_log('CoinRex metrics rows failed: ' . $e->getMessage());
        return [];
    }
}

function coinrexMetricsPercent(float $part, float $total): float {
    return $total > 0 ? round(($part / $total) * 100, 1) : 0.0;
}

function ensureInvestorMetricsSchema(PDO $db = null): void {
    static $ready = false;
    if ($ready) {
        return;
    }

    $db = $db ?: getDBConnection();
    if (!coinrexMetricsTableExists('users')) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS user_activity_days (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            activity_date DATE NOT NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'web',
            first_seen_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            activity_count INT UNSIGNED NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_activity_day (user_id, activity_date, source),
            KEY idx_user_activity_date (activity_date),
            KEY idx_user_activity_user_date (user_id, activity_date),
            CONSTRAINT fk_user_activity_days_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS user_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            session_key_hash CHAR(64) NOT NULL,
            source VARCHAR(30) NOT NULL DEFAULT 'web',
            started_at DATETIME NOT NULL,
            last_seen_at DATETIME NOT NULL,
            duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            ip_hash CHAR(64) NULL,
            user_agent_hash CHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_sessions_key (session_key_hash),
            KEY idx_user_sessions_user_seen (user_id, last_seen_at),
            KEY idx_user_sessions_started (started_at),
            KEY idx_user_sessions_source (source),
            CONSTRAINT fk_user_sessions_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS investor_metric_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token_code VARCHAR(32) NULL,
            token_hash CHAR(64) NOT NULL,
            label VARCHAR(120) NOT NULL DEFAULT 'Investor link',
            status ENUM('active','revoked') NOT NULL DEFAULT 'active',
            created_by_admin_id INT UNSIGNED NULL,
            last_accessed_at DATETIME NULL,
            access_count INT UNSIGNED NOT NULL DEFAULT 0,
            revoked_at DATETIME NULL,
            revoked_by_admin_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_investor_metric_tokens_code (token_code),
            UNIQUE KEY uq_investor_metric_tokens_hash (token_hash),
            KEY idx_investor_metric_tokens_status (status),
            KEY idx_investor_metric_tokens_created_at (created_at),
            CONSTRAINT fk_investor_metric_tokens_created_by
                FOREIGN KEY (created_by_admin_id) REFERENCES admins(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE,
            CONSTRAINT fk_investor_metric_tokens_revoked_by
                FOREIGN KEY (revoked_by_admin_id) REFERENCES admins(id)
                ON DELETE SET NULL
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!coinrexMetricsColumnExists('investor_metric_tokens', 'token_code')) {
        $db->exec("ALTER TABLE investor_metric_tokens ADD COLUMN token_code VARCHAR(32) NULL AFTER id");
        try {
            $db->exec("ALTER TABLE investor_metric_tokens ADD UNIQUE KEY uq_investor_metric_tokens_code (token_code)");
        } catch (Throwable $e) {
            error_log('Could not add investor token code unique key: ' . $e->getMessage());
        }
    }

    $ready = true;
}

function coinrexInvestorMetricTokenChars(): string {
    return 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
}

function coinrexGenerateInvestorMetricToken(int $length = 12): string {
    $chars = coinrexInvestorMetricTokenChars();
    $max = strlen($chars) - 1;
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[random_int(0, $max)];
    }
    return $token;
}

function coinrexNormalizeInvestorMetricToken($token): string {
    $token = trim((string) $token);
    return preg_match('/^[A-Za-z0-9]{8,32}$/', $token) ? $token : '';
}

function coinrexHashInvestorMetricToken($token): string {
    return hash('sha256', (string) $token);
}

function createInvestorMetricShareToken(string $label, int $admin_id = 0, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    ensureInvestorMetricsSchema($db);

    $label = substr(trim($label), 0, 120);
    if ($label === '') {
        $label = 'Investor link';
    }

    for ($attempt = 0; $attempt < 8; $attempt++) {
        $token = coinrexGenerateInvestorMetricToken(12);
        $hash = coinrexHashInvestorMetricToken($token);
        try {
            $stmt = $db->prepare("
                INSERT INTO investor_metric_tokens (token_code, token_hash, label, created_by_admin_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$token, $hash, $label, $admin_id > 0 ? $admin_id : null]);
            return [
                'success' => true,
                'id' => (int) $db->lastInsertId(),
                'token' => $token,
                'url' => (defined('ADMIN_BASE_URL') ? ADMIN_BASE_URL : '') . '/metrics.php?v=' . rawurlencode($token),
            ];
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1062') === false && stripos($e->getMessage(), 'Duplicate') === false) {
                throw $e;
            }
        }
    }

    return ['success' => false, 'message' => 'Could not generate a unique token.'];
}

function validateInvestorMetricShareToken($token, PDO $db = null): ?array {
    $token = coinrexNormalizeInvestorMetricToken($token);
    if ($token === '') {
        return null;
    }

    $db = $db ?: getDBConnection();
    ensureInvestorMetricsSchema($db);
    $stmt = $db->prepare("
        SELECT *
        FROM investor_metric_tokens
        WHERE token_hash = ?
          AND status = 'active'
          AND revoked_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([coinrexHashInvestorMetricToken($token)]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function touchInvestorMetricShareToken(int $token_id, PDO $db = null): void {
    if ($token_id <= 0) {
        return;
    }
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        UPDATE investor_metric_tokens
        SET last_accessed_at = NOW(),
            access_count = access_count + 1,
            updated_at = NOW()
        WHERE id = ?
          AND status = 'active'
    ");
    $stmt->execute([$token_id]);
}

function revokeInvestorMetricShareToken(int $token_id, int $admin_id = 0, PDO $db = null): bool {
    if ($token_id <= 0) {
        return false;
    }
    $db = $db ?: getDBConnection();
    ensureInvestorMetricsSchema($db);
    $stmt = $db->prepare("
        UPDATE investor_metric_tokens
        SET status = 'revoked',
            revoked_at = NOW(),
            revoked_by_admin_id = ?,
            updated_at = NOW()
        WHERE id = ?
          AND status = 'active'
    ");
    $stmt->execute([$admin_id > 0 ? $admin_id : null, $token_id]);
    return $stmt->rowCount() > 0;
}

function deleteInvestorMetricShareToken(int $token_id, PDO $db = null): bool {
    if ($token_id <= 0) {
        return false;
    }
    $db = $db ?: getDBConnection();
    ensureInvestorMetricsSchema($db);
    $stmt = $db->prepare("DELETE FROM investor_metric_tokens WHERE id = ?");
    $stmt->execute([$token_id]);
    return $stmt->rowCount() > 0;
}

function getInvestorMetricShareTokens(PDO $db = null): array {
    $db = $db ?: getDBConnection();
    ensureInvestorMetricsSchema($db);
    return coinrexMetricsRows($db, "
        SELECT imt.*, a.username AS created_by_username, a.email AS created_by_email
        FROM investor_metric_tokens imt
        LEFT JOIN admins a ON a.id = imt.created_by_admin_id
        ORDER BY imt.id DESC
        LIMIT 50
    ");
}

function coinrexMetricsNormalizeSource($source): string {
    $source = strtolower(trim((string) $source));
    $source = preg_replace('/[^a-z0-9_\-]/', '', $source);
    return $source !== '' ? substr($source, 0, 30) : 'web';
}

function recordUserActivity($user_id, $source = 'web', PDO $db = null): bool {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    try {
        $db = $db ?: getDBConnection();
        ensureInvestorMetricsSchema($db);
        $source = coinrexMetricsNormalizeSource($source);

        $stmt = $db->prepare("
            INSERT INTO user_activity_days (user_id, activity_date, source, first_seen_at, last_seen_at, activity_count)
            VALUES (?, CURDATE(), ?, NOW(), NOW(), 1)
            ON DUPLICATE KEY UPDATE
                last_seen_at = NOW(),
                activity_count = activity_count + 1,
                updated_at = NOW()
        ");
        $stmt->execute([$user_id, $source]);
        return true;
    } catch (Throwable $e) {
        error_log('CoinRex activity tracking failed: ' . $e->getMessage());
        return false;
    }
}

function recordUserSessionHeartbeat($user_id, $source = 'web', PDO $db = null): bool {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    try {
        $db = $db ?: getDBConnection();
        ensureInvestorMetricsSchema($db);
        $source = coinrexMetricsNormalizeSource($source);
        $session_key = session_id();
        if ($session_key === '') {
            return false;
        }

        $session_hash = hash('sha256', $user_id . '|' . $source . '|' . $session_key);
        $ip_hash = !empty($_SERVER['REMOTE_ADDR']) ? hash('sha256', (string) $_SERVER['REMOTE_ADDR']) : null;
        $ua_hash = !empty($_SERVER['HTTP_USER_AGENT']) ? hash('sha256', (string) $_SERVER['HTTP_USER_AGENT']) : null;

        $stmt = $db->prepare("
            INSERT INTO user_sessions (user_id, session_key_hash, source, started_at, last_seen_at, duration_seconds, ip_hash, user_agent_hash)
            VALUES (?, ?, ?, NOW(), NOW(), 0, ?, ?)
            ON DUPLICATE KEY UPDATE
                last_seen_at = NOW(),
                duration_seconds = GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, NOW())),
                updated_at = NOW()
        ");
        $stmt->execute([$user_id, $session_hash, $source, $ip_hash, $ua_hash]);
        return true;
    } catch (Throwable $e) {
        error_log('CoinRex session tracking failed: ' . $e->getMessage());
        return false;
    }
}

function recordAuthenticatedUserMetrics($user_id, $source = 'web', PDO $db = null): bool {
    $user_id = (int) $user_id;
    if ($user_id <= 0 || session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $now = time();
    $key = 'coinrex_metrics_touch_' . coinrexMetricsNormalizeSource($source);
    $last = (int) ($_SESSION[$key] ?? 0);
    if ($last > 0 && ($now - $last) < 60) {
        return true;
    }

    $_SESSION[$key] = $now;
    $db = $db ?: getDBConnection();
    recordUserActivity($user_id, $source, $db);
    recordUserSessionHeartbeat($user_id, $source, $db);
    return true;
}

function coinrexMetricsGrowthForWindow(PDO $db, int $days): float {
    $current = (float) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE created_at >= (NOW() - INTERVAL {$days} DAY)", [], 0);
    $previous = (float) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE created_at >= (NOW() - INTERVAL " . ($days * 2) . " DAY) AND created_at < (NOW() - INTERVAL {$days} DAY)", [], 0);
    if ($previous <= 0) {
        return $current > 0 ? 100.0 : 0.0;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

function coinrexMetricsRetentionRate(PDO $db, int $offset_days): float {
    if (!coinrexMetricsTableExists('user_activity_days')) {
        return 0.0;
    }

    $cohort = (float) coinrexMetricsScalar($db, "
        SELECT COUNT(*)
        FROM users
        WHERE DATE(created_at) <= DATE_SUB(CURDATE(), INTERVAL {$offset_days} DAY)
    ", [], 0);

    $retained = (float) coinrexMetricsScalar($db, "
        SELECT COUNT(DISTINCT u.id)
        FROM users u
        INNER JOIN user_activity_days uad
            ON uad.user_id = u.id
           AND uad.activity_date = DATE_ADD(DATE(u.created_at), INTERVAL {$offset_days} DAY)
        WHERE DATE(u.created_at) <= DATE_SUB(CURDATE(), INTERVAL {$offset_days} DAY)
    ", [], 0);

    return coinrexMetricsPercent($retained, $cohort);
}

function getAdminInvestorMetrics(PDO $db, string $window = '30d'): array {
    ensureInvestorMetricsSchema($db);

    $window = strtolower(trim($window));
    $window_days_map = ['today' => 1, '7d' => 7, '30d' => 30, 'all' => 36500];
    $window_days = $window_days_map[$window] ?? 30;
    $window_user_condition = $window === 'all' ? '1=1' : "created_at >= (NOW() - INTERVAL {$window_days} DAY)";
    $window_activity_condition = $window === 'all' ? '1=1' : "activity_date >= DATE_SUB(CURDATE(), INTERVAL " . max(0, $window_days - 1) . " DAY)";
    $analytics_ready = coinrexMetricsTableExists('user_activity_days') && coinrexMetricsTableExists('user_sessions');
    $analytics_has_data = $analytics_ready && (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM user_activity_days", [], 0) > 0;

    $total_users = (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users", [], 0);
    $active_now = coinrexMetricsColumnExists('users', 'last_active')
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE last_active IS NOT NULL AND last_active >= (NOW() - INTERVAL 5 MINUTE)", [], 0)
        : 0;

    $tracked_dau = $analytics_ready ? (int) coinrexMetricsScalar($db, "SELECT COUNT(DISTINCT user_id) FROM user_activity_days WHERE activity_date = CURDATE()", [], 0) : 0;
    $tracked_wau = $analytics_ready ? (int) coinrexMetricsScalar($db, "SELECT COUNT(DISTINCT user_id) FROM user_activity_days WHERE activity_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)", [], 0) : 0;
    $tracked_mau = $analytics_ready ? (int) coinrexMetricsScalar($db, "SELECT COUNT(DISTINCT user_id) FROM user_activity_days WHERE activity_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)", [], 0) : 0;
    $fallback_dau = coinrexMetricsColumnExists('users', 'last_active')
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE last_active IS NOT NULL AND last_active >= CURDATE()", [], 0)
        : 0;
    $fallback_wau = coinrexMetricsColumnExists('users', 'last_active')
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE last_active IS NOT NULL AND last_active >= (NOW() - INTERVAL 7 DAY)", [], 0)
        : 0;
    $fallback_mau = coinrexMetricsColumnExists('users', 'last_active')
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE last_active IS NOT NULL AND last_active >= (NOW() - INTERVAL 30 DAY)", [], 0)
        : 0;
    $dau = max($tracked_dau, $fallback_dau);
    $wau = max($tracked_wau, $fallback_wau);
    $mau = max($tracked_mau, $fallback_mau);
    $activity_quality = $analytics_has_data ? 'Tracked' : 'Live';
    $active_window = $analytics_ready ? (int) coinrexMetricsScalar($db, "SELECT COUNT(DISTINCT user_id) FROM user_activity_days WHERE {$window_activity_condition}", [], 0) : 0;
    if (coinrexMetricsColumnExists('users', 'last_active')) {
        $window_last_active_condition = $window === 'all' ? 'last_active IS NOT NULL' : "last_active IS NOT NULL AND last_active >= (NOW() - INTERVAL {$window_days} DAY)";
        $active_window = max($active_window, (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE {$window_last_active_condition}", [], 0));
    }
    $returning_24h_count = $analytics_ready
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(DISTINCT uad.user_id) FROM user_activity_days uad INNER JOIN users u ON u.id = uad.user_id WHERE uad.activity_date >= CURDATE() AND u.created_at < CURDATE()", [], 0)
        : 0;
    $returning_7d_count = $analytics_ready
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(DISTINCT uad.user_id) FROM user_activity_days uad INNER JOIN users u ON u.id = uad.user_id WHERE uad.activity_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND u.created_at < DATE_SUB(CURDATE(), INTERVAL 6 DAY)", [], 0)
        : 0;
    $returning_30d_count = $analytics_ready
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(DISTINCT uad.user_id) FROM user_activity_days uad INNER JOIN users u ON u.id = uad.user_id WHERE uad.activity_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND u.created_at < DATE_SUB(CURDATE(), INTERVAL 29 DAY)", [], 0)
        : 0;
    if (coinrexMetricsColumnExists('users', 'last_active')) {
        $returning_24h_count = max($returning_24h_count, (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE last_active IS NOT NULL AND last_active >= CURDATE() AND created_at < CURDATE()", [], 0));
        $returning_7d_count = max($returning_7d_count, (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE last_active IS NOT NULL AND last_active >= (NOW() - INTERVAL 7 DAY) AND created_at < (NOW() - INTERVAL 7 DAY)", [], 0));
        $returning_30d_count = max($returning_30d_count, (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE last_active IS NOT NULL AND last_active >= (NOW() - INTERVAL 30 DAY) AND created_at < (NOW() - INTERVAL 30 DAY)", [], 0));
    }
    $session_count = $analytics_ready ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM user_sessions WHERE duration_seconds > 0", [], 0) : 0;

    $learnhub_starts = coinrexMetricsTableExists('taskhub_learning_sessions')
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM taskhub_learning_sessions", [], 0)
        : (int) coinrexMetricsScalar($db, "SELECT COUNT(DISTINCT utl.user_id) FROM user_task_logs utl INNER JOIN mini_tasks mt ON mt.id = utl.task_id WHERE mt.task_group = 'mission'", [], 0);
    $learnhub_completed_sessions = coinrexMetricsTableExists('taskhub_learning_sessions')
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM taskhub_learning_sessions WHERE status = 'completed'", [], 0)
        : 0;
    $learnhub_avg_seconds = coinrexMetricsTableExists('taskhub_learning_sessions')
        ? (int) round((float) coinrexMetricsScalar($db, "SELECT COALESCE(AVG(active_seconds), 0) FROM taskhub_learning_sessions WHERE status = 'completed' AND active_seconds > 0", [], 0))
        : 0;
    $learnhub_active = coinrexMetricsTableExists('taskhub_learning_sessions')
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(DISTINCT user_id) FROM taskhub_learning_sessions WHERE updated_at >= (NOW() - INTERVAL 7 DAY)", [], 0)
        : 0;
    $learnhub_day_rows = coinrexMetricsRows($db, "
        SELECT mission_day, COUNT(DISTINCT user_id) AS total
        FROM user_task_logs
        WHERE status = 'completed'
          AND mission_day BETWEEN 1 AND 10
        GROUP BY mission_day
        ORDER BY mission_day ASC
    ");
    $learnhub_days = array_fill(1, 10, 0);
    foreach ($learnhub_day_rows as $row) {
        $day = (int) ($row['mission_day'] ?? 0);
        if ($day >= 1 && $day <= 10) {
            $learnhub_days[$day] = (int) ($row['total'] ?? 0);
        }
    }
    $learnhub_completed_users = coinrexMetricsColumnExists('users', 'current_day')
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE current_day > 10", [], 0)
        : (int) coinrexMetricsScalar($db, "
            SELECT COUNT(*) FROM (
                SELECT user_id
                FROM user_task_logs
                WHERE status = 'completed' AND mission_day BETWEEN 1 AND 10
                GROUP BY user_id
                HAVING COUNT(DISTINCT mission_day) >= 10
            ) completed_learners
        ", [], 0);

    $boost_completed = (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM user_task_logs utl INNER JOIN mini_tasks mt ON mt.id = utl.task_id WHERE mt.task_group = 'boosthub' AND utl.status = 'completed'", [], 0);
    $boost_today = (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM user_task_logs utl INNER JOIN mini_tasks mt ON mt.id = utl.task_id WHERE mt.task_group = 'boosthub' AND utl.status = 'completed' AND utl.completed_at >= CURDATE()", [], 0);
    $boost_pending = (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM user_task_logs utl INNER JOIN mini_tasks mt ON mt.id = utl.task_id WHERE mt.task_group = 'boosthub' AND utl.status = 'submitted'", [], 0);
    $boost_terminal = (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM user_task_logs utl INNER JOIN mini_tasks mt ON mt.id = utl.task_id WHERE mt.task_group = 'boosthub' AND utl.status IN ('submitted','completed','failed')", [], 0);
    $boost_review_seconds = coinrexMetricsColumnExists('user_task_logs', 'task_completed_at')
        ? (int) round((float) coinrexMetricsScalar($db, "
            SELECT COALESCE(AVG(TIMESTAMPDIFF(SECOND, completed_at, task_completed_at)), 0)
            FROM user_task_logs utl
            INNER JOIN mini_tasks mt ON mt.id = utl.task_id
            WHERE mt.task_group = 'boosthub'
              AND utl.status = 'completed'
              AND utl.task_completed_at IS NOT NULL
              AND utl.completed_at IS NOT NULL
              AND utl.task_completed_at >= utl.completed_at
        ", [], 0))
        : 0;

    $rex_total_requests = coinrexMetricsTableExists('rex_signer_approval_requests')
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM rex_signer_approval_requests", [], 0)
        : 0;
    $rex_success = coinrexMetricsTableExists('rex_signer_approval_requests')
        ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM rex_signer_approval_requests WHERE status = 'approved'", [], 0)
        : 0;

    return [
        'window' => $window,
        'analytics_ready' => $analytics_ready,
        'analytics_has_data' => $analytics_has_data,
        'growth' => [
            'total_users' => $total_users,
            'active_now' => $active_now,
            'dau' => $dau,
            'wau' => $wau,
            'mau' => $mau,
            'activity_quality' => $activity_quality,
            'new_today' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()", [], 0),
            'new_7d' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE created_at >= (NOW() - INTERVAL 7 DAY)", [], 0),
            'new_30d' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE created_at >= (NOW() - INTERVAL 30 DAY)", [], 0),
            'new_window' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE {$window_user_condition}", [], 0),
            'active_window' => $active_window,
            'growth_7d' => coinrexMetricsGrowthForWindow($db, 7),
            'growth_30d' => coinrexMetricsGrowthForWindow($db, 30),
        ],
        'learnhub' => [
            'starts' => $learnhub_starts,
            'completion_rate' => coinrexMetricsPercent($learnhub_completed_sessions, $learnhub_starts),
            'avg_completion_seconds' => $learnhub_avg_seconds,
            'active_learners' => $learnhub_active,
            'pro_users_earned' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE LOWER(COALESCE(level, 'beginner')) IN ('pro','premium')", [], 0),
            'completed_users' => $learnhub_completed_users,
            'day_counts' => $learnhub_days,
        ],
        'boosthub' => [
            'completed' => $boost_completed,
            'today' => $boost_today,
            'pending' => $boost_pending,
            'approval_rate' => coinrexMetricsPercent($boost_completed, $boost_terminal),
            'avg_review_seconds' => $boost_review_seconds,
        ],
        'reviews' => [
            'total' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM reviews", [], 0),
            'approved' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM reviews WHERE status = 'approved'", [], 0),
            'avg_rating' => round((float) coinrexMetricsScalar($db, "SELECT COALESCE(AVG(rating), 0) FROM reviews", [], 0), 2),
            'avg_trust_score' => round((float) coinrexMetricsScalar($db, "SELECT COALESCE(AVG(review_score), 0) FROM reviews", [], 0), 1),
            'today' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM reviews WHERE created_at >= CURDATE()", [], 0),
        ],
        'devhub' => [
            'verified_developers' => (int) coinrexMetricsScalar($db, "SELECT COUNT(DISTINCT user_id) FROM developer_verification WHERE status = 'approved'", [], 0),
            'submitted_projects' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM projects", [], 0),
            'approved_projects' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM projects WHERE approval_status = 'approved'", [], 0),
            'under_review' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM projects WHERE approval_status IN ('pending','under_review')", [], 0),
        ],
        'rexlink' => [
            'wallets_linked' => coinrexMetricsTableExists('rex_signer_sessions')
                ? (int) coinrexMetricsScalar($db, "SELECT COUNT(DISTINCT user_id) FROM rex_signer_sessions", [], 0)
                : (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE wallet_address IS NOT NULL AND wallet_address <> ''", [], 0),
            'sessions_created' => coinrexMetricsTableExists('rex_signer_sessions')
                ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM rex_signer_sessions", [], 0)
                : 0,
            'successful_signatures' => $rex_success,
            'authentication_requests' => $rex_total_requests + (coinrexMetricsTableExists('rex_signer_pairing_codes') ? (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM rex_signer_pairing_codes", [], 0) : 0),
            'success_rate' => coinrexMetricsPercent($rex_success, $rex_total_requests),
            'avg_signing_seconds' => coinrexMetricsTableExists('rex_signer_approval_requests')
                ? (int) round((float) coinrexMetricsScalar($db, "SELECT COALESCE(AVG(TIMESTAMPDIFF(SECOND, created_at, decided_at)), 0) FROM rex_signer_approval_requests WHERE status = 'approved' AND decided_at IS NOT NULL", [], 0))
                : 0,
        ],
        'economy' => [
            'earned' => round((float) coinrexMetricsScalar($db, "SELECT COALESCE(SUM(amount), 0) FROM reward_ledger WHERE amount > 0 AND status IN ('available','locked','claimed')", [], 0), 2),
            'claimed' => round((float) coinrexMetricsScalar($db, "SELECT COALESCE(SUM(amount), 0) FROM reward_ledger WHERE amount > 0 AND status = 'claimed'", [], 0), 2),
            'pending_claims' => round((float) coinrexMetricsScalar($db, "SELECT COALESCE(SUM(amount), 0) FROM reward_ledger WHERE amount > 0 AND status IN ('pending','available')", [], 0), 2),
            'active_referrers' => (int) coinrexMetricsScalar($db, "SELECT COUNT(*) FROM users WHERE valid_referrals > 0", [], 0),
            'valid_referrals' => (int) coinrexMetricsScalar($db, "SELECT COALESCE(SUM(valid_referrals), 0) FROM users", [], 0),
        ],
        'retention' => [
            'cohort_ready' => $analytics_has_data,
            'session_ready' => $session_count > 0,
            'day1' => $analytics_has_data ? coinrexMetricsRetentionRate($db, 1) : null,
            'day7' => $analytics_has_data ? coinrexMetricsRetentionRate($db, 7) : null,
            'day30' => $analytics_has_data ? coinrexMetricsRetentionRate($db, 30) : null,
            'returning_24h' => $dau > 0 ? coinrexMetricsPercent((float) $returning_24h_count, (float) $dau) : null,
            'returning_7d' => $wau > 0 ? coinrexMetricsPercent((float) $returning_7d_count, (float) $wau) : null,
            'returning_30d' => $mau > 0 ? coinrexMetricsPercent((float) $returning_30d_count, (float) $mau) : null,
            'avg_session_seconds' => $session_count > 0 ? (int) round((float) coinrexMetricsScalar($db, "SELECT COALESCE(AVG(duration_seconds), 0) FROM user_sessions WHERE duration_seconds > 0", [], 0)) : null,
        ],
        'cohorts' => $analytics_ready ? coinrexMetricsRows($db, "
            SELECT
                DATE(u.created_at) AS cohort_date,
                COUNT(*) AS new_users,
                SUM(CASE WHEN d1.user_id IS NULL THEN 0 ELSE 1 END) AS d1,
                SUM(CASE WHEN d7.user_id IS NULL THEN 0 ELSE 1 END) AS d7,
                SUM(CASE WHEN d30.user_id IS NULL THEN 0 ELSE 1 END) AS d30
            FROM users u
            LEFT JOIN user_activity_days d1 ON d1.user_id = u.id AND d1.activity_date = DATE_ADD(DATE(u.created_at), INTERVAL 1 DAY)
            LEFT JOIN user_activity_days d7 ON d7.user_id = u.id AND d7.activity_date = DATE_ADD(DATE(u.created_at), INTERVAL 7 DAY)
            LEFT JOIN user_activity_days d30 ON d30.user_id = u.id AND d30.activity_date = DATE_ADD(DATE(u.created_at), INTERVAL 30 DAY)
            WHERE u.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(u.created_at)
            ORDER BY cohort_date DESC
            LIMIT 10
        ") : [],
    ];
}
