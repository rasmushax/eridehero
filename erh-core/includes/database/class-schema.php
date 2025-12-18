<?php
/**
 * Database Schema handler.
 *
 * @package ERH\Database
 */

declare(strict_types=1);

namespace ERH\Database;

/**
 * Handles database table creation and migrations.
 */
class Schema {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get the full table name with prefix.
     *
     * @param string $table_name The base table name.
     * @return string The prefixed table name.
     */
    public function get_table_name(string $table_name): string {
        return $this->wpdb->prefix . $table_name;
    }

    /**
     * Create all database tables.
     *
     * @return void
     */
    public function create_tables(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $this->create_product_data_table();
        $this->create_price_history_table();
        $this->create_price_trackers_table();
        $this->create_product_views_table();
    }

    /**
     * Check and perform any necessary upgrades.
     *
     * @return void
     */
    public function maybe_upgrade(): void {
        $current_version = get_option('erh_db_version', '0');

        // Upgrade to add geo/currency columns to price history table.
        if (version_compare($current_version, '1.1.0', '<')) {
            $this->upgrade_price_history_geo_currency();
        }

        // Upgrade to remove geo-dependent columns from product_data table.
        // Re-run at 1.2.1 to ensure columns are actually dropped.
        if (version_compare($current_version, '1.2.1', '<')) {
            $this->upgrade_product_data_remove_geo_fields();
        }

        // Upgrade to add geo/currency columns to price_trackers table.
        if (version_compare($current_version, '1.3.0', '<')) {
            $this->upgrade_price_trackers_geo_currency();
            update_option('erh_db_version', '1.3.0');
        }

        // Re-run table creation to ensure all tables and columns exist.
        $this->create_tables();
    }

