<?php
/** Auto-split from legacy functions.php */

function normalizeReferralCode($code) {
    return strtoupper(trim((string)$code));
}

function buildReferralLink($referral_code) {
    $code = normalizeReferralCode($referral_code);
    return BASE_URL . '/auth.php?ref=' . rawurlencode($code);
}

function generateReferralCode($length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $code;
}

function generateUniqueReferralCode(PDO $db = null, $max_attempts = 10) {
    $db = $db ?: getDBConnection();
    $max_attempts = max(1, (int) $max_attempts);

    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
        $code = generateReferralCode();
        $stmt = $db->prepare("SELECT id FROM users WHERE referral_code = ? LIMIT 1");
        $stmt->execute([$code]);

        if (!$stmt->fetch()) {
            return $code;
        }
    }

    throw new RuntimeException('Could not generate a unique referral code. Please try again.');
}

function validateReferralCode($code) {
    $code = normalizeReferralCode($code);

    if ($code === '') {
        return [
            'valid' => true,
            'exists' => false,
            'message' => '',
            'code' => '',
        ];
    }

    if (!preg_match('/^[A-Z0-9]{6,16}$/', $code)) {
        return [
            'valid' => false,
            'exists' => false,
            'message' => 'Referral code format is invalid',
            'code' => $code,
        ];
    }

    $referrer = getUserByReferralCode($code);

    if (!$referrer) {
        return [
            'valid' => false,
            'exists' => false,
            'message' => 'Referral code not found',
            'code' => $code,
        ];
    }

    return [
        'valid' => true,
        'exists' => true,
        'message' => 'Referral code applied successfully',
        'code' => $code,
        'referrer' => $referrer,
    ];
}

function getReferralReviewStatusLabel($status) {
    $status = strtolower(trim((string) $status));
    $map = [
        'pending' => 'Pending',
        'qualified' => 'Valid',
        'flagged_manual_review' => 'Flagged Manual Review',
        'invalid' => 'Invalid',
    ];

    return $map[$status] ?? 'Pending';
}

function getReferralReviewStatusClass($status) {
    $status = strtolower(trim((string) $status));
    if ($status === 'qualified') {
        return 'status-approved';
    }
    if ($status === 'flagged_manual_review') {
        return 'status-flagged';
    }
    if ($status === 'invalid') {
        return 'status-rejected';
    }
    return 'status-pending';
}

function getCompletedTaskHubDaysCount($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $completed_days = 0;
    for ($day = 1; $day <= (int) TASKHUB_TOTAL_DAYS; $day++) {
        $day_info = taskHubGetDayCompletionInfo((int) $user_id, $day, $db);
        if (!empty($day_info['all_completed'])) {
            $completed_days++;
        }
    }

    return $completed_days;
}

function canReferralBecomeValid($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    return getCompletedTaskHubDaysCount((int) $user_id, $db) >= 4;
}

function calculateReferralCommissionAmount($referrer_level, $reward_amount) {
    $rate = (int) (getLevelPolicy($referrer_level)['referral_commission_percent'] ?? REFERRAL_COMMISSION_PERCENT);
    $reward_amount = max(0, (float) $reward_amount);

    if ($reward_amount <= 0 || $rate <= 0) {
        return 0.0;
    }

    return round(($reward_amount * $rate) / 100, 2);
}

