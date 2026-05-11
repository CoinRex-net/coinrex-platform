<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDBConnection();
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$where = " WHERE p.status='published' ";
$params = [];
if ($q !== '') {
    $where .= " AND (p.title LIKE ? OR p.excerpt LIKE ? OR p.content LIKE ?) ";
    $needle = '%' . $q . '%';
    $params = [$needle, $needle, $needle];
}

$count = $db->prepare("SELECT COUNT(*) FROM blog_posts p {$where}");
$count->execute($params);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / $limit));

$sql = "SELECT p.*, a.name AS author_name,
        (SELECT GROUP_CONCAT(c.name SEPARATOR ', ') FROM blog_post_categories pc JOIN blog_categories c ON c.id=pc.category_id WHERE pc.post_id=p.id) AS categories
        FROM blog_posts p
        LEFT JOIN admins a ON a.id = p.author_admin_id
        {$where}
        ORDER BY COALESCE(p.published_at,p.created_at) DESC LIMIT {$limit} OFFSET {$offset}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll() ?: [];

// Blog ad slots from Ads Manager (safe display only)
$leaderboardAd = function_exists('blogGetRandomAdByPlacement') ? blogGetRandomAdByPlacement($db, 'blog_leaderboard') : null;
$infeedAd = function_exists('blogGetRandomAdByPlacement') ? blogGetRandomAdByPlacement($db, 'blog_infeed') : null;
$sidebarAd = function_exists('blogGetRandomAdByPlacement') ? blogGetRandomAdByPlacement($db, 'blog_sidebar') : null;
$infeedAfterPost = (int) ($infeedAd['after_post'] ?? 3);
if ($infeedAfterPost < 1) $infeedAfterPost = 1;
if ($infeedAfterPost > 20) $infeedAfterPost = 20;

