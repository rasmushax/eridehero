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
            case 'electric-unicycle':
            case 'electric-skateboard':
            case 'hoverboard':
                $this->log('info', "Product type '{$product_type_slug}' migration not yet implemented");
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

        // YouTube review might be in review.youtube_video or youtube_review.
        $youtube = $acf['youtube_review'] ?? $acf['review']['youtube_video'] ?? '';
        $this->set_field('youtube_review', $youtube, $post_id);

        // Editor rating from ratings.overall or calculate average.
        $editor_rating = $this->get_editor_rating($acf);
        $this->set_field('editor_rating', $editor_rating, $post_id);
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

        // Motor group.
        $escooter['motor'] = [
            'motor_position' => $this->normalize_array($acf['motors'] ?? []),
            'motor_type'     => $acf['motor_type'] ?? '',
            'wheel_drive'    => $acf['Wheel Drive'] ?? $acf['wheel_drive'] ?? '',
            'voltage'        => $acf['voltage'] ?? '',
            'power_nominal'  => $acf['nominal_motor_wattage'] ?? '',
            'power_peak'     => $acf['total_peak_wattage'] ?? '',
        ];

        // Battery group.
        $escooter['battery'] = [
            'capacity'      => $acf['battery_capacity'] ?? '',
            'voltage'       => $acf['battery_voltage'] ?? '',
            'amphours'      => $acf['battery_amphours'] ?? '',
            'type'          => $acf['battery_type'] ?? '',
            'brand'         => $acf['battery_brand'] ?? '',
            'charging_time' => $acf['charging_time'] ?? '',
        ];

        // Brakes group.
        $escooter['brakes'] = [
            'type'        => $this->normalize_array($acf['brakes'] ?? []),
            'rotor_front' => '',
            'rotor_rear'  => '',
        ];

        // Wheels group.
        $escooter['wheels'] = [
            'tire_size_front' => $acf['tire_size_front'] ?? '',
            'tire_size_rear'  => $acf['tire_size_rear'] ?? '',
            'tire_width'      => $acf['tire_width'] ?? '',
            'tire_type'       => $this->normalize_array($acf['tires'] ?? []),
            'pneumatic_type'  => $acf['pneumatic_type'] ?? '',
        ];

        // Suspension group.
        $escooter['suspension'] = [
            'type'         => $this->map_suspension_type($acf['suspension'] ?? []),
            'front_travel' => '',
            'rear_travel'  => '',
        ];

        // Dimensions group.
        $escooter['dimensions'] = [
            'deck_length'         => $acf['deck_length'] ?? '',
            'deck_width'          => $acf['deck_width'] ?? '',
            'ground_clearance'    => $acf['ground_clearance'] ?? '',
            'handlebar_height_min'=> $acf['deck_to_handlebar_min'] ?? '',
            'handlebar_height_max'=> $acf['deck_to_handlebar_max'] ?? '',
            'handlebar_width'     => $acf['handlebar_width'] ?? '',
            'weight'              => $acf['weight'] ?? '',
            'max_load'            => $acf['max_load'] ?? '',
            'unfolded_length'     => $acf['unfolded_depth'] ?? '',
            'unfolded_width'      => $acf['unfolded_width'] ?? '',
            'unfolded_height'     => $acf['unfolded_height'] ?? '',
            'folded_length'       => $acf['folded_depth'] ?? '',
            'folded_width'        => $acf['folded_width'] ?? '',
            'folded_height'       => $acf['folded_height'] ?? '',
        ];

        // Lighting group.
        $escooter['lighting'] = [
            'lights'       => $this->normalize_array($acf['lights'] ?? []),
            'front_lumens' => '',
            'turn_signals' => $this->has_feature($acf, 'Turn Signals'),
        ];

        // Other group.
        $escooter['other'] = [
            'throttle_type' => $this->get_first($acf['throttle_type'] ?? []),
            'fold_location' => $acf['fold_location'] ?? '',
            'terrain'       => $acf['terrain'] ?? '',
            'ip_rating'     => $this->get_first($acf['weather_resistance'] ?? []),
            'footrest'      => !empty($acf['footrest']),
            'kickstand'     => true, // Most scooters have kickstands.
            'stem_type'     => '',
            'display_type'  => '',
        ];

        // Features.
        $escooter['features'] = $this->map_escooter_features($acf['features'] ?? []);

        // Save the entire e-scooters group.
        $this->set_field('e-scooters', $escooter, $post_id);

        $this->log('info', "Migrated e-scooters group with " . count($escooter) . " sub-groups");
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
            'Front fork'    => 'Front Fork',
            'Front spring'  => 'Front Spring',
            'Rear spring'   => 'Rear Spring',
            'Rear swingarm' => 'Rear Swingarm',
            'Dual spring'   => 'Dual Spring',
        ];

        $result = [];
        foreach ($suspension as $s) {
            $result[] = $map[$s] ?? $s;
        }
        return $result;
    }

    /**
     * Map old feature values to new format.
     *
     * @param array $features Old feature values.
     * @return array New feature values.
     */
    private function map_escooter_features(array $features): array {
        $map = [
            'App'                           => 'App',
            'Speed Modes'                   => 'Speed Modes',
            'Cruise Control'                => 'Cruise Control',
            'Folding Mechanism'             => 'Folding',
            'Foldable Handlebars'           => 'Foldable Handlebars',
            'Push-To-Start'                 => 'Push-To-Start',
            'Zero-Start'                    => 'Zero-Start',
            'Turn Signals'                  => 'Turn Signals',
            'Brake Curve Adjustment'        => 'Brake Curve Adjustment',
            'Acceleration Adjustment'       => 'Acceleration Adjustment',
            'Speed limiting'                => 'Speed Limiting',
            'Over-the-air firmware updates' => 'OTA Updates',
            'Location tracking'             => 'Location Tracking',
            'Quick-Swap Battery'            => 'Quick-Swap Battery',
            'Adjustable suspension'         => 'Adjustable Suspension',
            'Steering dampener'             => 'Steering Damper',
            'Electronic horn'               => 'Electronic Horn',
            'NFC Unlock'                    => 'NFC Unlock',
            'Self-healing tires'            => 'Self-Healing Tires',
            'Seat Add-On'                   => 'Seat Option',
        ];

        $result = [];
        foreach ($features as $f) {
            if (isset($map[$f])) {
                $result[] = $map[$f];
            } elseif (in_array($f, array_values($map), true)) {
                $result[] = $f;
            }
        }
        return $result;
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
