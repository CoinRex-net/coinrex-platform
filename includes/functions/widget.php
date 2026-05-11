<?php
/**
 * CoinRex embeddable widget helpers.
 */

function coinrexWidgetBase64UrlEncode($value) {
    return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
}

function coinrexWidgetBase64UrlDecode($value) {
    $value = strtr((string) $value, '-_', '+/');
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($value, true);
    return $decoded === false ? '' : $decoded;
}

function coinrexWidgetNormalizeSlug($slug) {
    $slug = strtolower(trim((string) $slug));
    $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', (string) $slug);
    return trim((string) $slug, '-');
}

function coinrexWidgetNormalizeDomain($domain) {
    $domain = strtolower(trim((string) $domain));
    if ($domain === '') {
        return '';
    }

    if (strpos($domain, '://') === false) {
        $domain = 'https://' . $domain;
    }

    $host = parse_url($domain, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }

    $host = strtolower(trim($host, '.'));
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }

    return $host;
}

function coinrexWidgetNormalizeDomainsInput($input) {
    if (is_array($input)) {
        $parts = $input;
    } else {
        $parts = preg_split('/[\s,;\r\n]+/', (string) $input) ?: [];
    }

    $domains = [];
    foreach ($parts as $part) {
        $domain = coinrexWidgetNormalizeDomain($part);
        if ($domain !== '' && !in_array($domain, $domains, true)) {
            $domains[] = $domain;
        }
    }

    return $domains;
}

function coinrexWidgetGetRequestOrigin() {
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    if ($origin !== '') {
        return $origin;
    }

    $referer = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($referer === '') {
        return '';
    }

    $parts = parse_url($referer);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }

    return strtolower($parts['scheme']) . '://' . strtolower($parts['host']);
}

function coinrexWidgetGetOriginHost($origin = null) {
    $origin = $origin === null ? coinrexWidgetGetRequestOrigin() : trim((string) $origin);
    if ($origin === '') {
        return '';
    }

    $host = parse_url($origin, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return '';
    }

    return coinrexWidgetNormalizeDomain($host);
}

function coinrexWidgetOriginIsAllowed($origin, array $allowed_domains) {
    $origin_host = coinrexWidgetGetOriginHost($origin);
    if ($origin_host === '') {
        return false;
    }

    foreach ($allowed_domains as $domain) {
        $domain = coinrexWidgetNormalizeDomain($domain);
        if ($domain === '') {
            continue;
        }

        if ($origin_host === $domain || preg_match('/\.' . preg_quote($domain, '/') . '$/', $origin_host) === 1) {
            return true;
        }
    }

    return false;
}

function coinrexWidgetIsoTimestamp($value) {
    $timestamp = $value ? strtotime((string) $value) : false;
    if ($timestamp === false) {
        $timestamp = time();
    }

    return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
}

function coinrexWidgetBuildThemeContract() {
    return [
        'customizable' => ['bg', 'opacity', 'blur', 'radius', 'shadow', 'spacing'],
        'locked_brand_tokens' => ['royal_blue', 'gold_stars', 'white_typography', 'logo_styling', 'verification_badge_colors'],
        'defaults' => [
            'layout' => 'single',
            'bg' => '#111111',
            'opacity' => 0.85,
            'blur' => 16,
            'radius' => 18,
            'shadow' => 'medium',
            'spacing' => 0,
        ],
        'shadow_presets' => [
            'none' => 'none',
            'soft' => '0 10px 24px rgba(2, 6, 23, 0.18)',
            'medium' => '0 12px 28px rgba(2, 6, 23, 0.22)',
            'strong' => '0 18px 44px rgba(2, 6, 23, 0.32)',
        ],
    ];
}

function coinrexWidgetGetSecret() {
    $secret = trim((string) (getenv('COINREX_WIDGET_SECRET') ?: ''));
    if ($secret !== '') {
        return $secret;
    }

    $fallback = trim((string) (ENCRYPTION_KEY ?: CSRF_KEY));
    if ($fallback !== '') {
        return $fallback;
    }

    return ENVIRONMENT === 'production' ? '' : 'coinrex-dev-widget-secret';
}

