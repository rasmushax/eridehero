<?php
/**
 * Product Migration Handler
 *
 * Migrates products from eridehero.com via REST API.
 * Maps old field names to new clean structure.
 *
 * @package ERH\Migration
 */

namespace ERH\Migration;

use ERH\PostTypes\Taxonomies;

/**
 * Handles product data migration.
 */
class ProductMigrator {

    /**
     * Source site URL for remote migration.
     *
     * @var string
     */
    private string $source_url;

    /**
     * Whether to run in dry-run mode.
     *
     * @var bool
     */
    private bool $dry_run = false;

    /**
     * Migration log.
     *
     * @var array
     */
    private array $log = [];

    /**
     * Field name mappings (old => new) for performance tests.
     * Removes colons and standardizes naming.
     *
     * @var array
     */
    private const PERF_FIELD_MAPPINGS = [
        'acceleration:_0-15_mph' => 'acceleration_0_15_mph',
        'acceleration:_0-20_mph' => 'acceleration_0_20_mph',
        'acceleration:_0-25_mph' => 'acceleration_0_25_mph',
        'acceleration:_0-30_mph' => 'acceleration_0_30_mph',
        'acceleration:_0-to-top' => 'acceleration_0_to_top',
    ];

    /**
     * Constructor.
     *
     * @param string $source_url The source site URL (e.g., https://eridehero.com).
     */
    public function __construct(string $source_url = '') {
        $this->source_url = rtrim($source_url, '/');
    }

    /**
     * Set dry-run mode.
     *
     * @param bool $dry_run Whether to enable dry-run mode.
     * @return self
     */
    public function set_dry_run(bool $dry_run): self {
        $this->dry_run = $dry_run;
        return $this;
    }

