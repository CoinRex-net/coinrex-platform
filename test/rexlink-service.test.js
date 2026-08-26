const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const createPairingService = require('../rexlink-service/services/pairing-service');
const createAuthSessionService = require('../rexlink-service/services/auth-session-service');
const createApprovalService = require('../rexlink-service/services/approval-service');
const { sessionPayload } = require('../rexlink-service/lib/payloads');

function jsonOk(res, payload, status = 200) {
  res.result = { success: true, status, ...payload };
  return res.result;
}

test('pairing creation does not wait for maintenance and returns a private status token', async () => {
  let insertSql = '';
  let insertParams = [];
  const db = {
    query: async (sql) => {
      if (sql.includes('rex_signer_networks')) {
        return [
          { slug: 'polygon', name: 'Polygon', chain_id: 137, native_symbol: 'POL' },
          { slug: 'base', name: 'Base', chain_id: 8453, native_symbol: 'ETH' },
          { slug: 'plasma', name: 'Plasma Mainnet', chain_id: 9745, native_symbol: 'XPL' },
        ];
      }
      return [];
    },
    one: async (sql) => {
      if (sql.includes('rex_signer_apps')) {
        return { app_id: 'coinrex', app_name: 'CoinRex', app_url: 'http://10.113.235.241/coinrex' };
      }
      return null;
    },
    pool: {
      execute: async (sql, params) => {
        insertSql = sql;
        insertParams = params;
        return [{ insertId: 41 }];
      },
    },
  };
  const service = createPairingService({
    config: {
      environment: 'development',
      publicApiUrl: 'https://api.coinrex.test',
      phpBaseUrl: 'https://coinrex.test',
      network: { slug: 'polygon', name: 'Polygon', chainId: 137, nativeSymbol: 'POL' },
    },
    db,
    auth: { webActor: async () => null },
    realtime: {},
    QRCode: {},
    maintenance: { expireOldRows: () => new Promise(() => {}) },
    sessionPayload: (value) => value,
    jsonOk,
    jsonError: () => {},
    sha256: (value) => `hash:${value}`,
    randomToken: () => 'private-poll-token',
    pairCode: () => '123456',
    normalizePairCode: (value) => value,
    formatPairCode: () => 'REX-123-456',
    clampDuration: (value) => Number(value),
    normalizeWallet: (value) => value,
  });
  const req = {
    body: { purpose: 'auth', duration_minutes: 5, app_id: 'coinrex' },
    headers: { origin: 'http://localhost' },
    ip: '127.0.0.1',
  };
  const res = {};

  await Promise.race([
    service.createPairing(req, res),
    new Promise((_, reject) => setTimeout(() => reject(new Error('pairing waited for maintenance')), 100)),
  ]);

  assert.equal(res.result.status, 201);
  assert.equal(res.result.pairing_id, 41);
  assert.equal(res.result.status_token, 'private-poll-token');
  assert.equal(res.result.qr_payload.app_id, 'coinrex');
  assert.equal(res.result.qr_payload.app_name, 'CoinRex');
  assert.equal(res.result.qr_payload.network_scope, 'multi');
  assert.ok(Number(res.result.expires_at_unix) > Math.floor(Date.now() / 1000));
  assert.equal(res.result.qr_payload.expires_at_unix, res.result.expires_at_unix);
  assert.deepEqual(
    res.result.qr_payload.supported_networks.map((network) => network.slug),
    ['polygon', 'base', 'plasma']
  );
  assert.match(insertSql, /poll_token_hash/);
  assert.ok(insertParams.includes('hash:private-poll-token'));
});

function pairingCompletionServiceWithConnection(conn) {
  return createPairingService({
    config: {
      environment: 'development',
      publicApiUrl: 'https://api.coinrex.test',
      phpBaseUrl: 'https://coinrex.test',
      network: { slug: 'polygon', name: 'Polygon', chainId: 137, nativeSymbol: 'POL' },
    },
    db: { tx: async (callback) => callback(conn) },
    auth: {},
    realtime: { publish: () => {} },
    QRCode: {},
    maintenance: { expireOldRows: async () => {} },
    sessionPayload: (value) => value,
    jsonOk,
    jsonError: () => {},
    sha256: (value) => `hash:${value}`,
    randomToken: () => 'session-token',
    pairCode: () => '123456',
    normalizePairCode: (value) => String(value).replace(/\D+/g, ''),
    formatPairCode: () => 'REX-123-456',
    clampDuration: (value) => Number(value),
    normalizeWallet: (value) => String(value).toLowerCase(),
  });
}

