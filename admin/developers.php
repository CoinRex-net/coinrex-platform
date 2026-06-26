<?php
$page_title = 'Developer Verification';
$activePage = 'developers';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();
$message = '';
$message_type = 'success';
$status_filter = trim((string) ($_GET['status'] ?? 'pending'));
$valid_filters = ['pending', 'approved', 'rejected', 'change_requested', 'all'];
if (!in_array($status_filter, $valid_filters, true)) {
    $status_filter = 'pending';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));

    $verification_id = (int) ($_POST['verification_id'] ?? 0);
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($verification_id > 0 && $user_id > 0 && in_array($action, ['approve', 'reject', 'change_requested'], true)) {
        $new_status = $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'change_requested');

        $stmt = $db->prepare("UPDATE developer_verification SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $verification_id]);

        if (tableHasColumn('users', 'has_verified_badge')) {
            $badge_stmt = $db->prepare("UPDATE users SET has_verified_badge = ? WHERE id = ?");
            $badge_stmt->execute([$new_status === 'approved' ? 1 : 0, $user_id]);
        }

        logAdminActivity(
            (int) $current_admin['id'],
            'developer_verification_' . $new_status,
            'developer_verification',
            (string) $verification_id,
            json_encode(['user_id' => $user_id], JSON_UNESCAPED_UNICODE)
        );

        $name_stmt = $db->prepare("SELECT full_name, username FROM users WHERE id = ? LIMIT 1");
        $name_stmt->execute([$user_id]);
        $name_row = $name_stmt->fetch() ?: [];
        $developer_name = trim((string) ($name_row['full_name'] ?? ''));
        if ($developer_name === '') {
            $developer_name = trim((string) ($name_row['username'] ?? 'Developer'));
        }

        $template_key = 'developer.verification.' . $new_status;
        createTemplatedNotification($template_key, 'developer', $user_id, [
            'developer_name' => $developer_name !== '' ? $developer_name : 'Developer',
        ], [
            'actor_type' => 'admin',
            'actor_id' => (int) $current_admin['id'],
            'meta' => ['verification_id' => $verification_id, 'status' => $new_status],
        ], $db);

        $message = 'Developer verification status updated.';
    } else {
        $message = 'Invalid developer verification action.';
        $message_type = 'error';
    }
}

$where = '';
$params = [];
if ($status_filter !== 'all') {
    $where = "WHERE dv.status = ?";
    $params[] = $status_filter;
}

