<?php
require_once __DIR__ . '/includes/config.php';

$page_title = 'Dashboard';
$activePage = 'dashboard';

require_once __DIR__ . '/includes/header.php';

// Add dashboard specific CSS
echo '<link rel="stylesheet" href="' . BASE_URL . '/devhub/assets/css/dashboard.css">';
echo '<link rel="stylesheet" href="' . ASSETS_URL . '/css/rating-badge.css">';

$user_id = getCurrentUserId();
$db = getDevHubDB();
ensureLevelEngineSchema($db);
$has_content_flags_table = tableExists('content_flags');
$has_feature_status_column = tableHasColumn('projects', 'feature_status');
$has_featured_column = tableHasColumn('projects', 'is_featured');
$has_sponsored_column = tableHasColumn('projects', 'is_sponsored');
$has_feature_queue_type_column = tableHasColumn('projects', 'feature_queue_type');
$has_priority_review_status_column = tableHasColumn('projects', 'priority_review_status');
$has_sponsored_status_column = tableHasColumn('projects', 'sponsored_status');

$promotion_message = '';
$promotion_message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promotion_action'])) {
    requireAppCsrf((string) ($_POST['csrf_token'] ?? ''));
    try {
        $promotion_result = requestProjectPromotion(
            (int) ($_POST['project_id'] ?? 0),
            (int) $user_id,
            (string) ($_POST['promotion_action'] ?? ''),
            $db
        );
        $promotion_message = (string) ($promotion_result['message'] ?? 'Promotion request submitted.');
    } catch (Throwable $e) {
        $promotion_message = $e->getMessage();
        $promotion_message_type = 'error';
    }
}
$project_flags_join = $has_content_flags_table ? "
    LEFT JOIN (
        SELECT target_id, 1 AS has_open_flag
        FROM content_flags
        WHERE target_type = 'project'
          AND status = 'open'
        GROUP BY target_id
    ) project_flags ON project_flags.target_id = p.id