function coinrexGenerateWidgetToken($slug, array $allowed_domains, $ttl_seconds = null, array $extra = []) {
    $secret = coinrexWidgetGetSecret();
    $slug = coinrexWidgetNormalizeSlug($slug);
    $allowed_domains = array_values(array_filter(array_map('coinrexWidgetNormalizeDomain', $allowed_domains)));

    if ($secret === '' || $slug === '' || empty($allowed_domains)) {
        return '';
    }

    $ttl_seconds = $ttl_seconds === null
        ? max(300, (int) (getenv('COINREX_WIDGET_TOKEN_TTL') ?: 86400))
        : max(300, (int) $ttl_seconds);

    $issued_at = time();
    $payload = array_merge([
        'slug' => $slug,
        'domains' => $allowed_domains,
        'iat' => $issued_at,
        'exp' => $issued_at + $ttl_seconds,
        'layouts' => ['single', 'glass'],
    ], $extra);

    $encoded_payload = coinrexWidgetBase64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $signature = coinrexWidgetBase64UrlEncode(hash_hmac('sha256', $encoded_payload, $secret, true));

    return $encoded_payload . '.' . $signature;
}

function coinrexValidateWidgetToken($token) {
    $token = trim((string) $token);
    if ($token === '') {
        return ['valid' => false, 'message' => 'Widget token is required.', 'payload' => null];
    }

    $secret = coinrexWidgetGetSecret();
    if ($secret === '') {
        return ['valid' => false, 'message' => 'Widget signing secret is not configured.', 'payload' => null];
    }

    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return ['valid' => false, 'message' => 'Widget token format is invalid.', 'payload' => null];
    }

    [$encoded_payload, $provided_signature] = $parts;
    $expected_signature = coinrexWidgetBase64UrlEncode(hash_hmac('sha256', $encoded_payload, $secret, true));
    if (!hash_equals($expected_signature, $provided_signature)) {
        return ['valid' => false, 'message' => 'Widget token signature check failed.', 'payload' => null];
    }

    $decoded_payload = coinrexWidgetBase64UrlDecode($encoded_payload);
    $payload = json_decode($decoded_payload, true);
    if (!is_array($payload)) {
        return ['valid' => false, 'message' => 'Widget token payload is invalid JSON.', 'payload' => null];
    }

    $exp = (int) ($payload['exp'] ?? 0);
    if ($exp <= time()) {
        return ['valid' => false, 'message' => 'Widget token has expired.', 'payload' => $payload];
    }

    $payload['slug'] = coinrexWidgetNormalizeSlug($payload['slug'] ?? '');
    $payload['domains'] = array_values(array_filter(array_map('coinrexWidgetNormalizeDomain', (array) ($payload['domains'] ?? []))));

    if ($payload['slug'] === '' || empty($payload['domains'])) {
        return ['valid' => false, 'message' => 'Widget token payload is incomplete.', 'payload' => $payload];
    }

    return ['valid' => true, 'message' => 'Token valid.', 'payload' => $payload];
}

function coinrexWidgetSendSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Cross-Origin-Resource-Policy: cross-origin');
}

function coinrexWidgetSendCorsHeaders($origin = '*') {
    $origin = trim((string) $origin);
    if ($origin === '') {
        return;
    }

    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Accept, Content-Type');
    header('Access-Control-Max-Age: 600');
    header('Vary: Origin');
    header('Access-Control-Allow-Origin: ' . $origin);
}

function coinrexWidgetClientFingerprint($scope = '') {
    $scope = trim((string) $scope);
    $ip = getClientIpAddress();
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
    $origin_host = coinrexWidgetGetOriginHost();
    return hash('sha256', implode('|', [$scope, $ip, $ua, $origin_host]));
}

