<?php
/** Auto-split from legacy functions.php */

function userCanAccessProjectReviewArea($user_or_level_state) {
    $level_state = is_array($user_or_level_state) && isset($user_or_level_state['level'])
        ? $user_or_level_state
        : getUserLevelState($user_or_level_state);

    return normalizeUserLevel($level_state['level'] ?? 'beginner') !== 'beginner';
}

function requireProjectReviewAccess($redirect_path = '/public/dashboard.php') {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/auth/auth.php');
    }

    $user = getCurrentUser();
    if (!$user || !userCanAccessProjectReviewArea($user)) {
        setFlashMessage('dashboard_success', 'Projects and Reviews unlock after you reach Pro. Complete TaskHub first.');
        redirect(BASE_URL . $redirect_path);
    }

    try {
        enforceUserModuleAccess($user, 'review');
    } catch (Throwable $e) {
        setFlashMessage('dashboard_success', $e->getMessage());
        redirect(BASE_URL . '/public/dashboard.php');
    }

    return $user;
}

/**
 * Get an array of project IDs that the given user has already reviewed.
 * Useful for batch-checking on listing pages like projects.php.
 *
 * @param int   $user_id
 * @param PDO   $db
 * @return int[] Array of project IDs
 */
function getUserReviewedProjectIds($user_id, PDO $db)
{
    $stmt = $db->prepare(
        "SELECT DISTINCT project_id
         FROM reviews
         WHERE user_id = ?"
    );
    $stmt->execute([(int) $user_id]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    return array_map('intval', $ids ?: []);
}

function ensureLevelEngineSchema(PDO $db = null) {
    static $schema_ready = false;

    if ($schema_ready) {
        return;
    }

    $db = $db ?: getDBConnection();

    if (!tableHasColumn('users', 'referral_qualified_at')) {
        $db->exec("ALTER TABLE users ADD COLUMN referral_qualified_at DATETIME NULL AFTER valid_referrals");
    }

    if (!tableHasColumn('users', 'referral_earnings')) {
        $db->exec("ALTER TABLE users ADD COLUMN referral_earnings DECIMAL(15,2) DEFAULT 0.00 AFTER referral_qualified_at");
    }

    if (!tableHasColumn('reviews', 'auto_approved_at')) {
        $db->exec("ALTER TABLE reviews ADD COLUMN auto_approved_at DATETIME NULL AFTER approval_note");
    }

    if (!tableHasColumn('reviews', 'auto_approved_by_level')) {
        $db->exec("ALTER TABLE reviews ADD COLUMN auto_approved_by_level TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_approved_at");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS review_reactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            review_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            reaction_type VARCHAR(20) NOT NULL DEFAULT 'like',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_review_user_reaction (review_id, user_id, reaction_type),
            KEY idx_review_reaction_review (review_id),
            KEY idx_review_reaction_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS content_flags (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            target_id INT UNSIGNED NOT NULL,
            reason VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_content_flag_user_target (user_id, target_type, target_id),
            KEY idx_content_flags_target (target_type, target_id),
            KEY idx_content_flags_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (tableExists('projects')) {
        if (!tableHasColumn('projects', 'feature_queue_type')) {
            $db->exec("ALTER TABLE projects ADD COLUMN feature_queue_type VARCHAR(20) NULL AFTER feature_status");
        }
        if (!tableHasColumn('projects', 'is_sponsored')) {
            $db->exec("ALTER TABLE projects ADD COLUMN is_sponsored TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured");
        }
        if (!tableHasColumn('projects', 'sponsored_status')) {
            $db->exec("ALTER TABLE projects ADD COLUMN sponsored_status VARCHAR(20) NOT NULL DEFAULT 'none' AFTER is_sponsored");
        }
        if (!tableHasColumn('projects', 'sponsored_plan')) {
            $db->exec("ALTER TABLE projects ADD COLUMN sponsored_plan VARCHAR(50) NULL AFTER sponsored_status");
        }
        if (!tableHasColumn('projects', 'sponsored_requested_at')) {
            $db->exec("ALTER TABLE projects ADD COLUMN sponsored_requested_at DATETIME NULL AFTER sponsored_plan");
        }
        if (!tableHasColumn('projects', 'sponsored_starts_at')) {
            $db->exec("ALTER TABLE projects ADD COLUMN sponsored_starts_at DATETIME NULL AFTER sponsored_requested_at");
        }
        if (!tableHasColumn('projects', 'sponsored_ends_at')) {
            $db->exec("ALTER TABLE projects ADD COLUMN sponsored_ends_at DATETIME NULL AFTER sponsored_starts_at");
        }
        if (!tableHasColumn('projects', 'priority_review_status')) {
            $db->exec("ALTER TABLE projects ADD COLUMN priority_review_status VARCHAR(20) NOT NULL DEFAULT 'none' AFTER feature_queue_type");
        }
        if (!tableHasColumn('projects', 'priority_review_requested_at')) {
            $db->exec("ALTER TABLE projects ADD COLUMN priority_review_requested_at DATETIME NULL AFTER priority_review_status");
        }
        if (!tableHasColumn('projects', 'priority_review_paid_at')) {
            $db->exec("ALTER TABLE projects ADD COLUMN priority_review_paid_at DATETIME NULL AFTER priority_review_requested_at");
        }
    }

    $schema_ready = true;
}

function projectMeetsFeatureCriteria(array $project_row) {
    $approval_status = strtolower(trim((string) ($project_row['approval_status'] ?? 'pending')));
    return $approval_status === 'approved'
        && (float) ($project_row['avg_rating'] ?? 0) >= (float) FEATURE_MIN_AVG_RATING
        && (int) ($project_row['total_reviews'] ?? 0) >= (int) FEATURE_MIN_APPROVED_REVIEWS;
}

function getProjectPromotionState(array $project_row) {
    $is_featured = (int) ($project_row['is_featured'] ?? 0) === 1;
    $is_sponsored = (int) ($project_row['is_sponsored'] ?? 0) === 1;
    $feature_status = strtolower(trim((string) ($project_row['feature_status'] ?? 'none')));
    $queue_type = strtolower(trim((string) ($project_row['feature_queue_type'] ?? '')));
    $priority_status = strtolower(trim((string) ($project_row['priority_review_status'] ?? 'none')));
    $sponsored_status = strtolower(trim((string) ($project_row['sponsored_status'] ?? 'none')));
    $eligible = projectMeetsFeatureCriteria($project_row);

    return [
        'is_featured' => $is_featured,
        'is_sponsored' => $is_sponsored,
        'feature_status' => $feature_status,
        'feature_queue_type' => $queue_type,
        'priority_review_status' => $priority_status,
        'sponsored_status' => $sponsored_status,
        'eligible' => $eligible,
        'can_request_standard' => $eligible && !$is_featured && $feature_status === 'eligible',
        'can_request_priority' => $eligible && !$is_featured && !in_array($priority_status, ['requested', 'active'], true),
        'can_request_sponsored' => strtolower(trim((string) ($project_row['approval_status'] ?? 'pending'))) === 'approved'
            && !$is_sponsored
            && !in_array($sponsored_status, ['requested', 'active'], true),
    ];
}

function requestProjectPromotion($project_id, $owner_user_id, $request_type, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    $project_id = (int) $project_id;
    $owner_user_id = (int) $owner_user_id;
    $request_type = strtolower(trim((string) $request_type));
    if ($project_id <= 0 || $owner_user_id <= 0) {
        throw new InvalidArgumentException('Invalid project request.');
    }

    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND created_by = ? LIMIT 1");
    $stmt->execute([$project_id, $owner_user_id]);
    $project = $stmt->fetch();
    if (!$project) {
        throw new RuntimeException('Project not found for this developer.');
    }

    $promotion = getProjectPromotionState($project);
    $approval_status = strtolower(trim((string) ($project['approval_status'] ?? 'pending')));

    if ($request_type === 'standard_feature_review') {
        if (!$promotion['can_request_standard']) {
            throw new RuntimeException('Standard featured review is not available for this project yet.');
        }

        $db->prepare("UPDATE projects SET feature_status = 'pending_review', feature_queue_type = 'standard', feature_requested_at = COALESCE(feature_requested_at, NOW()), updated_at = NOW() WHERE id = ?")
            ->execute([$project_id]);
        return ['success' => true, 'message' => 'Standard featured review requested.'];
    }

    if ($request_type === 'priority_feature_review') {
        if (!$promotion['can_request_priority']) {
            throw new RuntimeException('Priority featured review has already been requested or is not available yet.');
        }
        if (!$promotion['eligible']) {
            throw new RuntimeException('Priority review can only be requested after feature eligibility is reached.');
        }

        $db->prepare("UPDATE projects SET priority_review_status = 'requested', priority_review_requested_at = COALESCE(priority_review_requested_at, NOW()), updated_at = NOW() WHERE id = ?")
            ->execute([$project_id]);
        return ['success' => true, 'message' => 'Priority feature review request created. Payment placeholder is now waiting for admin handling.'];
    }

    if ($request_type === 'sponsored') {
        if ($approval_status !== 'approved') {
            throw new RuntimeException('Only approved projects can request sponsored placement.');
        }
        if (!$promotion['can_request_sponsored']) {
            throw new RuntimeException('Sponsored placement is already requested or active for this project.');
        }

        $db->prepare("UPDATE projects SET sponsored_status = 'requested', sponsored_plan = COALESCE(NULLIF(TRIM(sponsored_plan), ''), 'starter'), sponsored_requested_at = COALESCE(sponsored_requested_at, NOW()), updated_at = NOW() WHERE id = ?")
            ->execute([$project_id]);
        return ['success' => true, 'message' => 'Sponsored placement request created. Payment placeholder is now waiting for admin activation.'];
    }

    throw new InvalidArgumentException('Unknown promotion request type.');
}

function adminHandleProjectPromotionAction($project_id, $action, $admin_id = 0, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    $project_id = (int) $project_id;
    $admin_id = (int) $admin_id;
    $action = strtolower(trim((string) $action));
    if ($project_id <= 0) {
        throw new InvalidArgumentException('Invalid project id.');
    }

    $project_stmt = $db->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
    $project_stmt->execute([$project_id]);
    $project = $project_stmt->fetch();
    if (!$project) {
        throw new RuntimeException('Project not found.');
    }

    $promotion = getProjectPromotionState($project);

    if ($action === 'approve_priority_request') {
        if (!$promotion['eligible']) {
            throw new RuntimeException('Project no longer meets feature criteria.');
        }
        $db->prepare("UPDATE projects SET priority_review_status = 'active', priority_review_paid_at = COALESCE(priority_review_paid_at, NOW()), feature_status = 'pending_review', feature_queue_type = 'priority', feature_requested_at = COALESCE(feature_requested_at, NOW()), updated_at = NOW() WHERE id = ?")
            ->execute([$project_id]);
        return 'Priority feature review marked paid and moved to priority review queue.';
    }

    if ($action === 'reject_priority_request') {
        $db->prepare("UPDATE projects SET priority_review_status = 'rejected', updated_at = NOW() WHERE id = ?")
            ->execute([$project_id]);
        return 'Priority feature review request rejected.';
    }

    if ($action === 'activate_sponsored') {
        $db->prepare("UPDATE projects SET is_sponsored = 1, sponsored_status = 'active', sponsored_starts_at = COALESCE(sponsored_starts_at, NOW()), sponsored_ends_at = COALESCE(sponsored_ends_at, DATE_ADD(NOW(), INTERVAL 30 DAY)), updated_at = NOW() WHERE id = ?")
            ->execute([$project_id]);
        return 'Sponsored placement activated.';
    }

    if ($action === 'reject_sponsored') {
        $db->prepare("UPDATE projects SET is_sponsored = 0, sponsored_status = 'rejected', updated_at = NOW() WHERE id = ?")
            ->execute([$project_id]);
        return 'Sponsored request rejected.';
    }

    if ($action === 'expire_sponsored') {
        $db->prepare("UPDATE projects SET is_sponsored = 0, sponsored_status = 'expired', sponsored_ends_at = COALESCE(sponsored_ends_at, NOW()), updated_at = NOW() WHERE id = ?")
            ->execute([$project_id]);
        return 'Sponsored placement expired.';
    }

    throw new InvalidArgumentException('Unsupported promotion admin action.');
}

function normalizeUserLevel($level) {
    $level = strtolower(trim((string) $level));

    if ($level === 'premium') {
        return 'pro';
    }

    if (in_array($level, ['beginner', 'pro', 'expert'], true)) {
        return $level;
    }

    return 'beginner';
}

function levelDisplayName($level) {
    return ucfirst(normalizeUserLevel($level));
}

function getLevelSystemDefinitions() {
    static $definitions = null;

    if ($definitions !== null) {
        return $definitions;
    }

    $definitions = [
        'beginner' => [
            'label' => 'Beginner',
            'score_bonus' => 0,
            'trust_weight' => 1.0,
            'approval_lane' => 'standard',
            'approval_label' => '24-48 hours',
            'bonus_accuracy_threshold' => 0,
            'promotion_approved_reviews' => 0,
            'promotion_valid_referrals' => 0,
            'promotion_accuracy' => 0,
            'promotion_completed_tasks' => 0,
            'promotion_account_age_days' => 0,
            'demotion_rejection_ratio' => 1.0,
            'referral_commission_percent' => REFERRAL_COMMISSION_PERCENT,
        ],
        'pro' => [
            'label' => 'Pro',
            'score_bonus' => 5,
            'trust_weight' => PRO_TRUST_WEIGHT,
            'approval_lane' => 'priority',
            'approval_label' => 'Priority ~12 hours',
            'bonus_accuracy_threshold' => 70,
            'promotion_approved_reviews' => 0,
            'promotion_valid_referrals' => PRO_MIN_VALID_REFERRALS,
            'promotion_accuracy' => 0,
            'promotion_completed_tasks' => PRO_MIN_COMPLETED_TASKS,
            'promotion_account_age_days' => PRO_MIN_ACCOUNT_AGE_DAYS,
            'demotion_rejection_ratio' => 0.35,
            'referral_commission_percent' => REFERRAL_COMMISSION_PERCENT,
        ],
        'expert' => [
            'label' => 'Expert',
            'score_bonus' => 10,
            'trust_weight' => EXPERT_TRUST_WEIGHT,
            'approval_lane' => 'auto',
            'approval_label' => 'Auto-approved, validated in background',
            'bonus_accuracy_threshold' => 85,
            'promotion_approved_reviews' => 100,
            'promotion_valid_referrals' => 10,
            'promotion_accuracy' => 85,
            'promotion_completed_tasks' => PRO_MIN_COMPLETED_TASKS,
            'promotion_account_age_days' => PRO_MIN_ACCOUNT_AGE_DAYS,
            'max_rejection_ratio' => 0.15,
            'demotion_rejection_ratio' => 0.20,
            'referral_commission_percent' => EXPERT_REFERRAL_COMMISSION_PERCENT,
        ],
    ];

    return $definitions;
}

function getLevelPolicy($level) {
    $level = normalizeUserLevel($level);
    $definitions = getLevelSystemDefinitions();

    return $definitions[$level] ?? $definitions['beginner'];
}

function levelPromotionCriteriaMet($target_level, array $stats) {
    $target_level = normalizeUserLevel($target_level);
    $policy = getLevelPolicy($target_level);

    if ($target_level === 'beginner') {
        return true;
    }

    if ($target_level === 'pro') {
        // PRO promotion requires ALL of the following:
        // 1. All 10 TaskHub mission days completed
        // 2. Account age >= 7 days
        // 3. At least 1 valid referral
        // 4. No suspicious security signals
        if (empty($stats['mission_completed'])) {
            return false;
        }

        if ((int) ($stats['account_age_days'] ?? 0) < (int) ($policy['promotion_account_age_days'] ?? 0)) {
            return false;
        }

        if ((int) ($stats['valid_referrals'] ?? 0) < (int) ($policy['promotion_valid_referrals'] ?? 0)) {
            return false;
        }

        $signals = getUserSecuritySignals((int) ($stats['user_id'] ?? 0));
        if (!empty($signals['is_suspicious'])) {
            return false;
        }
        return true;
    }

    if ((int) ($stats['approved_reviews'] ?? 0) < (int) ($policy['promotion_approved_reviews'] ?? 0)) {
        return false;
    }

    if ((int) ($stats['valid_referrals'] ?? 0) < (int) ($policy['promotion_valid_referrals'] ?? 0)) {
        return false;
    }

    if ((float) ($stats['accuracy'] ?? 0) < (float) ($policy['promotion_accuracy'] ?? 0)) {
        return false;
    }

    if (isset($policy['max_rejection_ratio']) && (float) ($stats['rejection_ratio'] ?? 0) > (float) $policy['max_rejection_ratio']) {
        return false;
    }

    return true;
}

/**
 * Get detailed promotion blockers for a target level.
 * Returns an array of user-friendly reason strings explaining why promotion is blocked.
 */
function getLevelPromotionBlockers($target_level, array $stats) {
    $target_level = normalizeUserLevel($target_level);
    $policy = getLevelPolicy($target_level);
    $blockers = [];

    if ($target_level === 'beginner') {
        return $blockers;
    }

    if ($target_level === 'pro') {
        if (empty($stats['mission_completed'])) {
            $blockers[] = 'Complete all 10 TaskHub days first.';
        }

        $age_days = (int) ($stats['account_age_days'] ?? 0);
        $min_age = (int) ($policy['promotion_account_age_days'] ?? 0);
        if ($age_days < $min_age) {
            $days_left = $min_age - $age_days;
            $blockers[] = "Account must be {$min_age} days old ({$days_left} more day" . ($days_left > 1 ? 's' : '') . " needed).";
        }

        $referrals = (int) ($stats['valid_referrals'] ?? 0);
        $min_refs = (int) ($policy['promotion_valid_referrals'] ?? 0);
        if ($referrals < $min_refs) {
            $refs_left = $min_refs - $referrals;
            $blockers[] = "Need {$min_refs} valid referral" . ($min_refs > 1 ? 's' : '') . " ({$refs_left} more needed).";
        }

        // Check security signals
        $signals = getUserSecuritySignals((int) ($stats['user_id'] ?? 0));
        if (!empty($signals['reasons'])) {
            foreach ($signals['reasons'] as $reason) {
                $blockers[] = 'Security: ' . $reason;
            }
        }

        return $blockers;
    }

    // Expert level blockers
    $reviews = (int) ($stats['approved_reviews'] ?? 0);
    $min_reviews = (int) ($policy['promotion_approved_reviews'] ?? 0);
    if ($reviews < $min_reviews) {
        $reviews_left = $min_reviews - $reviews;
        $blockers[] = "Need {$min_reviews} approved reviews ({$reviews_left} more needed).";
    }

    $referrals = (int) ($stats['valid_referrals'] ?? 0);
    $min_refs = (int) ($policy['promotion_valid_referrals'] ?? 0);
    if ($referrals < $min_refs) {
        $refs_left = $min_refs - $referrals;
        $blockers[] = "Need {$min_refs} valid referrals ({$refs_left} more needed).";
    }

    $accuracy = (float) ($stats['accuracy'] ?? 0);
    $min_accuracy = (float) ($policy['promotion_accuracy'] ?? 0);
    if ($accuracy < $min_accuracy) {
        $blockers[] = "Need {$min_accuracy}% review accuracy (currently {$accuracy}%).";
    }

    if (isset($policy['max_rejection_ratio'])) {
        $rejection_ratio = (float) ($stats['rejection_ratio'] ?? 0);
        if ($rejection_ratio > (float) $policy['max_rejection_ratio']) {
            $blockers[] = 'Rejection ratio too high for promotion.';
        }
    }

    return $blockers;
}

function resolveStoredUserLevel($current_level, array $stats) {
    $current_level = normalizeUserLevel($current_level);

    if ($current_level === 'expert' && (float) ($stats['rejection_ratio'] ?? 0) > (float) getLevelPolicy('expert')['demotion_rejection_ratio']) {
        return levelPromotionCriteriaMet('pro', $stats) ? 'pro' : 'beginner';
    }

    if ($current_level === 'pro' && (float) ($stats['rejection_ratio'] ?? 0) > (float) getLevelPolicy('pro')['demotion_rejection_ratio']) {
        return 'beginner';
    }

    if (levelPromotionCriteriaMet('expert', $stats)) {
        return 'expert';
    }

    if ($current_level === 'beginner' && levelPromotionCriteriaMet('pro', $stats)) {
        return 'pro';
    }

    return $current_level;
}

function isLevelBonusActive($level, array $stats) {
    $level = normalizeUserLevel($level);
    $policy = getLevelPolicy($level);
    $required_accuracy = (float) ($policy['bonus_accuracy_threshold'] ?? 0);

    if ($required_accuracy <= 0) {
        return true;
    }

    return (float) ($stats['accuracy'] ?? 0) >= $required_accuracy;
}

function syncUserReviewCounters($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $user_id = (int) $user_id;

    $stats = getUserReviewPerformanceStats($user_id, $db);
    $stmt = $db->prepare("
        UPDATE users
        SET total_reviews = ?,
            approved_reviews_count = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        (int) ($stats['total_reviews'] ?? 0),
        (int) ($stats['approved_reviews'] ?? 0),
        $user_id,
    ]);

    return $stats;
}

function syncUserLevelStatus($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);
    ensureEarlyAirdropSchema($db);

    $user_id = (int) $user_id;
    $current_user = getUserById($user_id);
    if (!$current_user) {
        return null;
    }

    $old_level = normalizeUserLevel((string) ($current_user['level'] ?? 'beginner'));

    $stats = syncUserReviewCounters($user_id, $db);

    // Add mission completion, account age, and referral stats for level promotion checks
    $stats['mission_completed'] = taskHubMissionCompleted($user_id, $db);
    $stats['account_age_days'] = (int) floor((time() - strtotime((string) ($current_user['created_at'] ?? 'now'))) / 86400);
    $stats['valid_referrals'] = (int) ($current_user['valid_referrals'] ?? 0);
    $stats['user_id'] = $user_id;

    $new_level = resolveStoredUserLevel($current_user['level'] ?? 'beginner', $stats);
    $is_expert = $new_level === 'expert' ? 1 : 0;
    $is_pro = $new_level === 'pro' ? 1 : 0;
    $set_expert_at = $is_expert === 1 && empty($current_user['expert_at']);

    $sql = "
        UPDATE users
        SET level = ?,
            is_expert = ?,
            is_premium = ?,
            updated_at = NOW(),
            expert_at = " . ($set_expert_at ? "NOW()" : ($is_expert === 0 ? "NULL" : "expert_at")) . "
        WHERE id = ?
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$new_level, $is_expert, $is_pro, $user_id]);

    // Early Adopter Airdrop: unlock whenever Pro/Expert and TaskHub completion are both true.
    if (in_array($new_level, ['pro', 'expert'], true)) {
        try {
            unlockPendingEarlyAirdropForUser($user_id, $db);
        } catch (Throwable $e) {
            // Log but don't block level sync
            error_log('Early airdrop unlock failed for user ' . $user_id . ': ' . $e->getMessage());
        }
    }

    if ((int) ($_SESSION['user_id'] ?? 0) === $user_id) {
        $_SESSION['level'] = $new_level;
    }

    return getUserLevelState(['id' => $user_id, 'level' => $new_level], $db, array_merge($stats, ['level' => $new_level]));
}

