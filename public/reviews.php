<?php
/**
 * CoinRex Reviews Listing Page
 * Location: /coinrex/reviews.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireFeatureAccess('reviews');

$db = getDBConnection();
ensureLevelEngineSchema($db);
ensureRexRankSchema($db);
ensureReviewInsightSchema($db);
ensureRewardClaimSchema($db);

$current_user = getCurrentUser();
$is_public_view = !$current_user;
$current_user_level_state = $current_user ? getUserLevelState($current_user, $db) : null;
$review_action_message = consumeFlashMessage('reviews_action_message');
$review_action_type = consumeFlashMessage('reviews_action_type');

function esc($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function walletTypeLabel($wallet_address)
{
    $wallet_address = trim((string)$wallet_address);
    if ($wallet_address === '') return ['label' => 'Unknown', 'class' => 'unknown'];
    if (preg_match('/^(0x[a-fA-F0-9]{8,}|T[a-zA-Z0-9]{20,}|bc1[a-zA-Z0-9]{10,})$/', $wallet_address)) {
        return ['label' => 'Non-custodial', 'class' => 'non-custodial'];
    }
    return ['label' => 'Custodial', 'class' => 'custodial'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && trim((string) ($_POST['action'] ?? '')) === 'track_review_insight') {
    header('Content-Type: application/json');
    try {
        requireAppCsrf((string) ($_POST['csrf_token'] ?? ''));
        $review_ids = $_POST['review_ids'] ?? ($_POST['review_id'] ?? []);
        if (!is_array($review_ids)) {
            $review_ids = [$review_ids];
        }
        $result = recordReviewInsightEvent($review_ids, (string) ($_POST['event_type'] ?? ''), $db);
        echo json_encode($result);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tracking paused.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $current_user) {
    requireAppCsrf((string) ($_POST['csrf_token'] ?? ''));

    $action = trim((string) ($_POST['action'] ?? ''));
    $review_id = (int) ($_POST['review_id'] ?? 0);
    $redirect_url = BASE_URL . '/public/reviews.php?page=' . max(1, (int) ($_GET['page'] ?? 1));

    if ($action === 'cast_experience_vote') {
        $result = castRexRankExperienceVote($review_id, (int) $current_user['id'], (string) ($_POST['vote_type'] ?? ''), $db);
        setFlashMessage('reviews_action_message', (string) ($result['message'] ?? 'Unable to cast vote.'));
        setFlashMessage('reviews_action_type', !empty($result['success']) ? 'success' : 'error');
        redirect($redirect_url);
    }

    if ($action === 'cast_review_up') {
        $result = castReviewUpReward($review_id, (int) $current_user['id'], $db);
        setFlashMessage('reviews_action_message', (string) ($result['message'] ?? 'Unable to up review.'));
        setFlashMessage('reviews_action_type', !empty($result['success']) ? 'success' : 'error');
        redirect($redirect_url);
    }

    if ($action === 'submit_review_comment') {
        $result = submitReviewComment($review_id, (int) $current_user['id'], (string) ($_POST['comment_text'] ?? ''), $db);
        setFlashMessage('reviews_action_message', (string) ($result['message'] ?? 'Unable to add comment.'));
        setFlashMessage('reviews_action_type', !empty($result['success']) ? 'success' : 'error');
        redirect($redirect_url);
    }

    if ($action === 'like_review_comment') {
        $result = likeReviewCommentByReviewer((int) ($_POST['comment_id'] ?? 0), (int) $current_user['id'], $db);
        setFlashMessage('reviews_action_message', (string) ($result['message'] ?? 'Unable to like comment.'));
        setFlashMessage('reviews_action_type', !empty($result['success']) ? 'success' : 'error');
        redirect($redirect_url);
    }

    if ($action === 'convert_rexrank') {
        $result = convertRexRankToRex((int) $current_user['id'], (float) ($_POST['amount_rr'] ?? 0), $db);
        setFlashMessage('reviews_action_message', (string) ($result['message'] ?? 'Unable to convert RexRank.'));
        setFlashMessage('reviews_action_type', !empty($result['success']) ? 'success' : 'error');
        redirect($redirect_url);
    }

    if ($action === 'toggle_like') {
        $result = toggleReviewLike($review_id, (int) $current_user['id'], $db);
        setFlashMessage('reviews_action_message', (string) ($result['message'] ?? 'Unable to update review reaction.'));
        setFlashMessage('reviews_action_type', !empty($result['success']) ? 'success' : 'error');
        redirect($redirect_url);
    }

    if ($action === 'flag_review') {
        $result = submitContentFlag((int) $current_user['id'], 'review', $review_id, 'Flagged by expert reviewer from community feed.', $db);
        setFlashMessage('reviews_action_message', (string) ($result['message'] ?? 'Unable to flag review.'));
        setFlashMessage('reviews_action_type', !empty($result['success']) ? 'success' : 'error');
        redirect($redirect_url);
    }
}

$has_featured_column = tableHasColumn('projects', 'is_featured');
$has_sponsored_column = tableHasColumn('projects', 'is_sponsored');
$featured_select = $has_featured_column ? 'COALESCE(p.is_featured, 0)' : '0';
$sponsored_select = $has_sponsored_column ? 'COALESCE(p.is_sponsored, 0)' : '0';

$search = trim((string) ($_GET['search'] ?? ''));
$category_filter = trim((string) ($_GET['category'] ?? 'all'));
$rating_filter = trim((string) ($_GET['rating'] ?? 'all'));
$featured_filter = trim((string) ($_GET['featured'] ?? 'all'));

$allowed_rating = ['all', '4_plus', '3_plus', 'below_2'];
if (!in_array($rating_filter, $allowed_rating, true)) {
    $rating_filter = 'all';
}

$allowed_featured = ['all', 'featured', 'regular'];
if (!in_array($featured_filter, $allowed_featured, true)) {
    $featured_filter = 'all';
}

$category_stmt = $db->prepare("SELECT LOWER(TRIM(category)) AS category_key, TRIM(category) AS category_label, COUNT(*) AS total FROM projects WHERE approval_status = 'approved' AND TRIM(COALESCE(category, '')) <> '' GROUP BY LOWER(TRIM(category)), TRIM(category) ORDER BY total DESC, category_label ASC");
$category_stmt->execute();
$category_rows = $category_stmt->fetchAll() ?: [];
$allowed_categories = ['all'];
foreach ($category_rows as $crow) {
    $allowed_categories[] = (string) ($crow['category_key'] ?? '');
}
if (!in_array(strtolower($category_filter), $allowed_categories, true)) {
    $category_filter = 'all';
}

$where = ["r.status = 'approved'", "p.approval_status = 'approved'"];
$params = [];

if ($search !== '') {
    $where[] = "(
        COALESCE(NULLIF(TRIM(u.full_name), ''), u.username, 'Reviewer') LIKE :search
        OR u.username LIKE :search
        OR p.name LIKE :search
        OR r.review_title LIKE :search
        OR r.review_content LIKE :search
    )";
    $params[':search'] = '%' . $search . '%';
}

if ($category_filter !== 'all') {
    $where[] = "LOWER(TRIM(COALESCE(p.category, ''))) = :category_filter";
    $params[':category_filter'] = strtolower($category_filter);
}

if ($rating_filter === '4_plus') {
    $where[] = 'r.rating >= 4';
} elseif ($rating_filter === '3_plus') {
    $where[] = 'r.rating >= 3';
} elseif ($rating_filter === 'below_2') {
    $where[] = 'r.rating < 2';
}

if ($featured_filter === 'featured') {
    $where[] = "{$featured_select} = 1";
} elseif ($featured_filter === 'regular') {
    $where[] = "{$featured_select} = 0";
}

$where_sql = implode(' AND ', $where);

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = $page > 0 ? $page : 1;
$per_page = 8;
$offset = ($page - 1) * $per_page;

$count_stmt = $db->prepare("SELECT COUNT(*) AS total FROM reviews r INNER JOIN projects p ON p.id = r.project_id INNER JOIN users u ON u.id = r.user_id WHERE {$where_sql}");
foreach ($params as $pk => $pv) {
    $count_stmt->bindValue($pk, $pv);
}
$count_stmt->execute();
$total_reviews = (int)($count_stmt->fetch()['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_reviews / $per_page));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

$reviews_stmt = $db->prepare("SELECT
    r.id,
    r.user_id,
    r.project_id,
    r.rating,
    r.review_title,
    r.review_content,
    r.holding_amount,
    r.holding_days,
    r.wallet_address,
    r.review_score,
    r.created_at,
    u.username,
    u.full_name,
    u.avatar,
    u.level,
    r.helpful_count,
    COALESCE(ap.slot_group, '') AS priority_slot_group,
    COALESCE(ap.priority_rank, 99) AS priority_rank,
    ap.expires_at AS priority_expires_at,
    p.name AS project_name,
    p.logo AS project_logo,
    p.category AS project_category,
    {$featured_select} AS is_featured,
    {$sponsored_select} AS is_sponsored,
    COALESCE(ps.total_reviews, 0) AS project_total_reviews,
    COALESCE(ps.avg_rating, 0) AS project_avg_rating,
    COALESCE(ri.impression_count, 0) AS impression_count,
    COALESCE(ri.read_full_click_count, 0) AS read_full_click_count
FROM reviews r
INNER JOIN users u ON u.id = r.user_id
INNER JOIN projects p ON p.id = r.project_id
LEFT JOIN review_insights ri ON ri.review_id = r.id
LEFT JOIN (
    SELECT project_id, COUNT(*) AS total_reviews, AVG(rating) AS avg_rating
    FROM reviews
    WHERE status = 'approved'
    GROUP BY project_id
) ps ON ps.project_id = p.id
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
        MIN(CASE slot_group
            WHEN 'top1' THEN 1
            WHEN 'top3' THEN 2
            WHEN 'top5' THEN 4
            WHEN 'top10' THEN 6
            ELSE 99
        END) AS priority_rank,
        MAX(expires_at) AS expires_at
    FROM review_priority_slots
    WHERE status = 'active'
      AND expires_at > NOW()
    GROUP BY review_id
) ap ON ap.review_id = r.id
WHERE {$where_sql}
ORDER BY
    priority_rank ASC,
    CASE WHEN COALESCE(ap.slot_group, '') = 'top1' THEN ap.expires_at ELSE NULL END DESC,
    CASE WHEN COALESCE(ap.slot_group, '') IN ('top3', 'top5', 'top10') THEN RAND() ELSE 0 END,
    r.review_score DESC,
    r.created_at DESC
LIMIT :limit OFFSET :offset");

foreach ($params as $pk => $pv) {
    $reviews_stmt->bindValue($pk, $pv);
}
$reviews_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$reviews_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$reviews_stmt->execute();
$reviews = $reviews_stmt->fetchAll();

foreach ($reviews as &$review) {
    $review['avatar'] = coinrexNormalizeMediaUrl((string) ($review['avatar'] ?? ''));
    $review['project_logo'] = coinrexNormalizeMediaUrl((string) ($review['project_logo'] ?? ''));
}
unset($review);

$top_projects_stmt = $db->prepare("SELECT p.id, p.name, {$featured_select} AS is_featured, {$sponsored_select} AS is_sponsored, COALESCE(stats.total_reviews, 0) AS total_reviews, COALESCE(stats.avg_rating, 0) AS avg_rating FROM projects p INNER JOIN (SELECT project_id, COUNT(*) AS total_reviews, AVG(rating) AS avg_rating FROM reviews WHERE status = 'approved' GROUP BY project_id HAVING COUNT(*) > 0) stats ON stats.project_id = p.id WHERE p.approval_status = 'approved' ORDER BY stats.avg_rating DESC, stats.total_reviews DESC LIMIT 6");
$top_projects_stmt->execute();
$top_projects = $top_projects_stmt->fetchAll() ?: [];

$liked_review_ids = [];
$experience_votes = [];
$rexrank_stats = $current_user ? getUserRexRankStats((int) $current_user['id'], $db) : null;
$slot_costs = getRexRankSlotCosts();
if ($current_user) {
    $liked_stmt = $db->prepare("SELECT review_id FROM review_reactions WHERE user_id = ? AND reaction_type = 'like'");
    $liked_stmt->execute([(int) $current_user['id']]);
    $liked_review_ids = array_map('intval', $liked_stmt->fetchAll(PDO::FETCH_COLUMN));

    $vote_stmt = $db->prepare("SELECT review_id, reaction_type FROM review_reactions WHERE user_id = ? AND reaction_type IN ('same_experience', 'different_experience')");
    $vote_stmt->execute([(int) $current_user['id']]);
    foreach ($vote_stmt->fetchAll() ?: [] as $vote_row) {
        $experience_votes[(int) ($vote_row['review_id'] ?? 0)] = (string) ($vote_row['reaction_type'] ?? '');
    }
}

function renderReviewCommentText($text)
{
    $text = (string) $text;
    $parts = preg_split('/(@[A-Za-z0-9_]{2,30}|#[A-Za-z0-9_]{1,40}|\$[A-Za-z][A-Za-z0-9]{1,12})/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $html = '';
    foreach ($parts ?: [] as $part) {
        if ($part === '') {
            continue;
        }
        if (preg_match('/^@[A-Za-z0-9_]{2,30}$/', $part)) {
            $html .= '<span class="comment-token mention">' . esc($part) . '</span>';
        } elseif (preg_match('/^#[A-Za-z0-9_]{1,40}$/', $part)) {
            $html .= '<span class="comment-token tag">' . esc($part) . '</span>';
        } elseif (preg_match('/^\$[A-Za-z][A-Za-z0-9]{1,12}$/', $part)) {
            $html .= '<span class="comment-token currency">' . esc($part) . '</span>';
        } else {
            $html .= esc($part);
        }
    }
    return nl2br($html);
}

function hydrateReviewFeedEngagement(array $reviews, PDO $db, ?array $current_user)
{
    $ids = array_values(array_filter(array_map(static fn($review) => (int) ($review['id'] ?? 0), $reviews)));
    if (empty($ids)) {
        return $reviews;
    }

    foreach ($reviews as &$review) {
        $review['same_count'] = 0;
        $review['different_count'] = 0;
        $review['up_count'] = 0;
        $review['comment_count'] = 0;
        $review['current_user_upped'] = false;
        $review['comments'] = [];
    }
    unset($review);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $index_by_id = [];
    foreach ($reviews as $idx => $review) {
        $index_by_id[(int) ($review['id'] ?? 0)] = $idx;
    }

    $reaction_stmt = $db->prepare("
        SELECT review_id, reaction_type, COUNT(*) AS total
        FROM review_reactions
        WHERE review_id IN ($placeholders)
          AND reaction_type IN ('same_experience', 'different_experience', 'up')
        GROUP BY review_id, reaction_type
    ");
    $reaction_stmt->execute($ids);
    foreach ($reaction_stmt->fetchAll() ?: [] as $row) {
        $review_id = (int) ($row['review_id'] ?? 0);
        if (!isset($index_by_id[$review_id])) {
            continue;
        }
        $key = (string) ($row['reaction_type'] ?? '');
        if ($key === 'same_experience') {
            $reviews[$index_by_id[$review_id]]['same_count'] = (int) ($row['total'] ?? 0);
        } elseif ($key === 'different_experience') {
            $reviews[$index_by_id[$review_id]]['different_count'] = (int) ($row['total'] ?? 0);
        } elseif ($key === 'up') {
            $reviews[$index_by_id[$review_id]]['up_count'] = (int) ($row['total'] ?? 0);
        }
    }

    if ($current_user) {
        $up_stmt = $db->prepare("
            SELECT review_id
            FROM review_reactions
            WHERE user_id = ?
              AND reaction_type = 'up'
              AND review_id IN ($placeholders)
        ");
        $up_stmt->execute(array_merge([(int) $current_user['id']], $ids));
        foreach ($up_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $review_id) {
            $review_id = (int) $review_id;
            if (isset($index_by_id[$review_id])) {
                $reviews[$index_by_id[$review_id]]['current_user_upped'] = true;
            }
        }
    }

    $comment_count_stmt = $db->prepare("
        SELECT review_id, COUNT(*) AS total
        FROM review_comments
        WHERE review_id IN ($placeholders)
          AND status = 'visible'
        GROUP BY review_id
    ");
    $comment_count_stmt->execute($ids);
    foreach ($comment_count_stmt->fetchAll() ?: [] as $row) {
        $review_id = (int) ($row['review_id'] ?? 0);
        if (isset($index_by_id[$review_id])) {
            $reviews[$index_by_id[$review_id]]['comment_count'] = (int) ($row['total'] ?? 0);
        }
    }

    $comment_stmt = $db->prepare("
        SELECT
            c.id,
            c.review_id,
            c.user_id,
            c.comment_text,
            c.like_count,
            c.created_at,
            COALESCE(NULLIF(TRIM(u.full_name), ''), u.username, 'User') AS display_name,
            u.username,
            " . ($current_user ? "CASE WHEN l.id IS NULL THEN 0 ELSE 1 END" : "0") . " AS liked_by_current_user
        FROM review_comments c
        INNER JOIN users u ON u.id = c.user_id
        " . ($current_user ? "LEFT JOIN review_comment_likes l ON l.comment_id = c.id AND l.user_id = " . (int) $current_user['id'] : "") . "
        WHERE c.review_id IN ($placeholders)
          AND c.status = 'visible'
        ORDER BY c.created_at DESC
    ");
    $comment_stmt->execute($ids);
    foreach ($comment_stmt->fetchAll() ?: [] as $comment) {
        $review_id = (int) ($comment['review_id'] ?? 0);
        if (isset($index_by_id[$review_id])) {
            $reviews[$index_by_id[$review_id]]['comments'][] = $comment;
        }
    }

    return $reviews;
}

$reviews = hydrateReviewFeedEngagement($reviews, $db, $current_user);

function renderReviewFeedCard(array $review, int $index, int $offset, bool $is_public_view, ?array $current_user, ?array $current_user_level_state, array $experience_votes, array $slot_costs) {
    $wallet = walletTypeLabel($review['wallet_address'] ?? '');
    $rating = (float)($review['rating'] ?? 0);
    $full_name = trim((string) ($review['full_name'] ?? ''));
    $display_name = $full_name !== '' ? $full_name : (trim((string) ($review['username'] ?? '')) !== '' ? (string) $review['username'] : 'Reviewer');
    $user_level = ucfirst(strtolower((string)($review['level'] ?? 'Beginner')));
    $avatar_letter = strtoupper(substr((string)$display_name, 0, 1));
    $review_id = (int) ($review['id'] ?? 0);
    $is_top = ($offset + $index) < 10;
    $content = trim((string)($review['review_content'] ?? ''));
    if ($is_public_view && function_exists('mb_substr') && mb_strlen($content, 'UTF-8') > 220) {
        $content = rtrim(mb_substr($content, 0, 219, 'UTF-8')) . '...';
    } elseif ($is_public_view && strlen($content) > 220) {
        $content = rtrim(substr($content, 0, 219)) . '...';
    }
    $same_count = (int) ($review['same_count'] ?? 0);
    $different_count = (int) ($review['different_count'] ?? 0);
    $up_count = (int) ($review['up_count'] ?? 0);
    $comment_count = (int) ($review['comment_count'] ?? 0);
    $impression_count = (int) ($review['impression_count'] ?? 0);
    $read_full_count = (int) ($review['read_full_click_count'] ?? 0);
    $read_rate = $impression_count > 0 ? round(($read_full_count / max(1, $impression_count)) * 100, 1) : 0;
    $vote_count = $same_count + $different_count;
    $current_vote = $experience_votes[$review_id] ?? '';
    $current_level = normalizeUserLevel($current_user_level_state['level'] ?? ($current_user['level'] ?? 'beginner'));
    $can_vote = (bool) $current_user && in_array($current_level, ['pro', 'expert'], true) && (int) ($current_user['id'] ?? 0) !== (int) ($review['user_id'] ?? 0) && $current_vote === '';
    $is_owner = (bool) $current_user && (int) ($current_user['id'] ?? 0) === (int) ($review['user_id'] ?? 0);
    $can_up = (bool) $current_user && !$is_owner && empty($review['current_user_upped']);
    $expert_can_act = $current_user && userCanAccessExpertTools($current_user_level_state);
    $level_class = 'beginner';
    $level_icon = 'fas fa-seedling';
    if (strtolower($user_level) === 'expert') {
        $level_class = 'expert';
        $level_icon = 'fas fa-crown';
    } elseif (in_array(strtolower($user_level), ['pro', 'premium'], true)) {
        $level_class = 'pro';
        $level_icon = 'fas fa-gem';
        $user_level = 'Pro';
    }
    $is_featured_project = (int) ($review['is_featured'] ?? 0) === 1;
    $project_name = (string) ($review['project_name'] ?? 'Project');
    $project_initials = strtoupper(substr(trim($project_name) !== '' ? trim($project_name) : 'PR', 0, 2));
    $project_logo = (string) ($review['project_logo'] ?? '');
    $project_total_reviews = (int) ($review['project_total_reviews'] ?? 0);
    $project_avg_rating = (float) ($review['project_avg_rating'] ?? 0);
    $is_warning_project = $project_total_reviews >= 100 && $project_avg_rating < 2;
    $full_content_raw = trim((string)($review['review_content'] ?? ''));
    $priority_slot = (string) ($review['priority_slot_group'] ?? '');
    $priority_label = $priority_slot !== '' && isset($slot_costs[$priority_slot]) ? $slot_costs[$priority_slot]['label'] : '';
    $current_user_commented = false;
    foreach (($review['comments'] ?? []) as $comment_check) {
        if ($current_user && (int) ($comment_check['user_id'] ?? 0) === (int) ($current_user['id'] ?? 0)) {
            $current_user_commented = true;
            break;
        }
    }
    ob_start();
    ?>
    <article class="review-card review-card-horizontal <?php echo $is_top ? 'top-review' : ''; ?>" data-review-id="<?php echo $review_id; ?>" data-full-content="<?php echo esc($full_content_raw); ?>">
        <div class="review-card-main">
            <?php if ($priority_label !== ''): ?><div class="priority-tag"><i class="fas fa-bolt"></i> <?php echo esc($priority_label); ?></div><?php endif; ?>

            <header class="project-review-head">
                <a href="<?php echo BASE_URL; ?>/public/project-detail.php?id=<?php echo (int)$review['project_id']; ?>" class="project-info with-logo">
                    <span class="review-project-logo-wrap<?php echo $project_logo !== '' ? ' has-logo-image' : ' is-fallback'; ?>"<?php if ($project_logo !== ''): ?> style="background-image: url('<?php echo esc($project_logo); ?>');" aria-label="<?php echo esc($project_name); ?> logo"<?php endif; ?>>
                        <span class="review-project-logo-fallback"><?php echo esc($project_initials); ?></span>
                    </span>
                    <span class="project-copy">
                        <span class="project-label">Project</span>
                        <span class="project-name-row">
                            <span class="project-name"><?php echo esc($project_name); ?></span>
                            <span class="project-statuses-inline">
                                <span class="project-badge <?php echo $is_featured_project ? 'featured' : 'regular'; ?>">
                                    <i class="fas <?php echo $is_featured_project ? 'fa-gem' : 'fa-circle-check'; ?>"></i>
                                    <?php echo $is_featured_project ? 'Featured' : 'Regular'; ?>
                                </span>
                                <?php if ((int) ($review['is_sponsored'] ?? 0) === 1): ?>
                                    <span class="project-badge sponsored"><i class="fas fa-bullhorn"></i> Sponsored</span>
                                <?php endif; ?>
                                <?php if ($is_warning_project): ?>
                                    <span class="project-badge warning"><i class="fas fa-triangle-exclamation"></i> Low Rated</span>
                                <?php endif; ?>
                            </span>
                        </span>
                    </span>
                </a>
                <div class="project-head-side">
                    <?php echo renderUniversalRating([
                        'provider' => 'coinrex',
                        'value' => $rating,
                        'scale' => 5,
                        'size' => 'sm',
                        'variant' => 'cr-row-small',
                        'class' => 'review-rating-badge',
                    ]); ?>
                </div>
            </header>

            <div class="reviewer-line">
                <span>Reviewed by:</span>
                <strong><?php echo esc($display_name); ?></strong>
                <span class="user-level-badge <?php echo esc($level_class); ?>"><i class="<?php echo esc($level_icon); ?>"></i><span><?php echo esc($user_level); ?></span></span>
                <time><?php echo date('M d, Y', strtotime((string)$review['created_at'])); ?></time>
            </div>

            <?php if (!empty($review['review_title'])): ?>
                <h3 class="review-title"><?php echo esc($review['review_title']); ?></h3>
            <?php endif; ?>

            <div class="review-content-wrap">
                <p class="review-content collapsed"><?php echo nl2br(esc($content)); ?></p>
                <?php $needs_modal = strlen($full_content_raw) > 220; ?>
                <?php if ($needs_modal): ?>
                    <button type="button" class="read-more-btn" data-review-id="<?php echo $review_id; ?>">Read Full Review</button>
                <?php endif; ?>
            </div>

            <div class="review-insight-mini" aria-label="Review insights">
                <div><i class="fas fa-eye"></i><strong><?php echo number_format($impression_count); ?></strong><span>Impressions</span></div>
                <div><i class="fas fa-book-open"></i><strong><?php echo number_format($read_full_count); ?></strong><span>Reads</span></div>
                <div><i class="fas fa-chart-line"></i><strong><?php echo number_format($read_rate, $read_rate >= 10 ? 0 : 1); ?>%</strong><span>Read Rate</span></div>
            </div>

            <div class="review-bottom-row">
                <div class="review-engagement-bar">
                    <form method="POST" action="" class="engagement-action-form">
                        <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
                        <input type="hidden" name="action" value="cast_experience_vote">
                        <input type="hidden" name="review_id" value="<?php echo $review_id; ?>">
                        <button type="submit" name="vote_type" value="same_experience" class="engagement-btn" <?php echo $can_vote ? '' : 'disabled'; ?>>
                            <i class="fas fa-check"></i><span>Same</span><strong><?php echo number_format($same_count); ?></strong>
                        </button>
                    </form>
                    <form method="POST" action="" class="engagement-action-form">
                        <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
                        <input type="hidden" name="action" value="cast_experience_vote">
                        <input type="hidden" name="review_id" value="<?php echo $review_id; ?>">
                        <button type="submit" name="vote_type" value="different_experience" class="engagement-btn" <?php echo $can_vote ? '' : 'disabled'; ?>>
                            <i class="fas fa-code-compare"></i><span>Different</span><strong><?php echo number_format($different_count); ?></strong>
                        </button>
                    </form>
                    <form method="POST" action="" class="engagement-action-form">
                        <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
                        <input type="hidden" name="action" value="cast_review_up">
                        <input type="hidden" name="review_id" value="<?php echo $review_id; ?>">
                        <button type="submit" class="engagement-btn" <?php echo $can_up ? '' : 'disabled'; ?>>
                            <i class="fas fa-arrow-up"></i><span>Up</span><strong><?php echo number_format($up_count); ?></strong>
                        </button>
                    </form>
                    <button type="button" class="engagement-btn" data-comments-review="<?php echo $review_id; ?>" data-comments-title="<?php echo esc($review['project_name'] ?? 'Review'); ?>">
                        <i class="fas fa-comment"></i><span>Comments</span><strong><?php echo number_format($comment_count); ?></strong>
                    </button>
                </div>
            </div>

            <div class="review-comments-template" id="review-comments-<?php echo $review_id; ?>" hidden>
                <div class="comments-modal-head">
                    <span>Comments</span>
                    <strong><?php echo esc($review['project_name'] ?? 'Review'); ?></strong>
                </div>
                <?php if ($current_user && !$current_user_commented): ?>
                    <form method="POST" class="comment-compose-form">
                        <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
                        <input type="hidden" name="action" value="submit_review_comment">
                        <input type="hidden" name="review_id" value="<?php echo $review_id; ?>">
                        <textarea name="comment_text" maxlength="500" rows="3" placeholder="@user #tag $REX"></textarea>
                        <button type="submit">Comment</button>
                    </form>
                <?php elseif (!$current_user): ?>
                    <a href="<?php echo AUTH_URL; ?>/auth.php" class="comment-signin-link">Sign in to comment</a>
                <?php else: ?>
                    <div class="comment-once-note">You already commented.</div>
                <?php endif; ?>

                <div class="comment-list">
                    <?php if (empty($review['comments'])): ?>
                        <p class="comment-empty">No comments yet.</p>
                    <?php else: ?>
                        <?php foreach ($review['comments'] as $comment): ?>
                            <?php
                            $commenter_name = trim((string) ($comment['display_name'] ?? 'User'));
                            $commenter_initial = strtoupper(substr($commenter_name, 0, 1));
                            $can_like_comment = $is_owner
                                && $current_user
                                && (int) ($comment['user_id'] ?? 0) !== (int) ($current_user['id'] ?? 0)
                                && (int) ($comment['liked_by_current_user'] ?? 0) === 0;
                            ?>
                            <article class="comment-card">
                                <div class="comment-avatar"><?php echo esc($commenter_initial); ?></div>
                                <div class="comment-body">
                                    <div class="comment-topline">
                                        <strong><?php echo esc($commenter_name); ?></strong>
                                        <span><?php echo date('M d', strtotime((string) ($comment['created_at'] ?? 'now'))); ?></span>
                                    </div>
                                    <p><?php echo renderReviewCommentText($comment['comment_text'] ?? ''); ?></p>
                                    <div class="comment-actions">
                                        <span><i class="fas fa-heart"></i> <?php echo (int) ($comment['like_count'] ?? 0); ?></span>
                                        <?php if ($can_like_comment): ?>
                                            <form method="POST">
                                                <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
                                                <input type="hidden" name="action" value="like_review_comment">
                                                <input type="hidden" name="review_id" value="<?php echo $review_id; ?>">
                                                <input type="hidden" name="comment_id" value="<?php echo (int) ($comment['id'] ?? 0); ?>">
                                                <button type="submit">Like +1RR</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <aside class="review-card-side">
            <div class="review-meta <?php echo $is_public_view ? 'review-meta-public' : ''; ?>">
                <div class="meta-item"><i class="fas fa-wallet"></i><span>$<?php echo number_format((float)$review['holding_amount'], 0); ?></span></div>
                <div class="meta-item"><i class="fas fa-clock"></i><span><?php echo (int)$review['holding_days']; ?>d</span></div>
                <div class="meta-item wallet-meta"><span class="wallet-badge <?php echo esc($wallet['class']); ?>"><?php echo esc($wallet['label']); ?></span></div>
                <div class="meta-item"><i class="fas fa-ranking-star"></i><span><?php echo number_format($vote_count); ?> votes</span></div>
            </div>

            <?php if ($expert_can_act): ?>
                <form method="POST" action="" class="expert-flag-form">
                    <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
                    <input type="hidden" name="review_id" value="<?php echo $review_id; ?>">
                    <button type="submit" name="action" value="flag_review" class="read-more-btn">Flag Review</button>
                </form>
            <?php endif; ?>
        </aside>
    </article>
    <?php
    return (string) ob_get_clean();
}

function renderReviewFeedCards(array $reviews, int $offset, bool $is_public_view, ?array $current_user, ?array $current_user_level_state, array $experience_votes, array $slot_costs) {
    $html = '';
    foreach ($reviews as $index => $review) {
        $html .= renderReviewFeedCard($review, (int) $index, $offset, $is_public_view, $current_user, $current_user_level_state, $experience_votes, $slot_costs);
    }
    return $html;
}

if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'cards_html' => renderReviewFeedCards($reviews, $offset, $is_public_view, $current_user, $current_user_level_state, $experience_votes, $slot_costs),
        'page' => $page,
        'has_more' => $page < $total_pages,
        'total_pages' => $total_pages,
    ]);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/rating-badge.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/rating-badge.css'); ?>">
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/reviews.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/reviews.css'); ?>">

<main class="reviews-main">
    <div class="reviews-container wide">
        <?php if ($review_action_message !== ''): ?>
            <div id="reviewsToast" class="reviews-toast <?php echo $review_action_type === 'error' ? 'toast-error' : 'toast-success'; ?>" role="status" aria-live="polite">
                <?php echo esc($review_action_message); ?>
            </div>
        <?php endif; ?>

        <section class="reviews-hero">
            <h1>Community <span class="gradient-text">Reviews</span></h1>
            <p><?php echo $is_public_view ? 'Public approved reviews from CoinRex users.' : 'Vote with RexRank, earn $REX, and manage boosts from My Reviews.'; ?></p>
        </section>

        <section class="reviews-caution">
            <strong><i class="fas fa-triangle-exclamation"></i> Verified feed</strong>
            <p>Approved reviews are proof-checked, but opinions remain user-submitted.</p>
        </section>

        <?php if ($current_user && $rexrank_stats): ?>
            <section class="rexrank-wallet-strip">
                <div><span>RexRank</span><strong><?php echo number_format((float) $rexrank_stats['balance'], 0); ?>RR</strong></div>
                <div><span>Votes Today</span><strong><?php echo (int) $rexrank_stats['daily_votes']; ?>/<?php echo (int) $rexrank_stats['daily_vote_limit']; ?></strong></div>
                <div><span>Convertible</span><strong><?php echo number_format((float) $rexrank_stats['convertible_rr'], 0); ?>RR</strong></div>
                <form method="POST" class="rexrank-convert-form">
                    <input type="hidden" name="csrf_token" value="<?php echo esc(appCsrfToken()); ?>">
                    <input type="hidden" name="action" value="convert_rexrank">
                    <input type="number" name="amount_rr" min="10" step="10" max="<?php echo (int) $rexrank_stats['convertible_rr']; ?>" value="10" <?php echo (float) $rexrank_stats['convertible_rr'] < 10 ? 'disabled' : ''; ?>>
                    <button type="submit" <?php echo (float) $rexrank_stats['convertible_rr'] < 10 ? 'disabled' : ''; ?>>Convert</button>
                </form>
            </section>
        <?php endif; ?>

        <section class="reviews-toolbar">
            <form method="GET" class="reviews-filter-form">
                <input type="text" name="search" value="<?php echo esc($search); ?>" placeholder="Search project, reviewer, title...">
                <select name="category">
                    <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <?php foreach ($category_rows as $cat): ?>
                        <?php $ck = strtolower((string) ($cat['category_key'] ?? '')); ?>
                        <option value="<?php echo esc($ck); ?>" <?php echo strtolower($category_filter) === $ck ? 'selected' : ''; ?>><?php echo esc((string) ($cat['category_label'] ?? 'Category')); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="rating">
                    <option value="all" <?php echo $rating_filter === 'all' ? 'selected' : ''; ?>>All Ratings</option>
                    <option value="4_plus" <?php echo $rating_filter === '4_plus' ? 'selected' : ''; ?>>4★ and above</option>
                    <option value="3_plus" <?php echo $rating_filter === '3_plus' ? 'selected' : ''; ?>>3★ and above</option>
                    <option value="below_2" <?php echo $rating_filter === 'below_2' ? 'selected' : ''; ?>>Below 2★</option>
                </select>
                <select name="featured">
                    <option value="all" <?php echo $featured_filter === 'all' ? 'selected' : ''; ?>>All Projects</option>
                    <option value="featured" <?php echo $featured_filter === 'featured' ? 'selected' : ''; ?>>Featured Only</option>
                    <option value="regular" <?php echo $featured_filter === 'regular' ? 'selected' : ''; ?>>Regular Only</option>
                </select>
                <button type="submit">Apply</button>
            </form>
        </section>

        <div class="reviews-layout">
            <section class="reviews-feed-wrap">
                <?php if (empty($reviews)): ?>
                    <section class="reviews-empty">
                        <i class="fas fa-inbox"></i>
                        <h3>No Approved Reviews Yet</h3>
                        <p>Once quality reviews are approved, they will appear here.</p>
                    </section>
                <?php else: ?>
                    <section class="reviews-feed" id="reviewsFeed" data-current-page="<?php echo (int) $page; ?>" data-has-more="<?php echo $page < $total_pages ? '1' : '0'; ?>">
                        <?php echo renderReviewFeedCards($reviews, $offset, $is_public_view, $current_user, $current_user_level_state, $experience_votes, $slot_costs); ?>
                    </section>

                    <div class="reviews-load-state" id="reviewsLoadState" data-total-pages="<?php echo (int) $total_pages; ?>">
                        <span><?php echo $page < $total_pages ? 'Loading more reviews' : 'End of feed'; ?></span>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="reviews-sidebar">
                <section class="sidebar-card">
                    <h3>Top Rated Projects</h3>
                    <?php if (empty($top_projects)): ?>
                        <p class="muted">Top-rated projects will appear here soon.</p>
                    <?php else: ?>
                        <ul class="top-rated-list">
                            <?php foreach ($top_projects as $tp): ?>
                                <?php $tp_rating = (float) ($tp['avg_rating'] ?? 0); ?>
                                <li>
                                    <a href="<?php echo BASE_URL; ?>/public/project-detail.php?id=<?php echo (int) $tp['id']; ?>">
                                        <strong><?php echo esc((string) ($tp['name'] ?? 'Project')); ?></strong>
                                        <?php echo renderUniversalRating([
                                            'provider' => 'coinrex',
                                            'value' => $tp_rating,
                                            'scale' => 5,
                                            'size' => 'sm',
                                            'variant' => 'cr-row-small',
                                            'show_count' => false,
                                            'class' => 'sidebar-rating-badge',
                                        ]); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <section class="sidebar-card">
                    <h3>Categories</h3>
                    <?php if (empty($category_rows)): ?>
                        <p class="muted">No categories available yet.</p>
                    <?php else: ?>
                        <ul class="category-list">
                            <?php foreach ($category_rows as $cat): ?>
                                <li>
                                    <a href="<?php echo BASE_URL; ?>/public/reviews.php?<?php echo http_build_query(array_merge($_GET, ['category' => strtolower((string) ($cat['category_key'] ?? ''))])); ?>">
                                        <span><?php echo esc((string) ($cat['category_label'] ?? 'Category')); ?></span>
                                        <strong><?php echo (int) ($cat['total'] ?? 0); ?></strong>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
</main>

<!-- Review Detail Modal -->
<div id="reviewModal" class="review-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="review-modal__backdrop"></div>
    <div class="review-modal__panel">
        <button type="button" class="review-modal__close" aria-label="Close modal">
            <i class="fas fa-times"></i>
        </button>

        <div class="review-modal__body">
            <!-- Project Row (top) -->
            <header class="project-review-head" style="margin-bottom:12px;">
                <a href="" class="project-info with-logo" id="modalProjectLink">
                    <span class="review-project-logo-wrap" id="modalProjectLogoWrap">
                        <span class="review-project-logo-fallback" id="modalProjectLogoFallback">PR</span>
                    </span>
                    <span class="project-copy">
                        <span class="project-label">Project</span>
                        <span class="project-name-row">
                            <span class="project-name" id="modalProjectName"></span>
                            <span class="project-statuses-inline" id="modalProjectStatuses"></span>
                        </span>
                    </span>
                </a>
                <div class="project-head-side">
                    <div id="modalRating"></div>
                </div>
            </header>

            <!-- Reviewer Line -->
            <div class="reviewer-line">
                <span>Reviewed by:</span>
                <strong id="modalDisplayName"></strong>
                <span class="user-level-badge" id="modalLevelBadge"></span>
                <time id="modalDate"></time>
            </div>

            <!-- Title -->
            <h3 class="review-title" id="modalTitle" style="display:none;"></h3>

            <!-- Content -->
            <div class="review-content-wrap" style="margin-bottom:12px;">
                <div class="review-content" id="modalContent" style="display:block; -webkit-line-clamp:unset;"></div>
            </div>

            <!-- Meta (side style) -->
            <div class="review-meta" style="grid-template-columns:repeat(4,minmax(0,1fr)); margin-bottom:12px;">
                <div class="meta-item"><i class="fas fa-wallet"></i><span id="modalHoldingAmount"></span></div>
                <div class="meta-item"><i class="fas fa-clock"></i><span id="modalHoldingDays"></span></div>
                <div class="meta-item"><span class="wallet-badge" id="modalWalletBadge"></span></div>
                <div class="meta-item"><i class="fas fa-ranking-star"></i><span id="modalHelpfulCount">0</span> votes</div>
            </div>

            <!-- Comments section inside modal -->
            <div class="review-bottom-row" style="margin-top:0;">
                <div class="review-engagement-bar" style="margin-bottom:14px;">
                    <button type="button" class="engagement-btn" disabled>
                        <i class="fas fa-check"></i><span>Same</span><strong id="modalSameCount">0</strong>
                    </button>
                    <button type="button" class="engagement-btn" disabled>
                        <i class="fas fa-code-compare"></i><span>Different</span><strong id="modalDifferentCount">0</strong>
                    </button>
                    <button type="button" class="engagement-btn" disabled>
                        <i class="fas fa-arrow-up"></i><span>Up</span><strong id="modalUpCount">0</strong>
                    </button>
                    <button type="button" class="engagement-btn">
                        <i class="fas fa-comment"></i><span>Comments</span><strong id="modalCommentCount">0</strong>
                    </button>
                </div>
            </div>

            <!-- Modal Comments -->
            <div id="modalCommentsWrap" style="display:none;">
                <div class="comment-list" id="modalCommentList" style="max-height:300px; overflow-y:auto;"></div>
            </div>

            <input type="hidden" id="modalReviewId" value="">
        </div>
    </div>
</div>

<div id="commentsModal" class="priority-modal comments-modal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="priority-modal__backdrop" data-close-comments></div>
    <div class="priority-modal__panel comments-modal__panel">
        <button type="button" class="priority-modal__close" data-close-comments aria-label="Close comments modal">
            <i class="fas fa-times"></i>
        </button>
        <div id="commentsModalBody"></div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // ── Modal Logic ──
    const modal = document.getElementById('reviewModal');
    const backdrop = modal.querySelector('.review-modal__backdrop');
    const panel = modal.querySelector('.review-modal__panel');
    const closeBtn = modal.querySelector('.review-modal__close');
    const commentsModal = document.getElementById('commentsModal');
    const commentsModalBody = document.getElementById('commentsModalBody');
    const reviewInsightToken = <?php echo json_encode(appCsrfToken(), JSON_UNESCAPED_SLASHES); ?>;
    const trackedImpressions = new Set();

    // Modal fields
    const mDisplayName = document.getElementById('modalDisplayName');
    const mLevelBadge = document.getElementById('modalLevelBadge');
    const mDate = document.getElementById('modalDate');
    const mRating = document.getElementById('modalRating');
    const mProjectLink = document.getElementById('modalProjectLink');
    const mProjectName = document.getElementById('modalProjectName');
    const mProjectLogoWrap = document.getElementById('modalProjectLogoWrap');
    const mProjectLogoFallback = document.getElementById('modalProjectLogoFallback');
    const mProjectStatuses = document.getElementById('modalProjectStatuses');
    const mTitle = document.getElementById('modalTitle');
    const mContent = document.getElementById('modalContent');
    const mHoldingAmount = document.getElementById('modalHoldingAmount');
    const mHoldingDays = document.getElementById('modalHoldingDays');
    const mWalletBadge = document.getElementById('modalWalletBadge');
    const mHelpfulCount = document.getElementById('modalHelpfulCount');
    const mReviewId = document.getElementById('modalReviewId');
    const mSameCount = document.getElementById('modalSameCount');
    const mDifferentCount = document.getElementById('modalDifferentCount');
    const mUpCount = document.getElementById('modalUpCount');
    const mCommentCount = document.getElementById('modalCommentCount');
    const mCommentWrap = document.getElementById('modalCommentsWrap');
    const mCommentList = document.getElementById('modalCommentList');

    function openModal(card) {
        const reviewId = card.getAttribute('data-review-id') || '';
        const ratingEl = card.querySelector('.review-rating-badge');
        const projectLink = card.querySelector('.project-info');
        const projectNameEl = card.querySelector('.project-name');
        const projectLogoWrap = card.querySelector('.review-project-logo-wrap');
        const projectLogoFallback = card.querySelector('.review-project-logo-fallback');
        const titleEl = card.querySelector('.review-title');
        const displayNameEl = card.querySelector('.reviewer-line strong');
        const dateEl = card.querySelector('.reviewer-line time');
        const levelBadgeEl = card.querySelector('.reviewer-line .user-level-badge');
        const holdingAmountEl = card.querySelector('.meta-item:nth-child(1) span');
        const holdingDaysEl = card.querySelector('.meta-item:nth-child(2) span');
        const walletBadge = card.querySelector('.wallet-badge');
        const contentEl = card.querySelector('.review-content');
        const sameCountEl = card.querySelector('.engagement-btn span+strong');
        const reviewIdInput = card.querySelector('input[name="review_id"]');

        // Display name
        mDisplayName.textContent = displayNameEl ? displayNameEl.textContent.trim() : 'Reviewer';

        // Level badge
        if (levelBadgeEl) {
            mLevelBadge.innerHTML = levelBadgeEl.outerHTML;
            mLevelBadge.style.display = '';
        } else {
            mLevelBadge.style.display = 'none';
        }

        // Date
        mDate.textContent = dateEl ? dateEl.textContent.trim() : '';

        // Rating
        mRating.innerHTML = ratingEl ? ratingEl.innerHTML : '';

        // Project
        if (projectLink) mProjectLink.href = projectLink.href;
        mProjectName.textContent = projectNameEl ? projectNameEl.textContent.trim() : '';

        // Project logo
        mProjectLogoWrap.classList.remove('is-fallback', 'has-logo-image');
        mProjectLogoWrap.style.backgroundImage = '';
        mProjectLogoFallback.textContent = projectLogoFallback ? projectLogoFallback.textContent.trim() : 'PR';
        if (projectLogoWrap && projectLogoWrap.classList.contains('has-logo-image') && projectLogoWrap.style.backgroundImage) {
            mProjectLogoWrap.style.backgroundImage = projectLogoWrap.style.backgroundImage;
            mProjectLogoWrap.classList.add('has-logo-image');
        } else {
            mProjectLogoWrap.classList.add('is-fallback');
        }

        // Project statuses
        const projectStatusesEl = card.querySelector('.project-statuses-inline');
        if (projectStatusesEl) {
            mProjectStatuses.innerHTML = projectStatusesEl.innerHTML;
            mProjectStatuses.style.display = '';
        } else {
            mProjectStatuses.style.display = 'none';
        }

        // Title
        mTitle.textContent = titleEl ? titleEl.textContent.trim() : '';
        mTitle.style.display = titleEl ? '' : 'none';

        // Full content
        const fullContent = card.getAttribute('data-full-content') || (contentEl ? contentEl.textContent || contentEl.innerHTML : '');
        mContent.innerHTML = fullContent.replace(/\n/g, '<br>');

        // Meta
        mHoldingAmount.textContent = holdingAmountEl ? holdingAmountEl.textContent.trim() : '';
        mHoldingDays.textContent = holdingDaysEl ? holdingDaysEl.textContent.trim() : '';
        if (walletBadge) {
            mWalletBadge.textContent = walletBadge.textContent.trim();
            mWalletBadge.className = 'wallet-badge ' + Array.from(walletBadge.classList).filter(function(c) { return c === 'non-custodial' || c === 'custodial' || c === 'unknown'; }).join(' ');
        }

        // Engagement counts from card
        var sameVal = 0, diffVal = 0, upVal = 0, commVal = 0;
        var countBtns = card.querySelectorAll('.engagement-btn strong');
        if (countBtns.length >= 4) {
            sameVal = countBtns[0].textContent.replace(/[^0-9]/g, '') || '0';
            diffVal = countBtns[1].textContent.replace(/[^0-9]/g, '') || '0';
            upVal = countBtns[2].textContent.replace(/[^0-9]/g, '') || '0';
            commVal = countBtns[3].textContent.replace(/[^0-9]/g, '') || '0';
        }
        mSameCount.textContent = numberWithCommas(sameVal);
        mDifferentCount.textContent = numberWithCommas(diffVal);
        mUpCount.textContent = numberWithCommas(upVal);
        mCommentCount.textContent = numberWithCommas(commVal);

        // Votes
        var voteCountEl = card.querySelector('.meta-item:nth-child(4) span');
        mHelpfulCount.textContent = voteCountEl ? voteCountEl.textContent.trim().replace(/[^0-9]/g, '') || '0' : '0';

        // Review ID
        if (reviewIdInput) mReviewId.value = reviewIdInput.value;

        // Load comments from hidden template
        var commentTemplate = document.getElementById('review-comments-' + reviewId);
        if (commentTemplate && mCommentList && mCommentWrap) {
            var commentsHtml = '';
            var commentCards = commentTemplate.querySelectorAll('.comment-card');
            if (commentCards.length > 0) {
                commentCards.forEach(function(cc) {
                    commentsHtml += cc.outerHTML;
                });
                mCommentList.innerHTML = commentsHtml;
                mCommentWrap.style.display = '';
            } else {
                mCommentList.innerHTML = '<p class="comment-empty">No comments yet.</p>';
                mCommentWrap.style.display = '';
            }
        } else if (mCommentWrap) {
            mCommentWrap.style.display = 'none';
        }

        // Show modal
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(function() {
            modal.classList.add('review-modal--open');
        });
    }

    function numberWithCommas(x) {
        return Number(x).toLocaleString('en-US');
    }

    function closeModal() {
        modal.classList.remove('review-modal--open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function trackReviewInsight(reviewIds, eventType) {
        const ids = Array.isArray(reviewIds) ? reviewIds.filter(Boolean) : [reviewIds].filter(Boolean);
        if (!ids.length || !eventType) return;
        const payload = new FormData();
        payload.append('action', 'track_review_insight');
        payload.append('event_type', eventType);
        payload.append('csrf_token', reviewInsightToken);
        ids.forEach(function(id) {
            payload.append('review_ids[]', String(id));
        });
        if (navigator.sendBeacon) {
            navigator.sendBeacon(window.location.pathname + window.location.search, payload);
            return;
        }
        fetch(window.location.href, { method: 'POST', body: payload, credentials: 'same-origin', keepalive: true }).catch(function() {});
    }

    let insightObserver = null;
    function observeReviewInsights(scope) {
        const cards = (scope || document).querySelectorAll('.review-card[data-review-id]');
        if (!cards.length) return;
        if ('IntersectionObserver' in window) {
            if (!insightObserver) {
                insightObserver = new IntersectionObserver(function(entries) {
                    const ids = [];
                    entries.forEach(function(entry) {
                        if (!entry.isIntersecting) return;
                        const id = entry.target.getAttribute('data-review-id');
                        if (!id || trackedImpressions.has(id)) return;
                        trackedImpressions.add(id);
                        ids.push(id);
                        insightObserver.unobserve(entry.target);
                    });
                    if (ids.length) trackReviewInsight(ids, 'impression');
                }, { threshold: 0.55 });
            }
            cards.forEach(function(card) {
                const id = card.getAttribute('data-review-id');
                if (id && !trackedImpressions.has(id)) {
                    insightObserver.observe(card);
                }
            });
            return;
        }

        const ids = [];
        cards.forEach(function(card) {
            const id = card.getAttribute('data-review-id');
            if (id && !trackedImpressions.has(id)) {
                trackedImpressions.add(id);
                ids.push(id);
            }
        });
        if (ids.length) trackReviewInsight(ids, 'impression');
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.read-more-btn[data-review-id]');
        if (!btn) return;
        e.preventDefault();
        const card = btn.closest('.review-card');
        if (card) {
            trackReviewInsight(card.getAttribute('data-review-id'), 'read_full');
            openModal(card);
        }
    });

    document.addEventListener('click', function(e) {
        const commentsBtn = e.target.closest('[data-comments-review]');
        if (!commentsBtn || !commentsModal || !commentsModalBody) return;
        e.preventDefault();
        const reviewId = commentsBtn.getAttribute('data-comments-review') || '';
        const template = document.getElementById('review-comments-' + reviewId);
        if (!template) return;
        commentsModalBody.innerHTML = template.innerHTML;
        commentsModal.setAttribute('aria-hidden', 'false');
        commentsModal.classList.add('priority-modal--open');
        document.body.style.overflow = 'hidden';
    });

    document.querySelectorAll('[data-close-comments]').forEach(function(close) {
        close.addEventListener('click', function() {
            if (!commentsModal || !commentsModalBody) return;
            commentsModal.classList.remove('priority-modal--open');
            commentsModal.setAttribute('aria-hidden', 'true');
            commentsModalBody.innerHTML = '';
            document.body.style.overflow = modal.classList.contains('review-modal--open') ? 'hidden' : '';
        });
    });

    // Close handlers
    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('review-modal--open')) {
            closeModal();
        }
    });

    // ── Toast ──
    const feed = document.getElementById('reviewsFeed');
    const loadState = document.getElementById('reviewsLoadState');
    let loadingMore = false;

    function loadMoreReviews() {
        if (!feed || !loadState || loadingMore || feed.dataset.hasMore !== '1') return;
        loadingMore = true;
        loadState.classList.add('is-loading');

        const url = new URL(window.location.href);
        const nextPage = (parseInt(feed.dataset.currentPage || '1', 10) || 1) + 1;
        url.searchParams.set('ajax', '1');
        url.searchParams.set('page', String(nextPage));

        fetch(url.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function(data) {
                if (data.cards_html) {
                    feed.insertAdjacentHTML('beforeend', data.cards_html);
                    observeReviewInsights(feed);
                }
                feed.dataset.currentPage = String(data.page || nextPage);
                feed.dataset.hasMore = data.has_more ? '1' : '0';
                loadState.querySelector('span').textContent = data.has_more ? 'Loading more reviews' : 'End of feed';
            })
            .catch(function() {
                loadState.querySelector('span').textContent = 'Load paused';
            })
            .finally(function() {
                loadingMore = false;
                loadState.classList.remove('is-loading');
            });
    }

    if (loadState && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) loadMoreReviews();
            });
        }, { rootMargin: '320px 0px' });
        observer.observe(loadState);
    } else {
        window.addEventListener('scroll', function() {
            if (!loadState) return;
            const rect = loadState.getBoundingClientRect();
            if (rect.top < window.innerHeight + 320) loadMoreReviews();
        }, { passive: true });
    }

    observeReviewInsights(document);

    const toast = document.getElementById('reviewsToast');
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
        }, 3000);
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
