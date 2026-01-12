<?php
/**
 * Cache rebuild cron job for rebuilding the wp_product_data table.
 *
 * @package ERH\Cron
 */

declare(strict_types=1);

namespace ERH\Cron;

use ERH\Pricing\PriceFetcher;
use ERH\Database\ProductCache;
use ERH\Database\PriceHistory;
use ERH\Database\PriceTracker;
use ERH\Database\ViewTracker;
use ERH\Scoring\ProductScorer;

/**
 * Rebuilds the product_data cache table with fresh data.
 *
 * Architecture:
 * - HFT stores prices per-country (DE, FR, IT, etc.)
 * - This job groups them into 5 regions: US, GB, EU, CA, AU
 * - Frontend detects user's country and maps to region
 *
 * The price_history field contains region-keyed pricing data:
 * [
 *     'US' => [
 *         'current_price' => 499.99,
 *         'currency' => 'USD',
 *         'instock' => true,
 *         'retailer' => 'Amazon',
 *         'bestlink' => 'https://...',
 *         // Period averages
 *         'avg_3m' => 549.99, 'avg_6m' => 579.99, 'avg_12m' => 599.99, 'avg_all' => 589.99,
 *         // Period lows
 *         'low_3m' => 449.99, 'low_6m' => 429.99, 'low_12m' => 399.99, 'low_all' => 379.99,
 *         // Period highs
 *         'high_3m' => 649.99, 'high_6m' => 699.99, 'high_12m' => 749.99, 'high_all' => 799.99,
 *         'updated_at' => '2025-12-17 10:00:00',
 *     ],
 *     'GB' => [...],  // GBP prices
 *     'EU' => [...],  // EUR prices (aggregated from DE, FR, IT, ES, etc.)
 *     'CA' => [...],  // CAD prices
 *     'AU' => [...],  // AUD prices
 * ]
 */
class CacheRebuildJob implements CronJobInterface {

    /**
     * Supported product types.
     */
    private const PRODUCT_TYPES = [
        'Electric Scooter',
        'Electric Bike',
        'Electric Skateboard',
        'Electric Unicycle',
        'Hoverboard',
    ];

    /**
     * Regions to build price data for.
     * These are the 5 primary regions we track prices for.
     * HFT stores per-country (DE, FR, etc.), we group into regions here.
     */
    private const REGIONS = ['US', 'GB', 'EU', 'CA', 'AU'];

    /**
     * EU country codes that map to the EU region.
     * Prices from these countries are grouped under 'EU' in the cache.
     */
    private const EU_COUNTRIES = [
        'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'IE', 'PT', 'FI',
        'GR', 'LU', 'SK', 'SI', 'EE', 'LV', 'LT', 'CY', 'MT',
        'PL', 'CZ', 'HU', 'RO', 'BG', 'HR', 'DK', 'SE', 'NO', 'CH', 'EU',
    ];

    /**
     * Price fetcher instance.
     *
     * @var PriceFetcher
     */
    private PriceFetcher $price_fetcher;

    /**
     * Product cache instance.
     *
     * @var ProductCache
     */
    private ProductCache $product_cache;

    /**
     * Price history instance.
     *
     * @var PriceHistory
     */
    private PriceHistory $price_history;

    /**
     * Price tracker instance.
     *
     * @var PriceTracker
     */
    private PriceTracker $price_tracker;

    /**
     * View tracker instance.
     *
     * @var ViewTracker
     */
    private ViewTracker $view_tracker;

    /**
     * Cron manager reference for locking.
     *
     * @var CronManager
     */
    private CronManager $cron_manager;

    /**
     * Product scorer instance.
     *
     * @var ProductScorer
     */
    private ProductScorer $product_scorer;

    /**
     * Constructor.
     *
     * @param PriceFetcher  $price_fetcher  Price fetcher instance.
     * @param ProductCache  $product_cache  Product cache instance.
     * @param PriceHistory  $price_history  Price history instance.
     * @param PriceTracker  $price_tracker  Price tracker instance.
     * @param ViewTracker   $view_tracker   View tracker instance.
     * @param CronManager   $cron_manager   Cron manager instance.
     * @param ProductScorer $product_scorer Product scorer instance.
     */
    public function __construct(
        PriceFetcher $price_fetcher,
        ProductCache $product_cache,
        PriceHistory $price_history,
        PriceTracker $price_tracker,
        ViewTracker $view_tracker,
        CronManager $cron_manager,
        ProductScorer $product_scorer
    ) {
        $this->price_fetcher  = $price_fetcher;
        $this->product_cache  = $product_cache;
        $this->price_history  = $price_history;
        $this->price_tracker  = $price_tracker;
        $this->view_tracker   = $view_tracker;
        $this->cron_manager   = $cron_manager;
        $this->product_scorer = $product_scorer;
    }

    /**
     * Get the job's display name.
     *
     * @return string
     */
    public function get_name(): string {
        return __('Product Cache Rebuild', 'erh-core');
    }

    /**
     * Get the job's description.
     *
     * @return string
     */
    public function get_description(): string {
        return __('Rebuilds the product finder cache with fresh pricing and stats.', 'erh-core');
    }

    /**
     * Get the WordPress hook name.
     *
     * @return string
     */
    public function get_hook_name(): string {
        return 'erh_cron_cache_rebuild';
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
        if (!$this->cron_manager->lock_job('cache-rebuild', 1800)) {
            error_log('[ERH Cron] Cache rebuild job already running, skipping.');
            return;
        }

        try {
            $this->run();
        } finally {
            $this->cron_manager->unlock_job('cache-rebuild');
            $this->cron_manager->record_run_time('cache-rebuild');
        }
    }

