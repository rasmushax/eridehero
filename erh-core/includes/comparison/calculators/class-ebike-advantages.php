<?php
/**
 * E-Bike Advantages Calculator.
 *
 * Calculates spec-based advantages for e-bike comparisons.
 * Contains e-bike-specific logic for composites (motor system, components, comfort),
 * rankings (motor brand, drivetrain, suspension), and advantage formatting.
 *
 * @package ERH\Comparison\Calculators
 */

declare(strict_types=1);

namespace ERH\Comparison\Calculators;

use ERH\Comparison\AdvantageCalculatorBase;
use ERH\Comparison\PriceBracketConfig;
use ERH\Config\SpecConfig;

/**
 * E-bike advantage calculator.
 */
class EbikeAdvantages extends AdvantageCalculatorBase {

	/**
	 * Product type this calculator handles.
	 *
	 * @var string
	 */
	private string $product_type = 'ebike';

	// =========================================================================
	// Ranking Constants
	// =========================================================================

	/**
	 * Motor brand quality ranking (best first).
	 */
	private const MOTOR_BRAND_RANKING = [
		'bosch'      => 1,
		'shimano'    => 1,
		'brose'      => 1,
		'fazua'      => 1,
		'tq'         => 2,
		'yamaha'     => 2,
		'giant'      => 2,
		'syncdrive'  => 2,
		'specialized' => 2,
		'mahle'      => 2,
		'bafang'     => 3,
		'mivice'     => 3,
		'generic'    => 5,
	];

	/**
	 * Motor position ranking (best first).
	 */
	private const MOTOR_POSITION_RANKING = [
		'mid'    => 1,
		'center' => 1,
		'crank'  => 1,
		'rear'   => 2,
		'hub'    => 2,
		'front'  => 3,
	];

	/**
	 * Sensor type ranking (best first).
	 */
	private const SENSOR_RANKING = [
		'torque'  => 1,
		'both'    => 1,
		'cadence' => 2,
		'none'    => 3,
	];

	/**
	 * Battery brand ranking (best first).
	 */
	private const BATTERY_BRAND_RANKING = [
		'samsung'   => 1,
		'lg'        => 1,
		'panasonic' => 1,
		'sony'      => 1,
		'bosch'     => 2,
		'shimano'   => 2,
		'brose'     => 2,
		'yamaha'    => 2,
		'generic'   => 4,
	];

	/**
	 * Brake type ranking (best first).
	 */
	private const BRAKE_TYPE_RANKING = [
		'hydraulic'  => 1,
		'mechanical' => 2,
		'disc'       => 2,
		'rim'        => 3,
		'v-brake'    => 3,
	];

	/**
	 * Drivetrain quality ranking (best first).
	 */
	private const DRIVETRAIN_RANKING = [
		'xtr'      => 1,
		'xt'       => 1,
		'eagle'    => 1,
		'ultegra'  => 1,
		'dura-ace' => 1,
		'deore'    => 2,
		'slx'      => 2,
		'105'      => 2,
		'cues'     => 3,
		'alivio'   => 3,
		'tourney'  => 4,
		'generic'  => 5,
	];

	/**
	 * Drive system ranking (best first).
	 */
	private const DRIVE_SYSTEM_RANKING = [
		'belt'  => 1,
		'chain' => 2,
	];

	/**
	 * Tire brand ranking (best first).
	 */
	private const TIRE_BRAND_RANKING = [
		'maxxis'      => 1,
		'schwalbe'    => 1,
		'continental' => 1,
		'pirelli'     => 1,
		'vittoria'    => 1,
		'michelin'    => 1,
		'wtb'         => 2,
		'kenda'       => 2,
		'cst'         => 3,
		'generic'     => 4,
	];

	/**
	 * Frame material ranking (best first).
	 */
	private const FRAME_MATERIAL_RANKING = [
		'carbon'   => 1,
		'cfrp'     => 1,
		'aluminum' => 2,
		'alloy'    => 2,
		'steel'    => 3,
		'chromoly' => 3,
	];

	/**
	 * Suspension type ranking (best first).
	 */
	private const SUSPENSION_RANKING = [
		'air'    => 1,
		'coil'   => 2,
		'spring' => 2,
		'rigid'  => 3,
		'none'   => 4,
	];

	/**
	 * Display type ranking (best first).
	 */
	private const DISPLAY_RANKING = [
		'color' => 1,
		'tft'   => 1,
		'lcd'   => 2,
		'mono'  => 2,
		'led'   => 3,
		'none'  => 4,
	];

	// =========================================================================
	// Interface Methods
	// =========================================================================

	/**
	 * Get the product type.
	 *
	 * @return string
	 */
	public function get_product_type(): string {
		return $this->product_type;
	}

	/**
	 * Calculate advantages for head-to-head (2 product) comparison.
	 *
	 * @param array $products Array of 2 product data arrays.
	 * @return array Array with 2 elements, each containing advantages for that product.
	 */
	public function calculate_head_to_head( array $products ): array {
		if ( count( $products ) !== 2 ) {
			return array_fill( 0, count( $products ), [] );
		}

		$this->max_advantages = 4;
		$this->threshold      = 3.0; // 3% minimum difference for numeric specs.

		$advantages = [ [], [] ];
		$specs      = $this->get_head_to_head_specs();

		foreach ( $specs as $spec_def ) {
			// Check if both products have maxed out advantages.
			if ( count( $advantages[0] ) >= $this->max_advantages &&
				 count( $advantages[1] ) >= $this->max_advantages ) {
				break;
			}

			$result = $this->compare_spec( $products, $spec_def );

			if ( $result && $this->can_add_advantage( $advantages, $result['winner'] ) ) {
				$advantages[ $result['winner'] ][] = $result['advantage'];
			}
		}

		return $advantages;
	}

	/**
	 * Calculate advantages for multi-product (3+) comparison.
	 *
	 * @param array $products Array of 3+ product data arrays.
	 * @return array|null Advantages array or null if not enough products.
	 */
	public function calculate_multi( array $products ): ?array {
		$count = count( $products );
		if ( $count < 3 ) {
			return array_fill( 0, $count, [] );
		}

		$advantages  = array_fill( 0, $count, [] );
		$multi_specs = $this->get_multi_comparison_specs();

		foreach ( $multi_specs as $spec_def ) {
			$result = $this->find_multi_winner( $products, $spec_def );
			if ( $result && $result['winner_idx'] !== null ) {
				$advantages[ $result['winner_idx'] ][] = $result['advantage'];
			}
		}

		// Add category score winners.
		$this->add_category_winner_advantages( $products, $advantages );

		return $advantages;
	}

	/**
	 * Calculate advantages and weaknesses for single product display.
	 *
	 * @param array  $product Single product data array.
	 * @param string $geo     Geo region code.
	 * @return array|null Analysis result or null.
	 */
	public function calculate_single( array $product, string $geo = 'US' ): ?array {
		$specs = $product['specs'] ?? [];

		// Get regional price for bracketing.
		$price_history = $product['price_history'] ?? [];
		$geo_pricing   = $price_history[ $geo ] ?? null;
		$current_price = $geo_pricing['current_price'] ?? null;

		// Determine comparison mode.
		$use_bracket   = false;
		$bracket       = null;
		$fallback_info = null;

		if ( $current_price && $current_price > 0 ) {
			$bracket     = PriceBracketConfig::get_bracket( (float) $current_price, 'ebike' );
			$use_bracket = true;
		} else {
			$fallback_info = [
				'reason'  => 'no_regional_price',
				'message' => "No {$geo} pricing available. Comparing against all e-bikes.",
			];
		}

		// Fetch comparison set.
		$comparison_set = $this->get_single_comparison_set( $bracket, $geo, $use_bracket );

		// Check if bracket has enough products.
		if ( $use_bracket && count( $comparison_set ) < PriceBracketConfig::MIN_BRACKET_SIZE ) {
			$use_bracket   = false;
			$fallback_info = [
				'reason'  => 'insufficient_bracket_size',
				'message' => 'Only ' . count( $comparison_set ) . ' products in bracket. Comparing against all e-bikes.',
			];
			$comparison_set = $this->get_single_comparison_set( null, $geo, false );
		}

		// Define specs to analyze.
		$analysis_specs = $this->get_single_analysis_specs();

		$advantages = [];
		$weaknesses = [];

		foreach ( $analysis_specs as $spec_def ) {
			$result = $this->analyze_single_spec( $product, $comparison_set, $spec_def, $geo );

			if ( ! $result ) {
				continue;
			}

			if ( $result['is_advantage'] ) {
				$advantages[] = $result['item'];
			} elseif ( $result['is_weakness'] ) {
				$weaknesses[] = $result['item'];
			}
		}

		// Sort by strength.
		usort( $advantages, fn( $a, $b ) => $b['percentile'] <=> $a['percentile'] );
		usort( $weaknesses, fn( $a, $b ) => $a['percentile'] <=> $b['percentile'] );

		// Calculate bracket average scores.
		$bracket_scores = $this->calculate_bracket_average_scores( $comparison_set );

		return [
			'advantages'      => $advantages,
			'weaknesses'      => $weaknesses,
			'comparison_mode' => $use_bracket ? 'bracket' : 'category',
			'bracket'         => $use_bracket ? $bracket : null,
			'products_in_set' => count( $comparison_set ),
			'bracket_scores'  => $bracket_scores,
			'fallback'        => $fallback_info,
		];
	}

	// =========================================================================
	// Head-to-Head Specs Definition
	// =========================================================================

	/**
	 * Get specs for head-to-head comparison.
	 *
	 * @return array Array of spec definitions.
	 */
	private function get_head_to_head_specs(): array {
		return [
			// Motor & Drive.
			[
				'key'           => 'motor.torque',
				'label'         => 'Torque',
				'unit'          => 'Nm',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'more',
			],
			[
				'key'           => 'motor.motor_brand',
				'label'         => 'Motor',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'motor_brand',
			],
			[
				'key'           => 'motor.motor_position',
				'label'         => 'Motor Position',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'motor_position',
			],
			[
				'key'           => 'motor.sensor_type',
				'label'         => 'Pedal Sensor',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'sensor',
			],
			[
				'key'           => 'motor.power_nominal',
				'label'         => 'Motor Power',
				'unit'          => 'W',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'more',
			],

			// Battery & Range.
			[
				'key'           => 'battery.battery_capacity',
				'label'         => 'Battery Capacity',
				'unit'          => 'Wh',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'more',
			],
			[
				'key'           => 'battery.range',
				'label'         => 'Range',
				'unit'          => 'mi',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'more',
			],
			[
				'key'           => 'battery.charge_time',
				'label'         => 'Charge Time',
				'unit'          => 'hrs',
				'higher_better' => false,
				'format'        => 'numeric',
				'diff_format'   => 'shorter',
			],
			[
				'key'           => 'battery.removable',
				'label'         => 'Removable Battery',
				'higher_better' => true,
				'format'        => 'boolean',
			],

			// Components.
			[
				'key'           => 'brakes.brake_type',
				'label'         => 'Brakes',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'brake_type',
			],
			[
				'key'           => 'brakes.rotor_size_front',
				'label'         => 'Rotor Size',
				'unit'          => 'mm',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'larger',
				'min_diff'      => 20,
			],
			[
				'key'           => 'drivetrain.derailleur',
				'label'         => 'Drivetrain',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'drivetrain',
			],
			[
				'key'           => 'drivetrain.drive_system',
				'label'         => 'Drive',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'drive_system',
			],
			[
				'key'           => 'drivetrain.gears',
				'label'         => 'Gears',
				'unit'          => 'speed',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'more',
				'min_diff'      => 2,
			],
			[
				'key'           => 'frame_and_geometry.frame_material',
				'label'         => 'Frame',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'frame_material',
			],
			[
				'key'           => 'wheels_and_tires.tire_brand',
				'label'         => 'Tires',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'tire_brand',
			],

			// Suspension & Comfort.
			[
				'key'           => 'suspension.front_suspension',
				'label'         => 'Front Suspension',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'suspension',
			],
			[
				'key'           => 'suspension.front_travel',
				'label'         => 'Fork Travel',
				'unit'          => 'mm',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'more',
				'min_diff'      => 20,
			],
			[
				'key'           => 'suspension.rear_suspension',
				'label'         => 'Rear Suspension',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'suspension',
			],
			[
				'key'           => 'wheels_and_tires.tire_width',
				'label'         => 'Tire Width',
				'unit'          => '"',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'larger',
				'min_diff'      => 0.3,
			],

			// Practicality.
			[
				'key'           => 'weight_and_capacity.weight',
				'label'         => 'Weight',
				'unit'          => 'lbs',
				'higher_better' => false,
				'format'        => 'numeric',
				'diff_format'   => 'lighter',
			],
			[
				'key'           => 'weight_and_capacity.weight_limit',
				'label'         => 'Weight Capacity',
				'unit'          => 'lbs',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'higher',
			],
			[
				'key'           => 'components.display',
				'label'         => 'Display',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'display',
			],
			[
				'key'           => 'components.app_compatible',
				'label'         => 'App',
				'higher_better' => true,
				'format'        => 'boolean',
			],
			[
				'key'           => 'speed_and_class.throttle',
				'label'         => 'Throttle',
				'higher_better' => true,
				'format'        => 'boolean',
			],
			[
				'key'           => 'integrated_features',
				'label'         => 'Features',
				'higher_better' => true,
				'format'        => 'feature_count',
				'min_diff'      => 2,
			],
		];
	}

