<?php
/**
 * Finder products JSON generation cron job.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

use ERH\CategoryConfig;

/**
 * Generates finder JSON files per product type for the database/finder tools.
 * Creates separate files: finder_escooter.json, finder_ebike.json, etc.
 */
class FinderJsonJob implements CronJobInterface {

    /**
     * Cron manager reference for locking.
     *
     * @var CronManager
     */
    private CronManager $cron_manager;

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Constructor.
     *
     * @param CronManager $cron_manager Cron manager instance.
     */
    public function __construct(CronManager $cron_manager) {
        global $wpdb;
        $this->cron_manager = $cron_manager;
        $this->wpdb = $wpdb;
    }

    /**
     * Get the job's display name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('Finder Products JSON Generator', 'erh-core');
    }

    /**
     * Get the job's description.
     *
     * @return string
     */
    public function get_description(): string {
        return __('Generates finder JSON files per product type with full specs for the database/finder tools.', 'erh-core');
    }

    /**
     * Get the WordPress hook name.
     *
     * @return string
     */
    public function get_hook_name(): string {
        return 'erh_cron_finder_json';
    }

    /**
     * Get the cron schedule.
     *
     * @return string
     */
    public function get_schedule(): string {
        return 'erh_two_hours';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function execute(): void {
        // Acquire lock to prevent concurrent execution.
        if (!$this->cron_manager->lock_job('finder-json', 600)) {
            error_log('[ERH Cron] Finder JSON job already running, skipping.');
            return;
        }

        try {
            $this->run();
        } finally {
            $this->cron_manager->unlock_job('finder-json');
            $this->cron_manager->record_run_time('finder-json');
        }
    }

    /**
     * Run the finder JSON generation logic.
     *
     * @return void
     */
    private function run(): void {
        $upload_dir = wp_upload_dir();
        $total_products = 0;

        foreach (CategoryConfig::get_type_to_finder_key() as $product_type => $slug) {
            $count = $this->generate_type_json($product_type, $slug, $upload_dir['basedir']);
            $total_products += $count;
        }

        error_log(sprintf(
            '[ERH Cron] Finder JSON generation completed. Total products: %d',
            $total_products
        ));
    }

    /**
     * Generate JSON file for a specific product type.
     *
     * @param string $product_type The product type.
     * @param string $slug The file slug.
     * @param string $base_dir The upload base directory.
     * @return int Number of products processed.
     */
    private function generate_type_json(string $product_type, string $slug, string $base_dir): int {
        $table_name = $this->wpdb->prefix . ERH_TABLE_PRODUCT_DATA;

        // Get all products of this type from the cache table.
        // Note: price, instock, bestlink are now stored per-geo in price_history.
        $products = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    product_id,
                    name,
                    product_type,
                    specs,
                    rating,
                    popularity_score,
                    permalink,
                    image_url,
                    price_history
                FROM {$table_name}
                WHERE product_type = %s
                ORDER BY popularity_score DESC, name ASC",
                $product_type
            )
        );

        if (empty($products)) {
            error_log("[ERH Cron] Finder JSON: No products found for type '{$product_type}'.");
            return 0;
        }

        $finder_items = [];

        foreach ($products as $product) {
            $specs = maybe_unserialize($product->specs);
            $price_history = maybe_unserialize($product->price_history);

            // Build the finder item with all relevant data.
            // Pricing data (current_price, instock, bestlink, averages) is geo-keyed in price_history.
            $item = [
                'id'            => (int) $product->product_id,
                'name'          => $product->name,
                'category'      => $slug,
                'url'           => $product->permalink,
                'thumbnail'     => $product->image_url ?: $this->get_default_thumbnail(),
                'rating'        => $product->rating !== null ? (float) $product->rating : null,
                'popularity'    => (int) $product->popularity_score,
                'pricing'       => is_array($price_history) ? $price_history : new \stdClass(),
                'specs'         => $this->flatten_specs($specs, $slug),
            ];

            $finder_items[] = $item;
        }

        // Generate JSON.
        $json_data = wp_json_encode($finder_items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json_data === false) {
            error_log("[ERH Cron] Finder JSON: Failed to encode JSON for '{$product_type}'.");
            return 0;
        }

