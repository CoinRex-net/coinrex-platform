<?php
$page_title = 'Reward Users';
$activePage = 'reward-users';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';
require_once __DIR__ . '/includes/pagination.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);
$current_admin = getCurrentAdmin();
[$message, $message_type] = adminRewardProcessAction($db, $current_admin);

$perPage = 20;
$user_search = trim((string) ($_GET['q'] ?? ''));
$page = paginationGetPage('page', 1);

$users = adminRewardGetUsers($db, $user_search, $page, $perPage);
$total_users = adminRewardGetUsersCount($db, $user_search);
$total_pages = max(1, (int) ceil($total_users / $perPage));

// AJAX mode: return only table HTML + pagination
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');

    $tableBody = '';
    if (empty($users)) {
        $tableBody = '<tr><td colspan="5" style="text-align:center;padding:32px;color:#94a3b8;">No users found matching your search.</td></tr>';
    } else {
        foreach ($users as $user_row) {
            $level_state = getUserLevelState(['id' => (int) $user_row['id'], 'level' => (string) ($user_row['level'] ?? 'beginner')], $db);
            $levelClass = ($level_state['level'] ?? 'beginner') === 'expert' ? 'is-active' : (($level_state['level'] ?? 'beginner') === 'pro' ? 'is-pending' : 'is-suspended');
            $tableBody .= '<tr>';
            $tableBody .= '<td data-label="User"><strong>' . htmlspecialchars((string) ($user_row['full_name'] ?: $user_row['username']), ENT_QUOTES, 'UTF-8') . '</strong><br><span class="muted">@' . htmlspecialchars((string) $user_row['username'], ENT_QUOTES, 'UTF-8') . '</span><br><span class="muted">' . htmlspecialchars((string) $user_row['email'], ENT_QUOTES, 'UTF-8') . '</span></td>';
            $tableBody .= '<td data-label="Level"><span class="dashboard-pill ' . $levelClass . '">' . htmlspecialchars((string) ($level_state['display_level'] ?? 'Beginner'), ENT_QUOTES, 'UTF-8') . '</span><br><span class="muted">' . number_format((float) ($level_state['accuracy'] ?? 0), 1) . '% accuracy</span><br><span class="muted">' . number_format((int) ($user_row['approved_reviews_count'] ?? 0)) . ' approved reviews</span></td>';
            $tableBody .= '<td data-label="Balances"><strong>' . number_format((float) ($user_row['available_balance'] ?? 0), 2) . ' available</strong><br><span class="muted">' . number_format((float) ($user_row['locked_balance'] ?? 0), 2) . ' locked</span><br><span class="muted">' . number_format((int) ($user_row['valid_referrals'] ?? 0)) . ' referrals</span></td>';
            $tableBody .= '<td data-label="Signals">';
            if ((int) ($user_row['login_attempts'] ?? 0) >= ANTI_FARM_MAX_LOGIN_ATTEMPTS) {
                $tableBody .= '<span class="dashboard-pill is-suspended">High login attempts</span><br>';
            }
            if (!empty($user_row['signup_ip']) && !empty($user_row['last_ip']) && $user_row['signup_ip'] === $user_row['last_ip']) {
                $tableBody .= '<span class="dashboard-pill is-pending">Stable IP fingerprint</span><br>';
            }
            $tableBody .= '<span class="muted">Status: ' . htmlspecialchars((string) ($user_row['status'] ?? 'active'), ENT_QUOTES, 'UTF-8') . '</span>';
            $tableBody .= '</td>';
            $tableBody .= '<td data-label="Freeze">';
            $tableBody .= '<form method="POST" action="" class="inline-form">';
            $tableBody .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
            $tableBody .= '<input type="hidden" name="action_type" value="toggle_freeze">';
            $tableBody .= '<input type="hidden" name="user_id" value="' . (int) $user_row['id'] . '">';
            $tableBody .= '<input type="hidden" name="reward_frozen" value="' . (!empty($user_row['reward_frozen']) ? '0' : '1') . '">';
            $tableBody .= '<button type="submit" class="btn ' . (!empty($user_row['reward_frozen']) ? 'btn-secondary' : 'btn-danger') . '">' . (!empty($user_row['reward_frozen']) ? 'Unfreeze rewards' : 'Freeze rewards') . '</button>';
            $tableBody .= '</form>';
            $tableBody .= '</td>';
            $tableBody .= '</tr>';
        }
    }

    $paginationHtml = renderPagination($page, $total_pages, ADMIN_BASE_URL . '/reward-users.php', $user_search !== '' ? ['q' => $user_search] : []);

    echo json_encode(paginationJsonResponse($tableBody, $paginationHtml, $page));
    exit();
}

