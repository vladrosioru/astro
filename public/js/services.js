/*
 * Services page — category tab filter for the service card grid.
 * Vanilla, dependency-free, and self-guarded: if the tab bar or grid
 * aren't on the page, this does nothing, so it is safe to load site-wide.
 * Testimonials on this page reuse the About page's [data-testi] carousel,
 * already initialised by about.js.
 */
(function () {
    'use strict';

    function initTabs(root) {
        var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-svc-tab]'));
        var grid = document.querySelector('[data-svc-grid]');
        if (!tabs.length || !grid) return;

        var cards = Array.prototype.slice.call(grid.querySelectorAll('[data-svc-cat]'));

        function show(cat) {
            cards.forEach(function (card) {
                card.hidden = card.getAttribute('data-svc-cat') !== cat;
            });
            tabs.forEach(function (tab) {
                tab.classList.toggle('is-active', tab.getAttribute('data-svc-tab') === cat);
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                show(tab.getAttribute('data-svc-tab'));
            });
        });

        var active = tabs.filter(function (t) { return t.classList.contains('is-active'); })[0] || tabs[0];
        show(active.getAttribute('data-svc-tab'));
    }

    function init() {
        var bars = document.querySelectorAll('[data-svc-tabs]');
        Array.prototype.forEach.call(bars, initTabs);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
