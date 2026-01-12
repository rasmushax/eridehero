<?php
/**
 * View Tracker - CRUD operations for wp_product_views table.
 *
 * @package ERH\Database
 */

declare(strict_types=1);

namespace ERH\Database;

/**
 * Handles CRUD operations for the product_views table.
 * This table stores product view tracking data for analytics.
 */
class ViewTracker {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Table name with prefix.
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
        $this->table_name = $wpdb->prefix . ERH_TABLE_PRODUCT_VIEWS;
    }

    /**
     * Record a product view.
     *
     * @param int    $product_id The product post ID.
     * @param string $ip_address The visitor IP address (will be hashed).
     * @param string $user_agent The visitor user agent.
     * @return bool True on success, false if duplicate or bot.
     */
    public function record_view(int $product_id, string $ip_address, string $user_agent = ''): bool {
        // Skip bots.
        if ($this->is_bot($user_agent)) {
            return false;
        }

        // Hash the IP for privacy.
        $ip_hash = $this->hash_ip($ip_address);

        // Check for duplicate view today.
        if ($this->has_viewed_today($product_id, $ip_hash)) {
            return false;
        }

        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'product_id' => $product_id,
                'ip_hash'    => $ip_hash,
                'user_agent' => substr($user_agent, 0, 255),
                'view_date'  => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Check if IP has already viewed product today.
     *
     * @param int    $product_id The product post ID.
     * @param string $ip_hash    The hashed IP address.
     * @return bool True if already viewed today.
     */
    public function has_viewed_today(int $product_id, string $ip_hash): bool {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                 WHERE product_id = %d
                 AND ip_hash = %s
                 AND DATE(view_date) = CURDATE()",
                $product_id,
                $ip_hash
            )
        );

        return (int)$count > 0;
    }

    /**
     * Get view count for a product.
     *
     * @param int $product_id The product post ID.
     * @param int $days       Number of days to count. 0 for all time.
     * @return int View count.
     */
    public function get_view_count(int $product_id, int $days = 0): int {
        if ($days > 0) {
            $count = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name}
                     WHERE product_id = %d
                     AND view_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $product_id,
                    $days
                )
            );
        } else {
            $count = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name}
                     WHERE product_id = %d",
                    $product_id
                )
            );
        }

        return (int)$count;
    }

    /**
     * Get view counts for multiple products.
     *
     * @param array<int> $product_ids Array of product post IDs.
     * @param int        $days        Number of days to count.
     * @return array<int, int> View counts indexed by product ID.
     */
    public function get_view_counts_bulk(array $product_ids, int $days = 30): array {
        if (empty($product_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));

        $sql = "SELECT product_id, COUNT(*) as views
                FROM {$this->table_name}
                WHERE product_id IN ({$placeholders})";

        $params = $product_ids;

        if ($days > 0) {
            $sql .= " AND view_date >= DATE_SUB(NOW(), INTERVAL %d DAY)";
            $params[] = $days;
        }

        $sql .= " GROUP BY product_id";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );

        $counts = [];
        foreach ($results as $row) {
            $counts[(int)$row['product_id']] = (int)$row['views'];
        }

        // Ensure all requested products have a count (0 if not found).
        foreach ($product_ids as $id) {
            if (!isset($counts[$id])) {
                $counts[$id] = 0;
            }
        }

        return $counts;
    }

    /**
     * Get most viewed products.
     *
     * @param int         $limit        Number of products to return.
     * @param int         $days         Number of days to consider.
     * @param string|null $product_type Optional product type filter.
     * @return array<int, array<string, mixed>> Product IDs with view counts.
     */
    public function get_most_viewed(int $limit = 10, int $days = 30, ?string $product_type = null): array {
        $product_data_table = $this->wpdb->prefix . ERH_TABLE_PRODUCT_DATA;

        $sql = "SELECT v.product_id, COUNT(*) as views
                FROM {$this->table_name} v";

        $params = [];

        if ($product_type !== null) {
            $sql .= " INNER JOIN {$product_data_table} pd ON v.product_id = pd.product_id
                      WHERE pd.product_type = %s";
            $params[] = $product_type;

            if ($days > 0) {
                $sql .= " AND v.view_date >= DATE_SUB(NOW(), INTERVAL %d DAY)";
                $params[] = $days;
            }
        } else {
            if ($days > 0) {
                $sql .= " WHERE v.view_date >= DATE_SUB(NOW(), INTERVAL %d DAY)";
                $params[] = $days;
            }
        }

        $sql .= " GROUP BY v.product_id
                  ORDER BY views DESC
                  LIMIT %d";
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );

        return array_map(function ($row) {
            return [
                'product_id' => (int)$row['product_id'],
                'views'      => (int)$row['views'],
            ];
        }, $results ?: []);
    }

    /**
     * Get daily view statistics for a product.
     *
     * @param int $product_id The product post ID.
     * @param int $days       Number of days of history.
     * @return array<int, array<string, mixed>> Daily view counts.
     */
    public function get_daily_stats(int $product_id, int $days = 30): array {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DATE(view_date) as date, COUNT(*) as views
                 FROM {$this->table_name}
                 WHERE product_id = %d
                 AND view_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
                 GROUP BY DATE(view_date)
                 ORDER BY date ASC",
                $product_id,
                $days
            ),
            ARRAY_A
        );

        return array_map(function ($row) {
            return [
                'date'  => $row['date'],
                'views' => (int)$row['views'],
            ];
        }, $results ?: []);
    }

    /**
     * Clean up old view records.
     * Uses probabilistic cleanup to avoid running on every request.
     *
     * @param int $days_to_keep Number of days of data to keep.
     * @param int $probability  1 in N chance of running (e.g., 100 = 1% chance).
     * @return int Number of rows deleted (0 if cleanup didn't run).
     */
    public function maybe_cleanup(int $days_to_keep = 90, int $probability = 100): int {
        // Probabilistic cleanup.
        if (wp_rand(1, $probability) !== 1) {
            return 0;
        }

        return $this->cleanup($days_to_keep);
    }

    /**
     * Force cleanup of old view records.
     *
     * @param int $days_to_keep Number of days of data to keep.
     * @return int Number of rows deleted.
     */
    public function cleanup(int $days_to_keep = 90): int {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name}
                 WHERE view_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_to_keep
            )
        );

        return $result !== false ? (int)$result : 0;
    }

    /**
     * Delete all views for a product.
     *
     * @param int $product_id The product post ID.
     * @return bool True on success.
     */
    public function delete_for_product(int $product_id): bool {
        $result = $this->wpdb->delete(
            $this->table_name,
            ['product_id' => $product_id],
            ['%d']
        );
        return $result !== false;
    }

    /**
     * Hash an IP address for privacy.
     *
     * @param string $ip_address The IP address.
     * @return string The hashed IP.
     */
    private function hash_ip(string $ip_address): string {
        // Use a daily salt to prevent long-term tracking.
        $salt = wp_salt('auth') . date('Y-m-d');
        return hash('sha256', $ip_address . $salt);
    }

    /**
     * Check if user agent appears to be a bot.
     *
     * @param string $user_agent The user agent string.
     * @return bool True if likely a bot.
     */
    private function is_bot(string $user_agent): bool {
        if (empty($user_agent)) {
            return true;
        }

        $bot_patterns = [
            'bot',
            'crawl',
            'spider',
            'slurp',
            'googlebot',
            'bingbot',
            'yandex',
            'baidu',
            'duckduck',
            'facebookexternalhit',
            'twitterbot',
            'linkedinbot',
            'whatsapp',
            'telegram',
            'pinterest',
            'semrush',
            'ahrefsbot',
            'mj12bot',
            'dotbot',
            'petalbot',
            'bytespider',
            'headlesschrome',
            'phantomjs',
            'selenium',
            'puppeteer',
            'wget',
            'curl',
            'python-requests',
            'go-http-client',
            'java/',
            'apache-httpclient',
        ];

        $user_agent_lower = strtolower($user_agent);

        foreach ($bot_patterns as $pattern) {
            if (strpos($user_agent_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
