<?php
/**
 * Electric Scooter Advantages Calculator.
 *
 * Calculates spec-based advantages for electric scooter comparisons.
 * Contains escooter-specific logic for composites (ride quality, portability),
 * normalizers (suspension, tire type), and advantage formatting.
 *
 * @package ERH\Comparison\Calculators
 */

declare(strict_types=1);

namespace ERH\Comparison\Calculators;

use ERH\Comparison\AdvantageCalculatorBase;
use ERH\Comparison\PriceBracketConfig;
use ERH\Config\SpecConfig;

/**
 * Electric scooter advantage calculator.
 */
class EscooterAdvantages extends AdvantageCalculatorBase {

    /**
     * Product type this calculator handles.
     *
     * @var string
     */
    private string $product_type = 'escooter';

    /**
     * Get the product type.
     *
     * @return string
     */
    public function get_product_type(): string {
        return $this->product_type;
    }

    /**
     * Get the spec wrapper key for e-scooters.
     *
     * @return string
     */
    protected function get_spec_wrapper(): string {
        return 'e-scooters';
    }

    /**
     * Calculate advantages for head-to-head (2 product) comparison.
     *
     * @param array $products Array of 2 product data arrays.
     * @return array Array with 2 elements, each containing advantages for that product.
     */
    public function calculate_head_to_head(array $products): array {
        if (count($products) !== 2) {
            return array_fill(0, count($products), []);
        }

        $spec_config = SpecConfig::get_comparison_specs();
        $threshold = SpecConfig::SPEC_ADVANTAGE_THRESHOLD;
        $this->max_advantages = SpecConfig::SPEC_ADVANTAGE_MAX;
        $this->threshold = $threshold;

        // Sort specs by priority.
        uasort($spec_config, fn($a, $b) => ($a['priority'] ?? 99) - ($b['priority'] ?? 99));

        $advantages = [[], []];
        $processed_keys = [];
        $composite_winners = [];
        $composite_specs = [];
        $handled_specs = [];

        foreach ($spec_config as $spec_key => $spec) {
            // Check max advantages reached for both products.
            if (count($advantages[0]) >= $this->max_advantages && count($advantages[1]) >= $this->max_advantages) {
                break;
            }

            // Skip specs that were already handled by a composite.
            if (in_array($spec_key, $handled_specs, true)) {
                $processed_keys[] = $spec_key;
                continue;
            }

            // Handle composite specs (e.g., "_ride_quality").
            if (($spec['type'] ?? '') === 'composite') {
                $result = $this->process_composite_advantage($spec_key, $spec, $products, $advantages);
                if ($result) {
                    $advantages = $result['advantages'];
                    if ($result['winner'] !== null) {
                        $composite_winners[$spec_key] = $result['winner'];
                    }
                    foreach ($spec['specs'] ?? [] as $child_key) {
                        $composite_specs[$child_key] = [
                            'composite' => $spec_key,
                            'winner'    => $result['winner'],
                            'close'     => $result['close'] ?? false,
                        ];
                    }
                    foreach ($result['handled_specs'] ?? [] as $handled_key) {
                        $handled_specs[] = $handled_key;
                    }
                }
                $processed_keys[] = $spec_key;
                continue;
            }

            // Check if this spec belongs to a composite.
            if (isset($composite_specs[$spec_key])) {
                $comp_info = $composite_specs[$spec_key];
                if (!$comp_info['close'] && $comp_info['winner'] !== null) {
                    $result = $this->process_individual_spec_for_loser(
                        $spec_key,
                        $spec,
                        $products,
                        $comp_info['winner'],
                        $advantages
                    );
                    if ($result) {
                        $advantages = $result;
                    }
                    $processed_keys[] = $spec_key;
                    continue;
                }
            }

            // Handle fallbackFor - skip if primary spec was already processed.
            if (!empty($spec['fallbackFor'])) {
                if (in_array($spec['fallbackFor'], $processed_keys, true)) {
                    continue;
                }
            }

            // Get values from both products.
            // Check for derived specs first (calculated metrics like wh_per_lb, speed_per_lb).
            if (!empty($spec['isDerived'])) {
                $val_a = $this->get_derived_spec_value($products[0]['specs'], $spec_key, $spec);
                $val_b = $this->get_derived_spec_value($products[1]['specs'], $spec_key, $spec);
            } else {
                $val_a = $this->get_normalized_spec_value($products[0]['specs'], $spec_key, $spec);
                $val_b = $this->get_normalized_spec_value($products[1]['specs'], $spec_key, $spec);
            }

            if ($val_a === null || $val_a === '' || $val_b === null || $val_b === '') {
                continue;
            }

            // For specs with displayFormatter, check raw values.
            if (!empty($spec['displayFormatter'])) {
                $raw_a = $this->get_nested_spec($products[0]['specs'], $spec_key);
                $raw_b = $this->get_nested_spec($products[1]['specs'], $spec_key);
                if (empty($raw_a) || empty($raw_b)) {
                    continue;
                }
            }

            $processed_keys[] = $spec_key;

            // Handle special formats.
            if (($spec['diffFormat'] ?? '') === 'dual_vs_single') {
                $adv = $this->format_motor_count_advantage($val_a, $val_b, $spec);
                if ($adv && $this->can_add_advantage($advantages, $adv['winner'])) {
                    $advantages[$adv['winner']][] = $adv;
                }
                continue;
            }

            if (($spec['diffFormat'] ?? '') === 'feature_count') {
                $features_a = $this->get_nested_spec($products[0]['specs'], $spec_key);
                $features_b = $this->get_nested_spec($products[1]['specs'], $spec_key);
                $adv = $this->format_feature_count_advantage($features_a, $features_b, $spec);
                if ($adv && $this->can_add_advantage($advantages, $adv['winner'])) {
                    $advantages[$adv['winner']][] = $adv;
                }
                continue;
            }

            // Handle ranked/categorical specs.
            if (!empty($spec['ranking'])) {
                $adv = $this->format_ranked_advantage($spec_key, $spec, $val_a, $val_b);
                if ($adv && $this->can_add_advantage($advantages, $adv['winner'])) {
                    $advantages[$adv['winner']][] = $adv;
                }
                continue;
            }

            // Numeric comparison.
            if (!is_numeric($val_a) || !is_numeric($val_b)) {
                continue;
            }

            $a = (float) $val_a;
            $b = (float) $val_b;

            if ($a === $b) {
                continue;
            }

            // Skip if requireValidPair is set and either value is 0.
            if (!empty($spec['requireValidPair']) && ($a === 0.0 || $b === 0.0)) {
                continue;
            }

            // Calculate difference.
            $higher_better = $spec['higherBetter'] ?? true;
            $diff = abs($a - $b);
            $base = $higher_better ? min($a, $b) : max($a, $b);
            $pct_diff = $base > 0 ? ($diff / $base) * 100 : 0;

            // Check threshold.
            $min_diff = $spec['minDiff'] ?? null;
            if ($min_diff !== null) {
                if ($diff < $min_diff) {
                    continue;
                }
            } elseif ($pct_diff < $threshold) {
                continue;
            }

            // Determine winner.
            if ($higher_better) {
                $winner_idx = $a > $b ? 0 : 1;
            } else {
                $winner_idx = $a < $b ? 0 : 1;
            }

            if (!$this->can_add_advantage($advantages, $winner_idx)) {
                continue;
            }

            // Format the advantage.
            if (!empty($spec['displayFormatter'])) {
                $raw_a = $this->get_nested_spec($products[0]['specs'], $spec_key);
                $raw_b = $this->get_nested_spec($products[1]['specs'], $spec_key);
                $adv = $this->format_display_formatter_advantage($spec_key, $spec, $raw_a, $raw_b, $winner_idx);
            } else {
                $adv = $this->format_spec_advantage($spec_key, $spec, $a, $b, $winner_idx);
            }

            if ($adv) {
                $advantages[$winner_idx][] = $adv;
            }
        }

        return $advantages;
    }

    /**
     * Calculate advantages for multi-product (3+) comparison.
     *
     * For 3+ products, shows which product is "best" at each key spec.
     * Each product gets a list of specs where they're the winner.
     *
     * @param array $products Array of 3+ product data arrays.
     * @return array Array with advantages for each product index.
     */
    public function calculate_multi(array $products): ?array {
        $count = count($products);
        if ($count < 3) {
            return array_fill(0, $count, []);
        }

        // Initialize advantages array for each product.
        $advantages = array_fill(0, $count, []);

        // Define specs to compare in multi-product mode.
        // Format: [spec_key, label, unit, higherBetter, testedFallback]
        $multi_specs = $this->get_multi_comparison_specs();

        foreach ($multi_specs as $spec_def) {
            $result = $this->find_multi_winner($products, $spec_def);
            if ($result && $result['winner_idx'] !== null) {
                $advantages[$result['winner_idx']][] = $result['advantage'];
            }
        }

        // Add category-based advantages (maintenance, ride quality).
        $this->add_category_winner_advantages($products, $advantages);

        return $advantages;
    }

    /**
     * Get specs to compare in multi-product mode.
     *
     * @return array Array of spec definitions.
     */
    private function get_multi_comparison_specs(): array {
        return [
            // Top speed - prefer tested, fallback to manufacturer.
            // Tooltips auto-fetched from centralized SpecConfig::TOOLTIPS via get_tooltip().
            [
                'key'           => 'tested_top_speed',
                'fallback'      => 'manufacturer_top_speed',
                'label'         => 'Fastest',
                'unit'          => 'mph',
                'higher_better' => true,
                'format'        => 'speed',
                'tooltip'       => SpecConfig::get_tooltip('tested_top_speed', 'comparison'),
            ],
            // Battery capacity (Wh) - always use this, not range claimed.
            [
                'key'           => 'battery.capacity',
                'label'         => 'Largest battery',
                'unit'          => 'Wh',
                'higher_better' => true,
                'format'        => 'numeric',
                'tooltip'       => SpecConfig::get_tooltip('battery.capacity', 'comparison'),
            ],
            // Tested range - only if all have it.
            [
                'key'           => 'tested_range_regular',
                'label'         => 'Longest tested range',
                'unit'          => 'mi',
                'higher_better' => true,
                'format'        => 'numeric',
                'require_all'   => true,
                'tooltip'       => SpecConfig::get_tooltip('tested_range_regular', 'comparison'),
            ],
            // Weight - lighter is better.
            [
                'key'           => 'dimensions.weight',
                'label'         => 'Lightest',
                'unit'          => 'lbs',
                'higher_better' => false,
                'format'        => 'numeric',
                'tooltip'       => SpecConfig::get_tooltip('dimensions.weight', 'comparison'),
            ],
            // Max load capacity.
            [
                'key'           => 'dimensions.max_load',
                'label'         => 'Highest weight capacity',
                'unit'          => 'lbs',
                'higher_better' => true,
                'format'        => 'numeric',
                'tooltip'       => SpecConfig::get_tooltip('dimensions.max_load', 'comparison'),
            ],
            // Wh per pound - efficiency metric.
            [
                'key'           => 'wh_per_lb',
                'label'         => 'Best Wh/lb ratio',
                'unit'          => 'Wh/lb',
                'higher_better' => true,
                'format'        => 'decimal',
                'tooltip'       => SpecConfig::get_tooltip('wh_per_lb', 'comparison'),
            ],
            // mph per pound - power-to-weight metric.
            [
                'key'           => 'speed_per_lb',
                'label'         => 'Best mph/lb ratio',
                'unit'          => 'mph/lb',
                'higher_better' => true,
                'format'        => 'decimal',
                'tooltip'       => SpecConfig::get_tooltip('speed_per_lb', 'comparison'),
            ],
            // Suspension - best type wins.
            [
                'key'           => 'suspension.type',
                'label'         => 'Best suspension',
                'higher_better' => true,
                'format'        => 'suspension',
                'tooltip'       => SpecConfig::get_tooltip('suspension.type', 'comparison'),
            ],
            // IP rating.
            [
                'key'           => 'other.ip_rating',
                'label'         => 'Best water resistance',
                'higher_better' => true,
                'format'        => 'ip_rating',
                'tooltip'       => SpecConfig::get_tooltip('other.ip_rating', 'comparison'),
            ],
            // Features count.
            [
                'key'           => 'features',
                'label'         => 'Most features',
                'higher_better' => true,
                'format'        => 'feature_count',
                'tooltip'       => SpecConfig::get_tooltip('features', 'comparison'),
            ],
        ];
    }

