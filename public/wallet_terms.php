<?php
/**
 * Wallet Platform - Terms of Service Page
 * Location: /coinrex/public/wallet_terms.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/wallet_header.php';

// Wallet platform specific terms info
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
                    <i class="fas fa-gavel"></i>
                    <span>Legal Agreement</span>
                </div>
                <h1 class="hero-title animate-fade-up">Terms of <span class="gradient-text">Service</span></h1>
                <p class="hero-description animate-fade-up delay-1">
                    These terms explain how RexLink should be used, what we expect from the community,
                    and how rewards, trust, and platform access are handled across the ecosystem.
                </p>
                <div class="hero-stats animate-fade-up delay-2">
                    <div class="hero-stat">
                        <span class="stat-number">8</span>
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
                    <i class="fas fa-user-shield"></i>
                    <h3>Account Integrity</h3>
                    <p>Use accurate details, protect your credentials, and stay responsible for your account activity.</p>
                </article>
                <article class="overview-card animate-fade-up delay-1">
                    <i class="fas fa-scale-balanced"></i>
                    <h3>Fair Use Only</h3>
                    <p>No spam, no bots, no fake reviews, and no reward-system abuse across RexLink features.</p>
                </article>
                <article class="overview-card animate-fade-up delay-2">
                    <i class="fas fa-coins"></i>
                    <h3>$REX Is Internal</h3>
                    <p>Rewards use the RexLink ledger, claim snapshots, and eligibility controls before any wallet approval flow.</p>
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
                    <div class="terms-card-head">
                        <div class="section-number">1</div>
                        <div>
                            <h2>Acceptance of Terms</h2>
                            <p>Using RexLink means accepting the rules that govern the platform.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>By accessing or using <strong>RexLink</strong> ("Platform", "we", "our", "us"), you agree to be bound by these Terms of Service.</p>
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
                        <p>You must be at least <strong>18 years old</strong> to use RexLink.</p>
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
                            <p>RexLink is built for real participation, not artificial engagement.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>RexLink allows users to:</p>
                        <div class="terms-dual-grid">
                            <div class="terms-mini-panel good">
                                <h3>Allowed</h3>
                                <ul class="terms-list">
                                    <li><i class="fas fa-check-circle"></i> Complete tasks</li>
                                    <li><i class="fas fa-check-circle"></i> Submit reviews</li>
                                    <li><i class="fas fa-check-circle"></i> Explore projects and submit proof-backed reviews</li>
                                    <li><i class="fas fa-check-circle"></i> Use RexLink features for wallet verification and approvals</li>
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
                            <h2>Rewards & $REX System</h2>
                            <p>The reward layer is internal to the platform and protected against misuse.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>RexLink provides an internal reward system known as <strong class="highlight">$REX</strong>.</p>
                        <div class="terms-callout info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <p>$REX is a virtual platform reward and does not guarantee real monetary value.</p>
                                <p>Rewards, calculations, and qualification rules may change over time.</p>
                                <p>Abuse or manipulation of the reward system may result in account suspension.</p>
                            </div>
                        </div>
                        <p>Claim generation, claim approval, and any on-chain reward movement may require eligibility checks, wallet pairing, and security review.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-1">
                    <div class="terms-card-head">
                        <div class="section-number">6</div>
                        <div>
                            <h2>Referral & Affiliate Program</h2>
                            <p>Referral rewards depend on real activity and may be adjusted by policy.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>Users may earn rewards through referrals and affiliate programs.</p>
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Referral rewards may require valid activity from referred users</li>
                            <li><i class="fas fa-check-circle"></i> Fraudulent referrals may lead to disqualification</li>
                            <li><i class="fas fa-check-circle"></i> RexLink reserves the right to modify referral rules at any time</li>
                        </ul>
                    </div>
                </article>

                <article class="terms-card animate-fade-up">
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
                            <li><i class="fas fa-check-circle"></i> RexLink may display, use, or remove content at its discretion</li>
                        </ul>
                        <p>We reserve the right to remove any content that violates our policies.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-2">
                    <div class="terms-card-head">
                        <div class="section-number">8</div>
                        <div>
                            <h2>Wallet, RexLink & On-Chain Features</h2>
                            <p>Wallet-linked features are provided for verification, approvals, and claim support.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>RexLink may ask you to connect or verify a wallet for review eligibility, RexLink sessions, claim approvals, or similar platform features.</p>
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> You are responsible for the wallet address and device you connect</li>
                            <li><i class="fas fa-check-circle"></i> RexLink pairing creates time-limited sessions and approval requests; it does not give RexLink your private keys or seed phrase</li>
                            <li><i class="fas fa-check-circle"></i> Blockchain network fees, failed transactions, third-party RPC issues, and wallet provider behavior are outside our direct control</li>
                        </ul>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-3">
                    <div class="terms-card-head">
                        <div class="section-number">9</div>
                        <div>
                            <h2>Account Suspension & Termination</h2>
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
            </div>
        </div>
    </section>

    <!-- ========================================
         CTA
         ======================================== -->
    <section class="terms-cta">
        <div class="wallet-container">
            <div class="terms-cta-card">
                <h2 class="animate-fade-up">Using RexLink Means Respecting the Rules</h2>
                <p class="animate-fade-up delay-1">
                    By continuing to use RexLink, you acknowledge that you have read, understood,
                    and agreed to these Terms of Service.
                </p>
                <div class="terms-cta-actions animate-fade-up delay-2">
                    <a href="<?php echo BASE_URL; ?>/wallet.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Wallet</a>
                    <a href="<?php echo BASE_URL; ?>/public/privacy.php" class="btn btn-primary"><i class="fas fa-shield-halved"></i> Privacy Policy</a>
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