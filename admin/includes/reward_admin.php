<?php

function adminRewardTaskCategories() {
    return [
        'youtube_subscribe' => 'YouTube Subscription',
        'x_follow' => 'X Follow',
        'telegram_join' => 'Telegram Join',
        'paid_promotion' => 'Paid Promotion',
        'website_visit' => 'Website Visit',
        'community_engagement' => 'Community Engagement',
        'custom' => 'Custom Task',
    ];
}

function adminRewardTaskGroups() {
    return [
        'mission' => 'MicroMission (10 Days)',
        'boosthub' => 'BoostHub Pool',
        'legacy' => 'Legacy Tasks',
    ];
}

function adminRewardDefaultCtaLabel($task_category) {
    $map = [
        'youtube_subscribe' => 'Open YouTube',
        'x_follow' => 'Open X',
        'telegram_join' => 'Open Telegram',
        'paid_promotion' => 'Open Campaign',
        'website_visit' => 'Open Link',
        'community_engagement' => 'Open Task',
        'custom' => 'Open Task',
    ];

    return $map[(string) $task_category] ?? 'Open Task';
}

function adminRewardProcessAction(PDO $db, array $current_admin) {
    $message = '';
    $message_type = 'success';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return [$message, $message_type];
    }

    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $action_type = (string) ($_POST['action_type'] ?? '');

    try {
        if ($action_type === 'save_task') {
            $task_id = (int) ($_POST['task_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $reward = max(0, (float) ($_POST['reward'] ?? 0));
            $daily_limit = max(1, (int) ($_POST['daily_limit'] ?? 1));
            $cooldown_seconds = max(0, (int) ($_POST['cooldown_seconds'] ?? 0));
            $is_active = !empty($_POST['is_active']) ? 1 : 0;
            $task_group = trim((string) ($_POST['task_group'] ?? 'boosthub'));
            $task_category = trim((string) ($_POST['task_category'] ?? 'custom'));
            $task_link = trim((string) ($_POST['task_link'] ?? ''));
            $completion_steps = trim((string) ($_POST['completion_steps'] ?? ''));
            $proof_notes = trim((string) ($_POST['proof_notes'] ?? ''));
            $cta_label = trim((string) ($_POST['cta_label'] ?? ''));
            $learning_title = trim((string) ($_POST['learning_title'] ?? ''));
            $learning_url = trim((string) ($_POST['learning_url'] ?? ''));
            $day_title = trim((string) ($_POST['day_title'] ?? ''));
            $required_reading_seconds = max(15, min(120, (int) ($_POST['required_reading_seconds'] ?? 45)));

            if ($title === '' || $description === '' || $reward <= 0) {

                throw new RuntimeException('Task title, short description, and reward are required.');
            }

            if (!in_array($task_group, ['mission', 'boosthub', 'legacy'], true)) {
                $task_group = 'boosthub';
            }

            if (!array_key_exists($task_category, adminRewardTaskCategories())) {
                $task_category = 'custom';
            }

            if ($task_link !== '' && filter_var($task_link, FILTER_VALIDATE_URL) === false) {
                throw new RuntimeException('Destination link must be a valid URL.');
            }

            if ($completion_steps === '') {
                throw new RuntimeException('Explain how the user should complete this task.');
            }

            if ($cta_label === '') {
                $cta_label = adminRewardDefaultCtaLabel($task_category);
            }

            $mission_day = isset($_POST['mission_day']) ? (int) $_POST['mission_day'] : null;
            $mission_step = isset($_POST['mission_step']) ? (int) $_POST['mission_step'] : null;
            $verification_mode = trim((string) ($_POST['verification_mode'] ?? 'instant'));
            $requires_quiz = !empty($_POST['requires_quiz']) ? 1 : 0;
            $requires_manual_review = !empty($_POST['requires_manual_review']) ? 1 : 0;
            $min_quiz_score = max(0, (int) ($_POST['min_quiz_score'] ?? 0));

            $allowed_verification_modes = ['instant', 'profile', 'manual', 'quiz', 'wallet', 'boosthub', 'mystery', 'claim_awareness'];
            if (!in_array($verification_mode, $allowed_verification_modes, true)) {
                $verification_mode = 'instant';
            }

            if ($verification_mode !== 'quiz') {
                $requires_quiz = 0;
                $min_quiz_score = 0;
            }

            if ($verification_mode === 'manual') {
                $requires_manual_review = 1;
            }

            if ($task_group !== 'mission') {
                $mission_day = null;
                $mission_step = null;
            } else {
                if ($mission_day === null || $mission_day <= 0 || $mission_day > (int) TASKHUB_TOTAL_DAYS) {
                    throw new RuntimeException('Mission day must be between 1 and ' . (int) TASKHUB_TOTAL_DAYS . '.');
                }
                if ($mission_step === null || $mission_step < 0) {
                    throw new RuntimeException('Mission step must be 0 or higher.');
                }
            }
            // Auto-generate task_key for new mission tasks
            $auto_task_key = '';
            if ($task_id <= 0 && $task_group === 'mission') {
                $count_stmt = $db->prepare("SELECT COUNT(*) FROM mini_tasks WHERE task_group = 'mission' AND mission_day = ? AND task_key LIKE 'day{$mission_day}_custom_%'");
                $count_stmt->execute([(int) $mission_day]);
                $next_num = (int) ($count_stmt->fetchColumn() ?: 0) + 1;
                $auto_task_key = 'day' . (int) $mission_day . '_custom_' . $next_num;
            }

            if ($task_id > 0) {
                $stmt = $db->prepare("
                    UPDATE mini_tasks
                    SET title = ?, description = ?, reward = ?, daily_limit = ?, cooldown_seconds = ?, is_active = ?, task_group = ?,
                        mission_day = ?, mission_step = ?, verification_mode = ?, requires_quiz = ?, requires_manual_review = ?, min_quiz_score = ?,
                        task_category = ?, task_link = ?, completion_steps = ?, proof_notes = ?, cta_label = ?,
                        learning_title = ?, learning_url = ?, day_title = ?, required_reading_seconds = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title, $description, $reward, $daily_limit, $cooldown_seconds, $is_active, $task_group,
                    $mission_day, $mission_step, $verification_mode, $requires_quiz, $requires_manual_review, $min_quiz_score,
                    $task_category, $task_link, $completion_steps, $proof_notes, $cta_label,
                    $learning_title, $learning_url, $day_title, $required_reading_seconds, $task_id
                ]);
                logAdminActivity((int) $current_admin['id'], 'mini_task_update', 'mini_task', (string) $task_id, json_encode(['title' => $title], JSON_UNESCAPED_UNICODE));
                $message = 'Task updated.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO mini_tasks (
                        title, description, reward, daily_limit, cooldown_seconds, is_active, task_group,
                        mission_day, mission_step, verification_mode, requires_quiz, requires_manual_review, min_quiz_score,
                        task_category, task_link, completion_steps, proof_notes, cta_label,
                        learning_title, learning_url, day_title, required_reading_seconds, task_key
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $description, $reward, $daily_limit, $cooldown_seconds, $is_active, $task_group,
                    $mission_day, $mission_step, $verification_mode, $requires_quiz, $requires_manual_review, $min_quiz_score,
                    $task_category, $task_link, $completion_steps, $proof_notes, $cta_label,
                    $learning_title, $learning_url, $day_title, $required_reading_seconds, $auto_task_key
                ]);
                $new_id = (int) $db->lastInsertId();
                logAdminActivity((int) $current_admin['id'], 'mini_task_create', 'mini_task', (string) $new_id, json_encode(['title' => $title], JSON_UNESCAPED_UNICODE));
                $message = 'Task created.';
            }
        } elseif ($action_type === 'save_day_title') {
            $mission_day = (int) ($_POST['mission_day'] ?? 0);
            $day_title = trim((string) ($_POST['day_title'] ?? ''));
            if ($mission_day <= 0 || $mission_day > (int) TASKHUB_TOTAL_DAYS) {
                throw new RuntimeException('Invalid mission day.');
            }
            $stmt = $db->prepare("UPDATE mini_tasks SET day_title = ? WHERE task_group = 'mission' AND mission_day = ?");
            $stmt->execute([$day_title, $mission_day]);
            logAdminActivity((int) $current_admin['id'], 'mini_task_day_title', 'mini_task', (string) $mission_day, json_encode(['day_title' => $day_title], JSON_UNESCAPED_UNICODE));
            $message = 'Day ' . $mission_day . ' title updated to "' . $day_title . '".';
        } elseif ($action_type === 'delete_task') {

            $task_id = (int) ($_POST['task_id'] ?? 0);
            if ($task_id <= 0) {
                throw new RuntimeException('Valid task ID is required.');
            }
            $stmt = $db->prepare("DELETE FROM mini_tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            logAdminActivity((int) $current_admin['id'], 'mini_task_delete', 'mini_task', (string) $task_id, '');
            $message = 'Task deleted.';
        } elseif ($action_type === 'review_taskhub_submission') {
            $log_id = (int) ($_POST['log_id'] ?? 0);
            $decision = (string) ($_POST['decision'] ?? '');
            $review_note = trim((string) ($_POST['review_note'] ?? ''));
            if ($log_id <= 0 || !in_array($decision, ['approve', 'reject', 'return'], true)) {
                throw new RuntimeException('Invalid LearnHub review action.');
            }

            $result = reviewTaskHubSubmission($log_id, $decision === 'approve', $db, [
                'return_for_correction' => $decision === 'return',
                'review_note' => $review_note,
            ]);
            logAdminActivity((int) $current_admin['id'], 'taskhub_submission_review', 'user_task_log', (string) $log_id, json_encode(['decision' => $decision, 'review_note' => $review_note], JSON_UNESCAPED_UNICODE));
            $label = (string) ($result['task_group'] ?? 'mission') === 'boosthub' ? 'BoostHub' : 'LearnHub';
            if (!empty($result['returned'])) {
                $message = $label . ' submission returned for correction.';
            } else {
                $message = !empty($result['approved']) ? ($label . ' submission approved.') : ($label . ' submission rejected.');
            }
        } elseif ($action_type === 'toggle_freeze') {
            $user_id = (int) ($_POST['user_id'] ?? 0);
            $reward_frozen = !empty($_POST['reward_frozen']) ? 1 : 0;
            if ($user_id <= 0) {
                throw new RuntimeException('Valid user is required.');
            }

            $stmt = $db->prepare("UPDATE users SET reward_frozen = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$reward_frozen, $user_id]);
            logAdminActivity((int) $current_admin['id'], 'reward_freeze_toggle', 'user', (string) $user_id, json_encode(['reward_frozen' => $reward_frozen], JSON_UNESCAPED_UNICODE));
            $message = $reward_frozen ? 'Rewards frozen for user.' : 'Reward freeze lifted.';
        } elseif ($action_type === 'referral_state') {
            $user_id = (int) ($_POST['user_id'] ?? 0);
            $decision = (string) ($_POST['decision'] ?? '');
            $flag_reason = trim((string) ($_POST['flag_reason'] ?? ''));
            if ($user_id <= 0 || !in_array($decision, ['qualify', 'invalidate', 'flag_manual_review', 'reset_pending'], true)) {
                throw new RuntimeException('Invalid referral action.');
            }
            applyReferralDecision($user_id, $decision, (int) ($current_admin['id'] ?? 0), $flag_reason !== '' ? $flag_reason : null, $db);
            logAdminActivity((int) $current_admin['id'], 'referral_validation', 'user', (string) $user_id, json_encode(['decision' => $decision], JSON_UNESCAPED_UNICODE));
            if ($decision === 'qualify') {
                $message = 'Referral marked valid.';
            } elseif ($decision === 'invalidate') {
                $message = 'Referral marked invalid.';
            } elseif ($decision === 'flag_manual_review') {
                $message = 'Referral flagged for manual review.';
            } else {
                $message = 'Referral moved back to pending.';
            }
        } else {
            throw new RuntimeException('Unknown admin action.');
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $message = $e->getMessage();
        $message_type = 'error';
    }

    return [$message, $message_type];
}

function adminRewardGetSummary(PDO $db) {
    $ledger_totals = $db->query("
        SELECT
            COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS earned_total,
            COALESCE(SUM(CASE WHEN status = 'available' THEN amount ELSE 0 END), 0) AS available_total,
            COALESCE(SUM(CASE WHEN status = 'locked' THEN amount ELSE 0 END), 0) AS locked_total,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_total,
            COALESCE(SUM(CASE WHEN status = 'claimed' THEN amount ELSE 0 END), 0) AS claimed_total
        FROM reward_ledger
    ")->fetch();

    return [
        'ledger_totals' => $ledger_totals,
        'open_claims' => (int) ($db->query("SELECT COUNT(*) FROM claim_snapshots WHERE status = 'generated'")->fetchColumn() ?: 0),
        'frozen_accounts' => (int) ($db->query("SELECT COUNT(*) FROM users WHERE reward_frozen = 1")->fetchColumn() ?: 0),
        'active_tasks' => (int) ($db->query("SELECT COUNT(*) FROM mini_tasks WHERE is_active = 1")->fetchColumn() ?: 0),
        'suspicious_accounts' => (int) ($db->query("
            SELECT COUNT(*)
            FROM users
            WHERE login_attempts >= " . ANTI_FARM_MAX_LOGIN_ATTEMPTS . "
               OR (signup_ip IS NOT NULL AND signup_ip <> '' AND signup_ip = last_ip AND signup_ip <> '')
        ")->fetchColumn() ?: 0),
        'taskhub_reviews' => (int) ($db->query("
            SELECT COUNT(*)
            FROM user_task_logs utl
            INNER JOIN mini_tasks mt ON mt.id = utl.task_id
            WHERE mt.task_group IN ('mission', 'boosthub')
              AND utl.status = 'submitted'
        ")->fetchColumn() ?: 0),
        'referrals_pending' => (int) ($db->query("
            SELECT COUNT(*)
            FROM users
            WHERE referred_by IS NOT NULL
              AND referral_qualified_at IS NULL
        ")->fetchColumn() ?: 0),
    ];
}

function adminRewardGetTopClaimedUsers(PDO $db, $limit = 10) {
    $limit = max(1, (int) $limit);
    $sql = "
        SELECT
            cs.user_id,
            COALESCE(u.username, CONCAT('User ', cs.user_id)) AS username,
            SUM(COALESCE(cs.total_amount, 0)) AS claimed_total
        FROM claim_snapshots cs
        LEFT JOIN users u ON u.id = cs.user_id
        WHERE cs.status IN ('generated', 'used')
        GROUP BY cs.user_id, u.username
        ORDER BY claimed_total DESC, cs.user_id ASC
        LIMIT {$limit}
    ";

    return $db->query($sql)->fetchAll();
}

function adminRewardGetTopRexHolders(PDO $db, $limit = 10) {
    $limit = max(1, (int) $limit);
    $sql = "
        SELECT
            u.id AS user_id,
            COALESCE(u.username, CONCAT('User ', u.id)) AS username,
            COALESCE(bal.available_balance, 0) AS available_balance,
            COALESCE(bal.locked_balance, 0) AS locked_balance,
            (COALESCE(bal.available_balance, 0) + COALESCE(bal.locked_balance, 0)) AS total_balance
        FROM users u
        LEFT JOIN (
            SELECT
                user_id,
                SUM(CASE WHEN status = 'available' THEN amount ELSE 0 END) AS available_balance,
                SUM(CASE WHEN status = 'locked' THEN amount ELSE 0 END) AS locked_balance
            FROM reward_ledger
            GROUP BY user_id
        ) bal ON bal.user_id = u.id
        ORDER BY total_balance DESC, available_balance DESC, u.id ASC
        LIMIT {$limit}
    ";

    return $db->query($sql)->fetchAll();
}

function adminRewardGetTasks(PDO $db, $task_group, $mission_day = 0) {
    $sql = "
        SELECT
            mt.*,
            COALESCE(stats.completed_total, 0) AS completed_total,
            COALESCE(stats.completed_today, 0) AS completed_today,
            COALESCE(stats.blocked_total, 0) AS blocked_total
        FROM mini_tasks mt
        LEFT JOIN (
            SELECT
                task_id,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_total,
                SUM(CASE WHEN status = 'completed' AND DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END) AS completed_today,
                SUM(CASE WHEN status = 'blocked' THEN 1 ELSE 0 END) AS blocked_total
            FROM user_task_logs
            GROUP BY task_id
        ) stats ON stats.task_id = mt.id
        WHERE mt.task_group = ?
    ";
    $params = [(string) $task_group];

    if ((string) $task_group === 'mission' && (int) $mission_day > 0) {
        $sql .= " AND mt.mission_day = ? ";
        $params[] = (int) $mission_day;
    }

    if ((string) $task_group === 'mission') {
        $sql .= " ORDER BY mt.mission_day ASC, mt.mission_step ASC, mt.id ASC ";
    } else {
        $sql .= " ORDER BY mt.id ASC ";
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function adminRewardGetTaskhubReviewRows(PDO $db) {
    ensureTaskHubRejectionSchema($db);
    return $db->query("
        SELECT
            utl.id,
            utl.user_id,
            utl.proof_data,
            utl.metadata,
            utl.task_available_at,
            utl.mission_day,
            utl.mission_step,
            utl.completed_at AS created_at,
            utl.rejection_count,
            mt.title,
            mt.task_key,
            mt.task_group,
            u.username,
            u.email
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        INNER JOIN users u ON u.id = utl.user_id
        WHERE mt.task_group = 'mission'
          AND utl.status = 'submitted'
        ORDER BY utl.id DESC
        LIMIT 40
    ")->fetchAll();
}

function adminRewardGetBoosthubReviewRows(PDO $db) {
    return $db->query("
        SELECT
            utl.id,
            utl.user_id,
            utl.proof_data,
            utl.task_available_at,
            utl.mission_day,
            utl.mission_step,
            utl.completed_at AS created_at,
            mt.title,
            mt.task_key,
            mt.task_group,
            mt.task_category,
            mt.task_link,
            mt.proof_notes,
            mt.reward,
            u.username,
            u.email
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        INNER JOIN users u ON u.id = utl.user_id
        WHERE mt.task_group = 'boosthub'
          AND utl.status = 'submitted'
        ORDER BY utl.id DESC
        LIMIT 40
    ")->fetchAll();
}

/**
 * Fetch all BoostHub evidence submissions with pagination and filtering.
 *
 * @param PDO    $db
 * @param string $task_category Filter by task category (or 'all')
 * @param string $status_filter Filter by evidence status: 'submitted', 'completed', 'failed', or 'all'
 * @param int    $page          Current page (1-based)
 * @param int    $perPage       Items per page
 * @param int    $user_id       Optional user filter
 * @return array ['rows' => array, 'total' => int, 'pages' => int]
 */
function adminRewardGetBoosthubAllEvidence(PDO $db, string $task_category = 'all', string $status_filter = 'all', int $page = 1, int $perPage = 20, int $user_id = 0): array {
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = "WHERE mt.task_group = 'boosthub'";
    $params = [];

    if ($task_category !== 'all') {
        $where .= " AND mt.task_category = ?";
        $params[] = $task_category;
    }

    if ($status_filter !== 'all') {
        $where .= " AND utl.status = ?";
        $params[] = $status_filter;
    }

    if ($user_id > 0) {
        $where .= " AND utl.user_id = ?";
        $params[] = $user_id;
    }

    // Count total
    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        INNER JOIN users u ON u.id = utl.user_id
        $where
    ");
    $countStmt->execute($params);
    $total = (int) ($countStmt->fetchColumn() ?: 0);
    $pages = (int) ceil($total / $perPage);

    // Fetch rows
    $stmt = $db->prepare("
        SELECT
            utl.id,
            utl.user_id,
            utl.proof_data,
            utl.status,
            utl.metadata,
            utl.rejection_count,
            utl.task_available_at,
            utl.completed_at,
            mt.title,
            mt.task_key,
            mt.task_group,
            mt.task_category,
            mt.task_link,
            mt.proof_notes,
            mt.reward,
            u.username,
            u.email
        FROM user_task_logs utl
        INNER JOIN mini_tasks mt ON mt.id = utl.task_id
        INNER JOIN users u ON u.id = utl.user_id
        $where
        ORDER BY utl.id DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    return ['rows' => $rows, 'total' => $total, 'pages' => $pages];
}

function adminRewardGetUsers(PDO $db, $user_search = '', $page = 1, $perPage = 20) {
    $offset = max(0, ((int) $page - 1) * (int) $perPage);
    $limit = max(1, (int) $perPage);
    $user_sql = "
        SELECT
            u.id,
            u.full_name,
            u.username,
            u.email,
            u.level,
            u.status,
            u.reward_frozen,
            u.valid_referrals,
            u.approved_reviews_count,
            u.login_attempts,
            u.signup_ip,
            u.last_ip,
            COALESCE(bal.available_balance, 0) AS available_balance,
            COALESCE(bal.locked_balance, 0) AS locked_balance
        FROM users u
        LEFT JOIN (
            SELECT
                user_id,
                SUM(CASE WHEN status = 'available' THEN amount ELSE 0 END) AS available_balance,
                SUM(CASE WHEN status = 'locked' THEN amount ELSE 0 END) AS locked_balance
            FROM reward_ledger
            GROUP BY user_id
        ) bal ON bal.user_id = u.id
    ";
    $user_params = [];
    if ($user_search !== '') {
        $needle = '%' . $user_search . '%';
        $user_sql .= " WHERE u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? ";
        $user_params = [$needle, $needle, $needle];
    }
    $user_sql .= " ORDER BY u.id DESC LIMIT {$limit} OFFSET {$offset}";
    $user_stmt = $db->prepare($user_sql);
    $user_stmt->execute($user_params);
    return $user_stmt->fetchAll();
}

function adminRewardGetUsersCount(PDO $db, $user_search = '') {
    $sql = "SELECT COUNT(*) FROM users u";
    $params = [];
    if ($user_search !== '') {
        $needle = '%' . $user_search . '%';
        $sql .= " WHERE u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? ";
        $params = [$needle, $needle, $needle];
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function adminRewardGetLedgerRows(PDO $db, array $filters = [], $page = 1, $perPage = 20) {
    $user_filter = trim((string) ($filters['user'] ?? ''));
    $source_filter = trim((string) ($filters['source'] ?? ''));
    $phase_filter = trim((string) ($filters['phase'] ?? ''));
    $status_filter = trim((string) ($filters['status'] ?? ''));
    $offset = max(0, ((int) $page - 1) * (int) $perPage);
    $limit = max(1, (int) $perPage);

    $ledger_sql = "
        SELECT rl.id, rl.user_id, rl.source, rl.reward_phase, rl.action_type, rl.amount, rl.status, rl.reference_id, rl.created_at, u.username
        FROM reward_ledger rl
        LEFT JOIN users u ON u.id = rl.user_id
        WHERE 1=1
    ";
    $ledger_params = [];
    if ($user_filter !== '') {
        $ledger_sql .= " AND (u.username LIKE ? OR CAST(rl.user_id AS CHAR) = ?) ";
        $ledger_params[] = '%' . $user_filter . '%';
        $ledger_params[] = $user_filter;
    }
    if ($source_filter !== '' && in_array($source_filter, ['mini_task', 'referral', 'review', 'bonus'], true)) {
        $ledger_sql .= " AND rl.source = ? ";
        $ledger_params[] = $source_filter;
    }
    if ($phase_filter !== '' && in_array($phase_filter, ['phase1', 'phase2'], true)) {
        $ledger_sql .= " AND rl.reward_phase = ? ";
        $ledger_params[] = $phase_filter;
    }
    if ($status_filter !== '' && in_array($status_filter, ['pending', 'locked', 'available', 'claimed'], true)) {
        $ledger_sql .= " AND rl.status = ? ";
        $ledger_params[] = $status_filter;
    }
    $ledger_sql .= " ORDER BY rl.id DESC LIMIT {$limit} OFFSET {$offset}";
    $ledger_stmt = $db->prepare($ledger_sql);
    $ledger_stmt->execute($ledger_params);
    return $ledger_stmt->fetchAll();
}

function adminRewardGetLedgerCount(PDO $db, array $filters = []) {
    $user_filter = trim((string) ($filters['user'] ?? ''));
    $source_filter = trim((string) ($filters['source'] ?? ''));
    $phase_filter = trim((string) ($filters['phase'] ?? ''));
    $status_filter = trim((string) ($filters['status'] ?? ''));

    $sql = "
        SELECT COUNT(*)
        FROM reward_ledger rl
        LEFT JOIN users u ON u.id = rl.user_id
        WHERE 1=1
    ";
    $params = [];
    if ($user_filter !== '') {
        $sql .= " AND (u.username LIKE ? OR CAST(rl.user_id AS CHAR) = ?) ";
        $params[] = '%' . $user_filter . '%';
        $params[] = $user_filter;
    }
    if ($source_filter !== '' && in_array($source_filter, ['mini_task', 'referral', 'review', 'bonus'], true)) {
        $sql .= " AND rl.source = ? ";
        $params[] = $source_filter;
    }
    if ($phase_filter !== '' && in_array($phase_filter, ['phase1', 'phase2'], true)) {
        $sql .= " AND rl.reward_phase = ? ";
        $params[] = $phase_filter;
    }
    if ($status_filter !== '' && in_array($status_filter, ['pending', 'locked', 'available', 'claimed'], true)) {
        $sql .= " AND rl.status = ? ";
        $params[] = $status_filter;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function adminRewardGetClaimRows(PDO $db, $page = 1, $perPage = 20) {
    $offset = max(0, ((int) $page - 1) * (int) $perPage);
    $limit = max(1, (int) $perPage);
    return $db->query("
        SELECT cs.id, cs.user_id, cs.total_amount, cs.nonce, cs.status, cs.created_at, u.username, u.level, u.reward_frozen
        FROM claim_snapshots cs
        LEFT JOIN users u ON u.id = cs.user_id
        ORDER BY cs.id DESC
        LIMIT {$limit} OFFSET {$offset}
    ")->fetchAll();
}

function adminRewardGetClaimCount(PDO $db) {
    return (int) ($db->query("SELECT COUNT(*) FROM claim_snapshots")->fetchColumn() ?: 0);
}

function adminRewardGetReferralRows(PDO $db, $page = 1, $perPage = 20, $search = '', $status_filter = '') {
    $offset = max(0, ((int) $page - 1) * (int) $perPage);
    $limit = max(1, (int) $perPage);

    $sql = "
        SELECT
            child.id,
            child.username,
            child.full_name,
            child.created_at,
            child.referral_review_status,
            child.referral_flag_reason,
            child.referral_reviewed_at,
            child.referral_qualified_at,
            child.valid_referrals,
            child.referral_abuse_detected,
            child.referral_abuse_reason,
            parent.username AS referrer_username,
            parent.id AS referrer_id
        FROM users child
        INNER JOIN users parent ON parent.id = child.referred_by
        WHERE 1=1
    ";
    $params = [];

    if ($search !== '') {
        $needle = '%' . $search . '%';
        $sql .= " AND (child.full_name LIKE ? OR child.username LIKE ? OR child.email LIKE ? OR parent.username LIKE ?) ";
        $params = array_merge($params, [$needle, $needle, $needle, $needle]);
    }

    if ($status_filter !== '') {
        if ($status_filter === 'qualified') {
            $sql .= " AND child.referral_review_status = 'qualified' ";
        } elseif (in_array($status_filter, ['pending', 'flagged_manual_review', 'invalid'], true)) {
            $sql .= " AND COALESCE(child.referral_review_status, 'pending') = ? ";
            $params[] = $status_filter;
        }
    }

    $sql .= " ORDER BY child.id DESC LIMIT {$limit} OFFSET {$offset}";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function adminRewardGetReferralRowsCount(PDO $db, $search = '', $status_filter = '') {
    $sql = "
        SELECT COUNT(*)
        FROM users child
        INNER JOIN users parent ON parent.id = child.referred_by
        WHERE 1=1
    ";
    $params = [];

    if ($search !== '') {
        $needle = '%' . $search . '%';
        $sql .= " AND (child.full_name LIKE ? OR child.username LIKE ? OR child.email LIKE ? OR parent.username LIKE ?) ";
        $params = array_merge($params, [$needle, $needle, $needle, $needle]);
    }

    if ($status_filter !== '') {
        if ($status_filter === 'qualified') {
            $sql .= " AND child.referral_review_status = 'qualified' ";
        } elseif (in_array($status_filter, ['pending', 'flagged_manual_review', 'invalid'], true)) {
            $sql .= " AND COALESCE(child.referral_review_status, 'pending') = ? ";
            $params[] = $status_filter;
        }
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) ($stmt->fetchColumn() ?: 0);
}

function adminRewardGetReferralMetrics(PDO $db) {
    $total = (int) ($db->query("
        SELECT COUNT(*) FROM users
        WHERE referred_by IS NOT NULL
    ")->fetchColumn() ?: 0);
    $valid = (int) ($db->query("
        SELECT COUNT(*) FROM users
        WHERE referred_by IS NOT NULL
        AND referral_review_status = 'qualified'
    ")->fetchColumn() ?: 0);
    $pending = (int) ($db->query("
        SELECT COUNT(*) FROM users
        WHERE referred_by IS NOT NULL
        AND COALESCE(referral_review_status, 'pending') = 'pending'
    ")->fetchColumn() ?: 0);
    $flagged = (int) ($db->query("
        SELECT COUNT(*) FROM users
        WHERE referred_by IS NOT NULL
        AND referral_review_status = 'flagged_manual_review'
    ")->fetchColumn() ?: 0);
    $invalid = (int) ($db->query("
        SELECT COUNT(*) FROM users
        WHERE referred_by IS NOT NULL
        AND referral_review_status = 'invalid'
    ")->fetchColumn() ?: 0);

    return [
        'total' => $total,
        'valid' => $valid,
        'pending' => $pending,
        'flagged' => $flagged,
        'invalid' => $invalid,
    ];
}