test('auth pairing clearly rejects a wallet that is not linked to any account', async () => {
  let queryIndex = 0;
  const conn = {
    execute: async () => {
      queryIndex += 1;
      if (queryIndex === 1) {
        return [[{
          id: 41,
          user_id: null,
          app_id: 'coinrex',
          pairing_purpose: 'auth',
          requested_duration_minutes: 5,
        }]];
      }
      return [[]];
    },
  };
  const service = pairingCompletionServiceWithConnection(conn);

  await assert.rejects(
    service.completePairing({
      body: { code: '123456', wallet_address: '0x1111111111111111111111111111111111111111' },
      headers: {},
      ip: '127.0.0.1',
    }, {}),
    (error) => error.status === 403
      && /not linked to any CoinRex account/.test(error.message)
      && /email and password/.test(error.message)
  );
});

test('claim pairing clearly rejects replacing a different wallet already linked to the account', async () => {
  let queryIndex = 0;
  const conn = {
    execute: async () => {
      queryIndex += 1;
      if (queryIndex === 1) {
        return [[{
          id: 42,
          user_id: 7,
          app_id: 'coinrex',
          pairing_purpose: 'claim',
          requested_duration_minutes: 5,
        }]];
      }
      if (queryIndex === 2) {
        return [[]];
      }
      return [[{
        id: 7,
        wallet_address: '0x2222222222222222222222222222222222222222',
      }]];
    },
  };
  const service = pairingCompletionServiceWithConnection(conn);

  await assert.rejects(
    service.completePairing({
      body: { code: '123456', wallet_address: '0x1111111111111111111111111111111111111111' },
      headers: {},
      ip: '127.0.0.1',
    }, {}),
    (error) => error.status === 409
      && /different RexLink wallet is already linked/.test(error.message)
      && /Disconnect the existing wallet/.test(error.message)
  );
});

test('pairing clearly rejects a wallet already linked to another account', async () => {
  let queryIndex = 0;
  const conn = {
    execute: async () => {
      queryIndex += 1;
      if (queryIndex === 1) {
        return [[{
          id: 43,
          user_id: 7,
          app_id: 'coinrex',
          pairing_purpose: 'claim',
          requested_duration_minutes: 5,
        }]];
      }
      return [[{
        id: 8,
        wallet_address: '0x1111111111111111111111111111111111111111',
      }]];
    },
  };
  const service = pairingCompletionServiceWithConnection(conn);

  await assert.rejects(
    service.completePairing({
      body: { code: '123456', wallet_address: '0x1111111111111111111111111111111111111111' },
      headers: {},
      ip: '127.0.0.1',
    }, {}),
    (error) => error.status === 409
      && /already linked to another CoinRex account/.test(error.message)
      && /only one CoinRex account/.test(error.message)
  );
});

test('session expiry uses server remaining time instead of parsing a database-local timestamp', () => {
  const before = Math.floor(Date.now() / 1000);
  const payload = sessionPayload({
    id: 9,
    user_id: 7,
    status: 'active',
    expires_at: '2099-01-01 00:00:00',
    remaining_seconds: 240,
  });
  const after = Math.floor(Date.now() / 1000);
  assert.ok(payload.expires_at_unix >= before + 240);
  assert.ok(payload.expires_at_unix <= after + 240);
  assert.equal(payload.remaining_seconds, 240);
});

test('generic approvals require an enabled matching network slug and chain ID', async () => {
  let inserted = null;
  const db = {
    one: async (sql, params) => {
      if (sql.includes('rex_signer_sessions')) {
        return { id: 5, user_id: 7, app_id: 'coinrex', wallet_address: '0x1111111111111111111111111111111111111111' };
      }
      if (sql.includes('rex_signer_networks') && params[0] === 'base' && Number(params[1]) === 8453) {
        return { slug: 'base', name: 'Base', chain_id: 8453, native_symbol: 'ETH' };
      }
      return null;
    },
    pool: {
      execute: async (sql, params) => {
        inserted = { sql, params };
        return [{ insertId: 88 }];
      },
    },
  };
  const service = createApprovalService({
    config: { phpBaseUrl: 'https://coinrex.test', publicApiUrl: 'https://api.coinrex.test', network: { slug: 'polygon' } },
    db,
    auth: { requireUserActor: async () => ({ user_id: 7 }) },
    claims: {},
    realtime: { publish: () => {} },
    maintenance: {},
    approvalPayload: (value) => value,
    jsonOk,
    jsonError: (_res, status, message) => {
      const error = new Error(message);
      error.status = status;
      throw error;
    },
    normalizeWallet: (value) => value,
  });

  const mismatchReq = {
    body: { network_slug: 'base', chain_id: 137, request_type: 'message' },
    headers: { 'x-rexlink-app-id': 'coinrex' },
  };
  await assert.rejects(service.createApprovalRequest(mismatchReq, {}), /disabled, unknown, or does not match/);

  const res = {};
  await service.createApprovalRequest({
    body: { network_slug: 'base', chain_id: 8453, request_type: 'message', title: 'Base action' },
    headers: { 'x-rexlink-app-id': 'coinrex' },
  }, res);
  assert.equal(res.result.request_id, 88);
  assert.equal(res.result.status, 'pending');
  assert.match(inserted.sql, /network_slug, chain_id/);
  assert.ok(inserted.params.includes(8453));
});

