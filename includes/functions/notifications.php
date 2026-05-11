<?php
/** In-app notification helpers */

function notificationsTableExists(PDO $db = null) {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $db = $db ?: getDBConnection();
    $cached = tableExists('notifications') && tableExists('notification_templates');
    return $cached;
}

function notificationNormalizeRecipientType($recipient_type) {
    $recipient_type = strtolower(trim((string) $recipient_type));
    return in_array($recipient_type, ['user', 'admin', 'developer'], true) ? $recipient_type : 'user';
}

function notificationNormalizePriority($priority) {
    $priority = strtolower(trim((string) $priority));
    return in_array($priority, ['low', 'normal', 'high'], true) ? $priority : 'normal';
}

function notificationNormalizeStatusFilter($status) {
    $status = strtolower(trim((string) $status));
    return in_array($status, ['all', 'read', 'unread'], true) ? $status : 'all';
}

function notificationResolveActionUrl($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }

    $normalized = str_replace('\\', '/', $url);
    if (preg_match('#(^|/)claims\.php($|\?)#i', $normalized)) {
        return BASE_URL . '/reward-history.php';
    }

    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    if (strpos($url, '/') === 0) {
        return rtrim(BASE_URL, '/') . $url;
    }

    return rtrim(BASE_URL, '/') . '/' . ltrim($url, '/');
}

function notificationTimeAgo($datetime) {
    $ts = strtotime((string) $datetime);
    if ($ts <= 0) {
        return 'just now';
    }
    $diff = time() - $ts;

    // Some environments store created_at in a different timezone,
    // which can make $diff negative and force every item to "just now".
    // Normalize to absolute distance so users still see real relative timing.
    if ($diff < 0) {
        $diff = abs($diff);
    }

    if ($diff < 60) {
        return 'Just now';
    }

    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return $mins . ' min' . ($mins === 1 ? '' : 's') . ' ago';
    }

    if ($diff < 86400) {
        $hrs = (int) floor($diff / 3600);
        return $hrs . ' hr' . ($hrs === 1 ? '' : 's') . ' ago';
    }

    if ($diff < 172800) {
        return 'Yesterday';
    }

    if ($diff < 604800) {
        $days = (int) floor($diff / 86400);
        return $days . ' days ago';
    }

    return date('M j, Y', $ts);
}

function renderNotificationTemplateText($template, array $vars = []) {
    $text = (string) $template;
    foreach ($vars as $key => $value) {
        $text = str_replace('{{' . $key . '}}', (string) $value, $text);
    }
    return trim($text);
}

