<?php
/**
 * CoinRex About Us Page
 * Location: /coinrex/public/about.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';

// Fetch statistics for the about page
$db = getDBConnection();

// Get count of active users
$users_stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$users_stmt->execute();
$users_result = $users_stmt->fetch();
$active_users = (int) ($users_result['count'] ?? 0);

// Get count of active projects
$projects_stmt = $db->prepare("SELECT COUNT(*) as count FROM projects WHERE status = 'active'");
$projects_stmt->execute();
$projects_result = $projects_stmt->fetch();
$active_projects = (int) ($projects_result['count'] ?? 0);

// Get count of all reviews
$reviews_stmt = $db->prepare("SELECT COUNT(*) as count FROM reviews");
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->fetch();
$total_reviews = (int) ($reviews_result['count'] ?? 0);

// Format numbers with K/M suffix for display
function formatStatNumber($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M+';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'K+';
    }
    return $number . '+';
}

$users_display = formatStatNumber($active_users);
$projects_display = formatStatNumber($active_projects);
$reviews_display = formatStatNumber($total_reviews);
?>

<!-- About Page Specific Styles -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/about.css">

<main class="about-main">
    
    <!-- Hero Section -->
    <section class="about-hero">
        <div class="about-container">
            <div class="hero-content">
                <div class="hero-badge animate-fade-up">
                    <i class="fas fa-info-circle"></i>
                    <span>Who We Are</span>
                </div>
                <h1 class="hero-title animate-fade-up">About <span class="gradient-text">CoinRex</span></h1>
                <p class="hero-description animate-fade-up delay-1">
                    CoinRex is a proof-backed crypto review platform where users explore projects,
                    submit verified experiences, earn $REX rewards, and help builders grow through public trust.
                </p>
                <div class="hero-stats animate-fade-up delay-2">
                    <div class="hero-stat">
                        <span class="stat-number"><?php echo $users_display; ?></span>
                        <span class="stat-label">Active Users</span>
                    </div>
                    <div class="hero-stat">
                        <span class="stat-number"><?php echo $projects_display; ?></span>
                        <span class="stat-label">Projects</span>
                    </div>
                    <div class="hero-stat">
                        <span class="stat-number"><?php echo $reviews_display; ?></span>
                        <span class="stat-label">Reviews</span>
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
    
    <!-- Mission Section -->
    <section class="about-section mission-section">
        <div class="about-container">
            <div class="section-content">
                <div class="section-icon animate-scale">
                    <i class="fas fa-rocket"></i>
                </div>
                <h2 class="animate-fade-up">Our Mission</h2>
                <p class="mission-quote animate-fade-up delay-1">Bring transparency, trust, and real value to the crypto ecosystem.</p>
                <div class="mission-grid">
                    <div class="mission-card animate-fade-up delay-2">
                        <i class="fas fa-chart-line"></i>
                        <h3>Projects gain</h3>
                        <p>public credibility</p>
                    </div>
                    <div class="mission-card animate-fade-up delay-3">
                        <i class="fas fa-users"></i>
                        <h3>Users earn through</h3>
                        <p>proof-backed participation</p>
                    </div>
                    <div class="mission-card animate-fade-up delay-4">
                        <i class="fas fa-handshake"></i>
                        <h3>The ecosystem grows</h3>
                        <p>through moderation and proof</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- What We Do Section -->
    <section class="about-section whatwedo-section">
        <div class="about-container">
            <div class="section-content">
                <div class="section-icon animate-scale">
                    <i class="fas fa-lightbulb"></i>
                </div>
                <h2 class="animate-fade-up">What We Do</h2>
                <p class="section-subtitle animate-fade-up delay-1">CoinRex allows users to:</p>
                <div class="whatwedo-grid">
                    <div class="whatwedo-card animate-fade-up delay-2">
                        <i class="fas fa-pen-alt"></i>
                        <h3>Submit quality reviews</h3>
                        <p>backed by wallet checks, holding details, and optional screenshots</p>
                    </div>
                    <div class="whatwedo-card animate-fade-up delay-3">
                        <i class="fas fa-search"></i>
                        <h3>Explore listed projects</h3>
                        <p>with ratings, review history, and trust signals</p>
                    </div>
                    <div class="whatwedo-card animate-fade-up delay-4">
                        <i class="fas fa-tasks"></i>
                        <h3>Complete TaskHub and BoostHub missions</h3>
                        <p>to learn, progress, and earn rewards</p>
                    </div>
                    <div class="whatwedo-card animate-fade-up delay-5">
                        <i class="fas fa-link"></i>
                        <h3>Use RexLink and reward tools</h3>
                        <p>for wallet-linked approvals and claim readiness</p>
                    </div>
                </div>
                <p class="whatwedo-footer animate-fade-up delay-6">Every action is designed to ensure real engagement, not artificial metrics.</p>
            </div>
        </div>
    </section>
    
    <!-- $REX Reward System Section -->
    <section class="about-section rex-section">
        <div class="about-container">
            <div class="section-content">
                <div class="section-icon animate-scale">
                    <i class="fas fa-coins"></i>
                </div>
                <h2 class="animate-fade-up">The $REX Reward System</h2>
                <p class="animate-fade-up delay-1">At the core of CoinRex is <strong class="highlight">$REX</strong>, our platform reward system.</p>
                <div class="rex-grid animate-fade-up delay-2">
                    <div class="rex-card">
                        <i class="fas fa-check-circle"></i>
                        <span>Completing tasks</span>
                    </div>
                    <div class="rex-card">
                        <i class="fas fa-check-circle"></i>
                        <span>Writing verified reviews</span>
                    </div>
                    <div class="rex-card">
                        <i class="fas fa-check-circle"></i>
                        <span>Passing proof and eligibility checks</span>
                    </div>
                    <div class="rex-card">
                        <i class="fas fa-check-circle"></i>
                        <span>Inviting others</span>
                    </div>
                </div>
                <div class="rex-future animate-fade-up delay-3">
                    <i class="fas fa-chart-line"></i>
                    <p>$REX rewards are tracked through the ledger, claim snapshots, and RexLink approval flows as CoinRex moves toward broader on-chain utility.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Growth & Recognition Section -->
    <section class="about-section growth-section">
        <div class="about-container">
            <div class="section-content">
                <div class="section-icon animate-scale">
                    <i class="fas fa-chart-simple"></i>
                </div>
                <h2 class="animate-fade-up">Growth & Recognition</h2>
                <p class="animate-fade-up delay-1">CoinRex is not just about earning; it is about progression, proof, and recognition.</p>
                <div class="growth-levels animate-fade-up delay-2">
                    <div class="level beginner">
                        <i class="fas fa-seedling"></i>
                        <span>Beginner</span>
                    </div>
                    <div class="level-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                    <div class="level premium">
                        <i class="fas fa-gem"></i>
                        <span>Pro</span>
                    </div>
                    <div class="level-arrow">
                        <i class="fas fa-arrow-right"></i>
                    </div>
                    <div class="level expert">
                        <i class="fas fa-crown"></i>
                        <span>Expert</span>
                    </div>
                </div>
                <div class="growth-ways animate-fade-up delay-3">
                    <div class="way">
                        <i class="fas fa-star"></i>
                        <span>Contributing high-quality reviews</span>
                    </div>
                    <div class="way">
                        <i class="fas fa-bolt"></i>
                        <span>Staying active in the ecosystem</span>
                    </div>
                    <div class="way">
                        <i class="fas fa-users"></i>
                        <span>Building strong referral networks</span>
                    </div>
                </div>
                <p class="animate-fade-up delay-4">From Beginner to Pro to Expert, each level unlocks stronger access, credibility, and reward opportunities.</p>
            </div>
        </div>
    </section>
    
    <!-- For Projects Section -->
    <section class="about-section projects-section">
        <div class="about-container">
            <div class="section-content">
                <div class="section-icon animate-scale">
                    <i class="fas fa-building"></i>
                </div>
                <h2 class="animate-fade-up">For Projects</h2>
                <p class="animate-fade-up delay-1">We provide a platform where crypto projects can:</p>
                <div class="projects-grid animate-fade-up delay-2">
                    <div class="project-benefit">
                        <i class="fas fa-list"></i>
                        <span>Submit projects through DevHub</span>
                    </div>
                    <div class="project-benefit">
                        <i class="fas fa-comment-dots"></i>
                        <span>Receive genuine user feedback</span>
                    </div>
                    <div class="project-benefit">
                        <i class="fas fa-shield-alt"></i>
                        <span>Build trust through proof-backed reviews</span>
                    </div>
                    <div class="project-benefit">
                        <i class="fas fa-chart-line"></i>
                        <span>Grow through listings, widgets, and sponsored visibility</span>
                    </div>
                </div>
                <p class="projects-footer animate-fade-up delay-3">Our goal is to help projects earn attention through community validation, moderation, and transparent trust signals.</p>
            </div>
        </div>
    </section>
    
    <!-- Vision Section -->
    <section class="about-section vision-section">
        <div class="about-container">
            <div class="section-content">
                <div class="section-icon animate-scale">
                    <i class="fas fa-eye"></i>
                </div>
                <h2 class="animate-fade-up">Our Vision</h2>
                <p class="animate-fade-up delay-1">We envision CoinRex as more than just a platform.</p>
                <div class="vision-quote animate-fade-up delay-2">
                    <i class="fas fa-quote-left"></i>
                    <p>It's a community-powered ecosystem where:</p>
                </div>
                <div class="vision-grid">
                    <div class="vision-card animate-fade-up delay-3">
                        <i class="fas fa-gift"></i>
                        <h3>Users are rewarded</h3>
                        <p>fairly</p>
                    </div>
                    <div class="vision-card animate-fade-up delay-4">
                        <i class="fas fa-balance-scale"></i>
                        <h3>Projects are judged</h3>
                        <p>transparently</p>
                    </div>
                    <div class="vision-card animate-fade-up delay-5">
                        <i class="fas fa-chart-line"></i>
                        <h3>Growth is driven by</h3>
                        <p>real participation</p>
                    </div>
                </div>
                <p class="animate-fade-up delay-6">As we grow, CoinRex will keep expanding reviews, DevHub, rewards, RexLink, and on-chain utility around one principle: trust before hype.</p>
            </div>
        </div>
    </section>
    
    <!-- Why CoinRex Section -->
    <section class="about-section why-section">
        <div class="about-container">
            <div class="section-content">
                <div class="section-icon animate-scale">
                    <i class="fas fa-diamond"></i>
                </div>
                <h2 class="animate-fade-up">Why CoinRex?</h2>
                <div class="why-compare animate-fade-up delay-1">
                    <div class="why-bad">
                        <h3>No</h3>
                        <ul>
                            <li>Fake engagement</li>
                            <li>Bots</li>
                            <li>Paid manipulation</li>
                        </ul>
                    </div>
                    <div class="why-good">
                        <h3>Only</h3>
                        <ul>
                            <li>Real users</li>
                            <li>Real actions</li>
                            <li>Real rewards</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Join the Movement CTA -->
    <section class="about-cta">
        <div class="about-container">
            <div class="cta-content">
                <h2 class="animate-fade-up">Join the Movement</h2>
                <p class="animate-fade-up delay-1">CoinRex is built for the future of crypto, where trust, transparency, and community come first.</p>
                <div class="cta-buttons animate-fade-up delay-2">
                    <a href="<?php echo BASE_URL; ?>/auth/auth.php?tab=register" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Join Now
                    </a>
                    <a href="<?php echo BASE_URL; ?>/projects.php" class="btn btn-outline">
                        <i class="fas fa-chart-line"></i> Explore Projects
                    </a>
                </div>
                <p class="cta-subtitle animate-fade-up delay-3">CoinRex is your platform.</p>
            </div>
        </div>
    </section>
    
</main>

<script>
// Intersection Observer for scroll animations
const animateElements = document.querySelectorAll('.animate-fade-up, .animate-scale, .animate-fade-right, .animate-fade-left');

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animated');
            // Optional: keep observing for repeat animations
            // observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.15, rootMargin: '0px 0px -30px 0px' });

animateElements.forEach(element => {
    observer.observe(element);
});

// Add a small delay to ensure elements are observed after page load
window.addEventListener('load', () => {
    animateElements.forEach(element => {
        if (element.getBoundingClientRect().top < window.innerHeight) {
            element.classList.add('animated');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
