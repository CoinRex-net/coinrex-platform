<?php
/**
 * RexLink API bootstrap.
 * Pairing/session endpoints for the extension-free CoinRex signer companion.
 */

$rex_signer_endpoint = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
if (in_array($rex_signer_endpoint, ['create_pairing.php', 'pairing_qr.php', 'login_from_session.php'], true)) {
    define('COINREX_SKIP_REWARD_SCHEMA_INIT', true);
    define('COINREX_SKIP_REX_SIGNER_SCHEMA_INIT', true);
}

require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/functions/realtime.php';

function rexSignerJsonInput() {
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function rexSignerInput($key, $default = null) {
    static $json = null;
    if ($json === null) {
        $json = rexSignerJsonInput();
    }

    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }

    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }

    return array_key_exists($key, $json) ? $json[$key] : $default;
}

function rexSignerHashSecret($secret) {
    return hash('sha256', trim((string) $secret));
}

function rexSignerRandomToken($bytes = 32) {
    return rtrim(strtr(base64_encode(random_bytes((int) $bytes)), '+/', '-_'), '=');
}

function rexSignerGeneratePairCode() {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function rexSignerFormatPairCode($code) {
    $digits = rexSignerNormalizePairCode($code);
    if (!preg_match('/^\d{6}$/', $digits)) {
        throw new InvalidArgumentException('Pairing code must be 6 digits.');
    }

    return 'REX-' . substr($digits, 0, 3) . '-' . substr($digits, 3, 3);
}

function rexSignerNormalizePairCode($code) {
    $digits = preg_replace('/\D+/', '', (string) $code);
    return (string) $digits;
}

function rexSignerClampDuration($minutes) {
    $minutes = (int) $minutes;
    if ($minutes <= 0) {
        return 10;
    }

    return max(5, min($minutes, 60));
}

function rexSignerMissingLabel() {
    return 'Not provided by dApp';
}

function rexSignerHostFromUrl($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $url = strpos($value, '://') === false ? 'https://' . $value : $value;
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || trim($host) === '') {
        return '';
    }

    $host = strtolower(trim($host, '.'));
    return strpos($host, 'www.') === 0 ? substr($host, 4) : $host;
}

function rexSignerTitleFromHost($host) {
    $first = strtolower(trim((string) $host));
    $first = explode('.', $first)[0] ?? '';
    $first = preg_replace('/[^a-z0-9\-_]+/', ' ', $first);
    $parts = preg_split('/[\s\-_]+/', (string) $first) ?: [];
    $parts = array_values(array_filter($parts, static function ($part) {
        return trim((string) $part) !== '';
    }));
    if (empty($parts)) {
        return '';
    }

    return implode(' ', array_map(static function ($part) {
        return ucfirst((string) $part);
    }, $parts));
}

function rexSignerRequestOriginUrl() {
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        return $origin;
    }

    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer === '') {
        return '';
    }

    $parts = parse_url($referer);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    $url = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']);
    if (!empty($parts['port'])) {
        $url .= ':' . (int) $parts['port'];
    }

    return $url;
}

function rexSignerIsLocalHost($host) {
    $host = strtolower(trim((string) $host));
    if ($host === '') {
        return false;
    }

    return $host === 'localhost'
        || $host === '127.0.0.1'
        || $host === '::1'
        || preg_match('/^10\./', $host) === 1
        || preg_match('/^192\.168\./', $host) === 1
        || preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host) === 1;
}

