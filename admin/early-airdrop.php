<?php
$page_title = 'Early Adopter Airdrop';
$activePage = 'early-airdrop';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/pagination.php';

$db = getDBConnection();
ensureEarlyAirdropSchema($db);

// ── Stats (always computed) ──
$pool_total = (float) EARLY_AIRDROP_POOL_TOTAL;
$pool_remaining = getEarlyAirdropPoolRemaining($db);
$pool_used = $pool_total - $pool_remaining;
$pool_percent = $pool_total > 0 ? round(($pool_used / $pool_total) * 100, 2) : 0;

$total_airdrop_users = (int) $db->query("
    SELECT COUNT(DISTINCT user_id) FROM reward_ledger
    WHERE action_type = 'early_adopter_airdrop'
")->fetchColumn();

$total_referral_bonuses = (int) $db->query("
    SELECT COUNT(*) FROM reward_ledger
    WHERE action_type = 'early_adopter_referral'
")->fetchColumn();

$total_distributed = (float) $db->query("
    SELECT COALESCE(SUM(amount), 0) FROM reward_ledger
    WHERE action_type IN ('early_adopter_airdrop', 'early_adopter_referral')
      AND status = 'available'
")->fetchColumn();

$pending_count = (int) $db->query("
    SELECT COUNT(*) FROM reward_ledger
    WHERE action_type = 'early_adopter_airdrop'
      AND status = 'pending'
")->fetchColumn();

$unlocked_count = (int) $db->query("
    SELECT COUNT(*) FROM reward_ledger
    WHERE action_type = 'early_adopter_airdrop'
      AND status = 'available'
")->fetchColumn();

// ── Pagination for recent activity ──
$perPage = 20;
$page = paginationGetPage('page');
$offset = ($page - 1) * $perPage;

// Total count for pagination
$total_activity = (int) $db->query("
    SELECT COUNT(*) FROM reward_ledger
    WHERE action_type IN ('early_adopter_airdrop', 'early_adopter_referral')
")->fetchColumn();
$total_pages = max(1, (int) ceil($total_activity / $perPage));

// ── AJAX handler (must come before any HTML output) ──
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $ajax_page = max(1, (int) ($_GET['page'] ?? 1));
        $ajax_offset = ($ajax_page - 1) * $perPage;

        $recent = $db->prepare("
            SELECT rl.id, rl.user_id, rl.amount, rl.status, rl.action_type, rl.created_at,
                   u.username, u.email, u.level
            FROM reward_ledger rl
            LEFT JOIN users u ON u.id = rl.user_id
            WHERE rl.action_type IN ('early_adopter_airdrop', 'early_adopter_referral')
            ORDER BY rl.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $recent->execute([$perPage, $ajax_offset]);
        $recent_rows = $recent->fetchAll();

        $tableBody = '';
        if (empty($recent_rows)) {
            $tableBody = '<tr><td colspan="7" class="dashboard-empty"><i class="fas fa-rocket"></i><p>No airdrop activity yet.</p></td></tr>';
        } else {
            foreach ($recent_rows as $row) {
                $tableBody .= '<tr>';
                $tableBody .= '<td data-label="ID">' . (int) $row['id'] . '</td>';
                $tableBody .= '<td data-label="User">
                    <a href="' . ADMIN_BASE_URL . '/users.php?search=' . urlencode((string) ($row['username'] ?? '')) . '" style="color:var(--color-primary);">
                        ' . htmlspecialchars((string) ($row['username'] ?: $row['email'] ?: 'User #' . $row['user_id']), ENT_QUOTES, 'UTF-8') . '
                    </a>
                </td>';
                $tableBody .= '<td data-label="Level"><span class="dashboard-pill">' . htmlspecialchars(ucfirst((string) ($row['level'] ?? 'beginner')), ENT_QUOTES, 'UTF-8') . '</span></td>';
                $tableBody .= '<td data-label="Amount"><strong>' . number_format((float) $row['amount'], 0) . ' REX</strong></td>';
                $tableBody .= '<td data-label="Type">';
                if ($row['action_type'] === 'early_adopter_airdrop') {
                    $tableBody .= '<span class="dashboard-pill type-airdrop">Signup Airdrop</span>';
                } else {
                    $tableBody .= '<span class="dashboard-pill type-referral">Referral Bonus</span>';
                }
                $tableBody .= '</td>';
                $tableBody .= '<td data-label="Status">';
                if ($row['status'] === 'pending') {
                    $tableBody .= '<span class="dashboard-pill status-pending">Pending</span>';
                } elseif ($row['status'] === 'available') {
                    $tableBody .= '<span class="dashboard-pill status-available">Available</span>';
                } else {
                    $tableBody .= '<span class="dashboard-pill">' . htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') . '</span>';
                }
                $tableBody .= '</td>';
                $tableBody .= '<td data-label="Date">' . htmlspecialchars(date('Y-m-d H:i', strtotime((string) $row['created_at'])), ENT_QUOTES, 'UTF-8') . '</td>';
                $tableBody .= '</tr>';
            }
        }

        $paginationHtml = renderPagination($ajax_page, $total_pages, ADMIN_BASE_URL . '/early-airdrop.php', [], 'page');

        echo json_encode(paginationJsonResponse($tableBody, $paginationHtml, $ajax_page));
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── Non-AJAX: get first page of recent activity ──
$recent = $db->prepare("
    SELECT rl.id, rl.user_id, rl.amount, rl.status, rl.action_type, rl.created_at,
           u.username, u.email, u.level
    FROM reward_ledger rl
    LEFT JOIN users u ON u.id = rl.user_id
    WHERE rl.action_type IN ('early_adopter_airdrop', 'early_adopter_referral')
    ORDER BY rl.created_at DESC
    LIMIT ? OFFSET ?
");
$recent->execute([$perPage, $offset]);
$recent_rows = $recent->fetchAll();

// Handle pool reset
$reset_message = '';
$reset_message_type = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_pool']) && canCurrentAdmin('manage_rewards')) {
    $new_total = (float) ($_POST['new_pool_total'] ?? 0);
    if ($new_total > 0) {
        $db->prepare("
            UPDATE early_airdrop_pool
            SET remaining_rex = ?,
                total_allocated_signup = 0,
                total_allocated_referral = 0,
                signup_count = 0,
                referral_count = 0,
                is_active = 1,
                updated_at = NOW()
            WHERE id = 1
        ")->execute([$new_total]);
        $reset_message = 'Pool reset to ' . number_format($new_total, 0) . ' $REX.';
        $pool_total = $new_total;
        $pool_remaining = $new_total;
        $pool_used = 0;
        $pool_percent = 0;
    } else {
        $reset_message = 'Invalid pool total. Must be greater than zero.';
        $reset_message_type = 'error';
    }
}
?>

<?php paginationRenderStyles(); ?>

<style>
/* Metric card icon colors for airdrop */
.dashboard-metric-card .metric-icon.is-gold {
    background: rgba(212, 175, 55, 0.15);
    color: #f5d76e;
}
.dashboard-metric-card .metric-icon.is-green {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
}
.dashboard-metric-card .metric-icon.is-blue {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
}
.dashboard-metric-card .metric-icon.is-orange {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
}
.dashboard-metric-card .metric-icon.is-purple {
    background: rgba(168, 85, 247, 0.15);
    color: #c084fc;
}
.dashboard-metric-card .metric-icon.is-cyan {
    background: rgba(6, 182, 212, 0.15);
    color: #22d3ee;
}
.dashboard-metric-card .metric-icon.is-rose {
    background: rgba(244, 63, 94, 0.15);
    color: #fb7185;
}

/* ── Premium Pool Consumption Progress Bar ── */
.airdrop-progress-card {
    background: linear-gradient(135deg, rgba(15,23,42,0.6), rgba(30,41,59,0.4));
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 16px;
    padding: 24px 28px;
    margin: 0 20px 20px;
    position: relative;
    overflow: hidden;
}
.airdrop-progress-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at 30% 40%, rgba(212,175,55,0.04), transparent 60%);
    pointer-events: none;
}
.airdrop-progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.airdrop-progress-title {
    display: flex;
    align-items: center;
    gap: 10px;
}
.airdrop-progress-title i {
    font-size: 18px;
    color: #f5d76e;
}
.airdrop-progress-title span {
    font-size: 14px;
    font-weight: 600;
    color: #e2e8f0;
    letter-spacing: 0.02em;
}
.airdrop-progress-percent {
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.02em;
}
.airdrop-progress-percent.is-low { color: #4ade80; }
.airdrop-progress-percent.is-mid { color: #fbbf24; }
.airdrop-progress-percent.is-high { color: #f87171; }

/* Track */
.airdrop-progress-track {
    width: 100%;
    height: 32px;
    background: rgba(255,255,255,0.05);
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);
}

/* Fill bar with animated shimmer */
.airdrop-progress-fill {
    height: 100%;
    border-radius: 16px;
    position: relative;
    transition: width 1.2s cubic-bezier(0.22, 1, 0.36, 1);
    overflow: hidden;
}
.airdrop-progress-fill.is-low {
    background: linear-gradient(90deg, #22c55e, #4ade80);
}
.airdrop-progress-fill.is-mid {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}
.airdrop-progress-fill.is-high {
    background: linear-gradient(90deg, #ef4444, #f87171);
}

/* Shimmer animation overlay */
.airdrop-progress-fill::after {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    animation: airdropShimmer 3s ease-in-out infinite;
}
@keyframes airdropShimmer {
    0% { left: -100%; }
    100% { left: 200%; }
}

/* Glow effect at the leading edge */
.airdrop-progress-fill::before {
    content: '';
    position: absolute;
    right: -4px;
    top: -4px;
    width: 12px;
    height: 40px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    filter: blur(6px);
}

/* Label inside the bar */
.airdrop-progress-label {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 12px;
    font-weight: 700;
    color: #0f172a;
    text-shadow: 0 1px 2px rgba(0,0,0,0.15);
    z-index: 2;
    white-space: nowrap;
    letter-spacing: 0.02em;
}

/* Stats row below the bar */
.airdrop-progress-stats {
    display: flex;
    justify-content: space-between;
    margin-top: 12px;
    gap: 16px;
    flex-wrap: wrap;
}
.airdrop-progress-stat {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #94a3b8;
}
.airdrop-progress-stat i {
    font-size: 13px;
}
.airdrop-progress-stat strong {
    color: #e2e8f0;
    font-weight: 600;
}
.airdrop-progress-stat .stat-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.airdrop-progress-stat .stat-dot.is-used { background: #f59e0b; }
.airdrop-progress-stat .stat-dot.is-remaining { background: #4ade80; }

/* Pool management form */
.pool-form {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    flex-wrap: wrap;
    margin-top: 12px;
}
.pool-form .form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.pool-form .form-group label {
    font-size: 11px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.pool-form .form-group input {
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04);
    color: #e2e8f0;
    font-size: 14px;
    min-width: 200px;
}
.pool-form .form-group input:focus {
    outline: none;
    border-color: #d4af37;
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.15);
}

/* Badge styles for action_type */
.dashboard-pill.type-airdrop {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.2);
}
.dashboard-pill.type-referral {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.2);
}
.dashboard-pill.status-pending {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.2);
}
.dashboard-pill.status-available {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.2);
}
</style>

<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-rocket"></i></div>
            <div class="dashboard-header-text">
                <h1>Early Adopter Airdrop</h1>
                <p>Track the 7% REX token airdrop for the first 100,000 users</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-database"></i> <?php echo number_format($pool_total, 0); ?> REX Pool
        </div>
    </div>

    <?php if ($reset_message !== ''): ?>
    <div id="pageToast" data-message="<?php echo htmlspecialchars($reset_message, ENT_QUOTES, 'UTF-8'); ?>" data-type="<?php echo $reset_message_type === 'error' ? 'error' : 'success'; ?>" style="display:none;"></div>
    <?php endif; ?>

    <!-- ====== SECTION 1: POOL OVERVIEW ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-chart-bar"></i> Pool Overview <span class="divider-sub">Airdrop fund consumption</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-database"></i> Pool</span>
                <h3>Airdrop Pool Status</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Live snapshot of the 70,000,000 $REX early adopter pool.</p>
            </div>
        </div>

        <div class="dashboard-metric-grid">
            <div class="dashboard-metric-card">
                <div class="metric-top">
                    <span class="metric-label">Pool Total</span>
                    <div class="metric-icon is-gold"><i class="fas fa-database"></i></div>
                </div>
                <span class="metric-value"><?php echo number_format($pool_total, 0); ?></span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top">
                    <span class="metric-label">Remaining</span>
                    <div class="metric-icon is-green"><i class="fas fa-check-circle"></i></div>
                </div>
                <span class="metric-value"><?php echo number_format($pool_remaining, 0); ?></span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top">
                    <span class="metric-label">Used</span>
                    <div class="metric-icon is-orange"><i class="fas fa-chart-line"></i></div>
                </div>
                <span class="metric-value"><?php echo number_format($pool_used, 0); ?> (<?php echo $pool_percent; ?>%)</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top">
                    <span class="metric-label">Distributed</span>
                    <div class="metric-icon is-blue"><i class="fas fa-coins"></i></div>
                </div>
                <span class="metric-value"><?php echo number_format($total_distributed, 0); ?></span>
            </div>
        </div>

        <!-- Premium Pool Consumption Progress Bar -->
        <?php
            $bar_class = $pool_percent > 80 ? 'is-high' : ($pool_percent > 50 ? 'is-mid' : 'is-low');
            $percent_class = $pool_percent > 80 ? 'is-high' : ($pool_percent > 50 ? 'is-mid' : 'is-low');
        ?>
        <div class="airdrop-progress-card">
            <div class="airdrop-progress-header">
                <div class="airdrop-progress-title">
                    <i class="fas fa-water"></i>
                    <span>Pool Consumption</span>
                </div>
                <span class="airdrop-progress-percent <?php echo $percent_class; ?>"><?php echo $pool_percent; ?>%</span>
            </div>
            <div class="airdrop-progress-track">
                <div class="airdrop-progress-fill <?php echo $bar_class; ?>" style="width:<?php echo $pool_percent; ?>%;">
                    <span class="airdrop-progress-label"><?php echo number_format($pool_used, 0); ?> / <?php echo number_format($pool_total, 0); ?> REX</span>
                </div>
            </div>
            <div class="airdrop-progress-stats">
                <div class="airdrop-progress-stat">
                    <span class="stat-dot is-used"></span>
                    Used: <strong><?php echo number_format($pool_used, 0); ?> REX</strong>
                </div>
                <div class="airdrop-progress-stat">
                    <span class="stat-dot is-remaining"></span>
                    Remaining: <strong><?php echo number_format($pool_remaining, 0); ?> REX</strong>
                </div>
                <div class="airdrop-progress-stat">
                    <i class="fas fa-users" style="color:#60a5fa;"></i>
                    Users: <strong><?php echo number_format($total_airdrop_users); ?></strong>
                </div>
                <div class="airdrop-progress-stat">
                    <i class="fas fa-gift" style="color:#4ade80;"></i>
                    Referrals: <strong><?php echo number_format($total_referral_bonuses); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- ====== SECTION 2: USER STATS ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-users"></i> User Stats <span class="divider-sub">Airdrop distribution to users</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-users"></i> Users</span>
                <h3>Airdrop User Distribution</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">How many users have received airdrops and their unlock status.</p>
            </div>
        </div>

        <div class="dashboard-metric-grid">
            <div class="dashboard-metric-card">
                <div class="metric-top">
                    <span class="metric-label">Users with Airdrop</span>
                    <div class="metric-icon is-gold"><i class="fas fa-users"></i></div>
                </div>
                <span class="metric-value"><?php echo number_format($total_airdrop_users); ?></span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top">
                    <span class="metric-label">Pending (Not Unlocked)</span>
                    <div class="metric-icon is-orange"><i class="fas fa-clock"></i></div>
                </div>
                <span class="metric-value"><?php echo number_format($pending_count); ?></span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top">
                    <span class="metric-label">Unlocked (Available)</span>
                    <div class="metric-icon is-green"><i class="fas fa-check-circle"></i></div>
                </div>
                <span class="metric-value"><?php echo number_format($unlocked_count); ?></span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top">
                    <span class="metric-label">Referral Bonuses</span>
                    <div class="metric-icon is-blue"><i class="fas fa-gift"></i></div>
                </div>
                <span class="metric-value"><?php echo number_format($total_referral_bonuses); ?></span>
            </div>
        </div>
    </div>

    <!-- ====== SECTION 3: POOL MANAGEMENT ====== -->
    <?php if (canCurrentAdmin('manage_rewards')): ?>
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-tools"></i> Pool Management <span class="divider-sub">Admin controls for the airdrop pool</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-sliders-h"></i> Controls</span>
                <h3>Pool Reset</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Reset the airdrop pool total. This overwrites the current pool amount.</p>
            </div>
        </div>
        <div style="padding:16px 20px 20px;">
            <form method="POST" class="pool-form" onsubmit="return confirm('Are you sure you want to reset the airdrop pool? This will overwrite the current pool total.');">
                <div class="form-group">
                    <label for="new_pool_total">New Pool Total ($REX)</label>
                    <input type="number" name="new_pool_total" id="new_pool_total" value="<?php echo (int) $pool_total; ?>" step="1" min="1" required>
                </div>
                <button type="submit" name="reset_pool" class="btn btn-danger" style="padding:8px 20px;"><i class="fas fa-redo"></i> Reset Pool</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ====== SECTION 4: RECENT ACTIVITY ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-list"></i> Recent Activity <span class="divider-sub">Latest airdrop and referral bonus entries</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-clock"></i> Activity</span>
                <h3>Recent Airdrop Activity</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Last 50 airdrop signup bonuses and referral bonuses.</p>
            </div>
        </div>

        <div class="dashboard-table-wrap">
            <table class="dashboard-table" id="activityTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Level</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (empty($recent_rows)): ?>
                        <tr>
                            <td colspan="7" class="dashboard-empty">
                                <i class="fas fa-rocket"></i>
                                <p>No airdrop activity yet.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_rows as $row): ?>
                            <tr>
                                <td data-label="ID"><?php echo (int) $row['id']; ?></td>
                                <td data-label="User">
                                    <a href="<?php echo ADMIN_BASE_URL; ?>/users.php?search=<?php echo urlencode((string) ($row['username'] ?? '')); ?>" style="color:var(--color-primary);">
                                        <?php echo htmlspecialchars((string) ($row['username'] ?: $row['email'] ?: 'User #' . $row['user_id']), ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                </td>
                                <td data-label="Level">
                                    <span class="dashboard-pill"><?php echo htmlspecialchars(ucfirst((string) ($row['level'] ?? 'beginner')), ENT_QUOTES, 'UTF-8'); ?></span>
                                </td>
                                <td data-label="Amount"><strong><?php echo number_format((float) $row['amount'], 0); ?> REX</strong></td>
                                <td data-label="Type">
                                    <?php if ($row['action_type'] === 'early_adopter_airdrop'): ?>
                                        <span class="dashboard-pill type-airdrop">Signup Airdrop</span>
                                    <?php else: ?>
                                        <span class="dashboard-pill type-referral">Referral Bonus</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <?php if ($row['status'] === 'pending'): ?>
                                        <span class="dashboard-pill status-pending">Pending</span>
                                    <?php elseif ($row['status'] === 'available'): ?>
                                        <span class="dashboard-pill status-available">Available</span>
                                    <?php else: ?>
                                        <span class="dashboard-pill"><?php echo htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Date"><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime((string) $row['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div id="pagination">
            <?php echo renderPagination($page, $total_pages, ADMIN_BASE_URL . '/early-airdrop.php', [], 'page'); ?>
        </div>
    </div>

</div><!-- /.dashboard-container -->

<!-- Toast notification from server-side message -->
<script>
(function () {
    'use strict';
    var pageToast = document.getElementById('pageToast');
    if (pageToast) {
        var msg = pageToast.getAttribute('data-message');
        var type = pageToast.getAttribute('data-type') || 'info';
        if (msg) {
            setTimeout(function () {
                if (typeof showToast === 'function') {
                    showToast(msg, type);
                } else {
                    alert(msg);
                }
            }, 100);
        }
    }
})();
</script>

<?php
// AJAX pagination JS
paginationRenderJS([
    'tableBodyId' => 'tableBody',
    'paginationId' => 'pagination',
    'fetchUrl' => ADMIN_BASE_URL . '/early-airdrop.php',
    'filterFormId' => null,
    'extraParams' => [],
    'pageParam' => 'page',
]);
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
