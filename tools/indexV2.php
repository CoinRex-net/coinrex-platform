<?php
ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
}

function homeEsc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function homeDisplayName(array $row)
{
    $display_name = trim((string) ($row['reviewer_name'] ?? ''));
    return $display_name !== '' ? $display_name : 'Community Member';
}

function homeShortHash($hash)
{
    $hash = trim((string) $hash);

    if ($hash === '') {
        return 'Proof pending';
    }

    if (strlen($hash) <= 14) {
        return $hash;
    }

    return substr($hash, 0, 6) . '...' . substr($hash, -4);
}

function homeExcerpt($text, $limit = 84)
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));

    if ($text === '') {
        return 'Fresh reviews will appear here as soon as the first approvals go live.';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 1, 'UTF-8')) . '...';
    }

    if (strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(substr($text, 0, $limit - 1)) . '...';
}

function homeInitial($text)
{
    $text = trim((string) $text);

    if ($text === '') {
        return 'C';
    }

    return strtoupper(substr($text, 0, 1));
}

$home_stats = [
    'approved_reviews' => 0,
    'approved_projects' => 0,
    'verified_projects' => 0,
    'active_reviewers' => 0,
    'total_rex_paid' => 0,
    'avg_rating' => 0,
    'trust_score' => 0,
];

$homepage_reviews = [];

