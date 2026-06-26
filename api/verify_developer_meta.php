<?php
/**
 * CoinRex Developer Meta Tag Verification API
 *
 * Fetches the developer's website and checks if the verification meta tag exists.
 * POST /api/verify_developer_meta.php
 *   website_url  (string)  The URL to check
 *   meta_code    (string)  The meta tag code to search for
 *
 * Returns JSON:
 *   { "success": true, "found": true/false, "message": "..." }
 */

require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

// Only admins can trigger meta verification
if (!apiIsAdminSession()) {
    apiErrorResponse(401, 'Admin authentication required.');
}

$website_url = trim((string) ($_POST['website_url'] ?? ''));
$meta_code   = trim((string) ($_POST['meta_code'] ?? ''));

if ($website_url === '') {
    apiErrorResponse(422, 'Website URL is required.');
}
if ($meta_code === '') {
    apiErrorResponse(422, 'Meta tag code is required.');
}

// Validate URL format
if (!preg_match('#^https?://#i', $website_url)) {
    apiErrorResponse(422, 'Website URL must start with http:// or https://');
}

// Normalize the meta code — extract just the content attribute value for flexible matching
$normalized_search = normalizeMetaCode($meta_code);

try {
    $html = fetchUrlContent($website_url);
    if ($html === false || $html === '') {
        apiErrorResponse(502, 'Could not fetch the website. The URL may be unreachable or blocking requests.');
    }

    $found = searchMetaTagInHtml($html, $normalized_search);

    if ($found) {
        apiSuccessResponse([
            'found'   => true,
            'message' => 'Meta tag verified successfully! The verification code was found on the website.',
        ]);
    } else {
        apiSuccessResponse([
            'found'   => false,
            'message' => 'Meta tag not found on the page. Make sure the meta tag is placed in the <head> section of your website.',
        ]);
    }
} catch (Throwable $e) {
    apiErrorResponse(500, 'Verification failed: ' . $e->getMessage());
}

// ---------- Helper Functions ----------

/**
 * Fetch URL content using cURL (preferred) or file_get_contents fallback.
 */
function fetchUrlContent($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'CoinRex-Verification-Bot/1.0',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
            ],
        ]);
        $html = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 400) {
            return false;
        }
        return $html;
    }

    // Fallback to file_get_contents
    $context = stream_context_create([
        'http' => [
            'method'          => 'GET',
            'timeout'         => 15,
            'user_agent'      => 'CoinRex-Verification-Bot/1.0',
            'header'          => "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n",
            'ignore_errors'   => true,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);

    $html = @file_get_contents($url, false, $context);
    return $html;
}

/**
 * Normalize the meta code for flexible searching.
 * Extracts just the content value from a full meta tag, or returns the raw string.
 */
function normalizeMetaCode($code) {
    // If it looks like a full HTML meta tag, extract the content attribute
    if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $code, $m)) {
        return trim($m[1]);
    }
    return trim($code);
}

/**
 * Search for the verification meta tag in HTML content.
 * Uses DOMDocument for reliable parsing.
 */
function searchMetaTagInHtml($html, $search_value) {
    // Suppress warnings from malformed HTML
    $use_errors = libxml_use_internal_errors(true);

    $dom = new DOMDocument();
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);

    libxml_use_internal_errors($use_errors);

    if (!$loaded) {
        // Fallback: simple string search
        return stripos($html, $search_value) !== false;
    }

    // Look for <meta name="coinrex-verification" content="...">
    $metas = $dom->getElementsByTagName('meta');
    foreach ($metas as $meta) {
        $name = trim((string) ($meta->getAttribute('name') ?? ''));
        $content = trim((string) ($meta->getAttribute('content') ?? ''));

        // Check if name matches coinrex-verification (case-insensitive)
        if (strcasecmp($name, 'coinrex-verification') === 0) {
            // Check if content contains our search value
            if ($content !== '' && (stripos($content, $search_value) !== false || $content === $search_value)) {
                return true;
            }
        }
    }

    // Fallback: also try a regex search in case DOM parsing missed something
    $pattern = '/<meta[^>]+name\s*=\s*["\']coinrex-verification["\'][^>]*content\s*=\s*["\']([^"\']+)["\'][^>]*\/?>/i';
    if (preg_match($pattern, $html, $matches)) {
        $found_content = trim($matches[1]);
        if (stripos($found_content, $search_value) !== false || $found_content === $search_value) {
            return true;
        }
    }

    return false;
}
