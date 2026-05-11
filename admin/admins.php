<?php
$page_title = 'Admin Management';
$activePage = 'admins';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();
$message = '';
$message_type = 'success';

if (!(tableExists('roles') && tableExists('permissions') && tableExists('role_permissions') && tableHasColumn('admins', 'role_id'))) {
    ?>
    <div class="message message-error">RBAC migration is not applied yet. Please run <code>database/migrations/2026_05_02_admin_rbac.sql</code> first.</div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$roles = $db->query("SELECT id, name, description, hierarchy_level FROM roles ORDER BY hierarchy_level ASC, name ASC")->fetchAll();
$role_map = [];
foreach ($roles as $role_row) {
    $role_map[(int) $role_row['id']] = $role_row;
}

$current_role_level = (int) ($current_admin['hierarchy_level'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'create_admin') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = adminNormalizeEmail((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $role_id = (int) ($_POST['role_id'] ?? 0);

            if ($username === '' || $name === '' || $email === '' || $password === '' || $role_id <= 0) {
                throw new RuntimeException('All fields are required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Invalid email format.');
            }
            if (strlen($password) < 10) {
                throw new RuntimeException('Password must be at least 10 characters.');
            }
            $target_role = $role_map[$role_id] ?? null;
            if (!$target_role) {
                throw new RuntimeException('Invalid role selected.');
            }
            if ((int) $target_role['hierarchy_level'] < $current_role_level) {
                throw new RuntimeException('Cannot assign a role higher than your own.');
            }

            $exists_stmt = $db->prepare("SELECT id FROM admins WHERE email = ? OR username = ? LIMIT 1");
            $exists_stmt->execute([$email, $username]);
            if ($exists_stmt->fetch()) {
                throw new RuntimeException('Admin with same email or username already exists.');
            }

            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $insert = $db->prepare("INSERT INTO admins (email, username, name, password_hash, role_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())");
            $insert->execute([$email, $username, $name, $hash, $role_id]);

            logAdminActivity((int) $current_admin['id'], 'admin_create', 'admin', (string) $db->lastInsertId(), json_encode(['email' => $email, 'role_id' => $role_id], JSON_UNESCAPED_UNICODE));
            $message = 'Admin created successfully.';
        } elseif ($action === 'update_admin') {
            $admin_id = (int) ($_POST['admin_id'] ?? 0);
            $role_id = (int) ($_POST['role_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'active');

            if ($admin_id <= 0 || $role_id <= 0 || !in_array($status, ['active', 'suspended'], true)) {
                throw new RuntimeException('Invalid update payload.');
            }

            $target = getAdminById($admin_id);
            if (!$target) {
                throw new RuntimeException('Admin not found.');
            }

            $target_role = $role_map[$role_id] ?? null;
            if (!$target_role) {
                throw new RuntimeException('Invalid role selected.');
            }
            if ((int) $target_role['hierarchy_level'] < $current_role_level) {
                throw new RuntimeException('Cannot assign a role higher than your own.');
            }

            if ($admin_id === (int) $current_admin['id']) {
                if ($status !== 'active') {
                    throw new RuntimeException('You cannot suspend your own account.');
                }
                if (strtolower((string) ($target['role_name'] ?? '')) === 'super_admin' && strtolower((string) ($target_role['name'] ?? '')) !== 'super_admin') {
                    throw new RuntimeException('You cannot remove your own super_admin role.');
                }
            }

            if (strtolower((string) ($target_role['name'] ?? '')) !== 'super_admin') {
                $count_stmt = $db->query("SELECT COUNT(*) FROM admins a INNER JOIN roles r ON r.id = a.role_id WHERE a.status = 'active' AND r.name = 'super_admin'");
                $super_count = (int) $count_stmt->fetchColumn();
                if (strtolower((string) ($target['role_name'] ?? '')) === 'super_admin' && $super_count <= 1) {
                    throw new RuntimeException('At least one super_admin must always remain.');
                }
            }

            if ($status !== 'active' && strtolower((string) ($target['role_name'] ?? '')) === 'super_admin') {
                $count_stmt = $db->query("SELECT COUNT(*) FROM admins a INNER JOIN roles r ON r.id = a.role_id WHERE a.status = 'active' AND r.name = 'super_admin'");
                $super_count = (int) $count_stmt->fetchColumn();
                if ($super_count <= 1) {
                    throw new RuntimeException('Cannot suspend the last active super_admin.');
                }
            }

            $upd = $db->prepare("UPDATE admins SET role_id = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$role_id, $status, $admin_id]);
            logAdminActivity((int) $current_admin['id'], 'admin_update', 'admin', (string) $admin_id, json_encode(['role_id' => $role_id, 'status' => $status], JSON_UNESCAPED_UNICODE));
            $message = 'Admin updated successfully.';
        } elseif ($action === 'reset_admin_password') {
            $admin_id = (int) ($_POST['admin_id'] ?? 0);
            $new_password = (string) ($_POST['new_password'] ?? '');

            if (strtolower((string) ($current_admin['role_name'] ?? '')) !== 'super_admin') {
                throw new RuntimeException('Only super_admin can reset sub-admin passwords.');
            }
            if ($admin_id <= 0 || $new_password === '') {
                throw new RuntimeException('Admin and new password are required.');
            }
            if (strlen($new_password) < 10) {
                throw new RuntimeException('New password must be at least 10 characters.');
            }

            $target = getAdminById($admin_id);
            if (!$target) {
                throw new RuntimeException('Target admin not found.');
            }

            $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $pwd = $db->prepare("UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $pwd->execute([$new_hash, $admin_id]);

            logAdminActivity((int) $current_admin['id'], 'admin_password_reset', 'admin', (string) $admin_id, json_encode(['target_email' => (string) ($target['email'] ?? '')], JSON_UNESCAPED_UNICODE));
            $message = 'Password reset successfully for selected admin.';
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

$admins = $db->query("SELECT a.id, a.username, a.name, a.email, a.status, a.last_login_at, a.created_at, a.role_id, r.name AS role_name, r.hierarchy_level FROM admins a LEFT JOIN roles r ON r.id = a.role_id ORDER BY a.id ASC")->fetchAll();
?>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<div class="panel">
    <h3>Create Admin</h3>
    <form method="POST" class="admin-task-builder-grid">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="create_admin">
        <input type="text" name="username" placeholder="Username" required>
        <input type="text" name="name" placeholder="Display Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password (min 10 chars)" required>
        <select name="role_id" required>
            <option value="">Select Role</option>
            <?php foreach ($roles as $role): ?>
                <?php if ((int) $role['hierarchy_level'] >= $current_role_level): ?>
                    <option value="<?php echo (int) $role['id']; ?>"><?php echo htmlspecialchars((string) $role['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit">Create Admin</button>
    </form>
</div>

<div class="panel">
    <h3>Admin Accounts</h3>
    <p class="muted" style="margin-top:-4px;">For security, existing passwords are never viewable. Super Admin can set a new password for sub-admins.</p>
    <div class="table-wrap">
        <table class="responsive-table">
            <thead><tr><th>ID</th><th>Admin</th><th>Role</th><th>Status</th><th>Last Login</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
                <tr>
                    <td data-label="ID"><?php echo (int) $a['id']; ?></td>
                    <td data-label="Admin"><strong><?php echo htmlspecialchars((string) $a['username'], ENT_QUOTES, 'UTF-8'); ?></strong><br><span class="muted"><?php echo htmlspecialchars((string) $a['email'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="Role"><?php echo htmlspecialchars((string) ($a['role_name'] ?? 'unassigned'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Status"><span class="status-pill <?php echo (string) $a['status'] === 'active' ? 'status-approved' : 'status-rejected'; ?>"><?php echo htmlspecialchars((string) $a['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="Last Login"><?php echo htmlspecialchars((string) ($a['last_login_at'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Action">
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="update_admin">
                            <input type="hidden" name="admin_id" value="<?php echo (int) $a['id']; ?>">
                            <select name="role_id">
                                <?php foreach ($roles as $role): ?>
                                    <?php if ((int) $role['hierarchy_level'] >= $current_role_level): ?>
                                        <option value="<?php echo (int) $role['id']; ?>" <?php echo (int) $a['role_id'] === (int) $role['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $role['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <select name="status">
                                <option value="active" <?php echo (string) $a['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="suspended" <?php echo (string) $a['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                            </select>
                            <button type="submit" class="btn btn-secondary">Update</button>
                        </form>
                        <?php if (strtolower((string) ($current_admin['role_name'] ?? '')) === 'super_admin' && (int) $a['id'] !== (int) $current_admin['id']): ?>
                            <form method="POST" class="inline-form" style="margin-top:8px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="reset_admin_password">
                                <input type="hidden" name="admin_id" value="<?php echo (int) $a['id']; ?>">
                                <input type="password" name="new_password" placeholder="Set new password" minlength="10" required>
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