try {
    $db = getDBConnection();
    $has_final_rex = tableHasColumn('reviews', 'final_rex');
    $has_review_score = tableHasColumn('reviews', 'review_score');
    $has_verified_projects = tableHasColumn('projects', 'is_verified');

    $reward_expression = $has_final_rex
        ? 'COALESCE(r.final_rex, r.calculated_rex, 0)'
        : 'COALESCE(r.calculated_rex, 0)';

    $trust_expression = $has_review_score
        ? 'COALESCE(AVG(r.review_score), 0)'
        : 'COALESCE(AVG(r.rating) * 20, 0)';

    $stats_stmt = $db->query("
        SELECT
            COUNT(r.id) AS approved_reviews,
            COUNT(DISTINCT r.user_id) AS active_reviewers,
            COALESCE(SUM({$reward_expression}), 0) AS total_rex_paid,
            COALESCE(AVG(r.rating), 0) AS avg_rating,
            {$trust_expression} AS trust_score
        FROM reviews r
        INNER JOIN projects p ON p.id = r.project_id
        WHERE r.status = 'approved'
          AND p.approval_status = 'approved'
    ");
    $review_stats = $stats_stmt ? ($stats_stmt->fetch() ?: []) : [];

    $project_verified_select = $has_verified_projects
        ? 'SUM(CASE WHEN COALESCE(is_verified, 0) = 1 THEN 1 ELSE 0 END)'
        : '0';

    $project_stmt = $db->query("
        SELECT
            COUNT(*) AS approved_projects,
            COALESCE({$project_verified_select}, 0) AS verified_projects
        FROM projects
        WHERE approval_status = 'approved'
    ");
    $project_stats = $project_stmt ? ($project_stmt->fetch() ?: []) : [];

    $home_stats['approved_reviews'] = (int) ($review_stats['approved_reviews'] ?? 0);
    $home_stats['active_reviewers'] = (int) ($review_stats['active_reviewers'] ?? 0);
    $home_stats['total_rex_paid'] = (float) ($review_stats['total_rex_paid'] ?? 0);
    $home_stats['avg_rating'] = (float) ($review_stats['avg_rating'] ?? 0);
    $home_stats['trust_score'] = (float) ($review_stats['trust_score'] ?? 0);
    $home_stats['approved_projects'] = (int) ($project_stats['approved_projects'] ?? 0);
    $home_stats['verified_projects'] = (int) ($project_stats['verified_projects'] ?? 0);

    $review_order = $has_review_score
        ? 'COALESCE(r.review_score, 0) DESC, r.created_at DESC'
        : 'r.rating DESC, r.created_at DESC';

    $reviews_stmt = $db->query("
        SELECT
            r.rating,
            r.review_title,
            r.review_content,
            r.tx_hash,
            {$reward_expression} AS reward_rex,
            COALESCE(NULLIF(TRIM(u.full_name), ''), NULLIF(TRIM(u.username), ''), 'Community Member') AS reviewer_name,
            COALESCE(NULLIF(TRIM(u.level), ''), 'beginner') AS reviewer_level,
            COALESCE(NULLIF(TRIM(u.avatar), ''), '') AS reviewer_avatar,
            p.name AS project_name
        FROM reviews r
        INNER JOIN users u ON u.id = r.user_id
        INNER JOIN projects p ON p.id = r.project_id
        WHERE r.status = 'approved'
          AND p.approval_status = 'approved'
        ORDER BY {$review_order}
        LIMIT 3
    ");
    $homepage_reviews = $reviews_stmt ? ($reviews_stmt->fetchAll() ?: []) : [];
} catch (Throwable $e) {
    $homepage_reviews = [];
}

if (empty($homepage_reviews)) {
    $homepage_reviews = [
        [
            'reviewer_name' => 'CoinRex Pilot',
            'reviewer_level' => 'early member',
            'rating' => 5,
            'review_title' => 'The review board is warming up',
            'review_content' => 'Approved reviews will show up here as soon as the first moderated submissions are published.',
            'tx_hash' => '',
            'reward_rex' => 0,
            'project_name' => 'Public launch queue',
        ],
        [
            'reviewer_name' => 'Dev Hub',
            'reviewer_level' => 'project owner',
            'rating' => 5,
            'review_title' => 'Verified projects will rotate into this spotlight',
            'review_content' => 'As teams complete onboarding and moderation, this section will surface their strongest community feedback.',
            'tx_hash' => '',
            'reward_rex' => 0,
            'project_name' => 'Approved listings',
        ],
        [
            'reviewer_name' => 'Top Reviewer',
            'reviewer_level' => 'community',
            'rating' => 5,
            'review_title' => 'Reward history will appear live here',
            'review_content' => 'Once approved review rewards are flowing, CoinRex will highlight them here instead of placeholder claims.',
            'tx_hash' => '',
            'reward_rex' => 0,
            'project_name' => '$REX earnings feed',
        ],
    ];
}

$hero_review = $homepage_reviews[0];
$hero_project_total = $home_stats['verified_projects'] > 0 ? $home_stats['verified_projects'] : $home_stats['approved_projects'];
$hero_project_label = $home_stats['verified_projects'] > 0 ? 'Verified Projects' : 'Approved Projects';
$hero_review_snippet_source = trim((string) ($hero_review['review_title'] ?? '')) !== ''
    ? $hero_review['review_title']
    : ($hero_review['review_content'] ?? '');
$latest_blog_posts = function_exists('blogGetLatest') ? blogGetLatest(3) : [];

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/index.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/rating-badge.css">
<style>
    .animate-fade-up,
    .animate-scale,
    .animate-fade-right,
    .animate-fade-left {
        opacity: 0;
        transition: opacity 0.7s ease, transform 0.7s ease;
        will-change: opacity, transform;
    }

    .animate-fade-up { transform: translateY(28px); }
    .animate-scale { transform: scale(0.95); }
    .animate-fade-right { transform: translateX(-24px); }
    .animate-fade-left { transform: translateX(24px); }

    .animated {
        opacity: 1 !important;
        transform: none !important;
    }

    .delay-1 { transition-delay: 0.08s; }
    .delay-2 { transition-delay: 0.16s; }
    .delay-3 { transition-delay: 0.24s; }
    .delay-4 { transition-delay: 0.32s; }
</style>

<main class="coinrex-home">
    <section class="hero animate-fade-up">
        <div class="container">
            <div class="hero-grid">
                <div class="hero-content">
                    <div class="hero-badge animate-fade-up">
                        <span class="live-badge"></span>
                        <i class="fas fa-rocket" aria-hidden="true"></i>
                        Launch Week | <?php echo number_format($home_stats['approved_reviews']); ?> approved reviews
                    </div>
                    <h1 class="hero-title animate-fade-up delay-1">
                        Where Crypto Projects
                        <span class="gradient-text">Get Their Crown</span>
                    </h1>
                    <p class="hero-description animate-fade-up delay-2">
                        Real users. Proof-backed reviews. A public scorecard for crypto teams that want credibility and community trust.
                    </p>
                    <div class="hero-buttons animate-fade-up delay-3">
                        <a href="<?php echo AUTH_URL; ?>/auth.php?tab=register" class="btn btn-primary btn-large">
                            <i class="fas fa-bolt" aria-hidden="true"></i>
                            Start Earning Now
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <a href="#how-it-works" class="btn btn-secondary btn-large">
                            <i class="fas fa-circle-info" aria-hidden="true"></i>
                            How It Works
                        </a>
                    </div>
                    <?php echo renderUniversalRating([
                        'provider' => 'coinrex',
                        'value' => (float) ($home_stats['avg_rating'] ?? 0),
                        'scale' => 5,
                        'size' => 'lg',
                        'variant' => 'cr-box-large',
                        'show_count' => false,
                        'class' => 'home-hero-rating-box animate-fade-up delay-4',
                    ]); ?>
                    <div class="hero-stats-mini animate-fade-up delay-4">
                        <div class="stat-item">
                            <strong><?php echo number_format((float) $home_stats['total_rex_paid'], 0); ?> $REX</strong>
                            <span>Paid to reviewers</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo number_format($home_stats['approved_reviews']); ?></strong>
                            <span>Approved reviews</span>
                        </div>
                        <div class="stat-item">
                            <strong><?php echo number_format($hero_project_total); ?></strong>
                            <span><?php echo homeEsc($hero_project_label); ?></span>
                        </div>
                    </div>
                </div>
                <div class="hero-visual animate-scale">
                    <div class="floating-card card-1">
                        <div class="review-header">
                            <span class="avatar"><?php echo homeEsc(homeInitial(homeDisplayName($hero_review))); ?></span>
                            <span class="name"><?php echo homeEsc(homeDisplayName($hero_review)); ?></span>
                            <span class="verified"><i class="fas fa-circle-check" aria-hidden="true"></i></span>
                        </div>
                        <div class="review-score">
                            <i class="fas fa-star" aria-hidden="true"></i>
                            <?php echo number_format((float) ($hero_review['rating'] ?? 0), 1); ?>/5
                        </div>
                        <div class="review-proof">
                            <i class="fas fa-shield-check" aria-hidden="true"></i>
                            Proof-verified review
                        </div>
                        <div class="review-text">"<?php echo homeEsc(homeExcerpt($hero_review_snippet_source, 48)); ?>"</div>
                        <div class="review-earn">
                            <?php echo ((float) ($hero_review['reward_rex'] ?? 0) > 0)
                                ? '+' . number_format((float) ($hero_review['reward_rex'] ?? 0), 0) . ' $REX earned'
                                : 'Reward history pending'; ?>
                        </div>
                    </div>
                    <div class="floating-card card-2">
                        <div class="earn-badge">
                            <i class="fas fa-coins" aria-hidden="true"></i>
                            <?php echo number_format((float) $home_stats['total_rex_paid'], 0); ?> $REX paid
                        </div>
                        <div class="proof-screenshot">
                            <i class="fas fa-shield-alt" aria-hidden="true"></i>
                            <?php echo number_format($home_stats['approved_reviews']); ?> approved proofs
                        </div>
                    </div>
                    <div class="floating-card card-3">
                        <div class="verified-badge">
                            <i class="fas fa-award" aria-hidden="true"></i>
                            <?php echo homeEsc($hero_project_label); ?>
                        </div>
                        <div class="score-circle">
                            <?php echo number_format((float) $home_stats['trust_score'], 0); ?><span>score</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="hero-gradient-bg"></div>
    </section>

    <section class="trust-stats animate-fade-up">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" data-target="<?php echo number_format((float) $home_stats['approved_reviews'], 0, '.', ''); ?>">0</div>
                    <div class="stat-label">Approved Reviews</div>
                    <div class="stat-proof"><i class="fas fa-link" aria-hidden="true"></i> Proof-backed submissions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" data-target="<?php echo number_format((float) $hero_project_total, 0, '.', ''); ?>">0</div>
                    <div class="stat-label"><?php echo homeEsc($hero_project_label); ?></div>
                    <div class="stat-proof"><i class="fas fa-circle-check" aria-hidden="true"></i> Moderated before listing</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" data-target="<?php echo number_format((float) $home_stats['active_reviewers'], 0, '.', ''); ?>">0</div>
                    <div class="stat-label">Active Reviewers</div>
                    <div class="stat-proof"><i class="fas fa-users" aria-hidden="true"></i> Distinct approved contributors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" data-target="<?php echo number_format((float) $home_stats['trust_score'], 1, '.', ''); ?>" data-decimals="1">0</div>
                    <div class="stat-label">Trust Score</div>
                    <div class="stat-proof"><i class="fas fa-star" aria-hidden="true"></i> Based on approved ratings</div>
                </div>
            </div>
            <div class="trust-message">
                <div class="trust-icon"><i class="fas fa-lock" aria-hidden="true"></i></div>
                <p><strong>Every approved review is backed by proof.</strong> CoinRex currently shows <?php echo number_format($home_stats['approved_reviews']); ?> approved reviews from <?php echo number_format($home_stats['active_reviewers']); ?> reviewers across the live marketplace.</p>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="how-it-works animate-fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Simple Process</span>
                <h2>How CoinRex Works</h2>
                <p>Three clear steps to join the review economy or launch a project listing.</p>
            </div>
            <div class="steps-grid">
                <div class="step-card animate-fade-up delay-1">
                    <div class="step-number">1</div>
                    <div class="step-icon"><i class="fas fa-user-plus" aria-hidden="true"></i></div>
                    <h3>Register</h3>
                    <p>Create your reviewer or developer account and set up the trust details that power your profile.</p>
                </div>
                <div class="step-card animate-fade-up delay-2">
                    <div class="step-number">2</div>
                    <div class="step-icon"><i class="fas fa-file-signature" aria-hidden="true"></i></div>
                    <h3>Submit Proof</h3>
                    <p>Reviewers send transaction evidence and screenshots. Project owners submit listings for moderation.</p>
                </div>
                <div class="step-card animate-fade-up delay-3">
                    <div class="step-number">3</div>
                    <div class="step-icon"><i class="fas fa-trophy" aria-hidden="true"></i></div>
                    <h3>Earn Credibility</h3>
                    <p>Approved reviews unlock $REX rewards, and high-performing projects build public trust on the board.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="earning-methods" class="earning-methods animate-fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Monetize Your Voice</span>
                <h2>How Users Earn on CoinRex</h2>
                <p>Quality participation is rewarded, not noise.</p>
            </div>
            <div class="methods-grid">
                <div class="method-card animate-fade-up delay-1">
                    <div class="method-icon"><i class="fas fa-pen-nib" aria-hidden="true"></i></div>
                    <h3>Quality Reviews</h3>
                    <p>Write detailed feedback with real proof and earn project-based $REX after approval.</p>
                    <div class="method-tag">Most Popular</div>
                    <div class="method-earn">Review rewards scale by quality</div>
                </div>
                <div class="method-card animate-fade-up delay-2">
                    <div class="method-icon"><i class="fas fa-scale-balanced" aria-hidden="true"></i></div>
                    <h3>Credibility Scoring</h3>
                    <p>Thoughtful reviews surface higher, helping strong contributors build visible trust over time.</p>
                    <div class="method-earn">Better reviews, stronger profile</div>
                </div>
                <div class="method-card animate-fade-up delay-3">
                    <div class="method-icon"><i class="fas fa-share-nodes" aria-hidden="true"></i></div>
                    <h3>Referral Program</h3>
                    <p>Invite users and developers into the ecosystem and grow alongside the network you bring in.</p>
                    <div class="method-earn">Built-in growth loop</div>
                </div>
                <div class="method-card animate-fade-up delay-4">
                    <div class="method-icon"><i class="fas fa-user-shield" aria-hidden="true"></i></div>
                    <h3>Expert Reputation</h3>
                    <p>Consistent approval quality helps reviewers unlock stronger credibility and higher-value opportunities.</p>
                    <div class="method-earn">Level up your reviewer profile</div>
                </div>
            </div>
            <div class="earn-cta">
                <a href="<?php echo AUTH_URL; ?>/auth.php?tab=register" class="btn btn-primary">
                    <i class="fas fa-chart-line" aria-hidden="true"></i>
                    Start Earning Today
                </a>
            </div>
        </div>
    </section>

    <section class="hub-spotlight animate-fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Inside RexHub</span>
                <h2>TaskHub & BoostHub</h2>
                <p>Two focused modules designed to help users grow from beginner to high-impact contributor.</p>
            </div>
            <div class="hub-grid">
                <article class="hub-card animate-fade-up delay-1">
                    <div class="hub-icon"><i class="fas fa-list-check" aria-hidden="true"></i></div>
                    <h3>TaskHub: Your Level-Up Engine</h3>
                    <p>Complete guided tasks, learn platform workflow, and unlock Pro/Expert progression criteria step by step.</p>
                    <ul>
                        <li>Daily and milestone-based tasks</li>
                        <li>Progress tracking for level upgrades</li>
                        <li>Structured onboarding for long-term rewards</li>
                    </ul>
                    <a href="<?php echo AUTH_URL; ?>/auth.php?tab=register" class="btn btn-secondary">Join and start TaskHub</a>
                </article>
                <article class="hub-card animate-fade-up delay-2">
                    <div class="hub-icon"><i class="fas fa-bolt" aria-hidden="true"></i></div>
                    <h3>BoostHub: Visibility & Momentum</h3>
                    <p>BoostHub helps active users amplify participation through engagement actions that improve profile momentum and discovery.</p>
                    <ul>
                        <li>Action-based growth opportunities</li>
                        <li>Faster profile momentum and trust signals</li>
                        <li>Designed for consistent contributors</li>
                    </ul>
                    <a href="<?php echo AUTH_URL; ?>/auth.php?tab=register" class="btn btn-primary">Unlock BoostHub access</a>
                </article>
            </div>
        </div>
    </section>

    <section class="premium-features animate-fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Project Growth</span>
                <h2>Why Teams Launch on CoinRex</h2>
                <p>Visibility, moderation, and a trust signal users can actually inspect — with paid Sponsored reach that never replaces earned trust.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card animate-fade-up delay-1">
                    <div class="feature-icon"><i class="fas fa-circle-check" aria-hidden="true"></i></div>
                    <h3>Verified Presence</h3>
                    <p>Projects that earn trust stand out with stronger marketplace credibility.</p>
                    <div class="feature-badge">Trust is earned publicly</div>
                </div>
                <div class="feature-card animate-fade-up delay-2">
                    <div class="feature-icon"><i class="fas fa-arrow-trend-up" aria-hidden="true"></i></div>
                    <h3>Priority Discovery</h3>
                    <p>Featured and well-reviewed listings rise faster in front of active reviewers, while Priority Review helps qualified teams move through review faster.</p>
                </div>
                <div class="feature-card animate-fade-up delay-3">
                    <div class="feature-icon"><i class="fas fa-shield-halved" aria-hidden="true"></i></div>
                    <h3>Proof-Based Reputation</h3>
                    <p>Public reviews tied to evidence make trust easier to assess than marketing alone.</p>
                </div>
                <div class="feature-card animate-fade-up delay-4">
                    <div class="feature-icon"><i class="fas fa-bullhorn" aria-hidden="true"></i></div>
                    <h3>Sponsored Visibility</h3>
                    <p>Use Sponsored placement to get stronger exposure, reach active reviewers faster, and build momentum while still earning Featured status the right way.</p>
                    <div class="feature-badge">Visibility can be bought. Trust cannot.</div>
                </div>
            </div>
        </div>
    </section>

    <section class="testimonials animate-fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Live Review Feed</span>
                <h2>Real People, Real Activity</h2>
                <p>The homepage now reflects approved review data from your actual marketplace.</p>
            </div>
            <div class="testimonials-grid">
                <?php foreach ($homepage_reviews as $review): ?>
                    <?php
                    $reviewer_name = homeDisplayName($review);
                    $review_level = trim((string) ($review['reviewer_level'] ?? 'community'));
                    $reviewer_avatar = coinrexNormalizeMediaUrl((string) ($review['reviewer_avatar'] ?? ''));
                    $review_rating = (float) ($review['rating'] ?? 0);
                    $review_reward = (float) ($review['reward_rex'] ?? 0);
                    $review_text_source = trim((string) ($review['review_content'] ?? '')) !== ''
                        ? $review['review_content']
                        : ($review['review_title'] ?? '');
                    ?>
                    <div class="testimonial-card animate-fade-up delay-1">
                        <div class="testimonial-header">
                            <div class="testimonial-user">
                                <div class="user-avatar">
                                    <?php if ($reviewer_avatar !== ''): ?>
                                        <img src="<?php echo homeEsc($reviewer_avatar); ?>" alt="<?php echo homeEsc($reviewer_name); ?> avatar">
                                    <?php else: ?>
                                        <?php echo homeEsc(homeInitial($reviewer_name)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="user-info">
                                    <strong><?php echo homeEsc($reviewer_name); ?></strong>
                                    <?php $home_level_badge = strtolower(trim((string) $review_level)); ?>
                                    <span class="user-level-badge simple-badge <?php echo homeEsc($home_level_badge === 'beginner' ? 'level-badge-beginner' : ($home_level_badge === 'expert' ? 'level-badge-expert' : 'level-badge-pro')); ?>">
                                        <i class="fas fa-gem" aria-hidden="true"></i>
                                        <?php echo homeEsc(ucwords(str_replace(['_', '-'], ' ', $review_level))); ?>
                                    </span>
                                </div>
                            </div>
                            <?php echo renderUniversalRating([
                                'provider' => 'coinrex',
                                'value' => $review_rating,
                                'scale' => 5,
                                'size' => 'sm',
                                'variant' => 'cr-row-small',
                                'show_count' => false,
                                'class' => 'testimonial-rating-badge',
                            ]); ?>
                        </div>
                        <p class="testimonial-text">"<?php echo homeEsc(homeExcerpt($review_text_source, 130)); ?>"</p>
                        <div class="proof-data">
                            <span><i class="fas fa-shield-check" aria-hidden="true"></i> Proof verified</span>
                            <span><i class="fas fa-layer-group" aria-hidden="true"></i> <?php echo homeEsc($review['project_name'] ?? 'Marketplace listing'); ?></span>
                        </div>
                        <div class="testimonial-earn">
                            <i class="fas fa-coins" aria-hidden="true"></i>
                            <?php echo $review_reward > 0 ? number_format($review_reward, 0) . ' $REX earned' : 'Awaiting payout history'; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($latest_blog_posts)): ?>
    <section class="home-blog-section animate-fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Learning Hub</span>
                <h2>Latest from CoinRex Blog</h2>
                <p>Guides, product updates, and practical walkthroughs for users and builders.</p>
            </div>
            <div class="home-blog-grid home-blog-grid-count-<?php echo (int) count($latest_blog_posts); ?>">
                <?php foreach ($latest_blog_posts as $blog_post): ?>
                    <article class="home-blog-card animate-fade-up delay-1">
                        <div class="home-blog-card-head">
                            <span class="home-blog-chip"><i class="fas fa-book-open" aria-hidden="true"></i> Blog Update</span>
                            <span class="home-blog-date"><i class="fas fa-clock" aria-hidden="true"></i> <?php echo date('M d, Y', strtotime((string) ($blog_post['published_at'] ?: $blog_post['created_at']))); ?></span>
                        </div>
                        <h3 class="home-blog-title">
                            <a class="home-blog-title-link" href="<?php echo BASE_URL; ?>/blog-post.php/<?php echo urlencode((string) ($blog_post['slug'] ?? '')); ?>">
                                <?php echo homeEsc((string) ($blog_post['title'] ?? 'Blog Post')); ?>
                            </a>
                        </h3>
                        <p class="home-blog-text"><?php echo homeEsc(homeExcerpt((string) ($blog_post['excerpt'] ?? ''), 120)); ?></p>
                        <div class="home-blog-meta">
                            <span><i class="fas fa-book-open"></i> <?php echo (int) blogReadTime((string) ($blog_post['excerpt'] ?? '')) + 1; ?> min read</span>
                            <span><i class="fas fa-arrow-trend-up"></i> Learn faster with CoinRex</span>
                        </div>
                        <a href="<?php echo BASE_URL; ?>/blog-post.php/<?php echo urlencode((string) ($blog_post['slug'] ?? '')); ?>" class="home-blog-link">Read article <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="home-blog-cta">
                <a href="<?php echo BASE_URL; ?>/blog.php" class="btn btn-secondary">Explore all articles</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="cta-section animate-fade-up">
        <div class="container">
            <div class="cta-card">
                <h2>Ready to Build the Next Phase?</h2>
                <p>Join as a reviewer to start earning, or enter the Dev Hub to submit your project for moderation.</p>
                <div class="cta-buttons">
                    <a href="<?php echo AUTH_URL; ?>/auth.php?tab=register" class="btn btn-primary btn-large">
                        <i class="fas fa-user-plus" aria-hidden="true"></i>
                        Register as User
                    </a>
                    <a href="<?php echo BASE_URL; ?>/devhub/pages/auth/register.php" class="btn btn-secondary btn-large">
                        <i class="fas fa-building" aria-hidden="true"></i>
                        List Your Project
                    </a>
                </div>
                <div class="cta-note">
                    <?php echo number_format($home_stats['active_reviewers']); ?> active reviewers &bull;
                    <?php echo number_format($home_stats['approved_projects']); ?> approved projects &bull;
                    Join free
                </div>
            </div>
        </div>
    </section>
</main>

<script>
    const animateElements = document.querySelectorAll('.animate-fade-up, .animate-scale, .animate-fade-right, .animate-fade-left');

    if ('IntersectionObserver' in window) {
        const scrollObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('animated');
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -30px 0px' });

        animateElements.forEach((element) => {
            scrollObserver.observe(element);
        });
    } else {
        animateElements.forEach((element) => element.classList.add('animated'));
    }

    window.addEventListener('load', () => {
        animateElements.forEach((element) => {
            if (element.getBoundingClientRect().top < window.innerHeight) {
                element.classList.add('animated');
            }
        });
    });

    function animateNumbers() {
        const statNumbers = document.querySelectorAll('.stat-number');

        statNumbers.forEach((stat) => {
            const target = parseFloat(stat.getAttribute('data-target') || '0');
            const decimals = parseInt(stat.getAttribute('data-decimals') || (Number.isInteger(target) ? '0' : '1'), 10);
            let current = 0;
            const increment = target === 0 ? 0 : target / 80;

            const renderValue = (value) => {
                if (decimals > 0) {
                    stat.textContent = value.toFixed(decimals);
                    return;
                }

                stat.textContent = Math.round(value).toLocaleString();
            };

            const updateCounter = () => {
                if (increment > 0 && current < target) {
                    current = Math.min(target, current + increment);
                    renderValue(current);
                    setTimeout(updateCounter, 20);
                    return;
                }

                renderValue(target);
            };

            updateCounter();
        });
    }

    const statsSection = document.querySelector('.trust-stats');

    if (statsSection && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                animateNumbers();
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.3 });

        observer.observe(statsSection);
    } else {
        animateNumbers();
    }

    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');

            if (!href || href === '#') {
                return;
            }

            const target = document.querySelector(href);

            if (!target) {
                return;
            }

            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth' });
        });
    });
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ob_end_flush(); ?>
