<?php
/** Feature visibility and access controls for MVP launch management. */

function getDefaultFeatureFlags(): array {
    $default_cta_guest = BASE_URL . '/index.php';
    $default_cta_user = BASE_URL . '/public/dashboard.php';

    return [
        'registration' => [
            'label' => 'Registration',
            'group' => 'MVP Core',
            'is_visible' => 1,
            'is_accessible' => 1,
            'fallback_title' => 'Registration is temporarily closed',
            'fallback_message' => 'New account registration is paused while we prepare the next launch window.',
            'fallback_cta_label' => 'Back to Home',
            'fallback_cta_url' => $default_cta_guest,
        ],
        'login' => [
            'label' => 'Login',
            'group' => 'MVP Core',
            'is_visible' => 1,
            'is_accessible' => 1,
            'fallback_title' => 'Login is temporarily unavailable',
            'fallback_message' => 'Account login is paused for a short maintenance window. Please try again soon.',
            'fallback_cta_label' => 'Back to Home',
            'fallback_cta_url' => $default_cta_guest,
        ],
        'rexlink_auth' => [
            'label' => 'RexLink Sign-In',
            'group' => 'MVP Core',
            'is_visible' => 1,
            'is_accessible' => 0,
            'fallback_title' => 'RexLink sign-in is coming soon',
            'fallback_message' => 'RexLink wallet sign-in is being finalized for the MVP rollout. Please use email login for now.',
            'fallback_cta_label' => 'Back to Login',
            'fallback_cta_url' => BASE_URL . '/auth/auth.php',
        ],
        'dashboard' => [
            'label' => 'Dashboard',
            'group' => 'MVP Core',
            'is_visible' => 1,
            'is_accessible' => 1,
            'fallback_title' => 'Dashboard is temporarily unavailable',
            'fallback_message' => 'RexHub is being updated for the MVP launch. Your account and rewards remain safe.',
            'fallback_cta_label' => 'Back to Home',
            'fallback_cta_url' => $default_cta_guest,
        ],
        'early_airdrop' => [
            'label' => 'Early Airdrop',
            'group' => 'Rewards',
            'is_visible' => 1,
            'is_accessible' => 1,
            'fallback_title' => 'Early Airdrop is paused',
            'fallback_message' => 'The early adopter airdrop display is temporarily paused while launch controls are updated.',
            'fallback_cta_label' => 'Go to Dashboard',
            'fallback_cta_url' => $default_cta_user,
        ],
        'learnhub' => [
            'label' => 'LearnHub',
            'group' => 'Rewards',
            'is_visible' => 1,
            'is_accessible' => 1,
            'fallback_title' => 'LearnHub is temporarily unavailable',
            'fallback_message' => 'LearnHub missions are paused while we tune the MVP experience.',
            'fallback_cta_label' => 'Go to Dashboard',
            'fallback_cta_url' => $default_cta_user,
        ],
        'boosthub' => [
            'label' => 'BoostHub',
            'group' => 'Rewards',
            'is_visible' => 1,
            'is_accessible' => 1,
            'fallback_title' => 'BoostHub is temporarily unavailable',
            'fallback_message' => 'Daily Boost tasks are paused while we prepare the next launch batch.',
            'fallback_cta_label' => 'Go to Dashboard',
            'fallback_cta_url' => $default_cta_user,
        ],
        'leaderboard' => [
            'label' => 'Leaderboard',
            'group' => 'Community',
            'is_visible' => 1,
            'is_accessible' => 1,
            'fallback_title' => 'Leaderboard is temporarily unavailable',
            'fallback_message' => 'Leaderboard rankings are being refreshed right now. Please check back shortly.',
            'fallback_cta_label' => 'Back to Home',
            'fallback_cta_url' => $default_cta_guest,
        ],
        'claim_center' => [
            'label' => 'Claim Center',
            'group' => 'Rewards',
            'is_visible' => 0,
            'is_accessible' => 0,
            'fallback_title' => 'Claim Center is coming soon',
            'fallback_message' => 'Reward claiming is not part of this MVP release yet. Keep earning REX from live MVP features.',
            'fallback_cta_label' => 'Go to Dashboard',
            'fallback_cta_url' => $default_cta_user,
        ],
        'projects' => [
            'label' => 'Projects',
            'group' => 'Marketplace',
            'is_visible' => 0,
            'is_accessible' => 0,
            'fallback_title' => 'Projects are coming soon',
            'fallback_message' => 'The public project marketplace is hidden during MVP launch and will open after review flows are ready.',
            'fallback_cta_label' => 'Go to Dashboard',
            'fallback_cta_url' => $default_cta_user,
        ],
        'reviews' => [
            'label' => 'Reviews',
            'group' => 'Marketplace',
            'is_visible' => 0,
            'is_accessible' => 0,
            'fallback_title' => 'Reviews are coming soon',
            'fallback_message' => 'Community reviews are hidden during the MVP launch while moderation flows are finalized.',
            'fallback_cta_label' => 'Go to Dashboard',
            'fallback_cta_url' => $default_cta_user,
        ],
        'devhub_auth' => [
            'label' => 'DevHub Auth',
            'group' => 'Developer',
            'is_visible' => 1,
            'is_accessible' => 1,
            'fallback_title' => 'Developer access is temporarily closed',
            'fallback_message' => 'Developer sign-in and registration are paused while DevHub launch controls are updated.',
            'fallback_cta_label' => 'Back to Home',
            'fallback_cta_url' => $default_cta_guest,
        ],
        'devhub_full' => [
            'label' => 'DevHub Full Panel',
            'group' => 'Developer',
            'is_visible' => 0,
            'is_accessible' => 0,
            'fallback_title' => 'DevHub full panel is coming soon',
            'fallback_message' => 'The developer dashboard is not part of this MVP release yet. Public MVP features remain available.',
            'fallback_cta_label' => 'Go to Dashboard',
            'fallback_cta_url' => $default_cta_user,
        ],
    ];
}

