const crypto = require('crypto');
const fs = require('fs');
const http = require('http');
const path = require('path');
const { WebSocket, WebSocketServer } = require('ws');

function loadEnvFile(filePath) {
  if (!fs.existsSync(filePath)) return;
  const lines = fs.readFileSync(filePath, 'utf8').split(/\r?\n/);
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const index = trimmed.indexOf('=');
    if (index <= 0) continue;
    const key = trimmed.slice(0, index).trim();
    let value = trimmed.slice(index + 1).trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    if (!(key in process.env)) {
      process.env[key] = value;
    }
  }
}

const rootDir = path.resolve(__dirname, '..');
loadEnvFile(path.join(rootDir, '.env'));
loadEnvFile(path.join(rootDir, '.env.local'));

const wsPort = Number(process.env.COINREX_REALTIME_WS_PORT || 8081);
const eventPort = Number(process.env.COINREX_REALTIME_EVENT_PORT || 8082);
const eventHost = process.env.COINREX_REALTIME_EVENT_HOST || '127.0.0.1';
const secret = String(process.env.COINREX_REALTIME_SECRET || process.env.COINREX_ENCRYPTION_KEY || process.env.COINREX_CSRF_KEY || 'coinrex-dev-realtime-secret');
const debugRealtime = /^(1|true|yes)$/i.test(String(process.env.COINREX_REALTIME_DEBUG || ''));
const heartbeatMs = Number(process.env.COINREX_REALTIME_HEARTBEAT_MS || 25000);
const startedAt = Date.now();
const stats = {
  broadcasts: 0,
  delivered: 0,
  failedSends: 0,
  lastEventType: '',
  lastEventAt: 0,
};

function base64UrlDecode(value) {
  const normalized = String(value).replace(/-/g, '+').replace(/_/g, '/');
  const padded = normalized.padEnd(Math.ceil(normalized.length / 4) * 4, '=');
  return Buffer.from(padded, 'base64').toString('utf8');
}

