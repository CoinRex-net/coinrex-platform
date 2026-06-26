<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDBConnection();

// Check logs for all users on day 1
echo "=== All user_task_logs for day 1 ===\n";
$stmt = $db->query("SELECT utl.id, utl.user_id, u.username, utl.task_id, mt.task_key, mt.title, utl.status, utl.mission_day, utl.mission_step, utl.task_available_at, utl.task_completed_at FROM user_task_logs utl LEFT JOIN mini_tasks mt ON mt.id = utl.task_id LEFT JOIN users u ON u.id = utl.user_id WHERE utl.mission_day = 1 ORDER BY utl.user_id, utl.mission_step");
while ($r = $stmt->fetch()) {
    echo json_encode($r) . "\n";
}

echo "\n=== Check if day1_profile_setup exists ===\n";
$stmt = $db->query("SELECT id, task_key, title, mission_day, mission_step, is_active FROM mini_tasks WHERE task_key = 'day1_profile_setup'");
$r = $stmt->fetch();
if ($r) {
    echo "FOUND: " . json_encode($r) . "\n";
} else {
    echo "NOT FOUND - MISSING FROM DB!\n";
}

echo "\n=== Check all tasks with 'profile' in name ===\n";
$stmt = $db->query("SELECT id, task_key, title, mission_day, mission_step, is_active FROM mini_tasks WHERE title LIKE '%profile%' OR task_key LIKE '%profile%'");
while ($r = $stmt->fetch()) {
    echo json_encode($r) . "\n";
}
