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

// Calculate stats
$total = count($posts);
$published_count = 0;
$draft_count = 0;
$archived_count = 0;
foreach ($posts as $p) {
    $s = (string) ($p['status'] ?? '');
    if ($s === 'published') $published_count++;
    elseif ($s === 'draft') $draft_count++;
    elseif ($s === 'archived') $archived_count++;
}
?>
<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-blog"></i></div>
            <div class="dashboard-header-text">
                <h1>Blog Posts</h1>
                <p>Manage CoinRex content and publication status</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-database"></i> <?php echo number_format($total); ?> posts
        </div>
    </div>

    <!-- ====== SECTION 1: OVERVIEW METRICS ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-chart-bar"></i> Overview <span class="divider-sub">Blog content metrics</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-blog"></i> Blog</span>
                <h3>Blog Management</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Create, edit, and manage blog posts, categories, tags, and ads.</p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn btn-primary" href="<?php echo ADMIN_BASE_URL; ?>/blog-create.php"><i class="fas fa-plus"></i> New Post</a>
                <a class="btn btn-secondary" href="<?php echo ADMIN_BASE_URL; ?>/blog-categories.php"><i class="fas fa-tags"></i> Categories</a>
                <a class="btn btn-secondary" href="<?php echo ADMIN_BASE_URL; ?>/blog-tags.php"><i class="fas fa-tag"></i> Tags</a>
                <a class="btn btn-secondary" href="<?php echo ADMIN_BASE_URL; ?>/blog-ads.php"><i class="fas fa-ad"></i> Ads</a>
            </div>
        </div>
        <div class="dashboard-metric-grid">
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-blue"><i class="fas fa-file-alt"></i></div></div>
                <span class="metric-value"><?php echo number_format($total); ?></span>
                <span class="metric-label">Total Posts</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-green"><i class="fas fa-check-circle"></i></div></div>
                <span class="metric-value"><?php echo number_format($published_count); ?></span>
                <span class="metric-label">Published</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-orange"><i class="fas fa-pen"></i></div></div>
                <span class="metric-value"><?php echo number_format($draft_count); ?></span>
                <span class="metric-label">Drafts</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-red"><i class="fas fa-archive"></i></div></div>
                <span class="metric-value"><?php echo number_format($archived_count); ?></span>
                <span class="metric-label">Archived</span>
            </div>
        </div>
    </div>

    <!-- ====== SECTION 2: FILTER BAR ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-filter"></i> Filter <span class="divider-sub">Search and filter blog posts</span></h2>
    </div>

    <div class="dashboard-panel" style="margin-bottom:16px;">
        <div class="dashboard-filter-bar">
            <div>
                <h3 style="margin:0 0 4px;font-size:15px;font-weight:700;color:#f1f5f9;">Search Posts</h3>
                <p class="muted" style="margin:0;font-size:12px;">Filter by title, slug, or publication status.</p>
            </div>
            <form method="GET" action="" class="dashboard-filter-form">
                <input type="text" name="q" placeholder="Search title or slug" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
                <select name="status">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
                    <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filter</button>
            </form>
        </div>
    </div>

    <!-- ====== SECTION 3: POSTS TABLE ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-list"></i> Posts <span class="divider-sub">All blog posts matching current filter</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Author</th>
                    <th>Published</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($posts)): ?>
                    <tr><td colspan="7" class="muted" style="text-align:center;padding:30px;color:#64748b;">No posts found.</td></tr>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td data-label="ID"><?php echo (int) $post['id']; ?></td>
                            <td data-label="Title"><?php echo htmlspecialchars((string) $post['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Slug">/<?php echo htmlspecialchars((string) $post['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Status">
                                <span class="dashboard-pill <?php
                                    $s = (string) ($post['status'] ?? '');
                                    if ($s === 'published') echo 'is-active';
                                    elseif ($s === 'draft') echo 'is-pending';
                                    elseif ($s === 'archived') echo 'is-suspended';
                                ?>"><?php echo htmlspecialchars(ucfirst((string) $post['status']), ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td data-label="Author"><?php echo htmlspecialchars((string) ($post['author_name'] ?: 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Published"><?php echo !empty($post['published_at']) ? htmlspecialchars((string) $post['published_at'], ENT_QUOTES, 'UTF-8') : '-'; ?></td>
                            <td data-label="Action">
                                <div class="action-stack">
                                    <a class="btn btn-secondary action-stack-btn" href="<?php echo ADMIN_BASE_URL; ?>/blog-edit.php?id=<?php echo (int) $post['id']; ?>"><i class="fas fa-edit"></i> Edit</a>
                                    <form method="post" action="<?php echo ADMIN_BASE_URL; ?>/blog-delete.php" class="delete-post-form" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) $post['id']; ?>">
                                        <button class="btn action-stack-btn" type="submit" style="background:#7f1d1d;color:#fee2e2;border:1px solid #b91c1c;width:100%;justify-content:center;"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.dashboard-container -->

<!-- ====== DELETE CONFIRMATION MODAL ====== -->
<div class="dashboard-modal" id="deleteModal">
    <div class="dashboard-modal-card" style="max-width:460px;">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-trash"></i> Delete Post</span>
                <h3>Delete Blog Post?</h3>
            </div>
            <button type="button" class="dashboard-modal-close" id="cancelDeleteBtn">&times;</button>
        </div>
        <div class="dashboard-modal-body">
            <p style="color:#cbd5e1;margin:0 0 16px;">This action is permanent and cannot be undone.</p>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" id="cancelDeleteBtn2">Cancel</button>
                <button type="button" class="btn" id="confirmDeleteBtn" style="background:#991b1b;color:#fee2e2;border:1px solid #ef4444;">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
  const modal = document.getElementById('deleteModal');
  const confirmBtn = document.getElementById('confirmDeleteBtn');
  const cancelBtns = document.querySelectorAll('#cancelDeleteBtn, #cancelDeleteBtn2');
  let targetForm = null;

  document.querySelectorAll('.delete-post-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      targetForm = form;
      modal.classList.add('show');
      document.body.style.overflow = 'hidden';
    });
  });

  cancelBtns.forEach(function (btn) {
    btn.addEventListener('click', function () {
      modal.classList.remove('show');
      document.body.style.overflow = '';
      targetForm = null;
    });
  });

  confirmBtn.addEventListener('click', function () {
    if (targetForm) targetForm.submit();
  });

  modal.addEventListener('click', function (e) {
    if (e.target === modal) {
      modal.classList.remove('show');
      document.body.style.overflow = '';
      targetForm = null;
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('show')) {
      modal.classList.remove('show');
      document.body.style.overflow = '';
      targetForm = null;
    }
  });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
