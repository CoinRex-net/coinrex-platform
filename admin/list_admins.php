<?php
$page_title = 'Admin Accounts';
$activePage = 'list-admins';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();
$admins = $db->query("SELECT a.id, a.username, a.name, a.email, a.status, a.last_login_at, r.name AS role_name FROM admins a LEFT JOIN roles r ON r.id = a.role_id ORDER BY a.id ASC")->fetchAll();

?>

<div class="panel">
    <div class="admin-toolbar">
        <h3 style="margin:0;">Admin Accounts</h3>
        <a class="btn btn-primary" href="<?php echo ADMIN_BASE_URL; ?>/create_admin.php">Create Admin</a>
    </div>
    <div class="table-wrap" style="margin-top:12px;">
        <table class="responsive-table">
            <thead><tr><th>ID</th><th>Admin</th><th>Role</th><th>Status</th><th>Last Login</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <tr>
                    <td data-label="ID"><?php echo (int) $a['id']; ?></td>
                    <td data-label="Admin"><strong><?php echo htmlspecialchars((string) $a['username'], ENT_QUOTES, 'UTF-8'); ?></strong><br><span class="muted"><?php echo htmlspecialchars((string) $a['email'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="Role"><?php echo htmlspecialchars((string) ($a['role_name'] ?? 'unassigned'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Status"><span class="status-pill <?php echo (string) $a['status'] === 'active' ? 'status-approved' : 'status-rejected'; ?>"><?php echo htmlspecialchars((string) $a['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="Last Login"><?php echo htmlspecialchars((string) ($a['last_login_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Actions" class="inline-form">
                        <a class="btn btn-secondary" href="<?php echo ADMIN_BASE_URL; ?>/edit_admin.php?id=<?php echo (int) $a['id']; ?>">Edit</a>
                        <a class="btn btn-danger" href="<?php echo ADMIN_BASE_URL; ?>/delete_admin.php?id=<?php echo (int) $a['id']; ?>">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
