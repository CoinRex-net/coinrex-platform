<?php
$db = new PDO('mysql:host=localhost;dbname=koinrex;charset=utf8mb4', 'root', '');
$stmt = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
                     FROM information_schema.COLUMNS 
                     WHERE TABLE_SCHEMA = 'koinrex' AND TABLE_NAME = 'mini_tasks'
                     ORDER BY ORDINAL_POSITION");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($results as $row) {
    printf("%-30s %-20s %-8s %-10s\n", $row['COLUMN_NAME'], $row['COLUMN_TYPE'], $row['IS_NULLABLE'], $row['COLUMN_DEFAULT'] ?? 'NULL');
}
