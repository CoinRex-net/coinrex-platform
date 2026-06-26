<?php
/**
 * CoinRex Email Verification Page
 * Location: /coinrex/auth/verify_email.php
 */

ob_start();

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(BASE_URL . '/public/dashboard.php');
}

$pending_user = getPendingVerificationUser();
if (!$pending_user) {
    redirect(BASE_URL . '/auth/auth.php?tab=login');
}

if ((int) ($pending_user['email_verified'] ?? 0) === 1) {
    establishAuthenticatedSession($pending_user);
    setFlashMessage('dashboard_success', 'Email already verified. Welcome back!');
    redirect(BASE_URL . '/public/dashboard.php');
}

$error = '';
$success = '';
$info = consumeFlashMessage('verify_info');
$submitted_otp = '';
$otp_length = EMAIL_VERIFICATION_OTP_LENGTH;
$expiry_minutes = EMAIL_VERIFICATION_OTP_EXPIRY_MINUTES;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    $remaining = getEmailVerificationResendRemainingSeconds();

    if ($remaining > 0) {
        $error = 'Please wait ' . $remaining . ' second(s) before requesting a new OTP.';
    } else {
        $result = startPendingEmailVerification($pending_user);
        if ($result['success']) {
            $success = 'New OTP sent.';
            $info = '';
        } else {
            $error = $result['message'];
        }
    }

    $pending_user = getPendingVerificationUser();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $submitted_otp = preg_replace('/\D/', '', (string) ($_POST['otp'] ?? ''));
    $result = validateEmailVerificationOtpSubmission($pending_user, $submitted_otp);

    if ($result['success']) {
        $verified_user = $result['user'] ?? $pending_user;

        if (empty($result['already_verified'])) {
            if (!markEmailAsVerified($verified_user['id'])) {
                $error = 'We could not verify your email right now. Please try again.';
            } else {
                $verified_user = getUserById($verified_user['id']);
            }
        }

        if ($error === '') {
            establishAuthenticatedSession($verified_user);
            setFlashMessage('dashboard_success', 'Email verified successfully. Welcome to CoinRex!');
            redirect(BASE_URL . '/public/dashboard.php');
        }
    } else {
        $error = $result['message'];
        $pending_user = getPendingVerificationUser() ?: $pending_user;
    }
}

$resend_remaining = getEmailVerificationResendRemainingSeconds();
$otp_attempts = (int) ($pending_user['otp_attempts'] ?? 0);
$otp_attempts_left = max(0, EMAIL_VERIFICATION_OTP_MAX_ATTEMPTS - $otp_attempts);
$otp_expiry_timestamp = !empty($pending_user['otp_expiry']) ? strtotime((string) $pending_user['otp_expiry']) : false;
$otp_expires_in = $otp_expiry_timestamp ? max(0, $otp_expiry_timestamp - time()) : ($expiry_minutes * 60);

require_once dirname(__DIR__) . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/auth.css">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/verify_email.css">

