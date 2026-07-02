<?php
define('COINREX_SKIP_SESSION_INIT', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$baseUrl = defined('PUBLIC_BASE_URL') ? rtrim(PUBLIC_BASE_URL, '/') : rtrim(BASE_URL, '/');
$urls = [];
$canonicalBlogSlugMap = [
    'what-is-blockchain-simple-guide-coinrex-beginners' => 'what-is-blockchain-a-simple-guide-for-coinrex-beginners',
];

$addUrl = static function ($loc, $priority = '0.70', $changefreq = 'weekly', $lastmod = null) use (&$urls, $baseUrl) {
    $path = '/' . ltrim((string) $loc, '/');
    $urls[$path] = [
        'loc' => $baseUrl . ($path === '/' ? '' : $path),
        'priority' => $priority,
        'changefreq' => $changefreq,
        'lastmod' => $lastmod ?: date('Y-m-d'),
    ];
};

$addUrl('/', '1.00', 'daily');
$addUrl('/public/projects.php', '0.90', 'daily');
$addUrl('/public/reviews.php', '0.85', 'daily');
$addUrl('/public/blog.php', '0.80', 'daily');
$addUrl('/public/taskhub.php', '0.75', 'weekly');
$addUrl('/public/boosthub.php', '0.70', 'weekly');
$addUrl('/public/litepaper.php', '0.70', 'monthly');
$addUrl('/public/roadmap.php', '0.65', 'monthly');
$addUrl('/public/sponsored-apply.php', '0.60', 'monthly');
$addUrl('/public/rex-signer.php', '0.60', 'monthly');
$addUrl('/public/about.php', '0.55', 'monthly');
$addUrl('/public/contact.php', '0.50', 'monthly');
$addUrl('/public/faq.php', '0.50', 'monthly');
$addUrl('/public/privacy.php', '0.35', 'yearly');
$addUrl('/public/terms.php', '0.35', 'yearly');
$addUrl('/public/cookies.php', '0.30', 'yearly');

try {
    $db = getDBConnection();

    $projectStmt = $db->query("SELECT id, updated_at, created_at FROM projects WHERE approval_status = 'approved' ORDER BY id DESC LIMIT 1000");
    if ($projectStmt) {
        foreach ($projectStmt->fetchAll() ?: [] as $project) {
            $lastmod = substr((string) ($project['updated_at'] ?? $project['created_at'] ?? ''), 0, 10);
            $addUrl('/public/project-detail.php?id=' . (int) $project['id'], '0.80', 'weekly', $lastmod ?: null);
        }
    }

    $blogStmt = $db->query("SELECT slug, updated_at, published_at, created_at FROM blog_posts WHERE status = 'published' ORDER BY COALESCE(published_at, created_at) DESC LIMIT 1000");
    if ($blogStmt) {
        foreach ($blogStmt->fetchAll() ?: [] as $post) {
            $slug = trim((string) ($post['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }

            if (isset($canonicalBlogSlugMap[$slug])) {
                continue;
            }

            $lastmod = substr((string) ($post['updated_at'] ?? $post['published_at'] ?? $post['created_at'] ?? ''), 0, 10);
            $addUrl('/blog-post.php/' . rawurlencode($slug), '0.75', 'weekly', $lastmod ?: null);
        }
    }
} catch (Throwable $e) {
    error_log('Sitemap dynamic URL load failed: ' . $e->getMessage());
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?php echo htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8'); ?></loc>
    <lastmod><?php echo htmlspecialchars($url['lastmod'], ENT_XML1, 'UTF-8'); ?></lastmod>
    <changefreq><?php echo htmlspecialchars($url['changefreq'], ENT_XML1, 'UTF-8'); ?></changefreq>
    <priority><?php echo htmlspecialchars($url['priority'], ENT_XML1, 'UTF-8'); ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