    /**
     * Find the winner for a spec across all products.
     *
     * @param array $products Array of products.
     * @param array $spec_def Spec definition.
     * @return array|null Result with winner_idx and advantage, or null.
     */
    private function find_multi_winner(array $products, array $spec_def): ?array {
        $key = $spec_def['key'];
        $fallback = $spec_def['fallback'] ?? null;
        $require_all = $spec_def['require_all'] ?? false;
        $higher_better = $spec_def['higher_better'] ?? true;
        $format = $spec_def['format'] ?? 'numeric';

        // Collect values from all products.
        $values = [];
        $use_fallback = false;

        foreach ($products as $idx => $product) {
            // Check for derived/calculated metrics first.
            $value = $this->get_derived_spec_value($product['specs'], $key, $spec_def);

            if ($value === null) {
                $value = $this->get_nested_spec($product['specs'], $key);

                // Check if we need to use fallback.
                if (($value === null || $value === '') && $fallback) {
                    $value = $this->get_nested_spec($product['specs'], $fallback);
                    if ($value !== null && $value !== '') {
                        $use_fallback = true;
                    }
                }
            }

            $values[$idx] = $value;
        }

        // If require_all, skip if any product is missing the value.
        if ($require_all) {
            foreach ($values as $v) {
                if ($v === null || $v === '') {
                    return null;
                }
            }
        }

        // Find winner based on format.
        $winner_idx = null;
        $winner_value = null;
        $display_value = null;

        switch ($format) {
            case 'speed':
            case 'numeric':
                $result = $this->find_numeric_winner($values, $higher_better);
                $winner_idx = $result['idx'];
                $winner_value = $result['value'];
                $display_value = $this->format_spec_number((float) $winner_value) . ' ' . ($spec_def['unit'] ?? '');
                break;

            case 'decimal':
                $result = $this->find_numeric_winner($values, $higher_better);
                $winner_idx = $result['idx'];
                $winner_value = $result['value'];
                // Format with 2 decimal places for ratio metrics.
                $display_value = number_format((float) $winner_value, 2) . ' ' . ($spec_def['unit'] ?? '');
                break;

            case 'suspension':
                $result = $this->find_suspension_winner($products);
                $winner_idx = $result['idx'];
                $display_value = $result['display'];
                break;

            case 'ip_rating':
                $result = $this->find_ip_rating_winner($values);
                $winner_idx = $result['idx'];
                $display_value = $result['display'];
                break;

            case 'feature_count':
                $result = $this->find_feature_count_winner($values);
                $winner_idx = $result['idx'];
                $display_value = $result['display'];
                break;
        }

        if ($winner_idx === null) {
            return null;
        }

        // Build advantage.
        $label = $spec_def['label'];
        if ($use_fallback && $key === 'tested_top_speed') {
            $label = 'Fastest'; // Don't say "tested" if using manufacturer value.
        }

        return [
            'winner_idx' => $winner_idx,
            'advantage'  => [
                'text'       => $label,
                'comparison' => trim($display_value),
                'spec_key'   => $key,
                'tooltip'    => $spec_def['tooltip'] ?? null,
            ],
        ];
    }

    /**
     * Find winner for numeric values.
     *
     * Returns null for idx if there's a tie (multiple products with same best value).
     *
     * @param array $values       Values indexed by product index.
     * @param bool  $higher_better Whether higher is better.
     * @return array With 'idx' (null if tie) and 'value'.
     */
    private function find_numeric_winner(array $values, bool $higher_better): array {
        $best_value = null;
        $best_indices = [];

        foreach ($values as $idx => $value) {
            if ($value === null || $value === '' || !is_numeric($value)) {
                continue;
            }

            $num = (float) $value;

            if ($best_value === null) {
                $best_value = $num;
                $best_indices = [$idx];
                continue;
            }

            $is_better = $higher_better ? ($num > $best_value) : ($num < $best_value);
            $is_equal = abs($num - $best_value) < 0.001; // Float comparison tolerance.

            if ($is_better) {
                $best_value = $num;
                $best_indices = [$idx];
            } elseif ($is_equal) {
                $best_indices[] = $idx;
            }
        }

        // Return null idx if tie (multiple products with same best value).
        $winner_idx = count($best_indices) === 1 ? $best_indices[0] : null;

        return ['idx' => $winner_idx, 'value' => $best_value];
    }

    /**
     * Find winner for suspension (best type).
     *
     * Returns null for idx if there's a tie (multiple products with same best suspension).
     *
     * @param array $products Products array.
     * @return array With 'idx' (null if tie) and 'display'.
     */
    private function find_suspension_winner(array $products): array {
        $best_score = -1;
        $best_indices = [];
        $best_display = '';

        foreach ($products as $idx => $product) {
            $susp_array = $this->get_nested_spec($product['specs'], 'suspension.type');
            $score = $this->score_suspension($susp_array);

            if ($score > $best_score) {
                $best_score = $score;
                $best_indices = [$idx];
                $best_display = $this->format_suspension_display($susp_array);
            } elseif ($score === $best_score && $score > 0) {
                $best_indices[] = $idx;
            }
        }

        // Don't award if best has no suspension.
        if ($best_score <= 0) {
            return ['idx' => null, 'display' => ''];
        }

        // Return null idx if tie (multiple products with same best score).
        $winner_idx = count($best_indices) === 1 ? $best_indices[0] : null;

        return ['idx' => $winner_idx, 'display' => $best_display];
    }

    /**
     * Score suspension array for comparison.
     *
     * @param array|null $susp_array Suspension array.
     * @return int Score (higher = better).
     */
    private function score_suspension($susp_array): int {
        if (!$susp_array || !is_array($susp_array) || empty($susp_array)) {
            return 0;
        }

        $types = array_map(fn($s) => strtolower((string) $s), $susp_array);

        // Check for "None" only.
        if (count(array_filter($types, fn($t) => $t !== 'none' && $t !== '')) === 0) {
            return 0;
        }

        $score_type = function ($type) {
            if (strpos($type, 'hydraulic') !== false) return 3;
            if (strpos($type, 'spring') !== false || strpos($type, 'fork') !== false) return 2;
            if (strpos($type, 'rubber') !== false) return 1;
            return 0;
        };

        // Check for dual.
        $has_front = false;
        $has_rear = false;
        $max_type_score = 0;

        foreach ($types as $type) {
            if (strpos($type, 'dual') !== false) {
                return 7 + $score_type($type); // Dual: 8-10.
            }
            if (strpos($type, 'front') !== false && strpos($type, 'none') === false) {
                $has_front = true;
                $max_type_score = max($max_type_score, $score_type($type));
            }
            if (strpos($type, 'rear') !== false && strpos($type, 'none') === false) {
                $has_rear = true;
                $max_type_score = max($max_type_score, $score_type($type));
            }
        }

        if ($has_front && $has_rear) {
            return 7 + $max_type_score; // Both = dual bonus.
        }

        if ($has_front || $has_rear) {
            return 2 + $max_type_score; // Single: 3-5.
        }

        return 0;
    }

    /**
     * Format suspension array for display.
     *
     * @param array|null $susp_array Suspension array.
     * @return string Display string.
     */
    private function format_suspension_display($susp_array): string {
        if (!$susp_array || !is_array($susp_array)) {
            return 'None';
        }

        $filtered = array_filter($susp_array, fn($s) => $s && strtolower($s) !== 'none');
        if (empty($filtered)) {
            return 'None';
        }

        return implode(', ', $filtered);
    }

    /**
     * Find winner for IP rating.
     *
     * IP rating comparison rules:
     * 1. Compare water rating (second digit) first - higher is better
     * 2. If water ratings are equal, having a dust rating (IP) beats no dust rating (IPX)
     *
     * Returns null for idx if there's a tie (multiple products with same best rating).
     *
     * Examples:
     * - IPX5 (water=5) > IP54 (water=4)
     * - IP55 (water=5, dust=5) > IPX5 (water=5, dust=none)
     * - IP67 > IP66 > IPX6
     * - IP55 == IP55 → tie (no winner)
     *
     * @param array $values IP rating values.
     * @return array With 'idx' (null if tie) and 'display'.
     */
    private function find_ip_rating_winner(array $values): array {
        $best_water = -1;
        $best_has_dust = false;
        $best_indices = [];
        $best_display = '';

        foreach ($values as $idx => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalized = strtoupper(trim((string) $value));
            $parsed = $this->parse_ip_rating($normalized);

            if ($parsed === null) {
                continue;
            }

            // Compare: water rating first, then dust rating as tiebreaker.
            $is_better = false;
            $is_equal = false;

            if ($parsed['water'] > $best_water) {
                $is_better = true;
            } elseif ($parsed['water'] === $best_water) {
                if ($parsed['has_dust'] && !$best_has_dust) {
                    $is_better = true;
                } elseif ($parsed['has_dust'] === $best_has_dust) {
                    $is_equal = true;
                }
            }

            if ($is_better) {
                $best_water = $parsed['water'];
                $best_has_dust = $parsed['has_dust'];
                $best_indices = [$idx];
                $best_display = $normalized;
            } elseif ($is_equal && $best_water >= 4) {
                $best_indices[] = $idx;
            }
        }

        // Don't award if best has no/low water rating.
        if ($best_water < 4) {
            return ['idx' => null, 'display' => ''];
        }

        // Return null idx if tie (multiple products with same best rating).
        $winner_idx = count($best_indices) === 1 ? $best_indices[0] : null;

        return ['idx' => $winner_idx, 'display' => $best_display];
    }

