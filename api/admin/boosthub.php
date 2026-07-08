<?php
/**
 * BoostHub Admin API — AJAX CRUD
 * Handles: list tasks, create/update task, delete task, toggle active, review submissions
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once dirname(__DIR__, 2) . '/admin/includes/config.php';
require_once dirname(__DIR__, 2) . '/admin/includes/reward_admin.php';

// Return JSON instead of redirect for API requests
if (!adminGuardIsLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}
$current_admin = getCurrentAdmin();
if (!$current_admin) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json; charset=utf-8');

try {

    // ─── GET: List tasks or reviews ───────────────────────────────
    if ($method === 'GET') {
        $action = trim((string) ($_GET['action'] ?? ''));

        if ($action === 'reviews') {
            // Return pending evidence submissions
            $rows = adminRewardGetBoosthubReviewRows($db);
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        }

        if ($action === 'all_evidence') {
            // Return all evidence with pagination and filtering
            $task_category = trim((string) ($_GET['task_category'] ?? 'all'));
            $status_filter = trim((string) ($_GET['status'] ?? 'all'));
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));

            $result = adminRewardGetBoosthubAllEvidence($db, $task_category, $status_filter, $page, $perPage);
            echo json_encode([
                'success' => true,
                'data' => $result['rows'],
                'total' => $result['total'],
                'pages' => $result['pages'],
                'page' => $page,
            ]);
            exit;
        }

        // Default: list tasks
        $selected_category = trim((string) ($_GET['task_category'] ?? 'all'));
        $task_categories = adminRewardTaskCategories();
        if ($selected_category !== 'all' && !array_key_exists($selected_category, $task_categories)) {
            $selected_category = 'all';
        }

        $task_rows = adminRewardGetTasks($db, 'boosthub');
        if ($selected_category !== 'all') {
            $task_rows = array_values(array_filter($task_rows, static function (array $task) use ($selected_category): bool {
                return (string) ($task['task_category'] ?? 'custom') === $selected_category;
            }));
        }

        echo json_encode(['success' => true, 'data' => $task_rows]);
        exit;
    }

    // ─── POST: Create/Update task, toggle, review, delete ─────────
    if ($method === 'POST') {
        $action = trim((string) ($_POST['action_type'] ?? ''));

        // ── Save (create or update) ──
        if ($action === 'save_task') {
            $task_id = (int) ($_POST['task_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $reward = max(0, (float) ($_POST['reward'] ?? 0));
            $daily_limit = max(1, (int) ($_POST['daily_limit'] ?? 1));
            $cooldown_seconds = max(0, (int) ($_POST['cooldown_seconds'] ?? 0));
            $is_active = !empty($_POST['is_active']) ? 1 : 0;
            $task_category = trim((string) ($_POST['task_category'] ?? 'custom'));
            $task_link = trim((string) ($_POST['task_link'] ?? ''));
            $completion_steps = trim((string) ($_POST['completion_steps'] ?? ''));
            $proof_notes = trim((string) ($_POST['proof_notes'] ?? ''));
            $cta_label = trim((string) ($_POST['cta_label'] ?? ''));

            if ($title === '' || $description === '' || $reward <= 0) {
                throw new RuntimeException('Task title, short description, and reward are required.');
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

            if ($task_id > 0) {
                // Update
                $stmt = $db->prepare("
                    UPDATE mini_tasks
                    SET title = ?, description = ?, reward = ?, daily_limit = ?, cooldown_seconds = ?, is_active = ?,
                        task_category = ?, task_link = ?, completion_steps = ?, proof_notes = ?, cta_label = ?
                    WHERE id = ? AND task_group = 'boosthub'
                ");
                $stmt->execute([
                    $title, $description, $reward, $daily_limit, $cooldown_seconds, $is_active,
                    $task_category, $task_link, $completion_steps, $proof_notes, $cta_label, $task_id
                ]);
                logAdminActivity((int) $current_admin['id'], 'mini_task_update', 'mini_task', (string) $task_id, json_encode(['title' => $title], JSON_UNESCAPED_UNICODE));
                echo json_encode(['success' => true, 'message' => 'Task updated.']);
            } else {
                // Create
                $stmt = $db->prepare("
                    INSERT INTO mini_tasks (
                        title, description, reward, daily_limit, cooldown_seconds, is_active, task_group,
                        task_category, task_link, completion_steps, proof_notes, cta_label
                    ) VALUES (?, ?, ?, ?, ?, ?, 'boosthub', ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $description, $reward, $daily_limit, $cooldown_seconds, $is_active,
                    $task_category, $task_link, $completion_steps, $proof_notes, $cta_label
                ]);
                $new_id = (int) $db->lastInsertId();
                logAdminActivity((int) $current_admin['id'], 'mini_task_create', 'mini_task', (string) $new_id, json_encode(['title' => $title], JSON_UNESCAPED_UNICODE));
                echo json_encode(['success' => true, 'message' => 'Task created.', 'id' => $new_id]);
            }
            exit;
        }

        // ── Toggle active ──
        if ($action === 'toggle') {
            $task_id = (int) ($_POST['task_id'] ?? 0);
            if ($task_id <= 0) throw new RuntimeException('Invalid task ID.');

            $stmt = $db->prepare("SELECT is_active FROM mini_tasks WHERE id = ? AND task_group = 'boosthub'");
            $stmt->execute([$task_id]);
            $task = $stmt->fetch();
            if (!$task) throw new RuntimeException('Task not found.');

            $new_active = empty($task['is_active']) ? 1 : 0;
            $stmt = $db->prepare("UPDATE mini_tasks SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_active, $task_id]);
            logAdminActivity((int) $current_admin['id'], 'mini_task_toggle', 'mini_task', (string) $task_id, json_encode(['is_active' => $new_active], JSON_UNESCAPED_UNICODE));

            echo json_encode(['success' => true, 'message' => $new_active ? 'Task activated.' : 'Task deactivated.', 'is_active' => $new_active]);
            exit;
        }

        // ── Delete ──
        if ($action === 'delete') {
            $task_id = (int) ($_POST['task_id'] ?? 0);
            if ($task_id <= 0) throw new RuntimeException('Invalid task ID.');

            $stmt = $db->prepare("DELETE FROM mini_tasks WHERE id = ? AND task_group = 'boosthub'");
            $stmt->execute([$task_id]);
            logAdminActivity((int) $current_admin['id'], 'mini_task_delete', 'mini_task', (string) $task_id, '');
            echo json_encode(['success' => true, 'message' => 'Task deleted.']);
            exit;
        }

        // ── Review submission ──
        if ($action === 'review') {
            $log_id = (int) ($_POST['log_id'] ?? 0);
            $decision = (string) ($_POST['decision'] ?? '');
            $review_note = trim((string) ($_POST['review_note'] ?? ''));
            if ($log_id <= 0 || !in_array($decision, ['approve', 'reject', 'return'], true)) {
                throw new RuntimeException('Invalid review action.');
            }

            $result = reviewTaskHubSubmission($log_id, $decision === 'approve', $db, [
                'return_for_correction' => $decision === 'return',
                'review_note' => $review_note,
            ]);
            logAdminActivity((int) $current_admin['id'], 'taskhub_submission_review', 'user_task_log', (string) $log_id, json_encode(['decision' => $decision, 'review_note' => $review_note], JSON_UNESCAPED_UNICODE));
            $label = 'BoostHub';
            if (!empty($result['returned'])) {
                $message = $label . ' submission returned for correction.';
            } else {
                $message = !empty($result['approved']) ? ($label . ' submission approved.') : ($label . ' submission rejected.');
            }

            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        }

        throw new RuntimeException('Unknown action.');
    }

    throw new RuntimeException('Method not allowed.');

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
