<?php
$page_title = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$has_content_flags_table = tableExists('content_flags');

// ── Main Site (CoinRex / Client Area) Metrics ──
$total_users          = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_user_window_minutes = 5;
$active_users         = tableHasColumn('users', 'last_active')
    ? (int) $db->query("SELECT COUNT(*) FROM users WHERE last_active IS NOT NULL AND last_active >= (NOW() - INTERVAL {$active_user_window_minutes} MINUTE)")->fetchColumn()
    : (int) $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$pro_users            = (int) $db->query("SELECT COUNT(*) FROM users WHERE LOWER(COALESCE(level, 'beginner')) IN ('pro', 'premium')")->fetchColumn();
$expert_users         = (int) $db->query("SELECT COUNT(*) FROM users WHERE LOWER(COALESCE(level, 'beginner')) = 'expert'")->fetchColumn();
$pending_reviews      = (int) $db->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
$pending_proofs       = tableHasColumn('reviews', 'proof_status')
    ? (int) $db->query("SELECT COUNT(*) FROM reviews WHERE proof_status = 'pending'")->fetchColumn()
    : $pending_reviews;
$pending_projects     = (int) $db->query("SELECT COUNT(*) FROM projects WHERE approval_status IN ('pending', 'under_review')")->fetchColumn();
$approved_projects    = (int) $db->query("SELECT COUNT(*) FROM projects WHERE approval_status = 'approved'")->fetchColumn();
$flagged_reviews      = (int) $db->query("SELECT COUNT(*) FROM reviews WHERE status = 'flagged'")->fetchColumn();
$unread_messages      = (int) $db->query("SELECT COUNT(*) FROM messages WHERE status = 'unread'")->fetchColumn();
$flagged_projects     = $has_content_flags_table
    ? (int) $db->query("SELECT COUNT(DISTINCT target_id) FROM content_flags WHERE target_type = 'project' AND status = 'open'")->fetchColumn()
    : 0;

