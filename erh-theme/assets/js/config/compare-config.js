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
 *
 * E-scooter uses new consumer-friendly categories with absolute scoring:
 * - Motor Performance: "How fast/powerful is it?"
 * - Range & Battery: "How far can I go?"
 * - Ride Quality: "Is it comfortable?"
 * - Portability: "Can I carry it?" (not yet implemented)
 * - Safety: "Is it safe?" (not yet implemented)
 * - Features: "What extras does it have?" (not yet implemented)
 * - Maintenance: "Is it hassle-free?" (not yet implemented)
 */
export const CATEGORY_WEIGHTS = {
    escooter: {
        'Motor Performance': 0.20,
        'Range & Battery': 0.20,
        'Ride Quality': 0.20,
        'Portability': 0.15,
        'Safety': 0.10,
        'Features': 0.10,
        'Maintenance': 0.05,
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

// =============================================================================
// Absolute Scoring System
// =============================================================================
//
// Fixed/absolute scoring that doesn't change based on what's being compared.
// Each category scores 0-100 based on fixed thresholds with logarithmic scaling.
// Missing specs redistribute weight to available specs (no guessing).
//
// Each scoring function returns: { score: number|null, maxPossible: number }
// - score: null means spec is missing/invalid, don't count it
// - maxPossible: 0 means this factor is skipped entirely
// =============================================================================

/**
 * Score motor power (nominal + peak average).
 * Log scale: 400W floor, 8000W ceiling.
 * @param {number|null} nominal - Nominal power in watts
 * @param {number|null} peak - Peak power in watts
 * @returns {{ score: number|null, maxPossible: number }}
 */
export function scorePower(nominal, peak) {
    const values = [nominal, peak].filter(v => v != null && v > 0);
    if (values.length === 0) return { score: null, maxPossible: 0 };

    const avgPower = values.reduce((a, b) => a + b, 0) / values.length;

    // Log scale: 45 * log2(watts/400) / log2(20)
    // 400W → 0 pts, 8000W → 45 pts
    const raw = 45 * Math.log2(avgPower / 400) / Math.log2(20);
    const score = Math.max(0, Math.min(45, raw));

    return { score, maxPossible: 45 };
}

/**
 * Score motor voltage.
 * Log scale: 18V floor, 84V ceiling.
 * @param {number|null} voltage - Voltage in V
 * @returns {{ score: number|null, maxPossible: number }}
 */
export function scoreMotorVoltage(voltage) {
    if (voltage == null || voltage <= 0) return { score: null, maxPossible: 0 };

    // Log scale: 20 * log2(voltage/18) / log2(4.67)
    // 18V → 0 pts, 84V → 20 pts
    const raw = 20 * Math.log2(voltage / 18) / Math.log2(4.67);
    const score = Math.max(0, Math.min(20, raw));

    return { score, maxPossible: 20 };
}

/**
 * Score top speed.
 * Log scale: 8 mph floor, 60 mph ceiling.
 * @param {number|null} speedMph - Top speed in mph
 * @returns {{ score: number|null, maxPossible: number }}
 */
export function scoreTopSpeed(speedMph) {
    if (speedMph == null || speedMph <= 0) return { score: null, maxPossible: 0 };

    // Log scale: 25 * log2(speed/8) / log2(7.5)
    // 8 mph → 0 pts, 60 mph → 25 pts
    const raw = 25 * Math.log2(speedMph / 8) / Math.log2(7.5);
    const score = Math.max(0, Math.min(25, raw));

    return { score, maxPossible: 25 };
}

/**
 * Score dual motor bonus.
 * @param {string|null} motorPosition - 'Dual', 'Front', 'Rear', etc.
 * @returns {{ score: number|null, maxPossible: number }}
 */
export function scoreDualMotor(motorPosition) {
    if (!motorPosition) return { score: null, maxPossible: 0 };

    const pos = String(motorPosition).toLowerCase();
    if (pos.includes('dual')) return { score: 10, maxPossible: 10 };
    if (pos.includes('front') || pos.includes('rear')) return { score: 0, maxPossible: 10 };

    return { score: null, maxPossible: 0 }; // Unknown position
}

/**
 * Calculate Motor Performance category score (0-100).
 * Factors: Power 45pts, Voltage 20pts, Top Speed 25pts, Dual Motor 10pts
 * @param {Object} specs - Product specs object
 * @returns {number|null} Score 0-100 or null if no data
 */
export function calculateMotorPerformance(specs) {
    const factors = [
        scorePower(
            getNestedValue(specs, 'motor.power_nominal'),
            getNestedValue(specs, 'motor.power_peak')
        ),
        scoreMotorVoltage(getNestedValue(specs, 'motor.voltage')),
        scoreTopSpeed(specs.manufacturer_top_speed),
        scoreDualMotor(getNestedValue(specs, 'motor.motor_position')),
    ];

    const available = factors.filter(f => f.score !== null);
    if (available.length === 0) return null;

    const totalScore = available.reduce((sum, f) => sum + f.score, 0);
    const totalMaxPossible = available.reduce((sum, f) => sum + f.maxPossible, 0);

    return Math.round((totalScore / totalMaxPossible) * 100);
}

// -----------------------------------------------------------------------------
// Range & Battery Scoring
// -----------------------------------------------------------------------------

/**
 * Score battery capacity.
 * Log scale: 150Wh floor, 3000Wh ceiling.
 * @param {number|null} wh - Battery capacity in Wh
 * @returns {{ score: number|null, maxPossible: number }}
 */
export function scoreBatteryCapacity(wh) {
    if (wh == null || wh <= 0) return { score: null, maxPossible: 0 };

    // Log scale: 70 * log2(wh/150) / log2(20)
    // 150Wh → 0 pts, 3000Wh → 70 pts
    const raw = 70 * Math.log2(wh / 150) / Math.log2(20);
    const score = Math.max(0, Math.min(70, raw));

    return { score, maxPossible: 70 };
}

/**
 * Score battery voltage.
 * Log scale: 18V floor, 84V ceiling (same as motor voltage).
 * @param {number|null} voltage - Battery voltage in V
 * @returns {{ score: number|null, maxPossible: number }}
 */
export function scoreBatteryVoltage(voltage) {
    if (voltage == null || voltage <= 0) return { score: null, maxPossible: 0 };

    // Log scale: 20 * log2(voltage/18) / log2(4.67)
    const raw = 20 * Math.log2(voltage / 18) / Math.log2(4.67);
    const score = Math.max(0, Math.min(20, raw));

    return { score, maxPossible: 20 };
}

/**
 * Score charge time (lower is better).
 * Inverse log scale: 2h excellent, 12h+ poor.
 * @param {number|null} hours - Charge time in hours
 * @returns {{ score: number|null, maxPossible: number }}
 */
export function scoreChargeTime(hours) {
    if (hours == null || hours <= 0) return { score: null, maxPossible: 0 };

    // Inverse log scale: 10 * (1 - log2(hours/1.5) / log2(10))
    // 2h → 9 pts, 6h → 4 pts, 15h+ → 0 pts
    const raw = 10 * (1 - Math.log2(hours / 1.5) / Math.log2(10));
    const score = Math.max(0, Math.min(10, raw));

    return { score, maxPossible: 10 };
}

/**
 * Calculate Range & Battery category score (0-100).
 * Factors: Battery Capacity 70pts, Voltage 20pts, Charge Time 10pts
 * @param {Object} specs - Product specs object
 * @returns {number|null} Score 0-100 or null if no data
 */
export function calculateRangeBattery(specs) {
    const factors = [
        scoreBatteryCapacity(getNestedValue(specs, 'battery.capacity')),
        scoreBatteryVoltage(getNestedValue(specs, 'battery.voltage')),
        scoreChargeTime(getNestedValue(specs, 'battery.charging_time')),
    ];

    const available = factors.filter(f => f.score !== null);
    if (available.length === 0) return null;

    const totalScore = available.reduce((sum, f) => sum + f.score, 0);
    const totalMaxPossible = available.reduce((sum, f) => sum + f.maxPossible, 0);

    return Math.round((totalScore / totalMaxPossible) * 100);
}

// -----------------------------------------------------------------------------
// Ride Quality Scoring
// -----------------------------------------------------------------------------

/**
 * Score suspension quality.
 * Front/Rear: Hydraulic +15, Spring/Fork +10, Rubber +7
 * Adjustable bonus: +10
 * @param {Array|null} suspensionType - Array like ["Front spring", "Rear hydraulic"]
 * @param {boolean|null} adjustable - Whether suspension is adjustable
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreSuspension(suspensionType, adjustable) {
    // Suspension is always scored, even if None (0 points)
    if (!suspensionType || !Array.isArray(suspensionType) || suspensionType.length === 0) {
        return { score: 0, maxPossible: 40 };
    }

    let score = 0;
    const types = suspensionType.map(s => String(s).toLowerCase());

    // Check for "None" only
    if (types.every(t => t === 'none' || t === '')) {
        return { score: 0, maxPossible: 40 };
    }

    // Front suspension scoring
    const frontHydraulic = types.some(t => t.includes('front') && t.includes('hydraulic'));
    const frontSpring = types.some(t => t.includes('front') && (t.includes('spring') || t.includes('fork')));
    const frontRubber = types.some(t => t.includes('front') && t.includes('rubber'));

    if (frontHydraulic) score += 15;
    else if (frontSpring) score += 10;
    else if (frontRubber) score += 7;

    // Rear suspension scoring
    const rearHydraulic = types.some(t => t.includes('rear') && t.includes('hydraulic'));
    const rearSpring = types.some(t => t.includes('rear') && (t.includes('spring') || t.includes('fork')));
    const rearRubber = types.some(t => t.includes('rear') && t.includes('rubber'));

    if (rearHydraulic) score += 15;
    else if (rearSpring) score += 10;
    else if (rearRubber) score += 7;

    // Handle "Dual" entries (affects both front and rear)
    const dualHydraulic = types.some(t => t.includes('dual') && t.includes('hydraulic'));
    const dualSpring = types.some(t => t.includes('dual') && (t.includes('spring') || t.includes('fork')));
    const dualRubber = types.some(t => t.includes('dual') && t.includes('rubber'));

    if (dualHydraulic) score = Math.max(score, 30);
    else if (dualSpring) score = Math.max(score, 20);
    else if (dualRubber) score = Math.max(score, 14);

    // Adjustable bonus
    if (adjustable === true) score += 10;

    return { score: Math.min(40, score), maxPossible: 40 };
}

/**
 * Score tire type for comfort.
 * Pneumatic: 20pts, Mixed/Semi: 10pts, Solid/Honeycomb: 0pts
 * Tubeless bonus: +5pts (only if pneumatic)
 * @param {string|null} tireType - 'Pneumatic', 'Solid', etc.
 * @param {string|null} pneumaticType - 'Tubeless', 'Tube', etc.
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreTireType(tireType, pneumaticType) {
    let score = 0;

    const type = String(tireType || '').toLowerCase();

    if (type.includes('pneumatic') && !type.includes('semi')) {
        score = 20; // Full pneumatic
    } else if (type.includes('mixed') || type.includes('semi')) {
        score = 10; // Mixed or semi-pneumatic
    } else if (type.includes('solid') || type.includes('honeycomb')) {
        score = 0; // Solid/honeycomb = no comfort
    }

    // Tubeless bonus (only if pneumatic)
    if (score >= 20) {
        const pType = String(pneumaticType || '').toLowerCase();
        if (pType.includes('tubeless')) {
            score += 5;
        }
    }

    return { score: Math.min(25, score), maxPossible: 25 };
}

/**
 * Score tire size for comfort.
 * 6" → 0pts, 8" → 6pts, 10" → 10pts, 12"+ → 15pts
 * @param {number|null} frontSize - Front tire size in inches
 * @param {number|null} rearSize - Rear tire size in inches
 * @returns {{ score: number|null, maxPossible: number }}
 */
export function scoreTireSize(frontSize, rearSize) {
    const sizes = [frontSize, rearSize].filter(s => s != null && s > 0);
    if (sizes.length === 0) return { score: null, maxPossible: 0 };

    const avgSize = sizes.reduce((a, b) => a + b, 0) / sizes.length;

    let score;
    if (avgSize <= 6) {
        score = 0;
    } else if (avgSize <= 8) {
        score = 3 + (avgSize - 6) * 1.5; // 6-8" = 3-6 pts
    } else if (avgSize <= 10) {
        score = 6 + (avgSize - 8) * 2; // 8-10" = 6-10 pts
    } else {
        score = 10 + (avgSize - 10) * 2.5; // 10"+ = 10-15 pts
    }

    return { score: Math.min(15, Math.max(0, score)), maxPossible: 15 };
}

/**
 * Score deck and handlebar dimensions.
 * Deck length: 15-22" scale
 * Deck width: 5-8" scale
 * Handlebar width: 18-24" scale
 * @param {number|null} deckLength - Deck length in inches
 * @param {number|null} deckWidth - Deck width in inches
 * @param {number|null} handlebarWidth - Handlebar width in inches
 * @returns {{ score: number|null, maxPossible: number }}
 */
export function scoreDeckAndHandlebar(deckLength, deckWidth, handlebarWidth) {
    const factors = [];

    // Deck length: 15" = 0pts, 22"+ = 4pts
    if (deckLength != null && deckLength > 0) {
        const deckLengthScore = Math.min(4, Math.max(0, (deckLength - 14) / 2));
        factors.push({ score: deckLengthScore, max: 4 });
    }

    // Deck width: 5" = 0pts, 8"+ = 3pts
    if (deckWidth != null && deckWidth > 0) {
        const deckWidthScore = Math.min(3, Math.max(0, (deckWidth - 4) / 1.5));
        factors.push({ score: deckWidthScore, max: 3 });
    }

    // Handlebar width: 18" = 0pts, 24"+ = 3pts
    if (handlebarWidth != null && handlebarWidth > 0) {
        const hbScore = Math.min(3, Math.max(0, (handlebarWidth - 16) / 3));
        factors.push({ score: hbScore, max: 3 });
    }

    if (factors.length === 0) return { score: null, maxPossible: 0 };

    const totalScore = factors.reduce((sum, f) => sum + f.score, 0);
    const totalMax = factors.reduce((sum, f) => sum + f.max, 0);

    // Scale to 10 pts max
    return { score: (totalScore / totalMax) * 10, maxPossible: 10 };
}

/**
 * Score comfort extras.
 * Steering damper: +5pts, Footrest: +5pts
 * @param {Array|null} features - Features array
 * @param {boolean|null} footrest - Whether has footrest
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreComfortExtras(features, footrest) {
    let score = 0;

    // Steering damper (in features array)
    if (Array.isArray(features)) {
        const hasSteeringDamper = features.some(f =>
            String(f).toLowerCase().includes('steering damper')
        );
        if (hasSteeringDamper) score += 5;
    }

    // Footrest
    if (footrest === true) score += 5;

    return { score, maxPossible: 10 };
}

/**
 * Calculate Ride Quality category score (0-100).
 * Factors: Suspension 40pts, Tire Type 25pts, Tire Size 15pts, Deck/Handlebar 10pts, Extras 10pts
 * @param {Object} specs - Product specs object
 * @returns {number|null} Score 0-100 or null if no data
 */
export function calculateRideQuality(specs) {
    const factors = [
        scoreSuspension(
            getNestedValue(specs, 'suspension.type'),
            getNestedValue(specs, 'suspension.adjustable')
        ),
        scoreTireType(
            getNestedValue(specs, 'wheels.tire_type'),
            getNestedValue(specs, 'wheels.pneumatic_type')
        ),
        scoreTireSize(
            getNestedValue(specs, 'wheels.tire_size_front'),
            getNestedValue(specs, 'wheels.tire_size_rear')
        ),
        scoreDeckAndHandlebar(
            getNestedValue(specs, 'dimensions.deck_length'),
            getNestedValue(specs, 'dimensions.deck_width'),
            getNestedValue(specs, 'dimensions.handlebar_width')
        ),
        scoreComfortExtras(
            specs.features,
            getNestedValue(specs, 'other.footrest')
        ),
    ];

    // Only filter out factors that returned null score
    // Suspension, tire type, and comfort extras always return a score (even 0)
    const available = factors.filter(f => f.score !== null && f.maxPossible > 0);
    if (available.length === 0) return null;

    const totalScore = available.reduce((sum, f) => sum + f.score, 0);
    const totalMaxPossible = available.reduce((sum, f) => sum + f.maxPossible, 0);

    return Math.round((totalScore / totalMaxPossible) * 100);
}

// -----------------------------------------------------------------------------
// Portability Scoring
// -----------------------------------------------------------------------------

/**
 * Score weight for portability (lighter is better).
 * Log scale: 25 lbs floor (ultralight), 140 lbs ceiling (beast).
 * @param {number|null} weight - Weight in lbs
 * @returns {{ score: number|null, maxPossible: number }}
 */
export function scoreWeight(weight) {
    if (weight == null || weight <= 0) return { score: null, maxPossible: 0 };

    // Cap at floor/ceiling
    if (weight <= 25) return { score: 60, maxPossible: 60 };
    if (weight >= 140) return { score: 0, maxPossible: 60 };

    // Log scale: 60 * (1 - log2(weight/25) / log2(5.6))
    // 25 lbs → 60 pts, 140 lbs → 0 pts
    const raw = 60 * (1 - Math.log2(weight / 25) / Math.log2(5.6));
    const score = Math.max(0, Math.min(60, raw));

    return { score, maxPossible: 60 };
}

/**
 * Score folded volume (smaller is better).
 * Log scale: 5000 cu in floor (super compact), 35000 cu in ceiling (huge).
 * @param {number|null} length - Folded length in inches
 * @param {number|null} width - Folded width in inches
 * @param {number|null} height - Folded height in inches
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreFoldedVolume(length, width, height) {
    // All three dimensions required
    if (length == null || width == null || height == null) {
        // No folded dimensions = doesn't fold or missing data = 0 pts
        return { score: 0, maxPossible: 35 };
    }
    if (length <= 0 || width <= 0 || height <= 0) {
        return { score: 0, maxPossible: 35 };
    }

    const volume = length * width * height;

    // Cap at floor/ceiling
    if (volume <= 5000) return { score: 35, maxPossible: 35 };
    if (volume >= 35000) return { score: 0, maxPossible: 35 };

    // Log scale: 35 * (1 - log2(volume/5000) / log2(7))
    // 5000 cu in → 35 pts, 35000 cu in → 0 pts
    const raw = 35 * (1 - Math.log2(volume / 5000) / Math.log2(7));
    const score = Math.max(0, Math.min(35, raw));

    return { score, maxPossible: 35 };
}

/**
 * Score quick-swap/removable battery bonus.
 * @param {Array|null} features - Features array
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreSwappableBattery(features) {
    if (!Array.isArray(features)) return { score: 0, maxPossible: 5 };

    const hasSwappable = features.some(f => {
        const lower = String(f).toLowerCase();
        return lower.includes('swap') ||
               lower.includes('removable battery') ||
               lower.includes('detachable battery');
    });

    return { score: hasSwappable ? 5 : 0, maxPossible: 5 };
}

/**
 * Calculate Portability category score (0-100).
 * Factors: Weight 60pts, Folded Volume 35pts, Swappable Battery 5pts
 * @param {Object} specs - Product specs object
 * @returns {number|null} Score 0-100 or null if no data
 */
export function calculatePortability(specs) {
    const factors = [
        scoreWeight(getNestedValue(specs, 'dimensions.weight')),
        scoreFoldedVolume(
            getNestedValue(specs, 'dimensions.folded_length'),
            getNestedValue(specs, 'dimensions.folded_width'),
            getNestedValue(specs, 'dimensions.folded_height')
        ),
        scoreSwappableBattery(specs.features),
    ];

    // Weight is required for portability score
    const weightFactor = factors[0];
    if (weightFactor.score === null) return null;

    const totalScore = factors.reduce((sum, f) => sum + (f.score ?? 0), 0);
    const totalMaxPossible = factors.reduce((sum, f) => sum + f.maxPossible, 0);

    return Math.round((totalScore / totalMaxPossible) * 100);
}

// -----------------------------------------------------------------------------
// Maintenance Scoring
// -----------------------------------------------------------------------------

/**
 * Score tire type for maintenance (less flats = better).
 * Note: This is inverse of ride quality - solid tires score HIGH here.
 * @param {string|null} tireType - 'Solid', 'Tubeless', 'Tubed', etc.
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreMaintenanceTireType(tireType) {
    const type = String(tireType || '').toLowerCase();

    let score = 15; // Default to tubed (most common, worst maintenance)

    if (type.includes('solid') || type.includes('honeycomb')) {
        score = 45; // Zero flats ever
    } else if (type.includes('mixed') || type.includes('semi')) {
        score = 30; // Semi-pneumatic
    } else if (type.includes('tubeless')) {
        score = 25; // Rare flats, easy plug fix
    } else if (type.includes('tubed') || type.includes('pneumatic')) {
        score = 15; // Most flats, hardest to fix
    }

    return { score, maxPossible: 45 };
}

/**
 * Score self-healing tires bonus.
 * @param {boolean|null} selfHealing - Whether tires are self-healing
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreSelfHealing(selfHealing) {
    return { score: selfHealing === true ? 5 : 0, maxPossible: 5 };
}

/**
 * Score brake type for maintenance.
 * @param {string|null} brakeType - 'Drum', 'Disc (Hydraulic)', etc.
 * @returns {number} Score 0-25
 */
function scoreSingleBrake(brakeType) {
    const type = String(brakeType || '').toLowerCase();

    if (type.includes('drum')) {
        return 25; // Sealed, set and forget
    } else if (type.includes('hydraulic') || type.includes('mechanical') || type.includes('disc')) {
        return 15; // Both disc types need maintenance
    } else if (type.includes('foot') || type === 'none' || type === '') {
        return 10; // Minimal maintenance
    }

    return 10; // Unknown defaults to low
}

/**
 * Score brake type for maintenance (average of front + rear).
 * @param {string|null} frontBrake - Front brake type
 * @param {string|null} rearBrake - Rear brake type
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreMaintenanceBrakes(frontBrake, rearBrake) {
    const frontScore = scoreSingleBrake(frontBrake);
    const rearScore = scoreSingleBrake(rearBrake);

    // Average front and rear
    const avgScore = (frontScore + rearScore) / 2;

    return { score: avgScore, maxPossible: 25 };
}

/**
 * Score regenerative braking bonus.
 * Reduces brake pad wear.
 * @param {boolean|null} regenerative - Whether has regen braking
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreRegenBraking(regenerative) {
    return { score: regenerative === true ? 5 : 0, maxPossible: 5 };
}

/**
 * Score IP rating for maintenance (water resistance).
 * Water rating (second digit) matters most for e-scooters.
 * @param {string|null} ipRating - IP rating like 'IP55', 'IPX5', etc.
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreMaintenanceIP(ipRating) {
    const rating = String(ipRating || '').toUpperCase();

    // Extract water rating (second digit or digit after X)
    // IP55 → 5, IPX5 → 5, IP67 → 7
    let waterRating = 0;

    if (rating.includes('7') || rating.includes('67')) {
        waterRating = 7;
    } else if (rating.includes('6') || rating.includes('66') || rating.includes('65')) {
        waterRating = 6;
    } else if (rating.includes('55') || rating === 'IPX5') {
        waterRating = 5;
    } else if (rating.includes('54') || rating === 'IPX4') {
        waterRating = 4;
    }

    // Score based on water rating
    let score;
    switch (waterRating) {
        case 7: score = 20; break;  // Immersion - bulletproof
        case 6: score = 16; break;  // Powerful jets
        case 5: score = 12; break;  // Water jets
        case 4: score = 8; break;   // Splashing
        default: score = 0;         // None/Unknown
    }

    return { score, maxPossible: 20 };
}

/**
 * Calculate Maintenance category score (0-100).
 * Factors: Tire Type 45pts, Self-Healing 5pts, Brakes 25pts, Regen 5pts, IP 20pts
 * @param {Object} specs - Product specs object
 * @returns {number|null} Score 0-100 or null if no data
 */
export function calculateMaintenance(specs) {
    const factors = [
        scoreMaintenanceTireType(getNestedValue(specs, 'wheels.tire_type')),
        scoreSelfHealing(getNestedValue(specs, 'wheels.self_healing')),
        scoreMaintenanceBrakes(
            getNestedValue(specs, 'brakes.front'),
            getNestedValue(specs, 'brakes.rear')
        ),
        scoreRegenBraking(getNestedValue(specs, 'brakes.regenerative')),
        scoreMaintenanceIP(getNestedValue(specs, 'other.ip_rating')),
    ];

    const totalScore = factors.reduce((sum, f) => sum + f.score, 0);
    const totalMaxPossible = factors.reduce((sum, f) => sum + f.maxPossible, 0);

    if (totalMaxPossible === 0) return null;

    return Math.round((totalScore / totalMaxPossible) * 100);
}

// -----------------------------------------------------------------------------
// Safety Scoring
// -----------------------------------------------------------------------------

/**
 * Determine brake type from string.
 * @param {string|null} brakeStr - Brake type string
 * @returns {string} 'hydraulic', 'mechanical', 'drum', 'foot', 'regen', or 'none'
 */
function parseBrakeType(brakeStr) {
    const type = String(brakeStr || '').toLowerCase();

    if (type.includes('hydraulic')) return 'hydraulic';
    if (type.includes('mechanical') || (type.includes('disc') && !type.includes('hydraulic'))) return 'mechanical';
    if (type.includes('drum')) return 'drum';
    if (type.includes('foot')) return 'foot';
    if (type.includes('regen')) return 'regen';
    if (type === '' || type === 'none') return 'none';

    return 'none';
}

/**
 * Calculate the "safe speed" a brake configuration can handle.
 * This is the speed at which the brakes are adequate for safe stopping.
 *
 * Key principles:
 * - Must have a front physical brake (rear-only is dangerous)
 * - Regen/foot alone is not sufficient
 * - Dual motor regen gets a bonus (+3 mph)
 *
 * Safe speed ratings:
 * - Dual hydraulic disc + regen: 90 mph (+ dual motor: 93 mph) - best possible, always safe
 * - Dual hydraulic disc: 80 mph
 * - Dual mechanical disc + regen: 40 mph
 * - Front hydraulic + rear drum/mech + regen: 38 mph
 * - Dual mechanical disc: 35 mph
 * - Dual drum + regen: 28 mph
 * - Front disc + rear regen only: 25 mph
 * - Dual drum: 22 mph
 * - Front drum + rear regen: 20 mph
 * - Single front disc + regen: 18 mph
 * - Single front drum + regen: 15 mph
 * - Rear only / regen only / foot only: 0 mph (unsafe)
 *
 * @param {string|null} frontBrake - Front brake type
 * @param {string|null} rearBrake - Rear brake type
 * @param {boolean} hasRegen - Whether has regenerative braking
 * @param {boolean} isDualMotor - Whether has dual motors (affects regen effectiveness)
 * @returns {number} Safe speed in mph
 */
export function calculateBrakeSafeSpeed(frontBrake, rearBrake, hasRegen, isDualMotor) {
    const front = parseBrakeType(frontBrake);
    const rear = parseBrakeType(rearBrake);

    // No front physical brake = unsafe
    if (front === 'none' || front === 'foot' || front === 'regen') {
        return 0;
    }

    let safeSpeed = 0;

    // Dual hydraulic disc - best possible, safe at any speed
    if (front === 'hydraulic' && rear === 'hydraulic') {
        safeSpeed = hasRegen ? 90 : 80;
    }
    // Dual mechanical disc
    else if (front === 'mechanical' && rear === 'mechanical') {
        safeSpeed = hasRegen ? 40 : 35;
    }
    // Front hydraulic + rear drum/mechanical
    else if (front === 'hydraulic' && (rear === 'drum' || rear === 'mechanical')) {
        safeSpeed = hasRegen ? 38 : 32;
    }
    // Front mechanical + rear drum
    else if (front === 'mechanical' && rear === 'drum') {
        safeSpeed = hasRegen ? 35 : 28;
    }
    // Dual drum
    else if (front === 'drum' && rear === 'drum') {
        safeSpeed = hasRegen ? 28 : 22;
    }
    // Front hydraulic + rear regen/foot/none
    else if (front === 'hydraulic' && (rear === 'regen' || rear === 'foot' || rear === 'none')) {
        safeSpeed = hasRegen ? 28 : 22;
    }
    // Front mechanical + rear regen/foot/none
    else if (front === 'mechanical' && (rear === 'regen' || rear === 'foot' || rear === 'none')) {
        safeSpeed = hasRegen ? 25 : 20;
    }
    // Front drum + rear regen/foot/none
    else if (front === 'drum' && (rear === 'regen' || rear === 'foot' || rear === 'none')) {
        safeSpeed = hasRegen ? 20 : 15;
    }
    // Front drum + rear mechanical/hydraulic (odd config but handle it)
    else if (front === 'drum' && (rear === 'mechanical' || rear === 'hydraulic')) {
        safeSpeed = hasRegen ? 30 : 25;
    }
    // Fallback for any other front brake config
    else {
        safeSpeed = hasRegen ? 15 : 10;
    }

    // Dual motor regen bonus (+3 mph) - more effective braking with both wheels
    if (hasRegen && isDualMotor) {
        safeSpeed += 3;
    }

    return safeSpeed;
}

/**
 * Score brake adequacy (50 pts max).
 * Based on brake safe speed relative to scooter's top speed.
 *
 * Ratio scoring (brake safe speed / actual top speed):
 * - ratio >= 1.3: 50 pts (overkill)
 * - ratio >= 1.0: 45 pts (appropriate)
 * - ratio >= 0.8: 30 pts (marginal)
 * - ratio >= 0.6: 15 pts (inadequate)
 * - ratio < 0.6:  5 pts (dangerous)
 *
 * @param {Object} specs - Product specs object
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreBrakeAdequacy(specs) {
    const topSpeed = specs.manufacturer_top_speed;
    if (topSpeed == null || topSpeed <= 0) return { score: 0, maxPossible: 50 };

    const frontBrake = getNestedValue(specs, 'brakes.front');
    const rearBrake = getNestedValue(specs, 'brakes.rear');
    const hasRegen = getNestedValue(specs, 'brakes.regenerative') === true;

    const motorPosition = getNestedValue(specs, 'motor.motor_position');
    const isDualMotor = motorPosition && String(motorPosition).toLowerCase().includes('dual');

    const brakeSafeSpeed = calculateBrakeSafeSpeed(frontBrake, rearBrake, hasRegen, isDualMotor);

    if (brakeSafeSpeed === 0) {
        return { score: 5, maxPossible: 50 };
    }

    const ratio = brakeSafeSpeed / topSpeed;

    let score;
    if (ratio >= 1.3) score = 50;
    else if (ratio >= 1.0) score = 45;
    else if (ratio >= 0.8) score = 30;
    else if (ratio >= 0.6) score = 15;
    else score = 5;

    return { score, maxPossible: 50 };
}

/**
 * Score visibility/lights (25 pts max).
 * - Front + Rear lights: 20 pts
 * - Rear only: 10 pts (more critical for cars seeing you)
 * - Front only: 8 pts
 * - Neither: 0 pts
 * - Turn signals bonus: +5 pts
 *
 * @param {Object} specs - Product specs object
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreVisibility(specs) {
    let score = 0;

    // Check lights array
    const lights = getNestedValue(specs, 'lighting.lights');
    let hasFront = false;
    let hasRear = false;

    if (Array.isArray(lights)) {
        const lightsLower = lights.map(l => String(l).toLowerCase());
        hasFront = lightsLower.some(l => l.includes('front') || l.includes('headlight'));
        hasRear = lightsLower.some(l => l.includes('rear') || l.includes('tail') || l.includes('brake'));
    }

    // Score based on light configuration
    if (hasFront && hasRear) {
        score = 20; // Full visibility
    } else if (hasRear) {
        score = 10; // Rear more critical (cars behind you)
    } else if (hasFront) {
        score = 8;  // Front only
    } else {
        score = 0;  // No lights = dangerous at night
    }

    // Turn signals bonus
    const hasTurnSignals = getNestedValue(specs, 'lighting.turn_signals') === true;
    if (hasTurnSignals) {
        score += 5;
    }

    return { score: Math.min(25, score), maxPossible: 25 };
}

/**
 * Score tire safety (15 pts max).
 * Considers tire type and size relative to speed.
 *
 * Tire Type vs Speed (10 pts):
 * - Pneumatic at any speed: 10 pts
 * - Solid/honeycomb at ≤15 mph: 8 pts (acceptable for slow scooters)
 * - Solid/honeycomb at 15-25 mph: 5 pts (marginal)
 * - Solid/honeycomb at 25+ mph: 2 pts (dangerous - no grip/shock absorption)
 *
 * Tire Size Adequacy (5 pts):
 * - Based on size relative to speed
 * - Small tires at high speed = dangerous
 *
 * @param {Object} specs - Product specs object
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreTireSafety(specs) {
    const topSpeed = specs.manufacturer_top_speed || 20; // Default to moderate
    const tireType = String(getNestedValue(specs, 'wheels.tire_type') || '').toLowerCase();
    const frontSize = getNestedValue(specs, 'wheels.tire_size_front');
    const rearSize = getNestedValue(specs, 'wheels.tire_size_rear');

    let score = 0;

    // Tire type scoring (10 pts max)
    const isSolid = tireType.includes('solid') || tireType.includes('honeycomb');
    const isPneumatic = tireType.includes('pneumatic') && !tireType.includes('semi');
    const isSemiPneumatic = tireType.includes('semi') || tireType.includes('mixed');

    if (isPneumatic) {
        score += 10; // Pneumatic always safe
    } else if (isSemiPneumatic) {
        // Semi-pneumatic: okay up to ~30 mph
        if (topSpeed <= 20) score += 8;
        else if (topSpeed <= 30) score += 6;
        else score += 4;
    } else if (isSolid) {
        // Solid tires: only acceptable at low speeds
        if (topSpeed <= 15) score += 8;
        else if (topSpeed <= 25) score += 5;
        else score += 2; // Dangerous at high speed
    } else {
        // Unknown tire type - assume moderate
        score += 5;
    }

    // Tire size adequacy (5 pts max)
    const sizes = [frontSize, rearSize].filter(s => s != null && s > 0);
    if (sizes.length > 0) {
        const avgSize = sizes.reduce((a, b) => a + b, 0) / sizes.length;

        // Size/speed ratio: want at least 0.4" per mph for stability
        // 10" tires / 25 mph = 0.4 (good)
        // 8" tires / 30 mph = 0.27 (marginal)
        // 11" tires / 50 mph = 0.22 (marginal for speed)
        const sizeRatio = avgSize / topSpeed;

        if (sizeRatio >= 0.5) {
            score += 5; // Excellent - big tires for speed
        } else if (sizeRatio >= 0.35) {
            score += 4; // Good
        } else if (sizeRatio >= 0.25) {
            score += 3; // Adequate
        } else if (sizeRatio >= 0.18) {
            score += 2; // Marginal
        } else {
            score += 1; // Small tires for speed
        }
    }

    return { score: Math.min(15, score), maxPossible: 15 };
}

/**
 * Score stability from dimensions (10 pts max).
 * - Handlebar width: 5 pts (wider = more control)
 * - Deck width: 3 pts (wider = more stable stance)
 * - Deck length: 2 pts (longer = more stable)
 *
 * @param {Object} specs - Product specs object
 * @returns {{ score: number, maxPossible: number }}
 */
export function scoreStability(specs) {
    let score = 0;

    // Handlebar width (5 pts max)
    // 24"+ = 5 pts, 20-24" = 3 pts, <20" = 1 pt
    const handlebarWidth = getNestedValue(specs, 'dimensions.handlebar_width');
    if (handlebarWidth != null && handlebarWidth > 0) {
        if (handlebarWidth >= 24) score += 5;
        else if (handlebarWidth >= 21) score += 4;
        else if (handlebarWidth >= 18) score += 3;
        else if (handlebarWidth >= 15) score += 2;
        else score += 1;
    }

    // Deck width (3 pts max)
    // 7"+ = 3 pts, 5-7" = 2 pts, <5" = 1 pt
    const deckWidth = getNestedValue(specs, 'dimensions.deck_width');
    if (deckWidth != null && deckWidth > 0) {
        if (deckWidth >= 7) score += 3;
        else if (deckWidth >= 5.5) score += 2;
        else score += 1;
    }

    // Deck length (2 pts max)
    // 20"+ = 2 pts, 16-20" = 1 pt
    const deckLength = getNestedValue(specs, 'dimensions.deck_length');
    if (deckLength != null && deckLength > 0) {
        if (deckLength >= 20) score += 2;
        else if (deckLength >= 16) score += 1;
    }

    return { score: Math.min(10, score), maxPossible: 10 };
}

/**
 * Calculate Safety category score (0-100).
 * Combines brake adequacy, visibility, tire safety, and stability.
 *
 * Breakdown:
 * - Brake Adequacy: 50 pts (relative to speed)
 * - Visibility: 25 pts (lights + turn signals)
 * - Tire Safety: 15 pts (type + size vs speed)
 * - Stability: 10 pts (handlebar/deck dimensions)
 *
 * @param {Object} specs - Product specs object
 * @returns {number|null} Score 0-100 or null if missing required data
 */
export function calculateSafety(specs) {
    // Top speed is required for meaningful safety scoring
    const topSpeed = specs.manufacturer_top_speed;
    if (topSpeed == null || topSpeed <= 0) return null;

    const factors = [
        scoreBrakeAdequacy(specs),
        scoreVisibility(specs),
        scoreTireSafety(specs),
        scoreStability(specs),
    ];

    const totalScore = factors.reduce((sum, f) => sum + f.score, 0);
    const totalMaxPossible = factors.reduce((sum, f) => sum + f.maxPossible, 0);

    return Math.round((totalScore / totalMaxPossible) * 100);
}

// -----------------------------------------------------------------------------
// Features Scoring
// -----------------------------------------------------------------------------

/**
 * Calculate Features category score (0-100).
 * Counts features from the features array plus separate boolean fields.
 * @param {Object} specs - Product specs object
 * @returns {number} Score 0-100
 */
export function calculateFeatures(specs) {
    let featureCount = 0;

    // Count items in features array
    const featuresArray = specs.features;
    if (Array.isArray(featuresArray)) {
        featureCount += featuresArray.length;
    }

    // Add separate boolean features (not typically in features array)
    // Regenerative braking
    if (getNestedValue(specs, 'brakes.regenerative') === true) {
        featureCount += 1;
    }

    // Lights (front/rear/both)
    const lights = getNestedValue(specs, 'lighting.lights');
    if (Array.isArray(lights) && lights.length > 0) {
        featureCount += 1;
    }

    // Kickstand
    if (getNestedValue(specs, 'other.kickstand') === true) {
        featureCount += 1;
    }

    // Footrest
    if (getNestedValue(specs, 'other.footrest') === true) {
        featureCount += 1;
    }

    // Adjustable suspension
    if (getNestedValue(specs, 'suspension.adjustable') === true) {
        featureCount += 1;
    }

    // Foldable handlebars
    if (getNestedValue(specs, 'dimensions.foldable_handlebars') === true) {
        featureCount += 1;
    }

    // Scale to 0-100 (17 features = 100 pts)
    // Most well-equipped scooters have 12-18 features
    const score = Math.min(100, Math.round((featureCount / 17) * 100));

    return score;
}

// -----------------------------------------------------------------------------
// Master Category Calculator
// -----------------------------------------------------------------------------

/**
 * Calculate absolute score for a category.
 * Uses fixed thresholds, not relative comparison.
 * @param {Object} specs - Product specs object
 * @param {string} categoryName - Category name from CATEGORY_WEIGHTS
 * @param {string} productCategory - 'escooter', 'ebike', etc.
 * @returns {number} Score 0-100 (returns 0 for unimplemented categories)
 */
export function calculateAbsoluteCategoryScore(specs, categoryName, productCategory = 'escooter') {
    // Only escooter has absolute scoring implemented
    if (productCategory === 'escooter') {
        switch (categoryName) {
            case 'Motor Performance':
                return calculateMotorPerformance(specs) ?? 0;
            case 'Range & Battery':
                return calculateRangeBattery(specs) ?? 0;
            case 'Ride Quality':
                return calculateRideQuality(specs) ?? 0;
            case 'Portability':
                return calculatePortability(specs) ?? 0;
            case 'Maintenance':
                return calculateMaintenance(specs) ?? 0;
            case 'Features':
                return calculateFeatures(specs) ?? 0;
            case 'Safety':
                return calculateSafety(specs) ?? 0;
            default:
                return 0;
        }
    }

    // Other product categories not yet implemented
    return 0;
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
    // Absolute scoring exports
    scorePower,
    scoreMotorVoltage,
    scoreTopSpeed,
    scoreDualMotor,
    calculateMotorPerformance,
    scoreBatteryCapacity,
    scoreBatteryVoltage,
    scoreChargeTime,
    calculateRangeBattery,
    scoreSuspension,
    scoreTireType,
    scoreTireSize,
    scoreDeckAndHandlebar,
    scoreComfortExtras,
    calculateRideQuality,
    scoreWeight,
    scoreFoldedVolume,
    scoreSwappableBattery,
    calculatePortability,
    scoreMaintenanceTireType,
    scoreSelfHealing,
    scoreMaintenanceBrakes,
    scoreRegenBraking,
    scoreMaintenanceIP,
    calculateMaintenance,
    calculateFeatures,
    calculateBrakeSafeSpeed,
    scoreBrakeAdequacy,
    scoreVisibility,
    scoreTireSafety,
    scoreStability,
    calculateSafety,
    calculateAbsoluteCategoryScore,
};
