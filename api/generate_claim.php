<?php
/**
 * Lock available rewards and create a claim snapshot.
 * POST: user_id (optional for logged-in user)
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $requested_user_id = isset($_POST['user_id']) ? apiGetRequestedUserId('user_id') : null;
    [$user_id] = apiResolveAuthorizedUserId($requested_user_id);

    $eligibility = getClaimEligibility($user_id);
    $snapshot = generateClaimSnapshotForUser($user_id);

    apiSuccessResponse([
        'message' => 'Claim snapshot generated successfully.',
        'snapshot_id' => $snapshot['snapshot_id'],
        'user_id' => $snapshot['user_id'],
        'amount' => $snapshot['amount'],
        'nonce' => $snapshot['nonce'],
        'status' => $snapshot['status'],
    ]);
} catch (Throwable $e) {
    $status_code = stripos($e->getMessage(), 'already prepared') !== false ? 409 : 422;
    apiErrorResponse($status_code, $e->getMessage());
}
