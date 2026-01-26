<?php
/**
 * Shared specs accessor methods for product scorers.
 *
 * Handles both flat and nested ACF data structures.
 *
 * @package ERH\Scoring
 */

declare(strict_types=1);

namespace ERH\Scoring;

/**
 * Trait providing spec data access methods.
 */
trait SpecsAccessor {

    /**
     * Product type group key for nested ACF structure.
     *
     * @var string|null
     */
    protected ?string $product_group_key = null;

    /**
     * Set the product group key for nested ACF structure lookups.
     *
     * @param string $product_type The product type.
     * @return void
     */
    protected function set_product_group_key(string $product_type): void {
        $group_keys = [
            'Electric Scooter'    => 'e-scooters',
            'Electric Bike'       => 'e-bikes',
            'Electric Skateboard' => 'e-skateboards',
            'Electric Unicycle'   => 'e-unicycles',
            'Hoverboard'          => 'hoverboards',
        ];

        $this->product_group_key = $group_keys[$product_type] ?? null;
    }

    /**
     * Get a value from specs array, handling both flat and nested structures.
     *
     * For e-scooters, ACF stores data under an 'e-scooters' group. This method
     * checks multiple locations in order:
     * 1. Direct nested path (e.g., 'motor.power_nominal' -> $specs['motor']['power_nominal'])
     * 2. Product-group-prefixed path (e.g., $specs['e-scooters']['motor']['power_nominal'])
     * 3. Flat fallback (e.g., $specs['nominal_motor_wattage'])
     *
     * @param array  $array      The array to search.
     * @param string $nested_path The dot-separated path for nested structure (e.g., 'motor.power_nominal').
     * @param string $flat_key   Optional flat key to check first (e.g., 'nominal_motor_wattage').
     * @return mixed The value or null if not found.
     */
    protected function get_nested_value(array $array, string $nested_path, string $flat_key = '') {
        // Try flat key first if provided.
        if ($flat_key !== '' && array_key_exists($flat_key, $array)) {
            return $array[$flat_key];
        }

        // Try direct nested path first (for pre-flattened data).
        $value = $this->traverse_path($array, $nested_path);
        if ($value !== null) {
            return $value;
        }

        // Try with product group prefix (for raw ACF data with e-scooters/etc wrapper).
        if ($this->product_group_key !== null) {
            $prefixed_path = $this->product_group_key . '.' . $nested_path;
            $value = $this->traverse_path($array, $prefixed_path);
            if ($value !== null) {
                return $value;
            }
        }

        // Try flat fallback based on nested path.
        return $this->get_flat_fallback($array, $nested_path);
    }

    /**
     * Traverse a dot-separated path in an array.
     *
     * @param array  $array The array to traverse.
     * @param string $path  The dot-separated path.
     * @return mixed The value or null if not found.
     */
    protected function traverse_path(array $array, string $path) {
        $keys  = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Get a top-level field value, checking both flat location and under product group.
     *
     * @param array  $array The specs array.
     * @param string $key   The field key to look up.
     * @return mixed The value or null if not found.
     */
    protected function get_top_level_value(array $array, string $key) {
        // Try flat/root location first.
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        // Try under product group (e.g., e-scooters.features).
        if ($this->product_group_key !== null && isset($array[$this->product_group_key][$key])) {
            return $array[$this->product_group_key][$key];
        }

        return null;
    }

    /**
     * Get flat field value based on nested path mapping.
     *
     * Maps nested paths to flat ACF field names for backward compatibility.
     *
     * @param array  $array       The specs array.
     * @param string $nested_path The nested path to map.
     * @return mixed The value or null if not found.
     */
    abstract protected function get_flat_fallback(array $array, string $nested_path);
}
