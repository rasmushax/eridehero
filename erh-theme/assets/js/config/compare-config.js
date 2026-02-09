/**
 * Compare Config - Comparison Utilities
 *
 * Contains ranking arrays and utility functions for value comparison and formatting.
 *
 * NOTE: Spec groups, category weights, and thresholds are now defined in PHP
 * (SpecConfig class) and injected via window.erhData.specConfig.
 * This file only contains the JS-side comparison and formatting utilities.
 *
 * @module config/compare-config
 */

// =============================================================================
// Ranking Arrays
// =============================================================================
// These define the ordering for non-numeric comparisons.
// Higher index = better value (used by compareValues function).

/**
 * IP rating ranking (higher index = better).
 * NOTE: This array is kept for backward compatibility, but use normalizeIpRating()
 * for accurate comparisons.
 */
export const IP_RATINGS = [
    'None', 'IPX4', 'IP54', 'IPX5', 'IP55', 'IPX6', 'IP56', 'IP65', 'IP66', 'IP67', 'IP68',
];

/**
 * Normalize IP rating to numeric score for comparison.
 *
 * Comparison rules:
 * 1. Water rating (second digit) is primary - higher wins
 * 2. If water equal, having dust rating (IP) beats no dust (IPX)
 *
 * Returns composite score: water*10 + (has_dust ? 1 : 0)
 *
 * Examples:
 * - IPX5 (50) > IP54 (41) — water 5 > water 4
 * - IP55 (51) > IPX5 (50) — both water 5, but IP55 has dust rating
 *
 * @param {string} rating - IP rating string (e.g., "IP54", "IPX5")
 * @returns {number} Composite score (0-81), or 0 if invalid
 */
export function normalizeIpRating(rating) {
    if (!rating) return 0;

    const match = String(rating).toUpperCase().match(/^IP([X0-9])([0-9])$/);
    if (!match) return 0;

    const dustChar = match[1];
    const water = parseInt(match[2], 10);
    const hasDust = dustChar !== 'X';

    return (water * 10) + (hasDust ? 1 : 0);
}

/**
 * Suspension type ranking (higher index = better).
 */
export const SUSPENSION_TYPES = [
    'None', 'Front only', 'Rear only', 'Dual spring', 'Dual hydraulic', 'Full suspension',
];

/**
 * Tire type ranking.
 */
export const TIRE_TYPES = [
    'Solid', 'Honeycomb', 'Semi-pneumatic', 'Pneumatic', 'Tubeless pneumatic',
];

/**
 * Brake type ranking (higher index = better).
 */
export const BRAKE_TYPES = [
    'None', 'Foot', 'Drum', 'Disc (Mechanical)', 'Disc (Hydraulic)',
];

/**
 * Display type ranking (higher index = better).
 */
export const DISPLAY_TYPES = [
    'None', 'Unknown', 'LED', 'LCD', 'OLED', 'TFT',
];

/**
 * Threshold for declaring a "tie" (percentage difference).
 * If two values are within this percentage, neither wins.
 * Must match PHP SpecConfig::TIE_THRESHOLD (3%).
 */
export const TIE_THRESHOLD = 3;

// =============================================================================
// E-Bike Score Category Labels
// =============================================================================

/**
 * E-Bike score category labels and icons.
 * Used for radar chart and score display.
 */
export const EBIKE_SCORE_CATEGORIES = {
    motor_drive: {
        label: 'Motor & Drive',
        shortLabel: 'Motor',
        icon: 'zap',
        tooltip: 'Based on torque, motor brand quality, motor position, sensor type, and power.',
    },
    battery_range: {
        label: 'Battery & Range',
        shortLabel: 'Battery',
        icon: 'battery',
        tooltip: 'Based on battery capacity, range, cell quality, charge time, and removability.',
    },
    component_quality: {
        label: 'Component Quality',
        shortLabel: 'Components',
        icon: 'settings',
        tooltip: 'Based on brake brand, drivetrain brand, tire brand, frame material, IP rating, and certifications.',
    },
    comfort: {
        label: 'Comfort',
        shortLabel: 'Comfort',
        icon: 'smile',
        tooltip: 'Based on front/rear suspension type and travel, and seatpost suspension.',
    },
    practicality: {
        label: 'Practicality',
        shortLabel: 'Practical',
        icon: 'box',
        tooltip: 'Based on weight, folding, lights, accessories, display, app connectivity, and throttle.',
    },
};