function coinrexWidgetEnforceRateLimit($bucket, $identifier, $limit, $window_seconds = 60) {
    $bucket = trim((string) $bucket);
    $identifier = trim((string) $identifier);
    $limit = max(1, (int) $limit);
    $window_seconds = max(10, (int) $window_seconds);

    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'coinrex_widget_rate_limits';
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }

    $file_path = $directory . DIRECTORY_SEPARATOR . hash('sha256', $bucket . '|' . $identifier) . '.json';
    $now = time();
    $state = ['count' => 0, 'reset_at' => $now + $window_seconds];

    $handle = @fopen($file_path, 'c+');
    if ($handle === false) {
        return ['allowed' => true, 'remaining' => $limit - 1, 'reset_at' => $state['reset_at']];
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return ['allowed' => true, 'remaining' => $limit - 1, 'reset_at' => $state['reset_at']];
        }

        $raw = stream_get_contents($handle);
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $state['count'] = max(0, (int) ($decoded['count'] ?? 0));
                $state['reset_at'] = max($now, (int) ($decoded['reset_at'] ?? ($now + $window_seconds)));
            }
        }

        if ($state['reset_at'] <= $now) {
            $state = ['count' => 0, 'reset_at' => $now + $window_seconds];
        }

        $state['count']++;
        $allowed = $state['count'] <= $limit;
        $remaining = max(0, $limit - $state['count']);

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return ['allowed' => $allowed, 'remaining' => $remaining, 'reset_at' => $state['reset_at']];
    } catch (Throwable $e) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
        return ['allowed' => true, 'remaining' => $limit - 1, 'reset_at' => $state['reset_at']];
    }
}

function coinrexWidgetGetProjectRatingBySlug($slug, PDO $db = null) {
    $slug = coinrexWidgetNormalizeSlug($slug);
    if ($slug === '' || !tableHasColumn('projects', 'slug')) {
        return null;
    }

    $db = $db ?: getDBConnection();
    $verified_select = tableHasColumn('projects', 'is_verified') ? 'COALESCE(p.is_verified, 0)' : '0';

    $sql = "
        SELECT
            p.id,
            p.name,
            p.slug,
            p.updated_at,
            {$verified_select} AS verified_flag,
            COALESCE(review_stats.total_reviews, 0) AS total_reviews,
            COALESCE(review_stats.avg_rating, 0) AS avg_rating
        FROM projects p
        LEFT JOIN (
            SELECT
                project_id,
                COUNT(*) AS total_reviews,
                ROUND(AVG(rating), 1) AS avg_rating
            FROM reviews
            WHERE LOWER(COALESCE(NULLIF(TRIM(status), ''), 'pending')) = 'approved'
            GROUP BY project_id
        ) review_stats ON review_stats.project_id = p.id
        WHERE LOWER(p.slug) = LOWER(?)
          AND LOWER(COALESCE(NULLIF(TRIM(p.approval_status), ''), 'pending')) = 'approved'
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$slug]);
    $project = $stmt->fetch();

    if (!$project) {
        return null;
    }

    return [
        'project_name' => trim((string) ($project['name'] ?? '')),
        'slug' => trim((string) ($project['slug'] ?? $slug)),
        'rating' => round((float) ($project['avg_rating'] ?? 0), 1),
        'reviews' => max(0, (int) ($project['total_reviews'] ?? 0)),
        'verified' => ((int) ($project['verified_flag'] ?? 0)) === 1,
        'updated_at' => coinrexWidgetIsoTimestamp($project['updated_at'] ?? null),
    ];
}

function coinrexWidgetBuildWidgetPayload(array $rating_payload) {
    $theme_contract = coinrexWidgetBuildThemeContract();

    return array_merge($rating_payload, [
        'widget' => [
            'provider' => 'coinrex',
            'allowed_layouts' => ['single', 'glass'],
            'theme' => $theme_contract,
            'refresh_seconds' => 300,
            'isolation' => 'shadow-dom',
            'render_mode' => 'remote-data',
        ],
    ]);
}