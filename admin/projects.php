<?php
$page_title = 'Projects';
$activePage = 'projects';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/pagination.php';

$db = getDBConnection();
ensureReviewEligibilitySchema($db);
$current_admin = getCurrentAdmin();
$message = '';
$message_type = 'success';
$status_filter = trim((string) ($_GET['status'] ?? 'pending'));
$search = trim((string) ($_GET['q'] ?? ''));
$valid_filters = ['pending', 'under_review', 'approved', 'rejected', 'flagged', 'all'];
if (!in_array($status_filter, $valid_filters, true)) {
    $status_filter = 'pending';
}

$perPage = 20;
$page = paginationGetPage('page', 1);
$offset = ($page - 1) * $perPage;

$feature_rating_threshold = (float) FEATURE_MIN_AVG_RATING;
$feature_review_threshold = (int) FEATURE_MIN_APPROVED_REVIEWS;
$has_featured_column = tableHasColumn('projects', 'is_featured');
$has_feature_status_column = tableHasColumn('projects', 'feature_status');
$has_feature_requested_at_column = tableHasColumn('projects', 'feature_requested_at');
$has_feature_reviewed_at_column = tableHasColumn('projects', 'feature_reviewed_at');
$has_feature_reviewed_by_column = tableHasColumn('projects', 'feature_reviewed_by');
$has_featured_at_column = tableHasColumn('projects', 'featured_at');
$has_feature_queue_type_column = tableHasColumn('projects', 'feature_queue_type');
$has_is_sponsored_column = tableHasColumn('projects', 'is_sponsored');
$has_sponsored_status_column = tableHasColumn('projects', 'sponsored_status');
$has_priority_review_status_column = tableHasColumn('projects', 'priority_review_status');
$has_priority_review_requested_at_column = tableHasColumn('projects', 'priority_review_requested_at');
$has_priority_review_paid_at_column = tableHasColumn('projects', 'priority_review_paid_at');
$has_sponsored_requested_at_column = tableHasColumn('projects', 'sponsored_requested_at');
$has_sponsored_starts_at_column = tableHasColumn('projects', 'sponsored_starts_at');
$has_sponsored_ends_at_column = tableHasColumn('projects', 'sponsored_ends_at');
$has_content_flags_table = tableExists('content_flags');

$project_flags_join = $has_content_flags_table ? "
    LEFT JOIN (
        SELECT target_id, 1 AS has_open_flag
        FROM content_flags
        WHERE target_type = 'project'
          AND status = 'open'
        GROUP BY target_id
    ) project_flags ON project_flags.target_id = p.id
" : '';

$moderation_status_sql = $has_content_flags_table
    ? "CASE WHEN COALESCE(project_flags.has_open_flag, 0) = 1 THEN 'flagged' ELSE LOWER(COALESCE(NULLIF(TRIM(p.approval_status), ''), 'pending')) END"
    : "LOWER(COALESCE(NULLIF(TRIM(p.approval_status), ''), 'pending'))";

$feature_status_select = $has_feature_status_column
    ? "LOWER(COALESCE(NULLIF(TRIM(p.feature_status), ''), 'none'))"
    : "'none'";
$feature_requested_select = $has_feature_requested_at_column ? 'p.feature_requested_at' : 'NULL';
$feature_reviewed_at_select = $has_feature_reviewed_at_column ? 'p.feature_reviewed_at' : 'NULL';
$feature_reviewed_by_select = $has_feature_reviewed_by_column ? 'p.feature_reviewed_by' : 'NULL';
$meets_feature_criteria_sql = "
    CASE
        WHEN LOWER(COALESCE(NULLIF(TRIM(p.approval_status), ''), 'pending')) = 'approved'
         AND COALESCE(p.avg_rating, 0) >= {$feature_rating_threshold}
         AND COALESCE(p.total_reviews, 0) >= {$feature_review_threshold}
        THEN 1
        ELSE 0
    END
";

