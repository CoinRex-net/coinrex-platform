<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

$db = getDBConnection();
$requested_slug = coinrexWidgetNormalizeSlug($_GET['slug'] ?? '');
$requested_domains_raw = $_GET['domains'] ?? ($_GET['domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$requested_domains = coinrexWidgetNormalizeDomainsInput($requested_domains_raw);

if (empty($requested_domains)) {
    $fallback_domain = coinrexWidgetNormalizeDomain($_SERVER['HTTP_HOST'] ?? 'localhost');
    if ($fallback_domain !== '') {
        $requested_domains = [$fallback_domain];
    }
}

$selected_slug = '';

if ($requested_slug !== '') {
    $selected_slug = $requested_slug;
} elseif (tableHasColumn('projects', 'slug')) {
    $stmt = $db->query("SELECT slug FROM projects WHERE LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) = 'approved' AND TRIM(COALESCE(slug, '')) <> '' ORDER BY id DESC LIMIT 1");
    $selected_slug = coinrexWidgetNormalizeSlug((string) $stmt->fetchColumn());
}

if ($selected_slug === '') {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'No approved project slug found for token generation.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

$token = coinrexGenerateWidgetToken($selected_slug, $requested_domains, 86400);

echo json_encode([
    'success' => $token !== '',
    'slug' => $selected_slug,
    'domains' => $requested_domains,
    'token' => $token,
    'rating_url' => BASE_URL . '/api/v1/project/' . rawurlencode($selected_slug) . '/rating',
    'widget_url' => BASE_URL . '/api/v1/project/' . rawurlencode($selected_slug) . '/widget?token=' . rawurlencode($token),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
