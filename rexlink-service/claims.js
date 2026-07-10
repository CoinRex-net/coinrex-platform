const fs = require('fs');
const path = require('path');
const { Wallet } = require('ethers');
const config = require('./config');
const db = require('./db');
const { decimalToWei } = require('./util');

function readJson(relative) {
  return JSON.parse(fs.readFileSync(path.join(config.rootDir, relative), 'utf8'));
}

function loadClaimDeploymentSet() {
  const candidates = [
    {
      networkSlug: 'polygon',
      chainId: 137,
      distributorFile: 'deployments/polygon-rex-claim-distributor.json',
      tokenFile: 'deployments/polygon-rex-token.json',
    },
    {
      networkSlug: 'polygon-amoy',
      chainId: 80002,
      distributorFile: 'deployments/polygon-amoy-rex-claim-distributor.json',
      tokenFile: 'deployments/polygon-amoy-rex-token.json',
    },
  ];

  for (const candidate of candidates) {
    const distributorPath = path.join(config.rootDir, candidate.distributorFile);
    if (!fs.existsSync(distributorPath)) continue;
    const distributorJson = readJson(candidate.distributorFile);
    if (!distributorJson.contractAddress) continue;
    const tokenJson = fs.existsSync(path.join(config.rootDir, candidate.tokenFile))
      ? readJson(candidate.tokenFile)
      : {};
    return {
      networkSlug: candidate.networkSlug,
      chainId: Number(distributorJson.chainId || candidate.chainId),
      distributor: distributorJson,
      token: tokenJson,
    };
  }

  throw new Error('Claim deployment metadata is missing.');
}

const claimDeployment = loadClaimDeploymentSet();
const distributor = claimDeployment.distributor;
const token = claimDeployment.token;

async function balance(userId, status, conn = db) {
  const [rows] = await conn.execute(
    'SELECT COALESCE(SUM(amount), 0) AS total FROM reward_ledger WHERE user_id = ? AND status = ?',
    [userId, status]
  );
  return Number(rows[0]?.total || 0);
}

async function eligibility(userId) {
  const user = await db.one('SELECT * FROM users WHERE id = ? LIMIT 1', [userId]);
  if (!user) return { eligible: false, message: 'User account not found.' };
  if (Number(user.reward_frozen || 0) === 1) return { eligible: false, message: 'Rewards are temporarily frozen by the admin team for this account.' };
  if (Number(user.security_suspended || 0) === 1 || String(user.status || '') !== 'active') return { eligible: false, message: 'Claim is temporarily unavailable while account activity is reviewed.' };
  if (!config.testingMode && !['pro', 'expert'].includes(String(user.level || 'beginner'))) return { eligible: false, message: 'Claim unlocks once your account reaches Pro level.' };
  const available = await balance(userId, 'available');
  if (!config.testingMode && available < config.claimMinimumRex) return { eligible: false, message: 'Minimum claim threshold has not been reached yet.' };
  if (available <= 0) return { eligible: false, message: 'No available rewards found for claim preparation.' };
  return { eligible: true, message: 'Claim snapshot can be generated.', balance: available.toFixed(8), level: String(user.level || 'beginner') };
}

async function generateNonce(conn) {
  for (;;) {
    const nonce = String(Math.floor(Math.random() * 8_000_000_000_000_000_000) + 1_000_000_000_000_000_000);
    const [rows] = await conn.execute('SELECT id FROM claim_snapshots WHERE nonce = ? LIMIT 1', [nonce]);
    if (!rows[0]) return nonce;
  }
}

