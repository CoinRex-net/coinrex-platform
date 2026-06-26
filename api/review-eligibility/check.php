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

$project_id = (int) ($body['project_id'] ?? 0);
$wallet_address = strtolower(trim((string) ($body['wallet_address'] ?? ($actor['user']['wallet_address'] ?? ''))));
if ($project_id <= 0) {
    apiErrorResponse(422, 'Valid project_id is required.');
}
if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
    apiErrorResponse(422, 'Connect a valid EVM wallet first.');
}

$user_wallet = strtolower(trim((string) ($actor['user']['wallet_address'] ?? '')));
if ($user_wallet !== $wallet_address) {
    apiErrorResponse(403, 'This wallet is not verified for your CoinRex account.');
}

$db = getDBConnection();
ensureReviewEligibilitySchema($db);

$project_stmt = $db->prepare("SELECT id, name FROM projects WHERE id = ? AND approval_status = 'approved' LIMIT 1");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch();
if (!$project) {
    apiErrorResponse(404, 'Approved project not found.');
}

try {
    $result = reviewEligibilityCheckProject($db, (int) $actor['user_id'], $project_id, $wallet_address);
    $check = $result['check'] ?? [];
    apiSuccessResponse([
        'status' => (string) ($result['status'] ?? 'not_eligible'),
        'cached' => !empty($result['cached']),
        'check_id' => (int) ($check['id'] ?? 0),
        'wallet_address' => $wallet_address,
        'project_id' => $project_id,
        'matched_chain_id' => isset($check['matched_chain_id']) ? (int) $check['matched_chain_id'] : null,
        'matched_project_contract_id' => isset($check['matched_project_contract_id']) ? (int) $check['matched_project_contract_id'] : null,
        'balance_display' => (string) ($check['balance_display'] ?? ''),
        'reason' => (string) ($check['reason'] ?? ''),
        'checked_at' => (string) ($check['checked_at'] ?? ''),
        'expires_at' => (string) ($check['expires_at'] ?? ''),
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}

