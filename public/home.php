<?php
ob_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
}

function homeTestEsc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function homeTestDisplayName(array $row)
{
    $display_name = trim((string) ($row['reviewer_name'] ?? ''));
    return $display_name !== '' ? $display_name : 'Community Member';
}

function homeTestExcerpt($text, $limit = 140)
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));

    if ($text === '') {
        return 'CoinRex helps people understand crypto projects through public, proof-backed reviews.';
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

function homeTestInitial($text)
{
    $text = trim((string) $text);
    return $text === '' ? 'C' : strtoupper(substr($text, 0, 1));
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
            'reviewer_name' => 'CoinRex Guide',
            'reviewer_level' => 'beginner',
            'review_title' => 'This page explains CoinRex in a simpler way',
            'review_content' => 'A new visitor should understand quickly that CoinRex is for reviewers who want to earn and for projects that want to build trust.',
            'rating' => 5,
            'reward_rex' => 0,
            'project_name' => 'CoinRex Overview',
            'reviewer_avatar' => '',
        ],
    ];
}

$latest_blog_posts = function_exists('blogGetLatest') ? blogGetLatest(3) : [];
$verified_or_approved = $home_stats['verified_projects'] > 0 ? $home_stats['verified_projects'] : $home_stats['approved_projects'];
$verified_or_approved_label = $home_stats['verified_projects'] > 0 ? 'Verified Projects' : 'Approved Projects';

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/home.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/rating-badge.css">

