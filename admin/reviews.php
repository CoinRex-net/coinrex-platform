<?php
$page_title = 'Review Verification';
$activePage = 'reviews';
require_once __DIR__ . '/includes/header.php';

function scoreHoldingAmount($amount) {
    $amount = (float) $amount;
    if ($amount >= 100) {
        return 20;
    }
    if ($amount >= 50) {
        return 15;
    }
    if ($amount >= 20) {
        return 10;
    }
    return 5;
}

function scoreHoldingDuration($days) {
    $days = (int) $days;
    if ($days >= 30) {
        return 20;
    }
    if ($days >= 15) {
        return 15;
    }
    if ($days >= 7) {
        return 10;
    }
    return 5;
}

function scoreReviewQuality($content) {
    $length = mb_strlen(trim((string) $content));
    if ($length >= 150 && $length <= 250) {
        return 20;
    }
    if (($length >= 100 && $length <= 149) || ($length >= 250 && $length <= 400)) {
        return 15;
    }
    if ($length >= 50 && $length <= 99) {
        return 10;
    }
    return 5;
}

function scoreReviewerHistory($approved_count, $rejected_count, $total_count) {
    $approved_count = (int) $approved_count;
    $rejected_count = (int) $rejected_count;
    $total_count = (int) $total_count;

    if ($rejected_count === 0 && $approved_count >= 5) {
        $score = 20;
    } elseif ($rejected_count <= 2) {
        $score = 15;
    } elseif ($rejected_count <= 5) {
        $score = 10;
    } else {
        $score = 5;
    }

    return (float) $score;
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

    $total = (float) $holding + (float) $duration + (float) $quality + (float) $history + (float) $wallet;
    $total = round($total, 2);

    return [
        'holding' => $holding,
        'duration' => $duration,
        'quality' => $quality,
        'history' => round($history, 2),
        'wallet' => $wallet,
        'total' => $total,
    ];
}

function calculateRewardFromScore($score, $project_max_reward, $wallet_type) {
    return calculateRewardFromFinalScore($score, $project_max_reward, $wallet_type);
}

function validateApproveEligibility(PDO $db, $review, $wallet_reuse_threshold = 5) {
    $errors = [];
    $review_id = (int) ($review['id'] ?? 0);
    $tx_hash = trim((string) ($review['tx_hash'] ?? ''));
    $wallet_address = trim((string) ($review['wallet_address'] ?? ''));
    $screenshot_url = trim((string) ($review['screenshot_url'] ?? ''));
    $review_content = trim((string) ($review['review_content'] ?? ''));

    if ($tx_hash === '') {
        $errors[] = 'TX hash is required.';
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM reviews WHERE tx_hash = ? AND id != ?");
        $stmt->execute([$tx_hash, $review_id]);
        $duplicate_tx = (int) ($stmt->fetch()['total'] ?? 0);
        if ($duplicate_tx > 0) {
            $errors[] = 'TX hash must be unique.';
        }
    }

    if ($wallet_address === '') {
        $errors[] = 'Wallet address is required.';
    } else {
        $stmt = $db->prepare("SELECT COUNT(*) AS total FROM reviews WHERE wallet_address = ? AND id != ?");
        $stmt->execute([$wallet_address, $review_id]);
        $wallet_reuse_count = (int) ($stmt->fetch()['total'] ?? 0);
        if ($wallet_reuse_count >= $wallet_reuse_threshold) {
            $errors[] = 'Wallet address is reused excessively.';
        }
    }

    if ($screenshot_url === '') {
        $errors[] = 'Screenshot proof is required.';
    }

    if ($review_content === '') {
        $errors[] = 'Review text must not be empty.';
    }

    return $errors;
}