	// =========================================================================
	// Multi-Comparison Specs Definition
	// =========================================================================

	/**
	 * Get specs for multi-product comparison.
	 *
	 * @return array Array of spec definitions.
	 */
	private function get_multi_comparison_specs(): array {
		return [
			[
				'key'           => 'motor.torque',
				'label'         => 'Most torque',
				'unit'          => 'Nm',
				'higher_better' => true,
				'format'        => 'numeric',
			],
			[
				'key'           => 'battery.battery_capacity',
				'label'         => 'Largest battery',
				'unit'          => 'Wh',
				'higher_better' => true,
				'format'        => 'numeric',
			],
			[
				'key'           => 'battery.range',
				'label'         => 'Longest range',
				'unit'          => 'mi',
				'higher_better' => true,
				'format'        => 'numeric',
				'require_all'   => true,
			],
			[
				'key'           => 'weight_and_capacity.weight',
				'label'         => 'Lightest',
				'unit'          => 'lbs',
				'higher_better' => false,
				'format'        => 'numeric',
			],
			[
				'key'           => 'weight_and_capacity.weight_limit',
				'label'         => 'Highest capacity',
				'unit'          => 'lbs',
				'higher_better' => true,
				'format'        => 'numeric',
			],
			[
				'key'           => 'wh_per_lb',
				'label'         => 'Best Wh/lb ratio',
				'unit'          => 'Wh/lb',
				'higher_better' => true,
				'format'        => 'decimal',
			],
			[
				'key'           => 'watts_per_lb',
				'label'         => 'Best power-to-weight',
				'unit'          => 'W/lb',
				'higher_better' => true,
				'format'        => 'decimal',
			],
			[
				'key'           => 'suspension.front_travel',
				'label'         => 'Most suspension travel',
				'unit'          => 'mm',
				'higher_better' => true,
				'format'        => 'numeric',
			],
			[
				'key'           => 'drivetrain.gears',
				'label'         => 'Most gears',
				'unit'          => 'speed',
				'higher_better' => true,
				'format'        => 'numeric',
			],
		];
	}

	// =========================================================================
	// Single Analysis Specs Definition
	// =========================================================================

	/**
	 * Get specs for single product analysis.
	 *
	 * @return array Array of spec definitions.
	 */
	private function get_single_analysis_specs(): array {
		return [
			// Value metrics (lower is better - price efficiency).
			[
				'key'           => 'value_metrics.price_per_wh',
				'label'         => 'Battery Value',
				'unit'          => '$/Wh',
				'higher_better' => false,
				'is_value'      => true,
			],
			[
				'key'           => 'value_metrics.price_per_nm',
				'label'         => 'Torque Value',
				'unit'          => '$/Nm',
				'higher_better' => false,
				'is_value'      => true,
			],
			[
				'key'           => 'value_metrics.price_per_watt',
				'label'         => 'Motor Value',
				'unit'          => '$/W',
				'higher_better' => false,
				'is_value'      => true,
			],

			// Raw performance specs.
			[
				'key'           => 'motor.torque',
				'label'         => 'Torque',
				'unit'          => 'Nm',
				'higher_better' => true,
			],
			[
				'key'           => 'motor.power_nominal',
				'label'         => 'Motor Power',
				'unit'          => 'W',
				'higher_better' => true,
			],
			[
				'key'           => 'battery.battery_capacity',
				'label'         => 'Battery Capacity',
				'unit'          => 'Wh',
				'higher_better' => true,
			],
			// Charge time removed - too dependent on battery size to be meaningful.
			[
				'key'           => 'weight_and_capacity.weight',
				'label'         => 'Weight',
				'unit'          => 'lbs',
				'higher_better' => false,
			],
			[
				'key'             => 'weight_and_capacity.weight_limit',
				'label'           => 'Weight Capacity',
				'unit'            => 'lbs',
				'higher_better'   => true,
				'absolute_thresh' => true,  // Uses absolute thresholds - 300 lbs is fine for most.
			],
			// These specs use absolute thresholds, not percentile comparison.
			[
				'key'             => 'drivetrain.gears',
				'label'           => 'Gear Count',
				'unit'            => 'gears',
				'higher_better'   => true,
				'absolute_thresh' => true,
			],
			[
				'key'             => 'brakes.rotor_size_front',
				'label'           => 'Rotor Size',
				'unit'            => 'mm',
				'higher_better'   => true,
				'absolute_thresh' => true,
			],
			[
				'key'             => 'suspension.front_travel',
				'label'           => 'Fork Travel',
				'unit'            => 'mm',
				'higher_better'   => true,
				'absolute_thresh' => true,  // 60mm is fine - only flag if truly minimal.
			],
			[
				'key'           => 'wheels_and_tires.tire_width',
				'label'         => 'Tire Width',
				'unit'          => '"',
				'higher_better' => true,
			],

			// Efficiency metrics.
			[
				'key'           => 'wh_per_lb',
				'label'         => 'Energy Density',
				'unit'          => 'Wh/lb',
				'higher_better' => true,
			],
			[
				'key'           => 'watts_per_lb',
				'label'         => 'Power-to-Weight',
				'unit'          => 'W/lb',
				'higher_better' => true,
			],

			// Score-based composites.
			[
				'key'            => 'motor_drive',
				'label'          => 'Motor System',
				'higher_better'  => true,
				'is_score_based' => true,
			],
			[
				'key'            => 'battery_range',
				'label'          => 'Battery System',
				'higher_better'  => true,
				'is_score_based' => true,
			],
			[
				'key'            => 'component_quality',
				'label'          => 'Components',
				'higher_better'  => true,
				'is_score_based' => true,
			],
			[
				'key'            => 'comfort',
				'label'          => 'Ride Comfort',
				'higher_better'  => true,
				'is_score_based' => true,
			],
			[
				'key'            => 'practicality',
				'label'          => 'Practicality',
				'higher_better'  => true,
				'is_score_based' => true,
			],

			// Descriptive specs.
			[
				'key'            => 'ip_rating',
				'label'          => 'Weather Resistance',
				'higher_better'  => true,
				'is_descriptive' => true,
			],
		];
	}

	// =========================================================================
	// Head-to-Head Comparison Logic
	// =========================================================================

	/**
	 * Compare a spec between two products.
	 *
	 * @param array $products Two products to compare.
	 * @param array $spec_def Spec definition.
	 * @return array|null Result with winner and advantage, or null.
	 */
	private function compare_spec( array $products, array $spec_def ): ?array {
		$key    = $spec_def['key'];
		$format = $spec_def['format'] ?? 'numeric';

		// Get values from both products.
		$val_a = $this->get_ebike_spec_value( $products[0]['specs'], $key );
		$val_b = $this->get_ebike_spec_value( $products[1]['specs'], $key );

		if ( $val_a === null || $val_b === null ) {
			return null;
		}

		switch ( $format ) {
			case 'numeric':
				return $this->compare_numeric( $val_a, $val_b, $spec_def );

			case 'ranked':
				return $this->compare_ranked( $val_a, $val_b, $spec_def );

			case 'boolean':
				return $this->compare_boolean( $val_a, $val_b, $spec_def );

			case 'feature_count':
				return $this->compare_feature_count( $products, $spec_def );

			default:
				return null;
		}
	}

	/**
	 * Compare numeric values.
	 *
	 * @param mixed $val_a    Product A value.
	 * @param mixed $val_b    Product B value.
	 * @param array $spec_def Spec definition.
	 * @return array|null Result or null.
	 */
	private function compare_numeric( $val_a, $val_b, array $spec_def ): ?array {
		if ( ! is_numeric( $val_a ) || ! is_numeric( $val_b ) ) {
			return null;
		}

		$a = (float) $val_a;
		$b = (float) $val_b;

		if ( abs( $a - $b ) < 0.001 ) {
			return null;
		}

		$higher_better = $spec_def['higher_better'] ?? true;
		$min_diff      = $spec_def['min_diff'] ?? null;

		$diff     = abs( $a - $b );
		$base     = $higher_better ? min( $a, $b ) : max( $a, $b );
		$pct_diff = $base > 0 ? ( $diff / $base ) * 100 : 0;

		// Check thresholds.
		if ( $min_diff !== null ) {
			if ( $diff < $min_diff ) {
				return null;
			}
		} elseif ( $pct_diff < $this->threshold ) {
			return null;
		}

		// Determine winner.
		if ( $higher_better ) {
			$winner_idx = $a > $b ? 0 : 1;
		} else {
			$winner_idx = $a < $b ? 0 : 1;
		}

		$winner_val = $winner_idx === 0 ? $a : $b;
		$loser_val  = $winner_idx === 0 ? $b : $a;

		return [
			'winner'    => $winner_idx,
			'advantage' => $this->format_numeric_advantage( $spec_def, $diff, $winner_val, $loser_val, $winner_idx ),
		];
	}

	/**
	 * Format numeric advantage with proper casing.
	 *
	 * @param array $spec_def   Spec definition.
	 * @param float $diff       Difference value.
	 * @param float $winner_val Winner's value.
	 * @param float $loser_val  Loser's value.
	 * @param int   $winner_idx Winner index.
	 * @return array Advantage data.
	 */
	private function format_numeric_advantage( array $spec_def, float $diff, float $winner_val, float $loser_val, int $winner_idx ): array {
		$label       = $spec_def['label'] ?? '';
		$unit        = $spec_def['unit'] ?? '';
		$diff_format = $spec_def['diff_format'] ?? 'more';
		$key         = $spec_def['key'] ?? '';

		// Format numbers.
		$diff_fmt   = $this->format_spec_number( $diff );
		$winner_fmt = $this->format_spec_number( $winner_val );
		$loser_fmt  = $this->format_spec_number( $loser_val );

		// Build text with proper casing (label lowercase mid-sentence).
		$label_lower = strtolower( $label );

		// Special handling for gears (unit='speed').
		if ( $unit === 'speed' ) {
			$text       = "{$diff_fmt} more gears";
			$comparison = "{$winner_fmt}-speed vs {$loser_fmt}-speed";
		} else {
			// Standard formatting.
			$unit_with_space = $unit ? "{$unit} " : '';

			switch ( $diff_format ) {
				case 'more':
					$text = "{$diff_fmt} {$unit_with_space}more {$label_lower}";
					break;
				case 'larger':
					$text = "{$diff_fmt} {$unit_with_space}larger {$label_lower}";
					break;
				case 'higher':
					$text = "{$diff_fmt} {$unit_with_space}higher {$label_lower}";
					break;
				case 'lighter':
					$text = "{$diff_fmt} {$unit} lighter";
					break;
				case 'shorter':
					$text = "{$diff_fmt} {$unit} shorter {$label_lower}";
					break;
				default:
					$text = "{$diff_fmt} {$unit_with_space}more {$label_lower}";
			}

			// Build comparison string.
			$unit_str   = $unit ? " {$unit}" : '';
			$comparison = "{$winner_fmt}{$unit_str} vs {$loser_fmt}{$unit_str}";
		}

		// Capitalize first letter only and clean up extra spaces.
		$text = ucfirst( trim( preg_replace( '/\s+/', ' ', $text ) ) );

		return [
			'text'       => $text,
			'comparison' => $comparison,
			'winner'     => $winner_idx,
			'spec_key'   => $key,
		];
	}

