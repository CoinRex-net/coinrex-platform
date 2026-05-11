<?php
$page_title = 'Rewards Overview';
$activePage = 'rewards';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);
$summary = adminRewardGetSummary($db);
$ledger_totals = $summary['ledger_totals'];
$top_claimed_users = adminRewardGetTopClaimedUsers($db, 10);
$top_rex_holders = adminRewardGetTopRexHolders($db, 10);

$reward_overview_chart = [
    'labels' => ['Earned', 'Claimed', 'Available', 'Locked', 'Pending'],
    'values' => [
        (float) ($ledger_totals['earned_total'] ?? 0),
        (float) ($ledger_totals['claimed_total'] ?? 0),
        (float) ($ledger_totals['available_total'] ?? 0),
        (float) ($ledger_totals['locked_total'] ?? 0),
        (float) ($ledger_totals['pending_total'] ?? 0),
    ],
];

$claimed_users_chart = [
    'labels' => array_map(static function ($row) {
        return (string) ($row['username'] ?? ('User ' . ($row['user_id'] ?? '')));
    }, $top_claimed_users),
    'values' => array_map(static function ($row) {
        return (float) ($row['claimed_total'] ?? 0);
    }, $top_claimed_users),
];

$holder_users_chart = [
    'labels' => array_map(static function ($row) {
        return (string) ($row['username'] ?? ('User ' . ($row['user_id'] ?? '')));
    }, $top_rex_holders),
    'values' => array_map(static function ($row) {
        return (float) ($row['total_balance'] ?? 0);
    }, $top_rex_holders),
];
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="panel admin-note-card">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Rewards</span>
            <h2>Rewards Control Center</h2>
        </div>
    </div>
    <div class="admin-metric-grid">
        <div class="admin-metric-card">
            <span class="admin-metric-label">Available</span>
            <strong><?php echo number_format((float) ($ledger_totals['available_total'] ?? 0), 2); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Locked</span>
            <strong><?php echo number_format((float) ($ledger_totals['locked_total'] ?? 0), 2); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Pending</span>
            <strong><?php echo number_format((float) ($ledger_totals['pending_total'] ?? 0), 2); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Claimed</span>
            <strong><?php echo number_format((float) ($ledger_totals['claimed_total'] ?? 0), 2); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Open Claims</span>
            <strong><?php echo number_format((int) ($summary['open_claims'] ?? 0)); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Frozen Users</span>
            <strong><?php echo number_format((int) ($summary['frozen_accounts'] ?? 0)); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">TaskHub Queue</span>
            <strong><?php echo number_format((int) ($summary['taskhub_reviews'] ?? 0)); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Pending Referrals</span>
            <strong><?php echo number_format((int) ($summary['referrals_pending'] ?? 0)); ?></strong>
        </div>
    </div>
</div>

<div class="admin-chart-grid">
    <div class="panel admin-chart-panel">
        <div class="admin-section-head">
            <div>
                <span class="admin-kicker">Analytics</span>
                <h3>Reward Flow Overview</h3>
                <p class="muted">Live comparison of earned, claimed, available, locked, and pending REX.</p>
            </div>
        </div>
        <div class="admin-chart-shell">
            <canvas id="rewardOverviewChart"></canvas>
        </div>
    </div>
    <div class="panel admin-chart-panel">
        <div class="admin-section-head">
            <div>
                <span class="admin-kicker">Top 10</span>
                <h3>Top Claimed Users</h3>
                <p class="muted">Users with the highest total claimed / withdrawn reward volume.</p>
            </div>
        </div>
        <div class="admin-chart-shell">
            <canvas id="topClaimedUsersChart"></canvas>
        </div>
    </div>
</div>

<div class="panel admin-chart-panel">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Holders</span>
            <h3>Top REX Holders</h3>
            <p class="muted">Ranking by total held balance using available + locked REX.</p>
        </div>
    </div>
    <div class="admin-chart-shell admin-chart-shell-lg">
        <canvas id="topRexHoldersChart"></canvas>
    </div>
