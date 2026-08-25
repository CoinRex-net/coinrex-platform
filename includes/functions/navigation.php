<?php
/**
 * Navigation management helpers for shared header/footer/mobile menus.
 */

function getDefaultNavigationControls(): array {
    return [
        'header_home_guest' => [
            'nav_key' => 'header_home_guest',
            'location' => 'header',
            'section_key' => 'primary',
            'section_label' => 'Header Primary',
            'label' => 'Home',
            'route_key' => 'home',
            'icon_class' => 'fas fa-home',
            'badge_text' => '',
            'sort_order' => 10,
            'is_enabled' => 1,
            'audience' => 'guest',
            'admin_hint' => 'Guest users see this as the first desktop navigation item.',
            'admin_route_hint' => BASE_URL . '/index.php',
            'active_pages' => ['index', 'home'],
        ],
        'header_dashboard_member' => [
            'nav_key' => 'header_dashboard_member',
            'location' => 'header',
            'section_key' => 'primary',
            'section_label' => 'Header Primary',
            'label' => 'RexHub',
            'route_key' => 'dashboard',
            'icon_class' => 'fas fa-gem',
            'badge_text' => '',
            'sort_order' => 10,
            'is_enabled' => 1,
            'audience' => 'member',
            'feature_key' => 'dashboard',
            'admin_hint' => 'Signed-in users see this dashboard shortcut instead of Home.',
            'admin_route_hint' => BASE_URL . '/public/dashboard.php',
            'active_pages' => ['dashboard'],
        ],
        'header_projects' => [
            'nav_key' => 'header_projects',
            'location' => 'header',
            'section_key' => 'primary',
            'section_label' => 'Header Primary',
            'label' => 'Projects',
            'route_key' => 'projects',
            'icon_class' => 'fas fa-chart-line',
            'badge_text' => 'Soon',
            'sort_order' => 20,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'projects',
            'admin_hint' => 'Public projects link in the desktop header.',
            'admin_route_hint' => BASE_URL . '/public/projects.php',
            'active_pages' => ['projects', 'project-detail'],
        ],
        'header_learnhub' => [
            'nav_key' => 'header_learnhub',
            'location' => 'header',
            'section_key' => 'primary',
            'section_label' => 'Header Primary',
            'label' => 'LearnHub',
            'route_key' => 'learnhub',
            'icon_class' => 'fas fa-list-check',
            'badge_text' => 'HOT',
            'sort_order' => 30,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'learnhub',
            'admin_hint' => 'Auto-hides for Pro/Expert users or when today mission is already completed.',
            'admin_route_hint' => BASE_URL . '/public/taskhub.php',
            'active_pages' => ['taskhub'],
        ],
        'header_boosthub' => [
            'nav_key' => 'header_boosthub',
            'location' => 'header',
            'section_key' => 'primary',
            'section_label' => 'Header Primary',
            'label' => 'BoostHub',
            'route_key' => 'boosthub',
            'icon_class' => 'fas fa-bolt',
            'badge_text' => 'NEW',
            'sort_order' => 40,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'boosthub',
            'admin_hint' => 'Daily boost tasks link in desktop header.',
            'admin_route_hint' => BASE_URL . '/public/boosthub.php',
            'active_pages' => ['boosthub'],
        ],
        'header_leaderboard' => [
            'nav_key' => 'header_leaderboard',
            'location' => 'header',
            'section_key' => 'primary',
            'section_label' => 'Header Primary',
            'label' => 'Leaderboard',
            'route_key' => 'leaderboard',
            'icon_class' => 'fas fa-trophy',
            'badge_text' => '',
            'sort_order' => 50,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'leaderboard',
            'admin_hint' => 'Public leaderboard link in the desktop header.',
            'admin_route_hint' => BASE_URL . '/public/leaderboard.php',
            'active_pages' => ['leaderboard'],
        ],
        'header_resources_menu' => [
            'nav_key' => 'header_resources_menu',
            'location' => 'header',
            'section_key' => 'primary',
            'section_label' => 'Header Primary',
            'label' => 'Resources',
            'route_key' => '',
            'icon_class' => 'fas fa-layer-group',
            'badge_text' => '',
            'sort_order' => 25,
            'is_enabled' => 1,
            'audience' => 'all',
            'item_type' => 'dropdown',
            'children_section_key' => 'resources',
            'admin_hint' => 'Opens the Resources dropdown in the primary header. Children are managed in the Header Resources section.',
            'admin_route_hint' => 'Dropdown only',
            'active_pages' => ['roadmap', 'litepaper', 'blog', 'blog-post', 'blog-category', 'blog-tag', 'about'],
        ],
        'header_marketplace_menu' => [
            'nav_key' => 'header_marketplace_menu',
            'location' => 'header',
            'section_key' => 'primary',
            'section_label' => 'Header Primary',
            'label' => 'Marketplace',
            'route_key' => '',
            'icon_class' => 'fas fa-store',
            'badge_text' => '',
            'sort_order' => 22,
            'is_enabled' => 1,
            'audience' => 'all',
            'item_type' => 'dropdown',
            'children_section_key' => 'marketplace',
            'admin_hint' => 'Disabled by default. Opens a marketplace dropdown in the primary header.',
            'admin_route_hint' => 'Dropdown only',
            'active_pages' => ['projects', 'project-detail', 'reviews', 'my-reviews', 'submit-review'],
        ],
        'header_marketplace_reviews' => [
            'nav_key' => 'header_marketplace_reviews',
            'location' => 'header',
            'section_key' => 'marketplace',
            'section_label' => 'Header Marketplace',
            'label' => 'Reviews',
            'route_key' => 'reviews',
            'icon_class' => 'fas fa-star',
            'badge_text' => '',
            'sort_order' => 10,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'reviews',
            'admin_hint' => 'Marketplace dropdown link.',
            'admin_route_hint' => BASE_URL . '/public/reviews.php',
            'active_pages' => ['reviews', 'my-reviews', 'submit-review'],
        ],
        'header_marketplace_project' => [
            'nav_key' => 'header_marketplace_project',
            'location' => 'header',
            'section_key' => 'marketplace',
            'section_label' => 'Header Marketplace',
            'label' => 'Projects',
            'route_key' => 'projects',
            'icon_class' => 'fas fa-chart-line',
            'badge_text' => 'Soon',
            'sort_order' => 20,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'projects',
            'admin_hint' => 'Marketplace dropdown link.',
            'admin_route_hint' => BASE_URL . '/public/projects.php',
            'active_pages' => ['projects', 'project-detail'],
        ],
        'header_marketplace_devhub' => [
            'nav_key' => 'header_marketplace_devhub',
            'location' => 'header',
            'section_key' => 'marketplace',
            'section_label' => 'Header Marketplace',
            'label' => 'DevHub',
            'route_key' => 'devhub',
            'icon_class' => 'fas fa-code',
            'badge_text' => '',
            'sort_order' => 30,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Marketplace dropdown link.',
            'admin_route_hint' => BASE_URL . '/devhub/index.php',
            'active_pages' => [],
        ],
        'header_resource_roadmap' => [
            'nav_key' => 'header_resource_roadmap',
            'location' => 'header',
            'section_key' => 'resources',
            'section_label' => 'Header Resources',
            'label' => 'Roadmap',
            'route_key' => 'roadmap',
            'icon_class' => 'fas fa-route',
            'badge_text' => '',
            'sort_order' => 10,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Resources dropdown item.',
            'admin_route_hint' => BASE_URL . '/public/roadmap.php',
            'active_pages' => ['roadmap'],
        ],
        'header_resource_litepaper' => [
            'nav_key' => 'header_resource_litepaper',
            'location' => 'header',
            'section_key' => 'resources',
            'section_label' => 'Header Resources',
            'label' => 'Litepaper',
            'route_key' => 'litepaper',
            'icon_class' => 'fas fa-file-alt',
            'badge_text' => '',
            'sort_order' => 20,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Resources dropdown item.',
            'admin_route_hint' => BASE_URL . '/public/litepaper.php',
            'active_pages' => ['litepaper'],
        ],
        'header_resource_blog' => [
            'nav_key' => 'header_resource_blog',
            'location' => 'header',
            'section_key' => 'resources',
            'section_label' => 'Header Resources',
            'label' => 'Blog',
            'route_key' => 'blog',
            'icon_class' => 'fas fa-blog',
            'badge_text' => '',
            'sort_order' => 30,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Resources dropdown item.',
            'admin_route_hint' => BASE_URL . '/public/blog.php',
            'active_pages' => ['blog', 'blog-post', 'blog-category', 'blog-tag'],
        ],
        'header_resource_about' => [
            'nav_key' => 'header_resource_about',
            'location' => 'header',
            'section_key' => 'resources',
            'section_label' => 'Header Resources',
            'label' => 'About Us',
            'route_key' => 'about',
            'icon_class' => 'fas fa-circle-info',
            'badge_text' => '',
            'sort_order' => 40,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Resources dropdown item.',
            'admin_route_hint' => BASE_URL . '/public/about.php',
            'active_pages' => ['about'],
        ],
        'header_wallet' => [
            'nav_key' => 'header_wallet',
            'location' => 'header',
            'section_key' => 'primary',
            'section_label' => 'Header Primary',
            'label' => 'Wallet',
            'route_key' => 'wallet',
            'icon_class' => 'fas fa-wallet',
            'badge_text' => '',
            'sort_order' => 35,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'RexLink wallet download and access page.',
            'admin_route_hint' => BASE_URL . '/wallet.php',
            'active_pages' => ['wallet'],
        ],
        'footer_platform_wallet' => [
            'nav_key' => 'footer_platform_wallet',
            'location' => 'footer',
            'section_key' => 'platform',
            'section_label' => 'Footer Platform',
            'label' => 'Wallet',
            'route_key' => 'wallet',
            'icon_class' => 'fas fa-wallet',
            'badge_text' => '',
            'sort_order' => 15,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'RexLink wallet link in the footer platform section.',
            'admin_route_hint' => BASE_URL . '/wallet.php',
            'active_pages' => ['wallet'],
        ],
        'footer_platform_home' => [
            'nav_key' => 'footer_platform_home',
            'location' => 'footer',
            'section_key' => 'platform',
            'section_label' => 'Footer Platform',
            'label' => 'Home',
            'route_key' => 'home',
            'icon_class' => 'fas fa-home',
            'badge_text' => '',
            'sort_order' => 10,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Platform links column in footer.',
            'admin_route_hint' => BASE_URL . '/index.php',
            'active_pages' => ['index', 'home'],
        ],
        'footer_platform_projects' => [
            'nav_key' => 'footer_platform_projects',
            'location' => 'footer',
            'section_key' => 'platform',
            'section_label' => 'Footer Platform',
            'label' => 'Projects',
            'route_key' => 'projects',
            'icon_class' => 'fas fa-chart-line',
            'badge_text' => 'Soon',
            'sort_order' => 20,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'projects',
            'admin_hint' => 'Platform links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/projects.php',
            'active_pages' => ['projects', 'project-detail'],
        ],
        'footer_platform_reviews' => [
            'nav_key' => 'footer_platform_reviews',
            'location' => 'footer',
            'section_key' => 'platform',
            'section_label' => 'Footer Platform',
            'label' => 'Reviews',
            'route_key' => 'reviews',
            'icon_class' => 'fas fa-star',
            'badge_text' => '',
            'sort_order' => 30,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'reviews',
            'admin_hint' => 'Platform links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/reviews.php',
            'active_pages' => ['reviews', 'my-reviews', 'submit-review'],
        ],
        'footer_platform_leaderboard' => [
            'nav_key' => 'footer_platform_leaderboard',
            'location' => 'footer',
            'section_key' => 'platform',
            'section_label' => 'Footer Platform',
            'label' => 'Leaderboard',
            'route_key' => 'leaderboard',
            'icon_class' => 'fas fa-trophy',
            'badge_text' => '',
            'sort_order' => 40,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'leaderboard',
            'admin_hint' => 'Platform links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/leaderboard.php',
            'active_pages' => ['leaderboard'],
        ],
        'footer_platform_devhub' => [
            'nav_key' => 'footer_platform_devhub',
            'location' => 'footer',
            'section_key' => 'platform',
            'section_label' => 'Footer Platform',
            'label' => 'Dev Hub',
            'route_key' => 'devhub',
            'icon_class' => 'fas fa-code',
            'badge_text' => '',
            'sort_order' => 50,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Only renders when DevHub is visible through launch controls.',
            'admin_route_hint' => BASE_URL . '/devhub/index.php',
            'active_pages' => [],
        ],
        'footer_resource_about' => [
            'nav_key' => 'footer_resource_about',
            'location' => 'footer',
            'section_key' => 'resources',
            'section_label' => 'Footer Resources',
            'label' => 'About Us',
            'route_key' => 'about',
            'icon_class' => 'fas fa-info-circle',
            'badge_text' => '',
            'sort_order' => 10,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Resources links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/about.php',
            'active_pages' => ['about'],
        ],
        'footer_resource_litepaper' => [
            'nav_key' => 'footer_resource_litepaper',
            'location' => 'footer',
            'section_key' => 'resources',
            'section_label' => 'Footer Resources',
            'label' => 'Litepaper',
            'route_key' => 'litepaper',
            'icon_class' => 'fas fa-file-alt',
            'badge_text' => '',
            'sort_order' => 20,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Resources links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/litepaper.php',
            'active_pages' => ['litepaper'],
        ],
        'footer_resource_roadmap' => [
            'nav_key' => 'footer_resource_roadmap',
            'location' => 'footer',
            'section_key' => 'resources',
            'section_label' => 'Footer Resources',
            'label' => 'Roadmap',
            'route_key' => 'roadmap',
            'icon_class' => 'fas fa-route',
            'badge_text' => '',
            'sort_order' => 30,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Resources links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/roadmap.php',
            'active_pages' => ['roadmap'],
        ],
        'footer_resource_faq' => [
            'nav_key' => 'footer_resource_faq',
            'location' => 'footer',
            'section_key' => 'resources',
            'section_label' => 'Footer Resources',
            'label' => 'FAQ',
            'route_key' => 'faq',
            'icon_class' => 'fas fa-question-circle',
            'badge_text' => '',
            'sort_order' => 40,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Resources links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/faq.php',
            'active_pages' => ['faq'],
        ],
        'footer_resource_contact' => [
            'nav_key' => 'footer_resource_contact',
            'location' => 'footer',
            'section_key' => 'resources',
            'section_label' => 'Footer Resources',
            'label' => 'Contact',
            'route_key' => 'contact',
            'icon_class' => 'fas fa-envelope',
            'badge_text' => '',
            'sort_order' => 50,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Resources links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/contact.php',
            'active_pages' => ['contact'],
        ],
        'footer_resource_blog' => [
            'nav_key' => 'footer_resource_blog',
            'location' => 'footer',
            'section_key' => 'resources',
            'section_label' => 'Footer Resources',
            'label' => 'Blog',
            'route_key' => 'blog',
            'icon_class' => 'fas fa-blog',
            'badge_text' => '',
            'sort_order' => 60,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Resources links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/blog.php',
            'active_pages' => ['blog', 'blog-post', 'blog-category', 'blog-tag'],
        ],
        'footer_legal_terms' => [
            'nav_key' => 'footer_legal_terms',
            'location' => 'footer',
            'section_key' => 'legal',
            'section_label' => 'Footer Legal',
            'label' => 'Terms of Service',
            'route_key' => 'terms',
            'icon_class' => 'fas fa-file-contract',
            'badge_text' => '',
            'sort_order' => 10,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Legal links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/terms.php',
            'active_pages' => ['terms'],
        ],
        'footer_legal_privacy' => [
            'nav_key' => 'footer_legal_privacy',
            'location' => 'footer',
            'section_key' => 'legal',
            'section_label' => 'Footer Legal',
            'label' => 'Privacy Policy',
            'route_key' => 'privacy',
            'icon_class' => 'fas fa-shield-alt',
            'badge_text' => '',
            'sort_order' => 20,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Legal links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/privacy.php',
            'active_pages' => ['privacy'],
        ],
        'footer_legal_cookies' => [
            'nav_key' => 'footer_legal_cookies',
            'location' => 'footer',
            'section_key' => 'legal',
            'section_label' => 'Footer Legal',
            'label' => 'Cookie Policy',
            'route_key' => 'cookies',
            'icon_class' => 'fas fa-cookie-bite',
            'badge_text' => '',
            'sort_order' => 30,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Legal links column in footer.',
            'admin_route_hint' => BASE_URL . '/public/cookies.php',
            'active_pages' => ['cookies'],
        ],
        'footer_bottom_support' => [
            'nav_key' => 'footer_bottom_support',
            'location' => 'footer',
            'section_key' => 'bottom',
            'section_label' => 'Footer Bottom',
            'label' => 'Support',
            'route_key' => 'contact',
            'icon_class' => '',
            'badge_text' => '',
            'sort_order' => 10,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Bottom utility links row in footer.',
            'admin_route_hint' => BASE_URL . '/public/contact.php',
            'active_pages' => ['contact'],
        ],
        'footer_bottom_status' => [
            'nav_key' => 'footer_bottom_status',
            'location' => 'footer',
            'section_key' => 'bottom',
            'section_label' => 'Footer Bottom',
            'label' => 'Status',
            'route_key' => 'roadmap',
            'icon_class' => '',
            'badge_text' => '',
            'sort_order' => 20,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Bottom utility links row in footer.',
            'admin_route_hint' => BASE_URL . '/public/roadmap.php',
            'active_pages' => ['roadmap'],
        ],
        'footer_bottom_api' => [
            'nav_key' => 'footer_bottom_api',
            'location' => 'footer',
            'section_key' => 'bottom',
            'section_label' => 'Footer Bottom',
            'label' => 'API',
            'route_key' => 'devhub_api',
            'icon_class' => '',
            'badge_text' => '',
            'sort_order' => 30,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Visible only when the full DevHub panel is live.',
            'admin_route_hint' => BASE_URL . '/devhub/widget-api.php',
            'active_pages' => [],
        ],
        'mobile_learnhub' => [
            'nav_key' => 'mobile_learnhub',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'LearnHub',
            'route_key' => 'learnhub',
            'icon_class' => 'fas fa-list-check',
            'badge_text' => 'HOT',
            'sort_order' => 10,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'learnhub',
            'admin_hint' => 'Primary mobile task tab. Falls back to Leaders when LearnHub should hide.',
            'admin_route_hint' => BASE_URL . '/public/taskhub.php',
            'active_pages' => ['taskhub'],
        ],
        'mobile_leaderboard_fallback' => [
            'nav_key' => 'mobile_leaderboard_fallback',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Leaders',
            'route_key' => 'leaderboard',
            'icon_class' => 'fas fa-trophy',
            'badge_text' => '',
            'sort_order' => 11,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'leaderboard',
            'admin_hint' => 'Only appears when LearnHub is not supposed to render.',
            'admin_route_hint' => BASE_URL . '/public/leaderboard.php',
            'active_pages' => ['leaderboard'],
        ],
        'mobile_leaderboard' => [
            'nav_key' => 'mobile_leaderboard',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Leaderboard',
            'route_key' => 'leaderboard',
            'icon_class' => 'fas fa-trophy',
            'badge_text' => '',
            'sort_order' => 15,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'leaderboard',
            'admin_hint' => 'Fixed mobile bottom nav leaderboard item.',
            'admin_route_hint' => BASE_URL . '/public/leaderboard.php',
            'active_pages' => ['leaderboard'],
        ],
        'mobile_boosthub' => [
            'nav_key' => 'mobile_boosthub',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'BoostHub',
            'route_key' => 'boosthub',
            'icon_class' => 'fas fa-bolt',
            'badge_text' => 'NEW',
            'sort_order' => 20,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'boosthub',
            'admin_hint' => 'Mobile boost tasks tab.',
            'admin_route_hint' => BASE_URL . '/public/boosthub.php',
            'active_pages' => ['boosthub'],
        ],
        'mobile_home_guest' => [
            'nav_key' => 'mobile_home_guest',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Home',
            'route_key' => 'home',
            'icon_class' => 'fas fa-home',
            'badge_text' => '',
            'sort_order' => 30,
            'is_enabled' => 1,
            'audience' => 'guest',
            'admin_hint' => 'Guest home shortcut in mobile bottom nav.',
            'admin_route_hint' => BASE_URL . '/index.php',
            'active_pages' => ['index', 'home'],
        ],
        'mobile_home_member' => [
            'nav_key' => 'mobile_home_member',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Home',
            'route_key' => 'dashboard',
            'icon_class' => 'fas fa-home',
            'badge_text' => '',
            'sort_order' => 30,
            'is_enabled' => 1,
            'audience' => 'member',
            'feature_key' => 'dashboard',
            'admin_hint' => 'Signed-in home shortcut in mobile bottom nav.',
            'admin_route_hint' => BASE_URL . '/public/dashboard.php',
            'active_pages' => ['dashboard'],
        ],
        'mobile_projects_member' => [
            'nav_key' => 'mobile_projects_member',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Projects',
            'route_key' => 'projects',
            'icon_class' => 'fas fa-chart-line',
            'badge_text' => 'Soon',
            'sort_order' => 40,
            'is_enabled' => 1,
            'audience' => 'member',
            'feature_key' => 'projects',
            'admin_hint' => 'Signed-in mobile nav item.',
            'admin_route_hint' => BASE_URL . '/public/projects.php',
            'active_pages' => ['projects', 'project-detail'],
        ],
        'mobile_reviews_member' => [
            'nav_key' => 'mobile_reviews_member',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Reviews',
            'route_key' => 'reviews',
            'icon_class' => 'fas fa-star',
            'badge_text' => '',
            'sort_order' => 50,
            'is_enabled' => 1,
            'audience' => 'member',
            'feature_key' => 'reviews',
            'admin_hint' => 'Signed-in mobile nav item.',
            'admin_route_hint' => BASE_URL . '/public/reviews.php',
            'active_pages' => ['reviews', 'my-reviews', 'submit-review'],
        ],
        'mobile_rewards_member' => [
            'nav_key' => 'mobile_rewards_member',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Rewards',
            'route_key' => 'reward_history',
            'icon_class' => 'fas fa-clock-rotate-left',
            'badge_text' => '',
            'sort_order' => 60,
            'is_enabled' => 1,
            'audience' => 'member',
            'admin_hint' => 'Signed-in MVP-safe mobile shortcut. Useful when marketplace links are hidden.',
            'admin_route_hint' => BASE_URL . '/public/reward-history.php',
            'active_pages' => ['reward-history'],
        ],
        'mobile_profile_member' => [
            'nav_key' => 'mobile_profile_member',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Profile',
            'route_key' => 'profile',
            'icon_class' => 'fas fa-id-badge',
            'badge_text' => '',
            'sort_order' => 70,
            'is_enabled' => 1,
            'audience' => 'member',
            'admin_hint' => 'Signed-in MVP-safe mobile shortcut. Helps keep the bottom nav at 5 slots during launch.',
            'admin_route_hint' => BASE_URL . '/public/profile.php',
            'active_pages' => ['profile'],
        ],
        'mobile_litepaper_guest' => [
            'nav_key' => 'mobile_litepaper_guest',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Litepaper',
            'route_key' => 'litepaper',
            'icon_class' => 'fas fa-file-alt',
            'badge_text' => '',
            'sort_order' => 40,
            'is_enabled' => 1,
            'audience' => 'guest',
            'admin_hint' => 'Guest mobile nav item.',
            'admin_route_hint' => BASE_URL . '/public/litepaper.php',
            'active_pages' => ['litepaper'],
        ],
        'mobile_litepaper_member' => [
            'nav_key' => 'mobile_litepaper_member',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'LitePaper',
            'route_key' => 'litepaper',
            'icon_class' => 'fas fa-file-alt',
            'badge_text' => '',
            'sort_order' => 80,
            'is_enabled' => 1,
            'audience' => 'member',
            'admin_hint' => 'Signed-in mobile nav item.',
            'admin_route_hint' => BASE_URL . '/public/litepaper.php',
            'active_pages' => ['litepaper'],
        ],
        'mobile_roadmap_guest' => [
            'nav_key' => 'mobile_roadmap_guest',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Roadmap',
            'route_key' => 'roadmap',
            'icon_class' => 'fas fa-route',
            'badge_text' => '',
            'sort_order' => 50,
            'is_enabled' => 1,
            'audience' => 'guest',
            'admin_hint' => 'Guest mobile nav item.',
            'admin_route_hint' => BASE_URL . '/public/roadmap.php',
            'active_pages' => ['roadmap'],
        ],
        'mobile_roadmap_member' => [
            'nav_key' => 'mobile_roadmap_member',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Roadmap',
            'route_key' => 'roadmap',
            'icon_class' => 'fas fa-route',
            'badge_text' => '',
            'sort_order' => 90,
            'is_enabled' => 1,
            'audience' => 'member',
            'admin_hint' => 'Signed-in mobile nav item.',
            'admin_route_hint' => BASE_URL . '/public/roadmap.php',
            'active_pages' => ['roadmap'],
        ],
        'mobile_wallet' => [
            'nav_key' => 'mobile_wallet',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'Wallet',
            'route_key' => 'wallet',
            'icon_class' => 'fas fa-wallet',
            'badge_text' => '',
            'sort_order' => 35,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Wallet link in the four direct mobile navigation slots.',
            'admin_route_hint' => BASE_URL . '/wallet.php',
            'active_pages' => ['wallet'],
        ],
        'mobile_about_guest' => [
            'nav_key' => 'mobile_about_guest',
            'location' => 'mobile',
            'section_key' => 'bottom',
            'section_label' => 'Mobile Bottom Nav',
            'label' => 'About',
            'route_key' => 'about',
            'icon_class' => 'fas fa-circle-info',
            'badge_text' => '',
            'sort_order' => 60,
            'is_enabled' => 1,
            'audience' => 'guest',
            'admin_hint' => 'Guest mobile nav item.',
            'admin_route_hint' => BASE_URL . '/public/about.php',
            'active_pages' => ['about'],
        ],
        // ============================================
        // MOBILE "MORE" FLOATING MENU ITEMS
        // Managed from admin Launch Control -> More Menu
        // ============================================
        'mobile_more_wallet' => [
            'nav_key' => 'mobile_more_wallet',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Wallet',
            'route_key' => 'wallet',
            'icon_class' => 'fas fa-wallet',
            'badge_text' => '',
            'sort_order' => 60,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Wallet link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/wallet.php',
            'active_pages' => ['wallet'],
        ],
        'mobile_more_home' => [
            'nav_key' => 'mobile_more_home',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Home',
            'route_key' => 'home',
            'icon_class' => 'fas fa-home',
            'badge_text' => '',
            'sort_order' => 50,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Home link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/index.php',
            'active_pages' => ['index', 'home'],
        ],
        'mobile_more_projects' => [
            'nav_key' => 'mobile_more_projects',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Projects',
            'route_key' => 'projects',
            'icon_class' => 'fas fa-chart-line',
            'badge_text' => 'Soon',
            'sort_order' => 10,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'projects',
            'admin_hint' => 'Projects link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/projects.php',
            'active_pages' => ['projects', 'project-detail'],
        ],
        'mobile_more_reviews' => [
            'nav_key' => 'mobile_more_reviews',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Reviews',
            'route_key' => 'reviews',
            'icon_class' => 'fas fa-star',
            'badge_text' => '',
            'sort_order' => 20,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'reviews',
            'admin_hint' => 'Reviews link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/reviews.php',
            'active_pages' => ['reviews', 'my-reviews', 'submit-review'],
        ],
        'mobile_more_leaderboard' => [
            'nav_key' => 'mobile_more_leaderboard',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Leaderboard',
            'route_key' => 'leaderboard',
            'icon_class' => 'fas fa-trophy',
            'badge_text' => '',
            'sort_order' => 30,
            'is_enabled' => 1,
            'audience' => 'all',
            'feature_key' => 'leaderboard',
            'admin_hint' => 'Leaderboard link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/leaderboard.php',
            'active_pages' => ['leaderboard'],
        ],
        'mobile_more_claim_center' => [
            'nav_key' => 'mobile_more_claim_center',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Claim Center',
            'route_key' => 'claim_center',
            'icon_class' => 'fas fa-gift',
            'badge_text' => '',
            'sort_order' => 50,
            'is_enabled' => 1,
            'audience' => 'member',
            'feature_key' => 'claim_center',
            'admin_hint' => 'Claim Center link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/claims.php',
            'active_pages' => ['claims'],
        ],
        'mobile_more_rewards' => [
            'nav_key' => 'mobile_more_rewards',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Rewards',
            'route_key' => 'reward_history',
            'icon_class' => 'fas fa-clock-rotate-left',
            'badge_text' => '',
            'sort_order' => 60,
            'is_enabled' => 1,
            'audience' => 'member',
            'admin_hint' => 'Reward history link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/reward-history.php',
            'active_pages' => ['reward-history'],
        ],
        'mobile_more_profile' => [
            'nav_key' => 'mobile_more_profile',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Profile',
            'route_key' => 'profile',
            'icon_class' => 'fas fa-id-badge',
            'badge_text' => '',
            'sort_order' => 70,
            'is_enabled' => 1,
            'audience' => 'member',
            'admin_hint' => 'Profile link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/profile.php',
            'active_pages' => ['profile'],
        ],
        'mobile_more_notifications' => [
            'nav_key' => 'mobile_more_notifications',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Notifications',
            'route_key' => 'notifications',
            'icon_class' => 'fas fa-bell',
            'badge_text' => '',
            'sort_order' => 80,
            'is_enabled' => 1,
            'audience' => 'member',
            'admin_hint' => 'Notifications link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/notifications.php',
            'active_pages' => ['notifications'],
        ],
        'mobile_more_litepaper' => [
            'nav_key' => 'mobile_more_litepaper',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Litepaper',
            'route_key' => 'litepaper',
            'icon_class' => 'fas fa-file-alt',
            'badge_text' => '',
            'sort_order' => 90,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Litepaper link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/litepaper.php',
            'active_pages' => ['litepaper'],
        ],
        'mobile_more_roadmap' => [
            'nav_key' => 'mobile_more_roadmap',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Roadmap',
            'route_key' => 'roadmap',
            'icon_class' => 'fas fa-route',
            'badge_text' => '',
            'sort_order' => 100,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Roadmap link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/roadmap.php',
            'active_pages' => ['roadmap'],
        ],
        'mobile_more_about' => [
            'nav_key' => 'mobile_more_about',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'About',
            'route_key' => 'about',
            'icon_class' => 'fas fa-circle-info',
            'badge_text' => '',
            'sort_order' => 40,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'About link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/about.php',
            'active_pages' => ['about'],
        ],
        'mobile_more_contact' => [
            'nav_key' => 'mobile_more_contact',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Contact',
            'route_key' => 'contact',
            'icon_class' => 'fas fa-envelope',
            'badge_text' => '',
            'sort_order' => 120,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Contact link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/contact.php',
            'active_pages' => ['contact'],
        ],
        'mobile_more_blog' => [
            'nav_key' => 'mobile_more_blog',
            'location' => 'mobile_more',
            'section_key' => 'more',
            'section_label' => 'Mobile More Menu',
            'label' => 'Blog',
            'route_key' => 'blog',
            'icon_class' => 'fas fa-blog',
            'badge_text' => '',
            'sort_order' => 130,
            'is_enabled' => 1,
            'audience' => 'all',
            'admin_hint' => 'Blog link inside the mobile More floating menu.',
            'admin_route_hint' => BASE_URL . '/public/blog.php',
            'active_pages' => ['blog', 'blog-post', 'blog-category', 'blog-tag'],
        ],
    ];
}

