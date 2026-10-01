<?php
define('COINREX_SKIP_REWARD_SCHEMA_INIT', true);
define('COINREX_SKIP_REX_SIGNER_SCHEMA_INIT', true);
require_once __DIR__ . '/_bootstrap.php';

try {
    $db = getDBConnection();
    $actor = rexSignerRequireUserActor($db, [
        'skip_schema' => true,
        'skip_maintenance' => true,
        'skip_user_sync' => true,
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
