<?php
ob_start();

// Prevent browser caching so countdownSeconds is always fresh
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireFeatureAccess('boosthub');

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

$db = getDBConnection();
ensureRewardClaimSchema($db);

$user = getCurrentUser();
$user_id = (int) $user['id'];

// ── Get BoostHub state ──
$boost_state = getBoostHubStateForUser($user_id, $db);
$boost_task = $boost_state['task'] ?? null;
$campaign_context = null;
if (!empty($boost_task['campaign'])) {
    $campaign_context = $boost_task['campaign'];
} elseif (!empty($boost_task['campaign_id'])) {
    $campaign_context = boostHubCampaignGet((int) $boost_task['campaign_id'], $db);
}
$status = $boost_state['status'] ?? 'closed';
$learnhub_completed = taskHubMissionCompleted($user_id, $db);

// ── Pending / Submitted tasks (non-blocking) ──
$pending_task = $boost_state['pending_task'] ?? null;   // returned for correction
$submitted_task = $boost_state['submitted_task'] ?? null; // awaiting review
$has_pending_review = !empty($boost_state['has_pending_review']);
$has_returned_task = !empty($boost_state['has_returned_task']);
$can_skip_task = !empty($boost_state['can_skip']);
$skip_remaining = (int) ($boost_state['skip_remaining'] ?? 0);

// ── Determine if user can claim ──
$can_claim = ($status === 'open' && !empty($boost_task));
$boost_task_metadata = !empty($boost_task['metadata']) ? (json_decode((string) $boost_task['metadata'], true) ?: []) : [];
$boost_correction_note = !empty($boost_task_metadata['correction_requested'])
    ? (string) ($boost_task_metadata['correction_note'] ?? 'Please update your evidence so the admin team can verify it.')
    : '';
$boost_previous_evidence = (string) ($boost_task['proof_data'] ?? '');

// ── Parse previous evidence for pre-fill ──
$prev_evidence_text = '';
$prev_screenshot_url = '';
if ($boost_previous_evidence !== '') {
    $parsed = json_decode($boost_previous_evidence, true);
    if (is_array($parsed)) {
        $prev_evidence_text = (string) ($parsed['text'] ?? '');
        $prev_screenshot_url = (string) ($parsed['screenshot'] ?? '');
    } else {
        $prev_evidence_text = $boost_previous_evidence;
    }
}

// ── Get last 3 days history ──
$history = [];
try {
    $hist_stmt = $db->prepare("
        SELECT 
            utl.id AS log_id,
            utl.status AS log_status,
            utl.proof_data,
            utl.metadata,
            utl.task_completed_at,
            utl.completed_at,
            mt.title AS task_title,
            mt.reward,
            mt.task_category
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.user_id = ?
          AND mt.task_group = 'boosthub'
          AND utl.status IN ('submitted', 'completed', 'failed')
          AND (
              utl.metadata IS NULL
              OR (
                  utl.metadata NOT LIKE '%\"skipped\":true%'
                  AND utl.metadata NOT LIKE '%\"skipped\": true%'
              )
          )
        ORDER BY COALESCE(utl.task_completed_at, utl.completed_at) DESC
        LIMIT 3
    ");
    $hist_stmt->execute([$user_id]);
    $history = $hist_stmt->fetchAll();
} catch (Exception $e) {
    $history = [];
}

// ── Stats ──
$total_done = 0;
$approved_count = 0;
try {
    $stats_stmt = $db->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS approved
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.user_id = ?
          AND mt.task_group = 'boosthub'
          AND utl.status IN ('completed', 'submitted', 'failed')
          AND (
              utl.metadata IS NULL
              OR (
                  utl.metadata NOT LIKE '%\"skipped\":true%'
                  AND utl.metadata NOT LIKE '%\"skipped\": true%'
              )
          )
    ");
    $stats_stmt->execute([$user_id]);
    $stats_row = $stats_stmt->fetch();
    $total_done = (int) ($stats_row['total'] ?? 0);
    $approved_count = (int) ($stats_row['approved'] ?? 0);
} catch (Exception $e) {
    $total_done = 0;
    $approved_count = 0;
}
$approval_rate = $total_done > 0 ? round(($approved_count / $total_done) * 100) : 0;
$partner_campaigns = [];
try {
    $partner_campaigns = boostHubPublicCampaignsForUser($user_id, $db);
} catch (Throwable $e) {
    error_log('BoostHub public campaigns unavailable: ' . $e->getMessage());
}
$current_boost_task_id = (int) ($boost_task['task_id'] ?? $boost_task['id'] ?? 0);

function boostHubRenderPublicCampaignTasks(array $campaign, bool $campaign_open, string $boost_status, int $current_task_id): string {
    ob_start();
    if (!$campaign['tasks']): ?>
        <div class='bh-campaign-task-empty'><i class='fas fa-hourglass-half'></i> Tasks are being prepared.</div>
    <?php else:
        $task_idx = 0;
        foreach ($campaign['tasks'] as $task):
            $task_id = (int) $task['id'];
            $task_state = (string) $task['user_state'];
            $is_current = $task_id === $current_task_id;
            $task_idx++;
            // Map task state -> icon for quick scanning
            $task_icon = 'fa-play';
            if ($task_state === 'completed') { $task_icon = 'fa-circle-check'; }
            elseif ($task_state === 'under_review') { $task_icon = 'fa-hourglass-half'; }
            elseif ($task_state === 'correction') { $task_icon = 'fa-rotate-left'; }
            elseif ($task_state === 'assigned') { $task_icon = 'fa-bolt'; }
            elseif (!$campaign_open) { $task_icon = 'fa-lock'; }
            elseif ($boost_status === 'locked') { $task_icon = 'fa-hourglass'; }
    ?>
        <div class='bh-campaign-task<?php echo $is_current ? ' is-active-task' : ''; ?>' data-task-state='<?php echo htmlspecialchars($task_state, ENT_QUOTES, 'UTF-8'); ?>'>
            <span class='bh-campaign-task-num'><i class='fas <?php echo $task_icon; ?>'></i><b><?php echo $task_idx; ?></b></span>
            <div class='bh-campaign-task-info'>
                <span class='bh-campaign-task-type'><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $task['task_category'])), ENT_QUOTES, 'UTF-8'); ?></span>
                <strong><?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                <small><?php echo htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
            <div class='bh-campaign-task-action'>
                <span class='bh-campaign-task-reward'><i class='fas fa-coins'></i> +<?php echo number_format((float) $task['reward'], 2); ?> $REX</span>
                <?php if ($task_state === 'completed'): ?>
                    <span class='bh-campaign-task-result is-complete'><i class='fas fa-check'></i> Completed</span>
                <?php elseif ($task_state === 'under_review'): ?>
                    <span class='bh-campaign-task-result'><i class='fas fa-clock'></i> Under review</span>
                <?php elseif ($task_state === 'correction'): ?>
                    <span class='bh-campaign-task-result is-warning'><i class='fas fa-pen'></i> Update evidence above</span>
                <?php elseif (!$campaign_open): ?>
                    <span class='bh-campaign-task-result'><i class='fas fa-lock'></i> <?php echo htmlspecialchars(ucfirst($campaign['effective_state']), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php elseif ($boost_status === 'locked'): ?>
                    <span class='bh-campaign-task-result'><i class='fas fa-hourglass'></i> Cooldown active</span>
                <?php elseif ($is_current || $task_state === 'assigned'): ?>
                    <button type='button' class='bh-campaign-task-btn' data-campaign-continue><i class='fas fa-play'></i> Continue Task</button>
                <?php else: ?>
                    <button type='button' class='bh-campaign-task-btn' data-campaign-task-start='<?php echo $task_id; ?>'><i class='fas fa-play'></i> Start Task</button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; endif;
    return (string) ob_get_clean();
}

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/boosthub-premium.css?v=<?php echo defined('APP_VERSION') ? APP_VERSION : '20260903'; ?>">

