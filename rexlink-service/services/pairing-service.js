function createPairingService({
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
}) {
  const REVIEW_PAIRING_QR_TTL_SECONDS = 300;
  const appCache = new Map();

  async function resolveApp(appId) {
    const normalized = String(appId || 'coinrex').trim().toLowerCase();
    if (!/^[a-z0-9][a-z0-9._-]{1,63}$/.test(normalized)) {
      const error = new Error('A valid RexLink app_id is required.');
      error.status = 422;
      throw error;
    }
    const cached = appCache.get(normalized);
    if (cached && cached.expiresAt > Date.now()) return cached.app;
    const app = await db.one(
      `SELECT app_id, app_name, app_url, callback_url FROM rex_signer_apps WHERE app_id = ? AND is_active = 1 LIMIT 1`,
      [normalized]
    );
    if (!app) {
      const error = new Error('This dApp is not registered for RexLink pairing.');
      error.status = 403;
      throw error;
    }
    appCache.set(normalized, { app, expiresAt: Date.now() + 60000 });
    return app;
  }

  function assertAppOrigin(app, requestOrigin) {
    const origin = String(requestOrigin || '').trim();
    const appUrl = String(app.app_url || '').trim();
    if (!origin || !appUrl) return;
    let actual;
    let expected;
    try {
      actual = new URL(origin).origin;
      expected = new URL(appUrl).origin;
    } catch (_) {
      const error = new Error('RexLink dApp origin is invalid.');
      error.status = 422;
      throw error;
    }
    const isLocalHost = (hostname) => {
      const host = String(hostname || '').toLowerCase();
      if (host === 'localhost' || host === '::1' || /^127\./.test(host) || /^10\./.test(host) || /^192\.168\./.test(host)) return true;
      const match = host.match(/^172\.(\d+)\./);
      return Boolean(match && Number(match[1]) >= 16 && Number(match[1]) <= 31);
    };
    const actualUrl = new URL(actual);
    const expectedUrl = new URL(expected);
    const localDevelopmentAlias = config.environment !== 'production'
      && String(app.app_id || '') === 'coinrex'
      && isLocalHost(actualUrl.hostname)
      && isLocalHost(expectedUrl.hostname);
    if (actual !== expected && !localDevelopmentAlias) {
      const error = new Error('This browser origin is not allowed to use the requested RexLink app_id.');
      error.status = 403;
      throw error;
    }
  }

  function buildQrPayload(displayCode, purpose, duration, expiresAt, extra = {}) {
    const supportedNetworks = Array.isArray(extra.supported_networks) ? extra.supported_networks : [];
    const preferredNetwork = extra.preferred_network || supportedNetworks[0] || {
      slug: config.network.slug,
      name: config.network.name,
      chain_id: config.network.chainId,
      native_symbol: config.network.nativeSymbol,
    };
    const networkLabel = supportedNetworks.length > 1 ? 'Multiple networks' : preferredNetwork.name;
    return {
      type: 'coinrex.rex_signer.pairing',
      version: 2,
      api_version: 'v1',
      code: displayCode,
      purpose,
      app_id: extra.app_id || 'coinrex',
      app_name: extra.app_name || extra.dapp_name || 'CoinRex',
      base_url: config.publicApiUrl,
      api_base_url: config.publicApiUrl,
      dapp_name: extra.app_name || extra.dapp_name || 'CoinRex',
      dapp_url: extra.app_url || extra.dapp_url || config.phpBaseUrl,
      network_scope: 'multi',
      supported_networks: supportedNetworks,
      preferred_network: preferredNetwork,
      network_slug: preferredNetwork.slug,
      network_name: preferredNetwork.name,
      chain_id: preferredNetwork.chain_id,
      native_symbol: preferredNetwork.native_symbol,
      requested_duration_minutes: duration,
      expires_at: expiresAt,
      expires_at_unix: Number(extra.expires_at_unix || 0) || undefined,
      endpoints: {
        complete_pairing: '/api/v1/pairing/complete',
        cancel_pairing: '/api/v1/pairing/cancel',
        session_status: '/api/v1/sessions',
        realtime_auth: '/api/v1/realtime/auth',
      },
      display_context: {
        dapp_name: extra.app_name || extra.dapp_name || 'CoinRex',
        website: new URL(extra.app_url || extra.dapp_url || config.phpBaseUrl).host,
        dapp_url: extra.app_url || extra.dapp_url || config.phpBaseUrl,
        network: networkLabel,
        network_scope: 'multi',
        network_slug: preferredNetwork.slug,
        chain_id: preferredNetwork.chain_id,
        native_symbol: preferredNetwork.native_symbol,
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

  function boolFlag(value) {
    return /^(1|true|yes|on)$/i.test(String(value || ''));
  }

  function requestedNetworkSlugs(value) {
    let source = value;
    if (typeof source === 'string') {
      try {
        source = JSON.parse(source);
      } catch (_) {
        source = source.split(',');
      }
    }
    return [...new Set((Array.isArray(source) ? source : [])
      .map((slug) => String(slug || '').trim().toLowerCase())
      .filter(Boolean))];
  }

  async function resolvePairingNetworks(value) {
    const requested = requestedNetworkSlugs(value);
    const rows = await db.query(
      `SELECT slug, name, chain_id, native_symbol
       FROM rex_signer_networks
       WHERE is_enabled = 1 AND chain_family = 'evm' AND chain_id IS NOT NULL
       ORDER BY sort_order ASC`
    );
    const supported = rows.map((row) => ({
      slug: String(row.slug),
      name: String(row.name),
      chain_id: Number(row.chain_id),
      native_symbol: String(row.native_symbol || ''),
    }));
    if (requested.length === 0) return supported;
    const bySlug = new Map(supported.map((network) => [network.slug, network]));
    const invalid = requested.filter((slug) => !bySlug.has(slug));
    if (invalid.length > 0) {
      const error = new Error(`Unsupported RexLink network: ${invalid.join(', ')}.`);
      error.status = 422;
      throw error;
    }
    return requested.map((slug) => bySlug.get(slug));
  }

  function reviewDuplicateMessage() {
    return 'This Wallet already have used to Review the Same Project, Please Switch to Fresh wallet to Check Eligibility';
  }

  async function findReviewWalletUsage(walletAddress, projectId) {
    const rows = await db.query(
      `SELECT id, user_id, project_id, status, wallet_address, eligibility_wallet_address
       FROM reviews
       WHERE project_id = ?
         AND (
           LOWER(COALESCE(wallet_address, '')) = ?
           OR LOWER(COALESCE(eligibility_wallet_address, '')) = ?
         )
       LIMIT 1`,
      [Number(projectId), walletAddress, walletAddress]
    );
    return rows[0] || null;
  }

  async function createPairing(req, res) {
    const startedAt = Date.now();
    maintenance.expireOldRows().catch(() => {});
    const requestedPurpose = String(req.body.purpose || 'claim').toLowerCase();
    const purpose = ['auth', 'claim', 'review_eligibility'].includes(requestedPurpose) ? requestedPurpose : 'claim';
    const duration = clampDuration(req.body.duration_minutes || 5);
    const appId = String(req.body.app_id || req.headers['x-app-id'] || 'coinrex').slice(0, 64);
    const app = await resolveApp(appId);
    assertAppOrigin(app, req.headers.origin);
    const actor = purpose === 'auth' ? await auth.webActor(req) : await auth.requireUserActor(req);
    const userId = actor?.user_id || null;
    const forceNewPairing = boolFlag(req.body.force_new_pairing);
    const requestedWalletRaw = String(req.body.requested_wallet_address || '').trim();
    const requestedWalletAddress = requestedWalletRaw ? normalizeWallet(requestedWalletRaw) : '';
    const meta = req.body.meta && typeof req.body.meta === 'object' ? req.body.meta : {};
    const supportedNetworks = await resolvePairingNetworks(req.body.network_slugs);
    const preferredNetwork = supportedNetworks[0] || null;

    if (userId && !forceNewPairing) {
      const active = await db.one(
        `SELECT *, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds
         FROM rex_signer_sessions WHERE user_id = ? AND app_id = ? AND status = 'active' AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1`,
        [userId, app.app_id]
      );
      if (active) return jsonOk(res, { message: 'RexLink is already connected.', already_connected: true, session: sessionPayload(active) });
    }

    const code = pairCode();
    const displayCode = formatPairCode(code);
    const statusToken = randomToken(24);
    const expiresAtUnix = Math.floor(Date.now() / 1000) + 300;
    const expiresAt = new Date(expiresAtUnix * 1000).toISOString();
    const [insert] = await db.pool.execute(
      `INSERT INTO rex_signer_pairing_codes
       (user_id, app_id, network_scope, requested_networks_json, code_hash, poll_token_hash, display_code, pairing_purpose, referral_code, requested_duration_minutes, expires_at, device_fingerprint, ip_address, user_agent)
       VALUES (?, ?, 'multi', ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), ?, ?, ?)`,
      [
        userId,
        app.app_id,
        JSON.stringify(supportedNetworks.map((network) => network.slug)),
        sha256(code),
        sha256(statusToken),
        displayCode,
        purpose,
        purpose === 'auth' ? String(req.body.referral_code || '').toUpperCase() || null : null,
        duration,
        purpose === 'auth' ? String(req.body.device_fingerprint || meta.device_fingerprint || '').slice(0, 255) : null,
        req.ip,
        req.headers['user-agent'] || null,
      ]
    );
    const qrPayload = buildQrPayload(displayCode, purpose, duration, expiresAt, {
      app_id: app.app_id,
      app_name: app.app_name,
      app_url: app.app_url || config.phpBaseUrl,
      requested_wallet_address: requestedWalletAddress || undefined,
      supported_networks: supportedNetworks,
      preferred_network: preferredNetwork,
      expires_at_unix: expiresAtUnix,
    });
    jsonOk(res, {
      message: 'Pairing code created.',
      pairing_id: insert.insertId,
      status_token: statusToken,
      display_code: displayCode,
      expires_in_seconds: 300,
      expires_at_unix: expiresAtUnix,
      requested_duration_minutes: duration,
      api_base_url: config.publicApiUrl,
      qr_payload: qrPayload,
      display_context: qrPayload.display_context,
      trust_context: qrPayload.trust_context,
      server_timing_ms: Date.now() - startedAt,
    }, 201);
  }

  async function pairingQr(req, res) {
    const payload = String(req.query.payload || '');
    if (!payload || payload.length > 3000) return jsonError(res, 422, 'Valid QR payload is required.');
    const svg = await QRCode.toString(payload, { type: 'svg', width: 220, margin: 2 });
    res.setHeader('Content-Type', 'image/svg+xml; charset=utf-8');
    res.end(svg);
  }

  async function completePairing(req, res) {
    const startedAt = Date.now();
    maintenance.expireOldRows().catch(() => {});
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
        const [users] = await conn.execute(
          `SELECT id FROM users WHERE wallet_address = ? AND status = 'active' LIMIT 1`,
          [wallet]
        );
        if (!users[0]) {
          const error = new Error('This wallet is not linked to a CoinRex account. Link it after signing in with email first.');
          error.status = 403;
          throw error;
        }
        userId = Number(users[0].id);
      }
      if (!userId) throw new Error('Pairing owner could not be resolved.');
      if (purpose !== 'review_eligibility') {
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
      }
      await conn.execute(`UPDATE rex_signer_sessions SET status = 'revoked', revoked_at = NOW(), revoke_reason = 'Replaced by a new RexLink session' WHERE user_id = ? AND app_id = ? AND status = 'active'`, [userId, pairing.app_id || 'coinrex']);
      const token = randomToken(32);
      const duration = clampDuration(pairing.requested_duration_minutes || 10);
      const [insert] = await conn.execute(
        `INSERT INTO rex_signer_sessions (user_id, app_id, pairing_code_id, session_token_hash, device_name, wallet_address, expires_at, last_seen_at, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ${duration} MINUTE), NOW(), ?, ?)`,
        [userId, pairing.app_id || 'coinrex', pairing.id, sha256(token), deviceName, wallet, req.ip, req.headers['user-agent'] || null]
      );
      await conn.execute(`UPDATE rex_signer_pairing_codes SET status = 'completed', completed_at = NOW(), completed_session_id = ? WHERE id = ?`, [insert.insertId, pairing.id]);
      const [sessionRows] = await conn.execute(`SELECT *, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds FROM rex_signer_sessions WHERE id = ?`, [insert.insertId]);
      return { token, session: sessionRows[0], userId, purpose, appId: pairing.app_id || 'coinrex' };
    });
    if (result.purpose === 'review_eligibility') {
      realtime.publish('session.connected', { user_id: result.userId, session_id: result.session.id, status: 'active', wallet_address: wallet, session: sessionPayload(result.session) });
      return jsonOk(res, {
        message: 'RexLink paired successfully.',
        session_token: result.token,
        session: sessionPayload(result.session),
        pairing_purpose: result.purpose,
        app_id: result.appId,
        server_timing_ms: Date.now() - startedAt,
      }, 201);
    }
    realtime.publish('session.connected', { user_id: result.userId, session_id: result.session.id, status: 'active', wallet_address: wallet, session: sessionPayload(result.session) });
    jsonOk(res, { message: 'RexLink paired successfully.', session_token: result.token, session: sessionPayload(result.session), pairing_purpose: result.purpose, app_id: result.appId, server_timing_ms: Date.now() - startedAt }, 201);
  }

  async function createReviewPairing(req, res) {
    const startedAt = Date.now();
    const actor = await auth.requireUserActor(req);
    const userId = Number(actor.user_id || 0);
    const duration = clampDuration(req.body.duration_minutes || 10);
    const forceNewPairing = boolFlag(req.body.force_new_pairing);
    const supportedNetworks = await resolvePairingNetworks(req.body.network_slugs);

    if (forceNewPairing) {
      await db.query(
        `UPDATE rex_signer_sessions
         SET status = 'revoked', revoked_at = NOW(), revoke_reason = 'Revoked from review eligibility page'
         WHERE user_id = ? AND status = 'active'`,
        [userId]
      );
      await db.query(
        `UPDATE rex_signer_pairing_codes
         SET status = 'expired'
         WHERE user_id = ? AND status = 'pending' AND pairing_purpose = 'review_eligibility'`,
        [userId]
      );
    } else {
      const active = await db.one(
        `SELECT *, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds
         FROM rex_signer_sessions
         WHERE user_id = ? AND status = 'active' AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1`,
        [userId]
      );
      if (active) {
        return jsonOk(res, {
          message: 'RexLink is already connected.',
          already_connected: true,
          session: sessionPayload(active),
          server_timing_ms: Date.now() - startedAt,
        });
      }

      const pending = await db.one(
        `SELECT id, display_code, requested_duration_minutes, requested_networks_json,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds,
                expires_at,
                UNIX_TIMESTAMP(expires_at) AS expires_at_unix
         FROM rex_signer_pairing_codes
         WHERE user_id = ? AND status = 'pending' AND pairing_purpose = 'review_eligibility' AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1`,
        [userId]
      );
      if (pending) {
        const storedSlugs = requestedNetworkSlugs(pending.requested_networks_json);
        const pendingNetworks = storedSlugs.length > 0
          ? await resolvePairingNetworks(storedSlugs)
          : supportedNetworks;
        if (
          Number(pending.requested_duration_minutes || 0) !== duration
          || Number(pending.remaining_seconds || 0) > REVIEW_PAIRING_QR_TTL_SECONDS
        ) {
          await db.query(`UPDATE rex_signer_pairing_codes SET status = 'expired' WHERE id = ?`, [Number(pending.id)]);
        } else {
        const qrPayload = buildQrPayload(
          pending.display_code,
          'review_eligibility',
          Number(pending.requested_duration_minutes || duration),
          pending.expires_at,
          {
            dapp_name: 'CoinRex Review Eligibility',
            dapp_url: config.phpBaseUrl,
            supported_networks: pendingNetworks,
            preferred_network: pendingNetworks[0] || null,
            expires_at_unix: Number(pending.expires_at_unix || 0),
          }
        );
        return jsonOk(res, {
          message: 'Pairing code ready.',
          pairing_id: Number(pending.id),
          display_code: pending.display_code,
          expires_in_seconds: Number(pending.remaining_seconds || 0),
          expires_at_unix: Number(pending.expires_at_unix || 0),
          requested_duration_minutes: Number(pending.requested_duration_minutes || duration),
          api_base_url: config.publicApiUrl,
          qr_payload: qrPayload,
          display_context: qrPayload.display_context,
          trust_context: qrPayload.trust_context,
          server_timing_ms: Date.now() - startedAt,
        }, 201);
        }
      }
    }

    const code = pairCode();
    const displayCode = formatPairCode(code);
    const [insert] = await db.pool.execute(
      `INSERT INTO rex_signer_pairing_codes
       (user_id, app_id, network_scope, requested_networks_json, code_hash, display_code, pairing_purpose, requested_duration_minutes, expires_at, ip_address, user_agent)
       VALUES (?, 'coinrex', 'multi', ?, ?, ?, 'review_eligibility', ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?, ?)`,
      [userId, JSON.stringify(supportedNetworks.map((network) => network.slug)), sha256(code), displayCode, duration, Math.ceil(REVIEW_PAIRING_QR_TTL_SECONDS / 60), req.ip, req.headers['user-agent'] || null]
    );
    const expiresAtUnix = Math.floor(Date.now() / 1000) + REVIEW_PAIRING_QR_TTL_SECONDS;
    const expiresAt = new Date(expiresAtUnix * 1000).toISOString();
    const qrPayload = buildQrPayload(displayCode, 'review_eligibility', duration, expiresAt, {
      dapp_name: 'CoinRex Review Eligibility',
      dapp_url: config.phpBaseUrl,
      supported_networks: supportedNetworks,
      preferred_network: supportedNetworks[0] || null,
      expires_at_unix: expiresAtUnix,
    });

    jsonOk(res, {
      message: 'Pairing code created.',
      pairing_id: insert.insertId,
      display_code: displayCode,
      expires_in_seconds: REVIEW_PAIRING_QR_TTL_SECONDS,
      expires_at_unix: expiresAtUnix,
      requested_duration_minutes: duration,
      api_base_url: config.publicApiUrl,
      qr_payload: qrPayload,
      display_context: qrPayload.display_context,
      trust_context: qrPayload.trust_context,
      server_timing_ms: Date.now() - startedAt,
    }, 201);
  }

  async function reviewWalletStatus(req, res) {
    const startedAt = Date.now();
    const actor = await auth.requireUserActor(req);
    const userId = Number(actor.user_id || 0);
    const projectId = Number(req.body.project_id || 0);
    const pairingId = Number(req.body.pairing_id || 0);
    const sessionId = Number(req.body.session_id || 0);
    if (!projectId) return jsonError(res, 422, 'Valid project_id is required.');

    let row = null;
    if (pairingId > 0) {
      const pairing = await db.one(
        `SELECT status AS pairing_status, pairing_purpose,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS pairing_remaining_seconds,
                completed_session_id
         FROM rex_signer_pairing_codes
         WHERE id = ? AND user_id = ? LIMIT 1`,
        [pairingId, userId]
      );
      if (!pairing || String(pairing.pairing_purpose || '') !== 'review_eligibility') {
        return jsonOk(res, { status: 'none', message: 'RexLink pairing was not found for review eligibility.', server_timing_ms: Date.now() - startedAt });
      }
      if (String(pairing.pairing_status || '') === 'pending') {
        return jsonOk(res, {
          status: Number(pairing.pairing_remaining_seconds || 0) <= 0 ? 'expired' : 'pending',
          message: 'Waiting for RexLink pairing.',
          server_timing_ms: Date.now() - startedAt,
        });
      }
      if (String(pairing.pairing_status || '') !== 'completed') {
        return jsonOk(res, { status: String(pairing.pairing_status || 'expired'), message: 'RexLink pairing is no longer active.', server_timing_ms: Date.now() - startedAt });
      }
      row = await db.one(
        `SELECT id AS session_id, user_id AS session_user_id, wallet_address AS session_wallet_address,
                status AS session_status, expires_at AS session_expires_at,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS session_remaining_seconds
         FROM rex_signer_sessions
         WHERE id = ? AND user_id = ? LIMIT 1`,
        [Number(pairing.completed_session_id || 0), userId]
      );
    } else if (sessionId > 0) {
      row = await db.one(
        `SELECT id AS session_id, user_id AS session_user_id, wallet_address AS session_wallet_address,
                status AS session_status, expires_at AS session_expires_at,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS session_remaining_seconds
         FROM rex_signer_sessions
         WHERE id = ? AND user_id = ? LIMIT 1`,
        [sessionId, userId]
      );
    } else {
      row = await db.one(
        `SELECT id AS session_id, user_id AS session_user_id, wallet_address AS session_wallet_address,
                status AS session_status, expires_at AS session_expires_at,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS session_remaining_seconds
         FROM rex_signer_sessions
         WHERE user_id = ? AND status = 'active' AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1`,
        [userId]
      );
    }

    if (!row) return jsonOk(res, { status: 'none', message: 'No active RexLink session found.', server_timing_ms: Date.now() - startedAt });
    if (String(row.session_status || '') !== 'active' || Number(row.session_remaining_seconds || 0) <= 0) {
      return jsonOk(res, { status: 'expired', message: 'RexLink session expired. Please pair again.', server_timing_ms: Date.now() - startedAt });
    }

    const wallet = normalizeWallet(row.session_wallet_address || '');
    const usedReview = await findReviewWalletUsage(wallet, projectId);
    if (usedReview) {
      return jsonError(res, 409, reviewDuplicateMessage(), { status: 'wallet_used', wallet_address: wallet });
    }

    jsonOk(res, {
      status: 'connected',
      message: 'RexLink wallet paired. Check eligibility next.',
      wallet_address: wallet,
      session_id: Number(row.session_id || 0),
      session_remaining_seconds: Number(row.session_remaining_seconds || 0),
      server_timing_ms: Date.now() - startedAt,
    });
  }

  async function reviewPairingStatus(req, res) {
    const code = normalizePairCode(req.body.code || '');
    if (!/^\d{6}$/.test(code)) return jsonError(res, 422, 'Enter the 6-digit pairing code from CoinRex.');
    const wallet = normalizeWallet(req.body.wallet_address || '');
    const row = await db.one(
      `SELECT pairing.id AS pairing_id, pairing.status AS pairing_status, pairing.completed_session_id,
              session_row.*, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), session_row.expires_at)) AS remaining_seconds
       FROM rex_signer_pairing_codes pairing
       LEFT JOIN rex_signer_sessions session_row ON session_row.id = pairing.completed_session_id
       WHERE pairing.code_hash = ? AND pairing.pairing_purpose = 'review_eligibility'
       LIMIT 1`,
      [sha256(code)]
    );
    if (!row) return jsonOk(res, { status: 'none', message: 'Review pairing code was not found.' });
    if (String(row.pairing_status || '') !== 'completed' || !row.completed_session_id) {
      return jsonOk(res, { status: String(row.pairing_status || 'pending'), pairing_id: Number(row.pairing_id || 0), message: 'Review pairing is still waiting for completion.' });
    }
    if (String(row.wallet_address || '').toLowerCase() !== wallet) {
      return jsonError(res, 409, 'Review pairing completed with a different wallet.');
    }
    if (String(row.status || '') !== 'active') {
      return jsonOk(res, { status: String(row.status || 'inactive'), pairing_id: Number(row.pairing_id || 0), session_id: Number(row.completed_session_id || 0), message: 'Review pairing session is no longer active.' });
    }
    jsonOk(res, {
      status: 'connected',
      pairing_id: Number(row.pairing_id || 0),
      session_id: Number(row.completed_session_id || 0),
      wallet_address: wallet,
      session: sessionPayload(row),
      message: 'Review pairing is connected.',
    });
  }

  async function cancelPairing(req, res) {
    const pairingId = Number(req.body.pairing_id || 0);
    const statusToken = String(req.body.status_token || '');
    if (pairingId > 0 && statusToken) {
      const result = await db.pool.execute(
        `UPDATE rex_signer_pairing_codes
         SET status = 'revoked'
         WHERE id = ? AND poll_token_hash = ? AND status = 'pending'`,
        [pairingId, sha256(statusToken)]
      );
      const update = Array.isArray(result) ? result[0] : result;
      if (!update || Number(update.affectedRows || 0) < 1) {
        return jsonError(res, 404, 'Pairing code is no longer pending.');
      }
      return jsonOk(res, { message: 'Pairing cancelled.', pairing_id: pairingId });
    }
    const code = normalizePairCode(req.body.code || '');
    if (!/^\d{6}$/.test(code)) return jsonError(res, 422, 'Pairing ID and status token are required.');
    await db.query(`UPDATE rex_signer_pairing_codes SET status = 'revoked' WHERE code_hash = ? AND status = 'pending'`, [sha256(code)]);
    jsonOk(res, { message: 'Pairing cancelled.' });
  }

  async function listSessions(req, res) {
    await maintenance.expireOldRows();
    const actor = await auth.requireUserActor(req);
    const appId = actor.type === 'signer_session' ? String(actor.session?.app_id || 'coinrex') : '';
    const appFilter = appId ? 'AND app_id = ?' : '';
    const params = appId ? [actor.user_id, appId] : [actor.user_id];
    const rows = await db.query(
      `SELECT *, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds
       FROM rex_signer_sessions WHERE user_id = ? ${appFilter}
       ORDER BY FIELD(status, 'active', 'expired', 'revoked'), created_at DESC LIMIT 25`,
      params
    );
    const sessions = rows.map(sessionPayload);
    const current = sessions.find((session) => session.status === 'active' && Number(session.remaining_seconds || 0) > 0) || null;
    jsonOk(res, { active_session_count: current ? 1 : 0, session_state: current ? 'active' : 'none', server_time_unix: Math.floor(Date.now() / 1000), current_session: current, sessions });
  }

  async function revokeSession(req, res) {
    const actor = await auth.requireUserActor(req);
    const sessionId = Number(req.body.session_id || actor.session_id || 0);
    await db.query(`UPDATE rex_signer_sessions SET status = 'revoked', revoked_at = NOW(), revoke_reason = ? WHERE id = ? AND user_id = ? AND status = 'active'`, [String(req.body.reason || 'Revoked by user'), sessionId, actor.user_id]);
    realtime.publish('session.revoked', { user_id: actor.user_id, session_id: sessionId, status: 'revoked' });
    jsonOk(res, { message: 'Session revoked.', session_id: sessionId, session_state: 'revoked', revoked: true, server_time_unix: Math.floor(Date.now() / 1000) });
  }

  return {
    createPairing,
    createReviewPairing,
    reviewWalletStatus,
    reviewPairingStatus,
    pairingQr,
    completePairing,
    cancelPairing,
    listSessions,
    revokeSession,
  };
}

module.exports = createPairingService;
