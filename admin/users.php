<?php
$page_title = 'Users';
$activePage = 'users';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$message = '';
$message_type = 'success';
$search = trim((string) ($_GET['q'] ?? ''));
$status_filter = trim((string) ($_GET['status'] ?? 'active'));
$current_admin = getCurrentAdmin();

if (!in_array($status_filter, ['active', 'suspended', 'all'], true)) {
    $status_filter = 'active';
}

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

$params = [];
$query_sql = "
    SELECT id, full_name, username, email, status, role, level, total_reviews, approved_reviews_count, valid_referrals, created_at
    FROM users
";
if ($status_filter !== 'all') {
    $query_sql .= " WHERE status = ? ";
    $params[] = $status_filter;
}

if ($search !== '') {
    $query_sql .= ($status_filter !== 'all') ? " AND " : " WHERE ";
    $query_sql .= " (full_name LIKE ? OR username LIKE ? OR email LIKE ?) ";
    $needle = '%' . $search . '%';
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
}
$query_sql .= " ORDER BY id DESC LIMIT 150";

$stmt = $db->prepare($query_sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$user_summary = [
    'total' => count($users),
    'beginner' => 0,
    'pro' => 0,
    'expert' => 0,
];

foreach ($users as $summary_user) {
    $summary_level = normalizeUserLevel($summary_user['level'] ?? 'beginner');
    if (!isset($user_summary[$summary_level])) {
        $summary_level = 'beginner';
    }
    $user_summary[$summary_level]++;
}
?>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="panel admin-note-card">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Community</span>
            <h2>User Operations</h2>
            <p class="muted">Monitor account status alongside the new level engine so trust weight and moderation lanes stay healthy.</p>
        </div>
    </div>
    <div class="admin-metric-grid">
        <div class="admin-metric-card">
            <span class="admin-metric-label">Loaded Users</span>
            <strong><?php echo number_format((int) $user_summary['total']); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Beginners</span>
            <strong><?php echo number_format((int) $user_summary['beginner']); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Pros</span>
            <strong><?php echo number_format((int) $user_summary['pro']); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Experts</span>
            <strong><?php echo number_format((int) $user_summary['expert']); ?></strong>
        </div>
    </div>
</div>

<div class="panel">
    <div class="admin-toolbar">
        <div>
            <h3 style="margin:0 0 6px;">Search Users</h3>
            <p class="muted" style="margin:0;">Find accounts by name, username, or email and review their current level posture.</p>
        </div>
        <form method="GET" action="" class="user-filter-grid">
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

<div class="panel">
    <div class="table-wrap">
        <table class="responsive-table">
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
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php
                $status = (string) ($user['status'] ?? 'inactive');
                $status_class = 'status-pending';
                if ($status === 'active') {
                    $status_class = 'status-active';
                } elseif (in_array($status, ['suspended', 'disabled'], true)) {
                    $status_class = 'status-disabled';
                }
                $target_status = $status === 'active' ? 'suspended' : 'active';
                $user_level_state = getUserLevelState(
                    [
                        'id' => (int) ($user['id'] ?? 0),
                        'level' => (string) ($user['level'] ?? 'beginner'),
                    ],
                    $db
                );
                ?>
                <tr>
                    <td data-label="ID"><?php echo (int) $user['id']; ?></td>
                    <td data-label="Username"><?php echo htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Email"><?php echo htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Level">
                        <span class="status-pill <?php echo ($user_level_state['level'] ?? 'beginner') === 'expert' ? 'status-approved' : (($user_level_state['level'] ?? 'beginner') === 'pro' ? 'status-pending' : 'status-disabled'); ?>">
                            <?php echo htmlspecialchars($user_level_state['display_level'] ?? 'Beginner', ENT_QUOTES, 'UTF-8'); ?>
                        </span><br>
                        <span class="muted">Trust <?php echo htmlspecialchars((string) ($user_level_state['trust_weight'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>x</span>
                    </td>
                    <td data-label="Status"><span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></td>
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
                                data-level="<?php echo htmlspecialchars((string) ($user_level_state['display_level'] ?? 'Beginner'), ENT_QUOTES, 'UTF-8'); ?>"
                                data-trust="<?php echo htmlspecialchars((string) ($user_level_state['trust_weight'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>"
                                data-accuracy="<?php echo htmlspecialchars(number_format((float) ($user_level_state['accuracy'] ?? 0), 1), ENT_QUOTES, 'UTF-8'); ?>"
                                data-total-reviews="<?php echo (int) ($user['total_reviews'] ?? 0); ?>"
                                data-approved-reviews="<?php echo (int) ($user['approved_reviews_count'] ?? 0); ?>"
                                data-valid-referrals="<?php echo (int) ($user['valid_referrals'] ?? 0); ?>"
                                data-created-at="<?php echo htmlspecialchars((string) ($user['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            >View</button>
                            <button type="submit" class="btn <?php echo $target_status === 'suspended' ? 'btn-danger' : 'btn-primary'; ?>">
                                <?php echo $target_status === 'suspended' ? 'Suspend' : 'Activate'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-modal" id="userDetailsModal" aria-hidden="true">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <div>
                <span class="admin-kicker">User Profile</span>
                <h3 id="userModalTitle">User Details</h3>
            </div>
            <button type="button" class="admin-modal-close" id="userModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="trust-modal-grid">
                <div class="trust-modal-card">
                    <h4>Identity</h4>
                    <div class="trust-detail-list" id="userIdentityBlock"></div>
                </div>
                <div class="trust-modal-card">
                    <h4>Account State</h4>
                    <div class="trust-detail-list" id="userAccountBlock"></div>
                </div>
                <div class="trust-modal-card trust-modal-card-wide">
                    <h4>Performance Snapshot</h4>
                    <div class="trust-detail-list" id="userPerformanceBlock"></div>
                </div>
            </div>
        </div>
    </div>
</div>

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
    var performance = document.getElementById('userPerformanceBlock');

    function esc(value) {
        var d = document.createElement('div');
        d.innerText = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function openModal(btn) {
        title.textContent = 'User #' + (btn.dataset.userId || '') + ' Details';
        identity.innerHTML =
            '<div><strong>Full Name:</strong> ' + esc(btn.dataset.fullName || '-') + '</div>' +
            '<div><strong>Username:</strong> ' + esc(btn.dataset.username || '-') + '</div>' +
            '<div><strong>Email:</strong> ' + esc(btn.dataset.email || '-') + '</div>';
        account.innerHTML =
            '<div><strong>Status:</strong> ' + esc(btn.dataset.status || '-') + '</div>' +
            '<div><strong>Role:</strong> ' + esc(btn.dataset.role || 'user') + '</div>' +
            '<div><strong>Joined:</strong> ' + esc(btn.dataset.createdAt || '-') + '</div>';
        performance.innerHTML =
            '<div><strong>Level:</strong> ' + esc(btn.dataset.level || '-') + '</div>' +
            '<div><strong>Trust Weight:</strong> ' + esc(btn.dataset.trust || '1') + 'x</div>' +
            '<div><strong>Accuracy:</strong> ' + esc(btn.dataset.accuracy || '0') + '%</div>' +
            '<div><strong>Approved Reviews:</strong> ' + esc(btn.dataset.approvedReviews || '0') + '</div>' +
            '<div><strong>Total Reviews:</strong> ' + esc(btn.dataset.totalReviews || '0') + '</div>' +
            '<div><strong>Valid Referrals:</strong> ' + esc(btn.dataset.validReferrals || '0') + '</div>';

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.user-view-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            openModal(btn);
        });
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
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
