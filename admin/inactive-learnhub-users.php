<?php
$page_title = 'User Outreach Export';
$activePage = 'inactive-learnhub-users';
$export_format = strtolower(trim((string) ($_GET['export'] ?? '')));
$is_export_request = in_array($export_format, ['txt', 'xls', 'xlsx'], true);

if ($is_export_request) {
    require_once __DIR__ . '/includes/config.php';
    requireAdminAuth();
    requireAdminPageAccess($activePage);
} else {
    require_once __DIR__ . '/includes/header.php';
}

$db = getDBConnection();
$total_days = defined('TASKHUB_TOTAL_DAYS') ? (int) TASKHUB_TOTAL_DAYS : 10;
$segments = outreachExportSegments();
$segment = outreachExportNormalizeSegment((string) ($_GET['segment'] ?? 'learnhub_inactive'), $segments);
$inactive_days = outreachExportNormalizeInactiveDays((string) ($_GET['inactive_window'] ?? '3'), (string) ($_GET['inactive_custom'] ?? ''));
$registered_window = outreachExportNormalizeRegisteredWindow((string) ($_GET['registered_window'] ?? 'all'));
$registered_from = outreachExportNormalizeDate((string) ($_GET['registered_from'] ?? ''));
$registered_to = outreachExportNormalizeDate((string) ($_GET['registered_to'] ?? ''));
$learnhub_filter = outreachExportNormalizeLearnHubFilter((string) ($_GET['learnhub'] ?? 'not_started'), $total_days);
$level_filter = outreachExportNormalizeLevel((string) ($_GET['level'] ?? $_GET['user_level'] ?? 'all'));
$status_filter = outreachExportNormalizeStatus((string) ($_GET['status'] ?? 'active'));
$activity_filter = outreachExportNormalizeActivity((string) ($_GET['activity'] ?? 'any'));
$email_verified_filter = outreachExportNormalizeTernary((string) ($_GET['email_verified'] ?? 'all'));
$wallet_verified_filter = outreachExportNormalizeTernary((string) ($_GET['wallet_verified'] ?? 'all'));
$search = trim((string) ($_GET['q'] ?? ''));

function outreachExportSegments(): array {
    return [
        'all_valid_email' => [
            'label' => 'All Users With Valid Email',
            'description' => 'Every account with an exportable email address.',
            'icon' => 'fas fa-address-book',
        ],
        'new_never_active' => [
            'label' => 'Newly Registered, Never Active',
            'description' => 'Users who signed up, passed the grace window, and never returned.',
            'icon' => 'fas fa-user-clock',
        ],
        'registered_inactive' => [
            'label' => 'Registered But Inactive',
            'description' => 'Users whose latest known activity is outside the selected window.',
            'icon' => 'fas fa-user-slash',
        ],
        'learnhub_inactive' => [
            'label' => 'LearnHub Inactive By Stage',
            'description' => 'Users stuck at a selected LearnHub stage and inactive.',
            'icon' => 'fas fa-graduation-cap',
        ],
        'day10_beginner_inactive' => [
            'label' => 'Day 10 Inactive: Beginner',
            'description' => 'Beginner users sitting on Day 10 and inactive.',
            'icon' => 'fas fa-seedling',
        ],
        'day10_pro_inactive' => [
            'label' => 'Day 10 Inactive: PRO',
            'description' => 'PRO users sitting on Day 10 and inactive.',
            'icon' => 'fas fa-gem',
        ],
        'pro_inactive' => [
            'label' => 'PRO Inactive',
            'description' => 'PRO users who left the platform for the selected window.',
            'icon' => 'fas fa-gem',
        ],
        'expert_inactive' => [
            'label' => 'Expert Inactive',
            'description' => 'Expert users who left the platform for the selected window.',
            'icon' => 'fas fa-crown',
        ],
        'unverified_email' => [
            'label' => 'Unverified Email Users',
            'description' => 'Accounts that still need email verification.',
            'icon' => 'fas fa-envelope-circle-check',
        ],
        'wallet_not_verified' => [
            'label' => 'Wallet Not Verified',
            'description' => 'Accounts without a verified wallet.',
            'icon' => 'fas fa-wallet',
        ],
    ];
}

