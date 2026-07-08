<?php
/**
 * CoinRex Admin Auth Helpers
 * Location: /coinrex/admin/includes/auth.php
 */

if (!defined('ADMIN_CSRF_SESSION_KEY')) {
    define('ADMIN_CSRF_SESSION_KEY', '_admin_csrf_token');
}

if (!defined('ADMIN_MAX_LOGIN_ATTEMPTS')) {
    define('ADMIN_MAX_LOGIN_ATTEMPTS', 5);
}

if (!defined('ADMIN_LOCKOUT_MINUTES')) {
    define('ADMIN_LOCKOUT_MINUTES', 15);
}

function adminNormalizeEmail($email) {
    return strtolower(trim((string) $email));
}

function getAdminByEmail($email) {
    $db = getDBConnection();
    $has_roles = function_exists('tableExists') ? tableExists('roles') : false;
    $has_role_id = function_exists('tableHasColumn') ? tableHasColumn('admins', 'role_id') : false;
    $sql = $has_roles && $has_role_id
        ? "SELECT a.*, r.name AS role_name, r.hierarchy_level FROM admins a LEFT JOIN roles r ON r.id = a.role_id WHERE a.email = ? LIMIT 1"
        : "SELECT a.*, 'super_admin' AS role_name, 1 AS hierarchy_level FROM admins a WHERE a.email = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([adminNormalizeEmail($email)]);
    return $stmt->fetch();
}

function getAdminById($admin_id) {
    $db = getDBConnection();
    $has_roles = function_exists('tableExists') ? tableExists('roles') : false;
    $has_role_id = function_exists('tableHasColumn') ? tableHasColumn('admins', 'role_id') : false;
    $sql = $has_roles && $has_role_id
        ? "SELECT a.*, r.name AS role_name, r.hierarchy_level FROM admins a LEFT JOIN roles r ON r.id = a.role_id WHERE a.id = ? LIMIT 1"
        : "SELECT a.*, 'super_admin' AS role_name, 1 AS hierarchy_level FROM admins a WHERE a.id = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([(int) $admin_id]);
    return $stmt->fetch();
}

function adminIpAddress() {
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function adminLogEvent($admin_id, $action, $details = null) {
    if (!(function_exists('tableExists') && tableExists('admin_logs'))) {
        return;
    }
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([(int) $admin_id ?: null, (string) $action, $details !== null ? (string) $details : null, adminIpAddress()]);
    } catch (Throwable $e) {
        // no-op: avoid breaking runtime if logs table is not yet migrated
    }
}

function establishAdminSession($admin) {
    session_regenerate_id(true);
    $_SESSION[ADMIN_SESSION_ID_KEY] = (int) $admin['id'];
    $_SESSION[ADMIN_SESSION_EMAIL_KEY] = (string) $admin['email'];
    $_SESSION[ADMIN_SESSION_NAME_KEY] = (string) $admin['name'];
    $_SESSION['role_id'] = isset($admin['role_id']) ? (int) $admin['role_id'] : null;
    $_SESSION['role_name'] = (string) ($admin['role_name'] ?? 'super_admin');
    $_SESSION['admin_role_name'] = (string) ($admin['role_name'] ?? 'super_admin');
}

function isAdminLoggedIn() {
    return !empty($_SESSION[ADMIN_SESSION_ID_KEY]);
}

function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }

    $admin = getAdminById((int) $_SESSION[ADMIN_SESSION_ID_KEY]);
    if (!$admin || ($admin['status'] ?? 'disabled') !== 'active') {
        adminLogout();
        return null;
    }

    return $admin;
}

