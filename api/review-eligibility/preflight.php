<?php
define('COINREX_SKIP_REWARD_SCHEMA_INIT', true);
require_once dirname(__DIR__) . '/_bootstrap.php';

apiRequireMethod('GET');

$actor = apiGetAuthenticatedUser();
if ($actor['type'] !== 'user' || empty($actor['user_id'])) {
    apiErrorResponse(403, 'User authentication required.');
}

$project_id = (int) ($_GET['project_id'] ?? 0);
if ($project_id <= 0) {
    apiErrorResponse(422, 'Valid project_id is required.');
}

$db = getDBConnection();
ensureReviewEligibilitySchema($db);
$user_id = (int) $actor['user_id'];
$wallet_address = strtolower(trim((string) ($actor['user']['wallet_address'] ?? '')));
$has_wallet = (bool) preg_match('/^0x[a-f0-9]{40}$/', $wallet_address);

$project_stmt = $db->prepare("SELECT id, name FROM projects WHERE id = ? AND approval_status = 'approved' LIMIT 1");
$project_stmt->execute([$project_id]);
$project = $project_stmt->fetch();
if (!$project) {
    apiErrorResponse(404, 'Approved project not found.');
}

$existing_stmt = $db->prepare("SELECT id, status, proof_status FROM reviews WHERE user_id = ? AND project_id = ? ORDER BY id DESC LIMIT 1");
$existing_stmt->execute([$user_id, $project_id]);
$existing_review = $existing_stmt->fetch() ?: null;

$session = null;
if ($has_wallet) {
    $session_stmt = $db->prepare("
        SELECT id, wallet_address, status, expires_at,
               UNIX_TIMESTAMP(expires_at) AS expires_at_unix,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds
        FROM rex_signer_sessions
        WHERE user_id = ?
          AND app_id = 'coinrex'
          AND wallet_address = ?
          AND status = 'active'
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $session_stmt->execute([$user_id, $wallet_address]);
    $session = $session_stmt->fetch() ?: null;
}

$fresh_check = $has_wallet
    ? reviewEligibilityGetFreshCheck($db, $user_id, $project_id, $wallet_address, null)
    : null;
$monitoring = $has_wallet
    ? reviewEligibilityMonitoringGetLatest($db, $user_id, $project_id, $wallet_address)
    : null;
$monitoring_payload = $monitoring ? reviewEligibilityMonitoringPayload($monitoring) : null;
if ($monitoring_payload && (string) ($monitoring_payload['status'] ?? '') === 'eligible'
    && !empty($monitoring_payload['expires_at'])
    && strtotime((string) $monitoring_payload['expires_at']) <= time()) {
    $monitoring_payload = null;
}

$decision = coinrexReviewPreflightDecision($has_wallet, $existing_review, $fresh_check, $monitoring_payload, $session);

$state = (string) $decision['state'];
$next_action = (string) $decision['next_action'];
$reason = (string) $decision['reason'];

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

apiSuccessResponse([
    'state' => $state,
    'next_action' => $next_action,
    'reason' => $reason,
    'project' => [
        'id' => (int) $project['id'],
        'name' => (string) $project['name'],
    ],
    'wallet' => [
        'address' => $has_wallet ? $wallet_address : '',
        'linked' => $has_wallet,
    ],
    'session' => $session ? [
        'id' => (int) $session['id'],
        'session_id' => (int) $session['id'],
        'status' => 'active',
        'remaining_seconds' => max(0, (int) $session['remaining_seconds']),
        'expires_at' => (string) $session['expires_at'],
        'expires_at_unix' => (int) $session['expires_at_unix'],
    ] : null,
    'eligibility' => $fresh_check ? [
        'id' => (int) ($fresh_check['id'] ?? 0),
        'status' => (string) ($fresh_check['status'] ?? ''),
        'reason' => (string) ($fresh_check['reason'] ?? ''),
        'checked_at' => (string) ($fresh_check['checked_at'] ?? ''),
        'expires_at' => (string) ($fresh_check['expires_at'] ?? ''),
    ] : null,
    'monitoring' => $monitoring_payload,
    'existing_review' => $existing_review ? [
        'id' => (int) $existing_review['id'],
        'status' => (string) ($existing_review['status'] ?? 'pending'),
        'proof_status' => (string) ($existing_review['proof_status'] ?? 'pending'),
    ] : null,
]);