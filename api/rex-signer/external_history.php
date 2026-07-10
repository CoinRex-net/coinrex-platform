<?php
/**
 * Rate-limited mobile helper: fetch recent external wallet activity through a
 * server-side explorer key, then let RexLink merge it into local SecureStore.
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('GET');

function rexSignerExplorerEnv($key, $default = '') {
    $value = getenv((string) $key);
    return is_string($value) && trim($value) !== '' ? trim($value) : $default;
}

function rexSignerExternalAmount($raw_value, $decimals) {
    $raw = preg_replace('/\D+/', '', (string) $raw_value);
    $decimals = max(0, (int) $decimals);
    if ($raw === '') {
        return '0';
    }

    if ($decimals === 0) {
        return ltrim($raw, '0') ?: '0';
    }

    $raw = str_pad($raw, $decimals + 1, '0', STR_PAD_LEFT);
    $whole = substr($raw, 0, -$decimals);
    $fraction = substr($raw, -$decimals);
    $fraction = rtrim(substr($fraction, 0, 6), '0');
    $whole = ltrim($whole, '0') ?: '0';

    return $fraction === '' ? $whole : $whole . '.' . $fraction;
}

function rexSignerExternalGasFee($gas_used, $gas_price, $symbol = 'POL') {
    $gas_used = (float) preg_replace('/[^\d.]+/', '', (string) $gas_used);
    $gas_price = (float) preg_replace('/[^\d.]+/', '', (string) $gas_price);
    if ($gas_used <= 0 || $gas_price <= 0) {
        return '';
    }

    $fee = ($gas_used * $gas_price) / 1000000000000000000;
    if ($fee <= 0) {
        return '';
    }

    $formatted = rtrim(rtrim(number_format($fee, 6, '.', ''), '0'), '.');
    return ($formatted === '' ? '0' : $formatted) . ' ' . $symbol;
}

function rexSignerExplorerRequest($base_url, array $params) {
    $url = rtrim((string) $base_url, '?') . '?' . http_build_query($params);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 7,
            'header' => "Accept: application/json\r\nUser-Agent: RexLink/1.0\r\n",
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || trim($raw) === '') {
        return ['status' => 'unavailable', 'result' => []];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['status' => 'unavailable', 'result' => []];
    }

    $message = strtolower((string) ($decoded['message'] ?? ''));
    if (($decoded['status'] ?? '') === '0' && strpos($message, 'no transactions') === false) {
        return ['status' => strpos($message, 'rate') !== false ? 'rate_limited' : 'unavailable', 'result' => []];
    }

    return [
        'status' => 'ok',
        'result' => is_array($decoded['result'] ?? null) ? $decoded['result'] : [],
    ];
}

function rexSignerNormalizeExternalHistory(array $rows, $wallet_address, array $network, $kind = 'token') {
    $wallet_lower = strtolower((string) $wallet_address);
    $explorer = rtrim((string) ($network['explorer_url'] ?? 'https://amoy.polygonscan.com'), '/');
    $network_slug = (string) ($network['slug'] ?? 'polygon-amoy');
    $network_name = (string) ($network['name'] ?? 'Polygon Amoy');
    $native_symbol = (string) ($network['native_symbol'] ?? 'POL');
    $items = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $timestamp = (int) ($row['timeStamp'] ?? 0);
        $hash = strtolower(trim((string) ($row['hash'] ?? '')));
        $from = strtolower(trim((string) ($row['from'] ?? '')));
        $to = strtolower(trim((string) ($row['to'] ?? '')));
        if ($timestamp <= 0 || $hash === '' || ($from !== $wallet_lower && $to !== $wallet_lower)) {
            continue;
        }

        $direction = $to === $wallet_lower ? 'received' : 'sent';
        $counterparty = $direction === 'received' ? $from : $to;
        $receipt_status = (string) ($row['txreceipt_status'] ?? '1');
        $status = $receipt_status === '0' ? 'Failed' : 'Confirmed';
        $symbol = $kind === 'native' ? $native_symbol : trim((string) ($row['tokenSymbol'] ?? 'TOKEN'));
        $decimals = $kind === 'native' ? 18 : (int) ($row['tokenDecimal'] ?? 18);
        $amount = rexSignerExternalAmount($row['value'] ?? '0', $decimals) . ' ' . ($symbol !== '' ? $symbol : 'TOKEN');
        $log_id = $kind === 'native' ? 'native' : (string) ($row['logIndex'] ?? $row['transactionIndex'] ?? 'token');

        $items[] = [
            'id' => 'external-' . $hash . '-' . $log_id,
            'walletAddress' => $wallet_lower,
            'type' => $direction === 'received' ? 'receive' : 'send',
            'direction' => $direction,
            'status' => $status,
            'tokenSymbol' => $symbol,
            'amount' => $amount,
            'counterpartyAddress' => $counterparty !== '' ? $counterparty : 'Unknown',
            'gasFee' => $direction === 'sent' ? rexSignerExternalGasFee($row['gasUsed'] ?? '', $row['gasPrice'] ?? '', $native_symbol) : '',
            'estimatedValue' => '',
            'txHash' => $hash,
            'explorerUrl' => $explorer !== '' ? $explorer . '/tx/' . $hash : '',
            'networkSlug' => $network_slug,
            'networkName' => $network_name,
            'source' => 'external',
            'createdAt' => date('c', $timestamp),
            'updatedAt' => date('c'),
        ];
    }

    return $items;
}

$db = getDBConnection();
rexSignerEnsureSchema($db);
$session = null;
$token = rexSignerGetBearerToken();
if ($token !== '') {
    $session = rexSignerGetSessionByToken($db, $token);
    if (!$session) {
        apiErrorResponse(401, 'Valid RexLink session required.');
    }
}

$wallet_address = strtolower(trim((string) rexSignerInput('wallet_address', $session['wallet_address'] ?? '')));

if (!preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
    apiErrorResponse(422, 'Valid wallet_address is required.');
}

$session_wallet = strtolower(trim((string) ($session['wallet_address'] ?? '')));
if ($session_wallet !== '' && $session_wallet !== $wallet_address) {
    apiErrorResponse(403, 'This signer session is paired to a different wallet.');
}

$claim_network = rexSignerClaimNetworkConfig($db);
$network = rexSignerNetworkContext($db, (string) ($claim_network['network_slug'] ?? 'polygon'), (int) ($claim_network['chain_id'] ?? 137));
$network['explorer_url'] = $network['is_known'] ? (string) ($network['explorer_url'] ?? '') : '';
if (empty($network['explorer_url'])) {
    $network['explorer_url'] = (string) (($claim_network['claim_deployment_data']['explorerUrl'] ?? '') ?: ($claim_network['network_slug'] === 'polygon-amoy' ? 'https://amoy.polygonscan.com' : 'https://polygonscan.com'));
}

$api_key = rexSignerExplorerEnv('ETHERSCAN_API_KEY', rexSignerExplorerEnv('POLYGONSCAN_API_KEY', rexSignerExplorerEnv('EXPLORER_API_KEY', '')));
if ($api_key === '') {
    apiSuccessResponse([
        'status' => 'missing_api_key',
        'message' => 'External history sync is not configured.',
        'wallet_address' => $wallet_address,
        'lookback_days' => 7,
        'fetched_at' => date('c'),
        'external_history' => [],
    ]);
}

$api_base = rexSignerExplorerEnv('ETHERSCAN_API_BASE_URL', 'https://api.etherscan.io/v2/api');
$chain_id = (int) ($network['chain_id'] ?? $claim_network['chain_id'] ?? 137);
$common = [
    'chainid' => $chain_id,
    'module' => 'account',
    'address' => $wallet_address,
    'startblock' => 0,
    'endblock' => 99999999,
    'page' => 1,
    'offset' => 100,
    'sort' => 'desc',
    'apikey' => $api_key,
];

$normal = rexSignerExplorerRequest($api_base, array_merge($common, ['action' => 'txlist']));
$tokens = rexSignerExplorerRequest($api_base, array_merge($common, ['action' => 'tokentx']));
$cutoff = time() - (7 * 24 * 60 * 60);

$normal_rows = array_values(array_filter($normal['result'], static function ($row) use ($cutoff) {
    return is_array($row) && (int) ($row['timeStamp'] ?? 0) >= $cutoff && (string) ($row['value'] ?? '0') !== '0';
}));
$token_rows = array_values(array_filter($tokens['result'], static function ($row) use ($cutoff) {
    return is_array($row) && (int) ($row['timeStamp'] ?? 0) >= $cutoff;
}));
$token_hashes = [];
foreach ($token_rows as $row) {
    $hash = strtolower(trim((string) ($row['hash'] ?? '')));
    if ($hash !== '') {
        $token_hashes[$hash] = true;
    }
}
$normal_rows = array_values(array_filter($normal_rows, static function ($row) use ($token_hashes) {
    $hash = strtolower(trim((string) ($row['hash'] ?? '')));
    return $hash === '' || empty($token_hashes[$hash]);
}));

$history = array_merge(
    rexSignerNormalizeExternalHistory($normal_rows, $wallet_address, $network, 'native'),
    rexSignerNormalizeExternalHistory($token_rows, $wallet_address, $network, 'token')
);
usort($history, static function ($a, $b) {
    return strtotime((string) ($b['createdAt'] ?? '')) <=> strtotime((string) ($a['createdAt'] ?? ''));
});

$status = $normal['status'] === 'ok' || $tokens['status'] === 'ok' ? 'ok' : ($normal['status'] !== 'ok' ? $normal['status'] : $tokens['status']);
apiSuccessResponse([
    'status' => $status,
    'wallet_address' => $wallet_address,
    'lookback_days' => 7,
    'fetched_at' => date('c'),
    'external_history' => array_slice($history, 0, 100),
]);
