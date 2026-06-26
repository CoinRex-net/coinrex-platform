 <?php
$page_title = 'Quiz Manager';
$activePage = 'quiz-manager';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();

// --- Get all mission task keys that have or need quizzes ---
$task_keys = $db->query("
    SELECT DISTINCT mt.task_key, mt.title, mt.mission_day, mt.mission_step
    FROM mini_tasks mt
    WHERE mt.task_group = 'mission'
      AND mt.is_active = 1
      AND mt.verification_mode = 'quiz'
    ORDER BY mt.mission_day ASC, mt.mission_step ASC
")->fetchAll();

// --- Current selection (for initial page load only, JS handles the rest) ---
$selected_task_key = trim((string) ($_GET['task_key'] ?? ''));
if ($selected_task_key === '' && !empty($task_keys)) {
    $selected_task_key = (string) ($task_keys[0]['task_key'] ?? '');
}
?>
<div class="dashboard-header">
    <div class="dashboard-header-left">
        <div class="dashboard-header-icon"><i class="fas fa-circle-question"></i></div>
        <div class="dashboard-header-text">
            <h1>Quiz Manager</h1>
            <p>Customize quiz questions for each LearnHub mission task (optional overrides).</p>
        </div>
    </div>
    <span class="dashboard-header-badge"><i class="fas fa-circle"></i> LearnHub</span>
</div>

<div class="dashboard-panel">
    <div class="dashboard-panel-header">
        <h3><i class="fas fa-filter"></i> Select a quiz task</h3>
        <span class="panel-badge" id="quizQuestionsCount">0</span>
    </div>

    <?php if (empty($task_keys)): ?>
        <div class="dashboard-empty">
            <i class="fas fa-triangle-exclamation"></i>
            <p>No active quiz-mode mission tasks found. Set a mission task to <strong>verification_mode = quiz</strong> in Task Management.</p>
        </div>
    <?php else: ?>
        <div class="admin-task-builder-grid">
            <div class="admin-form-field admin-form-field-span-2">
                <label>Select Task</label>
                <select id="quizTaskSelect" name="task_key">
                    <option value="">— Select a task —</option>
                    <?php foreach ($task_keys as $tk): ?>
                        <option value="<?php echo htmlspecialchars((string) $tk['task_key'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected_task_key === (string) $tk['task_key'] ? 'selected' : ''; ?>>
                            Day <?php echo (int) ($tk['mission_day'] ?? 0); ?> — <?php echo htmlspecialchars((string) $tk['title'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars((string) $tk['task_key'], ENT_QUOTES, 'UTF-8'); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="quiz-toolbar">
            <div class="quiz-toolbar-left">
                <button type="button" class="btn btn-primary" id="quizAddBtn">
                    <i class="fas fa-plus"></i> Add Question
                </button>
            </div>
        </div>

        <!-- Questions Container (rendered by JS) -->
        <div id="quizQuestionsContainer">
            <div class="dashboard-empty">
                <i class="fas fa-triangle-exclamation"></i>
                <p>Select a task to view its questions.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- Add / Edit Modal -->
<!-- ============================================================ -->
<div class="quiz-modal-overlay" id="quizModalOverlay"></div>
<div class="quiz-modal" id="quizModal">
    <div class="quiz-modal-header">
        <h2 id="quizModalTitle">Add New Question</h2>
        <button type="button" class="quiz-modal-close" id="modalCancelBtn">&times;</button>
    </div>
    <form id="quizModalForm" novalidate>
        <div class="quiz-modal-body">
            <!-- Hidden fields -->
            <input type="hidden" id="modalQuestionId" value="0">
            <input type="hidden" id="modalTaskKey" value="">

            <!-- Task selector (shown only in add mode) -->
            <div class="quiz-modal-field" id="modalTaskSelectWrap">
                <label for="modalTaskSelect">Select Task</label>
                <select id="modalTaskSelect">
                    <option value="">— Select a task —</option>
                </select>
            </div>

            <!-- Question text -->
            <div class="quiz-modal-field">
                <label for="modalQuestion">Question Text</label>
                <textarea id="modalQuestion" rows="3" placeholder="What is the main purpose of this feature?" required></textarea>
            </div>

            <!-- Choices -->
            <div class="quiz-modal-field">
                <label>Choices <span style="font-weight:400;text-transform:none;color:#64748b;">(check the box next to correct answer(s))</span></label>
                <div id="modalChoicesContainer">
                    <!-- Dynamically added by JS -->
                </div>
                <button type="button" class="quiz-add-choice-btn" id="addChoiceBtn">
                    <i class="fas fa-plus"></i> Add Choice
                </button>
            </div>

            <!-- Sort order -->
            <div class="quiz-modal-field">
                <label for="modalSortOrder">Sort Order</label>
                <input type="number" id="modalSortOrder" value="0" min="0" placeholder="0">
                <p style="margin:4px 0 0; font-size:11px; color:#64748b;">Leave as 0 to use default order. Higher numbers appear later.</p>
            </div>
        </div>
        <div class="quiz-modal-footer">
            <button type="button" class="btn btn-secondary" id="modalCancelBtn2">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Question</button>
        </div>
    </form>
</div>

<!-- ====== DELETE CONFIRMATION MODAL ====== -->
<div class="dashboard-modal" id="deleteModal">
    <div class="dashboard-modal-card" style="max-width:460px;">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-trash"></i> Delete Question</span>
                <h3>Delete this question?</h3>
            </div>
            <button type="button" class="dashboard-modal-close" id="cancelDeleteBtn">&times;</button>
        </div>
        <div class="dashboard-modal-body">
            <p style="color:#cbd5e1;margin:0 0 16px;">This action is permanent and cannot be undone.</p>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" id="cancelDeleteBtn2">Cancel</button>
                <button type="button" class="btn" id="confirmDeleteBtn" style="background:#991b1b;color:#fee2e2;border:1px solid #ef4444;">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo ADMIN_BASE_URL; ?>/assets/js/quiz-manager.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
