<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $db = getDBConnection();
    $actor = apiGetAuthenticatedUser();
    if ($actor['type'] !== 'user' || empty($actor['user_id'])) {
        apiErrorResponse(403, 'Only logged-in CoinRex users can create approval requests.');
    }

    rexSignerExpireOldRows($db, ['publish_session_expired_events' => false]);

    $network_slug = trim((string) rexSignerInput('network_slug', 'polygon-amoy'));
    $request_type = strtolower(trim((string) rexSignerInput('request_type', 'claim')));
    $title = trim((string) rexSignerInput('title', 'CoinRex approval request'));
    $summary = trim((string) rexSignerInput('summary', ''));
    $amount = trim((string) rexSignerInput('amount', ''));
    $fee_estimate = trim((string) rexSignerInput('fee_estimate', ''));
    $payload = rexSignerInput('payload', null);
    $dapp_name = trim((string) rexSignerInput('dapp_name', ''));
    $dapp_url = trim((string) rexSignerInput('dapp_url', ''));
    $network_name = trim((string) rexSignerInput('network_name', ''));
    $chain_id = rexSignerInput('chain_id', null);
    $wallet_address = trim((string) rexSignerInput('wallet_address', ''));
    $contract_address = trim((string) rexSignerInput('contract_address', ''));
    $recipient_address = trim((string) rexSignerInput('recipient_address', ''));
    $spender_address = trim((string) rexSignerInput('spender_address', ''));
    $expires_minutes = max(1, min((int) rexSignerInput('expires_minutes', 10), 60));

    if ($network_slug === '') {
        apiErrorResponse(422, 'network_slug is required.');
    }
    if ($chain_id === null || $chain_id === '' || (int) $chain_id <= 0) {
        apiErrorResponse(422, 'chain_id is required.');
    }

    if (!in_array($request_type, ['claim', 'send', 'message'], true)) {
        apiErrorResponse(422, 'Invalid request_type.');
    }

    if ($title === '') {
        apiErrorResponse(422, 'title is required.');
    }

    $network_stmt = $db->prepare("SELECT slug, name, chain_id, native_symbol FROM rex_signer_networks WHERE slug = ? AND chain_id = ? AND is_enabled = 1 AND chain_family = 'evm' LIMIT 1");
    $network_stmt->execute([$network_slug, (int) $chain_id]);
    $network_row = $network_stmt->fetch();
    if (!$network_row) {
        apiErrorResponse(422, 'The requested network is disabled, unknown, or does not match its chain ID.');
    }

    $expiry_stmt = $db->query("SELECT DATE_ADD(NOW(), INTERVAL {$expires_minutes} MINUTE) AS expires_at");
    $approval_expires_at = (string) (($expiry_stmt ? $expiry_stmt->fetch()['expires_at'] ?? '' : '') ?: '');

    $payload_json = null;
    $payload = is_array($payload) ? $payload : ($payload === null || $payload === '' ? [] : $payload);
    if (is_array($payload)) {
        $payload = array_merge([
            'dapp_name' => $dapp_name !== '' ? substr($dapp_name, 0, 80) : null,
            'dapp_url' => $dapp_url !== '' ? substr($dapp_url, 0, 255) : null,
            'origin' => rexSignerRequestOriginUrl() ?: null,
            'base_url' => defined('BASE_URL') ? BASE_URL : null,
            'api_base_url' => defined('BASE_URL') ? BASE_URL : null,
            'network_slug' => $network_slug,
            'network_name' => $network_name !== '' ? substr($network_name, 0, 100) : (string) ($network_row['name'] ?? ''),
            'chain_id' => $chain_id !== null && $chain_id !== '' ? (int) $chain_id : (isset($network_row['chain_id']) ? (int) $network_row['chain_id'] : null),
            'native_symbol' => (string) ($network_row['native_symbol'] ?? ''),
            'wallet_address' => $wallet_address !== '' ? strtolower($wallet_address) : null,
            'contract_address' => $contract_address !== '' ? strtolower($contract_address) : null,
            'recipient_address' => $recipient_address !== '' ? strtolower($recipient_address) : null,
            'spender_address' => $spender_address !== '' ? strtolower($spender_address) : null,
            'amount' => $amount !== '' ? $amount : null,
            'fee_estimate' => $fee_estimate !== '' ? $fee_estimate : null,
        ], array_filter($payload, static function ($value) {
            return $value !== null && $value !== '';
        }));
        $payload['network_slug'] = $network_slug;
        $payload['network_name'] = $network_name !== '' ? substr($network_name, 0, 100) : (string) ($network_row['name'] ?? '');
        $payload['chain_id'] = $chain_id !== null && $chain_id !== '' ? (int) $chain_id : (isset($network_row['chain_id']) ? (int) $network_row['chain_id'] : null);
        $payload['native_symbol'] = (string) ($network_row['native_symbol'] ?? '');
        $contexts = rexSignerBuildDisplayContext($db, array_merge($payload, [
            'network_slug' => $network_slug,
            'chain_id' => $payload['chain_id'] ?? null,
            'expires_at' => $approval_expires_at,
        ]));
        $payload['display_context'] = $contexts['display_context'];
        $payload['trust_context'] = $contexts['trust_context'];
    }
    if ($payload !== null && $payload !== '') {
        $payload_json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload_json === false) {
            apiErrorResponse(422, 'payload must be JSON serializable.');
        }
    }

    $stmt = $db->prepare("
        INSERT INTO rex_signer_approval_requests
            (user_id, network_slug, chain_id, request_type, title, summary, amount, fee_estimate, payload_json, expires_at)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL {$expires_minutes} MINUTE))
    ");
    $stmt->execute([
        (int) $actor['user_id'],
        $network_slug,
        (int) $chain_id,
        $request_type,
        substr($title, 0, 160),
        $summary !== '' ? substr($summary, 0, 255) : null,
        $amount !== '' ? substr($amount, 0, 80) : null,
        $fee_estimate !== '' ? substr($fee_estimate, 0, 80) : null,
        $payload_json,
    ]);

    $response_contexts = is_array($payload) && isset($payload['display_context'], $payload['trust_context'])
        ? ['display_context' => $payload['display_context'], 'trust_context' => $payload['trust_context']]
        : rexSignerBuildDisplayContext($db, [
            'network_slug' => $network_slug,
            'chain_id' => isset($network_row['chain_id']) ? (int) $network_row['chain_id'] : null,
            'expires_at' => $approval_expires_at,
        ]);

    apiSuccessResponse([
        'message' => 'Approval request created.',
        'request_id' => (int) $db->lastInsertId(),
        'status' => 'pending',
        'expires_in_seconds' => $expires_minutes * 60,
        'display_context' => $response_contexts['display_context'],
        'trust_context' => $response_contexts['trust_context'],
    ], 201);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
