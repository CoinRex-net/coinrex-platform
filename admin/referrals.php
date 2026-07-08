<?php
$page_title = 'Referral Validation';
$activePage = 'referrals';

// ═══════════════════════════════════════════════════════════════
// POST handling MUST happen BEFORE any output (header.php etc.)
// ═══════════════════════════════════════════════════════════════
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/reward_admin.php';
require_once __DIR__ . '/includes/pagination.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);
$current_admin = getCurrentAdmin();

// Handle POST actions — store result in session flash for display after redirect
[$message, $message_type] = adminRewardProcessAction($db, $current_admin);

// If this is a POST request with a message, store it in session flash and redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $message !== '') {
    $_SESSION['_referral_toast'] = [
        'message' => $message,
        'type' => $message_type,
    ];
    $redirect = ADMIN_BASE_URL . '/referrals.php';
    header('Location: ' . $redirect);
    exit;
}

// Read flash message from session (set by previous POST redirect)
$flash_message = '';
$flash_type = 'success';
if (!empty($_SESSION['_referral_toast'])) {
    $flash_message = (string) ($_SESSION['_referral_toast']['message'] ?? '');
    $flash_type = (string) ($_SESSION['_referral_toast']['type'] ?? 'success');
    unset($_SESSION['_referral_toast']);
}

// Use flash message if available, otherwise use current POST message
if ($flash_message !== '') {
    $message = $flash_message;
    $message_type = $flash_type;
}

// Now safe to include header (output starts here)
require_once __DIR__ . '/includes/header.php';

// Handle AJAX GET request
$is_ajax = !empty($_GET['ajax']);
$page = paginationGetPage();
$perPage = paginationGetPerPage(20);
$search = trim((string) ($_GET['search'] ?? ''));
$status_filter = trim((string) ($_GET['status'] ?? ''));

