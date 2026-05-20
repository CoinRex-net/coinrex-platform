<?php
/**
 * TaskHub Learning Session — Validate
 * Simplified: Always validates successfully (timer logic removed).
 * POST /api/learning/validate_session.php
 * Body: session_token, task_key
 * Returns: valid (bool), message
 */
require_once __DIR__ . '/../_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(null);
    $session_token = trim((string) ($_POST['session_token'] ?? ''));
    $task_key = trim((string) ($_POST['task_key'] ?? ''));

    if ($session_token === '' || $task_key === '') {
        throw new InvalidArgumentException('Valid session_token and task_key are required.');
    }

    $db = getDBConnection();

    // Update the task log metadata to mark learning as opened
    $task_stmt = $db->prepare("SELECT * FROM mini_tasks WHERE task_key = ? AND task_group = 'mission' AND is_active = 1 LIMIT 1");
    $task_stmt->execute([$task_key]);
    $task_row = $task_stmt->fetch();

    if ($task_row) {
        $log_row = getTaskHubLatestLog((int) $user_id, (int) $task_row['id'], (int) ($task_row['mission_day'] ?? 0), $db);
        if ($log_row) {
            $metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];
            $metadata['learning_opened'] = true;
            $metadata['learning_validated_at'] = date('Y-m-d H:i:s');
            $metadata['session_token'] = $session_token;
            taskHubUpdateLog((int) $log_row['id'], ['metadata' => $metadata], $db);
        }
    }

    apiSuccessResponse([
        'valid' => true,
        'message' => 'Learning validated successfully! You can now proceed to the quiz.',
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
