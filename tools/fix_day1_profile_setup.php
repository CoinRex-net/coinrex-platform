<?php
/**
 * Re-insert the missing day1_profile_setup task (step 1 of Day 1).
 */
require_once __DIR__ . '/../includes/config.php';
$db = getDBConnection();

// Check if it already exists
$stmt = $db->prepare("SELECT id FROM mini_tasks WHERE task_key = ?");
$stmt->execute(['day1_profile_setup']);
$existing = $stmt->fetch();

if ($existing) {
    echo "day1_profile_setup already exists (ID: {$existing['id']}). Making it active.\n";
    $db->prepare("UPDATE mini_tasks SET is_active = 1, mission_day = 1, mission_step = 1, task_group = 'mission' WHERE id = ?")
       ->execute([(int) $existing['id']]);
} else {
    echo "Inserting day1_profile_setup...\n";
    $stmt = $db->prepare("
        INSERT INTO mini_tasks (task_key, title, description, reward, mission_day, mission_step, task_group, verification_mode, requires_manual_review, is_active, task_category, cta_label, completion_steps, proof_notes, daily_limit, cooldown_seconds, unlock_after_hours)
        VALUES (?, ?, ?, ?, ?, ?, 'mission', 'profile', 0, 1, 'profile', 'Complete Profile', ?, ?, 1, 0, 0)
    ");
    $stmt->execute([
        'day1_profile_setup',
        'Complete Your Profile',
        'Fill in your profile details to personalize your experience and unlock rewards.',
        0.0,
        1,
        1,
        "1. Go to your Profile page.\n2. Add your full name, bio, and profile picture.\n3. Save your changes.",
        'This step auto-validates when your profile is complete.',
    ]);
    echo "Inserted with ID: " . $db->lastInsertId() . "\n";
}

echo "\nDone!\n";
