
<?php
$page_title = 'Developer Verification Application';
$activePage = 'get-verified';
require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/devhub/assets/css/apply.css">
<?php
$user_id = getCurrentUserId();
$db = getDevHubDB();
$cooldown_seconds = 10 * 24 * 60 * 60;
$identity_change_wait_seconds = 30 * 24 * 60 * 60;

$stmt = $db->prepare("
    SELECT id, full_name, username, email, password, has_verified_badge
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$user_id]);
$user = $stmt->fetch() ?: [
    'id' => $user_id,
    'full_name' => '',
    'username' => '',
    'email' => '',
    'password' => '',
    'has_verified_badge' => 0,
];

$verification = getLatestDeveloperVerification($db, $user_id);
$state = buildDeveloperVerificationState($verification, $user, $user_id, $cooldown_seconds, $identity_change_wait_seconds);

$error = '';
$success = false;
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $verification_method = trim((string) ($_POST['verification_method'] ?? ''));
    $verification_id = (int) ($verification['id'] ?? 0);

    if (empty($user['email']) || empty($user['username'])) {
        $error = 'Unable to load your account details. Please sign in again.';
    } elseif ($action === 'request_change') {
        if (!$state['is_verified']) {
            $error = 'Only approved developers can request an identity change.';
        } elseif (!$state['can_request_identity_change']) {
            $available_on = $state['identity_change_available_at']
                ? date('F j, Y g:i A', $state['identity_change_available_at'])
                : 'later';
            $error = 'Identity changes are available only once every 30 days. Your next request can be submitted after ' . $available_on . '.';
        } elseif ($verification_id <= 0) {
            $error = 'Verification record not found for this account.';
        } else {
            try {
                $stmt = $db->prepare("
                    UPDATE developer_verification
                    SET status = 'change_requested',
                        updated_at = NOW()
                    WHERE id = ? AND user_id = ?
                ");
                $result = $stmt->execute([$verification_id, $user_id]);

                if ($result) {
                    $success = true;
                    $success_message = 'Your identity change request has been submitted for admin review.';
                } else {
                    $error = 'Failed to submit the change request. Please try again.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'request_review') {
        $error = 'One valid proof method is enough. Submit either a social post URL or a website meta tag.';
    } elseif ($state['cooldown_active']) {
        $cooldown_until = $state['cooldown_ends_at'] ? date('F j, Y g:i A', $state['cooldown_ends_at']) : 'later';
        $error = 'Your profile is in the rejection cooldown window. You can submit again after ' . $cooldown_until . '.';
    } elseif ($verification_method === 'post') {
        $post_url = trim((string) ($_POST['post_url'] ?? ''));

        if ($post_url === '') {
            $error = 'Verification post URL is required.';
        } else {
            try {
                if ($verification_id > 0) {
                    $stmt = $db->prepare("
                        UPDATE developer_verification
                        SET full_name = ?,
                            email = ?,
                            username = ?,
                            password_hash = ?,
                            verification_post_url = ?,
                            verification_url = NULL,
                            verification_code = NULL,
                            status = 'pending',
                            updated_at = NOW()
                        WHERE id = ? AND user_id = ?
                    ");
                    $result = $stmt->execute([
                        $user['full_name'],
                        $user['email'],
                        $user['username'],
                        $user['password'],
                        $post_url,
                        $verification_id,
                        $user_id,
                    ]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO developer_verification
                        (user_id, full_name, email, username, password_hash, status, verification_post_url, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())
                    ");
                    $result = $stmt->execute([
                        $user_id,
                        $user['full_name'],
                        $user['email'],
                        $user['username'],
                        $user['password'],
                        $post_url,
                    ]);
                }

                if ($result) {
                    $success = true;
                    $success_message = 'Your social media proof has been submitted for admin review.';
                    unset($_SESSION['devhub_terms_agreed'], $_SESSION['devhub_terms_agreed_at']);
                } else {
                    $error = 'Failed to submit your verification proof. Please try again.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($verification_method === 'code') {
        $website_url = trim((string) ($_POST['website_url'] ?? ''));
        $meta_code = trim((string) ($_POST['meta_code'] ?? ''));
        $errors = [];

        if ($website_url === '') {
            $errors[] = 'Website URL is required.';
        }
        if ($meta_code === '') {
            $errors[] = 'Meta tag code is required.';
        }

        if (empty($errors)) {
            try {
                if ($verification_id > 0) {
                    $stmt = $db->prepare("
                        UPDATE developer_verification
                        SET full_name = ?,
                            email = ?,
                            username = ?,
                            password_hash = ?,
                            verification_post_url = NULL,
                            verification_url = ?,
                            verification_code = ?,
                            status = 'pending',
                            updated_at = NOW()
                        WHERE id = ? AND user_id = ?
                    ");
                    $result = $stmt->execute([
                        $user['full_name'],
                        $user['email'],
                        $user['username'],
                        $user['password'],
                        $website_url,
                        $meta_code,
                        $verification_id,
                        $user_id,
                    ]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO developer_verification
                        (user_id, full_name, email, username, password_hash, status, verification_url, verification_code, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())
                    ");
                    $result = $stmt->execute([
                        $user_id,
                        $user['full_name'],
                        $user['email'],
                        $user['username'],
                        $user['password'],
                        $website_url,
                        $meta_code,
                    ]);
                }

                if ($result) {
                    $success = true;
                    $success_message = 'Your website meta-tag proof has been submitted for admin review.';
                    unset($_SESSION['devhub_terms_agreed'], $_SESSION['devhub_terms_agreed_at']);
                } else {
                    $error = 'Failed to submit your verification proof. Please try again.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            $error = implode(' ', $errors);
        }
    }

    $verification = getLatestDeveloperVerification($db, $user_id);
    $state = buildDeveloperVerificationState($verification, $user, $user_id, $cooldown_seconds, $identity_change_wait_seconds);
}

if ($state['status'] === 'approved' && (int) ($user['has_verified_badge'] ?? 0) === 0) {
    try {
        $stmt = $db->prepare("
            UPDATE users
            SET has_verified_badge = 1
            WHERE id = ? AND has_verified_badge = 0
        ");
        $stmt->execute([$user_id]);
        $user['has_verified_badge'] = 1;
        $state = buildDeveloperVerificationState($verification, $user, $user_id, $cooldown_seconds, $identity_change_wait_seconds);
    } catch (PDOException $e) {
        // Keep the page usable even if badge sync fails.
    }
}

$generated_meta_tag = '<meta name="coinrex-verification" content="' . ($user['username'] ?: 'developer') . '-' . $user_id . '">';
$cooldown_ends_display = $state['cooldown_ends_at'] ? date('F j, Y g:i A', $state['cooldown_ends_at']) : '';
$identity_change_available_display = $state['identity_change_available_at']
    ? date('F j, Y g:i A', $state['identity_change_available_at'])
    : '';
?>

<div class="apply-wrapper">
    <div class="apply-header">
        <h1><i class="fas fa-shield-alt"></i> Developer Verification Application</h1>
        <p>Submit one valid proof method to unlock verified developer access on CoinRex.</p>
    </div>

    <?php if ($state['is_verified']): ?>
        <div class="apply-card success-card">
            <div class="card-body" style="text-align: center;">
                <div class="success-badge">Verified Developer</div>
                <div class="success-icon"><i class="fas fa-crown"></i></div>
                <h3>Congratulations! Your developer identity is officially verified.</h3>
                <p>Your profile has been approved, your trust badge is active, and you can now register projects across DevHub.</p>
                <div class="action-row">
                    <a href="<?php echo BASE_URL; ?>/devhub/index.php" class="btn-primary">
                        <i class="fas fa-arrow-left"></i> Return to Dashboard
                    </a>
                    <?php if (!$state['is_change_requested']): ?>
                        <?php if ($state['can_request_identity_change']): ?>
                            <form method="POST" action="" class="inline-action-form">
                                <input type="hidden" name="action" value="request_change">
                                <button type="submit" class="btn-secondary">
                                    <i class="fas fa-rotate"></i> Change Identity
                                </button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn-secondary btn-disabled" disabled>
                                <i class="fas fa-lock"></i> Change Identity
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if (!$state['is_change_requested']): ?>
                    <p class="change-window-note">
                        <?php if ($state['can_request_identity_change']): ?>
                            Identity updates are available once every 30 days, and your next request window is open now.
                        <?php else: ?>
                            Identity updates are allowed once every 30 days.
                        <?php endif; ?>
                        <?php if (!$state['can_request_identity_change'] && $identity_change_available_display !== ''): ?>
                            Next available on <?php echo htmlspecialchars($identity_change_available_display, ENT_QUOTES, 'UTF-8'); ?>.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($state['is_change_requested']): ?>
            <div class="apply-card pending-card">
                <div class="card-body" style="text-align: center;">
                    <div class="pending-icon"><i class="fas fa-hourglass-half"></i></div>
                    <h3>Identity Change Request Pending</h3>
                    <p>Your request to update verification details is under review. Your current verified access stays active until the review is finished.</p>
                </div>
            </div>
        <?php endif; ?>
    <?php elseif ($state['is_pending']): ?>
        <div class="steps-indicator">
            <div class="step completed">
                <div class="step-number">1</div>
                <div class="step-label">Submit Proof</div>
            </div>
            <div class="step active">
                <div class="step-number">2</div>
                <div class="step-label">Admin Review</div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-label">Verified</div>
            </div>
        </div>

        <div class="apply-card">
            <div class="card-header">
                <h2><i class="fas fa-user"></i> Developer Information</h2>
                <p>Your current account details</p>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" value="<?php echo htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-at"></i> Username</label>
                        <input type="text" value="<?php echo htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" value="<?php echo htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                </div>
                <div class="submitted-info">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($state['submitted_method_label'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        </div>

        <div class="apply-card pending-card">
            <div class="card-body" style="text-align: center;">
                <div class="pending-icon"><i class="fas fa-hourglass-half"></i></div>
                <h3>Verification Pending Review</h3>
                <p>Your proof has been submitted and is waiting for admin approval.</p>
                <a href="<?php echo BASE_URL; ?>/devhub/index.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    <?php elseif ($state['is_rejected'] && $state['cooldown_active']): ?>
        <div class="steps-indicator">
            <div class="step completed">
                <div class="step-number">1</div>
                <div class="step-label">Proof Sent</div>
            </div>
            <div class="step rejected">
                <div class="step-number"><i class="fas fa-xmark"></i></div>
                <div class="step-label">Rejected</div>
            </div>
            <div class="step active">
                <div class="step-number">3</div>
                <div class="step-label">Cooldown</div>
            </div>
        </div>

        <div class="apply-card rejected-card">
            <div class="card-body" style="text-align: center;">
                <div class="pending-icon"><i class="fas fa-clock"></i></div>
                <h3>Your last verification request was rejected.</h3>
                <p>A 10 day cooldown is now active before you can submit a new identity proof.</p>
                <?php if ($cooldown_ends_display !== ''): ?>
                    <p class="cooldown-note">You can apply again after <?php echo htmlspecialchars($cooldown_ends_display, ENT_QUOTES, 'UTF-8'); ?>.</p>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/devhub/index.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="steps-indicator">
            <div class="step active">
                <div class="step-number">1</div>
                <div class="step-label">Submit Proof</div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-label">Admin Review</div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-label">Verified</div>
            </div>
        </div>

        <div class="apply-card review-card">
            <div class="card-body" style="text-align: center;">
                <div class="review-icon"><i class="fas fa-route"></i></div>
                <h3>Choose one verification path</h3>
                <p>Use either a public social media post URL or a website meta-tag. One valid method is enough to start review.</p>
            </div>
        </div>

        <?php if ($state['needs_revision']): ?>
            <div class="alert alert-warning">
                Admin requested changes to your last submission. Update one proof method below and resubmit for review.
            </div>
        <?php elseif ($state['is_rejected'] && !$state['cooldown_active']): ?>
            <div class="alert alert-info">
                Your 10 day cooldown has finished. You can submit a fresh verification proof now.
            </div>
        <?php endif; ?>

        <div class="apply-card">
            <div class="card-header">
                <h2><i class="fas fa-user"></i> Developer Information</h2>
                <p>Your current account details</p>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" value="<?php echo htmlspecialchars((string) ($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-at"></i> Username</label>
                        <input type="text" value="<?php echo htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" value="<?php echo htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" disabled>
                </div>
            </div>
        </div>

        <div class="apply-card">
            <div class="card-header">
                <h2><i class="fab fa-twitter"></i> Social Media Post Verification</h2>
                <p>Post about CoinRex from your official profile and submit the public URL.</p>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="method-form">
                    <input type="hidden" name="verification_method" value="post">
                    <div class="form-group">
                        <label><i class="fas fa-link"></i> Verification Post URL</label>
                        <input
                            type="text"
                            name="post_url"
                            placeholder="https://twitter.com/yourhandle/status/123456789"
                            value="<?php echo htmlspecialchars((string) ($verification['verification_post_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                        <div class="help-text">Use a post from your public project or founder account that clearly identifies your CoinRex profile.</div>
                    </div>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Social Proof
                    </button>
                </form>
            </div>
        </div>

        <div class="apply-card">
            <div class="card-header">
                <h2><i class="fas fa-code"></i> Meta Tag Verification</h2>
                <p>Add this meta tag to the head section of your official website.</p>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="method-form">
                    <input type="hidden" name="verification_method" value="code">
                    <div class="form-group">
                        <label><i class="fas fa-code"></i> Generated Meta Tag</label>
                        <div class="meta-tag-display"><?php echo htmlspecialchars($generated_meta_tag, ENT_QUOTES, 'UTF-8'); ?></div>
                        <button type="button" class="btn-copy" onclick="copyMetaTag()">
                            <i class="fas fa-copy"></i> Copy Meta Tag
                        </button>
                        <div class="help-text">Place this exact meta tag inside your website head section before submitting.</div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Website URL</label>
                        <input
                            type="text"
                            name="website_url"
                            placeholder="https://yourwebsite.com"
                            value="<?php echo htmlspecialchars((string) ($verification['verification_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                        >
                        <div class="help-text">Enter the exact page URL where the meta tag is visible.</div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Meta Tag Code</label>
                        <textarea name="meta_code" rows="3" placeholder="Paste the exact meta tag code you added"><?php echo htmlspecialchars((string) ($verification['verification_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <div class="help-text">Paste the same meta tag code so the admin can verify it quickly.</div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Website Proof
                    </button>
                </form>
            </div>
        </div>

        <div class="form-actions">
            <a href="<?php echo BASE_URL; ?>/devhub/index.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    <?php endif; ?>
</div>

<div id="toastContainer"></div>

<script>
    function copyMetaTag() {
        const metaTag = '<?php echo addslashes($generated_meta_tag); ?>';

        navigator.clipboard.writeText(metaTag).then(function () {
            showToast('Meta tag copied to clipboard.', 'success');
        });
    }

    function showToast(message, type) {
        const container = document.getElementById('toastContainer');
        container.innerHTML = '';

        const toast = document.createElement('div');
        toast.className = 'toast-notification toast-' + type;
        toast.innerHTML = '<div class="toast-content"><i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i><span>' + message + '</span></div>';
        container.appendChild(toast);

        setTimeout(function () {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';

            setTimeout(function () {
                toast.remove();
            }, 300);
        }, 5000);
    }

    <?php if ($success): ?>
    showToast('<?php echo addslashes($success_message); ?>', 'success');
    <?php elseif ($error): ?>
    showToast('<?php echo addslashes($error); ?>', 'error');
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; __halt_compiler(); ?>

<?php
$user_id = getCurrentUserId();

$db = getDevHubDB();

// Get existing verification record
$stmt = $db->prepare("SELECT * FROM developer_verification WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$verification = $stmt->fetch();

// Determine current verification status from latest record
$verification_status = strtolower(trim((string)($verification['status'] ?? '')));

// Check if user is already verified (approved status wins)
$is_verified = ($verification_status === 'approved') || isVerifiedDeveloper($user_id);

// Check if there's a pending change request
$has_pending_change = ($verification && $verification['status'] == 'change_requested');

// Handle form submission
$error = '';
$success = false;
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Handle Request Change (for already verified users)
    if ($action === 'request_change' && $is_verified) {
        try {
            $stmt = $db->prepare("
                UPDATE developer_verification 
                SET status = 'change_requested',
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $result = $stmt->execute([$user_id]);
            
            if ($result) {
                $success = true;
                $success_message = "Your change request has been submitted! Admin will review your updated information.";
                // Refresh verification data
                $stmt = $db->prepare("SELECT * FROM developer_verification WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                $stmt->execute([$user_id]);
                $verification = $stmt->fetch();
                $has_pending_change = true;
            } else {
                $error = "Failed to submit change request. Please try again.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
    // Handle Initial Verification Submission (for unverified users)
    elseif (!$is_verified) {
        $verification_method = $_POST['verification_method'] ?? '';
        
        if ($verification_method == 'post') {
            $post_url = trim($_POST['post_url'] ?? '');
            
            if (empty($post_url)) {
                $error = "Verification post URL is required";
            } else {
                try {
                    if ($verification) {
                        $stmt = $db->prepare("
                            UPDATE developer_verification 
                            SET verification_post_url = ?,
                                status = 'pending',
                                updated_at = NOW()
                            WHERE user_id = ?
                        ");
                        $result = $stmt->execute([$post_url, $user_id]);
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO developer_verification 
                            (user_id, verification_post_url, status, created_at, updated_at)
                            VALUES (?, ?, 'pending', NOW(), NOW())
                        ");
                        $result = $stmt->execute([$user_id, $post_url]);
                    }
                    
                    if ($result) {
                        $success = true;
                        $success_message = "Social Media Post verification submitted successfully!";
                        // Refresh verification data
                        $stmt = $db->prepare("SELECT * FROM developer_verification WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                        $stmt->execute([$user_id]);
                        $verification = $stmt->fetch();
                    } else {
                        $error = "Failed to submit application. Please try again.";
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        } 
        elseif ($verification_method == 'code') {
            $website_url = trim($_POST['website_url'] ?? '');
            $meta_code = trim($_POST['meta_code'] ?? '');
            
            $errors = [];
            if (empty($website_url)) $errors[] = "Website URL is required";
            if (empty($meta_code)) $errors[] = "Meta tag code is required";
            
            if (empty($errors)) {
                try {
                    if ($verification) {
                        $stmt = $db->prepare("
                            UPDATE developer_verification 
                            SET verification_url = ?,
                                verification_code = ?,
                                status = 'pending',
                                updated_at = NOW()
                            WHERE user_id = ?
                        ");
                        $result = $stmt->execute([$website_url, $meta_code, $user_id]);
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO developer_verification 
                            (user_id, verification_url, verification_code, status, created_at, updated_at)
                            VALUES (?, ?, ?, 'pending', NOW(), NOW())
                        ");
                        $result = $stmt->execute([$user_id, $website_url, $meta_code]);
                    }
                    
                    if ($result) {
                        $success = true;
                        $success_message = "Meta Tag verification submitted successfully!";
                        // Refresh verification data
                        $stmt = $db->prepare("SELECT * FROM developer_verification WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                        $stmt->execute([$user_id]);
                        $verification = $stmt->fetch();
                    } else {
                        $error = "Failed to submit application. Please try again.";
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            } else {
                $error = implode("<br>", $errors);
            }
        }
        // Handle Final Review Request (both methods submitted)
        elseif ($action === 'request_review') {
            try {
                $stmt = $db->prepare("
                    UPDATE developer_verification 
                    SET status = 'pending',
                        updated_at = NOW()
                    WHERE user_id = ?
                ");
                $result = $stmt->execute([$user_id]);
                
                if ($result) {
                    $success = true;
                    $success_message = "Your verification request has been submitted for admin review!";
                    // Refresh verification data
                    $stmt = $db->prepare("SELECT * FROM developer_verification WHERE user_id = ? ORDER BY id DESC LIMIT 1");
                    $stmt->execute([$user_id]);
                    $verification = $stmt->fetch();
                } else {
                    $error = "Failed to submit review request. Please try again.";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Check what methods have been submitted
$has_post_submitted = !empty($verification['verification_post_url']);
$has_code_submitted = !empty($verification['verification_code']);
$both_submitted = $has_post_submitted && $has_code_submitted;
$verification_status = strtolower(trim((string)($verification['status'] ?? '')));
$is_pending = ($verification_status === 'pending');
$is_approved = ($verification_status === 'approved');
$is_change_requested = ($verification_status === 'change_requested');

// Keep user badge in sync once verification is approved
if ($is_approved) {
    try {
        $stmt = $db->prepare("
            UPDATE users
            SET has_verified_badge = 1
            WHERE id = ? AND has_verified_badge = 0
        ");
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        // Do not block page rendering if badge sync fails.
    }
}

// Get user data
$stmt = $db->prepare("SELECT full_name, username, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Generate meta tag
$generated_meta_tag = '<meta name="coinrex-verification" content="' . htmlspecialchars($user['username'] ?? 'developer') . '-' . uniqid() . '">';
?>

<div class="apply-wrapper">
    <div class="apply-header">
        <h1><i class="fas fa-shield-alt"></i> Developer Verification Application</h1>
        <p>Get verified to unlock project registration and gain trust from the community</p>
    </div>
    
    <!-- ============================================ -->
    <!-- SECTION 1: USER IS ALREADY VERIFIED (APPROVED) -->
    <!-- ============================================ -->
    <?php if ($is_verified): ?>
        
        <!-- Success Message for Approved Users -->
        <div class="apply-card success-card">
            <div class="card-body" style="text-align: center;">
                <div class="success-icon">🎉</div>
                <h3>Congratulations! You're Verified! ✅</h3>
                <p>Your developer account has been approved. You can now register projects and access all developer features.</p>
                <a href="<?php echo BASE_URL; ?>/devhub/index.php" class="btn-primary">
                    <i class="fas fa-arrow-left"></i> Return to Dashboard
                </a>
            </div>
        </div>
        
        <!-- Show pending change request message if applicable -->
        <?php if ($is_change_requested): ?>
            <div class="apply-card pending-card">
                <div class="card-body" style="text-align: center;">
                    <div class="pending-icon">⏳</div>
                    <h3>Change Request Pending</h3>
                    <p>Your request to update verification information has been submitted. Admin will review your changes.</p>
                </div>
            </div>
        <?php endif; ?>
    
    <!-- ============================================ -->
    <!-- SECTION 2: USER HAS PENDING VERIFICATION -->
    <!-- ============================================ -->
    <?php elseif ($is_pending): ?>
        
        <!-- Steps Indicator -->
        <div class="steps-indicator">
            <div class="step completed">
                <div class="step-number">✓</div>
                <div class="step-label">Submit Methods</div>
            </div>
            <div class="step completed">
                <div class="step-number">✓</div>
                <div class="step-label">Request Review</div>
            </div>
            <div class="step active">
                <div class="step-number">3</div>
                <div class="step-label">Get Verified ✓</div>
            </div>
        </div>
        
        <!-- Developer Info Card -->
        <div class="apply-card">
            <div class="card-header">
                <h2><i class="fas fa-user"></i> Developer Information</h2>
                <p>Your account information</p>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-at"></i> Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                </div>
            </div>
        </div>
        
        <!-- Pending Status Message -->
        <div class="apply-card pending-card">
            <div class="card-body" style="text-align: center;">
                <div class="pending-icon">⏳</div>
                <h3>Verification Request Submitted!</h3>
                <p>Your application is now pending admin review. You will be notified once approved.</p>
                <a href="<?php echo BASE_URL; ?>/devhub/index.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    
    <!-- ============================================ -->
    <!-- SECTION 3: UNVERIFIED USER - SHOW FORM -->
    <!-- ============================================ -->
    <?php else: ?>
        
        <!-- Steps Indicator -->
        <div class="steps-indicator">
            <div class="step <?php echo $has_post_submitted || $has_code_submitted ? 'completed' : 'active'; ?>">
                <div class="step-number">1</div>
                <div class="step-label">Submit Methods</div>
            </div>
            <div class="step <?php echo $both_submitted ? 'active' : ''; ?>">
                <div class="step-number">2</div>
                <div class="step-label">Request Review</div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-label">Get Verified ✓</div>
            </div>
        </div>
        
        <!-- Developer Info Card -->
        <div class="apply-card">
            <div class="card-header">
                <h2><i class="fas fa-user"></i> Developer Information</h2>
                <p>Your account information</p>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-at"></i> Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" disabled>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                </div>
            </div>
        </div>
        
        <!-- Post Method Card -->
        <?php if (!$both_submitted): ?>
        <div class="apply-card <?php echo $has_post_submitted ? 'submitted' : ''; ?>">
            <div class="card-header">
                <h2><i class="fab fa-twitter"></i> Social Media Post Verification</h2>
                <p>Post about CoinRex on Twitter/X/Telegram</p>
                <?php if ($has_post_submitted): ?>
                    <span class="status-badge submitted"><i class="fas fa-check"></i> Submitted</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="method-form">
                    <input type="hidden" name="verification_method" value="post">
                    <div class="form-group">
                        <label><i class="fas fa-link"></i> Verification Post URL</label>
                        <input type="text" name="post_url" 
                               placeholder="https://twitter.com/yourhandle/status/123456789 or https://x.com/..." 
                               value="<?php echo htmlspecialchars($verification['verification_post_url'] ?? ''); ?>"
                               <?php echo $has_post_submitted ? 'disabled' : ''; ?>>
                        <div class="help-text">Post about CoinRex on your social media and paste the link here.</div>
                    </div>
                    <?php if (!$has_post_submitted): ?>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Post Verification
                        </button>
                    <?php else: ?>
                        <div class="submitted-info">
                            <i class="fas fa-check-circle"></i> Already submitted. Waiting for review.
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Code Method Card -->
        <?php if (!$both_submitted): ?>
        <div class="apply-card <?php echo $has_code_submitted ? 'submitted' : ''; ?>">
            <div class="card-header">
                <h2><i class="fas fa-code"></i> Meta Tag Verification</h2>
                <p>Add meta tag to your website</p>
                <?php if ($has_code_submitted): ?>
                    <span class="status-badge submitted"><i class="fas fa-check"></i> Submitted</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="method-form">
                    <input type="hidden" name="verification_method" value="code">
                    <div class="form-group">
                        <label><i class="fas fa-code"></i> Generated Meta Tag</label>
                        <div class="meta-tag-display"><?php echo htmlspecialchars($generated_meta_tag); ?></div>
                        <button type="button" class="btn-copy" onclick="copyMetaTag()">
                            <i class="fas fa-copy"></i> Copy Meta Tag
                        </button>
                        <div class="help-text">Copy this meta tag and paste it into your website's &lt;head&gt; section.</div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-globe"></i> Website URL</label>
                        <input type="text" name="website_url" 
                               placeholder="https://yourwebsite.com" 
                               value="<?php echo htmlspecialchars($verification['verification_url'] ?? ''); ?>"
                               <?php echo $has_code_submitted ? 'disabled' : ''; ?>>
                        <div class="help-text">Enter your website URL where you placed the meta tag.</div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Meta Tag Code</label>
                        <textarea name="meta_code" rows="3" 
                                  placeholder="Paste the exact meta tag code you added to your website"
                                  <?php echo $has_code_submitted ? 'disabled' : ''; ?>><?php echo htmlspecialchars($verification['verification_code'] ?? ''); ?></textarea>
                        <div class="help-text">Paste the exact meta tag code you added to your website.</div>
                    </div>
                    
                    <?php if (!$has_code_submitted): ?>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Meta Tag Verification
                        </button>
                    <?php else: ?>
                        <div class="submitted-info">
                            <i class="fas fa-check-circle"></i> Already submitted. Waiting for review.
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Request Review Button (only when both methods submitted) -->
        <?php if ($both_submitted && !$is_pending): ?>
            <div class="apply-card review-card">
                <div class="card-body" style="text-align: center;">
                    <div class="review-icon">✅</div>
                    <h3>Both verification methods submitted!</h3>
                    <p>Your application is ready for admin review. Click below to request verification.</p>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="request_review">
                        <button type="submit" class="btn-primary btn-large">
                            <i class="fas fa-shield-alt"></i> Request Identity Verification Review
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Back to Dashboard button for unverified users -->
        <div class="form-actions">
            <a href="<?php echo BASE_URL; ?>/devhub/index.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
    <?php endif; ?>
</div>

<!-- Toast Container -->
<div id="toastContainer"></div>

<script>
    function copyMetaTag() {
        const metaTag = '<?php echo addslashes($generated_meta_tag); ?>';
        navigator.clipboard.writeText(metaTag).then(function() {
            showToast('Meta tag copied to clipboard!', 'success');
        });
    }
    
    function showToast(message, type) {
        const container = document.getElementById('toastContainer');
        container.innerHTML = '';
        const toast = document.createElement('div');
        toast.className = 'toast-notification toast-' + type;
        toast.innerHTML = '<div class="toast-content"><i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i><span>' + message + '</span></div>';
        container.appendChild(toast);
        
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 5000);
    }
    
    <?php if ($success): ?>
    showToast('<?php echo addslashes($success_message); ?>', 'success');
    <?php elseif ($error): ?>
    showToast('<?php echo addslashes($error); ?>', 'error');
    <?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