// =============================================================================
// Utility Functions
// =============================================================================

/**
 * Get a nested value from an object using dot notation.
 * e.g., getNestedValue(obj, 'motor.power_nominal') returns obj.motor.power_nominal
 *
 * @param {Object} obj - The object to get value from
 * @param {string} path - Dot-separated path to the value
 * @returns {*} The value at the path, or undefined if not found
 */
export function getNestedValue(obj, path) {
    if (!obj || !path) return undefined;

    // If no dot in path, direct access
    if (!path.includes('.')) {
        return obj[path];
    }

    // Navigate through nested path
    const parts = path.split('.');
    let current = obj;

    for (const part of parts) {
        if (current === null || current === undefined) {
            return undefined;
        }
        current = current[part];
    }

    return current;
}

/**
 * Format value for display.
 *
 * @param {*} value - Raw value
 * @param {Object} spec - Spec configuration
 * @returns {string} Formatted display string
 */
export function formatSpecValue(value, spec) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    // Handle arrays (like suspension.type or features)
    if (Array.isArray(value)) {
        if (value.length === 0) return '—';

        // Suspension array format - join with ", "
        if (spec.format === 'suspensionArray') {
            // Filter out "None" values
            const filtered = value.filter(v => v && v !== 'None');
            if (filtered.length === 0) return 'None';
            return filtered.join(', ');
        }

        // Feature array format - show count and first few
        if (spec.format === 'featureArray') {
            if (value.length <= 3) {
                return value.join(', ');
            }
            return `${value.slice(0, 3).join(', ')} +${value.length - 3} more`;
        }

        // Generic array format
        if (spec.format === 'array') {
            return value.join(', ');
        }

        // Default array handling
        return value.join(', ');
    }

    // Handle objects (like suspension: { front: "Spring", rear: "Dual" })
    if (typeof value === 'object' && value !== null) {
        // Suspension object
        if (value.front !== undefined || value.rear !== undefined) {
            const front = value.front || 'None';
            const rear = value.rear || 'None';
            if (front === rear) return front;
            if (front === 'None') return `Rear: ${rear}`;
            if (rear === 'None') return `Front: ${front}`;
            return `F: ${front}, R: ${rear}`;
        }
        // Generic object - try to extract meaningful value
        if (value.type) {
            // If type is an array (like suspension.type), handle it
            if (Array.isArray(value.type)) {
                const filtered = value.type.filter(v => v && v !== 'None');
                if (filtered.length === 0) return 'None';
                return filtered.join(', ');
            }
            return String(value.type);
        }
        if (value.value) return String(value.value);
        // Last resort - join non-empty values
        const vals = Object.values(value).filter(v => v && v !== 'None');
        return vals.length ? vals.join(', ') : '—';
    }

    // Boolean formatting
    if (spec.format === 'boolean') {
        if (value === true || value === 'Yes' || value === 'yes' || value === 1) {
            return 'Yes';
        }
        if (value === false || value === 'No' || value === 'no' || value === 0) {
            return 'No';
        }
        return String(value);
    }

    // IP rating formatting - uppercase valid ratings (e.g. "ip54" → "IP54"), leave others as-is
    if (spec.format === 'ip') {
        const str = String(value);
        return /^ip/i.test(str) ? str.toUpperCase() : str;
    }

    // Suspension formatting (string value)
    if (spec.format === 'suspension') {
        return String(value);
    }

    // Currency formatting (value metrics like $24.22/Wh)
    if (spec.format === 'currency') {
        const num = parseFloat(value);
        if (!isNaN(num)) {
            // Currency format: symbol + value + unit (e.g., "€24.22/Wh")
            // currencySymbol is added by resolveGeoSpec() for geoAware specs
            const symbol = spec.currencySymbol || '$';
            const unit = spec.valueUnit || '';
            return symbol + num.toFixed(2) + unit;
        }
        return '—';
    }

    // Decimal formatting (efficiency metrics like 0.45 mph/lb)
    if (spec.format === 'decimal') {
        const num = parseFloat(value);
        if (!isNaN(num)) {
            // Decimal format uses valueUnit with space (e.g., "0.45 mph/lb")
            const unit = spec.valueUnit ? ' ' + spec.valueUnit : '';
            return num.toFixed(2) + unit;
        }
        return '—';
    }

    // Numeric with unit
    if (spec.unit) {
        const num = parseFloat(value);
        if (!isNaN(num)) {
            // Round to 1 decimal if needed
            const formatted = Number.isInteger(num) ? num : num.toFixed(1);
            return `${formatted} ${spec.unit}`;
        }
    }

    return String(value);
}

