<?php
/**
 * CoinRex Header Component
 * Location: /coinrex/includes/header.php
 */

// Include configuration
require_once __DIR__ . '/config.php';

// Set current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$coinrex_embedded_learning = (string) ($_GET['th_embed'] ?? '') === '1';

if (!function_exists('coinrexSeoUrl')) {
    function coinrexSeoUrl($path = '') {
        $base_url = defined('PUBLIC_BASE_URL') ? PUBLIC_BASE_URL : BASE_URL;
        $base_url = rtrim((string) $base_url, '/');
        $path = '/' . ltrim((string) $path, '/');

        return $base_url . ($path === '/' ? '' : $path);
    }
}

if (!function_exists('coinrexCanonicalUrl')) {
    function coinrexCanonicalUrl() {
        $script_path = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $path_info = trim((string) ($_SERVER['PATH_INFO'] ?? ''), '/');
        $path = $script_path !== '' ? $script_path : '/';

        if ($path_info !== '') {
            $path .= '/' . $path_info;
        }

        if (basename($script_path) === 'blog-post.php') {
            $slug = trim((string) ($_GET['slug'] ?? $path_info));
            if ($slug !== '') {
                $path = preg_replace('#/blog-post\.php(?:/.*)?$#', '/blog-post.php/' . rawurlencode($slug), $script_path) ?: $path;
            }
        } elseif (basename($script_path) === 'project-detail.php' && isset($_GET['id'])) {
            $project_id = (int) $_GET['id'];
            if ($project_id > 0) {
                $path = $script_path . '?id=' . $project_id;
            }
        }

        return coinrexSeoUrl($path);
    }
}

if ($coinrex_embedded_learning) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo SITE_NAME; ?> - Learning</title>
    <meta name="base-url" content="<?php echo BASE_URL; ?>">
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/images/favicon.ico">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/theme.css">
    <style>
        html, body {
            min-height: 100%;
            margin: 0;
            background: #0f172a;
            color: #e2e8f0;
            overflow-x: hidden;
        }

        body {
            padding: 0;
        }

        body::before,
        body::after,
        .nex-container,
        .mobile-bottom-nav,
        .footer,
        .fixed-social {
            display: none !important;
        }
    </style>
</head>
<body data-theme="dark" class="coinrex-embedded-learning">
<?php
    return;
}

// Check if user is logged in (implement your auth logic)
$is_logged_in = isset($_SESSION['user_id']) ? true : false;
$user_name = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$user_display_name = $user_name;
$user_balance_display = '0.00 $REX';
$user_avatar_url = '';
$home_url = $is_logged_in ? (BASE_URL . '/public/dashboard.php') : (BASE_URL . '/index.php');
$request_path = str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? ''));
$is_devhub_request = strpos($request_path, '/devhub/') !== false;
$home_active = $is_logged_in
    ? ($current_page == 'dashboard')
    : ($current_page == 'index' || $current_page == '');

if ($is_logged_in && function_exists('getCurrentUser')) {
    $header_user = getCurrentUser();

    if ($header_user) {
        if (function_exists('unlockPendingEarlyAirdropForUser')) {
            try {
                $header_db = getDBConnection();
                unlockPendingEarlyAirdropForUser((int) $header_user['id'], $header_db);
                $header_user = getUserById((int) $header_user['id']) ?: $header_user;
            } catch (Throwable $e) {
                // Header rendering should not fail because a reward sync did not complete.
            }
        }

        $username = trim((string)($header_user['username'] ?? $user_name));
        $full_name = trim((string)($header_user['full_name'] ?? ''));
        $first_name = '';

        if ($full_name !== '') {
            $name_parts = preg_split('/\s+/', $full_name);
            $first_name = trim((string)($name_parts[0] ?? ''));
        }

        $user_name = $username !== '' ? $username : $user_name;
        $user_display_name = $first_name !== '' ? $first_name : ($username !== '' ? $username : 'User');
        $user_balance_display = number_format((float)($header_user['rex_balance'] ?? 0), 2) . ' $REX';
        $user_avatar_url = coinrexNormalizeMediaUrl((string) ($header_user['avatar'] ?? ''));
    }
}

