        </main>
    </div>
</div>
<script>
(function() {
    var menuBtn = document.getElementById('adminMobileMenuBtn');
    var overlay = document.getElementById('adminSidebarOverlay');
    var sidebar = document.getElementById('adminSidebar');
    var userMenu = document.getElementById('adminUserMenu');
    var userMenuTrigger = document.getElementById('adminUserMenuTrigger');

    function closeSidebar() {
        if (!sidebar || !overlay || !menuBtn) {
            return;
        }
        sidebar.classList.remove('open');
        overlay.classList.remove('show');
        menuBtn.classList.remove('slide');
        menuBtn.classList.remove('is-open');
        var icon = menuBtn.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-xmark');
            icon.classList.add('fa-bars');
        }
    }

    function openSidebar() {
        if (!sidebar || !overlay || !menuBtn) {
            return;
        }
        sidebar.classList.add('open');
        overlay.classList.add('show');
        menuBtn.classList.add('slide');
        menuBtn.classList.add('is-open');
        var icon = menuBtn.querySelector('i');
        if (icon) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-xmark');
        }
    }

    function closeUserMenu() {
        if (!userMenu || !userMenuTrigger) {
            return;
        }
        userMenu.classList.remove('is-open');
        userMenuTrigger.setAttribute('aria-expanded', 'false');
    }

    if (menuBtn && overlay && sidebar) {
        menuBtn.addEventListener('click', function() {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        overlay.addEventListener('click', closeSidebar);
    }

    if (userMenu && userMenuTrigger) {
        userMenuTrigger.addEventListener('click', function(event) {
            if (window.innerWidth > 992) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            var isOpen = userMenu.classList.toggle('is-open');
            userMenuTrigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', function(event) {
            if (!userMenu.contains(event.target)) {
                closeUserMenu();
            }
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            closeSidebar();
            closeUserMenu();
        }
    });

    var navGroups = document.getElementById('adminNavGroups');
    if (navGroups) {
        navGroups.querySelectorAll('[data-nav-group-toggle]').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                var group = toggle.closest('.admin-nav-group');
                if (!group) {
                    return;
                }
                var isOpen = group.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });
    }
})();
</script>
</body>
</html>
