<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') { http_response_code(404); die('Tag not found'); }
$db = getDBConnection();
$tagStmt = $db->prepare("SELECT * FROM blog_tags WHERE slug=? LIMIT 1");
$tagStmt->execute([$slug]);
$tag = $tagStmt->fetch();
if (!$tag) { http_response_code(404); die('Tag not found'); }
$stmt = $db->prepare("SELECT p.* FROM blog_posts p JOIN blog_post_tags pt ON pt.post_id=p.id WHERE pt.tag_id=? AND p.status='published' ORDER BY COALESCE(p.published_at,p.created_at) DESC");
$stmt->execute([(int)$tag['id']]);
$posts = $stmt->fetchAll() ?: [];
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/blog.css">
<main class="blog-shell">
    <section class="blog-hero">
        <h1>Tag: <?php echo htmlspecialchars((string)$tag['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p>Topic-focused articles matched by tag relevance.</p>
    </section>
    <div class="archive-list">
    <?php foreach ($posts as $post): ?>
        <article class="archive-item">
            <a href="<?php echo BASE_URL; ?>/blog-post.php/<?php echo urlencode((string)$post['slug']); ?>"><?php echo htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></a>
            <div class="blog-meta" style="margin-top:8px;"><?php echo date('M d, Y', strtotime((string)($post['published_at'] ?: $post['created_at']))); ?></div>
        </article>
    <?php endforeach; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
