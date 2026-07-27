<?php
/**
 * CoinRex Project Detail Page
 * Location: /coinrex/project-detail.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
requireFeatureAccess('projects');
requireProjectReviewAccess('/taskhub.php');

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id <= 0) {
    redirect(BASE_URL . '/public/projects.php');
}

$db = getDBConnection();
$has_featured_column = tableHasColumn('projects', 'is_featured');
$has_sponsored_column = tableHasColumn('projects', 'is_sponsored');
$featured_select = $has_featured_column ? 'COALESCE(is_featured, 0)' : '0';
$sponsored_select = $has_sponsored_column ? 'COALESCE(is_sponsored, 0)' : '0';
$stmt = $db->prepare("SELECT p.*, {$featured_select} AS is_featured, {$sponsored_select} AS is_sponsored FROM projects p WHERE p.id = ? AND p.approval_status = 'approved'");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    redirect(BASE_URL . '/public/projects.php');
}

$project_logo_url = coinrexNormalizeMediaUrl((string) ($project['logo'] ?? ''));
$project_name = trim((string) ($project['name'] ?? 'Project'));
$project_description = trim(preg_replace('/\s+/', ' ', strip_tags((string) ($project['description'] ?? ''))));
$page_title = $project_name . ' Reviews, Trust Score & Rewards | CoinRex';
$meta_description = $project_description !== ''
    ? substr($project_description, 0, 155)
    : 'View proof-backed reviews, eligibility requirements, rewards, and trust details for ' . $project_name . ' on CoinRex.';
$meta_keywords = $project_name . ', crypto project reviews, CoinRex reviews, blockchain trust score';
$seo_base_url = defined('PUBLIC_BASE_URL') ? rtrim(PUBLIC_BASE_URL, '/') : rtrim(BASE_URL, '/');
$canonical_url = $seo_base_url . '/public/project-detail.php?id=' . $project_id;
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/project-detail.css?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/css/project-detail.css'); ?>">

<main class="project-detail-main">
    <div class="detail-container">
        
        <!-- Back Button -->
        <a href="<?php echo BASE_URL; ?>/public/projects.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Projects
        </a>
        
        <!-- Project Header -->
        <div class="project-header animate-fade-up">
            <div class="project-logo-large<?php echo $project_logo_url !== '' ? ' has-logo-image' : ''; ?>"<?php if ($project_logo_url !== ''): ?> style="background-image: url('<?php echo htmlspecialchars($project_logo_url, ENT_QUOTES, 'UTF-8'); ?>');" aria-label="<?php echo htmlspecialchars((string) $project['name'], ENT_QUOTES, 'UTF-8'); ?> logo"<?php endif; ?>>
                <?php if($project_logo_url !== ''): ?>
                    <img src="<?php echo htmlspecialchars($project_logo_url, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $project['name'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php else: ?>
                    <div class="logo-placeholder-large">
                        <?php echo strtoupper(substr($project['name'], 0, 2)); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="project-info">
                <h1>
                    <?php echo htmlspecialchars($project['name']); ?>
                    <span class="project-status-chip <?php echo (int)$project['is_featured'] === 1 ? 'featured' : 'regular'; ?>">
                        <i class="fas <?php echo (int)$project['is_featured'] === 1 ? 'fa-gem' : 'fa-circle-check'; ?>"></i>
                        <?php echo (int)$project['is_featured'] === 1 ? 'Featured' : 'Regular'; ?>
                    </span>
                    <?php if ((int) ($project['is_sponsored'] ?? 0) === 1): ?>
                        <span class="project-status-chip sponsored">
                            <i class="fas fa-bullhorn"></i>
                            Sponsored
                        </span>
                    <?php endif; ?>
                </h1>
                <div class="project-meta">
                    <span class="category-badge"><i class="fas fa-tag"></i> <?php echo ucfirst($project['category']); ?></span>
                </div>
                <p class="project-full-description"><?php echo nl2br(htmlspecialchars($project['description'] ?? 'No description available.')); ?></p>
            </div>
        </div>
        
        <!-- Review Requirements Section -->
        <div class="requirements-section animate-fade-up delay-1">
            <h2><i class="fas fa-clipboard-list"></i> Review Requirements</h2>
            <div class="requirements-grid">
                <div class="requirement-card">
                    <i class="fas fa-dollar-sign"></i>
                    <h3>Holding</h3>
                    <p><strong>$<?php echo number_format($project['min_holding_amount'], 2); ?></strong> minimum</p>
                </div>
                <div class="requirement-card">
                    <i class="fas fa-clock"></i>
                    <h3>Duration</h3>
                    <p><strong><?php echo $project['required_holding_days']; ?> days</strong> minimum</p>
                </div>
                <div class="requirement-card">
                    <i class="fas fa-coins"></i>
                    <h3>Reward</h3>
                    <p>Up to <strong><?php echo $project['max_reward_rex']; ?> $REX</strong></p>
                </div>
                <div class="requirement-card">
                    <i class="fas fa-file-alt"></i>
                    <h3>Review</h3>
                    <p><strong>150+</strong> characters</p>
                </div>
            </div>
        </div>
        
        <!-- Terms & Conditions Section -->
        <div class="terms-section animate-fade-up delay-2">
            <h2><i class="fas fa-file-contract"></i> Review Terms</h2>
            <div class="terms-content">
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>1. Wallet Proof</strong>
                        <p>Connect RexLink or an external wallet for the fastest check.</p>
                    </div>
                </div>
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>2. Eligibility</strong>
                        <p>CoinRex checks the wallet against this project.</p>
                    </div>
                </div>
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>3. Manual Proof Fallback</strong>
                        <p>Use wallet address, TX hash, and screenshot if you cannot connect.</p>
                    </div>
                </div>
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>4. Real Review</strong>
                        <p>Write your own 150+ character experience.</p>
                    </div>
                </div>
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>5. Security</strong>
                        <p>Never upload seed phrases or private keys.</p>
                    </div>
                </div>
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>6. One Review Per Project</strong>
                        <p>Each account can submit one review per project.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons animate-fade-up delay-3">
            <a href="<?php echo BASE_URL; ?>/public/submit-review.php?project_id=<?php echo $project['id']; ?>" class="btn-submit-review">
                <i class="fas fa-pen-alt"></i> Write Review
            </a>
            <a href="<?php echo BASE_URL; ?>/public/projects.php" class="btn-browse-more">
                <i class="fas fa-search"></i> Browse More Projects
            </a>
        </div>
        
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