<main class="boosthub-premium">
    <div class="boosthub-shell">

        <!-- Header -->
        <div class="bh-header">
            <div class="bh-header-left">
                <span class="bh-header-badge"><i class="fas fa-bolt"></i> BoostHub</span>
                <h1 id='boostHubViewTitle'>Daily Boost</h1>
            </div>
            <div class="bh-header-actions">
                <?php if (!$learnhub_completed): ?>
                    <a href="<?php echo BASE_URL; ?>/public/taskhub.php" class="bh-header-btn bh-header-btn--secondary"><i class="fas fa-graduation-cap"></i><span>LearnHub</span></a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/public/dashboard.php" class="bh-header-btn bh-header-btn--primary"><i class="fas fa-chart-simple"></i><span>Dashboard</span></a>
            </div>
        </div>

        <!-- ── PENDING REVIEW PANEL ── -->
        <nav class='bh-view-tabs' role='tablist' aria-label='BoostHub views'>
            <button type='button' class='bh-view-tab is-active' id='dailyBoostTab' role='tab' aria-selected='true' aria-controls='dailyBoostPanel' data-bh-view='daily'><i class='fas fa-bolt'></i><span>Daily Boost</span></button>
            <button type='button' class='bh-view-tab' id='partnerCampaignsTab' role='tab' aria-selected='false' aria-controls='partnerCampaignsPanel' data-bh-view='campaigns'><i class='fas fa-handshake'></i><span>Partner Campaigns</span><?php if ($partner_campaigns): ?><b><?php echo count($partner_campaigns); ?></b><?php endif; ?></button>
        </nav>

        <div class='bh-tab-panel' id='partnerCampaignsPanel' role='tabpanel' aria-labelledby='partnerCampaignsTab' data-bh-tab-panel='campaigns' hidden>
        <?php if ($partner_campaigns): ?>
        <section class='bh-campaigns bh-reveal' aria-labelledby='partnerCampaignsTitle'>
            <div class='bh-campaigns-head'>
                <div>
                    <span class='bh-campaigns-kicker'><i class='fas fa-handshake'></i> Sponsored opportunities</span>
                    <h2 id='partnerCampaignsTitle'>Partner Campaigns</h2>
                    <p>Choose a campaign task below. Evidence and rewards use the same trusted BoostHub process.</p>
                </div>
                <span class='bh-campaigns-count'><?php echo count($partner_campaigns); ?> available</span>
            </div>
