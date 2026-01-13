/**
 * Battery Degradation Calculator
 *
 * Estimates battery capacity degradation based on research-backed model:
 * - Calendar aging follows √t (square root of time) - SEI layer growth
 * - Cycle aging ~0.015% per cycle (1000-2000 cycles to 80%)
 * - Temperature factor based on Geotab EV study
 * - Depth of discharge multiplier
 *
 * Sources:
 * - Geotab 2025 EV Battery Health Study (22,700 vehicles)
 * - NREL Battery Life Model
 * - E-bike industry degradation data
 */

import {
    formatNumber,
    formatPercent,
    getInputValues,
    setResults,
    bindInputHandlers,
    clamp,
} from '../../utils/calculator-utils.js';

// Research-backed constants
const CONFIG = {
    // Calendar aging: 2.3% at year 1, scales with √t (Geotab study average)
    calendarBase: 2.3,

    // Cycle aging: ~0.015% per cycle (80% capacity at ~1333 cycles average)
    cycleRate: 0.015,

    // Optimal temperature (21°C / 70°F) - Geotab study
    optimalTempC: 21,

    // Temperature penalty: +2% degradation rate per °C above optimal
    tempPenaltyPerC: 0.02,

    // DoD multipliers (relative to 20-80% baseline)
    dodFactors: {
        'conservative': 0.6,  // 30-70% - minimal stress
        'moderate': 1.0,      // 20-80% - recommended baseline
        'full': 1.8,          // 10-90% - increased wear
        'extreme': 2.5,       // 0-100% - maximum stress
    },

    // Overlapping mechanism factor (research shows ~85% of sum)
    overlapFactor: 0.85,

    // Maximum degradation cap
    maxLoss: 40,

    // Assumed cycles per year for projections
    cyclesPerYear: 250,
};

/**
 * Calculate battery degradation using research-backed model.
 */
function calculateDegradation(capacity, age, cycles, tempC, dodLevel) {
    // Calendar aging: √t relationship (diffusion-limited SEI growth)
    const calendarLoss = age > 0 ? CONFIG.calendarBase * Math.sqrt(age) : 0;

    // Cycle aging with DoD factor
    const dodFactor = CONFIG.dodFactors[dodLevel] || 1.0;
    const cycleLoss = cycles * CONFIG.cycleRate * dodFactor;

    // Temperature factor (penalty for temps above optimal)
    const tempDelta = tempC - CONFIG.optimalTempC;
    const tempFactor = 1 + Math.max(0, tempDelta * CONFIG.tempPenaltyPerC);

    // Combined with overlap factor
    const rawLoss = (calendarLoss + cycleLoss) * tempFactor * CONFIG.overlapFactor;
    const totalLossPercent = clamp(rawLoss, 0, CONFIG.maxLoss);

    // Current capacity
    const remainingPercent = 100 - totalLossPercent;
    const currentCapacity = capacity * (remainingPercent / 100);

    // Project future years
    const projections = [];
    for (let year = 1; year <= 5; year++) {
        const futureAge = age + year;
        const futureCycles = cycles + (year * CONFIG.cyclesPerYear);

        const futureCalendarLoss = CONFIG.calendarBase * Math.sqrt(futureAge);
        const futureCycleLoss = futureCycles * CONFIG.cycleRate * dodFactor;
        const futureRawLoss = (futureCalendarLoss + futureCycleLoss) * tempFactor * CONFIG.overlapFactor;
        const futureLoss = clamp(futureRawLoss, 0, CONFIG.maxLoss);

        const futurePercent = 100 - futureLoss;
        const futureCapacity = capacity * (futurePercent / 100);

        projections.push({
            year: futureAge,
            percent: futurePercent,
            capacity: futureCapacity,
        });
    }

    return {
        currentPercent: remainingPercent,
        currentCapacity,
        calendarLoss: calendarLoss * CONFIG.overlapFactor,
        cycleLoss: cycleLoss * tempFactor * CONFIG.overlapFactor,
        tempFactor,
        totalLoss: totalLossPercent,
        projections,
    };
}