    /**
     * Fetch products from remote source.
     *
     * @param int $page    Page number.
     * @param int $per_page Products per page.
     * @return array|null Products data or null on failure.
     */
    public function fetch_remote_products(int $page = 1, int $per_page = 100): ?array {
        if (empty($this->source_url)) {
            $this->log('error', 'No source URL configured');
            return null;
        }

        $url = sprintf(
            '%s/wp-json/wp/v2/products?page=%d&per_page=%d&_fields=id,title,slug,status,acf,featured_media',
            $this->source_url,
            $page,
            $per_page
        );

        $this->log('info', "Fetching: {$url}");

        $response = wp_remote_get($url, [
            'timeout' => 60,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            $this->log('error', 'Request failed: ' . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->log('error', "HTTP {$code} response");
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('error', 'JSON decode error: ' . json_last_error_msg());
            return null;
        }

        $total = wp_remote_retrieve_header($response, 'x-wp-total');
        $total_pages = wp_remote_retrieve_header($response, 'x-wp-totalpages');

        $this->log('info', "Fetched " . count($data) . " products (page {$page}/{$total_pages}, total: {$total})");

        return [
            'products'    => $data,
            'total'       => (int) $total,
            'total_pages' => (int) $total_pages,
            'page'        => $page,
        ];
    }

    /**
     * Migrate a single product from source to local.
     *
     * @param array $old_data The product data from source (with ACF fields).
     * @return int|null The new/updated post ID or null on failure.
     */
    public function migrate_product(array $old_data): ?int {
        $title = $old_data['title']['rendered'] ?? $old_data['title'] ?? '';
        $slug = $old_data['slug'] ?? '';

        $this->log('info', "Migrating: {$title}");

        // Check if product already exists by slug.
        $existing = get_page_by_path($slug, OBJECT, 'products');
        $post_id = $existing ? $existing->ID : null;

        $acf = $old_data['acf'] ?? [];

        // Map old product_type to taxonomy.
        $old_product_type = $acf['product_type'] ?? '';
        $product_type_slug = Taxonomies::map_old_product_type($old_product_type);

        // If product_type is empty, try to infer from title or skip.
        if (!$product_type_slug) {
            $product_type_slug = $this->infer_product_type($title, $acf);
            if (!$product_type_slug) {
                $this->log('warning', "Skipping {$title} - could not determine product type");
                return null;
            }
            $this->log('info', "Inferred product type '{$product_type_slug}' for {$title}");
        }

        // Prepare post data.
        $post_data = [
            'post_title'  => wp_strip_all_tags($title),
            'post_name'   => $slug,
            'post_status' => $old_data['status'] ?? 'publish',
            'post_type'   => 'products',
        ];

        if ($this->dry_run) {
            $this->log('dry-run', "Would create/update: {$title}");
            return null;
        }

        // Create or update post.
        if ($post_id) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }

        if (is_wp_error($result)) {
            $this->log('error', "Failed to save {$title}: " . $result->get_error_message());
            return null;
        }

        $post_id = $result;

        // Set product_type taxonomy.
        wp_set_object_terms($post_id, $product_type_slug, 'product_type');

        // Set brand taxonomy.
        $brand = $acf['brand'] ?? '';
        if (!empty($brand)) {
            $brand_id = Taxonomies::get_or_create_brand($brand);
            if ($brand_id) {
                wp_set_object_terms($post_id, [$brand_id], 'brand');
            }
        }

        // Migrate ACF fields based on product type.
        $this->migrate_acf_fields($post_id, $acf, $product_type_slug);

        // Migrate image.
        $this->migrate_image($post_id, $acf, $old_data);

        $this->log('success', "Migrated: {$title} (ID: {$post_id})");

        return $post_id;
    }

    /**
     * Migrate ACF fields from source to local.
     *
     * @param int    $post_id           The post ID.
     * @param array  $acf               Source ACF data.
     * @param string $product_type_slug The product type slug.
     * @return void
     */
    private function migrate_acf_fields(int $post_id, array $acf, string $product_type_slug): void {
        // Basic info (all products).
        $this->migrate_basic_info($post_id, $acf);

        // Performance tests (all products).
        $this->migrate_performance_tests($post_id, $acf);

        // Product-type specific fields.
        switch ($product_type_slug) {
            case 'electric-bike':
                $this->migrate_ebike_fields($post_id, $acf);
                break;
            case 'electric-scooter':
                $this->migrate_escooter_fields($post_id, $acf);
                break;
            case 'electric-skateboard':
                $this->migrate_eskateboard_fields($post_id, $acf);
                break;
            case 'hoverboard':
                $this->migrate_hoverboard_fields($post_id, $acf);
                break;
            case 'electric-unicycle':
                $this->migrate_euc_fields($post_id, $acf);
                break;
        }
    }

    /**
     * Migrate basic info fields.
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Source ACF data.
     * @return void
     */
    private function migrate_basic_info(int $post_id, array $acf): void {
        $this->set_field('model', $acf['model'] ?? '', $post_id);
        $this->set_field('release_year', $acf['release_year'] ?? '', $post_id);
        $this->set_field('release_quarter', $acf['release_quarter'] ?? 'Unknown', $post_id);

        // Editor rating from ratings.overall or calculate average.
        $editor_rating = $this->get_editor_rating($acf);
        $this->set_field('editor_rating', $editor_rating, $post_id);

        // Review group - review_post and youtube_video.
        $review = [
            'review_post'   => $acf['review']['review_post'] ?? '',
            'youtube_video' => $acf['review']['youtube_video'] ?? $acf['youtube_review'] ?? '',
        ];
        $this->set_field('review', $review, $post_id);

        // Obsolete group.
        $obsolete = [
            'is_product_obsolete'           => $acf['obsolete']['is_product_obsolete'] ?? false,
            'has_the_product_been_superseded' => $acf['obsolete']['has_the_product_been_superseded'] ?? false,
            'new_product'                   => $acf['obsolete']['new_product'] ?? '',
        ];
        $this->set_field('obsolete', $obsolete, $post_id);
    }

    /**
     * Migrate performance test fields.
     * Maps old field names with colons to new clean names.
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Source ACF data.
     * @return void
     */
    private function migrate_performance_tests(int $post_id, array $acf): void {
        // Direct copy fields (same name in source and destination).
        $direct_fields = [
            'tested_top_speed',
            'fastest_0_15', 'fastest_0_20', 'fastest_0_25', 'fastest_0_30', 'fastest_0_top',
            'tested_range_fast', 'tested_range_regular', 'tested_range_slow',
            'tested_range_avg_speed_fast', 'tested_range_avg_speed_regular', 'tested_range_avg_speed_slow',
            'brake_distance', 'hill_climbing',
            'manufacturer_top_speed', 'manufacturer_range', 'max_incline', 'ideal_incline',
        ];

        foreach ($direct_fields as $field) {
            if (isset($acf[$field]) && $acf[$field] !== '') {
                $this->set_field($field, $acf[$field], $post_id);
            }
        }

        // Mapped fields (old name => new name).
        foreach (self::PERF_FIELD_MAPPINGS as $old_name => $new_name) {
            if (isset($acf[$old_name]) && $acf[$old_name] !== '') {
                $this->set_field($new_name, $acf[$old_name], $post_id);
            }
        }
    }

    /**
     * Migrate e-bike specific fields.
     * Copies the entire e-bikes group as-is (1-to-1 structure match).
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Source ACF data.
     * @return void
     */
    private function migrate_ebike_fields(int $post_id, array $acf): void {
        $ebike = $acf['e-bikes'] ?? [];

        if (empty($ebike)) {
            $this->log('warning', "No e-bikes data found for post {$post_id}");
            return;
        }

        // Copy the entire e-bikes group directly - structures match 1-to-1.
        $this->set_field('e-bikes', $ebike, $post_id);

        $this->log('info', "Copied e-bikes group with " . count($ebike) . " sub-groups");
    }

    /**
     * Migrate e-scooter specific fields.
     * Maps flat source fields to new nested structure.
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Source ACF data.
     * @return void
     */
    private function migrate_escooter_fields(int $post_id, array $acf): void {
        $escooter = [];
        $features = $acf['features'] ?? [];

        // Motor group.
        $escooter['motor'] = [
            'motor_position' => $this->map_motor_position($acf['motors'] ?? []),
            'motor_type'     => $acf['motor_type'] ?? '',
            'voltage'        => $acf['voltage'] ?? $acf['battery_voltage'] ?? '',
            'power_nominal'  => $acf['nominal_motor_wattage'] ?? '',
            'power_peak'     => $acf['total_peak_wattage'] ?? '',
        ];

        // Battery group.
        $escooter['battery'] = [
            'capacity'      => $acf['battery_capacity'] ?? '',
            'voltage'       => $acf['battery_voltage'] ?? '',
            'amphours'      => $acf['battery_amphours'] ?? '',
            'battery_type'  => $acf['battery_type'] ?? 'Lithium-ion',
            'brand'         => $acf['battery_brand'] ?? '',
            'charging_time' => $acf['charging_time'] ?? '',
        ];

        // Brakes group - map old array to front/rear selects.
        $brakes = $this->normalize_array($acf['brakes'] ?? []);
        $escooter['brakes'] = [
            'front'        => $this->map_brake_type_select($brakes),
            'rear'         => $this->map_brake_type_select($brakes),
            'regenerative' => in_array('Electronic', $brakes, true) || in_array('Regen', $brakes, true),
        ];

        // Wheels group - fallback rear to front if not set.
        $tire_front = $acf['tire_size_front'] ?? '';
        $tire_rear = $acf['tire_size_rear'] ?? '';
        if (empty($tire_rear) && !empty($tire_front)) {
            $tire_rear = $tire_front;
        }
        $escooter['wheels'] = [
            'tire_size_front' => $tire_front,
            'tire_size_rear'  => $tire_rear,
            'tire_width'      => $acf['tire_width'] ?? '',
            'tire_type'       => $this->map_tire_type_select($acf['tires'] ?? []),
            'pneumatic_type'  => $acf['pneumatic_type'] ?? '',
            'self_healing'    => $this->has_feature($acf, 'Self-healing tires'),
        ];

        // Suspension group.
        $escooter['suspension'] = [
            'type'       => $this->map_suspension_type($acf['suspension'] ?? []),
            'adjustable' => $this->has_feature($acf, 'Adjustable suspension'),
        ];

        // Dimensions group.
        $escooter['dimensions'] = [
            'deck_length'          => $acf['deck_length'] ?? '',
            'deck_width'           => $acf['deck_width'] ?? '',
            'ground_clearance'     => $acf['ground_clearance'] ?? '',
            'handlebar_height_min' => $acf['deck_to_handlebar_min'] ?? '',
            'handlebar_height_max' => $acf['deck_to_handlebar_max'] ?? '',
            'handlebar_width'      => $acf['handlebar_width'] ?? '',
            'weight'               => $acf['weight'] ?? '',
            'max_load'             => $acf['max_load'] ?? '',
            'unfolded_length'      => $acf['unfolded_depth'] ?? '',
            'unfolded_width'       => $acf['unfolded_width'] ?? '',
            'unfolded_height'      => $acf['unfolded_height'] ?? '',
            'folded_length'        => $acf['folded_depth'] ?? '',
            'folded_width'         => $acf['folded_width'] ?? '',
            'folded_height'        => $acf['folded_height'] ?? '',
            'foldable_handlebars'  => $this->has_feature($acf, 'Foldable Handlebars'),
        ];

        // Lighting group.
        $escooter['lighting'] = [
            'lights'       => $this->normalize_array($acf['lights'] ?? []),
            'turn_signals' => $this->has_feature($acf, 'Turn Signals'),
        ];

        // Other group.
        $ip_rating = $this->get_first($acf['weather_resistance'] ?? []);
        $escooter['other'] = [
            'throttle_type' => $this->get_first($acf['throttle_type'] ?? []),
            'fold_location' => $acf['fold_location'] ?? 'Stem',
            'terrain'       => $this->map_terrain($acf['terrain'] ?? ''),
            'ip_rating'     => !empty($ip_rating) ? $ip_rating : 'Unknown',
            'footrest'      => !empty($acf['footrest']),
            'kickstand'     => true,
            'display_type'  => 'Unknown',
        ];

        // Features - exclude ones that now live elsewhere.
        $escooter['features'] = $this->map_escooter_features($features);

        // Save the entire e-scooters group.
        $this->set_field('e-scooters', $escooter, $post_id);

        $this->log('info', "Migrated e-scooters group with " . count($escooter) . " sub-groups");
    }

    /**
     * Map motor position array to single select value.
     *
     * @param array $motors Motors array (e.g., ["Rear"], ["Front", "Rear"]).
     * @return string Motor position (Front, Rear, or Dual).
     */
    private function map_motor_position(array $motors): string {
        if (empty($motors)) {
            return 'Rear';
        }
        if (count($motors) > 1 || in_array('Both', $motors, true) || in_array('Dual', $motors, true)) {
            return 'Dual';
        }
        $first = $motors[0] ?? '';
        if (stripos($first, 'front') !== false) {
            return 'Front';
        }
        return 'Rear';
    }

    /**
     * Map old brake array to select value.
     *
     * @param array $brakes Brakes array.
     * @return string Brake type select value.
     */
    private function map_brake_type_select(array $brakes): string {
        foreach ($brakes as $brake) {
            $lower = strtolower($brake);
            if (strpos($lower, 'hydraulic') !== false) {
                return 'Disc (Hydraulic)';
            }
            if (strpos($lower, 'mechanical') !== false || strpos($lower, 'cable') !== false) {
                return 'Disc (Mechanical)';
            }
            if (strpos($lower, 'disc') !== false) {
                return 'Disc (Mechanical)';
            }
            if (strpos($lower, 'drum') !== false) {
                return 'Drum';
            }
        }
        return 'None';
    }

    /**
     * Map old tire type array to select value.
     *
     * @param array $tires Tires array.
     * @return string Tire type select value (Pneumatic, Solid, Mixed).
     */
    private function map_tire_type_select(array $tires): string {
        $tires = $this->normalize_array($tires);
        if (empty($tires)) {
            return 'Pneumatic';
        }

        $has_pneumatic = false;
        $has_solid = false;

        foreach ($tires as $tire) {
            $lower = strtolower($tire);
            if (strpos($lower, 'pneumatic') !== false || strpos($lower, 'air') !== false) {
                $has_pneumatic = true;
            }
            if (strpos($lower, 'solid') !== false || strpos($lower, 'honeycomb') !== false) {
                $has_solid = true;
            }
        }

        if ($has_pneumatic && $has_solid) {
            return 'Mixed';
        }
        if ($has_solid) {
            return 'Solid';
        }
        return 'Pneumatic';
    }

    /**
     * Map terrain to allowed values.
     *
     * @param string $terrain Old terrain value.
     * @return string New terrain value (Street, Hybrid, Off-road).
     */
    private function map_terrain(string $terrain): string {
        $lower = strtolower($terrain);
        if (strpos($lower, 'off') !== false) {
            return 'Off-road';
        }
        if (strpos($lower, 'hybrid') !== false || strpos($lower, 'all') !== false) {
            return 'Hybrid';
        }
        return 'Street';
    }

    /**
     * Normalize array value (ensure it's an array).
     *
     * @param mixed $value The value.
     * @return array
     */
    private function normalize_array($value): array {
        if (is_array($value)) {
            return $value;
        }
        if (empty($value)) {
            return [];
        }
        return [$value];
    }

    /**
     * Get first value from array or return empty string.
     *
     * @param mixed $value The value.
     * @return string
     */
    private function get_first($value): string {
        if (is_array($value)) {
            return $value[0] ?? '';
        }
        return (string) $value;
    }

    /**
     * Check if product has a specific feature.
     *
     * @param array  $acf     ACF data.
     * @param string $feature Feature name.
     * @return bool
     */
    private function has_feature(array $acf, string $feature): bool {
        $features = $acf['features'] ?? [];
        return in_array($feature, $features, true);
    }

    /**
     * Map old suspension array to new format.
     *
     * @param array $suspension Old suspension values.
     * @return array New suspension type values.
     */
    private function map_suspension_type(array $suspension): array {
        $map = [
            'Front fork'       => 'Front fork',
            'Front spring'     => 'Front spring',
            'Front hydraulic'  => 'Front hydraulic',
            'Front rubber'     => 'Front rubber',
            'Rear fork'        => 'Rear fork',
            'Rear spring'      => 'Rear spring',
            'Rear hydraulic'   => 'Rear hydraulic',
            'Rear rubber'      => 'Rear rubber',
            'Rear swingarm'    => 'Rear spring',
            'None'             => 'None',
        ];

        $result = [];
        foreach ($suspension as $s) {
            $mapped = $map[$s] ?? $s;
            if (!in_array($mapped, $result, true)) {
                $result[] = $mapped;
            }
        }
        return $result;
    }

    /**
     * Map old feature values to new format.
     * Excludes features that now live in dedicated fields.
     *
     * @param array $features Old feature values.
     * @return array New feature values.
     */
    private function map_escooter_features(array $features): array {
        // Features that now live in dedicated fields (exclude from features list).
        $excluded = [
            'Foldable Handlebars',
            'Adjustable suspension',
            'Self-healing tires',
        ];

        $map = [
            'App'                           => 'App',
            'Speed Modes'                   => 'Speed Modes',
            'Cruise Control'                => 'Cruise Control',
            'Folding Mechanism'             => 'Folding',
            'Push-To-Start'                 => 'Push-To-Start',
            'Zero-Start'                    => 'Zero-Start',
            'Turn Signals'                  => 'Turn Signals',
            'Brake Curve Adjustment'        => 'Brake Curve Adjustment',
            'Acceleration Adjustment'       => 'Acceleration Adjustment',
            'Speed limiting'                => 'Speed Limiting',
            'Over-the-air firmware updates' => 'OTA Updates',
            'Location tracking'             => 'Location Tracking',
            'Quick-Swap Battery'            => 'Quick-Swap Battery',
            'Steering dampener'             => 'Steering Damper',
            'Electronic horn'               => 'Electronic Horn',
            'NFC Unlock'                    => 'NFC Unlock',
            'Seat Add-On'                   => 'Seat Option',
        ];

        $result = [];
        foreach ($features as $f) {
            // Skip excluded features.
            if (in_array($f, $excluded, true)) {
                continue;
            }
            if (isset($map[$f])) {
                $result[] = $map[$f];
            } elseif (in_array($f, array_values($map), true)) {
                $result[] = $f;
            }
        }
        return $result;
    }

    /**
     * Migrate e-skateboard specific fields.
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Source ACF data.
     * @return void
     */
    private function migrate_eskateboard_fields(int $post_id, array $acf): void {
        $eskateboard = [];
        $features = $this->normalize_array($acf['features'] ?? []);

        // Motor group.
        $eskateboard['motor'] = [
            'motor_type'    => $acf['motor_type'] ?: 'Hub',
            'drive'         => $this->map_skateboard_drive($acf['Wheel Drive'] ?? ''),
            'motor_count'   => 2, // Default, not in source data
            'motor_size'    => '', // Not in source data
            'power_nominal' => $acf['nominal_motor_wattage'] ?? '',
            'power_peak'    => $acf['total_peak_wattage'] ?? '',
        ];

        // Battery group.
        $eskateboard['battery'] = [
            'capacity'      => $acf['battery_capacity'] ?? '',
            'voltage'       => $acf['battery_voltage'] ?? $acf['voltage'] ?? '',
            'amphours'      => $acf['battery_amphours'] ?? '',
            'battery_type'  => $acf['battery_type'] ?? 'Lithium-ion',
            'brand'         => $acf['battery_brand'] ?? '',
            'configuration' => '', // Not in source data
            'charging_time' => $acf['charging_time'] ?? '',
        ];

        // Deck group.
        $eskateboard['deck'] = [
            'length'   => $acf['deck_length'] ?? '',
            'width'    => $acf['deck_width'] ?? '',
            'material' => $acf['deck_material'] ?? '',
            'concave'  => '', // Not in source data
        ];

        // Trucks group.
        $eskateboard['trucks'] = [
            'trucks'   => $acf['trucks'] ?? '',
            'bushings' => $acf['bushings'] ?? '',
        ];

        // Wheels group.
        $wheel_size = $acf['wheel_size_front'] ?? $acf['wheel_size_rear'] ?? '';
        $eskateboard['wheels'] = [
            'wheel_size'     => $wheel_size,
            'wheel_width'    => $acf['tire_width'] ?? '',
            'durometer'      => $acf['tire_durometer'] ?? '',
            'wheel_type'     => $this->map_skateboard_wheel_type($acf['tires'] ?? []),
            'wheel_material' => $this->map_skateboard_wheel_material($acf['tire_material'] ?? []),
            'terrain'        => $this->map_skateboard_terrain($acf['terrain'] ?? ''),
        ];

        // Suspension group.
        $has_suspension = !empty($acf['suspension']) && !in_array('None', (array) $acf['suspension'], true);
        $eskateboard['suspension'] = [
            'has_suspension'  => $has_suspension,
            'suspension_type' => $has_suspension ? implode(', ', (array) $acf['suspension']) : '',
        ];

        // Dimensions group.
        $eskateboard['dimensions'] = [
            'wheelbase'        => $acf['wheel_base'] ?? '',
            'ground_clearance' => $acf['ground_clearance'] ?? '',
            'weight'           => $acf['weight'] ?? '',
            'max_load'         => $acf['max_load'] ?? '',
            'unfolded_length'  => $acf['unfolded_depth'] ?? '',
            'unfolded_width'   => $acf['unfolded_width'] ?? '',
            'unfolded_height'  => $acf['unfolded_height'] ?? '',
        ];

        // Electronics group.
        $eskateboard['electronics'] = [
            'esc'         => $acf['esc'] ?? '',
            'remote_type' => '', // Not in source data
        ];

        // Lighting group.
        $eskateboard['lighting'] = [
            'lights'          => $this->normalize_array($acf['lights'] ?? []),
            'side_visibility' => $this->normalize_array($acf['Side_visibility'] ?? []),
            'ambient_lights'  => false, // Not in source data
        ];

        // Other group.
        $eskateboard['other'] = [
            'ip_rating' => $this->map_skateboard_ip_rating($acf['weather_resistance'] ?? []),
        ];

        // Features.
        $eskateboard['features'] = $this->map_eskateboard_features($acf);

        $this->set_field('e-skateboards', $eskateboard, $post_id);
        $this->log('info', "Migrated e-skateboards group with " . count($eskateboard) . " sub-groups");
    }

    /**
     * Map skateboard drive value.
     *
     * @param string $drive Old drive value (e.g., "2WD").
     * @return string
     */
    private function map_skateboard_drive(string $drive): string {
        $drive = strtoupper(trim($drive));
        if (in_array($drive, ['1WD', '2WD', '4WD'], true)) {
            return $drive;
        }
        return '2WD'; // Default.
    }

    /**
     * Map skateboard wheel type.
     *
     * @param array $tires Tires array.
     * @return string
     */
    private function map_skateboard_wheel_type(array $tires): string {
        $tires = $this->normalize_array($tires);
        foreach ($tires as $tire) {
            $lower = strtolower($tire);
            if (strpos($lower, 'pneumatic') !== false || strpos($lower, 'inflat') !== false || strpos($lower, 'air') !== false) {
                return 'Pneumatic';
            }
            if (strpos($lower, 'solid') !== false) {
                return 'Solid';
            }
        }
        return 'Solid'; // Default for skateboards.
    }

    /**
     * Map skateboard wheel material.
     *
     * @param array $materials Material array.
     * @return string
     */
    private function map_skateboard_wheel_material(array $materials): string {
        $materials = $this->normalize_array($materials);
        foreach ($materials as $material) {
            $lower = strtolower($material);
            if (strpos($lower, 'polyurethane') !== false || strpos($lower, 'pu') !== false) {
                return 'Polyurethane';
            }
            if (strpos($lower, 'rubber') !== false) {
                return 'Rubber';
            }
            if (strpos($lower, 'cloud') !== false) {
                return 'Cloudwheels';
            }
        }
        return 'Polyurethane'; // Default.
    }

    /**
     * Map skateboard terrain.
     *
     * @param string $terrain Terrain value.
     * @return string
     */
    private function map_skateboard_terrain(string $terrain): string {
        $lower = strtolower($terrain);
        if (strpos($lower, 'all') !== false || strpos($lower, 'off') !== false) {
            return 'All-Terrain';
        }
        if (strpos($lower, 'hybrid') !== false) {
            return 'Hybrid';
        }
        return 'Street';
    }

    /**
     * Map skateboard IP rating from weather_resistance array.
     *
     * @param array $resistance Weather resistance array.
     * @return string
     */
    private function map_skateboard_ip_rating(array $resistance): string {
        $resistance = $this->normalize_array($resistance);
        foreach ($resistance as $r) {
            $r = strtoupper(trim($r));
            // Check for valid IP ratings.
            if (preg_match('/^IP[X]?\d{1,2}$/i', $r)) {
                return $r;
            }
        }
        return 'Unknown';
    }

    /**
     * Map skateboard features.
     *
     * @param array $acf ACF data.
     * @return array
     */
    private function map_eskateboard_features(array $acf): array {
        $features = [];

        // Boolean fields.
        if (!empty($acf['cruise_control'])) {
            $features[] = 'Cruise Control';
        }
        if (!empty($acf['riding_modes'])) {
            $features[] = 'Speed Modes';
        }
        if (!empty($acf['bluetooth_app'])) {
            $features[] = 'App';
        }
        if (!empty($acf['quick_swap_battery'])) {
            $features[] = 'Quick-Swap Battery';
        }
        if (!empty($acf['push_to_start'])) {
            $features[] = 'Push Start';
        }
        if (!empty($acf['braking_modes'])) {
            $features[] = 'Braking Modes';
        }

        // Check features array for additional items.
        $source_features = $this->normalize_array($acf['features'] ?? []);
        $map = [
            'App'                  => 'App',
            'Speed Modes'          => 'Speed Modes',
            'Cruise Control'       => 'Cruise Control',
            'Regenerative Braking' => 'Regenerative Braking',
            'Reverse'              => 'Reverse',
        ];

        foreach ($source_features as $f) {
            if (isset($map[$f]) && !in_array($map[$f], $features, true)) {
                $features[] = $map[$f];
            }
        }

        return $features;
    }

    /**
     * Migrate hoverboard specific fields.
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Source ACF data.
     * @return void
     */
    private function migrate_hoverboard_fields(int $post_id, array $acf): void {
        $hoverboard = [];

        // Motor group.
        $hoverboard['motor'] = [
            'power_nominal' => $acf['nominal_motor_wattage'] ?? '',
            'power_peak'    => $acf['total_peak_wattage'] ?? '',
        ];

        // Battery group.
        $hoverboard['battery'] = [
            'capacity'      => $acf['battery_capacity'] ?? '',
            'voltage'       => $acf['battery_voltage'] ?? $acf['voltage'] ?? '',
            'amphours'      => $acf['battery_amphours'] ?? '',
            'battery_type'  => $acf['battery_type'] ?? 'Lithium-ion',
            'charging_time' => $acf['charging_time'] ?? '',
        ];

        // Wheels group.
        $hoverboard['wheels'] = [
            'wheel_size'     => $acf['tire_size'] ?? '',
            'wheel_width'    => $acf['tire_width'] ?? '',
            'wheel_type'     => $this->map_hoverboard_wheel_type($acf['tires'] ?? []),
            'pneumatic_type' => 'Tubed', // Default for pneumatic
        ];

        // Dimensions group.
        $hoverboard['dimensions'] = [
            'weight'   => $acf['weight'] ?? '',
            'max_load' => $acf['max_load'] ?? '',
            'length'   => $acf['unfolded_depth'] ?? '',
            'width'    => $acf['unfolded_width'] ?? '',
            'height'   => $acf['unfolded_height'] ?? '',
        ];

        // Lighting group.
        $hoverboard['lighting'] = [
            'lights' => $this->map_hoverboard_lights($acf),
        ];

        // Connectivity group.
        $hoverboard['connectivity'] = [
            'bluetooth_speaker' => !empty($acf['bluetooth_speakers']),
            'app_enabled'       => !empty($acf['bluetooth_app']),
            'speed_modes'       => !empty($acf['riding_modes']),
        ];

        // Safety group.
        $hoverboard['safety'] = [
            'ul_2272'   => !empty($acf['ul_2272']),
            'ip_rating' => $this->map_hoverboard_ip_rating($acf['weather_resistance'] ?? []),
        ];

        // Other group.
        $hoverboard['other'] = [
            'terrain' => $this->map_hoverboard_terrain($acf['terrain'] ?? ''),
            'min_age' => '', // Not in source data
        ];

        // Features.
        $hoverboard['features'] = $this->map_hoverboard_features($acf);

        $this->set_field('hoverboards', $hoverboard, $post_id);
        $this->log('info', "Migrated hoverboards group with " . count($hoverboard) . " sub-groups");
    }

    /**
     * Map hoverboard wheel type.
     *
     * @param array $tires Tires array.
     * @return string
     */
    private function map_hoverboard_wheel_type(array $tires): string {
        $tires = $this->normalize_array($tires);
        foreach ($tires as $tire) {
            $lower = strtolower($tire);
            if (strpos($lower, 'pneumatic') !== false || strpos($lower, 'inflat') !== false || strpos($lower, 'air') !== false) {
                return 'Pneumatic';
            }
        }
        return 'Solid'; // Default
    }

    /**
     * Map hoverboard lights from source fields.
     *
     * @param array $acf Source ACF data.
     * @return array
     */
    private function map_hoverboard_lights(array $acf): array {
        $lights_source = $this->normalize_array($acf['lights'] ?? []);
        $side_source = $this->normalize_array($acf['Side_visibility'] ?? []);

        $lights = [];

        foreach ($lights_source as $light) {
            $lower = strtolower($light);
            if ($lower === 'both') {
                $lights[] = 'Front';
                $lights[] = 'Rear';
            } elseif ($lower === 'front') {
                $lights[] = 'Front';
            } elseif ($lower === 'rear') {
                $lights[] = 'Rear';
            } elseif ($lower === 'none') {
                $lights[] = 'None';
            }
        }

        // Add side lights if present
        foreach ($side_source as $side) {
            $lower = strtolower($side);
            if ($lower !== 'none' && !empty($lower)) {
                $lights[] = 'Side';
                break;
            }
        }

        return array_unique($lights);
    }

    /**
     * Map hoverboard IP rating.
     *
     * @param array $resistance Weather resistance array.
     * @return string
     */
    private function map_hoverboard_ip_rating(array $resistance): string {
        $resistance = $this->normalize_array($resistance);
        foreach ($resistance as $r) {
            $r = strtoupper(trim($r));
            if (preg_match('/^IP[X]?\d{1,2}$/i', $r)) {
                return $r;
            }
        }
        return 'Unknown';
    }

    /**
     * Map hoverboard terrain.
     *
     * @param string $terrain Terrain value.
     * @return string
     */
    private function map_hoverboard_terrain(string $terrain): string {
        $lower = strtolower($terrain);
        if (strpos($lower, 'off') !== false || strpos($lower, 'all') !== false) {
            return 'Off-road';
        }
        return 'Street';
    }

    /**
     * Map hoverboard features.
     *
     * @param array $acf ACF data.
     * @return array
     */
    private function map_hoverboard_features(array $acf): array {
        $features = [];

        if (!empty($acf['carrying_handles'])) {
            $features[] = 'Carrying Handle';
        }

        // Check if has lights (add LED Lights feature)
        $lights = $this->normalize_array($acf['lights'] ?? []);
        if (!empty($lights) && !in_array('None', $lights, true)) {
            $features[] = 'LED Lights';
        }

        return $features;
    }

    /**
     * Migrate EUC (Electric Unicycle) specific fields.
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Source ACF data.
     * @return void
     */
    private function migrate_euc_fields(int $post_id, array $acf): void {
        $euc = [];

        // Motor group.
        $euc['motor'] = [
            'power_nominal' => $acf['nominal_motor_wattage'] ?? '',
            'power_peak'    => $acf['total_peak_wattage'] ?? '',
            'torque'        => $acf['torque'] ?? '',
            'hollow_motor'  => !empty($acf['hollow_motor']),
        ];

        // Battery group.
        $euc['battery'] = [
            'capacity'      => $acf['battery_capacity'] ?? '',
            'voltage'       => $acf['battery_voltage'] ?? $acf['voltage'] ?? '',
            'amphours'      => $acf['battery_amphours'] ?? '',
            'battery_type'  => $this->map_euc_battery_type($acf['battery_type'] ?? '', $acf['battery_brand'] ?? ''),
            'charging_time' => $acf['charging_time'] ?? '',
            'charger_output'=> $acf['charger_output'] ?? '',
            'fast_charger'  => !empty($acf['fast_charger']),
            'dual_charging' => !empty($acf['dual_charging']),
            'bms'           => $acf['bms'] ?? '',
        ];

        // Wheel group.
        $euc['wheel'] = [
            'tire_size'    => $this->parse_tire_size_inches($acf['tire_size'] ?? $acf['wheel_size'] ?? ''),
            'tire_width'   => $this->parse_tire_width_inches($acf['tire_width'] ?? ''),
            'tire_type'    => $this->map_euc_tire_type($acf['tires'] ?? []),
            'tire_tread'   => $this->map_euc_tire_tread($acf['terrain'] ?? ''),
            'self_healing' => $this->has_feature($acf, 'Self-healing tires'),
        ];

        // Suspension group.
        $has_suspension = !empty($acf['suspension']) && !in_array('None', (array) $acf['suspension'], true);
        $euc['suspension'] = [
            'has_suspension'       => $has_suspension,
            'suspension_type'      => $has_suspension ? $this->map_euc_suspension_type($acf['suspension'] ?? []) : 'None',
            'suspension_travel'    => $acf['suspension_travel'] ?? '',
            'adjustable_suspension'=> !empty($acf['adjustable_suspension']),
        ];

        // Pedals group.
        $euc['pedals'] = [
            'pedal_height'     => $acf['pedal_height'] ?? '',
            'pedal_size'       => $acf['pedal_size'] ?? '',
            'pedal_angle'      => $acf['pedal_angle'] ?? '',
            'adjustable_pedals'=> !empty($acf['adjustable_pedals']),
            'spiked_pedals'    => !empty($acf['spiked_pedals']),
        ];

        // Dimensions group.
        $euc['dimensions'] = [
            'weight'   => $acf['weight'] ?? '',
            'max_load' => $acf['max_load'] ?? '',
            'height'   => $acf['unfolded_height'] ?? '',
            'width'    => $acf['unfolded_width'] ?? '',
            'depth'    => $acf['unfolded_depth'] ?? '',
        ];

        // Lighting group.
        $euc['lighting'] = [
            'headlight'        => !empty($acf['headlight']) || $this->has_light_type($acf, 'Front'),
            'headlight_lumens' => $acf['headlight_lumens'] ?? '',
            'taillight'        => !empty($acf['taillight']) || $this->has_light_type($acf, 'Rear'),
            'brake_light'      => !empty($acf['brake_light']),
            'rgb_lights'       => !empty($acf['rgb_lights']) || $this->has_light_type($acf, 'Side'),
        ];

        // Connectivity group.
        $euc['connectivity'] = [
            'bluetooth' => !empty($acf['bluetooth']),
            'app'       => !empty($acf['bluetooth_app']),
            'speaker'   => !empty($acf['bluetooth_speakers']),
            'gps'       => !empty($acf['gps']),
        ];

        // Safety group.
        $euc['safety'] = [
            'ip_rating'     => $this->map_euc_ip_rating($acf['weather_resistance'] ?? []),
            'tiltback_speed'=> $acf['tiltback_speed'] ?? '',
            'cutoff_speed'  => $acf['cutoff_speed'] ?? '',
            'lift_sensor'   => !empty($acf['lift_sensor']),
        ];

        // Features.
        $euc['features'] = $this->map_euc_features($acf);

        $this->set_field('eucs', $euc, $post_id);
        $this->log('info', "Migrated eucs group with " . count($euc) . " sub-groups");
    }

    /**
     * Map EUC battery type based on type and brand.
     *
     * @param string $type  Battery type.
     * @param string $brand Battery brand.
     * @return string
     */
    private function map_euc_battery_type(string $type, string $brand): string {
        $brand_lower = strtolower($brand);

        if (strpos($brand_lower, 'lg') !== false) {
            return 'LG';
        }
        if (strpos($brand_lower, 'samsung') !== false) {
            return 'Samsung';
        }
        if (strpos($brand_lower, 'panasonic') !== false) {
            return 'Panasonic';
        }
        if (strpos(strtolower($type), 'lipo') !== false) {
            return 'LiPo';
        }

        return 'Lithium-ion';
    }

    /**
     * Parse tire size to inches (number).
     *
     * @param mixed $size Tire size value.
     * @return string Inches as number string.
     */
    private function parse_tire_size_inches($size): string {
        if (empty($size)) {
            return '';
        }

        // If already a number, return it
        if (is_numeric($size)) {
            return (string) $size;
        }

        // Extract number from string like "16 inch" or "16""
        if (preg_match('/(\d+\.?\d*)/', $size, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Parse tire width to inches.
     *
     * @param mixed $width Tire width value.
     * @return string Inches as number string.
     */
    private function parse_tire_width_inches($width): string {
        if (empty($width)) {
            return '';
        }

        if (is_numeric($width)) {
            return (string) $width;
        }

        // Extract number from string
        if (preg_match('/(\d+\.?\d*)/', $width, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Map EUC tire type.
     *
     * @param mixed $tires Tires array or string.
     * @return string
     */
    private function map_euc_tire_type($tires): string {
        $tires = $this->normalize_array($tires);
        foreach ($tires as $tire) {
            $lower = strtolower($tire);
            if (strpos($lower, 'solid') !== false) {
                return 'Solid';
            }
            if (strpos($lower, 'honeycomb') !== false) {
                return 'Honeycomb';
            }
        }
        return 'Pneumatic'; // Default for EUCs
    }

    /**
     * Map EUC tire tread from terrain.
     *
     * @param mixed $terrain Terrain value.
     * @return string
     */
    private function map_euc_tire_tread($terrain): string {
        $terrain = is_array($terrain) ? ($terrain[0] ?? '') : (string) $terrain;
        $lower = strtolower($terrain);
        if (strpos($lower, 'off') !== false) {
            return 'Knobby';
        }
        if (strpos($lower, 'all') !== false || strpos($lower, 'hybrid') !== false) {
            return 'All-Terrain';
        }
        return 'Street';
    }

    /**
     * Map EUC suspension type to select value.
     *
     * @param mixed $suspension Suspension array or string.
     * @return string
     */
    private function map_euc_suspension_type($suspension): string {
        $suspension = $this->normalize_array($suspension);

        foreach ($suspension as $s) {
            $lower = strtolower($s);

            if (strpos($lower, 'dnm') !== false) {
                return 'DNM';
            }
            if (strpos($lower, 'kke') !== false) {
                return 'KKE';
            }
            if (strpos($lower, 'fastace') !== false) {
                return 'Fastace';
            }
            if (strpos($lower, 'air') !== false) {
                return 'Air';
            }
            if (strpos($lower, 'hydraulic') !== false) {
                return 'Hydraulic';
            }
            if (strpos($lower, 'coil') !== false || strpos($lower, 'spring') !== false) {
                return 'Coil Spring';
            }
        }

        // If suspension exists but type not recognized
        if (!empty($suspension) && !in_array('None', $suspension, true)) {
            return 'Custom';
        }

        return 'None';
    }

    /**
     * Check if product has a specific light type.
     *
     * @param array  $acf  ACF data.
     * @param string $type Light type (Front, Rear, Side).
     * @return bool
     */
    private function has_light_type(array $acf, string $type): bool {
        $lights = $this->normalize_array($acf['lights'] ?? []);
        foreach ($lights as $light) {
            if (strcasecmp($light, $type) === 0 || strcasecmp($light, 'Both') === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Map EUC IP rating.
     *
     * @param mixed $resistance Weather resistance array or string.
     * @return string
     */
    private function map_euc_ip_rating($resistance): string {
        $resistance = $this->normalize_array($resistance);
        foreach ($resistance as $r) {
            $r = strtoupper(trim($r));
            if (preg_match('/^IP[X]?\d{1,2}$/i', $r)) {
                return $r;
            }
        }
        return 'Unknown';
    }

    /**
     * Map EUC features.
     *
     * @param array $acf ACF data.
     * @return array
     */
    private function map_euc_features(array $acf): array {
        $features = [];

        if (!empty($acf['kickstand'])) {
            $features[] = 'Kickstand';
        }
        if (!empty($acf['trolley_handle'])) {
            $features[] = 'Trolley Handle';
        }
        if (!empty($acf['retractable_handle'])) {
            $features[] = 'Retractable Handle';
        }
        if (!empty($acf['mudguard'])) {
            $features[] = 'Mudguard';
        }
        if (!empty($acf['power_pads'])) {
            $features[] = 'Power Pads';
        }
        if (!empty($acf['display_screen'])) {
            $features[] = 'Display Screen';
        }
        if (!empty($acf['usb_port'])) {
            $features[] = 'USB Charging Port';
        }

        // Check features array
        $source_features = $this->normalize_array($acf['features'] ?? []);
        $map = [
            'Kickstand'         => 'Kickstand',
            'Trolley Handle'    => 'Trolley Handle',
            'Handle'            => 'Trolley Handle',
            'Mudguard'          => 'Mudguard',
            'Fender'            => 'Mudguard',
            'Power Pads'        => 'Power Pads',
            'Display'           => 'Display Screen',
            'USB'               => 'USB Charging Port',
            'Anti-Spin'         => 'Anti-Spin Button',
            'Learning Mode'     => 'Learning Mode',
        ];

        foreach ($source_features as $f) {
            if (isset($map[$f]) && !in_array($map[$f], $features, true)) {
                $features[] = $map[$f];
            }
        }

        return $features;
    }

    /**
     * Migrate product image.
     *
     * @param int   $post_id  The post ID.
     * @param array $acf      Source ACF data.
     * @param array $old_data Full source product data.
     * @return void
     */
    private function migrate_image(int $post_id, array $acf, array $old_data): void {
        if (has_post_thumbnail($post_id)) {
            $this->log('info', "Product {$post_id} already has a featured image, skipping");
            return;
        }

        $image_url = $this->get_remote_image_url($acf, $old_data);

        if (empty($image_url)) {
            $this->log('warning', "No image found for product {$post_id}");
            return;
        }

        $this->log('info', "Downloading image: {$image_url}");

        $attachment_id = $this->sideload_image($image_url, $post_id);

        if ($attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
            $this->log('success', "Image uploaded successfully (attachment ID: {$attachment_id})");
        }
    }

    /**
     * Get the remote image URL from ACF data or featured_media.
     *
     * @param array $acf      ACF data.
     * @param array $old_data Full source product data.
     * @return string|null Image URL or null.
     */
    private function get_remote_image_url(array $acf, array $old_data): ?string {
        // Check ACF big_thumbnail field.
        $big_thumbnail = $acf['big_thumbnail'] ?? null;
        if ($big_thumbnail) {
            if (is_array($big_thumbnail) && !empty($big_thumbnail['url'])) {
                return $big_thumbnail['url'];
            }
            if (is_string($big_thumbnail) && filter_var($big_thumbnail, FILTER_VALIDATE_URL)) {
                return $big_thumbnail;
            }
            if (is_numeric($big_thumbnail)) {
                $url = $this->fetch_remote_media_url((int) $big_thumbnail);
                if ($url) {
                    return $url;
                }
            }
        }

        // Check ACF thumbnail field.
        $thumbnail = $acf['thumbnail'] ?? null;
        if ($thumbnail) {
            if (is_array($thumbnail) && !empty($thumbnail['url'])) {
                return $thumbnail['url'];
            }
            if (is_string($thumbnail) && filter_var($thumbnail, FILTER_VALIDATE_URL)) {
                return $thumbnail;
            }
            if (is_numeric($thumbnail)) {
                $url = $this->fetch_remote_media_url((int) $thumbnail);
                if ($url) {
                    return $url;
                }
            }
        }

        // Fall back to featured_media from REST API.
        $featured_media_id = $old_data['featured_media'] ?? 0;
        if ($featured_media_id) {
            return $this->fetch_remote_media_url((int) $featured_media_id);
        }

        return null;
    }

    /**
     * Fetch media URL from remote site by attachment ID.
     *
     * @param int $media_id The remote attachment ID.
     * @return string|null The media URL or null.
     */
    private function fetch_remote_media_url(int $media_id): ?string {
        if (empty($this->source_url) || $media_id <= 0) {
            return null;
        }

        $url = sprintf(
            '%s/wp-json/wp/v2/media/%d?_fields=source_url',
            $this->source_url,
            $media_id
        );

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            $this->log('warning', "Failed to fetch media {$media_id}: " . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->log('warning', "HTTP {$code} fetching media {$media_id}");
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['source_url'] ?? null;
    }

    /**
     * Sideload an image from URL to the WordPress media library.
     *
     * @param string $url     The image URL.
     * @param int    $post_id The post to attach the image to.
     * @return int|null The attachment ID or null on failure.
     */
    private function sideload_image(string $url, int $post_id): ?int {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($url, 60);

        if (is_wp_error($tmp)) {
            $this->log('error', "Failed to download image: " . $tmp->get_error_message());
            return null;
        }

        $url_path = wp_parse_url($url, PHP_URL_PATH);
        $filename = basename($url_path);

        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file_array, $post_id);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            $this->log('error', "Failed to sideload image: " . $attachment_id->get_error_message());
            return null;
        }

        return $attachment_id;
    }

    /**
     * Get editor rating from old ratings structure.
     *
     * @param array $acf Source ACF data.
     * @return string The editor rating (1-10) or empty.
     */
    private function get_editor_rating(array $acf): string {
        $ratings = $acf['ratings'] ?? [];

        if (!empty($ratings['overall'])) {
            return (string) $ratings['overall'];
        }

        // Calculate average of available ratings.
        $values = array_filter([
            $ratings['speed'] ?? null,
            $ratings['acceleration_hills'] ?? null,
            $ratings['range'] ?? null,
            $ratings['portability'] ?? null,
            $ratings['ride_quality'] ?? null,
            $ratings['build_quality'] ?? null,
            $ratings['safety'] ?? null,
            $ratings['features'] ?? null,
            $ratings['value'] ?? null,
        ], fn($v) => $v !== null && $v !== '');

        if (empty($values)) {
            return '';
        }

        return (string) round(array_sum($values) / count($values), 1);
    }

    /**
     * Infer product type from title or ACF data.
     *
     * @param string $title The product title.
     * @param array  $acf   The ACF data.
     * @return string|null The inferred product type slug or null.
     */
    private function infer_product_type(string $title, array $acf): ?string {
        // Check for e-bike ACF data (has populated e-bikes group).
        if (!empty($acf['e-bikes']) && is_array($acf['e-bikes'])) {
            $ebike = $acf['e-bikes'];
            // Check if e-bikes has actual data (not just empty defaults).
            if (!empty($ebike['category']) || !empty($ebike['motor']['power_nominal'])) {
                return 'electric-bike';
            }
        }

        // Check for scooter-specific fields.
        if (!empty($acf['deck_length']) || !empty($acf['throttle_type'])) {
            return 'electric-scooter';
        }

        $title_lower = strtolower($title);

        // E-bike keywords.
        $ebike_keywords = ['e-bike', 'ebike', 'electric bike', 'heckler', 'bullit', 'ransom', 'levo', 'turbo', 'powerfly', 'rail', 'fuel', 'shuttle', 'trance', 'reign', 'stance', 'habit', 'moterra'];
        foreach ($ebike_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return 'electric-bike';
            }
        }

        // Scooter keywords.
        $scooter_keywords = ['scooter', 'ninebot', 'kaabo', 'wolf', 'dualtron', 'vsett', 'inokim', 'emove', 'apollo', 'varla', 'gotrax', 'hiboy', 'segway', 'unagi', 'boosted rev', 'niu', 'mercane', 'zero', 'mantis', 'ghost', 'blade', 'thunder', 'eagle', 'falcon', 'phantom', 'speedway', 'navee'];
        foreach ($scooter_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return 'electric-scooter';
            }
        }

        // EUC keywords.
        $euc_keywords = ['unicycle', 'euc', 'inmotion', 'gotway', 'begode', 'kingsong', 'veteran', 'leaperkim'];
        foreach ($euc_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return 'electric-unicycle';
            }
        }

        // Skateboard keywords.
        $eskate_keywords = ['skateboard', 'longboard', 'boosted board', 'evolve', 'meepo', 'exway', 'wowgo', 'backfire', 'ownboard', 'teamgee'];
        foreach ($eskate_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return 'electric-skateboard';
            }
        }

        // Hoverboard keywords.
        $hoverboard_keywords = ['hoverboard', 'swagway', 'swagtron', 'razor hovertrax'];
        foreach ($hoverboard_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return 'hoverboard';
            }
        }

        return null;
    }

    /**
     * Set an ACF field value.
     *
     * @param string $field_name The field name.
     * @param mixed  $value      The field value.
     * @param int    $post_id    The post ID.
     * @return void
     */
    private function set_field(string $field_name, $value, int $post_id): void {
        if (function_exists('update_field')) {
            update_field($field_name, $value, $post_id);
        } else {
            update_post_meta($post_id, $field_name, $value);
        }
    }

    /**
     * Log a message.
     *
     * @param string $level   Log level (info, error, success, warning, dry-run).
     * @param string $message The message.
     * @return void
     */
    private function log(string $level, string $message): void {
        $this->log[] = [
            'level'   => $level,
            'message' => $message,
            'time'    => current_time('mysql'),
        ];

        if (defined('WP_CLI') && WP_CLI) {
            switch ($level) {
                case 'error':
                    \WP_CLI::error($message, false);
                    break;
                case 'warning':
                    \WP_CLI::warning($message);
                    break;
                case 'success':
                    \WP_CLI::success($message);
                    break;
                default:
                    \WP_CLI::log($message);
            }
        } else {
            error_log("[ERH Migration] [{$level}] {$message}");
        }
    }

    /**
     * Get the migration log.
     *
     * @return array The log entries.
     */
    public function get_log(): array {
        return $this->log;
    }

    /**
     * Migrate a single product by ID or slug.
     *
     * @param string $identifier Product ID (numeric) or slug.
     * @return array Migration result.
     */
    public function migrate_single_product(string $identifier): array {
        $result = [
            'success'      => false,
            'product_name' => '',
            'reason'       => '',
        ];

        $product = $this->fetch_single_product($identifier);

        if (!$product) {
            $result['reason'] = "Could not find product: {$identifier}";
            return $result;
        }

        $result['product_name'] = $product['title']['rendered'] ?? $product['slug'] ?? $identifier;

        $migrated_id = $this->migrate_product($product);

        if ($migrated_id) {
            $result['success'] = true;
            $this->log('success', "Migrated product ID {$migrated_id}: " . $result['product_name']);
        } elseif ($this->dry_run) {
            $result['success'] = true;
            $result['reason'] = 'Dry run - no changes made';
        } else {
            $result['reason'] = 'Migration failed - check debug.log for details';
        }

        return $result;
    }

    /**
     * Fetch a single product from remote site by ID or slug.
     *
     * @param string $identifier Product ID (numeric) or slug.
     * @return array|null Product data or null if not found.
     */
    private function fetch_single_product(string $identifier): ?array {
        if (is_numeric($identifier)) {
            $url = sprintf(
                '%s/wp-json/wp/v2/products/%d?_fields=id,slug,title,status,acf,featured_media',
                $this->source_url,
                (int) $identifier
            );
        } else {
            $url = sprintf(
                '%s/wp-json/wp/v2/products?slug=%s&_fields=id,slug,title,status,acf,featured_media',
                $this->source_url,
                urlencode($identifier)
            );
        }

        $this->log('info', "Fetching product from: {$url}");

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            $this->log('error', "HTTP error: " . $response->get_error_message());
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->log('error', "HTTP {$code} response");
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_numeric($identifier)) {
            if (empty($data) || !is_array($data)) {
                return null;
            }
            return $data[0] ?? null;
        }

        return $data;
    }

    /**
     * Run full remote migration.
     *
     * @param int $batch_size Products per batch.
     * @return array Migration summary.
     */
    public function run_remote_migration(int $batch_size = 50): array {
        $summary = [
            'total'     => 0,
            'migrated'  => 0,
            'skipped'   => 0,
            'errors'    => 0,
        ];

        $page = 1;
        do {
            $result = $this->fetch_remote_products($page, $batch_size);
            if (!$result) {
                break;
            }

            $summary['total'] = $result['total'];

            foreach ($result['products'] as $product) {
                $migrated_id = $this->migrate_product($product);
                if ($migrated_id) {
                    $summary['migrated']++;
                } elseif (!$this->dry_run) {
                    $summary['skipped']++;
                }
            }

            $page++;
        } while ($page <= $result['total_pages']);

        return $summary;
    }
}