<?php
                // ── Campaign section summary stats ──
                $stats_active = 0;
                $stats_reward_pool = 0.0;
                $stats_completed = 0;
                $stats_total = 0;
                $stats_slots = 0;
                foreach ($partner_campaigns as $sc) {
                    if (($sc['effective_state'] ?? '') === 'active') { $stats_active++; }
                    if (in_array(($sc['effective_state'] ?? ''), ['active', 'full'], true)) {
                        foreach (($sc['tasks'] ?? []) as $st) { $stats_reward_pool += (float) ($st['reward'] ?? 0); }
                    }
                    $stats_completed += (int) ($sc['progress_completed'] ?? 0);
                    $stats_total += (int) ($sc['progress_total'] ?? 0);
                    $stats_slots += (int) ($sc['remaining_slots'] ?? 0);
                }
            ?>
            <div class='bh-campaign-stats'>
                <div class='bh-campaign-stat-card'>
                    <span class='bh-campaign-stat-icon is-active'><i class='fas fa-fire'></i></span>
                    <div class='bh-campaign-stat-text'><strong><?php echo (int) $stats_active; ?></strong><span>Active campaigns</span></div>
                </div>
                <div class='bh-campaign-stat-card'>
                    <span class='bh-campaign-stat-icon is-gold'><i class='fas fa-coins'></i></span>
                    <div class='bh-campaign-stat-text'><strong><?php echo number_format($stats_reward_pool, 2); ?> $REX</strong><span>Reward pool</span></div>
                </div>
                <div class='bh-campaign-stat-card'>
                    <span class='bh-campaign-stat-icon is-blue'><i class='fas fa-list-check'></i></span>
                    <div class='bh-campaign-stat-text'><strong><?php echo (int) $stats_completed; ?> / <?php echo (int) $stats_total; ?></strong><span>Your completion</span></div>
                </div>
                <div class='bh-campaign-stat-card'>
                    <span class='bh-campaign-stat-icon is-users'><i class='fas fa-user-plus'></i></span>
                    <div class='bh-campaign-stat-text'><strong><?php echo (int) $stats_slots; ?></strong><span>Open slots</span></div>
                </div>
            </div>
            <div class='bh-campaign-list'>
            <?php foreach ($partner_campaigns as $campaign_idx => $campaign):
                $campaign_state = (string) $campaign['effective_state'];
                $campaign_open = $campaign_state === 'active';
                $campaign_id = (int) $campaign['id'];
                $campaign_has_cover = (string) ($campaign['project_cover'] ?? '') !== '';
                $campaign_progress = (int) ($campaign['progress_percent'] ?? 0);
                $campaign_expanded = false;
                // Map campaign state -> icon for the badge
                $campaign_state_icon = 'fa-circle-info';
                if ($campaign_state === 'active') { $campaign_state_icon = 'fa-bolt'; }
                elseif ($campaign_state === 'scheduled') { $campaign_state_icon = 'fa-calendar-day'; }
                elseif ($campaign_state === 'full') { $campaign_state_icon = 'fa-users'; }
                elseif ($campaign_state === 'paused') { $campaign_state_icon = 'fa-pause'; }
                elseif ($campaign_state === 'expired') { $campaign_state_icon = 'fa-hourglass-end'; }
            ?>
                <article class='bh-campaign-card is-foldable<?php echo $campaign_expanded ? ' is-expanded' : ''; ?> is-<?php echo htmlspecialchars($campaign_state, ENT_QUOTES, 'UTF-8'); ?><?php echo $campaign_has_cover ? ' has-cover' : ''; ?>' style='--i:<?php echo $campaign_idx; ?>' data-campaign-card>
                    <?php if ($campaign_has_cover): ?><div class='bh-campaign-cover'><img src='<?php echo htmlspecialchars($campaign['project_cover'], ENT_QUOTES, 'UTF-8'); ?>' alt='' loading='lazy'><div class='bh-campaign-cover-overlay'></div></div><?php endif; ?>
                    <div class='bh-campaign-card-body'>
                    <div class='bh-campaign-card-head'>
                        <div class='bh-campaign-project'>
                            <?php if ($campaign['project_logo'] !== ''): ?>
                                <img src='<?php echo htmlspecialchars($campaign['project_logo'], ENT_QUOTES, 'UTF-8'); ?>' alt='<?php echo htmlspecialchars($campaign['project_name'], ENT_QUOTES, 'UTF-8'); ?> logo' loading='lazy'>
                            <?php else: ?>
                                <span class='bh-campaign-logo-fallback'><i class='fas fa-building'></i></span>
                            <?php endif; ?>
                            <div class='bh-campaign-project-text'>
                                <strong class='bh-campaign-project-name'><?php echo htmlspecialchars($campaign['project_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class='bh-campaign-campaign-name'><?php echo htmlspecialchars($campaign['campaign_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                        <div class='bh-campaign-head-right'>
                            <?php
                                $countdown_ref = ($campaign_state === 'scheduled') ? $campaign['start_at'] : $campaign['end_at'];
                                $countdown_ts = boostHubCampaignTimestamp((string) $countdown_ref);
                                $countdown_remaining = max(0, $countdown_ts - time());
                            ?>
                            <?php if (in_array($campaign_state, ['active', 'scheduled'], true) && $countdown_remaining > 0): ?>
                            <div class='bh-campaign-countdown' data-campaign-countdown='<?php echo $countdown_remaining; ?>' data-campaign-countdown-target='<?php echo htmlspecialchars(boostHubCampaignClientDateTime((string) $countdown_ref), ENT_QUOTES, 'UTF-8'); ?>'>
                                <span class='bh-campaign-countdown-icon'><i class='fas fa-clock'></i></span>
                                <div class='bh-campaign-countdown-inner'>
                                    <span class='bh-campaign-countdown-text'><?php echo htmlspecialchars(taskHubFormatDuration($countdown_remaining), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class='bh-campaign-countdown-label'><?php echo $campaign_state === 'scheduled' ? 'until starts' : 'remaining'; ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <span class='bh-campaign-state is-<?php echo htmlspecialchars($campaign_state, ENT_QUOTES, 'UTF-8'); ?>'><i class='fas <?php echo $campaign_state_icon; ?>'></i><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $campaign_state)), ENT_QUOTES, 'UTF-8'); ?></span>
                            <button type='button' class='bh-campaign-toggle' data-campaign-toggle aria-expanded='<?php echo $campaign_expanded ? 'true' : 'false'; ?>'><i class='fas fa-chevron-down'></i><span><?php echo $campaign_expanded ? 'Hide details' : 'View details'; ?></span></button>
                        </div>
                    </div>
                    <div class='bh-campaign-collapsible' <?php echo $campaign_expanded ? '' : 'hidden'; ?>>
                    <?php if ($campaign['short_description'] !== ''): ?><p class='bh-campaign-description'><?php echo htmlspecialchars($campaign['short_description'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <div class='bh-campaign-meta'>
                        <span class='bh-campaign-meta-item'><i class='fas fa-calendar-days'></i> <?php echo $campaign_state === 'scheduled' ? 'Starts' : 'Ends'; ?> <time data-bh-local-datetime='<?php echo htmlspecialchars(boostHubCampaignClientDateTime((string) $countdown_ref), ENT_QUOTES, 'UTF-8'); ?>'><?php echo htmlspecialchars(date('M j, Y', $countdown_ts), ENT_QUOTES, 'UTF-8'); ?></time></span>
                        <span class='bh-campaign-meta-item'><i class='fas fa-users'></i> <?php echo (int) $campaign['remaining_slots']; ?> slots left</span>
                        <span class='bh-campaign-meta-item'><i class='fas fa-list-check'></i> <?php echo (int) ($campaign['progress_total'] ?? 0) > 0 ? (int) ($campaign['progress_completed'] ?? 0) . ' / ' . (int) ($campaign['progress_total'] ?? 0) . ' tasks' : count($campaign['tasks']) . ' tasks'; ?></span>
                        <?php if ($campaign['project_website'] !== ''): ?><a class='bh-campaign-meta-item' href='<?php echo htmlspecialchars($campaign['project_website'], ENT_QUOTES, 'UTF-8'); ?>' target='_blank' rel='noopener noreferrer'><i class='fas fa-arrow-up-right-from-square'></i> Visit project</a><?php endif; ?>
                    </div>
                    <?php
                        $capacity_max = (int) ($campaign['max_participants'] ?? 0);
                        $capacity_used = (int) ($campaign['approved_participants'] ?? 0);
                        $capacity_pct = $capacity_max > 0 ? min(100, (int) round(($capacity_used / $capacity_max) * 100)) : 0;
                        $capacity_full = $capacity_max > 0 && $capacity_used >= $capacity_max;
                        $capacity_low = !$capacity_full && $capacity_max > 0 && (($capacity_max - $capacity_used) <= max(1, (int) round($capacity_max * 0.2)));
                    ?>
                    <?php if ($capacity_max > 0): ?>
                    <div class='bh-campaign-capacity<?php echo $capacity_full ? ' is-full' : ($capacity_low ? ' is-low' : ''); ?>'>
                        <div class='bh-campaign-capacity-head'><span><i class='fas fa-users'></i> Participant capacity</span><strong><?php echo (int) $capacity_used; ?> / <?php echo (int) $capacity_max; ?> filled</strong></div>
                        <div class='bh-capacity-track'><span class='bh-capacity-fill' style='width:<?php echo $capacity_pct; ?>%'></span></div>
                    </div>
                    <?php endif; ?>
                    <div class='bh-campaign-progress<?php echo $campaign_progress >= 100 ? ' is-done' : ''; ?>' aria-label='<?php echo $campaign_progress; ?> percent complete'>
                        <div class='bh-campaign-progress-head'>
                            <span>Your task progress</span>
                            <strong><?php if ($campaign_progress >= 100): ?><i class='fas fa-circle-check'></i><?php endif; ?><?php echo (int) ($campaign['progress_completed'] ?? 0); ?> / <?php echo (int) ($campaign['progress_total'] ?? 0); ?> complete<?php if ((int) ($campaign['progress_total'] ?? 0) > 0): ?><em><?php echo $campaign_progress; ?>%</em><?php endif; ?></strong>
                        </div>
                        <div class='bh-campaign-progress-track'><span class='bh-campaign-progress-fill' style='width:<?php echo $campaign_progress; ?>%'></span></div>
                    </div>
                    <div class='bh-campaign-tasks' id='campaignTasks<?php echo $campaign_id; ?>'>
                        <?php echo boostHubRenderPublicCampaignTasks($campaign, $campaign_open, $status, $current_boost_task_id); ?>
                    </div>
                    </div>
                    </div>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
        <?php else: ?>
        <section class='bh-campaigns bh-campaigns-empty'>
            <div class='bh-campaigns-empty-art'><i class='fas fa-handshake'></i><i class='fas fa-bolt'></i></div>
            <h2>No partner campaigns right now</h2>
            <p>New sponsored opportunities will appear here when they become available. BoostHub rewards keep flowing in the Daily Boost tab meanwhile.</p>
            <a href="#daily" class='bh-campaigns-empty-cta' data-bh-view='daily'><i class="fas fa-bolt"></i> Go to Daily Boost</a>
        </section>
        <?php endif; ?>
        </div>

        <div class='bh-tab-panel is-active' id='dailyBoostPanel' role='tabpanel' aria-labelledby='dailyBoostTab' data-bh-tab-panel='daily'>
        <?php if ($has_pending_review || $has_returned_task): ?>
        <section class="bh-panel bh-pending-panel bh-reveal">
            <div class="bh-pending-header">
                <span class="bh-pending-badge"><i class="fas fa-clock"></i> Pending Tasks</span>
                <span class="bh-pending-count"><?php echo ($has_pending_review ? 1 : 0) + ($has_returned_task ? 1 : 0); ?> active</span>
            </div>

            <?php if ($submitted_task):
                $submitted_meta = !empty($submitted_task['metadata']) ? (is_string($submitted_task['metadata']) ? json_decode($submitted_task['metadata'], true) : $submitted_task['metadata']) : [];
                $submitted_at = !empty($submitted_meta['submitted_at']) ? $submitted_meta['submitted_at'] : '';
                $submitted_title = htmlspecialchars((string) ($submitted_task['title'] ?? 'Task'), ENT_QUOTES, 'UTF-8');
                $submitted_category = htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($submitted_task['task_category'] ?? 'Social Task'))), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="bh-pending-item">
                <div class="bh-pending-item-icon is-pending"><i class="fas fa-hourglass-half"></i></div>
                <div class="bh-pending-item-info">
                    <strong><?php echo $submitted_title; ?></strong>
                    <span class="bh-pending-item-meta">
                        <span class="bh-pending-category"><?php echo $submitted_category; ?></span>
                        <?php if ($submitted_at): ?>
                            <span class="bh-pending-date">Submitted <?php echo date('M j, g:i A', strtotime($submitted_at)); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <span class="bh-history-status is-pending">⏳ Under Review</span>
            </div>
            <?php endif; ?>

            <?php if ($pending_task):
                $pending_meta = !empty($pending_task['metadata']) ? (is_string($pending_task['metadata']) ? json_decode($pending_task['metadata'], true) : $pending_task['metadata']) : [];
                $correction_note = !empty($pending_meta['correction_note']) ? htmlspecialchars((string) $pending_meta['correction_note'], ENT_QUOTES, 'UTF-8') : 'Please update your evidence.';
                $pending_title = htmlspecialchars((string) ($pending_task['title'] ?? 'Task'), ENT_QUOTES, 'UTF-8');
                $pending_category = htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($pending_task['task_category'] ?? 'Social Task'))), ENT_QUOTES, 'UTF-8');
                $pending_reward = (float) ($pending_task['reward'] ?? 0);
                $pending_task_id = (int) ($pending_task['task_id'] ?? 0);
                $pending_log_id = (int) ($pending_task['id'] ?? 0);

                // Parse previous evidence for pre-fill
                $pending_prev_text = '';
                $pending_prev_screenshot = '';
                $pending_proof = (string) ($pending_task['proof_data'] ?? '');
                if ($pending_proof !== '') {
                    $parsed = json_decode($pending_proof, true);
                    if (is_array($parsed)) {
                        $pending_prev_text = (string) ($parsed['text'] ?? '');
                        $pending_prev_screenshot = (string) ($parsed['screenshot'] ?? '');
                    } else {
                        $pending_prev_text = $pending_proof;
                    }
                }
            ?>
            <div class="bh-pending-item is-correction">
                <div class="bh-pending-item-icon is-correction"><i class="fas fa-rotate-left"></i></div>
                <div class="bh-pending-item-info">
                    <strong><?php echo $pending_title; ?></strong>
                    <span class="bh-pending-item-meta">
                        <span class="bh-pending-category"><?php echo $pending_category; ?></span>
                        <span class="bh-pending-reward">+<?php echo number_format($pending_reward, 2); ?> $REX</span>
                    </span>
                    <p class="bh-pending-correction-note"><?php echo $correction_note; ?></p>
                </div>
                <button type="button" class="bh-pending-resubmit-btn"
                    data-task-id="<?php echo $pending_task_id; ?>"
                    data-log-id="<?php echo $pending_log_id; ?>"
                    data-title="<?php echo $pending_title; ?>"
                    data-category="<?php echo $pending_category; ?>"
                    data-reward="<?php echo $pending_reward; ?>"
                    data-prev-text="<?php echo htmlspecialchars($pending_prev_text, ENT_QUOTES, 'UTF-8'); ?>"
                    data-prev-screenshot="<?php echo htmlspecialchars($pending_prev_screenshot, ENT_QUOTES, 'UTF-8'); ?>"
                    data-correction-note="<?php echo htmlspecialchars($correction_note, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-pen"></i> Update Evidence
                </button>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- Hero Card -->
        <section class="bh-hero">

            <?php if ($can_claim): ?>
                <!-- === CLAIM AVAILABLE === -->
                <div class="bh-claim-area">
                    <button type="button" class="bh-claim-btn" id="claimNowBtn">
                        <i class="fas fa-bolt"></i> <?php echo $boost_correction_note !== '' ? 'Update Evidence' : 'Claim Now'; ?>
                    </button>
                    <button
                        type="button"
                        class="bh-skip-btn"
                        id="skipTaskBtn"
                        <?php echo $can_skip_task ? '' : 'disabled'; ?>
                        title="<?php echo $can_skip_task ? 'Skip to the next unfinished task in your cycle' : 'No other unfinished BoostHub task is available in your cycle'; ?>"
                    >
                        <i class="fas fa-forward"></i> Skip to Next Task
                    </button>
                    <p class="bh-claim-sub"><?php echo $boost_correction_note !== '' ? 'Evidence update requested' : '1 social task available'; ?></p>
                    <p class="bh-skip-sub">
                        <?php echo $can_skip_task
                            ? htmlspecialchars((string) ($skip_remaining . ' unfinished task' . ($skip_remaining === 1 ? '' : 's') . ' remaining in your cycle after this one.'), ENT_QUOTES, 'UTF-8')
                            : 'Skip unavailable because no other unfinished BoostHub task is available right now.'; ?>
                    </p>
                </div>

            <?php elseif ($status === 'locked'): ?>
                <!-- === COUNTDOWN / COOLDOWN === -->
                <div class="bh-claimed">
                    <div class="bh-claimed-icon">⏳</div>
                    <h2>Next Task Unlocks Soon</h2>
                    <p>Your next BoostHub task will be available once the cooldown ends.</p>

                    <div class="bh-countdown">
                        <span class="bh-countdown-icon">⏳</span>
                        <div>
                            <span class="bh-countdown-text" id="countdownDisplay">
                                <?php echo htmlspecialchars(taskHubFormatDuration((int) $boost_state['countdown_seconds']), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <span class="bh-countdown-label">until next claim</span>
                        </div>
                    </div>
                    <div class="bh-progress-wrap">
                        <div class="bh-progress-track">
                            <span class="bh-progress-fill" id="countdownProgress" style="width:<?php echo $boost_state['countdown_seconds'] > 0 ? '50' : '100'; ?>%;"></span>
                        </div>
                    </div>

                </div>

            <?php elseif ($status === 'finished'): ?>
                <!-- === ALL TASKS COMPLETED === -->
                <div class="bh-claimed">
                    <div class="bh-claimed-icon">🏆</div>
                    <h2>All Tasks Completed</h2>
                    <p><?php echo htmlspecialchars((string) ($boost_state['message'] ?? 'No new BoostHub tasks available right now.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

            <?php else: ?>
                <!-- === CLOSED / OTHER === -->
                <div class="bh-claimed">
                    <div class="bh-claimed-icon">🔒</div>
                    <h2>Not Available</h2>
                    <p><?php echo htmlspecialchars((string) ($boost_state['message'] ?? 'BoostHub is not available for this account.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>

            <!-- Stats Row (always visible) -->
            <div class="bh-stats">
                <div class="bh-stat">
                    <span class="bh-stat-value"><?php echo (int) $total_done; ?></span>
                    <span class="bh-stat-label">Tasks Done</span>
                </div>
                <div class="bh-stat">
                    <span class="bh-stat-value"><?php echo (int) $approved_count; ?></span>
                    <span class="bh-stat-label">Approved</span>
                </div>
                <div class="bh-stat">
                    <span class="bh-stat-value"><?php echo (int) $approval_rate; ?>%</span>
                    <span class="bh-stat-label">Approval Rate</span>
                </div>
            </div>
        </section>

        <!-- History Section -->
        <section class="bh-panel bh-reveal">
            <h3 class="bh-history-title">Recent Activity</h3>
            <p class="bh-history-sub">Your last 3 task submissions and their status.</p>

            <?php if (empty($history)): ?>
                <div class="bh-history-empty">
                    <p>No activity yet. Claim your first task to get started!</p>
                </div>
            <?php else: ?>
                <div class="bh-history-list">
                    <?php foreach ($history as $entry): 
                        $log_status = (string) ($entry['log_status'] ?? '');
                        $task_title = htmlspecialchars((string) ($entry['task_title'] ?? 'Task'), ENT_QUOTES, 'UTF-8');
                        $reward = (float) ($entry['reward'] ?? 0);
                        $completed_at = (string) ($entry['task_completed_at'] ?? $entry['completed_at'] ?? '');
                        $date_display = date('M j', strtotime($completed_at));

                        // Determine status display
                        if ($log_status === 'completed') {
                            $status_label = 'Approved';
                            $status_class = 'is-approved';
                            $status_icon = '✅';
                        } elseif ($log_status === 'failed') {
                            $metadata = !empty($entry['metadata']) ? (is_string($entry['metadata']) ? json_decode($entry['metadata'], true) : $entry['metadata']) : [];
                            $is_correction = !empty($metadata['correction_requested']);
                            $status_label = $is_correction ? 'Needs Update' : 'Rejected';
                            $status_class = 'is-rejected';
                            $status_icon = '❌';
                            $rejection_reason = !empty($metadata['correction_note'])
                                ? htmlspecialchars((string) $metadata['correction_note'], ENT_QUOTES, 'UTF-8')
                                : (!empty($metadata['rejection_reason']) ? htmlspecialchars((string) $metadata['rejection_reason'], ENT_QUOTES, 'UTF-8') : '');
                        } else {
                            $status_label = 'Under Review';
                            $status_class = 'is-pending';
                            $status_icon = '⏳';
                        }
                    ?>
                        <div class="bh-history-item">
                            <span class="bh-history-date"><?php echo $date_display; ?></span>
                            <div class="bh-history-info">
                                <strong><?php echo $task_title; ?></strong>
                                <?php if ($log_status === 'failed' && !empty($rejection_reason)): ?>
                                    <span><?php echo $rejection_reason; ?></span>
                                <?php else: ?>
                                    <span><?php echo ucfirst($log_status); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($log_status === 'completed'): ?>
                                <span class="bh-history-reward">+<?php echo number_format($reward, 2); ?> $REX</span>
                            <?php endif; ?>
                            <span class="bh-history-status <?php echo $status_class; ?>">
                                <?php echo $status_icon; ?> <?php echo $status_label; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        </div>

    </div>
</main>

<!-- Claim Modal -->
<div class="bh-modal" id="claimModal" hidden>
    <div class="bh-modal-backdrop" data-modal-close></div>
    <div class="bh-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="claimModalTitle">
        <div class="bh-modal-head">
            <div class="bh-modal-head-left">
                <span class="bh-modal-head-icon"><i class="fas fa-bolt"></i></span>
                <h3 id="claimModalTitle">Claim Reward</h3>
            </div>
            <button type="button" class="bh-modal-close" data-modal-close aria-label="Close">✕</button>
        </div>

        <?php if ($boost_task): ?>
            <div class="bh-modal-body">

                <!-- Task Info Card -->
                <?php if ($campaign_context): ?>
                <div class='bh-modal-task-card'>
                    <?php if (!empty($campaign_context['project_logo'])): ?><img src='<?php echo htmlspecialchars($campaign_context['project_logo'], ENT_QUOTES, 'UTF-8'); ?>' alt='' style='width:44px;height:44px;border-radius:10px;object-fit:cover'><?php endif; ?>
                    <strong><?php echo htmlspecialchars($campaign_context['project_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <p><?php echo htmlspecialchars($campaign_context['campaign_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <small>Ends <time data-bh-local-datetime='<?php echo htmlspecialchars(boostHubCampaignClientDateTime((string) $campaign_context['end_at']), ENT_QUOTES, 'UTF-8'); ?>'><?php echo htmlspecialchars(date('M j, Y g:i A', boostHubCampaignTimestamp((string) $campaign_context['end_at'])), ENT_QUOTES, 'UTF-8'); ?></time> · <?php echo htmlspecialchars(boostHubCampaignEffectiveState($campaign_context), ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
                <?php endif; ?>
                <div class="bh-modal-task-card">
                    <div class="bh-modal-task-badge-row">
                        <span class="bh-modal-task-type">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($boost_task['task_category'] ?? 'Social Task'))), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <span class="bh-modal-reward-pill">
                            <i class="fas fa-coins"></i> +<?php echo number_format((float) ($boost_task['reward'] ?? 0), 2); ?> $REX
                        </span>
                    </div>
                    <h4 class="bh-modal-task-title"><?php echo htmlspecialchars((string) ($boost_task['title'] ?? 'Task'), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p class="bh-modal-task-desc"><?php echo htmlspecialchars((string) ($boost_task['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <!-- Steps Card -->
                <?php if (!empty($boost_task['completion_steps'])): ?>
                    <div class="bh-modal-steps-card">
                        <div class="card-head">
                            <i class="fas fa-list-check"></i> How to complete
                        </div>
                        <p><?php echo nl2br(htmlspecialchars((string) $boost_task['completion_steps'], ENT_QUOTES, 'UTF-8')); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Notes Card -->
                <?php if (!empty($boost_task['proof_notes'])): ?>
                    <div class="bh-modal-notes-card">
                        <div class="card-head">
                            <i class="fas fa-circle-info"></i> Notes
                        </div>
                        <p><?php echo nl2br(htmlspecialchars((string) $boost_task['proof_notes'], ENT_QUOTES, 'UTF-8')); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Evidence Card -->
                <?php if ($boost_correction_note !== ''): ?>
                    <div class="bh-modal-notes-card">
                        <div class="card-head">
                            <i class="fas fa-rotate-left"></i> Admin note
                        </div>
                        <p><?php echo nl2br(htmlspecialchars($boost_correction_note, ENT_QUOTES, 'UTF-8')); ?></p>
                    </div>
                <?php endif; ?>

                <div class="bh-modal-evidence-card">
                    <div class="card-head">
                        <i class="fas fa-file-pen"></i> Evidence <span style="color:var(--bh-primary-light);font-weight:400;text-transform:none;">*</span>
                    </div>

                    <!-- Text Evidence -->
                    <label style="color:var(--bh-text-secondary);font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-link"></i> Link / Username / Handle
                    </label>
                    <textarea id="proofInput" rows="3" placeholder="Paste your link, username, or handle here..."><?php echo htmlspecialchars($prev_evidence_text, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <div class="bh-modal-counter" id="proofCounter">0 characters</div>

                    <!-- Screenshot Upload -->
                    <label style="color:var(--bh-text-secondary);font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;margin-top:8px;">
                        <i class="fas fa-camera"></i> Screenshot <span style="color:var(--bh-text-muted);font-weight:400;">(optional)</span>
                    </label>
                    <div class="bh-upload-area" id="screenshotUploadArea">
                        <input type="file" id="screenshotInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                        <div class="bh-upload-placeholder" id="uploadPlaceholder"<?php echo $prev_screenshot_url !== '' ? ' hidden' : ''; ?>>
                            <i class="fas fa-cloud-arrow-up"></i>
                            <span>Click to upload screenshot</span>
                            <span class="bh-upload-hint">JPG, PNG, GIF, WebP • Max 5MB</span>
                        </div>
                        <div class="bh-upload-preview" id="uploadPreview"<?php echo $prev_screenshot_url !== '' ? '' : ' hidden'; ?>>
                            <img id="previewImage" src="<?php echo htmlspecialchars($prev_screenshot_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Screenshot preview">
                            <button type="button" class="bh-upload-remove" id="uploadRemoveBtn" aria-label="Remove screenshot">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="bh-upload-status" id="uploadStatus" hidden>
                            <span class="bh-upload-spinner"></span>
                            <span id="uploadStatusText">Uploading...</span>
                        </div>
                    </div>
                    <input type="hidden" id="screenshotUrl" value="<?php echo htmlspecialchars($prev_screenshot_url, ENT_QUOTES, 'UTF-8'); ?>">
                </div>

            </div>

            <div class="bh-modal-footer">
                <?php if (!empty($boost_task['task_link'])): ?>
                    <a href="<?php echo htmlspecialchars((string) $boost_task['task_link'], ENT_QUOTES, 'UTF-8'); ?>" class="secondary-btn" target="_blank" rel="noopener noreferrer">
                        <i class="fas fa-arrow-up-right-from-square"></i> <?php echo htmlspecialchars((string) ($boost_task['cta_label'] ?? 'Open Task'), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endif; ?>
                <button type="button" class="primary-btn" id="submitClaimBtn">
                    <span class="spinner"></span>
                    <span class="btn-text"><i class="fas fa-paper-plane"></i> <?php echo $boost_correction_note !== '' ? 'Resubmit Evidence' : 'Submit Evidence'; ?></span>
                    <span class="btn-load">Submitting...</span>
                </button>
            </div>
        <?php else: ?>
            <div class="bh-modal-body">
                <p style="color:var(--bh-text-muted);text-align:center;padding:20px 0;">No task available at this time.</p>
            </div>
            <div class="bh-modal-footer">
                <button type="button" class="primary-btn" data-modal-close>Close</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Correction Modal (for resubmitting returned tasks) -->
<div class="bh-modal" id="correctionModal" hidden>
    <div class="bh-modal-backdrop" data-modal-close></div>
    <div class="bh-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="correctionModalTitle">
        <div class="bh-modal-head">
            <div class="bh-modal-head-left">
                <span class="bh-modal-head-icon"><i class="fas fa-rotate-left"></i></span>
                <h3 id="correctionModalTitle">Update Evidence</h3>
            </div>
            <button type="button" class="bh-modal-close" data-modal-close aria-label="Close">✕</button>
        </div>
        <div class="bh-modal-body">
            <div class="bh-modal-task-card">
                <div class="bh-modal-task-badge-row">
                    <span class="bh-modal-task-type" id="correctionCategory">
                        <i class="fas fa-tag"></i> Category
                    </span>
                    <span class="bh-modal-reward-pill" id="correctionReward">
                        <i class="fas fa-coins"></i> +0.00 $REX
                    </span>
                </div>
                <h4 class="bh-modal-task-title" id="correctionTitle">Task Title</h4>
            </div>
            <div class="bh-modal-notes-card">
                <div class="card-head">
                    <i class="fas fa-rotate-left"></i> Admin note
                </div>
                <p id="correctionNote">Please update your evidence.</p>
            </div>
            <div class="bh-modal-evidence-card">
                <div class="card-head">
                    <i class="fas fa-file-pen"></i> Updated Evidence <span style="color:var(--bh-primary-light);font-weight:400;text-transform:none;">*</span>
                </div>
                <label style="color:var(--bh-text-secondary);font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-link"></i> Link / Username / Handle
                </label>
                <textarea id="correctionProofInput" rows="3" placeholder="Paste your updated link, username, or handle..."></textarea>
                <div class="bh-modal-counter" id="correctionProofCounter">0 characters</div>

                <label style="color:var(--bh-text-secondary);font-size:0.82rem;font-weight:600;display:flex;align-items:center;gap:6px;margin-top:8px;">
                    <i class="fas fa-camera"></i> Screenshot <span style="color:var(--bh-text-muted);font-weight:400;">(optional)</span>
                </label>
                <div class="bh-upload-area" id="correctionUploadArea">
                    <input type="file" id="correctionScreenshotInput" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                    <div class="bh-upload-placeholder" id="correctionUploadPlaceholder">
                        <i class="fas fa-cloud-arrow-up"></i>
                        <span>Click to upload screenshot</span>
                        <span class="bh-upload-hint">JPG, PNG, GIF, WebP • Max 5MB</span>
                    </div>
                    <div class="bh-upload-preview" id="correctionUploadPreview" hidden>
                        <img id="correctionPreviewImage" src="" alt="Screenshot preview">
                        <button type="button" class="bh-upload-remove" id="correctionUploadRemoveBtn" aria-label="Remove screenshot">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="bh-upload-status" id="correctionUploadStatus" hidden>
                        <span class="bh-upload-spinner"></span>
                        <span id="correctionUploadStatusText">Uploading...</span>
                    </div>
                </div>
                <input type="hidden" id="correctionScreenshotUrl" value="">
            </div>
        </div>
        <div class="bh-modal-footer">
            <button type="button" class="secondary-btn" data-modal-close>Cancel</button>
            <button type="button" class="primary-btn" id="submitCorrectionBtn">
                <span class="spinner"></span>
                <span class="btn-text"><i class="fas fa-paper-plane"></i> Resubmit Evidence</span>
                <span class="btn-load">Submitting...</span>
            </button>
        </div>
    </div>
</div>

<!-- Celebration Modal -->
<div class="bh-modal" id="celebrationModal" hidden>
    <div class="bh-modal-backdrop" data-modal-close></div>
    <div class="bh-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="celebrationTitle">
        <div class="bh-celebration">
            <div class="bh-celebration-icon">🎉</div>
            <h3 id="celebrationTitle">Task Submitted!</h3>
            <p>Your evidence has been submitted successfully. It will be reviewed by our team and your reward will be credited upon approval.</p>
            <button type="button" class="primary-btn" id="celebrationCloseBtn"><i class="fas fa-arrow-left"></i> Back to BoostHub</button>
        </div>
    </div>
</div>

<!-- Confetti Canvas -->
<canvas id="bhConfetti"></canvas>

<script>
(function() {
    'use strict';

    const BASE_URL = <?php echo json_encode(BASE_URL); ?>;
    const submitUrl = BASE_URL + '/api/complete_mini_task.php';
    const skipUrl = BASE_URL + '/api/skip_boosthub_task.php';
    const campaignTaskUrl = BASE_URL + '/api/start_boosthub_campaign_task.php';
    const uploadUrl = BASE_URL + '/api/upload_boosthub_evidence.php';
    const taskId = <?php echo $boost_task ? (int) $boost_task['id'] : 0; ?>;
    const canSkipTask = <?php echo $can_skip_task ? 'true' : 'false'; ?>;
    const countdownSeconds = <?php echo (int) ($boost_state['countdown_seconds'] ?? 0); ?>;
    const totalCooldown = 86400; // Fixed 24h in seconds

    // ── Scroll Reveal ──
    const reveals = document.querySelectorAll('.bh-reveal');
    if (reveals.length && 'IntersectionObserver' in window) {
        const obs = new IntersectionObserver(function(entries) {
            entries.forEach(function(e) {
                if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        reveals.forEach(function(el) { obs.observe(el); });
    } else {
        reveals.forEach(function(el) { el.classList.add('visible'); });
    }

    // ── Modal Helpers ──
    // Switch between Daily Boost and Partner Campaigns without a long page.
    var viewTabs = document.querySelectorAll('[data-bh-view]');
    var viewPanels = document.querySelectorAll('[data-bh-tab-panel]');
    var viewTitle = document.getElementById('boostHubViewTitle');

    function activateBoostHubView(view, updateHash) {
        var selected = view === 'campaigns' ? 'campaigns' : 'daily';
        viewTabs.forEach(function(tab) {
            var active = tab.getAttribute('data-bh-view') === selected;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });
        viewPanels.forEach(function(panel) {
            panel.hidden = panel.getAttribute('data-bh-tab-panel') !== selected;
        });
        if (viewTitle) viewTitle.textContent = selected === 'campaigns' ? 'Partner Campaigns' : 'Daily Boost';
        if (updateHash && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', selected === 'campaigns' ? '#campaigns' : '#daily');
        }
        if (selected === 'campaigns') {
            var campaignsPanel = document.getElementById('partnerCampaignsPanel');
            if (campaignsPanel && !campaignsPanel.hidden) {

                campaignsPanel.scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        }
    }

    viewTabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            activateBoostHubView(tab.getAttribute('data-bh-view'), true);
        });
    });
    activateBoostHubView(window.location.hash === '#campaigns' ? 'campaigns' : 'daily', false);

    function openModal(id) {
        var el = document.getElementById(id);
        if (el) el.hidden = false;
    }

    function closeModal(id) {
        var el = document.getElementById(id);
        if (el) el.hidden = true;
    }

    document.querySelectorAll('[data-modal-close]').forEach(function(el) {
        el.addEventListener('click', function() {
            document.querySelectorAll('.bh-modal').forEach(function(m) { m.hidden = true; });
        });
    });

    // ── Claim Now Button ──
    var claimBtn = document.getElementById('claimNowBtn');
    if (claimBtn) {
        claimBtn.addEventListener('click', function() {
            openModal('claimModal');
        });
    }

    function parseBoostHubLocalTime(value) {
        if (!value) return null;
        var normalized = String(value).replace(' ', 'T');
        var date = new Date(normalized);
        return isNaN(date.getTime()) ? null : date;
    }

    function formatBoostHubLocalDate(value) {
        var date = parseBoostHubLocalTime(value);
        if (!date) return '';
        return date.toLocaleString(undefined, {month: 'short', day: 'numeric', year: 'numeric'});
    }

    document.querySelectorAll('[data-bh-local-datetime]').forEach(function(el) {
        var label = formatBoostHubLocalDate(el.getAttribute('data-bh-local-datetime'));
        if (label) el.textContent = label;
    });

    document.querySelectorAll('.bh-campaign-cover img').forEach(function(image) {
        var cover = image.closest('.bh-campaign-cover');
        var src = image.currentSrc || image.src;
        if (cover && src) {
            cover.style.backgroundImage = 'url(' + JSON.stringify(src) + ')';
            cover.classList.add('is-bg-cover');
        }
    });

    // Campaign countdown timers use the visitor's local system clock for display.
    document.querySelectorAll('[data-campaign-countdown]').forEach(function(el) {
        var targetDate = parseBoostHubLocalTime(el.getAttribute('data-campaign-countdown-target'));
        var remaining = targetDate ? Math.max(0, Math.floor((targetDate.getTime() - Date.now()) / 1000)) : Math.max(0, parseInt(el.getAttribute('data-campaign-countdown') || '0', 10));
        var textEl = el.querySelector('.bh-campaign-countdown-text');
        var labelEl = el.querySelector('.bh-campaign-countdown-label');
        if (!textEl) return;

        function fmtCampaignDuration(secs) {
            secs = Math.max(0, secs);
            var d = Math.floor(secs / 86400);
            var h = Math.floor((secs % 86400) / 3600);
            var m = Math.floor((secs % 3600) / 60);
            var s = secs % 60;
            var parts = [];
            if (d > 0) parts.push(d + 'd');
            if (d > 0 || h > 0) parts.push(h + 'h');
            if (d > 0 || h > 0 || m > 0) parts.push(m + 'm');
            parts.push(s + 's');
            return parts.join(' ');
        }

        function updateUrgency() {
            if (remaining <= 3600) { el.classList.add('is-urgent'); }
            else { el.classList.remove('is-urgent'); }
        }

        textEl.textContent = fmtCampaignDuration(remaining);
        updateUrgency();

        var timer = setInterval(function() {
            remaining = targetDate ? Math.max(0, Math.floor((targetDate.getTime() - Date.now()) / 1000)) : Math.max(0, remaining - 1);
            if (remaining <= 0) {
                clearInterval(timer);
                textEl.textContent = 'Expired!';
                textEl.classList.add('is-expired');
                el.classList.remove('is-urgent');
                if (labelEl) labelEl.textContent = 'Refresh to update status';
                return;
            }
            textEl.textContent = fmtCampaignDuration(remaining);
            updateUrgency();
        }, 1000);
    });

    document.querySelectorAll('[data-campaign-toggle]').forEach(function(button) {
        button.addEventListener('click', function() {
            var card = button.closest('[data-campaign-card]');
            if (!card) return;
            var panel = card.querySelector('.bh-campaign-collapsible');
            var expanded = !card.classList.contains('is-expanded');
            card.classList.toggle('is-expanded', expanded);
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            var label = button.querySelector('span');
            if (label) label.textContent = expanded ? 'Hide details' : 'View details';
            if (panel) panel.hidden = !expanded;
        });
    });

    document.querySelectorAll('[data-campaign-continue]').forEach(function(button) {
        button.addEventListener('click', function() {
            activateBoostHubView('daily', true);
            if (claimBtn) claimBtn.click();
        });
    });

    document.querySelectorAll('[data-campaign-task-start]').forEach(function(button) {
        button.addEventListener('click', async function() {
            var selectedTaskId = Number(button.getAttribute('data-campaign-task-start') || 0);
            if (!selectedTaskId || button.disabled) return;

            var originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class=\'fas fa-spinner fa-spin\'></i> Selecting...';

            try {
                var body = new URLSearchParams();
                body.set('task_id', String(selectedTaskId));

                var response = await fetch(campaignTaskUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                });
                var data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error((data && (data.error || data.message)) || 'Unable to select this task.');
                }

                if (window.history && window.history.replaceState) window.history.replaceState(null, '', '#daily');
                window.location.reload();
            } catch (error) {
                alert(error && error.message ? error.message : 'Unable to start this campaign task right now.');
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        });
    });

    var skipTaskBtn = document.getElementById('skipTaskBtn');
    if (skipTaskBtn && canSkipTask) {
        skipTaskBtn.addEventListener('click', async function() {
            if (skipTaskBtn.disabled || !taskId) return;
            if (!window.confirm('Skip to the next unfinished BoostHub task?')) return;

            skipTaskBtn.disabled = true;
            skipTaskBtn.classList.add('is-loading');

            try {
                var body = new URLSearchParams();
                body.set('task_id', String(taskId));

                var response = await fetch(skipUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body.toString()
                });
                var data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error((data && (data.error || data.message)) || 'Skip failed.');
                }

                window.location.reload();
            } catch (error) {
                alert(error && error.message ? error.message : 'Unable to skip this task right now.');
                skipTaskBtn.disabled = false;
                skipTaskBtn.classList.remove('is-loading');
            }
        });
    }

    // ── Character Counter (main) ──
    var proofInput = document.getElementById('proofInput');
    var proofCounter = document.getElementById('proofCounter');
    if (proofInput && proofCounter) {
        proofCounter.textContent = proofInput.value.length + ' characters';
        proofInput.addEventListener('input', function() {
            var len = proofInput.value.length;
            proofCounter.textContent = len + ' characters';
            proofCounter.classList.remove('warn', 'danger');
            if (len > 1000) proofCounter.classList.add('danger');
            else if (len > 500) proofCounter.classList.add('warn');
        });
    }

    // ── Character Counter (correction) ──
    var correctionProofInput = document.getElementById('correctionProofInput');
    var correctionProofCounter = document.getElementById('correctionProofCounter');
    if (correctionProofInput && correctionProofCounter) {
        correctionProofCounter.textContent = correctionProofInput.value.length + ' characters';
        correctionProofInput.addEventListener('input', function() {
            var len = correctionProofInput.value.length;
            correctionProofCounter.textContent = len + ' characters';
            correctionProofCounter.classList.remove('warn', 'danger');
            if (len > 1000) correctionProofCounter.classList.add('danger');
            else if (len > 500) correctionProofCounter.classList.add('warn');
        });
    }

    // ── Auto-resize textarea ──
    function autoResize(el) {
        if (!el) return;
        el.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 300) + 'px';
        });
    }
    autoResize(proofInput);
    autoResize(correctionProofInput);

    // ── Client-side image compression ──
    function compressImage(file, maxWidth, maxHeight, quality) {
        return new Promise(function(resolve, reject) {
            var img = new Image();
            var url = URL.createObjectURL(file);
            img.onload = function() {
                URL.revokeObjectURL(url);
                var canvas = document.createElement('canvas');
                var width = img.width;
                var height = img.height;

                // Scale down if needed
                if (width > maxWidth) {
                    height = Math.round(height * maxWidth / width);
                    width = maxWidth;
                }
                if (height > maxHeight) {
                    width = Math.round(width * maxHeight / height);
                    height = maxHeight;
                }

                canvas.width = width;
                canvas.height = height;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                // Convert to blob with compression
                canvas.toBlob(function(blob) {
                    if (!blob) {
                        reject(new Error('Compression failed'));
                        return;
                    }
                    // Create a new File from the compressed blob
                    var compressedFile = new File([blob], file.name, {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    resolve(compressedFile);
                }, 'image/jpeg', quality);
            };
            img.onerror = function() {
                URL.revokeObjectURL(url);
                reject(new Error('Failed to load image'));
            };
            img.src = url;
        });
    }

    // ── Screenshot Upload Handler ──
    function setupScreenshotUpload(uploadAreaId, fileInputId, placeholderId, previewId, previewImgId, removeBtnId, statusId, statusTextId, hiddenInputId) {
        var uploadArea = document.getElementById(uploadAreaId);
        var fileInput = document.getElementById(fileInputId);
        var placeholder = document.getElementById(placeholderId);
        var preview = document.getElementById(previewId);
        var previewImg = document.getElementById(previewImgId);
        var removeBtn = document.getElementById(removeBtnId);
        var status = document.getElementById(statusId);
        var statusText = document.getElementById(statusTextId);
        var hiddenInput = document.getElementById(hiddenInputId);

        if (!uploadArea || !fileInput) return;

        // Click to upload
        uploadArea.addEventListener('click', function(e) {
            if (e.target.closest('.bh-upload-remove')) return;
            fileInput.click();
        });

        // File selected
        fileInput.addEventListener('change', async function() {
            var file = fileInput.files[0];
            if (!file) return;

            // Validate file type
            var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Allowed: JPG, PNG, GIF, WebP.');
                fileInput.value = '';
                return;
            }

            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File too large. Maximum size is 5MB.');
                fileInput.value = '';
                return;
            }

            // Show uploading state (only when file is selected)
            placeholder.hidden = true;
            preview.hidden = true;
            status.hidden = false;
            statusText.textContent = 'Compressing & uploading...';

            try {
                // Compress image client-side before upload
                var compressed = await compressImage(file, 1920, 1080, 0.7);

                // Upload via FormData
                var formData = new FormData();
                formData.append('screenshot', compressed);

                var response = await fetch(uploadUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                var data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Upload failed');
                }

                // Show preview (only after successful upload)
                status.hidden = true;
                previewImg.src = data.url;
                preview.hidden = false;
                hiddenInput.value = data.url;

            } catch (err) {
                // Reset to placeholder on error
                status.hidden = true;
                placeholder.hidden = false;
                preview.hidden = true;
                alert('Upload failed: ' + err.message);
                fileInput.value = '';
            }
        });

        // Remove uploaded screenshot
        removeBtn.addEventListener('click', function() {
            preview.hidden = true;
            placeholder.hidden = false;
            status.hidden = true;
            hiddenInput.value = '';
            fileInput.value = '';
        });
    }

    // Setup main screenshot upload
    setupScreenshotUpload(
        'screenshotUploadArea', 'screenshotInput',
        'uploadPlaceholder', 'uploadPreview', 'previewImage',
        'uploadRemoveBtn', 'uploadStatus', 'uploadStatusText',
        'screenshotUrl'
    );

    // Setup correction screenshot upload
    setupScreenshotUpload(
        'correctionUploadArea', 'correctionScreenshotInput',
        'correctionUploadPlaceholder', 'correctionUploadPreview', 'correctionPreviewImage',
        'correctionUploadRemoveBtn', 'correctionUploadStatus', 'correctionUploadStatusText',
        'correctionScreenshotUrl'
    );

    // ── Submit Claim ──
    var submitBtn = document.getElementById('submitClaimBtn');
    if (submitBtn && taskId > 0) {
        submitBtn.addEventListener('click', async function() {
            var proof = proofInput ? proofInput.value.trim() : '';
            var screenshotUrl = document.getElementById('screenshotUrl') ? document.getElementById('screenshotUrl').value : '';

            if (!proof && !screenshotUrl) {
                if (proofInput) {
                    proofInput.style.borderColor = 'var(--bh-red)';
                    setTimeout(function() { proofInput.style.borderColor = ''; }, 2000);
                }
                alert('Please provide at least a link/username or a screenshot.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.classList.add('loading');

            try {
                var body = new URLSearchParams({
                    task_id: taskId,
                    proof: proof,
                    screenshot_url: screenshotUrl
                });

                var response = await fetch(submitUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body
                });
                var data = await response.json();

                if (!data.success) {
                    alert(data.message || 'Submission failed. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    return;
                }

                // Close claim modal, show celebration
                closeModal('claimModal');
                openModal('celebrationModal');
                launchConfetti();

            } catch (err) {
                alert('Submission failed. Please try again.');
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            }
        });
    }

    // ── Correction Modal: Resubmit from Pending Panel ──
    var correctionResubmitBtns = document.querySelectorAll('.bh-pending-resubmit-btn');
    var correctionTaskId = null;

    correctionResubmitBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var taskIdAttr = parseInt(btn.getAttribute('data-task-id'));
            var title = btn.getAttribute('data-title');
            var category = btn.getAttribute('data-category');
            var reward = parseFloat(btn.getAttribute('data-reward'));
            var prevText = btn.getAttribute('data-prev-text') || '';
            var prevScreenshot = btn.getAttribute('data-prev-screenshot') || '';
            var correctionNote = btn.getAttribute('data-correction-note') || 'Please update your evidence.';

            correctionTaskId = taskIdAttr;

            // Populate modal
            document.getElementById('correctionTitle').textContent = title;
            document.getElementById('correctionCategory').innerHTML = '<i class="fas fa-tag"></i> ' + category;
            document.getElementById('correctionReward').innerHTML = '<i class="fas fa-coins"></i> +' + reward.toFixed(2) + ' $REX';
            document.getElementById('correctionNote').textContent = correctionNote;
            document.getElementById('correctionProofInput').value = prevText;
            document.getElementById('correctionScreenshotUrl').value = prevScreenshot;

            // Reset upload area
            document.getElementById('correctionUploadPlaceholder').hidden = false;
            document.getElementById('correctionUploadPreview').hidden = true;
            document.getElementById('correctionUploadStatus').hidden = true;
            document.getElementById('correctionScreenshotInput').value = '';

            // If there's a previous screenshot, show it
            if (prevScreenshot) {
                document.getElementById('correctionUploadPlaceholder').hidden = true;
                document.getElementById('correctionPreviewImage').src = prevScreenshot;
                document.getElementById('correctionUploadPreview').hidden = false;
            }

            // Update counter
            var len = prevText.length;
            correctionProofCounter.textContent = len + ' characters';
            correctionProofCounter.classList.remove('warn', 'danger');

            openModal('correctionModal');
        });
    });

    // Submit correction
    var submitCorrectionBtn = document.getElementById('submitCorrectionBtn');
    if (submitCorrectionBtn) {
        submitCorrectionBtn.addEventListener('click', async function() {
            var proof = correctionProofInput ? correctionProofInput.value.trim() : '';
            var screenshotUrl = document.getElementById('correctionScreenshotUrl') ? document.getElementById('correctionScreenshotUrl').value : '';

            if (!proof && !screenshotUrl) {
                if (correctionProofInput) {
                    correctionProofInput.style.borderColor = 'var(--bh-red)';
                    setTimeout(function() { correctionProofInput.style.borderColor = ''; }, 2000);
                }
                alert('Please provide at least a link/username or a screenshot.');
                return;
            }

            if (!correctionTaskId) {
                alert('Task reference missing. Please try again.');
                return;
            }

            submitCorrectionBtn.disabled = true;
            submitCorrectionBtn.classList.add('loading');

            try {
                var body = new URLSearchParams({
                    task_id: correctionTaskId,
                    proof: proof,
                    screenshot_url: screenshotUrl
                });

                var response = await fetch(submitUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: body
                });
                var data = await response.json();

                if (!data.success) {
                    alert(data.message || 'Submission failed. Please try again.');
                    submitCorrectionBtn.disabled = false;
                    submitCorrectionBtn.classList.remove('loading');
                    return;
                }

                closeModal('correctionModal');
                openModal('celebrationModal');
                launchConfetti();

            } catch (err) {
                alert('Submission failed. Please try again.');
                submitCorrectionBtn.disabled = false;
                submitCorrectionBtn.classList.remove('loading');
            }
        });
    }

    // ── Celebration Close ──
    var celebrationClose = document.getElementById('celebrationCloseBtn');
    if (celebrationClose) {
        celebrationClose.addEventListener('click', function() {
            window.location.reload();
        });
    }

    // ── Countdown Timer ──
    var countdownEl = document.getElementById('countdownDisplay');
    var progressEl = document.getElementById('countdownProgress');
    if (countdownEl) {
        var remaining = Math.max(0, countdownSeconds);

        function formatDuration(secs) {
            secs = Math.max(0, secs);
            var h = Math.floor(secs / 3600);
            var m = Math.floor((secs % 3600) / 60);
            var s = secs % 60;
            var parts = [];
            if (h > 0) parts.push(h + 'h');
            if (h > 0 || m > 0) parts.push(m + 'm');
            parts.push(s + 's');
            return parts.join(' ');
        }

        // Set initial progress bar width based on elapsed time
        if (progressEl && totalCooldown > 0 && remaining > 0) {
            var elapsed = totalCooldown - remaining;
            var initPct = (elapsed / totalCooldown) * 100;
            progressEl.style.width = Math.min(100, Math.max(0, initPct)) + '%';
        }

        function updateCountdown() {
            remaining = Math.max(0, remaining - 1);
            countdownEl.textContent = formatDuration(remaining);
            if (progressEl && totalCooldown > 0) {
                var elapsed = totalCooldown - remaining;
                var pct = (elapsed / totalCooldown) * 100;
                progressEl.style.width = Math.min(100, Math.max(0, pct)) + '%';
            }
            if (remaining <= 0) {
                countdownEl.textContent = 'Ready!';
                window.location.reload();
            }
        }

        if (remaining > 0) {
            setInterval(updateCountdown, 1000);
        } else {
            countdownEl.textContent = 'Ready!';
        }
    }

    // ── Confetti ──
    function launchConfetti() {
        var canvas = document.getElementById('bhConfetti');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        var particles = [];
        var colors = ['#1D4ED8', '#D4AF37', '#22c55e', '#ef4444', '#93C5FD', '#FACC15', '#60a5fa'];

        for (var i = 0; i < 150; i++) {
            particles.push({
                x: Math.random() * canvas.width,
                y: Math.random() * canvas.height - canvas.height,
                w: Math.random() * 10 + 5,
                h: Math.random() * 6 + 3,
                color: colors[Math.floor(Math.random() * colors.length)],
                vx: (Math.random() - 0.5) * 4,
                vy: Math.random() * 3 + 2,
                rot: Math.random() * 360,
                rotSpeed: (Math.random() - 0.5) * 10
            });
        }

        var frame = 0;
        var maxFrames = 180;

        function draw() {
            if (frame >= maxFrames) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                return;
            }
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            particles.forEach(function(p) {
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.05;
                p.rot += p.rotSpeed;
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rot * Math.PI / 180);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
                ctx.restore();
            });
            frame++;
            requestAnimationFrame(draw);
        }
        draw();
    }

})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
