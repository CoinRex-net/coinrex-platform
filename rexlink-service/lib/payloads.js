function sessionPayload(row) {
  if (!row) return null;
  const remainingSeconds = row.remaining_seconds !== undefined && row.remaining_seconds !== null
    ? Math.max(0, Number(row.remaining_seconds))
    : null;
  const explicitExpiryUnix = Number(row.expires_at_unix || 0);
  const parsedExpiryUnix = row.expires_at ? Math.floor(new Date(row.expires_at).getTime() / 1000) : 0;
  const expiresAtUnix = explicitExpiryUnix > 0
    ? explicitExpiryUnix
    : (remainingSeconds !== null ? Math.floor(Date.now() / 1000) + remainingSeconds : parsedExpiryUnix);
  return {
    id: Number(row.id),
    user_id: Number(row.user_id),
    app_id: row.app_id || 'coinrex',
    network_scope: 'multi',
    device_name: row.device_name,
    wallet_address: row.wallet_address || null,
    status: row.status,
    expires_at: row.expires_at,
    expires_at_unix: expiresAtUnix > 0 ? expiresAtUnix : null,
    remaining_seconds: remainingSeconds,
    last_seen_at: row.last_seen_at,
    created_at: row.created_at,
  };
}

function approvalPayload(row) {
  const payload = row.payload_json ? JSON.parse(row.payload_json) : null;
  const result = row.result_json ? JSON.parse(row.result_json) : null;
  return {
    id: Number(row.id),
    user_id: Number(row.user_id),
    app_id: row.app_id || 'coinrex',
    session_id: row.session_id ? Number(row.session_id) : null,
    network_slug: row.network_slug,
    chain_id: row.chain_id ? Number(row.chain_id) : (payload?.chain_id ? Number(payload.chain_id) : null),
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

module.exports = {
  sessionPayload,
  approvalPayload,
};
