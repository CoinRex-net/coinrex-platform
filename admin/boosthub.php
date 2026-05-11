<?php
$page_title = 'BoostHub Management';
$activePage = 'boosthub-management';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);
$current_admin = getCurrentAdmin();
[$message, $message_type] = adminRewardProcessAction($db, $current_admin);
$task_rows = adminRewardGetTasks($db, 'boosthub');
$boosthub_review_rows = adminRewardGetBoosthubReviewRows($db);
$task_categories = adminRewardTaskCategories();
$selected_category = trim((string) ($_GET['task_category'] ?? 'all'));
if ($selected_category !== 'all' && !array_key_exists($selected_category, $task_categories)) {
    $selected_category = 'all';
}

if ($selected_category !== 'all') {
    $task_rows = array_values(array_filter($task_rows, static function (array $task) use ($selected_category): bool {
        return (string) ($task['task_category'] ?? 'custom') === $selected_category;
    }));
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
            <span class="admin-kicker">BoostHub</span>
            <h2>BoostHub Management</h2>
            <p class="muted">Manage and review BoostHub tasks with category-focused filtering.</p>
        </div>
    </div>
    <form method="GET" class="admin-task-builder">
        <div class="admin-task-builder-grid">
            <div class="admin-form-field admin-form-field-compact">
                <label>Task Type</label>
                <select name="task_category" onchange="this.form.submit()">
                    <option value="all" <?php echo $selected_category === 'all' ? 'selected' : ''; ?>>All Types</option>
                    <?php foreach ($task_categories as $task_category_key => $task_category_label): ?>
                        <option value="<?php echo htmlspecialchars($task_category_key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected_category === $task_category_key ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($task_category_label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="admin-task-builder-actions">
            <span class="muted">Default view shows all seeded task types.</span>
            <button type="submit" class="btn btn-secondary">Apply Filter</button>
        </div>
    </form>
    <details class="admin-task-card admin-task-create-card">
        <summary class="btn btn-primary">Create New Task</summary>
        <form method="POST" action="" class="admin-task-builder">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action_type" value="save_task">
            <input type="hidden" name="task_id" value="0">
            <input type="hidden" name="task_group" value="boosthub">
            <div class="admin-task-builder-grid">
                <div class="admin-form-field">
                    <label>Task Name</label>
                    <input type="text" name="title" placeholder="Join CoinRex Telegram" required>
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
                    <input type="text" name="reward" placeholder="2.00" required>
                </div>
                <div class="admin-form-field">
                    <label>Repeat Gap</label>
                    <input type="text" name="cooldown_seconds" value="86400">
                </div>
                <div class="admin-form-field admin-form-field-span-2">
                    <label>Short Description</label>
                    <input type="text" name="description" placeholder="Ask the user to join, follow, or visit the campaign link." required>
                </div>
                <div class="admin-form-field admin-form-field-span-2">
                    <label>Destination Link</label>
                    <input type="text" name="task_link" placeholder="https://t.me/coinrexchannel" required>
                </div>
                <div class="admin-form-field admin-form-field-span-2">
                    <label>How To Complete</label>
                    <textarea name="completion_steps" rows="4" placeholder="1. Open the link&#10;2. Complete the join/follow/subscribe action&#10;3. Return and confirm completion" required></textarea>
                </div>
                <div class="admin-form-field admin-form-field-span-2">
                    <label>Proof Or Review Notes</label>
                    <textarea name="proof_notes" rows="3" placeholder="Explain what the user must submit or what the system will verify."></textarea>
                </div>
                <div class="admin-form-field">
                    <label>Button Label</label>
                    <input type="text" name="cta_label" placeholder="Open Telegram">
                </div>
                <div class="admin-form-field">
                    <label>Daily Limit</label>
                    <input type="text" name="daily_limit" value="1">
                </div>
            </div>
            <div class="admin-task-builder-actions">
                <label class="checkbox-inline"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                <button type="submit" class="btn btn-primary">Create BoostHub Task</button>
            </div>
        </form>
    </details>
    <div class="admin-task-card-list">
        <?php if (empty($task_rows)): ?>
            <div class="admin-task-card">
                <div class="admin-task-card-head">
                    <h3>No tasks found</h3>
                </div>
                <p class="muted">Try another task type filter.</p>
            </div>
        <?php endif; ?>
        <?php foreach ($task_rows as $task): ?>
            <form method="POST" action="" class="admin-task-card">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action_type" value="save_task">
                <input type="hidden" name="task_id" value="<?php echo (int) $task['id']; ?>">
                <input type="hidden" name="task_group" value="boosthub">
                <div class="admin-task-card-head">
                    <div>
                        <span class="status-pill status-under-review"><?php echo htmlspecialchars((string) ($task_categories[$task['task_category'] ?? 'custom'] ?? 'Custom Task'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <h3><?php echo htmlspecialchars((string) $task['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    </div>
                    <label class="checkbox-inline"><input type="checkbox" name="is_active" value="1" <?php echo !empty($task['is_active']) ? 'checked' : ''; ?>> Active</label>
                </div>
                <details class="admin-task-collapse">
                    <summary class="btn btn-secondary">Open Task Editor</summary>
                <div class="admin-task-builder-grid">
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
                    <div class="admin-form-field admin-form-field-span-2">
                        <label>Short Description</label>
                        <input type="text" name="description" value="<?php echo htmlspecialchars((string) ($task['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="admin-form-field admin-form-field-span-2">
                        <label>Destination Link</label>
                        <input type="text" name="task_link" value="<?php echo htmlspecialchars((string) ($task['task_link'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
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
                </div>
                </details>
                <div class="admin-task-card-foot">
                    <div class="admin-task-stat-line">
                        <span><?php echo number_format((int) ($task['completed_total'] ?? 0)); ?> completed</span>
                        <span><?php echo number_format((int) ($task['completed_today'] ?? 0)); ?> today</span>
                        <span><?php echo number_format((int) ($task['blocked_total'] ?? 0)); ?> blocked</span>
                    </div>
                    <button type="submit" class="btn btn-secondary">Save Changes</button>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
</div>

<div class="panel">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">BoostHub</span>
            <h2>Evidence Review Queue</h2>
            <p class="muted">Approve or reject submitted BoostHub evidence from one place.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
            <tr>
                <th>User</th>
                <th>Task</th>
                <th>Evidence</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($boosthub_review_rows)): ?>
                <?php foreach ($boosthub_review_rows as $review_row): ?>
                    <tr>
                        <td data-label="User">
                            <strong><?php echo htmlspecialchars((string) $review_row['username'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="muted"><?php echo htmlspecialchars((string) $review_row['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Task">
                            <span class="status-pill status-under-review"><?php echo htmlspecialchars((string) ($task_categories[$review_row['task_category'] ?? 'custom'] ?? 'Custom Task'), ENT_QUOTES, 'UTF-8'); ?></span><br>
                            <strong><?php echo htmlspecialchars((string) $review_row['title'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="muted">Reward: <?php echo number_format((float) ($review_row['reward'] ?? 0), 2); ?> $REX</span>
                        </td>
                        <td data-label="Evidence">
                            <code><?php echo htmlspecialchars((string) ($review_row['proof_data'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
                        </td>
                        <td data-label="Action">
                            <form method="POST" action="" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action_type" value="review_taskhub_submission">
                                <input type="hidden" name="log_id" value="<?php echo (int) $review_row['id']; ?>">
                                <input type="hidden" name="decision" value="approve">
                                <button type="submit" class="btn btn-primary">Approve</button>
                            </form>
                            <form method="POST" action="" class="inline-form" style="margin-top:8px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action_type" value="review_taskhub_submission">
                                <input type="hidden" name="log_id" value="<?php echo (int) $review_row['id']; ?>">
                                <input type="hidden" name="decision" value="reject">
                                <button type="submit" class="btn btn-danger">Reject</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No pending BoostHub evidence submissions.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
