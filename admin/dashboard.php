<?php
$page_title = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$has_content_flags_table = tableExists('content_flags');

$total_users = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_users = (int) $db->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$pending_projects = (int) $db->query("SELECT COUNT(*) FROM projects WHERE approval_status IN ('pending', 'under_review')")->fetchColumn();
$approved_projects = (int) $db->query("SELECT COUNT(*) FROM projects WHERE approval_status = 'approved'")->fetchColumn();
$pending_reviews = (int) $db->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn();
$pending_review_proofs = tableHasColumn('reviews', 'proof_status')
    ? (int) $db->query("SELECT COUNT(*) FROM reviews WHERE proof_status = 'pending'")->fetchColumn()
    : $pending_reviews;
$pending_developer_verifications = (int) $db->query("SELECT COUNT(*) FROM developer_verification WHERE status IN ('pending', 'change_requested')")->fetchColumn();
$pro_users = (int) $db->query("SELECT COUNT(*) FROM users WHERE LOWER(COALESCE(level, 'beginner')) IN ('pro', 'premium')")->fetchColumn();
$expert_users = (int) $db->query("SELECT COUNT(*) FROM users WHERE LOWER(COALESCE(level, 'beginner')) = 'expert'")->fetchColumn();
$flagged_reviews = (int) $db->query("SELECT COUNT(*) FROM reviews WHERE status = 'flagged'")->fetchColumn();
$pending_taskhub_reviews = (int) $db->query("
    SELECT COUNT(*)
    FROM user_task_logs utl
    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
    WHERE mt.task_group = 'mission'
      AND utl.status = 'submitted'
")->fetchColumn();
$pending_boosthub_reviews = (int) $db->query("
    SELECT COUNT(*)
    FROM user_task_logs utl
    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
    WHERE mt.task_group = 'boosthub'
      AND utl.status = 'submitted'
")->fetchColumn();
$unread_messages = (int) $db->query("SELECT COUNT(*) FROM messages WHERE status = 'unread'")->fetchColumn();
$flagged_projects = $has_content_flags_table
    ? (int) $db->query("SELECT COUNT(DISTINCT target_id) FROM content_flags WHERE target_type = 'project' AND status = 'open'")->fetchColumn()
    : 0;

$has_fraud_events_table = tableExists('fraud_events');
$security_alerts = [];
$security_alert_totals = [
    'critical' => 0,
    'warning' => 0,
    'duplicate_signal' => 0,
];

if ($has_fraud_events_table) {
    $security_alert_totals['critical'] = (int) $db->query("SELECT COUNT(*) FROM fraud_events WHERE severity = 'critical' AND created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
    $security_alert_totals['warning'] = (int) $db->query("SELECT COUNT(*) FROM fraud_events WHERE severity = 'warning' AND created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
    $security_alert_totals['duplicate_signal'] = (int) $db->query("SELECT COUNT(*) FROM fraud_events WHERE event_type = 'registration_blocked_duplicate_signal' AND created_at >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();

    $alerts_stmt = $db->query("
        SELECT event_type, severity, email, created_at, details_json
        FROM fraud_events
        ORDER BY id DESC
        LIMIT 10
    ");
    $security_alerts = $alerts_stmt ? $alerts_stmt->fetchAll() : [];
}

function adminMetricCard($label, $value, $url) {
    $safe_label = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
    $safe_value = number_format((int) $value);
    $safe_url = htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
    echo '<a class="admin-metric-card admin-metric-card-link" href="' . $safe_url . '">';
    echo '<span class="admin-metric-label">' . $safe_label . '</span>';
    echo '<strong>' . $safe_value . '</strong>';
    echo '</a>';
}
?>

<div class="panel admin-note-card">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Admin Hub</span>
            <h2>Operations Control Center</h2>
            <p class="muted">Track moderation load, trust engine activity, and high-impact queues from one place.</p>
        </div>
    </div>
    <div class="admin-metric-grid">
        <?php adminMetricCard('Total Users', $total_users, ADMIN_BASE_URL . '/users.php'); ?>
        <?php adminMetricCard('Active Users', $active_users, ADMIN_BASE_URL . '/users.php'); ?>
        <?php adminMetricCard('Pro Users', $pro_users, ADMIN_BASE_URL . '/reward-users.php'); ?>
        <?php adminMetricCard('Expert Users', $expert_users, ADMIN_BASE_URL . '/reward-users.php'); ?>
        <?php adminMetricCard('Pending Reviews', $pending_reviews, ADMIN_BASE_URL . '/reviews.php?status=pending'); ?>
        <?php adminMetricCard('Pending Proof Checks', $pending_review_proofs, ADMIN_BASE_URL . '/reviews.php?status=pending'); ?>
        <?php adminMetricCard('Pending Projects', $pending_projects, ADMIN_BASE_URL . '/projects.php?status=pending'); ?>
        <?php adminMetricCard('Pending Dev Verifications', $pending_developer_verifications, ADMIN_BASE_URL . '/developers.php?status=pending'); ?>
        <?php adminMetricCard('Pending TaskHub Review', $pending_taskhub_reviews, ADMIN_BASE_URL . '/taskhub-review.php'); ?>
        <?php adminMetricCard('Pending BoostHub Review', $pending_boosthub_reviews, ADMIN_BASE_URL . '/boosthub.php'); ?>
        <?php adminMetricCard('Unread Messages', $unread_messages, ADMIN_BASE_URL . '/messages.php'); ?>
    </div>
</div>

<div class="admin-split-grid">
    <div class="panel">
        <div class="admin-section-head">
            <div>
                <span class="admin-kicker">Queues</span>
                <h3>Trust Operations Queue</h3>
                <p class="muted">Review proof validation, weighted trust moderation, and developer identity checks stay visible here.</p>
            </div>
        </div>
        <div class="admin-metric-grid">
            <?php adminMetricCard('Approved Projects', $approved_projects, ADMIN_BASE_URL . '/projects.php?status=approved'); ?>
            <?php adminMetricCard('Flagged Projects', $flagged_projects, ADMIN_BASE_URL . '/projects.php?status=flagged'); ?>
            <?php adminMetricCard('Flagged Reviews', $flagged_reviews, ADMIN_BASE_URL . '/reviews.php?status=flagged'); ?>
        </div>
    </div>
    <div class="panel">
        <div class="admin-section-head">
            <div>
                <span class="admin-kicker">Focus</span>
                <h3>Suggested Next Actions</h3>
                <p class="muted">Start with pending proofs, then move to project verification and developer requests.</p>
            </div>
        </div>
        <div class="trust-detail-list">
            <div><strong>1.</strong> Clear high-trust pending reviews first to keep the updated lane system responsive.</div>
            <div><strong>2.</strong> Recheck flagged items to catch abuse before trust weighting compounds.</div>
            <div><strong>3.</strong> Keep project verification aligned with live weighted `project_score` values.</div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
