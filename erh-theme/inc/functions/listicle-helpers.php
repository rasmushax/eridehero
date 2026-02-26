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
 * Get the spec registry for listicle key spec presets.
 *
 * Each entry maps a preset key to its label, value suffix, icon,
 * and data paths per product category (with 'default' fallback).
 *
 * @return array Spec registry keyed by preset slug.
 */
function erh_get_spec_registry(): array {
	return [
		'tested_speed'     => [
			'label'  => 'Tested Speed',
			'suffix' => ' MPH',
			'icon'   => 'dashboard',
			'paths'  => [
				'default' => [ 'tested_top_speed' ],
			],
		],
		'tested_range'     => [
			'label'  => 'Tested Range',
			'suffix' => ' miles',
			'icon'   => 'range',
			'paths'  => [
				'default' => [ 'tested_range_regular' ],
			],
		],
		'weight'           => [
			'label'  => 'Weight',
			'suffix' => ' lbs',
			'icon'   => 'weight',
			'paths'  => [
				'default'  => [ 'weight' ],
				'escooter' => [ 'weight', 'e-scooters.dimensions.weight' ],
				'ebike'    => [ 'weight', 'ebike_data.weight_and_capacity.weight' ],
				'euc'      => [ 'weight', 'dimensions.weight' ],
			],
		],
		'max_load'         => [
			'label'  => 'Max Load',
			'suffix' => ' lbs',
			'icon'   => 'weight-scale',
			'paths'  => [
				'default'  => [ 'max_load' ],
				'escooter' => [ 'max_load', 'e-scooters.dimensions.max_load' ],
				'ebike'    => [ 'max_load', 'ebike_data.weight_and_capacity.weight_limit' ],
				'euc'      => [ 'max_load', 'dimensions.max_load' ],
			],
		],
		'battery_capacity' => [
			'label'  => 'Battery',
			'suffix' => ' Wh',
			'icon'   => 'battery-charging',
			'paths'  => [
				'default'  => [ 'battery_capacity' ],
				'escooter' => [ 'battery_capacity', 'e-scooters.battery.capacity' ],
				'ebike'    => [ 'battery_capacity', 'ebike_data.battery.capacity' ],
				'euc'      => [ 'battery_capacity', 'battery.capacity' ],
			],
		],
		'nominal_power'    => [
			'label'  => 'Nominal Power',
			'suffix' => 'W',
			'icon'   => 'motor',
			'paths'  => [
				'default'  => [ 'nominal_motor_wattage' ],
				'escooter' => [ 'nominal_motor_wattage', 'e-scooters.motor.power_nominal' ],
				'ebike'    => [ 'nominal_motor_wattage', 'ebike_data.motor.power_nominal' ],
				'euc'      => [ 'nominal_motor_wattage', 'motor.power_nominal' ],
			],
		],
		'charging_time'    => [
			'label'  => 'Charge Time',
			'suffix' => ' hrs',
			'icon'   => 'battery-charging',
			'paths'  => [
				'default' => [ 'battery.charging_time' ],
				'ebike'   => [ 'battery.charge_time', 'ebike_data.battery.charge_time' ],
				'euc'     => [ 'battery.charging_time' ],
			],
		],
		'peak_power'       => [
			'label'  => 'Peak Power',
			'suffix' => 'W',
			'icon'   => 'motor',
			'paths'  => [
				'default' => [ 'motor.power_peak' ],
				'euc'     => [ 'motor.power_peak' ],
			],
		],
		'accel_0_15'       => [
			'label'  => '0-15 MPH',
			'suffix' => 's',
			'icon'   => 'stopwatch',
			'paths'  => [
				'default' => [ 'acceleration_0_15_mph' ],
			],
		],
		'accel_0_20'       => [
			'label'  => '0-20 MPH',
			'suffix' => 's',
			'icon'   => 'stopwatch',
			'paths'  => [
				'default' => [ 'acceleration_0_20_mph' ],
			],
		],
		'accel_0_25'       => [
			'label'  => '0-25 MPH',
			'suffix' => 's',
			'icon'   => 'stopwatch',
			'paths'  => [
				'default' => [ 'acceleration_0_25_mph' ],
			],
		],
		'accel_0_30'       => [
			'label'  => '0-30 MPH',
			'suffix' => 's',
			'icon'   => 'stopwatch',
			'paths'  => [
				'default' => [ 'acceleration_0_30_mph' ],
			],
		],
		'brake_distance'   => [
			'label'  => 'Brake Distance',
			'suffix' => ' ft',
			'icon'   => 'brake',
			'paths'  => [
				'default' => [ 'brake_distance' ],
			],
		],
		'hill_climb'       => [
			'label'  => 'Hill Climb',
			'suffix' => "\u{00B0}",
			'icon'   => 'mountain',
			'paths'  => [
				'default'     => [ 'hill_climbing' ],
				'eskateboard' => [ 'hill_climb_angle' ],
			],
		],
		'ip_rating'        => [
			'label'  => 'IP Rating',
			'suffix' => '',
			'icon'   => 'cloud-rain',
			'paths'  => [
				'default'    => [ 'other.ip_rating' ],
				'ebike'      => [ 'safety_and_compliance.ip_rating' ],
				'euc'        => [ 'safety.ip_rating' ],
				'hoverboard' => [ 'safety.ip_rating' ],
			],
		],
		'tire_size'        => [
			'label'  => 'Tire Size',
			'suffix' => '"',
			'icon'   => 'tire',
			'paths'  => [
				'default' => [ 'wheels.tire_size_front' ],
				'euc'     => [ 'wheel.tire_size' ],
			],
		],
		'claimed_speed'    => [
			'label'  => 'Claimed Speed',
			'suffix' => ' MPH',
			'icon'   => 'dashboard',
			'paths'  => [
				'default' => [ 'manufacturer_top_speed' ],
			],
		],
		'claimed_range'    => [
			'label'  => 'Claimed Range',
			'suffix' => ' miles',
			'icon'   => 'range',
			'paths'  => [
				'default' => [ 'manufacturer_range' ],
			],
		],
	];
}

