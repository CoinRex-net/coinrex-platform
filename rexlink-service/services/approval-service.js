function createApprovalService({
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
}) {
  async function resolveApprovalNetwork(networkSlug, chainId) {
    const slug = String(networkSlug || '').trim().toLowerCase();
    const numericChainId = Number(chainId || 0);
    if (!slug || !Number.isInteger(numericChainId) || numericChainId <= 0) {
      const error = new Error('A valid network_slug and chain_id are required.');
      error.status = 422;
      throw error;
    }
    const network = await db.one(
      `SELECT slug, name, chain_id, native_symbol
       FROM rex_signer_networks
       WHERE slug = ? AND chain_id = ? AND is_enabled = 1 AND chain_family = 'evm'
       LIMIT 1`,
      [slug, numericChainId]
    );
    if (!network) {
      const error = new Error('The requested network is disabled, unknown, or does not match its chain ID.');
      error.status = 422;
      throw error;
    }
    return {
      slug: String(network.slug),
      name: String(network.name),
      chain_id: Number(network.chain_id),
      native_symbol: String(network.native_symbol || ''),
    };
  }

  async function createApprovalRequest(req, res) {
    const actor = await auth.requireUserActor(req);
    const appId = String(req.body.app_id || req.headers['x-rexlink-app-id'] || 'coinrex').trim().toLowerCase();
    const active = await db.one(`SELECT * FROM rex_signer_sessions WHERE user_id = ? AND app_id = ? AND status = 'active' AND expires_at > NOW() ORDER BY id DESC LIMIT 1`, [actor.user_id, appId]);
    if (!active) return jsonError(res, 409, 'Connect RexLink before creating approval requests.');
    const requestType = ['claim', 'send', 'message'].includes(String(req.body.request_type || '').toLowerCase())
      ? String(req.body.request_type).toLowerCase()
      : 'message';
    const payload = req.body.payload && typeof req.body.payload === 'object' ? req.body.payload : {};
    const network = await resolveApprovalNetwork(
      req.body.network_slug || payload.network_slug,
      req.body.chain_id || payload.chain_id
    );
    const [insert] = await db.pool.execute(
      `INSERT INTO rex_signer_approval_requests
       (user_id, app_id, session_id, network_slug, chain_id, request_type, title, summary, amount, fee_estimate, payload_json, wallet_address, expires_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))`,
      [
        actor.user_id,
        active.app_id || 'coinrex',
        active.id,
        network.slug,
        network.chain_id,
        requestType,
        String(req.body.title || 'CoinRex approval request').slice(0, 160),
        String(req.body.summary || 'Approve this request in RexLink.').slice(0, 255),
        req.body.amount ? String(req.body.amount).slice(0, 80) : null,
        req.body.fee_estimate ? String(req.body.fee_estimate).slice(0, 80) : null,
        JSON.stringify({
          ...payload,
          dapp_name: 'CoinRex',
          dapp_url: config.phpBaseUrl,
          api_base_url: config.publicApiUrl,
          network_slug: network.slug,
          network_name: network.name,
          chain_id: network.chain_id,
          native_symbol: network.native_symbol,
        }),
        active.wallet_address || null,
        Math.max(1, Math.min(Number(req.body.expires_minutes || 10), 60)),
      ]
    );
    realtime.publish('approval.created', { user_id: actor.user_id, session_id: active.id, request_id: insert.insertId, status: 'pending', request_type: requestType, title: req.body.title || 'CoinRex approval request' });
    jsonOk(res, { message: 'Approval request created.', request_id: insert.insertId, status: 'pending' }, 201);
  }

  async function createClaimApproval(req, res) {
    await maintenance.expireOldRows();
    const actor = await auth.requireUserActor(req);
    const elig = await claims.eligibility(actor.user_id);
    if (!elig.eligible) return jsonError(res, 422, elig.message);
    const active = await db.one(`SELECT * FROM rex_signer_sessions WHERE user_id = ? AND app_id = 'coinrex' AND status = 'active' AND expires_at > NOW() ORDER BY id DESC LIMIT 1`, [actor.user_id]);
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
    const claimNetwork = await resolveApprovalNetwork(payload.network_slug, payload.chain_id);
    const [insert] = await db.pool.execute(
      `INSERT INTO rex_signer_approval_requests
       (user_id, app_id, session_id, network_slug, chain_id, request_type, title, summary, amount, fee_estimate, payload_json, wallet_address, expires_at, tx_status)
       VALUES (?, ?, ?, ?, ?, 'claim', 'Approve REX Claim', ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'approval_pending')`,
      [actor.user_id, active.app_id || 'coinrex', active.id, claimNetwork.slug, claimNetwork.chain_id, `${amount.toFixed(8)} REX`, payload.fee_estimate, JSON.stringify(payload), active.wallet_address]
    );
    realtime.publish('approval.created', { user_id: actor.user_id, session_id: active.id, request_id: insert.insertId, status: 'pending', request_type: 'claim', title: 'Approve REX Claim', amount: `${amount.toFixed(8)} REX`, fee_estimate: payload.fee_estimate, payload });
    jsonOk(res, { message: 'Claim approval request created.', request_id: insert.insertId, status: 'pending', amount: amount.toFixed(8), fee_estimate: payload.fee_estimate, expires_in_seconds: 600 }, 201);
  }

  async function listApprovalRequests(req, res) {
    const actor = await auth.requireUserActor(req);
    const status = String(req.query.status || 'pending');
    const params = [actor.user_id];
    const filter = status !== 'all' ? 'AND status = ?' : '';
    if (status !== 'all') params.push(status);
    const rows = await db.query(`SELECT * FROM rex_signer_approval_requests WHERE user_id = ? ${filter} ORDER BY created_at DESC LIMIT 50`, params);
    jsonOk(res, { approval_requests: rows.map(approvalPayload) });
  }

  async function getApprovalStatus(req, res) {
    const actor = await auth.requireUserActor(req);
    const row = await db.one(`SELECT * FROM rex_signer_approval_requests WHERE id = ? AND user_id = ? LIMIT 1`, [Number(req.query.request_id || 0), actor.user_id]);
    if (!row) return jsonError(res, 404, 'Approval request was not found.');
    jsonOk(res, { approval_request: approvalPayload(row) });
  }

  async function decideApproval(req, res) {
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
  }

  return {
    createApprovalRequest,
    createClaimApproval,
    listApprovalRequests,
    getApprovalStatus,
    decideApproval,
  };
}

module.exports = createApprovalService;
