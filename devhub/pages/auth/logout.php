<?php
require_once __DIR__ . '/../../includes/config.php';
logout();
header('Location: ' . BASE_URL . '/auth/auth.php?tab=login');
exit();
?>