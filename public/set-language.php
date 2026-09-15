<?php
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed');
}

requireAppCsrf((string) ($_POST['csrf_token'] ?? ''));

$locale = coinrexNormalizeLocale((string) ($_POST['language'] ?? ''));
$redirect_to = trim((string) ($_POST['redirect_to'] ?? ''));

if ($locale === '') {
    setFlashMessage('locale_error', t('locale.invalid'));
} else {
    coinrexSetLocale($locale);
    setFlashMessage('locale_success', t('locale.saved'));
}

$fallback = defined('BASE_URL') ? BASE_URL . '/index.php' : '/';
$target = $fallback;

if ($redirect_to !== '') {
    $parts = parse_url($redirect_to);
    $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
    $query = is_array($parts) && isset($parts['query']) ? '?' . (string) $parts['query'] : '';
    if ($path !== '' && strpos($path, '//') !== 0) {
        $target = $path . $query;
    }
}

redirect($target);
