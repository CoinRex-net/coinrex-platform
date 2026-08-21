const fs = require('fs');
const path = require('path');
const { Contract, formatEther, formatUnits } = require('ethers');

const ERC20_ABI = [
  'function balanceOf(address owner) view returns (uint256)',
  'function decimals() view returns (uint8)',
  'function symbol() view returns (string)',
];

const onchainBalanceCache = new Map();
const BALANCE_CACHE_TTL_MS = 15000;
const NETWORK_DISPLAY = {
  polygon: { nativeName: 'Polygon Gas', logoKey: 'polygon' },
  base: { nativeName: 'Base Gas', logoKey: 'base' },
  plasma: { nativeName: 'Plasma Gas', logoKey: 'plasma' },
};

function readDeploymentJson(relativePath) {
  const filePath = path.resolve(__dirname, '..', '..', relativePath);
  if (!fs.existsSync(filePath)) return {};
  try {
    const decoded = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    return decoded && typeof decoded === 'object' ? decoded : {};
  } catch (_) {
    return {};
  }
}

function deploymentForNetwork(networkSlug) {
  const slug = String(networkSlug || '').trim().toLowerCase();
  if (!slug) return {};

  const direct = readDeploymentJson(`deployments/${slug}-rex-token.json`);
  if (direct.contractAddress) {
    return direct;
  }

  if (slug === 'polygon') {
    const fallback = readDeploymentJson('deployments/polygon-amoy-rex-token.json');
    if (fallback.contractAddress) {
      return fallback;
    }
  }

  return {};
}

function cacheKey(networkSlug, walletAddress, tokenType) {
  return `${networkSlug}:${String(walletAddress || '').toLowerCase()}:${tokenType}`;
}

function getCachedBalance(key) {
  const entry = onchainBalanceCache.get(key);
  if (entry && Date.now() - entry.ts < BALANCE_CACHE_TTL_MS) {
    return entry.value;
  }
  return undefined;
}

function setCachedBalance(key, value) {
  onchainBalanceCache.set(key, { ts: Date.now(), value });
}

async function fetchNativeBalance(provider, walletAddress) {
  if (!walletAddress) return { balance_wei: '0', balance_formatted: '0.000', balance_status: 'no_address' };
  const balanceWei = await provider.getBalance(walletAddress);
  const formatted = formatEther(balanceWei);
  return {
    balance_wei: balanceWei.toString(),
    balance_formatted: Number(formatted).toFixed(6),
    balance_status: 'live',
  };
}

async function fetchErc20Balance(provider, contractAddress, walletAddress) {
  if (!walletAddress || !contractAddress) return { balance_wei: '0', balance_formatted: '0.00', balance_status: 'no_contract' };
  const contract = new Contract(contractAddress, ERC20_ABI, provider);
  const [balanceWeiRaw, decimalsRaw] = await Promise.all([
    contract.balanceOf(walletAddress),
    contract.decimals(),
  ]);
  const decimals = Number(decimalsRaw);
  const balanceWei = balanceWeiRaw.toString();
  const formatted = formatUnits(balanceWeiRaw, decimals);
  return {
    balance_wei: balanceWei,
    balance_formatted: formatted,
    decimals,
    balance_status: 'live',
  };
}

function createAssetService({ config, db, auth, realtime, providers, jsonOk }) {
  async function health(_req, res) {
    await db.query('SELECT 1');
    jsonOk(res, { service: 'rexlink-node', public_api_url: config.publicApiUrl });
  }

  async function realtimeAuth(req, res) {
    const actor = await auth.requireUserActor(req);
    jsonOk(res, { ws_url: realtime.wsUrl(), token: auth.makeRealtimeToken(actor) });
  }

  async function networks(_req, res) {
    const rows = await db.query(`SELECT slug, name, chain_id, native_symbol, rpc_url, explorer_url, environment, is_enabled FROM rex_signer_networks WHERE is_enabled = 1 ORDER BY sort_order ASC`);
    jsonOk(res, { networks: rows });
  }

  async function assets(req, res) {
    const walletAddress = String(req.query.wallet_address || '').trim() || null;
    const networks = await db.query(`SELECT slug, name, chain_id, native_symbol, rpc_url, explorer_url, environment, is_enabled FROM rex_signer_networks WHERE is_enabled = 1 ORDER BY sort_order ASC`);

    const enriched = [];
    for (const network of networks) {
      const providerList = providers.getAllForNetwork(String(network.slug));
      const provider = providerList[0] || null;
      let nativeBalance = { balance_wei: '0', balance_formatted: '0.000', balance_status: 'unavailable' };
      const networkMeta = NETWORK_DISPLAY[String(network.slug)] || { nativeName: `${network.name || 'Network'} Gas`, logoKey: 'network' };

      if (walletAddress && providerList.length > 0) {
        const nativeKey = cacheKey(network.slug, walletAddress, 'native');
        const cachedNative = getCachedBalance(nativeKey);
        if (cachedNative !== undefined) {
          nativeBalance = cachedNative;
        } else {
          for (const candidateProvider of providerList) {
            try {
              nativeBalance = await fetchNativeBalance(candidateProvider, walletAddress);
              setCachedBalance(nativeKey, nativeBalance);
              break;
            } catch (_) {
              nativeBalance = { balance_wei: '0', balance_formatted: '0.000', balance_status: 'rpc_error' };
            }
          }
        }
      }

      const tokens = [
        {
          symbol: network.native_symbol || 'POL',
          name: networkMeta.nativeName,
          decimals: 18,
          assetType: 'native',
          logoKey: networkMeta.logoKey,
          sendEnabled: true,
          receiveEnabled: true,
          priceStatus: 'unavailable',
          balance: nativeBalance.balance_formatted,
          balancePlaceholder: nativeBalance.balance_formatted,
          balance_wei: nativeBalance.balance_wei,
          balance_status: nativeBalance.balance_status,
        },
      ];

      enriched.push({
        ...network,
        chainId: network.chain_id,
        nativeSymbol: network.native_symbol,
        rpcUrl: network.rpc_url,
        explorerUrl: network.explorer_url,
        wallet_address: walletAddress,
        logoKey: networkMeta.logoKey,
        tokens,
      });
    }

    jsonOk(res, {
      networks: enriched,
      market_prices: {},
    });
  }

  async function externalHistory(req, res) {
    const walletAddress = String(req.query.wallet_address || '').trim() || null;
    const networkSlug = String(req.query.network_slug || config.network.slug);
    if (!walletAddress) return jsonOk(res, { history: [], items: [] });

    const externalHistory = await providers.fetchExternalHistory(networkSlug, walletAddress);
    jsonOk(res, { history: externalHistory, items: externalHistory });
  }

  return {
    health,
    realtimeAuth,
    networks,
    assets,
    externalHistory,
  };
}

module.exports = createAssetService;
