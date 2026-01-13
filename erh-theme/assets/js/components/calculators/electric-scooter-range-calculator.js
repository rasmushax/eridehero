/**
 * Electric Scooter Range Calculator
 *
 * Estimates real-world range based on extensive testing of 50+ scooter models.
 *
 * Formula: Range = base_efficiency × battery_wh × temp_factor × weight_factor × speed_factor × cycle_factor
 *
 * Factors:
 * - Base efficiency: -0.008 × ln(motor_W) + 0.0899 (larger motors less efficient at cruise)
 * - Temperature: Penalty outside 0-35°C range
 * - Weight: Baseline 79kg, linear adjustment
 * - Speed: Slow +15%, Fast -15%
 * - Cycles: ~0.037% degradation per cycle
 */

import { formatNumber } from '../../utils/calculator-utils.js';

// Constants
const BASELINE_WEIGHT_KG = 79;
const LBS_TO_KG = 0.453592;
const KM_TO_MILES = 0.621371;

/**
 * Calculate temperature factor.
 * Smoother curve than original binary jumps.
 */
function getTempFactor(tempC) {
    // Optimal range: 10-30°C = 1.0
    if (tempC >= 10 && tempC <= 30) return 1.0;

    // Cold: linear decrease, steeper below 0
    if (tempC < 10) {
        if (tempC < 0) {
            // Below freezing: 0.8 at 0°C, down to 0.6 at -40°C
            return Math.max(0.6, 0.8 + tempC * 0.005);
        }
        // 0-10°C: gradual decrease from 1.0 to 0.8
        return 1 - (10 - tempC) * 0.02;
    }

    // Hot: gradual decrease above 30°C
    // 0.83 at 35°C, 0.75 at ~45°C
    return Math.max(0.75, 1 - (tempC - 30) * 0.034);
}

/**
 * Calculate weight factor.
 * Baseline: 79kg = 1.0
 */
function getWeightFactor(weightKg) {
    return 1.65 - 0.65 * (weightKg / BASELINE_WEIGHT_KG);
}

/**
 * Calculate speed factor.
 */
function getSpeedFactor(speed) {
    const factors = {
        slow: 1.15,
        medium: 1.0,
        fast: 0.85,
    };
    return factors[speed] || 1.0;
}

/**
 * Calculate base efficiency (miles per Wh) based on motor power.
 * Logarithmic curve: larger motors less efficient at cruising speeds.
 */
function getBaseEfficiency(motorW) {
    return -0.008 * Math.log(motorW) + 0.0899;
}

/**
 * Calculate battery degradation factor.
 * ~0.037% loss per cycle, linear model.
 */
function getCycleFactor(cycles) {
    return Math.max(0.5, 1 - (cycles * 0.0365) / 100);
}

/**
 * Main range calculation.
 */
function calculateRange(inputs) {
    const {
        weightKg,
        tempC,
        speed,
        motorW,
        batteryWh,
        cycles,
    } = inputs;

    // Calculate all factors
    const baseEfficiency = getBaseEfficiency(motorW);
    const tempFactor = getTempFactor(tempC);
    const weightFactor = getWeightFactor(weightKg);
    const speedFactor = getSpeedFactor(speed);
    const cycleFactor = getCycleFactor(cycles);

    // Final range in miles
    const rangeMiles = baseEfficiency * batteryWh * tempFactor * weightFactor * speedFactor * cycleFactor;

    return {
        rangeMiles: Math.max(0, rangeMiles),
        rangeKm: Math.max(0, rangeMiles / KM_TO_MILES),
        factors: {
            baseEfficiency,
            tempFactor,
            weightFactor,
            speedFactor,
            cycleFactor,
        },
    };
}

/**
 * Format range value.
 */
function formatRange(value) {
    if (!value || value <= 0) return '--';
    return formatNumber(Math.round(value * 10) / 10);
}

/**
 * Initialize the calculator.
 */