function ensureNavigationControlsSchema(PDO $db = null): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $db = $db ?: getDBConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS navigation_controls (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nav_key VARCHAR(120) NOT NULL,
            location VARCHAR(40) NOT NULL,
            section_key VARCHAR(60) NOT NULL DEFAULT 'default',
            label VARCHAR(120) NOT NULL,
            custom_url VARCHAR(500) NOT NULL DEFAULT '',
            icon_class VARCHAR(120) NOT NULL DEFAULT '',
            badge_text VARCHAR(40) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            audience VARCHAR(20) NOT NULL DEFAULT 'all',
            item_type VARCHAR(20) NOT NULL DEFAULT 'link',
            children_section_key VARCHAR(60) NOT NULL DEFAULT '',
            admin_hint VARCHAR(255) NOT NULL DEFAULT '',
            admin_route_hint VARCHAR(500) NOT NULL DEFAULT '',
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_navigation_controls_key (nav_key),
            KEY idx_navigation_controls_location (location),
            KEY idx_navigation_controls_section (section_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    coinrexEnsureNavigationColumn($db, 'audience', "ALTER TABLE navigation_controls ADD COLUMN audience VARCHAR(20) NOT NULL DEFAULT 'all' AFTER is_enabled");
    coinrexEnsureNavigationColumn($db, 'item_type', "ALTER TABLE navigation_controls ADD COLUMN item_type VARCHAR(20) NOT NULL DEFAULT 'link' AFTER audience");
    coinrexEnsureNavigationColumn($db, 'children_section_key', "ALTER TABLE navigation_controls ADD COLUMN children_section_key VARCHAR(60) NOT NULL DEFAULT '' AFTER item_type");
    coinrexEnsureNavigationColumn($db, 'admin_hint', "ALTER TABLE navigation_controls ADD COLUMN admin_hint VARCHAR(255) NOT NULL DEFAULT '' AFTER children_section_key");
    coinrexEnsureNavigationColumn($db, 'admin_route_hint', "ALTER TABLE navigation_controls ADD COLUMN admin_route_hint VARCHAR(500) NOT NULL DEFAULT '' AFTER admin_hint");
    coinrexEnsureNavigationColumn($db, 'is_system', "ALTER TABLE navigation_controls ADD COLUMN is_system TINYINT(1) NOT NULL DEFAULT 0 AFTER admin_route_hint");

    coinrexEnsureNavigationSlotsSchema($db);
    seedDefaultNavigationControls($db, false, false);
    $ensured = true;
}

function coinrexEnsureNavigationSlotsSchema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS navigation_slots (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            slot_group VARCHAR(80) NOT NULL,
            location VARCHAR(40) NOT NULL,
            section_key VARCHAR(60) NOT NULL,
            audience VARCHAR(20) NOT NULL DEFAULT 'all',
            user_level VARCHAR(20) NOT NULL DEFAULT 'all',
            slot_number INT NOT NULL,
            nav_key VARCHAR(120) NOT NULL DEFAULT '',
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_navigation_slots_group_slot_level (slot_group, slot_number, user_level),
            KEY idx_navigation_slots_lookup (location, section_key, audience, user_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    coinrexEnsureNavigationSlotColumn($db, 'user_level', "ALTER TABLE navigation_slots ADD COLUMN user_level VARCHAR(20) NOT NULL DEFAULT 'all' AFTER audience");

    try {
        $db->exec("ALTER TABLE navigation_slots DROP INDEX uq_navigation_slots_group_slot");
    } catch (Throwable $e) {
    }

    try {
        $db->exec("ALTER TABLE navigation_slots ADD UNIQUE KEY uq_navigation_slots_group_slot_level (slot_group, slot_number, user_level)");
    } catch (Throwable $e) {
    }
}

function coinrexEnsureNavigationColumn(PDO $db, string $column, string $ddl): void {
    $stmt = $db->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'navigation_controls'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$column]);
    if ($stmt->fetch()) {
        return;
    }

    $db->exec($ddl);
}

