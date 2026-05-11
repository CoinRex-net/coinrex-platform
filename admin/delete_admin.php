<?php
$page_title = 'Delete Admin';
$activePage = 'delete-admin';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();

$admin_id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($admin_id <= 0) { die('Invalid admin id'); }

if (!RbacService::isSuperAdmin((int) $current_admin['id'])) {
    http_response_code(403);
    die('Only super_admin can delete admin accounts.');
}

if ($admin_id === (int) $current_admin['id']) {
    die('You cannot delete your own account.');
}

$stmt = $db->prepare("SELECT a.id, a.email, r.name AS role_name FROM admins a LEFT JOIN roles r ON r.id = a.role_id WHERE a.id = ? LIMIT 1");
$stmt->execute([$admin_id]);
$target = $stmt->fetch();
if (!$target) { die('Admin not found'); }

if (strtolower((string) ($target['role_name'] ?? '')) === 'super_admin') {
    $count = (int) $db->query("SELECT COUNT(*) FROM admins a INNER JOIN roles r ON r.id = a.role_id WHERE r.name = 'super_admin' AND a.status = 'active'")->fetchColumn();
    if ($count <= 1) {
        die('At least one active super_admin must always exist.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $del = $db->prepare("DELETE FROM admins WHERE id = ? LIMIT 1");
    $del->execute([$admin_id]);
    logAdminActivity((int) $current_admin['id'], 'admin_delete', 'admin', (string) $admin_id, json_encode(['target_email' => (string) $target['email']], JSON_UNESCAPED_UNICODE));
    header('Location: ' . ADMIN_BASE_URL . '/list_admins.php');
    exit();
}
?>

<div class="panel">
    <h3>Delete Admin</h3>
    <p>Are you sure you want to delete admin <strong><?php echo htmlspecialchars((string) $target['email'], ENT_QUOTES, 'UTF-8'); ?></strong>?</p>
    <form method="POST" class="inline-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?php echo (int) $admin_id; ?>">
        <button type="submit" class="btn btn-danger">Yes, Delete</button>
        <a class="btn btn-secondary" href="<?php echo ADMIN_BASE_URL; ?>/list_admins.php">Cancel</a>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
