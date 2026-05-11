<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/devhub/pages/auth/login.php');
    exit();
}

$user_id = getCurrentUserId();
$db = getDevHubDB();
$is_verified = isVerifiedDeveloper($user_id);

$page_title = 'Edit Project';
$activePage = 'submit-project';

$project_id = (int) ($_GET['id'] ?? $_POST['project_id'] ?? 0);
$errors = [];
$success = false;
$success_message = '';
$toast_messages = [];

$esc = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$slugify = static function ($value) {
    $value = strtolower(trim((string) $value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string) $value, '-');
    return $value !== '' ? $value : 'project';
};

$project_stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND created_by = ? LIMIT 1");
$project_stmt->execute([$project_id, $user_id]);
$project = $project_stmt->fetch();

if (!$project) {
    http_response_code(404);
    require_once __DIR__ . '/../includes/header.php';
    echo '<link rel="stylesheet" href="' . BASE_URL . '/devhub/assets/css/submit-project.css">';
    echo '<div class="project-submit-wrapper"><div class="submit-notice error"><h3><i class="fas fa-ban"></i> Project Not Found</h3><p>This project does not exist or does not belong to your account.</p><a class="btn-primary" href="' . BASE_URL . '/devhub/index.php">Back to Dashboard</a></div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit();
}

$edit_state = getDeveloperProjectEditState($db, (int) $user_id, $project, 30);
$approval_status = (string) ($edit_state['approval_status'] ?? 'pending');
$cooldown_days = (int) ($edit_state['cooldown_days'] ?? 30);
$cooldown_end_ts = (int) ($edit_state['cooldown_end_ts'] ?? 0);
$cooldown_remaining = (int) ($edit_state['cooldown_remaining'] ?? 0);
$is_pending_review_state = (bool) ($edit_state['is_pending_review_state'] ?? false);
$is_cooldown_state = (bool) ($edit_state['is_cooldown_state'] ?? false);
$can_edit_now = (bool) ($edit_state['can_edit_now'] ?? false);

