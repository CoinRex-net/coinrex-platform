<?php
$page_title = 'Create Admin';
$activePage = 'create-admin';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();
$error = '';

$roles = $db->query("SELECT id, name, hierarchy_level FROM roles ORDER BY hierarchy_level ASC, name ASC")->fetchAll();
$current_role_level = (int) ($current_admin['hierarchy_level'] ?? 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = adminNormalizeEmail((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role_id = (int) ($_POST['role_id'] ?? 0);

    $roleStmt = $db->prepare("SELECT id, name, hierarchy_level FROM roles WHERE id = ? LIMIT 1");
    $roleStmt->execute([$role_id]);
    $role = $roleStmt->fetch();

    if (!$role || (int) $role['hierarchy_level'] < $current_role_level) {
        $error = 'Invalid role selection.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $username === '' || $name === '' || strlen($password) < 10) {
        $error = 'Provide valid username, name, email and password (min 10 chars).';
    } else {
        $exists = $db->prepare("SELECT id FROM admins WHERE email = ? OR username = ? LIMIT 1");
        $exists->execute([$email, $username]);
        if ($exists->fetch()) {
            $error = 'Admin with same email/username already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $ins = $db->prepare("INSERT INTO admins (email, username, name, password_hash, role_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())");
            $ins->execute([$email, $username, $name, $hash, $role_id]);
            logAdminActivity((int) $current_admin['id'], 'admin_create', 'admin', (string) $db->lastInsertId(), json_encode(['email' => $email, 'role_id' => $role_id], JSON_UNESCAPED_UNICODE));
            header('Location: ' . ADMIN_BASE_URL . '/list_admins.php');
            exit();
        }
    }
}
?>

<?php if ($error !== ''): ?><div class="message message-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<div class="panel">
    <h3>Create Admin</h3>
    <form method="POST" class="admin-task-builder-grid">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="text" name="username" placeholder="Username" required>
        <input type="text" name="name" placeholder="Display Name" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password (min 10 chars)" minlength="10" required>
        <select name="role_id" required>
            <option value="">Select Role</option>
            <?php foreach ($roles as $r): ?>
                <?php if ((int) $r['hierarchy_level'] >= $current_role_level): ?>
                    <option value="<?php echo (int) $r['id']; ?>"><?php echo htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary" type="submit">Create</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
