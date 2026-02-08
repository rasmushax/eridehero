<?php
/**
 * E-Bike Scorer - calculates scores across 5 categories.
 *
 * Categories:
 * - Motor & Drive (25%): Torque, motor brand, position, sensor, power
 * - Battery & Range (20%): Capacity, cell quality, charge time, removable
 * - Component Quality (25%): Brakes, drivetrain, tires, frame, IP, certifications
 * - Comfort (15%): Front/rear suspension, seatpost, tire width
 * - Practicality (15%): Weight, display, app, folding, accessories, throttle
 *
 * @package ERH\Scoring
 */

declare(strict_types=1);

namespace ERH\Scoring;

/**
 * Calculate absolute product scores for e-bikes.
 */
class EbikeScorer {

    use ScoringHelpers;
    use SpecsAccessor;

    /**
     * Category weights for overall score calculation.
     *
     * @var array<string, float>
     */
    private const CATEGORY_WEIGHTS = [
        'motor_drive'       => 0.25,
        'battery_range'     => 0.20,
        'component_quality' => 0.25,
        'comfort'           => 0.15,
        'practicality'      => 0.15,
    ];

    /**
     * Calculate all category scores for an e-bike.
     *
     * @param array $specs The product specs array.
     * @return array Category scores including overall.
     */
    public function calculate(array $specs): array {
        $this->set_product_group_key('Electric Bike');

        $scores = [
            'motor_drive'       => $this->calculate_motor_drive($specs),
            'battery_range'     => $this->calculate_battery_range($specs),
            'component_quality' => $this->calculate_component_quality($specs),
            'comfort'           => $this->calculate_comfort($specs),
            'practicality'      => $this->calculate_practicality($specs),
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
     * Get flat fallback value for e-bike fields.
     *
     * E-bikes use nested ACF structure without flat fallbacks.
     *
     * @param array  $array       The specs array.
     * @param string $nested_path The nested path to map.
     * @return mixed Always returns null for e-bikes.
     */
    protected function get_flat_fallback(array $array, string $nested_path) {
        // E-bikes don't have flat fallbacks - they use nested ACF structure.
        return null;
    }

    // =========================================================================
    // Motor & Drive (100 pts max)
    // =========================================================================

    /**
     * Calculate Motor & Drive category score.
     *
     * Factors: Torque 30pts, Motor Brand 25pts, Position 20pts, Sensor 15pts, Power 10pts.
     */
    private function calculate_motor_drive(array $specs): ?int {
        $factors = [
            $this->score_torque($this->get_nested_value($specs, 'motor.torque')),
            $this->score_motor_brand(
                $this->get_nested_value($specs, 'motor.motor_brand'),
                $this->get_nested_value($specs, 'motor.motor_type')
            ),
            $this->score_motor_position($this->get_nested_value($specs, 'motor.motor_position')),
            $this->score_sensor_type($this->get_nested_value($specs, 'motor.sensor_type')),
            $this->score_power(
                $this->get_nested_value($specs, 'motor.power_nominal'),
                $this->get_nested_value($specs, 'motor.power_peak')
            ),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score torque (30 pts).
     * Log scale: 30Nm floor → 120Nm ceiling.
     */
    private function score_torque($torque): array {
        return $this->log_scale($torque, 30, 120, 30);
    }

    /**
     * Score motor position (20 pts).
     * Mid-drive = 20, Rear hub = 12, Front hub = 6.
     */
    private function score_motor_position($position): array {
        if (empty($position)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $pos = strtolower((string) $position);

        if (strpos($pos, 'mid') !== false || strpos($pos, 'center') !== false || strpos($pos, 'crank') !== false) {
            return ['score' => 20, 'max_possible' => 20];
        }

        if (strpos($pos, 'rear') !== false) {
            return ['score' => 12, 'max_possible' => 20];
        }

        if (strpos($pos, 'front') !== false) {
            return ['score' => 6, 'max_possible' => 20];
        }

        if (strpos($pos, 'hub') !== false) {
            return ['score' => 12, 'max_possible' => 20];
        }

        return ['score' => null, 'max_possible' => 0];
    }

    /**
     * Score sensor type (15 pts).
     * Torque = 15, Both = 15, Cadence = 6, None = 0.
     */
    private function score_sensor_type($sensor_type): array {
        if (empty($sensor_type)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $sensor = strtolower((string) $sensor_type);

        if (strpos($sensor, 'torque') !== false || strpos($sensor, 'both') !== false) {
            return ['score' => 15, 'max_possible' => 15];
        }

        if (strpos($sensor, 'cadence') !== false) {
            return ['score' => 6, 'max_possible' => 15];
        }

        if (strpos($sensor, 'none') !== false) {
            return ['score' => 0, 'max_possible' => 15];
        }

        return ['score' => null, 'max_possible' => 0];
    }

    /**
     * Score motor power (10 pts).
     * Log scale: 250W floor → 1500W ceiling.
     */
    private function score_power($nominal, $peak): array {
        $power = null;
        if ($nominal !== null && is_numeric($nominal) && (float) $nominal > 0) {
            $power = (float) $nominal;
        } elseif ($peak !== null && is_numeric($peak) && (float) $peak > 0) {
            $power = (float) $peak;
        }

        if ($power === null) {
            return ['score' => null, 'max_possible' => 0];
        }

        return $this->log_scale($power, 250, 1500, 10);
    }

    /**
     * Score motor brand quality (25 pts).
     *
     * 5-tier system:
     * - Tier 1 (25pts): Bosch, Shimano Steps, Brose, Fazua
     * - Tier 2 (20pts): Yamaha, Giant SyncDrive, Specialized SL, TQ, Mahle
     * - Tier 3 (15pts): Bafang M-series (M500, M600, M620, Ultra)
     * - Tier 4 (10pts): Bafang hub motors, generic mid-drive
     * - Tier 5 (5pts): Generic hub motors, unknown brands
     */
    private function score_motor_brand($motor_brand, $motor_type = null): array {
        $brand = strtolower((string) ($motor_brand ?? ''));
        $type  = strtolower((string) ($motor_type ?? ''));
        $combined = $brand . ' ' . $type;

        if (empty(trim($brand))) {
            return ['score' => null, 'max_possible' => 0];
        }

        // Tier 1 patterns.
        $tier1 = ['/\bbosch\b/i', '/\bshimano\b/i', '/\bbrose\b/i', '/\bfazua\b/i'];
        foreach ($tier1 as $pattern) {
            if (preg_match($pattern, $combined)) {
                return ['score' => 25, 'max_possible' => 25];
            }
        }

        // Tier 2 patterns.
        $tier2 = ['/\btq\b/i', '/\bgiant\b/i', '/\bsyncdrive\b/i', '/\byamaha\b/i', '/\bspecialized\b/i', '/\bmahle\b/i', '/\bmpf\b/i'];
        foreach ($tier2 as $pattern) {
            if (preg_match($pattern, $combined)) {
                return ['score' => 20, 'max_possible' => 25];
            }
        }

        // Tier 3: Bafang M-series.
        if (preg_match('/\bbafang\s*(m[56]\d{2}|m[56]00|ultra|max)/i', $combined)) {
            return ['score' => 15, 'max_possible' => 25];
        }
        if (preg_match('/\bmivice\b/i', $combined)) {
            return ['score' => 15, 'max_possible' => 25];
        }

        // Tier 4: Standard Bafang or generic mid-drive.
        if (preg_match('/\bbafang\b/i', $combined)) {
            return ['score' => 10, 'max_possible' => 25];
        }
        if (preg_match('/\b(mid[- ]?drive|center[- ]?motor|crank[- ]?motor)\b/i', $combined)) {
            return ['score' => 10, 'max_possible' => 25];
        }

        // Tier 5: Default.
        return ['score' => 5, 'max_possible' => 25];
    }

    // =========================================================================
    // Battery & Range (100 pts max)
    // =========================================================================

    /**
     * Calculate Battery & Range category score.
     *
     * Factors: Capacity 55pts, Cell Quality 15pts, Charge Time 15pts, Removable 15pts.
     */
    private function calculate_battery_range(array $specs): ?int {
        $factors = [
            $this->score_battery_capacity($this->get_nested_value($specs, 'battery.battery_capacity')),
            $this->score_battery_cells($this->get_nested_value($specs, 'battery.battery_brand')),
            $this->score_charge_time($this->get_nested_value($specs, 'battery.charge_time')),
            $this->score_removable_battery($this->get_nested_value($specs, 'battery.removable')),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score battery capacity (55 pts).
     * Log scale: 300Wh floor → 1000Wh ceiling.
     */
    private function score_battery_capacity($wh): array {
        return $this->log_scale($wh, 300, 1000, 55);
    }

    /**
     * Score battery cell quality (15 pts).
     *
     * 4-tier system:
     * - Tier 1 (15pts): Samsung, LG, Panasonic, Sony
     * - Tier 2 (10pts): Bosch, Shimano, Brose, Yamaha systems
     * - Tier 3 (5pts): Brand-name but cells unknown
     * - Tier 4 (0pts): Generic/unspecified
     */
    private function score_battery_cells($battery_brand): array {
        $brand = strtolower((string) ($battery_brand ?? ''));

        if (empty(trim($brand))) {
            return ['score' => null, 'max_possible' => 0];
        }

        // Tier 1.
        $tier1 = ['/\bsamsung\b/i', '/\blg\s*(chem|cell)?\b/i', '/\bpanasonic\b/i', '/\bsony\b/i'];
        foreach ($tier1 as $pattern) {
            if (preg_match($pattern, $brand)) {
                return ['score' => 15, 'max_possible' => 15];
            }
        }

        // Tier 2.
        $tier2 = ['/\bbosch\b/i', '/\bshimano\b/i', '/\bbrose\b/i', '/\byamaha\b/i'];
        foreach ($tier2 as $pattern) {
            if (preg_match($pattern, $brand)) {
                return ['score' => 10, 'max_possible' => 15];
            }
        }

        // Tier 3.
        if (strlen($brand) > 3 && !preg_match('/\b(generic|unknown|standard|oem)\b/i', $brand)) {
            return ['score' => 5, 'max_possible' => 15];
        }

        // Tier 4.
        return ['score' => 0, 'max_possible' => 15];
    }

    /**
     * Score charge time (15 pts).
     * Linear: 2h → 15pts, 8h → 0pts.
     */
    private function score_charge_time($hours): array {
        return $this->linear_scale($hours, 2, 8, 15, true);
    }

    /**
     * Score removable battery (15 pts).
     */
    private function score_removable_battery($removable): array {
        if ($removable === null) {
            return ['score' => null, 'max_possible' => 0];
        }
        return ['score' => $removable === true ? 15 : 0, 'max_possible' => 15];
    }

    // =========================================================================
    // Component Quality (100 pts max)
    // =========================================================================

    /**
     * Calculate Component Quality category score.
     *
     * Factors: Brake Brand 15pts, Brake Type 10pts, Rotor Size 8pts,
     * Drivetrain Brand 15pts, Drive System 10pts, Gear Count 7pts,
     * Tire Brand 10pts, Frame Material 10pts, IP Rating 10pts, Certifications 5pts.
     */
    private function calculate_component_quality(array $specs): ?int {
        $factors = [
            // Brake quality.
            $this->score_brake_brand(
                $this->get_nested_value($specs, 'brakes.brake_brand'),
                $this->get_nested_value($specs, 'brakes.brake_model')
            ),
            $this->score_brake_type($this->get_nested_value($specs, 'brakes.brake_type')),
            $this->score_rotor_size(
                $this->get_nested_value($specs, 'brakes.rotor_size_front'),
                $this->get_nested_value($specs, 'brakes.rotor_size_rear')
            ),

            // Drivetrain quality.
            $this->score_drivetrain_brand(
                $this->get_nested_value($specs, 'drivetrain.derailleur'),
                $this->get_nested_value($specs, 'drivetrain.shifter')
            ),
            $this->score_drive_system($this->get_nested_value($specs, 'drivetrain.drive_system')),
            $this->score_gear_count($this->get_nested_value($specs, 'drivetrain.gears')),

            // Build quality.
            $this->score_tire_brand($this->get_nested_value($specs, 'wheels_and_tires.tire_brand')),
            $this->score_frame_material($this->get_nested_value($specs, 'frame_and_geometry.frame_material')),

            // Safety/compliance.
            $this->score_ip_rating($this->get_nested_value($specs, 'safety_and_compliance.ip_rating')),
            $this->score_certifications($this->get_nested_value($specs, 'safety_and_compliance.certifications')),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score brake type (10 pts).
     * Hydraulic = 10, Mechanical = 5, Rim = 2.
     */
    private function score_brake_type($brake_type): array {
        if (empty($brake_type)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $types = is_array($brake_type) ? $brake_type : [$brake_type];
        $combined = strtolower(implode(' ', $types));

        if (strpos($combined, 'hydraulic') !== false) {
            return ['score' => 10, 'max_possible' => 10];
        }

        if (strpos($combined, 'mechanical') !== false || strpos($combined, 'disc') !== false) {
            return ['score' => 5, 'max_possible' => 10];
        }

        if (strpos($combined, 'rim') !== false || strpos($combined, 'v-brake') !== false) {
            return ['score' => 2, 'max_possible' => 10];
        }

        return ['score' => null, 'max_possible' => 0];
    }

    /**
     * Score rotor size (8 pts).
     * 203mm+ = 8, 180mm = 6, 160mm = 4, <160mm = 2.
     */
    private function score_rotor_size($front_rotor, $rear_rotor): array {
        $rotor = null;
        if ($front_rotor !== null && is_numeric($front_rotor) && (float) $front_rotor > 0) {
            $rotor = (float) $front_rotor;
        } elseif ($rear_rotor !== null && is_numeric($rear_rotor) && (float) $rear_rotor > 0) {
            $rotor = (float) $rear_rotor;
        }

        if ($rotor === null) {
            return ['score' => null, 'max_possible' => 0];
        }

        if ($rotor >= 203) {
            return ['score' => 8, 'max_possible' => 8];
        }
        if ($rotor >= 180) {
            return ['score' => 6, 'max_possible' => 8];
        }
        if ($rotor >= 160) {
            return ['score' => 4, 'max_possible' => 8];
        }

        return ['score' => 2, 'max_possible' => 8];
    }

    /**
     * Score drive system (10 pts).
     * Belt = 10, Chain = 5.
     */
    private function score_drive_system($drive_system): array {
        if (empty($drive_system)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $system = strtolower((string) $drive_system);

        if (strpos($system, 'belt') !== false) {
            return ['score' => 10, 'max_possible' => 10];
        }

        if (strpos($system, 'chain') !== false) {
            return ['score' => 5, 'max_possible' => 10];
        }

        return ['score' => null, 'max_possible' => 0];
    }

    /**
     * Score gear count (7 pts).
     * 10+ = 7, 8-9 = 5, 7 = 4, 1-3 = 2.
     */
    private function score_gear_count($gears): array {
        if ($gears === null || !is_numeric($gears) || (int) $gears <= 0) {
            return ['score' => null, 'max_possible' => 0];
        }

        $g = (int) $gears;

        if ($g >= 10) {
            return ['score' => 7, 'max_possible' => 7];
        }
        if ($g >= 8) {
            return ['score' => 5, 'max_possible' => 7];
        }
        if ($g >= 7) {
            return ['score' => 4, 'max_possible' => 7];
        }

        return ['score' => 2, 'max_possible' => 7];
    }

    /**
     * Score brake brand quality (15 pts).
     *
     * 5-tier system based on brake brand and model.
     */
    private function score_brake_brand($brake_brand, $brake_model = null): array {
        $brand = strtolower((string) ($brake_brand ?? ''));
        $model = strtolower((string) ($brake_model ?? ''));
        $combined = trim($brand . ' ' . $model);

        if (empty($brand)) {
            return ['score' => null, 'max_possible' => 0];
        }

        // Tier 1 (15pts): Premium.
        $tier1 = [
            '/\b(xt|xtr)\b/i', '/\bbr-?m[89]\d{3}/i', '/\bcode\b/i', '/\bmaven\b/i',
            '/\bg2\s*(ultimate|rsc)/i', '/\bmotive\s*(ultimate|silver)/i',
            '/\bmt[57]e?\b/i', '/\bhope\b/i', '/\bsaint\b/i', '/\bzee\b/i',
        ];
        foreach ($tier1 as $pattern) {
            if (preg_match($pattern, $combined)) {
                return ['score' => 15, 'max_possible' => 15];
            }
        }

        // Tier 2 (12pts): Good mid-range.
        $tier2 = [
            '/\bslx\b/i', '/\bbr-?m7\d{3}/i', '/\bdeore\s*(br-?m6120|m6120|4[- ]?piston)/i',
            '/\bmt520\b/i', '/\bmt420\b/i', '/\blevel\b/i', '/\bguide\b/i',
            '/\bdb8\b/i', '/\bmotive\s*bronze/i', '/\bmagura\s*mt\b/i',
        ];
        foreach ($tier2 as $pattern) {
            if (preg_match($pattern, $combined)) {
                return ['score' => 12, 'max_possible' => 15];
            }
        }

        // Generic Deore.
        if (preg_match('/\bdeore\b/i', $combined)) {
            return ['score' => 12, 'max_possible' => 15];
        }

        // Tier 3 (9pts): Entry-level Shimano, Tektro premium.
        $tier3 = [
            '/\bmt[45]00\b/i', '/\bmt-?[45]10\b/i', '/\bbr-?mt[45]\d{2}/i',
            '/\borion\b/i', '/\bdorado\b/i', '/\bhd-?m7[0-9]{2}/i',
            '/\bhd-?m745/i', '/\bdb4\b/i', '/\b4[- ]?piston\b/i',
        ];
        foreach ($tier3 as $pattern) {
            if (preg_match($pattern, $combined)) {
                return ['score' => 9, 'max_possible' => 15];
            }
        }

        // Tier 4 (6pts): Budget.
        $tier4 = [
            '/\bmt200\b/i', '/\bmt-?200\b/i', '/\bbr-?mt200/i',
            '/\bhd-?e\d{3}/i', '/\bhd-?e3[0-9]{2}/i', '/\bhd-?r\d{3}/i',
            '/\bhd-?t\d{3}/i', '/\baries\b/i', '/\bpromax\b/i', '/\bzoom\b/i',
        ];
        foreach ($tier4 as $pattern) {
            if (preg_match($pattern, $combined)) {
                return ['score' => 6, 'max_possible' => 15];
            }
        }

        // Generic Tektro = Tier 4.
        if (preg_match('/\btektro\b/i', $combined)) {
            return ['score' => 6, 'max_possible' => 15];
        }

        // Generic Shimano = Tier 3.
        if (preg_match('/\bshimano\b/i', $combined)) {
            return ['score' => 9, 'max_possible' => 15];
        }

        // Generic SRAM = Tier 3.
        if (preg_match('/\bsram\b/i', $combined)) {
            return ['score' => 9, 'max_possible' => 15];
        }

        // Tier 5 (3pts): Generic/unbranded.
        return ['score' => 3, 'max_possible' => 15];
    }

    /**
     * Score drivetrain brand quality (15 pts).
     *
     * 5-tier system based on derailleur/shifter brand.
     */
    private function score_drivetrain_brand($derailleur, $shifter): array {
        $combined = strtolower((string) ($derailleur ?? '')) . ' ' . strtolower((string) ($shifter ?? ''));

        if (strlen(trim($combined)) <= 1) {
            return ['score' => null, 'max_possible' => 0];
        }

        // Internal hub/CVT systems first.
        if (preg_match('/\b(enviolo|rohloff|pinion|nuvinci)\b/i', $combined)) {
            return ['score' => 15, 'max_possible' => 15];
        }
        if (preg_match('/\bshimano\s*(nexus|alfine)\b/i', $combined)) {
            return ['score' => 12, 'max_possible' => 15];
        }

        // Tier 1 (15pts): Premium.
        $tier1 = [
            '/\b(xt|xtr)\b/i', '/\bdeore\s*(xt|12|linkglide\s*\+)/i',
            '/\bultegra\b/i', '/\bdura[- ]?ace\b/i',
            '/\b(xx|x0|x01)\s*eagle/i', '/\bgx\s*eagle/i',
            '/\b(red|force)\s*(axs|etap)/i', '/\beagle.*transmission/i', '/\beagle.*axs/i',
        ];
        foreach ($tier1 as $pattern) {
            if (preg_match($pattern, $combined)) {
                return ['score' => 15, 'max_possible' => 15];
            }
        }

        // Tier 2 (12pts): Good mid-range.
        $tier2 = [
            '/\bslx\b/i', '/\bdeore\s*(m[456]\d{3}|rd-?m)/i', '/\b105\b/i',
            '/\bnx\s*eagle/i', '/\bsx\s*eagle/i', '/\brival\b/i',
            '/\bapex\b/i', '/\bmicroshift\s*(advent\s*x|xle|xcd)/i',
        ];
        foreach ($tier2 as $pattern) {
            if (preg_match($pattern, $combined)) {
                return ['score' => 12, 'max_possible' => 15];
            }
        }

        // Generic Deore.
        if (preg_match('/\bdeore\b/i', $combined) && !preg_match('/\b(xt|12|linkglide)/i', $combined)) {
            return ['score' => 12, 'max_possible' => 15];
        }

        // Tier 3 (9pts): Entry-level named.
        $tier3 = ['/\bcues\b/i', '/\balivio\b/i', '/\bmicroshift\s*advent\b/i'];
        foreach ($tier3 as $pattern) {
            if (preg_match($pattern, $combined)) {
                return ['score' => 9, 'max_possible' => 15];
            }
        }

        // Tier 4 (6pts): Budget Shimano.
        $tier4 = [
            '/\baltus\b/i', '/\bacera\b/i', '/\btourney\b/i', '/\bmegarange\b/i',
            '/\bshimano\s*\d+[- ]?speed/i', '/\b\d+[- ]?speed\s*shimano/i',
        ];
        foreach ($tier4 as $pattern) {
            if (preg_match($pattern, $combined)) {
                return ['score' => 6, 'max_possible' => 15];
            }
        }

        // Generic Shimano = Tier 4.
        if (preg_match('/\bshimano\b/i', $combined)) {
            return ['score' => 6, 'max_possible' => 15];
        }

        // Tier 5 (3pts): Generic/unbranded.
        return ['score' => 3, 'max_possible' => 15];
    }

    /**
     * Score tire brand quality (10 pts).
     *
     * 3-tier system:
     * - Tier 1 (10pts): Maxxis, Schwalbe, Continental, Pirelli, Vittoria, Michelin
     * - Tier 2 (7pts): WTB, Kenda, CST, Vee Tire, Teravail
     * - Tier 3 (4pts): Generic/unbranded
     */
    private function score_tire_brand($tire_brand): array {
        $brand = strtolower((string) ($tire_brand ?? ''));

        if (empty(trim($brand))) {
            return ['score' => null, 'max_possible' => 0];
        }

        // Tier 1.
        $tier1 = ['/\bmaxxis\b/i', '/\bschwalbe\b/i', '/\bcontinental\b/i', '/\bpirelli\b/i', '/\bvittoria\b/i', '/\bmichelin\b/i', '/\bcadex\b/i'];
        foreach ($tier1 as $pattern) {
            if (preg_match($pattern, $brand)) {
                return ['score' => 10, 'max_possible' => 10];
            }
        }

        // Tier 2.
        $tier2 = ['/\bwtb\b/i', '/\bkenda\b/i', '/\bcst\b/i', '/\bvee\s*tire\b/i', '/\bteravail\b/i'];
        foreach ($tier2 as $pattern) {
            if (preg_match($pattern, $brand)) {
                return ['score' => 7, 'max_possible' => 10];
            }
        }

        // Tier 3.
        return ['score' => 4, 'max_possible' => 10];
    }

    /**
     * Score frame material (10 pts).
     *
     * Carbon = 10, Aluminum = 7, Steel = 5.
     */
    private function score_frame_material($frame_material): array {
        if (empty($frame_material)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $materials = is_array($frame_material) ? $frame_material : [$frame_material];
        $combined = strtolower(implode(' ', $materials));

        if (preg_match('/\b(carbon|cfrp)\b/i', $combined)) {
            return ['score' => 10, 'max_possible' => 10];
        }

        if (preg_match('/\b(alumin|alloy|6061|7005)\b/i', $combined)) {
            return ['score' => 7, 'max_possible' => 10];
        }

        if (preg_match('/\b(steel|chromoly|hi-?ten)\b/i', $combined)) {
            return ['score' => 5, 'max_possible' => 10];
        }

        return ['score' => null, 'max_possible' => 0];
    }

    /**
     * Score IP rating (10 pts).
     *
     * IPX1-3 excluded. IPX4+ scores: 4=8, 5=11, 6=13, 7+=15.
     */
    private function score_ip_rating($ip_rating): array {
        if (empty($ip_rating)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $rating = strtoupper((string) $ip_rating);

        $water_rating = 0;
        if (preg_match('/IP[X0-9]([0-9])/', $rating, $matches)) {
            $water_rating = (int) $matches[1];
        }

        // IPX1-3 excluded.
        if ($water_rating < 4) {
            return ['score' => null, 'max_possible' => 0];
        }

        $score_map = [4 => 8, 5 => 11, 6 => 13, 7 => 15, 8 => 15];
        $score = $score_map[$water_rating] ?? 8;

        return ['score' => $score, 'max_possible' => 15];
    }

    /**
     * Score certifications (5 pts).
     *
     * UL 2849 = 5, EN 15194/CE = 3, Other = 1, None = 0.
     */
    private function score_certifications($certifications): array {
        if (empty($certifications)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $certs = is_array($certifications) ? $certifications : [$certifications];
        $combined = strtolower(implode(' ', $certs));

        if (preg_match('/\bul\s*2849\b/i', $combined)) {
            return ['score' => 5, 'max_possible' => 5];
        }

        if (preg_match('/\b(en\s*15194|ce)\b/i', $combined)) {
            return ['score' => 3, 'max_possible' => 5];
        }

        if (strlen($combined) > 2 && !preg_match('/\bnone\b/i', $combined)) {
            return ['score' => 1, 'max_possible' => 5];
        }

        return ['score' => 0, 'max_possible' => 5];
    }

    // =========================================================================
    // Comfort (100 pts max)
    // =========================================================================

    /**
     * Calculate Comfort category score.
     *
     * Factors: Front Suspension Type 35pts, Front Travel 20pts,
     * Rear Suspension 30pts, Seatpost Suspension 15pts, Tire Width 15pts.
     */
    private function calculate_comfort(array $specs): ?int {
        $factors = [
            $this->score_front_suspension($this->get_nested_value($specs, 'suspension.front_suspension')),
            $this->score_front_travel($this->get_nested_value($specs, 'suspension.front_travel')),
            $this->score_rear_suspension($this->get_nested_value($specs, 'suspension.rear_suspension')),
            $this->score_seatpost_suspension($this->get_nested_value($specs, 'suspension.seatpost_suspension')),
            $this->score_tire_width($this->get_nested_value($specs, 'wheels_and_tires.tire_width')),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Score front suspension type (35 pts).
     * Air = 35, Coil = 25, Rigid = 0.
     */
    private function score_front_suspension($suspension): array {
        if (empty($suspension)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $susp = strtolower((string) $suspension);

        if (strpos($susp, 'air') !== false) {
            return ['score' => 35, 'max_possible' => 35];
        }

        if (strpos($susp, 'coil') !== false || strpos($susp, 'spring') !== false) {
            return ['score' => 25, 'max_possible' => 35];
        }

        if (strpos($susp, 'rigid') !== false || strpos($susp, 'none') !== false) {
            // Per research: rigid forks still provide base comfort (10 pts)
            // Road bikes and rigid-fork designs are valid choices, not defects
            return ['score' => 10, 'max_possible' => 35];
        }

        // Unknown - assume some suspension exists.
        return ['score' => 15, 'max_possible' => 35];
    }

    /**
     * Score front suspension travel (20 pts).
     * Linear scale: 50mm → 0pts, 180mm → 20pts.
     */
    private function score_front_travel($travel): array {
        if (!$this->is_valid_numeric($travel)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $t = (float) $travel;

        if ($t < 50) {
            return ['score' => 0, 'max_possible' => 20];
        }

        return $this->linear_scale($t, 50, 180, 20);
    }

    /**
     * Score rear suspension (30 pts).
     * Air = 30, Coil = 22, None = 0.
     */
    private function score_rear_suspension($suspension): array {
        if (empty($suspension)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $susp = strtolower((string) $suspension);

        if (strpos($susp, 'air') !== false) {
            return ['score' => 30, 'max_possible' => 30];
        }

        if (strpos($susp, 'coil') !== false || strpos($susp, 'spring') !== false) {
            return ['score' => 22, 'max_possible' => 30];
        }

        if (strpos($susp, 'none') !== false || strpos($susp, 'rigid') !== false || strpos($susp, 'hardtail') !== false || $susp === '') {
            // Per research: hardtails are a valid design choice, not a defect
            // Base points acknowledge the frame still contributes some compliance
            return ['score' => 8, 'max_possible' => 30];
        }

        // Unknown - assume some suspension exists.
        return ['score' => 15, 'max_possible' => 30];
    }

    /**
     * Score seatpost suspension (15 pts).
     */
    private function score_seatpost_suspension($has_seatpost): array {
        if ($has_seatpost === null) {
            return ['score' => null, 'max_possible' => 0];
        }
        return ['score' => $has_seatpost === true ? 15 : 0, 'max_possible' => 15];
    }

    /**
     * Score tire width for comfort (15 pts max).
     *
     * Per research: wider tires provide pneumatic cushioning.
     * Log scale: 2" = 10pts (floor), 4" = 15pts (ceiling).
     *
     * @param mixed $tire_width Tire width in inches.
     * @return array Score array with 'score' and 'max_possible'.
     */
    private function score_tire_width($tire_width): array {
        if (!$this->is_valid_numeric($tire_width)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $w = (float) $tire_width;

        // Below 2" = 10pts floor (narrow road tires still provide some cushioning)
        if ($w <= 2.0) {
            return ['score' => 10, 'max_possible' => 15];
        }

        // At or above 4" = 15pts ceiling (fat tires)
        if ($w >= 4.0) {
            return ['score' => 15, 'max_possible' => 15];
        }

        // Log scale for the 5-point range between 2" and 4"
        // Maps 2"->10pts to 4"->15pts using logarithmic curve
        $ratio = log($w / 2.0, 2) / log(4.0 / 2.0, 2);
        $score = 10 + (5 * $ratio);

        return ['score' => $score, 'max_possible' => 15];
    }

    // =========================================================================
    // Practicality (100 pts max)
    // =========================================================================

    /**
     * Calculate Practicality category score.
     *
     * Factors: Weight 40pts, Display 15pts, App 15pts, Folding 10pts,
     * Accessories 15pts, Throttle 5pts.
     */
    private function calculate_practicality(array $specs): ?int {
        $categories = $this->get_top_level_value($specs, 'category');
        $weight_thresholds = $this->get_weight_thresholds($categories);

        $factors = [
            $this->score_weight(
                $this->get_nested_value($specs, 'weight_and_capacity.weight'),
                $weight_thresholds
            ),
            $this->score_display($this->get_nested_value($specs, 'components.display')),
            $this->score_app($this->get_nested_value($specs, 'components.app_compatible')),
            $this->score_folding($categories),
            $this->score_accessories($specs),
            $this->score_throttle($this->get_nested_value($specs, 'speed_and_class.throttle')),
        ];

        return $this->calculate_category_score($factors);
    }

    /**
     * Get weight thresholds based on e-bike category.
     */
    private function get_weight_thresholds($categories): array {
        $thresholds = [
            'cargo'       => ['floor' => 65, 'ceiling' => 110],
            'fat'         => ['floor' => 55, 'ceiling' => 85],
            'fat tire'    => ['floor' => 55, 'ceiling' => 85],
            'mountain'    => ['floor' => 45, 'ceiling' => 75],
            'emtb'        => ['floor' => 45, 'ceiling' => 75],
            'commuter'    => ['floor' => 40, 'ceiling' => 70],
            'city'        => ['floor' => 40, 'ceiling' => 70],
            'folding'     => ['floor' => 35, 'ceiling' => 65],
            'lightweight' => ['floor' => 30, 'ceiling' => 50],
            'road'        => ['floor' => 30, 'ceiling' => 50],
        ];

        if (empty($categories)) {
            return ['floor' => 40, 'ceiling' => 70];
        }

        $cats = is_array($categories) ? $categories : [$categories];
        $best_floor   = 40;
        $best_ceiling = 70;

        foreach ($cats as $cat) {
            $cat_lower = strtolower((string) $cat);

            foreach ($thresholds as $key => $values) {
                if (strpos($cat_lower, $key) !== false) {
                    if ($values['ceiling'] > $best_ceiling) {
                        $best_floor   = $values['floor'];
                        $best_ceiling = $values['ceiling'];
                    }
                    break;
                }
            }
        }

        return ['floor' => $best_floor, 'ceiling' => $best_ceiling];
    }

    /**
     * Score weight (40 pts).
     *
     * Uses log scale so penalty for heavy bikes tapers off gradually.
     */
    private function score_weight($weight, array $thresholds): array {
        if (!$this->is_valid_numeric($weight)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $w       = (float) $weight;
        $floor   = $thresholds['floor'];
        $ceiling = $thresholds['ceiling'];

        if ($w <= $floor) {
            return ['score' => 40, 'max_possible' => 40];
        }

        if ($w >= $ceiling) {
            return ['score' => 0, 'max_possible' => 40];
        }

        // Log scale: heavier bikes lose points quickly at first, then tapers.
        $excess     = $w - $floor;
        $max_excess = $ceiling - $floor;

        $score = 40 * (1 - log1p($excess) / log1p($max_excess));

        return ['score' => max(0, min(40, $score)), 'max_possible' => 40];
    }

    /**
     * Score folding capability (10 pts).
     */
    private function score_folding($categories): array {
        if (empty($categories)) {
            return ['score' => 0, 'max_possible' => 10];
        }

        $cats = is_array($categories) ? $categories : [$categories];

        foreach ($cats as $cat) {
            if (strpos(strtolower((string) $cat), 'folding') !== false) {
                return ['score' => 10, 'max_possible' => 10];
            }
        }

        return ['score' => 0, 'max_possible' => 10];
    }

    /**
     * Score accessories (15 pts).
     *
     * Fenders: 4, Rear Rack: 4, Front Rack: 2, Kickstand: 3, Walk Assist: 2.
     */
    private function score_accessories(array $specs): array {
        $score = 0;

        if ($this->get_nested_value($specs, 'integrated_features.fenders') === true) {
            $score += 4;
        }
        if ($this->get_nested_value($specs, 'integrated_features.rear_rack') === true) {
            $score += 4;
        }
        if ($this->get_nested_value($specs, 'integrated_features.front_rack') === true) {
            $score += 2;
        }
        if ($this->get_nested_value($specs, 'integrated_features.kickstand') === true) {
            $score += 3;
        }
        if ($this->get_nested_value($specs, 'integrated_features.walk_assist') === true) {
            $score += 2;
        }

        return ['score' => $score, 'max_possible' => 15];
    }

    /**
     * Score display type (15 pts).
     * Color TFT = 15, LCD = 10, LED = 6, None = 0.
     */
    private function score_display($display): array {
        if (empty($display)) {
            return ['score' => null, 'max_possible' => 0];
        }

        $disp = strtolower((string) $display);

        if (strpos($disp, 'color') !== false || strpos($disp, 'tft') !== false || strpos($disp, 'colour') !== false) {
            return ['score' => 15, 'max_possible' => 15];
        }

        if (strpos($disp, 'lcd') !== false || strpos($disp, 'mono') !== false) {
            return ['score' => 10, 'max_possible' => 15];
        }

        if (strpos($disp, 'led') !== false) {
            return ['score' => 6, 'max_possible' => 15];
        }

        if (strpos($disp, 'none') !== false) {
            return ['score' => 0, 'max_possible' => 15];
        }

        // Unknown - assume basic LCD.
        return ['score' => 7, 'max_possible' => 15];
    }

    /**
     * Score app connectivity (15 pts).
     */
    private function score_app($app_compatible): array {
        if ($app_compatible === null) {
            return ['score' => null, 'max_possible' => 0];
        }

        if ($app_compatible === true) {
            return ['score' => 15, 'max_possible' => 15];
        }
        if ($app_compatible === false) {
            return ['score' => 0, 'max_possible' => 15];
        }

        $app = strtolower((string) $app_compatible);

        if (strpos($app, 'yes') !== false || strpos($app, 'full') !== false) {
            return ['score' => 15, 'max_possible' => 15];
        }

        if (strpos($app, 'basic') !== false || strpos($app, 'limited') !== false) {
            return ['score' => 9, 'max_possible' => 15];
        }

        return ['score' => 0, 'max_possible' => 15];
    }

    /**
     * Score throttle (5 pts).
     */
    private function score_throttle($throttle): array {
        if ($throttle === null) {
            return ['score' => null, 'max_possible' => 0];
        }
        return ['score' => $throttle === true ? 5 : 0, 'max_possible' => 5];
    }
}
