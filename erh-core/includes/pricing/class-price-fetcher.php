<?php
/**
 * Price Fetcher - Retrieves pricing data from HFT plugin tables.
 *
 * @package ERH\Pricing
 */

declare(strict_types=1);

namespace ERH\Pricing;

/**
 * Fetches product prices from the HouseFresh Tools (HFT) plugin database tables.
 */
class PriceFetcher {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * HFT tracked links table name.
     *
     * @var string
     */
    private string $tracked_links_table;

    /**
     * HFT scrapers table name.
     *
     * @var string
     */
    private string $scrapers_table;

    /**
     * Retailer logos helper.
     *
     * @var RetailerLogos
     */
    private RetailerLogos $logos;

    /**
     * Constructor.
     *
     * @param RetailerLogos|null $logos Optional RetailerLogos instance.
     */
    public function __construct(?RetailerLogos $logos = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
        $this->scrapers_table = $wpdb->prefix . 'hft_scrapers';
        $this->logos = $logos ?? new RetailerLogos();
    }

    /**
     * Check if HFT plugin is active and tables exist.
     *
     * @return bool True if HFT is available.
     */
    public function is_hft_available(): bool {
        // Check if HFT plugin is loaded.
        if (!defined('HFT_VERSION')) {
            return false;
        }

        // Check if tables exist.
        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $this->tracked_links_table
            )
        );

        return $table_exists === $this->tracked_links_table;
    }

    /**
     * Get all prices for a product.
     *
     * @param int         $product_id The product post ID.
     * @param string|null $geo        Optional geo target filter (e.g., 'US', 'GB').
     * @return array<int, array<string, mixed>> Array of price data, sorted by availability and price.
     */
    public function get_prices(int $product_id, ?string $geo = null): array {
        if (!$this->is_hft_available()) {
            return [];
        }

        $sql = "SELECT
                    tl.id,
                    tl.product_post_id,
                    tl.tracking_url,
                    tl.scraper_id,
                    tl.parser_identifier,
                    tl.geo_target,
                    tl.affiliate_link_override,
                    tl.current_price,
                    tl.current_currency,
                    tl.current_status,
                    tl.current_shipping_info,
                    tl.last_scraped_at,
                    s.name AS retailer_name,
                    s.domain AS retailer_domain,
                    s.affiliate_link_format
                FROM {$this->tracked_links_table} tl
                LEFT JOIN {$this->scrapers_table} s ON tl.scraper_id = s.id
                WHERE tl.product_post_id = %d
                AND tl.current_price IS NOT NULL
                AND tl.current_price > 0";

        $params = [$product_id];

        // Add geo filter if specified.
        if ($geo !== null) {
            $sql .= " AND (tl.geo_target IS NULL OR tl.geo_target = '' OR tl.geo_target = %s)";
            $params[] = $geo;
        }

        // Order by: in-stock first, then by price ascending.
        $sql .= " ORDER BY
                    CASE WHEN tl.current_status = 'In Stock' THEN 0 ELSE 1 END,
                    tl.current_price ASC";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );

        if (empty($results)) {
            return [];
        }

        // Transform results to match expected format.
        return array_map([$this, 'transform_price_row'], $results);
    }

    /**
     * Get the best (lowest in-stock) price for a product.
     *
     * @param int         $product_id The product post ID.
     * @param string|null $geo        Optional geo target filter.
     * @return array<string, mixed>|null Best price data or null if none found.
     */
    public function get_best_price(int $product_id, ?string $geo = null): ?array {
        $prices = $this->get_prices($product_id, $geo);

        if (empty($prices)) {
            return null;
        }

        // First result is already the best (sorted by in-stock, then price).
        return $prices[0];
    }

    /**
     * Get the best price for a product filtered by currency.
     *
     * This is used by the tracker system to ensure validation uses the same
     * currency that the frontend displayed to the user.
     *
     * @param int    $product_id The product post ID.
     * @param string $geo        Geo target filter (e.g., 'US', 'EU', 'GB').
     * @param string $currency   Currency code to filter by (e.g., 'USD', 'EUR', 'GBP').
     * @return array<string, mixed>|null Best price data or null if none found.
     */
    public function get_best_price_for_currency(int $product_id, string $geo, string $currency): ?array {
        $prices = $this->get_prices($product_id, $geo);

        if (empty($prices)) {
            return null;
        }

        // Filter to only prices matching the requested currency.
        $filtered = array_filter(
            $prices,
            fn($price) => ($price['currency'] ?? 'USD') === $currency
        );

        if (empty($filtered)) {
            return null;
        }

        // Return the best (first after filtering - already sorted by in-stock, then price).
        return reset($filtered);
    }

    /**
     * Get prices for multiple products at once.
     *
     * @param array<int>  $product_ids Array of product post IDs.
     * @param string|null $geo         Optional geo target filter.
     * @return array<int, array<int, array<string, mixed>>> Prices indexed by product ID.
     */
    public function get_prices_bulk(array $product_ids, ?string $geo = null): array {
        if (empty($product_ids) || !$this->is_hft_available()) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));

        $sql = "SELECT
                    tl.id,
                    tl.product_post_id,
                    tl.tracking_url,
                    tl.scraper_id,
                    tl.parser_identifier,
                    tl.geo_target,
                    tl.affiliate_link_override,
                    tl.current_price,
                    tl.current_currency,
                    tl.current_status,
                    tl.current_shipping_info,
                    tl.last_scraped_at,
                    s.name AS retailer_name,
                    s.domain AS retailer_domain,
                    s.affiliate_link_format
                FROM {$this->tracked_links_table} tl
                LEFT JOIN {$this->scrapers_table} s ON tl.scraper_id = s.id
                WHERE tl.product_post_id IN ({$placeholders})
                AND tl.current_price IS NOT NULL
                AND tl.current_price > 0";

        $params = $product_ids;

        if ($geo !== null) {
            $sql .= " AND (tl.geo_target IS NULL OR tl.geo_target = '' OR tl.geo_target = %s)";
            $params[] = $geo;
        }

        $sql .= " ORDER BY
                    tl.product_post_id,
                    CASE WHEN tl.current_status = 'In Stock' THEN 0 ELSE 1 END,
                    tl.current_price ASC";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );

        // Group by product ID.
        $grouped = [];
        foreach ($results as $row) {
            $pid = (int)$row['product_post_id'];
            if (!isset($grouped[$pid])) {
                $grouped[$pid] = [];
            }
            $grouped[$pid][] = $this->transform_price_row($row);
        }

        return $grouped;
    }

    /**
     * Check if a product has any price data.
     *
     * @param int $product_id The product post ID.
     * @return bool True if product has price data.
     */
    public function has_price_data(int $product_id): bool {
        if (!$this->is_hft_available()) {
            return false;
        }

        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tracked_links_table}
                WHERE product_post_id = %d
                AND current_price IS NOT NULL
                AND current_price > 0",
                $product_id
            )
        );

        return (int)$count > 0;
    }

    /**
     * Check if a product is in stock at any retailer.
     *
     * @param int         $product_id The product post ID.
     * @param string|null $geo        Optional geo target filter.
     * @return bool True if product is in stock somewhere.
     */
    public function is_in_stock(int $product_id, ?string $geo = null): bool {
        if (!$this->is_hft_available()) {
            return false;
        }

        $sql = "SELECT COUNT(*) FROM {$this->tracked_links_table}
                WHERE product_post_id = %d
                AND current_status = 'In Stock'
                AND current_price IS NOT NULL
                AND current_price > 0";

        $params = [$product_id];

        if ($geo !== null) {
            $sql .= " AND (geo_target IS NULL OR geo_target = '' OR geo_target = %s)";
            $params[] = $geo;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare($sql, $params)
        );

        return (int)$count > 0;
    }

    /**
     * Transform a database row into the expected price format.
     *
     * @param array<string, mixed> $row Database row.
     * @return array<string, mixed> Transformed price data.
     */
    private function transform_price_row(array $row): array {
        // Build the affiliate URL.
        $url = $row['affiliate_link_override'] ?: $this->build_affiliate_url($row);

        // Get retailer logo from HFT scraper (use 'erh-logo-small' - 48px height).
        $logo_url = null;
        $scraper_id = isset($row['scraper_id']) ? (int)$row['scraper_id'] : 0;
        if ($scraper_id > 0) {
            $logo_url = $this->logos->get_logo_by_id($scraper_id, 'erh-logo-small');
        }

        return [
            'id'              => (int)$row['id'],
            'product_id'      => (int)$row['product_post_id'],
            'url'             => $url,
            'price'           => (float)$row['current_price'],
            'currency'        => $row['current_currency'] ?: 'USD',
            'currency_symbol' => $this->get_currency_symbol($row['current_currency'] ?: 'USD'),
            'status'          => $row['current_status'],
            'in_stock'        => $row['current_status'] === 'In Stock',
            'shipping'        => $row['current_shipping_info'],
            'retailer'        => $row['retailer_name'] ?: $row['retailer_domain'] ?: 'Unknown',
            'domain'          => $row['retailer_domain'],
            'logo_url'        => $logo_url,
            'geo'             => $row['geo_target'],
            'last_updated'    => $row['last_scraped_at'],
        ];
    }

    /**
     * Build affiliate URL from tracking URL and format template.
     *
     * @param array<string, mixed> $row Database row with URL and format info.
     * @return string The affiliate URL.
     */
    private function build_affiliate_url(array $row): string {
        $tracking_url = $row['tracking_url'];
        $format = $row['affiliate_link_format'];

        // If no format template, return tracking URL as-is.
        if (empty($format)) {
            return $tracking_url;
        }

        // Replace template placeholders.
        $url = str_replace(
            ['{URL}', '{URLE}', '{ID}'],
            [$tracking_url, urlencode($tracking_url), $this->extract_product_id($tracking_url)],
            $format
        );

        return $url;
    }

    /**
     * Extract product ID from URL (for Amazon ASINs, etc.).
     *
     * @param string $url The product URL.
     * @return string The extracted ID or empty string.
     */
    private function extract_product_id(string $url): string {
        // Try to extract Amazon ASIN.
        if (preg_match('/\/(?:dp|gp\/product)\/([A-Z0-9]{10})/i', $url, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Get currency symbol for a currency code.
     *
     * @param string $currency_code ISO currency code.
     * @return string Currency symbol.
     */
    private function get_currency_symbol(string $currency_code): string {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'CA$',
            'AUD' => 'A$',
            'JPY' => '¥',
            'CNY' => '¥',
        ];

        return $symbols[strtoupper($currency_code)] ?? $currency_code;
    }
}
