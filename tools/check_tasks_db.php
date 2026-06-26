<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDBConnection();
$stmt = $db->query("SELECT id, task_key, title, verification_mode, requires_manual_review, mission_day, mission_step FROM mini_tasks WHERE task_group = 'mission' AND is_active = 1 ORDER BY mission_day, mission_step");
echo "Active mission tasks in DB:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: {$row['id']} | Day: {$row['mission_day']} | Step: {$row['mission_step']} | Key: {$row['task_key']} | Title: {$row['title']} | VM: {$row['verification_mode']} | Manual: {$row['requires_manual_review']}\n";
}
