<?php
require_once __DIR__ . '/_fast_bootstrap.php';

coinrexFastRequirePost();

try {
    $started = microtime(true);
    $user_id = coinrexFastUserId();
    $project_id = (int) coinrexFastInput('project_id', 0);
    $pairing_id = (int) coinrexFastInput('pairing_id', 0);
    $session_id = (int) coinrexFastInput('session_id', 0);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $db = coinrexFastDb();
    $row = null;

    if ($pairing_id > 0) {
        $stmt = $db->prepare("
            SELECT status AS pairing_status,
                   pairing_purpose,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS pairing_remaining_seconds,
                   completed_session_id
            FROM rex_signer_pairing_codes
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$pairing_id, $user_id]);
        $pairing = $stmt->fetch();

        if (!$pairing || (string) ($pairing['pairing_purpose'] ?? '') !== 'review_eligibility') {
            coinrexFastSuccess([
                'status' => 'none',
                'message' => 'RexLink pairing was not found for review eligibility.',
                'server_timing_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        }

        if ((string) ($pairing['pairing_status'] ?? '') === 'pending') {
            coinrexFastSuccess([
                'status' => (int) ($pairing['pairing_remaining_seconds'] ?? 0) <= 0 ? 'expired' : 'pending',
                'message' => 'Waiting for RexLink pairing.',
                'server_timing_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        }

        if ((string) ($pairing['pairing_status'] ?? '') !== 'completed') {
            coinrexFastSuccess([
                'status' => (string) ($pairing['pairing_status'] ?? 'expired'),
                'message' => 'RexLink pairing is no longer active.',
                'server_timing_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        }

        $completed_session_id = (int) ($pairing['completed_session_id'] ?? 0);
        if ($completed_session_id > 0) {
            $session_stmt = $db->prepare("
                SELECT id AS session_id,
                       user_id AS session_user_id,
                       wallet_address AS session_wallet_address,
                       status AS session_status,
                       expires_at AS session_expires_at,
                       GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS session_remaining_seconds
                FROM rex_signer_sessions
                WHERE id = ?
                  AND user_id = ?
                LIMIT 1
            ");
            $session_stmt->execute([$completed_session_id, $user_id]);
        } else {
            $session_stmt = $db->prepare("
                SELECT id AS session_id,
                       user_id AS session_user_id,
                       wallet_address AS session_wallet_address,
                       status AS session_status,
                       expires_at AS session_expires_at,
                       GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS session_remaining_seconds
                FROM rex_signer_sessions
                WHERE pairing_code_id = ?
                  AND user_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $session_stmt->execute([$pairing_id, $user_id]);
        }
        $row = $session_stmt->fetch();
    } else {
        if ($session_id > 0) {
            $stmt = $db->prepare("
                SELECT id AS session_id,
                       user_id AS session_user_id,
                       wallet_address AS session_wallet_address,
                       status AS session_status,
                       expires_at AS session_expires_at,
                       GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS session_remaining_seconds
                FROM rex_signer_sessions
                WHERE id = ?
                  AND user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$session_id, $user_id]);
        } else {
            $stmt = $db->prepare("
                SELECT id AS session_id,
                       user_id AS session_user_id,
                       wallet_address AS session_wallet_address,
                       status AS session_status,
                       expires_at AS session_expires_at,
                       GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS session_remaining_seconds
                FROM rex_signer_sessions
                WHERE user_id = ?
                  AND status = 'active'
                  AND expires_at > NOW()
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
        }
        $row = $stmt->fetch();
    }

    if (!$row) {
        coinrexFastSuccess([
            'status' => 'none',
            'message' => 'No active RexLink session found.',
            'server_timing_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    if ((int) ($row['session_user_id'] ?? 0) !== $user_id) {
        coinrexFastError(403, 'This RexLink session belongs to another account.');
    }

    if ((string) ($row['session_status'] ?? '') !== 'active' || (int) ($row['session_remaining_seconds'] ?? 0) <= 0) {
        coinrexFastSuccess([
            'status' => 'expired',
            'message' => 'RexLink session expired. Please pair again.',
            'server_timing_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    $wallet_address = strtolower(trim((string) ($row['session_wallet_address'] ?? '')));
    if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
        coinrexFastError(422, 'RexLink did not return a valid wallet address.');
    }

    $used_review = coinrexFastFindWalletReviewUsage($db, $wallet_address, $project_id);
    if ($used_review) {
        coinrexFastError(409, 'This Wallet already have used to Review the Same Project, Please Switch to Fresh wallet to Check Eligibility', [
            'status' => 'wallet_used',
            'wallet_address' => $wallet_address,
        ]);
    }

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $_SESSION['review_eligibility_verified_wallet'] = [
        'user_id' => $user_id,
        'wallet_address' => $wallet_address,
        'session_id' => (int) ($row['session_id'] ?? 0),
        'verified_at' => time(),
    ];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    coinrexFastSuccess([
        'status' => 'connected',
        'message' => 'RexLink wallet paired. Check eligibility next.',
        'wallet_address' => $wallet_address,
        'session_id' => (int) ($row['session_id'] ?? 0),
        'session_remaining_seconds' => max(0, (int) ($row['session_remaining_seconds'] ?? 0)),
        'server_timing_ms' => (int) round((microtime(true) - $started) * 1000),
    ]);
} catch (Throwable $e) {
    coinrexFastError(422, $e->getMessage());
}