	/**
	 * Compare ranked values.
	 *
	 * @param mixed $val_a    Product A value.
	 * @param mixed $val_b    Product B value.
	 * @param array $spec_def Spec definition.
	 * @return array|null Result or null.
	 */
	private function compare_ranked( $val_a, $val_b, array $spec_def ): ?array {
		$ranking_type = $spec_def['ranking'] ?? '';
		$ranking      = $this->get_ranking_map( $ranking_type );

		if ( empty( $ranking ) ) {
			return null;
		}

		$rank_a = $this->get_rank_value( $val_a, $ranking );
		$rank_b = $this->get_rank_value( $val_b, $ranking );

		if ( $rank_a === null || $rank_b === null || $rank_a === $rank_b ) {
			return null;
		}

		// Lower rank number is better.
		$winner_idx  = $rank_a < $rank_b ? 0 : 1;
		$winner_val  = $this->to_string( $winner_idx === 0 ? $val_a : $val_b );
		$loser_val   = $this->to_string( $winner_idx === 0 ? $val_b : $val_a );

		// Format values with consistent casing.
		$winner_display = $this->format_ranked_value( $winner_val, $ranking_type );
		$loser_display  = $this->format_ranked_value( $loser_val, $ranking_type );

		return [
			'winner'    => $winner_idx,
			'advantage' => [
				'text'       => 'Better ' . strtolower( $spec_def['label'] ),
				'comparison' => $winner_display . ' vs ' . $loser_display,
				'winner'     => $winner_idx,
				'spec_key'   => $spec_def['key'],
			],
		];
	}

	/**
	 * Format ranked value for display with proper casing.
	 *
	 * @param string $value        Raw value.
	 * @param string $ranking_type Type of ranking.
	 * @return string Formatted value.
	 */
	private function format_ranked_value( string $value, string $ranking_type ): string {
		$lower = strtolower( $value );

		// Acronyms that should stay uppercase.
		$acronyms = [ 'tft', 'lcd', 'led', 'xt', 'xtr', 'slx' ];
		if ( in_array( $lower, $acronyms, true ) ) {
			return strtoupper( $value );
		}

		// Proper nouns / brand names - capitalize first letter of each word.
		$brand_types = [ 'motor_brand', 'battery_brand', 'tire_brand', 'drivetrain' ];
		if ( in_array( $ranking_type, $brand_types, true ) ) {
			return ucwords( $lower );
		}

		// Capitalize first letter for everything else.
		return ucfirst( $lower );
	}

	/**
	 * Compare boolean values.
	 *
	 * @param mixed $val_a    Product A value.
	 * @param mixed $val_b    Product B value.
	 * @param array $spec_def Spec definition.
	 * @return array|null Result or null.
	 */
	private function compare_boolean( $val_a, $val_b, array $spec_def ): ?array {
		$bool_a = $this->to_boolean( $val_a );
		$bool_b = $this->to_boolean( $val_b );

		if ( $bool_a === $bool_b ) {
			return null;
		}

		$winner_idx = $bool_a ? 0 : 1;

		return [
			'winner'    => $winner_idx,
			'advantage' => [
				'text'       => 'Has ' . strtolower( $spec_def['label'] ),
				'comparison' => 'yes vs no',
				'winner'     => $winner_idx,
				'spec_key'   => $spec_def['key'],
			],
		];
	}

	/**
	 * Compare feature counts.
	 *
	 * @param array $products Products to compare.
	 * @param array $spec_def Spec definition.
	 * @return array|null Result or null.
	 */
	private function compare_feature_count( array $products, array $spec_def ): ?array {
		$features_a = $this->count_integrated_features( $products[0]['specs'] );
		$features_b = $this->count_integrated_features( $products[1]['specs'] );

		$min_diff = $spec_def['min_diff'] ?? 2;
		$diff     = abs( $features_a - $features_b );

		if ( $diff < $min_diff ) {
			return null;
		}

		$winner_idx   = $features_a > $features_b ? 0 : 1;
		$winner_count = max( $features_a, $features_b );
		$loser_count  = min( $features_a, $features_b );

		return [
			'winner'    => $winner_idx,
			'advantage' => [
				'text'       => 'More features',
				'comparison' => "{$winner_count} vs {$loser_count}",
				'winner'     => $winner_idx,
				'spec_key'   => 'integrated_features',
			],
		];
	}

	// =========================================================================
	// Multi-Comparison Logic
	// =========================================================================

	/**
	 * Find the winner for a spec across multiple products.
	 *
	 * @param array $products Array of products.
	 * @param array $spec_def Spec definition.
	 * @return array|null Result with winner_idx and advantage.
	 */
	private function find_multi_winner( array $products, array $spec_def ): ?array {
		$key           = $spec_def['key'];
		$require_all   = $spec_def['require_all'] ?? false;
		$higher_better = $spec_def['higher_better'] ?? true;
		$format        = $spec_def['format'] ?? 'numeric';

		// Collect values.
		$values = [];
		foreach ( $products as $idx => $product ) {
			$value = $this->get_ebike_spec_value( $product['specs'], $key );
			$values[ $idx ] = $value;
		}

		// Check require_all.
		if ( $require_all ) {
			foreach ( $values as $v ) {
				if ( $v === null || $v === '' ) {
					return null;
				}
			}
		}

		// Find winner.
		$winner_idx    = null;
		$winner_value  = null;
		$display_value = null;

		if ( $format === 'numeric' || $format === 'decimal' ) {
			$result        = $this->find_numeric_winner( $values, $higher_better );
			$winner_idx    = $result['idx'];
			$winner_value  = $result['value'];

			if ( $winner_value !== null ) {
				$decimals      = $format === 'decimal' ? 2 : 0;
				$display_value = number_format( (float) $winner_value, $decimals ) . ' ' . ( $spec_def['unit'] ?? '' );
			}
		}

		if ( $winner_idx === null ) {
			return null;
		}

		return [
			'winner_idx' => $winner_idx,
			'advantage'  => [
				'text'       => $spec_def['label'],
				'comparison' => trim( $display_value ),
				'spec_key'   => $key,
			],
		];
	}

	/**
	 * Find numeric winner among values.
	 *
	 * @param array $values       Values indexed by product index.
	 * @param bool  $higher_better Whether higher is better.
	 * @return array With 'idx' and 'value'.
	 */
	private function find_numeric_winner( array $values, bool $higher_better ): array {
		$best_value   = null;
		$best_indices = [];

		foreach ( $values as $idx => $value ) {
			if ( $value === null || $value === '' || ! is_numeric( $value ) ) {
				continue;
			}

			$num = (float) $value;

			if ( $best_value === null ) {
				$best_value   = $num;
				$best_indices = [ $idx ];
				continue;
			}

			$is_better = $higher_better ? ( $num > $best_value ) : ( $num < $best_value );
			$is_equal  = abs( $num - $best_value ) < 0.001;

			if ( $is_better ) {
				$best_value   = $num;
				$best_indices = [ $idx ];
			} elseif ( $is_equal ) {
				$best_indices[] = $idx;
			}
		}

		$winner_idx = count( $best_indices ) === 1 ? $best_indices[0] : null;

		return [ 'idx' => $winner_idx, 'value' => $best_value ];
	}

	/**
	 * Add category score winner advantages.
	 *
	 * @param array $products   Products array.
	 * @param array $advantages Advantages array (modified in place).
	 */
	private function add_category_winner_advantages( array $products, array &$advantages ): void {
		$score_categories = [
			'motor_drive'       => 'Best motor system',
			'battery_range'     => 'Best battery',
			'component_quality' => 'Best components',
			'comfort'           => 'Best comfort',
			'practicality'      => 'Most practical',
		];

		foreach ( $score_categories as $score_key => $label ) {
			$winner = $this->find_category_score_winner( $products, $score_key );
			if ( $winner !== null ) {
				$winner_specs = $products[ $winner ]['specs'] ?? [];

				// Get descriptive summary for multi-comparison (always shows something).
				$details = $this->get_multi_comparison_summary( $score_key, $winner_specs );

				// Only show if we have meaningful details.
				if ( empty( $details ) ) {
					continue;
				}

				$advantages[ $winner ][] = [
					'text'       => $label,
					'comparison' => ucfirst( $details ),
					'spec_key'   => "_{$score_key}",
				];
			}
		}
	}

	/**
	 * Get summary text for multi-comparison category winner.
	 *
	 * Unlike get_score_details() which uses thresholds, this always
	 * produces a description of the key specs.
	 *
	 * @param string $key   Score key.
	 * @param array  $specs Product specs.
	 * @return string Summary text.
	 */
	private function get_multi_comparison_summary( string $key, array $specs ): string {
		return match ( $key ) {
			'motor_drive'       => $this->get_motor_summary( $specs ),
			'battery_range'     => $this->get_battery_summary( $specs ),
			'component_quality' => $this->get_component_summary( $specs ),
			'comfort'           => $this->get_comfort_summary( $specs ),
			'practicality'      => $this->get_practicality_summary( $specs ),
			default             => '',
		};
	}

	/**
	 * Get motor system summary for winner.
	 *
	 * @param array $specs Product specs.
	 * @return string Summary.
	 */
	private function get_motor_summary( array $specs ): string {
		$details = [];

		$motor_brand = $this->to_string( $this->get_ebike_spec_value( $specs, 'motor.motor_brand' ) );
		$position    = $this->to_string( $this->get_ebike_spec_value( $specs, 'motor.motor_position' ) );
		$torque      = $this->get_ebike_spec_value( $specs, 'motor.torque' );
		$sensor      = $this->to_string( $this->get_ebike_spec_value( $specs, 'motor.sensor_type' ) );

		if ( $motor_brand ) {
			$details[] = ucfirst( strtolower( $motor_brand ) );
		}
		if ( $position ) {
			$pos_lower = strtolower( $position );
			if ( strpos( $pos_lower, 'mid' ) !== false || strpos( $pos_lower, 'center' ) !== false ) {
				$details[] = 'mid-drive';
			} elseif ( strpos( $pos_lower, 'hub' ) !== false ) {
				$details[] = 'hub motor';
			}
		}
		if ( $torque && is_numeric( $torque ) ) {
			$details[] = (int) $torque . 'Nm';
		}
		if ( $sensor ) {
			$sensor_lower = strtolower( $sensor );
			if ( strpos( $sensor_lower, 'torque' ) !== false ) {
				$details[] = 'torque sensor';
			}
		}

		return implode( ', ', $details );
	}

	/**
	 * Get battery system summary for winner.
	 *
	 * @param array $specs Product specs.
	 * @return string Summary.
	 */
	private function get_battery_summary( array $specs ): string {
		$details = [];

		$capacity  = $this->get_ebike_spec_value( $specs, 'battery.battery_capacity' );
		$brand     = $this->to_string( $this->get_ebike_spec_value( $specs, 'battery.battery_brand' ) );
		$removable = $this->get_ebike_spec_value( $specs, 'battery.removable' );
		$range     = $this->get_ebike_spec_value( $specs, 'battery.range' );

		if ( $capacity && is_numeric( $capacity ) ) {
			$details[] = (int) $capacity . 'Wh';
		}
		if ( $brand ) {
			$brand_lower = strtolower( $brand );
			if ( in_array( $brand_lower, [ 'samsung', 'lg', 'panasonic' ], true ) ) {
				$details[] = ucfirst( $brand_lower ) . ' cells';
			}
		}
		if ( $this->to_boolean( $removable ) ) {
			$details[] = 'removable';
		}
		if ( $range && is_numeric( $range ) ) {
			$details[] = (int) $range . ' mi range';
		}

		return implode( ', ', $details );
	}

