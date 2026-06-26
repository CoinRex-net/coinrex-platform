<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $db = getDBConnection();
    $actor = rexSignerRequireUserActor($db);

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
