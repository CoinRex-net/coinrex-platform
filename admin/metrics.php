<?php
$page_title = 'Metrics';
$activePage = 'metrics';
require_once __DIR__ . '/includes/config.php';

$db = getDBConnection();
$shareToken = coinrexNormalizeInvestorMetricToken((string) ($_GET['v'] ?? ''));
$isInvestorShareRequest = $shareToken !== '';
$shareAccess = null;
$shareMessage = '';
$shareMessageType = 'success';
$createdShareUrl = '';
$shareTokens = [];

if ($isInvestorShareRequest) {
    $shareAccess = validateInvestorMetricShareToken($shareToken, $db);
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Token visitors are read-only.';
        exit;
    }

    if (isset($_GET['health'])) {
        header('Content-Type: application/json; charset=utf-8');
        if (!$shareAccess) {
            http_response_code(403);
            echo json_encode(['valid' => false], JSON_UNESCAPED_SLASHES);
            exit;
        }
        echo json_encode(['valid' => true], JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($shareAccess) {
        touchInvestorMetricShareToken((int) ($shareAccess['id'] ?? 0), $db);
    }
} else {
    requireAdminAuth();
    requireAdminPageAccess($activePage);

    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
        requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
        $action = (string) ($_POST['action'] ?? '');
        $currentAdmin = getCurrentAdmin();
        $adminId = (int) ($currentAdmin['id'] ?? 0);

        if ($action === 'create_investor_metric_token') {
            $created = createInvestorMetricShareToken((string) ($_POST['label'] ?? ''), $adminId, $db);
            if (!empty($created['success'])) {
                $createdShareUrl = (string) ($created['url'] ?? '');
                $shareMessage = 'Investor metrics link created. Copy it now; the token is shown only once.';
                $shareMessageType = 'success';
            } else {
                $shareMessage = (string) ($created['message'] ?? 'Could not create investor link.');
                $shareMessageType = 'error';
            }
        } elseif ($action === 'revoke_investor_metric_token') {
            $revoked = revokeInvestorMetricShareToken((int) ($_POST['token_id'] ?? 0), $adminId, $db);
            $shareMessage = $revoked ? 'Investor metrics link expired.' : 'Investor metrics link was already expired or not found.';
            $shareMessageType = $revoked ? 'success' : 'info';
        } elseif ($action === 'delete_investor_metric_token') {
            $deleted = deleteInvestorMetricShareToken((int) ($_POST['token_id'] ?? 0), $db);
            $shareMessage = $deleted ? 'Investor metrics link deleted.' : 'Investor metrics link was not found.';
            $shareMessageType = $deleted ? 'success' : 'error';
        }
    }
}

$window = strtolower(trim((string) ($_GET['window'] ?? '30d')));
if (!in_array($window, ['today', '7d', '30d', 'all'], true)) {
    $window = '30d';
}

$metrics = getAdminInvestorMetrics($db, $window);
$windowLabels = ['today' => 'Today', '7d' => 'Last 7 Days', '30d' => 'Last 30 Days', 'all' => 'All Time'];
$windowLabel = $windowLabels[$window] ?? 'Last 30 Days';
if (!$isInvestorShareRequest) {
    $shareTokens = getInvestorMetricShareTokens($db);
}

function investorMetricFormatNumber($value): string {
    if (is_float($value)) {
        return number_format($value, 1);
    }
    return number_format((int) $value);
}

function investorMetricFormatRex($value): string {
    return number_format((float) $value, 2) . ' REX';
}

function investorMetricFormatPercent($value): string {
    return number_format((float) $value, 1) . '%';
}

function investorMetricDisplayPercent($value, string $fallback = 'Collecting'): string {
    return $value === null ? $fallback : investorMetricFormatPercent($value);
}

function investorMetricFormatDuration($seconds): string {
    if ($seconds === null) {
        return 'Collecting';
    }
    $seconds = max(0, (int) $seconds);
    if ($seconds <= 0) {
        return 'N/A';
    }
    if ($seconds < 60) {
        return $seconds . 's';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    }
    return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
}

function investorMetricTrend($value, $suffix = '%'): string {
    $value = (float) $value;
    $class = $value >= 0 ? 'is-up' : 'is-down';
    $icon = $value >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down';
    return '<span class="metric-trend ' . $class . '"><i class="fas ' . $icon . '"></i> ' . htmlspecialchars(number_format($value, 1) . $suffix, ENT_QUOTES, 'UTF-8') . '</span>';
}

