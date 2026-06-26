<?php
/**
 * Compatibility shim for legacy roadmap links.
 * Main page: /public/roadmap.php
 */

require_once __DIR__ . '/includes/config.php';

redirect(BASE_URL . '/public/roadmap.php');
