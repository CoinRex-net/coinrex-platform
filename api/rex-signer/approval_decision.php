<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $db = getDBConnection();
    $actor = rexSignerRequireUserActor($db);
    if ($actor['type'] !== 'signer_session') {
        apiErrorResponse(403, 'An active RexLink session is required to decide approvals.');
    }

    rexSignerExpireOldRows($db);

    $request_id = (int) rexSignerInput('request_id', 0);
    $decision = strtolower(trim((string) rexSignerInput('decision', '')));
    if ($request_id <= 0) {
        apiErrorResponse(422, 'Valid request_id is required.');
    }

    if (!in_array($decision, ['approved', 'rejected'], true)) {
        apiErrorResponse(422, 'Decision must be approved or rejected.');
    }

    $request_stmt = $db->prepare("
        SELECT *
        FROM rex_signer_approval_requests
        WHERE id = ?
          AND user_id = ?
          AND status = 'pending'
          AND expires_at > NOW()
        LIMIT 1
    ");
    $request_stmt->execute([$request_id, (int) $actor['user_id']]);
    $request = $request_stmt->fetch();
    if (!$request) {
        apiErrorResponse(404, 'Pending approval request was not found.');
    }

    $result = null;
    $wallet_address = null;
    $decision_note = trim((string) rexSignerInput('note', ''));
    if ($decision === 'approved' && (string) ($request['request_type'] ?? '') === 'claim') {
        $payload = !empty($request['payload_json']) ? json_decode((string) $request['payload_json'], true) : [];
        if (($payload['action'] ?? '') === 'generate_claim') {
            $wallet_address = trim((string) rexSignerInput('wallet_address', ''));
            $result = rexSignerBuildSignedClaim($db, (int) $actor['user_id'], $wallet_address, $payload['claim_amount'] ?? null);
        }
    }

    $stmt = $db->prepare("
        UPDATE rex_signer_approval_requests
        SET status = ?,
            session_id = ?,
            wallet_address = COALESCE(?, wallet_address),
            result_json = COALESCE(?, result_json),
            decision_note = ?,
            decided_at = NOW()
        WHERE id = ?
          AND user_id = ?
          AND status = 'pending'
          AND expires_at > NOW()
    ");
    $stmt->execute([
        $decision,
        (int) $actor['session_id'],
        $wallet_address,
        $result ? json_encode($result, JSON_UNESCAPED_SLASHES) : null,
        $decision_note,
        $request_id,
        (int) $actor['user_id'],
    ]);

    if ($stmt->rowCount() <= 0) {
        apiErrorResponse(404, 'Pending approval request was not found.');
    }

    coinrexRealtimePublish('approval.updated', [
        'user_id' => (int) $actor['user_id'],
        'session_id' => (int) $actor['session_id'],
        'request_id' => $request_id,
        'status' => $decision,
        'request_type' => (string) ($request['request_type'] ?? ''),
        'title' => (string) ($request['title'] ?? ''),
        'summary' => (string) ($request['summary'] ?? ''),
        'amount' => (string) ($request['amount'] ?? ''),
        'fee_estimate' => (string) ($request['fee_estimate'] ?? ''),
        'network_slug' => (string) ($request['network_slug'] ?? ''),
        'wallet_address' => $wallet_address,
        'has_result' => is_array($result),
        'decision_note' => $decision_note,
    ]);

    apiSuccessResponse([
        'message' => 'Approval request updated.',
        'request_id' => $request_id,
        'status' => $decision,
        'result' => $result,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
