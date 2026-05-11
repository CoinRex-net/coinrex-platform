<?php
/**
 * CoinRex Footer Component
 * Location: /coinrex/includes/footer.php
 */
?>

<!-- Footer Stylesheet -->
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/footer.css">

<footer class="footer">
    <div class="footer-container">
        <div class="footer-grid">
            
            <!-- Brand Column with Bigger Logo -->
            <div class="footer-brand">
                <div class="footer-logo-wrapper">
                    <img src="<?php echo ASSETS_URL; ?>/images/logo.png" alt="CoinRex" class="footer-logo-img">
                    <p class="footer-slogan"><?php echo SITE_TAGLINE; ?></p>
                </div>
                <blockquote class="footer-description footer-quote">“Join thousands of crypto enthusiasts earning rewards through honest, verified reviews with blockchain proof.”</blockquote>
            </div>
            
            <!-- Platform Links -->
            <div class="footer-links">
                <h4><i class="fas fa-rocket"></i> Platform</h4>
                <a href="<?php echo BASE_URL; ?>/index.php"><i class="fas fa-home"></i> Home</a>
<a href="<?php echo BASE_URL; ?>/projects.php"><i class="fas fa-chart-line"></i> Projects</a>
<a href="<?php echo BASE_URL; ?>/reviews.php"><i class="fas fa-star"></i> Reviews</a>
<a href="<?php echo BASE_URL; ?>/developers.php"><i class="fas fa-code"></i> Dev Hub</a>
<a href="<?php echo BASE_URL; ?>/leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>
            </div>
            
            <!-- Resources Links -->
            <div class="footer-links">
                <h4><i class="fas fa-book"></i> Resources</h4>
                <a href="<?php echo BASE_URL; ?>/about.php"><i class="fas fa-info-circle"></i> About Us</a>
<a href="<?php echo BASE_URL; ?>/faq.php"><i class="fas fa-question-circle"></i> FAQ</a>
<a href="<?php echo BASE_URL; ?>/contact.php"><i class="fas fa-envelope"></i> Contact</a>
<a href="<?php echo BASE_URL; ?>/blog.php"><i class="fas fa-blog"></i> Blog</a>
<a href="<?php echo BASE_URL; ?>/roadmap.php"><i class="fas fa-map"></i> Roadmap</a>
            </div>
            
            <!-- Legal Links -->
            <div class="footer-links">
                <h4><i class="fas fa-gavel"></i> Legal</h4>
              <a href="<?php echo BASE_URL; ?>/terms.php"><i class="fas fa-file-contract"></i> Terms of Service</a>
<a href="<?php echo BASE_URL; ?>/privacy.php"><i class="fas fa-shield-alt"></i> Privacy Policy</a>
<a href="<?php echo BASE_URL; ?>/cookies.php"><i class="fas fa-cookie-bite"></i> Cookie Policy</a>
<a href="<?php echo BASE_URL; ?>/report.php"><i class="fas fa-flag"></i> Report Scam</a>            </div>
            
        </div>
        
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> — Decentralized Trust Protocol. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Support</a>
                    <span>•</span>
                    <a href="#">Status</a>
                    <span>•</span>
                    <a href="<?php echo BASE_URL; ?>/devhub/widget-api.php">API</a>
                </div>
            </div>
            <p class="footer-warning">
                <i class="fas fa-shield-alt"></i> Every review requires proof (TX hash/screenshot). No bots allowed.
            </p>
        </div>
    </div>
</footer>

<!-- Fixed Social Icons (Right Side) -->
<div class="fixed-social">
    <a href="#" class="social-fixed twitter" target="_blank">
        <i class="fab fa-twitter"></i>
        <span class="social-tooltip">Twitter</span>
    </a>
    <a href="#" class="social-fixed discord" target="_blank">
        <i class="fab fa-discord"></i>
        <span class="social-tooltip">Discord</span>
    </a>
    <a href="#" class="social-fixed telegram" target="_blank">
        <i class="fab fa-telegram-plane"></i>
        <span class="social-tooltip">Telegram</span>
    </a>
    <a href="#" class="social-fixed github" target="_blank">
        <i class="fab fa-github"></i>
        <span class="social-tooltip">GitHub</span>
    </a>
    <div class="social-divider"></div>
    <div class="social-fixed back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
        <span class="social-tooltip">Back to Top</span>
    </div>
</div>

<script>
// Back to top functionality
const backToTop = document.getElementById('backToTop');
if (backToTop) {
    backToTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// Show/hide back to top button based on scroll
window.addEventListener('scroll', () => {
    if (window.scrollY > 500) {
        backToTop.style.opacity = '1';
        backToTop.style.visibility = 'visible';
    } else {
        backToTop.style.opacity = '0';
        backToTop.style.visibility = 'hidden';
    }
});
</script>

</body>
</html>