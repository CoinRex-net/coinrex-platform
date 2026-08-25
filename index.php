<?php
// index.php - CoinRex Redesigned Homepage
ob_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/public/dashboard.php');
}

function homeEsc($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function homeDisplayName(array $row) {
    $display_name = trim((string) ($row['reviewer_name'] ?? ''));
    return $display_name !== '' ? $display_name : 'Community Member';
}

function homeShortHash($hash) {
    $hash = trim((string) $hash);
    if ($hash === '') return 'Proof pending';
    if (strlen($hash) <= 14) return $hash;
    return substr($hash, 0, 6) . '...' . substr($hash, -4);
}

function homeExcerpt($text, $limit = 84) {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    if ($text === '') return 'Fresh reviews will appear here as soon as the first approvals go live.';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $limit) return $text;
        return rtrim(mb_substr($text, 0, $limit - 1, 'UTF-8')) . '...';
    }
    if (strlen($text) <= $limit) return $text;
    return rtrim(substr($text, 0, $limit - 1)) . '...';
}

function homeInitial($text) {
    $text = trim((string) $text);
    return $text === '' ? 'C' : strtoupper(substr($text, 0, 1));
}

// Database queries
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
        WHERE r.status = 'approved' AND p.approval_status = 'approved'
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
        WHERE r.status = 'approved' AND p.approval_status = 'approved'
        ORDER BY {$review_order}
        LIMIT 4
    ");
    $homepage_reviews = $reviews_stmt ? ($reviews_stmt->fetchAll() ?: []) : [];
} catch (Throwable $e) {
    $homepage_reviews = [];
}

// Placeholder reviews if empty
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
        [
            'reviewer_name' => 'Growth Tracker',
            'reviewer_level' => 'analyst',
            'rating' => 4,
            'review_title' => 'This section updates as new approvals come in',
            'review_content' => 'Bookmark this space — fresh review data will populate here as the community contributes.',
            'tx_hash' => '',
            'reward_rex' => 0,
            'project_name' => 'Live review section',
        ],
    ];
}

$hero_review = $homepage_reviews[0];
$hero_review_avatar = coinrexNormalizeMediaUrl((string) ($hero_review['reviewer_avatar'] ?? ''));
$hero_project_total = $home_stats['verified_projects'] > 0 ? $home_stats['verified_projects'] : $home_stats['approved_projects'];
$hero_project_label = $home_stats['verified_projects'] > 0 ? 'Verified Projects' : 'Approved Projects';
$hero_review_snippet_source = trim((string) ($hero_review['review_title'] ?? '')) !== ''
    ? $hero_review['review_title']
    : ($hero_review['review_content'] ?? '');
$latest_blog_posts = function_exists('blogGetLatest') ? blogGetLatest(3) : [];

$page_title = 'CoinRex - A Web3 Trust Layer';
$meta_description = 'CoinRex is a Web3 trust layer for proof-backed crypto project reviews, user rewards, and transparent project discovery.';
$meta_keywords = 'CoinRex, Web3 trust layer, crypto reviews, blockchain project reviews, proof-backed reviews';

require_once __DIR__ . '/includes/header.php';
?>

<!-- New CoinRex Homepage Stylesheet -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/homepage-v2.css?v=<?php echo (int) @filemtime(__DIR__ . '/assets/css/homepage-v2.css'); ?>">
<!-- Homepage Background Animations -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/homepage-animations.css">

