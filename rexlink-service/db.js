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
  // Create rex_signer_apps table for multi-app support
  await query(`
    CREATE TABLE IF NOT EXISTS rex_signer_apps (
      id INT AUTO_INCREMENT PRIMARY KEY,
      app_id VARCHAR(64) UNIQUE NOT NULL,
      app_name VARCHAR(128) NOT NULL,
      app_url VARCHAR(512) DEFAULT NULL,
      public_key VARCHAR(256) DEFAULT NULL,
      callback_url VARCHAR(512) DEFAULT NULL,
      is_active TINYINT(1) DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  `);

  // Add app_id columns to existing tables (safe migration)
  await ensureColumn('rex_signer_pairing_codes', 'poll_token_hash', 'poll_token_hash CHAR(64) DEFAULT NULL');
  await ensureColumn('rex_signer_pairing_codes', 'app_id', "app_id VARCHAR(64) DEFAULT 'coinrex'");
  await ensureColumn('rex_signer_sessions', 'app_id', "app_id VARCHAR(64) DEFAULT 'coinrex'");
  await ensureColumn('rex_signer_approval_requests', 'app_id', "app_id VARCHAR(64) DEFAULT 'coinrex'");

  await ensureColumn('rex_signer_pairing_codes', 'network_scope', 'network_scope VARCHAR(20) NOT NULL DEFAULT \'multi\' AFTER app_id');
  await ensureColumn('rex_signer_pairing_codes', 'requested_networks_json', 'requested_networks_json JSON NULL AFTER network_scope');
  await ensureColumn('rex_signer_approval_requests', 'chain_id', 'chain_id INT UNSIGNED NULL AFTER network_slug');

  // Legacy schema
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
