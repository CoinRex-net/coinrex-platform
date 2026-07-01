<?php
/**
 * CoinRex Cookie Policy Page
 * Location: /coinrex/public/cookies.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/terms.css">
<style>
/* Prevent nested/duplicate scroll behavior on this legal page */
html, body {
    overflow-x: hidden;
}

body {
    overflow-y: auto !important;
}

.terms-main,
.terms-section-shell {
    overflow: visible !important;
}
</style>

<main class="terms-main">
    <section class="terms-hero">
        <div class="terms-container">
            <div class="terms-hero-content">
                <div class="hero-badge animate-fade-up">
                    <i class="fas fa-cookie-bite"></i>
                    <span>Transparency & Consent</span>
                </div>
                <h1 class="hero-title animate-fade-up">Cookie <span class="gradient-text">Policy</span></h1>
                <p class="hero-description animate-fade-up delay-1">
                    This Cookie Policy explains how CoinRex uses cookies, sessions, and similar technologies
                    to keep authentication secure, protect forms, support RexLink flows, and improve platform reliability.
                </p>
                <div class="terms-meta animate-fade-up delay-2">
                    <span class="terms-meta-pill"><i class="fas fa-calendar-alt"></i> Last Updated: <?php echo date('F j, Y'); ?></span>
                    <span class="terms-meta-pill"><i class="fas fa-shield-alt"></i> Sessions, security, and user control</span>
                </div>
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
                            <h2>What Are Cookies?</h2>
                            <p>Small text files stored in your browser to remember settings and activity.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>Cookies are small data files placed on your device when you visit a website. They help sites remember your login state, remember-me choices, preferences, and security context.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-1">
                    <div class="terms-card-head">
                        <div class="section-number">2</div>
                        <div>
                            <h2>How CoinRex Uses Cookies</h2>
                            <p>Cookies are used to provide core platform functionality and security.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <ul class="terms-list">
                            <li><i class="fas fa-check-circle"></i> Maintain secure login sessions</li>
                            <li><i class="fas fa-check-circle"></i> Protect forms with CSRF/session controls</li>
                            <li><i class="fas fa-check-circle"></i> Support remember-me login, RexLink pairing status, and claim approval flows</li>
                            <li><i class="fas fa-check-circle"></i> Remember user preferences where applicable</li>
                            <li><i class="fas fa-check-circle"></i> Improve reliability and performance diagnostics</li>
                        </ul>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-2">
                    <div class="terms-card-head">
                        <div class="section-number">3</div>
                        <div>
                            <h2>Types of Cookies We Use</h2>
                            <p>We classify cookies by purpose and duration.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <div class="terms-dual-grid">
                            <div class="terms-mini-panel good">
                                <h3><i class="fas fa-lock"></i> Essential Cookies</h3>
                                <p>Required for login, CSRF protection, remember-me behavior, RexLink session checks, and core site operation. Without these, core features may not work.</p>
                            </div>
                            <div class="terms-mini-panel good">
                                <h3><i class="fas fa-sliders"></i> Functional Cookies</h3>
                                <p>Help remember choices such as lightweight UI preferences for a smoother experience.</p>
                            </div>
                        </div>
                        <div class="terms-dual-grid" style="margin-top:12px;">
                            <div class="terms-mini-panel good">
                                <h3><i class="fas fa-chart-line"></i> Analytics Cookies</h3>
                                <p>May be used in aggregate form to understand usage patterns, reliability, and platform quality.</p>
                            </div>
                            <div class="terms-mini-panel bad">
                                <h3><i class="fas fa-clock"></i> Session vs Persistent</h3>
                                <p>Session cookies expire after you close your browser. Persistent cookies may remain for a limited period.</p>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="terms-card animate-fade-up">
                    <div class="terms-card-head">
                        <div class="section-number">4</div>
                        <div>
                            <h2>Third-Party Cookies</h2>
                            <p>Some integrated services may set their own cookies.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>When CoinRex integrates third-party providers such as infrastructure, analytics, email, wallet providers, browser wallets, blockchain RPC services, or embedded services, those services may set cookies or local storage under their own policies.</p>
                        <div class="terms-callout info">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <p>We do not control third-party cookie behavior outside our domain. Please review their privacy/cookie policies separately.</p>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-1">
                    <div class="terms-card-head">
                        <div class="section-number">5</div>
                        <div>
                            <h2>Managing Cookies</h2>
                            <p>You can control or disable cookies from your browser settings.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>You can usually manage cookie settings in your browser under Privacy/Security preferences. Disabling essential cookies may affect login and security features on CoinRex.</p>
                        <ul class="terms-list terms-list-danger">
                            <li><i class="fas fa-ban"></i> Blocking all cookies may break authentication</li>
                            <li><i class="fas fa-ban"></i> Some features may stop working as expected</li>
                        </ul>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-2">
                    <div class="terms-card-head">
                        <div class="section-number">6</div>
                        <div>
                            <h2>Policy Updates</h2>
                            <p>This policy may be updated when platform features or legal requirements change.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
                        <p>We may revise this Cookie Policy periodically. Updated versions will be posted on this page with a refreshed "Last Updated" date.</p>
                    </div>
                </article>

                <article class="terms-card animate-fade-up delay-3">
                    <div class="terms-card-head">
                        <div class="section-number">7</div>
                        <div>
                            <h2>Contact</h2>
                            <p>Questions about cookie usage? Reach out to our support team.</p>
                        </div>
                    </div>
                    <div class="terms-card-body">
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
</main>

<script>
const animateElements = document.querySelectorAll('.animate-fade-up');
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animated');
        }
    });
}, { threshold: 0.15, rootMargin: '0px 0px -30px 0px' });
animateElements.forEach((el) => observer.observe(el));
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