    /**
     * Upgrade price history table to add geo and currency columns.
     *
     * @return void
     */
    private function upgrade_price_history_geo_currency(): void {
        $table_name = $this->get_table_name(ERH_TABLE_PRICE_HISTORY);

        // Check if table exists.
        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $table_name)
        );

        if (!$table_exists) {
            return; // Table will be created by create_tables().
        }

        // Check if currency column exists.
        $currency_exists = $this->wpdb->get_var(
            "SHOW COLUMNS FROM {$table_name} LIKE 'currency'"
        );

        if (!$currency_exists) {
            // Add currency column after price.
            $this->wpdb->query(
                "ALTER TABLE {$table_name} ADD COLUMN currency varchar(10) NOT NULL DEFAULT 'USD' AFTER price"
            );
            error_log('[ERH Schema] Added currency column to price history table');
        }

        // Check if geo column exists.
        $geo_exists = $this->wpdb->get_var(
            "SHOW COLUMNS FROM {$table_name} LIKE 'geo'"
        );

        if (!$geo_exists) {
            // Add geo column after domain.
            $this->wpdb->query(
                "ALTER TABLE {$table_name} ADD COLUMN geo varchar(10) NOT NULL DEFAULT 'US' AFTER domain"
            );
            error_log('[ERH Schema] Added geo column to price history table');
        }

        // Drop old unique key and add new one (if needed).
        $index_exists = $this->wpdb->get_var(
            "SHOW INDEX FROM {$table_name} WHERE Key_name = 'product_date'"
        );

        if ($index_exists) {
            // Drop old unique key.
            $this->wpdb->query("ALTER TABLE {$table_name} DROP INDEX product_date");
            error_log('[ERH Schema] Dropped old product_date index');
        }

        // Check if new unique key exists.
        $new_index_exists = $this->wpdb->get_var(
            "SHOW INDEX FROM {$table_name} WHERE Key_name = 'product_date_geo_currency'"
        );

        if (!$new_index_exists) {
            // Add new unique key.
            $this->wpdb->query(
                "ALTER TABLE {$table_name} ADD UNIQUE KEY product_date_geo_currency (product_id, date, geo, currency)"
            );
            error_log('[ERH Schema] Added new product_date_geo_currency index');
        }

        // Add individual indexes for geo and currency if missing.
        $geo_index = $this->wpdb->get_var(
            "SHOW INDEX FROM {$table_name} WHERE Key_name = 'geo'"
        );
        if (!$geo_index) {
            $this->wpdb->query("ALTER TABLE {$table_name} ADD KEY geo (geo)");
        }

        $currency_index = $this->wpdb->get_var(
            "SHOW INDEX FROM {$table_name} WHERE Key_name = 'currency'"
        );
        if (!$currency_index) {
            $this->wpdb->query("ALTER TABLE {$table_name} ADD KEY currency (currency)");
        }

        error_log('[ERH Schema] Price history table upgraded with geo/currency support');
    }

    /**
     * Upgrade product_data table to remove geo-dependent columns.
     *
     * With geo-aware pricing, price/instock/bestlink are now stored
     * per-geo in the serialized price_history field instead.
     *
     * @return void
     */
    private function upgrade_product_data_remove_geo_fields(): void {
        $table_name = $this->get_table_name(ERH_TABLE_PRODUCT_DATA);

        // Check if table exists.
        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $table_name)
        );

        if (!$table_exists) {
            return; // Table will be created by create_tables().
        }

        // Drop price column if it exists.
        $price_exists = $this->wpdb->get_var(
            "SHOW COLUMNS FROM {$table_name} LIKE 'price'"
        );
        if ($price_exists) {
            $this->wpdb->query("ALTER TABLE {$table_name} DROP COLUMN price");
            error_log('[ERH Schema] Dropped price column from product_data');
        }

        // Drop instock column if it exists.
        $instock_exists = $this->wpdb->get_var(
            "SHOW COLUMNS FROM {$table_name} LIKE 'instock'"
        );
        if ($instock_exists) {
            $this->wpdb->query("ALTER TABLE {$table_name} DROP COLUMN instock");
            error_log('[ERH Schema] Dropped instock column from product_data');
        }

        // Drop bestlink column if it exists.
        $bestlink_exists = $this->wpdb->get_var(
            "SHOW COLUMNS FROM {$table_name} LIKE 'bestlink'"
        );
        if ($bestlink_exists) {
            $this->wpdb->query("ALTER TABLE {$table_name} DROP COLUMN bestlink");
            error_log('[ERH Schema] Dropped bestlink column from product_data');
        }

        // Drop price index if it exists.
        $price_index = $this->wpdb->get_var(
            "SHOW INDEX FROM {$table_name} WHERE Key_name = 'price'"
        );
        if ($price_index) {
            $this->wpdb->query("ALTER TABLE {$table_name} DROP INDEX price");
            error_log('[ERH Schema] Dropped price index from product_data');
        }

        // Drop instock index if it exists.
        $instock_index = $this->wpdb->get_var(
            "SHOW INDEX FROM {$table_name} WHERE Key_name = 'instock'"
        );
        if ($instock_index) {
            $this->wpdb->query("ALTER TABLE {$table_name} DROP INDEX instock");
            error_log('[ERH Schema] Dropped instock index from product_data');
        }

        error_log('[ERH Schema] Product data table upgraded - removed geo-dependent columns');
    }

    /**
     * Upgrade price_trackers table to add geo and currency columns.
     *
     * This enables geo-aware price tracking so users track prices
     * in their own region/currency.
     *
     * @return void
     */
    private function upgrade_price_trackers_geo_currency(): void {
        $table_name = $this->get_table_name(ERH_TABLE_PRICE_TRACKERS);

        // Check if table exists.
        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $table_name)
        );

        if (!$table_exists) {
            return; // Table will be created by create_tables().
        }

        // Add geo column if missing.
        $geo_exists = $this->wpdb->get_var(
            "SHOW COLUMNS FROM {$table_name} LIKE 'geo'"
        );
        if (!$geo_exists) {
            $this->wpdb->query(
                "ALTER TABLE {$table_name} ADD COLUMN geo varchar(10) NOT NULL DEFAULT 'US' AFTER product_id"
            );
            error_log('[ERH Schema] Added geo column to price_trackers');
        }

        // Add currency column if missing.
        $currency_exists = $this->wpdb->get_var(
            "SHOW COLUMNS FROM {$table_name} LIKE 'currency'"
        );
        if (!$currency_exists) {
            $this->wpdb->query(
                "ALTER TABLE {$table_name} ADD COLUMN currency varchar(10) NOT NULL DEFAULT 'USD' AFTER geo"
            );
            error_log('[ERH Schema] Added currency column to price_trackers');
        }

        // Add geo index if missing.
        $geo_index = $this->wpdb->get_var(
            "SHOW INDEX FROM {$table_name} WHERE Key_name = 'geo'"
        );
        if (!$geo_index) {
            $this->wpdb->query("ALTER TABLE {$table_name} ADD KEY geo (geo)");
            error_log('[ERH Schema] Added geo index to price_trackers');
        }

        error_log('[ERH Schema] Price trackers table upgraded with geo/currency support');
    }

    /**
     * Create the product_data table (Finder tool cache).
     *
     * This table stores pre-computed product data for the finder tool
     * to enable fast filtering and sorting without querying post meta.
     *
     * Note: price, instock, bestlink were removed in v1.2.0 - these are now
     * stored per-geo in the serialized price_history field.
     *
     * @return void
     */
    private function create_product_data_table(): void {
        $table_name = $this->get_table_name(ERH_TABLE_PRODUCT_DATA);
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            product_type varchar(50) NOT NULL,
            name varchar(255) NOT NULL,
            specs longtext,
            rating decimal(3,1) DEFAULT NULL,
            popularity_score int(8) DEFAULT 0,
            permalink varchar(255) DEFAULT NULL,
            image_url varchar(255) DEFAULT NULL,
            price_history longtext,
            last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY product_id (product_id),
            KEY product_type (product_type),
            KEY popularity_score (popularity_score)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create the product_daily_prices table.
     *
     * This table stores historical daily price snapshots for products,
     * with support for multiple geos and currencies per product per day.
     *
     * @return void
     */
    private function create_price_history_table(): void {
        $table_name = $this->get_table_name(ERH_TABLE_PRICE_HISTORY);
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            price decimal(10,2) NOT NULL,
            currency varchar(10) NOT NULL DEFAULT 'USD',
            domain varchar(255) NOT NULL,
            geo varchar(10) NOT NULL DEFAULT 'US',
            date date NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY product_date_geo_currency (product_id, date, geo, currency),
            KEY product_id (product_id),
            KEY date (date),
            KEY geo (geo),
            KEY currency (currency)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create the price_trackers table.
     *
     * This table stores user price tracking preferences and targets.
     *
     * @return void
     */
    private function create_price_trackers_table(): void {
        $table_name = $this->get_table_name(ERH_TABLE_PRICE_TRACKERS);
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            geo varchar(10) NOT NULL DEFAULT 'US',
            currency varchar(10) NOT NULL DEFAULT 'USD',
            start_price decimal(10,2) DEFAULT NULL,
            current_price decimal(10,2) DEFAULT NULL,
            target_price decimal(10,2) DEFAULT NULL,
            price_drop decimal(10,2) DEFAULT NULL,
            last_notified_price decimal(10,2) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_notification_time datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY product_id (product_id),
            KEY user_product (user_id, product_id),
            KEY geo (geo)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create the product_views table.
     *
     * This table stores product view tracking data for analytics.
     *
     * @return void
     */
    private function create_product_views_table(): void {
        $table_name = $this->get_table_name(ERH_TABLE_PRODUCT_VIEWS);
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            ip_hash varchar(64) NOT NULL,
            user_agent varchar(255) DEFAULT NULL,
            view_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY view_date (view_date),
            KEY ip_hash (ip_hash),
            KEY product_date (product_id, view_date)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Check if a table exists.
     *
     * @param string $table_name The table name (without prefix).
     * @return bool True if the table exists.
     */
    public function table_exists(string $table_name): bool {
        $full_table_name = $this->get_table_name($table_name);
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                'SHOW TABLES LIKE %s',
                $full_table_name
            )
        );
        return $result === $full_table_name;
    }

    /**
     * Drop all plugin tables.
     *
     * WARNING: This permanently deletes all data. Use with caution.
     *
     * @return void
     */
    public function drop_tables(): void {
        $tables = [
            ERH_TABLE_PRODUCT_DATA,
            ERH_TABLE_PRICE_HISTORY,
            ERH_TABLE_PRICE_TRACKERS,
            ERH_TABLE_PRODUCT_VIEWS,
        ];

        foreach ($tables as $table) {
            $full_table_name = $this->get_table_name($table);
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->query("DROP TABLE IF EXISTS {$full_table_name}");
        }
    }

    /**
     * Get row count for a table.
     *
     * @param string $table_name The table name (without prefix).
     * @return int The row count.
     */
    public function get_row_count(string $table_name): int {
        $full_table_name = $this->get_table_name($table_name);

        if (!$this->table_exists($table_name)) {
            return 0;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$full_table_name}");
        return (int)$count;
    }

    /**
     * Truncate a table (remove all rows but keep structure).
     *
     * @param string $table_name The table name (without prefix).
     * @return bool True on success.
     */
    public function truncate_table(string $table_name): bool {
        $full_table_name = $this->get_table_name($table_name);

        if (!$this->table_exists($table_name)) {
            return false;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $this->wpdb->query("TRUNCATE TABLE {$full_table_name}");
        return $result !== false;
    }

    /**
     * Clean up old data (for maintenance).
     *
     * @param int $days_to_keep Number of days of view data to keep.
     * @return int Number of rows deleted.
     */
    public function cleanup_old_views(int $days_to_keep = 90): int {
        $table_name = $this->get_table_name(ERH_TABLE_PRODUCT_VIEWS);

        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "DELETE FROM {$table_name} WHERE view_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days_to_keep
            )
        );

        return $result !== false ? (int)$result : 0;
    }
}
