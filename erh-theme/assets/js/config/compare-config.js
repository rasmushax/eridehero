/**
 * Compare Config - Spec definitions and scoring weights
 *
 * Defines spec groups, display labels, units, and winner calculation logic.
 * This is the single source of truth for comparison page data.
 *
 * @module config/compare-config
 */

/**
 * Spec groups by product category.
 * Each spec defines:
 * - key: Field name in product.specs object
 * - label: Display label
 * - unit: Unit suffix (mph, lbs, etc.)
 * - higherBetter: true if higher values win, false if lower wins
 * - tooltip: Optional explanatory text for [?] icon
 * - format: Optional custom formatter ('boolean', 'ip', 'suspension', etc.)
 * - scoreWeight: Optional weight for category scoring (0-1)
 */
export const SPEC_GROUPS = {
    escooter: {
        'Performance': {
            icon: 'zap',
            specs: [
                { key: 'manufacturer_top_speed', label: 'Top Speed (claimed)', unit: 'mph', higherBetter: true, scoreWeight: 0.3 },
                { key: 'tested_top_speed', label: 'Tested Top Speed', unit: 'mph', higherBetter: true, scoreWeight: 0.2, tooltip: 'Our real-world test result' },
                { key: 'acceleration_0_15_mph', label: '0-15 mph', unit: 's', higherBetter: false, scoreWeight: 0.2, tooltip: 'Time to reach 15 mph from standstill' },
                { key: 'acceleration_0_20_mph', label: '0-20 mph', unit: 's', higherBetter: false, scoreWeight: 0.15 },
                { key: 'motor.power_nominal', label: 'Motor Power', unit: 'W', higherBetter: true, scoreWeight: 0.1 },
                { key: 'motor.power_peak', label: 'Peak Power', unit: 'W', higherBetter: true, scoreWeight: 0.05 },
                { key: 'max_incline', label: 'Hill Grade', unit: '°', higherBetter: true },
            ]
        },
        'Range & Battery': {
            icon: 'battery',
            specs: [
                { key: 'manufacturer_range', label: 'Claimed Range', unit: 'mi', higherBetter: true, scoreWeight: 0.2 },
                { key: 'tested_range_regular', label: 'Tested Range', unit: 'mi', higherBetter: true, scoreWeight: 0.35, tooltip: 'Our real-world test at 165lb rider, flat terrain' },
                { key: 'tested_range_fast', label: 'Tested Range (fast)', unit: 'mi', higherBetter: true, scoreWeight: 0.15, tooltip: 'Range at max speed setting' },
                { key: 'battery.capacity', label: 'Battery', unit: 'Wh', higherBetter: true, scoreWeight: 0.2 },
                { key: 'battery.voltage', label: 'Voltage', unit: 'V', higherBetter: true, scoreWeight: 0.05 },
                { key: 'battery.charging_time', label: 'Charge Time', unit: 'h', higherBetter: false, scoreWeight: 0.05 },
            ]
        },
        'Build & Portability': {
            icon: 'box',
            specs: [
                { key: 'dimensions.weight', label: 'Weight', unit: 'lbs', higherBetter: false, scoreWeight: 0.25 },
                { key: 'dimensions.max_load', label: 'Max Load', unit: 'lbs', higherBetter: true, scoreWeight: 0.2 },
                { key: 'other.ip_rating', label: 'IP Rating', higherBetter: true, scoreWeight: 0.15, format: 'ip' },
                { key: 'suspension.type', label: 'Suspension', higherBetter: true, scoreWeight: 0.2, format: 'suspensionArray' },
                { key: 'other.terrain', label: 'Terrain', scoreWeight: 0.1 },
                { key: 'other.fold_location', label: 'Fold Location', scoreWeight: 0.05 },
                { key: 'dimensions.folded_length', label: 'Folded Length', unit: '"', higherBetter: false, scoreWeight: 0.05 },
            ]
        },
        'Dimensions': {
            icon: 'ruler',
            collapsed: true,
            specs: [
                { key: 'dimensions.deck_length', label: 'Deck Length', unit: '"', higherBetter: true, scoreWeight: 0.3 },
                { key: 'dimensions.deck_width', label: 'Deck Width', unit: '"', higherBetter: true, scoreWeight: 0.3 },
                { key: 'dimensions.ground_clearance', label: 'Ground Clearance', unit: '"', higherBetter: true, scoreWeight: 0.2 },
                { key: 'dimensions.unfolded_length', label: 'Length', unit: '"' },
                { key: 'dimensions.unfolded_width', label: 'Width', unit: '"' },
                { key: 'dimensions.unfolded_height', label: 'Height', unit: '"' },
                { key: 'dimensions.handlebar_height_min', label: 'Handlebar Height (min)', unit: '"', scoreWeight: 0.1 },
                { key: 'dimensions.handlebar_height_max', label: 'Handlebar Height (max)', unit: '"', scoreWeight: 0.1 },
                { key: 'dimensions.handlebar_width', label: 'Handlebar Width', unit: '"' },
            ]
        },
        'Wheels & Tires': {
            icon: 'circle',
            collapsed: true,
            specs: [
                { key: 'wheels.tire_size_front', label: 'Front Tire Size', unit: '"', higherBetter: true, scoreWeight: 0.25 },
                { key: 'wheels.tire_size_rear', label: 'Rear Tire Size', unit: '"', higherBetter: true, scoreWeight: 0.25 },
                { key: 'wheels.tire_type', label: 'Tire Type', format: 'tire', scoreWeight: 0.2 },
                { key: 'wheels.tire_width', label: 'Tire Width', unit: '"', higherBetter: true, scoreWeight: 0.15 },
                { key: 'wheels.pneumatic_type', label: 'Pneumatic Type' },
                { key: 'wheels.self_healing', label: 'Self-Healing Tires', format: 'boolean', higherBetter: true, scoreWeight: 0.15 },
            ]
        },
        'Brakes': {
            icon: 'octagon',
            collapsed: true,
            specs: [
                { key: 'brakes.front', label: 'Front Brake', scoreWeight: 0.4, format: 'brakeType' },
                { key: 'brakes.rear', label: 'Rear Brake', scoreWeight: 0.4, format: 'brakeType' },
                { key: 'brakes.regenerative', label: 'Regenerative Braking', format: 'boolean', higherBetter: true, scoreWeight: 0.2 },
            ]
        },
        'Features': {
            icon: 'settings',
            collapsed: true,
            specs: [
                { key: 'other.display_type', label: 'Display', scoreWeight: 0.15, format: 'displayType' },
                { key: 'features', label: 'Features', format: 'featureArray', scoreWeight: 0.35 },
                { key: 'lighting.turn_signals', label: 'Turn Signals', format: 'boolean', higherBetter: true, scoreWeight: 0.15 },
                { key: 'lighting.lights', label: 'Lights', format: 'array', scoreWeight: 0.1 },
                { key: 'other.kickstand', label: 'Kickstand', format: 'boolean', higherBetter: true, scoreWeight: 0.1 },
                { key: 'other.footrest', label: 'Footrest', format: 'boolean', higherBetter: true, scoreWeight: 0.05 },
                { key: 'other.throttle_type', label: 'Throttle Type', scoreWeight: 0.1 },
            ]
        },
    },

    ebike: {
        'Motor & Power': {
            icon: 'zap',
            specs: [
                { key: 'e-bikes.motor.power_nominal', label: 'Motor Power', unit: 'W', higherBetter: true, scoreWeight: 0.3 },
                { key: 'e-bikes.motor.power_peak', label: 'Peak Power', unit: 'W', higherBetter: true, scoreWeight: 0.2 },
                { key: 'e-bikes.motor.torque', label: 'Torque', unit: 'Nm', higherBetter: true, scoreWeight: 0.25 },
                { key: 'e-bikes.motor.type', label: 'Motor Type', scoreWeight: 0.1 },
                { key: 'e-bikes.motor.position', label: 'Motor Position', scoreWeight: 0.15 },
            ]
        },
        'Range & Battery': {
            icon: 'battery',
            specs: [
                { key: 'e-bikes.battery.range_claimed', label: 'Claimed Range', unit: 'mi', higherBetter: true, scoreWeight: 0.25 },
                { key: 'e-bikes.battery.capacity', label: 'Battery', unit: 'Wh', higherBetter: true, scoreWeight: 0.3 },
                { key: 'e-bikes.battery.voltage', label: 'Voltage', unit: 'V', higherBetter: true, scoreWeight: 0.1 },
                { key: 'e-bikes.battery.removable', label: 'Removable Battery', format: 'boolean', higherBetter: true, scoreWeight: 0.2 },
                { key: 'e-bikes.battery.charge_time', label: 'Charge Time', unit: 'h', higherBetter: false, scoreWeight: 0.15 },
            ]
        },
        'Speed & Performance': {
            icon: 'gauge',
            specs: [
                { key: 'e-bikes.performance.top_speed', label: 'Top Speed', unit: 'mph', higherBetter: true, scoreWeight: 0.4 },
                { key: 'e-bikes.performance.class', label: 'Class', scoreWeight: 0.3 },
                { key: 'e-bikes.performance.pedal_assist_levels', label: 'Assist Levels', higherBetter: true, scoreWeight: 0.15 },
                { key: 'e-bikes.performance.throttle', label: 'Throttle', format: 'boolean', scoreWeight: 0.15 },
            ]
        },
        'Build & Frame': {
            icon: 'box',
            specs: [
                { key: 'e-bikes.frame.weight', label: 'Weight', unit: 'lbs', higherBetter: false, scoreWeight: 0.2 },
                { key: 'e-bikes.frame.max_load', label: 'Max Load', unit: 'lbs', higherBetter: true, scoreWeight: 0.2 },
                { key: 'e-bikes.frame.material', label: 'Frame Material', scoreWeight: 0.15 },
                { key: 'e-bikes.frame.type', label: 'Frame Type', scoreWeight: 0.15 },
                { key: 'e-bikes.frame.suspension', label: 'Suspension', format: 'suspension', scoreWeight: 0.15 },
                { key: 'e-bikes.frame.foldable', label: 'Foldable', format: 'boolean', scoreWeight: 0.15 },
            ]
        },
        'Components': {
            icon: 'settings',
            collapsed: true,
            specs: [
                { key: 'e-bikes.components.gears', label: 'Gears' },
                { key: 'e-bikes.components.brakes', label: 'Brakes' },
                { key: 'e-bikes.components.wheel_size', label: 'Wheel Size', unit: '"' },
                { key: 'e-bikes.components.tire_type', label: 'Tire Type' },
                { key: 'e-bikes.components.display', label: 'Display' },
                { key: 'e-bikes.components.lights', label: 'Lights' },
            ]
        },
    },

    euc: {
        'Performance': {
            icon: 'zap',
            specs: [
                { key: 'manufacturer_top_speed', label: 'Top Speed', unit: 'mph', higherBetter: true, scoreWeight: 0.35 },
                { key: 'nominal_motor_wattage', label: 'Motor Power', unit: 'W', higherBetter: true, scoreWeight: 0.35 },
                { key: 'peak_motor_wattage', label: 'Peak Power', unit: 'W', higherBetter: true, scoreWeight: 0.15 },
                { key: 'hill_climb_angle', label: 'Hill Grade', unit: '°', higherBetter: true, scoreWeight: 0.15 },
            ]
        },
        'Range & Battery': {
            icon: 'battery',
            specs: [
                { key: 'manufacturer_range', label: 'Claimed Range', unit: 'mi', higherBetter: true, scoreWeight: 0.3 },
                { key: 'battery_capacity', label: 'Battery', unit: 'Wh', higherBetter: true, scoreWeight: 0.4 },
                { key: 'battery_voltage', label: 'Voltage', unit: 'V', higherBetter: true, scoreWeight: 0.15 },
                { key: 'charge_time', label: 'Charge Time', unit: 'h', higherBetter: false, scoreWeight: 0.15 },
            ]
        },
        'Build': {
            icon: 'box',
            specs: [
                { key: 'weight', label: 'Weight', unit: 'lbs', higherBetter: false, scoreWeight: 0.3 },
                { key: 'max_weight_capacity', label: 'Max Load', unit: 'lbs', higherBetter: true, scoreWeight: 0.25 },
                { key: 'wheel_size', label: 'Wheel Size', unit: '"', scoreWeight: 0.2 },
                { key: 'suspension', label: 'Suspension', format: 'suspension', scoreWeight: 0.15 },
                { key: 'ip_rating', label: 'IP Rating', format: 'ip', scoreWeight: 0.1 },
            ]
        },
    },

    eskateboard: {
        'Performance': {
            icon: 'zap',
            specs: [
                { key: 'manufacturer_top_speed', label: 'Top Speed', unit: 'mph', higherBetter: true, scoreWeight: 0.35 },
                { key: 'nominal_motor_wattage', label: 'Motor Power', unit: 'W', higherBetter: true, scoreWeight: 0.25 },
                { key: 'motor_count', label: 'Motors', higherBetter: true, scoreWeight: 0.2 },
                { key: 'hill_climb_angle', label: 'Hill Grade', unit: '°', higherBetter: true, scoreWeight: 0.2 },
            ]
        },
        'Range & Battery': {
            icon: 'battery',
            specs: [
                { key: 'manufacturer_range', label: 'Claimed Range', unit: 'mi', higherBetter: true, scoreWeight: 0.35 },
                { key: 'battery_capacity', label: 'Battery', unit: 'Wh', higherBetter: true, scoreWeight: 0.35 },
                { key: 'charge_time', label: 'Charge Time', unit: 'h', higherBetter: false, scoreWeight: 0.15 },
                { key: 'swappable_battery', label: 'Swappable Battery', format: 'boolean', scoreWeight: 0.15 },
            ]
        },
        'Build': {
            icon: 'box',
            specs: [
                { key: 'weight', label: 'Weight', unit: 'lbs', higherBetter: false, scoreWeight: 0.25 },
                { key: 'max_weight_capacity', label: 'Max Load', unit: 'lbs', higherBetter: true, scoreWeight: 0.2 },
                { key: 'deck_type', label: 'Deck Type', scoreWeight: 0.2 },
                { key: 'deck_length', label: 'Deck Length', unit: '"', scoreWeight: 0.15 },
                { key: 'wheel_type', label: 'Wheel Type', scoreWeight: 0.2 },
            ]
        },
    },

    hoverboard: {
        'Performance': {
            icon: 'zap',
            specs: [
                { key: 'manufacturer_top_speed', label: 'Top Speed', unit: 'mph', higherBetter: true, scoreWeight: 0.4 },
                { key: 'nominal_motor_wattage', label: 'Motor Power', unit: 'W', higherBetter: true, scoreWeight: 0.35 },
                { key: 'hill_climb_angle', label: 'Hill Grade', unit: '°', higherBetter: true, scoreWeight: 0.25 },
            ]
        },
        'Range & Battery': {
            icon: 'battery',
            specs: [
                { key: 'manufacturer_range', label: 'Claimed Range', unit: 'mi', higherBetter: true, scoreWeight: 0.4 },
                { key: 'battery_capacity', label: 'Battery', unit: 'Wh', higherBetter: true, scoreWeight: 0.35 },
                { key: 'charge_time', label: 'Charge Time', unit: 'h', higherBetter: false, scoreWeight: 0.25 },
            ]
        },
        'Build': {
            icon: 'box',
            specs: [
                { key: 'weight', label: 'Weight', unit: 'lbs', higherBetter: false, scoreWeight: 0.3 },
                { key: 'max_weight_capacity', label: 'Max Load', unit: 'lbs', higherBetter: true, scoreWeight: 0.3 },
                { key: 'wheel_size', label: 'Wheel Size', unit: '"', scoreWeight: 0.2 },
                { key: 'ul_certified', label: 'UL Certified', format: 'boolean', higherBetter: true, scoreWeight: 0.2 },
            ]
        },
    },
};

