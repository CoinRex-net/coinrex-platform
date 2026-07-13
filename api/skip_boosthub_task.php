<?php
/**
 * Skip the currently assigned BoostHub task and move to the next unfinished one.
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(isset($_POST['user_id']) ? apiGetRequestedUserId('user_id') : null);
    $task_id = (int) ($_POST['task_id'] ?? 0);

    if ($task_id <= 0) {
        throw new InvalidArgumentException('Valid task_id is required.');
    }

    $result = skipBoostHubTask((int) $user_id, $task_id, getDBConnection());

    apiSuccessResponse([
        'message' => 'Task skipped. The next unfinished BoostHub task has been assigned.',
        'result' => $result,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
