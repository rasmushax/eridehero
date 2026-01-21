<?php
/**
 * Centralized cache key management.
 *
 * Provides consistent cache key generation across all ERH components.
 * Use this class instead of constructing cache keys manually.
 *
 * @package ERH
 */

declare(strict_types=1);

namespace ERH;

/**
 * Cache key generator for consistent naming and easy invalidation.
 */
class CacheKeys {

    /**
     * Prefix for all ERH cache keys.
     *
     * @var string
     */
    private const PREFIX = 'erh_';

    /**
     * Supported geo regions.
     *
     * @var array<string>
     */
    private const GEOS = ['US', 'GB', 'EU', 'CA', 'AU'];

    /**
     * Supported product category keys.
     *
     * @var array<string>
     */
    private const CATEGORIES = ['escooter', 'ebike', 'euc', 'skateboard', 'hoverboard'];

    /**
     * Price intel cache key (retailers + current prices).
     *
     * @param int    $product_id Product post ID.
     * @param string $geo        Geo region code (US, GB, EU, CA, AU).
     * @return string Cache key.
     */
    public static function priceIntel(int $product_id, string $geo): string {
        return self::PREFIX . "price_intel_{$product_id}_{$geo}";
    }

    /**
     * Price history cache key.
     *
     * @param int    $product_id Product post ID.
     * @param string $geo        Geo region code.
     * @return string Cache key.
     */
    public static function priceHistory(int $product_id, string $geo): string {
        return self::PREFIX . "price_history_{$product_id}_{$geo}";
    }

    /**
     * Listicle specs HTML cache key.
     *
     * @param int    $product_id   Product post ID.
     * @param string $category_key Category key (escooter, ebike, etc.).
     * @return string Cache key.
     */
    public static function listicleSpecs(int $product_id, string $category_key): string {
        return self::PREFIX . "listicle_specs_{$product_id}_{$category_key}";
    }

    /**
     * Similar products cache key.
     *
     * @param int    $product_id Product post ID.
     * @param int    $limit      Number of similar products.
     * @param string $geo        Geo region code.
     * @return string Cache key.
     */
    public static function similarProducts(int $product_id, int $limit, string $geo): string {
        return self::PREFIX . "similar_{$product_id}_{$limit}_{$geo}";
    }

    /**
     * Deals API cache key.
     *
     * @param string $category  Category filter.
     * @param int    $limit     Max deals to return.
     * @param string $geo       Geo region code.
     * @param string $period    Period string (3m, 6m, 12m).
     * @param int    $threshold Discount threshold (multiplied by 10 for clean keys).
     * @return string Cache key.
     */
    public static function deals(string $category, int $limit, string $geo, string $period, int $threshold): string {
        return self::PREFIX . "deals_api_{$category}_{$limit}_{$geo}_{$period}_{$threshold}";
    }

    /**
     * Deals finder internal cache key.
     *
     * @param string $product_type Product type.
     * @param string $geo          Geo region code.
     * @param int    $period       Period in days.
     * @param int    $threshold    Discount threshold (multiplied by 10).
     * @return string Cache key.
     */
    public static function dealsFinder(string $product_type, string $geo, int $period, int $threshold): string {
        return self::PREFIX . "deals_{$product_type}_{$geo}_{$period}_{$threshold}";
    }

    /**
     * Clear all price-related caches for a product.
     *
     * @param int $product_id Product post ID.
     * @return void
     */
    public static function clearPriceCaches(int $product_id): void {
        foreach (self::GEOS as $geo) {
            delete_transient(self::priceIntel($product_id, $geo));
            delete_transient(self::priceHistory($product_id, $geo));
        }
    }

    /**
     * Clear listicle specs cache for a product.
     *
     * @param int $product_id Product post ID.
     * @return void
     */
    public static function clearListicleSpecs(int $product_id): void {
        foreach (self::CATEGORIES as $category) {
            delete_transient(self::listicleSpecs($product_id, $category));
        }
    }

    /**
     * Clear similar products cache for a product.
     *
     * @param int $product_id Product post ID.
     * @return void
     */
    public static function clearSimilarProducts(int $product_id): void {
        // Common limit values used in the codebase
        $limits = [3, 4, 5, 6, 8, 10, 12];

        foreach (self::GEOS as $geo) {
            foreach ($limits as $limit) {
                delete_transient(self::similarProducts($product_id, $limit, $geo));
            }
        }
    }

    /**
     * Clear all caches for a product (comprehensive).
     *
     * @param int $product_id Product post ID.
     * @return void
     */
    public static function clearProduct(int $product_id): void {
        self::clearPriceCaches($product_id);
        self::clearListicleSpecs($product_id);
        self::clearSimilarProducts($product_id);
        self::clearProductSpecs($product_id);
        self::clearProductHasPricing($product_id);
        self::clearProductAnalysis($product_id);
    }

    // -------------------------------------------------------------------------
    // Additional cache keys (for consistency, not all need invalidation hooks)
    // -------------------------------------------------------------------------

    /**
     * Deal counts cache key.
     * Note: Expires via TTL (30 min), not invalidated on price updates.
     *
     * @param string $geo       Geo region code.
     * @param string $period    Period string (3m, 6m, 12m).
     * @param int    $threshold Discount threshold (multiplied by 10).
     * @return string Cache key.
     */
    public static function dealCounts(string $geo, string $period, int $threshold): string {
        return self::PREFIX . "deal_counts_{$geo}_{$period}_{$threshold}";
    }

    /**
     * YouTube videos cache key.
     * Note: Managed by YouTube sync cron job.
     *
     * @return string Cache key.
     */
    public static function youtubeVideos(): string {
        return self::PREFIX . 'youtube_videos';
    }

    /**
     * Product has pricing check cache key.
     *
     * @param int $product_id Product post ID.
     * @return string Cache key.
     */
    public static function productHasPricing(int $product_id): string {
        return self::PREFIX . "has_pricing_{$product_id}";
    }

    /**
     * Product page specs HTML cache key.
     *
     * @param int    $product_id   Product post ID.
     * @param string $category_key Category key (escooter, ebike, etc.).
     * @return string Cache key.
     */
    public static function productSpecs(int $product_id, string $category_key): string {
        return self::PREFIX . "product_specs_{$product_id}_{$category_key}";
    }

    /**
     * Clear product specs cache for a product.
     *
     * @param int $product_id Product post ID.
     * @return void
     */
    public static function clearProductSpecs(int $product_id): void {
        foreach (self::CATEGORIES as $category) {
            delete_transient(self::productSpecs($product_id, $category));
        }
    }

    /**
     * Clear product has pricing cache for a product.
     *
     * @param int $product_id Product post ID.
     * @return void
     */
    public static function clearProductHasPricing(int $product_id): void {
        delete_transient(self::productHasPricing($product_id));
    }

    /**
     * Product analysis cache key (single-product advantages/weaknesses).
     *
     * @param int    $product_id Product post ID.
     * @param string $geo        Geo region code.
     * @return string Cache key.
     */
    public static function productAnalysis(int $product_id, string $geo): string {
        return self::PREFIX . "product_analysis_{$product_id}_{$geo}";
    }

    /**
     * Clear product analysis cache for a product.
     *
     * @param int $product_id Product post ID.
     * @return void
     */
    public static function clearProductAnalysis(int $product_id): void {
        foreach (self::GEOS as $geo) {
            delete_transient(self::productAnalysis($product_id, $geo));
        }
    }
}
