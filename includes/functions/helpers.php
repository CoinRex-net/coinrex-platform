<?php
/** Auto-split from legacy functions.php */

function redirect($url) {
    header("Location: $url");
    exit();
}

function coinrexNormalizeMediaUrl($path) {
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $path)) {
        $parts = parse_url($path);
        $mediaPath = trim((string) ($parts['path'] ?? ''));
        if ($mediaPath !== '') {
            $path = $mediaPath;
        } else {
            return $path;
        }
    }

    if (strpos($path, BASE_URI . '/') === 0) {
        $path = substr($path, strlen(BASE_URI));
    }

    if (strpos($path, '/coinrex/') === 0 && BASE_URI !== '/coinrex') {
        $path = substr($path, strlen('/coinrex'));
    }

    if ($path === '') {
        return '';
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return rtrim(BASE_URL, '/') . $path;
}
