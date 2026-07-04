<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $db = getDBConnection();
    rexSignerExpireOldRows($db);

    $token = rexSignerGetBearerToken();
    $token_session = rexSignerGetAnySessionByToken($db, $token);
    $actor = null;

    if ($token_session) {
        $actor = [
            'type' => 'signer_session',
            'user_id' => (int) $token_session['user_id'],
            'session_id' => (int) $token_session['id'],
            'session' => $token_session,
        ];
    } else {
        $actor = apiGetAuthenticatedUser();
        if ($actor['type'] !== 'user' || empty($actor['user_id'])) {
            apiErrorResponse(401, 'Authentication required.');
        }
    }

    $session_id = (int) rexSignerInput('session_id', $actor['session_id'] ?? 0);
    if ($session_id <= 0) {
        apiErrorResponse(422, 'Valid session_id is required.');
    }

    $server_time_stmt = $db->query("SELECT UNIX_TIMESTAMP(NOW()) AS server_time_unix");
    $server_time_unix = (int) ($server_time_stmt->fetch()['server_time_unix'] ?? time());

    $session_stmt = $db->prepare("
        SELECT *,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds,
               UNIX_TIMESTAMP(expires_at) AS expires_at_unix
        FROM rex_signer_sessions
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
    ");
    $session_stmt->execute([$session_id, (int) $actor['user_id']]);
    $session = $session_stmt->fetch();
    if (!$session) {
        apiSuccessResponse([
            'message' => 'Session already ended.',
            'session_id' => $session_id,
            'session_state' => 'none',
            'revoked' => false,
            'server_time_unix' => $server_time_unix,
        ]);
    }

    $session_state = (string) ($session['status'] ?? 'none');
    if ($session_state === 'active' && (int) ($session['remaining_seconds'] ?? 0) <= 0) {
        $session_state = 'expired';
    }

    $revoked = false;
    $cancelled_requests = 0;
    if ($session_state === 'active') {
        $stmt = $db->prepare("
            UPDATE rex_signer_sessions
            SET status = 'revoked',
                revoked_at = NOW(),
                revoke_reason = ?
            WHERE id = ?
              AND user_id = ?
              AND status = 'active'
        ");
        $stmt->execute([
            trim((string) rexSignerInput('reason', 'Revoked by user')),
            $session_id,
            (int) $actor['user_id'],
        ]);
        $revoked = $stmt->rowCount() > 0;
        $session_state = $revoked ? 'revoked' : $session_state;
    }
    if (in_array($session_state, ['revoked', 'expired'], true)) {
        $cancelled_requests = rexSignerCancelPendingApprovalsForEndedSessions(
            $db,
            $session_id,
            'RexLink session ended before approval.'
        );
        $released_claims = rexSignerReleaseApprovedClaimApprovalsForEndedSessions(
            $db,
            $session_id,
            'RexLink session ended before claim submission.'
        );
        $cancelled_requests += $released_claims;
        coinrexRealtimePublish($session_state === 'revoked' ? 'session.revoked' : 'session.expired', [
            'user_id' => (int) $actor['user_id'],
            'session_id' => $session_id,
            'status' => $session_state,
        ]);
        if ($cancelled_requests > 0) {
            coinrexRealtimePublish('approval.cancelled', [
                'user_id' => (int) $actor['user_id'],
                'session_id' => $session_id,
                'status' => 'cancelled',
                'count' => $cancelled_requests,
            ]);
        }
    }

    apiSuccessResponse([
        'message' => $revoked ? 'Session revoked.' : 'Session already ended.',
        'session_id' => $session_id,
        'session_state' => $session_state,
        'revoked' => $revoked,
        'cancelled_pending_requests' => $cancelled_requests,
        'server_time_unix' => $server_time_unix,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
