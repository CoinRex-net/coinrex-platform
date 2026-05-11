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
$level_state = getUserLevelState($user, $db);
$user['level'] = $level_state['level'];
$level_progress = getUserLevelProgressData($level_state, $db);
$mini_task_stats = getUserMiniTaskStats((int) $user['id'], $db);
$dashboard_notice = consumeFlashMessage('dashboard_success');

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
if ($next_policy) {
    $current_tasks = (int) ($mini_task_stats['completed_total'] ?? 0);
    $current_referrals = (int) ($user['valid_referrals'] ?? 0);
    $current_reviews = (int) ($level_state['stats']['approved_reviews'] ?? 0);
    $current_accuracy = (float) ($level_state['accuracy'] ?? 0);

    $task_left = max(0, (int) ($next_policy['promotion_completed_tasks'] ?? 0) - $current_tasks);
    $referrals_left = max(0, (int) ($next_policy['promotion_valid_referrals'] ?? 0) - $current_referrals);
    $reviews_left = max(0, (int) ($next_policy['promotion_approved_reviews'] ?? 0) - $current_reviews);
    $accuracy_left = max(0, (float) ($next_policy['promotion_accuracy'] ?? 0) - $current_accuracy);

    if ($task_left > 0) {
        $remaining_requirements[] = $task_left . ' more completed tasks';
    }
    if ($referrals_left > 0) {
        $remaining_requirements[] = $referrals_left . ' more valid referrals';
    }
    if ($reviews_left > 0) {
        $remaining_requirements[] = $reviews_left . ' more approved reviews';
    }
    if ($accuracy_left > 0) {
        $remaining_requirements[] = number_format($accuracy_left, 1) . '% more accuracy';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/dashboard.css">

<main class="dashboard-main">
    <div class="dashboard-container">
        <?php if ($dashboard_notice !== ''): ?>
            <section class="card">
                <p class="muted-line" style="margin:0;"><?php echo htmlspecialchars($dashboard_notice, ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
        <?php endif; ?>
        <section class="dashboard-hero card">
            <div>
                <span class="hero-kicker">Dashboard</span>
                <h1>Welcome back, <?php echo htmlspecialchars($profile_name, ENT_QUOTES, 'UTF-8'); ?></h1>
                <div class="hero-pills">
                    <span class="hero-pill hero-level-pill level-<?php echo htmlspecialchars($current_level, ENT_QUOTES, 'UTF-8'); ?>"><i class="<?php echo htmlspecialchars($level_icon, ENT_QUOTES, 'UTF-8'); ?>"></i><?php echo htmlspecialchars($current_level === 'pro' ? 'Pro' : ucfirst((string) $user['level']), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="hero-pill"><?php echo number_format((float) ($level_state['accuracy'] ?? 0), 1); ?>% accuracy</span>
                    <span class="hero-pill"><?php echo number_format((int) ($user['valid_referrals'] ?? 0)); ?> referrals</span>
                </div>
            </div>
            <div class="hero-balance-card">
                <span>Available balance</span>
                <strong><?php echo number_format((float) $balance, 2); ?> $REX</strong>
                <small><?php echo htmlspecialchars((string) ($claim_eligibility['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
        </section>

        <div class="dashboard-grid">
            <section class="card profile-card">
                <div class="profile-head">
                    <div class="profile-avatar">
                        <?php if ($user_avatar_url !== ''): ?>
                            <img src="<?php echo htmlspecialchars($user_avatar_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar">
                        <?php else: ?>
                            <span><?php echo htmlspecialchars($avatar_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2><?php echo htmlspecialchars($profile_name, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p>@<?php echo htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                <div class="profile-meta">
                    <div><span>Member since</span><strong><?php echo date('M Y', strtotime((string) $user['created_at'])); ?></strong></div>
                    <div><span>Location</span><strong><?php echo htmlspecialchars((string) ($user['country'] ?: 'Not set'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                    <div><span>Wallet</span><strong><?php echo htmlspecialchars((string) ($user['wallet_address'] ?: 'Not connected'), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                </div>
            </section>

            <section class="stats-grid">
                <div class="stat-card">
                    <span class="stat-label">$REX Balance</span>
                    <strong><?php echo number_format((float) ($user['rex_balance'] ?? 0), 2); ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Total Earned</span>
                    <strong><?php echo number_format((float) ($user['total_rex_earned'] ?? 0), 2); ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Reviews</span>
                    <strong><?php echo number_format((int) ($user['total_reviews'] ?? 0)); ?></strong>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Votes Cast</span>
                    <strong><?php echo number_format((int) ($user['total_votes'] ?? 0)); ?></strong>
                </div>
            </section>
        </div>

        <div class="dashboard-grid">
            <section class="card">
                <div class="section-head">
                    <h3>Quick Actions</h3>
                </div>
                <div class="actions-grid">
                    <?php if (userCanAccessProjectReviewArea($user)): ?>
                        <a href="<?php echo BASE_URL; ?>/submit-review.php" class="action-btn">Write Review</a>
                        <a href="<?php echo BASE_URL; ?>/projects.php" class="action-btn">View Projects</a>
                    <?php else: ?>
                        <span class="action-btn action-btn-disabled">Reviews unlock at Pro</span>
                        <span class="action-btn action-btn-disabled">Projects unlock at Pro</span>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/taskhub.php" class="action-btn">TaskHub</a>
                    <a href="<?php echo BASE_URL; ?>/boosthub.php" class="action-btn">BoostHub</a>
                    <span class="action-btn action-btn-disabled action-btn-soon">Claim Center <span class="soon-badge">Soon</span></span>
                </div>
            </section>

            <section class="card">
                <div class="section-head">
                    <h3>Level Progress</h3>
                    <span class="simple-badge level-badge-<?php echo htmlspecialchars($current_level, ENT_QUOTES, 'UTF-8'); ?>"><i class="<?php echo htmlspecialchars($level_icon, ENT_QUOTES, 'UTF-8'); ?>"></i><?php echo htmlspecialchars($current_level === 'pro' ? 'Pro' : ucfirst((string) $user['level']), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="progress-bar-container">
                    <div class="progress-bar" style="width: <?php echo number_format((float) $level_progress['progress'], 1); ?>%"></div>
                </div>
                <div class="progress-stats">
                    <div><span>Tasks</span><strong><?php echo number_format((int) ($mini_task_stats['completed_total'] ?? 0)); ?></strong></div>
                    <div><span>Referrals</span><strong><?php echo number_format((int) ($user['valid_referrals'] ?? 0)); ?></strong></div>
                    <div><span>Approved Reviews</span><strong><?php echo number_format((int) ($level_state['stats']['approved_reviews'] ?? 0)); ?></strong></div>
                </div>
                <?php if ($next_level): ?>
                    <p class="muted-line">Next: <?php echo htmlspecialchars(ucfirst((string) $next_level), ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="remaining-unlock">
                        <strong>Remaining to unlock <?php echo htmlspecialchars(ucfirst((string) $next_level), ENT_QUOTES, 'UTF-8'); ?>:</strong>
                        <?php if (!empty($remaining_requirements)): ?>
                            <ul>
                                <?php foreach ($remaining_requirements as $req): ?>
                                    <li><?php echo htmlspecialchars($req, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>All criteria complete. Level sync will promote you automatically.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="muted-line">Top level reached</p>
                <?php endif; ?>
            </section>
        </div>

        <div class="dashboard-grid">
            <section class="card">
                <div class="section-head">
                    <h3>Referral Link</h3>
                </div>
                <div class="copy-row">
                    <input type="text" id="referralLink" value="<?php echo htmlspecialchars(BASE_URL . '/auth/auth.php?ref=' . (string) $user['referral_code'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    <button type="button" class="copy-btn" data-copy-target="referralLink">Copy</button>
                </div>
            </section>

            <section class="card">
                <div class="section-head">
                    <h3>Claim Access</h3>
                </div>
                <div class="claim-summary claim-summary-locked">
                    <span>Locked until blockchain implementation is live</span>
                    <span class="soon-badge">Soon</span>
                </div>
            </section>
        </div>

        <section class="card">
            <div class="section-head">
                <h3>Recent Reviews</h3>
            </div>
            <div class="history-list">
                <?php if (!empty($recent_reviews)): ?>
                    <?php foreach ($recent_reviews as $review): ?>
                        <div class="history-row">
                            <div>
                                <strong><?php echo htmlspecialchars((string) ($review['project_name'] ?: 'Project'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars((string) ($review['review_title'] ?: 'Review submitted'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="history-right">
                                <strong><?php echo number_format((float) ($review['final_rex'] ?? 0), 2); ?> $REX</strong>
                                <span><?php echo htmlspecialchars((string) ($review['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="history-row">
                        <div>
                            <strong>No reviews yet</strong>
                            <span>Start from the quick actions above.</span>
                        </div>
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
        if (!input) {
            return;
        }

        try {
            await navigator.clipboard.writeText(input.value);
            button.textContent = 'Copied';
            window.setTimeout(() => {
                button.textContent = 'Copy';
            }, 1200);
        } catch (error) {
            input.select();
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
