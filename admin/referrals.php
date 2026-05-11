<?php
$page_title = 'Referral Validation';
$activePage = 'referrals';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);
$current_admin = getCurrentAdmin();
[$message, $message_type] = adminRewardProcessAction($db, $current_admin);
$referral_rows = adminRewardGetReferralRows($db);
?>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Referrals</span>
            <h2>Referral Validation</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
            <tr>
                <th>Referred User</th>
                <th>Referrer</th>
                <th>Status</th>
                <th>Progress</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($referral_rows as $referral_row): ?>
                <tr>
                    <td data-label="Referred User">
                        <strong><?php echo htmlspecialchars((string) ($referral_row['full_name'] ?: $referral_row['username']), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <span class="muted">@<?php echo htmlspecialchars((string) $referral_row['username'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                        <span class="muted"><?php echo htmlspecialchars((string) $referral_row['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td data-label="Referrer">
                        <strong><?php echo htmlspecialchars((string) $referral_row['referrer_username'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <span class="muted">User ID <?php echo (int) $referral_row['referrer_id']; ?></span>
                    </td>
                    <td data-label="Status">
                        <?php $review_status = (string) ($referral_row['referral_review_status'] ?? (!empty($referral_row['referral_qualified_at']) ? 'qualified' : 'pending')); ?>
                        <span class="status-pill <?php echo htmlspecialchars(getReferralReviewStatusClass($review_status), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars(getReferralReviewStatusLabel($review_status), ENT_QUOTES, 'UTF-8'); ?>
                        </span><br>
                        <?php if (!empty($referral_row['referral_qualified_at'])): ?>
                            <span class="muted"><?php echo htmlspecialchars((string) $referral_row['referral_qualified_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($referral_row['referral_flag_reason'])): ?>
                            <br><span class="muted"><?php echo htmlspecialchars((string) $referral_row['referral_flag_reason'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Progress">
                        <span class="muted"><?php echo (int) getCompletedTaskHubDaysCount((int) $referral_row['id'], $db); ?>/4 TaskHub days</span>
                    </td>
                    <td data-label="Action">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <form method="POST" action="" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action_type" value="referral_state">
                                <input type="hidden" name="user_id" value="<?php echo (int) $referral_row['id']; ?>">
                                <input type="hidden" name="decision" value="qualify">
                                <button type="submit" class="btn btn-primary">Approve</button>
                            </form>
                            <form method="POST" action="" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action_type" value="referral_state">
                                <input type="hidden" name="user_id" value="<?php echo (int) $referral_row['id']; ?>">
                                <input type="hidden" name="decision" value="invalidate">
                                <button type="submit" class="btn btn-danger">Invalidate</button>
                            </form>
                            <form method="POST" action="" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action_type" value="referral_state">
                                <input type="hidden" name="user_id" value="<?php echo (int) $referral_row['id']; ?>">
                                <input type="hidden" name="decision" value="flag_manual_review">
                                <input type="hidden" name="flag_reason" value="Manual review requested by admin.">
                                <button type="submit" class="btn">Flag Review</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
