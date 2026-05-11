<?php
$page_title = 'Review Insights';
$activePage = 'review-insights';
require_once __DIR__ . '/includes/header.php';

echo '<link rel="stylesheet" href="' . BASE_URL . '/devhub/assets/css/reviews.css">';

$user_id = getCurrentUserId();
$db = getDevHubDB();

$status_filter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$valid_statuses = ['all', 'approved', 'rejected', 'pending', 'flagged'];
if (!in_array($status_filter, $valid_statuses, true)) {
    $status_filter = 'all';
}

$project_filter = (int) ($_GET['project_id'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;

$projects_stmt = $db->prepare(
    "SELECT id, name FROM projects WHERE created_by = ? ORDER BY name ASC"
);
$projects_stmt->execute([$user_id]);
$projects = $projects_stmt->fetchAll() ?: [];

$allowed_project_ids = array_map(static function ($row) {
    return (int) ($row['id'] ?? 0);
}, $projects);

if ($project_filter > 0 && !in_array($project_filter, $allowed_project_ids, true)) {
    $project_filter = 0;
}

$where = ["p.created_by = ?"];
$params = [$user_id];

if ($status_filter !== 'all') {
    $where[] = "LOWER(COALESCE(NULLIF(TRIM(r.status), ''), 'pending')) = ?";
    $params[] = $status_filter;
}

if ($project_filter > 0) {
    $where[] = "r.project_id = ?";
    $params[] = $project_filter;
}

$where_sql = implode(' AND ', $where);

$count_stmt = $db->prepare(
    "SELECT COUNT(*)
    FROM reviews r
    INNER JOIN projects p ON p.id = r.project_id
    WHERE {$where_sql}"
);
$count_stmt->execute($params);
$total_reviews_filtered = (int) $count_stmt->fetchColumn();

$total_pages = max(1, (int) ceil($total_reviews_filtered / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

$reviews_stmt = $db->prepare(
    "SELECT
        r.id,
        r.project_id,
        p.name AS project_name,
        p.category AS project_category,
        COALESCE(NULLIF(TRIM(r.review_title), ''), 'Untitled Review') AS review_title,
        COALESCE(r.review_content, '') AS review_content,
        COALESCE(r.pros, '') AS pros,
        COALESCE(r.cons, '') AS cons,
        COALESCE(r.rating, 0) AS rating,
        LOWER(COALESCE(NULLIF(TRIM(r.status), ''), 'pending')) AS status,
        r.created_at
    FROM reviews r
    INNER JOIN projects p ON p.id = r.project_id
    WHERE {$where_sql}
    ORDER BY r.created_at DESC, r.id DESC
    LIMIT {$per_page} OFFSET {$offset}"
);
$reviews_stmt->execute($params);
$reviews = $reviews_stmt->fetchAll() ?: [];

$stats_stmt = $db->prepare(
    "SELECT
        SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(r.status), ''), 'pending')) = 'approved' THEN 1 ELSE 0 END) AS approved_reviews,
        SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(r.status), ''), 'pending')) = 'rejected' THEN 1 ELSE 0 END) AS rejected_reviews,
        SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(r.status), ''), 'pending')) = 'pending' THEN 1 ELSE 0 END) AS pending_reviews,
        SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(r.status), ''), 'pending')) = 'flagged' THEN 1 ELSE 0 END) AS flagged_reviews
    FROM reviews r
    INNER JOIN projects p ON p.id = r.project_id
    WHERE p.created_by = ?"
);
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch() ?: [];

$approved_reviews = (int) ($stats['approved_reviews'] ?? 0);
$rejected_reviews = (int) ($stats['rejected_reviews'] ?? 0);
$pending_reviews = (int) ($stats['pending_reviews'] ?? 0);
$flagged_reviews = (int) ($stats['flagged_reviews'] ?? 0);

$status_label = static function (string $status): string {
    $map = [
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'pending' => 'Pending',
        'flagged' => 'Flagged',
    ];

    return $map[$status] ?? 'Pending';
};

$build_page_url = static function (int $target_page) use ($status_filter, $project_filter): string {
    $query = [
        'status' => $status_filter,
        'project_id' => $project_filter,
        'page' => $target_page,
    ];
    return BASE_URL . '/devhub/reviews.php?' . http_build_query($query);
};
?>

