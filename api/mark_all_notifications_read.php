<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $actor = apiGetAuthenticatedUser();
    $requested_type = strtolower(trim((string) ($_POST['recipient_type'] ?? $_GET['recipient_type'] ?? '')));
    if ($actor['type'] === 'admin') {
        $recipient_type = 'admin';
        $recipient_id = (int) ($actor['admin_id'] ?? 0);
    } else {
        $default_type = ((string) ($actor['role'] ?? '') === 'developer') ? 'developer' : 'user';
        $recipient_type = in_array($requested_type, ['developer', 'user'], true) ? $requested_type : $default_type;
        $recipient_id = (int) ($actor['user_id'] ?? 0);
    }

    $updated = markAllNotificationsAsRead($recipient_type, $recipient_id);
    apiSuccessResponse([
        'updated' => $updated,
        'unread_count' => getUnreadNotificationCount($recipient_type, $recipient_id),
        'counts' => getNotificationCounts($recipient_type, $recipient_id),
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
