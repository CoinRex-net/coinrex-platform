<?php
/**
 * CoinRex DevHub Footer
 * Location: /coinrex/devhub/includes/footer.php
 */
?>

        <!-- Footer CSS -->
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>/devhub/assets/css/footer.css">
        
        <!-- Footer -->
        <footer class="devhub-footer">
            <div class="footer-container">
                <div class="footer-brand">
                    <img src="<?php echo BASE_URL; ?>/assets/images/favicon.png" alt="CoinRex" class="footer-logo">
                    <div class="footer-brand-text">
                        <h4>DevHub</h4>
                        <span>by CoinRex</span>
                    </div>
                </div>
                <div class="footer-links">
                    <a href="<?php echo BASE_URL; ?>/index.php" target="_blank">
                        <i class="fas fa-home"></i> Main Site
                    </a>
                    <a href="<?php echo BASE_URL; ?>/devhub/widget-api.php">
                        <i class="fas fa-plug"></i> Widget API
                    </a>
                    <a href="<?php echo BASE_URL; ?>/contact.php" target="_blank">
                        <i class="fas fa-headset"></i> Support
                    </a>
                    <a href="#" id="backToTop">
                        <i class="fas fa-arrow-up"></i> Back to Top
                    </a>
                </div>
                <div class="footer-copyright">
                    <i class="fas fa-code"></i>
                    <span>&copy; <?php echo date('Y'); ?> DevHub by CoinRex. All rights reserved.</span>
                </div>
            </div>
        </footer>
        </main> <!-- End of devhub-main -->
    </div> <!-- Close devhub-app-wrapper -->
    
    <!-- Back to Top Script -->
    <script>
        document.getElementById('backToTop')?.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    </script>
    
    <script>
    (function() {
        'use strict';
        
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.querySelector('.devhub-sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (!mobileMenuBtn || !sidebar || !overlay) return;
        
        function isMobile() {
            return window.innerWidth <= 992;
        }
        
        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('show');
            mobileMenuBtn.classList.add('is-open');
            mobileMenuBtn.classList.add('slide');
            const icon = mobileMenuBtn.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            }
        }
        
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
            mobileMenuBtn.classList.remove('is-open');
            mobileMenuBtn.classList.remove('slide');
            const icon = mobileMenuBtn.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }
        
        function toggleSidebar() {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }
        
        // Toggle button click
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleSidebar();
        });
        
        // Overlay click to close
        overlay.addEventListener('click', function() {
            closeSidebar();
        });
        
        // Close sidebar when clicking menu links on mobile
        const navLinks = sidebar.querySelectorAll('.dh-menu');
        navLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                if (isMobile()) {
                    closeSidebar();
                }
            });
        });
        
        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (!isMobile() && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
    })();
</script>

<script>
(function () {
    var toggle = document.getElementById('devNotificationsToggle');
    var dropdown = document.getElementById('devNotificationsDropdown');
    var markAllBtn = document.getElementById('devMarkAllNotificationsRead');
    var badge = document.getElementById('devNotificationBadge');

    if (!toggle || !dropdown) return;

    var markAllInBackground = function (reloadAfter) {
        var body = new URLSearchParams();
        body.set('recipient_type', 'developer');
        return fetch('<?php echo BASE_URL; ?>/api/mark_all_notifications_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            credentials: 'same-origin'
        }).then(function () {
            if (badge && badge.parentNode) {
                badge.parentNode.removeChild(badge);
            }
            dropdown.querySelectorAll('.devhub-notification-link.is-unread').forEach(function (link) {
                link.classList.remove('is-unread');
            });
            if (reloadAfter) {
                window.location.reload();
            }
        });
    };

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        var willOpen = !dropdown.classList.contains('active');
        dropdown.classList.toggle('active');
        toggle.setAttribute('aria-expanded', dropdown.classList.contains('active') ? 'true' : 'false');

        if (willOpen) {
            markAllInBackground(false).catch(function () {});
        }
    });

    dropdown.querySelectorAll('.devhub-notification-link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (badge && badge.parentNode) {
                badge.parentNode.removeChild(badge);
            }
        });
    });

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function (e) {
            e.preventDefault();
            markAllInBackground(true).catch(function () {});
        });
    }

    document.addEventListener('click', function () {
        dropdown.classList.remove('active');
        toggle.setAttribute('aria-expanded', 'false');
    });

    dropdown.addEventListener('click', function (e) {
        e.stopPropagation();
    });
})();
</script>

<script>
(function () {
    var profileToggle = document.getElementById('devProfileToggle');
    var profileDropdown = document.getElementById('devProfileDropdown');
    var notificationToggle = document.getElementById('devNotificationsToggle');
    var notificationDropdown = document.getElementById('devNotificationsDropdown');

    if (!profileToggle || !profileDropdown) {
        return;
    }

    profileToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = profileDropdown.classList.contains('active');

        profileDropdown.classList.toggle('active');
        profileToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');

        if (!isOpen && notificationDropdown && notificationToggle) {
            notificationDropdown.classList.remove('active');
            notificationToggle.setAttribute('aria-expanded', 'false');
        }
    });

    profileDropdown.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    document.addEventListener('click', function () {
        profileDropdown.classList.remove('active');
        profileToggle.setAttribute('aria-expanded', 'false');
    });
})();
</script>
</body>
</html>