/**
 * Charging Time Calculator
 *
 * Estimates time required to charge a battery based on:
 * - Battery capacity (Wh, kWh, Ah)
 * - Battery voltage (when using Wh/kWh)
 * - Charging current (A)
 * - Current state of charge (SoC)
 *
 * Formula:
 * Capacity (Ah) = Capacity (Wh) / Voltage (V)
 * Remaining Capacity = Capacity * (1 - SoC/100)
 * Charging Time (hours) = Remaining Capacity (Ah) / Charging Current (A)
 */

import {
    formatNumber,
    getInputValues,
    setResults,
    bindInputHandlers,
    clamp,
} from '../../utils/calculator-utils.js';

// Default values for reset
const DEFAULTS = {
    capacity: 500,
    capacityUnit: 'Wh',
    voltage: 48,
    current: 2,
    soc: 0,
};

/**
 * Calculate charging time.
 */
function calculateChargingTime(capacity, capacityUnit, voltage, current, soc) {
    // Convert capacity to Ah
    let capacityAh;
    switch (capacityUnit) {
        case 'kWh':
            capacityAh = (capacity * 1000) / voltage;
            break;
        case 'Wh':
            capacityAh = capacity / voltage;
            break;
        case 'Ah':
        default:
            capacityAh = capacity;
            break;
    }

    // Calculate remaining capacity based on SoC
    const remainingCapacity = capacityAh * (1 - soc / 100);

    // Calculate charging time in hours
    const chargingTimeHours = current > 0 ? remainingCapacity / current : 0;

    // Convert to hours and minutes
    const hours = Math.floor(chargingTimeHours);
    const minutes = Math.round((chargingTimeHours - hours) * 60);

    // Handle edge case where minutes round to 60
    const finalHours = minutes === 60 ? hours + 1 : hours;
    const finalMinutes = minutes === 60 ? 0 : minutes;

    return {
        hours: finalHours,
        minutes: finalMinutes,
        totalMinutes: Math.round(chargingTimeHours * 60),
        capacityAh,
        remainingCapacity,
    };
}

/**
 * Format time output.
 */
function formatTime(hours, minutes) {
    if (hours === 0 && minutes === 0) {
        return '--';
    }

    const parts = [];
    if (hours > 0) {
        parts.push(`${hours} ${hours === 1 ? 'hour' : 'hours'}`);
    }
    if (minutes > 0 || hours === 0) {
        parts.push(`${minutes} ${minutes === 1 ? 'min' : 'mins'}`);
    }
    return parts.join(' ');
}

/**
 * Initialize the charging time calculator.
 */
export function init(container) {
    const voltageGroup = container.querySelector('[data-voltage-group]');
    const capacityUnitSelect = container.querySelector('[data-input="capacityUnit"]');
    const resetBtn = container.querySelector('[data-calculator-reset]');

    /**
     * Show/hide voltage field based on capacity unit.
     */
    function updateVoltageVisibility() {
        const unit = capacityUnitSelect?.value || 'Wh';
        const needsVoltage = unit === 'Wh' || unit === 'kWh';

        if (voltageGroup) {
            voltageGroup.style.display = needsVoltage ? '' : 'none';
            // Update required state
            const voltageInput = voltageGroup.querySelector('[data-input="voltage"]');
            if (voltageInput) {
                voltageInput.required = needsVoltage;
            }
        }
    }

    /**
     * Main calculation function.
     */
    function calculate() {
        const values = getInputValues(container);

        const capacity = values.capacity || 0;
        const capacityUnit = capacityUnitSelect?.value || 'Wh';
        const voltage = values.voltage || 48;
        const current = values.current || 0;
        const soc = clamp(values.soc || 0, 0, 100);

        // Validate required fields
        const needsVoltage = capacityUnit === 'Wh' || capacityUnit === 'kWh';
        if (!capacity || !current || (needsVoltage && !voltage)) {
            setResults(container, {
                'charging-time': '--',
                'remaining-capacity': '--',
            });
            return;
        }

        const result = calculateChargingTime(
            capacity,
            capacityUnit,
            voltage,
            current,
            soc
        );

        // Update results
        setResults(container, {
            'charging-time': formatTime(result.hours, result.minutes),
            'remaining-capacity': formatNumber(result.remainingCapacity.toFixed(2)) + ' Ah',
        });

        // Announce for screen readers
        announceResult(result.hours, result.minutes);
    }

    // Debounced announcer
    let announceTimeout;
    function announceResult(hours, minutes) {
        clearTimeout(announceTimeout);
        announceTimeout = setTimeout(() => {
            const announcer = container.querySelector('[aria-live]');
            if (announcer && (hours > 0 || minutes > 0)) {
                announcer.textContent = `Estimated charging time: ${formatTime(hours, minutes)}.`;
            }
        }, 500);
    }

    /**
     * Reset calculator to defaults.
     */
    function resetCalculator() {
        const capacityInput = container.querySelector('[data-input="capacity"]');
        const voltageInput = container.querySelector('[data-input="voltage"]');
        const currentInput = container.querySelector('[data-input="current"]');
        const socInput = container.querySelector('[data-input="soc"]');

        if (capacityInput) capacityInput.value = DEFAULTS.capacity;
        if (voltageInput) voltageInput.value = DEFAULTS.voltage;
        if (currentInput) currentInput.value = DEFAULTS.current;
        if (socInput) socInput.value = DEFAULTS.soc;

        if (capacityUnitSelect) {
            capacityUnitSelect.value = DEFAULTS.capacityUnit;
            capacityUnitSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        updateVoltageVisibility();
        calculate();

        const announcer = container.querySelector('[aria-live]');
        if (announcer) {
            announcer.textContent = 'Calculator reset to default values.';
        }
    }

    // Bind capacity unit change
    if (capacityUnitSelect) {
        capacityUnitSelect.addEventListener('change', () => {
            updateVoltageVisibility();
            calculate();
        });
    }

    // Bind reset button
    if (resetBtn) {
        resetBtn.addEventListener('click', resetCalculator);
    }

    // Initialize
    updateVoltageVisibility();
    bindInputHandlers(container, calculate);
    calculate();
}
