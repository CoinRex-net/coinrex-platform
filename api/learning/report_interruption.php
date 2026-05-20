<?php
/**
 * TaskHub Learning Session — Report Interruption
 * Called via navigator.sendBeacon() when tab is closed, page refreshed, or user navigates away.
 * POST /api/learning/report_interruption.php
 * Body: session_token, reason (optional, default: tab_closed)
 */
require_once __DIR__ . '/../_bootstrap.php';

apiRequireMethod('POST');

try {
    [$user_id] = apiResolveAuthorizedUserId(null);
    $session_token = trim((string) ($_POST['session_token'] ?? ''));
    $reason = trim((string) ($_POST['reason'] ?? 'tab_closed'));

    if ($session_token === '') {
        throw new InvalidArgumentException('Valid session_token is required.');
    }

    $allowed_reasons = ['tab_closed', 'page_refresh', 'navigation_away', 'multiple_tabs'];
    if (!in_array($reason, $allowed_reasons, true)) {
        $reason = 'tab_closed';
    }

    $db = getDBConnection();
    taskHubReportInterruption($session_token, $reason, $db);

    apiSuccessResponse([
        'message' => 'Interruption reported.',
    ]);
} catch (Throwable $e) {
    // Silent fail for sendBeacon — don't throw errors
    apiErrorResponse(422, $e->getMessage());
}
