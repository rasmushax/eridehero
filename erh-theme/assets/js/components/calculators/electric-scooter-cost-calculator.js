/**
 * Electric Scooter Cost Calculator
 *
 * Calculates the real cost of owning and riding an electric scooter,
 * with optional comparisons against gas cars, electric cars,
 * public transit, and rideshare.
 *
 * Defaults based on real-world testing of 85+ electric scooters.
 *
 * Supports Imperial (miles, gallons, MPG) and Metric (km, liters, L/100km).
 */

import {
    formatNumber,
    bindInputHandlers,
} from '../../utils/calculator-utils.js';

// ── Constants ──────────────────────────────────────────────────────────

const MI_TO_KM = 1.60934;
const GAL_TO_L = 3.78541;
const MPG_TO_L100KM = 235.214; // Reciprocal conversion: L/100km = 235.214 / MPG

// Based on testing of 85 electric scooters:
// Avg battery 620 Wh, avg tested range 22.3 mi, avg efficiency 43.24 mi/kWh
const DEFAULTS = {
    commute: 5,
    days: 5,
    battery: 620,
    range: 22,
    price: 800,
    lifespan: 4,
    electricity: 0.16,
    gasMpg: 25,
    gasPrice: 3.50,
    evEfficiency: 3.5,
    transitMonthly: 100,
    ridesharePerMile: 2.50,
};

// ── Formatters ─────────────────────────────────────────────────────────

function formatMoney(num) {
    if (num === null || isNaN(num) || !isFinite(num)) return '--';
    const abs = Math.abs(num);
    const sign = num < 0 ? '-' : '';
    if (abs >= 1000) return sign + '$' + formatNumber(Math.round(abs));
    return sign + '$' + abs.toFixed(2);
}

function formatPerDistance(num, unit) {
    if (num === null || isNaN(num) || !isFinite(num) || num <= 0) return '--';
    if (num < 0.01) return (num * 100).toFixed(2) + '\u00A2/' + unit;
    if (num < 0.10) return (num * 100).toFixed(1) + '\u00A2/' + unit;
    return '$' + num.toFixed(2) + '/' + unit;
}

// ── Init ───────────────────────────────────────────────────────────────

