<?php
$page_title = 'BoostHub Evidence Log';
$activePage = 'boosthub-evidence';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';
require_once __DIR__ . '/includes/pagination.php';

$db = getDBConnection();
$task_categories = adminRewardTaskCategories();
?>
<?php paginationRenderStyles(); ?>
<style>
/* ── Evidence Log Page ── */
.boosthub-evidence-log {
    display: grid;
    gap: 20px;
}

/* ── Filters ── */
.evidence-filters {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    padding: 16px 20px;
    background: rgba(15, 23, 42, 0.6);
    border: 1px solid rgba(148, 163, 184, 0.08);
    border-radius: 14px;
}
.evidence-filters label {
    color: #94a3b8;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}
.evidence-filters select {
    padding: 8px 14px;
    border-radius: 10px;
    border: 1px solid rgba(148, 163, 184, 0.15);
    background: rgba(15, 23, 42, 0.8);
    color: #e2e8f0;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    outline: none;
    min-width: 160px;
}
.evidence-filters select:focus {
    border-color: rgba(29, 78, 216, 0.4);
    box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
}
.evidence-filters .btn {
    margin-left: auto;
}

/* ── Status badges ── */
.evidence-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
}
.evidence-status.is-submitted {
    background: rgba(212, 175, 55, 0.12);
    border: 1px solid rgba(212, 175, 55, 0.25);
    color: #facc15;
}
.evidence-status.is-completed {
    background: rgba(34, 197, 94, 0.12);
    border: 1px solid rgba(34, 197, 94, 0.25);
    color: #22c55e;
}
.evidence-status.is-failed {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: #ef4444;
}
.evidence-status.is-blocked {
    background: rgba(148, 163, 184, 0.12);
    border: 1px solid rgba(148, 163, 184, 0.25);
    color: #94a3b8;
}

/* ── Evidence detail popover ── */
.evidence-detail-btn.btn {
    cursor: pointer;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 6px 12px;
    text-decoration: none;
}
.evidence-detail-btn.btn:hover {
    color: #60a5fa;
}

/* ── Evidence detail modal ── */
.evidence-detail-modal .dashboard-modal-card {
    max-width: 600px;
}
.evidence-detail-body {
    display: grid;
    gap: 14px;
}
.evidence-detail-field {
    display: grid;
    gap: 4px;
}
.evidence-detail-field label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.evidence-detail-field .value {
    color: #e2e8f0;
    font-size: 0.88rem;
    line-height: 1.5;
    word-break: break-word;
}
.evidence-detail-field .value code {
    display: block;
    padding: 10px 14px;
    background: rgba(2, 6, 23, 0.5);
    border-radius: 10px;
    border: 1px solid rgba(148, 163, 184, 0.1);
    font-size: 0.82rem;
    color: #cbd5e1;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    max-height: 200px;
    overflow-y: auto;
}
.evidence-detail-field .value img {
    max-height: 200px;
    max-width: 100%;
    border-radius: 10px;
    border: 1px solid rgba(148, 163, 184, 0.12);
    object-fit: cover;
    cursor: pointer;
}

/* ── Evidence copy button ── */
.evidence-copy-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 8px;
    border: 1px solid rgba(148, 163, 184, 0.2);
    background: rgba(148, 163, 184, 0.08);
    color: #94a3b8;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s ease;
    font-family: inherit;
    margin-top: 6px;
}
.evidence-copy-btn:hover {
    background: rgba(148, 163, 184, 0.15);
    color: #e2e8f0;
    border-color: rgba(148, 163, 184, 0.3);
}
.evidence-copy-btn.is-copied {
    background: rgba(34, 197, 94, 0.15);
    color: #22c55e;
    border-color: rgba(34, 197, 94, 0.3);
}
.evidence-copy-btn i {
    font-size: 0.82rem;
}

/* ── Action buttons in table ── */
.evidence-action-btns {
    display: flex;
    align-items: center;
    gap: 6px;
}
.evidence-action-btns .btn {
    font-size: 0.78rem;
    padding: 6px 12px;
    white-space: nowrap;
}
.evidence-action-btns .btn i {
    font-size: 0.82rem;
}

