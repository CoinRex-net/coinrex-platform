<?php
/**
 * Render Google-style pagination links.
 * Defined once at the top so both AJAX handler and normal render can use it.
 */
function renderPagination($currentPage, $totalPages, $baseUrl, $extraParams = [], $pageParam = 'page') {
    if ($totalPages <= 1) {
        return '';
    }

    $buildUrl = function ($page) use ($baseUrl, $extraParams, $pageParam) {
        $params = $extraParams;
        $params[$pageParam] = $page;
        return $baseUrl . '?' . http_build_query($params);
    };

    $html = '<div class="pagination-bar">';

    // Prev
    if ($currentPage > 1) {
        $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage - 1), ENT_QUOTES, 'UTF-8') . '" class="pagination-link pagination-prev" data-page="' . ($currentPage - 1) . '"><i class="fas fa-chevron-left"></i> Prev</a>';
    } else {
        $html .= '<span class="pagination-link pagination-prev is-disabled"><i class="fas fa-chevron-left"></i> Prev</span>';
    }

    $range = 2;
    $start = max(1, $currentPage - $range);
    $end = min($totalPages, $currentPage + $range);

    if ($start > 1) {
        $html .= '<a href="' . htmlspecialchars($buildUrl(1), ENT_QUOTES, 'UTF-8') . '" class="pagination-link" data-page="1">1</a>';
        if ($start > 2) {
            $html .= '<span class="pagination-ellipsis">…</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? ' is-active' : '';
        $html .= '<a href="' . htmlspecialchars($buildUrl($i), ENT_QUOTES, 'UTF-8') . '" class="pagination-link' . $active . '" data-page="' . $i . '">' . $i . '</a>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<span class="pagination-ellipsis">…</span>';
        }
        $html .= '<a href="' . htmlspecialchars($buildUrl($totalPages), ENT_QUOTES, 'UTF-8') . '" class="pagination-link" data-page="' . $totalPages . '">' . $totalPages . '</a>';
    }

    if ($currentPage < $totalPages) {
        $html .= '<a href="' . htmlspecialchars($buildUrl($currentPage + 1), ENT_QUOTES, 'UTF-8') . '" class="pagination-link pagination-next" data-page="' . ($currentPage + 1) . '">Next <i class="fas fa-chevron-right"></i></a>';
    } else {
        $html .= '<span class="pagination-link pagination-next is-disabled">Next <i class="fas fa-chevron-right"></i></span>';
    }

    $html .= '</div>';
    return $html;
}

