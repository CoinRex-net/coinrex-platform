<?php
/** Select a visible Partner Campaign task through the existing BoostHub assignment flow. */
require_once __DIR__ . '/_bootstrap.php';
apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(isset($_POST['user_id']) ? apiGetRequestedUserId('user_id') : null);
    $task_id = (int) ($_POST['task_id'] ?? 0);
    if ($task_id <= 0) { throw new InvalidArgumentException('Please choose a valid campaign task.'); }
    $result = boostHubStartCampaignTask((int) $user_id, $task_id, getDBConnection());
    apiSuccessResponse([
        'message' => !empty($result['already_assigned']) ? 'This campaign task is already ready.' : 'Campaign task selected. You can complete it now.',
        'result' => $result,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
