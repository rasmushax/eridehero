<?php
/**
 * Product Cache - CRUD operations for wp_product_data table.
 *
 * @package ERH\Database
 */

declare(strict_types=1);

namespace ERH\Database;

/**
 * Handles CRUD operations for the product_data cache table.
 * This table stores pre-computed product data for the finder tool.
 */
class ProductCache {

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
        $this->table_name = $wpdb->prefix . ERH_TABLE_PRODUCT_DATA;
    }

    /**
     * Get a single product from the cache.
     *
     * @param int $product_id The product post ID.
     * @return array<string, mixed>|null Product data or null if not found.
     */
    public function get(int $product_id): ?array {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE product_id = %d",
                $product_id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return $this->unserialize_row($row);
    }

    /**
     * Get all products from the cache.
     *
     * @param string|null $product_type Optional filter by product type.
     * @return array<int, array<string, mixed>> Array of product data.
     */
    public function get_all(?string $product_type = null): array {
        if ($product_type !== null) {
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE product_type = %s",
                    $product_type
                ),
                ARRAY_A
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $results = $this->wpdb->get_results(
                "SELECT * FROM {$this->table_name}",
                ARRAY_A
            );
        }

        return array_map([$this, 'unserialize_row'], $results ?: []);
    }

    /**
     * Get products with filters for the finder tool.
     *
     * Note: Price and instock filters now require a geo parameter since
     * these values are stored per-geo in price_history. Filtering happens
     * in PHP after fetching results.
     *
     * @param array<string, mixed> $filters Filter criteria.
     * @param string               $orderby Column to order by.
     * @param string               $order   ASC or DESC.
     * @param int                  $limit   Number of results.
     * @param int                  $offset  Offset for pagination.
     * @return array<int, array<string, mixed>> Matching products.
     */
    public function get_filtered(
        array $filters = [],
        string $orderby = 'popularity_score',
        string $order = 'DESC',
        int $limit = 50,
        int $offset = 0
    ): array {
        $where_clauses = [];
        $params = [];

        // Product type filter.
        if (!empty($filters['product_type'])) {
            $where_clauses[] = 'product_type = %s';
            $params[] = $filters['product_type'];
        }

        // Rating filter.
        if (!empty($filters['min_rating'])) {
            $where_clauses[] = 'rating >= %f';
            $params[] = (float)$filters['min_rating'];
        }

        // Build WHERE clause.
        $where = '';
        if (!empty($where_clauses)) {
            $where = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        // Validate orderby column (price removed - now geo-dependent).
        $allowed_orderby = ['popularity_score', 'rating', 'name', 'last_updated'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'popularity_score';
        }

        // Validate order direction.
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // Build query - fetch more than needed if geo filtering required.
        $geo = $filters['geo'] ?? null;
        $needs_geo_filter = $geo && (
            isset($filters['instock']) ||
            !empty($filters['min_price']) ||
            !empty($filters['max_price'])
        );

        $fetch_limit = $needs_geo_filter ? $limit * 3 : $limit;

        $sql = "SELECT * FROM {$this->table_name} {$where}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";

        $params[] = $fetch_limit;
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare($sql, $params),
            ARRAY_A
        );

        $products = array_map([$this, 'unserialize_row'], $results ?: []);

        // Apply geo-dependent filters in PHP.
        if ($needs_geo_filter) {
            $products = $this->apply_geo_filters($products, $filters, $geo);
        }

        return array_slice($products, 0, $limit);
    }

    /**
     * Apply geo-dependent filters to products.
     *
     * @param array<int, array<string, mixed>> $products Products to filter.
     * @param array<string, mixed>             $filters  Filter criteria.
     * @param string                           $geo      Geo code.
     * @return array<int, array<string, mixed>> Filtered products.
     */
    private function apply_geo_filters(array $products, array $filters, string $geo): array {
        return array_filter($products, function ($product) use ($filters, $geo) {
            $geo_data = $product['price_history'][$geo] ?? null;

            // Instock filter.
            if (isset($filters['instock']) && $filters['instock']) {
                if (!$geo_data || empty($geo_data['instock'])) {
                    return false;
                }
            }

            // Price range filters.
            if (!empty($filters['min_price'])) {
                $price = $geo_data['current_price'] ?? 0;
                if ($price < (float)$filters['min_price']) {
                    return false;
                }
            }
            if (!empty($filters['max_price'])) {
                $price = $geo_data['current_price'] ?? PHP_INT_MAX;
                if ($price > (float)$filters['max_price']) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Count products matching filters.
     *
     * Note: For geo-dependent filters (price, instock), this returns
     * an estimate based on non-geo filters. Use get_filtered() for
     * accurate counts with geo filters.
     *
     * @param array<string, mixed> $filters Filter criteria.
     * @return int Count of matching products.
     */
    public function count_filtered(array $filters = []): int {
        $where_clauses = [];
        $params = [];

        if (!empty($filters['product_type'])) {
            $where_clauses[] = 'product_type = %s';
            $params[] = $filters['product_type'];
        }

        if (!empty($filters['min_rating'])) {
            $where_clauses[] = 'rating >= %f';
            $params[] = (float)$filters['min_rating'];
        }

        $where = '';
        if (!empty($where_clauses)) {
            $where = 'WHERE ' . implode(' AND ', $where_clauses);
        }

        $sql = "SELECT COUNT(*) FROM {$this->table_name} {$where}";

        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $count = $this->wpdb->get_var($this->wpdb->prepare($sql, $params));
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $count = $this->wpdb->get_var($sql);
        }

        return (int)$count;
    }

    /**
     * Insert or update a product in the cache.
     *
     * Note: price_history should contain geo-keyed pricing data:
     * [
     *     'US' => [
     *         'current_price' => 499.99,
     *         'currency' => 'USD',
     *         'avg_price_6m' => 599.99,
     *         'discount_percent' => -16.7,
     *         'is_deal' => true,
     *         'instock' => true,
     *         'retailer' => 'Amazon',
     *     ],
     *     'GB' => [...],
     * ]
     *
     * @param array<string, mixed> $data Product data.
     * @return bool True on success.
     */
    public function upsert(array $data): bool {
        $product_id = (int)($data['product_id'] ?? 0);
        if ($product_id <= 0) {
            return false;
        }

        $existing = $this->get($product_id);

        $row = [
            'product_id'       => $product_id,
            'product_type'     => $data['product_type'] ?? '',
            'name'             => $data['name'] ?? '',
            'specs'            => maybe_serialize($data['specs'] ?? []),
            'rating'           => $data['rating'] ?? null,
            'popularity_score' => $data['popularity_score'] ?? 0,
            'permalink'        => $data['permalink'] ?? '',
            'image_url'        => $data['image_url'] ?? '',
            'price_history'    => maybe_serialize($data['price_history'] ?? []),
            'last_updated'     => current_time('mysql'),
        ];

        $formats = ['%d', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%s', '%s'];

        if ($existing) {
            // Update.
            $result = $this->wpdb->update(
                $this->table_name,
                $row,
                ['product_id' => $product_id],
                $formats,
                ['%d']
            );
            return $result !== false;
        } else {
            // Insert.
            $result = $this->wpdb->insert($this->table_name, $row, $formats);
            return $result !== false;
        }
    }

    /**
     * Delete a product from the cache.
     *
     * @param int $product_id The product post ID.
     * @return bool True on success.
     */
    public function delete(int $product_id): bool {
        $result = $this->wpdb->delete(
            $this->table_name,
            ['product_id' => $product_id],
            ['%d']
        );
        return $result !== false;
    }

    /**
     * Delete all products of a specific type.
     *
     * @param string $product_type The product type.
     * @return int Number of rows deleted.
     */
    public function delete_by_type(string $product_type): int {
        $result = $this->wpdb->delete(
            $this->table_name,
            ['product_type' => $product_type],
            ['%s']
        );
        return $result !== false ? (int)$result : 0;
    }

    /**
     * Get products that need updating (older than threshold).
     *
     * @param int $hours_old Hours since last update.
     * @return array<int> Array of product IDs needing update.
     */
    public function get_stale_products(int $hours_old = 24): array {
        $results = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT product_id FROM {$this->table_name}
                WHERE last_updated < DATE_SUB(NOW(), INTERVAL %d HOUR)",
                $hours_old
            )
        );

        return array_map('intval', $results ?: []);
    }

    /**
     * Unserialize specs and price_history in a row.
     *
     * @param array<string, mixed> $row Database row.
     * @return array<string, mixed> Row with unserialized data.
     */
    private function unserialize_row(array $row): array {
        if (isset($row['specs'])) {
            $row['specs'] = maybe_unserialize($row['specs']);
        }
        if (isset($row['price_history'])) {
            $row['price_history'] = maybe_unserialize($row['price_history']);
        }

        // Cast numeric fields.
        $row['id'] = isset($row['id']) ? (int)$row['id'] : 0;
        $row['product_id'] = isset($row['product_id']) ? (int)$row['product_id'] : 0;
        $row['rating'] = isset($row['rating']) ? (float)$row['rating'] : null;
        $row['popularity_score'] = isset($row['popularity_score']) ? (int)$row['popularity_score'] : 0;

        return $row;
    }
}
