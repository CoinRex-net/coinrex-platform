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
$project_id = (int) ($body['project_id'] ?? 0);
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
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + 6.0;
    $timed_out = false;
    $last_status = null;

    do {
        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        $last_status = proc_get_status($process);
        if (!is_array($last_status) || empty($last_status['running'])) {
            break;
        }
        if (microtime(true) >= $deadline) {
            $timed_out = true;
            proc_terminate($process);
            break;
        }
        usleep(10000);
    } while (true);

    $stdout .= (string) stream_get_contents($pipes[1]);
    $stderr .= (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed_exit_code = proc_close($process);
    $exit_code = is_array($last_status) && isset($last_status['exitcode']) && (int) $last_status['exitcode'] >= 0
        ? (int) $last_status['exitcode']
        : $closed_exit_code;

    if ($timed_out) {
        throw new RuntimeException('Wallet signature verification timed out. Please try again.');
    }

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

$verified_nonce_message = (string) ($nonce_state['message'] ?? '');
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

try {
    verifyReviewEligibilityWalletSignature(
        $wallet_address,
        $verified_nonce_message,
        $signature
    );
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}

$db = getDBConnection();
ensureReviewEligibilitySchema($db);

$used_review = $project_id > 0 ? reviewEligibilityFindWalletReviewUsage($db, $wallet_address, 0, $project_id) : null;
if ($used_review) {
    apiErrorResponse(409, 'This Wallet already have used to Review the Same Project, Please Switch to Fresh wallet to Check Eligibility');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['review_eligibility_wallet_nonce']);
$_SESSION['review_eligibility_verified_wallet'] = [
    'user_id' => (int) $actor['user_id'],
    'wallet_address' => $wallet_address,
    'session_id' => 0,
    'verified_at' => time(),
];

apiSuccessResponse([
    'wallet_address' => $wallet_address,
    'message' => 'Wallet connected for review eligibility.',
]);