<div class="review-insights-wrapper">
    <div class="review-insights-head">
        <h1><i class="fas fa-clipboard-list"></i> Review Insights</h1>
        <p>Read community feedback to improve your projects. Sensitive reviewer and proof data is hidden for security.</p>
    </div>

    <div class="review-insights-stats">
        <article class="insight-stat">
            <h3><?php echo number_format($approved_reviews); ?></h3>
            <p>Approved Reviews</p>
        </article>
        <article class="insight-stat">
            <h3><?php echo number_format($rejected_reviews); ?></h3>
            <p>Rejected Reviews</p>
        </article>
        <article class="insight-stat">
            <h3><?php echo number_format($pending_reviews); ?></h3>
            <p>Pending Reviews</p>
        </article>
        <article class="insight-stat">
            <h3><?php echo number_format($flagged_reviews); ?></h3>
            <p>Flagged Reviews</p>
        </article>
    </div>

    <form method="GET" class="review-filters">
        <input type="hidden" name="page" value="1">
        <label>
            Status
            <select name="status">
                <?php foreach ($valid_statuses as $status_option): ?>
                    <option value="<?php echo htmlspecialchars($status_option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $status_filter === $status_option ? 'selected' : ''; ?>>
                        <?php echo ucfirst($status_option); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Project
            <select name="project_id">
                <option value="0">All Projects</option>
                <?php foreach ($projects as $project): ?>
                    <?php $pid = (int) ($project['id'] ?? 0); ?>
                    <option value="<?php echo $pid; ?>" <?php echo $project_filter === $pid ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) ($project['name'] ?? 'Project'), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="btn-filter">Apply</button>
    </form>

    <?php if (!empty($reviews)): ?>
        <div class="insights-list">
            <?php foreach ($reviews as $review): ?>
                <?php
                $status = (string) ($review['status'] ?? 'pending');
                $title = (string) ($review['review_title'] ?? 'Untitled Review');
                $content = trim((string) ($review['review_content'] ?? ''));
                $pros = trim((string) ($review['pros'] ?? ''));
                $cons = trim((string) ($review['cons'] ?? ''));
                $rating = (float) ($review['rating'] ?? 0);
                $created_label = formatDevhubDateTime((string) ($review['created_at'] ?? ''));
                ?>
                <article class="insight-card">
                    <div class="insight-card-top">
                        <div>
                            <h3><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="insight-project">
                                <?php echo htmlspecialchars((string) ($review['project_name'] ?? 'Project'), ENT_QUOTES, 'UTF-8'); ?>
                                • <?php echo htmlspecialchars((string) ($review['project_category'] ?? 'Uncategorized'), ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        </div>
                        <div class="insight-badges">
                            <span class="status-pill status-<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($status_label($status), ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="rating-pill"><?php echo number_format($rating, 1); ?>/5</span>
                        </div>
                    </div>

                    <div class="insight-body">
                        <h4>Description</h4>
                        <p><?php echo nl2br(htmlspecialchars($content !== '' ? $content : 'No description provided.', ENT_QUOTES, 'UTF-8')); ?></p>
                    </div>

                    <div class="insight-columns">
                        <div>
                            <h5>Pros</h5>
                            <p><?php echo nl2br(htmlspecialchars($pros !== '' ? $pros : 'No pros shared.', ENT_QUOTES, 'UTF-8')); ?></p>
                        </div>
                        <div>
                            <h5>Cons</h5>
                            <p><?php echo nl2br(htmlspecialchars($cons !== '' ? $cons : 'No cons shared.', ENT_QUOTES, 'UTF-8')); ?></p>
                        </div>
                    </div>

                    <div class="insight-foot">
                        Submitted: <?php echo htmlspecialchars($created_label, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($total_pages > 1): ?>
            <div class="insights-pagination">
                <a class="page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : htmlspecialchars($build_page_url($page - 1), ENT_QUOTES, 'UTF-8'); ?>">&larr; Previous</a>
                <span class="page-indicator">Page <?php echo number_format($page); ?> of <?php echo number_format($total_pages); ?></span>
                <a class="page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo $page >= $total_pages ? '#' : htmlspecialchars($build_page_url($page + 1), ENT_QUOTES, 'UTF-8'); ?>">Next &rarr;</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="insights-empty">
            <i class="fas fa-inbox"></i>
            <h3>No reviews found</h3>
            <p>Try changing your filter, or wait for new moderated reviews to arrive.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
