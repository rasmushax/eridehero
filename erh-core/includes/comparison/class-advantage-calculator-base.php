<?php
/**
 * Base Advantage Calculator.
 *
 * Provides shared utilities for product-type-specific advantage calculators.
 * Contains formatting, comparison, and spec access functions.
 *
 * @package ERH\Comparison
 */

declare(strict_types=1);

namespace ERH\Comparison;

use ERH\Config\SpecConfig;

/**
 * Abstract base class for advantage calculators.
 */
abstract class AdvantageCalculatorBase implements AdvantageCalculatorInterface {

    /**
     * Maximum advantages per product.
     *
     * @var int
     */
    protected int $max_advantages = 4;

    /**
     * Get the spec wrapper key for this product type.
     *
     * ProductCache data (wp_product_data) stores specs with wrappers:
     * - E-scooters: 'e-scooters.battery.capacity'
     * - E-bikes: 'e-bikes.battery.battery_capacity'
     *
     * @return string Wrapper key (e.g., 'e-scooters', 'e-bikes').
     */
    abstract protected function get_spec_wrapper(): string;

    /**
     * Get a spec value with automatic wrapper prefixing.
     *
     * For ProductCache data (wp_product_data), specs are stored with wrappers.
     * This helper automatically prefixes the key with the product type wrapper.
     *
     * @param array  $specs Specs array from ProductCache.
     * @param string $key   Spec key without wrapper (e.g., 'battery.capacity').
     * @return mixed Value or null.
     */
    protected function get_typed_spec_value( array $specs, string $key ) {
        $wrapper      = $this->get_spec_wrapper();
        $prefixed_key = $wrapper . '.' . $key;

        // Try with wrapper prefix first (raw ProductCache data).
        $value = $this->get_nested_spec( $specs, $prefixed_key );

        // Try without wrapper (flattened compare data).
        if ( $value === null ) {
            $value = $this->get_nested_spec( $specs, $key );
        }

        return $value;
    }

    /**
     * Threshold percentage for declaring an advantage.
     *
     * @var float
     */
    protected float $threshold = 3.0;

