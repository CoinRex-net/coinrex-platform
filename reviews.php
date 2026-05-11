<?php
/**
 * CoinRex Reviews Listing Page
 * Location: /coinrex/reviews.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDBConnection();
ensureLevelEngineSchema($db);

$current_user = getCurrentUser();
$is_public_view = !$current_user;
$current_user_level_state = $current_user ? getUserLevelState($current_user, $db) : null;
$review_action_message = consumeFlashMessage('reviews_action_message');
$review_action_type = consumeFlashMessage('reviews_action_type');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user) {
    requireAppCsrf((string) ($_POST['csrf_token'] ?? ''));

    $action = trim((string) ($_POST['action'] ?? ''));
    $review_id = (int) ($_POST['review_id'] ?? 0);

    if ($action === 'toggle_like') {
        $result = toggleReviewLike($review_id, (int) $current_user['id'], $db);
        setFlashMessage('reviews_action_message', (string) ($result['message'] ?? 'Unable to update review reaction.'));
        setFlashMessage('reviews_action_type', !empty($result['success']) ? 'success' : 'error');
        redirect(BASE_URL . '/reviews.php?page=' . max(1, (int) ($_GET['page'] ?? 1)));
    }

    if ($action === 'flag_review') {
        $result = submitContentFlag((int) $current_user['id'], 'review', $review_id, 'Flagged by expert reviewer from community feed.', $db);
        setFlashMessage('reviews_action_message', (string) ($result['message'] ?? 'Unable to flag review.'));
        setFlashMessage('reviews_action_type', !empty($result['success']) ? 'success' : 'error');
        redirect(BASE_URL . '/reviews.php?page=' . max(1, (int) ($_GET['page'] ?? 1)));
    }
}

$has_featured_column = tableHasColumn('projects', 'is_featured');
$has_sponsored_column = tableHasColumn('projects', 'is_sponsored');
$featured_select = $has_featured_column ? 'COALESCE(p.is_featured, 0)' : '0';
$sponsored_select = $has_sponsored_column ? 'COALESCE(p.is_sponsored, 0)' : '0';

$search = trim((string) ($_GET['search'] ?? ''));
$category_filter = trim((string) ($_GET['category'] ?? 'all'));
$rating_filter = trim((string) ($_GET['rating'] ?? 'all'));
$featured_filter = trim((string) ($_GET['featured'] ?? 'all'));

$allowed_rating = ['all', '4_plus', '3_plus', 'below_2'];
if (!in_array($rating_filter, $allowed_rating, true)) {
    $rating_filter = 'all';
}

$allowed_featured = ['all', 'featured', 'regular'];
if (!in_array($featured_filter, $allowed_featured, true)) {
    $featured_filter = 'all';
}

$category_stmt = $db->prepare("SELECT LOWER(TRIM(category)) AS category_key, TRIM(category) AS category_label, COUNT(*) AS total FROM projects WHERE approval_status = 'approved' AND TRIM(COALESCE(category, '')) <> '' GROUP BY LOWER(TRIM(category)), TRIM(category) ORDER BY total DESC, category_label ASC");
$category_stmt->execute();
$category_rows = $category_stmt->fetchAll() ?: [];
$allowed_categories = ['all'];
foreach ($category_rows as $crow) {
    $allowed_categories[] = (string) ($crow['category_key'] ?? '');
}
if (!in_array(strtolower($category_filter), $allowed_categories, true)) {
    $category_filter = 'all';
}

$where = ["r.status = 'approved'", "p.approval_status = 'approved'"];
$params = [];

if ($search !== '') {
    $where[] = "(
        COALESCE(NULLIF(TRIM(u.full_name), ''), u.username, 'Reviewer') LIKE :search
        OR u.username LIKE :search
        OR p.name LIKE :search
        OR r.review_title LIKE :search
        OR r.review_content LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

if ($category_filter !== 'all') {
    $where[] = "LOWER(TRIM(COALESCE(p.category, ''))) = :category_filter";
    $params[':category_filter'] = strtolower($category_filter);
}

if ($rating_filter === '4_plus') {
    $where[] = 'r.rating >= 4';
} elseif ($rating_filter === '3_plus') {
    $where[] = 'r.rating >= 3';
} elseif ($rating_filter === 'below_2') {
    $where[] = 'r.rating < 2';
}

if ($featured_filter === 'featured') {
    $where[] = "{$featured_select} = 1";
} elseif ($featured_filter === 'regular') {
    $where[] = "{$featured_select} = 0";
}

$where_sql = implode(' AND ', $where);

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = $page > 0 ? $page : 1;
$per_page = $is_public_view ? 6 : 20;
$offset = ($page - 1) * $per_page;

$count_stmt = $db->prepare("SELECT COUNT(*) AS total FROM reviews r INNER JOIN projects p ON p.id = r.project_id INNER JOIN users u ON u.id = r.user_id WHERE {$where_sql}");
foreach ($params as $pk => $pv) {
    $count_stmt->bindValue($pk, $pv);
}
$count_stmt->execute();
$total_reviews = (int)($count_stmt->fetch()['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_reviews / $per_page));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

$reviews_stmt = $db->prepare("SELECT
    r.id,
    r.user_id,
    r.project_id,
    r.rating,
    r.review_title,
    r.review_content,
    r.holding_amount,
    r.holding_days,
    r.wallet_address,
    r.review_score,
    r.created_at,
    u.username,
    u.full_name,
    u.avatar,
    u.level,
    r.helpful_count,
    p.name AS project_name,
    p.category AS project_category,
    {$featured_select} AS is_featured,
    {$sponsored_select} AS is_sponsored,
    COALESCE(ps.total_reviews, 0) AS project_total_reviews,
    COALESCE(ps.avg_rating, 0) AS project_avg_rating
FROM reviews r
INNER JOIN users u ON u.id = r.user_id
INNER JOIN projects p ON p.id = r.project_id
LEFT JOIN (
    SELECT project_id, COUNT(*) AS total_reviews, AVG(rating) AS avg_rating
    FROM reviews
    WHERE status = 'approved'
    GROUP BY project_id
) ps ON ps.project_id = p.id
WHERE {$where_sql}
ORDER BY r.review_score DESC, r.created_at DESC
LIMIT :limit OFFSET :offset");

foreach ($params as $pk => $pv) {
    $reviews_stmt->bindValue($pk, $pv);
}
$reviews_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$reviews_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$reviews_stmt->execute();
$reviews = $reviews_stmt->fetchAll();

foreach ($reviews as &$review) {
    $review['avatar'] = coinrexNormalizeMediaUrl((string) ($review['avatar'] ?? ''));
}
unset($review);

$top_projects_stmt = $db->prepare("SELECT p.id, p.name, {$featured_select} AS is_featured, {$sponsored_select} AS is_sponsored, COALESCE(stats.total_reviews, 0) AS total_reviews, COALESCE(stats.avg_rating, 0) AS avg_rating FROM projects p INNER JOIN (SELECT project_id, COUNT(*) AS total_reviews, AVG(rating) AS avg_rating FROM reviews WHERE status = 'approved' GROUP BY project_id HAVING COUNT(*) > 0) stats ON stats.project_id = p.id WHERE p.approval_status = 'approved' ORDER BY stats.avg_rating DESC, stats.total_reviews DESC LIMIT 6");
$top_projects_stmt->execute();
$top_projects = $top_projects_stmt->fetchAll() ?: [];

function esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function walletTypeLabel($wallet_address)
{
    $wallet_address = trim((string)$wallet_address);
    if ($wallet_address === '') return ['label' => 'Unknown', 'class' => 'unknown'];
    if (preg_match('/^(0x[a-fA-F0-9]{8,}|T[a-zA-Z0-9]{20,}|bc1[a-zA-Z0-9]{10,})$/', $wallet_address)) {
        return ['label' => 'Non-custodial', 'class' => 'non-custodial'];
    }
    return ['label' => 'Custodial', 'class' => 'custodial'];
}

$liked_review_ids = [];
if ($current_user) {
    $liked_stmt = $db->prepare("SELECT review_id FROM review_reactions WHERE user_id = ? AND reaction_type = 'like'");
    $liked_stmt->execute([(int) $current_user['id']]);
    $liked_review_ids = array_map('intval', $liked_stmt->fetchAll(PDO::FETCH_COLUMN));
}

require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/reviews.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/rating-badge.css">

<main class="reviews-main">
    <div class="reviews-container wide">
        <?php if ($review_action_message !== ''): ?>
            <div id="reviewsToast" class="reviews-toast <?php echo $review_action_type === 'error' ? 'toast-error' : 'toast-success'; ?>" role="status" aria-live="polite">
                <?php echo esc($review_action_message); ?>
            </div>
        <?php endif; ?>

        <section class="reviews-hero">
            <div class="reviews-badge"><i class="fas fa-shield-alt"></i><span>Trust-Driven Feed</span></div>
            <h1>Community <span class="gradient-text">Reviews</span></h1>
            <p><?php echo $is_public_view ? 'Public preview of community reviews. Sign in for full insights, engagement actions, and complete review context.' : 'Ranked by credibility score so high-quality, trustworthy reviews appear first.'; ?></p>
        </section>

        <section class="reviews-caution">
            <strong><i class="fas fa-triangle-exclamation"></i> Verification Responsibility Notice</strong>
            <p>All reviews are manually verified by the CoinRex team. Human review may involve occasional mistakes. We validate proof submissions, but we do not guarantee the factual accuracy of each user’s opinion, suggestion, or descriptive claim.</p>
        </section>

        <section class="reviews-toolbar">
            <form method="GET" class="reviews-filter-form">
                <input type="text" name="search" value="<?php echo esc($search); ?>" placeholder="Search project, reviewer, title...">
                <select name="category">
                    <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <?php foreach ($category_rows as $cat): ?>
                        <?php $ck = strtolower((string) ($cat['category_key'] ?? '')); ?>
                        <option value="<?php echo esc($ck); ?>" <?php echo strtolower($category_filter) === $ck ? 'selected' : ''; ?>><?php echo esc((string) ($cat['category_label'] ?? 'Category')); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="rating">
                    <option value="all" <?php echo $rating_filter === 'all' ? 'selected' : ''; ?>>All Ratings</option>
                    <option value="4_plus" <?php echo $rating_filter === '4_plus' ? 'selected' : ''; ?>>4★ and above</option>
                    <option value="3_plus" <?php echo $rating_filter === '3_plus' ? 'selected' : ''; ?>>3★ and above</option>
                    <option value="below_2" <?php echo $rating_filter === 'below_2' ? 'selected' : ''; ?>>Below 2★</option>
                </select>
                <select name="featured">
                    <option value="all" <?php echo $featured_filter === 'all' ? 'selected' : ''; ?>>All Projects</option>
                    <option value="featured" <?php echo $featured_filter === 'featured' ? 'selected' : ''; ?>>Featured Only</option>
                    <option value="regular" <?php echo $featured_filter === 'regular' ? 'selected' : ''; ?>>Regular Only</option>
                </select>
                <button type="submit">Apply</button>
            </form>
        </section>

        <div class="reviews-layout">
            <section class="reviews-feed-wrap">
                <?php if (empty($reviews)): ?>
                    <section class="reviews-empty">
                        <i class="fas fa-inbox"></i>
                        <h3>No Approved Reviews Yet</h3>
                        <p>Once quality reviews are approved, they will appear here.</p>
                    </section>
                <?php else: ?>
                    <section class="reviews-feed two-col">
                        <?php foreach ($reviews as $index => $review): ?>
                            <?php
                            $wallet = walletTypeLabel($review['wallet_address'] ?? '');
                            $rating = (float)($review['rating'] ?? 0);
                            $full_name = trim((string) ($review['full_name'] ?? ''));
                            $display_name = $full_name !== '' ? $full_name : (trim((string) ($review['username'] ?? '')) !== '' ? (string) $review['username'] : 'Reviewer');
                            $user_level = ucfirst(strtolower((string)($review['level'] ?? 'Beginner')));
                            $avatar_letter = strtoupper(substr((string)$display_name, 0, 1));
                            $is_top = ($offset + $index) < 10;
                            $content = trim((string)($review['review_content'] ?? ''));
                            if ($is_public_view && function_exists('mb_substr') && mb_strlen($content, 'UTF-8') > 180) {
                                $content = rtrim(mb_substr($content, 0, 179, 'UTF-8')) . '...';
                            } elseif ($is_public_view && strlen($content) > 180) {
                                $content = rtrim(substr($content, 0, 179)) . '...';
                            }
                            $helpful_count = (int)($review['helpful_count'] ?? 0);
                            $can_mark_helpful = (bool) $current_user;
                            $expert_can_act = $current_user && userCanAccessExpertTools($current_user_level_state);
                            $liked_by_current_user = in_array((int) $review['id'], $liked_review_ids, true);
                            $level_class = 'beginner';
                            $level_icon = 'fas fa-seedling';
                            if (strtolower($user_level) === 'expert') {
                                $level_class = 'expert';
                                $level_icon = 'fas fa-crown';
                            } elseif (in_array(strtolower($user_level), ['pro', 'premium'], true)) {
                                $level_class = 'pro';
                                $level_icon = 'fas fa-gem';
                                $user_level = 'Pro';
                            }
                            $is_featured_project = (int) ($review['is_featured'] ?? 0) === 1;
                            $project_total_reviews = (int) ($review['project_total_reviews'] ?? 0);
                            $project_avg_rating = (float) ($review['project_avg_rating'] ?? 0);
                            $is_warning_project = $project_total_reviews >= 100 && $project_avg_rating < 2;
                            ?>
                            <article class="review-card <?php echo $is_top ? 'top-review' : ''; ?>">
                                <?php if ($is_top): ?><div class="top-tag"><i class="fas fa-crown"></i> Top 10</div><?php endif; ?>

                                <header class="review-head">
                                    <div class="user-block">
                                        <?php if (!empty($review['avatar'])): ?>
                                            <img src="<?php echo esc($review['avatar']); ?>" alt="<?php echo esc($display_name); ?>" class="user-avatar">
                                        <?php else: ?>
                                            <div class="user-avatar placeholder"><?php echo esc($avatar_letter); ?></div>
                                        <?php endif; ?>
                                        <div class="user-meta">
                                            <strong>
                                                <?php echo esc($display_name); ?>
                                                <span class="user-level-badge <?php echo esc($level_class); ?>"><i class="<?php echo esc($level_icon); ?>"></i><span><?php echo esc($user_level); ?></span></span>
                                            </strong>
                                            <span><?php echo date('M d, Y', strtotime((string)$review['created_at'])); ?></span>
                                        </div>
                                    </div>
                                </header>

                                <?php echo renderUniversalRating([
                                    'provider' => 'coinrex',
                                    'value' => $rating,
                                    'scale' => 5,
                                    'size' => 'sm',
                                    'variant' => 'cr-row-small',
                                    'class' => 'review-rating-badge',
                                ]); ?>

                                <div class="project-row">
                                    <a href="<?php echo BASE_URL; ?>/project-detail.php?id=<?php echo (int)$review['project_id']; ?>" class="project-info no-logo">
                                        <span class="project-name"><?php echo esc($review['project_name']); ?></span>
                                    </a>
                                    <div class="project-statuses">
                                        <span class="project-badge <?php echo $is_featured_project ? 'featured' : 'regular'; ?>">
                                            <i class="fas <?php echo $is_featured_project ? 'fa-gem' : 'fa-circle-check'; ?>"></i>
                                            <?php echo $is_featured_project ? 'Featured' : 'Regular'; ?>
                                        </span>
                                        <?php if ((int) ($review['is_sponsored'] ?? 0) === 1): ?>
                                            <span class="project-badge sponsored"><i class="fas fa-bullhorn"></i> Sponsored</span>
                                        <?php endif; ?>
                                        <?php if ($is_warning_project): ?>
                                            <span class="project-badge warning"><i class="fas fa-triangle-exclamation"></i> Low Rated (100+)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($review['review_title'])): ?>
                                    <h3 class="review-title"><?php echo esc($review['review_title']); ?></h3>
                                <?php endif; ?>

                                <div class="review-content-wrap">
                                    <p class="review-content collapsed"><?php echo nl2br(esc($content)); ?></p>
                                    <button type="button" class="read-more-btn" hidden>Read More</button>
                                </div>

                                <footer class="review-meta <?php echo $is_public_view ? 'review-meta-public' : ''; ?>">
                                    <div class="meta-item"><i class="fas fa-wallet"></i><span>$<?php echo number_format((float)$review['holding_amount'], 2); ?></span></div>
                                    <div class="meta-item"><i class="fas fa-clock"></i><span><?php echo (int)$review['holding_days']; ?> days</span></div>
                                    <div class="meta-item"><span class="wallet-badge <?php echo esc($wallet['class']); ?>"><?php echo esc($wallet['label']); ?></span></div>
                                    <?php if (!$is_public_view): ?><div class="meta-item"><i class="fas fa-thumbs-up"></i><span><?php echo number_format($helpful_count); ?> helpful</span></div><?php endif; ?>
                                </footer>

                                <div class="helpful-section">
                                    <span class="helpful-count"><i class="fas fa-thumbs-up"></i> <?php echo number_format($helpful_count); ?> found this helpful</span>
                                    <?php if ($can_mark_helpful): ?>
                                        <form method="POST" action="" class="helpful-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
                                            <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                            <button type="submit" name="action" value="toggle_like" class="helpful-btn" <?php echo $liked_by_current_user ? 'aria-pressed="true"' : ''; ?>>
                                                <i class="fas fa-thumbs-up" aria-hidden="true"></i>
                                                <?php echo $liked_by_current_user ? 'Marked Helpful' : 'Mark Helpful'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="<?php echo AUTH_URL; ?>/auth.php" class="helpful-btn helpful-btn-link">Sign in to mark helpful</a>
                                    <?php endif; ?>
                                </div>

                                <?php if ($expert_can_act): ?>
                                    <div class="review-meta" style="padding-top: 10px;">
                                        <form method="POST" action="" style="display:inline-flex; gap:12px; flex-wrap:wrap;">
                                            <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
                                            <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                            <button type="submit" name="action" value="flag_review" class="read-more-btn" style="display:inline-flex;">Flag Review</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </section>

                    <?php if ($total_pages > 1): ?>
                        <nav class="reviews-pagination" aria-label="Reviews pagination">
                            <?php $prev = $page - 1; $next = $page + 1; $qs = $_GET; ?>
                            <?php $qs['page'] = $prev; ?>
                            <a class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : (BASE_URL . '/reviews.php?' . http_build_query($qs)); ?>"><i class="fas fa-chevron-left"></i> Prev</a>
                            <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                            <?php $qs['page'] = $next; ?>
                            <a class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo $page >= $total_pages ? '#' : (BASE_URL . '/reviews.php?' . http_build_query($qs)); ?>">Next <i class="fas fa-chevron-right"></i></a>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <aside class="reviews-sidebar">
                <section class="sidebar-card">
                    <h3>Top Rated Projects</h3>
                    <?php if (empty($top_projects)): ?>
                        <p class="muted">Top-rated projects will appear here soon.</p>
                    <?php else: ?>
                        <ul class="top-rated-list">
                            <?php foreach ($top_projects as $tp): ?>
                                <?php $tp_rating = (float) ($tp['avg_rating'] ?? 0); ?>
                                <li>
                                    <a href="<?php echo BASE_URL; ?>/project-detail.php?id=<?php echo (int) $tp['id']; ?>">
                                        <strong><?php echo esc((string) ($tp['name'] ?? 'Project')); ?></strong>
                                        <?php echo renderUniversalRating([
                                            'provider' => 'coinrex',
                                            'value' => $tp_rating,
                                            'scale' => 5,
                                            'size' => 'sm',
                                            'variant' => 'cr-row-small',
                                            'show_count' => false,
                                            'class' => 'sidebar-rating-badge',
                                        ]); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="sidebar-card">
                    <h3>Categories</h3>
                    <?php if (empty($category_rows)): ?>
                        <p class="muted">No categories available yet.</p>
                    <?php else: ?>
                        <ul class="category-list">
                            <?php foreach ($category_rows as $cat): ?>
                                <li>
                                    <a href="<?php echo BASE_URL; ?>/reviews.php?<?php echo http_build_query(array_merge($_GET, ['category' => strtolower((string) ($cat['category_key'] ?? ''))])); ?>">
                                        <span><?php echo esc((string) ($cat['category_label'] ?? 'Category')); ?></span>
                                        <strong><?php echo (int) ($cat['total'] ?? 0); ?></strong>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
</main>

<script>
(function() {
    'use strict';
    const contentBlocks = document.querySelectorAll('.review-content-wrap');
    contentBlocks.forEach(function(block) {
        const textEl = block.querySelector('.review-content');
        const btn = block.querySelector('.read-more-btn');
        if (!textEl || !btn) return;

        const lineHeight = parseFloat(getComputedStyle(textEl).lineHeight || '24');
        const maxLines = 4;
        const maxHeight = lineHeight * maxLines + 2;
        if (textEl.scrollHeight <= maxHeight) return;

        btn.hidden = false;
        btn.addEventListener('click', function() {
            const expanded = textEl.classList.toggle('expanded');
            textEl.classList.toggle('collapsed', !expanded);
            btn.textContent = expanded ? 'Read Less' : 'Read More';
        });
    });
})();

(function() {
    const toast = document.getElementById('reviewsToast');
    if (!toast) return;

    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    window.setTimeout(() => {
        toast.classList.remove('show');
        window.setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 260);
    }, 3000);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