    /**
     * Parse IP rating into water and dust components.
     *
     * @param string $rating IP rating string (e.g., "IP54", "IPX5").
     * @return array|null Array with 'water', 'dust', 'has_dust' or null if invalid.
     */
    private function parse_ip_rating(string $rating): ?array {
        // Match IP followed by digit or X, then digit.
        // Format: IP[dust][water] where dust can be X (no rating) or 0-6.
        if (!preg_match('/^IP([X0-9])([0-9])$/i', $rating, $matches)) {
            return null;
        }

        $dust_char = strtoupper($matches[1]);
        $water = (int) $matches[2];

        return [
            'water'    => $water,
            'dust'     => $dust_char === 'X' ? null : (int) $dust_char,
            'has_dust' => $dust_char !== 'X',
        ];
    }

    /**
     * Find winner for feature count.
     *
     * Returns null for idx if there's a tie (multiple products with same feature count).
     *
     * @param array $values Feature arrays.
     * @return array With 'idx' (null if tie) and 'display'.
     */
    private function find_feature_count_winner(array $values): array {
        $best_count = 0;
        $best_indices = [];

        foreach ($values as $idx => $value) {
            $count = is_array($value) ? count($value) : 0;

            if ($count > $best_count) {
                $best_count = $count;
                $best_indices = [$idx];
            } elseif ($count === $best_count && $count >= 3) {
                $best_indices[] = $idx;
            }
        }

        // Need at least 3 features to be notable.
        if ($best_count < 3) {
            return ['idx' => null, 'display' => ''];
        }

        // Return null idx if tie (multiple products with same feature count).
        $winner_idx = count($best_indices) === 1 ? $best_indices[0] : null;

        return ['idx' => $winner_idx, 'display' => $best_count . ' features'];
    }

    /**
     * Get derived/calculated spec value.
     *
     * Handles computed metrics like Wh/lb and mph/lb ratios.
     *
     * @param array  $specs    Product specs.
     * @param string $key      Spec key.
     * @param array  $spec_def Spec definition.
     * @return float|null Calculated value or null if not a derived spec.
     */
    private function get_derived_spec_value(array $specs, string $key, array $spec_def): ?float {
        $weight = $this->get_nested_spec($specs, 'dimensions.weight');
        if (!is_numeric($weight) || (float) $weight <= 0) {
            return null;
        }

        $weight_val = (float) $weight;

        switch ($key) {
            case 'wh_per_lb':
                // Battery capacity (Wh) divided by weight (lbs).
                $capacity = $this->get_nested_spec($specs, 'battery.capacity');
                if (!is_numeric($capacity) || (float) $capacity <= 0) {
                    return null;
                }
                return (float) $capacity / $weight_val;

            case 'speed_per_lb':
                // Top speed divided by weight.
                $speed = $this->get_nested_spec($specs, 'tested_top_speed');
                if (!is_numeric($speed) || (float) $speed <= 0) {
                    // Try fallback to manufacturer top speed.
                    $speed = $this->get_nested_spec($specs, 'manufacturer_top_speed');
                }
                if (!is_numeric($speed) || (float) $speed <= 0) {
                    return null;
                }
                return (float) $speed / $weight_val;

            default:
                return null;
        }
    }

    /**
     * Add category-based winner advantages (maintenance, ride quality).
     *
     * @param array $products   Products array.
     * @param array $advantages Advantages array (modified in place).
     */
    private function add_category_winner_advantages(array $products, array &$advantages): void {
        // Maintenance category winner.
        $maintenance_winner = $this->find_category_score_winner($products, 'maintenance');
        if ($maintenance_winner !== null) {
            $reasons = $this->get_maintenance_reasons($products[$maintenance_winner]['specs']);
            if (!empty($reasons)) {
                $advantages[$maintenance_winner][] = [
                    'text'       => 'Lowest maintenance',
                    'comparison' => implode(', ', $reasons),
                    'spec_key'   => '_maintenance',
                    'tooltip'    => SpecConfig::get_tooltip('_maintenance', 'comparison'),
                ];
            }
        }

        // Folded footprint (most compact).
        $footprint_winner = $this->find_footprint_winner($products);
        if ($footprint_winner !== null) {
            $footprint = $this->calculate_folded_footprint($products[$footprint_winner]['specs']);
            $advantages[$footprint_winner][] = [
                'text'       => 'Smallest folded footprint',
                'comparison' => $this->format_folded_footprint($footprint),
                'spec_key'   => 'folded_footprint',
                'tooltip'    => SpecConfig::get_tooltip('folded_footprint', 'comparison'),
            ];
        }
    }

    /**
     * Find winner based on category score.
     *
     * Returns null if there's a tie (multiple products with same best score).
     *
     * @param array  $products  Products array.
     * @param string $score_key Score key (e.g., 'maintenance').
     * @return int|null Winner index or null if no clear winner/tie.
     */
    private function find_category_score_winner(array $products, string $score_key): ?int {
        $best_score = 0;
        $best_indices = [];

        foreach ($products as $idx => $product) {
            $score = $product['specs']['scores'][$score_key] ?? 0;

            if ($score > $best_score) {
                $best_score = $score;
                $best_indices = [$idx];
            } elseif ($score === $best_score && $score >= 50) {
                $best_indices[] = $idx;
            }
        }

        // Need meaningful score to be notable.
        if ($best_score < 50) {
            return null;
        }

        // Return null if tie (multiple products with same best score).
        return count($best_indices) === 1 ? $best_indices[0] : null;
    }

    /**
     * Get reasons why a product has good maintenance.
     *
     * @param array $specs Product specs.
     * @return array Reason strings.
     */
    private function get_maintenance_reasons(array $specs): array {
        $reasons = [];

        // Tire type.
        $tire_type = $this->get_nested_spec($specs, 'wheels.tire_type');
        if ($tire_type) {
            $tire_lower = strtolower($tire_type);
            if (strpos($tire_lower, 'solid') !== false) {
                $reasons[] = 'solid tires (no flats)';
            } elseif (strpos($tire_lower, 'tubeless') !== false) {
                $reasons[] = 'tubeless tires';
            }
        }

        // Self-healing.
        $self_healing = $this->get_nested_spec($specs, 'wheels.self_healing');
        if ($self_healing) {
            $reasons[] = 'self-healing tires';
        }

        // Split rim.
        $split_rim = $this->get_nested_spec($specs, 'wheels.split_rim');
        if ($split_rim) {
            $reasons[] = 'split rim design';
        }

        return $reasons;
    }

    /**
     * Find the product with smallest folded footprint.
     *
     * Returns null if there's a tie (multiple products with same smallest footprint).
     *
     * @param array $products Products array.
     * @return int|null Winner index or null if no clear winner/tie.
     */
    private function find_footprint_winner(array $products): ?int {
        $best_footprint = PHP_FLOAT_MAX;
        $best_indices = [];

        foreach ($products as $idx => $product) {
            $footprint = $this->calculate_folded_footprint($product['specs']);

            if ($footprint === null) {
                continue;
            }

            if ($footprint < $best_footprint) {
                $best_footprint = $footprint;
                $best_indices = [$idx];
            } elseif (abs($footprint - $best_footprint) < 0.001) {
                // Float comparison tolerance for tie detection.
                $best_indices[] = $idx;
            }
        }

        // No valid footprints found.
        if (empty($best_indices)) {
            return null;
        }

        // Return null if tie (multiple products with same smallest footprint).
        return count($best_indices) === 1 ? $best_indices[0] : null;
    }

    /**
     * Calculate advantages and weaknesses for single product display.
     *
     * Compares product against others in the same price bracket.
     * Falls back to category-wide percentile if no price or sparse bracket.
     *
     * @param array  $product Single product data array with specs and price_history.
     * @param string $geo     Geo region code for price-based bracketing.
     * @return array|null Analysis result with advantages, weaknesses, and context.
     */
    public function calculate_single(array $product, string $geo = 'US'): ?array {
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
            $bracket     = PriceBracketConfig::get_bracket( (float) $current_price );
            $use_bracket = true;
        } else {
            $fallback_info = [
                'reason'  => 'no_regional_price',
                'message' => "No {$geo} pricing available. Comparing against all electric scooters.",
            ];
        }

        // Fetch comparison set.
        $comparison_set = $this->get_single_comparison_set( $bracket, $geo, $use_bracket );

        // Check if bracket has enough products.
        if ( $use_bracket && count( $comparison_set ) < PriceBracketConfig::MIN_BRACKET_SIZE ) {
            $use_bracket   = false;
            $fallback_info = [
                'reason'  => 'insufficient_bracket_size',
                'message' => "Only " . count( $comparison_set ) . " products in bracket. Comparing against all electric scooters.",
            ];
            // Re-fetch without bracket filter.
            $comparison_set = $this->get_single_comparison_set( null, $geo, false );
        }

        // DEBUG: Log analysis context.
        $bracket_label = $bracket ? $bracket['label'] : 'All';
        $product_names = array_map( fn( $p ) => $p['name'] ?? 'Unknown', $comparison_set );
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( sprintf(
            "[Analysis] Starting analysis for %s | Price: $%s | Bracket: %s ($%d-$%d) | Mode: %s | Comparison set (%d): %s",
            $product['name'] ?? 'Unknown',
            $current_price ?? 'N/A',
            $bracket_label,
            $bracket['min'] ?? 0,
            $bracket['max'] ?? 0,
            $use_bracket ? 'bracket' : 'category',
            count( $comparison_set ),
            implode( ', ', $product_names )
        ) );

        // Define specs to analyze.
        $analysis_specs = $this->get_single_analysis_specs();

        $advantages  = [];
        $weaknesses  = [];

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

        // Sort by strength of advantage/weakness (percentile distance from threshold).
        usort( $advantages, fn( $a, $b ) => $b['percentile'] <=> $a['percentile'] );
        usort( $weaknesses, fn( $a, $b ) => $a['percentile'] <=> $b['percentile'] );

        // Calculate bracket average scores for radar chart.
        $bracket_scores = $this->calculate_bracket_average_scores( $comparison_set );

