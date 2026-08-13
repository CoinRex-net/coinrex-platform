<?php
/**
 * RexLink Wallet Platform — APK download endpoint.
 * Location: /coinrex/wallet/download.php
 *
 * Serves the APK file and records each download for the download counter.
 */

require_once __DIR__ . '/includes/config.php';

$apkInfo = walletApkInfo();

if (!$apkInfo['exists']) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('RexLink APK is not available yet. Please check back soon.');
}

// ── Record download (best-effort, never blocks the download) ──
$db = walletDb();
if ($db !== null) {
    try {
        $stmt = $db->prepare(
            'INSERT INTO rexlink_downloads (ip_address, user_agent, referrer) VALUES (:ip, :ua, :ref)'
        );
        $stmt->execute([
            ':ip'  => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ':ua'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ':ref' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500),
        ]);
    } catch (Throwable $e) {
        // Ignore — a DB hiccup must not break the file download.
    }
}

// ── Stream the file ──
$file = WALLET_APK_PATH;
$size = (int) filesize($file);

// Support HTTP Range requests (resumable downloads).
$start = 0;
$end   = $size - 1;
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    if (preg_match('/bytes=(\d*)-(\d*)/', (string) $range, $m)) {
        if ($m[1] !== '') {
            $start = (int) $m[1];
        }
        if ($m[2] !== '') {
            $end = min((int) $m[2], $size - 1);
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }
}

$length = $end - $start + 1;

header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="RexLink.apk"');
header('Content-Length: ' . $length);
header('Accept-Ranges: bytes');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache');

$fp = @fopen($file, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit('Unable to open the APK file.');
}

if ($start > 0) {
    fseek($fp, $start);
}

$sent = 0;
while (!feof($fp) && $sent < $length) {
    $chunkSize = min(8192, $length - $sent);
    $chunk = fread($fp, $chunkSize);
    if ($chunk === false) {
        break;
    }
    echo $chunk;
    $sent += strlen($chunk);
}
fclose($fp);
exit;