/**
 * Score suspension array for comparison.
 * Hierarchy: dual > hydraulic > spring > rubber > none
 *
 * Scoring:
 * - Dual hydraulic: 10
 * - Dual spring: 9
 * - Dual rubber: 8
 * - Single hydraulic: 5
 * - Single spring: 4
 * - Single rubber: 3
 * - None: 0
 *
 * @param {Array|null} suspensionArray - Array like ["Front hydraulic", "Rear spring"]
 * @returns {number} Score for comparison
 */
function scoreSuspensionForComparison(suspensionArray) {
    if (!suspensionArray || !Array.isArray(suspensionArray) || suspensionArray.length === 0) {
        return 0;
    }

    const types = suspensionArray.map(s => String(s).toLowerCase());

    // Check for "None" only
    if (types.every(t => t === 'none' || t === '')) {
        return 0;
    }

    // Helper to score a single suspension type
    const scoreType = (type) => {
        if (type.includes('hydraulic')) return 3;
        if (type.includes('spring') || type.includes('fork')) return 2;
        if (type.includes('rubber')) return 1;
        return 0;
    };

    // Check for dual suspension entries
    const dualEntry = types.find(t => t.includes('dual'));
    if (dualEntry) {
        const typeScore = scoreType(dualEntry);
        return 7 + typeScore; // Dual: 8-10 range
    }

    // Check for front and rear
    const frontEntry = types.find(t => t.includes('front'));
    const rearEntry = types.find(t => t.includes('rear'));

    const hasFront = frontEntry && !frontEntry.includes('none');
    const hasRear = rearEntry && !rearEntry.includes('none');

    if (hasFront && hasRear) {
        // Both front and rear = dual bonus
        const frontScore = scoreType(frontEntry);
        const rearScore = scoreType(rearEntry);
        const avgTypeScore = (frontScore + rearScore) / 2;
        return 7 + avgTypeScore; // Dual range: 7-10
    } else if (hasFront || hasRear) {
        // Single suspension
        const entry = hasFront ? frontEntry : rearEntry;
        const typeScore = scoreType(entry);
        return 2 + typeScore; // Single range: 3-5
    }

    return 0;
}

/**
 * Compare two values and determine winner.
 *
 * @param {*} valueA - First value
 * @param {*} valueB - Second value
 * @param {Object} spec - Spec configuration
 * @returns {number} -1 if A wins, 1 if B wins, 0 if tie/incomparable
 */
