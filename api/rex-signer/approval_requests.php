<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $db = getDBConnection();
    $actor = rexSignerRequireUserActor($db);

    rexSignerExpireOldRows($db, ['publish_session_expired_events' => false]);

    $status = strtolower(trim((string) rexSignerInput('status', 'pending')));
    if (!in_array($status, ['pending', 'approved', 'rejected', 'expired', 'cancelled', 'all'], true)) {
        apiErrorResponse(422, 'Invalid approval status filter.');
    }

    if ($status !== 'pending') {
        syncSubmittedClaimTransactionsForUser((int) $actor['user_id'], $db);
        syncStaleClaimApprovalsForUser((int) $actor['user_id'], $db);
    }

    $params = [(int) $actor['user_id']];
    $status_sql = '';
    if ($status !== 'all') {
        $status_sql = 'AND status = ?';
        $params[] = $status;
    }

    $stmt = $db->prepare("
        SELECT id, user_id, session_id, network_slug, request_type, title, summary, amount,
               fee_estimate, payload_json, wallet_address, tx_hash, result_json, status,
               decision_note, decided_at, completed_at, expires_at, created_at
        FROM rex_signer_approval_requests
        WHERE user_id = ?
          {$status_sql}
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute($params);

    $requests = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['id'] = (int) $row['id'];
        $row['user_id'] = (int) $row['user_id'];
        $row['session_id'] = $row['session_id'] !== null ? (int) $row['session_id'] : null;
        $row['payload'] = $row['payload_json'] ? json_decode((string) $row['payload_json'], true) : null;
        $row['result'] = $row['result_json'] ? json_decode((string) $row['result_json'], true) : null;
        $payload = is_array($row['payload']) ? $row['payload'] : [];
        $contexts = rexSignerBuildDisplayContext($db, array_merge($payload, [
            'network_slug' => (string) ($row['network_slug'] ?? ''),
            'wallet_address' => (string) ($row['wallet_address'] ?? ($payload['wallet_address'] ?? '')),
            'amount' => (string) ($row['amount'] ?? ($payload['amount'] ?? '')),
            'fee_estimate' => (string) ($row['fee_estimate'] ?? ($payload['fee_estimate'] ?? '')),
            'expires_at' => (string) ($row['expires_at'] ?? ''),
        ]));
        if (empty($payload['display_context'])) {
            $row['display_context'] = $contexts['display_context'];
        } else {
            $row['display_context'] = $payload['display_context'];
        }
        if (empty($payload['trust_context'])) {
            $row['trust_context'] = $contexts['trust_context'];
        } else {
            $row['trust_context'] = $payload['trust_context'];
        }
        unset($row['payload_json']);
        unset($row['result_json']);
        $requests[] = $row;
    }

    apiSuccessResponse([
        'approval_requests' => $requests,
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
