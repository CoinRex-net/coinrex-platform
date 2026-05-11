<?php
/**
 * CoinRex My Reviews Page
 * Location: /coinrex/my-reviews.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

requireProjectReviewAccess('/taskhub.php');

$db = getDBConnection();
ensureLevelEngineSchema($db);
$user = getCurrentUser();
$user_level_state = getUserLevelState($user, $db);

$has_wallet_type = tableHasColumn('reviews', 'wallet_type');
$has_final_rex = tableHasColumn('reviews', 'final_rex');
$has_review_score = tableHasColumn('reviews', 'review_score');
$has_proof_status = tableHasColumn('reviews', 'proof_status');
$has_rejection_reason = tableHasColumn('reviews', 'rejection_reason');
$has_approval_note = tableHasColumn('reviews', 'approval_note');
$has_proof_rejection_reason = tableHasColumn('reviews', 'proof_rejection_reason');
$has_reviewed_at = tableHasColumn('reviews', 'reviewed_at');
$has_proof_verified_at = tableHasColumn('reviews', 'proof_verified_at');
$has_auto_approved_at = tableHasColumn('reviews', 'auto_approved_at');
$has_project_verified = tableHasColumn('projects', 'is_verified');

$status_filter = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$proof_filter = strtolower(trim((string) ($_GET['proof'] ?? 'all')));
$search_query = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 8;

$valid_status_filters = ['all', 'pending', 'approved', 'rejected', 'flagged'];
$valid_proof_filters = ['all', 'pending', 'verified', 'rejected', 'flagged'];

if (!in_array($status_filter, $valid_status_filters, true)) {
    $status_filter = 'all';
}
if (!in_array($proof_filter, $valid_proof_filters, true)) {
    $proof_filter = 'all';
}
if (!$has_proof_status) {
    $proof_filter = 'all';
}

function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function buildMyReviewsUrl(array $overrides = [])
{
    $params = [
        'status' => $_GET['status'] ?? 'all',
        'proof' => $_GET['proof'] ?? 'all',
        'q' => $_GET['q'] ?? '',
        'page' => $_GET['page'] ?? 1,
    ];

    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($params[$key]);
            continue;
        }
        $params[$key] = $value;
    }

    if (($params['status'] ?? 'all') === 'all') {
        unset($params['status']);
    }
    if (($params['proof'] ?? 'all') === 'all') {
        unset($params['proof']);
    }
    if (trim((string) ($params['q'] ?? '')) === '') {
        unset($params['q']);
    }
    if ((int) ($params['page'] ?? 1) <= 1) {
        unset($params['page']);
    }

    $query = http_build_query($params);
    return BASE_URL . '/my-reviews.php' . ($query !== '' ? ('?' . $query) : '');
}

function reviewStatusMeta($status)
{
    $status = strtolower(trim((string) $status));
    $map = [
        'approved' => ['label' => 'Approved', 'class' => 'approved', 'icon' => 'fas fa-check-circle'],
        'rejected' => ['label' => 'Rejected', 'class' => 'rejected', 'icon' => 'fas fa-times-circle'],
        'flagged' => ['label' => 'Flagged', 'class' => 'flagged', 'icon' => 'fas fa-flag'],
        'pending' => ['label' => 'Pending Review', 'class' => 'pending', 'icon' => 'fas fa-hourglass-half'],
    ];

    return $map[$status] ?? ['label' => ucfirst($status !== '' ? $status : 'Pending'), 'class' => 'pending', 'icon' => 'fas fa-hourglass-half'];
}

function proofStatusMeta($status)
{
    $status = strtolower(trim((string) $status));
    $map = [
        'verified' => ['label' => 'Proof Verified', 'class' => 'verified', 'icon' => 'fas fa-check-circle'],
        'rejected' => ['label' => 'Proof Rejected', 'class' => 'rejected', 'icon' => 'fas fa-times-circle'],
        'flagged' => ['label' => 'Proof Flagged', 'class' => 'flagged', 'icon' => 'fas fa-exclamation-triangle'],
        'pending' => ['label' => 'Proof Pending', 'class' => 'pending', 'icon' => 'fas fa-search'],
    ];

    return $map[$status] ?? ['label' => 'Proof Pending', 'class' => 'pending', 'icon' => 'fas fa-search'];
}

function maskValue($value, $start = 8, $end = 6)
{
    $value = trim((string) $value);
    if ($value === '') {
        return 'Not provided';
    }

    $length = strlen($value);
    if ($length <= ($start + $end + 3)) {
        return $value;
    }

    return substr($value, 0, $start) . '...' . substr($value, -$end);
}

function walletTypeMeta($wallet_type)
{
    $wallet_type = strtolower(trim((string) $wallet_type));
    if ($wallet_type === 'custodial') {
        return ['label' => 'Custodial', 'class' => 'custodial', 'icon' => 'fas fa-university'];
    }

    return ['label' => 'Non-custodial', 'class' => 'non-custodial', 'icon' => 'fas fa-wallet'];
}

function rewardValue(array $review)
{
    $final_rex = (float) ($review['final_rex'] ?? 0);
    if ($final_rex > 0) {
        return $final_rex;
    }

    return (float) ($review['calculated_rex'] ?? 0);
}

function rewardLabel(array $review)
{
    $status = strtolower((string) ($review['status'] ?? 'pending'));
    $final_rex = (float) ($review['final_rex'] ?? 0);

    if ($status === 'approved' && $final_rex > 0) {
        return 'Earned';
    }

    return 'Estimate';
}

function scoreLabel($score)
{
    $score = (float) $score;
    if ($score >= 85) {
        return ['label' => 'High Trust', 'class' => 'high'];
    }
    if ($score >= 60) {
        return ['label' => 'Medium Trust', 'class' => 'medium'];
    }
    if ($score > 0) {
        return ['label' => 'Low Trust', 'class' => 'low'];
    }

    return ['label' => 'Pending Score', 'class' => 'pending'];
}

$stats = [
    'total_reviews' => 0,
    'approved_reviews' => 0,
    'pending_reviews' => 0,
    'rejected_reviews' => 0,
    'flagged_reviews' => 0,
    'proof_verified_reviews' => 0,
    'approved_rex' => 0,
    'average_rating' => 0,
];

try {
    $stats_query = "
        SELECT
            COUNT(*) AS total_reviews,
            SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) AS approved_reviews,
            SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) AS pending_reviews,
            SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_reviews,
            SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) AS flagged_reviews,
            " . ($has_proof_status
                ? "SUM(CASE WHEN r.proof_status = 'verified' THEN 1 ELSE 0 END)"
                : "0") . " AS proof_verified_reviews,
            SUM(CASE WHEN r.status = 'approved' THEN " . ($has_final_rex ? "COALESCE(NULLIF(r.final_rex, 0), r.calculated_rex, 0)" : "COALESCE(r.calculated_rex, 0)") . " ELSE 0 END) AS approved_rex,
            COALESCE(AVG(r.rating), 0) AS average_rating
        FROM reviews r
        WHERE r.user_id = ?
    ";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute([(int) $user['id']]);
    $stats = array_merge($stats, (array) $stats_stmt->fetch());
} catch (Throwable $e) {
    $stats = $stats;
}

$where_clauses = ["r.user_id = ?"];
$query_params = [(int) $user['id']];

if ($status_filter !== 'all') {
    $where_clauses[] = "r.status = ?";
    $query_params[] = $status_filter;
}
if ($has_proof_status && $proof_filter !== 'all') {
    $where_clauses[] = "r.proof_status = ?";
    $query_params[] = $proof_filter;
}
if ($search_query !== '') {
    $where_clauses[] = "(r.review_title LIKE ? OR r.review_content LIKE ? OR COALESCE(p.name, '') LIKE ?)";
    $like = '%' . $search_query . '%';
    $query_params[] = $like;
    $query_params[] = $like;
    $query_params[] = $like;
}

$where_sql = implode(' AND ', $where_clauses);

$total_reviews = 0;
$total_pages = 1;
$reviews = [];

try {
    $count_stmt = $db->prepare("
        SELECT COUNT(*) AS total
        FROM reviews r
        LEFT JOIN projects p ON p.id = r.project_id
        WHERE {$where_sql}
    ");
    $count_stmt->execute($query_params);
    $total_reviews = (int) ($count_stmt->fetch()['total'] ?? 0);
    $total_pages = max(1, (int) ceil($total_reviews / $per_page));
    if ($page > $total_pages) {
        $page = $total_pages;
    }
    $offset = ($page - 1) * $per_page;

    $review_fields = [
        'r.id',
        'r.project_id',
        'r.review_title',
        'r.review_content',
        'r.rating',
        'r.pros',
        'r.cons',
        'r.holding_amount',
        'r.holding_days',
        ($has_wallet_type ? 'r.wallet_type' : "'non_custodial' AS wallet_type"),
        'r.tx_hash',
        'r.wallet_address',
        'r.screenshot_url',
        'r.calculated_rex',
        ($has_final_rex ? 'r.final_rex' : '0.00 AS final_rex'),
        ($has_review_score ? 'r.review_score' : '0.00 AS review_score'),
        'r.status',
        ($has_proof_status ? 'r.proof_status' : "'pending' AS proof_status"),
        ($has_rejection_reason ? 'r.rejection_reason' : 'NULL AS rejection_reason'),
        ($has_approval_note ? 'r.approval_note' : 'NULL AS approval_note'),
        ($has_proof_rejection_reason ? 'r.proof_rejection_reason' : 'NULL AS proof_rejection_reason'),
        ($has_reviewed_at ? 'r.reviewed_at' : 'NULL AS reviewed_at'),
        ($has_proof_verified_at ? 'r.proof_verified_at' : 'NULL AS proof_verified_at'),
        ($has_auto_approved_at ? 'r.auto_approved_at' : 'NULL AS auto_approved_at'),
        'r.created_at',
        'r.updated_at',
        "COALESCE(p.name, 'Archived Project') AS project_name",
        'p.logo AS project_logo',
        ($has_project_verified ? 'COALESCE(p.is_verified, 0) AS project_verified' : '0 AS project_verified'),
    ];

    $reviews_sql = "
        SELECT " . implode(",\n            ", $review_fields) . "
        FROM reviews r
        LEFT JOIN projects p ON p.id = r.project_id
        WHERE {$where_sql}
        ORDER BY r.created_at DESC
        LIMIT " . (int) $per_page . " OFFSET " . (int) $offset . "
    ";
    $reviews_stmt = $db->prepare($reviews_sql);
    foreach ($query_params as $index => $value) {
        $reviews_stmt->bindValue($index + 1, $value);
    }
    $reviews_stmt->execute();
    $reviews = $reviews_stmt->fetchAll();

foreach ($reviews as &$review) {
    $review['project_logo'] = coinrexNormalizeMediaUrl((string) ($review['project_logo'] ?? ''));
}
unset($review);
} catch (Throwable $e) {
    $reviews = [];
    $total_reviews = 0;
    $total_pages = 1;
    $page = 1;
}

$page_title = 'My Reviews';
require_once __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/my-reviews.css">

<main class="my-reviews-main">
    <div class="my-reviews-shell">
        <section class="reviews-hero">
            <div class="reviews-hero-copy">
                <span class="hero-kicker">Private Review Center</span>
                <h1>Track Your <span class="gradient-text">Review Journey</span></h1>
                <p>See moderation progress, proof verification, reward outcomes, and review details in one place. Your current trust lane: <strong><?php echo esc($user_level_state['approval_label']); ?></strong>.</p>
            </div>
            <div class="reviews-hero-side">
                <div class="hero-pill hero-pill-strong">
                    <i class="fas fa-layer-group"></i>
                    <span><?php echo (int) ($stats['total_reviews'] ?? 0); ?> total reviews</span>
                </div>
                <div class="hero-pill">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo (int) ($stats['proof_verified_reviews'] ?? 0); ?> proof verified</span>
                </div>
                <div class="hero-pill">
                    <i class="fas fa-coins"></i>
                    <span><?php echo number_format((float) ($stats['approved_rex'] ?? 0), 2); ?> $REX earned</span>
                </div>
            </div>
        </section>

        <section class="reviews-overview">
            <article class="overview-card accent">
                <span class="overview-label">Approved</span>
                <strong><?php echo (int) ($stats['approved_reviews'] ?? 0); ?></strong>
                <p>Published or cleared reviews that made it through moderation.</p>
            </article>
            <article class="overview-card">
                <span class="overview-label">Pending</span>
                <strong><?php echo (int) ($stats['pending_reviews'] ?? 0); ?></strong>
                <p>Reviews still moving through moderation or proof checks.</p>
            </article>
            <article class="overview-card">
                <span class="overview-label">Rejected</span>
                <strong><?php echo (int) ($stats['rejected_reviews'] ?? 0); ?></strong>
                <p>Submissions that need correction, clearer proof, or stronger review quality.</p>
            </article>
            <article class="overview-card">
                <span class="overview-label">Average Rating</span>
                <strong><?php echo number_format((float) ($stats['average_rating'] ?? 0), 1); ?>/5</strong>
                <p>Your submitted experience score across all projects so far.</p>
            </article>
        </section>

        <section class="reviews-toolbar">
            <div class="toolbar-copy">
                <h2>My Review Activity</h2>
                <p>Use filters to narrow by moderation status, proof state, or project name.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/projects.php" class="toolbar-action">
                <i class="fas fa-plus"></i>
                <span>Submit New Review</span>
            </a>
        </section>

        <section class="review-filter-panel">
            <div class="status-chip-row">
                <?php foreach ($valid_status_filters as $status_option): ?>
                    <?php $is_active = $status_filter === $status_option; ?>
                    <a href="<?php echo esc(buildMyReviewsUrl(['status' => $status_option, 'page' => 1])); ?>" class="status-chip <?php echo $is_active ? 'active' : ''; ?>">
                        <?php echo esc($status_option === 'all' ? 'All Reviews' : ucfirst($status_option)); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="GET" class="review-filter-form">
                <input type="hidden" name="status" value="<?php echo esc($status_filter); ?>">
                <div class="filter-field search-field">
                    <label for="reviewSearch">Search</label>
                    <input type="text" id="reviewSearch" name="q" value="<?php echo esc($search_query); ?>" placeholder="Search by project, title, or review text">
                </div>
                <div class="filter-field">
                    <label for="proofFilter">Proof Status</label>
                    <select id="proofFilter" name="proof" <?php echo $has_proof_status ? '' : 'disabled'; ?>>
                        <?php foreach ($valid_proof_filters as $proof_option): ?>
                            <option value="<?php echo esc($proof_option); ?>" <?php echo $proof_filter === $proof_option ? 'selected' : ''; ?>>
                                <?php echo esc($proof_option === 'all' ? 'All Proof States' : ucfirst($proof_option)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$has_proof_status): ?>
                        <small>Proof-state filtering is not available in this database version yet.</small>
                    <?php endif; ?>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-filter">
                        <i class="fas fa-sliders"></i>
                        <span>Apply</span>
                    </button>
                    <a href="<?php echo BASE_URL; ?>/my-reviews.php" class="btn-clear">Reset</a>
                </div>
            </form>
        </section>

        <section class="results-meta">
            <div class="results-copy">
                <strong><?php echo (int) $total_reviews; ?></strong>
                <span><?php echo $total_reviews === 1 ? 'review matches your filter' : 'reviews match your filters'; ?></span>
            </div>
            <?php if ($search_query !== ''): ?>
                <div class="results-tag">
                    <i class="fas fa-search"></i>
                    <span><?php echo esc($search_query); ?></span>
                </div>
            <?php endif; ?>
        </section>

        <?php if (empty($reviews)): ?>
            <section class="reviews-empty-state">
                <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                <h3>No reviews found</h3>
                <p>Try changing the filter, or submit your first proof-backed review to start building your trust history.</p>
                <div class="empty-actions">
                    <a href="<?php echo BASE_URL; ?>/projects.php" class="btn-primary-empty">Browse Projects</a>
                    <a href="<?php echo BASE_URL; ?>/my-reviews.php" class="btn-secondary-empty">Clear Filters</a>
                </div>
            </section>
        <?php else: ?>
            <section class="review-card-grid">
                <?php foreach ($reviews as $review): ?>
                    <?php
                    $status_meta = reviewStatusMeta($review['status'] ?? 'pending');
                    $proof_meta = proofStatusMeta($review['proof_status'] ?? 'pending');
                    $wallet_meta = walletTypeMeta($review['wallet_type'] ?? 'non_custodial');
                    $score_meta = scoreLabel($review['review_score'] ?? 0);
                    $reward_value = rewardValue($review);
                    $review_excerpt = trim((string) ($review['review_content'] ?? ''));
                    if (mb_strlen($review_excerpt) > 180) {
                        $review_excerpt = mb_substr($review_excerpt, 0, 177) . '...';
                    }
                    ?>
                    <article class="review-activity-card">
                        <div class="card-topline">
                            <div class="project-identity">
                                <div class="project-logo-wrap">
                                    <?php if (!empty($review['project_logo'])): ?>
                                        <img src="<?php echo esc($review['project_logo']); ?>" alt="<?php echo esc($review['project_name']); ?>">
                                    <?php else: ?>
                                        <div class="project-logo-fallback"><?php echo esc(strtoupper(substr((string) $review['project_name'], 0, 2))); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="project-copy">
                                    <div class="project-name-row">
                                        <strong><?php echo esc($review['project_name']); ?></strong>
                                        <?php if (!empty($review['project_verified'])): ?>
                                            <span class="project-verified"><i class="fas fa-check-circle"></i> Verified</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="project-date">Submitted <?php echo date('M d, Y', strtotime((string) $review['created_at'])); ?></span>
                                </div>
                            </div>
                            <div class="status-stack">
                                <span class="status-pill <?php echo esc($status_meta['class']); ?>">
                                    <i class="<?php echo esc($status_meta['icon']); ?>"></i>
                                    <?php echo esc($status_meta['label']); ?>
                                </span>
                                <span class="status-pill proof <?php echo esc($proof_meta['class']); ?>">
                                    <i class="<?php echo esc($proof_meta['icon']); ?>"></i>
                                    <?php echo esc($proof_meta['label']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="review-body">
                            <div class="review-headline-row">
                                <h3><?php echo esc($review['review_title'] !== '' ? $review['review_title'] : 'Untitled Review'); ?></h3>
                                <div class="rating-chip">
                                    <i class="fas fa-star"></i>
                                    <span><?php echo number_format((float) ($review['rating'] ?? 0), 1); ?>/5</span>
                                </div>
                            </div>
                            <p class="review-excerpt"><?php echo esc($review_excerpt); ?></p>
                        </div>

                        <div class="review-insights">
                            <div class="insight-item">
                                <span>Reward</span>
                                <strong><?php echo number_format($reward_value, 2); ?> $REX</strong>
                                <small><?php echo esc(rewardLabel($review)); ?></small>
                            </div>
                            <div class="insight-item">
                                <span>Trust Score</span>
                                <strong><?php echo number_format((float) ($review['review_score'] ?? 0), 0); ?></strong>
                                <small class="score-tone <?php echo esc($score_meta['class']); ?>"><?php echo esc($score_meta['label']); ?></small>
                            </div>
                            <div class="insight-item">
                                <span>Wallet Type</span>
                                <strong><i class="<?php echo esc($wallet_meta['icon']); ?>"></i> <?php echo esc($wallet_meta['label']); ?></strong>
                                <small><?php echo esc(maskValue($review['wallet_address'] ?? '', 8, 6)); ?></small>
                            </div>
                            <div class="insight-item">
                                <span>TX Hash</span>
                                <strong><?php echo esc(maskValue($review['tx_hash'] ?? '', 10, 8)); ?></strong>
                                <small><?php echo esc((int) ($review['holding_days'] ?? 0)); ?> days held</small>
                            </div>
                        </div>

                        <?php if (!empty($review['rejection_reason']) || !empty($review['proof_rejection_reason']) || !empty($review['approval_note'])): ?>
                            <div class="review-note-strip">
                                <i class="fas fa-circle-info"></i>
                                <span>
                                    <?php
                                    $primary_note = (string) ($review['rejection_reason'] ?: ($review['proof_rejection_reason'] ?: $review['approval_note']));
                                    echo esc($primary_note);
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>

                        <div class="card-actions">
                            <button type="button" class="btn-card-primary" data-open-review="<?php echo (int) $review['id']; ?>">
                                <i class="fas fa-eye"></i>
                                <span>Open Details</span>
                            </button>
                            <a href="<?php echo BASE_URL; ?>/project-detail.php?id=<?php echo (int) $review['project_id']; ?>" class="btn-card-secondary">
                                <i class="fas fa-external-link-alt"></i>
                                <span>View Project</span>
                            </a>
                        </div>

                        <div class="review-detail-template" id="review-detail-<?php echo (int) $review['id']; ?>" hidden>
                            <div class="detail-header">
                                <div>
                                    <span class="detail-kicker">Review Detail</span>
                                    <h3><?php echo esc($review['review_title'] !== '' ? $review['review_title'] : 'Untitled Review'); ?></h3>
                                    <p><?php echo esc($review['project_name']); ?> | submitted <?php echo date('M d, Y', strtotime((string) $review['created_at'])); ?></p>
                                </div>
                                <div class="detail-status-group">
                                    <span class="status-pill <?php echo esc($status_meta['class']); ?>">
                                        <i class="<?php echo esc($status_meta['icon']); ?>"></i>
                                        <?php echo esc($status_meta['label']); ?>
                                    </span>
                                    <span class="status-pill proof <?php echo esc($proof_meta['class']); ?>">
                                        <i class="<?php echo esc($proof_meta['icon']); ?>"></i>
                                        <?php echo esc($proof_meta['label']); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="detail-grid">
                                <section class="detail-panel">
                                    <h4>Review Summary</h4>
                                    <div class="detail-metrics">
                                        <div><span>Rating</span><strong><?php echo number_format((float) ($review['rating'] ?? 0), 1); ?>/5</strong></div>
                                        <div><span>Reward</span><strong><?php echo number_format($reward_value, 2); ?> $REX</strong></div>
                                        <div><span>Trust Score</span><strong><?php echo number_format((float) ($review['review_score'] ?? 0), 0); ?></strong></div>
                                        <div><span>Wallet</span><strong><?php echo esc($wallet_meta['label']); ?></strong></div>
                                    </div>
                                    <div class="detail-copy">
                                        <h5>Full Review</h5>
                                        <p><?php echo nl2br(esc($review['review_content'] ?? '')); ?></p>
                                    </div>
                                    <?php if (!empty($review['pros']) || !empty($review['cons'])): ?>
                                        <div class="detail-columns">
                                            <div>
                                                <h5>What Felt Good</h5>
                                                <p><?php echo !empty($review['pros']) ? nl2br(esc($review['pros'])) : 'No notes added.'; ?></p>
                                            </div>
                                            <div>
                                                <h5>What Needs Improvement</h5>
                                                <p><?php echo !empty($review['cons']) ? nl2br(esc($review['cons'])) : 'No notes added.'; ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </section>
                                <section class="detail-panel">
                                    <h4>Proof & Moderation</h4>
                                    <div class="detail-data-list">
                                        <div>
                                            <span>Holding Amount</span>
                                            <strong>$<?php echo number_format((float) ($review['holding_amount'] ?? 0), 2); ?></strong>
                                        </div>
                                        <div>
                                            <span>Holding Duration</span>
                                            <strong><?php echo (int) ($review['holding_days'] ?? 0); ?> days</strong>
                                        </div>
                                        <div>
                                            <span>Wallet Address</span>
                                            <strong class="mono"><?php echo esc($review['wallet_address'] ?? ''); ?></strong>
                                            <button type="button" class="copy-inline" data-copy="<?php echo esc($review['wallet_address'] ?? ''); ?>">Copy</button>
                                        </div>
                                        <div>
                                            <span>TX Hash</span>
                                            <strong class="mono"><?php echo esc($review['tx_hash'] ?? ''); ?></strong>
                                            <button type="button" class="copy-inline" data-copy="<?php echo esc($review['tx_hash'] ?? ''); ?>">Copy</button>
                                        </div>
                                    </div>

                                    <?php if (!empty($review['approval_note']) || !empty($review['rejection_reason']) || !empty($review['proof_rejection_reason'])): ?>
                                        <div class="moderation-notes">
                                            <h5>Moderator Notes</h5>
                                            <?php if (!empty($review['approval_note'])): ?>
                                                <p><strong>Approval note:</strong> <?php echo esc($review['approval_note']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($review['rejection_reason'])): ?>
                                                <p><strong>Review reason:</strong> <?php echo esc($review['rejection_reason']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($review['proof_rejection_reason'])): ?>
                                                <p><strong>Proof reason:</strong> <?php echo esc($review['proof_rejection_reason']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="timeline-block">
                                        <h5>Timeline</h5>
                                        <div class="timeline-list">
                                            <div><span>Submitted</span><strong><?php echo date('M d, Y g:i A', strtotime((string) $review['created_at'])); ?></strong></div>
                                            <div><span>Last Updated</span><strong><?php echo date('M d, Y g:i A', strtotime((string) $review['updated_at'])); ?></strong></div>
                                            <?php if (!empty($review['reviewed_at'])): ?>
                                                <div><span>Moderated</span><strong><?php echo date('M d, Y g:i A', strtotime((string) $review['reviewed_at'])); ?></strong></div>
                                            <?php endif; ?>
                                            <?php if (!empty($review['proof_verified_at'])): ?>
                                                <div><span>Proof Checked</span><strong><?php echo date('M d, Y g:i A', strtotime((string) $review['proof_verified_at'])); ?></strong></div>
                                            <?php endif; ?>
                                            <?php if (!empty($review['auto_approved_at'])): ?>
                                                <div><span>Fast Lane</span><strong><?php echo date('M d, Y g:i A', strtotime((string) $review['auto_approved_at'])); ?></strong></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($review['screenshot_url'])): ?>
                                        <div class="proof-preview-panel">
                                            <h5>Screenshot Proof</h5>
                                            <a href="<?php echo esc($review['screenshot_url']); ?>" target="_blank" rel="noopener noreferrer" class="proof-preview-link">
                                                <img src="<?php echo esc($review['screenshot_url']); ?>" alt="Proof screenshot for <?php echo esc($review['project_name']); ?>">
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </section>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <?php if ($total_pages > 1): ?>
                <nav class="pagination-nav" aria-label="My reviews pagination">
                    <a class="pagination-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : esc(buildMyReviewsUrl(['page' => $page - 1])); ?>">
                        <i class="fas fa-arrow-left"></i>
                        <span>Previous</span>
                    </a>
                    <div class="pagination-pages">
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="<?php echo esc(buildMyReviewsUrl(['page' => $i])); ?>" class="pagination-page <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                    <a class="pagination-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo $page >= $total_pages ? '#' : esc(buildMyReviewsUrl(['page' => $page + 1])); ?>">
                        <span>Next</span>
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<div class="review-modal" id="reviewDetailModal" hidden>
    <div class="review-modal-backdrop" data-close-review-modal></div>
    <div class="review-modal-dialog" role="dialog" aria-modal="true" aria-label="Review details">
        <button type="button" class="review-modal-close" data-close-review-modal aria-label="Close review details">
            <i class="fas fa-times"></i>
        </button>
        <div class="review-modal-body" id="reviewModalBody"></div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const modal = document.getElementById('reviewDetailModal');
    const modalBody = document.getElementById('reviewModalBody');
    const openButtons = document.querySelectorAll('[data-open-review]');
    const closeButtons = document.querySelectorAll('[data-close-review-modal]');

    function closeModal() {
        if (!modal || !modalBody) return;
        modal.setAttribute('hidden', 'hidden');
        modalBody.innerHTML = '';
        document.body.classList.remove('review-modal-open');
    }

    function bindCopyButtons(scope) {
        scope.querySelectorAll('[data-copy]').forEach((button) => {
            button.addEventListener('click', async () => {
                const value = button.getAttribute('data-copy') || '';
                if (!value) return;
                try {
                    await navigator.clipboard.writeText(value);
                    const original = button.textContent;
                    button.textContent = 'Copied';
                    setTimeout(() => {
                        button.textContent = original;
                    }, 1400);
                } catch (error) {
                    button.textContent = 'Copy failed';
                }
            });
        });
    }

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const reviewId = button.getAttribute('data-open-review');
            const template = document.getElementById(`review-detail-${reviewId}`);
            if (!modal || !modalBody || !template) return;

            modalBody.innerHTML = template.innerHTML;
            bindCopyButtons(modalBody);
            modal.removeAttribute('hidden');
            document.body.classList.add('review-modal-open');
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hasAttribute('hidden')) {
            closeModal();
        }
    });

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