function coinrexEnsureNavigationSlotColumn(PDO $db, string $column, string $ddl): void {
    $stmt = $db->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'navigation_slots'
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$column]);
    if ($stmt->fetch()) {
        return;
    }

    $db->exec($ddl);
}

function coinrexLevelSlotGroup(string $baseSlotGroup, string $level): string {
    $level = strtolower(trim($level));
    if ($level === 'visitor') {
        return $baseSlotGroup . '_visitor';
    }

    $level = normalizeUserLevel($level);
    if ($level === 'beginner' || $level === '') {
        $level = 'beginner';
    }
    return $baseSlotGroup . '_' . $level;
}

function seedDefaultNavigationControls(PDO $db = null, bool $resetPresentation = false, bool $resetEnabled = false): void {
    $db = $db ?: getDBConnection();
    $defaults = getDefaultNavigationControls();

    foreach ($defaults as $key => $item) {
        $stmt = $db->prepare("SELECT id FROM navigation_controls WHERE nav_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $existingId = (int) ($stmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $fields = [
                'location = ?',
                'audience = ?',
                'item_type = ?',
                'children_section_key = ?',
                'admin_hint = ?',
                'admin_route_hint = ?',
                'is_system = 1',
            ];
            $params = [
                (string) $item['location'],
                (string) ($item['audience'] ?? 'all'),
                (string) ($item['item_type'] ?? 'link'),
                (string) ($item['children_section_key'] ?? ''),
                (string) ($item['admin_hint'] ?? ''),
                (string) ($item['admin_route_hint'] ?? ''),
            ];

            // Always enforce the correct plural label for marketplace project link.
            if ($key === 'header_marketplace_project') {
                $fields[] = 'label = ?';
                $params[] = 'Projects';
            }

            if ($resetPresentation) {
                $fields[] = 'section_key = ?';
                $fields[] = 'label = ?';
                $fields[] = 'custom_url = ?';
                $fields[] = 'icon_class = ?';
                $fields[] = 'badge_text = ?';
                $fields[] = 'sort_order = ?';
                $params[] = (string) $item['section_key'];
                $params[] = (string) $item['label'];
                $params[] = '';
                $params[] = (string) ($item['icon_class'] ?? '');
                $params[] = (string) ($item['badge_text'] ?? '');
                $params[] = (int) ($item['sort_order'] ?? 0);
            }

            if ($resetEnabled) {
                $fields[] = 'is_enabled = ?';
                $params[] = (int) ($item['is_enabled'] ?? 1);
            }

            $params[] = $key;
            $db->prepare('UPDATE navigation_controls SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE nav_key = ?')
                ->execute($params);
            continue;
        }

        $insert = $db->prepare("
            INSERT INTO navigation_controls (
                nav_key, location, section_key, label, custom_url, icon_class, badge_text, sort_order, is_enabled
                , audience, item_type, children_section_key, admin_hint, admin_route_hint, is_system
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $insert->execute([
            $key,
            (string) $item['location'],
            (string) $item['section_key'],
            (string) $item['label'],
            '',
            (string) ($item['icon_class'] ?? ''),
            (string) ($item['badge_text'] ?? ''),
            (int) ($item['sort_order'] ?? 0),
            (int) ($item['is_enabled'] ?? 1),
            (string) ($item['audience'] ?? 'all'),
            (string) ($item['item_type'] ?? 'link'),
            (string) ($item['children_section_key'] ?? ''),
            (string) ($item['admin_hint'] ?? ''),
            (string) ($item['admin_route_hint'] ?? ''),
        ]);
    }
}

function getNavigationControlRegistry(bool $refresh = false): array {
    static $cache = null;
    if ($refresh) {
        $cache = null;
    }
    if (is_array($cache)) {
        return $cache;
    }

    $defaults = getDefaultNavigationControls();
    $cache = $defaults;

    try {
        $db = getDBConnection();
        ensureNavigationControlsSchema($db);
        $rows = $db->query("SELECT * FROM navigation_controls")->fetchAll() ?: [];
        foreach ($rows as $row) {
            $key = (string) ($row['nav_key'] ?? '');
            if ($key === '') {
                continue;
            }

            if (!isset($cache[$key])) {
                $cache[$key] = [
                    'nav_key' => $key,
                    'location' => (string) ($row['location'] ?? 'header'),
                    'section_key' => (string) ($row['section_key'] ?? 'primary'),
                    'section_label' => coinrexNavigationSectionLabel((string) ($row['location'] ?? 'header'), (string) ($row['section_key'] ?? 'primary')),
                    'label' => trim((string) ($row['label'] ?? '')) !== '' ? trim((string) ($row['label'] ?? '')) : 'New Link',
                    'route_key' => '',
                    'custom_url' => trim((string) ($row['custom_url'] ?? '')),
                    'icon_class' => trim((string) ($row['icon_class'] ?? '')),
                    'badge_text' => trim((string) ($row['badge_text'] ?? '')),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_enabled' => (int) ($row['is_enabled'] ?? 1),
                    'audience' => (string) ($row['audience'] ?? 'all'),
                    'item_type' => (string) ($row['item_type'] ?? 'link'),
                    'children_section_key' => (string) ($row['children_section_key'] ?? ''),
                    'admin_hint' => trim((string) ($row['admin_hint'] ?? '')) !== '' ? trim((string) ($row['admin_hint'] ?? '')) : 'Custom navigation item created from Launch Control.',
                    'admin_route_hint' => trim((string) ($row['admin_route_hint'] ?? '')),
                    'active_pages' => [],
                    'is_system' => (int) ($row['is_system'] ?? 0),
                ];
            }

            $cache[$key] = array_merge($cache[$key], [
                'label' => trim((string) ($row['label'] ?? '')) !== '' ? trim((string) $row['label']) : (string) $cache[$key]['label'],
                'custom_url' => trim((string) ($row['custom_url'] ?? '')),
                'icon_class' => trim((string) ($row['icon_class'] ?? '')),
                'badge_text' => trim((string) ($row['badge_text'] ?? '')),
                'sort_order' => (int) ($row['sort_order'] ?? $cache[$key]['sort_order']),
                'is_enabled' => (int) ($row['is_enabled'] ?? $cache[$key]['is_enabled']),
                'location' => (string) ($row['location'] ?? $cache[$key]['location']),
                'section_key' => (string) ($row['section_key'] ?? $cache[$key]['section_key']),
                'section_label' => coinrexNavigationSectionLabel((string) ($row['location'] ?? $cache[$key]['location']), (string) ($row['section_key'] ?? $cache[$key]['section_key'])),
                'audience' => (string) ($row['audience'] ?? ($cache[$key]['audience'] ?? 'all')),
                'item_type' => (string) ($row['item_type'] ?? ($cache[$key]['item_type'] ?? 'link')),
                'children_section_key' => (string) ($row['children_section_key'] ?? ($cache[$key]['children_section_key'] ?? '')),
                'admin_hint' => trim((string) ($row['admin_hint'] ?? '')) !== '' ? trim((string) $row['admin_hint']) : (string) ($cache[$key]['admin_hint'] ?? ''),
                'admin_route_hint' => trim((string) ($row['admin_route_hint'] ?? '')) !== '' ? trim((string) $row['admin_route_hint']) : (string) ($cache[$key]['admin_route_hint'] ?? ''),
                'is_system' => (int) ($row['is_system'] ?? ($cache[$key]['is_system'] ?? 0)),
            ]);

            if ($key === 'header_marketplace_project' && trim((string) ($cache[$key]['badge_text'] ?? '')) === '') {
                $cache[$key]['badge_text'] = 'Soon';
            }
        }
    } catch (Throwable $e) {
        $cache = $defaults;
    }

    return $cache;
}

function coinrexNavigationSectionLabel(string $location, string $sectionKey): string {
    $map = [
        'header:primary' => 'Header Primary',
        'header:resources' => 'Header Resources',
        'header:marketplace' => 'Header Marketplace',
        'footer:platform' => 'Footer Platform',
        'footer:resources' => 'Footer Resources',
        'footer:legal' => 'Footer Legal',
        'footer:bottom' => 'Footer Bottom',
        'mobile:bottom' => 'Mobile Bottom Nav',
        'mobile_more:more' => 'Mobile More Menu',
    ];

    $compound = $location . ':' . $sectionKey;
    if (isset($map[$compound])) {
        return $map[$compound];
    }

    $locationLabel = match ($location) {
        'header' => 'Header',
        'footer' => 'Footer',
        'mobile' => 'Mobile',
        'mobile_more' => 'Mobile More',
        default => ucwords(str_replace('_', ' ', $location)),
    };

    $sectionLabel = ucwords(str_replace('_', ' ', $sectionKey));

    return trim($locationLabel . ' ' . $sectionLabel);
}

function coinrexGenerateNavigationKey(string $location, string $sectionKey, string $label): string {
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $label), '_'));
    if ($base === '') {
        $base = 'custom_link';
    }

    return strtolower($location . '_' . $sectionKey . '_' . $base . '_' . substr(sha1($location . '|' . $sectionKey . '|' . $label . '|' . microtime(true)), 0, 8));
}

