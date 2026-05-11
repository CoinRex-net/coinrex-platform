<?php

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
