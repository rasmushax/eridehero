<?php
/**
 * EUC (Electric Unicycle) Scorer - calculates scores across 6 categories.
 *
 * Categories:
 * - Motor Performance (20%): Nominal power, peak power, top speed, voltage, hollow motor, torque
 * - Range & Battery (25%): Battery capacity (Wh), charge time, dual charging, fast charger, cell brand
 * - Ride Quality (20%): Suspension type/travel/adjustable, tire size/width, pedal height, pedals, self-healing
 * - Safety (15%): Tiltback margin, IP rating, lighting, lift sensor, max load
 * - Portability (12%): Weight, trolley handle, kickstand
 * - Features (8%): Connectivity booleans + features checkbox count
 *
 * Design notes:
 * - No tested values (low coverage) - uses manufacturer specs only
 * - No claimed range - uses Wh as primary battery metric instead
 * - Voltage scored in Motor Performance only (Wh already incorporates V×Ah)
 * - Battery type scored by cell brand quality (Samsung/LG/Panasonic > generic > LiPo)
 * - Suspension scored by brand tier (DNM/KKE/Fastace = premium aftermarket)
 *
 * @package ERH\Scoring
 */

declare(strict_types=1);

namespace ERH\Scoring;

/**
 * Calculate absolute product scores for EUCs.
 */
class EucScorer {

    use ScoringHelpers;
    use SpecsAccessor;

    /**
     * Category weights for overall score calculation.
     *
     * @var array<string, float>
     */
    private const CATEGORY_WEIGHTS = [
        'motor_performance' => 0.20,
        'battery_range'     => 0.25,
        'ride_quality'      => 0.20,
        'safety'            => 0.15,
        'portability'       => 0.12,
        'features'          => 0.08,
    ];

    /**
     * Calculate all category scores for an EUC.
     *
     * @param array $specs The product specs array.
     * @return array Category scores including overall.
     */
    public function calculate(array $specs): array {
        $this->set_product_group_key('Electric Unicycle');

        $scores = [
            'motor_performance' => $this->calculate_motor_performance($specs),
            'battery_range'     => $this->calculate_battery_range($specs),
            'ride_quality'      => $this->calculate_ride_quality($specs),
            'safety'            => $this->calculate_safety($specs),
            'portability'       => $this->calculate_portability($specs),
            'features'          => $this->calculate_features($specs),
        ];

        $scores['overall'] = $this->calculate_overall_score($scores);

        return $scores;
    }

    /**
     * Calculate weighted overall score from category scores.
     *
     * Null categories redistribute weight to available categories.
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
     * Get flat fallback value for EUC fields.
     *
     * EUCs use nested ACF structure without flat fallbacks.
     *
     * @param array  $array       The specs array.
     * @param string $nested_path The nested path to map.
     * @return mixed Always returns null for EUCs.
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
     * Factors: Nominal Power 30pts (log 800-4500W), Peak Power 20pts (log 2000-20000W),
     * Top Speed 25pts (log 15-75mph), Voltage 15pts (log 60-134V),
     * Hollow Motor 5pts (boolean), Torque 5pts (log 40-250Nm).
     */
    private function calculate_motor_performance(array $specs): ?int {
        $factors = [
            // Nominal power: sustained output, core differentiator.
            // Range: beginner EUCs ~800W, high-performance ~4500W.
            $this->log_scale(
                $this->get_nested_value($specs, 'motor.power_nominal'),
                800.0, 4500.0, 30
            ),
            // Peak power: burst for hills and acceleration.
            // Range: entry ~2000W, top-tier ~20000W.
            $this->log_scale(
                $this->get_nested_value($specs, 'motor.power_peak'),
                2000.0, 20000.0, 20
            ),
            // Top speed: manufacturer claimed.
            // Range: beginner ~15mph, performance ~75mph.
            $this->log_scale(
                $this->get_top_level_value($specs, 'manufacturer_top_speed'),
                15.0, 75.0, 25
            ),
            // Battery voltage: power delivery efficiency.
            // Range: entry-level 60V, flagship ~134V.
            $this->log_scale(
                $this->get_nested_value($specs, 'battery.voltage'),
                60.0, 134.0, 15
            ),
            // Hollow motor: lighter, more efficient hub motor design.
            $this->boolean_score(
                $this->get_nested_value($specs, 'motor.hollow_motor'),
                5
            ),
            // Torque: direct measure of hill climbing and acceleration.
            // Range: small EUCs ~40Nm, high-torque ~250Nm.
            $this->log_scale(
                $this->get_nested_value($specs, 'motor.torque'),
                40.0, 250.0, 5
            ),
        ];

        return $this->calculate_category_score($factors);
    }