if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $referral_rows = adminRewardGetReferralRows($db, $page, $perPage, $search, $status_filter);
        $total_rows = adminRewardGetReferralRowsCount($db, $search, $status_filter);
        $total_pages = max(1, (int) ceil($total_rows / $perPage));

        $tableBody = '';
        foreach ($referral_rows as $referral_row) {
            $review_status = (string) ($referral_row['referral_review_status'] ?? (!empty($referral_row['referral_qualified_at']) ? 'qualified' : 'pending'));
            $taskhub_days = (int) getCompletedTaskHubDaysCount((int) $referral_row['id'], $db);
            $progress_pct = min(100, round(($taskhub_days / 4) * 100));
            $avatar_letter = strtoupper(substr(trim((string) ($referral_row['full_name'] ?: $referral_row['username'])), 0, 1));
            $abuse_detected = !empty($referral_row['referral_abuse_detected']);
            $abuse_reason = (string) ($referral_row['referral_abuse_reason'] ?? '');

            // Determine if qualify button should be disabled
            // Rule: Only block if user is pending AND hasn't completed 4 LearnHub days
            // Flagged, Invalid, and Pending-with-4-days can all be qualified by admin
            $qualify_disabled = ($review_status === 'qualified');
            $qualify_tooltip = 'Approve referral';
            if (!$qualify_disabled && $review_status === 'pending' && $taskhub_days < 4) {
                $qualify_disabled = true;
                $qualify_tooltip = 'Requires 4 LearnHub days (currently ' . $taskhub_days . ')';
            }

            $tableBody .= '<tr>';
            $tableBody .= '<td data-label="Referred User">
                <div class="user-cell">
                    <div class="user-avatar">' . htmlspecialchars($avatar_letter, ENT_QUOTES, 'UTF-8') . '</div>
                    <div class="user-info">
                        <strong>' . htmlspecialchars((string) ($referral_row['full_name'] ?: $referral_row['username']), ENT_QUOTES, 'UTF-8') . '</strong>
                        <span class="muted">@' . htmlspecialchars((string) $referral_row['username'], ENT_QUOTES, 'UTF-8') . '</span>
                        <span class="muted">Joined ' . htmlspecialchars((string) $referral_row['created_at'], ENT_QUOTES, 'UTF-8') . '</span>
                    </div>
                </div>
            </td>';
            $tableBody .= '<td data-label="Referrer">
                <div class="user-cell">
                    <div class="user-avatar sm">' . htmlspecialchars(strtoupper(substr(trim((string) $referral_row['referrer_username']), 0, 1)), ENT_QUOTES, 'UTF-8') . '</div>
                    <div class="user-info">
                        <strong>' . htmlspecialchars((string) $referral_row['referrer_username'], ENT_QUOTES, 'UTF-8') . '</strong>
                        <span class="muted">ID ' . (int) $referral_row['referrer_id'] . '</span>
                    </div>
                </div>
            </td>';
            $tableBody .= '<td data-label="Status">
                <span class="dashboard-pill ' . htmlspecialchars(getReferralReviewStatusClass($review_status), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(getReferralReviewStatusLabel($review_status), ENT_QUOTES, 'UTF-8') . '</span>';
            if (!empty($referral_row['referral_qualified_at'])) {
                $tableBody .= '<br><span class="muted">' . htmlspecialchars((string) $referral_row['referral_qualified_at'], ENT_QUOTES, 'UTF-8') . '</span>';
            }
            if (!empty($referral_row['referral_flag_reason'])) {
                $tableBody .= '<br><span class="muted flag-reason" title="' . htmlspecialchars((string) $referral_row['referral_flag_reason'], ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-flag"></i> ' . htmlspecialchars((string) $referral_row['referral_flag_reason'], ENT_QUOTES, 'UTF-8') . '</span>';
            }
            if ($abuse_detected) {
                $tableBody .= '<br><span class="muted abuse-badge" title="' . htmlspecialchars($abuse_reason, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-shield-alt"></i> Abuse: ' . htmlspecialchars($abuse_reason, ENT_QUOTES, 'UTF-8') . '</span>';
            }
            $tableBody .= '</td>';
            $tableBody .= '<td data-label="Progress">
                <div class="progress-cell">
                    <div class="progress-bar-wrap">
                        <div class="progress-bar-fill" style="width:' . $progress_pct . '%"></div>
                    </div>
                    <span class="muted">' . $taskhub_days . '/4 LearnHub days</span>
                </div>
            </td>';
            $tableBody .= '<td data-label="Action">
                <div class="action-cell">
                    <form method="POST" action="" class="inline-form referral-action-form">
                        <input type="hidden" name="csrf_token" value="' . htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8') . '">
                        <input type="hidden" name="action_type" value="referral_state">
                        <input type="hidden" name="user_id" value="' . (int) $referral_row['id'] . '">
                        <input type="hidden" name="decision" value="qualify">
                        <button type="submit" class="btn btn-primary btn-sm" title="' . htmlspecialchars($qualify_tooltip, ENT_QUOTES, 'UTF-8') . '" ' . ($qualify_disabled ? 'disabled' : '') . '><i class="fas fa-check"></i></button>
                    </form>
                    <form method="POST" action="" class="inline-form referral-action-form">
                        <input type="hidden" name="csrf_token" value="' . htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8') . '">
                        <input type="hidden" name="action_type" value="referral_state">
                        <input type="hidden" name="user_id" value="' . (int) $referral_row['id'] . '">
                        <input type="hidden" name="decision" value="invalidate">
                        <button type="submit" class="btn btn-danger btn-sm" title="Invalidate referral" ' . ($review_status === 'invalid' ? 'disabled' : '') . '><i class="fas fa-times"></i></button>
                    </form>
                    <form method="POST" action="" class="inline-form referral-action-form">
                        <input type="hidden" name="csrf_token" value="' . htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8') . '">
                        <input type="hidden" name="action_type" value="referral_state">
                        <input type="hidden" name="user_id" value="' . (int) $referral_row['id'] . '">
                        <input type="hidden" name="decision" value="flag_manual_review">
                        <input type="hidden" name="flag_reason" value="Manual review requested by admin.">
                        <button type="submit" class="btn btn-warning btn-sm" title="Flag for manual review" ' . ($review_status === 'flagged_manual_review' ? 'disabled' : '') . '><i class="fas fa-flag"></i></button>
                    </form>
                    <form method="POST" action="" class="inline-form referral-action-form">
                        <input type="hidden" name="csrf_token" value="' . htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8') . '">
                        <input type="hidden" name="action_type" value="referral_state">
                        <input type="hidden" name="user_id" value="' . (int) $referral_row['id'] . '">
                        <input type="hidden" name="decision" value="reset_pending">
                        <button type="submit" class="btn btn-secondary btn-sm" title="Reset to pending" ' . ($review_status === 'pending' ? 'disabled' : '') . '><i class="fas fa-undo"></i></button>
                    </form>
                </div>
            </td>';
            $tableBody .= '</tr>';
        }

        if ($tableBody === '') {
            $tableBody = '<tr><td colspan="5" class="dashboard-empty"><i class="fas fa-user-plus"></i><p>No referrals found matching your criteria.</p></td></tr>';
        }

        $paginationHtml = renderPagination($page, $total_pages, ADMIN_BASE_URL . '/referrals.php', array_filter(['search' => $search, 'status' => $status_filter]));

        echo json_encode(paginationJsonResponse($tableBody, $paginationHtml, $page));
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Non-AJAX: get metrics and first page
$metrics = adminRewardGetReferralMetrics($db);
$referral_rows = adminRewardGetReferralRows($db, $page, $perPage, $search, $status_filter);
$total_rows = adminRewardGetReferralRowsCount($db, $search, $status_filter);
$total_pages = max(1, (int) ceil($total_rows / $perPage));
?>

<?php paginationRenderStyles(); ?>

<style>
/* ====== Referral Validation — Dashboard Theme Overrides ====== */
.user-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}
.user-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, #d4af37, #b8860b);
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}
.user-avatar.sm {
    width: 28px;
    height: 28px;
    font-size: 11px;
}
.user-info {
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.user-info strong {
    font-size: 14px;
    color: #e2e8f0;
}
.user-info .muted {
    font-size: 11px;
    color: #64748b;
}

.progress-cell {
    min-width: 120px;
}
.progress-bar-wrap {
    width: 100%;
    height: 6px;
    background: rgba(255,255,255,0.08);
    border-radius: 3px;
    overflow: hidden;
    margin-bottom: 4px;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #d4af37, #f0d060);
    border-radius: 3px;
    transition: width 0.3s;
}

.action-cell {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.action-cell .btn-sm {
    padding: 6px 10px;
    font-size: 12px;
    min-width: 32px;
}
.btn-warning {
    background: rgba(245,158,11,0.15);
    color: #f59e0b;
    border: 1px solid rgba(245,158,11,0.3);
}
.btn-warning:hover {
    background: rgba(245,158,11,0.25);
}
.btn-secondary {
    background: rgba(100,116,139,0.15);
    color: #94a3b8;
    border: 1px solid rgba(100,116,139,0.3);
}
.btn-secondary:hover {
    background: rgba(100,116,139,0.25);
}

.flag-reason {
    font-size: 11px;
    color: #ef4444 !important;
    cursor: help;
}
.flag-reason i {
    margin-right: 3px;
}

.abuse-badge {
    font-size: 11px;
    color: #f59e0b !important;
    cursor: help;
}
.abuse-badge i {
    margin-right: 3px;
}

/* Override dashboard-pill for referral statuses */
.dashboard-pill.status-approved {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
    border: 1px solid rgba(34, 197, 94, 0.2);
}
.dashboard-pill.status-pending {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.2);
}
.dashboard-pill.status-flagged {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.2);
}
.dashboard-pill.status-rejected {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
    border: 1px solid rgba(100, 116, 139, 0.2);
}

