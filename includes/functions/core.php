<?php
/** Auto-split from legacy functions.php */

/**
 * CoinRex Helper Functions
 * Location: /coinrex/includes/functions.php
 */

// Sanitize input

// Normalize email input for consistent lookups/storage

// Normalize referral codes for consistent lookups

// Extract a normalized domain from an email address

// Common disposable email domains blocked during registration

// Block registrations that use disposable email providers

// Validate password strength against registration policy

// Generate unique referral code

// Generate username from email or full name

// Hash password

// Verify password

// Get user by email

// Get user by id

// Get user by username

// Resolve a user by either email address or username

// Get user by referral code

// Check whether a database table contains a specific column

// Validate optional referral code input

// Lightweight session flash messaging


// Create a six-digit OTP for email verification

// Store a new verification OTP on the user record

// Check whether SMTP credentials are ready for live OTP delivery

// Send an email through PHPMailer + SMTP

// Send the login OTP email for account verification

// Send the password reset OTP email

// Keep pending email-verification state in session until the verify page is built


if (!defined('REMEMBER_ME_COOKIE_NAME')) {
    define('REMEMBER_ME_COOKIE_NAME', 'coinrex_remember');
}

if (!defined('REMEMBER_ME_LIFETIME_SECONDS')) {
    define('REMEMBER_ME_LIFETIME_SECONDS', 10 * 24 * 60 * 60);
}

// Start password reset OTP delivery for the selected user







// Resolve the currently pending email-verification user from session

// Check whether the resend cooldown has elapsed

// Validate the submitted OTP against the database-backed fields

// Finalize email verification on the user record

// Update login metadata and establish the application session

// Get current user ID

// Check if user is verified developer

// Get DevHub database connection

// Process new user registration

if (!defined('APP_CSRF_SESSION_KEY')) {
    define('APP_CSRF_SESSION_KEY', '_app_csrf_token');
}


































































































// Process login

// Check if user is logged in

// Get current user data

// Logout

// Redirect function

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

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

function getDevHubDB() {
    return getDBConnection();
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

    enforceUserModuleAccess($user, 'taskhub');

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
        enforceUserModuleAccess($user, 'boosthub');
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

    // TESTING_MODE: Skip security signals, daily limit, cooldown, and anti-farming checks
    if (!defined('TESTING_MODE') || !TESTING_MODE) {
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

function getApprovalLaneLabel($user_or_level_state) {
    $level_state = is_array($user_or_level_state) ? $user_or_level_state : getUserLevelState($user_or_level_state);
    return (string) ($level_state['approval_label'] ?? '24-48 hours');
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
