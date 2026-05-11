<?php
/**
 * Complete one mini task for the authenticated user.
 * POST: task_id
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(isset($_POST['user_id']) ? apiGetRequestedUserId('user_id') : null);
    $task_id = (int) ($_POST['task_id'] ?? 0);

    if ($task_id <= 0) {
        throw new InvalidArgumentException('Valid task_id is required.');
    }

    $result = completeMiniTask($user_id, $task_id, [
        'proof' => trim((string) ($_POST['proof'] ?? '')),
    ]);

    apiSuccessResponse([
        'message' => !empty($result['submitted']) ? 'Evidence submitted. Waiting for admin approval.' : 'Mini task completed and reward added.',
        'entry' => $result['entry'] ?? null,
        'submitted' => !empty($result['submitted']),
        'balance' => number_format(getRewardLedgerBalance($user_id, 'available'), 8, '.', ''),
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