export function init(container) {
    let isMetric = false;
    const enabled = new Set(['gas']); // Gas car enabled by default

    // ── DOM refs ────────────────────────────────────────────────────

    const unitToggles = container.querySelectorAll('[data-unit-system]');
    const comparisonToggles = container.querySelectorAll('[data-comparison-toggle]');
    const resetBtn = container.querySelector('[data-calculator-reset]');

    const el = (sel) => container.querySelector(sel);

    const inputs = {
        commute:         el('[data-input="commute"]'),
        days:            el('[data-input="days"]'),
        battery:         el('[data-input="battery"]'),
        range:           el('[data-input="range"]'),
        price:           el('[data-input="price"]'),
        lifespan:        el('[data-input="lifespan"]'),
        electricity:     el('[data-input="electricity"]'),
        gasMpg:          el('[data-input="gas-mpg"]'),
        gasPrice:        el('[data-input="gas-price"]'),
        evEfficiency:    el('[data-input="ev-efficiency"]'),
        transitMonthly:  el('[data-input="transit-monthly"]'),
        rideshareRate:   el('[data-input="rideshare-rate"]'),
    };

    const sections = {
        gas:       el('[data-comparison="gas"]'),
        ev:        el('[data-comparison="ev"]'),
        transit:   el('[data-comparison="transit"]'),
        rideshare: el('[data-comparison="rideshare"]'),
    };

    const resultSections = {
        gas:       el('[data-result-section="gas"]'),
        ev:        el('[data-result-section="ev"]'),
        transit:   el('[data-result-section="transit"]'),
        rideshare: el('[data-result-section="rideshare"]'),
    };

    const res = (name) => el(`[data-result="${name}"]`);

    // ── Helpers ─────────────────────────────────────────────────────

    function getVal(input) {
        return parseFloat(input?.value) || 0;
    }

    function setResult(element, value) {
        if (element) element.textContent = value;
    }

    function displaySavings(element, savings) {
        if (!element) return;
        element.classList.remove('result-value--success', 'result-value--warning');
        if (savings > 1) {
            element.textContent = formatMoney(savings) + '/yr';
            element.classList.add('result-value--success');
        } else if (savings < -1) {
            element.textContent = formatMoney(Math.abs(savings)) + ' more/yr';
            element.classList.add('result-value--warning');
        } else {
            element.textContent = 'About the same';
        }
    }

    function convertInput(input, factor, decimals) {
        if (!input?.value) return;
        const val = parseFloat(input.value);
        if (!isNaN(val) && val > 0) {
            input.value = (val * factor).toFixed(decimals);
        }
    }

    // ── Calculation ────────────────────────────────────────────────

    function calculate() {
        const distUnit = isMetric ? 'km' : 'mi';

        // Raw input values
        let commuteDist = getVal(inputs.commute);
        let rangeDist = getVal(inputs.range);
        let gasFuelEcon = getVal(inputs.gasMpg);
        let gasFuelPrice = getVal(inputs.gasPrice);
        let evEff = getVal(inputs.evEfficiency);
        let rideshareRate = getVal(inputs.rideshareRate);

        const days = getVal(inputs.days);
        const battery = getVal(inputs.battery);
        const price = getVal(inputs.price);
        const lifespan = getVal(inputs.lifespan);
        const elecCost = getVal(inputs.electricity);
        const transitMonthly = getVal(inputs.transitMonthly);

        // Convert to base units (miles, gallons, MPG) if metric
        let commuteMi, rangeMi, gasMpg, gasPriceGal, evMiKwh, ridesharePerMi;

        if (isMetric) {
            commuteMi = commuteDist / MI_TO_KM;
            rangeMi = rangeDist / MI_TO_KM;
            gasMpg = gasFuelEcon > 0 ? MPG_TO_L100KM / gasFuelEcon : 0;
            gasPriceGal = gasFuelPrice * GAL_TO_L;
            evMiKwh = evEff / MI_TO_KM;
            ridesharePerMi = rideshareRate * MI_TO_KM;
        } else {
            commuteMi = commuteDist;
            rangeMi = rangeDist;
            gasMpg = gasFuelEcon;
            gasPriceGal = gasFuelPrice;
            evMiKwh = evEff;
            ridesharePerMi = rideshareRate;
        }

        // Annual distance
        const annualMiles = commuteMi * 2 * days * 52;
        if (annualMiles <= 0 || rangeMi <= 0 || battery <= 0) {
            clearResults();
            return;
        }

        // ── E-Scooter costs ────────────────────────────────────────

        const costPerCharge = (battery / 1000) * elecCost;
        const costPerMileElec = costPerCharge / rangeMi;
        const annualElectricity = costPerMileElec * annualMiles;
        const annualOwnership = lifespan > 0 ? price / lifespan : 0;
        const scooterAnnual = annualElectricity + annualOwnership;
        const scooterMonthly = scooterAnnual / 12;
        const scooterRunningMonthly = annualElectricity / 12;

        // Display with correct distance unit
        const displayPerDist = isMetric ? costPerMileElec * MI_TO_KM : costPerMileElec;

        setResult(res('scooter-per-distance'), formatPerDistance(displayPerDist, distUnit));
        setResult(res('scooter-monthly'), formatMoney(scooterMonthly));
        setResult(res('scooter-annual'), formatMoney(scooterAnnual));
        setResult(res('scooter-per-charge'), formatMoney(costPerCharge));

        // ── Comparisons ────────────────────────────────────────────

        let biggestSaving = 0;
        let biggestMode = '';
        let biggestModeMonthly = 0;

        // Gas car
        if (enabled.has('gas') && gasMpg > 0 && gasPriceGal > 0) {
            const gasPerMile = gasPriceGal / gasMpg;
            const gasAnnual = gasPerMile * annualMiles;
            const gasSavings = gasAnnual - annualElectricity;
            const displayGasPerDist = isMetric ? gasPerMile * MI_TO_KM : gasPerMile;

            setResult(res('gas-per-distance'), formatPerDistance(displayGasPerDist, distUnit));
            setResult(res('gas-annual'), formatMoney(gasAnnual));
            displaySavings(res('gas-savings'), gasSavings);

            if (gasSavings > biggestSaving) {
                biggestSaving = gasSavings;
                biggestMode = 'gas car';
                biggestModeMonthly = gasAnnual / 12;
            }
        }

        // Electric car
        if (enabled.has('ev') && evMiKwh > 0 && elecCost > 0) {
            const evPerMile = elecCost / evMiKwh;
            const evAnnual = evPerMile * annualMiles;
            const evSavings = evAnnual - annualElectricity;
            const displayEvPerDist = isMetric ? evPerMile * MI_TO_KM : evPerMile;

            setResult(res('ev-per-distance'), formatPerDistance(displayEvPerDist, distUnit));
            setResult(res('ev-annual'), formatMoney(evAnnual));
            displaySavings(res('ev-savings'), evSavings);

            if (evSavings > biggestSaving) {
                biggestSaving = evSavings;
                biggestMode = 'electric car';
                biggestModeMonthly = evAnnual / 12;
            }
        }

        // Public transit
        if (enabled.has('transit') && transitMonthly > 0) {
            const transitAnnual = transitMonthly * 12;
            const transitSavings = transitAnnual - annualElectricity;

            setResult(res('transit-annual'), formatMoney(transitAnnual));
            displaySavings(res('transit-savings'), transitSavings);

            if (transitSavings > biggestSaving) {
                biggestSaving = transitSavings;
                biggestMode = 'public transit';
                biggestModeMonthly = transitMonthly;
            }
        }

        // Rideshare
        if (enabled.has('rideshare') && ridesharePerMi > 0) {
            const rideshareAnnual = ridesharePerMi * annualMiles;
            const rideshareSavings = rideshareAnnual - annualElectricity;
            const displayRsPerDist = isMetric ? ridesharePerMi * MI_TO_KM : ridesharePerMi;

            setResult(res('rideshare-per-distance'), formatPerDistance(displayRsPerDist, distUnit));
            setResult(res('rideshare-annual'), formatMoney(rideshareAnnual));
            displaySavings(res('rideshare-savings'), rideshareSavings);

            if (rideshareSavings > biggestSaving) {
                biggestSaving = rideshareSavings;
                biggestMode = 'rideshare';
                biggestModeMonthly = rideshareAnnual / 12;
            }
        }

        // ── Summary ────────────────────────────────────────────────

        const summaryEl = res('biggest-saving');
        const breakEvenEl = res('break-even');

        if (biggestSaving > 1 && summaryEl) {
            summaryEl.textContent = formatMoney(biggestSaving) + '/yr';
            summaryEl.classList.remove('result-value--warning');
            summaryEl.classList.add('result-value--success');

            // Break-even: months until purchase price is recovered
            // Compare alternative monthly cost vs scooter running cost (excl. purchase)
            const netMonthlySaving = biggestModeMonthly - scooterRunningMonthly;
            if (netMonthlySaving > 0 && breakEvenEl) {
                const months = Math.ceil(price / netMonthlySaving);
                if (months <= 120) {
                    breakEvenEl.textContent = months <= 1 ? '< 1 month' : months + ' months';
                } else {
                    breakEvenEl.textContent = '10+ years';
                }
            } else {
                setResult(breakEvenEl, '--');
            }
        } else {
            setResult(summaryEl, '--');
            setResult(breakEvenEl, '--');
            if (summaryEl) {
                summaryEl.classList.remove('result-value--success');
            }
        }

        // Show/hide result sections
        Object.entries(resultSections).forEach(([mode, section]) => {
            if (section) section.hidden = !enabled.has(mode);
        });

        announceResults(scooterAnnual, biggestSaving, biggestMode);
    }

    function clearResults() {
        container.querySelectorAll('[data-result]').forEach(el => {
            el.textContent = '--';
            el.classList.remove('result-value--success', 'result-value--warning');
        });
    }

    // ── Unit system toggle ─────────────────────────────────────────

    function handleUnitToggle(e) {
        const system = e.currentTarget.dataset.unitSystem;
        const switchingToMetric = system === 'metric';
        if (switchingToMetric === isMetric) return;

        // Convert distance fields
        const distFactor = switchingToMetric ? MI_TO_KM : 1 / MI_TO_KM;
        convertInput(inputs.commute, distFactor, 1);
        convertInput(inputs.range, distFactor, 1);

        // MPG ↔ L/100km (reciprocal: both directions use same formula)
        if (inputs.gasMpg?.value) {
            const val = parseFloat(inputs.gasMpg.value);
            if (val > 0) {
                inputs.gasMpg.value = (MPG_TO_L100KM / val).toFixed(1);
            }
        }

        // $/gallon ↔ $/liter
        const fuelFactor = switchingToMetric ? 1 / GAL_TO_L : GAL_TO_L;
        convertInput(inputs.gasPrice, fuelFactor, 2);

        // mi/kWh ↔ km/kWh
        convertInput(inputs.evEfficiency, distFactor, 1);

        // $/mi ↔ $/km
        const rateFactor = switchingToMetric ? 1 / MI_TO_KM : MI_TO_KM;
        convertInput(inputs.rideshareRate, rateFactor, 2);

        isMetric = switchingToMetric;

        unitToggles.forEach(t => {
            t.classList.toggle('is-active', t.dataset.unitSystem === system);
        });

        updateLabels();
        calculate();
    }

    // ── Labels ─────────────────────────────────────────────────────

    function updateLabels() {
        // Unit suffixes
        const unitMap = {
            commute:         isMetric ? 'km' : 'mi',
            range:           isMetric ? 'km' : 'mi',
            'gas-mpg':       isMetric ? 'L/100km' : 'MPG',
            'gas-price':     isMetric ? '/L' : '/gal',
            'ev-efficiency': isMetric ? 'km/kWh' : 'mi/kWh',
            'rideshare-rate': isMetric ? '/km' : '/mi',
        };

        Object.entries(unitMap).forEach(([key, text]) => {
            const unitEl = container.querySelector(`[data-unit="${key}"]`);
            if (unitEl) unitEl.textContent = text;
        });

        // Placeholders
        const placeholders = {
            commute:      isMetric ? 'e.g. 8' : 'e.g. 5',
            range:        isMetric ? 'e.g. 35' : 'e.g. 22',
            'gas-mpg':    isMetric ? 'e.g. 9.4' : 'e.g. 25',
            'gas-price':  isMetric ? 'e.g. 1.80' : 'e.g. 3.50',
            'ev-efficiency': isMetric ? 'e.g. 5.6' : 'e.g. 3.5',
            'rideshare-rate': isMetric ? 'e.g. 1.55' : 'e.g. 2.50',
        };

        Object.entries(placeholders).forEach(([key, text]) => {
            const input = container.querySelector(`[data-input="${key}"]`);
            if (input) input.placeholder = text;
        });

        // Distance labels in results
        container.querySelectorAll('[data-distance-label]').forEach(el => {
            el.textContent = isMetric ? 'Per km' : 'Per Mile';
        });
    }

    // ── Comparison toggles ─────────────────────────────────────────

    function handleComparisonToggle(e) {
        const mode = e.currentTarget.dataset.comparisonToggle;

        if (enabled.has(mode)) {
            enabled.delete(mode);
        } else {
            enabled.add(mode);
        }

        e.currentTarget.classList.toggle('is-active', enabled.has(mode));

        // Show/hide input section
        if (sections[mode]) {
            sections[mode].hidden = !enabled.has(mode);
        }

        calculate();
    }

    // ── Reset ──────────────────────────────────────────────────────

    function reset() {
        // Reset unit system
        if (isMetric) {
            isMetric = false;
            unitToggles.forEach(t => {
                t.classList.toggle('is-active', t.dataset.unitSystem === 'imperial');
            });
        }

        // Reset inputs to defaults
        const defaultMap = {
            commute: DEFAULTS.commute,
            days: DEFAULTS.days,
            battery: DEFAULTS.battery,
            range: DEFAULTS.range,
            price: DEFAULTS.price,
            lifespan: DEFAULTS.lifespan,
            electricity: DEFAULTS.electricity,
            'gas-mpg': DEFAULTS.gasMpg,
            'gas-price': DEFAULTS.gasPrice,
            'ev-efficiency': DEFAULTS.evEfficiency,
            'transit-monthly': DEFAULTS.transitMonthly,
            'rideshare-rate': DEFAULTS.ridesharePerMile,
        };

        Object.entries(defaultMap).forEach(([key, val]) => {
            const input = container.querySelector(`[data-input="${key}"]`);
            if (input) input.value = val;
        });

        // Reset comparisons to gas only
        enabled.clear();
        enabled.add('gas');

        comparisonToggles.forEach(t => {
            const mode = t.dataset.comparisonToggle;
            t.classList.toggle('is-active', mode === 'gas');
        });

        Object.entries(sections).forEach(([mode, section]) => {
            if (section) section.hidden = mode !== 'gas';
        });

        updateLabels();
        calculate();

        const announcer = container.querySelector('[aria-live]');
        if (announcer) announcer.textContent = 'Calculator reset to defaults.';
    }

    // ── Accessibility ──────────────────────────────────────────────

    let announceTimeout;
    function announceResults(scooterAnnual, biggestSaving, biggestMode) {
        clearTimeout(announceTimeout);
        announceTimeout = setTimeout(() => {
            const announcer = container.querySelector('[aria-live]');
            if (!announcer) return;

            let msg = `E-scooter annual cost: ${formatMoney(scooterAnnual)}.`;
            if (biggestSaving > 1 && biggestMode) {
                msg += ` You save ${formatMoney(biggestSaving)} per year compared to ${biggestMode}.`;
            }
            announcer.textContent = msg;
        }, 500);
    }

    // ── Bind events ────────────────────────────────────────────────

    unitToggles.forEach(btn => btn.addEventListener('click', handleUnitToggle));
    comparisonToggles.forEach(btn => btn.addEventListener('click', handleComparisonToggle));
    if (resetBtn) resetBtn.addEventListener('click', reset);

    bindInputHandlers(container, calculate);

    // ── Initialize ─────────────────────────────────────────────────

    Object.entries(sections).forEach(([mode, section]) => {
        if (section) section.hidden = !enabled.has(mode);
    });

    updateLabels();
    calculate();
}
