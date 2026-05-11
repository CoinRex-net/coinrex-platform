<?php
/**
 * Return active MiniTaskHub items for the authenticated user.
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    [$user_id] = apiResolveAuthorizedUserId(isset($_GET['user_id']) ? apiGetRequestedUserId('user_id') : null);
    $tasks = getMiniTasksForUser($user_id);
    $level_state = getUserLevelState($user_id);

    apiSuccessResponse([
        'user_id' => $user_id,
        'level' => $level_state['level'],
        'tasks' => $tasks,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