function rexSignerNetworkContext(PDO $db, $network_slug = '', $chain_id = null) {
    $network_slug = trim((string) $network_slug);
    $chain_id_value = $chain_id !== null && $chain_id !== '' ? (int) $chain_id : 0;
    $row = null;

    if ($network_slug !== '') {
        $stmt = $db->prepare("SELECT slug, name, chain_id, native_symbol FROM rex_signer_networks WHERE slug = ? AND is_enabled = 1 LIMIT 1");
        $stmt->execute([$network_slug]);
        $row = $stmt->fetch() ?: null;
    }

    if (!$row && $chain_id_value > 0) {
        $stmt = $db->prepare("SELECT slug, name, chain_id, native_symbol FROM rex_signer_networks WHERE chain_id = ? AND is_enabled = 1 LIMIT 1");
        $stmt->execute([$chain_id_value]);
        $row = $stmt->fetch() ?: null;
    }

    if (!$row) {
        return [
            'slug' => $network_slug !== '' ? $network_slug : rexSignerMissingLabel(),
            'name' => $network_slug !== '' ? $network_slug : rexSignerMissingLabel(),
            'chain_id' => $chain_id_value > 0 ? $chain_id_value : null,
            'native_symbol' => '',
            'is_known' => false,
            'mismatch' => false,
        ];
    }

    $resolved_chain_id = isset($row['chain_id']) ? (int) $row['chain_id'] : 0;
    return [
        'slug' => (string) ($row['slug'] ?? $network_slug),
        'name' => (string) ($row['name'] ?? $network_slug),
        'chain_id' => $resolved_chain_id > 0 ? $resolved_chain_id : null,
        'native_symbol' => (string) ($row['native_symbol'] ?? ''),
        'is_known' => true,
        'mismatch' => $chain_id_value > 0 && $resolved_chain_id > 0 && $chain_id_value !== $resolved_chain_id,
    ];
}

function rexSignerBuildDisplayContext(PDO $db, array $raw = []) {
    $payload = isset($raw['payload']) && is_array($raw['payload']) ? $raw['payload'] : [];
    $source = array_merge($payload, $raw);
    unset($source['payload']);

    $dapp_url = trim((string) ($source['dapp_url'] ?? $source['origin'] ?? $source['base_url'] ?? ''));
    if ($dapp_url === '') {
        $dapp_url = rexSignerRequestOriginUrl();
    }
    if ($dapp_url === '' && defined('BASE_URL')) {
        $dapp_url = (string) BASE_URL;
    }

    $domain = rexSignerHostFromUrl($dapp_url);
    $dapp_name = trim((string) ($source['dapp_name'] ?? $source['app_name'] ?? $source['website_name'] ?? ''));
    if ($dapp_name === '') {
        $dapp_name = rexSignerTitleFromHost($domain);
    }
    if ($dapp_name === '' && defined('SITE_NAME')) {
        $dapp_name = (string) SITE_NAME;
    }
    if ($dapp_name === '') {
        $dapp_name = 'Unknown dApp';
    }

    $network = rexSignerNetworkContext(
        $db,
        $source['network_slug'] ?? '',
        $source['chain_id'] ?? null
    );

    $warnings = [];
    if ($domain === '') {
        $warnings[] = 'Website/domain was not provided by dApp.';
    } elseif (rexSignerIsLocalHost($domain)) {
        $warnings[] = 'This website address may not be reachable from your phone unless both devices are on the same network.';
    }
    if (!$network['is_known']) {
        $warnings[] = 'Network is not in RexLink network list.';
    }
    if ($network['mismatch']) {
        $warnings[] = 'Network slug and chain ID do not match.';
    }

    $amount = trim((string) ($source['amount'] ?? $source['claim_amount'] ?? $source['value'] ?? ''));
    $fee = trim((string) ($source['fee_estimate'] ?? $source['gas_fee'] ?? $source['execution_fee'] ?? ''));
    $wallet = trim((string) ($source['wallet_address'] ?? $source['requested_wallet_address'] ?? $source['from_address'] ?? $source['claimant'] ?? ''));
    $contract = trim((string) ($source['contract_address'] ?? $source['contractAddress'] ?? ''));
    $recipient = trim((string) ($source['recipient_address'] ?? $source['recipientAddress'] ?? $source['to_address'] ?? ''));
    $spender = trim((string) ($source['spender_address'] ?? $source['spenderAddress'] ?? ''));
    $expires_at = trim((string) ($source['expires_at'] ?? ''));

    $display = [
        'dapp_name' => $dapp_name,
        'website' => $domain !== '' ? $domain : rexSignerMissingLabel(),
        'dapp_url' => $dapp_url !== '' ? $dapp_url : rexSignerMissingLabel(),
        'network' => (string) $network['name'],
        'network_slug' => (string) $network['slug'],
        'chain_id' => $network['chain_id'],
        'native_symbol' => (string) $network['native_symbol'],
        'wallet' => $wallet !== '' ? strtolower($wallet) : rexSignerMissingLabel(),
        'contract' => $contract !== '' ? strtolower($contract) : rexSignerMissingLabel(),
        'recipient' => $recipient !== '' ? strtolower($recipient) : rexSignerMissingLabel(),
        'spender' => $spender !== '' ? strtolower($spender) : rexSignerMissingLabel(),
        'amount' => $amount !== '' ? $amount : rexSignerMissingLabel(),
        'fee' => $fee !== '' ? $fee : 'Fee estimate unavailable',
        'expires_at' => $expires_at !== '' ? $expires_at : 'Expiry unavailable',
        'warnings' => $warnings,
    ];

    return [
        'display_context' => $display,
        'trust_context' => [
            'source' => 'raw_request',
            'verified' => false,
            'website' => $display['website'],
            'dapp_name' => $display['dapp_name'],
            'network_known' => (bool) $network['is_known'],
            'network_mismatch' => (bool) $network['mismatch'],
            'warnings' => $warnings,
        ],
    ];
}