function ensureFeatureFlagsSchema(PDO $db = null): void {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $db = $db ?: getDBConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS feature_flags (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            feature_key VARCHAR(80) NOT NULL,
            label VARCHAR(120) NOT NULL,
            feature_group VARCHAR(80) NOT NULL DEFAULT 'General',
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            is_accessible TINYINT(1) NOT NULL DEFAULT 1,
            fallback_title VARCHAR(180) NOT NULL DEFAULT '',
            fallback_message TEXT NULL,
            fallback_cta_label VARCHAR(80) NOT NULL DEFAULT '',
            fallback_cta_url VARCHAR(500) NOT NULL DEFAULT '',
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_feature_flags_key (feature_key),
            KEY idx_feature_flags_group (feature_group),
            KEY idx_feature_flags_visible (is_visible),
            KEY idx_feature_flags_accessible (is_accessible)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    seedDefaultFeatureFlags($db, false, false);
    $ensured = true;
}

function seedDefaultFeatureFlags(PDO $db = null, bool $resetMessages = false, bool $resetControls = true): void {
    $db = $db ?: getDBConnection();
    $defaults = getDefaultFeatureFlags();

    foreach ($defaults as $key => $flag) {
        $stmt = $db->prepare("SELECT id FROM feature_flags WHERE feature_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $existing_id = (int) ($stmt->fetchColumn() ?: 0);

        if ($existing_id > 0) {
            $fields = [
                'label = ?',
                'feature_group = ?',
            ];
            $params = [
                (string) $flag['label'],
                (string) $flag['group'],
            ];

            if ($resetControls) {
                $fields[] = 'is_visible = ?';
                $fields[] = 'is_accessible = ?';
                $params[] = (int) $flag['is_visible'];
                $params[] = (int) $flag['is_accessible'];
            }

            if ($resetMessages) {
                $fields[] = 'fallback_title = ?';
                $fields[] = 'fallback_message = ?';
                $fields[] = 'fallback_cta_label = ?';
                $fields[] = 'fallback_cta_url = ?';
                $params[] = (string) $flag['fallback_title'];
                $params[] = (string) $flag['fallback_message'];
                $params[] = (string) $flag['fallback_cta_label'];
                $params[] = (string) $flag['fallback_cta_url'];
            }

            $params[] = $key;
            $db->prepare('UPDATE feature_flags SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE feature_key = ?')
                ->execute($params);
            continue;
        }

        $insert = $db->prepare("
            INSERT INTO feature_flags (
                feature_key, label, feature_group, is_visible, is_accessible,
                fallback_title, fallback_message, fallback_cta_label, fallback_cta_url
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert->execute([
            $key,
            (string) $flag['label'],
            (string) $flag['group'],
            (int) $flag['is_visible'],
            (int) $flag['is_accessible'],
            (string) $flag['fallback_title'],
            (string) $flag['fallback_message'],
            (string) $flag['fallback_cta_label'],
            (string) $flag['fallback_cta_url'],
        ]);
    }
}

function getFeatureFlag(string $key): array {
    static $cache = [];
    $key = trim($key);

    if ($key === '') {
        return [];
    }

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $defaults = getDefaultFeatureFlags();
    $fallback = $defaults[$key] ?? [
        'label' => ucwords(str_replace('_', ' ', $key)),
        'group' => 'General',
        'is_visible' => 0,
        'is_accessible' => 0,
        'fallback_title' => 'Feature unavailable',
        'fallback_message' => 'This feature is not available right now.',
        'fallback_cta_label' => 'Go to Dashboard',
        'fallback_cta_url' => BASE_URL . '/public/dashboard.php',
    ];

    try {
        $db = getDBConnection();
        ensureFeatureFlagsSchema($db);
        $stmt = $db->prepare("SELECT * FROM feature_flags WHERE feature_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch() ?: [];
        if ($row) {
            $cache[$key] = array_merge($fallback, [
                'feature_key' => (string) $row['feature_key'],
                'label' => (string) $row['label'],
                'group' => (string) ($row['feature_group'] ?? $fallback['group']),
                'is_visible' => (int) $row['is_visible'],
                'is_accessible' => (int) $row['is_accessible'],
                'fallback_title' => (string) $row['fallback_title'],
                'fallback_message' => (string) ($row['fallback_message'] ?? ''),
                'fallback_cta_label' => (string) $row['fallback_cta_label'],
                'fallback_cta_url' => (string) $row['fallback_cta_url'],
            ]);
        } else {
            $cache[$key] = array_merge($fallback, ['feature_key' => $key]);
        }
    } catch (Throwable $e) {
        $cache[$key] = array_merge($fallback, ['feature_key' => $key]);
    }

    return $cache[$key];
}

function featureIsVisible(string $key): bool {
    $flag = getFeatureFlag($key);
    return !empty($flag['is_visible']);
}

function featureIsAccessible(string $key): bool {
    $flag = getFeatureFlag($key);
    return !empty($flag['is_accessible']);
}

function renderFeatureFallback(array $flag, array $context = []): void {
    if (!headers_sent()) {
        http_response_code(503);
    }

    $title = trim((string) ($flag['fallback_title'] ?? 'Feature unavailable'));
    $message = trim((string) ($flag['fallback_message'] ?? 'This feature is not available right now.'));
    $cta_label = trim((string) ($flag['fallback_cta_label'] ?? 'Go to Dashboard'));
    $cta_url = trim((string) ($flag['fallback_cta_url'] ?? ''));

    if ($cta_url === '') {
        $cta_url = isLoggedIn() ? (BASE_URL . '/public/dashboard.php') : (BASE_URL . '/index.php');
    }

    $site_name = defined('SITE_NAME') ? SITE_NAME : 'CoinRex';
    $assets_url = defined('ASSETS_URL') ? ASSETS_URL : (BASE_URL . '/assets');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars($assets_url, ENT_QUOTES, 'UTF-8'); ?>/images/favicon.ico">
    <link rel="stylesheet" href="<?php echo htmlspecialchars($assets_url, ENT_QUOTES, 'UTF-8'); ?>/css/theme.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: #081120; color: #e2e8f0; font-family: Inter, Arial, sans-serif; }
        .feature-fallback { width: min(620px, 100%); border: 1px solid rgba(148,163,184,.18); border-radius: 18px; padding: 30px; background: linear-gradient(145deg, rgba(15,23,42,.96), rgba(8,17,32,.94)); box-shadow: 0 28px 80px rgba(0,0,0,.38); text-align: center; }
        .feature-fallback__badge { display: inline-flex; align-items: center; gap: 8px; padding: 7px 12px; border-radius: 999px; background: rgba(212,175,55,.10); border: 1px solid rgba(212,175,55,.28); color: #fde68a; font-size: 12px; font-weight: 800; text-transform: uppercase; }
        .feature-fallback h1 { margin: 18px 0 10px; color: #f8fafc; font-size: clamp(26px, 5vw, 40px); line-height: 1.1; }
        .feature-fallback p { margin: 0 auto 22px; max-width: 520px; color: #b9c7e8; line-height: 1.65; }
        .feature-fallback a { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 18px; border-radius: 12px; background: #1d4ed8; color: #fff; font-weight: 800; text-decoration: none; }
    </style>
</head>
<body>
    <main class="feature-fallback" role="main">
        <span class="feature-fallback__badge"><?php echo htmlspecialchars((string) ($flag['label'] ?? 'Feature'), ENT_QUOTES, 'UTF-8'); ?></span>
        <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <a href="<?php echo htmlspecialchars($cta_url, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cta_label, ENT_QUOTES, 'UTF-8'); ?></a>
    </main>
</body>
</html>
<?php
}

function requireFeatureAccess(string $key, array $fallbackContext = []): void {
    if (featureIsAccessible($key)) {
        return;
    }

    renderFeatureFallback(getFeatureFlag($key), $fallbackContext);
    exit;
}
?>
