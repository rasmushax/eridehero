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
use ERH\Reviews\ReviewQuery;

/**
 * Rebuilds the product_data cache table with fresh data.
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
     * Review query instance.
     *
     * @var ReviewQuery
     */
    private ReviewQuery $review_query;

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
     * @param ProductCache $product_cache Product cache instance.
     * @param PriceHistory $price_history Price history instance.
     * @param PriceTracker $price_tracker Price tracker instance.
     * @param ViewTracker $view_tracker View tracker instance.
     * @param ReviewQuery $review_query Review query instance.
     * @param CronManager $cron_manager Cron manager instance.
     */
    public function __construct(
        PriceFetcher $price_fetcher,
        ProductCache $product_cache,
        PriceHistory $price_history,
        PriceTracker $price_tracker,
        ViewTracker $view_tracker,
        ReviewQuery $review_query,
        CronManager $cron_manager
    ) {
        $this->price_fetcher = $price_fetcher;
        $this->product_cache = $product_cache;
        $this->price_history = $price_history;
        $this->price_tracker = $price_tracker;
        $this->view_tracker = $view_tracker;
        $this->review_query = $review_query;
        $this->cron_manager = $cron_manager;
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
     * @return void
     */
    private function run(): void {
        $start_time = microtime(true);
        $processed_count = 0;

        foreach (self::PRODUCT_TYPES as $product_type) {
            $products = $this->get_products_by_type($product_type);

            foreach ($products as $product_id) {
                $this->rebuild_product($product_id, $product_type);
                $processed_count++;
            }
        }

        $elapsed = round(microtime(true) - $start_time, 2);

        error_log(sprintf(
            '[ERH Cron] Cache rebuild completed. Processed: %d products in %ss',
            $processed_count,
            $elapsed
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
     * @param int $product_id The product post ID.
     * @param string $product_type The product type.
     * @return void
     */
    private function rebuild_product(int $product_id, string $product_type): void {
        $product_data = [
            'product_id'       => $product_id,
            'product_type'     => $product_type,
            'name'             => get_the_title($product_id),
            'specs'            => $this->get_normalized_specs($product_id, $product_type),
            'price'            => null,
            'instock'          => 0,
            'permalink'        => get_permalink($product_id),
            'image_url'        => $this->get_product_image($product_id),
            'rating'           => null,
            'popularity_score' => 0,
            'bestlink'         => '',
            'price_history'    => [],
        ];

        // Get pricing data.
        $prices = $this->price_fetcher->get_prices($product_id);
        if (!empty($prices)) {
            $best_price = $prices[0];
            $product_data['price'] = isset($best_price['price']) && is_numeric($best_price['price']) && $best_price['price'] > 0
                ? (float) $best_price['price']
                : null;
            $product_data['instock'] = isset($best_price['stock_status']) && $best_price['stock_status'] == 1 ? 1 : 0;
            $product_data['bestlink'] = $best_price['url'] ?? '';
        }

        // Popularity boost for in-stock items.
        if ($product_data['instock']) {
            $product_data['popularity_score'] += 5;
        }

        // Get price history statistics.
        if ($product_data['price'] !== null) {
            $product_data['price_history'] = $this->calculate_price_history_stats($product_id, $product_data['price']);
        }

        // Get review rating and count.
        $reviews = $this->review_query->get_product_reviews($product_id);
        $ratings = $reviews['ratings_distribution'];

        if ($ratings['ratings_count'] > 0) {
            $avg_rating = (float) $ratings['average_rating'];
            $product_data['rating'] = $avg_rating > 0 ? $avg_rating : null;

            // Popularity boost from ratings and review count.
            $product_data['popularity_score'] += $avg_rating;
            $product_data['popularity_score'] += $ratings['ratings_count'] * 2;
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

        // Add computed comparisons to specs.
        $product_data['specs'] = $this->add_computed_specs($product_data['specs'], $product_data['price'], $product_type);

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

        // Try custom size first, then standard thumbnail.
        $image_url = wp_get_attachment_image_url($image_id, 'thumbnail-150');

        if (!$image_url) {
            $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
        }

        if (!$image_url) {
            $image_url = wp_get_attachment_image_url($image_id, 'medium');
        }

        return $image_url ?: '';
    }

    /**
     * Calculate price history statistics for a product.
     *
     * @param int $product_id The product ID.
     * @param float $current_price The current price.
     * @return array Price history statistics.
     */
    private function calculate_price_history_stats(int $product_id, float $current_price): array {
        $history = $this->price_history->get_history($product_id, 0, 'DESC');

        if (empty($history)) {
            return [];
        }

        $now = new \DateTime();

        // Categorize prices by time period.
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

        if (empty($prices_all)) {
            return [];
        }

        // Calculate all-time statistics.
        $mean = round(array_sum($prices_all) / count($prices_all), 2);
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $prices_all)) / count($prices_all);
        $std_dev = round(sqrt($variance), 2);

        $stats = [
            'average_price'  => $mean,
            'lowest_price'   => (string) round(min($prices_all), 2),
            'highest_price'  => (string) round(max($prices_all), 2),
            'std_dev'        => $std_dev,
        ];

        // Add time-period averages.
        if (!empty($prices_12m)) {
            $stats['average_price_12m'] = round(array_sum($prices_12m) / count($prices_12m), 2);
        }

        if (!empty($prices_6m)) {
            $stats['average_price_6m'] = round(array_sum($prices_6m) / count($prices_6m), 2);
        }

        if (!empty($prices_3m)) {
            $stats['average_price_3m'] = round(array_sum($prices_3m) / count($prices_3m), 2);
        }

        // Calculate Z-score.
        if ($std_dev > 0) {
            $stats['z_score'] = round(($current_price - $mean) / $std_dev, 2);
        } else {
            $stats['z_score'] = 0;
        }

        // Calculate price difference from average.
        $stats['price_difference'] = round($mean - $current_price, 2);
        $stats['price_difference_percentage'] = round((($current_price - $mean) / $mean) * 100, 2);

        return $stats;
    }

    /**
     * Add computed price/spec comparisons to specs.
     *
     * @param array $specs The product specs.
     * @param float|null $price The product price.
     * @param string $product_type The product type.
     * @return array The specs with computed values.
     */
    private function add_computed_specs(array $specs, ?float $price, string $product_type): array {
        if ($price === null || $price <= 0) {
            return $specs;
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
     * @param float $price The product price.
     * @return array The specs with computed values.
     */
    private function add_ebike_computed_specs(array $specs, float $price): array {
        $ebike_data = $specs['e-bikes'];

        // Price vs Motor Power (Nominal).
        if (isset($ebike_data['motor']['power_nominal']) && is_numeric($ebike_data['motor']['power_nominal'])) {
            $power = (float) $ebike_data['motor']['power_nominal'];
            $specs['price_per_watt_nominal'] = $power > 0 ? round($price / $power, 2) : null;
        }

        // Price vs Motor Power (Peak).
        if (isset($ebike_data['motor']['power_peak']) && is_numeric($ebike_data['motor']['power_peak'])) {
            $power = (float) $ebike_data['motor']['power_peak'];
            $specs['price_per_watt_peak'] = $power > 0 ? round($price / $power, 2) : null;
        }

        // Price vs Torque.
        if (isset($ebike_data['motor']['torque']) && is_numeric($ebike_data['motor']['torque'])) {
            $torque = (float) $ebike_data['motor']['torque'];
            $specs['price_per_nm_torque'] = $torque > 0 ? round($price / $torque, 2) : null;
        }

        // Price vs Battery Capacity.
        if (isset($ebike_data['battery']['battery_capacity']) && is_numeric($ebike_data['battery']['battery_capacity'])) {
            $capacity = (float) $ebike_data['battery']['battery_capacity'];
            $specs['price_per_wh_battery'] = $capacity > 0 ? round($price / $capacity, 2) : null;
        }

        // Price vs Range.
        if (isset($ebike_data['battery']['range']) && is_numeric($ebike_data['battery']['range'])) {
            $range = (float) $ebike_data['battery']['range'];
            $specs['price_per_mile_range'] = $range > 0 ? round($price / $range, 2) : null;
        }

        // Price vs Weight.
        if (isset($ebike_data['weight_and_capacity']['weight']) && is_numeric($ebike_data['weight_and_capacity']['weight'])) {
            $weight = (float) $ebike_data['weight_and_capacity']['weight'];
            $specs['price_per_lb'] = $weight > 0 ? round($price / $weight, 2) : null;

            // Range per pound.
            if (isset($ebike_data['battery']['range']) && is_numeric($ebike_data['battery']['range'])) {
                $range = (float) $ebike_data['battery']['range'];
                $specs['range_per_lb'] = $weight > 0 ? round($range / $weight, 2) : null;
            }

            // Battery capacity per pound.
            if (isset($ebike_data['battery']['battery_capacity']) && is_numeric($ebike_data['battery']['battery_capacity'])) {
                $capacity = (float) $ebike_data['battery']['battery_capacity'];
                $specs['wh_per_lb'] = $weight > 0 ? round($capacity / $weight, 2) : null;
            }

            // Power per pound.
            if (isset($ebike_data['motor']['power_nominal']) && is_numeric($ebike_data['motor']['power_nominal'])) {
                $power = (float) $ebike_data['motor']['power_nominal'];
                $specs['watts_per_lb'] = $weight > 0 ? round($power / $weight, 2) : null;
            }
        }

        // Price vs Weight Limit.
        if (isset($ebike_data['weight_and_capacity']['weight_limit']) && is_numeric($ebike_data['weight_and_capacity']['weight_limit'])) {
            $limit = (float) $ebike_data['weight_and_capacity']['weight_limit'];
            $specs['price_per_lb_capacity'] = $limit > 0 ? round($price / $limit, 2) : null;
        }

        // Price vs Top Assist Speed.
        if (isset($ebike_data['speed_and_class']['top_assist_speed']) && is_numeric($ebike_data['speed_and_class']['top_assist_speed'])) {
            $speed = (float) $ebike_data['speed_and_class']['top_assist_speed'];
            $specs['price_per_mph_assist'] = $speed > 0 ? round($price / $speed, 2) : null;
        }

        return $specs;
    }

    /**
     * Add computed specs for e-scooters and other types.
     *
     * @param array $specs The product specs.
     * @param float $price The product price.
     * @return array The specs with computed values.
     */
    private function add_scooter_computed_specs(array $specs, float $price): array {
        // Price vs Weight.
        if (isset($specs['weight']) && is_numeric($specs['weight'])) {
            $weight = (float) $specs['weight'];
            $specs['price_per_lb'] = $weight > 0 ? round($price / $weight, 2) : null;

            // Weight-based comparisons.
            if (isset($specs['manufacturer_top_speed']) && is_numeric($specs['manufacturer_top_speed'])) {
                $speed = (float) $specs['manufacturer_top_speed'];
                $specs['speed_per_lb'] = $weight > 0 ? round($speed / $weight, 2) : null;
            }

            if (isset($specs['manufacturer_range']) && is_numeric($specs['manufacturer_range'])) {
                $range = (float) $specs['manufacturer_range'];
                $specs['range_per_lb'] = $weight > 0 ? round($range / $weight, 2) : null;
            }

            if (isset($specs['tested_range_regular']) && is_numeric($specs['tested_range_regular'])) {
                $range = (float) $specs['tested_range_regular'];
                $specs['tested_range_per_lb'] = $weight > 0 ? round($range / $weight, 2) : null;
            }
        }

        // Price vs Speed.
        if (isset($specs['manufacturer_top_speed']) && is_numeric($specs['manufacturer_top_speed'])) {
            $speed = (float) $specs['manufacturer_top_speed'];
            $specs['price_per_mph'] = $speed > 0 ? round($price / $speed, 2) : null;
        }

        // Price vs Range.
        if (isset($specs['manufacturer_range']) && is_numeric($specs['manufacturer_range'])) {
            $range = (float) $specs['manufacturer_range'];
            $specs['price_per_mile_range'] = $range > 0 ? round($price / $range, 2) : null;
        }

        // Price vs Battery.
        if (isset($specs['battery_capacity']) && is_numeric($specs['battery_capacity'])) {
            $capacity = (float) $specs['battery_capacity'];
            $specs['price_per_wh'] = $capacity > 0 ? round($price / $capacity, 2) : null;
        }

        // Price vs Motor Power.
        if (isset($specs['nominal_motor_wattage']) && is_numeric($specs['nominal_motor_wattage'])) {
            $power = (float) $specs['nominal_motor_wattage'];
            $specs['price_per_watt'] = $power > 0 ? round($price / $power, 2) : null;
        }

        // Price vs Payload Capacity.
        if (isset($specs['max_load']) && is_numeric($specs['max_load'])) {
            $max_load = (float) $specs['max_load'];
            $specs['price_per_lb_capacity'] = $max_load > 0 ? round($price / $max_load, 2) : null;
        }

        // Price vs Tested Range.
        if (isset($specs['tested_range_regular']) && is_numeric($specs['tested_range_regular'])) {
            $range = (float) $specs['tested_range_regular'];
            $specs['price_per_tested_mile'] = $range > 0 ? round($price / $range, 2) : null;
        }

        // Price vs Tested Top Speed.
        if (isset($specs['tested_top_speed']) && is_numeric($specs['tested_top_speed'])) {
            $speed = (float) $specs['tested_top_speed'];
            $specs['price_per_tested_mph'] = $speed > 0 ? round($price / $speed, 2) : null;
        }

        // Price vs Brake Distance.
        if (isset($specs['brake_distance']) && is_numeric($specs['brake_distance'])) {
            $brake = (float) $specs['brake_distance'];
            $specs['price_per_brake_ft'] = $brake > 0 ? round($price / $brake, 2) : null;
        }

        // Price vs Hill Climbing.
        if (isset($specs['hill_climbing']) && is_numeric($specs['hill_climbing'])) {
            $hill = (float) $specs['hill_climbing'];
            $specs['price_per_hill_degree'] = $hill > 0 ? round($price / $hill, 2) : null;
        }

        // Price vs Acceleration.
        $acceleration_speeds = ['0-15', '0-20', '0-25', '0-30'];
        foreach ($acceleration_speeds as $speed) {
            $field_key = "acceleration_{$speed}_mph";
            if (isset($specs[$field_key]) && is_numeric($specs[$field_key])) {
                $accel = (float) $specs[$field_key];
                $specs["price_per_acc_{$speed}_mph"] = $accel > 0 ? round($price / $accel, 2) : null;
            }
        }

        // Acceleration 0-to-top.
        if (isset($specs['acceleration_0-to-top']) && is_numeric($specs['acceleration_0-to-top'])) {
            $accel = (float) $specs['acceleration_0-to-top'];
            $specs['price_per_acc_0-to-top'] = $accel > 0 ? round($price / $accel, 2) : null;
        }

        // Max weight capacity comparisons.
        if (isset($specs['max_weight_capacity']) && is_numeric($specs['max_weight_capacity'])) {
            $capacity = (float) $specs['max_weight_capacity'];

            if (isset($specs['manufacturer_top_speed']) && is_numeric($specs['manufacturer_top_speed'])) {
                $speed = (float) $specs['manufacturer_top_speed'];
                $specs['speed_per_lb_capacity'] = $capacity > 0 ? round($speed / $capacity, 2) : null;
            }

            if (isset($specs['manufacturer_range']) && is_numeric($specs['manufacturer_range'])) {
                $range = (float) $specs['manufacturer_range'];
                $specs['range_per_lb_capacity'] = $capacity > 0 ? round($range / $capacity, 2) : null;
            }
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
