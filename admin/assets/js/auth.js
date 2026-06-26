/**
 * CoinRex AdminHub — Login Page Enhancements
 * Handles loading states, remember me, password toggle, validation
 */
(function () {
    'use strict';

    const form = document.getElementById('loginForm');
    const submitBtn = document.getElementById('loginSubmitBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.getElementById('passwordToggle');
    const toggleIcon = document.getElementById('toggleIcon');
    const emailInput = document.getElementById('email');
    const rememberCheck = document.getElementById('rememberMe');
    const errorContainer = document.getElementById('errorContainer');

    // ── Auto-focus email field ──
    if (emailInput) {
        emailInput.focus();
    }

    // ── Password Toggle ──
    if (toggleBtn && passwordInput) {
        toggleBtn.addEventListener('click', function () {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            if (toggleIcon) {
                toggleIcon.className = isPassword ? 'far fa-eye-slash' : 'far fa-eye';
            }
        });
    }

    // ── Remember Me (localStorage) ──
    if (rememberCheck && emailInput) {
        // Restore saved email
        try {
            const saved = localStorage.getItem('adminhub_remember_email');
            if (saved) {
                emailInput.value = saved;
                rememberCheck.checked = true;
            }
        } catch (_) { /* ignore */ }

        // Save on submit
        form.addEventListener('submit', function () {
            try {
                if (rememberCheck.checked) {
                    localStorage.setItem('adminhub_remember_email', emailInput.value);
                } else {
                    localStorage.removeItem('adminhub_remember_email');
                }
            } catch (_) { /* ignore */ }
        });
    }

    // ── Loading State on Submit ──
    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {
            // Basic client-side validation
            const email = emailInput ? emailInput.value.trim() : '';
            const password = passwordInput ? passwordInput.value : '';

            if (!email || !password) {
                // Let the browser handle HTML5 validation
                return;
            }

            // Show loading state
            submitBtn.disabled = true;
            if (btnText) btnText.textContent = 'Signing in…';
            if (btnSpinner) btnSpinner.style.display = 'inline-block';
        });
    }

    // ── Animate error message on load ──
    if (errorContainer) {
        // Add a small delay then slide in
        requestAnimationFrame(function () {
            errorContainer.classList.add('show');
        });
    }

    // ── Keyboard shortcut hint ──
    const hintEl = document.getElementById('keyboardHint');
    if (hintEl) {
        document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Enter' && document.activeElement === emailInput) {
                // User pressed Enter on email field — hint is working
                hintEl.style.opacity = '0.5';
            }
        });
    }

})();
