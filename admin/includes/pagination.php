<?php
/**
 * Shared Admin Pagination Helper
 * Location: /coinrex/admin/includes/pagination.php
 *
 * Provides a single source of truth for Google-style pagination rendering
 * and AJAX pagination JavaScript across all admin pages.
 */

/**
 * Render Google-style pagination links.
 *
 * @param int    $currentPage Current page number (1-based)
 * @param int    $totalPages  Total number of pages
 * @param string $baseUrl     Base URL for pagination links
 * @param array  $extraParams Additional query parameters to preserve
 * @param string $pageParam   Name of the page parameter (default 'page')
 * @return string HTML of the pagination bar
 */
function renderPagination(int $currentPage, int $totalPages, string $baseUrl, array $extraParams = [], string $pageParam = 'page'): string {
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

/**
 * Get the current page number from $_GET.
 */
function paginationGetPage(string $param = 'page', int $default = 1): int {
    return max(1, (int) ($_GET[$param] ?? $default));
}

/**
 * Get the per-page limit from $_GET or use default.
 */
function paginationGetPerPage(int $default = 20): int {
    return max(1, min(200, (int) ($_GET['per_page'] ?? $default)));
}

/**
 * Build a JSON response array for AJAX pagination.
 */
function paginationJsonResponse(string $tableBody, string $paginationHtml, int $page): array {
    return [
        'table_body' => $tableBody,
        'pagination' => $paginationHtml,
        'page' => $page,
    ];
}

/**
 * Output the pagination CSS styles (once per page).
 */
function paginationRenderStyles(): void {
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
    <?php
}

/**
 * Generate the AJAX pagination JavaScript IIFE.
 *
 * @param array $config Configuration array with keys:
 *   - tableBodyId: string (default 'tableBody')
 *   - paginationId: string (default 'pagination')
 *   - fetchUrl: string (the URL to fetch from)
 *   - filterFormId: string|null (default 'filterForm')
 *   - extraParams: array of extra param names to include from form
 *   - pageParam: string (default 'page')
 */
function paginationRenderJS(array $config = []): void {
    $tableBodyId = $config['tableBodyId'] ?? 'tableBody';
    $paginationId = $config['paginationId'] ?? 'pagination';
    $fetchUrl = $config['fetchUrl'] ?? '';
    $filterFormId = $config['filterFormId'] ?? 'filterForm';
    $extraParams = $config['extraParams'] ?? [];
    $pageParam = $config['pageParam'] ?? 'page';

    $extraParamsJson = json_encode($extraParams, JSON_UNESCAPED_UNICODE);
    ?>
    <script>
    (function () {
        'use strict';

        const tableBody = document.getElementById('<?php echo $tableBodyId; ?>');
        const pagination = document.getElementById('<?php echo $paginationId; ?>');
        if (!tableBody || !pagination) return;

        const fetchUrl = '<?php echo htmlspecialchars($fetchUrl, ENT_QUOTES, 'UTF-8'); ?>';
        const extraParamNames = <?php echo $extraParamsJson; ?>;
        const pageParam = '<?php echo $pageParam; ?>';

        const filterForm = document.getElementById('<?php echo $filterFormId; ?>');

        function readFilterParamsFromForm() {
            const values = {};
            if (!filterForm) {
                return values;
            }
            extraParamNames.forEach(function (name) {
                const el = filterForm.querySelector('[name="' + name + '"]');
                if (el) {
                    values[name] = el.value.trim();
                }
            });
            return values;
        }

        function syncFormFromUrl(url) {
            if (!filterForm) {
                return;
            }
            extraParamNames.forEach(function (name) {
                const el = filterForm.querySelector('[name="' + name + '"]');
                if (el) {
                    el.value = url.searchParams.get(name) || '';
                }
            });
        }

        function applyFilterParamsToUrl(url, page, filterValues) {
            url.searchParams.set(pageParam, String(page));
            extraParamNames.forEach(function (name) {
                const val = (filterValues[name] || '').trim();
                if (val !== '') {
                    url.searchParams.set(name, val);
                } else {
                    url.searchParams.delete(name);
                }
            });
            url.searchParams.delete('ajax');
        }

        function loadPage(page, options) {
            options = options || {};
            const filterValues = options.filterValues || readFilterParamsFromForm();
            const params = new URLSearchParams();
            params.set('ajax', '1');
            params.set(pageParam, String(page));

            extraParamNames.forEach(function (name) {
                const val = (filterValues[name] || '').trim();
                if (val !== '') {
                    params.set(name, val);
                }
            });

            pagination.classList.add('is-loading');

            const urlToFetch = fetchUrl + '?' + params.toString();

            fetch(urlToFetch)
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status);
                    }
                    return r.json();
                })
                .then(function (data) {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    tableBody.innerHTML = data.table_body || '';
                    pagination.innerHTML = data.pagination || '';
                    const url = new URL(window.location.href);
                    applyFilterParamsToUrl(url, data.page || page, filterValues);
                    window.history.pushState({}, '', url.toString());
                    pagination.classList.remove('is-loading');
                })
                .catch(function (err) {
                    console.error('AJAX pagination error:', err);
                    pagination.classList.remove('is-loading');
                    if (filterForm && options.allowFormFallback !== false) {
                        filterForm.submit();
                    }
                });
        }

        if (pagination) {
            pagination.addEventListener('click', function (e) {
                const link = e.target.closest('.pagination-link');
                if (!link || link.classList.contains('is-disabled')) {
                    return;
                }
                const page = link.getAttribute('data-page');
                if (!page) {
                    return;
                }
                e.preventDefault();
                loadPage(parseInt(page, 10), { allowFormFallback: false });
            });
        }

        if (filterForm) {
            filterForm.addEventListener('submit', function (e) {
                e.preventDefault();
                loadPage(1);
            });
        }

        window.addEventListener('popstate', function () {
            const url = new URL(window.location.href);
            syncFormFromUrl(url);
            const page = parseInt(url.searchParams.get(pageParam), 10) || 1;
            loadPage(page, { allowFormFallback: false });
        });
    })();
    </script>
    <?php
}