    /**
     * Get a nested spec value using dot notation.
     *
     * @param array  $specs    Specs array.
     * @param string $spec_key Dot-notation key (e.g., 'motor.power_peak').
     * @return mixed Value or null.
     */
    protected function get_nested_spec(array $specs, string $spec_key) {
        if (empty($spec_key)) {
            return null;
        }

        // Direct access first.
        if (isset($specs[$spec_key])) {
            return $specs[$spec_key];
        }

        // Try dot notation.
        $parts = explode('.', $spec_key);
        $value = $specs;

        foreach ($parts as $part) {
            if (!is_array($value) || !isset($value[$part])) {
                return null;
            }
            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Get spec value with optional normalization.
     *
     * @param array  $specs    Full specs array.
     * @param string $spec_key Spec key.
     * @param array  $spec     Spec configuration.
     * @return mixed Normalized value or raw value.
     */
    protected function get_normalized_spec_value(array $specs, string $spec_key, array $spec) {
        $raw_value = $this->get_nested_spec($specs, $spec_key);

        // Check for normalizer.
        $normalizer = $spec['normalizer'] ?? null;
        if ($normalizer && function_exists($normalizer)) {
            // Some normalizers need the full specs array.
            if (!empty($spec['normalizerFullSpecs'])) {
                return $normalizer($specs);
            }
            return $normalizer($raw_value);
        }

        return $raw_value;
    }

    /**
     * Compare two spec values and determine winner.
     *
     * @param mixed $val_a Product A value.
     * @param mixed $val_b Product B value.
     * @param array $spec  Spec configuration.
     * @return int|null Winner index (0 or 1) or null if tie/incomparable.
     */
    protected function compare_spec_values($val_a, $val_b, array $spec): ?int {
        // Handle ranked values.
        if (!empty($spec['ranking'])) {
            $ranking = $spec['ranking'];
            $rank_a = array_search($val_a, $ranking, true);
            $rank_b = array_search($val_b, $ranking, true);

            if ($rank_a === false || $rank_b === false) {
                return null;
            }
            if ($rank_a === $rank_b) {
                return null;
            }

            return $rank_a > $rank_b ? 0 : 1;
        }

        // Handle numeric values.
        if (is_numeric($val_a) && is_numeric($val_b)) {
            $a = (float) $val_a;
            $b = (float) $val_b;

            if ($a === $b) {
                return null;
            }

            $higher_better = $spec['higherBetter'] ?? true;
            if ($higher_better) {
                return $a > $b ? 0 : 1;
            }
            return $a < $b ? 0 : 1;
        }

        return null;
    }

    /**
     * Format a number for display.
     *
     * @param float $value Number to format.
     * @return string Formatted number.
     */
    protected function format_spec_number(float $value): string {
        // Remove trailing zeros for clean display.
        if (floor($value) == $value) {
            return number_format($value, 0);
        }
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }

    /**
     * Format a basic spec advantage (numeric comparison).
     *
     * @param string $spec_key   Spec key.
     * @param array  $spec       Spec configuration.
     * @param float  $val_a      Product A value.
     * @param float  $val_b      Product B value.
     * @param int    $winner_idx Winner index (0 or 1).
     * @return array|null Advantage data.
     */
    protected function format_spec_advantage(string $spec_key, array $spec, float $val_a, float $val_b, int $winner_idx): ?array {
        $winner_val = $winner_idx === 0 ? $val_a : $val_b;
        $loser_val = $winner_idx === 0 ? $val_b : $val_a;
        $diff = abs($winner_val - $loser_val);

        $label = $spec['label'] ?? $spec_key;
        $unit = $spec['unit'] ?? '';
        $diff_format = $spec['diffFormat'] ?? 'more';
        $tooltip = $spec['tooltip'] ?? null;
        $format = $spec['format'] ?? null;

        // Format values - use 2 decimal places for 'decimal' format (ratio metrics).
        if ($format === 'decimal') {
            $diff_fmt = number_format($diff, 2);
            $winner_fmt = number_format($winner_val, 2);
            $loser_fmt = number_format($loser_val, 2);
        } else {
            $diff_fmt = $this->format_spec_number($diff);
            $winner_fmt = $this->format_spec_number($winner_val);
            $loser_fmt = $this->format_spec_number($loser_val);
        }

        // Build the text based on diffFormat.
        $text = $this->build_advantage_text($diff_format, $diff_fmt, $unit, $label);

        // Clean up double spaces.
        $text = preg_replace('/\s+/', ' ', trim($text));

        // Build comparison string.
        if (in_array($diff_format, ['foldable_bars', 'has_feature'], true)) {
            $comparison = 'yes vs no';
        } else {
            $unit_str = $unit ? " {$unit}" : '';
            $comparison = "{$winner_fmt}{$unit_str} vs {$loser_fmt}{$unit_str}";
        }

        return [
            'text'       => $text,
            'comparison' => $comparison,
            'winner'     => $winner_idx,
            'spec_key'   => $spec_key,
            'winner_val' => $winner_val,
            'loser_val'  => $loser_val,
            'diff'       => $diff,
            'tooltip'    => $tooltip,
        ];
    }

    /**
     * Build advantage text based on format.
     *
     * @param string $diff_format Format type.
     * @param string $diff_fmt    Formatted difference value.
     * @param string $unit        Unit string.
     * @param string $label       Label string.
     * @return string Advantage text.
     */
    protected function build_advantage_text(string $diff_format, string $diff_fmt, string $unit, string $label): string {
        switch ($diff_format) {
            case 'faster':
                return "{$diff_fmt} {$unit} faster {$label}";
            case 'more':
                return "{$diff_fmt}{$unit} more {$label}";
            case 'higher':
                return "{$diff_fmt}{$unit} higher {$label}";
            case 'larger':
                return "{$diff_fmt}{$unit} larger {$label}";
            case 'lighter':
                return "{$diff_fmt} {$unit} lighter";
            case 'longer':
                return "{$diff_fmt} {$unit} longer {$label}";
            case 'shorter':
                return "{$diff_fmt} {$unit} shorter {$label}";
            case 'foldable_bars':
                return "foldable {$label}";
            case 'has_feature':
                return "{$label}";
            case 'lower':
                return "lower maintenance {$label}";
            case 'water_resistance':
                return "higher {$label}";
            case 'safer_tires':
                return 'safer tires';
            case 'larger_tires':
                return 'larger tires';
            case 'better':
                return "better {$label}";
            default:
                return "{$diff_fmt}{$unit} {$diff_format} {$label}";
        }
    }

    /**
     * Format a ranked/categorical advantage.
     *
     * @param string $spec_key Spec key.
     * @param array  $spec     Spec config.
     * @param mixed  $val_a    Product A value.
     * @param mixed  $val_b    Product B value.
     * @return array|null Advantage data or null.
     */
    protected function format_ranked_advantage(string $spec_key, array $spec, $val_a, $val_b): ?array {
        $ranking = $spec['ranking'] ?? [];
        $rank_a = array_search($val_a, $ranking, true);
        $rank_b = array_search($val_b, $ranking, true);

        if ($rank_a === false || $rank_b === false || $rank_a === $rank_b) {
            return null;
        }

        $winner_idx = $rank_a > $rank_b ? 0 : 1;
        $winner_val = $winner_idx === 0 ? $val_a : $val_b;
        $loser_val = $winner_idx === 0 ? $val_b : $val_a;

        $label = $spec['label'] ?? $spec_key;
        $diff_format = $spec['diffFormat'] ?? '';

        // Build text based on format.
        $loser_display = strtolower($loser_val);
        $loser_is_none = $loser_display === 'none';

        switch ($diff_format) {
            case 'suspension':
                $text = $loser_is_none
                    ? ucfirst($winner_val) . ' vs no suspension'
                    : ucfirst($winner_val) . ' vs ' . $loser_display . ' suspension';
                break;
            case 'tire_type':
                $text = ucfirst($winner_val) . ' vs ' . $loser_display . ' tires';
                break;
            default:
                $text = ucfirst($winner_val) . ' vs ' . $loser_display . ' ' . $label;
        }

        return [
            'text'       => $text,
            'comparison' => null,
            'winner'     => $winner_idx,
            'spec_key'   => $spec_key,
            'winner_val' => $winner_val,
            'loser_val'  => $loser_val,
            'tooltip'    => $spec['tooltip'] ?? null,
        ];
    }

    /**
     * Format advantage for specs with displayFormatter.
     *
     * @param string $spec_key   Spec key.
     * @param array  $spec       Spec configuration.
     * @param mixed  $raw_a      Product A raw value.
     * @param mixed  $raw_b      Product B raw value.
     * @param int    $winner_idx Winner index (0 or 1).
     * @return array|null Advantage data.
     */
    protected function format_display_formatter_advantage(string $spec_key, array $spec, $raw_a, $raw_b, int $winner_idx): ?array {
        $formatter = $spec['displayFormatter'] ?? null;
        if (!$formatter || !function_exists($formatter)) {
            return null;
        }

        $label = $spec['label'] ?? $spec_key;
        $tooltip = $spec['tooltip'] ?? null;
        $diff_format = $spec['diffFormat'] ?? 'better';

        // Format both values using the display formatter.
        $display_a = $formatter($raw_a);
        $display_b = $formatter($raw_b);

        $winner_display = $winner_idx === 0 ? $display_a : $display_b;
        $loser_display = $winner_idx === 0 ? $display_b : $display_a;

        // Handle water_resistance format.
        if ($diff_format === 'water_resistance') {
            return [
                'text'       => 'Higher ' . $label,
                'comparison' => strtoupper($winner_display) . ' vs ' . strtoupper($loser_display),
                'winner'     => $winner_idx,
                'spec_key'   => $spec_key,
                'winner_val' => $winner_display,
                'loser_val'  => $loser_display,
                'tooltip'    => $tooltip,
            ];
        }

        // Build headline and comparison.
        $loser_lower = strtolower($loser_display);
        $loser_is_none = $loser_lower === 'none';

        $comparison = $loser_is_none
            ? ucfirst($winner_display) . ' vs none'
            : ucfirst($winner_display) . ' vs ' . $loser_lower;

        // Build headline based on diffFormat.
        $text = ($diff_format === 'suspension') ? 'Better ' . $label : 'Better ' . $label;

        return [
            'text'       => $text,
            'comparison' => $comparison,
            'winner'     => $winner_idx,
            'spec_key'   => $spec_key,
            'winner_val' => $winner_display,
            'loser_val'  => $loser_display,
            'tooltip'    => $tooltip,
        ];
    }

    /**
     * Format motor count advantage (dual vs single).
     *
     * @param mixed $val_a Product A motor count.
     * @param mixed $val_b Product B motor count.
     * @param array $spec  Spec configuration.
     * @return array|null Advantage data or null.
     */
    protected function format_motor_count_advantage($val_a, $val_b, array $spec): ?array {
        $count_a = is_numeric($val_a) ? (int) $val_a : 1;
        $count_b = is_numeric($val_b) ? (int) $val_b : 1;

        if ($count_a === $count_b) {
            return null;
        }

        $winner_idx = $count_a > $count_b ? 0 : 1;
        $winner_count = $winner_idx === 0 ? $count_a : $count_b;
        $loser_count = $winner_idx === 0 ? $count_b : $count_a;

        $winner_label = $winner_count > 1 ? 'Dual motor' : 'Single motor';
        $loser_label = $loser_count > 1 ? 'dual motor' : 'single motor';

        return [
            'text'       => "{$winner_label} vs {$loser_label}",
            'comparison' => null,
            'winner'     => $winner_idx,
            'spec_key'   => 'motor.motor_count',
            'winner_val' => $winner_count,
            'loser_val'  => $loser_count,
            'diff'       => abs($count_a - $count_b),
            'tooltip'    => $spec['tooltip'] ?? null,
        ];
    }

    /**
     * Format feature count advantage.
     *
     * @param mixed $features_a Product A features array.
     * @param mixed $features_b Product B features array.
     * @param array $spec       Spec configuration.
     * @return array|null Advantage data or null.
     */
    protected function format_feature_count_advantage($features_a, $features_b, array $spec): ?array {
        $arr_a = is_array($features_a) ? $features_a : [];
        $arr_b = is_array($features_b) ? $features_b : [];

        $count_a = count($arr_a);
        $count_b = count($arr_b);

        $min_diff = $spec['minDiff'] ?? 2;
        $diff = abs($count_a - $count_b);

        if ($diff < $min_diff) {
            return null;
        }

        $winner_idx = $count_a > $count_b ? 0 : 1;
        $winner_features = $winner_idx === 0 ? $arr_a : $arr_b;
        $loser_features = $winner_idx === 0 ? $arr_b : $arr_a;
        $winner_count = $winner_idx === 0 ? $count_a : $count_b;
        $loser_count = $winner_idx === 0 ? $count_b : $count_a;

        // Find unique features.
        $loser_lower = array_map('strtolower', $loser_features);
        $unique_features = array_filter($winner_features, function ($feature) use ($loser_lower) {
            return !in_array(strtolower($feature), $loser_lower, true);
        });

        // Build comparison string.
        $unique_list = array_values($unique_features);
        if (count($unique_list) > 4) {
            $comparison = implode(', ', array_slice($unique_list, 0, 4)) . ' +' . (count($unique_list) - 4) . ' more';
        } else {
            $comparison = implode(', ', $unique_list);
        }

        return [
            'text'       => 'More features',
            'comparison' => $comparison ?: "{$winner_count} vs {$loser_count}",
            'winner'     => $winner_idx,
            'spec_key'   => 'features',
            'winner_val' => $winner_count,
            'loser_val'  => $loser_count,
            'diff'       => $diff,
            'tooltip'    => $spec['tooltip'] ?? null,
        ];
    }

    /**
     * Format a detail string for composite comparison line.
     *
     * @param string $spec_key   Spec key.
     * @param array  $spec       Spec config.
     * @param mixed  $val_a      Product A value.
     * @param mixed  $val_b      Product B value.
     * @param int    $winner_idx Winner index.
     * @return string|null Detail string or null.
     */
    protected function format_composite_detail(string $spec_key, array $spec, $val_a, $val_b, int $winner_idx): ?string {
        $winner_val = $winner_idx === 0 ? $val_a : $val_b;
        $loser_val = $winner_idx === 0 ? $val_b : $val_a;
        $unit = $spec['unit'] ?? '';
        $label = $spec['label'] ?? '';

        // Handle specs with displayFormatter.
        if (!empty($spec['displayFormatter'])) {
            $formatter = $spec['displayFormatter'];
            if (function_exists($formatter)) {
                $winner_str = strtolower($formatter($winner_val));
                $loser_str = strtolower($formatter($loser_val));
                if ($loser_str === 'none') {
                    return "{$winner_str} vs no {$label}";
                }
                return "{$winner_str} vs {$loser_str} {$label}";
            }
        }

        // Handle ranked values.
        if (!empty($spec['ranking'])) {
            $winner_str = strtolower((string) $winner_val);
            $loser_str = strtolower((string) $loser_val);
            if ($loser_str === 'none') {
                return "{$winner_str} vs no {$label}";
            }
            return "{$winner_str} vs {$loser_str} {$label}";
        }

        // Handle numeric values.
        if (is_numeric($winner_val) && is_numeric($loser_val)) {
            $w_fmt = $this->format_spec_number((float) $winner_val);
            $l_fmt = $this->format_spec_number((float) $loser_val);
            return "{$w_fmt}{$unit} vs {$l_fmt}{$unit} {$label}";
        }

        return null;
    }

    /**
     * Check if an advantage should be added (within limits).
     *
     * @param array $advantages Current advantages array.
     * @param int   $winner_idx Winner index.
     * @return bool True if can add.
     */
    protected function can_add_advantage(array $advantages, int $winner_idx): bool {
        return count($advantages[$winner_idx] ?? []) < $this->max_advantages;
    }
}
