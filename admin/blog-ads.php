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

$total_ads = count($ads);
$active_ads = 0;
foreach ($ads as $a) {
    if ((int) ($a['is_active'] ?? 0) === 1) $active_ads++;
}
?>
<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-ad"></i></div>
            <div class="dashboard-header-text">
                <h1>Blog Ads Manager</h1>
                <p>Create and manage blog advertisements</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-database"></i> <?php echo number_format($total_ads); ?> ads
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div data-toast data-toast-message="<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>" data-toast-type="<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" style="display:none;"></div>
    <?php endif; ?>
    <?php if (!$hasCtaText): ?>
        <div data-toast data-toast-message="CTA column missing in blog_ads. Run: ALTER TABLE blog_ads ADD COLUMN cta_text VARCHAR(80) NULL AFTER target_url;" data-toast-type="error" style="display:none;"></div>
    <?php endif; ?>

    <!-- ====== SECTION 1: OVERVIEW ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-chart-bar"></i> Overview <span class="divider-sub">Ad management metrics</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-ad"></i> Ads</span>
                <h3>Ad Management</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Create, edit, and manage blog advertisements across placements.</p>
            </div>
            <a href="<?php echo ADMIN_BASE_URL; ?>/blog.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Blog</a>
        </div>
        <div class="dashboard-metric-grid">
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-blue"><i class="fas fa-ad"></i></div></div>
                <span class="metric-value"><?php echo number_format($total_ads); ?></span>
                <span class="metric-label">Total Ads</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-green"><i class="fas fa-check-circle"></i></div></div>
                <span class="metric-value"><?php echo number_format($active_ads); ?></span>
                <span class="metric-label">Active</span>
            </div>
        </div>
    </div>

    <!-- ====== SECTION 2: AD FORM ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-<?php echo $editAd ? 'edit' : 'plus-circle'; ?>"></i> <?php echo $editAd ? 'Edit Ad #' . (int) $editAd['id'] : 'Create Blog Ad'; ?> <span class="divider-sub">Ad details and targeting</span></h2>
    </div>

    <div class="dashboard-panel" style="margin-bottom:16px;">
        <form method="post" enctype="multipart/form-data" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo (int) ($editAd['id'] ?? 0); ?>">

            <!-- Placement & Targeting -->
            <div style="margin-bottom:20px;">
                <h4 style="margin:0 0 12px;color:#f1f5f9;font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-bullseye" style="color:#d4af37;font-size:14px;"></i> Placement & Targeting
                </h4>
                <div class="dashboard-filter-form" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">Placement</label>
                        <select name="placement" required>
                            <option value="blog_leaderboard" <?php echo (($editAd['placement'] ?? '') === 'blog_leaderboard') ? 'selected' : ''; ?>>Leaderboard</option>
                            <option value="blog_infeed" <?php echo (($editAd['placement'] ?? '') === 'blog_infeed') ? 'selected' : ''; ?>>In-feed</option>
                            <option value="blog_sidebar" <?php echo (($editAd['placement'] ?? '') === 'blog_sidebar') ? 'selected' : ''; ?>>Sidebar</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">Ad Type</label>
                        <select name="ad_type" required>
                            <option value="text" <?php echo (($editAd['ad_type'] ?? '') === 'text') ? 'selected' : ''; ?>>Text</option>
                            <option value="image" <?php echo (($editAd['ad_type'] ?? '') === 'image') ? 'selected' : ''; ?>>Image</option>
                            <option value="gif" <?php echo (($editAd['ad_type'] ?? '') === 'gif') ? 'selected' : ''; ?>>GIF</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">Priority <span style="color:#64748b;font-weight:400;">(lower = first)</span></label>
                        <input type="number" name="priority" placeholder="e.g. 100" value="<?php echo (int) ($editAd['priority'] ?? 100); ?>">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">After Post # <span style="color:#64748b;font-weight:400;">(in-feed only)</span></label>
                        <input type="number" name="after_post" placeholder="e.g. 3" value="<?php echo (int) ($editAd['after_post'] ?? 3); ?>" min="1" max="20">
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div style="margin-bottom:20px;">
                <h4 style="margin:0 0 12px;color:#f1f5f9;font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-file-alt" style="color:#d4af37;font-size:14px;"></i> Content
                </h4>
                <div class="dashboard-filter-form" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">Title</label>
                        <input type="text" name="title" placeholder="Ad title" value="<?php echo htmlspecialchars((string) ($editAd['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">Description</label>
                        <input type="text" name="description" placeholder="Ad description" value="<?php echo htmlspecialchars((string) ($editAd['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">CTA Text <span style="color:#64748b;font-weight:400;">(for text ads)</span></label>
                        <input type="text" name="cta_text" placeholder="e.g. Learn More" value="<?php echo htmlspecialchars((string) ($editAd['cta_text'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">Media URL</label>
                        <input type="url" name="media_url" placeholder="https://example.com/ad.png" value="<?php echo htmlspecialchars((string) ($editAd['media_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">Upload File</label>
                        <input type="file" name="media_file" accept="image/png,image/jpeg,image/webp,image/gif">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">Target URL</label>
                        <input type="url" name="target_url" placeholder="https://example.com" value="<?php echo htmlspecialchars((string) ($editAd['target_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
            </div>

            <!-- Schedule & Status -->
            <div style="margin-bottom:20px;">
                <h4 style="margin:0 0 12px;color:#f1f5f9;font-size:14px;font-weight:700;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-calendar-alt" style="color:#d4af37;font-size:14px;"></i> Schedule & Status
                </h4>
                <div class="dashboard-filter-form" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;">
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">Starts At</label>
                        <input type="datetime-local" name="starts_at" value="<?php echo !empty($editAd['starts_at']) ? htmlspecialchars(str_replace(' ', 'T', substr((string)$editAd['starts_at'], 0, 16)), ENT_QUOTES, 'UTF-8') : ''; ?>">
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;font-weight:600;">Ends At</label>
                        <input type="datetime-local" name="ends_at" value="<?php echo !empty($editAd['ends_at']) ? htmlspecialchars(str_replace(' ', 'T', substr((string)$editAd['ends_at'], 0, 16)), ENT_QUOTES, 'UTF-8') : ''; ?>">
                    </div>
                    <div style="display:flex;align-items:flex-end;padding-bottom:4px;">
                        <label style="display:flex;align-items:center;gap:8px;color:#f1f5f9;cursor:pointer;">
                            <input type="checkbox" name="is_active" <?php echo !isset($editAd['is_active']) || (int)$editAd['is_active'] === 1 ? 'checked' : ''; ?>>
                            <span style="font-size:13px;font-weight:600;">Active</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div style="display:flex;gap:8px;padding-top:12px;border-top:1px solid rgba(148,163,184,0.15);">
                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-<?php echo $editAd ? 'save' : 'plus'; ?>"></i> <?php echo $editAd ? 'Update Ad' : 'Save Ad'; ?>
                </button>
                <?php if ($editAd): ?>
                    <a class="btn btn-secondary" href="<?php echo ADMIN_BASE_URL; ?>/blog-ads.php"><i class="fas fa-times"></i> Cancel Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ====== SECTION 3: ADS TABLE ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-list"></i> Current Ads <span class="divider-sub">All blog advertisements</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Placement</th>
                    <th>Type</th>
                    <th>Title</th>
                    <th>CTA</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($ads)): ?>
                    <tr><td colspan="8" class="muted" style="text-align:center;padding:30px;color:#64748b;">No ads found.</td></tr>
                <?php else: ?>
                    <?php foreach ($ads as $ad): ?>
                        <tr>
                            <td data-label="ID"><?php echo (int) $ad['id']; ?></td>
                            <td data-label="Placement"><?php echo htmlspecialchars((string) $ad['placement'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Type"><?php echo htmlspecialchars((string) strtoupper((string) $ad['ad_type']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Title"><?php echo htmlspecialchars((string) ($ad['title'] ?: '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="CTA"><?php echo htmlspecialchars((string) ($ad['cta_text'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Priority"><?php echo (int) $ad['priority']; ?></td>
                            <td data-label="Status">
                                <span class="dashboard-pill <?php echo (int) $ad['is_active'] === 1 ? 'is-active' : 'is-suspended'; ?>">
                                    <?php echo (int) $ad['is_active'] === 1 ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td data-label="Action">
                                <div class="action-stack" style="flex-direction:row;flex-wrap:wrap;">
                                    <a class="btn btn-secondary action-stack-btn" href="<?php echo ADMIN_BASE_URL; ?>/blog-ads.php?edit=<?php echo (int) $ad['id']; ?>"><i class="fas fa-edit"></i> Edit</a>
                                    <form method="post" class="delete-ad-form" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
                                        <button class="btn action-stack-btn" type="submit" style="background:#7f1d1d;color:#fee2e2;border:1px solid #b91c1c;"><i class="fas fa-trash"></i> Delete</button>
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
                <span class="modal-kicker"><i class="fas fa-trash"></i> Delete Ad</span>
                <h3>Delete Blog Ad?</h3>
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

  document.querySelectorAll('.delete-ad-form').forEach(function (form) {
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
