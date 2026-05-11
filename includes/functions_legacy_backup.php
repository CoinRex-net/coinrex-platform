<?php
/**
 * CoinRex Helper Functions
 * Location: /coinrex/includes/functions.php
 */

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Normalize email input for consistent lookups/storage
function normalizeEmail($email) {
    return strtolower(trim($email));
}

// Normalize referral codes for consistent lookups
function normalizeReferralCode($code) {
    return strtoupper(trim((string)$code));
}

// Extract a normalized domain from an email address
function getEmailDomain($email) {
    $email = normalizeEmail($email);

    if ($email === '' || strpos($email, '@') === false) {
        return '';
    }

    $parts = explode('@', $email);
    return trim(end($parts), ". \t\n\r\0\x0B");
}

// Common disposable email domains blocked during registration
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

// Block registrations that use disposable email providers
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

// Validate password strength against registration policy
function validatePasswordPolicy($password) {
    $requirements = [
        'length' => strlen($password) >= 9,
        'uppercase' => preg_match('/[A-Z]/', $password) === 1,
        'digit' => preg_match('/\d/', $password) === 1,
        'special' => preg_match('/[^A-Za-z0-9]/', $password) === 1,
    ];
    
    return [
        'is_valid' => !in_array(false, $requirements, true),
        'requirements' => $requirements,
    ];
}

// Generate unique referral code
function generateReferralCode($length = 8) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $code;
}

// Generate username from email or full name
function generateUsername($fullname, $email) {
    // Try full name first
    $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', str_replace(' ', '', $fullname)));
    if (strlen($base) < 3) {
        $base = explode('@', $email)[0];
    }
    
    $username = $base;
    $counter = 1;
    
    $db = getDBConnection();
    while (true) {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            break;
        }
        $username = $base . $counter;
        $counter++;
    }
    
    return $username;
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Get user by email
function getUserByEmail($email) {
    $db = getDBConnection();
    $email = normalizeEmail($email);
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

// Get user by id
function getUserById($user_id) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Get user by username
function getUserByUsername($username) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

// Resolve a user by either email address or username
function getUserByIdentifier($identifier) {
    $identifier = trim((string) $identifier);
    if ($identifier === '') {
        return null;
    }

    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
        return getUserByEmail($identifier);
    }

    return getUserByUsername($identifier);
}

// Get user by referral code
function getUserByReferralCode($code) {
    $db = getDBConnection();
    $code = normalizeReferralCode($code);
    $stmt = $db->prepare("SELECT * FROM users WHERE referral_code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch();
}

// Check whether a database table contains a specific column
function tableHasColumn($table_name, $column_name) {
    static $column_cache = [];

    $cache_key = $table_name . '.' . $column_name;
    if (array_key_exists($cache_key, $column_cache)) {
        return $column_cache[$cache_key];
    }

    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([DB_NAME, $table_name, $column_name]);

    $exists = ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
    $column_cache[$cache_key] = $exists;

    return $exists;
}

// Validate optional referral code input
function validateReferralCode($code) {
    $code = normalizeReferralCode($code);

    if ($code === '') {
        return [
            'valid' => true,
            'exists' => false,
            'message' => '',
            'code' => '',
        ];
    }

    if (!preg_match('/^[A-Z0-9]{6,16}$/', $code)) {
        return [
            'valid' => false,
            'exists' => false,
            'message' => 'Referral code format is invalid',
            'code' => $code,
        ];
    }

    $referrer = getUserByReferralCode($code);

    if (!$referrer) {
        return [
            'valid' => false,
            'exists' => false,
            'message' => 'Referral code not found',
            'code' => $code,
        ];
    }

    return [
        'valid' => true,
        'exists' => true,
        'message' => 'Referral code applied successfully',
        'code' => $code,
        'referrer' => $referrer,
    ];
}

// Lightweight session flash messaging
function setFlashMessage($key, $message) {
    $_SESSION['_flash'][$key] = $message;
}

function consumeFlashMessage($key) {
    if (!isset($_SESSION['_flash'][$key])) {
        return '';
    }

    $message = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    if (empty($_SESSION['_flash'])) {
        unset($_SESSION['_flash']);
    }

    return $message;
}

// Create a six-digit OTP for email verification
function generateEmailVerificationOtp() {
    return str_pad((string) random_int(0, 999999), EMAIL_VERIFICATION_OTP_LENGTH, '0', STR_PAD_LEFT);
}

// Store a new verification OTP on the user record
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

// Check whether SMTP credentials are ready for live OTP delivery
function isMailConfigured() {
    return MAIL_SMTP_USERNAME !== ''
        && MAIL_SMTP_PASSWORD !== ''
        && MAIL_FROM_EMAIL !== '';
}

// Send an email through PHPMailer + SMTP
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

// Send the login OTP email for account verification
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

// Send the password reset OTP email
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

// Keep pending email-verification state in session until the verify page is built
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

if (!defined('REMEMBER_ME_COOKIE_NAME')) {
    define('REMEMBER_ME_COOKIE_NAME', 'coinrex_remember');
}

if (!defined('REMEMBER_ME_LIFETIME_SECONDS')) {
    define('REMEMBER_ME_LIFETIME_SECONDS', 10 * 24 * 60 * 60);
}

// Start password reset OTP delivery for the selected user
function startPendingPasswordReset($user) {
    $fresh_user = getUserById($user['id']);
    if (!$fresh_user) {
        return ['success' => false, 'message' => 'User not found for password reset'];
    }

    $now = time();
    $existing_user_id = $_SESSION['pending_password_reset_user_id'] ?? null;
    $last_sent_at = (int) ($_SESSION['pending_password_reset_last_sent_at'] ?? 0);
    $existing_expiry = !empty($fresh_user['otp_expiry']) ? strtotime((string) $fresh_user['otp_expiry']) : false;
    $has_recent_otp = $existing_user_id == ($fresh_user['id'] ?? null)
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
        return ['success' => false, 'message' => 'Failed to store password reset OTP'];
    }

    $_SESSION['pending_password_reset_user_id'] = $fresh_user['id'];
    $_SESSION['pending_password_reset_email'] = $fresh_user['email'];
    unset($_SESSION['pending_password_reset_verified_at']);

    $mail_result = sendPasswordResetOtpMessage($fresh_user, $otp);
    $_SESSION['pending_password_reset_last_sent_at'] = $mail_result['success'] ? $now : 0;
    $_SESSION['pending_password_reset_mail_status'] = $mail_result;

    return $mail_result;
}

function clearPendingPasswordReset() {
    unset($_SESSION['pending_password_reset_user_id']);
    unset($_SESSION['pending_password_reset_email']);
    unset($_SESSION['pending_password_reset_last_sent_at']);
    unset($_SESSION['pending_password_reset_mail_status']);
    unset($_SESSION['pending_password_reset_verified_at']);
}

function getPendingPasswordResetUser() {
    $user_id = $_SESSION['pending_password_reset_user_id'] ?? null;
    if (!$user_id) {
        return null;
    }

    $user = getUserById($user_id);
    if (!$user) {
        clearPendingPasswordReset();
        return null;
    }

    return $user;
}

function getPasswordResetResendRemainingSeconds() {
    $last_sent_at = (int) ($_SESSION['pending_password_reset_last_sent_at'] ?? 0);
    if ($last_sent_at <= 0) {
        return 0;
    }

    $remaining = EMAIL_VERIFICATION_OTP_RESEND_COOLDOWN_SECONDS - (time() - $last_sent_at);
    return max(0, $remaining);
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

function resetUserPassword($user_id, $password) {
    $db = getDBConnection();
    $hashed_password = hashPassword($password);
    $stmt = $db->prepare("
        UPDATE users
        SET password = ?,
            otp_code = NULL,
            otp_expiry = NULL,
            otp_attempts = 0,
            login_attempts = 0,
            updated_at = NOW()
        WHERE id = ?
    ");

    return $stmt->execute([$hashed_password, $user_id]);
}

// Resolve the currently pending email-verification user from session
function getPendingVerificationUser() {
    $user_id = $_SESSION['pending_verification_user_id'] ?? null;
    if (!$user_id) {
        return null;
    }

    $user = getUserById($user_id);
    if (!$user) {
        clearPendingEmailVerification();
        return null;
    }

    return $user;
}

// Check whether the resend cooldown has elapsed
function getEmailVerificationResendRemainingSeconds() {
    $last_sent_at = (int) ($_SESSION['pending_verification_last_sent_at'] ?? 0);
    if ($last_sent_at <= 0) {
        return 0;
    }

    $remaining = EMAIL_VERIFICATION_OTP_RESEND_COOLDOWN_SECONDS - (time() - $last_sent_at);
    return max(0, $remaining);
}

// Validate the submitted OTP against the database-backed fields
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

// Finalize email verification on the user record
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

// Update login metadata and establish the application session
function establishAuthenticatedSession($user, $remember = false) {
    $db = getDBConnection();
    ensureRememberMeSchema($db);

    $update = "UPDATE users SET 
                login_attempts = 0,
                last_login = NOW(),
                last_ip = ?,
                last_active = NOW()
               WHERE id = ?";
    $stmt = $db->prepare($update);
    $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? null, $user['id']]);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['level'] = $user['level'];
    clearPendingEmailVerification();

    if ($remember) {
        issueRememberMeToken((int) $user['id'], $db);
    } else {
        clearRememberMeTokenForUser((int) $user['id'], $db);
    }
}

// Get current user ID
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

