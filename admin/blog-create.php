<?php
$page_title = 'Create Blog Post';
$activePage = 'blog-create';
require_once __DIR__ . '/includes/config.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();
$message = '';
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
        $slug = blogUniqueSlug($db, $title);
        $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare("INSERT INTO blog_posts (title,slug,excerpt,content,content_md,author_admin_id,status,seo_title,seo_description,cta_text,cta_url,cta_type,published_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $stmt->execute([$title, $slug, $excerpt, $content, $content_md ?: null, (int) ($current_admin['id'] ?? 0), $status, $seo_title ?: null, $seo_description ?: null, $cta_text ?: null, $cta_url ?: null, $cta_type ?: null, $published_at]);
        $post_id = (int) $db->lastInsertId();

        foreach ((array) ($_POST['categories'] ?? []) as $category_id) {
            $catId = (int) $category_id;
            if ($catId > 0) {
                $db->prepare("INSERT IGNORE INTO blog_post_categories (post_id, category_id) VALUES (?, ?)")->execute([$post_id, $catId]);
            }
        }

        foreach ((array) ($_POST['tags'] ?? []) as $tag_id) {
            $tagId = (int) $tag_id;
            if ($tagId > 0) {
                $db->prepare("INSERT IGNORE INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)")->execute([$post_id, $tagId]);
            }
        }

        header('Location: ' . ADMIN_BASE_URL . '/blog-edit.php?id=' . $post_id . '&saved=1');
        exit();
    }
}

require_once __DIR__ . '/includes/header.php';