/* Metric card icon colors */
.dashboard-metric-card .metric-icon.is-total {
    background: rgba(212, 175, 55, 0.15);
    color: #f5d76e;
}
.dashboard-metric-card .metric-icon.is-valid {
    background: rgba(34, 197, 94, 0.15);
    color: #4ade80;
}
.dashboard-metric-card .metric-icon.is-pending {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
}
.dashboard-metric-card .metric-icon.is-flagged {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
}
.dashboard-metric-card .metric-icon.is-invalid {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
}

/* Filter form — responsive (avoid fixed 4-col overflow on mobile) */
.dashboard-filter-bar.referral-filter-bar {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
}
.dashboard-filter-form.referral-filter {
    display: grid;
    grid-template-columns: minmax(0, 180px) minmax(0, 1fr);
    gap: 10px;
    width: 100%;
    max-width: 100%;
}
.dashboard-filter-form.referral-filter .referral-filter-actions {
    grid-column: 1 / -1;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.dashboard-filter-form.referral-filter .referral-filter-actions .btn {
    width: auto;
    min-width: 100px;
    flex: 0 0 auto;
}
@media (max-width: 768px) {
    .dashboard-filter-form.referral-filter {
        grid-template-columns: 1fr;
    }
}

/* Disabled button tooltip fallback for browsers that don't show tooltips on disabled buttons */
.btn-sm[disabled] {
    pointer-events: none;
    opacity: 0.4;
}
</style>

<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-user-check"></i></div>
            <div class="dashboard-header-text">
                <h1>Referral Validation</h1>
                <p>All referred users — Valid, Pending, Flagged, and Invalid. Abuse-detected users flagged automatically. Admin cannot bypass the 4 LearnHub day requirement.</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-users"></i> <?php echo number_format($metrics['total']); ?> Referrals
        </div>
    </div>

    <?php if ($message !== ''): ?>
    <!-- Toast message from POST action -->
    <div id="pageToast" data-message="<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>" data-type="<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" style="display:none;"></div>
    <?php endif; ?>

    <!-- ====== METRICS ====== -->
    <div class="dashboard-metric-grid">
        <div class="dashboard-metric-card">
            <div class="metric-top">
                <span class="metric-label">Total Referrals</span>
                <div class="metric-icon is-total"><i class="fas fa-users"></i></div>
            </div>
            <span class="metric-value"><?php echo number_format($metrics['total']); ?></span>
        </div>
        <div class="dashboard-metric-card">
            <div class="metric-top">
                <span class="metric-label">Valid</span>
                <div class="metric-icon is-valid"><i class="fas fa-check-circle"></i></div>
            </div>
            <span class="metric-value"><?php echo number_format($metrics['valid']); ?></span>
        </div>
        <div class="dashboard-metric-card">
            <div class="metric-top">
                <span class="metric-label">Pending</span>
                <div class="metric-icon is-pending"><i class="fas fa-clock"></i></div>
            </div>
            <span class="metric-value"><?php echo number_format($metrics['pending']); ?></span>
        </div>
        <div class="dashboard-metric-card">
            <div class="metric-top">
                <span class="metric-label">Flagged</span>
                <div class="metric-icon is-flagged"><i class="fas fa-flag"></i></div>
            </div>
            <span class="metric-value"><?php echo number_format($metrics['flagged']); ?></span>
        </div>
        <div class="dashboard-metric-card">
            <div class="metric-top">
                <span class="metric-label">Invalid</span>
                <div class="metric-icon is-invalid"><i class="fas fa-times-circle"></i></div>
            </div>
            <span class="metric-value"><?php echo number_format($metrics['invalid']); ?></span>
        </div>
    </div>

    <!-- ====== SECTION DIVIDER ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-list"></i> Referrals <span class="divider-sub">Browse and manage all referral validations</span></h2>
    </div>

    <!-- ====== PANEL ====== -->
    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-user-check"></i> Referrals</span>
                <h3>Referral Validation Management</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Review all referred users. Valid referrals are read-only. Pending users with <4 LearnHub days cannot be approved. Flagged users show the abuse reason. Invalid users show the flag reason.</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="dashboard-filter-bar referral-filter-bar">
            <form id="filterForm" class="dashboard-filter-form referral-filter" method="GET" action="<?php echo htmlspecialchars(ADMIN_BASE_URL . '/referrals.php', ENT_QUOTES, 'UTF-8'); ?>">
                <select name="status" id="statusFilter" aria-label="Filter by status">
                    <option value="">All Statuses</option>
                    <option value="qualified" <?php echo $status_filter === 'qualified' ? 'selected' : ''; ?>>Valid</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="flagged_manual_review" <?php echo $status_filter === 'flagged_manual_review' ? 'selected' : ''; ?>>Flagged</option>
                    <option value="invalid" <?php echo $status_filter === 'invalid' ? 'selected' : ''; ?>>Invalid</option>
                </select>
                <input type="text" name="search" id="searchInput" placeholder="Search by name, username, or referrer..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Search referrals">
                <div class="referral-filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
                    <?php if ($search !== '' || $status_filter !== ''): ?>
                        <a href="<?php echo htmlspecialchars(ADMIN_BASE_URL . '/referrals.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="dashboard-table-wrap">
            <table class="dashboard-table" id="referralsTable">
                <thead>
                <tr>
                    <th>Referred User</th>
                    <th>Referrer</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody id="tableBody">
                <?php if (empty($referral_rows)): ?>
                    <tr>
                        <td colspan="5" class="dashboard-empty">
                            <i class="fas fa-user-plus"></i>
                            <p>No referrals found matching your criteria.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($referral_rows as $referral_row): ?>
                        <?php
                        $review_status = (string) ($referral_row['referral_review_status'] ?? (!empty($referral_row['referral_qualified_at']) ? 'qualified' : 'pending'));
                        $taskhub_days = (int) getCompletedTaskHubDaysCount((int) $referral_row['id'], $db);
                        $progress_pct = min(100, round(($taskhub_days / 4) * 100));
                        $avatar_letter = strtoupper(substr(trim((string) ($referral_row['full_name'] ?: $referral_row['username'])), 0, 1));
                        $abuse_detected = !empty($referral_row['referral_abuse_detected']);
                        $abuse_reason = (string) ($referral_row['referral_abuse_reason'] ?? '');

                        // Determine if qualify button should be disabled
                        // Rule: Only block if user is pending AND hasn't completed 4 LearnHub days
                        // Flagged, Invalid, and Pending-with-4-days can all be qualified by admin
                        $qualify_disabled = ($review_status === 'qualified');
                        $qualify_tooltip = 'Approve referral';
                        if (!$qualify_disabled && $review_status === 'pending' && $taskhub_days < 4) {
                            $qualify_disabled = true;
                            $qualify_tooltip = 'Requires 4 LearnHub days (currently ' . $taskhub_days . ')';
                        }
                        ?>
                        <tr>
                            <td data-label="Referred User">
                                <div class="user-cell">
                                    <div class="user-avatar"><?php echo htmlspecialchars($avatar_letter, ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="user-info">
                                        <strong><?php echo htmlspecialchars((string) ($referral_row['full_name'] ?: $referral_row['username']), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="muted">@<?php echo htmlspecialchars((string) $referral_row['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="muted">Joined <?php echo htmlspecialchars((string) $referral_row['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Referrer">
                                <div class="user-cell">
                                    <div class="user-avatar sm"><?php echo htmlspecialchars(strtoupper(substr(trim((string) $referral_row['referrer_username']), 0, 1)), ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="user-info">
                                        <strong><?php echo htmlspecialchars((string) $referral_row['referrer_username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="muted">ID <?php echo (int) $referral_row['referrer_id']; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Status">
                                <span class="dashboard-pill <?php echo htmlspecialchars(getReferralReviewStatusClass($review_status), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(getReferralReviewStatusLabel($review_status), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <?php if (!empty($referral_row['referral_qualified_at'])): ?>
                                    <br><span class="muted"><?php echo htmlspecialchars((string) $referral_row['referral_qualified_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($referral_row['referral_flag_reason'])): ?>
                                    <br><span class="muted flag-reason" title="<?php echo htmlspecialchars((string) $referral_row['referral_flag_reason'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-flag"></i> <?php echo htmlspecialchars((string) $referral_row['referral_flag_reason'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <?php if ($abuse_detected): ?>
                                    <br><span class="muted abuse-badge" title="<?php echo htmlspecialchars($abuse_reason, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-shield-alt"></i> Abuse: <?php echo htmlspecialchars($abuse_reason, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Progress">
                                <div class="progress-cell">
                                    <div class="progress-bar-wrap">
                                        <div class="progress-bar-fill" style="width:<?php echo $progress_pct; ?>%"></div>
                                    </div>
                                    <span class="muted"><?php echo $taskhub_days; ?>/4 LearnHub days</span>
                                </div>
                            </td>
                            <td data-label="Action">
                                <div class="action-cell">
                                    <form method="POST" action="" class="inline-form referral-action-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action_type" value="referral_state">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $referral_row['id']; ?>">
                                        <input type="hidden" name="decision" value="qualify">
                                        <button type="submit" class="btn btn-primary btn-sm" title="<?php echo htmlspecialchars($qualify_tooltip, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $qualify_disabled ? 'disabled' : ''; ?>><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" action="" class="inline-form referral-action-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action_type" value="referral_state">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $referral_row['id']; ?>">
                                        <input type="hidden" name="decision" value="invalidate">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Invalidate referral" <?php echo $review_status === 'invalid' ? 'disabled' : ''; ?>><i class="fas fa-times"></i></button>
                                    </form>
                                    <form method="POST" action="" class="inline-form referral-action-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action_type" value="referral_state">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $referral_row['id']; ?>">
                                        <input type="hidden" name="decision" value="flag_manual_review">
                                        <input type="hidden" name="flag_reason" value="Manual review requested by admin.">
                                        <button type="submit" class="btn btn-warning btn-sm" title="Flag for manual review" <?php echo $review_status === 'flagged_manual_review' ? 'disabled' : ''; ?>><i class="fas fa-flag"></i></button>
                                    </form>
                                    <form method="POST" action="" class="inline-form referral-action-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action_type" value="referral_state">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $referral_row['id']; ?>">
                                        <input type="hidden" name="decision" value="reset_pending">
                                        <button type="submit" class="btn btn-secondary btn-sm" title="Reset to pending" <?php echo $review_status === 'pending' ? 'disabled' : ''; ?>><i class="fas fa-undo"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div id="pagination">
            <?php echo renderPagination($page, $total_pages, ADMIN_BASE_URL . '/referrals.php', array_filter(['search' => $search, 'status' => $status_filter])); ?>
        </div>
    </div>

</div><!-- /.dashboard-container -->

<?php
// AJAX pagination JS (handles pagination clicks + filter form)
paginationRenderJS([
    'tableBodyId' => 'tableBody',
    'paginationId' => 'pagination',
    'fetchUrl' => ADMIN_BASE_URL . '/referrals.php',
    'filterFormId' => 'filterForm',
    'extraParams' => ['search', 'status'],
    'pageParam' => 'page',
    'loadingText' => 'Loading referrals',
]);

?>

<!-- Custom JS: show toast from server-side message on page load -->
<script>
(function () {
    'use strict';

    var pageToast = document.getElementById('pageToast');
    if (pageToast) {
        var msg = pageToast.getAttribute('data-message');
        var type = pageToast.getAttribute('data-type') || 'info';
        if (msg) {
            setTimeout(function () {
                showToast(msg, type);
            }, 100);
        }
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
