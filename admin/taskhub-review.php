<?php
$page_title = 'TaskHub Review';
$activePage = 'taskhub-review';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);
$current_admin = getCurrentAdmin();
[$message, $message_type] = adminRewardProcessAction($db, $current_admin);
$taskhub_review_rows = adminRewardGetTaskhubReviewRows($db);
?>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">TaskHub</span>
            <h2>Manual Review Queue</h2>
            <p class="muted">Review pending evidence for TaskHub mission tasks.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
            <tr>
                <th>User</th>
                <th>Task</th>
                <th>Proof</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($taskhub_review_rows)): ?>
                <?php foreach ($taskhub_review_rows as $review_row): ?>
                    <?php $trace = !empty($review_row['metadata']) ? (json_decode((string) $review_row['metadata'], true) ?: []) : []; ?>
                    <tr>
                        <td data-label="User">
                            <strong><?php echo htmlspecialchars((string) $review_row['username'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="muted"><?php echo htmlspecialchars((string) $review_row['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Task">
                            <span class="status-pill status-pending">MISSION</span><br>
                            <strong>Day <?php echo (int) $review_row['mission_day']; ?></strong><br>
                            <span class="muted"><?php echo htmlspecialchars((string) $review_row['title'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <span class="muted"><?php echo htmlspecialchars((string) ($review_row['task_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Proof">
                            <code><?php echo htmlspecialchars((string) ($review_row['proof_data'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
                            <?php if (!empty($trace['platform'])): ?>
                                <br><span class="muted">Platform: <?php echo htmlspecialchars((string) $trace['platform'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($trace['submitted_ip'])): ?>
                                <br><span class="muted">IP: <?php echo htmlspecialchars((string) $trace['submitted_ip'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($trace['submitted_user_agent'])): ?>
                                <br><span class="muted">UA: <?php echo htmlspecialchars((string) $trace['submitted_user_agent'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($trace['submitted_referer'])): ?>
                                <br><span class="muted">Ref: <?php echo htmlspecialchars((string) $trace['submitted_referer'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Action">
                            <form method="POST" action="" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action_type" value="review_taskhub_submission">
                                <input type="hidden" name="log_id" value="<?php echo (int) $review_row['id']; ?>">
                                <input type="hidden" name="decision" value="approve">
                                <button type="submit" class="btn btn-primary">Approve</button>
                            </form>
                            <form method="POST" action="" class="inline-form" style="margin-top:8px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action_type" value="review_taskhub_submission">
                                <input type="hidden" name="log_id" value="<?php echo (int) $review_row['id']; ?>">
                                <input type="hidden" name="decision" value="reject">
                                <button type="submit" class="btn btn-danger">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No pending submissions.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
