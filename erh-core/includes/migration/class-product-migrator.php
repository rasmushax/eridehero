<?php
/**
 * Product Migration Handler
 *
 * Migrates products from old ACF structure to new structure.
 * Can pull from remote site via REST API or migrate local data.
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
            'headers' => [
                'Accept' => 'application/json',
            ],
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
     * Migrate a single product from old structure to new.
     *
     * @param array $old_data The old product data (with ACF fields).
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

        if (!$product_type_slug) {
            $this->log('warning', "Skipping {$title} - unknown product type: {$old_product_type}");
            return null;
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
                wp_set_object_terms($post_id, [$brand_id], 'brands');
            }
        }

        // Migrate ACF fields.
        $this->migrate_acf_fields($post_id, $acf, $product_type_slug);

        // Migrate image.
        $this->migrate_image($post_id, $acf, $old_data);

        $this->log('success', "Migrated: {$title} (ID: {$post_id})");

        return $post_id;
    }

    /**
     * Migrate ACF fields from old to new structure.
     *
     * @param int    $post_id          The post ID.
     * @param array  $acf              Old ACF data.
     * @param string $product_type_slug The product type slug.
     * @return void
     */
    private function migrate_acf_fields(int $post_id, array $acf, string $product_type_slug): void {
        // Basic info.
        $this->set_field('model', $acf['model'] ?? '', $post_id);
        $this->set_field('release_year', $acf['release_year'] ?? '', $post_id);
        $this->set_field('release_quarter', $acf['release_quarter'] ?? 'Unknown', $post_id);
        $this->set_field('youtube_review', $acf['youtube_review'] ?? '', $post_id);

        // Editor rating (from ratings.overall or calculate average).
        $editor_rating = $this->get_editor_rating($acf);
        $this->set_field('editor_rating', $editor_rating, $post_id);

        // Manufacturer specs.
        $this->set_field('manufacturer_top_speed', $acf['manufacturer_top_speed'] ?? '', $post_id);
        $this->set_field('manufacturer_range', $acf['manufacturer_range'] ?? '', $post_id);
        $this->set_field('weight', $acf['weight'] ?? '', $post_id);
        $this->set_field('max_weight_capacity', $acf['max_weight_capacity'] ?? '', $post_id);
        $this->set_field('max_incline', $acf['max_incline'] ?? '', $post_id);

        // Performance tests.
        $this->set_field('tested_top_speed', $acf['tested_top_speed'] ?? '', $post_id);
        $this->set_field('brake_distance', $acf['brake_distance'] ?? '', $post_id);
        $this->set_field('hill_climbing', $acf['hill_climbing'] ?? '', $post_id);
        $this->set_field('ideal_incline', $acf['ideal_incline'] ?? '', $post_id);

        // Range tests (nested).
        $this->set_field('range_tests', [
            'fast'             => $acf['tested_range_fast'] ?? '',
            'regular'          => $acf['tested_range_regular'] ?? '',
            'slow'             => $acf['tested_range_slow'] ?? '',
            'avg_speed_fast'   => $acf['tested_range_avg_speed_fast'] ?? '',
            'avg_speed_regular'=> $acf['tested_range_avg_speed_regular'] ?? '',
            'avg_speed_slow'   => $acf['tested_range_avg_speed_slow'] ?? '',
        ], $post_id);

        // Acceleration tests (nested).
        $this->set_field('acceleration', [
            'to_15' => $acf['acceleration:_0-15_mph'] ?? '',
            'to_20' => $acf['acceleration:_0-20_mph'] ?? '',
            'to_25' => $acf['acceleration:_0-25_mph'] ?? '',
            'to_30' => $acf['acceleration:_0-30_mph'] ?? '',
            'to_top'=> $acf['acceleration:_0-to-top'] ?? '',
        ], $post_id);

        // Fastest tests (nested).
        $this->set_field('fastest', [
            'to_15' => $acf['fastest_0_15'] ?? '',
            'to_20' => $acf['fastest_0_20'] ?? '',
            'to_25' => $acf['fastest_0_25'] ?? '',
            'to_30' => $acf['fastest_0_30'] ?? '',
            'to_top'=> $acf['fastest_0_top'] ?? '',
        ], $post_id);

        // Battery.
        $this->set_field('battery_capacity', $acf['battery_capacity'] ?? '', $post_id);
        $this->set_field('battery_voltage', $acf['battery_voltage'] ?? '', $post_id);
        $this->set_field('battery_amphours', $acf['battery_ah'] ?? '', $post_id);
        $this->set_field('battery_type', $acf['battery_type'] ?? 'Lithium-ion', $post_id);
        $this->set_field('charging_time', $acf['charging_time'] ?? '', $post_id);
        $this->set_field('charger_output', $acf['charger_output'] ?? '', $post_id);

        // Motor.
        $motor_config = 'single';
        if (!empty($acf['dual_motor']) || stripos($acf['motor_type'] ?? '', 'dual') !== false) {
            $motor_config = 'dual';
        }
        $this->set_field('motor_configuration', $motor_config, $post_id);
        $this->set_field('motor_type', $this->map_motor_type($acf['motor_type'] ?? ''), $post_id);
        $this->set_field('motor_position', $this->map_motor_position($acf), $post_id);
        $this->set_field('motor_nominal_wattage', $acf['nominal_motor_wattage'] ?? '', $post_id);
        $this->set_field('motor_peak_wattage', $acf['peak_motor_wattage'] ?? '', $post_id);
        $this->set_field('motor_torque', $acf['motor_torque'] ?? '', $post_id);

        // Brakes.
        $this->set_field('brake_front', $this->map_brake_type($acf['front_brake_type'] ?? ''), $post_id);
        $this->set_field('brake_rear', $this->map_brake_type($acf['rear_brake_type'] ?? ''), $post_id);
        $this->set_field('brake_regen', !empty($acf['regen_braking']), $post_id);
        $this->set_field('brake_abs', !empty($acf['abs']), $post_id);

        // Product-type specific fields.
        switch ($product_type_slug) {
            case 'electric-scooter':
                $this->migrate_escooter_fields($post_id, $acf);
                break;
            case 'electric-bike':
                $this->migrate_ebike_fields($post_id, $acf);
                break;
            case 'electric-unicycle':
                $this->migrate_euc_fields($post_id, $acf);
                break;
            case 'electric-skateboard':
                $this->migrate_eskate_fields($post_id, $acf);
                break;
            case 'hoverboard':
                $this->migrate_hoverboard_fields($post_id, $acf);
                break;
        }
    }

    /**
     * Migrate e-scooter specific fields.
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Old ACF data.
     * @return void
     */
    private function migrate_escooter_fields(int $post_id, array $acf): void {
        // Dimensions.
        $this->set_field('escooter_dimensions', [
            'deck_length'      => $acf['deck_length'] ?? '',
            'deck_width'       => $acf['deck_width'] ?? '',
            'handlebar_height' => $acf['handlebar_height'] ?? '',
            'ground_clearance' => $acf['ground_clearance'] ?? '',
            'folded_length'    => $acf['folded_length'] ?? '',
            'folded_height'    => $acf['folded_height'] ?? '',
        ], $post_id);

        // Wheels.
        $this->set_field('escooter_wheels', [
            'tire_size_front'  => $acf['tire_size_front'] ?? '',
            'tire_size_rear'   => $acf['tire_size_rear'] ?? '',
            'tire_type'        => $this->map_tire_type($acf['tire_type'] ?? ''),
            'suspension_front' => $this->map_suspension_type($acf['suspension_type_front'] ?? ''),
            'suspension_rear'  => $this->map_suspension_type($acf['suspension_type_rear'] ?? ''),
        ], $post_id);

        // Lighting.
        $this->set_field('escooter_lighting', [
            'front_light'        => !empty($acf['front_light']),
            'rear_light'         => !empty($acf['rear_light']),
            'deck_lights'        => !empty($acf['deck_lights']),
            'turn_signals'       => !empty($acf['turn_signals']),
            'front_light_lumens' => $acf['front_light_lumens'] ?? '',
        ], $post_id);

        // Other specs.
        $this->set_field('escooter_other', [
            'ip_rating'     => $acf['ip_rating'] ?? '',
            'throttle_type' => $this->map_throttle_type($acf['throttle_type'] ?? ''),
            'fold_location' => $this->map_fold_location($acf['fold_location'] ?? ''),
            'terrain'       => $this->map_terrain($acf['terrain'] ?? ''),
            'stem_type'     => $acf['stem_type'] ?? '',
            'display_type'  => $acf['display_type'] ?? '',
        ], $post_id);

        // Features.
        $features = $this->map_escooter_features($acf['features'] ?? []);
        $this->set_field('escooter_features', $features, $post_id);
    }

    /**
     * Migrate e-bike specific fields.
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Old ACF data.
     * @return void
     */
    private function migrate_ebike_fields(int $post_id, array $acf): void {
        $ebike = $acf['e-bikes'] ?? [];

        // Category.
        $this->set_field('ebike_category', $ebike['category'] ?? [], $post_id);

        // Motor details.
        $motor = $ebike['motor'] ?? [];
        $this->set_field('ebike_motor', [
            'brand'        => $motor['motor_brand'] ?? '',
            'model'        => $motor['motor_model'] ?? '',
            'assist_levels'=> $motor['assist_levels'] ?? '',
            'sensor_type'  => strtolower($motor['sensor_type'] ?? 'unknown'),
        ], $post_id);

        // Battery details.
        $battery = $ebike['battery'] ?? [];
        $this->set_field('ebike_battery', [
            'position'  => strtolower($battery['battery_position'] ?? 'unknown'),
            'removable' => !empty($battery['removable']),
        ], $post_id);

        // Update shared battery fields from e-bike data.
        if (!empty($battery['battery_capacity'])) {
            $this->set_field('battery_capacity', $battery['battery_capacity'], $post_id);
        }
        if (!empty($battery['voltage'])) {
            $this->set_field('battery_voltage', $battery['voltage'], $post_id);
        }
        if (!empty($battery['amphours'])) {
            $this->set_field('battery_amphours', $battery['amphours'], $post_id);
        }
        if (!empty($battery['charge_time'])) {
            $this->set_field('charging_time', $battery['charge_time'], $post_id);
        }

        // Speed & Class.
        $speed_class = $ebike['speed_and_class'] ?? [];
        $this->set_field('ebike_class', [
            'class'              => $speed_class['class'] ?? [],
            'top_assist_speed'   => $speed_class['top_assist_speed'] ?? '',
            'throttle_top_speed' => $speed_class['throttle_top_speed'] ?? '',
            'has_throttle'       => !empty($speed_class['throttle']),
        ], $post_id);

        // Drivetrain.
        $drivetrain = $ebike['drivetrain'] ?? [];
        $this->set_field('ebike_drivetrain', [
            'gears'            => $drivetrain['gears'] ?? '',
            'derailleur_type'  => strtolower($drivetrain['derailleur_type'] ?? ''),
            'derailleur_brand' => $drivetrain['derailleur_brand'] ?? '',
            'chain_type'       => strtolower($drivetrain['chain_type'] ?? ''),
        ], $post_id);

        // Brakes.
        $brakes = $ebike['brakes'] ?? [];
        $this->set_field('ebike_brakes', [
            'brand'       => $brakes['brake_brand'] ?? '',
            'rotor_front' => $brakes['rotor_front'] ?? '',
            'rotor_rear'  => $brakes['rotor_rear'] ?? '',
        ], $post_id);

        // Frame.
        $frame = $ebike['frame'] ?? [];
        $this->set_field('ebike_frame', [
            'material'     => strtolower($frame['material'] ?? ''),
            'step_through' => !empty($frame['step_through']),
            'foldable'     => !empty($frame['foldable']),
            'wheel_size'   => $frame['wheel_size'] ?? '',
            'tire_width'   => $frame['tire_width'] ?? '',
            'suspension'   => strtolower($frame['suspension'] ?? ''),
        ], $post_id);
    }

    /**
     * Migrate EUC specific fields.
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Old ACF data.
     * @return void
     */
    private function migrate_euc_fields(int $post_id, array $acf): void {
        // EUC-specific fields if they exist.
        $this->set_field('euc_wheel', [
            'size'              => $acf['wheel_size'] ?? '',
            'tire_type'         => $acf['tire_type'] ?? '',
            'tire_width'        => $acf['tire_width'] ?? '',
            'suspension'        => !empty($acf['suspension']),
            'suspension_travel' => $acf['suspension_travel'] ?? '',
        ], $post_id);
    }

    /**
     * Migrate e-skate specific fields.
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Old ACF data.
     * @return void
     */
    private function migrate_eskate_fields(int $post_id, array $acf): void {
        $this->set_field('eskate_deck', [
            'length'   => $acf['deck_length'] ?? '',
            'width'    => $acf['deck_width'] ?? '',
            'material' => $acf['deck_material'] ?? '',
            'flex'     => $acf['deck_flex'] ?? '',
            'style'    => $acf['deck_style'] ?? '',
        ], $post_id);

        $this->set_field('eskate_wheels', [
            'size'       => $acf['wheel_size'] ?? '',
            'type'       => $acf['wheel_type'] ?? '',
            'durometer'  => $acf['wheel_durometer'] ?? '',
            'trucks'     => $acf['truck_type'] ?? '',
        ], $post_id);
    }

    /**
     * Migrate hoverboard specific fields.
     *
     * @param int   $post_id The post ID.
     * @param array $acf     Old ACF data.
     * @return void
     */
    private function migrate_hoverboard_fields(int $post_id, array $acf): void {
        $this->set_field('hoverboard_specs', [
            'wheel_size'   => $acf['wheel_size'] ?? '',
            'tire_type'    => $acf['tire_type'] ?? '',
            'ip_rating'    => $acf['ip_rating'] ?? '',
            'ul_certified' => !empty($acf['ul_certified']),
        ], $post_id);
    }

    /**
     * Migrate product image.
     *
     * @param int   $post_id  The post ID.
     * @param array $acf      Old ACF data.
     * @param array $old_data Full old product data.
     * @return void
     */
    private function migrate_image(int $post_id, array $acf, array $old_data): void {
        // Prefer big_thumbnail, fall back to thumbnail.
        $image_id = $acf['big_thumbnail'] ?? $acf['thumbnail'] ?? null;

        if ($image_id) {
            // For local migration, the image ID should work.
            // For remote migration, we'd need to download and sideload.
            if (is_numeric($image_id) && get_post($image_id)) {
                $this->set_field('product_image', $image_id, $post_id);
            }
        }
    }

    /**
     * Get editor rating from old ratings structure.
     *
     * @param array $acf Old ACF data.
     * @return string The editor rating (1-10) or empty.
     */
    private function get_editor_rating(array $acf): string {
        $ratings = $acf['ratings'] ?? [];

        // Use overall if it exists.
        if (!empty($ratings['overall'])) {
            return (string) $ratings['overall'];
        }

        // Otherwise calculate average of available ratings.
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
        ], function ($v) {
            return $v !== null && $v !== '';
        });

        if (empty($values)) {
            return '';
        }

        return (string) round(array_sum($values) / count($values), 1);
    }

    /**
     * Map old motor type to new value.
     *
     * @param string $old Old motor type value.
     * @return string New motor type value.
     */
    private function map_motor_type(string $old): string {
        $map = [
            'Hub Motor'  => 'hub',
            'Hub'        => 'hub',
            'Belt'       => 'belt',
            'Belt Drive' => 'belt',
            'Mid-Drive'  => 'mid_drive',
            'Gear Drive' => 'gear_drive',
        ];
        return $map[$old] ?? 'hub';
    }

    /**
     * Map motor position from ACF data.
     *
     * @param array $acf ACF data.
     * @return string Motor position.
     */
    private function map_motor_position(array $acf): string {
        if (!empty($acf['dual_motor']) || !empty($acf['front_motor'])) {
            return 'both';
        }
        if (!empty($acf['front_motor_only'])) {
            return 'front';
        }
        return 'rear';
    }

    /**
     * Map old brake type to new value.
     *
     * @param string $old Old brake type value.
     * @return string New brake type value.
     */
    private function map_brake_type(string $old): string {
        $old_lower = strtolower($old);
        if (strpos($old_lower, 'hydraulic') !== false) {
            return 'hydraulic_disc';
        }
        if (strpos($old_lower, 'disc') !== false || strpos($old_lower, 'mechanical') !== false) {
            return 'mechanical_disc';
        }
        if (strpos($old_lower, 'drum') !== false) {
            return 'drum';
        }
        if (strpos($old_lower, 'regen') !== false || strpos($old_lower, 'electronic') !== false) {
            return 'regenerative';
        }
        if (strpos($old_lower, 'foot') !== false) {
            return 'foot_brake';
        }
        if (empty($old) || strpos($old_lower, 'none') !== false) {
            return 'none';
        }
        return $old_lower;
    }

    /**
     * Map old tire type to new value.
     *
     * @param string $old Old tire type value.
     * @return string New tire type value.
     */
    private function map_tire_type(string $old): string {
        $map = [
            'Pneumatic'      => 'pneumatic',
            'Air'            => 'pneumatic',
            'Solid'          => 'solid',
            'Honeycomb'      => 'honeycomb',
            'Self-Healing'   => 'self_healing',
            'Tubeless'       => 'tubeless',
        ];
        return $map[$old] ?? strtolower(str_replace(' ', '_', $old));
    }

    /**
     * Map old suspension type to new value.
     *
     * @param string $old Old suspension type value.
     * @return string New suspension type value.
     */
    private function map_suspension_type(string $old): string {
        $map = [
            'None'      => 'none',
            'Spring'    => 'spring',
            'Hydraulic' => 'hydraulic',
            'Air'       => 'air',
            'Rubber'    => 'rubber',
            'Swingarm'  => 'swingarm',
        ];
        return $map[$old] ?? strtolower($old);
    }

    /**
     * Map old throttle type to new value.
     *
     * @param string|array $old Old throttle type value.
     * @return string New throttle type value.
     */
    private function map_throttle_type($old): string {
        if (is_array($old)) {
            $old = $old[0] ?? '';
        }
        $map = [
            'Thumb'      => 'thumb',
            'Trigger'    => 'trigger',
            'Half-Twist' => 'half_twist',
            'Full-Twist' => 'full_twist',
            'Wheel'      => 'wheel',
        ];
        return $map[$old] ?? strtolower(str_replace('-', '_', $old));
    }

    /**
     * Map old fold location to new value.
     *
     * @param string $old Old fold location value.
     * @return string New fold location value.
     */
    private function map_fold_location(string $old): string {
        return strtolower($old);
    }

    /**
     * Map old terrain to new value.
     *
     * @param string $old Old terrain value.
     * @return string New terrain value.
     */
    private function map_terrain(string $old): string {
        return strtolower(str_replace('-', '_', $old));
    }

    /**
     * Map old features array to new format.
     *
     * @param array $old_features Old features array.
     * @return array New features array.
     */
    private function map_escooter_features(array $old_features): array {
        $map = [
            'App'                         => 'app',
            'Speed Modes'                 => 'speed_modes',
            'Cruise Control'              => 'cruise_control',
            'Quick-Swap Battery'          => 'quick_swap_battery',
            'Folding Mechanism'           => 'folding',
            'Seat Add-On'                 => 'seat_addon',
            'Foldable Handlebars'         => 'foldable_handlebars',
            'Zero-Start'                  => 'zero_start',
            'Push-To-Start'               => 'push_to_start',
            'Turn Signals'                => 'turn_signals',
            'Brake Curve Adjustment'      => 'brake_adjustment',
            'Acceleration Adjustment'     => 'acceleration_adjustment',
            'Self-healing tires'          => 'self_healing_tires',
            'Speed limiting'              => 'speed_limiting',
            'Over-the-air firmware updates' => 'ota_updates',
            'Adjustable suspension'       => 'adjustable_suspension',
            'Steering dampener'           => 'steering_dampener',
            'Location tracking'           => 'location_tracking',
            'Electronic horn'             => 'electronic_horn',
            'NFC Unlock'                  => 'nfc_unlock',
        ];

        $new_features = [];
        foreach ($old_features as $feature) {
            if (isset($map[$feature])) {
                $new_features[] = $map[$feature];
            }
        }

        return $new_features;
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
     * Run full migration from remote source.
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
                } elseif ($this->dry_run) {
                    // Dry run doesn't count as migrated.
                } else {
                    $summary['skipped']++;
                }
            }

            $page++;
        } while ($page <= $result['total_pages']);

        return $summary;
    }
}
