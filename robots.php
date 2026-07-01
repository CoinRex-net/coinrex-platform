<?php
define('COINREX_SKIP_SESSION_INIT', true);
require_once __DIR__ . '/includes/config.php';

$baseUrl = defined('PUBLIC_BASE_URL') ? rtrim(PUBLIC_BASE_URL, '/') : rtrim(BASE_URL, '/');

header('Content-Type: text/plain; charset=UTF-8');
?>
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /api/
Disallow: /auth/
Disallow: /cache/
Disallow: /contracts/
Disallow: /database/
Disallow: /deployments/
Disallow: /docs/
Disallow: /includes/
Disallow: /node_modules/
Disallow: /realtime/
Disallow: /rexlink-service/
Disallow: /scripts/
Disallow: /src/
Disallow: /tests/
Disallow: /uploads/
Disallow: /vendor/
Disallow: /*?th_embed=
Disallow: /*?status=

Sitemap: <?php echo $baseUrl; ?>/sitemap.xml
