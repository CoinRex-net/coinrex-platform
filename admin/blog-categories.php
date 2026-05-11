<?php
$page_title = 'Blog Categories';
$activePage = 'blog-categories';
require_once __DIR__ . '/includes/header.php';
$db = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    if ($name !== '') {
        $slug = blogSlugify($name);
        $db->prepare("INSERT INTO blog_categories (name, slug, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE description=VALUES(description), updated_at=NOW()")->execute([$name, $slug, $description ?: null]);
    }
    header('Location: ' . ADMIN_BASE_URL . '/blog-categories.php');
    exit();
}

$rows = $db->query("SELECT * FROM blog_categories ORDER BY name ASC")->fetchAll() ?: [];
?>
<div class="panel">
    <form method="post" class="project-filter-grid">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="text" name="name" placeholder="Category name" required>
        <input type="text" name="description" placeholder="Description">
        <button class="btn btn-primary" type="submit">Add Category</button>
    </form>
</div>
<div class="panel"><div class="table-wrap"><table class="responsive-table"><thead><tr><th>ID</th><th>Name</th><th>Slug</th><th>Description</th></tr></thead><tbody><?php foreach ($rows as $r): ?><tr><td data-label="ID"><?php echo (int)$r['id']; ?></td><td data-label="Name"><?php echo htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8'); ?></td><td data-label="Slug"><?php echo htmlspecialchars((string)$r['slug'], ENT_QUOTES, 'UTF-8'); ?></td><td data-label="Description"><?php echo htmlspecialchars((string)($r['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
