<?php
/**
 * Deals Finder - Find products priced below their historical average.
 *
 * @package ERH\Pricing
 */

declare(strict_types=1);

namespace ERH\Pricing;

use ERH\Database\ProductCache;

/**
 * Finds products that are currently priced below their historical average.
 *
 * Price data is now geo-aware. The price_history field in wp_product_data
 * contains per-geo pricing data pre-computed by cron:
 *
 * [
 *     'US' => [
 *         'current_price' => 499.99,
 *         'currency' => 'USD',
 *         'avg_price_3m' => 549.99,
 *         'avg_price_6m' => 599.99,
 *         'avg_price_12m' => 579.99,
 *         'lowest_price' => 449.99,
 *         'highest_price' => 699.99,
 *         'instock' => true,
 *         'retailer' => 'Amazon',
 *         'bestlink' => 'https://...',
 *         'updated_at' => '2025-12-17 10:00:00',
 *     ],
 *     'GB' => [...],
 * ]
 */
class DealsFinder {

    /**
     * Default geo when none specified.
     *
     * @var string
     */
    public const DEFAULT_GEO = 'US';

    /**
     * Available average periods.
     *
     * @var array<string>
     */
    public const PERIODS = ['3m', '6m', '12m'];

    /**
     * Default average period for deal comparison.
     *
     * @var string
     */
    public const DEFAULT_PERIOD = '6m';

    /**
     * Product cache instance.
     *
     * @var ProductCache
     */
    private ProductCache $product_cache;

    /**
     * Constructor.
     *
     * @param ProductCache|null $product_cache Optional ProductCache instance.
     */
    public function __construct(?ProductCache $product_cache = null) {
        $this->product_cache = $product_cache ?? new ProductCache();
    }

    /**
     * Get deals for a product type (or all types).
     *
     * @param string|null $product_type              The product type (e.g., 'Electric Scooter'), or null for all.
     * @param float       $price_difference_threshold Minimum percentage below average (negative value, e.g., -5 for 5% below).
     * @param int         $limit                     Maximum number of deals to return.
     * @param string      $geo                       Geo code (e.g., 'US', 'GB').
     * @param string      $period                    Average period to compare against ('3m', '6m', '12m').
     * @return array<int, array<string, mixed>> Array of deal products with price analysis.
     */
    public function get_deals(
        ?string $product_type = null,
        float $price_difference_threshold = -5.0,
        int $limit = 50,
        string $geo = self::DEFAULT_GEO,
        string $period = self::DEFAULT_PERIOD
    ): array {
        // Validate period.
        if (!in_array($period, self::PERIODS, true)) {
            $period = self::DEFAULT_PERIOD;
        }

        $avg_key = 'avg_price_' . $period;

        // Build filters.
        $filters = [];
        if ($product_type !== null) {
            $filters['product_type'] = $product_type;
        }

        // Get products.
        $products = $this->product_cache->get_filtered(
            $filters,
            'popularity_score',
            'DESC',
            500, // Get more than needed, we'll filter by geo.
            0
        );

        $deals = [];

        foreach ($products as $product) {
            // Skip products without price_history.
            if (empty($product['price_history']) || !is_array($product['price_history'])) {
                continue;
            }

            // Get geo-specific data.
            $geo_data = $product['price_history'][$geo] ?? null;
            if (!$geo_data) {
                continue;
            }

            // Skip if not in stock for this geo.
            if (empty($geo_data['instock'])) {
                continue;
            }

            // Skip if no current price.
            if (empty($geo_data['current_price']) || $geo_data['current_price'] <= 0) {
                continue;
            }

            // Skip if no average for requested period.
            if (empty($geo_data[$avg_key]) || $geo_data[$avg_key] <= 0) {
                continue;
            }

            // Compute deal status.
            $avg_price = (float)$geo_data[$avg_key];
            $current_price = (float)$geo_data['current_price'];
            $discount = (($current_price - $avg_price) / $avg_price) * 100;

            if ($discount <= $price_difference_threshold) {
                $product['deal_analysis'] = [
                    'current_price'    => $current_price,
                    'currency'         => $geo_data['currency'] ?? 'USD',
                    'avg_price'        => $avg_price,
                    'avg_period'       => $period,
                    'avg_price_3m'     => $geo_data['avg_price_3m'] ?? null,
                    'avg_price_6m'     => $geo_data['avg_price_6m'] ?? null,
                    'avg_price_12m'    => $geo_data['avg_price_12m'] ?? null,
                    'discount_percent' => round($discount, 1),
                    'savings_amount'   => round($avg_price - $current_price, 2),
                    'lowest_price'     => $geo_data['lowest_price'] ?? null,
                    'highest_price'    => $geo_data['highest_price'] ?? null,
                    'retailer'         => $geo_data['retailer'] ?? null,
                    'bestlink'         => $geo_data['bestlink'] ?? null,
                    'instock'          => true,
                ];
                $product['geo'] = $geo;
                $deals[] = $product;
            }
        }

        // Sort by discount (best deals first - most negative).
        usort($deals, function ($a, $b) {
            return $a['deal_analysis']['discount_percent'] <=> $b['deal_analysis']['discount_percent'];
        });

        // Limit results.
        return array_slice($deals, 0, $limit);
    }

