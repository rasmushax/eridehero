<?php
/**
 * Inline stat shortcodes: [stat], [batval], [year].
 *
 * @package ERH\Shortcodes
 */

declare(strict_types=1);

namespace ERH\Shortcodes;

/**
 * Handles the [stat], [batval], and [year] shortcodes.
 *
 * [stat stat="tested_top_speed"] renders a single formatted spec value.
 * [year] renders the current year.
 * [batval] renders price-per-Wh (stubbed until price integration).
 */
class StatShortcode {

    /**
     * @var ShortcodeManager
     */
    private ShortcodeManager $mgr;

    /**
     * Dispatch table mapping stat keys to [field_key, format_method].
     *
     * Composite stats use null field_key and receive the full fields array.
     *
     * @var array<string, array{0: string|null, 1: string}>
     */
    private const STAT_MAP = [
        // Speed stats.
        'tested_top_speed'       => ['tested_top_speed',       'format_speed'],
        'manufacturer_top_speed' => ['manufacturer_top_speed', 'format_speed'],

        // Range stats.
        'manufacturer_range' => ['manufacturer_range',   'format_distance'],
        'range_fast'         => ['tested_range_fast',    'format_distance'],
        'range_regular'      => ['tested_range_regular', 'format_distance'],
        'range_slow'         => ['tested_range_slow',    'format_distance'],

        // Range average speed stats.
        'range_speed_fast'    => ['tested_range_avg_speed_fast',    'format_speed'],
        'range_speed_regular' => ['tested_range_avg_speed_regular', 'format_speed'],
        'range_speed_slow'    => ['tested_range_avg_speed_slow',    'format_speed'],

        // Acceleration average stats.
        'accel_avg_15'  => ['acceleration:_0-15_mph', 'format_seconds'],
        'accel_avg_20'  => ['acceleration:_0-20_mph', 'format_seconds'],
        'accel_avg_25'  => ['acceleration:_0-25_mph', 'format_seconds'],
        'accel_avg_30'  => ['acceleration:_0-30_mph', 'format_seconds'],
        'accel_avg_top' => ['acceleration:_0-to-top', 'format_seconds'],

        // Acceleration best stats.
        'accel_15'  => ['fastest_0_15',  'format_seconds'],
        'accel_20'  => ['fastest_0_20',  'format_seconds'],
        'accel_25'  => ['fastest_0_25',  'format_seconds'],
        'accel_30'  => ['fastest_0_30',  'format_seconds'],
        'accel_top' => ['fastest_0_top', 'format_seconds'],

        // Weight / load.
        'weight'  => ['weight',   'format_weight'],
        'maxload' => ['max_load', 'format_weight'],

        // Battery.
        'wh' => ['battery_capacity', 'format_wh'],

        // Measurements.
        'ground_clearance' => ['ground_clearance', 'format_inches'],
        'handlebars'       => ['handlebar_width',  'format_inches'],

        // Braking.
        'braking' => ['brake_distance', 'format_braking'],

        // Hill climbing.
        'hill_climb'       => ['hill_climbing', 'format_hill_climb'],
        'hill_climb_short' => ['hill_climbing', 'format_hill_climb_short'],

        // Composite stats (null field_key, pass full fields array).
        'deck'     => [null, 'format_deck'],
        'folded'   => [null, 'format_folded'],
        'unfolded' => [null, 'format_unfolded'],
    ];

    /**
     * @param ShortcodeManager $mgr The shortcode manager.
     */
    public function __construct(ShortcodeManager $mgr) {
        $this->mgr = $mgr;
    }

