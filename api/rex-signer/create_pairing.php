<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $db = getDBConnection();
    $purpose = strtolower(trim((string) rexSignerInput('purpose', 'claim')));
    $is_auth_pairing = $purpose === 'auth';
    if ($is_auth_pairing && !featureIsAccessible('rexlink_auth')) {
        apiErrorResponse(403, 'RexLink sign-in is coming soon. Please use email login for now.');
    }
    $actor = null;
    $user_id = null;

    if (isLoggedIn()) {
        $user = getCurrentUser();
        if ($user) {
            $actor = [
                'type' => 'user',
                'user_id' => (int) ($user['id'] ?? 0),
                'user' => $user,
            ];
            $user_id = (int) $actor['user_id'];
        }
    }

    if (!$is_auth_pairing && (empty($actor['user_id']) || $actor['type'] !== 'user')) {
        apiErrorResponse(403, 'Only logged-in CoinRex users can create RexLink pairing codes.');
    }

    rexSignerExpireOldRows($db);

    $duration = rexSignerClampDuration(rexSignerInput('duration_minutes', 10));
    $dapp_name = trim((string) rexSignerInput('dapp_name', (defined('SITE_NAME') ? SITE_NAME : 'CoinRex')));
    $dapp_url = trim((string) rexSignerInput('dapp_url', (defined('BASE_URL') ? BASE_URL : '')));
    $network_slug = trim((string) rexSignerInput('network_slug', 'polygon-amoy'));
    $requested_wallet_address = trim((string) rexSignerInput('requested_wallet_address', ''));
    $network_stmt = $db->prepare("SELECT slug, name, chain_id, native_symbol FROM rex_signer_networks WHERE slug = ? AND is_enabled = 1 LIMIT 1");
    $network_stmt->execute([$network_slug]);
    $network_row = $network_stmt->fetch();
    if (!$network_row) {
        $network_slug = 'polygon-amoy';
        $network_stmt->execute([$network_slug]);
        $network_row = $network_stmt->fetch() ?: ['slug' => 'polygon-amoy', 'name' => 'Polygon Amoy', 'chain_id' => 80002, 'native_symbol' => 'POL'];
    }
    if ($requested_wallet_address !== '' && !preg_match('/^0x[a-fA-F0-9]{40}$/', $requested_wallet_address)) {
        apiErrorResponse(422, 'requested_wallet_address must be a valid wallet address.');
    }
    $public_base_url = defined('PUBLIC_BASE_URL') ? PUBLIC_BASE_URL : BASE_URL;
    $build_qr_payload = static function ($display_code, $expires_at = '', $expires_in_seconds = null, $expires_at_unix = null) use ($db, $is_auth_pairing, $duration, $dapp_name, $dapp_url, $network_row, $requested_wallet_address, $public_base_url) {
        $context_source = [
            'dapp_name' => $dapp_name,
            'dapp_url' => $dapp_url,
            'base_url' => $public_base_url,
            'network_slug' => (string) ($network_row['slug'] ?? 'polygon-amoy'),
            'network_name' => (string) ($network_row['name'] ?? 'Polygon Amoy'),
            'chain_id' => isset($network_row['chain_id']) ? (int) $network_row['chain_id'] : null,
            'requested_wallet_address' => $requested_wallet_address,
            'expires_at' => $expires_at,
        ];
        $contexts = rexSignerBuildDisplayContext($db, $context_source);
        $payload = [
            'type' => 'coinrex.rex_signer.pairing',
            'version' => 2,
            'code' => $display_code,
            'purpose' => $is_auth_pairing ? 'auth' : 'claim',
            'base_url' => $public_base_url,
            'api_base_url' => $public_base_url,
            'dapp_name' => $dapp_name !== '' ? substr($dapp_name, 0, 80) : null,
            'dapp_url' => $dapp_url !== '' ? substr($dapp_url, 0, 255) : BASE_URL,
            'network_slug' => (string) ($network_row['slug'] ?? 'polygon-amoy'),
            'network_name' => (string) ($network_row['name'] ?? 'Polygon Amoy'),
            'chain_id' => isset($network_row['chain_id']) ? (int) $network_row['chain_id'] : null,
            'native_symbol' => (string) ($network_row['native_symbol'] ?? ''),
            'requested_duration_minutes' => $duration,
            'expires_at' => $expires_at,
            'display_context' => $contexts['display_context'],
            'trust_context' => $contexts['trust_context'],
        ];
        if ($expires_in_seconds !== null) {
            $payload['expires_in_seconds'] = max(0, (int) $expires_in_seconds);
        }
        if ($expires_at_unix !== null) {
            $payload['expires_at_unix'] = max(0, (int) $expires_at_unix);
        }
        if ($requested_wallet_address !== '') {
            $payload['requested_wallet_address'] = strtolower($requested_wallet_address);
        }
        return $payload;
    };
    $referral_code = null;
    if ($is_auth_pairing) {
        $requested_referral_code = normalizeReferralCode(rexSignerInput('referral_code', ''));
        if ($requested_referral_code !== '') {
            $referral_validation = validateReferralCode($requested_referral_code);
            if (!$referral_validation['valid']) {
                apiErrorResponse(422, $referral_validation['message']);
            }
            $referral_code = (string) $referral_validation['code'];
        }
    }

    if ($user_id !== null) {
        $active_stmt = $db->prepare("
            SELECT *,
                   GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS remaining_seconds,
                   UNIX_TIMESTAMP(expires_at) AS expires_at_unix
            FROM rex_signer_sessions
            WHERE user_id = ?
              AND status = 'active'
              AND expires_at > NOW()
            ORDER BY id DESC
            LIMIT 1
        ");
        $active_stmt->execute([$user_id]);
        $active_session = $active_stmt->fetch();
        if ($active_session) {
            apiSuccessResponse([
                'message' => 'RexLink is already connected.',
                'already_connected' => true,
                'session' => rexSignerSessionPayload($active_session),
            ]);
        }
    }

    $pending_pairing = null;
    if ($is_auth_pairing && !empty($_SESSION['rex_signer_auth_pairing_id'])) {
        $pending_stmt = $db->prepare("
            SELECT id, display_code, requested_duration_minutes, referral_code, expires_at,
                   GREATEST(TIMESTAMPDIFF(SECOND, NOW(), expires_at), 0) AS expires_in_seconds,
                   UNIX_TIMESTAMP(expires_at) AS expires_at_unix
            FROM rex_signer_pairing_codes
            WHERE id = ?
              AND pairing_purpose = 'auth'
              AND status = 'pending'
              AND expires_at > NOW()
            LIMIT 1
        ");
        $pending_stmt->execute([(int) $_SESSION['rex_signer_auth_pairing_id']]);
        $pending_pairing = $pending_stmt->fetch();
    } elseif ($user_id !== null) {
        $pending_stmt = $db->prepare("
            SELECT id, display_code, requested_duration_minutes, referral_code, expires_at,
                   GREATEST(TIMESTAMPDIFF(SECOND, NOW(), expires_at), 0) AS expires_in_seconds,
                   UNIX_TIMESTAMP(expires_at) AS expires_at_unix
            FROM rex_signer_pairing_codes
            WHERE user_id = ?
              AND pairing_purpose = ?
              AND status = 'pending'
              AND expires_at > NOW()
            ORDER BY id DESC
            LIMIT 1
        ");
        $pending_stmt->execute([$user_id, $purpose]);
        $pending_pairing = $pending_stmt->fetch();
    }

    if ($pending_pairing) {
        $display_code = (string) $pending_pairing['display_code'];
        $normalized_pending_code = rexSignerNormalizePairCode($display_code);
        if ($is_auth_pairing && (
            (int) $pending_pairing['requested_duration_minutes'] !== $duration
            || strtoupper((string) ($pending_pairing['referral_code'] ?? '')) !== strtoupper((string) $referral_code)
        )) {
            $expire_pending = $db->prepare("UPDATE rex_signer_pairing_codes SET status = 'expired' WHERE id = ?");
            $expire_pending->execute([(int) $pending_pairing['id']]);
            $pending_pairing = null;
        } elseif (preg_match('/^\d{6}$/', $normalized_pending_code)) {
            $display_code = rexSignerFormatPairCode($normalized_pending_code);
            apiSuccessResponse([
                'message' => 'Existing pairing code is still active.',
                'pairing_id' => (int) $pending_pairing['id'],
                'display_code' => $display_code,
                'expires_in_seconds' => max(1, (int) ($pending_pairing['expires_in_seconds'] ?? 300)),
                'requested_duration_minutes' => (int) $pending_pairing['requested_duration_minutes'],
                'display_context' => $build_qr_payload($display_code, (string) ($pending_pairing['expires_at'] ?? ''), (int) ($pending_pairing['expires_in_seconds'] ?? 300), isset($pending_pairing['expires_at_unix']) ? (int) $pending_pairing['expires_at_unix'] : null)['display_context'],
                'trust_context' => $build_qr_payload($display_code, (string) ($pending_pairing['expires_at'] ?? ''), (int) ($pending_pairing['expires_in_seconds'] ?? 300), isset($pending_pairing['expires_at_unix']) ? (int) $pending_pairing['expires_at_unix'] : null)['trust_context'],
                'qr_payload' => $build_qr_payload($display_code, (string) ($pending_pairing['expires_at'] ?? ''), (int) ($pending_pairing['expires_in_seconds'] ?? 300), isset($pending_pairing['expires_at_unix']) ? (int) $pending_pairing['expires_at_unix'] : null),
            ]);
        }

        if ($pending_pairing) {
            $expire_legacy = $db->prepare("UPDATE rex_signer_pairing_codes SET status = 'expired' WHERE id = ?");
            $expire_legacy->execute([(int) $pending_pairing['id']]);
        }
    }

    $code = '';
    $display_code = '';
    $code_hash = '';
    $collision_stmt = $db->prepare("
        SELECT id
        FROM rex_signer_pairing_codes
        WHERE code_hash = ?
        LIMIT 1
    ");
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $candidate = rexSignerGeneratePairCode();
        $candidate_hash = rexSignerHashSecret($candidate);
        $collision_stmt->execute([$candidate_hash]);
        if (!$collision_stmt->fetch()) {
            $code = $candidate;
            $display_code = rexSignerFormatPairCode($candidate);
            $code_hash = $candidate_hash;
            break;
        }
    }

    if ($code === '') {
        apiErrorResponse(503, 'Could not create a pairing code. Please try again.');
    }

    $stmt = $db->prepare("
        INSERT INTO rex_signer_pairing_codes
            (user_id, code_hash, display_code, pairing_purpose, referral_code, requested_duration_minutes, expires_at, device_fingerprint, ip_address, user_agent)
        VALUES
            (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $code_hash,
        $display_code,
        $is_auth_pairing ? 'auth' : 'claim',
        $referral_code,
        $duration,
        $is_auth_pairing ? substr(trim((string) rexSignerInput('device_fingerprint', '')), 0, 255) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
    $pairing_id = (int) $db->lastInsertId();
    if ($is_auth_pairing) {
        $_SESSION['rex_signer_auth_pairing_id'] = $pairing_id;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $created_stmt = $db->prepare("
        SELECT expires_at,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), expires_at)) AS expires_in_seconds,
               UNIX_TIMESTAMP(expires_at) AS expires_at_unix
        FROM rex_signer_pairing_codes
        WHERE id = ?
        LIMIT 1
    ");
    $created_stmt->execute([$pairing_id]);
    $created_pairing = $created_stmt->fetch() ?: [];
    $expires_at = (string) ($created_pairing['expires_at'] ?? '');
    $expires_in_seconds = max(1, (int) ($created_pairing['expires_in_seconds'] ?? 300));
    $expires_at_unix = isset($created_pairing['expires_at_unix']) ? (int) $created_pairing['expires_at_unix'] : null;
    $qr_payload = $build_qr_payload($display_code, $expires_at, $expires_in_seconds, $expires_at_unix);

    apiSuccessResponse([
        'message' => 'Pairing code created.',
        'pairing_id' => $pairing_id,
        'display_code' => $display_code,
        'expires_in_seconds' => $expires_in_seconds,
        'expires_at' => $expires_at,
        'expires_at_unix' => $expires_at_unix,
        'requested_duration_minutes' => $duration,
        'api_base_url' => $public_base_url,
        'display_context' => $qr_payload['display_context'],
        'trust_context' => $qr_payload['trust_context'],
        'qr_payload' => $qr_payload,
    ], 201);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
