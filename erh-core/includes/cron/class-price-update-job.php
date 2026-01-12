<?php
/**
 * Price update cron job for recording daily price snapshots.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

use ERH\GeoConfig;
use ERH\Pricing\PriceFetcher;
use ERH\Database\PriceHistory;

/**
 * Records daily price snapshots to the wp_product_daily_prices table.
 * Records best price per product per geo per currency.
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
        return __('Records daily price snapshots per geo and currency for price history charts.', 'erh-core');
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
        $today = current_time('Y-m-d');
        $updated_count = 0;
        $skipped_count = 0;
        $geo_currency_count = 0;

        // Get all published products.
        $products = get_posts([
            'post_type'      => 'products',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($products as $product_id) {
            // Get ALL prices from HFT (includes all geos and currencies).
            $all_prices = $this->price_fetcher->get_prices($product_id);

            // Skip if no price info available.
            if (empty($all_prices)) {
                $skipped_count++;
                continue;
            }

            // Group prices by region, keeping only the best (lowest in-stock) for each.
            $best_by_geo_currency = $this->get_best_prices_by_geo_currency($all_prices);

            if (empty($best_by_geo_currency)) {
                $skipped_count++;
                continue;
            }

            // Record each region's price.
            foreach ($best_by_geo_currency as $best) {
                $this->price_history->record_price(
                    $product_id,
                    $best['price'],
                    $best['currency'],
                    $best['domain'],
                    $best['geo'],
                    $today
                );
                $geo_currency_count++;
            }

            $updated_count++;
        }

        // Run cleanup of old entries.
        $this->cleanup_old_entries();

        // Log results.
        error_log(sprintf(
            '[ERH Cron] Daily price update completed. Products: %d, Skipped: %d, Geo+Currency entries: %d',
            $updated_count,
            $skipped_count,
            $geo_currency_count
        ));
    }

    /**
     * Group prices by region and return best price for each region.
     *
     * Maps country codes to the 5-region model (US, GB, EU, CA, AU) and
     * validates that currency matches the expected region currency.
     *
     * @param array<int, array<string, mixed>> $prices All prices from PriceFetcher.
     * @return array<string, array<string, mixed>> Best price per region.
     */
    private function get_best_prices_by_geo_currency(array $prices): array {
        $grouped = [];

        foreach ($prices as $price_data) {
            // Skip invalid prices.
            if (!isset($price_data['price']) || !is_numeric($price_data['price']) || $price_data['price'] <= 0) {
                continue;
            }

            $raw_geo = strtoupper($price_data['geo'] ?? '');
            $currency = strtoupper($price_data['currency'] ?? 'USD');

            // Map to region and validate currency matches.
            $region = $this->map_to_region($raw_geo, $currency);
            if ($region === null) {
                continue; // Skip prices that don't fit our region model.
            }

            // Verify currency matches expected for this region.
            $expected_currency = GeoConfig::get_currency($region);
            if ($currency !== $expected_currency) {
                continue;
            }

            $price = (float)$price_data['price'];
            $in_stock = $price_data['in_stock'] ?? false;

            // Determine if this is a better price for this region.
            $is_better = false;

            if (!isset($grouped[$region])) {
                // First entry for this region.
                $is_better = true;
            } else {
                $existing = $grouped[$region];

                // Prefer in-stock over out-of-stock.
                if ($in_stock && !$existing['in_stock']) {
                    $is_better = true;
                } elseif ($in_stock === $existing['in_stock']) {
                    // Same stock status, compare prices.
                    if ($price < $existing['price']) {
                        $is_better = true;
                    }
                }
                // If current is out-of-stock but existing is in-stock, don't replace.
            }

            if ($is_better) {
                $grouped[$region] = [
                    'price'    => $price,
                    'currency' => $currency,
                    'domain'   => $price_data['domain'] ?? '',
                    'geo'      => $region, // Store the region, not the raw geo.
                    'in_stock' => $in_stock,
                ];
            }
        }

        return $grouped;
    }

    /**
     * Map a geo code and currency to one of the 5 regions.
     *
     * @param string $geo      The raw geo/country code (e.g., 'US', 'DE', 'FR').
     * @param string $currency The currency code.
     * @return string|null The region code or null if no match.
     */
    private function map_to_region(string $geo, string $currency): ?string {
        // Direct region matches.
        if (in_array($geo, GeoConfig::REGIONS, true)) {
            return $geo;
        }

        // EU country codes map to EU region.
        if (GeoConfig::is_eu_country($geo)) {
            return 'EU';
        }

        // Empty geo: infer from currency.
        if ($geo === '') {
            switch ($currency) {
                case 'USD':
                    return 'US';
                case 'EUR':
                    return 'EU';
                case 'GBP':
                    return 'GB';
                case 'CAD':
                    return 'CA';
                case 'AUD':
                    return 'AU';
            }
        }

        return null;
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
