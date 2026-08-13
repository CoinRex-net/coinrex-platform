<?php
/**
 * RexLink Wallet Platform — Footer.
 * Location: /coinrex/wallet/includes/footer.php
 */
?>
<footer class="wallet-footer">
    <div class="wallet-footer-inner">
        <div class="wallet-footer-grid">

            <div class="wallet-footer-col wallet-footer-about">
                <div class="wallet-footer-brand">
                    <img src="<?php echo htmlspecialchars(WALLET_ASSETS_URL . '/images/logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="RexLink" onerror="this.onerror=null; this.style.display='none';">
                    <span>RexLink</span>
                </div>
                <p><?php echo htmlspecialchars(WALLET_TAGLINE, ENT_QUOTES, 'UTF-8'); ?> — the secure, non-custodial mobile wallet for <?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>. Approve transactions and manage rewards from your phone.</p>
            </div>

            <div class="wallet-footer-col">
                <h4>Platform</h4>
                <ul>
                    <li><a href="<?php echo htmlspecialchars(WALLET_MAIN_SITE_URL, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">CoinRex Home</a></li>
                    <li><a href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/index.php#how-it-works', ENT_QUOTES, 'UTF-8'); ?>">How it Works</a></li>
                    <li><a href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/index.php#faq', ENT_QUOTES, 'UTF-8'); ?>">FAQ</a></li>
                    <?php if (WALLET_DOWNLOADS_ENABLED): ?>
                        <li><a href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/download.php', ENT_QUOTES, 'UTF-8'); ?>">Download APK</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="wallet-footer-col">
                <h4>Resources</h4>
                <ul>
                    <li><a href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/about.php', ENT_QUOTES, 'UTF-8'); ?>">About RexLink</a></li>
                    <li><a href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/contact.php', ENT_QUOTES, 'UTF-8'); ?>">Contact</a></li>
                    <li><a href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/privacy.php', ENT_QUOTES, 'UTF-8'); ?>">Privacy Policy</a></li>
                    <li><a href="<?php echo htmlspecialchars(WALLET_BASE_URL . '/terms.php', ENT_QUOTES, 'UTF-8'); ?>">Terms of Service</a></li>
                </ul>
            </div>

            <div class="wallet-footer-col">
                <h4>Connect</h4>
                <ul>
                    <li><a href="mailto:<?php echo htmlspecialchars(WALLET_SUPPORT_EMAIL, ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars(WALLET_SUPPORT_EMAIL, ENT_QUOTES, 'UTF-8'); ?></a></li>
                    <li><a href="https://x.com" target="_blank" rel="noopener noreferrer"><i class="fab fa-twitter"></i> Twitter / X</a></li>
                    <li><a href="https://t.me" target="_blank" rel="noopener noreferrer"><i class="fab fa-telegram"></i> Telegram</a></li>
                </ul>
            </div>
        </div>

        <div class="wallet-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(WALLET_SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>. All rights reserved.</span>
            <span class="wallet-footer-secure"><i class="fas fa-shield-alt"></i> Non-custodial by design</span>
        </div>
    </div>
</footer>

<script src="<?php echo htmlspecialchars(WALLET_ASSETS_URL . '/js/script.js?v=' . filemtime(__DIR__ . '/../assets/js/script.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