function calculateReviewFinalScore($base_score, $user_level_state, array $review_context = []) {
    $base_score = max(0, min(100, round((float) $base_score, 2)));
    $level_state = is_array($user_level_state) ? $user_level_state : getUserLevelState((int) $user_level_state);
    $level = normalizeUserLevel($level_state['level'] ?? 'beginner');
    $total_reviews = (int) ($review_context['user_total_reviews'] ?? $level_state['stats']['total_reviews'] ?? 0);

    $penalty_percent = 0;
    $score_after_penalty = $base_score;

    if ($level === 'beginner' && $total_reviews <= 1) {
        $penalty_percent = 5;
        $score_after_penalty = round($base_score * 0.95, 2);
    }

    $level_bonus = (int) ($level_state['score_bonus'] ?? 0);
    $final_score = min(100, round($score_after_penalty + $level_bonus, 2));

    return [
        'base_score' => $base_score,
        'score_after_penalty' => $score_after_penalty,
        'penalty_percent' => $penalty_percent,
        'level_bonus' => $level_bonus,
        'final_score' => $final_score,
        'bonus_active' => !empty($level_state['bonus_active']),
    ];
}

function shouldAutoApproveReview($user_or_level_state) {
    $level_state = is_array($user_or_level_state) ? $user_or_level_state : getUserLevelState($user_or_level_state);
    return normalizeUserLevel($level_state['level'] ?? 'beginner') === 'expert'
        && !empty($level_state['bonus_active']);
}