// ── DevHub Operations Metrics ──
$pending_dev_verifications = (int) $db->query("SELECT COUNT(*) FROM developer_verification WHERE status IN ('pending', 'change_requested')")->fetchColumn();
$pending_taskhub_reviews   = (int) $db->query("
    SELECT COUNT(*)
    FROM user_task_logs utl
    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
    WHERE mt.task_group = 'mission'
      AND utl.status = 'submitted'
")->fetchColumn();
$pending_boosthub_reviews  = (int) $db->query("
    SELECT COUNT(*)
    FROM user_task_logs utl
    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
    WHERE mt.task_group = 'boosthub'
      AND utl.status = 'submitted'
")->fetchColumn();
$total_devhub_users        = (int) $db->query("SELECT COUNT(DISTINCT user_id) FROM developer_verification WHERE status = 'approved'")->fetchColumn();
$total_devhub_projects     = (int) $db->query("SELECT COUNT(*) FROM projects WHERE approval_status = 'approved' AND created_by IN (SELECT user_id FROM developer_verification WHERE status = 'approved')")->fetchColumn();

// ── Security / Fraud Alerts ──
$has_fraud_events_table = tableExists('fraud_events');
$security_alerts = [];
$security_alert_totals = ['critical' => 0, 'warning' => 0, 'duplicate_signal' => 0];

if ($has_fraud_events_table) {
    $security_alert_totals['critical']        = (int) $db->query("SELECT COUNT(*) FROM fraud_events WHERE severity = 'critical' AND created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
    $security_alert_totals['warning']         = (int) $db->query("SELECT COUNT(*) FROM fraud_events WHERE severity = 'warning' AND created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
    $security_alert_totals['duplicate_signal'] = (int) $db->query("SELECT COUNT(*) FROM fraud_events WHERE event_type = 'registration_blocked_duplicate_signal' AND created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();

    $alerts_stmt = $db->query("SELECT event_type, severity, email, created_at, details_json, GREATEST(0, TIMESTAMPDIFF(SECOND, created_at, NOW())) AS age_seconds FROM fraud_events ORDER BY id DESC LIMIT 10");
    $security_alerts = $alerts_stmt ? $alerts_stmt->fetchAll() : [];
}

// ── Recent Activity (combined, limited to 10 total) ──
$recent_users    = $db->query("SELECT id, username, full_name, created_at, GREATEST(0, TIMESTAMPDIFF(SECOND, created_at, NOW())) AS age_seconds FROM users ORDER BY id DESC LIMIT 10")->fetchAll();
$recent_reviews  = $db->query("SELECT r.id, r.created_at, r.status, u.username, GREATEST(0, TIMESTAMPDIFF(SECOND, r.created_at, NOW())) AS age_seconds FROM reviews r LEFT JOIN users u ON u.id = r.user_id ORDER BY r.id DESC LIMIT 10")->fetchAll();
$recent_projects = $db->query("SELECT p.id, p.name, p.created_at, p.approval_status, u.username, GREATEST(0, TIMESTAMPDIFF(SECOND, p.created_at, NOW())) AS age_seconds FROM projects p LEFT JOIN users u ON u.id = p.created_by ORDER BY p.id DESC LIMIT 10")->fetchAll();

// ── System Health ──
$db_healthy = true;
try { $db->query("SELECT 1"); } catch (Throwable $e) { $db_healthy = false; }
$cache_available   = function_exists('apcu_fetch') || function_exists('wp_cache_get');
$session_available = session_status() === PHP_SESSION_ACTIVE || session_status() === PHP_SESSION_NONE;

// ── Determine if any pending items exist (for hidden section 0) ──
$total_pending = $pending_taskhub_reviews + $pending_boosthub_reviews + $pending_reviews + $pending_proofs + $pending_projects + $pending_dev_verifications + $unread_messages;
$has_pending = $total_pending > 0;

// ── Helpers ──
function metricCard($label, $value, $url, $iconClass = 'is-gold', $icon = 'fa-chart-simple') {
    $safe_label = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
    $safe_value = number_format((int) $value);
    $safe_url   = htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
    echo '<a class="dashboard-metric-card" href="' . $safe_url . '">';
    echo '<div class="metric-top">';
    echo '<span class="metric-icon ' . $iconClass . '"><i class="fas ' . $icon . '"></i></span>';
    echo '</div>';
    echo '<strong class="metric-value">' . $safe_value . '</strong>';
    echo '<span class="metric-label">' . $safe_label . '</span>';
    echo '</a>';
}

function pendingItem($label, $value, $url, $iconClass = 'is-gold', $icon = 'fa-clock') {
    if ($value <= 0) return;
    $safe_label = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
    $safe_value = number_format((int) $value);
    $safe_url   = htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
    echo '<a class="dashboard-pending-item" href="' . $safe_url . '">';
    echo '<span class="pi-icon ' . $iconClass . '"><i class="fas ' . $icon . '"></i></span>';
    echo '<div class="pi-meta"><strong>' . $safe_value . '</strong><span>' . $safe_label . '</span></div>';
    echo '</a>';
}

function timeAgo($datetime, $age_seconds = null) {
    if ($age_seconds !== null && is_numeric($age_seconds)) {
        $diff = max(0, (int) $age_seconds);
    } else {
        if (!$datetime) return '';
        $now = new DateTime();
        $then = new DateTime($datetime);
        $diff = max(0, $now->getTimestamp() - $then->getTimestamp());
    }
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}
?>

<!-- ════════════════════════════════════════════ -->
<!--  DASHBOARD HEADER                           -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-header">
    <div class="dashboard-header-left">
        <div class="dashboard-header-icon"><i class="fas fa-gauge-high"></i></div>
        <div class="dashboard-header-text">
            <h1>Operations Dashboard</h1>
            <p>Monitor and manage CoinRex platform operations in real-time</p>
        </div>
    </div>
    <span class="dashboard-header-badge"><i class="fas fa-circle"></i> Live</span>
</div>

<!-- ════════════════════════════════════════════ -->
<!--  SECTION 0: PENDING REQUESTS (HIDDEN)      -->
<!--  Only appears when there are pending items  -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-pending-bar<?php echo $has_pending ? ' has-pending' : ''; ?>">
    <div class="dashboard-pending-bar-inner">
        <div class="dashboard-pending-bar-header">
            <i class="fas fa-bell"></i>
            <strong>Pending Requests</strong>
            <span class="pending-count-badge"><?php echo number_format($total_pending); ?> total</span>
        </div>
        <div class="dashboard-pending-grid">
            <?php
            pendingItem('LearnHub Reviews',  $pending_taskhub_reviews,  ADMIN_BASE_URL . '/taskhub-review.php',       'is-gold',   'fa-list-check');
            pendingItem('BoostHub Reviews', $pending_boosthub_reviews, ADMIN_BASE_URL . '/boosthub.php',             'is-rose',   'fa-rocket');
            pendingItem('Pending Reviews',  $pending_reviews,          ADMIN_BASE_URL . '/reviews.php?status=pending', 'is-orange', 'fa-star');
            pendingItem('Pending Proofs',   $pending_proofs,           ADMIN_BASE_URL . '/reviews.php?status=pending', 'is-cyan',  'fa-file-circle-check');
            pendingItem('Pending Projects', $pending_projects,         ADMIN_BASE_URL . '/projects.php?status=pending', 'is-blue', 'fa-diagram-project');
            pendingItem('Dev Verifications',$pending_dev_verifications,ADMIN_BASE_URL . '/developers.php?status=pending', 'is-purple', 'fa-id-card');
            pendingItem('Unread Messages',  $unread_messages,          ADMIN_BASE_URL . '/messages.php',             'is-orange', 'fa-envelope');
            ?>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════ -->
<!--  SECTION 1: MAIN SITE OPERATIONS           -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-section-divider">
    <h2><i class="fas fa-globe"></i> Main Site Operations <span class="divider-sub">— CoinRex Client Area</span></h2>
</div>

<div class="dashboard-metric-grid">
    <?php
    metricCard('Total Users',       $total_users,       ADMIN_BASE_URL . '/users.php',                        'is-blue',   'fa-users');
    metricCard('Active Users Now',  $active_users,      ADMIN_BASE_URL . '/users.php',                        'is-green',  'fa-user-check');
    metricCard('Pro / Premium',     $pro_users,         ADMIN_BASE_URL . '/reward-users.php',                 'is-gold',   'fa-crown');
    metricCard('Expert Users',      $expert_users,      ADMIN_BASE_URL . '/reward-users.php',                 'is-purple', 'fa-gem');
    metricCard('Flagged Reviews',   $flagged_reviews,   ADMIN_BASE_URL . '/reviews.php?status=flagged',       'is-red',    'fa-flag');
    metricCard('Approved Projects', $approved_projects, ADMIN_BASE_URL . '/projects.php?status=approved',     'is-green',  'fa-circle-check');
    metricCard('Flagged Projects',  $flagged_projects,  ADMIN_BASE_URL . '/projects.php?status=flagged',      'is-red',    'fa-triangle-exclamation');
    ?>
</div>

<!-- ════════════════════════════════════════════ -->
<!--  SECTION 2: DEVHUB OPERATIONS              -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-section-divider">
    <h2><i class="fas fa-code"></i> DevHub Operations <span class="divider-sub">— Developer Ecosystem</span></h2>
</div>

<div class="dashboard-metric-grid">
    <?php
    metricCard('DevHub Users',    $total_devhub_users,    ADMIN_BASE_URL . '/developers.php',                    'is-purple', 'fa-laptop-code');
    metricCard('DevHub Projects', $total_devhub_projects, ADMIN_BASE_URL . '/projects.php?source=devhub',        'is-cyan',   'fa-cubes');
    ?>
</div>

<!-- ════════════════════════════════════════════ -->
<!--  SECTION 3: SECURITY & FRAUD OPERATIONS    -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-section-divider">
    <h2><i class="fas fa-shield-halved"></i> Security & Fraud Operations <span class="divider-sub">— Last 7 Days</span></h2>
</div>

<?php if ($has_fraud_events_table): ?>
<div class="dashboard-panel">
    <div class="dashboard-alert-summary">
        <div class="dashboard-alert-card is-critical">
            <div class="alert-icon"><i class="fas fa-circle-exclamation"></i></div>
            <div class="alert-meta">
                <strong><?php echo number_format($security_alert_totals['critical']); ?></strong>
                <span>Critical Events</span>
            </div>
        </div>
        <div class="dashboard-alert-card is-warning">
            <div class="alert-icon"><i class="fas fa-triangle-exclamation"></i></div>
            <div class="alert-meta">
                <strong><?php echo number_format($security_alert_totals['warning']); ?></strong>
                <span>Warnings</span>
            </div>
        </div>
        <div class="dashboard-alert-card is-info">
            <div class="alert-icon"><i class="fas fa-copy"></i></div>
            <div class="alert-meta">
                <strong><?php echo number_format($security_alert_totals['duplicate_signal']); ?></strong>
                <span>Duplicate Signals</span>
            </div>
        </div>
    </div>

    <?php if (!empty($security_alerts)): ?>
    <div class="dashboard-alert-list">
        <?php foreach ($security_alerts as $alert):
            $sev   = strtolower((string) ($alert['severity'] ?? 'info'));
            $type  = strtolower((string) ($alert['event_type'] ?? 'unknown'));
            $email = htmlspecialchars((string) ($alert['email'] ?? '—'), ENT_QUOTES, 'UTF-8');
            $time  = timeAgo((string) ($alert['created_at'] ?? ''), $alert['age_seconds'] ?? null);
            $dot_class = $sev === 'critical' ? 'is-critical' : ($sev === 'warning' ? 'is-warning' : 'is-info');
            $label = ucwords(str_replace('_', ' ', $type));
        ?>
        <div class="dashboard-alert-item">
            <span class="alert-dot <?php echo $dot_class; ?>"></span>
            <div class="alert-text">
                <strong><?php echo $label; ?></strong>
                <span><?php echo $email; ?></span>
            </div>
            <span class="alert-time"><?php echo $time; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="dashboard-empty"><i class="fas fa-shield-check"></i>No security alerts in the last 7 days.</div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="dashboard-panel">
    <div class="dashboard-empty"><i class="fas fa-shield"></i>Fraud detection table not available.</div>
</div>
<?php endif; ?>

<!-- ════════════════════════════════════════════ -->
<!--  QUICK ACTIONS + SYSTEM HEALTH             -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-split">
    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            <span class="panel-badge">All Sections</span>
        </div>
        <div class="dashboard-actions">
            <a href="<?php echo ADMIN_BASE_URL; ?>/reviews.php?status=pending" class="dashboard-action-btn">
                <i class="fas fa-star"></i> Review Queue
            </a>
            <a href="<?php echo ADMIN_BASE_URL; ?>/projects.php?status=pending" class="dashboard-action-btn">
                <i class="fas fa-diagram-project"></i> Pending Projects
            </a>
            <a href="<?php echo ADMIN_BASE_URL; ?>/users.php" class="dashboard-action-btn">
                <i class="fas fa-users"></i> Manage Users
            </a>
            <a href="<?php echo ADMIN_BASE_URL; ?>/messages.php" class="dashboard-action-btn">
                <i class="fas fa-envelope"></i> Messages
            </a>
            <a href="<?php echo ADMIN_BASE_URL; ?>/developers.php?status=pending" class="dashboard-action-btn">
                <i class="fas fa-id-card"></i> Dev Verifications
            </a>
            <a href="<?php echo ADMIN_BASE_URL; ?>/taskhub-review.php" class="dashboard-action-btn">
                <i class="fas fa-list-check"></i> LearnHub Review
            </a>
            <a href="<?php echo ADMIN_BASE_URL; ?>/boosthub.php" class="dashboard-action-btn">
                <i class="fas fa-rocket"></i> BoostHub Review
            </a>
            <a href="<?php echo ADMIN_BASE_URL; ?>/settings.php" class="dashboard-action-btn">
                <i class="fas fa-gear"></i> Settings
            </a>
        </div>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <h3><i class="fas fa-heart-pulse"></i> System Health</h3>
            <span class="panel-badge">Core Services</span>
        </div>
        <div class="dashboard-health">
            <div class="dashboard-health-item">
                <span class="health-dot <?php echo $db_healthy ? 'is-healthy' : 'is-error'; ?>"></span>
                <div class="health-meta"><strong>Database</strong><span><?php echo $db_healthy ? 'Connected' : 'Error'; ?></span></div>
            </div>
            <div class="dashboard-health-item">
                <span class="health-dot is-healthy"></span>
                <div class="health-meta"><strong>PHP Version</strong><span><?php echo phpversion(); ?></span></div>
            </div>
            <div class="dashboard-health-item">
                <span class="health-dot <?php echo $cache_available ? 'is-healthy' : 'is-warning'; ?>"></span>
                <div class="health-meta"><strong>Cache</strong><span><?php echo $cache_available ? 'Available' : 'Not detected'; ?></span></div>
            </div>
            <div class="dashboard-health-item">
                <span class="health-dot <?php echo $session_available ? 'is-healthy' : 'is-warning'; ?>"></span>
                <div class="health-meta"><strong>Sessions</strong><span><?php echo $session_available ? 'Ready' : 'Inactive'; ?></span></div>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════ -->
<!--  RECENT ACTIVITY + FOCUS (SIDE BY SIDE)    -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-split">
    <!-- Recent Activity -->
    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <h3><i class="fas fa-clock-rotate-left"></i> Recent Activity</h3>
            <a href="<?php echo ADMIN_BASE_URL; ?>/users.php" class="panel-link">View all →</a>
        </div>
        <div class="dashboard-activity">
            <?php
            $activity_count = 0;
            $max_activity = 10;
            // Users
            foreach ($recent_users as $u) {
                if ($activity_count >= $max_activity) break;
                $activity_count++;
                $name = htmlspecialchars((string) ($u['full_name'] ?: $u['username'] ?: 'User'), ENT_QUOTES, 'UTF-8');
                $time = timeAgo((string) ($u['created_at'] ?? ''), $u['age_seconds'] ?? null);
                echo '<div class="dashboard-activity-item">';
                echo '<div class="activity-icon is-user"><i class="fas fa-user-plus"></i></div>';
                echo '<div class="activity-meta"><strong>New user: ' . $name . '</strong><span>Registered</span></div>';
                echo '<span class="activity-time">' . $time . '</span></div>';
            }
            // Reviews
            foreach ($recent_reviews as $r) {
                if ($activity_count >= $max_activity) break;
                $activity_count++;
                $username = htmlspecialchars((string) ($r['username'] ?? 'User'), ENT_QUOTES, 'UTF-8');
                $status   = htmlspecialchars((string) ($r['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8');
                $time     = timeAgo((string) ($r['created_at'] ?? ''), $r['age_seconds'] ?? null);
                echo '<div class="dashboard-activity-item">';
                echo '<div class="activity-icon is-review"><i class="fas fa-star"></i></div>';
                echo '<div class="activity-meta"><strong>Review by ' . $username . '</strong><span>Status: ' . $status . '</span></div>';
                echo '<span class="activity-time">' . $time . '</span></div>';
            }
            // Projects
            foreach ($recent_projects as $p) {
                if ($activity_count >= $max_activity) break;
                $activity_count++;
                $pname   = htmlspecialchars((string) ($p['name'] ?: 'Project'), ENT_QUOTES, 'UTF-8');
                $pstatus = htmlspecialchars((string) ($p['approval_status'] ?? 'pending'), ENT_QUOTES, 'UTF-8');
                $time    = timeAgo((string) ($p['created_at'] ?? ''), $p['age_seconds'] ?? null);
                echo '<div class="dashboard-activity-item">';
                echo '<div class="activity-icon is-project"><i class="fas fa-diagram-project"></i></div>';
                echo '<div class="activity-meta"><strong>Project: ' . $pname . '</strong><span>Status: ' . $pstatus . '</span></div>';
                echo '<span class="activity-time">' . $time . '</span></div>';
            }
            if ($activity_count === 0):
            ?>
            <div class="dashboard-empty"><i class="fas fa-inbox"></i>No recent activity to display.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Focus / Suggested Next Actions -->
    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <h3><i class="fas fa-lightbulb"></i> Focus</h3>
            <span class="panel-badge">Prioritized</span>
        </div>
        <div class="dashboard-suggestions">
            <div class="dashboard-suggestion">
                <span class="suggestion-num">1</span>
                <span class="suggestion-text">Clear high-trust pending reviews first to keep the updated lane system responsive.</span>
            </div>
            <div class="dashboard-suggestion">
                <span class="suggestion-num">2</span>
                <span class="suggestion-text">Recheck flagged items to catch abuse before trust weighting compounds.</span>
            </div>
            <div class="dashboard-suggestion">
                <span class="suggestion-num">3</span>
                <span class="suggestion-text">Keep project verification aligned with live weighted <code>project_score</code> values.</span>
            </div>
            <div class="dashboard-suggestion">
                <span class="suggestion-num">4</span>
                <span class="suggestion-text">Review pending developer verifications to maintain ecosystem trust.</span>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