test('anonymous authentication status requires its response-only polling token', async () => {
  const row = {
    id: 41,
    app_id: 'coinrex',
    pairing_purpose: 'auth',
    poll_token_hash: 'hash:private-poll-token',
    status: 'completed',
    session_id: 9,
    session_user_id: 7,
    session_wallet_address: '0x1111111111111111111111111111111111111111',
    session_status: 'active',
    session_expires_at: '2026-08-20 12:00:00',
    session_remaining_seconds: 240,
  };
  const service = createAuthSessionService({
    config: { phpBaseUrl: 'https://coinrex.test' },
    db: { one: async () => row },
    auth: { makeLoginTicket: () => 'login-ticket' },
    sessionPayload: (value) => value,
    sha256: (value) => `hash:${value}`,
    jsonOk,
  });

  await assert.rejects(
    service.loginFromSession({ body: { pairing_id: 41, status_token: 'wrong' }, query: {} }, {}),
    (error) => error.status === 403
  );

  const res = {};
  await service.loginFromSession({ body: { pairing_id: 41, status_token: 'private-poll-token' }, query: {} }, res);
  assert.equal(res.result.status, 'authenticated');
  assert.match(res.result.redirect_url, /login-ticket/);
});

test('browser QR compaction produces a small multi-network v2 envelope', () => {
  const source = fs.readFileSync(require.resolve('../assets/js/rexlink-pairing.js'), 'utf8');
  const window = { location: { origin: 'https://coinrex.test' } };
  vm.runInNewContext(source, { window, navigator: {}, document: {} });
  const compact = window.CoinRexPairing.compactPayload({
    code: 'REX-123-456',
    network_scope: 'multi',
    supported_networks: [
      { slug: 'polygon', name: 'Polygon', chain_id: 137, native_symbol: 'POL' },
      { slug: 'base', name: 'Base', chain_id: 8453, native_symbol: 'ETH' },
      { slug: 'plasma', name: 'Plasma Mainnet', chain_id: 9745, native_symbol: 'XPL' },
    ],
    endpoints: { complete_pairing: '/api/v1/pairing/complete' },
    trust_context: { source: 'node_rexlink', verified: true },
  }, {});
  assert.equal(compact.t, 'rl');
  assert.equal(compact.v, 2);
  assert.equal(compact.s, 'm');
  assert.deepEqual(
    Array.from(compact.n, (network) => network[0]),
    ['polygon', 'base', 'plasma']
  );
  assert.ok(JSON.stringify(compact).length < 450);
  assert.equal(compact.type, 'coinrex.rex_signer.pairing');
  assert.equal(compact.code, 'REX-123-456');
  assert.equal(compact.endpoints, undefined);
});

test('browser QR compaction preserves PHP pairing protocol v1', () => {
  const source = fs.readFileSync(require.resolve('../assets/js/rexlink-pairing.js'), 'utf8');
  const window = { location: { origin: 'https://coinrex.xyz' } };
  vm.runInNewContext(source, { window, navigator: {}, document: {} });
  const compact = window.CoinRexPairing.compactPayload({
    version: 1,
    code: '123456',
    purpose: 'auth',
    api_base_url: 'https://coinrex.xyz',
    trust_context: { verified: true },
  }, {});

  assert.equal(compact.v, 1);
  assert.equal(compact.u, 'https://coinrex.xyz');
  assert.equal(compact.p, 'auth');
});

test('shared PHP navigation include does not emit a UTF-8 BOM before API JSON', () => {
  const source = fs.readFileSync(require.resolve('../includes/functions/navigation.php'));
  assert.notDeepEqual(Array.from(source.subarray(0, 3)), [0xef, 0xbb, 0xbf]);
  assert.equal(source.subarray(0, 5).toString('utf8'), '<?php');
});