    /**
     * Get deals across all product types.
     *
     * @param float  $price_difference_threshold Minimum percentage below average.
     * @param int    $limit                      Maximum deals per product type.
     * @param string $geo                        Geo code.
     * @param string $period                     Average period ('3m', '6m', '12m').
     * @return array<string, array<int, array<string, mixed>>> Deals grouped by product type.
     */
    public function get_all_deals(
        float $price_difference_threshold = -5.0,
        int $limit = 20,
        string $geo = self::DEFAULT_GEO,
        string $period = self::DEFAULT_PERIOD
    ): array {
        $product_types = [
            'Electric Scooter',
            'Electric Bike',
            'Electric Skateboard',
            'Electric Unicycle',
            'Hoverboard',
        ];

        $all_deals = [];

        foreach ($product_types as $type) {
            $deals = $this->get_deals($type, $price_difference_threshold, $limit, $geo, $period);
            if (!empty($deals)) {
                $all_deals[$type] = $deals;
            }
        }

        return $all_deals;
    }

    /**
     * Get the best deal for each product type.
     *
     * @param float  $price_difference_threshold Minimum percentage below average.
     * @param string $geo                        Geo code.
     * @param string $period                     Average period ('3m', '6m', '12m').
     * @return array<string, array<string, mixed>|null> Best deal per product type.
     */
    public function get_top_deals(
        float $price_difference_threshold = -5.0,
        string $geo = self::DEFAULT_GEO,
        string $period = self::DEFAULT_PERIOD
    ): array {
        $all_deals = $this->get_all_deals($price_difference_threshold, 1, $geo, $period);

        $top_deals = [];
        foreach ($all_deals as $type => $deals) {
            $top_deals[$type] = $deals[0] ?? null;
        }

        return $top_deals;
    }

    /**
     * Check if a specific product is currently a deal.
     *
     * @param int    $product_id                 The product post ID.
     * @param float  $price_difference_threshold Minimum percentage below average.
     * @param string $geo                        Geo code.
     * @param string $period                     Average period ('3m', '6m', '12m').
     * @return array<string, mixed>|null Deal analysis or null if not a deal.
     */
    public function is_deal(
        int $product_id,
        float $price_difference_threshold = -5.0,
        string $geo = self::DEFAULT_GEO,
        string $period = self::DEFAULT_PERIOD
    ): ?array {
        // Validate period.
        if (!in_array($period, self::PERIODS, true)) {
            $period = self::DEFAULT_PERIOD;
        }

        $avg_key = 'avg_price_' . $period;

        $product = $this->product_cache->get($product_id);

        if (!$product) {
            return null;
        }

        if (empty($product['price_history']) || !is_array($product['price_history'])) {
            return null;
        }

        $geo_data = $product['price_history'][$geo] ?? null;
        if (!$geo_data) {
            return null;
        }

        if (empty($geo_data['current_price']) || $geo_data['current_price'] <= 0) {
            return null;
        }

        if (empty($geo_data[$avg_key]) || $geo_data[$avg_key] <= 0) {
            return null;
        }

        $avg_price = (float)$geo_data[$avg_key];
        $current_price = (float)$geo_data['current_price'];
        $discount = (($current_price - $avg_price) / $avg_price) * 100;

        if ($discount > $price_difference_threshold) {
            return null;
        }

        return [
            'is_deal'          => true,
            'current_price'    => $current_price,
            'currency'         => $geo_data['currency'] ?? 'USD',
            'avg_price'        => $avg_price,
            'avg_period'       => $period,
            'avg_price_3m'     => $geo_data['avg_price_3m'] ?? null,
            'avg_price_6m'     => $geo_data['avg_price_6m'] ?? null,
            'avg_price_12m'    => $geo_data['avg_price_12m'] ?? null,
            'discount_percent' => round($discount, 1),
            'savings_amount'   => round($avg_price - $current_price, 2),
            'lowest_price'     => $geo_data['lowest_price'] ?? null,
            'highest_price'    => $geo_data['highest_price'] ?? null,
            'retailer'         => $geo_data['retailer'] ?? null,
            'bestlink'         => $geo_data['bestlink'] ?? null,
            'instock'          => !empty($geo_data['instock']),
        ];
    }