/* ── Responsive ── */
@media (max-width: 768px) {
    .evidence-filters {
        flex-direction: column;
        align-items: stretch;
    }
    .evidence-filters select {
        width: 100%;
        min-width: 0;
    }
    .evidence-filters .btn {
        margin-left: 0;
        width: 100%;
        justify-content: center;
    }

    /* Table → card layout (matching boosthub.php style) */
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

    .evidence-action-btns {
        width: 100%;
    }
    .evidence-action-btns .btn {
        flex: 1;
        justify-content: center;
    }

    /* ── Evidence detail modal responsive ── */
    .evidence-detail-modal {
        padding: 10px;
        align-items: center;
    }
    .evidence-detail-modal .dashboard-modal-card {
        max-width: 100%;
        width: 100%;
        margin: 0;
        border-radius: 16px;
        max-height: 90vh;
    }
    .evidence-detail-body {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    .evidence-detail-field {
        gap: 2px;
    }
    .evidence-detail-field label {
        font-size: 0.7rem;
    }
    .evidence-detail-field .value {
        font-size: 0.82rem;
    }
    .evidence-detail-field .value code {
        max-height: 120px;
        font-size: 0.75rem;
        padding: 8px 10px;
    }
    .evidence-detail-field .value img {
        max-height: 120px;
    }
    /* Full-width fields in modal */
    .evidence-detail-field.is-fullwidth {
        grid-column: 1 / -1;
    }
    .dashboard-modal-footer {
        flex-direction: column;
        gap: 8px;
    }
    .dashboard-modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
    .dashboard-modal-header {
        padding: 14px 16px 10px;
    }
    .dashboard-modal-header h3 {
        font-size: 1rem;
        margin-top: 4px;
    }
    .dashboard-modal-body {
        padding: 12px 16px;
    }

    /* ── Pagination responsive ── */
    #evidencePagination {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 6px;
    }
    #evidencePagination .pagination-link {
        font-size: 0.78rem;
        padding: 6px 10px;
    }
    #evidencePagination .pagination-prev,
    #evidencePagination .pagination-next {
        font-size: 0.78rem;
        padding: 6px 10px;
    }
    #evidencePagination .pagination-ellipsis {
        font-size: 0.78rem;
        padding: 6px 4px;
    }
}

@media (max-width: 480px) {
    .dashboard-table tbody tr td::before {
        width: 70px;
        font-size: 0.7rem;
    }
    .dashboard-table tbody tr td {
        font-size: 0.82rem;
    }

    /* ── Evidence detail modal on very small screens ── */
    .evidence-detail-modal .dashboard-modal-card {
        max-width: 98vw;
        border-radius: 12px;
    }
    .evidence-detail-field .value code {
        max-height: 120px;
        font-size: 0.75rem;
        padding: 8px 10px;
    }
    .evidence-detail-field .value img {
        max-height: 120px;
    }
    .dashboard-modal-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .dashboard-modal-header h3 {
        font-size: 1rem;
    }

    /* ── Pagination on very small screens ── */
    #evidencePagination .pagination-link {
        font-size: 0.72rem;
        padding: 5px 8px;
    }
    #evidencePagination .pagination-prev,
    #evidencePagination .pagination-next {
        font-size: 0.72rem;
        padding: 5px 8px;
    }
}
</style>

<div class="dashboard-header">
    <div class="dashboard-header-left">
        <div class="dashboard-header-icon"><i class="fas fa-clipboard-list"></i></div>
        <div class="dashboard-header-text">
            <h1>BoostHub Evidence Log</h1>
            <p>View all submitted evidence with their review status.</p>
        </div>
    </div>
    <span class="dashboard-header-badge"><i class="fas fa-circle"></i> Evidence</span>
</div>

