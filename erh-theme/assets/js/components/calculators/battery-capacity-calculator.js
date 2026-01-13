/**
 * Battery Capacity Calculator
 *
 * Converts between Wh, Ah, and V using tabs.
 * User selects what they want to calculate.
 * Supports kWh and mAh unit toggles for various device sizes.
 *
 * Formulas:
 * Wh = Ah × V
 * Ah = Wh ÷ V
 * V = Wh ÷ Ah
 */

import {
    formatNumber,
} from '../../utils/calculator-utils.js';

/**
 * Format number with up to 2 decimal places, trimming trailing zeros.
 */
function formatValue(num) {
    if (num === null || num === undefined || isNaN(num) || num === 0) return '--';
    const formatted = num.toFixed(2).replace(/\.?0+$/, '');
    return formatNumber(parseFloat(formatted));
}

/**
 * Initialize the battery capacity calculator.
 */
export function init(container) {
    const tabs = container.querySelectorAll('[data-calc-tab]');
    const panels = container.querySelectorAll('[data-calc-panel]');
    const resetBtn = container.querySelector('[data-calculator-reset]');

    let activeTab = 'wh';

    // Track active units per tab
    const units = {
        wh: 'Wh',   // Result unit for Calculate Wh tab
        ah: 'Ah',   // Result unit for Calculate Ah tab
    };

    /**
     * Switch active tab/panel.
     */
    function switchTab(tabName) {
        activeTab = tabName;

        tabs.forEach(tab => {
            const isActive = tab.dataset.calcTab === tabName;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive);
        });

        panels.forEach(panel => {
            const isActive = panel.dataset.calcPanel === tabName;
            panel.hidden = !isActive;
        });

        calculate();
    }

    /**
     * Calculate based on active tab.
     */
    function calculate() {
        const panel = container.querySelector(`[data-calc-panel="${activeTab}"]`);
        if (!panel) return;

        const resultEl = panel.querySelector('[data-result="value"]');
        if (!resultEl) return;

        let result = null;
        let displayUnit = '';

        if (activeTab === 'wh') {
            const ah = parseFloat(panel.querySelector('[data-input="ah"]')?.value) || 0;
            const v = parseFloat(panel.querySelector('[data-input="voltage"]')?.value) || 0;
            if (ah > 0 && v > 0) {
                result = ah * v; // Result in Wh
                // Convert to kWh if toggle is set
                if (units.wh === 'kWh') {
                    result = result / 1000;
                    displayUnit = 'kWh';
                } else {
                    displayUnit = 'Wh';
                }
                resultEl.textContent = formatValue(result) + ' ' + displayUnit;
            } else {
                resultEl.textContent = '--';
            }
        } else if (activeTab === 'ah') {
            const wh = parseFloat(panel.querySelector('[data-input="wh"]')?.value) || 0;
            const v = parseFloat(panel.querySelector('[data-input="voltage"]')?.value) || 0;
            if (wh > 0 && v > 0) {
                result = wh / v; // Result in Ah
                // Convert to mAh if toggle is set
                if (units.ah === 'mAh') {
                    result = result * 1000;
                    displayUnit = 'mAh';
                } else {
                    displayUnit = 'Ah';
                }
                resultEl.textContent = formatValue(result) + ' ' + displayUnit;
            } else {
                resultEl.textContent = '--';
            }
        } else if (activeTab === 'voltage') {
            const wh = parseFloat(panel.querySelector('[data-input="wh"]')?.value) || 0;
            const ah = parseFloat(panel.querySelector('[data-input="ah"]')?.value) || 0;
            if (wh > 0 && ah > 0) {
                result = wh / ah;
                resultEl.textContent = formatValue(result) + ' V';
            } else {
                resultEl.textContent = '--';
            }
        }

        // Announce for screen readers
        if (result !== null) {
            announceResult(result, displayUnit || 'V');
        }
    }

    /**
     * Handle unit toggle clicks.
     */
    function initUnitToggles() {
        container.querySelectorAll('[data-unit-toggle]').forEach(toggle => {
            const btns = toggle.querySelectorAll('[data-unit]');
            btns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const unitType = toggle.dataset.unitToggle; // 'wh' or 'ah'
                    const unitValue = btn.dataset.unit; // 'Wh', 'kWh', 'Ah', 'mAh'

                    // Update active state
                    btns.forEach(b => b.classList.remove('is-active'));
                    btn.classList.add('is-active');

                    // Store selection
                    units[unitType] = unitValue;

                    // Recalculate
                    calculate();
                });
            });
        });
    }

    // Debounced announcer
    let announceTimeout;
    function announceResult(value, unit) {
        clearTimeout(announceTimeout);
        announceTimeout = setTimeout(() => {
            const announcer = container.querySelector('[aria-live]');
            const labels = { wh: 'Capacity', ah: 'Charge', voltage: 'Voltage' };
            if (announcer) {
                announcer.textContent = `${labels[activeTab]}: ${formatValue(value)} ${unit}.`;
            }
        }, 500);
    }

    /**
     * Reset all inputs in all panels.
     */
    function resetCalculator() {
        panels.forEach(panel => {
            panel.querySelectorAll('input[type="number"]').forEach(input => {
                input.value = '';
            });
            const resultEl = panel.querySelector('[data-result="value"]');
            if (resultEl) resultEl.textContent = '--';
        });

        // Reset unit toggles to defaults
        units.wh = 'Wh';
        units.ah = 'Ah';
        container.querySelectorAll('[data-unit-toggle]').forEach(toggle => {
            const btns = toggle.querySelectorAll('[data-unit]');
            btns.forEach((btn, i) => {
                btn.classList.toggle('is-active', i === 0);
            });
        });

        const announcer = container.querySelector('[aria-live]');
        if (announcer) {
            announcer.textContent = 'Calculator reset.';
        }
    }

    // Bind tab clicks
    tabs.forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.calcTab));

        // Keyboard navigation
        tab.addEventListener('keydown', (e) => {
            const tabsArray = Array.from(tabs);
            const currentIndex = tabsArray.indexOf(tab);
            let nextIndex = currentIndex;

            if (e.key === 'ArrowRight') {
                nextIndex = (currentIndex + 1) % tabsArray.length;
            } else if (e.key === 'ArrowLeft') {
                nextIndex = (currentIndex - 1 + tabsArray.length) % tabsArray.length;
            }

            if (nextIndex !== currentIndex) {
                e.preventDefault();
                const nextTab = tabsArray[nextIndex];
                nextTab.focus();
                switchTab(nextTab.dataset.calcTab);
            }
        });
    });

    // Bind input handlers for each panel
    panels.forEach(panel => {
        panel.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('input', calculate);
        });
    });

    // Bind reset button
    if (resetBtn) {
        resetBtn.addEventListener('click', resetCalculator);
    }

    // Initialize unit toggles
    initUnitToggles();

    // Initialize first tab
    switchTab('wh');
}