require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/blog.css">
<main class="blog-shell">
    <section class="blog-hero">
        <h1>CoinRex Blog</h1>
        <p>Premium guides, product updates, and strategic insights for TaskHub, BoostHub, DevHub, and rewards.</p>
    </section>

    <?php if ($leaderboardAd): ?>
        <section class="blog-ad blog-ad-leaderboard">
            <span class="ad-badge">Sponsored</span>
            <?php if (!empty($leaderboardAd['target_url'])): ?><a href="<?php echo htmlspecialchars((string) $leaderboardAd['target_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="nofollow sponsored noopener"><?php endif; ?>
                <?php if ((($leaderboardAd['ad_type'] ?? '') === 'image' || ($leaderboardAd['ad_type'] ?? '') === 'gif') && !empty($leaderboardAd['media_url'])): ?>
                    <img src="<?php echo htmlspecialchars((string) $leaderboardAd['media_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Sponsored Ad" class="ad-media">
                <?php else: ?>
                    <h4><?php echo htmlspecialchars((string) ($leaderboardAd['title'] ?? 'Sponsored'), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p><?php echo htmlspecialchars((string) ($leaderboardAd['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if (!empty($leaderboardAd['cta_text']) && !empty($leaderboardAd['target_url'])): ?>
                        <div style="margin-top:8px;"><span class="blog-cta"><?php echo htmlspecialchars((string) $leaderboardAd['cta_text'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php if (!empty($leaderboardAd['target_url'])): ?></a><?php endif; ?>
        </section>
    <?php endif; ?>

    <form method="get" class="blog-search">
        <input type="text" name="q" placeholder="Search articles" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" class="blog-search-input">
        <button type="submit" class="nex-btn nex-btn-primary">Search</button>
    </form>
    <div class="blog-grid">
    <div class="blog-cards">
        <?php foreach ($posts as $idx => $post): ?>
            <article class="blog-card">
                <h3 style="margin-top:0;"><a href="<?php echo BASE_URL; ?>/blog-post.php?slug=<?php echo urlencode((string) $post['slug']); ?>"><?php echo htmlspecialchars((string) $post['title'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                <p style="opacity:.8;"><?php echo htmlspecialchars((string) ($post['excerpt'] ?: mb_substr(strip_tags((string) $post['content']), 0, 130) . '...'), ENT_QUOTES, 'UTF-8'); ?></p>
                <div class="blog-meta">
                    <span>By <?php echo htmlspecialchars((string) ($post['author_name'] ?: 'CoinRex Team'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><?php echo date('M d, Y', strtotime((string) ($post['published_at'] ?: $post['created_at']))); ?></span>
                    <?php if (!empty($post['categories'])): ?>
                        <span class="chip"><?php echo htmlspecialchars(explode(',', (string) $post['categories'])[0], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
                <div style="margin-top:12px;">
                    <a href="<?php echo BASE_URL; ?>/blog-post.php?slug=<?php echo urlencode((string) $post['slug']); ?>"><span class="blog-cta">Read Full Article</span></a>
                </div>
            </article>

            <?php if ($infeedAd && $infeedAfterPost === ((int) $idx + 1) && (($infeedAd['ad_type'] ?? 'text') === 'text')): ?>
                <article class="blog-card blog-card-sponsored">
                    <span class="ad-badge">Sponsored</span>
                    <?php if (!empty($infeedAd['target_url'])): ?><a href="<?php echo htmlspecialchars((string) $infeedAd['target_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="nofollow sponsored noopener"><?php endif; ?>
                        <h3 style="margin-top:0;"><?php echo htmlspecialchars((string) ($infeedAd['title'] ?? 'Sponsored'), ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars((string) ($infeedAd['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (!empty($infeedAd['cta_text']) && !empty($infeedAd['target_url'])): ?>
                            <div style="margin-top:12px;"><span class="blog-cta"><?php echo htmlspecialchars((string) $infeedAd['cta_text'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                        <?php endif; ?>
                    <?php if (!empty($infeedAd['target_url'])): ?></a><?php endif; ?>
                </article>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <aside class="blog-sidebar">
        <div class="widget"><h4>Explore</h4><a href="<?php echo BASE_URL; ?>/blog-category.php?slug=platform-guides">Platform Guides</a><br><a href="<?php echo BASE_URL; ?>/blog-category.php?slug=product-updates">Product Updates</a></div>
        <?php if ($sidebarAd): ?>
            <div class="widget blog-ad blog-ad-sidebar">
                <span class="ad-badge">Sponsored</span>
                <?php if (!empty($sidebarAd['target_url'])): ?><a href="<?php echo htmlspecialchars((string) $sidebarAd['target_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="nofollow sponsored noopener"><?php endif; ?>
                    <?php if ((($sidebarAd['ad_type'] ?? '') === 'image' || ($sidebarAd['ad_type'] ?? '') === 'gif') && !empty($sidebarAd['media_url'])): ?>
                        <img src="<?php echo htmlspecialchars((string) $sidebarAd['media_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Sponsored Ad" class="ad-media">
                    <?php else: ?>
                        <h4><?php echo htmlspecialchars((string) ($sidebarAd['title'] ?? 'Sponsored'), ENT_QUOTES, 'UTF-8'); ?></h4>
                        <p><?php echo htmlspecialchars((string) ($sidebarAd['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (!empty($sidebarAd['cta_text']) && !empty($sidebarAd['target_url'])): ?>
                            <div style="margin-top:8px;"><span class="blog-cta"><?php echo htmlspecialchars((string) $sidebarAd['cta_text'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php if (!empty($sidebarAd['target_url'])): ?></a><?php endif; ?>
            </div>
        <?php endif; ?>
    </aside>
    </div>
    <?php if ($pages > 1): ?>
    <div class="blog-pagination">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?page=<?php echo $i; ?><?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>" style="padding:8px 12px;border-radius:8px;background:<?php echo $i === $page ? '#22c55e' : '#1e293b'; ?>;color:#fff;"><?php echo $i; ?></a>
        <?php endfor; ?>

        <?php if ($page < $pages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $q !== '' ? '&q=' . urlencode($q) : ''; ?>" style="padding:8px 12px;border-radius:8px;background:#0ea5e9;color:#fff;">Next</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
