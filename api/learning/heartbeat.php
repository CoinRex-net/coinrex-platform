<?php
/**
 * TaskHub Learning Session — Heartbeat
 * Simplified: Always succeeds (timer logic removed).
 * POST /api/learning/heartbeat.php
 * Body: session_token, active_seconds, is_visible, is_focused, scroll_depth
 * Returns: session status, server_active_seconds, required_seconds
 */
require_once __DIR__ . '/../_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(null);
    $session_token = trim((string) ($_POST['session_token'] ?? ''));
    $reported_active_seconds = (int) ($_POST['active_seconds'] ?? 0);
    $is_visible = !empty($_POST['is_visible']) && $_POST['is_visible'] !== 'false' && $_POST['is_visible'] !== '0';
    $is_focused = !empty($_POST['is_focused']) && $_POST['is_focused'] !== 'false' && $_POST['is_focused'] !== '0';
    $scroll_depth = min(100, max(0, (int) ($_POST['scroll_depth'] ?? 0)));

    if ($session_token === '') {
        throw new InvalidArgumentException('Valid session_token is required.');
    }

    $db = getDBConnection();

    // Process heartbeat
    $session = taskHubProcessHeartbeat(
        $session_token,
        $reported_active_seconds,
        $is_visible,
        $is_focused,
        $scroll_depth,
        $db
    );

    if ($session === null) {
        apiErrorResponse(422, 'Learning session expired or invalid. Please start again.');
        exit;
    }

    $server_active_seconds = (int) ($session['active_seconds'] ?? 0);
    $required_seconds = (int) ($session['required_seconds'] ?? 1);
    $status = (string) ($session['status'] ?? 'active');
    $interruption_count = (int) ($session['interruption_count'] ?? 0);
    $max_scroll_depth = (int) ($session['max_scroll_depth'] ?? 0);

    $is_complete = $server_active_seconds >= $required_seconds;

    apiSuccessResponse([
        'status' => $status,
        'server_active_seconds' => $server_active_seconds,
        'required_seconds' => $required_seconds,
        'interruption_count' => $interruption_count,
        'max_scroll_depth' => $max_scroll_depth,
        'is_complete' => $is_complete,
        'remaining_seconds' => max(0, $required_seconds - $server_active_seconds),
        'message' => $is_complete
            ? 'Learning complete! You can now proceed to the quiz.'
            : 'Keep reading... ' . max(0, $required_seconds - $server_active_seconds) . ' more seconds needed.',
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
