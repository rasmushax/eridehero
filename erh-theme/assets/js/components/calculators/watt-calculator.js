/**
 * Watt Calculator
 *
 * Circular calculator for Voltage, Current, Resistance, and Power.
 * Fill any 2 fields → auto-computes the other 2.
 * Uses "last-2-touched" input tracking for intuitive UX.
 *
 * Formulas (from Ohm's Law + Power Law):
 * V = I × R       | I = V / R       | R = V / I       | P = V × I
 * P = I² × R      | P = V² / R      | V = √(P × R)    | I = √(P / R)
 * V = P / I       | I = P / V       | R = V² / P       | R = P / I²
 */

import { formatNumber } from '../../utils/calculator-utils.js';

/**
 * Unit definitions with conversion factors to base units.
 * Base units: V (volts), A (amps), Ω (ohms), W (watts)
 */
const UNITS = {
    voltage: [
        { value: 'μV', label: 'μV', factor: 1e-6 },
        { value: 'mV', label: 'mV', factor: 1e-3 },
        { value: 'V',  label: 'V',  factor: 1 },
        { value: 'kV', label: 'kV', factor: 1e3 },
        { value: 'MV', label: 'MV', factor: 1e6 },
    ],
    current: [
        { value: 'μA', label: 'μA', factor: 1e-6 },
        { value: 'mA', label: 'mA', factor: 1e-3 },
        { value: 'A',  label: 'A',  factor: 1 },
        { value: 'kA', label: 'kA', factor: 1e3 },
    ],
    resistance: [
        { value: 'mΩ', label: 'mΩ', factor: 1e-3 },
        { value: 'Ω',  label: 'Ω',  factor: 1 },
        { value: 'kΩ', label: 'kΩ', factor: 1e3 },
        { value: 'MΩ', label: 'MΩ', factor: 1e6 },
    ],
    power: [
        { value: 'μW', label: 'μW', factor: 1e-6 },
        { value: 'mW', label: 'mW', factor: 1e-3 },
        { value: 'W',  label: 'W',  factor: 1 },
        { value: 'kW', label: 'kW', factor: 1e3 },
        { value: 'MW', label: 'MW', factor: 1e6 },
        { value: 'HP', label: 'HP', factor: 745.7 },
    ],
};

/** Field order matches UI: voltage, current, resistance, power */
const FIELDS = ['voltage', 'current', 'resistance', 'power'];

/**
 * Solve for missing fields given the two known ones.
 * All values in base units (V, A, Ω, W).
 * Returns null for any result that can't be computed (division by zero, negative sqrt).
 */
function solve(known) {
    const keys = Object.keys(known);
    if (keys.length < 2) return null;

    // Use the two most recent inputs
    const [a, b] = keys.slice(-2);
    const pair = [a, b].sort().join('+');
    const vals = known;
    const result = {};

    switch (pair) {
        case 'current+voltage': // V, I known
            result.power = vals.voltage * vals.current;
            result.resistance = vals.current !== 0 ? vals.voltage / vals.current : null;
            break;

        case 'resistance+voltage': // V, R known
            result.current = vals.resistance !== 0 ? vals.voltage / vals.resistance : null;
            result.power = vals.resistance !== 0 ? (vals.voltage ** 2) / vals.resistance : null;
            break;

        case 'power+voltage': // V, P known
            result.current = vals.voltage !== 0 ? vals.power / vals.voltage : null;
            result.resistance = vals.power !== 0 ? (vals.voltage ** 2) / vals.power : null;
            break;

        case 'current+resistance': // I, R known
            result.voltage = vals.current * vals.resistance;
            result.power = (vals.current ** 2) * vals.resistance;
            break;

        case 'current+power': // I, P known
            result.voltage = vals.current !== 0 ? vals.power / vals.current : null;
            result.resistance = vals.current !== 0 ? vals.power / (vals.current ** 2) : null;
            break;

        case 'power+resistance': // R, P known
            {
                const vSquared = vals.power * vals.resistance;
                result.voltage = vSquared >= 0 ? Math.sqrt(vSquared) : null;
                const iSquared = vals.resistance !== 0 ? vals.power / vals.resistance : null;
                result.current = iSquared !== null && iSquared >= 0 ? Math.sqrt(iSquared) : null;
            }
            break;

        default:
            return null;
    }

    return result;
}

