<?php
$original = file_get_contents(__DIR__ . '/../includes/functions/taskhub_original.php');
$current = file_get_contents(__DIR__ . '/../includes/functions/taskhub.php');
echo 'Original lines: ' . substr_count($original, "\n") . "\n";
echo 'Current lines: ' . substr_count($current, "\n") . "\n";

// Check what functions are in original but not in current
preg_match_all('/function (\w+)/', $original, $orig_matches);
preg_match_all('/function (\w+)/', $current, $curr_matches);

$missing = array_diff($orig_matches[1], $curr_matches[1]);
echo "Missing functions:\n";
print_r($missing);

$extra = array_diff($curr_matches[1], $orig_matches[1]);
echo "Extra functions (new):\n";
print_r($extra);
