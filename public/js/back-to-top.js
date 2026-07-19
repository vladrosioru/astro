(function () {
    var btn = document.getElementById('back-to-top');
    if (!btn) return;

    var THRESHOLD = 400;
    var ticking = false;

    function update() {
        btn.classList.toggle('is-visible', window.scrollY > THRESHOLD);
        ticking = false;
    }

    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(update);
            ticking = true;
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    update();

    btn.addEventListener('click', function () {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