/**
 * Format a value for display, adapting decimal places to magnitude.
 */
function formatValue(num) {
    if (num === null || num === undefined || isNaN(num) || !isFinite(num)) return '--';

    // For very small or very large numbers, use scientific notation
    if (Math.abs(num) > 0 && (Math.abs(num) < 0.001 || Math.abs(num) >= 1e9)) {
        return num.toExponential(3);
    }

    // Determine appropriate decimal places based on magnitude
    let decimals;
    const abs = Math.abs(num);
    if (abs >= 1000) decimals = 1;
    else if (abs >= 1) decimals = 3;
    else decimals = 4;

    const formatted = parseFloat(num.toFixed(decimals));
    return formatNumber(formatted, decimals).replace(/\.?0+$/, '');
}

/**
 * Initialize the watt calculator.
 */
export function init(container) {
    // Input history: ordered list of field names, most recent last
    let inputHistory = [];

    // Track which fields are user-edited vs computed
    const userEdited = new Set();

    // Suppress recalculation during programmatic updates
    let suppressCalc = false;

    // DOM references
    const fields = {};
    FIELDS.forEach(name => {
        fields[name] = {
            input: container.querySelector(`[data-input="${name}"]`),
            select: container.querySelector(`[data-unit="${name}"]`),
            group: container.querySelector(`[data-field="${name}"]`),
        };
    });

    const resetBtn = container.querySelector('[data-calculator-reset]');
    const formulaEl = container.querySelector('[data-active-formula]');

    /**
     * Get the conversion factor for a field's currently selected unit.
     */
    function getUnitFactor(fieldName) {
        const select = fields[fieldName].select;
        if (!select) return 1;
        const unitDef = UNITS[fieldName].find(u => u.value === select.value);
        return unitDef ? unitDef.factor : 1;
    }

    /**
     * Convert a display value to base unit.
     */
    function toBase(fieldName, displayValue) {
        return displayValue * getUnitFactor(fieldName);
    }

    /**
     * Convert a base unit value to display unit.
     */
    function fromBase(fieldName, baseValue) {
        const factor = getUnitFactor(fieldName);
        return factor !== 0 ? baseValue / factor : 0;
    }

    /**
     * Update visual state: highlight computed fields.
     */
    function updateFieldStates() {
        FIELDS.forEach(name => {
            const group = fields[name].group;
            if (!group) return;
            const isComputed = !userEdited.has(name) && fields[name].input?.value !== '';
            group.classList.toggle('is-computed', isComputed);
        });
    }

    /**
     * Show the active formula being used.
     */
    function updateFormula(inputKeys) {
        if (!formulaEl) return;
        const pair = inputKeys.slice(-2).sort().join('+');
        const formulas = {
            'current+voltage':    'P = V × I  |  R = V ÷ I',
            'resistance+voltage': 'I = V ÷ R  |  P = V² ÷ R',
            'power+voltage':      'I = P ÷ V  |  R = V² ÷ P',
            'current+resistance': 'V = I × R  |  P = I² × R',
            'current+power':      'V = P ÷ I  |  R = P ÷ I²',
            'power+resistance':   'V = √(P × R)  |  I = √(P ÷ R)',
        };
        formulaEl.textContent = formulas[pair] || 'Enter any two values to calculate the rest';
    }

    /**
     * Main calculation.
     */
    function calculate() {
        if (suppressCalc) return;

        // Gather known values (only user-edited fields)
        const known = {};
        const knownKeys = [];

        // Use inputHistory order so the last 2 are the "active" inputs
        inputHistory.forEach(name => {
            if (!userEdited.has(name)) return;
            const input = fields[name].input;
            const val = parseFloat(input?.value);
            if (!isNaN(val) && val !== 0) {
                known[name] = toBase(name, val);
                knownKeys.push(name);
            }
        });

        // Need at least 2 known values
        if (knownKeys.length < 2) {
            // Clear computed fields
            FIELDS.forEach(name => {
                if (!userEdited.has(name)) {
                    const input = fields[name].input;
                    if (input) input.value = '';
                }
            });
            updateFormula([]);
            updateFieldStates();
            return;
        }

        const result = solve(known);
        if (!result) return;

        updateFormula(knownKeys);

        // Fill in computed fields
        suppressCalc = true;
        FIELDS.forEach(name => {
            if (userEdited.has(name)) return;
            if (result[name] === undefined) return;

            const input = fields[name].input;
            if (!input) return;

            if (result[name] === null || !isFinite(result[name])) {
                input.value = '';
            } else {
                const displayVal = fromBase(name, result[name]);
                input.value = formatValue(displayVal);
            }
        });
        suppressCalc = false;

        updateFieldStates();
        announceResults(knownKeys, result);
    }

    /**
     * Handle user input on a field.
     */
    function handleInput(fieldName) {
        const input = fields[fieldName].input;
        const val = input?.value.trim();

        if (val === '' || isNaN(parseFloat(val))) {
            // User cleared the field — remove from tracking
            userEdited.delete(fieldName);
            inputHistory = inputHistory.filter(n => n !== fieldName);
        } else {
            // Add/move to end of history
            inputHistory = inputHistory.filter(n => n !== fieldName);
            inputHistory.push(fieldName);
            userEdited.add(fieldName);

            // If more than 2 user-edited fields, demote the oldest
            while (userEdited.size > 2) {
                const oldest = inputHistory.find(n => userEdited.has(n));
                if (oldest) {
                    userEdited.delete(oldest);
                }
            }
        }

        calculate();
    }

    /**
     * Handle unit change on a field.
     */
    function handleUnitChange(fieldName) {
        // If this field has a computed value, recalculate to update its display unit
        // If it's a user-input field, the base value changes so recalculate outputs
        calculate();
    }

    /**
     * Screen reader announcement.
     */
    let announceTimeout;
    function announceResults(inputKeys, result) {
        clearTimeout(announceTimeout);
        announceTimeout = setTimeout(() => {
            const announcer = container.querySelector('[aria-live]');
            if (!announcer) return;

            const parts = [];
            FIELDS.forEach(name => {
                if (inputKeys.includes(name)) return;
                if (result[name] === null || result[name] === undefined) return;
                const displayVal = fromBase(name, result[name]);
                const unit = fields[name].select?.value || '';
                parts.push(`${name}: ${formatValue(displayVal)} ${unit}`);
            });

            if (parts.length > 0) {
                announcer.textContent = `Calculated: ${parts.join(', ')}.`;
            }
        }, 500);
    }

    /**
     * Reset all fields.
     */
    function reset() {
        suppressCalc = true;
        FIELDS.forEach(name => {
            const input = fields[name].input;
            if (input) input.value = '';

            // Reset to default units
            const select = fields[name].select;
            if (select) {
                const defaults = { voltage: 'V', current: 'A', resistance: 'Ω', power: 'W' };
                select.value = defaults[name];
            }
        });
        suppressCalc = false;

        inputHistory = [];
        userEdited.clear();

        if (formulaEl) {
            formulaEl.textContent = 'Enter any two values to calculate the rest';
        }

        updateFieldStates();

        const announcer = container.querySelector('[aria-live]');
        if (announcer) {
            announcer.textContent = 'Calculator reset.';
        }
    }

    // Bind input events
    FIELDS.forEach(name => {
        const input = fields[name].input;
        if (input) {
            input.addEventListener('input', () => handleInput(name));
        }

        const select = fields[name].select;
        if (select) {
            select.addEventListener('change', () => handleUnitChange(name));
        }
    });

    // Bind reset
    if (resetBtn) {
        resetBtn.addEventListener('click', reset);
    }

    // Initialize formula text
    if (formulaEl) {
        formulaEl.textContent = 'Enter any two values to calculate the rest';
    }
}
