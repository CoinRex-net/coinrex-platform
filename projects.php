<?php
/**
 * CoinRex Projects Page
 * Location: /coinrex/projects.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
$current_user = getCurrentUser();
$can_submit_review = $current_user && userCanAccessProjectReviewArea($current_user);
require_once __DIR__ . '/includes/header.php';

// Fetch only admin-approved projects (public visibility control)
$db = getDBConnection();
$has_featured_column = tableHasColumn('projects', 'is_featured');
$has_sponsored_column = tableHasColumn('projects', 'is_sponsored');
$featured_select = $has_featured_column ? 'COALESCE(p.is_featured, 0)' : '0';
$sponsored_select = $has_sponsored_column ? 'COALESCE(p.is_sponsored, 0)' : '0';
$stmt = $db->prepare("
    SELECT
        p.*,
        {$featured_select} AS is_featured,
        {$sponsored_select} AS is_sponsored,
        COALESCE(review_stats.total_reviews, 0) AS total_reviews,
        COALESCE(review_stats.avg_rating, 0) AS avg_rating
    FROM projects p
    LEFT JOIN (
        SELECT
            project_id,
            COUNT(*) AS total_reviews,
            AVG(rating) AS avg_rating
        FROM reviews
        WHERE status = 'approved'
        GROUP BY project_id
    ) review_stats ON review_stats.project_id = p.id
    WHERE p.approval_status = 'approved'
    ORDER BY is_featured DESC, p.is_verified DESC, p.created_at DESC
");
$stmt->execute();
$projects = $stmt->fetchAll();

$sponsored_projects = array_values(array_filter($projects, static function ($project) {
    return (int) ($project['is_sponsored'] ?? 0) === 1;
}));

$grid_projects = array_values(array_filter($projects, static function ($project) {
    return (int) ($project['is_sponsored'] ?? 0) !== 1;
}));
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/projects.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/rating-badge.css">

<main class="projects-main">
    <div class="projects-container">
        
        <!-- Header -->
        <div class="page-header animate-fade-up">
            <div class="header-badge">
                <i class="fas fa-rocket"></i>
                <span>Browse & Review</span>
            </div>
            <h1>Crypto <span class="gradient-text">Projects</span></h1>
            <p>Explore listed projects publicly. Sign in and level up to submit quality reviews and earn $REX rewards.</p>
        </div>

        <!-- Sponsored Showcase -->
        <section class="sponsored-showcase animate-fade-up delay-1">
            <?php if (!empty($sponsored_projects)): ?>
                <div class="sponsored-slider-shell">
                    <button type="button" class="sponsored-slider-control sponsored-slider-control--prev" aria-label="Previous sponsored projects" data-slider-prev>
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <div class="sponsored-slider" aria-label="Sponsored projects slider" data-sponsored-slider>
                        <div class="sponsored-track">
                        <?php foreach ($sponsored_projects as $project): ?>
                            <?php $project_logo_url = coinrexNormalizeMediaUrl((string) ($project['logo'] ?? '')); ?>
                            <article class="sponsored-mini-card <?php echo (int) $project['is_featured'] === 1 ? 'featured' : 'regular'; ?>">
                                <div class="sponsored-mini-card__top">
                                    <div class="project-logo sponsored-mini-card__logo">
                                        <?php if($project_logo_url !== ''): ?>
                                            <img src="<?php echo htmlspecialchars($project_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($project['name']); ?>">
                                        <?php else: ?>
                                            <div class="logo-placeholder">
                                                <?php echo strtoupper(substr($project['name'], 0, 2)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="sponsored-mini-card__identity">
                                        <div class="sponsored-mini-card__title-row">
                                            <h3><?php echo htmlspecialchars($project['name']); ?></h3>
                                            <div class="sponsored-mini-card__chips">
                                                <span class="project-status-chip <?php echo (int)$project['is_featured'] === 1 ? 'featured' : 'regular'; ?>">
                                                    <i class="fas <?php echo (int)$project['is_featured'] === 1 ? 'fa-gem' : 'fa-circle-check'; ?>"></i>
                                                    <?php echo (int)$project['is_featured'] === 1 ? 'Featured' : 'Regular'; ?>
                                                </span>
                                                <span class="project-status-chip sponsored">
                                                    <i class="fas fa-bullhorn"></i>
                                                    Sponsored
                                                </span>
                                            </div>
                                        </div>
                                        <span class="badge category"><?php echo ucfirst($project['category']); ?></span>
                                    </div>
                                </div>

                                <p class="sponsored-mini-card__description"><?php echo htmlspecialchars(substr($project['description'] ?? 'No description available', 0, 95)); ?>...</p>

                                <div class="sponsored-mini-card__stats">
                                    <div class="stat stat-rating">
                                        <?php echo renderUniversalRating([
                                            'provider' => 'coinrex',
                                            'value' => (float) ($project['avg_rating'] ?? 0),
                                            'scale' => 5,
                                            'size' => 'sm',
                                            'variant' => 'cr-row-small',
                                            'show_count' => false,
                                            'class' => 'project-rating-badge',
                                        ]); ?>
                                    </div>
                                    <div class="stat">
                                        <i class="fas fa-pen-alt"></i>
                                        <span><?php echo number_format($project['total_reviews']); ?> reviews</span>
                                    </div>
                                </div>

                                <div class="sponsored-mini-card__footer">
                                    <div class="sponsored-mini-card__reward">
                                        <i class="fas fa-coins"></i>
                                        <span>Up to <?php echo $project['max_reward_rex']; ?> $REX</span>
                                    </div>

                                    <?php if ($can_submit_review): ?>
                                        <a href="<?php echo BASE_URL; ?>/project-detail.php?id=<?php echo $project['id']; ?>" class="btn-review sponsored-mini-card__cta">
                                            <i class="fas fa-pen-alt"></i> Review
                                        </a>
                                    <?php elseif (!$current_user): ?>
                                        <a href="<?php echo AUTH_URL; ?>/auth.php" class="btn-review btn-review-locked sponsored-mini-card__cta">
                                            <i class="fas fa-lock"></i> Review
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo BASE_URL; ?>/taskhub.php" class="btn-review btn-review-locked sponsored-mini-card__cta">
                                            <i class="fas fa-lock"></i> Review
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="button" class="sponsored-slider-control sponsored-slider-control--next" aria-label="Next sponsored projects" data-slider-next>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php else: ?>
                <div class="sponsored-fallback-grid">
                    <article class="sponsored-mini-card sponsored-mini-card--fallback">
                        <div class="sponsored-mini-card__top sponsored-mini-card__top--fallback">
                            <div class="sponsored-mini-card__icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="sponsored-mini-card__identity">
                                <div class="sponsored-mini-card__title-row">
                                    <h3>Premium Placement</h3>
                                </div>
                                <span class="badge category">Visibility</span>
                            </div>
                        </div>
                        <p class="sponsored-mini-card__description">Put your project in front of active CoinRex visitors with above-the-fold sponsored exposure.</p>
                        <div class="sponsored-mini-card__stats sponsored-mini-card__stats--fallback">
                            <div class="sponsored-mini-card__feature-pill"><i class="fas fa-star"></i> Premium homepage section</div>
                            <div class="sponsored-mini-card__feature-pill"><i class="fas fa-eye"></i> Higher discoverability</div>
                        </div>
                    </article>

                    <article class="sponsored-mini-card sponsored-mini-card--fallback">
                        <div class="sponsored-mini-card__top sponsored-mini-card__top--fallback">
                            <div class="sponsored-mini-card__icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="sponsored-mini-card__identity">
                                <div class="sponsored-mini-card__title-row">
                                    <h3>Better Engagement</h3>
                                </div>
                                <span class="badge category">Growth</span>
                            </div>
                        </div>
                        <p class="sponsored-mini-card__description">Drive more review intent with a clear path from the showcase into the existing project detail and review flow.</p>
                        <div class="sponsored-mini-card__stats sponsored-mini-card__stats--fallback">
                            <div class="sponsored-mini-card__feature-pill"><i class="fas fa-pen-alt"></i> Review-ready audience</div>
                            <div class="sponsored-mini-card__feature-pill"><i class="fas fa-bolt"></i> Stronger first impression</div>
                        </div>
                    </article>

                    <article class="sponsored-mini-card sponsored-mini-card--fallback sponsored-mini-card--cta">
                        <div class="sponsored-mini-card__top sponsored-mini-card__top--fallback">
                            <div class="sponsored-mini-card__icon sponsored-mini-card__icon--accent">
                                <i class="fas fa-paper-plane"></i>
                            </div>
                            <div class="sponsored-mini-card__identity">
                                <div class="sponsored-mini-card__title-row">
                                    <h3>Sponsor Your Project</h3>
                                </div>
                                <span class="badge category">Contact</span>
                            </div>
                        </div>
                        <p class="sponsored-mini-card__description">Talk to CoinRex about sponsored slots, premium reach, and the best placement plan for your project.</p>
                        <div class="sponsored-mini-card__footer">
                            <div class="sponsored-mini-card__reward">
                                <i class="fas fa-gem"></i>
                                <span>Priority showcase access</span>
                            </div>
                            <a href="<?php echo BASE_URL; ?>/contact.php" class="btn-review sponsored-mini-card__cta">
                                <i class="fas fa-paper-plane"></i> Contact Now
                            </a>
                        </div>
                    </article>
                </div>
            <?php endif; ?>
        </section>
        
        <!-- Projects Grid -->
        <div class="projects-grid">
            <?php if(empty($grid_projects)): ?>
                <div class="no-projects">
                    <i class="fas fa-inbox"></i>
                    <h3>No Projects Available</h3>
                    <p>Check back soon for new projects to review!</p>
                </div>
            <?php else: ?>
                <?php foreach($grid_projects as $project): ?>
                    <?php $project_logo_url = coinrexNormalizeMediaUrl((string) ($project['logo'] ?? '')); ?>
                    <div class="project-card <?php echo (int) $project['is_featured'] === 1 ? 'project-card-featured' : 'project-card-regular'; ?> <?php echo (int) ($project['is_sponsored'] ?? 0) === 1 ? 'project-card-sponsored' : ''; ?>">
                        
                        <div class="project-identity-grid">
                            <div class="project-logo project-logo-inline">
                                <?php if($project_logo_url !== ''): ?>
                                    <img src="<?php echo htmlspecialchars($project_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($project['name']); ?>">
                                <?php else: ?>
                                    <div class="logo-placeholder">
                                        <?php echo strtoupper(substr($project['name'], 0, 2)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="project-meta-column">
                                <div class="project-meta-row project-meta-row-top">
                                    <h3 class="project-name">
                                        <span class="project-name-text"><?php echo htmlspecialchars($project['name']); ?></span>
                                        <span class="project-status-chip <?php echo (int)$project['is_featured'] === 1 ? 'featured' : 'regular'; ?>">
                                            <i class="fas <?php echo (int)$project['is_featured'] === 1 ? 'fa-gem' : 'fa-circle-check'; ?>"></i>
                                            <?php echo (int)$project['is_featured'] === 1 ? 'Featured' : 'Regular'; ?>
                                        </span>
                                        <?php if ((int) ($project['is_sponsored'] ?? 0) === 1): ?>
                                            <span class="project-status-chip sponsored">
                                                <i class="fas fa-bullhorn"></i>
                                                Sponsored
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                </div>

                                <div class="project-meta-row project-meta-row-bottom">
                                    <span class="badge category"><?php echo ucfirst($project['category']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <p class="project-description"><?php echo htmlspecialchars(substr($project['description'] ?? 'No description available', 0, 90)); ?>...</p>
                        
                        <div class="project-stats">
                            <div class="stat stat-rating">
                                <?php echo renderUniversalRating([
                                    'provider' => 'coinrex',
                                    'value' => (float) ($project['avg_rating'] ?? 0),
                                    'scale' => 5,
                                    'size' => 'sm',
                                    'variant' => 'cr-row-small',
                                    'show_count' => false,
                                    'class' => 'project-rating-badge',
                                ]); ?>
                            </div>
                            <div class="stat">
                                <i class="fas fa-pen-alt"></i>
                                <span><?php echo number_format($project['total_reviews']); ?> reviews</span>
                            </div>
                        </div>
                        
                        <div class="project-rewards">
                            <div class="reward-item">
                                <i class="fas fa-coins"></i>
                                <span>Up to <?php echo $project['max_reward_rex']; ?> $REX</span>
                            </div>
                            <div class="reward-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo $project['required_holding_days']; ?> days</span>
                            </div>
                            <div class="reward-item">
                                <i class="fas fa-dollar-sign"></i>
                                <span>$<?php echo number_format($project['min_holding_amount'], 0); ?></span>
                            </div>
                        </div>
                        
                        <div class="project-links">
                            <?php if($project['website_url']): ?>
                                <a href="<?php echo $project['website_url']; ?>" target="_blank" rel="noopener noreferrer" class="link-btn" title="Website"><i class="fas fa-globe"></i></a>
                            <?php endif; ?>
                            <?php if($project['twitter_url']): ?>
                                <a href="<?php echo $project['twitter_url']; ?>" target="_blank" rel="noopener noreferrer" class="link-btn" title="Twitter"><i class="fab fa-twitter"></i></a>
                            <?php endif; ?>
                            <?php if($project['telegram_url']): ?>
                                <a href="<?php echo $project['telegram_url']; ?>" target="_blank" rel="noopener noreferrer" class="link-btn" title="Telegram"><i class="fab fa-telegram"></i></a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($can_submit_review): ?>
                            <a href="<?php echo BASE_URL; ?>/project-detail.php?id=<?php echo $project['id']; ?>" class="btn-review">
                                <i class="fas fa-pen-alt"></i> Post Quality Review
                            </a>
                        <?php elseif (!$current_user): ?>
                            <a href="<?php echo AUTH_URL; ?>/auth.php" class="btn-review btn-review-locked">
                                <i class="fas fa-lock"></i> Sign in to Post Review
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/taskhub.php" class="btn-review btn-review-locked">
                                <i class="fas fa-lock"></i> Unlock at Pro Level
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var slider = document.querySelector('[data-sponsored-slider]');
    var prevButton = document.querySelector('[data-slider-prev]');
    var nextButton = document.querySelector('[data-slider-next]');

    if (!slider || !prevButton || !nextButton) {
        return;
    }

    var getStep = function () {
        var firstCard = slider.querySelector('.sponsored-mini-card');
        if (!firstCard) {
            return slider.clientWidth;
        }

        var cardWidth = firstCard.getBoundingClientRect().width;
        var styles = window.getComputedStyle(slider.querySelector('.sponsored-track'));
        var gap = parseFloat(styles.columnGap || styles.gap || 0);

        return cardWidth + gap;
    };

    var updateControls = function () {
        var maxScroll = slider.scrollWidth - slider.clientWidth - 4;
        prevButton.disabled = slider.scrollLeft <= 4;
        nextButton.disabled = slider.scrollLeft >= maxScroll;
    };

    prevButton.addEventListener('click', function () {
        slider.scrollBy({ left: -getStep(), behavior: 'smooth' });
    });

    nextButton.addEventListener('click', function () {
        slider.scrollBy({ left: getStep(), behavior: 'smooth' });
    });

    slider.addEventListener('scroll', updateControls, { passive: true });
    window.addEventListener('resize', updateControls);
    updateControls();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