function adminLogin($email, $password) {
    $admin = getAdminByEmail($email);
    if (!$admin) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    if (!empty($admin['locked_until']) && strtotime((string) $admin['locked_until']) > time()) {
        adminLogEvent((int) $admin['id'], 'admin_login_blocked_locked', null);
        return ['success' => false, 'message' => 'Account is temporarily locked due to multiple failed login attempts.'];
    }

    if (($admin['status'] ?? 'disabled') !== 'active') {
        return ['success' => false, 'message' => 'Admin account is disabled'];
    }

    if (!password_verify((string) $password, (string) $admin['password_hash'])) {
        $failed_attempts = ((int) ($admin['failed_login_attempts'] ?? 0)) + 1;
        $is_lock = $failed_attempts >= ADMIN_MAX_LOGIN_ATTEMPTS;
        $db = getDBConnection();
        if ($is_lock) {
            $stmt = $db->prepare("UPDATE admins SET failed_login_attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE), updated_at = NOW() WHERE id = ?");
            $stmt->execute([$failed_attempts, ADMIN_LOCKOUT_MINUTES, (int) $admin['id']]);
            adminLogEvent((int) $admin['id'], 'admin_lockout_triggered', json_encode(['attempts' => $failed_attempts], JSON_UNESCAPED_UNICODE));
        } else {
            $stmt = $db->prepare("UPDATE admins SET failed_login_attempts = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$failed_attempts, (int) $admin['id']]);
        }
        adminLogEvent((int) $admin['id'], 'admin_login_failed', json_encode(['attempts' => $failed_attempts], JSON_UNESCAPED_UNICODE));
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    establishAdminSession($admin);

    $db = getDBConnection();
    $stmt = $db->prepare("UPDATE admins SET last_login_at = NOW(), failed_login_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = ?");
    $stmt->execute([(int) $admin['id']]);
    adminLogEvent((int) $admin['id'], 'admin_login_success', null);

    return ['success' => true, 'admin' => $admin];
}

function adminLogout() {
    $admin_id = (int) ($_SESSION[ADMIN_SESSION_ID_KEY] ?? 0);
    unset($_SESSION[ADMIN_SESSION_ID_KEY]);
    unset($_SESSION[ADMIN_SESSION_EMAIL_KEY]);
    unset($_SESSION[ADMIN_SESSION_NAME_KEY]);
    unset($_SESSION['admin_role_name']);
    unset($_SESSION['role_id']);
    unset($_SESSION['role_name']);
    unset($_SESSION[ADMIN_CSRF_SESSION_KEY]);
    if ($admin_id > 0) {
        adminLogEvent($admin_id, 'admin_logout', null);
    }
}

function hasPermission($admin_id, $permission_name) {
    return RbacService::hasPermission((int) $admin_id, (string) $permission_name);
}

function getAdminRole($admin_id) {
    return (string) (RbacService::getAdminRole((int) $admin_id) ?? '');
}

function getPermissionsByRole($role_id) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT p.name FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? ORDER BY p.name ASC");
    $stmt->execute([(int) $role_id]);
    return array_map(static function ($row) { return (string) $row['name']; }, $stmt->fetchAll());
}

function createRole($name, $description = '', $hierarchy_level = 100) {
    $db = getDBConnection();
    $stmt = $db->prepare("INSERT INTO roles (name, description, hierarchy_level, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $stmt->execute([trim((string) $name), trim((string) $description), (int) $hierarchy_level]);
    return (int) $db->lastInsertId();
}

function assignRoleToAdmin($admin_id, $role_id) {
    $db = getDBConnection();
    $stmt = $db->prepare("UPDATE admins SET role_id = ?, updated_at = NOW() WHERE id = ?");
    return $stmt->execute([(int) $role_id, (int) $admin_id]);
}

function requireAdminPermission($permission_name) {
    $admin = getCurrentAdmin();
    if (!$admin || !hasPermission((int) $admin['id'], (string) $permission_name)) {
        http_response_code(403);
        die('Access denied: insufficient permissions.');
    }
}

function requireAdminPageAccess($active_page) {
    $page_map = [
        'dashboard' => 'view_reports',
        'users' => 'manage_users',
        'projects' => 'manage_projects',
        'reviews' => 'manage_reviews',
        'developers' => 'manage_developers',
        'rewards' => 'manage_rewards',
        'reward-ledger' => 'manage_rewards',
        'reward-users' => 'manage_rewards',
        'referrals' => 'manage_rewards',
        'early-airdrop' => 'manage_rewards',
        'task-management' => 'manage_tasks',
        'quiz-manager' => 'manage_tasks',
        'taskhub-review' => 'moderate_tasks',
        'boosthub-management' => 'moderate_tasks',
        'boosthub-evidence' => 'moderate_tasks',
        'boosthub' => 'moderate_tasks',
        'blog' => 'manage_blog',
        'blog-create' => 'manage_blog',
        'blog-edit' => 'manage_blog',
        'blog-categories' => 'manage_blog',
        'blog-tags' => 'manage_blog',
        'messages' => 'view_reports',
        'launch-control' => 'manage_launch_controls',
        'roadmap' => 'manage_roadmap',
        'security-management' => 'manage_users',
        'admins' => 'manage_admins',
        'list-admins' => 'manage_admins',
        'create-admin' => 'manage_admins',
        'edit-admin' => 'manage_admins',
        'delete-admin' => 'manage_admins',
    ];

    $required = $page_map[(string) $active_page] ?? null;
    if ($required !== null) {
        requireAdminPermission($required);
    }
}

function canCurrentAdmin($permission_name) {
    $admin = getCurrentAdmin();
    return $admin ? hasPermission((int) $admin['id'], (string) $permission_name) : false;
}

function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . ADMIN_BASE_URL . '/login.php');
        exit();
    }

    if (!getCurrentAdmin()) {
        header('Location: ' . ADMIN_BASE_URL . '/login.php');
        exit();
    }
}

function adminCsrfToken() {
    if (empty($_SESSION[ADMIN_CSRF_SESSION_KEY])) {
        $_SESSION[ADMIN_CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION[ADMIN_CSRF_SESSION_KEY];
}

function validateAdminCsrfToken($token) {
    $session_token = $_SESSION[ADMIN_CSRF_SESSION_KEY] ?? '';
    return is_string($token)
        && is_string($session_token)
        && $token !== ''
        && hash_equals($session_token, $token);
}

function requireAdminCsrf($token) {
    if (!validateAdminCsrfToken($token)) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

function logAdminActivity($admin_id, $action, $target_type, $target_id, $details = null) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO admin_activity_logs (admin_id, action, target_type, target_id, details, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int) $admin_id,
        substr((string) $action, 0, 80),
        substr((string) $target_type, 0, 50),
        substr((string) $target_id, 0, 80),
        $details !== null ? (string) $details : null,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    adminLogEvent((int) $admin_id, (string) $action, $details);
}
?>
