<?php
$current_script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
$is_dashboard_route = in_array($current_script, [BASE_URI . '/devhub/index.php', BASE_URI . '/devhub/'], true);
$is_submit_project_route = ($current_script === BASE_URI . '/devhub/projects/submit_project.php');
$is_reviews_route = ($current_script === BASE_URI . '/devhub/reviews.php');
$is_widget_api_route = ($current_script === BASE_URI . '/devhub/widget-api.php');
$is_apply_route = ($current_script === BASE_URI . '/devhub/apply.php');
$user_id = getCurrentUserId();
$db = getDevHubDB();

$is_verified = false;
$has_pending_change = false;

if ($user_id) {
    $is_verified = isVerifiedDeveloper($user_id);
    $stmt = $db->prepare("SELECT id FROM developer_verification WHERE user_id = ? AND status = 'change_requested' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $has_pending_change = $stmt->fetch();
}
?>

<aside class="devhub-sidebar">
    <!-- Logo -->
    <div class="dh-logo">
        <img src="<?php echo BASE_URL; ?>/assets/images/favicon.png" alt="CoinRex">
        <div>
            <span>DevHub</span>
            <small>by CoinRex</small>
        </div>
    </div>
    
    <!-- Menu -->
    <nav class="dh-nav">
        <a href="<?php echo BASE_URL; ?>/devhub/index.php" class="dh-menu <?php echo $is_dashboard_route ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <?php if($is_verified): ?>
            <a href="<?php echo BASE_URL; ?>/devhub/projects/submit_project.php" class="dh-menu <?php echo $is_submit_project_route ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Register Project</span>
            </a>

            <a href="<?php echo BASE_URL; ?>/devhub/reviews.php" class="dh-menu <?php echo $is_reviews_route ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Review Insights</span>
            </a>
        <?php else: ?>
            <a href="#" class="dh-menu dh-menu--disabled" title="Project Registration will unlock after verification">
                <i class="fas fa-plus-circle"></i>
                <span>Register Project</span>
                <i class="fas fa-lock dh-menu-lock"></i>
            </a>

            <a href="#" class="dh-menu dh-menu--disabled" title="Review Insights will unlock after verification">
                <i class="fas fa-clipboard-list"></i>
                <span>Review Insights</span>
                <i class="fas fa-lock dh-menu-lock"></i>
            </a>
        <?php endif; ?>

        <!-- Widgets & API is accessible to all logged-in users (unverified can visit but see CTA) -->
        <a href="<?php echo BASE_URL; ?>/devhub/widget-api.php" class="dh-menu <?php echo $is_widget_api_route ? 'active' : ''; ?>">
            <i class="fas fa-plug"></i>
            <span>Widgets & API</span>
        </a>

        <a href="<?php echo BASE_URL; ?>/devhub/apply.php" class="dh-menu <?php echo $is_apply_route ? 'active' : ''; ?>">
            <i class="fas fa-shield-alt"></i>
            <span><?php echo $is_verified ? ($has_pending_change ? "Change Pending" : "Request Identity Change") : "Get Verified"; ?></span>
        </a>
    </nav>
    
    <!-- Bottom Section -->
    <div class="dh-bottom">
        <!-- Social Icons -->
        <div class="dh-social">
            <a href="https://twitter.com/coinrex" target="_blank" rel="noopener noreferrer"><i class="fab fa-twitter"></i></a>
            <a href="https://facebook.com/coinrex" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook-f"></i></a>
            <a href="https://t.me/coinrex" target="_blank" rel="noopener noreferrer"><i class="fab fa-telegram-plane"></i></a>
        </div>
    </div>
</aside>