function investorMetricCard($label, $value, $icon = 'fa-chart-simple', $iconClass = 'is-gold', $note = '', $quality = ''): void {
    $safe_label = htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8');
    $safe_value = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    $safe_note = (string) $note;
    $safe_quality = htmlspecialchars((string) $quality, ENT_QUOTES, 'UTF-8');

    echo '<div class="dashboard-metric-card investor-metric-card">';
    echo '<div class="metric-top"><span class="metric-icon ' . htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') . '"><i class="fas ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i></span>';
    if ($safe_quality !== '') {
        echo '<span class="data-quality-badge">' . $safe_quality . '</span>';
    }
    echo '</div>';
    echo '<strong class="metric-value">' . $safe_value . '</strong>';
    echo '<span class="metric-label">' . $safe_label . '</span>';
    if ($safe_note !== '') {
        echo '<span class="metric-trend-note">' . $safe_note . '</span>';
    }
    echo '</div>';
}

function investorWindowUrl($window): string {
    global $isInvestorShareRequest, $shareToken;
    $params = ['window' => (string) $window];
    if ($isInvestorShareRequest && $shareToken !== '') {
        $params['v'] = $shareToken;
    }
    return ADMIN_BASE_URL . '/metrics.php?' . http_build_query($params);
}

$analyticsQuality = !empty($metrics['analytics_has_data']) ? 'Tracked' : 'New';
$activityQuality = (string) ($metrics['growth']['activity_quality'] ?? $analyticsQuality);

if ($isInvestorShareRequest && !$shareAccess):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Metrics Access Expired - CoinRex</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo ADMIN_BASE_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo ADMIN_BASE_URL; ?>/assets/css/admin-dashboard.css">
</head>
<body data-admin-theme="dark" class="investor-share-body">
    <main class="investor-share-shell">
        <div class="dashboard-panel investor-access-denied">
            <div class="dashboard-header-icon"><i class="fas fa-lock"></i></div>
            <h1>Metrics Access Expired</h1>
            <p>This investor dashboard link is no longer active. Please request a fresh CoinRex metrics link.</p>
        </div>
    </main>
</body>
</html>
<?php
exit;
endif;

if ($isInvestorShareRequest):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>CoinRex Metrics</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo ADMIN_BASE_URL; ?>/assets/css/admin.css">
    <link rel="stylesheet" href="<?php echo ADMIN_BASE_URL; ?>/assets/css/admin-dashboard.css">
</head>
<body data-admin-theme="dark" class="investor-share-body">
<main class="admin-main investor-share-shell">
<?php else: ?>
<?php require_once __DIR__ . '/includes/header.php'; ?>
<?php endif; ?>


<div class="dashboard-header investor-dashboard-header">
    <div class="dashboard-header-left">
        <div class="dashboard-header-icon"><i class="fas fa-chart-line"></i></div>
        <div class="dashboard-header-text">
            <h1>CoinRex Metrics</h1>
            <p>Investor dashboard for traction, retention, onboarding, and economy health.</p>
        </div>
    </div>
    <?php if ($isInvestorShareRequest): ?>
        <span class="dashboard-header-badge"><i class="fas fa-lock"></i> Read Only</span>
    <?php endif; ?>
    <div class="investor-window-tabs" aria-label="Metrics date window">
        <?php
        $windows = ['today' => 'Today', '7d' => '7D', '30d' => '30D', 'all' => 'All'];
        foreach ($windows as $key => $label):
        ?>
            <a href="<?php echo investorWindowUrl($key); ?>" class="<?php echo $window === $key ? 'active' : ''; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if (!$isInvestorShareRequest): ?>