    /**
     * Get deal count by product type.
     *
     * @param float  $price_difference_threshold Minimum percentage below average.
     * @param string $geo                        Geo code.
     * @param string $period                     Average period ('3m', '6m', '12m').
     * @return array<string, int> Count of deals per product type.
     */
    public function get_deal_counts(
        float $price_difference_threshold = -5.0,
        string $geo = self::DEFAULT_GEO,
        string $period = self::DEFAULT_PERIOD
    ): array {
        $all_deals = $this->get_all_deals($price_difference_threshold, 1000, $geo, $period);

        $counts = [];
        foreach ($all_deals as $type => $deals) {
            $counts[$type] = count($deals);
        }

        return $counts;
    }

    /**
     * Get price analysis for a product (all averages).
     *
     * @param int    $product_id The product post ID.
     * @param string $geo        Geo code.
     * @return array<string, mixed>|null Price analysis or null if no data.
     */
    public function get_price_analysis(int $product_id, string $geo = self::DEFAULT_GEO): ?array {
        $product = $this->product_cache->get($product_id);

        if (!$product) {
            return null;
        }

        if (empty($product['price_history']) || !is_array($product['price_history'])) {
            return null;
        }

        $geo_data = $product['price_history'][$geo] ?? null;
        if (!$geo_data) {
            return null;
        }

        $current_price = (float)($geo_data['current_price'] ?? 0);

        // Calculate discount for each period.
        $discounts = [];
        foreach (self::PERIODS as $period) {
            $avg_key = 'avg_price_' . $period;
            if (!empty($geo_data[$avg_key]) && $geo_data[$avg_key] > 0) {
                $avg = (float)$geo_data[$avg_key];
                $discounts[$period] = round((($current_price - $avg) / $avg) * 100, 1);
            } else {
                $discounts[$period] = null;
            }
        }

        return [
            'current_price'    => $current_price,
            'currency'         => $geo_data['currency'] ?? 'USD',
            'avg_price_3m'     => $geo_data['avg_price_3m'] ?? null,
            'avg_price_6m'     => $geo_data['avg_price_6m'] ?? null,
            'avg_price_12m'    => $geo_data['avg_price_12m'] ?? null,
            'discount_vs_3m'   => $discounts['3m'],
            'discount_vs_6m'   => $discounts['6m'],
            'discount_vs_12m'  => $discounts['12m'],
            'lowest_price'     => $geo_data['lowest_price'] ?? null,
            'highest_price'    => $geo_data['highest_price'] ?? null,
            'retailer'         => $geo_data['retailer'] ?? null,
            'bestlink'         => $geo_data['bestlink'] ?? null,
            'instock'          => !empty($geo_data['instock']),
            'updated_at'       => $geo_data['updated_at'] ?? null,
        ];
    }

    /**
     * Split a price into whole and fractional parts.
     * Utility method for display formatting.
     *
     * @param float $price The price to split.
     * @return array{whole: string, fractional: string} Price parts.
     */
    public static function split_price(float $price): array {
        $whole_part = intval($price);
        $fractional_part = round(($price - $whole_part) * 100);
        $fractional_str = str_pad((string)$fractional_part, 2, '0', STR_PAD_LEFT);

        return [
            'whole'      => number_format($whole_part),
            'fractional' => $fractional_str,
        ];
    }

    /**
     * Format a deal percentage for display.
     *
     * @param float $percentage The percentage (negative for discount).
     * @return string Formatted string (e.g., "15% below average").
     */
    public static function format_deal_percentage(float $percentage): string {
        $abs_percentage = abs($percentage);

        if ($percentage < 0) {
            return sprintf('%s%% below average', number_format($abs_percentage, 0));
        } elseif ($percentage > 0) {
            return sprintf('%s%% above average', number_format($abs_percentage, 0));
        }

        return 'at average price';
    }
}
