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
<style>
/* ── Tab Navigation ── */
.boosthub-tabs {
    display: flex;
    gap: 4px;
    margin-bottom: 24px;
    background: rgba(15, 23, 42, 0.6);
    border-radius: 14px;
    padding: 4px;
    border: 1px solid rgba(148, 163, 184, 0.08);
    overflow-x: auto;
}
.boosthub-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 22px;
    border-radius: 11px;
    border: none;
    background: transparent;
    color: #94a3b8;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    flex-shrink: 0;
}
.boosthub-tab:hover {
    color: #e2e8f0;
    background: rgba(255,255,255,0.04);
}
.boosthub-tab.is-active {
    background: linear-gradient(135deg, rgba(29, 78, 216, 0.92), rgba(30, 64, 175, 0.88));
    color: #fff;
    box-shadow: 0 4px 12px rgba(29, 78, 216, 0.25);
}
.boosthub-tab i {
    font-size: 0.95rem;
}
.boosthub-tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: rgba(255,255,255,0.12);
    font-size: 0.72rem;
    font-weight: 800;
}
.boosthub-tab.is-active .boosthub-tab-badge {
    background: rgba(255,255,255,0.18);
}

/* ── Tab Panels ── */
.boosthub-tab-panel {
    display: none;
}
.boosthub-tab-panel.is-active {
    display: block;
    animation: bhFadeUp 0.3s ease;
}

