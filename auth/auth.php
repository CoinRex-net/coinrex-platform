<?php
/**
 * CoinRex Authentication Page (Login + Register)
 * Location: /coinrex/auth/auth.php
 */

// Start output buffering to prevent header issues
ob_start();

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

function normalizeRedirectTarget($target) {
    if (empty($target) || !is_string($target)) {
        return '';
    }
    $target = trim($target);
    if (strpos($target, BASE_URL) === 0) {
        $target = substr($target, strlen(BASE_URL));
    }
    if (strpos($target, '/') !== 0) {
        return '';
    }
    return $target;
}

$redirect_to = normalizeRedirectTarget($_GET['redirect'] ?? $_POST['redirect_to'] ?? '');

// Check if already logged in
if (isLoggedIn()) {
    if ($redirect_to !== '') {
        redirect(BASE_URL . $redirect_to);
    }
    redirect(BASE_URL . '/public/dashboard.php');
}

$active_tab = isset($_GET['tab']) && $_GET['tab'] == 'register' ? 'register' : 'login';
$error = '';
$success = consumeFlashMessage('auth_success');
$is_register_submission = $_SERVER['REQUEST_METHOD'] === 'POST'
    && ((isset($_POST['form_action']) && $_POST['form_action'] === 'register') || isset($_POST['register']));
$is_login_submission = $_SERVER['REQUEST_METHOD'] === 'POST'
    && ((isset($_POST['form_action']) && $_POST['form_action'] === 'login') || isset($_POST['login']));
$referral_from_link = '';

if (isset($_GET['ref']) || isset($_GET['referral']) || isset($_GET['referral_code'])) {
    $referral_from_link = strtoupper(sanitize($_GET['ref'] ?? $_GET['referral'] ?? $_GET['referral_code'] ?? ''));
    if ($referral_from_link !== '') {
        $active_tab = 'register';
    }
}

$show_login_feature = featureIsVisible('login');
$show_registration_feature = featureIsVisible('registration');
$show_rexlink_auth_feature = featureIsVisible('rexlink_auth');
$rexlink_auth_accessible = featureIsAccessible('rexlink_auth');
if ($active_tab === 'register') {
    requireFeatureAccess('registration');
} else {
    requireFeatureAccess('login');
}

$register_fullname = $is_register_submission ? sanitize($_POST['fullname'] ?? '') : '';
$register_email = $is_register_submission ? normalizeEmail($_POST['email'] ?? '') : '';
$register_referral = $is_register_submission
    ? normalizeReferralCode($_POST['referral_code'] ?? '')
    : $referral_from_link;
