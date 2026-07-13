<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

$db = getDBConnection();
ensureRewardClaimSchema($db);

$user = getCurrentUser();
$user_id = (int) ($user['id'] ?? 0);

$allowed_statuses = ['all', 'pending', 'available', 'locked', 'claimed'];
$allowed_sources = ['all', 'mini_task', 'referral', 'review', 'bonus'];
$allowed_phases = ['all', 'phase1', 'phase2'];
$allowed_flows = ['all', 'incoming', 'outgoing'];

$status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$source = strtolower(trim((string) ($_GET['source'] ?? 'all')));
$phase = strtolower(trim((string) ($_GET['phase'] ?? 'all')));
$flow = strtolower(trim((string) ($_GET['flow'] ?? 'all')));
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 12;

if (!in_array($status, $allowed_statuses, true)) {
    $status = 'all';
}
if (!in_array($source, $allowed_sources, true)) {
    $source = 'all';
}
if (!in_array($phase, $allowed_phases, true)) {
    $phase = 'all';
}
if (!in_array($flow, $allowed_flows, true)) {
    $flow = 'all';
}

$balances = [
    'available' => getRewardLedgerBalance($user_id, 'available', $db),
    'locked' => getRewardLedgerBalance($user_id, 'locked', $db),
    'pending' => getRewardLedgerBalance($user_id, 'pending', $db),
    'claimed' => getRewardLedgerBalance($user_id, 'claimed', $db),
];

$claim_eligibility = getClaimEligibility($user_id, $db);
$display_balance = getLedgerDisplayBalance($user_id, $db);