if ($has_feature_status_column) {
    $sync_pending_review_sql = "
        UPDATE projects
        SET feature_status = 'eligible',
            " . ($has_feature_queue_type_column ? "feature_queue_type = NULL," : '') . "
            " . ($has_feature_requested_at_column ? "feature_requested_at = COALESCE(feature_requested_at, NOW())," : '') . "
            updated_at = NOW()
        WHERE LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) = 'approved'
          AND COALESCE(avg_rating, 0) >= ?
          AND COALESCE(total_reviews, 0) >= ?
          AND LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) = 'none'
          AND COALESCE(is_featured, 0) = 0
    ";
    $sync_stmt = $db->prepare($sync_pending_review_sql);
    $sync_stmt->execute([$feature_rating_threshold, $feature_review_threshold]);

    $reset_parts = ["feature_status = 'none'"];
    if ($has_feature_requested_at_column) {
        $reset_parts[] = 'feature_requested_at = NULL';
    }
    if ($has_feature_reviewed_at_column) {
        $reset_parts[] = 'feature_reviewed_at = NULL';
    }
    if ($has_feature_reviewed_by_column) {
        $reset_parts[] = 'feature_reviewed_by = NULL';
    }
    if ($has_featured_at_column) {
        $reset_parts[] = 'featured_at = NULL';
    }
    $reset_parts[] = 'updated_at = NOW()';

    $reset_pending_review_sql = "
        UPDATE projects
        SET " . implode(",\n            ", $reset_parts) . "
        WHERE LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) IN ('eligible', 'pending_review')
          AND (
              LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) <> 'approved'
              OR COALESCE(avg_rating, 0) < ?
              OR COALESCE(total_reviews, 0) < ?
          )
    ";
    $reset_stmt = $db->prepare($reset_pending_review_sql);
    $reset_stmt->execute([$feature_rating_threshold, $feature_review_threshold]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdminCsrf((string) ($_POST['csrf_token'] ?? ''));
    $project_id = (int) ($_POST['project_id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');

    if ($project_id > 0 && in_array($action, ['approve', 'reject', 'mark_under_review', 'approve_feature', 'reject_feature', 'approve_priority_request', 'reject_priority_request', 'activate_sponsored', 'reject_sponsored', 'expire_sponsored'], true)) {
        $project_owner_id = 0;
        $project_name_for_notice = 'Project';
        $developer_name_for_notice = 'Developer';
        $project_lookup_stmt = $db->prepare("SELECT p.name, p.created_by, u.full_name, u.username FROM projects p LEFT JOIN users u ON u.id = p.created_by WHERE p.id = ? LIMIT 1");
        $project_lookup_stmt->execute([$project_id]);
        $project_lookup = $project_lookup_stmt->fetch() ?: [];
        $project_owner_id = (int) ($project_lookup['created_by'] ?? 0);
        $project_name_for_notice = trim((string) ($project_lookup['name'] ?? 'Project'));
        $developer_name_for_notice = trim((string) ($project_lookup['full_name'] ?? ''));
        if ($developer_name_for_notice === '') {
            $developer_name_for_notice = trim((string) ($project_lookup['username'] ?? 'Developer'));
        }

        $created_feature_queue_now = false;

        $had_open_project_flag = false;
        if ($has_content_flags_table) {
            $flag_check_before_stmt = $db->prepare("SELECT 1 FROM content_flags WHERE target_type = 'project' AND target_id = ? AND status = 'open' LIMIT 1");
            $flag_check_before_stmt->execute([$project_id]);
            $had_open_project_flag = (bool) $flag_check_before_stmt->fetch();
        }

        if ($action === 'approve') {
            $stmt = $db->prepare("UPDATE projects SET approval_status = 'approved', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$project_id]);

            if ($has_feature_status_column) {
                $feature_pending_stmt = $db->prepare("
                    UPDATE projects
                    SET feature_status = 'eligible',
                        " . ($has_feature_queue_type_column ? "feature_queue_type = NULL," : '') . "
                        " . ($has_feature_requested_at_column ? "feature_requested_at = COALESCE(feature_requested_at, NOW())," : '') . "
                        updated_at = NOW()
                    WHERE id = ?
                      AND LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) = 'approved'
                      AND COALESCE(avg_rating, 0) >= ?
                      AND COALESCE(total_reviews, 0) >= ?
                      AND LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) = 'none'
                      AND COALESCE(is_featured, 0) = 0
                ");
                $feature_pending_stmt->execute([$project_id, $feature_rating_threshold, $feature_review_threshold]);
                $created_feature_queue_now = $feature_pending_stmt->rowCount() > 0;
            }
        } elseif ($action === 'reject') {
            $project_reset_parts = ["approval_status = 'rejected'"];
            if ($has_featured_column) {
                $project_reset_parts[] = 'is_featured = 0';
            }
            if ($has_feature_status_column) {
                $project_reset_parts[] = "feature_status = 'none'";
            }
            if ($has_feature_requested_at_column) {
                $project_reset_parts[] = 'feature_requested_at = NULL';
            }
            if ($has_feature_reviewed_at_column) {
                $project_reset_parts[] = 'feature_reviewed_at = NULL';
            }
            if ($has_feature_reviewed_by_column) {
                $project_reset_parts[] = 'feature_reviewed_by = NULL';
            }
            if ($has_featured_at_column) {
                $project_reset_parts[] = 'featured_at = NULL';
            }
            $project_reset_parts[] = 'updated_at = NOW()';

            $stmt = $db->prepare("
                UPDATE projects
                SET " . implode(",\n                    ", $project_reset_parts) . "
                WHERE id = ?
            ");
            $stmt->execute([$project_id]);
        } elseif ($action === 'mark_under_review') {
            $stmt = $db->prepare("UPDATE projects SET approval_status = 'under_review', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$project_id]);
        } elseif ($action === 'approve_feature' && $has_feature_status_column) {
            $feature_stmt = $db->prepare("
                UPDATE projects
                SET is_featured = 1,
                    feature_status = 'featured',
                    " . ($has_feature_queue_type_column ? "feature_queue_type = NULL," : '') . "
                    " . ($has_feature_reviewed_at_column ? "feature_reviewed_at = NOW()," : '') . "
                    " . ($has_feature_reviewed_by_column ? "feature_reviewed_by = ?," : '') . "
                    " . ($has_featured_at_column ? "featured_at = COALESCE(featured_at, NOW())," : '') . "
                    updated_at = NOW()
                WHERE id = ?
                  AND LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) = 'approved'
                  AND LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) IN ('pending_review', 'eligible')
            ");
            $feature_params = [];
            if ($has_feature_reviewed_by_column) {
                $feature_params[] = (int) ($current_admin['id'] ?? 0);
            }
            $feature_params[] = $project_id;
            $feature_stmt->execute($feature_params);
        } elseif ($action === 'reject_feature' && $has_feature_status_column) {
            $feature_reject_parts = [
                'is_featured = 0',
                "feature_status = 'rejected'",
            ];
            if ($has_feature_queue_type_column) {
                $feature_reject_parts[] = 'feature_queue_type = NULL';
            }
            if ($has_feature_reviewed_at_column) {
                $feature_reject_parts[] = 'feature_reviewed_at = NOW()';
            }
            if ($has_feature_reviewed_by_column) {
                $feature_reject_parts[] = 'feature_reviewed_by = ?';
            }
            if ($has_featured_at_column) {
                $feature_reject_parts[] = 'featured_at = NULL';
            }
            $feature_reject_parts[] = 'updated_at = NOW()';

            $feature_reject_stmt = $db->prepare("
                UPDATE projects
                SET " . implode(",\n                    ", $feature_reject_parts) . "
                WHERE id = ?
                  AND LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) = 'approved'
                  AND LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) = 'pending_review'
            ");
            $feature_reject_params = [];
            if ($has_feature_reviewed_by_column) {
                $feature_reject_params[] = (int) ($current_admin['id'] ?? 0);
            }
            $feature_reject_params[] = $project_id;
            $feature_reject_stmt->execute($feature_reject_params);
        } elseif (in_array($action, ['approve_priority_request', 'reject_priority_request', 'activate_sponsored', 'reject_sponsored', 'expire_sponsored'], true)) {
            $message = adminHandleProjectPromotionAction($project_id, $action, (int) ($current_admin['id'] ?? 0), $db);
        }

        if ($has_content_flags_table && in_array($action, ['approve', 'reject', 'mark_under_review'], true)) {
            $resolve_flags = $db->prepare("
                UPDATE content_flags
                SET status = 'resolved',
                    updated_at = NOW()
                WHERE target_type = 'project'
                  AND target_id = ?
                  AND status = 'open'
            ");
            $resolve_flags->execute([$project_id]);
        }

        if ($project_owner_id > 0) {
            $template_vars = [
                'developer_name' => $developer_name_for_notice !== '' ? $developer_name_for_notice : 'Developer',
                'project_name' => $project_name_for_notice !== '' ? $project_name_for_notice : 'Project',
            ];

            if ($action === 'approve') {
                createTemplatedNotification('project.approved', 'developer', $project_owner_id, $template_vars, [
                    'actor_type' => 'admin',
                    'actor_id' => (int) ($current_admin['id'] ?? 0),
                    'meta' => ['project_id' => $project_id, 'status' => 'approved'],
                ], $db);

                if ($created_feature_queue_now) {
                    createTemplatedNotification('project.feature.criteria_reached', 'developer', $project_owner_id, $template_vars, [
                        'actor_type' => 'admin',
                        'actor_id' => (int) ($current_admin['id'] ?? 0),
                        'meta' => ['project_id' => $project_id, 'feature_status' => 'pending_review'],
                    ], $db);
                }
            } elseif ($action === 'reject') {
                createTemplatedNotification('project.rejected', 'developer', $project_owner_id, $template_vars, [
                    'actor_type' => 'admin',
                    'actor_id' => (int) ($current_admin['id'] ?? 0),
                    'meta' => ['project_id' => $project_id, 'status' => 'rejected'],
                ], $db);
            } elseif ($action === 'mark_under_review') {
                createTemplatedNotification('project.under_review', 'developer', $project_owner_id, $template_vars, [
                    'actor_type' => 'admin',
                    'actor_id' => (int) ($current_admin['id'] ?? 0),
                    'meta' => ['project_id' => $project_id, 'status' => 'under_review'],
                ], $db);
            } elseif ($action === 'approve_feature') {
                createTemplatedNotification('project.feature.approved', 'developer', $project_owner_id, $template_vars, [
                    'actor_type' => 'admin',
                    'actor_id' => (int) ($current_admin['id'] ?? 0),
                    'meta' => ['project_id' => $project_id, 'feature_status' => 'featured'],
                ], $db);
            } elseif ($action === 'reject_feature') {
                createTemplatedNotification('project.feature.rejected', 'developer', $project_owner_id, $template_vars, [
                    'actor_type' => 'admin',
                    'actor_id' => (int) ($current_admin['id'] ?? 0),
                    'meta' => ['project_id' => $project_id, 'feature_status' => 'rejected'],
                ], $db);
            }

            if ($has_content_flags_table && $had_open_project_flag) {
                createTemplatedNotification('project.flagged', 'developer', $project_owner_id, $template_vars, [
                    'actor_type' => 'admin',
                    'actor_id' => (int) ($current_admin['id'] ?? 0),
                    'meta' => ['project_id' => $project_id, 'status' => 'flagged'],
                ], $db);
            }
        }

        logAdminActivity(
            (int) $current_admin['id'],
            'project_moderation_' . $action,
            'project',
            (string) $project_id,
            json_encode([
                'feature_threshold_rating' => $feature_rating_threshold,
                'feature_threshold_reviews' => $feature_review_threshold,
            ], JSON_UNESCAPED_UNICODE)
        );
        if ($message === '') {
            $message = in_array($action, ['approve_feature', 'reject_feature'], true)
                ? 'Featured badge review updated.'
                : 'Project moderation action applied.';
        }
    } else {
        $message = 'Invalid project moderation payload.';
        $message_type = 'error';
    }
}

// Count query
$count_where_parts = [];
$count_params = [];
if ($status_filter !== 'all') {
    $count_where_parts[] = "{$moderation_status_sql} = ?";
    $count_params[] = $status_filter;
}
if ($search !== '') {
    $count_where_parts[] = "(p.name LIKE ? OR p.slug LIKE ? OR p.website_url LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $needle = '%' . $search . '%';
    $count_params[] = $needle;
    $count_params[] = $needle;
    $count_params[] = $needle;
    $count_params[] = $needle;
    $count_params[] = $needle;
}
$count_where = '';
if (!empty($count_where_parts)) {
    $count_where = 'WHERE ' . implode(' AND ', $count_where_parts);
}
$count_sql = "
    SELECT COUNT(*)
    FROM projects p
    LEFT JOIN users u ON u.id = p.created_by
    {$project_flags_join}
    {$count_where}
";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($count_params);
$total_projects = (int) $count_stmt->fetchColumn();
$total_pages = max(1, (int) ceil($total_projects / $perPage));

// Data query
$where_parts = [];
$params = [];
if ($status_filter !== 'all') {
    $where_parts[] = "{$moderation_status_sql} = ?";
    $params[] = $status_filter;
}
if ($search !== '') {
    $where_parts[] = "(p.name LIKE ? OR p.slug LIKE ? OR p.website_url LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $needle = '%' . $search . '%';
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
}

$where = '';
if (!empty($where_parts)) {
    $where = 'WHERE ' . implode(' AND ', $where_parts);
}

$featured_select = $has_featured_column ? 'COALESCE(p.is_featured, 0)' : '0';
$sponsored_select = $has_is_sponsored_column ? 'COALESCE(p.is_sponsored, 0)' : '0';
$feature_queue_type_select = $has_feature_queue_type_column ? "LOWER(COALESCE(NULLIF(TRIM(p.feature_queue_type), ''), ''))" : "''";
$priority_review_status_select = $has_priority_review_status_column ? "LOWER(COALESCE(NULLIF(TRIM(p.priority_review_status), ''), 'none'))" : "'none'";
$sponsored_status_select = $has_sponsored_status_column ? "LOWER(COALESCE(NULLIF(TRIM(p.sponsored_status), ''), 'none'))" : "'none'";
$latest_verification_join = "
    LEFT JOIN (
        SELECT dv_current.user_id, dv_current.status
        FROM developer_verification dv_current
        INNER JOIN (
            SELECT user_id, MAX(id) AS latest_id
            FROM developer_verification
            GROUP BY user_id
        ) latest_dv ON latest_dv.latest_id = dv_current.id
    ) dv ON dv.user_id = p.created_by
";

$query = "
    SELECT
        p.id,
        p.name,
        p.slug,
        p.category,
        p.description,
        p.approval_status,
        {$featured_select} AS is_featured,
        {$sponsored_select} AS is_sponsored,
        {$feature_status_select} AS feature_status,
        {$feature_queue_type_select} AS feature_queue_type,
        {$priority_review_status_select} AS priority_review_status,
        {$sponsored_status_select} AS sponsored_status,
        {$feature_requested_select} AS feature_requested_at,
        " . ($has_priority_review_requested_at_column ? 'p.priority_review_requested_at' : 'NULL') . " AS priority_review_requested_at,
        " . ($has_priority_review_paid_at_column ? 'p.priority_review_paid_at' : 'NULL') . " AS priority_review_paid_at,
        " . ($has_sponsored_requested_at_column ? 'p.sponsored_requested_at' : 'NULL') . " AS sponsored_requested_at,
        " . ($has_sponsored_starts_at_column ? 'p.sponsored_starts_at' : 'NULL') . " AS sponsored_starts_at,
        " . ($has_sponsored_ends_at_column ? 'p.sponsored_ends_at' : 'NULL') . " AS sponsored_ends_at,
        {$feature_reviewed_at_select} AS feature_reviewed_at,
        {$feature_reviewed_by_select} AS feature_reviewed_by,
        p.website_url,
        p.twitter_url,
        p.telegram_url,
        p.discord_url,
        p.github_url,
        p.contract_address,
        p.network,
        p.project_live_since,
        p.status,
        p.min_holding_amount,
        p.max_reward_rex,
        p.required_holding_days,
        p.created_at,
        p.updated_at,
        p.project_score,
        p.total_reviews,
        p.avg_rating,
        p.is_verified,
        {$meets_feature_criteria_sql} AS meets_feature_criteria,
        {$moderation_status_sql} AS moderation_status,
        u.username AS creator_username,
        u.email AS creator_email,
        dv.status AS developer_verification_status
    FROM projects p
    LEFT JOIN users u ON u.id = p.created_by
    {$latest_verification_join}
    {$project_flags_join}
    {$where}
    ORDER BY
        CASE
            WHEN {$priority_review_status_select} = 'active' AND {$feature_status_select} = 'pending_review' THEN 0
            WHEN {$feature_status_select} = 'pending_review' THEN 1
            WHEN {$sponsored_status_select} = 'requested' THEN 2
            ELSE 3
        END,
        p.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;

$stmt = $db->prepare($query);
$stmt->execute($params);
$projects = $stmt->fetchAll();
$project_contract_map = [];
if (!empty($projects)) {
    $ids = array_map(static fn($project) => (int) $project['id'], $projects);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $contract_stmt = $db->prepare("
        SELECT project_id, network_name, chain_id, contract_address, token_type, is_primary, is_active, verification_status
        FROM project_contracts
        WHERE project_id IN ({$placeholders})
        ORDER BY is_primary DESC, id ASC
    ");
    $contract_stmt->execute($ids);
    foreach ($contract_stmt->fetchAll() as $contract_row) {
        $project_contract_map[(int) $contract_row['project_id']][] = $contract_row;
    }
}

$promotion_overview_stmt = $db->query("
    SELECT
        COALESCE(SUM(CASE WHEN COALESCE(is_featured, 0) = 1 THEN 1 ELSE 0 END), 0) AS featured_live,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) = 'eligible' THEN 1 ELSE 0 END), 0) AS eligible_count,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) = 'pending_review' THEN 1 ELSE 0 END), 0) AS feature_queue_count,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(priority_review_status), ''), 'none')) = 'requested' THEN 1 ELSE 0 END), 0) AS priority_requested_count,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(priority_review_status), ''), 'none')) = 'active' THEN 1 ELSE 0 END), 0) AS priority_active_count,
        COALESCE(SUM(CASE WHEN COALESCE(is_sponsored, 0) = 1 THEN 1 ELSE 0 END), 0) AS sponsored_live,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(sponsored_status), ''), 'none')) = 'requested' THEN 1 ELSE 0 END), 0) AS sponsored_requested_count
    FROM projects