	/**
	 * Get component quality summary for winner.
	 *
	 * @param array $specs Product specs.
	 * @return string Summary.
	 */
	private function get_component_summary( array $specs ): string {
		$details = [];

		$brake_type = $this->to_string( $this->get_ebike_spec_value( $specs, 'brakes.brake_type' ) );
		$drivetrain = $this->to_string( $this->get_ebike_spec_value( $specs, 'drivetrain.derailleur' ) );
		$frame      = $this->to_string( $this->get_ebike_spec_value( $specs, 'frame_and_geometry.frame_material' ) );
		$tire_brand = $this->to_string( $this->get_ebike_spec_value( $specs, 'wheels_and_tires.tire_brand' ) );

		if ( $brake_type ) {
			$brake_lower = strtolower( $brake_type );
			if ( strpos( $brake_lower, 'hydraulic' ) !== false ) {
				$details[] = 'hydraulic brakes';
			}
		}
		if ( $drivetrain ) {
			$rank = $this->get_rank_value( $drivetrain, self::DRIVETRAIN_RANKING );
			if ( $rank <= 2 ) {
				$details[] = ucfirst( strtolower( $drivetrain ) );
			}
		}
		if ( $frame ) {
			$frame_lower = strtolower( $frame );
			if ( strpos( $frame_lower, 'carbon' ) !== false ) {
				$details[] = 'carbon frame';
			} elseif ( strpos( $frame_lower, 'aluminum' ) !== false || strpos( $frame_lower, 'alloy' ) !== false ) {
				$details[] = 'alloy frame';
			}
		}
		if ( $tire_brand ) {
			$rank = $this->get_rank_value( $tire_brand, self::TIRE_BRAND_RANKING );
			if ( $rank <= 1 ) {
				$details[] = ucfirst( strtolower( $tire_brand ) ) . ' tires';
			}
		}

		return implode( ', ', $details );
	}

	/**
	 * Get comfort summary for winner.
	 *
	 * @param array $specs Product specs.
	 * @return string Summary.
	 */
	private function get_comfort_summary( array $specs ): string {
		$details = [];

		$front_susp = $this->to_string( $this->get_ebike_spec_value( $specs, 'suspension.front_suspension' ) );
		$rear_susp  = $this->to_string( $this->get_ebike_spec_value( $specs, 'suspension.rear_suspension' ) );
		$travel     = $this->get_ebike_spec_value( $specs, 'suspension.front_travel' );
		$tire_width = $this->get_ebike_spec_value( $specs, 'wheels_and_tires.tire_width' );

		$has_front = $front_susp && strtolower( $front_susp ) !== 'rigid' && strtolower( $front_susp ) !== 'none';
		$has_rear  = $rear_susp && strtolower( $rear_susp ) !== 'none' && strtolower( $rear_susp ) !== 'hardtail';

		if ( $has_front && $has_rear ) {
			$details[] = 'full suspension';
		} elseif ( $has_front ) {
			$details[] = 'front suspension';
		}

		if ( $travel && is_numeric( $travel ) ) {
			$details[] = (int) $travel . 'mm travel';
		}

		if ( $tire_width && is_numeric( $tire_width ) ) {
			$details[] = number_format( (float) $tire_width, 1 ) . '" tires';
		}

		return implode( ', ', $details );
	}

	/**
	 * Get practicality summary for winner.
	 *
	 * @param array $specs Product specs.
	 * @return string Summary.
	 */
	private function get_practicality_summary( array $specs ): string {
		$details = [];

		$weight   = $this->get_ebike_spec_value( $specs, 'weight_and_capacity.weight' );
		$display  = $this->to_string( $this->get_ebike_spec_value( $specs, 'components.display' ) );
		$app      = $this->get_ebike_spec_value( $specs, 'components.app_compatible' );
		$features = $this->count_integrated_features( $specs );

		if ( $weight && is_numeric( $weight ) ) {
			$details[] = (int) $weight . ' lbs';
		}

		if ( $display ) {
			$display_lower = strtolower( $display );
			if ( strpos( $display_lower, 'color' ) !== false || strpos( $display_lower, 'tft' ) !== false ) {
				$details[] = 'color display';
			} elseif ( strpos( $display_lower, 'lcd' ) !== false ) {
				$details[] = 'LCD display';
			}
		}

		if ( $this->to_boolean( $app ) ) {
			$details[] = 'app';
		}

		if ( $features >= 3 ) {
			$details[] = "{$features} accessories";
		}

		return implode( ', ', $details );
	}

	/**
	 * Find winner based on category score.
	 *
	 * @param array  $products  Products array.
	 * @param string $score_key Score key.
	 * @return int|null Winner index or null.
	 */
	private function find_category_score_winner( array $products, string $score_key ): ?int {
		$best_score   = 0;
		$best_indices = [];

		foreach ( $products as $idx => $product ) {
			$score = $product['specs']['scores'][ $score_key ] ?? 0;

			if ( $score > $best_score ) {
				$best_score   = $score;
				$best_indices = [ $idx ];
			} elseif ( $score === $best_score && $score >= 50 ) {
				$best_indices[] = $idx;
			}
		}

		if ( $best_score < 50 ) {
			return null;
		}

		return count( $best_indices ) === 1 ? $best_indices[0] : null;
	}

	// =========================================================================
	// Single Product Analysis Logic
	// =========================================================================

	/**
	 * Get comparison set for single product analysis.
	 *
	 * @param array|null $bracket     Bracket config or null.
	 * @param string     $geo         Geo region code.
	 * @param bool       $use_bracket Whether to filter by bracket.
	 * @return array Array of product data.
	 */
	private function get_single_comparison_set( ?array $bracket, string $geo, bool $use_bracket ): array {
		$cache        = new \ERH\Database\ProductCache();
		$all_products = $cache->get_all( 'Electric Bike' );

		if ( ! $use_bracket ) {
			return $all_products;
		}

		$filtered = [];
		foreach ( $all_products as $prod ) {
			$price_history = $prod['price_history'] ?? [];
			$geo_pricing   = $price_history[ $geo ] ?? null;
			$price         = $geo_pricing['current_price'] ?? null;

			if ( ! $price || $price <= 0 ) {
				continue;
			}

			if ( $price >= $bracket['min'] && $price < $bracket['max'] ) {
				$filtered[] = $prod;
			}
		}

		return $filtered;
	}

	/**
	 * Analyze a single spec against comparison set.
	 *
	 * @param array  $product        Target product.
	 * @param array  $comparison_set Products to compare against.
	 * @param array  $spec_def       Spec definition.
	 * @param string $geo            Geo region code.
	 * @return array|null Result with is_advantage, is_weakness, item.
	 */
	private function analyze_single_spec( array $product, array $comparison_set, array $spec_def, string $geo ): ?array {
		$key           = $spec_def['key'];
		$higher_better = $spec_def['higher_better'] ?? true;

		// Handle score-based composite specs.
		if ( ! empty( $spec_def['is_score_based'] ) ) {
			return $this->analyze_score_based_spec( $product, $comparison_set, $spec_def );
		}

		// Handle descriptive specs.
		if ( ! empty( $spec_def['is_descriptive'] ) ) {
			return $this->analyze_descriptive_spec( $product, $spec_def );
		}

		// Handle absolute threshold specs (gear count, rotor size).
		if ( ! empty( $spec_def['absolute_thresh'] ) ) {
			return $this->analyze_absolute_threshold_spec( $product, $spec_def, $geo );
		}

		// Get product value.
		$product_value = $this->get_single_spec_value( $product, $key, $geo );

		if ( $product_value === null || ( is_numeric( $product_value ) && $product_value <= 0 ) ) {
			return null;
		}

		// Collect values from comparison set.
		$values = [];
		foreach ( $comparison_set as $comp_product ) {
			$val = $this->get_single_spec_value( $comp_product, $key, $geo );
			if ( $val !== null && ( ! is_numeric( $val ) || $val > 0 ) ) {
				$values[] = (float) $val;
			}
		}

		if ( count( $values ) < 3 ) {
			return null;
		}

		// Calculate stats.
		$avg = array_sum( $values ) / count( $values );
		$min = min( $values );
		$max = max( $values );

		if ( $min === $max ) {
			return null;
		}

		// Calculate percentile and pct vs avg.
		$percentile = $this->calculate_percentile( (float) $product_value, $values, $higher_better );
		$rank       = $this->calculate_rank( (float) $product_value, $values, $higher_better );
		$pct_vs_avg = $avg > 0 ? ( ( $product_value - $avg ) / $avg ) * 100 : 0;

		// Determine advantage/weakness.
		$is_advantage = PriceBracketConfig::is_advantage( $percentile, $pct_vs_avg, $higher_better );
		$is_weakness  = PriceBracketConfig::is_weakness( $percentile, $pct_vs_avg, $higher_better );

		// Sanity check.
		if ( $is_weakness ) {
			if ( $higher_better && (float) $product_value >= $max ) {
				$is_weakness = false;
			} elseif ( ! $higher_better && (float) $product_value <= $min ) {
				$is_weakness = false;
			}
		}

		// Special case: Don't flag 250W motor as weakness.
		// 250W is the EU/Class 1 legal standard - comparing to 750W US bikes isn't fair.
		if ( $is_weakness && $key === 'motor.power_nominal' ) {
			$power = (float) $product_value;
			if ( $power >= 250 && $power <= 350 ) {
				// 250-350W range is standard for EU/Class 1 bikes.
				$is_weakness = false;
			}
		}

		// Special case: Don't flag power-to-weight as weakness for 250W bikes.
		// These bikes are legally capped at 250W, so low W/lb is expected.
		if ( $is_weakness && $key === 'watts_per_lb' ) {
			$motor_power = $this->get_single_spec_value( $product, 'motor.power_nominal', $geo );
			if ( $motor_power && (float) $motor_power >= 250 && (float) $motor_power <= 350 ) {
				$is_weakness = false;
			}
		}

		if ( ! $is_advantage && ! $is_weakness ) {
			return null;
		}

		// Format values.
		$formatted_value = $this->format_analysis_value( $product_value, $spec_def );
		$formatted_avg   = $this->format_analysis_value( $avg, $spec_def );

		$item = [
			'spec_key'      => $key,
			'label'         => $spec_def['label'],
			'product_value' => $formatted_value,
			'bracket_avg'   => $formatted_avg,
			'unit'          => $spec_def['unit'] ?? '',
			'percentile'    => round( $percentile, 1 ),
			'pct_vs_avg'    => round( $pct_vs_avg, 1 ),
			'text'          => $this->format_single_text( $spec_def, $percentile, $pct_vs_avg, $is_advantage, $higher_better, $rank, count( $values ) ),
			'comparison'    => $this->format_comparison_text( $formatted_value, $formatted_avg, $spec_def ),
			'tooltip'       => $this->get_ebike_tooltip( $key ),
		];

		return [
			'is_advantage' => $is_advantage,
			'is_weakness'  => $is_weakness,
			'item'         => $item,
		];
	}

	/**
	 * Analyze score-based composite spec.
	 *
	 * @param array $product        Target product.
	 * @param array $comparison_set Products to compare against.
	 * @param array $spec_def       Spec definition.
	 * @return array|null Result.
	 */
	private function analyze_score_based_spec( array $product, array $comparison_set, array $spec_def ): ?array {
		$key   = $spec_def['key'];
		$label = $spec_def['label'];

		$product_score = $product['specs']['scores'][ $key ] ?? null;

		if ( $product_score === null ) {
			return null;
		}

		// Collect scores.
		$scores = [];
		foreach ( $comparison_set as $comp_product ) {
			$comp_score = $comp_product['specs']['scores'][ $key ] ?? null;
			if ( $comp_score !== null ) {
				$scores[] = (float) $comp_score;
			}
		}

		if ( count( $scores ) < 3 ) {
			return null;
		}

		$avg  = array_sum( $scores ) / count( $scores );
		$diff = (float) $product_score - $avg;

		// Thresholds: ±8 points.
		$is_advantage = $diff >= 8;
		$is_weakness  = $diff <= -8;

		if ( ! $is_advantage && ! $is_weakness ) {
			return null;
		}

		// Get tier label and details.
		$tier    = $this->get_score_tier_label( $key, $diff, $is_advantage );
		$details = $this->get_score_details( $key, $product['specs'] ?? [], $is_advantage );

		$item = [
			'spec_key'      => $key,
			'label'         => $label,
			'product_value' => $product_score,
			'bracket_avg'   => round( $avg ),
			'unit'          => 'score',
			'percentile'    => 0,
			'pct_vs_avg'    => round( $diff ),
			'text'          => $tier,
			'comparison'    => ucfirst( $details ),
			'tooltip'       => $this->get_ebike_tooltip( $key ),
		];

		return [
			'is_advantage' => $is_advantage,
			'is_weakness'  => $is_weakness,
			'item'         => $item,
		];
	}

