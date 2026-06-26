<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $payload = trim((string) ($_GET['payload'] ?? ''));
    if ($payload === '' || strlen($payload) > 2000) {
        apiErrorResponse(422, 'Valid QR payload is required.');
    }
    $decoded_payload = json_decode($payload, true);
    $is_auth_pairing_payload = is_array($decoded_payload)
        && ($decoded_payload['type'] ?? '') === 'coinrex.rex_signer.pairing'
        && ($decoded_payload['purpose'] ?? '') === 'auth';

    if (!$is_auth_pairing_payload) {
        $actor = apiGetAuthenticatedUser();
        if ($actor['type'] !== 'user' || empty($actor['user_id'])) {
            apiErrorResponse(403, 'Only logged-in CoinRex users can render pairing QR codes.');
        }
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $project_root = dirname(__DIR__, 2);
    $cache_dir = $project_root . '/cache/rexlink-qr';
    if (!is_dir($cache_dir) && !@mkdir($cache_dir, 0775, true) && !is_dir($cache_dir)) {
        apiErrorResponse(500, 'QR cache directory could not be created.');
    }

    $cache_key = hash('sha256', $payload . '|v1');
    $cache_file = $cache_dir . '/' . $cache_key . '.svg';
    $cache_ttl_seconds = 60;

    if (is_readable($cache_file)
        && filemtime($cache_file) > 0
        && (time() - filemtime($cache_file)) < $cache_ttl_seconds) {
        $cached_svg = file_get_contents($cache_file);
        if (is_string($cached_svg) && trim($cached_svg) !== '') {
            header('Content-Type: image/svg+xml; charset=utf-8');
            header('Cache-Control: private, max-age=30, stale-while-revalidate=30');
            echo $cached_svg;
            exit;
        }
    }

    $script = $project_root . '/scripts/render-qr-svg.js';
    if (!is_file($script)) {
        apiErrorResponse(500, 'QR renderer is missing.');
    }

    $node_binary = trim((string) (getenv('NODE_BINARY') ?: 'node'));
    $command = escapeshellarg($node_binary) . ' ' . escapeshellarg($script);
    $descriptor_spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor_spec, $pipes, $project_root);
    if (!is_resource($process)) {
        apiErrorResponse(500, 'QR renderer could not start.');
    }

    fwrite($pipes[0], json_encode([
        'text' => $payload,
        'width' => 220,
        'margin' => 2,
    ], JSON_UNESCAPED_SLASHES));
    fclose($pipes[0]);
    $svg = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);

    if ($exit_code !== 0 || trim((string) $svg) === '') {
        apiErrorResponse(500, trim((string) $stderr) ?: 'QR render failed.');
    }

    if (file_put_contents($cache_file, $svg) === false) {
        // Fall back to serving the freshly rendered image without blocking the request.
    }

    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: private, max-age=30, stale-while-revalidate=30');
    echo $svg;
    exit;
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
