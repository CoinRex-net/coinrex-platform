<?php
/**
 * CoinRex Privacy Policy Page
 * Location: /coinrex/public/privacy.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Privacy Page Specific Styles -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/terms.css">

<main class="terms-main">
    <section class="terms-hero">
        <div class="terms-container">
            <div class="terms-hero-content">
                <div class="hero-badge animate-fade-up">
                    <i class="fas fa-shield-alt"></i>
                    <span>Your Privacy Matters</span>
                </div>
                <h1 class="hero-title animate-fade-up">Privacy <span class="gradient-text">Policy</span></h1>
                <p class="hero-description animate-fade-up delay-1">
                    This policy explains what data CoinRex collects, why it is needed, and how we protect
                    your information across authentication, reviews, rewards, RexLink, and platform activity.
                </p>
                <div class="hero-stats animate-fade-up delay-2">
                    <div class="hero-stat">
                        <span class="stat-number">11</span>
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
                <div class="terms-meta animate-fade-up delay-3">
                    <span class="terms-meta-pill">
                        <i class="fas fa-calendar-alt"></i>
                        Last Updated: <?php echo date('F j, Y'); ?>
                    </span>
                    <span class="terms-meta-pill">
                        <i class="fas fa-user-shield"></i>
                        Transparent data practices and user control
                    </span>
                </div>
            </div>
        </div>
        <div class="hero-wave">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320">
                <path fill="#0f172a" fill-opacity="1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,154.7C960,171,1056,181,1152,165.3C1248,149,1344,107,1392,85.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path>
            </svg>
        </div>
    </section>

    <section class="terms-overview">
        <div class="terms-container">
            <div class="overview-grid">
                <article class="overview-card animate-fade-up">
                    <i class="fas fa-user-lock"></i>
                    <h3>Protected Accounts</h3>
                    <p>We collect minimum required data and apply safeguards to prevent unauthorized access.</p>
                </article>
                <article class="overview-card animate-fade-up delay-1">
                    <i class="fas fa-database"></i>
                    <h3>Purpose-Limited Use</h3>
                    <p>Your information is used for account features, rewards, platform integrity, and support only.</p>
                </article>
                <article class="overview-card animate-fade-up delay-2">
                    <i class="fas fa-scale-balanced"></i>
                    <h3>User Control</h3>
                    <p>You can request access, correction, or deletion of your eligible account data.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="terms-section-shell">
        <div class="terms-container">
            <div class="terms-section-list">
                <article class="terms-card animate-fade-up">
                    <div class="terms-card-head"><div class="section-number">1</div><div><h2>Introduction</h2><p>Our commitment to user privacy and responsible data handling.</p></div></div>
                    <div class="terms-card-body">
                        <p><strong>CoinRex</strong> respects your privacy and is committed to protecting your personal information.</p>
                        <p>This Privacy Policy explains how we collect, use, and safeguard your data.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-1">
                    <div class="terms-card-head"><div class="section-number">2</div><div><h2>Information We Collect</h2><p>Data categories needed for secure and reliable platform operations.</p></div></div>
                    <div class="terms-card-body">
                        <p>We may collect the following information:</p>
                        <div class="terms-dual-grid">
                            <div class="terms-mini-panel good"><h3>Account Data</h3><ul class="terms-list"><li><i class="fas fa-check-circle"></i> Name, username, and email address</li><li><i class="fas fa-check-circle"></i> Password hash and verification status</li><li><i class="fas fa-check-circle"></i> Referral and account-level information</li></ul></div>
                            <div class="terms-mini-panel"><h3>Usage &amp; Security Data</h3><ul class="terms-list"><li><i class="fas fa-check-circle"></i> IP address, device, browser, and session signals</li><li><i class="fas fa-check-circle"></i> Platform actions, task progress, and notification activity</li><li><i class="fas fa-check-circle"></i> Anti-abuse, rate-limit, and fraud review signals</li></ul></div>
                        </div>
                        <div class="terms-callout info"><i class="fas fa-user-plus"></i><div><p>We may also store optional profile details, project/review content, proof uploads, wallet addresses, RexLink pairing/session metadata, claim records, and DevHub project details when you use those features.</p></div></div>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-2">
                    <div class="terms-card-head"><div class="section-number">3</div><div><h2>How We Use Your Information</h2><p>Operational, security, and product-improvement use cases.</p></div></div>
                    <div class="terms-card-body">
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Create and manage your account</li>
                            <li><i class="fas fa-check-circle"></i> Provide reviews, projects, TaskHub, BoostHub, RexLink, DevHub, and reward features</li>
                            <li><i class="fas fa-check-circle"></i> Verify wallet-linked review eligibility and claim approvals</li>
                            <li><i class="fas fa-check-circle"></i> Improve user experience</li>
                            <li><i class="fas fa-check-circle"></i> Prevent fraud and abuse</li>
                            <li><i class="fas fa-check-circle"></i> Communicate updates and support</li>
                        </ul>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-3">
                    <div class="terms-card-head"><div class="section-number">4</div><div><h2>Cookies &amp; Tracking</h2><p>How cookies support sessions and analytics.</p></div></div>
                    <div class="terms-card-body">
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Enhance user experience</li>
                            <li><i class="fas fa-check-circle"></i> Understand platform usage and reliability</li>
                            <li><i class="fas fa-check-circle"></i> Maintain login sessions</li>
                        </ul>
                        <p>You can disable cookies through your browser settings.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up">
                    <div class="terms-card-head"><div class="section-number">5</div><div><h2>Data Sharing</h2><p>Limited sharing under strict purpose and legal boundaries.</p></div></div>
                    <div class="terms-card-body">
                        <p><strong>We do not sell your personal data.</strong></p>
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> With trusted service providers such as hosting, email, analytics, realtime, wallet/RPC, or infrastructure providers</li>
                            <li><i class="fas fa-check-circle"></i> When required by law</li>
                            <li><i class="fas fa-check-circle"></i> To protect platform security</li>
                        </ul>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-1">
                    <div class="terms-card-head"><div class="section-number">6</div><div><h2>Data Security</h2><p>Controls implemented to reduce risk and unauthorized access.</p></div></div>
                    <div class="terms-card-body">
                        <p>We implement security measures to protect your data.</p>
                        <div class="terms-callout warning"><i class="fas fa-exclamation-triangle"></i><div><p>However, no system is 100% secure, and we cannot guarantee absolute security.</p></div></div>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-2">
                    <div class="terms-card-head"><div class="section-number">7</div><div><h2>User Rights</h2><p>Your rights to access and control personal data.</p></div></div>
                    <div class="terms-card-body">
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Access your data</li>
                            <li><i class="fas fa-check-circle"></i> Request correction or deletion</li>
                            <li><i class="fas fa-check-circle"></i> Stop using the platform at any time</li>
                        </ul>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-3">
                    <div class="terms-card-head"><div class="section-number">8</div><div><h2>Third-Party Services</h2><p>External providers may have separate privacy policies.</p></div></div>
                    <div class="terms-card-body">
                        <p>CoinRex may integrate third-party services such as email delivery, hosting, analytics, wallet providers, browser wallets, blockchain RPC services, and realtime infrastructure.</p>
                        <div class="terms-callout info"><i class="fas fa-external-link-alt"></i><div><p>These services have their own privacy policies, and we are not responsible for their practices.</p></div></div>
                    </div>
                </article>

                <article class="terms-card animate-fade-up">
                    <div class="terms-card-head"><div class="section-number">9</div><div><h2>Data Retention</h2><p>Retention periods depend on account status and legal requirements.</p></div></div>
                    <div class="terms-card-body"><p>We retain your data as long as your account is active or as required for legal purposes.</p></div>
                </article>

                <article class="terms-card animate-fade-up delay-1">
                    <div class="terms-card-head"><div class="section-number">10</div><div><h2>Changes to This Policy</h2><p>Policy updates may occur as the platform evolves.</p></div></div>
                    <div class="terms-card-body"><p>We may update this Privacy Policy from time to time. Continued use of the platform indicates acceptance of the updated policy.</p></div>
                </article>

                <article class="terms-card animate-fade-up delay-2">
                    <div class="terms-card-head"><div class="section-number">11</div><div><h2>Contact Us</h2><p>Reach support for privacy-related questions.</p></div></div>
                    <div class="terms-card-body">
                        <p>If you have questions about this Privacy Policy, contact us at:</p>
                        <div class="contact-box"><i class="fas fa-envelope"></i><a href="mailto:<?php echo htmlspecialchars(SITE_EMAIL, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(SITE_EMAIL, ENT_QUOTES, 'UTF-8'); ?></a></div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="terms-cta">
        <div class="terms-container">
            <div class="terms-cta-card">
                <h2 class="animate-fade-up">Your Data, Your Trust</h2>
                <p class="animate-fade-up delay-1">By using CoinRex, you acknowledge that you have read, understood, and agree to this Privacy Policy.</p>
                <div class="terms-cta-actions animate-fade-up delay-2">
                    <a href="<?php echo BASE_URL; ?>/terms.php" class="btn btn-outline"><i class="fas fa-file-contract"></i> Terms of Service</a>
                    <a href="<?php echo BASE_URL; ?>/contact.php" class="btn btn-primary"><i class="fas fa-headset"></i> Contact Support</a>
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
});

window.addEventListener('load', () => {
    animateElements.forEach(element => {
        if (element.getBoundingClientRect().top < window.innerHeight) {
            element.classList.add('animated');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
