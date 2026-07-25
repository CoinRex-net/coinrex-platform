<?php
$page_title = 'Inactive Users Export';
$activePage = 'inactive-learnhub-users';
$export_format = strtolower(trim((string) ($_GET['export'] ?? '')));
$is_export_request = in_array($export_format, ['txt', 'xls'], true);

if ($is_export_request) {
    require_once __DIR__ . '/includes/config.php';
    requireAdminAuth();
    requireAdminPageAccess($activePage);
} else {
    require_once __DIR__ . '/includes/header.php';
}

$db = getDBConnection();
$total_days = defined('TASKHUB_TOTAL_DAYS') ? (int) TASKHUB_TOTAL_DAYS : 10;
$learnhub_filter = inactiveLearnHubNormalizeFilter((string) ($_GET['learnhub'] ?? 'not_started'), $total_days);
$status_filter = inactiveLearnHubNormalizeStatus((string) ($_GET['status'] ?? 'active'));
$search = trim((string) ($_GET['q'] ?? ''));

function inactiveLearnHubNormalizeFilter(string $value, int $total_days): string {
    $value = strtolower(trim($value));
    if ($value === 'not_started') {
        return 'not_started';
    }

    if (preg_match('/^day_(\d{1,2})$/', $value, $matches)) {
        $day = (int) $matches[1];
        if ($day >= 1 && $day <= $total_days) {
            return 'day_' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        }
    }

    return 'not_started';
}

function inactiveLearnHubNormalizeStatus(string $value): string {
    return in_array($value, ['active', 'suspended', 'all'], true) ? $value : 'active';
}

function inactiveLearnHubFilterLabel(string $filter): string {
    if ($filter === 'not_started') {
        return 'Not Started LearnHub';
    }
    $day = (int) substr($filter, 4);
    return 'Day ' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
}

function inactiveLearnHubStageLabel(array $row, int $total_days): string {
    $completed = max(0, min($total_days, (int) ($row['completed_days'] ?? 0)));
    $current_day = max(1, min($total_days, (int) ($row['current_day'] ?? 1)));
    if ($completed <= 0 && $current_day <= 1) {
        return 'Not Started LearnHub';
    }
    if ($completed >= $total_days || (int) ($row['current_day'] ?? 1) > $total_days) {
        return 'Completed';
    }
    return 'Day ' . str_pad((string) $current_day, 2, '0', STR_PAD_LEFT);
}

function inactiveLearnHubBuildQuery(string $filter, string $status, string $search, int $total_days, bool $count_only = false): array {
    $completed_expr = "GREATEST(
        COALESCE(log_stats.completed_days, 0),
        LEAST({$total_days}, GREATEST(0, COALESCE(u.current_day, 1) - 1))
    )";

    $select = $count_only
        ? "COUNT(*) AS total"
        : "u.id, u.email, u.username, u.full_name, u.status, u.current_day, u.last_active, u.created_at, {$completed_expr} AS completed_days";

    $sql = "
        SELECT {$select}
        FROM users u
        LEFT JOIN (
            SELECT user_id, COUNT(DISTINCT mission_day) AS completed_days
            FROM user_task_logs
            WHERE status = 'completed'
              AND mission_day BETWEEN 1 AND {$total_days}
            GROUP BY user_id
        ) log_stats ON log_stats.user_id = u.id
        WHERE 1 = 1
    ";
    $params = [];

    if ($status !== 'all') {
        $sql .= " AND u.status = ? ";
        $params[] = $status;
    }

    if ($search !== '') {
        $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?) ";
        $needle = '%' . $search . '%';
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
    }

    if ($filter === 'not_started') {
        $sql .= " AND COALESCE(u.current_day, 1) <= 1 AND COALESCE(log_stats.completed_days, 0) = 0 ";
    } else {
        $day = (int) substr($filter, 4);
        $sql .= " AND COALESCE(u.current_day, 1) = ? ";
        $params[] = $day;
    }

    if (!$count_only) {
        $sql .= " ORDER BY u.id DESC ";
    }

    return [$sql, $params];
}

