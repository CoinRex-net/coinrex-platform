<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(null);
    $task_key = trim((string) ($_POST['task_key'] ?? ''));

    if ($task_key === '') {
        throw new InvalidArgumentException('Valid task_key is required.');
    }

    $db = getDBConnection();
    $task_stmt = $db->prepare("SELECT * FROM mini_tasks WHERE task_key = ? AND task_group = 'mission' AND is_active = 1 LIMIT 1");
    $task_stmt->execute([$task_key]);
    $task_row = $task_stmt->fetch();

    if (!$task_row) {
        throw new RuntimeException('TaskHub task not found.');
    }

    $user = getUserById((int) $user_id);
    if (!$user || (int) ($task_row['mission_day'] ?? 0) !== (int) ($user['current_day'] ?? 1)) {
        throw new RuntimeException('Task is not currently available.');
    }

    $log_row = getTaskHubLatestLog((int) $user_id, (int) $task_row['id'], (int) ($task_row['mission_day'] ?? 0), $db);
    if (!$log_row) {
        throw new RuntimeException('Task is still locked.');
    }

    $metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];
    $metadata['learning_opened'] = true;
    $metadata['learning_opened_at'] = date('Y-m-d H:i:s');

    taskHubUpdateLog((int) $log_row['id'], ['metadata' => $metadata], $db);

    apiSuccessResponse([
        'message' => 'Learning step validated.',
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
