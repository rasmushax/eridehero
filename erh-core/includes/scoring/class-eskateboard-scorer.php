<?php
/**
 * E-Skateboard Scorer - calculates scores across 5 categories.
 *
 * Categories:
 * - Motor Performance (25%): Nominal power, peak power, top speed, motor count, drive type, motor size
 * - Battery & Range (25%): Battery capacity (Wh), voltage, charge time, battery brand
 * - Ride Quality (25%): Wheel size, wheel material, deck length/width, concave, trucks, suspension, terrain
 * - Portability (15%): Weight, handle feature
 * - Features (10%): Feature count, IP rating, lighting
 *
 * Design notes:
 * - No tested values (low coverage) - uses manufacturer specs only
 * - No claimed range in scoring - Wh is more reliable for e-skates
 * - No battery config scoring - too many null values
 * - No durometer scoring - preference (soft vs hard), not quality indicator
 * - No ground_clearance scoring - preference (low vs high), not quality indicator
 * - Belt > Hub drive type ranking per enthusiast consensus
 * - Weight ranges specific to skateboards (8-35 lbs)
 *
 * @package ERH\Scoring
 */

declare(strict_types=1);

namespace ERH\Scoring;

/**
 * Calculate absolute product scores for e-skateboards.
 */
class EskateboardScorer {

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
        'ride_quality'      => 0.25,
        'portability'       => 0.15,
        'features'          => 0.10,
    ];

    /**
     * Calculate all category scores for an e-skateboard.
     *
     * @param array $specs The product specs array.
     * @return array Category scores including overall.
     */
    public function calculate(array $specs): array {
        $this->set_product_group_key('Electric Skateboard');

        $scores = [
            'motor_performance' => $this->calculate_motor_performance($specs),
            'battery_range'     => $this->calculate_battery_range($specs),
            'ride_quality'      => $this->calculate_ride_quality($specs),
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
     * Get flat fallback value for e-skateboard fields.
     *
     * E-skateboards use nested ACF structure without flat fallbacks.
     *
     * @param array  $array       The specs array.
     * @param string $nested_path The nested path to map.
     * @return mixed Always returns null for e-skateboards.
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
     * Factors: Nominal Power 30pts (log 300-3000W), Peak Power 20pts (log 500-8000W),
     * Top Speed 25pts (log 15-50mph), Motor Count 10pts (tier),
     * Drive Type 10pts (tier), Motor Size 5pts (regex tier).
     */
    private function calculate_motor_performance(array $specs): ?int {
        $factors = [
            // Nominal power: sustained output.
            // Range: budget ~300W, premium ~3000W.
            $this->log_scale(
                $this->get_nested_value($specs, 'motor.power_nominal'),
                300.0, 3000.0, 30
            ),
            // Peak power: burst for hills and acceleration.
            // Range: entry ~500W, top-tier ~8000W.
            $this->log_scale(
                $this->get_nested_value($specs, 'motor.power_peak'),
                500.0, 8000.0, 20
            ),
            // Top speed: manufacturer claimed.
            // Range: budget ~15mph, performance ~50mph.
            $this->log_scale(
                $this->get_top_level_value($specs, 'manufacturer_top_speed'),
                15.0, 50.0, 25
            ),
            // Motor count: 4WD > 2WD > 1WD.
            $this->score_motor_count(
                $this->get_nested_value($specs, 'motor.drive')
            ),
            // Drive type: Belt > Gear > Direct Drive > Hub.
            $this->score_drive_type(
                $this->get_nested_value($specs, 'motor.motor_type')
            ),
            // Motor size: larger motors = more torque.
            $this->score_motor_size(
                $this->get_nested_value($specs, 'motor.motor_size')
            ),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score motor count / drive configuration.
     *
     * 4WD = 10pts, 2WD = 7pts, 1WD = 3pts.
     *
     * @param mixed $drive Drive configuration string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_motor_count($drive): array {
        if ($drive === null || $drive === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        $drive_str = strtoupper((string) $drive);

        return $this->tier_score($drive_str, [
            '4WD' => 10,
            '2WD' => 7,
            '1WD' => 3,
        ], 10, 3);
    }

    /**
     * Score drive type.
     *
     * Belt/Gear drives offer better torque and performance than hub motors.
     *
     * @param mixed $type Motor type string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_drive_type($type): array {
        if ($type === null || $type === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        return $this->tier_score((string) $type, [
            'Belt'         => 10,
            'Gear'         => 9,
            'Direct Drive' => 8,
            'Hub'          => 6,
        ], 10, 5);
    }

    /**
     * Score motor size from string (e.g., "6374", "90mm").
     *
     * Extracts numeric value and tiers: 90mm+ = 5, 70mm+ = 3, else = 1.
     *
     * @param mixed $size Motor size string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_motor_size($size): array {
        if ($size === null || $size === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        $size_str = (string) $size;

        // Extract numeric value from strings like "6374", "90mm", "63mm x 74mm".
        if (preg_match('/(\d+)/', $size_str, $matches)) {
            $num = (int) $matches[1];

            // For 4-digit motor codes (e.g., 6374), second two digits = diameter.
            if ($num >= 1000) {
                $num = (int) substr((string) $num, 2);
            }

            if ($num >= 90) {
                return ['score' => 5, 'max_possible' => 5];
            }
            if ($num >= 70) {
                return ['score' => 3, 'max_possible' => 5];
            }
            return ['score' => 1, 'max_possible' => 5];
        }

        return ['score' => null, 'max_possible' => 0];
    }

    // =========================================================================
    // Battery & Range (100 pts max)
    // =========================================================================

    /**
     * Calculate Battery & Range category score.
     *
     * Primary metric is Wh (no claimed range used - unreliable for e-skates).
     * Factors: Battery Capacity 60pts (log 100-1000Wh), Voltage 15pts (log 24-60V),
     * Charge Time 15pts (inverse log 1-6h), Battery Brand 10pts (tiered).
     */
    private function calculate_battery_range(array $specs): ?int {
        $factors = [
            // Battery capacity: primary range indicator.
            // Range: budget ~100Wh, premium ~1000Wh.
            $this->log_scale(
                $this->get_nested_value($specs, 'battery.capacity'),
                100.0, 1000.0, 60
            ),
            // Voltage: power delivery efficiency.
            // Range: entry-level 24V, flagship ~60V.
            $this->log_scale(
                $this->get_nested_value($specs, 'battery.voltage'),
                24.0, 60.0, 15
            ),
            // Charge time: convenience factor (lower is better).
            // Range: fast ~1h, large batteries ~6h.
            $this->log_scale(
                $this->get_nested_value($specs, 'battery.charging_time'),
                1.0, 6.0, 15, true
            ),
            // Battery cell brand: quality/longevity indicator.
            $this->score_battery_brand(
                $this->get_nested_value($specs, 'battery.brand')
            ),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score battery cell brand.
     *
     * Samsung, LG, Molicel = premium cells. EVE = mid-tier. Generic/Unknown = unscored.
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
            'Samsung' => 10,
            'LG'      => 10,
            'Molicel' => 10,
            'EVE'     => 7,
        ], 10, 3);
    }

    // =========================================================================
    // Ride Quality (100 pts max)
    // =========================================================================

    /**
     * Calculate Ride Quality category score.
     *
     * Factors: Wheel Size 20pts (log 70-200mm), Wheel Material 15pts (tier),
     * Deck Length 15pts (log 28-45"), Deck Width 10pts (log 8-12"),
     * Concave 10pts (tier), Trucks 10pts (tier), Has Suspension 10pts (boolean),
     * Terrain 10pts (tier).
     */
    private function calculate_ride_quality(array $specs): ?int {
        $factors = [
            // Wheel size: larger = smoother ride.
            // Range: PU 70mm to AT 200mm.
            $this->log_scale(
                $this->get_nested_value($specs, 'wheels.wheel_size'),
                70.0, 200.0, 20
            ),
            // Wheel material: Cloudwheels > Rubber/Pneumatic > Polyurethane.
            $this->score_wheel_material(
                $this->get_nested_value($specs, 'wheels.wheel_material')
            ),
            // Deck length: longer = more stable.
            // Range: shortboard ~28", longboard ~45".
            $this->log_scale(
                $this->get_nested_value($specs, 'deck.length'),
                28.0, 45.0, 15
            ),
            // Deck width: wider = more comfortable stance.
            // Range: narrow ~8", wide ~12".
            $this->log_scale(
                $this->get_nested_value($specs, 'deck.width'),
                8.0, 12.0, 10
            ),
            // Deck concave: deeper concave = better foot lock.
            $this->score_concave(
                $this->get_nested_value($specs, 'deck.concave')
            ),
            // Trucks: Double Kingpin > Reverse Kingpin > Standard.
            $this->score_trucks(
                $this->get_nested_value($specs, 'trucks.trucks')
            ),
            // Suspension: absorbs bumps.
            $this->boolean_score(
                $this->get_nested_value($specs, 'suspension.has_suspension'),
                10
            ),
            // Terrain capability: All-Terrain > Hybrid > Street.
            $this->score_terrain(
                $this->get_nested_value($specs, 'wheels.terrain')
            ),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score wheel material.
     *
     * Cloudwheels = 15pts (best ride quality), Rubber/Pneumatic = 12pts,
     * Polyurethane = 8pts (standard).
     *
     * @param mixed $material Wheel material string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_wheel_material($material): array {
        if ($material === null || $material === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        return $this->tier_score((string) $material, [
            'Cloudwheel'   => 15,
            'Rubber'       => 12,
            'Pneumatic'    => 12,
            'Polyurethane' => 8,
        ], 15, 8);
    }

    /**
     * Score deck concave profile.
     *
     * Deeper concave provides better foot lock-in for stability.
     *
     * @param mixed $concave Concave type string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_concave($concave): array {
        if ($concave === null || $concave === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        return $this->tier_score((string) $concave, [
            'W-Concave'  => 10,
            'Aggressive' => 9,
            'Medium'     => 7,
            'Mild'       => 5,
            'Flat'       => 3,
        ], 10, 5);
    }

    /**
     * Score truck type.
     *
     * Double Kingpin = best carving, Reverse Kingpin = good stability,
     * Standard = basic.
     *
     * @param mixed $trucks Truck type string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_trucks($trucks): array {
        if ($trucks === null || $trucks === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        return $this->tier_score((string) $trucks, [
            'Double Kingpin'  => 10,
            'DKP'             => 10,
            'Reverse Kingpin' => 8,
            'RKP'             => 8,
            'Standard'        => 5,
            'TKP'             => 5,
        ], 10, 5);
    }

    /**
     * Score terrain capability.
     *
     * All-Terrain boards can handle more surface types.
     *
     * @param mixed $terrain Terrain type string.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_terrain($terrain): array {
        if ($terrain === null || $terrain === '') {
            return ['score' => null, 'max_possible' => 0];
        }

        return $this->tier_score((string) $terrain, [
            'All-Terrain' => 10,
            'Hybrid'      => 7,
            'Street'      => 4,
        ], 10, 4);
    }

    // =========================================================================
    // Portability (100 pts max)
    // =========================================================================

    /**
     * Calculate Portability category score.
     *
     * Factors: Weight 80pts (inverse log 8-35lbs), Handle 20pts (from features array).
     */
    private function calculate_portability(array $specs): ?int {
        $features_array = $this->get_top_level_value($specs, 'features');

        $factors = [
            // Weight: primary portability factor (lighter is better).
            // Range: lightest shortboards ~8lbs, heaviest AT ~35lbs.
            $this->log_scale(
                $this->get_nested_value($specs, 'dimensions.weight'),
                8.0, 35.0, 80, true
            ),
            // Handle: makes carrying much easier.
            $this->score_feature_item($features_array, 'Handle', 20),
        ];

        // Weight is required for portability score.
        if ($factors[0]['score'] === null) {
            return null;
        }

        return $this->calculate_category_score($factors);
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
     * Factors: Feature Count 60pts (scale 0-11 features),
     * IP Rating 20pts (tiered), Lighting 20pts (composite).
     */
    private function calculate_features(array $specs): ?int {
        $factors = [
            // Feature count from checkbox array.
            $this->score_feature_count($specs),
            // IP rating: water and dust protection.
            $this->score_ip_rating(
                $this->get_nested_value($specs, 'other.ip_rating')
            ),
            // Lighting: visibility and safety.
            $this->score_lighting($specs),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score feature count from features checkbox array.
     *
     * 11 features = 60 pts maximum.
     *
     * @param array $specs Product specs.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_feature_count(array $specs): array {
        $features_array = $this->get_top_level_value($specs, 'features');

        if (!is_array($features_array) || empty($features_array)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $count = count($features_array);
        $score = min(60, (int) round(($count / 11) * 60));

        return ['score' => $score, 'max_possible' => 60];
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
            return ['score' => 0, 'max_possible' => 20];
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
            $score = 20;  // IP67+: submersible.
        } elseif ($water >= 6) {
            $score = 16;  // IP66, IPX6: powerful water jets.
        } elseif ($water >= 5) {
            $score = 13;  // IP55, IPX5: water jets.
        } elseif ($water >= 4) {
            $score = 8;   // IP54, IPX4: splashes.
        } else {
            $score = 3;   // IP34 or lower: minimal.
        }

        return ['score' => $score, 'max_possible' => 20];
    }

    /**
     * Score lighting setup.
     *
     * Front 8pts, Rear 8pts, Ambient 4pts.
     *
     * @param array $specs Product specs.
     * @return array{score: float|null, max_possible: int}
     */
    private function score_lighting(array $specs): array {
        $score    = 0;
        $has_data = false;

        // Lights field is a checkbox with values: Front, Rear, Both, None.
        $lights = $this->get_nested_value($specs, 'lighting.lights');

        if ($lights !== null) {
            $has_data = true;

            if (is_array($lights)) {
                $lights_lower = array_map('strtolower', array_map('strval', $lights));

                if (in_array('both', $lights_lower, true)) {
                    $score += 16; // Front + Rear.
                } else {
                    if (in_array('front', $lights_lower, true)) {
                        $score += 8;
                    }
                    if (in_array('rear', $lights_lower, true)) {
                        $score += 8;
                    }
                }
            } elseif (is_string($lights)) {
                $lights_lower = strtolower($lights);
                if ($lights_lower === 'both') {
                    $score += 16;
                } elseif ($lights_lower === 'front') {
                    $score += 8;
                } elseif ($lights_lower === 'rear') {
                    $score += 8;
                }
            }
        }

        // Ambient lights: side LEDs for visibility.
        $ambient = $this->get_nested_value($specs, 'lighting.ambient_lights');
        if ($ambient !== null) {
            $has_data = true;
            if ($ambient === true || $ambient === 1 || $ambient === '1') {
                $score += 4;
            }
        }

        if (!$has_data) {
            return ['score' => null, 'max_possible' => 0];
        }

        return ['score' => min(20, $score), 'max_possible' => 20];
    }
}
