<?php
$page_title = 'Review Verification';
$activePage = 'reviews';
require_once __DIR__ . '/includes/header.php';

function scoreHoldingAmount($amount) {
    $amount = (float) $amount;
    if ($amount >= 100) return 20;
    if ($amount >= 50) return 15;
    if ($amount >= 20) return 10;
    return 5;
}

function scoreHoldingDuration($days) {
    $days = (int) $days;
    if ($days >= 30) return 20;
    if ($days >= 15) return 15;
    if ($days >= 7) return 10;
    return 5;
}

function scoreReviewQuality($content) {
    $length = mb_strlen(trim((string) $content));
    if ($length >= 150 && $length <= 250) return 20;
    if (($length >= 100 && $length <= 149) || ($length >= 250 && $length <= 400)) return 15;
    if ($length >= 50 && $length <= 99) return 10;
    return 5;
}

function scoreReviewerHistory($approved_count, $rejected_count, $total_count) {
    $approved_count = (int) $approved_count;
    $rejected_count = (int) $rejected_count;
    if ($rejected_count === 0 && $approved_count >= 5) return 20;
    if ($rejected_count <= 2) return 15;
    if ($rejected_count <= 5) return 10;
    return 5;
}

function scoreWalletType($wallet_type) {
    return $wallet_type === 'non_custodial' ? 20 : 10;
}

function calculateScoreBreakdown($review) {
    $holding = scoreHoldingAmount($review['holding_amount'] ?? 0);
    $duration = scoreHoldingDuration($review['holding_days'] ?? 0);
    $quality = scoreReviewQuality($review['review_content'] ?? '');
    $history = scoreReviewerHistory(
        $review['user_approved_reviews'] ?? 0,
        $review['user_rejected_reviews'] ?? 0,
        $review['user_total_reviews'] ?? 0
    );
    $wallet = scoreWalletType((string) ($review['wallet_type'] ?? 'custodial'));
    $total = round((float) $holding + (float) $duration + (float) $quality + (float) $history + (float) $wallet, 2);
    return ['holding' => $holding, 'duration' => $duration, 'quality' => $quality, 'history' => round($history, 2), 'wallet' => $wallet, 'total' => $total];
}

function calculateRewardFromScore($score, $project_max_reward, $wallet_type) {
    return calculateRewardFromFinalScore($score, $project_max_reward, $wallet_type);
}

function validateApproveEligibility(PDO $db, $review, $wallet_reuse_threshold = 5) {
    $errors = [];
    if (($review['proof_status'] ?? '') === 'verified' && ($review['eligibility_status'] ?? '') === 'eligible') {
        return [];
    }
    $review_id = (int) ($review['id'] ?? 0);
    $tx_hash = trim((string) ($review['tx_hash'] ?? ''));
    $wallet_address = trim((string) ($review['wallet_address'] ?? ''));
    $screenshot_url = trim((string) ($review['screenshot_url'] ?? ''));
    $review_content = trim((string) ($review['review_content'] ?? ''));
    if ($tx_hash === '') $errors[] = 'TX hash is required.';
    else {
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM reviews WHERE tx_hash = ? AND id != ?");
        $stmt->execute([$tx_hash, $review_id]);
        if ((int) ($stmt->fetch()['total'] ?? 0) > 0) $errors[] = 'TX hash must be unique.';
    }
    if ($wallet_address === '') $errors[] = 'Wallet address is required.';
    else {
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM reviews WHERE wallet_address = ? AND id != ?");
        $stmt->execute([$wallet_address, $review_id]);
        if ((int) ($stmt->fetch()['total'] ?? 0) >= $wallet_reuse_threshold) $errors[] = 'Wallet address is reused excessively.';
    }
    if ($screenshot_url === '') $errors[] = 'Screenshot proof is required.';
    if ($review_content === '') $errors[] = 'Review text must not be empty.';
    return $errors;
}

$db = getDBConnection();
ensureLevelEngineSchema($db);
ensureRewardClaimSchema($db);
ensureReviewEligibilitySchema($db);
$current_admin = getCurrentAdmin();
$message = '';
$message_type = 'success';
$status_filter = trim((string) ($_GET['status'] ?? 'pending'));
$valid_filters = ['pending', 'approved', 'rejected', 'flagged', 'all'];
if (!in_array($status_filter, $valid_filters, true)) $status_filter = 'pending';
$wallet_filter = trim((string) ($_GET['wallet_type'] ?? 'all'));
$valid_wallet_filters = ['all', 'custodial', 'non_custodial'];
if (!in_array($wallet_filter, $valid_wallet_filters, true)) $wallet_filter = 'all';

