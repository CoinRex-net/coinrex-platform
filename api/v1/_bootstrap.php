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

function apiV1RexLinkProxyTarget() {
    $port = max(1, min(65535, (int) (getenv('REXLINK_API_PORT') ?: 18083)));
    $candidates = [
        getenv('REXLINK_NODE_INTERNAL_API_URL') ?: '',
        'http://127.0.0.1:' . $port,
        'http://localhost:' . $port,
        defined('REXLINK_NODE_API_BASE_URL') ? REXLINK_NODE_API_BASE_URL : '',
        getenv('REXLINK_API_BASE_URL') ?: '',
    ];

    foreach ($candidates as $candidate) {
        $candidate = rtrim(trim((string) $candidate), '/');
        if ($candidate === '') {
            continue;
        }

        $candidate_host = strtolower((string) parse_url($candidate, PHP_URL_HOST));
        $candidate_port = (string) (parse_url($candidate, PHP_URL_PORT) ?: '');
        $base_host = strtolower((string) parse_url(BASE_URL, PHP_URL_HOST));
        $base_port = (string) (parse_url(BASE_URL, PHP_URL_PORT) ?: '');
        $candidate_path = rtrim((string) (parse_url($candidate, PHP_URL_PATH) ?: ''), '/');
        $base_path = rtrim((string) (parse_url(BASE_URL, PHP_URL_PATH) ?: ''), '/');

        if ($candidate_host === $base_host && $candidate_port === $base_port && $candidate_path === $base_path) {
            continue;
        }

        return $candidate;
    }

    return '';
}

function apiV1RewriteRexLinkPayloadUrls($value) {
    if (is_array($value)) {
        foreach ($value as $key => $child) {
            if (($key === 'api_base_url' || $key === 'base_url') && is_string($child) && preg_match('#^https?://#i', $child)) {
                $value[$key] = BASE_URL;
                continue;
            }
            $value[$key] = apiV1RewriteRexLinkPayloadUrls($child);
        }
    }
    return $value;
}

function apiV1RewriteRexLinkResponseBody($body, $content_type) {
    if (stripos((string) $content_type, 'application/json') === false) {
        return $body;
    }

    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        return $body;
    }

    return json_encode(
        apiV1RewriteRexLinkPayloadUrls($decoded),
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
}

function apiV1ProxyRexLinkResponseHeaders($header_lines, &$content_type) {
    foreach ($header_lines as $line) {
        if (stripos($line, 'content-type:') === 0) {
            $content_type = trim(substr($line, 13));
            header($line, false);
        } elseif (stripos($line, 'cache-control:') === 0 || stripos($line, 'access-control-') === 0 || stripos($line, 'vary:') === 0) {
            header($line, false);
        }
    }
}

function apiV1ProxyRexLinkRequest($target_path) {
    $base = apiV1RexLinkProxyTarget();
    if ($base === '') {
        apiV1ErrorResponse(503, 'RexLink API upstream is not configured.');
    }

    $method = strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
    $query_parts = [];
    foreach ($_GET as $key => $value) {
        if ($key !== 'path') {
            $query_parts[$key] = $value;
        }
    }
    $query = http_build_query($query_parts);
    $url = $base . $target_path . ($query !== '' ? '?' . $query : '');
    $body = file_get_contents('php://input');
    $headers = ['Accept: application/json'];

    $content_type = trim((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($content_type !== '') {
        $headers[] = 'Content-Type: ' . $content_type;
    }

    $forward_headers = [
        'HTTP_AUTHORIZATION' => 'Authorization',
        'HTTP_X_REX_SIGNER_SESSION' => 'X-REX-SIGNER-SESSION',
        'HTTP_X_COINREX_WEB_ACTOR' => 'X-CoinRex-Web-Actor',
        'HTTP_X_REXLINK_APP_ID' => 'X-RexLink-App-ID',
        'HTTP_ORIGIN' => 'Origin',
        'HTTP_COOKIE' => 'Cookie',
    ];
    foreach ($forward_headers as $server_key => $header_name) {
        if (!empty($_SERVER[$server_key])) {
            $headers[] = $header_name . ': ' . (string) $_SERVER[$server_key];
        }
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 12,
        ]);
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body === false ? '' : $body);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $message = curl_error($ch) ?: 'RexLink API upstream did not respond.';
            curl_close($ch);
            apiV1ErrorResponse(502, $message);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $header_size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $response_headers = substr((string) $raw, 0, $header_size);
        $response_body = substr((string) $raw, $header_size);
        curl_close($ch);

        $response_content_type = '';
        apiV1ProxyRexLinkResponseHeaders(preg_split('/\r\n|\n|\r/', $response_headers), $response_content_type);
        http_response_code($status > 0 ? $status : 502);
        echo apiV1RewriteRexLinkResponseBody($response_body, $response_content_type);
        exit;
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => in_array($method, ['GET', 'HEAD'], true) ? '' : ($body === false ? '' : $body),
            'ignore_errors' => true,
            'timeout' => 12,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    $status = 502;
    $response_content_type = '';
    foreach (($http_response_header ?? []) as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $matches)) {
            $status = (int) $matches[1];
            continue;
        }
        apiV1ProxyRexLinkResponseHeaders([$line], $response_content_type);
    }

    http_response_code($status);
    echo $response === false
        ? json_encode(['success' => false, 'message' => 'RexLink API upstream did not respond.'])
        : apiV1RewriteRexLinkResponseBody($response, $response_content_type);
    exit;
}
