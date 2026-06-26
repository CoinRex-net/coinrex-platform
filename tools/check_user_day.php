<?php
require_once __DIR__ . '/../includes/config.php';
$db = getDBConnection();

echo "=== User 1 ===\n";
$stmt = $db->prepare("SELECT id, username, email, current_day, level FROM users WHERE id = ?");
$stmt->execute([1]);
print_r($stmt->fetch());

echo "\n=== All users ===\n";
$stmt = $db->query("SELECT id, username, email, current_day, level FROM users ORDER BY id");
while ($r = $stmt->fetch()) {
    echo json_encode($r) . "\n";
}
