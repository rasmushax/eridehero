<?php
/**
 * Shared utilities for shortcode field access, unit conversion, and table building.
 *
 * @package ERH\Shortcodes
 */

declare(strict_types=1);

namespace ERH\Shortcodes;

/**
 * Helper class providing field mapping, unit conversion, and HTML table builder.
 */
class ProductDataHelper {

    // Unit conversion constants.
    public const MILE_TO_KM = 1.609344;
    public const LB_TO_KG   = 0.45359237;
    public const IN_TO_CM   = 2.54;
    public const FPS_TO_MPH = 0.6818;
    public const FT_TO_M    = 0.3048;

    /**
     * Known product-type ACF group keys.
     *
     * @var string[]
     */
    private const PRODUCT_GROUPS = ['e-scooters', 'e-skateboards'];

    /**
     * Maps flat field keys to group-relative nested paths.
     *
     * These paths are shared across product types. At runtime, the detected
     * product group (e.g. 'e-scooters', 'e-skateboards') is prepended.
     *
     * @var array<string, string>
     */
    private const GROUP_FIELD_MAP = [
        'weight'             => 'dimensions.weight',
        'max_load'           => 'dimensions.max_load',
        'battery_capacity'   => 'battery.capacity',
        'battery_voltage'    => 'battery.voltage',
        'ground_clearance'   => 'dimensions.ground_clearance',
        'ip_rating'          => 'other.ip_rating',
        'weather_resistance' => 'other.ip_rating',
    ];

    /**
     * Type-specific field overrides where nested paths differ between product types.
     *
     * Paths are relative to the product group key.
     *
     * @var array<string, array<string, string>>
     */
    private const TYPE_FIELD_MAP = [
        'e-scooters' => [
            'battery_amphours'  => 'battery.ah',
            'deck_length'       => 'dimensions.deck_length',
            'deck_width'        => 'dimensions.deck_width',
            'handlebar_width'   => 'dimensions.handlebar_width',
            'folded_width'      => 'dimensions.folded_width',
            'folded_height'     => 'dimensions.folded_height',
            'folded_depth'      => 'dimensions.folded_length',
            'folded_length'     => 'dimensions.folded_length',
            'unfolded_width'    => 'dimensions.unfolded_width',
            'unfolded_height'   => 'dimensions.unfolded_height',
            'unfolded_depth'    => 'dimensions.unfolded_length',
            'unfolded_length'   => 'dimensions.unfolded_length',
        ],
        'e-skateboards' => [
            'battery_amphours'  => 'battery.amphours',
            'deck_length'       => 'deck.length',
            'deck_width'        => 'deck.width',
        ],
    ];

    /**
     * Maps field names that were renamed (flat, not group-dependent).
     *
     * @var array<string, string>
     */
    private const FLAT_FIELD_MAP = [
        'acceleration:_0-15_mph' => 'acceleration_0_15_mph',
        'acceleration:_0-20_mph' => 'acceleration_0_20_mph',
        'acceleration:_0-25_mph' => 'acceleration_0_25_mph',
        'acceleration:_0-30_mph' => 'acceleration_0_30_mph',
        'acceleration:_0-to-top' => 'acceleration_0_to_top',
    ];

    /**
     * Per-request fields cache keyed by product ID.
     *
     * @var array<int, array|null>
     */
    private static array $fields_cache = [];

    /**
     * Get all ACF fields for a product, with per-request caching.
     *
     * @param int $id Product post ID.
     * @return array|null Fields array or null if not found.
     */
    public static function get_product_fields(int $id): ?array {
        if (array_key_exists($id, self::$fields_cache)) {
            return self::$fields_cache[$id];
        }

        $fields = get_fields($id);
        self::$fields_cache[$id] = is_array($fields) ? $fields : null;
        return self::$fields_cache[$id];
    }

