/*
 * Article share icons — open Facebook/X's share dialog in a small popup
 * window (matching how the reference theme's share icons behave) instead of
 * a full new tab. Self-guarded: if `.article-share` isn't on the page (any
 * other page loads this with `defer`), it does nothing. Progressive
 * enhancement: the links keep target="_blank" so they still work with JS
 * disabled, just as a normal new tab instead of a popup.
 *
 * Instagram has no "share this link" web intent, so its link points at the
 * profile instead of a per-article share URL — clicking it also copies the
 * article link to the clipboard and shows a brief toast so the visitor can
 * paste it once they're in Instagram.
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

    function showToast(message) {
        var toast = document.createElement('div');
        toast.className = 'article-share-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        window.requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });
        window.setTimeout(function () {
            toast.classList.remove('is-visible');
            window.setTimeout(function () {
                toast.remove();
            }, 300);
        }, 2500);
    }

    var links = document.querySelectorAll('.article-share a[target="_blank"]');
    Array.prototype.forEach.call(links, function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();

            if (link.dataset.share === 'instagram') {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(window.location.href).then(function () {
                        showToast('Link copied — paste it on Instagram');
                    }, function () {
                        showToast('Copy this page’s link, then paste it on Instagram');
                    });
                } else {
                    showToast('Copy this page’s link, then paste it on Instagram');
                }
            }

            openPopup(link.href);
        });
    });
})();
