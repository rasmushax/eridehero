/**
 * Page View Tracker
 *
 * Tracks non-product page views for CTR calculations.
 * Product pages have their own tracking via product-page.js.
 *
 * Uses sessionStorage for dedup (one track per page per session).
 */

/**
 * Track the current page view.
 */
export function trackPageView() {
    // Skip product pages (they have their own tracking).
    if (document.querySelector('[data-product-page]')) return;

    const path = getPagePath();
    const type = detectPageType();
    if (!type) return;

    // Session dedup.
    const key = 'erh_pv_' + path;
    if (sessionStorage.getItem(key)) return;
    sessionStorage.setItem(key, '1');

    // Fire and forget.
    const { restUrl, nonce } = window.erhData || {};
    if (!restUrl) return;

    fetch(restUrl + 'pages/view', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce,
        },
        body: JSON.stringify({ path, type }),
        keepalive: true,
    }).catch(() => {});
}

/**
 * Get the current page path (relative to site root).
 *
 * @returns {string}
 */
function getPagePath() {
    let path = window.location.pathname;

    // Strip site subfolder prefix if present.
    const { siteUrl } = window.erhData || {};
    if (siteUrl) {
        try {
            const sitePrefix = new URL(siteUrl).pathname;
            if (sitePrefix !== '/' && path.startsWith(sitePrefix)) {
                path = path.slice(sitePrefix.length);
            }
        } catch (e) {
            // Ignore invalid URL.
        }
    }

    // Normalize: ensure leading slash, strip trailing slash.
    if (!path.startsWith('/')) path = '/' + path;
    if (path !== '/' && path.endsWith('/')) path = path.slice(0, -1);

    return path;
}

/**
 * Detect the page type from the current URL/DOM.
 *
 * @returns {string|null} Page type or null if unclassified.
 */
function detectPageType() {
    const path = getPagePath();

    if (path === '/' || path === '') return 'homepage';
    if (path.startsWith('/compare')) return 'compare';
    if (path.startsWith('/tools')) return 'tool';
    if (path.startsWith('/deals')) return 'deals';
    if (path.startsWith('/finder')) return 'finder';

    // Listicle patterns.
    if (/^\/(best|top|cheapest|fastest|lightest|most|longest|affordable|safest)-/.test(path)) {
        return 'listicle';
    }

    // Category archive pages.
    if (/^\/(e-scooters|e-bikes|electric-skateboards|electric-unicycles|hoverboards)(\/page\/\d+)?$/.test(path)) {
        return 'category';
    }

    // Don't track unclassified pages.
    return null;
}
