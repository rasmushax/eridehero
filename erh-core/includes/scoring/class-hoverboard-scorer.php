<?php
/**
 * Hoverboard Scorer - calculates scores across 5 categories.
 *
 * Categories:
 * - Motor Performance (25%): Motor power, top speed, battery voltage
 * - Battery & Range (25%): Battery capacity (sole factor)
 * - Portability (20%): Weight (sole factor, inverse)
 * - Ride Comfort (15%): Wheel size, pneumatic tires
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
            $this->score_motor_power(
                $this->get_nested_value($specs, 'nominal_motor_wattage')
            ),
            $this->score_top_speed(
                $this->get_nested_value($specs, 'manufacturer_top_speed')
            ),
            $this->score_voltage(
                $this->get_nested_value($specs, 'battery.voltage')
                ?? $this->get_nested_value($specs, 'motor.voltage')
            ),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score motor power (log scale 150W-600W, 50pts max).
     *
     * @param mixed $watts Motor wattage.
     * @return array{score: int|null, max_possible: int}
     */
    private function score_motor_power($watts): array {
        if (!$this->is_valid_numeric($watts)) {
            return ['score' => null, 'max_possible' => 0];
        }

        return [
            'score'        => $this->log_scale((float) $watts, 150.0, 600.0, 50),
            'max_possible' => 50,
        ];
    }

    /**
     * Score top speed (log scale 6-12mph, 25pts max).
     *
     * @param mixed $speed Top speed in mph.
     * @return array{score: int|null, max_possible: int}
     */
    private function score_top_speed($speed): array {
        if (!$this->is_valid_numeric($speed)) {
            return ['score' => null, 'max_possible' => 0];
        }

        return [
            'score'        => $this->log_scale((float) $speed, 6.0, 12.0, 25),
            'max_possible' => 25,
        ];
    }

    /**
     * Score battery voltage (log scale 24V-42V, 25pts max).
     *
     * @param mixed $voltage Battery voltage.
     * @return array{score: int|null, max_possible: int}
     */
    private function score_voltage($voltage): array {
        if (!$this->is_valid_numeric($voltage)) {
            return ['score' => null, 'max_possible' => 0];
        }

        return [
            'score'        => $this->log_scale((float) $voltage, 24.0, 42.0, 25),
            'max_possible' => 25,
        ];
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
        $capacity = $this->get_nested_value($specs, 'battery_capacity')
                    ?? $this->get_nested_value($specs, 'battery.capacity');

        if (!$this->is_valid_numeric($capacity)) {
            return null;
        }

        return $this->log_scale((float) $capacity, 30.0, 200.0, 100);
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
        $weight = $this->get_nested_value($specs, 'weight')
                  ?? $this->get_nested_value($specs, 'dimensions.weight');

        if (!$this->is_valid_numeric($weight)) {
            return null;
        }

        return $this->log_scale((float) $weight, 15.0, 35.0, 100, true);
    }

    // =========================================================================
    // Ride Comfort (100 pts max)
    // =========================================================================

    /**
     * Calculate Ride Comfort category score.
     *
     * Factors: Wheel size 60pts (linear 6.5-10"), Pneumatic tires 40pts (boolean).
     */
    private function calculate_ride_comfort(array $specs): ?int {
        $factors = [
            $this->score_wheel_size(
                $this->get_nested_value($specs, 'wheel_size')
            ),
            $this->score_tire_type(
                $this->get_nested_value($specs, 'wheel_type')
                ?? $this->get_nested_value($specs, 'tire_type')
            ),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score wheel size (linear 6.5-10", 60pts max).
     *
     * @param mixed $size Wheel size in inches.
     * @return array{score: int|null, max_possible: int}
     */
    private function score_wheel_size($size): array {
        if (!$this->is_valid_numeric($size)) {
            return ['score' => null, 'max_possible' => 0];
        }

        return [
            'score'        => $this->linear_scale((float) $size, 6.5, 10.0, 60),
            'max_possible' => 60,
        ];
    }

    /**
     * Score tire type (pneumatic = 40pts, solid = 0pts).
     *
     * @param mixed $type Tire/wheel type string.
     * @return array{score: int|null, max_possible: int}
     */
    private function score_tire_type($type): array {
        if ($type === null || $type === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        $lower = strtolower((string) $type);

        $score = 0;
        if (strpos($lower, 'pneumatic') !== false || strpos($lower, 'air') !== false || strpos($lower, 'inflatable') !== false) {
            $score = 40;
        }

        return [
            'score'        => $score,
            'max_possible' => 40,
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

        // Feature checklist booleans.
        $checklist = [
            'carrying_handle',
            'fenders',
            'non_slip_deck',
            'gps_tracking',
            'learning_mode',
            'led_lights',
        ];

        foreach ($checklist as $feature_key) {
            $value = $this->get_nested_value($specs, $feature_key)
                     ?? $this->get_nested_value($specs, 'features.' . $feature_key);

            if ($value !== null) {
                $has_any_data = true;
                if ($value === true || $value === 1 || $value === '1' || strtolower((string) $value) === 'yes' || strtolower((string) $value) === 'true') {
                    $feature_count++;
                }
            }
        }

        // Features array (ACF checkbox field).
        $features_array = $this->get_nested_value($specs, 'features');
        if (is_array($features_array)) {
            $has_any_data  = true;
            $feature_count += count($features_array);
        }

        // Connectivity booleans.
        $connectivity = [
            'bluetooth_speaker' => ['connectivity.bluetooth_speaker', 'bluetooth_speaker'],
            'app_enabled'       => ['connectivity.app_enabled', 'app_enabled'],
            'speed_modes'       => ['connectivity.speed_modes', 'speed_modes'],
        ];

        foreach ($connectivity as $key => $paths) {
            $value = null;
            foreach ($paths as $path) {
                $value = $this->get_nested_value($specs, $path);
                if ($value !== null) {
                    break;
                }
            }

            if ($value !== null) {
                $has_any_data = true;
                if ($value === true || $value === 1 || $value === '1' || strtolower((string) $value) === 'yes' || strtolower((string) $value) === 'true') {
                    $feature_count++;
                }
            }
        }

        if (!$has_any_data) {
            return null;
        }

        // Scale to 0-100 (9 features = 100 pts).
        return min(100, (int) round(($feature_count / 9) * 100));
    }
}
