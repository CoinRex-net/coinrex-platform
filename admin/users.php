<?php
$page_title = 'Users';
$activePage = 'users';
$is_ajax_request = !empty($_GET['ajax']);
if ($is_ajax_request) {
    require_once __DIR__ . '/includes/config.php';
    requireAdminAuth();
    requireAdminPageAccess($activePage);
} else {
    require_once __DIR__ . '/includes/header.php';
}
require_once __DIR__ . '/includes/pagination.php';

$db = getDBConnection();
$message = '';
$message_type = 'success';
$search = trim((string) ($_GET['q'] ?? ''));
$status_filter = trim((string) ($_GET['status'] ?? 'active'));
$current_admin = getCurrentAdmin();

if (!in_array($status_filter, ['active', 'suspended', 'all'], true)) {
    $status_filter = 'active';
}

function adminUserListLevelState(array $user): array {
    $level = normalizeUserLevel($user['level'] ?? 'beginner');
    $policy = getLevelPolicy($level);
    $total_reviews = (int) ($user['total_reviews'] ?? 0);
    $approved_reviews = (int) ($user['approved_reviews_count'] ?? 0);
    $accuracy = $total_reviews > 0 ? round(($approved_reviews / $total_reviews) * 100, 1) : 0.0;

    return [
        'level' => $level,
        'display_level' => levelDisplayName($level),
        'trust_weight' => (float) ($policy['trust_weight'] ?? 1),
        'accuracy' => $accuracy,
    ];
}

function adminUserListLearnHubCompletedDays(array $user, PDO $db): int {
    $total_days = defined('TASKHUB_TOTAL_DAYS') ? (int) TASKHUB_TOTAL_DAYS : 10;
    $current_day = max(1, (int) ($user['current_day'] ?? 1));
    $completed_days = max(0, min($total_days, $current_day - 1));

    if (function_exists('taskHubMissionCompleted') && taskHubMissionCompleted((int) ($user['id'] ?? 0), $db)) {
        return $total_days;
    }

    return $completed_days;
}

function adminUserListBuildBoosthubEvidenceUrl(array $user): string {
    $params = ['user_id=' . (int) ($user['id'] ?? 0)];
    $username = trim((string) ($user['username'] ?? ''));
    if ($username !== '') {
        $params[] = 'username=' . rawurlencode($username);
    }

    return ADMIN_BASE_URL . '/boosthub-evidence.php?' . implode('&', $params);
}

function adminUserListDecorate(array $user, PDO $db): array {
    $user['learnhub_completed_days'] = adminUserListLearnHubCompletedDays($user, $db);
    $user['boosthub_evidence_url'] = adminUserListBuildBoosthubEvidenceUrl($user);
    return $user;
}

$perPage = 20;
$page = paginationGetPage('page', 1);
$offset = ($page - 1) * $perPage;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));

    $user_id = (int) ($_POST['user_id'] ?? 0);
    $new_status = (string) ($_POST['new_status'] ?? '');

    if ($user_id > 0 && in_array($new_status, ['active', 'suspended'], true)) {
        $update = $db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
        $update->execute([$new_status, $user_id]);

        logAdminActivity(
            (int) $current_admin['id'],
            'user_status_update',
            'user',
            (string) $user_id,
            json_encode(['new_status' => $new_status], JSON_UNESCAPED_UNICODE)
        );

        $message = 'User status updated successfully.';
        $message_type = 'success';
    } else {
        $message = 'Invalid user update payload.';
        $message_type = 'error';
    }
}

// Count query
$count_params = [];
$count_sql = "SELECT COUNT(*) FROM users";
if ($status_filter !== 'all') {
    $count_sql .= " WHERE status = ? ";
    $count_params[] = $status_filter;
}
if ($search !== '') {
    $count_sql .= ($status_filter !== 'all') ? " AND " : " WHERE ";
    $count_sql .= " (full_name LIKE ? OR username LIKE ? OR email LIKE ?) ";
    $needle = '%' . $search . '%';
    $count_params[] = $needle;
    $count_params[] = $needle;
    $count_params[] = $needle;
}
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($count_params);
$total_users = (int) $count_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_users / $perPage));

$summary_sql = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE
            WHEN LOWER(TRIM(COALESCE(level, 'beginner'))) IN ('pro', 'premium') THEN 1
            ELSE 0
        END) AS pro,
        SUM(CASE
            WHEN LOWER(TRIM(COALESCE(level, 'beginner'))) = 'expert' THEN 1
            ELSE 0
        END) AS expert
    FROM users