/**
 * Category weights for overall scoring.
 * Each category's contribution to the total product score.
 */
export const CATEGORY_WEIGHTS = {
    escooter: {
        'Performance': 0.25,
        'Range & Battery': 0.25,
        'Build & Portability': 0.20,
        'Dimensions': 0.05,
        'Wheels & Tires': 0.10,
        'Brakes': 0.05,
        'Features': 0.10,
    },
    ebike: {
        'Motor & Power': 0.25,
        'Range & Battery': 0.25,
        'Speed & Performance': 0.20,
        'Build & Frame': 0.20,
        'Components': 0.10,
    },
    euc: {
        'Performance': 0.35,
        'Range & Battery': 0.35,
        'Build': 0.30,
    },
    eskateboard: {
        'Performance': 0.35,
        'Range & Battery': 0.35,
        'Build': 0.30,
    },
    hoverboard: {
        'Performance': 0.30,
        'Range & Battery': 0.35,
        'Build': 0.35,
    },
};

/**
 * IP rating ranking for comparison.
 * Higher index = better water resistance.
 */
export const IP_RATINGS = [
    'None', 'IPX4', 'IPX5', 'IP54', 'IP55', 'IP56', 'IP65', 'IP66', 'IP67', 'IP68'
];

/**
 * Suspension type ranking.
 * Higher index = better suspension.
 */
