<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $db = getDBConnection();
    $actor = apiGetAuthenticatedUser();
    if ($actor['type'] !== 'user' || empty($actor['user_id'])) {
        apiErrorResponse(403, 'Only logged-in CoinRex users can request claim approvals.');
    }

    $user_id = (int) $actor['user_id'];
    rexSignerExpireOldRows($db);
    syncSubmittedClaimTransactionsForUser($user_id, $db);
    syncStaleClaimApprovalsForUser($user_id, $db);

    $eligibility = getClaimEligibility($user_id, $db);
    if (empty($eligibility['eligible'])) {
        apiErrorResponse(422, (string) ($eligibility['message'] ?? 'Claim requirements are not met.'));
    }

    $open_stmt = $db->prepare("
        SELECT id
        FROM claim_snapshots
        WHERE user_id = ?
          AND status = 'generated'
        LIMIT 1
    ");
    $open_stmt->execute([$user_id]);
    if ($open_stmt->fetch()) {
        apiErrorResponse(409, 'A claim is already prepared for this account.');
    }

    $session_stmt = $db->prepare("
        SELECT id, wallet_address
        FROM rex_signer_sessions
        WHERE user_id = ?
          AND status = 'active'
          AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $session_stmt->execute([$user_id]);
    $active_session = $session_stmt->fetch();
    if (!$active_session) {
        apiErrorResponse(409, 'Connect RexLink before requesting claim approval.');
    }

    $pending_stmt = $db->prepare("
        SELECT id, session_id, network_slug, request_type, title, summary, amount, fee_estimate, payload_json, expires_at, created_at
        FROM rex_signer_approval_requests
        WHERE user_id = ?
          AND request_type = 'claim'
          AND status = 'pending'
          AND expires_at > NOW()
        ORDER BY id DESC
        LIMIT 1
    ");
    $pending_stmt->execute([$user_id]);
    $pending = $pending_stmt->fetch();
    if ($pending) {
        $pending_payload = !empty($pending['payload_json']) ? json_decode((string) $pending['payload_json'], true) : [];
        $pending_contexts = rexSignerBuildDisplayContext($db, array_merge(is_array($pending_payload) ? $pending_payload : [], [
            'network_slug' => (string) ($pending['network_slug'] ?? 'polygon-amoy'),
            'amount' => (string) ($pending['amount'] ?? ''),
            'fee_estimate' => (string) ($pending['fee_estimate'] ?? ''),
            'expires_at' => (string) ($pending['expires_at'] ?? ''),
        ]));
        coinrexRealtimePublish('approval.created', [
            'user_id' => $user_id,
            'session_id' => (int) ($pending['session_id'] ?? $active_session['id']),
            'request_id' => (int) $pending['id'],
            'status' => 'pending',
            'request_type' => (string) ($pending['request_type'] ?? 'claim'),
            'title' => (string) ($pending['title'] ?? 'Approve REX Claim'),
            'summary' => (string) ($pending['summary'] ?? 'Approve this request in RexLink.'),
            'amount' => (string) ($pending['amount'] ?? ''),
            'fee_estimate' => (string) ($pending['fee_estimate'] ?? ''),
            'network_slug' => (string) ($pending['network_slug'] ?? 'polygon-amoy'),
            'expires_at' => (string) ($pending['expires_at'] ?? ''),
            'created_at' => (string) ($pending['created_at'] ?? ''),
            'display_context' => $pending_contexts['display_context'],
            'trust_context' => $pending_contexts['trust_context'],
            'payload' => is_array($pending_payload) ? $pending_payload : null,
        ]);
        apiSuccessResponse([
            'message' => 'Claim approval is already pending.',
            'request_id' => (int) $pending['id'],
            'status' => 'pending',
            'display_context' => $pending_contexts['display_context'],
            'trust_context' => $pending_contexts['trust_context'],
        ]);
    }

    $available_amount = (float) ($eligibility['balance'] ?? getRewardLedgerBalance($user_id, 'available', $db));
    $claim_amount = round((float) rexSignerInput('claim_amount', $available_amount), 8);
    if ($claim_amount <= 0) {
        apiErrorResponse(422, 'Claim amount must be greater than zero.');
    }
    if ($claim_amount > $available_amount) {
        apiErrorResponse(422, 'Claim amount cannot exceed your available REX balance.');
    }

    $deployment = rexSignerClaimDistributorDeployment();
    $payload = [
        'action' => 'generate_claim',
        'dapp_name' => defined('SITE_NAME') ? SITE_NAME : 'CoinRex',
        'dapp_url' => defined('BASE_URL') ? BASE_URL : '',
        'origin' => rexSignerRequestOriginUrl() ?: null,
        'base_url' => defined('BASE_URL') ? BASE_URL : '',
        'api_base_url' => defined('BASE_URL') ? BASE_URL : '',
        'network_slug' => 'polygon-amoy',
        'network_name' => 'Polygon Amoy',
        'claim_amount' => number_format($claim_amount, 8, '.', ''),
        'amount' => number_format($claim_amount, 8, '.', '') . ' REX',
        'fee_estimate' => ((string) ($deployment['claimFeeFormatted'] ?? '0.01')) . ' POL',
        'wallet_address' => (string) ($active_session['wallet_address'] ?? ''),
        'contract_address' => (string) $deployment['contractAddress'],
        'chain_id' => (int) ($deployment['chainId'] ?? 80002),
    ];
    $contexts = rexSignerBuildDisplayContext($db, array_merge($payload, [
        'expires_at' => date('Y-m-d H:i:s', time() + 600),
    ]));
    $payload['display_context'] = $contexts['display_context'];
    $payload['trust_context'] = $contexts['trust_context'];
    $request_title = 'Approve REX Claim';
    $request_summary = 'Approve this request in RexLink to prepare and submit your claim on Polygon Amoy.';
    $request_amount = number_format($claim_amount, 8, '.', '') . ' REX';
    $request_fee = ((string) ($deployment['claimFeeFormatted'] ?? '0.01')) . ' POL';

    $stmt = $db->prepare("
        INSERT INTO rex_signer_approval_requests
            (user_id, session_id, network_slug, request_type, title, summary, amount, fee_estimate, payload_json, expires_at)
        VALUES
            (?, ?, 'polygon-amoy', 'claim', ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))
    ");
    $stmt->execute([
        $user_id,
        (int) $active_session['id'],
        $request_title,
        $request_summary,
        $request_amount,
        $request_fee,
        json_encode($payload, JSON_UNESCAPED_SLASHES),
    ]);
    $request_id = (int) $db->lastInsertId();

    coinrexRealtimePublish('approval.created', [
        'user_id' => $user_id,
        'session_id' => (int) $active_session['id'],
        'request_id' => $request_id,
        'status' => 'pending',
        'request_type' => 'claim',
        'title' => $request_title,
        'summary' => $request_summary,
        'amount' => $request_amount,
        'fee_estimate' => $request_fee,
        'network_slug' => 'polygon-amoy',
        'network_name' => 'Polygon Amoy',
        'chain_id' => (int) ($deployment['chainId'] ?? 80002),
        'dapp_name' => defined('SITE_NAME') ? SITE_NAME : 'CoinRex',
        'dapp_url' => defined('BASE_URL') ? BASE_URL : '',
        'wallet_address' => (string) ($active_session['wallet_address'] ?? ''),
        'contract_address' => (string) $deployment['contractAddress'],
        'payload' => $payload,
        'display_context' => $contexts['display_context'],
        'trust_context' => $contexts['trust_context'],
        'expires_in_seconds' => 600,
        'created_at' => date('c'),
    ]);

    apiSuccessResponse([
        'message' => 'Claim approval request created.',
        'request_id' => $request_id,
        'status' => 'pending',
        'amount' => number_format($claim_amount, 8, '.', ''),
        'fee_estimate' => $request_fee,
        'expires_in_seconds' => 600,
        'display_context' => $contexts['display_context'],
        'trust_context' => $contexts['trust_context'],
    ], 201);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