function coinrexNavigationRouteUrl(string $routeKey, array $context = []): string {
    switch ($routeKey) {
        case 'home':
            return BASE_URL . '/index.php';
        case 'wallet':
            return BASE_URL . '/wallet.php';
        case 'dashboard':
            return BASE_URL . '/public/dashboard.php';
        case 'projects':
            return BASE_URL . '/public/projects.php';
        case 'reviews':
            return BASE_URL . '/public/reviews.php';
        case 'learnhub':
            return BASE_URL . '/public/taskhub.php';
        case 'boosthub':
            return BASE_URL . '/public/boosthub.php';
        case 'leaderboard':
            return BASE_URL . '/public/leaderboard.php';
        case 'reward_history':
            return BASE_URL . '/public/reward-history.php';
        case 'profile':
            return BASE_URL . '/public/profile.php';
        case 'roadmap':
            return BASE_URL . '/public/roadmap.php';
        case 'litepaper':
            return BASE_URL . '/public/litepaper.php';
        case 'blog':
            return BASE_URL . '/public/blog.php';
        case 'about':
            return BASE_URL . '/public/about.php';
        case 'contact':
            return BASE_URL . '/public/contact.php';
        case 'faq':
            return BASE_URL . '/public/faq.php';
        case 'terms':
            return BASE_URL . '/public/terms.php';
        case 'privacy':
            return BASE_URL . '/public/privacy.php';
        case 'cookies':
            return BASE_URL . '/public/cookies.php';
        case 'devhub':
            return BASE_URL . '/devhub/index.php';
        case 'devhub_api':
            return BASE_URL . '/devhub/widget-api.php';
        default:
            return BASE_URL . '/index.php';
    }
}