";
$summary_params = [];
if ($status_filter !== 'all') {
    $summary_sql .= " WHERE status = ? ";
    $summary_params[] = $status_filter;
}
if ($search !== '') {
    $summary_sql .= ($status_filter !== 'all') ? " AND " : " WHERE ";
    $summary_sql .= " (full_name LIKE ? OR username LIKE ? OR email LIKE ?) ";
    $needle = '%' . $search . '%';
    $summary_params[] = $needle;
    $summary_params[] = $needle;
    $summary_params[] = $needle;
}
$summary_stmt = $db->prepare($summary_sql);
$summary_stmt->execute($summary_params);
$summary_row = $summary_stmt->fetch() ?: [];

// Data query
$params = [];
$query_sql = "
    SELECT
        u.id,
        u.full_name,
        u.username,
        u.email,
        u.status,
        u.role,
        u.level,
        u.total_reviews,
        u.approved_reviews_count,
        u.valid_referrals,
        u.created_at,
        u.current_day,
        u.last_login,
        u.last_active,
        u.reward_frozen,
        u.security_flagged,
        u.security_flag_reason,
        u.security_suspended,
        u.login_attempts,
        u.taskhub_blocked_until,
        u.boosthub_blocked_until,
        u.review_blocked_until,
        COALESCE(boosthub_stats.completed_total, 0) AS boosthub_completed_tasks
    FROM users u
    LEFT JOIN (
        SELECT
            utl.user_id,
            COUNT(*) AS completed_total
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE mt.task_group = 'boosthub'
          AND utl.status = 'completed'
        GROUP BY utl.user_id
    ) boosthub_stats ON boosthub_stats.user_id = u.id
";
if ($status_filter !== 'all') {
    $query_sql .= " WHERE u.status = ? ";
    $params[] = $status_filter;
}

if ($search !== '') {
    $query_sql .= ($status_filter !== 'all') ? " AND " : " WHERE ";
    $query_sql .= " (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?) ";
    $needle = '%' . $search . '%';
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
}
$query_sql .= " ORDER BY u.id DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($query_sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
$users = array_map(static function (array $user) use ($db): array {
    return adminUserListDecorate($user, $db);
}, $users);

$user_summary = [
    'total' => (int) ($summary_row['total'] ?? $total_users),
    'pro' => (int) ($summary_row['pro'] ?? 0),
    'expert' => (int) ($summary_row['expert'] ?? 0),
];
$user_summary['beginner'] = max(0, $user_summary['total'] - $user_summary['pro'] - $user_summary['expert']);

