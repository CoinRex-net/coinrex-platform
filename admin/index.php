<?php
require_once __DIR__ . '/includes/config.php';

if (isAdminLoggedIn()) {
    header('Location: ' . ADMIN_BASE_URL . '/dashboard.php');
    exit();
}

header('Location: ' . ADMIN_BASE_URL . '/login.php');
exit();
?>