function coinrexNavigationCanRenderKey(string $navKey, array $context = []): bool {
    $isLoggedIn = !empty($context['is_logged_in']);
    $userLevel = (string) ($context['user_level'] ?? '');
    $taskhubCompleted = !empty($context['taskhub_mission_completed']);
    $learnhubAvailable = featureIsVisible('learnhub') && (!$isLoggedIn || !in_array($userLevel, ['pro', 'expert'], true)) && (!$isLoggedIn || !$taskhubCompleted);

    switch ($navKey) {
        case 'header_wallet':
        case 'footer_platform_wallet':
        case 'mobile_wallet':
        case 'mobile_more_wallet':
            return true;
        case 'header_dashboard_member':
        case 'mobile_home_member':
            return $isLoggedIn && featureIsVisible('dashboard');
        case 'header_home_guest':
        case 'mobile_home_guest':
        case 'mobile_litepaper_guest':
        case 'mobile_roadmap_guest':
        case 'mobile_about_guest':
            return !$isLoggedIn;
        case 'header_projects':
        case 'header_marketplace_project':
        case 'footer_platform_projects':
        case 'mobile_projects_member':
            return featureIsVisible('projects') && ($navKey !== 'mobile_projects_member' || $isLoggedIn);
        case 'header_marketplace_reviews':
        case 'footer_platform_reviews':
        case 'mobile_reviews_member':
            return featureIsVisible('reviews') && ($navKey !== 'mobile_reviews_member' || $isLoggedIn);
        case 'header_learnhub':
        case 'mobile_learnhub':
            return $learnhubAvailable;
        case 'mobile_leaderboard_fallback':
            return !$learnhubAvailable && featureIsVisible('leaderboard');
        case 'header_boosthub':
        case 'mobile_boosthub':
            return featureIsVisible('boosthub');
        case 'header_leaderboard':
        case 'footer_platform_leaderboard':
        case 'mobile_leaderboard':
            return featureIsVisible('leaderboard');
        case 'header_marketplace_devhub':
        case 'footer_platform_devhub':
            return featureIsVisible('devhub_full') || featureIsVisible('devhub_auth');
        case 'header_marketplace_menu':
        case 'header_resources_menu':
            return true;
        case 'footer_bottom_api':
            return featureIsVisible('devhub_full');
        default:
            return true;
    }
}

