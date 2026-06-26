<?php
/**
 * TaskHub Learning Session — Heartbeat
 * 
 * Receives periodic heartbeats from the learning bridge page to track:
 * - Active reading time (seconds)
 * - Tab visibility (is the user looking at the tab?)
 * - Tab focus (is the tab focused?)
 * - Scroll depth (how far did the user scroll?)
 * 
 * POST /api/learning/heartbeat_session.php
 * Body: session_token, task_key, active_seconds, is_visible, is_focused, scroll_depth
 */
require_once __DIR__ . '/../_bootstrap.php';

try {
    apiRequireMethod('POST');

    [$user_id] = apiResolveAuthorizedUserId(null);
    $session_token = trim((string) ($_POST['session_token'] ?? ''));
    $task_key = trim((string) ($_POST['task_key'] ?? ''));
    $active_seconds = max(0, (int) ($_POST['active_seconds'] ?? 0));
    $is_visible = !empty($_POST['is_visible']);
    $is_focused = !empty($_POST['is_focused']);
    $scroll_depth = max(0, min(100, (int) ($_POST['scroll_depth'] ?? 0)));

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
        // Session already completed — return success with current state
        apiSuccessResponse([
            'session' => [
                'active_seconds' => (int) ($session['active_seconds'] ?? 0),
                'required_seconds' => (int) ($session['required_seconds'] ?? 45),
                'status' => 'completed',
            ],
        ]);
        exit;
    }

    if (!in_array((string) ($session['status'] ?? ''), ['active', 'paused'], true)) {
        throw new RuntimeException('Session is ' . $session['status'] . ' and cannot receive heartbeats.');
    }

    // Determine new status based on visibility
    $new_status = (string) ($session['status'] ?? 'active');
    if (!$is_visible || !$is_focused) {
        $new_status = 'paused';
    } else {
        $new_status = 'active';
    }

    // Update the session
    $current_active = (int) ($session['active_seconds'] ?? 0);
    $new_active = max($current_active, $active_seconds);
    $current_scroll = (int) ($session['max_scroll_depth'] ?? 0);
    $new_scroll = max($current_scroll, $scroll_depth);
    $current_interruptions = (int) ($session['interruption_count'] ?? 0);

    // If the session was active but now paused due to visibility, increment interruption count
    $new_interruptions = $current_interruptions;
    if ((string) ($session['status'] ?? '') === 'active' && $new_status === 'paused') {
        $new_interruptions++;
    }

    $db->prepare("UPDATE taskhub_learning_sessions 
                   SET active_seconds = ?, 
                       max_scroll_depth = ?, 
                       interruption_count = ?,
                       status = ?,
                       last_heartbeat = NOW()
                   WHERE id = ?")
       ->execute([$new_active, $new_scroll, $new_interruptions, $new_status, (int) $session['id']]);

    apiSuccessResponse([
        'session' => [
            'active_seconds' => $new_active,
            'required_seconds' => (int) ($session['required_seconds'] ?? 45),
            'status' => $new_status,
            'interruption_count' => $new_interruptions,
            'max_scroll_depth' => $new_scroll,
        ],
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
