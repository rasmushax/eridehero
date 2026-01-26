<?php
/**
 * E-Scooter Scorer - calculates scores across 7 categories.
 *
 * Categories:
 * - Motor Performance (20%): Power, voltage, top speed, dual motor
 * - Range & Battery (20%): Capacity, voltage, charge time
 * - Ride Quality (20%): Suspension, tire type/size, deck, comfort extras
 * - Portability (15%): Weight, folded volume, swappable battery
 * - Safety (10%): Brake adequacy, visibility, tire safety, stability
 * - Features (10%): Feature count
 * - Maintenance (5%): Tire type, brakes, IP rating
 *
 * @package ERH\Scoring
 */

declare(strict_types=1);

namespace ERH\Scoring;

/**
 * Calculate absolute product scores for e-scooters.
 */
class EscooterScorer {

    use ScoringHelpers;
    use SpecsAccessor;

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
     * Flat field mapping for e-scooter ACF fields.
     *
     * @var array<string, string>
     */
    private const FLAT_MAP = [
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
        'dimensions.weight'              => 'weight',
        'dimensions.folded_length'       => 'folded_length',
        'dimensions.folded_width'        => 'folded_width',
        'dimensions.folded_height'       => 'folded_height',
        'dimensions.deck_length'         => 'deck_length',
        'dimensions.deck_width'          => 'deck_width',
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
        'other.ip_rating' => 'ip_rating',
        'other.footrest'  => 'footrest',
        'other.kickstand' => 'kickstand',
    ];

