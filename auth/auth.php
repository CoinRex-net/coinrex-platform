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
    redirect(BASE_URL . '/dashboard.php');
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
    $password_validation = validatePasswordPolicy($password);
    $referral_validation = validateReferralCode($referral_code);
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } elseif (isDisposableEmail($email)) {
        $error = 'Temporary email addresses are not allowed';
    } elseif (getUserByEmail($email)) {
        $error = 'Email already registered';
    } elseif (!$password_validation['is_valid']) {
        $error = 'Password must be at least 9 characters and include an uppercase letter, a number, and a special character';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!$referral_validation['valid']) {
        $error = $referral_validation['message'];
    } elseif (!isset($_POST['terms'])) {
        $error = 'Please accept the Terms of Service';
    } else {
        $result = registerUser($full_name, $email, $password, $referral_code);
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
            redirect(BASE_URL . '/dashboard.php');
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

<main class="auth-main">
    <div class="auth-container">
        
        <div class="auth-bg-decoration">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="gradient-orb orb-3"></div>
        </div>
        
        <div class="auth-card">
            
            <!-- Clean Logo Section - No extra containers -->
            <div class="auth-logo">
                <img src="<?php echo ASSETS_URL; ?>/images/logo.png" alt="CoinRex" class="auth-logo-img">
                <p class="auth-tagline"><?php echo SITE_TAGLINE; ?></p>
            </div>

            <div class="auth-intro">
                <span class="auth-kicker"><i class="fas fa-shield-halved"></i> Secure Access</span>
                <h1><?php echo $active_tab === 'register' ? 'Create your CoinRex account' : 'Welcome back to CoinRex'; ?></h1>
                <p><?php echo $active_tab === 'register'
                    ? 'Start earning rewards, track your activity, and join the CoinRex community with a fast and secure signup flow.'
                    : 'Sign in to continue earning, manage your reviews, and stay connected with your CoinRex dashboard.'; ?></p>
            </div>
            
            <!-- Error/Success Messages -->
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
            
            <!-- Tabs -->
            <div class="auth-tabs">
                <button class="auth-tab <?php echo $active_tab == 'login' ? 'active' : ''; ?>" data-tab="login">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In</span>
                </button>
                <button class="auth-tab <?php echo $active_tab == 'register' ? 'active' : ''; ?>" data-tab="register">
                    <i class="fas fa-user-plus"></i>
                    <span>Create Account</span>
                </button>
                <div class="auth-tab-slider"></div>
            </div>
            
            <!-- Login Form -->
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
            </div>
            
            <!-- Register Form -->
            <div class="auth-form-container <?php echo $active_tab == 'register' ? 'active' : ''; ?>" id="registerForm">
                <form method="POST" class="auth-form" id="registerAuthForm">
                    <input type="hidden" name="form_action" value="register">
                    <input type="hidden" name="device_fingerprint" id="deviceFingerprintField" value="">
                <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_to, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="register-grid">
                        <div class="register-column">
                            <!-- Full Name -->
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

                            <!-- Email -->
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
                            <!-- Password -->
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

                            <!-- Confirm Password -->
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

                            <!-- Referral Code (Optional) -->
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
                            <span class="checkbox-text">I agree to the <a href="../terms.php">Terms of Service</a></span>
                        </label>
                    </div>

                    <div class="auth-info">
                        <i class="fas fa-star"></i>
                        <span>Get 10 $REX welcome bonus + 5 $REX with referral code!</span>
                    </div>
                </form>
            </div>
            
            <div class="auth-quote">
                <i class="fas fa-quote-left"></i>
                <p>Join 10,000+ crypto enthusiasts earning rewards through honest reviews</p>
            </div>
            
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.auth-tab');
    const loginContainer = document.getElementById('loginForm');
    const registerContainer = document.getElementById('registerForm');
    const slider = document.querySelector('.auth-tab-slider');
    const registerForm = document.getElementById('registerAuthForm');
    const deviceFingerprintField = document.getElementById('deviceFingerprintField');
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