// AJAX mode
if ($is_ajax_request) {
    header('Content-Type: application/json');

    $tableBody = '';
    if (empty($users)) {
        $tableBody = '<tr><td colspan="6" style="text-align:center;padding:32px;color:#94a3b8;">No users found matching your filters.</td></tr>';
    } else {
        foreach ($users as $user) {
            $status = (string) ($user['status'] ?? 'inactive');
            $status_class = 'is-pending';
            if ($status === 'active') {
                $status_class = 'is-active';
            } elseif (in_array($status, ['suspended', 'disabled'], true)) {
                $status_class = 'is-suspended';
            }
            $target_status = $status === 'active' ? 'suspended' : 'active';
            $user_level_state = adminUserListLevelState($user);
            $display_level = $user_level_state['display_level'] ?? 'Beginner';
            $level_class = 'is-beginner';
            if (strtolower($display_level) === 'expert') {
                $level_class = 'is-expert';
            } elseif (in_array(strtolower($display_level), ['pro', 'premium'], true)) {
                $level_class = 'is-pro';
            }
            $tableBody .= '<tr>';
            $tableBody .= '<td data-label="ID">' . (int) $user['id'] . '</td>';
            $tableBody .= '<td data-label="Username">' . htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            $tableBody .= '<td data-label="Email">' . htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            $tableBody .= '<td data-label="Level">';
            $tableBody .= '<span class="dashboard-pill ' . $level_class . '">' . htmlspecialchars($display_level, ENT_QUOTES, 'UTF-8') . '</span>';
            $tableBody .= '<span class="dashboard-trust-badge">Trust <strong>' . htmlspecialchars((string) ($user_level_state['trust_weight'] ?? 1), ENT_QUOTES, 'UTF-8') . 'x</strong></span>';
            $tableBody .= '</td>';
            $tableBody .= '<td data-label="Status"><span class="dashboard-pill ' . $status_class . '">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span></td>';
            $tableBody .= '<td data-label="Action">';
            $tableBody .= '<form method="POST" action="" class="inline-form">';
            $tableBody .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
            $tableBody .= '<input type="hidden" name="user_id" value="' . (int) $user['id'] . '">';
            $tableBody .= '<input type="hidden" name="new_status" value="' . $target_status . '">';
            $tableBody .= '<button type="button" class="btn btn-secondary user-view-btn"';
            $tableBody .= ' data-user-id="' . (int) $user['id'] . '"';
            $tableBody .= ' data-full-name="' . htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-username="' . htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-email="' . htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-role="' . htmlspecialchars((string) ($user['role'] ?? 'user'), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-status="' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-level="' . htmlspecialchars($display_level, ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-trust="' . htmlspecialchars((string) ($user_level_state['trust_weight'] ?? 1), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-accuracy="' . htmlspecialchars(number_format((float) ($user_level_state['accuracy'] ?? 0), 1), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-total-reviews="' . (int) ($user['total_reviews'] ?? 0) . '"';
            $tableBody .= ' data-approved-reviews="' . (int) ($user['approved_reviews_count'] ?? 0) . '"';
            $tableBody .= ' data-valid-referrals="' . (int) ($user['valid_referrals'] ?? 0) . '"';
            $tableBody .= ' data-created-at="' . htmlspecialchars((string) ($user['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-last-login="' . htmlspecialchars((string) ($user['last_login'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-last-active="' . htmlspecialchars((string) ($user['last_active'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-learnhub-completed-days="' . (int) ($user['learnhub_completed_days'] ?? 0) . '"';
            $tableBody .= ' data-boosthub-completed-tasks="' . (int) ($user['boosthub_completed_tasks'] ?? 0) . '"';
            $tableBody .= ' data-boosthub-evidence-url="' . htmlspecialchars((string) ($user['boosthub_evidence_url'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-reward-frozen="' . (!empty($user['reward_frozen']) ? '1' : '0') . '"';
            $tableBody .= ' data-security-flagged="' . (!empty($user['security_flagged']) ? '1' : '0') . '"';
            $tableBody .= ' data-security-flag-reason="' . htmlspecialchars((string) ($user['security_flag_reason'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-security-suspended="' . (!empty($user['security_suspended']) ? '1' : '0') . '"';
            $tableBody .= ' data-login-attempts="' . (int) ($user['login_attempts'] ?? 0) . '"';
            $tableBody .= ' data-taskhub-blocked-until="' . htmlspecialchars((string) ($user['taskhub_blocked_until'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-boosthub-blocked-until="' . htmlspecialchars((string) ($user['boosthub_blocked_until'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= ' data-review-blocked-until="' . htmlspecialchars((string) ($user['review_blocked_until'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
            $tableBody .= '>View</button>';
            if ($target_status === 'suspended') {
                $escaped_username = htmlspecialchars(str_replace("'", "\\'", (string) ($user['username'] ?? '')), ENT_QUOTES, 'UTF-8');
                $ajax_csrf = htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8');
                $tableBody .= '<button type="button" class="btn btn-danger" onclick="openSuspendModal(' . (int) $user['id'] . ', \'' . $escaped_username . '\', \'' . $ajax_csrf . '\')">Suspend</button>';
            } else {

                $tableBody .= '<button type="submit" class="btn btn-primary">Activate</button>';
            }
            $tableBody .= '</form>';

            $tableBody .= '</td>';
            $tableBody .= '</tr>';
        }
    }

    $paginationHtml = renderPagination($page, $total_pages, ADMIN_BASE_URL . '/users.php', array_merge(
        $search !== '' ? ['q' => $search] : [],
        ['status' => $status_filter]
    ));

    echo json_encode(paginationJsonResponse($tableBody, $paginationHtml, $page));
    exit();
}

paginationRenderStyles();
?>

<!-- ════════════════════════════════════════════ -->
<!--  PAGE HEADER                                -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-header">
    <div class="dashboard-header-left">
        <div class="dashboard-header-icon"><i class="fas fa-users"></i></div>
        <div class="dashboard-header-text">
            <h1>User Operations</h1>
            <p>Monitor account status alongside the new level engine so trust weight and moderation lanes stay healthy.</p>
        </div>
    </div>
    <span class="dashboard-header-badge"><i class="fas fa-circle"></i> Community</span>
</div>

<?php if ($message !== ''): ?>
    <div class="dashboard-message <?php echo $message_type === 'error' ? 'is-error' : 'is-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<!-- ════════════════════════════════════════════ -->
<!--  USER SUMMARY METRICS                      -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-metric-grid">
    <div class="dashboard-metric-card">
        <div class="metric-top">
            <span class="metric-icon is-blue"><i class="fas fa-users"></i></span>
        </div>
        <strong class="metric-value"><?php echo number_format((int) $total_users); ?></strong>
        <span class="metric-label">Total Users</span>
    </div>
    <div class="dashboard-metric-card">
        <div class="metric-top">
            <span class="metric-icon is-gold"><i class="fas fa-seedling"></i></span>
        </div>
        <strong class="metric-value"><?php echo number_format((int) $user_summary['beginner']); ?></strong>
        <span class="metric-label">Beginners</span>
    </div>
    <div class="dashboard-metric-card">
        <div class="metric-top">
            <span class="metric-icon is-gold"><i class="fas fa-crown"></i></span>
        </div>
        <strong class="metric-value"><?php echo number_format((int) $user_summary['pro']); ?></strong>
        <span class="metric-label">Pros</span>
    </div>
    <div class="dashboard-metric-card">
        <div class="metric-top">
            <span class="metric-icon is-purple"><i class="fas fa-gem"></i></span>
        </div>
        <strong class="metric-value"><?php echo number_format((int) $user_summary['expert']); ?></strong>
        <span class="metric-label">Experts</span>
    </div>
</div>

<!-- ════════════════════════════════════════════ -->
<!--  SEARCH & FILTER                           -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-panel">
    <div class="dashboard-filter-bar">
        <div>
            <h3 style="margin:0 0 4px; color:#f1f5f9; font-size:15px; font-weight:700;">Search Users</h3>
            <p class="muted" style="margin:0; font-size:13px;">Find accounts by name, username, or email and review their current level posture.</p>
        </div>
        <form method="GET" action="" class="dashboard-filter-form" id="filterForm">
            <select name="status">
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
            </select>
            <input type="text" name="q" placeholder="Search users by name, username, email" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════ -->
<!--  USERS TABLE                               -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-table-wrap">
    <table class="dashboard-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Email</th>
            <th>Level</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody id="tableBody">
        <?php if (empty($users)): ?>
            <tr><td colspan="6" style="text-align:center;padding:32px;color:#94a3b8;">No users found matching your filters.</td></tr>
        <?php else: ?>
            <?php foreach ($users as $user): ?>
                <?php
                $status = (string) ($user['status'] ?? 'inactive');
                $status_class = 'is-pending';
                if ($status === 'active') {
                    $status_class = 'is-active';
                } elseif (in_array($status, ['suspended', 'disabled'], true)) {
                    $status_class = 'is-suspended';
                }
                $target_status = $status === 'active' ? 'suspended' : 'active';
                $user_level_state = adminUserListLevelState($user);
                $display_level = $user_level_state['display_level'] ?? 'Beginner';
                $level_class = 'is-beginner';
                if (strtolower($display_level) === 'expert') {
                    $level_class = 'is-expert';
                } elseif (in_array(strtolower($display_level), ['pro', 'premium'], true)) {
                    $level_class = 'is-pro';
                }
                ?>
                <tr>
                    <td data-label="ID"><?php echo (int) $user['id']; ?></td>
                    <td data-label="Username"><?php echo htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Email"><?php echo htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Level">
                        <span class="dashboard-pill <?php echo $level_class; ?>">
                            <?php echo htmlspecialchars($display_level, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <span class="dashboard-trust-badge">Trust <strong><?php echo htmlspecialchars((string) ($user_level_state['trust_weight'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>x</strong></span>
                    </td>
                    <td data-label="Status">
                        <span class="dashboard-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td data-label="Action">
                        <form method="POST" action="" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                            <input type="hidden" name="new_status" value="<?php echo $target_status; ?>">
                            <button
                                type="button"
                                class="btn btn-secondary user-view-btn"
                                data-user-id="<?php echo (int) $user['id']; ?>"
                                data-full-name="<?php echo htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-username="<?php echo htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-email="<?php echo htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-role="<?php echo htmlspecialchars((string) ($user['role'] ?? 'user'), ENT_QUOTES, 'UTF-8'); ?>"
                                data-status="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"
                                data-level="<?php echo htmlspecialchars($display_level, ENT_QUOTES, 'UTF-8'); ?>"
                                data-trust="<?php echo htmlspecialchars((string) ($user_level_state['trust_weight'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>"
                                data-accuracy="<?php echo htmlspecialchars(number_format((float) ($user_level_state['accuracy'] ?? 0), 1), ENT_QUOTES, 'UTF-8'); ?>"
                                data-total-reviews="<?php echo (int) ($user['total_reviews'] ?? 0); ?>"
                                data-approved-reviews="<?php echo (int) ($user['approved_reviews_count'] ?? 0); ?>"
                                data-valid-referrals="<?php echo (int) ($user['valid_referrals'] ?? 0); ?>"
                                data-created-at="<?php echo htmlspecialchars((string) ($user['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-last-login="<?php echo htmlspecialchars((string) ($user['last_login'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-last-active="<?php echo htmlspecialchars((string) ($user['last_active'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-learnhub-completed-days="<?php echo (int) ($user['learnhub_completed_days'] ?? 0); ?>"
                                data-boosthub-completed-tasks="<?php echo (int) ($user['boosthub_completed_tasks'] ?? 0); ?>"
                                data-boosthub-evidence-url="<?php echo htmlspecialchars((string) ($user['boosthub_evidence_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-reward-frozen="<?php echo !empty($user['reward_frozen']) ? '1' : '0'; ?>"
                                data-security-flagged="<?php echo !empty($user['security_flagged']) ? '1' : '0'; ?>"
                                data-security-flag-reason="<?php echo htmlspecialchars((string) ($user['security_flag_reason'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-security-suspended="<?php echo !empty($user['security_suspended']) ? '1' : '0'; ?>"
                                data-login-attempts="<?php echo (int) ($user['login_attempts'] ?? 0); ?>"
                                data-taskhub-blocked-until="<?php echo htmlspecialchars((string) ($user['taskhub_blocked_until'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-boosthub-blocked-until="<?php echo htmlspecialchars((string) ($user['boosthub_blocked_until'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-review-blocked-until="<?php echo htmlspecialchars((string) ($user['review_blocked_until'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            >View</button>
                            <?php if ($target_status === 'suspended'): ?>
                                <?php $suspend_csrf = htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>
                                <button type="button" class="btn btn-danger" onclick="openSuspendModal(<?php echo (int) $user['id']; ?>, '<?php echo htmlspecialchars(str_replace("'", "\\'", (string) ($user['username'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo $suspend_csrf; ?>')">
                                    Suspend
                                </button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-primary">
                                    Activate
                                </button>
                            <?php endif; ?>
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
    <?php echo renderPagination($page, $total_pages, ADMIN_BASE_URL . '/users.php', array_merge(
        $search !== '' ? ['q' => $search] : [],
        ['status' => $status_filter]
    )); ?>
</div>

<!-- ════════════════════════════════════════════ -->
<!--  MODAL: Suspend Confirmation               -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-modal" id="suspendModal" aria-hidden="true">
    <div class="dashboard-modal-card" style="width:min(440px,100%);">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker" style="color:#f87171;">⚠️ Confirm Action</span>
                <h3>Suspend User</h3>
            </div>
            <button type="button" class="dashboard-modal-close" id="suspendModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="dashboard-modal-body" style="text-align:center;">
            <div style="font-size:48px;margin-bottom:12px;">🚫</div>
            <p style="color:#cbd5e1;font-size:15px;margin:0 0 6px;">
                Are you sure you want to suspend this user?
            </p>
            <p style="color:#f87171;font-size:13px;font-weight:600;margin:0 0 16px;" id="suspendUserName"></p>
            <p style="color:#64748b;font-size:12px;margin:0 0 20px;">
                This will prevent the user from logging in or completing tasks. You can reactivate them later.
            </p>
            <input type="hidden" id="suspendUserId" value="0">
            <input type="hidden" id="suspendCsrfToken" value="">
            <input type="hidden" id="suspendNewStatus" value="suspended">
            <div style="display:flex;gap:10px;justify-content:center;">
                <button type="button" class="btn btn-secondary" id="suspendCancelBtn">Cancel</button>
                <button type="button" class="btn btn-danger" id="suspendConfirmBtn">
                    <i class="fas fa-ban"></i> Suspend User
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ════════════════════════════════════════════ -->
<!--  USER DETAILS MODAL (Premium)              -->
<!-- ════════════════════════════════════════════ -->
<div class="dashboard-modal" id="userDetailsModal" aria-hidden="true">

    <div class="dashboard-modal-card user-details-modal-card">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-user"></i> User Profile</span>
                <h3 id="userModalTitle">User Details</h3>
            </div>
            <button type="button" class="dashboard-modal-close" id="userModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="dashboard-modal-body">
            <div class="dashboard-modal-grid">
                <div class="dashboard-modal-card-inner">
                    <h4><i class="fas fa-id-card"></i> Identity</h4>
                    <div class="dashboard-modal-avatar-block" id="userAvatarBlock">
                        <div class="dashboard-modal-avatar" id="userModalAvatar">A</div>
                        <div class="dashboard-modal-avatar-meta">
                            <strong id="userModalAvatarName">—</strong>
                            <span id="userModalAvatarUsername">@—</span>
                        </div>
                    </div>
                    <div class="dashboard-detail-list" id="userIdentityBlock"></div>
                </div>
                <div class="dashboard-modal-card-inner">
                    <h4><i class="fas fa-shield"></i> Account State</h4>
                    <div class="dashboard-detail-list" id="userAccountBlock"></div>
                </div>
                <div class="dashboard-modal-card-inner">
                    <h4><i class="fas fa-bolt"></i> Hub Activity</h4>
                    <div class="dashboard-detail-list" id="userHubBlock"></div>
                </div>
                <div class="dashboard-modal-card-inner">
                    <h4><i class="fas fa-triangle-exclamation"></i> Security Flags</h4>
                    <div class="dashboard-detail-list" id="userSecurityBlock"></div>
                </div>
                <div class="dashboard-modal-card-inner dashboard-modal-card-inner-wide">
                    <h4><i class="fas fa-chart-bar"></i> Performance Snapshot</h4>
                    <div class="dashboard-detail-list" id="userPerformanceBlock"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.user-details-modal-card {
    width: min(920px, 100%);
}
.user-status-inline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: flex-end;
}
.user-modal-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(15, 23, 42, 0.55);
    color: #cbd5e1;
}
.user-modal-chip.is-danger {
    border-color: rgba(248, 113, 113, 0.35);
    background: rgba(127, 29, 29, 0.22);
    color: #fecaca;
}
.user-modal-chip.is-warning {
    border-color: rgba(250, 204, 21, 0.35);
    background: rgba(113, 63, 18, 0.2);
    color: #fde68a;
}
.user-modal-chip.is-info {
    border-color: rgba(96, 165, 250, 0.35);
    background: rgba(30, 64, 175, 0.18);
    color: #bfdbfe;
}
.user-modal-chip.is-success {
    border-color: rgba(74, 222, 128, 0.35);
    background: rgba(20, 83, 45, 0.22);
    color: #bbf7d0;
}
.user-modal-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #93c5fd;
    text-decoration: none;
    font-weight: 700;
}
.user-modal-link:hover {
    color: #dbeafe;
}
.dashboard-detail-list > div.user-detail-stack {
    display: block;
}
.dashboard-detail-list > div.user-detail-stack .detail-value {
    display: block;
    margin-top: 6px;
    line-height: 1.55;
}
@media (max-width: 768px) {
    .user-details-modal-card {
        width: 100%;
    }
    .dashboard-detail-list > div {
        gap: 8px;
        align-items: flex-start;
        flex-direction: column;
    }
    .user-status-inline {
        justify-content: flex-start;
    }
}
</style>

<script>
(function() {
    var modal = document.getElementById('userDetailsModal');
    var closeBtn = document.getElementById('userModalClose');
    if (!modal || !closeBtn) {
        return;
    }

    var title = document.getElementById('userModalTitle');
    var identity = document.getElementById('userIdentityBlock');
    var account = document.getElementById('userAccountBlock');
    var hub = document.getElementById('userHubBlock');
    var security = document.getElementById('userSecurityBlock');
    var performance = document.getElementById('userPerformanceBlock');
    var avatarEl = document.getElementById('userModalAvatar');
    var avatarName = document.getElementById('userModalAvatarName');
    var avatarUsername = document.getElementById('userModalAvatarUsername');

    function esc(value) {
        var d = document.createElement('div');
        d.innerText = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function formatDateTime(value) {
        if (!value) {
            return '-';
        }

        var normalized = String(value).replace(' ', 'T');
        var date = new Date(normalized);
        if (isNaN(date.getTime())) {
            return esc(value);
        }

        return esc(date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        }));
    }

    function formatRelative(value) {
        if (!value) {
            return '';
        }

        var normalized = String(value).replace(' ', 'T');
        var date = new Date(normalized);
        if (isNaN(date.getTime())) {
            return '';
        }

        var diffMs = Date.now() - date.getTime();
        var future = diffMs < 0;
        var diffSeconds = Math.round(Math.abs(diffMs) / 1000);
        var units = [
            ['day', 86400],
            ['hour', 3600],
            ['minute', 60]
        ];

        for (var i = 0; i < units.length; i++) {
            var unitName = units[i][0];
            var unitSeconds = units[i][1];
            if (diffSeconds >= unitSeconds) {
                var amount = Math.floor(diffSeconds / unitSeconds);
                return future
                    ? 'in ' + amount + ' ' + unitName + (amount === 1 ? '' : 's')
                    : amount + ' ' + unitName + (amount === 1 ? '' : 's') + ' ago';
            }
        }

        return future ? 'in a moment' : 'just now';
    }

    function formatDateWithRelative(value) {
        if (!value) {
            return '-';
        }

        var absolute = formatDateTime(value);
        var relative = formatRelative(value);
        return relative ? absolute + ' (' + esc(relative) + ')' : absolute;
    }

    function buildChip(label, variant) {
        return '<span class="user-modal-chip' + (variant ? ' ' + variant : '') + '">' + esc(label) + '</span>';
    }

    function openModal(btn) {
        title.textContent = 'User #' + (btn.dataset.userId || '') + ' Details';

        var fullName = btn.dataset.fullName || '';
        var username = btn.dataset.username || '';
        var initial = (fullName || username || 'U').charAt(0).toUpperCase();
        avatarEl.textContent = initial;
        avatarName.textContent = fullName || username || '-';
        avatarUsername.textContent = '@' + (username || '-');

        var learnhubCompletedDays = parseInt(btn.dataset.learnhubCompletedDays || '0', 10);
        var boosthubCompletedTasks = parseInt(btn.dataset.boosthubCompletedTasks || '0', 10);
        var loginAttempts = parseInt(btn.dataset.loginAttempts || '0', 10);
        var rewardFrozen = btn.dataset.rewardFrozen === '1';
        var securityFlagged = btn.dataset.securityFlagged === '1';
        var securitySuspended = btn.dataset.securitySuspended === '1';
        var taskhubBlockedUntil = btn.dataset.taskhubBlockedUntil || '';
        var boosthubBlockedUntil = btn.dataset.boosthubBlockedUntil || '';
        var reviewBlockedUntil = btn.dataset.reviewBlockedUntil || '';
        var securityReason = btn.dataset.securityFlagReason || '';
        var securityPills = [];

        if (securityFlagged) securityPills.push(buildChip('Flagged', 'is-warning'));
        if (rewardFrozen) securityPills.push(buildChip('Rewards Frozen', 'is-danger'));
        if (securitySuspended || (btn.dataset.status || '').toLowerCase() === 'suspended') securityPills.push(buildChip('Suspended', 'is-danger'));
        if (taskhubBlockedUntil) securityPills.push(buildChip('LearnHub Blocked', 'is-info'));
        if (boosthubBlockedUntil) securityPills.push(buildChip('BoostHub Blocked', 'is-info'));
        if (reviewBlockedUntil) securityPills.push(buildChip('Review Blocked', 'is-info'));
        if (loginAttempts >= 10) securityPills.push(buildChip('Suspicious Login Pattern', 'is-warning'));
        if (securityPills.length === 0) securityPills.push(buildChip('Clear', 'is-success'));

        identity.innerHTML =
            '<div><strong>Full Name</strong><span class="detail-value">' + esc(btn.dataset.fullName || '-') + '</span></div>' +
            '<div><strong>Username</strong><span class="detail-value">' + esc(btn.dataset.username || '-') + '</span></div>' +
            '<div><strong>Email</strong><span class="detail-value">' + esc(btn.dataset.email || '-') + '</span></div>';
        account.innerHTML =
            '<div><strong>Status</strong><span class="detail-value">' + esc(btn.dataset.status || '-') + '</span></div>' +
            '<div><strong>Role</strong><span class="detail-value">' + esc(btn.dataset.role || 'user') + '</span></div>' +
            '<div><strong>Joined</strong><span class="detail-value">' + formatDateWithRelative(btn.dataset.createdAt || '') + '</span></div>' +
            '<div><strong>Last Login</strong><span class="detail-value">' + formatDateWithRelative(btn.dataset.lastLogin || '') + '</span></div>' +
            '<div><strong>Last Active</strong><span class="detail-value">' + formatDateWithRelative(btn.dataset.lastActive || '') + '</span></div>';
        if (hub) {
            hub.innerHTML =
                '<div><strong>LearnHub Completed</strong><span class="detail-value">' + esc(String(learnhubCompletedDays)) + ' day' + (learnhubCompletedDays === 1 ? '' : 's') + '</span></div>' +
                '<div class="user-detail-stack"><strong>BoostHub Completed Tasks</strong><span class="detail-value"><a class="user-modal-link" href="' + esc(btn.dataset.boosthubEvidenceUrl || '#') + '"><i class="fas fa-arrow-up-right-from-square"></i> ' + esc(String(boosthubCompletedTasks)) + ' task' + (boosthubCompletedTasks === 1 ? '' : 's') + '</a></span></div>' +
                '<div><strong>Valid Referrals</strong><span class="detail-value">' + esc(btn.dataset.validReferrals || '0') + '</span></div>';
        }
        if (security) {
            security.innerHTML =
                '<div class="user-detail-stack"><strong>Flags & Status</strong><span class="detail-value user-status-inline">' + securityPills.join(' ') + '</span></div>' +
                '<div><strong>Login Attempts</strong><span class="detail-value">' + esc(String(loginAttempts)) + '</span></div>' +
                '<div><strong>Security Reason</strong><span class="detail-value">' + esc(securityReason || '-') + '</span></div>' +
                '<div><strong>LearnHub Block</strong><span class="detail-value">' + formatDateWithRelative(taskhubBlockedUntil) + '</span></div>' +
                '<div><strong>BoostHub Block</strong><span class="detail-value">' + formatDateWithRelative(boosthubBlockedUntil) + '</span></div>' +
                '<div><strong>Review Block</strong><span class="detail-value">' + formatDateWithRelative(reviewBlockedUntil) + '</span></div>';
        }
        performance.innerHTML =
            '<div><strong>Level</strong><span class="detail-value">' + esc(btn.dataset.level || '-') + '</span></div>' +
            '<div><strong>Trust Weight</strong><span class="detail-value">' + esc(btn.dataset.trust || '1') + 'x</span></div>' +
            '<div><strong>Accuracy</strong><span class="detail-value">' + esc(btn.dataset.accuracy || '0') + '%</span></div>' +
            '<div><strong>Approved Reviews</strong><span class="detail-value">' + esc(btn.dataset.approvedReviews || '0') + '</span></div>' +
            '<div><strong>Total Reviews</strong><span class="detail-value">' + esc(btn.dataset.totalReviews || '0') + '</span></div>' +
            '<div><strong>User ID</strong><span class="detail-value">#' + esc(btn.dataset.userId || '0') + '</span></div>';

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    // Use event delegation so dynamically-added buttons (e.g. after AJAX pagination) also work
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.user-view-btn');
        if (btn) {
            openModal(btn);
        }
    });

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    });
})();

// ── Suspend Confirmation Modal ──
(function() {
    var suspendModal = document.getElementById('suspendModal');
    if (!suspendModal) return;

    var closeBtn = document.getElementById('suspendModalClose');
    var cancelBtn = document.getElementById('suspendCancelBtn');
    var confirmBtn = document.getElementById('suspendConfirmBtn');

    window.openSuspendModal = function(userId, username, csrfToken) {
        document.getElementById('suspendUserId').value = userId;
        document.getElementById('suspendUserName').textContent = '@' + username;
        document.getElementById('suspendCsrfToken').value = csrfToken || '';
        suspendModal.classList.add('show');
        suspendModal.setAttribute('aria-hidden', 'false');
    };


    function closeSuspendModal() {
        suspendModal.classList.remove('show');
        suspendModal.setAttribute('aria-hidden', 'true');
    }

    function confirmSuspend() {
        var userId = document.getElementById('suspendUserId').value;
        if (!userId || userId === '0') return;

        // Build a hidden form and submit it
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        form.style.display = 'none';

        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = document.getElementById('suspendCsrfToken').value;
        form.appendChild(csrfInput);

        var idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'user_id';
        idInput.value = userId;
        form.appendChild(idInput);

        var statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'new_status';
        statusInput.value = 'suspended';
        form.appendChild(statusInput);

        document.body.appendChild(form);
        closeSuspendModal();
        form.submit();
    }

    if (closeBtn) closeBtn.addEventListener('click', closeSuspendModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeSuspendModal);
    if (confirmBtn) confirmBtn.addEventListener('click', confirmSuspend);

    suspendModal.addEventListener('click', function(e) {
        if (e.target === suspendModal) closeSuspendModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && suspendModal.classList.contains('show')) {
            closeSuspendModal();
        }
    });
})();
</script>


<?php
paginationRenderJS([
    'tableBodyId' => 'tableBody',
    'paginationId' => 'pagination',
    'fetchUrl' => ADMIN_BASE_URL . '/users.php',
    'filterFormId' => 'filterForm',
    'extraParams' => ['q', 'status'],
    'pageParam' => 'page',
    'loadingText' => 'Loading users',
]);
?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
