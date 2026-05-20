<?php
/**
 * TaskHub Learning Session — Start
 * Simplified: Creates a session with minimal required time (timer logic removed).
 * POST /api/learning/start_session.php
 * Body: task_key (string)
 * Returns: session_token, required_seconds
 */
require_once __DIR__ . '/../_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(null);
    $task_key = trim((string) ($_POST['task_key'] ?? ''));

    if ($task_key === '') {
        throw new InvalidArgumentException('Valid task_key is required.');
    }

    $db = getDBConnection();

    // Find the task
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

    // In TESTING_MODE, skip the current_day check
    if (!defined('TESTING_MODE') || TESTING_MODE !== true) {
        if ((int) ($task_row['mission_day'] ?? 0) !== (int) ($user['current_day'] ?? 1)) {
            throw new RuntimeException('Task is not currently available.');
        }
    }

    // Get the task definition for learning URL
    $definition = getTaskHubMissionTaskDefinitionByKey($task_key);
    $learning_url = $definition['learning_url'] ?? '';

    // Minimal required time (1 second - effectively instant)
    $required_seconds = 1;

    // Create the learning session
    $session_token = taskHubCreateLearningSession(
        (int) $user_id,
        (int) $task_row['id'],
        $task_key,
        $required_seconds,
        $db
    );

    apiSuccessResponse([
        'session_token' => $session_token,
        'required_seconds' => $required_seconds,
        'learning_url' => $learning_url,
        'message' => 'Learning session started.',
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
