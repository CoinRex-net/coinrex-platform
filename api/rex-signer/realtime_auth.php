<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $db = getDBConnection();
    $actor = rexSignerRequireUserActor($db, [
        'skip_schema' => true,
        'skip_maintenance' => true,
    ]);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $token = coinrexRealtimeClientToken($actor);

    apiSuccessResponse([
        'ws_url' => coinrexRealtimeWsUrl(),
        'token' => $token,
        'expires_in_seconds' => 900,
        'heartbeat_seconds' => 25,
        'fallback_poll_seconds' => 1,
        'slow_poll_seconds' => 12,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
