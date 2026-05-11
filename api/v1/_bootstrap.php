<?php
define('COINREX_SKIP_SESSION_INIT', true);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

function apiV1JsonResponse($status_code, array $payload) {
    http_response_code((int) $status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
    exit;
}

function apiV1ErrorResponse($status_code, $message, array $extra = []) {
    apiV1JsonResponse((int) $status_code, array_merge([
        'error' => [
            'code' => (int) $status_code,
            'message' => (string) $message,
        ],
    ], $extra));
}

function apiV1ResolvePath() {
    $path = trim((string) ($_GET['path'] ?? ($_SERVER['PATH_INFO'] ?? '')));
    $path = preg_replace('#^/+|/+$#', '', $path);
    return (string) $path;
}

function apiV1SendRateLimitHeaders(array $rate_limit_state) {
    if (isset($rate_limit_state['remaining'])) {
        header('X-RateLimit-Remaining: ' . max(0, (int) $rate_limit_state['remaining']));
    }
    if (!empty($rate_limit_state['reset_at'])) {
        header('X-RateLimit-Reset: ' . (int) $rate_limit_state['reset_at']);
    }
}