function userCanAccessClaimCenter($user_or_level_state) {
    if (defined('TESTING_MODE') && TESTING_MODE) {
        return true;
    }

    $level_state = is_array($user_or_level_state) ? $user_or_level_state : getUserLevelState($user_or_level_state);
    return in_array(normalizeUserLevel($level_state['level'] ?? 'beginner'), ['pro', 'expert'], true);
}

function syncProjectAggregateMetrics($project_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $project_id = (int) $project_id;

    if ($project_id <= 0) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT
            COUNT(r.id) AS total_reviews,
            COALESCE(AVG(r.rating), 0) AS avg_rating,
            COALESCE(
                SUM(
                    COALESCE(NULLIF(r.review_score, 0), r.rating * 20) *
                    CASE
                        WHEN LOWER(COALESCE(u.level, 'beginner')) = 'expert' THEN " . EXPERT_TRUST_WEIGHT . "
                        WHEN LOWER(COALESCE(u.level, 'beginner')) IN ('pro', 'premium') THEN " . PRO_TRUST_WEIGHT . "
                        ELSE 1
                    END
                ) /
                NULLIF(
                    SUM(
                        CASE
                            WHEN LOWER(COALESCE(u.level, 'beginner')) = 'expert' THEN " . EXPERT_TRUST_WEIGHT . "
                            WHEN LOWER(COALESCE(u.level, 'beginner')) IN ('pro', 'premium') THEN " . PRO_TRUST_WEIGHT . "
                            ELSE 1
                        END
                    ),
                    0
                ),
                0
            ) AS weighted_project_score
        FROM reviews r
        INNER JOIN users u ON u.id = r.user_id
        WHERE r.project_id = ?
          AND r.status = 'approved'
    ");
    $stmt->execute([$project_id]);
    $stats = $stmt->fetch() ?: [];

    $total_reviews = (int) ($stats['total_reviews'] ?? 0);
    $avg_rating = round((float) ($stats['avg_rating'] ?? 0), 2);
    $project_score = round((float) ($stats['weighted_project_score'] ?? 0), 2);
    $is_verified = $project_score >= PROJECT_VERIFICATION_SCORE_THRESHOLD ? 1 : 0;

    $sql = "
        UPDATE projects
        SET total_reviews = ?,
            avg_rating = ?,
            project_score = ?,
            is_verified = ?,
            updated_at = NOW(),
            verified_at = " . ($is_verified === 1 ? "COALESCE(verified_at, NOW())" : "NULL") . "
        WHERE id = ?
    ";
    $update = $db->prepare($sql);
    $update->execute([$total_reviews, $avg_rating, $project_score, $is_verified, $project_id]);

    $has_feature_status = tableHasColumn('projects', 'feature_status');
    $has_feature_requested_at = tableHasColumn('projects', 'feature_requested_at');
    $has_feature_reviewed_at = tableHasColumn('projects', 'feature_reviewed_at');
    $has_feature_reviewed_by = tableHasColumn('projects', 'feature_reviewed_by');
    $has_featured_at = tableHasColumn('projects', 'featured_at');

    if ($has_feature_status) {
        $project_stmt = $db->prepare("
            SELECT approval_status, feature_status
            FROM projects
            WHERE id = ?
            LIMIT 1
        ");
        $project_stmt->execute([$project_id]);
        $project_row = $project_stmt->fetch() ?: [];

        $approval_status = strtolower(trim((string) ($project_row['approval_status'] ?? 'pending')));
        $feature_status = strtolower(trim((string) ($project_row['feature_status'] ?? 'none')));
        $meets_feature_criteria = $approval_status === 'approved'
            && $avg_rating >= FEATURE_MIN_AVG_RATING
            && $total_reviews >= FEATURE_MIN_APPROVED_REVIEWS;

        if ($meets_feature_criteria && $feature_status === 'none') {
            $feature_sql = "
                UPDATE projects
                SET feature_status = 'eligible',
                    feature_queue_type = NULL,
                    " . ($has_feature_requested_at ? "feature_requested_at = COALESCE(feature_requested_at, NOW())," : '') . "
                    updated_at = NOW()
                WHERE id = ?
            ";
            $db->prepare($feature_sql)->execute([$project_id]);
        } elseif (!$meets_feature_criteria && in_array($feature_status, ['eligible', 'pending_review'], true)) {
            $reset_parts = ["feature_status = 'none'"];
            if (tableHasColumn('projects', 'feature_queue_type')) {
                $reset_parts[] = "feature_queue_type = NULL";
            }
            if ($has_feature_requested_at) {
                $reset_parts[] = "feature_requested_at = NULL";
            }
            if ($has_feature_reviewed_at) {
                $reset_parts[] = "feature_reviewed_at = NULL";
            }
            if ($has_feature_reviewed_by) {
                $reset_parts[] = "feature_reviewed_by = NULL";
            }
            if ($has_featured_at) {
                $reset_parts[] = "featured_at = NULL";
            }
            $reset_parts[] = "updated_at = NOW()";

            $feature_reset_sql = "
                UPDATE projects
                SET " . implode(",\n                    ", $reset_parts) . "
                WHERE id = ?
            ";
            $db->prepare($feature_reset_sql)->execute([$project_id]);
        }
    }

    return [
        'total_reviews' => $total_reviews,
        'avg_rating' => $avg_rating,
        'project_score' => $project_score,
        'is_verified' => $is_verified,
    ];
}

function userCanAccessExpertTools($user_or_level_state) {
    $level_state = is_array($user_or_level_state) ? $user_or_level_state : getUserLevelState($user_or_level_state);
    return normalizeUserLevel($level_state['level'] ?? 'beginner') === 'expert';
}

function toggleReviewLike($review_id, $user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    $review_id = (int) $review_id;
    $user_id = (int) $user_id;

    if ($user_id <= 0) {
        return ['success' => false, 'message' => 'Please sign in to mark helpful.'];
    }

    $lookup = $db->prepare("
        SELECT id
        FROM review_reactions
        WHERE review_id = ?
          AND user_id = ?
          AND reaction_type = 'like'
        LIMIT 1
    ");
    $lookup->execute([$review_id, $user_id]);
    $existing = $lookup->fetch();

    if ($existing) {
        $delete = $db->prepare("DELETE FROM review_reactions WHERE id = ?");
        $delete->execute([(int) $existing['id']]);

        $decrement = $db->prepare("UPDATE reviews SET helpful_count = GREATEST(helpful_count - 1, 0), updated_at = NOW() WHERE id = ?");
        $decrement->execute([$review_id]);

        return ['success' => true, 'liked' => false, 'message' => 'Helpful mark removed.'];
    }

    $insert = $db->prepare("
        INSERT INTO review_reactions (review_id, user_id, reaction_type)
        VALUES (?, ?, 'like')
    ");
    $insert->execute([$review_id, $user_id]);

    $increment = $db->prepare("UPDATE reviews SET helpful_count = helpful_count + 1, updated_at = NOW() WHERE id = ?");
    $increment->execute([$review_id]);

    return ['success' => true, 'liked' => true, 'message' => 'Marked as helpful.'];
}
