const http = require('http');
const express = require('express');
const QRCode = require('qrcode');
const { JsonRpcProvider } = require('ethers');
const config = require('./config');
const db = require('./db');
const auth = require('./auth');
const realtime = require('./realtime');
const claims = require('./claims');
const createAsyncRoute = require('./lib/async-route');
const { sessionPayload, approvalPayload } = require('./lib/payloads');
const createMaintenanceService = require('./services/maintenance-service');
const createPairingService = require('./services/pairing-service');
const createAssetService = require('./services/asset-service');
const createApprovalService = require('./services/approval-service');
const createAuthSessionService = require('./services/auth-session-service');
const createClaimMonitorService = require('./services/claim-monitor-service');
const createProviderFactory = require('./services/provider-factory');
const registerRoutes = require('./routes/register-routes');
const {
  jsonOk,
  jsonError,
  sha256,
  randomToken,
  pairCode,
  normalizePairCode,
  formatPairCode,
  clampDuration,
  normalizeWallet,
} = require('./util');

const app = express();
const providers = createProviderFactory({ config, db });
const provider = new JsonRpcProvider(config.polygonRpcUrl, config.network.chainId);
const asyncRoute = createAsyncRoute(jsonError);

app.use(express.json({ limit: '512kb' }));
app.use(express.urlencoded({ extended: true }));
app.use((req, res, next) => {
  const origin = req.headers.origin || '';
  if (origin) {
    res.setHeader('Access-Control-Allow-Origin', origin);
    res.setHeader('Vary', 'Origin');
  }
  res.setHeader('Access-Control-Allow-Credentials', 'true');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-REX-SIGNER-SESSION, X-CoinRex-Web-Actor');
  res.setHeader('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  if (req.method === 'OPTIONS') return res.status(204).end();
  next();
});

const maintenance = createMaintenanceService({ db });
const pairingService = createPairingService({
  config,
  db,
  auth,
  realtime,
  QRCode,
  maintenance,
  sessionPayload,
  jsonOk,
  jsonError,
  sha256,
  randomToken,
  pairCode,
  normalizePairCode,
  formatPairCode,
  clampDuration,
  normalizeWallet,
});
const assetService = createAssetService({
  config,
  db,
  auth,
  realtime,
  providers,
  jsonOk,
});
const approvalService = createApprovalService({
  config,
  db,
  auth,
  claims,
  realtime,
  maintenance,
  approvalPayload,
  jsonOk,
  jsonError,
  normalizeWallet,
});
const authSessionService = createAuthSessionService({
  config,
  db,
  auth,
  jsonOk,
});
const claimMonitorService = createClaimMonitorService({
  db,
  auth,
  claims,
  realtime,
  provider,
  jsonOk,
  jsonError,
});

registerRoutes(app, {
  health: assetService.health,
  createPairing: pairingService.createPairing,
  createReviewPairing: pairingService.createReviewPairing,
  reviewWalletStatus: pairingService.reviewWalletStatus,
  reviewPairingStatus: pairingService.reviewPairingStatus,
  pairingQr: pairingService.pairingQr,
  completePairing: pairingService.completePairing,
  cancelPairing: pairingService.cancelPairing,
  listSessions: pairingService.listSessions,
  revokeSession: pairingService.revokeSession,
  realtimeAuth: assetService.realtimeAuth,
  networks: assetService.networks,
  assets: assetService.assets,
  externalHistory: assetService.externalHistory,
  createApprovalRequest: approvalService.createApprovalRequest,
  createClaimApproval: approvalService.createClaimApproval,
  listApprovalRequests: approvalService.listApprovalRequests,
  getApprovalStatus: approvalService.getApprovalStatus,
  decideApproval: approvalService.decideApproval,
  completeClaimTx: claimMonitorService.completeClaimTx,
  loginFromSession: authSessionService.loginFromSession,
}, asyncRoute);

const server = http.createServer(app);
realtime.attach(server);

db.ensureSchema().then(async () => {
  await providers.init().catch(() => {});
  setInterval(() => claimMonitorService.watchPending().catch(() => {}), 5000);
  server.listen(config.port, config.host, () => {
    console.log(`RexLink API listening on ${config.publicApiUrl} via ${config.host}:${config.port}`);
  });
}).catch((error) => {
  console.error(error);
  process.exit(1);
});
