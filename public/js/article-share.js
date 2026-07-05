/*
 * Article share icons — open Facebook/X's share dialog in a small popup
 * window (matching how the reference theme's share icons behave) instead of
 * a full new tab. Self-guarded: if `.article-share` isn't on the page (any
 * other page loads this with `defer`), it does nothing. Progressive
 * enhancement: the links keep target="_blank" so they still work with JS
 * disabled, just as a normal new tab instead of a popup.
 */
(function () {
    'use strict';

    function openPopup(url) {
        var width = 580;
        var height = 650;
        var left = Math.max(0, (window.screenX || 0) + (window.outerWidth - width) / 2);
        var top = Math.max(0, (window.screenY || 0) + (window.outerHeight - height) / 2);
        window.open(
            url,
            'share',
            'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top +
                ',resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,status=no'
        );
    }

    var links = document.querySelectorAll('.article-share a[target="_blank"]');
    Array.prototype.forEach.call(links, function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            openPopup(link.href);
        });
    });
})();