</div>

<div class="admin-split-grid reward-admin-grid">
    <a href="<?php echo ADMIN_BASE_URL; ?>/task-management.php" class="panel admin-nav-panel">
        <span class="admin-kicker">Tasks</span>
        <h3>Task Management</h3>
        <p class="muted">Legacy task pool, limits, and status.</p>
    </a>
    <a href="<?php echo ADMIN_BASE_URL; ?>/boosthub.php" class="panel admin-nav-panel">
        <span class="admin-kicker">BoostHub</span>
        <h3>BoostHub Management</h3>
        <p class="muted">Boost task creation, rewards, and cooldowns.</p>
    </a>
    <a href="<?php echo ADMIN_BASE_URL; ?>/taskhub-review.php" class="panel admin-nav-panel">
        <span class="admin-kicker">Review</span>
        <h3>TaskHub Queue</h3>
        <p class="muted">Approve or reject manual submissions.</p>
    </a>
    <a href="<?php echo ADMIN_BASE_URL; ?>/reward-ledger.php" class="panel admin-nav-panel">
        <span class="admin-kicker">Ledger</span>
        <h3>Reward Ledger</h3>
        <p class="muted">Entries, phases, status filters, and claims.</p>
    </a>
    <a href="<?php echo ADMIN_BASE_URL; ?>/reward-users.php" class="panel admin-nav-panel">
        <span class="admin-kicker">Users</span>
        <h3>Reward Users</h3>
        <p class="muted">Balances, signals, and reward freezes.</p>
    </a>
    <a href="<?php echo ADMIN_BASE_URL; ?>/referrals.php" class="panel admin-nav-panel">
        <span class="admin-kicker">Referrals</span>
        <h3>Referral Validation</h3>
        <p class="muted">Manual qualification control.</p>
    </a>
</div>

<script>
(function () {
    if (typeof Chart === 'undefined') {
        return;
    }

    const axisColor = '#94a3b8';
    const gridColor = 'rgba(148, 163, 184, 0.12)';
    const labelColor = '#f8fafc';

    const rewardOverview = document.getElementById('rewardOverviewChart');
    if (rewardOverview) {
        new Chart(rewardOverview, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($reward_overview_chart['labels'], JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    label: 'REX',
                    data: <?php echo json_encode($reward_overview_chart['values'], JSON_UNESCAPED_UNICODE); ?>,
                    backgroundColor: ['#3b82f6', '#22c55e', '#60a5fa', '#f59e0b', '#a855f7'],
                    borderRadius: 10,
                    maxBarThickness: 48,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: labelColor } }
                },
                scales: {
                    x: { ticks: { color: axisColor }, grid: { display: false } },
                    y: { ticks: { color: axisColor }, grid: { color: gridColor } }
                }
            }
        });
    }

    const topClaimed = document.getElementById('topClaimedUsersChart');
    if (topClaimed) {
        new Chart(topClaimed, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($claimed_users_chart['labels'], JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    label: 'Claimed REX',
                    data: <?php echo json_encode($claimed_users_chart['values'], JSON_UNESCAPED_UNICODE); ?>,
                    backgroundColor: '#22c55e',
                    borderRadius: 10,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: labelColor } }
                },
                scales: {
                    x: { ticks: { color: axisColor }, grid: { color: gridColor } },
                    y: { ticks: { color: axisColor }, grid: { display: false } }
                }
            }
        });
    }

    const topHolders = document.getElementById('topRexHoldersChart');
    if (topHolders) {
        new Chart(topHolders, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($holder_users_chart['labels'], JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    label: 'Total Held REX',
                    data: <?php echo json_encode($holder_users_chart['values'], JSON_UNESCAPED_UNICODE); ?>,
                    backgroundColor: '#60a5fa',
                    borderRadius: 10,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { labels: { color: labelColor } }
                },
                scales: {
                    x: { ticks: { color: axisColor }, grid: { color: gridColor } },
                    y: { ticks: { color: axisColor }, grid: { display: false } }
                }
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
