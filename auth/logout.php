<?php
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

logoutUser();
redirect(BASE_URL . '/auth/auth.php');
?>