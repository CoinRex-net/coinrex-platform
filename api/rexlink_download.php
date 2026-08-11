<?php
/**
 * RexLink APK Download Tracker
 * Records download metrics and redirects to the APK file.
 * Location: /coinrex/api/rexlink_download.php
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Only allow GET requests
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$apk_path = ASSETS_PATH . '/downloads/RexLink.apk';
$apk_url = ASSETS_URL . '/downloads/RexLink.apk';

// If APK doesn't exist, redirect to the wallet page
if (!is_file($apk_path)) {
    header('Location: ' . BASE_URL . '/wallet.php');
    exit;
}

// Record the download
try {
    $db = getDBConnection();

    // Ensure table exists
    $db->exec("
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

    $ip_address = substr(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45);
    $user_agent = substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500);
    $referrer = substr(trim((string) ($_SERVER['HTTP_REFERER'] ?? '')), 0, 500);

    $insert = $db->prepare("
        INSERT INTO rexlink_downloads (ip_address, user_agent, referrer)
        VALUES (?, ?, ?)
    ");
    $insert->execute([$ip_address, $user_agent, $referrer]);
} catch (Throwable $e) {
    // Non-fatal: continue serving the file even if tracking fails
    error_log('RexLink download tracking failed: ' . $e->getMessage());
}

// Serve the APK file with proper headers
$file_size = (int) filesize($apk_path);
$file_name = 'RexLink.apk';

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . $file_size);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// Stream the file
$handle = fopen($apk_path, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit;
}

while (!feof($handle)) {
    $chunk = fread($handle, 8192);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    flush();
}

fclose($handle);
exit;