")->fetch() ?: [];

$promotion_queue_stmt = $db->query("
    SELECT id, name, category, approval_status,
           COALESCE(is_featured, 0) AS is_featured,
           COALESCE(is_sponsored, 0) AS is_sponsored,
           LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) AS feature_status,
           LOWER(COALESCE(NULLIF(TRIM(priority_review_status), ''), 'none')) AS priority_review_status,
           LOWER(COALESCE(NULLIF(TRIM(sponsored_status), ''), 'none')) AS sponsored_status,
           COALESCE(total_reviews, 0) AS total_reviews,
           COALESCE(avg_rating, 0) AS avg_rating
    FROM projects
    WHERE LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) = 'approved'
      AND (
            LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) IN ('eligible', 'pending_review')
         OR LOWER(COALESCE(NULLIF(TRIM(priority_review_status), ''), 'none')) = 'requested'
         OR LOWER(COALESCE(NULLIF(TRIM(sponsored_status), ''), 'none')) = 'requested'
         OR COALESCE(is_sponsored, 0) = 1
      )
    ORDER BY
        CASE
            WHEN LOWER(COALESCE(NULLIF(TRIM(priority_review_status), ''), 'none')) = 'requested' THEN 0
            WHEN LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) = 'pending_review' THEN 1
            WHEN LOWER(COALESCE(NULLIF(TRIM(sponsored_status), ''), 'none')) = 'requested' THEN 2
            WHEN LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) = 'eligible' THEN 3
            ELSE 4
        END,
        created_at DESC
    LIMIT 40
