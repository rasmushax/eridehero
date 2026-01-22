<?php
/**
 * Price Tracker - CRUD operations for wp_price_trackers table.
 *
 * @package ERH\Database
 */

declare(strict_types=1);

namespace ERH\Database;

/**
 * Handles CRUD operations for the price_trackers table.
 * This table stores user price tracking preferences and targets.
 */
class PriceTracker {

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
        $this->table_name = $wpdb->prefix . ERH_TABLE_PRICE_TRACKERS;
    }

    /**
     * Get a tracker by ID.
     *
     * @param int $tracker_id The tracker ID.
     * @return array<string, mixed>|null Tracker data or null.
     */
    public function get(int $tracker_id): ?array {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $tracker_id
            ),
            ARRAY_A
        );

        return $row ? $this->cast_row($row) : null;
    }

    /**
     * Get a user's tracker for a specific product.
     *
     * @param int $user_id    The user ID.
     * @param int $product_id The product post ID.
     * @return array<string, mixed>|null Tracker data or null.
     */
    public function get_for_user_product(int $user_id, int $product_id): ?array {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE user_id = %d AND product_id = %d",
                $user_id,
                $product_id
            ),
            ARRAY_A
        );

        return $row ? $this->cast_row($row) : null;
    }

    /**
     * Get all trackers for a user.
     *
     * @param int $user_id The user ID.
     * @return array<int, array<string, mixed>> Array of tracker data.
     */
    public function get_for_user(int $user_id): array {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE user_id = %d
                 ORDER BY created_at DESC",
                $user_id
            ),
            ARRAY_A
        );

        return array_map([$this, 'cast_row'], $results ?: []);
    }

    /**
     * Get all trackers for a product.
     *
     * @param int $product_id The product post ID.
     * @return array<int, array<string, mixed>> Array of tracker data.
     */
    public function get_for_product(int $product_id): array {
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                 WHERE product_id = %d",
                $product_id
            ),
            ARRAY_A
        );

        return array_map([$this, 'cast_row'], $results ?: []);
    }

    /**
     * Get all active trackers (for cron processing).
     *
     * @return array<int, array<string, mixed>> Array of all trackers.
     */
    public function get_all(): array {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name}",
            ARRAY_A
        );

        return array_map([$this, 'cast_row'], $results ?: []);
    }

    /**
     * Create a new price tracker.
     *
     * @param int        $user_id      The user ID.
     * @param int        $product_id   The product post ID.
     * @param float      $start_price  The starting/current price.
     * @param float|null $target_price The target price (null if using price_drop).
     * @param float|null $price_drop   The price drop amount (null if using target_price).
     * @param string     $geo          The geo region (US, GB, EU, CA, AU).
     * @param string     $currency     The currency code (USD, GBP, EUR, CAD, AUD).
     * @return int|false The new tracker ID or false on failure.
     */
    public function create(
        int $user_id,
        int $product_id,
        float $start_price,
        ?float $target_price = null,
        ?float $price_drop = null,
        string $geo = 'US',
        string $currency = 'USD'
    ) {
        // Check if tracker already exists for this user/product.
        $existing = $this->get_for_user_product($user_id, $product_id);
        if ($existing) {
            return false;
        }

        $result = $this->wpdb->insert(
            $this->table_name,
            [
                'user_id'       => $user_id,
                'product_id'    => $product_id,
                'geo'           => $geo,
                'currency'      => $currency,
                'start_price'   => $start_price,
                'current_price' => $start_price,
                'target_price'  => $target_price,
                'price_drop'    => $price_drop,
                'created_at'    => current_time('mysql'),
                'updated_at'    => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s']
        );

        if ($result === false) {
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update a tracker's target.
     *
     * @param int        $tracker_id   The tracker ID.
     * @param float|null $target_price The new target price.
     * @param float|null $price_drop   The new price drop amount.
     * @return bool True on success.
     */
    public function update_target(int $tracker_id, ?float $target_price = null, ?float $price_drop = null): bool {
        $result = $this->wpdb->update(
            $this->table_name,
            [
                'target_price' => $target_price,
                'price_drop'   => $price_drop,
                'updated_at'   => current_time('mysql'),
            ],
            ['id' => $tracker_id],
            ['%f', '%f', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Update the current price for a tracker.
     *
     * @param int   $tracker_id    The tracker ID.
     * @param float $current_price The new current price.
     * @return bool True on success.
     */
    public function update_current_price(int $tracker_id, float $current_price): bool {
        $result = $this->wpdb->update(
            $this->table_name,
            [
                'current_price' => $current_price,
                'updated_at'    => current_time('mysql'),
            ],
            ['id' => $tracker_id],
            ['%f', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Record that a notification was sent.
     *
     * @param int   $tracker_id     The tracker ID.
     * @param float $notified_price The price at notification time.
     * @return bool True on success.
     */
    public function record_notification(int $tracker_id, float $notified_price): bool {
        $result = $this->wpdb->update(
            $this->table_name,
            [
                'last_notified_price'    => $notified_price,
                'last_notification_time' => current_time('mysql'),
                'updated_at'             => current_time('mysql'),
            ],
            ['id' => $tracker_id],
            ['%f', '%s', '%s'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Generic update method for a tracker.
     *
     * @param int                  $tracker_id The tracker ID.
     * @param array<string, mixed> $data       Data to update.
     * @return bool True on success.
     */
    public function update(int $tracker_id, array $data): bool {
        // Always update the updated_at timestamp.
        $data['updated_at'] = current_time('mysql');

        // Build format array based on data types.
        $formats = [];
        foreach ($data as $key => $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            ['id' => $tracker_id],
            $formats,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete a tracker.
     *
     * @param int $tracker_id The tracker ID.
     * @return bool True on success.
     */
    public function delete(int $tracker_id): bool {
        $result = $this->wpdb->delete(
            $this->table_name,
            ['id' => $tracker_id],
            ['%d']
        );
        return $result !== false;
    }

    /**
     * Delete a tracker by user and product.
     *
     * @param int $user_id    The user ID.
     * @param int $product_id The product post ID.
     * @return bool True on success.
     */
    public function delete_for_user_product(int $user_id, int $product_id): bool {
        $result = $this->wpdb->delete(
            $this->table_name,
            [
                'user_id'    => $user_id,
                'product_id' => $product_id,
            ],
            ['%d', '%d']
        );
        return $result !== false;
    }

    /**
     * Delete all trackers for a user.
     *
     * @param int $user_id The user ID.
     * @return int Number of trackers deleted.
     */
    public function delete_for_user(int $user_id): int {
        $result = $this->wpdb->delete(
            $this->table_name,
            ['user_id' => $user_id],
            ['%d']
        );
        return $result !== false ? (int)$result : 0;
    }

    /**
     * Delete all trackers for a product.
     *
     * Used when a product is deleted to clean up orphaned trackers.
     *
     * @param int $product_id The product post ID.
     * @return int Number of trackers deleted.
     */
    public function delete_for_product(int $product_id): int {
        $result = $this->wpdb->delete(
            $this->table_name,
            ['product_id' => $product_id],
            ['%d']
        );
        return $result !== false ? (int)$result : 0;
    }

    /**
     * Record that the price was seen above the target.
     *
     * This enables re-notification when the price rebounds below target.
     *
     * @param int $tracker_id The tracker ID.
     * @return bool True on success.
     */
    public function record_above_target(int $tracker_id): bool {
        $result = $this->wpdb->update(
            $this->table_name,
            [
                'last_seen_above_target' => current_time('mysql'),
                'updated_at'             => current_time('mysql'),
            ],
            ['id' => $tracker_id],
            ['%s', '%s'],
            ['%d']
        );
        return $result !== false;
    }

    /**
     * Check if a tracker should trigger a notification.
     *
     * @param array<string, mixed> $tracker      The tracker data.
     * @param float                $current_price The current product price.
     * @return bool True if notification should be sent.
     */
    public function should_notify(array $tracker, float $current_price): bool {
        // Don't notify if we already notified at this price or lower.
        if ($tracker['last_notified_price'] !== null && $current_price >= $tracker['last_notified_price']) {
            return false;
        }

        // Check target price method.
        if ($tracker['target_price'] !== null) {
            return $current_price <= $tracker['target_price'];
        }

        // Check price drop method.
        if ($tracker['price_drop'] !== null && $tracker['start_price'] !== null) {
            $drop_threshold = $tracker['start_price'] - $tracker['price_drop'];
            return $current_price <= $drop_threshold;
        }

        return false;
    }

    /**
     * Get trackers that need notification checking.
     * Only returns trackers for users with email notifications enabled.
     *
     * @return array<int, array<string, mixed>> Trackers with user data.
     */
    public function get_trackers_for_notification(): array {
        $results = $this->wpdb->get_results(
            "SELECT t.*, u.user_email, u.display_name
             FROM {$this->table_name} t
             INNER JOIN {$this->wpdb->users} u ON t.user_id = u.ID
             INNER JOIN {$this->wpdb->usermeta} um ON u.ID = um.user_id
             WHERE um.meta_key = 'price_trackers_emails' AND um.meta_value = '1'",
            ARRAY_A
        );

        return array_map([$this, 'cast_row'], $results ?: []);
    }

    /**
     * Count trackers for a product.
     *
     * @param int $product_id The product post ID.
     * @return int Number of trackers.
     */
    public function count_for_product(int $product_id): int {
        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE product_id = %d",
                $product_id
            )
        );
        return (int)$count;
    }

    /**
     * Get tracker counts for multiple products in a single query.
     *
     * Avoids N+1 query problem when displaying lists of products.
     *
     * @param array<int> $product_ids Array of product IDs.
     * @return array<int, int> Map of product_id => tracker_count.
     */
    public function get_counts_bulk(array $product_ids): array {
        if (empty($product_ids)) {
            return [];
        }

        // Sanitize IDs.
        $product_ids = array_map('absint', $product_ids);
        $product_ids = array_filter($product_ids);

        if (empty($product_ids)) {
            return [];
        }

        // Build placeholders.
        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT product_id, COUNT(*) as tracker_count
                 FROM {$this->table_name}
                 WHERE product_id IN ({$placeholders})
                 GROUP BY product_id",
                $product_ids
            ),
            ARRAY_A
        );

        // Convert to map.
        $counts = [];
        foreach ($results ?: [] as $row) {
            $counts[(int) $row['product_id']] = (int) $row['tracker_count'];
        }

        return $counts;
    }

    /**
     * Cast numeric fields in a row.
     *
     * @param array<string, mixed> $row Database row.
     * @return array<string, mixed> Row with cast values.
     */
    private function cast_row(array $row): array {
        $row['id'] = (int)$row['id'];
        $row['user_id'] = (int)$row['user_id'];
        $row['product_id'] = (int)$row['product_id'];
        // Geo/currency fields (with fallback for older records).
        $row['geo'] = $row['geo'] ?? 'US';
        $row['currency'] = $row['currency'] ?? 'USD';
        $row['start_price'] = $row['start_price'] !== null ? (float)$row['start_price'] : null;
        $row['current_price'] = $row['current_price'] !== null ? (float)$row['current_price'] : null;
        $row['target_price'] = $row['target_price'] !== null ? (float)$row['target_price'] : null;
        $row['price_drop'] = $row['price_drop'] !== null ? (float)$row['price_drop'] : null;
        $row['last_notified_price'] = $row['last_notified_price'] !== null ? (float)$row['last_notified_price'] : null;
        $row['last_notification_time'] = $row['last_notification_time'] ?? null;
        $row['last_seen_above_target'] = $row['last_seen_above_target'] ?? null;
        return $row;
    }
}
