<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/auth/_bootstrap.php';

apiRequireMethod('POST');

try {
    $db = getDBConnection();
    rexSignerExpireOldRows($db);

    $code = rexSignerNormalizePairCode(rexSignerInput('code', ''));
    if (!preg_match('/^\d{6}$/', $code)) {
        apiErrorResponse(422, 'Enter the 6-digit pairing code from CoinRex.');
    }

    $device_name = trim((string) rexSignerInput('device_name', 'RexLink'));
    $device_name = $device_name !== '' ? substr($device_name, 0, 120) : 'RexLink';
    $wallet_address = trim((string) rexSignerInput('wallet_address', ''));
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
        apiErrorResponse(422, 'A valid RexLink wallet address is required.');
    }
    $wallet_address = strtolower($wallet_address);

    $db->beginTransaction();

    $stmt = $db->prepare("
        SELECT *
        FROM rex_signer_pairing_codes
        WHERE code_hash = ?
          AND status = 'pending'
          AND expires_at > NOW()
        LIMIT 1
        FOR UPDATE
    ");
    $stmt->execute([rexSignerHashSecret($code)]);
    $pairing = $stmt->fetch();

    if (!$pairing) {
        $db->rollBack();
        apiErrorResponse(404, 'Pairing code is invalid or expired.');
    }

    $pairing_purpose = strtolower((string) ($pairing['pairing_purpose'] ?? 'claim'));
    $pairing_user_id = !empty($pairing['user_id']) ? (int) $pairing['user_id'] : null;
    if ($pairing_purpose === 'auth' && $pairing_user_id === null) {
        [$auth_user] = rexSignerAuthFindOrCreateUser(
            $db,
            $wallet_address,
            (string) ($pairing['device_fingerprint'] ?? ''),
            (string) ($pairing['referral_code'] ?? '')
        );
        $login_error = rexSignerAuthUserCanLogin($auth_user);
        if ($login_error !== '') {
            $db->rollBack();
            apiErrorResponse(403, $login_error);
        }
        $pairing_user_id = (int) $auth_user['id'];
    }

    if ($pairing_user_id === null || $pairing_user_id <= 0) {
        $db->rollBack();
        apiErrorResponse(422, 'Pairing owner could not be resolved.');
    }

    $wallet_owner = $db->prepare("SELECT id FROM users WHERE wallet_address = ? AND id <> ? LIMIT 1");
    $wallet_owner->execute([$wallet_address, $pairing_user_id]);
    if ($wallet_owner->fetch()) {
        $db->rollBack();
        apiErrorResponse(409, 'This wallet is already linked to another CoinRex account.');
    }

    $wallet_update = $db->prepare("
        UPDATE users
        SET wallet_address = ?,
            wallet_verified_at = COALESCE(wallet_verified_at, NOW()),
            auth_provider = CASE
                WHEN auth_provider = 'email' THEN 'hybrid'
                ELSE auth_provider
            END,
            updated_at = NOW()
        WHERE id = ?
    ");
    $wallet_update->execute([$wallet_address, $pairing_user_id]);

    $duration = rexSignerClampDuration($pairing['requested_duration_minutes'] ?? 10);
    $session_token = rexSignerRandomToken(32);

    $existing_session_stmt = $db->prepare("
        SELECT id
        FROM rex_signer_sessions
        WHERE user_id = ?
          AND status = 'active'
    ");
    $existing_session_stmt->execute([$pairing_user_id]);
    $replaced_session_ids = array_map(static function ($row) {
        return (int) ($row['id'] ?? 0);
    }, $existing_session_stmt->fetchAll());

    $revoke_existing = $db->prepare("
        UPDATE rex_signer_sessions
        SET status = 'revoked',
            revoked_at = NOW(),
            revoke_reason = 'Replaced by a new RexLink session'
        WHERE user_id = ?
          AND status = 'active'
    ");
    $revoke_existing->execute([$pairing_user_id]);

    $insert = $db->prepare("
        INSERT INTO rex_signer_sessions
            (user_id, pairing_code_id, session_token_hash, device_name, wallet_address, expires_at, last_seen_at, ip_address, user_agent)
        VALUES
            (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL {$duration} MINUTE), NOW(), ?, ?)
    ");
    $insert->execute([
        $pairing_user_id,
        (int) $pairing['id'],
        rexSignerHashSecret($session_token),
        $device_name,
        $wallet_address,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    $session_id = (int) $db->lastInsertId();
    $update = $db->prepare("
        UPDATE rex_signer_pairing_codes
        SET status = 'completed',
            completed_at = NOW(),
            completed_session_id = ?
        WHERE id = ?
    ");
    $update->execute([$session_id, (int) $pairing['id']]);

    $session_stmt = $db->prepare("
        SELECT *,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds,
               UNIX_TIMESTAMP(expires_at) AS expires_at_unix
        FROM rex_signer_sessions
        WHERE id = ?
        LIMIT 1
    ");
    $session_stmt->execute([$session_id]);
    $session = $session_stmt->fetch();
    $session_payload = rexSignerSessionPayload($session);

    $db->commit();

    foreach ($replaced_session_ids as $replaced_session_id) {
        if ($replaced_session_id > 0) {
            coinrexRealtimePublish('session.revoked', [
                'user_id' => $pairing_user_id,
                'session_id' => $replaced_session_id,
                'status' => 'revoked',
                'reason' => 'Replaced by a new RexLink session',
            ]);
        }
    }

    coinrexRealtimePublish('session.connected', [
        'user_id' => $pairing_user_id,
        'session_id' => $session_id,
        'status' => 'active',
        'wallet_address' => $wallet_address,
        'session' => $session_payload,
    ]);

    apiSuccessResponse([
        'message' => 'RexLink paired successfully.',
        'session_token' => $session_token,
        'session' => $session_payload,
    ], 201);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    apiErrorResponse(422, $e->getMessage());
}
