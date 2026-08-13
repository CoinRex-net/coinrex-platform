<?php
/**
 * RexLink Wallet Platform — About page.
 * Location: /coinrex/wallet/about.php
 */

require_once __DIR__ . '/includes/config.php';

$page_title   = 'About RexLink — ' . WALLET_TAGLINE;
$meta_description = 'Learn about RexLink, the secure non-custodial mobile wallet built for ' . WALLET_SITE_NAME . '.';
$meta_keywords = 'about RexLink, RexLink wallet, CoinRex wallet, Web3 wallet';

$totalDl = walletDownloadCount();

require_once __DIR__ . '/includes/header.php';
?>
<main class="wallet-page">

    <section class="wallet-resource-hero">
        <div class="wallet-container">
            <span class="wallet-hero-kicker"><i class="fas fa-circle-info"></i> About RexLink</span>
            <h1>Your keys.<br><span class="wallet-gradient-text">Your control.</span></h1>
            <p class="wallet-resource-hero-lead">
                RexLink is the official mobile wallet of <?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>.
                We built it so you can approve transactions, link your wallet, and manage rewards without ever
                installing a browser extension — and without ever giving up your private keys.
            </p>
        </div>
    </section>

    <section class="wallet-section">
        <div class="wallet-container">
            <div class="wallet-section-head">
                <span class="wallet-section-kicker"><i class="fas fa-bullseye"></i> Our Mission</span>
                <h2>Web3 access without the barriers</h2>
                <p>Browser extensions are clunky and risky. RexLink makes Web3 simple, safe, and truly yours.</p>
            </div>
            <div class="wallet-features-grid">
                <article class="wallet-feature-card">
                    <span class="wallet-feature-icon"><i class="fas fa-key"></i></span>
                    <h3>Keys on your device</h3>
                    <p>Private keys never leave your phone. Secure device storage and biometric authentication keep your wallet safe.</p>
                </article>
                <article class="wallet-feature-card">
                    <span class="wallet-feature-icon"><i class="fas fa-bolt"></i></span>
                    <h3>One-tap approvals</h3>
                    <p>Every transaction is shown in plain language before you approve. No blind signing, no surprises.</p>
                </article>
                <article class="wallet-feature-card">
                    <span class="wallet-feature-icon"><i class="fas fa-link"></i></span>
                    <h3>Deep CoinRex integration</h3>
                    <p>Pair once via QR or 6-digit code, then link rewards, claims, and eligibility checks straight to your wallet.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="wallet-section">
        <div class="wallet-container">
            <div class="wallet-section-head">
                <span class="wallet-section-kicker"><i class="fas fa-heart"></i> What We Stand For</span>
                <h2>Security, honesty, ownership</h2>
            </div>
            <div class="wallet-values-grid">
                <article class="wallet-value-card">
                    <i class="fas fa-shield-halved"></i>
                    <h3>Security first</h3>
                    <p>Non-custodial architecture, signed APKs, and constant monitoring.</p>
                </article>
                <article class="wallet-value-card">
                    <i class="fas fa-scale-balanced"></i>
                    <h3>Fair rewards</h3>
                    <p>Verified reviews and honest participation earn $REX rewards.</p>
                </article>
                <article class="wallet-value-card">
                    <i class="fas fa-user-lock"></i>
                    <h3>User privacy</h3>
                    <p>Minimum data, purpose-limited use, and zero data selling.</p>
                </article>
                <article class="wallet-value-card">
                    <i class="fas fa-people-group"></i>
                    <h3>Built with the community</h3>
                    <p>Transparent rules, open feedback, and community-driven improvements.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="wallet-cta-strip">
        <div class="wallet-container">
            <div class="wallet-cta-box">
                <div>
                    <h3>Ready to own your Web3 access?</h3>
                    <p>Download RexLink and take control of your rewards today.</p>
                </div>
                <a class="wallet-btn-download" href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/download.php', ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-download"></i> Download APK
                </a>
            </div>
        </div>
    </section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
