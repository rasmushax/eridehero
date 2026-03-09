<?php
/**
 * Page View Tracker - CRUD operations for wp_erh_page_views table.
 *
 * Tracks non-product page views (listicles, compare, deals, etc.)
 * for CTR calculations in the click stats dashboard.
 *
 * @package ERH\Database
 */

declare(strict_types=1);

namespace ERH\Database;

/**
 * Handles CRUD operations for the page_views table.
 */
class PageViewTracker {

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
        $this->table_name = $wpdb->prefix . ERH_TABLE_PAGE_VIEWS;
    }

    /**
     * Record a page view.
     *
     * @param string $page_path The page path (e.g., /best-electric-scooters).
     * @param string $page_type The page type (e.g., listicle, compare).
     * @param string $ip        The visitor IP address (will be hashed).
     * @param string $ua        The visitor user agent.
     * @return bool True on success, false if duplicate or bot.
     */
    public function record_view(string $page_path, string $page_type, string $ip, string $ua): bool {
        // Skip bots.
        if ($this->is_bot($ua)) {
            return false;
        }

        // Hash the IP for privacy (daily salt).
        $ip_hash = $this->hash_ip($ip);

        // Deduplicate: one view per path per IP per day.
        if ($this->has_viewed_today($page_path, $ip_hash)) {
            return false;
        }

        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'page_path' => substr($page_path, 0, 255),
                'page_type' => substr($page_type, 0, 30),
                'ip_hash'   => $ip_hash,
                'view_date' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Check if IP has already viewed this path today.
     *
     * @param string $page_path The page path.
     * @param string $ip_hash   The hashed IP.
     * @return bool True if already viewed today.
     */
    public function has_viewed_today(string $page_path, string $ip_hash): bool {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                 WHERE page_path = %s
                 AND ip_hash = %s
                 AND DATE(view_date) = CURDATE()",
                $page_path,
                $ip_hash
            )
        );

        return (int) $count > 0;
    }

    /**
     * Get view count for a specific page path.
     *
     * @param string $page_path The page path.
     * @param int    $days      Number of days to look back.
     * @return int View count.
     */
    public function get_view_count(string $page_path, int $days = 30): int {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                 WHERE page_path = %s
                 AND view_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $page_path,
                $days
            )
        );

        return (int) $count;
    }

    /**
     * Get view summary by page type.
     *
     * @param int $days Number of days to look back.
     * @return array Array of [{type, views, unique_paths}].
     */
    public function get_type_summary(int $days = 30): array {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    page_type as type,
                    COUNT(*) as views,
                    COUNT(DISTINCT page_path) as unique_paths
                FROM {$this->table_name}
                WHERE view_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY page_type
                ORDER BY views DESC",
                $days
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get total views for a page type.
     *
     * @param string $type The page type.
     * @param int    $days Number of days to look back.
     * @return int Total views.
     */
    public function get_views_by_type(string $type, int $days = 30): int {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name}
                 WHERE page_type = %s
                 AND view_date >= DATE_SUB(NOW(), INTERVAL %d DAY)",
                $type,
                $days
            )
        );

        return (int) $count;
    }

    /**
     * Hash an IP address for privacy (daily salt).
     *
     * @param string $ip The IP address.
     * @return string The hashed IP.
     */
    private function hash_ip(string $ip): string {
        $salt = wp_salt('auth') . date('Y-m-d');
        return hash('sha256', $ip . $salt);
    }

    /**
     * Check if user agent appears to be a bot.
     *
     * @param string $ua The user agent string.
     * @return bool True if likely a bot.
     */
    private function is_bot(string $ua): bool {
        if (empty($ua)) {
            return true;
        }

        $bot_patterns = [
            'bot', 'crawl', 'spider', 'slurp', 'googlebot', 'bingbot',
            'yandex', 'baidu', 'duckduck', 'facebookexternalhit',
            'twitterbot', 'linkedinbot', 'whatsapp', 'telegram',
            'pinterest', 'semrush', 'ahrefsbot', 'mj12bot', 'dotbot',
            'petalbot', 'bytespider', 'headlesschrome', 'phantomjs',
            'selenium', 'puppeteer', 'wget', 'curl', 'python-requests',
            'go-http-client', 'java/', 'apache-httpclient',
        ];

        $ua_lower = strtolower($ua);

        foreach ($bot_patterns as $pattern) {
            if (strpos($ua_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