<main class="auth-main auth-main-split verify-main">
    <div class="auth-container auth-shell verify-shell">
        <div class="auth-bg-decoration" aria-hidden="true">
            <div class="gradient-orb orb-1"></div>
            <div class="gradient-orb orb-2"></div>
            <div class="gradient-orb orb-3"></div>
        </div>

        <div class="auth-card auth-split-card verify-split-card">
            <section class="auth-panel verify-panel">
                <div class="auth-logo verify-logo">
                    <img src="<?php echo ASSETS_URL; ?>/images/shield-logo.png" alt="CoinRex" class="auth-logo-img auth-shield-mark">
                    <p class="auth-tagline"><?php echo SITE_TAGLINE; ?></p>
                </div>

                <div class="verify-heading">
                    <span class="verify-badge"><i class="fas fa-shield-check"></i> Secure Verification</span>
                    <h1>Email OTP</h1>
                    <p>Enter the 6-digit code.</p>
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

                <section class="verify-email-card">
                    <div>
                        <span class="verify-email-label">OTP sent to</span>
                        <strong><?php echo htmlspecialchars((string) $pending_user['email'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="verify-email-status">
                        <span><i class="fas fa-clock"></i> <?php echo $expiry_minutes; ?> min expiry</span>
                        <span><i class="fas fa-keyboard"></i> <?php echo $otp_attempts_left; ?> attempts left</span>
                    </div>
                </section>

                <form method="POST" class="verify-form" id="verifyOtpForm" autocomplete="one-time-code">
                    <input type="hidden" name="otp" id="otpValue" value="<?php echo htmlspecialchars($submitted_otp, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="otp-input-wrap" id="otpInputWrap" data-otp-length="<?php echo $otp_length; ?>" aria-label="Email verification OTP">
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
                            <span>Paste code</span>
                        </div>

                        <button type="submit" name="verify_otp" class="auth-submit verify-submit">
                            <span>Verify Email</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </form>

                <div class="verify-actions">
                    <form method="POST" class="resend-form">
                        <button
                            type="submit"
                            name="resend_otp"
                            class="verify-secondary-btn"
                            id="resendOtpButton"
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

                <p class="verify-timer" id="resendTimer" data-remaining="<?php echo (int) $resend_remaining; ?>">
                    <?php if ($resend_remaining > 0): ?>
                        You can request a new OTP in <?php echo (int) $resend_remaining; ?> second(s).
                    <?php else: ?>
                        You can resend now.
                    <?php endif; ?>
                </p>
            </section>

            <aside class="auth-story auth-visual-zone verify-visual-zone" aria-label="CoinRex verification snapshot">
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
                        <i class="fas fa-envelope-circle-check"></i>
                        <span>Email</span>
                    </div>

                    <div class="auth-floating-shot auth-shot-review">
                        <i class="fas fa-key"></i>
                        <span>OTP</span>
                    </div>

                    <div class="auth-floating-shot auth-shot-reward">
                        <i class="fas fa-user-shield"></i>
                        <span>Access</span>
                    </div>

                    <div class="auth-floating-shot auth-shot-rexlink">
                        <img src="<?php echo ASSETS_URL; ?>/images/rexlink-logo.png" alt="RexLink">
                        <span>Secure</span>
                    </div>

                    <div class="auth-mini-panel auth-mini-panel-top">
                        <span class="auth-mini-dot"></span>
                        <strong>OTP Active</strong>
                    </div>

                    <div class="auth-mini-panel auth-mini-panel-bottom">
                        <img src="<?php echo ASSETS_URL; ?>/images/shield-logo.png" alt="">
                        <strong>Email Gate</strong>
                    </div>
                </div>

                <div class="auth-visual-ticker" aria-label="Verification steps">
                    <span>OTP Sent</span>
                    <span>Email Verified</span>
                    <span>Dashboard Unlocked</span>
                </div>
            </aside>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const otpSlots = Array.from(document.querySelectorAll('.otp-slot'));
    const otpValue = document.getElementById('otpValue');
    const verifyForm = document.getElementById('verifyOtpForm');
    const resendButton = document.getElementById('resendOtpButton');
    const resendTimer = document.getElementById('resendTimer');

    function getOtpDigits(value) {
        return (value || '').replace(/\D/g, '');
    }

    function syncOtpValue() {
        if (!otpValue) {
            return;
        }

        otpValue.value = otpSlots.map(function (input) {
            const digit = getOtpDigits(input.value).slice(0, 1);
            input.classList.toggle('is-filled', digit !== '');
            return digit;
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

    if (verifyForm) {
        verifyForm.addEventListener('submit', function () {
            syncOtpValue();
        });
    }

    if (resendTimer) {
        let remaining = parseInt(resendTimer.dataset.remaining || '0', 10);
        let timerId = null;

        const updateTimer = function () {
            if (remaining > 0) {
                resendTimer.textContent = 'Resend in ' + remaining + 's';
                if (resendButton) {
                    resendButton.disabled = true;
                }
                remaining -= 1;
            } else {
                resendTimer.textContent = 'You can resend now.';
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

    syncOtpValue();
    if (otpSlots.length) {
        focusSlot(0);
    }
});
</script>

<?php
require_once dirname(__DIR__) . '/includes/footer.php';
ob_end_flush();
?>
