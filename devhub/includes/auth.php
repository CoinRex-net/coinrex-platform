<?php
/**
 * CoinRex DevHub Authentication - SIMPLE VERSION
 * Location: /coinrex/devhub/includes/auth.php
 */

if (defined('DEVHUB_AUTH_LOADED')) {
    return;
}
define('DEVHUB_AUTH_LOADED', true);

function isDevHubLoggedIn() {
    return isset($_SESSION[DEVHUB_SESSION_KEY]) && $_SESSION[DEVHUB_SESSION_KEY] === true;
}

function requireDevLogin() {
    if (!isDevHubLoggedIn()) {
        header('Location: ' . BASE_URL . '/devhub/pages/auth/login.php');
        exit();
    }
}

function devLogin($user_id, $username, $role) {
    $_SESSION[DEVHUB_SESSION_KEY] = true;
    $_SESSION[DEVHUB_USER_ID] = $user_id;
    $_SESSION[DEVHUB_USERNAME] = $username;
    $_SESSION[DEVHUB_ROLE] = $role;
}

function devLogout() {
    unset($_SESSION[DEVHUB_SESSION_KEY]);
    unset($_SESSION[DEVHUB_USER_ID]);
    unset($_SESSION[DEVHUB_USERNAME]);
    unset($_SESSION[DEVHUB_ROLE]);
}
?>