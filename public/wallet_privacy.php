<?php
/**
 * Wallet Platform - Privacy Policy Page
 * Location: /coinrex/public/wallet_privacy.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/wallet_header.php';

// Wallet platform specific privacy info
$site_email = 'support@coinrex.xyz';
?>

<!-- Wallet Platform Specific Styles -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/terms.css">

<main class="wallet-page">

    <!-- ========================================
         HERO
         ======================================== -->
    <section class="wallet-hero" style="padding: 72px 0 56px;">
        <div class="wallet-container">
            <div class="wallet-hero-content">
                <div class="hero-badge animate-fade-up">
                    <i class="fas fa-shield-alt"></i>
                    <span>Your Privacy Matters</span>
                </div>
                <h1 class="hero-title animate-fade-up">Privacy <span class="gradient-text">Policy</span></h1>
                <p class="hero-description animate-fade-up delay-1">
                    This policy explains what data RexLink collects, why it is needed, and how we protect
                    your information across wallet functionality and platform activity.
                </p>
                <div class="hero-stats animate-fade-up delay-2">
                    <div class="hero-stat">
                        <span class="stat-number">7</span>
                        <span class="stat-label">Policy Sections</span>
                    </div>
                    <div class="hero-stat">
                        <span class="stat-number">0%</span>
                        <span class="stat-label">Data Sold</span>
                    </div>
                    <div class="hero-stat">
                        <span class="stat-number">24/7</span>
                        <span class="stat-label">Security Monitoring</span>
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

    <!-- ========================================
         OVERVIEW GRID
         ======================================== -->
    <section class="terms-overview">
        <div class="wallet-container">
            <div class="overview-grid">
                <article class="overview-card animate-fade-up">
                    <i class="fas fa-user-lock"></i>
                    <h3>Protected Accounts</h3>
                    <p>We collect minimum required data and apply safeguards to prevent unauthorized access.</p>
                </article>
                <article class="overview-card animate-fade-up delay-1">
                    <i class="fas fa-database"></i>
                    <h3>Purpose-Limited Use</h3>
                    <p>Your information is used for wallet features, rewards, platform integrity, and support only.</p>
                </article>
                <article class="overview-card animate-fade-up delay-2">
                    <i class="fas fa-scale-balanced"></i>
                    <h3>User Control</h3>
                    <p>You can request access, correction, or deletion of your eligible account data.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ========================================
         TERMS SECTIONS
         ======================================== -->
    <section class="terms-section-shell">
        <div class="wallet-container">
            <div class="terms-section-list">
                <article class="terms-card animate-fade-up">
                    <div class="terms-card-head"><div class="section-number">1</div><div><h2>Introduction</h2><p>Our commitment to user privacy and responsible data handling.</p></div></div>
                    <div class="terms-card-body">
                        <p><strong>RexLink</strong> respects your privacy and is committed to protecting your personal information.</p>
                        <p>This Privacy Policy explains how we collect, use, and safeguard your data.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-1">
                    <div class="terms-card-head"><div class="section-number">2</div><div><h2>Information We Collect</h2><p>Data categories needed for secure and reliable platform operations.</p></div></div>
                    <div class="terms-card-body">
                        <p>We may collect the following information:</p>
                        <div class="terms-dual-grid">
                            <div class="terms-mini-panel good"><h3>Account Data</h3><ul class="terms-list"><li><i class="fas fa-check-circle"></i> Name and username</li><li><i class="fas fa-check-circle"></i> Email address</li><li><i class="fas fa-check-circle"></i> Wallet address (if paired)</li></ul></div>
                            <div class="terms-mini-panel"><h3>Usage & Security Data</h3><ul class="terms-list"><li><i class="fas fa-check-circle"></i> IP address, device, browser</li><li><i class="fas fa-check-circle"></i> Platform actions and session signals</li><li><i class="fas fa-check-circle"></i> Anti-abuse and fraud review signals</li></ul></div>
                        </div>
                        <div class="terms-callout info"><i class="fas fa-user-plus"></i><div><p>We may also store optional profile details, wallet pairing/session metadata, and claim records when you use those features.</p></div></div>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-2">
                    <div class="terms-card-head"><div class="section-number">3</div><div><h2>How We Use Your Information</h2><p>Operational, security, and product-improvement use cases.</p></div></div>
                    <div class="terms-card-body">
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Create and manage your wallet</li>
                            <li><i class="fas fa-check-circle"></i> Provide wallet-linked review eligibility and claim approvals</li>
                            <li><i class="fas fa-check-circle"></i> Improve user experience</li>
                            <li><i class="fas fa-check-circle"></i> Prevent fraud and abuse</li>
                            <li><i class="fas fa-check-circle"></i> Communicate updates and support</li>
                        </ul>
                    </div>
                </article>

                <article class="terms-card animate-fade-up">
                    <div class="terms-card-head"><div class="section-number">4</div><div><h2>Cookies & Tracking</h2><p>How cookies support sessions and analytics.</p></div></div>
                    <div class="terms-card-body">
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Enhance user experience</li>
                            <li><i class="fas fa-check-circle"></i> Understand platform usage and reliability</li>
                            <li><i class="fas fa-check-circle"></i> Maintain login sessions</li>
                        </ul>
                        <p>You can disable cookies through your browser settings.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-1">
                    <div class="terms-card-head"><div class="section-number">5</div><div><h2>Data Sharing</h2><p>Limited sharing under strict purpose and legal boundaries.</p></div></div>
                    <div class="terms-card-body">
                        <p><strong>We do not sell your personal data.</strong></p>
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> With trusted service providers such as hosting, email, or infrastructure providers</li>
                            <li><i class="fas fa-check-circle"></i> When required by law</li>
                            <li><i class="fas fa-check-circle"></i> To protect platform security</li>
                        </ul>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-2">
                    <div class="terms-card-head"><div class="section-number">6</div><div><h2>Data Security</h2><p>Controls implemented to reduce risk and unauthorized access.</p></div></div>
                    <div class="terms-card-body">
                        <p>We implement security measures to protect your data.</p>
                        <div class="terms-callout warning"><i class="fas fa-exclamation-triangle"></i><div><p>However, no system is 100% secure, and we cannot guarantee absolute security.</p></div></div>
                    </div>
                </article>

                <article class="terms-card animate-fade-up">
                    <div class="terms-card-head"><div class="section-number">7</div><div><h2>User Rights</h2><p>Your rights to access and control personal data.</p></div></div>
                    <div class="terms-card-body">
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Access your data</li>
                            <li><i class="fas fa-check-circle"></i> Request correction or deletion</li>
                            <li><i class="fas fa-check-circle"></i> Stop using the platform at any time</li>
                        </ul>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- ========================================
         CTA
         ======================================== -->
    <section class="terms-cta">
        <div class="wallet-container">
            <div class="terms-cta-card">
                <h2 class="animate-fade-up">Your Data, Your Trust</h2>
                <p class="animate-fade-up delay-1">By using RexLink, you acknowledge that you have read, understood, and agree to this Privacy Policy.</p>
                <div class="terms-cta-actions animate-fade-up delay-2">
                    <a href="<?php echo BASE_URL; ?>/wallet.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Wallet</a>
                    <a href="<?php echo BASE_URL; ?>/public/contact.php" class="btn btn-primary"><i class="fas fa-headset"></i> Contact Support</a>
                </div>
            </div>
        </div>
    </section>

</main>

<script>
const animateElements = document.querySelectorAll('.animate-fade-up, .animate-scale, .animate-fade-right, .animate-fade-left');

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animated');
        }
    });
}, { threshold: 0.15, rootMargin: '0px 0px -30px 0px' });

animateElements.forEach(element => {
    observer.observe(element);

    window.addEventListener('load', () => {
        if (element.getBoundingClientRect().top < window.innerHeight) {
            element.classList.add('animated');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</html>