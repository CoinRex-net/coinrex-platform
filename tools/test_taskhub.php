<?php
/**
 * TaskHub — Test Page v2.0
 * 
 * This is a drop-in replacement for taskhub.php that uses the test API endpoints.
 * All original UI is preserved. To use, copy this file over taskhub.php and
 * update the JS/CSS paths to point to the test versions.
 * 
 * For testing: visit /test_taskhub.php directly
 */

// Bootstrap
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/test_includes/functions/taskhub.php';

session_start();

// Auth check
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/auth/auth.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$db = getDBConnection();

// Get user
$user = getUserById($user_id);
if (!$user) {
    session_destroy();
    header('Location: ' . BASE_URL . '/auth/auth.php');
    exit;
}

// Get TaskHub state
try {
    $state = getTaskHubState($user_id, $db);
    $days = $state['days'];
    $current_day = $state['current_day'];
    $total_days = $state['total_days'];
    $status = $state['status'];
    $status_message = $state['status_message'];
    $progress = $state['progress'];
    $completed_tasks = $state['completed_tasks'];
    $total_tasks = $state['total_tasks'];
} catch (Throwable $e) {
    $days = [];
    $current_day = 1;
    $total_days = 10;
    $status = 'error';
    $status_message = 'Error loading TaskHub state.';
    $progress = 0;
    $completed_tasks = 0;
    $total_tasks = 0;
}

// Get reward summary
try {
    $reward_summary = getTaskHubRewardSummary($user_id, $db);
} catch (Throwable $e) {
    $reward_summary = ['total_earned' => 0, 'phase1_cap' => 50, 'remaining' => 50];
}

$page_title = 'TaskHub - MicroMission';
require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/taskhub.css">

