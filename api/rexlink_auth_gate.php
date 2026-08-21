<?php
/**
 * RexLink passwordless-auth gate.
 *
 * Called by the RexLink sign-in flow once a pairing session appears so the
 * client can learn whether the paired wallet is linked to any CoinRex
 * account. If it is linked, the node finishes the sign-in (bridge ticket).
 * If it is not linked, the auth page shows the "not linked" message instead
 * of hanging on the generic node timeout.
 *
 * POST: { pairing_id }  OR  { wallet_address }
 */

require_once __DIR__ . '/review-eligibility/_fast_bootstrap.php';

coinrexFastRequirePost();

try {
    $started = microtime(true);
    $server_timing_ms = function () use ($started) {
        return (int) round((microtime(true) - $started) * 1000);
    };

    $pairing_id = (int) coinrexFastInput('pairing_id', 0);
    $wallet_address = strtolower(trim((string) coinrexFastInput('wallet_address', '')));

    if ($wallet_address !== '') {
        if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
            coinrexFastError(422, 'Invalid wallet address.');
        }
        $linked_user = coinrexFastDb()->prepare("SELECT id FROM users WHERE wallet_address = ? LIMIT 1");
        $linked_user->execute([$wallet_address]);
        coinrexFastSuccess([
            'state' => 'connected',
            'linked' => (bool) $linked_user->fetch(),
            'wallet_address' => $wallet_address,
            'server_timing_ms' => $server_timing_ms(),
        ]);
    }

    if ($pairing_id <= 0) {
        coinrexFastError(422, 'Valid pairing_id is required.');
    }

    $db = coinrexFastDb();

    $pairing_stmt = $db->prepare("
        SELECT status AS pairing_status,
               completed_session_id
        FROM rex_signer_pairing_codes
        WHERE id = ?
        LIMIT 1
    ");
    $pairing_stmt->execute([$pairing_id]);
    $pairing = $pairing_stmt->fetch();

    if (!$pairing) {
        coinrexFastSuccess([
            'state' => 'none',
            'message' => 'RexLink pairing was not found.',
            'server_timing_ms' => $server_timing_ms(),
        ]);
    }

    // Resolve the paired session the same way rexlink_wallet.php does: the node
    // records it on the pairing's completed_session_id, but fall back to the
    // session's own pairing_code_id in case a node version only sets that.
    $session = null;
    $completed_session_id = (int) ($pairing['completed_session_id'] ?? 0);
    if ($completed_session_id > 0) {
        $session_stmt = $db->prepare("
            SELECT id,
                   wallet_address,
                   status,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds
            FROM rex_signer_sessions
            WHERE id = ?
            LIMIT 1
        ");
        $session_stmt->execute([$completed_session_id]);
        $session = $session_stmt->fetch();
    }
    if (!$session) {
        $session_stmt = $db->prepare("
            SELECT id,
                   wallet_address,
                   status,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds
            FROM rex_signer_sessions
            WHERE pairing_code_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $session_stmt->execute([$pairing_id]);
        $session = $session_stmt->fetch();
    }

    if (!$session) {
        $pairing_status = strtolower(trim((string) ($pairing['pairing_status'] ?? 'pending')));
        if ($pairing_status === 'pending') {
            coinrexFastSuccess([
                'state' => 'pending',
                'message' => 'Waiting for RexLink pairing.',
                'server_timing_ms' => $server_timing_ms(),
            ]);
        }
        coinrexFastSuccess([
            'state' => in_array($pairing_status, ['expired', 'revoked'], true) ? $pairing_status : 'none',
            'message' => 'RexLink pairing is no longer active.',
            'server_timing_ms' => $server_timing_ms(),
        ]);
    }

    $session_status = (string) ($session['status'] ?? '');
    $session_wallet = strtolower(trim((string) ($session['wallet_address'] ?? '')));
    if (
        $session_status !== 'active'
        || (int) ($session['remaining_seconds'] ?? 0) <= 0
        || !preg_match('/^0x[a-f0-9]{40}$/', $session_wallet)
    ) {
        coinrexFastSuccess([
            'state' => 'expired',
            'message' => 'RexLink session expired. Please pair again.',
            'server_timing_ms' => $server_timing_ms(),
        ]);
    }

    $linked_user = $db->prepare("SELECT id FROM users WHERE wallet_address = ? LIMIT 1");
    $linked_user->execute([$session_wallet]);

    coinrexFastSuccess([
        'state' => 'connected',
        'linked' => (bool) $linked_user->fetch(),
        'wallet_address' => $session_wallet,
        'session_id' => (int) ($session['id'] ?? 0),
        'server_timing_ms' => $server_timing_ms(),
    ]);
} catch (Throwable $e) {
    coinrexFastError(422, $e->getMessage());
}
