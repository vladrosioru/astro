(function () {
    var links = document.querySelectorAll('[data-fb-page]');
    if (!links.length) return;

    var isAndroidPhone = /Android/i.test(navigator.userAgent)
        && window.matchMedia('(max-width: 720px)').matches;
    if (!isAndroidPhone) return;

    Array.prototype.forEach.call(links, function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var target = link.href;
            var intentUrl = 'intent://facewebmodal/f?href=' + encodeURIComponent(target)
                + '#Intent;package=com.facebook.katana;scheme=fb;'
                + 'S.browser_fallback_url=' + encodeURIComponent(target) + ';end';
            window.location.href = intentUrl;
        });
    });
})();
