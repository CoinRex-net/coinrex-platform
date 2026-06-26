<?php
/** Auto-split from legacy functions.php */

function getBoostHubStateForUser($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['status' => 'closed', 'message' => 'Invalid user.', 'task' => null, 'unlock_at' => null, 'countdown_seconds' => 0];
    }

    $user = getUserById($user_id);
    if (!$user) {
        return ['status' => 'closed', 'message' => 'User account not found.', 'task' => null, 'unlock_at' => null, 'countdown_seconds' => 0];
    }

    // BoostHub is available to all users — no profile/account-age gates

    $pending_stmt = $db->prepare("
        SELECT utl.*, mt.*
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.user_id = ?
          AND utl.status = 'pending'
          AND mt.task_group = 'boosthub'
          AND mt.is_active = 1
        ORDER BY utl.id DESC
        LIMIT 1
    ");
    $pending_stmt->execute([$user_id]);
    $pending = $pending_stmt->fetch();
    if ($pending) {
        return [
            'status' => 'open',
            'message' => 'Assigned task ready.',
            'task' => $pending,
            'unlock_at' => null,
            'countdown_seconds' => 0,
        ];
    }

    $submitted_stmt = $db->prepare("
        SELECT utl.id, utl.metadata
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.user_id = ?
          AND utl.status = 'submitted'
          AND mt.task_group = 'boosthub'
          AND mt.is_active = 1
        ORDER BY utl.id DESC
        LIMIT 1
    ");
    $submitted_stmt->execute([$user_id]);
    $submitted = $submitted_stmt->fetch();
    if ($submitted) {
        // Extract submitted_at from metadata JSON (stored when user submits evidence)
        $metadata = !empty($submitted['metadata']) ? (is_string($submitted['metadata']) ? json_decode($submitted['metadata'], true) : $submitted['metadata']) : [];
        $submitted_at = (string) ($metadata['submitted_at'] ?? '');
        $unlock_ts = $submitted_at !== '' ? strtotime($submitted_at . ' +24 hours') : 0;
        $countdown = ($unlock_ts && $unlock_ts > time()) ? max(0, $unlock_ts - time()) : 0;
        return [
            'status' => 'awaiting_review',
            'message' => 'Evidence submitted successfully. Reward will be credited after admin approval.',
            'task' => null,
            'unlock_at' => $unlock_ts ? date('Y-m-d H:i:s', $unlock_ts) : null,
            'countdown_seconds' => $countdown,
        ];
    }

    // TESTING_MODE: Skip 24h cooldown between BoostHub tasks
    if (!defined('TESTING_MODE') || !TESTING_MODE) {
        $last_completed_stmt = $db->prepare("
            SELECT MAX(COALESCE(utl.task_completed_at, utl.completed_at)) AS completed_at
            FROM user_task_logs utl
            INNER JOIN mini_tasks mt ON mt.id = utl.task_id
            WHERE utl.user_id = ?
              AND utl.status = 'completed'
              AND mt.task_group = 'boosthub'
        ");
        $last_completed_stmt->execute([$user_id]);
        $last_completed_at = (string) ($last_completed_stmt->fetch()['completed_at'] ?? '');
        if ($last_completed_at !== '') {
            $unlock_ts = strtotime($last_completed_at . ' +24 hours');
            if ($unlock_ts > time()) {
                return [
                    'status' => 'locked',
                    'message' => 'Next task unlocks after 24 hours.',
                    'task' => null,
                    'unlock_at' => date('Y-m-d H:i:s', $unlock_ts),
                    'countdown_seconds' => max(0, $unlock_ts - time()),
                ];
            }
        }
    }

    $assign_stmt = $db->prepare("
        SELECT mt.*
        FROM mini_tasks mt
        WHERE mt.task_group = 'boosthub'
          AND mt.is_active = 1
          AND mt.id NOT IN (
              SELECT DISTINCT utl.task_id
              FROM user_task_logs utl
              INNER JOIN mini_tasks mt2 ON mt2.id = utl.task_id
              WHERE utl.user_id = ?
                AND mt2.task_group = 'boosthub'
                AND utl.status = 'completed'
          )
        ORDER BY RAND()
        LIMIT 1
    ");
    $assign_stmt->execute([$user_id]);
    $task = $assign_stmt->fetch();
    if (!$task) {
        return [
            'status' => 'finished',
            'message' => 'No new BoostHub tasks available right now.',
            'task' => null,
            'unlock_at' => null,
            'countdown_seconds' => 0,
        ];
    }

    taskHubInsertLog($user_id, (int) $task['id'], 'pending', [
        'task_available_at' => date('Y-m-d H:i:s'),
        'metadata' => ['boosthub_assigned' => 1],
    ], $db);

    return [
        'status' => 'open',
        'message' => 'Assigned task ready.',
        'task' => $task,
        'unlock_at' => null,
        'countdown_seconds' => 0,
    ];
}
