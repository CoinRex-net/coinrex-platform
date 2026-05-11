<?php
require_once __DIR__ . '/_bootstrap.php';

apiRequireMethod('POST');

try {
    $actor = apiGetAuthenticatedUser();
    $notification_id = (int) ($_POST['notification_id'] ?? 0);
    if ($notification_id <= 0) {
        throw new InvalidArgumentException('Valid notification_id is required.');
    }

    if ($actor['type'] === 'admin') {
        $recipient_type = 'admin';
        $recipient_id = (int) ($actor['admin_id'] ?? 0);
    } else {
        $recipient_type = ((string) ($actor['role'] ?? '') === 'developer') ? 'developer' : 'user';
        $recipient_id = (int) ($actor['user_id'] ?? 0);
    }

    $updated = markNotificationAsRead($notification_id, $recipient_type, $recipient_id);
    apiSuccessResponse([
        'updated' => $updated > 0,
        'updated_count' => (int) $updated,
        'notification_id' => $notification_id,
        'unread_count' => getUnreadNotificationCount($recipient_type, $recipient_id),
        'counts' => getNotificationCounts($recipient_type, $recipient_id),
    ]);
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
