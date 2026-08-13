<?php
/**
 * Wallet Platform - About Us Page
 * Location: /coinrex/public/wallet_about.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/wallet_header.php';

// Wallet platform specific stats
$db = getDBConnection();

$wallet_total_downloads = 0;
$wallet_apk_exists = false;
$wallet_apk_path = ASSETS_PATH . '/downloads/RexLink.apk';
$wallet_apk_size_mb = 0;

if (is_file($wallet_apk_path)) {
    $wallet_apk_exists = true;
    $wallet_apk_size_bytes = (int) filesize($wallet_apk_path);
    $wallet_apk_size_mb = round($wallet_apk_size_bytes / (1024 * 1024), 1);
}

try {
    $db = getDBConnection();
    $count_stmt = $db->query("SELECT COUNT(*) FROM rexlink_downloads");
    $wallet_total_downloads = (int) ($count_stmt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $wallet_total_downloads = 0;
}
?>

<!-- Wallet Platform Specific Styles -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/wallet.css">

<main class="wallet-page">

    <!-- ========================================
         HERO — Split Layout
         ======================================== -->
    <section class="wallet-hero">
        <div class="wallet-container">
            <div class="wallet-hero-grid">

                <!-- Left: Copy -->
                <div class="wallet-hero-copy">
                    <span class="wallet-hero-kicker">
                        🦁 RexLink Mobile Wallet
                    </span>
                    <h1>
                        Extension Free<br>
                        <span class="wallet-gradient-text">Web3 Access</span>
                    </h1>
                    <p class="wallet-hero-lead">
                        RexLink is the secure, non-custodial mobile companion for CoinRex.
                        Keep your private keys on your phone, approve transactions with one tap,
                        and link your wallet to unlock rewards — no browser extension needed.
                    </p>
                    <div class="wallet-hero-actions">
                        <?php if ($wallet_apk_exists): ?>
                            <a href="<?php echo BASE_URL; ?>/api/rexlink_download.php" class="wallet-btn-download" id="walletDownloadBtn">
                                <i class="fas fa-download"></i>
                                Download APK
                            </a>
                        <?php else: ?>
                            <button type="button" class="wallet-btn-download is-disabled" disabled>
                                <i class="fas fa-hourglass-half"></i>
                                Coming Soon
                            </button>
                        <?php endif; ?>
                        <a href="#how-it-works" class="wallet-btn-secondary">
                            <i class="fas fa-circle-info"></i>
                            How it works
                        </a>
                    </div>
                    <div class="wallet-hero-meta">
                        <span class="wallet-hero-meta-item">
                            <i class="fas fa-tag"></i> v1.0.0
                        </span>
                        <span class="wallet-hero-meta-item">
                            <span class="wallet-rating-stars">★★★★★</span> 4.8
                        </span>
                        <span class="wallet-hero-meta-item">
                            <i class="fas fa-mobile-screen-button"></i> Android 8.0+
                        </span>
                        <span class="wallet-hero-meta-item">
                            <i class="fas fa-shield-halved"></i> Non-Custodial
                        </span>
                    </div>
                </div>

                <!-- Right: Phone Mockup -->
                <div class="wallet-hero-visual">
                    <div class="wallet-phone-mockup">
                        <div class="wallet-phone-notch"></div>
                        <div class="wallet-phone-screen">
                            <div class="wallet-phone-status-bar">
                                <span class="wallet-phone-time">9:41</span>
                                <span class="wallet-phone-status-icons">
                                    <i class="fas fa-signal"></i>
                                    <i class="fas fa-wifi"></i>
                                    <i class="fas fa-battery-full"></i>
                                </span>
                            </div>
                            <div class="wallet-phone-logo">
                                <img src="<?php echo ASSETS_URL; ?>/images/shield-logo.png" alt="RexLink" onerror="this.onerror=null; this.parentElement.innerHTML='<span style=\'font-size:2rem;\'>🦁</span>';">
                            </div>
                            <div class="wallet-phone-title">RexLink</div>
                            <div class="wallet-phone-subtitle">Wallet Ready</div>
                            <div class="wallet-phone-balance">
                                <span class="wallet-phone-balance-label">Balance</span>
                                <strong class="wallet-phone-balance-amount">0.00 $REX</strong>
                            </div>
                            <div class="wallet-phone-divider"></div>
                            <div class="wallet-phone-feature">
                                <i class="fas fa-check-circle"></i>
                                <span>Non-custodial wallet</span>
                            </div>
                            <div class="wallet-phone-feature">
                                <i class="fas fa-check-circle"></i>
                                <span>One-tap approvals</span>
                            </div>
                            <div class="wallet-phone-feature">
                                <i class="fas fa-check-circle"></i>
                                <span>QR pairing ready</span>
                            </div>
                            <div class="wallet-phone-btn">Open Wallet</div>
                            <div class="wallet-phone-home-indicator"></div>
                        </div>
                    </div>

                    <!-- Floating cards -->
                    <div class="wallet-float-card wallet-float-card-1">
                        <i class="fas fa-shield-halved"></i>
                        <span>Secure by design</span>
                    </div>
                    <div class="wallet-float-card wallet-float-card-2">
                        <i class="fas fa-bolt"></i>
                        <span>Instant approvals</span>
                    </div>
                    <div class="wallet-float-card wallet-float-card-3">
                        <i class="fas fa-link"></i>
                        <span>CoinRex ready</span>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ========================================
         STATS BAR — Play Store Style
         ======================================== -->
    <div class="wallet-container">
        <div class="wallet-stats-bar wallet-reveal">
            <div class="wallet-stat">
                <span class="wallet-stat-value"><?php echo number_format($wallet_total_downloads); ?></span>
                <span class="wallet-stat-label">Downloads</span>
            </div>
            <div class="wallet-stat">
                <span class="wallet-stat-value"><i class="fas fa-star"></i>4.8</span>
                <span class="wallet-stat-label">Rating</span>
            </div>
            <div class="wallet-stat">
                <span class="wallet-stat-value"><?php echo $wallet_apk_exists ? $wallet_apk_size_mb . ' MB' : '—'; ?></span>
                <span class="wallet-stat-label">Size</span>
            </div>
            <div class="wallet-stat">
                <span class="wallet-stat-value">Free</span>
                <span class="wallet-stat-label">Price</span>
            </div>
        </div>
    </div>

    <!-- ========================================
         FEATURES
         ======================================== -->
    <section class="wallet-section">
        <div class="wallet-container">
            <div class="wallet-section-head wallet-reveal">
                <span class="wallet-section-kicker"><i class="fas fa-gem"></i> Why RexLink</span>
                <h2>Built for secure, effortless Web3</h2>
                <p>RexLink keeps your keys on your device and your approvals in your control.</p>
            </div>
            <div class="wallet-features-grid">
                <article class="wallet-feature-card wallet-reveal wallet-reveal-delay-1">
                    <span class="wallet-feature-icon"><i class="fas fa-lock"></i></span>
                    <h3>Non-Custodial Security</h3>
                    <p>Your private keys never leave your phone. RexLink uses secure device storage and biometric authentication to keep your wallet safe.</p>
                </article>
                <article class="wallet-feature-card wallet-reveal wallet-reveal-delay-2">
                    <span class="wallet-feature-icon"><i class="fas fa-bolt"></i></span>
                    <h3>One-Tap Approvals</h3>
                    <p>Approve transactions, claims, and sign-in requests with a single tap. Human-readable details shown before every approval.</p>
                </article>
                <article class="wallet-feature-card wallet-reveal wallet-reveal-delay-3">
                    <span class="wallet-feature-icon"><i class="fas fa-link"></i></span>
                    <h3>Seamless CoinRex Integration</h3>
                    <p>Pair with CoinRex via QR code or 6-digit code. Link your wallet, verify eligibility, and manage rewards from your phone.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ========================================
         HOW IT WORKS
         ======================================== -->
    <section class="wallet-section" id="how-it-works">
        <div class="wallet-container">
            <div class="wallet-section-head wallet-reveal">
                <span class="wallet-section-kicker"><i class="fas fa-route"></i> Get Started</span>
                <h2>How it works</h2>
                <p>Three simple steps to secure Web3 access.</p>
            </div>
            <div class="wallet-steps-grid">
                <article class="wallet-step-card wallet-reveal wallet-reveal-delay-1">
                    <span class="wallet-step-number">1</span>
                    <span class="wallet-step-icon">📲</span>
                    <h3>Download the APK</h3>
                    <p>Download RexLink APK and install it on your Android device. Allow installation from unknown sources if prompted.</p>
                </article>
                <article class="wallet-step-card wallet-reveal wallet-reveal-delay-2">
                    <span class="wallet-step-number">2</span>
                    <span class="wallet-step-icon">🔐</span>
                    <h3>Create your wallet</h3>
                    <p>Set up your secure wallet with biometrics or PIN. Your private keys are generated and stored only on your device.</p>
                </article>
                <article class="wallet-step-card wallet-reveal wallet-reveal-delay-3">
                    <span class="wallet-step-number">3</span>
                    <span class="wallet-step-icon">🔗</span>
                    <h3>Pair with CoinRex</h3>
                    <p>Scan the QR code or enter the 6-digit code on CoinRex to link your wallet and unlock rewards, claims, and approvals.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ========================================
         DOWNLOAD CARD — Main CTA
         ======================================== -->
    <section class="wallet-download-section">
        <div class="wallet-container">
            <div class="wallet-download-card wallet-reveal">
                <div class="wallet-download-app-icon">
                    <img src="<?php echo ASSETS_URL; ?>/images/shield-logo.png" alt="RexLink">
                </div>
                <div class="wallet-download-info">
                    <h3>RexLink v1.0.0</h3>
                    <p>Extension-free Web3 access for Android. Non-custodial, secure, and ready for CoinRex.</p>
                    <div class="wallet-download-meta">
                        <span><i class="fas fa-tag"></i> v1.0.0</span>
                        <span><i class="fas fa-mobile-screen-button"></i> Android 8.0+</span>
                        <span><i class="fas fa-database"></i> <?php echo $wallet_apk_exists ? $wallet_apk_size_mb . ' MB' : '—'; ?></span>
                        <span><i class="fas fa-shield-halved"></i> Signed by CoinRex</span>
                    </div>
                </div>
                <div class="wallet-download-actions">
                    <?php if ($wallet_apk_exists): ?>
                        <a href="<?php echo BASE_URL; ?>/api/rexlink_download.php" class="wallet-btn-download" id="walletDownloadBtn2">
                            <i class="fas fa-download"></i>
                            Download APK
                        </a>
                    <?php else: ?>
                        <button type="button" class="wallet-btn-download is-disabled" disabled>
                            <i class="fas fa-hourglass-half"></i>
                            Coming Soon
                        </button>
                    <?php endif; ?>
                    <span class="wallet-download-count">
                        <i class="fas fa-arrow-down"></i>
                        <?php echo number_format($wallet_total_downloads); ?> downloads
                    </span>
                </div>
            </div>

            <div class="wallet-security-note wallet-reveal">
                <i class="fas fa-shield-halved"></i>
                <span>This APK is signed by CoinRex. Verify the package name: <strong>com.coinrex.rexlink</strong> before installing.</span>
            </div>
        </div>
    </section>

    <!-- ========================================
         TRUST STRIPE — Above FAQ
         ======================================== -->
    <section class="wallet-trust-stripe">
        <div class="wallet-container">
            <div class="wallet-trust-box wallet-reveal">
                <span class="wallet-trust-item">
                    <img src="<?php echo ASSETS_URL; ?>/images/shield-logo.png" alt="RexLink">
                    Powered by RexLink
                </span>
                <span class="wallet-trust-item">
                    <i class="fas fa-check-circle"></i>
                    Non-Custodial
                </span>
                <span class="wallet-trust-item">
                    <i class="fas fa-check-circle"></i>
                    Secure by Design
                </span>
                <span class="wallet-trust-item">
                    <i class="fas fa-check-circle"></i>
                    CoinRex Integrated
                </span>
            </div>
        </div>
    </section>

    <!-- ========================================
         FAQ
         ======================================== -->
    <section class="wallet-section">
        <div class="wallet-container">
            <div class="wallet-section-head wallet-reveal">
                <span class="wallet-section-kicker"><i class="fas fa-circle-question"></i> FAQ</span>
                <h2>Frequently asked questions</h2>
            </div>
            <div class="wallet-faq-grid">
                <article class="wallet-faq-item wallet-reveal wallet-reveal-delay-1">
                    <h3><i class="fas fa-shield-halved"></i> Is the APK safe to install?</h3>
                    <p>Yes. The APK is signed by CoinRex and follows non-custodial security principles. Your private keys never leave your device, and all sensitive data is stored using secure device storage.</p>
                </article>
                <article class="wallet-faq-item wallet-reveal wallet-reveal-delay-2">
                    <h3><i class="fas fa-wallet"></i> Do I need RexLink to use CoinRex?</h3>
                    <p>No. You can use CoinRex with email login. However, linking a RexLink wallet enables wallet verification, reward claims, and faster review eligibility checks.</p>
                </article>
                <article class="wallet-faq-item wallet-reveal wallet-reveal-delay-1">
                    <h3><i class="fas fa-rotate"></i> How do I update RexLink?</h3>
                    <p>Simply download the latest APK from this page and install it over your existing version. Your wallet and data are preserved during updates.</p>
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
                <a href="<?php echo BASE_URL; ?>/public/link-wallet.php" class="wallet-btn-download">
                    <i class="fas fa-link"></i>
                    Link Wallet
                </a>
            </div>
        </div>
    </section>

</main>

<script>
(function() {
    // Scroll reveal animations
    const revealElements = document.querySelectorAll('.wallet-reveal');
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        revealElements.forEach(function(el) {
            revealObserver.observe(el);
        });
    } else {
        revealElements.forEach(function(el) {
            el.classList.add('is-visible');
        });
    }

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (!href || href === '#') return;
            const target = document.querySelector(href);
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
</html>