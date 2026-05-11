<?php
$page_title = 'Reward Users';
$activePage = 'reward-users';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);
$current_admin = getCurrentAdmin();
[$message, $message_type] = adminRewardProcessAction($db, $current_admin);
$user_search = trim((string) ($_GET['q'] ?? ''));
$users = adminRewardGetUsers($db, $user_search);
?>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="admin-toolbar">
        <div>
            <span class="admin-kicker">Users</span>
            <h2 style="margin:10px 0 0;">Reward Users</h2>
        </div>
        <form method="GET" action="" class="inline-form">
            <input type="text" name="q" placeholder="Search users" value="<?php echo htmlspecialchars($user_search, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-secondary">Search</button>
        </form>
    </div>
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
            <tr>
                <th>User</th>
                <th>Level</th>
                <th>Balances</th>
                <th>Signals</th>
                <th>Freeze</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user_row): ?>
                <?php $level_state = getUserLevelState(['id' => (int) $user_row['id'], 'level' => (string) ($user_row['level'] ?? 'beginner')], $db); ?>
                <tr>
                    <td data-label="User">
                        <strong><?php echo htmlspecialchars((string) ($user_row['full_name'] ?: $user_row['username']), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <span class="muted">@<?php echo htmlspecialchars((string) $user_row['username'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                        <span class="muted"><?php echo htmlspecialchars((string) $user_row['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td data-label="Level">
                        <span class="status-pill <?php echo ($level_state['level'] ?? 'beginner') === 'expert' ? 'status-approved' : (($level_state['level'] ?? 'beginner') === 'pro' ? 'status-pending' : 'status-disabled'); ?>">
                            <?php echo htmlspecialchars((string) ($level_state['display_level'] ?? 'Beginner'), ENT_QUOTES, 'UTF-8'); ?>
                        </span><br>
                        <span class="muted"><?php echo number_format((float) ($level_state['accuracy'] ?? 0), 1); ?>% accuracy</span><br>
                        <span class="muted"><?php echo number_format((int) ($user_row['approved_reviews_count'] ?? 0)); ?> approved reviews</span>
                    </td>
                    <td data-label="Balances">
                        <strong><?php echo number_format((float) ($user_row['available_balance'] ?? 0), 2); ?> available</strong><br>
                        <span class="muted"><?php echo number_format((float) ($user_row['locked_balance'] ?? 0), 2); ?> locked</span><br>
                        <span class="muted"><?php echo number_format((int) ($user_row['valid_referrals'] ?? 0)); ?> referrals</span>
                    </td>
                    <td data-label="Signals">
                        <?php if ((int) ($user_row['login_attempts'] ?? 0) >= ANTI_FARM_MAX_LOGIN_ATTEMPTS): ?>
                            <span class="status-pill status-flagged">High login attempts</span><br>
                        <?php endif; ?>
                        <?php if (!empty($user_row['signup_ip']) && !empty($user_row['last_ip']) && $user_row['signup_ip'] === $user_row['last_ip']): ?>
                            <span class="status-pill status-under-review">Stable IP fingerprint</span><br>
                        <?php endif; ?>
                        <span class="muted">Status: <?php echo htmlspecialchars((string) ($user_row['status'] ?? 'active'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td data-label="Freeze">
                        <form method="POST" action="" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action_type" value="toggle_freeze">
                            <input type="hidden" name="user_id" value="<?php echo (int) $user_row['id']; ?>">
                            <input type="hidden" name="reward_frozen" value="<?php echo !empty($user_row['reward_frozen']) ? '0' : '1'; ?>">
                            <button type="submit" class="btn <?php echo !empty($user_row['reward_frozen']) ? 'btn-secondary' : 'btn-danger'; ?>">
                                <?php echo !empty($user_row['reward_frozen']) ? 'Unfreeze rewards' : 'Freeze rewards'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
