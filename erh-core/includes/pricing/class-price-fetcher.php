<?php
/**
 * Price Fetcher - Retrieves pricing data from HFT plugin tables.
 *
 * @package ERH\Pricing
 */

declare(strict_types=1);

namespace ERH\Pricing;

use ERH\GeoConfig;

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
                    tl.market_prices,
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
        // Links can have geo set in two places:
        // 1. tl.geo_target - for API-based links (single code like "US") or scraper links (comma-separated like "AT,BE,DE")
        // 2. s.geos - scraper's applicable geos (comma-separated like "AT,BE,DE" or single like "US")
        // We filter by expected currency for the geo to avoid mixing currencies.
        // Shopify Markets links bypass the currency filter — their current_currency only reflects
        // the first market, but they may have the correct currency in market_prices JSON.
        if ($geo !== null) {
            $geo = strtoupper($geo);
            $expected_currency = $this->get_currency_for_geo($geo);

            // Filter by expected currency for this geo.
            // SM links bypass this — their per-market prices are checked after expansion.
            $sql .= " AND (tl.current_currency = %s OR tl.market_prices IS NOT NULL)";
            $params[] = $expected_currency;

            // EU region needs to match EU or any individual EU country code.
            if ($geo === 'EU') {
                $eu_countries = array_merge(['EU'], GeoConfig::EU_COUNTRIES);

                // Build FIND_IN_SET conditions for comma-separated geo fields.
                $find_in_set_geo_target = [];
                $find_in_set_scraper_geos = [];
                foreach ($eu_countries as $country) {
                    $find_in_set_geo_target[] = "FIND_IN_SET(%s, tl.geo_target)";
                    $find_in_set_scraper_geos[] = "FIND_IN_SET(%s, s.geos)";
                }
                $geo_target_clause = implode(' OR ', $find_in_set_geo_target);
                $scraper_geos_clause = implode(' OR ', $find_in_set_scraper_geos);

                // Match: geo_target contains EU country, scraper geos contains EU country, OR no geo restriction
                $sql .= " AND (
                    ({$geo_target_clause})
                    OR ({$scraper_geos_clause})
                    OR (tl.geo_target IS NULL AND (s.geos IS NULL OR s.geos = ''))
                )";
                // Add params: eu_countries for geo_target FIND_IN_SET, then eu_countries for scraper geos FIND_IN_SET
                $params = array_merge($params, $eu_countries, $eu_countries);
            } else {
                // Single geo: check if it's in the comma-separated list or matches exactly.
                $sql .= " AND (
                    tl.geo_target = %s
                    OR FIND_IN_SET(%s, tl.geo_target)
                    OR s.geos = %s
                    OR FIND_IN_SET(%s, s.geos)
                    OR (tl.geo_target IS NULL AND (s.geos IS NULL OR s.geos = ''))
                )";
                $params[] = $geo; // exact match geo_target
                $params[] = $geo; // FIND_IN_SET geo_target
                $params[] = $geo; // exact match s.geos
                $params[] = $geo; // FIND_IN_SET s.geos
            }
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

        // Expand Shopify Markets rows into per-market offers.
        $results = $this->expand_shopify_market_rows($results, $geo);

        // Post-filter: SM rows bypassed the SQL currency filter, so filter by currency now.
        if ($geo !== null) {
            $expected_currency = $this->get_currency_for_geo($geo);
            $results = array_values(array_filter($results, function (array $row) use ($expected_currency): bool {
                return strtoupper($row['current_currency'] ?: 'USD') === $expected_currency;
            }));
        }

        // Re-sort after expansion: in-stock first, then price ascending.
        usort($results, function (array $a, array $b): int {
            $a_stock = ($a['current_status'] === 'In Stock') ? 0 : 1;
            $b_stock = ($b['current_status'] === 'In Stock') ? 0 : 1;
            if ($a_stock !== $b_stock) {
                return $a_stock - $b_stock;
            }
            return ((float) $a['current_price']) <=> ((float) $b['current_price']);
        });

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
                    tl.market_prices,
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
            $geo = strtoupper($geo);

            // EU region needs to match EU + individual EU country codes.
            // FIND_IN_SET handles comma-separated geo_target (e.g., SM links with "US,DE,GB").
            // SM links always pass through — their per-market filtering happens after expansion.
            if ($geo === 'EU') {
                $eu_countries = array_merge(['EU'], GeoConfig::EU_COUNTRIES);
                $eu_placeholders = implode(',', array_fill(0, count($eu_countries), '%s'));
                $find_conditions = [];
                foreach ($eu_countries as $country) {
                    $find_conditions[] = "FIND_IN_SET(%s, tl.geo_target)";
                }
                $find_clause = implode(' OR ', $find_conditions);
                $sql .= " AND (tl.geo_target IS NULL OR tl.geo_target = '' OR tl.geo_target IN ({$eu_placeholders}) OR ({$find_clause}) OR tl.market_prices IS NOT NULL)";
                $params = array_merge($params, $eu_countries, $eu_countries);
            } else {
                $sql .= " AND (tl.geo_target IS NULL OR tl.geo_target = '' OR tl.geo_target = %s OR FIND_IN_SET(%s, tl.geo_target) OR tl.market_prices IS NOT NULL)";
                $params[] = $geo;
                $params[] = $geo;
            }
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

        // Expand Shopify Markets rows into per-market offers.
        $results = $this->expand_shopify_market_rows($results, $geo);

        // Group by product ID.
        $grouped = [];
        foreach ($results as $row) {
            $pid = (int) $row['product_post_id'];
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
            $geo = strtoupper($geo);

            // EU region needs to match EU + individual EU country codes.
            // FIND_IN_SET handles comma-separated geo_target values (e.g., SM links).
            if ($geo === 'EU') {
                $eu_countries = array_merge(['EU'], GeoConfig::EU_COUNTRIES);
                $eu_placeholders = implode(',', array_fill(0, count($eu_countries), '%s'));
                $find_conditions = [];
                foreach ($eu_countries as $country) {
                    $find_conditions[] = "FIND_IN_SET(%s, geo_target)";
                }
                $find_clause = implode(' OR ', $find_conditions);
                $sql .= " AND (geo_target IS NULL OR geo_target = '' OR geo_target IN ({$eu_placeholders}) OR ({$find_clause}))";
                $params = array_merge($params, $eu_countries, $eu_countries);
            } else {
                $sql .= " AND (geo_target IS NULL OR geo_target = '' OR geo_target = %s OR FIND_IN_SET(%s, geo_target))";
                $params[] = $geo;
                $params[] = $geo;
            }
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare($sql, $params)
        );

        return (int)$count > 0;
    }

    /**
     * Expand Shopify Markets rows into per-market offers.
     *
     * A single SM tracked link with N markets becomes N rows, each with the
     * correct price, currency, status, and geo for that market.
     * Regular (non-SM) rows pass through unchanged.
     *
     * @param array<int, array<string, mixed>> $rows Raw database rows.
     * @param string|null $geo Optional geo filter to only include matching markets.
     * @return array<int, array<string, mixed>> Expanded rows.
     */
    private function expand_shopify_market_rows(array $rows, ?string $geo): array {
        $expanded = [];

        foreach ($rows as $row) {
            $market_json = $row['market_prices'] ?? null;

            if (empty($market_json)) {
                // Regular link — pass through unchanged.
                $expanded[] = $row;
                continue;
            }

            $markets = json_decode($market_json, true);
            if (!is_array($markets) || empty($markets)) {
                // Invalid or empty JSON — fall back to regular behavior.
                $expanded[] = $row;
                continue;
            }

            // Expand each market into a separate row.
            foreach ($markets as $market_key => $market) {
                if (!isset($market['price']) || !is_numeric($market['price'])) {
                    continue;
                }

                $countries = $market['countries'] ?? [$market_key];

                // If geo filter active, only include markets matching the requested geo.
                if ($geo !== null && !$this->market_matches_geo($countries, $geo)) {
                    continue;
                }

                // Create row copy with market-specific values.
                $market_row = $row;
                $market_row['current_price'] = $market['price'];
                $market_row['current_currency'] = $market['currency'] ?? 'USD';
                $market_row['current_status'] = $market['status'] ?? $row['current_status'];
                $market_row['geo_target'] = implode(',', $countries);
                $market_row['market_prices'] = null; // Prevent re-processing downstream.

                $expanded[] = $market_row;
            }
        }

        return $expanded;
    }

    /**
     * Check if a market's countries match the requested geo/region.
     *
     * Maps each country to ERH's 5-region model (US, GB, EU, CA, AU) via GeoConfig
     * and checks for a match.
     *
     * @param array<string> $countries Country codes from the market's countries array.
     * @param string        $geo       Requested geo/region code.
     * @return bool True if any country maps to the requested region.
     */
    private function market_matches_geo(array $countries, string $geo): bool {
        $geo = strtoupper($geo);

        foreach ($countries as $country) {
            if (GeoConfig::get_region(strtoupper($country)) === $geo) {
                return true;
            }
        }

        return false;
    }

    /**
     * Transform a database row into the expected price format.
     *
     * @param array<string, mixed> $row Database row (may be SM-expanded).
     * @return array<string, mixed> Transformed price data.
     */
    private function transform_price_row(array $row): array {
        // Build the affiliate URL.
        $url = $row['affiliate_link_override'] ?: $this->build_affiliate_url($row);

        // Get retailer logo and name.
        $logo_url = null;
        $retailer_name = null;
        $scraper_id = isset($row['scraper_id']) ? (int)$row['scraper_id'] : 0;
        $parser_id = $row['parser_identifier'] ?? null;

        if ($scraper_id > 0) {
            // Has a scraper - use scraper's logo and name.
            $logo_url = $this->logos->get_logo_by_id($scraper_id, 'erh-logo-small');
            $retailer_name = $row['retailer_name'] ?: $row['retailer_domain'];
        } elseif ($parser_id) {
            // No scraper but has parser_identifier (e.g., 'amazon') - use special retailer handling.
            $logo_url = $this->logos->get_logo_by_domain($parser_id, 'erh-logo-small');
            $retailer_name = $this->logos->get_retailer_name($parser_id);

            // For Amazon, append the geo (e.g., "Amazon US", "Amazon UK").
            if ($parser_id === 'amazon' && !empty($row['geo_target'])) {
                $retailer_name .= ' ' . strtoupper($row['geo_target']);
            }
        }

        // Final fallback for retailer name.
        if (!$retailer_name) {
            $retailer_name = $row['retailer_domain'] ?: 'Unknown';
        }

        // Build tracked URL for click tracking.
        $link_id = (int)$row['id'];
        $product_id = (int)$row['product_post_id'];
        $product_slug = get_post_field('post_name', $product_id);
        $tracked_url = $product_slug ? home_url("/go/{$product_slug}/{$link_id}/") : null;

        return [
            'id'              => $link_id,
            'product_id'      => $product_id,
            'url'             => $url,
            'tracked_url'     => $tracked_url,
            'price'           => (float)$row['current_price'],
            'currency'        => $row['current_currency'] ?: 'USD',
            'currency_symbol' => $this->get_currency_symbol($row['current_currency'] ?: 'USD'),
            'status'          => $row['current_status'],
            'in_stock'        => $row['current_status'] === 'In Stock',
            'shipping'        => $row['current_shipping_info'],
            'retailer'        => $retailer_name,
            'domain'          => $row['retailer_domain'] ?: $parser_id,
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
     * Get expected currency for a geo region.
     *
     * @param string $geo Geo region code.
     * @return string Currency code.
     */
    private function get_currency_for_geo(string $geo): string {
        $geo_currencies = [
            'US' => 'USD',
            'GB' => 'GBP',
            'EU' => 'EUR',
            'CA' => 'CAD',
            'AU' => 'AUD',
            // EU countries also map to EUR.
            'DE' => 'EUR',
            'FR' => 'EUR',
            'IT' => 'EUR',
            'ES' => 'EUR',
            'NL' => 'EUR',
            'DK' => 'EUR', // Uses EUR for pricing
            'SE' => 'EUR',
            'NO' => 'EUR',
            'AT' => 'EUR',
            'BE' => 'EUR',
            'IE' => 'EUR',
            'PT' => 'EUR',
            'FI' => 'EUR',
            'GR' => 'EUR',
            'PL' => 'EUR',
            'CZ' => 'EUR',
        ];

        return $geo_currencies[strtoupper($geo)] ?? 'USD';
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
