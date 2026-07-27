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

    ensureReviewCorrectionSchema($db);

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

function ensureReviewCorrectionSchema(PDO $db = null) {
    static $schema_ready = false;

    if ($schema_ready) {
        return;
    }

    $db = $db ?: getDBConnection();

    if (tableExists('reviews')) {
        if (!tableHasColumn('reviews', 'correction_count')) {
            $db->exec("ALTER TABLE reviews ADD COLUMN correction_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER auto_approved_by_level");
        }

        if (!tableHasColumn('reviews', 'correction_requested_at')) {
            $db->exec("ALTER TABLE reviews ADD COLUMN correction_requested_at DATETIME NULL AFTER correction_count");
        }

        if (!tableHasColumn('reviews', 'correction_note')) {
            $db->exec("ALTER TABLE reviews ADD COLUMN correction_note TEXT NULL AFTER correction_requested_at");
        }
    }

    $schema_ready = true;
}

function ensureRexRankSchema(PDO $db = null) {
    static $schema_ready = false;

    if ($schema_ready) {
        return;
    }

    $db = $db ?: getDBConnection();

    if (tableExists('users')) {
        if (!tableHasColumn('users', 'rexrank_balance')) {
            $db->exec("ALTER TABLE users ADD COLUMN rexrank_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER total_rex_earned");
        }
        if (!tableHasColumn('users', 'rexrank_total_earned')) {
            $db->exec("ALTER TABLE users ADD COLUMN rexrank_total_earned DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER rexrank_balance");
        }
        if (!tableHasColumn('users', 'rexrank_converted_total')) {
            $db->exec("ALTER TABLE users ADD COLUMN rexrank_converted_total DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER rexrank_total_earned");
        }
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS rexrank_ledger (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            amount DECIMAL(18,2) NOT NULL,
            balance_after DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            reference_type VARCHAR(40) NULL,
            reference_id VARCHAR(100) NULL,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_rexrank_user_created (user_id, created_at),
            KEY idx_rexrank_action (action_type),
            KEY idx_rexrank_reference (reference_type, reference_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS review_priority_slots (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            review_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            slot_group VARCHAR(20) NOT NULL,
            rexrank_cost DECIMAL(18,2) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_review_priority_active (status, expires_at, slot_group),
            KEY idx_review_priority_review (review_id),
            KEY idx_review_priority_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS review_comments (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            review_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            comment_text TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'visible',
            like_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_review_comment_user (review_id, user_id),
            KEY idx_review_comments_review_status (review_id, status),
            KEY idx_review_comments_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS review_comment_likes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_review_comment_like_user (comment_id, user_id),
            KEY idx_review_comment_like_comment (comment_id),
            KEY idx_review_comment_like_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    ensureReviewInsightSchema($db);

    $schema_ready = true;
}

function ensureReviewInsightSchema(PDO $db = null) {
    static $schema_ready = false;

    if ($schema_ready) {
        return;
    }

    $db = $db ?: getDBConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS review_insights (
            review_id INT UNSIGNED NOT NULL,
            impression_count INT UNSIGNED NOT NULL DEFAULT 0,
            read_full_click_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_impression_at DATETIME NULL,
            last_read_full_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (review_id),
            KEY idx_review_insights_impressions (impression_count),
            KEY idx_review_insights_reads (read_full_click_count)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $schema_ready = true;
}

function recordReviewInsightEvent($review_ids, $event_type, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureReviewInsightSchema($db);

    $event_type = strtolower(trim((string) $event_type));
    $ids = is_array($review_ids) ? $review_ids : [$review_ids];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
        return $id > 0;
    })));

    if (empty($ids) || !in_array($event_type, ['impression', 'read_full'], true)) {
        return ['success' => false, 'message' => 'Nothing to track.'];
    }

    $review_placeholders = implode(',', array_fill(0, count($ids), '?'));
    $approved = $db->prepare("SELECT id FROM reviews WHERE id IN ({$review_placeholders}) AND status = 'approved'");
    $approved->execute($ids);
    $approved_ids = array_map('intval', $approved->fetchAll(PDO::FETCH_COLUMN));

    if (empty($approved_ids)) {
        return ['success' => false, 'message' => 'No approved reviews to track.'];
    }

    $sql = $event_type === 'read_full'
        ? "INSERT INTO review_insights (review_id, read_full_click_count, last_read_full_at)
           VALUES (?, 1, NOW())
           ON DUPLICATE KEY UPDATE read_full_click_count = read_full_click_count + 1, last_read_full_at = NOW()"
        : "INSERT INTO review_insights (review_id, impression_count, last_impression_at)
           VALUES (?, 1, NOW())
           ON DUPLICATE KEY UPDATE impression_count = impression_count + 1, last_impression_at = NOW()";

    $stmt = $db->prepare($sql);
    foreach ($approved_ids as $review_id) {
        $stmt->execute([$review_id]);
    }

    return ['success' => true, 'tracked' => count($approved_ids)];
}

function getRexRankSlotCosts() {
    return [
        'top1' => ['label' => 'Top 1', 'cost' => 50, 'rank' => 1],
        'top3' => ['label' => 'Top 2-3', 'cost' => 30, 'rank' => 2],
        'top5' => ['label' => 'Top 4-5', 'cost' => 20, 'rank' => 4],
        'top10' => ['label' => 'Top 6-10', 'cost' => 10, 'rank' => 6],
    ];
}

function getUserRexRankStats($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRexRankSchema($db);

    $stmt = $db->prepare("SELECT rexrank_balance, rexrank_total_earned, rexrank_converted_total FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $user_id]);
    $row = $stmt->fetch() ?: [];
    $balance = (float) ($row['rexrank_balance'] ?? 0);
    $earned = (float) ($row['rexrank_total_earned'] ?? 0);
    $converted = (float) ($row['rexrank_converted_total'] ?? 0);
    $remaining_convertible = max(0, floor(($earned * 0.5) - $converted));

    $daily_stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM rexrank_ledger
        WHERE user_id = ?
          AND action_type = 'voter_vote_spend'
          AND created_at >= CURDATE()
    ");
    $daily_stmt->execute([(int) $user_id]);

    return [
        'balance' => $balance,
        'total_earned' => $earned,
        'converted_total' => $converted,
        'convertible_rr' => min($balance, $remaining_convertible),
        'daily_votes' => (int) ($daily_stmt->fetch()['total'] ?? 0),
        'daily_vote_limit' => 10,
    ];
}

function addRexRankLedgerEntry($user_id, $amount, $action_type, $reference_type = null, $reference_id = null, $note = null, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRexRankSchema($db);

    $user_id = (int) $user_id;
    $amount = round((float) $amount, 2);
    $action_type = substr(trim((string) $action_type), 0, 50);
    $reference_type = $reference_type !== null ? substr(trim((string) $reference_type), 0, 40) : null;
    $reference_id = $reference_id !== null ? substr(trim((string) $reference_id), 0, 100) : null;
    $note = $note !== null ? substr(trim((string) $note), 0, 255) : null;

    if ($user_id <= 0 || $amount == 0.0 || $action_type === '') {
        throw new InvalidArgumentException('Invalid RexRank ledger entry.');
    }

    $balance_stmt = $db->prepare("SELECT rexrank_balance FROM users WHERE id = ? FOR UPDATE");
    $balance_stmt->execute([$user_id]);
    $current_balance = (float) (($balance_stmt->fetch()['rexrank_balance'] ?? 0));
    $new_balance = round($current_balance + $amount, 2);
    if ($new_balance < 0) {
        throw new RuntimeException('Not enough RexRank.');
    }

    $update_sql = "UPDATE users SET rexrank_balance = ?, updated_at = NOW()";
    $params = [$new_balance];
    if ($amount > 0) {
        $update_sql .= ", rexrank_total_earned = rexrank_total_earned + ?";
        $params[] = $amount;
    }
    $update_sql .= " WHERE id = ?";
    $params[] = $user_id;
    $db->prepare($update_sql)->execute($params);

    $insert = $db->prepare("
        INSERT INTO rexrank_ledger (user_id, action_type, amount, balance_after, reference_type, reference_id, note)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([$user_id, $action_type, $amount, $new_balance, $reference_type, $reference_id, $note]);

    return $new_balance;
}

function castRexRankExperienceVote($review_id, $voter_user_id, $vote_type, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRexRankSchema($db);
    ensureRewardClaimSchema($db);

    $review_id = (int) $review_id;
    $voter_user_id = (int) $voter_user_id;
    $vote_type = strtolower(trim((string) $vote_type));
    $allowed_votes = ['same_experience', 'different_experience'];
    if (!in_array($vote_type, $allowed_votes, true)) {
        return ['success' => false, 'message' => 'Choose a valid vote.'];
    }
    if ($voter_user_id <= 0) {
        return ['success' => false, 'message' => 'Please sign in to vote.'];
    }

    $voter = getUserById($voter_user_id);
    if (!$voter || !in_array(normalizeUserLevel($voter['level'] ?? 'beginner'), ['pro', 'expert'], true)) {
        return ['success' => false, 'message' => 'Experience voting unlocks at Pro.'];
    }

    $review_stmt = $db->prepare("SELECT id, user_id, status FROM reviews WHERE id = ? LIMIT 1");
    $review_stmt->execute([$review_id]);
    $review = $review_stmt->fetch();
    if (!$review || strtolower((string) ($review['status'] ?? '')) !== 'approved') {
        return ['success' => false, 'message' => 'Only approved reviews can receive votes.'];
    }
    $author_user_id = (int) ($review['user_id'] ?? 0);
    if ($author_user_id === $voter_user_id) {
        return ['success' => false, 'message' => 'You cannot vote on your own review.'];
    }

    $existing = $db->prepare("
        SELECT id
        FROM review_reactions
        WHERE review_id = ?
          AND user_id = ?
          AND reaction_type IN ('same_experience', 'different_experience')
        LIMIT 1
    ");
    $existing->execute([$review_id, $voter_user_id]);
    if ($existing->fetch()) {
        return ['success' => false, 'message' => 'You already voted on this review.'];
    }

    $stats = getUserRexRankStats($voter_user_id, $db);
    if ((float) $stats['balance'] < 10) {
        return ['success' => false, 'message' => 'You need 10RR to vote.'];
    }
    if ((int) $stats['daily_votes'] >= 10) {
        return ['success' => false, 'message' => 'Daily vote limit reached.'];
    }

    try {
        $db->beginTransaction();
        addRexRankLedgerEntry($voter_user_id, -10, 'voter_vote_spend', 'review', (string) $review_id, $vote_type, $db);
        addRexRankLedgerEntry($author_user_id, 10, 'review_vote_earned', 'review', (string) $review_id, $vote_type, $db);

        $insert = $db->prepare("INSERT INTO review_reactions (review_id, user_id, reaction_type) VALUES (?, ?, ?)");
        $insert->execute([$review_id, $voter_user_id, $vote_type]);

        $db->prepare("UPDATE reviews SET helpful_count = helpful_count + 1, updated_at = NOW() WHERE id = ?")->execute([$review_id]);

        addRewardLedgerEntry(
            $voter_user_id,
            1,
            'bonus',
            'rexrank_vote_reward',
            'available',
            'rexrank_vote:' . $review_id . ':' . $voter_user_id,
            $db
        );

        $db->commit();
        return ['success' => true, 'message' => 'Vote added. +1 $REX earned.', 'vote_type' => $vote_type];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function castReviewUpReward($review_id, $user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRexRankSchema($db);

    $review_id = (int) $review_id;
    $user_id = (int) $user_id;
    if ($review_id <= 0 || $user_id <= 0) {
        return ['success' => false, 'message' => 'Please sign in to up this review.'];
    }

    $review_stmt = $db->prepare("SELECT id, user_id, status FROM reviews WHERE id = ? LIMIT 1");
    $review_stmt->execute([$review_id]);
    $review = $review_stmt->fetch();
    if (!$review || strtolower((string) ($review['status'] ?? '')) !== 'approved') {
        return ['success' => false, 'message' => 'Only approved reviews can be upped.'];
    }
    if ((int) ($review['user_id'] ?? 0) === $user_id) {
        return ['success' => false, 'message' => 'You cannot up your own review.'];
    }

    $existing = $db->prepare("SELECT id FROM review_reactions WHERE review_id = ? AND user_id = ? AND reaction_type = 'up' LIMIT 1");
    $existing->execute([$review_id, $user_id]);
    if ($existing->fetch()) {
        return ['success' => false, 'message' => 'You already upped this review.'];
    }

    try {
        $db->beginTransaction();
        $insert = $db->prepare("INSERT INTO review_reactions (review_id, user_id, reaction_type) VALUES (?, ?, 'up')");
        $insert->execute([$review_id, $user_id]);
        addRexRankLedgerEntry($user_id, 1, 'review_up_reward', 'review', (string) $review_id, 'Up reward', $db);
        $db->commit();
        return ['success' => true, 'message' => '+1RR earned.'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function submitReviewComment($review_id, $user_id, $comment_text, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRexRankSchema($db);

    $review_id = (int) $review_id;
    $user_id = (int) $user_id;
    $comment_text = trim((string) $comment_text);
    if ($review_id <= 0 || $user_id <= 0) {
        return ['success' => false, 'message' => 'Please sign in to comment.'];
    }
    if ($comment_text === '' || mb_strlen($comment_text) > 500) {
        return ['success' => false, 'message' => 'Comment must be 1-500 characters.'];
    }

    $review_stmt = $db->prepare("SELECT id, status FROM reviews WHERE id = ? LIMIT 1");
    $review_stmt->execute([$review_id]);
    $review = $review_stmt->fetch();
    if (!$review || strtolower((string) ($review['status'] ?? '')) !== 'approved') {
        return ['success' => false, 'message' => 'Only approved reviews can receive comments.'];
    }

    try {
        $insert = $db->prepare("
            INSERT INTO review_comments (review_id, user_id, comment_text, status)
            VALUES (?, ?, ?, 'visible')
        ");
        $insert->execute([$review_id, $user_id, $comment_text]);
        return ['success' => true, 'message' => 'Comment added.'];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1062') !== false || stripos($e->getMessage(), 'Duplicate') !== false) {
            return ['success' => false, 'message' => 'You already commented on this review.'];
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function likeReviewCommentByReviewer($comment_id, $reviewer_user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRexRankSchema($db);

    $comment_id = (int) $comment_id;
    $reviewer_user_id = (int) $reviewer_user_id;
    if ($comment_id <= 0 || $reviewer_user_id <= 0) {
        return ['success' => false, 'message' => 'Please sign in to like comments.'];
    }

    $stmt = $db->prepare("
        SELECT c.id, c.user_id AS commenter_id, c.review_id, r.user_id AS reviewer_id, r.status
        FROM review_comments c
        INNER JOIN reviews r ON r.id = c.review_id
        WHERE c.id = ?
          AND c.status = 'visible'
        LIMIT 1
    ");
    $stmt->execute([$comment_id]);
    $comment = $stmt->fetch();
    if (!$comment || (int) ($comment['reviewer_id'] ?? 0) !== $reviewer_user_id) {
        return ['success' => false, 'message' => 'Only the review author can like this comment.'];
    }
    if ((int) ($comment['commenter_id'] ?? 0) === $reviewer_user_id) {
        return ['success' => false, 'message' => 'You cannot reward your own comment.'];
    }

    try {
        $db->beginTransaction();
        $insert = $db->prepare("INSERT INTO review_comment_likes (comment_id, user_id) VALUES (?, ?)");
        $insert->execute([$comment_id, $reviewer_user_id]);
        $db->prepare("UPDATE review_comments SET like_count = like_count + 1, updated_at = NOW() WHERE id = ?")->execute([$comment_id]);
        addRexRankLedgerEntry((int) $comment['commenter_id'], 1, 'comment_reviewer_like', 'review_comment', (string) $comment_id, 'Reviewer liked comment', $db);
        $db->commit();
        return ['success' => true, 'message' => 'Comment liked. Commenter earned +1RR.'];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if (strpos($e->getMessage(), '1062') !== false || stripos($e->getMessage(), 'Duplicate') !== false) {
            return ['success' => false, 'message' => 'Comment already liked.'];
        }
        return ['success' => false, 'message' => $e->getMessage()];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function convertRexRankToRex($user_id, $amount_rr, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRexRankSchema($db);
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    $amount_rr = floor((float) $amount_rr);
    if ($user_id <= 0 || $amount_rr < 10) {
        return ['success' => false, 'message' => 'Minimum conversion is 10RR.'];
    }

    $stats = getUserRexRankStats($user_id, $db);
    if ($amount_rr > (float) $stats['convertible_rr']) {
        return ['success' => false, 'message' => 'Amount exceeds your convertible RexRank.'];
    }
    $rex_amount = round($amount_rr / 10, 8);

    try {
        $db->beginTransaction();
        addRexRankLedgerEntry($user_id, -$amount_rr, 'rexrank_conversion_debit', 'conversion', 'rexrank_to_rex', 'Converted to $REX', $db);
        $db->prepare("UPDATE users SET rexrank_converted_total = rexrank_converted_total + ?, updated_at = NOW() WHERE id = ?")->execute([$amount_rr, $user_id]);
        addRewardLedgerEntry($user_id, $rex_amount, 'bonus', 'rexrank_conversion', 'available', 'rexrank_conversion:' . time() . ':' . $user_id, $db);
        $db->commit();
        return ['success' => true, 'message' => 'RexRank converted to $REX.', 'rex_amount' => $rex_amount];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function purchaseReviewPrioritySlot($review_id, $user_id, $slot_group, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRexRankSchema($db);

    $review_id = (int) $review_id;
    $user_id = (int) $user_id;
    $slot_group = strtolower(trim((string) $slot_group));
    $costs = getRexRankSlotCosts();
    if (!isset($costs[$slot_group])) {
        return ['success' => false, 'message' => 'Choose a valid priority slot.'];
    }

    $review_stmt = $db->prepare("SELECT id, user_id, status FROM reviews WHERE id = ? AND user_id = ? LIMIT 1");
    $review_stmt->execute([$review_id, $user_id]);
    $review = $review_stmt->fetch();
    if (!$review || strtolower((string) ($review['status'] ?? '')) !== 'approved') {
        return ['success' => false, 'message' => 'Only your approved reviews can be boosted.'];
    }

    $cost = (float) $costs[$slot_group]['cost'];
    if ((float) getUserRexRankStats($user_id, $db)['balance'] < $cost) {
        return ['success' => false, 'message' => 'Not enough RexRank for this slot.'];
    }

    try {
        $db->beginTransaction();
        addRexRankLedgerEntry($user_id, -$cost, 'priority_slot_spend', 'review', (string) $review_id, $slot_group, $db);
        $db->prepare("
            INSERT INTO review_priority_slots (review_id, user_id, slot_group, rexrank_cost, status, starts_at, expires_at)
            VALUES (?, ?, ?, ?, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))
        ")->execute([$review_id, $user_id, $slot_group, $cost]);
        $db->commit();
        return ['success' => true, 'message' => $costs[$slot_group]['label'] . ' priority active for 7 days.'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
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
            'promotion_valid_referrals' => 0,
            'promotion_accuracy' => 0,
            'promotion_completed_tasks' => PRO_MIN_COMPLETED_TASKS,
            'promotion_account_age_days' => 0,
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
        return !empty($stats['mission_completed']);
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
