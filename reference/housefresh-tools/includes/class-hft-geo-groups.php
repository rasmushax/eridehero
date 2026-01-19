<?php
declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class HFT_Geo_Groups.
 *
 * Handles predefined geographic groupings for scrapers.
 * Allows users to select regions like "EU" instead of manually entering 27 country codes.
 */
class HFT_Geo_Groups {

    /**
     * Predefined geo groups with their country codes.
     */
    public const GROUPS = [
        'EU' => [
            'label' => 'European Union',
            'countries' => ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'],
        ],
        'NORDIC' => [
            'label' => 'Nordic Countries',
            'countries' => ['DK', 'SE', 'NO', 'FI', 'IS'],
        ],
        'DACH' => [
            'label' => 'DACH Region',
            'countries' => ['DE', 'AT', 'CH'],
        ],
        'BENELUX' => [
            'label' => 'Benelux',
            'countries' => ['BE', 'NL', 'LU'],
        ],
        'UK' => [
            'label' => 'United Kingdom',
            'countries' => ['GB'],
        ],
        'NA' => [
            'label' => 'North America',
            'countries' => ['US', 'CA', 'MX'],
        ],
        'GLOBAL' => [
            'label' => 'Global (All Countries)',
            'countries' => ['GLOBAL'],
        ],
    ];

    /**
     * Expand a mixed list of geos and groups into individual country codes.
     *
     * @param array|string $geos Comma-separated or array of geos/groups.
     * @return array Unique country codes.
     */
    public static function expand(array|string $geos): array {
        if (is_string($geos)) {
            $geos = array_map('trim', explode(',', $geos));
        }

        // Filter for extensibility
        $groups = apply_filters('hft_geo_groups', self::GROUPS);

        $expanded = [];
        foreach ($geos as $geo) {
            $geo = strtoupper(trim($geo));
            if (empty($geo)) {
                continue;
            }

            if (isset($groups[$geo])) {
                $expanded = array_merge($expanded, $groups[$geo]['countries']);
            } else {
                $expanded[] = $geo;
            }
        }

        return array_unique($expanded);
    }

    /**
     * Check if a country code is a group identifier.
     *
     * @param string $code The code to check.
     * @return bool True if the code is a group identifier.
     */
    public static function is_group(string $code): bool {
        $groups = apply_filters('hft_geo_groups', self::GROUPS);
        return isset($groups[strtoupper($code)]);
    }

    /**
     * Get all available groups for admin UI.
     *
     * @return array All available geo groups.
     */
    public static function get_all_groups(): array {
        return apply_filters('hft_geo_groups', self::GROUPS);
    }

    /**
     * Get groups as array for JavaScript.
     *
     * @return array Groups formatted for JavaScript.
     */
    public static function get_groups_for_js(): array {
        $groups = apply_filters('hft_geo_groups', self::GROUPS);
        $js_groups = [];

        foreach ($groups as $key => $group) {
            $js_groups[$key] = [
                'label' => $group['label'],
                'countries' => $group['countries'],
            ];
        }

        return $js_groups;
    }

    /**
     * Detect which groups are fully represented in a list of country codes.
     *
     * @param array|string $geos The country codes to check.
     * @return array Group identifiers that are fully present.
     */
    public static function detect_groups(array|string $geos): array {
        if (is_string($geos)) {
            $geos = array_map('trim', explode(',', $geos));
        }

        $geos = array_map('strtoupper', array_filter($geos));
        $groups = apply_filters('hft_geo_groups', self::GROUPS);
        $detected = [];

        foreach ($groups as $key => $group) {
            // Skip GLOBAL as it's a special flag
            if ($key === 'GLOBAL') {
                if (in_array('GLOBAL', $geos)) {
                    $detected[] = $key;
                }
                continue;
            }

            // Check if all countries in the group are present
            $all_present = true;
            foreach ($group['countries'] as $country) {
                if (!in_array($country, $geos)) {
                    $all_present = false;
                    break;
                }
            }

            if ($all_present) {
                $detected[] = $key;
            }
        }

        return $detected;
    }
}