<div class="dashboard-panel investor-token-panel">
    <div class="dashboard-panel-header">
        <h3><i class="fas fa-key"></i> VC Token-Gated Access</h3>
        <span class="panel-badge">Metrics only</span>
    </div>
    <form method="POST" class="investor-token-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="create_investor_metric_token">
        <input type="text" name="label" maxlength="120" placeholder="VC name or fund label">
        <button type="submit" class="btn btn-secondary"><i class="fas fa-plus"></i> Create Link</button>
    </form>
    <?php if ($shareMessage !== ''): ?>
        <div class="investor-token-message" data-toast data-toast-type="<?php echo htmlspecialchars($shareMessageType, ENT_QUOTES, 'UTF-8'); ?>" data-toast-message="<?php echo htmlspecialchars($shareMessage, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($shareMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($createdShareUrl !== ''): ?>
        <div class="investor-created-link">
            <code><?php echo htmlspecialchars($createdShareUrl, ENT_QUOTES, 'UTF-8'); ?></code>
            <button type="button" class="btn btn-secondary" data-copy-investor-link="<?php echo htmlspecialchars($createdShareUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-copy"></i> Copy</button>
        </div>
    <?php endif; ?>

    <div class="dashboard-table-wrap investor-token-table-wrap">
        <table class="dashboard-table investor-token-table">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Status</th>
                    <th>Accesses</th>
                    <th>Last Access</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shareTokens)): ?>
                    <tr><td colspan="6" class="dashboard-empty"><i class="fas fa-key"></i>No investor links created yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($shareTokens as $tokenRow): ?>
                        <tr>
                            <td data-label="Label"><?php echo htmlspecialchars((string) ($tokenRow['label'] ?? 'Investor link'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Status"><span class="data-quality-badge <?php echo (string) ($tokenRow['status'] ?? '') === 'active' ? 'is-active' : 'is-revoked'; ?>"><?php echo htmlspecialchars((string) ($tokenRow['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td data-label="Accesses"><?php echo number_format((int) ($tokenRow['access_count'] ?? 0)); ?></td>
                            <td data-label="Last Access"><?php echo htmlspecialchars((string) ($tokenRow['last_accessed_at'] ?? 'Never'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Created"><?php echo htmlspecialchars((string) ($tokenRow['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Action">
                                <div class="investor-token-actions">
                                <?php
                                    $rowToken = coinrexNormalizeInvestorMetricToken((string) ($tokenRow['token_code'] ?? ''));
                                    $rowUrl = $rowToken !== '' ? ADMIN_BASE_URL . '/metrics.php?v=' . rawurlencode($rowToken) : '';
                                ?>
                                <?php if ($rowUrl !== ''): ?>
                                    <button type="button" class="btn btn-secondary" data-copy-investor-link="<?php echo htmlspecialchars($rowUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-copy"></i> Copy</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary" disabled><i class="fas fa-copy"></i> Copy</button>
                                <?php endif; ?>
                                <?php if ((string) ($tokenRow['status'] ?? '') === 'active'): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="revoke_investor_metric_token">
                                        <input type="hidden" name="token_id" value="<?php echo (int) ($tokenRow['id'] ?? 0); ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Expire this investor metrics link now?');"><i class="fas fa-ban"></i> Expire</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted">Expired</span>
                                <?php endif; ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="delete_investor_metric_token">
                                        <input type="hidden" name="token_id" value="<?php echo (int) ($tokenRow['id'] ?? 0); ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this investor metrics link permanently?');"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (empty($metrics['analytics_has_data'])): ?>
<div class="dashboard-panel investor-data-note">
    <i class="fas fa-circle-info"></i>
    <span>Activity headline cards use live <code>last_active</code> fallback while cohort retention and session-duration history continue collecting. Product, reward, review, LearnHub, BoostHub, and RexLink totals are live.</span>
</div>
<?php endif; ?>

<div class="dashboard-section-divider">
    <h2><i class="fas fa-arrow-trend-up"></i> Growth <span class="divider-sub">investor traction</span></h2>
</div>
<div class="dashboard-metric-grid investor-metric-grid">
    <?php
    investorMetricCard('Total Registered Users', investorMetricFormatNumber($metrics['growth']['total_users']), 'fa-users', 'is-blue');
    investorMetricCard('Active Users Now', investorMetricFormatNumber($metrics['growth']['active_now']), 'fa-circle-play', 'is-green', 'Last 5 minutes');
    investorMetricCard('Daily Active Users', investorMetricFormatNumber($metrics['growth']['dau']), 'fa-bolt', 'is-gold', 'Today', $activityQuality);
    investorMetricCard('7 Day Active Users', investorMetricFormatNumber($metrics['growth']['wau']), 'fa-calendar-week', 'is-cyan', 'Rolling 7 days', $activityQuality);
    investorMetricCard('Monthly Active Users', investorMetricFormatNumber($metrics['growth']['mau']), 'fa-calendar-days', 'is-purple', 'Rolling 30 days', $activityQuality);
    investorMetricCard('New Users', investorMetricFormatNumber($metrics['growth']['new_window']), 'fa-user-plus', 'is-green', $windowLabel);
    investorMetricCard('Growth 7D / 30D', investorMetricFormatPercent($metrics['growth']['growth_7d']) . ' / ' . investorMetricFormatPercent($metrics['growth']['growth_30d']), 'fa-arrow-trend-up', 'is-orange', 'New-user momentum');
    ?>
</div>

<div class="dashboard-section-divider">
    <h2><i class="fas fa-graduation-cap"></i> LearnHub <span class="divider-sub">onboarding completion</span></h2>
</div>
<div class="dashboard-metric-grid investor-metric-grid">
    <?php
    investorMetricCard('Total LearnHub Starts', investorMetricFormatNumber($metrics['learnhub']['starts']), 'fa-play', 'is-blue');
    investorMetricCard('Completion Rate', investorMetricFormatPercent($metrics['learnhub']['completion_rate']), 'fa-circle-check', 'is-green');
    investorMetricCard('Average Completion Time', investorMetricFormatDuration($metrics['learnhub']['avg_completion_seconds']), 'fa-stopwatch', 'is-gold');
    investorMetricCard('Active Learners', investorMetricFormatNumber($metrics['learnhub']['active_learners']), 'fa-book-open-reader', 'is-cyan', 'Last 7 days');
    investorMetricCard('PRO Users Earned', investorMetricFormatNumber($metrics['learnhub']['pro_users_earned']), 'fa-crown', 'is-orange');
    investorMetricCard('LearnHub Completion Days', investorMetricFormatNumber($metrics['learnhub']['completed_users']), 'fa-flag-checkered', 'is-purple');
    ?>
</div>
<div class="dashboard-panel investor-funnel-panel">
    <div class="dashboard-panel-header">
        <h3><i class="fas fa-timeline"></i> Day 1 to Day 10 Progress</h3>
        <span class="panel-badge">Users completed per day</span>
    </div>
    <div class="learnhub-funnel">
        <?php
        $maxDayCount = max(1, max($metrics['learnhub']['day_counts']));
        foreach ($metrics['learnhub']['day_counts'] as $day => $count):
            $width = max(6, (int) round(((int) $count / $maxDayCount) * 100));
        ?>
            <div class="funnel-step">
                <div class="funnel-step-top">
                    <strong>Day <?php echo (int) $day; ?></strong>
                    <span><?php echo number_format((int) $count); ?></span>
                </div>
                <div class="funnel-bar"><span style="width: <?php echo $width; ?>%;"></span></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="dashboard-section-divider">
    <h2><i class="fas fa-rocket"></i> BoostHub <span class="divider-sub">engagement engine</span></h2>
</div>
<div class="dashboard-metric-grid investor-metric-grid">
    <?php
    investorMetricCard('Total Tasks Completed', investorMetricFormatNumber($metrics['boosthub']['completed']), 'fa-list-check', 'is-green');
    investorMetricCard('Tasks Today', investorMetricFormatNumber($metrics['boosthub']['today']), 'fa-bolt', 'is-gold');
    investorMetricCard('Pending Reviews', investorMetricFormatNumber($metrics['boosthub']['pending']), 'fa-clock', 'is-orange');
    investorMetricCard('Approval Rate', investorMetricFormatPercent($metrics['boosthub']['approval_rate']), 'fa-thumbs-up', 'is-cyan');
    investorMetricCard('Average Review Time', investorMetricFormatDuration($metrics['boosthub']['avg_review_seconds']), 'fa-stopwatch', 'is-purple');
    ?>
</div>

<div class="dashboard-split investor-section-split">
    <div>
        <div class="dashboard-section-divider">
            <h2><i class="fas fa-star"></i> Reviews</h2>
        </div>
        <div class="dashboard-metric-grid investor-compact-grid">
            <?php
            investorMetricCard('Total Reviews', investorMetricFormatNumber($metrics['reviews']['total']), 'fa-comments', 'is-blue');
            investorMetricCard('Approved Reviews', investorMetricFormatNumber($metrics['reviews']['approved']), 'fa-circle-check', 'is-green');
            investorMetricCard('Average Rating', number_format((float) $metrics['reviews']['avg_rating'], 2), 'fa-star-half-stroke', 'is-gold');
            investorMetricCard('Average Trust Score', number_format((float) $metrics['reviews']['avg_trust_score'], 1), 'fa-shield-heart', 'is-purple');
            investorMetricCard('Reviews Today', investorMetricFormatNumber($metrics['reviews']['today']), 'fa-calendar-day', 'is-orange');
            ?>
        </div>
    </div>
    <div>
        <div class="dashboard-section-divider">
            <h2><i class="fas fa-code"></i> DevHub</h2>
        </div>
        <div class="dashboard-metric-grid investor-compact-grid">
            <?php
            investorMetricCard('Verified Developers', investorMetricFormatNumber($metrics['devhub']['verified_developers']), 'fa-user-shield', 'is-purple');
            investorMetricCard('Submitted Projects', investorMetricFormatNumber($metrics['devhub']['submitted_projects']), 'fa-folder-plus', 'is-blue');
            investorMetricCard('Approved Projects', investorMetricFormatNumber($metrics['devhub']['approved_projects']), 'fa-circle-check', 'is-green');
            investorMetricCard('Projects Under Review', investorMetricFormatNumber($metrics['devhub']['under_review']), 'fa-hourglass-half', 'is-orange');
            ?>
        </div>
    </div>
</div>

<div class="dashboard-section-divider">
    <h2><i class="fas fa-link"></i> RexLink <span class="divider-sub">second product traction</span></h2>
</div>
<div class="dashboard-metric-grid investor-metric-grid">
    <?php
    investorMetricCard('Wallets Linked', investorMetricFormatNumber($metrics['rexlink']['wallets_linked']), 'fa-wallet', 'is-blue');
    investorMetricCard('Sessions Created', investorMetricFormatNumber($metrics['rexlink']['sessions_created']), 'fa-plug-circle-check', 'is-cyan');
    investorMetricCard('Successful Signatures', investorMetricFormatNumber($metrics['rexlink']['successful_signatures']), 'fa-signature', 'is-green');
    investorMetricCard('Authentication Requests', investorMetricFormatNumber($metrics['rexlink']['authentication_requests']), 'fa-key', 'is-gold');
    investorMetricCard('Success Rate', investorMetricFormatPercent($metrics['rexlink']['success_rate']), 'fa-chart-pie', 'is-purple');
    investorMetricCard('Average Signing Time', investorMetricFormatDuration($metrics['rexlink']['avg_signing_seconds']), 'fa-stopwatch', 'is-orange');
    ?>
</div>

<div class="dashboard-section-divider">
    <h2><i class="fas fa-coins"></i> Economy <span class="divider-sub">reward flow</span></h2>
</div>
<div class="dashboard-metric-grid investor-metric-grid">
    <?php
    investorMetricCard('Total REX Earned', investorMetricFormatRex($metrics['economy']['earned']), 'fa-sack-dollar', 'is-gold');
    investorMetricCard('Total REX Claimed', investorMetricFormatRex($metrics['economy']['claimed']), 'fa-vault', 'is-green');
    investorMetricCard('Pending Claims', investorMetricFormatRex($metrics['economy']['pending_claims']), 'fa-hourglass-half', 'is-orange');
    investorMetricCard('Active Referrers', investorMetricFormatNumber($metrics['economy']['active_referrers']), 'fa-user-group', 'is-cyan');
    investorMetricCard('Valid Referrals', investorMetricFormatNumber($metrics['economy']['valid_referrals']), 'fa-user-check', 'is-blue');
    ?>
</div>

<div class="dashboard-section-divider">
    <h2><i class="fas fa-heart-pulse"></i> Retention <span class="divider-sub">VC proof metrics</span></h2>
</div>
<div class="dashboard-metric-grid investor-metric-grid">
    <?php
    investorMetricCard('Day-1 Retention', investorMetricDisplayPercent($metrics['retention']['day1']), 'fa-calendar-check', 'is-green', 'Cohort return after signup', $analyticsQuality);
    investorMetricCard('Day-7 Retention', investorMetricDisplayPercent($metrics['retention']['day7']), 'fa-calendar-week', 'is-cyan', 'Cohort return after signup', $analyticsQuality);
    investorMetricCard('Day-30 Retention', investorMetricDisplayPercent($metrics['retention']['day30']), 'fa-calendar-days', 'is-purple', 'Cohort return after signup', $analyticsQuality);
    investorMetricCard('Returning Users 24H', investorMetricDisplayPercent($metrics['retention']['returning_24h'], 'No activity'), 'fa-rotate-left', 'is-gold', 'Live fallback where available', $activityQuality);
    investorMetricCard('Returning Users 7D', investorMetricDisplayPercent($metrics['retention']['returning_7d'], 'No activity'), 'fa-repeat', 'is-orange', 'Live fallback where available', $activityQuality);
    investorMetricCard('Returning Users 30D', investorMetricDisplayPercent($metrics['retention']['returning_30d'], 'No activity'), 'fa-arrows-rotate', 'is-blue', 'Live fallback where available', $activityQuality);
    investorMetricCard('Avg Session Duration', investorMetricFormatDuration($metrics['retention']['avg_session_seconds']), 'fa-stopwatch', 'is-green', 'Tracked session history', $analyticsQuality);
    ?>
</div>

<div class="dashboard-panel investor-cohort-panel" id="investorMetricsEnd">
    <div class="dashboard-panel-header">
        <h3><i class="fas fa-table"></i> Retention Cohorts</h3>
        <span class="panel-badge">Last 30 days</span>
    </div>
    <?php if (empty($metrics['analytics_ready']) || empty($metrics['cohorts'])): ?>
        <div class="dashboard-empty"><i class="fas fa-chart-line"></i>Not enough tracked data yet.</div>
    <?php else: ?>
        <div class="dashboard-table-wrap">
            <table class="dashboard-table investor-cohort-table">
                <thead>
                    <tr>
                        <th>Cohort Date</th>
                        <th>New Users</th>
                        <th>D1</th>
                        <th>D7</th>
                        <th>D30</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($metrics['cohorts'] as $cohort):
                        $newUsers = max(1, (int) ($cohort['new_users'] ?? 0));
                    ?>
                    <tr>
                        <td data-label="Cohort Date"><?php echo htmlspecialchars((string) ($cohort['cohort_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td data-label="New Users"><?php echo number_format((int) ($cohort['new_users'] ?? 0)); ?></td>
                        <td data-label="D1"><?php echo number_format((int) ($cohort['d1'] ?? 0)); ?> <span><?php echo investorMetricFormatPercent(((int) ($cohort['d1'] ?? 0) / $newUsers) * 100); ?></span></td>
                        <td data-label="D7"><?php echo number_format((int) ($cohort['d7'] ?? 0)); ?> <span><?php echo investorMetricFormatPercent(((int) ($cohort['d7'] ?? 0) / $newUsers) * 100); ?></span></td>
                        <td data-label="D30"><?php echo number_format((int) ($cohort['d30'] ?? 0)); ?> <span><?php echo investorMetricFormatPercent(((int) ($cohort['d30'] ?? 0) / $newUsers) * 100); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($isInvestorShareRequest): ?>
</main>
<script>
(function() {
    var tokenUrl = <?php echo json_encode(ADMIN_BASE_URL . '/metrics.php?v=' . rawurlencode($shareToken) . '&health=1', JSON_UNESCAPED_SLASHES); ?>;
    setInterval(function() {
        fetch(tokenUrl, { credentials: 'same-origin', cache: 'no-store' }).then(function(response) {
            if (!response.ok) {
                document.body.innerHTML = '<main class="investor-share-shell"><div class="dashboard-panel investor-access-denied"><div class="dashboard-header-icon"><i class="fas fa-lock"></i></div><h1>Metrics Access Expired</h1><p>This investor dashboard link is no longer active.</p></div></main>';
            }
        }).catch(function() {});
    }, 30000);
})();
</script>
</body>
</html>
<?php else: ?>
<script>
(function() {
    function notify(message, type) {
        if (typeof showToast === 'function') {
            showToast(message, type || 'info');
        } else {
            alert(message);
        }
    }

    function copyText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(value);
        }
        return new Promise(function(resolve, reject) {
            var input = document.createElement('textarea');
            input.value = value;
            input.setAttribute('readonly', 'readonly');
            input.style.position = 'fixed';
            input.style.left = '-9999px';
            input.style.top = '0';
            document.body.appendChild(input);
            input.focus();
            input.select();
            try {
                var ok = document.execCommand('copy');
                document.body.removeChild(input);
                ok ? resolve() : reject(new Error('Copy failed'));
            } catch (error) {
                document.body.removeChild(input);
                reject(error);
            }
        });
    }

    document.querySelectorAll('[data-copy-investor-link]').forEach(function(button) {
        button.addEventListener('click', function() {
            var value = button.getAttribute('data-copy-investor-link') || '';
            if (!value) {
                notify('No investor link available to copy.', 'error');
                return;
            }
            button.disabled = true;
            copyText(value).then(function() {
                var old = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Copied';
                notify('Investor metrics link copied.', 'success');
                setTimeout(function() { button.innerHTML = old; }, 1600);
            }).catch(function() {
                notify('Copy failed. Please copy the link manually.', 'error');
            }).finally(function() {
                button.disabled = false;
            });
        });
    });
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>
