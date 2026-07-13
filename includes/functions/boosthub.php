<?php
/** Auto-split from legacy functions.php */

function boostHubBuildTaskFingerprint(array $task_row): string {
    $task_key = trim((string) ($task_row['task_key'] ?? ''));
    if ($task_key !== '') {
        return 'key:' . strtolower($task_key);
    }

    $title = strtolower(trim((string) ($task_row['title'] ?? '')));
    $category = strtolower(trim((string) ($task_row['task_category'] ?? '')));
    $task_link = strtolower(trim((string) ($task_row['task_link'] ?? '')));

    return 'fallback:' . md5($title . '|' . $category . '|' . $task_link);
}

function boostHubGetTaskCycleState($user_id, PDO $db = null): array {
    $db = $db ?: getDBConnection();

    $stmt = $db->prepare("
        SELECT
            utl.task_id,
            utl.status,
            utl.metadata,
            mt.task_key,
            mt.title,
            mt.task_category,
            mt.task_link
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.user_id = ?
          AND mt.task_group = 'boosthub'
          AND utl.status IN ('pending', 'submitted', 'completed', 'failed')
    ");
    $stmt->execute([(int) $user_id]);

    $completed_ids = [];
    $completed_fingerprints = [];
    $blocked_ids = [];
    $blocked_fingerprints = [];

    foreach ($stmt->fetchAll() as $row) {
        $task_id = (int) ($row['task_id'] ?? 0);
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        $fingerprint = boostHubBuildTaskFingerprint((array) $row);
        $metadata = !empty($row['metadata']) ? (json_decode((string) $row['metadata'], true) ?: []) : [];

        if ($status === 'completed') {
            $completed_ids[$task_id] = true;
            $completed_fingerprints[$fingerprint] = true;
            continue;
        }

        if ($status === 'pending' || $status === 'submitted' || ($status === 'failed' && !empty($metadata['correction_requested']))) {
            $blocked_ids[$task_id] = true;
            $blocked_fingerprints[$fingerprint] = true;
        }
    }

    return [
        'completed_ids' => $completed_ids,
        'completed_fingerprints' => $completed_fingerprints,
        'blocked_ids' => $blocked_ids,
        'blocked_fingerprints' => $blocked_fingerprints,
    ];
}

function boostHubGetAssignableTasks($user_id, PDO $db = null, int $current_task_id = 0): array {
    $db = $db ?: getDBConnection();
    $cycle_state = boostHubGetTaskCycleState((int) $user_id, $db);

    $stmt = $db->query("
        SELECT *
        FROM mini_tasks
        WHERE task_group = 'boosthub'
          AND is_active = 1
        ORDER BY id ASC
    ");

    $tasks = [];
    foreach ($stmt->fetchAll() as $task_row) {
        $task_id = (int) ($task_row['id'] ?? 0);
        $fingerprint = boostHubBuildTaskFingerprint((array) $task_row);

        if (isset($cycle_state['completed_ids'][$task_id]) || isset($cycle_state['completed_fingerprints'][$fingerprint])) {
            continue;
        }

        $is_current_task = $current_task_id > 0 && $task_id === $current_task_id;
        if (
            !$is_current_task
            && (isset($cycle_state['blocked_ids'][$task_id]) || isset($cycle_state['blocked_fingerprints'][$fingerprint]))
        ) {
            continue;
        }

        $tasks[] = $task_row;
    }

    return $tasks;
}

function boostHubSelectAssignableTask($user_id, PDO $db = null, int $current_task_id = 0): ?array {
    $tasks = boostHubGetAssignableTasks((int) $user_id, $db, $current_task_id);
    if (empty($tasks)) {
        return null;
    }

    if ($current_task_id <= 0) {
        return $tasks[0] ?? null;
    }

    if (count($tasks) <= 1) {
        return null;
    }

    foreach ($tasks as $index => $task_row) {
        if ((int) ($task_row['id'] ?? 0) === $current_task_id) {
            return $tasks[($index + 1) % count($tasks)] ?? null;
        }
    }

    return $tasks[0] ?? null;
}

function skipBoostHubTask($user_id, $task_id, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    $user_id = (int) $user_id;
    $task_id = (int) $task_id;

    $pending_stmt = $db->prepare("
        SELECT utl.*, mt.*
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.user_id = ?
          AND utl.task_id = ?
          AND utl.status = 'pending'
          AND mt.task_group = 'boosthub'
          AND mt.is_active = 1
        ORDER BY utl.id DESC
        LIMIT 1
    ");
    $pending_stmt->execute([$user_id, $task_id]);
    $pending_task = $pending_stmt->fetch();
    if (!$pending_task) {
        throw new RuntimeException('This BoostHub task is not available to skip.');
    }

    $cycle_tasks = boostHubGetAssignableTasks($user_id, $db, $task_id);
    $next_task = boostHubSelectAssignableTask($user_id, $db, $task_id);
    if (!$next_task) {
        throw new RuntimeException('No other unfinished BoostHub task is available in the cycle right now.');
    }

    $metadata = !empty($pending_task['metadata']) ? (json_decode((string) $pending_task['metadata'], true) ?: []) : [];
    $metadata['skipped'] = true;
    $metadata['skipped_at'] = date('c');
    $metadata['review_outcome'] = 'skipped';

    $db->beginTransaction();
    try {
        taskHubUpdateLog((int) $pending_task['id'], [
            'status' => 'failed',
            'completed_at' => date('Y-m-d H:i:s'),
            'task_completed_at' => date('Y-m-d H:i:s'),
            'metadata' => $metadata,
        ], $db);

        taskHubInsertLog($user_id, (int) ($next_task['id'] ?? 0), 'pending', [
            'task_available_at' => date('Y-m-d H:i:s'),
            'metadata' => ['boosthub_assigned' => 1, 'assigned_after_skip' => true],
        ], $db);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    return [
        'skipped_task_id' => $task_id,
        'next_task_id' => (int) ($next_task['id'] ?? 0),
        'next_task_title' => (string) ($next_task['title'] ?? 'Task'),
        'remaining_cycle_tasks' => max(0, count($cycle_tasks) - 1),
    ];
}

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
        return ['status' => 'closed', 'message' => 'Invalid user.', 'task' => null, 'unlock_at' => null, 'countdown_seconds' => 0, 'pending_task' => null, 'submitted_task' => null, 'has_pending_review' => false, 'has_returned_task' => false, 'can_skip' => false, 'skip_remaining' => 0];
    }

    $user = getUserById($user_id);
    if (!$user) {
        return ['status' => 'closed', 'message' => 'User account not found.', 'task' => null, 'unlock_at' => null, 'countdown_seconds' => 0, 'pending_task' => null, 'submitted_task' => null, 'has_pending_review' => false, 'has_returned_task' => false, 'can_skip' => false, 'skip_remaining' => 0];
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
        $remaining_assignable_tasks = boostHubGetAssignableTasks($user_id, $db, (int) ($pending['task_id'] ?? 0));
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
            'can_skip' => count($remaining_assignable_tasks) > 1,
            'skip_remaining' => max(0, count($remaining_assignable_tasks) - 1),
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
                    'can_skip' => false,
                    'skip_remaining' => 0,
                ];
            }
        }
    }

    // ── 5. Assign a new random task ──
    $task = boostHubSelectAssignableTask($user_id, $db);
    if (!$task) {
        return [
            'status' => 'finished',
            'message' => 'All active BoostHub tasks are finished for this account right now.',
            'task' => null,
            'unlock_at' => null,
            'countdown_seconds' => 0,
            'pending_task' => $pending_task,
            'submitted_task' => $submitted_task,
            'has_pending_review' => !empty($submitted_task),
            'has_returned_task' => !empty($pending_task),
            'can_skip' => false,
            'skip_remaining' => 0,
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
        'can_skip' => false,
        'skip_remaining' => 0,
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
