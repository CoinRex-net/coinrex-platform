function createClaimMonitorService({
  db,
  auth,
  claims,
  realtime,
  provider,
  jsonOk,
  jsonError,
}) {
  async function completeClaimTx(req, res) {
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
  }

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

  return {
    completeClaimTx,
    checkReceipt,
    watchPending,
  };
}

module.exports = createClaimMonitorService;
