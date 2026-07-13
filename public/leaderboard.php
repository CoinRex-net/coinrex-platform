<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!featureIsAccessible('leaderboard')) {
    renderFeatureFallback(getFeatureFlag('leaderboard'));
    exit;
}

$metric = normalizeLeaderboardMetric($_GET['metric'] ?? 'valid_referrals');
$period = normalizeLeaderboardPeriod($_GET['period'] ?? 'today');
$metric_options = getLeaderboardMetricOptions();
$period_options = getLeaderboardPeriodOptions();

$page_title = 'Leaderboard | CoinRex';
$meta_description = 'Explore the CoinRex community leaderboard by valid referrals, BoostHub activity, and $REX earned across recent time periods.';
$canonical_url = BASE_URL . '/public/leaderboard.php';

$db = getDBConnection();
$entries = getLeaderboardEntries($metric, $period, 10, $db);
$selected_metric = $metric_options[$metric];
$selected_period = $period_options[$period];
$is_logged_in = isLoggedIn();
$current_user_id = $is_logged_in ? (int) getCurrentUserId() : 0;
$server_now = new DateTimeImmutable('now');
$next_reset = $server_now->modify('tomorrow')->setTime(0, 0, 0);
$next_reset_iso = $next_reset->format(DateTimeInterface::ATOM);
$next_reset_label = $next_reset->format('M j, g:i A T');

require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/leaderboard.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/leaderboard.css'); ?>">

