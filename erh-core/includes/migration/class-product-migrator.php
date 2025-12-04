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

        // Debug: Log ACF data for first product.
        static $logged_acf_sample = false;
        if (!$logged_acf_sample) {
            $this->log('info', 'Sample ACF data keys: ' . implode(', ', array_keys($acf)));
            if (!empty($acf['product_type'])) {
                $this->log('info', "Sample product_type value: {$acf['product_type']}");
            } else {
                $this->log('warning', 'ACF product_type is empty - will try to infer from title');
            }
            $logged_acf_sample = true;
        }

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

        // Coupons (repeater field).
        if (!empty($acf['coupon']) && is_array($acf['coupon'])) {
            $this->set_field('coupon', $acf['coupon'], $post_id);
        }

        // Editor rating (from ratings.overall or calculate average).
        $editor_rating = $this->get_editor_rating($acf);
        $this->set_field('editor_rating', $editor_rating, $post_id);

        // Manufacturer specs.
        $this->set_field('manufacturer_top_speed', $acf['manufacturer_top_speed'] ?? '', $post_id);
        $this->set_field('manufacturer_range', $acf['manufacturer_range'] ?? '', $post_id);
        $this->set_field('weight', $acf['weight'] ?? '', $post_id);
        // max_weight_capacity might also be stored as max_load in some products.
        $max_capacity = $acf['max_weight_capacity'] ?? $acf['max_load'] ?? '';
        $this->set_field('max_weight_capacity', $max_capacity, $post_id);
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
            'ip_rating'          => $acf['ip_rating'] ?? '',
            'weather_resistance' => $acf['weather_resistance'] ?? '',
            'throttle_type'      => $this->map_throttle_type($acf['throttle_type'] ?? ''),
            'fold_location'      => $this->map_fold_location($acf['fold_location'] ?? ''),
            'terrain'            => $this->map_terrain($acf['terrain'] ?? ''),
            'stem_type'          => $acf['stem_type'] ?? '',
            'display_type'       => $acf['display_type'] ?? '',
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

        // Motor details (e-bike specific).
        $motor = $ebike['motor'] ?? [];
        $this->set_field('ebike_motor', [
            'brand'        => $motor['motor_brand'] ?? '',
            'model'        => $motor['motor_model'] ?? '',
            'assist_levels'=> $motor['assist_levels'] ?? '',
            'sensor_type'  => strtolower($motor['sensor_type'] ?? 'unknown'),
        ], $post_id);

        // Update shared motor fields from e-bike motor data.
        if (!empty($motor['power_nominal'])) {
            $this->set_field('motor_nominal_wattage', $motor['power_nominal'], $post_id);
        }
        if (!empty($motor['power_peak'])) {
            $this->set_field('motor_peak_wattage', $motor['power_peak'], $post_id);
        }
        if (!empty($motor['torque'])) {
            $this->set_field('motor_torque', $motor['torque'], $post_id);
        }

        // Battery details (e-bike specific).
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
        // E-bike claimed range goes to shared manufacturer_range.
        if (!empty($battery['range'])) {
            $this->set_field('manufacturer_range', $battery['range'], $post_id);
        }
        // Also check for range_claimed field name variant.
        if (!empty($battery['range_claimed'])) {
            $this->set_field('manufacturer_range', $battery['range_claimed'], $post_id);
        }

        // Weight and capacity (e-bike stores these in a nested group).
        $weight_capacity = $ebike['weight_and_capacity'] ?? [];
        if (!empty($weight_capacity['weight'])) {
            $this->set_field('weight', $weight_capacity['weight'], $post_id);
        }
        if (!empty($weight_capacity['weight_limit'])) {
            $this->set_field('max_weight_capacity', $weight_capacity['weight_limit'], $post_id);
        }

        // Speed & Class.
        $speed_class = $ebike['speed_and_class'] ?? [];
        $this->set_field('ebike_class', [
            'class'              => $speed_class['class'] ?? [],
            'top_assist_speed'   => $speed_class['top_assist_speed'] ?? '',
            'throttle_top_speed' => $speed_class['throttle_top_speed'] ?? '',
            'has_throttle'       => !empty($speed_class['throttle']),
        ], $post_id);

        // Update shared manufacturer_top_speed from e-bike top_assist_speed if not set.
        if (!empty($speed_class['top_assist_speed'])) {
            $this->set_field('manufacturer_top_speed', $speed_class['top_assist_speed'], $post_id);
        }

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
        // Check if product already has a featured image (skip if already migrated).
        if (has_post_thumbnail($post_id)) {
            $this->log('info', "Product {$post_id} already has a featured image, skipping");
            return;
        }

        // Try to get image URL from various sources.
        $image_url = $this->get_remote_image_url($acf, $old_data);

        if (empty($image_url)) {
            $this->log('warning', "No image found for product {$post_id}");
            return;
        }

        $this->log('info', "Downloading image: {$image_url}");

        // Sideload the image.
        $attachment_id = $this->sideload_image($image_url, $post_id);

        if ($attachment_id) {
            // Set as featured image (no need for separate ACF field).
            set_post_thumbnail($post_id, $attachment_id);

            $this->log('success', "Image uploaded successfully (attachment ID: {$attachment_id})");
        }
    }

    /**
     * Get the remote image URL from ACF data or featured_media.
     *
     * @param array $acf      ACF data.
     * @param array $old_data Full old product data.
     * @return string|null Image URL or null.
     */
    private function get_remote_image_url(array $acf, array $old_data): ?string {
        // Check ACF big_thumbnail field - it might be an array with URL.
        $big_thumbnail = $acf['big_thumbnail'] ?? null;
        if ($big_thumbnail) {
            // ACF image field can be array, URL string, or ID.
            if (is_array($big_thumbnail) && !empty($big_thumbnail['url'])) {
                return $big_thumbnail['url'];
            }
            if (is_string($big_thumbnail) && filter_var($big_thumbnail, FILTER_VALIDATE_URL)) {
                return $big_thumbnail;
            }
            // If it's an ID, we need to fetch the URL from remote.
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
            $url = $this->fetch_remote_media_url((int) $featured_media_id);
            if ($url) {
                return $url;
            }
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
        // Require necessary files for media handling.
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download the file to a temp location.
        $tmp = download_url($url, 60);

        if (is_wp_error($tmp)) {
            $this->log('error', "Failed to download image: " . $tmp->get_error_message());
            return null;
        }

        // Get the filename from URL.
        $url_path = wp_parse_url($url, PHP_URL_PATH);
        $filename = basename($url_path);

        // Prepare file array for sideloading.
        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp,
        ];

        // Sideload the file.
        $attachment_id = media_handle_sideload($file_array, $post_id);

        // Clean up temp file if sideload failed.
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
     * Infer product type from title or ACF data when product_type field is empty.
     *
     * @param string $title The product title.
     * @param array  $acf   The ACF data.
     * @return string|null The inferred product type slug or null.
     */
    private function infer_product_type(string $title, array $acf): ?string {
        $title_lower = strtolower($title);

        // Check for e-bike indicators.
        $ebike_keywords = ['e-bike', 'ebike', 'electric bike', 'eride', 'heckler', 'bullit', 'ransom', 'levo', 'turbo', 'powerfly', 'rail', 'fuel', 'shuttle', 'trance', 'reign', 'stance', 'habit', 'moterra'];
        foreach ($ebike_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return 'electric-bike';
            }
        }

        // Check for e-bike ACF data (has e-bikes group).
        if (!empty($acf['e-bikes']) && is_array($acf['e-bikes'])) {
            return 'electric-bike';
        }

        // Check for scooter indicators.
        $scooter_keywords = ['scooter', 'ninebot', 'kaabo', 'wolf', 'dualtron', 'vsett', 'inokim', 'emove', 'apollo', 'varla', 'gotrax', 'hiboy', 'segway', 'unagi', 'boosted rev', 'niu', 'mercane', 'zero', 'mantis', 'ghost', 'blade', 'thunder', 'eagle', 'falcon', 'phantom', 'speedway'];
        foreach ($scooter_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return 'electric-scooter';
            }
        }

        // Check for scooter ACF data.
        if (!empty($acf['deck_length']) || !empty($acf['handlebar_height']) || !empty($acf['throttle_type'])) {
            return 'electric-scooter';
        }

        // Check for EUC indicators.
        $euc_keywords = ['unicycle', 'euc', 'inmotion', 'gotway', 'begode', 'kingsong', 'veteran', 'leaperkim'];
        foreach ($euc_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return 'electric-unicycle';
            }
        }

        // Check for skateboard indicators.
        $eskate_keywords = ['skateboard', 'longboard', 'boosted board', 'evolve', 'meepo', 'exway', 'wowgo', 'backfire', 'ownboard', 'teamgee'];
        foreach ($eskate_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return 'electric-skateboard';
            }
        }

        // Check for hoverboard indicators.
        $hoverboard_keywords = ['hoverboard', 'swagway', 'swagtron', 'razor hovertrax'];
        foreach ($hoverboard_keywords as $keyword) {
            if (stripos($title, $keyword) !== false) {
                return 'hoverboard';
            }
        }

        // Could not determine type.
        return null;
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