<div class="boosthub-evidence-log">
    <!-- Filters -->
    <div class="evidence-filters">
        <label for="evidenceFilterCategory"><i class="fas fa-tag"></i> Task Type</label>
        <select id="evidenceFilterCategory">
            <option value="all">All Types</option>
            <?php foreach ($task_categories as $key => $label): ?>
                <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="evidenceFilterStatus"><i class="fas fa-flag"></i> Status</label>
        <select id="evidenceFilterStatus">
            <option value="all">All Statuses</option>
            <option value="submitted">Pending Review</option>
            <option value="completed">Approved</option>
            <option value="failed">Rejected / Returned</option>
            <option value="blocked">Blocked</option>
        </select>

        <button type="button" class="btn btn-primary" id="evidenceRefreshBtn">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>

    <!-- Table -->
    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <h3><i class="fas fa-inbox"></i> Submissions <span id="evidenceTotalCount" class="muted" style="font-weight:400;font-size:0.82rem;"></span></h3>
        </div>
        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody id="evidenceTableBody">
                    <tr>
                        <td colspan="5" class="dashboard-empty">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div id="evidencePagination" class="pagination-bar" style="margin-top:16px;"></div>
    </div>
</div>

<!-- Evidence Detail Modal -->
<div class="dashboard-modal evidence-detail-modal" id="evidenceDetailModal">
    <div class="dashboard-modal-card">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-file-lines"></i> Evidence Details</span>
                <h3>Submission #<span id="evidenceDetailId"></span></h3>
            </div>
        </div>
        <div class="dashboard-modal-body">
            <div class="evidence-detail-body" id="evidenceDetailBody">
                <!-- Populated by JS -->
            </div>
        </div>
        <div class="dashboard-modal-footer" style="padding:14px 22px;display:flex;gap:8px;justify-content:flex-end;border-top:1px solid rgba(148,163,184,0.08);">
            <button type="button" class="btn btn-secondary" id="evidenceDetailClose2">Close</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var pathPrefix = window.location.pathname.indexOf('/coinrex/') === 0 ? '/coinrex' : '';
    var API_BASE = pathPrefix + '/api/admin/boosthub.php';

    var categorySelect = document.getElementById('evidenceFilterCategory');
    var statusSelect = document.getElementById('evidenceFilterStatus');
    var refreshBtn = document.getElementById('evidenceRefreshBtn');
    var tableBody = document.getElementById('evidenceTableBody');
    var paginationEl = document.getElementById('evidencePagination');
    var totalCountEl = document.getElementById('evidenceTotalCount');

    var detailModal = document.getElementById('evidenceDetailModal');
    var detailId = document.getElementById('evidenceDetailId');
    var detailBody = document.getElementById('evidenceDetailBody');
    var detailClose2 = document.getElementById('evidenceDetailClose2');

    var currentPage = 1;
    var totalPages = 1;

    // ─── Load evidence ────────────────────────────────────────────
    function loadEvidence(page) {
        page = page || 1;
        currentPage = page;

        var category = categorySelect ? categorySelect.value : 'all';
        var status = statusSelect ? statusSelect.value : 'all';

        tableBody.innerHTML = '<tr><td colspan="5" class="dashboard-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></td></tr>';

        var url = API_BASE + '?action=all_evidence&task_category=' + encodeURIComponent(category) +
                  '&status=' + encodeURIComponent(status) +
                  '&page=' + page +
                  '&per_page=20';

        console.log('Fetching URL:', url);
        fetch(url)
            .then(function (response) {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                }
                return response.text().then(function (text) {
                    console.log('Response text (first 300 chars):', text.substring(0, 300));
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                    }
                });
            })
            .then(function (res) {
                console.log('Parsed response:', JSON.stringify(res).substring(0, 300));
                if (!res.success) throw new Error(res.error || 'Failed to load');
                var data = Array.isArray(res.data) ? res.data : [];
                renderTable(data);
                renderPagination(res.page || 1, res.pages || 1, res.total || 0);
            })
            .catch(function (err) {
                console.error('Fetch error:', err);
                if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="5" class="dashboard-empty is-error"><i class="fas fa-exclamation-triangle"></i><p>' + escapeHtml(err.message) + '</p></td></tr>';
                }
            });
    }

    // ─── Render table ─────────────────────────────────────────────
    function renderTable(rows) {
        if (rows.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="dashboard-empty"><i class="fas fa-inbox"></i><p>No submissions found.</p></td></tr>';
            return;
        }

        var html = '';
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];

            // Status badge
            var statusClass = 'is-' + (r.status || 'submitted');
            var statusLabel = r.status === 'completed' ? 'Approved' :
                             (r.status === 'failed' ? 'Rejected' :
                             (r.status === 'submitted' ? 'Pending' : (r.status || 'Unknown')));

            // Check metadata for review outcome
            var meta = {};
            try { meta = JSON.parse(r.metadata || '{}'); } catch (e) {}
            if (r.status === 'failed' && meta.review_outcome === 'returned_for_correction') {
                statusLabel = 'Returned';
            } else if (r.status === 'failed' && meta.review_outcome === 'rejected') {
                statusLabel = 'Rejected';
            }

            // Category label
            var catLabel = r.task_category || 'custom';
            var catDisplay = catLabel.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });

            html += '' +
                '<tr data-log-id="' + r.id + '">' +
                    '<td data-label="ID">#' + r.id + '</td>' +
                    '<td data-label="User">' +
                        '<strong>' + escapeHtml(r.username || 'Unknown') + '</strong>' +
                        (r.email ? '<br><span class="muted">' + escapeHtml(r.email) + '</span>' : '') +
                    '</td>' +
                    '<td data-label="Category"><span class="status-pill status-under-review">' + escapeHtml(catDisplay) + '</span></td>' +
                    '<td data-label="Status"><span class="evidence-status ' + statusClass + '">' + escapeHtml(statusLabel) + '</span></td>' +
                    '<td data-label="Action">' +
                        '<div class="evidence-action-btns">' +
                            '<button type="button" class="btn btn-primary evidence-detail-btn" data-id="' + r.id + '"><i class="fas fa-eye"></i> View</button>' +
                            '<button type="button" class="btn evidence-delete-btn" data-id="' + r.id + '" style="background:#991b1b;color:#fee2e2;border:1px solid #ef4444;"><i class="fas fa-trash"></i> Delete</button>' +
                        '</div>' +
                    '</td>' +
                '</tr>';
        }

        tableBody.innerHTML = html;

        // Bind detail buttons
        var detailBtns = tableBody.querySelectorAll('.evidence-detail-btn');
        for (var db = 0; db < detailBtns.length; db++) {
            detailBtns[db].addEventListener('click', function () {
                var id = parseInt(this.getAttribute('data-id'), 10);
                for (var j = 0; j < rows.length; j++) {
                    if (parseInt(rows[j].id, 10) === id) {
                        openDetailModal(rows[j]);
                        break;
                    }
                }
            });
        }

        // Bind delete buttons
        var deleteBtns = tableBody.querySelectorAll('.evidence-delete-btn');
        for (var d = 0; d < deleteBtns.length; d++) {
            deleteBtns[d].addEventListener('click', function () {
                var id = parseInt(this.getAttribute('data-id'), 10);
                if (confirm('Delete evidence submission #' + id + '? This action cannot be undone.')) {
                    deleteEvidence(id);
                }
            });
        }
    }

    // ─── Delete evidence ──────────────────────────────────────────
    function deleteEvidence(id) {
        var url = API_BASE;
        var formData = new FormData();
        formData.append('action_type', 'delete_evidence');
        formData.append('id', id);
        fetch(url, { method: 'POST', body: formData })
            .then(function (res) { return res.json(); })
            .then(function (res) {
                if (res.success) {
                    loadEvidence(currentPage);
                } else {
                    alert('Failed to delete: ' + (res.error || 'Unknown error'));
                }
            })
            .catch(function (err) {
                alert('Error: ' + err.message);
            });
    }

    // ─── Render pagination ────────────────────────────────────────
    function renderPagination(page, pages, total) {
        if (totalCountEl) {
            totalCountEl.textContent = '— ' + total + ' total';
        }

        if (pages <= 1) {
            paginationEl.innerHTML = '';
            return;
        }

        var html = '';

        // Prev
        if (page > 1) {
            html += '<a href="#" class="pagination-link pagination-prev" data-page="' + (page - 1) + '"><i class="fas fa-chevron-left"></i> Prev</a>';
        } else {
            html += '<span class="pagination-link pagination-prev is-disabled"><i class="fas fa-chevron-left"></i> Prev</span>';
        }

        var range = 2;
        var start = Math.max(1, page - range);
        var end = Math.min(pages, page + range);

        if (start > 1) {
            html += '<a href="#" class="pagination-link" data-page="1">1</a>';
            if (start > 2) {
                html += '<span class="pagination-ellipsis">…</span>';
            }
        }

        for (var p = start; p <= end; p++) {
            if (p === page) {
                html += '<span class="pagination-link is-active">' + p + '</span>';
            } else {
                html += '<a href="#" class="pagination-link" data-page="' + p + '">' + p + '</a>';
            }
        }

        if (end < pages) {
            if (end < pages - 1) {
                html += '<span class="pagination-ellipsis">…</span>';
            }
            html += '<a href="#" class="pagination-link" data-page="' + pages + '">' + pages + '</a>';
        }

        // Next
        if (page < pages) {
            html += '<a href="#" class="pagination-link pagination-next" data-page="' + (page + 1) + '">Next <i class="fas fa-chevron-right"></i></a>';
        } else {
            html += '<span class="pagination-link pagination-next is-disabled">Next <i class="fas fa-chevron-right"></i></span>';
        }

        paginationEl.innerHTML = html;

        // Bind pagination clicks
        var links = paginationEl.querySelectorAll('a.pagination-link');
        for (var li = 0; li < links.length; li++) {
            links[li].addEventListener('click', function (e) {
                e.preventDefault();
                var pg = parseInt(this.getAttribute('data-page'), 10);
                if (pg > 0 && pg !== currentPage) {
                    loadEvidence(pg);
                }
            });
        }
    }

    // ─── Open detail modal ────────────────────────────────────────
    function openDetailModal(r) {
        if (!detailModal || !detailId || !detailBody) return;

        detailId.textContent = r.id;

        // Parse evidence
        var evidenceText = r.proof_data || '';
        var evidenceScreenshot = '';
        try {
            var parsed = JSON.parse(r.proof_data || '{}');
            if (typeof parsed === 'object' && parsed !== null) {
                evidenceText = parsed.text || r.proof_data || '';
                evidenceScreenshot = parsed.screenshot || '';
            }
        } catch (e) {}

        // Parse metadata
        var meta = {};
        try { meta = JSON.parse(r.metadata || '{}'); } catch (e) {}

        var statusLabel = r.status === 'completed' ? 'Approved' :
                         (r.status === 'failed' ? 'Rejected' :
                         (r.status === 'submitted' ? 'Pending' : (r.status || 'Unknown')));
        if (r.status === 'failed' && meta.review_outcome === 'returned_for_correction') {
            statusLabel = 'Returned for Correction';
        } else if (r.status === 'failed' && meta.review_outcome === 'rejected') {
            statusLabel = 'Rejected';
        }

        var dateStr = r.completed_at || '';
        var dateDisplay = dateStr ? dateStr.substring(0, 10) + ' ' + dateStr.substring(11, 16) : '-';

        var screenshotHtml = '';
        if (evidenceScreenshot) {
            screenshotHtml = '<div class="value"><a href="' + escapeHtml(evidenceScreenshot) + '" target="_blank" rel="noopener noreferrer"><img src="' + escapeHtml(evidenceScreenshot) + '" alt="Screenshot"></a></div>';
        }

        var reviewNoteHtml = '';
        if (meta.correction_note) {
            reviewNoteHtml = '<div class="evidence-detail-field is-fullwidth"><label>Correction Note</label><div class="value" style="color:#ef4444;">' + escapeHtml(meta.correction_note) + '</div></div>';
        }
        if (meta.reviewed_at) {
            reviewNoteHtml += '<div class="evidence-detail-field is-fullwidth"><label>Reviewed At</label><div class="value">' + escapeHtml(meta.reviewed_at) + '</div></div>';
        }

        var rejectionHtml = '';
        if (r.rejection_count > 0) {
            rejectionHtml = '<div class="evidence-detail-field is-fullwidth"><label>Rejection Count</label><div class="value">' + r.rejection_count + '</div></div>';
        }

        // Category display
        var catDisplay = (r.task_category || 'custom').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });

        detailBody.innerHTML = '' +
            '<div class="evidence-detail-field"><label>User</label><div class="value"><strong>' + escapeHtml(r.username || 'Unknown') + '</strong>' + (r.email ? ' (' + escapeHtml(r.email) + ')' : '') + '</div></div>' +
            '<div class="evidence-detail-field"><label>Task</label><div class="value"><strong>' + escapeHtml(r.title || 'Untitled') + '</strong> — ' + parseFloat(r.reward || 0).toFixed(2) + ' $REX</div></div>' +
            '<div class="evidence-detail-field"><label>Category</label><div class="value">' + escapeHtml(catDisplay) + '</div></div>' +
            '<div class="evidence-detail-field"><label>Status</label><div class="value"><span class="evidence-status is-' + (r.status || 'submitted') + '">' + escapeHtml(statusLabel) + '</span></div></div>' +
            '<div class="evidence-detail-field"><label>Date</label><div class="value">' + escapeHtml(dateDisplay) + '</div></div>' +
            '<div class="evidence-detail-field is-fullwidth"><label>Evidence Text</label><div class="value"><code id="evidenceTextContent">' + escapeHtml(evidenceText) + '</code><button type="button" class="evidence-copy-btn" onclick="copyEvidenceText(this)" title="Copy to clipboard"><i class="fas fa-copy"></i> Copy</button></div></div>' +
            (evidenceScreenshot ? '<div class="evidence-detail-field is-fullwidth"><label>Screenshot</label>' + screenshotHtml + '</div>' : '') +
            reviewNoteHtml +
            rejectionHtml;

        detailModal.classList.add('show');
    }

    function closeDetailModal() {
        if (detailModal) detailModal.classList.remove('show');
    }

    // ─── Utility ─────────────────────────────────────────────────
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // ─── Event bindings ──────────────────────────────────────────
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            loadEvidence(1);
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            loadEvidence(1);
        });
    }

    if (statusSelect) {
        statusSelect.addEventListener('change', function () {
            loadEvidence(1);
        });
    }

    if (detailClose2) detailClose2.addEventListener('click', closeDetailModal);
    if (detailModal) {
        detailModal.addEventListener('click', function (e) {
            if (e.target === detailModal) closeDetailModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDetailModal();
    });

    // ─── Boot ────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { loadEvidence(1); });
    } else {
        loadEvidence(1);
    }

})();

// ─── Global copy function for Evidence Text ─────────────────────
function copyEvidenceText(btn) {
    'use strict';
    var codeEl = document.getElementById('evidenceTextContent');
    if (!codeEl) return;

    var text = codeEl.textContent || codeEl.innerText || '';
    if (!text) return;

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
            showCopiedFeedback(btn);
        }).catch(function () {
            fallbackCopy(text, btn);
        });
    } else {
        fallbackCopy(text, btn);
    }
}

function fallbackCopy(text, btn) {
    'use strict';
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        showCopiedFeedback(btn);
    } catch (e) {}
    document.body.removeChild(textarea);
}

function showCopiedFeedback(btn) {
    'use strict';
    if (!btn) return;
    btn.classList.add('is-copied');
    btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
    setTimeout(function () {
        btn.classList.remove('is-copied');
        btn.innerHTML = '<i class="fas fa-copy"></i> Copy';
    }, 2000);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
