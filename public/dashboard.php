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
ensureRexRankSchema($db);

$user = getCurrentUser();
$engagement_state = engagementDashboardState((int)$user['id'], $db);
$engagement_announcement = $engagement_state['announcement'];
$social_assignment = $engagement_state['assignment'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAppCsrf((string) ($_POST['csrf_token'] ?? ''));
    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'convert_rexrank') {
        $result = convertRexRankToRex((int) $user['id'], (float) ($_POST['amount_rr'] ?? 0), $db);
        setFlashMessage('dashboard_success', (string) ($result['message'] ?? 'Unable to convert RexRank.'));
        redirect(BASE_URL . '/public/dashboard.php');
    }
}

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
$rexrank_stats = getUserRexRankStats((int) $user['id'], $db);

$my_referrals = getUserReferralList((int) $user['id'], $db);
$referral_metrics = ['total' => count($my_referrals), 'valid' => 0, 'pending' => 0, 'invalid' => 0];
foreach ($my_referrals as $ref) {
    $ref_status = strtolower(trim((string) ($ref['referral_review_status'] ?? 'pending')));
    if ($ref_status === 'qualified') {
        $referral_metrics['valid']++;
    } elseif ($ref_status === 'invalid') {
        $referral_metrics['invalid']++;
    } else {
        $referral_metrics['pending']++;
    }
}

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
$has_pro_access = in_array($current_level, ['pro', 'expert'], true);
$pro_weekly_streak_state = null;
if ($has_pro_access) {
    try {
        $pro_weekly_streak_state = proWeeklyStreakGetState((int) $user['id'], $db);
    } catch (Throwable $e) {
        error_log('PRO weekly streak state failed: ' . $e->getMessage());
    }
}
$next_level = $level_progress['next_level'] ?? null;
$level_definitions = getLevelSystemDefinitions();
$next_policy = $next_level && isset($level_definitions[$next_level]) ? $level_definitions[$next_level] : null;
$remaining_requirements = [];
$referrals_left = 0;
$task_left = 0;
$reviews_left = 0;
$accuracy_left = 0;
$next_goal_text = '';
if ($next_policy) {
    $mission_completed = !empty($level_state['stats']['mission_completed']);

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

// Dashboard mission portal. Reuse each Hub's authoritative state so counts and
// availability match the dedicated task pages exactly.
$dashboard_learn_state = null;
$dashboard_learn_tasks = [];
$dashboard_checkin_task = null;
$dashboard_boost_state = null;

if ($show_learnhub_feature && !$pro_mission_complete && $current_level === 'beginner') {
    try {
        $dashboard_learn_state = getTaskHubState((int) $user['id'], $db);
        if (($dashboard_learn_state['access'] ?? '') === 'open') {
            foreach (($dashboard_learn_state['tasks'] ?? []) as $dashboard_task) {
                if (taskHubIsCheckinTaskKey((string) ($dashboard_task['task_key'] ?? ''))) {
                    $dashboard_checkin_task = $dashboard_task;
                    continue;
                }
                if (($dashboard_task['status'] ?? '') !== 'completed') {
                    $dashboard_learn_tasks[] = $dashboard_task;
                }
            }
        }
    } catch (Throwable $e) {
        $dashboard_learn_state = null;
    }
}

if ($show_boosthub_feature) {
    try {
        $dashboard_boost_state = getBoostHubStateForUser((int) $user['id'], $db);
    } catch (Throwable $e) {
        $dashboard_boost_state = null;
    }
}

$dashboard_checkin_status = (string) ($dashboard_checkin_task['status'] ?? '');
$dashboard_has_learn_item = $dashboard_learn_state !== null
    && (!empty($dashboard_learn_tasks)
        || ($dashboard_checkin_task !== null && $dashboard_checkin_status !== 'completed'));
$dashboard_has_boost_item = $dashboard_boost_state !== null
    && (!empty($dashboard_boost_state['pending_task'])
        || !empty($dashboard_boost_state['task'])
        || !empty($dashboard_boost_state['submitted_task']));

$pro_referrals_complete = $pro_current_referrals >= $pro_required_referrals;
$pro_age_complete = $pro_account_age_days >= $pro_required_age_days;
$user_referral_link = buildReferralLink((string) ($user['referral_code'] ?? ''));
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
        'copy_text' => $user_referral_link,
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

if ($current_level !== 'pro' && $current_level !== 'expert') {
    if ($next_level === 'pro' && !$pro_mission_complete) {
        $next_goal_text = 'Complete all 10 LearnHub days -> Pro';
    }

    $pro_requirements = [
        [
            'key' => 'learnhub',
            'label' => 'LearnHub Mission',
            'complete' => $pro_mission_complete,
            'blocked' => false,
            'meta' => $pro_mission_complete ? '10-day mission complete' : 'Complete all 10 LearnHub days',
            'helper' => $pro_mission_complete ? 'Done. PRO will sync automatically.' : 'Finish the final LearnHub mission flow, including the mystery box.',
            'action_label' => $pro_mission_complete ? '' : 'Open LearnHub',
            'action_url' => BASE_URL . '/public/taskhub.php',
            'copy_text' => '',
            'icon' => 'fas fa-graduation-cap',
        ],
    ];
    $pro_completed_count = $pro_mission_complete ? 1 : 0;
    $pro_total_count = 1;
    $pro_progress_percent = $pro_mission_complete ? 100 : 0;
    $pro_ready = $pro_mission_complete;
    $level_card_title = 'Complete the 10-day LearnHub mission';
}

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
            'copy_text' => $user_referral_link,
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
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/pro-weekly-streak.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/pro-weekly-streak.css'); ?>">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/engagement.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/engagement.css'); ?>">

