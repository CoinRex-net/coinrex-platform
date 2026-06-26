<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/devhub/pages/auth/login.php');
    exit();
}

$user_id = getCurrentUserId();
$db = getDevHubDB();
ensureReviewEligibilitySchema($db);
$is_verified = isVerifiedDeveloper($user_id);

$page_title = 'Submit Project';
$activePage = 'submit-project';

$errors = [];
$success = false;
$success_message = '';
$uploaded_logo_web_path = '';
$recent_project_updates = [];
$contract_rows = [];
$max_projects_per_developer = 3;
$developer_project_count = 0;
$developer_pending_project = null;
$submission_block_reason = '';

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

$esc = static function ($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
};

$slugify = static function ($value) {
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');
    return $value !== '' ? $value : 'project';
};

$updates_stmt = $db->prepare("
    SELECT name, approval_status, updated_at
    FROM projects
    WHERE created_by = ?
      AND approval_status IN ('approved', 'rejected')
    ORDER BY updated_at DESC
    LIMIT 3
");
$updates_stmt->execute([$user_id]);
$recent_project_updates = $updates_stmt->fetchAll();

$count_stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE created_by = ?");
$count_stmt->execute([$user_id]);
$developer_project_count = (int) $count_stmt->fetchColumn();

$pending_stmt = $db->prepare("
    SELECT id, name, approval_status, updated_at
    FROM projects
    WHERE created_by = ?
      AND LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) IN ('pending', 'under_review', 'flagged')
    ORDER BY updated_at DESC, id DESC
    LIMIT 1
");
$pending_stmt->execute([$user_id]);
$developer_pending_project = $pending_stmt->fetch() ?: null;

if ($developer_pending_project) {
    $submission_block_reason = 'Your project "' . (string) ($developer_pending_project['name'] ?? 'Project') . '" is still waiting for admin approval. You can submit another project after this one is approved or rejected.';
} elseif ($developer_project_count >= $max_projects_per_developer) {
    $submission_block_reason = 'You have reached the developer project limit of ' . $max_projects_per_developer . ' projects.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_verified) {
    if ($submission_block_reason !== '') {
        $errors['general'] = $submission_block_reason;
    }

    foreach ($form as $key => $default) {
        $form[$key] = trim($_POST[$key] ?? $default);
    }

    $form['slug'] = $slugify($form['name']);
    $form['status'] = in_array($form['status'], ['upcoming', 'active', 'maintenance', 'paused'], true) ? $form['status'] : 'upcoming';

    $contract_source = $_POST;
    $bulk_rows = reviewEligibilityParseBulkRows($_POST['contract_bulk'] ?? '');
    if (!empty($bulk_rows)) {
        foreach ($bulk_rows as $bulk_row) {
            $contract_source['contract_network_name'][] = $bulk_row['network_name'];
            $contract_source['contract_chain_id'][] = $bulk_row['chain_id'];
            $contract_source['contract_address_multi'][] = $bulk_row['contract_address'];
            $contract_source['contract_token_type'][] = $bulk_row['token_type'];
            $contract_source['contract_is_active'][] = '1';
        }
    }
    if (empty($contract_source['contract_address_multi']) && $form['contract_address'] !== '') {
        $contract_source['contract_network_name'] = [$form['network']];
        $contract_source['contract_chain_id'] = [$_POST['chain_id'] ?? ''];
        $contract_source['contract_address_multi'] = [$form['contract_address']];
        $contract_source['contract_token_type'] = [$_POST['token_type'] ?? 'ERC20'];
        $contract_source['primary_contract_index'] = 0;
    }
    $contract_rows = reviewEligibilityNormalizeContractRows($contract_source, $errors);
    if (empty($contract_rows)) {
        $errors['contracts'] = 'Add one primary EVM contract for automatic review eligibility.';
    }
    foreach ($contract_rows as $row) {
        if ((int) $row['is_primary'] === 1) {
            $form['network'] = $row['network_name'];
            $form['contract_address'] = $row['contract_address'];
            break;
        }
    }

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

    if (!isset($_FILES['logo']) || (int)$_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors['logo'] = 'Project logo is required.';
    }

    if (empty($errors)) {
        if ($form['contract_address'] !== '') {
            $stmt = $db->prepare("SELECT id FROM projects WHERE LOWER(contract_address) = LOWER(?) LIMIT 1");
            $stmt->execute([$form['contract_address']]);
            if ($stmt->fetch()) {
                $errors['contract_address'] = 'This contract address already exists.';
            }
        }
        foreach ($contract_rows as $row) {
            $stmt = $db->prepare("SELECT project_id FROM project_contracts WHERE chain_id = ? AND contract_address = ? LIMIT 1");
            $lookup_address = ($row['token_type'] ?? '') === 'NATIVE' ? '' : $row['contract_address'];
            $stmt->execute([(int) $row['chain_id'], $lookup_address]);
            if ($stmt->fetch()) {
                $errors['contracts'] = 'One of these chain + contract pairs is already used by another project.';
                break;
            }
        }

        $stmt = $db->prepare("SELECT id FROM projects WHERE LOWER(website_url) = LOWER(?) LIMIT 1");
        $stmt->execute([$form['website_url']]);
        if ($stmt->fetch()) {
            $errors['website_url'] = 'This website URL is already used by another project.';
        }
    }

    if (empty($errors)) {
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
        }
    }

    if (empty($errors)) {
        $logos_dir = __DIR__ . '/logos';
        if (!is_dir($logos_dir)) {
            mkdir($logos_dir, 0755, true);
        }

        $safe_file_name = $form['slug'] . '-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
        $logo_abs_path = $logos_dir . DIRECTORY_SEPARATOR . $safe_file_name;
        $uploaded_logo_web_path = '/devhub/projects/logos/' . $safe_file_name;

        if (!move_uploaded_file($_FILES['logo']['tmp_name'], $logo_abs_path)) {
            $errors['logo'] = 'Could not save logo file. Please try again.';
        }
    }

    if (empty($errors)) {
        try {
            $insert = $db->prepare("
                INSERT INTO projects (
                    name, slug, logo, category, description, website_url,
                    telegram_url, twitter_url, contract_address, github_url, discord_url,
                    network, project_live_since, status, min_holding_amount, max_reward_rex,
                    required_holding_days, created_by, created_at, updated_at, approval_status
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, NOW(), NOW(), 'pending'
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
                $form['contract_address'] !== '' ? $form['contract_address'] : '',
                $form['github_url'] !== '' ? $form['github_url'] : null,
                $form['discord_url'] !== '' ? $form['discord_url'] : null,
                $form['network'] !== '' ? $form['network'] : null,
                $form['project_live_since'] !== '' ? $form['project_live_since'] : null,
                $form['status'],
                $form['min_holding_amount'] !== '' ? $form['min_holding_amount'] : null,
                $form['max_reward_rex'] !== '' ? $form['max_reward_rex'] : null,
                $form['required_holding_days'] !== '' ? $form['required_holding_days'] : null,
                $user_id
            ]);

            $project_id = (int) $db->lastInsertId();
            reviewEligibilitySaveProjectContracts($db, $project_id, $contract_rows);

            $success = true;
            $success_message = 'Project submitted successfully. It is now pending admin approval.';
            $form = [
                'name' => '', 'slug' => '', 'category' => '', 'description' => '', 'website_url' => '',
                'twitter_url' => '', 'telegram_url' => '', 'discord_url' => '', 'github_url' => '',
                'contract_address' => '', 'network' => '', 'project_live_since' => '', 'status' => 'upcoming',
                'min_holding_amount' => '', 'max_reward_rex' => '', 'required_holding_days' => ''
            ];
        } catch (PDOException $e) {
            $errors['general'] = 'Database error: ' . $e->getMessage();
            if ($uploaded_logo_web_path !== '') {
                $uploaded_file = __DIR__ . '/logos/' . basename($uploaded_logo_web_path);
                if (is_file($uploaded_file)) {
                    @unlink($uploaded_file);
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/devhub/assets/css/submit-project.css">

<div class="project-submit-wrapper">
    <div class="project-submit-header">
        <h1><i class="fas fa-layer-group"></i> Submit Project</h1>
        <p>Use this guided flow to submit your project for admin review.</p>
    </div>

    <?php if (!$is_verified): ?>
        <div class="submit-notice warning">
            <h3><i class="fas fa-shield-alt"></i> Verification Required</h3>
            <p>Your account must be verified before you can submit a project.</p>
            <a class="btn-secondary" href="<?php echo BASE_URL; ?>/devhub/apply.php">Complete Verification</a>
        </div>
    <?php elseif ($success): ?>
        <div class="submit-notice success">
            <h3><i class="fas fa-check-circle"></i> Submission Received</h3>
            <p><?php echo $esc($success_message); ?></p>
            <div class="notice-actions">
                <a class="btn-primary" href="<?php echo BASE_URL; ?>/devhub/index.php">Back to Dashboard</a>
            </div>
        </div>
    <?php elseif ($submission_block_reason !== ''): ?>
        <div class="submit-notice warning">
            <h3><i class="fas fa-hourglass-half"></i> Submission Temporarily Locked</h3>
            <p><?php echo $esc($submission_block_reason); ?></p>
            <p>You have submitted <?php echo number_format($developer_project_count); ?> of <?php echo number_format($max_projects_per_developer); ?> allowed projects.</p>
            <div class="notice-actions">
                <a class="btn-primary" href="<?php echo BASE_URL; ?>/devhub/index.php">Back to Dashboard</a>
            </div>
        </div>
    <?php else: ?>
        <?php if (!empty($recent_project_updates)): ?>
            <section class="submit-notice moderation-updates-panel">
                <div class="moderation-updates-head">
                    <div>
                        <span class="moderation-updates-kicker">Recent Activity</span>
                        <h3><i class="fas fa-clipboard-check"></i> Recent Moderation Updates</h3>
                        <p>Track the latest review decisions for your recently submitted projects.</p>
                    </div>
                    <div class="moderation-updates-count">
                        <strong><?php echo number_format(count($recent_project_updates)); ?></strong>
                        <span>Latest updates</span>
                    </div>
                </div>

                <div class="moderation-updates-list">
                    <?php foreach ($recent_project_updates as $moderation_item): ?>
                        <?php
                        $moderation_status_raw = strtolower(trim((string) ($moderation_item['approval_status'] ?? 'pending')));
                        $moderation_status_label = strtoupper($moderation_status_raw !== '' ? $moderation_status_raw : 'pending');
                        $moderation_status_class = 'status-pending';
                        $moderation_status_icon = 'fa-hourglass-half';

                        if ($moderation_status_raw === 'approved') {
                            $moderation_status_class = 'status-approved';
                            $moderation_status_icon = 'fa-circle-check';
                        } elseif ($moderation_status_raw === 'rejected') {
                            $moderation_status_class = 'status-rejected';
                            $moderation_status_icon = 'fa-circle-xmark';
                        } elseif ($moderation_status_raw === 'under_review') {
                            $moderation_status_class = 'status-under-review';
                            $moderation_status_icon = 'fa-spinner';
                        } elseif ($moderation_status_raw === 'flagged') {
                            $moderation_status_class = 'status-flagged';
                            $moderation_status_icon = 'fa-flag';
                        }
                        ?>
                        <article class="moderation-update-card">
                            <div class="moderation-update-icon <?php echo $esc($moderation_status_class); ?>">
                                <i class="fas <?php echo $esc($moderation_status_icon); ?>"></i>
                            </div>
                            <div class="moderation-update-body">
                                <div class="moderation-update-top">
                                    <strong><?php echo $esc($moderation_item['name'] ?? 'Project'); ?></strong>
                                    <span class="status-chip <?php echo $esc($moderation_status_class); ?>"><?php echo $esc($moderation_status_label); ?></span>
                                </div>
                                <div class="moderation-update-meta">
                                    <span><i class="fas fa-clock"></i> Updated <?php echo $esc((string) ($moderation_item['updated_at'] ?? '')); ?></span>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($errors['general'])): ?>
            <div class="submit-notice error"><p><?php echo $esc($errors['general']); ?></p></div>
        <?php endif; ?>

        <form id="projectWizardForm" class="wizard-form" method="POST" action="" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="action" value="final_submit">

            <div class="wizard-layout">
                <div class="wizard-main">
                    <div class="wizard-progress">
                        <div class="wizard-progress-track"></div>
                        <div class="wizard-progress-fill" id="wizardProgressFill"></div>
                        <div class="wizard-stepper-item active" data-step-nav="1"><span>1</span> Basic Info</div>
                        <div class="wizard-stepper-item" data-step-nav="2"><span>2</span> Links</div>
                        <div class="wizard-stepper-item" data-step-nav="3"><span>3</span> Technical</div>
                        <div class="wizard-stepper-item" data-step-nav="4"><span>4</span> Tokenomics</div>
                        <div class="wizard-stepper-item" data-step-nav="5"><span>5</span> Review</div>
                    </div>

                    <div class="wizard-card">
                        <section class="wizard-step active" data-step="1">
                            <h2>Basic Information</h2>
                            <p>Start with your core project identity.</p>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Project Name <span class="req">*</span></label>
                                    <input type="text" id="name" name="name" value="<?php echo $esc($form['name']); ?>" placeholder="CoinRex Wallet">
                                    <small class="field-error" data-error-for="name"><?php echo $esc($errors['name'] ?? ''); ?></small>
                                </div>

                                <div class="form-group">
                                    <label for="slug">Slug (Auto Generated)</label>
                                    <input type="text" id="slug" name="slug" value="<?php echo $esc($form['slug']); ?>" placeholder="coinrex-wallet" readonly>
                                    <small class="hint">Auto-generated from project name for SEO. You don't need to edit this.</small>
                                    <small class="field-error" data-error-for="slug"></small>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="logo">Project Logo <span class="req">*</span></label>
                                    <input type="file" id="logo" name="logo" accept=".png,.jpg,.jpeg,.webp">
                                    <small class="hint">PNG/JPG/WEBP, max 4MB.</small>
                                    <small class="field-error" data-error-for="logo"><?php echo $esc($errors['logo'] ?? ''); ?></small>
                                </div>

                                <div class="form-group">
                                    <label for="category">Category <span class="req">*</span></label>
                                    <select id="category" name="category">
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
                                    <small class="field-error" data-error-for="category"><?php echo $esc($errors['category'] ?? ''); ?></small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Short Description <span class="req">*</span></label>
                                <textarea id="description" name="description" rows="4" placeholder="Briefly describe your project..."><?php echo $esc($form['description']); ?></textarea>
                                <small class="field-error" data-error-for="description"><?php echo $esc($errors['description'] ?? ''); ?></small>
                            </div>
                        </section>

                        <section class="wizard-step" data-step="2">
                            <h2>Links & Social Presence</h2>
                            <p>Add your official links and community channels.</p>

                            <div class="form-group">
                                <label for="website_url">Website URL <span class="req">*</span></label>
                                <input type="url" id="website_url" name="website_url" value="<?php echo $esc($form['website_url']); ?>" placeholder="https://yourproject.com">
                                <small class="field-error" data-error-for="website_url"><?php echo $esc($errors['website_url'] ?? ''); ?></small>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="twitter_url">Twitter URL</label>
                                    <input type="url" id="twitter_url" name="twitter_url" value="<?php echo $esc($form['twitter_url']); ?>" placeholder="https://x.com/yourproject">
                                    <small class="field-error" data-error-for="twitter_url"><?php echo $esc($errors['twitter_url'] ?? ''); ?></small>
                                </div>
                                <div class="form-group">
                                    <label for="telegram_url">Telegram URL</label>
                                    <input type="url" id="telegram_url" name="telegram_url" value="<?php echo $esc($form['telegram_url']); ?>" placeholder="https://t.me/yourproject">
                                    <small class="field-error" data-error-for="telegram_url"><?php echo $esc($errors['telegram_url'] ?? ''); ?></small>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="discord_url">Discord URL</label>
                                    <input type="url" id="discord_url" name="discord_url" value="<?php echo $esc($form['discord_url']); ?>" placeholder="https://discord.gg/yourproject">
                                    <small class="field-error" data-error-for="discord_url"><?php echo $esc($errors['discord_url'] ?? ''); ?></small>
                                </div>
                                <div class="form-group">
                                    <label for="github_url">GitHub URL (Optional)</label>
                                    <input type="url" id="github_url" name="github_url" value="<?php echo $esc($form['github_url']); ?>" placeholder="https://github.com/org/repo">
                                    <small class="field-error" data-error-for="github_url"><?php echo $esc($errors['github_url'] ?? ''); ?></small>
                                </div>
                            </div>

                            <div class="encourage-note" id="socialHint">Add at least one social link to improve trust for reviewers.</div>
                        </section>

                        <section class="wizard-step" data-step="3">
                            <h2>Review Eligibility Contracts</h2>
                            <p>Add the official token/NFT contracts users can hold to unlock review submission.</p>

                            <input type="hidden" id="contract_address" name="contract_address" value="<?php echo $esc($form['contract_address']); ?>">
                            <input type="hidden" id="network" name="network" value="<?php echo $esc($form['network']); ?>">

                            <?php if (!empty($errors['contracts'])): ?>
                                <div class="submit-notice error"><p><?php echo $esc($errors['contracts']); ?></p></div>
                            <?php endif; ?>

                            <div class="eligibility-setup-card">
                                <div class="eligibility-setup-icon"><i class="fas fa-shield-check"></i></div>
                                <div class="eligibility-setup-copy">
                                    <span class="eligibility-kicker">Automatic review eligibility</span>
                                    <strong>Start with the main chain, then add every supported chain where users may hold the same token.</strong>
                                    <div class="eligibility-chip-row">
                                        <button type="button" class="eligibility-chip" data-quick-network="Ethereum">Ethereum</button>
                                        <button type="button" class="eligibility-chip" data-quick-network="BNB Smart Chain">BSC</button>
                                        <button type="button" class="eligibility-chip" data-quick-network="Base">Base</button>
                                        <button type="button" class="eligibility-chip" data-quick-network="Polygon">Polygon</button>
                                        <button type="button" class="eligibility-chip" data-quick-network="Arbitrum">Arbitrum</button>
                                    </div>
                                </div>
                            </div>

                            <div class="contract-builder contract-builder-submit" id="contractBuilder">
                                <div class="contract-row contract-row-head">
                                    <span>Primary</span><span>Network</span><span>Chain ID</span><span>Contract</span><span>Type</span><span></span>
                                </div>
                                <div id="contractRows">
                                    <div class="contract-row" data-contract-row>
                                        <label class="contract-primary-toggle"><input type="radio" name="primary_contract_index" value="0" checked><span>Primary</span></label>
                                        <label class="contract-field"><span>Network</span><select name="contract_network_name[]" data-network-select>
                                                <option value="">Network</option>
                                                <?php foreach (array_keys(reviewEligibilityKnownNetworks()) as $network): ?>
                                                    <option value="<?php echo $esc($network); ?>"><?php echo $esc($network); ?></option>
                                                <?php endforeach; ?>
                                            </select></label>
                                        <label class="contract-field"><span>Chain ID</span><input type="number" name="contract_chain_id[]" data-chain-id placeholder="1"></label>
                                        <label class="contract-field contract-address-field"><span>Contract Address</span><input type="text" name="contract_address_multi[]" data-contract-address placeholder="0x..."></label>
                                        <label class="contract-field"><span>Token Type</span><select name="contract_token_type[]" data-token-type>
                                                <option value="NATIVE">Native</option>
                                                <option value="ERC20">ERC20</option>
                                                <option value="ERC721">ERC721</option>
                                                <option value="ERC1155">ERC1155</option>
                                            </select></label>
                                        <input type="hidden" name="contract_is_active[]" value="1">
                                        <button type="button" class="contract-remove-btn" data-remove-contract><i class="fas fa-times"></i><span>Remove</span></button>
                                    </div>
                                </div>
                                <button type="button" class="btn-ghost contract-add-btn" id="addContractRow"><i class="fas fa-plus"></i> Add another chain</button>
                            </div>

                            <div class="form-group contract-bulk-box">
                                <label for="contract_bulk">Bulk Add Contracts</label>
                                <textarea id="contract_bulk" name="contract_bulk" rows="4" placeholder="Polygon,137,,NATIVE&#10;Base,8453,0x...,ERC20"></textarea>
                                <small class="hint">Optional. One row per chain: Network, Chain ID, Contract Address, Token Type.</small>
                            </div>

                            <div class="form-group">
                                <label for="project_live_since">Project Live Since</label>
                                <input type="date" id="project_live_since" name="project_live_since" value="<?php echo $esc($form['project_live_since']); ?>">
                                <small class="field-error" data-error-for="project_live_since"><?php echo $esc($errors['project_live_since'] ?? ''); ?></small>
                            </div>
                        </section>

                        <section class="wizard-step" data-step="4">
                            <h2>Tokenomics / Requirements</h2>
                            <p>Use this section to explain holding rules and reward caps for your campaign. All fields are optional, but accurate values help reviewers and users understand eligibility faster.</p>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="min_holding_amount">Minimum Holding Amount</label>
                                    <input type="text" id="min_holding_amount" name="min_holding_amount" value="<?php echo $esc($form['min_holding_amount']); ?>" placeholder="e.g. 100">
                                    <small class="hint">Example: minimum 100 tokens required in wallet to qualify.</small>
                                    <small class="field-error" data-error-for="min_holding_amount"><?php echo $esc($errors['min_holding_amount'] ?? ''); ?></small>
                                </div>

                                <div class="form-group">
                                    <label for="max_reward_rex">Maximum Reward ($REX)</label>
                                    <input type="text" id="max_reward_rex" name="max_reward_rex" value="<?php echo $esc($form['max_reward_rex']); ?>" placeholder="e.g. 5000">
                                    <small class="hint">Set a cap to prevent over-allocation in one campaign cycle.</small>
                                    <small class="field-error" data-error-for="max_reward_rex"><?php echo $esc($errors['max_reward_rex'] ?? ''); ?></small>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="required_holding_days">Required Holding Days</label>
                                    <input type="text" id="required_holding_days" name="required_holding_days" value="<?php echo $esc($form['required_holding_days']); ?>" placeholder="e.g. 30">
                                    <small class="hint">Example: users must hold for 30 days before they can claim.</small>
                                    <small class="field-error" data-error-for="required_holding_days"><?php echo $esc($errors['required_holding_days'] ?? ''); ?></small>
                                </div>
                                <div class="form-group">
                                    <label for="status">Project Status</label>
                                    <select id="status" name="status">
                                        <option value="upcoming" <?php echo $form['status'] === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                        <option value="active" <?php echo $form['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="maintenance" <?php echo $form['status'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                        <option value="paused" <?php echo $form['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                    </select>
                                    <small class="field-error" data-error-for="status"></small>
                                </div>
                            </div>
                        </section>

                        <section class="wizard-step" data-step="5">
                            <h2>Review & Submit</h2>
                            <p>Review your project details before final submission.</p>

                            <div class="review-grid">
                                <div class="review-item"><strong>Name:</strong> <span data-review="name">-</span></div>
                                <div class="review-item"><strong>Slug:</strong> <span data-review="slug">-</span></div>
                                <div class="review-item"><strong>Category:</strong> <span data-review="category">-</span></div>
                                <div class="review-item"><strong>Website:</strong> <span data-review="website_url">-</span></div>
                                <div class="review-item"><strong>Contract:</strong> <span data-review="contract_address">-</span></div>
                                <div class="review-item"><strong>Network:</strong> <span data-review="network">-</span></div>
                                <div class="review-item"><strong>Status:</strong> <span data-review="status">-</span></div>
                                <div class="review-item full"><strong>Description:</strong> <span data-review="description">-</span></div>
                            </div>

                            <div class="review-edit-actions">
                                <button type="button" class="btn-ghost review-edit-btn" data-edit-step="1">Edit Basic Info</button>
                                <button type="button" class="btn-ghost review-edit-btn" data-edit-step="2">Edit Links</button>
                                <button type="button" class="btn-ghost review-edit-btn" data-edit-step="3">Edit Technical</button>
                                <button type="button" class="btn-ghost review-edit-btn" data-edit-step="4">Edit Tokenomics</button>
                            </div>
                        </section>
                    </div>

                    <div class="wizard-actions" id="wizardActions">
                        <button type="button" class="btn-ghost" id="btnBack">Back</button>
                        <button type="button" class="btn-primary" id="btnNext">Next</button>
                        <button type="submit" class="btn-primary" id="btnSubmit">Submit Project</button>
                    </div>
                </div>

                <aside class="wizard-preview">
                    <h3>Live Preview</h3>
                    <div class="preview-logo-wrap"><img id="logoPreview" src="<?php echo BASE_URL; ?>/assets/images/favicon.png" alt="Project Logo Preview"></div>
                    <div class="preview-card">
                        <div class="preview-card-head">
                            <h4 id="previewName">Project Name</h4>
                            <span id="previewStatus">upcoming</span>
                        </div>
                        <p id="previewDescription">Your project summary will appear here.</p>
                        <ul>
                            <li><i class="fas fa-link"></i> <span id="previewWebsite">Website not set</span></li>
                            <li><i class="fas fa-cube"></i> <span id="previewNetwork">Network not set</span></li>
                            <li><i class="fas fa-hashtag"></i> <span id="previewSlug">project-slug</span></li>
                        </ul>
                    </div>
                </aside>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
(function() {
    'use strict';

    const form = document.getElementById('projectWizardForm');
    if (!form) {
        localStorage.removeItem('coinrex_submit_project_draft_v1');
        return;
    }

    const draftKey = 'coinrex_submit_project_draft_v1';
    const totalSteps = 5;
    let currentStep = 1;

    const steps = Array.from(document.querySelectorAll('.wizard-step'));
    const navItems = Array.from(document.querySelectorAll('.wizard-stepper-item'));
    const progressFill = document.getElementById('wizardProgressFill');
    const btnBack = document.getElementById('btnBack');
    const btnNext = document.getElementById('btnNext');
    const btnSubmit = document.getElementById('btnSubmit');
    const nameInput = document.getElementById('name');
    const slugInput = document.getElementById('slug');
    const logoInput = document.getElementById('logo');
    const socialHint = document.getElementById('socialHint');
    const logoPreview = document.getElementById('logoPreview');
    const contractRows = document.getElementById('contractRows');
    const addContractRow = document.getElementById('addContractRow');
    const knownChains = <?php echo json_encode(reviewEligibilityKnownNetworks(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

    const fieldIds = ['name', 'slug', 'category', 'description', 'website_url', 'twitter_url', 'telegram_url', 'discord_url', 'github_url', 'contract_address', 'network', 'project_live_since', 'status', 'min_holding_amount', 'max_reward_rex', 'required_holding_days'];

    function slugify(value) {
        return (value || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    }

    function setError(fieldName, message) {
        const node = document.querySelector('[data-error-for="' + fieldName + '"]');
        if (node) node.textContent = message || '';
    }

    function clearErrors(step) {
        const stepNode = document.querySelector('.wizard-step[data-step="' + step + '"]');
        if (!stepNode) return;
        stepNode.querySelectorAll('.field-error').forEach(function(el) { el.textContent = ''; });
    }

    function isValidUrl(value) {
        if (!value) return true;
        try {
            const parsed = new URL(value);
            return parsed.protocol === 'http:' || parsed.protocol === 'https:';
        } catch (err) {
            return false;
        }
    }

    function syncPrimaryContractFields() {
        const checked = document.querySelector('input[name="primary_contract_index"]:checked');
        const row = checked ? checked.closest('[data-contract-row]') : document.querySelector('[data-contract-row]');
        const networkField = document.getElementById('network');
        const contractField = document.getElementById('contract_address');
        if (!row || !networkField || !contractField) return;
        const network = row.querySelector('[data-network-select]');
        const address = row.querySelector('[data-contract-address]');
        networkField.value = network ? network.value : '';
        contractField.value = address ? address.value.trim() : '';
    }

    function refreshContractIndexes() {
        Array.from(document.querySelectorAll('[data-contract-row]')).forEach(function(row, index) {
            const radio = row.querySelector('input[name="primary_contract_index"]');
            if (radio) radio.value = String(index);
        });
        if (!document.querySelector('input[name="primary_contract_index"]:checked')) {
            const first = document.querySelector('input[name="primary_contract_index"]');
            if (first) first.checked = true;
        }
        syncPrimaryContractFields();
    }

    function bindContractRow(row) {
        const network = row.querySelector('[data-network-select]');
        const chainId = row.querySelector('[data-chain-id]');
        const address = row.querySelector('[data-contract-address]');
        const remove = row.querySelector('[data-remove-contract]');
        const radio = row.querySelector('input[name="primary_contract_index"]');
        const tokenType = row.querySelector('[data-token-type]');
        function syncNativeAddressState() {
            if (!address || !tokenType) return;
            const isNative = tokenType.value === 'NATIVE';
            address.readOnly = isNative;
            address.placeholder = isNative ? 'Native balance uses chain only' : '0x...';
            if (isNative) address.value = '';
        }
        if (network) {
            network.addEventListener('change', function() {
                if (knownChains[network.value] && chainId && !chainId.value) {
                    chainId.value = knownChains[network.value].chain_id;
                }
                syncPrimaryContractFields();
                saveDraft();
            });
        }
        [chainId, address, radio, tokenType].forEach(function(el) {
            if (!el) return;
            el.addEventListener('input', function() { syncNativeAddressState(); syncPrimaryContractFields(); saveDraft(); });
            el.addEventListener('change', function() { syncNativeAddressState(); syncPrimaryContractFields(); saveDraft(); });
        });
        if (remove) {
            remove.addEventListener('click', function() {
                if (document.querySelectorAll('[data-contract-row]').length <= 1) return;
                row.remove();
                refreshContractIndexes();
                saveDraft();
            });
        }
        syncNativeAddressState();
    }

    function createContractRow() {
        const first = document.querySelector('[data-contract-row]');
        if (!first || !contractRows) return null;
        const clone = first.cloneNode(true);
        clone.querySelectorAll('input, select').forEach(function(input) {
            if (input.type === 'radio') input.checked = false;
            else if (input.type === 'hidden') input.value = '1';
            else if (input.matches('[data-token-type]')) input.value = 'ERC20';
            else input.value = '';
        });
        contractRows.appendChild(clone);
        bindContractRow(clone);
        refreshContractIndexes();
        return clone;
    }

    function findEmptyContractRow() {
        return Array.from(document.querySelectorAll('[data-contract-row]')).find(function(row) {
        const network = row.querySelector('[data-network-select]');
        const address = row.querySelector('[data-contract-address]');
        const chainId = row.querySelector('[data-chain-id]');
        const tokenType = row.querySelector('[data-token-type]');
        return (!network || !network.value) && (!address || !address.value.trim()) && (!chainId || !chainId.value) && (!tokenType || tokenType.value === 'ERC20');
        }) || null;
    }

    function applyQuickNetwork(networkName) {
        let row = findEmptyContractRow() || createContractRow();
        if (!row) return;
        const network = row.querySelector('[data-network-select]');
        const chainId = row.querySelector('[data-chain-id]');
        if (network) network.value = networkName;
        if (chainId && knownChains[networkName]) chainId.value = knownChains[networkName].chain_id;
        syncPrimaryContractFields();
        saveDraft();
    }

    function validateStep(step) {
        clearErrors(step);
        let valid = true;

        if (step === 1) {
            const name = nameInput.value.trim();
            const slug = slugInput.value.trim();
            const category = document.getElementById('category').value.trim();
            const description = document.getElementById('description').value.trim();
            const hasLogo = logoInput.files && logoInput.files.length > 0;

            if (!name) { setError('name', 'Project name is required.'); valid = false; }
            if (!slug) { setError('slug', 'Slug will be auto-generated from project name.'); valid = false; }
            else if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) { setError('slug', 'Generated slug is invalid. Update project name.'); valid = false; }
            if (!category) { setError('category', 'Category is required.'); valid = false; }
            if (!description) { setError('description', 'Short description is required.'); valid = false; }
            if (!hasLogo) { setError('logo', 'Project logo is required.'); valid = false; }
        }

        if (step === 2) {
            const website = document.getElementById('website_url').value.trim();
            if (!website) {
                setError('website_url', 'Website URL is required.');
                valid = false;
            } else if (!isValidUrl(website)) {
                setError('website_url', 'Please enter a valid URL.');
                valid = false;
            }

            ['twitter_url', 'telegram_url', 'discord_url', 'github_url'].forEach(function(field) {
                const value = document.getElementById(field).value.trim();
                if (value && !isValidUrl(value)) {
                    setError(field, 'Please enter a valid URL.');
                    valid = false;
                }
            });

            const socialLinks = [
                document.getElementById('twitter_url').value.trim(),
                document.getElementById('telegram_url').value.trim(),
                document.getElementById('discord_url').value.trim(),
                document.getElementById('github_url').value.trim()
            ].filter(Boolean);
            if (socialHint) socialHint.classList.toggle('soft-warning', socialLinks.length === 0);
        }

        if (step === 3) {
            const rows = Array.from(document.querySelectorAll('[data-contract-row]'));
            const activeRows = rows.filter(function(row) {
                const network = row.querySelector('[data-network-select]')?.value.trim() || '';
                const chainId = row.querySelector('[data-chain-id]')?.value.trim() || '';
                const address = row.querySelector('[data-contract-address]')?.value.trim() || '';
                return network || chainId || address;
            });
            if (activeRows.length === 0) {
                alert('Add one primary contract for review eligibility.');
                valid = false;
            }
            activeRows.forEach(function(row) {
                const network = row.querySelector('[data-network-select]')?.value.trim() || '';
                const chainId = row.querySelector('[data-chain-id]')?.value.trim() || '';
                const address = row.querySelector('[data-contract-address]')?.value.trim() || '';
                const tokenType = row.querySelector('[data-token-type]')?.value.trim() || 'ERC20';
                if (!network || !/^\d+$/.test(chainId) || !/^0x[a-fA-F0-9]{40}$/.test(address)) {
                    if (tokenType === 'NATIVE' && network && /^\d+$/.test(chainId)) {
                        return;
                    }
                    alert('Every contract row needs network, positive chain ID, and valid 0x contract address.');
                    valid = false;
                }
            });
            const liveSince = document.getElementById('project_live_since').value.trim();
            if (liveSince && !/^\d{4}-\d{2}-\d{2}$/.test(liveSince)) { setError('project_live_since', 'Please provide a valid date.'); valid = false; }
        }

        if (step === 4) {
            const minHolding = document.getElementById('min_holding_amount').value.trim();
            const maxReward = document.getElementById('max_reward_rex').value.trim();
            const requiredDays = document.getElementById('required_holding_days').value.trim();

            if (minHolding && Number.isNaN(Number(minHolding))) { setError('min_holding_amount', 'Must be numeric.'); valid = false; }
            if (maxReward && Number.isNaN(Number(maxReward))) { setError('max_reward_rex', 'Must be numeric.'); valid = false; }
            if (requiredDays && !/^\d+$/.test(requiredDays)) { setError('required_holding_days', 'Must be a whole number.'); valid = false; }
        }

        return valid;
    }

    function updateReview() {
        const values = {};
        fieldIds.forEach(function(id) { const el = document.getElementById(id); values[id] = el ? el.value.trim() : ''; });

        ['name', 'slug', 'category', 'website_url', 'contract_address', 'network', 'status', 'description'].forEach(function(key) {
            const node = document.querySelector('[data-review="' + key + '"]');
            if (node) node.textContent = values[key] || '-';
        });

        document.getElementById('previewName').textContent = values.name || 'Project Name';
        document.getElementById('previewStatus').textContent = values.status || 'upcoming';
        document.getElementById('previewDescription').textContent = values.description || 'Your project summary will appear here.';
        document.getElementById('previewWebsite').textContent = values.website_url || 'Website not set';
        document.getElementById('previewNetwork').textContent = values.network || 'Network not set';
        document.getElementById('previewSlug').textContent = values.slug || 'project-slug';
    }

    function updateStepUI() {
        steps.forEach(function(step) {
            const stepNo = Number(step.dataset.step);
            step.classList.toggle('active', stepNo === currentStep);
        });

        navItems.forEach(function(item) {
            const stepNo = Number(item.dataset.stepNav);
            item.classList.toggle('active', stepNo === currentStep);
            item.classList.toggle('completed', stepNo < currentStep);
        });

        const percent = ((currentStep - 1) / (totalSteps - 1)) * 100;
        if (progressFill) progressFill.style.width = percent + '%';

        btnBack.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        btnNext.style.display = currentStep === totalSteps ? 'none' : 'inline-flex';
        btnSubmit.style.display = currentStep === totalSteps ? 'inline-flex' : 'none';

        if (currentStep === totalSteps) updateReview();
    }

    function goToStep(step) {
        currentStep = Math.max(1, Math.min(totalSteps, step));
        updateStepUI();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function readLogoPreview() {
        const file = logoInput.files && logoInput.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(event) { logoPreview.src = event.target.result; };
        reader.readAsDataURL(file);
    }

    function saveDraft() {
        const draft = {};
        fieldIds.forEach(function(id) { const el = document.getElementById(id); draft[id] = el ? el.value : ''; });
        draft.currentStep = currentStep;
        localStorage.setItem(draftKey, JSON.stringify(draft));
    }

    function restoreDraft() {
        const raw = localStorage.getItem(draftKey);
        if (!raw) return;
        try {
            const draft = JSON.parse(raw);
            fieldIds.forEach(function(id) {
                const el = document.getElementById(id);
                if (el && typeof draft[id] === 'string' && !el.value) el.value = draft[id];
            });
            if (draft.currentStep && Number(draft.currentStep) > 1) currentStep = Number(draft.currentStep);
        } catch (error) {
            localStorage.removeItem(draftKey);
        }
    }

    btnNext.addEventListener('click', function() {
        if (!validateStep(currentStep)) return;
        goToStep(currentStep + 1);
        saveDraft();
    });

    btnBack.addEventListener('click', function() {
        goToStep(currentStep - 1);
        saveDraft();
    });

    form.addEventListener('submit', function(event) {
        const brokenStep = [1, 2, 3, 4].find(function(step) { return !validateStep(step); });
        if (brokenStep) {
            event.preventDefault();
            goToStep(brokenStep);
            return;
        }
        localStorage.removeItem(draftKey);
    });

    document.querySelectorAll('.review-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() { goToStep(Number(btn.dataset.editStep || 1)); });
    });

    logoInput.addEventListener('change', function() {
        setError('logo', '');
        readLogoPreview();
        saveDraft();
    });

    nameInput.addEventListener('input', function() {
        slugInput.value = slugify(nameInput.value);
        updateReview();
        saveDraft();
    });

    fieldIds.forEach(function(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', function() { updateReview(); saveDraft(); });
        el.addEventListener('change', function() { updateReview(); saveDraft(); });
    });

    if (addContractRow) addContractRow.addEventListener('click', createContractRow);
    document.querySelectorAll('[data-quick-network]').forEach(function(button) {
        button.addEventListener('click', function() {
            applyQuickNetwork(button.getAttribute('data-quick-network') || '');
        });
    });
    document.querySelectorAll('[data-contract-row]').forEach(bindContractRow);
    refreshContractIndexes();

    restoreDraft();
    updateReview();
    updateStepUI();
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
