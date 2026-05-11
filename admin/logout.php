<?php
require_once __DIR__ . '/includes/config.php';
adminLogout();
header('Location: ' . ADMIN_BASE_URL . '/login.php');
exit();
?>
