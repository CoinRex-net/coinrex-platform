const fs = require('fs');
const path = require('path');

function loadEnvFile(filePath) {
  if (!fs.existsSync(filePath)) return;
  for (const line of fs.readFileSync(filePath, 'utf8').split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const index = trimmed.indexOf('=');
    if (index <= 0) continue;
    const key = trimmed.slice(0, index).trim();
    let value = trimmed.slice(index + 1).trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    if (!(key in process.env)) process.env[key] = value;
  }
}

const rootDir = path.resolve(__dirname, '..');
loadEnvFile(path.join(rootDir, '.env'));
loadEnvFile(path.join(rootDir, '.env.local'));

const publicBase = String(process.env.COINREX_PUBLIC_BASE_URL || 'http://localhost/coinrex').replace(/\/+$/, '');
const apiPort = Number(process.env.REXLINK_API_PORT || 18083);

module.exports = {
  rootDir,
  port: apiPort,
  publicApiUrl: String(process.env.REXLINK_PUBLIC_API_URL || publicBase.replace(/\/coinrex\/?$/, '') + `:${apiPort}`).replace(/\/+$/, ''),
  phpBaseUrl: publicBase,
  sessionSavePath: process.env.COINREX_SESSION_SAVE_PATH || path.join(rootDir, 'cache', 'sessions'),
  db: {
    host: process.env.COINREX_DB_HOST || 'localhost',
    database: process.env.COINREX_DB_NAME || 'koinrex',
    user: process.env.COINREX_DB_USER || 'root',
    password: process.env.COINREX_DB_PASS || '',
    waitForConnections: true,
    connectionLimit: 10,
    charset: 'utf8mb4',
  },
  realtimeSecret: process.env.COINREX_REALTIME_SECRET || process.env.COINREX_ENCRYPTION_KEY || process.env.COINREX_CSRF_KEY || 'coinrex-dev-realtime-secret',
  claimSignerPrivateKey: process.env.REX_CLAIM_SIGNER_PRIVATE_KEY || process.env.POLYGON_AMOY_PRIVATE_KEY || '',
  polygonRpcUrl: process.env.POLYGON_MAINNET_RPC_URL || process.env.POLYGON_AMOY_RPC_URL || 'https://rpc-amoy.polygon.technology',
  network: {
    slug: String(process.env.REXLINK_NETWORK_SLUG || 'polygon'),
    name: String(process.env.REXLINK_NETWORK_NAME || 'Polygon'),
    chainId: Number(process.env.REXLINK_NETWORK_CHAIN_ID || 137),
    nativeSymbol: String(process.env.REXLINK_NETWORK_NATIVE_SYMBOL || 'POL'),
  },
  testingMode: /^(1|true|yes|on)$/i.test(String(process.env.COINREX_TESTING_MODE || 'false')),
  claimMinimumRex: Number(process.env.REWARD_CLAIM_MINIMUM_REX || 100),
};
