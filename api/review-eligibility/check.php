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
$ownership_verified_at = null;
$has_review_wallet_session = is_array($session_wallet)
    && (int) ($session_wallet['user_id'] ?? 0) === (int) $actor['user_id']
    && strtolower((string) ($session_wallet['wallet_address'] ?? '')) === $wallet_address
    && time() - (int) ($session_wallet['verified_at'] ?? 0) <= 900;
if ($has_review_wallet_session) {
    $ownership_verified_at = date('Y-m-d H:i:s', (int) $session_wallet['verified_at']);
}

if (!$has_review_wallet_session) {
    $session_stmt = $db->prepare("
        SELECT id, created_at
        FROM rex_signer_sessions
        WHERE user_id = ?
          AND app_id = 'coinrex'
          AND wallet_address = ?
          AND status = 'active'
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $session_stmt->execute([(int) $actor['user_id'], $wallet_address]);
    $rex_session = $session_stmt->fetch();
    $has_review_wallet_session = (bool) $rex_session;
    if ($rex_session) {
        $ownership_verified_at = (string) ($rex_session['created_at'] ?? date('Y-m-d H:i:s'));
    }
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
    $monitoring = reviewEligibilityMonitoringStart(
        $db,
        (int) $actor['user_id'],
        $project_id,
        $wallet_address,
        $ownership_verified_at ?: date('Y-m-d H:i:s')
    );
    apiSuccessResponse(array_merge([
        'message' => (string) ($monitoring['reason'] ?? 'Holding verification started.'),
    ], reviewEligibilityMonitoringPayload($monitoring)), 201);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage(), ['reason_code' => 'monitoring_not_started']);
}