export function init(container) {
    // Unit system state
    let isMetric = false;

    // DOM elements
    const unitToggles = container.querySelectorAll('[data-unit-system]');
    const weightInput = container.querySelector('[data-input="weight"]');
    const weightLabel = container.querySelector('[data-label="weight"]');
    const weightUnit = container.querySelector('[data-unit="weight"]');
    const tempInput = container.querySelector('[data-input="temp"]');
    const tempLabel = container.querySelector('[data-label="temp"]');
    const tempUnit = container.querySelector('[data-unit="temp"]');
    const speedSelect = container.querySelector('[data-input="speed"]');
    const motorInput = container.querySelector('[data-input="motor"]');
    const batteryInput = container.querySelector('[data-input="battery"]');
    const cyclesInput = container.querySelector('[data-input="cycles"]');
    const resultValue = container.querySelector('[data-result="range"]');
    const resultUnit = container.querySelector('[data-result="unit"]');
    const resetBtn = container.querySelector('[data-calculator-reset]');

    // Factor display elements (optional)
    const factorEls = {
        temp: container.querySelector('[data-factor="temp"]'),
        weight: container.querySelector('[data-factor="weight"]'),
        speed: container.querySelector('[data-factor="speed"]'),
        cycles: container.querySelector('[data-factor="cycles"]'),
    };

    /**
     * Update unit labels based on system.
     */
    function updateUnits() {
        if (weightLabel) {
            weightLabel.textContent = isMetric ? 'Rider Weight (kg)' : 'Rider Weight (lbs)';
        }
        if (weightUnit) {
            weightUnit.textContent = isMetric ? 'kg' : 'lbs';
        }
        if (weightInput) {
            weightInput.placeholder = isMetric ? 'e.g. 75' : 'e.g. 165';
            weightInput.min = isMetric ? 22 : 50;
            weightInput.max = isMetric ? 200 : 440;
        }

        if (tempLabel) {
            tempLabel.textContent = isMetric ? 'Temperature (°C)' : 'Temperature (°F)';
        }
        if (tempUnit) {
            tempUnit.textContent = isMetric ? '°C' : '°F';
        }
        if (tempInput) {
            tempInput.placeholder = isMetric ? 'e.g. 20' : 'e.g. 68';
        }

        if (resultUnit) {
            resultUnit.textContent = isMetric ? 'km' : 'miles';
        }
    }

    /**
     * Get current input values, normalized to metric.
     */
    function getInputs() {
        let weightKg = parseFloat(weightInput?.value) || 0;
        let tempC = parseFloat(tempInput?.value) || 0;

        // Convert to metric if needed
        if (!isMetric) {
            weightKg = weightKg * LBS_TO_KG;
            tempC = (tempC - 32) / 1.8;
        }

        return {
            weightKg,
            tempC,
            speed: speedSelect?.value || 'medium',
            motorW: parseFloat(motorInput?.value) || 0,
            batteryWh: parseFloat(batteryInput?.value) || 0,
            cycles: parseFloat(cyclesInput?.value) || 0,
        };
    }

    /**
     * Validate inputs.
     */
    function validateInputs(inputs) {
        return (
            inputs.weightKg > 0 &&
            inputs.motorW > 0 &&
            inputs.batteryWh > 0
        );
    }

    /**
     * Update factor display.
     */
    function updateFactors(factors) {
        if (factorEls.temp && factors) {
            const pct = Math.round(factors.tempFactor * 100);
            factorEls.temp.textContent = pct === 100 ? 'Optimal' : `${pct}%`;
            factorEls.temp.className = pct === 100 ? 'factor-value factor-value--good' : 'factor-value factor-value--warn';
        }
        if (factorEls.weight && factors) {
            const pct = Math.round(factors.weightFactor * 100);
            factorEls.weight.textContent = `${pct}%`;
            factorEls.weight.className = pct >= 100 ? 'factor-value factor-value--good' : 'factor-value factor-value--neutral';
        }
        if (factorEls.speed && factors) {
            const pct = Math.round(factors.speedFactor * 100);
            factorEls.speed.textContent = pct > 100 ? `+${pct - 100}%` : pct < 100 ? `${pct - 100}%` : 'Baseline';
        }
        if (factorEls.cycles && factors) {
            const pct = Math.round(factors.cycleFactor * 100);
            factorEls.cycles.textContent = pct === 100 ? '100%' : `${pct}%`;
            factorEls.cycles.className = pct >= 90 ? 'factor-value factor-value--good' : 'factor-value factor-value--warn';
        }
    }

    /**
     * Main calculate function.
     */
    function calculate() {
        const inputs = getInputs();

        if (!validateInputs(inputs)) {
            if (resultValue) resultValue.textContent = '--';
            updateFactors(null);
            return;
        }

        const result = calculateRange(inputs);

        if (resultValue) {
            const displayRange = isMetric ? result.rangeKm : result.rangeMiles;
            resultValue.textContent = formatRange(displayRange);
        }

        updateFactors(result.factors);
        announceResult(isMetric ? result.rangeKm : result.rangeMiles);
    }

    /**
     * Screen reader announcement.
     */
    let announceTimeout;
    function announceResult(range) {
        clearTimeout(announceTimeout);
        announceTimeout = setTimeout(() => {
            const announcer = container.querySelector('[aria-live]');
            if (announcer && range > 0) {
                const unit = isMetric ? 'kilometers' : 'miles';
                announcer.textContent = `Estimated range: ${formatRange(range)} ${unit}.`;
            }
        }, 500);
    }

    /**
     * Reset calculator.
     */
    function reset() {
        // Reset inputs
        if (weightInput) weightInput.value = '';
        if (tempInput) tempInput.value = '';
        if (speedSelect) speedSelect.value = 'medium';
        if (motorInput) motorInput.value = '';
        if (batteryInput) batteryInput.value = '';
        if (cyclesInput) cyclesInput.value = '1';

        // Reset result
        if (resultValue) resultValue.textContent = '--';

        // Reset factors
        Object.values(factorEls).forEach(el => {
            if (el) el.textContent = '--';
        });

        // Announce
        const announcer = container.querySelector('[aria-live]');
        if (announcer) {
            announcer.textContent = 'Calculator reset.';
        }
    }

    /**
     * Handle unit system toggle.
     */
    function handleUnitToggle(e) {
        const btn = e.currentTarget;
        const system = btn.dataset.unitSystem;
        const switchingToMetric = system === 'metric';

        // Convert existing values before switching
        if (switchingToMetric !== isMetric) {
            // Weight conversion
            if (weightInput && weightInput.value) {
                const currentWeight = parseFloat(weightInput.value);
                if (switchingToMetric) {
                    // lbs to kg
                    weightInput.value = Math.round(currentWeight * LBS_TO_KG);
                } else {
                    // kg to lbs
                    weightInput.value = Math.round(currentWeight / LBS_TO_KG);
                }
            }

            // Temperature conversion
            if (tempInput && tempInput.value) {
                const currentTemp = parseFloat(tempInput.value);
                if (switchingToMetric) {
                    // °F to °C
                    tempInput.value = Math.round((currentTemp - 32) / 1.8);
                } else {
                    // °C to °F
                    tempInput.value = Math.round(currentTemp * 1.8 + 32);
                }
            }
        }

        isMetric = switchingToMetric;

        // Update toggle states
        unitToggles.forEach(toggle => {
            toggle.classList.toggle('is-active', toggle.dataset.unitSystem === system);
        });

        // Update labels
        updateUnits();

        // Recalculate with new units
        calculate();
    }

    // Bind events
    unitToggles.forEach(btn => {
        btn.addEventListener('click', handleUnitToggle);
    });

    [weightInput, tempInput, motorInput, batteryInput, cyclesInput].forEach(input => {
        if (input) {
            input.addEventListener('input', calculate);
        }
    });

    if (speedSelect) {
        speedSelect.addEventListener('change', calculate);
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', reset);
    }

    // Initialize
    updateUnits();
}