// ====== AJAX handler must come BEFORE header.php to avoid HTML pollution ======
if (!empty($_GET['ajax'])) {
    require_once __DIR__ . '/includes/config.php';
    requireAdminAuth();
    requireAdminPageAccess('reward-ledger');
    require_once __DIR__ . '/includes/reward_admin.php';

    $db = getDBConnection();
    ensureRewardClaimSchema($db);

    $perPage = 20;
    $ledger_filters = [
        'user' => trim((string) ($_GET['user'] ?? '')),
        'source' => trim((string) ($_GET['source'] ?? '')),
        'phase' => trim((string) ($_GET['phase'] ?? '')),
        'status' => trim((string) ($_GET['status'] ?? '')),
    ];
    $ledger_page = max(1, (int) ($_GET['page'] ?? 1));
    $claims_page = max(1, (int) ($_GET['claims_page'] ?? 1));

    $ledger_rows = adminRewardGetLedgerRows($db, $ledger_filters, $ledger_page, $perPage);
    $total_ledger = adminRewardGetLedgerCount($db, $ledger_filters);
    $total_ledger_pages = max(1, (int) ceil($total_ledger / $perPage));

    $claim_rows = adminRewardGetClaimRows($db, $claims_page, $perPage);
    $total_claims = adminRewardGetClaimCount($db);
    $total_claims_pages = max(1, (int) ceil($total_claims / $perPage));

    // Build filter params for pagination links
    $filterParams = [];
    if ($ledger_filters['user'] !== '') $filterParams['user'] = $ledger_filters['user'];
    if ($ledger_filters['source'] !== '') $filterParams['source'] = $ledger_filters['source'];
    if ($ledger_filters['phase'] !== '') $filterParams['phase'] = $ledger_filters['phase'];
    if ($ledger_filters['status'] !== '') $filterParams['status'] = $ledger_filters['status'];

    // Ledger table body
    $ledgerBody = '';
    if (empty($ledger_rows)) {
        $ledgerBody = '<tr><td colspan="6" style="text-align:center;padding:32px;color:#94a3b8;">No ledger entries found matching your filters.</td></tr>';
    } else {
        foreach ($ledger_rows as $lr) {
            $statusClass = ($lr['status'] ?? '') === 'available' ? 'is-active' : (($lr['status'] ?? '') === 'locked' ? 'is-pending' : 'is-suspended');
            $ledgerBody .= '<tr>';
            $ledgerBody .= '<td data-label="Entry"><strong>#' . (int) $lr['id'] . '</strong><br><span class="muted">' . htmlspecialchars((string) ($lr['action_type'] ?? 'credit'), ENT_QUOTES, 'UTF-8') . '</span><br><span class="muted">' . htmlspecialchars((string) ($lr['reference_id'] ?? 'n/a'), ENT_QUOTES, 'UTF-8') . '</span></td>';
            $ledgerBody .= '<td data-label="User"><strong>' . htmlspecialchars((string) ($lr['username'] ?? ('User ' . $lr['user_id'])), ENT_QUOTES, 'UTF-8') . '</strong><br><span class="muted">ID ' . (int) $lr['user_id'] . '</span></td>';
            $ledgerBody .= '<td data-label="Source">' . htmlspecialchars((string) $lr['source'], ENT_QUOTES, 'UTF-8') . '</td>';
            $ledgerBody .= '<td data-label="Phase">' . htmlspecialchars(strtoupper((string) $lr['reward_phase']), ENT_QUOTES, 'UTF-8') . '</td>';
            $ledgerBody .= '<td data-label="Amount"><strong>' . number_format((float) ($lr['amount'] ?? 0), 4) . '</strong></td>';
            $ledgerBody .= '<td data-label="Status"><span class="dashboard-pill ' . $statusClass . '">' . htmlspecialchars((string) $lr['status'], ENT_QUOTES, 'UTF-8') . '</span></td>';
            $ledgerBody .= '</tr>';
        }
    }

    // Claims table body
    $claimsBody = '';
    if (empty($claim_rows)) {
        $claimsBody = '<tr><td colspan="4" style="text-align:center;padding:32px;color:#94a3b8;">No claim records found.</td></tr>';
    } else {
        foreach ($claim_rows as $cl) {
            $statusClass = ($cl['status'] ?? '') === 'generated' ? 'is-pending' : (($cl['status'] ?? '') === 'used' ? 'is-active' : 'is-suspended');
            $claimsBody .= '<tr>';
            $claimsBody .= '<td data-label="Claim"><strong>#' . (int) $cl['id'] . '</strong><br><span class="muted">' . number_format((float) ($cl['total_amount'] ?? 0), 2) . ' $REX</span><br><span class="muted">' . htmlspecialchars((string) $cl['created_at'], ENT_QUOTES, 'UTF-8') . '</span></td>';
            $claimsBody .= '<td data-label="User"><strong>' . htmlspecialchars((string) ($cl['username'] ?? ('User ' . $cl['user_id'])), ENT_QUOTES, 'UTF-8') . '</strong><br><span class="muted">' . htmlspecialchars(ucfirst((string) ($cl['level'] ?? 'beginner')), ENT_QUOTES, 'UTF-8') . '</span><br>';
            if (!empty($cl['reward_frozen'])) $claimsBody .= '<span class="dashboard-pill is-suspended">Frozen</span>';
            $claimsBody .= '</td>';
            $claimsBody .= '<td data-label="Status"><span class="dashboard-pill ' . $statusClass . '">' . htmlspecialchars((string) $cl['status'], ENT_QUOTES, 'UTF-8') . '</span></td>';
            $claimsBody .= '<td data-label="Nonce"><code>' . htmlspecialchars(substr((string) $cl['nonce'], -10), ENT_QUOTES, 'UTF-8') . '</code></td>';
            $claimsBody .= '</tr>';
        }
    }

    // Pagination bars
    $ledgerPagination = renderPagination($ledger_page, $total_ledger_pages, ADMIN_BASE_URL . '/reward-ledger.php', array_merge($filterParams, ['claims_page' => $claims_page]), 'page');
    $claimsPagination = renderPagination($claims_page, $total_claims_pages, ADMIN_BASE_URL . '/reward-ledger.php', array_merge($filterParams, ['page' => $ledger_page]), 'claims_page');

    header('Content-Type: application/json');
    echo json_encode([
        'ledger_body' => $ledgerBody,
        'ledger_pagination' => $ledgerPagination,
        'claims_body' => $claimsBody,
        'claims_pagination' => $claimsPagination,
        'ledger_page' => $ledger_page,
        'claims_page' => $claims_page,
    ]);
    exit();
}

