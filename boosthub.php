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
$boost_state = getBoostHubStateForUser((int) $user['id'], $db);
$boost_task = $boost_state['task'] ?? null;

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/reward-pages-boosthub.css">

<main class="boosthub-premium-page">
    <div class="boosthub-shell">
        <section class="boosthub-hero">
            <div class="boosthub-hero-top">
                <span class="boosthub-badge">BoostHub</span>
                <h1>Assigned Boost Tasks</h1>
                <p>Complete one verified task at a time and unlock the next assignment every 24 hours.</p>
            </div>
            <div class="boosthub-hero-metrics">
                <div class="metric-card">
                    <span>Status</span>
                    <strong><?php echo htmlspecialchars((string) ucfirst((string) ($boost_state['status'] ?? 'active')), ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="metric-card">
                    <span>Current Task</span>
                    <strong><?php echo !empty($boost_task) ? 'Available' : 'None'; ?></strong>
                </div>
                <div class="metric-card">
                    <span>Queue</span>
                    <strong><?php echo !empty($boost_task) ? '1' : '0'; ?></strong>
                </div>
            </div>
            <div class="page-actions">
                <a href="<?php echo BASE_URL; ?>/taskhub.php" class="secondary-btn">Back to TaskHub</a>
                <a href="<?php echo BASE_URL; ?>/dashboard.php" class="secondary-btn">Dashboard</a>
            </div>
        </section>

        <section class="task-card boosthub-safety-card" role="alert" aria-live="polite">
            <div class="boosthub-safety-head">
                <span class="boosthub-safety-icon" aria-hidden="true">⚠</span>
                <div>
                    <h3>Safety Warning & Risk Alert</h3>
                    <p class="boosthub-safety-subtitle">Please review carefully before completing any promotional task.</p>
                </div>
            </div>
            <ul class="boosthub-safety-list">
                <li>Some tasks may involve third-party platforms, links, or paid promotions.</li>
                <li>CoinRex is not responsible for financial loss, scams, or outcomes outside this platform.</li>
                <li>Never share private keys, seed phrases, OTPs, or wallet recovery credentials.</li>
            </ul>
            <p class="boosthub-safety-footer"><strong>Do your own research (DYOR)</strong> before taking action.</p>
        </section>

        <?php if (($boost_state['status'] ?? '') === 'closed'): ?>
            <section class="task-card">
                <h3>BoostHub closed</h3>
                <p><?php echo htmlspecialchars((string) ($boost_state['message'] ?? 'BoostHub is not available for this account.'), ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
        <?php elseif (($boost_state['status'] ?? '') === 'locked'): ?>
            <section class="task-card boosthub-lock-card boosthub-state-card">
                <h3>Next Task Locked</h3>
                <p><?php echo htmlspecialchars((string) ($boost_state['message'] ?? 'Next task unlocks after 24 hours.'), ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="task-countdown boosthub-countdown" data-countdown-seconds="<?php echo (int) ($boost_state['countdown_seconds'] ?? 0); ?>">
                    Unlocks in <?php echo htmlspecialchars(taskHubFormatDuration((int) ($boost_state['countdown_seconds'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <div class="boosthub-countdown-ring" aria-hidden="true">
                    <svg viewBox="0 0 120 120" width="0" height="0" style="position:absolute;">
                        <defs>
                            <linearGradient id="boosthubCountdownGrad" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#22c55e"/>
                                <stop offset="100%" stop-color="#38bdf8"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
            </section>
        <?php elseif (($boost_state['status'] ?? '') === 'finished'): ?>
            <section class="task-card">
                <h3>All Tasks Completed</h3>
                <p><?php echo htmlspecialchars((string) ($boost_state['message'] ?? 'No new BoostHub tasks available right now.'), ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
        <?php elseif (($boost_state['status'] ?? '') === 'awaiting_review'): ?>
            <section class="task-card">
                <h3>Submission Pending</h3>
                <p><?php echo htmlspecialchars((string) ($boost_state['message'] ?? 'Your evidence is under admin review.'), ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
        <?php else: ?>
            <section class="task-grid">
                <?php if ($boost_task): ?>
                    <article class="task-card boosthub-task-card" data-task-id="<?php echo (int) $boost_task['id']; ?>">
                        <div class="history-row boosthub-task-head">
                            <div>
                                <?php if (!empty($boost_task['task_category'])): ?>
                                    <span class="status-chip"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $boost_task['task_category'])), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <h3 class="boosthub-task-title"><?php echo htmlspecialchars((string) $boost_task['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p><?php echo htmlspecialchars((string) ($boost_task['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="history-row-right">
                                <span class="status-chip">Assigned</span>
                            </div>
                        </div>
                        <div class="task-meta">
                            <span class="boosthub-reward-pill">Reward: <?php echo number_format((float) ($boost_task['reward'] ?? 0), 2); ?> $REX</span>
                            <span>Complete this assigned task to unlock the next one after 24h.</span>
                        </div>
                        <?php if (!empty($boost_task['completion_steps'])): ?>
                            <div class="taskhub-brief-card">
                                <strong>How to complete</strong>
                                <p><?php echo nl2br(htmlspecialchars((string) $boost_task['completion_steps'], ENT_QUOTES, 'UTF-8')); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($boost_task['proof_notes'])): ?>
                            <div class="taskhub-brief-card boosthub-proof-notes">
                                <strong>Notes</strong>
                                <p><?php echo nl2br(htmlspecialchars((string) $boost_task['proof_notes'], ENT_QUOTES, 'UTF-8')); ?></p>
                            </div>
                        <?php endif; ?>
                        <div class="boosthub-proof-form">
                            <div class="boosthub-proof-head">
                                <strong>Evidence</strong>
                            </div>
                            <div class="boosthub-proof-grid">
                                <textarea class="task-proof-input boosthub-proof-text" rows="5" placeholder="Paste evidence link, screenshot URL, username, handle, or any proof details."></textarea>
                            </div>
                        </div>
                        <div class="page-actions">
                            <?php if (!empty($boost_task['task_link'])): ?>
                                <a href="<?php echo htmlspecialchars((string) $boost_task['task_link'], ENT_QUOTES, 'UTF-8'); ?>" class="secondary-btn" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars((string) ($boost_task['cta_label'] ?? 'Open Task'), ENT_QUOTES, 'UTF-8'); ?></a>
                            <?php endif; ?>
                            <button type="button" class="primary-btn task-submit-btn" data-submit-task>
                                Submit Evidence
                            </button>
                        </div>
                    </article>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<div class="taskhub-modal" id="boosthubModal" hidden>
    <div class="taskhub-modal-backdrop" data-modal-close></div>
    <div class="taskhub-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="boosthubModalTitle">
        <div class="taskhub-modal-head">
            <h3 id="boosthubModalTitle">BoostHub</h3>
            <button type="button" class="taskhub-modal-close" data-modal-close aria-label="Close message">x</button>
        </div>
        <p class="taskhub-modal-message" id="boosthubModalMessage"></p>
        <div class="page-actions">
            <button type="button" class="primary-btn" id="boosthubModalAction">Okay</button>
        </div>
    </div>
</div>

<script>
(function() {
    const submitUrl = <?php echo json_encode(BASE_URL . '/api/complete_mini_task.php'); ?>;
    const modal = document.getElementById('boosthubModal');
    const modalTitle = document.getElementById('boosthubModalTitle');
    const modalMessage = document.getElementById('boosthubModalMessage');
    const modalAction = document.getElementById('boosthubModalAction');

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

    async function postForm(body) {
        const response = await fetch(submitUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: new URLSearchParams(body),
        });
        return response.json();
    }

    document.querySelectorAll('[data-submit-task]').forEach((button) => {
        button.addEventListener('click', async function() {
            const row = button.closest('[data-task-id]');
            if (!row) {
                return;
            }

            button.disabled = true;
            button.textContent = 'Submitting...';

            try {
                const proofInput = row.querySelector('.boosthub-proof-text');
                const data = await postForm({
                    task_id: row.dataset.taskId || '',
                    proof: proofInput ? proofInput.value : '',
                });
                if (!data.success) {
                    showModal('BoostHub', data.message || 'BoostHub task submission failed.', function() {
                        button.disabled = false;
                        button.textContent = 'Submit Evidence';
                    });
                    return;
                }
                showModal('BoostHub', data.message || 'Task submitted successfully.', function() {
                    window.location.reload();
                });
            } catch (error) {
                showModal('BoostHub', 'BoostHub task submission failed.', function() {
                    button.disabled = false;
                    button.textContent = 'Submit Evidence';
                });
            }
        });
    });

    window.setInterval(() => {
        document.querySelectorAll('[data-countdown-seconds]').forEach((element) => {
            let seconds = Number(element.dataset.countdownSeconds || 0);
            if (seconds <= 0) {
                return;
            }
            seconds -= 1;
            element.dataset.countdownSeconds = String(seconds);
            element.textContent = seconds > 0 ? 'Next task unlocks in ' + formatDuration(seconds) : 'Ready';
        });
    }, 1000);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
