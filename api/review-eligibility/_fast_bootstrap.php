<?php
$coinrex_fast_root = dirname(__DIR__, 2);

function coinrexFastLoadEnv($path) {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }
        if (
            (strlen($value) >= 2)
            && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

coinrexFastLoadEnv($coinrex_fast_root . '/.env');
coinrexFastLoadEnv($coinrex_fast_root . '/.env.local');

if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();
}

function coinrexFastJson($status, array $payload) {
    http_response_code((int) $status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function coinrexFastSuccess(array $payload = [], $status = 200) {
    coinrexFastJson($status, array_merge(['success' => true], $payload));
}

function coinrexFastError($status, $message, array $extra = []) {
    coinrexFastJson($status, array_merge(['success' => false, 'message' => (string) $message], $extra));
}

function coinrexFastInput($key, $default = null) {
    static $json = null;
    if ($json === null) {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
        $json = is_array($decoded) ? $decoded : [];
    }
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }
    return array_key_exists($key, $json) ? $json[$key] : $default;
}

function coinrexFastRequirePost() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        coinrexFastError(405, 'Method not allowed.');
    }
}

function coinrexFastDb() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $host = getenv('COINREX_DB_HOST') ?: '127.0.0.1';
    if (strtolower((string) $host) === 'localhost') {
        $host = '127.0.0.1';
    }
    $pdo = new PDO(
        'mysql:host=' . $host . ';dbname=' . (getenv('COINREX_DB_NAME') ?: 'koinrex') . ';charset=utf8mb4',
        getenv('COINREX_DB_USER') ?: 'root',
        getenv('COINREX_DB_PASS') ?: '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}

function coinrexFastUserId() {
    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    if ($user_id <= 0) {
        coinrexFastError(403, 'User authentication required.');
    }
    return $user_id;
}

function coinrexFastPairCode() {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function coinrexFastFormatPairCode($code) {
    $digits = preg_replace('/\D+/', '', (string) $code);
    if (!preg_match('/^\d{6}$/', $digits)) {
        throw new InvalidArgumentException('Pairing code must be 6 digits.');
    }
    return 'REX-' . substr($digits, 0, 3) . '-' . substr($digits, 3, 3);
}

function coinrexFastHash($value) {
    return hash('sha256', trim((string) $value));
}

function coinrexFastPublicBaseUrl() {
    $public = trim((string) (getenv('COINREX_PUBLIC_BASE_URL') ?: ''));
    if ($public !== '') {
        return rtrim($public, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return 'http://' . $host . '/coinrex';
}

function coinrexFastRexlinkBaseUrl() {
    $base = trim((string) (getenv('REXLINK_API_BASE_URL') ?: ''));
    return $base !== '' ? rtrim($base, '/') : coinrexFastPublicBaseUrl();
}

function coinrexFastHostFromUrl($value) {
    $host = parse_url((string) $value, PHP_URL_HOST);
    return is_string($host) ? strtolower($host) : '';
}

function coinrexFastFindWalletReviewUsage(PDO $db, $wallet_address, $project_id) {
    $wallet_address = strtolower(trim((string) $wallet_address));
    $project_id = (int) $project_id;
    if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address) || $project_id <= 0) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT id, user_id, project_id, status, wallet_address, eligibility_wallet_address
        FROM reviews
        WHERE project_id = ?
          AND (
            LOWER(COALESCE(wallet_address, '')) = ?
            OR LOWER(COALESCE(eligibility_wallet_address, '')) = ?
          )
          AND LOWER(COALESCE(status, '')) NOT IN ('rejected', 'deleted', 'cancelled')
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$project_id, $wallet_address, $wallet_address]);
    $row = $stmt->fetch();
    return $row ?: null;
}
