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
     * Get summary statistics.
     *
     * @param int  $days         Number of days to look back.
     * @param bool $exclude_bots Whether to exclude bot traffic.
     * @return array Summary data.
     */
    public function get_summary(int $days = 30, bool $exclude_bots = true): array {
        $date_from = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        $bot_clause = $this->get_bot_clause($exclude_bots);

        // Total clicks.
        $total_clicks = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE clicked_at >= %s {$bot_clause}",
                $date_from
            )
        );

        // Unique products clicked.
        $unique_products = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT product_id) FROM {$this->table_name} WHERE clicked_at >= %s {$bot_clause}",
                $date_from
            )
        );

        // Mobile percentage.
        $mobile_clicks = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE clicked_at >= %s AND device_type IN ('mobile', 'tablet') {$bot_clause}",
                $date_from
            )
        );
        $mobile_percent = $total_clicks > 0 ? round(($mobile_clicks / $total_clicks) * 100) : 0;

        // Unique referrer pages.
        $unique_pages = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT referrer_path) FROM {$this->table_name}
                WHERE clicked_at >= %s AND referrer_path IS NOT NULL {$bot_clause}",
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
}
