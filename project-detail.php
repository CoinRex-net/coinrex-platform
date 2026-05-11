<?php
/**
 * CoinRex Project Detail Page
 * Location: /coinrex/project-detail.php
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
requireProjectReviewAccess('/taskhub.php');
require_once __DIR__ . '/includes/header.php';

$project_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($project_id <= 0) {
    redirect(BASE_URL . '/projects.php');
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
    redirect(BASE_URL . '/projects.php');
}

$project_logo_url = coinrexNormalizeMediaUrl((string) ($project['logo'] ?? ''));
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/project-detail.css">

<main class="project-detail-main">
    <div class="detail-container">
        
        <!-- Back Button -->
        <a href="<?php echo BASE_URL; ?>/projects.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Projects
        </a>
        
        <!-- Project Header -->
        <div class="project-header animate-fade-up">
            <div class="project-logo-large">
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
            <h2><i class="fas fa-clipboard-list"></i> Quality Review Requirements</h2>
            <div class="requirements-grid">
                <div class="requirement-card">
                    <i class="fas fa-dollar-sign"></i>
                    <h3>Minimum Holding</h3>
                    <p>You must hold at least <strong>$<?php echo number_format($project['min_holding_amount'], 2); ?></strong> worth of tokens</p>
                </div>
                <div class="requirement-card">
                    <i class="fas fa-clock"></i>
                    <h3>Holding Duration</h3>
                    <p>Minimum <strong><?php echo $project['required_holding_days']; ?> days</strong> of holding required</p>
                </div>
                <div class="requirement-card">
                    <i class="fas fa-coins"></i>
                    <h3>$REX Reward</h3>
                    <p>Earn up to <strong><?php echo $project['max_reward_rex']; ?> $REX</strong> per quality review</p>
                </div>
                <div class="requirement-card">
                    <i class="fas fa-file-alt"></i>
                    <h3>Review Length</h3>
                    <p>Minimum <strong>150 characters</strong> with detailed analysis</p>
                </div>
            </div>
        </div>
        
        <!-- Terms & Conditions Section -->
        <div class="terms-section animate-fade-up delay-2">
            <h2><i class="fas fa-file-contract"></i> Terms & Conditions for Quality Review</h2>
            <div class="terms-content">
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>1. Proof of Transaction Required</strong>
                        <p>You must provide a valid transaction hash (TX Hash) from a blockchain explorer showing your interaction with the project.</p>
                    </div>
                </div>
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>2. Screenshot Evidence</strong>
                        <p>A clear screenshot showing your wallet balance or transaction is mandatory. Edited or fake screenshots will lead to immediate rejection.</p>
                    </div>
                </div>
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>3. Honest & Detailed Review</strong>
                        <p>Reviews must be at least 150 characters and provide genuine insights. AI-generated or copied reviews will be rejected.</p>
                    </div>
                </div>
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>4. Wallet Type Impact on Rewards</strong>
                        <p>Non-custodial wallets (Metamask, Trust Wallet, etc.) receive full rewards. Custodial/exchange wallets (Binance, OKX, Coinbase) receive 50% of the calculated reward.</p>
                    </div>
                </div>
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>5. Manual Verification</strong>
                        <p>All reviews and proofs are manually verified by our team. This process takes 24-48 hours.</p>
                    </div>
                </div>
                <div class="term-item">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>6. One Review Per Project</strong>
                        <p>You can submit only one quality review per project. Multiple reviews may be rejected.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="action-buttons animate-fade-up delay-3">
            <a href="<?php echo BASE_URL; ?>/submit-review.php?project_id=<?php echo $project['id']; ?>" class="btn-submit-review">
                <i class="fas fa-pen-alt"></i> Post Quality Review
            </a>
            <a href="<?php echo BASE_URL; ?>/projects.php" class="btn-browse-more">
                <i class="fas fa-search"></i> Browse More Projects
            </a>
        </div>
        
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