// ====== Normal page render ======
$page_title = 'Reward Ledger';
$activePage = 'reward-ledger';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/reward_admin.php';

$db = getDBConnection();
ensureRewardClaimSchema($db);

$perPage = 20;
$ledger_filters = [
    'user' => trim((string) ($_GET['user'] ?? '')),
    'source' => trim((string) ($_GET['source'] ?? '')),
    'phase' => trim((string) ($_GET['phase'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
];
$ledger_page = max(1, (int) ($_GET['page'] ?? 1));
$claims_page = max(1, (int) ($_GET['claims_page'] ?? 1));

$ledger_rows = adminRewardGetLedgerRows($db, $ledger_filters, $ledger_page, $perPage);
$total_ledger = adminRewardGetLedgerCount($db, $ledger_filters);
$total_ledger_pages = max(1, (int) ceil($total_ledger / $perPage));

$claim_rows = adminRewardGetClaimRows($db, $claims_page, $perPage);
$total_claims = adminRewardGetClaimCount($db);
$total_claims_pages = max(1, (int) ceil($total_claims / $perPage));

// Build filter params for pagination links (exclude page/claims_page)
$filterParams = [];
if ($ledger_filters['user'] !== '') $filterParams['user'] = $ledger_filters['user'];
if ($ledger_filters['source'] !== '') $filterParams['source'] = $ledger_filters['source'];
if ($ledger_filters['phase'] !== '') $filterParams['phase'] = $ledger_filters['phase'];
if ($ledger_filters['status'] !== '') $filterParams['status'] = $ledger_filters['status'];
?>
<style>
/* ====== Google-Style Pagination ====== */
.pagination-bar {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 4px;
    padding: 16px 0 4px;
    flex-wrap: wrap;
}
.pagination-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 8px;
    background: transparent;
    color: #94a3b8;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.15s;
    cursor: pointer;
    border: none;
    font-family: inherit;
}
.pagination-link:hover {
    background: rgba(148,163,184,0.1);
    color: #e2e8f0;
}
.pagination-link.is-active {
    background: #d4af37;
    color: #0f172a;
    font-weight: 700;
}
.pagination-link.is-disabled {
    opacity: 0.35;
    cursor: default;
    pointer-events: none;
}
.pagination-link i {
    font-size: 11px;
}
.pagination-ellipsis {
    color: #64748b;
    padding: 0 4px;
    font-size: 14px;
}
.pagination-prev,
.pagination-next {
    gap: 6px;
}
.pagination-bar.is-loading .pagination-link {
    pointer-events: none;
    opacity: 0.5;
}
</style>

<div class="dashboard-container">

    <!-- ====== HEADER ====== -->
    <div class="dashboard-header">
        <div class="dashboard-header-left">
            <div class="dashboard-header-icon"><i class="fas fa-book"></i></div>
            <div class="dashboard-header-text">
                <h1>Reward Ledger</h1>
                <p>Browse all reward transactions and claim records</p>
            </div>
        </div>
        <div class="dashboard-header-badge">
            <i class="fas fa-database"></i> <?php echo number_format($total_ledger); ?> Entries
        </div>
    </div>

    <!-- ====== LEDGER TABLE ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-list"></i> Ledger Entries <span class="divider-sub">Filter and browse reward transactions</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-receipt"></i> Ledger</span>
                <h3>Transaction Ledger</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">All reward credits, debits, and status changes across the platform.</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="dashboard-filter-bar">
            <form method="GET" action="" class="dashboard-filter-form" id="ledgerFilterForm">
                <input type="text" name="user" placeholder="Username or User ID" value="<?php echo htmlspecialchars($ledger_filters['user'], ENT_QUOTES, 'UTF-8'); ?>">
                <select name="source">
                    <option value="">All Sources</option>
                    <option value="mini_task" <?php echo $ledger_filters['source'] === 'mini_task' ? 'selected' : ''; ?>>Mini Task</option>
                    <option value="referral" <?php echo $ledger_filters['source'] === 'referral' ? 'selected' : ''; ?>>Referral</option>
                    <option value="review" <?php echo $ledger_filters['source'] === 'review' ? 'selected' : ''; ?>>Review</option>
                    <option value="bonus" <?php echo $ledger_filters['source'] === 'bonus' ? 'selected' : ''; ?>>Bonus</option>
                </select>
                <select name="phase">
                    <option value="">All Phases</option>
                    <option value="phase1" <?php echo $ledger_filters['phase'] === 'phase1' ? 'selected' : ''; ?>>Phase 1</option>
                    <option value="phase2" <?php echo $ledger_filters['phase'] === 'phase2' ? 'selected' : ''; ?>>Phase 2</option>
                </select>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $ledger_filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="locked" <?php echo $ledger_filters['status'] === 'locked' ? 'selected' : ''; ?>>Locked</option>
                    <option value="available" <?php echo $ledger_filters['status'] === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="claimed" <?php echo $ledger_filters['status'] === 'claimed' ? 'selected' : ''; ?>>Claimed</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <?php if ($ledger_filters['user'] !== '' || $ledger_filters['source'] !== '' || $ledger_filters['phase'] !== '' || $ledger_filters['status'] !== ''): ?>
                    <a href="<?php echo ADMIN_BASE_URL; ?>/reward-ledger.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Ledger Table -->
        <div class="dashboard-table-wrap">
            <table class="dashboard-table" id="ledgerTable">
                <thead>
                <tr>
                    <th>Entry</th>
                    <th>User</th>
                    <th>Source</th>
                    <th>Phase</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody id="ledgerTableBody">
                <?php if (empty($ledger_rows)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:32px;color:#94a3b8;">No ledger entries found matching your filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($ledger_rows as $lr): ?>
                        <?php $statusClass = ($lr['status'] ?? '') === 'available' ? 'is-active' : (($lr['status'] ?? '') === 'locked' ? 'is-pending' : 'is-suspended'); ?>
                        <tr>
                            <td data-label="Entry">
                                <strong>#<?php echo (int) $lr['id']; ?></strong><br>
                                <span class="muted"><?php echo htmlspecialchars((string) ($lr['action_type'] ?? 'credit'), ENT_QUOTES, 'UTF-8'); ?></span><br>
                                <span class="muted"><?php echo htmlspecialchars((string) ($lr['reference_id'] ?? 'n/a'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td data-label="User">
                                <strong><?php echo htmlspecialchars((string) ($lr['username'] ?? ('User ' . $lr['user_id'])), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <span class="muted">ID <?php echo (int) $lr['user_id']; ?></span>
                            </td>
                            <td data-label="Source"><?php echo htmlspecialchars((string) $lr['source'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Phase"><?php echo htmlspecialchars(strtoupper((string) $lr['reward_phase']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Amount"><strong><?php echo number_format((float) ($lr['amount'] ?? 0), 4); ?></strong></td>
                            <td data-label="Status"><span class="dashboard-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string) $lr['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Ledger Pagination -->
        <div id="ledgerPagination">
            <?php echo renderPagination($ledger_page, $total_ledger_pages, ADMIN_BASE_URL . '/reward-ledger.php', array_merge($filterParams, ['claims_page' => $claims_page]), 'page'); ?>
        </div>
    </div>

    <!-- ====== CLAIMS TABLE ====== -->
    <div class="dashboard-section-divider">
        <h2><i class="fas fa-hand-holding-heart"></i> Claim Records <span class="divider-sub">Generated and used claim snapshots</span></h2>
    </div>

    <div class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-file-invoice"></i> Claims</span>
                <h3>Claim Snapshots</h3>
                <p class="muted" style="margin:4px 0 0;font-size:12px;">Records of generated claim codes and their usage status.</p>
            </div>
        </div>

        <div class="dashboard-table-wrap">
            <table class="dashboard-table" id="claimsTable">
                <thead>
                <tr>
                    <th>Claim</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>Nonce</th>
                </tr>
                </thead>
                <tbody id="claimsTableBody">
                <?php if (empty($claim_rows)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:32px;color:#94a3b8;">No claim records found.</td></tr>
                <?php else: ?>
                    <?php foreach ($claim_rows as $cl): ?>
                        <?php $statusClass = ($cl['status'] ?? '') === 'generated' ? 'is-pending' : (($cl['status'] ?? '') === 'used' ? 'is-active' : 'is-suspended'); ?>
                        <tr>
                            <td data-label="Claim">
                                <strong>#<?php echo (int) $cl['id']; ?></strong><br>
                                <span class="muted"><?php echo number_format((float) ($cl['total_amount'] ?? 0), 2); ?> $REX</span><br>
                                <span class="muted"><?php echo htmlspecialchars((string) $cl['created_at'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td data-label="User">
                                <strong><?php echo htmlspecialchars((string) ($cl['username'] ?? ('User ' . $cl['user_id'])), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <span class="muted"><?php echo htmlspecialchars(ucfirst((string) ($cl['level'] ?? 'beginner')), ENT_QUOTES, 'UTF-8'); ?></span><br>
                                <?php if (!empty($cl['reward_frozen'])): ?>
                                    <span class="dashboard-pill is-suspended">Frozen</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status"><span class="dashboard-pill <?php echo $statusClass; ?>"><?php echo htmlspecialchars((string) $cl['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td data-label="Nonce"><code><?php echo htmlspecialchars(substr((string) $cl['nonce'], -10), ENT_QUOTES, 'UTF-8'); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Claims Pagination -->
        <div id="claimsPagination">
            <?php echo renderPagination($claims_page, $total_claims_pages, ADMIN_BASE_URL . '/reward-ledger.php', array_merge($filterParams, ['page' => $ledger_page]), 'claims_page'); ?>
        </div>
    </div>

</div><!-- /.dashboard-container -->

<script>
(function () {
    'use strict';

    const ledgerBody = document.getElementById('ledgerTableBody');
    const claimsBody = document.getElementById('claimsTableBody');
    const ledgerPag = document.getElementById('ledgerPagination');
    const claimsPag = document.getElementById('claimsPagination');

    if (!ledgerBody || !claimsBody || !ledgerPag || !claimsPag) return;

    /**
     * Fetch new page data via AJAX and update the DOM.
     */
    function loadPage(page, claimsPage, filters) {
        const params = new URLSearchParams();
        params.set('ajax', '1');
        params.set('page', page);
        params.set('claims_page', claimsPage);
        if (filters.user) params.set('user', filters.user);
        if (filters.source) params.set('source', filters.source);
        if (filters.phase) params.set('phase', filters.phase);
        if (filters.status) params.set('status', filters.status);

        // Add loading state
        ledgerPag.classList.add('is-loading');
        claimsPag.classList.add('is-loading');

        fetch('<?php echo ADMIN_BASE_URL; ?>/reward-ledger.php?' + params.toString())
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                // Update ledger
                ledgerBody.innerHTML = data.ledger_body;
                ledgerPag.innerHTML = data.ledger_pagination;
                // Update claims
                claimsBody.innerHTML = data.claims_body;
                claimsPag.innerHTML = data.claims_pagination;

                // Re-bind click handlers on new pagination links
                bindPaginationClicks();

                // Update URL without reload
                const url = new URL(window.location);
                url.searchParams.set('page', data.ledger_page);
                url.searchParams.set('claims_page', data.claims_page);
                window.history.pushState({}, '', url.toString());

                // Remove loading state
                ledgerPag.classList.remove('is-loading');
                claimsPag.classList.remove('is-loading');
            })
            .catch(function () {
                ledgerPag.classList.remove('is-loading');
                claimsPag.classList.remove('is-loading');
            });
    }

    /**
     * Get current filter values from the filter form.
     */
    function getFilters() {
        const form = document.getElementById('ledgerFilterForm');
        if (!form) return {};
        return {
            user: form.querySelector('input[name="user"]')?.value || '',
            source: form.querySelector('select[name="source"]')?.value || '',
            phase: form.querySelector('select[name="phase"]')?.value || '',
            status: form.querySelector('select[name="status"]')?.value || '',
        };
    }

    /**
     * Get current page numbers from URL.
     */
    function getCurrentPages() {
        const url = new URL(window.location);
        return {
            page: parseInt(url.searchParams.get('page')) || 1,
            claimsPage: parseInt(url.searchParams.get('claims_page')) || 1,
        };
    }

    /**
     * Bind click events to all pagination links.
     */
    function bindPaginationClicks() {
        document.querySelectorAll('#ledgerPagination .pagination-link, #claimsPagination .pagination-link').forEach(function (link) {
            link.addEventListener('click', function (e) {
                // Only intercept links with data-page (not disabled spans)
                const page = this.getAttribute('data-page');
                if (!page) return;

                e.preventDefault();

                const pages = getCurrentPages();
                const filters = getFilters();

                // Determine which pagination was clicked
                if (this.closest('#ledgerPagination')) {
                    pages.page = parseInt(page);
                } else if (this.closest('#claimsPagination')) {
                    pages.claimsPage = parseInt(page);
                }

                loadPage(pages.page, pages.claimsPage, filters);
            });
        });
    }

    // Intercept filter form submission to use AJAX
    const filterForm = document.getElementById('ledgerFilterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const filters = getFilters();
            loadPage(1, 1, filters);
        });
    }

    // Handle browser back/forward
    window.addEventListener('popstate', function () {
        const pages = getCurrentPages();
        const filters = getFilters();
        loadPage(pages.page, pages.claimsPage, filters);
    });

    // Initial bind
    bindPaginationClicks();
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
