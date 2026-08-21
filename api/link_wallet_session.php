<?php
/**
 * Persist a paired RexLink wallet onto the logged-in CoinRex account.
 *
 * The pairing flow (public/link-wallet.php) creates a RexLink session on the
 * node (:18083) that shares the coinrex DB. Once the session reports a wallet,
 * the browser POSTs the session here so we can:
 *   1. load the active session for the current user,
 *   2. validate the wallet address,
 *   3. reject the wallet if it is already linked to another account,
 *   4. persist it on the user row (idempotently).
 *
 * POST (JSON): { session_id, csrf_token }
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    if (!isLoggedIn()) {
        apiErrorResponse(401, 'Authentication required.');
    }

    $user = getCurrentUser();
    $user_id = (int) ($user['id'] ?? 0);
    if ($user_id <= 0) {
        apiErrorResponse(401, 'Authentication required.');
    }

    $raw = file_get_contents('php://input');
    $body = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
    $body = is_array($body) ? $body : [];

    $csrf_token = trim((string) ($body['csrf_token'] ?? ($_POST['csrf_token'] ?? '')));
    if (!validateAppCsrfToken($csrf_token)) {
        apiErrorResponse(403, 'Invalid CSRF token.');
    }

    $db = getDBConnection();
    $session_id = (int) ($body['session_id'] ?? 0);

    if ($session_id > 0) {
        $stmt = $db->prepare("
            SELECT id,
                   user_id,
                   wallet_address,
                   status,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds
            FROM rex_signer_sessions
            WHERE id = ?
              AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$session_id, $user_id]);
    } else {
        $stmt = $db->prepare("
            SELECT id,
                   user_id,
                   wallet_address,
                   status,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds
            FROM rex_signer_sessions
            WHERE user_id = ?
              AND status = 'active'
              AND expires_at > NOW()
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
    }
    $session = $stmt->fetch();

    if (!$session) {
        apiErrorResponse(422, 'No active RexLink session found. Pair your wallet again.');
    }
    if ((int) ($session['user_id'] ?? 0) !== $user_id) {
        apiErrorResponse(403, 'This RexLink session belongs to another account.');
    }
    if ((string) ($session['status'] ?? '') !== 'active' || (int) ($session['remaining_seconds'] ?? 0) <= 0) {
        apiErrorResponse(422, 'RexLink session expired. Please pair your wallet again.');
    }

    $wallet_address = strtolower(trim((string) ($session['wallet_address'] ?? '')));
    if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
        apiErrorResponse(422, 'RexLink did not return a valid wallet address.');
    }

    // Conflict check: the same wallet cannot belong to two CoinRex accounts.
    $conflict = $db->prepare("SELECT id FROM users WHERE wallet_address = ? AND id <> ? LIMIT 1");
    $conflict->execute([$wallet_address, $user_id]);
    if ($conflict->fetch()) {
        apiErrorResponse(409, 'This wallet is already linked to another CoinRex account.');
    }

    // Keep the sign-in identity consistent:
    // - Wallet-only accounts stay 'rex_signer'.
    // - Email accounts with a verified email become 'hybrid' (email + wallet).
    // - Anything else falls back to 'rex_signer' so wallet sign-in works.
    $current_provider = strtolower(trim((string) ($user['auth_provider'] ?? 'email')));
    $email = trim((string) ($user['email'] ?? ''));
    $email_verified = (int) ($user['email_verified'] ?? 0);
    if ($current_provider === 'rex_signer') {
        $new_provider = 'rex_signer';
    } elseif ($email !== '' && $email_verified === 1) {
        $new_provider = 'hybrid';
    } else {
        $new_provider = 'rex_signer';
    }

    $update = $db->prepare("
        UPDATE users
        SET wallet_address = ?,
            wallet_verified_at = NOW(),
            auth_provider = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$wallet_address, $new_provider, $user_id]);

    // Revoke the user's other active sessions (keep the just-linked one).
    $keep_session_id = (int) ($session['id'] ?? 0);
    $other_stmt = $db->prepare("
        SELECT id
        FROM rex_signer_sessions
        WHERE user_id = ?
          AND id <> ?
          AND status = 'active'
    ");
    $other_stmt->execute([$user_id, $keep_session_id]);
    $other_ids = array_map('intval', array_column($other_stmt->fetchAll(), 'id'));

    if (!empty($other_ids)) {
        $placeholders = implode(',', array_fill(0, count($other_ids), '?'));
        $revoke = $db->prepare("
            UPDATE rex_signer_sessions
            SET status = 'revoked',
                revoked_at = NOW(),
                revoke_reason = 'Replaced by a newly linked wallet'
            WHERE user_id = ?
              AND id IN ({$placeholders})
              AND status = 'active'
        ");
        $revoke->execute(array_merge([$user_id], $other_ids));
        if (function_exists('coinrexRealtimePublish')) {
            foreach ($other_ids as $other_id) {
                coinrexRealtimePublish('session.revoked', [
                    'user_id' => $user_id,
                    'session_id' => (int) $other_id,
                    'status' => 'revoked',
                    'reason' => 'Replaced by a newly linked wallet',
                ]);
            }
        }
    }

    apiSuccessResponse([
        'message' => 'Wallet linked successfully.',
        'wallet_address' => $wallet_address,
        'session_id' => $keep_session_id,
    ]);
} catch (Throwable $e) {
    $status_code = stripos($e->getMessage(), 'already linked') !== false ? 409 : 422;
    apiErrorResponse($status_code, $e->getMessage());
}
