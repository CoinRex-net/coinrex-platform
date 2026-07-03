<?php
require_once __DIR__ . '/_bootstrap.php';

function rexSignerReadDeploymentJson($relative_path) {
    $path = dirname(__DIR__, 2) . '/' . ltrim((string) $relative_path, '/\\');
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

function rexSignerCachedCoinGeckoPrices() {
    $cache_file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'coinrex_rex_signer_prices.json';
    $cache_ttl = 90;

    if (is_file($cache_file)) {
        $cached = json_decode((string) file_get_contents($cache_file), true);
        if (is_array($cached) && isset($cached['fetched_at']) && (time() - (int) $cached['fetched_at']) < $cache_ttl) {
            return $cached;
        }
    }

    $url = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=polygon-ecosystem-token,plasma&order=market_cap_desc&per_page=10&page=1&sparkline=false&price_change_percentage=24h';
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
            'header' => "Accept: application/json\r\nUser-Agent: CoinRex-REX-Signer/1.0\r\n",
        ],
    ]);

    $payload = @file_get_contents($url, false, $context);
    if (!is_string($payload) || trim($payload) === '') {
        return is_array($cached ?? null) ? $cached : ['fetched_at' => time(), 'prices' => []];
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        return is_array($cached ?? null) ? $cached : ['fetched_at' => time(), 'prices' => []];
    }

    $prices = [];
    foreach ($decoded as $market) {
        if (!is_array($market)) {
            continue;
        }

        $id = (string) ($market['id'] ?? '');
        $symbol = null;
        if ($id === 'polygon-ecosystem-token') {
            $symbol = 'POL';
        } elseif ($id === 'plasma') {
            $symbol = 'XPL';
        }

        if ($symbol === null) {
            continue;
        }

        $prices[$symbol] = [
            'price_usd' => isset($market['current_price']) ? (float) $market['current_price'] : null,
            'price_change_24h' => isset($market['price_change_percentage_24h']) ? (float) $market['price_change_percentage_24h'] : null,
            'price_status' => isset($market['current_price']) ? 'live' : 'unavailable',
            'logo_url' => !empty($market['image']) ? (string) $market['image'] : null,
        ];
    }

    $cache = [
        'fetched_at' => time(),
        'prices' => $prices,
    ];

    @file_put_contents($cache_file, json_encode($cache, JSON_UNESCAPED_SLASHES));
    return $cache;
}

function rexSignerTokenPrice($symbol, array $price_cache) {
    $symbol = strtoupper((string) $symbol);

    if ($symbol === 'REX') {
        return [
            'price_usd' => 0.0,
            'price_change_24h' => 0.0,
            'price_status' => 'testnet_unpriced',
        ];
    }

    if (isset($price_cache['prices'][$symbol]) && is_array($price_cache['prices'][$symbol])) {
        return $price_cache['prices'][$symbol];
    }

    return [
        'price_usd' => null,
        'price_change_24h' => null,
        'price_status' => 'unavailable',
    ];
}

