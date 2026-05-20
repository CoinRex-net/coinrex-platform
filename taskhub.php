<?php
ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

$db = getDBConnection();
ensureRewardClaimSchema($db);

$user = getCurrentUser();
$state = getTaskHubState((int) $user['id'], $db);

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/reward-pages.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/taskhub-premium.css">

<main class="reward-page taskhub-premium">
    <div class="reward-page-shell">

        <!-- ============================================================
             HEADER
             ============================================================ -->
        <div class="th-header">
            <div class="th-header-left">
                <span class="reward-tag">TaskHub</span>
                <h1>10-Day Mission Board</h1>
            </div>
            <div class="th-header-actions">
                <a href="<?php echo BASE_URL; ?>/dashboard.php" class="secondary-btn">Dashboard</a>
                <a href="<?php echo BASE_URL; ?>/boosthub.php" class="secondary-btn">BoostHub</a>
            </div>
        </div>

        <?php if (($state['access'] ?? '') !== 'open'): ?>
            <div class="th-hero-card">
                <div class="th-hero-header">
                    <div>
                        <span class="th-hero-day-badge"><span class="th-day-num">!</span> Closed</span>
                        <h2 class="th-hero-title">TaskHub not available</h2>
                    </div>
                </div>
                <div class="th-hero-body">
                    <p class="th-task-description"><?php echo htmlspecialchars((string) ($state['message'] ?? 'TaskHub is not available for this account.'), ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
        <?php else: ?>

        <!-- ============================================================
             DAY STEPPER (Days 1-10)
             ============================================================ -->
        <div class="th-day-stepper" role="tablist" aria-label="Mission days">
            <?php 
            $day_titles_map = [
                1 => 'Welcome',
                2 => 'Explore', 
                3 => 'Privacy',
                4 => 'Roadmap',
                5 => 'DevHub',
                6 => 'Review',
                7 => 'Filter',
                8 => 'Wallet',
                9 => 'Momentum',
                10 => 'Mystery',
            ];
            foreach (($state['days'] ?? []) as $day): ?>
                <?php
                $is_current = !empty($day['is_current']);
                $is_past = !empty($day['is_past']);
                $is_future = empty($is_current) && empty($is_past);
                $dot_class = $is_current ? 'is-active' : ($is_past ? 'is-completed' : 'is-locked');
                $day_name = $day_titles_map[(int) $day['day']] ?? '';
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

                    <!-- Timer Overlay (if day is locked with countdown) -->
                    <?php if (!empty($day['countdown_seconds']) && empty($day['is_current'])): ?>
                        <div class="th-timer-overlay" data-th-timer data-th-timer="<?php echo (int) $day['countdown_seconds']; ?>">
                            <div class="th-timer-icon">⏳</div>
                            <span class="th-timer-label">Next Day</span>
                            <span class="th-timer-count" data-th-timer-count><?php echo htmlspecialchars(taskHubFormatDuration((int) $day['countdown_seconds']), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="th-timer-sub" data-th-timer-sub>until Day <?php echo (int) $day['day']; ?> unlocks</span>
                        </div>
                    <?php endif; ?>

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
                            $day_name = $day_titles_map[$day_num] ?? '';
                            $next_day_name = $day_titles_map[$next_day_num] ?? 'Mystery';
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
                                <?php if ($day_num < 10 && !empty($day['countdown_seconds'])): ?>
                                    <div class="th-next-day-preview">
                                        <span class="th-next-day-label">Up Next:</span>
                                        <strong>Day <?php echo $next_day_num; ?>: <?php echo htmlspecialchars($next_day_name, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                    <div class="th-timer-count" data-th-day-countdown="<?php echo (int) $day['countdown_seconds']; ?>">
                                        Unlocks in <?php echo htmlspecialchars(taskHubFormatDuration((int) $day['countdown_seconds']), ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php elseif ($day_num >= 10): ?>
                                    <p style="color:var(--th-gold);font-weight:700;margin-top:8px;">🏆 You've completed the entire 10-day mission! Check your rewards!</p>
                                <?php endif; ?>
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
                            // Determine premium card type based on task_key
                            $task_key_str = (string) ($task['task_key'] ?? '');
                            $is_checkin_task = strpos($task_key_str, '_checkin') !== false || strpos($task_key_str, '_check_in') !== false;
                            $is_social_task = strpos($task_key_str, 'social') !== false || strpos($task_key_str, 'share') !== false;
                            $is_quiz_task = ($task['verification_mode'] ?? '') === 'quiz';
                            
                            $premium_card_class = '';
                            $premium_badge_icon = '📋';
                            $premium_badge_text = 'Step ' . ($current_task_index + 1) . ' of ' . $total_tasks_in_day;
                            $premium_title = '';
                            $premium_desc = '';
                            
                            if ($is_checkin_task) {
                                $premium_card_class = 'is-checkin';
                                $premium_badge_icon = '📅';
                                $premium_badge_text = 'Daily Check-in';
                                $premium_title = '🔖 Daily Check-in';
                                $premium_desc = 'Check in to maintain your streak and earn rewards.';
                            } elseif ($is_social_task) {
                                $premium_card_class = 'is-social';
                                $premium_badge_icon = '🌐';
                                $premium_badge_text = 'Social Task';
                                $premium_title = '🌐 Social Engagement';
                                $premium_desc = 'Connect with the community and share your experience.';
                            } elseif ($is_quiz_task) {
                                $premium_card_class = 'is-quiz';
                                $premium_badge_icon = '🧠';
                                $premium_badge_text = 'Knowledge Quiz';
                                $premium_title = '🧠 Knowledge Check';
                                $premium_desc = 'Test your understanding of the platform.';
                            } else {
                                // All other tasks get premium treatment too
                                $verification_mode = (string) ($task['verification_mode'] ?? '');
                                $task_key_lower = strtolower($task_key_str);
                                
                                if (strpos($task_key_lower, 'profile') !== false || $verification_mode === 'profile') {
                                    $premium_card_class = 'is-profile';
                                    $premium_badge_icon = '👤';
                                    $premium_badge_text = 'Profile Setup';
                                    $premium_title = '👤 Complete Your Profile';
                                    $premium_desc = 'Set up your profile to unlock rewards and features.';
                                } elseif (strpos($task_key_lower, 'wallet') !== false || $verification_mode === 'wallet') {
                                    $premium_card_class = 'is-wallet';
                                    $premium_badge_icon = '💼';
                                    $premium_badge_text = 'Wallet Connect';
                                    $premium_title = '💼 Connect Your Wallet';
                                    $premium_desc = 'Link your wallet to prepare for future withdrawals.';
                                } elseif (strpos($task_key_lower, 'txhash') !== false || strpos($task_key_lower, 'volume') !== false || strpos($task_key_lower, 'hold') !== false || $verification_mode === 'manual') {
                                    $premium_card_class = 'is-proof';
                                    $premium_badge_icon = '📎';
                                    $premium_badge_text = 'Proof Submission';
                                    $premium_title = '📎 Submit Proof';
                                    $premium_desc = 'Provide evidence of your activity to earn rewards.';
                                } elseif (strpos($task_key_lower, 'mystery') !== false || $verification_mode === 'mystery') {
                                    $premium_card_class = 'is-mystery';
                                    $premium_badge_icon = '🎁';
                                    $premium_badge_text = 'Mystery Box';
                                    $premium_title = '🎁 Mystery Reward';
                                    $premium_desc = 'Pick a box to reveal your bonus reward!';
                                } elseif (strpos($task_key_lower, 'boosthub') !== false || $verification_mode === 'boosthub_redirect') {
                                    $premium_card_class = 'is-boosthub';
                                    $premium_badge_icon = '⚡';
                                    $premium_badge_text = 'BoostHub Task';
                                    $premium_title = '⚡ BoostHub Challenge';
                                    $premium_desc = 'Complete a challenge in BoostHub to earn rewards.';
                                } else {
                                    // Default premium card for any other task
                                    $premium_card_class = 'is-mission';
                                    $premium_badge_icon = '🎯';
                                    $premium_badge_text = 'Mission Task';
                                    $premium_title = '🎯 ' . htmlspecialchars((string) ($task['title'] ?? 'Mission Task'), ENT_QUOTES, 'UTF-8');
                                    $premium_desc = htmlspecialchars((string) ($task['description'] ?? 'Complete this task to progress through the mission.'), ENT_QUOTES, 'UTF-8');
                                }
                            }
                            ?>
                            
                            <div class="th-task-content th-premium-card <?php echo $premium_card_class; ?>" data-task-key="<?php echo htmlspecialchars($task_key_str, ENT_QUOTES, 'UTF-8'); ?>" data-verification-mode="<?php echo htmlspecialchars((string) $task['verification_mode'], ENT_QUOTES, 'UTF-8'); ?>" data-profile-complete="<?php echo ($task['verification_mode'] ?? '') === 'profile' ? (!empty($task['profile_complete']) ? '1' : '0') : ''; ?>">

                                <!-- Timer Overlay for locked tasks -->
                                <?php if ($is_timed_lock): ?>
                                    <div class="th-timer-overlay" data-th-timer data-th-timer="<?php echo (int) ($task['countdown_seconds'] ?? 0); ?>">
                                        <div class="th-timer-icon">🔒</div>
                                        <span class="th-timer-label">Next Task</span>
                                        <span class="th-timer-count" data-th-timer-count><?php echo htmlspecialchars(taskHubFormatDuration((int) $task['countdown_seconds']), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="th-timer-sub" data-th-timer-sub>until this task unlocks</span>
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
                                    <?php if (!$is_timed_lock && !$is_submitted && !$is_completed && !empty($task['learning_title']) && $has_learning_url): ?>
                                        <div class="th-learning-gate <?php echo !empty($task['learning_opened']) ? 'is-validated' : 'is-locked'; ?>" data-learning-gate data-task-key="<?php echo htmlspecialchars((string) $task['task_key'], ENT_QUOTES, 'UTF-8'); ?>" data-learning-opened="<?php echo !empty($task['learning_opened']) ? '1' : '0'; ?>">
                                            <span class="th-learning-label">📖 <?php echo htmlspecialchars((string) $task['learning_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <a href="<?php echo htmlspecialchars((string) $task['learning_url'], ENT_QUOTES, 'UTF-8'); ?>" class="th-learning-btn" target="_blank" rel="noopener noreferrer" data-learning-open>Open & Validate</a>
                                            <span class="th-learning-status" data-learning-status><?php echo !empty($task['learning_opened']) ? 'Learning validated ✓' : 'Not opened'; ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Beginner-friendly quiz instructions -->
                                    <?php if (!$is_timed_lock && !$is_submitted && !$is_completed && !empty($task['quiz'])): ?>
                                        <div class="th-quiz-instructions">
                                            <span class="th-quiz-instructions-icon">📖</span>
                                            <div class="th-quiz-instructions-text">
                                                <strong>Read first, then answer.</strong> Review the material above, then answer each question below. You must get all questions right to earn the reward. Click <strong>"Review Material"</strong> to re-read the content anytime.
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
                                                            <?php $letter = chr(65 + $choice_index); ?>
                                                            <label class="th-quiz-choice" data-choice>
                                                                <input type="radio" name="<?php echo htmlspecialchars((string) $task['task_key'], ENT_QUOTES, 'UTF-8'); ?>_q_<?php echo $question_index; ?>" value="<?php echo $choice_index; ?>" data-correct="<?php echo ((int) ($question['answer'] ?? -1) === $choice_index) ? '1' : '0'; ?>" hidden>
                                                                <span class="th-quiz-choice-marker"><?php echo $letter; ?></span>
                                                                <span class="th-quiz-choice-text"><?php echo htmlspecialchars((string) $choice, ENT_QUOTES, 'UTF-8'); ?></span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                <?php elseif (($task['verification_mode'] ?? '') === 'manual' && !$is_timed_lock && !$is_submitted && !$is_completed): ?>
                                    <!-- === PROOF SPECIFIC: Manual proof input — beginner-friendly === -->
                                    <div class="th-proof-section">
                                        <div class="th-proof-header">
                                            <span class="th-proof-icon">📎</span>
                                            <div class="th-proof-header-text">
                                                <strong>Submit Your Proof</strong>
                                                <span>Provide evidence of your activity for manual review</span>
                                            </div>
                                        </div>
                                        <div class="th-proof-tips">
                                            <div class="th-proof-tip">
                                                <span class="th-proof-tip-icon">🔗</span>
                                                <span>Paste a transaction hash (TX ID), screenshot URL, or any public proof link</span>
                                            </div>
                                            <div class="th-proof-tip">
                                                <span class="th-proof-tip-icon">👤</span>
                                                <span>Include your username or handle so we can verify</span>
                                            </div>
                                            <div class="th-proof-tip">
                                                <span class="th-proof-tip-icon">✅</span>
                                                <span>Make sure the transaction is worth at least <strong>10 USDT</strong></span>
                                            </div>
                                        </div>
                                        <div class="th-proof-input-wrap">
                                            <textarea class="th-premium-input th-premium-textarea task-proof-input" rows="3" placeholder="e.g. https://etherscan.io/tx/0x... or your exchange username"></textarea>
                                        </div>
                                    </div>


                                <?php elseif (($task['verification_mode'] ?? '') === 'wallet' && !$is_timed_lock && !$is_submitted && !$is_completed): ?>
                                    <!-- === WALLET SPECIFIC: Wallet input === -->
                                    <input type="text" class="th-premium-input task-wallet-input" placeholder="Enter your wallet address">

                                <?php elseif (($task['verification_mode'] ?? '') === 'mystery' && !$is_timed_lock && !$is_submitted && !$is_completed): ?>
                                    <!-- === MYSTERY SPECIFIC: Mystery boxes === -->
                                    <div class="th-mystery-area">
                                        <p>🎁 Pick a mystery box to reveal your reward!</p>
                                        <div class="th-mystery-boxes">
                                            <?php for ($b = 0; $b < 3; $b++): ?>
                                                <div class="th-mystery-box" data-box-index="<?php echo $b; ?>">
                                                    <div class="th-mystery-box-inner">
                                                        <div class="th-mystery-box-front">
                                                            <span class="th-mystery-box-icon">🎁</span>
                                                            <span class="th-mystery-box-label">Pick Me!</span>
                                                        </div>
                                                        <div class="th-mystery-box-back">
                                                            <span class="th-mystery-box-icon">💰</span>
                                                            <span class="th-mystery-box-reward" data-box-reward>0 $REX</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="th-mystery-result" id="mysteryResult" hidden>
                                            <div class="th-mystery-result-icon">🎉</div>
                                            <strong id="mysteryResultText">You won 0 $REX!</strong>
                                            <p id="mysteryResultSub">Click Claim to add to your balance.</p>
                                        </div>
                                    </div>

                                <?php elseif (($task['verification_mode'] ?? '') === 'boosthub_redirect' && !$is_timed_lock && !$is_submitted && !$is_completed): ?>
                                    <!-- === BOOSTHUB SPECIFIC: Redirect link === -->
                                    <a href="<?php echo BASE_URL; ?>/boosthub.php" class="th-premium-btn" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;margin-top:12px;">
                                        ⚡ Open BoostHub
                                    </a>

                                <?php elseif (!$is_timed_lock && !$is_submitted && !$is_completed && !empty($task['learning_title'])): ?>
                                    <!-- === LEARNING GATE (for non-quiz tasks like day2_ui_exploration) === -->
                                    <div class="th-learning-gate <?php echo !empty($task['learning_opened']) ? 'is-validated' : 'is-locked'; ?>" data-learning-gate data-task-key="<?php echo htmlspecialchars((string) $task['task_key'], ENT_QUOTES, 'UTF-8'); ?>" data-learning-opened="<?php echo !empty($task['learning_opened']) ? '1' : '0'; ?>">
                                        <span class="th-learning-label">📖 <?php echo htmlspecialchars((string) $task['learning_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if (!empty($task['learning_url'])): ?>
                                            <a href="<?php echo htmlspecialchars((string) $task['learning_url'], ENT_QUOTES, 'UTF-8'); ?>" class="th-learning-btn" target="_blank" rel="noopener noreferrer" data-learning-open>Open & Validate</a>
                                        <?php endif; ?>
                                        <span class="th-learning-status" data-learning-status><?php echo !empty($task['learning_opened']) ? 'Learning validated ✓' : 'Not opened'; ?></span>
                                    </div>
                                <?php endif; ?>

                                <!-- Reward Badge (shown for all task types) -->
                                <div class="th-premium-reward">
                                    💰 +<?php echo number_format((float) ($task['reward'] ?? 0), 2); ?> $REX
                                </div>

                                <!-- Status Message -->
                                <?php if (!empty($task['status_message'])): ?>
                                    <p style="color:var(--th-text-muted);font-size:13px;margin-top:12px;"><?php echo htmlspecialchars((string) $task['status_message'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>

                                <!-- Premium Footer with Submit Button -->
                                <div class="th-premium-footer">
                                    <span class="th-premium-step">Step <?php echo $current_task_index + 1; ?> of <?php echo $total_tasks_in_day; ?></span>
                                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                                        <?php if (($task['verification_mode'] ?? '') === 'boosthub_redirect' && !$is_completed): ?>
                                            <a href="<?php echo BASE_URL; ?>/boosthub.php" class="th-premium-btn">Open BoostHub</a>
                                        <?php endif; ?>
                                        <?php if (!$is_timed_lock && !$is_completed): ?>
                                            <button type="button" class="th-premium-btn <?php echo $is_submitted ? 'is-done' : ''; ?>" data-submit-task <?php echo $is_submitted ? 'disabled' : ''; ?>>
                                                <?php
                                                if ($is_submitted) {
                                                    echo '✓ Submitted';
                                                } elseif (($task['verification_mode'] ?? '') === 'mystery') {
                                                    echo 'Claim Reward';
                                                } elseif ($is_checkin_task) {
                                                    echo '✅ Check In Now';
                                                } elseif ($is_quiz_task) {
                                                    echo 'Submit Quiz →';
                                                } else {
                                                    echo 'Submit Task →';
                                                }
                                                ?>
                                            </button>
                                        <?php elseif ($is_completed): ?>
                                            <button type="button" class="th-premium-btn is-done" disabled>✓ Completed</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- ============================================================
             PROGRESS SUMMARY (below hero card)
             ============================================================ -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:20px;">
            <div style="background:var(--th-surface);border:1px solid var(--th-border);border-radius:var(--th-radius);padding:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:12px;color:var(--th-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Overall Progress</span>
                    <strong style="font-size:13px;color:var(--th-text-primary);"><?php echo (int) ($state['overall_completed_tasks'] ?? 0); ?>/<?php echo (int) ($state['overall_total_tasks'] ?? 0); ?></strong>
                </div>
                <div style="height:6px;border-radius:3px;background:rgba(255,255,255,0.06);overflow:hidden;">
                    <span style="display:block;height:100%;border-radius:3px;background:linear-gradient(90deg,var(--th-blue),#60a5fa);width:<?php echo (int) ($state['overall_progress_percent'] ?? 0); ?>%;transition:width 0.5s ease;"></span>
                </div>
            </div>
            <div style="background:var(--th-surface);border:1px solid var(--th-border);border-radius:var(--th-radius);padding:16px;display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <span style="font-size:12px;color:var(--th-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Status</span>
                    <strong style="display:block;margin-top:4px;font-size:14px;color:var(--th-text-primary);" id="progressStatusValue"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($state['status'] ?? 'in_progress'))), ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div style="text-align:right;">
                    <span style="font-size:12px;color:var(--th-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:0.05em;">Mission</span>
                    <strong style="display:block;margin-top:4px;font-size:14px;color:<?php echo !empty($state['paused']) ? 'var(--th-gold)' : 'var(--th-green)'; ?>;"><?php echo !empty($state['paused']) ? '⏸ Paused' : '▶ On Track'; ?></strong>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>
</main>

<!-- ============================================================
     MODAL
     ============================================================ -->
<div class="taskhub-modal" id="taskhubModal" hidden>
    <div class="taskhub-modal-backdrop" data-modal-close></div>
    <div class="taskhub-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="taskhubModalTitle">
        <div class="taskhub-modal-head">
            <h3 id="taskhubModalTitle">TaskHub</h3>
            <button type="button" class="taskhub-modal-close" data-modal-close aria-label="Close message">x</button>
        </div>
        <p class="taskhub-modal-message" id="taskhubModalMessage"></p>
        <div class="page-actions">
            <button type="button" class="primary-btn" id="taskhubModalAction">Okay</button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/taskhub/greeting-modal.php'; ?>
<?php require_once __DIR__ . '/includes/taskhub/mystery-box.php'; ?>

<script src="<?php echo ASSETS_URL; ?>/js/taskhub-premium.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
