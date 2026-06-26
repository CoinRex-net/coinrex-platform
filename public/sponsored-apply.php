<?php
/**
 * CoinRex Sponsored Project Application
 * Token-gated page for developers to submit sponsored project applications.
 * Access via: /sponsored-apply.php?token=XXXX
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

$db = getDBConnection();
$errors = [];
$success = false;
$success_message = '';
$edit_mode = false;
$project_data = null;

// Validate token
$token = trim($_GET['token'] ?? '');
if ($token === '') {
    $errors[] = 'No application token provided.';
    $token_valid = false;
} else {
    $token_row = validateSponsoredToken($db, $token);
    if (!$token_row) {
        $errors[] = 'This application link is invalid or has expired. Please contact the administrator for a new link.';
        $token_valid = false;
    } else {
        $token_valid = true;

        // Check if this is an edit (token used but project still pending)
        if ((int) ($token_row['used'] ?? 0) === 1 && !empty($token_row['project_id'])) {
            $edit_mode = true;
            // Load existing project data
            $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
            $stmt->execute([$token_row['project_id']]);
            $project_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$project_data) {
                $errors[] = 'The linked project could not be found.';
                $token_valid = false;
            }
        }
    }
}

// Form defaults
$form = [
    'name' => '',
    'slug' => '',
    'category' => '',
    'description' => '',
    'website_url' => '',
    'twitter_url' => '',
    'telegram_url' => '',
    'discord_url' => '',
    'github_url' => '',
    'contract_address' => '',
    'network' => '',
    'project_live_since' => '',
    'status' => 'upcoming',
    'min_holding_amount' => '',
    'max_reward_rex' => '',
    'required_holding_days' => ''
];

// Pre-fill form if editing
if ($edit_mode && $project_data) {
    foreach ($form as $key => $default) {
        $form[$key] = $project_data[$key] ?? $default;
    }
}

$esc = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$slugify = static function ($value) {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');
    return $value !== '' ? $value : 'project';
};

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid && isset($_POST['action']) && $_POST['action'] === 'submit_sponsored') {
    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? $default);
    }

    $form['slug'] = $slugify($form['name']);
    $form['status'] = in_array($form['status'], ['upcoming', 'active', 'maintenance', 'paused'], true) ? $form['status'] : 'upcoming';

    // Validation
    if ($form['name'] === '') {
        $errors['name'] = 'Project name is required.';
    }
    if ($form['slug'] === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $form['slug'])) {
        $errors['name'] = 'Please provide a valid project name to generate slug.';
    }
    if ($form['category'] === '') {
        $errors['category'] = 'Category is required.';
    }
    if ($form['description'] === '') {
        $errors['description'] = 'Short description is required.';
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

    if ($form['project_live_since'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['project_live_since'])) {
        $errors['project_live_since'] = 'Please provide a valid date.';
    }

    if ($form['min_holding_amount'] !== '' && !is_numeric($form['min_holding_amount'])) {
        $errors['min_holding_amount'] = 'Minimum holding amount must be numeric.';
    }
    if ($form['max_reward_rex'] !== '' && !is_numeric($form['max_reward_rex'])) {
        $errors['max_reward_rex'] = 'Maximum reward must be numeric.';
    }
    if ($form['required_holding_days'] !== '' && !ctype_digit($form['required_holding_days'])) {
        $errors['required_holding_days'] = 'Required holding days must be a whole number.';
    }

    // Logo upload (required for new, optional for edit)
    $uploaded_logo_web_path = '';
    $has_new_logo = isset($_FILES['logo']) && (int)$_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE;

    if (!$edit_mode && !$has_new_logo) {
        $errors['logo'] = 'Project logo is required.';
    }

    // Check uniqueness constraints (only for new projects)
    if (empty($errors) && !$edit_mode) {
        if ($form['contract_address'] !== '') {
            $stmt = $db->prepare("SELECT id FROM projects WHERE LOWER(contract_address) = LOWER(?) LIMIT 1");
            $stmt->execute([$form['contract_address']]);
            if ($stmt->fetch()) {
                $errors['contract_address'] = 'This contract address already exists.';
            }
        }

        $stmt = $db->prepare("SELECT id FROM projects WHERE LOWER(website_url) = LOWER(?) LIMIT 1");
        $stmt->execute([$form['website_url']]);
        if ($stmt->fetch()) {
            $errors['website_url'] = 'This website URL is already used by another project.';
        }
    }

    // Handle logo upload
    if ($has_new_logo && empty($errors)) {
        $logo_file = $_FILES['logo'];
        $tmp_name = $logo_file['tmp_name'];
        $original_name = $logo_file['name'] ?? '';
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['png', 'jpg', 'jpeg', 'webp'];
        $max_size = 4 * 1024 * 1024;

        if ((int)$logo_file['error'] !== UPLOAD_ERR_OK) {
            $errors['logo'] = 'Logo upload failed. Please try again.';
        } elseif (!in_array($extension, $allowed_extensions, true)) {
            $errors['logo'] = 'Allowed logo formats: PNG, JPG, JPEG, WEBP.';
        } elseif ((int)$logo_file['size'] > $max_size) {
            $errors['logo'] = 'Logo size must be 4MB or smaller.';
        } elseif (!@getimagesize($tmp_name)) {
            $errors['logo'] = 'Uploaded file is not a valid image.';
        } else {
            $logos_dir = __DIR__ . '/assets/uploads/projects';
            if (!is_dir($logos_dir)) {
                mkdir($logos_dir, 0755, true);
            }

            $safe_file_name = $form['slug'] . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
            $logo_abs_path = $logos_dir . DIRECTORY_SEPARATOR . $safe_file_name;
            $uploaded_logo_web_path = '/assets/uploads/projects/' . $safe_file_name;

            if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logo_abs_path)) {
                $errors['logo'] = 'Could not save logo file. Please try again.';
            }
        }
    }

    // Save to database
    if (empty($errors)) {
        try {
            if ($edit_mode && $project_data) {
                // Update existing project
                $update_sql = "
                    UPDATE projects SET
                        name = ?, slug = ?, category = ?, description = ?, website_url = ?,
                        telegram_url = ?, twitter_url = ?, contract_address = ?, github_url = ?, discord_url = ?,
                        network = ?, project_live_since = ?, status = ?, min_holding_amount = ?, max_reward_rex = ?,
                        required_holding_days = ?, updated_at = NOW()
                ";
                $update_params = [
                    $form['name'], $form['slug'], $form['category'], $form['description'], $form['website_url'],
                    $form['telegram_url'] !== '' ? $form['telegram_url'] : null,
                    $form['twitter_url'] !== '' ? $form['twitter_url'] : null,
                    $form['contract_address'] !== '' ? $form['contract_address'] : null,
                    $form['github_url'] !== '' ? $form['github_url'] : null,
                    $form['discord_url'] !== '' ? $form['discord_url'] : null,
                    $form['network'] !== '' ? $form['network'] : null,
                    $form['project_live_since'] !== '' ? $form['project_live_since'] : null,
                    $form['status'],
                    $form['min_holding_amount'] !== '' ? $form['min_holding_amount'] : null,
                    $form['max_reward_rex'] !== '' ? $form['max_reward_rex'] : null,
                    $form['required_holding_days'] !== '' ? $form['required_holding_days'] : null,
                ];

                // Add logo update if new logo uploaded
                if ($uploaded_logo_web_path !== '') {
                    $update_sql = str_replace('updated_at = NOW()', 'logo = ?, updated_at = NOW()', $update_sql);
                    array_splice($update_params, 0, 0, [$uploaded_logo_web_path]);
                }

                $update_sql .= " WHERE id = ?";
                $update_params[] = $project_data['id'];

                $stmt = $db->prepare($update_sql);
                $stmt->execute($update_params);

                $success = true;
                $success_message = 'Your sponsored project application has been updated successfully! The admin will review your changes.';
            } else {
                // Insert new project
                $insert = $db->prepare("
                    INSERT INTO projects (
                        name, slug, logo, category, description, website_url,
                        telegram_url, twitter_url, contract_address, github_url, discord_url,
                        network, project_live_since, status, min_holding_amount, max_reward_rex,
                        required_holding_days, approval_status, sponsored_status, sponsored_requested_at,
                        created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, 'pending', 'requested', NOW(),
                        NOW(), NOW()
                    )
                ");

                $insert->execute([
                    $form['name'],
                    $form['slug'],
                    $uploaded_logo_web_path,
                    $form['category'],
                    $form['description'],
                    $form['website_url'],
                    $form['telegram_url'] !== '' ? $form['telegram_url'] : null,
                    $form['twitter_url'] !== '' ? $form['twitter_url'] : null,
                    $form['contract_address'] !== '' ? $form['contract_address'] : null,
                    $form['github_url'] !== '' ? $form['github_url'] : null,
                    $form['discord_url'] !== '' ? $form['discord_url'] : null,
                    $form['network'] !== '' ? $form['network'] : null,
                    $form['project_live_since'] !== '' ? $form['project_live_since'] : null,
                    $form['status'],
                    $form['min_holding_amount'] !== '' ? $form['min_holding_amount'] : null,
                    $form['max_reward_rex'] !== '' ? $form['max_reward_rex'] : null,
                    $form['required_holding_days'] !== '' ? $form['required_holding_days'] : null,
                ]);

                $project_id = $db->lastInsertId();

                // Mark token as used
                markSponsoredTokenUsed($db, $token, $project_id);

                $success = true;
                $success_message = 'Your sponsored project application has been submitted successfully! The admin will review it shortly.';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/sponsored-apply.css">

<div class="sponsored-apply-wrapper">
    <div class="sponsored-apply-header">
        <h1><i class="fas fa-bullhorn"></i> Sponsored Project Application</h1>
        <p>Submit your project for a sponsored listing on CoinRex. All fields marked with <span class="req">*</span> are required.</p>
    </div>

    <?php if (!$token_valid): ?>
        <div class="sponsored-notice error">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <h3>Invalid or Expired Link</h3>
                <p><?php echo implode('</p><p>', array_map($esc, $errors)); ?></p>
                <p>Please contact the CoinRex team for a new application link.</p>
            </div>
        </div>

    <?php elseif ($success): ?>
        <div class="sponsored-notice success">
            <i class="fas fa-check-circle"></i>
            <div>
                <h3>Submission Received</h3>
                <p><?php echo $esc($success_message); ?></p>
                <p>You can close this page now. If you need to make changes later, the admin can provide you with a new edit link.</p>
            </div>
        </div>

    <?php else: ?>
        <?php if ($edit_mode): ?>
            <div class="sponsored-notice info">
                <i class="fas fa-pen"></i>
                <div>
                    <h3>Editing Your Application</h3>
                    <p>You can update your project details below. Your changes will be reviewed by the admin.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['general'])): ?>
            <div class="sponsored-notice error">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <p><?php echo $esc($errors['general']); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <form class="sponsored-form" method="POST" action="?token=<?php echo $esc($token); ?>" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="action" value="submit_sponsored">

            <!-- Section 1: Basic Info -->
            <div class="sponsored-section">
                <h2><i class="fas fa-info-circle"></i> Basic Information</h2>
                <div class="sponsored-grid">
                    <div class="sponsored-field">
                        <label for="name">Project Name <span class="req">*</span></label>
                        <input type="text" id="name" name="name" value="<?php echo $esc($form['name']); ?>" placeholder="CoinRex Wallet" required>
                        <small class="field-error"><?php echo $esc($errors['name'] ?? ''); ?></small>
                    </div>
                    <div class="sponsored-field">
                        <label for="category">Category <span class="req">*</span></label>
                        <select id="category" name="category" required>
                            <option value="">Select category</option>
                            <?php
                            $categories = ['DeFi', 'Wallet', 'Exchange', 'Gaming', 'NFT', 'Infrastructure', 'Analytics', 'Other'];
                            foreach ($categories as $category):
                            ?>
                                <option value="<?php echo $esc($category); ?>" <?php echo $form['category'] === $category ? 'selected' : ''; ?>>
                                    <?php echo $esc($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-error"><?php echo $esc($errors['category'] ?? ''); ?></small>
                    </div>
                </div>

                <div class="sponsored-field">
                    <label for="description">Short Description <span class="req">*</span></label>
                    <textarea id="description" name="description" rows="4" placeholder="Briefly describe your project..." required><?php echo $esc($form['description']); ?></textarea>
                    <small class="field-error"><?php echo $esc($errors['description'] ?? ''); ?></small>
                </div>

                <div class="sponsored-field">
                    <label for="logo">Project Logo <?php echo $edit_mode ? '' : '<span class="req">*</span>'; ?></label>
                    <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.webp" <?php echo $edit_mode ? '' : 'required'; ?>>
                    <small class="hint">PNG/JPG/WEBP, max 4MB. <?php echo $edit_mode ? 'Leave empty to keep existing logo.' : ''; ?></small>
                    <small class="field-error"><?php echo $esc($errors['logo'] ?? ''); ?></small>
                </div>
            </div>

            <!-- Section 2: Links -->
            <div class="sponsored-section">
                <h2><i class="fas fa-link"></i> Links & Social Presence</h2>

                <div class="sponsored-field">
                    <label for="website_url">Website URL <span class="req">*</span></label>
                    <input type="url" id="website_url" name="website_url" value="<?php echo $esc($form['website_url']); ?>" placeholder="https://yourproject.com" required>
                    <small class="field-error"><?php echo $esc($errors['website_url'] ?? ''); ?></small>
                </div>

                <div class="sponsored-grid">
                    <div class="sponsored-field">
                        <label for="twitter_url">Twitter / X URL</label>
                        <input type="url" id="twitter_url" name="twitter_url" value="<?php echo $esc($form['twitter_url']); ?>" placeholder="https://x.com/yourproject">
                        <small class="field-error"><?php echo $esc($errors['twitter_url'] ?? ''); ?></small>
                    </div>
                    <div class="sponsored-field">
                        <label for="telegram_url">Telegram URL</label>
                        <input type="url" id="telegram_url" name="telegram_url" value="<?php echo $esc($form['telegram_url']); ?>" placeholder="https://t.me/yourproject">
                        <small class="field-error"><?php echo $esc($errors['telegram_url'] ?? ''); ?></small>
                    </div>
                </div>

                <div class="sponsored-grid">
                    <div class="sponsored-field">
                        <label for="discord_url">Discord URL</label>
                        <input type="url" id="discord_url" name="discord_url" value="<?php echo $esc($form['discord_url']); ?>" placeholder="https://discord.gg/yourproject">
                        <small class="field-error"><?php echo $esc($errors['discord_url'] ?? ''); ?></small>
                    </div>
                    <div class="sponsored-field">
                        <label for="github_url">GitHub URL</label>
                        <input type="url" id="github_url" name="github_url" value="<?php echo $esc($form['github_url']); ?>" placeholder="https://github.com/org/repo">
                        <small class="field-error"><?php echo $esc($errors['github_url'] ?? ''); ?></small>
                    </div>
                </div>
            </div>

            <!-- Section 3: Technical -->
            <div class="sponsored-section">
                <h2><i class="fas fa-microchip"></i> Technical Details</h2>

                <div class="sponsored-field">
                    <label for="contract_address">Contract Address</label>
                    <input type="text" id="contract_address" name="contract_address" value="<?php echo $esc($form['contract_address']); ?>" placeholder="0x...">
                    <small class="hint">Leave blank for native coins that don't use token contracts.</small>
                    <small class="field-error"><?php echo $esc($errors['contract_address'] ?? ''); ?></small>
                </div>

                <div class="sponsored-grid">
                    <div class="sponsored-field">
                        <label for="network">Network</label>
                        <select id="network" name="network">
                            <option value="">Select network</option>
                            <?php
                            $networks = ['Ethereum', 'BNB Smart Chain', 'Solana', 'Polygon', 'Arbitrum', 'Optimism', 'Avalanche', 'Base', 'Other'];
                            foreach ($networks as $network):
                            ?>
                                <option value="<?php echo $esc($network); ?>" <?php echo $form['network'] === $network ? 'selected' : ''; ?>>
                                    <?php echo $esc($network); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="field-error"></small>
                    </div>
                    <div class="sponsored-field">
                        <label for="project_live_since">Project Live Since</label>
                        <input type="date" id="project_live_since" name="project_live_since" value="<?php echo $esc($form['project_live_since']); ?>">
                        <small class="field-error"><?php echo $esc($errors['project_live_since'] ?? ''); ?></small>
                    </div>
                </div>
            </div>

            <!-- Section 4: Tokenomics -->
            <div class="sponsored-section">
                <h2><i class="fas fa-chart-pie"></i> Tokenomics & Requirements</h2>
                <p class="section-hint">These fields help reviewers understand your project's tokenomics. All are optional but recommended.</p>

                <div class="sponsored-grid">
                    <div class="sponsored-field">
                        <label for="min_holding_amount">Minimum Holding Amount</label>
                        <input type="text" id="min_holding_amount" name="min_holding_amount" value="<?php echo $esc($form['min_holding_amount']); ?>" placeholder="e.g. 100">
                        <small class="field-error"><?php echo $esc($errors['min_holding_amount'] ?? ''); ?></small>
                    </div>
                    <div class="sponsored-field">
                        <label for="max_reward_rex">Maximum Reward ($REX)</label>
                        <input type="text" id="max_reward_rex" name="max_reward_rex" value="<?php echo $esc($form['max_reward_rex']); ?>" placeholder="e.g. 5000">
                        <small class="field-error"><?php echo $esc($errors['max_reward_rex'] ?? ''); ?></small>
                    </div>
                </div>

                <div class="sponsored-grid">
                    <div class="sponsored-field">
                        <label for="required_holding_days">Required Holding Days</label>
                        <input type="text" id="required_holding_days" name="required_holding_days" value="<?php echo $esc($form['required_holding_days']); ?>" placeholder="e.g. 30">
                        <small class="field-error"><?php echo $esc($errors['required_holding_days'] ?? ''); ?></small>
                    </div>
                    <div class="sponsored-field">
                        <label for="status">Project Status</label>
                        <select id="status" name="status">
                            <option value="upcoming" <?php echo $form['status'] === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                            <option value="active" <?php echo $form['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="maintenance" <?php echo $form['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="paused" <?php echo $form['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="sponsored-actions">
                <button type="submit" class="btn-sponsored-submit">
                    <i class="fas fa-paper-plane"></i> <?php echo $edit_mode ? 'Update Application' : 'Submit Application'; ?>
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
(function() {
    'use strict';

    const form = document.querySelector('.sponsored-form');
    if (!form) return;

    const nameInput = document.getElementById('name');
    const slugInput = document.createElement('input');
    slugInput.type = 'hidden';
    slugInput.name = 'slug';
    form.appendChild(slugInput);

    function slugify(value) {
        return (value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }

    if (nameInput) {
        nameInput.addEventListener('input', function() {
            slugInput.value = slugify(nameInput.value);
        });
        // Set initial slug
        slugInput.value = slugify(nameInput.value);
    }

    // Client-side validation styling
    form.addEventListener('submit', function(e) {
        const required = form.querySelectorAll('[required]');
        let valid = true;
        required.forEach(function(field) {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                valid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        if (!valid) {
            e.preventDefault();
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) firstInvalid.focus();
        }
    });

    // Clear invalid state on input
    form.querySelectorAll('input, select, textarea').forEach(function(field) {
        field.addEventListener('input', function() {
            field.classList.remove('is-invalid');
        });
        field.addEventListener('change', function() {
            field.classList.remove('is-invalid');
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
