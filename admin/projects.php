<?php
$page_title = 'Projects';
$activePage = 'projects';
require_once __DIR__ . '/includes/header.php';

$db = getDBConnection();
$current_admin = getCurrentAdmin();
$message = '';
$message_type = 'success';
$status_filter = trim((string) ($_GET['status'] ?? 'pending'));
$search = trim((string) ($_GET['q'] ?? ''));
$valid_filters = ['pending', 'under_review', 'approved', 'rejected', 'flagged', 'all'];
if (!in_array($status_filter, $valid_filters, true)) {
    $status_filter = 'pending';
}

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
                $feature_pending_stmt = $db->prepare("\n                    UPDATE projects\n                    SET feature_status = 'eligible',\n                        " . ($has_feature_queue_type_column ? "feature_queue_type = NULL," : '') . "\n                        " . ($has_feature_requested_at_column ? "feature_requested_at = COALESCE(feature_requested_at, NOW())," : '') . "\n                        updated_at = NOW()\n                    WHERE id = ?\n                      AND LOWER(COALESCE(NULLIF(TRIM(approval_status), ''), 'pending')) = 'approved'\n                      AND COALESCE(avg_rating, 0) >= ?\n                      AND COALESCE(total_reviews, 0) >= ?\n                      AND LOWER(COALESCE(NULLIF(TRIM(feature_status), ''), 'none')) = 'none'\n                      AND COALESCE(is_featured, 0) = 0\n                ");
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
        $message = in_array($action, ['approve_feature', 'reject_feature'], true)
            ? 'Featured badge review updated.'
            : 'Project moderation action applied.';
    } else {
        $message = 'Invalid project moderation payload.';
        $message_type = 'error';
    }
}

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
    LIMIT 200
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$projects = $stmt->fetchAll();

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
        return ['label' => 'Featured Live', 'class' => 'status-approved'];
    }
    if ($feature_status === 'pending_review') {
        return ['label' => 'Feature Review Pending', 'class' => 'status-under-review'];
    }
    if ($feature_status === 'rejected') {
        return ['label' => 'Feature Rejected', 'class' => 'status-rejected'];
    }
    if ($feature_status === 'eligible') {
        return ['label' => 'Eligible For Feature Review', 'class' => 'status-pending'];
    }
    if ((int) $meets_feature_criteria === 1) {
        return ['label' => 'Eligible For Feature Review', 'class' => 'status-pending'];
    }

    return ['label' => 'Not Eligible', 'class' => 'status-disabled'];
};
?>

<?php if ($message !== ''): ?>
    <div class="message <?php echo $message_type === 'error' ? 'message-error' : 'message-success'; ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="panel admin-note-card">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Listings</span>
            <h2>Project Moderation</h2>
            <p class="muted">Project states are moderated separately from featured-badge review. Featured review only opens after the quality thresholds are met.</p>
        </div>
    </div>
    <div class="admin-metric-grid">
        <div class="admin-metric-card">
            <span class="admin-metric-label">Loaded Projects</span>
            <strong><?php echo number_format((int) $project_summary['loaded']); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Verified</span>
            <strong><?php echo number_format((int) $project_summary['verified']); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Featured Live</span>
            <strong><?php echo number_format((int) $project_summary['featured']); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Feature Queue</span>
            <strong><?php echo number_format((int) $project_summary['feature_queue']); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Priority Queue</span>
            <strong><?php echo number_format((int) $project_summary['priority_queue']); ?></strong>
        </div>
        <div class="admin-metric-card">
            <span class="admin-metric-label">Sponsored Live</span>
            <strong><?php echo number_format((int) $project_summary['sponsored']); ?></strong>
        </div>
    </div>
</div>

