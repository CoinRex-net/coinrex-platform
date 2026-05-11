<?php
/** Auto-split from legacy functions.php */

function normalizeEmail($email) {
    return strtolower(trim($email));
}

function getEmailDomain($email) {
    $email = normalizeEmail($email);

    if ($email === '' || strpos($email, '@') === false) {
        return '';
    }

    $parts = explode('@', $email);
    return trim(end($parts), ". \t\n\r\0\x0B");
}

function getDisposableEmailDomains() {
    static $domains = [
        '10minutemail.com',
        '10minutemail.net',
        '20minutemail.com',
        'anonbox.net',
        'burner-mail.io',
        'discard.email',
        'dispostable.com',
        'emailondeck.com',
        'fakeinbox.com',
        'getairmail.com',
        'getnada.com',
        'guerrillamail.com',
        'guerrillamail.net',
        'guerrillamailblock.com',
        'harakirimail.com',
        'inboxkitten.com',
        'jetable.org',
        'mailcatch.com',
        'maildrop.cc',
        'mailinator.com',
        'mailnesia.com',
        'mintemail.com',
        'moakt.com',
        'mytemp.email',
        'nada.ltd',
        'sharklasers.com',
        'spam4.me',
        'spambog.com',
        'spambog.de',
        'tempmail.com',
        'tempmail.org',
        'temp-mail.io',
        'temp-mail.org',
        'tempmailo.com',
        'throwawaymail.com',
        'tmail.ws',
        'tmpmail.org',
        'trashmail.com',
        'trashmail.de',
        'yopmail.com',
        'yopmail.fr',
        'yopmail.net',
        'yopmail.org',
    ];

    return $domains;
}

function isDisposableEmail($email) {
    $domain = getEmailDomain($email);
    if ($domain === '') {
        return false;
    }

    foreach (getDisposableEmailDomains() as $blocked_domain) {
        $suffix = '.' . $blocked_domain;
        if ($domain === $blocked_domain || substr($domain, -strlen($suffix)) === $suffix) {
            return true;
        }
    }

    return false;
}