$has_proof_status = tableHasColumn('reviews', 'proof_status');
$has_rejection_reason = tableHasColumn('reviews', 'rejection_reason');
$has_approved_reviews_count = tableHasColumn('users', 'approved_reviews_count');
$has_wallet_type = tableHasColumn('reviews', 'wallet_type');
$has_final_rex = tableHasColumn('reviews', 'final_rex');
$has_reviewed_by = tableHasColumn('reviews', 'reviewed_by');
$has_reviewed_at = tableHasColumn('reviews', 'reviewed_at');
$has_approval_note = tableHasColumn('reviews', 'approval_note');
$has_proof_verified_by = tableHasColumn('reviews', 'proof_verified_by');
$has_proof_verified_at = tableHasColumn('reviews', 'proof_verified_at');
$has_proof_rejection_reason = tableHasColumn('reviews', 'proof_rejection_reason');
$has_users_rex_balance = tableHasColumn('users', 'rex_balance');
$has_users_total_rex_earned = tableHasColumn('users', 'total_rex_earned');
$has_review_score = tableHasColumn('reviews', 'review_score');
$has_auto_approved_at = tableHasColumn('reviews', 'auto_approved_at');
$has_auto_approved_by_level = tableHasColumn('reviews', 'auto_approved_by_level');
$has_eligibility_columns = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $review_id = (int) ($_POST['review_id'] ?? 0);
    $review_user_id = (int) ($_POST['review_user_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    $moderation_note = trim((string) ($_POST['moderation_note'] ?? ''));
    if ($review_id > 0 && $review_user_id > 0 && in_array($action, ['approve', 'reject', 'flag'], true)) {
        $new_status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'flagged');
        try {
            $db->beginTransaction();
            $current_stmt = $db->prepare("
                SELECT r.*, COALESCE(p.max_reward_rex, 0) AS project_max_reward,
                    COALESCE(NULLIF(TRIM(u.level), ''), 'beginner') AS user_level,
                    COALESCE(user_stats.approved_reviews, 0) AS user_approved_reviews,
                    COALESCE(user_stats.rejected_reviews, 0) AS user_rejected_reviews,
                    COALESCE(user_stats.total_reviews, 0) AS user_total_reviews
                FROM reviews r
                LEFT JOIN projects p ON p.id = r.project_id
                LEFT JOIN users u ON u.id = r.user_id
                LEFT JOIN (
                    SELECT user_id,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_reviews,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_reviews,
                        COUNT(*) AS total_reviews
                    FROM reviews GROUP BY user_id
                ) user_stats ON user_stats.user_id = r.user_id
                WHERE r.id = ? FOR UPDATE
            ");
            $current_stmt->execute([$review_id]);
            $current_review = $current_stmt->fetch();
            if (!$current_review) throw new RuntimeException('Review not found.');
            $previous_status = (string) ($current_review['status'] ?? 'pending');
            $breakdown = calculateScoreBreakdown($current_review);
            $user_level_state = getUserLevelState(['id' => (int) ($current_review['user_id'] ?? 0), 'level' => (string) ($current_review['user_level'] ?? 'beginner')], $db);
            $score_details = calculateReviewFinalScore((float) $breakdown['total'], $user_level_state, ['user_total_reviews' => (int) ($current_review['user_total_reviews'] ?? 0)]);
            $score_total = (float) $score_details['final_score'];
            $wallet_type = (string) ($current_review['wallet_type'] ?? 'custodial');
            $reward_amount = calculateRewardFromScore($score_total, (float) ($current_review['project_max_reward'] ?? 0), $wallet_type);
            if ($new_status === 'approved') {
                $eligibility_errors = validateApproveEligibility($db, $current_review);
                if (!empty($eligibility_errors)) throw new RuntimeException(implode(' ', $eligibility_errors));
            }
            $updates = ["status = ?", "updated_at = NOW()"];
            $params = [$new_status];
            if ($has_proof_status) { $updates[] = "proof_status = ?"; $params[] = $new_status === 'approved' ? 'verified' : ($new_status === 'rejected' ? 'rejected' : 'flagged'); }
            if ($has_reviewed_by) { $updates[] = "reviewed_by = ?"; $params[] = (int) $current_admin['id']; }
            if ($has_reviewed_at) $updates[] = "reviewed_at = NOW()";
            if ($has_approval_note) { $updates[] = "approval_note = ?"; $params[] = $new_status === 'approved' ? $moderation_note : null; }
            if ($has_rejection_reason) { $updates[] = "rejection_reason = ?"; $params[] = ($new_status === 'rejected' || $new_status === 'flagged') ? $moderation_note : null; }
            if ($has_proof_verified_by) { $updates[] = "proof_verified_by = ?"; $params[] = $new_status === 'approved' ? (int) $current_admin['id'] : null; }
            if ($has_proof_verified_at) $updates[] = "proof_verified_at = " . ($new_status === 'approved' ? "NOW()" : "NULL");
            if ($has_proof_rejection_reason) { $updates[] = "proof_rejection_reason = ?"; $params[] = ($new_status === 'rejected' || $new_status === 'flagged') ? $moderation_note : null; }
            if ($has_review_score) { $updates[] = "review_score = ?"; $params[] = $new_status === 'approved' ? $score_total : 0; }
            if ($has_final_rex) { $updates[] = "final_rex = ?"; $params[] = $new_status === 'approved' ? $reward_amount : 0; }
            if ($has_auto_approved_at) $updates[] = "auto_approved_at = NULL";
            if ($has_auto_approved_by_level) $updates[] = "auto_approved_by_level = 0";
            $params[] = $review_id;
            $stmt = $db->prepare("UPDATE reviews SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->execute($params);
            if ($new_status === 'approved' && $previous_status !== 'approved' && $has_users_rex_balance) {
                if ($reward_amount > 0) {
                    addRewardLedgerEntry($review_user_id, $reward_amount, 'review', 'review_approval_reward', 'available', 'review:' . $review_id, $db, resolveRewardPhase('review', $user_level_state['level'] ?? 'beginner'), $user_level_state['level'] ?? 'beginner');
                }
                syncUserReviewCounters($review_user_id, $db);
                maybeActivateReferralQualification($review_user_id, $db);
                creditReferralCommissionForReview($review_user_id, $reward_amount, $db);
            } elseif ($new_status !== 'approved' && $previous_status === 'approved' && $has_users_rex_balance) {
                $previous_reward = (int) round((float) (($has_final_rex ? ($current_review['final_rex'] ?? 0) : ($current_review['calculated_rex'] ?? 0))), 0);
                if ($previous_reward > 0) {
                    addRewardLedgerEntry($review_user_id, -1 * $previous_reward, 'review', 'review_reward_reversal', 'available', 'review_reversal:' . $review_id, $db, resolveRewardPhase('review', $user_level_state['level'] ?? 'beginner'), $user_level_state['level'] ?? 'beginner');
                }
                syncUserReviewCounters($review_user_id, $db);
                reverseReferralCommissionForReview($review_user_id, $previous_reward, $db);
            }
            if ($has_approved_reviews_count) {
                if ($previous_status !== 'approved' && $new_status === 'approved') {
                    $db->prepare("UPDATE users SET approved_reviews_count = approved_reviews_count + 1 WHERE id = ?")->execute([$review_user_id]);
                } elseif ($previous_status === 'approved' && $new_status !== 'approved') {
                    $db->prepare("UPDATE users SET approved_reviews_count = CASE WHEN approved_reviews_count > 0 THEN approved_reviews_count - 1 ELSE 0 END WHERE id = ?")->execute([$review_user_id]);
                }
            }
            syncUserLevelStatus($review_user_id, $db);
            syncProjectAggregateMetrics((int) ($current_review['project_id'] ?? 0), $db);
            logAdminActivity((int) $current_admin['id'], 'review_moderation_' . $new_status, 'review', (string) $review_id, json_encode(['note' => $moderation_note, 'previous_status' => $previous_status, 'score' => $score_total, 'reward' => $reward_amount, 'level_bonus' => $score_details['level_bonus'], 'bonus_active' => $score_details['bonus_active'], 'approval_lane' => $user_level_state['approval_lane'] ?? 'standard'], JSON_UNESCAPED_UNICODE));
            $project_name_for_notice = (string) ($current_review['project_name'] ?? 'project');
            if ($new_status === 'approved') {
                createTemplatedNotification('review.approved', 'user', $review_user_id, ['project_name' => $project_name_for_notice], ['actor_type' => 'admin', 'actor_id' => (int) $current_admin['id'], 'meta' => ['review_id' => $review_id, 'status' => $new_status]], $db);
            } elseif ($new_status === 'rejected') {
                createTemplatedNotification('review.rejected', 'user', $review_user_id, ['project_name' => $project_name_for_notice, 'reason' => $moderation_note !== '' ? $moderation_note : 'No reason provided'], ['actor_type' => 'admin', 'actor_id' => (int) $current_admin['id'], 'meta' => ['review_id' => $review_id, 'status' => $new_status]], $db);
            } elseif ($new_status === 'flagged') {
                createTemplatedNotification('review.flagged', 'user', $review_user_id, ['project_name' => $project_name_for_notice], ['actor_type' => 'admin', 'actor_id' => (int) $current_admin['id'], 'meta' => ['review_id' => $review_id, 'status' => $new_status]], $db);
            }
            $db->commit();
            $message = 'Review moderation action applied.';
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $message = 'Failed to moderate review: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = 'Invalid review moderation payload.';
        $message_type = 'error';
    }
}

$where_parts = [];
$params = [];
if ($status_filter !== 'all') { $where_parts[] = "r.status = ?"; $params[] = $status_filter; }
if ($has_wallet_type && $wallet_filter !== 'all') { $where_parts[] = "r.wallet_type = ?"; $params[] = $wallet_filter; }
$where = !empty($where_parts) ? "WHERE " . implode(' AND ', $where_parts) : '';

$proof_select = $has_proof_status ? "r.proof_status" : "NULL AS proof_status";
$reason_select = $has_rejection_reason ? "r.rejection_reason" : "NULL AS rejection_reason";
$wallet_select = $has_wallet_type ? "r.wallet_type" : "NULL AS wallet_type";
$final_rex_select = $has_final_rex ? "r.final_rex" : "NULL AS final_rex";
$score_select = $has_review_score ? "r.review_score" : "NULL AS review_score";
$eligibility_select = $has_eligibility_columns
    ? "r.eligibility_status, r.eligibility_wallet_address, r.eligibility_chain_id, r.eligibility_contract_address"
    : "NULL AS eligibility_status, NULL AS eligibility_wallet_address, NULL AS eligibility_chain_id, NULL AS eligibility_contract_address";

$stmt = $db->prepare("
    SELECT r.id, r.user_id, r.project_id, r.review_title, r.review_content, r.rating, r.status, {$proof_select},
        r.tx_hash, r.wallet_address, r.screenshot_url, r.created_at, {$reason_select},
        r.holding_amount, r.holding_days, r.calculated_rex, {$final_rex_select}, {$wallet_select}, {$score_select},
        {$eligibility_select},
        r.pros, r.cons,
        u.username, u.email, u.level,
        p.name AS project_name, COALESCE(p.max_reward_rex, 0) AS project_max_reward,
        COALESCE(user_stats.approved_reviews, 0) AS user_approved_reviews,
        COALESCE(user_stats.rejected_reviews, 0) AS user_rejected_reviews,
        COALESCE(user_stats.total_reviews, 0) AS user_total_reviews
    FROM reviews r
    LEFT JOIN users u ON u.id = r.user_id
    LEFT JOIN projects p ON p.id = r.project_id
    LEFT JOIN (
        SELECT user_id,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_reviews,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_reviews,
            COUNT(*) AS total_reviews
        FROM reviews GROUP BY user_id
    ) user_stats ON user_stats.user_id = r.user_id
    {$where}
    ORDER BY
        CASE
            WHEN r.status = 'pending' AND LOWER(COALESCE(u.level, 'beginner')) = 'expert' THEN 0
            WHEN r.status = 'pending' AND LOWER(COALESCE(u.level, 'beginner')) IN ('pro', 'premium') THEN 1
            WHEN r.status = 'pending' THEN 2
            ELSE 3
        END,
        r.created_at DESC
    LIMIT 200
");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

$review_queue_stats = ['pending' => 0, 'approved' => 0, 'flagged' => 0, 'expert_lane' => 0, 'pro_lane' => 0];
foreach ($reviews as $queue_review) {
    $queue_status = (string) ($queue_review['status'] ?? 'pending');
    if (isset($review_queue_stats[$queue_status])) $review_queue_stats[$queue_status]++;
    $queue_level_state = getUserLevelState(['id' => (int) ($queue_review['user_id'] ?? 0), 'level' => (string) ($queue_review['level'] ?? 'beginner')], $db);
    if (($queue_level_state['approval_lane'] ?? '') === 'auto') $review_queue_stats['expert_lane']++;
    elseif (($queue_level_state['approval_lane'] ?? '') === 'priority') $review_queue_stats['pro_lane']++;
}
?>
<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="dashboard-header-text">
                <h1>Review Verification</h1>
                <p>Proof-backed review moderation with score engine, level bonuses, and reward flow</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-database"></i> <?php echo number_format(count($reviews)); ?> loaded
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div data-toast data-toast-message="<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>" data-toast-type="<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" style="display:none;"></div>
    <?php endif; ?>

    <!-- ====== SECTION 1: OVERVIEW METRICS ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-chart-bar"></i> Overview <span class="divider-sub">Review queue metrics</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-list"></i> Queue</span>
                <h3>Review Moderation Queue</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Validate TX hash, wallet ownership evidence, level bonuses, trust weight, and reward flow before closing each review.</p>
            </div>
        </div>
        <div class="dashboard-metric-grid">
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-gold"><i class="fas fa-clock"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) $review_queue_stats['pending']); ?></span>
                <span class="metric-label">Pending Queue</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-cyan"><i class="fas fa-bolt"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) $review_queue_stats['pro_lane']); ?></span>
                <span class="metric-label">Pro Priority</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-purple"><i class="fas fa-rocket"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) $review_queue_stats['expert_lane']); ?></span>
                <span class="metric-label">Expert Fast-Track</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-red"><i class="fas fa-flag"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) $review_queue_stats['flagged']); ?></span>
                <span class="metric-label">Flagged</span>
            </div>
        </div>
    </div>

    <!-- ====== SECTION 2: FILTER BAR ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-filter"></i> Filter <span class="divider-sub">Narrow the review queue</span></h2>
    </div>

    <div class="dashboard-panel" style="margin-bottom:16px;">
        <div class="dashboard-filter-bar">
            <div>
                <h3 style="margin:0 0 4px;font-size:15px;font-weight:700;color:#f1f5f9;">Filter Reviews</h3>
                <p class="muted" style="margin:0;font-size:12px;">Filter by status and wallet type to work the queue efficiently.</p>
            </div>
            <form method="GET" action="" class="dashboard-filter-form">
                <select name="status">
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="flagged" <?php echo $status_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
                <?php if ($has_wallet_type): ?>
                    <select name="wallet_type">
                        <option value="all" <?php echo $wallet_filter === 'all' ? 'selected' : ''; ?>>All Wallet Types</option>
                        <option value="non_custodial" <?php echo $wallet_filter === 'non_custodial' ? 'selected' : ''; ?>>Non-custodial</option>
                        <option value="custodial" <?php echo $wallet_filter === 'custodial' ? 'selected' : ''; ?>>Custodial</option>
                    </select>
                <?php endif; ?>
                <button type="submit" class="btn btn-secondary">Apply Filter</button>
            </form>
        </div>
    </div>

    <!-- ====== SECTION 3: REVIEW TABLE ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-list"></i> Review Queue <span class="divider-sub">All reviews matching current filter</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Reviewer</th>
                    <th>Project</th>
                    <th>Queue</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($reviews as $review):
                    $status = (string) ($review['status'] ?? 'pending');
                    $status_class = 'is-pending';
                    if ($status === 'approved') $status_class = 'is-active';
                    elseif (in_array($status, ['rejected', 'flagged'], true)) $status_class = 'is-suspended';
                    $proof_status = (string) ($review['proof_status'] ?? 'pending');
                    $preview_breakdown = calculateScoreBreakdown($review);
                    $preview_level_state = getUserLevelState(['id' => (int) ($review['user_id'] ?? 0), 'level' => (string) ($review['level'] ?? 'beginner')], $db);
                    $preview_score_details = calculateReviewFinalScore((float) $preview_breakdown['total'], $preview_level_state, ['user_total_reviews' => (int) ($review['user_total_reviews'] ?? 0)]);
                    $preview_score = (float) $preview_score_details['final_score'];
                    $preview_reward = calculateRewardFromScore($preview_score, (float) ($review['project_max_reward'] ?? 0), (string) ($review['wallet_type'] ?? 'custodial'));
                    $score_tone_class = 'score-mid';
                    if ($preview_score > 85) $score_tone_class = 'score-high';
                    elseif ($preview_score < 50) $score_tone_class = 'score-low';
                    $summary_excerpt = trim((string) ($review['review_title'] ?? '')) !== '' ? (string) $review['review_title'] : (string) ($review['review_content'] ?? '');
                    if (function_exists('mb_strlen') && function_exists('mb_substr')) $summary_excerpt = mb_strlen($summary_excerpt) > 84 ? mb_substr($summary_excerpt, 0, 84) . '...' : $summary_excerpt;
                    else $summary_excerpt = strlen($summary_excerpt) > 84 ? substr($summary_excerpt, 0, 84) . '...' : $summary_excerpt;
                    $approval_lane = $preview_level_state['approval_lane'] ?? 'standard';
                    $lane_class = 'is-pending';
                    $lane_label = $preview_level_state['approval_label'] ?? '24-48 hours';
                    if ($approval_lane === 'auto') $lane_class = 'is-active';
                    elseif ($approval_lane === 'priority') $lane_class = 'is-pro';
                ?>
                    <tr>
                        <td data-label="ID"><?php echo (int) $review['id']; ?></td>
                        <td data-label="Reviewer">
                            <strong><?php echo htmlspecialchars((string) ($review['username'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="muted"><?php echo htmlspecialchars((string) ($review['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <span>Level: <?php echo htmlspecialchars($preview_level_state['display_level'] ?? 'Beginner', ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <span class="muted"><?php echo htmlspecialchars($summary_excerpt, ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Project">
                            <strong><?php echo htmlspecialchars((string) ($review['project_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="muted">Rating: <?php echo htmlspecialchars((string) ($review['rating'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>/5</span><br>
                            <span class="muted">Trust Weight: <?php echo htmlspecialchars((string) ($preview_level_state['trust_weight'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>x</span>
                        </td>
                        <td data-label="Queue">
                            <span class="dashboard-pill <?php echo $lane_class; ?>"><?php echo htmlspecialchars($lane_label, ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <span class="muted">Bonus <?php echo !empty($preview_score_details['bonus_active']) ? 'active' : 'suspended'; ?></span>
                        </td>
                        <td data-label="Status">
                            <span class="dashboard-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <span class="muted">Proof: <?php echo htmlspecialchars($proof_status, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (($review['eligibility_status'] ?? '') === 'eligible'): ?>
                                <br><span class="muted">On-chain: eligible</span>
                            <?php endif; ?>
                            <br><span class="muted">Reward: <?php echo htmlspecialchars((string) (($review['final_rex'] ?? null) !== null ? $review['final_rex'] : ($review['calculated_rex'] ?? '—')), ENT_QUOTES, 'UTF-8'); ?> $REX</span>
                        </td>
                        <td data-label="Action">
                            <div class="action-stack">
                                <button type="button" class="btn btn-secondary action-view-btn review-view-btn"
                                    data-review-id="<?php echo (int) $review['id']; ?>"
                                    data-review-user-id="<?php echo (int) ($review['user_id'] ?? 0); ?>"
                                    data-username="<?php echo htmlspecialchars((string) ($review['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-email="<?php echo htmlspecialchars((string) ($review['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-level="<?php echo htmlspecialchars($preview_level_state['display_level'] ?? 'Beginner', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-project-name="<?php echo htmlspecialchars((string) ($review['project_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-rating="<?php echo htmlspecialchars((string) ($review['rating'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-review-title="<?php echo htmlspecialchars((string) ($review['review_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-review-content="<?php echo htmlspecialchars((string) ($review['review_content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-pros="<?php echo htmlspecialchars((string) ($review['pros'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-cons="<?php echo htmlspecialchars((string) ($review['cons'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-tx-hash="<?php echo htmlspecialchars((string) ($review['tx_hash'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-wallet-address="<?php echo htmlspecialchars((string) ($review['wallet_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-screenshot-url="<?php echo htmlspecialchars((string) ($review['screenshot_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-holding-amount="<?php echo htmlspecialchars((string) ($review['holding_amount'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-holding-days="<?php echo htmlspecialchars((string) ($review['holding_days'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-wallet-type="<?php echo htmlspecialchars((string) ($review['wallet_type'] ?? 'custodial'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-eligibility-status="<?php echo htmlspecialchars((string) ($review['eligibility_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-eligibility-wallet="<?php echo htmlspecialchars((string) ($review['eligibility_wallet_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-eligibility-chain="<?php echo htmlspecialchars((string) ($review['eligibility_chain_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-eligibility-contract="<?php echo htmlspecialchars((string) ($review['eligibility_contract_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-score="<?php echo htmlspecialchars((string) number_format($preview_score, 1), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-reward="<?php echo htmlspecialchars((string) $preview_reward, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-trust-weight="<?php echo htmlspecialchars((string) ($preview_level_state['trust_weight'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-lane-label="<?php echo htmlspecialchars($lane_label, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-bonus-active="<?php echo !empty($preview_score_details['bonus_active']) ? '1' : '0'; ?>"
                                    data-approval-lane="<?php echo htmlspecialchars($approval_lane, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-user-approved-reviews="<?php echo (int) ($review['user_approved_reviews'] ?? 0); ?>"
                                    data-user-rejected-reviews="<?php echo (int) ($review['user_rejected_reviews'] ?? 0); ?>"
                                    data-user-total-reviews="<?php echo (int) ($review['user_total_reviews'] ?? 0); ?>"
                                    data-project-max-reward="<?php echo htmlspecialchars((string) ($review['project_max_reward'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-score-holding="<?php echo htmlspecialchars((string) $preview_breakdown['holding'], ENT_QUOTES, 'UTF-8'); ?>/20"
                                    data-score-duration="<?php echo htmlspecialchars((string) $preview_breakdown['duration'], ENT_QUOTES, 'UTF-8'); ?>/20"
                                    data-score-quality="<?php echo htmlspecialchars((string) $preview_breakdown['quality'], ENT_QUOTES, 'UTF-8'); ?>/20"
                                    data-score-history="<?php echo htmlspecialchars((string) $preview_breakdown['history'], ENT_QUOTES, 'UTF-8'); ?>/20"
                                    data-score-wallet="<?php echo htmlspecialchars((string) $preview_breakdown['wallet'], ENT_QUOTES, 'UTF-8'); ?>/20"
                                    data-score-raw="<?php echo htmlspecialchars((string) $preview_breakdown['total'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-score-bonus="<?php echo htmlspecialchars((string) ($preview_score_details['level_bonus'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>"
                                ><i class="fas fa-eye"></i> View</button>
                                <form method="POST" action="" class="action-stack-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                    <input type="hidden" name="review_user_id" value="<?php echo (int) ($review['user_id'] ?? 0); ?>">
                                    <button type="submit" name="action" value="approve" class="btn btn-primary action-stack-btn"><i class="fas fa-check-circle"></i> Approve</button>
                                    <button type="submit" name="action" value="reject" class="btn btn-danger action-stack-btn"><i class="fas fa-times-circle"></i> Reject</button>
                                    <button type="submit" name="action" value="flag" class="btn btn-secondary action-stack-btn"><i class="fas fa-flag"></i> Flag</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.dashboard-container -->

<!-- ====== REVIEW DETAIL MODAL ====== -->
<div class="dashboard-modal" id="reviewDetailModal">
    <div class="dashboard-modal-card">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-shield-halved"></i> Review Details</span>
                <h3 id="modalReviewTitle">Review</h3>
            </div>
            <button type="button" class="dashboard-modal-close" id="closeReviewModal">&times;</button>
        </div>
        <div class="dashboard-modal-body">

            <!-- ====== HERO CARD ====== -->
            <div class="modal-hero-card">
                <div class="modal-hero-avatar">
                    <i class="fas fa-user-pen"></i>
                </div>
                <div class="modal-hero-info">
                    <h2 id="modalReviewerName">Reviewer</h2>
                    <span class="modal-hero-slug" id="modalReviewerEmail">email</span>
                    <div class="modal-hero-badges">
                        <span class="modal-hero-badge" id="modalReviewerLevel">Beginner</span>
                        <span class="modal-hero-badge is-feature" id="modalReviewLane">Standard</span>
                    </div>
                </div>
                <div class="modal-hero-score">
                    <div class="modal-hero-score-value" id="modalReviewScore">0</div>
                    <div class="modal-hero-score-label">Score</div>
                </div>
            </div>

            <!-- ====== QUALITY METRICS ROW ====== -->
            <div class="modal-metrics-row">
                <div class="modal-metric-card">
                    <div class="modal-metric-icon is-gold"><i class="fas fa-star"></i></div>
                    <div class="modal-metric-body">
                        <span class="modal-metric-value" id="modalReviewRating">0.0</span>
                        <span class="modal-metric-label">Rating</span>
                    </div>
                </div>
                <div class="modal-metric-card">
                    <div class="modal-metric-icon is-blue"><i class="fas fa-coins"></i></div>
                    <div class="modal-metric-body">
                        <span class="modal-metric-value" id="modalReviewReward">0</span>
                        <span class="modal-metric-label">$REX Reward</span>
                    </div>
                </div>
                <div class="modal-metric-card">
                    <div class="modal-metric-icon is-green"><i class="fas fa-weight-scale"></i></div>
                    <div class="modal-metric-body">
                        <span class="modal-metric-value" id="modalTrustWeight">1x</span>
                        <span class="modal-metric-label">Trust Weight</span>
                    </div>
                </div>
            </div>

            <!-- ====== TWO-COLUMN GRID ====== -->
            <div class="modal-grid-2col">

                <!-- Score Breakdown Card -->
                <div class="modal-info-card">
                    <div class="modal-info-card-header">
                        <i class="fas fa-chart-pie"></i> Score Breakdown
                    </div>
                    <div class="modal-info-card-body">
                        <div class="modal-info-row">
                            <span class="modal-info-label">Holding Amount</span>
                            <span class="modal-info-value" id="modalScoreHolding">0/20</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Holding Duration</span>
                            <span class="modal-info-value" id="modalScoreDuration">0/20</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Review Quality</span>
                            <span class="modal-info-value" id="modalScoreQuality">0/20</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Reviewer History</span>
                            <span class="modal-info-value" id="modalScoreHistory">0/20</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Wallet Type</span>
                            <span class="modal-info-value" id="modalScoreWallet">0/20</span>
                        </div>
                        <div class="modal-info-row" style="border-top:1px solid rgba(212,175,55,0.2);padding-top:10px;margin-top:4px;">
                            <span class="modal-info-label" style="color:#f5d76e;font-weight:700;">Raw Score</span>
                            <span class="modal-info-value" style="color:#f5d76e;font-weight:700;" id="modalScoreRaw">0</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Level Bonus</span>
                            <span class="modal-info-value" id="modalScoreBonus">+0</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Bonus Active</span>
                            <span class="modal-info-value" id="modalBonusActive">Yes</span>
                        </div>
                        <div class="modal-info-row" style="border-top:1px solid rgba(212,175,55,0.2);padding-top:10px;margin-top:4px;">
                            <span class="modal-info-label" style="color:#f5d76e;font-weight:700;">Final Score</span>
                            <span class="modal-info-value" style="color:#f5d76e;font-weight:700;" id="modalScoreFinal">0</span>
                        </div>
                    </div>
                </div>

                <!-- Proof Pack Card -->
                <div class="modal-info-card">
                    <div class="modal-info-card-header">
                        <i class="fas fa-shield"></i> Proof Pack
                    </div>
                    <div class="modal-info-card-body">
                        <div class="modal-info-row">
                            <span class="modal-info-label">TX Hash</span>
                            <span class="modal-info-value" style="font-family:monospace;font-size:11px;word-break:break-all;" id="modalTxHash">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Wallet</span>
                            <span class="modal-info-value" style="font-family:monospace;font-size:11px;word-break:break-all;" id="modalWalletAddress">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Wallet Type</span>
                            <span class="modal-info-value" id="modalWalletType">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Holding Amount</span>
                            <span class="modal-info-value" id="modalHoldingAmount">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Holding Days</span>
                            <span class="modal-info-value" id="modalHoldingDays">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Screenshot</span>
                            <span class="modal-info-value" id="modalScreenshotUrl">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">On-chain Status</span>
                            <span class="modal-info-value" id="modalEligibilityStatus">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Matched Chain</span>
                            <span class="modal-info-value" id="modalEligibilityChain">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Matched Contract</span>
                            <span class="modal-info-value" style="font-family:monospace;font-size:11px;word-break:break-all;" id="modalEligibilityContract">—</span>
                        </div>
                    </div>
                </div>

                <!-- Reviewer Context Card -->
                <div class="modal-info-card">
                    <div class="modal-info-card-header">
                        <i class="fas fa-user"></i> Reviewer Context
                    </div>
                    <div class="modal-info-card-body">
                        <div class="modal-info-row">
                            <span class="modal-info-label">Username</span>
                            <span class="modal-info-value" id="modalUsername">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Email</span>
                            <span class="modal-info-value" id="modalEmail">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Level</span>
                            <span class="modal-info-value" id="modalLevel">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Approved Reviews</span>
                            <span class="modal-info-value" id="modalApprovedReviews">0</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Rejected Reviews</span>
                            <span class="modal-info-value" id="modalRejectedReviews">0</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Total Reviews</span>
                            <span class="modal-info-value" id="modalTotalReviews">0</span>
                        </div>
                    </div>
                </div>

                <!-- Project Context Card -->
                <div class="modal-info-card">
                    <div class="modal-info-card-header">
                        <i class="fas fa-cube"></i> Project Context
                    </div>
                    <div class="modal-info-card-body">
                        <div class="modal-info-row">
                            <span class="modal-info-label">Project</span>
                            <span class="modal-info-value" id="modalProjectName">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Rating Given</span>
                            <span class="modal-info-value" id="modalRating">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Max Reward</span>
                            <span class="modal-info-value" id="modalMaxReward">0 $REX</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Approval Lane</span>
                            <span class="modal-info-value" id="modalApprovalLane">Standard</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Queue Label</span>
                            <span class="modal-info-value" id="modalLaneLabel">24-48 hours</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ====== REVIEW CONTENT CARD ====== -->
            <div class="modal-info-card modal-info-card-full">
                <div class="modal-info-card-header">
                    <i class="fas fa-align-left"></i> Review Content
                </div>
                <div class="modal-info-card-body">
                    <div class="modal-info-row">
                        <span class="modal-info-label">Title</span>
                        <span class="modal-info-value" id="modalReviewTitleText">—</span>
                    </div>
                    <div style="padding:12px 0;border-bottom:1px solid rgba(148,163,184,0.05);">
                        <span class="modal-info-label" style="display:block;margin-bottom:6px;">Content</span>
                        <p class="modal-description" id="modalReviewContentText">No content provided.</p>
                    </div>
                    <div style="padding:12px 0;border-bottom:1px solid rgba(148,163,184,0.05);">
                        <span class="modal-info-label" style="display:block;margin-bottom:6px;">Pros</span>
                        <p class="modal-description" id="modalReviewPros">None listed.</p>
                    </div>
                    <div style="padding:12px 0;">
                        <span class="modal-info-label" style="display:block;margin-bottom:6px;">Cons</span>
                        <p class="modal-description" id="modalReviewCons">None listed.</p>
                    </div>
                </div>
            </div>

            <!-- ====== MODERATION ACTION FORM ====== -->
            <div class="modal-info-card modal-info-card-full" style="border-color:rgba(212,175,55,0.15);background:linear-gradient(135deg,rgba(212,175,55,0.04),rgba(245,215,110,0.02));">
                <div class="modal-info-card-header">
                    <i class="fas fa-gavel"></i> Moderation Action
                </div>
                <div class="modal-info-card-body">
                    <form method="POST" action="" id="modalModerationForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="review_id" id="modalFormReviewId" value="">
                        <input type="hidden" name="review_user_id" id="modalFormReviewUserId" value="">
                        <input type="hidden" name="action" id="modalFormAction" value="">
                        <div style="margin-bottom:12px;">
                            <label style="display:block;font-size:12px;font-weight:600;color:#94a3b8;margin-bottom:4px;">Moderation Note (optional)</label>
                            <textarea name="moderation_note" id="modalModerationNote" rows="3" style="width:100%;background:linear-gradient(180deg,rgba(15,23,42,0.96),rgba(11,18,32,0.92));border:1px solid rgba(148,163,184,0.18);color:#f8fafc;border-radius:12px;padding:10px 12px;font-size:13px;resize:vertical;" placeholder="Add a note for the reviewer..."></textarea>
                        </div>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <button type="button" class="btn btn-primary" id="modalApproveBtn"><i class="fas fa-check-circle"></i> Approve</button>
                            <button type="button" class="btn btn-danger" id="modalRejectBtn"><i class="fas fa-times-circle"></i> Reject</button>
                            <button type="button" class="btn btn-secondary" id="modalFlagBtn"><i class="fas fa-flag"></i> Flag</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const modal = document.getElementById('reviewDetailModal');
    const closeBtn = document.getElementById('closeReviewModal');
    const viewBtns = document.querySelectorAll('.review-view-btn');

    function openModal(data) {
        // Hero
        document.getElementById('modalReviewTitle').textContent = 'Review #' + (data.reviewId || '');
        document.getElementById('modalReviewerName').textContent = data.username || 'Reviewer';
        document.getElementById('modalReviewerEmail').textContent = data.email || '';
        document.getElementById('modalReviewerLevel').textContent = data.level || 'Beginner';
        document.getElementById('modalReviewLane').textContent = data.laneLabel || 'Standard';
        document.getElementById('modalReviewScore').textContent = data.score || '0';

        // Metrics
        document.getElementById('modalReviewRating').textContent = data.rating || '0';
        document.getElementById('modalReviewReward').textContent = data.reward || '0';
        document.getElementById('modalTrustWeight').textContent = (data.trustWeight || '1') + 'x';

        // Score breakdown
        document.getElementById('modalScoreHolding').textContent = data.scoreHolding || '0/20';
        document.getElementById('modalScoreDuration').textContent = data.scoreDuration || '0/20';
        document.getElementById('modalScoreQuality').textContent = data.scoreQuality || '0/20';
        document.getElementById('modalScoreHistory').textContent = data.scoreHistory || '0/20';
        document.getElementById('modalScoreWallet').textContent = data.scoreWallet || '0/20';
        document.getElementById('modalScoreRaw').textContent = data.scoreRaw || '0';
        document.getElementById('modalScoreBonus').textContent = '+ ' + (data.scoreBonus || '0');
        document.getElementById('modalBonusActive').textContent = data.bonusActive === '1' ? 'Yes' : 'Suspended';
        document.getElementById('modalScoreFinal').textContent = data.score || '0';

        // Proof pack — with click-to-copy
        var txEl = document.getElementById('modalTxHash');
        var txVal = data.txHash || '—';
        txEl.textContent = txVal;
        txEl.className = 'modal-info-value modal-contract is-copyable';
        txEl.title = 'Click to copy TX hash';

        var walletEl = document.getElementById('modalWalletAddress');
        var walletVal = data.walletAddress || '—';
        walletEl.textContent = walletVal;
        walletEl.className = 'modal-info-value modal-contract is-copyable';
        walletEl.title = 'Click to copy wallet address';
        document.getElementById('modalWalletType').textContent = data.walletType || '—';
        document.getElementById('modalHoldingAmount').textContent = data.holdingAmount || '—';
        document.getElementById('modalHoldingDays').textContent = data.holdingDays || '—';
        var screenshotEl = document.getElementById('modalScreenshotUrl');
        if (data.screenshotUrl) {
            screenshotEl.innerHTML = '<a href="' + data.screenshotUrl + '" target="_blank" rel="noopener noreferrer" class="modal-link"><i class="fas fa-image"></i> View Screenshot</a>';
        } else {
            screenshotEl.textContent = '—';
        }

        document.getElementById('modalEligibilityStatus').textContent = data.eligibilityStatus || '—';
        document.getElementById('modalEligibilityChain').textContent = data.eligibilityChain || '—';
        document.getElementById('modalEligibilityContract').textContent = data.eligibilityContract || '—';

        // Reviewer context
        document.getElementById('modalUsername').textContent = data.username || '—';
        document.getElementById('modalEmail').textContent = data.email || '—';
        document.getElementById('modalLevel').textContent = data.level || '—';
        document.getElementById('modalApprovedReviews').textContent = data.userApprovedReviews || '0';
        document.getElementById('modalRejectedReviews').textContent = data.userRejectedReviews || '0';
        document.getElementById('modalTotalReviews').textContent = data.userTotalReviews || '0';

        // Project context
        document.getElementById('modalProjectName').textContent = data.projectName || '—';
        document.getElementById('modalRating').textContent = data.rating || '—';
        document.getElementById('modalMaxReward').textContent = (data.projectMaxReward || '0') + ' $REX';
        document.getElementById('modalApprovalLane').textContent = data.approvalLane || 'Standard';
        document.getElementById('modalLaneLabel').textContent = data.laneLabel || '24-48 hours';

        // Review content
        document.getElementById('modalReviewTitleText').textContent = data.reviewTitle || '—';
        document.getElementById('modalReviewContentText').textContent = data.reviewContent || 'No content provided.';
        document.getElementById('modalReviewPros').textContent = data.pros || 'None listed.';
        document.getElementById('modalReviewCons').textContent = data.cons || 'None listed.';

        // Form
        document.getElementById('modalFormReviewId').value = data.reviewId || '';
        document.getElementById('modalFormReviewUserId').value = data.reviewUserId || '';

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    viewBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            openModal({
                reviewId: btn.getAttribute('data-review-id'),
                reviewUserId: btn.getAttribute('data-review-user-id'),
                username: btn.getAttribute('data-username'),
                email: btn.getAttribute('data-email'),
                level: btn.getAttribute('data-level'),
                projectName: btn.getAttribute('data-project-name'),
                rating: btn.getAttribute('data-rating'),
                reviewTitle: btn.getAttribute('data-review-title'),
                reviewContent: btn.getAttribute('data-review-content'),
                pros: btn.getAttribute('data-pros'),
                cons: btn.getAttribute('data-cons'),
                txHash: btn.getAttribute('data-tx-hash'),
                walletAddress: btn.getAttribute('data-wallet-address'),
                screenshotUrl: btn.getAttribute('data-screenshot-url'),
                holdingAmount: btn.getAttribute('data-holding-amount'),
                holdingDays: btn.getAttribute('data-holding-days'),
                walletType: btn.getAttribute('data-wallet-type'),
                eligibilityStatus: btn.getAttribute('data-eligibility-status'),
                eligibilityWallet: btn.getAttribute('data-eligibility-wallet'),
                eligibilityChain: btn.getAttribute('data-eligibility-chain'),
                eligibilityContract: btn.getAttribute('data-eligibility-contract'),
                score: btn.getAttribute('data-score'),
                reward: btn.getAttribute('data-reward'),
                trustWeight: btn.getAttribute('data-trust-weight'),
                laneLabel: btn.getAttribute('data-lane-label'),
                bonusActive: btn.getAttribute('data-bonus-active'),
                approvalLane: btn.getAttribute('data-approval-lane'),
                userApprovedReviews: btn.getAttribute('data-user-approved-reviews'),
                userRejectedReviews: btn.getAttribute('data-user-rejected-reviews'),
                userTotalReviews: btn.getAttribute('data-user-total-reviews'),
                projectMaxReward: btn.getAttribute('data-project-max-reward'),
                scoreHolding: btn.getAttribute('data-score-holding'),
                scoreDuration: btn.getAttribute('data-score-duration'),
                scoreQuality: btn.getAttribute('data-score-quality'),
                scoreHistory: btn.getAttribute('data-score-history'),
                scoreWallet: btn.getAttribute('data-score-wallet'),
                scoreRaw: btn.getAttribute('data-score-raw'),
                scoreBonus: btn.getAttribute('data-score-bonus'),
            });
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) closeModal();
    });

    // Click-to-copy for TX hash and wallet address
    document.addEventListener('click', function(e) {
        var target = e.target.closest('.modal-contract.is-copyable');
        if (!target) return;
        var text = target.textContent.trim();
        if (!text || text === '—') return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                target.classList.add('is-copied');
                setTimeout(function() { target.classList.remove('is-copied'); }, 2000);
            }).catch(function() { fallbackCopy(target, text); });
        } else {
            fallbackCopy(target, text);
        }
    });

    function fallbackCopy(el, text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            el.classList.add('is-copied');
            setTimeout(function() { el.classList.remove('is-copied'); }, 2000);
        } catch (e) {}
        document.body.removeChild(ta);
    }

    // Moderation action buttons
    document.getElementById('modalApproveBtn').addEventListener('click', function() {
        document.getElementById('modalFormAction').value = 'approve';
        document.getElementById('modalModerationForm').submit();
    });
    document.getElementById('modalRejectBtn').addEventListener('click', function() {
        document.getElementById('modalFormAction').value = 'reject';
        document.getElementById('modalModerationForm').submit();
    });
    document.getElementById('modalFlagBtn').addEventListener('click', function() {
        document.getElementById('modalFormAction').value = 'flag';
        document.getElementById('modalModerationForm').submit();
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
