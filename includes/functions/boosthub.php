<?php
/** Auto-split from legacy functions.php */

/**
 * Get BoostHub state for a user.
 * Now returns pending/submitted tasks SEPARATELY from the main task flow,
 * so users can always get new tasks according to timing.
 */
function getBoostHubStateForUser($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return ['status' => 'closed', 'message' => 'Invalid user.', 'task' => null, 'unlock_at' => null, 'countdown_seconds' => 0, 'pending_task' => null, 'submitted_task' => null, 'has_pending_review' => false, 'has_returned_task' => false];
    }

    $user = getUserById($user_id);
    if (!$user) {
        return ['status' => 'closed', 'message' => 'User account not found.', 'task' => null, 'unlock_at' => null, 'countdown_seconds' => 0, 'pending_task' => null, 'submitted_task' => null, 'has_pending_review' => false, 'has_returned_task' => false];
    }

    // BoostHub is available to all users — no profile/account-age gates

    // ── 1. Check for returned/correction tasks (non-blocking) ──
    $pending_task = null;
    $revision_stmt = $db->prepare("
        SELECT utl.*, mt.*
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.user_id = ?
          AND utl.status = 'failed'
          AND mt.task_group = 'boosthub'
          AND mt.is_active = 1
        ORDER BY utl.id DESC
        LIMIT 1
    ");
    $revision_stmt->execute([$user_id]);
    $revision = $revision_stmt->fetch();
    if ($revision) {
        $metadata = !empty($revision['metadata']) ? (json_decode((string) $revision['metadata'], true) ?: []) : [];
        if (!empty($metadata['correction_requested'])) {
            $pending_task = $revision;
        }
    }

    // ── 2. Check for submitted/awaiting review tasks (non-blocking) ──
    $submitted_task = null;
    $submitted_stmt = $db->prepare("
        SELECT utl.*, mt.*
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
        $submitted_task = $submitted;
    }

    // ── 3. Check for pending (assigned but not yet submitted) tasks ──
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
        // Return the pending task as the current task
        return [
            'status' => 'open',
            'message' => 'Assigned task ready.',
            'task' => $pending,
            'unlock_at' => null,
            'countdown_seconds' => 0,
            'pending_task' => $pending_task,
            'submitted_task' => $submitted_task,
            'has_pending_review' => !empty($submitted_task),
            'has_returned_task' => !empty($pending_task),
        ];
    }

    // ── 4. Check 24h cooldown from last completed task ──
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
                    'pending_task' => $pending_task,
                    'submitted_task' => $submitted_task,
                    'has_pending_review' => !empty($submitted_task),
                    'has_returned_task' => !empty($pending_task),
                ];
            }
        }
    }

    // ── 5. Assign a new random task ──
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
            'pending_task' => $pending_task,
            'submitted_task' => $submitted_task,
            'has_pending_review' => !empty($submitted_task),
            'has_returned_task' => !empty($pending_task),
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
        'pending_task' => $pending_task,
        'submitted_task' => $submitted_task,
        'has_pending_review' => !empty($submitted_task),
        'has_returned_task' => !empty($pending_task),
    ];
}

/**
 * Check if the user has already submitted the same evidence text
 * for the same task category (anti-abuse filter).
 */
function boostHubCheckDuplicateEvidence($user_id, $task_category, $evidence_text, PDO $db = null) {
    $db = $db ?: getDBConnection();

    // Normalize the evidence text for comparison
    $evidence_text = trim($evidence_text);
    if ($evidence_text === '') {
        return false;
    }

    // Search for similar evidence in the same task category
    $stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.user_id = ?
          AND mt.task_group = 'boosthub'
          AND mt.task_category = ?
          AND utl.proof_data LIKE ?
          AND utl.status IN ('completed', 'submitted', 'failed')
    ");
    // Use LIKE to match the text evidence (stored as JSON or plain text)
    $like_pattern = '%' . $evidence_text . '%';
    $stmt->execute([(int) $user_id, $task_category, $like_pattern]);

    return ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
}
