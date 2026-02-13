/**
 * E-Scooter Laws Map
 *
 * Vehicle-type wrapper for the shared laws-map module.
 * Defines e-scooter specific classification mapping and info box fields.
 */

import { initLawsMap, enumLabel } from './laws-map.js';

const CLASSIFICATION_MAP = {
    specific_escooter: { cssClass: 'state-legal',       label: 'Legal',              icon: 'check-circle',  iconColor: 'icon-green' },
    local_rule:        { cssClass: 'state-conditional',  label: 'Varies by Location', icon: 'interrogation', iconColor: 'icon-orange' },
    unclear_or_local:  { cssClass: 'state-conditional',  label: 'Unclear / Local',    icon: 'interrogation', iconColor: 'icon-orange' },
    prohibited:        { cssClass: 'state-prohibited',   label: 'Prohibited',         icon: 'cross-circle',  iconColor: 'icon-red' },
};

/**
 * Build the 6-item info box grid for an e-scooter state.
 */
function buildInfoBoxItems(data, clsMap) {
    const cls = clsMap[data.classification] || {};
    return [
        { icon: 'legal',             label: 'Status',   value: cls.label || 'Unknown', statusIcon: cls.icon, statusColor: cls.iconColor },
        { icon: 'age-alt',           label: 'Min Age',  value: data.minAge != null ? `${data.minAge} yrs` : 'N/A' },
        { icon: 'tachometer-fast',   label: 'Max Speed', value: data.maxSpeedMph != null ? `${data.maxSpeedMph} MPH` : 'N/A' },
        { icon: 'motorcycle-helmet', label: 'Helmet',   value: enumLabel(data.helmetRequired) },
        { icon: 'walking',           label: 'Sidewalk', value: enumLabel(data.sidewalkRiding) },
        { icon: 'road',              label: 'Street',   value: enumLabel(data.streetRiding) },
    ];
}

export function init(container) {
    initLawsMap(container, {
        classificationMap: CLASSIFICATION_MAP,
        buildInfoBoxItems,
    });
}
