<?php
/**
 * Click Stats - Admin statistics queries.
 *
 * @package ERH\Tracking
 */

declare(strict_types=1);

namespace ERH\Tracking;

/**
 * Provides statistics queries for click tracking data.
 */
class ClickStats {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Clicks table name.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . ERH_TABLE_CLICKS;
    }

    /**
     * Get the bot filter clause for queries.
     *
     * @param bool $exclude_bots Whether to exclude bots.
     * @return string SQL WHERE clause fragment.
     */
    private function get_bot_clause(bool $exclude_bots): string {
        return $exclude_bots ? 'AND is_bot = 0' : '';
    }

    /**
     * Get the internal-only filter clause (excludes external referrers).
     *
     * @param string $alias Optional table alias prefix (e.g., 'c.').
     * @return string SQL WHERE clause fragment.
     */
    private function get_internal_clause(string $alias = ''): string {
        return "AND {$alias}is_external = 0";
    }

    /**
     * Get summary statistics.
     *
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Summary data.
     */
    public function get_summary(int $days = 30, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);
        $internal_clause = $this->get_internal_clause();

        // Total clicks (internal only).
        $total_clicks = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE clicked_at >= %s {$bot_clause} {$internal_clause}",
                $date_from
            )
        );

        // Unique products clicked.
        $unique_products = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT product_id) FROM {$this->table_name} WHERE clicked_at >= %s {$bot_clause} {$internal_clause}",
                $date_from
            )
        );

        // Mobile percentage.
        $mobile_clicks = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE clicked_at >= %s AND device_type IN ('mobile', 'tablet') {$bot_clause} {$internal_clause}",
                $date_from
            )
        );
        $mobile_percent = $total_clicks > 0 ? round(($mobile_clicks / $total_clicks) * 100) : 0;

        // Unique referrer pages.
        $unique_pages = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT referrer_path) FROM {$this->table_name}
                WHERE clicked_at >= %s AND referrer_path IS NOT NULL {$bot_clause} {$internal_clause}",
                $date_from
            )
        );

        return [
            'total_clicks'    => $total_clicks,
            'unique_products' => $unique_products,
            'mobile_percent'  => $mobile_percent,
            'unique_pages'    => $unique_pages,
        ];
    }

    /**
     * Get bot traffic statistics.
     *
     * @param int $days Number of days to look back.
     * @return array Bot statistics.
     */
    public function get_bot_stats(int $days = 30): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $total = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE clicked_at >= %s",
                $date_from
            )
        );

        $bot_count = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE clicked_at >= %s AND is_bot = 1",
                $date_from
            )
        );

        $human_count = $total - $bot_count;
        $bot_percent = $total > 0 ? round(($bot_count / $total) * 100, 1) : 0;

        return [
            'total'       => $total,
            'bot_count'   => $bot_count,
            'human_count' => $human_count,
            'bot_percent' => $bot_percent,
        ];
    }

    /**
     * Get top referrer pages.
     *
     * @param int  $days         Number of days to look back.
     * @param int  $limit        Maximum results.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Top referrers with click counts.
     */
    public function get_top_referrers(int $days = 30, int $limit = 20, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);
        $internal_clause = $this->get_internal_clause();

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    referrer_path,
                    COUNT(*) as click_count,
                    COUNT(DISTINCT product_id) as unique_products
                FROM {$this->table_name}
                WHERE clicked_at >= %s
                AND referrer_path IS NOT NULL
                AND referrer_path != ''
                {$bot_clause}
                {$internal_clause}
                GROUP BY referrer_path
                ORDER BY click_count DESC
                LIMIT %d",
                $date_from,
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get clicks by product.
     *
     * @param int  $days         Number of days to look back.
     * @param int  $limit        Maximum results.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Products with click counts.
     */
    public function get_product_clicks(int $days = 30, int $limit = 20, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    c.product_id,
                    p.post_title as product_name,
                    COUNT(*) as click_count,
                    COUNT(DISTINCT c.tracked_link_id) as unique_retailers
                FROM {$this->table_name} c
                LEFT JOIN {$this->wpdb->posts} p ON c.product_id = p.ID
                WHERE c.clicked_at >= %s {$bot_clause}
                GROUP BY c.product_id, p.post_title
                ORDER BY click_count DESC
                LIMIT %d",
                $date_from,
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get clicks for a specific product.
     *
     * @param int  $product_id   The product post ID.
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Click data for the product.
     */
    public function get_product_detail_clicks(int $product_id, int $days = 30, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        // Get overall stats.
        $total = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE product_id = %d AND clicked_at >= %s {$bot_clause}",
                $product_id,
                $date_from
            )
        );

        // Get by retailer.
        $tracked_links_table = $this->wpdb->prefix . 'hft_tracked_links';
        $scrapers_table = $this->wpdb->prefix . 'hft_scrapers';

        $by_retailer = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    COALESCE(s.name, tl.parser_identifier, 'Unknown') as retailer,
                    tl.geo_target,
                    COUNT(*) as click_count
                FROM {$this->table_name} c
                LEFT JOIN {$tracked_links_table} tl ON c.tracked_link_id = tl.id
                LEFT JOIN {$scrapers_table} s ON tl.scraper_id = s.id
                WHERE c.product_id = %d AND c.clicked_at >= %s {$bot_clause}
                GROUP BY retailer, tl.geo_target
                ORDER BY click_count DESC",
                $product_id,
                $date_from
            ),
            ARRAY_A
        );

        return [
            'total_clicks' => $total,
            'by_retailer'  => $by_retailer ?: [],
        ];
    }

    /**
     * Get clicks by retailer/geo.
     *
     * @param int  $days         Number of days to look back.
     * @param int  $limit        Maximum results.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Retailers with click counts.
     */
    public function get_retailer_clicks(int $days = 30, int $limit = 20, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        $tracked_links_table = $this->wpdb->prefix . 'hft_tracked_links';
        $scrapers_table = $this->wpdb->prefix . 'hft_scrapers';

        // Use tl.geo_target first (for Amazon links), then fall back to s.geos (for scraper-based links)
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    COALESCE(s.name, tl.parser_identifier, 'Unknown') as retailer,
                    COALESCE(tl.geo_target, s.geos) as geo_target,
                    COUNT(*) as click_count,
                    COUNT(DISTINCT c.product_id) as unique_products
                FROM {$this->table_name} c
                LEFT JOIN {$tracked_links_table} tl ON c.tracked_link_id = tl.id
                LEFT JOIN {$scrapers_table} s ON tl.scraper_id = s.id
                WHERE c.clicked_at >= %s {$bot_clause}
                GROUP BY retailer, geo_target
                ORDER BY click_count DESC
                LIMIT %d",
                $date_from,
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get clicks by geo.
     *
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Geo regions with click counts.
     */
    public function get_geo_clicks(int $days = 30, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    COALESCE(user_geo, 'Unknown') as geo,
                    COUNT(*) as click_count
                FROM {$this->table_name}
                WHERE clicked_at >= %s {$bot_clause}
                GROUP BY user_geo
                ORDER BY click_count DESC",
                $date_from
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get daily click trend.
     *
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Daily click counts.
     */
    public function get_daily_trend(int $days = 30, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    DATE(clicked_at) as date,
                    COUNT(*) as click_count
                FROM {$this->table_name}
                WHERE clicked_at >= %s {$bot_clause}
                GROUP BY DATE(clicked_at)
                ORDER BY date ASC",
                $date_from
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get device type distribution.
     *
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Device types with counts.
     */
    public function get_device_distribution(int $days = 30, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    device_type,
                    COUNT(*) as click_count
                FROM {$this->table_name}
                WHERE clicked_at >= %s {$bot_clause}
                GROUP BY device_type
                ORDER BY click_count DESC",
                $date_from
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get hourly click distribution.
     *
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Hourly click counts.
     */
    public function get_hourly_distribution(int $days = 30, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    HOUR(clicked_at) as hour,
                    COUNT(*) as click_count
                FROM {$this->table_name}
                WHERE clicked_at >= %s {$bot_clause}
                GROUP BY HOUR(clicked_at)
                ORDER BY hour ASC",
                $date_from
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get summary for the previous period (for period-over-period comparison).
     *
     * @param int  $days         Current period length in days.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Summary data for the previous period.
     */
    public function get_previous_period_summary(int $days = 30, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-" . ($days * 2) . " days"));
        $date_to   = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);
        $internal_clause = $this->get_internal_clause();

        $total_clicks = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE clicked_at >= %s AND clicked_at < %s {$bot_clause} {$internal_clause}",
                $date_from,
                $date_to
            )
        );

        $unique_products = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT product_id) FROM {$this->table_name}
                WHERE clicked_at >= %s AND clicked_at < %s {$bot_clause} {$internal_clause}",
                $date_from,
                $date_to
            )
        );

        $mobile_clicks = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE clicked_at >= %s AND clicked_at < %s
                AND device_type IN ('mobile', 'tablet') {$bot_clause} {$internal_clause}",
                $date_from,
                $date_to
            )
        );
        $mobile_percent = $total_clicks > 0 ? round(($mobile_clicks / $total_clicks) * 100) : 0;

        return [
            'total_clicks'    => $total_clicks,
            'unique_products' => $unique_products,
            'mobile_percent'  => $mobile_percent,
        ];
    }

    /**
     * Get product conversion funnel: views, on-page clicks, and CTR per product.
     *
     * CTR is calculated using only clicks whose referrer_path matches the
     * product's own page (by post_name slug). This avoids inflated CTR from
     * clicks originating on listicles, compare pages, etc.
     *
     * A separate total_clicks column shows ALL clicks across all surfaces.
     *
     * @param int  $days         Number of days to look back.
     * @param int  $limit        Maximum results.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @param int  $min_views    Minimum views to include (filters noise).
     * @return array Products with views, page_clicks, total_clicks, and CTR.
     */
    public function get_product_conversion_funnel(int $days = 30, int $limit = 15, bool $exclude_bots = true, int $min_views = 5): array {
        $date_from  = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);
        $internal_clause = $this->get_internal_clause('c.');
        $views_table = $this->wpdb->prefix . ERH_TABLE_PRODUCT_VIEWS;

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    p.ID as product_id,
                    p.post_title as product_name,
                    COALESCE(v.views, 0) as views,
                    COALESCE(pc.page_clicks, 0) as page_clicks,
                    COALESCE(tc.total_clicks, 0) as total_clicks,
                    CASE WHEN COALESCE(v.views, 0) > 0
                        THEN ROUND((COALESCE(pc.page_clicks, 0) / COALESCE(v.views, 0)) * 100, 1)
                        ELSE 0
                    END as ctr
                FROM {$this->wpdb->posts} p
                LEFT JOIN (
                    SELECT product_id, COUNT(*) as views
                    FROM {$views_table}
                    WHERE view_date >= %s
                    GROUP BY product_id
                ) v ON p.ID = v.product_id
                LEFT JOIN (
                    SELECT c.product_id, COUNT(*) as page_clicks
                    FROM {$this->table_name} c
                    INNER JOIN {$this->wpdb->posts} pp ON c.product_id = pp.ID
                    WHERE c.clicked_at >= %s {$bot_clause} {$internal_clause}
                    AND c.referrer_path LIKE CONCAT('%%/', pp.post_name)
                    GROUP BY c.product_id
                ) pc ON p.ID = pc.product_id
                LEFT JOIN (
                    SELECT product_id, COUNT(*) as total_clicks
                    FROM {$this->table_name}
                    WHERE clicked_at >= %s {$bot_clause} AND is_external = 0
                    GROUP BY product_id
                ) tc ON p.ID = tc.product_id
                WHERE p.post_type = 'products'
                AND p.post_status = 'publish'
                AND COALESCE(v.views, 0) >= %d
                ORDER BY ctr DESC, page_clicks DESC
                LIMIT %d",
                $date_from,
                $date_from,
                $date_from,
                $min_views,
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get "leaky bucket" products: high views but low on-page click-through rate.
     *
     * Uses only clicks originating from the product's own page (by slug match)
     * to calculate CTR, consistent with the conversion funnel.
     *
     * @param int  $days         Number of days to look back.
     * @param int  $limit        Maximum results.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @param int  $min_views    Minimum views to qualify.
     * @return array Products sorted by wasted potential.
     */
    public function get_leaky_buckets(int $days = 30, int $limit = 10, bool $exclude_bots = true, int $min_views = 10): array {
        $date_from  = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);
        $internal_clause = $this->get_internal_clause('c.');
        $views_table = $this->wpdb->prefix . ERH_TABLE_PRODUCT_VIEWS;

        // Get average on-page CTR.
        $avg_ctr = $this->get_average_product_ctr($days, $exclude_bots);

        // Get products with high views but below-average on-page CTR.
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    p.ID as product_id,
                    p.post_title as product_name,
                    COALESCE(v.views, 0) as views,
                    COALESCE(pc.page_clicks, 0) as page_clicks,
                    CASE WHEN COALESCE(v.views, 0) > 0
                        THEN ROUND((COALESCE(pc.page_clicks, 0) / COALESCE(v.views, 0)) * 100, 1)
                        ELSE 0
                    END as ctr,
                    ROUND(COALESCE(v.views, 0) * (%f - CASE WHEN COALESCE(v.views, 0) > 0
                        THEN (COALESCE(pc.page_clicks, 0) / COALESCE(v.views, 0)) * 100
                        ELSE 0
                    END) / 100) as missed_clicks
                FROM {$this->wpdb->posts} p
                LEFT JOIN (
                    SELECT product_id, COUNT(*) as views
                    FROM {$views_table}
                    WHERE view_date >= %s
                    GROUP BY product_id
                ) v ON p.ID = v.product_id
                LEFT JOIN (
                    SELECT c.product_id, COUNT(*) as page_clicks
                    FROM {$this->table_name} c
                    INNER JOIN {$this->wpdb->posts} pp ON c.product_id = pp.ID
                    WHERE c.clicked_at >= %s {$bot_clause} {$internal_clause}
                    AND c.referrer_path LIKE CONCAT('%%/', pp.post_name)
                    GROUP BY c.product_id
                ) pc ON p.ID = pc.product_id
                WHERE p.post_type = 'products'
                AND p.post_status = 'publish'
                AND COALESCE(v.views, 0) >= %d
                AND (CASE WHEN COALESCE(v.views, 0) > 0
                    THEN (COALESCE(pc.page_clicks, 0) / COALESCE(v.views, 0)) * 100
                    ELSE 0
                END) < %f
                ORDER BY missed_clicks DESC
                LIMIT %d",
                $avg_ctr,
                $date_from,
                $date_from,
                $min_views,
                $avg_ctr,
                $limit
            ),
            ARRAY_A
        );

        return [
            'avg_ctr'  => round($avg_ctr, 1),
            'products' => $results ?: [],
        ];
    }

    /**
     * Get clicks grouped by content type (derived from referrer path).
     *
     * Classifies referrer paths into content types:
     * - compare: /compare/* paths
     * - review: Single product pages
     * - listicle: /best-* paths, category archive-like paths
     * - tool: /tools/* paths
     * - homepage: / root
     * - other: Everything else
     *
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Content types with click counts and page counts.
     */
    public function get_content_type_clicks(int $days = 30, bool $exclude_bots = true): array {
        $date_from  = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);
        $internal_clause = $this->get_internal_clause();

        // Get all referrer paths with their click counts for classification.
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    referrer_path,
                    COUNT(*) as click_count
                FROM {$this->table_name}
                WHERE clicked_at >= %s
                AND referrer_path IS NOT NULL
                AND referrer_path != ''
                {$bot_clause}
                {$internal_clause}
                GROUP BY referrer_path",
                $date_from
            ),
            ARRAY_A
        );

        if (empty($results)) {
            return [];
        }

        // Strip WordPress subfolder prefix for classification.
        $home_path = wp_parse_url(home_url(), PHP_URL_PATH) ?: '';

        $types = [];
        foreach ($results as $row) {
            $path = $row['referrer_path'];

            // Strip subfolder prefix if present.
            if ($home_path && strpos($path, $home_path) === 0) {
                $path = substr($path, strlen($home_path));
            }
            if (empty($path) || $path[0] !== '/') {
                $path = '/' . $path;
            }

            $type = $this->classify_content_type($path);
            if (!isset($types[$type])) {
                $types[$type] = ['clicks' => 0, 'pages' => 0];
            }
            $types[$type]['clicks'] += (int) $row['click_count'];
            $types[$type]['pages']++;
        }

        // Sort by clicks descending.
        uasort($types, fn($a, $b) => $b['clicks'] <=> $a['clicks']);

        // Format output.
        $output = [];
        foreach ($types as $type => $data) {
            $output[] = [
                'type'           => $type,
                'label'          => $this->get_content_type_label($type),
                'clicks'         => $data['clicks'],
                'pages'          => $data['pages'],
                'clicks_per_page' => $data['pages'] > 0 ? round($data['clicks'] / $data['pages'], 1) : 0,
            ];
        }

        return $output;
    }

    /**
     * Classify a referrer path into a content type.
     *
     * @param string $path The normalized path (without subfolder prefix).
     * @return string Content type key.
     */
    private function classify_content_type(string $path): string {
        $path = rtrim($path, '/');

        if ($path === '' || $path === '/') {
            return 'homepage';
        }

        if (strpos($path, '/compare') === 0) {
            return 'compare';
        }

        if (strpos($path, '/tools') === 0) {
            return 'tool';
        }

        if (strpos($path, '/deals') === 0) {
            return 'deals';
        }

        if (strpos($path, '/finder') === 0) {
            return 'finder';
        }

        // Listicle patterns: /best-*, /top-*, category-level pages.
        if (preg_match('#^/(best|top|cheapest|fastest|lightest|most|longest|affordable|safest)-#', $path)) {
            return 'listicle';
        }

        // Category archive pages (with optional /page/N suffix).
        if (preg_match('#^/(e-scooters|e-bikes|electric-skateboards|electric-unicycles|hoverboards)(/page/\d+)?$#', $path)) {
            return 'category';
        }

        // Single product pages (most other paths with a slug).
        // These are review/product detail pages.
        return 'review';
    }

    /**
     * Get human-readable label for a content type.
     *
     * @param string $type Content type key.
     * @return string Label.
     */
    private function get_content_type_label(string $type): string {
        $labels = [
            'review'   => 'Reviews',
            'compare'  => 'Compare Pages',
            'listicle' => 'Listicles',
            'tool'     => 'Tools',
            'deals'    => 'Deals Pages',
            'finder'   => 'Finder Tool',
            'category' => 'Category Pages',
            'homepage' => 'Homepage',
            'other'    => 'Other',
        ];
        return $labels[$type] ?? ucfirst($type);
    }

    /**
     * Get retailer preference data for products with multiple retailers.
     *
     * Shows which retailers users prefer when they have choices,
     * indicating brand trust and price sensitivity.
     *
     * @param int  $days         Number of days to look back.
     * @param int  $limit        Maximum retailer results.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Retailer preference data with win rates.
     */
    public function get_retailer_preference(int $days = 30, int $limit = 10, bool $exclude_bots = true): array {
        $date_from  = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);
        $tracked_links_table = $this->wpdb->prefix . 'hft_tracked_links';
        $scrapers_table      = $this->wpdb->prefix . 'hft_scrapers';

        // Get click distribution across retailers for products that have 2+ retailers.
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    COALESCE(s.name, tl.parser_identifier, 'Unknown') as retailer,
                    COUNT(*) as clicks,
                    COUNT(DISTINCT c.product_id) as products
                FROM {$this->table_name} c
                INNER JOIN {$tracked_links_table} tl ON c.tracked_link_id = tl.id
                LEFT JOIN {$scrapers_table} s ON tl.scraper_id = s.id
                WHERE c.clicked_at >= %s
                AND c.product_id IN (
                    SELECT product_id FROM (
                        SELECT product_id, COUNT(DISTINCT tracked_link_id) as retailer_count
                        FROM {$this->table_name}
                        WHERE clicked_at >= %s {$bot_clause}
                        GROUP BY product_id
                        HAVING retailer_count >= 2
                    ) multi
                )
                {$bot_clause}
                GROUP BY retailer
                ORDER BY clicks DESC
                LIMIT %d",
                $date_from,
                $date_from,
                $limit
            ),
            ARRAY_A
        );

        if (empty($results)) {
            return [];
        }

        // Calculate total clicks to derive share percentages.
        $total = array_sum(array_column($results, 'clicks'));

        return array_map(function (array $row) use ($total): array {
            $row['clicks']   = (int) $row['clicks'];
            $row['products'] = (int) $row['products'];
            $row['share']    = $total > 0 ? round(($row['clicks'] / $total) * 100, 1) : 0;
            return $row;
        }, $results);
    }

    /**
     * Get average on-page product CTR across all products with views.
     *
     * Only counts clicks whose referrer_path matches the product's own
     * page slug, giving a true product page conversion rate.
     *
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return float Average CTR percentage.
     */
    public function get_average_product_ctr(int $days = 30, bool $exclude_bots = true): float {
        $date_from  = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);
        $internal_clause = $this->get_internal_clause('c.');
        $views_table = $this->wpdb->prefix . ERH_TABLE_PRODUCT_VIEWS;

        $result = (float) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT
                    CASE WHEN SUM(v.views) > 0
                        THEN (SUM(COALESCE(pc.page_clicks, 0)) / SUM(v.views)) * 100
                        ELSE 0
                    END
                FROM (
                    SELECT product_id, COUNT(*) as views
                    FROM {$views_table}
                    WHERE view_date >= %s
                    GROUP BY product_id
                ) v
                LEFT JOIN (
                    SELECT c.product_id, COUNT(*) as page_clicks
                    FROM {$this->table_name} c
                    INNER JOIN {$this->wpdb->posts} pp ON c.product_id = pp.ID
                    WHERE c.clicked_at >= %s {$bot_clause} {$internal_clause}
                    AND c.referrer_path LIKE CONCAT('%%/', pp.post_name)
                    GROUP BY c.product_id
                ) pc ON v.product_id = pc.product_id",
                $date_from,
                $date_from
            )
        );

        return round($result, 1);
    }

    /**
     * Get click velocity (average daily clicks) for current and previous period.
     *
     * @param int  $days         Period length in days.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Current and previous velocity.
     */
    public function get_click_velocity(int $days = 30, bool $exclude_bots = true): array {
        $date_from_current  = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $date_from_previous = gmdate('Y-m-d H:i:s', strtotime("-" . ($days * 2) . " days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        $current = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE clicked_at >= %s {$bot_clause}",
                $date_from_current
            )
        );

        $previous = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE clicked_at >= %s AND clicked_at < %s {$bot_clause}",
                $date_from_previous,
                $date_from_current
            )
        );

        return [
            'current_daily_avg'  => $days > 0 ? round($current / $days, 1) : 0,
            'previous_daily_avg' => $days > 0 ? round($previous / $days, 1) : 0,
        ];
    }

    /**
     * Get external traffic sources (clicks from outside our site).
     *
     * @param int  $days         Number of days to look back.
     * @param int  $limit        Maximum results.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array External sources with click counts.
     */
    public function get_external_traffic_sources(int $days = 30, int $limit = 15, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    COALESCE(referrer_domain, 'unknown') as domain,
                    COUNT(*) as clicks,
                    COUNT(DISTINCT product_id) as unique_products
                FROM {$this->table_name}
                WHERE clicked_at >= %s
                AND is_external = 1
                {$bot_clause}
                GROUP BY domain
                ORDER BY clicks DESC
                LIMIT %d",
                $date_from,
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get direct traffic count (no referrer, not external).
     *
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Direct traffic stats.
     */
    public function get_direct_traffic_count(int $days = 30, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        $count = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE clicked_at >= %s
                AND referrer_path IS NULL
                AND is_external = 0
                {$bot_clause}",
                $date_from
            )
        );

        return ['clicks' => $count];
    }

    /**
     * Get clicks grouped by click source (UI placement).
     *
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Click sources with counts.
     */
    public function get_clicks_by_source(int $days = 30, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);
        $internal_clause = $this->get_internal_clause();

        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    COALESCE(click_source, 'unknown') as source,
                    COUNT(*) as clicks
                FROM {$this->table_name}
                WHERE clicked_at >= %s
                {$bot_clause}
                {$internal_clause}
                GROUP BY source
                ORDER BY clicks DESC",
                $date_from
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get total external + direct click count for the summary card.
     *
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array External and direct counts.
     */
    public function get_external_summary(int $days = 30, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        $external = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE clicked_at >= %s AND is_external = 1 {$bot_clause}",
                $date_from
            )
        );

        $total = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE clicked_at >= %s {$bot_clause}",
                $date_from
            )
        );

        return [
            'external_clicks' => $external,
            'total_clicks'    => $total,
            'external_percent' => $total > 0 ? round(($external / $total) * 100, 1) : 0,
        ];
    }
}