$user_notification_count = 0;
$user_notifications = [];
$notifications_all_url = BASE_URL . '/public/notifications.php?status=all';
$notifications_unread_url = BASE_URL . '/public/notifications.php?status=unread';
if ($is_logged_in && !empty($header_user['id']) && function_exists('getUnreadNotificationCount')) {
    $user_notification_count = getUnreadNotificationCount('user', (int) $header_user['id']);
    $user_notifications = getNotifications('user', (int) $header_user['id'], 6);
}

$user_level_for_nav = normalizeUserLevel(($header_user['level'] ?? $_SESSION['level'] ?? 'beginner'));
$taskhub_mission_completed_for_nav = false;
if ($is_logged_in && !empty($header_user['id']) && function_exists('taskHubMissionCompleted')) {
    try {
        $taskhub_mission_completed_for_nav = taskHubMissionCompleted((int) $header_user['id'], getDBConnection());
    } catch (Throwable $e) {
        $taskhub_mission_completed_for_nav = false;
    }
}
$show_dashboard_nav = featureIsVisible('dashboard');
$show_projects_nav = featureIsVisible('projects');
$show_reviews_nav = featureIsVisible('reviews');
$show_learnhub_nav = featureIsVisible('learnhub');
$can_access_taskhub_nav = $show_learnhub_nav && (!$is_logged_in || !$taskhub_mission_completed_for_nav);
$show_boosthub_nav = featureIsVisible('boosthub');
$show_claim_center_nav = featureIsVisible('claim_center');
$show_devhub_nav = featureIsVisible('devhub_full') || featureIsVisible('devhub_auth');
$show_login_nav = featureIsVisible('login');
$can_access_claim_center_nav = $is_logged_in && $show_claim_center_nav;
$claim_center_accessible_nav = featureIsAccessible('claim_center');
$learnhub_nav_url = BASE_URL . '/public/taskhub.php';
$boosthub_nav_url = BASE_URL . '/public/boosthub.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php
    $coinrex_page_title = trim((string) ($page_title ?? ''));
    $coinrex_meta_description = trim((string) ($meta_description ?? ''));
    $coinrex_meta_keywords = trim((string) ($meta_keywords ?? ''));
    $coinrex_canonical_url = trim((string) ($canonical_url ?? ''));
    $coinrex_page_title = $coinrex_page_title !== '' ? $coinrex_page_title : (SITE_NAME . ' - ' . SITE_TAGLINE);
    $coinrex_meta_description = $coinrex_meta_description !== ''
        ? $coinrex_meta_description
        : 'CoinRex helps crypto users discover projects, publish proof-backed reviews, and earn rewards through trust-driven participation.';
    $coinrex_meta_keywords = $coinrex_meta_keywords !== ''
        ? $coinrex_meta_keywords
        : 'crypto reviews, blockchain projects, verified crypto reviews, crypto rewards, CoinRex';
    $coinrex_canonical_url = $coinrex_canonical_url !== '' ? $coinrex_canonical_url : coinrexCanonicalUrl();
    ?>
    <title><?php echo htmlspecialchars($coinrex_page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($coinrex_meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($coinrex_meta_keywords, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="CoinRex">
    <meta name="robots" content="index,follow">
    <meta name="theme-color" content="#0f172a">
    <meta property="og:site_name" content="<?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($coinrex_page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($coinrex_meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo htmlspecialchars($coinrex_canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars(coinrexSeoUrl('/assets/images/logo.png'), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($coinrex_page_title, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($coinrex_meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars(coinrexSeoUrl('/assets/images/logo.png'), ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Base URL for JS -->
    <meta name="base-url" content="<?php echo BASE_URL; ?>">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($coinrex_canonical_url, ENT_QUOTES, 'UTF-8'); ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/images/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/images/favicon.ico">
    <link rel="apple-touch-icon" href="<?php echo ASSETS_URL; ?>/images/favicon.png">
    <link rel="manifest" href="<?php echo coinrexSeoUrl('/manifest.json'); ?>">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/theme.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/header.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/header.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if ($is_logged_in): ?>
    <style>
    .rexlink-session-chip {
        position: fixed;
        left: 18px;
        bottom: 18px;
        z-index: 1700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-height: 38px;
        padding: 8px 12px;
        border: 1px solid rgba(34, 197, 94, .34);
        border-radius: 999px;
        background: rgba(8, 24, 18, .94);
        color: #bbf7d0;
        box-shadow: 0 16px 38px rgba(0, 0, 0, .32);
        backdrop-filter: blur(10px);
        font-size: 12px;
        font-weight: 900;
        letter-spacing: 0;
        transform: translateY(8px);
        opacity: 0;
        pointer-events: none;
        transition: opacity .2s ease, transform .2s ease, border-color .2s ease, color .2s ease, background .2s ease;
    }
    .rexlink-session-chip[hidden] {
        display: none;
    }
    .rexlink-session-chip.is-visible {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }
    .rexlink-session-chip.is-warning {
        border-color: rgba(250, 204, 21, .42);
        background: rgba(36, 28, 8, .94);
        color: #fde68a;
    }
    .rexlink-session-chip i {
        color: currentColor;
    }
    .rexlink-session-chip strong {
        color: #f8fafc;
    }
    @media (max-width: 768px) {
        .rexlink-session-chip {
            left: 12px;
            bottom: 76px;
            min-height: 34px;
            padding: 7px 10px;
            font-size: 11px;
        }
    }
    </style>
    <?php endif; ?>
</head>
<body data-theme="dark">

<!-- Top Navbar (Desktop + Tablet) -->
<nav class="nex-nav" aria-label="Primary navigation">
    <div class="nex-container">
        <div class="nex-navbar">
            
            <!-- Logo -->
            <div class="nex-logo">
                <a href="<?php echo $home_url; ?>">
                    <img src="<?php echo ASSETS_URL; ?>/images/logo.png" 
                         alt="CoinRex" 
                         class="nex-logo-img"
                         onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\'fas fa-coins\' style=\'font-size:28px; color:#22c55e;\'></i>'">
                </a>
            </div>

            <!-- Desktop Navigation Links -->
            <div class="nex-nav-links">
                <?php if($is_logged_in && $show_dashboard_nav): ?>
                <a href="<?php echo BASE_URL; ?>/public/dashboard.php" class="<?php echo $home_active ? 'active' : ''; ?>">
                    <i class="fas fa-gem"></i>
                    <span>RexHub</span>
                </a>
                <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/index.php" class="<?php echo $home_active ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <?php endif; ?>
                <?php if($show_projects_nav): ?>
                <a href="<?php echo BASE_URL; ?>/public/projects.php" class="<?php echo ($current_page == 'projects') ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Projects</span>
                </a>
                <?php endif; ?>
                <?php if($can_access_taskhub_nav): ?>
                <a href="<?php echo $learnhub_nav_url; ?>" class="<?php echo ($current_page == 'taskhub') ? 'active' : ''; ?>">
                    <i class="fas fa-list-check"></i>
                    <span class="nex-nav-label">LearnHub <span class="nex-hot-badge">HOT</span></span>
                </a>
                <?php endif; ?>
                <?php if($show_boosthub_nav): ?>
                    <a href="<?php echo $boosthub_nav_url; ?>" class="<?php echo ($current_page == 'boosthub') ? 'active' : ''; ?>">
                        <i class="fas fa-bolt"></i>
                        <span class="nex-nav-label">BoostHub <span class="nex-hot-badge">NEW</span></span>
                    </a>
                <?php endif; ?>
                <?php $resources_active = in_array($current_page, ['roadmap', 'litepaper', 'blog', 'blog-post', 'blog-category', 'blog-tag', 'about'], true); ?>
                <div class="nex-resource-menu">
                    <button type="button" class="nex-resource-trigger <?php echo $resources_active ? 'active' : ''; ?>" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-layer-group"></i>
                        <span>Resources</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="nex-dropdown nex-resource-dropdown">
                        <a href="<?php echo BASE_URL; ?>/public/roadmap.php"><i class="fas fa-route"></i><span class="nex-dropdown-link-label">Roadmap</span></a>
                        <a href="<?php echo BASE_URL; ?>/public/litepaper.php"><i class="fas fa-file-alt"></i><span class="nex-dropdown-link-label">Litepaper</span></a>
                        <a href="<?php echo BASE_URL; ?>/public/blog.php"><i class="fas fa-blog"></i><span class="nex-dropdown-link-label">Blog</span></a>
                        <a href="<?php echo BASE_URL; ?>/public/about.php"><i class="fas fa-circle-info"></i><span class="nex-dropdown-link-label">About Us</span></a>
                    </div>
                </div>
            </div>

            <!-- Right Actions -->
            <div class="nex-actions">
                <?php if($is_logged_in): ?>
                    <div class="nex-notification-menu">
                        <button type="button" class="nex-notification-btn" id="userNotificationsToggle" aria-label="Open notifications" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-bell"></i>
                            <span class="nex-notification-badge <?php echo $user_notification_count > 0 ? '' : 'is-hidden'; ?>" id="userNotificationBadge"><?php echo $user_notification_count > 99 ? '99+' : $user_notification_count; ?></span>
                        </button>
                        <div class="nex-dropdown nex-notification-dropdown" id="userNotificationsDropdown">
                            <div class="nex-notification-panel">
                                <div class="nex-notification-panel-head">
                                    <div class="nex-notification-panel-copy">
                                        <strong class="nex-notification-panel-title">Notifications</strong>
                                        <span class="nex-notification-panel-subtitle" id="userNotificationStatusText"><?php echo $user_notification_count > 0 ? ($user_notification_count . ' unread notification' . ($user_notification_count === 1 ? '' : 's')) : 'All caught up'; ?></span>
                                    </div>
                                    <button type="button" class="nex-notification-action <?php echo $user_notification_count > 0 ? '' : 'is-disabled'; ?>" id="markAllNotificationsRead" <?php echo $user_notification_count > 0 ? '' : 'disabled'; ?>>
                                        <i class="fas fa-check-double"></i>
                                        <span>Mark all</span>
                                    </button>
                                </div>
                                <div class="nex-notification-list" id="userNotificationsList">
                                    <?php if (empty($user_notifications)): ?>
                                        <div class="nex-notification-empty">
                                            <i class="fas fa-bell-slash"></i>
                                            <span>No notifications yet.</span>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($user_notifications as $item): ?>
                                            <?php $item_href = trim((string) ($item['action_url'] ?? '')) !== '' ? (string) $item['action_url'] : (string) $notifications_unread_url; ?>
                                            <a href="<?php echo htmlspecialchars($item_href, ENT_QUOTES, 'UTF-8'); ?>" class="nex-notification-link <?php echo empty($item['is_read']) ? 'is-unread' : ''; ?>" data-notification-id="<?php echo (int) ($item['id'] ?? 0); ?>" data-notification-read="<?php echo empty($item['is_read']) ? '0' : '1'; ?>">
                                                <span class="nex-notification-icon-wrap"><i class="fas fa-circle-info"></i></span>
                                                <span class="nex-dropdown-link-meta">
                                                    <span class="nex-notification-line">
                                                        <span class="nex-dropdown-link-label"><?php echo htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <?php if (empty($item['is_read'])): ?><span class="nex-notification-dot"></span><?php endif; ?>
                                                    </span>
                                                    <small><?php echo htmlspecialchars((string) ($item['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                                                    <em><?php echo htmlspecialchars((string) ($item['time_ago'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></em>
                                                </span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="nex-notification-panel-foot">
                                    <a href="<?php echo htmlspecialchars((string) $notifications_all_url, ENT_QUOTES, 'UTF-8'); ?>" class="nex-notification-footer-link"><i class="fas fa-list"></i><span>View all</span></a>
                                    <a href="<?php echo htmlspecialchars((string) $notifications_unread_url, ENT_QUOTES, 'UTF-8'); ?>" class="nex-notification-footer-link"><i class="fas fa-envelope-open-text"></i><span>Unread only</span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="nex-user-menu">
                        <button type="button" class="nex-user-trigger" id="userAvatar" aria-label="Open user menu" aria-haspopup="true" aria-expanded="false">
                            <div class="nex-user-meta">
                                <span class="nex-user-name"><?php echo htmlspecialchars($user_display_name, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="nex-user-balance"><?php echo htmlspecialchars($user_balance_display, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="nex-avatar<?php echo $user_avatar_url !== '' ? ' has-avatar-image' : ''; ?>"<?php if ($user_avatar_url !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($user_avatar_url, ENT_QUOTES, 'UTF-8'); ?>');"<?php endif; ?>>
                                <?php if ($user_avatar_url === ''): ?>
                                    <span class="nex-avatar-initial"><?php echo htmlspecialchars(strtoupper(substr($user_name !== '' ? $user_name : $user_display_name, 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </div>
                        </button>
                        <div class="nex-dropdown" id="userDropdown">
                            <a href="<?php echo BASE_URL; ?>/public/profile.php"><i class="fas fa-id-badge"></i><span class="nex-dropdown-link-label">Profile</span></a>
                            <a href="<?php echo BASE_URL; ?>/public/dashboard.php"><i class="fas fa-user"></i> Dashboard</a>
                            <a href="<?php echo BASE_URL; ?>/public/reward-history.php"><i class="fas fa-clock-rotate-left"></i> Reward History</a>
                            <?php if ($can_access_claim_center_nav): ?>
                                <a href="<?php echo BASE_URL; ?>/public/claims.php"><i class="fas fa-gift"></i><span class="nex-dropdown-link-label">Claim Center</span><?php if (!$claim_center_accessible_nav): ?><span class="nex-link-badge">Soon</span><?php endif; ?></a>
                            <?php endif; ?>
                            <hr>
                            <a href="<?php echo BASE_URL; ?>/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php if ($show_login_nav): ?>
                    <a href="<?php echo AUTH_URL; ?>/auth.php" class="nex-btn nex-btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Sign In</span>
                    </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
        </div>
    </div>
</nav>

<!-- Mobile Bottom Navigation Bar (appears only on mobile) -->
<div class="mobile-bottom-nav">
    <?php if($can_access_taskhub_nav): ?>
    <a href="<?php echo $learnhub_nav_url; ?>" class="mobile-nav-item <?php echo ($current_page == 'taskhub') ? 'active' : ''; ?>">
        <span class="mobile-nav-icon-wrap">
            <i class="fas fa-list-check"></i>
            <span class="mobile-hot-badge">HOT</span>
        </span>
        <span class="mobile-nav-label">LearnHub</span>
    </a>
    <?php endif; ?>
    <a href="<?php echo $boosthub_nav_url; ?>" class="mobile-nav-item <?php echo ($current_page == 'boosthub') ? 'active' : ''; ?>">
        <span class="mobile-nav-icon-wrap">
            <i class="fas fa-bolt"></i>
            <span class="mobile-hot-badge">NEW</span>
        </span>
        <span class="mobile-nav-label">BoostHub</span>
    </a>
    <a href="<?php echo $home_url; ?>" class="mobile-nav-item mobile-nav-home <?php echo $home_active ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <?php if($is_logged_in): ?>
        <a href="<?php echo BASE_URL; ?>/public/projects.php" class="mobile-nav-item <?php echo ($current_page == 'projects') ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Projects</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/public/reviews.php" class="mobile-nav-item <?php echo in_array($current_page, ['reviews', 'my-reviews'], true) ? 'active' : ''; ?>">
            <i class="fas fa-star"></i>
            <span>Reviews</span>
        </a>
    <?php else: ?>
        <a href="<?php echo BASE_URL; ?>/public/litepaper.php" class="mobile-nav-item <?php echo ($current_page == 'litepaper') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>Litepaper</span>
        </a>
        <a href="<?php echo BASE_URL; ?>/public/roadmap.php" class="mobile-nav-item <?php echo ($current_page == 'roadmap') ? 'active' : ''; ?>">
            <i class="fas fa-route"></i>
            <span>Roadmap</span>
        </a>
    <?php endif; ?>
</div>

<?php if ($is_logged_in): ?>
<div class="rexlink-session-chip" id="rexLinkSessionChip" hidden>
    <i class="fas fa-link"></i>
    <span>RexLink <strong id="rexLinkSessionChipTime">--:--</strong></span>
</div>
<?php endif; ?>

<script>
// User dropdown functionality
const userAvatar = document.getElementById('userAvatar');
const userDropdown = document.getElementById('userDropdown');
const userNotificationsToggle = document.getElementById('userNotificationsToggle');
const userNotificationsDropdown = document.getElementById('userNotificationsDropdown');
const markAllNotificationsReadBtn = document.getElementById('markAllNotificationsRead');
const userNotificationBadge = document.getElementById('userNotificationBadge');
const userNotificationStatusText = document.getElementById('userNotificationStatusText');
const userNotificationsList = document.getElementById('userNotificationsList');

<?php if ($is_logged_in): ?>
(function() {
    const chip = document.getElementById('rexLinkSessionChip');
    const chipTime = document.getElementById('rexLinkSessionChipTime');
    const sessionsEndpoint = <?php echo json_encode(BASE_URL . '/api/rex-signer/sessions.php', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    let remainingSeconds = 0;
    let tickTimer = null;
    let pollTimer = null;
    let expiryDispatched = false;

    function formatRexLinkTime(seconds) {
        const safeSeconds = Math.max(0, Number(seconds || 0));
        const minutes = Math.floor(safeSeconds / 60);
        const secs = String(safeSeconds % 60).padStart(2, '0');
        return minutes + ':' + secs;
    }

    function hideRexLinkChip(dispatchExpired) {
        remainingSeconds = 0;
        if (chip) {
            chip.classList.remove('is-visible', 'is-warning');
            chip.hidden = true;
        }
        if (dispatchExpired && !expiryDispatched) {
            expiryDispatched = true;
            window.dispatchEvent(new CustomEvent('rexlink:session-expired'));
        }
    }

    function renderRexLinkChip() {
        if (!chip || !chipTime) {
            return;
        }
        if (remainingSeconds <= 0) {
            hideRexLinkChip(true);
            return;
        }
        chip.hidden = false;
        chipTime.textContent = formatRexLinkTime(remainingSeconds);
        chip.classList.toggle('is-warning', remainingSeconds <= 120);
        window.requestAnimationFrame(function() {
            chip.classList.add('is-visible');
        });
    }

    function startRexLinkTick() {
        if (tickTimer) {
            window.clearInterval(tickTimer);
        }
        tickTimer = window.setInterval(function() {
            if (remainingSeconds > 0) {
                remainingSeconds -= 1;
            }
            renderRexLinkChip();
        }, 1000);
    }

    function fetchRexLinkSession() {
        return fetch(sessionsEndpoint, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function(response) {
            return response.json();
        }).then(function(data) {
            if (!data || data.success !== true) {
                throw new Error((data && data.message) || 'Could not load RexLink session.');
            }
            const currentSession = data.current_session || null;
            const nextRemaining = currentSession && currentSession.status === 'active'
                ? Number(currentSession.remaining_seconds || 0)
                : 0;
            if (nextRemaining > 0) {
                expiryDispatched = false;
                remainingSeconds = nextRemaining;
                renderRexLinkChip();
                startRexLinkTick();
            } else {
                hideRexLinkChip(false);
            }
            return data;
        }).catch(function() {
            hideRexLinkChip(false);
            return null;
        });
    }

    fetchRexLinkSession();
    pollTimer = window.setInterval(function() {
        if (document.visibilityState === 'visible') {
            fetchRexLinkSession();
        }
    }, 5000);
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            fetchRexLinkSession();
        }
    });
    window.addEventListener('beforeunload', function() {
        if (tickTimer) {
            window.clearInterval(tickTimer);
        }
        if (pollTimer) {
            window.clearInterval(pollTimer);
        }
    });
})();
<?php endif; ?>

if (userAvatar && userDropdown) {
    userAvatar.addEventListener('click', function(e) {
        e.stopPropagation();
        userDropdown.classList.toggle('active');
        userAvatar.setAttribute('aria-expanded', userDropdown.classList.contains('active') ? 'true' : 'false');
    });
    
    document.addEventListener('click', function() {
        if (userDropdown) userDropdown.classList.remove('active');
        if (userAvatar) userAvatar.setAttribute('aria-expanded', 'false');
    });
    
    if(userDropdown) {
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
}

if (userNotificationsToggle && userNotificationsDropdown) {
    const notificationsEndpoint = '<?php echo BASE_URL; ?>/api/get_notifications.php?per_page=6&status=all';
    const markNotificationReadEndpoint = '<?php echo BASE_URL; ?>/api/mark_notification_read.php';
    const markAllNotificationsReadEndpoint = '<?php echo BASE_URL; ?>/api/mark_all_notifications_read.php';
    const notificationsAllUrl = <?php echo json_encode((string) $notifications_all_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const notificationsUnreadUrl = <?php echo json_encode((string) $notifications_unread_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    let currentNotificationUnreadCount = <?php echo (int) $user_notification_count; ?>;

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatNotificationCount(count) {
        return count > 99 ? '99+' : String(count);
    }

    function setNotificationUnreadCount(count) {
        currentNotificationUnreadCount = Math.max(0, parseInt(count || 0, 10));
        if (userNotificationBadge) {
            userNotificationBadge.textContent = formatNotificationCount(currentNotificationUnreadCount);
            userNotificationBadge.classList.toggle('is-hidden', currentNotificationUnreadCount <= 0);
        }
        if (userNotificationStatusText) {
            userNotificationStatusText.textContent = currentNotificationUnreadCount > 0
                ? currentNotificationUnreadCount + ' unread notification' + (currentNotificationUnreadCount === 1 ? '' : 's')
                : 'All caught up';
        }
        if (markAllNotificationsReadBtn) {
            markAllNotificationsReadBtn.disabled = currentNotificationUnreadCount <= 0;
            markAllNotificationsReadBtn.classList.toggle('is-disabled', currentNotificationUnreadCount <= 0);
        }
    }

    function renderNotifications(items) {
        if (!userNotificationsList) {
            return;
        }

        if (!Array.isArray(items) || items.length === 0) {
            userNotificationsList.innerHTML = '<div class="nex-notification-empty"><i class="fas fa-bell-slash"></i><span>No notifications yet.</span></div>';
            return;
        }

        userNotificationsList.innerHTML = items.map(function (item) {
            const href = item && item.action_url ? item.action_url : notificationsUnreadUrl;
            const unread = !item || !item.is_read;
            return '<a href="' + escapeHtml(href) + '" class="nex-notification-link ' + (unread ? 'is-unread' : '') + '" data-notification-id="' + parseInt(item.id || 0, 10) + '" data-notification-read="' + (unread ? '0' : '1') + '">' +
                '<span class="nex-notification-icon-wrap"><i class="fas fa-circle-info"></i></span>' +
                '<span class="nex-dropdown-link-meta">' +
                    '<span class="nex-notification-line">' +
                        '<span class="nex-dropdown-link-label">' + escapeHtml(item.title || 'Notification') + '</span>' +
                        (unread ? '<span class="nex-notification-dot"></span>' : '') +
                    '</span>' +
                    '<small>' + escapeHtml(item.message || '') + '</small>' +
                    '<em>' + escapeHtml(item.time_ago || '') + '</em>' +
                '</span>' +
            '</a>';
        }).join('');
    }

    function markNotificationLinkAsRead(link) {
        if (!link || !link.classList.contains('is-unread')) {
            return;
        }

        link.classList.remove('is-unread');
        link.setAttribute('data-notification-read', '1');
        const dot = link.querySelector('.nex-notification-dot');
        if (dot) {
            dot.remove();
        }
        setNotificationUnreadCount(currentNotificationUnreadCount - 1);
    }

    function markAllNotificationLinksAsRead() {
        if (!userNotificationsList) {
            return;
        }

        userNotificationsList.querySelectorAll('.nex-notification-link.is-unread').forEach(function (link) {
            link.classList.remove('is-unread');
            link.setAttribute('data-notification-read', '1');
        });

        userNotificationsList.querySelectorAll('.nex-notification-dot').forEach(function (dot) {
            dot.remove();
        });
    }

    function fetchNotifications() {
        if (userNotificationsList) {
            userNotificationsList.classList.add('is-loading');
        }

        return fetch(notificationsEndpoint, {
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (!data || data.success !== true) {
                throw new Error((data && data.message) || 'Failed to load notifications.');
            }
            renderNotifications(data.items || []);
            setNotificationUnreadCount(typeof data.unread_count !== 'undefined' ? data.unread_count : 0);
            return data;
        }).catch(function () {
            return null;
        }).finally(function () {
            if (userNotificationsList) {
                userNotificationsList.classList.remove('is-loading');
            }
        });
    }

    function sendMarkNotificationRequest(notificationId) {
        const body = new URLSearchParams();
        body.set('notification_id', String(notificationId));
        return fetch(markNotificationReadEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin',
            keepalive: true
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (data && data.success) {
                if (typeof data.unread_count !== 'undefined') {
                    setNotificationUnreadCount(data.unread_count);
                }
                return data;
            }
            throw new Error((data && data.message) || 'Failed to update notification.');
        });
    }

    userNotificationsToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        const willOpen = !userNotificationsDropdown.classList.contains('active');
        userNotificationsDropdown.classList.toggle('active');
        userNotificationsToggle.setAttribute('aria-expanded', userNotificationsDropdown.classList.contains('active') ? 'true' : 'false');
        userNotificationsToggle.classList.toggle('is-active', userNotificationsDropdown.classList.contains('active'));

        if (willOpen) {
            fetchNotifications();
        }
    });

    document.addEventListener('click', function() {
        if (userNotificationsDropdown) userNotificationsDropdown.classList.remove('active');
        if (userNotificationsToggle) userNotificationsToggle.setAttribute('aria-expanded', 'false');
        if (userNotificationsToggle) userNotificationsToggle.classList.remove('is-active');
    });

    userNotificationsDropdown.addEventListener('click', function(e) {
        e.stopPropagation();

        const notificationLink = e.target.closest('.nex-notification-link');
        if (!notificationLink) {
            return;
        }

        const id = parseInt(notificationLink.getAttribute('data-notification-id') || '0', 10);
        const isUnread = notificationLink.getAttribute('data-notification-read') !== '1';

        if (!id || !isUnread) {
            return;
        }

        markNotificationLinkAsRead(notificationLink);
        sendMarkNotificationRequest(id).catch(function() {
            fetchNotifications();
        });
    });

    if (markAllNotificationsReadBtn) {
        markAllNotificationsReadBtn.addEventListener('click', function(e) {
            e.preventDefault();

            if (markAllNotificationsReadBtn.disabled) {
                return;
            }

            fetch(markAllNotificationsReadEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: '',
                credentials: 'same-origin'
            }).then(function(response) {
                return response.json();
            }).then(function(data) {
                if (!data || data.success !== true) {
                    throw new Error((data && data.message) || 'Failed to mark all notifications as read.');
                }
                markAllNotificationLinksAsRead();
                setNotificationUnreadCount(typeof data.unread_count !== 'undefined' ? data.unread_count : 0);
            }).catch(function() {
                fetchNotifications();
            });
        });
    }

    window.addEventListener('focus', function() {
        fetchNotifications();
    });

    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            fetchNotifications();
        }
    });

    window.setInterval(function() {
        if (document.visibilityState === 'visible') {
            fetchNotifications();
        }
    }, 60000);

    setNotificationUnreadCount(currentNotificationUnreadCount);
}

// Scroll effect for top navbar
window.addEventListener('scroll', function() {
    const nav = document.querySelector('.nex-nav');
    if (!nav) return;
    if (window.scrollY > 50) {
        nav.classList.add('scrolled');
    } else {
        nav.classList.remove('scrolled');
    }
});
</script>