<?php if($engagement_announcement): ?>
<div class="eng-overlay" id="announcementModal">
 <section class="eng-card eng-card--announcement" role="dialog" aria-modal="true" aria-labelledby="announcementTitle">
  <button class="eng-close" id="announcementClose" type="button" aria-label="Close announcement"><i class="fas fa-xmark"></i></button>
  <div class="eng-card-header"><span class="eng-card-icon"><i class="fas fa-bullhorn"></i></span><div class="eng-heading"><span class="eng-eyebrow">Quick update</span><h2 id="announcementTitle"><?php echo htmlspecialchars($engagement_announcement['title'],ENT_QUOTES,'UTF-8'); ?></h2></div></div>
  <p class="eng-description"><?php echo nl2br(htmlspecialchars($engagement_announcement['message'],ENT_QUOTES,'UTF-8')); ?></p>
  <div class="eng-announcement-actions<?php echo empty($engagement_announcement['cta_url']) ? ' is-single' : ''; ?>"><?php if(!empty($engagement_announcement['cta_url'])): ?><a class="eng-primary eng-announcement-cta" target="_blank" rel="noopener noreferrer" href="<?php echo htmlspecialchars($engagement_announcement['cta_url'],ENT_QUOTES,'UTF-8'); ?>"><span><?php echo htmlspecialchars($engagement_announcement['cta_label']?:'Learn more',ENT_QUOTES,'UTF-8'); ?></span><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a><?php endif; ?><button class="eng-secondary eng-announcement-continue" id="announcementCloseSecondary" type="button"><i class="fas fa-check" aria-hidden="true"></i><span>Got it, continue</span></button></div>
  <label class="eng-optout"><input type="checkbox" id="announcementForever"><span><strong>Don't show this update again</strong><br>This only hides this announcement. Future important updates can still appear.</span></label>
 </section>