/**
 * Resolve a single preset spec from the registry for a product.
 *
 * @param string $preset_key   Key from the spec registry.
 * @param int    $product_id   Product ID.
 * @param string $category_key Category key (escooter, ebike, etc.).
 * @return array|null ['label', 'value', 'icon'] or null if no data found.
 */
function erh_resolve_preset_spec( string $preset_key, int $product_id, string $category_key ): ?array {
	$registry = erh_get_spec_registry();

	if ( ! isset( $registry[ $preset_key ] ) ) {
		return null;
	}

	$spec = $registry[ $preset_key ];

	// Get product data from cache.
	$product_data = erh_get_product_cache_data( $product_id );
	$specs        = [];

	if ( $product_data && ! empty( $product_data['specs'] ) ) {
		$specs = is_array( $product_data['specs'] )
			? $product_data['specs']
			: maybe_unserialize( $product_data['specs'] );
	}

	if ( empty( $specs ) || ! is_array( $specs ) ) {
		return null;
	}

	$nested_wrapper = erh_get_specs_wrapper_key( $category_key );

	// Get paths for this category, falling back to default.
	$paths = $spec['paths'][ $category_key ] ?? $spec['paths']['default'] ?? [];

	// Try each path until we find a value.
	$value = null;
	foreach ( $paths as $path ) {
		$value = erh_get_spec_from_cache( $specs, $path, $nested_wrapper );
		if ( $value ) {
			break;
		}
	}

	if ( ! $value ) {
		return null;
	}

	// Format large numbers with commas (e.g. 33600 → 33,600).
	if ( is_numeric( $value ) && abs( (float) $value ) >= 1000 ) {
		$value = number_format( (float) $value );
	}

	$formatted = $value . $spec['suffix'];

	// Tire size: append width if available (e.g. 16" x 3").
	if ( 'tire_size' === $preset_key ) {
		$width_paths = [
			'euc' => [ 'wheel.tire_width' ],
		];
		$w_paths = $width_paths[ $category_key ] ?? [];
		foreach ( $w_paths as $w_path ) {
			$width = erh_get_spec_from_cache( $specs, $w_path, $nested_wrapper );
			if ( $width ) {
				$formatted = $value . '" x ' . $width . '"';
				break;
			}
		}
	}

	return [
		'label' => $spec['label'],
		'value' => $formatted,
		'icon'  => $spec['icon'],
	];
}

/**
 * Build key specs array from block-level overrides.
 *
 * @param array  $overrides    ACF repeater rows from key_specs_override field.
 * @param int    $product_id   Product ID.
 * @param string $category_key Category key.
 * @return array Array of ['label', 'value', 'icon'] items.
 */
function erh_build_key_specs_from_overrides( array $overrides, int $product_id, string $category_key ): array {
	$result = [];

	foreach ( $overrides as $row ) {
		$mode = $row['spec_mode'] ?? 'preset';

		if ( 'manual' === $mode ) {
			$label = trim( $row['manual_label'] ?? '' );
			$value = trim( $row['manual_value'] ?? '' );
			$icon  = $row['manual_icon'] ?? '';

			if ( $label && $value ) {
				$result[] = [
					'label' => $label,
					'value' => $value,
					'icon'  => $icon,
				];
			}
		} else {
			$preset_key = $row['spec_preset'] ?? '';

			if ( $preset_key ) {
				$spec = erh_resolve_preset_spec( $preset_key, $product_id, $category_key );
				if ( $spec ) {
					$result[] = $spec;
				}
			}
		}
	}

	return array_slice( $result, 0, 6 );
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

	// Helper to get spec (formats large numbers with commas).
	$get = function( $key ) use ( $specs, $nested_wrapper ) {
		$val = erh_get_spec_from_cache( $specs, $key, $nested_wrapper );
		if ( $val && is_numeric( $val ) && abs( (float) $val ) >= 1000 ) {
			$val = number_format( (float) $val );
		}
		return $val;
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

		case 'euc':
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
			$weight = $get( 'weight' ) ?: $get( 'dimensions.weight' );
			if ( $weight ) {
				$result[] = array( 'label' => 'Weight', 'value' => $weight . ' lbs', 'icon' => 'weight' );
			}

			// Battery Capacity.
			$battery = $get( 'battery_capacity' ) ?: $get( 'battery.capacity' );
			if ( $battery ) {
				$result[] = array( 'label' => 'Battery', 'value' => $battery . ' Wh', 'icon' => 'battery-charging' );
			}

			// Nominal Power.
			$motor = $get( 'nominal_motor_wattage' ) ?: $get( 'motor.power_nominal' );
			if ( $motor ) {
				$result[] = array( 'label' => 'Nominal Power', 'value' => $motor . 'W', 'icon' => 'motor' );
			}

			// Tire Size (with width if available).
			$tire_size = $get( 'wheel.tire_size' );
			if ( $tire_size ) {
				$tire_width = $get( 'wheel.tire_width' );
				$tire_value = $tire_width ? $tire_size . '" x ' . $tire_width . '"' : $tire_size . '"';
				$result[]   = array( 'label' => 'Tire Size', 'value' => $tire_value, 'icon' => 'tire' );
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