    /**
     * Calculate all category scores for an e-scooter.
     *
     * @param array $specs The product specs array.
     * @return array Category scores including overall.
     */
    public function calculate(array $specs): array {
        $this->set_product_group_key('Electric Scooter');

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

    /**
     * Get flat fallback value for e-scooter fields.
     *
     * @param array  $array       The specs array.
     * @param string $nested_path The nested path to map.
     * @return mixed The value or null if not found.
     */
    protected function get_flat_fallback(array $array, string $nested_path) {
        $flat_key = self::FLAT_MAP[$nested_path] ?? null;

        if ($flat_key !== null && array_key_exists($flat_key, $array)) {
            return $array[$flat_key];
        }

        return null;
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

        return $this->log_scale($avg_power, 400, 8000, 45);
    }

    /**
     * Score motor voltage.
     * Log scale: 18V floor, 84V ceiling.
     */
    private function score_motor_voltage($voltage): array {
        return $this->log_scale($voltage, 18, 84, 20);
    }

    /**
     * Score top speed.
     * Log scale: 8 mph floor, 60 mph ceiling.
     */
    private function score_top_speed($speed_mph): array {
        return $this->log_scale($speed_mph, 8, 60, 25);
    }

    /**
     * Score dual motor bonus.
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
     */
    private function score_battery_capacity($wh): array {
        return $this->log_scale($wh, 150, 3000, 70);
    }

    /**
     * Score battery voltage.
     * Log scale: 18V floor, 84V ceiling.
     */
    private function score_battery_voltage($voltage): array {
        return $this->log_scale($voltage, 18, 84, 20);
    }

    /**
     * Score charge time (lower is better).
     * Inverse log scale: 1.5h floor, 15h ceiling.
     */
    private function score_charge_time($hours): array {
        return $this->log_scale($hours, 1.5, 15, 10, true);
    }

    // =========================================================================
    // Ride Quality (100 pts max)
    // =========================================================================

    /**
     * Calculate Ride Quality category score.
     *
     * Factors: Suspension 32pts, Tire Type 22pts, Tire Size 15pts,
     * Deck/Handlebar 10pts, Comfort Extras 10pts.
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
     * Front is weighted more heavily than rear (absorbs most bumps).
     * Front: Hydraulic +15, Spring/Fork +10, Rubber +7.
     * Rear:  Hydraulic +7, Spring/Fork +5, Rubber +3.
     * Adjustable bonus: +10.
     */
    private function score_suspension($suspension_type, $adjustable): array {
        // Suspension is always scored, even if None (0 points).
        if (!is_array($suspension_type) || empty($suspension_type)) {
            return ['score' => 0, 'max_possible' => 32];
        }

        $types = array_map(fn($s) => strtolower((string) $s), $suspension_type);

        // Check for "None" only.
        if (count($types) === 1 && ($types[0] === 'none' || $types[0] === '')) {
            return ['score' => 0, 'max_possible' => 32];
        }

        $score = 0;

        // Front suspension scoring.
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

        // Rear suspension scoring.
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

        // Handle "Dual" entries.
        $dual_hydraulic = $this->array_some($types, fn($t) => strpos($t, 'dual') !== false && strpos($t, 'hydraulic') !== false);
        $dual_spring    = $this->array_some($types, fn($t) => strpos($t, 'dual') !== false && (strpos($t, 'spring') !== false || strpos($t, 'fork') !== false));
        $dual_rubber    = $this->array_some($types, fn($t) => strpos($t, 'dual') !== false && strpos($t, 'rubber') !== false);

        if ($dual_hydraulic) {
            $score = max($score, 22);
        } elseif ($dual_spring) {
            $score = max($score, 15);
        } elseif ($dual_rubber) {
            $score = max($score, 10);
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
     * Pneumatic: 20pts, Mixed/Semi: 10pts, Solid: 0pts.
     * Tubeless bonus: +2pts.
     */
    private function score_tire_type($tire_type, $pneumatic_type): array {
        $score      = 0;
        $type       = strtolower((string) ($tire_type ?? ''));
        $p_type     = strtolower((string) ($pneumatic_type ?? ''));
        $is_tubeless = strpos($type, 'tubeless') !== false || strpos($p_type, 'tubeless') !== false;

        if ($is_tubeless || (strpos($type, 'pneumatic') !== false && strpos($type, 'semi') === false)) {
            $score = 20;
        } elseif (strpos($type, 'mixed') !== false || strpos($type, 'semi') !== false) {
            $score = 10;
        } elseif (strpos($type, 'solid') !== false || strpos($type, 'honeycomb') !== false) {
            $score = 0;
        }

        // Tubeless bonus.
        if ($score >= 20 && $is_tubeless) {
            $score += 2;
        }

        return ['score' => min(22, $score), 'max_possible' => 22];
    }

    /**
     * Score tire size for comfort.
     *
     * 6" → 0pts, 8" → 6pts, 10" → 10pts, 12"+ → 15pts.
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
            $score = 3 + ($avg_size - 6) * 1.5;
        } elseif ($avg_size <= 10) {
            $score = 6 + ($avg_size - 8) * 2;
        } else {
            $score = 10 + ($avg_size - 10) * 2.5;
        }

        return ['score' => min(15, max(0, $score)), 'max_possible' => 15];
    }

    /**
     * Score deck and handlebar dimensions.
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

        return ['score' => ($total_score / $total_max) * 10, 'max_possible' => 10];
    }

    /**
     * Score comfort extras.
     *
     * Steering damper: +5pts, Footrest: +5pts.
     */
    private function score_comfort_extras($features, $footrest): array {
        $score = 0;

        if (is_array($features)) {
            $has_steering_damper = $this->array_some(
                $features,
                fn($f) => strpos(strtolower((string) $f), 'steering damper') !== false
            );
            if ($has_steering_damper) {
                $score += 5;
            }
        }

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
     */
    private function score_weight($weight): array {
        return $this->log_scale($weight, 25, 140, 60, true);
    }

    /**
     * Score folded volume (smaller is better).
     * Log scale: 5000 cu in floor, 35000 cu in ceiling.
     */
    private function score_folded_volume($length, $width, $height): array {
        // All three dimensions required.
        if ($length === null || $width === null || $height === null) {
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

        return $this->log_scale($volume, 5000, 35000, 35, true);
    }

    /**
     * Score quick-swap/removable battery bonus.
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
     * Factors: Brake Adequacy 50pts, Visibility 25pts, Tire Safety 40pts, Stability 10pts.
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
     */
    private function calculate_brake_safe_speed(?string $front_brake, ?string $rear_brake, bool $has_regen, bool $is_dual_motor): int {
        $front = $this->parse_brake_type($front_brake);
        $rear  = $this->parse_brake_type($rear_brake);

        // No front physical brake = unsafe.
        if (in_array($front, ['none', 'foot', 'regen'], true)) {
            return 0;
        }

        $safe_speed = 0;

        // Dual hydraulic disc - best possible.
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
        // Front drum + rear mechanical/hydraulic.
        elseif ($front === 'drum' && in_array($rear, ['mechanical', 'hydraulic'], true)) {
            $safe_speed = $has_regen ? 30 : 25;
        }
        // Fallback.
        else {
            $safe_speed = $has_regen ? 15 : 10;
        }

        // Dual motor regen bonus.
        if ($has_regen && $is_dual_motor) {
            $safe_speed += 3;
        }

        return $safe_speed;
    }

    /**
     * Parse brake type string.
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
     */
    private function score_visibility(array $specs): array {
        $score = 0;

        $lights    = $this->get_nested_value($specs, 'lighting.lights');
        $has_front = false;
        $has_rear  = false;

        if (is_array($lights)) {
            $lights_lower = array_map(fn($l) => strtolower((string) $l), $lights);
            $has_front    = $this->array_some($lights_lower, fn($l) => strpos($l, 'front') !== false || strpos($l, 'headlight') !== false);
            $has_rear     = $this->array_some($lights_lower, fn($l) => strpos($l, 'rear') !== false || strpos($l, 'tail') !== false || strpos($l, 'brake') !== false);
        }

        if ($has_front && $has_rear) {
            $score = 20;
        } elseif ($has_rear) {
            $score = 10;
        } elseif ($has_front) {
            $score = 8;
        } else {
            $score = 0;
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
     */
    private function score_tire_safety(array $specs): array {
        $tire_type  = strtolower((string) ($this->get_nested_value($specs, 'wheels.tire_type') ?? ''));
        $front_size = $this->get_nested_value($specs, 'wheels.tire_size_front');
        $rear_size  = $this->get_nested_value($specs, 'wheels.tire_size_rear');

        $score = 0;

        // Tire type scoring (25 pts max).
        $is_tubeless       = strpos($tire_type, 'tubeless') !== false;
        $is_solid          = strpos($tire_type, 'solid') !== false || strpos($tire_type, 'honeycomb') !== false;
        $is_pneumatic      = $is_tubeless || (strpos($tire_type, 'pneumatic') !== false && strpos($tire_type, 'semi') === false);
        $is_semi_pneumatic = strpos($tire_type, 'semi') !== false || strpos($tire_type, 'mixed') !== false;

        if ($is_pneumatic) {
            $score += 25;
        } elseif ($is_semi_pneumatic) {
            $score += 12;
        } elseif ($is_solid) {
            $score += 0;
        } else {
            $score += 12;
        }

        // Tire size scoring (15 pts max).
        $sizes = array_filter(
            [$front_size, $rear_size],
            fn($s) => $s !== null && is_numeric($s) && (float) $s > 0
        );

        if (!empty($sizes)) {
            $avg_size = array_sum(array_map('floatval', $sizes)) / count($sizes);

            if ($avg_size >= 10) {
                $score += 15;
            } elseif ($avg_size >= 9) {
                $score += 11;
            } elseif ($avg_size >= 8) {
                $score += 8;
            } elseif ($avg_size >= 7) {
                $score += 5;
            } else {
                $score += 0;
            }
        }

        return ['score' => $score, 'max_possible' => 40];
    }

    /**
     * Score stability from dimensions (10 pts max).
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
     * Factors: Tire Type 50pts, Self-Healing 20pts, Brakes 25pts, Regen 5pts, IP 20pts.
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
     * Note: Inverse of ride quality - solid tires score HIGH here.
     */
    private function score_maintenance_tire_type($tire_type): array {
        $type = strtolower((string) ($tire_type ?? ''));

        $score = 0;

        if (strpos($type, 'solid') !== false || strpos($type, 'honeycomb') !== false) {
            $score = 50;
        } elseif (strpos($type, 'mixed') !== false || strpos($type, 'semi') !== false) {
            $score = 30;
        } elseif (strpos($type, 'tubeless') !== false) {
            $score = 10;
        }

        return ['score' => $score, 'max_possible' => 50];
    }

    /**
     * Score self-healing tires bonus.
     */
    private function score_self_healing($self_healing): array {
        return ['score' => $self_healing === true ? 20 : 0, 'max_possible' => 20];
    }

    /**
     * Score brake type for maintenance.
     */
    private function score_maintenance_brakes($front_brake, $rear_brake): array {
        $front_score = $this->score_single_brake_maintenance($front_brake);
        $rear_score  = $this->score_single_brake_maintenance($rear_brake);

        $avg_score = ($front_score + $rear_score) / 2;

        return ['score' => $avg_score, 'max_possible' => 25];
    }

    /**
     * Score a single brake type for maintenance.
     */
    private function score_single_brake_maintenance($brake_type): float {
        $type = strtolower((string) ($brake_type ?? ''));

        if (strpos($type, 'drum') !== false) {
            return 25;
        }
        if (strpos($type, 'hydraulic') !== false || strpos($type, 'mechanical') !== false || strpos($type, 'disc') !== false) {
            return 15;
        }
        if (strpos($type, 'foot') !== false || $type === 'none' || $type === '') {
            return 10;
        }

        return 10;
    }

    /**
     * Score regenerative braking bonus.
     */
    private function score_regen_braking($regenerative): array {
        return ['score' => $regenerative === true ? 5 : 0, 'max_possible' => 5];
    }

    /**
     * Score IP rating for maintenance.
     */
    private function score_maintenance_ip($ip_rating): array {
        $rating = strtoupper((string) ($ip_rating ?? ''));

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

        $score = 0;
        switch ($water_rating) {
            case 7:
                $score = 20;
                break;
            case 6:
                $score = 16;
                break;
            case 5:
                $score = 12;
                break;
            case 4:
                $score = 8;
                break;
            default:
                $score = 0;
        }

        return ['score' => $score, 'max_possible' => 20];
    }
}