        return [
            'advantages'       => $advantages,
            'weaknesses'       => $weaknesses,
            'comparison_mode'  => $use_bracket ? 'bracket' : 'category',
            'bracket'          => $use_bracket ? $bracket : null,
            'products_in_set'  => count( $comparison_set ),
            'bracket_scores'   => $bracket_scores,
            'fallback'         => $fallback_info,
        ];
    }

    /**
     * Calculate average scores across a comparison set for radar chart.
     *
     * @param array $comparison_set Products in the comparison set.
     * @return array Associative array of score_key => average_value.
     */
    private function calculate_bracket_average_scores( array $comparison_set ): array {
        $score_keys = [
            'motor_performance',
            'range_battery',
            'ride_quality',
            'portability',
            'safety',
            'features',
            'maintenance',
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

    /**
     * Get comparison set for single product analysis.
     *
     * @param array|null $bracket     Bracket config or null for category-wide.
     * @param string     $geo         Geo region code.
     * @param bool       $use_bracket Whether to filter by bracket.
     * @return array Array of product data arrays.
     */
    private function get_single_comparison_set( ?array $bracket, string $geo, bool $use_bracket ): array {
        // Get all escooters from cache.
        $cache = new \ERH\Database\ProductCache();
        $all_products = $cache->get_all( 'Electric Scooter' );

        if ( ! $use_bracket ) {
            // Category-wide: return all products (no price filter for fallback).
            return $all_products;
        }

        // Filter to products in same bracket with geo pricing.
        $filtered = [];
        foreach ( $all_products as $prod ) {
            $price_history = $prod['price_history'] ?? [];
            $geo_pricing   = $price_history[ $geo ] ?? null;
            $price         = $geo_pricing['current_price'] ?? null;

            if ( ! $price || $price <= 0 ) {
                continue;
            }

            // Check if in same bracket.
            if ( $price >= $bracket['min'] && $price < $bracket['max'] ) {
                $filtered[] = $prod;
            }
        }

        return $filtered;
    }

    /**
     * Get specs to analyze for single product analysis.
     *
     * @return array Array of spec definitions.
     */
    private function get_single_analysis_specs(): array {
        // Tooltips auto-fetched from centralized SpecConfig::TOOLTIPS via get_tooltip().
        return [
            // Value metrics (from value_metrics - lower is better).
            [
                'key'           => 'value_metrics.price_per_wh',
                'label'         => 'Battery Value',
                'unit'          => '$/Wh',
                'higher_better' => false,
                'is_value'      => true,
                'tooltip'       => SpecConfig::get_tooltip('value_metrics.price_per_wh', 'comparison'),
            ],
            [
                'key'           => 'value_metrics.price_per_watt',
                'label'         => 'Motor Value',
                'unit'          => '$/W',
                'higher_better' => false,
                'is_value'      => true,
                'tooltip'       => SpecConfig::get_tooltip('value_metrics.price_per_watt', 'comparison'),
            ],
            [
                'key'           => 'value_metrics.price_per_tested_mile',
                'label'         => 'Range Value',
                'unit'          => '$/mi',
                'higher_better' => false,
                'is_value'      => true,
                'tooltip'       => SpecConfig::get_tooltip('value_metrics.price_per_tested_mile', 'comparison'),
            ],
            [
                'key'           => 'value_metrics.price_per_mph',
                'label'         => 'Speed Value',
                'unit'          => '$/mph',
                'higher_better' => false,
                'is_value'      => true,
                'tooltip'       => SpecConfig::get_tooltip('value_metrics.price_per_mph', 'comparison'),
            ],
            // Raw performance specs (higher is better unless noted).
            [
                'key'           => 'tested_top_speed',
                'label'         => 'Top Speed',
                'unit'          => 'mph',
                'higher_better' => true,
                'tooltip'       => SpecConfig::get_tooltip('tested_top_speed', 'comparison'),
            ],
            [
                'key'           => 'tested_range_regular',
                'label'         => 'Tested Range',
                'unit'          => 'mi',
                'higher_better' => true,
                'tooltip'       => SpecConfig::get_tooltip('tested_range_regular', 'comparison'),
            ],
            [
                'key'           => 'battery.capacity',
                'label'         => 'Battery Capacity',
                'unit'          => 'Wh',
                'higher_better' => true,
                'tooltip'       => SpecConfig::get_tooltip('battery.capacity', 'comparison'),
            ],
            [
                'key'           => 'motor.power_nominal',
                'label'         => 'Motor Power',
                'unit'          => 'W',
                'higher_better' => true,
                'tooltip'       => SpecConfig::get_tooltip('motor.power_nominal', 'comparison'),
            ],
            [
                'key'           => 'dimensions.weight',
                'label'         => 'Weight',
                'unit'          => 'lbs',
                'higher_better' => false,
                'tooltip'       => SpecConfig::get_tooltip('dimensions.weight', 'comparison'),
            ],
            [
                'key'           => 'dimensions.max_load',
                'label'         => 'Max Load',
                'unit'          => 'lbs',
                'higher_better' => true,
                'tooltip'       => SpecConfig::get_tooltip('dimensions.max_load', 'comparison'),
            ],
            // Efficiency metrics (from pre-computed values).
            [
                'key'           => 'wh_per_lb',
                'label'         => 'Energy Density',
                'unit'          => 'Wh/lb',
                'higher_better' => true,
                'tooltip'       => SpecConfig::get_tooltip('wh_per_lb', 'comparison'),
            ],
            [
                'key'           => 'speed_per_lb',
                'label'         => 'Speed-to-weight ratio',
                'unit'          => 'mph/lb',
                'higher_better' => true,
                'tooltip'       => SpecConfig::get_tooltip('speed_per_lb', 'comparison'),
            ],
            [
                'key'           => 'tested_range_per_lb',
                'label'         => 'Range Efficiency',
                'unit'          => 'mi/lb',
                'higher_better' => true,
                'tooltip'       => SpecConfig::get_tooltip('tested_range_per_lb', 'comparison'),
            ],
            // Score-based composite specs (compare category scores vs bracket average).
            [
                'key'           => 'ride_quality',
                'label'         => 'Ride Quality',
                'higher_better' => true,
                'is_score_based' => true,
                'tooltip'       => SpecConfig::get_tooltip('ride_quality', 'comparison'),
            ],
            [
                'key'           => 'maintenance',
                'label'         => 'Maintenance',
                'higher_better' => true,
                'is_score_based' => true,
                'tooltip'       => SpecConfig::get_tooltip('maintenance', 'comparison'),
            ],
            // Absolute quality specs (not bracket-relative).
            [
                'key'           => 'ip_rating',
                'label'         => 'Water Resistance',
                'higher_better' => true,
                'is_descriptive' => true,
                'tooltip'       => SpecConfig::get_tooltip('other.ip_rating', 'comparison'),
            ],
            [
                'key'           => 'feature_count',
                'label'         => 'Features',
                'unit'          => 'features',
                'higher_better' => true,
                'tooltip'       => SpecConfig::get_tooltip('features', 'comparison'),
            ],
        ];
    }

    /**
     * Analyze a single spec against the comparison set.
     *
     * @param array  $product        Target product.
     * @param array  $comparison_set Products to compare against.
     * @param array  $spec_def       Spec definition.
     * @param string $geo            Geo region code.
     * @return array|null Result with is_advantage, is_weakness, and item data.
     */
    private function analyze_single_spec( array $product, array $comparison_set, array $spec_def, string $geo ): ?array {
        $key           = $spec_def['key'];
        $higher_better = $spec_def['higher_better'] ?? true;
        $is_value      = $spec_def['is_value'] ?? false;

        // Handle score-based composite specs (ride_quality, maintenance).
        if ( ! empty( $spec_def['is_score_based'] ) ) {
            return $this->analyze_score_based_spec( $product, $comparison_set, $spec_def );
        }

        // Handle descriptive specs (IP rating) differently.
        if ( ! empty( $spec_def['is_descriptive'] ) ) {
            return $this->analyze_descriptive_spec( $product, $comparison_set, $spec_def, $geo );
        }

        // Get product value.
        $product_value = $this->get_single_spec_value( $product, $key, $geo );

        if ( $product_value === null || $product_value === '' || ( is_numeric( $product_value ) && $product_value <= 0 ) ) {
            // DEBUG: Log skipped spec due to null/zero product value.
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf(
                '[Analysis] SKIP %s: product=%s has no value (got: %s)',
                $spec_def['label'],
                $product['name'] ?? 'Unknown',
                var_export( $product_value, true )
            ) );
            return null;
        }

        // Collect values from comparison set (with product names for logging).
        $values          = [];
        $values_with_names = [];
        foreach ( $comparison_set as $comp_product ) {
            $val = $this->get_single_spec_value( $comp_product, $key, $geo );
            if ( $val !== null && $val !== '' && ( ! is_numeric( $val ) || $val > 0 ) ) {
                $values[]            = (float) $val;
                $values_with_names[] = [
                    'name'  => $comp_product['name'] ?? 'Unknown',
                    'value' => $val,
                ];
            }
        }

        if ( count( $values ) < 3 ) {
            // DEBUG: Log skipped spec due to insufficient data.
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf(
                '[Analysis] SKIP %s: only %d products have data (need 3+)',
                $spec_def['label'],
                count( $values )
            ) );
            return null;
        }

        // Calculate stats.
        $avg         = array_sum( $values ) / count( $values );
        $min         = min( $values );
        $max         = max( $values );
        $total_count = count( $values );

        // Skip if all values are equal (no variance to compare).
        if ( $min === $max ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf(
                '[Analysis] SKIP %s: all values are equal (%.3f)',
                $spec_def['label'],
                $min
            ) );
            return null;
        }

        // Calculate percentile.
        $percentile = $this->calculate_percentile( (float) $product_value, $values, $higher_better );

        // Calculate rank (1 = best).
        $rank = $this->calculate_rank( (float) $product_value, $values, $higher_better );

        // Calculate percentage vs average.
        $pct_vs_avg = $avg > 0 ? ( ( $product_value - $avg ) / $avg ) * 100 : 0;

        // Determine if advantage or weakness.
        $is_advantage = PriceBracketConfig::is_advantage( $percentile, $pct_vs_avg, $higher_better );
        $is_weakness  = PriceBracketConfig::is_weakness( $percentile, $pct_vs_avg, $higher_better );

        // Sanity check: product with best value can't be a weakness (handles percentile ties).
        // If higher_better and value = max, not a weakness.
        // If !higher_better and value = min, not a weakness.
        if ( $is_weakness ) {
            if ( $higher_better && (float) $product_value >= $max ) {
                $is_weakness = false;
            } elseif ( ! $higher_better && (float) $product_value <= $min ) {
                $is_weakness = false;
            }
        }

        // DEBUG: Log detailed analysis.
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( sprintf(
            "[Analysis] %s: product=%s (%.3f), avg=%.3f, min=%.3f, max=%.3f, percentile=%.1f, pct_vs_avg=%.1f%%, higher_better=%s, is_adv=%s, is_weak=%s",
            $spec_def['label'],
            $product['name'] ?? 'Unknown',
            $product_value,
            $avg,
            $min,
            $max,
            $percentile,
            $pct_vs_avg,
            $higher_better ? 'Y' : 'N',
            $is_advantage ? 'Y' : 'N',
            $is_weakness ? 'Y' : 'N'
        ) );

        // Log all bracket values for this spec.
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( sprintf(
            "[Analysis] %s bracket values: %s",
            $spec_def['label'],
            implode( ', ', array_map( function( $v ) {
                return sprintf( '%s=%.3f', $v['name'], $v['value'] );
            }, $values_with_names ) )
        ) );

        // Skip if neither.
        if ( ! $is_advantage && ! $is_weakness ) {
            return null;
        }

        // Format values for display.
        $formatted_value = $this->format_analysis_value( $product_value, $spec_def );
        $formatted_avg   = $this->format_analysis_value( $avg, $spec_def );

        // Format the result.
        $item = [
            'spec_key'      => $key,
            'label'         => $spec_def['label'],
            'product_value' => $formatted_value,
            'bracket_avg'   => $formatted_avg,
            'unit'          => $spec_def['unit'],
            'percentile'    => round( $percentile, 1 ),
            'pct_vs_avg'    => round( $pct_vs_avg, 1 ),
            'tooltip'       => $spec_def['tooltip'] ?? null,
            'text'          => $this->format_single_text( $spec_def, $percentile, $pct_vs_avg, $is_advantage, $higher_better, $rank, $total_count ),
            'comparison'    => $this->format_comparison_text( $formatted_value, $formatted_avg, $spec_def ),
        ];

        return [
            'is_advantage' => $is_advantage,
            'is_weakness'  => $is_weakness,
            'item'         => $item,
        ];
    }

    /**
     * Analyze a score-based composite spec (ride_quality, maintenance).
     *
     * Compares product's category score vs bracket average.
     * Thresholds: 20+ points above avg = Excellent, 14+ = Great, 8+ = Above-average.
     *
     * @param array $product        Target product.
     * @param array $comparison_set Products to compare against.
     * @param array $spec_def       Spec definition.
     * @return array|null Result with is_advantage, is_weakness, and item data.
     */
    private function analyze_score_based_spec( array $product, array $comparison_set, array $spec_def ): ?array {
        $key   = $spec_def['key'];
        $label = $spec_def['label'];

        // Get product's category score.
        $product_score = $product['specs']['scores'][ $key ] ?? null;

        if ( $product_score === null ) {
            return null;
        }

        // Collect scores from comparison set.
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

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( sprintf(
            '[Analysis] %s Score: product=%d, avg=%.1f, diff=%.1f',
            $label,
            $product_score,
            $avg,
            $diff
        ) );

        // Thresholds: 20+ = Excellent, 14+ = Great, 8+ = Above-average.
        // Negative: -20 or worse = Very poor, -14 = Poor, -8 = Below-average.
        $is_advantage = $diff >= 8;
        $is_weakness  = $diff <= -8;

        if ( ! $is_advantage && ! $is_weakness ) {
            return null;
        }

        // Get quality tier and details.
        if ( $is_advantage ) {
            $tier = $this->get_score_tier_label( $key, $diff, true );
        } else {
            $tier = $this->get_score_tier_label( $key, $diff, false );
        }

        // Get supporting details based on spec type.
        $details = '';
        if ( $key === 'ride_quality' ) {
            $details = $this->get_ride_quality_details( $product['specs'] ?? [], $is_advantage );
        } elseif ( $key === 'maintenance' ) {
            $details = $this->get_maintenance_details( $product['specs'] ?? [], $is_advantage );
        }

        // Capitalize first letter of details.
        if ( $details ) {
            $details = ucfirst( $details );
        }

        // Format display text.
        $display_text = $tier;

        // Build the item.
        $item = [
            'spec_key'      => $key,
            'label'         => $label,
            'product_value' => $product_score,
            'bracket_avg'   => round( $avg ),
            'unit'          => 'score',
            'percentile'    => 0, // Not used for score-based.
            'pct_vs_avg'    => round( $diff ),
            'tooltip'       => $spec_def['tooltip'] ?? null,
            'text'          => $display_text,
            'comparison'    => $details, // Details shown as comparison text.
        ];

        return [
            'is_advantage' => $is_advantage,
            'is_weakness'  => $is_weakness,
            'item'         => $item,
        ];
    }

    /**
     * Get tier label based on score difference.
     *
     * @param string $key         Spec key (ride_quality, maintenance).
     * @param float  $diff        Score difference from average.
     * @param bool   $is_positive Whether the difference is positive (advantage).
     * @return string Tier label.
     */
    private function get_score_tier_label( string $key, float $diff, bool $is_positive ): string {
        $abs_diff = abs( $diff );

        // Maintenance uses different labels (about low maintenance, not high quality).
        if ( $key === 'maintenance' ) {
            if ( $is_positive ) {
                if ( $abs_diff >= 20 ) {
                    return 'Very low maintenance';
                } elseif ( $abs_diff >= 14 ) {
                    return 'Low maintenance';
                } else {
                    return 'Easy maintenance';
                }
            } else {
                if ( $abs_diff >= 20 ) {
                    return 'High maintenance';
                } elseif ( $abs_diff >= 14 ) {
                    return 'Higher maintenance';
                } else {
                    return 'More maintenance needed';
                }
            }
        }

        // Ride quality and other scores.
        if ( $is_positive ) {
            if ( $abs_diff >= 20 ) {
                return 'Excellent ride quality';
            } elseif ( $abs_diff >= 14 ) {
                return 'Great ride quality';
            } else {
                return 'Above-average ride quality';
            }
        } else {
            if ( $abs_diff >= 20 ) {
                return 'Very poor ride quality';
            } elseif ( $abs_diff >= 14 ) {
                return 'Poor ride quality';
            } else {
                return 'Below-average ride quality';
            }
        }
    }

    /**
     * Get ride quality supporting details.
     *
     * For strengths: show positive factors (suspension, pneumatic/tubeless tires, large tires).
     * For weaknesses: show negative factors (no suspension, solid tires, small tires).
     *
     * @param array $specs        Product specs.
     * @param bool  $is_advantage Whether this is a strength.
     * @return string Supporting details text.
     */
    private function get_ride_quality_details( array $specs, bool $is_advantage ): string {
        $details = [];

        // Suspension type.
        $suspension = $this->get_typed_spec_value( $specs, 'suspension.type' );

        $has_suspension = false;
        if ( ! empty( $suspension ) && is_array( $suspension ) ) {
            $types = array_filter( $suspension, function( $s ) {
                return $s && strtolower( (string) $s ) !== 'none';
            });

            if ( count( $types ) >= 2 ) {
                $has_suspension = true;
                if ( $is_advantage ) {
                    $has_hydraulic = false;
                    foreach ( $types as $type ) {
                        if ( strpos( strtolower( (string) $type ), 'hydraulic' ) !== false ) {
                            $has_hydraulic = true;
                            break;
                        }
                    }
                    $details[] = $has_hydraulic ? 'dual hydraulic suspension' : 'dual suspension';
                }
            } elseif ( count( $types ) === 1 ) {
                $has_suspension = true;
                if ( $is_advantage ) {
                    $type = reset( $types );
                    if ( strpos( strtolower( (string) $type ), 'hydraulic' ) !== false ) {
                        $details[] = 'hydraulic suspension';
                    } elseif ( strpos( strtolower( (string) $type ), 'spring' ) !== false ) {
                        $details[] = 'spring suspension';
                    }
                }
            }
        }

        // For weakness: note no suspension.
        if ( ! $is_advantage && ! $has_suspension ) {
            $details[] = 'no suspension';
        }

        // Tire type.
        $tire_type = $this->get_typed_spec_value( $specs, 'wheels.tire_type' );

        if ( $tire_type ) {
            $tire_lower = strtolower( (string) $tire_type );
            if ( $is_advantage ) {
                // For strengths: show good tire types.
                if ( strpos( $tire_lower, 'tubeless' ) !== false ) {
                    $details[] = 'tubeless tires';
                } elseif ( strpos( $tire_lower, 'pneumatic' ) !== false ) {
                    $details[] = 'pneumatic tires';
                }
            } else {
                // For weaknesses: show solid/honeycomb tires (harsh ride).
                if ( strpos( $tire_lower, 'solid' ) !== false || strpos( $tire_lower, 'honeycomb' ) !== false ) {
                    $details[] = 'solid tires';
                }
            }
        }

        // Tire size - only show for strengths if large, or weaknesses if small.
        $tire_front = $this->get_typed_spec_value( $specs, 'wheels.tire_size_front' );
        $tire_rear  = $this->get_typed_spec_value( $specs, 'wheels.tire_size_rear' );

        $avg_size = 0;
        $count    = 0;
        if ( is_numeric( $tire_front ) ) {
            $avg_size += (float) $tire_front;
            $count++;
        }
        if ( is_numeric( $tire_rear ) ) {
            $avg_size += (float) $tire_rear;
            $count++;
        }
        if ( $count > 0 ) {
            $avg_size = $avg_size / $count;
            if ( $is_advantage && $avg_size >= 10 ) {
                $details[] = round( $avg_size ) . '" tires';
            } elseif ( ! $is_advantage && $avg_size < 8 ) {
                $details[] = 'small ' . round( $avg_size ) . '" tires';
            }
        }

        return implode( ', ', $details );
    }

    /**
     * Get maintenance supporting details.
     *
     * For strengths (low maintenance): show factors that reduce maintenance (solid tires, drum brakes, self-healing).
     * For weaknesses (high maintenance): show factors that increase maintenance (tubed tires, hydraulic brakes).
     *
     * @param array $specs        Product specs.
     * @param bool  $is_advantage Whether this is a strength (low maintenance).
     * @return string Supporting details text.
     */
    private function get_maintenance_details( array $specs, bool $is_advantage ): string {
        $details = [];

        // Tire type.
        $tire_type = $this->get_typed_spec_value( $specs, 'wheels.tire_type' );

        $is_pneumatic   = false;
        $is_solid       = false;
        $is_tubeless    = false;

        if ( $tire_type ) {
            $tire_lower   = strtolower( (string) $tire_type );
            $is_solid     = strpos( $tire_lower, 'solid' ) !== false || strpos( $tire_lower, 'honeycomb' ) !== false;
            $is_tubeless  = strpos( $tire_lower, 'tubeless' ) !== false;
            // Tubeless implies pneumatic; also check for "pneumatic" or "air" explicitly.
            $is_pneumatic = $is_tubeless || strpos( $tire_lower, 'pneumatic' ) !== false || strpos( $tire_lower, 'air' ) !== false;

            if ( $is_advantage ) {
                // For low maintenance strength: show solid/tubeless.
                if ( $is_solid ) {
                    $details[] = 'solid tires (no flats)';
                } elseif ( $is_tubeless ) {
                    $details[] = 'tubeless tires';
                }
            } else {
                // For high maintenance weakness: show tire type.
                if ( $is_pneumatic ) {
                    if ( $is_tubeless ) {
                        $details[] = 'tubeless tires';
                    } else {
                        $details[] = 'tubed air tires';
                    }
                }
            }
        }

        // Self-healing tires.
        $self_healing = $this->get_typed_spec_value( $specs, 'wheels.self_healing' );

        $has_self_healing = $self_healing === true || $self_healing === 'true' || $self_healing === '1' || $self_healing === 1;

        if ( $is_advantage && $has_self_healing ) {
            $details[] = 'self-healing tires';
        } elseif ( ! $is_advantage && ! $is_solid && ! $has_self_healing ) {
            // Show "not self-healing" for any pneumatic/tubeless that isn't solid and doesn't have self-healing.
            $details[] = 'not self-healing';
        }

        // Brake type.
        $front_brake = $this->get_typed_spec_value( $specs, 'brakes.front' );
        $rear_brake  = $this->get_typed_spec_value( $specs, 'brakes.rear' );

        $brakes        = [ $front_brake, $rear_brake ];
        $has_drum      = false;
        $has_disc      = false;
        $has_hydraulic = false;

        foreach ( $brakes as $brake ) {
            if ( $brake ) {
                $brake_lower = strtolower( (string) $brake );
                if ( strpos( $brake_lower, 'drum' ) !== false ) {
                    $has_drum = true;
                }
                if ( strpos( $brake_lower, 'disc' ) !== false ) {
                    $has_disc = true;
                }
                if ( strpos( $brake_lower, 'hydraulic' ) !== false ) {
                    $has_hydraulic = true;
                }
            }
        }

        if ( $is_advantage ) {
            if ( $has_drum ) {
                $details[] = 'drum brakes';
            }
        } else {
            // Disc brakes need pad replacement; hydraulic need bleeding.
            if ( $has_hydraulic ) {
                $details[] = 'hydraulic disc brakes';
            } elseif ( $has_disc ) {
                $details[] = 'disc brakes';
            }
        }

        // IP rating for water resistance (only for strengths).
        if ( $is_advantage ) {
            $ip_rating = $this->get_typed_spec_value( $specs, 'other.ip_rating' );

            if ( $ip_rating ) {
                $water = $this->get_ip_water_rating( $ip_rating );
                if ( $water >= 6 ) {
                    $details[] = 'high water resistance';
                }
            }
        }

        return implode( ', ', $details );
    }

    /**
     * Analyze a descriptive spec (IP rating).
     *
     * These specs use absolute quality-based labels, not bracket comparison.
     *
     * @param array  $product        Target product.
     * @param array  $comparison_set Products to compare against.
     * @param array  $spec_def       Spec definition.
     * @param string $geo            Geo region code.
     * @return array|null Result with is_advantage, is_weakness, and item data.
     */
    private function analyze_descriptive_spec( array $product, array $comparison_set, array $spec_def, string $geo ): ?array {
        $key = $spec_def['key'];

        if ( $key === 'ip_rating' ) {
            return $this->analyze_ip_rating( $product, $comparison_set, $spec_def );
        }

        return null;
    }

    /**
     * Analyze suspension as a descriptive spec.
     *
     * Suspension quality levels:
     * - Dual hydraulic = excellent
     * - Dual spring/fork = great
     * - Single hydraulic = good
     * - Single spring/fork = decent
     * - None = potential weakness
     *
     * @param array $product        Target product.
     * @param array $comparison_set Products to compare against.
     * @param array $spec_def       Spec definition.
     * @return array|null Result with is_advantage, is_weakness, and item data.
     */
    private function analyze_suspension( array $product, array $comparison_set, array $spec_def ): ?array {
        $specs = $product['specs'] ?? [];

        // Get suspension type array.
        $suspension = $this->get_typed_spec_value( $specs, 'suspension.type' );

        // Calculate product's suspension score.
        $score = $this->calculate_suspension_score( $specs );

        // Collect scores from comparison set.
        $scores = [];
        foreach ( $comparison_set as $comp_product ) {
            $comp_score = $this->calculate_suspension_score( $comp_product['specs'] ?? [] );
            $scores[]   = $comp_score;
        }

        if ( count( $scores ) < 3 ) {
            return null;
        }

        $avg = array_sum( $scores ) / count( $scores );
        $max = max( $scores );
        $min = min( $scores );

        // Skip if no variance.
        if ( $min === $max ) {
            return null;
        }

        // Calculate percentile and rank.
        $percentile = $this->calculate_percentile( (float) $score, $scores, true );
        $rank       = $this->calculate_rank( (float) $score, $scores, true );

        // Format the suspension display text.
        $display_text = $this->format_suspension_quality( $suspension, $score );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( sprintf(
            '[Analysis] Suspension: score=%d, avg=%.1f, percentile=%.1f, rank=%d, display=%s',
            $score,
            $avg,
            $percentile,
            $rank,
            $display_text
        ) );

        // Determine if advantage or weakness based on score thresholds.
        // Score 6+ (dual with decent type) = potential strength if above average.
        // Score 0 (no suspension) = potential weakness only if others in bracket have suspension.
        $is_advantage = $score >= 6 && $percentile >= 70;
        $is_weakness  = $score === 0 && $avg >= 3; // Only a weakness if bracket avg suggests others have suspension

        if ( ! $is_advantage && ! $is_weakness ) {
            return null;
        }

        // Build the item.
        $item = [
            'spec_key'      => 'suspension',
            'label'         => 'Suspension',
            'product_value' => $display_text,
            'bracket_avg'   => $this->format_bracket_suspension_avg( $avg ),
            'unit'          => '',
            'percentile'    => round( $percentile, 1 ),
            'pct_vs_avg'    => 0, // Not applicable for descriptive.
            'tooltip'       => $spec_def['tooltip'] ?? null,
            'text'          => $is_advantage ? $display_text : 'No suspension',
        ];

        return [
            'is_advantage' => $is_advantage,
            'is_weakness'  => $is_weakness,
            'item'         => $item,
        ];
    }

    /**
     * Format suspension quality for display.
     *
     * @param array|null $suspension Suspension type array.
     * @param int        $score      Calculated score.
     * @return string Display text.
     */
    private function format_suspension_quality( $suspension, int $score ): string {
        if ( ! $suspension || ! is_array( $suspension ) || $score === 0 ) {
            return 'No suspension';
        }

        // Filter out "None" entries.
        $types = array_filter( $suspension, function( $s ) {
            return $s && strtolower( (string) $s ) !== 'none';
        });

        if ( empty( $types ) ) {
            return 'No suspension';
        }

        // Check for dual suspension.
        $has_dual  = false;
        $has_front = false;
        $has_rear  = false;
        $best_type = '';

        foreach ( $types as $type ) {
            $type_lower = strtolower( (string) $type );

            if ( strpos( $type_lower, 'dual' ) !== false ) {
                $has_dual = true;
            }
            if ( strpos( $type_lower, 'front' ) !== false ) {
                $has_front = true;
            }
            if ( strpos( $type_lower, 'rear' ) !== false ) {
                $has_rear = true;
            }

            // Track best type.
            if ( strpos( $type_lower, 'hydraulic' ) !== false ) {
                $best_type = 'hydraulic';
            } elseif ( $best_type !== 'hydraulic' && strpos( $type_lower, 'spring' ) !== false ) {
                $best_type = 'spring';
            } elseif ( $best_type !== 'hydraulic' && $best_type !== 'spring' && strpos( $type_lower, 'fork' ) !== false ) {
                $best_type = 'fork';
            }
        }

        // Build description.
        $is_dual = $has_dual || ( $has_front && $has_rear );

        if ( $is_dual ) {
            if ( $best_type === 'hydraulic' ) {
                return 'Dual hydraulic suspension';
            } elseif ( $best_type === 'spring' ) {
                return 'Dual spring suspension';
            } else {
                return 'Dual suspension';
            }
        } else {
            if ( $best_type === 'hydraulic' ) {
                return 'Hydraulic suspension';
            } elseif ( $best_type === 'spring' ) {
                return 'Spring suspension';
            } elseif ( $best_type === 'fork' ) {
                return 'Fork suspension';
            } else {
                return 'Basic suspension';
            }
        }
    }

    /**
     * Format bracket average suspension for comparison display.
     *
     * @param float $avg Average score.
     * @return string Display text.
     */
    private function format_bracket_suspension_avg( float $avg ): string {
        if ( $avg >= 6 ) {
            return 'most have dual';
        } elseif ( $avg >= 3 ) {
            return 'most have single';
        } else {
            return 'most have none';
        }
    }

    /**
     * Analyze IP rating as an absolute quality spec (not bracket-relative).
     *
     * IP rating quality levels (absolute, not bracket-dependent):
     * - IP*7+ = Excellent water resistance (strength)
     * - IP*6 = Great water resistance (strength)
     * - IP*5 = Good water resistance (strength)
     * - IP*4 = Basic splash resistance (neutral, skip)
     * - IP*3 or lower = Poor water resistance (weakness)
     * - None/Unknown = Not tested (weakness)
     *
     * @param array $product        Target product.
     * @param array $comparison_set Products to compare against (unused - absolute analysis).
     * @param array $spec_def       Spec definition.
     * @return array|null Result with is_advantage, is_weakness, and item data.
     */
    private function analyze_ip_rating( array $product, array $comparison_set, array $spec_def ): ?array {
        $specs = $product['specs'] ?? [];

        // Get IP rating.
        $ip_rating = $this->get_typed_spec_value( $specs, 'other.ip_rating' );

        // Get the water rating digit.
        $water_rating = $this->get_ip_water_rating( $ip_rating );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( sprintf(
            '[Analysis] IP Rating (absolute): value=%s, water=%d',
            $ip_rating ?? 'null',
            $water_rating
        ) );

        // Determine quality based on absolute water rating.
        // IP5+ = strength, IP3 or lower/none = weakness, IP4 = neutral (skip).
        $is_advantage = $water_rating >= 5;
        $is_weakness  = empty( $ip_rating ) || $water_rating <= 3;

        // IP4 is neutral - don't show.
        if ( ! $is_advantage && ! $is_weakness ) {
            return null;
        }

        // Get quality label.
        $quality_label = $this->get_ip_quality_label( $water_rating );

        // Format display text with IP rating in parentheses.
        if ( $is_advantage ) {
            $display_text = $quality_label . ' (' . strtoupper( (string) $ip_rating ) . ')';
        } elseif ( empty( $ip_rating ) ) {
            $display_text = 'No water resistance rating';
        } else {
            $display_text = 'Limited water resistance (' . strtoupper( (string) $ip_rating ) . ')';
        }

        // Get comparison text explaining what the IP rating means.
        $comparison = $this->get_ip_rating_details( $water_rating, $is_advantage );

        // Build the item.
        $item = [
            'spec_key'      => 'ip_rating',
            'label'         => 'Water Resistance',
            'product_value' => '', // Not used for absolute analysis.
            'bracket_avg'   => '', // No bracket comparison for absolute analysis.
            'unit'          => '',
            'percentile'    => 0, // Not applicable for absolute.
            'pct_vs_avg'    => 0,
            'tooltip'       => SpecConfig::get_tooltip('other.ip_rating', 'comparison'),
            'text'          => $display_text,
            'comparison'    => $comparison,
        ];

        return [
            'is_advantage' => $is_advantage,
            'is_weakness'  => $is_weakness,
            'item'         => $item,
        ];
    }

    /**
     * Get the water rating digit from an IP rating string.
     *
     * @param string|null $ip_rating IP rating (e.g., "IP54", "IPX5").
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
     * Get quality label for IP water rating.
     *
     * @param int $water_rating Water rating (0-9).
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
     * Get details explaining what an IP water rating means.
     *
     * @param int  $water_rating Water rating (0-9).
     * @param bool $is_advantage Whether this is a strength.
     * @return string Details text.
     */
    private function get_ip_rating_details( int $water_rating, bool $is_advantage ): string {
        if ( $is_advantage ) {
            // Strengths: explain what protection you get.
            if ( $water_rating >= 7 ) {
                return 'Safe for heavy rain and puddles';
            } elseif ( $water_rating >= 6 ) {
                return 'Safe for rain and wet conditions';
            } else {
                return 'Safe for light rain';
            }
        } else {
            // Weaknesses: explain the limitation.
            if ( $water_rating === 0 ) {
                return 'Avoid wet conditions';
            } else {
                return 'Avoid riding in rain';
            }
        }
    }

    /**
     * Format bracket average IP rating for comparison display.
     *
     * @param float $avg Average score.
     * @return string Display text.
     */
    private function format_bracket_ip_avg( float $avg ): string {
        if ( $avg >= 6 ) {
            return 'most have IP6+';
        } elseif ( $avg >= 5 ) {
            return 'most have IP5';
        } elseif ( $avg >= 4 ) {
            return 'most have IP4';
        } else {
            return 'most have none';
        }
    }

    /**
     * Get spec value for single product analysis.
     *
     * Handles value_metrics (geo-specific) and regular specs.
     *
     * @param array  $product Product data.
     * @param string $key     Spec key.
     * @param string $geo     Geo region code.
     * @return mixed Spec value or null.
     */
    private function get_single_spec_value( array $product, string $key, string $geo ) {
        $specs = $product['specs'] ?? [];

        // Handle value_metrics (geo-specific).
        // Key format: 'value_metrics.price_per_wh' → lookup at specs['value_metrics'][$geo]['price_per_wh']
        if ( strpos( $key, 'value_metrics.' ) === 0 ) {
            $metric_key    = str_replace( 'value_metrics.', '', $key );
            $value_metrics = $specs['value_metrics'][ $geo ] ?? [];
            return $value_metrics[ $metric_key ] ?? null;
        }

        // Handle special calculated fields.
        if ( $key === 'suspension_score' ) {
            return $this->calculate_suspension_score( $specs );
        }

        if ( $key === 'ip_score' ) {
            return $this->calculate_ip_score( $specs );
        }

        if ( $key === 'feature_count' ) {
            $features = $this->get_typed_spec_value( $specs, 'features' );
            return is_array( $features ) ? count( $features ) : 0;
        }

        // Use typed spec value helper (auto-prefixes with 'e-scooters.').
        return $this->get_typed_spec_value( $specs, $key );
    }

    /**
     * Calculate suspension score for comparison.
     *
     * @param array $specs Product specs.
     * @return int Score (0-10).
     */
    private function calculate_suspension_score( array $specs ): int {
        $suspension = $this->get_typed_spec_value( $specs, 'suspension.type' );

        if ( empty( $suspension ) || ! is_array( $suspension ) ) {
            return 0;
        }

        $score = 0;
        $type_scores = [
            'hydraulic' => 4,
            'spring'    => 3,
            'fork'      => 3,
            'rubber'    => 1,
        ];

        foreach ( $suspension as $susp ) {
            $susp_lower = strtolower( (string) $susp );
            foreach ( $type_scores as $type => $type_score ) {
                if ( strpos( $susp_lower, $type ) !== false ) {
                    $score += $type_score;
                    break;
                }
            }
        }

        // Bonus for dual suspension.
        if ( count( $suspension ) >= 2 ) {
            $score += 2;
        }

        return min( $score, 10 );
    }

    /**
     * Calculate IP rating score for comparison.
     *
     * @param array $specs Product specs.
     * @return int Score (0-10).
     */
    private function calculate_ip_score( array $specs ): int {
        $ip_rating = $this->get_typed_spec_value( $specs, 'other.ip_rating' );

        if ( empty( $ip_rating ) ) {
            return 0;
        }

        // Parse IP rating (e.g., IP54, IPX5).
        if ( preg_match( '/IP([X\d])(\d)/i', $ip_rating, $matches ) ) {
            $dust  = $matches[1] === 'X' ? 0 : (int) $matches[1];
            $water = (int) $matches[2];

            // Water rating is primary (0-8), dust is secondary bonus.
            return min( $water + ( $dust > 0 ? 1 : 0 ), 10 );
        }

        return 0;
    }

    /**
     * Calculate percentile rank for a value.
     *
     * @param float $value        The value to rank.
     * @param array $values       All values to compare against.
     * @param bool  $higher_better Whether higher values are better.
     * @return float Percentile (0-100).
     */
    private function calculate_percentile( float $value, array $values, bool $higher_better ): float {
        $count = count( $values );

        if ( $count === 0 ) {
            return 50.0;
        }

        // Count how many values this beats.
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

        // Percentile is the percentage of values beaten.
        return ( $beats / $count ) * 100;
    }

    /**
     * Calculate rank for a value (1 = best).
     *
     * @param float $value        The value to rank.
     * @param array $values       All values to compare against.
     * @param bool  $higher_better Whether higher values are better.
     * @return int Rank (1 = best).
     */
    private function calculate_rank( float $value, array $values, bool $higher_better ): int {
        $count = count( $values );

        if ( $count === 0 ) {
            return 1;
        }

        // Count how many values are better than this one.
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

        // Rank is 1 + number of items that are better.
        return $better_count + 1;
    }

    /**
     * Format a value for display in analysis.
     *
     * Precision rules:
     * - Value metrics ($/Wh, $/W, etc.): 2 decimals
     * - Wh/lb (energy density): 1 decimal
     * - mph/lb, mi/lb (efficiency): 2 decimals
     * - mph, mi (speed, range): 1 decimal
     * - Everything else: integer
     *
     * @param mixed $value    Value to format.
     * @param array $spec_def Spec definition.
     * @return mixed Formatted value.
     */
    private function format_analysis_value( $value, array $spec_def ) {
        if ( ! is_numeric( $value ) ) {
            return $value;
        }

        $unit = $spec_def['unit'] ?? '';
        $key  = $spec_def['key'] ?? '';

        // Value metrics ($/Wh, $/W, $/mi, $/mph) - 2 decimals.
        if ( ! empty( $spec_def['is_value'] ) ) {
            return round( (float) $value, 2 );
        }

        // Wh/lb (energy density) - 1 decimal.
        if ( $unit === 'Wh/lb' ) {
            return round( (float) $value, 1 );
        }

        // mph/lb, mi/lb (efficiency ratios) - 2 decimals.
        if ( $unit === 'mph/lb' || $unit === 'mi/lb' ) {
            return round( (float) $value, 2 );
        }

        // mph (top speed) - 1 decimal.
        if ( $unit === 'mph' ) {
            return round( (float) $value, 1 );
        }

        // mi (range) - 1 decimal.
        if ( $unit === 'mi' ) {
            return round( (float) $value, 1 );
        }

        // lbs (weight) - 1 decimal.
        if ( $unit === 'lbs' ) {
            return round( (float) $value, 1 );
        }

        // Integer for most other specs (power, battery capacity, etc.).
        return round( (float) $value );
    }

    /**
     * Format comparison text for display (e.g., "24.8 mph vs 21.1 mph avg").
     *
     * @param mixed $product_value Formatted product value.
     * @param mixed $bracket_avg   Formatted bracket average.
     * @param array $spec_def      Spec definition.
     * @return string Formatted comparison text.
     */
    private function format_comparison_text( $product_value, $bracket_avg, array $spec_def ): string {
        $unit = $spec_def['unit'] ?? '';

        // Value metrics: format as "$1.42/Wh vs $1.65/Wh avg".
        if ( ! empty( $spec_def['is_value'] ) ) {
            // Convert unit like "$/Wh" to "$X/Wh" format.
            $unit_suffix = str_replace( '$/', '/', $unit ); // "$/Wh" → "/Wh"
            return sprintf(
                '$%s%s vs $%s%s avg',
                $product_value,
                $unit_suffix,
                $bracket_avg,
                $unit_suffix
            );
        }

        // No unit: just show values.
        if ( empty( $unit ) ) {
            return sprintf( '%s vs %s avg', $product_value, $bracket_avg );
        }

        // Standard format: "24.8 mph vs 21.1 mph avg".
        return sprintf( '%s %s vs %s %s avg', $product_value, $unit, $bracket_avg, $unit );
    }

    /**
     * Format human-readable text for advantage/weakness.
     *
     * Uses rank for "Best/Worst" in small brackets where percentile alone
     * wouldn't reach 95%+ (e.g., #1 of 7 = 85.7% percentile).
     *
     * @param array $spec_def      Spec definition.
     * @param float $percentile    Percentile rank (0-100).
     * @param float $pct_vs_avg    Percentage vs average.
     * @param bool  $is_advantage  Whether this is an advantage.
     * @param bool  $higher_better Whether higher is better.
     * @param int   $rank          Product's rank (1 = best).
     * @param int   $total_count   Total products in comparison set.
     * @return string Formatted text.
     */
    private function format_single_text( array $spec_def, float $percentile, float $pct_vs_avg, bool $is_advantage, bool $higher_better, int $rank = 0, int $total_count = 0 ): string {
        $label       = $spec_def['label'];
        $label_lower = strtolower( $label );
        $key         = $spec_def['key'] ?? '';

        // Special handling for weight (use "lighter"/"heavier" instead of "better"/"worse").
        // Only match actual weight spec, not ratios like "Speed-to-weight".
        $is_weight = ( $key === 'dimensions.weight' );

        if ( $is_advantage ) {
            // Rank 1 = literally the best in bracket.
            if ( $rank === 1 ) {
                if ( $is_weight ) {
                    return 'Lightest';
                }
                return "Best {$label_lower}";
            } elseif ( $percentile >= 95 ) {
                if ( $is_weight ) {
                    return 'Lightest';
                }
                return "Best {$label_lower}";
            } elseif ( $percentile >= 90 || ( $total_count > 0 && $rank <= max( 2, (int) ceil( $total_count * 0.1 ) ) ) ) {
                if ( $is_weight ) {
                    return 'Very light';
                }
                return "Excellent {$label_lower}";
            } elseif ( $percentile >= 80 || ( $total_count > 0 && $rank <= max( 2, (int) ceil( $total_count * 0.2 ) ) ) ) {
                if ( $is_weight ) {
                    return 'Light';
                }
                return "Strong {$label_lower}";
            } else {
                // Triggered by pct_vs_avg threshold.
                $pct_display = abs( round( $pct_vs_avg ) );
                if ( $is_weight ) {
                    return "{$pct_display}% lighter than average";
                } elseif ( $higher_better ) {
                    return "{$pct_display}% above average {$label_lower}";
                } else {
                    return "{$pct_display}% better {$label_lower}";
                }
            }
        } else {
            // Last rank = literally the worst in bracket.
            if ( $rank === $total_count && $total_count > 0 ) {
                if ( $is_weight ) {
                    return 'Heaviest in class';
                }
                return "Lowest {$label_lower}";
            } elseif ( $percentile <= 5 ) {
                if ( $is_weight ) {
                    return 'Heaviest in class';
                }
                return "Lowest {$label_lower}";
            } elseif ( $percentile <= 10 || ( $total_count > 0 && $rank >= $total_count - max( 1, (int) ceil( $total_count * 0.1 ) ) + 1 ) ) {
                if ( $is_weight ) {
                    return 'Very heavy';
                }
                return "Low {$label_lower}";
            } elseif ( $percentile <= 20 || ( $total_count > 0 && $rank >= $total_count - max( 1, (int) ceil( $total_count * 0.2 ) ) + 1 ) ) {
                if ( $is_weight ) {
                    return 'Heavy';
                }
                return "Below average {$label_lower}";
            } else {
                // Triggered by pct_vs_avg threshold.
                $pct_display = abs( round( $pct_vs_avg ) );
                if ( $is_weight ) {
                    return "{$pct_display}% heavier than average";
                } elseif ( $higher_better ) {
                    return "{$pct_display}% below average {$label_lower}";
                } else {
                    return "{$pct_display}% worse {$label_lower}";
                }
            }
        }
    }

    /**
     * Process a composite advantage (e.g., "Better ride quality").
     *
     * @param string $spec_key   Composite spec key.
     * @param array  $spec       Composite spec config.
     * @param array  $products   Products.
     * @param array  $advantages Current advantages.
     * @return array|null Result with updated advantages, winner, and close flag.
     */
    private function process_composite_advantage(string $spec_key, array $spec, array $products, array $advantages): ?array {
        // Special handling for portability composite.
        if ($spec_key === '_portability') {
            return $this->process_portability_composite($spec, $products, $advantages);
        }

        $score_key = $spec['scoreKey'] ?? '';
        $threshold = $spec['threshold'] ?? 5;
        $child_specs = $spec['specs'] ?? [];

        // Get category scores.
        $score_a = $products[0]['specs']['scores'][$score_key] ?? 0;
        $score_b = $products[1]['specs']['scores'][$score_key] ?? 0;

        $diff = abs($score_a - $score_b);
        $close = $diff < $threshold;

        // If scores are close, skip consolidated.
        if ($close) {
            return [
                'advantages' => $advantages,
                'winner'     => null,
                'close'      => true,
            ];
        }

        // Determine category winner.
        $winner_idx = $score_a > $score_b ? 0 : 1;
        $loser_idx = $winner_idx === 0 ? 1 : 0;

        if (!$this->can_add_advantage($advantages, $winner_idx)) {
            return [
                'advantages' => $advantages,
                'winner'     => $winner_idx,
                'close'      => false,
            ];
        }

        // Collect individual spec wins for the comparison line.
        $spec_config = SpecConfig::get_comparison_specs();
        $winner_details = [];

        foreach ($child_specs as $child_key) {
            $child_spec = $spec_config[$child_key] ?? null;
            if (!$child_spec) {
                continue;
            }

            $val_a = $this->get_normalized_spec_value($products[0]['specs'], $child_key, $child_spec);
            $val_b = $this->get_normalized_spec_value($products[1]['specs'], $child_key, $child_spec);

            if ($val_a === null || $val_b === null || $val_a === '' || $val_b === '') {
                continue;
            }

            $child_winner = $this->compare_spec_values($val_a, $val_b, $child_spec);
            if ($child_winner === $winner_idx) {
                if (!empty($child_spec['displayFormatter'])) {
                    $raw_a = $this->get_nested_spec($products[0]['specs'], $child_key);
                    $raw_b = $this->get_nested_spec($products[1]['specs'], $child_key);
                    $detail = $this->format_composite_detail($child_key, $child_spec, $raw_a, $raw_b, $winner_idx);
                } else {
                    $detail = $this->format_composite_detail($child_key, $child_spec, $val_a, $val_b, $winner_idx);
                }
                if ($detail) {
                    $winner_details[] = $detail;
                }
            }
        }

        // Create consolidated advantage.
        if (!empty($winner_details)) {
            $comparison_line = implode(', ', $winner_details);
            $comparison_line = ucfirst($comparison_line);

            $diff_format = $spec['diffFormat'] ?? 'better';
            $label = $spec['label'] ?? 'specs';

            switch ($diff_format) {
                case 'lower':
                    $text = 'Lower ' . $label;
                    break;
                case 'safer':
                    $text = 'Safer';
                    break;
                default:
                    $text = 'Better ' . $label;
            }

            $advantages[$winner_idx][] = [
                'text'       => $text,
                'comparison' => $comparison_line,
                'winner'     => $winner_idx,
                'spec_key'   => $spec_key,
                'tooltip'    => $spec['tooltip'] ?? null,
            ];
        }

        return [
            'advantages'    => $advantages,
            'winner'        => $winner_idx,
            'close'         => false,
            'handled_specs' => $child_specs,
        ];
    }

    /**
     * Process portability composite advantage.
     *
     * @param array $spec       Composite spec config.
     * @param array $products   Products.
     * @param array $advantages Current advantages.
     * @return array Result with updated advantages, winner, and close flag.
     */
    private function process_portability_composite(array $spec, array $products, array $advantages): array {
        // Get weight values.
        $weight_a = $this->get_nested_spec($products[0]['specs'], 'dimensions.weight');
        $weight_b = $this->get_nested_spec($products[1]['specs'], 'dimensions.weight');

        // Get footprint values.
        $footprint_a = $this->calculate_folded_footprint($products[0]['specs']);
        $footprint_b = $this->calculate_folded_footprint($products[1]['specs']);

        // Determine winners.
        $weight_winner = null;
        $footprint_winner = null;

        if (is_numeric($weight_a) && is_numeric($weight_b) && $weight_a !== $weight_b) {
            $weight_winner = (float) $weight_a < (float) $weight_b ? 0 : 1;
        }

        if ($footprint_a !== null && $footprint_b !== null && $footprint_a !== $footprint_b) {
            $footprint_winner = $footprint_a < $footprint_b ? 0 : 1;
        }

        // Case 1: Same product wins both.
        if ($weight_winner !== null && $footprint_winner !== null && $weight_winner === $footprint_winner) {
            $winner_idx = $weight_winner;

            if ($this->can_add_advantage($advantages, $winner_idx)) {
                $weight_diff = abs((float) $weight_a - (float) $weight_b);
                $winner_footprint = $winner_idx === 0 ? $footprint_a : $footprint_b;
                $loser_footprint = $winner_idx === 0 ? $footprint_b : $footprint_a;

                $details = [];
                $details[] = $this->format_spec_number($weight_diff) . ' lbs lighter';
                $details[] = 'smaller when folded (' . $this->format_folded_footprint($winner_footprint) . ' vs ' . $this->format_folded_footprint($loser_footprint) . ')';

                $advantages[$winner_idx][] = [
                    'text'       => 'More portable',
                    'comparison' => ucfirst(implode(', ', $details)),
                    'winner'     => $winner_idx,
                    'spec_key'   => '_portability',
                    'tooltip'    => $spec['tooltip'] ?? null,
                ];
            }

            return [
                'advantages'    => $advantages,
                'winner'        => $winner_idx,
                'close'         => false,
                'handled_specs' => ['dimensions.weight', 'folded_footprint'],
            ];
        }

        // Case 2: Split wins.
        $handled_specs = [];

        if ($weight_winner !== null && $this->can_add_advantage($advantages, $weight_winner)) {
            $weight_diff = abs((float) $weight_a - (float) $weight_b);
            $winner_weight = $weight_winner === 0 ? $weight_a : $weight_b;
            $loser_weight = $weight_winner === 0 ? $weight_b : $weight_a;

            $advantages[$weight_winner][] = [
                'text'       => $this->format_spec_number($weight_diff) . ' lbs lighter',
                'comparison' => $this->format_spec_number((float) $winner_weight) . ' lbs vs ' . $this->format_spec_number((float) $loser_weight) . ' lbs',
                'winner'     => $weight_winner,
                'spec_key'   => 'dimensions.weight',
                'tooltip'    => SpecConfig::get_tooltip('dimensions.weight', 'comparison'),
            ];
            $handled_specs[] = 'dimensions.weight';
        }

        if ($footprint_winner !== null && $this->can_add_advantage($advantages, $footprint_winner)) {
            $winner_footprint = $footprint_winner === 0 ? $footprint_a : $footprint_b;
            $loser_footprint = $footprint_winner === 0 ? $footprint_b : $footprint_a;

            $advantages[$footprint_winner][] = [
                'text'       => 'Smaller when folded',
                'comparison' => $this->format_folded_footprint($winner_footprint) . ' vs ' . $this->format_folded_footprint($loser_footprint),
                'winner'     => $footprint_winner,
                'spec_key'   => 'folded_footprint',
                'tooltip'    => SpecConfig::get_tooltip('folded_footprint', 'comparison'),
            ];
            $handled_specs[] = 'folded_footprint';
        }

        return [
            'advantages'    => $advantages,
            'winner'        => null,
            'close'         => true,
            'handled_specs' => $handled_specs,
        ];
    }

    /**
     * Process individual spec for the loser of a composite category.
     *
     * @param string $spec_key    Spec key.
     * @param array  $spec        Spec config.
     * @param array  $products    Products.
     * @param int    $comp_winner Composite category winner index.
     * @param array  $advantages  Current advantages.
     * @return array|null Updated advantages or null.
     */
    private function process_individual_spec_for_loser(string $spec_key, array $spec, array $products, int $comp_winner, array $advantages): ?array {
        $loser_idx = $comp_winner === 0 ? 1 : 0;

        if (!$this->can_add_advantage($advantages, $loser_idx)) {
            return $advantages;
        }

        $val_a = $this->get_normalized_spec_value($products[0]['specs'], $spec_key, $spec);
        $val_b = $this->get_normalized_spec_value($products[1]['specs'], $spec_key, $spec);

        if ($val_a === null || $val_b === null || $val_a === '' || $val_b === '') {
            return $advantages;
        }

        $spec_winner = $this->compare_spec_values($val_a, $val_b, $spec);
        if ($spec_winner !== $loser_idx) {
            return $advantages;
        }

        // Format the advantage.
        if (!empty($spec['displayFormatter'])) {
            $raw_a = $this->get_nested_spec($products[0]['specs'], $spec_key);
            $raw_b = $this->get_nested_spec($products[1]['specs'], $spec_key);
            $adv = $this->format_display_formatter_advantage($spec_key, $spec, $raw_a, $raw_b, $loser_idx);
        } elseif (!empty($spec['ranking'])) {
            $adv = $this->format_ranked_advantage($spec_key, $spec, $val_a, $val_b);
        } elseif (is_numeric($val_a) && is_numeric($val_b)) {
            $adv = $this->format_spec_advantage($spec_key, $spec, (float) $val_a, (float) $val_b, $loser_idx);
        } else {
            return $advantages;
        }

        if ($adv) {
            $advantages[$loser_idx][] = $adv;
        }

        return $advantages;
    }

    /**
     * Calculate folded footprint (length × width in sq inches).
     *
     * @param array $specs Product specs.
     * @return float|null Footprint or null if dimensions unavailable.
     */
    private function calculate_folded_footprint(array $specs): ?float {
        $length = $this->get_nested_spec($specs, 'dimensions.folded_length');
        $width = $this->get_nested_spec($specs, 'dimensions.folded_width');

        if (!is_numeric($length) || !is_numeric($width)) {
            return null;
        }

        return (float) $length * (float) $width;
    }

    /**
     * Format folded footprint for display.
     *
     * @param float $footprint Footprint in sq inches.
     * @return string Formatted string (e.g., "748 sq in").
     */
    private function format_folded_footprint(float $footprint): string {
        return number_format($footprint, 0) . ' sq in';
    }
}
