const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const config = require('./config');
const db = require('./db');
const { parseCookies, sha256 } = require('./util');

function phpUnserializeSessionValue(serialized) {
  const value = String(serialized || '');
  if (/^i:\d+;/.test(value)) return Number(value.slice(2, -1));
  if (/^s:\d+:/.test(value)) {
    const firstQuote = value.indexOf('"');
    const lastQuote = value.lastIndexOf('"');
    return firstQuote >= 0 && lastQuote > firstQuote ? value.slice(firstQuote + 1, lastQuote) : '';
  }
  return null;
}

function readPhpSession(sessionId) {
  if (!/^[a-zA-Z0-9,-]{1,128}$/.test(String(sessionId || ''))) return {};
  const file = path.join(config.sessionSavePath, `sess_${sessionId}`);
  if (!fs.existsSync(file)) return {};
  const raw = fs.readFileSync(file, 'utf8');
  const out = {};
  const re = /([A-Za-z0-9_]+)\|((?:i:\d+;)|(?:s:\d+:"[^"]*";)|(?:b:[01];)|(?:N;))/g;
  let match;
  while ((match = re.exec(raw))) out[match[1]] = phpUnserializeSessionValue(match[2]);
  return out;
}

async function getUserById(userId) {
  if (!userId) return null;
  return db.one('SELECT * FROM users WHERE id = ? LIMIT 1', [Number(userId)]);
}

async function webActor(req) {
  const cookies = parseCookies(req.headers.cookie || '');
  const sessionId = cookies.PHPSESSID || cookies.COINREXSESSID || '';
  const session = readPhpSession(sessionId);
  const userId = Number(session.user_id || 0);
  if (!userId) return null;
  const user = await getUserById(userId);
  if (!user || String(user.status || '') !== 'active' || Number(user.security_suspended || 0) === 1) return null;
  return { type: 'web_user', user_id: userId, user, session_id: null };
}

async function signerActor(req) {
  const header = req.headers.authorization || req.headers['x-rex-signer-session'] || '';
  const token = String(header).replace(/^Bearer\s+/i, '').trim() || String(req.body?.session_token || req.query?.session_token || '').trim();
  if (!token) return null;
  const row = await db.one(
    `SELECT * FROM rex_signer_sessions
     WHERE session_token_hash = ? AND status = 'active' AND expires_at > NOW()
     LIMIT 1`,
    [sha256(token)]
  );
  if (!row) return null;
  await db.query('UPDATE rex_signer_sessions SET last_seen_at = NOW() WHERE id = ?', [row.id]);
  return { type: 'signer_session', user_id: Number(row.user_id), session_id: Number(row.id), session: row, token };
}

async function requireUserActor(req, { signerOnly = false } = {}) {
  const signer = await signerActor(req);
  if (signer) return signer;
  if (!signerOnly) {
    const web = await webActor(req);
    if (web) return web;
  }
  const err = new Error(signerOnly ? 'An active RexLink session is required.' : 'Authentication required.');
  err.status = signerOnly ? 403 : 401;
  throw err;
}

function base64urlJson(payload) {
  return Buffer.from(JSON.stringify(payload)).toString('base64url');
}

function signPart(part) {
  return crypto.createHmac('sha256', config.realtimeSecret).update(part).digest('base64url');
}

function makeLoginTicket(userId, sessionId, walletAddress) {
  const payload = {
    user_id: Number(userId),
    rex_signer_session_id: Number(sessionId),
    wallet_address: String(walletAddress || ''),
    iat: Math.floor(Date.now() / 1000),
    exp: Math.floor(Date.now() / 1000) + 90,
    nonce: crypto.randomBytes(10).toString('hex'),
  };
  const part = base64urlJson(payload);
  return `${part}.${signPart(part)}`;
}

function verifyLoginTicket(ticket) {
  const [part, sig] = String(ticket || '').split('.');
  if (!part || !sig || sig !== signPart(part)) throw new Error('Invalid RexLink login ticket.');
  const payload = JSON.parse(Buffer.from(part, 'base64url').toString('utf8'));
  if (Number(payload.exp || 0) < Math.floor(Date.now() / 1000)) throw new Error('RexLink login ticket expired.');
  return payload;
}

function makeRealtimeToken(actor, ttl = 900) {
  const userId = Number(actor.user_id || 0);
  const sessionId = Number(actor.session_id || 0);
  const rooms = [`user:${userId}`];
  if (sessionId > 0) rooms.push(`session:${sessionId}`);
  const payload = {
    sub: String(userId),
    type: actor.type,
    user_id: userId,
    session_id: sessionId,
    rooms,
    iat: Math.floor(Date.now() / 1000),
    exp: Math.floor(Date.now() / 1000) + ttl,
  };
  const part = base64urlJson(payload);
  return `${part}.${signPart(part)}`;
}

module.exports = {
  getUserById,
  webActor,
  signerActor,
  requireUserActor,
  makeLoginTicket,
  verifyLoginTicket,
  makeRealtimeToken,
};
