<?php
$page_title = 'Blog Ads Manager';
$activePage = 'blog-ads';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$message = '';
$message_type = 'success';
$editId = (int) ($_GET['edit'] ?? 0);
$editAd = null;

$hasCtaText = false;
try {
    $colStmt = $db->query("SHOW COLUMNS FROM blog_ads LIKE 'cta_text'");
    $hasCtaText = (bool) ($colStmt && $colStmt->fetch());
} catch (Throwable $e) {
    $hasCtaText = false;
}

if (!$hasCtaText) {
    try {
        $db->exec("ALTER TABLE blog_ads ADD COLUMN cta_text VARCHAR(80) NULL AFTER target_url");
        $hasCtaText = true;
    } catch (Throwable $e) {
        // keep false; UI will show warning with manual SQL
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $action = trim((string) ($_POST['action'] ?? 'save'));

    try {
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM blog_ads WHERE id=? LIMIT 1")->execute([$id]);
            $message = 'Ad deleted.';
        }
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $placement = trim((string) ($_POST['placement'] ?? ''));
        $ad_type = trim((string) ($_POST['ad_type'] ?? 'text'));
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $media_url = trim((string) ($_POST['media_url'] ?? ''));
        $target_url = trim((string) ($_POST['target_url'] ?? ''));
        $cta_text = trim((string) ($_POST['cta_text'] ?? ''));
        $after_post = (int) ($_POST['after_post'] ?? 3);
        $priority = (int) ($_POST['priority'] ?? 100);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $starts_at = trim((string) ($_POST['starts_at'] ?? ''));
        $ends_at = trim((string) ($_POST['ends_at'] ?? ''));

        $validPlacements = ['blog_leaderboard','blog_infeed','blog_sidebar'];
        $validTypes = ['image','gif','text'];

        if (!in_array($placement, $validPlacements, true) || !in_array($ad_type, $validTypes, true)) {
            $message = 'Invalid placement or ad type.';
            $message_type = 'error';
        } else {
            if (!empty($_FILES['media_file']['name']) && (int) ($_FILES['media_file']['error'] ?? 1) === 0) {
                $uploadDir = __DIR__ . '/../uploads/ads';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0775, true);
                }
                $tmpName = (string) ($_FILES['media_file']['tmp_name'] ?? '');
                $original = (string) ($_FILES['media_file']['name'] ?? '');
                $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
                $allowed = ['jpg','jpeg','png','webp','gif'];
                if (in_array($ext, $allowed, true) && is_uploaded_file($tmpName)) {
                    $fileName = 'ad_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = $uploadDir . '/' . $fileName;
                    if (move_uploaded_file($tmpName, $dest)) {
                        $media_url = BASE_URL . '/uploads/ads/' . $fileName;
                    }
                }
            }

            if ($id > 0) {
                if ($hasCtaText) {
                    $stmt = $db->prepare("UPDATE blog_ads SET placement=?, ad_type=?, title=?, description=?, media_url=?, target_url=?, cta_text=?, after_post=?, is_active=?, starts_at=?, ends_at=?, priority=?, updated_at=NOW() WHERE id=?");
                    $stmt->execute([$placement, $ad_type, $title ?: null, $description ?: null, $media_url ?: null, $target_url ?: null, $cta_text ?: null, $after_post ?: null, $is_active, $starts_at ?: null, $ends_at ?: null, $priority, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE blog_ads SET placement=?, ad_type=?, title=?, description=?, media_url=?, target_url=?, after_post=?, is_active=?, starts_at=?, ends_at=?, priority=?, updated_at=NOW() WHERE id=?");
                    $stmt->execute([$placement, $ad_type, $title ?: null, $description ?: null, $media_url ?: null, $target_url ?: null, $after_post ?: null, $is_active, $starts_at ?: null, $ends_at ?: null, $priority, $id]);
                }
                $message = 'Ad updated.';
            } else {
                if ($hasCtaText) {
                    $stmt = $db->prepare("INSERT INTO blog_ads (placement, ad_type, title, description, media_url, target_url, cta_text, after_post, is_active, starts_at, ends_at, priority, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, ?,NOW(),NOW())");
                    $stmt->execute([$placement, $ad_type, $title ?: null, $description ?: null, $media_url ?: null, $target_url ?: null, $cta_text ?: null, $after_post ?: null, $is_active, $starts_at ?: null, $ends_at ?: null, $priority]);
                } else {
                    $stmt = $db->prepare("INSERT INTO blog_ads (placement, ad_type, title, description, media_url, target_url, after_post, is_active, starts_at, ends_at, priority, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())");
                    $stmt->execute([$placement, $ad_type, $title ?: null, $description ?: null, $media_url ?: null, $target_url ?: null, $after_post ?: null, $is_active, $starts_at ?: null, $ends_at ?: null, $priority]);
                }
                $message = 'Ad created.';
            }
        }
    }
    } catch (Throwable $e) {
        $message = 'Save failed: ' . $e->getMessage();
        $message_type = 'error';
    }
}

$ads = $db->query("SELECT * FROM blog_ads ORDER BY placement ASC, priority ASC, id DESC")->fetchAll() ?: [];
if ($editId > 0) {
    foreach ($ads as $row) {
        if ((int) $row['id'] === $editId) {
            $editAd = $row;
            break;
        }
    }
}
?>

