/**
 * Admin Toast Notification System
 * Shared across all admin pages.
 * Usage:
 *   showToast('Project approved successfully!', 'success');
 *   showToast('Something went wrong.', 'error');
 *   showToast('Heads up! Review pending.', 'info');
 */
(function () {
    'use strict';

    var toastContainer = null;

    function ensureContainer() {
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'admin-toast-container';
            document.body.appendChild(toastContainer);
        }
        return toastContainer;
    }

    /**
     * Show a toast notification.
     * @param {string} message - The message text.
     * @param {string} type - 'success', 'error', or 'info'.
     * @param {number} duration - Auto-dismiss time in ms (default 5000).
     */
    window.showToast = function (message, type, duration) {
        if (!message) return;
        type = type || 'info';
        duration = duration || 5000;

        var container = ensureContainer();

        // Create toast element
        var toast = document.createElement('div');
        toast.className = 'admin-toast is-' + type;

        // Icon
        var iconMap = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle',
        };
        var icon = iconMap[type] || 'fa-info-circle';

        toast.innerHTML =
            '<div class="admin-toast-icon"><i class="fas ' + icon + '"></i></div>' +
            '<div class="admin-toast-body">' +
                '<span class="admin-toast-message">' + escapeHtml(message) + '</span>' +
            '</div>' +
            '<button type="button" class="admin-toast-close" aria-label="Close">&times;</button>' +
            '<div class="admin-toast-progress"><div class="admin-toast-progress-bar"></div></div>';

        container.appendChild(toast);

        // Trigger enter animation
        requestAnimationFrame(function () {
            toast.classList.add('show');
        });

        // Progress bar
        var progressBar = toast.querySelector('.admin-toast-progress-bar');
        progressBar.style.transition = 'width ' + duration + 'ms linear';
        requestAnimationFrame(function () {
            progressBar.style.width = '0%';
        });

        // Close handler
        var closeBtn = toast.querySelector('.admin-toast-close');
        closeBtn.addEventListener('click', function () {
            dismissToast(toast);
        });

        // Auto-dismiss
        var autoTimer = setTimeout(function () {
            dismissToast(toast);
        }, duration);

        // Store timer reference for cleanup
        toast._autoTimer = autoTimer;

        // Pause on hover
        toast.addEventListener('mouseenter', function () {
            clearTimeout(toast._autoTimer);
            progressBar.style.transition = 'none';
            progressBar.style.width = progressBar.getBoundingClientRect().width / toast.getBoundingClientRect().width * 100 + '%';
        });

        toast.addEventListener('mouseleave', function () {
            var remaining = progressBar.getBoundingClientRect().width / toast.getBoundingClientRect().width;
            toast._autoTimer = setTimeout(function () {
                dismissToast(toast);
            }, duration * remaining);
            progressBar.style.transition = 'width ' + (duration * remaining) + 'ms linear';
            requestAnimationFrame(function () {
                progressBar.style.width = '0%';
            });
        });
    };

    function dismissToast(toast) {
        if (toast.classList.contains('hiding')) return;
        toast.classList.remove('show');
        toast.classList.add('hiding');
        clearTimeout(toast._autoTimer);

        // Remove after animation
        setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Auto-show toasts from data attributes on existing alert elements
    document.addEventListener('DOMContentLoaded', function () {
        var alerts = document.querySelectorAll('[data-toast]');
        alerts.forEach(function (el) {
            var message = el.getAttribute('data-toast-message') || el.textContent.trim();
            var type = el.getAttribute('data-toast-type') || 'info';
            if (message) {
                showToast(message, type);
            }
            el.style.display = 'none';
        });
    });

})();
