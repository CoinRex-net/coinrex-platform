<?php
/**
 * Wallet Platform - Contact Page
 * Location: /coinrex/public/wallet_contact.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/wallet_header.php';

// Wallet platform contact info
$wallet_contact_info = [
    'support_email' => 'support@coinrex.xyz',
    'admin_email' => 'admin@coinrex.xyz',
    'platform' => 'wallet',
];
?>

<!-- Wallet Platform Specific Styles -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/contact.css">

<main class="wallet-page">

    <!-- ========================================
         HERO
         ======================================== -->
    <section class="wallet-hero" style="padding: 72px 0 56px;">
        <div class="wallet-container">
            <div class="wallet-hero-grid">
                <div class="wallet-hero-copy">
                    <span class="wallet-hero-kicker">
                        🦁 Get Support
                    </span>
                    <h1>Talk to the RexLink Team</h1>
                    <p>We're here to help with your RexLink wallet and platform questions.</p>
                    <div class="wallet-hero-actions">
                        <a href="#contact-methods" class="wallet-btn-secondary">
                            <i class="fas fa-circle-info"></i>
                            Contact Methods
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================
         CONTACT GRID
         ======================================== -->
    <section class="wallet-section">
        <div class="wallet-container">
            <div class="wallet-grid">
                <!-- Contact Panel -->
                <div class="wallet-contact-panel wallet-reveal">
                    <div class="wallet-contact-panel-header">
                        <h2>Send a message</h2>
                        <p>We'll route it to the right team.</p>
                    </div>

                    <form method="POST" action="" id="walletContactForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(appCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="wallet-form-grid">
                            <div class="wallet-form-field">
                                <label for="name">Name <span class="wallet-required">*</span></label>
                                <input id="name" type="text" name="name" placeholder="Your name" required autocomplete="name">
                            </div>
                            <div class="wallet-form-field">
                                <label for="email">Email <span class="wallet-required">*</span></label>
                                <input id="email" type="email" name="email" placeholder="you@example.com" required autocomplete="email">
                            </div>
                            <div class="wallet-form-field-full">
                                <label for="subject">Subject <span class="wallet-required">*</span></label>
                                <input id="subject" type="text" name="subject" placeholder="Brief summary" required minlength="5">
                            </div>
                            <div class="wallet-form-field-full">
                                <label for="message">Message <span class="wallet-required">*</span></label>
                                <textarea id="message" name="message" rows="6" placeholder="Include context, links, or issue details." required minlength="20"></textarea>
                            </div>
                        </div>

                        <div class="wallet-submit-row">
                            <span class="wallet-submit-note"><i class="fas fa-shield-halved"></i> Sent securely</span>
                            <button class="wallet-btn-submit" type="submit" id="walletSubmitBtn">
                                <span class="spinner"></span>
                                <span class="btn-text"><i class="fas fa-paper-plane"></i> Send</span>
                                <span class="btn-load">Sending...</span>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Sidebar -->
                <aside class="wallet-contact-sidebar wallet-reveal">
                    <div class="wallet-contact-panel">
                        <h3>Contact channels</h3>
                        <p>Verified official emails.</p>
                        <div class="wallet-contact-channels">
                            <div class="wallet-contact-channel">
                                <span class="wallet-contact-channel-icon"><i class="fas fa-headset"></i></span>
                                <div class="wallet-contact-channel-info">
                                    <strong>Support</strong>
                                    <a href="mailto:support@coinrex.xyz">support@coinrex.xyz</a>
                                </div>
                            </div>
                            <div class="wallet-contact-channel">
                                <span class="wallet-contact-channel-icon"><i class="fas fa-user-shield"></i></span>
                                <div class="wallet-contact-channel-info">
                                    <strong>Admin</strong>
                                    <a href="mailto:admin@coinrex.xyz">admin@coinrex.xyz</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wallet-contact-panel">
                        <div class="contact-meta-grid">
                            <div class="contact-meta-item">
                                <strong>Response</strong>
                                <p>Within 24 hours</p>
                            </div>
                            <div class="contact-meta-item">
                                <strong>Hours</strong>
                                <p>Mon–Fri, 9AM–6PM UTC</p>
                            </div>
                        </div>
                        <div class="wallet-contact-tip">
                            <h4><i class="fas fa-lightbulb"></i> Tip</h4>
                            <p>Clear subject + full context = faster reply.</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <!-- ========================================
         FAQ
         ======================================== -->
    <section class="wallet-section wallet-faq">
        <div class="wallet-container">
            <div class="wallet-section-head wallet-reveal">
                <span class="wallet-section-kicker"><i class="fas fa-circle-question"></i> FAQ</span>
                <h2>Frequently asked questions</h2>
            </div>
            <div class="wallet-faq-grid">
                <article class="wallet-faq-item wallet-reveal wallet-reveal-delay-1">
                    <h3><i class="fas fa-shield-halved"></i> Is the APK safe to install?</h3>
                    <p>Yes. The APK is signed by CoinRex and follows non-custodial security principles. Your private keys never leave your device.</p>
                </article>
                <article class="wallet-faq-item wallet-reveal wallet-reveal-delay-2">
                    <h3><i class="fas fa-wallet"></i> Do I need RexLink to use CoinRex?</h3>
                    <p>No. You can use CoinRex with email login. However, linking a RexLink wallet enables wallet verification and reward claims.</p>
                </article>
                <article class="wallet-faq-item wallet-reveal wallet-reveal-delay-1">
                    <h3><i class="fas fa-rotate"></i> How do I update RexLink?</h3>
                    <p>Simply download the latest APK from this page and install it over your existing version. Your wallet and data are preserved.</p>
                </article>
                <article class="wallet-faq-item wallet-reveal wallet-reveal-delay-2">
                    <h3><i class="fas fa-mobile-screen-button"></i> Which devices are supported?</h3>
                    <p>RexLink supports Android 8.0 (Oreo) and above. iOS support is planned for a future release.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ========================================
         CTA STRIP
         ======================================== -->
    <section class="wallet-cta-strip">
        <div class="wallet-container">
            <div class="wallet-cta-box wallet-reveal">
                <div>
                    <h3>Already have RexLink installed?</h3>
                    <p>Link your wallet to CoinRex and unlock rewards, claims, and approvals.</p>
                </div>
                <a href="<?php echo BASE_URL; ?>/wallet.php" class="wallet-btn-download">
                    <i class="fas fa-link"></i>
                    Back to Wallet
                </a>
            </div>
        </div>
    </section>

</main>

<script>
(function() {
    'use strict';

    // Scroll reveal
    const reveals = document.querySelectorAll('.wallet-reveal');
    if (reveals.length && 'IntersectionObserver' in window) {
        const obs = new IntersectionObserver(function(entries) {
            entries.forEach(function(e) {
                if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        reveals.forEach(function(el) { obs.observe(el); });
    } else {
        reveals.forEach(function(el) { el.classList.add('visible'); });
    }

    // FAQ accordion
    const faqs = document.querySelectorAll('.wallet-faq-item');
    faqs.forEach(function(item) {
        var q = item.querySelector('.wallet-faq-q');
        if (q) {
            q.addEventListener('click', function() {
                var open = item.classList.contains('open');
                faqs.forEach(function(f) { f.classList.remove('open'); });
                if (!open) item.classList.add('open');
            });
        }
    });

    // Submit loading
    var form = document.getElementById('walletContactForm');
    var btn = document.getElementById('walletSubmitBtn');
    if (form && btn) {
        form.addEventListener('submit', function() {
            var fields = ['name', 'email', 'subject', 'message'];
            var valid = true;
            fields.forEach(function(id) {
                var f = document.getElementById(id);
                if (f && !f.value.trim()) {
                    valid = false;
                    f.style.borderColor = 'var(--color-danger)';
                    setTimeout(function() { f.style.borderColor = ''; }, 2000);
                }
            });
            if (valid) { btn.disabled = true; btn.classList.add('loading'); }
        });
    }

    // Reset error borders on input
    document.querySelectorAll('.wallet-form input, .wallet-form select, .wallet-form textarea, .wallet-form-full input, .wallet-form-full select, .wallet-form-full textarea').forEach(function(el) {
        el.addEventListener('input', function() { this.style.borderColor = ''; });
    });

})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</html>