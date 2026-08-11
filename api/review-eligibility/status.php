<?php
require_once dirname(__DIR__) . '/_bootstrap.php';

apiRequireMethod('GET');

$actor = apiGetAuthenticatedUser();
if ($actor['type'] !== 'user' || empty($actor['user_id'])) {
    apiErrorResponse(403, 'User authentication required.');
}

$project_id = (int) ($_GET['project_id'] ?? 0);
$wallet_address = strtolower(trim((string) ($_GET['wallet_address'] ?? '')));
if ($project_id <= 0 || !preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
    apiErrorResponse(422, 'A valid project and wallet are required.');
}

$db = getDBConnection();
$session = reviewEligibilityMonitoringGetLatest($db, (int) $actor['user_id'], $project_id, $wallet_address);
if (!$session) {
    apiSuccessResponse([
        'status' => 'not_started',
        'reason_code' => 'not_started',
        'reason' => 'Receive the project token, then press Start Verification.',
        'remaining_seconds' => 0,
    ]);
}

if ((string) $session['status'] === 'eligible' && !empty($session['expires_at']) && strtotime((string) $session['expires_at']) <= time()) {
    $db->prepare('UPDATE review_eligibility_monitoring_sessions SET status=\'expired\', reason_code=\'eligibility_expired\', reason=\'Your eligibility window expired. Start a new holding verification.\', updated_at=NOW() WHERE id=? AND status=\'eligible\'')
        ->execute([(int) $session['id']]);
    $session = reviewEligibilityMonitoringGetLatest($db, (int) $actor['user_id'], $project_id, $wallet_address);
}

if (in_array((string) $session['status'], ['active', 'provider_delayed'], true)
    && (!empty($session['next_check_at']) && strtotime((string) $session['next_check_at']) <= time())) {
    $session = reviewEligibilityMonitoringProcess($db, (int) $session['id']) ?: $session;
}

apiSuccessResponse(reviewEligibilityMonitoringPayload($session));