    /**
     * Run the cache rebuild logic.
     *
     * Uses batch processing to minimize database queries.
     *
     * @return void
     */
    private function run(): void {
        $start_time = microtime(true);
        $processed_count = 0;
        $query_stats = ['hft_queries' => 0, 'history_queries' => 0];

        foreach (self::PRODUCT_TYPES as $product_type) {
            $products = $this->get_products_by_type($product_type);

            if (empty($products)) {
                continue;
            }

            // Fetch all data in bulk for this product type.
            $bulk_start = microtime(true);

            // 1. Get all current prices from HFT in one query.
            $all_prices = $this->price_fetcher->get_prices_bulk($products);
            $query_stats['hft_queries']++;

            // 2. Get all historical prices in one query.
            $all_history = $this->price_history->get_history_bulk($products);
            $query_stats['history_queries']++;

            $bulk_elapsed = round((microtime(true) - $bulk_start) * 1000, 1);

            error_log(sprintf(
                '[ERH Cron] Bulk fetch for %s: %d products, %d price rows, %d history rows in %sms',
                $product_type,
                count($products),
                array_sum(array_map('count', $all_prices)),
                array_sum(array_map('count', $all_history)),
                $bulk_elapsed
            ));

            // Process each product using pre-fetched data.
            foreach ($products as $product_id) {
                $prices = $all_prices[$product_id] ?? [];
                $history = $all_history[$product_id] ?? [];

                $this->rebuild_product_with_data($product_id, $product_type, $prices, $history);
                $processed_count++;
            }
        }

        $elapsed = round(microtime(true) - $start_time, 2);

        error_log(sprintf(
            '[ERH Cron] Cache rebuild completed. Processed: %d products in %ss (HFT queries: %d, History queries: %d)',
            $processed_count,
            $elapsed,
            $query_stats['hft_queries'],
            $query_stats['history_queries']
        ));

        // Update post modified dates for specific finder pages (for cache invalidation).
        $this->touch_finder_pages();
    }