<div class="th-container">
    <!-- HEADER -->
    <div class="th-header">
        <div class="th-header-left">
            <h1 class="th-title">🚀 MicroMission</h1>
            <p class="th-subtitle">Complete daily tasks, earn <strong>$REX</strong> rewards</p>
        </div>
        <div class="th-header-right">
            <div class="th-reward-badge">
                <span class="th-reward-label">Earned</span>
                <span class="th-reward-amount"><?php echo number_format($reward_summary['total_earned'], 2); ?> $REX</span>
                <span class="th-reward-cap">of <?php echo number_format($reward_summary['phase1_cap'], 2); ?> $REX cap</span>
            </div>
        </div>
    </div>

    <!-- PROGRESS BAR -->
    <div class="th-progress-section">
        <div class="th-progress-header">
            <span class="th-progress-label">Mission Progress</span>
            <span class="th-progress-percent"><?php echo (int) $progress; ?>%</span>
        </div>
        <div class="th-progress-track">
            <div class="th-progress-fill" style="width: <?php echo (int) $progress; ?>%;"></div>
        </div>
        <div class="th-progress-stats">
            <span><?php echo (int) $completed_tasks; ?> / <?php echo (int) $total_tasks; ?> tasks</span>
            <span id="missionStatusText"><?php echo htmlspecialchars($status_message, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    </div>

    <!-- DAY NAVIGATION DOTS -->
    <div class="th-day-nav">
        <?php foreach ($days as $day_data): 
            $day = (int) $day_data['day'];
            $is_current = $day_data['is_current'];
            $is_locked = $day_data['is_locked'];
            $is_completed = $day_data['is_completed'];
            $dot_class = 'th-day-dot';
            if ($is_current) $dot_class .= ' is-active';
            if ($is_completed) $dot_class .= ' is-completed';
            if ($is_locked) $dot_class .= ' is-locked';
        ?>
            <button class="<?php echo $dot_class; ?>" data-th-day="<?php echo $day; ?>" 
                    onclick="taskhubSelectDay(<?php echo $day; ?>)"
                    <?php echo $is_locked ? 'disabled' : ''; ?>>
                <span class="th-day-number"><?php echo $day; ?></span>
                <span class="th-day-label">Day <?php echo $day; ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- DAY PANELS -->
    <?php foreach ($days as $day_data): 
        $day = (int) $day_data['day'];
        $is_current = $day_data['is_current'];
        $is_locked = $day_data['is_locked'];
        $is_completed = $day_data['is_completed'];
        $tasks = $day_data['tasks'];
    ?>
        <div class="th-day-panel" data-th-day-panel="<?php echo $day; ?>" <?php echo $day !== $current_day ? 'hidden' : ''; ?>>
            <div class="th-day-header">
                <h2 class="th-day-title">Day <?php echo $day; ?></h2>
                <?php if ($is_completed): ?>
                    <span class="th-day-badge th-day-badge-completed">✅ Completed</span>
                <?php elseif ($is_current): ?>
                    <span class="th-day-badge th-day-badge-current">● Active</span>
                <?php elseif ($is_locked): ?>
                    <span class="th-day-badge th-day-badge-locked">🔒 Locked</span>
                <?php endif; ?>
            </div>

            <div class="th-tasks">
                <?php foreach ($tasks as $task): 
                    $task_key = $task['task_key'];
                    $task_status = $task['status'];
                    $verification_mode = $task['verification_mode'];
                    $requires_quiz = $task['requires_quiz'];
                    $requires_manual_review = $task['requires_manual_review'];
                    $learning_url = $task['learning_url'];
                    $learning_title = $task['learning_title'];
                    $has_quiz = $task['has_quiz'];
                    $log_metadata = $task['log_metadata'];
                    $learning_opened = !empty($log_metadata['learning_opened']);
                    $is_check_in = strpos($task_key, '_check_in') !== false;
                    $is_mystery = $task_key === 'day10_mystery';

                    $card_class = 'th-hero-card';
                    if ($task_status === 'completed') $card_class .= ' is-completed';
                    if ($task_status === 'submitted') $card_class .= ' is-submitted';
                    if ($task_status === 'failed') $card_class .= ' is-failed';
                ?>
                    <div class="<?php echo $card_class; ?>" data-task-key="<?php echo $task_key; ?>" data-verification-mode="<?php echo $verification_mode; ?>">
                        <div class="th-hero-card-header">
                            <div class="th-hero-card-title-row">
                                <h3 class="th-hero-card-title"><?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <span class="th-hero-card-reward">+<?php echo number_format($task['reward'], 2); ?> $REX</span>
                            </div>
                            <p class="th-hero-card-desc"><?php echo htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>

                        <div class="th-hero-card-body">
                            <!-- STATUS BADGE -->
                            <?php if ($task_status === 'completed'): ?>
                                <div class="th-status-badge th-status-completed">✅ Completed</div>
                            <?php elseif ($task_status === 'submitted'): ?>
                                <div class="th-status-badge th-status-submitted">⏳ Under Review</div>
                            <?php elseif ($task_status === 'failed'): ?>
                                <div class="th-status-badge th-status-failed">❌ Failed</div>
                            <?php endif; ?>

                            <!-- LEARNING GATE -->
                            <?php if ($learning_url !== '' && $task_status !== 'completed'): ?>
                                <div class="th-learning-gate" data-learning-gate>
                                    <div class="th-learning-header">
                                        <span class="th-learning-label">📖 Learning Required</span>
                                        <span class="th-learning-status" data-learning-status></span>
                                    </div>
                                    
                                    <!-- Timer Overlay -->
                                    <div class="th-timer-overlay" data-th-timer hidden>
                                        <div class="th-timer-bar">
                                            <div class="th-timer-progress" data-th-timer-progress style="width:0%;"></div>
                                        </div>
                                        <span class="th-timer-text" data-th-timer-text></span>
                                    </div>

                                    <button class="th-btn th-btn-secondary" data-start-learning>
                                        <?php echo htmlspecialchars($learning_title ?: 'Start Learning', ENT_QUOTES, 'UTF-8'); ?>
                                    </button>
                                    
                                    <iframe class="th-learning-frame" data-learning-frame hidden></iframe>
                                    
                                    <button class="th-btn th-btn-primary" data-validate-learning hidden>
                                        ✅ Validate Learning
                                    </button>
                                </div>
                            <?php endif; ?>

                            <!-- QUIZ BLOCK -->
                            <?php if ($has_quiz && $task_status !== 'completed'): ?>
                                <?php 
                                $definition = getTaskHubMissionTaskDefinitionByKey($task_key);
                                $quiz = $definition ? shuffleQuizChoices($definition['quiz'] ?? [], (string) $user_id . '_' . $task_key) : [];
                                ?>
                                <div class="th-quiz-block" data-quiz-block <?php echo (!$learning_opened && $learning_url !== '') ? 'hidden' : ''; ?>>
                                    <h4 class="th-quiz-title">📝 Quick Quiz</h4>
                                    <?php foreach ($quiz as $q_idx => $question): ?>
                                        <div class="th-quiz-question" data-quiz-question>
                                            <p class="th-quiz-question-text"><?php echo htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <div class="th-quiz-choices">
                                                <?php foreach ($question['choices'] as $c_idx => $choice): ?>
                                                    <label class="th-quiz-choice" data-choice>
                                                        <input type="radio" name="q_<?php echo $q_idx; ?>" value="<?php echo $c_idx; ?>" hidden>
                                                        <span class="th-choice-indicator"></span>
                                                        <span class="th-choice-text"><?php echo htmlspecialchars($choice, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <button class="th-btn th-btn-secondary" data-quiz-submit>📋 Review Answers</button>
                                    <p class="th-quiz-feedback" data-quiz-feedback></p>
                                </div>
                            <?php endif; ?>

                            <!-- SOCIAL FOLLOW INPUTS -->
                            <?php if ($task_key === 'day1_social_follow' && $task_status !== 'completed'): ?>
                                <div class="th-task-inputs">
                                    <div class="th-input-group">
                                        <label class="th-input-label">X (Twitter) Username</label>
                                        <input type="text" class="th-input" data-x-handle placeholder="@username" autocomplete="off">
                                    </div>
                                    <div class="th-input-group">
                                        <label class="th-input-label">Telegram Username</label>
                                        <input type="text" class="th-input" data-telegram-handle placeholder="@username" autocomplete="off">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- SHARE EXPERIENCE INPUTS -->
                            <?php if ($task_key === 'day3_share_experience' && $task_status !== 'completed'): ?>
                                <div class="th-task-inputs">
                                    <div class="th-input-group">
                                        <label class="th-input-label">Platform</label>
                                        <select class="th-input" data-share-platform>
                                            <option value="">Select platform...</option>
                                            <option value="x">X (Twitter)</option>
                                            <option value="facebook">Facebook</option>
                                            <option value="binance_square">Binance Square</option>
                                            <option value="medium">Medium</option>
                                            <option value="reddit">Reddit</option>
                                        </select>
                                    </div>
                                    <div class="th-input-group">
                                        <label class="th-input-label">Post URL</label>
                                        <input type="url" class="th-input" data-share-proof-url placeholder="https://..." autocomplete="off">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- WALLET INPUT -->
                            <?php if ($verification_mode === 'wallet' && $task_status !== 'completed'): ?>
                                <div class="th-task-inputs">
                                    <div class="th-input-group">
                                        <label class="th-input-label">Wallet Address (BEP-20)</label>
                                        <input type="text" class="th-input task-wallet-input" placeholder="0x..." autocomplete="off">
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- PROOF INPUT -->
                            <?php if ($verification_mode === 'manual' && $task_status !== 'completed' && !in_array($task_key, ['day1_social_follow', 'day3_share_experience'])): ?>
                                <div class="th-task-inputs">
                                    <div class="th-input-group">
                                        <label class="th-input-label">Proof / Notes</label>
                                        <textarea class="th-input task-proof-input" rows="2" placeholder="Provide proof or notes for admin review..."></textarea>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- SUBMIT BUTTON -->
                            <?php if ($task_status !== 'completed' && $task_status !== 'submitted'): ?>
                                <button class="th-btn th-btn-primary th-submit-btn" data-submit-task>
                                    <?php if ($is_check_in): ?>
                                        ✅ Check In
                                    <?php elseif ($is_mystery): ?>
                                        🎁 Open Mystery Box
                                    <?php else: ?>
                                        🚀 Submit Task
                                    <?php endif; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- GREETING MODAL -->
<div id="greetingModal" class="th-modal-overlay" hidden>
    <div class="th-modal-content">
        <button class="th-modal-close" data-greeting-close>&times;</button>
        <div class="th-modal-body">
            <div class="th-greeting-icon">👋</div>
            <h2 id="greetingTitle" class="th-greeting-title">Day 1 - Ready to Go!</h2>
            <p id="greetingMessage" class="th-greeting-message">Great start! Let's complete today's tasks.</p>
            <div class="th-greeting-day">Day <span id="greetingDayNumber">1</span></div>
            <button id="greetingAction" class="th-btn th-btn-primary">Let's Go! 🚀</button>
        </div>
    </div>
</div>

<!-- MYSTERY BOX MODAL -->
<div id="mysteryModal" class="th-modal-overlay" hidden>
    <div class="th-modal-content th-mystery-content">
        <div class="th-mystery-header">
            <h2>🎁 Mystery Box Reward</h2>
            <p>Pick a box to reveal your bonus reward!</p>
        </div>
        <div class="th-mystery-boxes">
            <div class="th-mystery-box" data-box-index="0">
                <div class="th-mystery-box-inner">
                    <div class="th-mystery-box-front">🎁</div>
                    <div class="th-mystery-box-back">
                        <span class="th-mystery-reward" data-box-reward></span>
                    </div>
                </div>
            </div>
            <div class="th-mystery-box" data-box-index="1">
                <div class="th-mystery-box-inner">
                    <div class="th-mystery-box-front">🎁</div>
                    <div class="th-mystery-box-back">
                        <span class="th-mystery-reward" data-box-reward></span>
                    </div>
                </div>
            </div>
            <div class="th-mystery-box" data-box-index="2">
                <div class="th-mystery-box-inner">
                    <div class="th-mystery-box-front">🎁</div>
                    <div class="th-mystery-box-back">
                        <span class="th-mystery-reward" data-box-reward></span>
                    </div>
                </div>
            </div>
        </div>
        <div id="mysteryResult" class="th-mystery-result" hidden>
            <p id="mysteryResultText" class="th-mystery-result-text"></p>
            <p id="mysteryResultSub" class="th-mystery-result-sub"></p>
            <button id="mysteryClaimBtn" class="th-btn th-btn-primary" hidden>Claim Reward</button>
        </div>
        <div id="mysteryConfetti" class="th-confetti-container"></div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/test_assets/js/taskhub-premium.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
