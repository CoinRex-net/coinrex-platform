<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('GET');

apiSuccessResponse([
    'service' => 'rexlink-php',
    'api_version' => 'legacy',
    'base_url' => rtrim((string) (defined('PUBLIC_BASE_URL') ? PUBLIC_BASE_URL : BASE_URL), '/') . '/api/rex-signer',
    'endpoints' => [
        'create_pairing' => '/create_pairing.php',
        'complete_pairing' => '/complete_pairing.php',
        'sessions' => '/sessions.php',
        'pairing_qr' => '/pairing_qr.php',
    ],
]);