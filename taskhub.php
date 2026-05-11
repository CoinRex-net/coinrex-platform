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

<main class="reward-page">
    <div class="reward-page-shell">
        <section class="reward-panel">
            <div>
                <span class="reward-tag">TaskHub</span>
                <h1>10-day mission board</h1>
                <div class="page-actions">
                    <a href="<?php echo BASE_URL; ?>/dashboard.php" class="secondary-btn">Back to Dashboard</a>
                    <a href="<?php echo BASE_URL; ?>/boosthub.php" class="secondary-btn">Open BoostHub</a>
                </div>
            </div>
            <div class="reward-balance-box">
                <span>Current Day</span>
                <strong id="currentDayValue"><?php echo number_format((int) ($state['current_day'] ?? 1)); ?></strong>
                <p class="reward-note" id="missionStatusText"><?php echo htmlspecialchars((string) ($state['status_message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="taskhub-progress-block">
                    <div class="taskhub-progress-label-row">
                        <span>Current Day Progress</span>
                        <strong><?php echo (int) ($state['completed_tasks'] ?? 0); ?>/<?php echo (int) ($state['total_tasks'] ?? 0); ?></strong>
                    </div>
                    <div class="taskhub-progress-track" aria-hidden="true">
                        <span class="taskhub-progress-fill" style="width: <?php echo (int) ($state['current_day_progress_percent'] ?? 0); ?>%;"></span>
                    </div>
                </div>
            </div>
        </section>

        <?php if (($state['access'] ?? '') !== 'open'): ?>
            <section class="task-card">
                <h3>TaskHub closed</h3>
                <p><?php echo htmlspecialchars((string) ($state['message'] ?? 'TaskHub is not available for this account.'), ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
        <?php else: ?>
            <section class="reward-grid taskhub-progress-grid">
                <div class="task-card">
                    <h3>Progress</h3>
                    <div class="taskhub-progress-block">
                        <div class="taskhub-progress-label-row">
                            <span>Overall Progress</span>
                            <strong><?php echo (int) ($state['overall_completed_tasks'] ?? 0); ?>/<?php echo (int) ($state['overall_total_tasks'] ?? 0); ?> tasks</strong>
                        </div>
                        <div class="taskhub-progress-track" aria-hidden="true">
                            <span class="taskhub-progress-fill" style="width: <?php echo (int) ($state['overall_progress_percent'] ?? 0); ?>%;"></span>
                        </div>
                        <div class="metric-grid">
                            <div class="mini-metric">
                                <span>Status</span>
                                <strong id="progressStatusValue"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($state['status'] ?? 'in_progress'))), ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div class="mini-metric">
                                <span>Mission</span>
                                <strong><?php echo !empty($state['paused']) ? 'Paused' : 'On Track'; ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="taskhub-step-shell" aria-label="Mission days">
                <div class="taskhub-step-header">
                    <div>
                        <span class="reward-tag">Mission Days</span>
                        <h2>Choose a day</h2>
                    </div>
                    <p class="reward-note">Only the selected day is shown below.</p>
                </div>
                <div class="taskhub-step-strip" id="taskhubDaySelector" role="tablist" aria-label="TaskHub day selector">
                    <?php foreach (($state['days'] ?? []) as $day): ?>
                        <button
                            type="button"
                            class="taskhub-step-btn <?php echo !empty($day['is_current']) ? 'is-selected' : ''; ?>"
                            data-day-trigger="<?php echo (int) $day['day']; ?>"
                            data-day-locked="<?php echo (int) (empty($day['is_current']) && empty($day['is_past'])); ?>"
                            role="tab"
                            aria-selected="<?php echo !empty($day['is_current']) ? 'true' : 'false'; ?>"
                            <?php echo empty($day['is_current']) && empty($day['is_past']) ? 'disabled' : ''; ?>
                        >
                            <span class="taskhub-step-kicker">Day <?php echo (int) $day['day']; ?></span>
                            <strong><?php echo htmlspecialchars((string) ($day['title'] ?? ('Day ' . (int) $day['day'])), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </button>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="taskhub-days-grid" id="taskhubDaysGrid">
                <?php foreach (($state['days'] ?? []) as $day): ?>
                    <?php if (empty($day['is_current']) && empty($day['is_past'])): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <article
                        class="taskhub-day-card taskhub-day-<?php echo htmlspecialchars((string) $day['status'], ENT_QUOTES, 'UTF-8'); ?>"
                        data-day-panel="<?php echo (int) $day['day']; ?>"
                        <?php echo !empty($day['is_current']) ? '' : 'hidden'; ?>
                    >
                        <div class="taskhub-day-head">
                            <div>
                                <span class="taskhub-day-label">Day <?php echo (int) $day['day']; ?></span>
                                <h3><?php echo htmlspecialchars((string) ($day['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                            </div>
                            <span class="status-chip"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $day['status'])), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>

                        <div class="taskhub-day-meta">
                            <span><?php echo (int) ($day['completed_tasks'] ?? 0); ?>/<?php echo (int) ($day['total_tasks'] ?? 0); ?> tasks done</span>
                            <span><?php echo htmlspecialchars((string) ($day['status_message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php if (!empty($day['countdown_seconds'])): ?>
                                <span class="taskhub-day-countdown" data-day-countdown-seconds="<?php echo (int) $day['countdown_seconds']; ?>">Unlocks in <?php echo htmlspecialchars(taskHubFormatDuration((int) $day['countdown_seconds']), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="taskhub-task-list">
                            <?php foreach (($day['tasks'] ?? []) as $task): ?>
                                <?php $collapse_task = !empty($day['is_current']) && ($task['status'] ?? '') === 'completed'; ?>
                                <?php $is_timed_lock = ($task['status'] ?? '') === 'locked' && !empty($task['countdown_seconds']); ?>
                                <div class="taskhub-task-row <?php echo $collapse_task ? 'is-condensed' : ''; ?> <?php echo $is_timed_lock ? 'is-timer-locked' : ''; ?>" data-task-key="<?php echo htmlspecialchars((string) $task['task_key'], ENT_QUOTES, 'UTF-8'); ?>" data-verification-mode="<?php echo htmlspecialchars((string) $task['verification_mode'], ENT_QUOTES, 'UTF-8'); ?>" data-profile-complete="<?php echo ($task['verification_mode'] ?? '') === 'profile' ? (!empty($task['profile_complete']) ? '1' : '0') : ''; ?>">
                                    <div class="taskhub-task-copy">
                                        <div class="taskhub-task-title-row">
                                            <span class="taskhub-task-mark <?php echo ($task['status'] ?? '') === 'completed' ? 'is-complete' : (($task['status'] ?? '') === 'locked' ? 'is-locked' : ''); ?>">
                                                <?php echo ($task['status'] ?? '') === 'completed' ? '&#10003;' : ((int) ($task['mission_step'] ?? 0) + 1); ?>
                                            </span>
                                            <div>
                                                <strong><?php echo htmlspecialchars((string) $task['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <?php if (!$is_timed_lock): ?>
                                                    <span><?php echo htmlspecialchars((string) ($task['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($is_timed_lock): ?>
                                            <div class="taskhub-unlock-banner">
                                                <span class="taskhub-unlock-kicker">Next Task</span>
                                                <strong class="task-countdown" data-countdown-seconds="<?php echo (int) ($task['countdown_seconds'] ?? 0); ?>">Will unlock in <?php echo htmlspecialchars(taskHubFormatDuration((int) $task['countdown_seconds']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            </div>
                                        <?php elseif (!$collapse_task): ?>
                                            <div class="task-meta">
                                                <span>Reward: <?php echo number_format((float) ($task['reward'] ?? 0), 2); ?> $REX</span>
                                                <span class="task-message"><?php echo htmlspecialchars((string) ($task['status_message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="task-countdown" data-countdown-seconds="<?php echo (int) ($task['countdown_seconds'] ?? 0); ?>"><?php echo !empty($task['countdown_seconds']) ? 'Next task unlocks in ' . htmlspecialchars(taskHubFormatDuration((int) $task['countdown_seconds']), ENT_QUOTES, 'UTF-8') : ''; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!$collapse_task && !$is_timed_lock && !empty($task['learning_title'])): ?>
                                            <div class="taskhub-inline-actions taskhub-learn-gate" data-learning-gate data-task-key="<?php echo htmlspecialchars((string) $task['task_key'], ENT_QUOTES, 'UTF-8'); ?>" data-learning-opened="<?php echo !empty($task['learning_opened']) ? '1' : '0'; ?>">
                                                <span class="reward-note"><?php echo htmlspecialchars((string) $task['learning_title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php if (!empty($task['learning_url'])): ?>
                                                    <a
                                                        href="<?php echo htmlspecialchars((string) $task['learning_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        class="secondary-btn taskhub-mini-btn"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        data-learning-open
                                                    >Open & Validate</a>
                                                <?php endif; ?>
                                                <span class="taskhub-learn-status" data-learning-status><?php echo !empty($task['learning_opened']) ? 'Learning validated' : 'Not opened'; ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (($task['task_key'] ?? '') === 'day1_social_follow' && !empty($day['is_current']) && !$collapse_task && !$is_timed_lock): ?>
                                            <div class="taskhub-social-proof">
                                                <div class="taskhub-social-links">
                                                    <a href="https://x.com" class="taskhub-social-link" target="_blank" rel="noopener noreferrer"><i class="fab fa-x-twitter"></i><span>X</span></a>
                                                    <a href="https://t.me" class="taskhub-social-link" target="_blank" rel="noopener noreferrer"><i class="fab fa-telegram-plane"></i><span>Telegram</span></a>
                                                </div>
                                                <p class="taskhub-social-note">Follow one of the official social channels, then share your personal or official handle below. Our team will manually review the follow proof.</p>
                                                <div class="taskhub-social-field-grid">
                                                    <input type="text" class="task-social-input" data-x-handle placeholder="X username or URL">
                                                    <input type="text" class="task-social-input" data-telegram-handle placeholder="Telegram username or URL">
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (($task['verification_mode'] ?? '') === 'quiz' && !empty($task['quiz']) && !empty($day['is_current']) && !$collapse_task && !$is_timed_lock): ?>
                                        <div class="task-quiz" data-quiz-block <?php echo empty($task['learning_opened']) ? 'hidden' : ''; ?>>
                                            <?php foreach ($task['quiz'] as $question_index => $question): ?>
                                                <div class="task-quiz-question" data-quiz-question data-question-index="<?php echo (int) $question_index; ?>" <?php echo $question_index > 0 ? 'hidden' : ''; ?>>
                                                    <strong><?php echo ($question_index + 1) . '. ' . htmlspecialchars((string) $question['question'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    <?php foreach (($question['choices'] ?? []) as $choice_index => $choice): ?>
                                                        <label class="checkbox-inline"><input type="radio" name="<?php echo htmlspecialchars((string) $task['task_key'], ENT_QUOTES, 'UTF-8'); ?>_q_<?php echo $question_index; ?>" value="<?php echo $choice_index; ?>" data-correct="<?php echo ((int) ($question['answer'] ?? -1) === $choice_index) ? '1' : '0'; ?>"> <?php echo htmlspecialchars((string) $choice, ENT_QUOTES, 'UTF-8'); ?></label>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                        <?php if (($task['task_key'] ?? '') === 'day3_share_experience' && !empty($day['is_current']) && !$collapse_task && !$is_timed_lock): ?>
                                            <div class="taskhub-social-proof">
                                                <div class="taskhub-social-links">
                                                    <a href="https://x.com" class="taskhub-social-link" target="_blank" rel="noopener noreferrer"><i class="fab fa-x-twitter"></i><span>X (Twitter)</span></a>
                                                    <a href="https://facebook.com" class="taskhub-social-link" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook-f"></i><span>Facebook</span></a>
                                                    <a href="https://www.binance.com/en/square" class="taskhub-social-link" target="_blank" rel="noopener noreferrer"><i class="fas fa-chart-line"></i><span>Binance Square</span></a>
                                                    <a href="https://medium.com" class="taskhub-social-link" target="_blank" rel="noopener noreferrer"><i class="fab fa-medium"></i><span>Medium</span></a>
                                                    <a href="https://reddit.com" class="taskhub-social-link" target="_blank" rel="noopener noreferrer"><i class="fab fa-reddit-alien"></i><span>Reddit</span></a>
                                                </div>
                                                <div class="taskhub-social-field-grid">
                                                    <select class="task-social-input" data-share-platform>
                                                        <option value="">Select platform</option>
                                                        <option value="x">X (Twitter)</option>
                                                        <option value="facebook">Facebook</option>
                                                        <option value="binance_square">Binance Square</option>
                                                        <option value="medium">Medium</option>
                                                        <option value="reddit">Reddit</option>
                                                    </select>
                                                    <input type="url" class="task-social-input" data-share-proof-url placeholder="Paste your public post URL">
                                                </div>
                                            </div>
                                        <?php elseif (($task['verification_mode'] ?? '') === 'manual' && !empty($day['is_current']) && !$collapse_task && !$is_timed_lock && ($task['task_key'] ?? '') !== 'day1_social_follow'): ?>
                                            <textarea class="task-proof-input" rows="4" placeholder="Paste proof link and your official/personal handle for manual review"></textarea>
                                    <?php endif; ?>

                                    <?php if (($task['verification_mode'] ?? '') === 'wallet' && !empty($day['is_current']) && !$collapse_task && !$is_timed_lock): ?>
                                        <input type="text" class="task-wallet-input" placeholder="Wallet address">
                                    <?php endif; ?>

                                    <?php if (!empty($day['is_current']) && !$collapse_task && !$is_timed_lock): ?>
                                        <div class="page-actions">
                                            <?php if (($task['verification_mode'] ?? '') === 'boosthub_redirect'): ?>
                                                <a href="<?php echo BASE_URL; ?>/boosthub.php" class="secondary-btn">Open BoostHub</a>
                                            <?php endif; ?>
                                            <button
                                                type="button"
                                                class="primary-btn task-submit-btn"
                                                data-submit-task
                                                <?php echo in_array((string) $task['status'], ['completed', 'submitted', 'locked'], true) || ($task['verification_mode'] ?? '') === 'boosthub_redirect' ? 'disabled' : ''; ?>
                                            >
                                                <?php
                                                if (($task['status'] ?? '') === 'submitted') {
                                                    echo 'Awaiting Review';
                                                } elseif (($task['status'] ?? '') === 'completed') {
                                                    echo 'Completed';
                                                } else {
                                                    echo 'Submit';
                                                }
                                                ?>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <?php if (($task['verification_mode'] ?? '') === 'mystery'): ?>
                                    <div class="taskhub-inline-actions">
                                        <span class="reward-note">Mystery Reward Box</span>
                                        <span style="font-size:24px;" aria-hidden="true">🎁</span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

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

<script>
(function() {
    const submitUrl = <?php echo json_encode(BASE_URL . '/api/submit_taskhub_task.php'); ?>;
    const learnMarkUrl = <?php echo json_encode(BASE_URL . '/api/mark_taskhub_learning.php'); ?>;
    const dayTriggers = Array.from(document.querySelectorAll('[data-day-trigger]'));
    const dayPanels = Array.from(document.querySelectorAll('[data-day-panel]'));
    const modal = document.getElementById('taskhubModal');
    const modalTitle = document.getElementById('taskhubModalTitle');
    const modalMessage = document.getElementById('taskhubModalMessage');
    const modalAction = document.getElementById('taskhubModalAction');

    function closeModal() {
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modalAction.onclick = null;
    }

    function showModal(title, message, onConfirm) {
        if (!modal || !modalTitle || !modalMessage || !modalAction) {
            return;
        }
        modalTitle.textContent = title;
        modalMessage.textContent = message;
        modal.hidden = false;
        modalAction.onclick = function() {
            closeModal();
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        };
    }

    document.querySelectorAll('[data-modal-close]').forEach((element) => {
        element.addEventListener('click', closeModal);
    });

    function formatDuration(totalSeconds) {
        const seconds = Math.max(0, Number(totalSeconds) || 0);
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainingSeconds = seconds % 60;
        const parts = [];

        if (hours > 0) {
            parts.push(hours + 'h');
        }
        if (hours > 0 || minutes > 0) {
            parts.push(minutes + 'm');
        }
        parts.push(remainingSeconds + 's');
        return parts.join(' ');
    }

    function selectDay(dayNumber) {
        dayTriggers.forEach((trigger) => {
            const isSelected = Number(trigger.dataset.dayTrigger) === dayNumber;
            trigger.classList.toggle('is-selected', isSelected);
            trigger.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        });

        dayPanels.forEach((panel) => {
            panel.hidden = Number(panel.dataset.dayPanel) !== dayNumber;
        });
    }

    dayTriggers.forEach((trigger) => {
        trigger.addEventListener('click', function() {
            if (trigger.disabled) {
                return;
            }
            selectDay(Number(trigger.dataset.dayTrigger));
        });
    });

    function tickCountdown(selector, prefix, readyText) {
        document.querySelectorAll(selector).forEach((element) => {
            let seconds = Number(element.dataset.dayCountdownSeconds || element.dataset.countdownSeconds || 0);
            if (seconds <= 0) {
                return;
            }
            seconds -= 1;
            if (element.dataset.dayCountdownSeconds !== undefined) {
                element.dataset.dayCountdownSeconds = String(seconds);
            } else {
                element.dataset.countdownSeconds = String(seconds);
            }
            element.textContent = seconds > 0 ? prefix + formatDuration(seconds) : readyText;
        });
    }

    async function postForm(body) {
        const response = await fetch(submitUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(body),
        });
        return response.json();
    }

    async function markLearningOpened(taskKey) {
        const response = await fetch(learnMarkUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams({ task_key: taskKey }),
        });
        return response.json();
    }

    document.querySelectorAll('[data-learning-gate]').forEach((gate) => {
        const opener = gate.querySelector('[data-learning-open]');
        const status = gate.querySelector('[data-learning-status]');
        const taskKey = gate.dataset.taskKey || '';
        if (!opener || !status || !taskKey) {
            return;
        }

        opener.addEventListener('click', async () => {
            window.setTimeout(async () => {
                try {
                    const data = await markLearningOpened(taskKey);
                    if (data.success) {
                        gate.dataset.learningOpened = '1';
                        gate.setAttribute('data-learning-opened', '1');
                        status.textContent = 'Learning validated';
                        const row = gate.closest('[data-task-key]');
                        const quizBlock = row ? row.querySelector('[data-quiz-block]') : null;
                        if (quizBlock) {
                            quizBlock.hidden = false;
                            const questions = Array.from(quizBlock.querySelectorAll('[data-quiz-question]'));
                            questions.forEach((question, idx) => {
                                question.hidden = idx !== 0;
                            });
                        }
                    }
                } catch (e) {
                    // no-op; backend will enforce validation anyway
                }
            }, 1200);
        });
    });

    document.querySelectorAll('[data-submit-task]').forEach((button) => {
        button.addEventListener('click', async function() {
            const row = button.closest('[data-task-key]');
            if (!row) {
                return;
            }

            const taskKey = row.dataset.taskKey || '';
            const verificationMode = row.dataset.verificationMode || 'instant';
            const payload = { task_key: taskKey };

            if (verificationMode === 'profile' && row.dataset.profileComplete === '0') {
                showModal('TaskHub', 'First complete your profile.', function() {
                    window.location.href = <?php echo json_encode(BASE_URL . '/profile.php'); ?>;
                });
                return;
            }

            if (verificationMode === 'manual') {
                if (taskKey === 'day1_social_follow') {
                    const xInput = row.querySelector('[data-x-handle]');
                    const telegramInput = row.querySelector('[data-telegram-handle]');
                    payload.x_handle = xInput ? xInput.value : '';
                    payload.telegram_handle = telegramInput ? telegramInput.value : '';
                } else {
                    if (taskKey === 'day3_share_experience') {
                        const platformInput = row.querySelector('[data-share-platform]');
                        const proofUrlInput = row.querySelector('[data-share-proof-url]');
                        payload.platform = platformInput ? platformInput.value : '';
                        payload.proof = proofUrlInput ? proofUrlInput.value : '';
                    } else {
                        const proofInput = row.querySelector('.task-proof-input');
                        payload.proof = proofInput ? proofInput.value : '';
                    }
                }
            }

            if (verificationMode === 'wallet') {
                const walletInput = row.querySelector('.task-wallet-input');
                payload.wallet_address = walletInput ? walletInput.value : '';
            }

            if (verificationMode === 'quiz') {
                const gate = row.querySelector('[data-learning-gate]');
                if (gate && gate.dataset.learningOpened !== '1') {
                    showModal('TaskHub Quiz', 'Please open the learning page first, then continue the quiz.');
                    return;
                }

                const answers = [];
                row.querySelectorAll('[data-quiz-block] .task-quiz-question').forEach((question, index) => {
                    const selected = question.querySelector('input[type="radio"]:checked');
                    answers[index] = selected ? Number(selected.value) : -1;
                });
                payload.answers_json = JSON.stringify(answers);
            }

            button.disabled = true;
            button.textContent = 'Submitting...';

            try {
                const data = await postForm(payload);
                if (!data.success) {
                    showModal('TaskHub', data.message || 'Task submission failed.', function() {
                        button.disabled = false;
                        button.textContent = 'Submit';
                    });
                    return;
                }
                showModal('TaskHub', data.message || 'Task submitted successfully.', function() {
                    window.location.reload();
                });
            } catch (error) {
                showModal('TaskHub', 'Task submission failed.', function() {
                    button.disabled = false;
                    button.textContent = 'Submit';
                });
            }
        });
    });

    document.querySelectorAll('[data-quiz-block]').forEach((quizBlock) => {
        const questions = Array.from(quizBlock.querySelectorAll('[data-quiz-question]'));
        questions.forEach((question, index) => {
            question.querySelectorAll('input[type="radio"]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    const isCorrect = radio.dataset.correct === '1';
                    if (!isCorrect) {
                        showModal('TaskHub Quiz', 'Wrong answer. Please try again to continue.');
                        radio.checked = false;
                        return;
                    }
                });
            });

            question.querySelectorAll('input[type="radio"]').forEach((radio) => {
                radio.addEventListener('change', () => {
                    if (radio.dataset.correct !== '1') {
                        showModal('TaskHub Quiz', 'Wrong answer. Please try again to continue.');
                        radio.checked = false;
                        return;
                    }
                    const nextQuestion = questions[index + 1];
                    if (nextQuestion) {
                        question.hidden = true;
                        nextQuestion.hidden = false;
                        nextQuestion.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
            });
        });
    });

    window.setInterval(() => {
        tickCountdown('[data-countdown-seconds]', 'Next task unlocks in ', 'Ready');
        tickCountdown('[data-day-countdown-seconds]', 'Unlocks in ', 'Unlocked');
    }, 1000);

    const selectedTrigger = document.querySelector('[data-day-trigger].is-selected');
    if (selectedTrigger) {
        selectDay(Number(selectedTrigger.dataset.dayTrigger));
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
