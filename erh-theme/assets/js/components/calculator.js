/**
 * Calculator Dispatcher
 *
 * Loads calculator modules dynamically based on data-calculator attribute.
 * Each calculator is a separate module in ./calculators/ folder.
 */

export function initCalculator() {
    const container = document.querySelector('[data-calculator]');
    if (!container) return null;

    const type = container.dataset.calculator;
    if (!type) {
        console.warn('[Calculator] No calculator type specified');
        return null;
    }

    // Dynamic import based on calculator type
    import(`./calculators/${type}.js`)
        .then(module => {
            if (typeof module.init === 'function') {
                module.init(container);
            } else {
                console.warn(`[Calculator] Module "${type}" has no init function`);
            }
        })
        .catch(err => {
            console.error(`[Calculator] Failed to load "${type}":`, err);
        });

    return { type };
}
