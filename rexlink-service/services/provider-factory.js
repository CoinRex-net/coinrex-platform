const http = require('http');
const https = require('https');
const { JsonRpcProvider } = require('ethers');

const FALLBACK_RPC_URLS = {
  polygon: [
    'https://polygon-bor-rpc.publicnode.com',
    'https://rpc.ankr.com/polygon',
    'https://1rpc.io/matic',
    'https://polygon.llamarpc.com',
  ],
  base: [
    'https://base-rpc.publicnode.com',
    'https://base.llamarpc.com',
    'https://1rpc.io/base',
  ],
  plasma: [],
};

const ETHERSCAN_V2_API_URL = 'https://api.etherscan.io/v2/api';
const EXPLORER_CHAIN_IDS = {
  polygon: 137,
  base: 8453,
};

function rpcCandidatesForNetwork(slug, primaryRpcUrl = '') {
  const networkSlug = String(slug || '').toLowerCase();
  return Array.from(new Set([
    String(primaryRpcUrl || '').trim(),
    ...(FALLBACK_RPC_URLS[networkSlug] || []),
  ].filter(Boolean)));
}

function explorerApiForSlug(slug, chainId = 0) {
  const networkSlug = String(slug || '').trim().toLowerCase();
  const resolvedChainId = Number(chainId || EXPLORER_CHAIN_IDS[networkSlug] || 0);
  if (!resolvedChainId) {
    return null;
  }

  return {
    url: ETHERSCAN_V2_API_URL,
    chainId: resolvedChainId,
  };
}

function requestJson(url) {
  return new Promise((resolve, reject) => {
    const target = String(url || '').trim();
    if (!target) {
      resolve({});
      return;
    }

    const client = target.startsWith('https://') ? https : http;
    const request = client.get(target, {
      headers: {
        Accept: 'application/json',
        'User-Agent': 'RexLink/1.0',
      },
      timeout: 9000,
    }, (response) => {
      let raw = '';
      response.setEncoding('utf8');
      response.on('data', (chunk) => {
        raw += chunk;
      });
      response.on('end', () => {
        if (response.statusCode && response.statusCode >= 400) {
          reject(new Error(`Explorer request failed with status ${response.statusCode}`));
          return;
        }

        try {
          resolve(raw ? JSON.parse(raw) : {});
        } catch (error) {
          reject(error);
        }
      });
    });

    request.on('timeout', () => {
      request.destroy(new Error('Explorer request timed out.'));
    });
    request.on('error', reject);
  });
}

function createProviderFactory({ config, db }) {
  const providers = new Map();
  const networkMeta = new Map();
  let defaultProvider = null;

  async function init() {
    const rows = await db.query(
      `SELECT slug, rpc_url, chain_id FROM rex_signer_networks WHERE is_enabled = 1`
    );
    for (const row of rows) {
      const rpcCandidates = rpcCandidatesForNetwork(row.slug, row.rpc_url);
      if (rpcCandidates.length === 0) continue;
      const chainId = Number(row.chain_id || config.network.chainId);
      networkMeta.set(String(row.slug), {
        chainId,
      });
      const providerList = rpcCandidates.map((rpcUrl) => new JsonRpcProvider(rpcUrl, chainId));
      providers.set(String(row.slug), providerList);
      if (String(row.slug) === config.network.slug && providerList[0]) {
        defaultProvider = providerList[0];
      }
    }

    if (!defaultProvider && !providers.has(config.network.slug)) {
      const providerList = rpcCandidatesForNetwork(config.network.slug, config.polygonRpcUrl)
        .map((rpcUrl) => new JsonRpcProvider(rpcUrl, config.network.chainId));
      if (providerList[0]) {
        defaultProvider = providerList[0];
        providers.set(config.network.slug, providerList);
      }
    }
  }

  function getDefault() {
    return defaultProvider || (providers.get(config.network.slug) || [])[0] || null;
  }

  function getForNetwork(slug) {
    return (providers.get(String(slug)) || [])[0] || getDefault() || null;
  }

  function getAllForNetwork(slug) {
    const providerList = providers.get(String(slug)) || [];
    return providerList.length ? providerList : (getDefault() ? [getDefault()] : []);
  }

  async function fetchExternalHistory(networkSlug, walletAddress) {
    if (!walletAddress) return [];

    const networkSlugKey = String(networkSlug || '').trim().toLowerCase();
    const explorerApi = explorerApiForSlug(networkSlugKey, networkMeta.get(networkSlugKey)?.chainId || 0);
    if (!explorerApi?.url || !explorerApi?.chainId) return [];

    const explorerKey = String(process.env.ETHERSCAN_API_KEY || process.env.POLYGONSCAN_API_KEY || process.env.EXPLORER_API_KEY || '');
    const commonParams = {
      chainid: String(explorerApi.chainId),
      module: 'account',
      address: walletAddress,
      startblock: 0,
      endblock: 99999999,
      page: 1,
      offset: 1000,
      sort: 'desc',
    };
    if (explorerKey) commonParams.apikey = explorerKey;

    try {
      const [normalData, tokenData] = await Promise.all([
        requestJson(`${explorerApi.url}?${new URLSearchParams({ ...commonParams, action: 'txlist' })}`).catch(() => ({})),
        requestJson(`${explorerApi.url}?${new URLSearchParams({ ...commonParams, action: 'tokentx' })}`).catch(() => ({})),
      ]);
      const normalRows = Array.isArray(normalData.result) ? normalData.result : [];
      const tokenRows = Array.isArray(tokenData.result) ? tokenData.result : [];

      const cutoff = Math.floor(Date.now() / 1000) - (7 * 24 * 60 * 60);
      const tokenHashes = new Set(tokenRows.map((row) => (row.hash || '').toLowerCase()).filter(Boolean));
      const nativeRows = normalRows.filter((row) => (Number(row.timeStamp || 0) >= cutoff) && !tokenHashes.has((row.hash || '').toLowerCase()));
      const filteredTokenRows = tokenRows.filter((row) => Number(row.timeStamp || 0) >= cutoff);

      const history = [
        ...nativeRows.map((tx) => ({
          hash: tx.hash || '',
          fromAddr: tx.from || '',
          toAddr: tx.to || '',
          value: tx.value || '0',
          blockNumber: tx.blockNumber || 0,
          timestamp: tx.timeStamp || null,
          status: String(tx.txreceipt_status || '1') === '0' ? 'failed' : 'confirmed',
          gasPrice: tx.gasPrice || '0',
          gasUsed: tx.gasUsed || '0',
        })),
        ...filteredTokenRows.map((tx) => ({
          hash: tx.hash || '',
          fromAddr: tx.from || '',
          toAddr: tx.to || '',
          value: tx.value || '0',
          blockNumber: tx.blockNumber || 0,
          timestamp: tx.timeStamp || null,
          status: String(tx.txreceipt_status || '1') === '0' ? 'failed' : 'confirmed',
          gasPrice: tx.gasPrice || '0',
          gasUsed: tx.gasUsed || '0',
          tokenSymbol: tx.tokenSymbol || tx.tokenName || '',
          tokenDecimal: tx.tokenDecimal || '18',
        })),
      ];

      return history.sort((a, b) => Number(b.timestamp || 0) - Number(a.timestamp || 0)).slice(0, 100);
    } catch (_) {
      return [];
    }
  }

  return {
    init,
    getDefault,
    getForNetwork,
    getAllForNetwork,
    fetchExternalHistory,
  };
}

module.exports = createProviderFactory;
