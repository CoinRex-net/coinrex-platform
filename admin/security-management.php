<?php
$page_title = 'Security Management';
$activePage = 'security-management';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['security_action'])) {
    try {
        requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
        $user_id = (int) ($_POST['user_id'] ?? 0);
        $action = strtolower(trim((string) ($_POST['security_action'] ?? '')));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $hours = max(1, (int) ($_POST['hours'] ?? 24));

        applySecurityActionToUser($user_id, $action, [
            'reason' => $reason,
            'hours' => $hours,
        ], $db);

        logAdminActivity((int) ($current_admin['id'] ?? 0), 'security_action', 'user', (string) $user_id, json_encode([
            'action' => $action,
            'hours' => $hours,
            'reason' => $reason,
        ], JSON_UNESCAPED_UNICODE));

        $message = 'Security action applied successfully.';
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

$has_fraud_events_table = tableExists('fraud_events');
$alerts = [];
if ($has_fraud_events_table) {
    $stmt = $db->query("SELECT id, event_type, severity, user_id, email, created_at, details_json FROM fraud_events ORDER BY id DESC LIMIT 100");
    $alerts = $stmt ? $stmt->fetchAll() : [];
}

$flagged_users_stmt = $db->query("SELECT id, full_name, username, email, status, security_flagged, security_flag_reason, security_warning_count, security_suspended, taskhub_blocked_until, boosthub_blocked_until, review_blocked_until FROM users WHERE security_flagged = 1 OR security_suspended = 1 ORDER BY updated_at DESC, id DESC LIMIT 100");
$flagged_users = $flagged_users_stmt ? $flagged_users_stmt->fetchAll() : [];

// Calculate stats
$flagged_count = 0;
$suspended_count = 0;
$blocked_count = 0;
foreach ($flagged_users as $u) {
    if (!empty($u['security_suspended'])) $suspended_count++;
    if (!empty($u['security_flagged'])) $flagged_count++;
    if (!empty($u['taskhub_blocked_until']) || !empty($u['boosthub_blocked_until']) || !empty($u['review_blocked_until'])) $blocked_count++;
}
$events_count = count($alerts);
?>
<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-shield-hacked"></i></div>
            <div class="dashboard-header-text">
                <h1>Security Management</h1>
                <p>Review system flagged patterns and take action (warning, suspension, temporary module blocks)</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-database"></i> <?php echo number_format(count($flagged_users)); ?> flagged
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div data-toast data-toast-message="<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>" data-toast-type="<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" style="display:none;"></div>
    <?php endif; ?>

    <!-- ====== SECTION 1: OVERVIEW METRICS ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-chart-bar"></i> Overview <span class="divider-sub">Security queue metrics</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-shield"></i> Security</span>
                <h3>Security Management Overview</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Monitor flagged users, apply warnings, suspensions, and temporary module blocks.</p>
            </div>
        </div>
        <div class="dashboard-metric-grid">
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-gold"><i class="fas fa-flag"></i></div></div>
                <span class="metric-value"><?php echo number_format($flagged_count); ?></span>
                <span class="metric-label">Flagged Users</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-red"><i class="fas fa-ban"></i></div></div>
                <span class="metric-value"><?php echo number_format($suspended_count); ?></span>
                <span class="metric-label">Suspended</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-orange"><i class="fas fa-lock"></i></div></div>
                <span class="metric-value"><?php echo number_format($blocked_count); ?></span>
                <span class="metric-label">Active Blocks</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-blue"><i class="fas fa-exclamation-triangle"></i></div></div>
                <span class="metric-value"><?php echo number_format($events_count); ?></span>
                <span class="metric-label">Security Events</span>
            </div>
        </div>
    </div>

    <!-- ====== SECTION 2: FLAGGED USERS ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-users"></i> Flagged Users <span class="divider-sub">Users with active security flags or suspensions</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Flags</th>
                    <th>Blocks</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($flagged_users)): ?>
                    <tr><td colspan="5" class="muted" style="text-align:center;padding:30px;color:#64748b;">No flagged users found.</td></tr>
                <?php else: ?>
                    <?php foreach ($flagged_users as $user_row): ?>
                        <tr>
                            <td data-label="ID"><?php echo (int) $user_row['id']; ?></td>
                            <td data-label="User">
                                <strong><?php echo htmlspecialchars((string) ($user_row['full_name'] ?: $user_row['username']), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <span class="muted">@<?php echo htmlspecialchars((string) $user_row['username'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                                <span class="muted"><?php echo htmlspecialchars((string) $user_row['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td data-label="Flags">
                                <span class="dashboard-pill <?php echo !empty($user_row['security_suspended']) ? 'is-suspended' : 'is-pending'; ?>">
                                    <?php echo !empty($user_row['security_suspended']) ? 'Suspended' : 'Flagged'; ?>
                                </span><br>
                                <span class="muted" style="font-size:11px;">Warnings: <?php echo (int) ($user_row['security_warning_count'] ?? 0); ?></span><br>
                                <span class="muted" style="font-size:11px;"><?php echo htmlspecialchars((string) ($user_row['security_flag_reason'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td data-label="Blocks">
                                <span class="muted" style="font-size:11px;display:block;">LearnHub: <?php echo htmlspecialchars((string) ($user_row['taskhub_blocked_until'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="muted" style="font-size:11px;display:block;">BoostHub: <?php echo htmlspecialchars((string) ($user_row['boosthub_blocked_until'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="muted" style="font-size:11px;display:block;">Review: <?php echo htmlspecialchars((string) ($user_row['review_blocked_until'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td data-label="Action">
                                <form method="POST" class="action-stack-form" style="display:grid;gap:6px;min-width:160px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $user_row['id']; ?>">
                                    <select name="security_action" required style="width:100%;background:linear-gradient(180deg,rgba(15,23,42,0.96),rgba(11,18,32,0.92));border:1px solid rgba(148,163,184,0.18);color:#f8fafc;border-radius:12px;padding:8px 10px;font-size:12px;">
                                        <option value="warning">Warning</option>
                                        <option value="suspend">Suspend Account</option>
                                        <option value="block_taskhub">Temp Block LearnHub</option>
                                        <option value="block_boosthub">Temp Block BoostHub</option>
                                        <option value="block_review">Temp Block Review</option>
                                        <option value="clear_flags">Clear Flags/Blocks</option>
                                    </select>
                                    <input type="number" name="hours" min="1" value="24" placeholder="Hours" style="width:100%;background:linear-gradient(180deg,rgba(15,23,42,0.96),rgba(11,18,32,0.92));border:1px solid rgba(148,163,184,0.18);color:#f8fafc;border-radius:12px;padding:8px 10px;font-size:12px;">
                                    <input type="text" name="reason" placeholder="Reason (optional)" style="width:100%;background:linear-gradient(180deg,rgba(15,23,42,0.96),rgba(11,18,32,0.92));border:1px solid rgba(148,163,184,0.18);color:#f8fafc;border-radius:12px;padding:8px 10px;font-size:12px;">
                                    <button type="submit" class="btn btn-primary action-stack-btn" style="font-size:12px;padding:6px 10px;min-width:auto;width:100%;justify-content:center;">Apply</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ====== SECTION 3: SECURITY EVENTS ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-history"></i> Recent Security Events <span class="divider-sub">Fraud signals and system alerts</span></h2>
    </div>

    <div class="dashboard-panel">
        <?php if (!$has_fraud_events_table): ?>
            <div class="dashboard-empty">
                <i class="fas fa-database"></i>
                <p>Run migration <code style="background:rgba(212,175,55,0.1);color:#f5d76e;padding:2px 6px;border-radius:4px;">database/migrations/2026_05_04_user_security_signals.sql</code> first.</p>
            </div>
        <?php else: ?>
            <div class="dashboard-table-wrap">
                <table class="dashboard-table">
                    <thead>
                    <tr>
                        <th>Time</th>
                        <th>Severity</th>
                        <th>Event</th>
                        <th>Email</th>
                        <th>Reason</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($alerts)): ?>
                        <tr><td colspan="5" class="muted" style="text-align:center;padding:30px;color:#64748b;">No alerts logged.</td></tr>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): $details = json_decode((string) ($alert['details_json'] ?? ''), true) ?: []; ?>
                            <tr>
                                <td data-label="Time"><?php echo htmlspecialchars((string) ($alert['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Severity">
                                    <span class="dashboard-pill <?php echo strtolower((string) ($alert['severity'] ?? 'warning')) === 'critical' ? 'is-suspended' : 'is-pending'; ?>">
                                        <?php echo htmlspecialchars((string) ($alert['severity'] ?? 'warning'), ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td data-label="Event"><?php echo htmlspecialchars((string) ($alert['event_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Email"><?php echo htmlspecialchars((string) ($alert['email'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td data-label="Reason"><?php echo htmlspecialchars((string) ($details['reason'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /.dashboard-container -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
