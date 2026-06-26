<?php
/**
 * Quiz Manager API
 * AJAX-driven CRUD for taskhub_quiz_questions
 * 
 * Endpoints:
 *   GET    ?task_key=xxx          — List questions for a task
 *   POST   (JSON body)            — Create a new question
 *   PUT    (JSON body)            — Update an existing question
 *   DELETE ?id=xxx                — Delete a question
 *   POST   ?action=toggle&id=xxx  — Toggle active/inactive
 *   POST   ?action=reorder        — Reorder questions (send sorted IDs)
 *   POST   ?action=seed           — Seed from hardcoded defaults
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once dirname(__DIR__, 2) . '/admin/includes/config.php';

adminGuardRequireAuth();
$current_admin = getCurrentAdmin();
if (!$current_admin) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = trim((string) ($_GET['action'] ?? ''));
$json_body = null;

// Parse JSON body for POST/PUT
if (in_array($method, ['POST', 'PUT'], true)) {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $json_body = json_decode($raw, true);
    }
}

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($method) {
        // ============================================================
        // GET — List questions for a task_key
        // ============================================================
        case 'GET':
            $task_key = trim((string) ($_GET['task_key'] ?? ''));
            if ($task_key === '') {
                throw new RuntimeException('task_key is required.');
            }
            $stmt = $db->prepare("
                SELECT id, task_key, question, choices, answer, sort_order, is_active, created_at, updated_at
                FROM taskhub_quiz_questions
                WHERE task_key = ?
                ORDER BY sort_order ASC, id ASC
            ");
            $stmt->execute([$task_key]);
            $rows = $stmt->fetchAll();

            // Decode choices JSON and answer JSON for each row
            foreach ($rows as &$row) {
                $row['choices'] = json_decode((string) ($row['choices'] ?? '[]'), true) ?: [];
                $raw_answer = (string) ($row['answer'] ?? '[0]');
                // Handle both old integer format and new JSON array format
                if (strpos($raw_answer, '[') === 0) {
                    $row['answer'] = json_decode($raw_answer, true) ?: [0];
                } else {
                    $row['answer'] = [(int) $raw_answer];
                }
            }
            unset($row);

            echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // POST — Create / Toggle / Reorder / Seed
        // ============================================================
        case 'POST':
            if ($action === 'toggle') {
                $qid = (int) ($_GET['id'] ?? 0);
                if ($qid <= 0) {
                    throw new RuntimeException('Invalid question ID.');
                }
                $stmt = $db->prepare("UPDATE taskhub_quiz_questions SET is_active = 1 - is_active, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$qid]);
                logAdminActivity((int) $current_admin['id'], 'quiz_question_toggle', 'taskhub_quiz_questions', (string) $qid, null);
                echo json_encode(['success' => true, 'message' => 'Question toggled.']);
                break;
            }

            if ($action === 'reorder') {
                $ids = (array) ($json_body['ids'] ?? []);
                if (empty($ids)) {
                    throw new RuntimeException('No IDs provided for reorder.');
                }
                $stmt = $db->prepare("UPDATE taskhub_quiz_questions SET sort_order = ?, updated_at = NOW() WHERE id = ?");
                foreach ($ids as $index => $id) {
                    $stmt->execute([(int) $index, (int) $id]);
                }
                echo json_encode(['success' => true, 'message' => 'Reordered.']);
                break;
            }

            if ($action === 'seed') {
                $task_key = trim((string) ($json_body['task_key'] ?? ''));
                if ($task_key === '') {
                    throw new RuntimeException('Task key is required.');
                }

                // Check if questions already exist
                $check_stmt = $db->prepare("SELECT COUNT(*) FROM taskhub_quiz_questions WHERE task_key = ?");
                $check_stmt->execute([$task_key]);
                $existing_count = (int) ($check_stmt->fetchColumn() ?: 0);
                if ($existing_count > 0) {
                    throw new RuntimeException('This task already has ' . $existing_count . ' question(s). Delete them first to re-seed.');
                }

                // Load hardcoded definition from legacy file
                require_once dirname(__DIR__, 2) . '/includes/functions_legacy_backup.php';
                $definition = getTaskHubMissionTaskDefinitionByKey($task_key);
                $hardcoded_quiz = $definition['quiz'] ?? [];
                // Fallback: try the original file
                if (empty($hardcoded_quiz)) {
                    require_once dirname(__DIR__, 2) . '/includes/functions/taskhub_original.php';
                    $definition = getTaskHubMissionTaskDefinitionByKey($task_key);
                    $hardcoded_quiz = $definition['quiz'] ?? [];
                }
                if (empty($hardcoded_quiz)) {
                    throw new RuntimeException('No hardcoded quiz found for this task key.');
                }

                $insert_stmt = $db->prepare("
                    INSERT INTO taskhub_quiz_questions (task_key, question, choices, answer, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $seeded = 0;
                foreach ($hardcoded_quiz as $q_idx => $q_data) {
                    $question_text = trim((string) ($q_data['question'] ?? ''));
                    $choices = $q_data['choices'] ?? [];
                    $raw_answer = $q_data['answer'] ?? 0;
                    if ($question_text === '' || empty($choices)) {
                        continue;
                    }
                    // Convert answer to JSON array format
                    if (is_array($raw_answer)) {
                        $answer_json = json_encode(array_map('intval', $raw_answer), JSON_UNESCAPED_UNICODE);
                    } else {
                        $answer_json = '[' . (int) $raw_answer . ']';
                    }
                    $choices_json = json_encode(array_values($choices), JSON_UNESCAPED_UNICODE);
                    $insert_stmt->execute([$task_key, $question_text, $choices_json, $answer_json, $q_idx]);
                    $seeded++;
                }

                logAdminActivity((int) $current_admin['id'], 'quiz_seed', 'taskhub_quiz_questions', 0, json_encode(['task_key' => $task_key, 'seeded' => $seeded], JSON_UNESCAPED_UNICODE));
                echo json_encode(['success' => true, 'message' => 'Seeded ' . $seeded . ' question(s).', 'seeded' => $seeded]);
                break;
            }

            // Default POST = Create new question
            if ($json_body === null) {
                throw new RuntimeException('Invalid JSON body.');
            }

            $task_key = trim((string) ($json_body['task_key'] ?? ''));
            $question = trim((string) ($json_body['question'] ?? ''));
            $choices = (array) ($json_body['choices'] ?? []);
            $raw_answer = $json_body['answer'] ?? [0];
            $sort_order = max(0, (int) ($json_body['sort_order'] ?? 0));

            if ($task_key === '' || $question === '' || empty($choices)) {
                throw new RuntimeException('Task key, question, and choices are required.');
            }
            if (count($choices) < 2) {
                throw new RuntimeException('At least 2 choices are required.');
            }

            // Normalize answer to array
            if (!is_array($raw_answer)) {
                $raw_answer = [(int) $raw_answer];
            }
            $answer_json = json_encode(array_map('intval', $raw_answer), JSON_UNESCAPED_UNICODE);
            $choices_json = json_encode(array_values($choices), JSON_UNESCAPED_UNICODE);

            $stmt = $db->prepare("
                INSERT INTO taskhub_quiz_questions (task_key, question, choices, answer, sort_order, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$task_key, $question, $choices_json, $answer_json, $sort_order]);
            $new_id = (int) $db->lastInsertId();

            logAdminActivity((int) $current_admin['id'], 'quiz_question_create', 'taskhub_quiz_questions', (string) $new_id, json_encode(['task_key' => $task_key], JSON_UNESCAPED_UNICODE));

            echo json_encode([
                'success' => true,
                'message' => 'Question created.',
                'data' => [
                    'id' => $new_id,
                    'task_key' => $task_key,
                    'question' => $question,
                    'choices' => array_values($choices),
                    'answer' => array_map('intval', $raw_answer),
                    'sort_order' => $sort_order,
                    'is_active' => 1,
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // PUT — Update existing question
        // ============================================================
        case 'PUT':
            if ($json_body === null) {
                throw new RuntimeException('Invalid JSON body.');
            }

            $qid = (int) ($json_body['id'] ?? 0);
            $question = trim((string) ($json_body['question'] ?? ''));
            $choices = (array) ($json_body['choices'] ?? []);
            $raw_answer = $json_body['answer'] ?? [0];
            $sort_order = max(0, (int) ($json_body['sort_order'] ?? 0));

            if ($qid <= 0) {
                throw new RuntimeException('Invalid question ID.');
            }
            if ($question === '' || empty($choices)) {
                throw new RuntimeException('Question and choices are required.');
            }
            if (count($choices) < 2) {
                throw new RuntimeException('At least 2 choices are required.');
            }

            // Normalize answer to array
            if (!is_array($raw_answer)) {
                $raw_answer = [(int) $raw_answer];
            }
            $answer_json = json_encode(array_map('intval', $raw_answer), JSON_UNESCAPED_UNICODE);
            $choices_json = json_encode(array_values($choices), JSON_UNESCAPED_UNICODE);

            $stmt = $db->prepare("
                UPDATE taskhub_quiz_questions
                SET question = ?, choices = ?, answer = ?, sort_order = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$question, $choices_json, $answer_json, $sort_order, $qid]);

            logAdminActivity((int) $current_admin['id'], 'quiz_question_update', 'taskhub_quiz_questions', (string) $qid, json_encode(['task_key' => $json_body['task_key'] ?? ''], JSON_UNESCAPED_UNICODE));

            echo json_encode(['success' => true, 'message' => 'Question updated.']);
            break;

        // ============================================================
        // DELETE — Delete a question
        // ============================================================
        case 'DELETE':
            $qid = (int) ($_GET['id'] ?? 0);
            if ($qid <= 0) {
                throw new RuntimeException('Invalid question ID.');
            }
            $stmt = $db->prepare("DELETE FROM taskhub_quiz_questions WHERE id = ?");
            $stmt->execute([$qid]);
            logAdminActivity((int) $current_admin['id'], 'quiz_question_delete', 'taskhub_quiz_questions', (string) $qid, null);
            echo json_encode(['success' => true, 'message' => 'Question deleted.']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed.']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