    /**
     * Retrieve a spec value from a fields array.
     *
     * Lookup order:
     * 1. Direct key in fields array
     * 2. Flat field renames (acceleration colon → underscore)
     * 3. Type-specific override paths (e.g. e-scooters deck_length vs e-skateboards deck.length)
     * 4. Shared group field paths (e.g. {group}.dimensions.weight)
     * 5. Dot-path traversal if key contains dots
     *
     * @param array  $fields The product fields array.
     * @param string $key    The field key to look up.
     * @return mixed The value or null if not found.
     */
    public static function get_spec(array $fields, string $key) {
        // 1. Direct key.
        if (array_key_exists($key, $fields)) {
            return $fields[$key];
        }

        // 2. Flat field renames (not group-dependent).
        if (isset(self::FLAT_FIELD_MAP[$key])) {
            $mapped = self::FLAT_FIELD_MAP[$key];
            if (array_key_exists($mapped, $fields)) {
                return $fields[$mapped];
            }
        }

        // 3–4. Group-dependent lookups.
        $group = self::detect_group($fields);
        if ($group !== null) {
            // 3. Type-specific override.
            if (isset(self::TYPE_FIELD_MAP[$group][$key])) {
                $path = $group . '.' . self::TYPE_FIELD_MAP[$group][$key];
                $val  = self::traverse_path($fields, $path);
                if ($val !== null) {
                    return $val;
                }
            }

            // 4. Shared group path.
            if (isset(self::GROUP_FIELD_MAP[$key])) {
                $path = $group . '.' . self::GROUP_FIELD_MAP[$key];
                $val  = self::traverse_path($fields, $path);
                if ($val !== null) {
                    return $val;
                }
            }
        }

        // 5. Dot-path traversal.
        if (strpos($key, '.') !== false) {
            return self::traverse_path($fields, $key);
        }

        return null;
    }

    /**
     * Detect the product-type ACF group present in the fields array.
     *
     * @param array $fields The product fields array.
     * @return string|null The group key (e.g. 'e-scooters', 'e-skateboards') or null.
     */
    private static function detect_group(array $fields): ?string {
        foreach (self::PRODUCT_GROUPS as $group) {
            if (isset($fields[$group]) && is_array($fields[$group])) {
                return $group;
            }
        }
        return null;
    }

    /**
     * Traverse a dot-separated path in an array.
     *
     * @param array  $array The array to traverse.
     * @param string $path  Dot-separated path (e.g. 'e-scooters.battery.capacity').
     * @return mixed The value or null if not found.
     */
    private static function traverse_path(array $array, string $path) {
        $keys  = explode('.', $path);
        $value = $array;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return null;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Parse a comma-separated list of product IDs, resolving "this" via the manager.
     *
     * @param string           $ids Comma-separated IDs (may include "this").
     * @param ShortcodeManager $mgr The shortcode manager for resolving "this".
     * @return int[] Resolved product IDs.
     */
    public static function parse_product_ids(string $ids, ShortcodeManager $mgr): array {
        $raw    = array_map('trim', explode(',', $ids));
        $result = [];

        foreach ($raw as $item) {
            if ($item === 'this') {
                $this_id = $mgr->resolve_this_product();
                if ($this_id !== null) {
                    $result[] = $this_id;
                }
            } elseif (is_numeric($item) && (int) $item > 0) {
                $result[] = (int) $item;
            }
        }

        return $result;
    }

    /**
     * Convert a value using a multiplier and round to specified decimals.
     *
     * @param float $val  The value to convert.
     * @param float $mult The multiplier.
     * @param int   $dec  Decimal places (default 1).
     * @return float The converted value.
     */
    public static function convert(float $val, float $mult, int $dec = 1): float {
        return round($val * $mult, $dec);
    }

    /**
     * Build an HTML table wrapped in a styled figure element.
     *
     * Cell contents are assumed to be pre-escaped by the caller.
     *
     * @param string[] $headers Table column headers.
     * @param array[]  $rows    Array of row arrays (each cell is an HTML string).
     * @param string   $caption Optional table caption.
     * @return string The complete HTML table.
     */
    public static function build_table(array $headers, array $rows, string $caption = ''): string {
        $html = '<figure class="wp-block-table erh-shortcode-table minimalistic"><table>';

        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . esc_html($header) . '</th>';
        }
        $html .= '</tr></thead>';

        $html .= '<tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . $cell . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        if ($caption !== '') {
            $html .= '<figcaption>' . esc_html($caption) . '</figcaption>';
        }

        $html .= '</figure>';
        return $html;
    }
}
