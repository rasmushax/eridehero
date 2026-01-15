<?php
/**
 * Listicle Helper Functions
 *
 * Functions for listicle block card display.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get key specs for listicle item card display.
 *
 * Returns an array of label/value pairs for display in a grid format.
 * Prioritizes the most important specs per product type.
 *
 * @param int    $product_id   Product ID.
 * @param string $category_key Category key (escooter, ebike, etc.).
 * @return array Array of ['label' => string, 'value' => string, 'icon' => string] items.
 */
function erh_get_listicle_key_specs( int $product_id, string $category_key ): array {
	$result = array();

	// Get product data from cache.
	$product_data = erh_get_product_cache_data( $product_id );
	$specs        = array();

	if ( $product_data && ! empty( $product_data['specs'] ) ) {
		$specs = is_array( $product_data['specs'] )
			? $product_data['specs']
			: maybe_unserialize( $product_data['specs'] );
	}

	if ( empty( $specs ) || ! is_array( $specs ) ) {
		return $result;
	}

	$nested_wrapper = erh_get_specs_wrapper_key( $category_key );

	// Helper to get spec.
	$get = function( $key ) use ( $specs, $nested_wrapper ) {
		return erh_get_spec_from_cache( $specs, $key, $nested_wrapper );
	};

	// Define key specs per product type (6 specs max).
	// Order: Tested Speed, Tested Range, Weight, Max Load, Battery Capacity, Nominal Power
	// Note: tested_* fields are at root level, others may be nested or at root
	switch ( $category_key ) {
		case 'escooter':
			// Tested Speed (root level field).
			$speed = $get( 'tested_top_speed' );
			if ( $speed ) {
				$result[] = array( 'label' => 'Tested Speed', 'value' => $speed . ' MPH', 'icon' => 'dashboard' );
			}

			// Tested Range (root level field).
			$range = $get( 'tested_range_regular' );
			if ( $range ) {
				$result[] = array( 'label' => 'Tested Range', 'value' => $range . ' miles', 'icon' => 'range' );
			}

			// Weight (try root, then nested).
			$weight = $get( 'weight' ) ?: $get( 'e-scooters.dimensions.weight' );
			if ( $weight ) {
				$result[] = array( 'label' => 'Weight', 'value' => $weight . ' lbs', 'icon' => 'weight' );
			}

			// Max Load (try root, then nested).
			$max_load = $get( 'max_load' ) ?: $get( 'e-scooters.dimensions.max_load' );
			if ( $max_load ) {
				$result[] = array( 'label' => 'Max Load', 'value' => $max_load . ' lbs', 'icon' => 'weight-scale' );
			}

			// Battery Capacity (try root, then nested).
			$battery = $get( 'battery_capacity' ) ?: $get( 'e-scooters.battery.capacity' );
			if ( $battery ) {
				$result[] = array( 'label' => 'Battery Capacity', 'value' => $battery . ' Wh', 'icon' => 'battery-charging' );
			}

			// Nominal Power (try root, then nested).
			$motor = $get( 'nominal_motor_wattage' ) ?: $get( 'e-scooters.motor.power_nominal' );
			if ( $motor ) {
				$result[] = array( 'label' => 'Nominal Power', 'value' => $motor . 'W', 'icon' => 'motor' );
			}
			break;

		case 'ebike':
			// Tested Speed (root level field).
			$speed = $get( 'tested_top_speed' );
			if ( $speed ) {
				$result[] = array( 'label' => 'Tested Speed', 'value' => $speed . ' MPH', 'icon' => 'dashboard' );
			}

			// Tested Range (root level field).
			$range = $get( 'tested_range_regular' );
			if ( $range ) {
				$result[] = array( 'label' => 'Tested Range', 'value' => $range . ' miles', 'icon' => 'range' );
			}

			// Weight.
			$weight = $get( 'weight' ) ?: $get( 'ebike_data.weight_and_capacity.weight' );
			if ( $weight ) {
				$result[] = array( 'label' => 'Weight', 'value' => $weight . ' lbs', 'icon' => 'weight' );
			}

			// Max Load.
			$max_load = $get( 'max_load' ) ?: $get( 'ebike_data.weight_and_capacity.weight_limit' );
			if ( $max_load ) {
				$result[] = array( 'label' => 'Max Load', 'value' => $max_load . ' lbs', 'icon' => 'weight-scale' );
			}

			// Battery Capacity.
			$battery = $get( 'battery_capacity' ) ?: $get( 'ebike_data.battery.capacity' );
			if ( $battery ) {
				$result[] = array( 'label' => 'Battery Capacity', 'value' => $battery . ' Wh', 'icon' => 'battery-charging' );
			}

			// Nominal Power.
			$motor = $get( 'nominal_motor_wattage' ) ?: $get( 'ebike_data.motor.power_nominal' );
			if ( $motor ) {
				$result[] = array( 'label' => 'Nominal Power', 'value' => $motor . 'W', 'icon' => 'motor' );
			}
			break;

		default:
			// Generic specs for other product types.
			$speed = $get( 'tested_top_speed' );
			if ( $speed ) {
				$result[] = array( 'label' => 'Tested Speed', 'value' => $speed . ' MPH', 'icon' => 'dashboard' );
			}

			$range = $get( 'tested_range_regular' );
			if ( $range ) {
				$result[] = array( 'label' => 'Tested Range', 'value' => $range . ' miles', 'icon' => 'range' );
			}

			$weight = $get( 'weight' );
			if ( $weight ) {
				$result[] = array( 'label' => 'Weight', 'value' => $weight . ' lbs', 'icon' => 'weight' );
			}

			$max_load = $get( 'max_load' );
			if ( $max_load ) {
				$result[] = array( 'label' => 'Max Load', 'value' => $max_load . ' lbs', 'icon' => 'weight-scale' );
			}

			$battery = $get( 'battery_capacity' );
			if ( $battery ) {
				$result[] = array( 'label' => 'Battery Capacity', 'value' => $battery . ' Wh', 'icon' => 'battery-charging' );
			}

			$motor = $get( 'nominal_motor_wattage' );
			if ( $motor ) {
				$result[] = array( 'label' => 'Nominal Power', 'value' => $motor . 'W', 'icon' => 'motor' );
			}
			break;
	}

	// Return max 6 specs.
	return array_slice( $result, 0, 6 );
}