$flash_login_email = consumeFlashMessage('auth_login_email');
$login_email = $is_login_submission
    ? normalizeEmail($_POST['email'] ?? '')
    : normalizeEmail($flash_login_email);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check_email'])) {
    $email = normalizeEmail($_GET['email'] ?? '');
    $response = [
        'valid' => false,
        'exists' => false,
        'disposable' => false,
        'message' => 'Please enter a valid email address',
    ];

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if (isDisposableEmail($email)) {
            $response = [
                'valid' => true,
                'exists' => false,
                'disposable' => true,
                'message' => 'Temporary email addresses are not allowed',
            ];
        } else {
            $email_exists = getUserByEmail($email) ? true : false;
            $response = [
                'valid' => true,
                'exists' => $email_exists,
                'disposable' => false,
                'message' => $email_exists ? 'This email is already registered' : 'Email is available',
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check_referral'])) {
    $referral_code = normalizeReferralCode($_GET['referral_code'] ?? $_GET['code'] ?? '');
    $validation = validateReferralCode($referral_code);

    header('Content-Type: application/json');
    echo json_encode([
        'valid' => $validation['valid'],
        'exists' => $validation['exists'],
        'message' => $validation['message'],
        'code' => $validation['code'],
    ]);
    exit;
}

// Handle registration
if ($is_register_submission) {
    $active_tab = 'register';
    $full_name = sanitize($_POST['fullname']);
    $email = normalizeEmail($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $referral_code = !empty($_POST['referral_code']) ? normalizeReferralCode($_POST['referral_code']) : null;
    $registration_validation = validateReviewerRegistrationSubmission(
        $full_name,
        $email,
        $password,
        $confirm_password,
        $referral_code,
        isset($_POST['terms'])
    );
    
    // Validation
    if (!$registration_validation['valid']) {
        $error = $registration_validation['message'];
    } else {
        $result = registerUser(
            $registration_validation['full_name'],
            $registration_validation['email'],
            $password,
            $registration_validation['referral_code']
        );
        if ($result['success']) {
            setFlashMessage(
                'auth_success',
                $result['message'] . ' Welcome bonus: ' . $result['bonus'] . ' $REX! Please sign in with your new account.'
            );
            setFlashMessage('auth_login_email', $email);
            redirect(BASE_URL . '/auth/auth.php?tab=login');
        } else {
            $error = $result['message'];
        }
    }
}

// Handle login
if ($is_login_submission) {
    $active_tab = 'login';
    $email = normalizeEmail($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter email and password';
    } else {
        $result = loginUser($email, $password, $remember);
        if ($result['success']) {
            redirect(BASE_URL . '/public/dashboard.php');
        } elseif (!empty($result['requires_verification'])) {
            setFlashMessage('verify_info', $result['message']);
            setFlashMessage('verify_email', $result['email'] ?? $email);
            redirect($result['redirect_url'] ?? (BASE_URL . '/auth/verify_email.php'));
        } else {
            $error = $result['message'];
        }
    }
}

// Now include header AFTER all PHP processing
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- Auth Page Styles -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/auth.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/rexlink-auth.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/rexlink-auth.css'); ?>">

<main class="auth-main auth-main-split">
    <div class="auth-container auth-shell">
        <div class="auth-bg-decoration" aria-hidden="true">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="gradient-orb orb-3"></div>
        </div>

        <div class="auth-card auth-split-card">
            <section class="auth-panel">
                <div class="auth-logo">
                    <img src="<?php echo ASSETS_URL; ?>/images/shield-logo.png" alt="CoinRex" class="auth-logo-img auth-shield-mark">
                    <p class="auth-tagline"><?php echo SITE_TAGLINE; ?></p>
                </div>

                <?php if($error): ?>
                <div class="auth-message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <?php if($success): ?>
                <div class="auth-message success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <?php endif; ?>

                <div class="auth-tabs">
                    <?php if ($show_login_feature): ?>
                    <button class="auth-tab <?php echo $active_tab == 'login' ? 'active' : ''; ?>" data-tab="login">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Sign In</span>
                    </button>
                    <?php endif; ?>
                    <?php if ($show_registration_feature): ?>
                    <button class="auth-tab <?php echo $active_tab == 'register' ? 'active' : ''; ?>" data-tab="register">
                        <i class="fas fa-user-plus"></i>
                        <span>Register</span>
                    </button>
                    <?php endif; ?>
                    <div class="auth-tab-slider"></div>
                </div>

                <div class="auth-form-container <?php echo $active_tab == 'login' ? 'active' : ''; ?>" id="loginForm">
                    <form method="POST" class="auth-form" id="loginAuthForm">
                        <input type="hidden" name="form_action" value="login">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_to, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="input-field">
                                <div class="input-control">
                                    <input type="email" name="email" id="loginEmail" value="<?php echo htmlspecialchars($login_email, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" required>
                                    <label for="loginEmail">Email Address</label>
                                    <span class="input-border"></span>
                                </div>
                            </div>
                        </div>

                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="input-field">
                                <div class="input-control">
                                    <input type="password" name="password" id="loginPassword" required>
                                    <label for="loginPassword">Password</label>
                                    <span class="input-border"></span>
                                    <button type="button" class="password-toggle" data-target="loginPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-options">
                            <label class="checkbox-label" title="Keep me signed in on this device for up to 10 days.">
                                <input type="checkbox" name="remember" id="rememberMeCheckbox" title="Keep me signed in on this device for up to 10 days.">
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">Remember me</span>
                            </label>
                            <a href="<?php echo BASE_URL; ?>/auth/forgot.php" class="forgot-link">Forgot password?</a>
                        </div>

                        <button type="submit" name="login" class="auth-submit">
                            <span>Sign In</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </form>

                    <?php if ($show_rexlink_auth_feature): ?>
                    <div class="rex-auth-divider"><span>or</span></div>
                    <button type="button" class="rex-auth-button rex-signer-auth-trigger <?php echo $rexlink_auth_accessible ? '' : 'is-soon'; ?>" id="rexSignerAuthButton" <?php echo $rexlink_auth_accessible ? '' : 'disabled aria-disabled="true"'; ?>>
                        <span class="rex-auth-button-main">
                            <span class="rex-auth-lion" aria-hidden="true">🦁</span>
                            <span>Sign in with RexLink</span>
                        </span>
                        <?php if (!$rexlink_auth_accessible): ?><span class="rex-auth-soon-badge">Coming Soon</span><?php endif; ?>
                    </button>
                    <?php endif; ?>
                </div>

                <div class="auth-form-container <?php echo $active_tab == 'register' ? 'active' : ''; ?>" id="registerForm">
                    <form method="POST" class="auth-form" id="registerAuthForm">
                        <input type="hidden" name="form_action" value="register">
                        <input type="hidden" name="device_fingerprint" id="deviceFingerprintField" value="">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_to, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="register-grid">
                            <div class="register-column">
                                <div class="input-group">
                                    <div class="input-icon">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                    <div class="input-field">
                                        <div class="input-control">
                                            <input type="text" name="fullname" id="regFullname" value="<?php echo htmlspecialchars($register_fullname, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="name" required>
                                            <label for="regFullname">Full Name</label>
                                            <span class="input-border"></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="input-group">
                                    <div class="input-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="input-field">
                                        <div class="input-control">
                                            <input type="email" name="email" id="regEmail" value="<?php echo htmlspecialchars($register_email, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" required>
                                            <label for="regEmail">Email Address</label>
                                            <span class="input-border"></span>
                                        </div>
                                        <div class="field-feedback" id="emailFeedback" aria-live="polite"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="register-column">
                                <div class="input-group">
                                    <div class="input-icon">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                    <div class="input-field password-field" id="passwordFieldGroup">
                                        <div class="input-control">
                                            <input type="password" name="password" id="regPassword" autocomplete="new-password" required>
                                            <label for="regPassword">Password</label>
                                            <span class="input-border"></span>
                                            <button type="button" class="password-toggle" data-target="regPassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="password-checklist" id="passwordChecklist" aria-live="polite">
                                            <div class="checklist-title">Password must include:</div>
                                            <div class="checklist-item" data-rule="length">
                                                <i class="fas fa-circle"></i>
                                                <span>At least 9 characters</span>
                                            </div>
                                            <div class="checklist-item" data-rule="uppercase">
                                                <i class="fas fa-circle"></i>
                                                <span>One capital letter</span>
                                            </div>
                                            <div class="checklist-item" data-rule="digit">
                                                <i class="fas fa-circle"></i>
                                                <span>At least one digit</span>
                                            </div>
                                            <div class="checklist-item" data-rule="special">
                                                <i class="fas fa-circle"></i>
                                                <span>One special character</span>
                                            </div>
                                        </div>
                                        <div class="field-feedback" id="passwordFeedback" aria-live="polite"></div>
                                    </div>
                                </div>

                                <div class="input-group">
                                    <div class="input-icon">
                                        <i class="fas fa-shield-alt"></i>
                                    </div>
                                    <div class="input-field">
                                        <div class="input-control">
                                            <input type="password" name="confirm_password" id="regConfirmPassword" autocomplete="new-password" required>
                                            <label for="regConfirmPassword">Confirm Password</label>
                                            <span class="input-border"></span>
                                            <button type="button" class="password-toggle" data-target="regConfirmPassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="field-feedback" id="confirmFeedback" aria-live="polite"></div>
                                    </div>
                                </div>

                                <div class="input-group">
                                    <div class="input-icon">
                                        <i class="fas fa-gift"></i>
                                    </div>
                                    <div class="input-field">
                                        <div class="input-control">
                                            <input type="text" name="referral_code" id="regReferral" value="<?php echo htmlspecialchars($register_referral, ENT_QUOTES, 'UTF-8'); ?>">
                                            <label for="regReferral">Referral Code (Optional)</label>
                                            <span class="input-border"></span>
                                        </div>
                                        <div class="field-feedback" id="referralFeedback" aria-live="polite"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-options register-terms">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms" id="registerTermsCheckbox" <?php echo $is_register_submission && isset($_POST['terms']) ? 'checked' : ''; ?> required>
                                <span class="checkbox-custom"></span>
                                <span class="checkbox-text">I agree to the <a href="../public/terms.php">Terms of Service</a></span>
                            </label>
                        </div>

                        <div class="register-action">
                            <button type="submit" name="register" class="auth-submit" id="registerSubmitButton" <?php echo $is_register_submission && isset($_POST['terms']) ? '' : 'disabled'; ?>>
                                <span>Create Account</span>
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>

                        <?php
                        $db_auth = getDBConnection();
                        $is_early_airdrop = isEarlyAirdropActive($db_auth);
                        ?>
                        <div class="auth-info">
                            <i class="fas fa-star"></i>
                            <?php if ($is_early_airdrop): ?>
                            <span>Get <strong><?php echo number_format(EARLY_AIRDROP_SIGNUP_BONUS); ?></strong> $REX welcome bonus + <strong><?php echo number_format(EARLY_AIRDROP_REFERRAL_BONUS); ?></strong> $REX with referral code!</span>
                            <?php else: ?>
                            <span>Get 10 $REX welcome bonus + 5 $REX with referral code!</span>
                            <?php endif; ?>
                        </div>
                    </form>

                    <?php if ($show_rexlink_auth_feature): ?>
                    <div class="rex-auth-divider"><span>or</span></div>
                    <button type="button" class="rex-auth-button rex-signer-auth-trigger <?php echo $rexlink_auth_accessible ? '' : 'is-soon'; ?>" <?php echo $rexlink_auth_accessible ? '' : 'disabled aria-disabled="true"'; ?>>
                        <span class="rex-auth-button-main">
                            <span class="rex-auth-lion" aria-hidden="true">🦁</span>
                            <span>Sign in with RexLink</span>
                        </span>
                        <?php if (!$rexlink_auth_accessible): ?><span class="rex-auth-soon-badge">Coming Soon</span><?php endif; ?>
                    </button>
                    <?php endif; ?>
                </div>
            </section>

            <aside class="auth-story auth-visual-zone" aria-label="CoinRex network snapshot">
                <div class="auth-network-scene">
                    <div class="auth-network-grid" aria-hidden="true"></div>
                    <div class="auth-chain-line auth-chain-line-a" aria-hidden="true"></div>
                    <div class="auth-chain-line auth-chain-line-b" aria-hidden="true"></div>
                    <div class="auth-chain-line auth-chain-line-c" aria-hidden="true"></div>

                    <div class="auth-network-node auth-node-a" aria-hidden="true"></div>
                    <div class="auth-network-node auth-node-b" aria-hidden="true"></div>
                    <div class="auth-network-node auth-node-c" aria-hidden="true"></div>
                    <div class="auth-network-node auth-node-d" aria-hidden="true"></div>

                    <div class="auth-core-snapshot">
                        <img src="<?php echo ASSETS_URL; ?>/images/circle-icon.png" alt="CoinRex" class="auth-core-logo">
                    </div>

                    <div class="auth-floating-shot auth-shot-proof">
                        <i class="fas fa-shield-halved"></i>
                        <span>Proof</span>
                    </div>

                    <div class="auth-floating-shot auth-shot-review">
                        <i class="fas fa-star"></i>
                        <span>Review</span>
                    </div>

                    <div class="auth-floating-shot auth-shot-reward">
                        <i class="fas fa-coins"></i>
                        <span>$REX</span>
                    </div>

                    <div class="auth-floating-shot auth-shot-rexlink">
                        <img src="<?php echo ASSETS_URL; ?>/images/rexlink-logo.png" alt="RexLink">
                        <span>RexLink</span>
                    </div>

                    <div class="auth-mini-panel auth-mini-panel-top">
                        <span class="auth-mini-dot"></span>
                        <strong>Verified</strong>
                    </div>

                    <div class="auth-mini-panel auth-mini-panel-bottom">
                        <img src="<?php echo ASSETS_URL; ?>/images/shield-logo.png" alt="">
                        <strong>Secure</strong>
                    </div>
                </div>

                <div class="auth-visual-ticker" aria-label="CoinRex activity snapshots">
                    <span>Proof Uploaded</span>
                    <span>Review Approved</span>
                    <span>Reward Queued</span>
                </div>
            </aside>
        </div>
    </div>
</main>

<div class="rexlink-modal" id="rexLinkModal" role="dialog" aria-modal="true" aria-labelledby="rexLinkModalTitle" hidden>
    <div class="rexlink-backdrop" id="rexLinkModalBackdrop"></div>
    <div class="rexlink-dialog">
        <div class="rexlink-head">
            <div>
                <span class="rexlink-tag"><i class="fas fa-link"></i> RexLink</span>
                <h3 id="rexLinkModalTitle">Sign in with RexLink</h3>
            </div>
            <button type="button" class="rexlink-close" id="rexLinkModalClose" aria-label="Close RexLink sign in">&times;</button>
        </div>
        <div class="rexlink-progress" aria-label="RexLink sign in progress">
            <span id="rexLinkProgressDuration" class="is-active">1. Scan QR</span>
            <span id="rexLinkProgressSuccess">2. Connected</span>
        </div>
        <div class="rexlink-body">
            <section class="rexlink-step is-active" id="rexLinkQrStep">
                <div class="rexlink-link-grid">
                    <div class="rexlink-copy">
                        <div class="rexlink-link-title">
                            <div>
                                <h4>Link this browser</h4>
                            </div>
                            <div class="rexlink-countdown" id="rexLinkCountdown">Waiting for code</div>
                        </div>
                        <p class="rexlink-session-note" id="rexLinkSessionNote">You'll be paired with CoinRex for 10 minutes after linking.</p>
                        <ul class="rexlink-guide">
                            <li><i class="fas fa-mobile-screen-button"></i><span>Open RexLink app.</span></li>
                            <li><i class="fas fa-qrcode"></i><span>Scan QR or enter this 6 digit code.</span></li>
                        </ul>
                        <div class="rexlink-link-actions">
                            <button type="button" class="rexlink-primary" id="rexLinkPrimaryButton" hidden>Generate New QR</button>
                        </div>
                    </div>
                    <div class="rexlink-qr-card">
                        <div class="rexlink-qr-stage">
                            <div class="rexlink-qr-placeholder" id="rexLinkQrPlaceholder" aria-label="RexLink QR is loading"></div>
                            <img id="rexLinkQrImage" alt="RexLink pairing QR" hidden>
                            <span class="rexlink-qr-logo-badge" id="rexLinkQrLogoBadge" aria-hidden="true">
                                <img src="<?php echo ASSETS_URL; ?>/images/favicon.png" alt="">
                            </span>
                        </div>
                        <div class="rexlink-code-row">
                            <strong class="rexlink-code" id="rexLinkPairingCode">No code yet</strong>
                            <button type="button" class="rexlink-copy-button" id="rexLinkCopyCodeButton" title="Copy code" aria-label="Copy RexLink code" disabled>
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <p class="rexlink-status" id="rexLinkStatus">Waiting for RexLink.</p>
            </section>

            <section class="rexlink-step" id="rexLinkSuccessStep">
                <div class="rexlink-success">
                    <div>
                        <div class="rexlink-success-icon"><i class="fas fa-check"></i></div>
                        <h4>RexLink connected</h4>
                        <p id="rexLinkSuccessMessage">Signing you in to CoinRex...</p>
                        <p class="rexlink-session-note" id="rexLinkSuccessCountdown">RexLink session is active.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="<?php echo ASSETS_URL; ?>/js/qrcode-browser.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/qrcode-browser.js'); ?>"></script>
<script src="<?php echo ASSETS_URL; ?>/js/rexlink-pairing.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/rexlink-pairing.js'); ?>"></script>
<script>
window.CoinRexAuthConfig = {
    rexlinkApiBaseUrl: <?php echo json_encode(BASE_URL); ?>,
    authRedirectTo: <?php echo json_encode($redirect_to !== '' ? BASE_URL . $redirect_to : BASE_URL . '/public/dashboard.php'); ?>,
    rexLinkReferralCode: <?php echo json_encode((string) $register_referral); ?>,
    rexLinkAuthAccessible: <?php echo $rexlink_auth_accessible ? 'true' : 'false'; ?>,
    baseUrl: <?php echo json_encode(BASE_URL); ?>,
    browserBaseUrl: window.location.origin + <?php echo json_encode(BASE_URI); ?>
};
</script>
<script src="<?php echo ASSETS_URL; ?>/js/rexlink-auth.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/rexlink-auth.js'); ?>"></script>

<?php 
require_once dirname(__DIR__) . '/includes/footer.php';
// End output buffering and flush
ob_end_flush();
?>
