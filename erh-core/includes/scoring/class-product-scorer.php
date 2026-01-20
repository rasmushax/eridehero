<?php
/**
 * Product Scorer for absolute scoring based on fixed thresholds.
 *
 * Uses logarithmic scaling where early gains matter more (500W→1000W is huge, 5000W→5500W is marginal).
 * Each category scores 0-100. Missing specs redistribute weight to available specs.
 *
 * @package ERH\Scoring
 */

declare(strict_types=1);

namespace ERH\Scoring;

/**
 * Calculate absolute product scores across 7 categories.
 *
 * Categories:
 * - Motor Performance: How fast/powerful?
 * - Range & Battery: How far can I go?
 * - Ride Quality: Is it comfortable?
 * - Portability & Fit: Can I carry it?
 * - Safety: Is it safe?
 * - Features: What extras?
 * - Maintenance: Is it hassle-free?
 */
class ProductScorer {

    /**
     * Category weights for overall score calculation.
     *
     * @var array<string, float>
     */
    private const CATEGORY_WEIGHTS = [
        'motor_performance' => 0.20,
        'range_battery'     => 0.20,
        'ride_quality'      => 0.20,
        'portability'       => 0.15,
        'safety'            => 0.10,
        'features'          => 0.10,
        'maintenance'       => 0.05,
    ];