<main class="cr-home">
    
    <!-- ==================== HERO SECTION ==================== -->
    <section class="cr-hero" id="hero">
        <div class="cr-container">
            <div class="cr-hero-inner">
                
                <!-- Hero Badge -->
                <div class="cr-hero-badge">
                    <span class="cr-live-dot"></span>
                    Crypto Review & Trust Platform
                </div>

                <!-- Hero Headline - Rotating Tagline Animation -->
                <h1 class="cr-hero-title cr-hero-title-rotating">
                    <span class="cr-hero-line cr-hero-line-1"><span class="cr-gold">Real Reviews</span> for Crypto Projects.</span>
                    <span class="cr-hero-line cr-hero-line-2"><span class="cr-gold">Real Rewards</span> for Honest Users.</span>
                </h1>

                <!-- Hero Description - Plain Language -->
                <p class="cr-hero-desc">
                    CoinRex is where <mark class="cr-highlight">users review crypto projects with proof</mark> and earn 
                    <mark class="cr-highlight">$REX rewards</mark> for quality contributions. Developers list their 
                    projects here to <mark class="cr-highlight">build public trust</mark> through real user feedback.
                </p>

                <!-- Dual CTA Buttons - Clear Paths -->
                <div class="cr-hero-actions">
                    <a href="<?php echo AUTH_URL; ?>/auth.php?tab=register" class="cr-btn cr-btn-primary cr-btn-lg">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                        Start Reviewing & Earn
                    </a>
                    <a href="<?php echo BASE_URL; ?>/devhub/pages/auth/register.php" class="cr-btn cr-btn-secondary cr-btn-lg">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <line x1="12" y1="8" x2="12" y2="16"/>
                            <line x1="8" y1="12" x2="16" y2="12"/>
                        </svg>
                        List Your Project & Build Trust
                    </a>
                </div>

                <!-- Live Stats Bar -->
                <div class="cr-hero-stats">
                    <div class="cr-hero-stat">
                        <strong><?php echo number_format($home_stats['approved_reviews']); ?>+</strong>
                        <span>Approved Reviews</span>
                    </div>
                    <div class="cr-hero-stat-divider"></div>
                    <div class="cr-hero-stat">
                        <strong><?php echo number_format($hero_project_total); ?>+</strong>
                        <span><?php echo homeEsc($hero_project_label); ?></span>
                    </div>
                    <div class="cr-hero-stat-divider"></div>
                    <div class="cr-hero-stat">
                        <strong><?php echo number_format((float) $home_stats['total_rex_paid'], 0); ?> $REX</strong>
                        <span>Paid to Reviewers</span>
                    </div>
                </div>
            </div>

            <!-- Hero Visual Card -->
            <div class="cr-hero-visual">
                <div class="floating-card card-1 cr-floating-card-primary">
                    <div class="review-header">
                        <?php if ($hero_review_avatar !== ''): ?>
                            <span class="avatar avatar-image" style="background-image: url('<?php echo homeEsc($hero_review_avatar); ?>');" aria-label="<?php echo homeEsc(homeDisplayName($hero_review)); ?> avatar"></span>
                        <?php else: ?>
                            <span class="avatar"><?php echo homeEsc(homeInitial(homeDisplayName($hero_review))); ?></span>
                        <?php endif; ?>
                        <span class="name"><?php echo homeEsc(homeDisplayName($hero_review)); ?></span>
                        <span class="verified"><i class="fas fa-circle-check" aria-hidden="true"></i></span>
                    </div>
                    <div class="review-proof">
                        <i class="fas fa-shield-check" aria-hidden="true"></i>
                        Proof-verified review
                    </div>
                    <div class="review-text">"<?php echo homeEsc(homeExcerpt($hero_review_snippet_source, 52)); ?>"</div>
                    <div class="review-earn">
                        <?php echo ((float) ($hero_review['reward_rex'] ?? 0) > 0)
                            ? '+' . number_format((float) ($hero_review['reward_rex'] ?? 0), 0) . ' $REX earned'
                            : 'Reward history pending'; ?>
                    </div>
                </div>
                
                <div class="floating-card card-2 cr-floating-card-secondary">
                    <div class="earn-badge">
                        <i class="fas fa-coins" aria-hidden="true"></i>
                        <?php echo number_format((float) $home_stats['total_rex_paid'], 0); ?> $REX paid
                    </div>
                    <div class="proof-screenshot">
                        <i class="fas fa-shield-alt" aria-hidden="true"></i>
                        <?php echo number_format($home_stats['approved_reviews']); ?> approved proofs
                    </div>
                </div>

                <div class="floating-card card-3 cr-floating-card-tertiary">
                    <div class="verified-badge">
                        <i class="fas fa-award" aria-hidden="true"></i>
                        <?php echo homeEsc($hero_project_label); ?>
                    </div>
                    <div class="score-panel">
                        <strong><?php echo number_format((float) $home_stats['trust_score'], 0); ?></strong>
                        <span>Trust Score</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Background gradient -->
        <div class="cr-hero-bg"></div>
    </section>

    <!-- ==================== WHAT IS COINREX ==================== -->
    <section class="cr-what-is" id="what-is">
        <div class="cr-container">
            <div class="cr-section-tag">About the Platform</div>
            <h2 class="cr-section-title">What is CoinRex?</h2>
            <p class="cr-section-desc">
                A simple explanation — in plain language.
            </p>
            
            <div class="cr-explanation-grid">
                <div class="cr-explanation-card">
                    <div class="cr-explanation-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                        </svg>
                    </div>
                    <h3>Users Submit Reviews</h3>
                    <p>People share their real experience with crypto projects — what they liked, what they didn't, and provide proof to back it up.</p>
                </div>
                
                <div class="cr-explanation-card">
                    <div class="cr-explanation-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                    <h3>Projects Get Listed</h3>
                    <p>Developers submit their crypto projects for public listing. Once approved, real users can review them openly.</p>
                </div>
                
                <div class="cr-explanation-card">
                    <div class="cr-explanation-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <h3>Trust Becomes Visible</h3>
                    <p>Reviews, proof, and moderation create public trust signals. Users know which projects are real, and developers earn credibility.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== CHOOSE YOUR PATH ==================== -->
    <section class="cr-choose-path" id="paths">
        <div class="cr-container">
            <div class="cr-section-tag">Get Started</div>
            <h2 class="cr-section-title">Choose Your Path on CoinRex</h2>
            <p class="cr-section-desc">
                The platform serves two groups. Pick the one that describes you.
            </p>
            
            <div class="cr-path-grid">
                <!-- Reviewer Path -->
                <div class="cr-path-card cr-path-reviewer">
                    <div class="cr-path-badge">Most Popular</div>
                    <div class="cr-path-icon-wrap">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <h3>I'm a User / Reviewer</h3>
                    <p>I want to explore crypto projects, share honest reviews, and earn rewards for quality contributions.</p>
                    <ul class="cr-path-list">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Browse listed crypto projects
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Write reviews with proof
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Earn $REX rewards
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Build your reviewer profile
                        </li>
                    </ul>
                    <a href="<?php echo AUTH_URL; ?>/auth.php?tab=register" class="cr-btn cr-btn-primary cr-btn-block">
                        Join as Reviewer
                    </a>
                </div>

                <!-- Developer Path -->
                <div class="cr-path-card cr-path-developer">
                    <div class="cr-path-icon-wrap">
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="16 18 22 12 16 6"/>
                            <polyline points="8 6 2 12 8 18"/>
                        </svg>
                    </div>
                    <h3>I'm a Developer / Project Owner</h3>
                    <p>I want to list my crypto project, get real user reviews, and build public credibility and visibility.</p>
                    <ul class="cr-path-list">
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Submit project for listing
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Receive public user reviews
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Build trust with real feedback
                        </li>
                        <li>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            Improve visibility & discovery
                        </li>
                    </ul>
                    <a href="<?php echo BASE_URL; ?>/devhub/pages/auth/register.php" class="cr-btn cr-btn-secondary cr-btn-block">
                        Join as Developer
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== HOW IT WORKS ==================== -->
    <section class="cr-how-it-works" id="how-it-works">
        <div class="cr-container">
            <div class="cr-section-tag">Simple Process</div>
            <h2 class="cr-section-title">How CoinRex Works</h2>
            <p class="cr-section-desc">
                Three steps. Two paths. One platform for trust.
            </p>
            
            <!-- Tabs for two paths -->
            <div class="cr-tabs">
                <button class="cr-tab active" data-tab="reviewer-flow">For Reviewers</button>
                <button class="cr-tab" data-tab="developer-flow">For Developers</button>
            </div>
            
            <!-- Reviewer Flow -->
            <div class="cr-flow-panel active" id="reviewer-flow">
                <div class="cr-steps">
                    <div class="cr-step">
                        <div class="cr-step-number">1</div>
                        <div class="cr-step-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                        </div>
                        <h3>Browse Projects</h3>
                        <p>Explore the list of approved crypto projects on CoinRex. Find ones you've used or want to learn about.</p>
                    </div>
                    
                    <div class="cr-step-connector">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </div>
                    
                    <div class="cr-step">
                        <div class="cr-step-number">2</div>
                        <div class="cr-step-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </div>
                        <h3>Write Review + Submit Proof</h3>
                        <p>Share your honest experience. Add screenshots, transaction hashes, or other proof to back your review.</p>
                    </div>
                    
                    <div class="cr-step-connector">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </div>
                    
                    <div class="cr-step">
                        <div class="cr-step-number">3</div>
                        <div class="cr-step-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="8" r="6"/>
                                <path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/>
                            </svg>
                        </div>
                        <h3>Earn $REX & Grow</h3>
                        <p>Approved reviews earn $REX rewards. Build a stronger reviewer profile as you contribute more.</p>
                    </div>
                </div>
            </div>
            
            <!-- Developer Flow -->
            <div class="cr-flow-panel" id="developer-flow">
                <div class="cr-steps">
                    <div class="cr-step">
                        <div class="cr-step-number">1</div>
                        <div class="cr-step-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <h3>Submit Your Project</h3>
                        <p>Fill in your project details and submit for moderation. Our team reviews and approves quality listings.</p>
                    </div>
                    
                    <div class="cr-step-connector">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </div>
                    
                    <div class="cr-step">
                        <div class="cr-step-number">2</div>
                        <div class="cr-step-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                        </div>
                        <h3>Get Reviewed by Real Users</h3>
                        <p>Once listed, your project becomes visible to reviewers. They share honest feedback backed by proof.</p>
                    </div>
                    
                    <div class="cr-step-connector">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </div>
                    
                    <div class="cr-step">
                        <div class="cr-step-number">3</div>
                        <div class="cr-step-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                            </svg>
                        </div>
                        <h3>Build Trust & Visibility</h3>
                        <p>Positive reviews build your trust score. More trust means better visibility and stronger credibility.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== TRUST SIGNALS ==================== -->
    <section class="cr-trust-signals" id="trust">
        <div class="cr-container">
            <div class="cr-section-tag">Platform Activity</div>
            <h2 class="cr-section-title">Trust Is Already Building</h2>
            <p class="cr-section-desc">
                Real numbers from the CoinRex marketplace — updated live.
            </p>
            
            <div class="cr-signals-grid">
                <div class="cr-signal-card">
                    <div class="cr-signal-number" data-target="<?php echo $home_stats['approved_reviews']; ?>">0</div>
                    <div class="cr-signal-label">Approved Reviews</div>
                    <div class="cr-signal-sub">Proof-backed & moderated</div>
                </div>
                <div class="cr-signal-card">
                    <div class="cr-signal-number" data-target="<?php echo $hero_project_total; ?>">0</div>
                    <div class="cr-signal-label"><?php echo homeEsc($hero_project_label); ?></div>
                    <div class="cr-signal-sub">Moderated before listing</div>
                </div>
                <div class="cr-signal-card">
                    <div class="cr-signal-number" data-target="<?php echo $home_stats['active_reviewers']; ?>">0</div>
                    <div class="cr-signal-label">Active Reviewers</div>
                    <div class="cr-signal-sub">Distinct contributors</div>
                </div>
                <div class="cr-signal-card">
                    <div class="cr-signal-number" data-target="<?php echo number_format((float) $home_stats['trust_score'], 1, '.', ''); ?>" data-decimals="1">0</div>
                    <div class="cr-signal-label">Trust Score</div>
                    <div class="cr-signal-sub">Based on approved ratings</div>
                </div>
            </div>
            
            <div class="cr-trust-note">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                <p><strong>Every approved review requires proof.</strong> CoinRex currently has <?php echo number_format($home_stats['approved_reviews']); ?> approved reviews from <?php echo number_format($home_stats['active_reviewers']); ?> reviewers across the marketplace.</p>
            </div>
        </div>
    </section>

    <!-- ==================== LIVE REVIEWS ==================== -->
    <section class="cr-live-reviews" id="reviews">
        <div class="cr-container">
            <div class="cr-section-tag">Community Activity</div>
            <h2 class="cr-section-title">Real Reviews from Real Users</h2>
            <p class="cr-section-desc">
                These reviews were submitted with proof and approved after moderation.
            </p>
            
            <div class="cr-reviews-grid">
                <?php foreach ($homepage_reviews as $review): ?>
                    <?php
                    $reviewer_name = homeDisplayName($review);
                    $reviewer_level_key = strtolower(trim((string) ($review['reviewer_level'] ?? 'pro')));
                    $review_rating = (float) ($review['rating'] ?? 0);
                    $review_reward = (float) ($review['reward_rex'] ?? 0);
                    $review_text = trim((string) ($review['review_content'] ?? '')) !== ''
                        ? $review['review_content']
                        : ($review['review_title'] ?? '');
                    ?>
                    <div class="cr-review-card">
                        <div class="cr-review-header">
                            <div class="cr-review-user">
                                <div class="cr-review-avatar">
                                    <?php echo homeEsc(homeInitial($reviewer_name)); ?>
                                </div>
                                <div>
                                    <strong><?php echo homeEsc($reviewer_name); ?></strong>
                                    <span class="cr-review-level-badge simple-badge <?php echo homeEsc($reviewer_level_key === 'beginner' ? 'level-badge-beginner' : ($reviewer_level_key === 'expert' ? 'level-badge-expert' : 'level-badge-pro')); ?>">
                                        <i class="fas fa-gem" aria-hidden="true"></i>
                                        <?php echo homeEsc(ucwords(str_replace(['_', '-'], ' ', $review['reviewer_level'] ?? 'community'))); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="cr-review-badge">
                                <i class="fas fa-shield-check" aria-hidden="true"></i>
                                Approved Review
                            </div>
                        </div>
                        <p class="cr-review-text">"<?php echo homeEsc(homeExcerpt($review_text, 140)); ?>"</p>
                        <div class="cr-review-meta">
                            <span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                                Proof verified
                            </span>
                            <span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                                </svg>
                                <?php echo homeEsc($review['project_name'] ?? 'Listed project'); ?>
                            </span>
                        </div>
                        <?php if ($review_reward > 0): ?>
                        <div class="cr-review-reward">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="12" y1="6" x2="12" y2="12"/>
                                <line x1="12" y1="12" x2="16" y2="14"/>
                            </svg>
                            Earned <?php echo number_format($review_reward, 0); ?> $REX
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ==================== EARNING FOR REVIEWERS ==================== -->
    <section class="cr-earning" id="earning">
        <div class="cr-container">
            <div class="cr-section-tag">For Reviewers</div>
            <h2 class="cr-section-title">How Reviewers Earn on CoinRex</h2>
            <p class="cr-section-desc">
                Rewards go to quality contributions, not noise.
            </p>
            
            <div class="cr-earning-grid">
                <div class="cr-earning-card">
                    <div class="cr-earning-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </div>
                    <h3>Quality Reviews</h3>
                    <p>Write detailed, proof-backed reviews about projects you've used. Better reviews = better rewards.</p>
                    <div class="cr-earning-tag">Most Popular</div>
                </div>
                
                <div class="cr-earning-card">
                    <div class="cr-earning-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                            <polyline points="17 6 23 6 23 12"/>
                        </svg>
                    </div>
                    <h3>Credibility Growth</h3>
                    <p>Consistent quality reviews improve your reviewer profile and help you reach Pro/Expert levels.</p>
                </div>
                
                <div class="cr-earning-card">
                    <div class="cr-earning-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                    </div>
                    <h3>Referral Program</h3>
                    <p>Invite other quality reviewers and developers. Grow the ecosystem together.</p>
                </div>
                
                <div class="cr-earning-card">
                    <div class="cr-earning-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    </div>
                    <h3>Expert Reputation</h3>
                    <p>High-quality contributors get noticed and gain preferred visibility in the marketplace.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== FOR DEVELOPERS ==================== -->
    <section class="cr-developers" id="developers">
        <div class="cr-container">
            <div class="cr-section-tag">For Developers</div>
            <h2 class="cr-section-title">Why Developers Choose CoinRex</h2>
            <p class="cr-section-desc">
                Build trust that users can verify — not just claims they have to believe.
            </p>
            
            <div class="cr-developer-grid">
                <div class="cr-developer-card">
                    <div class="cr-developer-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                        </svg>
                    </div>
                    <h3>Verified Presence</h3>
                    <p>Get listed on the platform and let real users verify your project's legitimacy through public reviews.</p>
                </div>
                
                <div class="cr-developer-card">
                    <div class="cr-developer-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <h3>Proof-Based Reputation</h3>
                    <p>Reviews come with proof. Trust becomes something users can inspect, not just guess at.</p>
                </div>
                
                <div class="cr-developer-card">
                    <div class="cr-developer-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <h3>Sponsored Visibility</h3>
                    <p>Boost your project's reach with Sponsored placement while your trust score grows organically.</p>
                    <div class="cr-developer-note">Visibility can be bought. Trust must be earned.</div>
                </div>
                
                <div class="cr-developer-card">
                    <div class="cr-developer-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <h3>Priority Discovery</h3>
                    <p>Highly-rated projects rise in the marketplace, gaining more visibility from active reviewers.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== OUR PARTNER ==================== -->
    <section class="cr-partner" id="partner" aria-labelledby="partner-title">
        <div class="cr-container">
            <div class="cr-section-tag">Our Partner</div>
            <h2 class="cr-section-title" id="partner-title">Insight Meets Innovation</h2>

            <div class="cr-partner-card">
                <a
                    href="https://cryptothreads.io"
                    class="cr-partner-logo-link"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Visit Cryptothreads.io (opens in a new tab)"
                >
                    <img
                        src="<?php echo ASSETS_URL; ?>/images/logoCT.png"
                        alt="Cryptothreads.io"
                        class="cr-partner-logo"
                        loading="lazy"
                        decoding="async"
                    >
                </a>

                <p class="cr-partner-description">
                    Research-backed crypto insights and structured market analysis from across Asia.
                </p>

                <div class="cr-partner-actions">
                    <a
                        href="https://cryptothreads.io"
                        class="cr-partner-website"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        cryptothreads.io
                        <span aria-hidden="true">↗</span>
                    </a>

                    <a
                        href="https://cryptothreads.io"
                        class="cr-btn cr-btn-primary cr-partner-cta"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        Explore Cryptothreads.io
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>
    <!-- ==================== BLOG ==================== -->
    <?php if (!empty($latest_blog_posts)): ?>
    <section class="cr-blog" id="blog">
        <div class="cr-container">
            <div class="cr-section-tag">Learning Hub</div>
            <h2 class="cr-section-title">Latest from the CoinRex Blog</h2>
            <p class="cr-section-desc">
                Guides, updates, and practical knowledge for reviewers and developers.
            </p>
            
            <div class="cr-blog-grid cr-blog-grid-count-<?php echo (int) count($latest_blog_posts); ?>">
                <?php foreach ($latest_blog_posts as $blog_post): ?>
                    <article class="cr-blog-card">
                        <div class="cr-blog-meta-top">
                            <span>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                <?php echo date('M d, Y', strtotime((string) ($blog_post['published_at'] ?: $blog_post['created_at']))); ?>
                            </span>
                            <span>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M12 8v4l3 3"></path>
                                    <circle cx="12" cy="12" r="10"></circle>
                                </svg>
                                <?php echo (int) blogReadTime((string) ($blog_post['excerpt'] ?? '')) + 1; ?> min read
                            </span>
                        </div>
                        <h3>
                            <a href="<?php echo BASE_URL; ?>/public/blog-post.php/<?php echo urlencode((string) ($blog_post['slug'] ?? '')); ?>">
                                <?php echo homeEsc((string) ($blog_post['title'] ?? 'Blog Post')); ?>
                            </a>
                        </h3>
                        <p><?php echo homeEsc(homeExcerpt((string) ($blog_post['excerpt'] ?? ''), 110)); ?></p>
                        <a href="<?php echo BASE_URL; ?>/public/blog-post.php/<?php echo urlencode((string) ($blog_post['slug'] ?? '')); ?>" class="cr-btn cr-btn-secondary cr-blog-link-btn">
                            Read article
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="5" y1="12" x2="19" y2="12"/>
                                <polyline points="12 5 19 12 12 19"/>
                            </svg>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
            
            <div class="cr-blog-cta">
                <a href="<?php echo BASE_URL; ?>/public/blog.php" class="cr-btn cr-btn-secondary">View All Articles</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ==================== FINAL CTA ==================== -->
    <section class="cr-final-cta" id="join">
        <div class="cr-container">
            <div class="cr-cta-box">
                <h2>Ready to Join CoinRex?</h2>
                <p>
                    Choose your path. Review projects and earn rewards, or list your project and build trust with real user feedback.
                </p>
                <div class="cr-cta-actions">
                    <a href="<?php echo AUTH_URL; ?>/auth.php?tab=register" class="cr-btn cr-btn-primary cr-btn-lg">
                        Start Reviewing
                    </a>
                    <a href="<?php echo BASE_URL; ?>/devhub/pages/auth/register.php" class="cr-btn cr-btn-secondary cr-btn-lg">
                        List a Project
                    </a>
                </div>
                <p class="cr-cta-footer">
                    <?php echo number_format($home_stats['active_reviewers']); ?> active reviewers • 
                    <?php echo number_format($home_stats['approved_projects']); ?> approved projects • 
                    Free to join
                </p>
            </div>
        </div>
    </section>

