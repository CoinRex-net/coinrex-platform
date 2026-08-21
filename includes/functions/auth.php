<?php
/** Auto-split from legacy functions.php */

function validateReviewerRegistrationSubmission($full_name, $email, $password, $confirm_password, $referral_code = null, $terms_accepted = false) {
    $full_name = sanitize($full_name);
    $email = normalizeEmail($email);
    $password = (string) $password;
    $confirm_password = (string) $confirm_password;
    $referral_code = normalizeReferralCode($referral_code);
    $password_validation = validatePasswordPolicy($password);
    $referral_validation = validateReferralCode($referral_code);

    if (empty($full_name) || empty($email) || empty($password)) {
        return ['valid' => false, 'message' => 'Please fill in all required fields'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Invalid email address'];
    }

    if (isDisposableEmail($email)) {
        return ['valid' => false, 'message' => 'Temporary email addresses are not allowed'];
    }

    if (getUserByEmail($email)) {
        return ['valid' => false, 'message' => 'Email already registered'];
    }

    if (!$password_validation['is_valid']) {
        return ['valid' => false, 'message' => 'Password must be at least 9 characters and include an uppercase letter, a number, and a special character'];
    }

    if ($password !== $confirm_password) {
        return ['valid' => false, 'message' => 'Passwords do not match'];
    }

    if (!$referral_validation['valid']) {
        return ['valid' => false, 'message' => $referral_validation['message']];
    }

    if (!$terms_accepted) {
        return ['valid' => false, 'message' => 'Please accept the Terms of Service'];
    }

    return [
        'valid' => true,
        'message' => '',
        'full_name' => $full_name,
        'email' => $email,
        'referral_code' => $referral_code !== '' ? $referral_code : null,
    ];
}

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
    if (function_exists('recordAuthenticatedUserMetrics')) {
        recordAuthenticatedUserMetrics($user_id, 'web', $db);
    }
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

    if (!$user || !userAuthIdentityVerified($user)) {
        clearRememberMeTokenByCookie($db);
        return false;
    }

    establishAuthenticatedSession($user, false);
    issueRememberMeToken((int) $user['id'], $db);
    return true;
}

function userAuthIdentityVerified(array $user) {
    if (($user['status'] ?? '') !== 'active') {
        return false;
    }

    if (!empty($user['security_suspended'])) {
        return false;
    }

    $provider = strtolower(trim((string) ($user['auth_provider'] ?? 'email')));
    if ($provider === 'rex_signer') {
        return !empty($user['wallet_address']) && !empty($user['wallet_verified_at']);
    }

    return (int) ($user['email_verified'] ?? 0) === 1;
}

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

    if ((int) ($user['login_attempts'] ?? 0) >= ANTI_FARM_MAX_LOGIN_ATTEMPTS) {
        return ['success' => false, 'message' => 'Too many failed login attempts. Please reset your password or contact support.'];
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

function isLoggedIn() {
    if (isset($_SESSION['user_id'])) {
        $user_id = (int) $_SESSION['user_id'];
        if ($user_id <= 0) {
            return false;
        }

        $db = getDBConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || !userAuthIdentityVerified($user)) {
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

function revokeRexSignerSessionsForLogout(PDO $db, int $user_id, int $preferred_session_id = 0): void {
    if ($user_id <= 0 || !function_exists('tableExists') || !tableExists('rex_signer_sessions')) {
        return;
    }

    $params = [$user_id];
    $session_filter = '';
    if ($preferred_session_id > 0) {
        $session_filter = ' AND id = ?';
        $params[] = $preferred_session_id;
    }

    $select = $db->prepare("
        SELECT id
        FROM rex_signer_sessions
        WHERE user_id = ?
          AND status = 'active'
          AND expires_at > NOW()
          {$session_filter}
    ");
    $select->execute($params);
    $session_ids = array_map('intval', array_column($select->fetchAll(), 'id'));

    if (empty($session_ids) && $preferred_session_id > 0) {
        $select = $db->prepare("
            SELECT id
            FROM rex_signer_sessions
            WHERE user_id = ?
              AND status = 'active'
              AND expires_at > NOW()
        ");
        $select->execute([$user_id]);
        $session_ids = array_map('intval', array_column($select->fetchAll(), 'id'));
    }

    if (empty($session_ids)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($session_ids), '?'));
    $update = $db->prepare("
        UPDATE rex_signer_sessions
        SET status = 'revoked',
            revoked_at = NOW(),
            revoke_reason = 'CoinRex logout'
        WHERE user_id = ?
          AND id IN ({$placeholders})
          AND status = 'active'
    ");
    $update->execute(array_merge([$user_id], $session_ids));
    $revoked_count = (int) $update->rowCount();

    $cancelled_count = 0;
    if (function_exists('tableExists') && tableExists('rex_signer_approval_requests')) {
        $cancel = $db->prepare("
            UPDATE rex_signer_approval_requests
            SET status = 'cancelled',
                decided_at = COALESCE(decided_at, NOW()),
                decision_note = 'RexLink session ended by CoinRex logout.'
            WHERE user_id = ?
              AND session_id IN ({$placeholders})
              AND status = 'pending'
        ");
        $cancel->execute(array_merge([$user_id], $session_ids));
        $cancelled_count = (int) $cancel->rowCount();
    }

    if ($revoked_count > 0 && function_exists('coinrexRealtimePublish')) {
        foreach ($session_ids as $session_id) {
            coinrexRealtimePublish('session.revoked', [
                'user_id' => $user_id,
                'session_id' => (int) $session_id,
                'status' => 'revoked',
                'reason' => 'CoinRex logout',
            ]);
        }

        if ($cancelled_count > 0) {
            coinrexRealtimePublish('approval.cancelled', [
                'user_id' => $user_id,
                'status' => 'cancelled',
                'count' => $cancelled_count,
                'reason' => 'RexLink session ended by CoinRex logout.',
            ]);
        }
    }
}

function logoutUser() {
    $db = getDBConnection();

    if (isset($_SESSION['user_id'])) {
        $user_id = (int) $_SESSION['user_id'];
        clearRememberMeTokenForUser($user_id, $db);
        revokeRexSignerSessionsForLogout($db, $user_id, (int) ($_SESSION['rex_signer_login_session_id'] ?? 0));
    } else {
        clearRememberMeTokenByCookie($db);
    }

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    return true;
}
