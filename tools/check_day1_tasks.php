<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDBConnection();

echo "=== Day 1 Tasks ===\n";
$stmt = $db->query("SELECT id, task_key, title, mission_day, mission_step, verification_mode, requires_manual_review, is_active FROM mini_tasks WHERE mission_day = 1 AND task_group = 'mission' ORDER BY mission_step");
while ($r = $stmt->fetch()) {
    echo json_encode($r) . "\n";
}

echo "\n=== User Task Logs for user 1 (day 1) ===\n";
$stmt = $db->prepare("SELECT utl.id, utl.task_id, mt.task_key, mt.title, utl.status, utl.mission_day, utl.mission_step, utl.task_available_at, utl.task_completed_at, utl.completed_at, utl.proof_data, utl.metadata FROM user_task_logs utl LEFT JOIN mini_tasks mt ON mt.id = utl.task_id WHERE utl.user_id = ? AND utl.mission_day = 1 ORDER BY utl.mission_step");
$stmt->execute([1]);
while ($r = $stmt->fetch()) {
    echo json_encode($r) . "\n";
}
