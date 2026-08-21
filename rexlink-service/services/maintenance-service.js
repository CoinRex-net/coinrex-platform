function createMaintenanceService({ db }) {
  let running = null;
  let lastRunAt = 0;

  async function expireOldRows() {
    if (running) return running;
    if (Date.now() - lastRunAt < 15000) return null;
    running = Promise.all([
      db.query(`UPDATE rex_signer_pairing_codes SET status = 'expired' WHERE status = 'pending' AND expires_at <= NOW()`),
      db.query(`UPDATE rex_signer_sessions SET status = 'expired' WHERE status = 'active' AND expires_at <= NOW()`),
      db.query(`UPDATE rex_signer_approval_requests SET status = 'expired' WHERE status = 'pending' AND expires_at <= NOW()`),
    ]).finally(() => {
      lastRunAt = Date.now();
      running = null;
    });
    return running;
  }

  return {
    expireOldRows,
  };
}

module.exports = createMaintenanceService;