</div>
<?php endif; ?>
<?php if($social_assignment && !empty($social_assignment['is_blocked'])): ?>
<div class="eng-overlay" id="socialGateModal" <?php echo $engagement_announcement?'hidden':''; ?>>
 <section class="eng-card" role="dialog" aria-modal="true" aria-labelledby="socialGateTitle">
  <div class="eng-card-header"><span class="eng-card-icon"><i class="<?php echo $social_assignment['platform']==='x'?'fab fa-x-twitter':'fab fa-telegram'; ?>"></i></span><div class="eng-heading"><span class="eng-eyebrow">One quick setup</span><h2 id="socialGateTitle"><?php echo htmlspecialchars($social_assignment['modal_title'],ENT_QUOTES,'UTF-8'); ?></h2></div></div>
  <p class="eng-description"><?php echo nl2br(htmlspecialchars($social_assignment['modal_message'],ENT_QUOTES,'UTF-8')); ?></p>
  <div class="eng-steps" aria-label="Setup progress"><div class="eng-step is-active" data-eng-step="1"><span class="eng-step-number">1</span><span>Open channel</span></div><div class="eng-step" data-eng-step="2"><span class="eng-step-number">2</span><span>Add profile</span></div><div class="eng-step" data-eng-step="3"><span class="eng-step-number">3</span><span>Upload proof</span></div></div>
  <div class="eng-panel"><div class="eng-panel-title"><strong>Step 1 - Follow or join</strong><span class="eng-platform"><?php echo $social_assignment['platform']==='x'?'X / Twitter':'Telegram'; ?></span></div><p class="eng-helper">Click below. The channel opens in a new tab, so you won't lose this form.</p><button class="eng-primary" id="socialCta" type="button"><i class="<?php echo $social_assignment['platform']==='x'?'fab fa-x-twitter':'fab fa-telegram'; ?>"></i><span><?php echo htmlspecialchars($social_assignment['cta_label'],ENT_QUOTES,'UTF-8'); ?></span><i class="fas fa-arrow-up-right-from-square"></i></button></div>
  <form class="eng-form" id="socialEvidenceForm" enctype="multipart/form-data">
   <div class="eng-panel"><div class="eng-panel-title"><strong>Step 2 - Tell us your profile</strong></div><div class="eng-form">
    <label class="eng-field"><span class="eng-label">Your username <span class="eng-required">*</span></span><input class="eng-input" name="handle" autocomplete="off" placeholder="<?php echo $social_assignment['platform']==='x'?'Example: coinrex_user':'Example: coinrexmember'; ?>" required pattern="[A-Za-z0-9_.-]{3,120}"><span class="eng-field-hint">Enter it without the @ sign.</span></label>
    <label class="eng-field"><span class="eng-label">Your profile link <span class="eng-required">*</span></span><input class="eng-input" name="profile_url" type="url" inputmode="url" placeholder="<?php echo $social_assignment['platform']==='x'?'https://x.com/yourname':'https://t.me/yourname'; ?>" required><span class="eng-field-hint">Open your profile, copy its link, then paste it here.</span></label>
   </div></div>
   <div class="eng-panel"><div class="eng-panel-title"><strong>Step 3 - Upload a screenshot</strong></div><p class="eng-helper">Take a screenshot that clearly shows you followed or joined the CoinRex channel.</p><label class="eng-upload"><input id="socialScreenshot" name="screenshot" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required><i class="fas fa-cloud-arrow-up"></i><strong>Tap to choose screenshot</strong><span class="eng-file-name" id="socialFileName">JPG, PNG or WebP - maximum 5 MB</span></label></div>
   <div class="eng-attempt"><i class="fas fa-circle-info"></i><span>This is attempt <?php echo (int)$social_assignment['strike_count']+1; ?> of <?php echo max(1,(int)$social_assignment['max_strikes']); ?>. If anything is unclear, the admin will return it with a reason so you can fix it.</span></div>
   <div class="eng-privacy"><i class="fas fa-shield-halved"></i><span>Your screenshot is only used by CoinRex admins to review this request.</span></div>
   <button class="eng-primary" id="socialSubmitButton" type="submit" disabled><i class="fas fa-paper-plane"></i><span>Submit proof & open dashboard</span></button><p id="socialGateMessage" class="eng-message" role="status" aria-live="polite"></p>
  </form>
 </section>