$form = [
    'description' => (string) ($project['description'] ?? ''),
    'category' => (string) ($project['category'] ?? ''),
    'slug' => (string) ($project['slug'] ?? ''),
    'website_url' => (string) ($project['website_url'] ?? ''),
    'twitter_url' => (string) ($project['twitter_url'] ?? ''),
    'telegram_url' => (string) ($project['telegram_url'] ?? ''),
    'discord_url' => (string) ($project['discord_url'] ?? ''),
    'github_url' => (string) ($project['github_url'] ?? ''),
    'network' => (string) ($project['network'] ?? ''),
    'contract_address' => (string) ($project['contract_address'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_verified && $can_edit_now) {
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? $default);
    }

    $form['slug'] = $slugify($form['slug']);

    if ($form['description'] === '') {
        $errors['description'] = 'Short description is required.';
    }
    if ($form['category'] === '') {
        $errors['category'] = 'Category is required.';
    }
    if ($form['slug'] === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $form['slug'])) {
        $errors['slug'] = 'Please provide a valid slug (lowercase letters, numbers, hyphens).';
    }
    if ($form['website_url'] === '') {
        $errors['website_url'] = 'Website URL is required.';
    } elseif (!filter_var($form['website_url'], FILTER_VALIDATE_URL)) {
        $errors['website_url'] = 'Please provide a valid website URL.';
    }

    $url_fields = ['twitter_url', 'telegram_url', 'discord_url', 'github_url'];
    foreach ($url_fields as $url_field) {
        if ($form[$url_field] !== '' && !filter_var($form[$url_field], FILTER_VALIDATE_URL)) {
            $errors[$url_field] = 'Please provide a valid URL.';
        }
    }

    if (empty($errors) && $form['slug'] !== strtolower((string) ($project['slug'] ?? ''))) {
        $slug_stmt = $db->prepare("SELECT id FROM projects WHERE LOWER(slug) = LOWER(?) AND id <> ? LIMIT 1");
        $slug_stmt->execute([$form['slug'], (int) $project['id']]);
        if ($slug_stmt->fetch()) {
            $errors['slug'] = 'This slug is already used by another project.';
        }
    }

    if (empty($errors) && $form['website_url'] !== '' && strtolower($form['website_url']) !== strtolower((string) ($project['website_url'] ?? ''))) {
        $stmt = $db->prepare("SELECT id FROM projects WHERE LOWER(website_url) = LOWER(?) AND id <> ? LIMIT 1");
        $stmt->execute([$form['website_url'], (int) $project['id']]);
        if ($stmt->fetch()) {
            $errors['website_url'] = 'This website URL is already used by another project.';
        }
    }

    if (empty($errors) && $form['contract_address'] !== '' && strtolower($form['contract_address']) !== strtolower((string) ($project['contract_address'] ?? ''))) {
        $stmt = $db->prepare("SELECT id FROM projects WHERE LOWER(contract_address) = LOWER(?) AND id <> ? LIMIT 1");
        $stmt->execute([$form['contract_address'], (int) $project['id']]);
        if ($stmt->fetch()) {
            $errors['contract_address'] = 'This contract address already exists.';
        }
    }

    $new_logo_web_path = null;
    if (empty($errors) && isset($_FILES['logo']) && (int) $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $logo_file = $_FILES['logo'];
        $tmp_name = $logo_file['tmp_name'];
        $original_name = $logo_file['name'] ?? '';
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['png', 'jpg', 'jpeg', 'webp'];
        $max_size = 4 * 1024 * 1024;

        if ((int) $logo_file['error'] !== UPLOAD_ERR_OK) {
            $errors['logo'] = 'Logo upload failed. Please try again.';
        } elseif (!in_array($extension, $allowed_extensions, true)) {
            $errors['logo'] = 'Allowed logo formats: PNG, JPG, JPEG, WEBP.';
        } elseif ((int) $logo_file['size'] > $max_size) {
            $errors['logo'] = 'Logo size must be 4MB or smaller.';
        } elseif (!@getimagesize($tmp_name)) {
            $errors['logo'] = 'Uploaded file is not a valid image.';
        }

        if (empty($errors)) {
            $logos_dir = __DIR__ . '/logos';
            if (!is_dir($logos_dir)) {
                mkdir($logos_dir, 0755, true);
            }

            $safe_file_name = $form['slug'] . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
            $logo_abs_path = $logos_dir . DIRECTORY_SEPARATOR . $safe_file_name;
            $new_logo_web_path = '/devhub/projects/logos/' . $safe_file_name;

            if (!move_uploaded_file($logo_file['tmp_name'], $logo_abs_path)) {
                $errors['logo'] = 'Could not save logo file. Please try again.';
                $new_logo_web_path = null;
            }
        }
    }

    if (empty($errors)) {
        try {
            $update_sql = "
                UPDATE projects
                SET
                    slug = ?,
                    logo = ?,
                    category = ?,
                    description = ?,
                    website_url = ?,
                    telegram_url = ?,
                    twitter_url = ?,
                    github_url = ?,
                    discord_url = ?,
                    network = ?,
                    contract_address = ?,
                    approval_status = 'pending',
                    updated_at = NOW()
                WHERE id = ? AND created_by = ?
                LIMIT 1
            ";
            $update = $db->prepare($update_sql);
            $update->execute([
                $form['slug'],
                $new_logo_web_path ?: (string) ($project['logo'] ?? ''),
                $form['category'],
                $form['description'],
                $form['website_url'],
                $form['telegram_url'] !== '' ? $form['telegram_url'] : null,
                $form['twitter_url'] !== '' ? $form['twitter_url'] : null,
                $form['github_url'] !== '' ? $form['github_url'] : null,
                $form['discord_url'] !== '' ? $form['discord_url'] : null,
                $form['network'] !== '' ? $form['network'] : null,
                $form['contract_address'] !== '' ? $form['contract_address'] : null,
                (int) $project['id'],
                $user_id,
            ]);

            $project_stmt->execute([$project_id, $user_id]);
            $project = $project_stmt->fetch();
            $form = [
                'description' => (string) ($project['description'] ?? ''),
                'category' => (string) ($project['category'] ?? ''),
                'slug' => (string) ($project['slug'] ?? ''),
                'website_url' => (string) ($project['website_url'] ?? ''),
                'twitter_url' => (string) ($project['twitter_url'] ?? ''),
                'telegram_url' => (string) ($project['telegram_url'] ?? ''),
                'discord_url' => (string) ($project['discord_url'] ?? ''),
                'github_url' => (string) ($project['github_url'] ?? ''),
                'network' => (string) ($project['network'] ?? ''),
                'contract_address' => (string) ($project['contract_address'] ?? ''),
            ];

            $success = true;
            $success_message = 'Project changes saved and submitted for admin approval.';
            $toast_messages[] = ['type' => 'success', 'text' => $success_message];
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        if (!empty($errors['general'])) {
            $toast_messages[] = ['type' => 'error', 'text' => (string) $errors['general']];
        } else {
            $toast_messages[] = ['type' => 'error', 'text' => 'Please fix highlighted fields and try again.'];
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
$project_preview_logo_url = coinrexNormalizeMediaUrl((string) ($project['logo'] ?? ''));
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/devhub/assets/css/submit-project.css">

<div class="project-submit-wrapper">
    <div class="project-submit-header">
        <h1><i class="fas fa-pen"></i> Edit Project</h1>
        <p>Edit allowed fields only. Changes require admin approval before going live.</p>
    </div>

    <?php if (!$is_verified): ?>
        <div class="submit-notice warning">
            <h3><i class="fas fa-shield-alt"></i> Verification Required</h3>
            <p>Your account must be verified before you can edit projects.</p>
            <a class="btn-secondary" href="<?php echo BASE_URL; ?>/devhub/apply.php">Complete Verification</a>
        </div>
    <?php else: ?>
        <?php if ($is_pending_review_state): ?>
            <div class="submit-notice" style="border-color: rgba(59,130,246,.45); background: linear-gradient(135deg, rgba(15,23,42,.98), rgba(30,41,59,.92));">
                <h3 style="color:#dbeafe;"><i class="fas fa-hourglass-half" style="color:#60a5fa;"></i> Edit Request Under Review</h3>
                <p style="color:#cbd5e1;">Your project update is currently in admin review for platform safety. Editing is temporarily locked until moderation is completed.</p>
                <div class="encourage-note" style="margin-top:12px; color:#bfdbfe; border-color: rgba(96,165,250,.45); background: rgba(30,64,175,.12);">
                    Current status: <strong style="color:#ffffff;"><?php echo $esc(strtoupper($approval_status)); ?></strong>
                </div>
            </div>
        <?php elseif ($is_cooldown_state): ?>
            <div class="submit-notice" style="border-color: rgba(212,175,55,.5); background: linear-gradient(135deg, rgba(15,23,42,.98), rgba(30,41,59,.92));">
                <h3 style="color:#f8fafc;"><i class="fas fa-lock" style="color:#facc15;"></i> Cooldown Active</h3>
                <p style="color:#e2e8f0;">This project was recently approved. To reduce admin validation burden, editing unlocks after <strong><?php echo (int) $cooldown_days; ?> days</strong>.</p>
                <div class="review-item full" style="margin-top:10px; color:#f8fafc; background: rgba(15,23,42,.75);">
                    <strong>Time Remaining:</strong>
                    <span id="cooldownCounter" data-end-ts="<?php echo (int) $cooldown_end_ts; ?>" style="color:#fcd34d; font-weight:700; letter-spacing:.3px;">Calculating...</span>
                </div>
            </div>
        <?php else: ?>
        <form class="wizard-form" method="POST" action="" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="project_id" value="<?php echo (int) $project_id; ?>">

            <div class="wizard-layout">
                <div class="wizard-main">
                    <div class="wizard-progress" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                        <div class="wizard-progress-track"></div>
                        <div class="wizard-progress-fill" style="width:100%;"></div>
                        <div class="wizard-stepper-item active"><span>1</span> Editable Fields</div>
                        <div class="wizard-stepper-item completed"><span>2</span> Locked Fields</div>
                    </div>

                    <div class="wizard-card" style="display:grid; gap: 24px;">
                        <section class="wizard-step active" style="display:block; opacity:1; transform:none;">
                            <h2>Editable Fields</h2>
                            <p>Update these fields anytime. Changes are sent to moderation before they go live.</p>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="description">Short Description <span class="req">*</span></label>
                                    <textarea id="description" name="description" rows="4"><?php echo $esc($form['description']); ?></textarea>
                                    <small class="field-error"><?php echo $esc($errors['description'] ?? ''); ?></small>
                                </div>
                                <div class="form-group">
                                    <label for="category">Category <span class="req">*</span></label>
                                    <select id="category" name="category">
                                        <option value="">Select category</option>
                                        <?php $categories = ['DeFi', 'Wallet', 'Exchange', 'Gaming', 'NFT', 'Infrastructure', 'Analytics', 'Other']; ?>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $esc($category); ?>" <?php echo $form['category'] === $category ? 'selected' : ''; ?>><?php echo $esc($category); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="field-error"><?php echo $esc($errors['category'] ?? ''); ?></small>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="slug">Slug <span class="req">*</span></label>
                                    <input type="text" id="slug" name="slug" value="<?php echo $esc($form['slug']); ?>" placeholder="project-slug">
                                    <small class="field-error"><?php echo $esc($errors['slug'] ?? ''); ?></small>
                                </div>
                                <div class="form-group">
                                    <label for="logo">Project Logo</label>
                                    <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.webp">
                                    <small class="hint">Optional. Upload only if you want to replace current logo.</small>
                                    <small class="field-error"><?php echo $esc($errors['logo'] ?? ''); ?></small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="website_url">Website URL <span class="req">*</span></label>
                                <input type="url" id="website_url" name="website_url" value="<?php echo $esc($form['website_url']); ?>">
                                <small class="field-error"><?php echo $esc($errors['website_url'] ?? ''); ?></small>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="twitter_url">Twitter URL</label>
                                    <input type="url" id="twitter_url" name="twitter_url" value="<?php echo $esc($form['twitter_url']); ?>">
                                    <small class="field-error"><?php echo $esc($errors['twitter_url'] ?? ''); ?></small>
                                </div>
                                <div class="form-group">
                                    <label for="telegram_url">Telegram URL</label>
                                    <input type="url" id="telegram_url" name="telegram_url" value="<?php echo $esc($form['telegram_url']); ?>">
                                    <small class="field-error"><?php echo $esc($errors['telegram_url'] ?? ''); ?></small>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="discord_url">Discord URL</label>
                                    <input type="url" id="discord_url" name="discord_url" value="<?php echo $esc($form['discord_url']); ?>">
                                    <small class="field-error"><?php echo $esc($errors['discord_url'] ?? ''); ?></small>
                                </div>
                                <div class="form-group">
                                    <label for="github_url">GitHub URL</label>
                                    <input type="url" id="github_url" name="github_url" value="<?php echo $esc($form['github_url']); ?>">
                                    <small class="field-error"><?php echo $esc($errors['github_url'] ?? ''); ?></small>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="network">Network</label>
                                    <select id="network" name="network">
                                        <option value="">Select network</option>
                                        <?php $networks = ['Ethereum', 'BNB Smart Chain', 'Solana', 'Polygon', 'Arbitrum', 'Optimism', 'Avalanche', 'Base', 'Other']; ?>
                                        <?php foreach ($networks as $network): ?>
                                            <option value="<?php echo $esc($network); ?>" <?php echo $form['network'] === $network ? 'selected' : ''; ?>><?php echo $esc($network); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="contract_address">Contract Address</label>
                                    <input type="text" id="contract_address" name="contract_address" value="<?php echo $esc($form['contract_address']); ?>">
                                    <small class="hint">You can update contract address when network changes.</small>
                                    <small class="field-error"><?php echo $esc($errors['contract_address'] ?? ''); ?></small>
                                </div>
                            </div>
                        </section>

                        <section>
                            <h2>Locked Fields (Read Only)</h2>
                            <div class="review-grid">
                                <div class="review-item"><strong>Project Live Date:</strong> <?php echo $esc((string) ($project['project_live_since'] ?? '-')); ?></div>
                                <div class="review-item"><strong>Min Holding:</strong> <?php echo $esc((string) ($project['min_holding_amount'] ?? '-')); ?></div>
                                <div class="review-item"><strong>Max Reward (REX):</strong> <?php echo $esc((string) ($project['max_reward_rex'] ?? '-')); ?></div>
                                <div class="review-item"><strong>Holding Days:</strong> <?php echo $esc((string) ($project['required_holding_days'] ?? '-')); ?></div>
                            </div>
                            <div class="encourage-note soft-warning" style="margin-top:12px;">
                                Tokenomics and live-date are locked for platform security and cannot be changed by developers.
                            </div>
                        </section>

                        <div class="wizard-actions" style="justify-content: flex-start;">
                            <a class="btn-ghost" href="<?php echo BASE_URL; ?>/devhub/index.php">Cancel</a>
                            <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes (Send for Admin Approval)</button>
                        </div>
                    </div>
                </div>

                <aside class="wizard-preview">
                    <h3>Live Preview</h3>
                    <div class="preview-logo-wrap"><img id="logoPreview" src="<?php echo $esc($project_preview_logo_url !== '' ? $project_preview_logo_url : (BASE_URL . '/assets/images/favicon.png')); ?>" alt="Project Logo Preview"></div>
                    <div class="preview-card">
                        <div class="preview-card-head">
                            <h4 id="previewSlug"><?php echo $esc($form['slug']); ?></h4>
                            <span id="previewStatus"><?php echo $esc((string) ($project['approval_status'] ?? 'pending')); ?></span>
                        </div>
                        <p id="previewDescription"><?php echo $esc($form['description']); ?></p>
                        <ul>
                            <li><i class="fas fa-link"></i> <span id="previewWebsite"><?php echo $esc($form['website_url'] ?: 'Website not set'); ?></span></li>
                            <li><i class="fas fa-cube"></i> <span id="previewNetwork"><?php echo $esc($form['network'] ?: 'Network not set'); ?></span></li>
                            <li><i class="fas fa-hashtag"></i> <span id="previewContract"><?php echo $esc($form['contract_address'] ?: 'Contract not set'); ?></span></li>
                        </ul>
                    </div>
                </aside>
            </div>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!empty($toast_messages)): ?>
    <div class="dh-toast-stack" id="dhToastStack">
        <?php foreach ($toast_messages as $toast): ?>
            <div class="dh-toast <?php echo $toast['type'] === 'success' ? 'success' : 'error'; ?>" role="alert" aria-live="polite">
                <span class="dh-toast-icon"><i class="fas <?php echo $toast['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i></span>
                <span class="dh-toast-text"><?php echo $esc($toast['text']); ?></span>
                <button type="button" class="dh-toast-close" aria-label="Close">&times;</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<style>
.dh-toast-stack { position: fixed; top: 18px; right: 18px; z-index: 1200; display: grid; gap: 10px; width: min(420px, calc(100vw - 24px)); }
.dh-toast { display:flex; align-items:flex-start; gap:10px; padding:12px 14px; border-radius:14px; border:1px solid rgba(255,255,255,.08); background:#0f172a; color:#e2e8f0; box-shadow:0 10px 30px rgba(2,6,23,.35); animation:toastIn .2s ease; }
.dh-toast.success { border-color: rgba(29,78,216,.45); }
.dh-toast.error { border-color: rgba(239,68,68,.45); }
.dh-toast-icon { margin-top:1px; }
.dh-toast.success .dh-toast-icon { color:#60a5fa; }
.dh-toast.error .dh-toast-icon { color:#f87171; }
.dh-toast-text { flex:1; font-size:13px; line-height:1.35; }
.dh-toast-close { border:0; background:transparent; color:#94a3b8; cursor:pointer; font-size:18px; line-height:1; padding:0; }
@keyframes toastIn { from { opacity:0; transform:translateY(-6px);} to { opacity:1; transform:translateY(0);} }
</style>

<script>
(function () {
    var stack = document.getElementById('dhToastStack');

    function dismiss(node) {
        if (!node) return;
        node.style.opacity = '0';
        node.style.transform = 'translateY(-4px)';
        setTimeout(function () { if (node && node.parentNode) node.parentNode.removeChild(node); }, 180);
    }

    if (stack) {
        stack.querySelectorAll('.dh-toast').forEach(function (toast, index) {
            var close = toast.querySelector('.dh-toast-close');
            if (close) close.addEventListener('click', function () { dismiss(toast); });
            setTimeout(function () { dismiss(toast); }, 5200 + (index * 500));
        });
    }

    var bindPreview = function (id, targetId, fallback) {
        var input = document.getElementById(id);
        var target = document.getElementById(targetId);
        if (!input || !target) return;
        var update = function () {
            var value = (input.value || '').trim();
            target.textContent = value || fallback;
        };
        input.addEventListener('input', update);
        input.addEventListener('change', update);
    };

    bindPreview('slug', 'previewSlug', 'project-slug');
    bindPreview('description', 'previewDescription', 'Project summary appears here.');
    bindPreview('website_url', 'previewWebsite', 'Website not set');
    bindPreview('network', 'previewNetwork', 'Network not set');
    bindPreview('contract_address', 'previewContract', 'Contract not set');

    var logoInput = document.getElementById('logo');
    var logoPreview = document.getElementById('logoPreview');
    if (logoInput && logoPreview) {
        logoInput.addEventListener('change', function () {
            var file = logoInput.files && logoInput.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function (e) { logoPreview.src = e.target.result; };
            reader.readAsDataURL(file);
        });
    }

    var counter = document.getElementById('cooldownCounter');
    if (counter) {
        var endTs = Number(counter.getAttribute('data-end-ts') || 0) * 1000;
        var tick = function () {
            var diff = Math.max(0, endTs - Date.now());
            if (diff <= 0) {
                counter.textContent = 'Cooldown complete. Please refresh to edit.';
                return;
            }
            var totalSec = Math.floor(diff / 1000);
            var days = Math.floor(totalSec / 86400);
            var hours = Math.floor((totalSec % 86400) / 3600);
            var mins = Math.floor((totalSec % 3600) / 60);
            var secs = totalSec % 60;
            counter.textContent = days + 'd ' + hours + 'h ' + mins + 'm ' + secs + 's';
            requestAnimationFrame(function () { setTimeout(tick, 1000); });
        };
        tick();
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
