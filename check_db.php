<?php
define('COINREX_SKIP_SESSION_INIT', true);
require_once __DIR__ . '/includes/config.php';

$expected_schema = [
    'users' => [
        'id', 'full_name', 'email', 'password', 'username', 'referral_code', 'referred_by',
        'role', 'level', 'status', 'rex_balance', 'total_rex_earned', 'referral_earnings',
        'signup_ip', 'user_agent', 'email_verified', 'email_verified_at', 'otp_code',
        'otp_expiry', 'otp_attempts', 'login_attempts', 'last_login', 'last_ip',
        'last_active', 'remember_token_hash', 'remember_token_expires_at',
        'avatar', 'country', 'wallet_address', 'wallet_type', 'is_developer_verified',
        'is_expert', 'is_premium', 'is_affiliate', 'has_verified_badge', 'expert_at',
        'total_referrals', 'valid_referrals', 'referral_qualified_at', 'total_reviews',
        'approved_reviews_count',
        'created_at', 'updated_at'
    ],
    'admins' => [
        'id', 'email', 'username', 'name', 'password_hash', 'status',
        'last_login_at', 'created_at', 'updated_at'
    ],
    'projects' => [
        'id', 'name', 'slug', 'logo', 'category', 'description', 'website_url',
        'telegram_url', 'twitter_url', 'contract_address', 'github_url', 'discord_url',
        'network', 'project_live_since', 'status', 'min_holding_amount', 'max_reward_rex',
        'required_holding_days', 'created_by', 'approval_status', 'is_verified',
        'verified_at', 'is_featured', 'feature_status', 'feature_requested_at',
        'feature_reviewed_at', 'feature_reviewed_by', 'featured_at',
        'project_score', 'total_reviews', 'avg_rating',
        'created_at', 'updated_at'
    ],
    'developer_verification' => [
        'id', 'user_id', 'full_name', 'email', 'username', 'password_hash', 'status',
        'verification_post_url', 'verification_url', 'verification_code',
        'has_verified_badge', 'created_at', 'updated_at'
    ],
    'reviews' => [
        'id', 'user_id', 'project_id', 'review_title', 'review_content', 'rating',
        'pros', 'cons', 'holding_amount', 'holding_days', 'wallet_type', 'tx_hash',
        'wallet_address', 'screenshot_url', 'tokenomics_score', 'team_score',
        'utility_score', 'community_score', 'risk_score', 'calculated_rex',
        'final_rex', 'review_score', 'status', 'proof_status', 'rejection_reason',
        'approval_note', 'helpful_count', 'reviewed_by', 'reviewed_at',
        'proof_verified_by', 'proof_verified_at', 'proof_rejection_reason',
        'auto_approved_at', 'auto_approved_by_level', 'created_at', 'updated_at'
    ],
    'review_reactions' => [
        'id', 'review_id', 'user_id', 'reaction_type', 'created_at'
    ],
    'reward_ledger' => [
        'id', 'user_id', 'source', 'reward_phase', 'action_type', 'amount', 'status',
        'reference_id', 'user_level_at_time', 'created_at'
    ],
    'claim_snapshots' => [
        'id', 'user_id', 'total_amount', 'nonce', 'status', 'created_at'
    ],
    'mini_tasks' => [
        'id', 'title', 'description', 'reward', 'daily_limit', 'cooldown_seconds',
        'is_active'
    ],
    'user_task_logs' => [
        'id', 'user_id', 'task_id', 'completed_at', 'status'
    ],
    'content_flags' => [
        'id', 'user_id', 'target_type', 'target_id', 'reason', 'status',
        'created_at', 'updated_at'
    ],
    'admin_activity_logs' => [
        'id', 'admin_id', 'action', 'target_type', 'target_id', 'details',
        'ip_address', 'created_at'
    ],
    'messages' => [
        'id', 'title', 'body', 'status', 'recipient_admin_id', 'created_at', 'read_at'
    ],
];

try {
    $server_pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "MySQL server connection: OK\n";

    $db_exists_stmt = $server_pdo->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.SCHEMATA
        WHERE SCHEMA_NAME = ?
    ");
    $db_exists_stmt->execute([DB_NAME]);
    $db_exists = ((int) ($db_exists_stmt->fetch()['total'] ?? 0)) > 0;

    if (!$db_exists) {
        echo "Database '" . DB_NAME . "' does not exist yet.\n";
        echo "Import recreate_db.sql first, then rerun this checker.\n";
        exit;
    }

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    echo "Database connection (" . DB_NAME . "): OK\n\n";

    $table_stmt = $pdo->query("SHOW TABLES");
    $existing_tables = $table_stmt->fetchAll(PDO::FETCH_COLUMN);
    $existing_lookup = array_fill_keys($existing_tables, true);

    echo "Tables currently present:\n";
    foreach ($existing_tables as $table_name) {
        echo "- " . $table_name . "\n";
    }

    echo "\nSchema validation:\n";
    $missing_tables = [];
    $missing_columns = [];

    foreach ($expected_schema as $table_name => $required_columns) {
        if (!isset($existing_lookup[$table_name])) {
            $missing_tables[] = $table_name;
            echo "[MISSING TABLE] " . $table_name . "\n";
            continue;
        }

        $column_stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ?
              AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ");
        $column_stmt->execute([DB_NAME, $table_name]);
        $actual_columns = $column_stmt->fetchAll(PDO::FETCH_COLUMN);
        $actual_lookup = array_fill_keys($actual_columns, true);

        $table_missing_columns = [];
        foreach ($required_columns as $column_name) {
            if (!isset($actual_lookup[$column_name])) {
                $table_missing_columns[] = $column_name;
            }
        }

        if (!empty($table_missing_columns)) {
            $missing_columns[$table_name] = $table_missing_columns;
            echo "[MISSING COLUMNS] " . $table_name . ": " . implode(', ', $table_missing_columns) . "\n";
        } else {
            echo "[OK] " . $table_name . " columns look complete\n";
        }
    }

    echo "\nIntegrity / engine check:\n";
    foreach ($existing_tables as $table_name) {
        try {
            $check_stmt = $pdo->query("CHECK TABLE `" . str_replace('`', '``', $table_name) . "`");
            $result = $check_stmt->fetch();
            $status = strtoupper((string) ($result['Msg_type'] ?? 'status'));
            $message = (string) ($result['Msg_text'] ?? 'Unknown');

            $create_stmt = $pdo->query("SHOW CREATE TABLE `" . str_replace('`', '``', $table_name) . "`");
            $create_row = $create_stmt->fetch();
            $engine = 'unknown';
            if (isset($create_row['Create Table']) && preg_match('/ENGINE=(\w+)/i', $create_row['Create Table'], $matches)) {
                $engine = $matches[1];
            }

            echo "- " . $table_name . ": " . $status . " - " . $message . " (engine: " . $engine . ")\n";
        } catch (Throwable $e) {
            echo "- " . $table_name . ": ERROR - " . $e->getMessage() . "\n";
        }
    }

    echo "\nSummary:\n";
    echo "- Missing tables: " . count($missing_tables) . "\n";
    echo "- Tables with missing columns: " . count($missing_columns) . "\n";

    if (empty($missing_tables) && empty($missing_columns)) {
        echo "CoinRex schema matches the audited application requirements.\n";
    } else {
        echo "CoinRex schema is incomplete. Re-import recreate_db.sql or patch the missing parts above.\n";
    }
} catch (Throwable $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
