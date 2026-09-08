(function () {
    var KEY = 'spims.session.entered';
    var loader = document.getElementById('spims-loader');
    var reduced = false;
    try {
        reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) {}

    function firstVisit() {
        try {
            return !sessionStorage.getItem(KEY);
        } catch (e) {
            return false;
        }
    }

    function markEntered() {
        try {
            sessionStorage.setItem(KEY, '1');
        } catch (e) {}
    }

    window.SpimsLoader = {
        show: function () {
            document.documentElement.classList.add('spims-loader-pending');
            if (loader) {
                loader.classList.add('is-visible');
            }
        },
        hide: function () {
            document.documentElement.classList.remove('spims-loader-pending');
            if (loader) {
                loader.classList.remove('is-visible');
            }
        }
    };

    function staggerEnter() {
        if (reduced || !document.documentElement.classList.contains('spims-session-enter')) {
            return;
        }
        var nodes = document.querySelectorAll(
            '.spims-page-header, .spims-card, .app-card, .app-tile, .catalog-card, .catalog-featured, .bento-grid > *, .hub-page .row > *, .spims-stat, .spims-landing-program, .spims-landing-stat, .auth-card'
        );
        var i;
        for (i = 0; i < nodes.length; i += 1) {
            nodes[i].classList.add('spims-enter');
            nodes[i].style.setProperty('--enter-i', String(Math.min(i, 14)));
        }
    }

    function hideAfterPaint() {
        var delay = firstVisit() && !reduced ? 420 : 0;
        window.setTimeout(function () {
            window.SpimsLoader.hide();
            document.documentElement.classList.add('spims-loader-ready');
            markEntered();
        }, delay);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            staggerEnter();
            hideAfterPaint();
        });
    } else {
        staggerEnter();
        hideAfterPaint();
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.tagName !== 'FORM') {
            return;
        }
        if (form.hasAttribute('data-catalog-loading')) {
            return;
        }
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'get') {
            return;
        }
        window.SpimsLoader.show();
    });

    window.addEventListener('beforeunload', function () {
        if (reduced) {
            return;
        }
        window.SpimsLoader.show();
    });

    document.addEventListener('error', function (event) {
        var target = event.target;
        if (target && target.classList && target.classList.contains('spims-cover-photo')) {
            target.remove();
        }
    }, true);
})();