" : '';
$project_status_sql = $has_content_flags_table
    ? "CASE WHEN COALESCE(project_flags.has_open_flag, 0) = 1 THEN 'flagged' ELSE LOWER(COALESCE(NULLIF(TRIM(p.approval_status), ''), 'pending')) END"
    : "LOWER(COALESCE(NULLIF(TRIM(p.approval_status), ''), 'pending'))";

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Latest verification status from developer_verification table
$stmt = $db->prepare("SELECT status FROM developer_verification WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$verification_row = $stmt->fetch();
$latest_verification_status = strtolower(trim((string)($verification_row['status'] ?? '')));

// Get developer project stats and moderation queue
$stats_stmt = $db->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN {$project_status_sql} = 'approved' THEN 1 ELSE 0 END), 0) AS approved_projects,
        COALESCE(SUM(CASE WHEN {$project_status_sql} = 'pending' THEN 1 ELSE 0 END), 0) AS pending_projects,
        COALESCE(SUM(CASE WHEN {$project_status_sql} = 'under_review' THEN 1 ELSE 0 END), 0) AS under_review_projects,
        COALESCE(SUM(CASE WHEN {$project_status_sql} = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected_projects,
        COALESCE(SUM(CASE WHEN {$project_status_sql} = 'flagged' THEN 1 ELSE 0 END), 0) AS flagged_projects,
        COALESCE(SUM(CASE WHEN {$project_status_sql} = 'approved' THEN COALESCE(review_stats.approved_reviews_count, 0) ELSE 0 END), 0) AS approved_reviews_count,
        COALESCE(SUM(CASE WHEN {$project_status_sql} = 'approved' THEN COALESCE(review_stats.rejected_reviews_count, 0) ELSE 0 END), 0) AS rejected_reviews_count,
        COALESCE(SUM(CASE WHEN {$project_status_sql} = 'approved' THEN COALESCE(review_stats.flagged_reviews_count, 0) ELSE 0 END), 0) AS flagged_reviews_count,
        COALESCE(ROUND(AVG(CASE WHEN {$project_status_sql} = 'approved' AND COALESCE(p.total_reviews, 0) > 0 THEN p.avg_rating END), 1), 0) AS approved_avg_rating
    FROM projects p
    LEFT JOIN (
        SELECT
            project_id,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_reviews_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_reviews_count,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) AS flagged_reviews_count
        FROM reviews
        GROUP BY project_id
    ) review_stats ON review_stats.project_id = p.id
    {$project_flags_join}
    WHERE p.created_by = ?
");
$stats_stmt->execute([$user_id]);
$project_stats = $stats_stmt->fetch() ?: [];

$approved_projects = (int) ($project_stats['approved_projects'] ?? 0);
$pending_projects = (int) ($project_stats['pending_projects'] ?? 0);
$under_review_projects = (int) ($project_stats['under_review_projects'] ?? 0);
$rejected_projects = (int) ($project_stats['rejected_projects'] ?? 0);
$flagged_projects = (int) ($project_stats['flagged_projects'] ?? 0);
$approved_reviews = (int) ($project_stats['approved_reviews_count'] ?? 0);
$rejected_reviews = (int) ($project_stats['rejected_reviews_count'] ?? 0);
$flagged_reviews = (int) ($project_stats['flagged_reviews_count'] ?? 0);
$total_reviews = $approved_reviews + $rejected_reviews + $flagged_reviews;
$avg_rating = round((float) ($project_stats['approved_avg_rating'] ?? 0), 1);
$feature_rating_threshold = (float) FEATURE_MIN_AVG_RATING;
$feature_review_threshold = (int) FEATURE_MIN_APPROVED_REVIEWS;
$project_status_breakdown = [
    ['label' => 'Pending', 'count' => $pending_projects, 'class' => 'status-pending'],
    ['label' => 'Under Review', 'count' => $under_review_projects, 'class' => 'status-under-review'],
    ['label' => 'Approved', 'count' => $approved_projects, 'class' => 'status-approved'],
    ['label' => 'Rejected', 'count' => $rejected_projects, 'class' => 'status-rejected'],
    ['label' => 'Flagged', 'count' => $flagged_projects, 'class' => 'status-flagged'],
];

$project_status_label = static function ($status) {
    $status = strtolower(trim((string) $status));
    $labels = [
        'pending' => 'Pending',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'flagged' => 'Flagged',
    ];

    return $labels[$status] ?? 'Pending';
};

$project_status_class = static function ($status) {
    $status = strtolower(trim((string) $status));
    if ($status === 'approved') {
        return 'status-approved';
    }
    if ($status === 'rejected') {
        return 'status-rejected';
    }
    if ($status === 'under_review') {
        return 'status-under-review';
    }
    if ($status === 'flagged') {
        return 'status-flagged';
    }

    return 'status-pending';
};

$tracker_stmt = $db->prepare("
    SELECT
        p.id,
        p.name,
        p.category,
        p.created_at,
        p.updated_at,
        p.total_reviews,
        p.avg_rating,
        COALESCE(review_counts.approved_reviews_count, 0) AS approved_reviews_count,
        COALESCE(review_counts.approved_avg_rating, 0) AS approved_avg_rating,
        " . ($has_featured_column ? "COALESCE(p.is_featured, 0)" : "0") . " AS is_featured,
        " . ($has_sponsored_column ? "COALESCE(p.is_sponsored, 0)" : "0") . " AS is_sponsored,
        " . ($has_feature_status_column ? "LOWER(COALESCE(NULLIF(TRIM(p.feature_status), ''), 'none'))" : "'none'") . " AS feature_status,
        " . ($has_feature_queue_type_column ? "LOWER(COALESCE(NULLIF(TRIM(p.feature_queue_type), ''), ''))" : "''") . " AS feature_queue_type,
        " . ($has_priority_review_status_column ? "LOWER(COALESCE(NULLIF(TRIM(p.priority_review_status), ''), 'none'))" : "'none'") . " AS priority_review_status,
        " . ($has_sponsored_status_column ? "LOWER(COALESCE(NULLIF(TRIM(p.sponsored_status), ''), 'none'))" : "'none'") . " AS sponsored_status,
        {$project_status_sql} AS moderation_status
    FROM projects p
    LEFT JOIN (
        SELECT
            project_id,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_reviews_count,
            ROUND(AVG(CASE WHEN status = 'approved' THEN rating END), 1) AS approved_avg_rating
        FROM reviews
        GROUP BY project_id
    ) review_counts ON review_counts.project_id = p.id
    {$project_flags_join}
    WHERE p.created_by = ?
    ORDER BY p.updated_at DESC, p.id DESC
    LIMIT 8
");
$tracker_stmt->execute([$user_id]);
$tracked_projects = $tracker_stmt->fetchAll();

$promotion_summary = [
    'featured_live' => 0,
    'sponsored_live' => 0,
    'eligible' => 0,
    'priority_requested' => 0,
    'priority_active' => 0,
    'sponsored_requested' => 0,
];
foreach ($tracked_projects as $promotion_project) {
    $promotion_state = getProjectPromotionState($promotion_project);
    if (!empty($promotion_state['is_featured'])) {
        $promotion_summary['featured_live']++;
    }
    if (!empty($promotion_state['is_sponsored'])) {
        $promotion_summary['sponsored_live']++;
    }
    if (($promotion_state['feature_status'] ?? 'none') === 'eligible') {
        $promotion_summary['eligible']++;
    }
    if (($promotion_state['priority_review_status'] ?? 'none') === 'requested') {
        $promotion_summary['priority_requested']++;
    }
    if (($promotion_state['priority_review_status'] ?? 'none') === 'active') {
        $promotion_summary['priority_active']++;
    }
    if (($promotion_state['sponsored_status'] ?? 'none') === 'requested') {
        $promotion_summary['sponsored_requested']++;
    }
}

$get_feature_progress = static function (array $project) use ($feature_rating_threshold, $feature_review_threshold) {
    $moderation_status = strtolower(trim((string) ($project['moderation_status'] ?? 'pending')));
    $promotion = getProjectPromotionState($project);
    $feature_status = (string) ($promotion['feature_status'] ?? 'none');
    $is_featured = !empty($promotion['is_featured']);
    $project_rating = round((float) ($project['approved_avg_rating'] ?? 0), 1);
    $project_reviews = (int) ($project['approved_reviews_count'] ?? 0);
    $remaining_reviews = max(0, $feature_review_threshold - $project_reviews);
    $rating_gap = max(0, round($feature_rating_threshold - $project_rating, 1));

    if ($is_featured || $feature_status === 'featured') {
        return ['label' => 'Featured badge is live on this project.', 'class' => 'feature-progress-live'];
    }
    if (!empty($promotion['is_sponsored'])) {
        return ['label' => 'Sponsored placement is active on this project.', 'class' => 'feature-progress-sponsored'];
    }
    if (($promotion['sponsored_status'] ?? 'none') === 'requested') {
        return ['label' => 'Sponsored placement request submitted and waiting for admin activation.', 'class' => 'feature-progress-sponsored'];
    }
    if (($promotion['priority_review_status'] ?? 'none') === 'requested') {
        return ['label' => 'Priority feature review requested. Waiting for payment/admin activation.', 'class' => 'feature-progress-review'];
    }
    if (($promotion['priority_review_status'] ?? 'none') === 'active' && $feature_status === 'pending_review') {
        return ['label' => 'Priority feature review is active. Your project is ahead in the feature queue.', 'class' => 'feature-progress-priority'];
    }
    if ($feature_status === 'pending_review') {
        $queue_type = strtolower(trim((string) ($project['feature_queue_type'] ?? 'standard')));
        $label = $queue_type === 'priority'
            ? 'Eligible now. Your project is in the paid priority feature review queue.'
            : 'Eligible now. Your project is waiting for admin featured review.';
        return ['label' => $label, 'class' => $queue_type === 'priority' ? 'feature-progress-priority' : 'feature-progress-ready'];
    }
    if ($feature_status === 'rejected') {
        return ['label' => 'Featured badge was reviewed and not approved yet.', 'class' => 'feature-progress-review'];
    }
    if ($feature_status === 'eligible') {
        return ['label' => 'Eligible now. Choose free standard review or paid priority review.', 'class' => 'feature-progress-ready'];
    }
    if ($moderation_status !== 'approved') {
        return ['label' => 'Featured review unlocks after the project is approved.', 'class' => 'feature-progress-blocked'];
    }
    if ($remaining_reviews > 0) {
        return ['label' => 'Need ' . number_format($remaining_reviews) . ' more approved reviews to unlock featured review. Rating threshold is checked at ' . number_format($feature_review_threshold) . '+ approved reviews.', 'class' => 'feature-progress-pending'];
    }
    if ($rating_gap <= 0 && $remaining_reviews <= 0) {
        return ['label' => 'Eligible now. Your project is ready to enter featured review.', 'class' => 'feature-progress-ready'];
    }
    if ($rating_gap > 0) {
        return ['label' => 'Need +' . number_format($rating_gap, 1) . ' rating points to reach featured review.', 'class' => 'feature-progress-pending'];
    }

    return ['label' => 'Need ' . number_format($remaining_reviews) . ' more approved reviews to reach featured review.', 'class' => 'feature-progress-pending'];
};

// Get verification status
$is_verified = isVerifiedDeveloper($user_id);
$is_approved_by_status = ($latest_verification_status === 'approved');
$has_verified_badge = (int)($user['has_verified_badge'] ?? 0) === 1;
$is_effectively_verified = $is_verified || $is_approved_by_status || $has_verified_badge;

// Sync badge when verified but badge is still 0
if ($is_effectively_verified && !$has_verified_badge) {
    try {
        $stmt = $db->prepare("UPDATE users SET has_verified_badge = 1 WHERE id = ? AND has_verified_badge = 0");
        $stmt->execute([$user_id]);
        $has_verified_badge = true;
    } catch (PDOException $e) {
        // Do not break dashboard render if update fails.
    }
}

$user_designation = $is_effectively_verified ? 'Verified Developer' : 'Developer';

// Calculate profile completion
$completion_items = [
    'full_name' => !empty($user['full_name']),
    'username' => !empty($user['username']),
    'email' => !empty($user['email']),
    'verification' => $is_effectively_verified
];
$completed = count(array_filter($completion_items));
$total_items = count($completion_items);
$completion_percent = ($completed / $total_items) * 100;

// IMPORTANT: No "Register Project" button until user is verified
$can_register_project = $is_effectively_verified; // Now requires verification
$first_letter = strtoupper(substr($user['full_name'] ?? $user['username'] ?? 'D', 0, 1));
$full_name = htmlspecialchars($user['full_name'] ?? $user['username'] ?? 'Developer');
?>

<div class="dashboard-wrapper">
    <div class="dashboard-header">
        <div class="header-top">
            <div class="user-area">
                <div class="user-avatar"><?php echo $first_letter; ?></div>
                <div class="user-info">
                    <div class="user-name"><?php echo $full_name; ?></div>
                    <div class="user-role">
                        <?php echo htmlspecialchars($user_designation); ?>
                        <?php if ($is_effectively_verified): ?>
                            <span class="verified-tick" title="Verified Developer" aria-label="Verified Developer">
                                <i class="fas fa-check"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="welcome-text">
            <h2>Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>! 👋</h2>
            <p>Manage your projects and grow on CoinRex</p>
        </div>
    </div>

    <!-- Unverified User CTA Banner -->
    <?php if (!$is_effectively_verified): ?>
    <div class="verify-cta-banner">
        <div class="verify-cta-content">
            <div class="verify-cta-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="verify-cta-text">
                <h3>Get Verified to Unlock Full DevHub Features</h3>
                <p>Verify your developer identity to register projects, generate widgets, access review insights, and build trust with the CoinRex community.</p>
                <div class="verify-cta-benefits">
                    <span><i class="fas fa-check-circle"></i> Register Projects</span>
                    <span><i class="fas fa-check-circle"></i> Widget & API Access</span>
                    <span><i class="fas fa-check-circle"></i> Review Insights</span>
                    <span><i class="fas fa-check-circle"></i> Verified Badge</span>
                </div>
            </div>
            <div class="verify-cta-action">
                <a href="<?php echo BASE_URL; ?>/devhub/apply.php" class="btn-verify-cta">
                    <i class="fas fa-check-double"></i> Get Verified Now
                </a>
                <p>Takes only a few minutes</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Profile Completion Progress with Complete Now Button -->
    <?php if (!$is_effectively_verified): ?>
    <div class="progress-card">
        <div class="progress-title">
            <h3><i class="fas fa-chart-line"></i> Profile Completion</h3>
            <div class="progress-badge"><?php echo round($completion_percent); ?>% Complete</div>
        </div>
        <div class="progress-bar-container">
            <div class="progress-bar-fill" style="width: <?php echo $completion_percent; ?>%"></div>
        </div>
        <div class="progress-stats">
            <span>✅ <?php echo $completed; ?> completed</span>
            <span>📋 <?php echo ($total_items - $completed); ?> remaining</span>
        </div>
        <div class="completion-list">
            <div class="completion-item <?php echo $completion_items['full_name'] ? 'completed' : 'pending'; ?>">
                <i class="fas <?php echo $completion_items['full_name'] ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                <span>Full Name</span>
            </div>
            <div class="completion-item <?php echo $completion_items['username'] ? 'completed' : 'pending'; ?>">
                <i class="fas <?php echo $completion_items['username'] ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                <span>Username</span>
            </div>
            <div class="completion-item <?php echo $completion_items['email'] ? 'completed' : 'pending'; ?>">
                <i class="fas <?php echo $completion_items['email'] ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                <span>Email</span>
            </div>
            <div class="completion-item <?php echo $completion_items['verification'] ? 'completed' : 'pending'; ?>">
                <i class="fas <?php echo $completion_items['verification'] ? 'fa-check-circle' : 'fa-circle'; ?>"></i>
                <span>Verification</span>
            </div>
        </div>
        
        <!-- Complete Now Button -->
        <?php if(!$is_effectively_verified): ?>
        <div class="complete-now-wrapper">
            <a href="<?php echo BASE_URL; ?>/devhub/apply.php" class="btn-complete-now">
                <i class="fas fa-check-double"></i> Complete Now
            </a>
            <p class="complete-now-hint">
                Get verified to unlock project registration
            </p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?php echo number_format($approved_projects); ?></h3>
            <p>Approved Projects</p>
            <span class="stat-footnote">Only approved listings are counted here.</span>
        </div>
        <div class="stat-card stat-card-queue">
            <h3><?php echo number_format($pending_projects); ?></h3>
            <p>Pending Projects</p>
            <span class="stat-footnote">Only the waiting-to-be-checked submissions appear in this card.</span>
        </div>
        <div class="stat-card">
            <h3><?php echo number_format($total_reviews); ?></h3>
            <p>All Reviews</p>
            <span class="stat-footnote">Approved + Rejected + Flagged reviews from your approved projects.</span>
        </div>
        <div class="stat-card">
            <h3><?php echo $avg_rating; ?></h3>
            <p>Avg Rating</p>
            <span class="stat-footnote">Average across approved projects with ratings.</span>
        </div>
    </div>

    <!-- Sponsored Project Marketing Card (visible to ALL users) -->
    <div class="sponsored-marketing-card">
        <div class="sponsored-marketing-content">
            <div class="sponsored-marketing-icon">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div class="sponsored-marketing-text">
                <h3>Get Sponsored Placement — No Verification Needed</h3>
                <p>Want your project to stand out? Sponsored placement gives you <strong>top priority visibility</strong> with a premium sponsored badge, appearing prominently across CoinRex for all users — no developer verification required.</p>
                <div class="sponsored-marketing-benefits">
                    <span><i class="fas fa-check-circle"></i> Top Priority Placement</span>
                    <span><i class="fas fa-check-circle"></i> Premium Sponsored Badge</span>
                    <span><i class="fas fa-check-circle"></i> Visible to All Users</span>
                    <span><i class="fas fa-check-circle"></i> No Verification Needed</span>
                </div>
            </div>
            <div class="sponsored-marketing-action">
                <a href="<?php echo BASE_URL; ?>/public/contact.php" class="btn-sponsored-cta">
                    <i class="fas fa-envelope"></i> Contact Now
                </a>
                <p>Get in touch with our team</p>
            </div>
        </div>
    </div>

    <div class="queue-summary-panel">
        <div class="queue-summary-head">
            <div>
                <span class="tracker-kicker">Queue Breakdown</span>
                <h3>Project Status Snapshot</h3>
                <p>Every submission status stays visible here without crowding the main metric cards.</p>
            </div>
        </div>
        <div class="queue-summary-list">
            <?php foreach ($project_status_breakdown as $status_item): ?>
                <div class="queue-summary-item">
                    <span class="status-chip <?php echo htmlspecialchars($status_item['class'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($status_item['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <strong><?php echo number_format((int) $status_item['count']); ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tracker-section">
        <div class="tracker-header">
            <div>
                <span class="tracker-kicker">Project Tracker</span>
                <h3>Submission Status</h3>
                <p>Trace every submitted project and see where it sits in the moderation pipeline.</p>
            </div>
            <?php if ($can_register_project && $is_effectively_verified): ?>
                <a href="<?php echo BASE_URL; ?>/devhub/projects/submit_project.php" class="btn-secondary">
                    <i class="fas fa-plus-circle"></i> Submit Another Project
                </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($tracked_projects)): ?>
            <div class="tracker-list">
                <?php foreach ($tracked_projects as $tracked_project): ?>
                    <?php
                    $moderation_status = strtolower(trim((string) ($tracked_project['moderation_status'] ?? 'pending')));
                    $status_label = $project_status_label($moderation_status);
                    $status_class = $project_status_class($moderation_status);
                    $feature_progress = $get_feature_progress($tracked_project);
                    $promotion_state = getProjectPromotionState($tracked_project);
                    $submitted_label = formatDevhubDateTime((string) ($tracked_project['created_at'] ?? ''));
                    $updated_label = formatDevhubDateTime((string) ($tracked_project['updated_at'] ?? ''));
                    ?>
                    <article class="tracker-card">
                        <div class="tracker-card-top">
                            <div class="tracker-title-wrap">
                                <h4><?php echo htmlspecialchars((string) ($tracked_project['name'] ?? 'Project'), ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p><?php echo htmlspecialchars((string) ($tracked_project['category'] ?? 'Uncategorized'), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="tracker-card-side">
                                <div class="tracker-rating-wrap"><?php echo renderUniversalRating([
                                    'provider' => 'coinrex',
                                    'value' => (float) ($tracked_project['approved_avg_rating'] ?? 0),
                                    'scale' => 5,
                                    'size' => 'sm',
                                    'variant' => 'cr-row-small',
                                    'show_count' => false,
                                    'class' => 'devhub-rating-badge',
                                ]); ?></div>
                            </div>
                        </div>
                        <div class="tracker-meta">
                            <span class="tracker-meta-status"><span class="status-chip <?php echo $status_class; ?>"><?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?></span></span>
                            <span><i class="fas fa-calendar-plus"></i> Submitted <?php echo htmlspecialchars($submitted_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><i class="fas fa-clock"></i> Last update <?php echo htmlspecialchars($updated_label, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span><i class="fas fa-comments"></i> <?php echo number_format((int) ($tracked_project['approved_reviews_count'] ?? 0)); ?> approved reviews</span>
                        </div>
                        <div class="feature-progress <?php echo htmlspecialchars($feature_progress['class'], ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-award"></i>
                            <span><?php echo htmlspecialchars($feature_progress['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="promotion-chip-row">
                            <?php if (!empty($promotion_state['is_featured'])): ?>
                                <span class="status-chip status-featured"><i class="fas fa-gem"></i> Featured</span>
                            <?php endif; ?>
                            <?php if (!empty($promotion_state['is_sponsored'])): ?>
                                <span class="status-chip status-sponsored"><i class="fas fa-bullhorn"></i> Sponsored</span>
                            <?php elseif (($promotion_state['sponsored_status'] ?? 'none') === 'requested'): ?>
                                <span class="status-chip status-sponsored-soft"><i class="fas fa-bullhorn"></i> Sponsored Requested</span>
                            <?php endif; ?>
                            <?php if (($promotion_state['priority_review_status'] ?? 'none') === 'active'): ?>
                                <span class="status-chip status-priority"><i class="fas fa-bolt"></i> Priority Queue</span>
                            <?php elseif (($promotion_state['priority_review_status'] ?? 'none') === 'requested'): ?>
                                <span class="status-chip status-priority-soft"><i class="fas fa-bolt"></i> Priority Requested</span>
                            <?php endif; ?>
                        </div>
                        <div class="promotion-actions">
                            <?php if (!empty($promotion_state['can_request_standard'])): ?>
                                <form method="POST" action="" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(appCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="project_id" value="<?php echo (int) ($tracked_project['id'] ?? 0); ?>">
                                    <input type="hidden" name="promotion_action" value="standard_feature_review">
                                    <button type="submit" class="btn-secondary"><i class="fas fa-award"></i> Request Featured Review</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!empty($promotion_state['can_request_priority'])): ?>
                                <form method="POST" action="" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(appCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="project_id" value="<?php echo (int) ($tracked_project['id'] ?? 0); ?>">
                                    <input type="hidden" name="promotion_action" value="priority_feature_review">
                                    <button type="submit" class="btn-secondary btn-priority"><i class="fas fa-bolt"></i> Upgrade to Priority</button>
                                </form>
                            <?php endif; ?>
                            <?php if (!empty($promotion_state['can_request_sponsored'])): ?>
                                <form method="POST" action="" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(appCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="project_id" value="<?php echo (int) ($tracked_project['id'] ?? 0); ?>">
                                    <input type="hidden" name="promotion_action" value="sponsored">
                                    <button type="submit" class="btn-secondary btn-sponsored"><i class="fas fa-bullhorn"></i> Request Sponsored</button>
                                </form>
                            <?php endif; ?>
                            <?php if (empty($promotion_state['can_request_standard']) && empty($promotion_state['can_request_priority']) && empty($promotion_state['can_request_sponsored'])): ?>
                                <div class="promotion-zero-state">
                                    <strong>Promotion actions are locked for now.</strong>
                                    <span>Approve the project first, then unlock Sponsored visibility or earn eligibility for Featured review.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 12px;">
                            <a class="btn-secondary" href="<?php echo BASE_URL; ?>/devhub/projects/edit_project.php?id=<?php echo (int) ($tracked_project['id'] ?? 0); ?>">
                                <i class="fas fa-pen"></i> Edit Project
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="tracker-empty">
                <i class="fas fa-layer-group"></i>
                <h4>No project submissions yet</h4>
                <p>Your submitted projects will appear here with live moderation status.</p>
            </div>
        <?php endif; ?>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php if ($promotion_message !== ''): ?>
<script>
window.addEventListener('DOMContentLoaded', function () {
    alert(<?php echo json_encode($promotion_message, JSON_UNESCAPED_UNICODE); ?>);
});
</script>
<?php endif; ?>