    /**
     * Calculate all category scores for a product.
     *
     * @param array  $specs        The product specs array.
     * @param string $product_type The product type (e.g., 'Electric Scooter').
     * @return array{
     *     motor_performance: int|null,
     *     range_battery: int|null,
     *     ride_quality: int|null,
     *     portability: int|null,
     *     safety: int|null,
     *     features: int|null,
     *     maintenance: int|null,
     *     overall: int|null
     * }
     */
    public function calculate_scores(array $specs, string $product_type): array {
        // Only e-scooters have scoring implemented for now.
        if ($product_type !== 'Electric Scooter') {
            return [
                'motor_performance' => null,
                'range_battery'     => null,
                'ride_quality'      => null,
                'portability'       => null,
                'safety'            => null,
                'features'          => null,
                'maintenance'       => null,
                'overall'           => null,
            ];
        }

        // Set the product group key for nested ACF structure lookups.
        // E-scooter data is stored under 'e-scooters' group in ACF.
        $this->set_product_group_key($product_type);

        $scores = [
            'motor_performance' => $this->calculate_motor_performance($specs),
            'range_battery'     => $this->calculate_range_battery($specs),
            'ride_quality'      => $this->calculate_ride_quality($specs),
            'portability'       => $this->calculate_portability($specs),
            'safety'            => $this->calculate_safety($specs),
            'features'          => $this->calculate_features($specs),
            'maintenance'       => $this->calculate_maintenance($specs),
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

    // =========================================================================
    // Motor Performance (100 pts max)
    // =========================================================================

    /**
     * Calculate Motor Performance category score.
     *
     * Factors: Power 45pts, Voltage 20pts, Top Speed 25pts, Dual Motor 10pts.
     *
     * @param array $specs Product specs.
     * @return int|null Score 0-100 or null if no data.
     */
    private function calculate_motor_performance(array $specs): ?int {
        $factors = [
            $this->score_power(
                $this->get_nested_value($specs, 'motor.power_nominal'),
                $this->get_nested_value($specs, 'motor.power_peak')
            ),
            $this->score_motor_voltage($this->get_nested_value($specs, 'motor.voltage')),
            $this->score_top_speed($this->get_top_level_value($specs, 'manufacturer_top_speed')),
            $this->score_dual_motor($this->get_nested_value($specs, 'motor.motor_position')),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score motor power (nominal + peak average).
     * Log scale: 400W floor, 8000W ceiling.
     *
     * @param mixed $nominal Nominal power in watts.
     * @param mixed $peak    Peak power in watts.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_power($nominal, $peak): array {
        $values = array_filter(
            [$nominal, $peak],
            fn($v) => $v !== null && is_numeric($v) && (float) $v > 0
        );

        if (empty($values)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $avg_power = array_sum($values) / count($values);

        // Log scale: 45 * log2(watts/400) / log2(20)
        // 400W → 0 pts, 8000W → 45 pts.
        $raw   = 45 * log($avg_power / 400, 2) / log(20, 2);
        $score = max(0, min(45, $raw));

        return ['score' => $score, 'max_possible' => 45];
    }

    /**
     * Score motor voltage.
     * Log scale: 18V floor, 84V ceiling.
     *
     * @param mixed $voltage Voltage in V.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_motor_voltage($voltage): array {
        if ($voltage === null || !is_numeric($voltage) || (float) $voltage <= 0) {
            return ['score' => null, 'max_possible' => 0];
        }

        $voltage = (float) $voltage;

        // Log scale: 20 * log2(voltage/18) / log2(4.67)
        // 18V → 0 pts, 84V → 20 pts.
        $raw   = 20 * log($voltage / 18, 2) / log(4.67, 2);
        $score = max(0, min(20, $raw));

        return ['score' => $score, 'max_possible' => 20];
    }

    /**
     * Score top speed.
     * Log scale: 8 mph floor, 60 mph ceiling.
     *
     * @param mixed $speed_mph Top speed in mph.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_top_speed($speed_mph): array {
        if ($speed_mph === null || !is_numeric($speed_mph) || (float) $speed_mph <= 0) {
            return ['score' => null, 'max_possible' => 0];
        }

        $speed = (float) $speed_mph;

        // Log scale: 25 * log2(speed/8) / log2(7.5)
        // 8 mph → 0 pts, 60 mph → 25 pts.
        $raw   = 25 * log($speed / 8, 2) / log(7.5, 2);
        $score = max(0, min(25, $raw));

        return ['score' => $score, 'max_possible' => 25];
    }

    /**
     * Score dual motor bonus.
     *
     * @param mixed $motor_position Motor position string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_dual_motor($motor_position): array {
        if (empty($motor_position)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $pos = strtolower((string) $motor_position);

        if (strpos($pos, 'dual') !== false) {
            return ['score' => 10, 'max_possible' => 10];
        }

        if (strpos($pos, 'front') !== false || strpos($pos, 'rear') !== false) {
            return ['score' => 0, 'max_possible' => 10];
        }

        return ['score' => null, 'max_possible' => 0];
    }

    // =========================================================================
    // Range & Battery (100 pts max)
    // =========================================================================

    /**
     * Calculate Range & Battery category score.
     *
     * Factors: Battery Capacity 70pts, Voltage 20pts, Charge Time 10pts.
     *
     * @param array $specs Product specs.
     * @return int|null Score 0-100 or null if no data.
     */
    private function calculate_range_battery(array $specs): ?int {
        $factors = [
            $this->score_battery_capacity($this->get_nested_value($specs, 'battery.capacity')),
            $this->score_battery_voltage($this->get_nested_value($specs, 'battery.voltage')),
            $this->score_charge_time($this->get_nested_value($specs, 'battery.charging_time')),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score battery capacity.
     * Log scale: 150Wh floor, 3000Wh ceiling.
     *
     * @param mixed $wh Battery capacity in Wh.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_battery_capacity($wh): array {
        if ($wh === null || !is_numeric($wh) || (float) $wh <= 0) {
            return ['score' => null, 'max_possible' => 0];
        }

        $capacity = (float) $wh;

        // Log scale: 70 * log2(wh/150) / log2(20)
        // 150Wh → 0 pts, 3000Wh → 70 pts.
        $raw   = 70 * log($capacity / 150, 2) / log(20, 2);
        $score = max(0, min(70, $raw));

        return ['score' => $score, 'max_possible' => 70];
    }

    /**
     * Score battery voltage.
     * Log scale: 18V floor, 84V ceiling (same as motor voltage).
     *
     * @param mixed $voltage Battery voltage in V.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_battery_voltage($voltage): array {
        if ($voltage === null || !is_numeric($voltage) || (float) $voltage <= 0) {
            return ['score' => null, 'max_possible' => 0];
        }

        $v = (float) $voltage;

        // Log scale: 20 * log2(voltage/18) / log2(4.67).
        $raw   = 20 * log($v / 18, 2) / log(4.67, 2);
        $score = max(0, min(20, $raw));

        return ['score' => $score, 'max_possible' => 20];
    }

    /**
     * Score charge time (lower is better).
     * Inverse log scale: 2h excellent, 12h+ poor.
     *
     * @param mixed $hours Charge time in hours.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_charge_time($hours): array {
        if ($hours === null || !is_numeric($hours) || (float) $hours <= 0) {
            return ['score' => null, 'max_possible' => 0];
        }

        $h = (float) $hours;

        // Inverse log scale: 10 * (1 - log2(hours/1.5) / log2(10))
        // 2h → 9 pts, 6h → 4 pts, 15h+ → 0 pts.
        $raw   = 10 * (1 - log($h / 1.5, 2) / log(10, 2));
        $score = max(0, min(10, $raw));

        return ['score' => $score, 'max_possible' => 10];
    }

    // =========================================================================
    // Ride Quality (100 pts max)
    // =========================================================================

    /**
     * Calculate Ride Quality category score.
     *
     * Factors: Suspension 40pts, Tire Type 25pts, Tire Size 15pts,
     * Deck/Handlebar 10pts, Comfort Extras 10pts.
     *
     * @param array $specs Product specs.
     * @return int|null Score 0-100 or null if no data.
     */
    private function calculate_ride_quality(array $specs): ?int {
        $factors = [
            $this->score_suspension(
                $this->get_nested_value($specs, 'suspension.type'),
                $this->get_nested_value($specs, 'suspension.adjustable')
            ),
            $this->score_tire_type(
                $this->get_nested_value($specs, 'wheels.tire_type'),
                $this->get_nested_value($specs, 'wheels.pneumatic_type')
            ),
            $this->score_tire_size(
                $this->get_nested_value($specs, 'wheels.tire_size_front'),
                $this->get_nested_value($specs, 'wheels.tire_size_rear')
            ),
            $this->score_deck_and_handlebar(
                $this->get_nested_value($specs, 'dimensions.deck_length'),
                $this->get_nested_value($specs, 'dimensions.deck_width'),
                $this->get_nested_value($specs, 'dimensions.handlebar_width')
            ),
            $this->score_comfort_extras(
                $this->get_top_level_value($specs, 'features'),
                $this->get_nested_value($specs, 'other.footrest')
            ),
        ];

        // Filter out null scores, but keep 0 scores.
        $available = array_filter($factors, fn($f) => $f['score'] !== null && $f['max_possible'] > 0);

        if (empty($available)) {
            return null;
        }

        $total_score = array_sum(array_column($available, 'score'));
        $total_max   = array_sum(array_column($available, 'max_possible'));

        return (int) round(($total_score / $total_max) * 100);
    }

    /**
     * Score suspension quality.
     *
     * Front suspension is weighted more heavily than rear (front absorbs most bumps).
     * Front: Hydraulic +15, Spring/Fork +10, Rubber +7.
     * Rear:  Hydraulic +7, Spring/Fork +5, Rubber +3 (roughly half of front).
     * Adjustable bonus: +10.
     *
     * @param mixed $suspension_type Array of suspension types.
     * @param mixed $adjustable      Whether suspension is adjustable.
     * @return array{score: float, max_possible: int}
     */
    private function score_suspension($suspension_type, $adjustable): array {
        // Suspension is always scored, even if None (0 points).
        // Max: 15 front + 7 rear + 10 adjustable = 32.
        if (!is_array($suspension_type) || empty($suspension_type)) {
            return ['score' => 0, 'max_possible' => 32];
        }

        $types = array_map(fn($s) => strtolower((string) $s), $suspension_type);

        // Check for "None" only.
        if (count($types) === 1 && ($types[0] === 'none' || $types[0] === '')) {
            return ['score' => 0, 'max_possible' => 32];
        }

        $score = 0;

        // Front suspension scoring (full weight - absorbs most bumps).
        $front_hydraulic = $this->array_some($types, fn($t) => strpos($t, 'front') !== false && strpos($t, 'hydraulic') !== false);
        $front_spring    = $this->array_some($types, fn($t) => strpos($t, 'front') !== false && (strpos($t, 'spring') !== false || strpos($t, 'fork') !== false));
        $front_rubber    = $this->array_some($types, fn($t) => strpos($t, 'front') !== false && strpos($t, 'rubber') !== false);

        if ($front_hydraulic) {
            $score += 15;
        } elseif ($front_spring) {
            $score += 10;
        } elseif ($front_rubber) {
            $score += 7;
        }

        // Rear suspension scoring (half weight - less impact on comfort).
        $rear_hydraulic = $this->array_some($types, fn($t) => strpos($t, 'rear') !== false && strpos($t, 'hydraulic') !== false);
        $rear_spring    = $this->array_some($types, fn($t) => strpos($t, 'rear') !== false && (strpos($t, 'spring') !== false || strpos($t, 'fork') !== false));
        $rear_rubber    = $this->array_some($types, fn($t) => strpos($t, 'rear') !== false && strpos($t, 'rubber') !== false);

        if ($rear_hydraulic) {
            $score += 7;
        } elseif ($rear_spring) {
            $score += 5;
        } elseif ($rear_rubber) {
            $score += 3;
        }

        // Handle "Dual" entries (affects both front and rear).
        $dual_hydraulic = $this->array_some($types, fn($t) => strpos($t, 'dual') !== false && strpos($t, 'hydraulic') !== false);
        $dual_spring    = $this->array_some($types, fn($t) => strpos($t, 'dual') !== false && (strpos($t, 'spring') !== false || strpos($t, 'fork') !== false));
        $dual_rubber    = $this->array_some($types, fn($t) => strpos($t, 'dual') !== false && strpos($t, 'rubber') !== false);

        if ($dual_hydraulic) {
            $score = max($score, 22); // 15 front + 7 rear
        } elseif ($dual_spring) {
            $score = max($score, 15); // 10 front + 5 rear
        } elseif ($dual_rubber) {
            $score = max($score, 10); // 7 front + 3 rear
        }

        // Adjustable bonus.
        if ($adjustable === true) {
            $score += 10;
        }

        return ['score' => min(32, $score), 'max_possible' => 32];
    }

    /**
     * Score tire type for comfort.
     *
     * Pneumatic (tubed or tubeless): 20pts, Mixed/Semi: 10pts, Solid/Honeycomb: 0pts.
     * Tubeless bonus: +2pts (~10% more comfortable - can run lower pressure without pinch flats).
     *
     * @param mixed $tire_type      Tire type string.
     * @param mixed $pneumatic_type Pneumatic type string.
     * @return array{score: float, max_possible: int}
     */
    private function score_tire_type($tire_type, $pneumatic_type): array {
        $score      = 0;
        $type       = strtolower((string) ($tire_type ?? ''));
        $p_type     = strtolower((string) ($pneumatic_type ?? ''));
        $is_tubeless = strpos($type, 'tubeless') !== false || strpos($p_type, 'tubeless') !== false;

        // Check for pneumatic (including tubeless which is a type of pneumatic).
        if ($is_tubeless || (strpos($type, 'pneumatic') !== false && strpos($type, 'semi') === false)) {
            $score = 20; // Full pneumatic (tubed or tubeless).
        } elseif (strpos($type, 'mixed') !== false || strpos($type, 'semi') !== false) {
            $score = 10; // Mixed or semi-pneumatic.
        } elseif (strpos($type, 'solid') !== false || strpos($type, 'honeycomb') !== false) {
            $score = 0; // Solid/honeycomb = no comfort.
        }

        // Tubeless bonus (~10% more comfortable - can run lower pressure).
        if ($score >= 20 && $is_tubeless) {
            $score += 2;
        }

        return ['score' => min(22, $score), 'max_possible' => 22];
    }

    /**
     * Score tire size for comfort.
     *
     * 6" → 0pts, 8" → 6pts, 10" → 10pts, 12"+ → 15pts.
     *
     * @param mixed $front_size Front tire size in inches.
     * @param mixed $rear_size  Rear tire size in inches.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_tire_size($front_size, $rear_size): array {
        $sizes = array_filter(
            [$front_size, $rear_size],
            fn($s) => $s !== null && is_numeric($s) && (float) $s > 0
        );

        if (empty($sizes)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $avg_size = array_sum($sizes) / count($sizes);

        if ($avg_size <= 6) {
            $score = 0;
        } elseif ($avg_size <= 8) {
            $score = 3 + ($avg_size - 6) * 1.5; // 6-8" = 3-6 pts.
        } elseif ($avg_size <= 10) {
            $score = 6 + ($avg_size - 8) * 2; // 8-10" = 6-10 pts.
        } else {
            $score = 10 + ($avg_size - 10) * 2.5; // 10"+ = 10-15 pts.
        }

        return ['score' => min(15, max(0, $score)), 'max_possible' => 15];
    }

    /**
     * Score deck and handlebar dimensions.
     *
     * @param mixed $deck_length     Deck length in inches.
     * @param mixed $deck_width      Deck width in inches.
     * @param mixed $handlebar_width Handlebar width in inches.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_deck_and_handlebar($deck_length, $deck_width, $handlebar_width): array {
        $factors = [];

        // Deck length: 15" = 0pts, 22"+ = 4pts.
        if ($deck_length !== null && is_numeric($deck_length) && (float) $deck_length > 0) {
            $deck_length_score = min(4, max(0, ((float) $deck_length - 14) / 2));
            $factors[]         = ['score' => $deck_length_score, 'max' => 4];
        }

        // Deck width: 5" = 0pts, 8"+ = 3pts.
        if ($deck_width !== null && is_numeric($deck_width) && (float) $deck_width > 0) {
            $deck_width_score = min(3, max(0, ((float) $deck_width - 4) / 1.5));
            $factors[]        = ['score' => $deck_width_score, 'max' => 3];
        }

        // Handlebar width: 18" = 0pts, 24"+ = 3pts.
        if ($handlebar_width !== null && is_numeric($handlebar_width) && (float) $handlebar_width > 0) {
            $hb_score  = min(3, max(0, ((float) $handlebar_width - 16) / 3));
            $factors[] = ['score' => $hb_score, 'max' => 3];
        }

        if (empty($factors)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $total_score = array_sum(array_column($factors, 'score'));
        $total_max   = array_sum(array_column($factors, 'max'));

        // Scale to 10 pts max.
        return ['score' => ($total_score / $total_max) * 10, 'max_possible' => 10];
    }

    /**
     * Score comfort extras.
     *
     * Steering damper: +5pts, Footrest: +5pts.
     *
     * @param mixed $features Features array.
     * @param mixed $footrest Whether has footrest.
     * @return array{score: float, max_possible: int}
     */
    private function score_comfort_extras($features, $footrest): array {
        $score = 0;

        // Steering damper (in features array).
        if (is_array($features)) {
            $has_steering_damper = $this->array_some(
                $features,
                fn($f) => strpos(strtolower((string) $f), 'steering damper') !== false
            );
            if ($has_steering_damper) {
                $score += 5;
            }
        }

        // Footrest.
        if ($footrest === true) {
            $score += 5;
        }

        return ['score' => $score, 'max_possible' => 10];
    }

    // =========================================================================
    // Portability (100 pts max)
    // =========================================================================

    /**
     * Calculate Portability category score.
     *
     * Factors: Weight 60pts, Folded Volume 35pts, Swappable Battery 5pts.
     *
     * @param array $specs Product specs.
     * @return int|null Score 0-100 or null if no data.
     */
    private function calculate_portability(array $specs): ?int {
        $factors = [
            $this->score_weight($this->get_nested_value($specs, 'dimensions.weight')),
            $this->score_folded_volume(
                $this->get_nested_value($specs, 'dimensions.folded_length'),
                $this->get_nested_value($specs, 'dimensions.folded_width'),
                $this->get_nested_value($specs, 'dimensions.folded_height')
            ),
            $this->score_swappable_battery($this->get_top_level_value($specs, 'features')),
        ];

        // Weight is required for portability score.
        $weight_factor = $factors[0];
        if ($weight_factor['score'] === null) {
            return null;
        }

        $total_score = 0;
        $total_max   = 0;

        foreach ($factors as $factor) {
            $total_score += $factor['score'] ?? 0;
            $total_max   += $factor['max_possible'];
        }

        return (int) round(($total_score / $total_max) * 100);
    }

    /**
     * Score weight for portability (lighter is better).
     * Log scale: 25 lbs floor (ultralight), 140 lbs ceiling (beast).
     *
     * @param mixed $weight Weight in lbs.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_weight($weight): array {
        if ($weight === null || !is_numeric($weight) || (float) $weight <= 0) {
            return ['score' => null, 'max_possible' => 0];
        }

        $w = (float) $weight;

        // Cap at floor/ceiling.
        if ($w <= 25) {
            return ['score' => 60, 'max_possible' => 60];
        }
        if ($w >= 140) {
            return ['score' => 0, 'max_possible' => 60];
        }

        // Log scale: 60 * (1 - log2(weight/25) / log2(5.6))
        // 25 lbs → 60 pts, 140 lbs → 0 pts.
        $raw   = 60 * (1 - log($w / 25, 2) / log(5.6, 2));
        $score = max(0, min(60, $raw));

        return ['score' => $score, 'max_possible' => 60];
    }

    /**
     * Score folded volume (smaller is better).
     * Log scale: 5000 cu in floor (super compact), 35000 cu in ceiling (huge).
     *
     * @param mixed $length Folded length in inches.
     * @param mixed $width  Folded width in inches.
     * @param mixed $height Folded height in inches.
     * @return array{score: float, max_possible: int}
     */
    private function score_folded_volume($length, $width, $height): array {
        // All three dimensions required.
        if ($length === null || $width === null || $height === null) {
            // No folded dimensions = doesn't fold or missing data = 0 pts.
            return ['score' => 0, 'max_possible' => 35];
        }

        if (!is_numeric($length) || !is_numeric($width) || !is_numeric($height)) {
            return ['score' => 0, 'max_possible' => 35];
        }

        $l = (float) $length;
        $w = (float) $width;
        $h = (float) $height;

        if ($l <= 0 || $w <= 0 || $h <= 0) {
            return ['score' => 0, 'max_possible' => 35];
        }

        $volume = $l * $w * $h;

        // Cap at floor/ceiling.
        if ($volume <= 5000) {
            return ['score' => 35, 'max_possible' => 35];
        }
        if ($volume >= 35000) {
            return ['score' => 0, 'max_possible' => 35];
        }

        // Log scale: 35 * (1 - log2(volume/5000) / log2(7))
        // 5000 cu in → 35 pts, 35000 cu in → 0 pts.
        $raw   = 35 * (1 - log($volume / 5000, 2) / log(7, 2));
        $score = max(0, min(35, $raw));

        return ['score' => $score, 'max_possible' => 35];
    }

    /**
     * Score quick-swap/removable battery bonus.
     *
     * @param mixed $features Features array.
     * @return array{score: float, max_possible: int}
     */
    private function score_swappable_battery($features): array {
        if (!is_array($features)) {
            return ['score' => 0, 'max_possible' => 5];
        }

        $has_swappable = $this->array_some($features, function ($f) {
            $lower = strtolower((string) $f);
            return strpos($lower, 'swap') !== false ||
                   strpos($lower, 'removable battery') !== false ||
                   strpos($lower, 'detachable battery') !== false;
        });

        return ['score' => $has_swappable ? 5 : 0, 'max_possible' => 5];
    }

    // =========================================================================
    // Safety (100 pts max)
    // =========================================================================

    /**
     * Calculate Safety category score.
     *
     * Factors: Brake Adequacy 50pts, Visibility 25pts, Tire Safety 15pts, Stability 10pts.
     *
     * @param array $specs Product specs.
     * @return int|null Score 0-100 or null if missing required data.
     */
    private function calculate_safety(array $specs): ?int {
        // Top speed is required for meaningful safety scoring.
        $top_speed = $this->get_top_level_value($specs, 'manufacturer_top_speed');
        if ($top_speed === null || !is_numeric($top_speed) || (float) $top_speed <= 0) {
            return null;
        }

        $factors = [
            $this->score_brake_adequacy($specs),
            $this->score_visibility($specs),
            $this->score_tire_safety($specs),
            $this->score_stability($specs),
        ];

        $total_score = array_sum(array_column($factors, 'score'));
        $total_max   = array_sum(array_column($factors, 'max_possible'));

        return (int) round(($total_score / $total_max) * 100);
    }

    /**
     * Calculate the "safe speed" a brake configuration can handle.
     *
     * @param string|null $front_brake  Front brake type.
     * @param string|null $rear_brake   Rear brake type.
     * @param bool        $has_regen    Whether has regenerative braking.
     * @param bool        $is_dual_motor Whether has dual motors.
     * @return int Safe speed in mph.
     */
    private function calculate_brake_safe_speed(?string $front_brake, ?string $rear_brake, bool $has_regen, bool $is_dual_motor): int {
        $front = $this->parse_brake_type($front_brake);
        $rear  = $this->parse_brake_type($rear_brake);

        // No front physical brake = unsafe.
        if (in_array($front, ['none', 'foot', 'regen'], true)) {
            return 0;
        }

        $safe_speed = 0;

        // Dual hydraulic disc - best possible, safe at any speed.
        if ($front === 'hydraulic' && $rear === 'hydraulic') {
            $safe_speed = $has_regen ? 90 : 80;
        }
        // Dual mechanical disc.
        elseif ($front === 'mechanical' && $rear === 'mechanical') {
            $safe_speed = $has_regen ? 40 : 35;
        }
        // Front hydraulic + rear drum/mechanical.
        elseif ($front === 'hydraulic' && in_array($rear, ['drum', 'mechanical'], true)) {
            $safe_speed = $has_regen ? 38 : 32;
        }
        // Front mechanical + rear drum.
        elseif ($front === 'mechanical' && $rear === 'drum') {
            $safe_speed = $has_regen ? 35 : 28;
        }
        // Dual drum.
        elseif ($front === 'drum' && $rear === 'drum') {
            $safe_speed = $has_regen ? 28 : 22;
        }
        // Front hydraulic + rear regen/foot/none.
        elseif ($front === 'hydraulic' && in_array($rear, ['regen', 'foot', 'none'], true)) {
            $safe_speed = $has_regen ? 28 : 22;
        }
        // Front mechanical + rear regen/foot/none.
        elseif ($front === 'mechanical' && in_array($rear, ['regen', 'foot', 'none'], true)) {
            $safe_speed = $has_regen ? 25 : 20;
        }
        // Front drum + rear regen/foot/none.
        elseif ($front === 'drum' && in_array($rear, ['regen', 'foot', 'none'], true)) {
            $safe_speed = $has_regen ? 20 : 15;
        }
        // Front drum + rear mechanical/hydraulic (odd config).
        elseif ($front === 'drum' && in_array($rear, ['mechanical', 'hydraulic'], true)) {
            $safe_speed = $has_regen ? 30 : 25;
        }
        // Fallback.
        else {
            $safe_speed = $has_regen ? 15 : 10;
        }

        // Dual motor regen bonus (+3 mph).
        if ($has_regen && $is_dual_motor) {
            $safe_speed += 3;
        }

        return $safe_speed;
    }

    /**
     * Parse brake type string.
     *
     * @param string|null $brake_str Brake type string.
     * @return string Parsed type: 'hydraulic', 'mechanical', 'drum', 'foot', 'regen', or 'none'.
     */
    private function parse_brake_type(?string $brake_str): string {
        $type = strtolower((string) ($brake_str ?? ''));

        if (strpos($type, 'hydraulic') !== false) {
            return 'hydraulic';
        }
        if (strpos($type, 'mechanical') !== false || (strpos($type, 'disc') !== false && strpos($type, 'hydraulic') === false)) {
            return 'mechanical';
        }
        if (strpos($type, 'drum') !== false) {
            return 'drum';
        }
        if (strpos($type, 'foot') !== false) {
            return 'foot';
        }
        if (strpos($type, 'regen') !== false) {
            return 'regen';
        }
        if ($type === '' || $type === 'none') {
            return 'none';
        }

        return 'none';
    }

    /**
     * Score brake adequacy (50 pts max).
     *
     * Based on brake safe speed relative to scooter's top speed.
     *
     * @param array $specs Product specs.
     * @return array{score: float, max_possible: int}
     */
    private function score_brake_adequacy(array $specs): array {
        $top_speed = (float) ($this->get_top_level_value($specs, 'manufacturer_top_speed') ?? 0);

        if ($top_speed <= 0) {
            return ['score' => 0, 'max_possible' => 50];
        }

        $front_brake = (string) ($this->get_nested_value($specs, 'brakes.front') ?? '');
        $rear_brake  = (string) ($this->get_nested_value($specs, 'brakes.rear') ?? '');
        $has_regen   = $this->get_nested_value($specs, 'brakes.regenerative') === true;

        $motor_position = $this->get_nested_value($specs, 'motor.motor_position');
        $is_dual_motor  = $motor_position && strpos(strtolower((string) $motor_position), 'dual') !== false;

        $brake_safe_speed = $this->calculate_brake_safe_speed($front_brake, $rear_brake, $has_regen, $is_dual_motor);

        if ($brake_safe_speed === 0) {
            return ['score' => 5, 'max_possible' => 50];
        }

        $ratio = $brake_safe_speed / $top_speed;

        if ($ratio >= 1.3) {
            $score = 50;
        } elseif ($ratio >= 1.0) {
            $score = 45;
        } elseif ($ratio >= 0.8) {
            $score = 30;
        } elseif ($ratio >= 0.6) {
            $score = 15;
        } else {
            $score = 5;
        }

        return ['score' => $score, 'max_possible' => 50];
    }

    /**
     * Score visibility/lights (25 pts max).
     *
     * @param array $specs Product specs.
     * @return array{score: float, max_possible: int}
     */
    private function score_visibility(array $specs): array {
        $score = 0;

        // Check lights array.
        $lights    = $this->get_nested_value($specs, 'lighting.lights');
        $has_front = false;
        $has_rear  = false;

        if (is_array($lights)) {
            $lights_lower = array_map(fn($l) => strtolower((string) $l), $lights);
            $has_front    = $this->array_some($lights_lower, fn($l) => strpos($l, 'front') !== false || strpos($l, 'headlight') !== false);
            $has_rear     = $this->array_some($lights_lower, fn($l) => strpos($l, 'rear') !== false || strpos($l, 'tail') !== false || strpos($l, 'brake') !== false);
        }

        // Score based on light configuration.
        if ($has_front && $has_rear) {
            $score = 20; // Full visibility.
        } elseif ($has_rear) {
            $score = 10; // Rear more critical (cars behind you).
        } elseif ($has_front) {
            $score = 8; // Front only.
        } else {
            $score = 0; // No lights = dangerous at night.
        }

        // Turn signals bonus.
        $has_turn_signals = $this->get_nested_value($specs, 'lighting.turn_signals') === true;
        if ($has_turn_signals) {
            $score += 5;
        }

        return ['score' => min(25, $score), 'max_possible' => 25];
    }

    /**
     * Score tire safety (40 pts max).
     *
     * Tire type: 25 pts (pneumatic safest, solid unsafe)
     * Tire size: 15 pts (larger = safer, absolute size)
     *
     * @param array $specs Product specs.
     * @return array{score: float, max_possible: int}
     */
    private function score_tire_safety(array $specs): array {
        $tire_type  = strtolower((string) ($this->get_nested_value($specs, 'wheels.tire_type') ?? ''));
        $front_size = $this->get_nested_value($specs, 'wheels.tire_size_front');
        $rear_size  = $this->get_nested_value($specs, 'wheels.tire_size_rear');

        $score = 0;

        // Tire type scoring (25 pts max).
        // Check for tubeless in tire_type (tubeless is a type of pneumatic).
        $is_tubeless       = strpos($tire_type, 'tubeless') !== false;
        $is_solid          = strpos($tire_type, 'solid') !== false || strpos($tire_type, 'honeycomb') !== false;
        $is_pneumatic      = $is_tubeless || (strpos($tire_type, 'pneumatic') !== false && strpos($tire_type, 'semi') === false);
        $is_semi_pneumatic = strpos($tire_type, 'semi') !== false || strpos($tire_type, 'mixed') !== false;

        if ($is_pneumatic) {
            $score += 25; // Pneumatic (tubed or tubeless) = safest.
        } elseif ($is_semi_pneumatic) {
            $score += 12; // Semi-pneumatic = moderate safety.
        } elseif ($is_solid) {
            $score += 0; // Solid = unsafe at any speed.
        } else {
            // Unknown tire type - assume semi-pneumatic level.
            $score += 12;
        }

        // Tire size scoring (15 pts max) - absolute size, not ratio.
        // Larger tires = more stable, better obstacle handling.
        $sizes = array_filter(
            [$front_size, $rear_size],
            fn($s) => $s !== null && is_numeric($s) && (float) $s > 0
        );

        if (!empty($sizes)) {
            $avg_size = array_sum(array_map('floatval', $sizes)) / count($sizes);

            if ($avg_size >= 10) {
                $score += 15; // 10"+ = excellent.
            } elseif ($avg_size >= 9) {
                $score += 11; // 9-10" = very good.
            } elseif ($avg_size >= 8) {
                $score += 8; // 8-9" = good.
            } elseif ($avg_size >= 7) {
                $score += 5; // 7-8" = adequate.
            } else {
                $score += 0; // <7" = small, less safe.
            }
        }

        return ['score' => $score, 'max_possible' => 40];
    }

    /**
     * Score stability from dimensions (10 pts max).
     *
     * @param array $specs Product specs.
     * @return array{score: float, max_possible: int}
     */
    private function score_stability(array $specs): array {
        $score = 0;

        // Handlebar width (5 pts max).
        $handlebar_width = $this->get_nested_value($specs, 'dimensions.handlebar_width');
        if ($handlebar_width !== null && is_numeric($handlebar_width) && (float) $handlebar_width > 0) {
            $hb = (float) $handlebar_width;
            if ($hb >= 24) {
                $score += 5;
            } elseif ($hb >= 21) {
                $score += 4;
            } elseif ($hb >= 18) {
                $score += 3;
            } elseif ($hb >= 15) {
                $score += 2;
            } else {
                $score += 1;
            }
        }

        // Deck width (3 pts max).
        $deck_width = $this->get_nested_value($specs, 'dimensions.deck_width');
        if ($deck_width !== null && is_numeric($deck_width) && (float) $deck_width > 0) {
            $dw = (float) $deck_width;
            if ($dw >= 7) {
                $score += 3;
            } elseif ($dw >= 5.5) {
                $score += 2;
            } else {
                $score += 1;
            }
        }

        // Deck length (2 pts max).
        $deck_length = $this->get_nested_value($specs, 'dimensions.deck_length');
        if ($deck_length !== null && is_numeric($deck_length) && (float) $deck_length > 0) {
            $dl = (float) $deck_length;
            if ($dl >= 20) {
                $score += 2;
            } elseif ($dl >= 16) {
                $score += 1;
            }
        }

        return ['score' => min(10, $score), 'max_possible' => 10];
    }

    // =========================================================================
    // Features (100 pts max)
    // =========================================================================

    /**
     * Calculate Features category score.
     *
     * Counts features from features array + separate booleans.
     *
     * @param array $specs Product specs.
     * @return int Score 0-100.
     */
    private function calculate_features(array $specs): int {
        $feature_count = 0;

        // Count items in features array.
        $features_array = $this->get_top_level_value($specs, 'features');
        if (is_array($features_array)) {
            $feature_count += count($features_array);
        }

        // Add separate boolean features.
        if ($this->get_nested_value($specs, 'brakes.regenerative') === true) {
            $feature_count++;
        }

        $lights = $this->get_nested_value($specs, 'lighting.lights');
        if (is_array($lights) && !empty($lights)) {
            $feature_count++;
        }

        if ($this->get_nested_value($specs, 'other.kickstand') === true) {
            $feature_count++;
        }

        if ($this->get_nested_value($specs, 'other.footrest') === true) {
            $feature_count++;
        }

        if ($this->get_nested_value($specs, 'suspension.adjustable') === true) {
            $feature_count++;
        }

        if ($this->get_nested_value($specs, 'dimensions.foldable_handlebars') === true) {
            $feature_count++;
        }

        // Scale to 0-100 (17 features = 100 pts).
        return min(100, (int) round(($feature_count / 17) * 100));
    }

    // =========================================================================
    // Maintenance (100 pts max)
    // =========================================================================

    /**
     * Calculate Maintenance category score.
     *
     * Factors: Tire Type 45pts, Self-Healing 5pts, Brakes 25pts, Regen 5pts, IP 20pts.
     *
     * @param array $specs Product specs.
     * @return int|null Score 0-100 or null if no data.
     */
    private function calculate_maintenance(array $specs): ?int {
        $factors = [
            $this->score_maintenance_tire_type($this->get_nested_value($specs, 'wheels.tire_type')),
            $this->score_self_healing($this->get_nested_value($specs, 'wheels.self_healing')),
            $this->score_maintenance_brakes(
                $this->get_nested_value($specs, 'brakes.front'),
                $this->get_nested_value($specs, 'brakes.rear')
            ),
            $this->score_regen_braking($this->get_nested_value($specs, 'brakes.regenerative')),
            $this->score_maintenance_ip($this->get_nested_value($specs, 'other.ip_rating')),
        ];

        $total_score = array_sum(array_column($factors, 'score'));
        $total_max   = array_sum(array_column($factors, 'max_possible'));

        if ($total_max === 0) {
            return null;
        }

        return (int) round(($total_score / $total_max) * 100);
    }

    /**
     * Score tire type for maintenance (less flats = better).
     *
     * Note: This is inverse of ride quality - solid tires score HIGH here.
     * Scores: Solid 50, Mixed 30, Tubeless 10, Tubed 0.
     *
     * @param mixed $tire_type Tire type string.
     * @return array{score: float, max_possible: int}
     */
    private function score_maintenance_tire_type($tire_type): array {
        $type = strtolower((string) ($tire_type ?? ''));

        $score = 0; // Default to tubed (most common, worst maintenance).

        if (strpos($type, 'solid') !== false || strpos($type, 'honeycomb') !== false) {
            $score = 50; // Zero flats ever.
        } elseif (strpos($type, 'mixed') !== false || strpos($type, 'semi') !== false) {
            $score = 30; // Semi-pneumatic.
        } elseif (strpos($type, 'tubeless') !== false) {
            $score = 10; // Rare flats, easy plug fix.
        }
        // Tubed pneumatic = 0 (default).

        return ['score' => $score, 'max_possible' => 50];
    }

    /**
     * Score self-healing tires bonus.
     *
     * @param mixed $self_healing Whether tires are self-healing.
     * @return array{score: float, max_possible: int}
     */
    private function score_self_healing($self_healing): array {
        return ['score' => $self_healing === true ? 20 : 0, 'max_possible' => 20];
    }

    /**
     * Score brake type for maintenance (average of front + rear).
     *
     * @param mixed $front_brake Front brake type.
     * @param mixed $rear_brake  Rear brake type.
     * @return array{score: float, max_possible: int}
     */
    private function score_maintenance_brakes($front_brake, $rear_brake): array {
        $front_score = $this->score_single_brake_maintenance($front_brake);
        $rear_score  = $this->score_single_brake_maintenance($rear_brake);

        // Average front and rear.
        $avg_score = ($front_score + $rear_score) / 2;

        return ['score' => $avg_score, 'max_possible' => 25];
    }

    /**
     * Score a single brake type for maintenance.
     *
     * @param mixed $brake_type Brake type string.
     * @return float Score 0-25.
     */
    private function score_single_brake_maintenance($brake_type): float {
        $type = strtolower((string) ($brake_type ?? ''));

        if (strpos($type, 'drum') !== false) {
            return 25; // Sealed, set and forget.
        }
        if (strpos($type, 'hydraulic') !== false || strpos($type, 'mechanical') !== false || strpos($type, 'disc') !== false) {
            return 15; // Both disc types need maintenance.
        }
        if (strpos($type, 'foot') !== false || $type === 'none' || $type === '') {
            return 10; // Minimal maintenance.
        }

        return 10; // Unknown defaults to low.
    }

    /**
     * Score regenerative braking bonus.
     *
     * Reduces brake pad wear.
     *
     * @param mixed $regenerative Whether has regen braking.
     * @return array{score: float, max_possible: int}
     */
    private function score_regen_braking($regenerative): array {
        return ['score' => $regenerative === true ? 5 : 0, 'max_possible' => 5];
    }

    /**
     * Score IP rating for maintenance (water resistance).
     *
     * @param mixed $ip_rating IP rating like 'IP55', 'IPX5', etc.
     * @return array{score: float, max_possible: int}
     */
    private function score_maintenance_ip($ip_rating): array {
        $rating = strtoupper((string) ($ip_rating ?? ''));

        // Extract water rating.
        $water_rating = 0;

        if (strpos($rating, '7') !== false || strpos($rating, '67') !== false) {
            $water_rating = 7;
        } elseif (strpos($rating, '6') !== false || strpos($rating, '66') !== false || strpos($rating, '65') !== false) {
            $water_rating = 6;
        } elseif (strpos($rating, '55') !== false || $rating === 'IPX5') {
            $water_rating = 5;
        } elseif (strpos($rating, '54') !== false || $rating === 'IPX4') {
            $water_rating = 4;
        }

        // Score based on water rating.
        $score = 0;
        switch ($water_rating) {
            case 7:
                $score = 20; // Immersion - bulletproof.
                break;
            case 6:
                $score = 16; // Powerful jets.
                break;
            case 5:
                $score = 12; // Water jets.
                break;
            case 4:
                $score = 8; // Splashing.
                break;
            default:
                $score = 0; // None/Unknown.
        }

        return ['score' => $score, 'max_possible' => 20];
    }

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Calculate category score from factors array.
     *
     * @param array $factors Array of factor results with 'score' and 'max_possible'.
     * @return int|null Score 0-100 or null if no data.
     */
    private function calculate_category_score(array $factors): ?int {
        $available = array_filter($factors, fn($f) => $f['score'] !== null);

        if (empty($available)) {
            return null;
        }

        $total_score = array_sum(array_column($available, 'score'));
        $total_max   = array_sum(array_column($available, 'max_possible'));

        return (int) round(($total_score / $total_max) * 100);
    }

    /**
     * Product type group key for nested ACF structure.
     *
     * @var string|null
     */
    private ?string $product_group_key = null;

    /**
     * Set the product group key for nested ACF structure lookups.
     *
     * @param string $product_type The product type.
     * @return void
     */
    private function set_product_group_key(string $product_type): void {
        $group_keys = [
            'Electric Scooter'    => 'e-scooters',
            'Electric Skateboard' => 'e-skateboards',
            'Electric Unicycle'   => 'e-unicycles',
            'Hoverboard'          => 'hoverboards',
        ];

        $this->product_group_key = $group_keys[$product_type] ?? null;
    }

    /**
     * Get a value from specs array, handling both flat and nested structures.
     *
     * For e-scooters, ACF stores data under an 'e-scooters' group. This method
     * checks multiple locations in order:
     * 1. Direct nested path (e.g., 'motor.power_nominal' -> $specs['motor']['power_nominal'])
     * 2. Product-group-prefixed path (e.g., $specs['e-scooters']['motor']['power_nominal'])
     * 3. Flat fallback (e.g., $specs['nominal_motor_wattage'])
     *
     * @param array  $array      The array to search.
     * @param string $nested_path The dot-separated path for nested structure (e.g., 'motor.power_nominal').
     * @param string $flat_key   Optional flat key to check first (e.g., 'nominal_motor_wattage').
     * @return mixed The value or null if not found.
     */
    private function get_nested_value(array $array, string $nested_path, string $flat_key = '') {
        // Try flat key first if provided.
        if ($flat_key !== '' && array_key_exists($flat_key, $array)) {
            return $array[$flat_key];
        }

        // Try direct nested path first (for pre-flattened data).
        $value = $this->traverse_path($array, $nested_path);
        if ($value !== null) {
            return $value;
        }

        // Try with product group prefix (for raw ACF data with e-scooters/etc wrapper).
        if ($this->product_group_key !== null) {
            $prefixed_path = $this->product_group_key . '.' . $nested_path;
            $value = $this->traverse_path($array, $prefixed_path);
            if ($value !== null) {
                return $value;
            }
        }

        // Try flat fallback based on nested path (e.g., 'motor.power_nominal' -> 'nominal_motor_wattage').
        return $this->get_flat_fallback($array, $nested_path);
    }

    /**
     * Traverse a dot-separated path in an array.
     *
     * @param array  $array The array to traverse.
     * @param string $path  The dot-separated path.
     * @return mixed The value or null if not found.
     */
    private function traverse_path(array $array, string $path) {
        $keys  = explode('.', $path);
        $value = $array;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * Get a top-level field value, checking both flat location and under product group.
     *
     * Some fields like 'manufacturer_top_speed' and 'features' are stored at the root level
     * in ACF, but may sometimes be nested under the product type group.
     *
     * @param array  $array The specs array.
     * @param string $key   The field key to look up.
     * @return mixed The value or null if not found.
     */
    private function get_top_level_value(array $array, string $key) {
        // Try flat/root location first.
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        // Try under product group (e.g., e-scooters.features).
        if ($this->product_group_key !== null && isset($array[$this->product_group_key][$key])) {
            return $array[$this->product_group_key][$key];
        }

        return null;
    }

    /**
     * Get flat field value based on nested path mapping.
     *
     * Maps nested paths to flat ACF field names for e-scooter backward compatibility.
     *
     * @param array  $array       The specs array.
     * @param string $nested_path The nested path to map.
     * @return mixed The value or null if not found.
     */
    private function get_flat_fallback(array $array, string $nested_path) {
        // Map nested paths to flat ACF field names for e-scooters.
        static $flat_map = [
            // Motor
            'motor.power_nominal'    => 'nominal_motor_wattage',
            'motor.power_peak'       => 'peak_motor_wattage',
            'motor.voltage'          => 'motor_voltage',
            'motor.motor_position'   => 'motor_configuration',
            // Battery
            'battery.capacity'       => 'battery_capacity',
            'battery.voltage'        => 'battery_voltage',
            'battery.charging_time'  => 'charging_time',
            // Wheels
            'wheels.tire_type'       => 'tire_type',
            'wheels.pneumatic_type'  => 'pneumatic_type',
            'wheels.tire_size_front' => 'tire_size_front',
            'wheels.tire_size_rear'  => 'tire_size_rear',
            'wheels.self_healing'    => 'self_healing_tires',
            // Dimensions
            'dimensions.weight'         => 'weight',
            'dimensions.folded_length'  => 'folded_length',
            'dimensions.folded_width'   => 'folded_width',
            'dimensions.folded_height'  => 'folded_height',
            'dimensions.deck_length'    => 'deck_length',
            'dimensions.deck_width'     => 'deck_width',
            'dimensions.handlebar_width'     => 'handlebar_width',
            'dimensions.foldable_handlebars' => 'foldable_handlebars',
            // Brakes
            'brakes.front'        => 'front_brake',
            'brakes.rear'         => 'rear_brake',
            'brakes.regenerative' => 'regenerative_braking',
            // Suspension
            'suspension.type'       => 'suspension_type',
            'suspension.adjustable' => 'adjustable_suspension',
            // Lighting
            'lighting.lights'       => 'lights',
            'lighting.turn_signals' => 'turn_signals',
            // Other
            'other.ip_rating'  => 'ip_rating',
            'other.footrest'   => 'footrest',
            'other.kickstand'  => 'kickstand',
        ];

        $flat_key = $flat_map[$nested_path] ?? null;

        if ($flat_key !== null && array_key_exists($flat_key, $array)) {
            return $array[$flat_key];
        }

        return null;
    }

    /**
     * Check if any element in array satisfies the callback.
     *
     * @param array    $array    The array to check.
     * @param callable $callback The callback function.
     * @return bool True if any element satisfies the callback.
     */
    private function array_some(array $array, callable $callback): bool {
        foreach ($array as $element) {
            if ($callback($element)) {
                return true;
            }
        }
        return false;
    }
}
