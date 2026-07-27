<?php
/**
 * CoinRex My Reviews Page
 * Location: /coinrex/my-reviews.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireFeatureAccess('reviews');

if (!isLoggedIn()) {
    redirect(BASE_URL . '/auth/auth.php');
}

requireProjectReviewAccess('/taskhub.php');

$db = getDBConnection();
ensureLevelEngineSchema($db);
ensureReviewCorrectionSchema($db);
ensureRexRankSchema($db);
$user = getCurrentUser();
$user_level_state = getUserLevelState($user, $db);
$rexrank_stats = getUserRexRankStats((int) $user['id'], $db);
$slot_costs = getRexRankSlotCosts();

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
$has_correction_count = tableHasColumn('reviews', 'correction_count');
$has_correction_requested_at = tableHasColumn('reviews', 'correction_requested_at');
$has_correction_note = tableHasColumn('reviews', 'correction_note');
$has_project_verified = tableHasColumn('projects', 'is_verified');

$page_notice = '';
$page_notice_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAppCsrf((string) ($_POST['csrf_token'] ?? ''));

    $action = trim((string) ($_POST['action'] ?? ''));
    if ($action === 'purchase_review_priority') {
        $result = purchaseReviewPrioritySlot((int) ($_POST['review_id'] ?? 0), (int) $user['id'], (string) ($_POST['slot_group'] ?? ''), $db);
        setFlashMessage('my_reviews_notice', (string) ($result['message'] ?? 'Unable to activate priority.'));
        setFlashMessage('my_reviews_notice_type', !empty($result['success']) ? 'success' : 'error');
        redirect(BASE_URL . '/public/my-reviews.php');
    }

    if ($action === 'submit_review_correction') {
        $review_id = (int) ($_POST['review_id'] ?? 0);
        try {
            $lookup = $db->prepare("
                SELECT id, user_id, project_id, status, proof_status, correction_count, screenshot_url
                FROM reviews
                WHERE id = ? AND user_id = ?
                LIMIT 1
            ");
            $lookup->execute([$review_id, (int) $user['id']]);
            $current_review = $lookup->fetch();
            if (!$current_review) {
                throw new RuntimeException('Review not found.');
            }
            $current_status = strtolower((string) ($current_review['status'] ?? ''));
            if (!in_array($current_status, ['rejected', 'flagged'], true)) {
                throw new RuntimeException('Only rejected or flagged reviews can be corrected.');
            }
            if ((int) ($current_review['correction_count'] ?? 0) >= 1) {
                throw new RuntimeException('Correction already used for this review.');
            }

            $review_title = trim((string) ($_POST['review_title'] ?? ''));
            $review_content = trim((string) ($_POST['review_content'] ?? ''));
            $pros = trim((string) ($_POST['pros'] ?? ''));
            $cons = trim((string) ($_POST['cons'] ?? ''));
            $tx_hash = trim((string) ($_POST['tx_hash'] ?? ''));
            $wallet_address = strtolower(trim((string) ($_POST['wallet_address'] ?? '')));
            $correction_note = trim((string) ($_POST['correction_note'] ?? ''));

            if ($review_title === '') {
                throw new RuntimeException('Review title is required.');
            }
            if (mb_strlen($review_content) < 150) {
                throw new RuntimeException('Review must be at least 150 characters.');
            }
            if ($wallet_address !== '' && !preg_match('/^0x[a-f0-9]{40}$/', $wallet_address)) {
                throw new RuntimeException('Wallet address must be a valid EVM address.');
            }

            $screenshot_url = (string) ($current_review['screenshot_url'] ?? '');
            if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
                $max_upload_size = 5 * 1024 * 1024;
                $allowed_mimes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                ];
                $tmp_name = (string) ($_FILES['screenshot']['tmp_name'] ?? '');
                $file_size = (int) ($_FILES['screenshot']['size'] ?? 0);
                if (!is_uploaded_file($tmp_name) || $file_size <= 0 || $file_size > $max_upload_size) {
                    throw new RuntimeException('Screenshot must be an image under 5MB.');
                }
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = $finfo ? finfo_file($finfo, $tmp_name) : false;
                if ($finfo) {
                    finfo_close($finfo);
                }
                $ext = $allowed_mimes[$mime_type] ?? '';
                if ($ext === '' || @getimagesize($tmp_name) === false) {
                    throw new RuntimeException('Screenshot format must be JPG, PNG, GIF, or WEBP.');
                }
                $upload_path = BASE_PATH . '/uploads/proofs/';
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0755, true);
                }
                $new_filename = 'correction_' . (int) $user['id'] . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
                if (!move_uploaded_file($tmp_name, $upload_path . $new_filename)) {
                    throw new RuntimeException('Failed to upload screenshot.');
                }
                $screenshot_url = BASE_URL . '/uploads/proofs/' . $new_filename;
            }

            $db->beginTransaction();
            $updates = [
                'review_title = ?',
                'review_content = ?',
                'pros = ?',
                'cons = ?',
                'status = ?',
                'updated_at = NOW()',
            ];
            $params = [$review_title, $review_content, $pros, $cons, 'pending'];
            if ($tx_hash !== '') {
                $updates[] = 'tx_hash = ?';
                $params[] = $tx_hash;
            }
            if ($wallet_address !== '') {
                $updates[] = 'wallet_address = ?';
                $params[] = $wallet_address;
            }
            if ($screenshot_url !== '') {
                $updates[] = 'screenshot_url = ?';
                $params[] = $screenshot_url;
            }
            if ($has_proof_status) {
                $updates[] = 'proof_status = ?';
                $params[] = 'pending';
            }
            if ($has_rejection_reason) {
                $updates[] = 'rejection_reason = NULL';
            }
            if ($has_approval_note) {
                $updates[] = 'approval_note = NULL';
            }
            if ($has_proof_rejection_reason) {
                $updates[] = 'proof_rejection_reason = NULL';
            }
            if ($has_review_score) {
                $updates[] = 'review_score = 0';
            }
            if ($has_final_rex) {
                $updates[] = 'final_rex = 0';
            }
            if ($has_correction_count) {
                $updates[] = 'correction_count = correction_count + 1';
            }
            if ($has_correction_requested_at) {
                $updates[] = 'correction_requested_at = NOW()';
            }
            if ($has_correction_note) {
                $updates[] = 'correction_note = ?';
                $params[] = $correction_note;
            }
            $params[] = $review_id;
            $update = $db->prepare('UPDATE reviews SET ' . implode(', ', $updates) . ' WHERE id = ?');
            $update->execute($params);
            syncUserReviewCounters((int) $user['id'], $db);
            syncUserLevelStatus((int) $user['id'], $db);
            syncProjectAggregateMetrics((int) ($current_review['project_id'] ?? 0), $db);
            $db->commit();
            setFlashMessage('my_reviews_notice', 'Correction submitted. Review is pending again.');
            setFlashMessage('my_reviews_notice_type', 'success');
            redirect(BASE_URL . '/public/my-reviews.php');
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $page_notice = $e->getMessage();
            $page_notice_type = 'error';
        }
    }
}

$flash_notice = consumeFlashMessage('my_reviews_notice');
if ($flash_notice !== '') {
    $page_notice = $flash_notice;
    $page_notice_type = consumeFlashMessage('my_reviews_notice_type') ?: 'success';
}

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
    return BASE_URL . '/public/my-reviews.php' . ($query !== '' ? ('?' . $query) : '');
}

function reviewStatusMeta($status)
{
    $status = strtolower(trim((string) $status));
    $map = [
        'approved' => ['label' => 'Approved', 'class' => 'approved', 'icon' => 'fas fa-check-circle'],
        'rejected' => ['label' => 'Rejected', 'class' => 'rejected', 'icon' => 'fas fa-times-circle'],
        'flagged' => ['label' => 'Flagged', 'class' => 'flagged', 'icon' => 'fas fa-flag'],
        'pending' => ['label' => 'Pending', 'class' => 'pending', 'icon' => 'fas fa-hourglass-half'],
    ];

    return $map[$status] ?? ['label' => ucfirst($status !== '' ? $status : 'Pending'), 'class' => 'pending', 'icon' => 'fas fa-hourglass-half'];
}

function proofStatusMeta($status)
{
    $status = strtolower(trim((string) $status));
    $map = [
        'verified' => ['label' => 'Verified', 'class' => 'verified', 'icon' => 'fas fa-check-circle'],
        'rejected' => ['label' => 'Rejected', 'class' => 'rejected', 'icon' => 'fas fa-times-circle'],
        'flagged' => ['label' => 'Flagged', 'class' => 'flagged', 'icon' => 'fas fa-exclamation-triangle'],
        'pending' => ['label' => 'Pending', 'class' => 'pending', 'icon' => 'fas fa-search'],
    ];

    return $map[$status] ?? ['label' => 'Pending', 'class' => 'pending', 'icon' => 'fas fa-search'];
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
        ($has_correction_count ? 'r.correction_count' : '0 AS correction_count'),
        ($has_correction_requested_at ? 'r.correction_requested_at' : 'NULL AS correction_requested_at'),
        ($has_correction_note ? 'r.correction_note' : 'NULL AS correction_note'),
        'r.created_at',
        'r.updated_at',
        "COALESCE(ap.slot_group, '') AS priority_slot_group",
        "ap.expires_at AS priority_expires_at",
        "COALESCE(p.name, 'Archived Project') AS project_name",
        'p.logo AS project_logo',
        ($has_project_verified ? 'COALESCE(p.is_verified, 0) AS project_verified' : '0 AS project_verified'),
        'COALESCE(ri.impression_count, 0) AS impression_count',
        'COALESCE(ri.read_full_click_count, 0) AS read_full_click_count',
    ];

    $reviews_sql = "
        SELECT " . implode(",\n            ", $review_fields) . "
        FROM reviews r
        LEFT JOIN projects p ON p.id = r.project_id
        LEFT JOIN review_insights ri ON ri.review_id = r.id
        LEFT JOIN (
            SELECT
                review_id,
                SUBSTRING_INDEX(GROUP_CONCAT(slot_group ORDER BY
                    CASE slot_group
                        WHEN 'top1' THEN 1
                        WHEN 'top3' THEN 2
                        WHEN 'top5' THEN 4
                        WHEN 'top10' THEN 6
                        ELSE 99
                    END ASC, created_at DESC), ',', 1) AS slot_group,
                MAX(expires_at) AS expires_at
            FROM review_priority_slots
            WHERE status = 'active'
              AND expires_at > NOW()
            GROUP BY review_id
        ) ap ON ap.review_id = r.id
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
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/my-reviews.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/my-reviews.css'); ?>">

<main class="my-reviews-main">
    <div class="my-reviews-shell">
        <?php if ($page_notice !== ''): ?>
            <div id="myReviewsToast" class="my-reviews-toast <?php echo $page_notice_type === 'error' ? 'toast-error' : 'toast-success'; ?>" role="status" aria-live="polite">
                <?php echo esc($page_notice); ?>
            </div>
        <?php endif; ?>

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
            <a href="<?php echo BASE_URL; ?>/public/projects.php" class="toolbar-action">
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
                    <a href="<?php echo BASE_URL; ?>/public/my-reviews.php" class="btn-clear">Reset</a>
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
                    <a href="<?php echo BASE_URL; ?>/public/projects.php" class="btn-primary-empty">Browse Projects</a>
                    <a href="<?php echo BASE_URL; ?>/public/my-reviews.php" class="btn-secondary-empty">Clear Filters</a>
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
                    $can_correct_review = in_array(strtolower((string) ($review['status'] ?? '')), ['rejected', 'flagged'], true)
                        && (int) ($review['correction_count'] ?? 0) < 1;
                    $can_boost_review = strtolower((string) ($review['status'] ?? '')) === 'approved';
                    $priority_slot = (string) ($review['priority_slot_group'] ?? '');
                    $priority_label = $priority_slot !== '' && isset($slot_costs[$priority_slot]) ? $slot_costs[$priority_slot]['label'] : '';
                    $impression_count = (int) ($review['impression_count'] ?? 0);
                    $read_full_count = (int) ($review['read_full_click_count'] ?? 0);
                    $read_rate = $impression_count > 0 ? round(($read_full_count / max(1, $impression_count)) * 100, 1) : 0;
                    $review_excerpt = trim((string) ($review['review_content'] ?? ''));
                    if (mb_strlen($review_excerpt) > 180) {
                        $review_excerpt = mb_substr($review_excerpt, 0, 177) . '...';
                    }
                    ?>
                    <article class="review-activity-card" data-review-id="<?php echo (int) $review['id']; ?>">
                        <div class="card-topline">
                            <div class="project-identity">
                                <div class="project-logo-wrap<?php echo !empty($review['project_logo']) ? ' has-logo-image' : ' is-fallback'; ?>"<?php if (!empty($review['project_logo'])): ?> style="background-image: url('<?php echo esc($review['project_logo']); ?>');" aria-label="<?php echo esc($review['project_name']); ?> logo"<?php endif; ?>>
                                    <div class="project-logo-fallback"><?php echo esc(strtoupper(substr(trim((string) $review['project_name']) !== '' ? (string) $review['project_name'] : 'PR', 0, 2))); ?></div>
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

                        <div class="review-analytics-strip" aria-label="Review insights">
                            <div><i class="fas fa-eye"></i><span>Impressions</span><strong><?php echo number_format($impression_count); ?></strong></div>
                            <div><i class="fas fa-book-open"></i><span>Full Reads</span><strong><?php echo number_format($read_full_count); ?></strong></div>
                            <div><i class="fas fa-chart-line"></i><span>Read Rate</span><strong><?php echo number_format($read_rate, $read_rate >= 10 ? 0 : 1); ?>%</strong></div>
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
                            <a href="<?php echo BASE_URL; ?>/public/project-detail.php?id=<?php echo (int) $review['project_id']; ?>" class="btn-card-secondary">
                                <i class="fas fa-external-link-alt"></i>
                                <span>View Project</span>
                            </a>
                            <?php if ($can_boost_review): ?>
                                <button type="button" class="btn-card-secondary" data-boost-review="<?php echo (int) $review['id']; ?>" data-boost-title="<?php echo esc($review['project_name'] ?? 'Review'); ?>">
                                    <i class="fas fa-bolt"></i>
                                    <span><?php echo $priority_label !== '' ? esc($priority_label) : 'Boost'; ?></span>
                                </button>
                            <?php endif; ?>
                            <?php if ($can_correct_review): ?>
                                <button type="button" class="btn-card-secondary" data-open-review="<?php echo (int) $review['id']; ?>">
                                    <i class="fas fa-pen"></i>
                                    <span>Fix</span>
                                </button>
                            <?php endif; ?>
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
                                        <div><span>Impressions</span><strong><?php echo number_format($impression_count); ?></strong></div>
                                        <div><span>Full Reads</span><strong><?php echo number_format($read_full_count); ?></strong></div>
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
                                    <div class="correction-status-panel">
                                        <h5>Correction</h5>
                                        <p><?php echo $can_correct_review ? '1 correction available.' : ((int) ($review['correction_count'] ?? 0) > 0 ? 'Correction used.' : 'No action needed.'); ?></p>
                                        <?php if (!empty($review['correction_note'])): ?>
                                            <p><strong>Last note:</strong> <?php echo esc($review['correction_note']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </section>
                            </div>

                            <?php if ($can_correct_review): ?>
                                <form method="POST" enctype="multipart/form-data" class="review-correction-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
                                    <input type="hidden" name="action" value="submit_review_correction">
                                    <input type="hidden" name="review_id" value="<?php echo (int) $review['id']; ?>">
                                    <h4>Fix Review</h4>
                                    <div class="correction-grid">
                                        <label>Title<input type="text" name="review_title" value="<?php echo esc($review['review_title'] ?? ''); ?>" required></label>
                                        <label>Wallet<input type="text" name="wallet_address" value="<?php echo esc($review['wallet_address'] ?? ''); ?>"></label>
                                        <label>TX Hash<input type="text" name="tx_hash" value="<?php echo esc($review['tx_hash'] ?? ''); ?>"></label>
                                        <label>Screenshot<input type="file" name="screenshot" accept="image/*"></label>
                                    </div>
                                    <label>Review<textarea name="review_content" rows="5" required><?php echo esc($review['review_content'] ?? ''); ?></textarea></label>
                                    <div class="correction-grid">
                                        <label>Good<textarea name="pros" rows="3"><?php echo esc($review['pros'] ?? ''); ?></textarea></label>
                                        <label>Improve<textarea name="cons" rows="3"><?php echo esc($review['cons'] ?? ''); ?></textarea></label>
                                    </div>
                                    <label>Note<textarea name="correction_note" rows="2" placeholder="What did you fix?"></textarea></label>
                                    <button type="submit" class="btn-card-primary"><i class="fas fa-paper-plane"></i> Submit Correction</button>
                                </form>
                            <?php endif; ?>
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

<div class="review-modal priority-choice-modal" id="priorityChoiceModal" hidden>
    <div class="review-modal-backdrop" data-close-priority-modal></div>
    <div class="review-modal-dialog priority-choice-dialog" role="dialog" aria-modal="true" aria-label="Boost review">
        <button type="button" class="review-modal-close" data-close-priority-modal aria-label="Close boost modal">
            <i class="fas fa-times"></i>
        </button>
        <div class="priority-choice-head">
            <span>Review Boost</span>
            <strong id="priorityChoiceTitle">Priority</strong>
        </div>
        <div class="priority-choice-grid">
            <?php foreach ($slot_costs as $slot_key => $slot): ?>
                <form method="POST" class="priority-choice-form">
                    <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
                    <input type="hidden" name="action" value="purchase_review_priority">
                    <input type="hidden" name="review_id" value="">
                    <input type="hidden" name="slot_group" value="<?php echo esc($slot_key); ?>">
                    <button type="submit">
                        <span><?php echo esc($slot['label']); ?></span>
                        <strong><?php echo (int) $slot['cost']; ?>RR</strong>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const modal = document.getElementById('reviewDetailModal');
    const modalBody = document.getElementById('reviewModalBody');
    const priorityModal = document.getElementById('priorityChoiceModal');
    const priorityTitle = document.getElementById('priorityChoiceTitle');
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

    document.querySelectorAll('[data-boost-review]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!priorityModal) return;
            const reviewId = button.getAttribute('data-boost-review') || '';
            priorityModal.querySelectorAll('input[name="review_id"]').forEach((input) => {
                input.value = reviewId;
            });
            if (priorityTitle) {
                priorityTitle.textContent = button.getAttribute('data-boost-title') || 'Priority';
            }
            priorityModal.removeAttribute('hidden');
            document.body.classList.add('review-modal-open');
        });
    });

    document.querySelectorAll('[data-close-priority-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!priorityModal) return;
            priorityModal.setAttribute('hidden', 'hidden');
            if (!modal || modal.hasAttribute('hidden')) {
                document.body.classList.remove('review-modal-open');
            }
        });
    });

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal && !modal.hasAttribute('hidden')) {
            closeModal();
        } else if (event.key === 'Escape' && priorityModal && !priorityModal.hasAttribute('hidden')) {
            priorityModal.setAttribute('hidden', 'hidden');
            document.body.classList.remove('review-modal-open');
        }
    });

    modal?.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
    });

    const toast = document.getElementById('myReviewsToast');
    if (toast) {
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });
        window.setTimeout(() => {
            toast.classList.remove('show');
            window.setTimeout(() => {
                if (toast && toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 260);
        }, 4200);
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
