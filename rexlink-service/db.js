const mysql = require('mysql2/promise');
const config = require('./config');

const pool = mysql.createPool(config.db);

async function query(sql, params = []) {
  const [rows] = await pool.execute(sql, params);
  return rows;
}

async function one(sql, params = []) {
  const rows = await query(sql, params);
  return rows[0] || null;
}

async function tx(work) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const result = await work(conn);
    await conn.commit();
    return result;
  } catch (error) {
    try { await conn.rollback(); } catch (_) {}
    throw error;
  } finally {
    conn.release();
  }
}

async function ensureColumn(table, column, ddl) {
  const row = await one(
    `SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?`,
    [table, column]
  );
  if (Number(row?.total || 0) === 0) {
    await query(`ALTER TABLE ${table} ADD COLUMN ${ddl}`);
  }
}

async function ensureSchema() {
  await ensureColumn('rex_signer_approval_requests', 'tx_status', "tx_status VARCHAR(30) NULL AFTER tx_hash");
  await ensureColumn('rex_signer_approval_requests', 'tx_submitted_at', "tx_submitted_at DATETIME NULL AFTER tx_status");
  await ensureColumn('rex_signer_approval_requests', 'tx_confirmed_at', "tx_confirmed_at DATETIME NULL AFTER tx_submitted_at");
  await ensureColumn('rex_signer_approval_requests', 'tx_failed_at', "tx_failed_at DATETIME NULL AFTER tx_confirmed_at");
  await ensureColumn('rex_signer_approval_requests', 'last_chain_checked_at', "last_chain_checked_at DATETIME NULL AFTER tx_failed_at");
  await ensureColumn('rex_signer_approval_requests', 'confirmation_attempts', "confirmation_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_chain_checked_at");
  await ensureColumn('rex_signer_approval_requests', 'chain_receipt_json', "chain_receipt_json JSON NULL AFTER confirmation_attempts");
  await ensureColumn('rex_signer_approval_requests', 'idempotency_key', "idempotency_key VARCHAR(80) NULL AFTER chain_receipt_json");
}

module.exports = { pool, query, one, tx, ensureSchema };
