<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/functions.php';

$db = getDBConnection();
$has_slug = tableHasColumn('projects', 'slug');

if (!$has_slug) {
    echo "NO_SLUG_COLUMN";
    exit;
}

$stmt = $db->query("SELECT id, name, slug, approval_status FROM projects ORDER BY id DESC LIMIT 20");
$rows = $stmt->fetchAll();

if (!$rows) {
    echo "NO_PROJECTS";
    exit;
}

foreach ($rows as $row) {
    echo implode(' | ', [
        (int) ($row['id'] ?? 0),
        (string) ($row['name'] ?? ''),
        (string) ($row['slug'] ?? ''),
        (string) ($row['approval_status'] ?? ''),
    ]) . PHP_EOL;
}