    // =========================================================================
    // Range & Battery (100 pts max)
    // =========================================================================

    /**
     * Calculate Range & Battery category score.
     *
     * Primary metric is Wh (no claimed range used).
     * Factors: Battery Capacity 60pts (log 320-4800Wh), Charge Time 15pts (inverse log 2-14h),
     * Dual Charging 8pts (boolean), Fast Charger 7pts (boolean),
     * Battery Brand 10pts (tiered by cell manufacturer quality).
     */
    private function calculate_battery_range(array $specs): ?int {
        $factors = [
            // Battery capacity: primary range indicator.
            // Range: beginner EUCs ~320Wh, flagship ~4800Wh.
            $this->log_scale(
                $this->get_nested_value($specs, 'battery.capacity'),
                320.0, 4800.0, 60
            ),
            // Charge time: convenience factor (lower is better).
            // Range: fast ~2h, large batteries ~14h.
            $this->log_scale(
                $this->get_nested_value($specs, 'battery.charging_time'),
                2.0, 14.0, 15, true
            ),
            // Dual charging port: halves effective charge time.
            $this->boolean_score(
                $this->get_nested_value($specs, 'battery.dual_charging'),
                8
            ),
            // Fast charger available: optional faster charging.
            $this->boolean_score(
                $this->get_nested_value($specs, 'battery.fast_charger'),
                7
            ),
            // Battery cell brand: quality/longevity indicator.
            $this->score_battery_brand(
                $this->get_nested_value($specs, 'battery.battery_brand')
            ),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score battery cell brand.
     *
     * Samsung, LG, Molicel, Panasonic = premium cells (high discharge, long lifespan).
     * EVE = good mid-tier. BAK, Lishen = budget. Generic/Unknown = unscored.
     *
     * @param mixed $brand Battery brand string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_battery_brand($brand): array {
        if ($brand === null || $brand === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        $brand_str = (string) $brand;

        if ($brand_str === 'Unknown' || $brand_str === 'Generic') {
            return ['score' => null, 'max_possible' => 0];
        }

        return $this->tier_score($brand_str, [
            'Samsung'   => 10,
            'LG'        => 10,
            'Molicel'   => 10,
            'Panasonic' => 10,
            'EVE'       => 7,
            'BAK'       => 4,
            'Lishen'    => 4,
        ], 10, 3);
    }

    // =========================================================================
    // Ride Quality (100 pts max)
    // =========================================================================

    /**
     * Calculate Ride Quality category score.
     *
     * Factors: Suspension Type 25pts (tier), Suspension Travel 15pts (log 20-140mm),
     * Adjustable Suspension 5pts (boolean), Tire Size 20pts (log 14-22"),
     * Tire Width 10pts (log 2.0-4.0"), Pedal Height 10pts (log 80-200mm),
     * Spiked Pedals 5pts (boolean), Adjustable Pedals 5pts (boolean),
     * Self-Healing Tires 5pts (boolean).
     */
    private function calculate_ride_quality(array $specs): ?int {
        $factors = [
            // Suspension type: brand-tiered scoring.
            // DNM/KKE/Fastace are premium aftermarket suspension brands.
            $this->score_suspension_type(
                $this->get_nested_value($specs, 'suspension.suspension_type')
            ),
            // Suspension travel: more travel = smoother ride.
            // Range: minimal ~20mm, long-travel ~140mm.
            $this->log_scale(
                $this->get_nested_value($specs, 'suspension.suspension_travel'),
                20.0, 140.0, 15
            ),
            // Adjustable suspension: tune for rider weight/style.
            $this->boolean_score(
                $this->get_nested_value($specs, 'suspension.adjustable_suspension'),
                5
            ),
            // Tire/wheel size: larger = smoother over bumps.
            // Range: 14" beginner to 22"+ monster wheels.
            $this->log_scale(
                $this->get_nested_value($specs, 'wheel.tire_size'),
                14.0, 22.0, 20
            ),
            // Tire width: wider = more stability and grip.
            // Range: narrow 2.0" to fat tire 4.0"+.
            $this->log_scale(
                $this->get_nested_value($specs, 'wheel.tire_width'),
                2.0, 4.0, 10
            ),
            // Pedal height: ground clearance for bumps and lean angles.
            // Range: low ~3" to high ~8".
            $this->log_scale(
                $this->get_nested_value($specs, 'pedals.pedal_height'),
                3.0, 8.0, 10
            ),
            // Spiked/grip pedals: foot security at speed.
            $this->boolean_score(
                $this->get_nested_value($specs, 'pedals.spiked_pedals'),
                5
            ),
            // Adjustable pedals: fit customization.
            $this->boolean_score(
                $this->get_nested_value($specs, 'pedals.adjustable_pedals'),
                5
            ),
            // Self-healing tires: puncture resistance.
            $this->boolean_score(
                $this->get_nested_value($specs, 'wheel.self_healing'),
                5
            ),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score EUC suspension type by brand/quality tier.
     *
     * EUC suspension brands have known quality tiers:
     * - DNM, KKE, Fastace: Premium aftermarket (25pts)
     * - Hydraulic: High quality (22pts)
     * - Air: Good (18pts)
     * - Custom: Manufacturer-specific (15pts)
     * - Coil Spring: Basic (10pts)
     * - None: No suspension (0pts)
     *
     * @param mixed $type Suspension type string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_suspension_type($type): array {
        if ($type === null || $type === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        $type_str = (string) $type;

        // "None" is valid data = 0 points (not null/missing).
        if (strtolower($type_str) === 'none') {
            return ['score' => 0, 'max_possible' => 25];
        }

        return $this->tier_score($type_str, [
            'DNM'      => 25,
            'KKE'      => 25,
            'Fastace'  => 25,
            'Hydraulic' => 22,
            'Air'       => 18,
            'Custom'    => 15,
            'Coil'      => 10,  // Matches "Coil Spring"
            'Spring'    => 10,
        ], 25, 12);
    }

    // =========================================================================
    // Safety (100 pts max)
    // =========================================================================

    /**
     * Calculate Safety category score.
     *
     * Factors: Tiltback Margin 30pts (tiered), IP Rating 25pts (tiered),
     * Lighting 25pts (composite), Lift Sensor 10pts (boolean),
     * Max Load 10pts (log 150-350lbs).
     */
    private function calculate_safety(array $specs): ?int {
        $factors = [
            // Tiltback safety margin: gap between warning and cutoff.
            $this->score_tiltback_margin(
                $this->get_nested_value($specs, 'safety.tiltback_speed'),
                $this->get_nested_value($specs, 'safety.cutoff_speed')
            ),
            // IP rating: water and dust protection.
            $this->score_ip_rating(
                $this->get_nested_value($specs, 'safety.ip_rating')
            ),
            // Lighting setup: visibility for safety.
            $this->score_lighting($specs),
            // Lift sensor: prevents runaway wheel when picked up.
            $this->boolean_score(
                $this->get_nested_value($specs, 'safety.lift_sensor'),
                10
            ),
            // Max load: structural safety margin.
            // Range: 150lbs (tight) to 350lbs (generous).
            $this->log_scale(
                $this->get_nested_value($specs, 'dimensions.max_load'),
                150.0, 350.0, 10
            ),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score tiltback safety margin.
     *
     * The margin between tiltback warning speed and hard cutoff speed
     * determines how much reaction time the rider has.
     * Margin = (cutoff - tiltback) / cutoff.
     *
     * @param mixed $tiltback_speed Tiltback speed in mph.
     * @param mixed $cutoff_speed   Cutoff speed in mph.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_tiltback_margin($tiltback_speed, $cutoff_speed): array {
        // Need both values for meaningful safety margin.
        if (!$this->is_valid_numeric($tiltback_speed) || !$this->is_valid_numeric($cutoff_speed)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $tiltback = (float) $tiltback_speed;
        $cutoff   = (float) $cutoff_speed;

        // Cutoff must be > tiltback for valid margin.
        if ($cutoff <= $tiltback || $cutoff <= 0) {
            return ['score' => 5, 'max_possible' => 30];
        }

        $margin = ($cutoff - $tiltback) / $cutoff;

        if ($margin >= 0.25) {
            $score = 30;  // Excellent: ≥25% margin.
        } elseif ($margin >= 0.15) {
            $score = 22;  // Good: 15-25% margin.
        } elseif ($margin >= 0.10) {
            $score = 15;  // Adequate: 10-15% margin.
        } else {
            $score = 8;   // Tight: <10% margin.
        }

        return ['score' => $score, 'max_possible' => 30];
    }

    /**
     * Score IP rating for water/dust protection.
     *
     * @param mixed $rating IP rating string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_ip_rating($rating): array {
        if ($rating === null || $rating === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        $rating_str = strtoupper((string) $rating);

        if ($rating_str === 'NONE' || $rating_str === 'UNKNOWN') {
            return ['score' => 0, 'max_possible' => 25];
        }

        // Extract water rating (second digit or X-digit for IPXn).
        $water = 0;

        if (preg_match('/IP(\d)(\d)/', $rating_str, $m)) {
            $water = (int) $m[2];
        } elseif (preg_match('/IPX(\d)/', $rating_str, $m)) {
            $water = (int) $m[1];
        }

        // Tier based on water protection level.
        if ($water >= 7) {
            $score = 25;  // IP67, IP77, IPX7: submersible.
        } elseif ($water >= 6) {
            $score = 20;  // IP66, IPX6: powerful water jets.
        } elseif ($water >= 5) {
            $score = 16;  // IP55, IP65, IPX5: water jets.
        } elseif ($water >= 4) {
            $score = 10;  // IP54, IP45, IPX4: splashes.
        } else {
            $score = 4;   // IP34 or lower: minimal.
        }

        return ['score' => $score, 'max_possible' => 25];
    }

    /**
     * Score lighting setup for visibility.
     *
     * Headlight 8pts, Taillight 5pts, Brake Light 7pts,
     * Headlight Lumens bonus 5pts (log 100-2000 lumens).
     *
     * @param array $specs Product specs.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_lighting(array $specs): array {
        $score    = 0;
        $has_data = false;

        // Headlight: 8pts.
        $headlight = $this->get_nested_value($specs, 'lighting.headlight');
        if ($headlight !== null) {
            $has_data = true;
            if ($headlight === true || $headlight === 1 || $headlight === '1') {
                $score += 8;
            }
        }

        // Taillight: 5pts.
        $taillight = $this->get_nested_value($specs, 'lighting.taillight');
        if ($taillight !== null) {
            $has_data = true;
            if ($taillight === true || $taillight === 1 || $taillight === '1') {
                $score += 5;
            }
        }

        // Brake light: 7pts (more safety-critical than taillight).
        $brake_light = $this->get_nested_value($specs, 'lighting.brake_light');
        if ($brake_light !== null) {
            $has_data = true;
            if ($brake_light === true || $brake_light === 1 || $brake_light === '1') {
                $score += 7;
            }
        }

        // Headlight lumens bonus: brighter = safer at night.
        $lumens = $this->get_nested_value($specs, 'lighting.headlight_lumens');
        if ($this->is_valid_numeric($lumens)) {
            $has_data      = true;
            $lumens_result = $this->log_scale((float) $lumens, 100.0, 2000.0, 5);
            $score        += $lumens_result['score'] ?? 0;
        }

        if (!$has_data) {
            return ['score' => null, 'max_possible' => 0];
        }

        return ['score' => min(25, $score), 'max_possible' => 25];
    }

    // =========================================================================
    // Portability (100 pts max)
    // =========================================================================

    /**
     * Calculate Portability category score.
     *
     * Factors: Weight 70pts (inverse log 28-120lbs), Trolley/Retractable Handle 20pts,
     * Kickstand 10pts.
     */
    private function calculate_portability(array $specs): ?int {
        $features_array = $this->get_top_level_value($specs, 'features');

        $factors = [
            // Weight: primary portability factor (lighter is better).
            // Range: lightest EUCs ~28lbs, heaviest ~120lbs.
            $this->log_scale(
                $this->get_nested_value($specs, 'dimensions.weight'),
                28.0, 120.0, 70, true
            ),
            // Trolley or retractable handle: roll instead of carry.
            $this->score_handle_feature($features_array),
            // Kickstand: practical for parking without leaning.
            $this->score_feature_item($features_array, 'Kickstand', 10),
        ];

        // Weight is required for portability score.
        if ($factors[0]['score'] === null) {
            return null;
        }

        return $this->calculate_category_score($factors);
    }

    /**
     * Score trolley/retractable handle feature.
     *
     * Retractable handle is best (20pts), trolley handle is good (15pts).
     *
     * @param mixed $features Features checkbox array.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_handle_feature($features): array {
        if (!is_array($features) || empty($features)) {
            return ['score' => 0, 'max_possible' => 20];
        }

        $has_retractable = $this->array_some($features, fn($f) => stripos((string) $f, 'Retractable') !== false);
        $has_trolley     = $this->array_some($features, fn($f) => stripos((string) $f, 'Trolley') !== false);

        if ($has_retractable) {
            return ['score' => 20, 'max_possible' => 20];
        }

        if ($has_trolley) {
            return ['score' => 15, 'max_possible' => 20];
        }

        return ['score' => 0, 'max_possible' => 20];
    }

    /**
     * Score a specific feature from the features checkbox array.
     *
     * @param mixed  $features Features checkbox array.
     * @param string $name     Feature name to look for (case-insensitive substring).
     * @param int    $pts      Points if found.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_feature_item($features, string $name, int $pts): array {
        if (!is_array($features) || empty($features)) {
            return ['score' => 0, 'max_possible' => $pts];
        }

        $has = $this->array_some($features, fn($f) => stripos((string) $f, $name) !== false);

        return ['score' => $has ? $pts : 0, 'max_possible' => $pts];
    }

    // =========================================================================
    // Features (100 pts max)
    // =========================================================================

    /**
     * Calculate Features category score.
     *
     * Counts features from checkbox array (10 items) + connectivity booleans (4).
     * 14 features = 100 pts.
     */
    private function calculate_features(array $specs): ?int {
        $feature_count = 0;
        $has_any_data  = false;

        // Features checkbox array (up to 10 items).
        $features_array = $this->get_top_level_value($specs, 'features');
        if (is_array($features_array) && !empty($features_array)) {
            $has_any_data   = true;
            $feature_count += count($features_array);
        }

        // Connectivity booleans.
        $connectivity_keys = [
            'connectivity.bluetooth',
            'connectivity.app',
            'connectivity.speaker',
            'connectivity.gps',
        ];

        foreach ($connectivity_keys as $path) {
            $value = $this->get_nested_value($specs, $path);

            if ($value !== null) {
                $has_any_data = true;
                if ($value === true || $value === 1 || $value === '1') {
                    $feature_count++;
                }
            }
        }

        if (!$has_any_data) {
            return null;
        }

        // Scale to 0-100 (14 features = 100 pts).
        return min(100, (int) round(($feature_count / 14) * 100));
    }
}