try {
    $db = getDBConnection();
    rexSignerExpireOldRows($db);

    $stmt = $db->query("
        SELECT slug, name, chain_id, native_symbol, rpc_url, explorer_url, environment,
               chain_family, claim_enabled, token_support_enabled, is_enabled
        FROM rex_signer_networks
        WHERE is_enabled = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $network_rows = $stmt->fetchAll();

    $rex_deployment = rexSignerReadDeploymentJson('deployments/polygon-amoy-rex-token.json');
    $rex_contract = (string) ($rex_deployment['contractAddress'] ?? '0x995C586c19De4003522b3A23dD7C9c9b112e4c71');
    $price_cache = rexSignerCachedCoinGeckoPrices();

    $network_meta = [
        'polygon' => [
            'logo_key' => 'polygon',
            'logo_url' => 'https://assets.coingecko.com/coins/images/32440/standard/polygon.png',
            'tokens' => [
                [
                    'symbol' => 'POL',
                    'name' => 'Polygon Gas',
                    'decimals' => 18,
                    'asset_type' => 'native',
                    'contract_address' => null,
                    'logo_key' => 'polygon',
                    'logo_url' => 'https://assets.coingecko.com/coins/images/32440/standard/polygon.png',
                    'send_enabled' => true,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.000',
                ],
                [
                    'symbol' => 'REX',
                    'name' => 'CoinRex Token',
                    'decimals' => 18,
                    'asset_type' => 'planned',
                    'contract_address' => null,
                    'logo_key' => 'rex',
                    'logo_url' => null,
                    'send_enabled' => false,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.00',
                ],
            ],
        ],
        'base' => [
            'logo_key' => 'base',
            'logo_url' => null,
            'tokens' => [
                [
                    'symbol' => 'ETH',
                    'name' => 'Base Gas',
                    'decimals' => 18,
                    'asset_type' => 'native',
                    'contract_address' => null,
                    'logo_key' => 'base',
                    'logo_url' => null,
                    'send_enabled' => true,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.000',
                ],
                [
                    'symbol' => 'REX',
                    'name' => 'CoinRex Token',
                    'decimals' => 18,
                    'asset_type' => 'planned',
                    'contract_address' => null,
                    'logo_key' => 'rex',
                    'logo_url' => null,
                    'send_enabled' => false,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.00',
                ],
            ],
        ],
        'plasma' => [
            'logo_key' => 'plasma',
            'logo_url' => null,
            'tokens' => [
                [
                    'symbol' => 'XPL',
                    'name' => 'Plasma Gas',
                    'decimals' => 18,
                    'asset_type' => 'native',
                    'contract_address' => null,
                    'logo_key' => 'plasma',
                    'logo_url' => null,
                    'send_enabled' => false,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.000',
                ],
                [
                    'symbol' => 'REX',
                    'name' => 'CoinRex Token',
                    'decimals' => 18,
                    'asset_type' => 'planned',
                    'contract_address' => null,
                    'logo_key' => 'rex',
                    'logo_url' => null,
                    'send_enabled' => false,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.00',
                ],
            ],
        ],
        'polygon-amoy' => [
            'logo_key' => 'polygon',
            'logo_url' => 'https://assets.coingecko.com/coins/images/32440/standard/polygon.png',
            'tokens' => [
                [
                    'symbol' => 'REX',
                    'name' => 'CoinRex Token',
                    'decimals' => (int) ($rex_deployment['decimals'] ?? 18),
                    'asset_type' => 'erc20',
                    'contract_address' => $rex_contract,
                    'logo_key' => 'rex',
                    'logo_url' => null,
                    'send_enabled' => true,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.00',
                ],
                [
                    'symbol' => 'POL',
                    'name' => 'Polygon Gas',
                    'decimals' => 18,
                    'asset_type' => 'native',
                    'contract_address' => null,
                    'logo_key' => 'polygon',
                    'logo_url' => 'https://assets.coingecko.com/coins/images/32440/standard/polygon.png',
                    'send_enabled' => true,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.000',
                ],
            ],
        ],
        'plasma-testnet' => [
            'logo_key' => 'plasma',
            'logo_url' => null,
            'tokens' => [
                [
                    'symbol' => 'XPL',
                    'name' => 'Plasma Gas',
                    'decimals' => 18,
                    'asset_type' => 'native',
                    'contract_address' => null,
                    'logo_key' => 'plasma',
                    'logo_url' => null,
                    'send_enabled' => false,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.000',
                ],
                [
                    'symbol' => 'REX',
                    'name' => 'CoinRex Token',
                    'decimals' => 18,
                    'asset_type' => 'planned',
                    'contract_address' => null,
                    'logo_key' => 'rex',
                    'logo_url' => null,
                    'send_enabled' => false,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.00',
                ],
            ],
        ],
    ];

    $networks = [];
    foreach ($network_rows as $row) {
        $slug = (string) $row['slug'];
        $meta = $network_meta[$slug] ?? ['logo_key' => 'network', 'logo_url' => null, 'tokens' => []];
        $tokens = [];

        foreach ($meta['tokens'] as $token) {
            $tokens[] = array_merge($token, rexSignerTokenPrice($token['symbol'], $price_cache));
        }

        $networks[] = [
            'slug' => $slug,
            'name' => (string) $row['name'],
            'chain_id' => isset($row['chain_id']) ? (int) $row['chain_id'] : null,
            'native_symbol' => (string) $row['native_symbol'],
            'rpc_url' => $row['rpc_url'],
            'explorer_url' => $row['explorer_url'],
            'environment' => (string) $row['environment'],
            'chain_family' => (string) ($row['chain_family'] ?? 'evm'),
            'claim_enabled' => (int) ($row['claim_enabled'] ?? 0),
            'token_support_enabled' => (int) ($row['token_support_enabled'] ?? 0),
            'is_enabled' => (int) $row['is_enabled'],
            'logo_key' => $meta['logo_key'],
            'logo_url' => $meta['logo_url'],
            'tokens' => $tokens,
        ];
    }

    apiSuccessResponse([
        'networks' => $networks,
        'price_cache' => [
            'fetched_at' => date('c', (int) ($price_cache['fetched_at'] ?? time())),
            'ttl_seconds' => 90,
        ],
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
