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

function rexSignerHistoryCachePath($wallet_address, $network_slug) {
    $identity = strtolower(trim((string) $wallet_address)) . ':' . strtolower(trim((string) $network_slug));
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'rexlink_history_v6_' . hash('sha256', $identity) . '.json';
}

function rexSignerPruneHistoryCache(array $history) {
    $cutoff = time() - (7 * 24 * 60 * 60);
    return array_values(array_filter($history, static function ($item) use ($cutoff) {
        if (!is_array($item)) {
            return false;
        }
        $created_at = strtotime((string) ($item['createdAt'] ?? ''));
        return $created_at === false || $created_at >= $cutoff;
    }));
}

function rexSignerLoadHistoryCache(PDO $db, $wallet_address, $network_slug) {
    try {
        $stmt = $db->prepare("
            SELECT history_json, UNIX_TIMESTAMP(fetched_at) AS fetched_at
            FROM rex_signer_activity_cache
            WHERE wallet_address = ? AND network_slug = ?
            LIMIT 1
        ");
        $stmt->execute([strtolower(trim((string) $wallet_address)), strtolower(trim((string) $network_slug))]);
        $row = $stmt->fetch();
        if ($row) {
            $history = json_decode((string) ($row['history_json'] ?? ''), true);
            if (is_array($history)) {
                return [
                    'fetched_at' => (int) ($row['fetched_at'] ?? 0),
                    'history' => rexSignerPruneHistoryCache($history),
                ];
            }
        }
    } catch (Throwable $e) {
        // Fall back to the local cache during rolling deployments or DB outages.
    }

    $path = rexSignerHistoryCachePath($wallet_address, $network_slug);
    if (!is_file($path)) {
        return ['fetched_at' => 0, 'history' => []];
    }

    $decoded = json_decode((string) @file_get_contents($path), true);
    if (!is_array($decoded)) {
        return ['fetched_at' => 0, 'history' => []];
    }

    return [
        'fetched_at' => (int) ($decoded['fetched_at'] ?? 0),
        'history' => rexSignerPruneHistoryCache((array) ($decoded['history'] ?? [])),
    ];
}

function rexSignerSaveHistoryCache(PDO $db, $wallet_address, $network_slug, array $history) {
    $history = array_values($history);
    $encoded_history = json_encode($history, JSON_UNESCAPED_SLASHES);
    if (is_string($encoded_history)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO rex_signer_activity_cache
                    (wallet_address, network_slug, history_json, fetched_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    history_json = VALUES(history_json),
                    fetched_at = VALUES(fetched_at)
            ");
            $stmt->execute([
                strtolower(trim((string) $wallet_address)),
                strtolower(trim((string) $network_slug)),
                $encoded_history,
            ]);
        } catch (Throwable $e) {
            // The file cache below keeps history available if DB persistence fails.
        }
    }

    $payload = [
        'fetched_at' => time(),
        'history' => $history,
    ];
    @file_put_contents(
        rexSignerHistoryCachePath($wallet_address, $network_slug),
        json_encode($payload, JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
}

function rexSignerMergeHistory(array $cached_history, array $live_history) {
    $seen = [];
    $merged = [];
    foreach (array_merge($live_history, $cached_history) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $id = strtolower(trim((string) ($item['id'] ?? '')));
        $hash = strtolower(trim((string) ($item['txHash'] ?? $item['hash'] ?? '')));
        $contract = strtolower(trim((string) ($item['contractAddress'] ?? $item['tokenAddress'] ?? 'native')));
        $key = $id !== '' ? $id : $hash . ':' . ($contract !== '' ? $contract : 'native');
        if (($id === '' && $hash === '') || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $merged[] = $item;
    }

    usort($merged, static function ($a, $b) {
        return strtotime((string) ($b['createdAt'] ?? '')) <=> strtotime((string) ($a['createdAt'] ?? ''));
    });
    return array_slice($merged, 0, 100);
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
    $fraction = rtrim(substr($fraction, 0, 12), '0');
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

    $formatted = rtrim(rtrim(number_format($fee, 12, '.', ''), '0'), '.');
    if ($formatted === '' || $formatted === '0') {
        return '<0.000000000001 ' . $symbol;
    }
    return $formatted . ' ' . $symbol;
}

function rexSignerExternalCounterpartyLabel(array $row, $direction) {
    $prefix = $direction === 'received' ? 'from' : 'to';
    $keys = [
        $prefix . 'Name',
        $prefix . 'Label',
        $prefix . 'Tag',
        $prefix . '_name',
        $prefix . '_label',
        $prefix . '_tag',
        $prefix . 'AddressName',
        $prefix . 'AddressLabel',
        $prefix . 'AddressTag',
    ];

    foreach ($keys as $key) {
        $label = trim(strip_tags((string) ($row[$key] ?? '')));
        if ($label === '' || strtolower($label) === 'unknown' || preg_match('/^0x[a-f0-9]{40}$/i', $label)) {
            continue;
        }
        return substr($label, 0, 80);
    }

    return '';
}

function rexSignerExternalTokenSymbolIsSafe($symbol) {
    $value = trim((string) $symbol);
    if ($value === '' || !preg_match('/^[A-Za-z][A-Za-z0-9._-]{0,19}$/D', $value)) {
        return false;
    }

    return !preg_match('/(?:^|[^a-z0-9])(?:https?:\/\/|www\.)|[a-z0-9][a-z0-9-]*\.(?:app|club|com|finance|io|net|org|site|to|top|xyz)(?:$|[^a-z0-9])/i', $value);
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
        $counterparty_label = rexSignerExternalCounterpartyLabel($row, $direction);
        $receipt_status = (string) ($row['txreceipt_status'] ?? '1');
        $status = $receipt_status === '0' ? 'Failed' : 'Confirmed';
        $symbol = $kind === 'native' ? $native_symbol : trim((string) ($row['tokenSymbol'] ?? 'TOKEN'));
        if ($kind === 'token' && !rexSignerExternalTokenSymbolIsSafe($symbol)) {
            continue;
        }
        $raw_decimals = trim((string) ($row['tokenDecimal'] ?? ''));
        $decimals = $kind === 'native'
            ? 18
            : (preg_match('/^\d{1,3}$/', $raw_decimals) ? max(0, min(255, (int) $raw_decimals)) : 18);
        $raw_amount = preg_replace('/\D+/', '', (string) ($row['value'] ?? '0'));
        $raw_amount = $raw_amount !== '' ? $raw_amount : '0';
        if (trim($raw_amount, '0') === '') {
            continue;
        }
        $amount = rexSignerExternalAmount($raw_amount, $decimals) . ' ' . ($symbol !== '' ? $symbol : 'TOKEN');
        $contract_address = $kind === 'native'
            ? ''
            : strtolower(trim((string) ($row['contractAddress'] ?? $row['tokenAddress'] ?? '')));
        $log_id = $kind === 'native' ? 'native' : (string) ($row['logIndex'] ?? $row['transactionIndex'] ?? 'token');

        $items[] = [
            'id' => 'external-' . $hash . '-' . $log_id,
            'walletAddress' => $wallet_lower,
            'type' => $direction === 'received' ? 'receive' : 'send',
            'direction' => $direction,
            'status' => $status,
            'tokenSymbol' => $symbol,
            'amount' => $amount,
            'rawAmount' => $raw_amount,
            'tokenDecimals' => $decimals,
            'contractAddress' => $contract_address,
            'counterpartyAddress' => $counterparty,
            'counterpartyLabel' => $counterparty_label,
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

function rexSignerHistoryExplorerFallback($network_slug, array $claim_network) {
    $slug = trim((string) $network_slug);
    if ($slug === 'base') {
        return 'https://basescan.org';
    }
    if ($slug === 'polygon-amoy') {
        return 'https://amoy.polygonscan.com';
    }
    if ($slug === 'polygon') {
        return 'https://polygonscan.com';
    }

    return trim((string) ($claim_network['claim_deployment_data']['explorerUrl'] ?? ''));
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
$requested_network_slug = trim((string) rexSignerInput('network_slug', ''));
$requested_chain_id = (int) rexSignerInput('chain_id', 0);
$network = rexSignerNetworkContext(
    $db,
    $requested_network_slug !== '' ? $requested_network_slug : (string) ($claim_network['network_slug'] ?? 'polygon'),
    $requested_chain_id > 0 ? $requested_chain_id : (int) ($claim_network['chain_id'] ?? 137)
);
$network_stmt = null;
if (!empty($network['slug'])) {
    $network_stmt = $db->prepare("SELECT explorer_url FROM rex_signer_networks WHERE slug = ? AND is_enabled = 1 LIMIT 1");
    $network_stmt->execute([(string) $network['slug']]);
} elseif (!empty($network['chain_id'])) {
    $network_stmt = $db->prepare("SELECT explorer_url FROM rex_signer_networks WHERE chain_id = ? AND is_enabled = 1 LIMIT 1");
    $network_stmt->execute([(int) $network['chain_id']]);
}
$network_row = $network_stmt ? ($network_stmt->fetch() ?: null) : null;
$network['explorer_url'] = trim((string) ($network_row['explorer_url'] ?? ''));
if (empty($network['explorer_url'])) {
    $network['explorer_url'] = rexSignerHistoryExplorerFallback((string) ($network['slug'] ?? ''), $claim_network);
}

$api_key = rexSignerExplorerEnv('ETHERSCAN_API_KEY', rexSignerExplorerEnv('POLYGONSCAN_API_KEY', rexSignerExplorerEnv('EXPLORER_API_KEY', '')));

$api_base = rexSignerExplorerEnv('ETHERSCAN_API_BASE_URL', 'https://api.etherscan.io/v2/api');

$is_etherscan_v2 = $api_key !== '';
$slug = trim((string) ($network['slug'] ?? ''));
$history_cache = rexSignerLoadHistoryCache($db, $wallet_address, $slug);
$history_cache_ttl = 90;
if (
    $history_cache['fetched_at'] > 0
    && (time() - (int) $history_cache['fetched_at']) < $history_cache_ttl
) {
    apiSuccessResponse([
        'status' => 'ok',
        'source' => 'server_cache',
        'wallet_address' => $wallet_address,
        'lookback_days' => 7,
        'fetched_at' => date('c', (int) $history_cache['fetched_at']),
        'external_history' => array_slice($history_cache['history'], 0, 100),
    ]);
}

// Etherscan V2 account history for Base is not available on its free tier.
// Base Blockscout exposes the same txlist/tokentx interface without that restriction.
if ($slug === 'base') {
    $api_base = 'https://base.blockscout.com/api';
    $api_key = '';
    $is_etherscan_v2 = false;
} elseif ($slug === 'plasma') {
    $api_base = 'https://api.routescan.io/v2/network/mainnet/evm/9745/etherscan/api';
    $api_key = '';
    $is_etherscan_v2 = false;
} elseif ($api_key === '') {
    if ($slug === 'polygon' || $slug === 'polygon-amoy') {
        $api_base = 'https://api.polygonscan.com/api';
    }
}
$chain_id = (int) ($network['chain_id'] ?? $claim_network['chain_id'] ?? 137);
$common = $is_etherscan_v2 ? ['chainid' => $chain_id] : [];
$common = array_merge($common, [
    'module' => 'account',
    'address' => $wallet_address,
    'startblock' => 0,
    'endblock' => 99999999,
    'page' => 1,
    'offset' => 100,
    'sort' => 'desc',
    'apikey' => $api_key,
]);

$normal = rexSignerExplorerRequest($api_base, array_merge($common, ['action' => 'txlist']));
$tokens = rexSignerExplorerRequest($api_base, array_merge($common, ['action' => 'tokentx']));
$cutoff = time() - (7 * 24 * 60 * 60);

$normal_rows = array_values(array_filter($normal['result'], static function ($row) use ($cutoff) {
    return is_array($row) && (int) ($row['timeStamp'] ?? 0) >= $cutoff && (string) ($row['value'] ?? '0') !== '0';
}));
$token_rows = array_values(array_filter($tokens['result'], static function ($row) use ($cutoff) {
    if (!is_array($row) || (int) ($row['timeStamp'] ?? 0) < $cutoff) {
        return false;
    }
    $raw_value = preg_replace('/\D+/', '', (string) ($row['value'] ?? '0'));
    return $raw_value !== '' && trim($raw_value, '0') !== '';
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
$response_source = 'explorer';
if ($status === 'ok') {
    $history = rexSignerMergeHistory($history_cache['history'], $history);
    rexSignerSaveHistoryCache($db, $wallet_address, $slug, $history);
} elseif (!empty($history_cache['history'])) {
    $history = $history_cache['history'];
    $status = 'ok';
    $response_source = 'stale_server_cache';
}

apiSuccessResponse([
    'status' => $status,
    'source' => $response_source,
    'wallet_address' => $wallet_address,
    'lookback_days' => 7,
    'fetched_at' => date('c'),
    'external_history' => array_slice($history, 0, 100),
]);