function outreachExportNormalizeSegment(string $value, array $segments): string {
    $value = strtolower(trim($value));
    return isset($segments[$value]) ? $value : 'learnhub_inactive';
}

function outreachExportNormalizeStatus(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['active', 'suspended', 'all'], true) ? $value : 'active';
}

function outreachExportNormalizeLevel(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['all', 'beginner', 'pro', 'expert'], true) ? $value : 'all';
}

function outreachExportNormalizeActivity(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['any', 'active_recently', 'inactive', 'never_active'], true) ? $value : 'any';
}

function outreachExportNormalizeTernary(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['all', 'yes', 'no'], true) ? $value : 'all';
}

function outreachExportNormalizeInactiveDays(string $window, string $custom): int {
    $window = strtolower(trim($window));
    if ($window === 'custom') {
        return max(1, min(3650, (int) $custom));
    }
    return in_array($window, ['3', '7', '14', '30'], true) ? (int) $window : 3;
}

function outreachExportNormalizeRegisteredWindow(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['all', 'today', '7d', '30d', 'custom'], true) ? $value : 'all';
}

function outreachExportNormalizeDate(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function outreachExportNormalizeLearnHubFilter(string $value, int $total_days): string {
    $value = strtolower(trim($value));
    if (in_array($value, ['all', 'not_started', 'completed'], true)) {
        return $value;
    }
    if (preg_match('/^day_(\d{1,2})$/', $value, $matches)) {
        $day = (int) $matches[1];
        if ($day >= 1 && $day <= $total_days) {
            return 'day_' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        }
    }
    return 'not_started';
}

function outreachExportIsFinalDayFilter(string $filter, int $total_days): bool {
    return $filter === 'day_' . str_pad((string) $total_days, 2, '0', STR_PAD_LEFT);
}

function outreachExportLearnHubLabel(string $filter): string {
    if ($filter === 'all') {
        return 'All LearnHub Stages';
    }
    if ($filter === 'not_started') {
        return 'Not Started LearnHub';
    }
    if ($filter === 'completed') {
        return 'Completed';
    }
    $day = (int) substr($filter, 4);
    return 'Day ' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
}

function outreachExportStageLabel(array $row, int $total_days): string {
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

function outreachExportLevelKey($level): string {
    $level = strtolower(trim((string) $level));
    if ($level === 'premium') {
        return 'pro';
    }
    return in_array($level, ['beginner', 'pro', 'expert'], true) ? $level : 'beginner';
}

function outreachExportLevelLabel($level): string {
    $level = outreachExportLevelKey($level);
    return $level === 'pro' ? 'PRO' : ucfirst($level);
}

function outreachExportYesNo($value): string {
    return !empty($value) ? 'Yes' : 'No';
}

function outreachExportSegmentLabel(string $segment, array $segments): string {
    return (string) ($segments[$segment]['label'] ?? 'User Outreach');
}

function outreachExportSegmentSlug(string $segment): string {
    return str_replace('_', '-', strtolower(trim($segment)));
}

function outreachExportShouldUseLearnHub(string $segment): bool {
    return in_array($segment, ['learnhub_inactive', 'day10_beginner_inactive', 'day10_pro_inactive'], true);
}

function outreachExportAppendValidEmailCondition(string &$sql): void {
    $sql .= " AND TRIM(COALESCE(u.email, '')) <> '' AND u.email LIKE '%@%.%' ";
}

function outreachExportApplyLevelFilter(string &$sql, string $level): void {
    if ($level === 'beginner') {
        $sql .= " AND LOWER(TRIM(COALESCE(u.level, 'beginner'))) = 'beginner' ";
    } elseif ($level === 'pro') {
        $sql .= " AND LOWER(TRIM(COALESCE(u.level, 'beginner'))) IN ('pro', 'premium') ";
    } elseif ($level === 'expert') {
        $sql .= " AND LOWER(TRIM(COALESCE(u.level, 'beginner'))) = 'expert' ";
    }
}

function outreachExportApplyLearnHubFilter(string &$sql, array &$params, string $learnhub_filter, int $total_days): void {
    if ($learnhub_filter === 'all') {
        return;
    }
    if ($learnhub_filter === 'not_started') {
        $sql .= " AND COALESCE(u.current_day, 1) <= 1 AND COALESCE(log_stats.completed_days, 0) = 0 ";
        return;
    }
    if ($learnhub_filter === 'completed') {
        $sql .= " AND (COALESCE(log_stats.completed_days, 0) >= ? OR COALESCE(u.current_day, 1) > ?) ";
        $params[] = $total_days;
        $params[] = $total_days;
        return;
    }
    $day = (int) substr($learnhub_filter, 4);
    $sql .= " AND COALESCE(u.current_day, 1) = ? ";
    $params[] = $day;
}

function outreachExportBuildQuery(array $filters, int $total_days, bool $count_only = false): array {
    $inactive_days = max(1, (int) ($filters['inactive_days'] ?? 3));
    $completed_expr = "GREATEST(
        COALESCE(log_stats.completed_days, 0),
        LEAST({$total_days}, GREATEST(0, COALESCE(u.current_day, 1) - 1))
    )";

    $select = $count_only
        ? "COUNT(*) AS total"
        : "u.id, u.email, u.username, u.full_name, u.status, u.level, u.current_day, u.last_active, u.last_login, u.created_at, u.email_verified, u.wallet_verified_at, {$completed_expr} AS completed_days";

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

    if (($filters['status'] ?? 'active') !== 'all') {
        $sql .= " AND u.status = ? ";
        $params[] = (string) $filters['status'];
    }

    $segment = (string) ($filters['segment'] ?? 'learnhub_inactive');
    if ($segment === 'all_valid_email') {
        outreachExportAppendValidEmailCondition($sql);
    } elseif ($segment === 'new_never_active') {
        $sql .= " AND u.last_active IS NULL AND u.created_at <= (NOW() - INTERVAL {$inactive_days} DAY) ";
    } elseif ($segment === 'registered_inactive') {
        $sql .= " AND u.created_at <= (NOW() - INTERVAL {$inactive_days} DAY) AND (u.last_active IS NULL OR u.last_active <= (NOW() - INTERVAL {$inactive_days} DAY)) ";
    } elseif ($segment === 'learnhub_inactive') {
        $sql .= " AND COALESCE(u.last_active, u.created_at) <= (NOW() - INTERVAL {$inactive_days} DAY) ";
        outreachExportApplyLearnHubFilter($sql, $params, (string) $filters['learnhub'], $total_days);
    } elseif ($segment === 'day10_beginner_inactive') {
        $sql .= " AND COALESCE(u.last_active, u.created_at) <= (NOW() - INTERVAL {$inactive_days} DAY) ";
        outreachExportApplyLearnHubFilter($sql, $params, 'day_' . str_pad((string) $total_days, 2, '0', STR_PAD_LEFT), $total_days);
        outreachExportApplyLevelFilter($sql, 'beginner');
    } elseif ($segment === 'day10_pro_inactive') {
        $sql .= " AND COALESCE(u.last_active, u.created_at) <= (NOW() - INTERVAL {$inactive_days} DAY) ";
        outreachExportApplyLearnHubFilter($sql, $params, 'day_' . str_pad((string) $total_days, 2, '0', STR_PAD_LEFT), $total_days);
        outreachExportApplyLevelFilter($sql, 'pro');
    } elseif ($segment === 'pro_inactive') {
        $sql .= " AND COALESCE(u.last_active, u.created_at) <= (NOW() - INTERVAL {$inactive_days} DAY) ";
        outreachExportApplyLevelFilter($sql, 'pro');
    } elseif ($segment === 'expert_inactive') {
        $sql .= " AND COALESCE(u.last_active, u.created_at) <= (NOW() - INTERVAL {$inactive_days} DAY) ";
        outreachExportApplyLevelFilter($sql, 'expert');
    } elseif ($segment === 'unverified_email') {
        $sql .= " AND COALESCE(u.email_verified, 0) = 0 ";
    } elseif ($segment === 'wallet_not_verified') {
        $sql .= " AND u.wallet_verified_at IS NULL ";
    }

    $level_filter = (string) ($filters['level'] ?? 'all');
    if ($level_filter !== 'all' && !in_array($segment, ['day10_beginner_inactive', 'day10_pro_inactive', 'pro_inactive', 'expert_inactive'], true)) {
        outreachExportApplyLevelFilter($sql, $level_filter);
    }

    $activity = (string) ($filters['activity'] ?? 'any');
    if ($activity === 'active_recently') {
        $sql .= " AND u.last_active IS NOT NULL AND u.last_active >= (NOW() - INTERVAL {$inactive_days} DAY) ";
    } elseif ($activity === 'inactive') {
        $sql .= " AND COALESCE(u.last_active, u.created_at) <= (NOW() - INTERVAL {$inactive_days} DAY) ";
    } elseif ($activity === 'never_active') {
        $sql .= " AND u.last_active IS NULL ";
    }

    $registered_window = (string) ($filters['registered_window'] ?? 'all');
    if ($registered_window === 'today') {
        $sql .= " AND u.created_at >= CURDATE() ";
    } elseif ($registered_window === '7d') {
        $sql .= " AND u.created_at >= (NOW() - INTERVAL 7 DAY) ";
    } elseif ($registered_window === '30d') {
        $sql .= " AND u.created_at >= (NOW() - INTERVAL 30 DAY) ";
    } elseif ($registered_window === 'custom') {
        if ((string) ($filters['registered_from'] ?? '') !== '') {
            $sql .= " AND u.created_at >= ? ";
            $params[] = (string) $filters['registered_from'] . ' 00:00:00';
        }
        if ((string) ($filters['registered_to'] ?? '') !== '') {
            $sql .= " AND u.created_at <= ? ";
            $params[] = (string) $filters['registered_to'] . ' 23:59:59';
        }
    }

    if (!outreachExportShouldUseLearnHub($segment) && (string) ($filters['learnhub'] ?? 'all') !== 'all') {
        outreachExportApplyLearnHubFilter($sql, $params, (string) $filters['learnhub'], $total_days);
    }

    if (($filters['email_verified'] ?? 'all') === 'yes') {
        $sql .= " AND COALESCE(u.email_verified, 0) = 1 ";
    } elseif (($filters['email_verified'] ?? 'all') === 'no') {
        $sql .= " AND COALESCE(u.email_verified, 0) = 0 ";
    }

    if (($filters['wallet_verified'] ?? 'all') === 'yes') {
        $sql .= " AND u.wallet_verified_at IS NOT NULL ";
    } elseif (($filters['wallet_verified'] ?? 'all') === 'no') {
        $sql .= " AND u.wallet_verified_at IS NULL ";
    }

    $search = trim((string) ($filters['search'] ?? ''));
    if ($search !== '') {
        $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?) ";
        $needle = '%' . $search . '%';
        $params[] = $needle;
        $params[] = $needle;
        $params[] = $needle;
    }

    if (!$count_only) {
        $sql .= " ORDER BY u.id DESC ";
    }

    return [$sql, $params];
}

