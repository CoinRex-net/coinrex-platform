<?php
/**
 * CoinRex Forgot Password Page
 * Location: /coinrex/auth/forgot.php
 */

ob_start();

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
}

$error = '';
$success = '';
$info = '';
$identifier = trim((string) ($_POST['identifier'] ?? ''));
$submitted_otp = '';
$pending_user = getPendingPasswordResetUser();
$otp_verified = isPasswordResetOtpVerified();
$otp_length = EMAIL_VERIFICATION_OTP_LENGTH;
$expiry_minutes = EMAIL_VERIFICATION_OTP_EXPIRY_MINUTES;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reset_otp'])) {
    $identifier = trim((string) ($_POST['identifier'] ?? ''));

    if ($identifier === '') {
        $error = 'Please enter your email address or username';
    } else {
        $matched_user = getUserByIdentifier($identifier);

        if (!$matched_user) {
            $error = 'No account found with that email or username';
            clearPendingPasswordReset();
            $pending_user = null;
            $otp_verified = false;
        } else {
            $result = startPendingPasswordReset($matched_user);
            if ($result['success']) {
                $pending_user = getPendingPasswordResetUser();
                $otp_verified = false;
                $success = 'A 6-digit OTP has been sent to your registered email address.';
                $info = 'Use the code from your inbox to verify your identity before choosing a new password.';
            } else {
                $error = $result['message'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    if (!$pending_user) {
        $error = 'Please start the password reset process again.';
    } else {
        $remaining = getPasswordResetResendRemainingSeconds();
        if ($remaining > 0) {
            $error = 'Please wait ' . $remaining . ' second(s) before requesting a new OTP.';
        } else {
            $result = startPendingPasswordReset($pending_user);
            if ($result['success']) {
                $pending_user = getPendingPasswordResetUser();
                $otp_verified = false;
                $success = 'A fresh 6-digit OTP has been sent to ' . $pending_user['email'] . '.';
                $info = 'Enter the latest OTP from your inbox to continue.';
            } else {
                $error = $result['message'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $submitted_otp = preg_replace('/\D/', '', (string) ($_POST['otp'] ?? ''));

    if (!$pending_user) {
        $error = 'Please request a password reset OTP first.';
    } else {
        $result = validatePasswordResetOtpSubmission($pending_user, $submitted_otp);
        if ($result['success']) {
            $pending_user = $result['user'] ?? $pending_user;
            $otp_verified = true;
            $success = 'Identity verified. You can now set a new password.';
            $info = '';
        } else {
            $error = $result['message'];
            $pending_user = getPendingPasswordResetUser() ?: $pending_user;
            $otp_verified = isPasswordResetOtpVerified();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $password = (string) ($_POST['password'] ?? '');
    $confirm_password = (string) ($_POST['confirm_password'] ?? '');
    $password_validation = validatePasswordPolicy($password);

    if (!$pending_user || !$otp_verified) {
        $error = 'Please verify your OTP before resetting the password.';
    } elseif (!$password_validation['is_valid']) {
        $error = 'Password must be at least 9 characters and include an uppercase letter, a number, and a special character';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (!resetUserPassword($pending_user['id'], $password)) {
        $error = 'We could not reset your password right now. Please try again.';
    } else {
        $login_email = normalizeEmail((string) $pending_user['email']);
        clearPendingPasswordReset();
        setFlashMessage('auth_success', 'Password reset successful. Please sign in with your new password.');
        setFlashMessage('auth_login_email', $login_email);
        redirect(BASE_URL . '/auth/auth.php?tab=login');
    }
}

$resend_remaining = getPasswordResetResendRemainingSeconds();
$otp_attempts = (int) ($pending_user['otp_attempts'] ?? 0);
$otp_attempts_left = max(0, EMAIL_VERIFICATION_OTP_MAX_ATTEMPTS - $otp_attempts);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/auth.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/verify_email.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/forgot.css">

<main class="auth-main verify-main forgot-main">
    <div class="auth-container verify-container forgot-container">
        <div class="auth-bg-decoration">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="gradient-orb orb-3"></div>
        </div>

        <div class="auth-card verify-card forgot-card">
            <div class="auth-logo verify-logo">
                <img src="<?php echo ASSETS_URL; ?>/images/logo.png" alt="CoinRex" class="auth-logo-img">
                <p class="auth-tagline"><?php echo SITE_TAGLINE; ?></p>
            </div>

            <div class="verify-heading forgot-heading">
                <span class="verify-badge"><i class="fas fa-unlock-keyhole"></i> Account Recovery</span>
                <h1>Forgot Password</h1>
                <p>Find your account, verify it with a 6-digit OTP, and create a secure new password.</p>
            </div>

            <?php if ($error): ?>
            <div class="auth-message error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="auth-message success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($info): ?>
            <div class="auth-message verify-info-message">
                <i class="fas fa-envelope-open-text"></i>
                <span><?php echo htmlspecialchars($info, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php endif; ?>

            <section class="verify-guide forgot-guide">
                <div class="guide-item">
                    <span class="guide-step">1</span>
                    <div>
                        <strong>Find your account</strong>
                        <p>Enter the email address or username linked to your CoinRex account.</p>
                    </div>
                </div>
                <div class="guide-item">
                    <span class="guide-step">2</span>
                    <div>
                        <strong>Verify with OTP</strong>
                        <p>We will send a 6-digit OTP to your registered email so we know it is really you.</p>
                    </div>
                </div>
                <div class="guide-item">
                    <span class="guide-step">3</span>
                    <div>
                        <strong>Set a fresh password</strong>
                        <p>Choose a strong password with the same security rules used during registration.</p>
                    </div>
                </div>
            </section>

            <?php if (!$pending_user): ?>
            <section class="forgot-section">
                <div class="section-head">
                    <h2>Find Your Account</h2>
                    <p>Use either your email address or your username. If the account exists, we will send the OTP to the registered email.</p>
                </div>

                <form method="POST" class="forgot-lookup-form">
                    <div class="input-group">
                        <div class="input-icon">
                            <i class="fas fa-user-magnifying-glass"></i>
                        </div>
                        <div class="input-field">
                            <div class="input-control focused">
                                <input type="text" name="identifier" id="resetIdentifier" value="<?php echo htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <label for="resetIdentifier">Email Address or Username</label>
                                <span class="input-border"></span>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="send_reset_otp" class="auth-submit">
                        <span>Send OTP</span>
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </section>
            <?php else: ?>
            <section class="verify-email-card forgot-email-card">
                <div class="verify-email-copy">
                    <span class="verify-email-label">Recovery Email</span>
                    <strong><?php echo htmlspecialchars((string) $pending_user['email'], ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
                <div class="verify-email-meta">
                    <span><i class="fas fa-user"></i> @<?php echo htmlspecialchars((string) $pending_user['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class="fas fa-keyboard"></i> <?php echo $otp_attempts_left; ?> attempt(s) left</span>
                </div>
            </section>

            <?php if (!$otp_verified): ?>
            <section class="forgot-section">
                <div class="section-head">
                    <h2>Verify OTP</h2>
                    <p>Enter or paste the 6-digit OTP we sent to your registered email.</p>
                </div>

                <form method="POST" class="verify-form" id="resetOtpForm" autocomplete="one-time-code">
                    <input type="hidden" name="otp" id="resetOtpValue" value="<?php echo htmlspecialchars($submitted_otp, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="otp-input-wrap" id="resetOtpInputWrap" data-otp-length="<?php echo $otp_length; ?>">
                        <?php for ($i = 0; $i < $otp_length; $i++): ?>
                        <input
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="1"
                            class="otp-slot"
                            autocomplete="<?php echo $i === 0 ? 'one-time-code' : 'off'; ?>"
                            value="<?php echo htmlspecialchars(substr($submitted_otp, $i, 1), ENT_QUOTES, 'UTF-8'); ?>"
                            aria-label="OTP digit <?php echo $i + 1; ?>"
                        >
                        <?php endfor; ?>
                    </div>

                    <div class="verify-form-footer">
                        <div class="verify-tip">
                            <i class="fas fa-bolt"></i>
                            <span>Tip: paste the full OTP and we will fill all six boxes automatically.</span>
                        </div>

                        <button type="submit" name="verify_otp" class="auth-submit verify-submit">
                            <span>Verify OTP</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>

                <div class="verify-actions forgot-actions">
                    <form method="POST" class="resend-form">
                        <button
                            type="submit"
                            name="resend_otp"
                            class="verify-secondary-btn"
                            id="resendResetOtpButton"
                            <?php echo $resend_remaining > 0 ? 'disabled' : ''; ?>
                        >
                            <i class="fas fa-paper-plane"></i>
                            <span>Resend OTP</span>
                        </button>
                    </form>

                    <a href="<?php echo BASE_URL; ?>/auth/auth.php?tab=login" class="verify-link-btn">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Login</span>
                    </a>
                </div>

                <p class="verify-timer" id="resetResendTimer" data-remaining="<?php echo (int) $resend_remaining; ?>">
                    <?php if ($resend_remaining > 0): ?>
                        You can request a new OTP in <?php echo (int) $resend_remaining; ?> second(s).
                    <?php else: ?>
                        Need a new code? You can resend it now.
                    <?php endif; ?>
                </p>
            </section>
            <?php else: ?>
            <section class="forgot-section">
                <div class="section-head">
                    <h2>Create New Password</h2>
                    <p>Your identity is verified. Choose a strong new password to secure your account.</p>
                </div>

                <form method="POST" class="forgot-reset-form" id="passwordResetForm">
                    <div class="reset-grid">
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="input-field password-field" id="resetPasswordFieldGroup">
                                <div class="input-control">
                                    <input type="password" name="password" id="resetPassword" autocomplete="new-password" required>
                                    <label for="resetPassword">New Password</label>
                                    <span class="input-border"></span>
                                    <button type="button" class="password-toggle" data-target="resetPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="password-checklist" id="resetPasswordChecklist" aria-live="polite">
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
                                <div class="field-feedback" id="resetPasswordFeedback" aria-live="polite"></div>
                            </div>
                        </div>

                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="input-field">
                                <div class="input-control">
                                    <input type="password" name="confirm_password" id="resetConfirmPassword" autocomplete="new-password" required>
                                    <label for="resetConfirmPassword">Confirm Password</label>
                                    <span class="input-border"></span>
                                    <button type="button" class="password-toggle" data-target="resetConfirmPassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="field-feedback" id="resetConfirmFeedback" aria-live="polite"></div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="reset_password" class="auth-submit">
                        <span>Reset Password</span>
                        <i class="fas fa-key"></i>
                    </button>
                </form>
            </section>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const otpSlots = Array.from(document.querySelectorAll('.otp-slot'));
    const otpValue = document.getElementById('resetOtpValue');
    const otpForm = document.getElementById('resetOtpForm');
    const resendButton = document.getElementById('resendResetOtpButton');
    const resendTimer = document.getElementById('resetResendTimer');
    const resetPassword = document.getElementById('resetPassword');
    const resetConfirmPassword = document.getElementById('resetConfirmPassword');
    const resetPasswordFeedback = document.getElementById('resetPasswordFeedback');
    const resetConfirmFeedback = document.getElementById('resetConfirmFeedback');
    const resetPasswordChecklist = document.getElementById('resetPasswordChecklist');
    const resetPasswordFieldGroup = document.getElementById('resetPasswordFieldGroup');

    function getOtpDigits(value) {
        return (value || '').replace(/\D/g, '');
    }

    function syncOtpValue() {
        if (!otpValue) {
            return;
        }

        otpValue.value = otpSlots.map(function (input) {
            return getOtpDigits(input.value).slice(0, 1);
        }).join('');
    }

    function focusSlot(index) {
        if (otpSlots[index]) {
            otpSlots[index].focus();
            otpSlots[index].select();
        }
    }

    function fillOtpSlots(digits) {
        const cleanDigits = getOtpDigits(digits).slice(0, otpSlots.length);

        if (!cleanDigits) {
            return;
        }

        otpSlots.forEach(function (slot, slotIndex) {
            slot.value = cleanDigits[slotIndex] || '';
        });

        syncOtpValue();
        focusSlot(Math.min(cleanDigits.length, otpSlots.length) - 1);
    }

    otpSlots.forEach(function (input, index) {
        input.addEventListener('beforeinput', function (event) {
            const incomingDigits = getOtpDigits(event.data || '');

            if (incomingDigits.length > 1) {
                event.preventDefault();
                fillOtpSlots(incomingDigits);
            }
        });

        input.addEventListener('input', function (event) {
            const insertedDigits = getOtpDigits(event.data || '');
            const fieldDigits = getOtpDigits(input.value);
            const digits = insertedDigits.length > 1 ? insertedDigits : fieldDigits;

            if (digits.length > 1) {
                fillOtpSlots(digits);
                return;
            }

            input.value = digits.slice(-1);
            syncOtpValue();

            if (input.value && index < otpSlots.length - 1) {
                focusSlot(index + 1);
            }
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Backspace' && !input.value && index > 0) {
                focusSlot(index - 1);
            }

            if (event.key === 'ArrowLeft' && index > 0) {
                event.preventDefault();
                focusSlot(index - 1);
            }

            if (event.key === 'ArrowRight' && index < otpSlots.length - 1) {
                event.preventDefault();
                focusSlot(index + 1);
            }
        });

        input.addEventListener('focus', function () {
            input.select();
        });

        input.addEventListener('paste', function (event) {
            const pasted = (event.clipboardData || window.clipboardData).getData('text');
            const digits = getOtpDigits(pasted);

            if (digits) {
                event.preventDefault();
                fillOtpSlots(digits);
            }
        });
    });

    if (otpForm) {
        otpForm.addEventListener('submit', function () {
            syncOtpValue();
        });
    }

    if (resendTimer) {
        let remaining = parseInt(resendTimer.dataset.remaining || '0', 10);
        let timerId = null;

        const updateTimer = function () {
            if (remaining > 0) {
                resendTimer.textContent = 'You can request a new OTP in ' + remaining + ' second(s).';
                if (resendButton) {
                    resendButton.disabled = true;
                }
                remaining -= 1;
            } else {
                resendTimer.textContent = 'Need a new code? You can resend it now.';
                if (resendButton) {
                    resendButton.disabled = false;
                }
                if (timerId) {
                    window.clearInterval(timerId);
                    timerId = null;
                }
            }
        };

        updateTimer();
        if (remaining > 0) {
            timerId = window.setInterval(updateTimer, 1000);
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

    function updateRequirement(rule, passed) {
        const item = resetPasswordChecklist ? resetPasswordChecklist.querySelector('[data-rule="' + rule + '"]') : null;
        if (!item) {
            return;
        }

        const icon = item.querySelector('i');
        item.classList.toggle('passed', passed);
        item.classList.toggle('failed', !passed && resetPassword.value.length > 0);

        if (icon) {
            icon.classList.toggle('fa-check-circle', passed);
            icon.classList.toggle('fa-times-circle', !passed && resetPassword.value.length > 0);
            icon.classList.toggle('fa-circle', resetPassword.value.length === 0);
        }
    }

    function togglePasswordChecklist(shouldShow) {
        if (!resetPasswordFieldGroup) {
            return;
        }

        resetPasswordFieldGroup.classList.toggle('show-password-modal', shouldShow);
    }

    function syncPasswordChecklistVisibility() {
        const activeElement = document.activeElement;
        const shouldShow = Boolean(
            activeElement
            && resetPasswordFieldGroup
            && resetPasswordFieldGroup.contains(activeElement)
        );

        togglePasswordChecklist(shouldShow);
    }

    function validatePasswordField() {
        if (!resetPassword) {
            return false;
        }

        const password = resetPassword.value;
        const checks = {
            length: password.length >= 9,
            uppercase: /[A-Z]/.test(password),
            digit: /\d/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };

        Object.keys(checks).forEach(function (rule) {
            updateRequirement(rule, checks[rule]);
        });

        const isValid = Object.keys(checks).every(function (rule) {
            return checks[rule];
        });

        resetPassword.setCustomValidity(password && !isValid ? 'Password must be at least 9 characters and include an uppercase letter, a number, and a special character.' : '');

        if (!password) {
            setFieldState(resetPassword, '');
            setFeedback(resetPasswordFeedback, '', '');
        } else {
            setFieldState(resetPassword, isValid ? 'is-valid' : 'is-invalid');
            setFeedback(resetPasswordFeedback, isValid ? 'is-valid' : 'is-invalid', isValid ? 'Valid Password' : 'Invalid Password Format');
        }

        return isValid;
    }

    function validateConfirmPasswordField() {
        if (!resetPassword || !resetConfirmPassword) {
            return false;
        }

        const confirmValue = resetConfirmPassword.value;
        if (!confirmValue) {
            resetConfirmPassword.setCustomValidity('');
            setFieldState(resetConfirmPassword, '');
            setFeedback(resetConfirmFeedback, '', '');
            return false;
        }

        const matches = resetPassword.value === confirmValue;
        resetConfirmPassword.setCustomValidity(matches ? '' : 'Confirm password must match the password field.');
        setFieldState(resetConfirmPassword, matches ? 'is-valid' : 'is-invalid');
        setFeedback(resetConfirmFeedback, matches ? 'is-valid' : 'is-invalid', matches ? 'Passwords match' : 'Passwords do not match');

        return matches;
    }

    document.querySelectorAll('.password-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            const targetId = button.dataset.target;
            const input = document.getElementById(targetId);
            const icon = button.querySelector('i');

            if (!input || !icon) {
                return;
            }

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

        button.addEventListener('focus', syncPasswordChecklistVisibility);
        button.addEventListener('blur', function () {
            setTimeout(syncPasswordChecklistVisibility, 0);
        });
    });

    document.querySelectorAll('.input-field input').forEach(function (input) {
        input.addEventListener('focus', function () {
            const control = input.closest('.input-control');
            if (control) {
                control.classList.add('focused');
            }
        });

        input.addEventListener('blur', function () {
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

    if (resetPassword) {
        resetPassword.addEventListener('focus', syncPasswordChecklistVisibility);
        resetPassword.addEventListener('blur', function () {
            setTimeout(syncPasswordChecklistVisibility, 0);
        });
        resetPassword.addEventListener('input', function () {
            validatePasswordField();
            validateConfirmPasswordField();
        });
        validatePasswordField();
    }

    if (resetConfirmPassword) {
        resetConfirmPassword.addEventListener('focus', function () {
            togglePasswordChecklist(false);
        });
        resetConfirmPassword.addEventListener('blur', function () {
            setTimeout(syncPasswordChecklistVisibility, 0);
        });
        resetConfirmPassword.addEventListener('input', validateConfirmPasswordField);
    }

    syncOtpValue();
    if (otpSlots.length) {
        focusSlot(0);
    }
    syncPasswordChecklistVisibility();
});
</script>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
ob_end_flush();
?>
