<?php

function coinrexRealtimeBase64UrlEncode($value) {
    return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
}

function coinrexRealtimeJson(array $payload) {
    return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function coinrexRealtimeSecret() {
    $secret = trim((string) (getenv('COINREX_REALTIME_SECRET') ?: ''));
    if ($secret !== '') {
        return $secret;
    }

    $fallback = trim((string) (defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : ''));
    if ($fallback !== '') {
        return $fallback;
    }

    $csrf = trim((string) (defined('CSRF_KEY') ? CSRF_KEY : ''));
    if ($csrf !== '') {
        return $csrf;
    }

    return ENVIRONMENT === 'production' ? '' : 'coinrex-dev-realtime-secret';
}

function coinrexRealtimeWsUrl() {
    $configured = trim((string) (getenv('COINREX_REALTIME_WS_URL') ?: ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (strpos($host, ':') !== false && substr_count($host, ':') === 1) {
        $host = explode(':', $host, 2)[0];
    }
    $scheme = coinrexIsHttpsRequest() ? 'wss' : 'ws';
    $port = (int) (getenv('COINREX_REALTIME_WS_PORT') ?: 8081);

    return $scheme . '://' . $host . ':' . $port;
}

function coinrexRealtimeEventUrl() {
    $configured = trim((string) (getenv('COINREX_REALTIME_EVENT_URL') ?: ''));
    if ($configured !== '') {
        return $configured;
    }

    $port = (int) (getenv('COINREX_REALTIME_EVENT_PORT') ?: 8082);
    return 'http://127.0.0.1:' . $port . '/events';
}

function coinrexRealtimeDebugEnabled() {
    return in_array(strtolower(trim((string) (getenv('COINREX_REALTIME_DEBUG') ?: ''))), ['1', 'true', 'yes', 'on'], true);
}

function coinrexRealtimeDebugLog($message, array $context = []) {
    if (!coinrexRealtimeDebugEnabled()) {
        return;
    }

    error_log('[CoinRex realtime] ' . $message . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : ''));
}

function coinrexRealtimeClientToken(array $actor, $ttl_seconds = 900) {
    $secret = coinrexRealtimeSecret();
    if ($secret === '') {
        throw new RuntimeException('Realtime secret is not configured.');
    }

    $user_id = (int) ($actor['user_id'] ?? 0);
    if ($user_id <= 0) {
        throw new InvalidArgumentException('Realtime token requires a user-scoped actor.');
    }

    $session_id = (int) ($actor['session_id'] ?? 0);
    $rooms = ['user:' . $user_id];
    if ($session_id > 0) {
        $rooms[] = 'session:' . $session_id;
    }

    $payload = [
        'sub' => (string) $user_id,
        'type' => (string) ($actor['type'] ?? 'user'),
        'user_id' => $user_id,
        'session_id' => $session_id,
        'rooms' => $rooms,
        'iat' => time(),
        'exp' => time() + max(60, (int) $ttl_seconds),
    ];

    $encoded = coinrexRealtimeBase64UrlEncode(coinrexRealtimeJson($payload));
    $signature = coinrexRealtimeBase64UrlEncode(hash_hmac('sha256', $encoded, $secret, true));

    return $encoded . '.' . $signature;
}

function coinrexRealtimePublish($type, array $payload = [], array $rooms = []) {
    $secret = coinrexRealtimeSecret();
    if ($secret === '') {
        return false;
    }

    $normalized_rooms = [];
    foreach ($rooms as $room) {
        $room = trim((string) $room);
        if ($room !== '') {
            $normalized_rooms[] = $room;
        }
    }

    if (empty($normalized_rooms)) {
        $user_id = (int) ($payload['user_id'] ?? 0);
        $session_id = (int) ($payload['session_id'] ?? 0);
        if ($user_id > 0) {
            $normalized_rooms[] = 'user:' . $user_id;
        }
        if ($session_id > 0) {
            $normalized_rooms[] = 'session:' . $session_id;
        }
    }

    if (empty($normalized_rooms)) {
        return false;
    }

    $event = [
        'type' => (string) $type,
        'event_id' => bin2hex(random_bytes(8)),
        'rooms' => array_values(array_unique($normalized_rooms)),
        'payload' => $payload,
        'ts' => time(),
        'created_at_ms' => (int) floor(microtime(true) * 1000),
    ];
    $body = coinrexRealtimeJson($event);
    if (!is_string($body)) {
        return false;
    }

    $headers = [
        'Content-Type: application/json',
        'X-CoinRex-Realtime-Signature: ' . hash_hmac('sha256', $body, $secret),
    ];

    $event_url = coinrexRealtimeEventUrl();
    $attempts = 2;
    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 1.5,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($event_url, false, $context);
        $decoded = is_string($result) ? json_decode($result, true) : null;
        $success = is_array($decoded) ? !empty($decoded['success']) : $result !== false;

        if ($success) {
            if ($attempt > 1) {
                coinrexRealtimeDebugLog('publish succeeded after retry', [
                    'type' => $type,
                    'event_id' => $event['event_id'],
                    'attempt' => $attempt,
                ]);
            }
            return true;
        }

        coinrexRealtimeDebugLog('publish attempt failed', [
            'type' => $type,
            'event_id' => $event['event_id'],
            'attempt' => $attempt,
            'response' => is_string($result) ? substr($result, 0, 200) : null,
        ]);

        if ($attempt < $attempts) {
            usleep(120000);
        }
    }

    return false;
}
