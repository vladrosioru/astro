/*
 * Services page — category tab switching (Archetypes/Methods panel swap),
 * the Archetypes pentagon flip-card interaction, and (phone widths only,
 * ≤620px) a swipeable one-card-at-a-time carousel for the Archetypes,
 * Methods, and Tarot card groups, which loops past either end (last card's
 * "next" lands back on the first, and vice versa). Vanilla, dependency-free,
 * and self-guarded: if the relevant elements aren't on the page, each piece
 * does nothing, so it is safe to load site-wide.
 * Testimonials on this page reuse the About page's [data-testi] carousel,
 * already initialised by about.js.
 */
(function () {
    'use strict';

    function initTabs(root) {
        var tabs = Array.prototype.slice.call(root.querySelectorAll('[data-svc-tab]'));
        var panels = Array.prototype.slice.call(document.querySelectorAll('[data-svc-panel]'));
        if (!tabs.length || !panels.length) return;

        function show(key) {
            panels.forEach(function (panel) {
                panel.hidden = panel.getAttribute('data-svc-panel') !== key;
            });
            tabs.forEach(function (tab) {
                var active = tab.getAttribute('data-svc-tab') === key;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
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

    function initFlipCards() {
        var cards = document.querySelectorAll('[data-flip]');
        Array.prototype.forEach.call(cards, function (card) {
            function toggle() {
                var flipped = card.classList.toggle('is-flipped');
                card.setAttribute('aria-pressed', String(flipped));
            }
            card.addEventListener('click', toggle);
            card.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggle();
                }
            });
        });
    }

    // Phone-only (≤620px) swipeable carousel: one card in view per card
    // group, native scroll-snap handles the swipe gesture itself (a real
    // touch-scroll never fires a synthetic click on the card being dragged,
    // so this never competes with initFlipCards' tap-to-flip above). Arrow/
    // dot listeners are harmless no-ops above 620px, since the arrows are
    // hidden and the track has no overflow to scroll there.
    //
    // Looping: a clone of the last card is prepended and a clone of the
    // first card is appended, so "next" from the last real card (or "prev"
    // from the first) has an adjacent slide to animate onto. Once the scroll
    // settles on a clone, it's instantly (no animation) replaced by scrolling
    // to the matching real card — the classic clone-buffer technique for an
    // infinite-looking loop without actually duplicating content twice over.
    function initCarousel(track) {
        var cards = Array.prototype.slice.call(track.querySelectorAll('.flip-card, .svc-card'));
        var root = track.parentElement;
        if (!cards.length || !root) return;

        var loop = cards.length > 1;
        var cloneFirst = null;
        var cloneLast = null;
        if (loop) {
            cloneFirst = cards[0].cloneNode(true);
            cloneLast = cards[cards.length - 1].cloneNode(true);
            [cloneFirst, cloneLast].forEach(function (clone) {
                clone.setAttribute('data-svc-clone', '');
                clone.setAttribute('aria-hidden', 'true');
                clone.removeAttribute('data-flip');
                clone.removeAttribute('tabindex');
                clone.removeAttribute('role');
                clone.removeAttribute('id');
            });
            // Insert each clone next to the real card it borders, using that
            // card's own parent — Archetypes' cards live nested inside
            // .svc-pentagon__row wrappers, not directly under `track`, so
            // `track.insertBefore(clone, cards[0])` would throw (cards[0]
            // isn't track's own child). display:contents on the row
            // wrappers flattens everything into one visual scroll-snap row
            // regardless of which wrapper a slide's DOM parent is.
            var firstCard = cards[0];
            var lastCard = cards[cards.length - 1];
            firstCard.parentNode.insertBefore(cloneLast, firstCard);
            lastCard.parentNode.insertBefore(cloneFirst, lastCard.nextSibling);
        }
        var slides = loop ? [cloneLast].concat(cards, [cloneFirst]) : cards.slice();

        var prev = root.querySelector('[data-svc-prev]');
        var next = root.querySelector('[data-svc-next]');
        var dots = Array.prototype.slice.call(root.querySelectorAll('[data-svc-dot]'));

        function slideIndex() {
            var i = Math.round(track.scrollLeft / track.clientWidth);
            return Math.max(0, Math.min(i, slides.length - 1));
        }

        // Maps a slide index (which may be a cloned bridge slide) to the
        // real card index it represents.
        function toRealIndex(i) {
            if (!loop) return i;
            if (i === 0) return cards.length - 1;
            if (i === slides.length - 1) return 0;
            return i - 1;
        }

        function goToSlide(i) {
            i = Math.max(0, Math.min(i, slides.length - 1));
            slides[i].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }

        function goToReal(realI) {
            goToSlide(realI + (loop ? 1 : 0));
        }

        function resetFlips() {
            cards.forEach(function (card) {
                if (card.classList.contains('is-flipped')) {
                    card.classList.remove('is-flipped');
                    card.setAttribute('aria-pressed', 'false');
                }
            });
        }

        function updateDots(realI) {
            dots.forEach(function (dot, di) { dot.classList.toggle('is-active', di === realI); });
        }

        var settleTimer = null;
        track.addEventListener('scroll', function () {
            resetFlips();
            clearTimeout(settleTimer);
            settleTimer = setTimeout(function () {
                var i = slideIndex();
                var realI = toRealIndex(i);
                if (loop && (i === 0 || i === slides.length - 1)) {
                    // Landed on a clone: jump to the real equivalent with no
                    // animation, completing the illusion of an endless loop.
                    track.scrollLeft += cards[realI].getBoundingClientRect().left - track.getBoundingClientRect().left;
                }
                updateDots(realI);
            }, 120);
        });

        if (prev) prev.addEventListener('click', function () { goToSlide(slideIndex() - 1); });
        if (next) next.addEventListener('click', function () { goToSlide(slideIndex() + 1); });
        dots.forEach(function (dot, di) {
            dot.addEventListener('click', function () { goToReal(di); });
        });

        track.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { e.preventDefault(); goToSlide(slideIndex() - 1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); goToSlide(slideIndex() + 1); }
        });

        // Default card: the Archetypes "Identity & growth" pillar already
        // carries flip-card--center (see svc-flip-card.blade.php); Methods
        // and Tarot cards never have that class, so they naturally fall back
        // to the first card instead. Blade already renders the matching dot
        // as .is-active server-side, so this only needs to move the scroll
        // position — it must NOT call updateDots(), which would compute a
        // bogus index (division by a clientWidth of 0) while the panel is
        // still `hidden` behind the other tab and clobber that correct
        // server-rendered state.
        var mq = window.matchMedia('(max-width: 620px)');
        function positionDefault() {
            var defaultCard = track.querySelector('.flip-card--center') || cards[0];
            if (defaultCard) {
                track.scrollLeft += defaultCard.getBoundingClientRect().left - track.getBoundingClientRect().left;
            }
        }
        if (mq.matches) positionDefault();
        mq.addEventListener('change', function (e) { if (e.matches) positionDefault(); });
    }

    function init() {
        var bars = document.querySelectorAll('[data-svc-tabs]');
        Array.prototype.forEach.call(bars, initTabs);
        initFlipCards();

        var tracks = document.querySelectorAll('[data-svc-track]');
        Array.prototype.forEach.call(tracks, initCarousel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