</main>

<script>
    // Tab switching for How It Works
    document.querySelectorAll('.cr-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const targetId = this.getAttribute('data-tab');
            
            // Update active tab
            document.querySelectorAll('.cr-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Show target panel, hide others
            document.querySelectorAll('.cr-flow-panel').forEach(panel => {
                panel.classList.remove('active');
                if (panel.id === targetId) {
                    panel.classList.add('active');
                }
            });
        });
    });

    // Animated counter for trust signals
    function animateCounters() {
        const counters = document.querySelectorAll('.cr-signal-number');
        
        counters.forEach(counter => {
            const target = parseFloat(counter.getAttribute('data-target') || '0');
            const decimals = parseInt(counter.getAttribute('data-decimals') || (Number.isInteger(target) ? '0' : '1'), 10);
            let current = 0;
            const increment = target === 0 ? 0 : target / 60;
            
            const render = (value) => {
                if (decimals > 0) {
                    counter.textContent = value.toFixed(decimals);
                } else {
                    counter.textContent = Math.round(value).toLocaleString();
                }
            };
            
            const update = () => {
                if (increment > 0 && current < target) {
                    current = Math.min(target, current + increment);
                    render(current);
                    requestAnimationFrame(() => setTimeout(update, 25));
                } else {
                    render(target);
                }
            };
            
            update();
        });
    }

    // Observe counters section
    const signalsSection = document.querySelector('.cr-trust-signals');
    if (signalsSection && 'IntersectionObserver' in window) {
        let animated = false;
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !animated) {
                    animated = true;
                    animateCounters();
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });
        observer.observe(signalsSection);
    } else {
        animateCounters();
    }

    // Scroll reveal animations
    const revealElements = document.querySelectorAll('.cr-explanation-card, .cr-path-card, .cr-step, .cr-signal-card, .cr-review-card, .cr-earning-card, .cr-developer-card, .cr-partner-card, .cr-blog-card');
    
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        
        revealElements.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(24px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            revealObserver.observe(el);
        });
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (!href || href === '#') return;
            const target = document.querySelector(href);
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
</script>

<!-- Homepage Background Animation Script -->
<script src="<?php echo ASSETS_URL; ?>/js/homepage-animations.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ob_end_flush(); ?>
