<?php
/**
 * CoinRex Admin Configuration
 * Location: /coinrex/admin/includes/config.php
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/services/RbacService.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/guard.php';

if (!defined('ADMIN_BASE_URL')) {
    define('ADMIN_BASE_URL', BASE_URL . '/admin');
}

if (!defined('ADMIN_SESSION_ID_KEY')) {
    define('ADMIN_SESSION_ID_KEY', 'admin_id');
}

if (!defined('ADMIN_SESSION_EMAIL_KEY')) {
    define('ADMIN_SESSION_EMAIL_KEY', 'admin_email');
}

if (!defined('ADMIN_SESSION_NAME_KEY')) {
    define('ADMIN_SESSION_NAME_KEY', 'admin_name');
}
?>
