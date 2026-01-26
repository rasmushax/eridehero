<?php
/**
 * Shared scoring helper methods for product scorers.
 *
 * This trait provides reusable scoring patterns (log scale, linear scale,
 * boolean, tier-based) to reduce code duplication across product-type scorers.
 *
 * @package ERH\Scoring
 */

declare(strict_types=1);

namespace ERH\Scoring;

/**
 * Trait providing shared scoring methods.
 */
trait ScoringHelpers {

    /**
     * Log-scale scoring for continuous values.
     *
     * Early gains matter more (diminishing returns curve).
     * Example: 500W→1000W is huge, 5000W→5500W is marginal.
     *
     * @param mixed $value   The value to score.
     * @param float $floor   Minimum value (scores 0).
     * @param float $ceiling Maximum value (scores max_pts).
     * @param int   $max_pts Maximum points for this factor.
     * @param bool  $inverse If true, higher values score lower (e.g., weight, charge time).
     * @return array{score: float|null, max_possible: int}
     */
    protected function log_scale($value, float $floor, float $ceiling, int $max_pts, bool $inverse = false): array {
        if (!$this->is_valid_numeric($value)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $v = (float) $value;

        // Cap at floor/ceiling.
        if ($v <= $floor) {
            return ['score' => $inverse ? $max_pts : 0, 'max_possible' => $max_pts];
        }
        if ($v >= $ceiling) {
            return ['score' => $inverse ? 0 : $max_pts, 'max_possible' => $max_pts];
        }

        // Calculate log scale ratio.
        $ratio = log($v / $floor, 2) / log($ceiling / $floor, 2);

        // Apply inverse if needed (for "lower is better" metrics).
        $score = $inverse ? ($max_pts * (1 - $ratio)) : ($max_pts * $ratio);

        return ['score' => max(0, min($max_pts, $score)), 'max_possible' => $max_pts];
    }

    /**
     * Linear scale scoring for continuous values.
     *
     * @param mixed $value   The value to score.
     * @param float $min     Minimum value (scores 0).
     * @param float $max     Maximum value (scores max_pts).
     * @param int   $max_pts Maximum points for this factor.
     * @param bool  $inverse If true, higher values score lower.
     * @return array{score: float|null, max_possible: int}
     */
    protected function linear_scale($value, float $min, float $max, int $max_pts, bool $inverse = false): array {
        if (!$this->is_valid_numeric($value)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $v = (float) $value;

        // Cap at min/max.
        if ($v <= $min) {
            return ['score' => $inverse ? $max_pts : 0, 'max_possible' => $max_pts];
        }
        if ($v >= $max) {
            return ['score' => $inverse ? 0 : $max_pts, 'max_possible' => $max_pts];
        }

        // Linear interpolation.
        $ratio = ($v - $min) / ($max - $min);
        $score = $inverse ? ($max_pts * (1 - $ratio)) : ($max_pts * $ratio);

        return ['score' => max(0, min($max_pts, $score)), 'max_possible' => $max_pts];
    }

    /**
     * Boolean scoring (yes/no features).
     *
     * @param mixed $value     The value to check.
     * @param int   $true_pts  Points if true.
     * @param int   $false_pts Points if false (default 0).
     * @return array{score: float|null, max_possible: int}
     */
    protected function boolean_score($value, int $true_pts, int $false_pts = 0): array {
        if ($value === null) {
            return ['score' => null, 'max_possible' => 0];
        }

        return [
            'score' => $value === true ? $true_pts : $false_pts,
            'max_possible' => $true_pts,
        ];
    }

    /**
     * Tier-based scoring for categorical values (e.g., brand quality).
     *
     * @param string $value   The value to match.
     * @param array  $tiers   Array of [pattern => score] pairs, checked in order.
     * @param int    $max_pts Maximum points for this factor.
     * @param int    $default Default score if no pattern matches.
     * @return array{score: float|null, max_possible: int}
     */
    protected function tier_score(string $value, array $tiers, int $max_pts, int $default = 0): array {
        if (empty($value)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $value_lower = strtolower($value);

        foreach ($tiers as $pattern => $score) {
            if (strpos($value_lower, strtolower($pattern)) !== false) {
                return ['score' => $score, 'max_possible' => $max_pts];
            }
        }

        return ['score' => $default, 'max_possible' => $max_pts];
    }

    /**
     * Pattern-based tier scoring using regex.
     *
     * @param string $value   The value to match.
     * @param array  $tiers   Array of [pattern => score] pairs where pattern is regex.
     * @param int    $max_pts Maximum points for this factor.
     * @param int    $default Default score if no pattern matches.
     * @return array{score: float|null, max_possible: int}
     */
    protected function regex_tier_score(string $value, array $tiers, int $max_pts, int $default = 0): array {
        if (empty(trim($value))) {
            return ['score' => null, 'max_possible' => 0];
        }

        foreach ($tiers as $pattern => $score) {
            if (preg_match($pattern, $value)) {
                return ['score' => $score, 'max_possible' => $max_pts];
            }
        }

        return ['score' => $default, 'max_possible' => $max_pts];
    }

    /**
     * Validate numeric value (not null, numeric, > 0).
     *
     * @param mixed $value The value to validate.
     * @return bool True if valid numeric value.
     */
    protected function is_valid_numeric($value): bool {
        return $value !== null && is_numeric($value) && (float) $value > 0;
    }

    /**
     * Calculate category score from factors array.
     *
     * Filters out null scores and calculates weighted percentage.
     *
     * @param array $factors Array of factor results with 'score' and 'max_possible'.
     * @return int|null Score 0-100 or null if no data.
     */
    protected function calculate_category_score(array $factors): ?int {
        $available = array_filter($factors, fn($f) => $f['score'] !== null);

        if (empty($available)) {
            return null;
        }

        $total_score = array_sum(array_column($available, 'score'));
        $total_max   = array_sum(array_column($available, 'max_possible'));

        return (int) round(($total_score / $total_max) * 100);
    }

    /**
     * Check if any element in array satisfies the callback.
     *
     * @param array    $array    The array to check.
     * @param callable $callback The callback function.
     * @return bool True if any element satisfies the callback.
     */
    protected function array_some(array $array, callable $callback): bool {
        foreach ($array as $element) {
            if ($callback($element)) {
                return true;
            }
        }
        return false;
    }
}
