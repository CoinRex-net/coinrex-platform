<?php
/**
 * CoinRex Footer Component
 * Location: /coinrex/includes/footer.php
 */
if ((string) ($_GET['th_embed'] ?? '') === '1') {
    ?>
<script src="<?php echo ASSETS_URL; ?>/js/auto-scrollbar.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/auto-scrollbar.js'); ?>"></script>
</body>
</html>
<?php
    return;
}

$footer_navigation_context = [
    'is_logged_in' => isset($_SESSION['user_id']) && $_SESSION['user_id'],
    'current_page' => basename($_SERVER['PHP_SELF'], '.php'),
];
$footer_platform_items = getManagedNavigationItems('footer', 'platform', $footer_navigation_context);
$footer_resource_items = getManagedNavigationItems('footer', 'resources', $footer_navigation_context);
$footer_legal_items = getManagedNavigationItems('footer', 'legal', $footer_navigation_context);
$footer_bottom_items = getManagedNavigationItems('footer', 'bottom', $footer_navigation_context);
?>

<!-- Footer Stylesheet -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/footer.css">

<footer class="footer">
    <div class="footer-container">
        <div class="footer-grid">

            <!-- Brand Column with Bigger Logo -->
            <div class="footer-brand">
                <div class="footer-logo-wrapper">
                    <img src="<?php echo ASSETS_URL; ?>/images/footer-logo.png" alt="CoinRex" class="footer-logo-img">
                </div>
                <div class="footer-proof-card">
                    <p class="footer-proof-text"><?php echo te('footer.proof_text'); ?></p>
                    <div class="footer-proof-pills" aria-label="<?php echo te('footer.trust_highlights'); ?>">
                        <span><i class="fas fa-shield-alt"></i> <?php echo te('footer.verified_proof'); ?></span>
                        <span><i class="fas fa-coins"></i> <?php echo te('footer.rex_rewards'); ?></span>
                        <span><i class="fas fa-users"></i> <?php echo te('footer.real_community'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Platform Links -->
            <div class="footer-links">
                <h4><i class="fas fa-rocket"></i> <?php echo te('footer.platform'); ?></h4>
                <?php foreach ($footer_platform_items as $nav_item): ?>
                    <a href="<?php echo htmlspecialchars((string) $nav_item['href'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if (trim((string) ($nav_item['icon_class'] ?? '')) !== ''): ?>
                            <i class="<?php echo htmlspecialchars((string) $nav_item['icon_class'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars((string) $nav_item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Resources Links -->
            <div class="footer-links">
                <h4><i class="fas fa-book"></i> <?php echo te('footer.resources'); ?></h4>
                <?php foreach ($footer_resource_items as $nav_item): ?>
                    <a href="<?php echo htmlspecialchars((string) $nav_item['href'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if (trim((string) ($nav_item['icon_class'] ?? '')) !== ''): ?>
                            <i class="<?php echo htmlspecialchars((string) $nav_item['icon_class'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars((string) $nav_item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Legal Links -->
            <div class="footer-links">
                <h4><i class="fas fa-gavel"></i> <?php echo te('footer.legal'); ?></h4>
                <?php foreach ($footer_legal_items as $nav_item): ?>
                    <a href="<?php echo htmlspecialchars((string) $nav_item['href'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if (trim((string) ($nav_item['icon_class'] ?? '')) !== ''): ?>
                            <i class="<?php echo htmlspecialchars((string) $nav_item['icon_class'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars((string) $nav_item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

        </div>

        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - <?php echo te('footer.copyright'); ?></p>
                <div class="footer-bottom-links">
                    <?php foreach ($footer_bottom_items as $index => $nav_item): ?>
                        <?php if ($index > 0): ?><span>&bull;</span><?php endif; ?>
                        <a href="<?php echo htmlspecialchars((string) $nav_item['href'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) $nav_item['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="footer-warning">
                <i class="fas fa-shield-alt"></i> <?php echo te('footer.warning'); ?>
            </p>
        </div>
    </div>
</footer>

<!-- Fixed Social Icons (Right Side) -->
<div class="fixed-social" id="fixedSocial">
    <div class="social-actions" id="socialActions">
        <a href="https://x.com/coinrex_app" class="social-fixed twitter" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-twitter"></i>
            <span class="social-tooltip">Twitter</span>
        </a>
        
        <a href="#" class="social-fixed telegram" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-telegram-plane"></i>
            <span class="social-tooltip">Telegram</span>
        </a>
        <a href="https://github.com/CoinRex-net/coinrex-platform" class="social-fixed github" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-github"></i>
            <span class="social-tooltip">GitHub</span>
        </a>
    </div>
    <button type="button" class="social-toggle" id="fixedSocialToggle" aria-controls="socialActions" aria-expanded="false" aria-label="<?php echo te('footer.open_social_links'); ?>">
        <i class="fas fa-share-alt"></i>
        <span class="social-tooltip"><?php echo te('footer.social_links'); ?></span>
    </button>
</div>

<button type="button" class="social-fixed fixed-back-to-top" id="backToTop" aria-label="<?php echo te('footer.back_to_top'); ?>">
    <i class="fas fa-arrow-up"></i>
    <span class="social-tooltip"><?php echo te('footer.back_to_top'); ?></span>
</button>

<script>
// Fold/unfold fixed social links
const fixedSocial = document.getElementById('fixedSocial');
const fixedSocialToggle = document.getElementById('fixedSocialToggle');
if (fixedSocial && fixedSocialToggle) {
    fixedSocialToggle.addEventListener('click', () => {
        const isOpen = fixedSocial.classList.toggle('is-open');
        fixedSocialToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        fixedSocialToggle.setAttribute('aria-label', isOpen ? <?php echo json_encode(t('footer.close_social_links'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?> : <?php echo json_encode(t('footer.open_social_links'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>);
    });
}

// Back to top functionality
const backToTop = document.getElementById('backToTop');
if (backToTop) {
    backToTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// Show/hide back to top button based on scroll
window.addEventListener('scroll', () => {
    if (!backToTop) {
        return;
    }

    if (window.scrollY > 500) {
        backToTop.style.opacity = '1';
        backToTop.style.visibility = 'visible';
    } else {
        backToTop.style.opacity = '0';
        backToTop.style.visibility = 'hidden';
    }
});
</script>

<script src="<?php echo ASSETS_URL; ?>/js/auto-scrollbar.js?v=<?php echo (int) @filemtime(dirname(__DIR__) . '/assets/js/auto-scrollbar.js'); ?>"></script>
</body>