<div class="panel admin-note-card">
    <div class="admin-section-head">
        <div>
            <span class="admin-kicker">Promotion Control</span>
            <h2>Featured, Priority Review & Sponsored</h2>
            <p class="muted">This section is always visible. <strong>Featured is earned</strong>, while <strong>Priority Review</strong> and <strong>Sponsored</strong> are paid operational/visibility layers.</p>
        </div>
    </div>
    <div class="admin-metric-grid">
        <div class="admin-metric-card"><span class="admin-metric-label">Eligible</span><strong><?php echo number_format((int) ($promotion_overview_stmt['eligible_count'] ?? 0)); ?></strong></div>
        <div class="admin-metric-card"><span class="admin-metric-label">Feature Queue</span><strong><?php echo number_format((int) ($promotion_overview_stmt['feature_queue_count'] ?? 0)); ?></strong></div>
        <div class="admin-metric-card"><span class="admin-metric-label">Priority Requested</span><strong><?php echo number_format((int) ($promotion_overview_stmt['priority_requested_count'] ?? 0)); ?></strong></div>
        <div class="admin-metric-card"><span class="admin-metric-label">Priority Active</span><strong><?php echo number_format((int) ($promotion_overview_stmt['priority_active_count'] ?? 0)); ?></strong></div>
        <div class="admin-metric-card"><span class="admin-metric-label">Sponsored Requested</span><strong><?php echo number_format((int) ($promotion_overview_stmt['sponsored_requested_count'] ?? 0)); ?></strong></div>
        <div class="admin-metric-card"><span class="admin-metric-label">Sponsored Live</span><strong><?php echo number_format((int) ($promotion_overview_stmt['sponsored_live'] ?? 0)); ?></strong></div>
    </div>
    <div class="table-wrap" style="margin-top:16px;">
        <table class="responsive-table">
            <thead>
            <tr>
                <th>Project</th>
                <th>Promotion State</th>
                <th>Quality</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($promotion_queue_stmt)): ?>
                <tr><td colspan="4" class="muted">No promotion requests or eligible projects are waiting right now.</td></tr>
            <?php else: ?>
                <?php foreach ($promotion_queue_stmt as $promo_project): ?>
                    <?php
                    $promo_feature_status = strtolower(trim((string) ($promo_project['feature_status'] ?? 'none')));
                    $promo_priority_status = strtolower(trim((string) ($promo_project['priority_review_status'] ?? 'none')));
                    $promo_sponsored_status = strtolower(trim((string) ($promo_project['sponsored_status'] ?? 'none')));
                    ?>
                    <tr>
                        <td data-label="Project"><strong><?php echo htmlspecialchars((string) ($promo_project['name'] ?? 'Project'), ENT_QUOTES, 'UTF-8'); ?></strong><br><span class="muted"><?php echo htmlspecialchars((string) ($promo_project['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td data-label="Promotion State">
                            <?php if ($promo_feature_status === 'eligible'): ?><span class="status-pill status-pending">Eligible</span><br><?php endif; ?>
                            <?php if ($promo_feature_status === 'pending_review'): ?><span class="status-pill status-under-review">Feature Queue</span><br><?php endif; ?>
                            <?php if ($promo_priority_status === 'requested'): ?><span class="status-pill status-approved">Priority Requested</span><br><?php endif; ?>
                            <?php if ($promo_priority_status === 'active'): ?><span class="status-pill status-approved">Priority Active</span><br><?php endif; ?>
                            <?php if ($promo_sponsored_status === 'requested'): ?><span class="status-pill status-flagged">Sponsored Requested</span><br><?php endif; ?>
                            <?php if ((int) ($promo_project['is_sponsored'] ?? 0) === 1): ?><span class="status-pill status-flagged">Sponsored Live</span><?php endif; ?>
                        </td>
                        <td data-label="Quality"><span class="muted"><?php echo number_format((int) ($promo_project['total_reviews'] ?? 0)); ?> reviews • <?php echo number_format((float) ($promo_project['avg_rating'] ?? 0), 1); ?>/5</span></td>
                        <td data-label="Action">
                            <form method="POST" action="" class="inline-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="project_id" value="<?php echo (int) $promo_project['id']; ?>">
                                <?php if ($promo_priority_status === 'requested'): ?>
                                    <button type="submit" name="action" value="approve_priority_request" class="btn btn-primary">Activate Priority</button>
                                    <button type="submit" name="action" value="reject_priority_request" class="btn btn-secondary">Reject Priority</button>
                                <?php elseif ($promo_feature_status === 'eligible'): ?>
                                    <button type="submit" name="action" value="approve_feature" class="btn btn-primary">Force Feature Approve</button>
                                <?php endif; ?>
                                <?php if ($promo_sponsored_status === 'requested'): ?>
                                    <button type="submit" name="action" value="activate_sponsored" class="btn btn-primary">Activate Sponsored</button>
                                    <button type="submit" name="action" value="reject_sponsored" class="btn btn-secondary">Reject Sponsored</button>
                                <?php elseif ((int) ($promo_project['is_sponsored'] ?? 0) === 1): ?>
                                    <button type="submit" name="action" value="expire_sponsored" class="btn btn-secondary">Expire Sponsored</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($feature_queue)): ?>
    <div class="panel feature-review-banner">
        <div>
            <strong><?php echo number_format(count($feature_queue)); ?> project(s) are waiting for featured badge review.</strong>
            <p class="muted" style="margin:6px 0 0;">Eligibility requires an approved project, rating of at least <?php echo number_format($feature_rating_threshold, 1); ?>/5, and at least <?php echo number_format($feature_review_threshold); ?> approved reviews.</p>
        </div>
        <button type="button" class="btn btn-primary" id="openFeatureQueueBtn">Open Feature Queue</button>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="admin-toolbar">
        <div>
            <h3 style="margin:0 0 6px;">Filter Project Queue</h3>
            <p class="muted" style="margin:0;">Use status filters to work the listing queue in a tighter flow.</p>
        </div>
        <form method="GET" action="" class="project-filter-grid">
            <input type="text" name="q" placeholder="Search name, slug, website, developer" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
            <select name="status">
                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                <option value="flagged" <?php echo $status_filter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
            </select>
            <button type="submit" class="btn btn-secondary">Filter</button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="table-wrap">
        <table class="responsive-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>Project</th>
                <th>Category</th>
                <th>Submitted By</th>
                <th>Quality</th>
                <th>Status</th>
                <th>Featured Badge</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $project): ?>
                <?php
                $approval_status = strtolower(trim((string) ($project['moderation_status'] ?? $project['approval_status'] ?? 'pending')));
                $status_class = 'status-pending';
                if ($approval_status === 'approved') {
                    $status_class = 'status-approved';
                } elseif ($approval_status === 'rejected') {
                    $status_class = 'status-rejected';
                } elseif ($approval_status === 'under_review') {
                    $status_class = 'status-under-review';
                } elseif ($approval_status === 'flagged') {
                    $status_class = 'status-flagged';
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
                ?>
                <tr>
                    <td data-label="ID"><?php echo (int) $project['id']; ?></td>
                    <td data-label="Project">
                        <strong><?php echo htmlspecialchars((string) $project['name'], ENT_QUOTES, 'UTF-8'); ?></strong><br>
                        <span class="muted">/<?php echo htmlspecialchars((string) ($project['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span><br>
                        <?php if (!empty($project['website_url'])): ?>
                            <a href="<?php echo htmlspecialchars((string) $project['website_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="muted">Visit website ↗</a>
                        <?php endif; ?>
                    </td>
                    <td data-label="Category"><?php echo htmlspecialchars((string) ($project['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td data-label="Submitted By">
                        <?php echo htmlspecialchars((string) ($project['creator_username'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?><br>
                        <span class="muted"><?php echo htmlspecialchars((string) ($project['creator_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span><br>
                        <span class="status-pill status-pending"><?php echo htmlspecialchars($dev_status, ENT_QUOTES, 'UTF-8'); ?></span>
                    </td>
                    <td data-label="Quality">
                        <strong><?php echo number_format((float) ($project['project_score'] ?? 0), 1); ?></strong> / 100<br>
                        <span class="muted"><?php echo number_format((int) ($project['total_reviews'] ?? 0)); ?> approved reviews • <?php echo number_format((float) ($project['avg_rating'] ?? 0), 1); ?> avg</span><br>
                        <span class="muted"><?php echo ((int) ($project['is_verified'] ?? 0)) === 1 ? 'Verified by trust engine' : 'Below verification threshold'; ?></span>
                    </td>
                    <td data-label="Status"><span class="status-pill <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $approval_status)), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td data-label="Featured Badge">
                        <span class="status-pill <?php echo $feature_state['class']; ?>"><?php echo htmlspecialchars($feature_state['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if ((int) ($project['is_sponsored'] ?? 0) === 1): ?>
                            <br><span class="status-pill status-flagged">Sponsored Live</span>
                        <?php elseif ($sponsored_status === 'requested'): ?>
                            <br><span class="status-pill status-under-review">Sponsored Requested</span>
                        <?php endif; ?>
                        <?php if ($priority_status === 'active'): ?>
                            <br><span class="status-pill status-approved">Priority Queue</span>
                        <?php elseif ($priority_status === 'requested'): ?>
                            <br><span class="status-pill status-under-review">Priority Requested</span>
                        <?php endif; ?>
                        <div class="feature-criteria-note">
                            Min rating: <?php echo number_format($feature_rating_threshold, 1); ?>/5<br>
                            Min approved reviews: <?php echo number_format($feature_review_threshold); ?>
                        </div>
                    </td>
                    <td data-label="Action">
                        <form method="POST" action="" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="project_id" value="<?php echo (int) $project['id']; ?>">
                            <button
                                type="button"
                                class="btn btn-secondary project-view-btn"
                                data-project-name="<?php echo htmlspecialchars((string) ($project['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-project-slug="<?php echo htmlspecialchars((string) ($project['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-category="<?php echo htmlspecialchars((string) ($project['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-description="<?php echo htmlspecialchars((string) ($project['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-website-url="<?php echo htmlspecialchars((string) ($project['website_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-twitter-url="<?php echo htmlspecialchars((string) ($project['twitter_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-telegram-url="<?php echo htmlspecialchars((string) ($project['telegram_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-discord-url="<?php echo htmlspecialchars((string) ($project['discord_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-github-url="<?php echo htmlspecialchars((string) ($project['github_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-contract-address="<?php echo htmlspecialchars((string) ($project['contract_address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-network="<?php echo htmlspecialchars((string) ($project['network'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-live-since="<?php echo htmlspecialchars((string) ($project['project_live_since'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-token-status="<?php echo htmlspecialchars((string) ($project['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-min-holding="<?php echo htmlspecialchars((string) ($project['min_holding_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-max-reward="<?php echo htmlspecialchars((string) ($project['max_reward_rex'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                data-holding-days="<?php echo htmlspecialchars((string) ($project['required_holding_days'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                            >View</button>
                            <?php if (!empty($project['website_url'])): ?>
                                <a href="<?php echo htmlspecialchars((string) $project['website_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary">Inspect</a>
                            <?php endif; ?>
                            <button type="submit" name="action" value="mark_under_review" class="btn btn-secondary">Review</button>
                            <button type="submit" name="action" value="approve" class="btn btn-primary">Approve</button>
                            <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
                            <?php if ($has_feature_status_column && $feature_status === 'pending_review'): ?>
                                <button type="submit" name="action" value="approve_feature" class="btn btn-primary">Approve Feature</button>
                                <button type="submit" name="action" value="reject_feature" class="btn btn-secondary">Reject Feature</button>
                            <?php endif; ?>
                            <?php if ($priority_status === 'requested'): ?>
                                <button type="submit" name="action" value="approve_priority_request" class="btn btn-primary">Activate Priority</button>
                                <button type="submit" name="action" value="reject_priority_request" class="btn btn-secondary">Reject Priority</button>
                            <?php endif; ?>
                            <?php if ($sponsored_status === 'requested'): ?>
                                <button type="submit" name="action" value="activate_sponsored" class="btn btn-primary">Activate Sponsored</button>
                                <button type="submit" name="action" value="reject_sponsored" class="btn btn-secondary">Reject Sponsored</button>
                            <?php elseif ((int) ($project['is_sponsored'] ?? 0) === 1): ?>
                                <button type="submit" name="action" value="expire_sponsored" class="btn btn-secondary">Expire Sponsored</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-modal" id="projectDetailsModal" aria-hidden="true">
    <div class="admin-modal-card admin-modal-card-lg">
        <div class="admin-modal-header">
            <div>
                <span class="admin-kicker">Project Profile</span>
                <h3 id="projectModalTitle">Project Details</h3>
            </div>
            <button type="button" class="admin-modal-close" id="projectModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="admin-modal-body">
            <div class="trust-modal-grid">
                <div class="trust-modal-card"><h4>Identity</h4><div class="trust-detail-list" id="projectIdentityBlock"></div></div>
                <div class="trust-modal-card"><h4>Technicals</h4><div class="trust-detail-list" id="projectTechnicalBlock"></div></div>
                <div class="trust-modal-card trust-modal-card-wide"><h4>Tokenomics & Links</h4><div class="trust-detail-list" id="projectTokenomicsBlock"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('projectDetailsModal');
    var closeBtn = document.getElementById('projectModalClose');
    if (!modal || !closeBtn) return;

    var title = document.getElementById('projectModalTitle');
    var identity = document.getElementById('projectIdentityBlock');
    var technical = document.getElementById('projectTechnicalBlock');
    var tokenomics = document.getElementById('projectTokenomicsBlock');

    function esc(v) { var d = document.createElement('div'); d.innerText = v == null ? '' : String(v); return d.innerHTML; }
    function linkBtn(url, label, tone, iconClass) {
        if (!url) return '<span class="status-pill status-disabled">' + esc(label) + ' N/A</span>';
        return '<a class="btn ' + (tone || 'btn-secondary') + ' project-link-btn" href="' + esc(url) + '" target="_blank" rel="noopener noreferrer"><i class="' + esc(iconClass || 'fas fa-link') + '"></i> ' + esc(label) + '</a>';
    }

    function openModal(btn) {
        title.textContent = (btn.dataset.projectName || 'Project') + ' Details';
        identity.innerHTML =
            '<div><strong>Name:</strong> ' + esc(btn.dataset.projectName || '-') + '</div>' +
            '<div><strong>Slug:</strong> /' + esc(btn.dataset.projectSlug || '-') + '</div>' +
            '<div><strong>Category:</strong> <span class="status-pill status-under-review">' + esc(btn.dataset.category || '-') + '</span></div>' +
            '<div><strong>Description:</strong> ' + esc(btn.dataset.description || '-') + '</div>';
        technical.innerHTML =
            '<div><strong>Network:</strong> <span class="status-pill status-pending">' + esc(btn.dataset.network || '-') + '</span></div>' +
            '<div><strong>Contract:</strong> <div class="copy-row"><code id="modalContractText">' + esc(btn.dataset.contractAddress || '-') + '</code><button type="button" class="btn btn-secondary btn-xs" id="copyContractBtn"><i class="fas fa-copy"></i> Copy</button></div></div>' +
            '<div><strong>Live Since:</strong> ' + esc(btn.dataset.liveSince || '-') + '</div>' +
            '<div><strong>Project Status:</strong> <span class="status-pill status-approved">' + esc(btn.dataset.tokenStatus || '-') + '</span></div>';
        tokenomics.innerHTML =
            '<div><strong>Min Holding:</strong> ' + esc(btn.dataset.minHolding || '-') + '</div>' +
            '<div><strong>Max Reward:</strong> ' + esc(btn.dataset.maxReward || '-') + '</div>' +
            '<div><strong>Holding Days:</strong> ' + esc(btn.dataset.holdingDays || '-') + '</div>' +
            '<div class="project-link-grid">' +
                linkBtn(btn.dataset.websiteUrl, 'Website', 'btn-primary', 'fas fa-globe') +
                linkBtn(btn.dataset.twitterUrl, 'X', 'btn-secondary', 'fa-brands fa-x-twitter') +
                linkBtn(btn.dataset.telegramUrl, 'Telegram', 'btn-secondary', 'fa-brands fa-telegram') +
                linkBtn(btn.dataset.discordUrl, 'Discord', 'btn-secondary', 'fa-brands fa-discord') +
                linkBtn(btn.dataset.githubUrl, 'GitHub', 'btn-secondary', 'fa-brands fa-github') +
            '</div>';

        var copyBtn = document.getElementById('copyContractBtn');
        var contractText = document.getElementById('modalContractText');
        if (copyBtn && contractText) {
            copyBtn.addEventListener('click', function() {
                var raw = (contractText.textContent || '').trim();
                if (!raw || raw === '-') return;
                navigator.clipboard.writeText(raw).then(function() {
                    copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied';
                    setTimeout(function() { copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 1400);
                });
            });
        }

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.project-view-btn').forEach(function(btn) {
        btn.addEventListener('click', function() { openModal(btn); });
    });
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && modal.classList.contains('show')) closeModal(); });
})();
</script>

<?php if (!empty($feature_queue)): ?>
    <div class="feature-queue-modal" id="featureQueueModal" hidden>
        <div class="feature-queue-backdrop" id="featureQueueBackdrop"></div>
        <div class="feature-queue-dialog" role="dialog" aria-modal="true" aria-labelledby="featureQueueTitle">
            <div class="feature-queue-head">
                <div>
                    <span class="admin-kicker">Feature Queue</span>
                    <h3 id="featureQueueTitle">Projects Ready For Featured Badge Review</h3>
                    <p class="muted">These projects are already approved and now meet both feature quality thresholds.</p>
                </div>
                <button type="button" class="feature-queue-close" id="closeFeatureQueueBtn" aria-label="Close feature queue">&times;</button>
            </div>
            <div class="feature-queue-list">
                <?php foreach ($feature_queue as $queued_project): ?>
                    <div class="feature-queue-item">
                        <div>
                            <strong><?php echo htmlspecialchars((string) ($queued_project['name'] ?? 'Project'), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <div class="feature-queue-meta">
                                Rating <?php echo number_format((float) ($queued_project['avg_rating'] ?? 0), 1); ?>/5
                                • <?php echo number_format((int) ($queued_project['total_reviews'] ?? 0)); ?> approved reviews
                            </div>
                        </div>
                        <form method="POST" action="" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(adminCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="project_id" value="<?php echo (int) $queued_project['id']; ?>">
                            <button type="submit" name="action" value="approve_feature" class="btn btn-primary">Approve Feature</button>
                            <button type="submit" name="action" value="reject_feature" class="btn btn-secondary">Reject Feature</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('featureQueueModal');
            const openBtn = document.getElementById('openFeatureQueueBtn');
            const closeBtn = document.getElementById('closeFeatureQueueBtn');
            const backdrop = document.getElementById('featureQueueBackdrop');

            if (!modal) {
                return;
            }

            const openModal = function () {
                modal.hidden = false;
                document.body.classList.add('feature-queue-open');
            };

            const closeModal = function () {
                modal.hidden = true;
                document.body.classList.remove('feature-queue-open');
            };

            if (openBtn) {
                openBtn.addEventListener('click', openModal);
            }
            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }
            if (backdrop) {
                backdrop.addEventListener('click', closeModal);
            }

            window.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeModal();
                }
            });

            openModal();
        })();
    </script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