function coinrexNavigationItemIsActive(array $item, array $context = []): bool {
    $currentPage = (string) ($context['current_page'] ?? '');
    $activePages = $item['active_pages'] ?? [];
    return in_array($currentPage, is_array($activePages) ? $activePages : [], true);
}

function coinrexPrepareNavigationItem(array $item, array $context = []): ?array {
    if (empty($item['is_enabled'])) {
        return null;
    }

    $audience = (string) ($item['audience'] ?? 'all');
    $isLoggedIn = !empty($context['is_logged_in']);
    if ($audience === 'guest' && $isLoggedIn) {
        return null;
    }
    if ($audience === 'member' && !$isLoggedIn) {
        return null;
    }

    if (!coinrexNavigationCanRenderKey((string) ($item['nav_key'] ?? ''), $context)) {
        return null;
    }

    $href = trim((string) ($item['custom_url'] ?? ''));
    if ((string) ($item['item_type'] ?? 'link') === 'dropdown') {
        $href = '#';
    } elseif ($href === '') {
        $href = coinrexNavigationRouteUrl((string) ($item['route_key'] ?? ''), $context);
    }

    $item['href'] = $href;
    $item['is_active'] = coinrexNavigationItemIsActive($item, $context);

    return $item;
}

function getManagedNavigationItems(string $location, string $sectionKey = '', array $context = []): array {
    $registry = getNavigationControlRegistry();
    $items = [];

    foreach ($registry as $item) {
        if ((string) ($item['location'] ?? '') !== $location) {
            continue;
        }
        if ($sectionKey !== '' && (string) ($item['section_key'] ?? '') !== $sectionKey) {
            continue;
        }
        $item = coinrexPrepareNavigationItem($item, $context);
        if ($item !== null) {
            $items[] = $item;
        }
    }

    usort($items, static function (array $a, array $b): int {
        $sortCompare = (int) ($a['sort_order'] ?? 0) <=> (int) ($b['sort_order'] ?? 0);
        if ($sortCompare !== 0) {
            return $sortCompare;
        }

        return strcmp((string) ($a['nav_key'] ?? ''), (string) ($b['nav_key'] ?? ''));
    });

    return $items;
}