	/**
	 * Analyze descriptive spec (IP rating).
	 *
	 * @param array $product  Target product.
	 * @param array $spec_def Spec definition.
	 * @return array|null Result.
	 */
	private function analyze_descriptive_spec( array $product, array $spec_def ): ?array {
		$key = $spec_def['key'];

		if ( $key === 'ip_rating' ) {
			return $this->analyze_ip_rating( $product );
		}

		return null;
	}

	/**
	 * Analyze IP rating.
	 *
	 * @param array $product Target product.
	 * @return array|null Result.
	 */
	private function analyze_ip_rating( array $product ): ?array {
		$specs = $product['specs'] ?? [];

		$ip_rating = $this->get_ebike_spec_value( $specs, 'safety_and_compliance.ip_rating' );

		$water_rating = $this->get_ip_water_rating( $ip_rating );

		// IP5+ = strength, IP3 or lower/none = weakness, IP4 = neutral.
		$is_advantage = $water_rating >= 5;
		$is_weakness  = empty( $ip_rating ) || $water_rating <= 3;

		if ( ! $is_advantage && ! $is_weakness ) {
			return null;
		}

		$quality_label = $this->get_ip_quality_label( $water_rating );

		if ( $is_advantage ) {
			$display_text = $quality_label . ' (' . strtoupper( (string) $ip_rating ) . ')';
			$comparison   = $this->get_ip_rating_details( $water_rating, true );
		} elseif ( empty( $ip_rating ) ) {
			$display_text = 'No water resistance rating';
			$comparison   = 'Avoid wet conditions';
		} else {
			$display_text = 'Limited water resistance (' . strtoupper( (string) $ip_rating ) . ')';
			$comparison   = 'Avoid riding in rain';
		}

		$item = [
			'spec_key'      => 'ip_rating',
			'label'         => 'Weather Resistance',
			'product_value' => '',
			'bracket_avg'   => '',
			'unit'          => '',
			'percentile'    => 0,
			'pct_vs_avg'    => 0,
			'text'          => $display_text,
			'comparison'    => $comparison,
			'tooltip'       => $this->get_ebike_tooltip( 'ip_rating' ),
		];

		return [
			'is_advantage' => $is_advantage,
			'is_weakness'  => $is_weakness,
			'item'         => $item,
		];
	}

	/**
	 * Analyze specs using absolute thresholds (not percentile-based).
	 *
	 * Used for gear count and rotor size where comparison to average
	 * doesn't make sense - only absolute values matter.
	 *
	 * @param array  $product  Target product.
	 * @param array  $spec_def Spec definition.
	 * @param string $geo      Geo region.
	 * @return array|null Result.
	 */
	private function analyze_absolute_threshold_spec( array $product, array $spec_def, string $geo ): ?array {
		$key   = $spec_def['key'];
		$value = $this->get_single_spec_value( $product, $key, $geo );

		if ( $value === null || ! is_numeric( $value ) || (float) $value <= 0 ) {
			return null;
		}

		$value = (float) $value;

		// Define absolute thresholds per spec.
		return match ( $key ) {
			'drivetrain.gears'              => $this->analyze_gear_count( $value, $spec_def ),
			'brakes.rotor_size_front'       => $this->analyze_rotor_size( $value, $spec_def ),
			'weight_and_capacity.weight_limit' => $this->analyze_weight_capacity( $value, $spec_def ),
			'suspension.front_travel'       => $this->analyze_suspension_travel( $value, $spec_def ),
			default                         => null,
		};
	}

	/**
	 * Analyze gear count with absolute thresholds.
	 *
	 * - 10+ gears: Strength (wide gear range)
	 * - 4-9 gears: Neutral (adequate)
	 * - 1-3 gears: Weakness (limited gearing)
	 *
	 * Single-speed (1 gear) is only flagged on non-urban bikes.
	 *
	 * @param float $gears    Number of gears.
	 * @param array $spec_def Spec definition.
	 * @return array|null Result.
	 */
	private function analyze_gear_count( float $gears, array $spec_def ): ?array {
		$gears = (int) $gears;

		// Strength: 10+ gears (11-speed, 12-speed groupsets).
		$is_advantage = $gears >= 10;

		// Weakness: 3 or fewer gears (single-speed, 3-speed internal hub).
		$is_weakness = $gears <= 3;

		if ( ! $is_advantage && ! $is_weakness ) {
			return null;
		}

		if ( $is_advantage ) {
			$text       = 'Wide gear range';
			$comparison = $gears . '-speed drivetrain';
		} else {
			if ( $gears === 1 ) {
				$text       = 'Single-speed';
				$comparison = 'No gear options for hills';
			} else {
				$text       = 'Limited gearing';
				$comparison = 'Only ' . $gears . ' gears';
			}
		}

		$item = [
			'spec_key'      => $spec_def['key'],
			'label'         => $spec_def['label'],
			'product_value' => $gears,
			'bracket_avg'   => '',
			'unit'          => 'gears',
			'percentile'    => 0,
			'pct_vs_avg'    => 0,
			'text'          => $text,
			'comparison'    => $comparison,
			'tooltip'       => $this->get_ebike_tooltip( $spec_def['key'] ),
		];

		return [
			'is_advantage' => $is_advantage,
			'is_weakness'  => $is_weakness,
			'item'         => $item,
		];
	}

	/**
	 * Analyze rotor size with absolute thresholds.
	 *
	 * - 180mm+: Strength (good stopping power)
	 * - 160-179mm: Neutral (adequate)
	 * - <160mm: Weakness (limited stopping power)
	 *
	 * @param float $rotor_mm Rotor diameter in mm.
	 * @param array $spec_def Spec definition.
	 * @return array|null Result.
	 */
	private function analyze_rotor_size( float $rotor_mm, array $spec_def ): ?array {
		$rotor = (int) $rotor_mm;

		// Strength: 180mm+ rotors provide excellent stopping power.
		$is_advantage = $rotor >= 180;

		// Weakness: Below 160mm has limited stopping power for e-bike weight.
		$is_weakness = $rotor < 160;

		if ( ! $is_advantage && ! $is_weakness ) {
			return null;
		}

		if ( $is_advantage ) {
			$text       = 'Large brake rotors';
			$comparison = $rotor . 'mm rotors';
		} else {
			$text       = 'Small brake rotors';
			$comparison = $rotor . 'mm may struggle with e-bike weight';
		}

		$item = [
			'spec_key'      => $spec_def['key'],
			'label'         => $spec_def['label'],
			'product_value' => $rotor,
			'bracket_avg'   => '',
			'unit'          => 'mm',
			'percentile'    => 0,
			'pct_vs_avg'    => 0,
			'text'          => $text,
			'comparison'    => $comparison,
			'tooltip'       => $this->get_ebike_tooltip( $spec_def['key'] ),
		];

		return [
			'is_advantage' => $is_advantage,
			'is_weakness'  => $is_weakness,
			'item'         => $item,
		];
	}

	/**
	 * Analyze weight capacity with absolute thresholds.
	 *
	 * - 350+ lbs: Strength (high capacity)
	 * - 250-349 lbs: Neutral (adequate for most riders)
	 * - <250 lbs: Weakness (may exclude heavier riders)
	 *
	 * Note: 300 lbs is perfectly adequate - only flag if truly low.
	 *
	 * @param float $capacity Weight limit in lbs.
	 * @param array $spec_def Spec definition.
	 * @return array|null Result.
	 */
	private function analyze_weight_capacity( float $capacity, array $spec_def ): ?array {
		$capacity = (int) $capacity;

		// Strength: 350+ lbs is genuinely high capacity.
		$is_advantage = $capacity >= 350;

		// Weakness: Below 250 lbs excludes many riders.
		// 250-349 lbs is normal/adequate - NOT a weakness.
		$is_weakness = $capacity < 250;

		if ( ! $is_advantage && ! $is_weakness ) {
			return null;
		}

		if ( $is_advantage ) {
			if ( $capacity >= 400 ) {
				$text       = 'Very high weight capacity';
				$comparison = 'Supports up to ' . $capacity . ' lbs';
			} else {
				$text       = 'High weight capacity';
				$comparison = 'Supports up to ' . $capacity . ' lbs';
			}
		} else {
			$text       = 'Limited weight capacity';
			$comparison = 'Only ' . $capacity . ' lbs max rider weight';
		}

		$item = [
			'spec_key'      => $spec_def['key'],
			'label'         => $spec_def['label'],
			'product_value' => $capacity,
			'bracket_avg'   => '',
			'unit'          => 'lbs',
			'percentile'    => 0,
			'pct_vs_avg'    => 0,
			'text'          => $text,
			'comparison'    => $comparison,
			'tooltip'       => $this->get_ebike_tooltip( $spec_def['key'] ),
		];

		return [
			'is_advantage' => $is_advantage,
			'is_weakness'  => $is_weakness,
			'item'         => $item,
		];
	}

	/**
	 * Analyze suspension travel with absolute thresholds.
	 *
	 * - 120+ mm: Strength (good for trails/rough terrain)
	 * - 40-119 mm: Neutral (adequate for most riding)
	 * - <40 mm: Weakness (minimal cushioning)
	 *
	 * Note: 60-80mm is fine for road/commuter bikes - NOT a weakness.
	 * Only truly minimal travel or rigid forks are weaknesses.
	 *
	 * @param float $travel_mm Fork travel in mm.
	 * @param array $spec_def  Spec definition.
	 * @return array|null Result.
	 */
	private function analyze_suspension_travel( float $travel_mm, array $spec_def ): ?array {
		$travel = (int) $travel_mm;

		// Strength: 120+ mm is genuinely good suspension.
		$is_advantage = $travel >= 120;

		// Weakness: Below 40mm is truly minimal.
		// 40-119mm is adequate for most use cases - NOT a weakness.
		$is_weakness = $travel < 40;

		if ( ! $is_advantage && ! $is_weakness ) {
			return null;
		}

		if ( $is_advantage ) {
			if ( $travel >= 150 ) {
				$text       = 'Long-travel suspension';
				$comparison = $travel . 'mm fork for rough terrain';
			} else {
				$text       = 'Good suspension travel';
				$comparison = $travel . 'mm fork';
			}
		} else {
			$text       = 'Minimal suspension';
			$comparison = 'Only ' . $travel . 'mm travel';
		}

		$item = [
			'spec_key'      => $spec_def['key'],
			'label'         => $spec_def['label'],
			'product_value' => $travel,
			'bracket_avg'   => '',
			'unit'          => 'mm',
			'percentile'    => 0,
			'pct_vs_avg'    => 0,
			'text'          => $text,
			'comparison'    => $comparison,
			'tooltip'       => $this->get_ebike_tooltip( $spec_def['key'] ),
		];

		return [
			'is_advantage' => $is_advantage,
			'is_weakness'  => $is_weakness,
			'item'         => $item,
		];
	}

