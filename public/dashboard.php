<?php
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireFeatureAccess('dashboard');

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

$db = getDBConnection();
ensureRewardClaimSchema($db);

$user = getCurrentUser();
$level_state = syncUserLevelStatus((int) $user['id'], $db) ?: getUserLevelState($user, $db);
$airdrop_unlock_state = unlockPendingEarlyAirdropForUser((int) $user['id'], $db);
$user = getUserById((int) $user['id']) ?: $user;
$user['level'] = $level_state['level'];
$level_progress = getUserLevelProgressData($level_state, $db);
$mini_task_stats = getUserMiniTaskStats((int) $user['id'], $db);
$dashboard_notice = consumeFlashMessage('dashboard_success');
$show_airdrop_feature = featureIsVisible('early_airdrop') && featureIsAccessible('early_airdrop');
$show_reviews_feature = featureIsVisible('reviews');
$show_projects_feature = featureIsVisible('projects');
$show_learnhub_feature = featureIsVisible('learnhub');
$show_boosthub_feature = featureIsVisible('boosthub');
$show_claim_feature = featureIsVisible('claim_center');

$balance = getRewardLedgerBalance((int) $user['id'], 'available', $db);
$claim_eligibility = getClaimEligibility((int) $user['id'], $db);

$stmt = $db->prepare("SELECT COUNT(*) AS total_votes FROM review_reactions WHERE user_id = ?");
$stmt->execute([(int) $user['id']]);
$user['total_votes'] = (int) (($stmt->fetch()['total_votes'] ?? 0));

