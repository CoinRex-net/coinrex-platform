<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

$user = getCurrentUser();
$status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
if (!in_array($status, ['all', 'read', 'unread'], true)) {
    $status = 'all';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;

$db = getDBConnection();
markAllNotificationsAsRead('user', (int) ($user['id'] ?? 0), $db);

$paged = getNotificationsPaged('user', (int) ($user['id'] ?? 0), $page, $per_page, $status, $db);
$items = $paged['items'];
$total = (int) $paged['total'];
$total_pages = max(1, (int) ceil($total / $per_page));
$notification_counts = $paged['counts'] ?? ['all' => 0, 'read' => 0, 'unread' => 0];

$full_name = trim((string) ($user['full_name'] ?? ''));
if ($full_name === '') {
    $full_name = trim((string) ($user['username'] ?? 'User'));
}
$total_balance = number_format((float) ($user['rex_balance'] ?? 0), 2);

$extractRewardAmount = static function ($text) {
    if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*\$REX/i', (string) $text, $m)) {
        return (string) $m[1];
    }
    return null;
};

require_once __DIR__ . '/../includes/header.php';
?>
<style>
.notif-wrap{max-width:1100px;margin:28px auto;padding:0 16px}.notif-shell{background:linear-gradient(180deg,rgba(15,23,42,.94),rgba(15,23,42,.9));border:1px solid rgba(148,163,184,.16);border-radius:24px;padding:22px;box-shadow:0 30px 80px rgba(2,6,23,.34)}.notif-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap}.notif-copy h2{margin:0;color:#f8fafc;font-size:28px}.notif-copy p{margin:8px 0 0;color:#94a3b8;max-width:640px;line-height:1.6}.notif-actions{display:flex;gap:10px;flex-wrap:wrap}.notif-action-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:999px;border:1px solid rgba(59,130,246,.28);background:rgba(29,78,216,.12);color:#dbeafe;text-decoration:none;font-weight:700;cursor:pointer}.notif-action-btn:disabled{opacity:.5;cursor:not-allowed}.notif-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:18px}.notif-stat{background:rgba(2,6,23,.36);border:1px solid rgba(148,163,184,.14);border-radius:18px;padding:16px}.notif-stat strong{display:block;color:#f8fafc;font-size:24px}.notif-stat span{display:block;margin-top:6px;color:#94a3b8;font-size:13px}.notif-filter{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.notif-filter a{padding:10px 14px;border-radius:999px;border:1px solid rgba(148,163,184,.24);text-decoration:none;color:#cbd5e1;font-weight:600}.notif-filter a.active{background:#1d4ed8;color:#fff;border-color:#1d4ed8;box-shadow:0 12px 24px rgba(29,78,216,.22)}.notif-list{display:grid;gap:14px;margin-top:18px}.notif-item{background:rgba(2,6,23,.42);border:1px solid rgba(148,163,184,.18);border-radius:18px;padding:16px 18px;transition:.2s ease}.notif-item.unread{border-color:rgba(59,130,246,.46);box-shadow:0 0 0 1px rgba(59,130,246,.12) inset;background:linear-gradient(180deg,rgba(29,78,216,.08),rgba(2,6,23,.42))}.notif-item.is-fading{opacity:.7}.notif-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.notif-title-wrap{display:flex;align-items:center;gap:10px;min-width:0}.notif-title-wrap strong{color:#f8fafc;font-size:16px}.notif-state{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.notif-state.unread{background:rgba(29,78,216,.14);color:#bfdbfe}.notif-state.read{background:rgba(15,118,110,.16);color:#99f6e4}.notif-meta{color:#93c5fd;font-size:12px;white-space:nowrap}.notif-msg{margin-top:12px;color:#cbd5e1;line-height:1.7}.notif-msg strong{color:#fff}.notif-foot{margin-top:14px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.notif-foot-note{color:#94a3b8;font-size:12px}.notif-foot-actions{display:flex;gap:10px;flex-wrap:wrap}.notif-inline-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border-radius:12px;border:1px solid rgba(148,163,184,.18);background:rgba(255,255,255,.02);color:#e2e8f0;text-decoration:none;cursor:pointer}.notif-inline-btn.primary{border-color:rgba(59,130,246,.3);background:rgba(29,78,216,.12);color:#dbeafe}.notif-empty{padding:38px 18px;text-align:center;border:1px dashed rgba(148,163,184,.22);border-radius:18px;color:#94a3b8;background:rgba(2,6,23,.24)}.notif-empty i{font-size:28px;display:block;margin-bottom:12px;color:#64748b}.notif-pages{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}.notif-page-link{display:inline-flex;align-items:center;justify-content:center;min-width:40px;padding:10px 14px;border-radius:12px;text-decoration:none;color:#cbd5e1;border:1px solid rgba(148,163,184,.2);background:rgba(255,255,255,.02)}.notif-page-link.active{background:#1d4ed8;border-color:#1d4ed8;color:#fff}.notif-page-link:hover{border-color:rgba(59,130,246,.34)}@media (max-width:768px){.notif-shell{padding:18px}.notif-stats{grid-template-columns:1fr}.notif-top,.notif-foot{flex-direction:column;align-items:flex-start}.notif-meta{white-space:normal}}
</style>
<main class="dashboard-main">
    <div class="dashboard-container notif-wrap">
        <section class="notif-shell">
            <div class="notif-head">
                <div class="notif-copy">
                    <h2>Notifications Center</h2>
                    <p>Track rewards, account activity, and important updates in one place. Your unread count and notification state now update instantly without needing a full page reload.</p>
                </div>
                <div class="notif-actions">
                    <button type="button" class="notif-action-btn" id="notifMarkAllBtn" <?php echo (int) ($notification_counts['unread'] ?? 0) > 0 ? '' : 'disabled'; ?>><i class="fas fa-check-double"></i><span>Mark all as read</span></button>
                    <a href="<?php echo htmlspecialchars(BASE_URL . '/public/dashboard.php', ENT_QUOTES, 'UTF-8'); ?>" class="notif-action-btn"><i class="fas fa-arrow-left"></i><span>Back to RexHub</span></a>
                </div>
            </div>
            <div class="notif-stats">
                <div class="notif-stat"><strong id="notifAllCount"><?php echo (int) ($notification_counts['all'] ?? 0); ?></strong><span>Total notifications</span></div>
                <div class="notif-stat"><strong id="notifUnreadCount"><?php echo (int) ($notification_counts['unread'] ?? 0); ?></strong><span>Unread right now</span></div>
                <div class="notif-stat"><strong id="notifReadCount"><?php echo (int) ($notification_counts['read'] ?? 0); ?></strong><span>Already reviewed</span></div>
            </div>
            <div class="notif-filter">
                <div class="notif-filter">
                    <a class="<?php echo $status === 'all' ? 'active' : ''; ?>" href="?status=all">All</a>
                    <a class="<?php echo $status === 'unread' ? 'active' : ''; ?>" href="?status=unread">Unread</a>
                    <a class="<?php echo $status === 'read' ? 'active' : ''; ?>" href="?status=read">Read</a>
                </div>
            </div>
            <?php if (empty($items)): ?>
                <div class="notif-empty"><i class="fas fa-bell-slash"></i><strong>No notifications found.</strong><span>Once account updates and rewards arrive, they’ll appear here.</span></div>
            <?php else: ?>
                <div class="notif-list" id="notifList">
                    <?php foreach ($items as $n): ?>
                        <?php
                            $rawMessage = (string) ($n['message'] ?? '');
                            $rewardAmount = $extractRewardAmount($rawMessage);
                            $activityText = (string) ($n['title'] ?? 'General account activity');
                            $notificationHref = trim((string) ($n['action_url'] ?? '')) !== '' ? (string) $n['action_url'] : (string) (BASE_URL . '/public/notifications.php?status=all');
                            $notificationIsUnread = empty($n['is_read']);
                        ?>
                        <article class="notif-item <?php echo $notificationIsUnread ? 'unread' : ''; ?>" data-notification-id="<?php echo (int) ($n['id'] ?? 0); ?>" data-is-read="<?php echo $notificationIsUnread ? '0' : '1'; ?>">
                            <div class="notif-top">
                                <div class="notif-title-wrap">
                                    <strong><?php echo htmlspecialchars((string) $n['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span class="notif-state <?php echo $notificationIsUnread ? 'unread' : 'read'; ?>"><?php echo $notificationIsUnread ? 'Unread' : 'Read'; ?></span>
                                </div>
                                <small class="notif-meta"><?php echo htmlspecialchars((string) ($n['time_ago'] ?? $n['created_at']), ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                            <div class="notif-msg">
                                <strong><?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?>!</strong><br>
                                <?php if ($rewardAmount !== null): ?>
                                    <span><?php echo htmlspecialchars($rewardAmount, ENT_QUOTES, 'UTF-8'); ?> $REX successfully added to your balance.</span><br>
                                <?php else: ?>
                                    <span><?php echo htmlspecialchars($rawMessage, ENT_QUOTES, 'UTF-8'); ?></span><br>
                                <?php endif; ?>
                                <span>Total Balance: <?php echo htmlspecialchars($total_balance, ENT_QUOTES, 'UTF-8'); ?> $REX</span><br>
                                <span>User Activity: <?php echo htmlspecialchars($activityText, ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="notif-foot">
                                <span class="notif-foot-note">Notification ID #<?php echo (int) ($n['id'] ?? 0); ?></span>
                                <div class="notif-foot-actions">
                                    <a href="<?php echo htmlspecialchars($notificationHref, ENT_QUOTES, 'UTF-8'); ?>" class="notif-inline-btn primary"><i class="fas fa-arrow-up-right-from-square"></i><span>Open</span></a>
                                    <?php if ($notificationIsUnread): ?>
                                        <button type="button" class="notif-inline-btn js-mark-read"><i class="fas fa-check"></i><span>Mark read</span></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="notif-pages">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a class="notif-page-link <?php echo $i === $page ? 'active' : ''; ?>" href="?status=<?php echo urlencode($status); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </section>
    </div>
</main>
<script>
(function () {
    var list = document.getElementById('notifList');
    var markAllBtn = document.getElementById('notifMarkAllBtn');
    var allCountEl = document.getElementById('notifAllCount');
    var unreadCountEl = document.getElementById('notifUnreadCount');
    var readCountEl = document.getElementById('notifReadCount');

    function setCounts(counts) {
        var allCount = parseInt((counts && counts.all) || 0, 10);
        var unreadCount = parseInt((counts && counts.unread) || 0, 10);
        var readCount = parseInt((counts && counts.read) || 0, 10);

        if (allCountEl) allCountEl.textContent = String(allCount);
        if (unreadCountEl) unreadCountEl.textContent = String(unreadCount);
        if (readCountEl) readCountEl.textContent = String(readCount);
        if (markAllBtn) markAllBtn.disabled = unreadCount <= 0;
    }

    function markCardRead(card) {
        if (!card || card.getAttribute('data-is-read') === '1') {
            return;
        }

        card.setAttribute('data-is-read', '1');
        card.classList.remove('unread');

        var state = card.querySelector('.notif-state');
        if (state) {
            state.textContent = 'Read';
            state.classList.remove('unread');
            state.classList.add('read');
        }

        var btn = card.querySelector('.js-mark-read');
        if (btn && btn.parentNode) {
            btn.parentNode.removeChild(btn);
        }
    }

    function postForm(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    }

    if (list) {
        list.addEventListener('click', function (event) {
            var button = event.target.closest('.js-mark-read');
            if (!button) {
                return;
            }

            event.preventDefault();
            var card = button.closest('.notif-item');
            var notificationId = parseInt((card && card.getAttribute('data-notification-id')) || '0', 10);
            if (!notificationId) {
                return;
            }

            card.classList.add('is-fading');
            postForm('<?php echo BASE_URL; ?>/api/mark_notification_read.php', 'notification_id=' + encodeURIComponent(String(notificationId)))
                .then(function (data) {
                    if (!data || data.success !== true) {
                        throw new Error((data && data.message) || 'Failed to mark notification as read.');
                    }
                    markCardRead(card);
                    setCounts(data.counts || {});
                })
                .catch(function () {})
                .finally(function () {
                    card.classList.remove('is-fading');
                });
        });
    }

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            if (markAllBtn.disabled) {
                return;
            }

            markAllBtn.disabled = true;
            postForm('<?php echo BASE_URL; ?>/api/mark_all_notifications_read.php', '')
                .then(function (data) {
                    if (!data || data.success !== true) {
                        throw new Error((data && data.message) || 'Failed to mark all notifications as read.');
                    }
                    document.querySelectorAll('.notif-item[data-is-read="0"]').forEach(function (card) {
                        markCardRead(card);
                    });
                    setCounts(data.counts || {});
                })
                .catch(function () {
                    markAllBtn.disabled = false;
                });
        });
    }

    setCounts(<?php echo json_encode($notification_counts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>);
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
