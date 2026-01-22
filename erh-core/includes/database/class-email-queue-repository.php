<?php
/**
 * Email Queue Repository for database operations.
 *
 * @package ERH\Database
 */

declare(strict_types=1);

namespace ERH\Database;

/**
 * Handles CRUD operations for the email queue table.
 */
class EmailQueueRepository {

    /**
     * Maximum number of retry attempts.
     */
    private const MAX_RETRIES = 3;

    /**
     * Retry delays in seconds: 5min, 15min, 45min.
     */
    private const RETRY_DELAYS = [300, 900, 2700];

    /**
     * Queue an email for sending.
     *
     * @param array $data {
     *     Email data.
     *
     *     @type string   $email_type        Email type (see EmailQueue constants).
     *     @type string   $recipient_email   Recipient email address.
     *     @type int|null $recipient_user_id User ID (optional).
     *     @type string   $subject           Email subject.
     *     @type string   $body              Email body (HTML).
     *     @type array|string|null $headers  Email headers.
     *     @type int      $priority          Priority level (1=critical, 5=normal, 10=low).
     * }
     * @return int|false The inserted ID or false on failure.
     */
    public function queue(array $data): int|false {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        $defaults = [
            'email_type'        => EmailQueue::TYPE_GENERAL,
            'recipient_email'   => '',
            'recipient_user_id' => null,
            'subject'           => '',
            'body'              => '',
            'headers'           => null,
            'status'            => EmailQueue::STATUS_PENDING,
            'priority'          => EmailQueue::PRIORITY_NORMAL,
            'retry_count'       => 0,
            'created_at'        => current_time('mysql'),
        ];

        $data = wp_parse_args($data, $defaults);

        // Validate required fields.
        if (empty($data['recipient_email']) || empty($data['subject']) || empty($data['body'])) {
            return false;
        }

        // Serialize headers if array.
        if (is_array($data['headers'])) {
            $data['headers'] = implode("\r\n", $data['headers']);
        }

        $result = $wpdb->insert($table, $data);
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Queue multiple emails in a single batch insert.
     *
     * Much more efficient than individual inserts for bulk operations.
     * Processes in chunks of 100 to avoid query size limits.
     *
     * @param array $emails Array of email data arrays (same format as queue()).
     * @return int Number of successfully queued emails.
     */
    public function queue_batch(array $emails): int {
        if (empty($emails)) {
            return 0;
        }

        global $wpdb;
        $table = EmailQueue::get_table_name();
        $chunk_size = 100;
        $queued = 0;

        // Process in chunks to avoid query size limits.
        $chunks = array_chunk($emails, $chunk_size);

        foreach ($chunks as $chunk) {
            $values = [];
            $placeholders = [];

            foreach ($chunk as $email) {
                // Validate required fields.
                if (empty($email['recipient_email']) || empty($email['subject']) || empty($email['body'])) {
                    continue;
                }

                // Prepare data with defaults.
                $email_type = $email['email_type'] ?? EmailQueue::TYPE_GENERAL;
                $recipient_email = $email['recipient_email'];
                $recipient_user_id = $email['recipient_user_id'] ?? null;
                $subject = $email['subject'];
                $body = $email['body'];
                $headers = is_array($email['headers'] ?? null) ? implode("\r\n", $email['headers']) : ($email['headers'] ?? null);
                $priority = $email['priority'] ?? EmailQueue::PRIORITY_NORMAL;
                $created_at = current_time('mysql');

                $values[] = $email_type;
                $values[] = $recipient_email;
                $values[] = $recipient_user_id;
                $values[] = $subject;
                $values[] = $body;
                $values[] = $headers;
                $values[] = EmailQueue::STATUS_PENDING;
                $values[] = $priority;
                $values[] = 0; // retry_count
                $values[] = $created_at;

                $placeholders[] = '(%s, %s, %d, %s, %s, %s, %s, %d, %d, %s)';
            }

            if (empty($placeholders)) {
                continue;
            }

            $columns = 'email_type, recipient_email, recipient_user_id, subject, body, headers, status, priority, retry_count, created_at';

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $sql = $wpdb->prepare(
                "INSERT INTO {$table} ({$columns}) VALUES " . implode(', ', $placeholders),
                $values
            );

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $result = $wpdb->query($sql);

            if ($result !== false) {
                $queued += $result;
            }
        }

        return $queued;
    }

    /**
     * Get pending emails ready for processing.
     *
     * Returns emails that are pending and either have no retry time
     * or their retry time has passed.
     *
     * @param int $limit Maximum number of emails to return.
     * @return array Array of email records.
     */
    public function get_pending(int $limit = 50): array {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE status = %s
               AND (next_retry_at IS NULL OR next_retry_at <= %s)
             ORDER BY priority ASC, created_at ASC
             LIMIT %d",
            EmailQueue::STATUS_PENDING,
            current_time('mysql'),
            $limit
        ), ARRAY_A) ?: [];
    }

    /**
     * Get a single email by ID.
     *
     * @param int $id The email ID.
     * @return array|null The email record or null.
     */
    public function get(int $id): ?array {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);

        return $result ?: null;
    }

    /**
     * Claim an email for processing (prevents double-send).
     *
     * Uses atomic update to ensure only one process can claim the email.
     * Sets claimed_at timestamp for stale detection.
     *
     * @param int $id The email ID.
     * @return bool True if claimed successfully, false if already claimed or not found.
     */
    public function claim(int $id): bool {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = %s, claimed_at = %s
             WHERE id = %d AND status = %s",
            EmailQueue::STATUS_PROCESSING,
            current_time('mysql'),
            $id,
            EmailQueue::STATUS_PENDING
        ));

        return $result === 1;
    }

    /**
     * Mark email as sent.
     *
     * @param int $id The email ID.
     * @return bool True on success.
     */
    public function mark_sent(int $id): bool {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        return (bool) $wpdb->update(
            $table,
            [
                'status'       => EmailQueue::STATUS_SENT,
                'processed_at' => current_time('mysql'),
            ],
            ['id' => $id]
        );
    }

    /**
     * Mark email as failed and schedule retry if attempts remain.
     *
     * @param int    $id    The email ID.
     * @param string $error Error message.
     * @return bool True on success.
     */
    public function mark_failed(int $id, string $error = ''): bool {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        // Get current retry count.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $email = $wpdb->get_row($wpdb->prepare(
            "SELECT retry_count FROM {$table} WHERE id = %d",
            $id
        ), ARRAY_A);

        if (!$email) {
            return false;
        }

        $retry_count = (int) $email['retry_count'];

        if ($retry_count < self::MAX_RETRIES) {
            // Schedule retry with exponential backoff.
            $delay = self::RETRY_DELAYS[$retry_count] ?? 2700;
            $next_retry = gmdate('Y-m-d H:i:s', time() + $delay);

            return (bool) $wpdb->update(
                $table,
                [
                    'status'        => EmailQueue::STATUS_PENDING,
                    'retry_count'   => $retry_count + 1,
                    'next_retry_at' => $next_retry,
                    'error_message' => $error,
                ],
                ['id' => $id]
            );
        }

        // Max retries exceeded - mark as permanently failed.
        return (bool) $wpdb->update(
            $table,
            [
                'status'        => EmailQueue::STATUS_FAILED,
                'processed_at'  => current_time('mysql'),
                'error_message' => $error,
            ],
            ['id' => $id]
        );
    }

    /**
     * Release a claimed email back to pending (e.g., on timeout).
     *
     * @param int $id The email ID.
     * @return bool True on success.
     */
    public function release(int $id): bool {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        return (bool) $wpdb->update(
            $table,
            ['status' => EmailQueue::STATUS_PENDING],
            ['id' => $id, 'status' => EmailQueue::STATUS_PROCESSING]
        );
    }

    /**
     * Get queue statistics.
     *
     * @return array{pending: int, processing: int, sent: int, failed: int}
     */
    public function get_stats(): array {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status",
            ARRAY_A
        );

        $result = [
            'pending'    => 0,
            'processing' => 0,
            'sent'       => 0,
            'failed'     => 0,
        ];

        foreach ($stats ?: [] as $row) {
            if (isset($result[$row['status']])) {
                $result[$row['status']] = (int) $row['count'];
            }
        }

        return $result;
    }

    /**
     * Get statistics by email type.
     *
     * @return array Keyed by email type with counts per status.
     */
    public function get_stats_by_type(): array {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats = $wpdb->get_results(
            "SELECT email_type, status, COUNT(*) as count
             FROM {$table}
             GROUP BY email_type, status",
            ARRAY_A
        );

        $result = [];
        foreach ($stats ?: [] as $row) {
            $type = $row['email_type'];
            $status = $row['status'];
            if (!isset($result[$type])) {
                $result[$type] = ['pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0];
            }
            $result[$type][$status] = (int) $row['count'];
        }

        return $result;
    }

    /**
     * Count emails sent today.
     *
     * @return int
     */
    public function count_sent_today(): int {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table}
             WHERE status = %s
               AND DATE(processed_at) = %s",
            EmailQueue::STATUS_SENT,
            current_time('Y-m-d')
        ));

        return (int) $count;
    }

    /**
     * Get recent failed emails for debugging.
     *
     * @param int $limit Maximum number of records.
     * @return array
     */
    public function get_recent_failures(int $limit = 10): array {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, email_type, recipient_email, subject, retry_count, error_message, processed_at
             FROM {$table}
             WHERE status = %s
             ORDER BY processed_at DESC
             LIMIT %d",
            EmailQueue::STATUS_FAILED,
            $limit
        ), ARRAY_A) ?: [];
    }

    /**
     * Clean up old sent/failed emails.
     *
     * @param int $days Number of days to keep.
     * @return int Number of rows deleted.
     */
    public function cleanup(int $days = 30): int {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table}
             WHERE status IN (%s, %s)
               AND processed_at < DATE_SUB(%s, INTERVAL %d DAY)",
            EmailQueue::STATUS_SENT,
            EmailQueue::STATUS_FAILED,
            current_time('mysql'),
            $days
        ));

        return $result !== false ? (int) $result : 0;
    }

    /**
     * Release stale processing emails (stuck for more than X minutes).
     *
     * This handles cases where processing crashed before completing.
     * Uses claimed_at timestamp to accurately detect stale emails.
     *
     * @param int $minutes Minutes after which processing is considered stale.
     * @return int Number of emails released.
     */
    public function release_stale(int $minutes = 5): int {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = %s, claimed_at = NULL
             WHERE status = %s
               AND claimed_at IS NOT NULL
               AND claimed_at < DATE_SUB(%s, INTERVAL %d MINUTE)",
            EmailQueue::STATUS_PENDING,
            EmailQueue::STATUS_PROCESSING,
            current_time('mysql'),
            $minutes
        ));

        return $result !== false ? (int) $result : 0;
    }

    /**
     * Delete a specific email from the queue.
     *
     * @param int $id The email ID.
     * @return bool True on success.
     */
    public function delete(int $id): bool {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        return (bool) $wpdb->delete($table, ['id' => $id]);
    }

    /**
     * Get emails for a specific user.
     *
     * @param int    $user_id User ID.
     * @param string $status  Optional status filter.
     * @param int    $limit   Maximum records to return.
     * @return array
     */
    public function get_for_user(int $user_id, string $status = '', int $limit = 50): array {
        global $wpdb;
        $table = EmailQueue::get_table_name();

        if ($status) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE recipient_user_id = %d AND status = %s
                 ORDER BY created_at DESC
                 LIMIT %d",
                $user_id,
                $status,
                $limit
            ), ARRAY_A) ?: [];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE recipient_user_id = %d
             ORDER BY created_at DESC
             LIMIT %d",
            $user_id,
            $limit
        ), ARRAY_A) ?: [];
    }
}
