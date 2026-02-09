<?php
/**
 * E-Skateboard Advantages Calculator.
 *
 * Calculates spec-based advantages for e-skateboard comparisons.
 * Handles head-to-head (2 products), multi (3+), and single product analysis.
 *
 * @package ERH\Comparison\Calculators
 */

declare(strict_types=1);

namespace ERH\Comparison\Calculators;

use ERH\Comparison\AdvantageCalculatorBase;
use ERH\Comparison\PriceBracketConfig;
use ERH\Config\SpecConfig;

/**
 * E-Skateboard advantage calculator.
 */
class EskateboardAdvantages extends AdvantageCalculatorBase {

	/**
	 * Product type this calculator handles.
	 *
	 * @var string
	 */
	private string $product_type = 'eskateboard';

	// =========================================================================
	// Ranking Constants
	// =========================================================================

	/**
	 * Wheel type ranking (best first).
	 */
	private const WHEEL_TYPE_RANKING = [
		'pneumatic'    => 1,
		'rubber'       => 1,
		'cloudwheels'  => 2,
		'polyurethane' => 3,
		'solid'        => 3,
	];

	/**
	 * Drive type ranking (best first).
	 */
	private const DRIVE_TYPE_RANKING = [
		'belt'         => 1,
		'gear'         => 1,
		'direct drive' => 2,
		'hub'          => 3,
	];

