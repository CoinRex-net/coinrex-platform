<?php
/** Auto-split from legacy functions.php */

/**
 * Normalizes quiz answer data from either the admin JSON-array format ([2])
 * or the legacy integer format (2) into zero-based choice indexes.
 */
function taskHubNormalizeQuizAnswerIndexes($answer): array {
    if (is_array($answer)) {
        $indexes = [];
        foreach ($answer as $answer_index) {
            if (is_numeric($answer_index)) {
                $indexes[] = max(0, (int) $answer_index);
            }
        }
        return array_values(array_unique($indexes));
    }

    if (is_string($answer)) {
        $trimmed_answer = trim($answer);
        if ($trimmed_answer !== '' && strpos($trimmed_answer, '[') === 0) {
            $decoded_answer = json_decode($trimmed_answer, true);
            return taskHubNormalizeQuizAnswerIndexes($decoded_answer);
        }
    }

    return is_numeric($answer) ? [max(0, (int) $answer)] : [0];
}

function taskHubNormalizeQuizAnswerIndex($answer): int {
    $indexes = taskHubNormalizeQuizAnswerIndexes($answer);
    return (int) ($indexes[0] ?? 0);
}

/**
 * Shuffles quiz choices and remaps the correct answer index.
 * Uses a deterministic seed so the shuffle is consistent within the same request
 * (both getTaskHubState and taskHubValidateSingleQuizAnswer produce the same order).
 * This prevents the correct answer from always being at index 0.
 */
function shuffleQuizChoices(array $quiz, string $seed = ''): array {
    $shuffled = [];
    foreach ($quiz as $q_idx => $question) {
        $choices = $question['choices'] ?? [];
        $correct_indexes = taskHubNormalizeQuizAnswerIndexes($question['answer'] ?? 0);

        // Use deterministic shuffle based on seed + question index.
        // mt_srand seeds the Mersenne Twister which shuffle() uses internally.
        $keys = array_keys($choices);
        $seed_str = $seed . '_q' . $q_idx;
        mt_srand(crc32($seed_str));
        shuffle($keys);
        mt_srand();

        $new_choices = [];
        $new_correct_indexes = [];
        foreach ($keys as $new_idx => $old_idx) {
            $new_choices[] = $choices[$old_idx];
            if (in_array((int) $old_idx, $correct_indexes, true)) {
                $new_correct_indexes[] = $new_idx;
            }
        }

        $shuffled[] = [
            'question' => $question['question'],
            'choices' => $new_choices,
            'answer' => array_values($new_correct_indexes),
        ];
    }
    return $shuffled;
}

function getTaskHubMissionTaskDefinitionByKey($task_key, PDO $db = null) {
    // DEPRECATED: Use taskHubGetQuizByTaskKey() instead.
    // This function is kept for backward compatibility.
    $quiz = taskHubGetQuizByTaskKey((string) $task_key, $db);
    if (!empty($quiz)) {
        return ['quiz' => $quiz];
    }
    return [];
}


/**
 * Returns day titles for the 10-day mission.
 * Generates dynamic titles based on the first non-checkin task in each day.
 */
function getTaskHubDayTitles(PDO $db = null) {
    $db = $db ?: getDBConnection();
    $tasks_by_day = getTaskHubTasksByDay($db);
    $titles = [];
    for ($day = 1; $day <= TASKHUB_TOTAL_DAYS; $day++) {
        $day_tasks = $tasks_by_day[$day] ?? [];
        $title = 'Day ' . $day;
        foreach ($day_tasks as $task) {
            $tk = (string) ($task['task_key'] ?? '');
            // Skip check-in tasks for the title
            if (strpos($tk, '_checkin') !== false || strpos($tk, '_check_in') !== false) {
                continue;
            }
            // Use day_title from DB if set
            $day_title = (string) ($task['day_title'] ?? '');
            if ($day_title !== '') {
                $title = $day_title;
                break;
            }
            // Fallback: use the first non-checkin task's category as the day theme
            $category = (string) ($task['task_category'] ?? '');
            if ($category !== '' && $category !== 'custom') {
                $title = ucwords(str_replace('_', ' ', $category));
            } elseif (!empty($task['title'])) {
                $title = (string) $task['title'];
            }
            break;
        }
        $titles[$day] = $title;
    }
    // Ensure all days 1-10 have a title (fallback for days with no active tasks)
    for ($day = 1; $day <= TASKHUB_TOTAL_DAYS; $day++) {
        if (!isset($titles[$day]) || $titles[$day] === '') {
            $titles[$day] = 'Day ' . $day;
        }
    }
    return $titles;
}

function getTaskHubResetTimestamp($timestamp = null) {
    $timestamp = $timestamp !== null ? (int) $timestamp : time();
    return strtotime(date('Y-m-d', $timestamp) . ' ' . sprintf('%02d:00:00', TASKHUB_SERVER_RESET_HOUR));
}

function getTaskHubNextResetTimestamp($timestamp = null) {
    $base = getTaskHubResetTimestamp($timestamp);
    if (($timestamp !== null ? (int) $timestamp : time()) >= $base) {
        return strtotime('+1 day', $base);
    }

    return $base;
}

function getTaskHubCurrentPhase1Earnings($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND source = 'mini_task'
          AND reward_phase = 'phase1'
          AND status IN ('available', 'locked', 'claimed')
    ");
    $stmt->execute([(int) $user_id]);
    return round((float) ($stmt->fetch()['total'] ?? 0), 8);
}

function taskHubFormatDuration($seconds) {
    $seconds = max(0, (int) $seconds);
    $hours = (int) floor($seconds / 3600);
    $minutes = (int) floor(($seconds % 3600) / 60);
    $remaining_seconds = $seconds % 60;
    $parts = [];

    if ($hours > 0) {
        $parts[] = $hours . 'h';
    }
    if ($hours > 0 || $minutes > 0) {
        $parts[] = $minutes . 'm';
    }
    $parts[] = $remaining_seconds . 's';

    return implode(' ', $parts);
}

function getTaskHubRewardAmountForTask($user_id, array $task_row, array $log_row = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $reward = round((float) ($task_row['reward'] ?? 0), 8);
    $metadata = [];
    if (!empty($log_row['metadata'])) {
        $metadata = json_decode((string) $log_row['metadata'], true) ?: [];
    }

    if (($task_row['verification_mode'] ?? '') === 'boosthub' && !empty($metadata['boost_reward'])) {
        $reward = round((float) $metadata['boost_reward'], 8);
    }

    if (($task_row['verification_mode'] ?? '') === 'mystery') {
        $reward = taskHubGetMysteryBoxReward((int) $user_id, $db);
    }

    // Check-in reward penalty: if user is completing after the deadline for this check-in day, reward is 1
    if (taskHubIsCheckinTaskKey((string) ($task_row['task_key'] ?? ''))) {
        $mission_day = (int) ($task_row['mission_day'] ?? $log_row['mission_day'] ?? 1);
        $reward = taskHubGetCheckinRewardForMissionDay((int) $user_id, $mission_day, $db);

        // Check if we're currently past the deadline for this task
        if (!empty($log_row['task_available_at'])) {
            $available_ts = strtotime((string) $log_row['task_available_at']);
            $current_ts = time();
            // If current time is after the server reset window from available time, penalize to 1 $REX
            if ($available_ts > 0 && $current_ts > 0 && $current_ts > getTaskHubNextResetTimestamp($available_ts)) {
                $reward = 1.0;
            }
        }
    }

    $current_phase_earnings = getTaskHubCurrentPhase1Earnings((int) $user_id, $db);
    $remaining_cap = max(0, (float) TASKHUB_PHASE1_REWARD_CAP - $current_phase_earnings);
    return round(min($reward, $remaining_cap), 8);
}

/**
 * Loads quiz questions from the taskhub_quiz_questions DB table for a given task_key.
 * Returns the same [{question, choices, answer}] format as the hardcoded arrays.
 * Returns empty array if no DB rows found.
 */



/**
 * Loads quiz questions from the taskhub_quiz_questions DB table for a given task_key.
 * Returns the same [{question, choices, answer}] format as the hardcoded arrays.
 * Returns empty array if no DB rows found.
 */
function taskHubGetQuizByTaskKey(string $task_key, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        SELECT question, choices, answer
        FROM taskhub_quiz_questions
        WHERE task_key = ?
          AND is_active = 1
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$task_key]);
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        return [];
    }
    $quiz = [];
    foreach ($rows as $row) {
        $choices = json_decode((string) ($row['choices'] ?? '[]'), true);
        if (!is_array($choices) || empty($choices)) {
            continue;
        }
        // Decode answer — supports both JSON array format and legacy integer
        $raw_answer = (string) ($row['answer'] ?? '0');
        $quiz[] = [
            'question' => (string) ($row['question'] ?? ''),
            'choices' => $choices,
            'answer' => taskHubNormalizeQuizAnswerIndexes($raw_answer),
        ];
    }
    return $quiz;
}

function taskHubIsCheckinTaskKey(string $task_key): bool {
    return strpos($task_key, '_checkin') !== false || strpos($task_key, '_check_in') !== false;
}

function taskHubIsCoreMissionTask(array $task_row): bool {
    $task_key = (string) ($task_row['task_key'] ?? '');
    if (taskHubIsCheckinTaskKey($task_key)) {
        return true;
    }

    $verification_mode = (string) ($task_row['verification_mode'] ?? '');
    return $verification_mode === 'quiz'
        || $verification_mode === 'mystery'
        || !empty($task_row['requires_quiz']);
}

