<?php
/**
 * CoinRex Terms of Service Page
 * Location: /coinrex/terms.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Terms Page Specific Styles -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/terms.css">

<main class="terms-main">
    <section class="terms-hero">
        <div class="terms-container">
            <div class="terms-hero-content">
                <div class="hero-badge animate-fade-up">
                    <i class="fas fa-gavel"></i>
                    <span>Legal Agreement</span>
                </div>
                <h1 class="hero-title animate-fade-up">Terms of <span class="gradient-text">Service</span></h1>
                <p class="hero-description animate-fade-up delay-1">
                    These terms explain how CoinRex should be used, what we expect from the community,
                    and how rewards, trust, and platform access are handled across the ecosystem.
                </p>
                <div class="hero-stats animate-fade-up delay-2">
                    <div class="hero-stat">
                        <span class="stat-number">11</span>
                        <span class="stat-label">Core Clauses</span>
                    </div>
                    <div class="hero-stat">
                        <span class="stat-number">18+</span>
                        <span class="stat-label">Minimum Age</span>
                    </div>
                    <div class="hero-stat">
                        <span class="stat-number">24/7</span>
                        <span class="stat-label">Platform Rules Apply</span>
                    </div>
                </div>
                <div class="terms-meta animate-fade-up delay-3">
                    <span class="terms-meta-pill">
                        <i class="fas fa-calendar-alt"></i>
                        Last Updated: <?php echo date('F j, Y'); ?>
                    </span>
                    <span class="terms-meta-pill">
                        <i class="fas fa-shield-alt"></i>
                        Trust, fairness, and responsible participation
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
                    <i class="fas fa-user-shield"></i>
                    <h3>Account Integrity</h3>
                    <p>Use accurate details, protect your credentials, and stay responsible for your account activity.</p>
                </article>
                <article class="overview-card animate-fade-up delay-1">
                    <i class="fas fa-scale-balanced"></i>
                    <h3>Fair Use Only</h3>
                    <p>No spam, no bots, no fake reviews, and no reward-system abuse across CoinRex features.</p>
                </article>
                <article class="overview-card animate-fade-up delay-2">
                    <i class="fas fa-coins"></i>
                    <h3>$REX Is Internal</h3>
                    <p>Rewards are platform-based, adjustable over time, and protected by moderation and fraud controls.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="terms-section-shell">
        <div class="terms-container">
            <div class="terms-section-list">
                <article class="terms-card animate-fade-up">
                    <div class="terms-card-head">
                        <div class="section-number">1</div>
                        <div>
                            <h2>Acceptance of Terms</h2>
                            <p>Using CoinRex means accepting the rules that govern the platform.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>By accessing or using <strong>CoinRex</strong> ("Platform", "we", "our", "us"), you agree to be bound by these Terms of Service.</p>
                        <p>If you do not agree with any part of these terms, you must not use our platform.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-1">
                    <div class="terms-card-head">
                        <div class="section-number">2</div>
                        <div>
                            <h2>Eligibility</h2>
                            <p>Participation is limited to adults who meet the minimum age requirement.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>You must be at least <strong>18 years old</strong> to use CoinRex.</p>
                        <p>By using our services, you confirm that you meet this requirement.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-2">
                    <div class="terms-card-head">
                        <div class="section-number">3</div>
                        <div>
                            <h2>Account Registration</h2>
                            <p>Your account information needs to be accurate, secure, and responsibly managed.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>To access certain features, you must create an account. You agree to:</p>
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Provide accurate and complete information</li>
                            <li><i class="fas fa-check-circle"></i> Keep your login credentials secure</li>
                            <li><i class="fas fa-check-circle"></i> Be responsible for all activities under your account</li>
                        </ul>
                        <p>We reserve the right to suspend or terminate accounts that provide false or misleading information.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-3">
                    <div class="terms-card-head">
                        <div class="section-number">4</div>
                        <div>
                            <h2>Platform Usage</h2>
                            <p>CoinRex is built for real participation, not artificial engagement.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>CoinRex allows users to:</p>
                        <div class="terms-dual-grid">
                            <div class="terms-mini-panel good">
                                <h3>Allowed</h3>
                                <ul class="terms-list">
                                    <li><i class="fas fa-check-circle"></i> Complete tasks</li>
                                    <li><i class="fas fa-check-circle"></i> Submit reviews</li>
                                    <li><i class="fas fa-check-circle"></i> Participate in voting</li>
                                    <li><i class="fas fa-check-circle"></i> Earn internal rewards ($REX)</li>
                                </ul>
                            </div>
                            <div class="terms-mini-panel bad">
                                <h3>Restricted</h3>
                                <ul class="terms-list terms-list-danger">
                                    <li><i class="fas fa-ban"></i> Fraudulent activities</li>
                                    <li><i class="fas fa-ban"></i> Spam or bot usage</li>
                                    <li><i class="fas fa-ban"></i> Fake reviews or misleading content</li>
                                    <li><i class="fas fa-ban"></i> Abuse of referral systems</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="terms-card animate-fade-up">
                    <div class="terms-card-head">
                        <div class="section-number">5</div>
                        <div>
                            <h2>Rewards &amp; $REX System</h2>
                            <p>The reward layer is internal to the platform and protected against misuse.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>CoinRex provides an internal reward system known as <strong class="highlight">$REX</strong>.</p>
                        <div class="terms-callout info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <p>$REX is a virtual platform reward and does not guarantee real monetary value.</p>
                                <p>Rewards, calculations, and qualification rules may change over time.</p>
                                <p>Abuse or manipulation of the reward system may result in account suspension.</p>
                            </div>
                        </div>
                        <p>Future tokenization of $REX is subject to platform development.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-1">
                    <div class="terms-card-head">
                        <div class="section-number">6</div>
                        <div>
                            <h2>Referral &amp; Affiliate Program</h2>
                            <p>Referral rewards depend on real activity and may be adjusted by policy.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>Users may earn rewards through referrals and affiliate programs.</p>
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Referral rewards may require valid activity from referred users</li>
                            <li><i class="fas fa-check-circle"></i> Fraudulent referrals may lead to disqualification</li>
                            <li><i class="fas fa-check-circle"></i> CoinRex reserves the right to modify referral rules at any time</li>
                        </ul>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-2">
                    <div class="terms-card-head">
                        <div class="section-number">7</div>
                        <div>
                            <h2>User-Generated Content</h2>
                            <p>Content must be truthful, lawful, and fit for public display on the platform.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>By submitting reviews or content, you agree that:</p>
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Your content is truthful and not misleading</li>
                            <li><i class="fas fa-check-circle"></i> You have the right to share such content</li>
                            <li><i class="fas fa-check-circle"></i> CoinRex may display, use, or remove content at its discretion</li>
                        </ul>
                        <p>We reserve the right to remove any content that violates our policies.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-3">
                    <div class="terms-card-head">
                        <div class="section-number">8</div>
                        <div>
                            <h2>Account Suspension &amp; Termination</h2>
                            <p>Serious policy violations can result in access restrictions or permanent removal.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>We may suspend or terminate your account if you:</p>
                        <ul class="terms-list terms-list-danger">
                            <li><i class="fas fa-ban"></i> Violate these terms</li>
                            <li><i class="fas fa-ban"></i> Engage in suspicious or abusive behavior</li>
                            <li><i class="fas fa-ban"></i> Attempt to exploit the platform</li>
                        </ul>
                        <p>Decisions regarding account suspension are final.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up">
                    <div class="terms-card-head">
                        <div class="section-number">9</div>
                        <div>
                            <h2>Limitation of Liability</h2>
                            <p>CoinRex is offered as-is, and some service risks remain outside our control.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>CoinRex is provided <strong>"as is"</strong> without warranties of any kind.</p>
                        <div class="terms-callout warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <p>We are not responsible for:</p>
                                <ul class="terms-list terms-list-plain">
                                    <li><i class="fas fa-minus"></i> Loss of earnings or rewards</li>
                                    <li><i class="fas fa-minus"></i> Platform downtime</li>
                                    <li><i class="fas fa-minus"></i> Third-party services or external links</li>
                                </ul>
                            </div>
                        </div>
                        <p>Use the platform at your own risk.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-1">
                    <div class="terms-card-head">
                        <div class="section-number">10</div>
                        <div>
                            <h2>Changes to Terms</h2>
                            <p>We may revise these rules as the platform evolves.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>We may update these Terms at any time. Continued use of the platform means you accept the updated terms.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-2">
                    <div class="terms-card-head">
                        <div class="section-number">11</div>
                        <div>
                            <h2>Contact Us</h2>
                            <p>Questions about these terms can be sent directly to the CoinRex support team.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>For any questions regarding these Terms, contact us at:</p>
                        <div class="contact-box">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?php echo htmlspecialchars(SITE_EMAIL, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars(SITE_EMAIL, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <section class="terms-cta">
        <div class="terms-container">
            <div class="terms-cta-card">
                <h2 class="animate-fade-up">Using CoinRex Means Respecting the Rules</h2>
                <p class="animate-fade-up delay-1">
                    By continuing to use CoinRex, you acknowledge that you have read, understood,
                    and agreed to these Terms of Service.
                </p>
                <div class="terms-cta-actions animate-fade-up delay-2">
                    <a href="<?php echo BASE_URL; ?>/auth/auth.php?tab=register" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Create Account
                    </a>
                    <a href="<?php echo BASE_URL; ?>/privacy.php" class="btn btn-outline">
                        <i class="fas fa-shield-halved"></i> Privacy Policy
                    </a>
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
