<?php
/** Auto-split from legacy functions.php */

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function validatePasswordPolicy($password) {
    $requirements = [
        'length' => strlen($password) >= 9,
        'uppercase' => preg_match('/[A-Z]/', $password) === 1,
        'digit' => preg_match('/\d/', $password) === 1,
        'special' => preg_match('/[^A-Za-z0-9]/', $password) === 1,
    ];
    
    return [
        'is_valid' => !in_array(false, $requirements, true),
        'requirements' => $requirements,
    ];
}

function appCsrfToken() {
    if (empty($_SESSION[APP_CSRF_SESSION_KEY])) {
        $_SESSION[APP_CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION[APP_CSRF_SESSION_KEY];
}

function validateAppCsrfToken($token) {
    $session_token = $_SESSION[APP_CSRF_SESSION_KEY] ?? '';

    return is_string($token)
        && is_string($session_token)
        && $token !== ''
        && hash_equals($session_token, $token);
}

function requireAppCsrf($token) {
    if (!validateAppCsrfToken($token)) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

function getClientIpAddress() {
    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}
