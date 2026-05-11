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
    }
}
$task_categories = adminRewardTaskCategories();
?>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Tasks</span>
            <h2>Task Management</h2>
            <p class="muted">MicroMission is preloaded for all 10 days. Admin can edit or pause any task.</p>
        </div>
    </div>
    <form method="GET" class="admin-task-builder">
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
        <?php if ($selected_day === 0): ?>
            <div class="admin-task-card">
                <div class="admin-task-card-head">
                    <h3>Select a day to edit tasks</h3>
                </div>
                <p class="muted">The mission list stays clean by default. Pick a day above to open only that day tasks.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($selected_group !== 'mission'): ?>
        <form method="POST" action="" class="admin-task-builder">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action_type" value="save_task">
            <input type="hidden" name="task_id" value="0">
            <input type="hidden" name="task_group" value="<?php echo htmlspecialchars($selected_group, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="admin-task-builder-grid">
                <div class="admin-form-field">
                    <label>Task Name</label>
                    <input type="text" name="title" placeholder="Visit CoinRex Website" required>
                </div>
                <div class="admin-form-field">
                    <label>Task Type</label>
                    <select name="task_category">
                        <?php foreach ($task_categories as $task_category_key => $task_category_label): ?>
                            <option value="<?php echo htmlspecialchars($task_category_key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($task_category_label, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="admin-form-field">
                    <label>Reward</label>
                    <input type="text" name="reward" placeholder="1.50" required>
                </div>
                <div class="admin-form-field">
                    <label>Repeat Gap</label>
                    <input type="text" name="cooldown_seconds" value="86400">
                </div>
                <div class="admin-form-field admin-form-field-span-2">
                    <label>Short Description</label>
                    <input type="text" name="description" placeholder="Explain the task in one clear line." required>
                </div>
                <div class="admin-form-field admin-form-field-span-2">
                    <label>Destination Link (optional)</label>
                    <input type="text" name="task_link" placeholder="https://coinrex.com/some-page">
                </div>
                <div class="admin-form-field admin-form-field-span-2">
                    <label>How To Complete</label>
                    <textarea name="completion_steps" rows="4" placeholder="1. Open the link&#10;2. Complete the requested action&#10;3. Return and confirm completion" required></textarea>
                </div>
                <div class="admin-form-field admin-form-field-span-2">
                    <label>Proof Or Review Notes</label>
                    <textarea name="proof_notes" rows="3" placeholder="Optional notes for the user or the reviewer."></textarea>
                </div>
                <div class="admin-form-field">
                    <label>Button Label</label>
                    <input type="text" name="cta_label" placeholder="Open Link">
                </div>
                <div class="admin-form-field">
                    <label>Daily Limit</label>
                    <input type="text" name="daily_limit" value="1">
                </div>
            </div>
            <div class="admin-task-builder-actions">
                <label class="checkbox-inline"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                <button type="submit" class="btn btn-primary">Create Task</button>
            </div>
        </form>
    <?php endif; ?>

    <div class="admin-task-card-list">
        <?php if ($selected_group === 'mission' && $selected_day === 0): ?>
        <?php else: ?>
        <?php if (empty($task_rows)): ?>
            <div class="admin-task-card">
                <div class="admin-task-card-head">
                    <h3>No tasks found</h3>
                </div>
                <p class="muted">Try a different filter.</p>
            </div>
        <?php endif; ?>
        <?php foreach ($task_rows as $task): ?>
            <form method="POST" action="" class="admin-task-card">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action_type" value="save_task">
                <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>">
                <input type="hidden" name="task_group" value="<?php echo htmlspecialchars((string) ($task['task_group'] ?? 'legacy'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="admin-task-card-head">
                    <div>
                        <span class="status-pill status-under-review"><?php echo htmlspecialchars((string) ($task_categories[$task['task_category'] ?? 'custom'] ?? 'Custom Task'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if (($task['task_group'] ?? '') === 'mission'): ?>
                            <span class="status-pill status-pending">Day <?php echo (int) ($task['mission_day'] ?? 0); ?> / Step <?php echo (int) ($task['mission_step'] ?? 0); ?></span>
                        <?php endif; ?>
                        <h3><?php echo htmlspecialchars((string) $task['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    </div>
                    <label class="checkbox-inline"><input type="checkbox" name="is_active" value="1" <?php echo !empty($task['is_active']) ? 'checked' : ''; ?>> Active</label>
                </div>
                <?php if (($task['task_group'] ?? '') === 'mission'): ?>
                <details class="admin-task-collapse">
                    <summary class="btn btn-secondary">Open Task Editor</summary>
                <?php endif; ?>
                <div class="admin-task-builder-grid" style="<?php echo (($task['task_group'] ?? '') === 'mission') ? 'margin-top:12px;' : ''; ?>">
                    <div class="admin-form-field">
                        <label>Task Name</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars((string) $task['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="admin-form-field">
                        <label>Task Type</label>
                        <select name="task_category">
                            <?php foreach ($task_categories as $task_category_key => $task_category_label): ?>
                                <option value="<?php echo htmlspecialchars($task_category_key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($task['task_category'] ?? 'custom') === $task_category_key ? 'selected' : ''; ?>><?php echo htmlspecialchars($task_category_label, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label>Reward</label>
                        <input type="text" name="reward" value="<?php echo htmlspecialchars((string) $task['reward'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="admin-form-field">
                        <label>Repeat Gap</label>
                        <input type="text" name="cooldown_seconds" value="<?php echo (int) $task['cooldown_seconds']; ?>">
                    </div>
                    <?php if (($task['task_group'] ?? '') === 'mission'): ?>
                        <div class="admin-form-field">
                            <label>Mission Day</label>
                            <input type="text" name="mission_day" value="<?php echo (int) ($task['mission_day'] ?? 0); ?>" required>
                        </div>
                        <div class="admin-form-field">
                            <label>Mission Step</label>
                            <input type="text" name="mission_step" value="<?php echo (int) ($task['mission_step'] ?? 0); ?>" required>
                        </div>
                    <?php endif; ?>
                    <div class="admin-form-field admin-form-field-span-2">
                        <label>Short Description</label>
                        <input type="text" name="description" value="<?php echo htmlspecialchars((string) ($task['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="admin-form-field admin-form-field-span-2">
                        <label>Destination Link (optional)</label>
                        <input type="text" name="task_link" value="<?php echo htmlspecialchars((string) ($task['task_link'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="admin-form-field admin-form-field-span-2">
                        <label>How To Complete</label>
                        <textarea name="completion_steps" rows="4" required><?php echo htmlspecialchars((string) ($task['completion_steps'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="admin-form-field admin-form-field-span-2">
                        <label>Proof Or Review Notes</label>
                        <textarea name="proof_notes" rows="3"><?php echo htmlspecialchars((string) ($task['proof_notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div class="admin-form-field">
                        <label>Button Label</label>
                        <input type="text" name="cta_label" value="<?php echo htmlspecialchars((string) ($task['cta_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="admin-form-field">
                        <label>Daily Limit</label>
                        <input type="text" name="daily_limit" value="<?php echo (int) $task['daily_limit']; ?>">
                    </div>
                    <?php if (($task['task_group'] ?? '') === 'mission'): ?>
                        <div class="admin-form-field">
                            <label>Verification Mode</label>
                            <select name="verification_mode">
                                <?php foreach (['instant','profile','manual','quiz','wallet','boosthub','claim_awareness','mystery'] as $mode): ?>
                                    <option value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($task['verification_mode'] ?? 'instant') === $mode) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $mode)), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="admin-form-field">
                            <label>Min Quiz Score</label>
                            <input type="text" name="min_quiz_score" value="<?php echo (int) ($task['min_quiz_score'] ?? 0); ?>">
                            <label class="checkbox-inline"><input type="checkbox" name="requires_quiz" value="1" <?php echo !empty($task['requires_quiz']) ? 'checked' : ''; ?>> Requires Quiz</label>
                            <label class="checkbox-inline"><input type="checkbox" name="requires_manual_review" value="1" <?php echo !empty($task['requires_manual_review']) ? 'checked' : ''; ?>> Manual Review</label>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="admin-task-card-foot">
                    <div class="admin-task-stat-line">
                        <span><?php echo number_format((int) ($task['completed_total'] ?? 0)); ?> completed</span>
                        <span><?php echo number_format((int) ($task['completed_today'] ?? 0)); ?> today</span>
                        <span><?php echo number_format((int) ($task['blocked_total'] ?? 0)); ?> blocked</span>
                    </div>
                    <button type="submit" class="btn btn-secondary">Save Changes</button>
                </div>
                <?php if (($task['task_group'] ?? '') === 'mission'): ?>
                </details>
                <?php endif; ?>
            </form>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
