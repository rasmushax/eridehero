<?php
/**
 * Product Migration Handler
 *
 * Migrates products from eridehero.com via REST API.
 * Uses 1-to-1 field copy since source and destination ACF structures match.
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
     * @param int    $post_id          The post ID.
     * @param array  $acf              Source ACF data.
     * @param string $product_type_slug The product type slug.
     * @return void
     */
    private function migrate_acf_fields(int $post_id, array $acf, string $product_type_slug): void {
        // Basic info (all products).
        $this->set_field('model', $acf['model'] ?? '', $post_id);
        $this->set_field('release_year', $acf['release_year'] ?? '', $post_id);
        $this->set_field('release_quarter', $acf['release_quarter'] ?? 'Unknown', $post_id);
        $this->set_field('youtube_review', $acf['youtube_review'] ?? '', $post_id);

        // Editor rating (from ratings.overall or calculate average).
        $editor_rating = $this->get_editor_rating($acf);
        $this->set_field('editor_rating', $editor_rating, $post_id);

        // Performance tests (all products) - copy directly, same field names.
        $perf_fields = [
            'tested_top_speed',
            'acceleration:_0-15_mph', 'acceleration:_0-20_mph', 'acceleration:_0-25_mph',
            'acceleration:_0-30_mph', 'acceleration:_0-to-top',
            'fastest_0_15', 'fastest_0_20', 'fastest_0_25', 'fastest_0_30', 'fastest_0_top',
            'tested_range_fast', 'tested_range_regular', 'tested_range_slow',
            'tested_range_avg_speed_fast', 'tested_range_avg_speed_regular', 'tested_range_avg_speed_slow',
            'brake_distance', 'hill_climbing',
            'manufacturer_top_speed', 'manufacturer_range', 'max_incline', 'ideal_incline',
        ];

        foreach ($perf_fields as $field) {
            if (isset($acf[$field]) && $acf[$field] !== '') {
                $this->set_field($field, $acf[$field], $post_id);
            }
        }

        // Product-type specific fields.
        switch ($product_type_slug) {
            case 'electric-bike':
                $this->migrate_ebike_fields($post_id, $acf);
                break;
            // Other product types will be added later.
            case 'electric-scooter':
            case 'electric-unicycle':
            case 'electric-skateboard':
            case 'hoverboard':
                $this->log('info', "Product type '{$product_type_slug}' migration not yet implemented");
                break;
        }
    }

    /**
     * Migrate e-bike specific fields.
     *
     * Since source and destination ACF structures are identical,
     * we just copy the entire e-bikes group as-is.
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
     * Migrate product image.
     *
     * @param int   $post_id  The post ID.
     * @param array $acf      Source ACF data.
     * @param array $old_data Full source product data.
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
        // Check for e-bike ACF data (has e-bikes group).
        if (!empty($acf['e-bikes']) && is_array($acf['e-bikes'])) {
            return 'electric-bike';
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
