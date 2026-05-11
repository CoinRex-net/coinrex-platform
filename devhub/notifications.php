<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/devhub/pages/auth/login.php');
    exit();
}

$user_id = (int) (getCurrentUserId() ?? 0);
$status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
if (!in_array($status, ['all', 'read', 'unread'], true)) {
    $status = 'all';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;

$paged = getNotificationsPaged('developer', $user_id, $page, $per_page, $status);
$items = $paged['items'] ?? [];
$total = (int) ($paged['total'] ?? 0);
$total_pages = max(1, (int) ceil($total / $per_page));

$db = getDevHubDB();
$user_stmt = $db->prepare("SELECT full_name, username FROM users WHERE id = ? LIMIT 1");
$user_stmt->execute([$user_id]);
$user_row = $user_stmt->fetch() ?: [];
$developer_name = trim((string) ($user_row['full_name'] ?? ''));
if ($developer_name === '') {
    $developer_name = trim((string) ($user_row['username'] ?? 'Developer'));
}

$page_title = 'Notifications';
$activePage = 'notifications';

require_once __DIR__ . '/includes/header.php';
?>

<style>
.devhub-notif-wrap{max-width:1120px;margin:14px auto 30px;padding:0 16px}.devhub-notif-card{background:#0f172a;border:1px solid rgba(148,163,184,.2);border-radius:16px;padding:16px}.devhub-notif-head{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.devhub-notif-filter{display:flex;gap:8px;flex-wrap:wrap}.devhub-notif-filter a{padding:8px 12px;border-radius:999px;border:1px solid rgba(148,163,184,.35);text-decoration:none;color:#cbd5e1}.devhub-notif-filter a.active{background:#1d4ed8;color:#fff;border-color:#1d4ed8}.devhub-notif-list{display:grid;gap:12px;margin-top:14px}.devhub-notif-item{background:#0b1220;border:1px solid rgba(148,163,184,.2);border-radius:14px;padding:14px}.devhub-notif-item.unread{border-color:rgba(59,130,246,.45);box-shadow:0 0 0 1px rgba(59,130,246,.15) inset}.devhub-notif-row{display:flex;justify-content:space-between;gap:8px}.devhub-notif-msg{margin-top:6px;color:#cbd5e1;line-height:1.45}.devhub-notif-foot{margin-top:10px;display:flex;gap:8px;flex-wrap:wrap}.devhub-notif-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 11px;border-radius:10px;text-decoration:none;border:1px solid rgba(59,130,246,.4);color:#dbeafe;background:rgba(29,78,216,.18);font-size:12px;cursor:pointer}.devhub-notif-pages{display:flex;flex-wrap:wrap;gap:7px;margin-top:14px}
.notif-modal{position:fixed;inset:0;display:none;z-index:1500}.notif-modal.active{display:block}.notif-modal-backdrop{position:absolute;inset:0;background:rgba(2,6,23,.58)}
.notif-modal-dialog{position:relative;max-width:860px;margin:4vh auto;background:#0b1220;border:1px solid rgba(148,163,184,.3);border-radius:14px;box-shadow:0 30px 60px rgba(2,6,23,.6);overflow:hidden}.notif-modal-head{display:flex;justify-content:space-between;gap:12px;padding:18px 22px;border-bottom:1px solid rgba(148,163,184,.25);background:#0f172a}.notif-modal-head h3{font-size:20px;line-height:1.3}.notif-modal-body{padding:20px 22px;color:#e2e8f0;max-height:76vh;overflow:auto;line-height:1.7;font-size:15px}.notif-modal-close{background:transparent;border:none;color:#cbd5e1;font-size:24px;cursor:pointer;line-height:1}.notif-preview{color:#94a3b8;line-height:1.45}
.notif-modal-body{scrollbar-width:thin;scrollbar-color:#3b82f6 #0f172a}.notif-modal-body::-webkit-scrollbar{width:10px}.notif-modal-body::-webkit-scrollbar-track{background:#0f172a;border-left:1px solid rgba(148,163,184,.2)}.notif-modal-body::-webkit-scrollbar-thumb{background:linear-gradient(180deg,#3b82f6,#1d4ed8);border-radius:999px;border:2px solid #0f172a}.notif-modal-body::-webkit-scrollbar-thumb:hover{background:linear-gradient(180deg,#60a5fa,#2563eb)}
.notif-modal-body{scrollbar-width:thin;scrollbar-color:#334155 #0f172a}.notif-modal-body::-webkit-scrollbar{width:10px}.notif-modal-body::-webkit-scrollbar-track{background:#0f172a;border-left:1px solid rgba(148,163,184,.15)}.notif-modal-body::-webkit-scrollbar-thumb{background:linear-gradient(180deg,#334155,#475569);border-radius:999px;border:2px solid #0f172a}.notif-modal-body::-webkit-scrollbar-thumb:hover{background:linear-gradient(180deg,#475569,#64748b)}
.mail-card{border:1px solid rgba(148,163,184,.3);border-radius:14px;overflow:hidden;background:#0f172a}.mail-card.type-success{border-top:5px solid #22c55e}.mail-card.type-error{border-top:5px solid #ef4444}.mail-card.type-warning{border-top:5px solid #f59e0b}.mail-card.type-info{border-top:5px solid #3b82f6}.mail-card-header{display:flex;align-items:center;gap:10px;padding:14px 16px;background:#111c31;border-bottom:1px solid rgba(148,163,184,.25)}.mail-card-header i{font-size:18px}.mail-card-header .success{color:#4ade80}.mail-card-header .error{color:#f87171}.mail-card-header .warning{color:#fbbf24}.mail-card-header .info{color:#60a5fa}.mail-card-header h3{color:#f8fafc}.mail-card-intro{padding:14px 16px 8px;color:#cbd5e1}.mail-divider{border:none;border-top:1px solid rgba(148,163,184,.25);margin:8px 16px}.mail-sections{padding:0 16px 14px;display:grid;gap:10px}.mail-section{border:1px solid rgba(148,163,184,.28);border-radius:10px;padding:10px 12px;background:#0b1220}.mail-section h4{margin:0 0 7px;font-size:14px;color:#f1f5f9;display:flex;gap:8px;align-items:center}.mail-section ul{margin:0;padding-left:18px}.mail-section li{margin:0 0 6px;color:#cbd5e1}.mail-cta-wrap{padding:2px 16px 14px}.mail-cta-btn{display:inline-flex;align-items:center;gap:8px;border:none;background:#2563eb;color:#fff;padding:10px 14px;border-radius:10px;font-weight:600;text-decoration:none}.mail-footer{margin:0 16px 16px;padding:10px 12px;border-radius:10px;background:#111827;border:1px dashed rgba(148,163,184,.5);color:#cbd5e1}
</style>

<div class="dashboard-wrapper devhub-notif-wrap">
    <section class="devhub-notif-card">
        <div class="devhub-notif-head">
            <div>
                <h2 style="margin:0;color:#f8fafc;">Notifications</h2>
                <p style="margin:4px 0 0;color:#94a3b8;">Dear <?php echo htmlspecialchars($developer_name, ENT_QUOTES, 'UTF-8'); ?>, here are your latest DevHub updates.</p>
            </div>
            <div class="devhub-notif-filter">
                <a class="<?php echo $status === 'all' ? 'active' : ''; ?>" href="?status=all">All</a>
                <a class="<?php echo $status === 'unread' ? 'active' : ''; ?>" href="?status=unread">Unread</a>
                <a class="<?php echo $status === 'read' ? 'active' : ''; ?>" href="?status=read">Read</a>
                <a href="#" id="devNotifMarkAll" class="devhub-notif-btn">Mark all as read</a>
            </div>
        </div>

        <?php if (empty($items)): ?>
            <p style="margin-top:14px;color:#94a3b8;">No notifications found.</p>
        <?php else: ?>
            <div class="devhub-notif-list">
                <?php foreach ($items as $n): ?>
                    <?php
                        $title_text = strtolower((string) ($n['title'] ?? ''));
                        $message_text = strtolower((string) ($n['message'] ?? ''));
                        $is_rejected_or_flagged = (strpos($title_text, 'rejected') !== false || strpos($title_text, 'flagged') !== false || strpos($message_text, 'rejected') !== false || strpos($message_text, 'flagged') !== false);
                    ?>
                    <article class="devhub-notif-item <?php echo empty($n['is_read']) ? 'unread' : ''; ?>" data-id="<?php echo (int) ($n['id'] ?? 0); ?>">
                        <div class="devhub-notif-row">
                            <strong style="color:#f8fafc;"><?php echo htmlspecialchars((string) ($n['title'] ?? 'Notification'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small style="color:#94a3b8;"><?php echo htmlspecialchars((string) ($n['time_ago'] ?? $n['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></small>
                        </div>
                        <div class="devhub-notif-msg notif-preview"><?php echo htmlspecialchars((string) mb_strimwidth((string) ($n['message'] ?? ''), 0, 160, '...'), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="devhub-notif-foot">
                            <button type="button" class="devhub-notif-btn devhub-open-notif-modal" data-title="<?php echo htmlspecialchars((string) ($n['title'] ?? 'Notification'), ENT_QUOTES, 'UTF-8'); ?>" data-body="<?php echo htmlspecialchars((string) ($n['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-action-url="<?php echo htmlspecialchars((string) ($n['action_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-book-open"></i> Read more</button>
                            <?php if ($is_rejected_or_flagged): ?>
                                <a class="devhub-notif-btn" href="<?php echo htmlspecialchars(BASE_URL . '/contact.php', ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-headset"></i> Contact Now</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="devhub-notif-pages">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a class="devhub-notif-btn" href="?status=<?php echo urlencode($status); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </section>
</div>

<div class="notif-modal" id="devNotifModal" aria-hidden="true">
    <div class="notif-modal-backdrop" id="devNotifModalBackdrop"></div>
    <div class="notif-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="devNotifModalTitle">
        <div class="notif-modal-head">
            <div>
                <span class="notif-doc-label">DevHub Notification</span>
                <h3 id="devNotifModalTitle" style="margin:4px 0 0;color:#f8fafc;">Notification</h3>
            </div>
            <button type="button" class="notif-modal-close" id="devNotifModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="notif-modal-body" id="devNotifModalBody"></div>
    </div>
</div>

<script>
(function () {
    var markAll = document.getElementById('devNotifMarkAll');

    var markAllInBackground = function (reloadAfter) {
        var body = new URLSearchParams();
        body.set('recipient_type', 'developer');
        return fetch('<?php echo BASE_URL; ?>/api/mark_all_notifications_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function () {
            if (reloadAfter) {
                window.location.reload();
            }
        });
    };

    markAllInBackground(false).catch(function () {});

    if (markAll) {
        markAll.addEventListener('click', function (e) {
            e.preventDefault();
            markAllInBackground(true).catch(function () {});
        });
    }

    var modal = document.getElementById('devNotifModal');
    var modalClose = document.getElementById('devNotifModalClose');
    var modalBackdrop = document.getElementById('devNotifModalBackdrop');
    var modalTitle = document.getElementById('devNotifModalTitle');
    var modalBody = document.getElementById('devNotifModalBody');

    if (modal && modalTitle && modalBody) {
        var escapeHtml = function (str) {
            return (str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        };

        var detectType = function (title) {
            var t = (title || '').toLowerCase();
            if (t.includes('approved')) return 'success';
            if (t.includes('rejected') || t.includes('not approved')) return 'error';
            if (t.includes('flagged') || t.includes('attention')) return 'warning';
            return 'info';
        };

        var parseMessage = function (rawBody) {
            var lines = (rawBody || '').split(/\r?\n/).map(function (line) { return line.trim(); });
            var introParts = [];
            var sections = [];
            var footer = [];
            var ctaLabel = '';
            var current = null;

            var headingMap = {
                '🔍': { key: 'reasons', title: 'Why this matters', icon: 'fa-search' },
                '💡': { key: 'improvements', title: 'How to improve', icon: 'fa-lightbulb' },
                '🚀': { key: 'next', title: 'Next step', icon: 'fa-rocket' },
                '📢': { key: 'pro', title: 'Growth Tip', icon: 'fa-bullhorn' },
                '🔒': { key: 'note', title: 'Important Note', icon: 'fa-lock' },
            };

            lines.forEach(function (line) {
                if (!line) return;
                if (line === '---') { current = null; return; }

                var cta = line.match(/^\[(.+)\]$/);
                if (cta) { ctaLabel = cta[1].trim(); return; }

                var emoji = line.substring(0, 2);
                if (headingMap[emoji]) {
                    var meta = headingMap[emoji];
                    current = { key: meta.key, title: meta.title, icon: meta.icon, items: [] };
                    sections.push(current);
                    return;
                }

                if (line.startsWith('•')) {
                    if (!current) {
                        current = { key: 'general', title: 'Details', icon: 'fa-list', items: [] };
                        sections.push(current);
                    }
                    current.items.push(line.replace(/^•\s*/, ''));
                    return;
                }

                if (current) {
                    current.items.push(line);
                } else if (/need help|need clarification/i.test(line)) {
                    footer.push(line);
                } else {
                    introParts.push(line);
                }
            });

            return { intro: introParts.join(' '), sections: sections, footer: footer.join(' '), ctaLabel: ctaLabel };
        };

        var renderMailCard = function (title, body, actionUrl) {
            var inferProjectName = function (titleText, sourceBody) {
                var bodyText = sourceBody || '';
                var bodyQuoted = bodyText.match(/project\s+\*\*"([^"]+)"\*\*/i) || bodyText.match(/project\s+"([^"]+)"/i);
                if (bodyQuoted && bodyQuoted[1]) return bodyQuoted[1].trim();

                var raw = titleText || '';
                var quoted = raw.match(/"([^"]+)"/);
                if (quoted && quoted[1]) return quoted[1].trim();

                var cleaned = raw
                    .replace(/^.*?(project\s+)/i, '')
                    .replace(/(approved|rejected|flagged|under review|featured|not approved).*$/i, '')
                    .replace(/[✅🎉⚠️❌🌟🔥]/g, '')
                    .trim();

                return cleaned || 'your project';
            };

            var injectDynamicValues = function (message, titleText, sourceBody) {
                var result = message || '';
                var projectName = inferProjectName(titleText, sourceBody);
                result = result.replace(/\{\{\s*project_name\s*\}\}/gi, projectName);
                result = result.replace(/\{\{\s*developer_name\s*\}\}/gi, '<?php echo addslashes(htmlspecialchars($developer_name, ENT_QUOTES, "UTF-8")); ?>');
                return result;
            };

            var normalizeProvidedBody = function (titleText, fallbackBody) {
                var t = (titleText || '').toLowerCase();
                if (t.includes('project not approved') || t.includes('project rejected')) {
                    return 'Your project **"{{project_name}}"** didn\'t pass our review this time.\n\nWe know that\'s not ideal — but the good news is you can fix it quickly.\n\n---\n\n🔍 What likely caused this:\n• Incomplete or unclear description\n• Missing social links (Twitter / Website)\n• Low-quality visuals or branding\n\n💡 How to improve your chances:\n• Write a clear, value-driven description\n• Add at least 2 active social links\n• Upload a clean logo + banner\n\n---\n\n🚀 Next step:\nUpdate your project and submit again for review after the cooldown Period.\n\n[Resubmit Project]\n\n---\n\n📢 Pro Insight:\nProjects with active communities and strong presentation get approved up to **3x faster**.\n\nNeed help? → Contact Support';
                }
                if (t.includes("you\'re live on devhub") || t.includes('project approved')) {
                    return 'Your project **"{{project_name}}"** has been successfully approved.\n\n---\n\n🚀 What you should do now:\n• Share your project link with your community\n• Drive engagement (clicks, reviews, activity)\n• Keep your project updated regularly\n\n---\n\n📈 Growth Tips:\n• Post on Twitter, Telegram, Discord\n• Encourage users to interact with your listing\n• Stay active to increase visibility\n\n[View Project]\n\n---\n\n🔥 Bonus:\nHigh-performing projects may get fast **featured placement** on CoinRex .';
                }
                if (t.includes('attention required') || t.includes('project flagged')) {
                    return 'Your project **"{{project_name}}"** has been flagged for review.\n\n---\n\n🔍 What this means:\nSome aspects of your project may not meet platform guidelines.\n\n---\n\n💡 What you should do:\n• Review your project content carefully\n• Ensure all details are accurate and complete\n• Remove any misleading or unclear information\n\n---\n\n🚀 Action required:\nUpdate your project to avoid further restrictions.\n\n[Edit Project]\n\n---\n\n🔒 Note:\nIgnoring flags may impact your visibility or approval status.\n\nNeed clarification? → Contact Support';
                }
                if (t.includes('verification not approved')) {
                    return 'Hi {{developer_name}},\nWe couldn\'t verify your developer profile this time.\n\nNo worries — this usually happens due to incomplete or mismatched verification signals.\n\n---\n\n🔍 Possible reasons:\n• X (Twitter) verification post not found or missing required #tags\n• Submitted handle does not have a Verified Blue Tick by X\n• Domain meta tag not detected or incorrectly placed\n• Website not accessible or not linked properly\n\n---\n\n💡 How to fix it:\n\n🔗 Social Verification (X):\n• Post using the required hashtags exactly as instructed\n• Make sure your X handle matches your project branding\n• Keep the post public until verification is complete\n\n🌐 Domain Verification:\n• Add the provided meta tag inside your website\'s <head> section\n• Ensure your domain is live and accessible\n• Double-check for typos or incorrect placement\n\n---\n\n🚀 Next step:\nUpdate your verification details and submit again.\n\n[Retry Verification]\n\n---\n\n📢 Pro Insight:\nDevelopers with verified social + domain presence gain significantly more trust and engagement on DevHub.\n\n🔒 We respect your privacy — no documents are required for verification.\n\nNeed help? → Contact Support';
                }
                return fallbackBody || '';
            };

            var originalBody = body || '';
            body = injectDynamicValues(normalizeProvidedBody(title, body), title, originalBody);
            var parsed = parseMessage(body || '');
            var type = detectType(title);
            var iconByType = { success: 'fa-circle-check', error: 'fa-circle-xmark', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' };

            if (!parsed.sections.length) {
                var t = (title || '').toLowerCase();
                if (t.includes('not approved') || t.includes('rejected')) {
                    parsed.sections = [
                        { title: 'Why this happened', icon: 'fa-search', items: ['Some required quality/compliance signals were missing or unclear.', 'Project/verification data may need stronger trust proof.'] },
                        { title: 'How to improve', icon: 'fa-lightbulb', items: ['Improve clarity of description and links.', 'Add stronger proof and keep profile/project details up to date.'] },
                        { title: 'Next step', icon: 'fa-rocket', items: ['Apply the fixes and resubmit after cooldown.'] }
                    ];
                    parsed.footer = parsed.footer || 'Need help? Contact support and we will guide you.';
                    parsed.ctaLabel = parsed.ctaLabel || 'Resubmit';
                } else if (t.includes('approved') || t.includes('live')) {
                    parsed.sections = [
                        { title: 'Next actions', icon: 'fa-rocket', items: ['Share your project link on official channels.', 'Encourage community engagement and reviews.', 'Keep updates frequent and consistent.'] },
                        { title: 'Growth tip', icon: 'fa-bullhorn', items: ['Projects with active communities gain visibility faster.'] }
                    ];
                    parsed.ctaLabel = parsed.ctaLabel || 'View Project';
                } else if (t.includes('flagged') || t.includes('attention')) {
                    parsed.sections = [
                        { title: 'What this means', icon: 'fa-search', items: ['Some content may require correction for compliance.'] },
                        { title: 'Action required', icon: 'fa-lightbulb', items: ['Review and update project details to avoid restrictions.'] },
                        { title: 'Next step', icon: 'fa-rocket', items: ['Edit your project and submit updated details.'] }
                    ];
                    parsed.footer = parsed.footer || 'Need clarification? Contact support.';
                    parsed.ctaLabel = parsed.ctaLabel || 'Edit Project';
                }
            }

            var sectionsHtml = parsed.sections.map(function (section) {
                var listItems = section.items.map(function (item) { return '<li>' + escapeHtml(item) + '</li>'; }).join('');
                return '<div class="mail-section"><h4><i class="fas ' + section.icon + '"></i>' + escapeHtml(section.title) + '</h4><ul>' + listItems + '</ul></div>';
            }).join('');

            var ctaHtml = '';
            if (parsed.ctaLabel && actionUrl) {
                ctaHtml = '<div class="mail-cta-wrap"><a class="mail-cta-btn" href="' + escapeHtml(actionUrl) + '"><i class="fas fa-arrow-up-right-from-square"></i>' + escapeHtml(parsed.ctaLabel) + '</a></div>';
            }

            var footerHtml = parsed.footer ? '<div class="mail-footer">' + escapeHtml(parsed.footer) + '</div>' : '';

            return '<article class="mail-card type-' + type + '">' +
                '<div class="mail-card-header"><i class="fas ' + iconByType[type] + ' ' + type + '"></i><h3 style="margin:0">' + escapeHtml(title || 'Notification') + '</h3></div>' +
                '<div class="mail-card-intro">' + escapeHtml(parsed.intro || 'Please review the update below.') + '</div>' +
                '<hr class="mail-divider" />' +
                '<div class="mail-sections">' + sectionsHtml + '</div>' +
                ctaHtml + footerHtml +
            '</article>';
        };

        var normalizeUrl = function (url) {
            var raw = (url || '').trim();
            if (!raw) return '';
            if (/^https?:\/\//i.test(raw)) return raw;
            if (raw.indexOf('<?php echo addslashes(BASE_URL); ?>') === 0) return raw;
            if (raw.charAt(0) === '/') return '<?php echo addslashes(BASE_URL); ?>' + raw;
            return '<?php echo addslashes(BASE_URL); ?>/' + raw.replace(/^\/+/, '');
        };

        var openModal = function (title, body, actionUrl) {
            modalTitle.textContent = title || 'Notification';
            modalBody.innerHTML = renderMailCard(title || 'Notification', body || '', normalizeUrl(actionUrl));
            modal.classList.add('active');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        };

        var closeModal = function () {
            modal.classList.remove('active');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        document.querySelectorAll('.devhub-open-notif-modal').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(btn.getAttribute('data-title') || 'Notification', btn.getAttribute('data-body') || '', btn.getAttribute('data-action-url') || '');
            });
        });

        if (modalClose) modalClose.addEventListener('click', closeModal);
        if (modalBackdrop) modalBackdrop.addEventListener('click', closeModal);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
