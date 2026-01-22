<?php
/**
 * Email Queue database table definition.
 *
 * @package ERH\Database
 */

declare(strict_types=1);

namespace ERH\Database;

/**
 * Handles email queue table schema.
 */
class EmailQueue {

    /**
     * Table name without prefix.
     */
    public const TABLE_NAME = 'erh_email_queue';

    /**
     * Email type constants.
     */
    public const TYPE_PRICE_ALERT = 'price_alert';
    public const TYPE_DEALS_DIGEST = 'deals_digest';
    public const TYPE_NEWSLETTER = 'newsletter';
    public const TYPE_WELCOME = 'welcome';
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_GENERAL = 'general';

    /**
     * Email status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    /**
     * Priority levels.
     * Lower number = higher priority.
     */
    public const PRIORITY_CRITICAL = 1;   // Immediate: password reset, welcome
    public const PRIORITY_NORMAL = 5;     // Batched: price alerts, deals digest
    public const PRIORITY_LOW = 10;       // Bulk: newsletters

    /**
     * Get the full table name with prefix.
     *
     * @return string
     */
    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the email queue table.
     *
     * @return void
     */
    public static function create_table(): void {
        global $wpdb;
        $table = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email_type varchar(50) NOT NULL,
            recipient_email varchar(255) NOT NULL,
            recipient_user_id bigint(20) unsigned DEFAULT NULL,
            subject varchar(255) NOT NULL,
            body longtext NOT NULL,
            headers text DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            priority tinyint(1) NOT NULL DEFAULT 5,
            retry_count tinyint(1) NOT NULL DEFAULT 0,
            next_retry_at datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY status_priority (status, priority, created_at),
            KEY email_type (email_type),
            KEY recipient_user_id (recipient_user_id),
            KEY next_retry (status, next_retry_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Check if the table exists.
     *
     * @return bool
     */
    public static function table_exists(): bool {
        global $wpdb;
        $table = self::get_table_name();
        $result = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table)
        );
        return $result === $table;
    }

    /**
     * Drop the table.
     *
     * WARNING: This permanently deletes all queued emails.
     *
     * @return void
     */
    public static function drop_table(): void {
        global $wpdb;
        $table = self::get_table_name();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
    }
}