$db = getDBConnection();
ensureLevelEngineSchema($db);
ensureRewardClaimSchema($db);
$current_admin = getCurrentAdmin();
$message = '';
$message_type = 'success';
$status_filter = trim((string) ($_GET['status'] ?? 'pending'));
$valid_filters = ['pending', 'approved', 'rejected', 'flagged', 'all'];
if (!in_array($status_filter, $valid_filters, true)) {
    $status_filter = 'pending';
}
$wallet_filter = trim((string) ($_GET['wallet_type'] ?? 'all'));
$valid_wallet_filters = ['all', 'custodial', 'non_custodial'];
if (!in_array($wallet_filter, $valid_wallet_filters, true)) {
    $wallet_filter = 'all';
}

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
                SELECT
                    r.*,
                    COALESCE(p.max_reward_rex, 0) AS project_max_reward,
                    COALESCE(NULLIF(TRIM(u.level), ''), 'beginner') AS user_level,
                    COALESCE(user_stats.approved_reviews, 0) AS user_approved_reviews,
                    COALESCE(user_stats.rejected_reviews, 0) AS user_rejected_reviews,
                    COALESCE(user_stats.total_reviews, 0) AS user_total_reviews
                FROM reviews r
                LEFT JOIN projects p ON p.id = r.project_id
                LEFT JOIN users u ON u.id = r.user_id
                LEFT JOIN (
                    SELECT
                        user_id,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_reviews,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_reviews,
                        COUNT(*) AS total_reviews
                    FROM reviews
                    GROUP BY user_id
                ) user_stats ON user_stats.user_id = r.user_id
                WHERE r.id = ?
                FOR UPDATE
            ");
            $current_stmt->execute([$review_id]);
            $current_review = $current_stmt->fetch();

            if (!$current_review) {
                throw new RuntimeException('Review not found.');
            }

            $previous_status = (string) ($current_review['status'] ?? 'pending');
            $breakdown = calculateScoreBreakdown($current_review);
            $user_level_state = getUserLevelState(
                [
                    'id' => (int) ($current_review['user_id'] ?? 0),
                    'level' => (string) ($current_review['user_level'] ?? 'beginner'),
                ],
                $db
            );
            $score_details = calculateReviewFinalScore(
                (float) $breakdown['total'],
                $user_level_state,
                ['user_total_reviews' => (int) ($current_review['user_total_reviews'] ?? 0)]
            );
            $score_total = (float) $score_details['final_score'];
            $wallet_type = (string) ($current_review['wallet_type'] ?? 'custodial');
            $reward_amount = calculateRewardFromScore($score_total, (float) ($current_review['project_max_reward'] ?? 0), $wallet_type);

            if ($new_status === 'approved') {
                $eligibility_errors = validateApproveEligibility($db, $current_review);
                if (!empty($eligibility_errors)) {
                    throw new RuntimeException(implode(' ', $eligibility_errors));
                }
            }

            $updates = ["status = ?", "updated_at = NOW()"];
            $params = [$new_status];

            if ($has_proof_status) {
                $updates[] = "proof_status = ?";
                $params[] = $new_status === 'approved' ? 'verified' : ($new_status === 'rejected' ? 'rejected' : 'flagged');
            }
            if ($has_reviewed_by) {
                $updates[] = "reviewed_by = ?";
                $params[] = (int) $current_admin['id'];
            }
            if ($has_reviewed_at) {
                $updates[] = "reviewed_at = NOW()";
            }
            if ($has_approval_note) {
                $updates[] = "approval_note = ?";
                $params[] = $new_status === 'approved' ? $moderation_note : null;
            }
            if ($has_rejection_reason) {
                $updates[] = "rejection_reason = ?";
                $params[] = ($new_status === 'rejected' || $new_status === 'flagged') ? $moderation_note : null;
            }
            if ($has_proof_verified_by) {
                $updates[] = "proof_verified_by = ?";
                $params[] = $new_status === 'approved' ? (int) $current_admin['id'] : null;
            }
            if ($has_proof_verified_at) {
                $updates[] = "proof_verified_at = " . ($new_status === 'approved' ? "NOW()" : "NULL");
            }
            if ($has_proof_rejection_reason) {
                $updates[] = "proof_rejection_reason = ?";
                $params[] = ($new_status === 'rejected' || $new_status === 'flagged') ? $moderation_note : null;
            }
            if ($has_review_score) {
                $updates[] = "review_score = ?";
                $params[] = $new_status === 'approved' ? $score_total : 0;
            }
            if ($has_final_rex) {
                $updates[] = "final_rex = ?";
                $params[] = $new_status === 'approved' ? $reward_amount : 0;
            }
            if ($has_auto_approved_at) {
                $updates[] = "auto_approved_at = NULL";
            }
            if ($has_auto_approved_by_level) {
                $updates[] = "auto_approved_by_level = 0";
            }

            $params[] = $review_id;
            $sql = "UPDATE reviews SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            // Reward posting logic.
            if ($new_status === 'approved' && $previous_status !== 'approved' && $has_users_rex_balance) {
                if ($reward_amount > 0) {
                    addRewardLedgerEntry(
                        $review_user_id,
                        $reward_amount,
                        'review',
                        'review_approval_reward',
                        'available',
                        'review:' . $review_id,
                        $db,
                        resolveRewardPhase('review', $user_level_state['level'] ?? 'beginner'),
                        $user_level_state['level'] ?? 'beginner'
                    );
                }
                syncUserReviewCounters($review_user_id, $db);
                maybeActivateReferralQualification($review_user_id, $db);
                creditReferralCommissionForReview($review_user_id, $reward_amount, $db);
            } elseif ($new_status !== 'approved' && $previous_status === 'approved' && $has_users_rex_balance) {
                $previous_reward = 0;
                if ($has_final_rex) {
                    $previous_reward = (int) round((float) ($current_review['final_rex'] ?? 0), 0);
                } else {
                    $previous_reward = (int) round((float) ($current_review['calculated_rex'] ?? 0), 0);
                }
                if ($previous_reward > 0) {
                    addRewardLedgerEntry(
                        $review_user_id,
                        -1 * $previous_reward,
                        'review',
                        'review_reward_reversal',
                        'available',
                        'review_reversal:' . $review_id,
                        $db,
                        resolveRewardPhase('review', $user_level_state['level'] ?? 'beginner'),
                        $user_level_state['level'] ?? 'beginner'
                    );
                }
                syncUserReviewCounters($review_user_id, $db);
                reverseReferralCommissionForReview($review_user_id, $previous_reward, $db);
            }

            if ($has_approved_reviews_count) {
                if ($previous_status !== 'approved' && $new_status === 'approved') {
                    $inc = $db->prepare("UPDATE users SET approved_reviews_count = approved_reviews_count + 1 WHERE id = ?");
                    $inc->execute([$review_user_id]);
                } elseif ($previous_status === 'approved' && $new_status !== 'approved') {
                    $dec = $db->prepare("UPDATE users SET approved_reviews_count = CASE WHEN approved_reviews_count > 0 THEN approved_reviews_count - 1 ELSE 0 END WHERE id = ?");
                    $dec->execute([$review_user_id]);
                }
            }

            syncUserLevelStatus($review_user_id, $db);
            syncProjectAggregateMetrics((int) ($current_review['project_id'] ?? 0), $db);

            logAdminActivity(
                (int) $current_admin['id'],
                'review_moderation_' . $new_status,
                'review',
                (string) $review_id,
                json_encode([
                    'note' => $moderation_note,
                    'previous_status' => $previous_status,
                    'score' => $score_total,
                    'reward' => $reward_amount,
                    'level_bonus' => $score_details['level_bonus'],
                    'bonus_active' => $score_details['bonus_active'],
                    'approval_lane' => $user_level_state['approval_lane'] ?? 'standard'
                ], JSON_UNESCAPED_UNICODE)
            );

            $project_name_for_notice = (string) ($current_review['project_name'] ?? 'project');
            if ($new_status === 'approved') {
                createTemplatedNotification('review.approved', 'user', $review_user_id, [
                    'project_name' => $project_name_for_notice,
                ], [
                    'actor_type' => 'admin',
                    'actor_id' => (int) $current_admin['id'],
                    'meta' => ['review_id' => $review_id, 'status' => $new_status],
                ], $db);
            } elseif ($new_status === 'rejected') {
                createTemplatedNotification('review.rejected', 'user', $review_user_id, [
                    'project_name' => $project_name_for_notice,
                    'reason' => $moderation_note !== '' ? $moderation_note : 'No reason provided',
                ], [
                    'actor_type' => 'admin',
                    'actor_id' => (int) $current_admin['id'],
                    'meta' => ['review_id' => $review_id, 'status' => $new_status],
                ], $db);
            } elseif ($new_status === 'flagged') {
                createTemplatedNotification('review.flagged', 'user', $review_user_id, [
                    'project_name' => $project_name_for_notice,
                ], [
                    'actor_type' => 'admin',
                    'actor_id' => (int) $current_admin['id'],
                    'meta' => ['review_id' => $review_id, 'status' => $new_status],
                ], $db);
            }

            $db->commit();
            $message = 'Review moderation action applied.';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
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
if ($status_filter !== 'all') {
    $where_parts[] = "r.status = ?";
    $params[] = $status_filter;
}
if ($has_wallet_type && $wallet_filter !== 'all') {
    $where_parts[] = "r.wallet_type = ?";
    $params[] = $wallet_filter;
}
$where = '';
if (!empty($where_parts)) {
    $where = "WHERE " . implode(' AND ', $where_parts);
}

