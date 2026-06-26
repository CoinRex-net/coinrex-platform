<?php
/**
 * Lock available rewards and create a claim snapshot.
 * POST: user_id (optional for logged-in user)
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $requested_user_id = isset($_POST['user_id']) ? apiGetRequestedUserId('user_id') : null;
    [$user_id] = apiResolveAuthorizedUserId($requested_user_id);

    $wallet_address = trim((string) ($_POST['wallet_address'] ?? ''));
    if ($wallet_address !== '') {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
            throw new InvalidArgumentException('Connected wallet address is invalid.');
        }
        $wallet_address = strtolower($wallet_address);

        $db = getDBConnection();
        ensureRewardClaimSchema($db);
        $wallet_owner = $db->prepare("SELECT id FROM users WHERE wallet_address = ? AND id <> ? LIMIT 1");
        $wallet_owner->execute([$wallet_address, $user_id]);
        if ($wallet_owner->fetch()) {
            throw new RuntimeException('This wallet is already linked to another CoinRex account.');
        }
        $update_wallet = $db->prepare("UPDATE users SET wallet_address = ?, updated_at = NOW() WHERE id = ?");
        $update_wallet->execute([$wallet_address, $user_id]);
    }

    $claim_amount = isset($_POST['claim_amount']) ? (float) $_POST['claim_amount'] : null;
    $snapshot = generateClaimSnapshotForUser($user_id, null, $claim_amount);

    apiSuccessResponse([
        'message' => 'Claim snapshot generated successfully.',
        'snapshot_id' => $snapshot['snapshot_id'],
        'user_id' => $snapshot['user_id'],
        'amount' => $snapshot['amount'],
        'nonce' => $snapshot['nonce'],
        'status' => $snapshot['status'],
    ]);
} catch (Throwable $e) {
    $status_code = stripos($e->getMessage(), 'already prepared') !== false ? 409 : 422;
    apiErrorResponse($status_code, $e->getMessage());
}
