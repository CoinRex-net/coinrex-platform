<?php
require 'includes/config.php';
$db = new PDO('mysql:host=localhost;dbname=koinrex', 'root', '');
$result = $db->query("SELECT nav_key, label, badge_text, item_type, children_section_key FROM navigation_controls WHERE is_system = 0");
while ($row = $result->fetch_assoc()) {
    if ($row['badge_text'] !== '') {
        echo 'Key: ' . $row['nav_key'] . ', Label: ' . $row['label'] . ', Badge: ' . $row['badge_text'] . ', Type: ' . $row['item_type'] . ', Children: ' . $row['children_section_key'] . "\n";
    }
}
?>