const http = require('http');
const { WebSocketServer, WebSocket } = require('ws');
const crypto = require('crypto');
const config = require('./config');

const rooms = new Map();
let wss = null;

function add(room, ws) {
  if (!rooms.has(room)) rooms.set(room, new Set());
  rooms.get(room).add(ws);
}

function remove(ws) {
  for (const clients of rooms.values()) clients.delete(ws);
}

function safeSend(ws, payload) {
  if (ws.readyState !== WebSocket.OPEN) return false;
  ws.send(JSON.stringify(payload));
  return true;
}

function publish(type, payload = {}, targetRooms = []) {
  const normalized = targetRooms.length ? targetRooms : [
    payload.user_id ? `user:${payload.user_id}` : '',
    payload.session_id ? `session:${payload.session_id}` : '',
  ].filter(Boolean);
  const packet = {
    type,
    event_id: crypto.randomBytes(8).toString('hex'),
    payload,
    ts: Math.floor(Date.now() / 1000),
    created_at_ms: Date.now(),
  };
  let delivered = 0;
  for (const room of normalized) {
    for (const ws of rooms.get(room) || []) {
      if (safeSend(ws, packet)) delivered += 1;
    }
  }
  return delivered;
}

function verifyToken(token) {
  const [part, sig] = String(token || '').split('.');
  const expected = crypto.createHmac('sha256', config.realtimeSecret).update(part || '').digest('base64url');
  if (!part || !sig || sig !== expected) throw new Error('Invalid realtime token.');
  const payload = JSON.parse(Buffer.from(part, 'base64url').toString('utf8'));
  if (Number(payload.exp || 0) < Math.floor(Date.now() / 1000)) throw new Error('Realtime token expired.');
  return payload;
}

function attach(server) {
  wss = new WebSocketServer({ server, path: '/ws' });
  wss.on('connection', (ws, req) => {
    try {
      const url = new URL(req.url || '/', `http://${req.headers.host || 'localhost'}`);
      const auth = verifyToken(url.searchParams.get('token'));
      ws.rooms = (auth.rooms || []).map(String);
      ws.userId = Number(auth.user_id || 0);
      ws.rooms.forEach((room) => add(room, ws));
      safeSend(ws, { type: 'realtime.ready', rooms: ws.rooms, ts: Date.now() });
    } catch (error) {
      safeSend(ws, { type: 'realtime.error', message: error.message });
      ws.close(1008);
      return;
    }
    ws.on('message', (raw) => {
      let data = null;
      try { data = JSON.parse(String(raw)); } catch (_) {}
      if (data?.type === 'ping') safeSend(ws, { type: 'pong', ts: Date.now() });
      if (data?.type === 'approval.intent') {
        publish('approval.intent', {
          user_id: ws.userId,
          request_id: Number(data.request_id || 0),
          decision: String(data.decision || ''),
        }, ws.rooms || []);
      }
    });
    ws.on('close', () => remove(ws));
    ws.on('error', () => remove(ws));
  });
}

function wsUrl() {
  return `${config.publicApiUrl.replace(/^http/i, 'ws')}/ws`;
}

module.exports = { attach, publish, wsUrl };
