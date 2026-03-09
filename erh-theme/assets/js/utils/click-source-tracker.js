/**
 * Click Source Tracker
 *
 * Appends a `?cs={source}` parameter to /go/ affiliate links based on
 * the nearest ancestor with a `data-click-source` attribute.
 *
 * Uses a delegated mousedown listener (capture phase) so the param
 * is set before the browser navigates.
 */

/**
 * Initialize click source tracking.
 */
export function initClickSourceTracking() {
    document.addEventListener('mousedown', handleMouseDown, true);
}

/**
 * Handle mousedown on affiliate links.
 *
 * @param {MouseEvent} event
 */
function handleMouseDown(event) {
    // Find the closest /go/ link from the click target.
    const link = event.target.closest('a[href*="/go/"]');
    if (!link) return;

    // Find the closest ancestor with a click source.
    const sourceEl = link.closest('[data-click-source]');
    if (!sourceEl) return;

    const source = sourceEl.dataset.clickSource;
    if (!source) return;

    const href = link.getAttribute('href');
    if (!href) return;

    // Don't add if already has cs param.
    if (href.includes('cs=')) return;

    // Append cs param.
    const separator = href.includes('?') ? '&' : '?';
    link.setAttribute('href', href + separator + 'cs=' + encodeURIComponent(source));
}
