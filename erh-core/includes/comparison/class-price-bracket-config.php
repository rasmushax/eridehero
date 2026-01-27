<?php
/**
 * Price Bracket Configuration.
 *
 * Defines price brackets for single-product analysis comparisons.
 * Products are compared against others in the same price range.
 *
 * @package ERH\Comparison
 */

declare(strict_types=1);

namespace ERH\Comparison;

/**
 * Configuration class for price-based product bracketing.
 */
class PriceBracketConfig {

	/**
	 * Price brackets with bounds (currency-agnostic - uses regional price as-is).
	 *
	 * Each bracket has:
	 * - min: Lower bound (inclusive)
	 * - max: Upper bound (exclusive)
	 * - label: Human-readable label for UI
	 *
	 * @var array<string, array{min: int, max: int, label: string}>
	 */
	public const BRACKETS = [
		'budget'      => [ 'min' => 0,    'max' => 500,         'label' => 'Budget' ],
		'midrange'    => [ 'min' => 500,  'max' => 1000,        'label' => 'Mid-Range' ],
		'performance' => [ 'min' => 1000, 'max' => 1500,        'label' => 'Performance' ],
		'premium'     => [ 'min' => 1500, 'max' => 2500,        'label' => 'Premium' ],
		'ultra'       => [ 'min' => 2500, 'max' => PHP_INT_MAX, 'label' => 'Ultra' ],
	];

	/**
	 * E-bike specific price brackets.
	 *
	 * E-bikes are generally more expensive than e-scooters due to
	 * larger batteries, more complex drivetrains, and component quality.
	 *
	 * @var array<string, array{min: int, max: int, label: string}>
	 */
	public const EBIKE_BRACKETS = [
		'budget'      => [ 'min' => 0,    'max' => 1500,        'label' => 'Budget' ],
		'midrange'    => [ 'min' => 1500, 'max' => 3000,        'label' => 'Mid-Range' ],
		'performance' => [ 'min' => 3000, 'max' => 5000,        'label' => 'Performance' ],
		'premium'     => [ 'min' => 5000, 'max' => 8000,        'label' => 'Premium' ],
		'ultra'       => [ 'min' => 8000, 'max' => PHP_INT_MAX, 'label' => 'Ultra' ],
	];

	/**
	 * Minimum products needed in bracket for bracket-based comparison.
	 * If fewer products exist, falls back to category-wide percentile.
	 */
	public const MIN_BRACKET_SIZE = 5;

	/**
	 * Percentile threshold for advantages - must be in top X%.
	 * Value of 20 means product must be in top 20% (percentile >= 80).
	 */
	public const ADVANTAGE_PERCENTILE = 20;

	/**
	 * Percentile threshold for weaknesses - must be in bottom X%.
	 * Value of 20 means product must be in bottom 20% (percentile <= 20).
	 */
	public const WEAKNESS_PERCENTILE = 20;

	/**
	 * Alternative threshold: percentage better/worse than bracket average.
	 * Used as OR condition with percentile threshold.
	 */
	public const AVERAGE_THRESHOLD = 15;

	/**
	 * Get brackets for a specific product type.
	 *
	 * @param string $product_type Product type slug (escooter, ebike, etc.).
	 * @return array<string, array{min: int, max: int, label: string}> Brackets array.
	 */
	public static function get_brackets_for_type( string $product_type ): array {
		$type = strtolower( trim( $product_type ) );

		return match ( $type ) {
			'ebike', 'electric bike', 'e-bike' => self::EBIKE_BRACKETS,
			default => self::BRACKETS,
		};
	}

	/**
	 * Get bracket key for a given price.
	 *
	 * @param float  $price        Price in regional currency.
	 * @param string $product_type Product type slug (optional, defaults to escooter brackets).
	 * @return string Bracket key (budget, midrange, performance, premium, ultra).
	 */
	public static function get_bracket_key( float $price, string $product_type = 'escooter' ): string {
		$brackets = self::get_brackets_for_type( $product_type );

		foreach ( $brackets as $key => $bracket ) {
			if ( $price >= $bracket['min'] && $price < $bracket['max'] ) {
				return $key;
			}
		}
		// Fallback to ultra for any edge cases.
		return 'ultra';
	}

	/**
	 * Get full bracket configuration for a given price.
	 *
	 * @param float  $price        Price to look up.
	 * @param string $product_type Product type slug (optional, defaults to escooter brackets).
	 * @return array{key: string, min: int, max: int, label: string} Bracket config.
	 */
	public static function get_bracket( float $price, string $product_type = 'escooter' ): array {
		$brackets = self::get_brackets_for_type( $product_type );
		$key      = self::get_bracket_key( $price, $product_type );
		$config   = $brackets[ $key ];

		return [
			'key'   => $key,
			'min'   => $config['min'],
			'max'   => $config['max'],
			'label' => $config['label'],
		];
	}

