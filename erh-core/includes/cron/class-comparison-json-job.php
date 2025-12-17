<?php
/**
 * Comparison products JSON generation cron job.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

/**
 * Generates the comparison_products.json file for the head-to-head comparison tool.
 */
class ComparisonJsonJob implements CronJobInterface {

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
        return __('Comparison Products JSON Generator', 'erh-core');
    }

    /**
     * Get the job's description.
     *
     * @return string
     */
    public function get_description(): string {
        return __('Generates the comparison products JSON file for the head-to-head comparison tool.', 'erh-core');
    }

    /**
     * Get the WordPress hook name.
     *
     * @return string
     */
    public function get_hook_name(): string {
        return 'erh_cron_comparison_json';
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
        if (!$this->cron_manager->lock_job('comparison-json', 300)) {
            error_log('[ERH Cron] Comparison JSON job already running, skipping.');
            return;
        }

        try {
            $this->run();
        } finally {
            $this->cron_manager->unlock_job('comparison-json');
            $this->cron_manager->record_run_time('comparison-json');
        }
    }

    /**
     * Run the comparison JSON generation logic.
     *
     * @return void
     */
    private function run(): void {
        $table_name = $this->wpdb->prefix . ERH_TABLE_PRODUCT_DATA;

        // Get all products from the cache table.
        // Price is stored in price_history JSON field per-geo.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $products = $this->wpdb->get_results(
            "SELECT
                product_id,
                name,
                product_type,
                price_history,
                image_url,
                permalink,
                popularity_score
            FROM {$table_name}
            WHERE product_type IS NOT NULL
            ORDER BY popularity_score DESC, name ASC"
        );

        if (empty($products)) {
            error_log('[ERH Cron] Comparison JSON: No products found in cache.');
            return;
        }

        $comparison_items = [];

        foreach ($products as $product) {
            // Extract prices for all geos.
            $prices = $this->get_all_geo_prices($product->price_history);

            $comparison_items[] = [
                'id'         => (int) $product->product_id,
                'name'       => $product->name,
                'category'   => $this->normalize_category($product->product_type),
                'thumbnail'  => $product->image_url ?: $this->get_default_thumbnail(),
                'prices'     => $prices,
                'url'        => $product->permalink,
                'popularity' => (int) $product->popularity_score,
            ];
        }

        // Generate JSON.
        $json_data = wp_json_encode($comparison_items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json_data === false) {
            error_log('[ERH Cron] Comparison JSON: Failed to encode JSON.');
            return;
        }

        // Get upload directory.
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/comparison_products.json';

        // Write the file.
        $result = file_put_contents($file_path, $json_data);

        if ($result === false) {
            error_log('[ERH Cron] Comparison JSON: Failed to write file.');
            return;
        }

        error_log(sprintf(
            '[ERH Cron] Comparison products JSON updated. Items: %d, Size: %s',
            count($comparison_items),
            size_format($result)
        ));
    }

    /**
     * Extract current prices for all geos from price_history.
     *
     * @param string|null $price_history Serialized price history data.
     * @return object Prices keyed by geo code (e.g., {US: 999.99, GB: 849.99}).
     */
    private function get_all_geo_prices(?string $price_history): object {
        $prices = new \stdClass();

        if (empty($price_history)) {
            return $prices;
        }

        $data = maybe_unserialize($price_history);
        if (!is_array($data)) {
            return $prices;
        }

        foreach ($data as $geo => $geo_data) {
            if (is_array($geo_data) && !empty($geo_data['current_price'])) {
                $prices->$geo = (float) $geo_data['current_price'];
            }
        }

        return $prices;
    }

    /**
     * Normalize product type to category slug for filtering.
     *
     * @param string $product_type The product type.
     * @return string The normalized category slug.
     */
    private function normalize_category(string $product_type): string {
        $map = [
            'Electric Scooter'    => 'escooter',
            'Electric Bike'       => 'ebike',
            'Electric Skateboard' => 'eskate',
            'Electric Unicycle'   => 'euc',
            'Hoverboard'          => 'hoverboard',
        ];

        return $map[$product_type] ?? strtolower(str_replace(' ', '-', $product_type));
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
