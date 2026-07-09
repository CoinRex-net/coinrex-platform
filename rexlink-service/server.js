const http = require('http');
const express = require('express');
const QRCode = require('qrcode');
const { JsonRpcProvider } = require('ethers');
const config = require('./config');
const db = require('./db');
const auth = require('./auth');
const realtime = require('./realtime');
const claims = require('./claims');
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
const provider = new JsonRpcProvider(config.polygonRpcUrl, config.network.chainId);

app.use(express.json({ limit: '512kb' }));
app.use(express.urlencoded({ extended: true }));
app.use((req, res, next) => {
  const origin = req.headers.origin || '';
  if (origin) {
    res.setHeader('Access-Control-Allow-Origin', origin);
    res.setHeader('Vary', 'Origin');
  }
  res.setHeader('Access-Control-Allow-Credentials', 'true');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-REX-SIGNER-SESSION');
  res.setHeader('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  if (req.method === 'OPTIONS') return res.status(204).end();
  next();
});

function asyncRoute(fn) {
  return (req, res) => fn(req, res).catch((error) => jsonError(res, error.status || 422, error.message));
}

async function expireOldRows() {
  await db.query(`UPDATE rex_signer_pairing_codes SET status = 'expired' WHERE status = 'pending' AND expires_at <= NOW()`);
  await db.query(`UPDATE rex_signer_sessions SET status = 'expired' WHERE status = 'active' AND expires_at <= NOW()`);
  await db.query(`UPDATE rex_signer_approval_requests SET status = 'expired' WHERE status = 'pending' AND expires_at <= NOW()`);
}

function sessionPayload(row) {
  if (!row) return null;
  return {
    id: Number(row.id),
    user_id: Number(row.user_id),
    device_name: row.device_name,
    wallet_address: row.wallet_address || null,
    status: row.status,
    expires_at: row.expires_at,
    expires_at_unix: row.expires_at ? Math.floor(new Date(row.expires_at).getTime() / 1000) : null,
    remaining_seconds: row.remaining_seconds !== undefined ? Math.max(0, Number(row.remaining_seconds)) : null,
    last_seen_at: row.last_seen_at,
    created_at: row.created_at,
  };
}

function buildQrPayload(displayCode, purpose, duration, expiresAt, extra = {}) {
  return {
    type: 'coinrex.rex_signer.pairing',
    version: 2,
    code: displayCode,
    purpose,
    base_url: config.publicApiUrl,
    api_base_url: config.publicApiUrl,
    dapp_name: 'CoinRex',
    dapp_url: config.phpBaseUrl,
    network_slug: config.network.slug,
    network_name: config.network.name,
    chain_id: config.network.chainId,
    native_symbol: config.network.nativeSymbol,
    requested_duration_minutes: duration,
    expires_at: expiresAt,
    display_context: {
      dapp_name: 'CoinRex',
      website: new URL(config.phpBaseUrl).host,
      dapp_url: config.phpBaseUrl,
      network: config.network.name,
      network_slug: config.network.slug,
      chain_id: config.network.chainId,
      native_symbol: config.network.nativeSymbol,
      wallet: extra.requested_wallet_address || 'Not provided by dApp',
      contract: 'Not provided by dApp',
      amount: 'Not provided by dApp',
      fee: 'Fee estimate unavailable',
      expires_at: expiresAt,
      warnings: [],
    },
    trust_context: { source: 'node_rexlink', verified: true, network_known: true, warnings: [] },
    ...extra,
  };
}

async function findOrCreateWalletUser(conn, walletAddress, referralCode = '') {
  const [existing] = await conn.execute('SELECT * FROM users WHERE wallet_address = ? LIMIT 1', [walletAddress]);
  if (existing[0]) return existing[0];
  const base = walletAddress.replace(/^0x/, '').slice(0, 10);
  const username = `rex${base}${Math.floor(Math.random() * 9999)}`;
  const referral = `RX${Math.random().toString(36).slice(2, 10).toUpperCase()}`;
  let referredBy = null;
  if (referralCode) {
    const [refs] = await conn.execute('SELECT id FROM users WHERE referral_code = ? LIMIT 1', [String(referralCode).toUpperCase()]);
    if (refs[0]) referredBy = refs[0].id;
  }
  const [insert] = await conn.execute(
    `INSERT INTO users
     (full_name, email, password, auth_provider, username, referral_code, referred_by, rex_balance, total_rex_earned, status, email_verified, wallet_address, wallet_verified_at)
     VALUES (?, NULL, NULL, 'rex_signer', ?, ?, ?, 0, 0, 'active', 0, ?, NOW())`,
    [`REX User ${walletAddress.slice(0, 6)}...${walletAddress.slice(-4)}`, username, referral, referredBy, walletAddress]
  );
  const [fresh] = await conn.execute('SELECT * FROM users WHERE id = ? LIMIT 1', [insert.insertId]);
  return fresh[0];
}

app.get('/health', asyncRoute(async (_req, res) => {
  await db.query('SELECT 1');
  jsonOk(res, { service: 'rexlink-node', public_api_url: config.publicApiUrl });
}));

app.post('/api/rex-signer/create_pairing.php', asyncRoute(async (req, res) => {
  await expireOldRows();
  const purpose = String(req.body.purpose || 'claim').toLowerCase() === 'auth' ? 'auth' : 'claim';
  const duration = clampDuration(req.body.duration_minutes || 10);
  const actor = purpose === 'claim' ? await auth.requireUserActor(req) : await auth.webActor(req);
  const userId = actor?.user_id || null;

  if (userId) {
    const active = await db.one(
      `SELECT *, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds
       FROM rex_signer_sessions WHERE user_id = ? AND status = 'active' AND expires_at > NOW()
       ORDER BY id DESC LIMIT 1`,
      [userId]
    );
    if (active) return jsonOk(res, { message: 'RexLink is already connected.', already_connected: true, session: sessionPayload(active) });
  }

  const code = pairCode();
  const displayCode = formatPairCode(code);
  const expiresAt = new Date(Date.now() + 300000).toISOString().slice(0, 19).replace('T', ' ');
  const [insert] = await db.pool.execute(
    `INSERT INTO rex_signer_pairing_codes
     (user_id, code_hash, display_code, pairing_purpose, referral_code, requested_duration_minutes, expires_at, device_fingerprint, ip_address, user_agent)
     VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), ?, ?, ?)`,
    [
      userId,
      sha256(code),
      displayCode,
      purpose,
      purpose === 'auth' ? String(req.body.referral_code || '').toUpperCase() || null : null,
      duration,
      purpose === 'auth' ? String(req.body.device_fingerprint || '').slice(0, 255) : null,
      req.ip,
      req.headers['user-agent'] || null,
    ]
  );
  const qrPayload = buildQrPayload(displayCode, purpose, duration, expiresAt);
  jsonOk(res, {
    message: 'Pairing code created.',
    pairing_id: insert.insertId,
    display_code: displayCode,
    expires_in_seconds: 300,
    requested_duration_minutes: duration,
    api_base_url: config.publicApiUrl,
    qr_payload: qrPayload,
    display_context: qrPayload.display_context,
    trust_context: qrPayload.trust_context,
  }, 201);
}));

app.get('/api/rex-signer/pairing_qr.php', asyncRoute(async (req, res) => {
  const payload = String(req.query.payload || '');
  if (!payload || payload.length > 3000) return jsonError(res, 422, 'Valid QR payload is required.');
  const svg = await QRCode.toString(payload, { type: 'svg', width: 220, margin: 2 });
  res.setHeader('Content-Type', 'image/svg+xml; charset=utf-8');
  res.end(svg);
}));

app.post('/api/rex-signer/complete_pairing.php', asyncRoute(async (req, res) => {
  await expireOldRows();
  const code = normalizePairCode(req.body.code || '');
  if (!/^\d{6}$/.test(code)) return jsonError(res, 422, 'Enter the 6-digit pairing code from CoinRex.');
  const wallet = normalizeWallet(req.body.wallet_address || '');
  const deviceName = String(req.body.device_name || 'RexLink').slice(0, 120);
  const result = await db.tx(async (conn) => {
    const [rows] = await conn.execute(
      `SELECT * FROM rex_signer_pairing_codes WHERE code_hash = ? AND status = 'pending' AND expires_at > NOW() LIMIT 1 FOR UPDATE`,
      [sha256(code)]
    );
    const pairing = rows[0];
    if (!pairing) throw new Error('Pairing code is invalid or expired.');
    let userId = pairing.user_id ? Number(pairing.user_id) : null;
    const purpose = String(pairing.pairing_purpose || 'claim');
    if (purpose === 'auth' && !userId) {
      const user = await findOrCreateWalletUser(conn, wallet, pairing.referral_code || '');
      userId = Number(user.id);
    }
    if (!userId) throw new Error('Pairing owner could not be resolved.');
    const [owners] = await conn.execute('SELECT id, wallet_address FROM users WHERE wallet_address = ? AND id <> ? LIMIT 1', [wallet, userId]);
    if (owners[0]) throw new Error('This wallet is already linked to another CoinRex account.');
    const [users] = await conn.execute('SELECT id, wallet_address FROM users WHERE id = ? LIMIT 1 FOR UPDATE', [userId]);
    const user = users[0];
    if (!user) throw new Error('Pairing owner could not be resolved.');
    const currentWallet = String(user.wallet_address || '').toLowerCase();
    if (purpose === 'claim' && currentWallet && currentWallet !== wallet) throw new Error('This CoinRex account is already linked to a different wallet.');
    if (!currentWallet || purpose === 'auth') {
      await conn.execute(
        `UPDATE users SET wallet_address = ?, wallet_verified_at = COALESCE(wallet_verified_at, NOW()), auth_provider = CASE WHEN auth_provider = 'email' THEN 'hybrid' ELSE auth_provider END, updated_at = NOW() WHERE id = ?`,
        [wallet, userId]
      );
    }
    await conn.execute(`UPDATE rex_signer_sessions SET status = 'revoked', revoked_at = NOW(), revoke_reason = 'Replaced by a new RexLink session' WHERE user_id = ? AND status = 'active'`, [userId]);
    const token = randomToken(32);
    const duration = clampDuration(pairing.requested_duration_minutes || 10);
    const [insert] = await conn.execute(
      `INSERT INTO rex_signer_sessions (user_id, pairing_code_id, session_token_hash, device_name, wallet_address, expires_at, last_seen_at, ip_address, user_agent)
       VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ${duration} MINUTE), NOW(), ?, ?)`,
      [userId, pairing.id, sha256(token), deviceName, wallet, req.ip, req.headers['user-agent'] || null]
    );
    await conn.execute(`UPDATE rex_signer_pairing_codes SET status = 'completed', completed_at = NOW(), completed_session_id = ? WHERE id = ?`, [insert.insertId, pairing.id]);
    const [sessionRows] = await conn.execute(`SELECT *, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds FROM rex_signer_sessions WHERE id = ?`, [insert.insertId]);
    return { token, session: sessionRows[0], userId };
  });
  realtime.publish('session.connected', { user_id: result.userId, session_id: result.session.id, status: 'active', wallet_address: wallet, session: sessionPayload(result.session) });
  jsonOk(res, { message: 'RexLink paired successfully.', session_token: result.token, session: sessionPayload(result.session) }, 201);
}));

app.post('/api/rex-signer/cancel_pairing.php', asyncRoute(async (req, res) => {
  const code = normalizePairCode(req.body.code || '');
  await db.query(`UPDATE rex_signer_pairing_codes SET status = 'revoked' WHERE code_hash = ? AND status = 'pending'`, [sha256(code)]);
  jsonOk(res, { message: 'Pairing cancelled.' });
}));

app.get('/api/rex-signer/sessions.php', asyncRoute(async (req, res) => {
  await expireOldRows();
  const actor = await auth.requireUserActor(req);
  const rows = await db.query(
    `SELECT *, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds
     FROM rex_signer_sessions WHERE user_id = ?
     ORDER BY FIELD(status, 'active', 'expired', 'revoked'), created_at DESC LIMIT 25`,
    [actor.user_id]
  );
  const sessions = rows.map(sessionPayload);
  const current = sessions.find((s) => s.status === 'active' && Number(s.remaining_seconds || 0) > 0) || null;
  jsonOk(res, { active_session_count: current ? 1 : 0, session_state: current ? 'active' : 'none', server_time_unix: Math.floor(Date.now() / 1000), current_session: current, sessions });
}));

app.post('/api/rex-signer/revoke_session.php', asyncRoute(async (req, res) => {
  const actor = await auth.requireUserActor(req);
  const sessionId = Number(req.body.session_id || actor.session_id || 0);
  await db.query(`UPDATE rex_signer_sessions SET status = 'revoked', revoked_at = NOW(), revoke_reason = ? WHERE id = ? AND user_id = ? AND status = 'active'`, [String(req.body.reason || 'Revoked by user'), sessionId, actor.user_id]);
  realtime.publish('session.revoked', { user_id: actor.user_id, session_id: sessionId, status: 'revoked' });
  jsonOk(res, { message: 'Session revoked.', session_id: sessionId, session_state: 'revoked', revoked: true, server_time_unix: Math.floor(Date.now() / 1000) });
}));

app.get('/api/rex-signer/realtime_auth.php', asyncRoute(async (req, res) => {
  const actor = await auth.requireUserActor(req);
  jsonOk(res, { ws_url: realtime.wsUrl(), token: auth.makeRealtimeToken(actor) });
}));

app.get('/api/rex-signer/networks.php', asyncRoute(async (_req, res) => {
  const rows = await db.query(`SELECT slug, name, chain_id, native_symbol, rpc_url, explorer_url, environment, is_enabled FROM rex_signer_networks WHERE is_enabled = 1 ORDER BY sort_order ASC`);
  jsonOk(res, { networks: rows });
}));

app.get('/api/rex-signer/assets.php', asyncRoute(async (_req, res) => {
  const networks = await db.query(`SELECT slug, name, chain_id, native_symbol, rpc_url, explorer_url, environment, is_enabled FROM rex_signer_networks WHERE is_enabled = 1 ORDER BY sort_order ASC`);
  jsonOk(res, {
    networks: networks.map((network) => ({
      ...network,
      chainId: network.chain_id,
      nativeSymbol: network.native_symbol,
      rpcUrl: network.rpc_url,
      explorerUrl: network.explorer_url,
      tokens: String(network.slug) === config.network.slug ? [
        {
          symbol: 'REX',
          name: 'CoinRex Token',
          decimals: Number(claims.token.decimals || 18),
          assetType: 'erc20',
          contractAddress: claims.token.contractAddress,
          sendEnabled: true,
          receiveEnabled: true,
          priceUsd: 0,
          priceStatus: 'testnet_unpriced',
          balancePlaceholder: '0.00',
        },
        {
          symbol: network.native_symbol || 'POL',
          name: 'Polygon Gas',
          decimals: 18,
          assetType: 'native',
          sendEnabled: true,
          receiveEnabled: true,
          priceStatus: 'unavailable',
          balancePlaceholder: '0.000',
        },
      ] : [],
    })),
    market_prices: {},
  });
}));

app.get('/api/rex-signer/external_history.php', asyncRoute(async (_req, res) => {
  jsonOk(res, { history: [], items: [] });
}));

app.post('/api/rex-signer/create_approval_request.php', asyncRoute(async (req, res) => {
  const actor = await auth.requireUserActor(req);
  const active = await db.one(`SELECT * FROM rex_signer_sessions WHERE user_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY id DESC LIMIT 1`, [actor.user_id]);
  if (!active) return jsonError(res, 409, 'Connect RexLink before creating approval requests.');
  const requestType = ['claim', 'send', 'message'].includes(String(req.body.request_type || '').toLowerCase())
    ? String(req.body.request_type).toLowerCase()
    : 'message';
  const payload = req.body.payload && typeof req.body.payload === 'object' ? req.body.payload : {};
  const [insert] = await db.pool.execute(
    `INSERT INTO rex_signer_approval_requests
     (user_id, session_id, network_slug, request_type, title, summary, amount, fee_estimate, payload_json, wallet_address, expires_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))`,
    [
      actor.user_id,
      active.id,
      String(req.body.network_slug || config.network.slug),
      requestType,
      String(req.body.title || 'CoinRex approval request').slice(0, 160),
      String(req.body.summary || 'Approve this request in RexLink.').slice(0, 255),
      req.body.amount ? String(req.body.amount).slice(0, 80) : null,
      req.body.fee_estimate ? String(req.body.fee_estimate).slice(0, 80) : null,
      JSON.stringify({ ...payload, dapp_name: 'CoinRex', dapp_url: config.phpBaseUrl, api_base_url: config.publicApiUrl }),
      active.wallet_address || null,
      Math.max(1, Math.min(Number(req.body.expires_minutes || 10), 60)),
    ]
  );
  realtime.publish('approval.created', { user_id: actor.user_id, session_id: active.id, request_id: insert.insertId, status: 'pending', request_type: requestType, title: req.body.title || 'CoinRex approval request' });
  jsonOk(res, { message: 'Approval request created.', request_id: insert.insertId, status: 'pending' }, 201);
}));

app.post('/api/rex-signer/create_claim_approval.php', asyncRoute(async (req, res) => {
  await expireOldRows();
  const actor = await auth.requireUserActor(req);
  const elig = await claims.eligibility(actor.user_id);
  if (!elig.eligible) return jsonError(res, 422, elig.message);
  const active = await db.one(`SELECT * FROM rex_signer_sessions WHERE user_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY id DESC LIMIT 1`, [actor.user_id]);
  if (!active) return jsonError(res, 409, 'Connect RexLink before requesting claim approval.');
  const amount = Number(req.body.claim_amount || elig.balance);
  if (amount <= 0 || amount > Number(elig.balance)) return jsonError(res, 422, 'Claim amount cannot exceed your available REX balance.');
  const pending = await db.one(`SELECT * FROM rex_signer_approval_requests WHERE user_id = ? AND request_type = 'claim' AND status = 'pending' AND expires_at > NOW() ORDER BY id DESC LIMIT 1`, [actor.user_id]);
  if (pending) return jsonOk(res, { message: 'Claim approval is already pending.', request_id: pending.id, status: 'pending' });
  const payload = {
    action: 'generate_claim',
    dapp_name: 'CoinRex',
    dapp_url: config.phpBaseUrl,
    base_url: config.publicApiUrl,
    api_base_url: config.publicApiUrl,
    network_slug: config.network.slug,
    network_name: config.network.name,
    claim_amount: amount.toFixed(8),
    amount: `${amount.toFixed(8)} REX`,
    fee_estimate: `${claims.distributor.claimFeeFormatted || '0.01'} POL`,
    wallet_address: active.wallet_address,
    contract_address: claims.distributor.contractAddress,
    chain_id: Number(claims.distributor.chainId || config.network.chainId),
  };
  const [insert] = await db.pool.execute(
    `INSERT INTO rex_signer_approval_requests
     (user_id, session_id, network_slug, request_type, title, summary, amount, fee_estimate, payload_json, wallet_address, expires_at, tx_status)
     VALUES (?, ?, ?, 'claim', 'Approve REX Claim', ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'approval_pending')`,
    [actor.user_id, active.id, config.network.slug, `${amount.toFixed(8)} REX`, payload.fee_estimate, JSON.stringify(payload), active.wallet_address]
  );
  realtime.publish('approval.created', { user_id: actor.user_id, session_id: active.id, request_id: insert.insertId, status: 'pending', request_type: 'claim', title: 'Approve REX Claim', amount: `${amount.toFixed(8)} REX`, fee_estimate: payload.fee_estimate, payload });
  jsonOk(res, { message: 'Claim approval request created.', request_id: insert.insertId, status: 'pending', amount: amount.toFixed(8), fee_estimate: payload.fee_estimate, expires_in_seconds: 600 }, 201);
}));

function approvalPayload(row) {
  const payload = row.payload_json ? JSON.parse(row.payload_json) : null;
  const result = row.result_json ? JSON.parse(row.result_json) : null;
  return {
    id: Number(row.id),
    user_id: Number(row.user_id),
    session_id: row.session_id ? Number(row.session_id) : null,
    network_slug: row.network_slug,
    request_type: row.request_type,
    title: row.title,
    summary: row.summary,
    amount: row.amount,
    fee_estimate: row.fee_estimate,
    payload,
    wallet_address: row.wallet_address || '',
    tx_hash: row.tx_hash || '',
    tx_status: row.tx_status || result?.tx_status || '',
    result,
    status: row.status,
    decision_note: row.decision_note || '',
    decided_at: row.decided_at || '',
    completed_at: row.completed_at || '',
    expires_at: row.expires_at || '',
    created_at: row.created_at || '',
    display_context: payload?.display_context || null,
    trust_context: payload?.trust_context || null,
  };
}

app.get('/api/rex-signer/approval_requests.php', asyncRoute(async (req, res) => {
  const actor = await auth.requireUserActor(req);
  const status = String(req.query.status || 'pending');
  const params = [actor.user_id];
  const filter = status !== 'all' ? 'AND status = ?' : '';
  if (status !== 'all') params.push(status);
  const rows = await db.query(`SELECT * FROM rex_signer_approval_requests WHERE user_id = ? ${filter} ORDER BY created_at DESC LIMIT 50`, params);
  jsonOk(res, { approval_requests: rows.map(approvalPayload) });
}));

app.get('/api/rex-signer/approval_status.php', asyncRoute(async (req, res) => {
  const actor = await auth.requireUserActor(req);
  const row = await db.one(`SELECT * FROM rex_signer_approval_requests WHERE id = ? AND user_id = ? LIMIT 1`, [Number(req.query.request_id || 0), actor.user_id]);
  if (!row) return jsonError(res, 404, 'Approval request was not found.');
  jsonOk(res, { approval_request: approvalPayload(row) });
}));

app.post('/api/rex-signer/approval_decision.php', asyncRoute(async (req, res) => {
  const actor = await auth.requireUserActor(req, { signerOnly: true });
  const requestId = Number(req.body.request_id || 0);
  const decision = String(req.body.decision || '').toLowerCase();
  if (!['approved', 'rejected'].includes(decision)) return jsonError(res, 422, 'Decision must be approved or rejected.');
  let result = null;
  const row = await db.tx(async (conn) => {
    const [rows] = await conn.execute(`SELECT * FROM rex_signer_approval_requests WHERE id = ? AND user_id = ? AND status = 'pending' AND expires_at > NOW() LIMIT 1 FOR UPDATE`, [requestId, actor.user_id]);
    const request = rows[0];
    if (!request) throw new Error('Pending approval request was not found.');
    if (decision === 'approved' && request.request_type === 'claim') {
      const wallet = normalizeWallet(actor.session.wallet_address || '');
      const payload = JSON.parse(request.payload_json || '{}');
      const snapshot = await claims.generateSnapshot(actor.user_id, payload.claim_amount, conn);
      result = await claims.signClaim(snapshot, wallet);
    }
    await conn.execute(
      `UPDATE rex_signer_approval_requests
       SET status = ?, session_id = ?, wallet_address = COALESCE(?, wallet_address), result_json = COALESCE(?, result_json),
           decision_note = ?, decided_at = NOW(), tx_status = CASE WHEN ? = 'approved' THEN 'snapshot_locked' ELSE tx_status END,
           completed_at = CASE WHEN ? = 'rejected' THEN NOW() ELSE completed_at END
       WHERE id = ? AND user_id = ?`,
      [decision, actor.session_id, result?.wallet_address || null, result ? JSON.stringify({ ...result, tx_status: 'pending', claim_snapshot_status: 'generated', ledger_status: 'locked' }) : null, String(req.body.note || ''), decision, decision, requestId, actor.user_id]
    );
    const [fresh] = await conn.execute('SELECT * FROM rex_signer_approval_requests WHERE id = ? LIMIT 1', [requestId]);
    return fresh[0];
  });
  realtime.publish('approval.updated', { user_id: actor.user_id, session_id: actor.session_id, request_id: requestId, status: decision, request_type: row.request_type, has_result: Boolean(result), decision_note: String(req.body.note || '') });
  jsonOk(res, { message: 'Approval request updated.', request_id: requestId, status: decision, result });
}));

app.post('/api/rex-signer/complete_claim_tx.php', asyncRoute(async (req, res) => {
  const actor = await auth.requireUserActor(req, { signerOnly: true });
  const requestId = Number(req.body.request_id || 0);
  const txHash = String(req.body.tx_hash || '').trim();
  const status = String(req.body.status || 'submitted').toLowerCase();
  if (!['submitted', 'confirmed', 'failed'].includes(status)) return jsonError(res, 422, 'Invalid transaction status.');
  const row = await db.one(`SELECT * FROM rex_signer_approval_requests WHERE id = ? AND user_id = ? AND request_type = 'claim' AND status = 'approved' LIMIT 1`, [requestId, actor.user_id]);
  if (!row) return jsonError(res, 404, 'Approved claim request was not found.');
  const result = row.result_json ? JSON.parse(row.result_json) : {};
  if (txHash) result.tx_hash = txHash;
  result.tx_status = status === 'failed' ? 'failed' : 'submitted';
  if (status === 'failed' && !txHash && result.snapshot_id) {
    await db.tx(async (conn) => {
      await claims.releaseSnapshot(Number(result.snapshot_id), actor.user_id, conn);
      result.claim_snapshot_status = 'expired';
      result.ledger_status = 'available';
      await conn.execute(`UPDATE rex_signer_approval_requests SET tx_status = 'tx_failed', tx_failed_at = NOW(), result_json = ?, completed_at = NOW() WHERE id = ?`, [JSON.stringify(result), requestId]);
    });
  } else {
    const txStatus = status === 'submitted' ? 'tx_broadcasted' : status === 'confirmed' ? 'tx_confirming' : 'tx_failed';
    await db.query(
      `UPDATE rex_signer_approval_requests SET tx_hash = COALESCE(NULLIF(?, ''), tx_hash), tx_status = ?, tx_submitted_at = COALESCE(tx_submitted_at, NOW()), result_json = ? WHERE id = ?`,
      [txHash, txStatus, JSON.stringify(result), requestId]
    );
    if (txHash) checkReceipt(requestId).catch(() => {});
  }
  realtime.publish('claim.tx.updated', { user_id: actor.user_id, session_id: row.session_id || actor.session_id, request_id: requestId, tx_status: status, tx_hash: txHash });
  jsonOk(res, { message: 'Claim transaction recorded.', request_id: requestId, tx_hash: txHash, tx_status: status });
}));

app.post('/api/rex-signer/auth/login_from_session.php', asyncRoute(async (req, res) => {
  const pairingId = Number(req.body.pairing_id || req.query.pairing_id || 0);
  if (!pairingId) return jsonOk(res, { status: 'none', message: 'No RexLink sign-in pairing is active.' });
  const row = await db.one(
    `SELECT pc.*, s.id AS session_id, s.user_id AS session_user_id, s.wallet_address AS session_wallet_address,
            s.status AS session_status, s.expires_at AS session_expires_at,
            GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), s.expires_at)) AS session_remaining_seconds
     FROM rex_signer_pairing_codes pc LEFT JOIN rex_signer_sessions s ON s.id = pc.completed_session_id
     WHERE pc.id = ? AND pc.pairing_purpose = 'auth' LIMIT 1`,
    [pairingId]
  );
  if (!row) return jsonOk(res, { status: 'none', message: 'RexLink sign-in pairing was not found.' });
  if (row.status === 'pending') return jsonOk(res, { status: new Date(row.expires_at).getTime() <= Date.now() ? 'expired' : 'pending', message: 'Waiting for RexLink.' });
  if (row.status !== 'completed' || !row.session_id || row.session_status !== 'active') return jsonOk(res, { status: row.status || 'expired', message: 'RexLink sign-in pairing is no longer active.' });
  const ticket = auth.makeLoginTicket(row.session_user_id, row.session_id, row.session_wallet_address);
  const redirectUrl = `${config.phpBaseUrl}/auth/rexlink_bridge.php?ticket=${encodeURIComponent(ticket)}`;
  jsonOk(res, { status: 'authenticated', message: 'Signed in with RexLink.', wallet_address: row.session_wallet_address, session_id: row.session_id, session_remaining_seconds: Number(row.session_remaining_seconds || 0), redirect_url: redirectUrl });
}));

async function checkReceipt(requestId) {
  const row = await db.one(`SELECT * FROM rex_signer_approval_requests WHERE id = ? AND request_type = 'claim' AND status = 'approved' AND tx_hash IS NOT NULL AND tx_hash <> '' LIMIT 1`, [requestId]);
  if (!row) return;
  const receipt = await provider.getTransactionReceipt(row.tx_hash);
  await db.query(`UPDATE rex_signer_approval_requests SET last_chain_checked_at = NOW(), confirmation_attempts = confirmation_attempts + 1 WHERE id = ?`, [requestId]);
  if (!receipt) return;
  const result = row.result_json ? JSON.parse(row.result_json) : {};
  result.tx_hash = row.tx_hash;
  result.tx_status = receipt.status === 1 ? 'confirmed' : 'failed';
  result.chain_receipt = { blockNumber: receipt.blockNumber, status: receipt.status, hash: receipt.hash };
  await db.tx(async (conn) => {
    if (receipt.status === 1 && result.snapshot_id) {
      await claims.finalizeSnapshot(Number(result.snapshot_id), Number(row.user_id), row.tx_hash, conn);
      result.claim_snapshot_status = 'used';
      result.ledger_status = 'claimed';
      await conn.execute(`UPDATE rex_signer_approval_requests SET tx_status = 'claim_completed', tx_confirmed_at = NOW(), completed_at = NOW(), result_json = ?, chain_receipt_json = ? WHERE id = ?`, [JSON.stringify(result), JSON.stringify(result.chain_receipt), requestId]);
    } else if (result.snapshot_id) {
      await claims.releaseSnapshot(Number(result.snapshot_id), Number(row.user_id), conn);
      result.claim_snapshot_status = 'expired';
      result.ledger_status = 'available';
      await conn.execute(`UPDATE rex_signer_approval_requests SET tx_status = 'claim_released', tx_failed_at = NOW(), completed_at = NOW(), result_json = ?, chain_receipt_json = ? WHERE id = ?`, [JSON.stringify(result), JSON.stringify(result.chain_receipt), requestId]);
    }
  });
  realtime.publish('claim.tx.updated', { user_id: row.user_id, session_id: row.session_id, request_id: requestId, tx_status: result.tx_status, tx_hash: row.tx_hash });
}

async function watchPending() {
  const rows = await db.query(
    `SELECT id FROM rex_signer_approval_requests
     WHERE request_type = 'claim' AND status = 'approved' AND tx_hash IS NOT NULL AND tx_hash <> ''
       AND tx_status IN ('tx_broadcasted','tx_confirming')
     ORDER BY COALESCE(last_chain_checked_at, '1970-01-01') ASC LIMIT 10`
  );
  for (const row of rows) await checkReceipt(row.id).catch(() => {});
}

const server = http.createServer(app);
realtime.attach(server);

db.ensureSchema().then(() => {
  setInterval(() => watchPending().catch(() => {}), 5000);
  server.listen(config.port, () => {
    console.log(`RexLink API listening on ${config.publicApiUrl} (port ${config.port})`);
  });
}).catch((error) => {
  console.error(error);
  process.exit(1);
});
