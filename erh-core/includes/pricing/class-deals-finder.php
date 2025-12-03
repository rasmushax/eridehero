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
 */
class DealsFinder {

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
     * Get deals for a product type.
     *
     * @param string $product_type              The product type (e.g., 'Electric Scooter').
     * @param float  $price_difference_threshold Minimum percentage below average (negative value, e.g., -5 for 5% below).
     * @param int    $limit                     Maximum number of deals to return.
     * @return array<int, array<string, mixed>> Array of deal products with price analysis.
     */
    public function get_deals(
        string $product_type = 'Electric Scooter',
        float $price_difference_threshold = -5.0,
        int $limit = 50
    ): array {
        // Get all products of this type that are in stock with price data.
        $products = $this->product_cache->get_filtered(
            [
                'product_type' => $product_type,
                'instock'      => true,
            ],
            'price',
            'ASC',
            500, // Get more than needed, we'll filter.
            0
        );

        $deals = [];

        foreach ($products as $product) {
            // Skip products without price.
            if (empty($product['price']) || $product['price'] <= 0) {
                continue;
            }

            // Skip products without price history.
            if (empty($product['price_history']) || !is_array($product['price_history'])) {
                continue;
            }

            $price_history = $product['price_history'];

            // Check if we have 6-month average price.
            if (!isset($price_history['average_price_6m']) || $price_history['average_price_6m'] <= 0) {
                continue;
            }

            $avg_price_6m = (float)$price_history['average_price_6m'];
            $current_price = (float)$product['price'];

            // Calculate price difference percentage.
            $price_diff_percent = (($current_price - $avg_price_6m) / $avg_price_6m) * 100;

            // Check if it qualifies as a deal.
            if ($price_diff_percent <= $price_difference_threshold) {
                $product['deal_analysis'] = [
                    'current_price'       => $current_price,
                    'average_price_6m'    => $avg_price_6m,
                    'price_diff_percent'  => round($price_diff_percent, 1),
                    'savings_amount'      => round($avg_price_6m - $current_price, 2),
                    'lowest_price'        => $price_history['lowest_price'] ?? null,
                    'highest_price'       => $price_history['highest_price'] ?? null,
                    'z_score'             => $price_history['z_score'] ?? null,
                ];

                $deals[] = $product;
            }
        }

        // Sort by price difference (best deals first - most negative).
        usort($deals, function ($a, $b) {
            return $a['deal_analysis']['price_diff_percent'] <=> $b['deal_analysis']['price_diff_percent'];
        });

        // Limit results.
        return array_slice($deals, 0, $limit);
    }

    /**
     * Get deals across all product types.
     *
     * @param float $price_difference_threshold Minimum percentage below average.
     * @param int   $limit                      Maximum deals per product type.
     * @return array<string, array<int, array<string, mixed>>> Deals grouped by product type.
     */
    public function get_all_deals(float $price_difference_threshold = -5.0, int $limit = 20): array {
        $product_types = [
            'Electric Scooter',
            'Electric Bike',
            'Electric Skateboard',
            'Electric Unicycle',
            'Hoverboard',
        ];

        $all_deals = [];

        foreach ($product_types as $type) {
            $deals = $this->get_deals($type, $price_difference_threshold, $limit);
            if (!empty($deals)) {
                $all_deals[$type] = $deals;
            }
        }

        return $all_deals;
    }

    /**
     * Get the best deal for each product type.
     *
     * @param float $price_difference_threshold Minimum percentage below average.
     * @return array<string, array<string, mixed>|null> Best deal per product type.
     */
    public function get_top_deals(float $price_difference_threshold = -5.0): array {
        $all_deals = $this->get_all_deals($price_difference_threshold, 1);

        $top_deals = [];
        foreach ($all_deals as $type => $deals) {
            $top_deals[$type] = $deals[0] ?? null;
        }

        return $top_deals;
    }

    /**
     * Check if a specific product is currently a deal.
     *
     * @param int   $product_id                 The product post ID.
     * @param float $price_difference_threshold Minimum percentage below average.
     * @return array<string, mixed>|null Deal analysis or null if not a deal.
     */
    public function is_deal(int $product_id, float $price_difference_threshold = -5.0): ?array {
        $product = $this->product_cache->get($product_id);

        if (!$product) {
            return null;
        }

        if (empty($product['price']) || $product['price'] <= 0) {
            return null;
        }

        if (empty($product['price_history']) || !is_array($product['price_history'])) {
            return null;
        }

        $price_history = $product['price_history'];

        if (!isset($price_history['average_price_6m']) || $price_history['average_price_6m'] <= 0) {
            return null;
        }

        $avg_price_6m = (float)$price_history['average_price_6m'];
        $current_price = (float)$product['price'];

        $price_diff_percent = (($current_price - $avg_price_6m) / $avg_price_6m) * 100;

        if ($price_diff_percent > $price_difference_threshold) {
            return null;
        }

        return [
            'is_deal'             => true,
            'current_price'       => $current_price,
            'average_price_6m'    => $avg_price_6m,
            'price_diff_percent'  => round($price_diff_percent, 1),
            'savings_amount'      => round($avg_price_6m - $current_price, 2),
            'lowest_price'        => $price_history['lowest_price'] ?? null,
            'highest_price'       => $price_history['highest_price'] ?? null,
        ];
    }

    /**
     * Get deal count by product type.
     *
     * @param float $price_difference_threshold Minimum percentage below average.
     * @return array<string, int> Count of deals per product type.
     */
    public function get_deal_counts(float $price_difference_threshold = -5.0): array {
        $all_deals = $this->get_all_deals($price_difference_threshold, 1000);

        $counts = [];
        foreach ($all_deals as $type => $deals) {
            $counts[$type] = count($deals);
        }

        return $counts;
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
