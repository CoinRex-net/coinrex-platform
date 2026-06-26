<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDBConnection();
$stmt = $db->query('DESCRIBE mini_tasks');
echo "=== mini_tasks columns ===\n";
foreach ($stmt->fetchAll() as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ') - Default: ' . ($col['Default'] ?? 'NULL') . "\n";
}
echo "\n=== Existing mission tasks ===\n";
$stmt2 = $db->query("SELECT id, task_key, title, mission_day, mission_step, is_active FROM mini_tasks WHERE task_group = 'mission' ORDER BY mission_day, mission_step");
$rows = $stmt2->fetchAll();
if (empty($rows)) {
    echo "No mission tasks found in DB.\n";
} else {
    foreach ($rows as $r) {
        echo "ID:{$r['id']} | key:{$r['task_key']} | day:{$r['mission_day']} | step:{$r['mission_step']} | title:{$r['title']} | active:{$r['is_active']}\n";
    }
}
echo "\n=== taskhub_quiz_questions table exists? ===\n";
try {
    $stmt3 = $db->query("SELECT COUNT(*) as cnt FROM taskhub_quiz_questions");
    $cnt = $stmt3->fetch()['cnt'];
    echo "Yes, has $cnt rows.\n";
} catch (Exception $e) {
    echo "Table does not exist or error: " . $e->getMessage() . "\n";
}