function getFixedMobileBottomNavigationItems(array $context = []): array {
    $isLoggedIn = !empty($context['is_logged_in']);
    $userLevel = normalizeUserLevel((string) ($context['user_level'] ?? 'beginner'));

    if (!$isLoggedIn) {
        $keys = [
            'mobile_leaderboard',
            'mobile_roadmap_guest',
            'mobile_home_guest',
            'mobile_litepaper_guest',
            'mobile_about_guest',
        ];
    } elseif (in_array($userLevel, ['pro', 'expert'], true)) {
        $keys = [
            'mobile_boosthub',
            'mobile_projects_member',
            'mobile_home_member',
            'mobile_leaderboard',
            'mobile_litepaper_member',
        ];
    } else {
        $keys = [
            'mobile_boosthub',
            'mobile_learnhub',
            'mobile_home_member',
            'mobile_leaderboard',
            'mobile_roadmap_member',
        ];
    }

    $items = [];
    $registry = getNavigationControlRegistry();
    $defaults = getDefaultNavigationControls();
    foreach ($keys as $key) {
        $item = $registry[$key] ?? ($defaults[$key] ?? null);
        if (!is_array($item)) {
            continue;
        }
        $item = coinrexPrepareNavigationItem($item, $context);
        if ($item !== null) {
            $items[] = $item;
        }
    }

    return array_slice($items, 0, 5);
}

