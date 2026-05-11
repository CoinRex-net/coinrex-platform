<?php
/**
 * Submit a TaskHub task action.
 * POST: task_key, wallet_address?, proof?, answers_json?
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $requested_user_id = null;
    if (isset($_POST['user_id']) && trim((string) $_POST['user_id']) !== '') {
        $requested_user_id = apiGetRequestedUserId('user_id');
    }

    [$user_id] = apiResolveAuthorizedUserId($requested_user_id);
    $task_key = trim((string) ($_POST['task_key'] ?? ''));
    if ($task_key === '') {
        throw new InvalidArgumentException('Valid task_key is required.');
    }

    $payload = [];
    if (isset($_POST['wallet_address'])) {
        $payload['wallet_address'] = trim((string) $_POST['wallet_address']);
    }
    if (isset($_POST['proof'])) {
        $payload['proof'] = trim((string) $_POST['proof']);
    }
    if (isset($_POST['x_handle'])) {
        $payload['x_handle'] = trim((string) $_POST['x_handle']);
    }
    if (isset($_POST['telegram_handle'])) {
        $payload['telegram_handle'] = trim((string) $_POST['telegram_handle']);
    }
    if (isset($_POST['platform'])) {
        $payload['platform'] = trim((string) $_POST['platform']);
    }
    if (!empty($_POST['answers_json'])) {
        $answers = json_decode((string) $_POST['answers_json'], true);
        if (is_array($answers)) {
            $payload['answers'] = $answers;
        }
    }

    $result = submitTaskHubTask($user_id, $task_key, $payload);
    $state = getTaskHubState($user_id);

    apiSuccessResponse([
        'message' => !empty($result['submitted']) ? 'Task submitted for review.' : 'Task completed.',
        'result' => $result,
        'state' => $state,
        'balance' => number_format(getRewardLedgerBalance($user_id, 'available'), 8, '.', ''),
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
