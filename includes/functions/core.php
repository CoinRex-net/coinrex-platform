<?php
/**
 * CoinRex Core Helper Functions
 *
 * Core utility functions used across the platform.
 * This file was auto-split from the legacy functions.php.
 *
 * @package CoinRex
 * @subpackage Helpers
 */

if (!defined('REMEMBER_ME_COOKIE_NAME')) {
    define('REMEMBER_ME_COOKIE_NAME', 'coinrex_remember');
}

if (!defined('REMEMBER_ME_LIFETIME_SECONDS')) {
    define('REMEMBER_ME_LIFETIME_SECONDS', 10 * 24 * 60 * 60);
}

if (!defined('APP_CSRF_SESSION_KEY')) {
    define('APP_CSRF_SESSION_KEY', '_app_csrf_token');
}

/**
 * Hash a password using bcrypt with cost factor 12.
 *
 * @param string $password The plain-text password.
 * @return string The bcrypt hash.
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against its bcrypt hash.
 *
 * @param string $password The plain-text password to verify.
 * @param string $hash     The stored bcrypt hash.
 * @return bool True if the password matches the hash.
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Check whether a database table contains a specific column.
 *
 * Results are cached in a static variable for the duration of the request.
 *
 * @param string $table_name  The table name.
 * @param string $column_name The column name.
 * @return bool True if the column exists.
 */
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

/**
 * Check whether a database table exists.
 *
 * Results are cached in a static variable for the duration of the request.
 *
 * @param string $table_name The table name.
 * @return bool True if the table exists.
 */
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

/**
 * Set a flash message in the session.
 *
 * @param string $key     The flash message key.
 * @param string $message The message content.
 */
function setFlashMessage($key, $message) {
    $_SESSION['_flash'][$key] = $message;
}

/**
 * Consume (retrieve and remove) a flash message from the session.
 *
 * @param string $key The flash message key.
 * @return string The message content, or empty string if not found.
 */
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

/**
 * Start a password reset OTP delivery for the given user.
 *
 * @param array $user The user record.
 * @return array Result with 'success' bool and 'message' string.
 */
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

/**
 * Clear the pending password reset session state.
 */
function clearPendingPasswordReset() {
    unset($_SESSION['pending_password_reset_user_id']);
    unset($_SESSION['pending_password_reset_email']);
    unset($_SESSION['pending_password_reset_last_sent_at']);
    unset($_SESSION['pending_password_reset_mail_status']);
    unset($_SESSION['pending_password_reset_verified_at']);
}

/**
 * Get the user record for the pending password reset.
 *
 * @return array|null The user record, or null if none pending.
 */
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

/**
 * Get the remaining cooldown seconds for password reset OTP resend.
 *
 * @return int Remaining seconds.
 */
function getPasswordResetResendRemainingSeconds() {
    $last_sent_at = (int) ($_SESSION['pending_password_reset_last_sent_at'] ?? 0);
    if ($last_sent_at <= 0) {
        return 0;
    }

    $remaining = EMAIL_VERIFICATION_OTP_RESEND_COOLDOWN_SECONDS - (time() - $last_sent_at);
    return max(0, $remaining);
}

/**
 * Reset a user's password and clear OTP state.
 *
 * @param int    $user_id  The user ID.
 * @param string $password The new plain-text password.
 * @return bool True on success.
 */
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

/**
 * Get the user record for the pending email verification.
 *
 * @return array|null The user record, or null if none pending.
 */
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

/**
 * Check if a user is a verified developer.
 *
 * @param int $user_id The user ID.
 * @return bool True if the user is a verified developer.
 */
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

/**
 * Get the DevHub database connection (alias for getDBConnection).
 *
 * @return PDO The database connection.
 */
function getDevHubDB() {
    return getDBConnection();
}

/**
 * Get available mini tasks for a user with availability status.
 *
 * @param int  $user_id The user ID.
 * @param PDO  $db      Optional database connection.
 * @return array List of tasks with availability metadata.
 */
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

/**
 * Log a mini task action (completed or blocked).
 *
 * @param int    $user_id The user ID.
 * @param int    $task_id The task ID.
 * @param string $status  'completed' or 'blocked'.
 * @param PDO    $db      Optional database connection.
 */
function logMiniTaskAction($user_id, $task_id, $status, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO user_task_logs (user_id, task_id, status)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([(int) $user_id, (int) $task_id, $status === 'blocked' ? 'blocked' : 'completed']);
}

/**
 * Complete a mini task and award the reward.
 *
 * @param int   $user_id The user ID.
 * @param int   $task_id The task ID.
 * @param array $payload Optional payload (e.g., proof data for BoostHub).
 * @param PDO   $db      Optional database connection.
 * @return array Result with 'entry' or 'submitted' key.
 * @throws RuntimeException On validation failure.
 */