paginationRenderStyles();
?>
<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-users"></i></div>
            <div class="dashboard-header-text">
                <h1>Reward Users</h1>
                <p>Manage user balances, signals, and reward freezes</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-database"></i> <?php echo number_format($total_users); ?> Users
        </div>
    </div>

    <!-- ====== TOAST ====== -->
    <?php if ($message !== ''): ?>
    <div data-toast="<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" data-toast-message="<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>" style="display:none;"></div>
    <?php endif; ?>

    <!-- ====== USERS TABLE ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-list"></i> Users <span class="divider-sub">Browse and manage reward users</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-user"></i> Users</span>
                <h3>Reward User Management</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">View balances, review signals, and toggle reward freezes for users.</p>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="dashboard-filter-bar">
            <form method="GET" action="" class="dashboard-filter-form" id="filterForm">
                <input type="text" name="q" placeholder="Search by name, username, or email" value="<?php echo htmlspecialchars($user_search, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($user_search !== ''): ?>
                    <a href="<?php echo ADMIN_BASE_URL; ?>/reward-users.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Users Table -->
        <div class="dashboard-table-wrap">
            <table class="dashboard-table" id="usersTable">
                <thead>
                <tr>
                    <th>User</th>
                    <th>Level</th>
                    <th>Balances</th>
                    <th>Signals</th>
                    <th>Freeze</th>
                </tr>
                </thead>
                <tbody id="tableBody">
                <?php if (empty($users)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:32px;color:#94a3b8;">No users found matching your search.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $user_row): ?>
                        <?php $level_state = getUserLevelState(['id' => (int) $user_row['id'], 'level' => (string) ($user_row['level'] ?? 'beginner')], $db); ?>
                        <tr>
                            <td data-label="User">
                                <strong><?php echo htmlspecialchars((string) ($user_row['full_name'] ?: $user_row['username']), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <span class="muted">@<?php echo htmlspecialchars((string) $user_row['username'], ENT_QUOTES, 'UTF-8'); ?></span><br>
                                <span class="muted"><?php echo htmlspecialchars((string) $user_row['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td data-label="Level">
                                <span class="dashboard-pill <?php echo ($level_state['level'] ?? 'beginner') === 'expert' ? 'is-active' : (($level_state['level'] ?? 'beginner') === 'pro' ? 'is-pending' : 'is-suspended'); ?>">
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
                                    <span class="dashboard-pill is-suspended">High login attempts</span><br>
                                <?php endif; ?>
                                <?php if (!empty($user_row['signup_ip']) && !empty($user_row['last_ip']) && $user_row['signup_ip'] === $user_row['last_ip']): ?>
                                    <span class="dashboard-pill is-pending">Stable IP fingerprint</span><br>
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
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div id="pagination">
            <?php echo renderPagination($page, $total_pages, ADMIN_BASE_URL . '/reward-users.php', $user_search !== '' ? ['q' => $user_search] : []); ?>
        </div>
    </div>

</div><!-- /.dashboard-container -->

<?php
paginationRenderJS([
    'tableBodyId' => 'tableBody',
    'paginationId' => 'pagination',
    'fetchUrl' => ADMIN_BASE_URL . '/reward-users.php',
    'filterFormId' => 'filterForm',
    'extraParams' => ['q'],
    'pageParam' => 'page',
    'loadingText' => 'Loading reward users',
]);

?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
