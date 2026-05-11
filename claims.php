<?php
ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

$db = getDBConnection();
ensureRewardClaimSchema($db);

$user = getCurrentUser();
$claim_eligibility = getClaimEligibility((int) $user['id'], $db);
$balances = [
    'available' => getRewardLedgerBalance((int) $user['id'], 'available', $db),
    'locked' => getRewardLedgerBalance((int) $user['id'], 'locked', $db),
    'pending' => getRewardLedgerBalance((int) $user['id'], 'pending', $db),
    'claimed' => getRewardLedgerBalance((int) $user['id'], 'claimed', $db),
];

$open_claim_stmt = $db->prepare("
    SELECT id, total_amount, nonce, status, created_at
    FROM claim_snapshots
    WHERE user_id = ?
      AND status = 'generated'
    ORDER BY id DESC
    LIMIT 1
");
$open_claim_stmt->execute([(int) $user['id']]);
$open_claim = $open_claim_stmt->fetch() ?: null;

$history_stmt = $db->prepare("
    SELECT id, total_amount, status, created_at
    FROM claim_snapshots
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 8
");
$history_stmt->execute([(int) $user['id']]);
$claim_history = $history_stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/reward-pages.css">

<main class="reward-page">
    <div class="reward-page-shell">
        <section class="reward-panel">
            <div>
                <span class="reward-tag">Claim Center</span>
                <h1>Ledger-backed claims</h1>
                <p>Claim status, locked rewards, and prepared snapshots are shown here without exposing nonce details in the UI.</p>
                <div class="page-actions">
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="secondary-btn">Back to Dashboard</a>
                    <a href="<?php echo BASE_URL; ?>/taskhub.php" class="secondary-btn">TaskHub</a>
                </div>
            </div>
            <div class="reward-balance-box">
                <span>Claim status</span>
                <strong><?php echo !empty($claim_eligibility['eligible']) ? 'Enabled' : 'Locked'; ?></strong>
                <p class="reward-note"><?php echo htmlspecialchars((string) ($claim_eligibility['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </section>

        <section class="claim-grid">
            <div class="claim-card">
                <h3>Balances</h3>
                <div class="metric-grid">
                    <div class="claim-metric"><span>Available</span><strong id="availableBalance"><?php echo number_format((float) $balances['available'], 2); ?></strong></div>
                    <div class="claim-metric"><span>Locked</span><strong id="lockedBalance"><?php echo number_format((float) $balances['locked'], 2); ?></strong></div>
                    <div class="claim-metric"><span>Pending</span><strong id="pendingBalance"><?php echo number_format((float) $balances['pending'], 2); ?></strong></div>
                    <div class="claim-metric"><span>Claimed</span><strong id="claimedBalance"><?php echo number_format((float) $balances['claimed'], 2); ?></strong></div>
                </div>
            </div>

            <div class="claim-card" id="claimActionCard" data-open-claim-nonce="<?php echo htmlspecialchars((string) ($open_claim['nonce'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <h3>Claim</h3>
                <p id="claimStatusText"><?php echo htmlspecialchars((string) ($claim_eligibility['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="page-actions">
                    <button type="button" id="generateClaimButton" class="primary-btn" <?php echo empty($claim_eligibility['eligible']) || $open_claim ? 'disabled' : ''; ?>>
                        <?php echo $open_claim ? 'Claim Prepared' : 'Generate Claim'; ?>
                    </button>
                </div>
                <?php if ($open_claim): ?>
                    <div class="status-chip" id="openClaimStatus">Snapshot #<?php echo (int) $open_claim['id']; ?> • <?php echo htmlspecialchars((string) $open_claim['status'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <p class="reward-note" id="openClaimAmount"><?php echo number_format((float) ($open_claim['total_amount'] ?? 0), 2); ?> $REX</p>
                <?php else: ?>
                    <div class="status-chip" id="openClaimStatus">No open snapshot</div>
                    <p class="reward-note" id="openClaimAmount"></p>
                <?php endif; ?>
            </div>
        </section>

        <section class="history-card">
            <h3>History</h3>
            <?php if (!empty($claim_history)): ?>
                <?php foreach ($claim_history as $snapshot): ?>
                    <div class="history-row">
                        <div>
                            <strong>Snapshot #<?php echo (int) $snapshot['id']; ?></strong>
                            <span><?php echo htmlspecialchars((string) $snapshot['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="history-row-right">
                            <strong><?php echo number_format((float) ($snapshot['total_amount'] ?? 0), 2); ?> $REX</strong>
                            <span><?php echo htmlspecialchars((string) ($snapshot['status'] ?? 'generated'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="history-row">
                    <div>
                        <strong>No claim history yet</strong>
                        <span>Generated claim snapshots will appear here.</span>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </div>
</main>

<script>
(function() {
    const claimUrl = <?php echo json_encode(BASE_URL . '/api/generate_claim.php'); ?>;
    const overviewUrl = <?php echo json_encode(BASE_URL . '/api/reward_overview.php'); ?>;
    const button = document.getElementById('generateClaimButton');

    async function postForm(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(body),
        });
        return response.json();
    }

    async function refreshOverview() {
        const response = await fetch(overviewUrl, { credentials: 'same-origin' });
        const data = await response.json();
        if (!data.success) {
            return;
        }

        document.getElementById('availableBalance').textContent = Number(data.balances.available || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('lockedBalance').textContent = Number(data.balances.locked || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('pendingBalance').textContent = Number(data.balances.pending || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('claimedBalance').textContent = Number(data.balances.claimed || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('claimStatusText').textContent = data.claim_eligibility.message || '';

        if (data.open_claim) {
            document.getElementById('openClaimStatus').textContent = 'Snapshot #' + data.open_claim.id + ' • ' + data.open_claim.status;
            document.getElementById('openClaimAmount').textContent = Number(data.open_claim.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' $REX';
        } else {
            document.getElementById('openClaimStatus').textContent = 'No open snapshot';
            document.getElementById('openClaimAmount').textContent = '';
        }

        if (button) {
            button.disabled = !data.claim_eligibility.eligible || !!data.open_claim;
            button.textContent = data.open_claim ? 'Claim Prepared' : 'Generate Claim';
        }
    }

    if (button) {
        button.addEventListener('click', async function() {
            button.disabled = true;
            button.textContent = 'Generating...';

            try {
                const data = await postForm(claimUrl, {});
                if (!data.success) {
                    alert(data.message || 'Claim could not be generated.');
                    await refreshOverview();
                    return;
                }
                window.location.reload();
            } catch (error) {
                alert('Claim could not be generated.');
                await refreshOverview();
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