function taskHubGetLearningMetaForTask(array $task_row): array {
    $task_key = (string) ($task_row['task_key'] ?? '');
    $title = trim((string) ($task_row['learning_title'] ?? ''));
    $url = trim((string) ($task_row['learning_url'] ?? ''));

    $fallbacks = [
        'day1_terms_quiz' => ['Terms of Service', BASE_URL . '/terms.php'],
        'day2_about_quiz' => ['About CoinRex', BASE_URL . '/about.php'],
        'day3_privacy_quiz' => ['Privacy Policy', BASE_URL . '/privacy.php'],
        'day4_roadmap_quiz' => ['Roadmap Briefing', BASE_URL . '/roadmap.php'],
        'day5_devhub_quiz' => ['DevHub', BASE_URL . '/devhub/index.php'],
        'day6_review_quiz' => ['Review Guide', BASE_URL . '/submit-review.php'],
        'day7_final_quiz' => ['Quality Gate', BASE_URL . '/litepaper.php'],
    ];

    if (($title === '' || $url === '') && isset($fallbacks[$task_key])) {
        $title = $title !== '' ? $title : $fallbacks[$task_key][0];
        $url = $url !== '' ? $url : $fallbacks[$task_key][1];
    }

    if ($title === '' && ((string) ($task_row['verification_mode'] ?? '') === 'quiz' || !empty($task_row['requires_quiz']))) {
        $title = (string) ($task_row['title'] ?? 'Learning Material');
    }

    return [
        'title' => $title,
        'url' => $url,
    ];
}

function taskHubNormalizeLearningUrlForCurrentHost(string $url): string {
    $url = trim($url);

    if ($url === '' || strtolower($url) === 'about:blank') {
        return $url;
    }

    $base_url = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
    if ($base_url === '') {
        return $url;
    }

    $base_parts = parse_url($base_url);
    if ($base_parts === false || empty($base_parts['host'])) {
        return $url;
    }

    $base_scheme = (string) ($base_parts['scheme'] ?? 'http');
    $base_host = (string) $base_parts['host'];
    $base_origin = $base_scheme . '://' . $base_host . (isset($base_parts['port']) ? ':' . $base_parts['port'] : '');
    $base_uri = defined('BASE_URI') ? rtrim((string) BASE_URI, '/') : rtrim((string) ($base_parts['path'] ?? ''), '/');

    if (strpos($url, '//') === 0) {
        $url = $base_scheme . ':' . $url;
    }

    $url_parts = parse_url($url);
    if ($url_parts === false) {
        return $url;
    }

    $path = (string) ($url_parts['path'] ?? '');
    $query = isset($url_parts['query']) ? '?' . $url_parts['query'] : '';
    $fragment = isset($url_parts['fragment']) ? '#' . $url_parts['fragment'] : '';

    if (empty($url_parts['host'])) {
        if ($path === '') {
            return $url;
        }

        if (strpos($path, '/') !== 0) {
            return $base_url . '/' . ltrim($url, '/');
        }

        return $base_origin . $path . $query . $fragment;
    }

    $scheme = strtolower((string) ($url_parts['scheme'] ?? 'http'));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return $url;
    }

    $url_host = strtolower((string) $url_parts['host']);
    $current_host = strtolower($base_host);
    $localhost_aliases = ['localhost', '127.0.0.1', '::1'];
    $same_app_path = $base_uri === ''
        || $path === $base_uri
        || strpos($path, $base_uri . '/') === 0
        || strpos($path, '/') === 0;
    $should_rehost = $url_host === $current_host
        || in_array($url_host, $localhost_aliases, true)
        || ($same_app_path && taskHubUrlHostIsPrivateNetwork($url_host));

    if (!$should_rehost) {
        return $url;
    }

    if ($path === '') {
        $path = '/';
    }

    if ($base_uri !== '' && $path !== $base_uri && strpos($path, $base_uri . '/') !== 0) {
        $path = $base_uri . '/' . ltrim($path, '/');
    }

    return $base_origin . $path . $query . $fragment;
}

function taskHubUrlHostIsPrivateNetwork(string $host): bool {
    $host = strtolower(trim($host, '[]'));

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = array_map('intval', explode('.', $host));
        return $parts[0] === 10
            || ($parts[0] === 172 && $parts[1] >= 16 && $parts[1] <= 31)
            || ($parts[0] === 192 && $parts[1] === 168)
            || ($parts[0] === 127);
    }

    return $host === 'localhost' || $host === '::1';
}

function getTaskHubTaskRows(PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->query("
        SELECT *
        FROM mini_tasks
        WHERE task_group = 'mission'
          AND is_active = 1
        ORDER BY mission_day ASC, mission_step ASC
    ");

    return array_values(array_filter($stmt->fetchAll(), static function ($task_row) {
        return taskHubIsCoreMissionTask((array) $task_row);
    }));
}

function getTaskHubTasksByDay(PDO $db = null) {
    $tasks_by_day = [];
    foreach (getTaskHubTaskRows($db) as $task_row) {
        $day = (int) ($task_row['mission_day'] ?? 0);
        if (!isset($tasks_by_day[$day])) {
            $tasks_by_day[$day] = [];
        }
        $tasks_by_day[$day][] = $task_row;
    }

    return $tasks_by_day;
}

function getTaskHubLatestLog($user_id, $task_id, $mission_day, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        SELECT *
        FROM user_task_logs
        WHERE user_id = ?
          AND task_id = ?
          AND mission_day = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([(int) $user_id, (int) $task_id, (int) $mission_day]);
    return $stmt->fetch() ?: null;
}

function taskHubInsertLog($user_id, $task_id, $status, array $extra = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        INSERT INTO user_task_logs (
            user_id, task_id, completed_at, task_completed_at, task_available_at, mission_day, mission_step,
            attempt_no, proof_data, score, metadata, status
        ) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int) $user_id,
        (int) $task_id,
        $extra['task_completed_at'] ?? null,
        $extra['task_available_at'] ?? null,
        isset($extra['mission_day']) ? (int) $extra['mission_day'] : null,
        isset($extra['mission_step']) ? (int) $extra['mission_step'] : null,
        isset($extra['attempt_no']) ? (int) $extra['attempt_no'] : 1,
        $extra['proof_data'] ?? null,
        isset($extra['score']) ? (int) $extra['score'] : null,
        isset($extra['metadata']) ? json_encode($extra['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        (string) $status,
    ]);

    return (int) $db->lastInsertId();
}

