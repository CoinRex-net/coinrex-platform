<?php
/**
 * RexLink Wallet Platform — Self-contained configuration.
 * Location: /coinrex/wallet/includes/config.php
 *
 * This config is independent of the main coinrex.xyz app so the
 * wallet platform can run on its own (wallet.coinrex.xyz) without bugs.
 */

// ── Base URLs ─────────────────────────────────────────────────────
$walletForwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
$walletScheme = $walletForwardedProto === 'https'
    ? 'https'
    : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$walletHost   = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));

define('WALLET_ROOT', dirname(__DIR__));

// Build the base URL so assets, images, and internal links always resolve
// correctly regardless of deployment:
//   1. COINREX_WALLET_BASE_URL env var (explicit, recommended on production)
//   2. The request path itself (works when the platform is the subdomain root,
//      a subfolder, or behind shared hosting) — matches exactly where the
//      browser loaded the page, so styles/images can never 404.
//   3. Document-root-relative path (local dev fallback).
$walletEnvBase = trim((string) (getenv('COINREX_WALLET_BASE_URL') ?: ''));
$walletBasePath = '';

if ($walletEnvBase === '') {
    // 2) Derive from the current request path. The wallet platform always
    // lives in a folder whose basename equals the platform folder name,
    // no matter how deep the entry script is (e.g. .../wallet/index.php
    // or .../wallet/api/rexlink_download.php).
    $walletScriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $walletFolderName = basename(WALLET_ROOT);
    if ($walletFolderName !== '' && strpos($walletScriptName, '/' . $walletFolderName . '/') !== false) {
        $walletCut = strpos($walletScriptName, '/' . $walletFolderName . '/') + 1 + strlen($walletFolderName);
        $walletBasePath = rtrim(substr($walletScriptName, 0, $walletCut), '/');
    } else {
        $walletBasePath = '';
    }
}

if ($walletEnvBase === '' && $walletBasePath === '') {
    // 3) Fallback: wallet folder relative to the server document root.
    $walletDocRoot = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $walletRootPath = str_replace('\\', '/', (string) realpath(WALLET_ROOT));
    if ($walletDocRoot !== '' && $walletRootPath !== '' && strpos($walletRootPath, $walletDocRoot) === 0) {
        $walletBasePath = rtrim(substr($walletRootPath, strlen($walletDocRoot)), '/');
    }
}

define('WALLET_BASE_URL', $walletEnvBase !== ''
    ? rtrim($walletEnvBase, '/')
    : ($walletScheme . '://' . $walletHost . $walletBasePath));
define('WALLET_ASSETS_URL', WALLET_BASE_URL . '/assets');
define('WALLET_APK_URL', WALLET_BASE_URL . '/apk/RexLink.apk');

// ── Main coinrex.xyz site (nav "CoinRex/" link) ──────────────────
define('WALLET_MAIN_SITE_URL', rtrim(trim((string) (getenv('COINREX_MAIN_SITE_URL') ?: 'https://coinrex.xyz')), '/'));

// ── Site identity ─────────────────────────────────────────────────
define('WALLET_NAME', 'RexLink');
define('WALLET_SITE_NAME', 'CoinRex');
define('WALLET_TAGLINE', 'Extension Free Web3 Access');
define('WALLET_SUPPORT_EMAIL', 'support@coinrex.xyz');
define('WALLET_ADMIN_EMAIL', 'admin@coinrex.xyz');
define('WALLET_APK_VERSION', '1.0.0');
define('WALLET_PACKAGE_NAME', 'com.coinrex.rexlink');

// ── APK file ──────────────────────────────────────────────────────
define('WALLET_APK_PATH', WALLET_ROOT . '/apk/RexLink.apk');

// ── Database (shared with the main app so download counts line up) ─
define('WALLET_DB_HOST', trim((string) (getenv('COINREX_DB_HOST') ?: '')) ?: 'localhost');
define('WALLET_DB_NAME', trim((string) (getenv('COINREX_DB_NAME') ?: '')) ?: 'koinrex');
define('WALLET_DB_USER', trim((string) (getenv('COINREX_DB_USER') ?: '')) ?: 'root');
define('WALLET_DB_PASS', (string) (getenv('COINREX_DB_PASS') ?: ''));

/**
 * Return a PDO connection for download metrics (graceful on failure).
 */
function walletDb(): ?PDO
{
    static $pdo = false;

    if ($pdo !== false) {
        return $pdo ?: null;
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . WALLET_DB_HOST . ';dbname=' . WALLET_DB_NAME . ';charset=utf8mb4',
            WALLET_DB_USER,
            WALLET_DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS rexlink_downloads (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL DEFAULT '',
                user_agent VARCHAR(500) NOT NULL DEFAULT '',
                referrer VARCHAR(500) NOT NULL DEFAULT '',
                downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_rexlink_downloads_date (downloaded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Throwable $e) {
        $pdo = false;
    }

    return $pdo ?: null;
}

/**
 * Total recorded downloads.
 */
function walletDownloadCount(): int
{
    $db = walletDb();
    if ($db === null) {
        return 0;
    }
    try {
        return (int) ($db->query('SELECT COUNT(*) FROM rexlink_downloads')->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        return 0;
    }
}

// ── Download availability (temporary kill-switch) ─────────────
// Set to false to disable every "Download APK" button across the
// wallet platform. Flip back to true to re-enable downloads.
define('WALLET_DOWNLOADS_ENABLED', false);

/**
 * Render the platform's primary "Download APK" button.
 * When downloads are disabled it renders a muted "Coming Soon" state.
 */
function walletDownloadButton(): string
{
    if (WALLET_DOWNLOADS_ENABLED) {
        return '<a class="wallet-btn-download" href="' . htmlspecialchars(WALLET_BASE_URL . '/download.php', ENT_QUOTES, 'UTF-8') . '">'
            . '<i class="fas fa-download"></i> Download APK</a>';
    }
    return '<span class="wallet-btn-download is-disabled" aria-disabled="true" title="Downloads coming soon">'
        . '<i class="fas fa-download"></i> Coming Soon</span>';
}

/**
 * Render the nav "Download APK" CTA (respects the kill-switch too).
 */
function walletNavDownloadCta(string $class = 'wallet-nav-cta'): string
{
    if (WALLET_DOWNLOADS_ENABLED) {
        return '<a class="' . $class . '" href="' . htmlspecialchars(WALLET_BASE_URL . '/download.php', ENT_QUOTES, 'UTF-8') . '">'
            . '<i class="fas fa-download"></i> Download APK</a>';
    }
    return '<span class="' . $class . ' is-disabled" aria-disabled="true" title="Downloads coming soon">'
        . '<i class="fas fa-download"></i> Coming Soon</span>';
}

/**
 * APK existence + size metadata.
 */
function walletApkInfo(): array
{
    $exists = is_file(WALLET_APK_PATH);
    $sizeMb = 0;
    if ($exists) {
        $sizeMb = round(filesize(WALLET_APK_PATH) / (1024 * 1024), 1);
    }
    return [
        'exists' => $exists,
        'size_mb' => $sizeMb,
        'path' => WALLET_APK_PATH,
    ];
}
