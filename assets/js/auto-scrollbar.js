(function () {
    'use strict';

    var idleDelay = 1000;
    var timers = new WeakMap();
    var root = document.documentElement;
    var scrollKeys = new Set([
        'ArrowDown',
        'ArrowLeft',
        'ArrowRight',
        'ArrowUp',
        'End',
        'Home',
        'PageDown',
        'PageUp',
        ' ',
        'Spacebar',
    ]);

    function isStaticScrollbarElement(element) {
        return Boolean(element && element.closest && element.closest('[data-scrollbar-static]'));
    }

    function isScrollable(element) {
        if (!element || element === document || element === window || element === document.body) {
            return false;
        }

        var style = window.getComputedStyle(element);
        var overflowY = style.overflowY;
        var overflowX = style.overflowX;
        var canScrollY = /(auto|scroll|overlay)/.test(overflowY) && element.scrollHeight > element.clientHeight + 1;
        var canScrollX = /(auto|scroll|overlay)/.test(overflowX) && element.scrollWidth > element.clientWidth + 1;

        return canScrollY || canScrollX;
    }

    function getScrollTarget(start) {
        var element = start && start.nodeType === Node.ELEMENT_NODE ? start : start && start.parentElement;

        while (element && element !== document.body && element !== root) {
            if (isStaticScrollbarElement(element)) {
                return null;
            }

            if (isScrollable(element)) {
                return element;
            }

            element = element.parentElement;
        }

        return root;
    }

    function showScrollbar(element) {
        if (!element || isStaticScrollbarElement(element)) {
            return;
        }

        element.classList.add('is-scrolling');

        if (timers.has(element)) {
            window.clearTimeout(timers.get(element));
        }

        timers.set(element, window.setTimeout(function () {
            element.classList.remove('is-scrolling');
            timers.delete(element);
        }, idleDelay));
    }

    function handleInteraction(event) {
        showScrollbar(getScrollTarget(event.target));
    }

    function handleDocumentScroll(event) {
        if (event.target === document || event.target === root || event.target === document.body) {
            showScrollbar(root);
            return;
        }

        showScrollbar(getScrollTarget(event.target));
    }

    function handleKeydown(event) {
        if (scrollKeys.has(event.key)) {
            showScrollbar(getScrollTarget(document.activeElement));
        }
    }

    window.addEventListener('scroll', handleDocumentScroll, true);
    window.addEventListener('wheel', handleInteraction, { passive: true, capture: true });
    window.addEventListener('touchmove', handleInteraction, { passive: true, capture: true });
    window.addEventListener('keydown', handleKeydown, true);
})();