<?php if ($message !== ''): ?><div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<?php if (!$hasCtaText): ?><div class="message message-error">CTA column missing in <code>blog_ads</code>. Run: <code>ALTER TABLE blog_ads ADD COLUMN cta_text VARCHAR(80) NULL AFTER target_url;</code></div><?php endif; ?>

<style>
.ads-shell .panel{border:1px solid #2c3a4d;border-radius:14px;background:linear-gradient(180deg,#0f172a,#111827)}
.ads-shell h3{color:#c4b5fd}
.ads-shell input,.ads-shell select{background:#0b1220;border:1px solid #334155;color:#e5e7eb;border-radius:10px;padding:10px}
</style>

<div class="ads-shell">

<div class="panel">
    <h3 style="margin-top:0;"><?php echo $editAd ? 'Edit Ad #' . (int) $editAd['id'] : 'Create Blog Ad'; ?></h3>
    <form method="post" enctype="multipart/form-data" class="project-filter-grid" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?php echo (int) ($editAd['id'] ?? 0); ?>">
        <select name="placement" required>
            <option value="blog_leaderboard" <?php echo (($editAd['placement'] ?? '') === 'blog_leaderboard') ? 'selected' : ''; ?>>Leaderboard</option>
            <option value="blog_infeed" <?php echo (($editAd['placement'] ?? '') === 'blog_infeed') ? 'selected' : ''; ?>>In-feed</option>
            <option value="blog_sidebar" <?php echo (($editAd['placement'] ?? '') === 'blog_sidebar') ? 'selected' : ''; ?>>Sidebar</option>
        </select>
        <select name="ad_type" required>
            <option value="text" <?php echo (($editAd['ad_type'] ?? '') === 'text') ? 'selected' : ''; ?>>Text</option>
            <option value="image" <?php echo (($editAd['ad_type'] ?? '') === 'image') ? 'selected' : ''; ?>>Image</option>
            <option value="gif" <?php echo (($editAd['ad_type'] ?? '') === 'gif') ? 'selected' : ''; ?>>GIF</option>
        </select>
        <input type="number" name="priority" placeholder="Priority (lower first)" value="<?php echo (int) ($editAd['priority'] ?? 100); ?>">
        <input type="text" name="title" placeholder="Ad title" value="<?php echo htmlspecialchars((string) ($editAd['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="text" name="description" placeholder="Ad description" value="<?php echo htmlspecialchars((string) ($editAd['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="url" name="media_url" placeholder="Media URL (image/gif)" value="<?php echo htmlspecialchars((string) ($editAd['media_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="file" name="media_file" accept="image/png,image/jpeg,image/webp,image/gif">
        <input type="url" name="target_url" placeholder="Target URL (optional)" value="<?php echo htmlspecialchars((string) ($editAd['target_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="text" name="cta_text" placeholder="CTA text (for text ads)" value="<?php echo htmlspecialchars((string) ($editAd['cta_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="number" name="after_post" placeholder="After post # (in-feed)" value="<?php echo (int) ($editAd['after_post'] ?? 3); ?>" min="1" max="20">
        <input type="datetime-local" name="starts_at" value="<?php echo !empty($editAd['starts_at']) ? htmlspecialchars(str_replace(' ', 'T', substr((string)$editAd['starts_at'], 0, 16)), ENT_QUOTES, 'UTF-8') : ''; ?>">
        <input type="datetime-local" name="ends_at" value="<?php echo !empty($editAd['ends_at']) ? htmlspecialchars(str_replace(' ', 'T', substr((string)$editAd['ends_at'], 0, 16)), ENT_QUOTES, 'UTF-8') : ''; ?>">
        <label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_active" <?php echo !isset($editAd['is_active']) || (int)$editAd['is_active'] === 1 ? 'checked' : ''; ?>> Active</label>
        <button class="btn btn-primary" type="submit"><?php echo $editAd ? 'Update Ad' : 'Save Ad'; ?></button>
        <?php if ($editAd): ?><a class="btn btn-secondary" href="<?php echo ADMIN_BASE_URL; ?>/blog-ads.php">Cancel Edit</a><?php endif; ?>
    </form>
</div>

<div class="panel">
    <h3 style="margin-top:0;">Current Ads</h3>
    <div class="table-wrap">
        <table class="responsive-table">
            <thead><tr><th>ID</th><th>Placement</th><th>Type</th><th>Title</th><th>CTA</th><th>Priority</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($ads as $ad): ?>
                <tr>
                    <td data-label="ID"><?php echo (int) $ad['id']; ?></td>
                    <td data-label="Placement"><?php echo htmlspecialchars((string) $ad['placement'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Type"><?php echo htmlspecialchars((string) strtoupper((string) $ad['ad_type']), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Title"><?php echo htmlspecialchars((string) ($ad['title'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="CTA"><?php echo htmlspecialchars((string) ($ad['cta_text'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Priority"><?php echo (int) $ad['priority']; ?></td>
                    <td data-label="Status"><?php echo (int) $ad['is_active'] === 1 ? 'Active' : 'Inactive'; ?></td>
                    <td data-label="Action" style="display:flex;gap:6px;flex-wrap:wrap;">
                        <a class="btn btn-secondary" href="<?php echo ADMIN_BASE_URL; ?>/blog-ads.php?edit=<?php echo (int) $ad['id']; ?>">Edit</a>
                        <form method="post" onsubmit="return confirm('Delete this ad?');" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
                            <button class="btn" style="background:#7f1d1d;color:#fee2e2;border:1px solid #b91c1c;" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
