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
$signature = trim((string) ($body['signature'] ?? ''));
$nonce_state = $_SESSION['review_eligibility_wallet_nonce'] ?? null;

function verifyReviewEligibilityWalletSignature($wallet_address, $message, $signature) {
    $script = dirname(__DIR__, 2) . '/scripts/verify-evm-message.js';
    if (!is_file($script)) {
        throw new RuntimeException('Wallet signature verifier is missing.');
    }

    $node_binary = trim((string) (getenv('NODE_BINARY') ?: 'node'));
    $command = escapeshellarg($node_binary) . ' ' . escapeshellarg($script);
    $descriptor_spec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor_spec, $pipes, dirname(__DIR__, 2));
    if (!is_resource($process)) {
        throw new RuntimeException('Wallet signature verifier could not start.');
    }

    fwrite($pipes[0], json_encode([
        'wallet_address' => $wallet_address,
        'message' => $message,
        'signature' => $signature,
    ], JSON_UNESCAPED_SLASHES));
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);

    if ($exit_code !== 0) {
        throw new RuntimeException(trim((string) $stderr) ?: 'Wallet signature verification failed.');
    }

    $decoded = json_decode((string) $stdout, true);
    if (!is_array($decoded) || empty($decoded['valid'])) {
        throw new RuntimeException('Wallet signature does not match the requested address.');
    }

    return $decoded;
}

if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
    apiErrorResponse(422, 'Valid EVM wallet address is required.');
}
if (!is_array($nonce_state)
    || (int) ($nonce_state['user_id'] ?? 0) !== (int) $actor['user_id']
    || strtolower((string) ($nonce_state['wallet_address'] ?? '')) !== $wallet_address
    || time() - (int) ($nonce_state['issued_at'] ?? 0) > 300
) {
    apiErrorResponse(422, 'Wallet verification nonce expired. Please reconnect.');
}
if (!preg_match('/^0x[a-fA-F0-9]{130}$/', $signature)) {
    apiErrorResponse(422, 'Valid wallet signature is required.');
}

try {
    verifyReviewEligibilityWalletSignature(
        $wallet_address,
        (string) ($nonce_state['message'] ?? ''),
        $signature
    );
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}

$db = getDBConnection();
ensureReviewEligibilitySchema($db);

$owner = $db->prepare("SELECT id FROM users WHERE wallet_address = ? AND id <> ? LIMIT 1");
$owner->execute([$wallet_address, (int) $actor['user_id']]);
if ($owner->fetch()) {
    apiErrorResponse(409, 'This wallet is already linked to another CoinRex account.');
}

$has_auth_provider = tableHasColumn('users', 'auth_provider');
$has_wallet_verified_at = tableHasColumn('users', 'wallet_verified_at');
$updates = ['wallet_address = ?', 'updated_at = NOW()'];
$params = [$wallet_address];
if ($has_wallet_verified_at) {
    $updates[] = 'wallet_verified_at = NOW()';
}
if ($has_auth_provider) {
    $updates[] = "auth_provider = CASE WHEN auth_provider = 'email' THEN 'hybrid' ELSE auth_provider END";
}
$params[] = (int) $actor['user_id'];
$stmt = $db->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?');
$stmt->execute($params);

unset($_SESSION['review_eligibility_wallet_nonce']);

apiSuccessResponse([
    'wallet_address' => $wallet_address,
    'message' => 'Wallet connected for review eligibility.',
]);

