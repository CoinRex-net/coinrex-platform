<?php
$page_title = 'Create Blog Post';
$activePage = 'blog-create';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $title = trim((string) ($_POST['title'] ?? ''));
    $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
    $content = trim((string) ($_POST['content_html'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'draft'));
    $seo_title = trim((string) ($_POST['seo_title'] ?? ''));
    $seo_description = trim((string) ($_POST['seo_description'] ?? ''));
    $cta_text = trim((string) ($_POST['cta_text'] ?? ''));
    $cta_url = trim((string) ($_POST['cta_url'] ?? ''));
    $cta_type = trim((string) ($_POST['cta_type'] ?? 'custom'));

    if ($title === '' || strip_tags($content) === '') {
        $message = 'Title and content are required.';
        $message_type = 'error';
    } else {
        $slug = blogUniqueSlug($db, $title);
        $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $stmt = $db->prepare("INSERT INTO blog_posts (title,slug,excerpt,content,author_admin_id,status,seo_title,seo_description,cta_text,cta_url,cta_type,published_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
        $stmt->execute([$title, $slug, $excerpt, $content, (int) ($current_admin['id'] ?? 0), $status, $seo_title ?: null, $seo_description ?: null, $cta_text ?: null, $cta_url ?: null, $cta_type ?: null, $published_at]);
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

$categories = $db->query("SELECT id,name FROM blog_categories ORDER BY name ASC")->fetchAll() ?: [];
$tags = $db->query("SELECT id,name FROM blog_tags ORDER BY name ASC")->fetchAll() ?: [];
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-blog-editor.css">
<style>#content_html{min-height:320px;}</style>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<div class="blog-admin-shell">
    <section class="blog-admin-hero">
        <h2>Create a High-Quality Blog Post</h2>
        <p>Craft educational and conversion-focused content for CoinRex using a richer color system and structured publishing flow.</p>
        <span class="pill-note">Advanced editor enabled</span>
    </section>

    <div class="blog-admin-grid">
        <div class="blog-admin-card">
            <form method="post" id="blogCreateForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="split-2">
                    <div>
                        <label class="field-label">Post Title</label>
                        <input class="input-pro" type="text" name="title" placeholder="Example: How to maximize TaskHub rewards" required>
                    </div>
                    <div>
                        <label class="field-label">Publication Status</label>
                        <select class="input-pro" name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select>
                    </div>
                </div>

                <label class="field-label">Excerpt</label>
                <textarea class="input-pro" name="excerpt" style="min-height:90px;" placeholder="Short summary shown on cards and search listings"></textarea>

                <label class="field-label">Main Content</label>
                <textarea class="input-pro" name="content_html" id="content_html" style="min-height:320px;"></textarea>

                <div class="split-2" style="margin-top:12px;">
                    <div><label class="field-label">SEO Title</label><input class="input-pro" type="text" name="seo_title"></div>
                    <div><label class="field-label">SEO Description</label><input class="input-pro" type="text" name="seo_description"></div>
                </div>

                <div class="split-3" style="margin-top:12px;">
                    <div><label class="field-label">CTA Text</label><input class="input-pro" type="text" name="cta_text" placeholder="Start TaskHub Now"></div>
                    <div><label class="field-label">CTA URL</label><input class="input-pro" type="text" name="cta_url" placeholder="https://..."></div>
                    <div><label class="field-label">CTA Type</label><select class="input-pro" name="cta_type"><option value="custom">Custom</option><option value="taskhub">TaskHub</option><option value="devhub">DevHub</option><option value="claims">Claims</option></select></div>
                </div>

                <label class="field-label">Categories</label>
                <select class="input-pro" name="categories[]" multiple style="min-height:110px;"><?php foreach ($categories as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>

                <label class="field-label">Tags</label>
                <select class="input-pro" name="tags[]" multiple style="min-height:110px;"><?php foreach ($tags as $t): ?><option value="<?php echo (int) $t['id']; ?>"><?php echo htmlspecialchars((string) $t['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>

                <div style="margin-top:14px;"><button class="btn btn-primary" type="submit">Create Post</button></div>
            </form>
        </div>

        <aside class="blog-admin-card">
            <h4>Publishing Guide for New Admins</h4>
            <ul class="helper-list">
                <li>Write one clear promise in title and opening paragraph.</li>
                <li>Use H2/H3 headings, bullets, and short paragraphs.</li>
                <li>Use colored highlights only where attention is needed.</li>
                <li>Assign the most relevant category for better related-post matching.</li>
                <li>Add CTA that maps to TaskHub, DevHub, or Claims intent.</li>
            </ul>
            <div class="color-note" style="margin-top:10px;">Dark theme palette upgraded with Indigo, Violet, Blue, and Teal accents for a premium editorial feel.</div>
        </aside>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/suneditor@2.47.5/dist/css/suneditor.min.css">
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
document.getElementById('blogCreateForm').addEventListener('submit', function () {
  document.getElementById('content_html').value = editor.getContents();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
