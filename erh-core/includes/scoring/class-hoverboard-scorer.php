<?php
/**
 * Hoverboard Scorer - calculates scores across 5 categories.
 *
 * Categories:
 * - Motor Performance (25%): Motor power, top speed, battery voltage
 * - Battery & Range (25%): Battery capacity (sole factor)
 * - Portability (20%): Weight (sole factor, inverse)
 * - Ride Comfort (15%): Wheel size (log scale 5-12"), pneumatic tires
 * - Features (15%): Feature checklist + connectivity booleans
 *
 * @package ERH\Scoring
 */

declare(strict_types=1);

namespace ERH\Scoring;

/**
 * Calculate absolute product scores for hoverboards.
 */
class HoverboardScorer {

    use ScoringHelpers;
    use SpecsAccessor;

    /**
     * Category weights for overall score calculation.
     *
     * @var array<string, float>
     */
    private const CATEGORY_WEIGHTS = [
        'motor_performance' => 0.25,
        'battery_range'     => 0.25,
        'portability'       => 0.20,
        'ride_comfort'      => 0.15,
        'features'          => 0.15,
    ];

    /**
     * Calculate all category scores for a hoverboard.
     *
     * @param array $specs The product specs array.
     * @return array Category scores including overall.
     */
    public function calculate(array $specs): array {
        $this->set_product_group_key('Hoverboard');

        $scores = [
            'motor_performance' => $this->calculate_motor_performance($specs),
            'battery_range'     => $this->calculate_battery_range($specs),
            'portability'       => $this->calculate_portability($specs),
            'ride_comfort'      => $this->calculate_ride_comfort($specs),
            'features'          => $this->calculate_features($specs),
        ];

        // Calculate weighted overall score.
        $scores['overall'] = $this->calculate_overall_score($scores);

        return $scores;
    }

    /**
     * Calculate weighted overall score from category scores.
     *
     * @param array $scores Category scores.
     * @return int|null Overall score 0-100 or null if no data.
     */
    private function calculate_overall_score(array $scores): ?int {
        $weighted_sum = 0.0;
        $total_weight = 0.0;

        foreach (self::CATEGORY_WEIGHTS as $category => $weight) {
            if (isset($scores[$category]) && $scores[$category] !== null) {
                $weighted_sum += $scores[$category] * $weight;
                $total_weight += $weight;
            }
        }

        if ($total_weight === 0.0) {
            return null;
        }

        return (int) round($weighted_sum / $total_weight);
    }

    /**
     * Get flat fallback value for hoverboard fields.
     *
     * Hoverboards use nested ACF structure without flat fallbacks.
     *
     * @param array  $array       The specs array.
     * @param string $nested_path The nested path to map.
     * @return mixed Always returns null for hoverboards.
     */
    protected function get_flat_fallback(array $array, string $nested_path) {
        return null;
    }

    // =========================================================================
    // Motor Performance (100 pts max)
    // =========================================================================

    /**
     * Calculate Motor Performance category score.
     *
     * Factors: Motor Power 50pts (log scale), Top Speed 25pts (log scale),
     * Battery Voltage 25pts (log scale).
     */
    private function calculate_motor_performance(array $specs): ?int {
        $factors = [
            $this->log_scale(
                $this->get_nested_value($specs, 'motor.power_nominal')
                ?? $this->get_nested_value($specs, 'nominal_motor_wattage'),
                150.0, 1000.0, 50
            ),
            $this->log_scale(
                $this->get_nested_value($specs, 'manufacturer_top_speed'),
                6.0, 12.0, 25
            ),
            $this->log_scale(
                $this->get_nested_value($specs, 'battery.voltage')
                ?? $this->get_nested_value($specs, 'motor.voltage'),
                24.0, 42.0, 25
            ),
        ];

        return $this->calculate_category_score($factors);
    }

    // =========================================================================
    // Battery & Range (100 pts max)
    // =========================================================================