$categories = $db->query("SELECT id,name FROM blog_categories ORDER BY name ASC")->fetchAll() ?: [];
$tags = $db->query("SELECT id,name FROM blog_tags ORDER BY name ASC")->fetchAll() ?: [];
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
            <div class="dashboard-header-icon"><i class="fas fa-blog"></i></div>
            <div class="dashboard-header-text">
                <h1>Create Blog Post</h1>
                <p>Craft educational and conversion-focused content for CoinRex</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-pen"></i> New Post
        </div>
    </div>

    <!-- ====== SECTION 1: OVERVIEW ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-chart-bar"></i> Overview <span class="divider-sub">New blog post editor</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-blog"></i> Blog</span>
                <h3>Create a High-Quality Blog Post</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Use the advanced editor below to craft your content with rich formatting.</p>
            </div>
            <a href="<?php echo ADMIN_BASE_URL; ?>/blog.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Blog</a>
        </div>
    </div>

    <!-- ====== SECTION 2: EDITOR ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-edit"></i> Editor <span class="divider-sub">Write and format your blog post</span></h2>
    </div>

    <div class="blog-admin-grid" style="display:grid;grid-template-columns:1fr 320px;gap:16px;align-items:start;">
        <div class="dashboard-panel" style="margin:0;">
            <form method="post" id="blogCreateForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="split-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label class="field-label">Post Title</label>
                        <input class="input-pro" type="text" name="title" placeholder="Example: How to maximize LearnHub rewards" required>
                    </div>
                    <div>
                        <label class="field-label">Publication Status</label>
                        <select class="input-pro" name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select>
                    </div>
                </div>

                <label class="field-label">Excerpt</label>
                <textarea class="input-pro" name="excerpt" style="min-height:90px;" placeholder="Short summary shown on cards and search listings"></textarea>

                <label class="field-label">Main Content</label>

                <!-- Editor Toggle -->
                <div class="editor-toggle-group">
                    <button type="button" class="editor-toggle-btn active" data-editor-toggle="visual">Visual Editor</button>
                    <button type="button" class="editor-toggle-btn" data-editor-toggle="markdown">Markdown</button>
                </div>

                <!-- Visual Editor Panel -->
                <div class="editor-panel active" data-editor-panel="visual">
                    <textarea class="input-pro" name="content_html" id="content_html" style="min-height:320px;"></textarea>
                </div>

                <!-- Markdown Editor Panel -->
                <div class="editor-panel" data-editor-panel="markdown">
                    <textarea class="input-pro" name="content_md" id="content_md" style="min-height:320px;font-family:'Consolas','Courier New',monospace;font-size:14px;line-height:1.6;" placeholder="Paste your Markdown content here...&#10;&#10;# Heading 1&#10;## Heading 2&#10;**Bold** *Italic*&#10;- List item&#10;1. Numbered list&#10;`code`&#10;```&#10;code block&#10;```"></textarea>
                    <p style="color:#94a3b8;font-size:12px;margin-top:6px;"><i class="fas fa-info-circle"></i> Markdown will be converted to HTML when saved.</p>
                </div>

                <div class="split-2" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
                    <div><label class="field-label">SEO Title</label><input class="input-pro" type="text" name="seo_title"></div>
                    <div><label class="field-label">SEO Description</label><input class="input-pro" type="text" name="seo_description"></div>
                </div>

                <div class="split-3" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:12px;">
                    <div><label class="field-label">CTA Text</label><input class="input-pro" type="text" name="cta_text" placeholder="Start LearnHub Now"></div>
                    <div><label class="field-label">CTA URL</label><input class="input-pro" type="text" name="cta_url" placeholder="https://..."></div>
                    <div><label class="field-label">CTA Type</label><select class="input-pro" name="cta_type"><option value="custom">Custom</option><option value="taskhub">LearnHub</option><option value="devhub">DevHub</option><option value="claims">Claims</option></select></div>
                </div>

                <label class="field-label">Categories</label>
                <select class="input-pro" name="categories[]" multiple style="min-height:110px;"><?php foreach ($categories as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>

                <label class="field-label">Tags</label>
                <select class="input-pro" name="tags[]" multiple style="min-height:110px;"><?php foreach ($tags as $t): ?><option value="<?php echo (int) $t['id']; ?>"><?php echo htmlspecialchars((string) $t['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>

                <div style="margin-top:14px;"><button class="btn btn-primary" type="submit">Create Post</button></div>
            </form>
        </div>

        <aside class="dashboard-panel" style="margin:0;">
            <div class="dashboard-panel-header" style="border:none;padding-bottom:0;">
                <div>
                    <span class="modal-kicker"><i class="fas fa-lightbulb"></i> Guide</span>
                    <h3 style="font-size:15px;">Publishing Guide</h3>
                </div>
            </div>
            <ul class="helper-list" style="list-style:none;padding:0;margin:12px 0 0;">
                <li style="padding:6px 0;border-bottom:1px solid rgba(148,163,184,0.1);color:#cbd5e1;font-size:13px;">✓ Write one clear promise in title and opening paragraph.</li>
                <li style="padding:6px 0;border-bottom:1px solid rgba(148,163,184,0.1);color:#cbd5e1;font-size:13px;">✓ Use H2/H3 headings, bullets, and short paragraphs.</li>
                <li style="padding:6px 0;border-bottom:1px solid rgba(148,163,184,0.1);color:#cbd5e1;font-size:13px;">✓ Use colored highlights only where attention is needed.</li>
                <li style="padding:6px 0;border-bottom:1px solid rgba(148,163,184,0.1);color:#cbd5e1;font-size:13px;">✓ Assign the most relevant category for better related-post matching.</li>
                <li style="padding:6px 0;color:#cbd5e1;font-size:13px;">✓ Add CTA that maps to LearnHub, DevHub, or Claims intent.</li>
            </ul>
            <div style="margin-top:12px;padding:10px;background:linear-gradient(135deg,rgba(99,102,241,0.08),rgba(139,92,246,0.08));border:1px solid rgba(99,102,241,0.15);border-radius:10px;color:#a5b4fc;font-size:12px;">
                <i class="fas fa-palette"></i> Dark theme palette upgraded with Indigo, Violet, Blue, and Teal accents for a premium editorial feel.
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

document.getElementById('blogCreateForm').addEventListener('submit', function () {
  // Sync SunEditor content to textarea before submit
  document.getElementById('content_html').value = editor.getContents();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
