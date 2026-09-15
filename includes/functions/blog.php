<?php

// Load Parsedown if available
$parsedownLoaded = false;
if (file_exists(__DIR__ . '/../../vendor/parsedown.php')) {
    require_once __DIR__ . '/../../vendor/parsedown.php';
    $parsedownLoaded = class_exists('Parsedown');
}

/**
 * Convert Markdown text to HTML using Parsedown (with fallback).
 */
function blogMarkdownToHtml(string $markdown): string {
    if (class_exists('Parsedown')) {
        $parsedown = new Parsedown();
        return $parsedown->text($markdown);
    }
    // Fallback: basic conversion
    return nl2br(htmlspecialchars($markdown, ENT_QUOTES, 'UTF-8'));
}

function blogSlugify($text) {
    $text = strtolower(trim((string) $text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim((string) $text, '-') ?: 'post';
}

function blogUniqueSlug(PDO $db, $title, $excludeId = 0) {
    $base = blogSlugify($title);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = 'SELECT id FROM blog_posts WHERE slug = ?' . ($excludeId > 0 ? ' AND id <> ?' : '') . ' LIMIT 1';
        $stmt = $db->prepare($sql);
        $params = [$slug];
        if ($excludeId > 0) $params[] = (int) $excludeId;
        $stmt->execute($params);
        if (!$stmt->fetch()) return $slug;
        $slug = $base . '-' . $i;
        $i++;
    }
}

function blogReadTime($content) {
    $words = str_word_count(strip_tags((string) $content));
    return max(1, (int) ceil($words / 200));
}

function blogGetLatest($limit = 3) {
    if (!tableExists('blog_posts')) return [];
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id,title,slug,excerpt,featured_image,published_at,created_at FROM blog_posts WHERE status='published' ORDER BY COALESCE(published_at, created_at) DESC LIMIT ?");
    $stmt->bindValue(1, (int) $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function blogAdPlacementOptions(): array {
    return [
        'blog_leaderboard' => 'Blog - Leaderboard',
        'blog_infeed' => 'Blog - In-feed',
        'blog_sidebar' => 'Blog - Sidebar',
        'boosthub_spotlight' => 'BoostHub - Sponsor Spotlight',
        'learnhub_spotlight' => 'LearnHub - Sponsor Spotlight',
        'dashboard_rexhub_spotlight' => 'Dashboard RexHub - Sponsor Spotlight',
    ];
}

function blogEnsureHubAdPlacements(PDO $db): bool {
    if (!tableExists('blog_ads')) return false;

    try {
        $stmt = $db->query("SHOW COLUMNS FROM blog_ads LIKE 'placement'");
        $column = $stmt ? $stmt->fetch() : null;
        $type = strtolower((string) ($column['Type'] ?? ''));
        if ($type !== '' && strpos($type, 'dashboard_rexhub_spotlight') !== false) {
            return true;
        }

        $db->exec("ALTER TABLE blog_ads MODIFY placement ENUM('blog_leaderboard','blog_infeed','blog_sidebar','boosthub_spotlight','learnhub_spotlight','dashboard_rexhub_spotlight') NOT NULL");
        return true;
    } catch (Throwable $e) {
        error_log('blog_ads placement upgrade failed: ' . $e->getMessage());
        return false;
    }
}
function blogGetAdByPlacement(PDO $db, string $placement): ?array {
    if (!tableExists('blog_ads')) return null;
    $sql = "SELECT * FROM blog_ads
            WHERE placement = ?
              AND is_active = 1
              AND (starts_at IS NULL OR starts_at <= NOW())
              AND (ends_at IS NULL OR ends_at >= NOW())
            ORDER BY priority ASC, id DESC
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$placement]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function blogRenderSponsorSpotlight(?array $ad, string $surface = 'hub', string $label = 'Sponsor spotlight'): string {
    if (!$ad) return '';

    $surface = preg_replace('/[^a-z0-9_-]/i', '', $surface) ?: 'hub';
    $type = (string) ($ad['ad_type'] ?? 'text');
    $is_media = in_array($type, ['image', 'gif'], true) && !empty($ad['media_url']);
    $target_url = trim((string) ($ad['target_url'] ?? ''));
    $title = trim((string) ($ad['title'] ?? 'Sponsored spotlight'));
    $description = trim((string) ($ad['description'] ?? ''));
    $cta_text = trim((string) ($ad['cta_text'] ?? 'Learn More'));
    $media_url = trim((string) ($ad['media_url'] ?? ''));

    ob_start();
    ?>
    <section class="sponsor-spotlight sponsor-spotlight--<?php echo htmlspecialchars($surface, ENT_QUOTES, 'UTF-8'); ?> sponsor-spotlight--<?php echo $is_media ? 'media' : 'text'; ?>" aria-label="<?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="sponsor-spotlight__badge"><i class="fas fa-rectangle-ad"></i> Sponsored</span>
        <?php if ($target_url !== ''): ?>
            <a class="sponsor-spotlight__link" href="<?php echo htmlspecialchars($target_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="nofollow sponsored noopener">
        <?php else: ?>
            <div class="sponsor-spotlight__link">
        <?php endif; ?>
            <?php if ($is_media): ?>
                <img class="sponsor-spotlight__media" src="<?php echo htmlspecialchars($media_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
            <?php else: ?>
                <div class="sponsor-spotlight__copy">
                    <strong><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <?php if ($description !== ''): ?><p><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                </div>
                <?php if ($target_url !== '' && $cta_text !== ''): ?>
                    <span class="sponsor-spotlight__cta"><?php echo htmlspecialchars($cta_text, ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-arrow-right"></i></span>
                <?php endif; ?>
            <?php endif; ?>
        <?php if ($target_url !== ''): ?>
            </a>
        <?php else: ?>
            </div>
        <?php endif; ?>
    </section>
    <?php
    return (string) ob_get_clean();
}
function blogGetRandomAdByPlacement(PDO $db, string $placement): ?array {
    if (!tableExists('blog_ads')) return null;
    $sql = "SELECT * FROM blog_ads
            WHERE placement = ?
              AND is_active = 1
              AND (starts_at IS NULL OR starts_at <= NOW())
              AND (ends_at IS NULL OR ends_at >= NOW())
            ORDER BY RAND()
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$placement]);
    $row = $stmt->fetch();
    return $row ?: null;
}
