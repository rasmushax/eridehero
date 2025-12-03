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
 * This table stores historical daily price snapshots for products.
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
     * @param string $domain     The retailer domain.
     * @param string $date       The date (Y-m-d format). Defaults to today.
     * @return bool True on success.
     */
    public function record_price(int $product_id, float $price, string $domain, string $date = ''): bool {
        if (empty($date)) {
            $date = current_time('Y-m-d');
        }

        // Use INSERT ... ON DUPLICATE KEY UPDATE for upsert.
        $sql = $this->wpdb->prepare(
            "INSERT INTO {$this->table_name} (product_id, price, domain, date)
             VALUES (%d, %f, %s, %s)
             ON DUPLICATE KEY UPDATE price = %f, domain = %s",
            $product_id,
            $price,
            $domain,
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
     * @param int    $product_id The product post ID.
     * @param int    $days       Number of days of history. 0 for all.
     * @param string $order      ASC or DESC.
     * @return array<int, array<string, mixed>> Array of price history records.
     */
    public function get_history(int $product_id, int $days = 0, string $order = 'ASC'): array {
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        if ($days > 0) {
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name}
                     WHERE product_id = %d
                     AND date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
                     ORDER BY date {$order}",
                    $product_id,
                    $days
                ),
                ARRAY_A
            );
        } else {
            $results = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_name}
                     WHERE product_id = %d
                     ORDER BY date {$order}",
                    $product_id
                ),
                ARRAY_A
            );
        }

        return $this->cast_results($results ?: []);
    }

    /**
     * Get price history formatted for Chart.js.
     *
     * @param int $product_id The product post ID.
     * @param int $days       Number of days of history.
     * @return array<string, mixed> Chart-ready data structure.
     */
    public function get_chart_data(int $product_id, int $days = 180): array {
        $history = $this->get_history($product_id, $days, 'ASC');

        $labels = [];
        $data = [];

        foreach ($history as $record) {
            $labels[] = $record['date'];
            $data[] = [
                'x'      => $record['date'],
                'y'      => $record['price'],
                'domain' => $record['domain'],
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
     * @param int $product_id The product post ID.
     * @param int $days       Number of days to analyze.
     * @return array<string, mixed>|null Statistics or null if no data.
     */
    public function get_statistics(int $product_id, int $days = 180): ?array {
        $history = $this->get_history($product_id, $days);

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
            'lowest_price'        => [
                'amount' => $lowest_price,
                'date'   => $lowest_record['date'] ?? null,
            ],
            'highest_price'       => [
                'amount' => $highest_price,
                'date'   => $highest_record['date'] ?? null,
            ],
            'average_price'       => round($average_price, 2),
            'std_deviation'       => round($std_dev, 2),
        ];
    }

    /**
     * Calculate Z-score for current price vs historical average.
     *
     * @param int   $product_id    The product post ID.
     * @param float $current_price The current price.
     * @param int   $days          Days of history to consider.
     * @return float|null Z-score or null if not enough data.
     */
    public function calculate_z_score(int $product_id, float $current_price, int $days = 180): ?float {
        $stats = $this->get_statistics($product_id, $days);

        if (!$stats || $stats['std_deviation'] == 0) {
            return null;
        }

        $z_score = ($current_price - $stats['average_price']) / $stats['std_deviation'];
        return round($z_score, 2);
    }

    /**
     * Check if current price is a deal (below average by threshold).
     *
     * @param int   $product_id    The product post ID.
     * @param float $current_price The current price.
     * @param float $threshold     Percentage below average to qualify as deal.
     * @param int   $days          Days of history to consider.
     * @return bool True if current price is a deal.
     */
    public function is_deal(int $product_id, float $current_price, float $threshold = 0.05, int $days = 180): bool {
        $stats = $this->get_statistics($product_id, $days);

        if (!$stats || $stats['average_price'] <= 0) {
            return false;
        }

        $deal_threshold = $stats['average_price'] * (1 - $threshold);
        return $current_price <= $deal_threshold;
    }

    /**
     * Get the latest recorded price for a product.
     *
     * @param int $product_id The product post ID.
     * @return array<string, mixed>|null Latest price record or null.
     */
    public function get_latest(int $product_id): ?array {
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE product_id = %d
                 ORDER BY date DESC
                 LIMIT 1",
                $product_id
            ),
            ARRAY_A
        );

        if (!$result) {
            return null;
        }

        return $this->cast_row($result);
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
