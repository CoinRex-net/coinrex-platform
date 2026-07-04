<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $db = getDBConnection();
    if (!featureIsAccessible('rexlink_auth')) {
        apiErrorResponse(403, 'RexLink sign-in is coming soon. Please use email login for now.');
    }
    rexSignerExpireOldRows($db);

    $pairing_id = (int) ($_SESSION['rex_signer_auth_pairing_id'] ?? 0);
    if ($pairing_id <= 0) {
        apiSuccessResponse([
            'status' => 'none',
            'message' => 'No RexLink sign-in pairing is active.',
        ]);
    }

    $stmt = $db->prepare("
        SELECT pc.*,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), pc.expires_at)) AS pairing_remaining_seconds,
               s.id AS session_id,
               s.user_id AS session_user_id,
               s.wallet_address AS session_wallet_address,
               s.status AS session_status,
               s.expires_at AS session_expires_at,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), s.expires_at)) AS session_remaining_seconds
        FROM rex_signer_pairing_codes pc
        LEFT JOIN rex_signer_sessions s ON s.id = pc.completed_session_id
        WHERE pc.id = ?
          AND pc.pairing_purpose = 'auth'
        LIMIT 1
    ");
    $stmt->execute([$pairing_id]);
    $pairing = $stmt->fetch();
    if (!$pairing) {
        unset($_SESSION['rex_signer_auth_pairing_id']);
        apiSuccessResponse([
            'status' => 'none',
            'message' => 'RexLink sign-in pairing was not found.',
        ]);
    }

    if ((string) ($pairing['status'] ?? '') === 'pending') {
        $remaining_seconds = (int) ($pairing['pairing_remaining_seconds'] ?? 0);
        apiSuccessResponse([
            'status' => $remaining_seconds <= 0 ? 'expired' : 'pending',
            'message' => 'Waiting for RexLink.',
            'expires_in_seconds' => $remaining_seconds,
        ]);
    }

    if ((string) ($pairing['status'] ?? '') === 'revoked') {
        unset($_SESSION['rex_signer_auth_pairing_id']);
        apiSuccessResponse([
            'status' => 'revoked',
            'message' => 'RexLink authentication was cancelled. Please create a new code and try again.',
        ]);
    }

    if ((string) ($pairing['status'] ?? '') !== 'completed' || empty($pairing['session_id'])) {
        unset($_SESSION['rex_signer_auth_pairing_id']);
        apiSuccessResponse([
            'status' => (string) ($pairing['status'] ?? 'expired'),
            'message' => 'RexLink sign-in pairing is no longer active.',
        ]);
    }

    if ((string) ($pairing['session_status'] ?? '') !== 'active' || (int) ($pairing['session_remaining_seconds'] ?? 0) <= 0) {
        unset($_SESSION['rex_signer_auth_pairing_id']);
        apiSuccessResponse([
            'status' => 'expired',
            'message' => 'RexLink session expired. Please try again.',
        ]);
    }

    $user_id = (int) ($pairing['session_user_id'] ?? 0);
    $user = $user_id > 0 ? getUserById($user_id) : null;
    if (!$user) {
        unset($_SESSION['rex_signer_auth_pairing_id']);
        apiErrorResponse(404, 'RexLink account could not be found.');
    }

    $login_error = rexSignerAuthUserCanLogin($user);
    if ($login_error !== '') {
        unset($_SESSION['rex_signer_auth_pairing_id']);
        apiErrorResponse(403, $login_error);
    }

    logUserSecuritySignal($user_id, 'login', [
        'raw_ip' => resolveClientIpAddress(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'channel' => 'rex_signer_session_login',
        'wallet_address' => $pairing['session_wallet_address'] ?? $user['wallet_address'] ?? null,
        'rex_signer_session_id' => (int) $pairing['session_id'],
    ], $db);

    establishAuthenticatedSession($user, false);
    $_SESSION['rex_signer_login_session_id'] = (int) $pairing['session_id'];
    $_SESSION['rex_signer_login_wallet_address'] = (string) ($pairing['session_wallet_address'] ?? '');
    unset($_SESSION['rex_signer_auth_pairing_id']);

    apiSuccessResponse([
        'status' => 'authenticated',
        'message' => 'Signed in with RexLink.',
        'wallet_address' => (string) ($pairing['session_wallet_address'] ?? $user['wallet_address'] ?? ''),
        'session_id' => (int) $pairing['session_id'],
        'session_expires_at' => (string) ($pairing['session_expires_at'] ?? ''),
        'session_remaining_seconds' => max(0, (int) ($pairing['session_remaining_seconds'] ?? 0)),
        'requested_duration_minutes' => (int) ($pairing['requested_duration_minutes'] ?? 10),
        'redirect_url' => BASE_URL . '/public/dashboard.php',
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