    /**
     * Render the [stat] shortcode.
     *
     * Usage: [stat stat="tested_top_speed"] or [stat stat="weight" products="12345"]
     *
     * @param array|string $atts Shortcode attributes.
     * @return string Formatted stat value or empty string.
     */
    public function render($atts): string {
        $atts = shortcode_atts([
            'stat'     => '',
            'products' => 'this',
        ], $atts);

        $stat = sanitize_key($atts['stat']);
        if ($stat === '' || !isset(self::STAT_MAP[$stat])) {
            return '';
        }

        $product_ids = ProductDataHelper::parse_product_ids($atts['products'], $this->mgr);
        if (empty($product_ids)) {
            return '';
        }

        $fields = ProductDataHelper::get_product_fields($product_ids[0]);
        if (!$fields) {
            return '';
        }

        [$field_key, $method] = self::STAT_MAP[$stat];

        // Composite stat — pass full fields array.
        if ($field_key === null) {
            return $this->$method($fields);
        }

        $value = ProductDataHelper::get_spec($fields, $field_key);
        if ($value === null || $value === '' || $value === false) {
            return '';
        }

        return $this->$method((float) $value);
    }

    /**
     * Render the [year] shortcode.
     *
     * @param array|string $atts Shortcode attributes (unused).
     * @return string Current year.
     */
    public function render_year($atts): string {
        return esc_html(gmdate('Y'));
    }

    /**
     * Render the [batval] shortcode.
     *
     * Returns price-per-Wh for the product. Stubbed until price integration.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string Formatted value or empty string.
     */
    public function render_batval($atts): string {
        $atts = shortcode_atts([
            'id' => 'this',
        ], $atts);

        $product_ids = ProductDataHelper::parse_product_ids($atts['id'], $this->mgr);
        if (empty($product_ids)) {
            return '';
        }

        $fields = ProductDataHelper::get_product_fields($product_ids[0]);
        if (!$fields) {
            return '';
        }

        $wh = (float) (ProductDataHelper::get_spec($fields, 'battery_capacity') ?? 0);
        if ($wh <= 0) {
            return '';
        }

        // Price integration stubbed — will integrate PriceFetcher later.
        return '';
    }

    // -------------------------------------------------------------------------
    // Format methods
    // -------------------------------------------------------------------------

    /**
     * Format a speed value with MPH and KMH.
     *
     * @param float $value Speed in MPH.
     * @return string Formatted string.
     */
    private function format_speed(float $value): string {
        if ($value <= 0) {
            return '';
        }
        $km = ProductDataHelper::convert($value, ProductDataHelper::MILE_TO_KM, 1);
        return esc_html($value . ' MPH (' . $km . ' KMH)');
    }

    /**
     * Format a distance value with miles and km.
     *
     * @param float $value Distance in miles.
     * @return string Formatted string.
     */
    private function format_distance(float $value): string {
        if ($value <= 0) {
            return '';
        }
        $km = ProductDataHelper::convert($value, ProductDataHelper::MILE_TO_KM, 1);
        return esc_html($value . ' miles (' . $km . ' km)');
    }

    /**
     * Format a time value in seconds.
     *
     * @param float $value Time in seconds.
     * @return string Formatted string.
     */
    private function format_seconds(float $value): string {
        if ($value <= 0) {
            return '';
        }
        return esc_html($value . ' seconds');
    }

    /**
     * Format a weight value with lbs and kg.
     *
     * @param float $value Weight in lbs.
     * @return string Formatted string.
     */
    private function format_weight(float $value): string {
        if ($value <= 0) {
            return '';
        }
        $kg = ProductDataHelper::convert($value, ProductDataHelper::LB_TO_KG, 1);
        return esc_html($value . ' lbs (' . $kg . ' kg)');
    }

    /**
     * Format a watt-hour value.
     *
     * @param float $value Watt-hours.
     * @return string Formatted string.
     */
    private function format_wh(float $value): string {
        if ($value <= 0) {
            return '';
        }
        return esc_html($value . ' Wh');
    }

    /**
     * Format an inches value with cm conversion.
     *
     * @param float $value Length in inches.
     * @return string Formatted string.
     */
    private function format_inches(float $value): string {
        if ($value <= 0) {
            return '';
        }
        $cm = ProductDataHelper::convert($value, ProductDataHelper::IN_TO_CM, 1);
        return esc_html($value . '" (' . $cm . ' cm)');
    }

