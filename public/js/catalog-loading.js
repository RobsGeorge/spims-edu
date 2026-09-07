(function () {
    var form = document.querySelector('[data-catalog-loading]');
    var results = document.getElementById('catalog-results');
    var skeletons = document.getElementById('catalog-skeletons');
    if (!form || !results || !skeletons || !window.fetch) {
        return;
    }

    function showSkeletons() {
        results.hidden = true;
        results.setAttribute('aria-busy', 'true');
        skeletons.hidden = false;
        skeletons.setAttribute('aria-hidden', 'false');
    }

    function hideSkeletons() {
        skeletons.hidden = true;
        skeletons.setAttribute('aria-hidden', 'true');
        results.hidden = false;
        results.setAttribute('aria-busy', 'false');
    }

    function filterUrl(source) {
        var url = new URL(source, window.location.origin);
        url.searchParams.delete('fragment');
        url.searchParams.delete('skeleton');
        return url;
    }

    function hasActiveFilters(url) {
        var q = (url.searchParams.get('q') || '').trim();
        var type = url.searchParams.get('type') || 'all';
        var price = url.searchParams.get('price') || 'all';
        var interest = url.searchParams.get('interest') || 'all';
        var sort = url.searchParams.get('sort') || 'code';
        return q !== '' || type !== 'all' || price !== 'all' || interest === 'flagged' || sort === 'interest';
    }

    function syncFeatured(url) {
        var featured = document.querySelector('.catalog-featured');
        if (!featured) {
            return;
        }
        featured.hidden = hasActiveFilters(url);
    }

    function load(url, push) {
        showSkeletons();
        var requestUrl = new URL(url.toString());
        requestUrl.searchParams.set('fragment', '1');
        return fetch(requestUrl.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('catalog fragment failed');
            }
            return response.text();
        }).then(function (html) {
            results.innerHTML = html;
            hideSkeletons();
            syncFeatured(url);
            if (push) {
                history.pushState({ catalog: true }, '', url.toString());
            }
        }).catch(function () {
            window.location.assign(url.toString());
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var url = filterUrl(form.action);
        var data = new FormData(form);
        data.forEach(function (value, key) {
            if (value === '') {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, String(value));
            }
        });
        load(url, true);
    });

    results.addEventListener('click', function (event) {
        var link = event.target.closest('.pagination a');
        if (!link) {
            return;
        }
        event.preventDefault();
        load(filterUrl(link.href), true);
    });

    window.addEventListener('popstate', function () {
        load(filterUrl(window.location.href), false);
    });
})();
