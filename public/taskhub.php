<?php
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireFeatureAccess('learnhub');

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

$db = getDBConnection();
ensureRewardClaimSchema($db);

$user = getCurrentUser();
if ($user && taskHubMissionCompleted((int) $user['id'], $db)) {
    redirect(BASE_URL . '/public/dashboard.php');
}

$state = getTaskHubState((int) $user['id'], $db);

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/reward-pages.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/taskhub-premium.css">

<main class="reward-page taskhub-premium">
    <div class="reward-page-shell">

        <?php if (($state['access'] ?? '') !== 'open'): ?>
            <div class="th-hero-card">
                <div class="th-hero-header">
                    <div>
                        <span class="th-hero-day-badge"><span class="th-day-num">!</span> Closed</span>
                        <h2 class="th-hero-title">LearnHub not available</h2>
                    </div>
                </div>
                <div class="th-hero-body">
                    <p class="th-task-description"><?php echo htmlspecialchars(str_replace('TaskHub', 'LearnHub', (string) ($state['message'] ?? 'LearnHub is not available for this account.')), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
        <?php else: ?>

        <!-- ============================================================
             STREAK HERO SECTION
             ============================================================ -->
        <?php
            $streak_badge_state = (string) ($state['streak_badge_state'] ?? (!empty($state['has_missed_days']) ? 'broken' : 'perfect'));
            $streak_badge_label = (string) ($state['streak_badge_label'] ?? ($streak_badge_state === 'perfect' ? 'Perfect' : ($streak_badge_state === 'rebuilding' ? 'Rebuilding' : 'Missed')));
            $streak_warning_active = !empty($state['streak_warning_active']);
        ?>
        <div class="th-streak-hero">
            <div class="th-streak-hero-top">
                <div class="th-streak-badge">
                    <span class="th-streak-fire">🔥</span>
                    <span class="th-streak-count">Day <?php echo (int) ($state['current_day'] ?? 1); ?> Streak</span>
                    <?php if ($streak_badge_state === 'broken'): ?>
                        <span class="th-streak-warning-badge">⚠️ Missed</span>
                    <?php elseif ($streak_badge_state === 'rebuilding'): ?>
                        <span class="th-streak-warning-badge"><?php echo htmlspecialchars($streak_badge_label, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php else: ?>
                        <span class="th-streak-perfect-badge">🏆 Perfect</span>
                    <?php endif; ?>
                </div>
                <div class="th-streak-actions">
                    <a href="<?php echo BASE_URL; ?>/public/dashboard.php" class="secondary-btn">Dashboard</a>
                </div>
            </div>

            <!-- Streak Progress Bar (10 days) with cumulative potential $REX -->
            <div class="th-streak-bar">
                <?php $current_day = (int) ($state['current_day'] ?? 1); ?>
                <?php $per_day_potential = $state['per_day_potential'] ?? []; ?>
                <?php $consecutive_streak = (int) ($state['consecutive_checkin_streak'] ?? 0); ?>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <?php
                    $dot_class = 'empty';
                    if ($i < $current_day) $dot_class = 'filled';
                    elseif ($i == $current_day) $dot_class = 'active';
                    
                    // Show cumulative potential $REX at this day
                    $cumulative_rex = $per_day_potential[$i] ?? ($i * ($i + 1) / 2);
                    
                    // For completed days, show actual earned; for current/future, show potential
                    $dot_reward_text = $cumulative_rex . ' $REX';
                    ?>
                    <div class="th-streak-dot <?php echo $dot_class; ?>" title="Day <?php echo $i; ?>: Cumulative <?php echo $cumulative_rex; ?> $REX">
                        <span class="th-streak-dot-day">
                            <span class="th-day-label">Day</span>
                            <span class="th-day-number"><?php echo $i; ?></span>
                        </span>
                        <span class="th-streak-dot-reward"><?php echo $dot_reward_text; ?></span>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Stats Row -->
            <div class="th-streak-stats">
                <div class="th-streak-stat">
                    <span class="th-streak-stat-label">Today's Check-in</span>
                    <span class="th-streak-stat-value highlight">+<?php echo (float) ($state['today_checkin_reward'] ?? 1); ?> $REX</span>
                </div>
                <div class="th-streak-stat">
                    <span class="th-streak-stat-label">Next Day</span>
                    <span class="th-streak-stat-value next">+<?php echo (float) ($state['next_checkin_reward'] ?? 0); ?> $REX 🎯</span>
                </div>
                <div class="th-streak-stat">
                    <span class="th-streak-stat-label">Streak Earned</span>
                    <span class="th-streak-stat-value"><?php echo (float) ($state['total_streak_earned'] ?? 0); ?> $REX</span>
                </div>
                <div class="th-streak-stat">
                    <span class="th-streak-stat-label">Potential</span>
                    <span class="th-streak-stat-value"><?php echo (float) ($state['total_potential_checkin'] ?? 55); ?> $REX</span>
                </div>
            </div>

            <!-- Unified PRO Progress Card -->
            <div class="th-pro-card">
                <!-- Card Header -->
                <div class="th-pro-card-header">
                    <div class="th-pro-card-title">
                        <span class="th-pro-card-icon">🏆</span>
                        <span>PRO Level Progress</span>
                    </div>
                    <?php if ($consecutive_streak >= 10): ?>
                        <span class="th-pro-card-badge is-unlocked">✅ Unlocked</span>
                    <?php else: ?>
                        <span class="th-pro-card-badge"><?php echo $consecutive_streak; ?>/10</span>
                    <?php endif; ?>
                </div>

                <!-- Days Completed Progress Bar -->
                <div class="th-pro-bar-section">
                    <?php
                    $completed_days = max(0, min(10, (int) ($state['completed_days'] ?? 0)));
                    ?>
                    <div class="th-pro-bar-label">
                        <span>Days Completed</span>
                        <span><?php echo $completed_days; ?> / 10</span>
                    </div>
                    <div class="th-pro-bar">
                        <div class="th-pro-bar-fill is-days" style="width: <?php echo ($completed_days / 10) * 100; ?>%;"></div>
                    </div>
                </div>

                <!-- Check-in Streak Progress Bar -->
                <div class="th-pro-bar-section">
                    <div class="th-pro-bar-label">
                        <span>Check-in Streak</span>
                        <span>
                            <?php echo $consecutive_streak; ?> / 10
                            <?php if ($consecutive_streak >= 10): ?>
                                🏆
                            <?php elseif ($consecutive_streak >= 7): ?>
                                🔥
                            <?php elseif ($consecutive_streak >= 5): ?>
                                💪
                            <?php elseif ($consecutive_streak >= 3): ?>
                                👍
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="th-pro-bar">
                        <div class="th-pro-bar-fill is-streak" style="width: <?php echo ($consecutive_streak / 10) * 100; ?>%;"></div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="th-pro-stats">
                    <div class="th-pro-stat">
                        <span class="th-pro-stat-icon">✅</span>
                        <span class="th-pro-stat-value"><?php echo $consecutive_streak; ?>/10</span>
                        <span class="th-pro-stat-label">Check-ins</span>
                    </div>
                    <div class="th-pro-stat">
                        <span class="th-pro-stat-icon">📅</span>
                        <span class="th-pro-stat-value"><?php echo $consecutive_streak; ?> day<?php echo $consecutive_streak !== 1 ? 's' : ''; ?></span>
                        <span class="th-pro-stat-label">Streak</span>
                    </div>
                    <div class="th-pro-stat">
                        <span class="th-pro-stat-icon">💰</span>
                        <span class="th-pro-stat-value"><?php echo (float) ($state['total_streak_earned'] ?? 0); ?>/<?php echo (float) ($state['total_potential_checkin'] ?? 55); ?> $REX</span>
                        <span class="th-pro-stat-label">Earned</span>
                    </div>
                    <div class="th-pro-stat">
                        <span class="th-pro-stat-icon">🎯</span>
                        <span class="th-pro-stat-value">+<?php echo (float) ($state['next_checkin_reward'] ?? 0); ?> $REX</span>
                        <span class="th-pro-stat-label">Next Reward</span>
                    </div>
                </div>

                <!-- Motivational Message -->
                <?php if ($consecutive_streak >= 10 && empty($state['has_missed_days'])): ?>
                    <div class="th-pro-message is-perfect">
                        <div class="th-pro-message-glow"></div>
                        <div class="th-pro-message-content">
                            <div class="th-pro-message-header">
                                <span class="th-pro-message-icon">🎉</span>
                                <span class="th-pro-message-badge is-unlocked">PRO Unlocked</span>
                            </div>
                            <div class="th-pro-message-body">
                                <p>You've conquered all <strong>10 days</strong> with a perfect streak! 🏆</p>
                                <div class="th-pro-message-rewards">
                                    <span class="th-pro-reward-chip">🎁 20 $REX Mystery Box</span>
                                    <span class="th-pro-reward-chip">👑 PRO Status</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif ($streak_warning_active): ?>
                    <div class="th-pro-message is-warning">
                        <div class="th-pro-message-content">
                            <div class="th-pro-message-header">
                                <span class="th-pro-message-icon">⚠️</span>
                                <span class="th-pro-message-badge is-warning">Streak Broken</span>
                            </div>
                            <div class="th-pro-message-body">
                                <p>You missed a day — your reward reset to <strong>1 $REX</strong>.</p>
                                <div class="th-pro-message-cta">
                                    <span class="th-pro-cta-text">Don't give up! Come back daily to rebuild your streak.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php elseif (!empty($state['has_missed_days'])): ?>
                    <div class="th-pro-message is-active">
                        <div class="th-pro-message-content">
                            <div class="th-pro-message-header">
                                <span class="th-pro-message-icon">↻</span>
                                <span class="th-pro-message-badge is-progress">Rebuilding</span>
                            </div>
                            <div class="th-pro-message-body">
                                <p>Your streak is active again. Keep checking in daily to grow your rebuilt rewards.</p>
                                <div class="th-pro-message-cta">
                                    <span class="th-pro-cta-text">Next check-in reward: +<?php echo (float) ($state['next_checkin_reward'] ?? 1); ?> $REX</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="th-pro-message is-active">
                        <div class="th-pro-message-content">
                            <div class="th-pro-message-header">
                                <span class="th-pro-message-icon">⚡</span>
                                <span class="th-pro-message-badge is-progress"><?php echo $consecutive_streak; ?>/10 Streak</span>
                            </div>
                            <div class="th-pro-message-body">
                                <p>Complete <strong class="th-pro-highlight"><?php echo 10 - $consecutive_streak; ?> more</strong> daily check-ins to unlock:</p>
                                <div class="th-pro-message-rewards">
                                    <span class="th-pro-reward-chip">👑 PRO Level</span>
                                    <span class="th-pro-reward-chip">🎁 20 $REX Mystery Box</span>
                                </div>
                                <div class="th-pro-message-cta">
                                    <span class="th-pro-cta-icon">💡</span>
                                    <span class="th-pro-cta-text">Come back tomorrow for your next check-in!</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Share Button -->
                <button type="button" class="th-pro-share-btn" data-share-progress data-day="<?php echo (int) ($state['current_day'] ?? 1); ?>">
                    📤 Share Your Progress
                </button>
            </div>
        </div>

        <!-- ============================================================
             DAY STEPPER (Days 1-10)
             ============================================================ -->
        <div class="th-day-stepper" role="tablist" aria-label="Mission days">
            <?php 
            foreach (($state['days'] ?? []) as $day): ?>
                <?php
                $is_current = !empty($day['is_current']);
                $is_past = !empty($day['is_past']);
                $is_future = empty($is_current) && empty($is_past);
                $dot_class = $is_current ? 'is-active' : ($is_past ? 'is-completed' : 'is-locked');
                $day_name = (string) ($day['title'] ?? ('Day ' . (int) $day['day']));
                ?>
                <div class="th-day-step" data-th-step>
                    <button
                        type="button"
                        class="th-day-dot <?php echo $dot_class; ?>"
                        data-th-day="<?php echo (int) $day['day']; ?>"
                        role="tab"
                        aria-selected="<?php echo $is_current ? 'true' : 'false'; ?>"
                        <?php echo $is_future ? 'disabled' : ''; ?>
                        title="Day <?php echo (int) $day['day']; ?>: <?php echo htmlspecialchars((string) ($day['title'] ?? $day_name), ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <?php echo $is_past ? '✓' : (int) $day['day']; ?>
                    </button>
                    <span class="th-day-step-label"><?php echo htmlspecialchars($day_name, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php if ($day['day'] < 10): ?>
                        <span class="th-day-connector <?php echo $is_past ? 'is-done' : ''; ?>"></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ============================================================
             DAY PANELS (hidden by default, shown via JS)
             ============================================================ -->
        <?php foreach (($state['days'] ?? []) as $day): ?>
            <?php if (empty($day['is_current']) && empty($day['is_past'])): ?>
                <?php continue; ?>
            <?php endif; ?>

            <div
                data-th-panel="<?php echo (int) $day['day']; ?>"
                <?php echo !empty($day['is_current']) ? '' : 'hidden'; ?>
            >
                <!-- ============================================================
                     HERO CARD — Single Task Focus
                     ============================================================ -->
                <div class="th-hero-card" data-hero-card="<?php echo (int) $day['day']; ?>">

                    <!-- Hero Card Header -->
                    <div class="th-hero-header">
                        <div>
                            <span class="th-hero-day-badge">
                                <span class="th-day-num"><?php echo (int) $day['day']; ?></span>
                                <?php echo htmlspecialchars((string) ($day['title'] ?? ('Day ' . (int) $day['day'])), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <h2 class="th-hero-title">
                                <?php echo htmlspecialchars((string) ($day['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </h2>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <span class="th-hero-task-progress">
                                <?php echo (int) ($day['completed_tasks'] ?? 0); ?>/<?php echo (int) ($day['total_tasks'] ?? 0); ?> tasks
                            </span>
                            <span class="th-hero-status-chip <?php echo ($day['status'] ?? '') === 'completed' ? 'is-completed' : (($day['status'] ?? '') === 'locked' ? 'is-locked' : ''); ?>">
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $day['status'])), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Hero Card Body — Shows current task -->
                    <div class="th-hero-body">
                        <?php
                        // Find the first non-completed task (or the first task if all completed)
                        $current_task = null;
                        $current_task_index = 0;
                        $total_tasks_in_day = count($day['tasks'] ?? []);
                        $all_completed = true;

                        foreach (($day['tasks'] ?? []) as $tidx => $task) {
                            if (($task['status'] ?? '') !== 'completed') {
                                if ($current_task === null) {
                                    $current_task = $task;
                                    $current_task_index = $tidx;
                                }
                                $all_completed = false;
                            }
                        }

                        // If all tasks completed, show summary
                        if ($all_completed && $total_tasks_in_day > 0):
                            $day_num = (int) $day['day'];
                            $next_day_num = $day_num + 1;
                            $day_name = (string) ($day['title'] ?? ('Day ' . $day_num));
                            $next_day_name = 'Day ' . $next_day_num;
                            
                            // Calculate today's earnings
                            $today_earnings = 0;
                            foreach (($day['tasks'] ?? []) as $task) {
                                if (($task['status'] ?? '') === 'completed') {
                                    $today_earnings += (float) ($task['reward'] ?? 0);
                                }
                            }
                            
                            $encouragements = [
                                'Amazing progress! You\'re learning the ropes like a pro.',
                                'Great job! Every task completed brings you closer to rewards.',
                                'You\'re on fire! Keep up the excellent work.',
                                'Fantastic! You\'re mastering the CoinRex platform.',
                                'Well done! Consistency is the key to success.',
                            ];
                            $encouragement = $encouragements[array_rand($encouragements)];
                        ?>
                            <div class="th-completed-summary" data-day-complete="<?php echo $day_num; ?>">
                                <div class="th-completed-icon">🎉</div>
                                <h3>Day <?php echo $day_num; ?>: <?php echo htmlspecialchars($day_name, ENT_QUOTES, 'UTF-8'); ?> Complete!</h3>
                                <p><?php echo htmlspecialchars($encouragement, ENT_QUOTES, 'UTF-8'); ?></p>
                                
                                <!-- Today's Earnings Breakdown -->
                                <div class="th-earnings-breakdown">
                                    <div class="th-earnings-header">📊 Today's Earnings</div>
                                    <?php foreach (($day['tasks'] ?? []) as $task): ?>
                                        <?php if (($task['status'] ?? '') === 'completed'): ?>
                                            <div class="th-earnings-item">
                                                <span class="th-earnings-item-name"><?php echo htmlspecialchars((string) ($task['title'] ?? 'Task'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="th-earnings-item-reward">+<?php echo number_format((float) ($task['reward'] ?? 0), 2); ?> $REX</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <div class="th-earnings-total">
                                        <span>Total Today</span>
                                        <strong>+<?php echo number_format($today_earnings, 2); ?> $REX</strong>
                                    </div>
                                </div>
                                
                                <?php if ($day_num < 10): ?>
                                    <!-- Tomorrow Preview -->
                                    <div class="th-tomorrow-preview">
                                        <div class="th-tomorrow-header">🔮 Tomorrow's Preview</div>
                                        <div class="th-tomorrow-day">
                                            <span class="th-tomorrow-day-badge">Day <?php echo $next_day_num; ?></span>
                                            <strong><?php echo htmlspecialchars($next_day_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                                        </div>
                                        <div class="th-tomorrow-checkin">
                                            Check-in reward: <strong>+<?php echo (float) ($state['next_checkin_reward'] ?? 1); ?> $REX</strong>
                                        </div>
                                        <?php if (!empty($state['next_day_preview'])): ?>
                                            <div class="th-tomorrow-tasks">
                                                <?php foreach ($state['next_day_preview'] as $preview_task): ?>
                                                    <div class="th-tomorrow-task">
                                                        <span class="th-tomorrow-task-icon">📋</span>
                                                        <span><?php echo htmlspecialchars((string) ($preview_task['title'] ?? 'Task'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Countdown to next day -->
                                    <?php if (!empty($day['countdown_seconds'])): ?>
                                        <div class="th-next-day-countdown">
                                            <span class="th-next-day-label">Next day unlocks in</span>
                                            <span class="th-timer-count" data-th-day-countdown="<?php echo (int) $day['countdown_seconds']; ?>">
                                                <?php echo htmlspecialchars(taskHubFormatDuration((int) $day['countdown_seconds']), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Share Progress Button -->
                                    <button type="button" class="th-share-btn" data-share-progress data-day="<?php echo $day_num; ?>">
                                        📤 Share Your Progress
                                    </button>
                                <?php elseif ($day_num >= 10): ?>
                                    <div class="th-mission-complete-banner">
                                        <span class="th-mission-complete-icon">🏆</span>
                                        <div>
                                            <strong>Mission Complete!</strong>
                                            <p>You've completed the entire 10-day mission! Check your rewards!</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($total_tasks_in_day === 0): ?>
                            <!-- No tasks configured for this day -->
                            <div class="th-empty-day">
                                <div class="th-empty-day-icon"><i class="fas fa-clipboard-list"></i></div>
                                <h3>No tasks available for this day yet</h3>
                                <p>Tasks will appear here once they are configured by the admin. Check back later!</p>
                                <div class="th-empty-day-actions">
                                    <a href="<?php echo BASE_URL; ?>/public/dashboard.php" class="th-premium-btn primary"><i class="fas fa-chart-simple"></i> Go to Dashboard</a>
                                </div>
                            </div>
                        <?php elseif ($current_task): ?>
                            <?php
                            $task = $current_task;
                            $is_timed_lock = ($task['status'] ?? '') === 'locked' && !empty($task['countdown_seconds']);
                            $is_submitted = ($task['status'] ?? '') === 'submitted';
                            $is_completed = ($task['status'] ?? '') === 'completed';
                            $is_available = ($task['status'] ?? '') === 'available' || ($task['status'] ?? '') === 'failed';
                            ?>
                            <?php
                            // Determine premium card type based on verification_mode and task properties
                            $task_key_str = (string) ($task['task_key'] ?? '');
                            $vm = (string) ($task['verification_mode'] ?? 'instant');
                            $task_key_lower = strtolower($task_key_str);
                            $is_checkin_task = strpos($task_key_lower, '_checkin') !== false || strpos($task_key_lower, '_check_in') !== false;
                            $is_social_task = strpos($task_key_lower, 'social') !== false || strpos($task_key_lower, 'share') !== false;
                            $is_quiz_task = ($vm === 'quiz');
                            
                            // Map verification_mode to card styling (dynamic from task data)
            $vm_icon_map = [
                'checkin' => '<i class="fas fa-calendar-check"></i>',
                'social' => '<i class="fas fa-share-nodes"></i>',
                'quiz' => '<i class="fas fa-brain"></i>',
                'manual' => '<i class="fas fa-paperclip"></i>',
                'mystery' => '<i class="fas fa-gift"></i>',
            ];
                             $vm_class_map = [
                                 'checkin' => 'is-checkin',
                                 'social' => 'is-social',
                                 'quiz' => 'is-quiz',
                                 'manual' => 'is-proof',
                                 'mystery' => 'is-mystery',
                             ];
                            
                            // Determine which mode key to use
                            if ($is_checkin_task) {
                                $mode_key = 'checkin';
                            } elseif ($is_social_task) {
                                $mode_key = 'social';
                            } elseif ($is_quiz_task) {
                                $mode_key = 'quiz';
                            } elseif (array_key_exists($vm, $vm_class_map)) {
                                $mode_key = $vm;
                            } else {
                                $mode_key = 'default';
                            }
                            
                            if ($mode_key === 'default') {
                                $premium_card_class = 'is-mission';
                                $premium_badge_icon = '<i class="fas fa-bullseye"></i>';
                                $premium_badge_text = htmlspecialchars((string) ($task['title'] ?? 'Mission Task'), ENT_QUOTES, 'UTF-8');
                                $premium_title = '<i class="fas fa-bullseye"></i> ' . htmlspecialchars((string) ($task['title'] ?? 'Mission Task'), ENT_QUOTES, 'UTF-8');
                                $premium_desc = htmlspecialchars((string) ($task['description'] ?? 'Complete this task to progress through the mission.'), ENT_QUOTES, 'UTF-8');
                            } else {
                                $premium_card_class = $vm_class_map[$mode_key] ?? 'is-mission';
                                $premium_badge_icon = $vm_icon_map[$mode_key] ?? '<i class="fas fa-bullseye"></i>';
                                $premium_badge_text = htmlspecialchars((string) ($task['title'] ?? 'Task'), ENT_QUOTES, 'UTF-8');
                                $premium_title = ($premium_badge_icon . ' ' . htmlspecialchars((string) ($task['title'] ?? 'Task'), ENT_QUOTES, 'UTF-8'));
                                $premium_desc = htmlspecialchars((string) ($task['description'] ?? 'Complete this task to progress.'), ENT_QUOTES, 'UTF-8');
                            }
                            ?>
                            
                            <div class="th-task-content th-premium-card <?php echo $premium_card_class; ?>" data-task-key="<?php echo htmlspecialchars($task_key_str, ENT_QUOTES, 'UTF-8'); ?>" data-verification-mode="<?php echo htmlspecialchars((string) $task['verification_mode'], ENT_QUOTES, 'UTF-8'); ?>">

                                <!-- ============================================================
                                     WAITING CARD (replaces lock timer overlay)
                                     ============================================================ -->
                                <?php if ($is_timed_lock): ?>
                                    <div class="th-waiting-card" data-th-waiting>
                                        <div class="th-waiting-header">
                                            <span class="th-waiting-icon">⏳</span>
                                            <div class="th-waiting-info">
                                                <span class="th-waiting-label">Next Challenge</span>
                                                <span class="th-waiting-timer" data-th-timer-count><?php echo htmlspecialchars(taskHubFormatDuration((int) $task['countdown_seconds']), ENT_QUOTES, 'UTF-8'); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="th-waiting-preview">
                                            <span class="th-waiting-preview-label">Up Next:</span>
                                            <strong class="th-waiting-preview-title"><?php echo htmlspecialchars((string) ($task['title'] ?? 'Task'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <span class="th-waiting-preview-reward">+<?php echo number_format((float) ($task['reward'] ?? 0), 2); ?> $REX</span>
                                        </div>
                                        
                                        <div class="th-waiting-activities">
                                            <span class="th-waiting-activities-label">⚡ While you wait:</span>
                                            <div class="th-waiting-activity-grid">
                                                <a href="<?php echo BASE_URL; ?>/public/dashboard.php" class="th-waiting-activity">
                                                    <span class="th-waiting-activity-icon">📊</span>
                                                    <span>Explore Dashboard</span>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/profile.php" class="th-waiting-activity">
                                                    <span class="th-waiting-activity-icon">👤</span>
                                                    <span>Complete Profile</span>
                                                </a>
                                                <a href="<?php echo BASE_URL; ?>/blog.php" class="th-waiting-activity">
                                                    <span class="th-waiting-activity-icon">📰</span>
                                                    <span>Read Blog</span>
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <!-- Notification permission button -->
                                        <div class="th-waiting-notify">
                                            <button type="button" class="th-notify-btn" data-enable-notifications>
                                                🔔 Notify me when ready
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- ============================================================
                                     PREMIUM BADGE (shown for ALL task types)
                                     ============================================================ -->
                                <div class="th-premium-badge">
                                    <span class="th-premium-badge-icon"><?php echo $premium_badge_icon; ?></span>
                                    <?php echo $premium_badge_text; ?>
                                </div>
                                <h3 class="th-premium-title"><?php echo $premium_title; ?></h3>
                                <p class="th-premium-desc"><?php echo htmlspecialchars((string) ($task['description'] ?? $premium_desc), ENT_QUOTES, 'UTF-8'); ?></p>

                                <?php if ($is_checkin_task): ?>
                                    <!-- === CHECK-IN SPECIFIC: Streak display === -->
                                    <div class="th-premium-streak">
                                        <span class="th-premium-streak-days"><?php echo (int) ($day['day'] ?? 1); ?></span>
                                        <div>
                                            <span class="th-premium-streak-label">Day Streak</span>
                                            <span class="th-premium-streak-reward">Reward: +<?php echo (float) ($task['reward'] ?? 1); ?> $REX</span>
                                        </div>
                                        <div class="th-premium-streak-bar">
                                            <div class="th-premium-streak-fill" style="width:<?php echo min(100, ((int) ($day['day'] ?? 1) / 10) * 100); ?>%;"></div>
                                        </div>
                                    </div>

                                <?php elseif ($is_social_task): ?>
                                    <!-- === SOCIAL SPECIFIC: Platform links + inputs === -->
                                    <?php if (($task['task_key'] ?? '') === 'day1_social_follow' && !$is_timed_lock && !$is_submitted && !$is_completed): ?>
                                        <div class="th-premium-platforms">
                                            <a href="https://x.com" class="th-premium-platform" target="_blank" rel="noopener noreferrer"><span class="th-premium-platform-icon">𝕏</span> X (Twitter)</a>
                                            <a href="https://t.me" class="th-premium-platform" target="_blank" rel="noopener noreferrer"><span class="th-premium-platform-icon">✈</span> Telegram</a>
                                        </div>
                                        <p style="color:var(--th-text-muted);font-size:12px;margin:0 0 12px;line-height:1.5;">Follow one of the official social channels, then share your handle below.</p>
                                        <div class="th-premium-inputs">
                                            <input type="text" class="th-premium-input" data-x-handle placeholder="X username or URL">
                                            <input type="text" class="th-premium-input" data-telegram-handle placeholder="Telegram username or URL">
                                        </div>
                                    <?php elseif (($task['task_key'] ?? '') === 'day3_share_experience' && !$is_timed_lock && !$is_submitted && !$is_completed): ?>
                                        <div class="th-premium-platforms">
                                            <a href="https://x.com" class="th-premium-platform" target="_blank" rel="noopener noreferrer"><span class="th-premium-platform-icon">𝕏</span> X</a>
                                            <a href="https://facebook.com" class="th-premium-platform" target="_blank" rel="noopener noreferrer"><span class="th-premium-platform-icon">f</span> Facebook</a>
                                            <a href="https://www.binance.com/en/square" class="th-premium-platform" target="_blank" rel="noopener noreferrer"><span class="th-premium-platform-icon">📊</span> Binance Square</a>
                                            <a href="https://medium.com" class="th-premium-platform" target="_blank" rel="noopener noreferrer"><span class="th-premium-platform-icon">M</span> Medium</a>
                                            <a href="https://reddit.com" class="th-premium-platform" target="_blank" rel="noopener noreferrer"><span class="th-premium-platform-icon">R</span> Reddit</a>
                                        </div>
                                        <div class="th-premium-inputs">
                                            <select class="th-premium-input" data-share-platform style="appearance:auto;padding:0 10px;">
                                                <option value="">Select platform</option>
                                                <option value="x">X (Twitter)</option>
                                                <option value="facebook">Facebook</option>
                                                <option value="binance_square">Binance Square</option>
                                                <option value="medium">Medium</option>
                                                <option value="reddit">Reddit</option>
                                            </select>
                                            <input type="url" class="th-premium-input" data-share-proof-url placeholder="Paste your public post URL">
                                        </div>
                                    <?php endif; ?>

                                <?php elseif ($is_quiz_task): ?>
                                    <?php
                                        $has_learning_url = !empty($task['learning_url']);
                                        $quiz_unlocked = !empty($task['learning_opened']) || !$has_learning_url;
                                        $required_quiz_score = (int) ($task['min_quiz_score'] ?? count($task['quiz'] ?? []));
                                        if ($required_quiz_score <= 0) {
                                            $required_quiz_score = count($task['quiz'] ?? []);
                                        }
                                    ?>
                                    <!-- === QUIZ SPECIFIC: Quiz info banner === -->
                                    <div class="th-premium-quiz-info">
                                        <span class="th-premium-quiz-info-icon">📝</span>
                                        <div class="th-premium-quiz-info-text">
                                            <strong><?php echo count($task['quiz'] ?? []); ?> Questions</strong>
                                            <span>Score at least <?php echo $required_quiz_score; ?>/<?php echo count($task['quiz'] ?? []); ?> to earn the reward</span>
                                        </div>
                                    </div>

                                    <!-- Learning Gate -->
                                    <?php if (!$is_timed_lock && !$is_submitted && !$is_completed && $has_learning_url): ?>
                                        <div class="th-learning-gate <?php echo !empty($task['learning_opened']) ? 'is-validated' : 'is-locked'; ?>" data-learning-gate data-task-key="<?php echo htmlspecialchars((string) $task['task_key'], ENT_QUOTES, 'UTF-8'); ?>" data-learning-opened="<?php echo !empty($task['learning_opened']) ? '1' : '0'; ?>">
                                            <span class="th-learning-label">📖 <?php echo htmlspecialchars((string) $task['learning_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <a href="<?php echo htmlspecialchars((string) $task['learning_url'], ENT_QUOTES, 'UTF-8'); ?>" class="th-learning-btn" target="_blank" rel="noopener noreferrer" data-learning-open>Open & Validate</a>
                                            <span class="th-learning-status" data-learning-status><?php echo !empty($task['learning_opened']) ? 'Learning validated ✓' : 'Not Open'; ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Beginner-friendly quiz instructions -->
                                    <?php if (!$is_timed_lock && !$is_submitted && !$is_completed && !empty($task['quiz'])): ?>
                                        <div class="th-quiz-instructions">
                                            <span class="th-quiz-instructions-icon">📖</span>
                                            <div class="th-quiz-instructions-text">
                                                <strong class="th-quiz-guide-title">Quick quiz guide</strong>
                                                <span class="th-quiz-guide-copy">Answer carefully. Correct answers move you forward automatically.</span>
                                                <span class="th-quiz-guide-steps">
                                                    <span>1. Read the material</span>
                                                    <span>2. Pick the best answer</span>
                                                    <span>3. Revisit Page anytime</span>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="th-quiz-block" data-quiz-block data-min-score="<?php echo (int) $required_quiz_score; ?>" <?php echo $quiz_unlocked ? '' : 'hidden'; ?>>

                                            <div class="th-quiz-progress">
                                                <div class="th-quiz-progress-track">
                                                    <div class="th-quiz-progress-fill" data-quiz-progress-fill style="width:0%;"></div>
                                                </div>
                                                <div class="th-quiz-progress-info">
                                                    <span class="th-quiz-progress-label" data-quiz-progress-label>Question 1 of <?php echo count($task['quiz']); ?></span>
                                                    <span class="th-quiz-progress-pct" data-quiz-progress-pct>0%</span>
                                                </div>
                                            </div>
                                            <?php foreach ($task['quiz'] as $question_index => $question): ?>
                                                <div class="th-quiz-question" data-quiz-question data-question-index="<?php echo (int) $question_index; ?>" <?php echo $question_index > 0 ? 'hidden' : ''; ?>>
                                                    <div class="th-quiz-q-text"><?php echo ($question_index + 1) . '. ' . htmlspecialchars((string) $question['question'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                    <div class="th-quiz-choices">
                                                        <?php foreach (($question['choices'] ?? []) as $choice_index => $choice): ?>
                                                            <?php
                                                            $letter = chr(65 + $choice_index);
                                                            $correct_answers = is_array($question['answer'] ?? null) ? array_map('intval', $question['answer']) : [(int) ($question['answer'] ?? -1)];
                                                            ?>
                                                            <label class="th-quiz-choice" data-choice>
                                                                <input type="radio" name="<?php echo htmlspecialchars((string) $task['task_key'], ENT_QUOTES, 'UTF-8'); ?>_q_<?php echo $question_index; ?>" value="<?php echo $choice_index; ?>" data-correct="<?php echo in_array((int) $choice_index, $correct_answers, true) ? '1' : '0'; ?>" hidden>
                                                                <span class="th-quiz-choice-marker"><?php echo $letter; ?></span>
                                                                <span class="th-quiz-choice-text"><?php echo htmlspecialchars((string) $choice, ENT_QUOTES, 'UTF-8'); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            

                                        </div>
                                    <?php endif; ?>

                                <?php elseif ($is_available && !$is_timed_lock && !$is_submitted && !$is_completed): ?>
                                    <!-- === OTHER TASK TYPES: Show completion steps === -->
                                    <?php if (!empty($task['completion_steps'])): ?>
                                        <div class="th-premium-steps">
                                            <span class="th-premium-steps-label"><i class="fas fa-list"></i> Steps to complete:</span>
                                            <div class="th-premium-steps-content"><?php echo nl2br(htmlspecialchars((string) $task['completion_steps'], ENT_QUOTES, 'UTF-8')); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($task['proof_notes'])): ?>
                                        <div class="th-premium-proof-notes">
                                            <span class="th-premium-proof-notes-label"><i class="fas fa-paperclip"></i> Proof notes:</span>
                                            <p><?php echo htmlspecialchars((string) $task['proof_notes'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <!-- ============================================================
                                     ACTION BUTTONS
                                     ============================================================ -->
                                <?php if ($is_available && !$is_timed_lock && !$is_submitted && !$is_completed): ?>
                                    <div class="th-premium-actions">
                                        <?php if ((int) ($task['id'] ?? 0) > 0): ?>
                                            <!-- DB-driven task: use task_link and cta_label from database -->
                                            <?php
                                            $db_cta_label = trim((string) ($task['cta_label'] ?? ''));
                                            $db_task_link = trim((string) ($task['task_link'] ?? ''));
                                            ?>
                                            <?php if ($is_checkin_task): ?>
                                                <button type="button" class="th-premium-btn primary" data-th-action="checkin">
                                                    <i class="fas fa-calendar-check"></i> <?php echo htmlspecialchars($db_cta_label ?: 'Check In Now', ENT_QUOTES, 'UTF-8'); ?>
                                                </button>
                                            <?php elseif ($is_social_task && ($task['task_key'] ?? '') === 'day1_social_follow'): ?>
                                                <button type="button" class="th-premium-btn primary" data-th-action="social_follow">
                                                    <i class="fas fa-paper-plane"></i> <?php echo htmlspecialchars($db_cta_label ?: 'Submit for Review', ENT_QUOTES, 'UTF-8'); ?>
                                                </button>
                                            <?php elseif ($is_social_task && ($task['task_key'] ?? '') === 'day3_share_experience'): ?>
                                                <button type="button" class="th-premium-btn primary" data-th-action="share_experience">
                                                    <i class="fas fa-paper-plane"></i> <?php echo htmlspecialchars($db_cta_label ?: 'Submit for Review', ENT_QUOTES, 'UTF-8'); ?>
                                                </button>
                                            <?php elseif ($is_quiz_task): ?>
                                                <!-- Quiz submit is handled by the JS-generated button inside the quiz block -->
                                                <div style="display:none;" data-th-action="quiz"></div>

                                            <?php elseif (($task['verification_mode'] ?? '') === 'mystery'): ?>
                                                <button type="button" class="th-premium-btn primary" data-th-action="mystery">
                                                    <i class="fas fa-gift"></i> <?php echo htmlspecialchars($db_cta_label ?: 'Open Mystery Box', ENT_QUOTES, 'UTF-8'); ?>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="th-premium-btn primary" data-th-action="instant">
                                                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($db_cta_label ?: 'Complete Task', ENT_QUOTES, 'UTF-8'); ?>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button type="button" class="th-premium-btn primary" data-th-action="instant">
                                                <i class="fas fa-check-circle"></i> Complete Task
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($is_submitted): ?>
                                    <div class="th-premium-submitted">
                                        <span class="th-premium-submitted-icon"><i class="fas fa-hourglass-half"></i></span>
                                        <div>
                                            <strong>Submitted for Review</strong>
                                            <p>An admin will review your submission. Check back later.</p>
                                        </div>
                                    </div>
                                <?php elseif ($is_completed): ?>
                                    <div class="th-premium-completed">
                                        <span class="th-premium-completed-icon"><i class="fas fa-check-circle"></i></span>
                                        <div>
                                            <strong>Task Completed!</strong>
                                            <p>You earned +<?php echo number_format((float) ($task['reward'] ?? 0), 2); ?> $REX for this task.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Error / Feedback area -->
                                <div class="th-premium-feedback" data-th-feedback hidden></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/taskhub/mystery-box.php'; ?>

<script src="<?php echo ASSETS_URL; ?>/js/taskhub-premium.js?v=<?php echo (int) @filemtime(__DIR__ . '/../assets/js/taskhub-premium.js'); ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php ob_end_flush(); ?>
