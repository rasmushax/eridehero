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
     * Calculate advantages for head-to-head (2 product) comparison.
     *
     * @param array $products Array of 2 product data arrays.
     * @return array Array with 2 elements, each containing advantages for that product.
     */
    public function calculate_head_to_head(array $products): array {
        if (count($products) !== 2) {
            return array_fill(0, count($products), []);
        }

        $spec_config = SpecConfig::COMPARISON_SPECS;
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
            [
                'key'           => 'tested_top_speed',
                'fallback'      => 'manufacturer_top_speed',
                'label'         => 'Fastest',
                'unit'          => 'mph',
                'higher_better' => true,
                'format'        => 'speed',
                'tooltip'       => 'Highest top speed (tested if available, otherwise manufacturer claim)',
            ],
            // Battery capacity (Wh) - always use this, not range claimed.
            [
                'key'           => 'battery.capacity',
                'label'         => 'Largest battery',
                'unit'          => 'Wh',
                'higher_better' => true,
                'format'        => 'numeric',
                'tooltip'       => 'Largest battery capacity in watt-hours',
            ],
            // Tested range - only if all have it.
            [
                'key'           => 'tested_range_regular',
                'label'         => 'Longest tested range',
                'unit'          => 'mi',
                'higher_better' => true,
                'format'        => 'numeric',
                'require_all'   => true,
                'tooltip'       => 'Longest range in our real-world test (175 lbs rider, mixed terrain)',
            ],
            // Weight - lighter is better.
            [
                'key'           => 'dimensions.weight',
                'label'         => 'Lightest',
                'unit'          => 'lbs',
                'higher_better' => false,
                'format'        => 'numeric',
                'tooltip'       => 'Lowest weight for easier carrying and transport',
            ],
            // Max load capacity.
            [
                'key'           => 'dimensions.max_load',
                'label'         => 'Highest weight capacity',
                'unit'          => 'lbs',
                'higher_better' => true,
                'format'        => 'numeric',
                'tooltip'       => 'Highest maximum rider weight supported',
            ],
            // Wh per pound - efficiency metric.
            [
                'key'           => 'wh_per_lb',
                'label'         => 'Best Wh/lb ratio',
                'unit'          => 'Wh/lb',
                'higher_better' => true,
                'format'        => 'decimal',
                'tooltip'       => 'Battery capacity divided by weight. Higher = more energy per pound of scooter',
            ],
            // mph per pound - power-to-weight metric.
            [
                'key'           => 'speed_per_lb',
                'label'         => 'Best mph/lb ratio',
                'unit'          => 'mph/lb',
                'higher_better' => true,
                'format'        => 'decimal',
                'tooltip'       => 'Top speed divided by weight. Higher = more speed per pound of scooter',
            ],
            // Suspension - best type wins.
            [
                'key'           => 'suspension.type',
                'label'         => 'Best suspension',
                'higher_better' => true,
                'format'        => 'suspension',
                'tooltip'       => 'Best suspension system for absorbing bumps and improving ride comfort',
            ],
            // IP rating.
            [
                'key'           => 'other.ip_rating',
                'label'         => 'Best water resistance',
                'higher_better' => true,
                'format'        => 'ip_rating',
                'tooltip'       => 'Highest IP water resistance rating for riding in wet conditions',
            ],
            // Features count.
            [
                'key'           => 'features',
                'label'         => 'Most features',
                'higher_better' => true,
                'format'        => 'feature_count',
                'tooltip'       => 'Most built-in features like app control, lights, cruise control, etc.',
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
                    'tooltip'    => 'Lowest maintenance requirements based on tire type, self-healing, and water resistance',
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
                'tooltip'    => 'Folded floor space (length × width). Smaller = easier to store and transport.',
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
     * Calculate advantages for single product display.
     *
     * @param array $product Single product data array.
     * @return array|null Advantages array or null if not implemented.
     */
    public function calculate_single(array $product): ?array {
        // TODO: Implement single product advantages.
        return null;
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
        $spec_config = SpecConfig::COMPARISON_SPECS;
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
                'tooltip'    => 'Lighter scooters are easier to carry upstairs, onto public transit, or into your office.',
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
                'tooltip'    => 'Folded floor space (length × width). Smaller = easier to store and transport.',
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
