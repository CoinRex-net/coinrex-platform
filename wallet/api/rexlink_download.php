<?php
/**
 * RexLink APK Download API — Independent Version
 * Location: /coinrex/wallet/api/rexlink_download.php
 *
 * Consistent with the self-contained wallet platform.
 * Returns JSON download info. Actual APK streaming happens in
 * wallet/download.php so the counter stays in sync.
 */

require_once __DIR__ . '/../includes/config.php';

// Only allow GET requests
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$apkInfo = walletApkInfo();
$totalDownloads = walletDownloadCount();

$response = [
    'success' => true,
    'apk_exists' => $apkInfo['exists'],
    'apk_size_mb' => $apkInfo['size_mb'],
    'apk_version' => WALLET_APK_VERSION,
    'total_downloads' => $totalDownloads,
    'download_url' => WALLET_BASE_URL . '/download.php',
    'package_name' => WALLET_PACKAGE_NAME,
];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
