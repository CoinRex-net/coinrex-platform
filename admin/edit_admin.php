<?php
$page_title = 'Edit Admin';
$activePage = 'edit-admin';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();
$admin_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$success = '';

$roles = $db->query("SELECT id, name, hierarchy_level FROM roles ORDER BY hierarchy_level ASC, name ASC")->fetchAll();
$current_role_level = (int) ($current_admin['hierarchy_level'] ?? 1);

$stmt = $db->prepare("SELECT a.*, r.name AS role_name FROM admins a LEFT JOIN roles r ON r.id = a.role_id WHERE a.id = ? LIMIT 1");
$stmt->execute([$admin_id]);
$target = $stmt->fetch();
if (!$target) { die('Admin not found'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $action = (string) ($_POST['action'] ?? 'update_admin');

    if ($action === 'reset_admin_password') {
        $new_password = (string) ($_POST['new_password'] ?? '');
        if (!RbacService::isSuperAdmin((int) $current_admin['id'])) {
            $error = 'Only super_admin can reset admin passwords.';
        } elseif ((int) $current_admin['id'] === $admin_id) {
            $error = 'Use account settings to change your own password.';
        } elseif (strlen($new_password) < 10) {
            $error = 'New password must be at least 10 characters.';
        } else {
            $hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $u = $db->prepare("UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $u->execute([$hash, $admin_id]);
            logAdminActivity((int) $current_admin['id'], 'admin_password_reset', 'admin', (string) $admin_id, json_encode(['target_email' => (string) ($target['email'] ?? '')], JSON_UNESCAPED_UNICODE));
            $success = 'Password reset successfully.';
        }
    } else {
        $role_id = (int) ($_POST['role_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'active');

        $roleStmt = $db->prepare("SELECT id, name, hierarchy_level FROM roles WHERE id = ? LIMIT 1");
        $roleStmt->execute([$role_id]);
        $role = $roleStmt->fetch();

        if (!$role || (int) $role['hierarchy_level'] < $current_role_level) {
            $error = 'Invalid role selection.';
        } elseif (!in_array($status, ['active', 'suspended'], true)) {
            $error = 'Invalid status.';
        } elseif ((int) $current_admin['id'] === $admin_id && $status !== 'active') {
            $error = 'You cannot suspend your own account.';
        } else {
            $upd = $db->prepare("UPDATE admins SET role_id = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$role_id, $status, $admin_id]);
            logAdminActivity((int) $current_admin['id'], 'admin_update', 'admin', (string) $admin_id, json_encode(['role_id' => $role_id, 'status' => $status], JSON_UNESCAPED_UNICODE));
            $success = 'Admin updated successfully.';
        }
    }
}
?>

<?php if ($error !== ''): ?><div class="message message-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if ($success !== ''): ?><div class="message message-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<div class="panel">
    <h3>Edit Admin: <?php echo htmlspecialchars((string) $target['username'], ENT_QUOTES, 'UTF-8'); ?></h3>
    <form method="POST" class="inline-form" style="flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?php echo (int) $admin_id; ?>">
        <select name="role_id" required>
            <?php foreach ($roles as $r): ?>
                <?php if ((int) $r['hierarchy_level'] >= $current_role_level): ?>
                    <option value="<?php echo (int) $r['id']; ?>" <?php echo (int) $target['role_id'] === (int) $r['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="active" <?php echo (string) $target['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="suspended" <?php echo (string) $target['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
        </select>
        <button class="btn btn-primary" type="submit">Save</button>
    </form>
</div>

<?php if (RbacService::isSuperAdmin((int) $current_admin['id']) && (int) $target['id'] !== (int) $current_admin['id']): ?>
<div class="panel">
    <h3>Reset Password</h3>
    <p class="muted">Set a new password for this sub-admin account.</p>
    <form method="POST" class="inline-form" style="flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?php echo (int) $admin_id; ?>">
        <input type="hidden" name="action" value="reset_admin_password">
        <input type="password" name="new_password" placeholder="New password (min 10 chars)" minlength="10" required>
        <button class="btn btn-primary" type="submit">Reset Password</button>
    </form>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