	/**
	 * Terrain ranking (best first).
	 */
	private const TERRAIN_RANKING = [
		'all-terrain' => 1,
		'hybrid'      => 2,
		'street'      => 3,
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
	 * Get the spec wrapper key for e-skateboards.
	 *
	 * @return string
	 */
	protected function get_spec_wrapper(): string {
		return 'e-skateboards';
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
		$this->threshold      = 3.0;

		$advantages = [ [], [] ];
		$specs      = $this->get_head_to_head_specs();

		foreach ( $specs as $spec_def ) {
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
			$bracket     = PriceBracketConfig::get_bracket( (float) $current_price, 'eskateboard' );
			$use_bracket = true;
		} else {
			$fallback_info = [
				'reason'  => 'no_regional_price',
				'message' => "No {$geo} pricing available. Comparing against all e-skateboards.",
			];
		}

		// Fetch comparison set.
		$comparison_set = $this->get_single_comparison_set( $bracket, $geo, $use_bracket );

		// Check if bracket has enough products.
		if ( $use_bracket && count( $comparison_set ) < PriceBracketConfig::MIN_BRACKET_SIZE ) {
			$use_bracket   = false;
			$fallback_info = [
				'reason'  => 'insufficient_bracket_size',
				'message' => 'Only ' . count( $comparison_set ) . ' products in bracket. Comparing against all e-skateboards.',
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
			// Motor & Performance.
			[
				'key'           => 'motor.power_nominal',
				'label'         => 'Motor Power',
				'unit'          => 'W',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'more',
			],
			[
				'key'           => 'motor.power_peak',
				'label'         => 'Peak Power',
				'unit'          => 'W',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'more',
			],
			[
				'key'           => 'manufacturer_top_speed',
				'label'         => 'Top Speed',
				'unit'          => 'mph',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'faster',
			],

			// Battery & Range.
			[
				'key'           => 'battery.capacity',
				'label'         => 'Battery Capacity',
				'unit'          => 'Wh',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'more',
			],
			[
				'key'           => 'battery.voltage',
				'label'         => 'Battery Voltage',
				'unit'          => 'V',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'more',
			],

			// Ride Quality.
			[
				'key'           => 'wheels.wheel_size',
				'label'         => 'Wheel Size',
				'unit'          => 'mm',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'larger',
				'min_diff'      => 5,
			],
			[
				'key'           => 'deck.length',
				'label'         => 'Deck Length',
				'unit'          => '"',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'larger',
				'min_diff'      => 1,
			],

			// Portability.
			[
				'key'           => 'dimensions.weight',
				'label'         => 'Weight',
				'unit'          => 'lbs',
				'higher_better' => false,
				'format'        => 'numeric',
				'diff_format'   => 'lighter',
			],
			[
				'key'           => 'dimensions.max_load',
				'label'         => 'Max Rider Weight',
				'unit'          => 'lbs',
				'higher_better' => true,
				'format'        => 'numeric',
				'diff_format'   => 'more',
			],

			// Ranked specs.
			[
				'key'           => 'wheels.wheel_type',
				'label'         => 'Wheel Type',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'wheel_type',
			],
			[
				'key'           => 'motor.motor_type',
				'label'         => 'Drive Type',
				'higher_better' => true,
				'format'        => 'ranked',
				'ranking'       => 'drive_type',
			],

			// Boolean specs.
			[
				'key'           => 'suspension.has_suspension',
				'label'         => 'Suspension',
				'higher_better' => true,
				'format'        => 'boolean',
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
				'key'           => 'motor.power_peak',
				'label'         => 'Most powerful',
				'unit'          => 'W',
				'higher_better' => true,
				'format'        => 'numeric',
			],
			[
				'key'           => 'battery.capacity',
				'label'         => 'Largest battery',
				'unit'          => 'Wh',
				'higher_better' => true,
				'format'        => 'numeric',
			],
			[
				'key'           => 'manufacturer_top_speed',
				'label'         => 'Fastest',
				'unit'          => 'mph',
				'higher_better' => true,
				'format'        => 'numeric',
			],
			[
				'key'           => 'dimensions.weight',
				'label'         => 'Lightest',
				'unit'          => 'lbs',
				'higher_better' => false,
				'format'        => 'numeric',
			],
			[
				'key'           => 'wheels.wheel_size',
				'label'         => 'Biggest wheels',
				'unit'          => 'mm',
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
				'key'           => 'value_metrics.price_per_watt',
				'label'         => 'Motor Value',
				'unit'          => '$/W',
				'higher_better' => false,
				'is_value'      => true,
			],
			[
				'key'           => 'value_metrics.price_per_mph',
				'label'         => 'Speed Value',
				'unit'          => '$/mph',
				'higher_better' => false,
				'is_value'      => true,
			],

			// Raw specs.
			[
				'key'           => 'manufacturer_top_speed',
				'label'         => 'Top Speed',
				'unit'          => 'mph',
				'higher_better' => true,
			],
			[
				'key'           => 'battery.capacity',
				'label'         => 'Battery Capacity',
				'unit'          => 'Wh',
				'higher_better' => true,
			],
			[
				'key'           => 'battery.voltage',
				'label'         => 'Battery Voltage',
				'unit'          => 'V',
				'higher_better' => true,
			],
			[
				'key'           => 'motor.power_nominal',
				'label'         => 'Motor Power',
				'unit'          => 'W',
				'higher_better' => true,
			],
			[
				'key'           => 'motor.power_peak',
				'label'         => 'Peak Power',
				'unit'          => 'W',
				'higher_better' => true,
			],
			[
				'key'           => 'battery.charging_time',
				'label'         => 'Charging Time',
				'unit'          => 'h',
				'higher_better' => false,
			],
			[
				'key'           => 'dimensions.weight',
				'label'         => 'Weight',
				'unit'          => 'lbs',
				'higher_better' => false,
			],
			[
				'key'           => 'wheels.wheel_size',
				'label'         => 'Wheel Size',
				'unit'          => 'mm',
				'higher_better' => true,
			],

			// Score-based composites.
			[
				'key'            => 'battery_range',
				'label'          => 'Battery & Range',
				'higher_better'  => true,
				'is_score_based' => true,
			],
			[
				'key'            => 'ride_quality',
				'label'          => 'Ride Quality',
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
		$val_a = $this->get_typed_spec_value( $products[0]['specs'], $key );
		$val_b = $this->get_typed_spec_value( $products[1]['specs'], $key );

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

		if ( $min_diff !== null ) {
			if ( $diff < $min_diff ) {
				return null;
			}
		} elseif ( $pct_diff < $this->threshold ) {
			return null;
		}

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
	 * Format numeric advantage.
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

		$diff_fmt   = $this->format_spec_number( $diff );
		$winner_fmt = $this->format_spec_number( $winner_val );
		$loser_fmt  = $this->format_spec_number( $loser_val );

		$label_lower     = strtolower( $label );
		$unit_with_space = $unit ? "{$unit} " : '';

		switch ( $diff_format ) {
			case 'more':
				$text = "{$diff_fmt} {$unit_with_space}more {$label_lower}";
				break;
			case 'faster':
				$text = "{$diff_fmt} {$unit} faster";
				break;
			case 'larger':
				$text = "{$diff_fmt} {$unit_with_space}larger {$label_lower}";
				break;
			case 'lighter':
				$text = "{$diff_fmt} {$unit} lighter";
				break;
			default:
				$text = "{$diff_fmt} {$unit_with_space}more {$label_lower}";
		}

		$unit_str   = $unit ? " {$unit}" : '';
		$comparison = "{$winner_fmt}{$unit_str} vs {$loser_fmt}{$unit_str}";

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

		$winner_idx = $rank_a < $rank_b ? 0 : 1;
		$winner_val = $this->to_string( $winner_idx === 0 ? $val_a : $val_b );
		$loser_val  = $this->to_string( $winner_idx === 0 ? $val_b : $val_a );

		return [
			'winner'    => $winner_idx,
			'advantage' => [
				'text'       => 'Better ' . strtolower( $spec_def['label'] ),
				'comparison' => $winner_val . ' vs ' . $loser_val,
				'winner'     => $winner_idx,
				'spec_key'   => $spec_def['key'],
			],
		];
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
		$higher_better = $spec_def['higher_better'] ?? true;
		$format        = $spec_def['format'] ?? 'numeric';

		$values = [];
		foreach ( $products as $idx => $product ) {
			$value = $this->get_typed_spec_value( $product['specs'], $key );
			$values[ $idx ] = $value;
		}

		$winner_idx    = null;
		$display_value = null;

		if ( $format === 'numeric' ) {
			$result     = $this->find_numeric_winner( $values, $higher_better );
			$winner_idx = $result['idx'];

			if ( $result['value'] !== null ) {
				$display_value = number_format( (float) $result['value'], 0 ) . ' ' . ( $spec_def['unit'] ?? '' );
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
			'motor_performance' => 'Best motor',
			'battery_range'     => 'Best battery',
			'ride_quality'      => 'Best ride quality',
			'portability'       => 'Most portable',
			'features'          => 'Most features',
		];

		foreach ( $score_categories as $score_key => $label ) {
			$winner = $this->find_category_score_winner( $products, $score_key );
			if ( $winner !== null ) {
				$winner_specs = $products[ $winner ]['specs'] ?? [];
				$details      = $this->get_multi_comparison_summary( $score_key, $winner_specs );

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
	 * @param string $key   Score key.
	 * @param array  $specs Product specs.
	 * @return string Summary text.
	 */
	private function get_multi_comparison_summary( string $key, array $specs ): string {
		$details = [];

		switch ( $key ) {
			case 'motor_performance':
				$power = $this->get_typed_spec_value( $specs, 'motor.power_peak' );
				$speed = $this->get_typed_spec_value( $specs, 'manufacturer_top_speed' );

				if ( $power && is_numeric( $power ) ) {
					$details[] = number_format( (int) $power ) . 'W peak';
				}
				if ( $speed && is_numeric( $speed ) ) {
					$details[] = $speed . ' mph';
				}
				break;

			case 'battery_range':
				$capacity = $this->get_typed_spec_value( $specs, 'battery.capacity' );

				if ( $capacity && is_numeric( $capacity ) ) {
					$details[] = number_format( (int) $capacity ) . 'Wh';
				}
				break;

			case 'ride_quality':
				$wheel_size = $this->get_typed_spec_value( $specs, 'wheels.wheel_size' );
				$deck_len   = $this->get_typed_spec_value( $specs, 'deck.length' );

				if ( $wheel_size && is_numeric( $wheel_size ) ) {
					$details[] = $wheel_size . 'mm wheels';
				}
				if ( $deck_len && is_numeric( $deck_len ) ) {
					$details[] = $deck_len . '" deck';
				}
				break;

			case 'portability':
				$weight = $this->get_typed_spec_value( $specs, 'dimensions.weight' );

				if ( $weight && is_numeric( $weight ) ) {
					$details[] = number_format( (float) $weight, 1 ) . ' lbs';
				}
				break;

			case 'features':
				$count = $this->count_skateboard_features( $specs );
				if ( $count > 0 ) {
					$details[] = $count . ' features';
				}
				break;
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
			} elseif ( $score > 0 && $score === $best_score ) {
				$best_indices[] = $idx;
			}
		}

		return count( $best_indices ) === 1 ? $best_indices[0] : null;
	}

	// =========================================================================
	// Single Analysis Logic
	// =========================================================================

	/**
	 * Get single comparison set.
	 *
	 * @param array|null $bracket     Bracket config or null.
	 * @param string     $geo         Geo region.
	 * @param bool       $use_bracket Whether to filter by bracket.
	 * @return array Products for comparison.
	 */
	private function get_single_comparison_set( ?array $bracket, string $geo, bool $use_bracket ): array {
		$cache        = new \ERH\Database\ProductCache();
		$all_products = $cache->get_all( 'Electric Skateboard' );

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

		// Get product value.
		$product_value = $this->get_single_spec_value( $product, $key, $geo );

		if ( $product_value === null || $product_value === '' || ( is_numeric( $product_value ) && $product_value <= 0 ) ) {
			return null;
		}

		// Collect values from comparison set.
		$values = [];
		foreach ( $comparison_set as $comp_product ) {
			$val = $this->get_single_spec_value( $comp_product, $key, $geo );
			if ( $val !== null && $val !== '' && ( ! is_numeric( $val ) || $val > 0 ) ) {
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
		$pct_vs_avg = $avg > 0 ? ( ( (float) $product_value - $avg ) / $avg ) * 100 : 0;

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

		$is_advantage = $diff >= 8;
		$is_weakness  = $diff <= -8;

		if ( ! $is_advantage && ! $is_weakness ) {
			return null;
		}

		$tier       = $this->get_score_tier_label( $key, $diff, $is_advantage );
		$details    = $this->get_score_details( $key, $product['specs'] ?? [], $is_advantage );
		$comparison = $details ? ucfirst( $details ) : '';

		$item = [
			'spec_key'      => $key,
			'label'         => $label,
			'product_value' => $product_score,
			'bracket_avg'   => round( $avg ),
			'unit'          => 'score',
			'percentile'    => 0,
			'pct_vs_avg'    => round( $diff ),
			'text'          => $tier,
			'comparison'    => $comparison,
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
	 * E-skateboards are typically not waterproof. IPX5+ = strength, IPX4 = neutral, less = weakness.
	 *
	 * @param array $product Target product.
	 * @return array|null Result.
	 */
	private function analyze_ip_rating( array $product ): ?array {
		$specs = $product['specs'] ?? [];

		$ip_rating    = $this->get_typed_spec_value( $specs, 'other.ip_rating' )
		                ?? $this->get_typed_spec_value( $specs, 'ip_rating' );
		$water_rating = $this->get_ip_water_rating( $ip_rating );

		$has_rating = ! empty( $ip_rating ) && strtolower( (string) $ip_rating ) !== 'unknown';

		$is_advantage = $has_rating && $water_rating >= 5;
		$is_weakness  = ! $has_rating || $water_rating <= 3;

		if ( ! $is_advantage && ! $is_weakness ) {
			return null;
		}

		if ( $is_advantage ) {
			$text       = 'Good water resistance (' . strtoupper( (string) $ip_rating ) . ')';
			$comparison = 'Safe for light rain and puddles';
		} elseif ( ! $has_rating ) {
			$text       = 'No water resistance rating';
			$comparison = 'Avoid wet conditions';
		} else {
			$text       = 'Limited water resistance (' . strtoupper( (string) $ip_rating ) . ')';
			$comparison = 'Avoid riding in rain';
		}

		$item = [
			'spec_key'      => 'ip_rating',
			'label'         => 'Weather Resistance',
			'product_value' => '',
			'bracket_avg'   => '',
			'unit'          => '',
			'percentile'    => 0,
			'pct_vs_avg'    => 0,
			'text'          => $text,
			'comparison'    => $comparison,
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
			'motor_performance',
			'battery_range',
			'ride_quality',
			'portability',
			'features',
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

		return $this->get_typed_spec_value( $specs, $key );
	}

	/**
	 * Get ranking map for a ranking type.
	 *
	 * @param string $ranking_type Ranking type.
	 * @return array Ranking map.
	 */
	private function get_ranking_map( string $ranking_type ): array {
		return match ( $ranking_type ) {
			'wheel_type' => self::WHEEL_TYPE_RANKING,
			'drive_type' => self::DRIVE_TYPE_RANKING,
			'terrain'    => self::TERRAIN_RANKING,
			default      => [],
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

		if ( isset( $ranking[ $normalized ] ) ) {
			return $ranking[ $normalized ];
		}

		foreach ( $ranking as $key => $rank ) {
			if ( strpos( $normalized, (string) $key ) !== false ) {
				return $rank;
			}
		}

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
	 * @param mixed $value Value.
	 * @return string String value or empty string.
	 */
	private function to_string( $value ): string {
		if ( $value === null ) {
			return '';
		}
		if ( is_array( $value ) ) {
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
	 * Count skateboard features.
	 *
	 * @param array $specs Product specs.
	 * @return int Feature count.
	 */
	private function count_skateboard_features( array $specs ): int {
		$count = 0;

		$features_array = $this->get_typed_spec_value( $specs, 'features' );
		if ( is_array( $features_array ) && ! empty( $features_array ) ) {
			$count += count( $features_array );
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

		if ( in_array( $unit, [ 'lbs', '"', 'mph', 'mi' ], true ) ) {
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
		$key   = $spec_def['key'] ?? '';
		$label = $spec_def['label'];

		$custom = $this->get_custom_spec_labels( $key );
		if ( $custom ) {
			if ( $is_advantage ) {
				if ( $rank === 1 ) {
					return $custom['best'];
				}
				if ( $percentile >= 90 ) {
					return $custom['excellent'];
				}
				return $custom['strong'];
			} else {
				if ( $rank === $total_count ) {
					return $custom['worst'];
				}
				if ( $percentile <= 10 ) {
					return $custom['very_weak'];
				}
				return $custom['weak'];
			}
		}

		$label_lower = strtolower( $label );

		if ( $is_advantage ) {
			if ( $rank === 1 ) {
				return "Best {$label_lower}";
			}
			if ( $percentile >= 90 ) {
				return "Excellent {$label_lower}";
			}
			return "Strong {$label_lower}";
		} else {
			if ( $rank === $total_count ) {
				return "Worst {$label_lower}";
			}
			if ( $percentile <= 10 ) {
				return "Very weak {$label_lower}";
			}
			return "Below average {$label_lower}";
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
			'dimensions.weight' => [
				'best'      => 'Lightest',
				'excellent' => 'Very light',
				'strong'    => 'Light',
				'worst'     => 'Heaviest',
				'very_weak' => 'Very heavy',
				'weak'      => 'Heavy',
			],
			'motor.power_nominal' => [
				'best'      => 'Most powerful motor',
				'excellent' => 'Powerful motor',
				'strong'    => 'Good motor power',
				'worst'     => 'Weakest motor',
				'very_weak' => 'Very low motor power',
				'weak'      => 'Low motor power',
			],
			'motor.power_peak' => [
				'best'      => 'Highest peak power',
				'excellent' => 'Very high peak power',
				'strong'    => 'Good peak power',
				'worst'     => 'Lowest peak power',
				'very_weak' => 'Very low peak power',
				'weak'      => 'Low peak power',
			],
			'battery.capacity' => [
				'best'      => 'Largest battery',
				'excellent' => 'Large battery',
				'strong'    => 'Good battery size',
				'worst'     => 'Smallest battery',
				'very_weak' => 'Very small battery',
				'weak'      => 'Small battery',
			],
			'battery.voltage' => [
				'best'      => 'Highest voltage',
				'excellent' => 'Very high voltage',
				'strong'    => 'High voltage',
				'worst'     => 'Lowest voltage',
				'very_weak' => 'Very low voltage',
				'weak'      => 'Low voltage',
			],
			'manufacturer_top_speed' => [
				'best'      => 'Fastest',
				'excellent' => 'Very fast',
				'strong'    => 'Fast',
				'worst'     => 'Slowest',
				'very_weak' => 'Very slow',
				'weak'      => 'Slow',
			],
			'wheels.wheel_size' => [
				'best'      => 'Largest wheels',
				'excellent' => 'Very large wheels',
				'strong'    => 'Large wheels',
				'worst'     => 'Smallest wheels',
				'very_weak' => 'Very small wheels',
				'weak'      => 'Small wheels',
			],
			default => null,
		};
	}

	/**
	 * Get tier label based on score difference.
	 *
	 * @param string $key         Score key.
	 * @param float  $diff        Score difference from average.
	 * @param bool   $is_positive Whether the difference is positive (advantage).
	 * @return string Tier label.
	 */
	private function get_score_tier_label( string $key, float $diff, bool $is_positive ): string {
		$abs_diff = abs( $diff );

		$labels = match ( $key ) {
			'battery_range' => [
				'excellent'  => 'Excellent battery & range',
				'great'      => 'Great battery & range',
				'above_avg'  => 'Good battery & range',
				'very_weak'  => 'Very limited battery & range',
				'weak'       => 'Limited battery & range',
				'below_avg'  => 'Below-average battery & range',
			],
			'ride_quality' => [
				'excellent'  => 'Excellent ride quality',
				'great'      => 'Great ride quality',
				'above_avg'  => 'Good ride quality',
				'very_weak'  => 'Poor ride quality',
				'weak'       => 'Below-average ride quality',
				'below_avg'  => 'Mediocre ride quality',
			],
			default => [],
		};

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
	 * Get descriptive details for a score-based spec.
	 *
	 * @param string $key          Score key.
	 * @param array  $specs        Product specs.
	 * @param bool   $is_advantage Whether this is a strength.
	 * @return string Details text, or empty string if none available.
	 */
	private function get_score_details( string $key, array $specs, bool $is_advantage ): string {
		return match ( $key ) {
			'ride_quality'  => $this->get_ride_quality_details( $specs, $is_advantage ),
			'battery_range' => $this->get_battery_range_details( $specs, $is_advantage ),
			default         => '',
		};
	}

	/**
	 * Get descriptive details for ride quality score.
	 *
	 * @param array $specs        Product specs.
	 * @param bool  $is_advantage Whether this is a strength.
	 * @return string Details text.
	 */
	private function get_ride_quality_details( array $specs, bool $is_advantage ): string {
		$details = [];

		$wheel_size = $this->get_typed_spec_value( $specs, 'wheels.wheel_size' );
		$deck_len   = $this->get_typed_spec_value( $specs, 'deck.length' );
		$terrain    = $this->to_string( $this->get_typed_spec_value( $specs, 'wheels.terrain' ) );

		if ( $wheel_size && is_numeric( $wheel_size ) ) {
			$details[] = $wheel_size . 'mm wheels';
		}
		if ( $deck_len && is_numeric( $deck_len ) ) {
			$details[] = $deck_len . '" deck';
		}
		if ( $terrain ) {
			$details[] = strtolower( $terrain );
		}

		return implode( ', ', $details );
	}

	/**
	 * Get descriptive details for battery & range score.
	 *
	 * @param array $specs        Product specs.
	 * @param bool  $is_advantage Whether this is a strength.
	 * @return string Details text.
	 */
	private function get_battery_range_details( array $specs, bool $is_advantage ): string {
		$details = [];

		$capacity = $this->get_typed_spec_value( $specs, 'battery.capacity' );
		$voltage  = $this->get_typed_spec_value( $specs, 'battery.voltage' );

		if ( $is_advantage ) {
			if ( $capacity && is_numeric( $capacity ) ) {
				$details[] = number_format( (int) $capacity ) . ' Wh battery';
			}
			if ( $voltage && is_numeric( $voltage ) ) {
				$details[] = $voltage . 'V';
			}
		} else {
			if ( $capacity && is_numeric( $capacity ) ) {
				$details[] = 'only ' . number_format( (int) $capacity ) . ' Wh battery';
			}
		}

		return implode( ', ', $details );
	}
}
