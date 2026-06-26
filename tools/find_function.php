<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions/taskhub.php';

$ref = new ReflectionFunction('getTaskHubState');
echo 'getTaskHubState starts at line: ' . $ref->getStartLine() . ' ends at: ' . $ref->getEndLine() . PHP_EOL;
