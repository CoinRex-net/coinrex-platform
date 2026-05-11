<?php
$page_title = 'Account Settings';
$activePage = 'settings';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$admin = getCurrentAdmin();
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = adminNormalizeEmail((string) ($_POST['email'] ?? ''));

        if ($username === '' || $name === '' || $email === '') {
            $error_message = 'Username, name, and email are required.';
        } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username)) {
            $error_message = 'Username must be 3-30 chars and only use letters, numbers, dot, underscore, or dash.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please provide a valid email address.';
        } else {
            $stmt = $db->prepare("SELECT id FROM admins WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
            $stmt->execute([$username, $email, (int) $admin['id']]);
            $existing = $stmt->fetch();

            if ($existing) {
                $error_message = 'Username or email is already taken.';
            } else {
                $update = $db->prepare("UPDATE admins SET username = ?, name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                $update->execute([$username, $name, $email, (int) $admin['id']]);
                $_SESSION[ADMIN_SESSION_EMAIL_KEY] = $email;
                logAdminActivity((int) $admin['id'], 'admin_profile_update', 'admin', (string) $admin['id'], json_encode(['username' => $username, 'email' => $email], JSON_UNESCAPED_UNICODE));
                $success_message = 'Account profile updated successfully.';
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = (string) ($_POST['current_password'] ?? '');
        $new_password = (string) ($_POST['new_password'] ?? '');
        $confirm_password = (string) ($_POST['confirm_password'] ?? '');

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $error_message = 'Please fill all password fields.';
        } elseif (!password_verify($current_password, (string) ($admin['password_hash'] ?? ''))) {
            $error_message = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 10) {
            $error_message = 'New password must be at least 10 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'Password confirmation does not match.';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
            $update = $db->prepare("UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $update->execute([$new_hash, (int) $admin['id']]);
            logAdminActivity((int) $admin['id'], 'admin_password_change', 'admin', (string) $admin['id'], null);
            $success_message = 'Password updated successfully.';
        }
    } else {
        $error_message = 'Invalid settings action.';
    }

    $admin = getCurrentAdmin();
}
?>

<?php if ($success_message !== ''): ?>
    <div class="message message-success"><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if ($error_message !== ''): ?>
    <div class="message message-error"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<div class="panel">
    <h3>Profile</h3>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="update_profile">
        <div class="settings-grid">
            <div>
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars((string) ($admin['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div>
                <label for="name">Display Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars((string) ($admin['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
        </div>
        <div class="settings-grid">
            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars((string) ($admin['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Save Profile</button>
    </form>
</div>

<div class="panel">
    <h3>Change Password</h3>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="change_password">
        <div class="settings-grid">
            <div>
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>
            <div>
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>
            <div>
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
