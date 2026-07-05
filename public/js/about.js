/*
 * About / Astrology page — testimonials carousel with dot pagination, plus
 * the Home "From the Journal" post-paging carousel.
 * Vanilla, dependency-free, and self-guarded: if a given carousel's markup
 * isn't on the page (any other page loads this with `defer`), that piece does
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

    // Home "From the Journal" carousel: pages through the posts below the
    // featured newest post, N (`data-per-page`) at a time. All posts are
    // already rendered server-side; this just shows/hides them per page and
    // builds the numbered pager + "next" control to match.
    function initJournalCarousel(root) {
        var perPage = parseInt(root.getAttribute('data-per-page'), 10) || 2;
        var cards = Array.prototype.slice.call(root.querySelectorAll('[data-journal-card]'));
        var section = root.closest('.about-shell') || root.parentElement;
        var pager = section ? section.querySelector('[data-journal-pager]') : null;
        if (!cards.length) return;

        var pageCount = Math.ceil(cards.length / perPage);
        var page = 0;

        function render() {
            cards.forEach(function (card, i) {
                card.style.display = Math.floor(i / perPage) === page ? '' : 'none';
            });
            if (!pager) return;
            Array.prototype.forEach.call(pager.querySelectorAll('[data-page]'), function (link) {
                var isActive = parseInt(link.getAttribute('data-page'), 10) === page;
                link.classList.toggle('is-active', isActive);
                if (isActive) { link.setAttribute('aria-current', 'page'); } else { link.removeAttribute('aria-current'); }
            });
            var next = pager.querySelector('[data-journal-next]');
            if (next) next.style.display = page >= pageCount - 1 ? 'none' : '';
        }

        function go(i) {
            page = Math.min(Math.max(i, 0), pageCount - 1);
            render();
        }

        if (pager && pageCount > 1) {
            for (var i = 0; i < pageCount; i++) {
                (function (idx) {
                    var link = document.createElement('a');
                    link.className = 'about-pager__link';
                    link.href = '#';
                    link.textContent = String(idx + 1);
                    link.setAttribute('data-page', String(idx));
                    link.addEventListener('click', function (e) { e.preventDefault(); go(idx); });
                    pager.appendChild(link);
                })(i);
            }

            var next = document.createElement('a');
            next.className = 'about-pager__link about-pager__link--next';
            next.href = '#';
            next.textContent = '→';
            next.setAttribute('aria-label', 'Next page');
            next.setAttribute('data-journal-next', '');
            next.addEventListener('click', function (e) { e.preventDefault(); go(page + 1); });
            pager.appendChild(next);
        } else if (pager) {
            pager.style.display = 'none';
        }

        render();
    }

    function init() {
        var carousels = document.querySelectorAll('[data-testi]');
        Array.prototype.forEach.call(carousels, initTestimonials);

        var journalCarousels = document.querySelectorAll('[data-journal-carousel]');
        Array.prototype.forEach.call(journalCarousels, initJournalCarousel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