// Check if user is verified developer
function isVerifiedDeveloper($user_id) {
    if (!$user_id) {
        return false;
    }

    $db = getDBConnection();

    $stmt = $db->prepare("
        SELECT status
        FROM developer_verification
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $verification = $stmt->fetch();

    if ($verification && isset($verification['status'])) {
        $status = strtolower(trim((string) $verification['status']));
        if ($status === 'approved') {
            return true;
        }
    }

    $stmt = $db->prepare("
        SELECT is_developer_verified, has_verified_badge
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    return (int) ($user['is_developer_verified'] ?? 0) === 1
        || (int) ($user['has_verified_badge'] ?? 0) === 1;
}

// Get DevHub database connection
function getDevHubDB() {
    return getDBConnection();
}

// Process new user registration
function registerUser($full_name, $email, $password, $referral_code = null) {
    $db = getDBConnection();
    $email = normalizeEmail($email);
    $referral_code = normalizeReferralCode($referral_code);
    ensureRewardClaimSchema($db);
    
    if (isDisposableEmail($email)) {
        return ['success' => false, 'message' => 'Temporary email addresses are not allowed'];
    }

    // Check if email exists
    if (getUserByEmail($email)) {
        return ['success' => false, 'message' => 'Email already registered'];
    }

    $password_validation = validatePasswordPolicy($password);
    if (!$password_validation['is_valid']) {
        return ['success' => false, 'message' => 'Password does not meet security requirements'];
    }
    
    // Generate username
    $username = generateUsername($full_name, $email);
    
    // Generate unique referral code
    $user_referral_code = generateReferralCode();
    
    // Hash password
    $hashed_password = hashPassword($password);
    
    // Get IP address
    $signup_ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Check referral
    $referred_by = null;
    $referral_bonus = 0;
    
    if ($referral_code) {
        $referral_validation = validateReferralCode($referral_code);
        if (!$referral_validation['valid']) {
            return ['success' => false, 'message' => $referral_validation['message']];
        }

        $referred_by = $referral_validation['referrer']['id'];
        $referral_bonus = REFERRAL_BONUS_REX;
    }
    
    // Calculate total bonus
    $total_bonus = WELCOME_BONUS_REX + $referral_bonus;

    try {
        $db->beginTransaction();

        $sql = "INSERT INTO users (
            full_name, email, password, username, referral_code, referred_by,
            rex_balance, total_rex_earned, signup_ip, user_agent, status, email_verified
        ) VALUES (
            ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, 'active', FALSE
        )";

        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $full_name, $email, $hashed_password, $username,
            $user_referral_code, $referred_by,
            $signup_ip, $user_agent
        ]);

        if (!$result) {
            throw new RuntimeException('Registration failed');
        }

        $user_id = (int) $db->lastInsertId();

        if ($referred_by) {
            $update = "UPDATE users SET total_referrals = total_referrals + 1 WHERE id = ?";
            $stmt = $db->prepare($update);
            $stmt->execute([$referred_by]);
        }

        addRewardLedgerEntry($user_id, WELCOME_BONUS_REX, 'bonus', 'welcome_bonus', 'available', 'welcome_bonus:' . $user_id, $db, 'phase1', 'beginner');
        if ($referral_bonus > 0) {
            addRewardLedgerEntry($user_id, $referral_bonus, 'bonus', 'referral_signup_bonus', 'available', 'referral_signup:' . $user_id, $db, 'phase1', 'beginner');
        }

        $db->commit();

        return [
            'success' => true,
            'user_id' => $user_id,
            'username' => $username,
            'bonus' => $total_bonus,
            'message' => 'Registration successful!'
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

if (!defined('APP_CSRF_SESSION_KEY')) {
    define('APP_CSRF_SESSION_KEY', '_app_csrf_token');
}

function appCsrfToken() {
    if (empty($_SESSION[APP_CSRF_SESSION_KEY])) {
        $_SESSION[APP_CSRF_SESSION_KEY] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION[APP_CSRF_SESSION_KEY];
}

function validateAppCsrfToken($token) {
    $session_token = $_SESSION[APP_CSRF_SESSION_KEY] ?? '';

    return is_string($token)
        && is_string($session_token)
        && $token !== ''
        && hash_equals($session_token, $token);
}

function requireAppCsrf($token) {
    if (!validateAppCsrfToken($token)) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

function tableExists($table_name) {
    static $table_cache = [];

    $cache_key = (string) $table_name;
    if (array_key_exists($cache_key, $table_cache)) {
        return $table_cache[$cache_key];
    }

    $db = getDBConnection();
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
    ");
    $stmt->execute([DB_NAME, $table_name]);

    $exists = ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
    $table_cache[$cache_key] = $exists;

    return $exists;
}

function ensureRememberMeSchema(PDO $db = null) {
    static $schema_ready = false;

    if ($schema_ready) {
        return;
    }

    $db = $db ?: getDBConnection();

    $table_stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'users'
    ");
    $table_stmt->execute([DB_NAME]);
    $users_table_exists = ((int) ($table_stmt->fetch()['total'] ?? 0)) > 0;

    if (!$users_table_exists) {
        return;
    }

    if (!rememberMeColumnsExist($db, ['remember_token_hash'])) {
        $db->exec("ALTER TABLE users ADD COLUMN remember_token_hash VARCHAR(255) NULL AFTER last_active");
    }

    if (!rememberMeColumnsExist($db, ['remember_token_expires_at'])) {
        $db->exec("ALTER TABLE users ADD COLUMN remember_token_expires_at DATETIME NULL AFTER remember_token_hash");
    }

    $schema_ready = true;
}

function ensureRewardClaimSchema(PDO $db = null) {
    static $schema_ready = false;

    if ($schema_ready) {
        return;
    }

    $db = $db ?: getDBConnection();

    if (!tableExists('users')) {
        return;
    }

    if (!tableHasColumn('users', 'wallet_address')) {
        $db->exec("ALTER TABLE users ADD COLUMN wallet_address VARCHAR(100) NULL AFTER country");
    }

    if (!tableHasColumn('users', 'reward_frozen')) {
        $db->exec("ALTER TABLE users ADD COLUMN reward_frozen TINYINT(1) NOT NULL DEFAULT 0 AFTER wallet_address");
    }

    if (!tableHasColumn('users', 'current_day')) {
        $db->exec("ALTER TABLE users ADD COLUMN current_day TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER reward_frozen");
    }

    if (!tableHasColumn('users', 'last_day_completed_at')) {
        $db->exec("ALTER TABLE users ADD COLUMN last_day_completed_at DATETIME NULL AFTER current_day");
    }

    if (!tableHasColumn('users', 'profile_completed_at')) {
        $db->exec("ALTER TABLE users ADD COLUMN profile_completed_at DATETIME NULL AFTER last_day_completed_at");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS reward_ledger (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            source VARCHAR(50) NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            amount DECIMAL(18,8) NOT NULL,
            status ENUM('pending','locked','available','claimed') NOT NULL DEFAULT 'pending',
            reference_id VARCHAR(100) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_reward_ledger_user_status (user_id, status),
            KEY idx_reward_ledger_source (source),
            KEY idx_reward_ledger_reference (reference_id),
            KEY idx_reward_ledger_created_at (created_at),
            CONSTRAINT fk_reward_ledger_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!tableHasColumn('reward_ledger', 'reward_phase')) {
        $db->exec("ALTER TABLE reward_ledger ADD COLUMN reward_phase ENUM('phase1','phase2') NOT NULL DEFAULT 'phase1' AFTER source");
    }

    if (!tableHasColumn('reward_ledger', 'user_level_at_time')) {
        $db->exec("ALTER TABLE reward_ledger ADD COLUMN user_level_at_time VARCHAR(20) NULL AFTER reference_id");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS claim_snapshots (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            total_amount DECIMAL(18,8) NOT NULL,
            nonce BIGINT UNSIGNED NOT NULL,
            status ENUM('generated','used','expired') NOT NULL DEFAULT 'generated',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_claim_snapshots_nonce (nonce),
            KEY idx_claim_snapshots_user_status (user_id, status),
            KEY idx_claim_snapshots_created_at (created_at),
            CONSTRAINT fk_claim_snapshots_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS mini_tasks (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(100) NOT NULL,
            description TEXT NULL,
            reward DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            daily_limit INT NOT NULL DEFAULT 1,
            cooldown_seconds INT NOT NULL DEFAULT 86400,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY idx_mini_tasks_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    if (!tableHasColumn('mini_tasks', 'task_key')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN task_key VARCHAR(120) NULL AFTER title");
    }

    if (!tableHasColumn('mini_tasks', 'task_group')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN task_group VARCHAR(30) NOT NULL DEFAULT 'legacy' AFTER task_key");
    }

    if (!tableHasColumn('mini_tasks', 'mission_day')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN mission_day TINYINT UNSIGNED NULL AFTER task_group");
    }

    if (!tableHasColumn('mini_tasks', 'mission_step')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN mission_step TINYINT UNSIGNED NULL AFTER mission_day");
    }

    if (!tableHasColumn('mini_tasks', 'unlock_after_hours')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN unlock_after_hours INT NOT NULL DEFAULT 0 AFTER mission_step");
    }

    if (!tableHasColumn('mini_tasks', 'verification_mode')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN verification_mode VARCHAR(30) NOT NULL DEFAULT 'instant' AFTER unlock_after_hours");
    }

    if (!tableHasColumn('mini_tasks', 'requires_quiz')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN requires_quiz TINYINT(1) NOT NULL DEFAULT 0 AFTER verification_mode");
    }

    if (!tableHasColumn('mini_tasks', 'requires_manual_review')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN requires_manual_review TINYINT(1) NOT NULL DEFAULT 0 AFTER requires_quiz");
    }

    if (!tableHasColumn('mini_tasks', 'min_quiz_score')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN min_quiz_score TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER requires_manual_review");
    }

    if (!tableHasColumn('mini_tasks', 'task_category')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN task_category VARCHAR(40) NOT NULL DEFAULT 'custom' AFTER min_quiz_score");
    }

    if (!tableHasColumn('mini_tasks', 'task_link')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN task_link VARCHAR(255) NULL AFTER task_category");
    }

    if (!tableHasColumn('mini_tasks', 'completion_steps')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN completion_steps TEXT NULL AFTER task_link");
    }

    if (!tableHasColumn('mini_tasks', 'proof_notes')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN proof_notes TEXT NULL AFTER completion_steps");
    }

    if (!tableHasColumn('mini_tasks', 'cta_label')) {
        $db->exec("ALTER TABLE mini_tasks ADD COLUMN cta_label VARCHAR(80) NULL AFTER proof_notes");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS user_task_logs (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            task_id INT UNSIGNED NOT NULL,
            completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status ENUM('completed','blocked') NOT NULL DEFAULT 'completed',
            PRIMARY KEY (id),
            KEY idx_user_task_logs_user_status (user_id, status),
            KEY idx_user_task_logs_task_status (task_id, status),
            KEY idx_user_task_logs_completed_at (completed_at),
            CONSTRAINT fk_user_task_logs_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_user_task_logs_task
                FOREIGN KEY (task_id) REFERENCES mini_tasks(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $status_definition = $db->query("SHOW COLUMNS FROM user_task_logs LIKE 'status'")->fetch();
    if ($status_definition && strpos((string) ($status_definition['Type'] ?? ''), "'pending'") === false) {
        $db->exec("ALTER TABLE user_task_logs MODIFY COLUMN status ENUM('pending','submitted','completed','blocked','failed') NOT NULL DEFAULT 'completed'");
    }

    if (!tableHasColumn('user_task_logs', 'task_completed_at')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN task_completed_at DATETIME NULL AFTER completed_at");
    }

    if (!tableHasColumn('user_task_logs', 'task_available_at')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN task_available_at DATETIME NULL AFTER task_completed_at");
    }

    if (!tableHasColumn('user_task_logs', 'mission_day')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN mission_day TINYINT UNSIGNED NULL AFTER task_available_at");
    }

    if (!tableHasColumn('user_task_logs', 'mission_step')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN mission_step TINYINT UNSIGNED NULL AFTER mission_day");
    }

    if (!tableHasColumn('user_task_logs', 'attempt_no')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN attempt_no INT UNSIGNED NOT NULL DEFAULT 1 AFTER mission_step");
    }

    if (!tableHasColumn('user_task_logs', 'proof_data')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN proof_data TEXT NULL AFTER attempt_no");
    }

    if (!tableHasColumn('user_task_logs', 'score')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN score TINYINT UNSIGNED NULL AFTER proof_data");
    }

    if (!tableHasColumn('user_task_logs', 'metadata')) {
        $db->exec("ALTER TABLE user_task_logs ADD COLUMN metadata LONGTEXT NULL AFTER score");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS taskhub_quiz_attempts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            task_id INT UNSIGNED NOT NULL,
            mission_day TINYINT UNSIGNED NOT NULL,
            score TINYINT UNSIGNED NOT NULL DEFAULT 0,
            total_questions TINYINT UNSIGNED NOT NULL DEFAULT 0,
            answers_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_taskhub_quiz_attempts_user_task (user_id, task_id),
            CONSTRAINT fk_taskhub_quiz_attempts_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
            CONSTRAINT fk_taskhub_quiz_attempts_task
                FOREIGN KEY (task_id) REFERENCES mini_tasks(id)
                ON DELETE CASCADE
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $task_count = (int) ($db->query("SELECT COUNT(*) FROM mini_tasks")->fetchColumn() ?: 0);
    if ($task_count === 0) {
        $db->exec("
            INSERT INTO mini_tasks (title, description, reward, daily_limit, cooldown_seconds, is_active) VALUES
            ('Daily Check-In', 'Return to CoinRex and keep your beginner streak alive.', 1.0000, 1, 86400, 1),
            ('Explore Projects', 'Browse listed projects and stay active in the ecosystem.', 1.5000, 1, 86400, 1),
            ('Profile Warmup', 'Keep your profile active while your account builds trust.', 2.0000, 1, 86400, 1)
        ");
    }

    $mission_tasks = getTaskHubMissionDefinitions();
    foreach ($mission_tasks as $mission_task) {
        $select_task = $db->prepare("SELECT id FROM mini_tasks WHERE task_key = ? LIMIT 1");
        $select_task->execute([(string) $mission_task['task_key']]);
        $existing_task = $select_task->fetch();

        $params = [
            (string) $mission_task['title'],
            (string) $mission_task['task_key'],
            'mission',
            (int) $mission_task['day'],
            (int) $mission_task['step'],
            (float) $mission_task['reward'],
            (int) $mission_task['daily_limit'],
            (int) $mission_task['cooldown_seconds'],
            (int) $mission_task['unlock_after_hours'],
            (string) $mission_task['verification_mode'],
            !empty($mission_task['requires_quiz']) ? 1 : 0,
            !empty($mission_task['requires_manual_review']) ? 1 : 0,
            (int) ($mission_task['min_quiz_score'] ?? 0),
            !empty($mission_task['is_active']) ? 1 : 0,
            (string) $mission_task['description'],
        ];

        if (!$existing_task) {
            $insert_task = $db->prepare("
                INSERT INTO mini_tasks (
                    title, task_key, task_group, mission_day, mission_step, reward, daily_limit, cooldown_seconds,
                    unlock_after_hours, verification_mode, requires_quiz, requires_manual_review, min_quiz_score, is_active, description
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_task->execute($params);
        }
    }

    $deprecated_task_keys = [
        'day4_boosthub',
        'day5_boosthub',
        'day6_boosthub',
        'day7_boosthub',
        'day8_boosthub',
        'day9_boosthub',
        'day10_boosthub',
        'day6_claim_awareness',
        'day8_wallet_familiarity',
        'day9_wallet_familiarity'
    ];
    $placeholders = implode(',', array_fill(0, count($deprecated_task_keys), '?'));
    $deprecate_stmt = $db->prepare("UPDATE mini_tasks SET is_active = 0 WHERE task_key IN ($placeholders)");
    $deprecate_stmt->execute($deprecated_task_keys);

    $schema_ready = true;
}

function normalizeLedgerText($value, $max_length) {
    return substr(trim((string) $value), 0, (int) $max_length);
}

function normalizeRewardLedgerSource($source) {
    $source = strtolower(trim((string) $source));
    $allowed_sources = ['mini_task', 'referral', 'review', 'bonus'];
    return in_array($source, $allowed_sources, true) ? $source : 'bonus';
}

function normalizeRewardPhase($phase) {
    $phase = strtolower(trim((string) $phase));
    return in_array($phase, ['phase1', 'phase2'], true) ? $phase : 'phase1';
}

function resolveRewardPhase($source, $user_level = null) {
    $source = normalizeRewardLedgerSource($source);
    $user_level = normalizeUserLevel($user_level ?? 'beginner');

    if ($source === 'mini_task' || $user_level === 'beginner') {
        return 'phase1';
    }

    return 'phase2';
}

function normalizeLedgerStatus($status) {
    $status = strtolower(trim((string) $status));
    $allowed_statuses = ['pending', 'locked', 'available', 'claimed'];
    return in_array($status, $allowed_statuses, true) ? $status : 'pending';
}

function getLedgerDisplayBalance($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND status IN ('available', 'locked', 'claimed')
    ");
    $stmt->execute([(int) $user_id]);
    return round((float) ($stmt->fetch()['total'] ?? 0), 8);
}

function syncLegacyRewardCache($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $available_display = getLedgerDisplayBalance($user_id, $db);

    $earned_stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND status IN ('available', 'locked', 'claimed')
    ");
    $earned_stmt->execute([$user_id]);
    $earned_total = round((float) ($earned_stmt->fetch()['total'] ?? 0), 8);

    $update = $db->prepare("
        UPDATE users
        SET rex_balance = ?,
            total_rex_earned = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$available_display, max(0, $earned_total), $user_id]);
    return true;
}

function addRewardLedgerEntry($user_id, $amount, $source, $action_type = 'credit', $status = 'available', $reference_id = null, PDO $db = null, $reward_phase = null, $user_level_at_time = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    $amount = round((float) $amount, 8);
    $source = normalizeRewardLedgerSource($source);
    $action_type = normalizeLedgerText($action_type, 50);
    $status = normalizeLedgerStatus($status);
    $reference_id = $reference_id !== null && trim((string) $reference_id) !== ''
        ? normalizeLedgerText($reference_id, 100)
        : null;
    $user_level_at_time = normalizeUserLevel($user_level_at_time ?? (getUserById($user_id)['level'] ?? 'beginner'));
    $reward_phase = normalizeRewardPhase($reward_phase ?? resolveRewardPhase($source, $user_level_at_time));

    if ($user_id <= 0) {
        throw new InvalidArgumentException('Invalid user ID.');
    }

    if ($amount == 0.0) {
        throw new InvalidArgumentException('Reward amount must not be zero.');
    }

    if ($source === '') {
        throw new InvalidArgumentException('Reward source is required.');
    }

    if ($action_type === '') {
        $action_type = 'credit';
    }

    $stmt = $db->prepare("
        INSERT INTO reward_ledger (user_id, source, reward_phase, action_type, amount, status, reference_id, user_level_at_time)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $source, $reward_phase, $action_type, $amount, $status, $reference_id, $user_level_at_time]);

    syncLegacyRewardCache($user_id, $db);

    return [
        'id' => (int) $db->lastInsertId(),
        'user_id' => $user_id,
        'amount' => number_format($amount, 8, '.', ''),
        'source' => $source,
        'reward_phase' => $reward_phase,
        'action_type' => $action_type,
        'status' => $status,
        'reference_id' => $reference_id,
        'user_level_at_time' => $user_level_at_time,
    ];
}

function getRewardLedgerBalance($user_id, $status = 'available', PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return 0.0;
    }

    $status = normalizeLedgerStatus($status);
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND status = ?
    ");
    $stmt->execute([$user_id, $status]);

    return round((float) ($stmt->fetch()['total'] ?? 0), 8);
}

function generateUniqueClaimNonce(PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    do {
        $nonce = (string) random_int(1000000000000000000, 9223372036854775807);
        $stmt = $db->prepare("SELECT id FROM claim_snapshots WHERE nonce = ? LIMIT 1");
        $stmt->execute([$nonce]);
        $exists = $stmt->fetch();
    } while ($exists);

    return $nonce;
}

function generateClaimSnapshotForUser($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        throw new InvalidArgumentException('Invalid user ID.');
    }

    $eligibility = getClaimEligibility($user_id, $db);
    if (empty($eligibility['eligible'])) {
        throw new RuntimeException((string) ($eligibility['message'] ?? 'Claim requirements are not met.'));
    }

    try {
        $db->beginTransaction();

        $claim_check = $db->prepare("
            SELECT id, nonce, total_amount, status, created_at
            FROM claim_snapshots
            WHERE user_id = ?
              AND status = 'generated'
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        $claim_check->execute([$user_id]);
        $open_snapshot = $claim_check->fetch();

        if ($open_snapshot) {
            throw new RuntimeException('A claim is already prepared for this account.');
        }

        $ledger_stmt = $db->prepare("
            SELECT id, amount
            FROM reward_ledger
            WHERE user_id = ?
              AND status = 'available'
            ORDER BY id ASC
            FOR UPDATE
        ");
        $ledger_stmt->execute([$user_id]);
        $rows = $ledger_stmt->fetchAll();

        if (empty($rows)) {
            throw new RuntimeException('No available rewards found for claim preparation.');
        }

        $total_amount = 0.0;
        $ledger_ids = [];
        foreach ($rows as $row) {
            $total_amount += (float) ($row['amount'] ?? 0);
            $ledger_ids[] = (int) $row['id'];
        }
        $total_amount = round($total_amount, 8);

        if ($total_amount <= 0) {
            throw new RuntimeException('Claim amount must be greater than zero.');
        }

        $nonce = generateUniqueClaimNonce($db);
        $insert_snapshot = $db->prepare("
            INSERT INTO claim_snapshots (user_id, total_amount, nonce, status)
            VALUES (?, ?, ?, 'generated')
        ");
        $insert_snapshot->execute([$user_id, $total_amount, $nonce]);
        $snapshot_id = (int) $db->lastInsertId();

        $placeholders = implode(',', array_fill(0, count($ledger_ids), '?'));
        $update_params = array_merge([$user_id], $ledger_ids);
        $lock_rewards = $db->prepare("
            UPDATE reward_ledger
            SET status = 'locked'
            WHERE user_id = ?
              AND status = 'available'
              AND id IN ($placeholders)
        ");
        $lock_rewards->execute($update_params);

        if ($lock_rewards->rowCount() !== count($ledger_ids)) {
            throw new RuntimeException('Unable to lock every reward row for this claim.');
        }

        $db->commit();

        return [
            'snapshot_id' => $snapshot_id,
            'user_id' => $user_id,
            'amount' => number_format($total_amount, 8, '.', ''),
            'nonce' => $nonce,
            'status' => 'generated',
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function getClaimSnapshotStatus($snapshot_id, $user_id = null, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $snapshot_id = (int) $snapshot_id;
    if ($snapshot_id <= 0) {
        throw new InvalidArgumentException('Invalid snapshot ID.');
    }

    $sql = "
        SELECT id, user_id, total_amount, nonce, status, created_at
        FROM claim_snapshots
        WHERE id = ?
    ";
    $params = [$snapshot_id];

    if ($user_id !== null) {
        $sql .= " AND user_id = ?";
        $params[] = (int) $user_id;
    }

    $sql .= " LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $snapshot = $stmt->fetch();

    if (!$snapshot) {
        throw new RuntimeException('Claim snapshot not found.');
    }

    return [
        'id' => (int) $snapshot['id'],
        'user_id' => (int) $snapshot['user_id'],
        'amount' => number_format((float) $snapshot['total_amount'], 8, '.', ''),
        'nonce' => (string) $snapshot['nonce'],
        'status' => (string) $snapshot['status'],
        'created_at' => (string) $snapshot['created_at'],
    ];
}

function getClientIpAddress() {
    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function getUserMiniTaskStats($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    $stmt = $db->prepare("
        SELECT
            COUNT(*) AS completed_total,
            SUM(CASE WHEN DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END) AS completed_today,
            SUM(CASE WHEN status = 'blocked' AND DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END) AS blocked_today
        FROM user_task_logs
        WHERE user_id = ?
          AND status IN ('completed', 'blocked')
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch() ?: [];

    return [
        'completed_total' => (int) ($stats['completed_total'] ?? 0),
        'completed_today' => (int) ($stats['completed_today'] ?? 0),
        'blocked_today' => (int) ($stats['blocked_today'] ?? 0),
    ];
}

function getUserSecuritySignals($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $user = getUserById((int) $user_id);

    if (!$user) {
        return [
            'is_suspicious' => true,
            'reasons' => ['User record not found.'],
            'matching_accounts' => 0,
        ];
    }

    $signup_ip = trim((string) ($user['signup_ip'] ?? ''));
    $last_ip = trim((string) ($user['last_ip'] ?? ''));
    $user_agent = trim((string) ($user['user_agent'] ?? ''));
    $ips = array_values(array_unique(array_filter([$signup_ip, $last_ip], static function ($value) {
        return trim((string) $value) !== '';
    })));

    $matching_accounts = 0;
    if (!empty($ips)) {
        $placeholders = implode(',', array_fill(0, count($ips), '?'));
        $params = array_merge([(int) $user_id], $ips, $ips);
        $stmt = $db->prepare("
            SELECT COUNT(*) AS total
            FROM users
            WHERE id <> ?
              AND (
                signup_ip IN ($placeholders)
                OR last_ip IN ($placeholders)
              )
        ");
        $stmt->execute($params);
        $matching_accounts = (int) ($stmt->fetch()['total'] ?? 0);
    }

    $reasons = [];
    if ($matching_accounts >= ANTI_FARM_MAX_ACCOUNTS_PER_IP) {
        $reasons[] = 'Multiple accounts detected from the same IP range.';
    }
    if ((int) ($user['login_attempts'] ?? 0) >= ANTI_FARM_MAX_LOGIN_ATTEMPTS) {
        $reasons[] = 'Excessive login attempts detected.';
    }
    if ($user_agent === '') {
        $reasons[] = 'Missing browser fingerprint information.';
    }

    return [
        'is_suspicious' => !empty($reasons),
        'reasons' => $reasons,
        'matching_accounts' => $matching_accounts,
        'signup_ip' => $signup_ip,
        'last_ip' => $last_ip,
        'user_agent' => $user_agent,
    ];
}

function canReferralBecomeValid($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $task_stats = getUserMiniTaskStats($user_id, $db);
    $level_state = getUserLevelState($user_id, $db);

    return $task_stats['completed_total'] >= REFERRAL_MIN_COMPLETED_TASKS
        || in_array((string) ($level_state['level'] ?? 'beginner'), ['pro', 'expert'], true)
        || in_array((string) ($level_state['recommended_level'] ?? 'beginner'), ['pro', 'expert'], true);
}

function getClaimEligibility($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = getUserById((int) $user_id);
    if (!$user) {
        return ['eligible' => false, 'message' => 'User account not found.'];
    }

    if (!empty($user['reward_frozen'])) {
        return ['eligible' => false, 'message' => 'Rewards are temporarily frozen by the admin team for this account.'];
    }

    $level_state = getUserLevelState($user, $db);
    if (!in_array((string) ($level_state['level'] ?? 'beginner'), ['pro', 'expert'], true)) {
        return ['eligible' => false, 'message' => 'Claim unlocks once your account reaches Pro level.'];
    }

    $balance = getRewardLedgerBalance((int) $user_id, 'available', $db);
    if ($balance < (float) REWARD_CLAIM_MINIMUM_REX) {
        return ['eligible' => false, 'message' => 'Minimum claim threshold has not been reached yet.'];
    }

    $signals = getUserSecuritySignals((int) $user_id, $db);
    if (!empty($signals['is_suspicious'])) {
        return ['eligible' => false, 'message' => 'Claim is temporarily unavailable while account activity is reviewed.', 'signals' => $signals];
    }

    return [
        'eligible' => true,
        'message' => 'Claim snapshot can be generated.',
        'kyc_required' => true,
        'balance' => number_format($balance, 8, '.', ''),
        'level' => (string) ($level_state['level'] ?? 'beginner'),
    ];
}

function getMiniTasksForUser($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    $task_stats = getUserMiniTaskStats($user_id, $db);

    $stmt = $db->query("
        SELECT id, task_group, title, description, reward, daily_limit, cooldown_seconds, is_active, task_category, task_link, completion_steps, proof_notes, cta_label
        FROM mini_tasks
        WHERE is_active = 1
        ORDER BY id ASC
    ");
    $tasks = $stmt->fetchAll();

    foreach ($tasks as &$task) {
        $task_id = (int) $task['id'];
        $log_stmt = $db->prepare("
            SELECT completed_at
            FROM user_task_logs
            WHERE user_id = ?
              AND task_id = ?
              AND status = 'completed'
            ORDER BY completed_at DESC
            LIMIT 1
        ");
        $log_stmt->execute([$user_id, $task_id]);
        $last_completion = $log_stmt->fetch()['completed_at'] ?? null;

        $daily_stmt = $db->prepare("
            SELECT COUNT(*) AS total
            FROM user_task_logs
            WHERE user_id = ?
              AND task_id = ?
              AND status = 'completed'
              AND DATE(completed_at) = CURDATE()
        ");
        $daily_stmt->execute([$user_id, $task_id]);
        $completed_today = (int) ($daily_stmt->fetch()['total'] ?? 0);

        $cooldown_seconds = (int) ($task['cooldown_seconds'] ?? 86400);
        $cooldown_remaining = 0;
        if ($last_completion) {
            $cooldown_remaining = max(0, $cooldown_seconds - (time() - strtotime((string) $last_completion)));
        }

        $daily_limit = max(1, (int) ($task['daily_limit'] ?? 1));
        $daily_remaining = max(0, $daily_limit - $completed_today);
        $global_daily_cap_reached = $task_stats['completed_today'] >= BEGINNER_GLOBAL_TASKS_PER_DAY;
        $is_available = !$global_daily_cap_reached && $daily_remaining > 0 && $cooldown_remaining <= 0;
        $availability_reason = 'Ready';

        if ($global_daily_cap_reached) {
            $availability_reason = 'Task limit reached';
        } elseif ($daily_remaining <= 0) {
            $availability_reason = 'Daily limit reached';
        } elseif ($cooldown_remaining > 0) {
            $availability_reason = 'Cooldown active';
        }

        $task['last_completed_at'] = $last_completion;
        $task['completed_today'] = $completed_today;
        $task['daily_remaining'] = $daily_remaining;
        $task['cooldown_remaining_seconds'] = $cooldown_remaining;
        $task['global_daily_cap_reached'] = $global_daily_cap_reached;
        $task['is_available'] = $is_available;
        $task['availability_reason'] = $availability_reason;
    }

    return $tasks;
}

function getBoostHubStateForUser($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['status' => 'closed', 'message' => 'Invalid user.', 'task' => null, 'unlock_at' => null, 'countdown_seconds' => 0];
    }

    $user = getUserById($user_id);
    if (!$user) {
        return ['status' => 'closed', 'message' => 'User account not found.', 'task' => null, 'unlock_at' => null, 'countdown_seconds' => 0];
    }

    $profile_complete = isUserProfileComplete($user);
    $account_age_days = max(0, (int) floor((time() - strtotime((string) ($user['created_at'] ?? 'now'))) / 86400));
    if (!$profile_complete || $account_age_days < 3) {
        $missing_profile_parts = [];
        if (trim((string) ($user['full_name'] ?? '')) === '') {
            $missing_profile_parts[] = 'full name';
        }
        if (trim((string) ($user['username'] ?? '')) === '') {
            $missing_profile_parts[] = 'username';
        }
        if (trim((string) ($user['country'] ?? '')) === '') {
            $missing_profile_parts[] = 'country';
        }
        if (trim((string) ($user['avatar'] ?? '')) === '') {
            $missing_profile_parts[] = 'avatar';
        }
        if (empty($user['profile_completed_at'])) {
            $missing_profile_parts[] = 'profile completion timestamp';
        }

        $reasons = [];
        if (!empty($missing_profile_parts)) {
            $reasons[] = 'complete profile: ' . implode(', ', $missing_profile_parts);
        }
        if ($account_age_days < 3) {
            $reasons[] = 'wait ' . (3 - $account_age_days) . ' more day(s) for account age';
        }

        return [
            'status' => 'closed',
            'message' => 'BoostHub locked: ' . implode('; ', $reasons) . '.',
            'task' => null,
            'unlock_at' => null,
            'countdown_seconds' => 0,
        ];
    }

    $pending_stmt = $db->prepare("
        SELECT utl.*, mt.*
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.user_id = ?
          AND utl.status = 'pending'
          AND mt.task_group = 'boosthub'
          AND mt.is_active = 1
        ORDER BY utl.id DESC
        LIMIT 1
    ");
    $pending_stmt->execute([$user_id]);
    $pending = $pending_stmt->fetch();
    if ($pending) {
        return [
            'status' => 'open',
            'message' => 'Assigned task ready.',
            'task' => $pending,
            'unlock_at' => null,
            'countdown_seconds' => 0,
        ];
    }

    $submitted_stmt = $db->prepare("
        SELECT utl.id
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.user_id = ?
          AND utl.status = 'submitted'
          AND mt.task_group = 'boosthub'
          AND mt.is_active = 1
        ORDER BY utl.id DESC
        LIMIT 1
    ");
    $submitted_stmt->execute([$user_id]);
    $submitted = $submitted_stmt->fetch();
    if ($submitted) {
        return [
            'status' => 'awaiting_review',
            'message' => 'Evidence submitted successfully. Reward will be credited after admin approval.',
            'task' => null,
            'unlock_at' => null,
            'countdown_seconds' => 0,
        ];
    }

    $last_completed_stmt = $db->prepare("
        SELECT MAX(COALESCE(utl.task_completed_at, utl.completed_at)) AS completed_at
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.user_id = ?
          AND utl.status = 'completed'
          AND mt.task_group = 'boosthub'
    ");
    $last_completed_stmt->execute([$user_id]);
    $last_completed_at = (string) ($last_completed_stmt->fetch()['completed_at'] ?? '');
    if ($last_completed_at !== '') {
        $unlock_ts = strtotime($last_completed_at . ' +24 hours');
        if ($unlock_ts > time()) {
            return [
                'status' => 'locked',
                'message' => 'Next task unlocks after 24 hours.',
                'task' => null,
                'unlock_at' => date('Y-m-d H:i:s', $unlock_ts),
                'countdown_seconds' => max(0, $unlock_ts - time()),
            ];
        }
    }

    $assign_stmt = $db->prepare("
        SELECT mt.*
        FROM mini_tasks mt
        WHERE mt.task_group = 'boosthub'
          AND mt.is_active = 1
          AND mt.id NOT IN (
              SELECT DISTINCT utl.task_id
              FROM user_task_logs utl
              INNER JOIN mini_tasks mt2 ON mt2.id = utl.task_id
              WHERE utl.user_id = ?
                AND mt2.task_group = 'boosthub'
                AND utl.status = 'completed'
          )
        ORDER BY RAND()
        LIMIT 1
    ");
    $assign_stmt->execute([$user_id]);
    $task = $assign_stmt->fetch();
    if (!$task) {
        return [
            'status' => 'finished',
            'message' => 'No new BoostHub tasks available right now.',
            'task' => null,
            'unlock_at' => null,
            'countdown_seconds' => 0,
        ];
    }

    taskHubInsertLog($user_id, (int) $task['id'], 'pending', [
        'task_available_at' => date('Y-m-d H:i:s'),
        'metadata' => ['boosthub_assigned' => 1],
    ], $db);

    return [
        'status' => 'open',
        'message' => 'Assigned task ready.',
        'task' => $task,
        'unlock_at' => null,
        'countdown_seconds' => 0,
    ];
}

function logMiniTaskAction($user_id, $task_id, $status, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO user_task_logs (user_id, task_id, status)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([(int) $user_id, (int) $task_id, $status === 'blocked' ? 'blocked' : 'completed']);
}

function completeMiniTask($user_id, $task_id, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = getUserById((int) $user_id);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    if (!empty($user['reward_frozen'])) {
        throw new RuntimeException('Rewards are temporarily frozen for this account.');
    }

    if (normalizeUserLevel($user['level'] ?? 'beginner') !== 'beginner') {
        throw new RuntimeException('MicroTaskHub is available for Beginner accounts only.');
    }

    $task_stmt = $db->prepare("
        SELECT *
        FROM mini_tasks
        WHERE id = ?
          AND is_active = 1
        LIMIT 1
    ");
    $task_stmt->execute([(int) $task_id]);
    $task = $task_stmt->fetch();

    if (!$task) {
        throw new RuntimeException('Mini task not found or inactive.');
    }

    if ((string) ($task['task_group'] ?? 'legacy') === 'boosthub') {
        $already_completed_stmt = $db->prepare("
            SELECT COUNT(*) AS total
            FROM user_task_logs
            WHERE user_id = ?
              AND task_id = ?
              AND status = 'completed'
        ");
        $already_completed_stmt->execute([(int) $user_id, (int) $task_id]);
        if ((int) ($already_completed_stmt->fetch()['total'] ?? 0) > 0) {
            throw new RuntimeException('This BoostHub task is already completed for your account.');
        }

        $pending_assignment_stmt = $db->prepare("
            SELECT id
            FROM user_task_logs
            WHERE user_id = ?
              AND task_id = ?
              AND status = 'pending'
            ORDER BY id DESC
            LIMIT 1
        ");
        $pending_assignment_stmt->execute([(int) $user_id, (int) $task_id]);
        $pending_assignment = $pending_assignment_stmt->fetch();
        if (!$pending_assignment) {
            throw new RuntimeException('This task is not assigned to you yet.');
        }

        $proof = trim((string) ($payload['proof'] ?? ''));

        if ($proof === '') {
            throw new RuntimeException('Evidence is required before submitting this BoostHub task.');
        }

        $boosthub_proof_data = $proof;
    }

    $signals = getUserSecuritySignals((int) $user_id, $db);
    if (!empty($signals['is_suspicious'])) {
        logMiniTaskAction($user_id, $task_id, 'blocked', $db);
        throw new RuntimeException('Task completion is temporarily blocked for security review.');
    }

    $task_stats = getUserMiniTaskStats((int) $user_id, $db);
    if ($task_stats['completed_today'] >= BEGINNER_GLOBAL_TASKS_PER_DAY) {
        logMiniTaskAction($user_id, $task_id, 'blocked', $db);
        throw new RuntimeException('Daily task limit reached for your account.');
    }

    $recent_stmt = $db->prepare("
        SELECT completed_at
        FROM user_task_logs
        WHERE user_id = ?
          AND task_id = ?
          AND status = 'completed'
        ORDER BY completed_at DESC
        LIMIT 1
    ");
    $recent_stmt->execute([(int) $user_id, (int) $task_id]);
    $last_completion = $recent_stmt->fetch()['completed_at'] ?? null;
    if ($last_completion) {
        $elapsed = time() - strtotime((string) $last_completion);
        if ($elapsed < (int) ($task['cooldown_seconds'] ?? 86400)) {
            logMiniTaskAction($user_id, $task_id, 'blocked', $db);
            throw new RuntimeException('Task cooldown is still active.');
        }
    }

    $rapid_stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM user_task_logs
        WHERE user_id = ?
          AND completed_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $rapid_stmt->execute([(int) $user_id, (int) ANTI_FARM_RAPID_ACTION_WINDOW_SECONDS]);
    $rapid_actions = (int) ($rapid_stmt->fetch()['total'] ?? 0);
    if ($rapid_actions > 0) {
        logMiniTaskAction($user_id, $task_id, 'blocked', $db);
        throw new RuntimeException('Please slow down before claiming another task reward.');
    }

    try {
        $db->beginTransaction();
        if ((string) ($task['task_group'] ?? 'legacy') === 'boosthub') {
            taskHubUpdateLog((int) ($pending_assignment['id'] ?? 0), [
                'status' => 'submitted',
                'proof_data' => $boosthub_proof_data !== '' ? $boosthub_proof_data : null,
                'metadata' => [
                    'submitted_at' => date('Y-m-d H:i:s'),
                    'review_outcome' => 'pending',
                ],
            ], $db);
            $db->commit();
            return ['submitted' => true];
        } else {
            logMiniTaskAction($user_id, $task_id, 'completed', $db);
        }
        $entry = addRewardLedgerEntry(
            (int) $user_id,
            (float) $task['reward'],
            'mini_task',
            'mini_task_completion',
            'available',
            'mini_task:' . (int) $task_id,
            $db,
            'phase1',
            'beginner'
        );
        maybeActivateReferralQualification((int) $user_id, $db);
        syncUserLevelStatus((int) $user_id, $db);
        $db->commit();
        return ['entry' => $entry];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function getTaskHubMissionDefinitions() {
    static $definitions = null;

    if ($definitions !== null) {
        return $definitions;
    }

    $quiz_day_1 = [
        [
            'question' => 'What should you do before using CoinRex rewards?',
            'choices' => ['Read the platform terms', 'Skip the rules', 'Only check prices'],
            'answer' => 0,
        ],
        [
            'question' => 'Which action helps keep rewards fair?',
            'choices' => ['Using one verified account', 'Creating many accounts', 'Skipping verification'],
            'answer' => 0,
        ],
        [
            'question' => 'What powers CoinRex rewards?',
            'choices' => ['The ledger system', 'Manual browser edits', 'Local storage'],
            'answer' => 0,
        ],
    ];

    $quiz_day_2 = [
        [
            'question' => 'Which page explains what CoinRex is building?',
            'choices' => ['About page', 'Logout page', '404 page'],
            'answer' => 0,
        ],
        [
            'question' => 'What is the main user dashboard for?',
            'choices' => ['Tracking progress and rewards', 'Mining tokens', 'Deleting claims'],
            'answer' => 0,
        ],
        [
            'question' => 'Why explore the interface early?',
            'choices' => ['To understand review and reward flows', 'To bypass cooldowns', 'To unlock claims instantly'],
            'answer' => 0,
        ],
    ];

    $quiz_day_3 = [
        [
            'question' => 'What does the privacy policy mainly explain?',
            'choices' => ['How data is handled', 'How to skip login', 'How to mint NFTs'],
            'answer' => 0,
        ],
        [
            'question' => 'Which signals may be checked for abuse prevention?',
            'choices' => ['IP and user agent', 'Monitor brightness only', 'Browser tab color'],
            'answer' => 0,
        ],
        [
            'question' => 'Why should proof submissions be real?',
            'choices' => ['Because admins can review them', 'Because fake proofs earn more', 'Because proof is optional'],
            'answer' => 0,
        ],
    ];

    $quiz_day_4 = [
        [
            'question' => 'What does the roadmap help you understand?',
            'choices' => ['What unlocks over time', 'Your Wi-Fi password', 'Local PHP settings'],
            'answer' => 0,
        ],
        [
            'question' => 'How many questions are in this roadmap quiz?',
            'choices' => ['Five', 'One', 'Ten'],
            'answer' => 0,
        ],
        [
            'question' => 'What is BoostHub used for here?',
            'choices' => ['A rotating admin task assignment', 'A crypto wallet', 'A database backup'],
            'answer' => 0,
        ],
        [
            'question' => 'When can the next mission day unlock?',
            'choices' => ['After tasks are done and server reset passes', 'Immediately after Task 0', 'Only after claim'],
            'answer' => 0,
        ],
        [
            'question' => 'What happens if you miss a mission day window?',
            'choices' => ['Progress pauses', 'Everything resets to Day 1', 'You skip ahead'],
            'answer' => 0,
        ],
    ];

    $quiz_day_5 = [
        [
            'question' => 'What is DevHub mainly for?',
            'choices' => ['Developer-facing project activity', 'Claim approval only', 'Password resets'],
            'answer' => 0,
        ],
        [
            'question' => 'Does wallet onboarding require sending funds here?',
            'choices' => ['No', 'Yes', 'Only on Day 10'],
            'answer' => 0,
        ],
        [
            'question' => 'Why add a wallet address?',
            'choices' => ['To prepare for future reward operations', 'To bypass moderation', 'To create a referral'],
            'answer' => 0,
        ],
    ];

    $quiz_day_6 = [
        [
            'question' => 'What should a review include?',
            'choices' => ['Honest proof-backed detail', 'Only emojis', 'Nothing but a rating'],
            'answer' => 0,
        ],
        [
            'question' => 'Why learn the claim system before Pro?',
            'choices' => ['So users understand locked and unlocked rewards', 'So they can skip the queue', 'So they can mint tokens'],
            'answer' => 0,
        ],
        [
            'question' => 'Who controls reward availability?',
            'choices' => ['Backend rules and admin overrides', 'Frontend buttons only', 'Browser cache'],
            'answer' => 0,
        ],
    ];

    $quiz_day_7 = [
        [
            'question' => 'What score is required to pass Day 7?',
            'choices' => ['4 out of 5', '2 out of 5', '5 out of 10'],
            'answer' => 0,
        ],
        [
            'question' => 'What happens if you fail Day 7?',
            'choices' => ['You stay on Day 7 until you pass', 'You skip to Day 8', 'You unlock claims'],
            'answer' => 0,
        ],
        [
            'question' => 'Why does CoinRex use anti-farming checks?',
            'choices' => ['To protect reward quality', 'To slow the homepage', 'To hide balances'],
            'answer' => 0,
        ],
        [
            'question' => 'What should you avoid while earning?',
            'choices' => ['Rapid repeat submissions', 'Reading the guide', 'Using one account'],
            'answer' => 0,
        ],
        [
            'question' => 'When do claims unlock?',
            'choices' => ['After reaching Pro', 'On Day 1', 'Before onboarding starts'],
            'answer' => 0,
        ],
    ];

    $definitions = [
        ['day' => 1, 'step' => 0, 'task_key' => 'day1_checkin', 'title' => 'Daily Check-in', 'description' => 'Start the onboarding journey.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 1, 'step' => 1, 'task_key' => 'day1_profile_setup', 'title' => 'Profile Setup', 'description' => 'Open your profile page and save your profile basics.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'profile', 'learning_title' => 'Profile Page', 'learning_url' => BASE_URL . '/profile.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 1, 'step' => 2, 'task_key' => 'day1_social_follow', 'title' => 'Social Follow', 'description' => 'Submit a social follow proof for review.', 'reward' => 2, 'unlock_after_hours' => 2, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 1, 'step' => 3, 'task_key' => 'day1_terms_quiz', 'title' => 'Learn and Quiz', 'description' => 'Review the terms and answer 3 questions.', 'reward' => 2, 'unlock_after_hours' => 5, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 3, 'quiz' => $quiz_day_1, 'learning_title' => 'Terms of Service', 'learning_url' => BASE_URL . '/terms.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 2, 'step' => 0, 'task_key' => 'day2_checkin', 'title' => 'Check-in', 'description' => 'Start Day 2.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 2, 'step' => 1, 'task_key' => 'day2_ui_exploration', 'title' => 'UI Exploration', 'description' => 'Explore the dashboard, reviews, and project areas.', 'reward' => 1, 'unlock_after_hours' => 3, 'verification_mode' => 'instant', 'learning_title' => 'Dashboard Overview', 'learning_url' => BASE_URL . '/dashboard.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 2, 'step' => 2, 'task_key' => 'day2_about_quiz', 'title' => 'Learn and Quiz', 'description' => 'Read the About page and answer 3 questions.', 'reward' => 2, 'unlock_after_hours' => 6, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 3, 'quiz' => $quiz_day_2, 'learning_title' => 'About CoinRex', 'learning_url' => BASE_URL . '/about.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 3, 'step' => 0, 'task_key' => 'day3_checkin', 'title' => 'Check-in', 'description' => 'Start Day 3.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 3, 'step' => 1, 'task_key' => 'day3_share_experience', 'title' => 'Share Experience', 'description' => 'Submit a short public share or community proof.', 'reward' => 2, 'unlock_after_hours' => 3, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'learning_title' => 'Community Share Guide', 'learning_url' => BASE_URL . '/dashboard.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 3, 'step' => 2, 'task_key' => 'day3_privacy_quiz', 'title' => 'Learn and Quiz', 'description' => 'Review the privacy policy and answer 3 questions.', 'reward' => 2, 'unlock_after_hours' => 6, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 3, 'quiz' => $quiz_day_3, 'learning_title' => 'Privacy Policy', 'learning_url' => BASE_URL . '/privacy.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 4, 'step' => 0, 'task_key' => 'day4_checkin', 'title' => 'Check-in', 'description' => 'Start Day 4.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 4, 'step' => 1, 'task_key' => 'day4_roadmap_quiz', 'title' => 'Learn and Quiz', 'description' => 'Review the roadmap briefing and answer 5 questions.', 'reward' => 2, 'unlock_after_hours' => 3, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 5, 'quiz' => $quiz_day_4, 'learning_title' => 'Roadmap Briefing', 'learning_url' => BASE_URL . '/roadmap.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 5, 'step' => 0, 'task_key' => 'day5_checkin', 'title' => 'Check-in', 'description' => 'Start Day 5.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 5, 'step' => 1, 'task_key' => 'day5_devhub_quiz', 'title' => 'Learn and Quiz', 'description' => 'Review DevHub and answer 3 questions.', 'reward' => 2, 'unlock_after_hours' => 3, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 3, 'quiz' => $quiz_day_5, 'learning_title' => 'DevHub', 'learning_url' => BASE_URL . '/devhub/index.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 5, 'step' => 3, 'task_key' => 'day5_wallet_connect', 'title' => 'Wallet Add or Connect', 'description' => 'Add a wallet address without any real transaction.', 'reward' => 1, 'unlock_after_hours' => 6, 'verification_mode' => 'wallet', 'learning_title' => 'Profile Wallet Section', 'learning_url' => BASE_URL . '/profile.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 6, 'step' => 0, 'task_key' => 'day6_checkin', 'title' => 'Check-in', 'description' => 'Start Day 6.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 6, 'step' => 1, 'task_key' => 'day6_review_quiz', 'title' => 'Learn and Quiz', 'description' => 'Study the review guide and answer 3 questions.', 'reward' => 2, 'unlock_after_hours' => 3, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 3, 'quiz' => $quiz_day_6, 'learning_title' => 'Review Guide', 'learning_url' => BASE_URL . '/submit-review.php', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 6, 'step' => 3, 'task_key' => 'day6_txhash_submit', 'title' => 'Transaction Proof (>=10 USDT)', 'description' => 'Submit one valid TX hash for a transaction worth at least 10 USDT for review.', 'reward' => 2, 'unlock_after_hours' => 6, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'learning_title' => 'Proof Submission Guide', 'learning_url' => BASE_URL . '/docs/SECURITY.md', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 7, 'step' => 0, 'task_key' => 'day7_checkin', 'title' => 'Check-in', 'description' => 'Start Day 7.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 7, 'step' => 1, 'task_key' => 'day7_final_quiz', 'title' => 'Final Quiz', 'description' => 'Pass the quality gate with at least 4/5.', 'reward' => 2, 'unlock_after_hours' => 0, 'verification_mode' => 'quiz', 'requires_quiz' => 1, 'min_quiz_score' => 4, 'quiz' => $quiz_day_7, 'learning_title' => 'Quality Gate', 'learning_url' => '', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 7, 'step' => 3, 'task_key' => 'day7_volume_submit', 'title' => 'Volume Proof (>=100 USDT)', 'description' => 'Submit proof of cumulative 100+ USDT transaction volume completed within one day.', 'reward' => 3, 'unlock_after_hours' => 6, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'learning_title' => 'Volume Proof Guide', 'learning_url' => BASE_URL . '/docs/SECURITY.md', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 8, 'step' => 0, 'task_key' => 'day8_checkin', 'title' => 'Check-in', 'description' => 'Start Day 8.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 8, 'step' => 2, 'task_key' => 'day8_hold_submit', 'title' => 'Hold Proof (>=10 USDT)', 'description' => 'Submit proof that you held tokens worth at least 10 USDT for one full day.', 'reward' => 3, 'unlock_after_hours' => 6, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'learning_title' => 'Hold Proof Guide', 'learning_url' => BASE_URL . '/docs/SECURITY.md', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 9, 'step' => 0, 'task_key' => 'day9_checkin', 'title' => 'Check-in', 'description' => 'Start Day 9.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 9, 'step' => 2, 'task_key' => 'day9_hold_submit', 'title' => 'Hold Proof (>=10 USDT)', 'description' => 'Submit proof that you held tokens worth at least 10 USDT for one full day.', 'reward' => 3, 'unlock_after_hours' => 6, 'verification_mode' => 'manual', 'requires_manual_review' => 1, 'learning_title' => 'Hold Proof Guide', 'learning_url' => BASE_URL . '/docs/SECURITY.md', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],

        ['day' => 10, 'step' => 0, 'task_key' => 'day10_checkin', 'title' => 'Check-in', 'description' => 'Start Day 10.', 'reward' => 1, 'unlock_after_hours' => 0, 'verification_mode' => 'instant', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
        ['day' => 10, 'step' => 2, 'task_key' => 'day10_mystery_box', 'title' => 'Mystery Box', 'description' => 'Claim the final reward box.', 'reward' => TASKHUB_MYSTERY_BOX_PERFECT_REWARD, 'unlock_after_hours' => 6, 'verification_mode' => 'mystery', 'daily_limit' => 1, 'cooldown_seconds' => 86400, 'is_active' => 1],
    ];

    return $definitions;
}

function getTaskHubDayTitles() {
    return [
        1 => 'Welcome Day',
        2 => 'Explore Day',
        3 => 'Privacy Day',
        4 => 'Roadmap Day',
        5 => 'DevHub Day',
        6 => 'Review Day',
        7 => 'Filter Day',
        8 => 'Wallet Day',
        9 => 'Momentum Day',
        10 => 'Mystery Day',
    ];
}

function taskHubGetBoostRequirementByDay($mission_day) {
    $map = [
        4 => 2.0,
        5 => 2.0,
        6 => 2.0,
        7 => 2.0,
        8 => 3.0,
        9 => 3.0,
        10 => 3.0,
    ];
    return (float) ($map[(int) $mission_day] ?? 0.0);
}

function taskHubGetBoostGatewayTask($mission_day) {
    $required_reward = taskHubGetBoostRequirementByDay((int) $mission_day);
    if ($required_reward <= 0) {
        return null;
    }

    return [
        'id' => 0,
        'task_key' => 'day' . (int) $mission_day . '_boosthub_gateway',
        'mission_step' => 90,
        'title' => 'BoostHub Task',
        'description' => 'Open BoostHub and complete one task worth exactly ' . number_format((float) $required_reward, 0) . ' $REX.',
        'reward' => (float) $required_reward,
        'task_category' => 'custom',
        'task_link' => BASE_URL . '/boosthub.php',
        'completion_steps' => "1. Open BoostHub.\n2. Complete one task worth exactly " . number_format((float) $required_reward, 0) . " \$REX.\n3. Return to TaskHub.",
        'proof_notes' => 'This step auto-validates from completed BoostHub tasks.',
        'cta_label' => 'Open BoostHub',
        'verification_mode' => 'boosthub_redirect',
        'requires_quiz' => 0,
        'requires_manual_review' => 0,
    ];
}

function userCanAccessProjectReviewArea($user_or_level_state) {
    $level_state = is_array($user_or_level_state) && isset($user_or_level_state['level'])
        ? $user_or_level_state
        : getUserLevelState($user_or_level_state);

    return normalizeUserLevel($level_state['level'] ?? 'beginner') !== 'beginner';
}

function requireProjectReviewAccess($redirect_path = '/dashboard.php') {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/auth/auth.php');
    }

    $user = getCurrentUser();
    if (!$user || !userCanAccessProjectReviewArea($user)) {
        setFlashMessage('dashboard_success', 'Projects and Reviews unlock after you reach Pro. Complete TaskHub first.');
        redirect(BASE_URL . $redirect_path);
    }

    return $user;
}

function getTaskHubMissionTaskDefinitionByKey($task_key) {
    foreach (getTaskHubMissionDefinitions() as $definition) {
        if ((string) $definition['task_key'] === (string) $task_key) {
            return $definition;
        }
    }

    return null;
}

function getTaskHubResetTimestamp($timestamp = null) {
    $timestamp = $timestamp !== null ? (int) $timestamp : time();
    return strtotime(date('Y-m-d', $timestamp) . ' ' . sprintf('%02d:00:00', TASKHUB_SERVER_RESET_HOUR));
}

function getTaskHubNextResetTimestamp($timestamp = null) {
    $base = getTaskHubResetTimestamp($timestamp);
    if (($timestamp !== null ? (int) $timestamp : time()) >= $base) {
        return strtotime('+1 day', $base);
    }

    return $base;
}

function getTaskHubCurrentPhase1Earnings($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND source = 'mini_task'
          AND reward_phase = 'phase1'
          AND status IN ('available', 'locked', 'claimed')
    ");
    $stmt->execute([(int) $user_id]);
    return round((float) ($stmt->fetch()['total'] ?? 0), 8);
}

function isUserProfileComplete(array $user) {
    return trim((string) ($user['full_name'] ?? '')) !== ''
        && trim((string) ($user['username'] ?? '')) !== ''
        && trim((string) ($user['country'] ?? '')) !== ''
        && trim((string) ($user['avatar'] ?? '')) !== ''
        && !empty($user['profile_completed_at']);
}

function uploadProfileAvatar($user_id, array $file) {
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Avatar upload failed. Please try again.');
    }

    $tmp_name = (string) ($file['tmp_name'] ?? '');
    $original_name = (string) ($file['name'] ?? '');
    $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['png', 'jpg', 'jpeg', 'webp'];
    $max_size = 4 * 1024 * 1024;

    if (!in_array($extension, $allowed_extensions, true)) {
        throw new RuntimeException('Allowed avatar formats: PNG, JPG, JPEG, WEBP.');
    }

    if ((int) ($file['size'] ?? 0) > $max_size) {
        throw new RuntimeException('Avatar size must be 4MB or smaller.');
    }

    if ($tmp_name === '' || !@getimagesize($tmp_name)) {
        throw new RuntimeException('Uploaded avatar is not a valid image.');
    }

    $avatar_dir = BASE_PATH . '/uploads/avatar';
    if (!is_dir($avatar_dir) && !mkdir($avatar_dir, 0755, true) && !is_dir($avatar_dir)) {
        throw new RuntimeException('Avatar directory could not be created.');
    }

    $safe_file_name = 'avatar_' . (int) $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $avatar_abs_path = $avatar_dir . DIRECTORY_SEPARATOR . $safe_file_name;
    if (!move_uploaded_file($tmp_name, $avatar_abs_path)) {
        throw new RuntimeException('Could not save avatar file. Please try again.');
    }

    return BASE_URL . '/uploads/avatar/' . $safe_file_name;
}

function updateUserProfileBasics($user_id, array $data, array $files = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $full_name = trim((string) ($data['full_name'] ?? ''));
    $username = trim((string) ($data['username'] ?? ''));
    $country = trim((string) ($data['country'] ?? ''));
    $current_user = getUserById((int) $user_id);
    if (!$current_user) {
        throw new RuntimeException('User account not found.');
    }
    $avatar = trim((string) ($current_user['avatar'] ?? ''));

    if ($full_name === '' || $username === '' || $country === '') {
        throw new RuntimeException('Full name, username, and country are required.');
    }

    if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $username)) {
        throw new RuntimeException('Username must be 3 to 30 characters and use letters, numbers, dot, underscore, or dash.');
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
    $stmt->execute([$username, (int) $user_id]);
    if ($stmt->fetch()) {
        throw new RuntimeException('This username is already taken.');
    }

    if (!empty($files['avatar']) && is_array($files['avatar'])) {
        $uploaded_avatar = uploadProfileAvatar((int) $user_id, $files['avatar']);
        if ($uploaded_avatar !== null) {
            $avatar = $uploaded_avatar;
        }
    }

    if ($avatar === '') {
        throw new RuntimeException('Profile photo is required.');
    }

    $update = $db->prepare("
        UPDATE users
        SET full_name = ?, username = ?, country = ?, avatar = ?, profile_completed_at = NOW(), updated_at = NOW()
        WHERE id = ?
    ");
    $update->execute([
        $full_name,
        $username,
        $country,
        $avatar !== '' ? $avatar : null,
        (int) $user_id,
    ]);

    $user = getUserById((int) $user_id);
    if ($user) {
        $_SESSION['username'] = $user['username'];
    }

    return $user;
}

function taskHubFormatDuration($seconds) {
    $seconds = max(0, (int) $seconds);
    $hours = (int) floor($seconds / 3600);
    $minutes = (int) floor(($seconds % 3600) / 60);
    $remaining_seconds = $seconds % 60;
    $parts = [];

    if ($hours > 0) {
        $parts[] = $hours . 'h';
    }
    if ($hours > 0 || $minutes > 0) {
        $parts[] = $minutes . 'm';
    }
    $parts[] = $remaining_seconds . 's';

    return implode(' ', $parts);
}

function getTaskHubRewardAmountForTask($user_id, array $task_row, array $log_row = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $reward = round((float) ($task_row['reward'] ?? 0), 8);
    $metadata = [];
    if (!empty($log_row['metadata'])) {
        $metadata = json_decode((string) $log_row['metadata'], true) ?: [];
    }

    if (($task_row['verification_mode'] ?? '') === 'boosthub' && !empty($metadata['boost_reward'])) {
        $reward = round((float) $metadata['boost_reward'], 8);
    }

    if (($task_row['verification_mode'] ?? '') === 'mystery') {
        $reward = taskHubHasMissedDays((int) $user_id, $db)
            ? (float) TASKHUB_MYSTERY_BOX_FALLBACK_REWARD
            : (float) TASKHUB_MYSTERY_BOX_PERFECT_REWARD;
    }

    $current_phase_earnings = getTaskHubCurrentPhase1Earnings((int) $user_id, $db);
    $remaining_cap = max(0, (float) TASKHUB_PHASE1_REWARD_CAP - $current_phase_earnings);
    return round(min($reward, $remaining_cap), 8);
}

function getTaskHubTaskRows(PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->query("
        SELECT *
        FROM mini_tasks
        WHERE task_group = 'mission'
          AND is_active = 1
        ORDER BY mission_day ASC, mission_step ASC
    ");

    return $stmt->fetchAll();
}

function getTaskHubTasksByDay(PDO $db = null) {
    $tasks_by_day = [];
    foreach (getTaskHubTaskRows($db) as $task_row) {
        $day = (int) ($task_row['mission_day'] ?? 0);
        if (!isset($tasks_by_day[$day])) {
            $tasks_by_day[$day] = [];
        }
        $tasks_by_day[$day][] = $task_row;
    }

    return $tasks_by_day;
}

function getTaskHubLatestLog($user_id, $task_id, $mission_day, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        SELECT *
        FROM user_task_logs
        WHERE user_id = ?
          AND task_id = ?
          AND mission_day = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([(int) $user_id, (int) $task_id, (int) $mission_day]);
    return $stmt->fetch() ?: null;
}

function taskHubInsertLog($user_id, $task_id, $status, array $extra = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO user_task_logs (
            user_id, task_id, completed_at, task_completed_at, task_available_at, mission_day, mission_step,
            attempt_no, proof_data, score, metadata, status
        ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int) $user_id,
        (int) $task_id,
        $extra['task_completed_at'] ?? null,
        $extra['task_available_at'] ?? null,
        isset($extra['mission_day']) ? (int) $extra['mission_day'] : null,
        isset($extra['mission_step']) ? (int) $extra['mission_step'] : null,
        isset($extra['attempt_no']) ? (int) $extra['attempt_no'] : 1,
        $extra['proof_data'] ?? null,
        isset($extra['score']) ? (int) $extra['score'] : null,
        isset($extra['metadata']) ? json_encode($extra['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        (string) $status,
    ]);

    return (int) $db->lastInsertId();
}

function taskHubUpdateLog($log_id, array $fields, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $sets = [];
    $params = [];

    $map = [
        'completed_at' => 'completed_at',
        'task_completed_at' => 'task_completed_at',
        'task_available_at' => 'task_available_at',
        'proof_data' => 'proof_data',
        'score' => 'score',
        'status' => 'status',
    ];

    foreach ($map as $input_key => $column_name) {
        if (array_key_exists($input_key, $fields)) {
            $sets[] = $column_name . ' = ?';
            $params[] = $fields[$input_key];
        }
    }

    if (array_key_exists('metadata', $fields)) {
        $sets[] = 'metadata = ?';
        $params[] = json_encode($fields['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (empty($sets)) {
        return false;
    }

    $params[] = (int) $log_id;
    $stmt = $db->prepare("UPDATE user_task_logs SET " . implode(', ', $sets) . " WHERE id = ?");
    return $stmt->execute($params);
}

function taskHubSelectRandomBoostTask($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->query("
        SELECT id, title, description, reward, task_category, task_link, completion_steps, proof_notes, cta_label
        FROM mini_tasks
        WHERE task_group = 'boosthub'
          AND is_active = 1
        ORDER BY RAND()
        LIMIT 1
    ");
    $task = $stmt->fetch();
    if (!$task) {
        return null;
    }

    return $task;
}

function taskHubCreatePendingDayTasks($user_id, $mission_day, $day_available_at, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_tasks = $tasks_by_day[(int) $mission_day] ?? [];
    if (empty($day_tasks)) {
        return;
    }

    $day_available_at = (string) $day_available_at;
    $checkin_task = $day_tasks[0];
    $existing = getTaskHubLatestLog((int) $user_id, (int) $checkin_task['id'], (int) $mission_day, $db);
    if (!$existing) {
        taskHubInsertLog((int) $user_id, (int) $checkin_task['id'], 'pending', [
            'task_available_at' => $day_available_at,
            'mission_day' => (int) $mission_day,
            'mission_step' => (int) ($checkin_task['mission_step'] ?? 0),
        ], $db);
    }
}

function taskHubCreateFollowupTasksAfterCheckIn($user_id, $mission_day, $checkin_completed_at, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_tasks = $tasks_by_day[(int) $mission_day] ?? [];
    if (count($day_tasks) <= 1) {
        return;
    }

    foreach ($day_tasks as $day_task) {
        if ((int) ($day_task['mission_step'] ?? 0) === 0) {
            continue;
        }

        $existing = getTaskHubLatestLog((int) $user_id, (int) $day_task['id'], (int) $mission_day, $db);
        if ($existing) {
            continue;
        }

        $available_at_ts = strtotime((string) $checkin_completed_at . ' +' . (int) ($day_task['unlock_after_hours'] ?? 0) . ' hours');
        $metadata = [];
        if (($day_task['verification_mode'] ?? '') === 'boosthub') {
            $boost_task = taskHubSelectRandomBoostTask((int) $user_id, $db);
            if ($boost_task) {
                $last_boost_stmt = $db->prepare("
                    SELECT MAX(task_completed_at) AS completed_at
                    FROM user_task_logs utl
                    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                    WHERE utl.user_id = ?
                      AND utl.status = 'completed'
                      AND mt.task_group = 'mission'
                      AND mt.verification_mode = 'boosthub'
                ");
                $last_boost_stmt->execute([(int) $user_id]);
                $last_boost_at = $last_boost_stmt->fetch()['completed_at'] ?? null;
                if ($last_boost_at) {
                    $available_at_ts = max($available_at_ts, strtotime((string) $last_boost_at . ' +24 hours'));
                }

                $metadata = [
                    'boost_task_id' => (int) $boost_task['id'],
                    'boost_title' => (string) $boost_task['title'],
                    'boost_description' => (string) ($boost_task['description'] ?? ''),
                    'boost_reward' => (float) ($boost_task['reward'] ?? $day_task['reward']),
                    'boost_category' => (string) ($boost_task['task_category'] ?? 'custom'),
                    'boost_link' => (string) ($boost_task['task_link'] ?? ''),
                    'boost_steps' => (string) ($boost_task['completion_steps'] ?? ''),
                    'boost_proof_notes' => (string) ($boost_task['proof_notes'] ?? ''),
                    'boost_cta_label' => (string) ($boost_task['cta_label'] ?? ''),
                ];
            }
        }

        taskHubInsertLog((int) $user_id, (int) $day_task['id'], 'pending', [
            'task_available_at' => date('Y-m-d H:i:s', $available_at_ts),
            'mission_day' => (int) $mission_day,
            'mission_step' => (int) ($day_task['mission_step'] ?? 0),
            'metadata' => $metadata,
        ], $db);
    }
}

function taskHubGetDayCompletionInfo($user_id, $mission_day, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_tasks = $tasks_by_day[(int) $mission_day] ?? [];
    if (empty($day_tasks)) {
        return ['all_completed' => false, 'completed_at' => null, 'day_started_at' => null, 'tasks' => []];
    }

    $all_completed = true;
    $completed_at_ts = 0;
    $day_started_at_ts = 0;
    $task_states = [];

    foreach ($day_tasks as $day_task) {
        $log = getTaskHubLatestLog((int) $user_id, (int) $day_task['id'], (int) $mission_day, $db);
        $task_states[] = ['task' => $day_task, 'log' => $log];

        if (!$log || !in_array((string) ($log['status'] ?? ''), ['completed'], true)) {
            $all_completed = false;
        }

        $available_at = !empty($log['task_available_at']) ? strtotime((string) $log['task_available_at']) : 0;
        $completed_at = !empty($log['task_completed_at']) ? strtotime((string) $log['task_completed_at']) : 0;
        if ($available_at > 0 && ($day_started_at_ts === 0 || $available_at < $day_started_at_ts)) {
            $day_started_at_ts = $available_at;
        }
        if ($completed_at > $completed_at_ts) {
            $completed_at_ts = $completed_at;
        }
    }

    $boost_required_reward = taskHubGetBoostRequirementByDay((int) $mission_day);
    if ($boost_required_reward > 0) {
        $started_at_value = $day_started_at_ts > 0 ? date('Y-m-d H:i:s', $day_started_at_ts) : null;
        $has_boost = false;
        if (!empty($started_at_value)) {
            $boost_stmt = $db->prepare("
                SELECT COUNT(*) AS total
                FROM user_task_logs utl
                INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                WHERE utl.user_id = ?
                  AND utl.status = 'completed'
                  AND mt.task_group = 'boosthub'
                  AND ROUND(mt.reward, 2) = ?
                  AND COALESCE(utl.task_completed_at, utl.completed_at) >= ?
            ");
            $boost_stmt->execute([(int) $user_id, round((float) $boost_required_reward, 2), (string) $started_at_value]);
            $has_boost = ((int) ($boost_stmt->fetch()['total'] ?? 0)) > 0;
        }
        if (!$has_boost) {
            $all_completed = false;
        }
    }

    return [
        'all_completed' => $all_completed,
        'completed_at' => $completed_at_ts > 0 ? date('Y-m-d H:i:s', $completed_at_ts) : null,
        'day_started_at' => $day_started_at_ts > 0 ? date('Y-m-d H:i:s', $day_started_at_ts) : null,
        'tasks' => $task_states,
    ];
}

function taskHubHasMissedDays($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    for ($day = 1; $day <= TASKHUB_TOTAL_DAYS; $day++) {
        $info = taskHubGetDayCompletionInfo((int) $user_id, $day, $db);
        if (empty($info['day_started_at']) || empty($info['completed_at'])) {
            continue;
        }

        if (strtotime((string) $info['completed_at']) > getTaskHubNextResetTimestamp(strtotime((string) $info['day_started_at']))) {
            return true;
        }
    }

    return false;
}

function taskHubMissionCompleted($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $day_10 = taskHubGetDayCompletionInfo((int) $user_id, TASKHUB_TOTAL_DAYS, $db);
    return !empty($day_10['all_completed']);
}

function taskHubDayHasScheduledUnlocks(array $day_completion) {
    foreach (($day_completion['tasks'] ?? []) as $task_state) {
        $log = $task_state['log'] ?? [];
        $status = (string) ($log['status'] ?? '');
        $available_at_ts = !empty($log['task_available_at']) ? strtotime((string) $log['task_available_at']) : 0;
        if ($status === 'pending' && $available_at_ts > time()) {
            return true;
        }
    }

    return false;
}

function syncTaskHubDayProgress($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = getUserById((int) $user_id);
    if (!$user) {
        return null;
    }

    $current_day = max(1, min(TASKHUB_TOTAL_DAYS, (int) ($user['current_day'] ?? 1)));
    taskHubCreatePendingDayTasks((int) $user_id, $current_day, date('Y-m-d H:i:s'), $db);

    while ($current_day <= TASKHUB_TOTAL_DAYS) {
        $completion = taskHubGetDayCompletionInfo((int) $user_id, $current_day, $db);
        if (empty($completion['all_completed'])) {
            break;
        }

        $completed_at_ts = strtotime((string) ($completion['completed_at'] ?? 'now'));
        $next_reset = getTaskHubNextResetTimestamp($completed_at_ts);
        $db->prepare("UPDATE users SET last_day_completed_at = ? WHERE id = ?")->execute([
            date('Y-m-d H:i:s', $completed_at_ts),
            (int) $user_id,
        ]);

        if ($current_day >= TASKHUB_TOTAL_DAYS) {
            break;
        }

        if (time() < $next_reset) {
            break;
        }

        $current_day++;
        $db->prepare("UPDATE users SET current_day = ? WHERE id = ?")->execute([(int) $current_day, (int) $user_id]);
        taskHubCreatePendingDayTasks((int) $user_id, $current_day, date('Y-m-d H:i:s', $next_reset), $db);
    }

    return getUserById((int) $user_id);
}

function getTaskHubState($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = syncTaskHubDayProgress((int) $user_id, $db);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    if (normalizeUserLevel($user['level'] ?? 'beginner') !== 'beginner') {
        return [
            'access' => 'closed',
            'message' => 'TaskHub is available for Beginner accounts only.',
            'current_day' => (int) ($user['current_day'] ?? 1),
            'status' => 'completed',
        ];
    }

    $current_day = max(1, min(TASKHUB_TOTAL_DAYS, (int) ($user['current_day'] ?? 1)));
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_titles = getTaskHubDayTitles();
    $profile_complete = isUserProfileComplete($user);
    $current_tasks = $tasks_by_day[$current_day] ?? [];
    $next_day_tasks = $tasks_by_day[$current_day + 1] ?? [];
    $day_completion = taskHubGetDayCompletionInfo((int) $user_id, $current_day, $db);
    $day_started_at_ts = !empty($day_completion['day_started_at']) ? strtotime((string) $day_completion['day_started_at']) : time();
    $next_reset_ts = getTaskHubNextResetTimestamp($day_started_at_ts);
    $has_scheduled_unlocks = taskHubDayHasScheduledUnlocks($day_completion);
    $overall_total_tasks = 0;
    $overall_completed_tasks = 0;
    foreach ($tasks_by_day as $mission_tasks) {
        $overall_total_tasks += count($mission_tasks);
    }

    $status = 'in_progress';
    $status_message = 'In progress';
    $day_deadline_passed = !empty($day_completion['day_started_at']) && time() >= $next_reset_ts;
    if (!empty($day_completion['all_completed'])) {
        $status = 'completed';
        $status_message = $current_day >= TASKHUB_TOTAL_DAYS
            ? 'Completed'
            : 'Day cleared. Waiting for server reset';
    } elseif ($has_scheduled_unlocks) {
        $status = 'in_progress';
        $status_message = 'Waiting for next task unlock';
    } elseif ($day_deadline_passed) {
        $status = 'paused';
        $status_message = 'Progress paused until completion';
    }

    $build_task_payload = static function (array $task_row, $log, $active_day, PDO $inner_db) use ($profile_complete) {
        $metadata = !empty($log['metadata']) ? (json_decode((string) $log['metadata'], true) ?: []) : [];
        $available_at_ts = !empty($log['task_available_at']) ? strtotime((string) $log['task_available_at']) : 0;
        $countdown = $available_at_ts > time() ? max(0, $available_at_ts - time()) : 0;

        $task_status = 'locked';
        $task_message = 'Complete previous tasks to continue';
        if ($log) {
            $task_status = (string) ($log['status'] ?? 'locked');
            if ($task_status === 'pending' && (!$active_day || $available_at_ts <= time())) {
                $task_status = 'available';
                $task_message = 'Ready';
            } elseif ($task_status === 'pending') {
                $task_status = 'locked';
                $task_message = 'Next task unlocks in ' . taskHubFormatDuration(max(0, $countdown));
            } elseif ($task_status === 'submitted') {
                $task_message = 'Awaiting manual review';
            } elseif ($task_status === 'failed') {
                $task_status = 'available';
                $task_message = !empty($task_row['requires_manual_review'])
                    ? 'Submission rejected. Try again.'
                    : 'Pass the quiz to proceed';
            } elseif ($task_status === 'completed') {
                $task_message = 'Completed';
            }
        }

        if (($task_row['verification_mode'] ?? '') === 'boosthub' && !empty($metadata['boost_title'])) {
            $task_row['title'] = $metadata['boost_title'];
            $task_row['description'] = $metadata['boost_description'] ?? $task_row['description'];
            $task_row['reward'] = $metadata['boost_reward'] ?? $task_row['reward'];
            $task_row['task_category'] = $metadata['boost_category'] ?? ($task_row['task_category'] ?? 'custom');
            $task_row['task_link'] = $metadata['boost_link'] ?? ($task_row['task_link'] ?? '');
            $task_row['completion_steps'] = $metadata['boost_steps'] ?? ($task_row['completion_steps'] ?? '');
            $task_row['proof_notes'] = $metadata['boost_proof_notes'] ?? ($task_row['proof_notes'] ?? '');
            $task_row['cta_label'] = $metadata['boost_cta_label'] ?? ($task_row['cta_label'] ?? '');
        }

        $definition = getTaskHubMissionTaskDefinitionByKey((string) ($task_row['task_key'] ?? ''));
        return [
            'id' => (int) $task_row['id'],
            'task_key' => (string) $task_row['task_key'],
            'mission_step' => (int) ($task_row['mission_step'] ?? 0),
            'title' => (string) $task_row['title'],
            'description' => (string) ($task_row['description'] ?? ''),
            'reward' => round((float) ($task_row['reward'] ?? 0), 2),
            'task_category' => (string) ($task_row['task_category'] ?? 'custom'),
            'task_link' => (string) ($task_row['task_link'] ?? ''),
            'completion_steps' => (string) ($task_row['completion_steps'] ?? ''),
            'proof_notes' => (string) ($task_row['proof_notes'] ?? ''),
            'cta_label' => (string) ($task_row['cta_label'] ?? ''),
            'status' => $task_status,
            'status_message' => $task_message,
            'countdown_seconds' => $countdown,
            'task_available_at' => $log['task_available_at'] ?? null,
            'task_completed_at' => $log['task_completed_at'] ?? null,
            'verification_mode' => (string) ($task_row['verification_mode'] ?? 'instant'),
            'requires_quiz' => !empty($task_row['requires_quiz']),
            'requires_manual_review' => !empty($task_row['requires_manual_review']),
            'learning_title' => $definition['learning_title'] ?? '',
            'learning_url' => $definition['learning_url'] ?? '',
            'learning_opened' => !empty($metadata['learning_opened']),
            'quiz' => $definition['quiz'] ?? [],
            'profile_complete' => ($task_row['verification_mode'] ?? '') === 'profile' ? $profile_complete : null,
        ];
    };

    $tasks_payload = [];
    foreach ($current_tasks as $task_row) {
        $log = getTaskHubLatestLog((int) $user_id, (int) $task_row['id'], $current_day, $db);
        $tasks_payload[] = $build_task_payload($task_row, $log, true, $db);
    }
    $boost_gateway = taskHubGetBoostGatewayTask($current_day);
    if ($boost_gateway) {
        $has_boost_completion = false;
        if (!empty($day_completion['day_started_at'])) {
            $boost_check_stmt = $db->prepare("
                SELECT COUNT(*) AS total
                FROM user_task_logs utl
                INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                WHERE utl.user_id = ?
                  AND utl.status = 'completed'
                  AND mt.task_group = 'boosthub'
                  AND ROUND(mt.reward, 2) = ?
                  AND COALESCE(utl.task_completed_at, utl.completed_at) >= ?
            ");
            $boost_check_stmt->execute([(int) $user_id, round((float) $boost_gateway['reward'], 2), (string) $day_completion['day_started_at']]);
            $has_boost_completion = ((int) ($boost_check_stmt->fetch()['total'] ?? 0)) > 0;
        }

        $tasks_payload[] = [
            'id' => 0,
            'task_key' => (string) $boost_gateway['task_key'],
            'mission_step' => (int) $boost_gateway['mission_step'],
            'title' => (string) $boost_gateway['title'],
            'description' => (string) $boost_gateway['description'],
            'reward' => round((float) $boost_gateway['reward'], 2),
            'task_category' => (string) $boost_gateway['task_category'],
            'task_link' => (string) $boost_gateway['task_link'],
            'completion_steps' => (string) $boost_gateway['completion_steps'],
            'proof_notes' => (string) $boost_gateway['proof_notes'],
            'cta_label' => (string) $boost_gateway['cta_label'],
            'status' => $has_boost_completion ? 'completed' : 'available',
            'status_message' => $has_boost_completion ? 'Completed' : 'Complete a matching BoostHub task, then come back.',
            'countdown_seconds' => 0,
            'task_available_at' => null,
            'task_completed_at' => null,
            'verification_mode' => 'boosthub_redirect',
            'requires_quiz' => false,
            'requires_manual_review' => false,
            'learning_title' => '',
            'learning_url' => '',
            'quiz' => [],
            'profile_complete' => null,
        ];
    }

    $next_day_preview = [];
    foreach ($next_day_tasks as $next_task) {
        $next_day_preview[] = [
            'title' => (string) $next_task['title'],
            'description' => (string) ($next_task['description'] ?? ''),
        ];
    }

    $days_payload = [];
    for ($day = 1; $day <= TASKHUB_TOTAL_DAYS; $day++) {
        $day_tasks = $tasks_by_day[$day] ?? [];
        $completion = taskHubGetDayCompletionInfo((int) $user_id, $day, $db);
        $day_status = 'locked';
        $day_message = 'Locked';
        $day_countdown = 0;

        if ($day < $current_day) {
            $day_status = 'completed';
            $day_message = 'Completed';
        } elseif ($day === $current_day) {
            if (!empty($completion['all_completed'])) {
                $day_status = 'completed';
                $day_message = $day >= TASKHUB_TOTAL_DAYS ? 'Completed' : 'Day cleared. Waiting for server reset';
                if ($day < TASKHUB_TOTAL_DAYS) {
                    $day_countdown = max(0, $next_reset_ts - time());
                }
            } else {
                $day_status = $status;
                $day_message = $status_message;
            }
        } elseif ($day === ($current_day + 1) && !empty($day_completion['all_completed'])) {
            $day_status = 'locked';
            $day_message = 'Unlocks after server reset';
            $day_countdown = max(0, $next_reset_ts - time());
        }

        $day_task_payload = [];
        foreach ($day_tasks as $day_task) {
            if ($day > $current_day) {
                $day_task_payload[] = [
                    'id' => (int) $day_task['id'],
                    'task_key' => (string) $day_task['task_key'],
                    'mission_step' => (int) ($day_task['mission_step'] ?? 0),
                    'title' => 'Surprise Task',
                    'description' => 'This task will be revealed when Day ' . $day . ' unlocks.',
                    'reward' => round((float) ($day_task['reward'] ?? 0), 2),
                    'task_category' => (string) ($day_task['task_category'] ?? 'custom'),
                    'task_link' => '',
                    'completion_steps' => '',
                    'proof_notes' => '',
                    'cta_label' => '',
                    'status' => 'locked',
                    'status_message' => 'Hidden until this day unlocks',
                    'countdown_seconds' => 0,
                    'task_available_at' => null,
                    'task_completed_at' => null,
                    'verification_mode' => (string) ($day_task['verification_mode'] ?? 'instant'),
                    'requires_quiz' => !empty($day_task['requires_quiz']),
                    'requires_manual_review' => !empty($day_task['requires_manual_review']),
                    'learning_title' => '',
                    'learning_url' => '',
                    'quiz' => [],
                ];
                continue;
            }

            $day_log = getTaskHubLatestLog((int) $user_id, (int) $day_task['id'], $day, $db);
            $day_task_payload[] = $build_task_payload($day_task, $day_log, $day === $current_day, $db);
        }
        $day_boost_gateway = taskHubGetBoostGatewayTask($day);
        if ($day_boost_gateway && $day <= $current_day) {
            $day_info_started = !empty($completion['day_started_at']) ? (string) $completion['day_started_at'] : '';
            $day_boost_completed = false;
            if ($day_info_started !== '') {
                $day_boost_stmt = $db->prepare("
                    SELECT COUNT(*) AS total
                    FROM user_task_logs utl
                    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                    WHERE utl.user_id = ?
                      AND utl.status = 'completed'
                      AND mt.task_group = 'boosthub'
                      AND ROUND(mt.reward, 2) = ?
                      AND COALESCE(utl.task_completed_at, utl.completed_at) >= ?
                ");
                $day_boost_stmt->execute([(int) $user_id, round((float) $day_boost_gateway['reward'], 2), $day_info_started]);
                $day_boost_completed = ((int) ($day_boost_stmt->fetch()['total'] ?? 0)) > 0;
            }

            $day_task_payload[] = [
                'id' => 0,
                'task_key' => (string) $day_boost_gateway['task_key'],
                'mission_step' => (int) $day_boost_gateway['mission_step'],
                'title' => (string) $day_boost_gateway['title'],
                'description' => (string) $day_boost_gateway['description'],
                'reward' => round((float) $day_boost_gateway['reward'], 2),
                'task_category' => (string) $day_boost_gateway['task_category'],
                'task_link' => (string) $day_boost_gateway['task_link'],
                'completion_steps' => (string) $day_boost_gateway['completion_steps'],
                'proof_notes' => (string) $day_boost_gateway['proof_notes'],
                'cta_label' => (string) $day_boost_gateway['cta_label'],
                'status' => $day_boost_completed ? 'completed' : ($day === $current_day ? 'available' : 'locked'),
                'status_message' => $day_boost_completed ? 'Completed' : ($day === $current_day ? 'Complete in BoostHub to continue.' : 'Locked'),
                'countdown_seconds' => 0,
                'task_available_at' => null,
                'task_completed_at' => null,
                'verification_mode' => 'boosthub_redirect',
                'requires_quiz' => false,
                'requires_manual_review' => false,
                'learning_title' => '',
                'learning_url' => '',
                'quiz' => [],
                'profile_complete' => null,
            ];
        }

        $days_payload[] = [
            'day' => $day,
            'title' => (string) ($day_titles[$day] ?? ('Day ' . $day)),
            'status' => $day_status,
            'status_message' => $day_message,
            'countdown_seconds' => $day_countdown,
            'tasks' => $day_task_payload,
            'completed_tasks' => count(array_filter($day_task_payload, static function ($task) {
                return ($task['status'] ?? '') === 'completed';
            })),
            'total_tasks' => count($day_task_payload),
            'is_current' => $day === $current_day,
            'is_past' => $day < $current_day,
        ];

        $overall_completed_tasks += count(array_filter($day_task_payload, static function ($task) use ($day, $current_day) {
            return $day <= $current_day && ($task['status'] ?? '') === 'completed';
        }));
    }

    $current_day_completed_tasks = count(array_filter($tasks_payload, static function ($task) {
        return ($task['status'] ?? '') === 'completed';
    }));
    $current_day_total_tasks = count($tasks_payload);
    $current_day_progress_percent = $current_day_total_tasks > 0
        ? (int) round(($current_day_completed_tasks / $current_day_total_tasks) * 100)
        : 0;
    $overall_progress_percent = $overall_total_tasks > 0
        ? (int) round(($overall_completed_tasks / $overall_total_tasks) * 100)
        : 0;

    return [
        'access' => 'open',
        'current_day' => $current_day,
        'status' => $status,
        'status_message' => $status_message,
        'next_reset_at' => date('Y-m-d H:i:s', $next_reset_ts),
        'tasks' => $tasks_payload,
        'next_day' => min(TASKHUB_TOTAL_DAYS, $current_day + 1),
        'next_day_preview' => $next_day_preview,
        'completed_tasks' => count(array_filter($tasks_payload, static function ($task) {
            return ($task['status'] ?? '') === 'completed';
        })),
        'total_tasks' => count($tasks_payload),
        'current_day_progress_percent' => $current_day_progress_percent,
        'overall_completed_tasks' => $overall_completed_tasks,
        'overall_total_tasks' => $overall_total_tasks,
        'overall_progress_percent' => $overall_progress_percent,
        'profile_complete' => $profile_complete,
        'paused' => $status === 'paused',
        'mission_completed' => taskHubMissionCompleted((int) $user_id, $db),
        'has_missed_days' => taskHubHasMissedDays((int) $user_id, $db),
        'days' => $days_payload,
    ];
}

function taskHubCompleteInstantTask($user_id, array $task_row, array $log_row, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $user = getUserById((int) $user_id);
    $metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];

    if (($task_row['verification_mode'] ?? '') === 'profile') {
        if (!isUserProfileComplete($user)) {
            throw new RuntimeException('Open your profile page and complete your avatar, full name, username, and country before finishing this task.');
        }
    }

    if (($task_row['verification_mode'] ?? '') === 'wallet') {
        $wallet_address = trim((string) ($payload['wallet_address'] ?? $user['wallet_address'] ?? ''));
        if ($wallet_address === '') {
            throw new RuntimeException('Add a wallet address to finish this task.');
        }
        $wallet_update = $db->prepare("UPDATE users SET wallet_address = ?, updated_at = NOW() WHERE id = ?");
        $wallet_update->execute([$wallet_address, (int) $user_id]);
    }

    if ((string) ($task_row['task_key'] ?? '') === 'day2_ui_exploration') {
        $metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];
        if (empty($metadata['learning_opened'])) {
            throw new RuntimeException('Please open the UI exploration page before completing this task.');
        }
    }

    $reward = getTaskHubRewardAmountForTask((int) $user_id, $task_row, $log_row, $db);
    if ($reward <= 0) {
        throw new RuntimeException('TaskHub phase1 reward cap reached.');
    }

    $completed_at = date('Y-m-d H:i:s');
    taskHubUpdateLog((int) $log_row['id'], [
        'status' => 'completed',
        'completed_at' => $completed_at,
        'task_completed_at' => $completed_at,
        'metadata' => $metadata,
    ], $db);

    $entry = addRewardLedgerEntry(
        (int) $user_id,
        $reward,
        'mini_task',
        'taskhub_completion',
        'available',
        'taskhub:' . (string) ($task_row['task_key'] ?? $task_row['id']),
        $db,
        'phase1',
        'beginner'
    );

    if ((int) ($task_row['mission_step'] ?? 0) === 0) {
        taskHubCreateFollowupTasksAfterCheckIn((int) $user_id, (int) ($task_row['mission_day'] ?? 0), $completed_at, $db);
    }

    $user = syncTaskHubDayProgress((int) $user_id, $db);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }
    syncUserLevelStatus((int) $user_id, $db);

    return $entry;
}

function taskHubSubmitQuizTask($user_id, array $task_row, array $log_row, array $answers, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $log_metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];
    if (empty($log_metadata['learning_opened'])) {
        throw new RuntimeException('Please open and review the learning page before submitting this quiz.');
    }

    $definition = getTaskHubMissionTaskDefinitionByKey((string) ($task_row['task_key'] ?? ''));
    $questions = $definition['quiz'] ?? [];
    if (empty($questions)) {
        throw new RuntimeException('Quiz definition not found.');
    }

    $score = 0;
    foreach ($questions as $index => $question) {
        if ((int) ($answers[$index] ?? -1) === (int) ($question['answer'] ?? -2)) {
            $score++;
        }
    }

    $attempt_stmt = $db->prepare("
        INSERT INTO taskhub_quiz_attempts (user_id, task_id, mission_day, score, total_questions, answers_json)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $attempt_stmt->execute([
        (int) $user_id,
        (int) $task_row['id'],
        (int) ($task_row['mission_day'] ?? 0),
        (int) $score,
        count($questions),
        json_encode(array_values($answers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    if ($score < (int) ($task_row['min_quiz_score'] ?? 0)) {
        taskHubUpdateLog((int) $log_row['id'], [
            'status' => 'failed',
            'score' => (int) $score,
            'metadata' => ['last_score' => (int) $score, 'required_score' => (int) ($task_row['min_quiz_score'] ?? 0)],
        ], $db);
        throw new RuntimeException('Pass the quiz to proceed.');
    }

    return taskHubCompleteInstantTask((int) $user_id, $task_row, $log_row, ['quiz_score' => $score], $db);
}

function taskHubSubmitManualTask($user_id, array $task_row, array $log_row, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $proof = trim((string) ($payload['proof'] ?? ''));
    $x_handle = trim((string) ($payload['x_handle'] ?? ''));
    $telegram_handle = trim((string) ($payload['telegram_handle'] ?? ''));

    if ((string) ($task_row['task_key'] ?? '') === 'day1_social_follow') {
        if ($x_handle === '' && $telegram_handle === '') {
            throw new RuntimeException('Add your X or Telegram username/URL for review.');
        }

        $proof_parts = [];
        if ($x_handle !== '') {
            $proof_parts[] = 'X: ' . $x_handle;
        }
        if ($telegram_handle !== '') {
            $proof_parts[] = 'Telegram: ' . $telegram_handle;
        }
        $proof = implode(' | ', $proof_parts);
    } elseif ($proof === '') {
        throw new RuntimeException('Proof is required for this task.');
    }

    taskHubUpdateLog((int) $log_row['id'], [
        'status' => 'submitted',
        'proof_data' => $proof,
        'metadata' => [
            'submitted_at' => date('Y-m-d H:i:s'),
            'x_handle' => $x_handle,
            'telegram_handle' => $telegram_handle,
        ],
    ], $db);

    return ['submitted' => true];
}

function submitTaskHubTask($user_id, $task_key, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = getUserById((int) $user_id);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    if (normalizeUserLevel($user['level'] ?? 'beginner') !== 'beginner') {
        throw new RuntimeException('TaskHub is available for Beginner accounts only.');
    }

    if (!empty($user['reward_frozen'])) {
        throw new RuntimeException('Rewards are temporarily frozen for this account.');
    }

    $signals = getUserSecuritySignals((int) $user_id, $db);
    if (!empty($signals['is_suspicious'])) {
        throw new RuntimeException('Suspicious activity detected. Try again later.');
    }

    syncTaskHubDayProgress((int) $user_id, $db);

    $task_stmt = $db->prepare("
        SELECT *
        FROM mini_tasks
        WHERE task_key = ?
          AND task_group = 'mission'
          AND is_active = 1
        LIMIT 1
    ");
    $task_stmt->execute([(string) $task_key]);
    $task_row = $task_stmt->fetch();
    if (!$task_row) {
        throw new RuntimeException('TaskHub task not found.');
    }

    if ((int) ($task_row['mission_day'] ?? 0) !== (int) ($user['current_day'] ?? 1)) {
        throw new RuntimeException('Complete previous tasks to continue.');
    }

    $log_row = getTaskHubLatestLog((int) $user_id, (int) $task_row['id'], (int) ($task_row['mission_day'] ?? 0), $db);
    if (!$log_row) {
        throw new RuntimeException('This task is still locked.');
    }

    if ((string) ($log_row['status'] ?? '') === 'submitted') {
        throw new RuntimeException('This task is awaiting manual review.');
    }

    $available_at_ts = !empty($log_row['task_available_at']) ? strtotime((string) $log_row['task_available_at']) : 0;
    if ($available_at_ts > time()) {
        throw new RuntimeException('Next task unlocks in ' . taskHubFormatDuration(max(0, $available_at_ts - time())) . '.');
    }

    $definition = getTaskHubMissionTaskDefinitionByKey((string) ($task_row['task_key'] ?? ''));
    $requires_learning_open = !empty($definition['learning_url']);
    if ($requires_learning_open) {
        $log_metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];
        if (empty($log_metadata['learning_opened'])) {
            throw new RuntimeException('Please open and validate the learning page before submitting this task.');
        }
    }

    if (($task_row['verification_mode'] ?? '') === 'quiz') {
        return taskHubSubmitQuizTask((int) $user_id, $task_row, $log_row, $payload['answers'] ?? [], $db);
    }

    if (!empty($task_row['requires_manual_review'])) {
        return taskHubSubmitManualTask((int) $user_id, $task_row, $log_row, $payload, $db);
    }

    return taskHubCompleteInstantTask((int) $user_id, $task_row, $log_row, $payload, $db);
}

function reviewTaskHubSubmission($log_id, $approve, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $stmt = $db->prepare("
        SELECT utl.*, mt.task_key, mt.verification_mode, mt.reward, mt.mission_day, mt.mission_step, mt.task_group, mt.title
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.id = ?
          AND utl.status = 'submitted'
        LIMIT 1
    ");
    $stmt->execute([(int) $log_id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Submission not found.');
    }

    if (!$approve) {
        taskHubUpdateLog((int) $row['id'], [
            'status' => 'failed',
            'metadata' => ['reviewed_at' => date('Y-m-d H:i:s'), 'review_outcome' => 'rejected'],
        ], $db);
        return ['approved' => false];
    }

    if ((string) ($row['task_group'] ?? 'mission') === 'boosthub') {
        $reward = round((float) ($row['reward'] ?? 0), 8);
        if ($reward <= 0) {
            throw new RuntimeException('BoostHub reward amount is invalid.');
        }
    } else {
        $reward = getTaskHubRewardAmountForTask((int) $row['user_id'], $row, $row, $db);
        if ($reward <= 0) {
            throw new RuntimeException('TaskHub phase1 reward cap reached.');
        }
    }

    $completed_at = date('Y-m-d H:i:s');
    taskHubUpdateLog((int) $row['id'], [
        'status' => 'completed',
        'completed_at' => $completed_at,
        'task_completed_at' => $completed_at,
        'metadata' => ['reviewed_at' => $completed_at, 'review_outcome' => 'approved'],
    ], $db);

    $entry = addRewardLedgerEntry(
        (int) $row['user_id'],
        $reward,
        'mini_task',
        (string) ($row['task_group'] ?? 'mission') === 'boosthub' ? 'boosthub_manual_approval' : 'taskhub_manual_approval',
        'available',
        ((string) ($row['task_group'] ?? 'mission') === 'boosthub' ? 'boosthub:' : 'taskhub:') . (string) ($row['task_key'] ?? $row['task_id']),
        $db,
        'phase1',
        'beginner'
    );

    if ((string) ($row['task_group'] ?? 'mission') === 'mission') {
        syncTaskHubDayProgress((int) $row['user_id'], $db);
    }
    syncUserLevelStatus((int) $row['user_id'], $db);
    return ['approved' => true, 'entry' => $entry, 'task_group' => (string) ($row['task_group'] ?? 'mission')];
}

function rememberMeColumnsExist(PDO $db = null, array $columns = ['remember_token_hash', 'remember_token_expires_at']) {
    $db = $db ?: getDBConnection();
    $placeholders = implode(',', array_fill(0, count($columns), '?'));

    $sql = "
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME IN ($placeholders)
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([DB_NAME], $columns));
    $found_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return count(array_intersect($columns, $found_columns)) === count($columns);
}

function setRememberMeCookie($token, $expires_at) {
    setcookie(REMEMBER_ME_COOKIE_NAME, $token, $expires_at, '/', '', false, true);
    $_COOKIE[REMEMBER_ME_COOKIE_NAME] = $token;
}

function clearRememberMeCookie() {
    setcookie(REMEMBER_ME_COOKIE_NAME, '', time() - 3600, '/');
    unset($_COOKIE[REMEMBER_ME_COOKIE_NAME]);
}

function clearRememberMeTokenForUser($user_id, PDO $db = null) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        clearRememberMeCookie();
        return;
    }

    $db = $db ?: getDBConnection();
    ensureRememberMeSchema($db);

    if (rememberMeColumnsExist($db)) {
        $stmt = $db->prepare("
            UPDATE users
            SET remember_token_hash = NULL,
                remember_token_expires_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$user_id]);
    }

    clearRememberMeCookie();
}

function clearRememberMeTokenByCookie(PDO $db = null) {
    $raw_token = (string) ($_COOKIE[REMEMBER_ME_COOKIE_NAME] ?? '');
    if ($raw_token === '') {
        clearRememberMeCookie();
        return;
    }

    $db = $db ?: getDBConnection();
    ensureRememberMeSchema($db);

    if (rememberMeColumnsExist($db)) {
        $token_hash = hash('sha256', $raw_token);
        $stmt = $db->prepare("
            UPDATE users
            SET remember_token_hash = NULL,
                remember_token_expires_at = NULL
            WHERE remember_token_hash = ?
        ");
        $stmt->execute([$token_hash]);
    }

    clearRememberMeCookie();
}

function issueRememberMeToken($user_id, PDO $db = null) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $db = $db ?: getDBConnection();
    ensureRememberMeSchema($db);

    $raw_token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $raw_token);
    $expires_at_ts = time() + REMEMBER_ME_LIFETIME_SECONDS;
    $expires_at = date('Y-m-d H:i:s', $expires_at_ts);

    $stmt = $db->prepare("
        UPDATE users
        SET remember_token_hash = ?,
            remember_token_expires_at = ?,
            last_active = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$token_hash, $expires_at, $user_id]);

    setRememberMeCookie($raw_token, $expires_at_ts);
    return true;
}

function refreshRememberMeTokenIfPresent($user_id, PDO $db = null) {
    $user_id = (int) $user_id;
    $raw_token = (string) ($_COOKIE[REMEMBER_ME_COOKIE_NAME] ?? '');

    if ($user_id <= 0 || $raw_token === '') {
        return false;
    }

    $db = $db ?: getDBConnection();
    ensureRememberMeSchema($db);

    $token_hash = hash('sha256', $raw_token);
    $expires_at_ts = time() + REMEMBER_ME_LIFETIME_SECONDS;
    $expires_at = date('Y-m-d H:i:s', $expires_at_ts);

    $stmt = $db->prepare("
        UPDATE users
        SET remember_token_expires_at = ?
        WHERE id = ?
          AND remember_token_hash = ?
    ");
    $stmt->execute([$expires_at, $user_id, $token_hash]);

    if ($stmt->rowCount() > 0) {
        setRememberMeCookie($raw_token, $expires_at_ts);
        return true;
    }

    return false;
}

function touchAuthenticatedUserActivity($user_id, PDO $db = null) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return false;
    }

    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        UPDATE users
        SET last_active = NOW(),
            last_ip = ?
        WHERE id = ?
    ");
    $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? null, $user_id]);

    refreshRememberMeTokenIfPresent($user_id, $db);
    return true;
}

function restoreRememberedSession() {
    $raw_token = (string) ($_COOKIE[REMEMBER_ME_COOKIE_NAME] ?? '');
    if ($raw_token === '') {
        return false;
    }

    $db = getDBConnection();
    ensureRememberMeSchema($db);

    if (!rememberMeColumnsExist($db)) {
        clearRememberMeCookie();
        return false;
    }

    $token_hash = hash('sha256', $raw_token);
    $stmt = $db->prepare("
        SELECT *
        FROM users
        WHERE remember_token_hash = ?
          AND remember_token_expires_at IS NOT NULL
          AND remember_token_expires_at >= NOW()
        LIMIT 1
    ");
    $stmt->execute([$token_hash]);
    $user = $stmt->fetch();

    if (!$user || ($user['status'] ?? '') !== 'active' || (int) ($user['email_verified'] ?? 0) !== 1) {
        clearRememberMeTokenByCookie($db);
        return false;
    }

    establishAuthenticatedSession($user, false);
    issueRememberMeToken((int) $user['id'], $db);
    return true;
}

function ensureLevelEngineSchema(PDO $db = null) {
    static $schema_ready = false;

    if ($schema_ready) {
        return;
    }

    $db = $db ?: getDBConnection();

    if (!tableHasColumn('users', 'referral_qualified_at')) {
        $db->exec("ALTER TABLE users ADD COLUMN referral_qualified_at DATETIME NULL AFTER valid_referrals");
    }

    if (!tableHasColumn('users', 'referral_earnings')) {
        $db->exec("ALTER TABLE users ADD COLUMN referral_earnings DECIMAL(15,2) DEFAULT 0.00 AFTER referral_qualified_at");
    }

    if (!tableHasColumn('reviews', 'auto_approved_at')) {
        $db->exec("ALTER TABLE reviews ADD COLUMN auto_approved_at DATETIME NULL AFTER approval_note");
    }

    if (!tableHasColumn('reviews', 'auto_approved_by_level')) {
        $db->exec("ALTER TABLE reviews ADD COLUMN auto_approved_by_level TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_approved_at");
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS review_reactions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            review_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            reaction_type VARCHAR(20) NOT NULL DEFAULT 'like',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_review_user_reaction (review_id, user_id, reaction_type),
            KEY idx_review_reaction_review (review_id),
            KEY idx_review_reaction_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS content_flags (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            target_type VARCHAR(20) NOT NULL,
            target_id INT UNSIGNED NOT NULL,
            reason VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_content_flag_user_target (user_id, target_type, target_id),
            KEY idx_content_flags_target (target_type, target_id),
            KEY idx_content_flags_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $schema_ready = true;
}

function normalizeUserLevel($level) {
    $level = strtolower(trim((string) $level));

    if ($level === 'premium') {
        return 'pro';
    }

    if (in_array($level, ['beginner', 'pro', 'expert'], true)) {
        return $level;
    }

    return 'beginner';
}

function levelDisplayName($level) {
    return ucfirst(normalizeUserLevel($level));
}

function getLevelSystemDefinitions() {
    static $definitions = null;

    if ($definitions !== null) {
        return $definitions;
    }

    $definitions = [
        'beginner' => [
            'label' => 'Beginner',
            'score_bonus' => 0,
            'trust_weight' => 1.0,
            'approval_lane' => 'standard',
            'approval_label' => '24-48 hours',
            'bonus_accuracy_threshold' => 0,
            'promotion_approved_reviews' => 0,
            'promotion_valid_referrals' => 0,
            'promotion_accuracy' => 0,
            'promotion_completed_tasks' => 0,
            'promotion_account_age_days' => 0,
            'demotion_rejection_ratio' => 1.0,
            'referral_commission_percent' => REFERRAL_COMMISSION_PERCENT,
        ],
        'pro' => [
            'label' => 'Pro',
            'score_bonus' => 5,
            'trust_weight' => PRO_TRUST_WEIGHT,
            'approval_lane' => 'priority',
            'approval_label' => 'Priority ~12 hours',
            'bonus_accuracy_threshold' => 70,
            'promotion_approved_reviews' => 0,
            'promotion_valid_referrals' => PRO_MIN_VALID_REFERRALS,
            'promotion_accuracy' => 0,
            'promotion_completed_tasks' => PRO_MIN_COMPLETED_TASKS,
            'promotion_account_age_days' => PRO_MIN_ACCOUNT_AGE_DAYS,
            'demotion_rejection_ratio' => 0.35,
            'referral_commission_percent' => REFERRAL_COMMISSION_PERCENT,
        ],
        'expert' => [
            'label' => 'Expert',
            'score_bonus' => 10,
            'trust_weight' => EXPERT_TRUST_WEIGHT,
            'approval_lane' => 'auto',
            'approval_label' => 'Auto-approved, validated in background',
            'bonus_accuracy_threshold' => 85,
            'promotion_approved_reviews' => 100,
            'promotion_valid_referrals' => 10,
            'promotion_accuracy' => 85,
            'promotion_completed_tasks' => PRO_MIN_COMPLETED_TASKS,
            'promotion_account_age_days' => PRO_MIN_ACCOUNT_AGE_DAYS,
            'max_rejection_ratio' => 0.15,
            'demotion_rejection_ratio' => 0.20,
            'referral_commission_percent' => EXPERT_REFERRAL_COMMISSION_PERCENT,
        ],
    ];

    return $definitions;
}

function getLevelPolicy($level) {
    $level = normalizeUserLevel($level);
    $definitions = getLevelSystemDefinitions();

    return $definitions[$level] ?? $definitions['beginner'];
}

function calculateAccuracyRate($approved_count, $total_count) {
    $approved_count = max(0, (int) $approved_count);
    $total_count = max(0, (int) $total_count);

    if ($total_count === 0) {
        return 0.0;
    }

    return round(($approved_count / $total_count) * 100, 2);
}

function calculateRejectionRatio($rejected_count, $total_count) {
    $rejected_count = max(0, (int) $rejected_count);
    $total_count = max(0, (int) $total_count);

    if ($total_count === 0) {
        return 0.0;
    }

    return round($rejected_count / $total_count, 4);
}

function getUserReviewPerformanceStats($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    $user_id = (int) $user_id;
    $user_stmt = $db->prepare("
        SELECT
            id,
            level,
            current_day,
            last_day_completed_at,
            valid_referrals,
            referred_by,
            referral_qualified_at,
            referral_earnings,
            total_rex_earned,
            total_reviews,
            approved_reviews_count,
            created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    if (!$user) {
        return [
            'user_id' => $user_id,
            'level' => 'beginner',
            'approved_reviews' => 0,
            'rejected_reviews' => 0,
            'flagged_reviews' => 0,
            'total_reviews' => 0,
            'valid_referrals' => 0,
            'referred_by' => 0,
            'referral_qualified_at' => null,
            'mission_completed' => false,
            'referral_earnings' => 0.0,
            'accuracy' => 0.0,
            'rejection_ratio' => 0.0,
            'total_rex_earned' => 0.0,
        ];
    }

    $review_stmt = $db->prepare("
        SELECT
            COUNT(*) AS total_reviews,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_reviews,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_reviews,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) AS flagged_reviews
        FROM reviews
        WHERE user_id = ?
    ");
    $review_stmt->execute([$user_id]);
    $review_stats = $review_stmt->fetch() ?: [];

    $approved_reviews = (int) ($review_stats['approved_reviews'] ?? 0);
    $rejected_reviews = (int) ($review_stats['rejected_reviews'] ?? 0);
    $flagged_reviews = (int) ($review_stats['flagged_reviews'] ?? 0);
    $total_reviews = (int) ($review_stats['total_reviews'] ?? 0);
    $task_stats = getUserMiniTaskStats($user_id, $db);

    return [
        'user_id' => $user_id,
        'level' => normalizeUserLevel($user['level'] ?? 'beginner'),
        'approved_reviews' => $approved_reviews,
        'rejected_reviews' => $rejected_reviews,
        'flagged_reviews' => $flagged_reviews,
        'total_reviews' => $total_reviews,
        'valid_referrals' => (int) ($user['valid_referrals'] ?? 0),
        'referred_by' => (int) ($user['referred_by'] ?? 0),
        'referral_qualified_at' => $user['referral_qualified_at'] ?? null,
        'mission_completed' => taskHubMissionCompleted($user_id, $db),
        'referral_earnings' => (float) ($user['referral_earnings'] ?? 0),
        'accuracy' => calculateAccuracyRate($approved_reviews, $total_reviews),
        'rejection_ratio' => calculateRejectionRatio($rejected_reviews, $total_reviews),
        'total_rex_earned' => (float) ($user['total_rex_earned'] ?? 0),
        'completed_tasks' => (int) ($task_stats['completed_total'] ?? 0),
        'completed_tasks_today' => (int) ($task_stats['completed_today'] ?? 0),
        'account_age_days' => max(0, (int) floor((time() - strtotime((string) ($user['created_at'] ?? 'now'))) / 86400)),
        'current_day' => (int) ($user['current_day'] ?? 1),
        'last_day_completed_at' => $user['last_day_completed_at'] ?? null,
    ];
}

function levelPromotionCriteriaMet($target_level, array $stats) {
    $target_level = normalizeUserLevel($target_level);
    $policy = getLevelPolicy($target_level);

    if ($target_level === 'beginner') {
        return true;
    }

    if ($target_level === 'pro') {
        if (!empty($stats['mission_completed'])) {
            $signals = getUserSecuritySignals((int) ($stats['user_id'] ?? 0));
            return empty($signals['is_suspicious']);
        }

        if ((int) ($stats['completed_tasks'] ?? 0) < (int) ($policy['promotion_completed_tasks'] ?? 0)) {
            return false;
        }

        if ((int) ($stats['valid_referrals'] ?? 0) < (int) ($policy['promotion_valid_referrals'] ?? 0)) {
            return false;
        }

        if ((int) ($stats['account_age_days'] ?? 0) < (int) ($policy['promotion_account_age_days'] ?? 0)) {
            return false;
        }

        $signals = getUserSecuritySignals((int) ($stats['user_id'] ?? 0));
        return empty($signals['is_suspicious']);
    }

    if ((int) ($stats['approved_reviews'] ?? 0) < (int) ($policy['promotion_approved_reviews'] ?? 0)) {
        return false;
    }

    if ((int) ($stats['valid_referrals'] ?? 0) < (int) ($policy['promotion_valid_referrals'] ?? 0)) {
        return false;
    }

    if ((float) ($stats['accuracy'] ?? 0) < (float) ($policy['promotion_accuracy'] ?? 0)) {
        return false;
    }

    if (isset($policy['max_rejection_ratio']) && (float) ($stats['rejection_ratio'] ?? 0) > (float) $policy['max_rejection_ratio']) {
        return false;
    }

    return true;
}

function resolveStoredUserLevel($current_level, array $stats) {
    $current_level = normalizeUserLevel($current_level);

    if ($current_level === 'expert' && (float) ($stats['rejection_ratio'] ?? 0) > (float) getLevelPolicy('expert')['demotion_rejection_ratio']) {
        return levelPromotionCriteriaMet('pro', $stats) ? 'pro' : 'beginner';
    }

    if ($current_level === 'pro' && (float) ($stats['rejection_ratio'] ?? 0) > (float) getLevelPolicy('pro')['demotion_rejection_ratio']) {
        return 'beginner';
    }

    if (levelPromotionCriteriaMet('expert', $stats)) {
        return 'expert';
    }

    if ($current_level === 'beginner' && levelPromotionCriteriaMet('pro', $stats)) {
        return 'pro';
    }

    return $current_level;
}

function isLevelBonusActive($level, array $stats) {
    $level = normalizeUserLevel($level);
    $policy = getLevelPolicy($level);
    $required_accuracy = (float) ($policy['bonus_accuracy_threshold'] ?? 0);

    if ($required_accuracy <= 0) {
        return true;
    }

    return (float) ($stats['accuracy'] ?? 0) >= $required_accuracy;
}

function getUserLevelState($user_or_id, PDO $db = null, array $stats_override = []) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    if (is_array($user_or_id)) {
        $user_id = (int) ($user_or_id['id'] ?? 0);
        $current_level = normalizeUserLevel($user_or_id['level'] ?? 'beginner');
    } else {
        $user_id = (int) $user_or_id;
        $current_user = getUserById($user_id);
        $current_level = normalizeUserLevel($current_user['level'] ?? 'beginner');
    }

    $stats = !empty($stats_override) ? $stats_override : getUserReviewPerformanceStats($user_id, $db);
    $stats['level'] = normalizeUserLevel($stats['level'] ?? $current_level);
    $current_level = normalizeUserLevel($stats['level']);
    $recommended_level = resolveStoredUserLevel($current_level, $stats);
    $bonus_level = $current_level;
    $bonus_active = isLevelBonusActive($bonus_level, $stats);
    $policy = getLevelPolicy($bonus_level);
    $next_level = null;

    if ($recommended_level === 'beginner') {
        $next_level = 'pro';
    } elseif ($recommended_level === 'pro') {
        $next_level = 'expert';
    }

    return [
        'user_id' => $user_id,
        'level' => $current_level,
        'display_level' => levelDisplayName($current_level),
        'recommended_level' => $recommended_level,
        'recommended_display_level' => levelDisplayName($recommended_level),
        'score_bonus' => $bonus_active ? (int) ($policy['score_bonus'] ?? 0) : 0,
        'base_score_bonus' => (int) ($policy['score_bonus'] ?? 0),
        'bonus_active' => $bonus_active,
        'bonus_status' => $bonus_active ? 'active' : 'suspended',
        'trust_weight' => (float) ($policy['trust_weight'] ?? 1),
        'approval_lane' => (string) ($policy['approval_lane'] ?? 'standard'),
        'approval_label' => (string) ($policy['approval_label'] ?? '24-48 hours'),
        'referral_commission_percent' => (int) ($policy['referral_commission_percent'] ?? REFERRAL_COMMISSION_PERCENT),
        'stats' => $stats,
        'accuracy' => (float) ($stats['accuracy'] ?? 0),
        'rejection_ratio' => (float) ($stats['rejection_ratio'] ?? 0),
        'next_level' => $next_level,
    ];
}

function syncUserReviewCounters($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $user_id = (int) $user_id;

    $stats = getUserReviewPerformanceStats($user_id, $db);
    $stmt = $db->prepare("
        UPDATE users
        SET total_reviews = ?,
            approved_reviews_count = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        (int) ($stats['total_reviews'] ?? 0),
        (int) ($stats['approved_reviews'] ?? 0),
        $user_id,
    ]);

    return $stats;
}

function syncUserLevelStatus($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    $user_id = (int) $user_id;
    $current_user = getUserById($user_id);
    if (!$current_user) {
        return null;
    }

    $stats = syncUserReviewCounters($user_id, $db);
    $new_level = resolveStoredUserLevel($current_user['level'] ?? 'beginner', $stats);
    $is_expert = $new_level === 'expert' ? 1 : 0;
    $is_pro = $new_level === 'pro' ? 1 : 0;
    $set_expert_at = $is_expert === 1 && empty($current_user['expert_at']);

    $sql = "
        UPDATE users
        SET level = ?,
            is_expert = ?,
            is_premium = ?,
            updated_at = NOW(),
            expert_at = " . ($set_expert_at ? "NOW()" : ($is_expert === 0 ? "NULL" : "expert_at")) . "
        WHERE id = ?
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$new_level, $is_expert, $is_pro, $user_id]);

    if ((int) ($_SESSION['user_id'] ?? 0) === $user_id) {
        $_SESSION['level'] = $new_level;
    }

    return getUserLevelState(['id' => $user_id, 'level' => $new_level], $db, array_merge($stats, ['level' => $new_level]));
}

function calculateReviewFinalScore($base_score, $user_level_state, array $review_context = []) {
    $base_score = max(0, min(100, round((float) $base_score, 2)));
    $level_state = is_array($user_level_state) ? $user_level_state : getUserLevelState((int) $user_level_state);
    $level = normalizeUserLevel($level_state['level'] ?? 'beginner');
    $total_reviews = (int) ($review_context['user_total_reviews'] ?? $level_state['stats']['total_reviews'] ?? 0);

    $penalty_percent = 0;
    $score_after_penalty = $base_score;

    if ($level === 'beginner' && $total_reviews <= 1) {
        $penalty_percent = 5;
        $score_after_penalty = round($base_score * 0.95, 2);
    }

    $level_bonus = (int) ($level_state['score_bonus'] ?? 0);
    $final_score = min(100, round($score_after_penalty + $level_bonus, 2));

    return [
        'base_score' => $base_score,
        'score_after_penalty' => $score_after_penalty,
        'penalty_percent' => $penalty_percent,
        'level_bonus' => $level_bonus,
        'final_score' => $final_score,
        'bonus_active' => !empty($level_state['bonus_active']),
    ];
}

function calculateRewardFromFinalScore($final_score, $project_max_reward, $wallet_type) {
    $final_score = (float) $final_score;
    $project_max_reward = (float) $project_max_reward;
    $wallet_type = strtolower(trim((string) $wallet_type));

    if ($final_score < 50 || $project_max_reward <= 0) {
        return 0;
    }

    $reward = ($final_score / 100) * $project_max_reward;
    if ($wallet_type === 'custodial') {
        $reward *= 0.5;
    }

    return (int) round($reward, 0);
}

function getApprovalLaneLabel($user_or_level_state) {
    $level_state = is_array($user_or_level_state) ? $user_or_level_state : getUserLevelState($user_or_level_state);
    return (string) ($level_state['approval_label'] ?? '24-48 hours');
}

function shouldAutoApproveReview($user_or_level_state) {
    $level_state = is_array($user_or_level_state) ? $user_or_level_state : getUserLevelState($user_or_level_state);
    return normalizeUserLevel($level_state['level'] ?? 'beginner') === 'expert'
        && !empty($level_state['bonus_active']);
}

function calculateReferralCommissionAmount($referrer_level, $reward_amount) {
    $rate = (int) (getLevelPolicy($referrer_level)['referral_commission_percent'] ?? REFERRAL_COMMISSION_PERCENT);
    $reward_amount = max(0, (float) $reward_amount);

    if ($reward_amount <= 0 || $rate <= 0) {
        return 0.0;
    }

    return round(($reward_amount * $rate) / 100, 2);
}

function maybeActivateReferralQualification($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);
    ensureRewardClaimSchema($db);

    $stats = getUserReviewPerformanceStats($user_id, $db);
    if ((int) ($stats['referred_by'] ?? 0) <= 0 || !empty($stats['referral_qualified_at'])) {
        return false;
    }

    $qualifies = canReferralBecomeValid((int) $user_id, $db);

    if (!$qualifies) {
        return false;
    }

    $mark = $db->prepare("
        UPDATE users
        SET referral_qualified_at = NOW()
        WHERE id = ?
          AND referral_qualified_at IS NULL
    ");
    $mark->execute([(int) $user_id]);

    if ($mark->rowCount() > 0) {
        $credit_referrer = $db->prepare("
            UPDATE users
            SET valid_referrals = valid_referrals + 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $credit_referrer->execute([(int) $stats['referred_by']]);
        return true;
    }

    return false;
}

function creditReferralCommissionForReview($review_user_id, $reward_amount, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    $review_user_id = (int) $review_user_id;
    $reward_amount = (float) $reward_amount;
    if ($review_user_id <= 0 || $reward_amount <= 0) {
        return 0.0;
    }

    maybeActivateReferralQualification($review_user_id, $db);

    $stmt = $db->prepare("
        SELECT referred_by, referral_qualified_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$review_user_id]);
    $referral = $stmt->fetch();

    if (!$referral || (int) ($referral['referred_by'] ?? 0) <= 0 || empty($referral['referral_qualified_at'])) {
        return 0.0;
    }

    $referrer_id = (int) $referral['referred_by'];
    $referrer_state = syncUserLevelStatus($referrer_id, $db);
    $commission = calculateReferralCommissionAmount($referrer_state['level'] ?? 'beginner', $reward_amount);

    if ($commission <= 0) {
        return 0.0;
    }

    $update = $db->prepare("
        UPDATE users
        SET referral_earnings = referral_earnings + ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$commission, $referrer_id]);
    addRewardLedgerEntry(
        $referrer_id,
        $commission,
        'referral',
        'review_referral_commission',
        'available',
        'referral_review:' . $review_user_id,
        $db,
        resolveRewardPhase('referral', $referrer_state['level'] ?? 'beginner'),
        $referrer_state['level'] ?? 'beginner'
    );

    return $commission;
}

function reverseReferralCommissionForReview($review_user_id, $reward_amount, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    $review_user_id = (int) $review_user_id;
    $reward_amount = (float) $reward_amount;
    if ($review_user_id <= 0 || $reward_amount <= 0) {
        return 0.0;
    }

    $stmt = $db->prepare("SELECT referred_by, referral_qualified_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$review_user_id]);
    $referral = $stmt->fetch();

    if (!$referral || (int) ($referral['referred_by'] ?? 0) <= 0 || empty($referral['referral_qualified_at'])) {
        return 0.0;
    }

    $referrer_id = (int) $referral['referred_by'];
    $referrer_state = getUserLevelState($referrer_id, $db);
    $commission = calculateReferralCommissionAmount($referrer_state['level'] ?? 'beginner', $reward_amount);

    if ($commission <= 0) {
        return 0.0;
    }

    $update = $db->prepare("
        UPDATE users
        SET referral_earnings = GREATEST(referral_earnings - ?, 0),
            updated_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$commission, $referrer_id]);
    addRewardLedgerEntry(
        $referrer_id,
        -1 * $commission,
        'referral',
        'review_referral_reversal',
        'available',
        'referral_review_reversal:' . $review_user_id,
        $db,
        resolveRewardPhase('referral', $referrer_state['level'] ?? 'beginner'),
        $referrer_state['level'] ?? 'beginner'
    );

    return $commission;
}

function syncProjectAggregateMetrics($project_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $project_id = (int) $project_id;

    if ($project_id <= 0) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT
            COUNT(r.id) AS total_reviews,
            COALESCE(AVG(r.rating), 0) AS avg_rating,
            COALESCE(
                SUM(
                    COALESCE(NULLIF(r.review_score, 0), r.rating * 20) *
                    CASE
                        WHEN LOWER(COALESCE(u.level, 'beginner')) = 'expert' THEN " . EXPERT_TRUST_WEIGHT . "
                        WHEN LOWER(COALESCE(u.level, 'beginner')) IN ('pro', 'premium') THEN " . PRO_TRUST_WEIGHT . "
                        ELSE 1
                    END
                ) /
                NULLIF(
                    SUM(
                        CASE
                            WHEN LOWER(COALESCE(u.level, 'beginner')) = 'expert' THEN " . EXPERT_TRUST_WEIGHT . "
                            WHEN LOWER(COALESCE(u.level, 'beginner')) IN ('pro', 'premium') THEN " . PRO_TRUST_WEIGHT . "
                            ELSE 1
                        END
                    ),
                    0
                ),
                0
            ) AS weighted_project_score
        FROM reviews r
        INNER JOIN users u ON u.id = r.user_id
        WHERE r.project_id = ?
          AND r.status = 'approved'
    ");
    $stmt->execute([$project_id]);
    $stats = $stmt->fetch() ?: [];

    $total_reviews = (int) ($stats['total_reviews'] ?? 0);
    $avg_rating = round((float) ($stats['avg_rating'] ?? 0), 2);
    $project_score = round((float) ($stats['weighted_project_score'] ?? 0), 2);
    $is_verified = $project_score >= PROJECT_VERIFICATION_SCORE_THRESHOLD ? 1 : 0;

    $sql = "
        UPDATE projects
        SET total_reviews = ?,
            avg_rating = ?,
            project_score = ?,
            is_verified = ?,
            updated_at = NOW(),
            verified_at = " . ($is_verified === 1 ? "COALESCE(verified_at, NOW())" : "NULL") . "
        WHERE id = ?
    ";
    $update = $db->prepare($sql);
    $update->execute([$total_reviews, $avg_rating, $project_score, $is_verified, $project_id]);

    $has_feature_status = tableHasColumn('projects', 'feature_status');
    $has_feature_requested_at = tableHasColumn('projects', 'feature_requested_at');
    $has_feature_reviewed_at = tableHasColumn('projects', 'feature_reviewed_at');
    $has_feature_reviewed_by = tableHasColumn('projects', 'feature_reviewed_by');
    $has_featured_at = tableHasColumn('projects', 'featured_at');

    if ($has_feature_status) {
        $project_stmt = $db->prepare("
            SELECT approval_status, feature_status
            FROM projects
            WHERE id = ?
            LIMIT 1
        ");
        $project_stmt->execute([$project_id]);
        $project_row = $project_stmt->fetch() ?: [];

        $approval_status = strtolower(trim((string) ($project_row['approval_status'] ?? 'pending')));
        $feature_status = strtolower(trim((string) ($project_row['feature_status'] ?? 'none')));
        $meets_feature_criteria = $approval_status === 'approved'
            && $avg_rating >= FEATURE_MIN_AVG_RATING
            && $total_reviews >= FEATURE_MIN_APPROVED_REVIEWS;

        if ($meets_feature_criteria && $feature_status === 'none') {
            $feature_sql = "
                UPDATE projects
                SET feature_status = 'pending_review',
                    " . ($has_feature_requested_at ? "feature_requested_at = COALESCE(feature_requested_at, NOW())," : '') . "
                    updated_at = NOW()
                WHERE id = ?
            ";
            $db->prepare($feature_sql)->execute([$project_id]);
        } elseif (!$meets_feature_criteria && $feature_status === 'pending_review') {
            $reset_parts = ["feature_status = 'none'"];
            if ($has_feature_requested_at) {
                $reset_parts[] = "feature_requested_at = NULL";
            }
            if ($has_feature_reviewed_at) {
                $reset_parts[] = "feature_reviewed_at = NULL";
            }
            if ($has_feature_reviewed_by) {
                $reset_parts[] = "feature_reviewed_by = NULL";
            }
            if ($has_featured_at) {
                $reset_parts[] = "featured_at = NULL";
            }
            $reset_parts[] = "updated_at = NOW()";

            $feature_reset_sql = "
                UPDATE projects
                SET " . implode(",\n                    ", $reset_parts) . "
                WHERE id = ?
            ";
            $db->prepare($feature_reset_sql)->execute([$project_id]);
        }
    }

    return [
        'total_reviews' => $total_reviews,
        'avg_rating' => $avg_rating,
        'project_score' => $project_score,
        'is_verified' => $is_verified,
    ];
}

function userCanAccessExpertTools($user_or_level_state) {
    $level_state = is_array($user_or_level_state) ? $user_or_level_state : getUserLevelState($user_or_level_state);
    return normalizeUserLevel($level_state['level'] ?? 'beginner') === 'expert';
}

function toggleReviewLike($review_id, $user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    $review_id = (int) $review_id;
    $user_id = (int) $user_id;

    if (!userCanAccessExpertTools($user_id)) {
        return ['success' => false, 'message' => 'Only experts can like reviews.'];
    }

    $lookup = $db->prepare("
        SELECT id
        FROM review_reactions
        WHERE review_id = ?
          AND user_id = ?
          AND reaction_type = 'like'
        LIMIT 1
    ");
    $lookup->execute([$review_id, $user_id]);
    $existing = $lookup->fetch();

    if ($existing) {
        $delete = $db->prepare("DELETE FROM review_reactions WHERE id = ?");
        $delete->execute([(int) $existing['id']]);

        $decrement = $db->prepare("UPDATE reviews SET helpful_count = GREATEST(helpful_count - 1, 0), updated_at = NOW() WHERE id = ?");
        $decrement->execute([$review_id]);

        return ['success' => true, 'liked' => false, 'message' => 'Review like removed.'];
    }

    $insert = $db->prepare("
        INSERT INTO review_reactions (review_id, user_id, reaction_type)
        VALUES (?, ?, 'like')
    ");
    $insert->execute([$review_id, $user_id]);

    $increment = $db->prepare("UPDATE reviews SET helpful_count = helpful_count + 1, updated_at = NOW() WHERE id = ?");
    $increment->execute([$review_id]);

    return ['success' => true, 'liked' => true, 'message' => 'Review liked successfully.'];
}

function submitContentFlag($user_id, $target_type, $target_id, $reason = '', PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureLevelEngineSchema($db);

    $user_id = (int) $user_id;
    $target_id = (int) $target_id;
    $target_type = strtolower(trim((string) $target_type));

    if (!userCanAccessExpertTools($user_id)) {
        return ['success' => false, 'message' => 'Only experts can flag content.'];
    }

    if (!in_array($target_type, ['review', 'project'], true) || $target_id <= 0) {
        return ['success' => false, 'message' => 'Invalid flag target.'];
    }

    $reason = trim((string) $reason);
    if ($reason === '') {
        $reason = 'Flagged by expert moderator for manual review.';
    }

    $stmt = $db->prepare("
        INSERT INTO content_flags (user_id, target_type, target_id, reason, status)
        VALUES (?, ?, ?, ?, 'open')
        ON DUPLICATE KEY UPDATE
            reason = VALUES(reason),
            status = 'open',
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$user_id, $target_type, $target_id, substr($reason, 0, 255)]);

    return ['success' => true, 'message' => ucfirst($target_type) . ' flagged for admin review.'];
}

function getUserLevelProgressData($user_or_id, PDO $db = null) {
    $level_state = is_array($user_or_id) && isset($user_or_id['stats'])
        ? $user_or_id
        : getUserLevelState($user_or_id, $db);

    $stats = $level_state['stats'];
    $current_level = normalizeUserLevel($level_state['recommended_level'] ?? $level_state['level'] ?? 'beginner');
    $next_level = null;

    if ($current_level === 'beginner') {
        $next_level = 'pro';
    } elseif ($current_level === 'pro') {
        $next_level = 'expert';
    }

    $requirements = [
        'approved_reviews_needed' => 0,
        'valid_referrals_needed' => 0,
        'accuracy_needed' => 0,
        'completed_tasks_needed' => 0,
        'account_age_days_needed' => 0,
    ];
    $progress = 100;

    if ($next_level !== null) {
        $policy = getLevelPolicy($next_level);
        if ($next_level === 'pro') {
            $task_ratio = $policy['promotion_completed_tasks'] > 0
                ? min(1, (int) ($stats['completed_tasks'] ?? 0) / (int) $policy['promotion_completed_tasks'])
                : 1;
            $referral_ratio = $policy['promotion_valid_referrals'] > 0
                ? min(1, (int) ($stats['valid_referrals'] ?? 0) / (int) $policy['promotion_valid_referrals'])
                : 1;
            $age_ratio = $policy['promotion_account_age_days'] > 0
                ? min(1, (int) ($stats['account_age_days'] ?? 0) / (int) $policy['promotion_account_age_days'])
                : 1;
            $progress = round((($task_ratio + $referral_ratio + $age_ratio) / 3) * 100, 1);
        } else {
            $review_ratio = $policy['promotion_approved_reviews'] > 0
                ? min(1, (int) ($stats['approved_reviews'] ?? 0) / (int) $policy['promotion_approved_reviews'])
                : 1;
            $referral_ratio = $policy['promotion_valid_referrals'] > 0
                ? min(1, (int) ($stats['valid_referrals'] ?? 0) / (int) $policy['promotion_valid_referrals'])
                : 1;
            $accuracy_ratio = $policy['promotion_accuracy'] > 0
                ? min(1, (float) ($stats['accuracy'] ?? 0) / (float) $policy['promotion_accuracy'])
                : 1;
            $progress = round((($review_ratio + $referral_ratio + $accuracy_ratio) / 3) * 100, 1);
        }

        $requirements = [
            'approved_reviews_needed' => max(0, (int) $policy['promotion_approved_reviews'] - (int) ($stats['approved_reviews'] ?? 0)),
            'valid_referrals_needed' => max(0, (int) $policy['promotion_valid_referrals'] - (int) ($stats['valid_referrals'] ?? 0)),
            'accuracy_needed' => max(0, round((float) $policy['promotion_accuracy'] - (float) ($stats['accuracy'] ?? 0), 2)),
            'completed_tasks_needed' => max(0, (int) $policy['promotion_completed_tasks'] - (int) ($stats['completed_tasks'] ?? 0)),
            'account_age_days_needed' => max(0, (int) $policy['promotion_account_age_days'] - (int) ($stats['account_age_days'] ?? 0)),
        ];
    }

    return [
        'current_level' => $current_level,
        'next_level' => $next_level,
        'progress' => $progress,
        'requirements' => $requirements,
    ];
}

// Process login
function loginUser($email, $password, $remember = false) {
    $db = getDBConnection();
    $email = normalizeEmail($email);
    
    $user = getUserByEmail($email);
    
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    if ($user['status'] !== 'active') {
        return ['success' => false, 'message' => 'Your account is ' . $user['status']];
    }
    
    if (!verifyPassword($password, $user['password'])) {
        // Update login attempts
        $update = "UPDATE users SET login_attempts = login_attempts + 1 WHERE id = ?";
        $stmt = $db->prepare($update);
        $stmt->execute([$user['id']]);
        
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    if ((int)($user['email_verified'] ?? 0) !== 1) {
        $verification_mail = startPendingEmailVerification($user);
        $verification_message = $verification_mail['success']
            ? 'Please verify your email. We sent a 6-digit OTP to ' . $user['email']
            : 'Email verification is required, but the OTP email could not be sent yet. ' . $verification_mail['message'];

        return [
            'success' => false,
            'requires_verification' => true,
            'message' => $verification_message,
            'redirect_url' => BASE_URL . '/auth/verify_email.php',
            'email' => $user['email'],
            'otp_sent' => $verification_mail['success'],
        ];
    }
    
    establishAuthenticatedSession($user, $remember);
    
    return [
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'level' => $user['level'],
            'rex_balance' => $user['rex_balance']
        ]
    ];
}

// Check if user is logged in
function isLoggedIn() {
    if (isset($_SESSION['user_id'])) {
        $user_id = (int) $_SESSION['user_id'];
        if ($user_id <= 0) {
            return false;
        }

        $db = getDBConnection();
        $stmt = $db->prepare("
            SELECT id, status, email_verified, last_active
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || ($user['status'] ?? '') !== 'active' || (int) ($user['email_verified'] ?? 0) !== 1) {
            logoutUser();
            return false;
        }

        $last_active_timestamp = !empty($user['last_active']) ? strtotime((string) $user['last_active']) : false;
        if ($last_active_timestamp !== false && (time() - $last_active_timestamp) > REMEMBER_ME_LIFETIME_SECONDS) {
            logoutUser();
            return false;
        }

        touchAuthenticatedUserActivity($user_id, $db);
        return true;
    }

    return restoreRememberedSession();
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }

    syncUserLevelStatus((int) $_SESSION['user_id']);

    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Logout
function logoutUser() {
    $db = getDBConnection();

    if (isset($_SESSION['user_id'])) {
        clearRememberMeTokenForUser((int) $_SESSION['user_id'], $db);
    } else {
        clearRememberMeTokenByCookie($db);
    }

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    return true;
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

?>
