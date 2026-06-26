<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDBConnection();

echo "=== ALL mini_tasks rows ===\n";
$stmt = $db->query("SELECT id, task_key, title, task_group, mission_day, mission_step, is_active FROM mini_tasks ORDER BY task_group, mission_day, mission_step");
$rows = $stmt->fetchAll();
foreach ($rows as $r) {
    echo "ID:{$r['id']} | Group:{$r['task_group']} | Day:{$r['mission_day']} Step:{$r['mission_step']} | Key:{$r['task_key']} | Title:{$r['title']} | Active:{$r['is_active']}\n";
}
echo "\n=== TOTAL: " . count($rows) . " rows ===\n";

echo "\n=== Checking taskhub_quiz_questions ===\n";
$stmt2 = $db->query("SELECT COUNT(*) as cnt FROM taskhub_quiz_questions");
$r2 = $stmt2->fetch();
echo "Quiz questions count: " . $r2['cnt'] . "\n";

echo "\n=== Checking user_task_logs ===\n";
$stmt3 = $db->query("SELECT COUNT(*) as cnt FROM user_task_logs");
$r3 = $stmt3->fetch();
echo "User task logs count: " . $r3['cnt'] . "\n";
