<?php
/** Auto-split from legacy functions.php */

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

function getUserById($user_id) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getUserByUsername($username) {
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    return $stmt->fetch();
}

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

function getUserByReferralCode($code) {
    $db = getDBConnection();
    $code = normalizeReferralCode($code);
    $stmt = $db->prepare("SELECT * FROM users WHERE referral_code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch();
}

function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

function getSecuritySignalSalt() {
    return hash('sha256', DB_NAME . '|' . (defined('SITE_NAME') ? SITE_NAME : 'coinrex'));
}

function normalizeClientIpAddress($ip) {
    $ip = trim((string) $ip);
    if ($ip === '') {
        return '';
    }

    if (strpos($ip, ',') !== false) {
        $parts = explode(',', $ip);
        $ip = trim((string) ($parts[0] ?? ''));
    }

    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function resolveClientIpAddress() {
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $ip = normalizeClientIpAddress($candidate);
        if ($ip !== '') {
            return $ip;
        }
    }

    return '';
}

function hashSecuritySignal($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    return hash('sha256', getSecuritySignalSalt() . '|' . $value);
}

function logFraudEvent($event_type, $severity = 'warning', array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    if (!tableExists('fraud_events')) {
        return;
    }

    $stmt = $db->prepare("INSERT INTO fraud_events (event_type, severity, user_id, email, ip_hash, fingerprint_hash, details_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        substr(trim((string) $event_type), 0, 80),
        in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'warning',
        isset($payload['user_id']) ? (int) $payload['user_id'] : null,
        isset($payload['email']) ? normalizeEmail((string) $payload['email']) : null,
        $payload['ip_hash'] ?? null,
        $payload['fingerprint_hash'] ?? null,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
}

function logUserSecuritySignal($user_id, $signal_type, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    if (!tableExists('user_security_signals')) {
        return;
    }

    $ip = normalizeClientIpAddress((string) ($payload['raw_ip'] ?? resolveClientIpAddress()));
    $fingerprint = trim((string) ($payload['fingerprint'] ?? ''));
    $user_agent = trim((string) ($payload['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));

    $stmt = $db->prepare("INSERT INTO user_security_signals (user_id, signal_type, ip_hash, raw_ip, fingerprint_hash, user_agent_hash, meta_json) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        (int) $user_id,
        in_array($signal_type, ['signup', 'login', 'taskhub', 'reward'], true) ? $signal_type : 'signup',
        $ip !== '' ? hashSecuritySignal($ip) : null,
        $ip !== '' ? $ip : null,
        $fingerprint !== '' ? hashSecuritySignal($fingerprint) : null,
        $user_agent !== '' ? hashSecuritySignal($user_agent) : null,
        json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
}

function evaluateRegistrationSecurityRisk($email, $device_fingerprint = null, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $email = normalizeEmail($email);
    $raw_ip = resolveClientIpAddress();
    $ip_hash = $raw_ip !== '' ? hashSecuritySignal($raw_ip) : '';
    $fingerprint = trim((string) $device_fingerprint);
    $fingerprint_hash = $fingerprint !== '' ? hashSecuritySignal($fingerprint) : '';

    $ip_match_count = 0;
    if ($ip_hash !== '') {
        if (tableExists('user_security_signals')) {
            $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) AS total FROM user_security_signals WHERE ip_hash = ?");
            $stmt->execute([$ip_hash]);
            $ip_match_count = (int) ($stmt->fetch()['total'] ?? 0);
        } else {
            $stmt = $db->prepare("SELECT COUNT(*) AS total FROM users WHERE signup_ip = ? OR last_ip = ?");
            $stmt->execute([$raw_ip, $raw_ip]);
            $ip_match_count = (int) ($stmt->fetch()['total'] ?? 0);
        }
    }

    $fingerprint_match_count = 0;
    if ($fingerprint_hash !== '' && tableExists('user_security_signals')) {
        $stmt = $db->prepare("SELECT COUNT(DISTINCT user_id) AS total FROM user_security_signals WHERE fingerprint_hash = ?");
        $stmt->execute([$fingerprint_hash]);
        $fingerprint_match_count = (int) ($stmt->fetch()['total'] ?? 0);
    }

    $ip_blocked = $ip_match_count >= ANTI_FARM_MAX_ACCOUNTS_PER_IP;
    $fingerprint_blocked = $fingerprint_hash !== '' && $fingerprint_match_count > 0;
    $combined_pattern = $ip_match_count > 0 && $fingerprint_match_count > 0;
    $messages = [];
    if ($ip_blocked) {
        $messages[] = 'Registration limit reached for this IP address.';
    }
    if ($fingerprint_blocked) {
        $messages[] = 'This device has already been used to register an account.';
    }

    return [
        'raw_ip' => $raw_ip,
        'ip_hash' => $ip_hash,
        'fingerprint_hash' => $fingerprint_hash,
        'ip_match_count' => $ip_match_count,
        'fingerprint_match_count' => $fingerprint_match_count,
        'ip_blocked' => $ip_blocked,
        'fingerprint_blocked' => $fingerprint_blocked,
        'blocked' => $ip_blocked || $fingerprint_blocked,
        'combined_pattern' => $combined_pattern,
        'message' => !empty($messages) ? implode(' ', $messages) : ($combined_pattern ? 'Security pattern detected. Account flagged for admin review.' : ''),
    ];
}

function applySecurityActionToUser($user_id, $action, array $options = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        throw new InvalidArgumentException('Invalid user id.');
    }

    $action = strtolower(trim((string) $action));
    if ($action === 'warning') {
        $stmt = $db->prepare("UPDATE users SET security_warning_count = security_warning_count + 1, security_flagged = 1, security_flag_reason = ?, updated_at = NOW() WHERE id = ?");
        $reason = substr((string) ($options['reason'] ?? 'Security warning issued by admin.'), 0, 255);
        $stmt->execute([$reason, $user_id]);
        createTemplatedNotification('security.warning', 'user', $user_id, ['reason' => $reason], ['event_key' => 'security.warning'], $db);
        return;
    }

    if ($action === 'suspend') {
        $stmt = $db->prepare("UPDATE users SET security_suspended = 1, status = 'suspended', security_flagged = 1, security_flag_reason = ?, updated_at = NOW() WHERE id = ?");
        $reason = substr((string) ($options['reason'] ?? 'Suspended by security management.'), 0, 255);
        $stmt->execute([$reason, $user_id]);
        createTemplatedNotification('security.suspended', 'user', $user_id, ['reason' => $reason], ['event_key' => 'security.suspended'], $db);
        return;
    }

    if (in_array($action, ['block_taskhub', 'block_boosthub', 'block_review'], true)) {
        $hours = max(1, (int) ($options['hours'] ?? 24));
        $column = $action === 'block_taskhub' ? 'taskhub_blocked_until' : ($action === 'block_boosthub' ? 'boosthub_blocked_until' : 'review_blocked_until');
        $sql = "UPDATE users SET {$column} = DATE_ADD(NOW(), INTERVAL ? HOUR), security_flagged = 1, security_flag_reason = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$hours, substr((string) ($options['reason'] ?? 'Temporary module block.'), 0, 255), $user_id]);
        return;
    }

    if ($action === 'clear_flags') {
        $stmt = $db->prepare("UPDATE users SET security_flagged = 0, security_flag_reason = NULL, security_suspended = 0, taskhub_blocked_until = NULL, boosthub_blocked_until = NULL, review_blocked_until = NULL, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$user_id]);
        return;
    }
}

function enforceUserModuleAccess($user, $module) {
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    if (!empty($user['reward_frozen'])) {
        throw new RuntimeException('Rewards are temporarily frozen for this account.');
    }

    if (!empty($user['security_suspended']) || strtolower((string) ($user['status'] ?? 'active')) === 'suspended') {
        throw new RuntimeException('Your account is suspended by security management.');
    }

    $module = strtolower(trim((string) $module));
    $map = [
        'taskhub' => 'taskhub_blocked_until',
        'boosthub' => 'boosthub_blocked_until',
        'review' => 'review_blocked_until',
    ];
    $column = $map[$module] ?? null;
    if ($column && !empty($user[$column]) && strtotime((string) $user[$column]) > time()) {
        throw new RuntimeException('Access to ' . ucfirst($module) . ' is temporarily blocked until ' . (string) $user[$column] . '.');
    }
}

function registerUser($full_name, $email, $password, $referral_code = null) {
    $db = getDBConnection();
    $full_name = sanitize($full_name);
    $email = normalizeEmail($email);
    $password = (string) $password;
    $referral_code = normalizeReferralCode($referral_code);
    ensureRewardClaimSchema($db);
    ensureEarlyAirdropSchema($db);

    if (empty($full_name) || empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'Please fill in all required fields'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address'];
    }
    
    if (isDisposableEmail($email)) {
        return ['success' => false, 'message' => 'Temporary email addresses are not allowed'];
    }

    // Check if email exists
    if (getUserByEmail($email)) {
        return ['success' => false, 'message' => 'Email already registered'];
    }

    $security_risk = evaluateRegistrationSecurityRisk($email, (string) ($_POST['device_fingerprint'] ?? ''), $db);
    if (!empty($security_risk['blocked'])) {
        logFraudEvent('registration_blocked_security_policy', 'warning', [
            'email' => $email,
            'ip_hash' => $security_risk['ip_hash'] ?? null,
            'fingerprint_hash' => $security_risk['fingerprint_hash'] ?? null,
            'ip_match_count' => $security_risk['ip_match_count'] ?? 0,
            'fingerprint_match_count' => $security_risk['fingerprint_match_count'] ?? 0,
            'ip_blocked' => !empty($security_risk['ip_blocked']),
            'fingerprint_blocked' => !empty($security_risk['fingerprint_blocked']),
        ], $db);
        return [
            'success' => false,
            'message' => (string) ($security_risk['message'] ?? 'Registration is temporarily unavailable from this device.'),
        ];
    }
    if (!empty($security_risk['combined_pattern'])) {
        logFraudEvent('registration_pattern_flagged', 'warning', [
            'email' => $email,
            'ip_hash' => $security_risk['ip_hash'] ?? null,
            'fingerprint_hash' => $security_risk['fingerprint_hash'] ?? null,
            'ip_match_count' => $security_risk['ip_match_count'] ?? 0,
            'fingerprint_match_count' => $security_risk['fingerprint_match_count'] ?? 0,
            'reason' => 'same ip + same fingerprint + repeat behavior pattern',
        ], $db);
    }

    $password_validation = validatePasswordPolicy($password);
    if (!$password_validation['is_valid']) {
        return ['success' => false, 'message' => 'Password does not meet security requirements'];
    }
    
    // Generate username
    $username = generateUsername($full_name, $email);
    
    // Hash password
    $hashed_password = hashPassword($password);
    
    // Get IP address
    $signup_ip = resolveClientIpAddress();
    if ($signup_ip === '') {
        $signup_ip = null;
    }
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $early_airdrop_active = isEarlyAirdropActive($db);

    // Check referral
    $referred_by = null;
    $referral_bonus = 0;
    
    if ($referral_code) {
        $referral_validation = validateReferralCode($referral_code);
        if (!$referral_validation['valid']) {
            return ['success' => false, 'message' => $referral_validation['message']];
        }

        $referred_by = $referral_validation['referrer']['id'];
        $referral_bonus = $early_airdrop_active ? EARLY_AIRDROP_REFERRAL_BONUS : REFERRAL_BONUS_REX;
    }
    
    $total_bonus = 0;

    try {
        $db->beginTransaction();

        $user_referral_code = generateUniqueReferralCode($db);

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

        if (!empty($security_risk['combined_pattern'])) {
            $flag_stmt = $db->prepare("UPDATE users SET security_flagged = 1, security_flag_reason = ? WHERE id = ?");
            $flag_stmt->execute(['System flagged: same IP + same fingerprint pattern', $user_id]);
        }

        logUserSecuritySignal($user_id, 'signup', [
            'raw_ip' => $signup_ip,
            'fingerprint' => trim((string) ($_POST['device_fingerprint'] ?? '')),
            'user_agent' => $user_agent,
            'channel' => 'auth_register',
        ], $db);

        if ($referred_by) {
            $update = "UPDATE users SET total_referrals = total_referrals + 1 WHERE id = ?";
            $stmt = $db->prepare($update);
            $stmt->execute([$referred_by]);
        }

        // Early Adopter Airdrop: reserve 1,000 REX for 30 days, pending Pro unlock.
        // If airdrop is inactive, fall back to standard welcome bonus
        $signup_reference_id = 'early_airdrop:signup:' . $user_id;
        if ($early_airdrop_active && deductEarlyAirdropPool($user_id, 'signup_bonus', EARLY_AIRDROP_SIGNUP_BONUS, $db, $signup_reference_id)) {
            $expires_at = date('Y-m-d H:i:s', time() + ((int) EARLY_AIRDROP_UNLOCK_DAYS * 86400));
            addRewardLedgerEntry($user_id, EARLY_AIRDROP_SIGNUP_BONUS, 'bonus', 'early_adopter_airdrop', 'pending', $signup_reference_id, $db, 'phase1', 'beginner', $expires_at);
            $total_bonus = EARLY_AIRDROP_SIGNUP_BONUS + $referral_bonus;
        } else {
            addRewardLedgerEntry($user_id, WELCOME_BONUS_REX, 'bonus', 'welcome_bonus', 'available', 'welcome_bonus:' . $user_id, $db, 'phase1', 'beginner');
            if ($early_airdrop_active && $referral_bonus === EARLY_AIRDROP_REFERRAL_BONUS) {
                $referral_bonus = REFERRAL_BONUS_REX;
            }
            $total_bonus = WELCOME_BONUS_REX + $referral_bonus;
        }

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
        error_log('Registration failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Registration could not be completed right now. Please try again.'];
    }
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
    // TESTING_MODE: Bypass all security checks for local development
    if (defined('TESTING_MODE') && TESTING_MODE) {
        return [
            'is_suspicious' => false,
            'reasons' => [],
            'matching_accounts' => 0,
            'matching_user_agents' => 0,
            'signup_ip' => '',
            'last_ip' => '',
            'user_agent' => '',
        ];
    }

    $db = $db ?: getDBConnection();
    $user = getUserById((int) $user_id);

    if (!$user) {
        return [
            'is_suspicious' => true,
            'reasons' => ['User record not found.'],
            'matching_accounts' => 0,
            'matching_user_agents' => 0,
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

    // Count accounts sharing the same user_agent (device fingerprint)
    $matching_user_agents = 0;
    if ($user_agent !== '') {
        $ua_stmt = $db->prepare("
            SELECT COUNT(*) AS total
            FROM users
            WHERE id <> ?
              AND user_agent = ?
        ");
        $ua_stmt->execute([(int) $user_id, $user_agent]);
        $matching_user_agents = (int) ($ua_stmt->fetch()['total'] ?? 0);
    }

    $reasons = [];
    if ($matching_accounts >= ANTI_FARM_MAX_ACCOUNTS_PER_IP) {
        $reasons[] = 'Multiple accounts (' . ($matching_accounts + 1) . ') detected from the same IP range. Maximum ' . ANTI_FARM_MAX_ACCOUNTS_PER_IP . ' accounts allowed per IP.';
    }
    if ($matching_user_agents >= ANTI_FARM_MAX_ACCOUNTS_PER_IP) {
        $reasons[] = 'Multiple accounts (' . ($matching_user_agents + 1) . ') detected from the same device. Maximum ' . ANTI_FARM_MAX_ACCOUNTS_PER_IP . ' accounts allowed per device.';
    }
    if ((int) ($user['login_attempts'] ?? 0) >= ANTI_FARM_MAX_LOGIN_ATTEMPTS) {
        $reasons[] = 'Excessive login attempts detected.';
    }

    return [
        'is_suspicious' => !empty($reasons),
        'reasons' => $reasons,
        'matching_accounts' => $matching_accounts,
        'matching_user_agents' => $matching_user_agents,
        'signup_ip' => $signup_ip,
        'last_ip' => $last_ip,
        'user_agent' => $user_agent,
    ];
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

    return '/uploads/avatar/' . $safe_file_name;
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

    // Ensure mission completion, account age, and referral stats are present for level promotion checks
    if (empty($stats['mission_completed'])) {
        $stats['mission_completed'] = taskHubMissionCompleted($user_id, $db);
    }
    if (empty($stats['account_age_days'])) {
        $current_user = is_array($user_or_id) ? $user_or_id : getUserById($user_id);
        $stats['account_age_days'] = (int) floor((time() - strtotime((string) ($current_user['created_at'] ?? 'now'))) / 86400);
    }
    if (empty($stats['valid_referrals'])) {
        $current_user = is_array($user_or_id) ? $user_or_id : getUserById($user_id);
        $stats['valid_referrals'] = (int) ($current_user['valid_referrals'] ?? 0);
    }
    $stats['user_id'] = $user_id;
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

    // Get promotion blockers if user is stuck at current level
    $promotion_blockers = [];
    if ($current_level === 'beginner' && $recommended_level === 'beginner') {
        $promotion_blockers = getLevelPromotionBlockers('pro', $stats);
    } elseif ($current_level === 'pro' && $recommended_level === 'pro') {
        $promotion_blockers = getLevelPromotionBlockers('expert', $stats);
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
        'promotion_blockers' => $promotion_blockers,
    ];
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
            $mission_ratio = !empty($stats['mission_completed']) ? 1 : 0;
            $progress = round($mission_ratio * 100, 1);
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
            'completed_tasks_needed' => $next_level === 'pro' && !empty($stats['mission_completed'])
                ? 0
                : max(0, (int) $policy['promotion_completed_tasks'] - (int) ($stats['completed_tasks'] ?? 0)),
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