/* ── Evidence Filter ── */
.boosthub-evidence-filter {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.boosthub-evidence-filter label {
    color: #94a3b8;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.boosthub-evidence-filter select {
    padding: 8px 14px;
    border-radius: 10px;
    border: 1px solid rgba(148, 163, 184, 0.15);
    background: rgba(15, 23, 42, 0.8);
    color: #e2e8f0;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    outline: none;
    min-width: 180px;
}
.boosthub-evidence-filter select:focus {
    border-color: rgba(29, 78, 216, 0.4);
    box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
}

@keyframes bhFadeUp {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* ── Responsive: Mobile ── */
@media (max-width: 768px) {
    .boosthub-tabs {
        gap: 2px;
        padding: 3px;
        border-radius: 12px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .boosthub-tabs::-webkit-scrollbar { display: none; }
    .boosthub-tab {
        padding: 10px 14px;
        font-size: 0.8rem;
        gap: 6px;
    }
    .boosthub-tab i { font-size: 0.85rem; }
    .boosthub-tab-badge { min-width: 18px; height: 18px; font-size: 0.65rem; padding: 0 5px; }

    /* Evidence filter */
    .boosthub-evidence-filter {
        flex-direction: column;
        align-items: stretch;
        gap: 8px;
    }
    .boosthub-evidence-filter select { min-width: 0; width: 100%; }

    /* Evidence review table → card layout */
    .dashboard-table-wrap {
        overflow-x: visible;
    }
    .dashboard-table thead { display: none; }
    .dashboard-table tbody tr {
        display: block;
        padding: 16px;
        margin-bottom: 12px;
        background: rgba(15, 23, 42, 0.7);
        border: 1px solid rgba(148, 163, 184, 0.10);
        border-radius: 14px;
    }
    .dashboard-table tbody tr td {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        padding: 6px 0;
        border: none;
        text-align: left;
    }
    .dashboard-table tbody tr td::before {
        content: attr(data-label);
        flex-shrink: 0;
        width: 80px;
        font-size: 0.75rem;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding-top: 2px;
    }
    .dashboard-table tbody tr td:last-child {
        padding-top: 10px;
        margin-top: 6px;
        border-top: 1px solid rgba(148, 163, 184, 0.08);
        flex-wrap: wrap;
        gap: 6px;
    }
    .dashboard-table tbody tr td:last-child::before {
        width: 100%;
        margin-bottom: 4px;
    }
    .dashboard-table tbody tr td:last-child .btn {
        flex: 1;
        min-width: 0;
        justify-content: center;
        font-size: 0.78rem;
        padding: 8px 10px;
    }
    .dashboard-table tbody tr td:last-child .btn + .btn {
        margin-top: 0 !important;
    }
    .boosthub-evidence-box {
        flex-direction: column;
        max-width: none;
        width: 100%;
    }
    .boosthub-evidence-box code {
        max-width: 100%;
        font-size: 0.8rem;
    }
    .boosthub-evidence-screenshot img {
        max-width: 100% !important;
        max-height: 120px !important;
    }
    .boosthub-open-task-link {
        width: 100%;
        justify-content: center;
    }

    /* Task toolbar */
    .quiz-toolbar {
        flex-direction: column;
        gap: 10px;
    }
    .quiz-toolbar-left,
    .quiz-toolbar-right {
        width: 100%;
    }
    .quiz-toolbar-right select {
        width: 100%;
        min-width: 0;
    }
    .quiz-toolbar-left .btn {
        width: 100%;
        justify-content: center;
    }

    /* Task cards */
    .boosthub-task-card-head {
        flex-direction: column;
        align-items: flex-start;
    }
    .boosthub-task-meta {
        flex-direction: column;
        gap: 6px;
    }
    .boosthub-task-stats {
        flex-direction: column;
        gap: 4px;
    }
    .boosthub-task-card-foot {
        flex-wrap: wrap;
        gap: 6px;
    }
    .boosthub-task-card-foot .btn {
        flex: 1;
        justify-content: center;
        font-size: 0.78rem;
        padding: 8px 10px;
    }

    /* Modals */
    .quiz-modal {
        width: calc(100vw - 24px) !important;
        margin: 0 12px !important;
        max-height: 90vh !important;
        border-radius: 18px !important;
    }
    .quiz-modal-header {
        padding: 16px 18px !important;
    }
    .quiz-modal-header h2 {
        font-size: 1rem !important;
    }
    .quiz-modal-body {
        padding: 14px 18px !important;
        gap: 14px !important;
    }
    .quiz-modal-footer {
        padding: 14px 18px !important;
        flex-direction: column;
        gap: 8px;
    }
    .quiz-modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
    .quiz-modal-field label {
        font-size: 0.8rem !important;
    }
    .quiz-modal-field input,
    .quiz-modal-field select,
    .quiz-modal-field textarea {
        font-size: 0.85rem !important;
        padding: 10px 12px !important;
    }

    /* Delete/Review modals */
    .dashboard-modal-card {
        width: calc(100vw - 32px) !important;
        max-width: none !important;
        margin: 0 16px !important;
        border-radius: 18px !important;
    }
    .dashboard-modal-card .dashboard-modal-body > div {
        flex-direction: column;
        gap: 8px;
    }
    .dashboard-modal-card .dashboard-modal-body .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .boosthub-tab {
        padding: 8px 10px;
        font-size: 0.75rem;
    }
    .boosthub-tab i { display: none; }
    .dashboard-table tbody tr td::before {
        width: 70px;
        font-size: 0.7rem;
    }
    .dashboard-table tbody tr td {
        font-size: 0.82rem;
    }
}
</style>

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
<!-- Tab Navigation -->
<!-- ============================================================ -->
<div class="boosthub-tabs" role="tablist">
    <button type="button" class="boosthub-tab is-active" data-tab="tasks" role="tab" aria-selected="true">
        <i class="fas fa-wrench"></i> Tasks
        <span class="boosthub-tab-badge" id="boosthubTaskCount">0</span>
    </button>
    <button type="button" class="boosthub-tab" data-tab="reviews" role="tab" aria-selected="false">
        <i class="fas fa-inbox"></i> Evidence Review
        <span class="boosthub-tab-badge" id="boosthubReviewCount">0</span>
    </button>
</div>

<!-- ============================================================ -->
<!-- Tasks Tab Panel -->
<!-- ============================================================ -->
<div class="boosthub-tab-panel is-active" id="boosthubTabTasks" role="tabpanel">
    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <h3><i class="fas fa-wrench"></i> Task management</h3>
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
</div>

<!-- ============================================================ -->
<!-- Evidence Review Tab Panel -->
<!-- ============================================================ -->
<div class="boosthub-tab-panel" id="boosthubTabReviews" role="tabpanel">
    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <h3><i class="fas fa-inbox"></i> Evidence review queue</h3>
        </div>

        <!-- Evidence Filter -->
        <div class="boosthub-evidence-filter">
            <label for="boosthubEvidenceFilter"><i class="fas fa-filter"></i> Filter by task type:</label>
            <select id="boosthubEvidenceFilter">
                <option value="all">All Types</option>
                <?php foreach ($task_categories as $task_category_key => $task_category_label): ?>
                    <option value="<?php echo htmlspecialchars($task_category_key, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($task_category_label, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
