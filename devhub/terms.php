<?php
require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    $redirect = urlencode($_SERVER['REQUEST_URI']);
    header('Location: ' . BASE_URL . '/auth/auth.php?redirect=' . $redirect);
    exit();
}

$user_id = getCurrentUserId();
$db = getDevHubDB();

$stmt = $db->prepare("SELECT id FROM developer_verification WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$user_id]);
$existing_verification = $stmt->fetch();
if ($existing_verification) {
    header('Location: ' . BASE_URL . '/devhub/apply.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agree_proceed'])) {
    $_SESSION['devhub_terms_agreed'] = true;
    $_SESSION['devhub_terms_agreed_at'] = time();

    header('Location: ' . BASE_URL . '/devhub/apply.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevHub Access Terms | CoinRex</title>
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo BASE_URL; ?>/assets/images/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/theme.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/devhub/assets/css/devhub.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/devhub/assets/css/terms.css">
</head>
<body>
    <main class="devhub-terms-main">
        <section class="devhub-hero">
            <div class="devhub-container">
                <div class="hero-content">
                    <div class="hero-badge animate-fade-up">
                        <i class="fas fa-shield-alt"></i>
                        <span>Developer Access Rules</span>
                    </div>
                    <h1 class="hero-title animate-fade-up">Welcome to <span class="gradient-text">DevHub</span></h1>
                    <p class="hero-description animate-fade-up delay-1">
                        DevHub is where verified builders list projects, earn trust, and reach real users.
                        Before you continue, review the standards that protect quality across CoinRex.
                    </p>
                    <div class="hero-stats animate-fade-up delay-2">
                        <div class="hero-stat">
                            <span class="stat-number">3</span>
                            <span class="stat-label">Trust Pillars</span>
                        </div>
                        <div class="hero-stat">
                            <span class="stat-number">2</span>
                            <span class="stat-label">Verification Paths</span>
                        </div>
                        <div class="hero-stat">
                            <span class="stat-number">100%</span>
                            <span class="stat-label">Transparency First</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="hero-wave">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                    <path fill="#0f172a" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
                </svg>
            </div>
        </section>

        <section class="devhub-overview">
            <div class="devhub-container">
                <?php if ($error): ?>
                    <div class="terms-alert animate-fade-up">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <div class="overview-grid">
                    <article class="overview-card animate-fade-up">
                        <i class="fas fa-badge-check"></i>
                        <h3>Verified Identity</h3>
                        <p>DevHub requires proof that you control the project or represent its public presence.</p>
                    </article>
                    <article class="overview-card animate-fade-up delay-1">
                        <i class="fas fa-list-check"></i>
                        <h3>Quality Listings</h3>
                        <p>Every project submission is expected to be accurate, complete, and safe for community review.</p>
                    </article>
                    <article class="overview-card animate-fade-up delay-2">
                        <i class="fas fa-users"></i>
                        <h3>Real Community Trust</h3>
                        <p>CoinRex is built around transparency, not hype, manipulation, or fake exposure.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="devhub-sections">
            <div class="devhub-container">
                <div class="terms-grid">
                    <article class="terms-panel animate-fade-up">
                        <div class="section-icon"><i class="fas fa-handshake"></i></div>
                        <h2>Welcome to DevHub</h2>
                        <p>DevHub is the official platform for developers, project owners, and teams to list their crypto projects on CoinRex.</p>
                        <p>Before proceeding, please read the following guidelines carefully.</p>
                    </article>

                    <article class="terms-panel animate-fade-up delay-1">
                        <div class="section-icon"><i class="fas fa-info-circle"></i></div>
                        <h2>What is DevHub?</h2>
                        <p>Verified developers can use DevHub to:</p>
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Submit crypto projects for listing</li>
                            <li><i class="fas fa-check-circle"></i> Reach real users for authentic reviews</li>
                            <li><i class="fas fa-check-circle"></i> Build trust through transparent validation</li>
                        </ul>
                        <p>All projects listed on CoinRex go through a verification process to ensure quality and authenticity.</p>
                    </article>

                    <article class="terms-panel animate-fade-up delay-2">
                        <div class="section-icon"><i class="fas fa-shield-alt"></i></div>
                        <h2>Verification Requirement</h2>
                        <p>To maintain trust and prevent fake listings, all developers must verify their identity before submitting projects.</p>
                        <div class="mini-grid">
                            <div class="mini-card">
                                <strong>Social Proof</strong>
                                <span>Twitter/X or another public profile</span>
                            </div>
                            <div class="mini-card">
                                <strong>Website Proof</strong>
                                <span>Official domain or website verification</span>
                            </div>
                        </div>
                        <p>At least one verification method is required.</p>
                    </article>

                    <article class="terms-panel animate-fade-up">
                        <div class="section-icon"><i class="fas fa-list-check"></i></div>
                        <h2>Project Rules</h2>
                        <p>When submitting a project, you agree that:</p>
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> All provided information is accurate and truthful</li>
                            <li><i class="fas fa-check-circle"></i> You are authorized to represent the project</li>
                            <li><i class="fas fa-check-circle"></i> The contract address and links are valid</li>
                            <li><i class="fas fa-check-circle"></i> The project does not promote scams, fraud, or misleading claims</li>
                        </ul>
                        <p>Incomplete or misleading submissions may be rejected.</p>
                    </article>

                    <article class="terms-panel animate-fade-up delay-1">
                        <div class="section-icon"><i class="fas fa-user-check"></i></div>
                        <h2>Responsibilities</h2>
                        <p>As a developer, you are responsible for:</p>
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Maintaining accurate project information</li>
                            <li><i class="fas fa-check-circle"></i> Updating links and details if changed</li>
                            <li><i class="fas fa-check-circle"></i> Ensuring your project remains trustworthy</li>
                        </ul>
                        <div class="callout warning">
                            <i class="fas fa-flag"></i>
                            <div>
                                <p>If suspicious activity is detected, your developer account or project may be flagged, suspended, or removed from the platform.</p>
                            </div>
                        </div>
                    </article>

                    <article class="terms-panel animate-fade-up delay-2">
                        <div class="section-icon"><i class="fas fa-ban"></i></div>
                        <h2>Prohibited Content</h2>
                        <p>The following are strictly not allowed:</p>
                        <ul class="terms-list terms-list-danger">
                            <li><i class="fas fa-ban"></i> Scam or rug-pull projects</li>
                            <li><i class="fas fa-ban"></i> Fake tokens or impersonation</li>
                            <li><i class="fas fa-ban"></i> Misleading reward campaigns</li>
                            <li><i class="fas fa-ban"></i> Spam submissions</li>
                        </ul>
                        <p>Violation may result in permanent restriction.</p>
                    </article>

                    <article class="terms-panel agreement-panel animate-fade-up">
                        <div class="section-icon"><i class="fas fa-file-signature"></i></div>
                        <h2>Agreement</h2>
                        <p>CoinRex is built to create a trusted ecosystem where developers get fair exposure and users get verified opportunities.</p>
                        <p>We aim to maintain transparency, fairness, and quality across the platform.</p>
                        <p>By continuing to DevHub, you agree to follow all rules and guidelines stated above.</p>
                        <p>Failure to comply may result in restrictions on your account or project listings.</p>
                    </article>
                </div>
            </div>
        </section>

        <section class="devhub-cta">
            <div class="devhub-container">
                <form method="post" class="terms-actions animate-fade-up">
                    <div class="cta-header">
                        <h2>Ready to Continue Into DevHub?</h2>
                        <p>Confirm that you understand the guidelines and continue to developer verification.</p>
                    </div>
                    <div class="terms-agreement">
                        <label class="agreement-checkbox">
                            <input type="checkbox" id="terms-agree" required>
                            <span class="checkmark"></span>
                            <span class="agreement-text">I understand and agree to DevHub guidelines</span>
                        </label>
                    </div>
                    <div class="terms-buttons">
                        <a href="<?php echo BASE_URL; ?>/index.php" class="btn-secondary">Back to CoinRex</a>
                        <button type="submit" name="agree_proceed" class="btn-primary" id="continue-btn" disabled>Agree & Continue</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkbox = document.getElementById('terms-agree');
            const continueBtn = document.getElementById('continue-btn');
            const animateElements = document.querySelectorAll('.animate-fade-up, .animate-scale, .animate-fade-right, .animate-fade-left');

            if (checkbox && continueBtn) {
                checkbox.addEventListener('change', function() {
                    continueBtn.disabled = !this.checked;
                });
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animated');
                    }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -30px 0px' });

            animateElements.forEach(element => {
                observer.observe(element);
            });

            window.addEventListener('load', () => {
                animateElements.forEach(element => {
                    if (element.getBoundingClientRect().top < window.innerHeight) {
                        element.classList.add('animated');
                    }
                });
            });
        });
    </script>
</body>
</html>
