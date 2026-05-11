<?php
require_once __DIR__ . '/_bootstrap.php';

try {
    $actor = apiGetAuthenticatedUser();
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = max(1, min(50, (int) ($_GET['per_page'] ?? 20)));
    $status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
    if (!in_array($status, ['all', 'read', 'unread'], true)) {
        $status = 'all';
    }

    if ($actor['type'] === 'admin') {
        $recipient_type = 'admin';
        $recipient_id = (int) ($actor['admin_id'] ?? 0);
    } else {
        $recipient_type = ((string) ($actor['role'] ?? '') === 'developer') ? 'developer' : 'user';
        $recipient_id = (int) ($actor['user_id'] ?? 0);
    }

    $data = getNotificationsPaged($recipient_type, $recipient_id, $page, $per_page, $status);
    apiSuccessResponse(array_merge($data, [
        'unread_count' => (int) ($data['counts']['unread'] ?? 0),
    ]));
} catch (Throwable $e) {
    apiErrorResponse(422, $e->getMessage());
}