$stmt = $db->prepare("
    SELECT
        dv.id, dv.user_id, dv.status, dv.verification_post_url, dv.verification_url, dv.verification_code, dv.updated_at, dv.created_at,
        u.username, u.email, u.full_name
    FROM developer_verification dv
    LEFT JOIN users u ON u.id = dv.user_id
    {$where}
    ORDER BY dv.updated_at DESC, dv.id DESC
    LIMIT 200
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Calculate stats
$stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'change_requested' => 0];
foreach ($rows as $r) {
    $s = (string) ($r['status'] ?? 'pending');
    if (isset($stats[$s])) $stats[$s]++;
}
?>
<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-user-shield"></i></div>
            <div class="dashboard-header-text">
                <h1>Developer Verification</h1>
                <p>Verify developer identities through post proof and website meta code</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-database"></i> <?php echo number_format(count($rows)); ?> loaded
        </div>
    </div>

    <?php if ($message !== ''): ?>
        <div data-toast data-toast-message="<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>" data-toast-type="<?php echo $message_type === 'error' ? 'error' : 'success'; ?>" style="display:none;"></div>
    <?php endif; ?>

    <!-- ====== SECTION 1: OVERVIEW METRICS ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-chart-bar"></i> Overview <span class="divider-sub">Verification queue metrics</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-list"></i> Queue</span>
                <h3>Developer Verification Queue</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Review developer applications by checking their post proof and website meta code before approving their verified badge.</p>
            </div>
        </div>
        <div class="dashboard-metric-grid">
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-gold"><i class="fas fa-clock"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) ($stats['pending'] ?? 0)); ?></span>
                <span class="metric-label">Pending Queue</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-green"><i class="fas fa-check-circle"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) ($stats['approved'] ?? 0)); ?></span>
                <span class="metric-label">Approved</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-orange"><i class="fas fa-pen-to-square"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) ($stats['change_requested'] ?? 0)); ?></span>
                <span class="metric-label">Change Requested</span>
            </div>
            <div class="dashboard-metric-card">
                <div class="metric-top"><div class="metric-icon is-red"><i class="fas fa-times-circle"></i></div></div>
                <span class="metric-value"><?php echo number_format((int) ($stats['rejected'] ?? 0)); ?></span>
                <span class="metric-label">Rejected</span>
            </div>
        </div>
    </div>

    <!-- ====== SECTION 2: FILTER BAR ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-filter"></i> Filter <span class="divider-sub">Narrow the verification queue</span></h2>
    </div>

    <div class="dashboard-panel" style="margin-bottom:16px;">
        <div class="dashboard-filter-bar">
            <div>
                <h3 style="margin:0 0 4px;font-size:15px;font-weight:700;color:#f1f5f9;">Filter Developers</h3>
                <p class="muted" style="margin:0;font-size:12px;">Filter by verification status to work the queue efficiently.</p>
            </div>
            <form method="GET" action="" class="dashboard-filter-form">
                <select name="status">
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="change_requested" <?php echo $status_filter === 'change_requested' ? 'selected' : ''; ?>>Change Requested</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
                <button type="submit" class="btn btn-secondary">Apply Filter</button>
            </form>
        </div>
    </div>

    <!-- ====== SECTION 3: DEVELOPER TABLE ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-list"></i> Verification Queue <span class="divider-sub">All developers matching current filter</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Developer</th>
                    <th>Status</th>
                    <th>Post Proof</th>
                    <th>Website / Meta</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $status = (string) ($row['status'] ?? 'pending');
                    $status_class = 'is-pending';
                    if ($status === 'approved') $status_class = 'is-active';
                    elseif ($status === 'rejected') $status_class = 'is-suspended';
                    elseif ($status === 'change_requested') $status_class = 'is-pro';
                    $developer_name = trim((string) ($row['full_name'] ?? ''));
                    if ($developer_name === '') $developer_name = trim((string) ($row['username'] ?? 'Unknown'));
                ?>
                    <tr>
                        <td data-label="ID"><?php echo (int) $row['id']; ?></td>
                        <td data-label="Developer">
                            <strong><?php echo htmlspecialchars($developer_name, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <span class="muted"><?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Status">
                            <span class="dashboard-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td data-label="Post Proof">
                            <?php if (!empty($row['verification_post_url'])): ?>
                                <a href="<?php echo htmlspecialchars((string) $row['verification_post_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="modal-link"><i class="fas fa-external-link-alt"></i> Open Post</a>
                            <?php else: ?>
                                <span class="muted">Not provided</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Website / Meta">
                            <?php if (!empty($row['verification_url'])): ?>
                                <a href="<?php echo htmlspecialchars((string) $row['verification_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="modal-link"><i class="fas fa-globe"></i> Open Site</a><br>
                            <?php endif; ?>
                            <?php if (!empty($row['verification_code'])): ?>
                                <code style="font-size:11px;color:#94a3b8;word-break:break-all;"><?php echo htmlspecialchars(substr((string) $row['verification_code'], 0, 140), ENT_QUOTES, 'UTF-8'); ?></code>
                            <?php else: ?>
                                <span class="muted">No meta code</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Actions">
                            <div class="action-stack">
                                <button type="button" class="btn btn-secondary action-view-btn dev-view-btn"
                                    data-dev-id="<?php echo (int) $row['id']; ?>"
                                    data-user-id="<?php echo (int) $row['user_id']; ?>"
                                    data-name="<?php echo htmlspecialchars($developer_name, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-email="<?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-status="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-post-url="<?php echo htmlspecialchars((string) ($row['verification_post_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-site-url="<?php echo htmlspecialchars((string) ($row['verification_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-code="<?php echo htmlspecialchars((string) ($row['verification_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                ><i class="fas fa-eye"></i> View</button>
                                <form method="POST" action="" class="action-stack-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="verification_id" value="<?php echo (int) $row['id']; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $row['user_id']; ?>">
                                    <button type="submit" class="btn btn-primary action-stack-btn" name="action" value="approve"><i class="fas fa-check-circle"></i> Approve</button>
                                    <button type="submit" class="btn btn-danger action-stack-btn" name="action" value="reject"><i class="fas fa-times-circle"></i> Reject</button>
                                    <button type="submit" class="btn btn-secondary action-stack-btn" name="action" value="change_requested"><i class="fas fa-pen"></i> Request Change</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.dashboard-container -->

<!-- ====== DEVELOPER DETAIL MODAL ====== -->
<div class="dashboard-modal" id="devDetailModal">
    <div class="dashboard-modal-card">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-user-shield"></i> Developer Details</span>
                <h3 id="modalDevTitle">Developer Verification</h3>
            </div>
            <button type="button" class="dashboard-modal-close" id="closeDevModal">&times;</button>
        </div>
        <div class="dashboard-modal-body">

            <!-- ====== HERO CARD ====== -->
            <div class="modal-hero-card">
                <div class="modal-hero-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="modal-hero-info">
                    <h2 id="modalDevName">Developer</h2>
                    <span class="modal-hero-slug" id="modalDevEmail">email</span>
                    <div class="modal-hero-badges">
                        <span class="modal-hero-badge" id="modalDevStatusBadge">Pending</span>
                    </div>
                </div>
                <div class="modal-hero-score">
                    <div class="modal-hero-score-value" id="modalDevId">#0</div>
                    <div class="modal-hero-score-label">Verification ID</div>
                </div>
            </div>

            <!-- ====== PROOF PACK CARD ====== -->
            <div class="modal-grid-2col">
                <div class="modal-info-card">
                    <div class="modal-info-card-header">
                        <i class="fas fa-shield"></i> Proof Pack
                    </div>
                    <div class="modal-info-card-body">
                        <div class="modal-info-row">
                            <span class="modal-info-label">Post Proof</span>
                            <span class="modal-info-value" id="modalDevPostUrl">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Website</span>
                            <span class="modal-info-value" id="modalDevSiteUrl">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Verification Code</span>
                            <span class="modal-info-value modal-contract is-copyable" id="modalDevCode" title="Click to copy verification code">—</span>
                        </div>
                        <div class="modal-info-row" style="border-top:1px solid rgba(148,163,184,0.1);padding-top:10px;margin-top:4px;">
                            <span class="modal-info-label">Auto-Verify</span>
                            <span class="modal-info-value" style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;">
                                <button type="button" class="btn btn-secondary" id="modalDevVerifyMetaBtn" style="font-size:12px;padding:6px 12px;"><i class="fas fa-search"></i> Verify Meta Tag</button>
                                <span id="modalDevMetaResult" style="font-size:12px;font-weight:600;display:none;"></span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Developer Context Card -->
                <div class="modal-info-card">
                    <div class="modal-info-card-header">
                        <i class="fas fa-user"></i> Developer Context
                    </div>
                    <div class="modal-info-card-body">
                        <div class="modal-info-row">
                            <span class="modal-info-label">Name</span>
                            <span class="modal-info-value" id="modalDevName2">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Email</span>
                            <span class="modal-info-value" id="modalDevEmail2">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Status</span>
                            <span class="modal-info-value" id="modalDevStatus2">—</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ====== MODERATION ACTION FORM ====== -->
            <div class="modal-info-card modal-info-card-full" style="border-color:rgba(212,175,55,0.15);background:linear-gradient(135deg,rgba(212,175,55,0.04),rgba(245,215,110,0.02));">
                <div class="modal-info-card-header">
                    <i class="fas fa-gavel"></i> Moderation Action
                </div>
                <div class="modal-info-card-body">
                    <form method="POST" action="" id="modalDevForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="verification_id" id="modalFormVerificationId" value="">
                        <input type="hidden" name="user_id" id="modalFormUserId" value="">
                        <input type="hidden" name="action" id="modalFormDevAction" value="">
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <button type="button" class="btn btn-primary" id="modalDevApproveBtn"><i class="fas fa-check-circle"></i> Approve</button>
                            <button type="button" class="btn btn-danger" id="modalDevRejectBtn"><i class="fas fa-times-circle"></i> Reject</button>
                            <button type="button" class="btn btn-secondary" id="modalDevChangeBtn"><i class="fas fa-pen"></i> Request Change</button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const modal = document.getElementById('devDetailModal');
    const closeBtn = document.getElementById('closeDevModal');
    const viewBtns = document.querySelectorAll('.dev-view-btn');

    function openModal(data) {
        document.getElementById('modalDevTitle').textContent = 'Developer Verification #' + (data.devId || '');
        document.getElementById('modalDevName').textContent = data.name || 'Developer';
        document.getElementById('modalDevEmail').textContent = data.email || '';
        document.getElementById('modalDevId').textContent = '#' + (data.devId || '0');

        var statusBadge = document.getElementById('modalDevStatusBadge');
        statusBadge.textContent = data.status || 'Pending';
        statusBadge.className = 'modal-hero-badge';
        if (data.status === 'approved') statusBadge.classList.add('is-approved');
        else if (data.status === 'rejected') statusBadge.classList.add('is-rejected');
        else statusBadge.classList.add('is-feature');

        // Proof pack
        var postEl = document.getElementById('modalDevPostUrl');
        if (data.postUrl) {
            postEl.innerHTML = '<a href="' + data.postUrl + '" target="_blank" rel="noopener noreferrer" class="modal-link"><i class="fas fa-external-link-alt"></i> Open Post</a>';
        } else {
            postEl.textContent = 'Not provided';
        }

        var siteEl = document.getElementById('modalDevSiteUrl');
        if (data.siteUrl) {
            siteEl.innerHTML = '<a href="' + data.siteUrl + '" target="_blank" rel="noopener noreferrer" class="modal-link"><i class="fas fa-globe"></i> Open Site</a>';
        } else {
            siteEl.textContent = 'Not provided';
        }

        var codeEl = document.getElementById('modalDevCode');
        codeEl.textContent = data.code || '—';
        codeEl.className = 'modal-info-value modal-contract is-copyable';
        codeEl.title = 'Click to copy verification code';

        // Developer context
        document.getElementById('modalDevName2').textContent = data.name || '—';
        document.getElementById('modalDevEmail2').textContent = data.email || '—';
        document.getElementById('modalDevStatus2').textContent = data.status || '—';

        // Form
        document.getElementById('modalFormVerificationId').value = data.devId || '';
        document.getElementById('modalFormUserId').value = data.userId || '';

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    viewBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            openModal({
                devId: btn.getAttribute('data-dev-id'),
                userId: btn.getAttribute('data-user-id'),
                name: btn.getAttribute('data-name'),
                email: btn.getAttribute('data-email'),
                status: btn.getAttribute('data-status'),
                postUrl: btn.getAttribute('data-post-url'),
                siteUrl: btn.getAttribute('data-site-url'),
                code: btn.getAttribute('data-code'),
            });
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) closeModal();
    });

    // Click-to-copy for verification code
    document.addEventListener('click', function(e) {
        var target = e.target.closest('.modal-contract.is-copyable');
        if (!target) return;
        var text = target.textContent.trim();
        if (!text || text === '—') return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                target.classList.add('is-copied');
                setTimeout(function() { target.classList.remove('is-copied'); }, 2000);
            }).catch(function() { fallbackCopy(target, text); });
        } else {
            fallbackCopy(target, text);
        }
    });

    function fallbackCopy(el, text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
            document.execCommand('copy');
            el.classList.add('is-copied');
            setTimeout(function() { el.classList.remove('is-copied'); }, 2000);
        } catch (e) {}
        document.body.removeChild(ta);
    }

    // Verify Meta Tag AJAX
    var verifyBtn = document.getElementById('modalDevVerifyMetaBtn');
    var metaResult = document.getElementById('modalDevMetaResult');

    verifyBtn.addEventListener('click', function() {
        var siteUrl = document.getElementById('modalDevSiteUrl').textContent.trim();
        var code = document.getElementById('modalDevCode').textContent.trim();

        // If siteUrl is a link, extract the URL
        var linkEl = document.getElementById('modalDevSiteUrl').querySelector('a');
        if (linkEl) {
            siteUrl = linkEl.getAttribute('href');
        }

        if (!siteUrl || siteUrl === 'Not provided' || siteUrl === '—') {
            metaResult.style.display = 'inline';
            metaResult.className = 'meta-verify-result is-error';
            metaResult.innerHTML = '<i class="fas fa-exclamation-triangle"></i> No website URL provided';
            return;
        }

        if (!code || code === '—') {
            metaResult.style.display = 'inline';
            metaResult.className = 'meta-verify-result is-error';
            metaResult.innerHTML = '<i class="fas fa-exclamation-triangle"></i> No meta code provided';
            return;
        }

        // Show loading
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
        metaResult.style.display = 'inline';
        metaResult.className = 'meta-verify-result';
        metaResult.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching website...';

        var formData = new FormData();
        formData.append('website_url', siteUrl);
        formData.append('meta_code', code);

        fetch('<?php echo BASE_URL; ?>/api/verify_developer_meta.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<i class="fas fa-search"></i> Verify Meta Tag';

            if (data.success && data.found) {
                metaResult.className = 'meta-verify-result is-success';
                metaResult.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
            } else if (data.success && !data.found) {
                metaResult.className = 'meta-verify-result is-error';
                metaResult.innerHTML = '<i class="fas fa-times-circle"></i> ' + data.message;
            } else {
                metaResult.className = 'meta-verify-result is-error';
                metaResult.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.message || 'Verification failed');
            }
        })
        .catch(function(err) {
            verifyBtn.disabled = false;
            verifyBtn.innerHTML = '<i class="fas fa-search"></i> Verify Meta Tag';
            metaResult.className = 'meta-verify-result is-error';
            metaResult.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Network error: ' + err.message;
        });
    });

    // Moderation action buttons
    document.getElementById('modalDevApproveBtn').addEventListener('click', function() {
        document.getElementById('modalFormDevAction').value = 'approve';
        document.getElementById('modalDevForm').submit();
    });
    document.getElementById('modalDevRejectBtn').addEventListener('click', function() {
        document.getElementById('modalFormDevAction').value = 'reject';
        document.getElementById('modalDevForm').submit();
    });
    document.getElementById('modalDevChangeBtn').addEventListener('click', function() {
        document.getElementById('modalFormDevAction').value = 'change_requested';
        document.getElementById('modalDevForm').submit();
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
