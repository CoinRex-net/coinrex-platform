<?php
/**
 * CoinRex Configuration File
 * Location: /coinrex/includes/config.php
 */

// Local environment file loader
if (!function_exists('coinrexLoadEnvFile')) {
    function coinrexLoadEnvFile($path) {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $separator_pos = strpos($line, '=');
            if ($separator_pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator_pos));
            $value = trim(substr($line, $separator_pos + 1));

            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            $value_length = strlen($value);
            if ($value_length >= 2) {
                $first_char = $value[0];
                $last_char = $value[$value_length - 1];
                if (($first_char === '"' && $last_char === '"') || ($first_char === "'" && $last_char === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('coinrexIsHttpsRequest')) {
    function coinrexIsHttpsRequest() {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        $requestScheme = strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
        if ($requestScheme === 'https') {
            return true;
        }

        $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        if ($forwardedProto !== '') {
            $forwardedProto = explode(',', $forwardedProto)[0];
            if (trim($forwardedProto) === 'https') {
                return true;
            }
        }

        $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ($forwardedSsl === 'on' || $forwardedSsl === '1') {
            return true;
        }

        $frontEndHttps = strtolower((string) ($_SERVER['HTTP_FRONT_END_HTTPS'] ?? ''));
        if ($frontEndHttps === 'on') {
            return true;
        }

        $cfVisitor = (string) ($_SERVER['HTTP_CF_VISITOR'] ?? '');
        if ($cfVisitor !== '' && stripos($cfVisitor, '"scheme":"https"') !== false) {
            return true;
        }

        return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }
}

$coinrex_base_path = dirname(__DIR__);
coinrexLoadEnvFile($coinrex_base_path . '/.env');
coinrexLoadEnvFile($coinrex_base_path . '/.env.local');

// Environment
$coinrex_env_raw = getenv('COINREX_ENV');
$coinrex_env = strtolower(trim((string) ($coinrex_env_raw !== false ? $coinrex_env_raw : 'production')));
if (!in_array($coinrex_env, ['development', 'dev', 'production', 'prod'], true)) {
    $coinrex_env = 'production';
}
$is_production = in_array($coinrex_env, ['production', 'prod'], true);

define('ENVIRONMENT', $is_production ? 'production' : 'development');

// ============================================================
// TESTING MODE FLAG
// ============================================================
// Set to true to bypass cooldowns, day progression, security
// signals, and daily task limits for testing purposes.
// Set to false to restore all production validations.
//
// Override via .env: COINREX_TESTING_MODE=true
// ============================================================
$testing_mode_raw = getenv('COINREX_TESTING_MODE');
$testing_mode = strtolower(trim((string) ($testing_mode_raw !== false ? $testing_mode_raw : 'false')));
// Temporarily enabled for mobile testing — set back to false in production
define('TESTING_MODE', !$is_production && in_array($testing_mode, ['1', 'true', 'yes', 'on'], true));

$claim_pairing_test_mode_raw = getenv('COINREX_CLAIM_PAIRING_TEST_MODE');
$claim_pairing_test_mode = strtolower(trim((string) ($claim_pairing_test_mode_raw !== false ? $claim_pairing_test_mode_raw : 'false')));
define('CLAIM_PAIRING_TEST_MODE', in_array($claim_pairing_test_mode, ['1', 'true', 'yes', 'on'], true));

// ============================================================
// LOCAL TEST MODE — Auto-enable for localhost development
// ============================================================
// When true, bypasses email verification, rate limits, and
// other security checks to make testing with multiple accounts
// easier on localhost.
// ============================================================
$host_lower = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$is_localhost = (
    $host_lower === 'localhost' ||
    $host_lower === '127.0.0.1' ||
    $host_lower === '::1' ||
    strpos($host_lower, 'localhost:') === 0
);
define('LOCAL_TEST_MODE', $is_localhost || TESTING_MODE);

// Error Reporting
if ($is_production) {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

if (!function_exists('coinrexPrepareSessionSavePath')) {
    function coinrexPrepareSessionSavePath($preferred_path, $fallback_base) {
        $paths = [
            trim((string) $preferred_path),
            rtrim((string) $fallback_base, "\\/") . DIRECTORY_SEPARATOR . 'coinrex_sessions',
        ];

        foreach ($paths as $path) {
            if ($path === '') {
                continue;
            }

            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }

            if (!is_dir($path)) {
                continue;
            }

            $probe = rtrim($path, "\\/") . DIRECTORY_SEPARATOR . 'coinrex_session_probe_' . bin2hex(random_bytes(4));
            if (@file_put_contents($probe, '1', LOCK_EX) === false) {
                continue;
            }

            @unlink($probe);
            return $path;
        }

        return '';
    }
}

if (!function_exists('coinrexSessionFileIsUsableNow')) {
    function coinrexSessionFileIsUsableNow($path) {
        $handle = @fopen((string) $path, 'c+b');
        if ($handle === false) {
            return false;
        }

        $locked = @flock($handle, LOCK_EX | LOCK_NB);
        if ($locked) {
            @flock($handle, LOCK_UN);
        }

        fclose($handle);
        return (bool) $locked;
    }
}

// Session Configuration
if (!defined('COINREX_SKIP_SESSION_INIT') && session_status() === PHP_SESSION_NONE) {
    $is_https = coinrexIsHttpsRequest();
    $cookie_secure = $is_production ? true : (bool) $is_https;
    $session_save_path_raw = getenv('COINREX_SESSION_SAVE_PATH');
    $session_save_path = trim((string) ($session_save_path_raw !== false ? $session_save_path_raw : ''));

    if ($session_save_path === '') {
        $session_save_path = $coinrex_base_path . '/cache/sessions';
    }

    $prepared_session_save_path = coinrexPrepareSessionSavePath($session_save_path, sys_get_temp_dir());

    if ($prepared_session_save_path !== '') {
        @ini_set('session.save_path', $prepared_session_save_path);
        @session_save_path($prepared_session_save_path);
    }

    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    @ini_set('session.cookie_secure', $cookie_secure ? '1' : '0');
    @ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $cookie_secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    $incoming_session_id = (string) ($_COOKIE[session_name()] ?? '');
    if ($incoming_session_id !== '' && preg_match('/^[a-zA-Z0-9,-]{1,128}$/', $incoming_session_id)) {
        $session_file = rtrim((string) session_save_path(), "\\/") . DIRECTORY_SEPARATOR . 'sess_' . $incoming_session_id;
        if (
            !is_file($session_file)
            || (
                is_file($session_file)
                && (
                    !is_readable($session_file)
                    || !is_writable($session_file)
                )
            )
        ) {
            session_id(bin2hex(random_bytes(16)));
        }
    }

    $session_started = @session_start();
    if (!$session_started && session_status() === PHP_SESSION_NONE) {
        error_log('CoinRex session_start failed for path: ' . (string) session_save_path());
        $fallback_session_save_path = coinrexPrepareSessionSavePath('', sys_get_temp_dir());
        if ($fallback_session_save_path !== '') {
            @ini_set('session.save_path', $fallback_session_save_path);
            @session_save_path($fallback_session_save_path);
        }
        session_id(bin2hex(random_bytes(16)));
        @session_start();
    }
}

// Database Configuration
// NOTE:
// - In production, credentials should always be provided through environment variables.
// - For local XAMPP development, fallback to MySQL's common default user `root`.
define('DB_HOST', getenv('COINREX_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('COINREX_DB_NAME') ?: 'koinrex');
define('DB_USER', getenv('COINREX_DB_USER') ?: 'root');
define('DB_PASS', getenv('COINREX_DB_PASS') ?: '');

// Path Configuration
define('BASE_PATH', $coinrex_base_path);
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('ASSETS_PATH', BASE_PATH . '/assets');

// URL Configuration
$protocol = coinrexIsHttpsRequest() ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$configured_base_url = trim((string) (getenv('COINREX_BASE_URL') ?: ''));
$configured_base_uri = trim((string) (getenv('COINREX_BASE_URI') ?: ''));
$document_root = realpath($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
$site_root = realpath(BASE_PATH);
$document_root = str_replace('\\', '/', $document_root);
$site_root = str_replace('\\', '/', $site_root);
$base_uri = '';
if ($configured_base_url !== '') {
    $configured_base_url = rtrim($configured_base_url, '/');
    $configured_path = parse_url($configured_base_url, PHP_URL_PATH);
    $base_uri = is_string($configured_path) ? $configured_path : '';
} elseif ($configured_base_uri !== '') {
    $base_uri = $configured_base_uri;
} elseif ($document_root !== '' && strpos($site_root, $document_root) === 0) {
    $base_uri = substr($site_root, strlen($document_root));
}
$base_uri = '/' . trim($base_uri, '/');
if ($base_uri === '/') {
    $base_uri = '';
}
$canonical_host = strtolower((string) preg_replace('/:\d+$/', '', $host));
if ($is_production && in_array($canonical_host, ['coinrex.xyz', 'www.coinrex.xyz'], true) && $base_uri === '/coinrex') {
    $base_uri = '';
}
define('BASE_URI', $base_uri);
define('BASE_URL', $configured_base_url !== '' ? $configured_base_url : rtrim($protocol . '://' . $host . $base_uri, '/'));
$public_base_url = trim((string) (getenv('COINREX_PUBLIC_BASE_URL') ?: ''));
$public_base_url = $public_base_url !== '' ? rtrim($public_base_url, '/') : '';
define('PUBLIC_BASE_URL_CONFIGURED', $public_base_url !== '');
define('PUBLIC_BASE_URL', $public_base_url !== '' ? $public_base_url : BASE_URL);
$rexlink_api_base_url = trim((string) (getenv('REXLINK_API_BASE_URL') ?: ''));
$rexlink_api_mode = strtolower(trim((string) (getenv('REXLINK_API_MODE') ?: 'php')));
$rexlink_use_node_api = in_array(strtolower(trim((string) (getenv('REXLINK_USE_NODE_API') ?: ''))), ['1', 'true', 'yes', 'on'], true);
if ($rexlink_api_base_url === '' && ($rexlink_api_mode === 'node' || $rexlink_use_node_api)) {
    $rexlink_api_base_url = trim((string) (getenv('REXLINK_PUBLIC_API_URL') ?: ''));
}
$rexlink_api_base_url = $rexlink_api_base_url !== '' ? rtrim($rexlink_api_base_url, '/') : '';
define('REXLINK_API_BASE_URL', $rexlink_api_base_url !== '' ? $rexlink_api_base_url : BASE_URL);
$rexlink_node_api_base_url = trim((string) (getenv('REXLINK_NODE_PUBLIC_API_URL') ?: ''));
$rexlink_node_api_base_url = $rexlink_node_api_base_url !== '' ? rtrim($rexlink_node_api_base_url, '/') : '';
define('REXLINK_NODE_API_BASE_URL', $rexlink_node_api_base_url !== '' ? $rexlink_node_api_base_url : REXLINK_API_BASE_URL);
define('REXLINK_AUTH_BRIDGE_URL', BASE_URL . '/auth/rexlink_bridge.php');
define('ASSETS_URL', BASE_URL . '/assets');
define('AUTH_URL', BASE_URL . '/auth');

// Security Keys
define('CSRF_KEY', getenv('COINREX_CSRF_KEY') ?: '');
define('ENCRYPTION_KEY', getenv('COINREX_ENCRYPTION_KEY') ?: '');

// Site Settings
define('SITE_NAME', 'CoinRex');
define('SITE_TAGLINE', 'A Web3 Trust Layer');
define('SITE_EMAIL', 'support@coinrex.xyz');

// Mail Settings
define('MAIL_SMTP_HOST', getenv('COINREX_SMTP_HOST') ?: 'smtp.gmail.com');
define('MAIL_SMTP_PORT', (int) (getenv('COINREX_SMTP_PORT') ?: 587));
define('MAIL_SMTP_SECURE', getenv('COINREX_SMTP_SECURE') ?: 'tls');
define('MAIL_SMTP_USERNAME', getenv('COINREX_SMTP_USERNAME') ?: '');
define('MAIL_SMTP_PASSWORD', getenv('COINREX_SMTP_PASSWORD') ?: '');
define('MAIL_FROM_EMAIL', getenv('COINREX_MAIL_FROM') ?: '');
define('MAIL_FROM_NAME', getenv('COINREX_MAIL_FROM_NAME') ?: (SITE_NAME . ' No Reply'));
define('MAIL_REPLY_TO_EMAIL', getenv('COINREX_MAIL_REPLY_TO') ?: SITE_EMAIL);
define('MAIL_REPLY_TO_NAME', getenv('COINREX_MAIL_REPLY_TO_NAME') ?: (SITE_NAME . ' Support'));
define('MAIL_NO_REPLY_NOTICE', 'This is an automated no-reply email. Please do not reply to this message.');

// OTP Settings
define('EMAIL_VERIFICATION_OTP_LENGTH', 6);
define('EMAIL_VERIFICATION_OTP_EXPIRY_MINUTES', 10);
define('EMAIL_VERIFICATION_OTP_RESEND_COOLDOWN_SECONDS', 120);
define('EMAIL_VERIFICATION_OTP_MAX_ATTEMPTS', 5);

// Reward Settings
define('WELCOME_BONUS_REX', 10);
define('REFERRAL_BONUS_REX', 5);
define('REFERRAL_COMMISSION_PERCENT', 15);
define('EXPERT_REFERRAL_COMMISSION_PERCENT', 30);
define('REFERRAL_VALIDATION_MIN_REX', 25);
define('PRO_TRUST_WEIGHT', 1.5);
define('EXPERT_TRUST_WEIGHT', 2.0);
define('PROJECT_VERIFICATION_SCORE_THRESHOLD', 75);
define('FEATURE_MIN_AVG_RATING', 4.0);
define('FEATURE_MIN_APPROVED_REVIEWS', 100);
define('REWARD_CLAIM_MINIMUM_REX', 100);
define('REFERRAL_MIN_COMPLETED_TASKS', 3);

// Early Adopter Airdrop Settings
define('EARLY_AIRDROP_POOL_TOTAL', 80000000);
define('EARLY_AIRDROP_SIGNUP_BONUS', 1000);
define('EARLY_AIRDROP_REFERRAL_BONUS', 50);
define('EARLY_AIRDROP_UNLOCK_DAYS', 30);
define('PRO_MIN_COMPLETED_TASKS', 7);
define('PRO_MIN_VALID_REFERRALS', 1);
define('PRO_MIN_ACCOUNT_AGE_DAYS', 7);
define('ANTI_FARM_MAX_ACCOUNTS_PER_IP', 3);
define('ANTI_FARM_MAX_LOGIN_ATTEMPTS', 10);
define('ANTI_FARM_RAPID_ACTION_WINDOW_SECONDS', 30);
define('BEGINNER_GLOBAL_TASKS_PER_DAY', 1);
define('TASKHUB_TOTAL_DAYS', 10);
define('TASKHUB_SERVER_RESET_HOUR', 0);
define('TASKHUB_PHASE1_REWARD_CAP', 150);
define('TASKHUB_MYSTERY_BOX_PERFECT_REWARD', 20);
define('TASKHUB_MYSTERY_BOX_FALLBACK_REWARD', 5);

// Database Connection Function
function getDBConnection() {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch(PDOException $e) {
        error_log('Database Connection Failed: ' . $e->getMessage());
        http_response_code(500);
        die('Database connection failed. Please try again later.');
    }
}

if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// Load functions AFTER database connection is defined
require_once INCLUDES_PATH . '/functions.php';
?>
