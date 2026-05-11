<?php
$page_title = 'Developer Verification';
$activePage = 'developers';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();
$message = '';
$message_type = 'success';
$status_filter = trim((string) ($_GET['status'] ?? 'pending'));
$valid_filters = ['pending', 'approved', 'rejected', 'change_requested', 'all'];
if (!in_array($status_filter, $valid_filters, true)) {
    $status_filter = 'pending';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));

    $verification_id = (int) ($_POST['verification_id'] ?? 0);
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($verification_id > 0 && $user_id > 0 && in_array($action, ['approve', 'reject', 'change_requested'], true)) {
        $new_status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'change_requested');

        $stmt = $db->prepare("UPDATE developer_verification SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $verification_id]);

        if (tableHasColumn('users', 'has_verified_badge')) {
            $badge_stmt = $db->prepare("UPDATE users SET has_verified_badge = ? WHERE id = ?");
            $badge_stmt->execute([$new_status === 'approved' ? 1 : 0, $user_id]);
        }

        logAdminActivity(
            (int) $current_admin['id'],
            'developer_verification_' . $new_status,
            'developer_verification',
            (string) $verification_id,
            json_encode(['user_id' => $user_id], JSON_UNESCAPED_UNICODE)
        );

        $name_stmt = $db->prepare("SELECT full_name, username FROM users WHERE id = ? LIMIT 1");
        $name_stmt->execute([$user_id]);
        $name_row = $name_stmt->fetch() ?: [];
        $developer_name = trim((string) ($name_row['full_name'] ?? ''));
        if ($developer_name === '') {
            $developer_name = trim((string) ($name_row['username'] ?? 'Developer'));
        }

        $template_key = 'developer.verification.' . $new_status;
        createTemplatedNotification($template_key, 'developer', $user_id, [
            'developer_name' => $developer_name !== '' ? $developer_name : 'Developer',
        ], [
            'actor_type' => 'admin',
            'actor_id' => (int) $current_admin['id'],
            'meta' => ['verification_id' => $verification_id, 'status' => $new_status],
        ], $db);

        $message = 'Developer verification status updated.';
    } else {
        $message = 'Invalid developer verification action.';
        $message_type = 'error';
    }
}

$where = '';
$params = [];
if ($status_filter !== 'all') {
    $where = "WHERE dv.status = ?";
    $params[] = $status_filter;
}

$stmt = $db->prepare("
    SELECT
        dv.id, dv.user_id, dv.status, dv.verification_post_url, dv.verification_url, dv.verification_code, dv.updated_at, dv.created_at,
        u.username, u.email, u.full_name
    FROM developer_verification dv
    LEFT JOIN users u ON u.id = dv.user_id
    {$where}
    ORDER BY dv.updated_at DESC, dv.id DESC
    LIMIT 200
");
$stmt->execute($params);
$rows = $stmt->fetchAll();
?>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="panel">
    <form method="GET" action="" class="inline-form">
        <select name="status">
            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            <option value="change_requested" <?php echo $status_filter === 'change_requested' ? 'selected' : ''; ?>>Change Requested</option>
            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>
</div>

<div class="panel">
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Developer</th>
                <th>Status</th>
                <th>Post Proof</th>
                <th>Website / Meta</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <?php
                $status = (string) ($row['status'] ?? 'pending');
                $status_class = 'status-pending';
                if ($status === 'approved') {
                    $status_class = 'status-approved';
                } elseif ($status === 'rejected') {
                    $status_class = 'status-rejected';
                } elseif ($status === 'change_requested') {
                    $status_class = 'status-disabled';
                }
                ?>
                <tr>
                    <td data-label="ID"><?php echo (int) $row['id']; ?></td>
                    <td data-label="Developer">
                        <strong><?php echo htmlspecialchars((string) ($row['full_name'] ?? $row['username'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <span class="muted"><?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td data-label="Status"><span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="Post Proof">
                        <?php if (!empty($row['verification_post_url'])): ?>
                            <a href="<?php echo htmlspecialchars((string) $row['verification_post_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open Post</a>
                        <?php else: ?>
                            <span class="muted">Not provided</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Website / Meta">
                        <?php if (!empty($row['verification_url'])): ?>
                            <a href="<?php echo htmlspecialchars((string) $row['verification_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open Site</a><br>
                        <?php endif; ?>
                        <?php if (!empty($row['verification_code'])): ?>
                            <code><?php echo htmlspecialchars(substr((string) $row['verification_code'], 0, 140), ENT_QUOTES, 'UTF-8'); ?></code>
                        <?php else: ?>
                            <span class="muted">No meta code</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Submitted"><?php echo htmlspecialchars((string) ($row['updated_at'] ?? $row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Actions">
                        <form method="POST" action="" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="verification_id" value="<?php echo (int) $row['id']; ?>">
                            <input type="hidden" name="user_id" value="<?php echo (int) $row['user_id']; ?>">
                            <button type="submit" class="btn btn-primary" name="action" value="approve">Approve</button>
                            <button type="submit" class="btn btn-danger" name="action" value="reject">Reject</button>
                            <button type="submit" class="btn btn-secondary" name="action" value="change_requested">Request Change</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
