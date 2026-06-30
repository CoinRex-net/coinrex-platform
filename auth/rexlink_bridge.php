<?php
ob_start();

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

function rexlinkBridgeBase64UrlDecode($value) {
    $value = strtr((string) $value, '-_', '+/');
    $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
    return base64_decode($value, true);
}

function rexlinkBridgeSecret() {
    $secret = trim((string) (getenv('COINREX_REALTIME_SECRET') ?: ''));
    if ($secret !== '') return $secret;
    if (defined('ENCRYPTION_KEY') && ENCRYPTION_KEY !== '') return ENCRYPTION_KEY;
    if (defined('CSRF_KEY') && CSRF_KEY !== '') return CSRF_KEY;
    return ENVIRONMENT === 'production' ? '' : 'coinrex-dev-realtime-secret';
}

try {
    $ticket = trim((string) ($_GET['ticket'] ?? ''));
    $parts = explode('.', $ticket, 2);
    if (count($parts) !== 2) {
        throw new RuntimeException('Invalid RexLink login ticket.');
    }

    [$payload_part, $signature] = $parts;
    $secret = rexlinkBridgeSecret();
    if ($secret === '') {
        throw new RuntimeException('RexLink bridge secret is not configured.');
    }

    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $payload_part, $secret, true)), '+/', '-_'), '=');
    if (!hash_equals($expected, $signature)) {
        throw new RuntimeException('Invalid RexLink login signature.');
    }

    $payload_json = rexlinkBridgeBase64UrlDecode($payload_part);
    $payload = is_string($payload_json) ? json_decode($payload_json, true) : null;
    if (!is_array($payload) || (int) ($payload['exp'] ?? 0) < time()) {
        throw new RuntimeException('RexLink login ticket expired.');
    }

    $user_id = (int) ($payload['user_id'] ?? 0);
    $user = $user_id > 0 ? getUserById($user_id) : null;
    if (!$user) {
        throw new RuntimeException('RexLink user was not found.');
    }

    establishAuthenticatedSession($user, false);
    $_SESSION['rex_signer_login_session_id'] = (int) ($payload['rex_signer_session_id'] ?? 0);
    $_SESSION['rex_signer_login_wallet_address'] = (string) ($payload['wallet_address'] ?? '');
    redirect(BASE_URL . '/public/dashboard.php');
} catch (Throwable $e) {
    setFlashMessage('auth_success', 'RexLink sign-in failed: ' . $e->getMessage());
    redirect(BASE_URL . '/auth/auth.php?tab=login');
}
