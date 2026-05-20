<?php
// Clear PHP opcache
if (function_exists('opcache_reset')) {
    $result = opcache_reset();
    echo "opcache_reset() returned: " . ($result ? "true" : "false") . "\n";
} else {
    echo "opcache_reset() not available\n";
}

// Also touch the taskhub functions file to invalidate cache
$file = __DIR__ . '/includes/functions/taskhub.php';
if (file_exists($file)) {
    touch($file);
    echo "Touched: $file\n";
}

echo "Done.\n";