function completeMiniTask($user_id, $task_id, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = getUserById((int) $user_id);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    enforceUserModuleAccess($user, 'taskhub');

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

    if ((string) ($task['task_group'] ?? 'legacy') === 'mission' && trim((string) ($task['task_key'] ?? '')) !== '') {
        if (!function_exists('submitTaskHubTask')) {
            throw new RuntimeException('TaskHub submission handler is unavailable.');
        }

        return submitTaskHubTask((int) $user_id, (string) $task['task_key'], $payload, $db);
    }

    // BoostHub is available to all account levels
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
            SELECT id, status, metadata
            FROM user_task_logs
            WHERE user_id = ?
              AND task_id = ?
              AND status IN ('pending', 'failed')
            ORDER BY id DESC
            LIMIT 1
        ");
        $pending_assignment_stmt->execute([(int) $user_id, (int) $task_id]);
        $pending_assignment = $pending_assignment_stmt->fetch();
        if (!$pending_assignment) {
            throw new RuntimeException('This task is not assigned to you yet.');
        }
        if ((string) ($pending_assignment['status'] ?? '') === 'failed') {
            $assignment_metadata = !empty($pending_assignment['metadata']) ? (json_decode((string) $pending_assignment['metadata'], true) ?: []) : [];
            if (empty($assignment_metadata['correction_requested'])) {
                throw new RuntimeException('This BoostHub submission was rejected and cannot be resubmitted.');
            }
        }

        $proof = trim((string) ($payload['proof'] ?? ''));

        if ($proof === '') {
            throw new RuntimeException('Evidence is required before submitting this BoostHub task.');
        }

        // ── Anti-abuse: Check for duplicate evidence in same task category ──
        $evidence_data = json_decode($proof, true);
        $evidence_text = '';
        if (is_array($evidence_data) && !empty($evidence_data['text'])) {
            $evidence_text = trim((string) $evidence_data['text']);
        } elseif (!is_array($evidence_data)) {
            $evidence_text = trim((string) $proof);
        }

        if ($evidence_text !== '' && function_exists('boostHubCheckDuplicateEvidence')) {
            $task_category = (string) ($task['task_category'] ?? '');
            if ($task_category !== '' && boostHubCheckDuplicateEvidence((int) $user_id, $task_category, $evidence_text, $db)) {
                throw new RuntimeException('This evidence (link/username) has already been used for a similar task. Please provide different evidence.');
            }
        }

        $boosthub_proof_data = $proof;
    } elseif (normalizeUserLevel($user['level'] ?? 'beginner') !== 'beginner') {
        throw new RuntimeException('MicroTaskHub is available for Beginner accounts only.');
    }

    // TESTING_MODE: Skip security signals, daily limit, cooldown, and anti-farming checks
    // BoostHub tasks also skip these checks since they have their own cooldown/assignment logic
    $is_boosthub = (string) ($task['task_group'] ?? 'legacy') === 'boosthub';
    if ((!defined('TESTING_MODE') || !TESTING_MODE) && !$is_boosthub) {
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
            $was_returned_for_correction = (string) ($pending_assignment['status'] ?? '') === 'failed';
            taskHubUpdateLog((int) ($pending_assignment['id'] ?? 0), [
                'status' => 'submitted',
                'proof_data' => $boosthub_proof_data !== '' ? $boosthub_proof_data : null,
                'metadata' => [
                    'submitted_at' => date('Y-m-d H:i:s'),
                    'review_outcome' => 'pending',
                    'resubmitted_after_correction' => $was_returned_for_correction,
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

/**
 * Calculate the accuracy rate based on approved vs total reviews.
 *
 * @param int $approved_count Number of approved reviews.
 * @param int $total_count    Total number of reviews.
 * @return float Accuracy percentage (0-100).
 */
function calculateAccuracyRate($approved_count, $total_count) {
    $approved_count = max(0, (int) $approved_count);
    $total_count = max(0, (int) $total_count);

    if ($total_count === 0) {
        return 0.0;
    }

    return round(($approved_count / $total_count) * 100, 2);
}

/**
 * Calculate the rejection ratio.
 *
 * @param int $rejected_count Number of rejected reviews.
 * @param int $total_count    Total number of reviews.
 * @return float Rejection ratio (0-1).
 */
function calculateRejectionRatio($rejected_count, $total_count) {
    $rejected_count = max(0, (int) $rejected_count);
    $total_count = max(0, (int) $total_count);

    if ($total_count === 0) {
        return 0.0;
    }

    return round($rejected_count / $total_count, 4);
}

/**
 * Get the approval lane label for a user or level state.
 *
 * @param array $user_or_level_state User record or level state array.
 * @return string The approval lane label.
 */
function getApprovalLaneLabel($user_or_level_state) {
    $level_state = is_array($user_or_level_state) ? $user_or_level_state : getUserLevelState($user_or_level_state);
    return (string) ($level_state['approval_label'] ?? '24-48 hours');
}

/**
 * Submit a content flag (for expert moderators).
 *
 * @param int    $user_id     The flagging user ID.
 * @param string $target_type 'review' or 'project'.
 * @param int    $target_id   The target record ID.
 * @param string $reason      Optional reason for flagging.
 * @param PDO    $db          Optional database connection.
 * @return array Result with 'success' bool and 'message' string.
 */
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
