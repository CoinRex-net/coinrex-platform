<?php
require_once __DIR__ . '/config.php';

// Security check (prefix-based protection with explicit public allowlist)
$current_page = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$devhub_prefix = BASE_URI . '/devhub/';
$is_devhub_route = strpos($current_page, $devhub_prefix) === 0;

$public_devhub_routes = [
    BASE_URI . '/devhub/pages/auth/login.php',
    BASE_URI . '/devhub/pages/auth/register.php',
    BASE_URI . '/devhub/pages/auth/logout.php',
];

$needs_auth = $is_devhub_route && !in_array($current_page, $public_devhub_routes, true);

if ($is_devhub_route && in_array($current_page, $public_devhub_routes, true)) {
    requireFeatureAccess('devhub_auth');
}

if ($needs_auth) {
    requireFeatureAccess('devhub_full');
}

if ($needs_auth && !isLoggedIn()) {
    $redirect = urlencode($_SERVER['REQUEST_URI']);
    header('Location: ' . BASE_URL . '/devhub/pages/auth/login.php?redirect=' . $redirect);
    exit();
}

// Note: Verification check removed from global guard.
// Unverified users can now visit Dashboard, Widgets & API, Notifications, etc.
// Individual pages handle their own verification requirements.
// The terms/apply flow is still accessible for new users to get started.

// Set active page for sidebar highlighting
$activePage = $activePage ?? '';
$devhub_page_title_map = [
    'dashboard' => 'Dashboard',
    'submit-project' => 'Register Project',
    'review-insights' => 'Review Insights',
    'widget-api' => 'Widgets & API',
    'get-verified' => 'Developer Verification',
    'notifications' => 'Notifications',
];
$page_title = $page_title ?? ($devhub_page_title_map[$activePage] ?? 'DevHub');

$devhub_current_user = null;
$devhub_user_name = 'Developer';
$devhub_user_role = 'Developer';
$devhub_user_avatar = '';
$devhub_user_initial = 'D';
$devhub_user_profile_url = BASE_URL . '/public/profile.php';

$dev_notification_count = 0;
$dev_notifications = [];
$dev_notifications_url = BASE_URL . '/devhub/notifications.php?status=all';
if ($needs_auth && isLoggedIn()) {
    $current_dev_user_id = (int) (getCurrentUserId() ?? 0);
    $devhub_current_user = function_exists('getCurrentUser') ? getCurrentUser() : null;
    if (is_array($devhub_current_user)) {
        $devhub_user_name = trim((string) ($devhub_current_user['full_name'] ?? ''));
        if ($devhub_user_name === '') {
            $devhub_user_name = trim((string) ($devhub_current_user['username'] ?? 'Developer'));
        }
        $devhub_user_role = isVerifiedDeveloper($current_dev_user_id) ? 'Verified Developer' : 'Developer';
        $devhub_user_avatar = coinrexNormalizeMediaUrl((string) ($devhub_current_user['avatar'] ?? ''));
        $devhub_user_initial = strtoupper(substr($devhub_user_name !== '' ? $devhub_user_name : 'D', 0, 1));
    }
    if ($current_dev_user_id > 0 && function_exists('getUnreadNotificationCount')) {
        $dev_notification_count = getUnreadNotificationCount('developer', $current_dev_user_id);
        $dev_notifications = function_exists('getNotifications')
            ? getNotifications('developer', $current_dev_user_id, 8)
            : [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>DevHub - CoinRex</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/devhub/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/devhub/assets/css/devhub.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/devhub/assets/css/sidebar.css">
</head>
<body class="devhub-theme">
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" id="mobileMenuBtn" type="button" aria-label="Open menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="devhub-app-wrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <main class="devhub-main-content">
            <header class="devhub-topbar">
                <div class="devhub-topbar-left">
                    <strong class="devhub-topbar-title"><?php echo htmlspecialchars((string) $page_title, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="devhub-topbar-right">
                    <div class="devhub-notification-menu">
                        <button type="button" class="devhub-notification-btn" id="devNotificationsToggle" aria-label="Open DevHub notifications" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <?php if ($dev_notification_count > 0): ?>
                                <span class="devhub-notification-badge" id="devNotificationBadge"><?php echo $dev_notification_count > 99 ? '99+' : $dev_notification_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="devhub-notification-dropdown" id="devNotificationsDropdown">
                            <?php if (empty($dev_notifications)): ?>
                                <div class="devhub-notification-empty">No notifications yet.</div>
                            <?php else: ?>
                                <?php foreach ($dev_notifications as $item): ?>
                                    <a href="<?php echo htmlspecialchars((string) $dev_notifications_url, ENT_QUOTES, 'UTF-8'); ?>" class="devhub-notification-link <?php echo empty($item['is_read']) ? 'is-unread' : ''; ?>" data-notification-id="<?php echo (int) ($item['id'] ?? 0); ?>">
                                        <i class="fas fa-circle-info"></i>
                                        <span class="devhub-notification-meta">
                                            <strong><?php echo htmlspecialchars((string) ($item['title'] ?? 'Notification'), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <small><?php echo htmlspecialchars((string) mb_strimwidth((string) ($item['message'] ?? ''), 0, 90, '...'), ENT_QUOTES, 'UTF-8'); ?></small>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                                <div class="devhub-notification-actions">
                                    <a href="<?php echo htmlspecialchars((string) $dev_notifications_url, ENT_QUOTES, 'UTF-8'); ?>">View all notifications</a>
                                    <a href="#" id="devMarkAllNotificationsRead">Mark all as read</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="devhub-profile-menu">
                        <button type="button" class="devhub-profile-toggle" id="devProfileToggle" aria-label="Open profile menu" aria-haspopup="true" aria-expanded="false">
                            <div class="devhub-profile-meta">
                                <span class="devhub-profile-name"><?php echo htmlspecialchars((string) $devhub_user_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="devhub-profile-role">
                                    <?php if ($devhub_user_role === 'Verified Developer'): ?>
                                        <span class="verified-tick devhub-verified-tick" title="Verified Developer" aria-label="Verified Developer">
                                            <i class="fas fa-check"></i>
                                        </span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars((string) $devhub_user_role, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </div>
                            <div class="devhub-profile-avatar">
                                <?php if ($devhub_user_avatar !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($devhub_user_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $devhub_user_name, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php else: ?>
                                    <span><?php echo htmlspecialchars((string) $devhub_user_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                            <i class="fas fa-chevron-down devhub-profile-caret"></i>
                        </button>
                        <div class="devhub-profile-dropdown" id="devProfileDropdown">
                            <a href="<?php echo htmlspecialchars($devhub_user_profile_url, ENT_QUOTES, 'UTF-8'); ?>" class="devhub-profile-link">
                                <i class="fas fa-user"></i>
                                <span>My Profile</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/devhub/notifications.php" class="devhub-profile-link">
                                <i class="fas fa-bell"></i>
                                <span>Notifications</span>
                            </a>
                            <a href="<?php echo BASE_URL; ?>/devhub/pages/auth/logout.php" class="devhub-profile-link devhub-profile-link--danger">
                                <i class="fas fa-right-from-bracket"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>
