/**
 * E-Bike Laws Map
 *
 * Vehicle-type wrapper for the shared laws-map module.
 * Defines e-bike specific classification mapping and info box fields,
 * including support for the three-class system.
 */

import { initLawsMap, enumLabel } from './laws-map.js';

const CLASSIFICATION_MAP = {
    three_class:        { cssClass: 'state-legal',       label: 'Three-Class System', icon: 'check-circle',  iconColor: 'icon-green' },
    bicycle_equivalent: { cssClass: 'state-legal',       label: 'Bicycle Equivalent', icon: 'check-circle',  iconColor: 'icon-green' },
    custom_definition:  { cssClass: 'state-conditional',  label: 'Custom Definition',  icon: 'interrogation', iconColor: 'icon-orange' },
    no_specific_law:    { cssClass: 'state-no-law',      label: 'No Specific Law',    icon: 'cross-circle',  iconColor: 'icon-muted' },
};

/**
 * Extract the maximum speed from e-bike data.
 * Prefers class 3 speed, then general, then class 2/1.
 */
function getMaxSpeed(data) {
    if (data.class3?.maxSpeedMph) return data.class3.maxSpeedMph;
    if (data.general?.maxSpeedMph) return data.general.maxSpeedMph;
    if (data.class2?.maxSpeedMph) return data.class2.maxSpeedMph;
    if (data.class1?.maxSpeedMph) return data.class1.maxSpeedMph;
    return null;
}

/**
 * Summarize helmet rules across classes.
 */
function getHelmetSummary(data) {
    if (data.general?.helmetRequired != null) {
        return enumLabel(data.general.helmetRequired);
    }
    const rules = [data.class1, data.class2, data.class3]
        .filter(Boolean)
        .map(c => c.helmetRequired)
        .filter(v => v != null);
    if (!rules.length) return 'N/A';
    const unique = [...new Set(rules)];
    if (unique.length === 1) return enumLabel(unique[0]);
    return 'Varies by Class';
}

/**
 * Build the 6-item info box grid for an e-bike state.
 */
function buildInfoBoxItems(data, clsMap) {
    const cls = clsMap[data.classification] || {};
    const maxSpeed = getMaxSpeed(data);

    return [
        { icon: 'legal',             label: 'Status',       value: cls.label || 'Unknown', statusIcon: cls.icon, statusColor: cls.iconColor },
        { icon: 'tachometer-fast',   label: 'Top Speed',    value: maxSpeed != null ? `${maxSpeed} MPH` : 'N/A' },
        { icon: 'idbadge',           label: 'License',      value: enumLabel(data.licenseRequired) },
        { icon: 'memo',              label: 'Registration', value: enumLabel(data.registrationRequired) },
        { icon: 'motorcycle-helmet', label: 'Helmet',       value: getHelmetSummary(data) },
        { icon: 'auction',           label: 'DUI',          value: enumLabel(data.duiApplies) },
    ];
}

export function init(container) {
    initLawsMap(container, {
        classificationMap: CLASSIFICATION_MAP,
        buildInfoBoxItems,
    });
}
