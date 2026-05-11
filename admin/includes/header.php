<?php
require_once __DIR__ . '/config.php';
requireAdminAuth();
requireAdminPageAccess((string) ($activePage ?? 'dashboard'));

$current_admin = getCurrentAdmin();
$page_title = $page_title ?? 'Admin Panel';
$admin_name = trim((string) ($current_admin['name'] ?? 'Admin'));
$admin_email = trim((string) ($current_admin['email'] ?? ''));
$admin_username = trim((string) ($current_admin['username'] ?? ''));
$admin_identity = $admin_username !== '' ? $admin_username : ($admin_name !== '' ? $admin_name : $admin_email);
$admin_initial = strtoupper(substr($admin_identity !== '' ? $admin_identity : 'A', 0, 1));
$admin_first_name_parts = preg_split('/\s+/', $admin_identity);
$admin_display_name = trim((string) ($admin_first_name_parts[0] ?? 'Admin'));
$unread_message_count = 0;

try {
    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM messages
        WHERE status = 'unread'
          AND (recipient_admin_id IS NULL OR recipient_admin_id = ?)
    ");
    $stmt->execute([(int) ($current_admin['id'] ?? 0)]);
    $unread_message_count = (int) ($stmt->fetch()['total'] ?? 0);
} catch (Throwable $e) {
    $unread_message_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?> - CoinRex Admin</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/devhub/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ADMIN_BASE_URL; ?>/assets/css/admin.css">
</head>
<body>
<button class="admin-mobile-menu-btn" id="adminMobileMenuBtn" aria-label="Open menu">
    <i class="fas fa-bars"></i>
</button>
<div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>
<div class="admin-layout">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="admin-content">
        <header class="admin-topbar">
            <strong><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></strong>
            <div class="admin-topbar-actions">
                <?php if (canCurrentAdmin('manage_admins')): ?>
                    <a href="<?php echo ADMIN_BASE_URL; ?>/list_admins.php" class="admin-message-btn admin-desktop-action" aria-label="Admin Management">
                        <i class="fas fa-user-gear"></i>
                    </a>
                <?php endif; ?>
                <a href="<?php echo ADMIN_BASE_URL; ?>/messages.php" class="admin-message-btn" aria-label="Messages">
                    <i class="fas fa-envelope"></i>
                    <?php if ($unread_message_count > 0): ?>
                        <span class="admin-message-badge"><?php echo $unread_message_count > 99 ? '99+' : $unread_message_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo ADMIN_BASE_URL; ?>/settings.php" class="admin-message-btn admin-desktop-action" aria-label="Account settings">
                    <i class="fas fa-gear"></i>
                </a>
                <a href="<?php echo ADMIN_BASE_URL; ?>/logout.php" class="admin-message-btn admin-logout-btn admin-desktop-action" aria-label="Logout">
                    <i class="fas fa-right-from-bracket"></i>
                </a>
                <div class="admin-user-card admin-user-card-desktop">
                    <div class="admin-user-meta">
                        <span class="admin-user-name"><?php echo htmlspecialchars($admin_display_name, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="admin-user-role"><?php echo htmlspecialchars((string) ($current_admin['role_name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="admin-avatar">
                        <span><?php echo htmlspecialchars($admin_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <div class="admin-user-menu admin-user-menu-mobile" id="adminUserMenu">
                    <button class="admin-user-card admin-user-menu-trigger" id="adminUserMenuTrigger" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="adminUserMenuDropdown">
                        <div class="admin-user-meta">
                            <span class="admin-user-name"><?php echo htmlspecialchars($admin_display_name, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="admin-user-role"><?php echo htmlspecialchars((string) ($current_admin['role_name'] ?? 'Admin'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="admin-avatar">
                            <span><?php echo htmlspecialchars($admin_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </button>
                    <div class="admin-user-dropdown" id="adminUserMenuDropdown">
                        <?php if (canCurrentAdmin('manage_admins')): ?>
                            <a href="<?php echo ADMIN_BASE_URL; ?>/list_admins.php" class="admin-user-dropdown-link">
                                <i class="fas fa-user-gear"></i>
                                <span>List Admins</span>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo ADMIN_BASE_URL; ?>/settings.php" class="admin-user-dropdown-link">
                            <i class="fas fa-gear"></i>
                            <span>Settings</span>
                        </a>
                        <a href="<?php echo ADMIN_BASE_URL; ?>/logout.php" class="admin-user-dropdown-link admin-user-dropdown-link-danger">
                            <i class="fas fa-right-from-bracket"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <main class="admin-main">
