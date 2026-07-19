(function () {
    var link = document.querySelector('[data-fb-page]');
    if (!link) return;

    var isAndroidPhone = /Android/i.test(navigator.userAgent)
        && window.matchMedia('(max-width: 720px)').matches;
    if (!isAndroidPhone) return;

    link.addEventListener('click', function (e) {
        e.preventDefault();
        var target = link.href;
        var intentUrl = 'intent://facewebmodal/f?href=' + encodeURIComponent(target)
            + '#Intent;package=com.facebook.katana;scheme=fb;'
            + 'S.browser_fallback_url=' + encodeURIComponent(target) + ';end';
        window.location.href = intentUrl;
    });
})();