	/**
	 * Calculate bracket average scores.
	 *
	 * @param array $comparison_set Products in comparison set.
	 * @return array Score key => average value.
	 */
	private function calculate_bracket_average_scores( array $comparison_set ): array {
		$score_keys = [
			'motor_drive',
			'battery_range',
			'component_quality',
			'comfort',
			'practicality',
		];

		$averages = [];

		foreach ( $score_keys as $key ) {
			$values = [];
			foreach ( $comparison_set as $prod ) {
				$score = $prod['specs']['scores'][ $key ] ?? null;
				if ( is_numeric( $score ) ) {
					$values[] = (float) $score;
				}
			}

			if ( ! empty( $values ) ) {
				$averages[ $key ] = round( array_sum( $values ) / count( $values ), 1 );
			}
		}

		return $averages;
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

	/**
	 * Get e-bike spec value with path resolution.
	 *
	 * @param array  $specs Product specs.
	 * @param string $key   Spec key (dot notation).
	 * @return mixed Value or null.
	 */
	private function get_ebike_spec_value( array $specs, string $key ) {
		// Try direct key first.
		$value = $this->get_nested_spec( $specs, $key );
		if ( $value !== null && $value !== '' ) {
			return $value;
		}

		// Try e-bikes prefix.
		$value = $this->get_nested_spec( $specs, 'e-bikes.' . $key );
		if ( $value !== null && $value !== '' ) {
			return $value;
		}

		// Try computed specs at root.
		if ( isset( $specs[ $key ] ) ) {
			return $specs[ $key ];
		}

		return null;
	}

	/**
	 * Get single spec value for analysis.
	 *
	 * @param array  $product Product data.
	 * @param string $key     Spec key.
	 * @param string $geo     Geo region.
	 * @return mixed Value or null.
	 */
	private function get_single_spec_value( array $product, string $key, string $geo ) {
		$specs = $product['specs'] ?? [];

		// Handle value_metrics.
		if ( strpos( $key, 'value_metrics.' ) === 0 ) {
			$metric_key    = str_replace( 'value_metrics.', '', $key );
			$value_metrics = $specs['value_metrics'][ $geo ] ?? [];
			return $value_metrics[ $metric_key ] ?? null;
		}

		return $this->get_ebike_spec_value( $specs, $key );
	}

	/**
	 * Get ranking map for a ranking type.
	 *
	 * @param string $ranking_type Ranking type.
	 * @return array Ranking map.
	 */
	private function get_ranking_map( string $ranking_type ): array {
		return match ( $ranking_type ) {
			'motor_brand'     => self::MOTOR_BRAND_RANKING,
			'motor_position'  => self::MOTOR_POSITION_RANKING,
			'sensor'          => self::SENSOR_RANKING,
			'battery_brand'   => self::BATTERY_BRAND_RANKING,
			'brake_type'      => self::BRAKE_TYPE_RANKING,
			'drivetrain'      => self::DRIVETRAIN_RANKING,
			'drive_system'    => self::DRIVE_SYSTEM_RANKING,
			'tire_brand'      => self::TIRE_BRAND_RANKING,
			'frame_material'  => self::FRAME_MATERIAL_RANKING,
			'suspension'      => self::SUSPENSION_RANKING,
			'display'         => self::DISPLAY_RANKING,
			default           => [],
		};
	}

	/**
	 * Get rank value from ranking map.
	 *
	 * @param mixed $value   Value to look up.
	 * @param array $ranking Ranking map.
	 * @return int|null Rank or null.
	 */
	private function get_rank_value( $value, array $ranking ): ?int {
		if ( $value === null || $value === '' ) {
			return null;
		}

		$normalized = strtolower( trim( (string) $value ) );

		// Direct match.
		if ( isset( $ranking[ $normalized ] ) ) {
			return $ranking[ $normalized ];
		}

		// Partial match.
		foreach ( $ranking as $key => $rank ) {
			// Cast key to string - PHP converts numeric string keys like '105' to integers.
			if ( strpos( $normalized, (string) $key ) !== false ) {
				return $rank;
			}
		}

		// Default to worst rank.
		return max( $ranking ) + 1;
	}

	/**
	 * Convert value to boolean.
	 *
	 * @param mixed $value Value.
	 * @return bool Boolean.
	 */
	private function to_boolean( $value ): bool {
		if ( $value === true || $value === 1 || $value === '1' ) {
			return true;
		}
		if ( is_string( $value ) ) {
			return strtolower( $value ) === 'yes' || strtolower( $value ) === 'true';
		}
		return false;
	}

	/**
	 * Safely convert value to string.
	 *
	 * Handles arrays by taking the first element.
	 *
	 * @param mixed $value Value.
	 * @return string String value or empty string.
	 */
	private function to_string( $value ): string {
		if ( $value === null ) {
			return '';
		}
		if ( is_array( $value ) ) {
			// Take first non-empty element.
			foreach ( $value as $v ) {
				if ( $v !== null && $v !== '' ) {
					return (string) $v;
				}
			}
			return '';
		}
		return (string) $value;
	}

	/**
	 * Count integrated features.
	 *
	 * @param array $specs Product specs.
	 * @return int Feature count.
	 */
	private function count_integrated_features( array $specs ): int {
		$features = $this->get_ebike_spec_value( $specs, 'integrated_features' );

		if ( ! is_array( $features ) ) {
			return 0;
		}

		$feature_keys = [
			'fenders',
			'rear_rack',
			'front_rack',
			'kickstand',
			'walk_assist',
			'integrated_lights',
			'usb',
			'alarm',
		];

		$count = 0;
		foreach ( $feature_keys as $feature ) {
			if ( isset( $features[ $feature ] ) && $this->to_boolean( $features[ $feature ] ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Get IP water rating from IP string.
	 *
	 * @param string|null $ip_rating IP rating string.
	 * @return int Water rating (0-9).
	 */
	private function get_ip_water_rating( ?string $ip_rating ): int {
		if ( empty( $ip_rating ) ) {
			return 0;
		}

		if ( preg_match( '/IP[X\d](\d)/i', $ip_rating, $matches ) ) {
			return (int) $matches[1];
		}

		return 0;
	}

	/**
	 * Get IP quality label.
	 *
	 * @param int $water_rating Water rating.
	 * @return string Quality label.
	 */
	private function get_ip_quality_label( int $water_rating ): string {
		if ( $water_rating >= 7 ) {
			return 'Excellent water resistance';
		} elseif ( $water_rating >= 6 ) {
			return 'Great water resistance';
		} elseif ( $water_rating >= 5 ) {
			return 'Good water resistance';
		} elseif ( $water_rating >= 4 ) {
			return 'Basic splash resistance';
		} else {
			return 'Limited water resistance';
		}
	}

	/**
	 * Get IP rating details.
	 *
	 * @param int  $water_rating Water rating.
	 * @param bool $is_advantage Whether advantage.
	 * @return string Details text.
	 */
	private function get_ip_rating_details( int $water_rating, bool $is_advantage ): string {
		if ( $is_advantage ) {
			if ( $water_rating >= 7 ) {
				return 'Safe for heavy rain and puddles';
			} elseif ( $water_rating >= 6 ) {
				return 'Safe for rain and wet conditions';
			} else {
				return 'Safe for light rain';
			}
		} else {
			if ( $water_rating === 0 ) {
				return 'Avoid wet conditions';
			} else {
				return 'Avoid riding in rain';
			}
		}
	}

	/**
	 * Calculate percentile rank.
	 *
	 * @param float $value        Value to rank.
	 * @param array $values       All values.
	 * @param bool  $higher_better Whether higher is better.
	 * @return float Percentile (0-100).
	 */
	private function calculate_percentile( float $value, array $values, bool $higher_better ): float {
		$count = count( $values );

		if ( $count === 0 ) {
			return 50.0;
		}

		$beats = 0;
		foreach ( $values as $v ) {
			if ( $higher_better ) {
				if ( $value > $v ) {
					$beats++;
				}
			} else {
				if ( $value < $v ) {
					$beats++;
				}
			}
		}

		return ( $beats / $count ) * 100;
	}

	/**
	 * Calculate rank (1 = best).
	 *
	 * @param float $value        Value to rank.
	 * @param array $values       All values.
	 * @param bool  $higher_better Whether higher is better.
	 * @return int Rank.
	 */
	private function calculate_rank( float $value, array $values, bool $higher_better ): int {
		$count = count( $values );

		if ( $count === 0 ) {
			return 1;
		}

		$better_count = 0;
		foreach ( $values as $v ) {
			if ( $higher_better ) {
				if ( $v > $value ) {
					$better_count++;
				}
			} else {
				if ( $v < $value ) {
					$better_count++;
				}
			}
		}

		return $better_count + 1;
	}

	/**
	 * Format analysis value.
	 *
	 * @param mixed $value    Value.
	 * @param array $spec_def Spec definition.
	 * @return mixed Formatted value.
	 */
	private function format_analysis_value( $value, array $spec_def ) {
		if ( ! is_numeric( $value ) ) {
			return $value;
		}

		$unit = $spec_def['unit'] ?? '';

		if ( ! empty( $spec_def['is_value'] ) ) {
			return round( (float) $value, 2 );
		}

		if ( in_array( $unit, [ 'Wh/lb', 'W/lb' ], true ) ) {
			return round( (float) $value, 1 );
		}

		if ( $unit === 'lbs' || $unit === 'hrs' ) {
			return round( (float) $value, 1 );
		}

		return round( (float) $value );
	}

	/**
	 * Format comparison text.
	 *
	 * @param mixed $product_value Product value.
	 * @param mixed $bracket_avg   Bracket average.
	 * @param array $spec_def      Spec definition.
	 * @return string Comparison text.
	 */
	private function format_comparison_text( $product_value, $bracket_avg, array $spec_def ): string {
		$unit = $spec_def['unit'] ?? '';

		if ( ! empty( $spec_def['is_value'] ) ) {
			$unit_suffix = str_replace( '$/', '/', $unit );
			return sprintf(
				'$%s%s vs $%s%s avg',
				$product_value,
				$unit_suffix,
				$bracket_avg,
				$unit_suffix
			);
		}

		if ( empty( $unit ) ) {
			return sprintf( '%s vs %s avg', $product_value, $bracket_avg );
		}

		return sprintf( '%s %s vs %s %s avg', $product_value, $unit, $bracket_avg, $unit );
	}

	/**
	 * Format single product analysis text.
	 *
	 * Uses natural language for specific specs rather than generic
	 * "Excellent {label}" patterns.
	 *
	 * @param array $spec_def      Spec definition.
	 * @param float $percentile    Percentile.
	 * @param float $pct_vs_avg    Percentage vs average.
	 * @param bool  $is_advantage  Whether advantage.
	 * @param bool  $higher_better Whether higher is better.
	 * @param int   $rank          Rank.
	 * @param int   $total_count   Total products.
	 * @return string Text.
	 */
	private function format_single_text( array $spec_def, float $percentile, float $pct_vs_avg, bool $is_advantage, bool $higher_better, int $rank = 0, int $total_count = 0 ): string {
		$key = $spec_def['key'] ?? '';

		// Custom labels for specific specs.
		$custom_labels = $this->get_custom_spec_labels( $key );
		if ( $custom_labels ) {
			return $this->format_with_custom_labels( $custom_labels, $percentile, $pct_vs_avg, $is_advantage, $rank, $total_count );
		}

		// Default generic formatting.
		$label       = $spec_def['label'];
		$label_lower = strtolower( $label );

		if ( $is_advantage ) {
			return $this->format_advantage_text( $label_lower, $percentile, $pct_vs_avg, $rank, $total_count );
		} else {
			return $this->format_weakness_text( $label_lower, $percentile, $pct_vs_avg, $rank, $total_count );
		}
	}

	/**
	 * Get custom label configuration for specific specs.
	 *
	 * @param string $key Spec key.
	 * @return array|null Labels config or null for default handling.
	 */
	private function get_custom_spec_labels( string $key ): ?array {
		return match ( $key ) {
			'weight_and_capacity.weight' => [
				'best'       => 'Lightest',
				'excellent'  => 'Very light',
				'strong'     => 'Light',
				'pct_better' => '%d%% lighter than average',
				'worst'      => 'Heaviest',
				'very_weak'  => 'Very heavy',
				'weak'       => 'Heavy',
				'pct_worse'  => '%d%% heavier than average',
			],
			'weight_and_capacity.weight_limit' => [
				'best'       => 'Highest weight capacity',
				'excellent'  => 'High weight capacity',
				'strong'     => 'Good weight capacity',
				'pct_better' => '%d%% higher weight capacity',
				'worst'      => 'Lowest weight capacity',
				'very_weak'  => 'Low weight capacity',
				'weak'       => 'Limited weight capacity',
				'pct_worse'  => '%d%% lower weight capacity',
			],
			'wheels_and_tires.tire_width' => [
				'best'       => 'Widest tires',
				'excellent'  => 'Wide tires',
				'strong'     => 'Wide tires',
				'pct_better' => 'Wide tires',
				'worst'      => 'Narrowest tires',
				'very_weak'  => 'Very narrow tires',
				'weak'       => 'Narrow tires',
				'pct_worse'  => 'Narrow tires',
			],
			'motor.torque' => [
				'best'       => 'Highest torque',
				'excellent'  => 'High torque',
				'strong'     => 'Good torque',
				'pct_better' => '%d%% more torque',
				'worst'      => 'Lowest torque',
				'very_weak'  => 'Very low torque',
				'weak'       => 'Low torque',
				'pct_worse'  => '%d%% less torque',
			],
			'motor.power_nominal' => [
				'best'       => 'Most powerful motor',
				'excellent'  => 'Powerful motor',
				'strong'     => 'Good motor power',
				'pct_better' => '%d%% more motor power',
				'worst'      => 'Least powerful motor',
				'very_weak'  => 'Very weak motor',
				'weak'       => 'Weak motor',
				'pct_worse'  => '%d%% less motor power',
			],
			'battery.battery_capacity' => [
				'best'       => 'Largest battery',
				'excellent'  => 'Large battery',
				'strong'     => 'Good battery capacity',
				'pct_better' => '%d%% more battery capacity',
				'worst'      => 'Smallest battery',
				'very_weak'  => 'Very small battery',
				'weak'       => 'Small battery',
				'pct_worse'  => '%d%% less battery capacity',
			],
			'suspension.front_travel' => [
				'best'       => 'Most suspension travel',
				'excellent'  => 'Long-travel suspension',
				'strong'     => 'Good suspension travel',
				'pct_better' => '%d%% more suspension travel',
				'worst'      => 'Least suspension travel',
				'very_weak'  => 'Minimal suspension',
				'weak'       => 'Short-travel suspension',
				'pct_worse'  => '%d%% less suspension travel',
			],
			'wh_per_lb' => [
				'best'       => 'Best energy density',
				'excellent'  => 'Excellent energy density',
				'strong'     => 'Good energy density',
				'pct_better' => '%d%% better energy density',
				'worst'      => 'Worst energy density',
				'very_weak'  => 'Poor energy density',
				'weak'       => 'Low energy density',
				'pct_worse'  => '%d%% lower energy density',
			],
			'watts_per_lb' => [
				'best'       => 'Best power-to-weight',
				'excellent'  => 'Excellent power-to-weight',
				'strong'     => 'Good power-to-weight',
				'pct_better' => '%d%% better power-to-weight',
				'worst'      => 'Worst power-to-weight',
				'very_weak'  => 'Poor power-to-weight',
				'weak'       => 'Low power-to-weight',
				'pct_worse'  => '%d%% lower power-to-weight',
			],
			// Value metrics.
			'value_metrics.price_per_wh' => [
				'best'       => 'Best battery value',
				'excellent'  => 'Excellent battery value',
				'strong'     => 'Good battery value',
				'pct_better' => '%d%% better battery value',
				'worst'      => 'Worst battery value',
				'very_weak'  => 'Poor battery value',
				'weak'       => 'Below-average battery value',
				'pct_worse'  => '%d%% worse battery value',
			],
			'value_metrics.price_per_nm' => [
				'best'       => 'Best torque value',
				'excellent'  => 'Excellent torque value',
				'strong'     => 'Good torque value',
				'pct_better' => '%d%% better torque value',
				'worst'      => 'Worst torque value',
				'very_weak'  => 'Poor torque value',
				'weak'       => 'Below-average torque value',
				'pct_worse'  => '%d%% worse torque value',
			],
			'value_metrics.price_per_watt' => [
				'best'       => 'Best motor value',
				'excellent'  => 'Excellent motor value',
				'strong'     => 'Good motor value',
				'pct_better' => '%d%% better motor value',
				'worst'      => 'Worst motor value',
				'very_weak'  => 'Poor motor value',
				'weak'       => 'Below-average motor value',
				'pct_worse'  => '%d%% worse motor value',
			],
			default => null,
		};
	}

	/**
	 * Format text using custom labels config.
	 *
	 * @param array $labels       Custom labels.
	 * @param float $percentile   Percentile.
	 * @param float $pct_vs_avg   Percentage vs average.
	 * @param bool  $is_advantage Whether advantage.
	 * @param int   $rank         Rank.
	 * @param int   $total_count  Total products.
	 * @return string Formatted text.
	 */
	private function format_with_custom_labels( array $labels, float $percentile, float $pct_vs_avg, bool $is_advantage, int $rank, int $total_count ): string {
		if ( $is_advantage ) {
			if ( $rank === 1 || $percentile >= 95 ) {
				return $labels['best'];
			} elseif ( $percentile >= 90 || ( $total_count > 0 && $rank <= max( 2, (int) ceil( $total_count * 0.1 ) ) ) ) {
				return $labels['excellent'];
			} elseif ( $percentile >= 80 || ( $total_count > 0 && $rank <= max( 2, (int) ceil( $total_count * 0.2 ) ) ) ) {
				return $labels['strong'];
			} else {
				return sprintf( $labels['pct_better'], abs( round( $pct_vs_avg ) ) );
			}
		} else {
			$reverse_rank = $total_count - $rank + 1;
			if ( $reverse_rank === 1 || $percentile <= 5 ) {
				return $labels['worst'];
			} elseif ( $percentile <= 10 || ( $total_count > 0 && $reverse_rank <= max( 2, (int) ceil( $total_count * 0.1 ) ) ) ) {
				return $labels['very_weak'];
			} elseif ( $percentile <= 20 || ( $total_count > 0 && $reverse_rank <= max( 2, (int) ceil( $total_count * 0.2 ) ) ) ) {
				return $labels['weak'];
			} else {
				return sprintf( $labels['pct_worse'], abs( round( $pct_vs_avg ) ) );
			}
		}
	}

	/**
	 * Format generic advantage text.
	 *
	 * @param string $label_lower Lowercase label.
	 * @param float  $percentile  Percentile.
	 * @param float  $pct_vs_avg  Percentage vs average.
	 * @param int    $rank        Rank.
	 * @param int    $total_count Total products.
	 * @return string Formatted text.
	 */
	private function format_advantage_text( string $label_lower, float $percentile, float $pct_vs_avg, int $rank, int $total_count ): string {
		if ( $rank === 1 || $percentile >= 95 ) {
			return "Best {$label_lower}";
		} elseif ( $percentile >= 90 || ( $total_count > 0 && $rank <= max( 2, (int) ceil( $total_count * 0.1 ) ) ) ) {
			return "Excellent {$label_lower}";
		} elseif ( $percentile >= 80 || ( $total_count > 0 && $rank <= max( 2, (int) ceil( $total_count * 0.2 ) ) ) ) {
			return "Good {$label_lower}";
		} else {
			$pct_display = abs( round( $pct_vs_avg ) );
			return "{$pct_display}% above average {$label_lower}";
		}
	}

	/**
	 * Format generic weakness text.
	 *
	 * @param string $label_lower Lowercase label.
	 * @param float  $percentile  Percentile.
	 * @param float  $pct_vs_avg  Percentage vs average.
	 * @param int    $rank        Rank.
	 * @param int    $total_count Total products.
	 * @return string Formatted text.
	 */
	private function format_weakness_text( string $label_lower, float $percentile, float $pct_vs_avg, int $rank, int $total_count ): string {
		$reverse_rank = $total_count - $rank + 1;
		if ( $reverse_rank === 1 || $percentile <= 5 ) {
			return "Lowest {$label_lower}";
		} elseif ( $percentile <= 10 || ( $total_count > 0 && $reverse_rank <= max( 2, (int) ceil( $total_count * 0.1 ) ) ) ) {
			return "Very low {$label_lower}";
		} elseif ( $percentile <= 20 || ( $total_count > 0 && $reverse_rank <= max( 2, (int) ceil( $total_count * 0.2 ) ) ) ) {
			return "Low {$label_lower}";
		} else {
			$pct_display = abs( round( $pct_vs_avg ) );
			return "{$pct_display}% below average {$label_lower}";
		}
	}

	/**
	 * Get score tier label.
	 *
	 * @param string $key         Score key.
	 * @param float  $diff        Score difference from average.
	 * @param bool   $is_positive Whether positive.
	 * @return string Tier label.
	 */
	private function get_score_tier_label( string $key, float $diff, bool $is_positive ): string {
		$abs_diff = abs( $diff );

		$labels = $this->get_score_labels( $key );

		if ( $is_positive ) {
			if ( $abs_diff >= 20 ) {
				return $labels['excellent'] ?? 'Excellent';
			} elseif ( $abs_diff >= 14 ) {
				return $labels['great'] ?? 'Great';
			} else {
				return $labels['above_avg'] ?? 'Above average';
			}
		} else {
			if ( $abs_diff >= 20 ) {
				return $labels['very_weak'] ?? 'Very weak';
			} elseif ( $abs_diff >= 14 ) {
				return $labels['weak'] ?? 'Weak';
			} else {
				return $labels['below_avg'] ?? 'Below average';
			}
		}
	}

	/**
	 * Get score labels for a score key.
	 *
	 * @param string $key Score key.
	 * @return array Labels.
	 */
	private function get_score_labels( string $key ): array {
		// Note: Score labels describe composite scores, not raw specs.
		// Avoid wording that clashes with raw spec labels (e.g., "Large battery").
		return match ( $key ) {
			'motor_drive' => [
				'excellent'  => 'Excellent motor system',
				'great'      => 'Great motor system',
				'above_avg'  => 'Above-average motor',
				'very_weak'  => 'Very weak motor system',
				'weak'       => 'Weak motor system',
				'below_avg'  => 'Below-average motor',
			],
			'battery_range' => [
				// Uses "range & battery" to distinguish from raw "Large battery" spec.
				'excellent'  => 'Excellent range & battery',
				'great'      => 'Great range & battery',
				'above_avg'  => 'Good range & battery',
				'very_weak'  => 'Very limited range',
				'weak'       => 'Limited range',
				'below_avg'  => 'Below-average range',
			],
			'component_quality' => [
				'excellent'  => 'Premium components',
				'great'      => 'Quality components',
				'above_avg'  => 'Good components',
				'very_weak'  => 'Budget components',
				'weak'       => 'Basic components',
				'below_avg'  => 'Below-average components',
			],
			'comfort' => [
				'excellent'  => 'Excellent ride comfort',
				'great'      => 'Comfortable ride',
				'above_avg'  => 'Good ride comfort',
				// Weakness labels describe WHY comfort is low.
				'very_weak'  => 'Rigid ride',
				'weak'       => 'Firm ride',
				'below_avg'  => 'Limited comfort',
			],
			'practicality' => [
				'excellent'  => 'Feature-rich',
				'great'      => 'Well-equipped',
				'above_avg'  => 'Good features',
				// Weakness labels are more specific - will be clarified by details.
				'very_weak'  => 'Minimal features',
				'weak'       => 'Few features',
				'below_avg'  => 'Limited features',
			],
			default => [],
		};
	}

	/**
	 * Get score details.
	 *
	 * @param string $key          Score key.
	 * @param array  $specs        Product specs.
	 * @param bool   $is_advantage Whether advantage.
	 * @return string Details text.
	 */
	private function get_score_details( string $key, array $specs, bool $is_advantage ): string {
		return match ( $key ) {
			'motor_drive'       => $this->get_motor_drive_details( $specs, $is_advantage ),
			'battery_range'     => $this->get_battery_range_details( $specs, $is_advantage ),
			'component_quality' => $this->get_component_quality_details( $specs, $is_advantage ),
			'comfort'           => $this->get_comfort_details( $specs, $is_advantage ),
			'practicality'      => $this->get_practicality_details( $specs, $is_advantage ),
			default             => '',
		};
	}

	/**
	 * Get motor/drive details.
	 *
	 * @param array $specs        Product specs.
	 * @param bool  $is_advantage Whether advantage.
	 * @return string Details text.
	 */
	private function get_motor_drive_details( array $specs, bool $is_advantage ): string {
		$details = [];

		$motor_brand = $this->get_ebike_spec_value( $specs, 'motor.motor_brand' );
		$position    = $this->get_ebike_spec_value( $specs, 'motor.motor_position' );
		$sensor      = $this->get_ebike_spec_value( $specs, 'motor.sensor_type' );
		$torque      = $this->get_ebike_spec_value( $specs, 'motor.torque' );

		if ( $is_advantage ) {
			if ( $motor_brand ) {
				$rank = $this->get_rank_value( $motor_brand, self::MOTOR_BRAND_RANKING );
				if ( $rank <= 2 ) {
					$details[] = ucfirst( strtolower( (string) $motor_brand ) ) . ' motor';
				}
			}
			if ( $position ) {
				$pos_lower = strtolower( (string) $position );
				if ( strpos( $pos_lower, 'mid' ) !== false || strpos( $pos_lower, 'center' ) !== false ) {
					$details[] = 'mid-drive';
				}
			}
			if ( $sensor ) {
				$sensor_lower = strtolower( (string) $sensor );
				if ( strpos( $sensor_lower, 'torque' ) !== false ) {
					$details[] = 'torque sensor';
				}
			}
			if ( $torque && is_numeric( $torque ) && (float) $torque >= 85 ) {
				$details[] = (int) $torque . 'Nm';
			}
		} else {
			if ( $position ) {
				$pos_lower = strtolower( (string) $position );
				if ( strpos( $pos_lower, 'hub' ) !== false || strpos( $pos_lower, 'rear' ) !== false ) {
					$details[] = 'hub motor';
				}
			}
			if ( $sensor ) {
				$sensor_lower = strtolower( (string) $sensor );
				if ( strpos( $sensor_lower, 'cadence' ) !== false ) {
					$details[] = 'cadence sensor only';
				}
			}
		}

		return implode( ', ', $details );
	}

	/**
	 * Get battery/range details.
	 *
	 * @param array $specs        Product specs.
	 * @param bool  $is_advantage Whether advantage.
	 * @return string Details text.
	 */
	private function get_battery_range_details( array $specs, bool $is_advantage ): string {
		$details = [];

		$capacity  = $this->get_ebike_spec_value( $specs, 'battery.battery_capacity' );
		$brand     = $this->get_ebike_spec_value( $specs, 'battery.battery_brand' );
		$removable = $this->get_ebike_spec_value( $specs, 'battery.removable' );

		if ( $is_advantage ) {
			if ( $capacity && is_numeric( $capacity ) && (float) $capacity >= 600 ) {
				$details[] = (int) $capacity . 'Wh';
			}
			if ( $brand ) {
				$rank = $this->get_rank_value( $brand, self::BATTERY_BRAND_RANKING );
				if ( $rank <= 1 ) {
					$details[] = ucfirst( strtolower( (string) $brand ) ) . ' cells';
				}
			}
			if ( $this->to_boolean( $removable ) ) {
				$details[] = 'removable';
			}
		} else {
			if ( $capacity && is_numeric( $capacity ) && (float) $capacity < 400 ) {
				$details[] = 'only ' . (int) $capacity . 'Wh';
			}
			if ( ! $this->to_boolean( $removable ) ) {
				$details[] = 'integrated battery';
			}
		}

		return implode( ', ', $details );
	}

	/**
	 * Get component quality details.
	 *
	 * @param array $specs        Product specs.
	 * @param bool  $is_advantage Whether advantage.
	 * @return string Details text.
	 */
	private function get_component_quality_details( array $specs, bool $is_advantage ): string {
		$details = [];

		$brake_type  = $this->get_ebike_spec_value( $specs, 'brakes.brake_type' );
		$drivetrain  = $this->get_ebike_spec_value( $specs, 'drivetrain.derailleur' );
		$frame       = $this->get_ebike_spec_value( $specs, 'frame_and_geometry.frame_material' );
		$tire_brand  = $this->get_ebike_spec_value( $specs, 'wheels_and_tires.tire_brand' );

		if ( $is_advantage ) {
			if ( $brake_type ) {
				$brake_lower = strtolower( (string) $brake_type );
				if ( strpos( $brake_lower, 'hydraulic' ) !== false ) {
					$details[] = 'hydraulic brakes';
				}
			}
			if ( $drivetrain ) {
				$rank = $this->get_rank_value( $drivetrain, self::DRIVETRAIN_RANKING );
				if ( $rank <= 2 ) {
					$details[] = ucfirst( strtolower( (string) $drivetrain ) ) . ' drivetrain';
				}
			}
			if ( $frame ) {
				$frame_lower = strtolower( (string) $frame );
				if ( strpos( $frame_lower, 'carbon' ) !== false ) {
					$details[] = 'carbon frame';
				}
			}
			if ( $tire_brand ) {
				$rank = $this->get_rank_value( $tire_brand, self::TIRE_BRAND_RANKING );
				if ( $rank <= 1 ) {
					$details[] = ucfirst( strtolower( (string) $tire_brand ) ) . ' tires';
				}
			}
		} else {
			if ( $brake_type ) {
				$brake_lower = strtolower( (string) $brake_type );
				if ( strpos( $brake_lower, 'mechanical' ) !== false ) {
					$details[] = 'mechanical brakes';
				} elseif ( strpos( $brake_lower, 'rim' ) !== false || strpos( $brake_lower, 'v-brake' ) !== false ) {
					$details[] = 'rim brakes';
				}
			}
			if ( $drivetrain ) {
				$rank = $this->get_rank_value( $drivetrain, self::DRIVETRAIN_RANKING );
				if ( $rank >= 4 ) {
					$details[] = 'basic drivetrain';
				}
			}
		}

		return implode( ', ', $details );
	}

	/**
	 * Get comfort details.
	 *
	 * @param array $specs        Product specs.
	 * @param bool  $is_advantage Whether advantage.
	 * @return string Details text.
	 */
	private function get_comfort_details( array $specs, bool $is_advantage ): string {
		$details = [];

		$front_susp = $this->get_ebike_spec_value( $specs, 'suspension.front_suspension' );
		$rear_susp  = $this->get_ebike_spec_value( $specs, 'suspension.rear_suspension' );
		$travel     = $this->get_ebike_spec_value( $specs, 'suspension.front_travel' );
		$tire_width = $this->get_ebike_spec_value( $specs, 'wheels_and_tires.tire_width' );

		if ( $is_advantage ) {
			$has_front = $front_susp && strtolower( (string) $front_susp ) !== 'rigid' && strtolower( (string) $front_susp ) !== 'none';
			$has_rear  = $rear_susp && strtolower( (string) $rear_susp ) !== 'none' && strtolower( (string) $rear_susp ) !== 'hardtail';

			if ( $has_front && $has_rear ) {
				$details[] = 'full suspension';
			} elseif ( $has_front ) {
				if ( $travel && is_numeric( $travel ) && (float) $travel >= 120 ) {
					$details[] = (int) $travel . 'mm fork';
				} else {
					$details[] = 'front suspension';
				}
			}

			if ( $tire_width && is_numeric( $tire_width ) && (float) $tire_width >= 2.8 ) {
				$details[] = number_format( (float) $tire_width, 1 ) . '" tires';
			}
		} else {
			// Check for no/rigid suspension.
			$susp_lower = $front_susp ? strtolower( (string) $front_susp ) : '';
			$is_rigid   = ! $front_susp || in_array( $susp_lower, [ 'rigid', 'none', 'n/a' ], true );

			if ( $is_rigid ) {
				$details[] = 'no front suspension';
			} elseif ( $travel && is_numeric( $travel ) && (float) $travel < 60 ) {
				// Has suspension but very short travel.
				$details[] = 'minimal suspension';
			}

			if ( $tire_width && is_numeric( $tire_width ) && (float) $tire_width < 2.0 ) {
				$details[] = 'narrow tires';
			}

			// If no specific details found, add a generic one.
			if ( empty( $details ) ) {
				$details[] = 'limited shock absorption';
			}
		}

		return implode( ', ', $details );
	}

	/**
	 * Get practicality details.
	 *
	 * @param array $specs        Product specs.
	 * @param bool  $is_advantage Whether advantage.
	 * @return string Details text.
	 */
	private function get_practicality_details( array $specs, bool $is_advantage ): string {
		$details = [];

		$weight   = $this->get_ebike_spec_value( $specs, 'weight_and_capacity.weight' );
		$display  = $this->get_ebike_spec_value( $specs, 'components.display' );
		$app      = $this->get_ebike_spec_value( $specs, 'components.app_compatible' );
		$throttle = $this->get_ebike_spec_value( $specs, 'speed_and_class.throttle' );

		$features = $this->count_integrated_features( $specs );

		if ( $is_advantage ) {
			if ( $weight && is_numeric( $weight ) && (float) $weight <= 50 ) {
				$details[] = (int) $weight . ' lbs';
			}

			if ( $display ) {
				$display_lower = strtolower( (string) $display );
				if ( strpos( $display_lower, 'color' ) !== false || strpos( $display_lower, 'tft' ) !== false ) {
					$details[] = 'color display';
				}
			}

			if ( $this->to_boolean( $app ) ) {
				$details[] = 'app';
			}

			if ( $features >= 5 ) {
				$details[] = "{$features} integrated features";
			}
		} else {
			// Note: Weight is NOT included here - it's analyzed as a separate spec.
			// Practicality weakness focuses on features/accessories only.

			if ( ! $this->to_boolean( $app ) ) {
				$details[] = 'no app';
			}

			if ( $features <= 2 ) {
				$details[] = 'few accessories';
			}

			// Check for basic display.
			if ( $display ) {
				$display_lower = strtolower( (string) $display );
				if ( strpos( $display_lower, 'led' ) !== false && strpos( $display_lower, 'lcd' ) === false ) {
					$details[] = 'basic display';
				}
			} elseif ( ! $display ) {
				$details[] = 'no display';
			}
		}

		return implode( ', ', $details );
	}

	/**
	 * Get tooltip for an e-bike spec key.
	 *
	 * Provides contextual explanations for why each spec matters
	 * when comparing e-bikes.
	 *
	 * @param string $key Spec key.
	 * @return string Tooltip text.
	 */
	private function get_ebike_tooltip( string $key ): string {
		return match ( $key ) {
			// Motor & Drive.
			'motor.torque'         => 'Torque measures climbing power. Higher torque = better hill performance.',
			'motor.power_nominal'  => 'Continuous motor power. More watts = more power for hills and acceleration.',
			'motor_drive'          => 'Overall motor system quality including brand, position, and sensor type.',

			// Battery & Range.
			'battery.battery_capacity' => 'Battery size in Watt-hours. Larger battery = longer potential range.',
			'battery_range'            => 'Combined battery capacity and expected range performance.',

			// Weight & Capacity.
			'weight_and_capacity.weight'       => 'Total bike weight affects handling and portability.',
			'weight_and_capacity.weight_limit' => 'Maximum combined weight of rider and cargo the bike supports.',

			// Components.
			'drivetrain.gears'        => 'More gears provide better options for hills and varied terrain.',
			'brakes.rotor_size_front' => 'Larger rotors provide more stopping power, important for heavy e-bikes.',
			'component_quality'       => 'Overall component quality including brakes, drivetrain, and frame.',

			// Suspension & Comfort.
			'suspension.front_travel' => 'Fork travel in mm. More travel = better bump absorption for rough terrain.',
			'wheels_and_tires.tire_width' => 'Wider tires provide more cushion, grip, and stability.',
			'comfort'                 => 'Overall ride comfort including suspension and tire setup.',

			// Efficiency.
			'wh_per_lb'   => 'Battery capacity relative to weight. Higher = more efficient energy storage.',
			'watts_per_lb' => 'Motor power relative to weight. Higher = better acceleration feel.',

			// Value Metrics.
			'value_metrics.price_per_wh'   => 'Cost per Watt-hour of battery. Lower = better battery value.',
			'value_metrics.price_per_nm'   => 'Cost per Newton-meter of torque. Lower = better power value.',
			'value_metrics.price_per_watt' => 'Cost per Watt of motor power. Lower = better motor value.',

			// Practicality.
			'practicality' => 'Overall practicality including weight, features, and accessories.',

			// Safety.
			'ip_rating' => 'Ingress Protection rating indicates dust and water resistance.',

			// Default.
			default => 'Performance metric compared against similar e-bikes in this price range.',
		};
	}
}
