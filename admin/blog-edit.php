<?php
$page_title = 'Edit Blog Post';
$activePage = 'blog-edit';
require_once __DIR__ . '/includes/header.php';

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
        $slug = blogUniqueSlug($db, $title, $id);
        $published_at = $status === 'published' ? ($post['published_at'] ?: date('Y-m-d H:i:s')) : null;
        $upd = $db->prepare("UPDATE blog_posts SET title=?,slug=?,excerpt=?,content=?,status=?,seo_title=?,seo_description=?,cta_text=?,cta_url=?,cta_type=?,published_at=?,updated_at=NOW() WHERE id=?");
        $upd->execute([$title, $slug, $excerpt, $content, $status, $seo_title ?: null, $seo_description ?: null, $cta_text ?: null, $cta_url ?: null, $cta_type ?: null, $published_at, $id]);

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

$stmt = $db->prepare("SELECT * FROM blog_posts WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$post = $stmt->fetch();
$categories = $db->query("SELECT id,name FROM blog_categories ORDER BY name ASC")->fetchAll() ?: [];
$tags = $db->query("SELECT id,name FROM blog_tags ORDER BY name ASC")->fetchAll() ?: [];
$postCategoryIds = array_map('intval', array_column($db->query("SELECT category_id FROM blog_post_categories WHERE post_id=" . (int) $id)->fetchAll() ?: [], 'category_id'));
$postTagIds = array_map('intval', array_column($db->query("SELECT tag_id FROM blog_post_tags WHERE post_id=" . (int) $id)->fetchAll() ?: [], 'tag_id'));
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/admin-blog-editor.css">
<style>#content_html{min-height:320px;}</style>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<div class="blog-admin-shell">
    <section class="blog-admin-hero">
        <h2>Edit Blog Post</h2>
        <p>Refine content quality, SEO clarity, and conversion intent with an improved colorful dark-theme editor.</p>
        <span class="pill-note">Live editing mode</span>
    </section>

    <div class="blog-admin-grid">
        <div class="blog-admin-card">
            <form method="post" id="blogEditForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="split-2">
                    <div><label class="field-label">Post Title</label><input class="input-pro" type="text" name="title" value="<?php echo htmlspecialchars((string) $post['title'], ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div><label class="field-label">Publication Status</label><select class="input-pro" name="status"><option value="draft" <?php echo $post['status']==='draft'?'selected':''; ?>>Draft</option><option value="published" <?php echo $post['status']==='published'?'selected':''; ?>>Published</option><option value="archived" <?php echo $post['status']==='archived'?'selected':''; ?>>Archived</option></select></div>
                </div>

                <label class="field-label">Excerpt</label>
                <textarea class="input-pro" name="excerpt" style="min-height:90px;"><?php echo htmlspecialchars((string) $post['excerpt'], ENT_QUOTES, 'UTF-8'); ?></textarea>

                <label class="field-label">Main Content</label>
                <textarea class="input-pro" name="content_html" id="content_html" style="min-height:320px;"><?php echo htmlspecialchars((string) ($post['content'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>

                <div class="split-2" style="margin-top:12px;">
                    <div><label class="field-label">SEO Title</label><input class="input-pro" type="text" name="seo_title" value="<?php echo htmlspecialchars((string) $post['seo_title'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div><label class="field-label">SEO Description</label><input class="input-pro" type="text" name="seo_description" value="<?php echo htmlspecialchars((string) $post['seo_description'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>

                <div class="split-3" style="margin-top:12px;">
                    <div><label class="field-label">CTA Text</label><input class="input-pro" type="text" name="cta_text" value="<?php echo htmlspecialchars((string) $post['cta_text'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div><label class="field-label">CTA URL</label><input class="input-pro" type="text" name="cta_url" value="<?php echo htmlspecialchars((string) $post['cta_url'], ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div><label class="field-label">CTA Type</label><select class="input-pro" name="cta_type"><option value="custom" <?php echo ($post['cta_type'] ?? '')==='custom'?'selected':''; ?>>Custom</option><option value="taskhub" <?php echo ($post['cta_type'] ?? '')==='taskhub'?'selected':''; ?>>TaskHub</option><option value="devhub" <?php echo ($post['cta_type'] ?? '')==='devhub'?'selected':''; ?>>DevHub</option><option value="claims" <?php echo ($post['cta_type'] ?? '')==='claims'?'selected':''; ?>>Claims</option></select></div>
                </div>

                <label class="field-label">Categories</label>
                <select class="input-pro" name="categories[]" multiple style="min-height:110px;"><?php foreach ($categories as $c): ?><option value="<?php echo (int) $c['id']; ?>" <?php echo in_array((int) $c['id'], $postCategoryIds, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $c['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>

                <label class="field-label">Tags</label>
                <select class="input-pro" name="tags[]" multiple style="min-height:110px;"><?php foreach ($tags as $t): ?><option value="<?php echo (int) $t['id']; ?>" <?php echo in_array((int) $t['id'], $postTagIds, true) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) $t['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select>

                <div style="margin-top:14px;"><button class="btn btn-primary" type="submit">Save Changes</button></div>
            </form>
        </div>

        <aside class="blog-admin-card">
            <h4>Editor Checklist</h4>
            <ul class="helper-list">
                <li>Check readability on mobile paragraphs.</li>
                <li>Ensure heading hierarchy is clean (H2/H3).</li>
                <li>Add links where users should take action.</li>
                <li>Keep SEO title crisp and human-friendly.</li>
                <li>Use category + tags that match article intent.</li>
            </ul>
            <div class="color-note" style="margin-top:10px;">Palette upgrade applied with Indigo/Violet/Teal accents for a premium dark editorial experience.</div>
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
document.getElementById('blogEditForm').addEventListener('submit', function () {
  document.getElementById('content_html').value = editor.getContents();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
