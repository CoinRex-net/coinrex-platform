<?php
$page_title = 'Task Management';
$activePage = 'task-management';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);
$current_admin = getCurrentAdmin();
[$message, $message_type] = adminRewardProcessAction($db, $current_admin);
$task_groups = adminRewardTaskGroups();
$selected_group = trim((string) ($_GET['group'] ?? 'mission'));
if (!array_key_exists($selected_group, $task_groups)) {
    $selected_group = 'mission';
}
$selected_day = max(0, (int) ($_GET['day'] ?? 0));
if ($selected_group !== 'mission') {
    $selected_day = 0;
}
$task_rows = adminRewardGetTasks($db, $selected_group, $selected_day);
$mission_task_counts = [];
$day_titles = [];
if ($selected_group === 'mission') {
    $all_mission_rows = adminRewardGetTasks($db, 'mission', 0);
    foreach ($all_mission_rows as $mission_row) {
        $day_key = (int) ($mission_row['mission_day'] ?? 0);
        if ($day_key <= 0) {
            continue;
        }
        if (!isset($mission_task_counts[$day_key])) {
            $mission_task_counts[$day_key] = 0;
        }
        $mission_task_counts[$day_key]++;
        $dt = trim((string) ($mission_row['day_title'] ?? ''));
        if ($dt !== '' && !isset($day_titles[$day_key])) {
            $day_titles[$day_key] = $dt;
        }
    }
}
$task_categories = adminRewardTaskCategories();
$verification_modes = ['instant', 'manual', 'quiz', 'mystery'];
?>
<style>
/* ── Task Management Specific Styles ── */
.task-create-btn-wrap {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 16px;
}
.task-card-compact {
    background: linear-gradient(145deg, rgba(15,23,42,0.8), rgba(30,41,59,0.6));
    border: 1px solid rgba(148,163,184,0.08);
    border-radius: 14px;
    padding: 16px 18px;
    transition: border-color 0.2s, box-shadow 0.2s;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}
.task-card-compact:hover {
    border-color: rgba(212,175,55,0.2);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}
