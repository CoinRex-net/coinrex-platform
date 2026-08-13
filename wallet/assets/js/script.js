/* RexLink Wallet Platform — interactions
   Location: /coinrex/wallet/assets/js/script.js */
(function () {
    'use strict';

    /* Mobile navigation toggle */
    var navToggle = document.getElementById('walletNavToggle');
    var navLinks = document.getElementById('walletNavLinks');
    if (navToggle && navLinks) {
        var navIconOpen = navToggle.querySelector('.wallet-nav-toggle-icon-open');
        var navIconClose = navToggle.querySelector('.wallet-nav-toggle-icon-close');

        function setNavOpen(isOpen) {
            navLinks.classList.toggle('is-open', isOpen);
            navToggle.classList.toggle('is-open', isOpen);
            navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (navIconOpen && navIconClose) {
                navIconOpen.style.display = isOpen ? 'none' : '';
                navIconClose.style.display = isOpen ? '' : 'none';
            }
        }

        navToggle.addEventListener('click', function () {
            setNavOpen(!navLinks.classList.contains('is-open'));
        });
        navLinks.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                setNavOpen(false);
            });
        });
    }

    /* Hero tagline rotation (one line visible at a time) */
    var heroLines = document.querySelectorAll('.wallet-hero-title-rotating .wallet-hero-line');
    if (heroLines.length > 1) {
        var current = 0;
        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function showLine(index) {
            heroLines.forEach(function (line, i) {
                line.classList.toggle('is-current', i === index);
            });
        }

        showLine(0);

        if (!reducedMotion) {
            setInterval(function () {
                current = (current + 1) % heroLines.length;
                showLine(current);
            }, 5000);
        }
    }

    /* Scroll-reveal */
    var revealEls = document.querySelectorAll('.wallet-reveal');
    if ('IntersectionObserver' in window && revealEls.length) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach(function (el) { observer.observe(el); });
    } else {
        revealEls.forEach(function (el) { el.classList.add('is-visible'); });
    }

    /* Footer year is server-rendered; nothing else needed. */
})();
