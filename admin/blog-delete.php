<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . ADMIN_BASE_URL . '/blog.php');
    exit();
}

requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . ADMIN_BASE_URL . '/blog.php');
    exit();
}

$db = getDBConnection();
$db->beginTransaction();
try {
    $db->prepare("DELETE FROM blog_post_categories WHERE post_id=?")->execute([$id]);
    $db->prepare("DELETE FROM blog_post_tags WHERE post_id=?")->execute([$id]);
    $db->prepare("DELETE FROM blog_posts WHERE id=? LIMIT 1")->execute([$id]);
    $db->commit();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

header('Location: ' . ADMIN_BASE_URL . '/blog.php');
exit();
