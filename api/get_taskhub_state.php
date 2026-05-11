<?php
/**
 * Return TaskHub mission state for the authenticated user.
 */

require_once __DIR__ . '/_bootstrap.php';

try {
    [$user_id] = apiResolveAuthorizedUserId(isset($_GET['user_id']) ? apiGetRequestedUserId('user_id') : null);
    $state = getTaskHubState($user_id);

    apiSuccessResponse([
        'user_id' => $user_id,
        'state' => $state,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
