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
$status = $boost_state['status'] ?? 'closed';
$learnhub_completed = taskHubMissionCompleted($user_id, $db);

// ── Determine if user can claim ──
// "open" with a task = can claim
// "locked" = already claimed within 24h
// "awaiting_review" = submitted, pending admin
// "finished" = no more tasks
// "closed" = not available
$can_claim = ($status === 'open' && !empty($boost_task));
$boost_task_link = $can_claim ? trim((string) ($boost_task['task_link'] ?? '')) : '';
$boost_task_cta_label = $can_claim ? trim((string) ($boost_task['cta_label'] ?? '')) : '';
if ($boost_task_cta_label === '') {
    $boost_task_cta_label = 'Open Task';
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

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/boosthub-premium.css">

<main class="boosthub-premium">
    <div class="boosthub-shell">

        <!-- Header -->
        <div class="bh-header">
            <div class="bh-header-left">
                <span class="bh-header-badge"><i class="fas fa-bolt"></i> BoostHub</span>
                <h1>Daily Boost</h1>
            </div>
            <div class="bh-header-actions">
                <?php if (!$learnhub_completed): ?>
                    <a href="<?php echo BASE_URL; ?>/public/taskhub.php" class="bh-header-btn bh-header-btn--secondary"><i class="fas fa-graduation-cap"></i><span>LearnHub</span></a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/public/dashboard.php" class="bh-header-btn bh-header-btn--primary"><i class="fas fa-chart-simple"></i><span>Dashboard</span></a>
            </div>
        </div>

        <!-- Hero Card -->
        <section class="bh-hero">

            <?php if ($can_claim): ?>
                <!-- === CLAIM AVAILABLE === -->
                <div class="bh-claim-area">
                    <button type="button" class="bh-claim-btn" id="claimNowBtn">
                        <i class="fas fa-bolt"></i> Claim Now
                    </button>
                    <?php if ($boost_task_link !== ''): ?>
                        <a href="<?php echo htmlspecialchars($boost_task_link, ENT_QUOTES, 'UTF-8'); ?>" class="bh-task-link-btn" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-arrow-up-right-from-square"></i>
                            <span><?php echo htmlspecialchars($boost_task_cta_label, ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    <?php endif; ?>
                    <p class="bh-claim-sub">1 social task available</p>
                </div>

            <?php elseif ($status === 'locked' || $status === 'awaiting_review'): ?>
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
                            $status_label = 'Rejected';
                            $status_class = 'is-rejected';
                            $status_icon = '❌';
                            // Try to get rejection reason from metadata
                            $metadata = !empty($entry['metadata']) ? (is_string($entry['metadata']) ? json_decode($entry['metadata'], true) : $entry['metadata']) : [];
                            $rejection_reason = !empty($metadata['rejection_reason']) ? htmlspecialchars((string) $metadata['rejection_reason'], ENT_QUOTES, 'UTF-8') : '';
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
                <div class="bh-modal-evidence-card">
                    <div class="card-head">
                        <i class="fas fa-file-pen"></i> Evidence <span style="color:var(--bh-primary-light);font-weight:400;text-transform:none;">*</span>
                    </div>
                    <textarea id="proofInput" rows="4" placeholder="Paste evidence link, screenshot URL, username, handle, or any proof details."></textarea>
                    <div class="bh-modal-counter" id="proofCounter">0 characters</div>
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
                    <span class="btn-text"><i class="fas fa-paper-plane"></i> Submit Evidence</span>
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
    const taskId = <?php echo $boost_task ? (int) $boost_task['id'] : 0; ?>;
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

    // ── Character Counter ──
    var proofInput = document.getElementById('proofInput');
    var proofCounter = document.getElementById('proofCounter');
    if (proofInput && proofCounter) {
        proofInput.addEventListener('input', function() {
            var len = proofInput.value.length;
            proofCounter.textContent = len + ' characters';
            proofCounter.classList.remove('warn', 'danger');
            if (len > 1000) proofCounter.classList.add('danger');
            else if (len > 500) proofCounter.classList.add('warn');
        });
    }

    // ── Auto-resize textarea ──
    if (proofInput) {
        proofInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 300) + 'px';
        });
    }

    // ── Submit Claim ──
    var submitBtn = document.getElementById('submitClaimBtn');
    if (submitBtn && taskId > 0) {
        submitBtn.addEventListener('click', async function() {
            var proof = proofInput ? proofInput.value.trim() : '';
            if (!proof) {
                proofInput.style.borderColor = 'var(--bh-red)';
                setTimeout(function() { proofInput.style.borderColor = ''; }, 2000);
                return;
            }

            submitBtn.disabled = true;
            submitBtn.classList.add('loading');

            try {
                var response = await fetch(submitUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                    body: new URLSearchParams({
                        task_id: taskId,
                        proof: proof
                    })
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
