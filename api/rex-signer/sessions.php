<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $db = getDBConnection();
    rexSignerExpireOldRows($db);
    $server_time_stmt = $db->query("SELECT UNIX_TIMESTAMP(NOW()) AS server_time_unix");
    $server_time_unix = (int) ($server_time_stmt->fetch()['server_time_unix'] ?? time());

    $token = rexSignerGetBearerToken();
    $token_session = rexSignerGetAnySessionByToken($db, $token);
    $actor = null;
    $session_state = 'none';

    if ($token_session) {
        $actor = [
            'type' => 'signer_session',
            'user_id' => (int) $token_session['user_id'],
            'session_id' => (int) $token_session['id'],
            'session' => $token_session,
        ];
        $session_state = (string) ($token_session['status'] ?? 'none');
        if ($session_state === 'active' && isset($token_session['remaining_seconds']) && (int) $token_session['remaining_seconds'] <= 0) {
            $session_state = 'expired';
        }
    } else {
        $actor = rexSignerGetActor($db);
        if (!empty($actor['session'])) {
            $session_state = (string) ($actor['session']['status'] ?? 'active');
        }
    }

    if (empty($actor['user_id'])) {
        apiSuccessResponse([
            'active_session_count' => 0,
            'session_state' => $session_state,
            'server_time_unix' => $server_time_unix,
            'current_session' => null,
            'sessions' => [],
        ]);
    }

    $stmt = $db->prepare("
        SELECT *,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds,
               UNIX_TIMESTAMP(expires_at) AS expires_at_unix
        FROM rex_signer_sessions
        WHERE user_id = ?
        ORDER BY FIELD(status, 'active', 'expired', 'revoked'), created_at DESC
        LIMIT 25
    ");
    $stmt->execute([(int) $actor['user_id']]);
    $session_rows = $stmt->fetchAll();
    $sessions = array_map('rexSignerSessionPayload', $session_rows);
    $preferred_session_id = (int) ($_SESSION['rex_signer_login_session_id'] ?? ($actor['session_id'] ?? 0));
    $current_session = null;

    foreach ($sessions as $session_payload) {
        $is_active = (string) ($session_payload['status'] ?? '') === 'active'
            && (int) ($session_payload['remaining_seconds'] ?? 0) > 0;
        if (!$is_active) {
            continue;
        }
        if ($preferred_session_id > 0 && (int) ($session_payload['id'] ?? 0) === $preferred_session_id) {
            $current_session = $session_payload;
            break;
        }
        if ($current_session === null) {
            $current_session = $session_payload;
        }
    }

    $count_stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM rex_signer_sessions
        WHERE user_id = ?
          AND status = 'active'
          AND expires_at > NOW()
    ");
    $count_stmt->execute([(int) $actor['user_id']]);

    apiSuccessResponse([
        'active_session_count' => (int) ($count_stmt->fetch()['total'] ?? 0),
        'session_state' => $session_state,
        'server_time_unix' => $server_time_unix,
        'current_session' => $current_session,
        'sessions' => $sessions,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
