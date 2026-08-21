function createAuthSessionService({ config, db, auth, sessionPayload, sha256, jsonOk }) {
  async function loginFromSession(req, res) {
    const pairingId = Number(req.body.pairing_id || req.query.pairing_id || 0);
    if (!pairingId) return jsonOk(res, { status: 'none', message: 'No RexLink pairing is active.' });
    const row = await db.one(
      `SELECT pc.*, s.id AS session_id, s.user_id AS session_user_id, s.wallet_address AS session_wallet_address,
              s.status AS session_status, s.expires_at AS session_expires_at,
              GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), s.expires_at)) AS session_remaining_seconds
       FROM rex_signer_pairing_codes pc LEFT JOIN rex_signer_sessions s ON s.id = pc.completed_session_id
       WHERE pc.id = ? LIMIT 1`,
      [pairingId]
    );
    if (!row) return jsonOk(res, { status: 'none', message: 'RexLink pairing was not found.' });
    const purpose = String(row.pairing_purpose || 'claim');
    if (purpose === 'auth') {
      const statusToken = String(req.body.status_token || req.query.status_token || '');
      if (!statusToken || !row.poll_token_hash || sha256(statusToken) !== String(row.poll_token_hash)) {
        const error = new Error('Pairing status authorization failed.');
        error.status = 403;
        throw error;
      }
    } else {
      const actor = await auth.requireUserActor(req);
      if (Number(actor.user_id || 0) !== Number(row.user_id || 0)) {
        const error = new Error('This pairing belongs to another account.');
        error.status = 403;
        throw error;
      }
    }
    if (row.status === 'pending') return jsonOk(res, { status: new Date(row.expires_at).getTime() <= Date.now() ? 'expired' : 'pending', message: 'Waiting for RexLink.' });
    if (row.status !== 'completed' || !row.session_id || row.session_status !== 'active') return jsonOk(res, { status: row.status || 'expired', message: 'RexLink sign-in pairing is no longer active.' });
    if (purpose !== 'auth') {
      return jsonOk(res, {
        status: 'connected',
        message: 'RexLink pairing is connected.',
        pairing_id: pairingId,
        pairing_purpose: purpose,
        app_id: row.app_id || 'coinrex',
        wallet_address: row.session_wallet_address,
        session_id: Number(row.session_id || 0),
        session: sessionPayload({
          id: row.session_id,
          user_id: row.session_user_id,
          wallet_address: row.session_wallet_address,
          status: row.session_status,
          expires_at: row.session_expires_at,
          remaining_seconds: row.session_remaining_seconds,
          app_id: row.app_id || 'coinrex',
        }),
      });
    }
    const ticket = auth.makeLoginTicket(row.session_user_id, row.session_id, row.session_wallet_address);
    const redirectUrl = `${config.phpBaseUrl}/auth/rexlink_bridge.php?ticket=${encodeURIComponent(ticket)}`;
    jsonOk(res, { status: 'authenticated', message: 'Signed in with RexLink.', pairing_purpose: purpose, app_id: row.app_id || 'coinrex', wallet_address: row.session_wallet_address, session_id: row.session_id, session_remaining_seconds: Number(row.session_remaining_seconds || 0), redirect_url: redirectUrl });
  }

  return {
    loginFromSession,
  };
}

module.exports = createAuthSessionService;