async function generateSnapshot(userId, claimAmount, conn) {
  const [openRows] = await conn.execute(
    `SELECT id FROM claim_snapshots WHERE user_id = ? AND status = 'generated' ORDER BY id DESC LIMIT 1 FOR UPDATE`,
    [userId]
  );
  if (openRows[0]) throw new Error('A claim is already prepared for this account.');

  const available = await balance(userId, 'available', conn);
  const requested = Math.round(Number(claimAmount || available) * 100000000) / 100000000;
  if (requested <= 0) throw new Error('Claim amount must be greater than zero.');
  if (requested > available) throw new Error('Claim amount cannot exceed your available REX balance.');

  const [rows] = await conn.execute(
    `SELECT id, source, reward_phase, action_type, amount, reference_id, user_level_at_time
     FROM reward_ledger WHERE user_id = ? AND status = 'available' ORDER BY id ASC FOR UPDATE`,
    [userId]
  );

  let remaining = requested;
  const lockRows = [];
  let splitRow = null;
  for (const row of rows) {
    if (remaining <= 0) break;
    const amount = Number(row.amount || 0);
    if (amount <= 0) continue;
    if (amount <= remaining + 0.00000001) {
      lockRows.push(row);
      remaining = Math.round((remaining - amount) * 100000000) / 100000000;
    } else {
      splitRow = { row, claimAmount: remaining, remainingAmount: Math.round((amount - remaining) * 100000000) / 100000000 };
      remaining = 0;
    }
  }
  if (remaining > 0.00000001) throw new Error('Claim amount cannot exceed your available REX balance.');

  const nonce = await generateNonce(conn);
  const [insert] = await conn.execute(
    `INSERT INTO claim_snapshots (user_id, total_amount, nonce, status) VALUES (?, ?, ?, 'generated')`,
    [userId, requested.toFixed(8), nonce]
  );
  const snapshotId = insert.insertId;
  const ref = `claim_snapshot:${snapshotId}`;

  if (lockRows.length) {
    const ids = lockRows.map((row) => row.id);
    const placeholders = ids.map(() => '?').join(',');
    const [update] = await conn.execute(
      `UPDATE reward_ledger SET status = 'locked', reference_id = ? WHERE user_id = ? AND status = 'available' AND id IN (${placeholders})`,
      [ref, userId, ...ids]
    );
    if (update.affectedRows !== ids.length) throw new Error('Unable to lock every reward row for this claim.');
  }

  if (splitRow) {
    await conn.execute(
      `UPDATE reward_ledger SET amount = ? WHERE id = ? AND user_id = ? AND status = 'available'`,
      [splitRow.remainingAmount.toFixed(8), splitRow.row.id, userId]
    );
    await conn.execute(
      `INSERT INTO reward_ledger (user_id, source, reward_phase, action_type, amount, status, reference_id, user_level_at_time)
       VALUES (?, ?, ?, ?, ?, 'locked', ?, ?)`,
      [
        userId,
        splitRow.row.source || 'manual',
        splitRow.row.reward_phase || 'phase1',
        splitRow.row.action_type || 'claim_split',
        splitRow.claimAmount.toFixed(8),
        ref,
        splitRow.row.user_level_at_time || 'beginner',
      ]
    );
  }

  return { snapshot_id: snapshotId, user_id: userId, amount: requested.toFixed(8), nonce, status: 'generated' };
}

async function signClaim(snapshot, walletAddress) {
  const privateKey = config.claimSignerPrivateKey.startsWith('0x') ? config.claimSignerPrivateKey : `0x${config.claimSignerPrivateKey}`;
  const signer = new Wallet(privateKey);
  const amountWei = decimalToWei(snapshot.amount, Number(token.decimals || 18));
  const deadline = Math.floor(Date.now() / 1000) + 900;
  const signature = await signer.signTypedData(
    {
      name: 'CoinRex Claim Distributor',
      version: '1',
      chainId: Number(claimDeployment.chainId || distributor.chainId || 137),
      verifyingContract: distributor.contractAddress,
    },
    {
      Claim: [
        { name: 'claimant', type: 'address' },
        { name: 'snapshotId', type: 'uint256' },
        { name: 'amount', type: 'uint256' },
        { name: 'deadline', type: 'uint256' },
      ],
    },
    {
      claimant: walletAddress,
      snapshotId: String(snapshot.snapshot_id),
      amount: amountWei,
      deadline: String(deadline),
    }
  );
  return {
    snapshot_id: snapshot.snapshot_id,
    amount: snapshot.amount,
    amount_wei: amountWei,
    nonce: snapshot.nonce,
    wallet_address: walletAddress,
    network_slug: claimDeployment.networkSlug,
    chain_id: Number(claimDeployment.chainId || distributor.chainId || 137),
    contract_address: distributor.contractAddress,
    rex_token_address: distributor.rexTokenAddress || '',
    claim_fee_wei: String(distributor.claimFee || '0'),
    claim_fee_pol: String(distributor.claimFeeFormatted || ''),
    deadline,
    signature,
    claim_signer: signer.address,
  };
}

async function finalizeSnapshot(snapshotId, userId, txHash, conn) {
  const ref = `claim_snapshot:${snapshotId}`;
  await conn.execute(`UPDATE claim_snapshots SET status = 'used' WHERE id = ? AND user_id = ?`, [snapshotId, userId]);
  const [update] = await conn.execute(
    `UPDATE reward_ledger SET status = 'claimed', reference_id = ? WHERE user_id = ? AND status = 'locked' AND reference_id = ?`,
    [`claim_tx:${txHash}`, userId, ref]
  );
  return update.affectedRows;
}

async function releaseSnapshot(snapshotId, userId, conn) {
  const ref = `claim_snapshot:${snapshotId}`;
  await conn.execute(`UPDATE claim_snapshots SET status = 'expired' WHERE id = ? AND user_id = ? AND status = 'generated'`, [snapshotId, userId]);
  const [update] = await conn.execute(
    `UPDATE reward_ledger SET status = 'available', reference_id = NULL WHERE user_id = ? AND status = 'locked' AND reference_id = ?`,
    [userId, ref]
  );
  return update.affectedRows;
}

module.exports = {
  distributor,
  token,
  eligibility,
  balance,
  generateSnapshot,
  signClaim,
  finalizeSnapshot,
  releaseSnapshot,
};
