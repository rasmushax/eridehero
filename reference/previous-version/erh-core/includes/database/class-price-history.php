<?php
/**
 * Price History - CRUD operations for wp_product_daily_prices table.
 *
 * @package ERH\Database
 */

declare(strict_types=1);

namespace ERH\Database;

/**
 * Handles CRUD operations for the product_daily_prices table.
 * This table stores historical daily price snapshots for products,
 * with support for multiple geos and currencies.
 */
class PriceHistory {

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
        $this->table_name = $wpdb->prefix . ERH_TABLE_PRICE_HISTORY;
    }

    /**
     * Record a daily price for a product.
     *
     * @param int    $product_id The product post ID.
     * @param float  $price      The price.
     * @param string $currency   The currency code (e.g., 'USD', 'EUR').
     * @param string $domain     The retailer domain.
     * @param string $geo        The geo code (e.g., 'US', 'DK').
     * @param string $date       The date (Y-m-d format). Defaults to today.
     * @return bool True on success.
     */
    public function record_price(
        int $product_id,
        float $price,
        string $currency,
        string $domain,
        string $geo,
        string $date = ''
    ): bool {
        if (empty($date)) {
            $date = current_time('Y-m-d');
        }

        // Normalize geo and currency to uppercase.
        $geo = strtoupper($geo);
        $currency = strtoupper($currency);

        // Use INSERT ... ON DUPLICATE KEY UPDATE for upsert.
        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->table_name} (product_id, price, currency, domain, geo, date)
             VALUES (%d, %f, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE price = %f, domain = %s",
            $product_id,
            $price,
            $currency,
            $domain,
            $geo,
            $date,
            $price,
            $domain
        );

        $result = $this->wpdb->query($sql);
        return $result !== false;
    }

    /**
     * Get price history for a product.
     *
     * @param int         $product_id The product post ID.
     * @param int         $days       Number of days of history. 0 for all.
     * @param string|null $geo        Optional geo filter.
     * @param string|null $currency   Optional currency filter.
     * @param string      $order      ASC or DESC.
     * @return array<int, array<string, mixed>> Array of price history records.
     */
    public function get_history(
        int $product_id,
        int $days = 0,
        ?string $geo = null,
        ?string $currency = null,
        string $order = 'ASC'
    ): array {
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $where_clauses = ['product_id = %d'];
        $params = [$product_id];

        if ($days > 0) {
            $where_clauses[] = 'date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)';
            $params[] = $days;
        }

        if ($geo !== null) {
            // Handle both exact match and empty/default values for US
            $geo = strtoupper($geo);
            if ($geo === 'US') {
                // Match 'US', empty string, or NULL (for legacy data)
                $where_clauses[] = "(geo = %s OR geo = '' OR geo IS NULL)";
            } else {
                $where_clauses[] = 'geo = %s';
            }
            $params[] = $geo;
        }

        if ($currency !== null) {
            $where_clauses[] = 'currency = %s';
            $params[] = strtoupper($currency);
        }

        $where_sql = implode(' AND ', $where_clauses);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE {$where_sql}
                 ORDER BY date {$order}",
                ...$params
            ),
            ARRAY_A
        );

        return $this->cast_results($results ?: []);
    }

    /**
     * Get price history formatted for Chart.js.
     *
     * @param int         $product_id The product post ID.
     * @param int         $days       Number of days of history.
     * @param string|null $geo        Optional geo filter.
     * @param string|null $currency   Optional currency filter.
     * @return array<string, mixed> Chart-ready data structure.
     */
    public function get_chart_data(
        int $product_id,
        int $days = 180,
        ?string $geo = null,
        ?string $currency = null
    ): array {
        $history = $this->get_history($product_id, $days, $geo, $currency, 'ASC');

        $labels = [];
        $data = [];

        foreach ($history as $record) {
            $labels[] = $record['date'];
            $data[] = [
                'x'        => $record['date'],
                'y'        => $record['price'],
                'domain'   => $record['domain'],
                'currency' => $record['currency'],
                'geo'      => $record['geo'],
            ];
        }

        return [
            'labels'   => $labels,
            'datasets' => [
                [
                    'label'       => 'Price History',
                    'data'        => $data,
                    'borderColor' => 'rgb(75, 192, 192)',
                    'tension'     => 0.1,
                ],
            ],
        ];
    }

    /**
     * Get price statistics for a product.
     *
     * @param int         $product_id The product post ID.
     * @param int         $days       Number of days to analyze.
     * @param string|null $geo        Optional geo filter.
     * @param string|null $currency   Optional currency filter.
     * @return array<string, mixed>|null Statistics or null if no data.
     */
    public function get_statistics(
        int $product_id,
        int $days = 180,
        ?string $geo = null,
        ?string $currency = null
    ): ?array {
        $history = $this->get_history($product_id, $days, $geo, $currency);

        if (empty($history)) {
            return null;
        }

        $prices = array_column($history, 'price');
        $count = count($prices);

        $lowest_price = min($prices);
        $highest_price = max($prices);
        $average_price = array_sum($prices) / $count;

        // Find dates for lowest and highest.
        $lowest_record = null;
        $highest_record = null;
        foreach ($history as $record) {
            if ($record['price'] === $lowest_price && !$lowest_record) {
                $lowest_record = $record;
            }
            if ($record['price'] === $highest_price && !$highest_record) {
                $highest_record = $record;
            }
        }

        // Calculate standard deviation.
        $variance = 0;
        foreach ($prices as $price) {
            $variance += pow($price - $average_price, 2);
        }
        $std_dev = sqrt($variance / $count);

        return [
            'tracking_start_date' => $history[0]['date'],
            'data_points'         => $count,
            'geo'                 => $geo,
            'currency'            => $currency ?? ($history[0]['currency'] ?? 'USD'),
            'lowest_price'        => [
                'amount' => $lowest_price,
                'date'   => $lowest_record['date'] ?? null,
                'domain' => $lowest_record['domain'] ?? null,
            ],
            'highest_price'       => [
                'amount' => $highest_price,
                'date'   => $highest_record['date'] ?? null,
                'domain' => $highest_record['domain'] ?? null,
            ],
            'average_price'       => round($average_price, 2),
            'std_deviation'       => round($std_dev, 2),
        ];
    }

    /**
     * Calculate Z-score for current price vs historical average.
     *
     * @param int         $product_id    The product post ID.
     * @param float       $current_price The current price.
     * @param int         $days          Days of history to consider.
     * @param string|null $geo           Optional geo filter.
     * @param string|null $currency      Optional currency filter.
     * @return float|null Z-score or null if not enough data.
     */
    public function calculate_z_score(
        int $product_id,
        float $current_price,
        int $days = 180,
        ?string $geo = null,
        ?string $currency = null
    ): ?float {
        $stats = $this->get_statistics($product_id, $days, $geo, $currency);

        if (!$stats || $stats['std_deviation'] == 0) {
            return null;
        }

        $z_score = ($current_price - $stats['average_price']) / $stats['std_deviation'];
        return round($z_score, 2);
    }

    /**
     * Check if current price is a deal (below average by threshold).
     *
     * @param int         $product_id    The product post ID.
     * @param float       $current_price The current price.
     * @param string      $currency      The currency of the current price.
     * @param float       $threshold     Percentage below average to qualify as deal.
     * @param int         $days          Days of history to consider.
     * @param string|null $geo           Optional geo filter.
     * @return bool True if current price is a deal.
     */
    public function is_deal(
        int $product_id,
        float $current_price,
        string $currency,
        float $threshold = 0.05,
        int $days = 180,
        ?string $geo = null
    ): bool {
        // Get stats for the same currency to compare apples to apples.
        $stats = $this->get_statistics($product_id, $days, $geo, $currency);

        if (!$stats || $stats['average_price'] <= 0) {
            return false;
        }

        $deal_threshold = $stats['average_price'] * (1 - $threshold);
        return $current_price <= $deal_threshold;
    }

    /**
     * Get the latest recorded price for a product.
     *
     * @param int         $product_id The product post ID.
     * @param string|null $geo        Optional geo filter.
     * @param string|null $currency   Optional currency filter.
     * @return array<string, mixed>|null Latest price record or null.
     */
    public function get_latest(
        int $product_id,
        ?string $geo = null,
        ?string $currency = null
    ): ?array {
        $where_clauses = ['product_id = %d'];
        $params = [$product_id];

        if ($geo !== null) {
            $where_clauses[] = 'geo = %s';
            $params[] = strtoupper($geo);
        }

        if ($currency !== null) {
            $where_clauses[] = 'currency = %s';
            $params[] = strtoupper($currency);
        }

        $where_sql = implode(' AND ', $where_clauses);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE {$where_sql}
                 ORDER BY date DESC
                 LIMIT 1",
                ...$params
            ),
            ARRAY_A
        );

        if (!$result) {
            return null;
        }

        return $this->cast_row($result);
    }

    /**
     * Get all available geos for a product.
     *
     * @param int $product_id The product post ID.
     * @return array<string> Array of geo codes.
     */
    public function get_available_geos(int $product_id): array {
        $results = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT DISTINCT geo FROM {$this->table_name}
                 WHERE product_id = %d
                 ORDER BY geo",
                $product_id
            )
        );

        return $results ?: [];
    }

    /**
     * Get all available currencies for a product (optionally filtered by geo).
     *
     * @param int         $product_id The product post ID.
     * @param string|null $geo        Optional geo filter.
     * @return array<string> Array of currency codes.
     */
    public function get_available_currencies(int $product_id, ?string $geo = null): array {
        $where_clauses = ['product_id = %d'];
        $params = [$product_id];

        if ($geo !== null) {
            $where_clauses[] = 'geo = %s';
            $params[] = strtoupper($geo);
        }

        $where_sql = implode(' AND ', $where_clauses);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT DISTINCT currency FROM {$this->table_name}
                 WHERE {$where_sql}
                 ORDER BY currency",
                ...$params
            )
        );

        return $results ?: [];
    }

    /**
     * Delete old price history records.
     *
     * @param int $days_to_keep Number of days of history to keep.
     * @return int Number of rows deleted.
     */
    public function cleanup(int $days_to_keep = 365): int {
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table_name}
                 WHERE date < DATE_SUB(CURDATE(), INTERVAL %d DAY)",
                $days_to_keep
            )
        );

        return $result !== false ? (int)$result : 0;
    }

    /**
     * Delete all history for a product.
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
     * Get price history for multiple products at once.
     *
     * This is an optimization to avoid N+1 queries in bulk operations.
     *
     * @param array<int> $product_ids Array of product post IDs.
     * @return array<int, array<int, array<string, mixed>>> History indexed by product ID.
     */
    public function get_history_bulk(array $product_ids): array {
        if (empty($product_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE product_id IN ({$placeholders})
                 ORDER BY product_id, date DESC",
                ...$product_ids
            ),
            ARRAY_A
        );

        // Group by product ID.
        $grouped = [];
        foreach ($results ?: [] as $row) {
            $pid = (int)$row['product_id'];
            if (!isset($grouped[$pid])) {
                $grouped[$pid] = [];
            }
            $grouped[$pid][] = $this->cast_row($row);
        }

        return $grouped;
    }

    /**
     * Cast numeric fields in a row.
     *
     * @param array<string, mixed> $row Database row.
     * @return array<string, mixed> Row with cast values.
     */
    private function cast_row(array $row): array {
        $row['id'] = (int)$row['id'];
        $row['product_id'] = (int)$row['product_id'];
        $row['price'] = (float)$row['price'];
        return $row;
    }

    /**
     * Cast numeric fields in result set.
     *
     * @param array<int, array<string, mixed>> $results Database results.
     * @return array<int, array<string, mixed>> Results with cast values.
     */
    private function cast_results(array $results): array {
        return array_map([$this, 'cast_row'], $results);
    }
}
