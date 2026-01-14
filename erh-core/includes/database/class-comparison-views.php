<?php
/**
 * Comparison Views - CRUD operations for wp_comparison_views table.
 *
 * Tracks view counts for product comparison pairs to enable
 * "popular comparisons" functionality.
 *
 * @package ERH\Database
 */

declare(strict_types=1);

namespace ERH\Database;

/**
 * Handles CRUD operations for the comparison_views table.
 * This table stores comparison view tracking data for popularity rankings.
 */
class ComparisonViews {

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
     * Bot patterns for filtering.
     *
     * @var array<string>
     */
    private array $bot_patterns = [
        'googlebot',
        'bingbot',
        'yandex',
        'baiduspider',
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
        'bot',
        'crawl',
        'spider',
        'slurp',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . ERH_TABLE_COMPARISON_VIEWS;
    }

    /**
     * Record a comparison view.
     *
     * For multi-product comparisons (3-4 products), this extracts all
     * unique pairs and records each one.
     *
     * @param array<int> $product_ids Array of product IDs being compared.
     * @param string     $user_agent  The visitor user agent (for bot filtering).
     * @return int Number of pairs tracked.
     */
    public function record_view(array $product_ids, string $user_agent = ''): int {
        // Filter to valid product IDs.
        $product_ids = array_filter(array_map('absint', $product_ids));

        if (count($product_ids) < 2) {
            return 0;
        }

        // Skip bots.
        if ($this->is_bot($user_agent)) {
            return 0;
        }

        // Extract all unique pairs.
        $pairs = $this->extract_pairs($product_ids);
        $tracked = 0;

        foreach ($pairs as $pair) {
            if ($this->upsert_pair($pair[0], $pair[1])) {
                $tracked++;
            }
        }

        return $tracked;
    }

    /**
     * Upsert a single comparison pair.
     *
     * @param int $product_1_id First product ID.
     * @param int $product_2_id Second product ID.
     * @return bool True on success.
     */
    public function upsert_pair(int $product_1_id, int $product_2_id): bool {
        // Normalize: always store with lower ID first.
        $ids = [$product_1_id, $product_2_id];
        sort($ids);

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "INSERT INTO {$this->table_name}
                 (product_1_id, product_2_id, view_count, last_viewed)
                 VALUES (%d, %d, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                 view_count = view_count + 1,
                 last_viewed = NOW()",
                $ids[0],
                $ids[1]
            )
        );

