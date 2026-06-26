<?php
/**
 * TaskHub Learning Session — Verify
 * 
 * Verifies that a learning session has been completed with sufficient reading time.
 * Called from the learning bridge page after the user clicks "I've Read It".
 * 
 * POST /api/learning/verify_session.php
 * Body: session_token, task_key, elapsed_seconds
 * 
 * GET /api/learning/verify_session.php?beacon=1&session_token=XXX&task_key=YYY
 * (Beacon mode for fallback notification)
 */
require_once __DIR__ . '/../_bootstrap.php';

try {
    // Handle beacon GET requests (from Image beacon)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['beacon'])) {
        $session_token = trim((string) ($_GET['session_token'] ?? ''));
        $task_key = trim((string) ($_GET['task_key'] ?? ''));
        if ($session_token !== '' && $task_key !== '') {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT * FROM taskhub_learning_sessions WHERE session_token = ? AND task_key = ? LIMIT 1");
            $stmt->execute([$session_token, $task_key]);
            $session = $stmt->fetch();
            if ($session && in_array((string) ($session['status'] ?? ''), ['active', 'paused'], true)) {
                $required = max(15, (int) ($session['required_seconds'] ?? 45));
                $active = (int) ($session['active_seconds'] ?? 0);
                if ($active >= $required) {
                    $db->prepare("UPDATE taskhub_learning_sessions SET status = 'completed', completed_at = NOW() WHERE id = ?")
                       ->execute([(int) $session['id']]);
                    // Also update the user_task_logs metadata
                    $task_stmt = $db->prepare("SELECT * FROM mini_tasks WHERE task_key = ? AND task_group = 'mission' AND is_active = 1 LIMIT 1");
                    $task_stmt->execute([$task_key]);
                    $task_row = $task_stmt->fetch();
                    if ($task_row) {
                        $user_id = (int) ($session['user_id'] ?? 0);
                        $log_row = getTaskHubLatestLog($user_id, (int) $task_row['id'], (int) ($task_row['mission_day'] ?? 0), $db);
                        if ($log_row) {
                            $metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];
                            $metadata['learning_opened'] = true;
                            $metadata['learning_validated_at'] = date('Y-m-d H:i:s');
                            $metadata['session_token'] = $session_token;
                            $metadata['active_seconds'] = $active;
                            taskHubUpdateLog((int) $log_row['id'], ['metadata' => $metadata], $db);
                        }
                    }
                }
            }
        }
        // Return 1x1 transparent GIF
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }

    // POST mode — normal verification
    apiRequireMethod('POST');

    [$user_id] = apiResolveAuthorizedUserId(null);
    $session_token = trim((string) ($_POST['session_token'] ?? ''));
    $task_key = trim((string) ($_POST['task_key'] ?? ''));
    $elapsed_seconds = max(0, (int) ($_POST['elapsed_seconds'] ?? 0));

    if ($session_token === '' || $task_key === '') {
        throw new InvalidArgumentException('Valid session_token and task_key are required.');
    }

    $db = getDBConnection();

    // Find the session
    $stmt = $db->prepare("SELECT * FROM taskhub_learning_sessions WHERE session_token = ? AND task_key = ? LIMIT 1");
    $stmt->execute([$session_token, $task_key]);
    $session = $stmt->fetch();

    if (!$session) {
        throw new RuntimeException('Learning session not found.');
    }

    if ((int) ($session['user_id'] ?? 0) !== (int) $user_id) {
        throw new RuntimeException('Session does not belong to this user.');
    }

    if ((string) ($session['status'] ?? '') === 'completed') {
        // Already verified — return success
        apiSuccessResponse([
            'valid' => true,
            'message' => 'Learning already verified.',
            'session' => $session,
        ]);
        exit;
    }

    if (!in_array((string) ($session['status'] ?? ''), ['active', 'paused'], true)) {
        throw new RuntimeException('Session is ' . $session['status'] . ' and cannot be verified.');
    }

    // Check required reading time
    $required_seconds = max(15, (int) ($session['required_seconds'] ?? 45));
    $active_seconds = max((int) ($session['active_seconds'] ?? 0), $elapsed_seconds);

    if ($active_seconds < $required_seconds) {
        throw new RuntimeException(
            'You need to read for at least ' . $required_seconds . ' seconds. ' .
            'You have read for ' . $active_seconds . ' seconds.'
        );
    }

    // Mark session as completed
    $db->prepare("UPDATE taskhub_learning_sessions SET status = 'completed', completed_at = NOW(), active_seconds = ? WHERE id = ?")
       ->execute([$active_seconds, (int) $session['id']]);

    // Update the user_task_logs metadata to mark learning as opened
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
            $metadata['active_seconds'] = $active_seconds;
            taskHubUpdateLog((int) $log_row['id'], ['metadata' => $metadata], $db);
        }
    }

    apiSuccessResponse([
        'valid' => true,
        'message' => 'Learning verified successfully! You can now proceed to the quiz.',
        'session' => [
            'active_seconds' => $active_seconds,
            'required_seconds' => $required_seconds,
            'status' => 'completed',
        ],
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