    /**
     * Get all product IDs of a specific type.
     *
     * @param string $product_type The product type.
     * @return array<int> Product IDs.
     */
    private function get_products_by_type(string $product_type): array {
        // Convert "Electric Scooter" to "electric-scooter" for taxonomy query.
        $taxonomy_slug = sanitize_title($product_type);

        return get_posts([
            'post_type'      => 'products',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'slug',
                    'terms'    => $taxonomy_slug,
                ],
            ],
        ]);
    }

    /**
     * Rebuild cache data for a single product.
     *
     * @deprecated Use rebuild_product_with_data() for bulk processing.
     * @param int $product_id The product post ID.
     * @param string $product_type The product type.
     * @return void
     */
    private function rebuild_product(int $product_id, string $product_type): void {
        // Fallback to individual queries (not used in normal flow).
        $prices = $this->price_fetcher->get_prices($product_id);
        $history = $this->price_history->get_history($product_id, 0, null, null, 'DESC');

        $this->rebuild_product_with_data($product_id, $product_type, $prices, $history);
    }

    /**
     * Rebuild cache data for a single product using pre-fetched data.
     *
     * This is the optimized version that works with bulk-fetched price and history data
     * to avoid N+1 queries.
     *
     * @param int   $product_id   The product post ID.
     * @param string $product_type The product type.
     * @param array $prices       Pre-fetched prices for this product (from get_prices_bulk).
     * @param array $history      Pre-fetched history for this product (from get_history_bulk).
     * @return void
     */
    private function rebuild_product_with_data(int $product_id, string $product_type, array $prices, array $history): void {
        $product_data = [
            'product_id'       => $product_id,
            'product_type'     => $product_type,
            'name'             => get_the_title($product_id),
            'specs'            => $this->get_normalized_specs($product_id, $product_type),
            'permalink'        => get_permalink($product_id),
            'image_url'        => $this->get_product_image($product_id),
            'rating'           => null,
            'popularity_score' => 0,
            'price_history'    => [],
        ];

        // Build region-keyed price_history using pre-fetched data.
        $price_history = [];
        $has_instock = false;
        $best_price_for_computed_specs = null;

        foreach (self::REGIONS as $region) {
            $region_data = $this->build_region_pricing_data_from_arrays($prices, $history, $region);
            if ($region_data !== null) {
                $price_history[$region] = $region_data;

                // Track if in stock in any region (for popularity boost).
                if (!empty($region_data['instock'])) {
                    $has_instock = true;
                }

                // Use first region with price for computed specs (US preferred).
                if ($best_price_for_computed_specs === null && !empty($region_data['current_price'])) {
                    $best_price_for_computed_specs = $region_data['current_price'];
                }
            }
        }

        $product_data['price_history'] = $price_history;

        // Popularity boost for in-stock items.
        if ($has_instock) {
            $product_data['popularity_score'] += 5;
        }

        // Popularity boost from tracker count.
        $tracker_count = $this->price_tracker->count_for_product($product_id);
        $product_data['popularity_score'] += $tracker_count * 2;

        // Popularity boost from views (logarithmic scale).
        $view_count = $this->view_tracker->get_view_count($product_id, 30);
        if ($view_count > 0) {
            $view_boost = log($view_count + 1) * 3.5;
            $product_data['popularity_score'] += round($view_boost, 1);
        }

        // Popularity boost for recent releases.
        $release_year = (int) get_field('release_year', $product_id);
        $current_year = (int) date('Y');

        if ($release_year === $current_year) {
            $product_data['popularity_score'] += 10;
        } elseif ($release_year === $current_year - 1) {
            $product_data['popularity_score'] += 5;
        }

        // Add computed comparisons to specs (using best price from any geo, prefer US).
        $product_data['specs'] = $this->add_computed_specs($product_data['specs'], $best_price_for_computed_specs, $product_type);

        // Calculate and add absolute category scores for comparison tool.
        $scores = $this->product_scorer->calculate_scores($product_data['specs'], $product_type);
        $product_data['specs']['scores'] = $scores;

        // Save to cache.
        $this->product_cache->upsert($product_data);
    }

    /**
     * Get normalized specs for a product.
     *
     * @param int $product_id The product ID.
     * @param string $product_type The product type.
     * @return array The normalized specs.
     */
    private function get_normalized_specs(int $product_id, string $product_type): array {
        $specs = get_fields($product_id) ?: [];

        // Clean acceleration field names (remove colons for e-scooters).
        $acceleration_fields_map = [
            'acceleration:_0-15_mph' => 'acceleration_0-15_mph',
            'acceleration:_0-20_mph' => 'acceleration_0-20_mph',
            'acceleration:_0-25_mph' => 'acceleration_0-25_mph',
            'acceleration:_0-30_mph' => 'acceleration_0-30_mph',
            'acceleration:_0-to-top' => 'acceleration_0-to-top',
        ];

        foreach ($acceleration_fields_map as $old_key => $new_key) {
            if (isset($specs[$old_key])) {
                $specs[$new_key] = $specs[$old_key];
                unset($specs[$old_key]);
            }
        }

        // Consolidate tire type for product types with wheels data.
        $specs = $this->consolidate_tire_type($specs, $product_type);

        return $specs;
    }

    /**
     * Consolidate tire_type and pneumatic_type into a single tire_type value.
     *
     * Maps to: Solid, Tubeless, Tubed, Mixed
     * - Solid tires → "Solid"
     * - Mixed (solid + pneumatic) → "Mixed"
     * - Pneumatic + Tubeless → "Tubeless"
     * - Pneumatic + Tubed (or default) → "Tubed"
     *
     * @param array  $specs        The product specs.
     * @param string $product_type The product type.
     * @return array The specs with consolidated tire_type.
     */
    private function consolidate_tire_type(array $specs, string $product_type): array {
        // Map product type to nested group key.
        $group_keys = [
            'Electric Scooter'    => 'e-scooters',
            'Electric Skateboard' => 'e-skateboards',
            'Electric Unicycle'   => 'e-unicycles',
            'Hoverboard'          => 'hoverboards',
        ];

        $group_key = $group_keys[$product_type] ?? null;
        if ($group_key === null) {
            return $specs;
        }

        // Check if wheels data exists.
        if (!isset($specs[$group_key]['wheels']) || !is_array($specs[$group_key]['wheels'])) {
            return $specs;
        }

        $wheels = &$specs[$group_key]['wheels'];
        $tire_type = $wheels['tire_type'] ?? '';
        $pneumatic_type = $wheels['pneumatic_type'] ?? '';

        if ($tire_type === '' && $pneumatic_type === '') {
            return $specs;
        }

        // Consolidate to single value.
        $tire_lower = strtolower(trim($tire_type));
        $pneumatic_lower = strtolower(trim($pneumatic_type));

        if ($tire_lower === 'solid') {
            $wheels['tire_type'] = 'Solid';
        } elseif ($tire_lower === 'mixed') {
            $wheels['tire_type'] = 'Mixed';
        } elseif ($pneumatic_lower === 'tubeless') {
            $wheels['tire_type'] = 'Tubeless';
        } else {
            // Default pneumatic to Tubed.
            $wheels['tire_type'] = 'Tubed';
        }

        // Remove pneumatic_type as it's now consolidated.
        unset($wheels['pneumatic_type']);

        return $specs;
    }

    /**
     * Get the product thumbnail image URL.
     *
     * @param int $product_id The product ID.
     * @return string The image URL or empty string.
     */
    private function get_product_image(int $product_id): string {
        $image_id = get_post_thumbnail_id($product_id);

        if (!$image_id) {
            return '';
        }

        // Try optimized size first (160px height for 80px display at 2x retina).
        // Falls back to standard sizes if erh-product-md not available.
        $image_url = wp_get_attachment_image_url($image_id, 'erh-product-md');

        if (!$image_url) {
            $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
        }

        if (!$image_url) {
            $image_url = wp_get_attachment_image_url($image_id, 'medium');
        }

        return $image_url ?: '';
    }

    /**
     * Build region-specific pricing data for a product.
     *
     * Returns pricing data structure for a single region with current price,
     * stock status, and period statistics (avg/low/high for 3m/6m/12m/all).
     *
     * For the EU region, this queries multiple EU country codes and picks the best price.
     *
     * @param int    $product_id The product ID.
     * @param string $region     The region code (e.g., 'US', 'GB', 'EU').
     * @return array|null Region pricing data or null if no genuine data for this region.
     */
    private function build_region_pricing_data(int $product_id, string $region): ?array {
        // Get best current price for this region.
        $best_price = $this->get_best_price_for_region($product_id, $region);

        // Get price history for this region.
        $history = $this->get_history_for_region($product_id, $region);

        // Need at least current price OR historical data to return anything.
        if ($best_price === null && empty($history)) {
            return null;
        }

        $region_data = [
            'current_price' => null,
            'currency'      => $this->get_currency_for_region($region),
            'instock'       => false,
            'retailer'      => null,
            'bestlink'      => null,
            // Averages
            'avg_3m'        => null,
            'avg_6m'        => null,
            'avg_12m'       => null,
            'avg_all'       => null,
            // Lows
            'low_3m'        => null,
            'low_6m'        => null,
            'low_12m'       => null,
            'low_all'       => null,
            // Highs
            'high_3m'       => null,
            'high_6m'       => null,
            'high_12m'      => null,
            'high_all'      => null,
            'updated_at'    => current_time('mysql'),
        ];

        // Populate current price data from best price.
        if ($best_price !== null) {
            $region_data['current_price'] = (float) $best_price['price'];
            $region_data['currency'] = $best_price['currency'] ?? $this->get_currency_for_region($region);
            $region_data['instock'] = !empty($best_price['in_stock']);
            $region_data['retailer'] = $best_price['retailer'] ?? null;
            $region_data['bestlink'] = $best_price['url'] ?? null;
        }

        // Calculate period statistics from historical data.
        if (!empty($history)) {
            $stats = $this->calculate_period_stats($history);

            // Merge all stats into region_data.
            $region_data['avg_3m']   = $stats['avg_3m'];
            $region_data['avg_6m']   = $stats['avg_6m'];
            $region_data['avg_12m']  = $stats['avg_12m'];
            $region_data['avg_all']  = $stats['avg_all'];
            $region_data['low_3m']   = $stats['low_3m'];
            $region_data['low_6m']   = $stats['low_6m'];
            $region_data['low_12m']  = $stats['low_12m'];
            $region_data['low_all']  = $stats['low_all'];
            $region_data['high_3m']  = $stats['high_3m'];
            $region_data['high_6m']  = $stats['high_6m'];
            $region_data['high_12m'] = $stats['high_12m'];
            $region_data['high_all'] = $stats['high_all'];
        }

        return $region_data;
    }

    /**
     * Build region-specific pricing data from pre-fetched arrays.
     *
     * This is the optimized version that works with bulk-fetched data
     * instead of making individual database queries.
     *
     * @param array  $prices  All current prices for this product.
     * @param array  $history All historical prices for this product.
     * @param string $region  The region code (e.g., 'US', 'GB', 'EU').
     * @return array|null Region pricing data or null if no genuine data for this region.
     */
    private function build_region_pricing_data_from_arrays(array $prices, array $history, string $region): ?array {
        // Get best current price for this region from pre-fetched array.
        $best_price = $this->get_best_price_for_region_from_array($prices, $region);

        // Get price history for this region from pre-fetched array.
        $region_history = $this->get_history_for_region_from_array($history, $region);

        // Need at least current price OR historical data to return anything.
        if ($best_price === null && empty($region_history)) {
            return null;
        }

        $region_data = [
            'current_price' => null,
            'currency'      => $this->get_currency_for_region($region),
            'instock'       => false,
            'retailer'      => null,
            'bestlink'      => null,
            // Averages
            'avg_3m'        => null,
            'avg_6m'        => null,
            'avg_12m'       => null,
            'avg_all'       => null,
            // Lows
            'low_3m'        => null,
            'low_6m'        => null,
            'low_12m'       => null,
            'low_all'       => null,
            // Highs
            'high_3m'       => null,
            'high_6m'       => null,
            'high_12m'      => null,
            'high_all'      => null,
            'updated_at'    => current_time('mysql'),
        ];

        // Populate current price data from best price.
        if ($best_price !== null) {
            $region_data['current_price'] = (float) $best_price['price'];
            $region_data['currency'] = $best_price['currency'] ?? $this->get_currency_for_region($region);
            $region_data['instock'] = !empty($best_price['in_stock']);
            $region_data['retailer'] = $best_price['retailer'] ?? null;
            $region_data['bestlink'] = $best_price['url'] ?? null;
        }

        // Calculate period statistics from historical data.
        if (!empty($region_history)) {
            $stats = $this->calculate_period_stats($region_history);

            // Merge all stats into region_data.
            $region_data['avg_3m']   = $stats['avg_3m'];
            $region_data['avg_6m']   = $stats['avg_6m'];
            $region_data['avg_12m']  = $stats['avg_12m'];
            $region_data['avg_all']  = $stats['avg_all'];
            $region_data['low_3m']   = $stats['low_3m'];
            $region_data['low_6m']   = $stats['low_6m'];
            $region_data['low_12m']  = $stats['low_12m'];
            $region_data['low_all']  = $stats['low_all'];
            $region_data['high_3m']  = $stats['high_3m'];
            $region_data['high_6m']  = $stats['high_6m'];
            $region_data['high_12m'] = $stats['high_12m'];
            $region_data['high_all'] = $stats['high_all'];
        }

        return $region_data;
    }

    /**
     * Get best price for a region from pre-fetched price array.
     *
     * Filters the prices by region/geo and returns the best one.
     *
     * @param array  $prices All prices for this product.
     * @param string $region The region code.
     * @return array|null Best price data or null.
     */
    private function get_best_price_for_region_from_array(array $prices, string $region): ?array {
        if (empty($prices)) {
            return null;
        }

        $best_price = null;
        $best_price_value = PHP_FLOAT_MAX;

        foreach ($prices as $price) {
            // Check if this price matches the region.
            if (!$this->price_matches_region($price, $region)) {
                continue;
            }

            // Verify it's a genuine price for this region.
            if (!$this->is_genuine_region_price($price, $region)) {
                continue;
            }

            // Prefer in-stock items, then lowest price.
            $is_in_stock = !empty($price['in_stock']);
            $price_value = (float) ($price['price'] ?? PHP_FLOAT_MAX);

            // If we don't have a price yet, take this one.
            if ($best_price === null) {
                $best_price = $price;
                $best_price_value = $price_value;
                continue;
            }

            // Prefer in-stock over out-of-stock.
            $best_is_in_stock = !empty($best_price['in_stock']);
            if ($is_in_stock && !$best_is_in_stock) {
                $best_price = $price;
                $best_price_value = $price_value;
                continue;
            }

            // If both same stock status, prefer lower price.
            if ($is_in_stock === $best_is_in_stock && $price_value < $best_price_value) {
                $best_price = $price;
                $best_price_value = $price_value;
            }
        }

        return $best_price;
    }

    /**
     * Check if a price entry matches a region.
     *
     * For EU region, accepts any EU country code.
     * For other regions, requires exact geo match or null geo.
     *
     * @param array  $price  The price data.
     * @param string $region The region code.
     * @return bool True if price matches region.
     */
    private function price_matches_region(array $price, string $region): bool {
        $price_geo = $price['geo'] ?? null;

        if ($region === 'EU') {
            // For EU, accept any EU country code, 'EU', or null with EUR currency.
            if (in_array($price_geo, self::EU_COUNTRIES, true)) {
                return true;
            }
            if ($price_geo === 'EU' || $price_geo === null || $price_geo === '') {
                return ($price['currency'] ?? '') === 'EUR';
            }
            return false;
        }

        // For non-EU regions, accept exact match or null geo.
        return $price_geo === $region || $price_geo === null || $price_geo === '';
    }

    /**
     * Get price history for a region from pre-fetched history array.
     *
     * Filters and aggregates history by region.
     *
     * @param array  $history All history for this product.
     * @param string $region  The region code.
     * @return array Filtered and sorted history records.
     */
    private function get_history_for_region_from_array(array $history, string $region): array {
        if (empty($history)) {
            return [];
        }

        $expected_currency = $this->get_currency_for_region($region);
        $region_history = [];

        foreach ($history as $entry) {
            $entry_geo = strtoupper($entry['geo'] ?? '');
            $entry_currency = strtoupper($entry['currency'] ?? '');

            // Filter by region and currency.
            if ($region === 'EU') {
                // For EU, accept any EU country code with EUR currency.
                if ($entry_currency !== 'EUR') {
                    continue;
                }
                if (!in_array($entry_geo, self::EU_COUNTRIES, true) && $entry_geo !== 'EU') {
                    continue;
                }
            } else {
                // For non-EU regions, require matching geo and currency.
                if ($entry_geo !== $region && $entry_geo !== '') {
                    continue;
                }
                if ($entry_currency !== $expected_currency) {
                    continue;
                }
            }

            $region_history[] = $entry;
        }

        // Sort by date descending.
        usort($region_history, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        // Deduplicate by date (keep first entry per date).
        $seen_dates = [];
        $deduped = [];
        foreach ($region_history as $entry) {
            $date = $entry['date'] ?? '';
            if (!isset($seen_dates[$date])) {
                $seen_dates[$date] = true;
                $deduped[] = $entry;
            }
        }

        return $deduped;
    }

    /**
     * Get best price for a region.
     *
     * For EU region, queries all EU country codes and returns the best (lowest in-stock) price.
     * For other regions, directly queries PriceFetcher.
     *
     * @deprecated Use get_best_price_for_region_from_array() with bulk data.
     * @param int    $product_id The product ID.
     * @param string $region     The region code.
     * @return array|null Best price data or null.
     */
    private function get_best_price_for_region(int $product_id, string $region): ?array {
        if ($region === 'EU') {
            // For EU, try multiple country codes and find the best price
            $best_price = null;
            $best_price_value = PHP_FLOAT_MAX;

            foreach (self::EU_COUNTRIES as $country) {
                $price = $this->price_fetcher->get_best_price($product_id, $country);

                if ($price === null) {
                    continue;
                }

                // Verify it's a genuine EU price (EUR currency)
                if (!$this->is_genuine_region_price($price, $region)) {
                    continue;
                }

                // Prefer in-stock items, then lowest price
                $is_in_stock = !empty($price['in_stock']);
                $price_value = (float) ($price['price'] ?? PHP_FLOAT_MAX);

                // If we don't have a price yet, take this one
                if ($best_price === null) {
                    $best_price = $price;
                    $best_price_value = $price_value;
                    continue;
                }

                // Prefer in-stock over out-of-stock
                $best_is_in_stock = !empty($best_price['in_stock']);
                if ($is_in_stock && !$best_is_in_stock) {
                    $best_price = $price;
                    $best_price_value = $price_value;
                    continue;
                }

                // If both same stock status, prefer lower price
                if ($is_in_stock === $best_is_in_stock && $price_value < $best_price_value) {
                    $best_price = $price;
                    $best_price_value = $price_value;
                }
            }

            return $best_price;
        }

        // For non-EU regions, query directly
        $price = $this->price_fetcher->get_best_price($product_id, $region);

        // Verify it's genuine for this region
        if ($price !== null && !$this->is_genuine_region_price($price, $region)) {
            return null;
        }

        return $price;
    }

    /**
     * Get price history for a region.
     *
     * For EU region, aggregates history from all EU country codes.
     *
     * @param int    $product_id The product ID.
     * @param string $region     The region code.
     * @return array Price history records.
     */
    private function get_history_for_region(int $product_id, string $region): array {
        if ($region === 'EU') {
            // For EU, aggregate history from all EU countries
            $all_history = [];

            foreach (self::EU_COUNTRIES as $country) {
                $history = $this->price_history->get_history($product_id, 0, $country, 'EUR', 'DESC');
                if (!empty($history)) {
                    $all_history = array_merge($all_history, $history);
                }
            }

            // Also try with 'EU' as geo directly
            $eu_history = $this->price_history->get_history($product_id, 0, 'EU', 'EUR', 'DESC');
            if (!empty($eu_history)) {
                $all_history = array_merge($all_history, $eu_history);
            }

            // Sort by date descending and remove duplicates by date
            usort($all_history, function ($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            // Deduplicate by date (keep first/lowest price per date)
            $seen_dates = [];
            $deduped = [];
            foreach ($all_history as $entry) {
                $date = $entry['date'] ?? '';
                if (!isset($seen_dates[$date])) {
                    $seen_dates[$date] = true;
                    $deduped[] = $entry;
                }
            }

            return $deduped;
        }

        // For non-EU regions, query directly
        $currency = $this->get_currency_for_region($region);
        return $this->price_history->get_history($product_id, 0, $region, $currency, 'DESC');
    }

    /**
     * Calculate period statistics from price history.
     *
     * Returns averages, lows, and highs for 3m, 6m, 12m, and all-time periods.
     *
     * @param array $history Price history records.
     * @return array Calculated statistics.
     */
    private function calculate_period_stats(array $history): array {
        $now = new \DateTime();

        $prices_all = [];
        $prices_12m = [];
        $prices_6m = [];
        $prices_3m = [];

        foreach ($history as $entry) {
            if (!is_numeric($entry['price'])) {
                continue;
            }

            $price = (float) $entry['price'];
            $prices_all[] = $price;

            $entry_date = new \DateTime($entry['date']);
            $diff_days = $now->diff($entry_date)->days;

            if ($diff_days <= 365) {
                $prices_12m[] = $price;
                if ($diff_days <= 180) {
                    $prices_6m[] = $price;
                    if ($diff_days <= 90) {
                        $prices_3m[] = $price;
                    }
                }
            }
        }

        return [
            // Averages
            'avg_3m'   => !empty($prices_3m) ? round(array_sum($prices_3m) / count($prices_3m), 2) : null,
            'avg_6m'   => !empty($prices_6m) ? round(array_sum($prices_6m) / count($prices_6m), 2) : null,
            'avg_12m'  => !empty($prices_12m) ? round(array_sum($prices_12m) / count($prices_12m), 2) : null,
            'avg_all'  => !empty($prices_all) ? round(array_sum($prices_all) / count($prices_all), 2) : null,
            // Lows
            'low_3m'   => !empty($prices_3m) ? round(min($prices_3m), 2) : null,
            'low_6m'   => !empty($prices_6m) ? round(min($prices_6m), 2) : null,
            'low_12m'  => !empty($prices_12m) ? round(min($prices_12m), 2) : null,
            'low_all'  => !empty($prices_all) ? round(min($prices_all), 2) : null,
            // Highs
            'high_3m'  => !empty($prices_3m) ? round(max($prices_3m), 2) : null,
            'high_6m'  => !empty($prices_6m) ? round(max($prices_6m), 2) : null,
            'high_12m' => !empty($prices_12m) ? round(max($prices_12m), 2) : null,
            'high_all' => !empty($prices_all) ? round(max($prices_all), 2) : null,
        ];
    }

    /**
     * Get the default currency for a region.
     *
     * @param string $region The region code.
     * @return string The currency code.
     */
    private function get_currency_for_region(string $region): string {
        $region_currencies = [
            'US' => 'USD',
            'GB' => 'GBP',
            'EU' => 'EUR',
            'CA' => 'CAD',
            'AU' => 'AUD',
        ];

        return $region_currencies[$region] ?? 'USD';
    }

    /**
     * Check if a price is genuinely for the requested region.
     *
     * This prevents US fallback prices from being stored under non-US regions.
     *
     * @param array  $price  The price data from PriceFetcher.
     * @param string $region The requested region code.
     * @return bool True if this price is genuine for the region.
     */
    private function is_genuine_region_price(array $price, string $region): bool {
        $price_geo = $price['geo'] ?? null;
        $price_currency = $price['currency'] ?? 'USD';

        // For US region
        if ($region === 'US') {
            // Accept if geo is US or null (global prices default to US)
            if ($price_geo === 'US' || empty($price_geo)) {
                return $price_currency === 'USD';
            }
            return false;
        }

        // For EU region - accept any EU country with EUR currency
        if ($region === 'EU') {
            // Must be EUR currency
            if ($price_currency !== 'EUR') {
                return false;
            }
            // Accept if geo is an EU country, 'EU', or null with EUR
            if (in_array($price_geo, self::EU_COUNTRIES, true)) {
                return true;
            }
            if ($price_geo === 'EU' || (empty($price_geo) && $price_currency === 'EUR')) {
                return true;
            }
            return false;
        }

        // For GB, CA, AU - must match geo and currency
        $expected_currency = $this->get_currency_for_region($region);

        // Exact geo match
        if ($price_geo === $region && $price_currency === $expected_currency) {
            return true;
        }

        // Global price with matching currency
        if (empty($price_geo) && $price_currency === $expected_currency) {
            return true;
        }

        return false;
    }

    /**
     * Add computed price/spec comparisons to specs.
     *
     * Calculates value metrics like price_per_lb, speed_per_lb, range_per_lb.
     * Price-based metrics require a valid price, but weight-based metrics
     * (speed_per_lb, range_per_lb) are calculated regardless of price availability.
     *
     * @param array $specs The product specs.
     * @param float|null $price The product price (null if unavailable).
     * @param string $product_type The product type.
     * @return array The specs with computed values.
     */
    private function add_computed_specs(array $specs, ?float $price, string $product_type): array {
        // Normalize price to null if invalid.
        if ($price !== null && $price <= 0) {
            $price = null;
        }

        if ($product_type === 'Electric Bike' && isset($specs['e-bikes'])) {
            return $this->add_ebike_computed_specs($specs, $price);
        }

        return $this->add_scooter_computed_specs($specs, $price);
    }

    /**
     * Add computed specs for e-bikes.
     *
     * @param array $specs The product specs.
     * @param float|null $price The product price (null if unavailable).
     * @return array The specs with computed values.
     */
    private function add_ebike_computed_specs(array $specs, ?float $price): array {
        $ebike_data = $specs['e-bikes'];
        $has_price = $price !== null;

        // Extract common values.
        $weight = null;
        if (isset($ebike_data['weight_and_capacity']['weight']) && is_numeric($ebike_data['weight_and_capacity']['weight'])) {
            $weight = (float) $ebike_data['weight_and_capacity']['weight'];
        }

        $range = null;
        if (isset($ebike_data['battery']['range']) && is_numeric($ebike_data['battery']['range'])) {
            $range = (float) $ebike_data['battery']['range'];
        }

        $capacity = null;
        if (isset($ebike_data['battery']['battery_capacity']) && is_numeric($ebike_data['battery']['battery_capacity'])) {
            $capacity = (float) $ebike_data['battery']['battery_capacity'];
        }

        $power_nominal = null;
        if (isset($ebike_data['motor']['power_nominal']) && is_numeric($ebike_data['motor']['power_nominal'])) {
            $power_nominal = (float) $ebike_data['motor']['power_nominal'];
        }

        // === Price-based metrics (require price) ===
        if ($has_price) {
            // Price vs Motor Power (Nominal).
            if ($power_nominal !== null && $power_nominal > 0) {
                $specs['price_per_watt_nominal'] = round($price / $power_nominal, 2);
            }

            // Price vs Motor Power (Peak).
            if (isset($ebike_data['motor']['power_peak']) && is_numeric($ebike_data['motor']['power_peak'])) {
                $power = (float) $ebike_data['motor']['power_peak'];
                if ($power > 0) {
                    $specs['price_per_watt_peak'] = round($price / $power, 2);
                }
            }

            // Price vs Torque.
            if (isset($ebike_data['motor']['torque']) && is_numeric($ebike_data['motor']['torque'])) {
                $torque = (float) $ebike_data['motor']['torque'];
                if ($torque > 0) {
                    $specs['price_per_nm_torque'] = round($price / $torque, 2);
                }
            }

            // Price vs Battery Capacity.
            if ($capacity !== null && $capacity > 0) {
                $specs['price_per_wh_battery'] = round($price / $capacity, 2);
            }

            // Price vs Range.
            if ($range !== null && $range > 0) {
                $specs['price_per_mile_range'] = round($price / $range, 2);
            }

            // Price vs Weight.
            if ($weight !== null && $weight > 0) {
                $specs['price_per_lb'] = round($price / $weight, 2);
            }

            // Price vs Weight Limit.
            if (isset($ebike_data['weight_and_capacity']['weight_limit']) && is_numeric($ebike_data['weight_and_capacity']['weight_limit'])) {
                $limit = (float) $ebike_data['weight_and_capacity']['weight_limit'];
                if ($limit > 0) {
                    $specs['price_per_lb_capacity'] = round($price / $limit, 2);
                }
            }

            // Price vs Top Assist Speed.
            if (isset($ebike_data['speed_and_class']['top_assist_speed']) && is_numeric($ebike_data['speed_and_class']['top_assist_speed'])) {
                $speed = (float) $ebike_data['speed_and_class']['top_assist_speed'];
                if ($speed > 0) {
                    $specs['price_per_mph_assist'] = round($price / $speed, 2);
                }
            }
        }

        // === Non-price metrics (always calculated if data available) ===
        if ($weight !== null && $weight > 0) {
            // Range per pound.
            if ($range !== null) {
                $specs['range_per_lb'] = round($range / $weight, 2);
            }

            // Battery capacity per pound.
            if ($capacity !== null) {
                $specs['wh_per_lb'] = round($capacity / $weight, 2);
            }

            // Power per pound.
            if ($power_nominal !== null) {
                $specs['watts_per_lb'] = round($power_nominal / $weight, 2);
            }
        }

        return $specs;
    }

    /**
     * Add computed specs for e-scooters and other types.
     *
     * Handles both flat (legacy) and nested (new ACF structure) field locations.
     * Price-based metrics require a valid price, but weight-based metrics
     * (speed_per_lb, range_per_lb) are calculated regardless of price availability.
     *
     * @param array $specs The product specs.
     * @param float|null $price The product price (null if unavailable).
     * @return array The specs with computed values.
     */
    private function add_scooter_computed_specs(array $specs, ?float $price): array {
        $has_price = $price !== null;

        // Helper to get value from flat or nested e-scooters structure.
        $get_value = function (string ...$paths) use ($specs): ?float {
            foreach ($paths as $path) {
                // Check flat key first.
                if (isset($specs[$path]) && is_numeric($specs[$path])) {
                    return (float) $specs[$path];
                }

                // Check nested path (e.g., 'e-scooters.dimensions.weight').
                $parts = explode('.', $path);
                $value = $specs;
                foreach ($parts as $part) {
                    if (!is_array($value) || !isset($value[$part])) {
                        $value = null;
                        break;
                    }
                    $value = $value[$part];
                }
                if ($value !== null && is_numeric($value)) {
                    return (float) $value;
                }
            }
            return null;
        };

        // Get values from various possible locations.
        $weight = $get_value('weight', 'e-scooters.dimensions.weight');
        $top_speed = $get_value('manufacturer_top_speed');
        $range = $get_value('manufacturer_range');
        $tested_range = $get_value('tested_range_regular');
        $tested_speed = $get_value('tested_top_speed');
        $battery = $get_value('battery_capacity', 'e-scooters.battery.capacity');
        $motor_power = $get_value('nominal_motor_wattage', 'e-scooters.motor.power_nominal');
        $max_load = $get_value('max_load', 'e-scooters.dimensions.max_load');
        $brake_distance = $get_value('brake_distance');
        $hill_climbing = $get_value('hill_climbing');
        $max_weight_capacity = $get_value('max_weight_capacity');

        // === Non-price metrics (always calculated if data available) ===
        if ($weight !== null && $weight > 0) {
            // Speed per lb.
            if ($top_speed !== null) {
                $specs['speed_per_lb'] = round($top_speed / $weight, 2);
            }
            // Range per lb.
            if ($range !== null) {
                $specs['range_per_lb'] = round($range / $weight, 2);
            }
            // Tested range per lb.
            if ($tested_range !== null) {
                $specs['tested_range_per_lb'] = round($tested_range / $weight, 2);
            }
        }

        // Max weight capacity comparisons (non-price based).
        if ($max_weight_capacity !== null && $max_weight_capacity > 0) {
            if ($top_speed !== null) {
                $specs['speed_per_lb_capacity'] = round($top_speed / $max_weight_capacity, 2);
            }
            if ($range !== null) {
                $specs['range_per_lb_capacity'] = round($range / $max_weight_capacity, 2);
            }
        }

        // === Price-based metrics (require price) ===
        if (!$has_price) {
            return $specs;
        }

        // Price vs Weight.
        if ($weight !== null && $weight > 0) {
            $specs['price_per_lb'] = round($price / $weight, 2);
        }

        // Price vs Speed.
        if ($top_speed !== null && $top_speed > 0) {
            $specs['price_per_mph'] = round($price / $top_speed, 2);
        }

        // Price vs Range.
        if ($range !== null && $range > 0) {
            $specs['price_per_mile_range'] = round($price / $range, 2);
        }

        // Price vs Battery.
        if ($battery !== null && $battery > 0) {
            $specs['price_per_wh'] = round($price / $battery, 2);
        }

        // Price vs Motor Power.
        if ($motor_power !== null && $motor_power > 0) {
            $specs['price_per_watt'] = round($price / $motor_power, 2);
        }

        // Price vs Payload Capacity.
        if ($max_load !== null && $max_load > 0) {
            $specs['price_per_lb_capacity'] = round($price / $max_load, 2);
        }

        // Price vs Tested Range.
        if ($tested_range !== null && $tested_range > 0) {
            $specs['price_per_tested_mile'] = round($price / $tested_range, 2);
        }

        // Price vs Tested Top Speed.
        if ($tested_speed !== null && $tested_speed > 0) {
            $specs['price_per_tested_mph'] = round($price / $tested_speed, 2);
        }

        // Price vs Brake Distance.
        if ($brake_distance !== null && $brake_distance > 0) {
            $specs['price_per_brake_ft'] = round($price / $brake_distance, 2);
        }

        // Price vs Hill Climbing.
        if ($hill_climbing !== null && $hill_climbing > 0) {
            $specs['price_per_hill_degree'] = round($price / $hill_climbing, 2);
        }

        // Price vs Acceleration.
        $acceleration_speeds = ['0-15', '0-20', '0-25', '0-30'];
        foreach ($acceleration_speeds as $speed) {
            $accel = $get_value("acceleration_{$speed}_mph");
            if ($accel !== null && $accel > 0) {
                $specs["price_per_acc_{$speed}_mph"] = round($price / $accel, 2);
            }
        }

        // Acceleration 0-to-top.
        $accel_top = $get_value('acceleration_0-to-top');
        if ($accel_top !== null && $accel_top > 0) {
            $specs['price_per_acc_0-to-top'] = round($price / $accel_top, 2);
        }

        return $specs;
    }

    /**
     * Touch finder page post_modified dates for cache invalidation.
     *
     * @return void
     */
    private function touch_finder_pages(): void {
        // These are the finder tool page IDs that need cache invalidation.
        $pages_to_update = [14781, 14699, 14641, 17310, 17249];

        foreach ($pages_to_update as $post_id) {
            if (get_post($post_id)) {
                wp_update_post([
                    'ID'                => $post_id,
                    'post_modified'     => current_time('mysql'),
                    'post_modified_gmt' => current_time('mysql', 1),
                ]);
            }
        }
    }
}
