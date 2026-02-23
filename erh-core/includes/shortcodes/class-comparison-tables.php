<?php
/**
 * Comparison table shortcodes for review posts.
 *
 * Handles 11 comparison table shortcodes plus [rangetest] and [acceltest].
 *
 * @package ERH\Shortcodes
 */

declare(strict_types=1);

namespace ERH\Shortcodes;

/**
 * Renders comparison tables that compare specs across multiple products.
 *
 * Each method follows the same pattern:
 * 1. Parse `ids` attribute into product IDs
 * 2. Loop products, load fields, extract values
 * 3. Build rows array
 * 4. Return build_table(headers, rows, caption)
 *
 * The "this" product (from the review's relationship field) is underlined
 * in the model cell to match old behavior.
 */
class ComparisonTables {

    /**
     * @var ShortcodeManager
     */
    private ShortcodeManager $mgr;

    /**
     * @param ShortcodeManager $mgr The shortcode manager.
     */
    public function __construct(ShortcodeManager $mgr) {
        $this->mgr = $mgr;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Get a float spec value, defaulting to 0.
     *
     * @param array  $fields Product fields.
     * @param string $key    Field key.
     * @return float The value or 0.
     */
    private function float_spec(array $fields, string $key): float {
        return (float) (ProductDataHelper::get_spec($fields, $key) ?? 0);
    }

    /**
     * Format speed with dual units (MPH / KMH).
     *
     * @param float $mph Speed in MPH.
     * @return string HTML string.
     */
    private function format_dual_speed(float $mph): string {
        if ($mph <= 0) {
            return '-';
        }
        $kmh = ProductDataHelper::convert($mph, ProductDataHelper::MILE_TO_KM, 1);
        return esc_html($mph . ' MPH') . '<br>(' . esc_html($kmh . ' KMH') . ')';
    }

    /**
     * Format distance with dual units (miles / km).
     *
     * @param float $mi Distance in miles.
     * @return string HTML string.
     */
    private function format_dual_distance(float $mi): string {
        if ($mi <= 0) {
            return '-';
        }
        $km = ProductDataHelper::convert($mi, ProductDataHelper::MILE_TO_KM, 1);
        return esc_html($mi . ' miles') . '<br>(' . esc_html($km . ' km') . ')';
    }

    /**
     * Format weight with dual units (lbs / kg).
     *
     * @param float $lbs Weight in lbs.
     * @return string HTML string.
     */
    private function format_dual_weight(float $lbs): string {
        if ($lbs <= 0) {
            return '-';
        }
        $kg = ProductDataHelper::convert($lbs, ProductDataHelper::LB_TO_KG, 1);
        return esc_html($lbs . ' lbs') . '<br>(' . esc_html($kg . ' kg') . ')';
    }

    /**
     * Build the model cell, underlining the "this" product.
     *
     * @param int    $product_id The product post ID.
     * @param string $name       The product name.
     * @return string HTML string for the model cell.
     */
    private function model_cell(int $product_id, string $name): string {
        $this_id = $this->mgr->resolve_this_product();
        return ($product_id === $this_id)
            ? '<u>' . esc_html($name) . '</u>'
            : esc_html($name);
    }

    // -------------------------------------------------------------------------
    // Shortcode handlers
    // -------------------------------------------------------------------------

    /**
     * [speedcomp ids="123,456" price="no"]
     *
     * Top speed comparison table.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML table.
     */
    public function speedcomp($atts): string {
        $atts = shortcode_atts([
            'ids'   => '',
            'price' => 'no',
        ], $atts);

        $product_ids = ProductDataHelper::parse_product_ids($atts['ids'], $this->mgr);
        if (empty($product_ids)) {
            return '';
        }

        $show_price = ($atts['price'] === 'yes');
        $headers    = ['Model', 'Top Speed'];
        if ($show_price) {
            $headers[] = '$/Speed';
        }

        $rows = [];
        foreach ($product_ids as $pid) {
            $fields = ProductDataHelper::get_product_fields($pid);
            $name   = get_the_title($pid);
            if (!$fields || empty($name)) {
                continue;
            }

            $speed = $this->float_spec($fields, 'tested_top_speed');

            $row   = [];
            $row[] = $this->model_cell($pid, $name);
            $row[] = ($speed > 0) ? $this->format_dual_speed($speed) : 'N/A';

            // Price per speed (stubbed).
            if ($show_price) {
                $row[] = '-';
            }

            $rows[] = $row;
        }

        if (empty($rows)) {
            return '';
        }
        return ProductDataHelper::build_table($headers, $rows);
    }

    /**
     * [rangetest]
     *
     * Range test results table for the current review product (3 test modes).
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML table.
     */
    public function rangetest($atts): string {
        $this_id = $this->mgr->resolve_this_product();
        if (!$this_id) {
            return '';
        }

        $fields = ProductDataHelper::get_product_fields($this_id);
        if (!$fields) {
            return '';
        }

        $tests = [
            ['#1: Speed Priority', 'tested_range_fast',    'tested_range_avg_speed_fast'],
            ['#2: Regular',        'tested_range_regular', 'tested_range_avg_speed_regular'],
            ['#3: Range Priority', 'tested_range_slow',    'tested_range_avg_speed_slow'],
        ];

        $rows = [];
        foreach ($tests as [$label, $range_key, $speed_key]) {
            $range = $this->float_spec($fields, $range_key);
            $speed = $this->float_spec($fields, $speed_key);
            if ($range <= 0 || $speed <= 0) {
                continue;
            }

            $rows[] = [
                esc_html($label),
                $this->format_dual_distance($range),
                $this->format_dual_speed($speed),
            ];
        }

        if (empty($rows)) {
            return '';
        }
        return ProductDataHelper::build_table(['Test (#)', 'Range', 'Avg. Speed'], $rows);
    }

    /**
     * [acceltest]
     *
     * Acceleration test results table for the current review product.
     * Shows Average and optionally Best columns for 0-15/20/25/30/top speed intervals.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML table.
     */
    public function acceltest($atts): string {
        $this_id = $this->mgr->resolve_this_product();
        if (!$this_id) {
            return '';
        }

        $fields = ProductDataHelper::get_product_fields($this_id);
        if (!$fields) {
            return '';
        }

        // Check if we have any "Best" times.
        $best_keys = ['fastest_0_15', 'fastest_0_20', 'fastest_0_25', 'fastest_0_30', 'fastest_0_top'];
        $has_best  = false;
        foreach ($best_keys as $bk) {
            if (!empty(ProductDataHelper::get_spec($fields, $bk))) {
                $has_best = true;
                break;
            }
        }

        $headers = ['Interval', 'Average'];
        if ($has_best) {
            $headers[] = 'Best';
        }

        $tests = [
            ['0-15 MPH (24 KMH)',   'acceleration:_0-15_mph', 'fastest_0_15'],
            ['0-20 MPH (32.2 KMH)', 'acceleration:_0-20_mph', 'fastest_0_20'],
            ['0-25 MPH (40.2 KMH)', 'acceleration:_0-25_mph', 'fastest_0_25'],
            ['0-30 MPH (48.2 KMH)', 'acceleration:_0-30_mph', 'fastest_0_30'],
        ];

        $rows = [];
        foreach ($tests as [$interval, $avg_key, $best_key]) {
            $avg = ProductDataHelper::get_spec($fields, $avg_key);
            if (empty($avg)) {
                continue;
            }

            $row = [
                esc_html($interval),
                esc_html($avg . ' s'),
            ];

            if ($has_best) {
                $best = ProductDataHelper::get_spec($fields, $best_key);
                $row[] = !empty($best) ? esc_html($best . ' s') : '';
            }

            $rows[] = $row;
        }

        // Top speed acceleration row.
        $accel_top = ProductDataHelper::get_spec($fields, 'acceleration:_0-to-top');
        $top_speed = $this->float_spec($fields, 'tested_top_speed');
        if (!empty($accel_top) && $top_speed > 0) {
            $top_speed_km = ProductDataHelper::convert($top_speed, ProductDataHelper::MILE_TO_KM, 1);

            $row = [
                esc_html('0-' . $top_speed . ' MPH (' . $top_speed_km . ' KMH)'),
                esc_html($accel_top . ' s'),
            ];

            if ($has_best) {
                $best_top = ProductDataHelper::get_spec($fields, 'fastest_0_top');
                $row[] = !empty($best_top) ? esc_html($best_top . ' s') : '';
            }

            $rows[] = $row;
        }

        if (empty($rows)) {
            return '';
        }
        return ProductDataHelper::build_table($headers, $rows);
    }

    /**
     * [accelcomp ids="123,456" speeds="15,20"]
     *
     * Acceleration comparison table across multiple products.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML table.
     */
    public function accelcomp($atts): string {
        $atts = shortcode_atts([
            'ids'    => '',
            'speeds' => '',
        ], $atts);

        $product_ids = ProductDataHelper::parse_product_ids($atts['ids'], $this->mgr);
        if (empty($product_ids)) {
            return '';
        }

        $accel_keys = ['15', '20', '25', '30'];
        $field_map  = [
            '15' => 'acceleration:_0-15_mph',
            '20' => 'acceleration:_0-20_mph',
            '25' => 'acceleration:_0-25_mph',
            '30' => 'acceleration:_0-30_mph',
        ];

        // Collect data and track which speeds have values.
        $items        = [];
        $speeds_found = [];

        foreach ($product_ids as $pid) {
            $fields = ProductDataHelper::get_product_fields($pid);
            $name   = get_the_title($pid);
            if (!$fields || empty($name)) {
                continue;
            }

            $speed_data = [];
            foreach ($accel_keys as $s) {
                $val = ProductDataHelper::get_spec($fields, $field_map[$s]);
                $speed_data[$s] = $val;
                if (!empty($val)) {
                    $speeds_found[$s] = true;
                }
            }

            $items[] = ['pid' => $pid, 'name' => $name, 'data' => $speed_data];
        }

        // Override with specific speeds if provided.
        if (!empty($atts['speeds'])) {
            $speeds_found = [];
            foreach (array_map('trim', explode(',', $atts['speeds'])) as $s) {
                if (is_numeric($s)) {
                    $speeds_found[$s] = true;
                }
            }
        }

        if (empty($items) || empty($speeds_found)) {
            return '';
        }

        // Build headers.
        $headers = ['Model'];
        foreach (array_keys($speeds_found) as $s) {
            $headers[] = '0-' . $s . ' MPH';
        }

        // Build rows.
        $rows = [];
        foreach ($items as $item) {
            $row = [$this->model_cell($item['pid'], $item['name'])];
            foreach (array_keys($speeds_found) as $s) {
                $val   = $item['data'][$s] ?? '';
                $row[] = !empty($val) ? esc_html($val . ' s') : '-';
            }
            $rows[] = $row;
        }

        return ProductDataHelper::build_table($headers, $rows);
    }

    /**
     * [hillcomp ids="123,456"]
     *
     * Hill climb comparison table with time and average speed.
     * Speed formula: FPS_TO_MPH * (250 / time_seconds) based on 250 ft test hill.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML table.
     */
    public function hillcomp($atts): string {
        $atts = shortcode_atts([
            'ids' => '',
        ], $atts);

        $product_ids = ProductDataHelper::parse_product_ids($atts['ids'], $this->mgr);
        if (empty($product_ids)) {
            return '';
        }

        $rows = [];
        foreach ($product_ids as $pid) {
            $fields = ProductDataHelper::get_product_fields($pid);
            $name   = get_the_title($pid);
            if (!$fields || empty($name)) {
                continue;
            }

            $time = $this->float_spec($fields, 'hill_climbing');
            if ($time <= 0) {
                continue;
            }

            $speed    = ProductDataHelper::FPS_TO_MPH * (250 / $time);
            $speed_km = ProductDataHelper::convert((float) $speed, ProductDataHelper::MILE_TO_KM, 1);

            $rows[] = [
                $this->model_cell($pid, $name),
                esc_html($time . ' s'),
                esc_html(round($speed, 1) . ' MPH (' . $speed_km . ' KMH)'),
            ];
        }

        if (empty($rows)) {
            return '';
        }
        return ProductDataHelper::build_table(['Model', 'Time', 'Speed'], $rows);
    }

    /**
     * [rangecomp ids="123,456" test="2"]
     *
     * Range comparison table for a specific test mode (1=fast, 2=regular, 3=slow).
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML table.
     */
    public function rangecomp($atts): string {
        $atts = shortcode_atts([
            'ids'  => '',
            'test' => '2',
        ], $atts);

        $product_ids = ProductDataHelper::parse_product_ids($atts['ids'], $this->mgr);
        if (empty($product_ids)) {
            return '';
        }

        $test_num    = max(1, min(3, (int) $atts['test']));
        $test_fields = [
            1 => ['tested_range_fast',    'tested_range_avg_speed_fast'],
            2 => ['tested_range_regular', 'tested_range_avg_speed_regular'],
            3 => ['tested_range_slow',    'tested_range_avg_speed_slow'],
        ];

        [$range_key, $speed_key] = $test_fields[$test_num];

        $rows = [];
        foreach ($product_ids as $pid) {
            $fields = ProductDataHelper::get_product_fields($pid);
            $name   = get_the_title($pid);
            if (!$fields || empty($name)) {
                continue;
            }

            $range = $this->float_spec($fields, $range_key);
            $speed = $this->float_spec($fields, $speed_key);

            $rows[] = [
                $this->model_cell($pid, $name),
                ($range > 0) ? $this->format_dual_distance($range) : '-',
                ($speed > 0) ? $this->format_dual_speed($speed) : '-',
            ];
        }

        if (empty($rows)) {
            return '';
        }

        $captions = [
            1 => 'Test #1 (Speed Priority), 175 lbs (80 kg) rider',
            2 => 'Test #2 (Regular Speed), 175 lbs (80 kg) rider',
            3 => 'Test #3 (Range Priority), 175 lbs (80 kg) rider',
        ];

        return ProductDataHelper::build_table(
            ['Model', 'Range', 'Avg. Speed'],
            $rows,
            $captions[$test_num]
        );
    }

    /**
     * [rangevsweight ids="123,456"]
     *
     * Range vs weight efficiency table with miles-per-lb ratio.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML table.
     */
    public function rangevsweight($atts): string {
        $atts = shortcode_atts([
            'ids' => '',
        ], $atts);

        $product_ids = ProductDataHelper::parse_product_ids($atts['ids'], $this->mgr);
        if (empty($product_ids)) {
            return '';
        }

        $rows = [];
        foreach ($product_ids as $pid) {
            $fields = ProductDataHelper::get_product_fields($pid);
            $name   = get_the_title($pid);
            if (!$fields || empty($name)) {
                continue;
            }

            $weight = $this->float_spec($fields, 'weight');
            $range  = $this->float_spec($fields, 'tested_range_regular');
            if ($weight <= 0 || $range <= 0) {
                continue;
            }

            $ratio = round($range / $weight, 2);

            $rows[] = [
                $this->model_cell($pid, $name),
                esc_html($range . ' miles'),
                esc_html($weight . ' lbs'),
                esc_html($ratio . ' miles/lb'),
            ];
        }

        if (empty($rows)) {
            return '';
        }
        return ProductDataHelper::build_table(['Model', 'Range', 'Weight', 'Ratio'], $rows);
    }

    /**
     * [weight ids="123,456"]
     *
     * Weight comparison table with lbs and kg columns.
     *
     * Method named weight_table to avoid confusion; registered as 'weight' shortcode.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML table.
     */
    public function weight_table($atts): string {
        $atts = shortcode_atts([
            'ids' => '',
        ], $atts);

        $product_ids = ProductDataHelper::parse_product_ids($atts['ids'], $this->mgr);
        if (empty($product_ids)) {
            return '';
        }

        $rows = [];
        foreach ($product_ids as $pid) {
            $fields = ProductDataHelper::get_product_fields($pid);
            $name   = get_the_title($pid);
            if (!$fields || empty($name)) {
                continue;
            }

            $weight = $this->float_spec($fields, 'weight');
            if ($weight <= 0) {
                continue;
            }

            $weight_kg = ProductDataHelper::convert($weight, ProductDataHelper::LB_TO_KG, 1);

            $rows[] = [
                $this->model_cell($pid, $name),
                esc_html($weight . ' lbs'),
                esc_html($weight_kg . ' kg'),
            ];
        }

        if (empty($rows)) {
            return '';
        }
        return ProductDataHelper::build_table(
            ['Model', 'Weight (lbs)', 'Weight (kg)'],
            $rows,
            'Based on our own high-precision weight measurements.'
        );
    }

    /**
     * [braking ids="123,456"]
     *
     * Braking distance comparison table.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML table.
     */
    public function braking($atts): string {
        $atts = shortcode_atts([
            'ids' => '',
        ], $atts);

        $product_ids = ProductDataHelper::parse_product_ids($atts['ids'], $this->mgr);
        if (empty($product_ids)) {
            return '';
        }

        $rows = [];
        foreach ($product_ids as $pid) {
            $fields = ProductDataHelper::get_product_fields($pid);
            $name   = get_the_title($pid);
            if (!$fields || empty($name)) {
                continue;
            }

            $distance = $this->float_spec($fields, 'brake_distance');
            if ($distance <= 0) {
                continue;
            }

            $distance_m = ProductDataHelper::convert($distance, ProductDataHelper::FT_TO_M, 1);

            $rows[] = [
                $this->model_cell($pid, $name),
                esc_html($distance . ' ft (' . $distance_m . ' m)'),
            ];
        }

        if (empty($rows)) {
            return '';
        }
        return ProductDataHelper::build_table(
            ['Model', 'Braking Distance'],
            $rows,
            'Braking from 15 MPH (24.2 KMH).'
        );
    }

    /**
     * [batcapcomp ids="123,456" price="no"]
     *
     * Battery capacity comparison table with optional price-per-Wh column.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML table.
     */
    public function batcapcomp($atts): string {
        $atts = shortcode_atts([
            'ids'   => '',
            'price' => 'no',
        ], $atts);

        $product_ids = ProductDataHelper::parse_product_ids($atts['ids'], $this->mgr);
        if (empty($product_ids)) {
            return '';
        }

        $show_price = ($atts['price'] === 'yes');
        $headers    = ['Model', 'Battery Capacity'];
        if ($show_price) {
            $headers[] = '$/Wh';
        }

        $rows = [];
        foreach ($product_ids as $pid) {
            $fields = ProductDataHelper::get_product_fields($pid);
            $name   = get_the_title($pid);
            if (!$fields || empty($name)) {
                continue;
            }

            $wh = $this->float_spec($fields, 'battery_capacity');
            $ah = ProductDataHelper::get_spec($fields, 'battery_amphours');
            $v  = ProductDataHelper::get_spec($fields, 'battery_voltage');

            $row   = [];
            $row[] = $this->model_cell($pid, $name);

            // Battery capacity cell.
            if ($wh > 0) {
                $bat_html = esc_html($wh . ' Wh');
                if (!empty($v) && !empty($ah)) {
                    $bat_html .= '<br>(' . esc_html($v . 'V, ' . $ah . 'Ah') . ')';
                }
                $row[] = $bat_html;
            } else {
                $row[] = '-';
            }

            // Price per Wh (stubbed).
            if ($show_price) {
                $row[] = '-';
            }

            $rows[] = $row;
        }

        if (empty($rows)) {
            return '';
        }

        $caption = $show_price
            ? 'Based on current best prices (updated every 24 hours)'
            : '';
        return ProductDataHelper::build_table($headers, $rows, $caption);
    }

    /**
     * [ipcomp ids="123,456"]
     *
     * IP rating comparison table.
     *
     * @param array|string $atts Shortcode attributes.
     * @return string HTML table.
     */
    public function ipcomp($atts): string {
        $atts = shortcode_atts([
            'ids' => '',
        ], $atts);

        $product_ids = ProductDataHelper::parse_product_ids($atts['ids'], $this->mgr);
        if (empty($product_ids)) {
            return '';
        }

        $rows = [];
        foreach ($product_ids as $pid) {
            $fields = ProductDataHelper::get_product_fields($pid);
            $name   = get_the_title($pid);
            if (!$fields || empty($name)) {
                continue;
            }

            // Try new ip_rating field, fall back to legacy weather_resistance.
            $ip = ProductDataHelper::get_spec($fields, 'ip_rating');
            if (empty($ip)) {
                $ip = ProductDataHelper::get_spec($fields, 'weather_resistance');
            }

            // Handle legacy array format.
            if (is_array($ip) && !empty($ip)) {
                $ip = $ip[0];
            }

            if (empty($ip)) {
                continue;
            }

            $rows[] = [
                $this->model_cell($pid, $name),
                esc_html((string) $ip),
            ];
        }

        if (empty($rows)) {
            return '';
        }
        return ProductDataHelper::build_table(['Model', 'IP Rating'], $rows);
    }
}
