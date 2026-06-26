<?php
$db = new PDO('mysql:host=localhost;dbname=koinrex;charset=utf8mb4', 'root', '');

// Check if day_title exists
$stmt = $db->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'koinrex' AND TABLE_NAME = 'mini_tasks' AND COLUMN_NAME = 'day_title'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row['cnt'] == 0) {
    $db->exec("ALTER TABLE mini_tasks ADD COLUMN day_title VARCHAR(255) NOT NULL DEFAULT '' AFTER task_key");
    echo "Added day_title column.\n";
} else {
    echo "day_title column already exists.\n";
}

// Check if required_reading_seconds exists
$stmt = $db->query("SELECT COUNT(*) as cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'koinrex' AND TABLE_NAME = 'mini_tasks' AND COLUMN_NAME = 'required_reading_seconds'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row['cnt'] == 0) {
    $db->exec("ALTER TABLE mini_tasks ADD COLUMN required_reading_seconds INT UNSIGNED NOT NULL DEFAULT 45 AFTER learning_url");
    echo "Added required_reading_seconds column.\n";
} else {
    echo "required_reading_seconds column already exists.\n";
}

// Verify
$stmt = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = 'koinrex' AND TABLE_NAME = 'mini_tasks' ORDER BY ORDINAL_POSITION");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($results as $col) {
    printf("%-30s %-20s %-10s\n", $col['COLUMN_NAME'], $col['COLUMN_TYPE'], $col['COLUMN_DEFAULT'] ?? 'NULL');
}