    /**
     * Format a braking distance with ft and m.
     *
     * @param float $value Distance in feet.
     * @return string Formatted string.
     */
    private function format_braking(float $value): string {
        if ($value <= 0) {
            return '';
        }
        $m = ProductDataHelper::convert($value, ProductDataHelper::FT_TO_M, 2);
        return esc_html($value . ' ft (' . $m . ' m)');
    }

    /**
     * Format hill climb time with average speed (full version).
     *
     * Speed = FPS_TO_MPH * (250 / time_seconds) based on 250 ft test hill.
     *
     * @param float $value Time in seconds.
     * @return string Formatted string.
     */
    private function format_hill_climb(float $value): string {
        if ($value <= 0) {
            return '';
        }
        $speed    = ProductDataHelper::FPS_TO_MPH * (250 / $value);
        $speed_km = ProductDataHelper::convert((float) $speed, ProductDataHelper::MILE_TO_KM, 1);
        return esc_html(
            $value . ' seconds with an average speed of '
            . round($speed, 1) . ' MPH (' . $speed_km . ' KMH)'
        );
    }

    /**
     * Format hill climb time with average speed (short version).
     *
     * @param float $value Time in seconds.
     * @return string Formatted string.
     */
    private function format_hill_climb_short(float $value): string {
        if ($value <= 0) {
            return '';
        }
        $speed = ProductDataHelper::FPS_TO_MPH * (250 / $value);
        return esc_html($value . ' s (Avg Speed: ' . round($speed, 1) . ' MPH)');
    }

    /**
     * Format deck dimensions (length x width) with cm conversion.
     *
     * @param array $fields Full product fields array.
     * @return string Formatted string.
     */
    private function format_deck(array $fields): string {
        $length = (float) (ProductDataHelper::get_spec($fields, 'deck_length') ?? 0);
        $width  = (float) (ProductDataHelper::get_spec($fields, 'deck_width') ?? 0);
        if ($length <= 0 || $width <= 0) {
            return '';
        }
        $length_cm = ProductDataHelper::convert($length, ProductDataHelper::IN_TO_CM, 1);
        $width_cm  = ProductDataHelper::convert($width, ProductDataHelper::IN_TO_CM, 1);
        return esc_html(
            $length . '" x ' . $width . '" ('
            . $length_cm . ' cm x ' . $width_cm . ' cm)'
        );
    }

    /**
     * Format 3D dimensions (width x height x depth/length) with cm conversion.
     *
     * @param array  $fields Full product fields array.
     * @param string $prefix Dimension prefix ('folded' or 'unfolded').
     * @return string Formatted string.
     */
    private function format_dimensions(array $fields, string $prefix): string {
        $width  = (float) (ProductDataHelper::get_spec($fields, $prefix . '_width') ?? 0);
        $height = (float) (ProductDataHelper::get_spec($fields, $prefix . '_height') ?? 0);
        $depth  = (float) (ProductDataHelper::get_spec($fields, $prefix . '_depth') ?? 0);
        if ($depth <= 0) {
            $depth = (float) (ProductDataHelper::get_spec($fields, $prefix . '_length') ?? 0);
        }

        if ($width <= 0 || $height <= 0 || $depth <= 0) {
            return '';
        }

        $width_cm  = ProductDataHelper::convert($width, ProductDataHelper::IN_TO_CM, 1);
        $height_cm = ProductDataHelper::convert($height, ProductDataHelper::IN_TO_CM, 1);
        $depth_cm  = ProductDataHelper::convert($depth, ProductDataHelper::IN_TO_CM, 1);
        return esc_html(
            $width . ' x ' . $height . ' x ' . $depth . ' in ('
            . $width_cm . ' x ' . $height_cm . ' x ' . $depth_cm . ' cm)'
        );
    }

    /**
     * Format folded dimensions.
     *
     * @param array $fields Full product fields array.
     * @return string Formatted string.
     */
    private function format_folded(array $fields): string {
        return $this->format_dimensions($fields, 'folded');
    }

    /**
     * Format unfolded dimensions.
     *
     * @param array $fields Full product fields array.
     * @return string Formatted string.
     */
    private function format_unfolded(array $fields): string {
        return $this->format_dimensions($fields, 'unfolded');
    }
}
