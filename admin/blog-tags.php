<?php
$page_title = 'Blog Tags';
$activePage = 'blog-tags';
require_once __DIR__ . '/includes/header.php';
$db = getDBConnection();

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name !== '') {
        $slug = blogSlugify($name);
        $db->prepare("INSERT INTO blog_tags (name, slug, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE updated_at=NOW()")->execute([$name, $slug]);
        $message = 'Tag added successfully.';
    } else {
        $message = 'Tag name is required.';
        $message_type = 'error';
    }
}

$rows = $db->query("SELECT * FROM blog_tags ORDER BY name ASC")->fetchAll() ?: [];
?>
<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-tag"></i></div>
            <div class="dashboard-header-text">
                <h1>Blog Tags</h1>
                <p>Manage blog post tags</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-database"></i> <?php echo number_format(count($rows)); ?> tags
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div data-toast data-toast-message="<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>" data-toast-type="<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" style="display:none;"></div>
    <?php endif; ?>

    <!-- ====== SECTION 1: OVERVIEW ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-chart-bar"></i> Overview <span class="divider-sub">Tag management</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-tag"></i> Tags</span>
                <h3>Tag Management</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Add and manage blog post tags.</p>
            </div>
            <a href="<?php echo ADMIN_BASE_URL; ?>/blog.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Blog</a>
        </div>
        <div class="dashboard-metric-grid">
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-blue"><i class="fas fa-tag"></i></div></div>
                <span class="metric-value"><?php echo number_format(count($rows)); ?></span>
                <span class="metric-label">Total Tags</span>
            </div>
        </div>
    </div>

    <!-- ====== SECTION 2: ADD TAG ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-plus-circle"></i> Add Tag <span class="divider-sub">Create a new blog tag</span></h2>
    </div>

    <div class="dashboard-panel" style="margin-bottom:16px;">
        <div class="dashboard-filter-bar">
            <div>
                <h3 style="margin:0 0 4px;font-size:15px;font-weight:700;color:#f1f5f9;">New Tag</h3>
                <p class="muted" style="margin:0;font-size:12px;">Enter a tag name.</p>
            </div>
            <form method="POST" action="" class="dashboard-filter-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="text" name="name" placeholder="Tag name" required>
                <button type="submit" class="btn btn-primary">Add Tag</button>
            </form>
        </div>
    </div>

    <!-- ====== SECTION 3: TAGS TABLE ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-list"></i> All Tags <span class="divider-sub">Existing blog tags</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Slug</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="3" class="muted" style="text-align:center;padding:30px;color:#64748b;">No tags found.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td data-label="ID"><?php echo (int) $r['id']; ?></td>
                            <td data-label="Name"><?php echo htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Slug"><?php echo htmlspecialchars((string) $r['slug'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.dashboard-container -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
