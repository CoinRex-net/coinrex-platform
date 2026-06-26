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

<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-coins"></i></div>
            <div class="dashboard-header-text">
                <h1>Rewards Control Center</h1>
                <p>Monitor REX rewards, claims, ledger, and user balances</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-database"></i> <?php echo number_format((float) ($ledger_totals['earned_total'] ?? 0), 0); ?> REX
        </div>
    </div>

    <!-- ====== SECTION 1: METRICS ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-chart-bar"></i> Overview <span class="divider-sub">Reward system metrics</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-coins"></i> Rewards</span>
                <h3>Reward System Overview</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Live snapshot of REX token distribution, claims, and system health.</p>
            </div>
        </div>
        <div class="dashboard-metric-grid">
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-gold"><i class="fas fa-circle"></i></div></div>
                <span class="metric-value"><?php echo number_format((float) ($ledger_totals['available_total'] ?? 0), 2); ?></span>
                <span class="metric-label">Available REX</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-orange"><i class="fas fa-lock"></i></div></div>
                <span class="metric-value"><?php echo number_format((float) ($ledger_totals['locked_total'] ?? 0), 2); ?></span>
                <span class="metric-label">Locked REX</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-purple"><i class="fas fa-hourglass-half"></i></div></div>
                <span class="metric-value"><?php echo number_format((float) ($ledger_totals['pending_total'] ?? 0), 2); ?></span>
                <span class="metric-label">Pending REX</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-green"><i class="fas fa-check-circle"></i></div></div>
                <span class="metric-value"><?php echo number_format((float) ($ledger_totals['claimed_total'] ?? 0), 2); ?></span>
                <span class="metric-label">Claimed REX</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-blue"><i class="fas fa-file-invoice"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) ($summary['open_claims'] ?? 0)); ?></span>
                <span class="metric-label">Open Claims</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-red"><i class="fas fa-snowflake"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) ($summary['frozen_accounts'] ?? 0)); ?></span>
                <span class="metric-label">Frozen Users</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-cyan"><i class="fas fa-tasks"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) ($summary['taskhub_reviews'] ?? 0)); ?></span>
                <span class="metric-label">LearnHub Queue</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-rose"><i class="fas fa-user-friends"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) ($summary['referrals_pending'] ?? 0)); ?></span>
                <span class="metric-label">Pending Referrals</span>
            </div>
        </div>
    </div>

    <!-- ====== SECTION 2: CHARTS ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-chart-pie"></i> Analytics <span class="divider-sub">Reward flow and top users</span></h2>
    </div>

    <div class="dashboard-split" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="dashboard-panel" style="margin:0;">
            <div class="dashboard-panel-header">
                <div>
                    <span class="modal-kicker"><i class="fas fa-chart-bar"></i> Flow</span>
                    <h3>Reward Flow Overview</h3>
                    <p class="muted" style="margin:4px 0 0;font-size:12px;">Live comparison of earned, claimed, available, locked, and pending REX.</p>
                </div>
            </div>
            <div style="padding:16px;min-height:260px;">
                <canvas id="rewardOverviewChart"></canvas>
            </div>
        </div>
        <div class="dashboard-panel" style="margin:0;">
            <div class="dashboard-panel-header">
                <div>
                    <span class="modal-kicker"><i class="fas fa-trophy"></i> Top 10</span>
                    <h3>Top Claimed Users</h3>
                    <p class="muted" style="margin:4px 0 0;font-size:12px;">Users with the highest total claimed / withdrawn reward volume.</p>
                </div>
            </div>
            <div style="padding:16px;min-height:260px;">
                <canvas id="topClaimedUsersChart"></canvas>
            </div>
        </div>
    </div>

    <div class="dashboard-panel" style="margin-top:16px;">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-crown"></i> Holders</span>
                <h3>Top REX Holders</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Ranking by total held balance using available + locked REX.</p>
            </div>
        </div>
        <div style="padding:16px;min-height:300px;">
            <canvas id="topRexHoldersChart"></canvas>
        </div>
    </div>

    <!-- ====== SECTION 3: QUICK LINKS ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-link"></i> Quick Actions <span class="divider-sub">Reward management tools</span></h2>
    </div>

    <div class="dashboard-actions">
        <a href="<?php echo ADMIN_BASE_URL; ?>/task-management.php" class="dashboard-action-btn">
            <i class="fas fa-tasks"></i>
            <strong>Task Management</strong>
            <span>Legacy task pool, limits, and status</span>
        </a>
        <a href="<?php echo ADMIN_BASE_URL; ?>/boosthub.php" class="dashboard-action-btn">
            <i class="fas fa-bolt"></i>
            <strong>BoostHub Management</strong>
            <span>Boost task creation, rewards, and cooldowns</span>
        </a>
        <a href="<?php echo ADMIN_BASE_URL; ?>/taskhub-review.php" class="dashboard-action-btn">
            <i class="fas fa-clipboard-check"></i>
            <strong>LearnHub Queue</strong>
            <span>Approve or reject manual submissions</span>
        </a>
        <a href="<?php echo ADMIN_BASE_URL; ?>/reward-ledger.php" class="dashboard-action-btn">
            <i class="fas fa-book"></i>
            <strong>Reward Ledger</strong>
            <span>Entries, phases, status filters, and claims</span>
        </a>
        <a href="<?php echo ADMIN_BASE_URL; ?>/reward-users.php" class="dashboard-action-btn">
            <i class="fas fa-users"></i>
            <strong>Reward Users</strong>
            <span>Balances, signals, and reward freezes</span>
        </a>
        <a href="<?php echo ADMIN_BASE_URL; ?>/referrals.php" class="dashboard-action-btn">
            <i class="fas fa-user-friends"></i>
            <strong>Referral Validation</strong>
            <span>Manual qualification control</span>
        </a>
    </div>

</div><!-- /.dashboard-container -->

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