")->fetchAll() ?: [];

$feature_queue = array_values(array_filter($projects, static function ($project) {
    return strtolower(trim((string) ($project['feature_status'] ?? 'none'))) === 'pending_review';
}));

$project_summary = [
    'loaded' => count($projects),
    'verified' => 0,
    'featured' => 0,
    'feature_queue' => count($feature_queue),
    'sponsored' => 0,
    'priority_queue' => 0,
];

foreach ($projects as $summary_project) {
    if ((int) ($summary_project['is_verified'] ?? 0) === 1) {
        $project_summary['verified']++;
    }
    if ((int) ($summary_project['is_featured'] ?? 0) === 1) {
        $project_summary['featured']++;
    }
    if ((int) ($summary_project['is_sponsored'] ?? 0) === 1) {
        $project_summary['sponsored']++;
    }
    if (strtolower(trim((string) ($summary_project['priority_review_status'] ?? 'none'))) === 'active'
        && strtolower(trim((string) ($summary_project['feature_status'] ?? 'none'))) === 'pending_review') {
        $project_summary['priority_queue']++;
    }
}

$format_feature_status = static function ($feature_status, $is_featured, $meets_feature_criteria) {
    $feature_status = strtolower(trim((string) $feature_status));

    if ((int) $is_featured === 1 || $feature_status === 'featured') {
        return ['label' => 'Featured Live', 'class' => 'is-active'];
    }
    if ($feature_status === 'pending_review') {
        return ['label' => 'Feature Review Pending', 'class' => 'is-pro'];
    }
    if ($feature_status === 'rejected') {
        return ['label' => 'Feature Rejected', 'class' => 'is-suspended'];
    }
    if ($feature_status === 'eligible') {
        return ['label' => 'Eligible For Feature Review', 'class' => 'is-pending'];
    }
    if ((int) $meets_feature_criteria === 1) {
        return ['label' => 'Eligible For Feature Review', 'class' => 'is-pending'];
    }

    return ['label' => 'Not Eligible', 'class' => 'is-beginner'];
};

