<?php
require_once dirname(__DIR__) . '/_bootstrap.php';

apiRequireMethod('POST');

$actor = apiGetAuthenticatedUser();
if ($actor['type'] !== 'user' || empty($actor['user_id'])) {
    apiErrorResponse(403, 'User authentication required.');
}

$raw = file_get_contents('php://input');
$body = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($body)) {
    $body = $_POST;
}

$wallet_address = strtolower(trim((string) ($body['wallet_address'] ?? '')));
if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
    apiErrorResponse(422, 'Valid EVM wallet address is required.');
}

$nonce = bin2hex(random_bytes(16));
$issued_at = time();
$message = "CoinRex review eligibility\nWallet: {$wallet_address}\nNonce: {$nonce}\nIssued: {$issued_at}";

$_SESSION['review_eligibility_wallet_nonce'] = [
    'wallet_address' => $wallet_address,
    'nonce' => $nonce,
    'message' => $message,
    'issued_at' => $issued_at,
    'user_id' => (int) $actor['user_id'],
];

apiSuccessResponse([
    'wallet_address' => $wallet_address,
    'nonce' => $nonce,
    'message' => $message,
    'expires_in' => 300,
]);