</div>
<?php endif; ?>

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

        <?php if ($pro_weekly_streak_state !== null): ?>
            <?php require __DIR__ . '/../includes/dashboard/pro-weekly-streak.php'; ?>
        <?php endif; ?>

        <!-- ===== MISSION CONTROL ===== -->
        <?php if ($dashboard_has_learn_item || $dashboard_has_boost_item): ?>
            <?php
            $learn_pending_count = count($dashboard_learn_tasks);
            $checkin_status = (string) ($dashboard_checkin_task['status'] ?? 'locked');
            $checkin_available = $checkin_status === 'available';
            $boost_task = null;
            $boost_mode = (string) ($dashboard_boost_state['status'] ?? 'closed');
            $action_count = $learn_pending_count + ($checkin_available ? 1 : 0);
            if (!empty($dashboard_boost_state['pending_task'])) {
                $boost_task = $dashboard_boost_state['pending_task'];
                $boost_mode = 'returned';
                $action_count++;
            } elseif (!empty($dashboard_boost_state['task'])) {
                $boost_task = $dashboard_boost_state['task'];
                $boost_mode = 'open';
                $action_count++;
            } elseif (!empty($dashboard_boost_state['submitted_task'])) {
                $boost_task = $dashboard_boost_state['submitted_task'];
                $boost_mode = 'submitted';
            }
            ?>
            <section class="card mission-control" aria-labelledby="mission-control-title">
                <div class="mission-control-head">
                    <div class="mission-control-heading">
                        <span class="mission-control-logo" aria-hidden="true"><i class="fas fa-bolt"></i></span>
                        <div>
                            <span class="mission-control-eyebrow">Your next move</span>
                            <h3 id="mission-control-title">Mission Control</h3>
                            <p><?php echo $action_count > 0 ? number_format($action_count) . ' mission' . ($action_count === 1 ? '' : 's') . ' in today\'s queue.' : 'You are caught up. New missions will appear here.'; ?></p>
                        </div>
                    </div>
                    <span class="mission-live-pill"><span></span> Live</span>
                </div>
                <div class="mission-control-grid<?php echo (!$dashboard_has_learn_item || !$dashboard_has_boost_item) ? ' mission-control-grid--single' : ''; ?>">
                    <?php if ($dashboard_has_learn_item): ?>
                        <?php
                        $learn_day = (int) ($dashboard_learn_state['current_day'] ?? 1);
                        $learn_completed = (int) ($dashboard_learn_state['completed_tasks'] ?? 0);
                        $learn_total = max(1, (int) ($dashboard_learn_state['total_tasks'] ?? 0));
                        $learn_progress = (int) ($dashboard_learn_state['current_day_progress_percent'] ?? 0);
                        $checkin_reward = (float) ($dashboard_checkin_task['reward'] ?? $dashboard_learn_state['today_checkin_reward'] ?? 0);
                        ?>
                        <article class="mission-hub mission-hub--learn<?php echo $checkin_status === 'completed' ? ' mission-hub--checkin-secured' : ''; ?>">
                            <div class="mission-hub-head">
                                <div class="mission-hub-brand">
                                    <span class="mission-hub-icon"><i class="fas fa-graduation-cap"></i></span>
                                    <div><strong>LearnHub</strong><span>Day <?php echo $learn_day; ?> of 10</span></div>
                                </div>
                                <span class="mission-progress-label"><?php echo $learn_completed; ?>/<?php echo $learn_total; ?> done</span>
                            </div>
                            <div class="mission-progress-track" role="progressbar" aria-label="LearnHub day progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $learn_progress; ?>"><span style="width: <?php echo $learn_progress; ?>%"></span></div>

                            <?php if ($dashboard_checkin_task): ?>
                                <div class="mission-checkin mission-checkin--<?php echo htmlspecialchars($checkin_status, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="mission-checkin-icon"><i class="fas <?php echo $checkin_status === 'completed' ? 'fa-circle-check' : ($checkin_available ? 'fa-calendar-check' : 'fa-lock'); ?>"></i></span>
                                    <div class="mission-checkin-copy">
                                        <span>Daily check-in</span>
                                        <strong><?php echo $checkin_status === 'completed' ? 'Streak secured for today' : ($checkin_available ? 'Secure today\'s streak' : htmlspecialchars((string) ($dashboard_checkin_task['status_message'] ?? 'Unlocking soon'), ENT_QUOTES, 'UTF-8')); ?></strong>
                                    </div>
                                    <span class="mission-reward">+<?php echo number_format($checkin_reward, 0); ?> $REX</span>
                                    <?php if ($checkin_available): ?>
                                        <button type="button" class="mission-primary-btn" data-dashboard-checkin="<?php echo htmlspecialchars((string) ($dashboard_checkin_task['task_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">Check in <i class="fas fa-arrow-right"></i></button>
                                    <?php elseif ($checkin_status !== 'completed'): ?>
                                        <a class="mission-icon-link" href="<?php echo BASE_URL; ?>/public/taskhub.php" aria-label="Open LearnHub"><i class="fas fa-arrow-right"></i></a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="mission-task-list">
                                <div class="mission-list-label"><span>Pending missions</span><span class="<?php echo $learn_pending_count > 0 ? 'is-pending' : 'is-clear'; ?>"><?php echo $learn_pending_count; ?></span></div>
                                <?php foreach (array_slice($dashboard_learn_tasks, 0, 3) as $learn_task): ?>
                                    <?php
                                    $learn_task_status = (string) ($learn_task['status'] ?? 'locked');
                                    $learn_task_url = ($learn_task['verification_mode'] ?? '') === 'boosthub_redirect' ? BASE_URL . '/public/boosthub.php' : BASE_URL . '/public/taskhub.php';
                                    ?>
                                    <a class="mission-task-row" href="<?php echo htmlspecialchars($learn_task_url, ENT_QUOTES, 'UTF-8'); ?>">
                                        <span class="mission-task-state mission-task-state--<?php echo htmlspecialchars($learn_task_status, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas <?php echo $learn_task_status === 'submitted' ? 'fa-hourglass-half' : ($learn_task_status === 'available' ? 'fa-play' : 'fa-lock'); ?>"></i></span>
                                        <span class="mission-task-copy"><strong><?php echo htmlspecialchars((string) ($learn_task['title'] ?? 'LearnHub task'), ENT_QUOTES, 'UTF-8'); ?></strong><small><?php echo htmlspecialchars((string) ($learn_task['status_message'] ?? ucfirst($learn_task_status)), ENT_QUOTES, 'UTF-8'); ?></small></span>
                                        <span class="mission-task-reward">+<?php echo number_format((float) ($learn_task['reward'] ?? 0), 0); ?></span>
                                        <i class="fas fa-chevron-right mission-task-arrow"></i>
                                    </a>
                                <?php endforeach; ?>
                                <?php if ($learn_pending_count === 0): ?><div class="mission-empty"><i class="fas fa-circle-check"></i><span>Today's LearnHub missions are complete.</span></div><?php endif; ?>
                            </div>
                            <a class="mission-hub-footer" href="<?php echo BASE_URL; ?>/public/taskhub.php"><span>Continue LearnHub</span><i class="fas fa-arrow-right"></i></a>
                        </article>
                    <?php endif; ?>

                    <?php if ($dashboard_has_boost_item): ?>
                        <article class="mission-hub mission-hub--boost">
                            <div class="mission-hub-head">
                                <div class="mission-hub-brand">
                                    <span class="mission-hub-icon"><i class="fas fa-rocket"></i></span>
                                    <div><strong>BoostHub</strong><span>Quick earning missions</span></div>
                                </div>
                                <span class="mission-status-badge mission-status-badge--<?php echo htmlspecialchars($boost_mode, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo $boost_mode === 'returned' ? 'Fix needed' : ($boost_mode === 'open' ? 'Ready now' : ($boost_mode === 'submitted' ? 'In review' : ($boost_mode === 'locked' ? 'Cooling down' : 'Up to date'))); ?>
                                </span>
                            </div>
                            <div class="boost-mission-spotlight boost-mission-spotlight--<?php echo htmlspecialchars($boost_mode, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($boost_task): ?>
                                    <div class="boost-mission-topline">
                                        <span><i class="fas <?php echo $boost_mode === 'returned' ? 'fa-rotate-left' : ($boost_mode === 'submitted' ? 'fa-clock' : 'fa-bolt'); ?>"></i> <?php echo $boost_mode === 'returned' ? 'Correction requested' : ($boost_mode === 'submitted' ? 'Evidence submitted' : 'Assigned to you'); ?></span>
                                        <span class="mission-reward">+<?php echo number_format((float) ($boost_task['reward'] ?? 0), 2); ?> $REX</span>
                                    </div>
                                    <h4><?php echo htmlspecialchars((string) ($boost_task['title'] ?? 'BoostHub task'), ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <p><?php echo htmlspecialchars((string) ($boost_task['description'] ?? 'Complete this quick task and submit your evidence.'), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <div class="boost-mission-meta"><span><i class="fas fa-tag"></i> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($boost_task['task_category'] ?? 'Community'))), ENT_QUOTES, 'UTF-8'); ?></span></div>
                                    <?php if ($boost_mode !== 'submitted'): ?>
                                        <a class="boost-mission-cta" href="<?php echo BASE_URL; ?>/public/boosthub.php"><?php echo $boost_mode === 'returned' ? 'Fix submission' : 'Start mission'; ?> <i class="fas fa-arrow-right"></i></a>
                                    <?php else: ?>
                                        <div class="boost-review-note"><i class="fas fa-shield-alt"></i><span>Your proof is being reviewed. Check BoostHub for updates.</span></div>
                                    <?php endif; ?>
                                <?php elseif ($boost_mode === 'locked'): ?>
                                    <span class="boost-lock-icon"><i class="fas fa-hourglass-half"></i></span>
                                    <span class="boost-lock-eyebrow">Next mission unlocks in</span>
                                    <strong class="boost-countdown" data-mission-countdown="<?php echo (int) ($dashboard_boost_state['countdown_seconds'] ?? 0); ?>">--h --m --s</strong>
                                    <p>Come back when the cooldown ends to keep earning.</p>
                                <?php else: ?>
                                    <span class="boost-lock-icon boost-lock-icon--done"><i class="fas fa-trophy"></i></span>
                                    <strong>All available boosts completed</strong>
                                    <p>You're caught up. We'll show your next opportunity here.</p>
                                <?php endif; ?>
                            </div>
                            <a class="mission-hub-footer" href="<?php echo BASE_URL; ?>/public/boosthub.php"><span>Open BoostHub</span><i class="fas fa-arrow-right"></i></a>
                        </article>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- ===== WALLET CARD ===== -->
        <?php
        $wallet_address_display = strtolower(trim((string) ($user['wallet_address'] ?? '')));
        $wallet_linked = $wallet_address_display !== '' && preg_match('/^0x[a-f0-9]{40}$/', $wallet_address_display);
        ?>
        <section class="wallet-card <?php echo $wallet_linked ? 'is-linked' : ''; ?>" aria-label="Wallet status">
            <div class="wallet-card-main">
                <span class="wallet-card-icon">
                    <?php if ($wallet_linked): ?>
                        <i class="fas fa-wallet"></i>
                    <?php else: ?>
                        <i class="fas fa-plug"></i>
                    <?php endif; ?>
                </span>
                <div class="wallet-card-copy">
                    <span class="wallet-card-label">Wallet Address</span>
                    <?php if ($wallet_linked): ?>
                        <div class="wallet-card-address">
                            <code><?php echo htmlspecialchars($wallet_address_display, ENT_QUOTES, 'UTF-8'); ?></code>
                            <button type="button" class="wallet-card-address-copy" data-copy-text="<?php echo htmlspecialchars($wallet_address_display, ENT_QUOTES, 'UTF-8'); ?>" title="Copy wallet address" aria-label="Copy wallet address">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    <?php else: ?>
                        <span class="wallet-card-empty-text">Not linked yet</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="wallet-card-side">
                <span class="wallet-card-badge">
                    <?php if ($wallet_linked): ?>
                        <i class="fas fa-circle-check"></i> Linked
                    <?php else: ?>
                        <i class="fas fa-clock"></i> Not Linked
                    <?php endif; ?>
                </span>
                <?php if ($wallet_linked): ?>
                    <a href="<?php echo BASE_URL; ?>/public/link-wallet.php" class="wallet-card-btn wallet-card-btn--reset">
                        <i class="fas fa-rotate-left"></i>
                        Reset Wallet
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/public/link-wallet.php" class="wallet-card-btn">
                        <i class="fas fa-link"></i>
                        Link Wallet
                    </a>
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
        $learnhub_completion_message = 'You have Completed the LearnHub Missions';
        $airdrop_next_step = $pro_mission_complete
            ? $learnhub_completion_message
            : ($airdrop_days_to_finish > 0
            ? ($airdrop_days_to_finish . ' LearnHub day' . ($airdrop_days_to_finish === 1 ? '' : 's') . ' left')
            : 'Reach PRO Level');
        $is_pro = $has_pro_access;
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
                        <span class="airdrop-premium-info-text">
                            <?php if ($pro_mission_complete): ?>
                                <strong><?php echo htmlspecialchars($learnhub_completion_message, ENT_QUOTES, 'UTF-8'); ?></strong>. Reach <strong>PRO Level</strong> to unlock your <strong><?php echo number_format($pending_airdrop, 0); ?> $REX</strong> airdrop.
                            <?php else: ?>
                                Complete <strong><?php echo $airdrop_total_days; ?> LearnHub days</strong> to automatically receive <strong>PRO Level</strong>. Then your <strong><?php echo number_format($pending_airdrop, 0); ?> $REX</strong> unlocks automatically.
                            <?php endif; ?>
                        </span>
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
                                <span class="airdrop-premium-referral-link"><?php echo htmlspecialchars($user_referral_link, ENT_QUOTES, 'UTF-8'); ?></span>
                                <button type="button" class="airdrop-premium-referral-copy" data-copy-text="<?php echo htmlspecialchars($user_referral_link, ENT_QUOTES, 'UTF-8'); ?>" title="Copy referral link">
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

                <?php if ($pro_mission_complete): ?>
                    <span class="airdrop-premium-cta airdrop-premium-cta--disabled" aria-disabled="true">
                        <span><?php echo htmlspecialchars($learnhub_completion_message, ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/public/taskhub.php" class="airdrop-premium-cta">
                        <span>Go to LearnHub</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </a>
                <?php endif; ?>
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
            <div class="stat-card stat-card--rexrank">
                <span class="stat-card-icon">RR</span>
                <strong class="stat-card-value"><?php echo number_format((float) ($rexrank_stats['balance'] ?? 0), 0); ?></strong>
                <span class="stat-card-label">RexRank</span>
                <span class="stat-card-subline"><?php echo number_format((float) ($rexrank_stats['convertible_rr'] ?? 0), 0); ?> convertible · <?php echo (int) ($rexrank_stats['daily_votes'] ?? 0); ?>/<?php echo (int) ($rexrank_stats['daily_vote_limit'] ?? 10); ?> votes</span>
                <form method="POST" class="dashboard-rr-convert">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(appCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="convert_rexrank">
                    <input type="number" name="amount_rr" min="10" step="10" max="<?php echo (int) ($rexrank_stats['convertible_rr'] ?? 0); ?>" value="10" <?php echo (float) ($rexrank_stats['convertible_rr'] ?? 0) < 10 ? 'disabled' : ''; ?>>
                    <button type="submit" <?php echo (float) ($rexrank_stats['convertible_rr'] ?? 0) < 10 ? 'disabled' : ''; ?>>Convert</button>
                </form>
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
                        <a href="<?php echo BASE_URL; ?>/public/my-reviews.php" class="action-icon-btn" title="My Reviews">
                            <span class="action-icon-btn-icon">RR</span>
                            <span class="action-icon-btn-label">My Reviews</span>
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
                    <?php if ($show_learnhub_feature && !$has_pro_access && !$pro_mission_complete): ?>
                    <a href="<?php echo BASE_URL; ?>/public/taskhub.php" class="action-icon-btn" title="LearnHub">
                        <span class="action-icon-btn-icon">✅</span>
                        <span class="action-icon-btn-label">LearnHub</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($show_leaderboard_nav): ?>
                    <a href="<?php echo BASE_URL; ?>/public/leaderboard.php" class="action-icon-btn" title="Leaderboard">
                        <span class="action-icon-btn-icon">🏆</span>
                        <span class="action-icon-btn-label">Leaders</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($show_boosthub_feature): ?>
                    <a href="<?php echo BASE_URL; ?>/public/boosthub.php" class="action-icon-btn" title="BoostHub">
                        <span class="action-icon-btn-icon">🚀</span>
                        <span class="action-icon-btn-label">Boost</span>
                    </a>
                    <?php endif; ?>
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
                            ? 'Your LearnHub mission is complete. PRO promotion will sync automatically.'
                            : 'Complete LearnHub Day 10 to unlock ' . htmlspecialchars($level_card_target, ENT_QUOTES, 'UTF-8') . ' automatically.'; ?>
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
                                    </div>
                                    <p><?php echo htmlspecialchars((string) $requirement['meta'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <small><?php echo htmlspecialchars((string) $requirement['helper'], ENT_QUOTES, 'UTF-8'); ?></small>
                                </div>
                                <div class="pro-requirement-side">
                                    <span class="pro-requirement-status"><?php echo htmlspecialchars($req_label, ENT_QUOTES, 'UTF-8'); ?></span>
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
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="level-next-goal <?php echo $pro_ready ? 'level-next-goal--done' : ''; ?>">
                        <span class="level-next-goal-arrow"><i class="<?php echo $pro_ready ? 'fas fa-check' : 'fas fa-arrow-right'; ?>"></i></span>
                        <span><?php echo $pro_ready ? 'LearnHub complete - promoting soon' : htmlspecialchars((string) ($next_goal_text ?: 'Finish the LearnHub mission to unlock ' . $level_card_target), ENT_QUOTES, 'UTF-8'); ?></span>
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

        <!-- ===== MY REFERRALS ===== -->
        <section class="card referral-card">
            <div class="section-head">
                <h3>My Referrals</h3>
                <span class="referral-total-badge"><?php echo (int) $referral_metrics['total']; ?></span>
            </div>

            <div class="referral-stats-row">
                <span class="referral-stat-pill referral-stat-pill--valid">
                    <span class="referral-stat-count"><?php echo (int) $referral_metrics['valid']; ?></span>
                    <span class="referral-stat-label">Valid</span>
                </span>
                <span class="referral-stat-pill referral-stat-pill--pending">
                    <span class="referral-stat-count"><?php echo (int) $referral_metrics['pending']; ?></span>
                    <span class="referral-stat-label">Pending</span>
                </span>
                <span class="referral-stat-pill referral-stat-pill--invalid">
                    <span class="referral-stat-count"><?php echo (int) $referral_metrics['invalid']; ?></span>
                    <span class="referral-stat-label">Invalid</span>
                </span>
            </div>

            <div class="referral-link-box">
                <span class="referral-link-label">Your Referral Link</span>
                <div class="referral-link-row">
                    <code class="referral-link-code"><?php echo htmlspecialchars($user_referral_link, ENT_QUOTES, 'UTF-8'); ?></code>
                    <button type="button" class="referral-link-copy" data-copy-text="<?php echo htmlspecialchars($user_referral_link, ENT_QUOTES, 'UTF-8'); ?>" title="Copy referral link">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </button>
                </div>
            </div>

            <?php if (!empty($my_referrals)): ?>
                <div class="referral-list">
                    <?php foreach ($my_referrals as $ref): ?>
                        <?php
                        $ref_status = strtolower(trim((string) ($ref['referral_review_status'] ?? 'pending')));
                        $ref_label = getReferralReviewStatusLabel($ref_status);
                        $ref_class = getReferralReviewStatusClass($ref_status);
                        $ref_name = trim((string) ($ref['full_name'] ?: $ref['username'] ?: 'User'));
                        $ref_joined = date('M d, Y', strtotime((string) $ref['created_at']));
                        $ref_taskhub_days = (int) getCompletedTaskHubDaysCount((int) $ref['id'], $db);
                        $ref_progress_pct = min(100, round(($ref_taskhub_days / 4) * 100));
                        ?>
                        <div class="referral-row">
                            <div class="referral-row-main">
                                <div class="referral-row-avatar">
                                    <?php echo strtoupper(substr($ref_name, 0, 1)); ?>
                                </div>
                                <div class="referral-row-info">
                                    <span class="referral-row-name"><?php echo htmlspecialchars($ref_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="referral-row-date">Joined <?php echo htmlspecialchars($ref_joined, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </div>
                            <div class="referral-row-progress">
                                <span class="referral-row-progress-text"><?php echo $ref_taskhub_days; ?>/4 days</span>
                                <div class="referral-row-progress-bar">
                                    <span style="width: <?php echo $ref_progress_pct; ?>%"></span>
                                </div>
                            </div>
                            <span class="referral-status-pill <?php echo htmlspecialchars($ref_class, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($ref_label, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="referral-empty">
                    <span class="referral-empty-icon">👥</span>
                    <p>No referrals yet. Share your link to invite friends!</p>
                </div>
            <?php endif; ?>
        </section>

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
window.coinrexEngagement=<?php echo json_encode(['csrf'=>appCsrfToken(),'announcementId'=>(int)($engagement_announcement['id']??0),'assignmentId'=>(int)($social_assignment['id']??0),'ctaAlreadyClicked'=>!empty($social_assignment['cta_clicked_at']),'ctaEndpoint'=>BASE_URL.'/api/social-engagement/cta.php','evidenceEndpoint'=>BASE_URL.'/api/social-engagement/evidence.php','dismissEndpoint'=>BASE_URL.'/api/social-engagement/dismiss-announcement.php'],JSON_UNESCAPED_SLASHES); ?>;
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

document.querySelectorAll('.wallet-card-address-copy[data-copy-text]').forEach((button) => {
    button.addEventListener('click', async function() {
        const text = this.dataset.copyText || '';
        if (!text) return;
        const originalHtml = this.innerHTML;
        try {
            const copied = await copyTextToClipboard(text);
            this.innerHTML = copied ? '<i class="fas fa-check"></i>' : '<i class="fas fa-triangle-exclamation"></i>';
            this.classList.toggle('is-copied', copied);
        } catch (error) {
            this.innerHTML = '<i class="fas fa-triangle-exclamation"></i>';
        }
        window.setTimeout(() => {
            this.innerHTML = originalHtml;
            this.classList.remove('is-copied');
        }, 1500);
    });
});

document.querySelectorAll('[data-copy-text]:not(.pro-requirement-action):not(.wallet-card-address-copy)').forEach((button) => {
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

document.querySelectorAll('[data-dashboard-checkin]').forEach((button) => {
    button.addEventListener('click', async function() {
        const taskKey = this.dataset.dashboardCheckin || '';
        if (!taskKey || this.disabled) return;

        const originalHtml = this.innerHTML;
        this.disabled = true;
        this.setAttribute('aria-busy', 'true');
        this.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Securing...';

        try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/submit_taskhub_task.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: new URLSearchParams({ task_key: taskKey }),
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Check-in could not be completed.');
            }
            this.innerHTML = '<i class="fas fa-check"></i> Streak secured';
            window.setTimeout(() => window.location.reload(), 650);
        } catch (error) {
            this.disabled = false;
            this.removeAttribute('aria-busy');
            this.innerHTML = originalHtml;
            window.alert(error.message || 'Check-in failed. Please try again.');
        }
    });
});

document.querySelectorAll('[data-mission-countdown]').forEach((element) => {
    let remaining = Math.max(0, Number(element.dataset.missionCountdown) || 0);
    const render = () => {
        const hours = Math.floor(remaining / 3600);
        const minutes = Math.floor((remaining % 3600) / 60);
        const seconds = remaining % 60;
        element.textContent = String(hours).padStart(2, '0') + 'h ' + String(minutes).padStart(2, '0') + 'm ' + String(seconds).padStart(2, '0') + 's';
        if (remaining <= 0) return false;
        remaining--;
        return true;
    };
    render();
    const timer = window.setInterval(() => {
        if (!render()) {
            window.clearInterval(timer);
            window.setTimeout(() => window.location.reload(), 800);
        }
    }, 1000);
});
</script>
<?php if ($pro_weekly_streak_state !== null): ?>
<script src="<?php echo ASSETS_URL; ?>/js/pro-weekly-streak.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/pro-weekly-streak.js'); ?>"></script>
<?php endif; ?>
<script src="<?php echo ASSETS_URL; ?>/js/engagement.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/engagement.js'); ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
