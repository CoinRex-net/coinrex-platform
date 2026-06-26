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
                <blockquote class="footer-description footer-quote">“Join thousands of crypto enthusiasts earning rewards through honest, verified reviews with blockchain proof.”</blockquote>
            </div>
            
            <!-- Platform Links -->
            <div class="footer-links">
                <h4><i class="fas fa-rocket"></i> Platform</h4>
                <a href="<?php echo BASE_URL; ?>/index.php"><i class="fas fa-home"></i> Home</a>
<?php if (featureIsVisible('projects')): ?><a href="<?php echo BASE_URL; ?>/public/projects.php"><i class="fas fa-chart-line"></i> Projects</a><?php endif; ?>
<?php if (featureIsVisible('reviews')): ?><a href="<?php echo BASE_URL; ?>/public/reviews.php"><i class="fas fa-star"></i> Reviews</a><?php endif; ?>
<?php if (featureIsVisible('devhub_full') || featureIsVisible('devhub_auth')): ?><a href="<?php echo BASE_URL; ?>/devhub/index.php"><i class="fas fa-code"></i> Dev Hub</a><?php endif; ?>
            </div>
            
            <!-- Resources Links -->
            <div class="footer-links">
                <h4><i class="fas fa-book"></i> Resources</h4>
                <a href="<?php echo BASE_URL; ?>/public/about.php"><i class="fas fa-info-circle"></i> About Us</a>
<a href="<?php echo BASE_URL; ?>/public/litepaper.php"><i class="fas fa-file-alt"></i> Litepaper</a>
<a href="<?php echo BASE_URL; ?>/public/roadmap.php"><i class="fas fa-route"></i> Roadmap</a>
<a href="<?php echo BASE_URL; ?>/public/faq.php"><i class="fas fa-question-circle"></i> FAQ</a>
                <a href="<?php echo BASE_URL; ?>/public/contact.php"><i class="fas fa-envelope"></i> Contact</a>
<a href="<?php echo BASE_URL; ?>/public/blog.php"><i class="fas fa-blog"></i> Blog</a>
            </div>
            
            <!-- Legal Links -->
            <div class="footer-links">
                <h4><i class="fas fa-gavel"></i> Legal</h4>
              <a href="<?php echo BASE_URL; ?>/public/terms.php"><i class="fas fa-file-contract"></i> Terms of Service</a>
<a href="<?php echo BASE_URL; ?>/public/privacy.php"><i class="fas fa-shield-alt"></i> Privacy Policy</a>
<a href="<?php echo BASE_URL; ?>/public/cookies.php"><i class="fas fa-cookie-bite"></i> Cookie Policy</a>
            </div>
            
        </div>
        
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> — Decentralized Trust Protocol. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Support</a>
                    <span>•</span>
                    <a href="#">Status</a>
                    <span>•</span>
                    <?php if (featureIsVisible('devhub_full')): ?><a href="<?php echo BASE_URL; ?>/devhub/widget-api.php">API</a><?php endif; ?>
                </div>
            </div>
            <p class="footer-warning">
                <i class="fas fa-shield-alt"></i> Every review requires proof (TX hash/screenshot). No bots allowed.
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
        <a href="#" class="social-fixed discord" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-discord"></i>
            <span class="social-tooltip">Discord</span>
        </a>
        <a href="#" class="social-fixed telegram" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-telegram-plane"></i>
            <span class="social-tooltip">Telegram</span>
        </a>
        <a href="#" class="social-fixed github" target="_blank" rel="noopener noreferrer">
            <i class="fab fa-github"></i>
            <span class="social-tooltip">GitHub</span>
        </a>
    </div>
    <button type="button" class="social-toggle" id="fixedSocialToggle" aria-controls="socialActions" aria-expanded="false" aria-label="Open social links">
        <i class="fas fa-share-alt"></i>
        <span class="social-tooltip">Social Links</span>
    </button>
</div>

<button type="button" class="social-fixed fixed-back-to-top" id="backToTop" aria-label="Back to top">
    <i class="fas fa-arrow-up"></i>
    <span class="social-tooltip">Back to Top</span>
</button>

<script>
// Fold/unfold fixed social links
const fixedSocial = document.getElementById('fixedSocial');
const fixedSocialToggle = document.getElementById('fixedSocialToggle');
if (fixedSocial && fixedSocialToggle) {
    fixedSocialToggle.addEventListener('click', () => {
        const isOpen = fixedSocial.classList.toggle('is-open');
        fixedSocialToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        fixedSocialToggle.setAttribute('aria-label', isOpen ? 'Close social links' : 'Open social links');
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

