<?php
/**
 * Add a reward ledger entry.
 * POST: user_id, amount, source
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');
apiRequireRewardIssuer();

try {
    $user_id = apiGetRequestedUserId('user_id');
    $amount = apiSanitizeDecimal($_POST['amount'] ?? '');
    $source = apiSanitizeLedgerSource($_POST['source'] ?? '');
    $action_type = normalizeLedgerText($_POST['action_type'] ?? 'credit', 50);
    $reference_id = normalizeLedgerText($_POST['reference_id'] ?? '', 100);
    $user = getUserById($user_id);

    if (!$user) {
        throw new InvalidArgumentException('User account not found.');
    }

    $entry = addRewardLedgerEntry(
        $user_id,
        $amount,
        $source,
        $action_type !== '' ? $action_type : 'credit',
        'available',
        $reference_id !== '' ? $reference_id : null,
        null,
        null,
        $user['level'] ?? 'beginner'
    );

    apiSuccessResponse([
        'message' => 'Reward added successfully.',
        'entry' => $entry,
    ], 201);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