$summary_stmt = $db->prepare("
    SELECT
        COUNT(*) AS total_rows,
        COALESCE(SUM(CASE WHEN amount >= 0 THEN amount ELSE 0 END), 0) AS incoming_total,
        COALESCE(SUM(CASE WHEN amount < 0 OR status = 'claimed' THEN ABS(amount) ELSE 0 END), 0) AS outgoing_total
    FROM reward_ledger
    WHERE user_id = ?
");
$summary_stmt->execute([$user_id]);
$summary = $summary_stmt->fetch() ?: ['total_rows' => 0, 'incoming_total' => 0, 'outgoing_total' => 0];

$where = ["user_id = ?"];
$params = [$user_id];

if ($status !== 'all') {
    $where[] = "status = ?";
    $params[] = $status;
}
if ($source !== 'all') {
    $where[] = "source = ?";
    $params[] = $source;
}
if ($phase !== 'all') {
    $where[] = "reward_phase = ?";
    $params[] = $phase;
}
if ($flow === 'incoming') {
    $where[] = "amount >= 0 AND status <> 'claimed'";
} elseif ($flow === 'outgoing') {
    $where[] = "(amount < 0 OR status = 'claimed')";
}

$where_sql = implode(' AND ', $where);
$offset = ($page - 1) * $per_page;

$count_stmt = $db->prepare("SELECT COUNT(*) AS total FROM reward_ledger WHERE {$where_sql}");
$count_stmt->execute($params);
$total_rows = (int) ($count_stmt->fetch()['total'] ?? 0);
$total_pages = max(1, (int) ceil($total_rows / $per_page));

$ledger_stmt = $db->prepare("SELECT id, source, reward_phase, action_type, amount, status, reference_id, user_level_at_time, created_at FROM reward_ledger WHERE {$where_sql} ORDER BY id DESC LIMIT {$per_page} OFFSET {$offset}");
$ledger_stmt->execute($params);
$ledger_rows = $ledger_stmt->fetchAll() ?: [];

$claims_stmt = $db->prepare("SELECT id, total_amount, nonce, status, created_at FROM claim_snapshots WHERE user_id = ? ORDER BY id DESC LIMIT 8");
$claims_stmt->execute([$user_id]);
$claim_rows = $claims_stmt->fetchAll() ?: [];

$full_name = trim((string) ($user['full_name'] ?? ''));
if ($full_name === '') {
    $full_name = trim((string) ($user['username'] ?? 'User'));
}

$labelize = static function ($value) {
    $value = strtolower(trim((string) $value));
    if ($value === '') {
        return 'General';
    }
    return ucwords(str_replace(['_', '-'], ' ', $value));
};

$buildFilterUrl = static function (array $overrides = []) use ($status, $source, $phase, $flow) {
    $query = array_merge([
        'status' => $status,
        'source' => $source,
        'phase' => $phase,
        'flow' => $flow,
    ], $overrides);

    return BASE_URL . '/public/reward-history.php?' . http_build_query($query);
};

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/reward-pages.css">
<style>
.reward-history-shell{display:grid;gap:22px;overflow-x:clip}.reward-history-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(300px,.75fr);gap:20px;align-items:start;min-width:0}.reward-history-table-card,.reward-history-claim-card,.reward-history-bridge-card{background:rgba(15,23,42,.92);border:1px solid rgba(148,163,184,.12);border-radius:24px;box-shadow:0 18px 40px rgba(0,0,0,.16);padding:22px;min-width:0}.reward-history-filter-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}.reward-history-filter-head h3,.reward-history-table-card h3,.reward-history-claim-card h3,.reward-history-bridge-card h3{margin:0;color:#f8fafc;font-size:1.05rem}.reward-history-filter-head p,.reward-history-table-card p,.reward-history-claim-card p,.reward-history-bridge-card p{color:#9fb1c9;line-height:1.65;margin:8px 0 0}.reward-history-toolbar{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:18px;padding:16px;border-radius:18px;background:rgba(2,6,23,.28);border:1px solid rgba(148,163,184,.08)}.reward-filter-group,.reward-filter-group select,.reward-history-item,.reward-history-main,.reward-history-details,.reward-history-detail,.reward-side-stack{min-width:0}.reward-filter-group label{display:block;margin-bottom:8px;color:#cbd5e1;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.reward-filter-group select{width:100%;border-radius:14px;border:1px solid rgba(148,163,184,.18);background:rgba(2,6,23,.72);color:#f8fafc;padding:12px 14px;font:inherit}.reward-history-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.reward-history-stat{border-radius:20px;border:1px solid rgba(148,163,184,.14);background:linear-gradient(180deg,rgba(15,23,42,.88),rgba(2,6,23,.52));padding:18px 18px 16px;min-width:0}.reward-history-stat span{display:block;color:#91a4bd;font-size:11px;text-transform:uppercase;letter-spacing:.08em}.reward-history-stat strong{display:block;margin-top:10px;color:#f8fafc;font-size:1.5rem;min-width:0}.reward-history-list{display:grid;gap:14px;margin-top:14px}.reward-history-item{padding:18px;border-radius:20px;border:1px solid rgba(148,163,184,.10);background:linear-gradient(180deg,rgba(2,6,23,.46),rgba(15,23,42,.48))}.reward-history-item-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}.reward-history-main{display:grid;gap:6px}.reward-history-main strong{display:block;color:#f8fafc;font-size:14px;line-height:1.35;word-break:break-word}.reward-history-main span{color:#8fa5c2;font-size:12px;line-height:1.5;word-break:break-word}.reward-history-pills{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.reward-history-details{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:14px;padding-top:14px;border-top:1px solid rgba(148,163,184,.10)}.reward-history-detail{display:grid;gap:6px}.reward-history-detail span{color:#7f94b1;font-size:11px;text-transform:uppercase;letter-spacing:.08em}.reward-history-detail strong,.reward-history-detail small{color:#d9e4f2;font-size:13px;line-height:1.5;word-break:break-word}.reward-chip{display:inline-flex;align-items:center;justify-content:center;min-height:32px;padding:0 12px;border-radius:999px;font-size:11px;font-weight:800;letter-spacing:.04em;white-space:nowrap;max-width:100%}.reward-chip.status-available{background:rgba(16,185,129,.14);color:#a7f3d0}.reward-chip.status-locked{background:rgba(245,158,11,.14);color:#fde68a}.reward-chip.status-pending{background:rgba(59,130,246,.14);color:#bfdbfe}.reward-chip.status-claimed{background:rgba(168,85,247,.14);color:#ddd6fe}.reward-chip.flow-incoming{background:rgba(29,78,216,.14);color:#93c5fd}.reward-chip.flow-outgoing{background:rgba(239,68,68,.14);color:#fecaca}.reward-amount{font-weight:900;font-size:14px;letter-spacing:.01em;white-space:normal;word-break:break-word}.reward-amount.incoming{color:#86efac}.reward-amount.outgoing{color:#fca5a5}.reward-history-empty{padding:26px 20px;border-radius:18px;border:1px dashed rgba(148,163,184,.16);text-align:center;background:rgba(2,6,23,.2);color:#9fb1c9}.reward-soon-cta{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.reward-soon-btn{display:inline-flex;align-items:center;gap:10px;min-height:46px;padding:0 16px;border-radius:14px;border:1px solid rgba(148,163,184,.18);background:rgba(255,255,255,.03);color:#f8fafc;font-weight:800;cursor:not-allowed;text-decoration:none}.reward-soon-btn .soon-badge{display:inline-flex;align-items:center;justify-content:center;padding:4px 8px;border-radius:999px;background:#f59e0b;color:#111827;font-size:10px;font-weight:900;letter-spacing:.06em;text-transform:uppercase}.reward-bridge-note{margin-top:14px;padding:15px 16px;border-radius:18px;background:linear-gradient(135deg,rgba(29,78,216,.14),rgba(2,6,23,.45));border:1px solid rgba(96,165,250,.2);color:#dbeafe;line-height:1.65}.reward-history-pages{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.reward-history-pages a{display:inline-flex;align-items:center;justify-content:center;min-width:40px;padding:10px 14px;border-radius:12px;text-decoration:none;color:#cbd5e1;border:1px solid rgba(148,163,184,.2);background:rgba(255,255,255,.02)}.reward-history-pages a.active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}.reward-claim-history{display:grid;gap:12px;margin-top:14px}.reward-claim-item{display:flex;justify-content:space-between;gap:12px;padding:14px 0;border-top:1px solid rgba(148,163,184,.12)}.reward-claim-item:first-child{border-top:0;padding-top:0}.reward-claim-item strong{color:#f8fafc}.reward-claim-item span{color:#9fb1c9}.reward-side-stack{display:grid;gap:18px}.reward-readiness-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px}.reward-readiness-grid .claim-metric{padding:16px}.reward-clean-note{margin-top:12px;color:#7f94b1;font-size:12px;line-height:1.6}@media (max-width:1100px){.reward-history-grid{grid-template-columns:1fr}.reward-history-stats,.reward-history-toolbar,.reward-readiness-grid,.reward-history-details{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width:720px){.reward-history-stats,.reward-history-toolbar,.reward-readiness-grid,.reward-history-details{grid-template-columns:1fr}.reward-history-item-top{flex-direction:column;align-items:flex-start}.reward-history-pills{justify-content:flex-start}.reward-history-table-card,.reward-history-claim-card,.reward-history-bridge-card{padding:18px}}
</style>
<style>
.reward-history-shell{gap:18px}.reward-history-hero{align-items:center}.reward-history-grid{grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr);gap:18px}.reward-history-table-card,.reward-history-claim-card,.reward-history-bridge-card{border-radius:20px;padding:20px}.reward-history-filter-head{align-items:center}.reward-history-filter-head p,.reward-history-table-card p,.reward-history-claim-card p,.reward-history-bridge-card p{margin:0}.reward-history-toolbar{gap:12px;margin-top:16px;padding:12px;border-radius:16px;background:rgba(2,6,23,.22)}.reward-filter-group label{margin-bottom:6px;color:#8ea2bd;font-size:10px}.reward-filter-group select{border-radius:12px;padding:10px 12px}.reward-history-stats{gap:12px}.reward-history-stat{border-radius:18px;padding:16px}.reward-history-stat strong{margin-top:8px;font-size:1.35rem}.reward-balance-label{display:block;color:#91a4bd;font-size:11px;text-transform:uppercase;letter-spacing:.08em}.reward-note,.reward-bridge-note,.reward-clean-note{display:none}.reward-history-list{gap:10px}.reward-history-item{padding:15px;border-radius:16px}.reward-history-item-top{gap:14px;flex-wrap:nowrap}.reward-history-main span{display:none}.reward-history-date{display:block;color:#8fa5c2;font-size:12px;line-height:1.5;word-break:break-word}.reward-history-row-amount{display:grid;gap:8px;justify-items:end;text-align:right;flex-shrink:0}.reward-history-pills{gap:7px}.reward-history-details{display:none!important}.reward-history-meta{margin-top:10px;padding-top:10px;border-top:1px solid rgba(148,163,184,.09);min-width:0}.reward-history-meta summary{width:max-content;max-width:100%;color:#93c5fd;font-size:12px;font-weight:800;cursor:pointer;list-style:none}.reward-history-meta summary::-webkit-details-marker{display:none}.reward-history-meta summary::after{content:"+";display:inline-flex;margin-left:8px;color:#cbd5e1}.reward-history-meta[open] summary::after{content:"-"}.reward-history-meta-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:10px}.reward-history-detail span{font-size:10px}.reward-history-detail strong,.reward-history-detail small{font-size:12px}.reward-chip{min-height:28px;padding:0 10px;font-size:10px;font-weight:900}.reward-amount{font-size:15px}.reward-soon-cta{margin-top:14px}.reward-soon-btn{min-height:42px;border-radius:12px;padding:0 14px}.reward-compact-message{margin:10px 0 0!important;color:#c7d2fe!important;font-size:13px;line-height:1.55}.reward-readiness-grid{gap:10px;margin-top:12px}.reward-readiness-grid .claim-metric{padding:14px}.reward-claim-history{gap:10px;margin-top:12px}.reward-claim-item{padding:12px 0}.reward-claim-item strong,.reward-claim-item span{display:block}.reward-claim-item span{font-size:12px;margin-top:4px}@media (max-width:1100px){.reward-history-meta-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width:720px){.reward-history-meta-grid{grid-template-columns:1fr}.reward-history-item-top{flex-direction:column;align-items:flex-start}.reward-history-row-amount{justify-items:start;text-align:left}.reward-history-pills{justify-content:flex-start}.reward-history-table-card,.reward-history-claim-card,.reward-history-bridge-card{padding:16px}}
</style>
<style>
@media (max-width: 980px) {
    .reward-page {
        padding-bottom: 132px;
    }
    .reward-page-shell.reward-history-shell {
        width: 100%;
        max-width: 100%;
        padding-left: 14px;
        padding-right: 14px;
    }
    .reward-history-hero,
    .reward-history-grid,
    .reward-history-stats,
    .reward-history-toolbar,
    .reward-readiness-grid {
        grid-template-columns: 1fr !important;
    }
    .reward-history-table-card,
    .reward-history-claim-card,
    .reward-history-bridge-card,
    .reward-history-stat,
    .reward-balance-box {
        min-width: 0;
        width: 100%;
    }
    .reward-history-toolbar {
        display: grid;
        gap: 10px;
        padding: 12px;
    }
    .reward-history-toolbar .page-actions {
        grid-column: auto !important;
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        margin-top: 2px;
    }
    .reward-filter-group select,
    .reward-history-toolbar .primary-btn,
    .reward-history-toolbar .secondary-btn {
        width: 100%;
        min-width: 0;
    }
    .reward-side-stack {
        display: grid;
        grid-template-columns: 1fr;
        gap: 14px;
    }
    .reward-history-item-top {
        flex-wrap: wrap;
    }
}
@media (max-width: 560px) {
    .reward-page {
        padding-top: 16px;
        padding-bottom: 148px;
    }
    .reward-page-shell.reward-history-shell {
        padding-left: 10px;
        padding-right: 10px;
        gap: 14px;
    }
    .reward-panel,
    .reward-history-table-card,
    .reward-history-claim-card,
    .reward-history-bridge-card {
        border-radius: 18px;
        padding: 16px;
    }
    .reward-balance-box strong,
    .reward-history-stat strong,
    .claim-metric strong {
        font-size: clamp(1.35rem, 9vw, 2.1rem);
        line-height: 1.08;
        overflow-wrap: anywhere;
    }
    .reward-history-toolbar .page-actions {
        grid-template-columns: 1fr;
    }
    .reward-history-filter-head {
        align-items: flex-start;
    }
    .reward-history-item-top {
        flex-direction: column;
        align-items: stretch;
    }
    .reward-history-row-amount {
        justify-items: start;
        text-align: left;
        width: 100%;
    }
    .reward-history-pills {
        justify-content: flex-start;
    }
    .reward-claim-item,
    .history-row {
        flex-direction: column;
        align-items: flex-start;
    }
    .fixed-social,
    .fixed-back-to-top {
        display: none !important;
    }
}
</style>

<main class="reward-page">
    <div class="reward-page-shell reward-history-shell">
        <section class="reward-panel reward-history-hero">
            <div>
                <span class="reward-tag">Reward History</span>
                <h1>Reward History</h1>
                <div class="page-actions">
                    <a href="<?php echo BASE_URL; ?>/public/dashboard.php" class="secondary-btn">Back to Dashboard</a>
                </div>
            </div>
            <div class="reward-balance-box">
                <span class="reward-balance-label">Balance</span>
                <strong><?php echo number_format((float) $display_balance, 2); ?> $REX</strong>
                <p class="reward-note">Hello <?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?> — your reward history is synced with ledger-backed account activity.</p>
            </div>
        </section>

        <section class="reward-history-stats">
            <div class="reward-history-stat"><span>Available</span><strong><?php echo number_format((float) $balances['available'], 2); ?> $REX</strong></div>
            <div class="reward-history-stat"><span>Locked</span><strong><?php echo number_format((float) $balances['locked'], 2); ?> $REX</strong></div>
            <div class="reward-history-stat"><span>Pending</span><strong><?php echo number_format((float) $balances['pending'], 2); ?> $REX</strong></div>
            <div class="reward-history-stat"><span>Claimed</span><strong><?php echo number_format((float) $balances['claimed'], 2); ?> $REX</strong></div>
        </section>

        <section class="reward-history-grid">
            <div class="reward-history-table-card">
                <div class="reward-history-filter-head">
                    <div>
                        <h3>Ledger</h3>
                    </div>
                    <div class="status-chip"><?php echo (int) $summary['total_rows']; ?> entr<?php echo (int) $summary['total_rows'] === 1 ? 'y' : 'ies'; ?></div>
                </div>

                <form method="GET" class="reward-history-toolbar">
                    <div class="reward-filter-group">
                        <label for="statusFilter">Status</label>
                        <select id="statusFilter" name="status">
                            <?php foreach ($allowed_statuses as $option): ?>
                                <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $status === $option ? 'selected' : ''; ?>><?php echo ucfirst($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="reward-filter-group">
                        <label for="sourceFilter">Source</label>
                        <select id="sourceFilter" name="source">
                            <?php foreach ($allowed_sources as $option): ?>
                                <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $source === $option ? 'selected' : ''; ?>><?php echo $option === 'all' ? 'All sources' : $labelize($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="reward-filter-group">
                        <label for="phaseFilter">Phase</label>
                        <select id="phaseFilter" name="phase">
                            <?php foreach ($allowed_phases as $option): ?>
                                <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $phase === $option ? 'selected' : ''; ?>><?php echo $option === 'all' ? 'All phases' : strtoupper($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="reward-filter-group">
                        <label for="flowFilter">Flow</label>
                        <select id="flowFilter" name="flow">
                            <?php foreach ($allowed_flows as $option): ?>
                                <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $flow === $option ? 'selected' : ''; ?>><?php echo ucfirst($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="page-actions" style="grid-column:1/-1;">
                        <button type="submit" class="primary-btn">Apply filters</button>
                        <a href="<?php echo BASE_URL; ?>/public/reward-history.php" class="secondary-btn">Reset</a>
                    </div>
                </form>

                <div class="reward-history-list">
                        <?php if (empty($ledger_rows)): ?>
                            <div class="reward-history-empty">No reward ledger entries match the selected filters yet.</div>
                        <?php else: ?>
                            <?php foreach ($ledger_rows as $row): ?>
                                <?php
                                    $row_status = strtolower((string) ($row['status'] ?? 'pending'));
                                    $is_outgoing = ((float) ($row['amount'] ?? 0) < 0) || $row_status === 'claimed';
                                    $direction = $is_outgoing ? 'Outgoing' : 'Incoming';
                                    $amount = (float) ($row['amount'] ?? 0);
                                ?>
                                <article class="reward-history-item">
                                    <div class="reward-history-item-top">
                                        <div class="reward-history-main">
                                            <strong><?php echo htmlspecialchars($labelize($row['action_type'] ?? 'credit'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <time class="reward-history-date"><?php echo htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></time>
                                            <span><?php echo htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($row['reference_id'])): ?> • Ref: <?php echo htmlspecialchars((string) $row['reference_id'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></span>
                                        </div>
                                        <div class="reward-history-row-amount">
                                            <strong class="reward-amount <?php echo $is_outgoing ? 'outgoing' : 'incoming'; ?>"><?php echo $is_outgoing ? '-' : '+'; ?><?php echo number_format(abs($amount), 2); ?> $REX</strong>
                                            <div class="reward-history-pills">
                                                <span class="reward-chip <?php echo $is_outgoing ? 'flow-outgoing' : 'flow-incoming'; ?>"><?php echo $direction; ?></span>
                                                <span class="reward-chip status-<?php echo htmlspecialchars($row_status, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucfirst($row_status), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <details class="reward-history-meta">
                                        <summary>Details</summary>
                                        <div class="reward-history-meta-grid">
                                            <div class="reward-history-detail">
                                                <span>Source</span>
                                                <strong><?php echo htmlspecialchars($labelize($row['source'] ?? 'bonus'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            </div>
                                            <div class="reward-history-detail">
                                                <span>Phase</span>
                                                <strong><?php echo htmlspecialchars(strtoupper((string) ($row['reward_phase'] ?? 'phase1')), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            </div>
                                            <div class="reward-history-detail">
                                                <span>Level</span>
                                                <small><?php echo htmlspecialchars($labelize($row['user_level_at_time'] ?? 'general'), ENT_QUOTES, 'UTF-8'); ?></small>
                                            </div>
                                            <div class="reward-history-detail">
                                                <span>Ref</span>
                                                <small><?php echo htmlspecialchars((string) ($row['reference_id'] ?? 'None'), ENT_QUOTES, 'UTF-8'); ?></small>
                                            </div>
                                        </div>
                                    </details>
                                    <div class="reward-history-details">
                                        <div class="reward-history-detail">
                                            <span>Source</span>
                                            <strong><?php echo htmlspecialchars($labelize($row['source'] ?? 'bonus'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </div>
                                        <div class="reward-history-detail">
                                            <span>Phase</span>
                                            <strong><?php echo htmlspecialchars(strtoupper((string) ($row['reward_phase'] ?? 'phase1')), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </div>
                                        <div class="reward-history-detail">
                                            <span>Level Context</span>
                                            <small><?php echo htmlspecialchars($labelize($row['user_level_at_time'] ?? 'general'), ENT_QUOTES, 'UTF-8'); ?></small>
                                        </div>
                                        <div class="reward-history-detail">
                                            <span>Amount</span>
                                            <strong class="reward-amount <?php echo $is_outgoing ? 'outgoing' : 'incoming'; ?>"><?php echo $is_outgoing ? '-' : '+'; ?><?php echo number_format(abs($amount), 2); ?> $REX</strong>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div class="reward-history-pages">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="<?php echo htmlspecialchars($buildFilterUrl(['page' => $i]), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="reward-side-stack">
                <section class="reward-history-bridge-card">
                    <h3>Claim Center</h3>
                    <p>Claim infrastructure is being prepared for CoinRex’s on-chain release. Until then, this area helps users understand claim readiness without exposing unfinished claim operations.</p>
                    <div class="reward-soon-cta">
                        <span class="reward-soon-btn"><i class="fas fa-gift"></i><span>Claim</span><span class="soon-badge">Soon</span></span>
                    </div>
                    <div class="reward-bridge-note">The claim section will be activated after CoinRex’s on-chain deployment is fully completed. Thank you for your patience while we finalize the blockchain rollout and security checks.</div>
                    <div class="page-actions" style="margin-top:16px;">
                        <span class="status-chip"><?php echo !empty($claim_eligibility['eligible']) ? 'Eligible once opened' : 'Not eligible yet'; ?></span>
                    </div>
                </section>

                <section class="reward-history-claim-card">
                    <h3>Readiness</h3>
                    <p class="reward-compact-message"><?php echo htmlspecialchars((string) ($claim_eligibility['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="reward-readiness-grid">
                        <div class="claim-metric"><span>Incoming</span><strong><?php echo number_format((float) ($summary['incoming_total'] ?? 0), 2); ?></strong></div>
                        <div class="claim-metric"><span>Outgoing</span><strong><?php echo number_format((float) ($summary['outgoing_total'] ?? 0), 2); ?></strong></div>
                        <div class="claim-metric"><span>Threshold</span><strong><?php echo number_format((float) REWARD_CLAIM_MINIMUM_REX, 2); ?></strong></div>
                        <div class="claim-metric"><span>Review</span><strong><?php echo !empty($claim_eligibility['eligible']) ? 'Passed' : 'Monitoring'; ?></strong></div>
                    </div>
                    <p class="reward-clean-note">CoinRex handles reward abuse protection through in-app activity validation, trust signals, and behavioral review — no KYC step is required for this flow.</p>
                </section>

                <section class="reward-history-claim-card">
                    <h3>Claim Snapshots</h3>
                    <div class="reward-claim-history">
                        <?php if (empty($claim_rows)): ?>
                            <div class="reward-history-empty">No snapshots yet.</div>
                        <?php else: ?>
                            <?php foreach ($claim_rows as $snapshot): ?>
                                <div class="reward-claim-item">
                                    <div>
                                        <strong>Snapshot #<?php echo (int) ($snapshot['id'] ?? 0); ?></strong>
                                        <span><?php echo htmlspecialchars((string) ($snapshot['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div style="text-align:right;">
                                        <strong><?php echo number_format((float) ($snapshot['total_amount'] ?? 0), 2); ?> $REX</strong>
                                        <span><?php echo htmlspecialchars(ucfirst((string) ($snapshot['status'] ?? 'generated')), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </section>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
