<?php
/**
 * RexSigner passwordless website authentication helpers.
 */

require_once dirname(__DIR__) . '/_bootstrap.php';

function rexSignerAuthTableHasIndex(PDO $db, $table, $index) {
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->execute([DB_NAME, (string) $table, (string) $index]);
    return ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
}

function rexSignerAuthNormalizeWallet($wallet_address) {
    $wallet_address = trim((string) $wallet_address);
    if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
        throw new InvalidArgumentException('Valid wallet address is required.');
    }

    return strtolower($wallet_address);
}

function rexSignerAuthEnsureSchema(PDO $db = null) {
    static $ready = false;
    if ($ready) {
        return;
    }

    $db = $db ?: getDBConnection();
    rexSignerEnsureSchema($db);
    ensureRewardClaimSchema($db);
    ensureEarlyAirdropSchema($db);

    if (!tableHasColumn('users', 'auth_provider')) {
        $db->exec("ALTER TABLE users ADD COLUMN auth_provider VARCHAR(30) NOT NULL DEFAULT 'email' AFTER password");
    }

    if (!tableHasColumn('users', 'wallet_verified_at')) {
        $db->exec("ALTER TABLE users ADD COLUMN wallet_verified_at DATETIME NULL AFTER wallet_address");
    }

    $db->exec("UPDATE users SET auth_provider = 'email' WHERE auth_provider IS NULL OR auth_provider = ''");
    $db->exec("UPDATE users SET wallet_address = NULL WHERE wallet_address IS NOT NULL AND TRIM(wallet_address) = ''");
    $db->exec("UPDATE users SET wallet_address = LOWER(wallet_address) WHERE wallet_address IS NOT NULL");

    $email_nullable = $db->prepare("
        SELECT IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'email'
        LIMIT 1
    ");
    $email_nullable->execute([DB_NAME]);
    if (strtoupper((string) ($email_nullable->fetch()['IS_NULLABLE'] ?? 'NO')) !== 'YES') {
        $db->exec("ALTER TABLE users MODIFY email VARCHAR(255) NULL");
    }

    $password_nullable = $db->prepare("
        SELECT IS_NULLABLE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'password'
        LIMIT 1
    ");
    $password_nullable->execute([DB_NAME]);
    if (strtoupper((string) ($password_nullable->fetch()['IS_NULLABLE'] ?? 'NO')) !== 'YES') {
        $db->exec("ALTER TABLE users MODIFY password VARCHAR(255) NULL");
    }

    if (!rexSignerAuthTableHasIndex($db, 'users', 'uq_users_wallet_address')) {
        $duplicate_stmt = $db->query("
            SELECT wallet_address
            FROM users
            WHERE wallet_address IS NOT NULL
            GROUP BY wallet_address
            HAVING COUNT(*) > 1
            LIMIT 1
        ");
        if ($duplicate_stmt && $duplicate_stmt->fetch()) {
            throw new RuntimeException('Duplicate wallet addresses must be resolved before RexSigner auth can be enabled.');
        }
        $db->exec("ALTER TABLE users ADD UNIQUE KEY uq_users_wallet_address (wallet_address)");
    }

    $ready = true;
}

function rexSignerAuthGeneratedName($wallet_address) {
    $wallet_address = rexSignerAuthNormalizeWallet($wallet_address);
    return 'REX User ' . substr($wallet_address, 0, 6) . '...' . substr($wallet_address, -4);
}

function rexSignerAuthGeneratedUsername(PDO $db, $wallet_address) {
    $wallet_address = rexSignerAuthNormalizeWallet($wallet_address);
    $base = 'rex' . substr(preg_replace('/[^a-f0-9]/', '', $wallet_address), 0, 10);
    $username = $base;
    $counter = 1;
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    while (true) {
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            return $username;
        }
        $username = $base . $counter;
        $counter++;
    }
}

function rexSignerAuthUserCanLogin(array $user) {
    if (($user['status'] ?? '') !== 'active') {
        return 'Your account is ' . (string) ($user['status'] ?? 'not active') . '.';
    }
    if (!empty($user['security_suspended'])) {
        return 'Your account is suspended by security management.';
    }

    $provider = strtolower((string) ($user['auth_provider'] ?? 'email'));
    if ($provider === 'rex_signer' || $provider === 'hybrid') {
        if (empty($user['wallet_verified_at'])) {
            return 'This wallet account is not verified yet.';
        }
        return '';
    }

    if ((int) ($user['email_verified'] ?? 0) !== 1) {
        return 'Please verify your email before signing in.';
    }

    return '';
}

function rexSignerAuthFindUserByWallet(PDO $db, $wallet_address) {
    $wallet_address = rexSignerAuthNormalizeWallet($wallet_address);
    $stmt = $db->prepare("SELECT * FROM users WHERE wallet_address = ? LIMIT 1");
    $stmt->execute([$wallet_address]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function rexSignerAuthEnsureWalletRegistrationRewards(PDO $db, array $user): void {
    $user_id = (int) ($user['id'] ?? 0);
    $provider = strtolower((string) ($user['auth_provider'] ?? ''));
    if ($user_id <= 0 || $provider !== 'rex_signer') {
        return;
    }

    ensureRewardClaimSchema($db);
    ensureEarlyAirdropSchema($db);

    $signup_reward_stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND (
            reference_id IN (?, ?)
            OR action_type IN ('early_adopter_airdrop', 'welcome_bonus')
          )
    ");
    $signup_reward_stmt->execute([
        $user_id,
        'early_airdrop:signup:' . $user_id,
        'welcome_bonus:' . $user_id,
    ]);
    if ((int) ($signup_reward_stmt->fetch()['total'] ?? 0) <= 0) {
        $signup_reference_id = 'early_airdrop:signup:' . $user_id;
        if (isEarlyAirdropActive($db) && deductEarlyAirdropPool($user_id, 'signup_bonus', EARLY_AIRDROP_SIGNUP_BONUS, $db, $signup_reference_id)) {
            $expires_at = date('Y-m-d H:i:s', time() + ((int) EARLY_AIRDROP_UNLOCK_DAYS * 86400));
            addRewardLedgerEntry($user_id, EARLY_AIRDROP_SIGNUP_BONUS, 'bonus', 'early_adopter_airdrop', 'pending', $signup_reference_id, $db, 'phase1', 'beginner', $expires_at);
        } else {
            addRewardLedgerEntry($user_id, WELCOME_BONUS_REX, 'bonus', 'welcome_bonus', 'available', 'welcome_bonus:' . $user_id, $db, 'phase1', 'beginner');
        }
    }

    $referred_by = (int) ($user['referred_by'] ?? 0);
    if ($referred_by > 0) {
        $referral_reward_stmt = $db->prepare("
            SELECT COUNT(*) AS total
            FROM reward_ledger
            WHERE user_id = ?
              AND reference_id = ?
        ");
        $referral_reward_stmt->execute([$user_id, 'referral_signup:' . $user_id]);
        if ((int) ($referral_reward_stmt->fetch()['total'] ?? 0) <= 0) {
            addRewardLedgerEntry($user_id, REFERRAL_BONUS_REX, 'bonus', 'referral_signup_bonus', 'available', 'referral_signup:' . $user_id, $db, 'phase1', 'beginner');
        }
    }
}

function rexSignerAuthCreateWalletUser(PDO $db, $wallet_address, $device_fingerprint = '', $incoming_referral_code = '') {
    $wallet_address = rexSignerAuthNormalizeWallet($wallet_address);
    $signup_ip = resolveClientIpAddress();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $fingerprint = trim((string) $device_fingerprint);
    $incoming_referral_code = normalizeReferralCode($incoming_referral_code);
    $risk = evaluateRegistrationSecurityRisk('wallet+' . substr($wallet_address, 2) . '@rexsigner.local', $fingerprint, $db);

    if (!empty($risk['blocked'])) {
        logFraudEvent('wallet_registration_blocked_security_policy', 'warning', [
            'email' => null,
            'wallet_address' => $wallet_address,
            'ip_hash' => $risk['ip_hash'] ?? null,
            'fingerprint_hash' => $risk['fingerprint_hash'] ?? null,
            'ip_match_count' => $risk['ip_match_count'] ?? 0,
            'fingerprint_match_count' => $risk['fingerprint_match_count'] ?? 0,
            'ip_blocked' => !empty($risk['ip_blocked']),
            'fingerprint_blocked' => !empty($risk['fingerprint_blocked']),
        ], $db);
        throw new RuntimeException((string) ($risk['message'] ?? 'Registration is temporarily unavailable from this device.'));
    }

    if (!empty($risk['combined_pattern'])) {
        logFraudEvent('wallet_registration_pattern_flagged', 'warning', [
            'email' => null,
            'wallet_address' => $wallet_address,
            'ip_hash' => $risk['ip_hash'] ?? null,
            'fingerprint_hash' => $risk['fingerprint_hash'] ?? null,
            'ip_match_count' => $risk['ip_match_count'] ?? 0,
            'fingerprint_match_count' => $risk['fingerprint_match_count'] ?? 0,
            'reason' => 'same ip + same fingerprint + wallet registration pattern',
        ], $db);
    }

    $referral_code = generateReferralCode();
    $full_name = rexSignerAuthGeneratedName($wallet_address);
    $username = rexSignerAuthGeneratedUsername($db, $wallet_address);
    $referred_by = null;
    $referral_bonus = 0;

    if ($incoming_referral_code !== '') {
        $referral_validation = validateReferralCode($incoming_referral_code);
        if (!$referral_validation['valid']) {
            throw new RuntimeException($referral_validation['message']);
        }
        $referred_by = (int) ($referral_validation['referrer']['id'] ?? 0);
        $referral_bonus = REFERRAL_BONUS_REX;
    }

    $started_transaction = !$db->inTransaction();
    try {
        if ($started_transaction) {
            $db->beginTransaction();
        }

        $stmt = $db->prepare("
            INSERT INTO users (
                full_name, email, password, auth_provider, username, referral_code,
                referred_by, rex_balance, total_rex_earned, signup_ip, user_agent,
                status, email_verified, wallet_address, wallet_verified_at
            ) VALUES (
                ?, NULL, NULL, 'rex_signer', ?, ?, ?, 0, 0, ?, ?,
                'active', 0, ?, NOW()
            )
        ");
        $stmt->execute([
            $full_name,
            $username,
            $referral_code,
            $referred_by,
            $signup_ip !== '' ? $signup_ip : null,
            $user_agent,
            $wallet_address,
        ]);

        $user_id = (int) $db->lastInsertId();
        if (!empty($risk['combined_pattern'])) {
            $flag_stmt = $db->prepare("UPDATE users SET security_flagged = 1, security_flag_reason = ? WHERE id = ?");
            $flag_stmt->execute(['System flagged: wallet signup IP + fingerprint pattern', $user_id]);
        }

        logUserSecuritySignal($user_id, 'signup', [
            'raw_ip' => $signup_ip,
            'fingerprint' => $fingerprint,
            'user_agent' => $user_agent,
            'channel' => 'rex_signer_auth_register',
            'wallet_address' => $wallet_address,
        ], $db);

        if ($referred_by) {
            $update_referrer = $db->prepare("UPDATE users SET total_referrals = total_referrals + 1 WHERE id = ?");
            $update_referrer->execute([$referred_by]);
        }

        $signup_reference_id = 'early_airdrop:signup:' . $user_id;
        if (isEarlyAirdropActive($db) && deductEarlyAirdropPool($user_id, 'signup_bonus', EARLY_AIRDROP_SIGNUP_BONUS, $db, $signup_reference_id)) {
            $expires_at = date('Y-m-d H:i:s', time() + ((int) EARLY_AIRDROP_UNLOCK_DAYS * 86400));
            addRewardLedgerEntry($user_id, EARLY_AIRDROP_SIGNUP_BONUS, 'bonus', 'early_adopter_airdrop', 'pending', $signup_reference_id, $db, 'phase1', 'beginner', $expires_at);
        } else {
            addRewardLedgerEntry($user_id, WELCOME_BONUS_REX, 'bonus', 'welcome_bonus', 'available', 'welcome_bonus:' . $user_id, $db, 'phase1', 'beginner');
        }

        if ($referral_bonus > 0) {
            addRewardLedgerEntry($user_id, $referral_bonus, 'bonus', 'referral_signup_bonus', 'available', 'referral_signup:' . $user_id, $db, 'phase1', 'beginner');
        }

        if ($started_transaction) {
            $db->commit();
        }
    } catch (Throwable $e) {
        if ($started_transaction && $db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    $fresh_stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $fresh_stmt->execute([$user_id]);
    $fresh = $fresh_stmt->fetch();
    if (!$fresh) {
        throw new RuntimeException('Wallet account could not be loaded after creation.');
    }

    return $fresh;
}

function rexSignerAuthFindOrCreateUser(PDO $db, $wallet_address, $device_fingerprint = '', $referral_code = '') {
    $wallet_address = rexSignerAuthNormalizeWallet($wallet_address);
    $existing = rexSignerAuthFindUserByWallet($db, $wallet_address);
    if ($existing) {
        rexSignerAuthEnsureWalletRegistrationRewards($db, $existing);
        $existing = rexSignerAuthFindUserByWallet($db, $wallet_address) ?: $existing;
        return [$existing, false];
    }

    return [rexSignerAuthCreateWalletUser($db, $wallet_address, $device_fingerprint, $referral_code), true];
}

rexSignerAuthEnsureSchema();
