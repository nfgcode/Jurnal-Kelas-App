{{--
    Full-screen loading overlay shown on every page load and during internal
    navigation (the server round-trip is slow, so this gives the user feedback
    instead of a frozen page). The styles are inlined here rather than living in
    app.css so the overlay is painted the instant the HTML arrives, before the
    stylesheet request resolves.
--}}
<style>
    #page-loader {
        position: fixed;
        inset: 0;
        z-index: 4000;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 20px;
        background: #23371d;
        transition: opacity .35s ease, visibility .35s ease;
    }
    #page-loader.is-hidden { opacity: 0; visibility: hidden; }
    .page-loader__mark {
        width: 58px; height: 58px; border-radius: 16px;
        background: rgba(255, 255, 255, .14);
        display: grid; place-items: center;
    }
    .page-loader__mark svg { width: 30px; height: 30px; }
    .page-loader__spin {
        width: 34px; height: 34px; border-radius: 50%;
        border: 3px solid rgba(255, 255, 255, .22);
        border-top-color: #fff;
        animation: page-loader-spin .7s linear infinite;
    }
    .page-loader__text {
        color: rgba(255, 255, 255, .85);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 13px; font-weight: 600; letter-spacing: .04em;
    }
    @keyframes page-loader-spin { to { transform: rotate(360deg); } }
    @media (prefers-reduced-motion: reduce) { .page-loader__spin { animation: none; } }
</style>

<div id="page-loader" role="status" aria-live="polite" aria-label="Memuat halaman">
    <span class="page-loader__mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H5.5A1.5 1.5 0 0 1 4 18.5z"/>
            <path d="M8 3v17M12 8h4M12 12h4"/>
        </svg>
    </span>
    <span class="page-loader__spin"></span>
    <span class="page-loader__text">Memuat…</span>
</div>

<script>
    (function () {
        var loader = document.getElementById('page-loader');
        if (!loader) return;

        // While a navigation is in flight the overlay must stay up; the safety
        // timeout below only clears the initial load, never a pending nav.
        var navigating = false;
        var show = function () { loader.classList.remove('is-hidden'); };
        var hide = function () { if (!navigating) loader.classList.add('is-hidden'); };

        // Clear the overlay the moment the DOM is ready (content is usable),
        // rather than waiting for window.load — which lingers until every image
        // and CDN font has arrived, leaving the loader up until the very end.
        if (document.readyState !== 'loading') hide();
        else document.addEventListener('DOMContentLoaded', hide);
        setTimeout(hide, 5000);

        // Back/forward cache restores a finished page — clear the overlay at once.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) { navigating = false; loader.classList.add('is-hidden'); }
        });

        // Re-show for a real same-tab navigation so the slow round-trip has feedback.
        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) return;
            var a = e.target.closest('a[href]');
            if (!a) return;
            // Skip new tabs, downloads (e.g. the Excel export), anchors, JS links,
            // and anything opting out via data-no-loader.
            if (a.target === '_blank' || a.hasAttribute('download') || 'noLoader' in a.dataset) return;
            var href = a.getAttribute('href');
            if (!href || href.charAt(0) === '#' || href.slice(0, 11) === 'javascript:') return;
            if (a.origin && a.origin !== window.location.origin) return;
            navigating = true; show();
        });

        // Filter submits and login all navigate too.
        document.addEventListener('submit', function () { navigating = true; show(); });
    })();
</script>