        // Write the file.
        $file_path = $base_dir . "/finder_{$slug}.json";
        $result = file_put_contents($file_path, $json_data);

        if ($result === false) {
            error_log("[ERH Cron] Finder JSON: Failed to write file for '{$product_type}'.");
            return 0;
        }

        error_log(sprintf(
            '[ERH Cron] Finder JSON: %s updated. Items: %d, Size: %s',
            "finder_{$slug}.json",
            count($finder_items),
            size_format($result)
        ));

        return count($finder_items);
    }

    /**
     * Normalize specs for the JSON output.
     * Preserves nested structure but removes non-essential fields and normalizes values.
     *
     * @param mixed  $specs The product specs.
     * @param string $category The product category slug.
     * @return array The normalized specs with nested structure preserved.
     */
    private function flatten_specs($specs, string $category): array {
        if (!is_array($specs)) {
            return [];
        }

        // Fields to exclude from output (non-spec fields).
        $exclude_fields = [
            'product_type',
            'big_thumbnail',
            'gallery',
            'product_video',
            'coupon',
            'related_products',
            'is_obsolete',
            'variants',
        ];

        // Map category (finder key) to ACF wrapper key.
        // First normalize the finder key to canonical key, then get ACF wrapper.
        $canonical_key = CategoryConfig::normalize_key( $category );
        $category_data = CategoryConfig::get_by_key( $canonical_key );
        $nested_key = $category_data ? $category_data['acf_wrapper'] : null;
        $output = [];

        // Handle nested product type structure.
        if ($nested_key && isset($specs[$nested_key]) && is_array($specs[$nested_key])) {
            // The product type group contains the main specs - preserve its nested structure.
            $output = $this->normalize_nested_specs($specs[$nested_key]);

            // Also include any top-level fields (basic info, etc.).
            foreach ($specs as $key => $value) {
                if ($key === $nested_key || in_array($key, $exclude_fields, true)) {
                    continue;
                }
                $output[$key] = $this->normalize_nested_specs($value);
            }
        } else {
            // No nested structure found - normalize everything.
            foreach ($specs as $key => $value) {
                if (in_array($key, $exclude_fields, true)) {
                    continue;
                }
                $output[$key] = $this->normalize_nested_specs($value);
            }
        }

        return $output;
    }

    /**
     * Recursively normalize nested specs while preserving structure.
     *
     * @param mixed $value The value to normalize.
     * @return mixed The normalized value.
     */
    private function normalize_nested_specs($value) {
        if (!is_array($value)) {
            return $this->normalize_value($value);
        }

        // Check if it's a simple indexed array (like ['Front', 'Rear']).
        if ($this->is_simple_array($value)) {
            return array_map([$this, 'normalize_value'], $value);
        }

        // Associative array - recurse into it.
        $normalized = [];
        foreach ($value as $k => $v) {
            $normalized[$k] = $this->normalize_nested_specs($v);
        }
        return $normalized;
    }

    /**
     * Normalize a spec value for JSON output.
     *
     * @param mixed $value The value to normalize.
     * @return mixed The normalized value.
     */
    private function normalize_value($value) {
        // Handle empty strings.
        if ($value === '' || $value === null) {
            return null;
        }

        // Handle numeric strings.
        if (is_string($value) && is_numeric($value)) {
            // Check if it's a float or int.
            if (strpos($value, '.') !== false) {
                return (float) $value;
            }
            return (int) $value;
        }

        // Handle booleans.
        if ($value === 'true' || $value === '1') {
            return true;
        }
        if ($value === 'false' || $value === '0') {
            return false;
        }

        return $value;
    }

    /**
     * Check if an array is a simple indexed array (not associative/nested).
     *
     * @param array $arr The array to check.
     * @return bool True if simple array.
     */
    private function is_simple_array(array $arr): bool {
        if (empty($arr)) {
            return true;
        }

        // Check if all values are scalar.
        foreach ($arr as $value) {
            if (is_array($value) || is_object($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the default thumbnail URL.
     *
     * @return string
     */
    private function get_default_thumbnail(): string {
        return 'https://eridehero.com/wp-content/uploads/2024/07/kick-scooter-1.svg';
    }
}
