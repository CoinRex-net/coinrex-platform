<?php
/**
 * CoinRex Projects Page
 * Location: /coinrex/projects.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireFeatureAccess('projects');
$current_user = getCurrentUser();
$can_submit_review = $current_user && userCanAccessProjectReviewArea($current_user);
require_once __DIR__ . '/../includes/header.php';

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

$featured_projects = array_values(array_filter($grid_projects, static function ($project) {
    return (int) ($project['is_featured'] ?? 0) === 1;
}));

$regular_projects = array_values(array_filter($grid_projects, static function ($project) {
    return (int) ($project['is_featured'] ?? 0) !== 1;
}));

$sort_projects_by_rating = static function (array &$items): void {
    usort($items, static function ($a, $b) {
        $rating_compare = ((float) ($b['avg_rating'] ?? 0)) <=> ((float) ($a['avg_rating'] ?? 0));
        if ($rating_compare !== 0) {
            return $rating_compare;
        }

        return ((int) ($b['total_reviews'] ?? 0)) <=> ((int) ($a['total_reviews'] ?? 0));
    });
};

$sort_projects_by_rating($featured_projects);
$sort_projects_by_rating($regular_projects);

$render_project_contract_address = static function ($address, $extra_class = ''): string {
    $address = trim((string) $address);
    if ($address === '') {
        return '';
    }

    $class = trim('project-contract-address ' . (string) $extra_class);
    $escaped_address = htmlspecialchars($address, ENT_QUOTES, 'UTF-8');
    $escaped_class = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

    return '<button type="button" class="' . $escaped_class . '" title="Click to copy contract address" aria-label="Copy contract address ' . $escaped_address . '" data-contract-address="' . $escaped_address . '">'
        . '<i class="fas fa-file-contract" aria-hidden="true"></i>'
        . '<span class="project-contract-address__label">Contract Address</span>'
        . '<code>' . $escaped_address . '</code>'
        . '<span class="project-contract-address__copy"><i class="fas fa-copy" aria-hidden="true"></i><span>Copy</span></span>'
        . '</button>';
};

// Determine which projects the current user has already reviewed
$reviewed_project_ids = [];
if ($current_user && $can_submit_review) {
    $reviewed_project_ids = getUserReviewedProjectIds((int) $current_user['id'], $db);
}
$reviewed_project_ids_set = array_flip($reviewed_project_ids);

$section_preview_limit = 3;
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/projects.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/projects.css'); ?>">
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
            <div class="sponsored-showcase__badge">
                <i class="fas fa-bullhorn"></i>
                <span>Sponsors</span>
            </div>
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

                                <?php echo $render_project_contract_address($project['contract_address'] ?? '', 'project-contract-address--sponsored'); ?>

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

                                    <?php if ($can_submit_review && isset($reviewed_project_ids_set[$project['id']])): ?>
                                        <a href="<?php echo BASE_URL; ?>/public/my-reviews.php" class="btn-review btn-review-done sponsored-mini-card__cta">
                                            <i class="fas fa-check-circle"></i> Already Reviewed
                                        </a>
                                    <?php elseif ($can_submit_review): ?>
                                        <a href="<?php echo BASE_URL; ?>/public/project-detail.php?id=<?php echo $project['id']; ?>" class="btn-review sponsored-mini-card__cta">
                                            <i class="fas fa-pen-alt"></i> Review
                                        </a>
                                    <?php elseif (!$current_user): ?>
                                        <a href="<?php echo AUTH_URL; ?>/auth.php" class="btn-review btn-review-locked sponsored-mini-card__cta">
                                            <i class="fas fa-lock"></i> Review
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo BASE_URL; ?>/public/taskhub.php" class="btn-review btn-review-locked sponsored-mini-card__cta">
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
                <div class="sponsored-slider-shell sponsored-slider-shell--fallback">
                    <button type="button" class="sponsored-slider-control sponsored-slider-control--prev" aria-label="Previous sponsor promotion" data-slider-prev>
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <div class="sponsored-slider" aria-label="Sponsor promotion slider" data-sponsored-slider>
                        <div class="sponsored-track sponsored-track--fallback">
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
                                    <a href="<?php echo BASE_URL; ?>/public/contact.php" class="btn-review sponsored-mini-card__cta">
                                        <i class="fas fa-paper-plane"></i> Contact Now
                                    </a>
                                </div>
                            </article>
                        </div>
                    </div>

                    <button type="button" class="sponsored-slider-control sponsored-slider-control--next" aria-label="Next sponsor promotion" data-slider-next>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            <?php endif; ?>
        </section>
        
        <!-- Projects Sections -->
        <?php if(empty($grid_projects)): ?>
            <div class="projects-grid">
                <div class="no-projects">
                    <i class="fas fa-inbox"></i>
                    <h3>No Projects Available</h3>
                    <p>Check back soon for new projects to review!</p>
                </div>
            </div>
        <?php else: ?>
            <?php if(!empty($featured_projects)): ?>
            <section class="project-list-section project-list-section--featured animate-fade-up delay-2">
                <div class="project-list-section__head">
                    <div>
                        <span class="project-list-section__eyebrow"><i class="fas fa-gem"></i> Featured</span>
                        <h2>Featured Projects</h2>
                    </div>
                    <p>Hand-picked projects with elevated visibility and stronger discovery priority.</p>
                </div>

                <div class="projects-grid projects-grid--featured" data-project-section-grid="featured">
                <?php foreach($featured_projects as $project_index => $project): ?>
                    <?php $project_logo_url = coinrexNormalizeMediaUrl((string) ($project['logo'] ?? '')); ?>
                    <div class="project-card <?php echo (int) $project['is_featured'] === 1 ? 'project-card-featured' : 'project-card-regular'; ?> <?php echo (int) ($project['is_sponsored'] ?? 0) === 1 ? 'project-card-sponsored' : ''; ?> <?php echo $project_index >= $section_preview_limit ? 'project-card--extra is-hidden' : ''; ?>">
                        
                        <div class="project-identity-grid">
                            <div class="project-logo project-logo-inline<?php echo $project_logo_url !== '' ? ' has-logo-image' : ''; ?>"<?php if ($project_logo_url !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($project_logo_url, ENT_QUOTES, 'UTF-8'); ?>');" aria-label="<?php echo htmlspecialchars((string) $project['name'], ENT_QUOTES, 'UTF-8'); ?> logo"<?php endif; ?>>
                                <?php if($project_logo_url === ''): ?>
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

                        <?php echo $render_project_contract_address($project['contract_address'] ?? ''); ?>
                        
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
                        
                        <?php if ($can_submit_review && isset($reviewed_project_ids_set[$project['id']])): ?>
                            <a href="<?php echo BASE_URL; ?>/public/my-reviews.php" class="btn-review btn-review-done">
                                <i class="fas fa-check-circle"></i> Already Reviewed
                            </a>
                        <?php elseif ($can_submit_review): ?>
                            <a href="<?php echo BASE_URL; ?>/public/project-detail.php?id=<?php echo $project['id']; ?>" class="btn-review">
                                <i class="fas fa-pen-alt"></i> Post Quality Review
                            </a>
                        <?php elseif (!$current_user): ?>
                            <a href="<?php echo AUTH_URL; ?>/auth.php" class="btn-review btn-review-locked">
                                <i class="fas fa-lock"></i> Sign in to Post Review
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/public/taskhub.php" class="btn-review btn-review-locked">
                                <i class="fas fa-lock"></i> Unlock at Pro Level
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>

                <?php if (count($featured_projects) > $section_preview_limit): ?>
                <div class="project-section-actions">
                    <button type="button" class="project-view-more-btn" data-project-view-more="featured">
                        <span>View more featured projects</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if(!empty($regular_projects)): ?>
            <section class="project-list-section project-list-section--regular animate-fade-up delay-3">
                <div class="project-list-section__head">
                    <div>
                        <span class="project-list-section__eyebrow"><i class="fas fa-circle-check"></i> Regular</span>
                        <h2>Regular Projects</h2>
                    </div>
                    <p>Explore approved CoinRex projects available for public discovery and review activity.</p>
                </div>

                <div class="projects-grid projects-grid--regular" data-project-section-grid="regular">
                <?php foreach($regular_projects as $project_index => $project): ?>
                    <?php $project_logo_url = coinrexNormalizeMediaUrl((string) ($project['logo'] ?? '')); ?>
                    <div class="project-card <?php echo (int) $project['is_featured'] === 1 ? 'project-card-featured' : 'project-card-regular'; ?> <?php echo (int) ($project['is_sponsored'] ?? 0) === 1 ? 'project-card-sponsored' : ''; ?> <?php echo $project_index >= $section_preview_limit ? 'project-card--extra is-hidden' : ''; ?>">
                        
                        <div class="project-identity-grid">
                            <div class="project-logo project-logo-inline<?php echo $project_logo_url !== '' ? ' has-logo-image' : ''; ?>"<?php if ($project_logo_url !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($project_logo_url, ENT_QUOTES, 'UTF-8'); ?>');" aria-label="<?php echo htmlspecialchars((string) $project['name'], ENT_QUOTES, 'UTF-8'); ?> logo"<?php endif; ?>>
                                <?php if($project_logo_url === ''): ?>
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
                                    </h3>
                                </div>

                                <div class="project-meta-row project-meta-row-bottom">
                                    <span class="badge category"><?php echo ucfirst($project['category']); ?></span>
                                </div>
                            </div>
                        </div>

                        <?php echo $render_project_contract_address($project['contract_address'] ?? ''); ?>
                        
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
                        
                        <?php if ($can_submit_review && isset($reviewed_project_ids_set[$project['id']])): ?>
                            <a href="<?php echo BASE_URL; ?>/public/my-reviews.php" class="btn-review btn-review-done">
                                <i class="fas fa-check-circle"></i> Already Reviewed
                            </a>
                        <?php elseif ($can_submit_review): ?>
                            <a href="<?php echo BASE_URL; ?>/public/project-detail.php?id=<?php echo $project['id']; ?>" class="btn-review">
                                <i class="fas fa-pen-alt"></i> Post Quality Review
                            </a>
                        <?php elseif (!$current_user): ?>
                            <a href="<?php echo AUTH_URL; ?>/auth.php" class="btn-review btn-review-locked">
                                <i class="fas fa-lock"></i> Sign in to Post Review
                            </a>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>/public/taskhub.php" class="btn-review btn-review-locked">
                                <i class="fas fa-lock"></i> Unlock at Pro Level
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>

                <?php if (count($regular_projects) > $section_preview_limit): ?>
                <div class="project-section-actions">
                    <button type="button" class="project-view-more-btn" data-project-view-more="regular">
                        <span>View more regular projects</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        <?php endif; ?>
        
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var slider = document.querySelector('[data-sponsored-slider]');
    var prevButton = document.querySelector('[data-slider-prev]');
    var nextButton = document.querySelector('[data-slider-next]');

    var copyContractAddress = function (address) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(address);
        }

        return new Promise(function (resolve, reject) {
            var input = document.createElement('textarea');
            input.value = address;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed';
            input.style.left = '-9999px';
            input.style.top = '0';
            document.body.appendChild(input);
            input.focus();
            input.select();

            try {
                if (document.execCommand('copy')) {
                    resolve();
                } else {
                    reject(new Error('Copy command failed'));
                }
            } catch (error) {
                reject(error);
            } finally {
                document.body.removeChild(input);
            }
        });
    };

    document.addEventListener('click', function (event) {
        var contractButton = event.target.closest('[data-contract-address]');
        if (!contractButton) {
            return;
        }

        var address = contractButton.getAttribute('data-contract-address') || '';
        if (!address) {
            return;
        }

        copyContractAddress(address).then(function () {
            contractButton.classList.add('is-copied');
            contractButton.setAttribute('title', 'Copied contract address');

            var copyText = contractButton.querySelector('.project-contract-address__copy span');
            if (copyText) {
                copyText.textContent = 'Copied';
            }

            window.setTimeout(function () {
                contractButton.classList.remove('is-copied');
                contractButton.setAttribute('title', 'Click to copy contract address');

                if (copyText) {
                    copyText.textContent = 'Copy';
                }
            }, 1600);
        }).catch(function () {
            contractButton.classList.add('is-copy-failed');
            window.setTimeout(function () {
                contractButton.classList.remove('is-copy-failed');
            }, 1600);
        });
    });

    if (slider && prevButton && nextButton) {
        var track = slider.querySelector('.sponsored-track');
        var sliderShell = slider.closest('.sponsored-slider-shell');
        var originalCards = Array.prototype.slice.call(slider.querySelectorAll('.sponsored-mini-card'));
        if (track && originalCards.length > 1) {
            originalCards.forEach(function (card) {
                track.appendChild(card.cloneNode(true));
            });
        }

        var cards = Array.prototype.slice.call(slider.querySelectorAll('.sponsored-mini-card'));
        var dots = [];
        var autoTimer = null;
        var autoDelay = 4200;
        var currentIndex = 0;
        var touchStartX = 0;
        var touchStartY = 0;
        var originalCount = originalCards.length;
        var hasClonedLoop = originalCount > 1;
        var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (sliderShell && originalCount > 1) {
            var dotsNav = document.createElement('div');
            dotsNav.className = 'sponsored-slider-nav';
            dotsNav.setAttribute('aria-label', 'Sponsor slider pagination');

            originalCards.forEach(function (card, index) {
                var cardTitle = card.querySelector('h3');
                var dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'sponsored-slider-dot';
                dot.setAttribute('aria-label', 'Show sponsor slide ' + (index + 1) + (cardTitle ? ': ' + cardTitle.textContent.trim() : ''));
                dot.addEventListener('click', function () {
                    goToSlide(index);
                    restartAutoSlide();
                });
                dotsNav.appendChild(dot);
                dots.push(dot);
            });

            sliderShell.appendChild(dotsNav);
        }

        var getGap = function () {
            if (!track) {
                return 0;
            }

            var styles = window.getComputedStyle(track);
            return parseFloat(styles.columnGap || styles.gap || 0) || 0;
        };

        var getStep = function () {
            var firstCard = cards[0];
            if (!firstCard) {
                return slider.clientWidth;
            }

            var cardWidth = firstCard.getBoundingClientRect().width;
            return cardWidth + getGap();
        };

        var getVisibleCount = function () {
            if (!cards.length) {
                return 1;
            }

            var step = getStep();
            if (step <= 0) {
                return 1;
            }

            return Math.max(1, Math.round((slider.clientWidth + getGap()) / step));
        };

        var getMaxIndex = function () {
            if (hasClonedLoop) {
                return originalCount;
            }

            return Math.max(0, cards.length - getVisibleCount());
        };

        var setActiveCard = function () {
            var activeIndex = hasClonedLoop && originalCount > 0 ? currentIndex % originalCount : currentIndex;

            cards.forEach(function (card, index) {
                card.classList.toggle('is-slider-active', index % Math.max(1, originalCount) === activeIndex);
            });

            dots.forEach(function (dot, index) {
                var isActive = index === activeIndex;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-current', isActive ? 'true' : 'false');
            });
        };

        var applyTrackPosition = function (animate) {
            if (!track || !cards.length) {
                return;
            }

            track.style.transitionDuration = !animate || prefersReducedMotion ? '0ms' : '';
            track.style.transform = 'translate3d(-' + (currentIndex * getStep()) + 'px, 0, 0)';
            setActiveCard();
        };

        var goToSlide = function (index) {
            if (!track || !cards.length) {
                return;
            }

            var maxIndex = getMaxIndex();

            if (hasClonedLoop && index < 0) {
                currentIndex = originalCount;
                applyTrackPosition(false);

                window.requestAnimationFrame(function () {
                    currentIndex = originalCount - 1;
                    applyTrackPosition(true);
                });
                return;
            }

            if (index > maxIndex) {
                currentIndex = 0;
            } else {
                currentIndex = Math.max(0, index);
            }

            applyTrackPosition(true);
        };

        var updateControls = function () {
            var hasScrollableCards = cards.length > 1;
            prevButton.disabled = !hasScrollableCards;
            nextButton.disabled = !hasScrollableCards;
        };

        var stopAutoSlide = function () {
            if (autoTimer) {
                window.clearInterval(autoTimer);
                autoTimer = null;
            }
        };

        var advanceSlide = function () {
            if (cards.length <= 1) {
                setActiveCard();
                return;
            }

            goToSlide(currentIndex + 1);
        };

        var startAutoSlide = function () {
            if (prefersReducedMotion || cards.length <= 1 || autoTimer) {
                return;
            }

            autoTimer = window.setInterval(advanceSlide, autoDelay);
        };

        var restartAutoSlide = function () {
            stopAutoSlide();
            startAutoSlide();
        };

        prevButton.addEventListener('click', function () {
            goToSlide(currentIndex - 1);
            restartAutoSlide();
        });

        nextButton.addEventListener('click', function () {
            goToSlide(currentIndex + 1);
            restartAutoSlide();
        });

        slider.addEventListener('touchstart', function (event) {
            if (!event.touches || !event.touches.length) {
                return;
            }

            touchStartX = event.touches[0].clientX;
            touchStartY = event.touches[0].clientY;
            stopAutoSlide();
        }, { passive: true });

        slider.addEventListener('touchend', function (event) {
            if (!event.changedTouches || !event.changedTouches.length) {
                startAutoSlide();
                return;
            }

            var deltaX = event.changedTouches[0].clientX - touchStartX;
            var deltaY = event.changedTouches[0].clientY - touchStartY;

            if (Math.abs(deltaX) > 45 && Math.abs(deltaX) > Math.abs(deltaY)) {
                goToSlide(deltaX < 0 ? currentIndex + 1 : currentIndex - 1);
            }

            startAutoSlide();
        }, { passive: true });

        if (sliderShell) {
            sliderShell.addEventListener('mouseenter', stopAutoSlide);
            sliderShell.addEventListener('mouseleave', startAutoSlide);
            sliderShell.addEventListener('focusin', stopAutoSlide);
            sliderShell.addEventListener('focusout', startAutoSlide);
        }

        window.addEventListener('resize', function () {
            updateControls();
            currentIndex = Math.min(currentIndex, getMaxIndex());
            applyTrackPosition(false);
            restartAutoSlide();
        });

        if (track) {
            track.addEventListener('transitionend', function () {
                if (hasClonedLoop && currentIndex === originalCount) {
                    currentIndex = 0;
                    applyTrackPosition(false);
                }
            });
        }

        updateControls();
        goToSlide(0);
        startAutoSlide();
    }

    document.querySelectorAll('[data-project-view-more]').forEach(function (button) {
        var sectionName = button.getAttribute('data-project-view-more');
        var grid = document.querySelector('[data-project-section-grid="' + sectionName + '"]');
        var label = button.querySelector('span');

        if (!grid || !label) {
            return;
        }

        var collapsedText = label.textContent;
        var expandedText = sectionName === 'regular' ? 'Show fewer regular projects' : 'Show fewer featured projects';
        button.setAttribute('aria-expanded', 'false');

        button.addEventListener('click', function () {
            var isExpanded = button.classList.toggle('is-expanded');

            grid.querySelectorAll('.project-card--extra').forEach(function (card) {
                card.classList.toggle('is-hidden', !isExpanded);
            });

            button.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
            label.textContent = isExpanded ? expandedText : collapsedText;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