.task-card-compact + .task-card-compact {
    margin-top: 10px;
}
.task-card-left {
    display: flex;
    align-items: center;
    gap: 14px;
    flex: 1;
    min-width: 200px;
}
.task-card-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
    background: rgba(212,175,55,0.12);
    color: #f5d76e;
}
.task-card-icon.is-quiz { background: rgba(59,130,246,0.15); color: #60a5fa; }
.task-card-icon.is-manual { background: rgba(239,68,68,0.15); color: #f87171; }
.task-card-icon.is-instant { background: rgba(34,197,94,0.15); color: #4ade80; }
.task-card-icon.is-profile { background: rgba(168,85,247,0.15); color: #c084fc; }
.task-card-icon.is-wallet { background: rgba(6,182,212,0.15); color: #67e8f9; }
.task-card-icon.is-boosthub { background: rgba(249,115,22,0.15); color: #fb923c; }
.task-card-icon.is-mystery { background: rgba(236,72,153,0.15); color: #f472b6; }
.task-card-info h4 {
    margin: 0 0 2px;
    font-size: 15px;
    font-weight: 700;
    color: #f1f5f9;
}
.task-card-info .task-card-meta {
    font-size: 12px;
    color: #94a3b8;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.task-card-info .task-card-meta span {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.task-card-right {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.task-card-stats {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    color: #64748b;
}
.task-card-stats strong {
    color: #94a3b8;
    font-weight: 600;
}
/* ── Day Settings Bar ── */
.day-settings-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 18px;
    background: rgba(15,23,42,0.6);
    border: 1px solid rgba(148,163,184,0.08);
    border-radius: 14px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.day-settings-bar label {
    font-size: 13px;
    font-weight: 600;
    color: #cbd5e1;
    white-space: nowrap;
}
.day-settings-bar input[type="text"] {
    background: rgba(15,23,42,0.8);
    border: 1px solid rgba(148,163,184,0.15);
    border-radius: 8px;
    padding: 8px 12px;
    color: #f1f5f9;
    font-size: 14px;
    width: 200px;
    transition: border-color 0.2s;
}
.day-settings-bar input[type="text"]:focus {
    border-color: #d4af37;
    outline: none;
}
.day-settings-bar .day-title-hint {
    font-size: 12px;
    color: #64748b;
}
/* ── Modal Tabs ── */
.modal-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 18px;
    border-bottom: 1px solid rgba(148,163,184,0.12);
    padding-bottom: 0;
    overflow-x: auto;
}
.modal-tab {
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 600;
    color: #94a3b8;
    background: transparent;
    border: 0;
    border-bottom: 2px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    border-radius: 8px 8px 0 0;
}
.modal-tab:hover {
    color: #e2e8f0;
    background: rgba(148,163,184,0.05);
}
.modal-tab.active {
    color: #f5d76e;
    border-bottom-color: #d4af37;
    background: rgba(212,175,55,0.06);
}
.modal-tab-content {
    display: none;
}
.modal-tab-content.active {
    display: block;
}
/* ── Modal Form Fields ── */
.modal-field {
    margin-bottom: 14px;
}
.modal-field label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #cbd5e1;
    margin-bottom: 4px;
}
.modal-field .field-hint {
    font-size: 11px;
    color: #64748b;
    margin: 2px 0 0;
    line-height: 1.4;
}
.modal-field input[type="text"],
.modal-field input[type="number"],
.modal-field select,
.modal-field textarea {
    width: 100%;
    background: rgba(15,23,42,0.8);
    border: 1px solid rgba(148,163,184,0.15);
    border-radius: 8px;
    padding: 9px 12px;
    color: #f1f5f9;
    font-size: 14px;
    transition: border-color 0.2s;
    box-sizing: border-box;
}
.modal-field input:focus,
.modal-field select:focus,
.modal-field textarea:focus {
    border-color: #d4af37;
    outline: none;
}
.modal-field textarea {
    resize: vertical;
    min-height: 60px;
}
.modal-field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
}
.modal-checkbox-row {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 6px;
}
.modal-checkbox-row label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 500;
    color: #94a3b8;
    cursor: pointer;
}
.modal-checkbox-row input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #d4af37;
}
.modal-footer-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-top: 16px;
    border-top: 1px solid rgba(148,163,184,0.12);
    margin-top: 8px;
}
.modal-footer-actions .left-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}
</style>

