<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDBConnection();

echo "=== All mission tasks grouped by day ===\n";
$stmt = $db->query("SELECT id, task_key, title, mission_day, mission_step, verification_mode, is_active FROM mini_tasks WHERE task_group = 'mission' AND is_active = 1 ORDER BY mission_day, mission_step");
$by_day = [];
while ($r = $stmt->fetch()) {
    $day = (int) $r['mission_day'];
    if (!isset($by_day[$day])) $by_day[$day] = [];
    $by_day[$day][] = $r;
}

for ($d = 1; $d <= 10; $d++) {
    $tasks = $by_day[$d] ?? [];
    echo "\nDay $d (" . count($tasks) . " tasks):\n";
    foreach ($tasks as $t) {
        echo "  Step {$t['mission_step']}: {$t['task_key']} - {$t['title']} (vm: {$t['verification_mode']})\n";
    }
    if (empty($tasks)) {
        echo "  ** NO TASKS **\n";
    }
}
