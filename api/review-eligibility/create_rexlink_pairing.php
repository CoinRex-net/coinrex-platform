<?php
require_once __DIR__ . '/_fast_bootstrap.php';

coinrexFastRequirePost();

try {
    $started = microtime(true);
    $user_id = coinrexFastUserId();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $db = coinrexFastDb();
    $duration = max(5, min((int) coinrexFastInput('duration_minutes', 10), 60));
    $qr_ttl_minutes = 5;
    $qr_ttl_seconds = $qr_ttl_minutes * 60;
    $force_new_pairing = filter_var(coinrexFastInput('force_new_pairing', false), FILTER_VALIDATE_BOOLEAN);
    $public_base_url = coinrexFastPublicBaseUrl();
    $api_base_url = coinrexFastRexlinkBaseUrl();
    $dapp_name = substr(trim((string) coinrexFastInput('dapp_name', 'CoinRex Review Eligibility')), 0, 80);
    $dapp_url = substr(trim((string) coinrexFastInput('dapp_url', $public_base_url)), 0, 255);

    if ($force_new_pairing) {
        $db->prepare("
            UPDATE rex_signer_sessions
            SET status = 'revoked',
                revoked_at = NOW(),
                revoke_reason = 'Replaced by fresh review eligibility pairing'
            WHERE user_id = ?
              AND status = 'active'
        ")->execute([$user_id]);

        $db->prepare("
            UPDATE rex_signer_pairing_codes
            SET status = 'expired'
            WHERE user_id = ?
              AND pairing_purpose = 'review_eligibility'
              AND status = 'pending'
        ")->execute([$user_id]);
    } else {
        $active_stmt = $db->prepare("
            SELECT id, wallet_address, expires_at,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds
            FROM rex_signer_sessions
            WHERE user_id = ?
              AND status = 'active'
              AND expires_at > NOW()
            ORDER BY id DESC
            LIMIT 1
        ");
        $active_stmt->execute([$user_id]);
        $active_session = $active_stmt->fetch();
        if ($active_session && preg_match('/^0x[a-f0-9]{40}$/', strtolower((string) ($active_session['wallet_address'] ?? '')))) {
            coinrexFastSuccess([
                'message' => 'Active RexLink session found.',
                'already_connected' => true,
                'server_timing_ms' => (int) round((microtime(true) - $started) * 1000),
                'session' => [
                    'id' => (int) ($active_session['id'] ?? 0),
                    'session_id' => (int) ($active_session['id'] ?? 0),
                    'wallet_address' => strtolower((string) ($active_session['wallet_address'] ?? '')),
                    'remaining_seconds' => max(0, (int) ($active_session['remaining_seconds'] ?? 0)),
                    'expires_at' => (string) ($active_session['expires_at'] ?? ''),
                ],
            ]);
        }

        $pending_stmt = $db->prepare("
            SELECT id, display_code, requested_duration_minutes, expires_at,
                   GREATEST(TIMESTAMPDIFF(SECOND, NOW(), expires_at), 0) AS expires_in_seconds,
                   UNIX_TIMESTAMP(expires_at) AS expires_at_unix
            FROM rex_signer_pairing_codes
            WHERE user_id = ?
              AND pairing_purpose = 'review_eligibility'
              AND status = 'pending'
              AND expires_at > NOW()
            ORDER BY id DESC
            LIMIT 1
        ");
        $pending_stmt->execute([$user_id]);
        $pending = $pending_stmt->fetch();
        if ($pending) {
            if ((int) ($pending['requested_duration_minutes'] ?? 0) !== $duration || (int) ($pending['expires_in_seconds'] ?? 0) > $qr_ttl_seconds) {
                $db->prepare("UPDATE rex_signer_pairing_codes SET status = 'expired' WHERE id = ?")->execute([(int) $pending['id']]);
            } else {
            $display_code = (string) $pending['display_code'];
            $expires_in_seconds = max(1, (int) ($pending['expires_in_seconds'] ?? 300));
            $expires_at_unix = isset($pending['expires_at_unix']) ? (int) $pending['expires_at_unix'] : null;
            coinrexFastSuccess([
                'message' => 'Existing pairing code is still active.',
                'pairing_id' => (int) $pending['id'],
                'display_code' => $display_code,
                'expires_in_seconds' => $expires_in_seconds,
                'requested_duration_minutes' => (int) ($pending['requested_duration_minutes'] ?? $duration),
                'server_timing_ms' => (int) round((microtime(true) - $started) * 1000),
                'qr_payload' => [
                    'type' => 'coinrex.rex_signer.pairing',
                    'version' => 2,
                    'code' => $display_code,
                    'purpose' => 'review_eligibility',
                    'api_base_url' => $api_base_url,
                    'base_url' => $api_base_url,
                    'dapp_name' => $dapp_name !== '' ? $dapp_name : 'CoinRex Review',
                    'dapp_url' => $dapp_url !== '' ? $dapp_url : $public_base_url,
                    'network_slug' => 'polygon',
                    'chain_id' => 137,
                    'requested_duration_minutes' => (int) ($pending['requested_duration_minutes'] ?? $duration),
                    'expires_in_seconds' => $expires_in_seconds,
                    'expires_at_unix' => $expires_at_unix,
                ],
            ]);
            }
        }
    }

    $collision_stmt = $db->prepare("SELECT id FROM rex_signer_pairing_codes WHERE code_hash = ? LIMIT 1");
    $code = '';
    $display_code = '';
    $code_hash = '';
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = coinrexFastPairCode();
        $candidate_hash = coinrexFastHash($candidate);
        $collision_stmt->execute([$candidate_hash]);
        if (!$collision_stmt->fetch()) {
            $code = $candidate;
            $display_code = coinrexFastFormatPairCode($candidate);
            $code_hash = $candidate_hash;
            break;
        }
    }
    if ($code === '') {
        coinrexFastError(503, 'Could not create a pairing code. Please try again.');
    }

    $insert = $db->prepare("
        INSERT INTO rex_signer_pairing_codes
            (user_id, code_hash, display_code, pairing_purpose, requested_duration_minutes, expires_at, ip_address, user_agent)
        VALUES
            (?, ?, ?, 'review_eligibility', ?, DATE_ADD(NOW(), INTERVAL " . (int) $qr_ttl_minutes . " MINUTE), ?, ?)
    ");
    $insert->execute([
        $user_id,
        $code_hash,
        $display_code,
        $duration,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);

    $pairing_id = (int) $db->lastInsertId();
    $created_stmt = $db->prepare("
        SELECT expires_at,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS expires_in_seconds,
               UNIX_TIMESTAMP(expires_at) AS expires_at_unix
        FROM rex_signer_pairing_codes
        WHERE id = ?
        LIMIT 1
    ");
    $created_stmt->execute([$pairing_id]);
    $created = $created_stmt->fetch() ?: [];
    $expires_in_seconds = max(1, (int) ($created['expires_in_seconds'] ?? ($duration * 60)));
    $expires_at_unix = isset($created['expires_at_unix']) ? (int) $created['expires_at_unix'] : null;

    coinrexFastSuccess([
        'message' => 'Review pairing code created.',
        'pairing_id' => $pairing_id,
        'display_code' => $display_code,
        'expires_in_seconds' => $expires_in_seconds,
        'expires_at' => (string) ($created['expires_at'] ?? ''),
        'expires_at_unix' => $expires_at_unix,
        'requested_duration_minutes' => $duration,
        'server_timing_ms' => (int) round((microtime(true) - $started) * 1000),
        'qr_payload' => [
            'type' => 'coinrex.rex_signer.pairing',
            'version' => 2,
            'code' => $display_code,
            'purpose' => 'review_eligibility',
            'api_base_url' => $api_base_url,
            'base_url' => $api_base_url,
            'dapp_name' => $dapp_name !== '' ? $dapp_name : 'CoinRex Review',
            'dapp_url' => $dapp_url !== '' ? $dapp_url : $public_base_url,
            'network_slug' => 'polygon',
            'chain_id' => 137,
            'requested_duration_minutes' => $duration,
            'expires_in_seconds' => $expires_in_seconds,
            'expires_at_unix' => $expires_at_unix,
        ],
    ], 201);
} catch (Throwable $e) {
    coinrexFastError(422, $e->getMessage());
}