<script id="taskhub-data" type="application/json">
<?php
$task_data_json = json_encode($task_rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($task_data_json === false) {
    $task_data_json = '[]';
}
echo $task_data_json;
?>
</script>
<script>
// ── PHP-injected safe values ──
<?php
$csrf_token_json = json_encode(adminCsrfToken(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$group_label_json = json_encode((string) ($task_groups[$selected_group] ?? $selected_group), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$selected_group_json = json_encode($selected_group, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
var TASK_DATA = JSON.parse(document.getElementById('taskhub-data').textContent);
var IS_MISSION_GROUP = <?php echo $selected_group === 'mission' ? 'true' : 'false'; ?>;
var SELECTED_DAY = <?php echo (int) $selected_day; ?>;
var CSRF_TOKEN = <?php echo $csrf_token_json; ?>;
var TASK_GROUP_LABEL = <?php echo $group_label_json; ?>;
var SELECTED_GROUP = <?php echo $selected_group_json; ?>;
var currentEditTaskId = 0;

// ── Modal Functions (defined early so inline onclick handlers work) ──

// ── Close Modal ──

function closeModal() {
    var modal = document.getElementById('taskModal');
    if (modal) modal.classList.remove('show');
    currentEditTaskId = 0;
}

// ── Open Create Modal ──
function openCreateModal() {
    currentEditTaskId = 0;
    document.getElementById('modalTitle').textContent = 'Create Task';
    document.getElementById('modalSubmitBtn').textContent = 'Create Task';
    document.getElementById('modalKicker').textContent = IS_MISSION_GROUP ? 'MicroMission \u2014 Day ' + SELECTED_DAY : TASK_GROUP_LABEL;
    document.getElementById('formTaskId').value = '0';
    document.getElementById('formTaskGroup').value = SELECTED_GROUP;
    document.getElementById('formMissionDay').value = SELECTED_DAY;
    document.getElementById('formMissionStep').value = '0';

    // Reset form
    document.getElementById('f_title').value = '';
    document.getElementById('f_description').value = '';
    document.getElementById('f_reward').value = '';
    document.getElementById('f_daily_limit').value = '1';
    document.getElementById('f_cooldown_seconds').value = '86400';
    document.getElementById('f_task_link').value = '';
    document.getElementById('f_completion_steps').value = '';
    document.getElementById('f_proof_notes').value = '';
    document.getElementById('f_cta_label').value = '';
    document.getElementById('f_verification_mode').value = 'instant';
    document.getElementById('f_requires_quiz').checked = false;
    document.getElementById('f_requires_manual_review').checked = false;
    document.getElementById('f_requires_quiz2').checked = false;
    document.getElementById('f_requires_manual_review2').checked = false;
    document.getElementById('f_learning_title').value = '';
    document.getElementById('f_learning_url').value = '';
    document.getElementById('f_required_reading_seconds').value = '45';
    document.getElementById('f_min_quiz_score').value = '0';
    document.getElementById('f_is_active').checked = true;
    document.getElementById('quizCheckboxRow').style.display = 'none';
    document.getElementById('manageQuizLink').style.display = 'none';

    // Pre-fill day title from the day settings bar
    var dayTitleInput = document.getElementById('dayTitleInput');
    if (dayTitleInput) {
        document.getElementById('f_day_title').value = dayTitleInput.value;
    } else {
        document.getElementById('f_day_title').value = '';
    }

    // Show/hide learning tab
    var learningTab = document.getElementById('learningTab');
    if (IS_MISSION_GROUP) {
        learningTab.style.display = 'block';
    } else {
        learningTab.style.display = 'none';
    }

    // Reset to first tab
    var firstTab = document.querySelectorAll('.modal-tab')[0];
    if (firstTab) firstTab.click();

    document.getElementById('taskModal').classList.add('show');
}

// ── Open Edit Modal ──
function openEditModal(taskId) {
    currentEditTaskId = taskId;
    var task = null;
    for (var i = 0; i < TASK_DATA.length; i++) {
        if (parseInt(TASK_DATA[i].id) === taskId) {
            task = TASK_DATA[i];
            break;
        }
    }
    if (!task) return;

    document.getElementById('modalTitle').textContent = 'Edit Task';
    document.getElementById('modalSubmitBtn').textContent = 'Save Changes';
    document.getElementById('modalKicker').textContent = IS_MISSION_GROUP ? 'MicroMission \u2014 Day ' + task.mission_day : TASK_GROUP_LABEL;
    document.getElementById('formTaskId').value = task.id;
    document.getElementById('formTaskGroup').value = task.task_group || SELECTED_GROUP;
    document.getElementById('formMissionDay').value = task.mission_day || 0;
    document.getElementById('formMissionStep').value = task.mission_step || 0;

    // Fill form fields
    document.getElementById('f_title').value = task.title || '';
    document.getElementById('f_description').value = task.description || '';
    document.getElementById('f_reward').value = task.reward || '';
    document.getElementById('f_daily_limit').value = task.daily_limit || '1';
    document.getElementById('f_cooldown_seconds').value = task.cooldown_seconds || '86400';
    document.getElementById('f_task_link').value = task.task_link || '';
    document.getElementById('f_completion_steps').value = task.completion_steps || '';
    document.getElementById('f_proof_notes').value = task.proof_notes || '';
    document.getElementById('f_cta_label').value = task.cta_label || '';
    document.getElementById('f_verification_mode').value = task.verification_mode || 'instant';
    document.getElementById('f_requires_quiz').checked = task.requires_quiz == 1;
    document.getElementById('f_requires_manual_review').checked = task.requires_manual_review == 1;
    document.getElementById('f_requires_quiz2').checked = task.requires_quiz == 1;
    document.getElementById('f_requires_manual_review2').checked = task.requires_manual_review == 1;
    document.getElementById('f_learning_title').value = task.learning_title || '';
    document.getElementById('f_learning_url').value = task.learning_url || '';
    document.getElementById('f_required_reading_seconds').value = task.required_reading_seconds || '45';
    document.getElementById('f_day_title').value = task.day_title || '';
    document.getElementById('f_min_quiz_score').value = task.min_quiz_score || '0';
    document.getElementById('f_is_active').checked = task.is_active == 1;
    document.getElementById('f_task_category').value = task.task_category || 'custom';

    // Show/hide quiz checkbox row based on verification mode
    toggleVerificationMode();

    // Show/hide learning tab
    var learningTab = document.getElementById('learningTab');
    if (IS_MISSION_GROUP) {
        learningTab.style.display = 'block';
    } else {
        learningTab.style.display = 'none';
    }

    // Show manage quiz link if requires_quiz
    var manageQuizLink = document.getElementById('manageQuizLink');
    if (task.requires_quiz == 1 && task.task_key) {
        manageQuizLink.style.display = 'block';
        document.getElementById('manageQuizBtn').href = 'quiz-manager.php?task_key=' + encodeURIComponent(task.task_key);
    } else {
        manageQuizLink.style.display = 'none';
    }

    // Reset to first tab
    var firstTab = document.querySelectorAll('.modal-tab')[0];
    if (firstTab) firstTab.click();

    document.getElementById('taskModal').classList.add('show');
}

// ── Toggle Verification Mode ──
function toggleVerificationMode() {
    var vm = document.getElementById('f_verification_mode').value;
    var quizRow = document.getElementById('quizCheckboxRow');
    var isQuiz = vm === 'quiz';
    quizRow.style.display = isQuiz ? 'flex' : 'none';
    if (!isQuiz) {
        document.getElementById('f_requires_quiz').checked = false;
        document.getElementById('f_requires_manual_review').checked = false;
    }
}

// ── Delete Confirmation ──
function openDeleteModal(taskId, taskTitle) {
    document.getElementById('deleteTaskId').value = taskId;
    document.getElementById('deleteTaskName').textContent = taskTitle;
    document.getElementById('deleteModal').classList.add('show');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('show');
    document.getElementById('deleteTaskId').value = '0';
    document.getElementById('deleteTaskName').textContent = '';
}

function confirmDelete() {
    var taskId = document.getElementById('deleteTaskId').value;
    if (!taskId || taskId === '0') return;

    // Build a hidden form and submit it
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;
    form.style.display = 'none';

    var csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = CSRF_TOKEN;
    form.appendChild(csrfInput);

    var actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action_type';
    actionInput.value = 'delete_task';
    form.appendChild(actionInput);

    var idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'task_id';
    idInput.value = taskId;
    form.appendChild(idInput);

    document.body.appendChild(form);
    closeDeleteModal();
    form.submit();
}

// ── Save Day Title (AJAX + Toast) ──
function saveDayTitle(btn) {

    var input = document.getElementById('dayTitleInput');
    var day = input.getAttribute('data-day');
    var title = input.value.trim();

    // Disable button and show saving state
    btn.disabled = true;
    btn.textContent = 'Saving...';

    var formData = new FormData();
    formData.append('csrf_token', CSRF_TOKEN);
    formData.append('action_type', 'save_day_title');
    formData.append('mission_day', day);
    formData.append('day_title', title);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(function(response) { return response.text(); })
    .then(function(html) {
        // Check for success/error in the response
        if (html.indexOf('dashboard-message is-success') !== -1) {
            showToast('Day ' + day + ' title saved successfully!', 'success');
        } else if (html.indexOf('dashboard-message is-error') !== -1) {
            showToast('Error saving day title. Please try again.', 'error');
        } else {
            showToast('Day title updated!', 'success');
        }
        // Re-enable button
        btn.disabled = false;
        btn.textContent = 'Save';
    })
    .catch(function() {
        showToast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.textContent = 'Save';
    });
}
</script>

<?php if ($message !== ''): ?>
    <div class="dashboard-message <?php echo $message_type === 'error' ? 'is-error' : 'is-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="dashboard-header">

    <div class="dashboard-header-left">
        <div class="dashboard-header-icon"><i class="fas fa-list-check"></i></div>
        <div class="dashboard-header-text">
            <h1>Task Management</h1>
            <p>All tasks are stored in the database. Create, edit, or delete tasks below.</p>
        </div>
    </div>
    <span class="dashboard-header-badge"><i class="fas fa-circle"></i> Tasks</span>
</div>

<!-- ── Filters ── -->
<div class="dashboard-panel">
    <div class="dashboard-panel-header">
        <h3><i class="fas fa-filter"></i> Filters</h3>
        <span class="panel-badge"><?php echo number_format((int) count($task_rows)); ?> task(s)</span>
    </div>
    <form method="GET" class="admin-task-builder" style="margin-top:0;">
        <div class="admin-task-builder-grid">
            <div class="admin-form-field">
                <label>Task Group</label>
                <select name="group" onchange="this.form.submit()">
                    <?php foreach ($task_groups as $task_group_key => $task_group_label): ?>
                        <option value="<?php echo htmlspecialchars($task_group_key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected_group === $task_group_key ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($task_group_label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selected_group === 'mission'): ?>
                <div class="admin-form-field">
                    <label>Mission Day</label>
                    <select name="day" onchange="this.form.submit()">
                        <option value="0">All Days</option>
                        <?php for ($day = 1; $day <= (int) TASKHUB_TOTAL_DAYS; $day++): ?>
                            <option value="<?php echo $day; ?>" <?php echo $selected_day === $day ? 'selected' : ''; ?>>Day <?php echo $day; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
        <div class="admin-task-builder-actions">
            <span class="muted">Filter the list to edit tasks quickly.</span>
            <button type="submit" class="btn btn-secondary">Apply Filter</button>
        </div>
    </form>

    <?php if ($selected_group === 'mission'): ?>
        <div class="admin-task-builder-actions">
            <?php for ($day = 1; $day <= (int) TASKHUB_TOTAL_DAYS; $day++): ?>
                <a href="?group=mission&day=<?php echo $day; ?>" class="btn <?php echo $selected_day === $day ? 'btn-primary' : 'btn-secondary'; ?>">
                    Day <?php echo $day; ?> (<?php echo (int) ($mission_task_counts[$day] ?? 0); ?>)
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ── Day Settings (shown when a specific day is selected) ── -->
<?php if ($selected_group === 'mission' && $selected_day > 0): ?>
    <div class="day-settings-bar">
        <label>🏷️ Day <?php echo $selected_day; ?> Title</label>
        <input type="text" id="dayTitleInput" value="<?php echo htmlspecialchars((string) ($day_titles[$selected_day] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. Welcome Day" data-day="<?php echo $selected_day; ?>">
        <span class="day-title-hint">Applies to all tasks in Day <?php echo $selected_day; ?>. Pre-filled when creating new tasks.</span>
        <button type="button" class="btn btn-secondary btn-sm" onclick="saveDayTitle(this)">Save</button>

    </div>
<?php endif; ?>

<!-- ── Create Button ── -->
<?php if ($selected_group !== 'mission' || $selected_day > 0): ?>
    <div class="task-create-btn-wrap">
        <button type="button" class="btn btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Create New Task
        </button>
    </div>
<?php endif; ?>

<!-- ── Task List ── -->
<div class="dashboard-panel">
    <div class="dashboard-panel-header">
        <h3><i class="fas fa-pen-to-square"></i> Tasks</h3>
        <span class="panel-badge"><?php echo htmlspecialchars((string) ($task_groups[$selected_group] ?? $selected_group), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="admin-task-card-list">
        <?php if ($selected_group === 'mission' && $selected_day === 0): ?>
            <div class="admin-task-card">
                <div class="admin-task-card-head">
                    <h3>Select a day to manage tasks</h3>
                </div>
                <p class="muted">Pick a day above to view, create, or edit tasks for that day.</p>
            </div>
        <?php elseif (empty($task_rows)): ?>
            <div class="admin-task-card">
                <div class="admin-task-card-head">
                    <h3>No tasks found</h3>
                </div>
                <p class="muted">Try a different filter or create a new task.</p>
            </div>
        <?php else: ?>
            <?php foreach ($task_rows as $task): ?>
                <?php
                    $vm = $task['verification_mode'] ?? 'instant';
                    $icon_class = 'is-' . $vm;
                    $icon_map = [
                        'instant' => '⚡', 'profile' => '👤', 'manual' => '📋',
                        'quiz' => '📝', 'wallet' => '💳', 'boosthub' => '🚀',
                        'mystery' => '🎁', 'claim_awareness' => '🔔'
                    ];
                    $icon = $icon_map[$vm] ?? '📌';
                    $is_mission = ($task['task_group'] ?? '') === 'mission';
                ?>
                <div class="task-card-compact" data-task-id="<?php echo (int) $task['id']; ?>">
                    <div class="task-card-left">
                        <div class="task-card-icon <?php echo $icon_class; ?>"><?php echo $icon; ?></div>
                        <div class="task-card-info">
                            <h4><?php echo htmlspecialchars((string) $task['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                            <div class="task-card-meta">
                                <span>🏷️ <?php echo htmlspecialchars((string) ($task_categories[$task['task_category'] ?? 'custom'] ?? 'Custom'), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($is_mission): ?>
                                    <span>📅 Day <?php echo (int) ($task['mission_day'] ?? 0); ?> / Step <?php echo (int) ($task['mission_step'] ?? 0); ?></span>
                                <?php endif; ?>
                                <span>💰 <?php echo number_format((float) ($task['reward'] ?? 0), 2); ?> $REX</span>
                                <span>🔁 <?php echo ucfirst((string) $vm); ?></span>
                                <?php if (!empty($task['is_active'])): ?>
                                    <span style="color:#4ade80;">● Active</span>
                                <?php else: ?>
                                    <span style="color:#f87171;">● Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="task-card-right">
                        <div class="task-card-stats">
                            <span><strong><?php echo number_format((int) ($task['completed_total'] ?? 0)); ?></strong> done</span>
                            <span><strong><?php echo number_format((int) ($task['completed_today'] ?? 0)); ?></strong> today</span>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="openEditModal(<?php echo (int) $task['id']; ?>)">
                            <i class="fas fa-pen"></i> Edit
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="openDeleteModal(<?php echo (int) $task['id']; ?>, '<?php echo htmlspecialchars(str_replace("'", "\\'", (string) $task['title']), ENT_QUOTES, 'UTF-8'); ?>')">
                            <i class="fas fa-trash"></i>
                        </button>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ════════════════════════════════════════════════════════════════ -->
<!-- ── MODAL: Create / Edit Task ── -->
<!-- ════════════════════════════════════════════════════════════════ -->
<div class="dashboard-modal" id="taskModal">
    <div class="dashboard-modal-card" style="width:min(860px,100%);">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker" id="modalKicker">MicroMission</span>
                <h3 id="modalTitle">Create Task</h3>
            </div>
            <button type="button" class="dashboard-modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="dashboard-modal-body">
            <form method="POST" action="" id="taskForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action_type" value="save_task">
                <input type="hidden" name="task_id" id="formTaskId" value="0">
                <input type="hidden" name="task_group" id="formTaskGroup" value="<?php echo htmlspecialchars($selected_group, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="mission_day" id="formMissionDay" value="<?php echo (int) $selected_day; ?>">
                <input type="hidden" name="mission_step" id="formMissionStep" value="0">

                <!-- Tabs -->
                <div class="modal-tabs" id="modalTabs">
                    <button type="button" class="modal-tab active" data-tab="basic">📋 Basic Info</button>
                    <button type="button" class="modal-tab" data-tab="details">🔧 Task Details</button>
                    <button type="button" class="modal-tab" data-tab="learning" id="learningTab">📖 Learning & Quiz</button>
                </div>

                <!-- Tab 1: Basic Info -->
                <div class="modal-tab-content active" id="tabBasic">
                    <div class="modal-field">
                        <label>Task Name</label>
                        <input type="text" name="title" id="f_title" placeholder="e.g. Follow CoinRex on X" required>
                        <p class="field-hint">What users see as the task title. Keep it short and clear.</p>
                    </div>
                    <div class="modal-field">
                        <label>Short Description</label>
                        <input type="text" name="description" id="f_description" placeholder="e.g. Follow @CoinRex on X and earn rewards" required>
                        <p class="field-hint">One-line explanation of what the user needs to do.</p>
                    </div>
                    <div class="modal-field-row">
                        <div class="modal-field">
                            <label>Task Type</label>
                            <select name="task_category" id="f_task_category">
                                <?php foreach ($task_categories as $task_category_key => $task_category_label): ?>
                                    <option value="<?php echo htmlspecialchars($task_category_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($task_category_label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-hint">Categorizes the task (YouTube, X, Telegram, etc.)</p>
                        </div>
                        <div class="modal-field">
                            <label>Reward ($REX)</label>
                            <input type="text" name="reward" id="f_reward" placeholder="1.50" required>
                            <p class="field-hint">Earnings per completion in $REX.</p>
                        </div>
                    </div>
                    <div class="modal-field-row">
                        <div class="modal-field">
                            <label>Daily Limit</label>
                            <input type="text" name="daily_limit" id="f_daily_limit" value="1">
                            <p class="field-hint">How many times per day a user can complete this.</p>
                        </div>
                        <div class="modal-field">
                            <label>Repeat Gap (seconds)</label>
                            <input type="text" name="cooldown_seconds" id="f_cooldown_seconds" value="86400">
                            <p class="field-hint">Cooldown before a user can re-attempt (86400 = 24h).</p>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Task Details -->
                <div class="modal-tab-content" id="tabDetails">
                    <div class="modal-field">
                        <label>Destination Link <span class="muted">(optional)</span></label>
                        <input type="text" name="task_link" id="f_task_link" placeholder="https://coinrex.xyz/some-page">
                        <p class="field-hint">URL users must visit to complete the task. Leave empty if not needed.</p>
                    </div>
                    <div class="modal-field">
                        <label>How To Complete</label>
                        <textarea name="completion_steps" id="f_completion_steps" rows="4" placeholder="1. Open the link&#10;2. Complete the requested action&#10;3. Return and confirm completion"></textarea>
                        <p class="field-hint">Step-by-step instructions for the user to follow.</p>
                    </div>

                    <div class="modal-field">
                        <label>Proof / Review Notes <span class="muted">(optional)</span></label>
                        <textarea name="proof_notes" id="f_proof_notes" rows="3" placeholder="Optional notes for the user or the reviewer."></textarea>
                        <p class="field-hint">Additional notes shown to the user or used during manual review.</p>
                    </div>
                    <div class="modal-field-row">
                        <div class="modal-field">
                            <label>Button Label</label>
                            <input type="text" name="cta_label" id="f_cta_label" placeholder="Open Task">
                            <p class="field-hint">Text shown on the call-to-action button.</p>
                        </div>
                        <div class="modal-field">
                            <label>Verification Mode</label>
                            <select name="verification_mode" id="f_verification_mode" onchange="toggleVerificationMode()">
                                <?php foreach ($verification_modes as $mode): ?>
                                    <option value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $mode)), ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-hint">How the system confirms the user completed the task.</p>
                        </div>
                    </div>
                    <div class="modal-checkbox-row" id="quizCheckboxRow" style="display:none;">
                        <label><input type="checkbox" name="requires_quiz" id="f_requires_quiz" value="1"> Requires Quiz</label>
                        <label><input type="checkbox" name="requires_manual_review" id="f_requires_manual_review" value="1"> Manual Review</label>
                    </div>
                </div>

                <!-- Tab 3: Learning & Quiz (mission only) -->
                <div class="modal-tab-content" id="tabLearning">
                    <div class="modal-field">
                        <label>📖 Learning Title <span class="muted">(optional)</span></label>
                        <input type="text" name="learning_title" id="f_learning_title" placeholder="e.g. Terms of Service">
                        <p class="field-hint">The page name shown to users — they'll see <strong>📖 Terms of Service</strong>.</p>
                    </div>
                    <div class="modal-field">
                        <label>🔗 Learning URL <span class="muted">(optional)</span></label>
                        <input type="text" name="learning_url" id="f_learning_url" placeholder="e.g. <?php echo htmlspecialchars(BASE_URL . '/terms.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <p class="field-hint">The page users must read before completing the task. Must start with <strong>https://</strong>.</p>
                    </div>
                    <div class="modal-field-row">
                        <div class="modal-field">
                            <label>⏱️ Required Reading Seconds</label>
                            <input type="number" name="required_reading_seconds" id="f_required_reading_seconds" value="45" min="15" max="120" placeholder="45">
                            <p class="field-hint">Minimum reading time (15–120 seconds) before verification.</p>
                        </div>
                        <div class="modal-field">
                            <label>🏷️ Day Title <span class="muted">(optional)</span></label>
                            <input type="text" name="day_title" id="f_day_title" placeholder="e.g. Welcome Day">
                            <p class="field-hint">Overrides the auto-generated day name. Same for all tasks in this day.</p>
                        </div>
                    </div>
                    <div class="modal-field-row">
                        <div class="modal-field">
                            <label>Min Quiz Score</label>
                            <input type="text" name="min_quiz_score" id="f_min_quiz_score" value="0">
                            <p class="field-hint">Minimum correct answers required to pass the quiz.</p>
                        </div>
                        <div class="modal-field" style="display:flex;flex-direction:column;justify-content:flex-end;">
                            <div class="modal-checkbox-row">
                                <label><input type="checkbox" name="requires_quiz" id="f_requires_quiz2" value="1"> Requires Quiz</label>
                                <label><input type="checkbox" name="requires_manual_review" id="f_requires_manual_review2" value="1"> Manual Review</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-field" id="manageQuizLink" style="display:none; margin-top:8px;">
                        <a href="#" class="btn btn-primary" target="_blank" id="manageQuizBtn">📝 Manage Quiz →</a>
                        <p class="field-hint" style="margin-top:4px;">Click to add or edit quiz questions. If no questions are added, the default quiz will be used automatically.</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer-actions">
                    <div class="left-actions">
                        <label class="checkbox-inline" style="font-size:13px;color:#94a3b8;">
                            <input type="checkbox" name="is_active" id="f_is_active" value="1" checked> Active
                        </label>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Create Task</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ── Tab Switching ──

(function() {
    var tabs = document.querySelectorAll('.modal-tab');
    for (var i = 0; i < tabs.length; i++) {
        tabs[i].addEventListener('click', function() {
            var allTabs = document.querySelectorAll('.modal-tab');
            for (var j = 0; j < allTabs.length; j++) {
                allTabs[j].classList.remove('active');
            }
            var allContent = document.querySelectorAll('.modal-tab-content');
            for (var k = 0; k < allContent.length; k++) {
                allContent[k].classList.remove('active');
            }
            this.classList.add('active');
            var tabName = this.getAttribute('data-tab');
            var contentId = 'tab' + tabName.charAt(0).toUpperCase() + tabName.slice(1);
            document.getElementById(contentId).classList.add('active');
        });
    }
})();

// ── Show toast for PHP flash messages on page load ──

(function() {
    var msgEl = document.querySelector('.dashboard-message');
    if (msgEl) {
        var msgText = msgEl.textContent.trim();
        var isError = msgEl.classList.contains('is-error');
        if (msgText) {
            // Delay slightly to let toast container initialize
            setTimeout(function() {
                showToast(msgText, isError ? 'error' : 'success');
            }, 300);
        }
    }
})();

// ── Close modal on overlay click ──
document.getElementById('taskModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ── Close delete modal on overlay click ──
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// ── Close modals on Escape key ──
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeDeleteModal();
    }
});

</script>


<!-- ════════════════════════════════════════════════════════════════ -->
<!-- ── MODAL: Delete Confirmation ── -->
<!-- ════════════════════════════════════════════════════════════════ -->
<div class="dashboard-modal" id="deleteModal">
    <div class="dashboard-modal-card" style="width:min(440px,100%);">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker" style="color:#f87171;">⚠️ Confirm Deletion</span>
                <h3>Delete Task</h3>
            </div>
            <button type="button" class="dashboard-modal-close" onclick="closeDeleteModal()">&times;</button>
        </div>
        <div class="dashboard-modal-body" style="text-align:center;">
            <div style="font-size:48px;margin-bottom:12px;">🗑️</div>
            <p style="color:#cbd5e1;font-size:15px;margin:0 0 6px;">
                Are you sure you want to delete this task?
            </p>
            <p style="color:#f87171;font-size:13px;font-weight:600;margin:0 0 16px;" id="deleteTaskName"></p>
            <p style="color:#64748b;font-size:12px;margin:0 0 20px;">
                This action <strong>cannot be undone</strong>. All completion data for this task will also be removed.
            </p>
            <input type="hidden" id="deleteTaskId" value="0">
            <div style="display:flex;gap:10px;justify-content:center;">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                    <i class="fas fa-trash"></i> Delete Task
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>



