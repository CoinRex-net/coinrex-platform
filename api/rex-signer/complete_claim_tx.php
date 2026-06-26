<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $db = getDBConnection();
    $actor = rexSignerRequireUserActor($db);
    if ($actor['type'] !== 'signer_session') {
        apiErrorResponse(403, 'An active RexLink session is required to report claim transactions.');
    }

    $request_id = (int) rexSignerInput('request_id', 0);
    $tx_hash = trim((string) rexSignerInput('tx_hash', ''));
    $status = strtolower(trim((string) rexSignerInput('status', 'submitted')));
    $error_message = trim((string) rexSignerInput('error_message', ''));
    if ($request_id <= 0) {
        apiErrorResponse(422, 'Valid request_id is required.');
    }
    if ($status !== 'failed' && !preg_match('/^0x[a-fA-F0-9]{64}$/', $tx_hash)) {
        apiErrorResponse(422, 'Valid transaction hash is required.');
    }
    if ($tx_hash !== '' && !preg_match('/^0x[a-fA-F0-9]{64}$/', $tx_hash)) {
        apiErrorResponse(422, 'Valid transaction hash is required.');
    }
    if (!in_array($status, ['submitted', 'confirmed', 'failed'], true)) {
        apiErrorResponse(422, 'Invalid transaction status.');
    }

    $stmt = $db->prepare("
        SELECT result_json, session_id
        FROM rex_signer_approval_requests
        WHERE id = ?
          AND user_id = ?
          AND request_type = 'claim'
          AND status = 'approved'
        LIMIT 1
    ");
    $stmt->execute([$request_id, (int) $actor['user_id']]);
    $row = $stmt->fetch();
    if (!$row) {
        apiErrorResponse(404, 'Approved claim request was not found.');
    }

    $result = $row['result_json'] ? json_decode((string) $row['result_json'], true) : [];
    if (!is_array($result)) {
        $result = [];
    }
    if ($tx_hash !== '') {
        $result['tx_hash'] = $tx_hash;
    }
    $result['tx_status'] = $status;
    $result['tx_reported_at'] = date('c');
    if ($status === 'failed' && $error_message !== '') {
        $result['tx_error'] = substr($error_message, 0, 255);
    }
    $claim_update = null;
    if (in_array($status, ['submitted', 'confirmed'], true) && !empty($result['snapshot_id'])) {
        $claim_update = markClaimSnapshotSubmitted((int) $result['snapshot_id'], (int) $actor['user_id'], $tx_hash, $db);
        $result['claim_snapshot_status'] = 'used';
        $result['ledger_status'] = 'claimed';
    } elseif ($status === 'failed' && $tx_hash === '' && !empty($result['snapshot_id'])) {
        $claim_update = expireClaimSnapshotForUser((int) $result['snapshot_id'], (int) $actor['user_id'], $db);
        $result['claim_snapshot_status'] = 'expired';
        $result['ledger_status'] = 'available';
    }

    $update = $db->prepare("
        UPDATE rex_signer_approval_requests
        SET tx_hash = ?,
            result_json = ?,
            completed_at = CASE WHEN ? IN ('confirmed', 'failed') THEN NOW() ELSE completed_at END
        WHERE id = ?
          AND user_id = ?
    ");
    $update->execute([
        $tx_hash,
        json_encode($result, JSON_UNESCAPED_SLASHES),
        $status,
        $request_id,
        (int) $actor['user_id'],
    ]);

    coinrexRealtimePublish('claim.tx.updated', [
        'user_id' => (int) $actor['user_id'],
        'session_id' => (int) ($row['session_id'] ?? $actor['session_id']),
        'request_id' => $request_id,
        'tx_status' => $status,
    ]);

    apiSuccessResponse([
        'message' => 'Claim transaction recorded.',
        'request_id' => $request_id,
        'tx_hash' => $tx_hash,
        'tx_status' => $status,
        'tx_error' => $status === 'failed' ? ($result['tx_error'] ?? '') : '',
        'claim_update' => $claim_update,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
