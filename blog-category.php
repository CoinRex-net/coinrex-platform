<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') { http_response_code(404); die('Category not found'); }
$db = getDBConnection();
$catStmt = $db->prepare("SELECT * FROM blog_categories WHERE slug=? LIMIT 1");
$catStmt->execute([$slug]);
$category = $catStmt->fetch();
if (!$category) { http_response_code(404); die('Category not found'); }
$stmt = $db->prepare("SELECT p.* FROM blog_posts p JOIN blog_post_categories pc ON pc.post_id=p.id WHERE pc.category_id=? AND p.status='published' ORDER BY COALESCE(p.published_at,p.created_at) DESC");
$stmt->execute([(int)$category['id']]);
$posts = $stmt->fetchAll() ?: [];
require_once __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/blog.css">
<main class="blog-shell">
    <section class="blog-hero">
        <h1>Category: <?php echo htmlspecialchars((string)$category['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p><?php echo !empty($category['description']) ? htmlspecialchars((string)$category['description'], ENT_QUOTES, 'UTF-8') : 'Curated posts under this topic.'; ?></p>
    </section>
    <div class="archive-list">
    <?php foreach ($posts as $post): ?>
        <article class="archive-item">
            <a href="<?php echo BASE_URL; ?>/blog-post.php?slug=<?php echo urlencode((string)$post['slug']); ?>"><?php echo htmlspecialchars((string)$post['title'], ENT_QUOTES, 'UTF-8'); ?></a>
            <div class="blog-meta" style="margin-top:8px;"><?php echo date('M d, Y', strtotime((string)($post['published_at'] ?: $post['created_at']))); ?></div>
        </article>
    <?php endforeach; ?>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