$proof_select = $has_proof_status ? "r.proof_status" : "NULL AS proof_status";
$reason_select = $has_rejection_reason ? "r.rejection_reason" : "NULL AS rejection_reason";
$wallet_select = $has_wallet_type ? "r.wallet_type" : "NULL AS wallet_type";
$final_rex_select = $has_final_rex ? "r.final_rex" : "NULL AS final_rex";
$score_select = $has_review_score ? "r.review_score" : "NULL AS review_score";

$stmt = $db->prepare("
    SELECT
        r.id, r.user_id, r.project_id, r.review_title, r.review_content, r.rating, r.status, {$proof_select},
        r.tx_hash, r.wallet_address, r.screenshot_url, r.created_at, {$reason_select},
        r.holding_amount, r.holding_days, r.calculated_rex, {$final_rex_select}, {$wallet_select}, {$score_select},
        u.username, u.email, u.level,
        p.name AS project_name,
        COALESCE(p.max_reward_rex, 0) AS project_max_reward,
        COALESCE(user_stats.approved_reviews, 0) AS user_approved_reviews,
        COALESCE(user_stats.rejected_reviews, 0) AS user_rejected_reviews,
        COALESCE(user_stats.total_reviews, 0) AS user_total_reviews
    FROM reviews r
    LEFT JOIN users u ON u.id = r.user_id
    LEFT JOIN projects p ON p.id = r.project_id
    LEFT JOIN (
        SELECT
            user_id,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_reviews,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_reviews,
            COUNT(*) AS total_reviews
        FROM reviews
        GROUP BY user_id
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

$review_queue_stats = [
    'pending' => 0,
    'approved' => 0,
    'flagged' => 0,
    'expert_lane' => 0,
    'pro_lane' => 0,
];

foreach ($reviews as $queue_review) {
    $queue_status = (string) ($queue_review['status'] ?? 'pending');
    if (isset($review_queue_stats[$queue_status])) {
        $review_queue_stats[$queue_status]++;
    }

    $queue_level_state = getUserLevelState(
        [
            'id' => (int) ($queue_review['user_id'] ?? 0),
            'level' => (string) ($queue_review['level'] ?? 'beginner'),
        ],
        $db
    );

    if (($queue_level_state['approval_lane'] ?? '') === 'auto') {
        $review_queue_stats['expert_lane']++;
    } elseif (($queue_level_state['approval_lane'] ?? '') === 'priority') {
        $review_queue_stats['pro_lane']++;
    }
}
?>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="trust-review-page">
    <div class="panel trust-hero">
        <div class="admin-section-head">
            <div>
                <span class="admin-kicker">Trust Operations</span>
                <h2>Proof-Backed Review Moderation</h2>
                <p class="muted">Validate TX hash, wallet ownership evidence, level bonuses, trust weight, and reward flow before closing each review.</p>
            </div>
        </div>
        <div class="admin-metric-grid admin-metric-grid-tight">
            <div class="admin-metric-card">
                <span class="admin-metric-label">Pending Queue</span>
                <strong><?php echo number_format((int) $review_queue_stats['pending']); ?></strong>
            </div>
            <div class="admin-metric-card">
                <span class="admin-metric-label">Pro Priority</span>
                <strong><?php echo number_format((int) $review_queue_stats['pro_lane']); ?></strong>
            </div>
            <div class="admin-metric-card">
                <span class="admin-metric-label">Expert Fast-Track</span>
                <strong><?php echo number_format((int) $review_queue_stats['expert_lane']); ?></strong>
            </div>
            <div class="admin-metric-card">
                <span class="admin-metric-label">Flagged</span>
                <strong><?php echo number_format((int) $review_queue_stats['flagged']); ?></strong>
            </div>
        </div>
    </div>

    <div class="panel trust-filter-panel">
        <form method="GET" action="" class="inline-form trust-filter-form">
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
            <button type="submit" class="btn btn-secondary trust-btn">Apply Filter</button>
        </form>
    </div>

    <div class="panel trust-table-panel">
        <div class="table-wrap">
            <table class="trust-table responsive-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Reviewer</th>
                    <th>Project</th>
                    <th>Score</th>
                    <th>Queue</th>
                    <th>TX / Wallet / Screenshot</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($reviews as $review): ?>
                    <?php
                    $status = (string) ($review['status'] ?? 'pending');
                    $status_class = 'status-pending';
                    if ($status === 'approved') {
                        $status_class = 'status-approved';
                    } elseif (in_array($status, ['rejected', 'flagged'], true)) {
                        $status_class = 'status-rejected';
                    }
                    $proof_status = (string) ($review['proof_status'] ?? 'pending');
                    $preview_breakdown = calculateScoreBreakdown($review);
                    $preview_level_state = getUserLevelState(
                        [
                            'id' => (int) ($review['user_id'] ?? 0),
                            'level' => (string) ($review['level'] ?? 'beginner'),
                        ],
                        $db
                    );
                    $preview_score_details = calculateReviewFinalScore(
                        (float) $preview_breakdown['total'],
                        $preview_level_state,
                        ['user_total_reviews' => (int) ($review['user_total_reviews'] ?? 0)]
                    );
                    $preview_score = (float) $preview_score_details['final_score'];
                    $preview_reward = calculateRewardFromScore(
                        $preview_score,
                        (float) ($review['project_max_reward'] ?? 0),
                        (string) ($review['wallet_type'] ?? 'custodial')
                    );
                    $score_tone_class = 'score-mid';
                    if ($preview_score > 85) {
                        $score_tone_class = 'score-high';
                    } elseif ($preview_score < 50) {
                        $score_tone_class = 'score-low';
                    }
                    $summary_excerpt = trim((string) ($review['review_title'] ?? '')) !== ''
                        ? (string) $review['review_title']
                        : (string) ($review['review_content'] ?? '');
                    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
                        $summary_excerpt = mb_strlen($summary_excerpt) > 84 ? mb_substr($summary_excerpt, 0, 84) . '...' : $summary_excerpt;
                    } else {
                        $summary_excerpt = strlen($summary_excerpt) > 84 ? substr($summary_excerpt, 0, 84) . '...' : $summary_excerpt;
                    }
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
                        <td data-label="Score">
                            <div class="score-summary-card <?php echo $score_tone_class; ?>">
                                <strong><?php echo number_format($preview_score, 1); ?></strong>
                                <span>/100</span>
                                <small><?php echo $preview_reward; ?> $REX</small>
                            </div>
                        </td>
                        <td data-label="Queue">
                            <span class="status-pill <?php echo ($preview_level_state['approval_lane'] ?? '') === 'auto' ? 'status-approved' : (($preview_level_state['approval_lane'] ?? '') === 'priority' ? 'status-pending' : 'status-disabled'); ?>">
                                <?php echo htmlspecialchars($preview_level_state['approval_label'] ?? '24-48 hours', ENT_QUOTES, 'UTF-8'); ?>
                            </span><br>
                            <span class="muted">Bonus <?php echo !empty($preview_score_details['bonus_active']) ? 'active' : 'suspended'; ?></span>
                        </td>
                        <td data-label="TX / Wallet / Screenshot">
                            <div><strong>TX:</strong> <code><?php echo htmlspecialchars(substr((string) ($review['tx_hash'] ?? ''), 0, 18), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($review['tx_hash']) ? '...' : ''; ?></code></div>
                            <div><strong>Wallet:</strong> <code><?php echo htmlspecialchars(substr((string) ($review['wallet_address'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($review['wallet_address']) ? '...' : ''; ?></code></div>
                            <div><strong>Proof:</strong> <?php echo !empty($review['screenshot_url']) ? 'Uploaded' : 'Missing'; ?></div>
                        </td>
                        <td data-label="Status">
                            <span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <span class="muted">Proof: <?php echo htmlspecialchars($proof_status, ENT_QUOTES, 'UTF-8'); ?></span>
                            <br><span class="muted">Reward: <?php echo htmlspecialchars((string) (($review['final_rex'] ?? null) !== null ? $review['final_rex'] : ($review['calculated_rex'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?> $REX</span>
                            <?php if (!empty($review['rejection_reason'])): ?>
                                <br><span class="muted">Note: <?php echo htmlspecialchars((string) $review['rejection_reason'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Action">
                            <button
                                type="button"
                                class="btn btn-secondary trust-btn review-open-btn"
                                data-review-modal="review-modal-<?php echo (int) $review['id']; ?>"
                            >
                                Open Review
                            </button>

                            <template id="review-modal-<?php echo (int) $review['id']; ?>">
                                <div class="trust-modal-shell">
                                    <div class="trust-modal-headline">
                                        <div>
                                            <span class="admin-kicker">Review #<?php echo (int) $review['id']; ?></span>
                                            <h3><?php echo htmlspecialchars((string) ($review['review_title'] ?? 'Review Detail'), ENT_QUOTES, 'UTF-8'); ?></h3>
                                            <p class="muted"><?php echo htmlspecialchars((string) ($review['project_name'] ?? 'Unknown project'), ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars((string) ($review['username'] ?? 'Unknown reviewer'), ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <div class="trust-modal-badges">
                                            <span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="status-pill <?php echo ($preview_level_state['approval_lane'] ?? '') === 'auto' ? 'status-approved' : (($preview_level_state['approval_lane'] ?? '') === 'priority' ? 'status-pending' : 'status-disabled'); ?>">
                                                <?php echo htmlspecialchars($preview_level_state['display_level'] ?? 'Beginner', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="trust-modal-grid">
                                        <div class="trust-modal-card">
                                            <h4>Score Engine</h4>
                                            <div class="score-breakdown <?php echo $score_tone_class; ?>">
                                                <strong>Preview Breakdown</strong>
                                                <div>Holding: <?php echo $preview_breakdown['holding']; ?></div>
                                                <div>Duration: <?php echo $preview_breakdown['duration']; ?></div>
                                                <div>Quality: <?php echo $preview_breakdown['quality']; ?></div>
                                                <div>History: <?php echo $preview_breakdown['history']; ?></div>
                                                <div>Wallet: <?php echo $preview_breakdown['wallet']; ?></div>
                                                <div>Base Score: <?php echo $preview_score_details['base_score']; ?></div>
                                                <?php if ((int) $preview_score_details['penalty_percent'] > 0): ?>
                                                    <div>First Review Penalty: -<?php echo (int) $preview_score_details['penalty_percent']; ?>%</div>
                                                <?php endif; ?>
                                                <div>Level Bonus: +<?php echo (int) $preview_score_details['level_bonus']; ?></div>
                                                <div>Bonus Status: <?php echo !empty($preview_score_details['bonus_active']) ? 'Active' : 'Suspended'; ?></div>
                                                <div class="score-total-line">Final Score: <?php echo $preview_score; ?> / 100</div>
                                                <div class="score-total-line">Reward: <?php echo $preview_reward; ?> $REX</div>
                                            </div>
                                        </div>
                                        <div class="trust-modal-card">
                                            <h4>Reviewer Context</h4>
                                            <div class="trust-detail-list">
                                                <div><strong>Email:</strong> <?php echo htmlspecialchars((string) ($review['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div><strong>Level:</strong> <?php echo htmlspecialchars($preview_level_state['display_level'] ?? 'Beginner', ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div><strong>Approval Lane:</strong> <?php echo htmlspecialchars($preview_level_state['approval_label'] ?? '24-48 hours', ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div><strong>Trust Weight:</strong> <?php echo htmlspecialchars((string) ($preview_level_state['trust_weight'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>x</div>
                                                <div><strong>Accuracy:</strong> <?php echo number_format((float) ($preview_level_state['accuracy'] ?? 0), 1); ?>%</div>
                                                <div><strong>Approved Reviews:</strong> <?php echo number_format((int) ($preview_level_state['stats']['approved_reviews'] ?? 0)); ?></div>
                                            </div>
                                        </div>
                                        <div class="trust-modal-card">
                                            <h4>Proof Pack</h4>
                                            <div class="trust-detail-list">
                                                <div><strong>TX Hash:</strong> <code><?php echo htmlspecialchars((string) ($review['tx_hash'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></div>
                                                <div><strong>Wallet:</strong> <code><?php echo htmlspecialchars((string) ($review['wallet_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></div>
                                                <?php if ($has_wallet_type): ?>
                                                    <div><strong>Wallet Type:</strong> <?php echo htmlspecialchars((string) ($review['wallet_type'] ?? 'unknown'), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <?php endif; ?>
                                                <div><strong>Proof Status:</strong> <?php echo htmlspecialchars($proof_status, ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div><strong>Screenshot:</strong>
                                                    <?php if (!empty($review['screenshot_url'])): ?>
                                                        <a href="<?php echo htmlspecialchars((string) $review['screenshot_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open proof asset</a>
                                                    <?php else: ?>
                                                        <span class="muted">Missing</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="trust-modal-card trust-modal-card-wide">
                                            <h4>Review Content</h4>
                                            <p class="trust-modal-review-copy"><?php echo nl2br(htmlspecialchars((string) ($review['review_content'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
                                            <?php if (!empty($review['pros']) || !empty($review['cons'])): ?>
                                                <div class="trust-modal-pros-cons">
                                                    <div>
                                                        <strong>Pros</strong>
                                                        <p><?php echo nl2br(htmlspecialchars((string) ($review['pros'] ?? 'Not provided'), ENT_QUOTES, 'UTF-8')); ?></p>
                                                    </div>
                                                    <div>
                                                        <strong>Cons</strong>
                                                        <p><?php echo nl2br(htmlspecialchars((string) ($review['cons'] ?? 'Not provided'), ENT_QUOTES, 'UTF-8')); ?></p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <form method="POST" action="" class="trust-action-form trust-modal-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                        <input type="hidden" name="review_user_id" value="<?php echo (int) $review['user_id']; ?>">
                                        <label class="trust-modal-note-label" for="modal-note-<?php echo (int) $review['id']; ?>">Moderation note</label>
                                        <input type="text" id="modal-note-<?php echo (int) $review['id']; ?>" name="moderation_note" placeholder="Reason for reject/flag, optional note for approval">
                                        <div class="inline-form trust-action-buttons">
                                            <button type="submit" name="action" value="approve" class="btn btn-primary trust-btn">Approve Review</button>
                                            <button type="submit" name="action" value="reject" class="btn btn-danger trust-btn">Reject Review</button>
                                            <button type="submit" name="action" value="flag" class="btn btn-secondary trust-btn">Flag Review</button>
                                        </div>
                                    </form>
                                </div>
                            </template>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="admin-modal" id="reviewDetailModal" aria-hidden="true">
    <div class="admin-modal-card admin-modal-card-lg">
        <div class="admin-modal-header">
            <div>
                <span class="admin-kicker">Review Detail</span>
                <h3 id="reviewDetailModalTitle">Moderation Detail</h3>
            </div>
            <button type="button" class="admin-modal-close" id="reviewDetailModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="admin-modal-body" id="reviewDetailModalBody"></div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('reviewDetailModal');
    var modalBody = document.getElementById('reviewDetailModalBody');
    var modalTitle = document.getElementById('reviewDetailModalTitle');
    var closeBtn = document.getElementById('reviewDetailModalClose');
    var openButtons = document.querySelectorAll('.review-open-btn');

    if (!modal || !modalBody || !closeBtn || !modalTitle || !openButtons.length) {
        return;
    }

    function closeModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
        modalBody.innerHTML = '';
    }

    openButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var templateId = button.getAttribute('data-review-modal');
            var template = templateId ? document.getElementById(templateId) : null;
            if (!template) {
                return;
            }

            modalBody.innerHTML = template.innerHTML;
            var heading = modalBody.querySelector('h3');
            modalTitle.textContent = heading ? heading.textContent : 'Moderation Detail';
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