function getNotificationTemplate($template_key, PDO $db = null) {
    $db = $db ?: getDBConnection();
    if (!notificationsTableExists($db)) {
        return null;
    }

    $stmt = $db->prepare("SELECT * FROM notification_templates WHERE template_key = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([trim((string) $template_key)]);
    return $stmt->fetch() ?: null;
}

function createNotification($recipient_type, $recipient_id, array $payload, PDO $db = null) {
    $db = $db ?: getDBConnection();
    if (!notificationsTableExists($db)) {
        return null;
    }

    $recipient_type = notificationNormalizeRecipientType($recipient_type);
    $recipient_id = (int) $recipient_id;
    if ($recipient_id <= 0) {
        return null;
    }

    $title = substr(trim((string) ($payload['title'] ?? 'Notification')), 0, 180);
    $message = trim((string) ($payload['message'] ?? ''));
    if ($message === '') {
        return null;
    }

    $stmt = $db->prepare("INSERT INTO notifications (recipient_type, recipient_id, actor_type, actor_id, template_key, event_key, title, message, action_url, meta_json, priority, is_read, read_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)");
    $stmt->execute([
        $recipient_type,
        $recipient_id,
        isset($payload['actor_type']) ? substr(trim((string) $payload['actor_type']), 0, 30) : null,
        isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
        isset($payload['template_key']) ? substr(trim((string) $payload['template_key']), 0, 120) : null,
        substr(trim((string) ($payload['event_key'] ?? 'generic.event')), 0, 120),
        $title,
        $message,
        isset($payload['action_url']) ? substr(trim((string) $payload['action_url']), 0, 255) : null,
        !empty($payload['meta']) ? json_encode($payload['meta'], JSON_UNESCAPED_UNICODE) : null,
        notificationNormalizePriority((string) ($payload['priority'] ?? 'normal')),
    ]);

    return (int) $db->lastInsertId();
}

function createTemplatedNotification($template_key, $recipient_type, $recipient_id, array $vars = [], array $overrides = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $template = getNotificationTemplate($template_key, $db);
    if (!$template) {
        return null;
    }

    $payload = [
        'template_key' => (string) $template['template_key'],
        'event_key' => (string) ($overrides['event_key'] ?? $template['template_key']),
        'title' => renderNotificationTemplateText((string) $template['title_template'], $vars),
        'message' => renderNotificationTemplateText((string) $template['message_template'], $vars),
        'action_url' => notificationResolveActionUrl($overrides['action_url'] ?? $template['default_action_url'] ?? null),
        'priority' => $overrides['priority'] ?? $template['default_priority'] ?? 'normal',
        'actor_type' => $overrides['actor_type'] ?? null,
        'actor_id' => $overrides['actor_id'] ?? null,
        'meta' => $overrides['meta'] ?? ['vars' => $vars],
    ];

    return createNotification($recipient_type, $recipient_id, $payload, $db);
}

function getUnreadNotificationCount($recipient_type, $recipient_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    if (!notificationsTableExists($db)) {
        return 0;
    }
    $stmt = $db->prepare("SELECT COUNT(*) AS total FROM notifications WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0");
    $stmt->execute([notificationNormalizeRecipientType($recipient_type), (int) $recipient_id]);
    return (int) ($stmt->fetch()['total'] ?? 0);
}

function getNotificationCounts($recipient_type, $recipient_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    if (!notificationsTableExists($db)) {
        return ['all' => 0, 'read' => 0, 'unread' => 0];
    }

    $stmt = $db->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread_total FROM notifications WHERE recipient_type = ? AND recipient_id = ?");
    $stmt->execute([notificationNormalizeRecipientType($recipient_type), (int) $recipient_id]);
    $row = $stmt->fetch() ?: [];

    $all = (int) ($row['total'] ?? 0);
    $unread = (int) ($row['unread_total'] ?? 0);

    return [
        'all' => $all,
        'read' => max(0, $all - $unread),
        'unread' => $unread,
    ];
}

function getNotifications($recipient_type, $recipient_id, $limit = 10, PDO $db = null) {
    $db = $db ?: getDBConnection();
    if (!notificationsTableExists($db)) {
        return [];
    }
    $limit = max(1, min(50, (int) $limit));
    $stmt = $db->prepare("SELECT id, title, message, action_url, priority, is_read, created_at FROM notifications WHERE recipient_type = ? AND recipient_id = ? ORDER BY created_at DESC, id DESC LIMIT {$limit}");
    $stmt->execute([notificationNormalizeRecipientType($recipient_type), (int) $recipient_id]);
    $rows = $stmt->fetchAll() ?: [];
    foreach ($rows as &$row) {
        $row['action_url'] = notificationResolveActionUrl($row['action_url'] ?? null);
        $row['time_ago'] = notificationTimeAgo($row['created_at'] ?? null);
    }
    unset($row);
    return $rows;
}

function getNotificationsPaged($recipient_type, $recipient_id, $page = 1, $per_page = 20, $status = 'all', PDO $db = null) {
    $db = $db ?: getDBConnection();
    if (!notificationsTableExists($db)) {
        return ['items' => [], 'total' => 0, 'page' => 1, 'per_page' => 20];
    }

    $page = max(1, (int) $page);
    $per_page = max(1, min(100, (int) $per_page));
    $status = notificationNormalizeStatusFilter($status);
    $offset = ($page - 1) * $per_page;
    $recipient_type = notificationNormalizeRecipientType($recipient_type);
    $recipient_id = (int) $recipient_id;

    $where = "recipient_type = ? AND recipient_id = ?";
    $params = [$recipient_type, $recipient_id];
    if ($status === 'read') {
        $where .= " AND is_read = 1";
    } elseif ($status === 'unread') {
        $where .= " AND is_read = 0";
    }

    $count_stmt = $db->prepare("SELECT COUNT(*) AS total FROM notifications WHERE {$where}");
    $count_stmt->execute($params);
    $total = (int) ($count_stmt->fetch()['total'] ?? 0);

    $stmt = $db->prepare("SELECT id, title, message, action_url, priority, is_read, created_at FROM notifications WHERE {$where} ORDER BY created_at DESC, id DESC LIMIT {$per_page} OFFSET {$offset}");
    $stmt->execute($params);

    $rows = $stmt->fetchAll() ?: [];
    foreach ($rows as &$row) {
        $row['action_url'] = notificationResolveActionUrl($row['action_url'] ?? null);
        $row['time_ago'] = notificationTimeAgo($row['created_at'] ?? null);
    }
    unset($row);

    return [
        'items' => $rows,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'counts' => getNotificationCounts($recipient_type, $recipient_id, $db),
    ];
}

function markAllNotificationsAsRead($recipient_type, $recipient_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    if (!notificationsTableExists($db)) {
        return 0;
    }
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0");
    $stmt->execute([notificationNormalizeRecipientType($recipient_type), (int) $recipient_id]);
    return (int) $stmt->rowCount();
}

function markNotificationAsRead($notification_id, $recipient_type, $recipient_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    if (!notificationsTableExists($db)) {
        return 0;
    }
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND recipient_type = ? AND recipient_id = ?");
    $stmt->execute([(int) $notification_id, notificationNormalizeRecipientType($recipient_type), (int) $recipient_id]);
    return (int) $stmt->rowCount();
}

function sendBroadcastNotification($audience, $template_key, array $vars = [], array $filters = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    if (!notificationsTableExists($db)) {
        return 0;
    }

    $audience = strtolower(trim((string) $audience));
    $count = 0;

    if ($audience === 'users' || $audience === 'all_users') {
        $sql = "SELECT id FROM users WHERE status = 'active'";
        $params = [];
        if (!empty($filters['level'])) {
            $sql .= " AND LOWER(COALESCE(level, 'beginner')) = ?";
            $params[] = strtolower(trim((string) $filters['level']));
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        foreach (($stmt->fetchAll() ?: []) as $row) {
            if (createTemplatedNotification($template_key, 'user', (int) $row['id'], $vars, [], $db)) {
                $count++;
            }
        }
    }

    if ($audience === 'developers' || $audience === 'all_developers') {
        $stmt = $db->query("SELECT DISTINCT user_id FROM developer_verification WHERE user_id IS NOT NULL");
        foreach (($stmt ? $stmt->fetchAll() : []) as $row) {
            if (createTemplatedNotification($template_key, 'developer', (int) $row['user_id'], $vars, [], $db)) {
                $count++;
            }
        }
    }

    return $count;
}
