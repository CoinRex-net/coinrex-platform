<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDBConnection();

echo "=== Quiz Questions ===\n";
$stmt = $db->query("SELECT * FROM taskhub_quiz_questions ORDER BY task_key, sort_order");
$rows = $stmt->fetchAll();
foreach ($rows as $r) {
    echo "ID:{$r['id']} | TaskKey:{$r['task_key']} | Q:{$r['question']} | Active:{$r['is_active']}\n";
}
echo "\nTotal: " . count($rows) . "\n";

echo "\n=== mini_tasks table structure ===\n";
$stmt2 = $db->query("DESCRIBE mini_tasks");
$cols = $stmt2->fetchAll();
foreach ($cols as $c) {
    echo "{$c['Field']} | {$c['Type']} | Null:{$c['Null']} | Default:{$c['Default']}\n";
}
