<?php
$page_title = 'Messages';
$activePage = '';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $message_id = (int) ($_POST['message_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'mark_read' && $message_id > 0) {
        $stmt = $db->prepare("
            UPDATE messages
            SET status = 'read', read_at = NOW()
            WHERE id = ?
              AND (recipient_admin_id IS NULL OR recipient_admin_id = ?)
        ");
        $stmt->execute([$message_id, (int) $current_admin['id']]);
        $message = 'Message marked as read.';
    } elseif ($action === 'delete' && $message_id > 0) {
        $stmt = $db->prepare("
            DELETE FROM messages
            WHERE id = ?
              AND (recipient_admin_id IS NULL OR recipient_admin_id = ?)
        ");
        $stmt->execute([$message_id, (int) $current_admin['id']]);
        $message = 'Message deleted.';
    } elseif (isset($_POST['mark_all_read'])) {
        $stmt = $db->prepare("
            UPDATE messages
            SET status = 'read', read_at = NOW()
            WHERE status = 'unread'
              AND (recipient_admin_id IS NULL OR recipient_admin_id = ?)
        ");
        $stmt->execute([(int) $current_admin['id']]);
        $message = 'All unread messages marked as read.';
    } else {
        $message = 'Invalid action.';
        $message_type = 'error';
    }
}

$stmt = $db->prepare("
    SELECT id, title, body, status, created_at, read_at
    FROM messages
    WHERE recipient_admin_id IS NULL OR recipient_admin_id = ?
    ORDER BY created_at DESC
    LIMIT 200
");
$stmt->execute([(int) $current_admin['id']]);
$messages = $stmt->fetchAll();
?>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="panel">
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" name="mark_all_read" value="1" class="btn btn-secondary">Mark All Read</button>
    </form>
</div>

<div class="panel">
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
            <tr>
                <th>Title</th>
                <th>Message</th>
                <th>Status</th>
                <th>Created</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($messages as $item): ?>
                <?php
                $status = (string) ($item['status'] ?? 'unread');
                $status_class = $status === 'read' ? 'status-approved' : 'status-pending';
                $full_body = (string) ($item['body'] ?? '');
                $preview_body = mb_strlen($full_body) > 140 ? mb_substr($full_body, 0, 140) . '...' : $full_body;
                ?>
                <tr>
                    <td data-label="Title"><?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Message">
                        <div class="message-preview-text"><?php echo htmlspecialchars($preview_body, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php if (mb_strlen($full_body) > 140): ?>
                            <button
                                type="button"
                                class="btn btn-secondary message-readmore-btn"
                                data-message-title="<?php echo htmlspecialchars((string) ($item['title'] ?? 'Message'), ENT_QUOTES, 'UTF-8'); ?>"
                                data-message-body="<?php echo htmlspecialchars($full_body, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                                Read More
                            </button>
                        <?php endif; ?>
                    </td>
                    <td data-label="Status"><span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="Created"><?php echo htmlspecialchars((string) ($item['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Action">
                        <div class="message-actions">
                            <?php if ($status === 'unread'): ?>
                                <form method="POST" action="" class="inline-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="message_id" value="<?php echo (int) $item['id']; ?>">
                                    <button type="submit" name="action" value="mark_read" class="btn btn-primary">Mark Read</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">Read at <?php echo htmlspecialchars((string) ($item['read_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <form method="POST" action="" class="inline-form" onsubmit="return confirm('Delete this message?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="message_id" value="<?php echo (int) $item['id']; ?>">
                                <button type="submit" name="action" value="delete" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="message-modal" id="messageModal" aria-hidden="true">
    <div class="message-modal-card">
        <div class="message-modal-header">
            <h3 id="messageModalTitle">Message</h3>
            <button type="button" class="message-modal-close" id="messageModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="message-modal-body" id="messageModalBody"></div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('messageModal');
    var modalTitle = document.getElementById('messageModalTitle');
    var modalBody = document.getElementById('messageModalBody');
    var closeBtn = document.getElementById('messageModalClose');
    var readMoreButtons = document.querySelectorAll('.message-readmore-btn');

    if (!modal || !modalTitle || !modalBody || !closeBtn) {
        return;
    }

    function closeModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    readMoreButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var title = btn.getAttribute('data-message-title') || 'Message';
            var body = btn.getAttribute('data-message-body') || '';
            modalTitle.textContent = title;
            modalBody.textContent = body;
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
        });
    });

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