export const SUSPENSION_TYPES = [
    'None', 'Front only', 'Rear only', 'Dual spring', 'Dual hydraulic', 'Full suspension'
];

/**
 * Tire type ranking (for comparison, not strictly better/worse).
 */
export const TIRE_TYPES = [
    'Solid', 'Honeycomb', 'Semi-pneumatic', 'Pneumatic', 'Tubeless pneumatic'
];

/**
 * Brake type ranking.
 * Higher index = better braking.
 */
export const BRAKE_TYPES = [
    'None', 'Foot', 'Drum', 'Disc (Mechanical)', 'Disc (Hydraulic)'
];

/**
 * Display type ranking.
 * Higher index = better display.
 */
export const DISPLAY_TYPES = [
    'None', 'Unknown', 'LED', 'LCD', 'OLED', 'TFT'
];

/**
 * Threshold for declaring a "tie" (percentage difference).
 * If two values are within this percentage, neither wins.
 */
export const TIE_THRESHOLD = 3; // 3%

/**
 * Minimum percentage difference to include in advantage list.
 */
export const ADVANTAGE_THRESHOLD = 5; // 5%

/**
 * Maximum number of advantages to show per product.
 */
export const MAX_ADVANTAGES = 5;

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

    // IP rating formatting
    if (spec.format === 'ip') {
        return String(value).toUpperCase();
    }

    // Suspension formatting (string value)
    if (spec.format === 'suspension') {
        return String(value);
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

    // IP rating comparison
    if (spec.format === 'ip') {
        const indexA = IP_RATINGS.indexOf(String(valueA).toUpperCase());
        const indexB = IP_RATINGS.indexOf(String(valueB).toUpperCase());
        if (indexA === indexB) return 0;
        if (indexA === -1) return 1;
        if (indexB === -1) return -1;
        return indexA > indexB ? -1 : 1;
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

    // Suspension array comparison - count suspension points (more = better)
    if (spec.format === 'suspensionArray') {
        const countA = Array.isArray(valueA) ? valueA.filter(v => v && v !== 'None').length : 0;
        const countB = Array.isArray(valueB) ? valueB.filter(v => v && v !== 'None').length : 0;
        if (countA === countB) return 0;
        return countA > countB ? -1 : 1;
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

export default {
    SPEC_GROUPS,
    CATEGORY_WEIGHTS,
    IP_RATINGS,
    SUSPENSION_TYPES,
    TIRE_TYPES,
    BRAKE_TYPES,
    DISPLAY_TYPES,
    TIE_THRESHOLD,
    ADVANTAGE_THRESHOLD,
    MAX_ADVANTAGES,
    getNestedValue,
    formatSpecValue,
    compareValues,
    calculatePercentDiff,
};