function inactiveLearnHubFetchRows(PDO $db, string $filter, string $status, string $search, int $total_days, ?int $limit = null): array {
    [$sql, $params] = inactiveLearnHubBuildQuery($filter, $status, $search, $total_days, false);
    if ($limit !== null) {
        $sql .= " LIMIT " . max(1, (int) $limit);
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function inactiveLearnHubCountRows(PDO $db, string $filter, string $status, string $search, int $total_days): int {
    [$sql, $params] = inactiveLearnHubBuildQuery($filter, $status, $search, $total_days, true);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function inactiveLearnHubExportableRows(array $rows): array {
    return array_values(array_filter($rows, static function (array $row): bool {
        $email = trim((string) ($row['email'] ?? ''));
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
    }));
}

function inactiveLearnHubExportFilename(string $filter, string $extension): string {
    $slug = $filter === 'not_started' ? 'not-started' : str_replace('_', '-', $filter);
    return 'coinrex-learnhub-inactive-' . $slug . '-' . date('Y-m-d') . '.' . $extension;
}

function inactiveLearnHubStreamTxt(array $rows, string $filter): void {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . inactiveLearnHubExportFilename($filter, 'txt') . '"');
    header('X-Content-Type-Options: nosniff');
    foreach (inactiveLearnHubExportableRows($rows) as $row) {
        echo trim((string) $row['email']) . "\r\n";
    }
    exit;
}

function inactiveLearnHubStreamXls(array $rows, string $filter, int $total_days): void {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . inactiveLearnHubExportFilename($filter, 'xls') . '"');
    header('X-Content-Type-Options: nosniff');
    echo "<table border=\"1\">\n";
    echo "<thead><tr><th>Email</th><th>Username</th><th>Full Name</th><th>Status</th><th>LearnHub Stage</th><th>Completed Days</th><th>Last Active</th><th>Registered Date</th></tr></thead>\n<tbody>\n";
    foreach (inactiveLearnHubExportableRows($rows) as $row) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $row['username'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $row['full_name'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) $row['status'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars(inactiveLearnHubStageLabel($row, $total_days), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . number_format((int) ($row['completed_days'] ?? 0)) . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['last_active'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
        echo "</tr>\n";
    }
    echo "</tbody>\n</table>";
    exit;
}

if ($is_export_request) {
    $export_rows = inactiveLearnHubFetchRows($db, $learnhub_filter, $status_filter, $search, $total_days);
    if ($export_format === 'txt') {
        inactiveLearnHubStreamTxt($export_rows, $learnhub_filter);
    }
    inactiveLearnHubStreamXls($export_rows, $learnhub_filter, $total_days);
}

$total_matches = inactiveLearnHubCountRows($db, $learnhub_filter, $status_filter, $search, $total_days);
$preview_rows = inactiveLearnHubFetchRows($db, $learnhub_filter, $status_filter, $search, $total_days, 100);
$exportable_count = count(inactiveLearnHubExportableRows($preview_rows));
if ($total_matches > count($preview_rows)) {
    $all_export_rows = inactiveLearnHubFetchRows($db, $learnhub_filter, $status_filter, $search, $total_days);
    $exportable_count = count(inactiveLearnHubExportableRows($all_export_rows));
}

$base_params = [
    'learnhub' => $learnhub_filter,
    'status' => $status_filter,
];
if ($search !== '') {
    $base_params['q'] = $search;
}
$txt_url = ADMIN_BASE_URL . '/inactive-learnhub-users.php?' . http_build_query(array_merge($base_params, ['export' => 'txt']));
$xls_url = ADMIN_BASE_URL . '/inactive-learnhub-users.php?' . http_build_query(array_merge($base_params, ['export' => 'xls']));
?>

<div class="dashboard-header">
    <div class="dashboard-header-left">
        <div class="dashboard-header-icon"><i class="fas fa-envelope-open-text"></i></div>
        <div class="dashboard-header-text">
            <h1>Inactive Users Export</h1>
            <p>Extract LearnHub-specific inactive user emails for targeted outreach.</p>
        </div>
    </div>
    <span class="dashboard-header-badge"><i class="fas fa-lock"></i> Admin Only</span>
</div>

<div class="dashboard-metric-grid inactive-export-summary">
    <div class="dashboard-metric-card">
        <div class="metric-top"><span class="metric-icon is-blue"><i class="fas fa-users"></i></span></div>
        <strong class="metric-value"><?php echo number_format($total_matches); ?></strong>
        <span class="metric-label">Matching Users</span>
    </div>
    <div class="dashboard-metric-card">
        <div class="metric-top"><span class="metric-icon is-green"><i class="fas fa-at"></i></span></div>
        <strong class="metric-value"><?php echo number_format($exportable_count); ?></strong>
        <span class="metric-label">Exportable Emails</span>
    </div>
    <div class="dashboard-metric-card">
        <div class="metric-top"><span class="metric-icon is-gold"><i class="fas fa-filter"></i></span></div>
        <strong class="metric-value inactive-export-label"><?php echo htmlspecialchars(inactiveLearnHubFilterLabel($learnhub_filter), ENT_QUOTES, 'UTF-8'); ?></strong>
        <span class="metric-label">Selected Filter</span>
    </div>
    <div class="dashboard-metric-card">
        <div class="metric-top"><span class="metric-icon is-purple"><i class="fas fa-clock"></i></span></div>
        <strong class="metric-value inactive-export-label"><?php echo htmlspecialchars(date('M j, H:i'), ENT_QUOTES, 'UTF-8'); ?></strong>
        <span class="metric-label">Generated</span>
    </div>
</div>

<div class="dashboard-panel inactive-export-panel">
    <div class="dashboard-panel-header">
        <h3><i class="fas fa-sliders"></i> Filters</h3>
        <span class="panel-badge">LearnHub stage</span>
    </div>
    <form method="GET" class="inactive-export-filters">
        <label>
            <span>LearnHub Filter</span>
            <select name="learnhub">
                <option value="not_started" <?php echo $learnhub_filter === 'not_started' ? 'selected' : ''; ?>>Not Started LearnHub</option>
                <?php for ($day = 1; $day <= $total_days; $day++):
                    $value = 'day_' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                ?>
                    <option value="<?php echo $value; ?>" <?php echo $learnhub_filter === $value ? 'selected' : ''; ?>>Day <?php echo str_pad((string) $day, 2, '0', STR_PAD_LEFT); ?></option>
                <?php endfor; ?>
            </select>
        </label>
        <label>
            <span>Status</span>
            <select name="status">
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
            </select>
        </label>
        <label>
            <span>Search</span>
            <input type="text" name="q" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Name, username, email">
        </label>
        <div class="inactive-export-actions">
            <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Apply</button>
            <a href="<?php echo ADMIN_BASE_URL; ?>/inactive-learnhub-users.php" class="btn btn-secondary"><i class="fas fa-xmark"></i> Clear</a>
            <a href="<?php echo htmlspecialchars($txt_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-file-lines"></i> Export TXT</a>
            <a href="<?php echo htmlspecialchars($xls_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-file-excel"></i> Export XLS</a>
        </div>
    </form>
</div>

<div class="dashboard-panel inactive-export-panel">
    <div class="dashboard-panel-header">
        <h3><i class="fas fa-table"></i> Preview</h3>
        <span class="panel-badge">First <?php echo number_format(count($preview_rows)); ?> rows</span>
    </div>
    <div class="dashboard-table-wrap">
        <table class="dashboard-table inactive-export-table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>LearnHub Stage</th>
                    <th>Completed Days</th>
                    <th>Last Active</th>
                    <th>Registered</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($preview_rows)): ?>
                    <tr><td colspan="7" class="dashboard-empty"><i class="fas fa-inbox"></i>No users found for this filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($preview_rows as $row): ?>
                        <tr>
                            <td data-label="Email"><?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="User">
                                <strong><?php echo htmlspecialchars((string) ($row['username'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <span class="muted"><?php echo htmlspecialchars((string) ($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td data-label="Status"><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="LearnHub Stage"><?php echo htmlspecialchars(inactiveLearnHubStageLabel($row, $total_days), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Completed Days"><?php echo number_format((int) ($row['completed_days'] ?? 0)); ?>/<?php echo number_format($total_days); ?></td>
                            <td data-label="Last Active"><?php echo htmlspecialchars((string) ($row['last_active'] ?? 'Never'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Registered"><?php echo htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
