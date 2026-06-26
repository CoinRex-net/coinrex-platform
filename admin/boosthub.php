<?php
$page_title = 'BoostHub Area';
$activePage = 'boosthub-management';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);
$current_admin = getCurrentAdmin();
[$message, $message_type] = adminRewardProcessAction($db, $current_admin);
$task_categories = adminRewardTaskCategories();
$selected_category = trim((string) ($_GET['task_category'] ?? 'all'));
if ($selected_category !== 'all' && !array_key_exists($selected_category, $task_categories)) {
    $selected_category = 'all';
}
?>

<?php if ($message !== ''): ?>
    <div class="dashboard-message <?php echo $message_type === 'error' ? 'is-error' : 'is-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="dashboard-header">
    <div class="dashboard-header-left">
        <div class="dashboard-header-icon"><i class="fas fa-bolt"></i></div>
        <div class="dashboard-header-text">
            <h1>BoostHub Management</h1>
            <p>Manage BoostHub tasks and review submitted evidence.</p>
        </div>
    </div>
    <span class="dashboard-header-badge"><i class="fas fa-circle"></i> BoostHub</span>
</div>

<!-- ============================================================ -->
<!-- Task Management Panel -->
<!-- ============================================================ -->
<div class="dashboard-panel">
    <div class="dashboard-panel-header">
        <h3><i class="fas fa-wrench"></i> Task management</h3>
        <span class="panel-badge" id="boosthubTaskCount">0 task(s)</span>
    </div>

    <!-- Toolbar -->
    <div class="quiz-toolbar">
        <div class="quiz-toolbar-left">
            <button type="button" class="btn btn-primary" id="boosthubAddBtn">
                <i class="fas fa-plus"></i> Create New Task
            </button>
        </div>
        <div class="quiz-toolbar-right">
            <select id="boosthubCategoryFilter" class="quiz-filter-select">
                <option value="all" <?php echo $selected_category === 'all' ? 'selected' : ''; ?>>All Types</option>
                <?php foreach ($task_categories as $task_category_key => $task_category_label): ?>
                    <option value="<?php echo htmlspecialchars($task_category_key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected_category === $task_category_key ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($task_category_label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Task Cards Container (rendered by JS) -->
    <div id="boosthubTaskList" class="boosthub-task-list">
        <div class="quiz-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Evidence Review Panel -->
<!-- ============================================================ -->
<div class="dashboard-panel">
    <div class="dashboard-panel-header">
        <h3><i class="fas fa-inbox"></i> Evidence review queue</h3>
        <span class="panel-badge" id="boosthubReviewCount">0 pending</span>
    </div>
    <div class="dashboard-table-wrap">
        <table class="dashboard-table">
            <thead>
            <tr>
                <th>User</th>
                <th>Task</th>
                <th>Evidence</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody id="boosthubReviewContainer">
                <tr>
                    <td colspan="4" class="dashboard-empty">
                        <i class="fas fa-spinner fa-spin"></i>
                        <p>Loading...</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- Task Add/Edit Modal -->
