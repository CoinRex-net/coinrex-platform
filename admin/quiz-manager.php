<?php
$page_title = 'Quiz Manager';
$activePage = 'quiz-manager';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();
$message = '';
$message_type = 'success';

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'save_question') {
            $qid = (int) ($_POST['qid'] ?? 0);
            $task_key = trim((string) ($_POST['task_key'] ?? ''));
            $question = trim((string) ($_POST['question'] ?? ''));
            $choice_raw = (string) ($_POST['choices'] ?? '');
            $answer = max(0, (int) ($_POST['answer'] ?? 0));
            $sort_order = max(0, (int) ($_POST['sort_order'] ?? 0));

            if ($task_key === '' || $question === '' || $choice_raw === '') {
                throw new RuntimeException('Task key, question, and choices are required.');
            }

            $choices = explode("\n", str_replace("\r\n", "\n", $choice_raw));
            $choices = array_map('trim', $choices);
            $choices = array_values(array_filter($choices, static fn($v) => $v !== ''));
            if (count($choices) < 2) {
                throw new RuntimeException('At least 2 choices are required.');
            }
            if ($answer >= count($choices)) {
                $answer = 0;
            }

            $choices_json = json_encode($choices, JSON_UNESCAPED_UNICODE);
            if ($qid > 0) {
                $stmt = $db->prepare("
                    UPDATE taskhub_quiz_questions
                    SET question = ?, choices = ?, answer = ?, sort_order = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$question, $choices_json, $answer, $sort_order, $qid]);
                logAdminActivity((int) $current_admin['id'], 'quiz_question_update', 'taskhub_quiz_questions', (string) $qid, json_encode(['task_key' => $task_key], JSON_UNESCAPED_UNICODE));
                $message = 'Question updated.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO taskhub_quiz_questions (task_key, question, choices, answer, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$task_key, $question, $choices_json, $answer, $sort_order]);
                $new_id = (int) $db->lastInsertId();
                logAdminActivity((int) $current_admin['id'], 'quiz_question_create', 'taskhub_quiz_questions', (string) $new_id, json_encode(['task_key' => $task_key], JSON_UNESCAPED_UNICODE));
                $message = 'Question created.';
            }
        } elseif ($action === 'delete_question') {
            $qid = (int) ($_POST['qid'] ?? 0);
            if ($qid <= 0) {
                throw new RuntimeException('Invalid question ID.');
            }
            $stmt = $db->prepare("DELETE FROM taskhub_quiz_questions WHERE id = ?");
            $stmt->execute([$qid]);
            logAdminActivity((int) $current_admin['id'], 'quiz_question_delete', 'taskhub_quiz_questions', (string) $qid, null);
            $message = 'Question deleted.';
        } elseif ($action === 'toggle_question') {
            $qid = (int) ($_POST['qid'] ?? 0);
            if ($qid <= 0) {
                throw new RuntimeException('Invalid question ID.');
            }
            $stmt = $db->prepare("UPDATE taskhub_quiz_questions SET is_active = 1 - is_active, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$qid]);
            $message = 'Question toggled.';
        } else {
            throw new RuntimeException('Unknown action.');
        }
    } catch (Throwable $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

// --- Get all mission task keys that have or need quizzes ---
$task_keys = $db->query("
    SELECT DISTINCT mt.task_key, mt.title, mt.mission_day, mt.mission_step
    FROM mini_tasks mt
    WHERE mt.task_group = 'mission'
      AND mt.is_active = 1
      AND mt.verification_mode = 'quiz'
    ORDER BY mt.mission_day ASC, mt.mission_step ASC
")->fetchAll();

// --- Current selection ---
$selected_task_key = trim((string) ($_GET['task_key'] ?? ''));
if ($selected_task_key === '' && !empty($task_keys)) {
    $selected_task_key = (string) ($task_keys[0]['task_key'] ?? '');
}

// --- Load questions for selected task_key ---
$questions = [];
if ($selected_task_key !== '') {
    $stmt = $db->prepare("
        SELECT id, task_key, question, choices, answer, sort_order, is_active, created_at, updated_at
        FROM taskhub_quiz_questions
        WHERE task_key = ?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$selected_task_key]);
    $questions = $stmt->fetchAll();
}
?>
<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Quiz Manager</span>
            <h2>TaskHub Quiz Questions</h2>
            <p class="muted">Manage quiz questions for each mission day task. Hardcoded quizzes are used as fallback when no DB questions exist.</p>
        </div>
    </div>

    <!-- Task Key Selector -->
    <form method="GET" class="admin-task-builder">
        <div class="admin-task-builder-grid">
            <div class="admin-form-field">
                <label>Select Task</label>
                <select name="task_key" onchange="this.form.submit()">
                    <?php foreach ($task_keys as $tk): ?>
                        <option value="<?php echo htmlspecialchars((string) $tk['task_key'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected_task_key === (string) $tk['task_key'] ? 'selected' : ''; ?>>
                            Day <?php echo (int) ($tk['mission_day'] ?? 0); ?> - <?php echo htmlspecialchars((string) $tk['title'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars((string) $tk['task_key'], ENT_QUOTES, 'UTF-8'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="admin-task-builder-actions">
            <span class="muted"><?php echo count($questions); ?> question(s) in DB for this task. If empty, the hardcoded quiz is used.</span>
        </div>
    </form>

    <!-- Add New Question Form -->
    <form method="POST" action="" class="admin-task-builder" style="margin-top:20px; border-top:1px solid rgba(148,163,184,0.15); padding-top:20px;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="save_question">
        <input type="hidden" name="task_key" value="<?php echo htmlspecialchars($selected_task_key, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="qid" value="0">
        <div class="admin-section-head">
            <div>
                <h3>Add New Question</h3>
            </div>
        </div>
        <div class="admin-task-builder-grid">
            <div class="admin-form-field admin-form-field-span-2">
                <label>Question Text</label>
                <input type="text" name="question" placeholder="What is the main purpose of this page?" required>
            </div>
            <div class="admin-form-field admin-form-field-span-2">
                <label>Choices (one per line)</label>
                <textarea name="choices" rows="4" placeholder="First choice&#10;Second choice&#10;Third choice" required></textarea>
            </div>
            <div class="admin-form-field">
                <label>Correct Answer (index, 0-based)</label>
                <input type="text" name="answer" value="0" placeholder="0">
            </div>
            <div class="admin-form-field">
                <label>Sort Order</label>
                <input type="text" name="sort_order" value="0" placeholder="0">
            </div>
        </div>
        <div class="admin-task-builder-actions">
            <button type="submit" class="btn btn-primary">Add Question</button>
        </div>
    </form>

    <!-- Existing Questions List -->
    <div class="admin-task-card-list" style="margin-top:20px;">
        <?php if (empty($questions)): ?>
            <div class="admin-task-card">
                <div class="admin-task-card-head">
                    <h3>No questions in DB for this task</h3>
                </div>
                <p class="muted">The system will use the hardcoded quiz. Add questions above to override it.</p>
            </div>
        <?php else: ?>
            <div class="admin-section-head" style="margin-top:16px;">
                <div>
                    <h3>Existing Questions (<?php echo count($questions); ?>)</h3>
                </div>
            </div>
            <?php foreach ($questions as $q): 
                $choices_list = json_decode((string) ($q['choices'] ?? '[]'), true) ?: [];
            ?>
                <form method="POST" action="" class="admin-task-card">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="task_key" value="<?php echo htmlspecialchars($selected_task_key, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="qid" value="<?php echo (int) $q['id']; ?>">
                    <div class="admin-task-card-head">
                        <div>
                            <span class="status-pill <?php echo !empty($q['is_active']) ? 'status-pending' : 'status-rejected'; ?>">
                                <?php echo !empty($q['is_active']) ? 'Active' : 'Inactive'; ?>
                            </span>
                            <span class="muted">Sort: <?php echo (int) ($q['sort_order'] ?? 0); ?></span>
                            <h3 style="margin-top:4px;"><?php echo htmlspecialchars((string) ($q['question'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                        </div>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <button type="submit" name="action" value="toggle_question" class="btn btn-secondary btn-sm">Toggle Active</button>
                            <button type="submit" name="action" value="delete_question" class="btn btn-danger btn-sm" onclick="return confirm('Delete this question?')">Delete</button>
                        </div>
                    </div>
                    <div class="admin-task-builder-grid">
                        <div class="admin-form-field admin-form-field-span-2">
                            <label>Question</label>
                            <input type="text" name="question" value="<?php echo htmlspecialchars((string) ($q['question'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="admin-form-field admin-form-field-span-2">
                            <label>Choices (one per line)</label>
                            <textarea name="choices" rows="4" required><?php 
                                echo htmlspecialchars(implode("\n", $choices_list), ENT_QUOTES, 'UTF-8'); 
                            ?></textarea>
                        </div>
                        <div class="admin-form-field">
                            <label>Correct Answer (index, 0-based)</label>
                            <input type="text" name="answer" value="<?php echo (int) ($q['answer'] ?? 0); ?>">
                        </div>
                        <div class="admin-form-field">
                            <label>Sort Order</label>
                            <input type="text" name="sort_order" value="<?php echo (int) ($q['sort_order'] ?? 0); ?>">
                        </div>
                    </div>
                    <div class="admin-task-card-foot">
                        <div class="admin-task-stat-line">
                            <span>Created: <?php echo htmlspecialchars((string) ($q['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span>Updated: <?php echo htmlspecialchars((string) ($q['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <button type="submit" name="action" value="save_question" class="btn btn-secondary">Save Changes</button>
                    </div>
                </form>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