function getUserByEmail($email) {
    $db = getDBConnection();
    $email = normalizeEmail($email);
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function generateEmailVerificationOtp() {
    return str_pad((string) random_int(0, 999999), EMAIL_VERIFICATION_OTP_LENGTH, '0', STR_PAD_LEFT);
}

function storeEmailVerificationOtp($user_id, $otp) {
    $db = getDBConnection();
    $expiry = date('Y-m-d H:i:s', time() + (EMAIL_VERIFICATION_OTP_EXPIRY_MINUTES * 60));
    $stmt = $db->prepare("
        UPDATE users
        SET otp_code = ?, otp_expiry = ?, otp_attempts = 0
        WHERE id = ?
    ");

    return $stmt->execute([$otp, $expiry, $user_id]);
}

function isMailConfigured() {
    return MAIL_SMTP_USERNAME !== ''
        && MAIL_SMTP_PASSWORD !== ''
        && MAIL_FROM_EMAIL !== '';
}

function sendSmtpEmail($to_email, $to_name, $subject, $html_body, $text_body = '') {
    if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        return ['success' => false, 'message' => 'PHPMailer is not installed'];
    }

    if (!isMailConfigured()) {
        return ['success' => false, 'message' => 'SMTP credentials are not configured'];
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = MAIL_SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_SMTP_USERNAME;
        $mail->Password = MAIL_SMTP_PASSWORD;
        $mail->Port = MAIL_SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        if (MAIL_SMTP_SECURE === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        if (MAIL_REPLY_TO_EMAIL !== '') {
            $mail->addReplyTo(MAIL_REPLY_TO_EMAIL, MAIL_REPLY_TO_NAME);
        }
        $mail->addAddress($to_email, $to_name ?: $to_email);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $text_body !== '' ? $text_body : strip_tags(str_replace(['<br>', '<br/>', '<br />'], PHP_EOL, $html_body));
        $mail->send();

        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (\Throwable $e) {
        return ['success' => false, 'message' => 'SMTP send failed: ' . $e->getMessage()];
    }
}

function sendEmailVerificationOtpMessage($user, $otp) {
    $recipient_email = normalizeEmail($user['email'] ?? '');
    $recipient_name = trim((string) ($user['full_name'] ?? $user['username'] ?? ''));
    $expiry_minutes = EMAIL_VERIFICATION_OTP_EXPIRY_MINUTES;
    $site_name = SITE_NAME;

    $subject = $site_name . ' Email Verification Code';
    $html_body = '
        <div style="font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 24px; color: #0f172a;">
            <h2 style="margin: 0 0 12px; color: #16a34a;">Verify your email</h2>
            <p style="margin: 0 0 16px;">Hi ' . htmlspecialchars($recipient_name !== '' ? $recipient_name : 'there', ENT_QUOTES, 'UTF-8') . ',</p>
            <p style="margin: 0 0 18px;">Use this 6-digit OTP to verify your CoinRex account:</p>
            <div style="margin: 0 0 18px; padding: 18px; background: #0f172a; border-radius: 14px; text-align: center;">
                <span style="font-size: 32px; letter-spacing: 10px; font-weight: 700; color: #4ade80;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</span>
            </div>
            <p style="margin: 0 0 10px;">This code expires in ' . $expiry_minutes . ' minutes.</p>
            <p style="margin: 0 0 10px; color: #475569;">If you did not try to sign in, you can ignore this email.</p>
            <p style="margin: 0 0 6px; font-size: 13px; color: #64748b;">' . htmlspecialchars(MAIL_NO_REPLY_NOTICE, ENT_QUOTES, 'UTF-8') . '</p>
            <p style="margin: 0; font-size: 13px; color: #64748b;">For help, contact ' . htmlspecialchars(SITE_EMAIL, ENT_QUOTES, 'UTF-8') . '.</p>
        </div>';
    $text_body = "Verify your email\n\nYour CoinRex OTP is: {$otp}\n\nThis code expires in {$expiry_minutes} minutes.\n\n" . MAIL_NO_REPLY_NOTICE . "\nFor help, contact " . SITE_EMAIL . ".";

    return sendSmtpEmail($recipient_email, $recipient_name, $subject, $html_body, $text_body);
}

function sendPasswordResetOtpMessage($user, $otp) {
    $recipient_email = normalizeEmail($user['email'] ?? '');
    $recipient_name = trim((string) ($user['full_name'] ?? $user['username'] ?? ''));
    $expiry_minutes = EMAIL_VERIFICATION_OTP_EXPIRY_MINUTES;
    $site_name = SITE_NAME;

    $subject = $site_name . ' Password Reset Code';
    $html_body = '
        <div style="font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 24px; color: #0f172a;">
            <h2 style="margin: 0 0 12px; color: #16a34a;">Reset your password</h2>
            <p style="margin: 0 0 16px;">Hi ' . htmlspecialchars($recipient_name !== '' ? $recipient_name : 'there', ENT_QUOTES, 'UTF-8') . ',</p>
            <p style="margin: 0 0 18px;">Use this 6-digit OTP to continue your CoinRex password reset:</p>
            <div style="margin: 0 0 18px; padding: 18px; background: #0f172a; border-radius: 14px; text-align: center;">
                <span style="font-size: 32px; letter-spacing: 10px; font-weight: 700; color: #4ade80;">' . htmlspecialchars($otp, ENT_QUOTES, 'UTF-8') . '</span>
            </div>
            <p style="margin: 0 0 10px;">This code expires in ' . $expiry_minutes . ' minutes.</p>
            <p style="margin: 0 0 10px; color: #475569;">If you did not request a password reset, you can ignore this email.</p>
            <p style="margin: 0 0 6px; font-size: 13px; color: #64748b;">' . htmlspecialchars(MAIL_NO_REPLY_NOTICE, ENT_QUOTES, 'UTF-8') . '</p>
            <p style="margin: 0; font-size: 13px; color: #64748b;">For help, contact ' . htmlspecialchars(SITE_EMAIL, ENT_QUOTES, 'UTF-8') . '.</p>
        </div>';
    $text_body = "Reset your password\n\nYour CoinRex OTP is: {$otp}\n\nThis code expires in {$expiry_minutes} minutes.\n\n" . MAIL_NO_REPLY_NOTICE . "\nFor help, contact " . SITE_EMAIL . ".";

    return sendSmtpEmail($recipient_email, $recipient_name, $subject, $html_body, $text_body);
}

function startPendingEmailVerification($user) {
    $fresh_user = getUserById($user['id']);
    if (!$fresh_user) {
        return ['success' => false, 'message' => 'User not found for email verification'];
    }

    $now = time();
    $existing_user_id = $_SESSION['pending_verification_user_id'] ?? null;
    $last_sent_at = (int) ($_SESSION['pending_verification_last_sent_at'] ?? 0);
    $existing_expiry = !empty($fresh_user['otp_expiry']) ? strtotime((string) $fresh_user['otp_expiry']) : false;
    $has_recent_otp = $existing_user_id == ($user['id'] ?? null)
        && !empty($fresh_user['otp_code'])
        && $existing_expiry !== false
        && $existing_expiry > $now
        && ($now - $last_sent_at) < EMAIL_VERIFICATION_OTP_RESEND_COOLDOWN_SECONDS;

    if ($has_recent_otp) {
        return [
            'success' => true,
            'message' => 'A recent OTP is already active. Please check your inbox.',
            'otp_reused' => true,
        ];
    }

    $otp = generateEmailVerificationOtp();
    if (!storeEmailVerificationOtp($fresh_user['id'], $otp)) {
        return ['success' => false, 'message' => 'Failed to store verification OTP'];
    }

    $_SESSION['pending_verification_user_id'] = $fresh_user['id'];
    $_SESSION['pending_verification_email'] = $fresh_user['email'];

    $mail_result = sendEmailVerificationOtpMessage($fresh_user, $otp);
    $_SESSION['pending_verification_last_sent_at'] = $mail_result['success'] ? $now : 0;
    $_SESSION['pending_verification_mail_status'] = $mail_result;

    return $mail_result;
}

function clearPendingEmailVerification() {
    unset($_SESSION['pending_verification_user_id']);
    unset($_SESSION['pending_verification_email']);
    unset($_SESSION['pending_verification_last_sent_at']);
    unset($_SESSION['pending_verification_mail_status']);
}

function validatePasswordResetOtpSubmission($user, $otp) {
    $otp = preg_replace('/\D/', '', (string) $otp);
    $fresh_user = getUserById($user['id']);

    if (!$fresh_user) {
        return ['success' => false, 'message' => 'Password reset session expired. Please start again.'];
    }

    if (strlen($otp) !== EMAIL_VERIFICATION_OTP_LENGTH) {
        return ['success' => false, 'message' => 'Please enter the full 6-digit OTP'];
    }

    if ((int) ($fresh_user['otp_attempts'] ?? 0) >= EMAIL_VERIFICATION_OTP_MAX_ATTEMPTS) {
        return ['success' => false, 'message' => 'Too many incorrect attempts. Please request a new OTP.'];
    }

    if (empty($fresh_user['otp_code']) || empty($fresh_user['otp_expiry'])) {
        return ['success' => false, 'message' => 'No active OTP found. Please request a new code.'];
    }

    $expiry_timestamp = strtotime((string) $fresh_user['otp_expiry']);
    if ($expiry_timestamp === false || $expiry_timestamp < time()) {
        return ['success' => false, 'message' => 'This OTP has expired. Please request a new code.'];
    }

    if (!hash_equals((string) $fresh_user['otp_code'], $otp)) {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE users SET otp_attempts = otp_attempts + 1 WHERE id = ?");
        $stmt->execute([$fresh_user['id']]);

        $remaining_attempts = max(0, EMAIL_VERIFICATION_OTP_MAX_ATTEMPTS - ((int) ($fresh_user['otp_attempts'] ?? 0) + 1));
        $message = $remaining_attempts > 0
            ? 'Invalid OTP. ' . $remaining_attempts . ' attempt(s) remaining.'
            : 'Too many incorrect attempts. Please request a new OTP.';

        return ['success' => false, 'message' => $message];
    }

    $_SESSION['pending_password_reset_verified_at'] = time();

    return ['success' => true, 'user' => $fresh_user, 'message' => 'OTP verified successfully'];
}

function isPasswordResetOtpVerified() {
    return !empty($_SESSION['pending_password_reset_verified_at']);
}

function getEmailVerificationResendRemainingSeconds() {
    $last_sent_at = (int) ($_SESSION['pending_verification_last_sent_at'] ?? 0);
    if ($last_sent_at <= 0) {
        return 0;
    }

    $remaining = EMAIL_VERIFICATION_OTP_RESEND_COOLDOWN_SECONDS - (time() - $last_sent_at);
    return max(0, $remaining);
}

function validateEmailVerificationOtpSubmission($user, $otp) {
    $otp = preg_replace('/\D/', '', (string) $otp);
    $fresh_user = getUserById($user['id']);

    if (!$fresh_user) {
        return ['success' => false, 'message' => 'Verification session expired. Please sign in again.'];
    }

    if ((int) ($fresh_user['email_verified'] ?? 0) === 1) {
        return ['success' => true, 'already_verified' => true, 'user' => $fresh_user, 'message' => 'Email already verified'];
    }

    if (strlen($otp) !== EMAIL_VERIFICATION_OTP_LENGTH) {
        return ['success' => false, 'message' => 'Please enter the full 6-digit OTP'];
    }

    if ((int) ($fresh_user['otp_attempts'] ?? 0) >= EMAIL_VERIFICATION_OTP_MAX_ATTEMPTS) {
        return ['success' => false, 'message' => 'Too many incorrect attempts. Please request a new OTP.'];
    }

    if (empty($fresh_user['otp_code']) || empty($fresh_user['otp_expiry'])) {
        return ['success' => false, 'message' => 'No active OTP found. Please request a new code.'];
    }

    $expiry_timestamp = strtotime((string) $fresh_user['otp_expiry']);
    if ($expiry_timestamp === false || $expiry_timestamp < time()) {
        return ['success' => false, 'message' => 'This OTP has expired. Please request a new code.'];
    }

    if (!hash_equals((string) $fresh_user['otp_code'], $otp)) {
        $db = getDBConnection();
        $stmt = $db->prepare("UPDATE users SET otp_attempts = otp_attempts + 1 WHERE id = ?");
        $stmt->execute([$fresh_user['id']]);

        $remaining_attempts = max(0, EMAIL_VERIFICATION_OTP_MAX_ATTEMPTS - ((int) ($fresh_user['otp_attempts'] ?? 0) + 1));
        $message = $remaining_attempts > 0
            ? 'Invalid OTP. ' . $remaining_attempts . ' attempt(s) remaining.'
            : 'Too many incorrect attempts. Please request a new OTP.';

        return ['success' => false, 'message' => $message];
    }

    return ['success' => true, 'user' => $fresh_user, 'message' => 'OTP verified successfully'];
}

function markEmailAsVerified($user_id) {
    $db = getDBConnection();
    $stmt = $db->prepare("
        UPDATE users
        SET email_verified = 1,
            email_verified_at = NOW(),
            otp_code = NULL,
            otp_expiry = NULL,
            otp_attempts = 0
        WHERE id = ?
    ");

    return $stmt->execute([$user_id]);
}