<!-- ============================================================ -->
<div class="quiz-modal-overlay" id="boosthubTaskModalOverlay"></div>
<div class="quiz-modal" id="boosthubTaskModal">
    <div class="quiz-modal-header">
        <h2>Create New BoostHub Task</h2>
        <button type="button" class="quiz-modal-close" id="boosthubTaskModalCancel">&times;</button>
    </div>
    <form id="boosthubTaskModalForm" novalidate>
        <div class="quiz-modal-body">
            <input type="hidden" id="boosthubTaskModalId" value="0">

            <div class="quiz-modal-field">
                <label for="boosthubTaskModalTitle">Task Name</label>
                <input type="text" id="boosthubTaskModalTitle" placeholder="Join CoinRex Telegram" required>
            </div>

            <div class="quiz-modal-field">
                <label for="boosthubTaskModalCategory">Task Type</label>
                <select id="boosthubTaskModalCategory">
                    <?php foreach ($task_categories as $task_category_key => $task_category_label): ?>
                        <option value="<?php echo htmlspecialchars($task_category_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($task_category_label, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="quiz-modal-field">
                <label for="boosthubTaskModalReward">Reward ($REX)</label>
                <input type="number" step="0.01" min="0.01" id="boosthubTaskModalReward" placeholder="2.00" required>
            </div>

            <div class="quiz-modal-field">
                <label for="boosthubTaskModalCooldown">Repeat Gap (seconds)</label>
                <input type="number" min="0" id="boosthubTaskModalCooldown" value="86400">
            </div>

            <div class="quiz-modal-field">
                <label for="boosthubTaskModalDescription">Short Description</label>
                <input type="text" id="boosthubTaskModalDescription" placeholder="Ask the user to join, follow, or visit the campaign link." required>
            </div>

            <div class="quiz-modal-field">
                <label for="boosthubTaskModalLink">Destination Link</label>
                <input type="text" id="boosthubTaskModalLink" placeholder="https://t.me/coinrexchannel">
            </div>

            <div class="quiz-modal-field">
                <label for="boosthubTaskModalSteps">How To Complete</label>
                <textarea id="boosthubTaskModalSteps" rows="4" placeholder="1. Open the link&#10;2. Complete the join/follow/subscribe action&#10;3. Return and confirm completion" required></textarea>
            </div>

            <div class="quiz-modal-field">
                <label for="boosthubTaskModalProof">Proof Or Review Notes</label>
                <textarea id="boosthubTaskModalProof" rows="3" placeholder="Explain what the user must submit or what the system will verify."></textarea>
            </div>

            <div class="quiz-modal-field">
                <label for="boosthubTaskModalCta">Button Label</label>
                <input type="text" id="boosthubTaskModalCta" placeholder="Open Telegram">
            </div>

            <div class="quiz-modal-field">
                <label for="boosthubTaskModalDailyLimit">Daily Limit</label>
                <input type="number" min="1" id="boosthubTaskModalDailyLimit" value="1">
            </div>

            <div class="quiz-modal-field">
                <label class="checkbox-inline">
                    <input type="checkbox" id="boosthubTaskModalActive" checked> Active
                </label>
            </div>
        </div>
        <div class="quiz-modal-footer">
            <button type="button" class="btn btn-secondary" id="boosthubTaskModalCancel2">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Task</button>
        </div>
    </form>
</div>

<!-- ============================================================ -->
<!-- Delete Confirmation Modal -->
<!-- ============================================================ -->
<div class="dashboard-modal" id="boosthubDeleteModal">
    <div class="dashboard-modal-card" style="max-width:460px;">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-trash"></i> Delete Task</span>
                <h3>Delete this task?</h3>
            </div>
            <button type="button" class="dashboard-modal-close" id="boosthubCancelDelete">&times;</button>
        </div>
        <div class="dashboard-modal-body">
            <p style="color:#cbd5e1;margin:0 0 16px;">This action is permanent and cannot be undone.</p>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" id="boosthubCancelDelete2">Cancel</button>
                <button type="button" class="btn" id="boosthubConfirmDelete" style="background:#991b1b;color:#fee2e2;border:1px solid #ef4444;">Yes, Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- Review Confirmation Modal -->
<!-- ============================================================ -->
<div class="dashboard-modal" id="boosthubReviewModal">
    <div class="dashboard-modal-card" style="max-width:460px;">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-clipboard-check"></i> Review Submission</span>
                <h3>Confirm decision</h3>
            </div>
            <button type="button" class="dashboard-modal-close" id="boosthubReviewModalClose">&times;</button>
        </div>
        <div class="dashboard-modal-body">
            <p style="color:#cbd5e1;margin:0 0 16px;" id="boosthubReviewModalMessage">Are you sure you want to approve this submission?</p>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" class="btn btn-secondary" id="boosthubReviewModalCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="boosthubReviewModalConfirm">Yes, Approve</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo ADMIN_BASE_URL; ?>/assets/js/boosthub-manager.js"></script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
