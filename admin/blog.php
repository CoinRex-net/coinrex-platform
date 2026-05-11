<?php
$page_title = 'Blog';
$activePage = 'blog';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$status = trim((string) ($_GET['status'] ?? 'all'));
$q = trim((string) ($_GET['q'] ?? ''));
$where = [];
$params = [];
if (in_array($status, ['draft', 'published', 'archived'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.slug LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("SELECT p.*, a.name AS author_name FROM blog_posts p LEFT JOIN admins a ON a.id=p.author_admin_id {$whereSql} ORDER BY p.created_at DESC LIMIT 300");
$stmt->execute($params);
$posts = $stmt->fetchAll() ?: [];
?>

<div class="panel">
    <div class="admin-toolbar">
        <div>
            <h3 style="margin:0;">Blog Posts</h3>
            <p class="muted" style="margin:6px 0 0;">Manage CoinRex content and publication status.</p>
        </div>
        <div class="inline-form">
            <a class="btn btn-primary" href="<?php echo ADMIN_BASE_URL; ?>/blog-create.php">+ New Post</a>
            <a class="btn btn-secondary" href="<?php echo ADMIN_BASE_URL; ?>/blog-categories.php">Categories</a>
            <a class="btn btn-secondary" href="<?php echo ADMIN_BASE_URL; ?>/blog-tags.php">Tags</a>
        </div>
    </div>
</div>

<div class="panel">
    <form method="get" class="project-filter-grid">
        <input type="text" name="q" placeholder="Search title or slug" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
        <select name="status">
            <option value="all">All Statuses</option>
            <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
            <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
            <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
        </select>
        <button class="btn btn-secondary" type="submit">Filter</button>
    </form>
</div>

<div class="panel">
    <div class="table-wrap">
        <table class="responsive-table">
            <thead><tr><th>ID</th><th>Title</th><th>Slug</th><th>Status</th><th>Author</th><th>Published</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($posts as $post): ?>
                <tr>
                    <td data-label="ID"><?php echo (int) $post['id']; ?></td>
                    <td data-label="Title"><?php echo htmlspecialchars((string) $post['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Slug">/<?php echo htmlspecialchars((string) $post['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Status"><span class="status-pill status-<?php echo htmlspecialchars((string) $post['status'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ucfirst((string) $post['status']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="Author"><?php echo htmlspecialchars((string) ($post['author_name'] ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Published"><?php echo !empty($post['published_at']) ? htmlspecialchars((string) $post['published_at'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                    <td data-label="Action" style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a class="btn btn-secondary" href="<?php echo ADMIN_BASE_URL; ?>/blog-edit.php?id=<?php echo (int) $post['id']; ?>">Edit</a>
                        <form method="post" action="<?php echo ADMIN_BASE_URL; ?>/blog-delete.php" class="delete-post-form" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="id" value="<?php echo (int) $post['id']; ?>">
                            <button class="btn" type="submit" style="background:#7f1d1d;color:#fee2e2;border:1px solid #b91c1c;">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="deleteModal" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,.72);z-index:5000;align-items:center;justify-content:center;padding:16px;">
    <div style="width:min(460px,100%);background:linear-gradient(160deg,#111827,#1e1b4b);border:1px solid #334155;border-radius:16px;padding:18px;color:#e5e7eb;box-shadow:0 16px 40px rgba(0,0,0,.45);">
        <h3 style="margin:0 0 8px;color:#fda4af;">Delete Blog Post?</h3>
        <p style="margin:0 0 14px;color:#cbd5e1;">This action is permanent and cannot be undone.</p>
        <div style="display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" id="cancelDeleteBtn" class="btn btn-secondary">Cancel</button>
            <button type="button" id="confirmDeleteBtn" class="btn" style="background:#991b1b;color:#fee2e2;border:1px solid #ef4444;">Yes, Delete</button>
        </div>
    </div>
</div>

<script>
(function () {
  const modal = document.getElementById('deleteModal');
  const confirmBtn = document.getElementById('confirmDeleteBtn');
  const cancelBtn = document.getElementById('cancelDeleteBtn');
  let targetForm = null;

  document.querySelectorAll('.delete-post-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      targetForm = form;
      modal.style.display = 'flex';
    });
  });

  cancelBtn.addEventListener('click', function () {
    modal.style.display = 'none';
    targetForm = null;
  });

  confirmBtn.addEventListener('click', function () {
    if (targetForm) targetForm.submit();
  });

  modal.addEventListener('click', function (e) {
    if (e.target === modal) {
      modal.style.display = 'none';
      targetForm = null;
    }
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
