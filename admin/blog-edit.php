<?php
$page_title = 'Edit Blog Post';
$activePage = 'blog-edit';
require_once __DIR__ . '/includes/config.php';

$db = getDBConnection();
$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM blog_posts WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$post = $stmt->fetch();
if (!$post) {
    die('Post not found');
}

$message = isset($_GET['saved']) ? 'Post saved successfully.' : '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
    $content = trim((string) ($_POST['content_html'] ?? ''));
    $content_md = trim((string) ($_POST['content_md'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'draft'));
    $seo_title = trim((string) ($_POST['seo_title'] ?? ''));
    $seo_description = trim((string) ($_POST['seo_description'] ?? ''));
    $cta_text = trim((string) ($_POST['cta_text'] ?? ''));
    $cta_url = trim((string) ($_POST['cta_url'] ?? ''));
    $cta_type = trim((string) ($_POST['cta_type'] ?? 'custom'));

    // If content_md is provided, convert it to HTML
    if ($content_md !== '' && $content === '') {
        $content = blogMarkdownToHtml($content_md);
    }

    if ($title === '' || (strip_tags($content) === '' && strip_tags($content_md) === '')) {
        $message = 'Title and content are required.';
        $message_type = 'error';
    } else {
        $slug = blogUniqueSlug($db, $title, $id);
        $published_at = $status === 'published' ? ($post['published_at'] ?: date('Y-m-d H:i:s')) : null;
        $upd = $db->prepare("UPDATE blog_posts SET title=?,slug=?,excerpt=?,content=?,content_md=?,status=?,seo_title=?,seo_description=?,cta_text=?,cta_url=?,cta_type=?,published_at=?,updated_at=NOW() WHERE id=?");
        $upd->execute([$title, $slug, $excerpt, $content, $content_md ?: null, $status, $seo_title ?: null, $seo_description ?: null, $cta_text ?: null, $cta_url ?: null, $cta_type ?: null, $published_at, $id]);

        $db->prepare("DELETE FROM blog_post_categories WHERE post_id=?")->execute([$id]);
        foreach ((array) ($_POST['categories'] ?? []) as $category_id) {
            $catId = (int) $category_id;
            if ($catId > 0) $db->prepare("INSERT IGNORE INTO blog_post_categories (post_id, category_id) VALUES (?, ?)")->execute([$id, $catId]);
        }
        $db->prepare("DELETE FROM blog_post_tags WHERE post_id=?")->execute([$id]);
        foreach ((array) ($_POST['tags'] ?? []) as $tag_id) {
            $tagId = (int) $tag_id;
            if ($tagId > 0) $db->prepare("INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)")->execute([$id, $tagId]);
        }

        header('Location: ' . ADMIN_BASE_URL . '/blog-edit.php?id=' . $id . '&saved=1');
        exit();
    }
}

require_once __DIR__ . '/includes/header.php';

