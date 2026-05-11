<?php
/** Auto-split from legacy functions.php */

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
