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

function rexSignerNetworkTokenDeployment($network_slug) {
    $slug = trim((string) $network_slug);
    if ($slug === '') {
        return [];
    }

    $deployment = rexSignerReadDeploymentJson("deployments/{$slug}-rex-token.json");
    if (!empty($deployment['contractAddress'])) {
        return $deployment;
    }

    if ($slug === 'polygon') {
        $fallback = rexSignerReadDeploymentJson('deployments/polygon-amoy-rex-token.json');
        if (!empty($fallback['contractAddress'])) {
            return $fallback;
        }
    }

    return [];
}

function rexSignerReliableRpcUrl($network_slug, $configured_url) {
    $slug = strtolower(trim((string) $network_slug));
    $configured = rtrim(trim((string) $configured_url), '/');
    $known_bad = [
        'https://polygon-rpc.com',
        'https://polygon.llamarpc.com',
        'https://base.llamarpc.com',
        'https://plasma-mainnet.g.alchemy.com/public',
    ];
    $preferred = [
        'polygon' => 'https://polygon-bor-rpc.publicnode.com',
        'base' => 'https://mainnet.base.org',
        'plasma' => 'https://rpc.plasma.to',
    ];

    if ($configured === '' || in_array(strtolower($configured), $known_bad, true)) {
        return (string) ($preferred[$slug] ?? $configured);
    }

    return $configured;
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

    $url = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=usd&ids=polygon-ecosystem-token,ethereum,plasma&order=market_cap_desc&per_page=10&page=1&sparkline=false&price_change_percentage=24h';
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
        } elseif ($id === 'ethereum') {
            $symbol = 'ETH';
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

    if ($symbol === 'USDT0' || $symbol === 'USDT' || $symbol === 'USDC') {
        return [
            'price_usd' => 1.0,
            'price_change_24h' => null,
            'price_status' => 'stable',
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
    rexSignerExpireOldRows($db, ['publish_session_expired_events' => false]);

    $stmt = $db->query("
        SELECT slug, name, chain_id, native_symbol, rpc_url, explorer_url, environment,
               chain_family, claim_enabled, token_support_enabled, is_enabled
        FROM rex_signer_networks
        WHERE is_enabled = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $network_rows = $stmt->fetchAll();

    $price_cache = rexSignerCachedCoinGeckoPrices();

    $network_meta = [
        'polygon' => [
            'logo_key' => 'polygon',
            'logo_url' => 'https://assets.coingecko.com/coins/images/32440/standard/polygon.png',
            'tokens' => static function(array $row) {
                return [
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
                    'symbol' => 'USDT0',
                    'name' => 'USDT0',
                    'decimals' => 6,
                    'asset_type' => 'erc20',
                    'contract_address' => '0xC2132D05D31c914A87C6611C10748AaCB04B58e8F',
                    'logo_key' => 'usdt0',
                    'logo_url' => null,
                    'send_enabled' => true,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.00',
                ],
            ];
            },
        ],
        'base' => [
            'logo_key' => 'base',
            'logo_url' => null,
            'tokens' => static function(array $row) {
                return [
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
            ];
            },
        ],
        'plasma' => [
            'logo_key' => 'plasma',
            'logo_url' => null,
            'tokens' => static function(array $row) {
                $has_rpc = !empty($row['rpc_url']);
                return [
                [
                    'symbol' => 'XPL',
                    'name' => 'Plasma Gas',
                    'decimals' => 18,
                    'asset_type' => 'native',
                    'contract_address' => null,
                    'logo_key' => 'plasma',
                    'logo_url' => null,
                    'send_enabled' => $has_rpc,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.000',
                ],
                [
                    'symbol' => 'USDT0',
                    'name' => 'USDT0',
                    'decimals' => 6,
                    'asset_type' => 'erc20',
                    'contract_address' => '0xB8CE59FC3717ada4C02eaDF9682A9e934F625ebb',
                    'logo_key' => 'usdt0',
                    'logo_url' => null,
                    'send_enabled' => $has_rpc,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.00',
                ],
            ];
            },
        ],
        'polygon-amoy' => [
            'logo_key' => 'polygon',
            'logo_url' => 'https://assets.coingecko.com/coins/images/32440/standard/polygon.png',
            'tokens' => static function(array $row) {
                return [
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
            ];
            },
        ],
        'plasma-testnet' => [
            'logo_key' => 'plasma',
            'logo_url' => null,
            'tokens' => static function(array $row) {
                $has_rpc = !empty($row['rpc_url']);
                return [
                [
                    'symbol' => 'XPL',
                    'name' => 'Plasma Gas',
                    'decimals' => 18,
                    'asset_type' => 'native',
                    'contract_address' => null,
                    'logo_key' => 'plasma',
                    'logo_url' => null,
                    'send_enabled' => $has_rpc,
                    'receive_enabled' => true,
                    'balance_placeholder' => '0.000',
                ],
            ];
            },
        ],
    ];

    $wallet_address = isset($_GET['wallet_address']) ? trim((string) $_GET['wallet_address']) : null;

    $networks = [];
    foreach ($network_rows as $row) {
        $slug = (string) $row['slug'];
        $meta = $network_meta[$slug] ?? ['logo_key' => 'network', 'logo_url' => null, 'tokens' => []];
        $tokens = [];

        $meta_tokens = is_callable($meta['tokens'] ?? null) ? $meta['tokens']($row) : ($meta['tokens'] ?? []);
        $meta_tokens = array_values(array_filter($meta_tokens, static function ($token) {
            return strtoupper((string) ($token['symbol'] ?? '')) !== 'REX';
        }));

        $native_balance = ['balance_wei' => '0', 'balance_formatted' => '0.000', 'balance_status' => 'unavailable'];
        $erc20_balances = [];

        if ($wallet_address && !empty($row['rpc_url'])) {
            $rpc_url = rexSignerReliableRpcUrl($slug, $row['rpc_url']);
            try {
                $native_balance = rexSignerRpcGetNativeBalance($rpc_url, $wallet_address);
            } catch (Throwable $e) {
                $native_balance = ['balance_wei' => '0', 'balance_formatted' => '0.000', 'balance_status' => 'rpc_error'];
            }

            foreach ($meta_tokens as $token) {
                if (($token['asset_type'] ?? '') !== 'erc20' || empty($token['contract_address'])) continue;
                try {
                    $erc20_balances[$token['symbol']] = rexSignerRpcGetErc20Balance(
                        $rpc_url,
                        $token['contract_address'],
                        $wallet_address,
                        (int) ($token['decimals'] ?? 18)
                    );
                } catch (Throwable $e) {
                    $erc20_balances[$token['symbol']] = ['balance_wei' => '0', 'balance_formatted' => '0.00', 'balance_status' => 'rpc_error'];
                }
            }
        }

        foreach ($meta_tokens as $token) {
            $enriched = array_merge($token, rexSignerTokenPrice($token['symbol'], $price_cache));

            if (($token['asset_type'] ?? '') === 'native') {
                $enriched['balance_placeholder'] = $native_balance['balance_formatted'];
                $enriched['balance_wei'] = $native_balance['balance_wei'];
                $enriched['balance_status'] = $native_balance['balance_status'];
            } elseif (($token['asset_type'] ?? '') === 'erc20') {
                $erc20 = $erc20_balances[$token['symbol']] ?? ['balance_formatted' => '0.00', 'balance_wei' => '0', 'balance_status' => 'unavailable'];
                $enriched['balance_placeholder'] = $erc20['balance_formatted'];
                $enriched['balance_wei'] = $erc20['balance_wei'];
                $enriched['balance_status'] = $erc20['balance_status'];
            }

            $tokens[] = $enriched;
        }

        $networks[] = [
            'slug' => $slug,
            'name' => (string) $row['name'],
            'chain_id' => isset($row['chain_id']) ? (int) $row['chain_id'] : null,
            'native_symbol' => (string) $row['native_symbol'],
            'rpc_url' => rexSignerReliableRpcUrl($slug, $row['rpc_url']),
            'explorer_url' => $row['explorer_url'],
            'environment' => (string) $row['environment'],
            'chain_family' => (string) ($row['chain_family'] ?? 'evm'),
            'claim_enabled' => (int) ($row['claim_enabled'] ?? 0),
            'token_support_enabled' => (int) ($row['token_support_enabled'] ?? 0),
            'is_enabled' => (int) $row['is_enabled'],
            'logo_key' => $meta['logo_key'],
            'logo_url' => $meta['logo_url'],
            'wallet_address' => $wallet_address,
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
