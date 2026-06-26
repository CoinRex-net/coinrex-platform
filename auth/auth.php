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
<style>
.rex-auth-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 14px 0;
    color: rgba(185, 199, 232, .82);
    font-size: 11px;
    font-weight: 900;
    text-transform: uppercase;
}
.rex-auth-divider::before,
.rex-auth-divider::after {
    content: "";
    height: 1px;
    flex: 1;
    background: rgba(148, 163, 184, .16);
}
.rex-auth-button {
    width: 100%;
    min-height: 44px;
    border: 1px solid rgba(148, 163, 184, .18);
    border-radius: 10px;
    background: rgba(2, 6, 23, .28);
    color: #dbeafe;
    font-weight: 950;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    transition: border-color .2s ease, background .2s ease, transform .2s ease;
}
.rex-auth-button:hover {
    border-color: rgba(250, 204, 21, .42);
    background: rgba(212, 175, 55, .08);
    color: #facc15;
    transform: none;
}
.rex-auth-button:disabled {
    cursor: not-allowed;
    opacity: .65;
}
.rexlink-modal[hidden] {
    display: none;
}
.rexlink-modal {
    position: fixed;
    inset: 0;
    z-index: 1800;
    display: grid;
    place-items: center;
    padding: 14px;
}
.rexlink-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(2, 6, 23, .78);
    backdrop-filter: blur(7px);
}
.rexlink-dialog {
    position: relative;
    width: min(860px, 100%);
    border: 1px solid rgba(148, 163, 184, .18);
    border-radius: 12px;
    background:
        radial-gradient(circle at 16% 0%, rgba(250, 204, 21, .10), transparent 34%),
        linear-gradient(145deg, #101827, #0a1221 64%, #0f172a);
    box-shadow: 0 26px 80px rgba(0, 0, 0, .46);
    overflow: hidden;
    display: grid;
    grid-template-rows: auto auto auto auto;
}
.rexlink-head {
    padding: 16px 20px 10px;
    border-bottom: 1px solid rgba(148, 163, 184, .12);
    display: flex;
    justify-content: space-between;
    gap: 14px;
    align-items: flex-start;
}
.rexlink-tag {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    color: #facc15;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
}
.rexlink-head h3 {
    margin: 3px 0 0;
    color: #f8fafc;
    font-size: clamp(1.16rem, 2.2vw, 1.5rem);
}
.rexlink-close {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    border: 1px solid rgba(148, 163, 184, .18);
    background: rgba(30, 41, 59, .92);
    color: #f8fafc;
    cursor: pointer;
    flex: 0 0 auto;
    font-size: 22px;
    line-height: 1;
}
.rexlink-progress {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
    padding: 0 18px 10px;
    border-bottom: 1px solid rgba(148, 163, 184, .12);
}
.rexlink-progress span {
    border-radius: 8px;
    background: rgba(148, 163, 184, .10);
    color: #91a4bd;
    font-size: 12px;
    font-weight: 900;
    text-align: center;
    padding: 7px 8px;
}
.rexlink-progress span.is-active {
    background: rgba(250, 204, 21, .14);
    color: #facc15;
}
.rexlink-progress span.is-complete {
    background: rgba(34, 197, 94, .14);
    color: #86efac;
}
.rexlink-body {
    overflow: visible;
    padding: 16px 20px 20px;
}
.rexlink-step {
    display: none;
}
.rexlink-step.is-active {
    display: grid;
    gap: 10px;
}
.rexlink-link-grid {
    display: grid;
    grid-template-columns: minmax(220px, .72fr) minmax(340px, 1.28fr);
    gap: 18px;
    align-items: stretch;
}
.rexlink-link-grid > .rexlink-copy {
    border: 1px solid rgba(148, 163, 184, .14);
    border-radius: 16px;
    padding: 13px;
    background:
        radial-gradient(circle at top left, rgba(250, 204, 21, .12), transparent 34%),
        rgba(8, 17, 32, .62);
    display: grid;
    align-content: start;
    gap: 10px;
}
.rexlink-copy h4,
.rexlink-success h4 {
    margin: 0;
    color: #f8fafc;
    font-size: 1.05rem;
}
.rexlink-copy p,
.rexlink-success p,
.rexlink-guide li,
.rexlink-status {
    margin: 4px 0 0;
    color: #b9c7e8;
    font-size: 13px;
    line-height: 1.35;
}
.rexlink-link-title {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.rexlink-guide {
    margin: 2px 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 6px;
}
.rexlink-guide li {
    display: flex;
    gap: 8px;
    align-items: flex-start;
}
.rexlink-guide i {
    color: #facc15;
    margin-top: 2px;
}
.rexlink-link-actions {
    display: flex;
    justify-content: flex-start;
}
.rexlink-qr-card {
    width: min(100%, 390px);
    justify-self: center;
    border-radius: 16px;
    padding: 12px;
    border: 1px solid rgba(250, 204, 21, .32);
    background: linear-gradient(145deg, rgba(13, 27, 52, .98), rgba(8, 17, 32, .94));
    box-shadow: 0 22px 48px rgba(2, 6, 23, .38);
    display: grid;
    place-items: center;
    gap: 8px;
}
.rexlink-qr-stage {
    position: relative;
    width: min(100%, 268px);
    aspect-ratio: 1;
    margin: 0 auto;
    border-radius: 16px;
    display: grid;
    place-items: center;
    background: rgba(2, 6, 23, .86);
    border: 1px solid rgba(147, 197, 253, .20);
}
.rexlink-qr-stage img,
.rexlink-qr-placeholder {
    width: 232px;
    height: 232px;
    border-radius: 8px;
}
.rexlink-qr-stage img {
    display: block;
    background: #fff;
    padding: 8px;
}
.rexlink-qr-stage img[hidden],
.rexlink-qr-placeholder[hidden] {
    display: none !important;
}
.rexlink-qr-placeholder {
    border: 1px dashed rgba(212, 175, 55, .36);
    display: grid;
    place-items: center;
    text-align: center;
    color: #b9c7e8;
    padding: 18px;
}
.rexlink-qr-placeholder::before {
    content: "";
    width: 42px;
    height: 42px;
    border-radius: 14px;
    border: 2px solid rgba(250, 204, 21, .18);
    border-top-color: rgba(250, 204, 21, .82);
    animation: rexlinkSpin .9s linear infinite;
}
.rexlink-qr-placeholder.is-rendered {
    border-style: solid;
    background: #fff;
    padding: 8px;
}
.rexlink-qr-placeholder.is-rendered::before {
    display: none;
}
.rexlink-qr-placeholder svg {
    display: block;
    width: 232px;
    height: 232px;
}
.rexlink-expired-placeholder {
    min-height: 100%;
    display: grid;
    place-items: center;
    align-content: center;
    gap: 8px;
    color: #fca5a5;
}
.rexlink-expired-placeholder i {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    background: rgba(239, 68, 68, .12);
    border: 1px solid rgba(248, 113, 113, .34);
    color: #f87171;
    font-size: 18px;
}
.rexlink-expired-placeholder strong {
    color: #f8fafc;
    font-size: 15px;
    font-weight: 900;
}
.rexlink-expired-placeholder small {
    color: #b9c7e8;
    font-size: 12px;
    line-height: 1.35;
    max-width: 170px;
}
.rexlink-qr-logo-badge {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: none;
    align-items: center;
    justify-content: center;
    background: #081120;
    border: 4px solid #fff;
    pointer-events: none;
    z-index: 2;
}
.rexlink-qr-logo-badge.is-visible {
    display: flex;
}
.rexlink-qr-logo-badge img {
    width: 35px;
    height: 35px;
    object-fit: contain;
    padding: 0;
    background: transparent;
    border-radius: 0;
}
.rexlink-code-row {
    margin-top: 0;
    display: inline-grid;
    grid-template-columns: minmax(0, auto) 36px;
    align-items: center;
    gap: 6px;
    justify-content: center;
    max-width: 100%;
}
.rexlink-code {
    min-height: 36px;
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid rgba(250, 204, 21, .34);
    background: rgba(212, 175, 55, .10);
    color: #facc15;
    font-size: clamp(16px, 2.3vw, 21px);
    font-weight: 950;
    letter-spacing: .08em;
    text-align: center;
    overflow-wrap: anywhere;
}
.rexlink-copy-button {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: 1px solid rgba(250, 204, 21, .32);
    background: rgba(212, 175, 55, .10);
    color: #facc15;
    cursor: pointer;
    display: inline-grid;
    place-items: center;
}
.rexlink-countdown {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    margin: 0;
    padding: 5px 9px;
    border: 1px solid rgba(34, 197, 94, .26);
    border-radius: 8px;
    background: rgba(34, 197, 94, .10);
    color: #86efac;
    font-size: 11px;
    font-weight: 900;
    white-space: nowrap;
}
.rexlink-session-note {
    margin: 8px 0 0;
    color: #91a4bd;
    font-size: 12px;
    font-weight: 800;
}
.rexlink-status {
    min-height: 18px;
    margin-top: 0;
}
.rexlink-status.is-error {
    color: #fca5a5;
}
.rexlink-status.is-success {
    color: #86efac;
}
.rexlink-success {
    min-height: 240px;
    display: grid;
    place-items: center;
    text-align: center;
}
.rexlink-success-icon {
    width: 78px;
    height: 78px;
    border-radius: 22px;
    margin: 0 auto 14px;
    display: grid;
    place-items: center;
    background: rgba(34, 197, 94, .14);
    color: #86efac;
    font-size: 32px;
    animation: rexlinkPop .65s ease both;
}
@keyframes rexlinkPop {
    0% { transform: scale(.72); opacity: 0; }
    70% { transform: scale(1.08); opacity: 1; }
    100% { transform: scale(1); opacity: 1; }
}
@keyframes rexlinkSpin {
    to { transform: rotate(360deg); }
}
.rexlink-secondary,
.rexlink-primary {
    min-height: 38px;
    border-radius: 8px;
    padding: 0 14px;
    font-weight: 900;
    cursor: pointer;
}
.rexlink-secondary {
    border: 1px solid rgba(148, 163, 184, .18);
    background: rgba(30, 41, 59, .72);
    color: #f8fafc;
}
.rexlink-primary {
    border: 1px solid rgba(250, 204, 21, .36);
    background: rgba(212, 175, 55, .13);
    color: #facc15;
}
.rexlink-primary:disabled,
.rexlink-secondary:disabled {
    cursor: not-allowed;
    opacity: .58;
}
@media (max-width: 720px) {
    .rexlink-link-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    .rexlink-link-grid > .rexlink-copy {
        order: 2;
        padding: 10px;
        gap: 7px;
    }
    .rexlink-qr-card {
        order: 1;
        width: min(100%, 430px);
        justify-self: center;
        display: grid;
        justify-items: center;
    }
    .rexlink-dialog {
        width: min(100%, 560px);
        max-height: calc(100dvh - 12px);
    }
    .rexlink-progress {
        padding-inline: 14px;
    }
    .rexlink-body,
    .rexlink-head {
        padding-inline: 14px;
    }
}
@media (max-width: 520px) {
    .rexlink-modal {
        padding: 6px;
    }
    .rexlink-head {
        padding: 10px 12px 7px;
    }
    .rexlink-tag {
        font-size: 11px;
    }
    .rexlink-head h3 {
        font-size: 1.05rem;
    }
    .rexlink-close {
        width: 34px;
        height: 34px;
    }
    .rexlink-progress {
        padding: 0 14px 8px;
        gap: 5px;
    }
    .rexlink-progress span {
        font-size: 11px;
        padding: 6px 5px;
    }
    .rexlink-body {
        padding: 10px 12px 12px;
    }
    .rexlink-link-title {
        display: grid;
        gap: 6px;
    }
    .rexlink-link-title h4 {
        font-size: .95rem;
    }
    .rexlink-session-note {
        margin-top: 2px;
        font-size: 11px;
        line-height: 1.25;
    }
    .rexlink-qr-stage {
        width: min(100%, 292px);
    }
    .rexlink-qr-stage img,
    .rexlink-qr-placeholder,
    .rexlink-qr-placeholder svg {
        width: 252px;
        height: 252px;
    }
    .rexlink-qr-card {
        padding: 10px;
        gap: 9px;
    }
    .rexlink-code-row {
        width: 100%;
        grid-template-columns: minmax(0, 1fr) 38px;
        justify-content: stretch;
    }
    .rexlink-code {
        width: 100%;
        min-height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }
    .rexlink-qr-logo-badge {
        width: 44px;
        height: 44px;
    }
    .rexlink-qr-logo-badge img {
        width: 30px;
        height: 30px;
    }
    .rexlink-guide {
        gap: 4px;
        margin-top: 3px;
    }
    .rexlink-guide li:nth-child(n+3) {
        display: none;
    }
    .rexlink-copy p,
    .rexlink-guide li,
    .rexlink-status {
        font-size: 11px;
    }
    .rexlink-status {
        min-height: 14px;
    }
}
</style>

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

                    <div class="rex-auth-divider"><span>or</span></div>
                    <button type="button" class="rex-auth-button rex-signer-auth-trigger" id="rexSignerAuthButton">
                        <span class="rex-auth-lion" aria-hidden="true">🦁</span>
                        <span>Sign in with RexLink</span>
                    </button>
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

                                <div class="register-action">
                                    <button type="submit" name="register" class="auth-submit" id="registerSubmitButton" <?php echo $is_register_submission && isset($_POST['terms']) ? '' : 'disabled'; ?>>
                                        <span>Create Account</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
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

                    <div class="rex-auth-divider"><span>or</span></div>
                    <button type="button" class="rex-auth-button rex-signer-auth-trigger">
                        <span class="rex-auth-lion" aria-hidden="true">🦁</span>
                        <span>Sign in with RexLink</span>
                    </button>
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
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.auth-tab');
    const loginContainer = document.getElementById('loginForm');
    const registerContainer = document.getElementById('registerForm');
    const slider = document.querySelector('.auth-tab-slider');
    const registerForm = document.getElementById('registerAuthForm');
    const deviceFingerprintField = document.getElementById('deviceFingerprintField');
    const rexSignerAuthButtons = Array.from(document.querySelectorAll('.rex-signer-auth-trigger'));
    const rexLinkModal = document.getElementById('rexLinkModal');
    const rexLinkModalBackdrop = document.getElementById('rexLinkModalBackdrop');
    const rexLinkModalClose = document.getElementById('rexLinkModalClose');
    const rexLinkQrStep = document.getElementById('rexLinkQrStep');
    const rexLinkSuccessStep = document.getElementById('rexLinkSuccessStep');
    const rexLinkProgressDuration = document.getElementById('rexLinkProgressDuration');
    const rexLinkProgressSuccess = document.getElementById('rexLinkProgressSuccess');
    const rexLinkQrPlaceholder = document.getElementById('rexLinkQrPlaceholder');
    const rexLinkQrImage = document.getElementById('rexLinkQrImage');
    const rexLinkQrLogoBadge = document.getElementById('rexLinkQrLogoBadge');
    const rexLinkPairingCode = document.getElementById('rexLinkPairingCode');
    const rexLinkCopyCodeButton = document.getElementById('rexLinkCopyCodeButton');
    const rexLinkCountdown = document.getElementById('rexLinkCountdown');
    const rexLinkSessionNote = document.getElementById('rexLinkSessionNote');
    const rexLinkStatus = document.getElementById('rexLinkStatus');
    const rexLinkSuccessMessage = document.getElementById('rexLinkSuccessMessage');
    const rexLinkSuccessCountdown = document.getElementById('rexLinkSuccessCountdown');
    const rexLinkPrimaryButton = document.getElementById('rexLinkPrimaryButton');
    const rexSignerCreatePairingUrl = <?php echo json_encode(BASE_URL . '/api/rex-signer/create_pairing.php'); ?>;
    const rexSignerPairingQrUrl = <?php echo json_encode(BASE_URL . '/api/rex-signer/pairing_qr.php'); ?>;
    const rexSignerLoginFromSessionUrl = <?php echo json_encode(BASE_URL . '/api/rex-signer/auth/login_from_session.php'); ?>;
    const rexSignerPublicBaseUrl = <?php echo json_encode(defined('PUBLIC_BASE_URL') ? PUBLIC_BASE_URL : BASE_URL); ?>;
    const authRedirectTo = <?php echo json_encode($redirect_to !== '' ? BASE_URL . $redirect_to : BASE_URL . '/public/dashboard.php'); ?>;
    const rexLinkReferralCode = <?php echo json_encode((string) $register_referral); ?>;
    let rexSignerAuthPollTimer = null;
    let rexLinkCountdownTimer = null;
    const rexLinkSelectedDuration = 10;
    let rexLinkRedirectTimer = null;
    let rexLinkAuthCompleted = false;
    let rexLinkStatusRequestInFlight = false;
    let rexLinkPairingRequestInFlight = false;
    function buildDeviceFingerprint() {
        const nav = window.navigator || {};
        const screenInfo = window.screen || {};
        const timezone = (Intl.DateTimeFormat && Intl.DateTimeFormat().resolvedOptions().timeZone) || '';
        const raw = [
            nav.userAgent || '',
            nav.language || '',
            (nav.languages || []).join(','),
            String(screenInfo.width || ''),
            String(screenInfo.height || ''),
            String(screenInfo.colorDepth || ''),
            timezone,
            String(new Date().getTimezoneOffset()),
            String(nav.hardwareConcurrency || ''),
            String(nav.platform || ''),
        ].join('|');

        let hash = 0;
        for (let i = 0; i < raw.length; i += 1) {
            hash = ((hash << 5) - hash) + raw.charCodeAt(i);
            hash |= 0;
        }

        return 'fp_' + Math.abs(hash).toString(16) + '_' + btoa(raw).slice(0, 24).replace(/[^a-zA-Z0-9]/g, '');
    }

    if (deviceFingerprintField) {
        deviceFingerprintField.value = buildDeviceFingerprint();
    }

    function rexLinkSetStatus(message, state) {
        if (!rexLinkStatus) {
            return;
        }
        rexLinkStatus.textContent = message;
        rexLinkStatus.classList.toggle('is-error', state === 'error');
        rexLinkStatus.classList.toggle('is-success', state === 'success');
    }

    function rexAuthPostJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body || {}),
        }).then(function(response) {
            return response.json();
        });
    }

    function rexAuthStopPolling() {
        if (rexSignerAuthPollTimer) {
            window.clearInterval(rexSignerAuthPollTimer);
            rexSignerAuthPollTimer = null;
        }
    }

    function rexLinkStopCountdown() {
        if (rexLinkCountdownTimer) {
            window.clearInterval(rexLinkCountdownTimer);
            rexLinkCountdownTimer = null;
        }
    }

    function rexLinkShortAddress(address) {
        const value = String(address || '');
        if (value.length <= 14) {
            return value;
        }
        return value.slice(0, 6) + '...' + value.slice(-4);
    }

    function rexLinkFormatClock(seconds) {
        const safeSeconds = Math.max(0, Number(seconds || 0));
        const minutes = Math.floor(safeSeconds / 60);
        const secs = String(safeSeconds % 60).padStart(2, '0');
        return minutes + ':' + secs;
    }

    function rexLinkSetStep(step) {
        [rexLinkQrStep, rexLinkSuccessStep].forEach(function(element) {
            if (element) {
                element.classList.remove('is-active');
            }
        });
        if (step === 'link' && rexLinkQrStep) {
            rexLinkQrStep.classList.add('is-active');
        }
        if (step === 'success' && rexLinkSuccessStep) {
            rexLinkSuccessStep.classList.add('is-active');
        }

        if (rexLinkProgressDuration) {
            rexLinkProgressDuration.classList.toggle('is-active', step === 'link');
            rexLinkProgressDuration.classList.toggle('is-complete', step === 'success');
        }
        if (rexLinkProgressSuccess) {
            rexLinkProgressSuccess.classList.toggle('is-active', step === 'success');
        }
        if (rexLinkPrimaryButton) {
            if (step !== 'link') {
                rexLinkPrimaryButton.hidden = true;
            }
        }
    }

    function rexLinkResetQr() {
        rexAuthStopPolling();
        rexLinkStopCountdown();
        rexLinkAuthCompleted = false;
        rexLinkStatusRequestInFlight = false;
        if (rexLinkRedirectTimer) {
            window.clearTimeout(rexLinkRedirectTimer);
            rexLinkRedirectTimer = null;
        }
        if (rexLinkQrImage) {
            rexLinkQrImage.hidden = true;
            rexLinkQrImage.onload = null;
            rexLinkQrImage.onerror = null;
            rexLinkQrImage.removeAttribute('src');
        }
        if (rexLinkQrPlaceholder) {
            rexLinkQrPlaceholder.hidden = false;
            rexLinkQrPlaceholder.classList.remove('is-rendered');
            rexLinkQrPlaceholder.innerHTML = '';
        }
        if (rexLinkQrLogoBadge) {
            rexLinkQrLogoBadge.classList.remove('is-visible');
        }
        if (rexLinkPairingCode) {
            rexLinkPairingCode.textContent = 'No code yet';
        }
        if (rexLinkCopyCodeButton) {
            rexLinkCopyCodeButton.disabled = true;
            rexLinkCopyCodeButton.innerHTML = '<i class="fas fa-copy"></i>';
        }
        if (rexLinkCountdown) {
            rexLinkCountdown.textContent = 'Waiting for code';
        }
        if (rexLinkSessionNote) {
            rexLinkSessionNote.textContent = "You'll be paired with CoinRex for 10 minutes after linking.";
        }
        if (rexLinkSuccessCountdown) {
            rexLinkSuccessCountdown.textContent = 'RexLink session is active.';
        }
        if (rexLinkPrimaryButton) {
            rexLinkPrimaryButton.hidden = true;
            rexLinkPrimaryButton.disabled = false;
            rexLinkPrimaryButton.textContent = 'Generate New QR';
        }
        rexLinkSetStatus('Creating RexLink code...', '');
    }

    function rexLinkOpenModal() {
        if (!rexLinkModal) {
            return;
        }
        rexLinkResetQr();
        rexLinkSetStep('link');
        rexLinkModal.hidden = false;
        document.body.style.overflow = 'hidden';
        rexLinkCreatePairing();
    }

    function rexLinkCloseModal() {
        rexAuthStopPolling();
        rexLinkStopCountdown();
        if (rexLinkRedirectTimer) {
            window.clearTimeout(rexLinkRedirectTimer);
            rexLinkRedirectTimer = null;
        }
        if (rexLinkModal) {
            rexLinkModal.hidden = true;
        }
        document.body.style.overflow = '';
    }

    function rexLinkShowExpired(message) {
        if (rexLinkAuthCompleted) {
            return;
        }
        rexAuthStopPolling();
        rexLinkStopCountdown();
        rexLinkSetStatus(message || 'This RexLink QR expired. Create a fresh code.', 'error');
        if (rexLinkCountdown) {
            rexLinkCountdown.textContent = 'QR expired';
        }
        if (rexLinkQrImage) {
            rexLinkQrImage.hidden = true;
            rexLinkQrImage.onload = null;
            rexLinkQrImage.onerror = null;
            rexLinkQrImage.removeAttribute('src');
        }
        if (rexLinkQrLogoBadge) {
            rexLinkQrLogoBadge.classList.remove('is-visible');
        }
        if (rexLinkQrPlaceholder) {
            rexLinkQrPlaceholder.hidden = false;
            rexLinkQrPlaceholder.classList.remove('is-rendered');
            rexLinkQrPlaceholder.innerHTML = '<span class="rexlink-expired-placeholder"><i class="fas fa-clock"></i><strong>QR expired</strong><small>Generate a new QR to keep pairing.</small></span>';
        }
        if (rexLinkPairingCode) {
            rexLinkPairingCode.textContent = 'QR expired';
        }
        if (rexLinkCopyCodeButton) {
            rexLinkCopyCodeButton.disabled = true;
            rexLinkCopyCodeButton.innerHTML = '<i class="fas fa-copy"></i>';
        }
        if (rexLinkPrimaryButton) {
            rexLinkPrimaryButton.hidden = false;
            rexLinkPrimaryButton.disabled = false;
            rexLinkPrimaryButton.textContent = 'Generate New QR';
        }
    }

    function rexLinkStartCountdown(seconds) {
        let remaining = Math.max(0, Number(seconds || 300));
        rexLinkStopCountdown();
        const updateCountdown = function() {
            const minutes = Math.floor(remaining / 60);
            const secs = String(remaining % 60).padStart(2, '0');
            if (rexLinkCountdown) {
                rexLinkCountdown.textContent = 'QR expires in ' + minutes + 'm ' + secs + 's';
            }
            if (remaining <= 0) {
                rexLinkShowExpired('This RexLink QR expired. Create a fresh code.');
                return;
            }
            remaining -= 1;
        };
        updateCountdown();
        rexLinkCountdownTimer = window.setInterval(updateCountdown, 1000);
    }

    function rexAuthPollStatus() {
        if (rexLinkAuthCompleted || rexLinkStatusRequestInFlight) {
            return;
        }
        rexLinkStatusRequestInFlight = true;
        rexAuthPostJson(rexSignerLoginFromSessionUrl, {})
            .then(function(data) {
                if (rexLinkAuthCompleted) {
                    return;
                }
                if (!data.success) {
                    throw new Error(data.message || 'Could not check RexLink status.');
                }
                const status = String(data.status || 'pending');
                if (status === 'authenticated') {
                    rexLinkAuthCompleted = true;
                    rexAuthStopPolling();
                    rexLinkStopCountdown();
                    window.clearTimeout(rexLinkRedirectTimer);
                    if (rexLinkSuccessMessage) {
                        const wallet = rexLinkShortAddress(data.wallet_address || data.wallet || '');
                        rexLinkSuccessMessage.textContent = wallet
                            ? 'Wallet ' + wallet + ' connected. Signing you in...'
                            : 'RexLink connected. Signing you in...';
                    }
                    if (rexLinkSuccessCountdown) {
                        rexLinkSuccessCountdown.textContent = 'RexLink session: ' + rexLinkFormatClock(data.session_remaining_seconds || 0) + ' remaining';
                    }
                    rexLinkSetStep('success');
                    rexLinkRedirectTimer = window.setTimeout(function() {
                        window.location.href = authRedirectTo || data.redirect_url;
                    }, 1200);
                    return;
                }
                if (status === 'expired' || status === 'revoked' || status === 'none') {
                    rexLinkShowExpired(status === 'expired' ? 'This RexLink QR expired. Create a fresh code.' : (data.message || 'This RexLink request is no longer active.'));
                    return;
                }
                rexLinkSetStatus('Waiting for RexLink to connect this browser.', '');
            })
            .catch(function(error) {
                if (rexLinkAuthCompleted) {
                    return;
                }
                rexLinkSetStatus(error.message || 'Could not check RexLink status.', 'error');
                if (rexLinkPrimaryButton) {
                    rexLinkPrimaryButton.hidden = false;
                }
            })
            .finally(function() {
                rexLinkStatusRequestInFlight = false;
            });
    }

    function rexLinkCompactQrPayload(qrPayload) {
        qrPayload = qrPayload && typeof qrPayload === 'object' ? qrPayload : {};
        return {
            type: qrPayload.type || 'coinrex.rex_signer.pairing',
            version: Number(qrPayload.version || 2),
            code: qrPayload.code || '',
            purpose: qrPayload.purpose || 'auth',
            api_base_url: String(qrPayload.api_base_url || rexSignerPublicBaseUrl || <?php echo json_encode(BASE_URL); ?>).replace(/\/+$/, ''),
            base_url: String(qrPayload.base_url || rexSignerPublicBaseUrl || <?php echo json_encode(BASE_URL); ?>).replace(/\/+$/, ''),
            dapp_name: qrPayload.dapp_name || 'CoinRex',
            dapp_url: qrPayload.dapp_url || <?php echo json_encode(BASE_URL); ?>,
            network_slug: qrPayload.network_slug || 'polygon-amoy',
            chain_id: Number(qrPayload.chain_id || 80002),
            requested_duration_minutes: Number(qrPayload.requested_duration_minutes || 10),
            expires_at: qrPayload.expires_at || '',
        };
    }

    function rexLinkRenderQrPayload(qrPayload) {
        if (!rexLinkQrPlaceholder || !qrPayload) {
            return;
        }

        const qrText = JSON.stringify(rexLinkCompactQrPayload(qrPayload));
        const renderFallbackImage = function() {
            if (!rexLinkQrImage) {
                return;
            }

            rexLinkQrImage.onload = function() {
                rexLinkQrPlaceholder.hidden = true;
                rexLinkQrPlaceholder.classList.remove('is-rendered');
                rexLinkQrPlaceholder.innerHTML = '';
                if (rexLinkQrLogoBadge) {
                    rexLinkQrLogoBadge.classList.add('is-visible');
                }
            };
            rexLinkQrImage.onerror = function() {
                rexLinkQrPlaceholder.hidden = false;
                rexLinkQrPlaceholder.classList.remove('is-rendered');
                rexLinkQrPlaceholder.innerHTML = '';
                if (rexLinkQrLogoBadge) {
                    rexLinkQrLogoBadge.classList.remove('is-visible');
                }
                rexLinkSetStatus('QR could not load. Use the code below.', 'error');
            };
            rexLinkQrImage.hidden = false;
            rexLinkQrImage.src = rexSignerPairingQrUrl + '?payload=' + encodeURIComponent(qrText);
        };

        if (window.CoinRexQRCode && typeof window.CoinRexQRCode.toString === 'function') {
            window.CoinRexQRCode.toString(qrText, {
                type: 'svg',
                width: 232,
                margin: 2,
                errorCorrectionLevel: 'M',
                color: {
                    dark: '#081120',
                    light: '#ffffff',
                },
            }).then(function(svg) {
                rexLinkQrPlaceholder.hidden = false;
                rexLinkQrPlaceholder.classList.add('is-rendered');
                rexLinkQrPlaceholder.innerHTML = svg;
                if (rexLinkQrImage) {
                    rexLinkQrImage.hidden = true;
                    rexLinkQrImage.removeAttribute('src');
                }
                if (rexLinkQrLogoBadge) {
                    rexLinkQrLogoBadge.classList.add('is-visible');
                }
            }).catch(renderFallbackImage);
            return;
        }

        renderFallbackImage();
    }

    function rexLinkCreatePairing() {
        if (rexLinkPairingRequestInFlight) {
            return;
        }
        rexLinkPairingRequestInFlight = true;
        rexLinkResetQr();
        rexLinkSetStep('link');
        rexLinkSetStatus('Creating RexLink code...', '');
        if (rexLinkPrimaryButton) {
            rexLinkPrimaryButton.hidden = false;
            rexLinkPrimaryButton.disabled = true;
            rexLinkPrimaryButton.textContent = 'Generating QR...';
        }

        rexAuthPostJson(rexSignerCreatePairingUrl, {
            purpose: 'auth',
            duration_minutes: 10,
            referral_code: rexLinkReferralCode,
            device_fingerprint: deviceFingerprintField ? deviceFingerprintField.value : '',
        }).then(function(data) {
            if (!data.success) {
                throw new Error(data.message || 'Could not create RexLink code.');
            }
            if (rexLinkPairingCode) {
                rexLinkPairingCode.textContent = data.display_code || 'Code ready';
            }
            if (data.qr_payload) {
                const qrPayload = Object.assign({}, data.qr_payload || {}, {
                    purpose: 'auth',
                    base_url: rexSignerPublicBaseUrl,
                    api_base_url: rexSignerPublicBaseUrl
                });
                rexLinkRenderQrPayload(qrPayload);
            }
            if (rexLinkQrPlaceholder) {
                rexLinkQrPlaceholder.hidden = !data.qr_payload;
            }
            if (rexLinkQrLogoBadge) {
                rexLinkQrLogoBadge.classList.remove('is-visible');
            }
            if (rexLinkCopyCodeButton) {
                rexLinkCopyCodeButton.disabled = !data.display_code;
            }
            if (rexLinkPrimaryButton) {
                rexLinkPrimaryButton.hidden = true;
                rexLinkPrimaryButton.disabled = false;
                rexLinkPrimaryButton.textContent = 'Generate New QR';
            }
            if (rexLinkSessionNote) {
                rexLinkSessionNote.textContent = "You'll be paired with CoinRex for 10 minutes after linking.";
            }
            rexLinkStartCountdown(data.expires_in_seconds || 300);
            rexLinkSetStatus('Open RexLink and connect with this QR or code.', '');
            rexLinkSetStep('link');
            rexSignerAuthPollTimer = window.setInterval(rexAuthPollStatus, 1000);
            rexAuthPollStatus();
        }).catch(function(error) {
            rexLinkSetStatus(error.message || 'RexLink sign-in could not start.', 'error');
            rexLinkSetStep('link');
            if (rexLinkQrPlaceholder) {
                rexLinkQrPlaceholder.hidden = false;
                rexLinkQrPlaceholder.classList.remove('is-rendered');
                rexLinkQrPlaceholder.innerHTML = '';
            }
            if (rexLinkQrLogoBadge) {
                rexLinkQrLogoBadge.classList.remove('is-visible');
            }
            if (rexLinkPrimaryButton) {
                rexLinkPrimaryButton.hidden = false;
                rexLinkPrimaryButton.disabled = false;
                rexLinkPrimaryButton.textContent = 'Generate New QR';
            }
        }).finally(function() {
            rexLinkPairingRequestInFlight = false;
        });
    }

    rexSignerAuthButtons.forEach(function(button) {
        button.addEventListener('click', rexLinkOpenModal);
    });

    if (rexLinkCopyCodeButton) {
        rexLinkCopyCodeButton.addEventListener('click', function() {
            const code = rexLinkPairingCode ? rexLinkPairingCode.textContent.trim() : '';
            if (!code || code === 'No code yet') {
                return;
            }
            const markCopied = function() {
                rexLinkCopyCodeButton.innerHTML = '<i class="fas fa-check"></i>';
                window.setTimeout(function() {
                    rexLinkCopyCodeButton.innerHTML = '<i class="fas fa-copy"></i>';
                }, 1400);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(markCopied).catch(markCopied);
            } else {
                markCopied();
            }
        });
    }

    if (rexLinkPrimaryButton) {
        rexLinkPrimaryButton.addEventListener('click', function() {
            rexLinkCreatePairing();
        });
    }

    window.addEventListener('rexlink:session-connected', function() {
        if (!rexLinkModal || rexLinkModal.hidden) {
            return;
        }
        rexAuthPollStatus();
    });

    [rexLinkModalBackdrop, rexLinkModalClose].forEach(function(element) {
        if (element) {
            element.addEventListener('click', rexLinkCloseModal);
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && rexLinkModal && !rexLinkModal.hidden) {
            rexLinkCloseModal();
        }
    });

    const registerSubmitButton = document.getElementById('registerSubmitButton');
    const registerTermsCheckbox = document.getElementById('registerTermsCheckbox');
    const regEmail = document.getElementById('regEmail');
    const regPassword = document.getElementById('regPassword');
    const regConfirmPassword = document.getElementById('regConfirmPassword');
    const regReferral = document.getElementById('regReferral');
    const emailFeedback = document.getElementById('emailFeedback');
    const passwordFeedback = document.getElementById('passwordFeedback');
    const confirmFeedback = document.getElementById('confirmFeedback');
    const referralFeedback = document.getElementById('referralFeedback');
    const passwordChecklist = document.getElementById('passwordChecklist');
    const passwordFieldGroup = document.getElementById('passwordFieldGroup');
    const confirmPasswordFieldGroup = regConfirmPassword ? regConfirmPassword.closest('.input-field') : null;
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const referralPattern = /^[A-Z0-9]{6,16}$/;
    let emailTimer = null;
    let emailRequestId = 0;
    let referralTimer = null;
    let referralRequestId = 0;
    
    function switchTab(tabId) {
        tabs.forEach(tab => {
            tab.classList.toggle('active', tab.dataset.tab === tabId);
        });
        
        if (tabId === 'login') {
            loginContainer.classList.add('active');
            registerContainer.classList.remove('active');
        } else {
            registerContainer.classList.add('active');
            loginContainer.classList.remove('active');
        }

        togglePasswordChecklist(false);
        
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabId);
        window.history.pushState({}, '', url);
        
        const activeTab = document.querySelector('.auth-tab.active');
        if (activeTab && slider) {
            const tabRect = activeTab.getBoundingClientRect();
            const containerRect = activeTab.parentElement.getBoundingClientRect();
            slider.style.width = tabRect.width + 'px';
            slider.style.transform = `translateX(${tabRect.left - containerRect.left}px)`;
        }
    }

    function setFieldState(input, state) {
        const control = input ? input.closest('.input-control') : null;
        if (!control) {
            return;
        }

        control.classList.remove('is-valid', 'is-invalid', 'is-checking');
        if (state) {
            control.classList.add(state);
        }
    }

    function setFeedback(element, status, message) {
        if (!element) {
            return;
        }

        element.classList.remove('is-valid', 'is-invalid', 'is-checking');
        element.textContent = message || '';

        if (status) {
            element.classList.add(status);
        }
    }

    function syncRegisterSubmitState() {
        if (!registerSubmitButton || !registerTermsCheckbox) {
            return;
        }

        registerSubmitButton.disabled = !registerTermsCheckbox.checked;
    }

    function updateRequirement(rule, passed) {
        const item = passwordChecklist ? passwordChecklist.querySelector(`[data-rule="${rule}"]`) : null;
        if (!item) {
            return;
        }

        const icon = item.querySelector('i');
        item.classList.toggle('passed', passed);
        item.classList.toggle('failed', !passed && regPassword.value.length > 0);

        if (icon) {
            icon.classList.toggle('fa-check-circle', passed);
            icon.classList.toggle('fa-times-circle', !passed && regPassword.value.length > 0);
            icon.classList.toggle('fa-circle', regPassword.value.length === 0);
        }
    }

    function togglePasswordChecklist(shouldShow) {
        if (!passwordFieldGroup) {
            return;
        }

        passwordFieldGroup.classList.toggle('show-password-modal', shouldShow);
    }

    function syncPasswordChecklistVisibility() {
        const activeElement = document.activeElement;
        const shouldShow = Boolean(
            activeElement
            && passwordFieldGroup
            && passwordFieldGroup.contains(activeElement)
        );

        togglePasswordChecklist(shouldShow);
    }

    function validatePasswordField() {
        if (!regPassword) {
            return false;
        }

        const password = regPassword.value;
        const checks = {
            length: password.length >= 9,
            uppercase: /[A-Z]/.test(password),
            digit: /\d/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };

        Object.entries(checks).forEach(([rule, passed]) => updateRequirement(rule, passed));

        const isValid = Object.values(checks).every(Boolean);
        regPassword.setCustomValidity(password && !isValid ? 'Password must be at least 9 characters and include an uppercase letter, a number, and a special character.' : '');

        if (!password) {
            setFieldState(regPassword, '');
            setFeedback(passwordFeedback, '', '');
        } else {
            setFieldState(regPassword, isValid ? 'is-valid' : 'is-invalid');
            setFeedback(
                passwordFeedback,
                isValid ? 'is-valid' : 'is-invalid',
                isValid ? 'Valid Password' : 'Invalid Password Format'
            );
        }

        return isValid;
    }

    function validateConfirmPasswordField() {
        if (!regPassword || !regConfirmPassword) {
            return false;
        }

        const confirmValue = regConfirmPassword.value;
        if (!confirmValue) {
            regConfirmPassword.setCustomValidity('');
            setFieldState(regConfirmPassword, '');
            setFeedback(confirmFeedback, '', '');
            return false;
        }

        const matches = regPassword.value === confirmValue;
        regConfirmPassword.setCustomValidity(matches ? '' : 'Confirm password must match the password field.');
        setFieldState(regConfirmPassword, matches ? 'is-valid' : 'is-invalid');
        setFeedback(confirmFeedback, matches ? 'is-valid' : 'is-invalid', matches ? 'Passwords match' : 'Passwords do not match');

        return matches;
    }

    async function validateEmailField() {
        if (!regEmail) {
            return false;
        }

        const email = regEmail.value.trim().toLowerCase();
        emailRequestId += 1;
        const requestId = emailRequestId;

        if (!email) {
            regEmail.setCustomValidity('');
            setFieldState(regEmail, '');
            setFeedback(emailFeedback, '', '');
            return false;
        }

        if (!emailPattern.test(email)) {
            regEmail.setCustomValidity('Please enter a valid email address.');
            setFieldState(regEmail, 'is-invalid');
            setFeedback(emailFeedback, 'is-invalid', 'Please enter a valid email address');
            return false;
        }

        regEmail.value = email;
        regEmail.setCustomValidity('');
        setFieldState(regEmail, 'is-checking');
        setFeedback(emailFeedback, 'is-checking', 'Checking email availability...');

        try {
            const checkUrl = new URL(window.location.href);
            checkUrl.search = '';
            checkUrl.searchParams.set('check_email', '1');
            checkUrl.searchParams.set('email', email);

            const response = await fetch(checkUrl.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            if (requestId !== emailRequestId) {
                return false;
            }

            if (!result.valid || result.exists || result.disposable) {
                regEmail.setCustomValidity(result.message || 'This email is already registered.');
                setFieldState(regEmail, 'is-invalid');
                setFeedback(emailFeedback, 'is-invalid', result.message || 'This email is not allowed');
                return false;
            }

            regEmail.setCustomValidity('');
            setFieldState(regEmail, 'is-valid');
            setFeedback(emailFeedback, 'is-valid', result.message || 'Email is available');
            return true;
        } catch (error) {
            regEmail.setCustomValidity('');
            setFieldState(regEmail, '');
            setFeedback(emailFeedback, '', 'We will recheck this email on submit');
            return true;
        }
    }

    async function validateReferralField() {
        if (!regReferral) {
            return true;
        }

        const referralCode = regReferral.value.trim().toUpperCase();
        referralRequestId += 1;
        const requestId = referralRequestId;

        regReferral.value = referralCode;

        if (!referralCode) {
            regReferral.setCustomValidity('');
            setFieldState(regReferral, '');
            setFeedback(referralFeedback, '', '');
            return true;
        }

        if (!referralPattern.test(referralCode)) {
            regReferral.setCustomValidity('Referral code format is invalid.');
            setFieldState(regReferral, 'is-invalid');
            setFeedback(referralFeedback, 'is-invalid', 'Referral code format is invalid');
            return false;
        }

        regReferral.setCustomValidity('');
        setFieldState(regReferral, 'is-checking');
        setFeedback(referralFeedback, 'is-checking', 'Checking referral code...');

        try {
            const checkUrl = new URL(window.location.href);
            checkUrl.search = '';
            checkUrl.searchParams.set('check_referral', '1');
            checkUrl.searchParams.set('referral_code', referralCode);

            const response = await fetch(checkUrl.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            if (requestId !== referralRequestId) {
                return false;
            }

            if (!result.valid) {
                regReferral.setCustomValidity(result.message || 'Referral code not found.');
                setFieldState(regReferral, 'is-invalid');
                setFeedback(referralFeedback, 'is-invalid', result.message || 'Referral code not found');
                return false;
            }

            regReferral.setCustomValidity('');
            setFieldState(regReferral, 'is-valid');
            setFeedback(referralFeedback, 'is-valid', result.message || 'Referral code applied successfully');
            return true;
        } catch (error) {
            regReferral.setCustomValidity('');
            setFieldState(regReferral, '');
            setFeedback(referralFeedback, '', 'We will verify this referral code on submit');
            return true;
        }
    }
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.tab));
    });
    
    setTimeout(() => {
        const activeTab = document.querySelector('.auth-tab.active');
        if (activeTab && slider) {
            const tabRect = activeTab.getBoundingClientRect();
            const containerRect = activeTab.parentElement.getBoundingClientRect();
            slider.style.width = tabRect.width + 'px';
            slider.style.transform = `translateX(${tabRect.left - containerRect.left}px)`;
        }
    }, 100);
    
    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    document.querySelectorAll('.input-field input').forEach(input => {
        input.addEventListener('focus', () => {
            const control = input.closest('.input-control');
            if (control) {
                control.classList.add('focused');
            }
        });
        input.addEventListener('blur', () => {
            const control = input.closest('.input-control');
            if (control && !input.value) {
                control.classList.remove('focused');
            }
        });
        if (input.value) {
            const control = input.closest('.input-control');
            if (control) {
                control.classList.add('focused');
            }
        }
    });

    if (regPassword) {
        regPassword.addEventListener('focus', syncPasswordChecklistVisibility);
        regPassword.addEventListener('blur', function() {
            setTimeout(syncPasswordChecklistVisibility, 0);
        });
        regPassword.addEventListener('input', function() {
            validatePasswordField();
            validateConfirmPasswordField();
        });
        validatePasswordField();
    }

    if (regConfirmPassword) {
        regConfirmPassword.addEventListener('focus', function() {
            togglePasswordChecklist(false);
        });
        regConfirmPassword.addEventListener('blur', function() {
            setTimeout(syncPasswordChecklistVisibility, 0);
        });
        regConfirmPassword.addEventListener('input', validateConfirmPasswordField);
    }

    document.querySelectorAll('.password-toggle').forEach(button => {
        button.addEventListener('focus', syncPasswordChecklistVisibility);
        button.addEventListener('blur', function() {
            setTimeout(syncPasswordChecklistVisibility, 0);
        });
    });

    if (regEmail) {
        regEmail.addEventListener('input', function() {
            clearTimeout(emailTimer);
            setFeedback(emailFeedback, '', '');
            setFieldState(regEmail, '');
            regEmail.setCustomValidity('');

            if (!regEmail.value.trim()) {
                return;
            }

            emailTimer = setTimeout(() => {
                validateEmailField();
            }, 300);
        });

        regEmail.addEventListener('blur', function() {
            clearTimeout(emailTimer);
            validateEmailField();
        });

        if (regEmail.value.trim()) {
            validateEmailField();
        }
    }

    if (regReferral) {
        regReferral.addEventListener('input', function() {
            clearTimeout(referralTimer);
            regReferral.value = regReferral.value.toUpperCase();
            setFeedback(referralFeedback, '', '');
            setFieldState(regReferral, '');
            regReferral.setCustomValidity('');

            if (!regReferral.value.trim()) {
                return;
            }

            referralTimer = setTimeout(() => {
                validateReferralField();
            }, 300);
        });

        regReferral.addEventListener('blur', function() {
            clearTimeout(referralTimer);
            validateReferralField();
        });

        if (regReferral.value.trim()) {
            validateReferralField();
        }
    }

    if (registerForm) {
        registerForm.addEventListener('submit', async function(event) {
            syncRegisterSubmitState();
            if (registerSubmitButton && registerSubmitButton.disabled) {
                return;
            }

            event.preventDefault();
            switchTab('register');

            const passwordValid = validatePasswordField();
            const confirmValid = validateConfirmPasswordField();
            const emailValid = await validateEmailField();
            const referralValid = await validateReferralField();

            if (!registerForm.reportValidity()) {
                return;
            }

            if (passwordValid && confirmValid && emailValid && referralValid) {
                HTMLFormElement.prototype.submit.call(registerForm);
            }
        });
    }

    if (registerTermsCheckbox) {
        registerTermsCheckbox.addEventListener('change', syncRegisterSubmitState);
        syncRegisterSubmitState();
    }

    syncPasswordChecklistVisibility();
});
</script>

<?php 
require_once dirname(__DIR__) . '/includes/footer.php';
// End output buffering and flush
ob_end_flush();
?>