<main class="home-test-page">
    <section class="home-test-hero home-test-hero-simple">
        <div class="container">
            <div class="home-test-hero-simple-box">
                <span class="home-test-kicker">Beginner Friendly Homepage Test</span>
                <h1>CoinRex is a platform where people review crypto projects with proof.</h1>
                <p class="home-test-lead">If you are a <strong>user</strong>, you can share your real experience and earn rewards. If you are a <strong>project owner</strong>, you can list your project and build public trust.</p>
                <div class="home-test-hero-quick-answers">
                    <div class="home-test-answer-card">
                        <strong>What is this?</strong>
                        <span>A crypto review + trust platform</span>
                    </div>
                    <div class="home-test-answer-card">
                        <strong>Who is it for?</strong>
                        <span>Reviewers and project owners</span>
                    </div>
                    <div class="home-test-answer-card">
                        <strong>What should I do?</strong>
                        <span>Pick your role and get started</span>
                    </div>
                </div>
                <div class="home-test-hero-actions">
                    <a href="<?php echo AUTH_URL; ?>/auth.php?tab=register" class="btn btn-primary btn-large">I want to Review Projects</a>
                    <a href="<?php echo BASE_URL; ?>/devhub/pages/auth/register.php" class="btn btn-secondary btn-large">I want to List My Project</a>
                </div>
            </div>
        </div>
    </section>

    <section class="home-test-explainer home-test-explainer-compact">
        <div class="container">
            <div class="home-test-section-head">
                <span class="home-test-kicker">Why CoinRex Exists</span>
                <h2>Because crypto users need something better than hype</h2>
                <p>CoinRex helps visitors quickly understand which projects are getting real feedback, and helps teams earn trust more publicly.</p>
            </div>
            <div class="home-test-explainer-grid">
                <article class="home-test-info-card">
                    <i class="fas fa-users"></i>
                    <h3>Users share real experience</h3>
                    <p>People can review projects they have actually used instead of depending only on influencers or ads.</p>
                </article>
                <article class="home-test-info-card">
                    <i class="fas fa-building"></i>
                    <h3>Projects build visible trust</h3>
                    <p>Teams can collect public reviews and show visitors they are serious, active, and transparent.</p>
                </article>
                <article class="home-test-info-card">
                    <i class="fas fa-shield-check"></i>
                    <h3>Proof matters</h3>
                    <p>The platform is designed to make reviews more useful by encouraging proof-backed public activity.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="home-test-paths">
        <div class="container">
            <div class="home-test-section-head">
                <span class="home-test-kicker">Choose Your Path</span>
                <h2>What do you want to do on CoinRex?</h2>
                <p>Pick the path that matches you best.</p>
            </div>
            <div class="home-test-path-grid">
                <article class="home-test-path-card">
                    <span class="home-test-path-icon"><i class="fas fa-pen-nib"></i></span>
                    <h3>I want to review projects</h3>
                    <ul>
                        <li>Create an account</li>
                        <li>Choose a project you’ve used</li>
                        <li>Submit your review with proof</li>
                        <li>Earn $REX when approved</li>
                    </ul>
                    <a href="<?php echo AUTH_URL; ?>/auth.php?tab=register" class="btn btn-primary">Start as Reviewer</a>
                </article>
                <article class="home-test-path-card home-test-path-card-alt">
                    <span class="home-test-path-icon"><i class="fas fa-bullhorn"></i></span>
                    <h3>I want to list my project</h3>
                    <ul>
                        <li>Register as a project owner</li>
                        <li>Submit your project for approval</li>
                        <li>Get public reviews from users</li>
                        <li>Build reputation and visibility</li>
                    </ul>
                    <a href="<?php echo BASE_URL; ?>/devhub/pages/auth/register.php" class="btn btn-secondary">List My Project</a>
                </article>
            </div>
        </div>
    </section>

    <section class="home-test-steps">
        <div class="container">
            <div class="home-test-section-head">
                <span class="home-test-kicker">How It Works</span>
                <h2>Simple 4-step flow</h2>
            </div>
            <div class="home-test-step-grid">
                <article class="home-test-step-card"><span>1</span><h3>Join CoinRex</h3><p>Create your account as a user or project owner.</p></article>
                <article class="home-test-step-card"><span>2</span><h3>Pick your role</h3><p>Review projects for rewards, or list your project for visibility and trust.</p></article>
                <article class="home-test-step-card"><span>3</span><h3>Submit real activity</h3><p>Reviews and listings go through moderation so the platform stays useful.</p></article>
                <article class="home-test-step-card"><span>4</span><h3>Build value publicly</h3><p>Users earn, projects gain trust, and visitors see a clearer picture of what’s real.</p></article>
            </div>
        </div>
    </section>

    <section class="home-test-proof-strip">
        <div class="container">
            <div class="home-test-proof-grid">
                <div class="home-test-metric-card">
                    <strong><?php echo number_format($home_stats['approved_reviews']); ?></strong>
                    <span>Approved Reviews</span>
                </div>
                <div class="home-test-metric-card">
                    <strong><?php echo number_format($verified_or_approved); ?></strong>
                    <span><?php echo homeTestEsc($verified_or_approved_label); ?></span>
                </div>
                <div class="home-test-metric-card">
                    <strong><?php echo number_format($home_stats['active_reviewers']); ?></strong>
                    <span>Active Reviewers</span>
                </div>
                <div class="home-test-metric-card">
                    <strong><?php echo number_format((float) $home_stats['total_rex_paid'], 0); ?></strong>
                    <span>$REX Paid</span>
                </div>
            </div>
        </div>
    </section>

    <section class="home-test-reviews">
        <div class="container">
            <div class="home-test-section-head">
                <span class="home-test-kicker">Live Activity</span>
                <h2>What approved reviews look like</h2>
                <p>These cards help a new visitor understand what kind of activity exists on CoinRex.</p>
            </div>
            <div class="home-test-review-grid">
                <?php foreach ($homepage_reviews as $review): ?>
                    <?php
                    $reviewer_name = homeTestDisplayName($review);
                    $reviewer_avatar = coinrexNormalizeMediaUrl((string) ($review['reviewer_avatar'] ?? ''));
                    $review_level = trim((string) ($review['reviewer_level'] ?? 'beginner'));
                    $review_level_key = strtolower($review_level);
                    $review_excerpt = homeTestExcerpt((string) (($review['review_content'] ?? '') !== '' ? $review['review_content'] : ($review['review_title'] ?? '')), 110);
                    ?>
                    <article class="home-test-review-card">
                        <div class="home-test-review-head">
                            <div class="home-test-review-user">
                                <div class="home-test-avatar">
                                    <?php if ($reviewer_avatar !== ''): ?>
                                        <img src="<?php echo homeTestEsc($reviewer_avatar); ?>" alt="<?php echo homeTestEsc($reviewer_name); ?> avatar">
                                    <?php else: ?>
                                        <?php echo homeTestEsc(homeTestInitial($reviewer_name)); ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?php echo homeTestEsc($reviewer_name); ?></strong>
                                    <span class="simple-badge <?php echo $review_level_key === 'beginner' ? 'level-badge-beginner' : ($review_level_key === 'expert' ? 'level-badge-expert' : 'level-badge-pro'); ?>">
                                        <i class="fas fa-gem"></i>
                                        <?php echo homeTestEsc(ucwords(str_replace(['_', '-'], ' ', $review_level))); ?>
                                    </span>
                                </div>
                            </div>
                            <?php echo renderUniversalRating([
                                'provider' => 'coinrex',
                                'value' => (float) ($review['rating'] ?? 0),
                                'scale' => 5,
                                'size' => 'sm',
                                'variant' => 'cr-row-small',
                                'show_count' => false,
                                'class' => 'home-test-rating',
                            ]); ?>
                        </div>
                        <p><?php echo homeTestEsc($review_excerpt); ?></p>
                        <div class="home-test-review-meta">
                            <span><i class="fas fa-layer-group"></i> <?php echo homeTestEsc((string) ($review['project_name'] ?? 'Marketplace listing')); ?></span>
                            <span><i class="fas fa-coins"></i> <?php echo (float) ($review['reward_rex'] ?? 0) > 0 ? number_format((float) $review['reward_rex'], 0) . ' $REX' : 'Reward pending'; ?></span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if (!empty($latest_blog_posts)): ?>
    <section class="home-test-blog">
        <div class="container">
            <div class="home-test-section-head">
                <span class="home-test-kicker">Learn Before You Start</span>
                <h2>Latest from CoinRex Blog</h2>
                <p>Helpful guides for both reviewers and project owners.</p>
            </div>
            <div class="home-test-blog-grid home-test-blog-grid-count-<?php echo (int) count($latest_blog_posts); ?>">
                <?php foreach ($latest_blog_posts as $blog_post): ?>
                    <article class="home-test-blog-card">
                        <span class="home-test-blog-date"><?php echo date('M d, Y', strtotime((string) ($blog_post['published_at'] ?: $blog_post['created_at']))); ?></span>
                        <h3><a href="<?php echo BASE_URL; ?>/blog-post.php/<?php echo urlencode((string) ($blog_post['slug'] ?? '')); ?>"><?php echo homeTestEsc((string) ($blog_post['title'] ?? 'Blog Post')); ?></a></h3>
                        <p><?php echo homeTestEsc(homeTestExcerpt((string) ($blog_post['excerpt'] ?? ''), 120)); ?></p>
                        <div class="home-test-blog-meta">
                            <span><i class="fas fa-book-open"></i> <?php echo (int) blogReadTime((string) ($blog_post['excerpt'] ?? '')) + 1; ?> min read</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="home-test-final-cta">
        <div class="container">
            <div class="home-test-final-panel">
                <h2>Still confused? Start with the path that matches you.</h2>
                <p>If you want to earn and share experience, join as a reviewer. If you want public trust and visibility for your project, join as a builder.</p>
                <div class="home-test-hero-actions">
                    <a href="<?php echo AUTH_URL; ?>/auth.php?tab=register" class="btn btn-primary btn-large">Join as Reviewer</a>
                    <a href="<?php echo BASE_URL; ?>/devhub/pages/auth/register.php" class="btn btn-secondary btn-large">Join as Project Owner</a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ob_end_flush(); ?>