function rexSignerActiveSessionWhere() {
    return "status = 'active' AND expires_at > NOW()";
}

function rexSignerCancelPendingApprovalsForEndedSessions(PDO $db, $session_id = null, $reason = 'RexLink session ended before approval.') {
    $params = [trim((string) $reason) ?: 'RexLink session ended before approval.'];
    $session_filter = '';
    if ($session_id !== null) {
        $session_filter = 'AND ar.session_id = ?';
        $params[] = (int) $session_id;
    }

    $stmt = $db->prepare("
        UPDATE rex_signer_approval_requests ar
        INNER JOIN rex_signer_sessions s ON s.id = ar.session_id
        SET ar.status = 'cancelled',
            ar.decision_note = ?,
            ar.decided_at = COALESCE(ar.decided_at, NOW()),
            ar.completed_at = COALESCE(ar.completed_at, NOW())
        WHERE ar.status = 'pending'
          AND ar.session_id IS NOT NULL
          {$session_filter}
          AND (s.status <> 'active' OR s.expires_at <= NOW())
    ");
    $stmt->execute($params);

    return (int) $stmt->rowCount();
}

function rexSignerReleaseApprovedClaimApprovalsForEndedSessions(PDO $db, $session_id = null, $reason = 'RexLink session ended before claim submission.') {
    $decision_note = trim((string) $reason) ?: 'RexLink session ended before claim submission.';
    $session_filter = '';
    if ($session_id !== null) {
        $session_filter = 'AND ar.session_id = ?';
    }

    $stmt = $db->prepare("
        SELECT ar.id, ar.user_id, ar.session_id, ar.result_json
        FROM rex_signer_approval_requests ar
        INNER JOIN rex_signer_sessions s ON s.id = ar.session_id
        WHERE ar.request_type = 'claim'
          AND ar.status = 'approved'
          AND (ar.tx_hash IS NULL OR ar.tx_hash = '')
          {$session_filter}
          AND (s.status <> 'active' OR s.expires_at <= NOW())
        ORDER BY ar.id ASC
        LIMIT 50
    ");
    $select_params = [];
    if ($session_id !== null) {
        $select_params[] = (int) $session_id;
    }
    $stmt->execute($select_params);
    $requests = $stmt->fetchAll();

    $released = 0;
    foreach ($requests as $request) {
        $user_id = (int) ($request['user_id'] ?? 0);
        $result = !empty($request['result_json']) ? json_decode((string) $request['result_json'], true) : [];
        $snapshot_id = (int) ($result['snapshot_id'] ?? 0);
        $claim_update = null;

        if ($user_id > 0 && $snapshot_id > 0) {
            try {
                $claim_update = expireClaimSnapshotForUser($snapshot_id, $user_id, $db);
            } catch (Throwable $e) {
                continue;
            }
        }

        $result = is_array($result) ? $result : [];
        $result['tx_status'] = 'failed';
        $result['tx_error'] = $decision_note;
        $result['tx_reported_at'] = date('c');
        $result['claim_snapshot_status'] = 'expired';
        $result['ledger_status'] = 'available';
        if (is_array($claim_update)) {
            $result['claim_update'] = $claim_update;
        }

        $update = $db->prepare("
            UPDATE rex_signer_approval_requests
            SET status = 'cancelled',
                result_json = ?,
                decision_note = ?,
                decided_at = COALESCE(decided_at, NOW()),
                completed_at = COALESCE(completed_at, NOW())
            WHERE id = ?
              AND request_type = 'claim'
              AND status = 'approved'
              AND (tx_hash IS NULL OR tx_hash = '')
        ");
        $update->execute([
            json_encode($result, JSON_UNESCAPED_SLASHES),
            $decision_note,
            (int) $request['id'],
        ]);
        if ($update->rowCount() > 0) {
            $released++;
        }
    }

    return $released;
}

function rexSignerExpireOldRows(PDO $db) {
    $expired_sessions = [];
    if (tableExists('rex_signer_sessions')) {
        $expired_stmt = $db->query("
            SELECT id, user_id
            FROM rex_signer_sessions
            WHERE status = 'active'
              AND expires_at <= NOW()
            LIMIT 50
        ");
        $expired_sessions = $expired_stmt ? $expired_stmt->fetchAll() : [];
    }

    $db->exec("UPDATE rex_signer_pairing_codes SET status = 'expired' WHERE status = 'pending' AND expires_at <= NOW()");
    $db->exec("UPDATE rex_signer_sessions SET status = 'expired' WHERE status = 'active' AND expires_at <= NOW()");
    rexSignerCancelPendingApprovalsForEndedSessions($db);
    rexSignerReleaseApprovedClaimApprovalsForEndedSessions($db);
    $db->exec("UPDATE rex_signer_approval_requests SET status = 'expired' WHERE status = 'pending' AND expires_at <= NOW()");

    foreach ($expired_sessions as $session) {
        coinrexRealtimePublish('session.expired', [
            'user_id' => (int) $session['user_id'],
            'session_id' => (int) $session['id'],
            'status' => 'expired',
        ]);
    }
}

function rexSignerEnsureSchema(PDO $db = null) {
    static $ready = false;
    if ($ready) {
        return;
    }

    $db = $db ?: getDBConnection();

    $db->exec("
        CREATE TABLE IF NOT EXISTS rex_signer_networks (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(50) NOT NULL,
            name VARCHAR(100) NOT NULL,
            chain_id INT UNSIGNED NULL,
            native_symbol VARCHAR(20) NOT NULL,
            rpc_url VARCHAR(500) NULL,
            explorer_url VARCHAR(500) NULL,
            environment ENUM('staging','testnet','mainnet','stub') NOT NULL DEFAULT 'testnet',
            chain_family VARCHAR(20) NOT NULL DEFAULT 'evm',
            claim_enabled TINYINT(1) NOT NULL DEFAULT 0,
            token_support_enabled TINYINT(1) NOT NULL DEFAULT 0,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT UNSIGNED NOT NULL DEFAULT 100,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rex_signer_networks_slug (slug),
            KEY idx_rex_signer_networks_enabled (is_enabled, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    try {
        $db->exec("ALTER TABLE rex_signer_networks MODIFY environment ENUM('staging','testnet','mainnet','stub') NOT NULL DEFAULT 'testnet'");
    } catch (Throwable $e) {
        // Older MySQL variants may reject no-op enum changes; continue with existing compatible schemas.
    }

    if (!tableHasColumn('rex_signer_networks', 'chain_family')) {
        $db->exec("ALTER TABLE rex_signer_networks ADD COLUMN chain_family VARCHAR(20) NOT NULL DEFAULT 'evm' AFTER environment");
    }

    if (!tableHasColumn('rex_signer_networks', 'claim_enabled')) {
        $db->exec("ALTER TABLE rex_signer_networks ADD COLUMN claim_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER chain_family");
    }

    if (!tableHasColumn('rex_signer_networks', 'token_support_enabled')) {
        $db->exec("ALTER TABLE rex_signer_networks ADD COLUMN token_support_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER claim_enabled");
    }

    $db->exec("
        INSERT INTO rex_signer_networks
            (slug, name, chain_id, native_symbol, rpc_url, explorer_url, environment, chain_family, claim_enabled, token_support_enabled, is_enabled, sort_order)
        VALUES
            ('polygon', 'Polygon', 137, 'POL', 'https://polygon-rpc.com', 'https://polygonscan.com', 'mainnet', 'evm', 0, 1, 1, 10),
            ('base', 'Base', 8453, 'ETH', 'https://mainnet.base.org', 'https://basescan.org', 'mainnet', 'evm', 0, 1, 1, 20),
            ('plasma', 'Plasma', NULL, 'XPL', NULL, NULL, 'mainnet', 'evm', 0, 0, 1, 30),
            ('polygon-amoy', 'Polygon Amoy', 80002, 'POL', 'https://rpc-amoy.polygon.technology', 'https://amoy.polygonscan.com', 'staging', 'evm', 1, 1, 1, 90)
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            chain_id = VALUES(chain_id),
            native_symbol = VALUES(native_symbol),
            rpc_url = VALUES(rpc_url),
            explorer_url = VALUES(explorer_url),
            environment = VALUES(environment),
            chain_family = VALUES(chain_family),
            claim_enabled = VALUES(claim_enabled),
            token_support_enabled = VALUES(token_support_enabled),
            is_enabled = VALUES(is_enabled),
            sort_order = VALUES(sort_order)
    ");

    $db->exec("UPDATE rex_signer_networks SET is_enabled = 0, environment = 'stub' WHERE slug = 'plasma-testnet'");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rex_signer_pairing_codes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            code_hash CHAR(64) NOT NULL,
            display_code VARCHAR(32) NOT NULL,
            pairing_purpose VARCHAR(30) NOT NULL DEFAULT 'claim',
            referral_code VARCHAR(32) NULL,
            status ENUM('pending','completed','expired','revoked') NOT NULL DEFAULT 'pending',
            requested_duration_minutes INT UNSIGNED NOT NULL DEFAULT 10,
            expires_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            completed_session_id INT UNSIGNED NULL,
            device_fingerprint VARCHAR(255) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rex_signer_pairing_code_hash (code_hash),
            KEY idx_rex_signer_pairing_user_status (user_id, status),
            KEY idx_rex_signer_pairing_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!tableHasColumn('rex_signer_pairing_codes', 'pairing_purpose')) {
        $db->exec("ALTER TABLE rex_signer_pairing_codes ADD COLUMN pairing_purpose VARCHAR(30) NOT NULL DEFAULT 'claim' AFTER display_code");
    }

    if (!tableHasColumn('rex_signer_pairing_codes', 'device_fingerprint')) {
        $db->exec("ALTER TABLE rex_signer_pairing_codes ADD COLUMN device_fingerprint VARCHAR(255) NULL AFTER completed_session_id");
    }

    if (!tableHasColumn('rex_signer_pairing_codes', 'referral_code')) {
        $db->exec("ALTER TABLE rex_signer_pairing_codes ADD COLUMN referral_code VARCHAR(32) NULL AFTER pairing_purpose");
    }

    $pairing_user_nullable_stmt = $db->prepare("
        SELECT IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'rex_signer_pairing_codes'
          AND COLUMN_NAME = 'user_id'
        LIMIT 1
    ");
    $pairing_user_nullable_stmt->execute([DB_NAME]);
    if (strtoupper((string) ($pairing_user_nullable_stmt->fetch()['IS_NULLABLE'] ?? 'NO')) !== 'YES') {
        try {
            $db->exec("ALTER TABLE rex_signer_pairing_codes MODIFY user_id INT UNSIGNED NULL");
        } catch (Throwable $e) {
            $db->exec("ALTER TABLE rex_signer_pairing_codes DROP FOREIGN KEY fk_rex_signer_pairing_user");
            $db->exec("ALTER TABLE rex_signer_pairing_codes MODIFY user_id INT UNSIGNED NULL");
            $db->exec("
                ALTER TABLE rex_signer_pairing_codes
                ADD CONSTRAINT fk_rex_signer_pairing_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ");
        }
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS rex_signer_sessions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            pairing_code_id INT UNSIGNED NULL,
            session_token_hash CHAR(64) NOT NULL,
            device_name VARCHAR(120) NULL,
            wallet_address VARCHAR(100) NULL,
            status ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
            expires_at DATETIME NOT NULL,
            last_seen_at DATETIME NULL,
            revoked_at DATETIME NULL,
            revoke_reason VARCHAR(255) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rex_signer_session_token_hash (session_token_hash),
            KEY idx_rex_signer_sessions_user_status (user_id, status, expires_at),
            KEY idx_rex_signer_sessions_pairing (pairing_code_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!tableHasColumn('rex_signer_sessions', 'wallet_address')) {
        $db->exec("ALTER TABLE rex_signer_sessions ADD COLUMN wallet_address VARCHAR(100) NULL AFTER device_name");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS rex_signer_approval_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            session_id INT UNSIGNED NULL,
            network_slug VARCHAR(50) NOT NULL,
            request_type ENUM('claim','send','message') NOT NULL DEFAULT 'claim',
            title VARCHAR(160) NOT NULL,
            summary VARCHAR(255) NULL,
            amount VARCHAR(80) NULL,
            fee_estimate VARCHAR(80) NULL,
            payload_json JSON NULL,
            status ENUM('pending','approved','rejected','expired','cancelled') NOT NULL DEFAULT 'pending',
            decision_note VARCHAR(255) NULL,
            decided_at DATETIME NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_rex_signer_approvals_user_status (user_id, status, expires_at),
            KEY idx_rex_signer_approvals_session (session_id),
            KEY idx_rex_signer_approvals_network (network_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!tableHasColumn('rex_signer_approval_requests', 'wallet_address')) {
        $db->exec("ALTER TABLE rex_signer_approval_requests ADD COLUMN wallet_address VARCHAR(100) NULL AFTER payload_json");
    }

    if (!tableHasColumn('rex_signer_approval_requests', 'tx_hash')) {
        $db->exec("ALTER TABLE rex_signer_approval_requests ADD COLUMN tx_hash VARCHAR(100) NULL AFTER wallet_address");
    }

    if (!tableHasColumn('rex_signer_approval_requests', 'result_json')) {
        $db->exec("ALTER TABLE rex_signer_approval_requests ADD COLUMN result_json JSON NULL AFTER tx_hash");
    }

    if (!tableHasColumn('rex_signer_approval_requests', 'completed_at')) {
        $db->exec("ALTER TABLE rex_signer_approval_requests ADD COLUMN completed_at DATETIME NULL AFTER decided_at");
    }

    $ready = true;
}

function rexSignerGetBearerToken() {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        $request_headers = apache_request_headers();
        if (is_array($request_headers)) {
            $header = $request_headers['Authorization'] ?? $request_headers['authorization'] ?? '';
        }
    }

    if (preg_match('/Bearer\s+(.+)/i', (string) $header, $matches)) {
        return trim($matches[1]);
    }

    $header_token = $_SERVER['HTTP_X_REX_SIGNER_SESSION'] ?? '';
    if ($header_token === '' && function_exists('apache_request_headers')) {
        $request_headers = isset($request_headers) && is_array($request_headers) ? $request_headers : apache_request_headers();
        if (is_array($request_headers)) {
            $header_token = $request_headers['X-REX-SIGNER-SESSION'] ?? $request_headers['x-rex-signer-session'] ?? '';
        }
    }

    if (trim((string) $header_token) !== '') {
        return trim((string) $header_token);
    }

    return trim((string) rexSignerInput('session_token', ''));
}

function rexSignerGetSessionByToken(PDO $db, $token) {
    $token = trim((string) $token);
    if ($token === '') {
        return null;
    }

    $stmt = $db->prepare("
        SELECT *,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds,
               UNIX_TIMESTAMP(expires_at) AS expires_at_unix
        FROM rex_signer_sessions
        WHERE session_token_hash = ?
          AND status = 'active'
          AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([rexSignerHashSecret($token)]);
    $session = $stmt->fetch();

    if (!$session) {
        return null;
    }

    $touch = $db->prepare("UPDATE rex_signer_sessions SET last_seen_at = NOW() WHERE id = ?");
    $touch->execute([(int) $session['id']]);

    return $session;
}

function rexSignerGetAnySessionByToken(PDO $db, $token) {
    $token = trim((string) $token);
    if ($token === '') {
        return null;
    }

    $stmt = $db->prepare("
        SELECT *,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds,
               UNIX_TIMESTAMP(expires_at) AS expires_at_unix
        FROM rex_signer_sessions
        WHERE session_token_hash = ?
        LIMIT 1
    ");
    $stmt->execute([rexSignerHashSecret($token)]);
    $session = $stmt->fetch();

    return $session ?: null;
}

function rexSignerGetActor(PDO $db = null) {
    $db = $db ?: getDBConnection();
    rexSignerEnsureSchema($db);
    rexSignerExpireOldRows($db);

    $token = rexSignerGetBearerToken();
    $session = rexSignerGetSessionByToken($db, $token);
    if ($session) {
        return [
            'type' => 'signer_session',
            'user_id' => (int) $session['user_id'],
            'session_id' => (int) $session['id'],
            'session' => $session,
        ];
    }

    $actor = apiGetAuthenticatedUser();
    if ($actor['type'] === 'user') {
        return [
            'type' => 'web_user',
            'user_id' => (int) $actor['user_id'],
            'session_id' => null,
            'user' => $actor['user'] ?? null,
        ];
    }

    return [
        'type' => 'admin',
        'user_id' => null,
        'session_id' => null,
        'admin_id' => (int) ($actor['admin_id'] ?? 0),
    ];
}

function rexSignerRequireUserActor(PDO $db = null) {
    $actor = rexSignerGetActor($db);
    if (!empty($actor['user_id'])) {
        return $actor;
    }

    apiErrorResponse(422, 'A user-scoped signer session is required.');
}

function rexSignerSessionPayload(array $row) {
    $expires_at_unix = isset($row['expires_at_unix']) && $row['expires_at_unix'] !== null
        ? (int) $row['expires_at_unix']
        : (!empty($row['expires_at']) ? strtotime((string) $row['expires_at']) : null);

    return [
        'id' => (int) $row['id'],
        'user_id' => (int) $row['user_id'],
        'device_name' => $row['device_name'],
        'wallet_address' => $row['wallet_address'] ?? null,
        'status' => $row['status'],
        'expires_at' => $row['expires_at'],
        'expires_at_unix' => $expires_at_unix,
        'remaining_seconds' => isset($row['remaining_seconds'])
            ? max(0, (int) $row['remaining_seconds'])
            : null,
        'last_seen_at' => $row['last_seen_at'],
        'created_at' => $row['created_at'],
    ];
}

function rexSignerReadJsonFile($relative_path) {
    $path = dirname(__DIR__, 2) . '/' . ltrim((string) $relative_path, '/\\');
    if (!is_readable($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function rexSignerClaimDistributorDeployment() {
    $deployment = rexSignerReadJsonFile('deployments/polygon-amoy-rex-claim-distributor.json');
    if (empty($deployment['contractAddress'])) {
        throw new RuntimeException('Claim distributor deployment metadata is missing.');
    }

    return $deployment;
}

function rexSignerDecimalToWei($amount, $decimals = 18) {
    $amount = trim((string) $amount);
    if ($amount === '' || !preg_match('/^\d+(\.\d+)?$/', $amount)) {
        throw new InvalidArgumentException('Invalid claim amount.');
    }

    $decimals = max(0, (int) $decimals);
    [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
    $fraction = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);
    $combined = ltrim($whole . $fraction, '0');

    return $combined === '' ? '0' : $combined;
}

function rexSignerSignClaimPayload(array $payload) {
    $script = dirname(__DIR__, 2) . '/scripts/sign-rex-claim.js';
    if (!is_file($script)) {
        throw new RuntimeException('Claim signing helper is missing.');
    }

    $node_binary = trim((string) (getenv('NODE_BINARY') ?: 'node'));
    $command = escapeshellarg($node_binary) . ' ' . escapeshellarg($script);
    $descriptor_spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor_spec, $pipes, dirname(__DIR__, 2));
    if (!is_resource($process)) {
        throw new RuntimeException('Claim signing helper could not start.');
    }

    fwrite($pipes[0], json_encode($payload, JSON_UNESCAPED_SLASHES));
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);

    if ($exit_code !== 0) {
        throw new RuntimeException(trim((string) $stderr) ?: 'Claim signing failed.');
    }

    $decoded = json_decode((string) $stdout, true);
    if (!is_array($decoded) || empty($decoded['signature'])) {
        throw new RuntimeException('Claim signing returned an invalid response.');
    }

    return $decoded;
}

function rexSignerBuildSignedClaim(PDO $db, $user_id, $wallet_address, $claim_amount = null) {
    $wallet_address = trim((string) $wallet_address);
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
        throw new InvalidArgumentException('Valid wallet address is required for claim approval.');
    }
    $wallet_address = strtolower($wallet_address);

    $wallet_owner = $db->prepare("SELECT id FROM users WHERE wallet_address = ? AND id <> ? LIMIT 1");
    $wallet_owner->execute([$wallet_address, (int) $user_id]);
    if ($wallet_owner->fetch()) {
        throw new RuntimeException('This wallet is already linked to another CoinRex account.');
    }

    $snapshot = null;

    try {
        $snapshot = generateClaimSnapshotForUser((int) $user_id, $db, $claim_amount);
        $deployment = rexSignerClaimDistributorDeployment();
        $token_deployment = rexSignerReadJsonFile('deployments/polygon-amoy-rex-token.json');
        $decimals = (int) ($token_deployment['decimals'] ?? 18);
        $amount_wei = rexSignerDecimalToWei($snapshot['amount'], $decimals);
        $deadline = time() + 900;

        $sign_payload = [
            'chainId' => (int) ($deployment['chainId'] ?? 80002),
            'contractAddress' => (string) $deployment['contractAddress'],
            'claimant' => $wallet_address,
            'snapshotId' => (string) $snapshot['snapshot_id'],
            'amount' => $amount_wei,
            'deadline' => (string) $deadline,
        ];
        $signed = rexSignerSignClaimPayload($sign_payload);

        $update_wallet = $db->prepare("UPDATE users SET wallet_address = ?, updated_at = NOW() WHERE id = ?");
        $update_wallet->execute([$wallet_address, (int) $user_id]);

        return [
            'snapshot_id' => (int) $snapshot['snapshot_id'],
            'amount' => $snapshot['amount'],
            'amount_wei' => $amount_wei,
            'nonce' => $snapshot['nonce'],
            'wallet_address' => $wallet_address,
            'network_slug' => 'polygon-amoy',
            'chain_id' => (int) ($deployment['chainId'] ?? 80002),
            'contract_address' => (string) $deployment['contractAddress'],
            'rex_token_address' => (string) ($deployment['rexTokenAddress'] ?? ''),
            'claim_fee_wei' => (string) ($deployment['claimFee'] ?? '0'),
            'claim_fee_pol' => (string) ($deployment['claimFeeFormatted'] ?? ''),
            'deadline' => $deadline,
            'signature' => (string) $signed['signature'],
            'claim_signer' => (string) ($signed['signerAddress'] ?? ''),
        ];
    } catch (Throwable $e) {
        if (is_array($snapshot) && !empty($snapshot['snapshot_id'])) {
            try {
                expireClaimSnapshotForUser((int) $snapshot['snapshot_id'], (int) $user_id, $db);
            } catch (Throwable $cleanupError) {
                // If cleanup fails, preserve the original claim error so callers
                // can retry; the stale snapshot can be handled by the sync jobs.
            }
        }

        throw $e;
    }
}

if (!defined('COINREX_SKIP_REX_SIGNER_SCHEMA_INIT') || !COINREX_SKIP_REX_SIGNER_SCHEMA_INIT) {
    rexSignerEnsureSchema();
}