	/**
	 * Get bracket label for display.
	 *
	 * @param string $bracket_key  Bracket key.
	 * @param string $product_type Product type slug (optional, defaults to escooter brackets).
	 * @return string Human-readable label.
	 */
	public static function get_bracket_label( string $bracket_key, string $product_type = 'escooter' ): string {
		$brackets = self::get_brackets_for_type( $product_type );
		return $brackets[ $bracket_key ]['label'] ?? 'Unknown';
	}

	/**
	 * Get price range string for display.
	 *
	 * @param string $bracket_key     Bracket key.
	 * @param string $currency_symbol Currency symbol (e.g., '$', '€').
	 * @param string $product_type    Product type slug (optional, defaults to escooter brackets).
	 * @return string Formatted range string (e.g., "$500-$1,000").
	 */
	public static function get_bracket_range_display( string $bracket_key, string $currency_symbol = '$', string $product_type = 'escooter' ): string {
		$brackets = self::get_brackets_for_type( $product_type );
		$bracket  = $brackets[ $bracket_key ] ?? null;

		if ( ! $bracket ) {
			return '';
		}

		$min = number_format( $bracket['min'] );
		$max = $bracket['max'] === PHP_INT_MAX ? '+' : number_format( $bracket['max'] );

		if ( $bracket['max'] === PHP_INT_MAX ) {
			return "{$currency_symbol}{$min}+";
		}

		return "{$currency_symbol}{$min}-{$currency_symbol}{$max}";
	}

	/**
	 * Check if a value qualifies as an advantage.
	 *
	 * A spec is an advantage if EITHER:
	 * 1. Product is in top ADVANTAGE_PERCENTILE% (percentile >= 80)
	 * 2. Product is AVERAGE_THRESHOLD% better than bracket average
	 *
	 * Note: Percentile is calculated as "percentage of products beaten" and already
	 * accounts for higher_better direction - higher percentile is ALWAYS better.
	 *
	 * @param float $percentile    Product's percentile rank (0-100, higher = beats more).
	 * @param float $pct_vs_avg    Percentage difference from average (positive = above avg).
	 * @param bool  $higher_better Whether higher raw values are better for this spec.
	 * @return bool True if qualifies as advantage.
	 */
	public static function is_advantage( float $percentile, float $pct_vs_avg, bool $higher_better ): bool {
		$percentile_threshold = 100 - self::ADVANTAGE_PERCENTILE; // 80

		// Percentile check: top 20% (percentile >= 80) - same for all specs.
		if ( $percentile >= $percentile_threshold ) {
			return true;
		}

		// Average threshold check: depends on higher_better direction.
		if ( $higher_better ) {
			// Higher is better: 15%+ above average is good.
			return $pct_vs_avg >= self::AVERAGE_THRESHOLD;
		} else {
			// Lower is better: 15%+ below average is good (negative pct_vs_avg).
			return $pct_vs_avg <= -self::AVERAGE_THRESHOLD;
		}
	}

	/**
	 * Check if a value qualifies as a weakness.
	 *
	 * A spec is a weakness if EITHER:
	 * 1. Product is in bottom WEAKNESS_PERCENTILE% (percentile <= 20)
	 * 2. Product is AVERAGE_THRESHOLD% worse than bracket average
	 *
	 * Note: Percentile is calculated as "percentage of products beaten" and already
	 * accounts for higher_better direction - lower percentile is ALWAYS worse.
	 *
	 * @param float $percentile    Product's percentile rank (0-100, lower = beats fewer).
	 * @param float $pct_vs_avg    Percentage difference from average (positive = above avg).
	 * @param bool  $higher_better Whether higher raw values are better for this spec.
	 * @return bool True if qualifies as weakness.
	 */
	public static function is_weakness( float $percentile, float $pct_vs_avg, bool $higher_better ): bool {
		// Percentile check: bottom 20% (percentile <= 20) - same for all specs.
		if ( $percentile <= self::WEAKNESS_PERCENTILE ) {
			return true;
		}

		// Average threshold check: depends on higher_better direction.
		if ( $higher_better ) {
			// Higher is better: 15%+ below average is bad (negative pct_vs_avg).
			return $pct_vs_avg <= -self::AVERAGE_THRESHOLD;
		} else {
			// Lower is better: 15%+ above average is bad (positive pct_vs_avg).
			return $pct_vs_avg >= self::AVERAGE_THRESHOLD;
		}
	}
}
