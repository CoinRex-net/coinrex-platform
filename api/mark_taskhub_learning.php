<?php
/**
 * TaskHub Learning — Mark Learning Opened (Legacy + New Session Support)
 * 
 * This endpoint now supports two modes:
 * 1. Legacy mode: Just marks learning_opened = true in metadata (for backward compatibility)
 * 2. Session mode: Creates a new learning session with server-side tracking
 * 
 * POST /api/mark_taskhub_learning.php
 * Body: task_key, [session_token (optional)]
 */
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(null);
    $task_key = trim((string) ($_POST['task_key'] ?? ''));
    $session_token = trim((string) ($_POST['session_token'] ?? ''));

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
    if (!$user) {
        throw new RuntimeException('User not found.');
    }

    // In TESTING_MODE, skip the current_day check to allow testing any day's tasks
    if (!defined('TESTING_MODE') || TESTING_MODE !== true) {
        if ((int) ($task_row['mission_day'] ?? 0) !== (int) ($user['current_day'] ?? 1)) {
            throw new RuntimeException('Task is not currently available.');
        }
    }

    $log_row = getTaskHubLatestLog((int) $user_id, (int) $task_row['id'], (int) ($task_row['mission_day'] ?? 0), $db);
    if (!$log_row) {
        throw new RuntimeException('Task is still locked.');
    }

    $metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];
    $metadata['learning_opened'] = true;
    $metadata['learning_opened_at'] = date('Y-m-d H:i:s');

    // If a session token was provided, store it
    if ($session_token !== '') {
        $metadata['session_token'] = $session_token;
    }

    taskHubUpdateLog((int) $log_row['id'], ['metadata' => $metadata], $db);

    apiSuccessResponse([
        'message' => 'Learning step validated.',
        'learning_opened' => true,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
