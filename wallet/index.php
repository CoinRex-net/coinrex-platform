<?php
/**
 * RexLink Wallet Platform — Landing page.
 * Location: /coinrex/wallet/index.php
 */

require_once __DIR__ . '/includes/config.php';

$page_title   = WALLET_NAME . ' — ' . WALLET_TAGLINE;
$meta_description = 'Download ' . WALLET_NAME . ' — the secure, non-custodial mobile wallet for ' . WALLET_SITE_NAME . '. Approve transactions, link your wallet, and manage rewards from your phone.';
$meta_keywords = 'RexLink, CoinRex wallet, crypto wallet, Web3 wallet, non-custodial wallet, Android APK, download RexLink';

$apkInfo   = walletApkInfo();
$totalDl   = walletDownloadCount();

require_once __DIR__ . '/includes/header.php';
?>
<main class="wallet-page">

    <!-- ================= HERO ================= -->
    <section class="wallet-hero">
        <div class="wallet-container">
            <div class="wallet-hero-grid">

                <div class="wallet-hero-copy wallet-reveal">
                    <span class="wallet-hero-kicker"><i class="fas fa-wallet"></i> RexLink Mobile Wallet</span>
                    <h1 class="wallet-hero-title wallet-hero-title-rotating">
                        <span class="wallet-hero-line wallet-hero-line-1">Extension-Free <span class="wallet-gradient-text">Web3 Access</span></span>
                        <span class="wallet-hero-line wallet-hero-line-2"><span class="wallet-gradient-text">CoinRex Companion</span> Mobile Wallet</span>
                    </h1>
                    <p class="wallet-hero-lead">
                        <?php echo htmlspecialchars(WALLET_NAME, ENT_QUOTES, 'UTF-8'); ?> is the secure, non-custodial mobile
                        companion for <?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>. Keep your private
                        keys on your phone, approve transactions with one tap, and link your wallet to unlock rewards —
                        no browser extension needed.
                    </p>
                    <div class="wallet-hero-actions">
                        <a class="wallet-btn-download" href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/download.php', ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fas fa-download"></i>
                            <?php echo $apkInfo['exists'] ? 'Download APK' : 'Get the App'; ?>
                        </a>
                        <a class="wallet-btn-secondary" href="#how-it-works"><i class="fas fa-arrow-down"></i> How it works</a>
                    </div>
                    <div class="wallet-hero-meta">
                        <span class="wallet-hero-meta-item"><i class="fas fa-tag"></i> v<?php echo htmlspecialchars(WALLET_APK_VERSION, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="wallet-hero-meta-item"><span class="wallet-rating-stars">★★★★★</span> 4.8</span>
                        <span class="wallet-hero-meta-item"><i class="fas fa-mobile-screen-button"></i> Android 8.0+</span>
                        <span class="wallet-hero-meta-item"><i class="fas fa-shield-halved"></i> Non-Custodial</span>
                    </div>
                </div>

                <div class="wallet-hero-visual wallet-reveal">
                    <div class="wallet-phone-mockup">
                        <div class="wallet-phone-notch"></div>
                        <div class="wallet-phone-screen">
                            <div class="wallet-phone-status-bar">
                                <span>9:41</span>
                                <span class="wallet-phone-status-icons"><i class="fas fa-signal"></i><i class="fas fa-wifi"></i><i class="fas fa-battery-full"></i></span>
                            </div>
                            <div class="wallet-phone-logo">
                                <img src="<?php echo htmlspecialchars(WALLET_ASSETS_URL . '/images/logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="RexLink" onerror="this.parentElement.innerHTML='<span class=wallet-phone-logo-fallback>🦁</span>';">
                            </div>
                            <div class="wallet-phone-title">RexLink</div>
                            <div class="wallet-phone-subtitle">Wallet Ready</div>
                            <div class="wallet-phone-balance">
                                <span class="wallet-phone-balance-label">Balance</span>
                                <strong class="wallet-phone-balance-amount">0.00 $REX</strong>
                            </div>
                            <div class="wallet-phone-divider"></div>
                            <div class="wallet-phone-feature"><i class="fas fa-check"></i> Non-custodial wallet</div>
                            <div class="wallet-phone-feature"><i class="fas fa-check"></i> One-tap approvals</div>
                            <div class="wallet-phone-feature"><i class="fas fa-check"></i> QR pairing ready</div>
                            <div class="wallet-phone-btn">Open Wallet</div>
                            <div class="wallet-phone-home-indicator"></div>
                        </div>
                    </div>
                    <div class="wallet-float-card wallet-float-card-1"><i class="fas fa-shield-halved"></i> Secure by design</div>
                    <div class="wallet-float-card wallet-float-card-2"><i class="fas fa-bolt"></i> Instant approvals</div>
                    <div class="wallet-float-card wallet-float-card-3"><i class="fas fa-link"></i> CoinRex ready</div>
                </div>

            </div>

            <!-- Stats bar -->
            <div class="wallet-stats-bar wallet-reveal">
                <div class="wallet-stat"><span class="wallet-stat-value"><?php echo number_format($totalDl); ?></span><span class="wallet-stat-label">Downloads</span></div>
                <div class="wallet-stat"><span class="wallet-stat-value">4.8<i class="fas fa-star"></i></span><span class="wallet-stat-label">Rating</span></div>
                <div class="wallet-stat"><span class="wallet-stat-value"><?php echo $apkInfo['exists'] ? $apkInfo['size_mb'] . ' MB' : '—'; ?></span><span class="wallet-stat-label">Size</span></div>
                <div class="wallet-stat"><span class="wallet-stat-value">Free</span><span class="wallet-stat-label">Price</span></div>
            </div>
        </div>
    </section>

    <!-- ================= FEATURES ================= -->
    <section class="wallet-section">
        <div class="wallet-container">
            <div class="wallet-section-head wallet-reveal">
                <span class="wallet-section-kicker"><i class="fas fa-gem"></i> Why RexLink</span>
                <h2>Built for secure, effortless Web3</h2>
                <p>RexLink keeps your keys on your device and your approvals in your control.</p>
            </div>
            <div class="wallet-features-grid">
                <article class="wallet-feature-card wallet-reveal">
                    <span class="wallet-feature-icon"><i class="fas fa-lock"></i></span>
                    <h3>Non-Custodial Security</h3>
                    <p>Your private keys never leave your phone. RexLink uses secure device storage and biometric authentication to keep your wallet safe.</p>
                </article>
                <article class="wallet-feature-card wallet-reveal wallet-reveal-delay-1">
                    <span class="wallet-feature-icon"><i class="fas fa-bolt"></i></span>
                    <h3>One-Tap Approvals</h3>
                    <p>Approve transactions, claims, and sign-in requests with a single tap. Human-readable details shown before every approval.</p>
                </article>
                <article class="wallet-feature-card wallet-reveal wallet-reveal-delay-2">
                    <span class="wallet-feature-icon"><i class="fas fa-link"></i></span>
                    <h3>Seamless CoinRex Integration</h3>
                    <p>Pair with CoinRex via QR code or 6-digit code. Link your wallet, verify eligibility, and manage rewards from your phone.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ================= HOW IT WORKS ================= -->
    <section class="wallet-section" id="how-it-works">
        <div class="wallet-container">
            <div class="wallet-section-head wallet-reveal">
                <span class="wallet-section-kicker"><i class="fas fa-route"></i> Get Started</span>
                <h2>How it works</h2>
                <p>Three simple steps to secure Web3 access.</p>
            </div>
            <div class="wallet-steps-grid">
                <article class="wallet-step-card wallet-reveal">
                    <span class="wallet-step-number">1</span>
                    <span class="wallet-step-icon">📲</span>
                    <h3>Download the APK</h3>
                    <p>Download the RexLink APK and install it on your Android device. Allow installation from unknown sources if prompted.</p>
                </article>
                <article class="wallet-step-card wallet-reveal wallet-reveal-delay-1">
                    <span class="wallet-step-number">2</span>
                    <span class="wallet-step-icon">🔐</span>
                    <h3>Create your wallet</h3>
                    <p>Set up your secure wallet with biometrics or PIN. Your private keys are generated and stored only on your device.</p>
                </article>
                <article class="wallet-step-card wallet-reveal wallet-reveal-delay-2">
                    <span class="wallet-step-number">3</span>
                    <span class="wallet-step-icon">🔗</span>
                    <h3>Pair with CoinRex</h3>
                    <p>Scan the QR code or enter the 6-digit code on CoinRex to link your wallet and unlock rewards, claims, and approvals.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ================= DOWNLOAD CTA ================= -->
    <section class="wallet-download-section">
        <div class="wallet-container">
            <div class="wallet-download-card wallet-reveal">
                <div class="wallet-download-app-icon">
                    <img src="<?php echo htmlspecialchars(WALLET_ASSETS_URL . '/images/logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="RexLink" onerror="this.style.display='none';">
                </div>
                <div class="wallet-download-info">
                    <h3>RexLink v<?php echo htmlspecialchars(WALLET_APK_VERSION, ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p>Extension-free Web3 access for Android. Non-custodial, secure, and ready for CoinRex.</p>
                    <div class="wallet-download-meta">
                        <span><i class="fas fa-tag"></i> v<?php echo htmlspecialchars(WALLET_APK_VERSION, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><i class="fas fa-mobile-screen-button"></i> Android 8.0+</span>
                        <span><i class="fas fa-database"></i> <?php echo $apkInfo['exists'] ? $apkInfo['size_mb'] . ' MB' : '—'; ?></span>
                        <span><i class="fas fa-shield-halved"></i> Signed by <?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <div class="wallet-download-actions">
                    <a class="wallet-btn-download" href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/download.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-download"></i> Download APK
                    </a>
                    <span class="wallet-download-count"><i class="fas fa-arrow-down"></i> <?php echo number_format($totalDl); ?> downloads</span>
                </div>
            </div>
            <div class="wallet-security-note wallet-reveal">
                <i class="fas fa-shield-halved"></i>
                <span>This APK is signed by <?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>. Verify the package name: <strong><?php echo htmlspecialchars(WALLET_PACKAGE_NAME, ENT_QUOTES, 'UTF-8'); ?></strong> before installing.</span>
            </div>
        </div>
    </section>

    <!-- ================= TRUST STRIPE ================= -->
    <section class="wallet-trust-stripe">
        <div class="wallet-container">
            <div class="wallet-trust-box wallet-reveal">
                <span class="wallet-trust-item"><img src="<?php echo htmlspecialchars(WALLET_ASSETS_URL . '/images/logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="RexLink"> Powered by RexLink</span>
                <span class="wallet-trust-item"><i class="fas fa-check-circle"></i> Non-Custodial</span>
                <span class="wallet-trust-item"><i class="fas fa-check-circle"></i> Secure by Design</span>
                <span class="wallet-trust-item"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> Integrated</span>
            </div>
        </div>
    </section>

    <!-- ================= FAQ ================= -->
    <section class="wallet-section" id="faq">
        <div class="wallet-container">
            <div class="wallet-section-head wallet-reveal">
                <span class="wallet-section-kicker"><i class="fas fa-circle-question"></i> FAQ</span>
                <h2>Frequently asked questions</h2>
            </div>
            <div class="wallet-faq-grid">
                <article class="wallet-faq-item wallet-reveal">
                    <h3><i class="fas fa-shield-halved"></i> Is the APK safe to install?</h3>
                    <p>Yes. The APK is signed by <?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> and follows non-custodial security principles. Your private keys never leave your device.</p>
                </article>
                <article class="wallet-faq-item wallet-reveal wallet-reveal-delay-1">
                    <h3><i class="fas fa-wallet"></i> Do I need RexLink to use <?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>?</h3>
                    <p>No. You can use <?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> with email login. However, linking a RexLink wallet enables wallet verification, reward claims, and faster eligibility checks.</p>
                </article>
                <article class="wallet-faq-item wallet-reveal">
                    <h3><i class="fas fa-rotate"></i> How do I update RexLink?</h3>
                    <p>Simply download the latest APK from this page and install it over your existing version. Your wallet and data are preserved.</p>
                </article>
                <article class="wallet-faq-item wallet-reveal wallet-reveal-delay-1">
                    <h3><i class="fas fa-mobile-screen-button"></i> Which devices are supported?</h3>
                    <p>RexLink supports Android 8.0 (Oreo) and above. iOS support is planned for a future release.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ================= CTA STRIP ================= -->
    <section class="wallet-cta-strip">
        <div class="wallet-container">
            <div class="wallet-cta-box wallet-reveal">
                <div>
                    <h3>Already have RexLink installed?</h3>
                    <p>Link your wallet to <?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?> and unlock rewards, claims, and approvals.</p>
                </div>
                <a class="wallet-btn-download" href="<?php echo htmlspecialchars(WALLET_MAIN_SITE_URL . '/public/link-wallet.php', ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-link"></i> Link Wallet
                </a>
            </div>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