export function compareValues(valueA, valueB, spec) {
    // Handle missing values
    if (valueA === null || valueA === undefined || valueA === '') return 1;
    if (valueB === null || valueB === undefined || valueB === '') return -1;

    // Boolean comparison
    if (spec.format === 'boolean') {
        const boolA = valueA === true || valueA === 'Yes' || valueA === 'yes' || valueA === 1;
        const boolB = valueB === true || valueB === 'Yes' || valueB === 'yes' || valueB === 1;
        if (boolA === boolB) return 0;
        if (spec.higherBetter !== false) {
            return boolA ? -1 : 1;
        }
        return boolA ? 1 : -1;
    }

    // IP rating comparison - use normalized scores
    if (spec.format === 'ip') {
        const scoreA = normalizeIpRating(valueA);
        const scoreB = normalizeIpRating(valueB);
        if (scoreA === scoreB) return 0;
        if (scoreA === 0) return 1;  // A has no valid rating
        if (scoreB === 0) return -1; // B has no valid rating
        return scoreA > scoreB ? -1 : 1;
    }

    // Suspension comparison (string)
    if (spec.format === 'suspension') {
        const indexA = SUSPENSION_TYPES.findIndex(s =>
            String(valueA).toLowerCase().includes(s.toLowerCase())
        );
        const indexB = SUSPENSION_TYPES.findIndex(s =>
            String(valueB).toLowerCase().includes(s.toLowerCase())
        );
        if (indexA === indexB) return 0;
        if (indexA === -1) return 1;
        if (indexB === -1) return -1;
        return indexA > indexB ? -1 : 1;
    }

    // Suspension array comparison - dual > hydraulic > spring > rubber
    if (spec.format === 'suspensionArray') {
        const scoreA = scoreSuspensionForComparison(valueA);
        const scoreB = scoreSuspensionForComparison(valueB);
        if (scoreA === scoreB) return 0;
        return scoreA > scoreB ? -1 : 1;
    }

    // Brake type comparison
    if (spec.format === 'brakeType') {
        const indexA = BRAKE_TYPES.findIndex(s =>
            String(valueA).toLowerCase().includes(s.toLowerCase())
        );
        const indexB = BRAKE_TYPES.findIndex(s =>
            String(valueB).toLowerCase().includes(s.toLowerCase())
        );
        if (indexA === indexB) return 0;
        if (indexA === -1) return 1;
        if (indexB === -1) return -1;
        return indexA > indexB ? -1 : 1;
    }

    // Display type comparison
    if (spec.format === 'displayType') {
        const indexA = DISPLAY_TYPES.findIndex(s =>
            String(valueA).toLowerCase() === s.toLowerCase()
        );
        const indexB = DISPLAY_TYPES.findIndex(s =>
            String(valueB).toLowerCase() === s.toLowerCase()
        );
        if (indexA === indexB) return 0;
        if (indexA === -1) return 1;
        if (indexB === -1) return -1;
        return indexA > indexB ? -1 : 1;
    }

    // Tire type comparison
    if (spec.format === 'tire') {
        const indexA = TIRE_TYPES.findIndex(s =>
            String(valueA).toLowerCase().includes(s.toLowerCase())
        );
        const indexB = TIRE_TYPES.findIndex(s =>
            String(valueB).toLowerCase().includes(s.toLowerCase())
        );
        if (indexA === indexB) return 0;
        if (indexA === -1) return 1;
        if (indexB === -1) return -1;
        return indexA > indexB ? -1 : 1;
    }

    // Feature array comparison - more features = better
    if (spec.format === 'featureArray' || spec.format === 'array') {
        const countA = Array.isArray(valueA) ? valueA.length : 0;
        const countB = Array.isArray(valueB) ? valueB.length : 0;
        if (countA === countB) return 0;
        return countA > countB ? -1 : 1;
    }

    // Numeric comparison
    const numA = parseFloat(valueA);
    const numB = parseFloat(valueB);

    if (isNaN(numA) && isNaN(numB)) return 0;
    if (isNaN(numA)) return 1;
    if (isNaN(numB)) return -1;

    // Check for tie threshold
    const percentDiff = Math.abs((numA - numB) / Math.max(numA, numB)) * 100;
    if (percentDiff <= TIE_THRESHOLD) return 0;

    // Determine winner based on higherBetter
    if (spec.higherBetter === false) {
        return numA < numB ? -1 : 1;
    }
    return numA > numB ? -1 : 1;
}

/**
 * Calculate percentage difference between two values.
 *
 * @param {number} valueA - First value
 * @param {number} valueB - Second value
 * @param {boolean} higherBetter - True if higher values are better
 * @returns {number} Percentage difference (positive = A wins, negative = B wins)
 */
export function calculatePercentDiff(valueA, valueB, higherBetter = true) {
    const numA = parseFloat(valueA);
    const numB = parseFloat(valueB);

    if (isNaN(numA) || isNaN(numB) || numB === 0) return 0;

    const diff = ((numA - numB) / numB) * 100;

    // Invert for "lower is better" specs
    return higherBetter ? diff : -diff;
}

// =============================================================================
// Default Export
// =============================================================================

export default {
    IP_RATINGS,
    normalizeIpRating,
    SUSPENSION_TYPES,
    TIRE_TYPES,
    BRAKE_TYPES,
    DISPLAY_TYPES,
    TIE_THRESHOLD,
    EBIKE_SCORE_CATEGORIES,
    getNestedValue,
    formatSpecValue,
    compareValues,
    calculatePercentDiff,
};
