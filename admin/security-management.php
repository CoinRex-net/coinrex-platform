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
?>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Security</span>
            <h2>Security Management</h2>
            <p class="muted">Review system flagged patterns and take action (warning, suspension, temporary module blocks).</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
            <tr>
                <th>User</th>
                <th>Flags</th>
                <th>Blocks</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($flagged_users)): ?>
                <tr><td colspan="4" class="muted">No flagged users found.</td></tr>
            <?php else: ?>
                <?php foreach ($flagged_users as $user_row): ?>
                    <tr>
                        <td data-label="User">
                            <strong><?php echo htmlspecialchars((string) ($user_row['full_name'] ?: $user_row['username']), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="muted">@<?php echo htmlspecialchars((string) $user_row['username'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <span class="muted"><?php echo htmlspecialchars((string) $user_row['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Flags">
                            <span class="status-pill <?php echo !empty($user_row['security_suspended']) ? 'status-rejected' : 'status-pending'; ?>">
                                <?php echo !empty($user_row['security_suspended']) ? 'Suspended' : 'Flagged'; ?>
                            </span><br>
                            <span class="muted">Warnings: <?php echo (int) ($user_row['security_warning_count'] ?? 0); ?></span><br>
                            <span class="muted"><?php echo htmlspecialchars((string) ($user_row['security_flag_reason'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Blocks">
                            <span class="muted">TaskHub: <?php echo htmlspecialchars((string) ($user_row['taskhub_blocked_until'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <span class="muted">BoostHub: <?php echo htmlspecialchars((string) ($user_row['boosthub_blocked_until'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <span class="muted">Review: <?php echo htmlspecialchars((string) ($user_row['review_blocked_until'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Action">
                            <form method="POST" class="inline-form" style="display:grid;gap:6px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="user_id" value="<?php echo (int) $user_row['id']; ?>">
                                <select name="security_action" required>
                                    <option value="warning">Warning</option>
                                    <option value="suspend">Suspend Account</option>
                                    <option value="block_taskhub">Temp Block TaskHub</option>
                                    <option value="block_boosthub">Temp Block BoostHub</option>
                                    <option value="block_review">Temp Block Review Submission</option>
                                    <option value="clear_flags">Clear Flags/Blocks</option>
                                </select>
                                <input type="number" name="hours" min="1" value="24" placeholder="Hours for temp block">
                                <input type="text" name="reason" placeholder="Reason (optional)">
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="panel">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Signals</span>
            <h3>Recent Security Events</h3>
        </div>
    </div>
    <?php if (!$has_fraud_events_table): ?>
        <div class="muted">Run migration <code>database/migrations/2026_05_04_user_security_signals.sql</code> first.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="responsive-table">
                <thead><tr><th>Time</th><th>Severity</th><th>Event</th><th>Email</th><th>Reason</th></tr></thead>
                <tbody>
                <?php if (empty($alerts)): ?>
                    <tr><td colspan="5" class="muted">No alerts logged.</td></tr>
                <?php else: ?>
                    <?php foreach ($alerts as $alert): $details = json_decode((string) ($alert['details_json'] ?? ''), true) ?: []; ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($alert['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="status-pill <?php echo strtolower((string) ($alert['severity'] ?? 'warning')) === 'critical' ? 'status-rejected' : 'status-pending'; ?>"><?php echo htmlspecialchars((string) ($alert['severity'] ?? 'warning'), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td><?php echo htmlspecialchars((string) ($alert['event_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($alert['email'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($details['reason'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
