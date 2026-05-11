<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    die('Post not found');
}

$db = getDBConnection();
$stmt = $db->prepare("SELECT p.*, a.name AS author_name FROM blog_posts p LEFT JOIN admins a ON a.id=p.author_admin_id WHERE p.slug=? AND p.status='published' LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();
if (!$post) {
    http_response_code(404);
    die('Post not found');
}

$categoryStmt = $db->prepare("SELECT c.id,c.name,c.slug FROM blog_categories c JOIN blog_post_categories pc ON pc.category_id=c.id WHERE pc.post_id=? LIMIT 1");
$categoryStmt->execute([(int) $post['id']]);
$primaryCategory = $categoryStmt->fetch();

$relatedSql = "SELECT p.id,p.title,p.slug,p.published_at,p.created_at
               FROM blog_posts p
               WHERE p.status='published' AND p.id<>?";
$params = [(int) $post['id']];
if ($primaryCategory) {
    $relatedSql = "SELECT p.id,p.title,p.slug,p.published_at,p.created_at
                   FROM blog_posts p
                   JOIN blog_post_categories pc ON pc.post_id=p.id
                   WHERE p.status='published' AND p.id<>? AND pc.category_id=?";
    $params[] = (int) $primaryCategory['id'];
}
$relatedSql .= " ORDER BY COALESCE(p.published_at,p.created_at) DESC LIMIT 5";
$relatedStmt = $db->prepare($relatedSql);
$relatedStmt->execute($params);
$relatedPosts = $relatedStmt->fetchAll() ?: [];

$latestStmt = $db->query("SELECT title,slug FROM blog_posts WHERE status='published' ORDER BY COALESCE(published_at,created_at) DESC LIMIT 6");
$latestPosts = $latestStmt ? ($latestStmt->fetchAll() ?: []) : [];

$postLeaderboardAd = function_exists('blogGetRandomAdByPlacement') ? blogGetRandomAdByPlacement($db, 'blog_leaderboard') : null;
$postInlineAd = function_exists('blogGetRandomAdByPlacement') ? blogGetRandomAdByPlacement($db, 'blog_infeed') : null;
$postSidebarAd = function_exists('blogGetRandomAdByPlacement') ? blogGetRandomAdByPlacement($db, 'blog_sidebar') : null;

// Build post body with inline ad inserted in the middle (around 3rd/4th paragraph), not at bottom.
$rawContent = (string) ($post['content'] ?? '');
$paragraphs = preg_split("/\r\n\r\n|\n\n|\r\r/", trim($rawContent)) ?: [];
$paragraphs = array_values(array_filter(array_map('trim', $paragraphs), static fn($p) => $p !== ''));

$inlineAdHtml = '';
if ($postInlineAd) {
    ob_start();
    ?>
    <section class="blog-ad blog-ad-infeed" style="margin:14px 0;">
        <span class="ad-badge">Sponsored</span>
        <?php if (!empty($postInlineAd['target_url'])): ?><a href="<?php echo htmlspecialchars((string) $postInlineAd['target_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="nofollow sponsored noopener"><?php endif; ?>
            <?php if ((($postInlineAd['ad_type'] ?? '') === 'image' || ($postInlineAd['ad_type'] ?? '') === 'gif') && !empty($postInlineAd['media_url'])): ?>
                <img src="<?php echo htmlspecialchars((string) $postInlineAd['media_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Sponsored Ad" class="ad-media">
            <?php else: ?>
                <h4><?php echo htmlspecialchars((string) ($postInlineAd['title'] ?? 'Sponsored'), ENT_QUOTES, 'UTF-8'); ?></h4>
                <p><?php echo htmlspecialchars((string) ($postInlineAd['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (!empty($postInlineAd['cta_text']) && !empty($postInlineAd['target_url'])): ?>
                    <div style="margin-top:8px;"><span class="blog-cta"><?php echo htmlspecialchars((string) $postInlineAd['cta_text'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                <?php endif; ?>
            <?php endif; ?>
        <?php if (!empty($postInlineAd['target_url'])): ?></a><?php endif; ?>
    </section>
    <?php
    $inlineAdHtml = (string) ob_get_clean();
}

$postContentHtml = '';
if (!empty($paragraphs)) {
    $insertAfter = min(4, max(3, (int) floor(count($paragraphs) / 2)));
    foreach ($paragraphs as $i => $para) {
        $postContentHtml .= '<p>' . nl2br(htmlspecialchars($para, ENT_QUOTES, 'UTF-8')) . '</p>';
        if ($inlineAdHtml !== '' && ($i + 1) === $insertAfter) {
            $postContentHtml .= $inlineAdHtml;
            $inlineAdHtml = '';
        }
    }
    if ($inlineAdHtml !== '' && count($paragraphs) >= 2) {
        // Fallback for unusual paragraph counts: keep ad inside body, not at very end section.
        $postContentHtml = preg_replace('/(<\/p>)/', '$1' . $inlineAdHtml, $postContentHtml, 1) ?: ($postContentHtml . $inlineAdHtml);
    }
} else {
    $postContentHtml = nl2br(htmlspecialchars($rawContent, ENT_QUOTES, 'UTF-8'));
}

require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/blog.css">
<main class="blog-shell">
    <?php if ($postLeaderboardAd): ?>
        <section class="blog-ad blog-ad-leaderboard" style="margin-bottom:14px;">
            <span class="ad-badge">Sponsored</span>
            <?php if (!empty($postLeaderboardAd['target_url'])): ?><a href="<?php echo htmlspecialchars((string) $postLeaderboardAd['target_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="nofollow sponsored noopener"><?php endif; ?>
                <?php if ((($postLeaderboardAd['ad_type'] ?? '') === 'image' || ($postLeaderboardAd['ad_type'] ?? '') === 'gif') && !empty($postLeaderboardAd['media_url'])): ?>
                    <img src="<?php echo htmlspecialchars((string) $postLeaderboardAd['media_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Sponsored Ad" class="ad-media">
                <?php else: ?>
                    <h4><?php echo htmlspecialchars((string) ($postLeaderboardAd['title'] ?? 'Sponsored'), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <p><?php echo htmlspecialchars((string) ($postLeaderboardAd['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php if (!empty($postLeaderboardAd['cta_text']) && !empty($postLeaderboardAd['target_url'])): ?>
                        <div style="margin-top:8px;"><span class="blog-cta"><?php echo htmlspecialchars((string) $postLeaderboardAd['cta_text'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php if (!empty($postLeaderboardAd['target_url'])): ?></a><?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="post-layout">
        <section class="post-main">
            <a href="<?php echo BASE_URL; ?>/blog.php">← Back to Blog</a>
            <h1><?php echo htmlspecialchars((string) $post['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="blog-meta">
                <span>By <?php echo htmlspecialchars((string) ($post['author_name'] ?: 'CoinRex Team'), ENT_QUOTES, 'UTF-8'); ?></span>
                <span><?php echo date('M d, Y', strtotime((string) ($post['published_at'] ?: $post['created_at']))); ?></span>
                <span><?php echo blogReadTime((string) $post['content']); ?> min read</span>
            </p>
            <?php if ($primaryCategory): ?>
                <div style="margin:8px 0 14px;"><span class="chip"><?php echo htmlspecialchars((string) $primaryCategory['name'], ENT_QUOTES, 'UTF-8'); ?></span></div>
            <?php endif; ?>
            <article class="post-content">
                <?php echo $postContentHtml; ?>
            </article>

            <?php if (!empty($post['cta_text']) && !empty($post['cta_url'])): ?>
                <div style="margin-top:16px;"><a href="<?php echo htmlspecialchars((string) $post['cta_url'], ENT_QUOTES, 'UTF-8'); ?>"><span class="blog-cta"><?php echo htmlspecialchars((string) $post['cta_text'], ENT_QUOTES, 'UTF-8'); ?></span></a></div>
            <?php endif; ?>
        </section>

        <aside class="blog-sidebar">
            <div class="widget">
                <h4>Related Posts</h4>
                <div class="related-list">
                    <?php if (!empty($relatedPosts)): ?>
                        <?php foreach ($relatedPosts as $rel): ?>
                            <a href="<?php echo BASE_URL; ?>/blog-post.php?slug=<?php echo urlencode((string) $rel['slug']); ?>">
                                <?php echo htmlspecialchars((string) $rel['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color:#94a3b8;display:block;padding:8px;">More posts coming soon.</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($postSidebarAd): ?>
                <div class="widget blog-ad blog-ad-sidebar">
                    <span class="ad-badge">Sponsored</span>
                    <?php if (!empty($postSidebarAd['target_url'])): ?><a href="<?php echo htmlspecialchars((string) $postSidebarAd['target_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="nofollow sponsored noopener"><?php endif; ?>
                        <?php if ((($postSidebarAd['ad_type'] ?? '') === 'image' || ($postSidebarAd['ad_type'] ?? '') === 'gif') && !empty($postSidebarAd['media_url'])): ?>
                            <img src="<?php echo htmlspecialchars((string) $postSidebarAd['media_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Sponsored Ad" class="ad-media">
                        <?php else: ?>
                            <h4><?php echo htmlspecialchars((string) ($postSidebarAd['title'] ?? 'Sponsored'), ENT_QUOTES, 'UTF-8'); ?></h4>
                            <p><?php echo htmlspecialchars((string) ($postSidebarAd['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php if (!empty($postSidebarAd['cta_text']) && !empty($postSidebarAd['target_url'])): ?>
                                <div style="margin-top:8px;"><span class="blog-cta"><?php echo htmlspecialchars((string) $postSidebarAd['cta_text'], ENT_QUOTES, 'UTF-8'); ?></span></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php if (!empty($postSidebarAd['target_url'])): ?></a><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="widget">
                <h4>Latest Articles</h4>
                <div class="related-list">
                    <?php foreach ($latestPosts as $item): ?>
                        <a href="<?php echo BASE_URL; ?>/blog-post.php?slug=<?php echo urlencode((string) $item['slug']); ?>"><?php echo htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