        return $result !== false;
    }

    /**
     * Get popular comparison pairs.
     *
     * Uses time-weighted formula: views * (1 - days_since_last_view / 90)
     * Comparisons older than 90 days get weight approaching 0.
     *
     * @param string|null $category Optional category filter (escooter, ebike, etc.).
     * @param int         $limit    Maximum results.
     * @return array<array<string, mixed>> Popular pairs with product data.
     */
    public function get_popular(?string $category = null, int $limit = 10): array {
        $posts_table = $this->wpdb->posts;

        $sql = "
            SELECT
                v.product_1_id,
                v.product_2_id,
                v.view_count,
                v.last_viewed,
                p1.post_title AS product_1_name,
                p2.post_title AS product_2_name,
                (v.view_count * GREATEST(0, 1 - DATEDIFF(NOW(), v.last_viewed) / 90)) AS weighted_score
            FROM {$this->table_name} v
            INNER JOIN {$posts_table} p1 ON v.product_1_id = p1.ID
            INNER JOIN {$posts_table} p2 ON v.product_2_id = p2.ID
            WHERE p1.post_status = 'publish'
            AND p2.post_status = 'publish'
        ";

        $params = [];

        if ($category) {
            // Join with term_relationships to filter by product_type taxonomy.
            $term_slug = $this->get_taxonomy_slug_from_category($category);
            $sql = "
                SELECT
                    v.product_1_id,
                    v.product_2_id,
                    v.view_count,
                    v.last_viewed,
                    p1.post_title AS product_1_name,
                    p2.post_title AS product_2_name,
                    (v.view_count * GREATEST(0, 1 - DATEDIFF(NOW(), v.last_viewed) / 90)) AS weighted_score
                FROM {$this->table_name} v
                INNER JOIN {$posts_table} p1 ON v.product_1_id = p1.ID
                INNER JOIN {$posts_table} p2 ON v.product_2_id = p2.ID
                INNER JOIN {$this->wpdb->term_relationships} tr ON p1.ID = tr.object_id
                INNER JOIN {$this->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$this->wpdb->terms} t ON tt.term_id = t.term_id
                WHERE p1.post_status = 'publish'
                AND p2.post_status = 'publish'
                AND tt.taxonomy = 'product_type'
                AND t.slug = %s
            ";
            $params[] = $term_slug;
        }

        $sql .= " ORDER BY weighted_score DESC LIMIT %d";
        $params[] = $limit;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Get view count for a specific pair.
     *
     * @param int $product_1_id First product ID.
     * @param int $product_2_id Second product ID.
     * @return int View count.
     */
    public function get_pair_views(int $product_1_id, int $product_2_id): int {
        // Normalize: always check with lower ID first.
        $ids = [$product_1_id, $product_2_id];
        sort($ids);

        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT view_count FROM {$this->table_name}
                 WHERE product_1_id = %d AND product_2_id = %d",
                $ids[0],
                $ids[1]
            )
        );

        return (int) ($count ?? 0);
    }

    /**
     * Get all comparison pairs for a product.
     *
     * @param int $product_id The product ID.
     * @param int $limit      Maximum results.
     * @return array<array<string, mixed>> Comparison pairs.
     */
    public function get_comparisons_for_product(int $product_id, int $limit = 20): array {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT
                    CASE WHEN product_1_id = %d THEN product_2_id ELSE product_1_id END AS other_product_id,
                    view_count,
                    last_viewed
                 FROM {$this->table_name}
                 WHERE product_1_id = %d OR product_2_id = %d
                 ORDER BY view_count DESC
                 LIMIT %d",
                $product_id,
                $product_id,
                $product_id,
                $limit
            ),
            ARRAY_A
        );

        return $results ?: [];
    }

    /**
     * Delete all comparison data for a product.
     *
     * Called when a product is deleted.
     *
     * @param int $product_id The product ID.
     * @return int Number of rows deleted.
     */
    public function delete_for_product(int $product_id): int {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name}
                 WHERE product_1_id = %d OR product_2_id = %d",
                $product_id,
                $product_id
            )
        );

        return $result !== false ? (int) $result : 0;
    }

    /**
     * Get total view count across all comparisons.
     *
     * @return int Total views.
     */
    public function get_total_views(): int {
        $total = $this->wpdb->get_var(
            "SELECT SUM(view_count) FROM {$this->table_name}"
        );

        return (int) ($total ?? 0);
    }

    /**
     * Get count of unique comparison pairs.
     *
     * @return int Number of unique pairs.
     */
    public function get_pair_count(): int {
        $count = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->table_name}"
        );

        return (int) ($count ?? 0);
    }

    /**
     * Check if a curated comparison exists for a pair.
     *
     * @param int $product_1_id First product ID.
     * @param int $product_2_id Second product ID.
     * @return int|null Comparison post ID or null.
     */
    public function get_curated_comparison(int $product_1_id, int $product_2_id): ?int {
        // Check both orderings.
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT p.ID
                 FROM {$this->wpdb->posts} p
                 INNER JOIN {$this->wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'product_1'
                 INNER JOIN {$this->wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'product_2'
                 WHERE p.post_type = 'comparison'
                 AND p.post_status = 'publish'
                 AND (
                     (pm1.meta_value = %d AND pm2.meta_value = %d)
                     OR (pm1.meta_value = %d AND pm2.meta_value = %d)
                 )
                 LIMIT 1",
                $product_1_id,
                $product_2_id,
                $product_2_id,
                $product_1_id
            )
        );

        return $result ? (int) $result : null;
    }

    /**
     * Extract all unique pairs from an array of product IDs.
     *
     * For [A, B, C], returns [[A,B], [A,C], [B,C]].
     *
     * @param array<int> $product_ids Array of product IDs.
     * @return array<array{0: int, 1: int}> Array of pairs.
     */
    private function extract_pairs(array $product_ids): array {
        $pairs = [];
        $count = count($product_ids);

        for ($i = 0; $i < $count - 1; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $pairs[] = [$product_ids[$i], $product_ids[$j]];
            }
        }

        return $pairs;
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

        $user_agent_lower = strtolower($user_agent);

        foreach ($this->bot_patterns as $pattern) {
            if (strpos($user_agent_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get product_type taxonomy slug from category key.
     *
     * @param string $category Category key (escooter, ebike, etc.).
     * @return string Taxonomy term slug (electric-scooter, etc.).
     */
    private function get_taxonomy_slug_from_category(string $category): string {
        $map = [
            'escooter'    => 'electric-scooter',
            'ebike'       => 'electric-bike',
            'eskateboard' => 'electric-skateboard',
            'euc'         => 'electric-unicycle',
            'hoverboard'  => 'hoverboard',
        ];

        return $map[$category] ?? 'electric-scooter';
    }
}