$stmt = $db->prepare("SELECT * FROM blog_posts WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$post = $stmt->fetch();
$categories = $db->query("SELECT id,name FROM blog_categories ORDER BY name ASC")->fetchAll() ?: [];
$tags = $db->query("SELECT id,name FROM blog_tags ORDER BY name ASC")->fetchAll() ?: [];
$postCategoryIds = array_map('intval', array_column($db->query("SELECT category_id FROM blog_post_categories WHERE post_id=" . (int) $id)->fetchAll() ?: [], 'category_id'));
$postTagIds = array_map('intval', array_column($db->query("SELECT tag_id FROM blog_post_tags WHERE post_id=" . (int) $id)->fetchAll() ?: [], 'tag_id'));
$postContentMd = (string) ($post['content_md'] ?? '');
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/suneditor@2.47.5/dist/css/suneditor.min.css">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-blog-editor.css">
<style>
/* Dashboard container — ensures proper padding and width */
.dashboard-container {
  width: 100%;
  max-width: 100%;
}
#content_html{min-height:320px;}
/* Sticky SunEditor toolbar */
.sun-editor .se-toolbar {
  position: sticky;
  top: 0;
  z-index: 100;
  background: #1e293b;
  border-bottom: 1px solid rgba(148,163,184,0.18);
}
/* Responsive grid — override inline style on small screens */
@media (max-width: 1000px) {
  .blog-admin-grid {
    grid-template-columns: 1fr !important;
  }
}
@media (max-width: 768px) {
  .sun-editor .se-toolbar {
    top: 0;
  }
}
/* Editor toggle buttons */
.editor-toggle-group {
  display: flex;
  gap: 0;
  margin-bottom: 10px;
  border: 1px solid rgba(148,163,184,0.2);
  border-radius: 8px;
  overflow: hidden;
  width: fit-content;
}
.editor-toggle-btn {
  padding: 8px 18px;
  background: transparent;
  border: none;
  color: #94a3b8;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  transition: all 0.2s;
}
.editor-toggle-btn.active {
  background: rgba(99,102,241,0.15);
  color: #a5b4fc;
}
.editor-toggle-btn:not(.active):hover {
  background: rgba(148,163,184,0.08);
  color: #cbd5e1;
}
.editor-panel {
  display: none;
}
.editor-panel.active {
  display: block;
}
</style>

<?php if ($message !== ''): ?>
    <div data-toast data-toast-message="<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>" data-toast-type="<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" style="display:none;"></div>
<?php endif; ?>

<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-edit"></i></div>
            <div class="dashboard-header-text">
                <h1>Edit Blog Post</h1>
                <p>Refine content quality, SEO clarity, and conversion intent</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-hashtag"></i> Post #<?php echo (int) $post['id']; ?>
        </div>
    </div>

    <!-- ====== SECTION 1: OVERVIEW ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-chart-bar"></i> Overview <span class="divider-sub">Editing blog post #<?php echo (int) $post['id']; ?></span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-blog"></i> Blog</span>
                <h3>Edit Blog Post</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Use the advanced editor below to refine your content with rich formatting.</p>
            </div>
            <a href="<?php echo ADMIN_BASE_URL; ?>/blog.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Blog</a>
        </div>
        <div class="dashboard-metric-grid">
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-blue"><i class="fas fa-file-alt"></i></div></div>
                <span class="metric-value">#<?php echo (int) $post['id']; ?></span>
                <span class="metric-label">Editing Post</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon <?php echo $post['status'] === 'published' ? 'is-green' : ($post['status'] === 'draft' ? 'is-orange' : 'is-red'); ?>"><i class="fas fa-<?php echo $post['status'] === 'published' ? 'check-circle' : ($post['status'] === 'draft' ? 'pen' : 'archive'); ?>"></i></div></div>
                <span class="metric-value"><?php echo htmlspecialchars(ucfirst((string) $post['status']), ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="metric-label">Status</span>
            </div>
        </div>
    </div>

    <!-- ====== SECTION 2: EDITOR ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-edit"></i> Editor <span class="divider-sub">Write and format your blog post</span></h2>
    </div>

    <div class="blog-admin-grid" style="display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start;">
        <div class="dashboard-panel" style="margin:0;">
            <form method="post" id="blogEditForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="split-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div><label class="field-label">Post Title</label><input class="input-pro" type="text" name="title" value="<?php echo htmlspecialchars((string) $post['title'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div><label class="field-label">Publication Status</label><select class="input-pro" name="status"><option value="draft" <?php echo $post['status']==='draft'?'selected':''; ?>>Draft</option><option value="published" <?php echo $post['status']==='published'?'selected':''; ?>>Published</option><option value="archived" <?php echo $post['status']==='archived'?'selected':''; ?>>Archived</option></select></div>
                </div>

                <label class="field-label">Excerpt</label>
                <textarea class="input-pro" name="excerpt" style="min-height:90px;"><?php echo htmlspecialchars((string) $post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></textarea>

                <label class="field-label">Main Content</label>

                <!-- Editor Toggle -->
                <div class="editor-toggle-group">
                    <button type="button" class="editor-toggle-btn <?php echo $postContentMd === '' ? 'active' : ''; ?>" data-editor-toggle="visual">Visual Editor</button>
                    <button type="button" class="editor-toggle-btn <?php echo $postContentMd !== '' ? 'active' : ''; ?>" data-editor-toggle="markdown">Markdown</button>
                </div>

                <!-- Visual Editor Panel -->
                <div class="editor-panel <?php echo $postContentMd === '' ? 'active' : ''; ?>" data-editor-panel="visual">
                    <textarea class="input-pro" name="content_html" id="content_html" style="min-height:320px;"><?php echo htmlspecialchars((string) ($post['content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <!-- Markdown Editor Panel -->
                <div class="editor-panel <?php echo $postContentMd !== '' ? 'active' : ''; ?>" data-editor-panel="markdown">
                    <textarea class="input-pro" name="content_md" id="content_md" style="min-height:320px;font-family:'Consolas','Courier New',monospace;font-size:14px;line-height:1.6;" placeholder="Paste your Markdown content here..."><?php echo htmlspecialchars($postContentMd, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <p style="color:#94a3b8;font-size:12px;margin-top:6px;"><i class="fas fa-info-circle"></i> Markdown will be converted to HTML when saved.</p>
                </div>

                <div class="split-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                    <div><label class="field-label">SEO Title</label><input class="input-pro" type="text" name="seo_title" value="<?php echo htmlspecialchars((string) $post['seo_title'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div><label class="field-label">SEO Description</label><input class="input-pro" type="text" name="seo_description" value="<?php echo htmlspecialchars((string) $post['seo_description'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>

                <div class="split-3" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:12px;">
                    <div><label class="field-label">CTA Text</label><input class="input-pro" type="text" name="cta_text" value="<?php echo htmlspecialchars((string) $post['cta_text'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div><label class="field-label">CTA URL</label><input class="input-pro" type="text" name="cta_url" value="<?php echo htmlspecialchars((string) $post['cta_url'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div><label class="field-label">CTA Type</label><select class="input-pro" name="cta_type"><option value="custom" <?php echo ($post['cta_type'] ?? '')==='custom'?'selected':''; ?>>Custom</option><option value="taskhub" <?php echo ($post['cta_type'] ?? '')==='taskhub'?'selected':''; ?>>LearnHub</option><option value="devhub" <?php echo ($post['cta_type'] ?? '')==='devhub'?'selected':''; ?>>DevHub</option><option value="claims" <?php echo ($post['cta_type'] ?? '')==='claims'?'selected':''; ?>>Claims</option></select></div>
                </div>

                <label class="field-label">Categories</label>
                <select class="input-pro" name="categories[]" multiple style="min-height:110px;"><?php foreach ($categories as $c): ?><option value="<?php echo (int) $c['id']; ?>" <?php echo in_array((int) $c['id'], $postCategoryIds, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>

                <label class="field-label">Tags</label>
                <select class="input-pro" name="tags[]" multiple style="min-height:110px;"><?php foreach ($tags as $t): ?><option value="<?php echo (int) $t['id']; ?>" <?php echo in_array((int) $t['id'], $postTagIds, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $t['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>

                <div style="margin-top:14px;"><button class="btn btn-primary" type="submit">Save Changes</button></div>
            </form>
        </div>

        <aside class="dashboard-panel" style="margin:0;">
            <div class="dashboard-panel-header" style="border:none;padding-bottom:0;">
                <div>
                    <span class="modal-kicker"><i class="fas fa-lightbulb"></i> Checklist</span>
                    <h3 style="font-size:15px;">Editor Checklist</h3>
                </div>
            </div>
            <ul class="helper-list" style="list-style:none;padding:0;margin:12px 0 0;">
                <li style="padding:6px 0;border-bottom:1px solid rgba(148,163,184,0.1);color:#cbd5e1;font-size:13px;">✓ Check readability on mobile paragraphs.</li>
                <li style="padding:6px 0;border-bottom:1px solid rgba(148,163,184,0.1);color:#cbd5e1;font-size:13px;">✓ Ensure heading hierarchy is clean (H2/H3).</li>
                <li style="padding:6px 0;border-bottom:1px solid rgba(148,163,184,0.1);color:#cbd5e1;font-size:13px;">✓ Add links where users should take action.</li>
                <li style="padding:6px 0;border-bottom:1px solid rgba(148,163,184,0.1);color:#cbd5e1;font-size:13px;">✓ Keep SEO title crisp and human-friendly.</li>
                <li style="padding:6px 0;color:#cbd5e1;font-size:13px;">✓ Use category + tags that match article intent.</li>
            </ul>
            <div style="margin-top:12px;padding:10px;background:linear-gradient(135deg,rgba(99,102,241,0.08),rgba(139,92,246,0.08));border:1px solid rgba(99,102,241,0.15);border-radius:10px;color:#a5b4fc;font-size:12px;">
                <i class="fas fa-palette"></i> Palette upgrade applied with Indigo/Violet/Teal accents for a premium dark editorial experience.
            </div>
        </aside>
    </div>

</div><!-- /.dashboard-container -->

<script src="https://cdn.jsdelivr.net/npm/suneditor@2.47.5/dist/suneditor.min.js"></script>
<script>
const editor = SUNEDITOR.create('content_html', {
  height: '360',
  buttonList: [
    ['undo', 'redo'],
    ['formatBlock', 'fontSize', 'fontColor', 'hiliteColor'],
    ['bold', 'underline', 'italic', 'strike'],
    ['removeFormat'],
    ['outdent', 'indent'],
    ['align', 'horizontalRule', 'list', 'lineHeight'],
    ['link', 'table', 'codeView'],
    ['fullScreen', 'showBlocks']
  ]
});

// Editor toggle functionality
document.querySelectorAll('[data-editor-toggle]').forEach(function(btn) {
  btn.addEventListener('click', function() {
    // Update toggle buttons
    document.querySelectorAll('[data-editor-toggle]').forEach(function(b) {
      b.classList.remove('active');
    });
    this.classList.add('active');

    // Show/hide editor panels
    var mode = this.getAttribute('data-editor-toggle');
    document.querySelectorAll('[data-editor-panel]').forEach(function(panel) {
      panel.classList.remove('active');
    });
    document.querySelector('[data-editor-panel="' + mode + '"]').classList.add('active');
  });
});

document.getElementById('blogEditForm').addEventListener('submit', function () {
  // Sync SunEditor content to textarea before submit
  document.getElementById('content_html').value = editor.getContents();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
