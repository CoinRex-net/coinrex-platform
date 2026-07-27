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
        $baseParts = parse_url(BASE_URL);
        $pathHost = strtolower((string) ($parts['host'] ?? ''));
        $baseHost = strtolower((string) ($baseParts['host'] ?? ''));
        $mediaPath = trim((string) ($parts['path'] ?? ''));

        if ($mediaPath !== '') {
            $baseUriPrefix = defined('BASE_URI') ? rtrim((string) BASE_URI, '/') : '';
            $localPrefixes = array_filter([
                $baseUriPrefix !== '' ? $baseUriPrefix . '/' : '',
                '/coinrex/',
                '/devhub/',
                '/assets/',
                '/uploads/',
            ]);
            $isLocalMediaPath = false;
            foreach ($localPrefixes as $prefix) {
                if (strpos($mediaPath, $prefix) === 0) {
                    $isLocalMediaPath = true;
                    break;
                }
            }

            if ($pathHost !== '' && $baseHost !== '' && $pathHost !== $baseHost && !$isLocalMediaPath) {
                return $path;
            }

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
