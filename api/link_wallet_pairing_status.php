<?php
$link_status_started_at = microtime(true);
define('COINREX_SKIP_REWARD_SCHEMA_INIT', true);
define('COINREX_SKIP_REX_SIGNER_SCHEMA_INIT', true);
require_once __DIR__ . '/rex-signer/_bootstrap.php';
header('Cache-Control: no-store, private');
$link_status_bootstrap_ms = (microtime(true) - $link_status_started_at) * 1000;
header('Server-Timing: bootstrap;dur=' . round($link_status_bootstrap_ms, 1), false);

apiRequireMethod('POST');

try {
    // Polling runs every 500 ms. Avoid isLoggedIn() here: it reloads the full
    // user row, updates activity/metrics, and refreshes remember-me state on
    // every poll. The signed-in PHP session plus the pairing owner check below
    // is sufficient for this read-only status lookup.
    $user_id = (int) ($_SESSION['user_id'] ?? 0);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $raw = file_get_contents('php://input');
    $body = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
    $body = is_array($body) ? $body : [];
    $pairing_id = (int) ($body['pairing_id'] ?? 0);

    if ($user_id <= 0 || $pairing_id <= 0) {
        apiErrorResponse(422, 'Valid RexLink pairing is required.');
    }

    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT pairing.id,
               pairing.status AS pairing_status,
               pairing.expires_at,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), pairing.expires_at)) AS pairing_remaining_seconds,
               pairing.completed_session_id,
               session_row.id AS session_id,
               session_row.wallet_address,
               session_row.status AS session_status,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), session_row.expires_at)) AS session_remaining_seconds
        FROM rex_signer_pairing_codes pairing
        LEFT JOIN rex_signer_sessions session_row
            ON session_row.id = pairing.completed_session_id
        WHERE pairing.id = ?
          AND pairing.user_id = ?
          AND pairing.pairing_purpose = 'claim'
        LIMIT 1
    ");
    $stmt->execute([$pairing_id, $user_id]);
    $pairing = $stmt->fetch();
    header('Server-Timing: total;dur=' . round((microtime(true) - $link_status_started_at) * 1000, 1), false);

    if (!$pairing) {
        apiErrorResponse(404, 'RexLink pairing was not found for this account.');
    }

    $pairing_status = strtolower((string) ($pairing['pairing_status'] ?? ''));
    if ($pairing_status === 'pending') {
        $remaining = (int) ($pairing['pairing_remaining_seconds'] ?? 0);
        apiSuccessResponse([
            'status' => $remaining > 0 ? 'pending' : 'expired',
            'message' => $remaining > 0 ? 'Waiting for RexLink.' : 'RexLink pairing code expired.',
            'expires_in_seconds' => $remaining,
        ]);
    }

    if ($pairing_status !== 'completed' || empty($pairing['session_id'])) {
        apiSuccessResponse([
            'status' => $pairing_status !== '' ? $pairing_status : 'expired',
            'message' => 'RexLink pairing is no longer active.',
        ]);
    }

    if (
        strtolower((string) ($pairing['session_status'] ?? '')) !== 'active'
        || (int) ($pairing['session_remaining_seconds'] ?? 0) <= 0
    ) {
        apiSuccessResponse([
            'status' => 'expired',
            'session_id' => (int) $pairing['session_id'],
            'message' => 'RexLink session expired. Please pair again.',
        ]);
    }

    $wallet_address = strtolower(trim((string) ($pairing['wallet_address'] ?? '')));
    if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
        apiErrorResponse(422, 'RexLink did not return a valid wallet address.');
    }

    apiSuccessResponse([
        'status' => 'connected',
        'message' => 'RexLink pairing is connected.',
        'pairing_id' => $pairing_id,
        'session_id' => (int) $pairing['session_id'],
        'wallet_address' => $wallet_address,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
