<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$db = getDBConnection();
reviewEligibilityMonitoringEnsureSchema($db);

$sessions = $db->query("SELECT id
    FROM review_eligibility_monitoring_sessions
    WHERE status IN ('active','provider_delayed') AND next_check_at <= NOW()
    ORDER BY next_check_at ASC
    LIMIT 100")->fetchAll();

$processed = 0;
$failed = 0;
foreach ($sessions as $row) {
    try {
        reviewEligibilityMonitoringProcess($db, (int) $row['id']);
        $processed++;
    } catch (Throwable $e) {
        $failed++;
        error_log('Review eligibility monitor #' . (int) $row['id'] . ': ' . $e->getMessage());
    }
}

$notifications = reviewEligibilityMonitoringDeliverOutbox($db, 50);
echo json_encode([
    'processed' => $processed,
    'failed' => $failed,
    'notifications_processed' => $notifications,
    'finished_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES) . PHP_EOL;