function evaluateReferralAbuseRisk($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $stmt = $db->prepare("SELECT id, referred_by, signup_ip, last_ip, user_agent, security_flagged, security_flag_reason FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int) $user_id]);
    $user = $stmt->fetch();

    if (!$user || (int) ($user['referred_by'] ?? 0) <= 0) {
        return [
            'is_suspicious' => false,
            'same_ip' => false,
            'same_fingerprint' => false,
            'same_behavior_pattern' => false,
            'reason' => '',
        ];
    }

    $referrer_id = (int) $user['referred_by'];
    $referrer_stmt = $db->prepare("SELECT id, signup_ip, last_ip, user_agent FROM users WHERE id = ? LIMIT 1");
    $referrer_stmt->execute([$referrer_id]);
    $referrer = $referrer_stmt->fetch();

    $user_ips = array_values(array_unique(array_filter([
        trim((string) ($user['signup_ip'] ?? '')),
        trim((string) ($user['last_ip'] ?? '')),
    ])));
    $referrer_ips = array_values(array_unique(array_filter([
        trim((string) ($referrer['signup_ip'] ?? '')),
        trim((string) ($referrer['last_ip'] ?? '')),
    ])));
    $same_ip = !empty(array_intersect($user_ips, $referrer_ips));

    $same_fingerprint = false;
    if (tableExists('user_security_signals')) {
        $fp_stmt = $db->prepare("
            SELECT COUNT(*) AS total
            FROM user_security_signals child_sig
            INNER JOIN user_security_signals parent_sig
                ON parent_sig.user_id = ?
               AND child_sig.user_id = ?
               AND parent_sig.fingerprint_hash IS NOT NULL
               AND child_sig.fingerprint_hash IS NOT NULL
               AND parent_sig.fingerprint_hash = child_sig.fingerprint_hash
        ");
        $fp_stmt->execute([$referrer_id, (int) $user_id]);
        $same_fingerprint = ((int) ($fp_stmt->fetch()['total'] ?? 0)) > 0;
    }

    $same_behavior_pattern = false;
    if (tableExists('user_security_signals')) {
        $ua_stmt = $db->prepare("
            SELECT COUNT(*) AS total
            FROM user_security_signals child_sig
            INNER JOIN user_security_signals parent_sig
                ON parent_sig.user_id = ?
               AND child_sig.user_id = ?
               AND parent_sig.user_agent_hash IS NOT NULL
               AND child_sig.user_agent_hash IS NOT NULL
               AND parent_sig.user_agent_hash = child_sig.user_agent_hash
        ");
        $ua_stmt->execute([$referrer_id, (int) $user_id]);
        $same_behavior_pattern = ((int) ($ua_stmt->fetch()['total'] ?? 0)) > 0;
    }

    if (!$same_behavior_pattern) {
        $same_behavior_pattern = trim((string) ($user['user_agent'] ?? '')) !== ''
            && trim((string) ($user['user_agent'] ?? '')) === trim((string) ($referrer['user_agent'] ?? ''));
    }

    // Build reasons list
    $reasons = [];
    if ($same_ip) {
        $reasons[] = 'same IP';
    }
    if ($same_fingerprint) {
        $reasons[] = 'same device fingerprint';
    }
    if ($same_behavior_pattern) {
        $reasons[] = 'same behavior pattern';
    }

    // Determine suspiciousness with looser criteria:
    // 1) same_ip + same_behavior_pattern (2 conditions, no fingerprint required)
    // 2) same_ip + 0 completed TaskHub days (fresh fake account)
    $taskhub_days = getCompletedTaskHubDaysCount((int) $user_id, $db);
    $is_suspicious = ($same_ip && $same_behavior_pattern) || ($same_ip && $taskhub_days === 0);

    return [
        'is_suspicious' => $is_suspicious,
        'same_ip' => $same_ip,
        'same_fingerprint' => $same_fingerprint,
        'same_behavior_pattern' => $same_behavior_pattern,
        'reason' => $is_suspicious ? implode(' + ', $reasons) : '',
    ];
}

function updateReferralReviewState($user_id, $status, $qualified_at = null, $flag_reason = null, $reviewed_by = null, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);
    ensureRewardClaimSchema($db);

    $status = strtolower(trim((string) $status));
    if (!in_array($status, ['pending', 'qualified', 'flagged_manual_review', 'invalid'], true)) {
        $status = 'pending';
    }

    $stmt = $db->prepare("
        UPDATE users
        SET referral_review_status = ?,
            referral_qualified_at = ?,
            referral_flag_reason = ?,
            referral_reviewed_at = CASE WHEN ? IS NULL THEN NULL ELSE NOW() END,
            referral_reviewed_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $status,
        $qualified_at,
        $flag_reason,
        $reviewed_by,
        $reviewed_by,
        (int) $user_id,
    ]);

    return true;
}

function applyReferralDecision($user_id, $decision, $reviewed_by = null, $flag_reason = null, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);
    ensureRewardClaimSchema($db);

    $decision = strtolower(trim((string) $decision));
    if (!in_array($decision, ['qualify', 'invalidate', 'flag_manual_review', 'reset_pending'], true)) {
        throw new InvalidArgumentException('Invalid referral decision.');
    }

    $owns_transaction = !$db->inTransaction();
    if ($owns_transaction) {
        $db->beginTransaction();
    }

    try {
        $stmt = $db->prepare("SELECT id, referred_by, referral_qualified_at, referral_review_status FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([(int) $user_id]);
        $referral_user = $stmt->fetch();

        if (!$referral_user || (int) ($referral_user['referred_by'] ?? 0) <= 0) {
            throw new RuntimeException('This user does not have a tracked referrer.');
        }

        $referrer_id = (int) $referral_user['referred_by'];
        $was_qualified = !empty($referral_user['referral_qualified_at']) || (string) ($referral_user['referral_review_status'] ?? '') === 'qualified';
        $current_status = strtolower(trim((string) ($referral_user['referral_review_status'] ?? 'pending')));

        if ($decision === 'qualify') {
            // NO bypass: ALL qualify attempts require 4 TaskHub days, regardless of current status
            if (!$was_qualified) {
                if (!canReferralBecomeValid((int) $user_id, $db)) {
                    throw new RuntimeException('User must complete 4 TaskHub days before qualification.');
                }
            }
            if (!$was_qualified) {
                updateReferralReviewState((int) $user_id, 'qualified', date('Y-m-d H:i:s'), null, $reviewed_by, $db);
                $db->prepare("UPDATE users SET valid_referrals = valid_referrals + 1, updated_at = NOW() WHERE id = ?")->execute([$referrer_id]);
            }
        } elseif ($decision === 'invalidate') {
            updateReferralReviewState((int) $user_id, 'invalid', null, $flag_reason, $reviewed_by, $db);
            if ($was_qualified) {
                $db->prepare("UPDATE users SET valid_referrals = GREATEST(valid_referrals - 1, 0), updated_at = NOW() WHERE id = ?")->execute([$referrer_id]);
            }
        } elseif ($decision === 'flag_manual_review') {
            updateReferralReviewState((int) $user_id, 'flagged_manual_review', null, $flag_reason, $reviewed_by, $db);
            if ($was_qualified) {
                $db->prepare("UPDATE users SET valid_referrals = GREATEST(valid_referrals - 1, 0), updated_at = NOW() WHERE id = ?")->execute([$referrer_id]);
            }
        } else {
            updateReferralReviewState((int) $user_id, 'pending', null, null, $reviewed_by, $db);
            if ($was_qualified) {
                $db->prepare("UPDATE users SET valid_referrals = GREATEST(valid_referrals - 1, 0), updated_at = NOW() WHERE id = ?")->execute([$referrer_id]);
            }
        }

        if ($owns_transaction) {
            $db->commit();
        }
        return true;
    } catch (Throwable $e) {
        if ($owns_transaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function maybeActivateReferralQualification($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);
    ensureRewardClaimSchema($db);
    ensureEarlyAirdropSchema($db);

    $stats = getUserReviewPerformanceStats($user_id, $db);
    if ((int) ($stats['referred_by'] ?? 0) <= 0) {
        return false;
    }

    $current_status = strtolower(trim((string) ($stats['referral_review_status'] ?? 'pending')));
    if (in_array($current_status, ['qualified', 'invalid', 'flagged_manual_review'], true)) {
        return false;
    }

    $user = getUserById((int) $user_id);
    if (!$user) {
        return false;
    }

    $qualifies = canReferralBecomeValid((int) $user_id, $db);

    if (!$qualifies) {
        return false;
    }

    $risk = evaluateReferralAbuseRisk((int) $user_id, $db);
    if (!empty($risk['is_suspicious'])) {
        // Persist abuse detection flag in DB so it survives re-evaluation
        $db->prepare("UPDATE users SET referral_abuse_detected = 1, referral_abuse_reason = ?, updated_at = NOW() WHERE id = ?")
            ->execute([(string) ($risk['reason'] ?? 'Referral abuse pattern detected.'), (int) $user_id]);
        applyReferralDecision((int) $user_id, 'flag_manual_review', null, (string) ($risk['reason'] ?? 'Referral abuse pattern detected.'), $db);
        return 'flagged_manual_review';
    }

    applyReferralDecision((int) $user_id, 'qualify', null, null, $db);

    // Early Adopter Airdrop: Award 50 REX to referrer from pool
    $referrer_id = (int) ($user['referred_by'] ?? 0);
    if ($referrer_id > 0 && isEarlyAirdropActive($db)) {
        $bonus_amount = (float) EARLY_AIRDROP_REFERRAL_BONUS;
        $reference_id = 'early_airdrop:referral:' . (int) $user_id;
        if (deductEarlyAirdropPool($referrer_id, 'referral_bonus', $bonus_amount, $db, $reference_id)) {
            addRewardLedgerEntry(
                $referrer_id,
                $bonus_amount,
                'bonus',
                'early_adopter_referral',
                'available',
                $reference_id,
                $db,
                'phase1',
                'beginner'
            );
        }
    }

    return true;
}

function creditReferralCommission($referred_user_id, $reward_amount, $origin, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    $referred_user_id = (int) $referred_user_id;
    $reward_amount = (float) $reward_amount;
    $origin = strtolower(trim((string) $origin));
    $allowed_origins = ['review', 'taskhub', 'boosthub'];
    if ($referred_user_id <= 0 || $reward_amount <= 0 || !in_array($origin, $allowed_origins, true)) {
        return 0.0;
    }

    maybeActivateReferralQualification($referred_user_id, $db);

    $stmt = $db->prepare("
        SELECT referred_by, referral_qualified_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$referred_user_id]);
    $referral = $stmt->fetch();

    if (!$referral || (int) ($referral['referred_by'] ?? 0) <= 0 || empty($referral['referral_qualified_at'])) {
        return 0.0;
    }

    $referrer_id = (int) $referral['referred_by'];
    $referrer_state = syncUserLevelStatus($referrer_id, $db);
    $commission = calculateReferralCommissionAmount($referrer_state['level'] ?? 'beginner', $reward_amount);

    if ($commission <= 0) {
        return 0.0;
    }

    $update = $db->prepare("
        UPDATE users
        SET referral_earnings = referral_earnings + ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$commission, $referrer_id]);
    addRewardLedgerEntry(
        $referrer_id,
        $commission,
        'referral',
        $origin . '_referral_commission',
        'available',
        'referral_' . $origin . ':' . $referred_user_id,
        $db,
        resolveRewardPhase('referral', $referrer_state['level'] ?? 'beginner'),
        $referrer_state['level'] ?? 'beginner'
    );

    return $commission;
}

function reverseReferralCommission($referred_user_id, $reward_amount, $origin, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    $referred_user_id = (int) $referred_user_id;
    $reward_amount = (float) $reward_amount;
    $origin = strtolower(trim((string) $origin));
    $allowed_origins = ['review', 'taskhub', 'boosthub'];
    if ($referred_user_id <= 0 || $reward_amount <= 0 || !in_array($origin, $allowed_origins, true)) {
        return 0.0;
    }

    $stmt = $db->prepare("SELECT referred_by, referral_qualified_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$referred_user_id]);
    $referral = $stmt->fetch();

    if (!$referral || (int) ($referral['referred_by'] ?? 0) <= 0 || empty($referral['referral_qualified_at'])) {
        return 0.0;
    }

    $referrer_id = (int) $referral['referred_by'];
    $referrer_state = getUserLevelState($referrer_id, $db);
    $commission = calculateReferralCommissionAmount($referrer_state['level'] ?? 'beginner', $reward_amount);

    if ($commission <= 0) {
        return 0.0;
    }

    $update = $db->prepare("
        UPDATE users
        SET referral_earnings = GREATEST(referral_earnings - ?, 0),
            updated_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$commission, $referrer_id]);
    addRewardLedgerEntry(
        $referrer_id,
        -1 * $commission,
        'referral',
        $origin . '_referral_reversal',
        'available',
        'referral_' . $origin . '_reversal:' . $referred_user_id,
        $db,
        resolveRewardPhase('referral', $referrer_state['level'] ?? 'beginner'),
        $referrer_state['level'] ?? 'beginner'
    );

    return $commission;
}

function creditReferralCommissionForReview($review_user_id, $reward_amount, PDO $db = null) {
    return creditReferralCommission($review_user_id, $reward_amount, 'review', $db);
}

function reverseReferralCommissionForReview($review_user_id, $reward_amount, PDO $db = null) {
    return reverseReferralCommission($review_user_id, $reward_amount, 'review', $db);
}

function getUserReferralList($referrer_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        SELECT
            id, username, full_name, created_at,
            referral_review_status, referral_qualified_at,
            referral_flag_reason
        FROM users
        WHERE referred_by = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([(int) $referrer_id]);
    return $stmt->fetchAll();
}
