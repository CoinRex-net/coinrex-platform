<?php
require_once __DIR__ . '/_bootstrap.php';

coinrexWidgetSendSecurityHeaders();

$path = apiV1ResolvePath();
if (!preg_match('#^project/([a-z0-9\-]+)/((?:rating|widget))$#i', $path, $matches)) {
    coinrexWidgetSendCorsHeaders('*');
    apiV1ErrorResponse(404, 'Endpoint not found.');
}

$slug = coinrexWidgetNormalizeSlug($matches[1] ?? '');
$resource = strtolower(trim((string) ($matches[2] ?? '')));
$method = strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')));

if (!in_array($method, ['GET', 'OPTIONS'], true)) {
    coinrexWidgetSendCorsHeaders('*');
    apiV1ErrorResponse(405, 'Method not allowed.');
}

if ($resource === 'rating') {
    coinrexWidgetSendCorsHeaders('*');

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $rate_limit = coinrexWidgetEnforceRateLimit('rating', coinrexWidgetClientFingerprint($slug), 240, 60);
    apiV1SendRateLimitHeaders($rate_limit);
    if (!$rate_limit['allowed']) {
        apiV1ErrorResponse(429, 'Too many rating requests. Please retry shortly.');
    }

    $payload = coinrexWidgetGetProjectRatingBySlug($slug);
    if (!$payload) {
        apiV1ErrorResponse(404, 'Project rating not found.');
    }

    header('Cache-Control: public, max-age=60, s-maxage=300');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', strtotime((string) ($payload['updated_at'] ?? 'now'))) . ' GMT');
    apiV1JsonResponse(200, $payload);
}

$origin = coinrexWidgetGetRequestOrigin();
$token = trim((string) ($_GET['token'] ?? ''));

if ($method === 'OPTIONS' && $token === '') {
    coinrexWidgetSendCorsHeaders('*');
    http_response_code(204);
    exit;
}

$verification = coinrexValidateWidgetToken($token);
if (!$verification['valid']) {
    coinrexWidgetSendCorsHeaders('*');
    apiV1ErrorResponse(401, $verification['message'], [
        'hint' => 'Provide a signed widget token via the token query parameter.',
    ]);
}

$token_payload = (array) ($verification['payload'] ?? []);
if (($token_payload['slug'] ?? '') !== $slug) {
    coinrexWidgetSendCorsHeaders('*');
    apiV1ErrorResponse(403, 'Widget token slug does not match the requested project.');
}

if ($origin === '' && ENVIRONMENT !== 'production') {
    $origin = BASE_URL;
}

if (!coinrexWidgetOriginIsAllowed($origin, (array) ($token_payload['domains'] ?? []))) {
    coinrexWidgetSendCorsHeaders('*');
    apiV1ErrorResponse(403, 'The requesting domain is not allowed for this widget token.');
}

coinrexWidgetSendCorsHeaders($origin);

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$rate_limit = coinrexWidgetEnforceRateLimit(
    'widget',
    coinrexWidgetClientFingerprint($slug . '|' . sha1($token)),
    120,
    60
);
apiV1SendRateLimitHeaders($rate_limit);
if (!$rate_limit['allowed']) {
    apiV1ErrorResponse(429, 'Too many widget requests. Please retry shortly.');
}

$payload = coinrexWidgetGetProjectRatingBySlug($slug);
if (!$payload) {
    apiV1ErrorResponse(404, 'Project widget data not found.');
}

$response = coinrexWidgetBuildWidgetPayload($payload);
$response['secured'] = [
    'token_valid' => true,
    'allowed_domains' => (array) ($token_payload['domains'] ?? []),
    'expires_at' => coinrexWidgetIsoTimestamp(date('Y-m-d H:i:s', (int) ($token_payload['exp'] ?? time()))),
];

header('Cache-Control: public, max-age=60, s-maxage=300');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', strtotime((string) ($payload['updated_at'] ?? 'now'))) . ' GMT');
apiV1JsonResponse(200, $response);