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

    // Get learning details from DB, with TaskHub quiz fallbacks.
    $learning_meta = taskHubGetLearningMetaForTask($task_row);
    $learning_url = taskHubNormalizeLearningUrlForCurrentHost((string) ($learning_meta['url'] ?? ''));
    $learning_title = (string) ($learning_meta['title'] ?? 'Learning Material');
    $required_seconds = max(15, (int) ($task_row['required_reading_seconds'] ?? 45));

    // Create the learning session
    $session_token = taskHubCreateLearningSession(
        (int) $user_id,
        (int) $task_row['id'],
        $task_key,
        $required_seconds,
        $db
    );

    // Build the bridge page URL
    // The bridge file is at public/includes/taskhub/learning-bridge.php
    // BASE_URL = http://localhost/coinrex (no /public), so we need /public/includes/...
    $bridge_url = rtrim((defined('BASE_URL') ? BASE_URL : 'http://localhost/coinrex'), '/')
        . '/public/includes/taskhub/learning-bridge.php'
        . '?th_session=' . urlencode($session_token)
        . '&th_task_key=' . urlencode($task_key)
        . '&th_url=' . urlencode($learning_url)
        . '&th_seconds=' . $required_seconds
        . '&th_title=' . urlencode($learning_title);


    apiSuccessResponse([
        'session_token' => $session_token,
        'required_seconds' => $required_seconds,
        'learning_url' => $learning_url,
        'learning_title' => $learning_title,
        'bridge_url' => $bridge_url,
        'message' => 'Learning session started.',
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
