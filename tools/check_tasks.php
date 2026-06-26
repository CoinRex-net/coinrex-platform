<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDBConnection();
$stmt = $db->query("SELECT id, task_key, title, mission_day, mission_step, verification_mode, requires_quiz, requires_manual_review FROM mini_tasks WHERE task_group = 'mission' AND is_active = 1 ORDER BY mission_day, mission_step");
$rows = $stmt->fetchAll();
echo "=== MISSION TASKS IN DB ===\n";
foreach ($rows as $r) {
    echo "Day {$r['mission_day']} Step {$r['mission_step']} | Key: {$r['task_key']} | Title: {$r['title']} | VM: {$r['verification_mode']} | Quiz: {$r['requires_quiz']} | Manual: {$r['requires_manual_review']}\n";
}
echo "\n=== TOTAL: " . count($rows) . " tasks ===\n";
