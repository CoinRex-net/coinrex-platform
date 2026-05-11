<?php
/**
 * Return the available reward ledger balance for a user.
 * GET: user_id (optional for logged-in user)
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    $requested_user_id = isset($_GET['user_id']) ? apiGetRequestedUserId('user_id') : null;
    [$user_id] = apiResolveAuthorizedUserId($requested_user_id);

    $balance = getRewardLedgerBalance($user_id, 'available');
    $cached_user = getUserById($user_id);

    apiSuccessResponse([
        'user_id' => $user_id,
        'status' => 'available',
        'balance' => number_format($balance, 8, '.', ''),
        'cached_balance' => number_format((float) ($cached_user['rex_balance'] ?? 0), 8, '.', ''),
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