    /**
     * Calculate Battery & Range category score.
     *
     * Sole factor: Battery capacity 100pts (log scale 30-200Wh).
     */
    private function calculate_battery_range(array $specs): ?int {
        $capacity = $this->get_nested_value($specs, 'battery.capacity')
                    ?? $this->get_nested_value($specs, 'battery_capacity');

        if (!$this->is_valid_numeric($capacity)) {
            return null;
        }

        $result = $this->log_scale((float) $capacity, 30.0, 200.0, 100);
        return $result['score'] !== null ? (int) round($result['score']) : null;
    }

    // =========================================================================
    // Portability (100 pts max)
    // =========================================================================

    /**
     * Calculate Portability category score.
     *
     * Sole factor: Weight 100pts (log scale 15-35lbs, inverse - lighter is better).
     */
    private function calculate_portability(array $specs): ?int {
        $weight = $this->get_nested_value($specs, 'dimensions.weight')
                  ?? $this->get_nested_value($specs, 'weight');

        if (!$this->is_valid_numeric($weight)) {
            return null;
        }

        $result = $this->log_scale((float) $weight, 15.0, 35.0, 100, true);
        return $result['score'] !== null ? (int) round($result['score']) : null;
    }

    // =========================================================================
    // Ride Comfort (100 pts max)
    // =========================================================================

    /**
     * Calculate Ride Comfort category score.
     *
     * Factors: Wheel size 65pts (log scale 5.0-12.0"), Pneumatic tires 35pts (boolean).
     */
    private function calculate_ride_comfort(array $specs): ?int {
        $wheel_type = $this->get_nested_value($specs, 'wheels.wheel_type')
                      ?? $this->get_nested_value($specs, 'wheel_type');

        $factors = [
            $this->log_scale(
                $this->get_nested_value($specs, 'wheels.wheel_size')
                ?? $this->get_nested_value($specs, 'wheel_size'),
                5.0, 12.0, 65
            ),
            $this->score_tire_type($wheel_type),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score tire type (pneumatic = 35pts, solid = 0pts).
     *
     * @param mixed $type Tire/wheel type string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_tire_type($type): array {
        if ($type === null || $type === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        $lower = strtolower((string) $type);

        $score = 0;
        if (strpos($lower, 'pneumatic') !== false || strpos($lower, 'air') !== false || strpos($lower, 'inflatable') !== false) {
            $score = 35;
        }

        return [
            'score'        => $score,
            'max_possible' => 35,
        ];
    }

    // =========================================================================
    // Features (100 pts max)
    // =========================================================================

    /**
     * Calculate Features category score.
     *
     * Counts features from checklist and connectivity booleans.
     * Checklist: Carrying Handle, Fenders, Non-Slip Deck, GPS Tracking,
     *            Learning Mode, LED Lights
     * Connectivity: Bluetooth Speaker, App Enabled, Speed Modes
     *
     * 9 features = 100 pts.
     */
    private function calculate_features(array $specs): ?int {
        $feature_count = 0;
        $has_any_data  = false;

        // Features array (ACF checkbox field stores feature names as strings).
        // e.g., ["LED Lights", "Carrying Handle"]
        $features_array = $this->get_nested_value($specs, 'features');
        if (is_array($features_array) && !empty($features_array)) {
            $has_any_data   = true;
            $feature_count += count($features_array);
        }

        // Connectivity booleans (nested under connectivity group).
        $connectivity_keys = [
            'connectivity.bluetooth_speaker',
            'connectivity.app_enabled',
            'connectivity.speed_modes',
        ];

        foreach ($connectivity_keys as $path) {
            $value = $this->get_nested_value($specs, $path);

            if ($value !== null) {
                $has_any_data = true;
                if ($value === true || $value === 1 || $value === '1' || (is_string($value) && in_array(strtolower($value), ['yes', 'true'], true))) {
                    $feature_count++;
                }
            }
        }

        // Safety features as bonus.
        $ul = $this->get_nested_value($specs, 'safety.ul_2272');
        if ($ul !== null) {
            $has_any_data = true;
            if ($ul === true || $ul === 1 || $ul === '1' || (is_string($ul) && in_array(strtolower($ul), ['yes', 'true'], true))) {
                $feature_count++;
            }
        }

        if (!$has_any_data) {
            return null;
        }

        // Scale to 0-100 (9 features = 100 pts).
        return min(100, (int) round(($feature_count / 9) * 100));
    }
}