<main class="leaderboard-page">
    <div class="leaderboard-shell">
        <section class="leaderboard-hero">
            <div class="leaderboard-hero-copy">
                <span class="leaderboard-kicker"><i class="fas fa-trophy"></i> Leaderboard</span>
                <h1>Top CoinRex users</h1>
            </div>
            <div class="leaderboard-hero-stat">
                <strong>Top 10</strong>
                <small><?php echo htmlspecialchars($selected_period['label'], ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
            <div class="leaderboard-reset-card" data-reset-at="<?php echo htmlspecialchars($next_reset_iso, ENT_QUOTES, 'UTF-8'); ?>">
                <span>Next Reset</span>
                <strong id="leaderboardResetCountdown">--:--:--</strong>
                <small><?php echo htmlspecialchars($next_reset_label, ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
        </section>

        <section class="leaderboard-controls">
            <div class="leaderboard-tab-row" role="tablist" aria-label="Leaderboard categories">
                <?php foreach ($metric_options as $metric_key => $option): ?>
                    <a
                        href="<?php echo htmlspecialchars(BASE_URL . '/public/leaderboard.php?' . http_build_query(['metric' => $metric_key, 'period' => $period]), ENT_QUOTES, 'UTF-8'); ?>"
                        class="leaderboard-tab<?php echo $metric_key === $metric ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $metric_key === $metric ? 'true' : 'false'; ?>"
                    >
                        <i class="<?php echo htmlspecialchars((string) $option['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <span><?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="leaderboard-period-row" aria-label="Leaderboard time period">
                <?php foreach ($period_options as $period_key => $option): ?>
                    <a
                        href="<?php echo htmlspecialchars(BASE_URL . '/public/leaderboard.php?' . http_build_query(['metric' => $metric, 'period' => $period_key]), ENT_QUOTES, 'UTF-8'); ?>"
                        class="leaderboard-period<?php echo $period_key === $period ? ' is-active' : ''; ?>"
                    >
                        <?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <p class="leaderboard-meta-copy"><?php echo htmlspecialchars((string) $selected_metric['description'], ENT_QUOTES, 'UTF-8'); ?></p>
        </section>

        <?php if (empty($entries)): ?>
            <section class="leaderboard-empty">
                <div class="leaderboard-empty-icon"><i class="fas fa-chart-line"></i></div>
                <h2>No rankings yet</h2>
                <p>No user has qualified for this leaderboard in the selected time period.</p>
            </section>
        <?php else: ?>
            <?php $podium_entries = array_slice($entries, 0, 3); ?>
            <?php $list_entries = array_slice($entries, 3); ?>
            <?php
                $podium_by_rank = [];
                foreach ($podium_entries as $podium_entry) {
                    $podium_by_rank[(int) $podium_entry['rank']] = $podium_entry;
                }
                $podium_display_entries = array_values(array_filter([
                    $podium_by_rank[2] ?? null,
                    $podium_by_rank[1] ?? null,
                    $podium_by_rank[3] ?? null,
                ]));
            ?>

            <section class="leaderboard-podium">
                <?php foreach ($podium_display_entries as $entry): ?>
                    <?php $is_current_user = $current_user_id > 0 && $current_user_id === (int) $entry['user_id']; ?>
                    <article class="leaderboard-podium-card leaderboard-rank-<?php echo (int) $entry['rank']; ?><?php echo $is_current_user ? ' is-current-user' : ''; ?>">
                        <span class="leaderboard-crown leaderboard-crown--rank-<?php echo (int) $entry['rank']; ?>" aria-hidden="true">
                            <i class="fas fa-crown"></i>
                        </span>
                        <span class="leaderboard-podium-rank">#<?php echo (int) $entry['rank']; ?></span>
                        <div class="leaderboard-avatar<?php echo $entry['avatar_url'] !== '' ? ' has-image' : ''; ?>">
                            <?php if ($entry['avatar_url'] !== ''): ?>
                                <img src="<?php echo htmlspecialchars($entry['avatar_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $entry['username'], ENT_QUOTES, 'UTF-8'); ?> avatar" loading="lazy">
                            <?php else: ?>
                                <span><?php echo htmlspecialchars((string) $entry['avatar_initial'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <strong><?php echo htmlspecialchars((string) $entry['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <span class="leaderboard-level leaderboard-level--<?php echo htmlspecialchars((string) $entry['level'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars((string) $entry['level_label'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <div class="leaderboard-score-block">
                            <span class="leaderboard-score"><?php echo htmlspecialchars((string) $entry['score_display'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <small><?php echo htmlspecialchars((string) $selected_metric['score_suffix'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </div>
                        <?php if ($is_current_user): ?><em class="leaderboard-you-badge">You</em><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="leaderboard-table-card">
                <div class="leaderboard-table-head">
                    <div>
                        <h2><?php echo count($entries) > 3 ? 'Ranks 4-10' : htmlspecialchars((string) $selected_metric['label'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p><?php echo htmlspecialchars((string) $selected_period['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <span class="leaderboard-table-badge"><?php echo count($entries); ?> users</span>
                </div>

                <div class="leaderboard-list" role="list">
                    <?php foreach ((count($entries) > 3 ? $list_entries : $entries) as $entry): ?>
                        <?php $is_current_user = $current_user_id > 0 && $current_user_id === (int) $entry['user_id']; ?>
                        <article class="leaderboard-row<?php echo $is_current_user ? ' is-current-user' : ''; ?>" role="listitem">
                            <div class="leaderboard-row-rank">#<?php echo (int) $entry['rank']; ?></div>
                            <div class="leaderboard-avatar leaderboard-avatar--small<?php echo $entry['avatar_url'] !== '' ? ' has-image' : ''; ?>">
                                <?php if ($entry['avatar_url'] !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($entry['avatar_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $entry['username'], ENT_QUOTES, 'UTF-8'); ?> avatar" loading="lazy">
                                <?php else: ?>
                                    <span><?php echo htmlspecialchars((string) $entry['avatar_initial'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="leaderboard-row-user">
                                <strong><?php echo htmlspecialchars((string) $entry['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span class="leaderboard-level leaderboard-level--<?php echo htmlspecialchars((string) $entry['level'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars((string) $entry['level_label'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                            <div class="leaderboard-row-score">
                                <strong><?php echo htmlspecialchars((string) $entry['score_display'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <span><?php echo htmlspecialchars((string) $selected_metric['score_suffix'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<script>
(function() {
    var resetCard = document.querySelector('.leaderboard-reset-card[data-reset-at]');
    var countdown = document.getElementById('leaderboardResetCountdown');
    if (!resetCard || !countdown) {
        return;
    }

    var resetAt = new Date(resetCard.getAttribute('data-reset-at')).getTime();
    if (!Number.isFinite(resetAt)) {
        return;
    }

    function pad(value) {
        return String(Math.max(0, value)).padStart(2, '0');
    }

    function renderResetCountdown() {
        var remaining = Math.max(0, Math.floor((resetAt - Date.now()) / 1000));
        var hours = Math.floor(remaining / 3600);
        var minutes = Math.floor((remaining % 3600) / 60);
        var seconds = remaining % 60;
        countdown.textContent = pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);

        if (remaining <= 0) {
            window.location.reload();
        }
    }

    renderResetCountdown();
    window.setInterval(renderResetCountdown, 1000);
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