function outreachExportFetchRows(PDO $db, array $filters, int $total_days, ?int $limit = null): array {
    [$sql, $params] = outreachExportBuildQuery($filters, $total_days, false);
    if ($limit !== null) {
        $sql .= " LIMIT " . max(1, (int) $limit);
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function outreachExportCountRows(PDO $db, array $filters, int $total_days): int {
    [$sql, $params] = outreachExportBuildQuery($filters, $total_days, true);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function outreachExportRowsWithEmail(array $rows): array {
    return array_values(array_filter($rows, static function (array $row): bool {
        $email = trim((string) ($row['email'] ?? ''));
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
    }));
}

function outreachExportFilename(string $segment, string $extension): string {
    return 'coinrex-outreach-' . outreachExportSegmentSlug($segment) . '-' . date('Y-m-d') . '.' . $extension;
}

function outreachExportBaseParams(array $filters): array {
    $params = [
        'segment' => $filters['segment'],
        'status' => $filters['status'],
        'level' => $filters['level'],
        'activity' => $filters['activity'],
        'inactive_window' => $filters['inactive_window'],
        'registered_window' => $filters['registered_window'],
        'learnhub' => $filters['learnhub'],
        'email_verified' => $filters['email_verified'],
        'wallet_verified' => $filters['wallet_verified'],
    ];
    foreach (['inactive_custom', 'registered_from', 'registered_to', 'q'] as $key) {
        if ((string) ($filters[$key] ?? '') !== '') {
            $params[$key] = $filters[$key];
        }
    }
    return $params;
}

function outreachExportStreamTxt(array $rows, string $segment): void {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . outreachExportFilename($segment, 'txt') . '"');
    header('X-Content-Type-Options: nosniff');
    foreach (outreachExportRowsWithEmail($rows) as $row) {
        $email = trim((string) ($row['email'] ?? ''));
        $username = trim((string) ($row['username'] ?? ''));
        $full_name = trim((string) ($row['full_name'] ?? ''));
        echo $email . ' | ' . $username . ' | ' . $full_name . "\r\n";
    }
    exit;
}

function outreachExportStreamXls(array $rows, string $segment, array $segments, int $total_days): void {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . outreachExportFilename($segment, 'xls') . '"');
    header('X-Content-Type-Options: nosniff');
    $headers = ['Email', 'Username', 'Full Name', 'Status', 'Level', 'Segment', 'LearnHub Stage', 'Completed Days', 'Last Active', 'Last Login', 'Registered Date', 'Email Verified', 'Wallet Verified'];
    echo "<table border=\"1\">\n<thead><tr>";
    foreach ($headers as $header) {
        echo '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo "</tr></thead>\n<tbody>\n";
    foreach (outreachExportRowsWithEmail($rows) as $row) {
        $cells = outreachExportRowCells($row, $segment, $segments, $total_days);
        echo '<tr>';
        foreach ($cells as $cell) {
            echo '<td>' . htmlspecialchars((string) $cell, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo "</tr>\n";
    }
    echo "</tbody>\n</table>";
    exit;
}

function outreachExportXml($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function outreachExportExcelColumn(int $index): string {
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function outreachExportRowCells(array $row, string $segment, array $segments, int $total_days): array {
    return [
        (string) ($row['email'] ?? ''),
        (string) ($row['username'] ?? ''),
        (string) ($row['full_name'] ?? ''),
        (string) ($row['status'] ?? ''),
        outreachExportLevelLabel($row['level'] ?? 'beginner'),
        outreachExportSegmentLabel($segment, $segments),
        outreachExportStageLabel($row, $total_days),
        (string) ((int) ($row['completed_days'] ?? 0)),
        (string) ($row['last_active'] ?? ''),
        (string) ($row['last_login'] ?? ''),
        (string) ($row['created_at'] ?? ''),
        outreachExportYesNo((int) ($row['email_verified'] ?? 0)),
        outreachExportYesNo((string) ($row['wallet_verified_at'] ?? '') !== ''),
    ];
}

function outreachExportStreamXlsx(array $rows, string $segment, array $segments, int $total_days): void {
    if (!class_exists('ZipArchive')) {
        outreachExportStreamXls($rows, $segment, $segments, $total_days);
    }

    $sheet_rows = [[
        'Email',
        'Username',
        'Full Name',
        'Status',
        'Level',
        'Segment',
        'LearnHub Stage',
        'Completed Days',
        'Last Active',
        'Last Login',
        'Registered Date',
        'Email Verified',
        'Wallet Verified',
    ]];
    foreach (outreachExportRowsWithEmail($rows) as $row) {
        $sheet_rows[] = outreachExportRowCells($row, $segment, $segments, $total_days);
    }

    $sheet_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<sheetData>';
    foreach ($sheet_rows as $row_index => $row) {
        $excel_row = $row_index + 1;
        $sheet_xml .= '<row r="' . $excel_row . '">';
        foreach ($row as $col_index => $value) {
            $cell_ref = outreachExportExcelColumn($col_index + 1) . $excel_row;
            $sheet_xml .= '<c r="' . $cell_ref . '" t="inlineStr"><is><t>' . outreachExportXml($value) . '</t></is></c>';
        }
        $sheet_xml .= '</row>';
    }
    $sheet_xml .= '</sheetData></worksheet>';

    $tmp = tempnam(sys_get_temp_dir(), 'coinrex_xlsx_');
    $zip = new ZipArchive();
    if ($tmp === false || $zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        outreachExportStreamXls($rows, $segment, $segments, $total_days);
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="User Outreach" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet_xml);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . outreachExportFilename($segment, 'xlsx') . '"');
    header('Content-Length: ' . filesize($tmp));
    header('X-Content-Type-Options: nosniff');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

$filters = [
    'segment' => $segment,
    'status' => $status_filter,
    'level' => $level_filter,
    'activity' => $activity_filter,
    'inactive_days' => $inactive_days,
    'inactive_window' => (string) ($_GET['inactive_window'] ?? '3'),
    'inactive_custom' => (string) ($_GET['inactive_custom'] ?? ''),
    'registered_window' => $registered_window,
    'registered_from' => $registered_from,
    'registered_to' => $registered_to,
    'learnhub' => $learnhub_filter,
    'email_verified' => $email_verified_filter,
    'wallet_verified' => $wallet_verified_filter,
    'search' => $search,
    'q' => $search,
];

if ($is_export_request) {
    $export_rows = outreachExportFetchRows($db, $filters, $total_days);
    if ($export_format === 'txt') {
        outreachExportStreamTxt($export_rows, $segment);
    }
    if ($export_format === 'xlsx') {
        outreachExportStreamXlsx($export_rows, $segment, $segments, $total_days);
    }
    outreachExportStreamXls($export_rows, $segment, $segments, $total_days);
}

$total_matches = outreachExportCountRows($db, $filters, $total_days);
$preview_rows = outreachExportFetchRows($db, $filters, $total_days, 100);
$exportable_count = count(outreachExportRowsWithEmail($preview_rows));
if ($total_matches > count($preview_rows)) {
    $all_export_rows = outreachExportFetchRows($db, $filters, $total_days);
    $exportable_count = count(outreachExportRowsWithEmail($all_export_rows));
}

$base_params = outreachExportBaseParams($filters);
$txt_url = ADMIN_BASE_URL . '/inactive-learnhub-users.php?' . http_build_query(array_merge($base_params, ['export' => 'txt']));
$xlsx_url = ADMIN_BASE_URL . '/inactive-learnhub-users.php?' . http_build_query(array_merge($base_params, ['export' => 'xlsx']));
$active_segment = $segments[$segment] ?? $segments['learnhub_inactive'];
?>

<div class="dashboard-header">
    <div class="dashboard-header-left">
        <div class="dashboard-header-icon"><i class="fas fa-bullhorn"></i></div>
        <div class="dashboard-header-text">
            <h1>User Outreach Export</h1>
            <p>Build targeted user audiences and export outreach-ready email lists.</p>
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
        <div class="metric-top"><span class="metric-icon is-gold"><i class="<?php echo htmlspecialchars((string) ($active_segment['icon'] ?? 'fas fa-filter'), ENT_QUOTES, 'UTF-8'); ?>"></i></span></div>
        <strong class="metric-value inactive-export-label"><?php echo htmlspecialchars((string) ($active_segment['label'] ?? 'User Outreach'), ENT_QUOTES, 'UTF-8'); ?></strong>
        <span class="metric-label">Selected Segment</span>
    </div>
    <div class="dashboard-metric-card">
        <div class="metric-top"><span class="metric-icon is-purple"><i class="fas fa-clock"></i></span></div>
        <strong class="metric-value inactive-export-label"><?php echo number_format($inactive_days); ?> days</strong>
        <span class="metric-label">Activity Window</span>
    </div>
</div>

<div class="dashboard-panel inactive-export-panel">
    <div class="dashboard-panel-header">
        <h3><i class="fas fa-sliders"></i> Audience Controls</h3>
        <span class="panel-badge"><?php echo htmlspecialchars((string) ($active_segment['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <form method="GET" class="inactive-export-filters">
        <label>
            <span>Segment</span>
            <select name="segment">
                <?php foreach ($segments as $segment_key => $segment_config): ?>
                    <option value="<?php echo htmlspecialchars($segment_key, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $segment === $segment_key ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) $segment_config['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
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
            <span>Level</span>
            <select name="level">
                <option value="all" <?php echo $level_filter === 'all' ? 'selected' : ''; ?>>All Levels</option>
                <option value="beginner" <?php echo $level_filter === 'beginner' ? 'selected' : ''; ?>>Beginner</option>
                <option value="pro" <?php echo $level_filter === 'pro' ? 'selected' : ''; ?>>PRO</option>
                <option value="expert" <?php echo $level_filter === 'expert' ? 'selected' : ''; ?>>Expert</option>
            </select>
        </label>
        <label>
            <span>Activity State</span>
            <select name="activity">
                <option value="any" <?php echo $activity_filter === 'any' ? 'selected' : ''; ?>>Any</option>
                <option value="active_recently" <?php echo $activity_filter === 'active_recently' ? 'selected' : ''; ?>>Active Recently</option>
                <option value="inactive" <?php echo $activity_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="never_active" <?php echo $activity_filter === 'never_active' ? 'selected' : ''; ?>>Never Active</option>
            </select>
        </label>
        <label>
            <span>Inactive Window</span>
            <select name="inactive_window">
                <?php foreach (['3', '7', '14', '30'] as $window): ?>
                    <option value="<?php echo $window; ?>" <?php echo (string) ($_GET['inactive_window'] ?? '3') === $window ? 'selected' : ''; ?>><?php echo $window; ?> days</option>
                <?php endforeach; ?>
                <option value="custom" <?php echo (string) ($_GET['inactive_window'] ?? '3') === 'custom' ? 'selected' : ''; ?>>Custom</option>
            </select>
        </label>
        <label>
            <span>Custom Days</span>
            <input type="number" name="inactive_custom" min="1" max="3650" value="<?php echo htmlspecialchars((string) ($_GET['inactive_custom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. 45">
        </label>
        <label>
            <span>Registered Window</span>
            <select name="registered_window">
                <option value="all" <?php echo $registered_window === 'all' ? 'selected' : ''; ?>>All Time</option>
                <option value="today" <?php echo $registered_window === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="7d" <?php echo $registered_window === '7d' ? 'selected' : ''; ?>>Last 7 Days</option>
                <option value="30d" <?php echo $registered_window === '30d' ? 'selected' : ''; ?>>Last 30 Days</option>
                <option value="custom" <?php echo $registered_window === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
            </select>
        </label>
        <label>
            <span>Registered From</span>
            <input type="date" name="registered_from" value="<?php echo htmlspecialchars($registered_from, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            <span>Registered To</span>
            <input type="date" name="registered_to" value="<?php echo htmlspecialchars($registered_to, ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <label>
            <span>LearnHub Stage</span>
            <select name="learnhub">
                <option value="all" <?php echo $learnhub_filter === 'all' ? 'selected' : ''; ?>>All Stages</option>
                <option value="not_started" <?php echo $learnhub_filter === 'not_started' ? 'selected' : ''; ?>>Not Started LearnHub</option>
                <?php for ($day = 1; $day <= $total_days; $day++):
                    $value = 'day_' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                ?>
                    <option value="<?php echo $value; ?>" <?php echo $learnhub_filter === $value ? 'selected' : ''; ?>>Day <?php echo str_pad((string) $day, 2, '0', STR_PAD_LEFT); ?></option>
                <?php endfor; ?>
                <option value="completed" <?php echo $learnhub_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
            </select>
        </label>
        <label>
            <span>Email Verified</span>
            <select name="email_verified">
                <option value="all" <?php echo $email_verified_filter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="yes" <?php echo $email_verified_filter === 'yes' ? 'selected' : ''; ?>>Verified</option>
                <option value="no" <?php echo $email_verified_filter === 'no' ? 'selected' : ''; ?>>Unverified</option>
            </select>
        </label>
        <label>
            <span>Wallet Verified</span>
            <select name="wallet_verified">
                <option value="all" <?php echo $wallet_verified_filter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="yes" <?php echo $wallet_verified_filter === 'yes' ? 'selected' : ''; ?>>Verified</option>
                <option value="no" <?php echo $wallet_verified_filter === 'no' ? 'selected' : ''; ?>>Not Verified</option>
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
            <a href="<?php echo htmlspecialchars($xlsx_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary"><i class="fas fa-file-excel"></i> Export XLSX</a>
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
                    <th>Level</th>
                    <th>LearnHub Stage</th>
                    <th>Last Active</th>
                    <th>Registered</th>
                    <th>Signals</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($preview_rows)): ?>
                    <tr><td colspan="8" class="dashboard-empty"><i class="fas fa-inbox"></i>No users found for this audience.</td></tr>
                <?php else: ?>
                    <?php foreach ($preview_rows as $row): ?>
                        <tr>
                            <td data-label="Email"><?php echo htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="User">
                                <strong><?php echo htmlspecialchars((string) ($row['username'] ?? 'User'), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <span class="muted"><?php echo htmlspecialchars((string) ($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td data-label="Status"><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Level"><?php echo htmlspecialchars(outreachExportLevelLabel($row['level'] ?? 'beginner'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="LearnHub Stage">
                                <?php echo htmlspecialchars(outreachExportStageLabel($row, $total_days), ENT_QUOTES, 'UTF-8'); ?><br>
                                <span class="muted"><?php echo number_format((int) ($row['completed_days'] ?? 0)); ?>/<?php echo number_format($total_days); ?> days</span>
                            </td>
                            <td data-label="Last Active"><?php echo htmlspecialchars((string) ($row['last_active'] ?? 'Never'), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Registered"><?php echo htmlspecialchars((string) ($row['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="Signals">
                                <span class="muted">Email: <?php echo htmlspecialchars(outreachExportYesNo((int) ($row['email_verified'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></span><br>
                                <span class="muted">Wallet: <?php echo htmlspecialchars(outreachExportYesNo((string) ($row['wallet_verified_at'] ?? '') !== ''), ENT_QUOTES, 'UTF-8'); ?></span><br>
                                <span class="muted">Login: <?php echo htmlspecialchars((string) ($row['last_login'] ?? 'Never'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
