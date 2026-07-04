/*
 * About / Astrology page — testimonials carousel with dot pagination.
 * Vanilla, dependency-free, and self-guarded: if the carousel markup
 * isn't on the page (any other page loads this with `defer`), it does
 * nothing. Loaded site-wide is therefore safe.
 */
(function () {
    'use strict';

    function initTestimonials(root) {
        var track = root.querySelector('[data-testi-track]');
        var slides = track ? track.children : [];
        var dots = Array.prototype.slice.call(root.querySelectorAll('[data-testi-dot]'));
        var prev = root.querySelector('[data-testi-prev]');
        var next = root.querySelector('[data-testi-next]');
        if (!track || slides.length === 0) return;

        var index = 0;
        var count = slides.length;
        var timer = null;

        function go(i) {
            index = (i + count) % count;
            track.style.transform = 'translateX(' + (-index * 100) + '%)';
            dots.forEach(function (d, di) { d.classList.toggle('is-active', di === index); });
        }

        function stop() { if (timer) { clearInterval(timer); timer = null; } }
        function start() { stop(); timer = setInterval(function () { go(index + 1); }, 6000); }

        if (prev) prev.addEventListener('click', function () { go(index - 1); start(); });
        if (next) next.addEventListener('click', function () { go(index + 1); start(); });
        dots.forEach(function (d, di) {
            d.addEventListener('click', function () { go(di); start(); });
        });

        // Pause the autoplay while the visitor is reading (hover / focus within).
        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('focusin', stop);
        root.addEventListener('focusout', start);

        go(0);
        start();
    }

    function init() {
        var carousels = document.querySelectorAll('[data-testi]');
        Array.prototype.forEach.call(carousels, initTestimonials);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