function base64UrlEncode(buffer) {
  return Buffer.from(buffer).toString('base64').replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function safeEqual(left, right) {
  const leftBuffer = Buffer.from(String(left));
  const rightBuffer = Buffer.from(String(right));
  return leftBuffer.length === rightBuffer.length && crypto.timingSafeEqual(leftBuffer, rightBuffer);
}

function verifyClientToken(token) {
  const [payloadPart, signaturePart] = String(token || '').split('.');
  if (!payloadPart || !signaturePart) {
    throw new Error('Missing realtime token.');
  }

  const expected = base64UrlEncode(crypto.createHmac('sha256', secret).update(payloadPart).digest());
  if (!safeEqual(expected, signaturePart)) {
    throw new Error('Invalid realtime token.');
  }

  const payload = JSON.parse(base64UrlDecode(payloadPart));
  if (!payload.exp || Number(payload.exp) < Math.floor(Date.now() / 1000)) {
    throw new Error('Realtime token expired.');
  }
  if (!Array.isArray(payload.rooms) || payload.rooms.length === 0) {
    throw new Error('Realtime token has no rooms.');
  }

  return payload;
}

function verifyEventSignature(body, signature) {
  const expected = crypto.createHmac('sha256', secret).update(body).digest('hex');
  return safeEqual(expected, signature || '');
}

const roomClients = new Map();

function addClientToRoom(room, ws) {
  if (!roomClients.has(room)) {
    roomClients.set(room, new Set());
  }
  roomClients.get(room).add(ws);
}

function removeClient(ws) {
  for (const [room, clients] of roomClients.entries()) {
    clients.delete(ws);
    if (clients.size === 0) {
      roomClients.delete(room);
    }
  }
}

function sendJson(ws, payload) {
  if (ws.readyState !== WebSocket.OPEN) {
    removeClient(ws);
    return false;
  }
  try {
    ws.send(JSON.stringify(payload));
    return true;
  } catch (error) {
    stats.failedSends += 1;
    removeClient(ws);
    try {
      ws.terminate();
    } catch (terminateError) {}
    return false;
  }
}

function broadcastToRooms(rooms, packet, excludeClient = null) {
  let delivered = 0;
  stats.broadcasts += 1;
  stats.lastEventType = String(packet.type || '');
  stats.lastEventAt = Date.now();
  for (const room of rooms) {
    const clients = roomClients.get(room);
    if (!clients) continue;
    for (const client of clients) {
      if (client === excludeClient) continue;
      if (sendJson(client, packet)) {
        delivered += 1;
      }
    }
  }
  stats.delivered += delivered;
  return delivered;
}

function realtimeHealth() {
  const uniqueClients = new Set();
  for (const clients of roomClients.values()) {
    for (const client of clients) {
      if (client.readyState === WebSocket.OPEN) {
        uniqueClients.add(client);
      }
    }
  }

  return {
    success: true,
    ws_clients: uniqueClients.size,
    rooms: roomClients.size,
    uptime_seconds: Math.floor((Date.now() - startedAt) / 1000),
    broadcasts: stats.broadcasts,
    delivered: stats.delivered,
    failed_sends: stats.failedSends,
    last_event_type: stats.lastEventType,
    last_event_at: stats.lastEventAt,
    heartbeat_ms: heartbeatMs,
  };
}

const wss = new WebSocketServer({ port: wsPort });

wss.on('error', (error) => {
  console.error(`CoinRex realtime WebSocket failed on port ${wsPort}: ${error.message}`);
  setImmediate(() => process.exit(1));
});

wss.on('connection', (ws, request) => {
  try {
    const url = new URL(request.url || '/', `ws://${request.headers.host || 'localhost'}`);
    const auth = verifyClientToken(url.searchParams.get('token'));
    ws.coinrexRooms = auth.rooms.map(String);
    ws.coinrexUserId = Number(auth.user_id || 0);
    ws.isAlive = true;
    ws.coinrexRooms.forEach((room) => addClientToRoom(room, ws));
    sendJson(ws, { type: 'realtime.ready', ts: Date.now(), rooms: ws.coinrexRooms });
  } catch (error) {
    sendJson(ws, { type: 'realtime.error', message: error.message || 'Realtime auth failed.' });
    ws.close(1008, 'Realtime auth failed');
    return;
  }

  ws.on('pong', () => {
    ws.isAlive = true;
  });
  ws.on('message', (message) => {
    let data;
    try {
      data = JSON.parse(String(message));
    } catch (error) {
      return;
    }
    if (data?.type === 'ping') {
      sendJson(ws, { type: 'pong', ts: Date.now() });
      return;
    }
    if (data?.type === 'approval.intent') {
      const requestId = Number(data.request_id || 0);
      const decision = String(data.decision || '').toLowerCase();
      if (requestId <= 0 || !['approved', 'rejected'].includes(decision)) {
        return;
      }
      broadcastToRooms(ws.coinrexRooms || [], {
        type: 'approval.intent',
        event_id: crypto.randomBytes(8).toString('hex'),
        payload: {
          request_id: requestId,
          decision,
          user_id: ws.coinrexUserId || 0,
        },
        ts: Math.floor(Date.now() / 1000),
        created_at_ms: Date.now(),
      }, ws);
    }
  });
  ws.on('close', () => removeClient(ws));
  ws.on('error', () => removeClient(ws));
});

const heartbeatTimer = setInterval(() => {
  for (const ws of wss.clients) {
    if (ws.isAlive === false) {
      removeClient(ws);
      try {
        ws.terminate();
      } catch (error) {}
      continue;
    }
    ws.isAlive = false;
    try {
      ws.ping();
    } catch (error) {
      removeClient(ws);
      try {
        ws.terminate();
      } catch (terminateError) {}
    }
  }
}, heartbeatMs);

wss.on('close', () => clearInterval(heartbeatTimer));

const eventServer = http.createServer((request, response) => {
  if (request.method === 'GET' && request.url === '/health') {
    response.writeHead(200, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify(realtimeHealth()));
    return;
  }

  if (request.method !== 'POST' || request.url !== '/events') {
    response.writeHead(404, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify({ success: false, message: 'Not found.' }));
    return;
  }

  let body = '';
  request.on('data', (chunk) => {
    body += chunk;
    if (body.length > 65536) {
      request.destroy();
    }
  });
  request.on('end', () => {
    const signature = request.headers['x-coinrex-realtime-signature'];
    if (!verifyEventSignature(body, Array.isArray(signature) ? signature[0] : signature)) {
      response.writeHead(401, { 'Content-Type': 'application/json' });
      response.end(JSON.stringify({ success: false, message: 'Invalid signature.' }));
      return;
    }

    let event;
    try {
      event = JSON.parse(body);
    } catch (error) {
      response.writeHead(422, { 'Content-Type': 'application/json' });
      response.end(JSON.stringify({ success: false, message: 'Invalid JSON.' }));
      return;
    }

    const rooms = Array.isArray(event.rooms) ? event.rooms.map(String) : [];
    let delivered = 0;
    const packet = {
      type: event.type || 'event',
      event_id: event.event_id || '',
      payload: event.payload || {},
      ts: event.ts || Math.floor(Date.now() / 1000),
      created_at_ms: event.created_at_ms || Date.now(),
    };
    delivered = broadcastToRooms(rooms, packet);
    if (debugRealtime) {
      console.log(`[realtime] ${packet.type} rooms=${rooms.join(',') || '-'} delivered=${delivered}`);
    }

    response.writeHead(200, { 'Content-Type': 'application/json' });
    response.end(JSON.stringify({ success: true, delivered }));
  });
});

eventServer.on('error', (error) => {
  console.error(`CoinRex realtime event server failed on ${eventHost}:${eventPort}: ${error.message}`);
  setImmediate(() => process.exit(1));
});

eventServer.listen(eventPort, eventHost, () => {
  console.log(`CoinRex realtime event server listening on http://${eventHost}:${eventPort}`);
});

wss.on('listening', () => {
  console.log(`CoinRex realtime WebSocket listening on ws://0.0.0.0:${wsPort}`);
});
