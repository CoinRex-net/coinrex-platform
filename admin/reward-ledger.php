<?php
$page_title = 'Reward Ledger';
$activePage = 'reward-ledger';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);
$ledger_filters = [
    'user' => trim((string) ($_GET['user'] ?? '')),
    'source' => trim((string) ($_GET['source'] ?? '')),
    'phase' => trim((string) ($_GET['phase'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
];
$ledger_rows = adminRewardGetLedgerRows($db, $ledger_filters);
$claim_rows = adminRewardGetClaimRows($db);
?>

<div class="panel">
    <div class="admin-toolbar">
        <div>
            <span class="admin-kicker">Ledger</span>
            <h2 style="margin:10px 0 0;">Reward Ledger</h2>
        </div>
        <form method="GET" action="" class="inline-form">
            <input type="text" name="user" placeholder="User or ID" value="<?php echo htmlspecialchars($ledger_filters['user'], ENT_QUOTES, 'UTF-8'); ?>">
            <select name="source">
                <option value="">All sources</option>
                <?php foreach (['mini_task', 'referral', 'review', 'bonus'] as $source): ?>
                    <option value="<?php echo $source; ?>" <?php echo $ledger_filters['source'] === $source ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $source)); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="phase">
                <option value="">All phases</option>
                <option value="phase1" <?php echo $ledger_filters['phase'] === 'phase1' ? 'selected' : ''; ?>>Phase 1</option>
                <option value="phase2" <?php echo $ledger_filters['phase'] === 'phase2' ? 'selected' : ''; ?>>Phase 2</option>
            </select>
            <select name="status">
                <option value="">All statuses</option>
                <?php foreach (['pending', 'locked', 'available', 'claimed'] as $status): ?>
                    <option value="<?php echo $status; ?>" <?php echo $ledger_filters['status'] === $status ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
            <tr>
                <th>Entry</th>
                <th>User</th>
                <th>Source</th>
                <th>Phase</th>
                <th>Amount</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($ledger_rows as $ledger_row): ?>
                <tr>
                    <td data-label="Entry">
                        <strong>#<?php echo (int) $ledger_row['id']; ?></strong><br>
                        <span class="muted"><?php echo htmlspecialchars((string) ($ledger_row['action_type'] ?? 'credit'), ENT_QUOTES, 'UTF-8'); ?></span><br>
                        <span class="muted"><?php echo htmlspecialchars((string) ($ledger_row['reference_id'] ?? 'n/a'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td data-label="User">
                        <strong><?php echo htmlspecialchars((string) ($ledger_row['username'] ?? ('User ' . $ledger_row['user_id'])), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <span class="muted">ID <?php echo (int) $ledger_row['user_id']; ?></span>
                    </td>
                    <td data-label="Source"><?php echo htmlspecialchars((string) $ledger_row['source'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Phase"><?php echo htmlspecialchars(strtoupper((string) $ledger_row['reward_phase']), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Amount"><strong><?php echo number_format((float) ($ledger_row['amount'] ?? 0), 4); ?></strong></td>
                    <td data-label="Status"><span class="status-pill <?php echo ($ledger_row['status'] ?? '') === 'available' ? 'status-approved' : (($ledger_row['status'] ?? '') === 'locked' ? 'status-pending' : 'status-disabled'); ?>"><?php echo htmlspecialchars((string) $ledger_row['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Claims</span>
            <h2>Claim Snapshots</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
            <tr>
                <th>Claim</th>
                <th>User</th>
                <th>Status</th>
                <th>Nonce</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($claim_rows as $claim): ?>
                <tr>
                    <td data-label="Claim">
                        <strong>#<?php echo (int) $claim['id']; ?></strong><br>
                        <span class="muted"><?php echo number_format((float) ($claim['total_amount'] ?? 0), 2); ?> $REX</span><br>
                        <span class="muted"><?php echo htmlspecialchars((string) $claim['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td data-label="User">
                        <strong><?php echo htmlspecialchars((string) ($claim['username'] ?? ('User ' . $claim['user_id'])), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <span class="muted"><?php echo htmlspecialchars(ucfirst((string) ($claim['level'] ?? 'beginner')), ENT_QUOTES, 'UTF-8'); ?></span><br>
                        <?php if (!empty($claim['reward_frozen'])): ?><span class="status-pill status-rejected">Frozen</span><?php endif; ?>
                    </td>
                    <td data-label="Status"><span class="status-pill <?php echo ($claim['status'] ?? '') === 'generated' ? 'status-pending' : (($claim['status'] ?? '') === 'used' ? 'status-approved' : 'status-disabled'); ?>"><?php echo htmlspecialchars((string) $claim['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="Nonce"><code><?php echo htmlspecialchars(substr((string) $claim['nonce'], -10), ENT_QUOTES, 'UTF-8'); ?></code></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