function taskHubUpdateLog($log_id, array $fields, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $sets = [];
    $params = [];

    $map = [
        'completed_at' => 'completed_at',
        'task_completed_at' => 'task_completed_at',
        'task_available_at' => 'task_available_at',
        'proof_data' => 'proof_data',
        'score' => 'score',
        'status' => 'status',
    ];

    foreach ($map as $input_key => $column_name) {
        if (array_key_exists($input_key, $fields)) {
            $sets[] = $column_name . ' = ?';
            $params[] = $fields[$input_key];
        }
    }

    if (array_key_exists('metadata', $fields)) {
        $sets[] = 'metadata = ?';
        $params[] = json_encode($fields['metadata'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (empty($sets)) {
        return false;
    }

    $params[] = (int) $log_id;
    $stmt = $db->prepare("UPDATE user_task_logs SET " . implode(', ', $sets) . " WHERE id = ?");
    return $stmt->execute($params);
}

function taskHubSelectRandomBoostTask($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $stmt = $db->query("
        SELECT id, title, description, reward, task_category, task_link, completion_steps, proof_notes, cta_label
        FROM mini_tasks
        WHERE task_group = 'boosthub'
          AND is_active = 1
        ORDER BY RAND()
        LIMIT 1
    ");
    $task = $stmt->fetch();
    if (!$task) {
        return null;
    }

    return $task;
}

function taskHubCreatePendingDayTasks($user_id, $mission_day, $day_available_at, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_tasks = $tasks_by_day[(int) $mission_day] ?? [];
    if (empty($day_tasks)) {
        return;
    }

    $day_available_at = (string) $day_available_at;

    foreach ($day_tasks as $day_task) {
        $existing = getTaskHubLatestLog((int) $user_id, (int) $day_task['id'], (int) $mission_day, $db);
        if ($existing) {
            continue;
        }

        // Check-in tasks (step 0) are available immediately
        // Follow-up tasks (step > 0) are also available immediately since they don't have unlock_after_hours
        $unlock_hours = (defined('TESTING_MODE') && TESTING_MODE) ? 0 : (int) ($day_task['unlock_after_hours'] ?? 0);
        $available_at = ($unlock_hours > 0) 
            ? date('Y-m-d H:i:s', strtotime($day_available_at . ' + ' . $unlock_hours . ' hours'))
            : $day_available_at;

        taskHubInsertLog((int) $user_id, (int) $day_task['id'], 'pending', [
            'task_available_at' => $available_at,
            'mission_day' => (int) $mission_day,
            'mission_step' => (int) ($day_task['mission_step'] ?? 0),
        ], $db);
    }
}

function taskHubCreateFollowupTasksAfterCheckIn($user_id, $mission_day, $checkin_completed_at, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_tasks = $tasks_by_day[(int) $mission_day] ?? [];
    if (count($day_tasks) <= 1) {
        return;
    }

    foreach ($day_tasks as $day_task) {
        if ((int) ($day_task['mission_step'] ?? 0) === 0) {
            continue;
        }

        $existing = getTaskHubLatestLog((int) $user_id, (int) $day_task['id'], (int) $mission_day, $db);
        if ($existing) {
            continue;
        }

        // TESTING_MODE: Bypass unlock_after_hours cooldown - tasks available immediately
        $unlock_hours = (defined('TESTING_MODE') && TESTING_MODE) ? 0 : (int) ($day_task['unlock_after_hours'] ?? 0);
        $available_at_ts = strtotime((string) $checkin_completed_at . ' +' . $unlock_hours . ' hours');
        $metadata = [];
        if (($day_task['verification_mode'] ?? '') === 'boosthub') {
            $boost_task = taskHubSelectRandomBoostTask((int) $user_id, $db);
            if ($boost_task) {
                $last_boost_stmt = $db->prepare("
                    SELECT MAX(task_completed_at) AS completed_at
                    FROM user_task_logs utl
                    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                    WHERE utl.user_id = ?
                      AND utl.status = 'completed'
                      AND mt.task_group = 'mission'
                      AND mt.verification_mode = 'boosthub'
                ");
                $last_boost_stmt->execute([(int) $user_id]);
                $last_boost_at = $last_boost_stmt->fetch()['completed_at'] ?? null;
                if ($last_boost_at) {
                    $available_at_ts = max($available_at_ts, strtotime((string) $last_boost_at . ' +24 hours'));
                }

                $metadata = [
                    'boost_task_id' => (int) $boost_task['id'],
                    'boost_title' => (string) $boost_task['title'],
                    'boost_description' => (string) ($boost_task['description'] ?? ''),
                    'boost_reward' => (float) ($boost_task['reward'] ?? $day_task['reward']),
                    'boost_category' => (string) ($boost_task['task_category'] ?? 'custom'),
                    'boost_link' => (string) ($boost_task['task_link'] ?? ''),
                    'boost_steps' => (string) ($boost_task['completion_steps'] ?? ''),
                    'boost_proof_notes' => (string) ($boost_task['proof_notes'] ?? ''),
                    'boost_cta_label' => (string) ($boost_task['cta_label'] ?? ''),
                ];
            }
        }

        taskHubInsertLog((int) $user_id, (int) $day_task['id'], 'pending', [
            'task_available_at' => date('Y-m-d H:i:s', $available_at_ts),
            'mission_day' => (int) $mission_day,
            'mission_step' => (int) ($day_task['mission_step'] ?? 0),
            'metadata' => $metadata,
        ], $db);
    }
}

function taskHubGetDayCompletionInfo($user_id, $mission_day, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_tasks = $tasks_by_day[(int) $mission_day] ?? [];
    if (empty($day_tasks)) {
        return ['all_completed' => false, 'completed_at' => null, 'day_started_at' => null, 'tasks' => []];
    }

    $all_completed = true;
    $completed_at_ts = 0;
    $day_started_at_ts = 0;
    $task_states = [];

    foreach ($day_tasks as $day_task) {
        $log = getTaskHubLatestLog((int) $user_id, (int) $day_task['id'], (int) $mission_day, $db);
        $task_states[] = ['task' => $day_task, 'log' => $log];

        if (!$log || !in_array((string) ($log['status'] ?? ''), ['completed'], true)) {
            $all_completed = false;
        }

        $available_at = !empty($log['task_available_at']) ? strtotime((string) $log['task_available_at']) : 0;
        $completed_at = !empty($log['task_completed_at']) ? strtotime((string) $log['task_completed_at']) : 0;
        if ($available_at > 0 && ($day_started_at_ts === 0 || $available_at < $day_started_at_ts)) {
            $day_started_at_ts = $available_at;
        }
        if ($completed_at > $completed_at_ts) {
            $completed_at_ts = $completed_at;
        }
    }

    // TESTING_MODE: Skip BoostHub gateway requirement
    if (!defined('TESTING_MODE') || !TESTING_MODE) {
        $boost_required_reward = taskHubGetBoostRequirementByDay((int) $mission_day);
        if ($boost_required_reward > 0) {
            $started_at_value = $day_started_at_ts > 0 ? date('Y-m-d H:i:s', $day_started_at_ts) : null;
            $has_boost = false;
            if (!empty($started_at_value)) {
                $boost_stmt = $db->prepare("
                    SELECT COUNT(*) AS total
                    FROM user_task_logs utl
                    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                    WHERE utl.user_id = ?
                      AND utl.status = 'completed'
                      AND mt.task_group = 'boosthub'
                      AND ROUND(mt.reward, 2) = ?
                      AND COALESCE(utl.task_completed_at, utl.completed_at) >= ?
                ");
                $boost_stmt->execute([(int) $user_id, round((float) $boost_required_reward, 2), (string) $started_at_value]);
                $has_boost = ((int) ($boost_stmt->fetch()['total'] ?? 0)) > 0;
            }
            if (!$has_boost) {
                $all_completed = false;
            }
        }
    }

    return [
        'all_completed' => $all_completed,
        'completed_at' => $completed_at_ts > 0 ? date('Y-m-d H:i:s', $completed_at_ts) : null,
        'day_started_at' => $day_started_at_ts > 0 ? date('Y-m-d H:i:s', $day_started_at_ts) : null,
        'tasks' => $task_states,
    ];
}

function taskHubGetDayDeadlineTimestamp(array $day_completion): int {
    if (empty($day_completion['day_started_at'])) {
        return 0;
    }

    $started_at_ts = strtotime((string) $day_completion['day_started_at']);
    if ($started_at_ts <= 0) {
        return 0;
    }

    return (int) getTaskHubNextResetTimestamp($started_at_ts);
}

function taskHubDayIsMissed($user_id, $mission_day, PDO $db = null, $timestamp = null): bool {
    $db = $db ?: getDBConnection();
    $info = taskHubGetDayCompletionInfo((int) $user_id, (int) $mission_day, $db);
    $deadline_ts = taskHubGetDayDeadlineTimestamp($info);
    if ($deadline_ts <= 0) {
        return false;
    }

    if (!empty($info['all_completed'])) {
        $completed_ts = !empty($info['completed_at']) ? strtotime((string) $info['completed_at']) : 0;
        return $completed_ts > 0 && $completed_ts > $deadline_ts;
    }

    $now_ts = $timestamp !== null ? (int) $timestamp : time();
    return $now_ts > $deadline_ts;
}

function taskHubHasMissedDays($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    for ($day = 1; $day <= TASKHUB_TOTAL_DAYS; $day++) {
        if (taskHubDayIsMissed((int) $user_id, $day, $db)) {
            return true;
        }
    }
    return false;
}

function taskHubGetLatestMissedDay($user_id, PDO $db = null, $up_to_day = null): int {
    $db = $db ?: getDBConnection();
    $last_missed_day = 0;
    $max_day = $up_to_day !== null
        ? max(1, min(TASKHUB_TOTAL_DAYS, (int) $up_to_day))
        : TASKHUB_TOTAL_DAYS;

    for ($day = 1; $day <= $max_day; $day++) {
        if (taskHubDayIsMissed((int) $user_id, $day, $db)) {
            $last_missed_day = $day;
        }
    }

    return $last_missed_day;
}

function taskHubGetCheckinRewardForMissionDay($user_id, $mission_day, PDO $db = null): float {
    $db = $db ?: getDBConnection();
    $mission_day = max(1, min(TASKHUB_TOTAL_DAYS, (int) $mission_day));
    $last_missed_day = taskHubGetLatestMissedDay((int) $user_id, $db, $mission_day);

    if ($last_missed_day > 0) {
        return (float) max(1, min(TASKHUB_TOTAL_DAYS, $mission_day - $last_missed_day + 1));
    }

    return (float) $mission_day;
}

function taskHubGetMissedDayCount($user_id, PDO $db = null): int {
    $db = $db ?: getDBConnection();
    $missed_days = 0;

    for ($day = 1; $day <= TASKHUB_TOTAL_DAYS; $day++) {
        if (taskHubDayIsMissed((int) $user_id, $day, $db)) {
            $missed_days++;
        }
    }

    return $missed_days;
}

function taskHubGetMysteryBoxReward($user_id, PDO $db = null): float {
    $db = $db ?: getDBConnection();
    $missed_days = taskHubGetMissedDayCount((int) $user_id, $db);
    return (float) max(10, (float) TASKHUB_MYSTERY_BOX_PERFECT_REWARD - ($missed_days * 5));
}

function taskHubGetStreakWarningState($user_id, PDO $db = null): array {
    $db = $db ?: getDBConnection();
    $latest_missed_day = taskHubGetLatestMissedDay((int) $user_id, $db);

    if ($latest_missed_day <= 0) {
        return [
            'latest_missed_day' => 0,
            'streak_warning_active' => false,
            'streak_badge_state' => 'perfect',
            'streak_badge_label' => 'Perfect',
        ];
    }

    $missed_info = taskHubGetDayCompletionInfo((int) $user_id, $latest_missed_day, $db);
    $missed_deadline_ts = taskHubGetDayDeadlineTimestamp($missed_info);
    $has_checkin_after_miss = false;

    if ($missed_deadline_ts > 0) {
        $stmt = $db->prepare("
            SELECT COUNT(*) AS total
            FROM user_task_logs utl
            INNER JOIN mini_tasks mt ON mt.id = utl.task_id
            WHERE utl.user_id = ?
              AND utl.status = 'completed'
              AND mt.task_group = 'mission'
              AND mt.mission_day >= ?
              AND (mt.task_key LIKE '%checkin%' OR mt.task_key LIKE '%check_in%')
              AND COALESCE(utl.task_completed_at, utl.completed_at) > ?
        ");
        $stmt->execute([
            (int) $user_id,
            (int) $latest_missed_day,
            date('Y-m-d H:i:s', $missed_deadline_ts),
        ]);
        $has_checkin_after_miss = ((int) ($stmt->fetch()['total'] ?? 0)) > 0;
    }

    return [
        'latest_missed_day' => $latest_missed_day,
        'streak_warning_active' => !$has_checkin_after_miss,
        'streak_badge_state' => $has_checkin_after_miss ? 'rebuilding' : 'broken',
        'streak_badge_label' => $has_checkin_after_miss ? 'Rebuilding' : 'Missed',
    ];
}

/**
 * Calculates the user's consecutive check-in streak (1-10).
 * Counts how many consecutive days (starting from day 1) the user has
 * completed all tasks within the same server reset window.
 * Breaks at the first incomplete day or missed day.
 */
function taskHubGetConsecutiveCheckinStreak($user_id, PDO $db = null): int {
    $db = $db ?: getDBConnection();
    $streak = 0;
    $last_missed_day = taskHubGetLatestMissedDay((int) $user_id, $db);
    $start_day = $last_missed_day > 0 ? $last_missed_day : 1;

    for ($day = $start_day; $day <= TASKHUB_TOTAL_DAYS; $day++) {
        $info = taskHubGetDayCompletionInfo((int) $user_id, $day, $db);
        
        // Day not started yet — streak ends here
        if (empty($info['day_started_at'])) {
            break;
        }
        
        // Day not fully completed — streak ends here
        $streak++;
        if (empty($info['all_completed'])) {
            break;
        }
    }

    return min($streak, TASKHUB_TOTAL_DAYS);
}

/**
 * Calculates the total $REX earned from check-in tasks across all days.
 * Queries reward_ledger for completed check-in task rewards.
 * Uses reference_id LIKE 'taskhub:%checkin%' pattern since reward_ledger
 * stores task keys in the reference_id field (e.g. 'taskhub:day1_checkin').
 */
function taskHubGetTotalCheckinEarned($user_id, PDO $db = null): float {
    $db = $db ?: getDBConnection();
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM reward_ledger
        WHERE user_id = ?
          AND source = 'mini_task'
          AND status IN ('available', 'locked', 'claimed')
          AND (
              reference_id LIKE 'taskhub:%checkin%'
              OR reference_id LIKE 'taskhub:%check_in%'
          )
    ");
    $stmt->execute([(int) $user_id]);
    return round((float) ($stmt->fetch()['total'] ?? 0), 2);
}

function taskHubDayHasBoostGatewayTask(array $day_tasks): bool {
    foreach ($day_tasks as $task) {
        $task_key = (string) ($task['task_key'] ?? '');
        if ((string) ($task['verification_mode'] ?? '') === 'boosthub' || strpos($task_key, '_boosthub_gateway') !== false) {
            return true;
        }
    }

    return false;
}

function taskHubMissionCompleted($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $day_10 = taskHubGetDayCompletionInfo((int) $user_id, TASKHUB_TOTAL_DAYS, $db);
    return !empty($day_10['all_completed']);
}

function taskHubDayHasScheduledUnlocks(array $day_completion) {
    foreach (($day_completion['tasks'] ?? []) as $task_state) {
        $log = $task_state['log'] ?? [];
        $status = (string) ($log['status'] ?? '');
        $available_at_ts = !empty($log['task_available_at']) ? strtotime((string) $log['task_available_at']) : 0;
        if ($status === 'pending' && $available_at_ts > time()) {
            return true;
        }
    }

    return false;
}

function syncTaskHubDayProgress($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = getUserById((int) $user_id);
    if (!$user) {
        return null;
    }

    $current_day = max(1, min(TASKHUB_TOTAL_DAYS, (int) ($user['current_day'] ?? 1)));
    taskHubCreatePendingDayTasks((int) $user_id, $current_day, date('Y-m-d H:i:s'), $db);

    while ($current_day <= TASKHUB_TOTAL_DAYS) {
        $completion = taskHubGetDayCompletionInfo((int) $user_id, $current_day, $db);
        if (empty($completion['all_completed'])) {
            break;
        }

        $completed_at_ts = strtotime((string) ($completion['completed_at'] ?? 'now'));
        $next_reset = getTaskHubNextResetTimestamp($completed_at_ts);
        $db->prepare("UPDATE users SET last_day_completed_at = ? WHERE id = ?")->execute([
            date('Y-m-d H:i:s', $completed_at_ts),
            (int) $user_id,
        ]);

        if ($current_day >= TASKHUB_TOTAL_DAYS) {
            break;
        }

        // TESTING_MODE: Skip server reset wait - advance to next day immediately
        if (!defined('TESTING_MODE') || !TESTING_MODE) {
            if (time() < $next_reset) {
                break;
            }
        }

        $current_day++;
        $db->prepare("UPDATE users SET current_day = ? WHERE id = ?")->execute([(int) $current_day, (int) $user_id]);
        taskHubCreatePendingDayTasks((int) $user_id, $current_day, date('Y-m-d H:i:s', $next_reset), $db);
    }

    return getUserById((int) $user_id);
}

function getTaskHubState($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = syncTaskHubDayProgress((int) $user_id, $db);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    // TESTING_MODE: Skip level check so testers can access TaskHub
    if (!defined('TESTING_MODE') || !TESTING_MODE) {
        if (normalizeUserLevel($user['level'] ?? 'beginner') !== 'beginner') {
            return [
                'access' => 'closed',
                'message' => 'TaskHub is available for Beginner accounts only.',
                'current_day' => (int) ($user['current_day'] ?? 1),
                'status' => 'completed',
            ];
        }
    }

    $current_day = max(1, min(TASKHUB_TOTAL_DAYS, (int) ($user['current_day'] ?? 1)));
    $tasks_by_day = getTaskHubTasksByDay($db);
    $day_titles = getTaskHubDayTitles();
    $profile_complete = isUserProfileComplete($user);
    $current_tasks = $tasks_by_day[$current_day] ?? [];
    $next_day_tasks = $tasks_by_day[$current_day + 1] ?? [];
    $day_completion = taskHubGetDayCompletionInfo((int) $user_id, $current_day, $db);
    $day_started_at_ts = !empty($day_completion['day_started_at']) ? strtotime((string) $day_completion['day_started_at']) : time();
    $next_reset_ts = getTaskHubNextResetTimestamp($day_started_at_ts);
    $has_scheduled_unlocks = taskHubDayHasScheduledUnlocks($day_completion);
    $overall_total_tasks = 0;
    $overall_completed_tasks = 0;

    $status = 'in_progress';
    $status_message = 'In progress';
    $day_deadline_passed = !empty($day_completion['day_started_at']) && time() >= $next_reset_ts;
    if (!empty($day_completion['all_completed'])) {
        $status = 'completed';
        $status_message = $current_day >= TASKHUB_TOTAL_DAYS
            ? 'Completed'
            : 'Day cleared. Waiting for server reset';
    } elseif ($has_scheduled_unlocks) {
        $status = 'in_progress';
        $status_message = 'Waiting for next task unlock';
    } elseif ($day_deadline_passed) {
        $status = 'paused';
        $status_message = 'Progress paused until completion';
    }

    $build_task_payload = static function (array $task_row, $log, $active_day, PDO $inner_db) use ($profile_complete, $user_id) {
        $metadata = !empty($log['metadata']) ? (json_decode((string) $log['metadata'], true) ?: []) : [];
        $available_at_ts = !empty($log['task_available_at']) ? strtotime((string) $log['task_available_at']) : 0;
        $countdown = $available_at_ts > time() ? max(0, $available_at_ts - time()) : 0;

        // TESTING_MODE: Ignore unlock timers - show all pending tasks as available
        $is_testing = defined('TESTING_MODE') && TESTING_MODE;
        $task_status = 'locked';
        $task_message = 'Complete previous tasks to continue';
        if ($log) {
            $task_status = (string) ($log['status'] ?? 'locked');
            if ($task_status === 'pending' && (!$active_day || $available_at_ts <= time() || $is_testing)) {
                $task_status = 'available';
                $task_message = 'Ready';
            } elseif ($task_status === 'pending') {
                $task_status = 'locked';
                $task_message = 'Next task unlocks in ' . taskHubFormatDuration(max(0, $countdown));
            } elseif ($task_status === 'submitted') {
                $task_message = 'Awaiting manual review';
            } elseif ($task_status === 'failed') {
                $task_status = 'available';
                $task_message = !empty($task_row['requires_manual_review'])
                    ? 'Submission rejected. Try again.'
                    : 'Pass the quiz to proceed';
            } elseif ($task_status === 'completed') {
                $task_message = 'Completed';
            }
        }

        if (($task_row['verification_mode'] ?? '') === 'boosthub' && !empty($metadata['boost_title'])) {
            $task_row['title'] = $metadata['boost_title'];
            $task_row['description'] = $metadata['boost_description'] ?? $task_row['description'];
            $task_row['reward'] = $metadata['boost_reward'] ?? $task_row['reward'];
            $task_row['task_category'] = $metadata['boost_category'] ?? ($task_row['task_category'] ?? 'custom');
            $task_row['task_link'] = $metadata['boost_link'] ?? ($task_row['task_link'] ?? '');
            $task_row['completion_steps'] = $metadata['boost_steps'] ?? ($task_row['completion_steps'] ?? '');
            $task_row['proof_notes'] = $metadata['boost_proof_notes'] ?? ($task_row['proof_notes'] ?? '');
            $task_row['cta_label'] = $metadata['boost_cta_label'] ?? ($task_row['cta_label'] ?? '');
        }

        $definition = getTaskHubMissionTaskDefinitionByKey((string) ($task_row['task_key'] ?? ''));
        // Shuffle quiz choices deterministically so correct answer isn't always at index 0.
        // Seed uses user_id + task_key so the shuffle is consistent across requests.
        $quiz_data = $definition['quiz'] ?? [];
        if (!empty($quiz_data)) {
            $seed = (string) $user_id . '_' . (string) ($task_row['task_key'] ?? '');
            $quiz_data = shuffleQuizChoices($quiz_data, $seed);
        }
        $learning_meta = taskHubGetLearningMetaForTask($task_row);
        $learning_url = taskHubNormalizeLearningUrlForCurrentHost((string) ($learning_meta['url'] ?? ''));
        $display_reward = getTaskHubRewardAmountForTask((int) $user_id, $task_row, (array) ($log ?: []), $inner_db);
        return [
            'id' => (int) $task_row['id'],
            'task_key' => (string) $task_row['task_key'],
            'mission_step' => (int) ($task_row['mission_step'] ?? 0),
            'title' => (string) $task_row['title'],
            'description' => (string) ($task_row['description'] ?? ''),
            'reward' => round((float) $display_reward, 2),
            'task_category' => (string) ($task_row['task_category'] ?? 'custom'),
            'task_link' => (string) ($task_row['task_link'] ?? ''),
            'completion_steps' => (string) ($task_row['completion_steps'] ?? ''),
            'proof_notes' => (string) ($task_row['proof_notes'] ?? ''),
            'cta_label' => (string) ($task_row['cta_label'] ?? ''),
            'status' => $task_status,
            'status_message' => $task_message,
            'countdown_seconds' => $countdown,
            'task_available_at' => $log['task_available_at'] ?? null,
            'task_completed_at' => $log['task_completed_at'] ?? null,
            'verification_mode' => (string) ($task_row['verification_mode'] ?? 'instant'),
            'requires_quiz' => !empty($task_row['requires_quiz']),
            'requires_manual_review' => !empty($task_row['requires_manual_review']),
            'min_quiz_score' => (int) ($task_row['min_quiz_score'] ?? 0),
            'learning_title' => (string) ($learning_meta['title'] ?? ''),
            'learning_url' => $learning_url,
            'required_reading_seconds' => (int) ($task_row['required_reading_seconds'] ?? 45),
            'learning_opened' => !empty($metadata['learning_opened']),
            'quiz' => $quiz_data,
            'profile_complete' => ($task_row['verification_mode'] ?? '') === 'profile' ? $profile_complete : null,
            'rejection_count' => (int) ($log['rejection_count'] ?? 0),
            'cooldown_remaining' => $task_status === 'failed' && !empty($metadata['reviewed_at'])
                ? max(0, 3600 - (time() - strtotime((string) $metadata['reviewed_at'])))
                : 0,
        ];
    };

    $tasks_payload = [];
    foreach ($current_tasks as $task_row) {
        $log = getTaskHubLatestLog((int) $user_id, (int) $task_row['id'], $current_day, $db);
        $tasks_payload[] = $build_task_payload($task_row, $log, true, $db);
    }
    $current_day_has_db_gateway = taskHubDayHasBoostGatewayTask($current_tasks);
    $boost_gateway = $current_day_has_db_gateway ? null : taskHubGetBoostGatewayTask($current_day);
    if ($boost_gateway) {
        $has_boost_completion = false;
        if (!empty($day_completion['day_started_at'])) {
            $boost_check_stmt = $db->prepare("
                SELECT COUNT(*) AS total
                FROM user_task_logs utl
                INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                WHERE utl.user_id = ?
                  AND utl.status = 'completed'
                  AND mt.task_group = 'boosthub'
                  AND ROUND(mt.reward, 2) = ?
                  AND COALESCE(utl.task_completed_at, utl.completed_at) >= ?
            ");
            $boost_check_stmt->execute([(int) $user_id, round((float) $boost_gateway['reward'], 2), (string) $day_completion['day_started_at']]);
            $has_boost_completion = ((int) ($boost_check_stmt->fetch()['total'] ?? 0)) > 0;
        }

        $tasks_payload[] = [
            'id' => 0,
            'task_key' => (string) $boost_gateway['task_key'],
            'mission_step' => (int) $boost_gateway['mission_step'],
            'title' => (string) $boost_gateway['title'],
            'description' => (string) $boost_gateway['description'],
            'reward' => round((float) $boost_gateway['reward'], 2),
            'task_category' => (string) $boost_gateway['task_category'],
            'task_link' => (string) $boost_gateway['task_link'],
            'completion_steps' => (string) $boost_gateway['completion_steps'],
            'proof_notes' => (string) $boost_gateway['proof_notes'],
            'cta_label' => (string) $boost_gateway['cta_label'],
            'status' => $has_boost_completion ? 'completed' : 'available',
            'status_message' => $has_boost_completion ? 'Completed' : 'Complete a matching BoostHub task, then come back.',
            'countdown_seconds' => 0,
            'task_available_at' => null,
            'task_completed_at' => null,
            'verification_mode' => 'boosthub_redirect',
            'requires_quiz' => false,
            'requires_manual_review' => false,
            'learning_title' => '',
            'learning_url' => '',
            'quiz' => [],
            'profile_complete' => null,
        ];
    }

    $next_day_preview = [];
    foreach ($next_day_tasks as $next_task) {
        $next_day_preview[] = [
            'title' => (string) $next_task['title'],
            'description' => (string) ($next_task['description'] ?? ''),
        ];
    }

    $days_payload = [];
    for ($day = 1; $day <= TASKHUB_TOTAL_DAYS; $day++) {
        $day_tasks = $tasks_by_day[$day] ?? [];
        $completion = taskHubGetDayCompletionInfo((int) $user_id, $day, $db);
        $day_status = 'locked';
        $day_message = 'Locked';
        $day_countdown = 0;

        if ($day < $current_day) {
            $day_status = 'completed';
            $day_message = 'Completed';
        } elseif ($day === $current_day) {
            if (!empty($completion['all_completed'])) {
                $day_status = 'completed';
                $day_message = $day >= TASKHUB_TOTAL_DAYS ? 'Completed' : 'Day cleared. Waiting for server reset';
                if ($day < TASKHUB_TOTAL_DAYS) {
                    $day_countdown = max(0, $next_reset_ts - time());
                }
            } else {
                $day_status = $status;
                $day_message = $status_message;
            }
        } elseif ($day === ($current_day + 1) && !empty($day_completion['all_completed'])) {
            $day_status = 'locked';
            $day_message = 'Unlocks after server reset';
            $day_countdown = max(0, $next_reset_ts - time());
        }

        $day_task_payload = [];
        foreach ($day_tasks as $day_task) {
            if ($day > $current_day) {
                $day_task_payload[] = [
                    'id' => (int) $day_task['id'],
                    'task_key' => (string) $day_task['task_key'],
                    'mission_step' => (int) ($day_task['mission_step'] ?? 0),
                    'title' => 'Surprise Task',
                    'description' => 'This task will be revealed when Day ' . $day . ' unlocks.',
                    'reward' => round((float) ($day_task['reward'] ?? 0), 2),
                    'task_category' => (string) ($day_task['task_category'] ?? 'custom'),
                    'task_link' => '',
                    'completion_steps' => '',
                    'proof_notes' => '',
                    'cta_label' => '',
                    'status' => 'locked',
                    'status_message' => 'Hidden until this day unlocks',
                    'countdown_seconds' => 0,
                    'task_available_at' => null,
                    'task_completed_at' => null,
                    'verification_mode' => (string) ($day_task['verification_mode'] ?? 'instant'),
                    'requires_quiz' => !empty($day_task['requires_quiz']),
                    'requires_manual_review' => !empty($day_task['requires_manual_review']),
                    'learning_title' => '',
                    'learning_url' => '',
                    'quiz' => [],
                ];
                continue;
            }

            $day_log = getTaskHubLatestLog((int) $user_id, (int) $day_task['id'], $day, $db);
            $day_task_payload[] = $build_task_payload($day_task, $day_log, $day === $current_day, $db);
        }
        $day_has_db_gateway = taskHubDayHasBoostGatewayTask($day_tasks);
        $day_boost_gateway = $day_has_db_gateway ? null : taskHubGetBoostGatewayTask($day);
        if ($day_boost_gateway && $day <= $current_day) {
            $day_info_started = !empty($completion['day_started_at']) ? (string) $completion['day_started_at'] : '';
            $day_boost_completed = false;
            if ($day_info_started !== '') {
                $day_boost_stmt = $db->prepare("
                    SELECT COUNT(*) AS total
                    FROM user_task_logs utl
                    INNER JOIN mini_tasks mt ON mt.id = utl.task_id
                    WHERE utl.user_id = ?
                      AND utl.status = 'completed'
                      AND mt.task_group = 'boosthub'
                      AND ROUND(mt.reward, 2) = ?
                      AND COALESCE(utl.task_completed_at, utl.completed_at) >= ?
                ");
                $day_boost_stmt->execute([(int) $user_id, round((float) $day_boost_gateway['reward'], 2), $day_info_started]);
                $day_boost_completed = ((int) ($day_boost_stmt->fetch()['total'] ?? 0)) > 0;
            }

            $day_task_payload[] = [
                'id' => 0,
                'task_key' => (string) $day_boost_gateway['task_key'],
                'mission_step' => (int) $day_boost_gateway['mission_step'],
                'title' => (string) $day_boost_gateway['title'],
                'description' => (string) $day_boost_gateway['description'],
                'reward' => round((float) $day_boost_gateway['reward'], 2),
                'task_category' => (string) $day_boost_gateway['task_category'],
                'task_link' => (string) $day_boost_gateway['task_link'],
                'completion_steps' => (string) $day_boost_gateway['completion_steps'],
                'proof_notes' => (string) $day_boost_gateway['proof_notes'],
                'cta_label' => (string) $day_boost_gateway['cta_label'],
                'status' => $day_boost_completed ? 'completed' : ($day === $current_day ? 'available' : 'locked'),
                'status_message' => $day_boost_completed ? 'Completed' : ($day === $current_day ? 'Complete in BoostHub to continue.' : 'Locked'),
                'countdown_seconds' => 0,
                'task_available_at' => null,
                'task_completed_at' => null,
                'verification_mode' => 'boosthub_redirect',
                'requires_quiz' => false,
                'requires_manual_review' => false,
                'learning_title' => '',
                'learning_url' => '',
                'quiz' => [],
                'profile_complete' => null,
            ];
        }

        $days_payload[] = [
            'day' => $day,
            'title' => (string) ($day_titles[$day] ?? ('Day ' . $day)),
            'status' => $day_status,
            'status_message' => $day_message,
            'countdown_seconds' => $day_countdown,
            'tasks' => $day_task_payload,
            'completed_tasks' => count(array_filter($day_task_payload, static function ($task) {
                return ($task['status'] ?? '') === 'completed';
            })),
            'total_tasks' => count($day_task_payload),
            'is_current' => $day === $current_day,
            'is_past' => $day < $current_day,
        ];

        $overall_completed_tasks += count(array_filter($day_task_payload, static function ($task) use ($day, $current_day) {
            return $day <= $current_day && ($task['status'] ?? '') === 'completed';
        }));
        $overall_total_tasks += count($day_task_payload);
    }

    $current_day_completed_tasks = count(array_filter($tasks_payload, static function ($task) {
        return ($task['status'] ?? '') === 'completed';
    }));
    $current_day_total_tasks = count($tasks_payload);
    $current_day_progress_percent = $current_day_total_tasks > 0
        ? (int) round(($current_day_completed_tasks / $current_day_total_tasks) * 100)
        : 0;
    $overall_progress_percent = $overall_total_tasks > 0
        ? (int) round(($overall_completed_tasks / $overall_total_tasks) * 100)
        : 0;
    $completed_days = count(array_filter($days_payload, static function ($day) {
        return ($day['status'] ?? '') === 'completed';
    }));

    // ============================================================
    // STREAK / CHECK-IN CALCULATIONS
    // ============================================================
    $has_missed = taskHubHasMissedDays((int) $user_id, $db);
    $streak_warning_state = taskHubGetStreakWarningState((int) $user_id, $db);
    $consecutive_streak = taskHubGetConsecutiveCheckinStreak((int) $user_id, $db);
    $total_streak_earned = taskHubGetTotalCheckinEarned((int) $user_id, $db);
    $missed_day_count = taskHubGetMissedDayCount((int) $user_id, $db);
    $mystery_box_reward = taskHubGetMysteryBoxReward((int) $user_id, $db);
    $total_potential_checkin = 55.0; // 1+2+3+...+10 = 55

    // Per-day cumulative potential $REX
    $per_day_potential = [];
    $cumulative = 0;
    for ($i = 1; $i <= TASKHUB_TOTAL_DAYS; $i++) {
        $cumulative += $i;
        $per_day_potential[$i] = (float) $cumulative;
    }

    $today_checkin_reward = taskHubGetCheckinRewardForMissionDay((int) $user_id, $current_day, $db);
    $next_checkin_reward = taskHubGetCheckinRewardForMissionDay((int) $user_id, min(TASKHUB_TOTAL_DAYS, $current_day + 1), $db);

    return [
        'access' => 'open',
        'current_day' => $current_day,
        'status' => $status,
        'status_message' => $status_message,
        'next_reset_at' => date('Y-m-d H:i:s', $next_reset_ts),
        'tasks' => $tasks_payload,
        'next_day' => min(TASKHUB_TOTAL_DAYS, $current_day + 1),
        'next_day_preview' => $next_day_preview,
        'completed_tasks' => count(array_filter($tasks_payload, static function ($task) {
            return ($task['status'] ?? '') === 'completed';
        })),
        'total_tasks' => count($tasks_payload),
        'current_day_progress_percent' => $current_day_progress_percent,
        'overall_completed_tasks' => $overall_completed_tasks,
        'overall_total_tasks' => $overall_total_tasks,
        'overall_progress_percent' => $overall_progress_percent,
        'completed_days' => $completed_days,
        'total_days' => TASKHUB_TOTAL_DAYS,
        'profile_complete' => $profile_complete,
        'paused' => $status === 'paused',
        'mission_completed' => taskHubMissionCompleted((int) $user_id, $db),
        'has_missed_days' => $has_missed,
        'latest_missed_day' => (int) ($streak_warning_state['latest_missed_day'] ?? 0),
        'streak_warning_active' => !empty($streak_warning_state['streak_warning_active']),
        'streak_badge_state' => (string) ($streak_warning_state['streak_badge_state'] ?? ($has_missed ? 'broken' : 'perfect')),
        'streak_badge_label' => (string) ($streak_warning_state['streak_badge_label'] ?? ($has_missed ? 'Missed' : 'Perfect')),
        'days' => $days_payload,
        // Streak / Check-in sync fields
        'consecutive_checkin_streak' => $consecutive_streak,
        'today_checkin_reward' => $today_checkin_reward,
        'next_checkin_reward' => $next_checkin_reward,
        'missed_day_count' => $missed_day_count,
        'mystery_box_reward' => $mystery_box_reward,
        'total_streak_earned' => $total_streak_earned,
        'total_potential_checkin' => $total_potential_checkin,
        'per_day_potential' => $per_day_potential,
    ];
}

function taskHubCompleteInstantTask($user_id, array $task_row, array $log_row, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $user = getUserById((int) $user_id);
    $metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];

    if ((string) ($log_row['status'] ?? '') === 'completed') {
        throw new RuntimeException('This task has already been completed.');
    }

    if (($task_row['verification_mode'] ?? '') === 'profile') {
        if (!isUserProfileComplete($user)) {
            throw new RuntimeException('Open your profile page and complete your avatar, full name, username, and country before finishing this task.');
        }
    }

    if (($task_row['verification_mode'] ?? '') === 'wallet') {
        $wallet_address = trim((string) ($payload['wallet_address'] ?? $user['wallet_address'] ?? ''));
        if ($wallet_address === '') {
            throw new RuntimeException('Add a wallet address to finish this task.');
        }
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet_address)) {
            throw new RuntimeException('Wallet address is invalid.');
        }
        $wallet_address = strtolower($wallet_address);
        $wallet_owner = $db->prepare("SELECT id FROM users WHERE wallet_address = ? AND id <> ? LIMIT 1");
        $wallet_owner->execute([$wallet_address, (int) $user_id]);
        if ($wallet_owner->fetch()) {
            throw new RuntimeException('This wallet is already linked to another CoinRex account.');
        }
        $wallet_update = $db->prepare("UPDATE users SET wallet_address = ?, updated_at = NOW() WHERE id = ?");
        $wallet_update->execute([$wallet_address, (int) $user_id]);
    }

    $reward = getTaskHubRewardAmountForTask((int) $user_id, $task_row, $log_row, $db);

    if ($reward <= 0) {
        throw new RuntimeException('TaskHub phase1 reward cap reached.');
    }

    $completed_at = date('Y-m-d H:i:s');
    $complete_stmt = $db->prepare("
        UPDATE user_task_logs
        SET status = 'completed',
            completed_at = ?,
            task_completed_at = ?,
            metadata = ?
        WHERE id = ?
          AND status <> 'completed'
    ");
    $complete_stmt->execute([
        $completed_at,
        $completed_at,
        json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        (int) $log_row['id'],
    ]);

    if ($complete_stmt->rowCount() < 1) {
        throw new RuntimeException('This task has already been completed.');
    }

    $entry = addRewardLedgerEntry(
        (int) $user_id,
        $reward,
        'mini_task',
        'taskhub_completion',
        'available',
        'taskhub:' . (string) ($task_row['task_key'] ?? $task_row['id']),
        $db,
        'phase1',
        'beginner'
    );

    creditReferralCommission((int) $user_id, $reward, 'taskhub', $db);

    if ((int) ($task_row['mission_step'] ?? 0) === 0) {
        taskHubCreateFollowupTasksAfterCheckIn((int) $user_id, (int) ($task_row['mission_day'] ?? 0), $completed_at, $db);
    }

    $user = syncTaskHubDayProgress((int) $user_id, $db);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }
    syncUserLevelStatus((int) $user_id, $db);

    return $entry;
}

/**
 * Validates a single quiz answer (used for real-time per-question checking).
 * Returns success only if ALL answers so far are correct.
 * NOTE: Must shuffle quiz choices the same way as getTaskHubState() so
 * the answer indices match what the frontend received.
 */
function taskHubValidateSingleQuizAnswer($user_id, array $task_row, array $log_row, array $answers, PDO $db = null): bool {
    $questions = taskHubGetQuizByTaskKey((string) ($task_row['task_key'] ?? ''), $db);
    if (empty($questions)) {
        return false;
    }

    // Shuffle the same way as getTaskHubState() so answer indices match.
    // Use the same seed: user_id + task_key
    $seed = (string) $user_id . '_' . (string) ($task_row['task_key'] ?? '');
    $questions = shuffleQuizChoices($questions, $seed);

    // Find the last answered question (ignore -1 / unanswered)
    $last_answered = -1;
    foreach ($answers as $idx => $val) {
        if ((int) $val >= 0) {
            $last_answered = (int) $idx;
        }
    }

    foreach ($questions as $index => $question) {
        if ($index > $last_answered) {
            // Don't check unanswered questions
            break;
        }
        $user_answer = (int) ($answers[$index] ?? -1);
        $correct_answers = taskHubNormalizeQuizAnswerIndexes($question['answer'] ?? []);
        if (!in_array($user_answer, $correct_answers, true)) {
            return false;
        }
    }

    return true;
}

function taskHubSubmitQuizTask($user_id, array $task_row, array $log_row, array $answers, PDO $db = null) {
    $db = $db ?: getDBConnection();
    $log_metadata = !empty($log_row['metadata']) ? (json_decode((string) $log_row['metadata'], true) ?: []) : [];

    $questions = taskHubGetQuizByTaskKey((string) ($task_row['task_key'] ?? ''), $db);
    if (empty($questions)) {
        throw new RuntimeException('Quiz definition not found.');
    }

    // Shuffle the same way as getTaskHubState() so answer indices match.
    // Use the same seed: user_id + task_key
    $questions = shuffleQuizChoices($questions, (string) $user_id . '_' . (string) ($task_row['task_key'] ?? ''));

    $score = 0;
    $detail = [];
    foreach ($questions as $index => $question) {
        $user_answer = (int) ($answers[$index] ?? -1);
        $correct_answers = taskHubNormalizeQuizAnswerIndexes($question['answer'] ?? []);
        $is_correct = in_array($user_answer, $correct_answers, true);
        if ($is_correct) {
            $score++;
        }
        $correct_answer_labels = [];
        foreach ($correct_answers as $correct_answer) {
            $correct_answer_labels[] = $question['choices'][$correct_answer] ?? 'Unknown';
        }
        $detail[] = [
            'question' => $question['question'],
            'correct' => $is_correct,
            'correct_answer' => implode(', ', $correct_answer_labels),
            'user_answer' => $user_answer >= 0 && isset($question['choices'][$user_answer]) ? $question['choices'][$user_answer] : '(none)',
        ];
    }

    $attempt_stmt = $db->prepare("
        INSERT INTO taskhub_quiz_attempts (user_id, task_id, mission_day, score, total_questions, answers_json)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $attempt_stmt->execute([
        (int) $user_id,
        (int) $task_row['id'],
        (int) ($task_row['mission_day'] ?? 0),
        (int) $score,
        count($questions),
        json_encode(array_values($answers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    if ($score < (int) ($task_row['min_quiz_score'] ?? 0)) {
        taskHubUpdateLog((int) $log_row['id'], [
            'status' => 'failed',
            'score' => (int) $score,
            'metadata' => ['last_score' => (int) $score, 'required_score' => (int) ($task_row['min_quiz_score'] ?? 0)],
        ], $db);
        throw new QuizFailedException('Pass the quiz to proceed. Score: ' . $score . '/' . count($questions), $detail);
    }

    return taskHubCompleteInstantTask((int) $user_id, $task_row, $log_row, ['quiz_score' => $score], $db);
}

/**
 * Exception that carries quiz detail (which questions were correct/wrong).
 */
class QuizFailedException extends RuntimeException {
    private array $detail;

    public function __construct(string $message, array $detail = []) {
        parent::__construct($message);
        $this->detail = $detail;
    }

    public function getDetail(): array {
        return $this->detail;
    }
}

function taskHubSubmitManualTask($user_id, array $task_row, array $log_row, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    $proof = trim((string) ($payload['proof'] ?? ''));
    $x_handle = trim((string) ($payload['x_handle'] ?? ''));
    $telegram_handle = trim((string) ($payload['telegram_handle'] ?? ''));

    if ((string) ($task_row['task_key'] ?? '') === 'day1_social_follow') {
        if ($x_handle === '' && $telegram_handle === '') {
            throw new RuntimeException('Add your X or Telegram username/URL for review.');
        }

        $proof_parts = [];
        if ($x_handle !== '') {
            $proof_parts[] = 'X: ' . $x_handle;
        }
        if ($telegram_handle !== '') {
            $proof_parts[] = 'Telegram: ' . $telegram_handle;
        }
        $proof = implode(' | ', $proof_parts);
    } elseif ((string) ($task_row['task_key'] ?? '') === 'day3_share_experience') {
        $platform = strtolower(trim((string) ($payload['platform'] ?? '')));
        $allowed_platforms = ['x', 'facebook', 'binance_square', 'medium', 'reddit'];
        if (!in_array($platform, $allowed_platforms, true)) {
            throw new RuntimeException('Select a valid platform (X, Facebook, Binance Square, Medium, Reddit).');
        }
        if ($proof === '' || filter_var($proof, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Paste a valid public post URL for review.');
        }

        $host = strtolower((string) (parse_url($proof, PHP_URL_HOST) ?? ''));
        $domain_map = [
            'x' => ['x.com', 'twitter.com'],
            'facebook' => ['facebook.com', 'fb.com'],
            'binance_square' => ['binance.com'],
            'medium' => ['medium.com'],
            'reddit' => ['reddit.com'],
        ];
        $allowed_hosts = $domain_map[$platform] ?? [];
        $host_ok = false;
        foreach ($allowed_hosts as $allowed_host) {
            if ($host === $allowed_host || substr($host, -strlen('.' . $allowed_host)) === '.' . $allowed_host) {
                $host_ok = true;
                break;
            }
        }
        if (!$host_ok) {
            throw new RuntimeException('The submitted URL does not match the selected platform domain.');
        }
    } elseif ($proof === '') {
        throw new RuntimeException('Proof is required for this task.');
    }

    // IMPORTANT: Manual/proof tasks must always go through admin review,
    // even when TESTING_MODE is enabled.

    taskHubUpdateLog((int) $log_row['id'], [
        'status' => 'submitted',
        'proof_data' => $proof,
        'metadata' => [
            'submitted_at' => date('Y-m-d H:i:s'),
            'x_handle' => $x_handle,
            'telegram_handle' => $telegram_handle,
            'platform' => strtolower(trim((string) ($payload['platform'] ?? ''))),
            'submitted_ip' => resolveClientIpAddress(),
            'submitted_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'submitted_referer' => substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255),
        ],
    ], $db);

    return ['submitted' => true];
}

function submitTaskHubTask($user_id, $task_key, array $payload = [], PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $user = getUserById((int) $user_id);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    // TESTING_MODE / LOCAL_TEST_MODE: Skip level check
    if ((!defined('TESTING_MODE') || !TESTING_MODE) && !LOCAL_TEST_MODE) {
        if (normalizeUserLevel($user['level'] ?? 'beginner') !== 'beginner') {
            throw new RuntimeException('TaskHub is available for Beginner accounts only.');
        }
    }

    enforceUserModuleAccess($user, 'taskhub');

    // TESTING_MODE / LOCAL_TEST_MODE: Skip security signals check (IP/device farming detection)
    if ((!defined('TESTING_MODE') || !TESTING_MODE) && !LOCAL_TEST_MODE) {
        $signals = getUserSecuritySignals((int) $user_id, $db);
        if (!empty($signals['is_suspicious'])) {
            logFraudEvent('taskhub_soft_security_signal', 'info', [
                'user_id' => (int) $user_id,
                'email' => (string) ($user['email'] ?? ''),
                'matching_accounts' => (int) ($signals['matching_accounts'] ?? 0),
                'matching_user_agents' => (int) ($signals['matching_user_agents'] ?? 0),
                'reasons' => $signals['reasons'] ?? [],
                'action' => 'allowed_taskhub_submit',
            ], $db);
        }
    }

    $user = syncTaskHubDayProgress((int) $user_id, $db);
    if (!$user) {
        throw new RuntimeException('User account not found.');
    }

    $task_stmt = $db->prepare("
        SELECT *
        FROM mini_tasks
        WHERE task_key = ?
          AND task_group = 'mission'
          AND is_active = 1
        LIMIT 1
    ");
    $task_stmt->execute([(string) $task_key]);
    $task_row = $task_stmt->fetch();
    if (!$task_row) {
        throw new RuntimeException('TaskHub task not found.');
    }
    if (!taskHubIsCoreMissionTask($task_row)) {
        throw new RuntimeException('This task is no longer part of TaskHub.');
    }

    // TESTING_MODE / LOCAL_TEST_MODE: Skip day progression check - testers can do all days
    if ((!defined('TESTING_MODE') || !TESTING_MODE) && !LOCAL_TEST_MODE) {
        if ((int) ($task_row['mission_day'] ?? 0) !== (int) ($user['current_day'] ?? 1)) {
            throw new RuntimeException('Complete previous tasks to continue.');
        }
    }

    $log_row = getTaskHubLatestLog((int) $user_id, (int) $task_row['id'], (int) ($task_row['mission_day'] ?? 0), $db);
    if (!$log_row) {
        throw new RuntimeException('This task is still locked.');
    }

    // TESTING_MODE / LOCAL_TEST_MODE: Allow re-submission even if previously submitted
    if ((!defined('TESTING_MODE') || !TESTING_MODE) && !LOCAL_TEST_MODE) {
        if ((string) ($log_row['status'] ?? '') === 'submitted') {
            throw new RuntimeException('This task is awaiting manual review.');
        }
    }

    // TESTING_MODE / LOCAL_TEST_MODE: Skip unlock timer cooldown check
    if ((!defined('TESTING_MODE') || !TESTING_MODE) && !LOCAL_TEST_MODE) {
        $available_at_ts = !empty($log_row['task_available_at']) ? strtotime((string) $log_row['task_available_at']) : 0;
        if ($available_at_ts > time()) {
            throw new RuntimeException('Next task unlocks in ' . taskHubFormatDuration(max(0, $available_at_ts - time())) . '.');
        }
    }

    if (($task_row['verification_mode'] ?? '') === 'quiz') {

        return taskHubSubmitQuizTask((int) $user_id, $task_row, $log_row, $payload['answers'] ?? [], $db);
    }

    if (!empty($task_row['requires_manual_review'])) {
        return taskHubSubmitManualTask((int) $user_id, $task_row, $log_row, $payload, $db);
    }

    return taskHubCompleteInstantTask((int) $user_id, $task_row, $log_row, $payload, $db);
}

/**
 * ============================================================
 * LEARNING SESSION MANAGEMENT (Secure Backend Validation)
 * ============================================================
 */

/**
 * Ensures the taskhub_learning_sessions table exists.
 */
function ensureTaskHubLearningSessionsSchema(PDO $db = null) {
    $db = $db ?: getDBConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS taskhub_learning_sessions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            task_id INT UNSIGNED NOT NULL,
            task_key VARCHAR(80) NOT NULL,
            session_token VARCHAR(128) NOT NULL,
            start_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_heartbeat TIMESTAMP NULL DEFAULT NULL,
            active_seconds INT UNSIGNED NOT NULL DEFAULT 0,
            required_seconds INT UNSIGNED NOT NULL DEFAULT 30,
            interruption_count INT UNSIGNED NOT NULL DEFAULT 0,
            max_scroll_depth INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('active','paused','invalid','completed') NOT NULL DEFAULT 'active',
            validation_failed_reason VARCHAR(255) DEFAULT NULL,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_learning_sessions_user (user_id),
            KEY idx_learning_sessions_task (task_key),
            KEY idx_learning_sessions_token (session_token),
            KEY idx_learning_sessions_status (status),
            KEY idx_learning_sessions_user_task (user_id, task_key, status),
            KEY idx_learning_sessions_heartbeat (last_heartbeat)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Generates a cryptographically secure session token.
 */
function taskHubGenerateSessionToken(): string {
    return bin2hex(random_bytes(32)); // 64-char hex token
}

/**
 * Creates a new learning session for a user/task.
 * Returns the session token.
 */
function taskHubCreateLearningSession(int $user_id, int $task_id, string $task_key, int $required_seconds = 30, PDO $db = null): string {
    $db = $db ?: getDBConnection();
    ensureTaskHubLearningSessionsSchema($db);

    // Invalidate any existing active sessions for this user+task
    $db->prepare("UPDATE taskhub_learning_sessions SET status = 'invalid', validation_failed_reason = 'new_session_created' WHERE user_id = ? AND task_key = ? AND status IN ('active', 'paused')")
       ->execute([$user_id, $task_key]);

    $token = taskHubGenerateSessionToken();
    $stmt = $db->prepare("
        INSERT INTO taskhub_learning_sessions (user_id, task_id, task_key, session_token, required_seconds, status)
        VALUES (?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([$user_id, $task_id, $task_key, $token, $required_seconds]);

    return $token;
}

/**
 * Processes a heartbeat for a learning session.
 * Returns the updated session data or null if invalid.
 */
function taskHubProcessHeartbeat(string $session_token, int $reported_active_seconds, bool $is_visible, bool $is_focused, int $scroll_depth, PDO $db = null): ?array {
    $db = $db ?: getDBConnection();
    ensureTaskHubLearningSessionsSchema($db);

    $stmt = $db->prepare("SELECT * FROM taskhub_learning_sessions WHERE session_token = ? LIMIT 1");
    $stmt->execute([$session_token]);
    $session = $stmt->fetch();

    if (!$session) {
        return null;
    }

    if (!in_array((string) ($session['status'] ?? ''), ['active', 'paused'], true)) {
        return null;
    }

    // Calculate server-side active time
    $now = time();
    $start_ts = strtotime((string) ($session['start_time'] ?? 'now'));
    $last_hb_ts = $session['last_heartbeat'] ? strtotime((string) $session['last_heartbeat']) : $start_ts;
    $elapsed = $now - $last_hb_ts;
    $trusted_elapsed = min($elapsed, 10);
    $new_active_seconds = (int) ($session['active_seconds'] ?? 0) + $trusted_elapsed;

    $current_max_depth = max((int) ($session['max_scroll_depth'] ?? 0), min(100, (int) $scroll_depth));
    $status = $is_visible && $is_focused ? 'active' : 'paused';

    $db->prepare("UPDATE taskhub_learning_sessions SET last_heartbeat = NOW(), active_seconds = ?, max_scroll_depth = ?, status = ? WHERE id = ?")
       ->execute([$new_active_seconds, $current_max_depth, $status, (int) $session['id']]);

    $stmt = $db->prepare("SELECT * FROM taskhub_learning_sessions WHERE id = ?");
    $stmt->execute([(int) $session['id']]);
    return $stmt->fetch() ?: null;
}


/**
 * Reports an interruption (tab close, refresh, navigation away).
 */
function taskHubReportInterruption(string $session_token, string $reason = 'tab_closed', PDO $db = null): void {
    $db = $db ?: getDBConnection();
    ensureTaskHubLearningSessionsSchema($db);

    $db->prepare("UPDATE taskhub_learning_sessions SET status = 'invalid', validation_failed_reason = ?, interruption_count = interruption_count + 1 WHERE session_token = ? AND status IN ('active', 'paused')")
       ->execute([$reason, $session_token]);
}


function reviewTaskHubSubmission($log_id, $approve, PDO $db = null, array $options = []) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);

    $stmt = $db->prepare("
        SELECT utl.*, mt.task_key, mt.verification_mode, mt.reward, mt.mission_day, mt.mission_step, mt.task_group, mt.title
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        WHERE utl.id = ?
          AND utl.status = 'submitted'
        LIMIT 1
    ");
    $stmt->execute([(int) $log_id]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Submission not found.');
    }

    $review_note = trim((string) ($options['review_note'] ?? ''));
    $return_for_correction = !empty($options['return_for_correction']);

    if (!$approve && $return_for_correction) {
        $task_group = (string) ($row['task_group'] ?? 'mission');
        $task_label = $task_group === 'boosthub' ? 'BoostHub' : 'LearnHub';
        $action_url = $task_group === 'boosthub' ? '/public/boosthub.php' : '/public/taskhub.php';
        $metadata = !empty($row['metadata']) ? (json_decode((string) $row['metadata'], true) ?: []) : [];
        $metadata['reviewed_at'] = date('Y-m-d H:i:s');
        $metadata['review_outcome'] = 'returned_for_correction';
        $metadata['correction_requested'] = true;
        $metadata['correction_note'] = $review_note !== '' ? $review_note : 'Please update your evidence so the admin team can verify it.';

        taskHubUpdateLog((int) $row['id'], [
            'status' => 'failed',
            'metadata' => $metadata,
        ], $db);

        createNotification('user', (int) $row['user_id'], [
            'template_key' => null,
            'event_key' => $task_group === 'boosthub' ? 'boosthub.evidence.returned' : 'taskhub.evidence.returned',
            'title' => $task_label . ' evidence needs correction',
            'message' => 'Your evidence for "' . (string) ($row['title'] ?? ($task_label . ' task')) . '" could not be verified. ' . $metadata['correction_note'],
            'action_url' => $action_url,
            'priority' => 'high',
            'meta' => [
                'log_id' => (int) $row['id'],
                'task_id' => (int) $row['task_id'],
                'task_group' => $task_group,
                'review_note' => $metadata['correction_note'],
            ],
        ], $db);

        return [
            'approved' => false,
            'returned' => true,
            'task_group' => $task_group,
        ];
    }

    if (!$approve) {
        // Track rejection count for this user+task
        $rejection_count = (int) ($row['rejection_count'] ?? 0) + 1;
        $db->prepare("UPDATE user_task_logs SET rejection_count = ? WHERE id = ?")
           ->execute([$rejection_count, (int) $row['id']]);

        taskHubUpdateLog((int) $row['id'], [
            'status' => 'failed',
            'metadata' => [
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_outcome' => 'rejected',
                'rejection_count' => $rejection_count,
            ],
        ], $db);

        // If 3rd LearnHub rejection, reverse mission rewards and reset user to Day 1.
        // BoostHub final rejections do not reset the LearnHub streak.
        if ((string) ($row['task_group'] ?? 'mission') === 'mission' && $rejection_count >= 3) {
            taskHubReverseTaskHubRewards((int) $row['user_id'], $db);
            taskHubResetUserToDay1((int) $row['user_id'], $db);
            return [
                'approved' => false,
                'reset' => true,
                'message' => 'User has been reset to Day 1 due to 3 rejections.',
            ];
        }

        return ['approved' => false];
    }

    if ((string) ($row['task_group'] ?? 'mission') === 'boosthub') {
        $reward = round((float) ($row['reward'] ?? 0), 8);
        if ($reward <= 0) {
            throw new RuntimeException('BoostHub reward amount is invalid.');
        }
    } else {
        $reward = getTaskHubRewardAmountForTask((int) $row['user_id'], $row, $row, $db);
        if ($reward <= 0) {
            throw new RuntimeException('TaskHub phase1 reward cap reached.');
        }
    }

    $completed_at = date('Y-m-d H:i:s');
    $existing_metadata = !empty($row['metadata']) ? (json_decode((string) $row['metadata'], true) ?: []) : [];
    $existing_metadata['reviewed_at'] = $completed_at;
    $existing_metadata['review_outcome'] = 'approved';
    taskHubUpdateLog((int) $row['id'], [
        'status' => 'completed',
        'completed_at' => $completed_at,
        'task_completed_at' => $completed_at,
        'metadata' => $existing_metadata,
    ], $db);

    $entry = addRewardLedgerEntry(
        (int) $row['user_id'],
        $reward,
        'mini_task',
        (string) ($row['task_group'] ?? 'mission') === 'boosthub' ? 'boosthub_manual_approval' : 'taskhub_manual_approval',
        'available',
        ((string) ($row['task_group'] ?? 'mission') === 'boosthub' ? 'boosthub:' : 'taskhub:') . (string) ($row['task_key'] ?? $row['task_id']),
        $db,
        'phase1',
        'beginner'
    );

    creditReferralCommission(
        (int) $row['user_id'],
        $reward,
        (string) ($row['task_group'] ?? 'mission') === 'boosthub' ? 'boosthub' : 'taskhub',
        $db
    );

    if ((string) ($row['task_group'] ?? 'mission') === 'mission') {
        syncTaskHubDayProgress((int) $row['user_id'], $db);
    }
    syncUserLevelStatus((int) $row['user_id'], $db);
    return ['approved' => true, 'entry' => $entry, 'task_group' => (string) ($row['task_group'] ?? 'mission')];
}


function ensureTaskHubRejectionSchema(PDO $db = null) {
    static $schema_ready = false;
    if ($schema_ready) { return; }
    $db = $db ?: getDBConnection();
    if (!tableHasColumn('user_task_logs', 'rejection_count')) {
        try {
            $db->exec("ALTER TABLE user_task_logs ADD COLUMN rejection_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER status");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), '1060') === false && stripos($e->getMessage(), 'Duplicate column') === false) {
                throw $e;
            }
        }
    }
    $schema_ready = true;
}

function taskHubReverseTaskHubRewards($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);
    $stmt = $db->prepare("SELECT id, amount, source, action_type, reference_id FROM reward_ledger WHERE user_id = ? AND source = 'mini_task' AND action_type IN ('taskhub_completion', 'taskhub_manual_approval') AND amount > 0 AND status IN ('available', 'locked')");
    $stmt->execute([(int) $user_id]);
    $entries = $stmt->fetchAll();
    $total_reversed = 0;
    foreach ($entries as $entry) {
        $amount = (float) ($entry['amount'] ?? 0);
        if ($amount <= 0) { continue; }
        addRewardLedgerEntry((int) $user_id, -$amount, 'mini_task', 'taskhub_reversal', 'available', 'reversal:' . (string) ($entry['reference_id'] ?? $entry['id']), $db, 'phase1', 'beginner');
        $db->prepare("UPDATE reward_ledger SET status = 'reversed', updated_at = NOW() WHERE id = ?")->execute([(int) $entry['id']]);
        $total_reversed += $amount;
    }
    return $total_reversed;
}

function taskHubResetUserToDay1($user_id, PDO $db = null) {
    $db = $db ?: getDBConnection();
    ensureRewardClaimSchema($db);
    $db->prepare("DELETE utl FROM user_task_logs utl INNER JOIN mini_tasks mt ON mt.id = utl.task_id WHERE utl.user_id = ? AND mt.task_group = 'mission'")->execute([(int) $user_id]);
    $db->prepare("UPDATE users SET current_day = 1, last_day_completed_at = NULL, updated_at = NOW() WHERE id = ?")->execute([(int) $user_id]);
    $db->prepare("DELETE FROM taskhub_quiz_attempts WHERE user_id = ?")->execute([(int) $user_id]);
    if (tableExists('taskhub_learning_sessions')) {
        $db->prepare("DELETE FROM taskhub_learning_sessions WHERE user_id = ?")->execute([(int) $user_id]);
    }
}

function taskHubGetBoostRequirementByDay($mission_day) {
    return 0.0;
}

function taskHubGetBoostGatewayTask($mission_day) {
    return null;
}
