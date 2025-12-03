<?php
/**
 * Price update cron job for recording daily price snapshots.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

use ERH\Pricing\PriceFetcher;
use ERH\Database\PriceHistory;

/**
 * Records daily price snapshots to the wp_product_daily_prices table.
 */
class PriceUpdateJob implements CronJobInterface {

    /**
     * Price fetcher instance.
     *
     * @var PriceFetcher
     */
    private PriceFetcher $price_fetcher;

    /**
     * Price history database instance.
     *
     * @var PriceHistory
     */
    private PriceHistory $price_history;

    /**
     * Cron manager reference for locking.
     *
     * @var CronManager
     */
    private CronManager $cron_manager;

    /**
     * Constructor.
     *
     * @param PriceFetcher $price_fetcher Price fetcher instance.
     * @param PriceHistory $price_history Price history database instance.
     * @param CronManager $cron_manager Cron manager instance.
     */
    public function __construct(
        PriceFetcher $price_fetcher,
        PriceHistory $price_history,
        CronManager $cron_manager
    ) {
        $this->price_fetcher = $price_fetcher;
        $this->price_history = $price_history;
        $this->cron_manager = $cron_manager;
    }

    /**
     * Get the job's display name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('Daily Price Update', 'erh-core');
    }

    /**
     * Get the job's description.
     *
     * @return string
     */
    public function get_description(): string {
        return __('Records daily price snapshots for price history charts.', 'erh-core');
    }

    /**
     * Get the WordPress hook name.
     *
     * @return string
     */
    public function get_hook_name(): string {
        return 'erh_cron_price_update';
    }

    /**
     * Get the cron schedule.
     *
     * @return string
     */
    public function get_schedule(): string {
        return 'daily';
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function execute(): void {
        // Acquire lock to prevent concurrent execution.
        if (!$this->cron_manager->lock_job('price-update', 600)) {
            error_log('[ERH Cron] Price update job already running, skipping.');
            return;
        }

        try {
            $this->run();
        } finally {
            $this->cron_manager->unlock_job('price-update');
            $this->cron_manager->record_run_time('price-update');
        }
    }

    /**
     * Run the price update logic.
     *
     * @return void
     */
    private function run(): void {
        global $wpdb;

        $today = current_time('Y-m-d');
        $updated_count = 0;
        $skipped_count = 0;
        $anomaly_count = 0;

        // Get all published products.
        $products = get_posts([
            'post_type'      => 'products',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($products as $product_id) {
            $prices = $this->price_fetcher->get_prices($product_id);

            // Skip if no price info available.
            if (empty($prices)) {
                $skipped_count++;
                continue;
            }

            $current_price = $prices[0]['price'] ?? null;
            $domain = $prices[0]['domain'] ?? '';

            // Validate price.
            if (!is_numeric($current_price) || $current_price <= 0) {
                $anomaly_count++;
                continue;
            }

            // Record the daily price.
            $this->price_history->record_price(
                $product_id,
                (float) $current_price,
                $domain,
                $today
            );

            $updated_count++;
        }

        // Run cleanup of old entries.
        $this->cleanup_old_entries();

        // Log results.
        error_log(sprintf(
            '[ERH Cron] Daily price update completed. Updated: %d, Skipped: %d, Anomalies: %d',
            $updated_count,
            $skipped_count,
            $anomaly_count
        ));
    }

    /**
     * Clean up price entries older than 2 years.
     *
     * @return void
     */
    private function cleanup_old_entries(): void {
        $deleted = $this->price_history->cleanup(730); // 2 years

        if ($deleted > 0) {
            error_log(sprintf('[ERH Cron] Cleaned up %d old price entries.', $deleted));
        }
    }
}