function getManagedNavigationSlotItems(string $slotGroup, string $location, string $sectionKey, int $limit, array $context = []): array {
    $slotGroup = trim($slotGroup);
    if ($slotGroup === '') {
        return array_slice(getManagedNavigationItems($location, $sectionKey, $context), 0, $limit);
    }

    $isLoggedIn = !empty($context['is_logged_in']);
    $userLevel = $isLoggedIn ? normalizeUserLevel((string) ($context['user_level'] ?? 'beginner')) : 'visitor';
    $legacySlotGroup = $slotGroup . '_' . ($isLoggedIn ? 'member' : 'guest');

    $slotKeys = [];
    $explicitEmptySlots = [];
    try {
        $db = getDBConnection();
        ensureNavigationControlsSchema($db);

        $slotGroups = array_values(array_unique([
            coinrexLevelSlotGroup($slotGroup, $userLevel),
            $legacySlotGroup,
            $slotGroup,
        ]));

        foreach ($slotGroups as $candidateSlotGroup) {
            $stmt = $db->prepare("
                SELECT slot_number, nav_key
                FROM navigation_slots
                WHERE slot_group = ?
                ORDER BY slot_number ASC
                LIMIT " . (int) $limit
            );
            $stmt->execute([$candidateSlotGroup]);
            $slotRows = $stmt->fetchAll() ?: [];
            if ($slotRows) {
                $slotKeys = [];
                foreach ($slotRows as $slotRow) {
                    $slotNumber = (int) ($slotRow['slot_number'] ?? 0);
                    $slotNavKey = trim((string) ($slotRow['nav_key'] ?? ''));
                    if ($slotNumber >= 1 && $slotNumber <= $limit) {
                        if ($slotNavKey === '') {
                            $explicitEmptySlots[$slotNumber] = true;
                        } else {
                            $slotKeys[$slotNumber] = $slotNavKey;
                        }
                    }
                }
                ksort($slotKeys);
                $slotKeys = array_values($slotKeys);
                break;
            }
        }
    } catch (Throwable $e) {
        $slotKeys = [];
        $explicitEmptySlots = [];
    }

    if (!$slotKeys && !$explicitEmptySlots) {
        return array_slice(getManagedNavigationItems($location, $sectionKey, $context), 0, $limit);
    }

    $registry = getNavigationControlRegistry();
    $defaults = getDefaultNavigationControls();
    $items = [];
    $usedKeys = [];
    foreach ($slotKeys as $slotKey) {
        $item = $registry[$slotKey] ?? ($defaults[$slotKey] ?? null);
        if (!is_array($item)) {
            continue;
        }
        $item = coinrexPrepareNavigationItem($item, $context);
        if ($item !== null) {
            $items[] = $item;
            $usedKeys[(string) ($item['nav_key'] ?? $slotKey)] = true;
        }
    }

    // Only fill slots that are NOT explicitly set to empty in the DB.
    // Custom dropdowns should be included in the fallback pool so newly
    // created dropdowns show up even before they are assigned to a slot.
    $fallbackPool = getManagedNavigationItems($location, $sectionKey, $context);

    $slotsToFill = $limit - count($items) - count($explicitEmptySlots);
    if ($slotsToFill > 0) {
        foreach ($fallbackPool as $fallbackItem) {
            $fallbackKey = (string) ($fallbackItem['nav_key'] ?? '');
            if ($fallbackKey === '' || isset($usedKeys[$fallbackKey])) {
                continue;
            }
            $items[] = $fallbackItem;
            $usedKeys[$fallbackKey] = true;
            $slotsToFill--;
            if ($slotsToFill <= 0) {
                break;
            }
        }
    }

    return array_slice($items, 0, $limit);
}

