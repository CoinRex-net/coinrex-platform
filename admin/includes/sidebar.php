<aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-brand admin-brand-logo">
        <img src="<?php echo BASE_URL; ?>/assets/images/favicon.png" alt="CoinRex">
        <div>
            <span>AdminHub</span>
            <small>by CoinRex</small>
        </div>
    </div>
    <?php
        $activePage = (string) ($activePage ?? 'dashboard');
        $corePages = ['dashboard', 'users', 'projects', 'reviews', 'developers', 'security-management', 'admins', 'blog', 'blog-create', 'blog-edit', 'blog-categories', 'blog-tags', 'blog-ads', 'sponsored-tokens', 'launch-control', 'roadmap'];
        $rewardPages = ['rewards', 'reward-ledger', 'reward-users', 'referrals', 'early-airdrop'];
        $taskPages = ['task-management', 'quiz-manager', 'taskhub-review', 'boosthub-management', 'boosthub-evidence'];
    ?>
    <nav class="admin-nav" id="adminNavGroups">
        <div class="admin-nav-group <?php echo in_array($activePage, $corePages, true) ? 'is-open' : ''; ?>">
            <button type="button" class="admin-nav-group-toggle" data-nav-group-toggle aria-expanded="<?php echo in_array($activePage, $corePages, true) ? 'true' : 'false'; ?>">
                <span><i class="fas fa-house"></i> Core</span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="admin-nav-group-links" data-nav-group-links>
                <a href="<?php echo ADMIN_BASE_URL; ?>/dashboard.php" class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
                <?php if (canCurrentAdmin('manage_users')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/users.php" class="<?php echo $activePage === 'users' ? 'active' : ''; ?>"><i class="fas fa-users"></i><span>Users</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_projects')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/projects.php" class="<?php echo $activePage === 'projects' ? 'active' : ''; ?>"><i class="fas fa-layer-group"></i><span>Projects</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_reviews')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/reviews.php" class="<?php echo $activePage === 'reviews' ? 'active' : ''; ?>"><i class="fas fa-clipboard-check"></i><span>Reviews</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_developers')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/developers.php" class="<?php echo $activePage === 'developers' ? 'active' : ''; ?>"><i class="fas fa-user-shield"></i><span>Developer Verification</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_users')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/security-management.php" class="<?php echo $activePage === 'security-management' ? 'active' : ''; ?>"><i class="fas fa-shield-halved"></i><span>Security Management</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_blog')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/blog.php" class="<?php echo $activePage === 'blog' ? 'active' : ''; ?>"><i class="fas fa-blog"></i><span>Blog</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_blog')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/blog-ads.php" class="<?php echo $activePage === 'blog-ads' ? 'active' : ''; ?>"><i class="fas fa-rectangle-ad"></i><span>Blog Ads</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_projects')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/sponsored-tokens.php" class="<?php echo $activePage === 'sponsored-tokens' ? 'active' : ''; ?>"><i class="fas fa-ticket-alt"></i><span>Sponsored Tokens</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_launch_controls')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/launch-control.php" class="<?php echo $activePage === 'launch-control' ? 'active' : ''; ?>"><i class="fas fa-sliders"></i><span>Launch Control</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_roadmap')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/roadmap.php" class="<?php echo $activePage === 'roadmap' ? 'active' : ''; ?>"><i class="fas fa-route"></i><span>Roadmap</span></a><?php endif; ?>
            </div>
        </div>

        <div class="admin-nav-group <?php echo in_array($activePage, $rewardPages, true) ? 'is-open' : ''; ?>">
            <button type="button" class="admin-nav-group-toggle" data-nav-group-toggle aria-expanded="<?php echo in_array($activePage, $rewardPages, true) ? 'true' : 'false'; ?>">
                <span><i class="fas fa-coins"></i> Rewards & Economy</span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="admin-nav-group-links" data-nav-group-links>
                <?php if (canCurrentAdmin('manage_rewards')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/rewards.php" class="<?php echo $activePage === 'rewards' ? 'active' : ''; ?>"><i class="fas fa-coins"></i><span>Rewards Overview</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_rewards')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/reward-ledger.php" class="<?php echo $activePage === 'reward-ledger' ? 'active' : ''; ?>"><i class="fas fa-file-invoice-dollar"></i><span>Reward Ledger</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_rewards')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/reward-users.php" class="<?php echo $activePage === 'reward-users' ? 'active' : ''; ?>"><i class="fas fa-user-lock"></i><span>Reward Users</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_rewards')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/referrals.php" class="<?php echo $activePage === 'referrals' ? 'active' : ''; ?>"><i class="fas fa-user-plus"></i><span>Referral Validation</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_rewards')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/early-airdrop.php" class="<?php echo $activePage === 'early-airdrop' ? 'active' : ''; ?>"><i class="fas fa-rocket"></i><span>Early Airdrop</span></a><?php endif; ?>
            </div>
        </div>

        <div class="admin-nav-group <?php echo in_array($activePage, $taskPages, true) ? 'is-open' : ''; ?>">
            <button type="button" class="admin-nav-group-toggle" data-nav-group-toggle aria-expanded="<?php echo in_array($activePage, $taskPages, true) ? 'true' : 'false'; ?>">
                <span><i class="fas fa-list-check"></i> Task Systems</span>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="admin-nav-group-links" data-nav-group-links>
                <?php if (canCurrentAdmin('manage_tasks')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/task-management.php" class="<?php echo $activePage === 'task-management' ? 'active' : ''; ?>"><i class="fas fa-list-check"></i><span>Task Management</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('manage_tasks')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/quiz-manager.php" class="<?php echo $activePage === 'quiz-manager' ? 'active' : ''; ?>"><i class="fas fa-question-circle"></i><span>Quiz Manager</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('moderate_tasks')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/taskhub-review.php" class="<?php echo $activePage === 'taskhub-review' ? 'active' : ''; ?>"><i class="fas fa-clipboard-check"></i><span>LearnHub Review</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('moderate_tasks')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/boosthub.php" class="<?php echo $activePage === 'boosthub-management' ? 'active' : ''; ?>"><i class="fas fa-bolt"></i><span>BoostHub Management</span></a><?php endif; ?>
                <?php if (canCurrentAdmin('moderate_tasks')): ?><a href="<?php echo ADMIN_BASE_URL; ?>/boosthub-evidence.php" class="<?php echo $activePage === 'boosthub-evidence' ? 'active' : ''; ?>"><i class="fas fa-clipboard-list"></i><span>BoostHub Evidence Log</span></a><?php endif; ?>
            </div>
        </div>
    </nav>
</aside>