$recent_reviews = [];
try {
    $stmt = $db->prepare("
        SELECT r.review_title, r.status, r.final_rex, r.created_at, p.name AS project_name
        FROM reviews r
        LEFT JOIN projects p ON p.id = r.project_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
        LIMIT 4
    ");
    $stmt->execute([(int) $user['id']]);
    $recent_reviews = $stmt->fetchAll();
} catch (Throwable $e) {
    $recent_reviews = [];
}

$profile_name = trim((string) ($user['full_name'] ?: $user['username'] ?: 'User'));
$avatar_initial = strtoupper(substr((string) ($user['username'] ?: $profile_name), 0, 1));
$user_avatar_url = coinrexNormalizeMediaUrl((string) ($user['avatar'] ?? ''));
$level_icon = 'fas fa-seedling';
if (($user['level'] ?? '') === 'pro') {
    $level_icon = 'fas fa-gem';
} elseif (($user['level'] ?? '') === 'expert') {
    $level_icon = 'fas fa-crown';
}

$current_level = normalizeUserLevel((string) ($user['level'] ?? 'beginner'));
$next_level = $level_progress['next_level'] ?? null;
$level_definitions = getLevelSystemDefinitions();
$next_policy = $next_level && isset($level_definitions[$next_level]) ? $level_definitions[$next_level] : null;
$remaining_requirements = [];
$next_goal_text = '';
if ($next_policy) {
    $current_tasks = (int) ($mini_task_stats['completed_total'] ?? 0);
    $current_referrals = (int) ($user['valid_referrals'] ?? 0);
    $current_reviews = (int) ($level_state['stats']['approved_reviews'] ?? 0);
    $current_accuracy = (float) ($level_state['accuracy'] ?? 0);
    $mission_completed = !empty($level_state['stats']['mission_completed']);

    $task_left = max(0, (int) ($next_policy['promotion_completed_tasks'] ?? 0) - $current_tasks);
    $referrals_left = max(0, (int) ($next_policy['promotion_valid_referrals'] ?? 0) - $current_referrals);
    $reviews_left = max(0, (int) ($next_policy['promotion_approved_reviews'] ?? 0) - $current_reviews);
    $accuracy_left = max(0, (float) ($next_policy['promotion_accuracy'] ?? 0) - $current_accuracy);

    if ($next_level === 'pro') {
        if (!$mission_completed) {
            $next_goal_text = 'Complete all 10 LearnHub days → Pro';
        } elseif ($referrals_left > 0) {
            $next_goal_text = $referrals_left . ' more referral' . ($referrals_left > 1 ? 's' : '') . ' → Pro';
        } else {
            $account_age_days = (int) ($level_state['stats']['account_age_days'] ?? 0);
            $age_needed = max(0, (int) ($next_policy['promotion_account_age_days'] ?? 0) - $account_age_days);
            if ($age_needed > 0) {
                $next_goal_text = $age_needed . ' more days → Pro';
            }
        }
    } else {
        $parts = [];
        if ($task_left > 0) $parts[] = $task_left . ' task' . ($task_left > 1 ? 's' : '');
        if ($referrals_left > 0) $parts[] = $referrals_left . ' referral' . ($referrals_left > 1 ? 's' : '');
        if ($reviews_left > 0) $parts[] = $reviews_left . ' review' . ($reviews_left > 1 ? 's' : '');
        if ($accuracy_left > 0) $parts[] = number_format($accuracy_left, 1) . '% accuracy';
        if (!empty($parts)) {
            $next_goal_text = implode(', ', $parts) . ' → ' . ucfirst((string) $next_level);
        }
    }
}

$pro_policy = getLevelPolicy('pro');
$pro_required_referrals = (int) ($pro_policy['promotion_valid_referrals'] ?? PRO_MIN_VALID_REFERRALS);
$pro_required_age_days = (int) ($pro_policy['promotion_account_age_days'] ?? PRO_MIN_ACCOUNT_AGE_DAYS);
$pro_mission_complete = !empty($level_state['stats']['mission_completed']);
$pro_current_referrals = (int) ($level_state['stats']['valid_referrals'] ?? $user['valid_referrals'] ?? 0);
$pro_account_age_days = (int) ($level_state['stats']['account_age_days'] ?? 0);
$pro_security_signals = getUserSecuritySignals((int) $user['id'], $db);
$pro_security_clear = empty($pro_security_signals['is_suspicious']);
$pro_referrals_complete = $pro_current_referrals >= $pro_required_referrals;
$pro_age_complete = $pro_account_age_days >= $pro_required_age_days;
$pro_requirements = [
    [
        'key' => 'learnhub',
        'label' => 'LearnHub Mission',
        'complete' => $pro_mission_complete,
        'blocked' => false,
        'meta' => $pro_mission_complete ? '10-day mission complete' : 'Complete all 10 LearnHub days',
        'helper' => $pro_mission_complete ? 'Done. This requirement is ready.' : 'Finish the final LearnHub mission flow, including the mystery box.',
        'action_label' => $pro_mission_complete ? '' : 'Open LearnHub',
        'action_url' => BASE_URL . '/public/taskhub.php',
        'copy_text' => '',
        'icon' => 'fas fa-graduation-cap',
    ],
    [
        'key' => 'referral',
        'label' => 'Valid Referral',
        'complete' => $pro_referrals_complete,
        'blocked' => false,
        'meta' => number_format($pro_current_referrals) . '/' . number_format(max(1, $pro_required_referrals)) . ' valid referral',
        'helper' => $pro_referrals_complete ? 'Done. Your referral requirement is complete.' : 'Invite one friend who qualifies as a valid referral.',
        'action_label' => $pro_referrals_complete ? '' : 'Copy Link',
        'action_url' => '',
        'copy_text' => BASE_URL . '/auth/auth.php?ref=' . ($user['referral_code'] ?? ''),
        'icon' => 'fas fa-user-plus',
    ],
    [
        'key' => 'age',
        'label' => 'Account Age',
        'complete' => $pro_age_complete,
        'blocked' => false,
        'meta' => number_format($pro_account_age_days) . '/' . number_format(max(1, $pro_required_age_days)) . ' days old',
        'helper' => $pro_age_complete
            ? 'Done. Your account age is eligible.'
            : 'Wait ' . max(0, $pro_required_age_days - $pro_account_age_days) . ' more day' . (max(0, $pro_required_age_days - $pro_account_age_days) === 1 ? '' : 's') . ' for Pro eligibility.',
        'action_label' => '',
        'action_url' => '',
        'copy_text' => '',
        'icon' => 'fas fa-clock',
    ],
    [
        'key' => 'security',
        'label' => 'Security Status',
        'complete' => $pro_security_clear,
        'blocked' => !$pro_security_clear,
        'meta' => $pro_security_clear ? 'Account activity clear' : 'Account under review',
        'helper' => $pro_security_clear ? 'Done. No security review is blocking Pro.' : 'Your account needs a security review before Pro can unlock.',
        'action_label' => '',
        'action_url' => '',
        'copy_text' => '',
        'icon' => 'fas fa-shield-alt',
    ],
];
$pro_completed_count = 0;
foreach ($pro_requirements as $pro_requirement) {
    if (!empty($pro_requirement['complete'])) {
        $pro_completed_count++;
    }
}
$pro_total_count = count($pro_requirements);
$pro_progress_percent = $pro_total_count > 0 ? (int) round(($pro_completed_count / $pro_total_count) * 100) : 0;
$pro_ready = $pro_completed_count === $pro_total_count;
$level_card_target = 'Pro';
$level_card_path = 'Beginner to Pro';
$level_card_title = 'Complete 4 Pro requirements';
$level_card_unlocked = $current_level === 'expert';

if ($current_level === 'pro') {
    $expert_policy = getLevelPolicy('expert');
    $expert_required_reviews = (int) ($expert_policy['promotion_approved_reviews'] ?? 100);
    $expert_required_referrals = (int) ($expert_policy['promotion_valid_referrals'] ?? 10);
    $expert_required_accuracy = (float) ($expert_policy['promotion_accuracy'] ?? 85);
    $expert_max_rejection_ratio = (float) ($expert_policy['max_rejection_ratio'] ?? 0.15);
    $expert_current_reviews = (int) ($level_state['stats']['approved_reviews'] ?? 0);
    $expert_current_referrals = (int) ($level_state['stats']['valid_referrals'] ?? $user['valid_referrals'] ?? 0);
    $expert_current_accuracy = (float) ($level_state['stats']['accuracy'] ?? $level_state['accuracy'] ?? 0);
    $expert_current_rejection_ratio = (float) ($level_state['stats']['rejection_ratio'] ?? $level_state['rejection_ratio'] ?? 0);
    $expert_reviews_complete = $expert_current_reviews >= $expert_required_reviews;
    $expert_referrals_complete = $expert_current_referrals >= $expert_required_referrals;
    $expert_accuracy_complete = $expert_current_accuracy >= $expert_required_accuracy;
    $expert_rejection_complete = $expert_current_rejection_ratio <= $expert_max_rejection_ratio;

    $level_card_target = 'Expert';
    $level_card_path = 'Pro to Expert';
    $level_card_title = 'Complete 4 Expert requirements';
    $pro_requirements = [
        [
            'key' => 'reviews',
            'label' => 'Approved Reviews',
            'complete' => $expert_reviews_complete,
            'blocked' => false,
            'meta' => number_format($expert_current_reviews) . '/' . number_format(max(1, $expert_required_reviews)) . ' approved reviews',
            'helper' => $expert_reviews_complete ? 'Done. Your review volume is ready.' : 'Submit quality reviews and wait for approvals.',
            'action_label' => $expert_reviews_complete ? '' : 'Browse Projects',
            'action_url' => BASE_URL . '/public/projects.php',
            'copy_text' => '',
            'icon' => 'fas fa-star',
        ],
        [
            'key' => 'expert_referrals',
            'label' => 'Valid Referrals',
            'complete' => $expert_referrals_complete,
            'blocked' => false,
            'meta' => number_format($expert_current_referrals) . '/' . number_format(max(1, $expert_required_referrals)) . ' valid referrals',
            'helper' => $expert_referrals_complete ? 'Done. Your referral count is ready.' : 'Invite more qualified users with your referral link.',
            'action_label' => $expert_referrals_complete ? '' : 'Copy Link',
            'action_url' => '',
            'copy_text' => BASE_URL . '/auth/auth.php?ref=' . ($user['referral_code'] ?? ''),
            'icon' => 'fas fa-user-plus',
        ],
        [
            'key' => 'accuracy',
            'label' => 'Review Accuracy',
            'complete' => $expert_accuracy_complete,
            'blocked' => false,
            'meta' => number_format($expert_current_accuracy, 1) . '/' . number_format($expert_required_accuracy, 1) . '% accuracy',
            'helper' => $expert_accuracy_complete ? 'Done. Your accuracy meets Expert level.' : 'Keep reviews detailed, fair, and proof-backed.',
            'action_label' => '',
            'action_url' => '',
            'copy_text' => '',
            'icon' => 'fas fa-bullseye',
        ],
        [
            'key' => 'rejection_ratio',
            'label' => 'Quality Standing',
            'complete' => $expert_rejection_complete,
            'blocked' => !$expert_rejection_complete,
            'meta' => number_format($expert_current_rejection_ratio * 100, 1) . '% rejection ratio',
            'helper' => $expert_rejection_complete ? 'Done. Your review quality standing is healthy.' : 'Reduce rejected reviews before Expert can unlock.',
            'action_label' => '',
            'action_url' => '',
            'copy_text' => '',
            'icon' => 'fas fa-shield-alt',
        ],
    ];
    $pro_completed_count = 0;
    foreach ($pro_requirements as $pro_requirement) {
        if (!empty($pro_requirement['complete'])) {
            $pro_completed_count++;
        }
    }
    $pro_total_count = count($pro_requirements);
    $pro_progress_percent = $pro_total_count > 0 ? (int) round(($pro_completed_count / $pro_total_count) * 100) : 0;
    $pro_ready = $pro_completed_count === $pro_total_count;
} elseif ($current_level === 'expert') {
    $level_card_target = 'Expert';
    $level_card_path = 'Current access';
    $level_card_title = 'Expert unlocked';
    $pro_completed_count = 4;
    $pro_total_count = 4;
    $pro_progress_percent = 100;
    $pro_ready = true;
}

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/dashboard.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/dashboard.css'); ?>">

<main class="dashboard-main">
    <div class="dashboard-container">
        <?php if ($dashboard_notice !== ''): ?>
            <section class="card dashboard-notice-card">
                <p class="dashboard-notice-text"><?php echo htmlspecialchars($dashboard_notice, ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
        <?php endif; ?>

        <!-- ===== HERO ===== -->
        <section class="dashboard-hero">
            <div class="hero-glow hero-glow--1"></div>
            <div class="hero-glow hero-glow--2"></div>

            <div class="hero-left">
                <div class="hero-top-row">
                    <div class="hero-avatar<?php echo $user_avatar_url !== '' ? ' has-avatar-image' : ''; ?>"<?php if ($user_avatar_url !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($user_avatar_url, ENT_QUOTES, 'UTF-8'); ?>');"<?php endif; ?>>
                        <?php if ($user_avatar_url === ''): ?>
                            <span class="hero-avatar-initial"><?php echo htmlspecialchars($avatar_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="hero-name-area">
                        <h1 class="hero-name"><?php echo htmlspecialchars($profile_name, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <span class="hero-level-badge level-badge-<?php echo htmlspecialchars($current_level, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="<?php echo htmlspecialchars($level_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                            <?php echo htmlspecialchars($current_level === 'pro' ? 'Pro' : ucfirst((string) $user['level']), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php if ((int) ($user['email_verified'] ?? 0) === 1): ?>
                        <span class="hero-email-verified-badge" title="<?php echo !empty($user['email_verified_at']) ? 'Verified at ' . htmlspecialchars((string) $user['email_verified_at'], ENT_QUOTES, 'UTF-8') : 'Email verified'; ?>">
                            <i class="fas fa-envelope-circle-check"></i>
                            Email Verified
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="hero-subtitle">Your dashboard — track your $REX earnings, level up, and unlock rewards.</p>
                <div class="hero-stats-row">
                    <span class="hero-stat-pill">
                        <span class="hero-stat-icon">🎯</span>
                        <?php echo number_format((float) ($level_state['accuracy'] ?? 0), 1); ?>%
                    </span>
                    <span class="hero-stat-pill">
                        <span class="hero-stat-icon">👥</span>
                        <?php echo number_format((int) ($user['valid_referrals'] ?? 0)); ?> ref
                    </span>
                    <span class="hero-stat-pill">
                        <span class="hero-stat-icon">✅</span>
                        <?php echo number_format((int) ($mini_task_stats['completed_total'] ?? 0)); ?> tasks
                    </span>
                </div>
            </div>

            <div class="hero-balance">
                <span class="hero-balance-label">Available</span>
                <div class="hero-balance-amount">
                    <span class="hero-balance-number"><?php echo number_format((float) $balance, 2); ?></span>
                    <span class="hero-balance-currency">$REX</span>
                </div>
                <?php if ($next_goal_text !== ''): ?>
                    <span class="hero-next-goal">🎯 <?php echo htmlspecialchars($next_goal_text, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
        </section>

        <?php
        // Early Adopter Airdrop Widget
        $pending_airdrop_details = getPendingAirdropDetails((int) $user['id'], $db);
        $pending_airdrop = (float) ($pending_airdrop_details['amount'] ?? 0);
        $airdrop_days_remaining = $pending_airdrop_details['days_remaining'] ?? null;
        $airdrop_expiry_label = !empty($pending_airdrop_details['expires_at'])
            ? date('M d, Y', strtotime((string) $pending_airdrop_details['expires_at']))
            : '';
        $airdrop_progress = getAirdropProgress((int) $user['id'], $db);
        $airdrop_completed_days = (int) ($airdrop_progress['completed_days'] ?? 0);
        $airdrop_total_days = (int) ($airdrop_progress['total_days'] ?? TASKHUB_TOTAL_DAYS);
        $airdrop_days_to_finish = max(0, $airdrop_total_days - $airdrop_completed_days);
        $airdrop_progress_percent = (float) ($airdrop_progress['progress'] ?? 0);
        $airdrop_next_step = $airdrop_days_to_finish > 0
            ? ($airdrop_days_to_finish . ' LearnHub day' . ($airdrop_days_to_finish === 1 ? '' : 's') . ' left')
            : 'Reach PRO Level';
        $is_pro = ($user['level'] ?? '') === 'pro';
        ?>
        <?php if ($show_airdrop_feature && $pending_airdrop > 0 && !$is_pro): ?>
        <section class="airdrop-premium">
            <div class="airdrop-premium-glow airdrop-premium-glow--1"></div>
            <div class="airdrop-premium-glow airdrop-premium-glow--2"></div>

            <div class="airdrop-premium-top">
                <div class="airdrop-premium-badge">
                    <span class="airdrop-premium-badge-icon">🚀</span>
                    <span class="airdrop-premium-badge-text">Early Adopter</span>
                </div>
                <div class="airdrop-premium-value">
                    <span class="airdrop-premium-value-amount"><?php echo number_format($pending_airdrop, 0); ?></span>
                    <span class="airdrop-premium-value-currency">$REX</span>
                    <span class="airdrop-premium-value-label">Pending Airdrop</span>
                </div>
            </div>

            <div class="airdrop-premium-body">
                <div class="airdrop-premium-progress-header">
                    <span class="airdrop-premium-progress-label">LearnHub Progress</span>
                    <span class="airdrop-premium-progress-count">
                        <strong><?php echo $airdrop_completed_days; ?></strong>/<?php echo $airdrop_total_days; ?> days
                    </span>
                </div>
                <div class="airdrop-premium-progress-track">
                    <div class="airdrop-premium-progress-fill" style="width: <?php echo $airdrop_progress_percent; ?>%">
                        <span class="airdrop-premium-progress-fill-pct"><?php echo $airdrop_progress_percent; ?>%</span>
                    </div>
                </div>

                <div class="airdrop-premium-milestones">
                    <div class="airdrop-premium-milestone <?php echo $airdrop_completed_days >= 1 ? 'airdrop-premium-milestone--done' : ''; ?>">
                        <span class="airdrop-premium-milestone-dot"></span>
                        <span class="airdrop-premium-milestone-label">Day 1</span>
                    </div>
                    <div class="airdrop-premium-milestone <?php echo $airdrop_completed_days >= 4 ? 'airdrop-premium-milestone--done' : ''; ?>">
                        <span class="airdrop-premium-milestone-dot"></span>
                        <span class="airdrop-premium-milestone-label">Day 4</span>
                    </div>
                    <div class="airdrop-premium-milestone <?php echo $airdrop_completed_days >= 7 ? 'airdrop-premium-milestone--done' : ''; ?>">
                        <span class="airdrop-premium-milestone-dot"></span>
                        <span class="airdrop-premium-milestone-label">Day 7</span>
                    </div>
                    <div class="airdrop-premium-milestone <?php echo $airdrop_completed_days >= 10 ? 'airdrop-premium-milestone--done' : ''; ?>">
                        <span class="airdrop-premium-milestone-dot"></span>
                        <span class="airdrop-premium-milestone-label">Day 10</span>
                    </div>
                </div>

                <div class="airdrop-premium-summary">
                    <div>
                        <span>Next step</span>
                        <strong><?php echo htmlspecialchars($airdrop_next_step, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div>
                        <span>Deadline</span>
                        <strong><?php echo htmlspecialchars($airdrop_expiry_label !== '' ? $airdrop_expiry_label : 'Pending', ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div>
                        <span>Time left</span>
                        <strong><?php echo $airdrop_days_remaining !== null ? ((int) $airdrop_days_remaining . ' day' . ((int) $airdrop_days_remaining === 1 ? '' : 's')) : 'Active'; ?></strong>
                    </div>
                </div>

                <div class="airdrop-premium-info">
                    <div class="airdrop-premium-info-item">
                        <span class="airdrop-premium-info-icon">🎯</span>
                        <span class="airdrop-premium-info-text">Step 1: complete <strong><?php echo $airdrop_total_days; ?> LearnHub days</strong>. Step 2: reach <strong>PRO Level</strong>. Then your <strong><?php echo number_format($pending_airdrop, 0); ?> $REX</strong> unlocks automatically.</span>
                    </div>
                    <div class="airdrop-premium-info-item">
                        <span class="airdrop-premium-info-icon">👥</span>
                        <span class="airdrop-premium-info-text">Invite a friend. When they complete <strong>4 LearnHub days</strong>, you earn <strong><?php echo number_format(EARLY_AIRDROP_REFERRAL_BONUS); ?> $REX</strong> instantly in your ledger.</span>
                    </div>
                </div>

                <!-- Referral Code Section (two-column) -->
                <div class="airdrop-premium-referral">
                    <div class="airdrop-premium-referral-header">
                        <span class="airdrop-premium-referral-icon">🔗</span>
                        <span class="airdrop-premium-referral-title">Your Referral Code</span>
                    </div>
                    <div class="airdrop-premium-referral-cols">
                        <div class="airdrop-premium-referral-col">
                            <span class="airdrop-premium-referral-col-label">Code</span>
                            <div class="airdrop-premium-referral-code-row">
                                <span class="airdrop-premium-referral-code"><?php echo htmlspecialchars((string) $user['referral_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <button type="button" class="airdrop-premium-referral-copy" data-copy-text="<?php echo htmlspecialchars((string) $user['referral_code'], ENT_QUOTES, 'UTF-8'); ?>" title="Copy referral code">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="airdrop-premium-referral-col">
                            <span class="airdrop-premium-referral-col-label">Share Link</span>
                            <div class="airdrop-premium-referral-link-row">
                                <span class="airdrop-premium-referral-link"><?php echo htmlspecialchars(BASE_URL . '/auth/auth.php?ref=' . $user['referral_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <button type="button" class="airdrop-premium-referral-copy" data-copy-text="<?php echo htmlspecialchars(BASE_URL . '/auth/auth.php?ref=' . $user['referral_code'], ENT_QUOTES, 'UTF-8'); ?>" title="Copy referral link">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="airdrop-premium-info-item airdrop-premium-info-item--warning">
                    <span class="airdrop-premium-info-icon"><i class="fas fa-triangle-exclamation"></i></span>
                    <span class="airdrop-premium-info-text">
                        Your reward is safely reserved for <strong><?php echo (int) EARLY_AIRDROP_UNLOCK_DAYS; ?> days</strong> after signup.
                        Complete LearnHub and reach <strong>PRO Level</strong> by
                        <strong><?php echo htmlspecialchars($airdrop_expiry_label !== '' ? $airdrop_expiry_label : 'expiry', ENT_QUOTES, 'UTF-8'); ?></strong>
                        <?php if ($airdrop_days_remaining !== null): ?>
                            (<?php echo (int) $airdrop_days_remaining; ?> day<?php echo (int) $airdrop_days_remaining === 1 ? '' : 's'; ?> left)
                        <?php endif; ?>
                        to keep it. If time runs out, it simply returns to the airdrop pool.
                    </span>
                </div>

                <a href="<?php echo BASE_URL; ?>/public/taskhub.php" class="airdrop-premium-cta">
                    <span>Go to LearnHub</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
        </section>
        <?php elseif ($show_airdrop_feature && !empty($airdrop_unlock_state['unlocked']) && (float) ($airdrop_unlock_state['amount'] ?? 0) > 0): ?>
        <section class="airdrop-premium airdrop-premium--unlocked">
            <div class="airdrop-premium-glow airdrop-premium-glow--1"></div>
            <div class="airdrop-premium-glow airdrop-premium-glow--2"></div>

            <div class="airdrop-premium-top">
                <div class="airdrop-premium-badge airdrop-premium-badge--unlocked">
                    <span class="airdrop-premium-badge-icon">✅</span>
                    <span class="airdrop-premium-badge-text">Unlocked</span>
                </div>
                <div class="airdrop-premium-value">
                    <span class="airdrop-premium-value-amount"><?php echo number_format((float) ($airdrop_unlock_state['amount'] ?? 0), 0); ?></span>
                    <span class="airdrop-premium-value-currency">$REX</span>
                    <span class="airdrop-premium-value-label">Now Available</span>
                </div>
            </div>

            <div class="airdrop-premium-body">
                <div class="airdrop-premium-unlocked-message">
                    <span class="airdrop-premium-unlocked-icon">🎉</span>
                    <div>
                        <strong>Congratulations!</strong>
                        <p>Your <strong><?php echo number_format((float) ($airdrop_unlock_state['amount'] ?? 0), 0); ?> $REX</strong> airdrop has been unlocked and added to your available balance!</p>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== STATS GRID ===== -->
        <div class="stats-grid">
            <div class="stat-card stat-card--balance">
                <span class="stat-card-icon">💰</span>
                <strong class="stat-card-value"><?php echo number_format((float) ($user['rex_balance'] ?? 0), 2); ?></strong>
                <span class="stat-card-label">$REX Balance</span>
            </div>
            <div class="stat-card">
                <span class="stat-card-icon">📈</span>
                <strong class="stat-card-value"><?php echo number_format((float) ($user['total_rex_earned'] ?? 0), 2); ?></strong>
                <span class="stat-card-label">Total Earned</span>
            </div>
            <div class="stat-card">
                <span class="stat-card-icon">⭐</span>
                <strong class="stat-card-value"><?php echo number_format((int) ($user['total_reviews'] ?? 0)); ?></strong>
                <span class="stat-card-label">Reviews</span>
            </div>
            <div class="stat-card">
                <span class="stat-card-icon">🗳️</span>
                <strong class="stat-card-value"><?php echo number_format((int) ($user['total_votes'] ?? 0)); ?></strong>
                <span class="stat-card-label">Votes Cast</span>
            </div>
        </div>

        <!-- ===== ACTIONS + LEVEL ===== -->
        <div class="dashboard-grid">
            <!-- Quick Actions -->
            <section class="card actions-card">
                <div class="section-head">
                    <h3>Quick Actions</h3>
                </div>
                <div class="actions-grid">
                    <?php if ($show_reviews_feature && userCanAccessProjectReviewArea($user)): ?>
                        <a href="<?php echo BASE_URL; ?>/public/submit-review.php" class="action-icon-btn" title="Write Review">
                            <span class="action-icon-btn-icon">✍️</span>
                            <span class="action-icon-btn-label">Review</span>
                        </a>
                    <?php elseif ($show_reviews_feature): ?>
                        <span class="action-icon-btn action-icon-btn--locked" title="Unlocks at Pro level">
                            <span class="action-icon-btn-icon">✍️</span>
                            <span class="action-icon-btn-label">Review</span>
                            <span class="action-icon-btn-lock">🔒</span>
                        </span>
                    <?php endif; ?>
                    <?php if ($show_projects_feature && userCanAccessProjectReviewArea($user)): ?>
                        <a href="<?php echo BASE_URL; ?>/public/projects.php" class="action-icon-btn" title="View Projects">
                            <span class="action-icon-btn-icon">📁</span>
                            <span class="action-icon-btn-label">Projects</span>
                        </a>
                    <?php elseif ($show_projects_feature): ?>
                        <span class="action-icon-btn action-icon-btn--locked" title="Unlocks at Pro level">
                            <span class="action-icon-btn-icon">📁</span>
                            <span class="action-icon-btn-label">Projects</span>
                            <span class="action-icon-btn-lock">🔒</span>
                        </span>
                    <?php endif; ?>
                    <?php if ($show_learnhub_feature): ?>
                    <a href="<?php echo BASE_URL; ?>/public/taskhub.php" class="action-icon-btn" title="LearnHub">
                        <span class="action-icon-btn-icon">✅</span>
                        <span class="action-icon-btn-label">LearnHub</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($show_boosthub_feature): ?>
                    <a href="<?php echo BASE_URL; ?>/public/boosthub.php" class="action-icon-btn" title="BoostHub">
                        <span class="action-icon-btn-icon">🚀</span>
                        <span class="action-icon-btn-label">Boost</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/public/rex-signer.php" class="action-icon-btn" title="RexLink">
                        <span class="action-icon-btn-icon"><i class="fas fa-qrcode"></i></span>
                        <span class="action-icon-btn-label">Signer</span>
                    </a>
                    <?php if ($show_claim_feature): ?>
                    <span class="action-icon-btn action-icon-btn--locked" title="Coming soon">
                        <span class="action-icon-btn-icon">🏆</span>
                        <span class="action-icon-btn-label">Claim</span>
                        <span class="action-icon-btn-lock">🔒</span>
                    </span>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Pro Progress -->
            <section class="card level-card pro-progress-card">
                <div class="section-head">
                    <h3><?php echo $level_card_unlocked ? 'Level Unlocked' : htmlspecialchars($level_card_target . ' Progress', ENT_QUOTES, 'UTF-8'); ?></h3>
                    <span class="level-ring-badge level-badge-<?php echo htmlspecialchars($current_level, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="<?php echo htmlspecialchars($level_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <?php echo htmlspecialchars($current_level === 'pro' ? 'Pro' : ucfirst((string) $user['level']), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>

                <div class="pro-progress-top">
                    <div>
                        <span class="pro-progress-eyebrow"><?php echo htmlspecialchars($level_card_path, ENT_QUOTES, 'UTF-8'); ?></span>
                        <strong><?php echo $level_card_unlocked ? htmlspecialchars(ucfirst($current_level), ENT_QUOTES, 'UTF-8') . ' unlocked' : htmlspecialchars($level_card_title, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <span class="pro-progress-count"><?php echo $level_card_unlocked ? '4/4 complete' : $pro_completed_count . '/' . $pro_total_count . ' complete'; ?></span>
                </div>

                <div class="pro-progress-bar" aria-label="<?php echo htmlspecialchars($level_card_target, ENT_QUOTES, 'UTF-8'); ?> progress <?php echo (int) ($level_card_unlocked ? 100 : $pro_progress_percent); ?> percent">
                    <span style="width: <?php echo (int) ($level_card_unlocked ? 100 : $pro_progress_percent); ?>%"></span>
                </div>

                <?php if ($level_card_unlocked): ?>
                    <div class="pro-progress-unlocked">
                        <span class="pro-progress-unlocked-icon"><i class="fas fa-check"></i></span>
                        <div>
                            <strong><?php echo htmlspecialchars(ucfirst($current_level), ENT_QUOTES, 'UTF-8'); ?> access is active</strong>
                            <p>Your review tools, reward claim access, and higher-level dashboard features are available.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="pro-progress-summary">
                        <?php echo $pro_ready
                            ? 'All ' . htmlspecialchars($level_card_target, ENT_QUOTES, 'UTF-8') . ' criteria are complete. Promotion will sync automatically.'
                            : 'Complete the pending items below to unlock ' . htmlspecialchars($level_card_target, ENT_QUOTES, 'UTF-8') . ' access.'; ?>
                    </p>

                    <div class="pro-requirement-list">
                        <?php foreach ($pro_requirements as $requirement): ?>
                            <?php
                            $req_state = !empty($requirement['blocked'])
                                ? 'blocked'
                                : (!empty($requirement['complete']) ? 'done' : 'pending');
                            $req_label = $req_state === 'done' ? 'Done' : ($req_state === 'blocked' ? 'Review' : 'Pending');
                            ?>
                            <div class="pro-requirement pro-requirement--<?php echo htmlspecialchars($req_state, ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="pro-requirement-icon"><i class="<?php echo htmlspecialchars((string) $requirement['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                                <div class="pro-requirement-main">
                                    <div class="pro-requirement-title-row">
                                        <strong><?php echo htmlspecialchars((string) $requirement['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span><?php echo htmlspecialchars($req_label, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <p><?php echo htmlspecialchars((string) $requirement['meta'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <small><?php echo htmlspecialchars((string) $requirement['helper'], ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>
                                <?php if (!empty($requirement['action_label']) && !empty($requirement['action_url'])): ?>
                                    <a class="pro-requirement-action" href="<?php echo htmlspecialchars((string) $requirement['action_url'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars((string) $requirement['action_label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                <?php elseif (!empty($requirement['action_label']) && !empty($requirement['copy_text'])): ?>
                                    <button type="button" class="pro-requirement-action" data-copy-text="<?php echo htmlspecialchars((string) $requirement['copy_text'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars((string) $requirement['action_label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="level-next-goal <?php echo $pro_ready ? 'level-next-goal--done' : ''; ?>">
                        <span class="level-next-goal-arrow"><i class="<?php echo $pro_ready ? 'fas fa-check' : 'fas fa-arrow-right'; ?>"></i></span>
                        <span><?php echo $pro_ready ? 'All criteria met - promoting soon' : htmlspecialchars((string) ($next_goal_text ?: 'Finish the pending ' . $level_card_target . ' requirements'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (false && $next_level && $next_goal_text !== ''): ?>
                    <div class="level-next-goal">
                        <span class="level-next-goal-arrow">→</span>
                        <span><?php echo htmlspecialchars($next_goal_text, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                <?php elseif (false && $next_level): ?>
                    <div class="level-next-goal level-next-goal--done">
                        <span>✅ All criteria met — promoting soon</span>
                    </div>
                <?php elseif (false): ?>
                    <div class="level-next-goal level-next-goal--done">
                        <span>👑 Top level reached</span>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <!-- ===== RECENT REVIEWS ===== -->
        <section class="card reviews-card">
            <div class="section-head">
                <h3>Recent Reviews</h3>
            </div>
            <div class="reviews-list">
                <?php if (!empty($recent_reviews)): ?>
                    <?php foreach ($recent_reviews as $review): ?>
                        <div class="review-row">
                            <div class="review-row-left">
                                <strong class="review-row-project"><?php echo htmlspecialchars((string) ($review['project_name'] ?: 'Project'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="review-row-title"><?php echo htmlspecialchars((string) ($review['review_title'] ?: 'Review submitted'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="review-row-right">
                                <span class="review-row-rex"><?php echo number_format((float) ($review['final_rex'] ?? 0), 2); ?> $REX</span>
                                <span class="review-row-status status-<?php echo htmlspecialchars((string) ($review['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) ($review['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="review-row review-row--empty">
                        <span class="review-empty-text">No reviews yet</span>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<script>
document.querySelectorAll('[data-copy-target]').forEach((button) => {
    button.addEventListener('click', async function() {
        const input = document.getElementById(button.dataset.copyTarget || '');
        if (!input) return;
        try {
            await navigator.clipboard.writeText(input.value);
            button.textContent = 'Copied';
            window.setTimeout(() => { button.textContent = 'Copy'; }, 1200);
        } catch (error) {
            input.select();
        }
    });
});

async function copyTextToClipboard(text) {
    if (!text) return false;
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return true;
    }

    const temp = document.createElement('input');
    temp.value = text;
    temp.setAttribute('readonly', 'readonly');
    temp.style.position = 'fixed';
    temp.style.left = '-9999px';
    temp.style.top = '0';
    document.body.appendChild(temp);
    temp.select();
    temp.setSelectionRange(0, temp.value.length);
    const copied = document.execCommand('copy');
    document.body.removeChild(temp);
    return copied;
}

document.querySelectorAll('.pro-requirement-action[data-copy-text]').forEach((button) => {
    button.addEventListener('click', async function() {
        const text = this.dataset.copyText || '';
        const originalText = this.textContent;
        try {
            const copied = await copyTextToClipboard(text);
            this.textContent = copied ? 'Copied' : 'Copy Failed';
        } catch (error) {
            this.textContent = 'Copy Failed';
        }
        window.setTimeout(() => { this.textContent = originalText; }, 1400);
    });
});

document.querySelectorAll('[data-copy-text]:not(.pro-requirement-action)').forEach((button) => {
    button.addEventListener('click', async function() {
        const text = this.dataset.copyText || '';
        if (!text) return;
        try {
            await copyTextToClipboard(text);
            this.classList.add('airdrop-premium-referral-copy--copied');
            const originalHtml = this.innerHTML;
            this.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
            window.setTimeout(() => {
                this.innerHTML = originalHtml;
                this.classList.remove('airdrop-premium-referral-copy--copied');
            }, 1500);
        } catch (error) {
            this.classList.remove('airdrop-premium-referral-copy--copied');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
