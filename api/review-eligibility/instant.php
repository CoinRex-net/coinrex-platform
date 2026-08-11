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

$db = getDBConnection();
ensureReviewEligibilitySchema($db);

$session_wallet = $_SESSION['review_eligibility_verified_wallet'] ?? null;
$has_review_wallet_session = is_array($session_wallet)
    && (int) ($session_wallet['user_id'] ?? 0) === (int) $actor['user_id']
    && strtolower((string) ($session_wallet['wallet_address'] ?? '')) === $wallet_address
    && time() - (int) ($session_wallet['verified_at'] ?? 0) <= 900;

if (!$has_review_wallet_session) {
    $session_stmt = $db->prepare("
        SELECT id
        FROM rex_signer_sessions
        WHERE user_id = ?
          AND wallet_address = ?
          AND status = 'active'
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $session_stmt->execute([(int) $actor['user_id'], $wallet_address]);
    $has_review_wallet_session = (bool) $session_stmt->fetch();
}

if (!$has_review_wallet_session) {
    apiErrorResponse(403, 'Pair or verify this wallet for the current review session first.');
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$used_review = reviewEligibilityFindWalletReviewUsage($db, $wallet_address, 0, $project_id);
if ($used_review) {
    apiErrorResponse(409, 'This Wallet already have used to Review the Same Project, Please Switch to Fresh wallet to Check Eligibility');
}

$project_stmt = $db->prepare("SELECT id, name FROM projects WHERE id = ? AND approval_status = 'approved' LIMIT 1");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch();
if (!$project) {
    apiErrorResponse(404, 'Approved project not found.');
}

try {
    $result = reviewEligibilityInstantCheck($db, (int) $actor['user_id'], $project_id, $wallet_address);
    $check = $result['check'] ?? [];
    $raw_result = [];
    if (!empty($check['raw_result_json'])) {
        $decoded_raw = json_decode((string) $check['raw_result_json'], true);
        if (is_array($decoded_raw)) {
            $raw_result = $decoded_raw;
        }
    }
    $eligibility_detail = [];
    foreach (($raw_result['results'] ?? []) as $candidate_detail) {
        if (is_array($candidate_detail) && isset($candidate_detail['decision'])) {
            $eligibility_detail = $candidate_detail;
            break;
        }
    }
    $balances = is_array($eligibility_detail['balances'] ?? null) ? $eligibility_detail['balances'] : [];
    $requirement = is_array($eligibility_detail['requirement'] ?? null) ? $eligibility_detail['requirement'] : [];
    $holding = is_array($eligibility_detail['holding'] ?? null) ? $eligibility_detail['holding'] : [];
    $status = (string) ($result['status'] ?? 'not_eligible');
    $suggested_methods = [];
    if ($status === 'not_eligible') {
        $suggested_methods = ['live', 'manual'];
    } elseif ($status === 'blocked') {
        $suggested_methods = ['live', 'manual'];
    }
    apiSuccessResponse([
        'status' => $status,
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
        'current_balance' => isset($balances['current_balance']) ? (float) $balances['current_balance'] : null,
        'average_balance' => isset($balances['average_balance']) ? (float) $balances['average_balance'] : null,
        'required_balance' => isset($requirement['min_holding_amount']) ? (float) $requirement['min_holding_amount'] : null,
        'required_days' => isset($requirement['required_holding_days']) ? (int) $requirement['required_holding_days'] : null,
        'holding_days' => isset($holding['holding_days']) ? (float) $holding['holding_days'] : null,
        'token_symbol' => (string) ($requirement['token_symbol'] ?? ''),
        'suggested_methods' => $suggested_methods,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage(), ['reason_code' => 'instant_check_failed']);
}