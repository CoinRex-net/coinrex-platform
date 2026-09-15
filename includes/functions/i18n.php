<?php
/** CoinRex UI localization helpers. */

function coinrexSupportedLocales() {
    $locales = defined('SUPPORTED_LOCALES') ? SUPPORTED_LOCALES : ['en'];
    return is_array($locales) && !empty($locales) ? array_values($locales) : ['en'];
}

function coinrexDefaultLocale() {
    $default = defined('DEFAULT_LOCALE') ? (string) DEFAULT_LOCALE : 'en';
    return coinrexNormalizeLocale($default) ?: 'en';
}

function coinrexNormalizeLocale($locale) {
    $locale = trim(str_replace('_', '-', (string) $locale));
    if (strtolower($locale) === 'zh-cn') {
        $locale = 'zh-CN';
    } else {
        $locale = strtolower($locale);
    }

    if ($locale === '') {
        return '';
    }

    if ($locale !== 'zh-CN') {
        $locale = explode('-', $locale)[0];
    }

    return in_array($locale, coinrexSupportedLocales(), true) ? $locale : '';
}

function coinrexLocaleLabel($locale) {
    $labels = [
        'en' => 'English',
        'ur' => 'Urdu',
        'hi' => 'Hindi',
        'es' => 'Spanish',
        'fr' => 'French',
        'de' => 'German',
        'tr' => 'Turkish',
        'zh-CN' => 'Chinese',
        'af' => 'Afrikaans',
        'am' => 'Amharic',
        'ar' => 'Arabic',
        'ber' => 'Tamazight',
        'bem' => 'Bemba',
        'bm' => 'Bambara',
        'ee' => 'Ewe',
        'ff' => 'Fulani',
        'ha' => 'Hausa',
        'ig' => 'Igbo',
        'kg' => 'Kongo',
        'ln' => 'Lingala',
        'lg' => 'Luganda',
        'mg' => 'Malagasy',
        'ny' => 'Chichewa',
        'om' => 'Oromo',
        'rw' => 'Kinyarwanda',
        'sn' => 'Shona',
        'so' => 'Somali',
        'st' => 'Sesotho',
        'sw' => 'Swahili',
        'ti' => 'Tigrinya',
        'tn' => 'Tswana',
        'ts' => 'Tsonga',
        'tw' => 'Twi',
        'wo' => 'Wolof',
        'xh' => 'Xhosa',
        'yo' => 'Yoruba',
        'zu' => 'Zulu',
    ];

    return $labels[$locale] ?? strtoupper((string) $locale);
}

function coinrexLocaleDirection($locale = null) {
    return 'ltr';
}

function coinrexLocaleCookieOptions($expires) {
    $secure = function_exists('coinrexIsHttpsRequest') ? coinrexIsHttpsRequest() : false;
    return ['expires' => (int) $expires, 'path' => '/', 'domain' => '', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'];
}

function coinrexUserLanguageColumnExists(PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = 'language'");
    $stmt->execute([DB_NAME]);
    return ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
}

function ensureUserLanguageSchema(PDO $db = null) {
    static $schema_ready = false;
    if ($schema_ready) {
        return;
    }

    $db = $db ?: getDBConnection();
    if (!function_exists('tableExists') || !tableExists('users')) {
        return;
    }

    if (!coinrexUserLanguageColumnExists($db)) {
        $db->exec("ALTER TABLE users ADD COLUMN language VARCHAR(10) NULL AFTER level");
    }

    $schema_ready = true;
}

function coinrexUserPreferredLocale(PDO $db = null) {
    if (empty($_SESSION['user_id'])) {
        return '';
    }

    $db = $db ?: getDBConnection();
    ensureUserLanguageSchema($db);
    if (!coinrexUserLanguageColumnExists($db)) {
        return '';
    }

    $stmt = $db->prepare('SELECT language FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $row = $stmt->fetch();
    return coinrexNormalizeLocale((string) ($row['language'] ?? ''));
}

function coinrexResolveLocale() {
    $user_locale = '';
    if (!empty($_SESSION['user_id'])) {
        try {
            $user_locale = coinrexUserPreferredLocale();
        } catch (Throwable $e) {
            error_log('CoinRex locale user preference failed: ' . $e->getMessage());
        }
    }

    if ($user_locale !== '') return $user_locale;

    $session_locale = coinrexNormalizeLocale((string) ($_SESSION['locale'] ?? ''));
    if ($session_locale !== '') return $session_locale;

    $cookie_name = defined('LOCALE_COOKIE_NAME') ? LOCALE_COOKIE_NAME : 'coinrex_locale';
    $cookie_locale = coinrexNormalizeLocale((string) ($_COOKIE[$cookie_name] ?? ''));
    if ($cookie_locale !== '') return $cookie_locale;

    return coinrexDefaultLocale();
}

function coinrexInitializeLocale() {
    $_SESSION['locale'] = coinrexResolveLocale();
}

function coinrexCurrentLocale() {
    $locale = coinrexNormalizeLocale((string) ($_SESSION['locale'] ?? ''));
    return $locale !== '' ? $locale : coinrexDefaultLocale();
}

function coinrexSetLocale($locale, $user_id = null, PDO $db = null) {
    $locale = coinrexNormalizeLocale($locale);
    if ($locale === '') return false;

    $_SESSION['locale'] = $locale;
    $cookie_name = defined('LOCALE_COOKIE_NAME') ? LOCALE_COOKIE_NAME : 'coinrex_locale';
    setcookie($cookie_name, $locale, coinrexLocaleCookieOptions(time() + 365 * 24 * 60 * 60));
    $_COOKIE[$cookie_name] = $locale;

    $user_id = $user_id !== null ? (int) $user_id : (int) ($_SESSION['user_id'] ?? 0);
    if ($user_id > 0) {
        $db = $db ?: getDBConnection();
        ensureUserLanguageSchema($db);
        $stmt = $db->prepare('UPDATE users SET language = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$locale, $user_id]);
    }

    return true;
}

function coinrexLoadTranslationCatalog($locale) {
    static $catalogs = [];
    $locale = coinrexNormalizeLocale($locale) ?: coinrexDefaultLocale();
    if (isset($catalogs[$locale])) return $catalogs[$locale];

    $path = BASE_PATH . '/lang/' . $locale . '/ui.php';
    $catalog = is_readable($path) ? require $path : [];
    $catalogs[$locale] = is_array($catalog) ? $catalog : [];
    return $catalogs[$locale];
}

function coinrexTranslationValue(array $catalog, $key) {
    $value = $catalog;
    foreach (explode('.', (string) $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return null;
        $value = $value[$part];
    }
    return is_scalar($value) ? (string) $value : null;
}

function t($key, array $params = [], $locale = null) {
    $locale = $locale !== null ? coinrexNormalizeLocale($locale) : coinrexCurrentLocale();
    $default_locale = coinrexDefaultLocale();
    $text = coinrexTranslationValue(coinrexLoadTranslationCatalog($locale), $key);
    if ($text === null && $locale !== $default_locale) {
        $text = coinrexTranslationValue(coinrexLoadTranslationCatalog($default_locale), $key);
    }
    if ($text === null) $text = (string) $key;
    foreach ($params as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

function te($key, array $params = [], $locale = null) {
    return htmlspecialchars(t($key, $params, $locale), ENT_QUOTES, 'UTF-8');
}
