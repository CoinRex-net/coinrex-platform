<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDBConnection();

// 1. Delete all mission tasks
$db->exec("DELETE FROM mini_tasks WHERE task_group = 'mission'");

// 2. Delete all related user_task_logs
$db->exec("DELETE FROM user_task_logs");

// 3. Reset all users' current_day to 1
$db->exec("UPDATE users SET current_day = 1, last_day_completed_at = NULL, updated_at = NOW()");

// 4. Clear quiz attempts
$db->exec("DELETE FROM taskhub_quiz_attempts");

// 5. Clear learning sessions
$db->exec("DELETE FROM taskhub_learning_sessions");

echo "All mission tasks and user progress cleared.\n";