/**
 * Get health status based on remaining percentage.
 */
function getHealthStatus(percent) {
    if (percent >= 90) {
        return { label: 'Excellent', class: 'success' };
    } else if (percent >= 80) {
        return { label: 'Good', class: 'success' };
    } else if (percent >= 70) {
        return { label: 'Fair', class: 'warning' };
    } else {
        return { label: 'Poor', class: 'error' };
    }
}

/**
 * Convert Fahrenheit to Celsius.
 */
function fToC(f) {
    return (f - 32) * 5 / 9;
}

/**
 * Convert Celsius to Fahrenheit.
 */
function cToF(c) {
    return (c * 9 / 5) + 32;
}

/**
 * Initialize the battery degradation calculator.
 */
export function init(container) {
    let useCelsius = true;
    const tempInput = container.querySelector('[data-input="temp"]');
    const tempUnitBtns = container.querySelectorAll('[data-temp-unit]');
    const tempUnitLabel = container.querySelector('[data-temp-unit-label]');

    function updateTempUnit(toCelsius) {
        if (useCelsius === toCelsius) return;

        const currentValue = parseFloat(tempInput?.value) || (useCelsius ? 21 : 70);
        const newValue = toCelsius ? fToC(currentValue) : cToF(currentValue);

        useCelsius = toCelsius;

        if (tempInput) {
            tempInput.value = Math.round(newValue);
            tempInput.min = toCelsius ? -10 : 14;
            tempInput.max = toCelsius ? 45 : 113;
        }

        if (tempUnitLabel) {
            tempUnitLabel.textContent = toCelsius ? '°C' : '°F';
        }

        // Update button states
        tempUnitBtns.forEach(btn => {
            const isCelsius = btn.dataset.tempUnit === 'c';
            btn.classList.toggle('is-active', isCelsius === toCelsius);
            btn.setAttribute('aria-pressed', isCelsius === toCelsius);
        });

        calculate();
    }

    function calculate() {
        const values = getInputValues(container);

        const capacity = values.capacity || 500;
        const age = values.age || 0;
        const cycles = values.cycles || 0;

        // Get temperature in Celsius
        let tempC = values.temp || 21;
        if (!useCelsius) {
            tempC = fToC(tempC);
        }

        // Get DoD level from select
        const dodSelect = container.querySelector('[data-input="dod"]');
        const dodLevel = dodSelect?.value || 'moderate';

        const result = calculateDegradation(capacity, age, cycles, tempC, dodLevel);
        const status = getHealthStatus(result.currentPercent);

        // Update main results
        setResults(container, {
            'current-percent': formatPercent(result.currentPercent, 0),
            'current-capacity': formatNumber(Math.round(result.currentCapacity)) + ' Wh',
            'health-status': status.label,
            'calendar-loss': formatPercent(result.calendarLoss, 1),
            'cycle-loss': formatPercent(result.cycleLoss, 1),
            'total-loss': formatPercent(result.totalLoss, 1),
        });

        // Update projections table with actual total ages
        result.projections.forEach((proj, i) => {
            setResults(container, {
                [`year${i + 1}-age`]: `Year ${proj.year}`,
                [`year${i + 1}-capacity`]: formatNumber(Math.round(proj.capacity)) + ' Wh',
                [`year${i + 1}-percent`]: formatPercent(proj.percent, 0),
            });
        });

        // Update status styling
        const statusEl = container.querySelector('[data-result="health-status"]');
        if (statusEl) {
            statusEl.className = `result-value result-value--${status.class}`;
        }
    }

    // Bind temperature unit toggle
    tempUnitBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            updateTempUnit(btn.dataset.tempUnit === 'c');
        });
    });

    // Bind input handlers and run initial calculation
    bindInputHandlers(container, calculate);
    calculate();
}