// AJAX mode
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');

    $tableBody = '';
    if (empty($projects)) {
        $tableBody = '<tr><td colspan="6" style="text-align:center;padding:32px;color:#94a3b8;">No projects found matching your filters.</td></tr>';
    } else {
        foreach ($projects as $project) {
            $approval_status = strtolower(trim((string) ($project['moderation_status'] ?? $project['approval_status'] ?? 'pending')));
            $status_class = 'is-pending';
            if ($approval_status === 'approved') {
                $status_class = 'is-active';
            } elseif ($approval_status === 'rejected') {
                $status_class = 'is-suspended';
            } elseif ($approval_status === 'under_review') {
                $status_class = 'is-pending';
            } elseif ($approval_status === 'flagged') {
                $status_class = 'is-suspended';
            }
            $dev_status = (string) ($project['developer_verification_status'] ?? 'not_applied');
            $feature_state = $format_feature_status(
                $project['feature_status'] ?? 'none',
                $project['is_featured'] ?? 0,
                $project['meets_feature_criteria'] ?? 0
            );
            $feature_status = strtolower(trim((string) ($project['feature_status'] ?? 'none')));
            $priority_status = strtolower(trim((string) ($project['priority_review_status'] ?? 'none')));
            $sponsored_status = strtolower(trim((string) ($project['sponsored_status'] ?? 'none')));
            $project_contracts_json = json_encode($project_contract_map[(int) $project['id']] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $tableBody .= '<tr>';
            $tableBody .= '<td data-label="ID">' . (int) $project['id'] . '</td>';
            $tableBody .= '<td data-label="Project"><strong>' . htmlspecialchars((string) $project['name'], ENT_QUOTES, 'UTF-8') . '</strong><br><span class="muted">/' . htmlspecialchars((string) ($project['slug'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span></td>';
            $tableBody .= '<td data-label="Submitted By">' . htmlspecialchars((string) ($project['creator_username'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') . '<br><span class="muted">' . htmlspecialchars((string) ($project['creator_email'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span></td>';
            $tableBody .= '<td data-label="Status"><span class="dashboard-pill ' . $status_class . '">' . htmlspecialchars(ucwords(str_replace('_', ' ', $approval_status)), ENT_QUOTES, 'UTF-8') . '</span></td>';
            $tableBody .= '<td data-label="Featured Badge">';
            $tableBody .= '<span class="dashboard-pill ' . $feature_state['class'] . '">' . htmlspecialchars($feature_state['label'], ENT_QUOTES, 'UTF-8') . '</span>';
            if ((int) ($project['is_sponsored'] ?? 0) === 1) {
                $tableBody .= '<br><span class="dashboard-pill is-suspended">Sponsored Live</span>';
            } elseif ($sponsored_status === 'requested') {
                $tableBody .= '<br><span class="dashboard-pill is-pending">Sponsored Requested</span>';
            }
            if ($priority_status === 'active') {
                $tableBody .= '<br><span class="dashboard-pill is-active">Priority Queue</span>';
            } elseif ($priority_status === 'requested') {
                $tableBody .= '<br><span class="dashboard-pill is-pending">Priority Requested</span>';
            }
            $tableBody .= '</td>';
            $tableBody .= '<td data-label="Action">';
            $tableBody .= '<div class="action-stack">';
            $tableBody .= '<button type="button" class="btn btn-secondary action-view-btn project-view-btn"';
            $tableBody .= ' data-project-name="' . htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-slug="' . htmlspecialchars((string) ($project['slug'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-category="' . htmlspecialchars((string) ($project['category'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-description="' . htmlspecialchars((string) ($project['description'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-avg-rating="' . htmlspecialchars(number_format((float) ($project['avg_rating'] ?? 0), 2), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-total-reviews="' . (int) ($project['total_reviews'] ?? 0) . '"';
             $tableBody .= ' data-project-score="' . htmlspecialchars(number_format((float) ($project['project_score'] ?? 0), 2), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-created-at="' . htmlspecialchars((string) ($project['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-status="' . htmlspecialchars($approval_status, ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-feature-status="' . htmlspecialchars($feature_state['label'], ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-is-verified="' . (int) ($project['is_verified'] ?? 0) . '"';
             $tableBody .= ' data-project-dev-verification="' . htmlspecialchars($dev_status, ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-logo="' . htmlspecialchars((string) ($project['logo'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-website-url="' . htmlspecialchars((string) ($project['website_url'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-twitter-url="' . htmlspecialchars((string) ($project['twitter_url'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-telegram-url="' . htmlspecialchars((string) ($project['telegram_url'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-discord-url="' . htmlspecialchars((string) ($project['discord_url'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-github-url="' . htmlspecialchars((string) ($project['github_url'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-contract-address="' . htmlspecialchars((string) ($project['contract_address'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-contracts="' . htmlspecialchars((string) $project_contracts_json, ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-network="' . htmlspecialchars((string) ($project['network'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-live-since="' . htmlspecialchars((string) ($project['project_live_since'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-token-status="' . htmlspecialchars((string) ($project['status'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-min-holding="' . htmlspecialchars((string) ($project['min_holding_amount'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-max-reward="' . htmlspecialchars((string) ($project['max_reward_rex'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= ' data-project-holding-days="' . htmlspecialchars((string) ($project['required_holding_days'] ?? ''), ENT_QUOTES, 'UTF-8') . '"';
             $tableBody .= '>View</button>';
             $tableBody .= '<form method="POST" action="" class="inline-form">';
             $tableBody .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
             $tableBody .= '<input type="hidden" name="project_id" value="' . (int) $project['id'] . '">';
             if ($approval_status === 'pending') {
                 $tableBody .= '<button type="submit" name="action" value="mark_under_review" class="btn btn-primary">Mark Under Review</button>';
             } elseif ($approval_status === 'under_review') {
                 $tableBody .= '<button type="submit" name="action" value="approve" class="btn btn-primary">Approve</button>';
                 $tableBody .= '<button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>';
             } elseif ($approval_status === 'approved') {
                 if ($feature_status === 'pending_review') {
                     $tableBody .= '<button type="submit" name="action" value="approve_feature" class="btn btn-primary">Approve Feature</button>';
                     $tableBody .= '<button type="submit" name="action" value="reject_feature" class="btn btn-danger">Reject Feature</button>';
                 }
                 if ($priority_status === 'requested') {
                     $tableBody .= '<button type="submit" name="action" value="approve_priority_request" class="btn btn-primary">Approve Priority</button>';
                     $tableBody .= '<button type="submit" name="action" value="reject_priority_request" class="btn btn-danger">Reject Priority</button>';
                 }
                 if ($sponsored_status === 'requested') {
                     $tableBody .= '<button type="submit" name="action" value="activate_sponsored" class="btn btn-primary">Activate Sponsored</button>';
                     $tableBody .= '<button type="submit" name="action" value="reject_sponsored" class="btn btn-danger">Reject Sponsored</button>';
                 }
                 if ((int) ($project['is_sponsored'] ?? 0) === 1) {
                     $tableBody .= '<button type="submit" name="action" value="expire_sponsored" class="btn btn-danger">Expire Sponsored</button>';
                 }
                 $tableBody .= '<button type="submit" name="action" value="mark_under_review" class="btn btn-secondary">Move to Review</button>';
             } elseif ($approval_status === 'rejected' || $approval_status === 'flagged') {
                 $tableBody .= '<button type="submit" name="action" value="mark_under_review" class="btn btn-primary">Reopen Review</button>';
             }
             $tableBody .= '</form>';
             $tableBody .= '</div>';
             $tableBody .= '</td>';
             $tableBody .= '</tr>';
         }
     }

     $paginationHtml = renderPagination($page, $total_pages, ADMIN_BASE_URL . '/projects.php', array_merge(
         $search !== '' ? ['q' => $search] : [],
         ['status' => $status_filter]
     ));

     echo json_encode(paginationJsonResponse($tableBody, $paginationHtml, $page));
     exit();
 }

 paginationRenderStyles();
 ?>
 <div class="dashboard-container">

     <!-- ====== HEADER ====== -->
     <div class="dashboard-header">
         <div class="dashboard-header-left">
             <div class="dashboard-header-icon"><i class="fas fa-project-diagram"></i></div>
             <div class="dashboard-header-text">
                 <h1>Project Moderation</h1>
                 <p>Review, approve, reject, and manage featured badges, priority reviews, and sponsored placements.</p>
             </div>
         </div>
         <div class="dashboard-header-badge">
             <i class="fas fa-database"></i> <?php echo number_format($total_projects); ?> Projects
         </div>
     </div>

     <?php if ($message !== ''): ?>
         <div
             data-toast
             data-toast-type="<?php echo $message_type === 'error' ? 'error' : 'success'; ?>"
             data-toast-message="<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>"
             hidden
         ></div>
     <?php endif; ?>

     <!-- ====== METRICS ====== -->
     <div class="dashboard-metric-grid">
         <div class="dashboard-metric-card">
             <div class="metric-top">
                 <span class="metric-icon is-blue"><i class="fas fa-project-diagram"></i></span>
             </div>
             <strong class="metric-value"><?php echo number_format((int) $total_projects); ?></strong>
             <span class="metric-label">Total Projects</span>
         </div>
         <div class="dashboard-metric-card">
             <div class="metric-top">
                 <span class="metric-icon is-gold"><i class="fas fa-check-circle"></i></span>
             </div>
             <strong class="metric-value"><?php echo number_format((int) ($promotion_overview_stmt['featured_live'] ?? 0)); ?></strong>
             <span class="metric-label">Featured Live</span>
         </div>
         <div class="dashboard-metric-card">
             <div class="metric-top">
                 <span class="metric-icon is-purple"><i class="fas fa-clock"></i></span>
             </div>
             <strong class="metric-value"><?php echo number_format((int) ($promotion_overview_stmt['feature_queue_count'] ?? 0)); ?></strong>
             <span class="metric-label">Feature Queue</span>
         </div>
         <div class="dashboard-metric-card">
             <div class="metric-top">
                 <span class="metric-icon is-gold"><i class="fas fa-star"></i></span>
             </div>
             <strong class="metric-value"><?php echo number_format((int) ($promotion_overview_stmt['sponsored_live'] ?? 0)); ?></strong>
             <span class="metric-label">Sponsored Live</span>
         </div>
         <div class="dashboard-metric-card">
             <div class="metric-top">
                 <span class="metric-icon is-blue"><i class="fas fa-bolt"></i></span>
             </div>
             <strong class="metric-value"><?php echo number_format((int) ($promotion_overview_stmt['priority_active_count'] ?? 0)); ?></strong>
             <span class="metric-label">Priority Active</span>
         </div>
         <div class="dashboard-metric-card">
             <div class="metric-top">
                 <span class="metric-icon is-purple"><i class="fas fa-flag"></i></span>
             </div>
             <strong class="metric-value"><?php echo number_format((int) ($promotion_overview_stmt['priority_requested_count'] ?? 0)); ?></strong>
             <span class="metric-label">Priority Requested</span>
         </div>
     </div>

     <!-- ====== FILTER BAR ====== -->
     <div class="dashboard-panel">
         <div class="dashboard-filter-bar">
             <div>
                 <h3 style="margin:0 0 4px; color:#f1f5f9; font-size:15px; font-weight:700;">Filter Projects</h3>
                 <p class="muted" style="margin:0; font-size:13px;">Filter by moderation status or search by name, slug, URL, or submitter.</p>
             </div>
             <form method="GET" action="" class="dashboard-filter-form" id="filterForm">
                 <select name="status">
                     <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                     <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                     <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                     <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                     <option value="flagged" <?php echo $status_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                     <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                 </select>
                 <input type="text" name="q" placeholder="Search projects..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                 <button type="submit" class="btn btn-secondary">Filter</button>
             </form>
         </div>
     </div>

     <!-- ====== PROJECTS TABLE ====== -->
     <div class="dashboard-table-wrap">
         <table class="dashboard-table">
             <thead>
             <tr>
                 <th>ID</th>
                 <th>Project</th>
                 <th>Submitted By</th>
                 <th>Status</th>
                 <th>Featured Badge</th>
                 <th>Action</th>
             </tr>
             </thead>
             <tbody id="tableBody">
             <?php if (empty($projects)): ?>
                 <tr><td colspan="6" style="text-align:center;padding:32px;color:#94a3b8;">No projects found matching your filters.</td></tr>
             <?php else: ?>
                 <?php foreach ($projects as $project): ?>
                     <?php
                     $approval_status = strtolower(trim((string) ($project['moderation_status'] ?? $project['approval_status'] ?? 'pending')));
                     $status_class = 'is-pending';
                     if ($approval_status === 'approved') {
                         $status_class = 'is-active';
                     } elseif ($approval_status === 'rejected') {
                         $status_class = 'is-suspended';
                     } elseif ($approval_status === 'under_review') {
                         $status_class = 'is-pending';
                     } elseif ($approval_status === 'flagged') {
                         $status_class = 'is-suspended';
                     }
                     $dev_status = (string) ($project['developer_verification_status'] ?? 'not_applied');
                     $feature_state = $format_feature_status(
                         $project['feature_status'] ?? 'none',
                         $project['is_featured'] ?? 0,
                         $project['meets_feature_criteria'] ?? 0
                     );
                     $feature_status = strtolower(trim((string) ($project['feature_status'] ?? 'none')));
                     $priority_status = strtolower(trim((string) ($project['priority_review_status'] ?? 'none')));
                     $sponsored_status = strtolower(trim((string) ($project['sponsored_status'] ?? 'none')));
                     $project_contracts_json = json_encode($project_contract_map[(int) $project['id']] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                     ?>
                     <tr>
                         <td data-label="ID"><?php echo (int) $project['id']; ?></td>
                         <td data-label="Project">
                             <strong><?php echo htmlspecialchars((string) $project['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                             <span class="muted">/<?php echo htmlspecialchars((string) ($project['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                         </td>
                         <td data-label="Submitted By">
                             <?php echo htmlspecialchars((string) ($project['creator_username'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?><br>
                             <span class="muted"><?php echo htmlspecialchars((string) ($project['creator_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                         </td>
                         <td data-label="Status">
                             <span class="dashboard-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $approval_status)), ENT_QUOTES, 'UTF-8'); ?></span>
                         </td>
                         <td data-label="Featured Badge">
                             <span class="dashboard-pill <?php echo $feature_state['class']; ?>"><?php echo htmlspecialchars($feature_state['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                             <?php if ((int) ($project['is_sponsored'] ?? 0) === 1): ?>
                                 <br><span class="dashboard-pill is-suspended">Sponsored Live</span>
                             <?php elseif ($sponsored_status === 'requested'): ?>
                                 <br><span class="dashboard-pill is-pending">Sponsored Requested</span>
                             <?php endif; ?>
                             <?php if ($priority_status === 'active'): ?>
                                 <br><span class="dashboard-pill is-active">Priority Queue</span>
                             <?php elseif ($priority_status === 'requested'): ?>
                                 <br><span class="dashboard-pill is-pending">Priority Requested</span>
                             <?php endif; ?>
                         </td>
                         <td data-label="Action">
                             <div class="action-stack">
                                 <button type="button" class="btn btn-secondary action-view-btn project-view-btn"
                                         data-project-name="<?php echo htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-slug="<?php echo htmlspecialchars((string) ($project['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-category="<?php echo htmlspecialchars((string) ($project['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-description="<?php echo htmlspecialchars((string) ($project['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-avg-rating="<?php echo htmlspecialchars(number_format((float) ($project['avg_rating'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-total-reviews="<?php echo (int) ($project['total_reviews'] ?? 0); ?>"
                                         data-project-score="<?php echo htmlspecialchars(number_format((float) ($project['project_score'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-created-at="<?php echo htmlspecialchars((string) ($project['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-status="<?php echo htmlspecialchars($approval_status, ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-feature-status="<?php echo htmlspecialchars($feature_state['label'], ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-is-verified="<?php echo (int) ($project['is_verified'] ?? 0); ?>"
                                         data-project-dev-verification="<?php echo htmlspecialchars($dev_status, ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-logo="<?php echo htmlspecialchars((string) ($project['logo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-website-url="<?php echo htmlspecialchars((string) ($project['website_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-twitter-url="<?php echo htmlspecialchars((string) ($project['twitter_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-telegram-url="<?php echo htmlspecialchars((string) ($project['telegram_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-discord-url="<?php echo htmlspecialchars((string) ($project['discord_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-github-url="<?php echo htmlspecialchars((string) ($project['github_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-contract-address="<?php echo htmlspecialchars((string) ($project['contract_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-contracts="<?php echo htmlspecialchars((string) $project_contracts_json, ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-network="<?php echo htmlspecialchars((string) ($project['network'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-live-since="<?php echo htmlspecialchars((string) ($project['project_live_since'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-token-status="<?php echo htmlspecialchars((string) ($project['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-min-holding="<?php echo htmlspecialchars((string) ($project['min_holding_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-max-reward="<?php echo htmlspecialchars((string) ($project['max_reward_rex'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                         data-project-holding-days="<?php echo htmlspecialchars((string) ($project['required_holding_days'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                 >View</button>
                                 <form method="POST" action="" class="inline-form">
                                     <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                     <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
                                     <?php if ($approval_status === 'pending'): ?>
                                         <button type="submit" name="action" value="mark_under_review" class="btn btn-primary">Mark Under Review</button>
                                     <?php elseif ($approval_status === 'under_review'): ?>
                                         <button type="submit" name="action" value="approve" class="btn btn-primary">Approve</button>
                                         <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                                     <?php elseif ($approval_status === 'approved'): ?>
                                         <?php if ($feature_status === 'pending_review'): ?>
                                             <button type="submit" name="action" value="approve_feature" class="btn btn-primary">Approve Feature</button>
                                             <button type="submit" name="action" value="reject_feature" class="btn btn-danger">Reject Feature</button>
                                         <?php endif; ?>
                                         <?php if ($priority_status === 'requested'): ?>
                                             <button type="submit" name="action" value="approve_priority_request" class="btn btn-primary">Approve Priority</button>
                                             <button type="submit" name="action" value="reject_priority_request" class="btn btn-danger">Reject Priority</button>
                                         <?php endif; ?>
                                         <?php if ($sponsored_status === 'requested'): ?>
                                             <button type="submit" name="action" value="activate_sponsored" class="btn btn-primary">Activate Sponsored</button>
                                             <button type="submit" name="action" value="reject_sponsored" class="btn btn-danger">Reject Sponsored</button>
                                         <?php endif; ?>
                                         <?php if ((int) ($project['is_sponsored'] ?? 0) === 1): ?>
                                             <button type="submit" name="action" value="expire_sponsored" class="btn btn-danger">Expire Sponsored</button>
                                         <?php endif; ?>
                                         <button type="submit" name="action" value="mark_under_review" class="btn btn-secondary">Move to Review</button>
                                     <?php elseif ($approval_status === 'rejected' || $approval_status === 'flagged'): ?>
                                         <button type="submit" name="action" value="mark_under_review" class="btn btn-primary">Reopen Review</button>
                                     <?php endif; ?>
                                 </form>
                             </div>
                         </td>
                     </tr>
                 <?php endforeach; ?>
             <?php endif; ?>
             </tbody>
         </table>
     </div>

     <!-- Pagination -->
     <div id="pagination">
         <?php echo renderPagination($page, $total_pages, ADMIN_BASE_URL . '/projects.php', array_merge(
             $search !== '' ? ['q' => $search] : [],
             ['status' => $status_filter]
         )); ?>
     </div>

 </div><!-- /.dashboard-container -->

<?php
paginationRenderJS([
    'tableBodyId' => 'tableBody',
    'paginationId' => 'pagination',
    'fetchUrl' => ADMIN_BASE_URL . '/projects.php',
    'filterFormId' => 'filterForm',
    'extraParams' => ['q', 'status'],
    'pageParam' => 'page',
    'loadingText' => 'Loading projects',
]);

?>

<!-- ====== PROJECT DETAIL MODAL ====== -->
<div class="dashboard-modal" id="projectDetailModal">
    <div class="dashboard-modal-card">
        <div class="dashboard-modal-header">
            <div>
                <span class="modal-kicker"><i class="fas fa-project-diagram"></i> Project Details</span>
                <h3 id="modalProjectTitle">Project</h3>
            </div>
            <button type="button" class="dashboard-modal-close" id="closeProjectModal">&times;</button>
        </div>
        <div class="dashboard-modal-body">

            <!-- ====== HERO CARD ====== -->
            <div class="modal-hero-card">
                <div class="modal-hero-avatar" id="modalProjectLogoContainer">
                    <i class="fas fa-cube" id="modalProjectLogoFallback"></i>
                    <img id="modalProjectLogoImg" src="" alt="Logo" style="display:none;width:48px;height:48px;border-radius:10px;object-fit:cover;">
                </div>
                <div class="modal-hero-info">
                    <h2 id="modalProjectName">Project</h2>
                    <span class="modal-hero-slug" id="modalProjectSlug">/slug</span>
                    <div class="modal-hero-badges">
                        <span class="modal-hero-badge" id="modalProjectStatusBadge">Pending</span>
                        <span class="modal-hero-badge is-feature" id="modalProjectFeatureBadge">Not Featured</span>
                    </div>
                </div>
                <div class="modal-hero-score">
                    <div class="modal-hero-score-value" id="modalProjectScore">0.00</div>
                    <div class="modal-hero-score-label">Score</div>
                </div>
            </div>

            <!-- ====== METRICS ROW ====== -->
            <div class="modal-metrics-row">
                <div class="modal-metric-card">
                    <div class="modal-metric-icon is-gold"><i class="fas fa-star"></i></div>
                    <div class="modal-metric-body">
                        <span class="modal-metric-value" id="modalProjectAvgRating">0.00</span>
                        <span class="modal-metric-label">Avg Rating</span>
                    </div>
                </div>
                <div class="modal-metric-card">
                    <div class="modal-metric-icon is-blue"><i class="fas fa-comments"></i></div>
                    <div class="modal-metric-body">
                        <span class="modal-metric-value" id="modalProjectTotalReviews">0</span>
                        <span class="modal-metric-label">Total Reviews</span>
                    </div>
                </div>
                <div class="modal-metric-card">
                    <div class="modal-metric-icon is-green"><i class="fas fa-check-circle"></i></div>
                    <div class="modal-metric-body">
                        <span class="modal-metric-value" id="modalProjectVerified">No</span>
                        <span class="modal-metric-label">Verified</span>
                    </div>
                </div>
            </div>

            <!-- ====== SOCIAL LINKS ====== -->
            <div class="modal-info-card">
                <div class="modal-info-card-header">
                    <i class="fas fa-link"></i> Social Links
                </div>
                <div class="modal-info-card-body">
                    <div class="modal-link-grid" id="modalSocialLinks">
                        <!-- Dynamically populated -->
                    </div>
                </div>
            </div>

            <!-- ====== TWO-COLUMN GRID ====== -->
            <div class="modal-grid-2col">

                <!-- Tokenomics Card -->
                <div class="modal-info-card">
                    <div class="modal-info-card-header">
                        <i class="fas fa-coins"></i> Tokenomics
                    </div>
                    <div class="modal-info-card-body">
                        <div class="modal-info-row">
                            <span class="modal-info-label">Contract Address</span>
                            <span class="modal-info-value copy-value" id="modalContractAddress">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Network</span>
                            <span class="modal-info-value" id="modalNetwork">—</span>
                        </div>
                        <div class="modal-info-row" style="display:block;">
                            <span class="modal-info-label">Eligibility Contracts</span>
                            <div class="modal-info-value" id="modalEligibilityContracts" style="display:grid;gap:6px;margin-top:8px;">—</div>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Min Holding Amount</span>
                            <span class="modal-info-value" id="modalMinHolding">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Max Reward ($REX)</span>
                            <span class="modal-info-value" id="modalMaxReward">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Required Holding Days</span>
                            <span class="modal-info-value" id="modalHoldingDays">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Project Status</span>
                            <span class="modal-info-value" id="modalTokenStatus">—</span>
                        </div>
                        <div class="modal-info-row">
                            <span class="modal-info-label">Live Since</span>
                            <span class="modal-info-value" id="modalLiveSince">—</span>
                        </div>
                    </div>
                </div>

                <!-- Description Card -->
                <div class="modal-info-card">
                    <div class="modal-info-card-header">
                        <i class="fas fa-align-left"></i> Description
                    </div>
                    <div class="modal-info-card-body">
                        <p class="modal-description" id="modalProjectDescription" style="color:#cbd5e1;font-size:13px;line-height:1.6;margin:0;word-break:break-word;">No description provided.</p>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const modal = document.getElementById('projectDetailModal');
    const closeBtn = document.getElementById('closeProjectModal');
    const tableBody = document.getElementById('tableBody');

    // Helper: escape HTML for safe innerHTML
    function esc(v) {
        var d = document.createElement('div');
        d.textContent = v == null ? '' : String(v);
        return d.innerHTML;
    }

    // Helper: build a clickable link button with copy support
    function linkBtn(url, label, iconClass, btnClass) {
        if (!url) {
            return '<span class="modal-link-btn is-na"><i class="' + esc(iconClass || 'fas fa-link') + '"></i> ' + esc(label) + ' N/A</span>';
        }
        var copyAttr = ' data-copy="' + esc(url) + '"';
        return '<a class="modal-link-btn ' + esc(btnClass || '') + '" href="' + esc(url) + '" target="_blank" rel="noopener noreferrer"><i class="' + esc(iconClass || 'fas fa-link') + '"></i> ' + esc(label) + '</a>' +
               '<button type="button" class="modal-link-copy" title="Copy ' + esc(label) + ' URL"' + copyAttr + '><i class="fas fa-copy"></i></button>';
    }

    function openModal(data) {
        // Basic fields
        document.getElementById('modalProjectTitle').textContent = data.name || 'Project';
        document.getElementById('modalProjectName').textContent = data.name || 'Project';
        document.getElementById('modalProjectSlug').textContent = '/' + (data.slug || 'slug');
        document.getElementById('modalProjectDescription').textContent = data.description || 'No description provided.';
        document.getElementById('modalProjectAvgRating').textContent = data.avgRating || '0.00';
        document.getElementById('modalProjectTotalReviews').textContent = data.totalReviews || '0';
        document.getElementById('modalProjectScore').textContent = data.score || '0.00';
        document.getElementById('modalProjectVerified').textContent = data.isVerified === '1' ? 'Yes' : 'No';

        // Tokenomics fields
        document.getElementById('modalContractAddress').textContent = data.contractAddress || '—';
        document.getElementById('modalNetwork').textContent = data.network || '—';
        var contractsNode = document.getElementById('modalEligibilityContracts');
        var contractRows = [];
        try {
            contractRows = JSON.parse(data.contracts || '[]');
        } catch (error) {
            contractRows = [];
        }
        if (contractsNode) {
            if (!Array.isArray(contractRows) || contractRows.length === 0) {
                contractsNode.textContent = 'No eligibility contracts configured.';
            } else {
                contractsNode.innerHTML = contractRows.map(function(row) {
                    var isNative = String(row.token_type || '').toUpperCase() === 'NATIVE';
                    var addressLine = isNative
                        ? '<span style="color:#bfdbfe;">Native balance check</span><br>'
                        : '<code style="word-break:break-all;color:#bfdbfe;">' + esc(row.contract_address || '') + '</code><br>';
                    return '<div style="padding:8px;border:1px solid rgba(148,163,184,.18);border-radius:8px;background:rgba(15,23,42,.45);">' +
                        '<strong>' + esc(row.network_name || 'Network') + '</strong> ' +
                        '<span style="color:#94a3b8;">chain ' + esc(row.chain_id || '') + ' | ' + esc(row.token_type || 'ERC20') + (String(row.is_primary) === '1' ? ' | Primary' : '') + '</span><br>' +
                        addressLine +
                        '<span style="color:#94a3b8;">Status: ' + esc(row.verification_status || 'needs_check') + (String(row.is_active) === '1' ? '' : ' | Disabled') + '</span>' +
                    '</div>';
                }).join('');
            }
        }
        document.getElementById('modalMinHolding').textContent = data.minHolding || '—';
        document.getElementById('modalMaxReward').textContent = data.maxReward || '—';
        document.getElementById('modalHoldingDays').textContent = data.holdingDays || '—';
        document.getElementById('modalTokenStatus').textContent = data.tokenStatus || '—';
        document.getElementById('modalLiveSince').textContent = data.liveSince || '—';

        // Logo
        var logoImg = document.getElementById('modalProjectLogoImg');
        var logoFallback = document.getElementById('modalProjectLogoFallback');
        if (data.logo) {
            logoImg.src = data.logo;
            logoImg.style.display = 'block';
            logoFallback.style.display = 'none';
        } else {
            logoImg.style.display = 'none';
            logoFallback.style.display = 'block';
        }

        // Status badge
        var statusBadge = document.getElementById('modalProjectStatusBadge');
        var statusText = data.status || 'pending';
        statusBadge.textContent = statusText.charAt(0).toUpperCase() + statusText.slice(1).replace(/_/g, ' ');
        statusBadge.className = 'modal-hero-badge';
        if (statusText === 'approved') statusBadge.classList.add('is-approved');
        else if (statusText === 'rejected' || statusText === 'flagged') statusBadge.classList.add('is-rejected');
        else statusBadge.classList.add('is-pending');

        // Feature badge
        var featureBadge = document.getElementById('modalProjectFeatureBadge');
        featureBadge.textContent = data.featureStatus || 'Not Featured';
        featureBadge.className = 'modal-hero-badge';
        if (data.featureStatus && data.featureStatus.toLowerCase().includes('featured live')) {
            featureBadge.classList.add('is-approved');
        } else if (data.featureStatus && data.featureStatus.toLowerCase().includes('pending')) {
            featureBadge.classList.add('is-pending');
        } else if (data.featureStatus && data.featureStatus.toLowerCase().includes('rejected')) {
            featureBadge.classList.add('is-rejected');
        } else {
            featureBadge.classList.add('is-pending');
        }

        // Social links
        var linksHtml =
            linkBtn(data.websiteUrl, 'Website', 'fas fa-globe', 'is-website') +
            linkBtn(data.twitterUrl, 'X (Twitter)', 'fa-brands fa-x-twitter', 'is-twitter') +
            linkBtn(data.telegramUrl, 'Telegram', 'fa-brands fa-telegram', 'is-telegram') +
            linkBtn(data.discordUrl, 'Discord', 'fa-brands fa-discord', 'is-discord') +
            linkBtn(data.githubUrl, 'GitHub', 'fa-brands fa-github', 'is-github');
        document.getElementById('modalSocialLinks').innerHTML = linksHtml;

        // Copy button handlers for social links
        document.querySelectorAll('#modalSocialLinks .modal-link-copy').forEach(function(copyBtn) {
            copyBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var url = copyBtn.getAttribute('data-copy');
                if (!url) return;
                navigator.clipboard.writeText(url).then(function() {
                    var orig = copyBtn.innerHTML;
                    copyBtn.innerHTML = '<i class="fas fa-check"></i>';
                    setTimeout(function() { copyBtn.innerHTML = orig; }, 1200);
                });
            });
        });

        // Contract address copy
        var contractEl = document.getElementById('modalContractAddress');
        var contractText = contractEl.textContent || '';
        if (contractText && contractText !== '—') {
            contractEl.style.cursor = 'pointer';
            contractEl.title = 'Click to copy';
            contractEl.onclick = function() {
                navigator.clipboard.writeText(contractText).then(function() {
                    var orig = contractEl.textContent;
                    contractEl.textContent = '✓ Copied!';
                    setTimeout(function() { contractEl.textContent = orig; }, 1200);
                });
            };
        } else {
            contractEl.style.cursor = 'default';
            contractEl.title = '';
            contractEl.onclick = null;
        }

        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }

    // Use event delegation on tableBody so View buttons work even after AJAX pagination replaces rows
    if (tableBody) {
        tableBody.addEventListener('click', function(e) {
            var btn = e.target.closest('.project-view-btn');
            if (!btn) return;
            openModal({
                name: btn.getAttribute('data-project-name'),
                slug: btn.getAttribute('data-project-slug'),
                category: btn.getAttribute('data-project-category'),
                description: btn.getAttribute('data-project-description'),
                avgRating: btn.getAttribute('data-project-avg-rating'),
                totalReviews: btn.getAttribute('data-project-total-reviews'),
                score: btn.getAttribute('data-project-score'),
                createdAt: btn.getAttribute('data-project-created-at'),
                status: btn.getAttribute('data-project-status'),
                featureStatus: btn.getAttribute('data-project-feature-status'),
                isVerified: btn.getAttribute('data-project-is-verified'),
                devVerification: btn.getAttribute('data-project-dev-verification'),
                logo: btn.getAttribute('data-project-logo'),
                websiteUrl: btn.getAttribute('data-project-website-url'),
                twitterUrl: btn.getAttribute('data-project-twitter-url'),
                telegramUrl: btn.getAttribute('data-project-telegram-url'),
                discordUrl: btn.getAttribute('data-project-discord-url'),
                githubUrl: btn.getAttribute('data-project-github-url'),
                contractAddress: btn.getAttribute('data-project-contract-address'),
                contracts: btn.getAttribute('data-project-contracts'),
                network: btn.getAttribute('data-project-network'),
                liveSince: btn.getAttribute('data-project-live-since'),
                tokenStatus: btn.getAttribute('data-project-token-status'),
                minHolding: btn.getAttribute('data-project-min-holding'),
                maxReward: btn.getAttribute('data-project-max-reward'),
                holdingDays: btn.getAttribute('data-project-holding-days'),
            });
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('show')) closeModal();
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
