<?php
/**
 * Price History Migration Handler
 *
 * Migrates price history data from eridehero.com via REST API.
 * Converts old schema (no geo/currency) to new schema (with geo/currency).
 *
 * @package ERH\Migration
 */

namespace ERH\Migration;

use ERH\Database\PriceHistory;

/**
 * Handles price history data migration.
 */
class PriceHistoryMigrator {

    /**
     * Source site URL for remote migration.
     *
     * @var string
     */
    private string $source_url;

    /**
     * Secret key for API authentication.
     *
     * @var string
     */
    private string $secret_key;

    /**
     * Price history database instance.
     *
     * @var PriceHistory
     */
    private PriceHistory $price_history;

    /**
     * Whether to run in dry-run mode.
     *
     * @var bool
     */
    private bool $dry_run = false;

    /**
     * Migration log.
     *
     * @var array
     */
    private array $log = [];

    /**
     * Default geo for imported data.
     *
     * @var string
     */
    private string $default_geo = 'US';

    /**
     * Default currency for imported data.
     *
     * @var string
     */
    private string $default_currency = 'USD';

    /**
     * Cache for product slug to local ID mapping.
     *
     * @var array
     */
    private array $product_id_map = [];

    /**
     * Constructor.
     *
     * @param string $source_url The source site URL (e.g., https://eridehero.com).
     * @param string $secret_key The secret key for API authentication.
     */
    public function __construct(string $source_url = '', string $secret_key = '') {
        $this->source_url = rtrim($source_url, '/');
        $this->secret_key = $secret_key;
        $this->price_history = new PriceHistory();
    }

    /**
     * Set dry-run mode.
     *
     * @param bool $dry_run Whether to enable dry-run mode.
     * @return self
     */
    public function set_dry_run(bool $dry_run): self {
        $this->dry_run = $dry_run;
        return $this;
    }

    /**
     * Set default geo for imported data.
     *
     * @param string $geo The geo code.
     * @return self
     */
    public function set_default_geo(string $geo): self {
        $this->default_geo = strtoupper($geo);
        return $this;
    }

    /**
     * Set default currency for imported data.
     *
     * @param string $currency The currency code.
     * @return self
     */
    public function set_default_currency(string $currency): self {
        $this->default_currency = strtoupper($currency);
        return $this;
    }

    /**
     * Test connection to the source API.
     *
     * @return array|null Count data or null on failure.
     */
    public function test_connection(): ?array {
        $url = sprintf(
            '%s/wp-json/erh-migration/v1/price-history/count?secret=%s',
            $this->source_url,
            urlencode($this->secret_key)
        );

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            $this->log('error', 'Connection failed: ' . $response->get_error_message());
            return null;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            $this->log('error', "API returned status {$status}");
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['total_records'])) {
            $this->log('error', 'Invalid API response');
            return null;
        }

        return $data;
    }

    /**
     * Fetch price history data from remote source.
     *
     * @param int $page     Page number.
     * @param int $per_page Records per page.
     * @return array|null Response data or null on failure.
     */
    public function fetch_remote_data(int $page = 1, int $per_page = 1000): ?array {
        if (empty($this->source_url)) {
            $this->log('error', 'No source URL configured');
            return null;
        }

        $url = sprintf(
            '%s/wp-json/erh-migration/v1/price-history?page=%d&per_page=%d&secret=%s',
            $this->source_url,
            $page,
            $per_page,
            urlencode($this->secret_key)
        );

        $this->log('info', "Fetching page {$page}...");

        $response = wp_remote_get($url, [
            'timeout' => 120,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            $this->log('error', 'Fetch failed: ' . $response->get_error_message());
            return null;
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            $this->log('error', "API returned status {$status}");
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['data'])) {
            $this->log('error', 'Invalid API response');
            return null;
        }

        return $data;
    }

    /**
     * Run the full migration.
     *
     * @param int $batch_size Records per batch.
     * @return array Migration summary.
     */
    public function run_migration(int $batch_size = 1000): array {
        $summary = [
            'total_fetched' => 0,
            'imported'      => 0,
            'skipped'       => 0,
            'errors'        => 0,
            'dry_run'       => $this->dry_run,
        ];

        // Build product ID map first.
        $this->build_product_id_map();

        $page = 1;
        $has_more = true;

        while ($has_more) {
            $result = $this->fetch_remote_data($page, $batch_size);

            if (!$result) {
                $this->log('error', "Failed to fetch page {$page}");
                $summary['errors']++;
                break;
            }

            $records = $result['data'];
            $summary['total_fetched'] += count($records);

            foreach ($records as $record) {
                $import_result = $this->import_record($record);

                if ($import_result === true) {
                    $summary['imported']++;
                } elseif ($import_result === false) {
                    $summary['errors']++;
                } else {
                    $summary['skipped']++;
                }
            }

            // Check if more pages.
            $has_more = $page < ($result['pages'] ?? 0);
            $page++;

            // Log progress.
            $this->log('info', sprintf(
                'Progress: Page %d/%d, Imported: %d, Skipped: %d',
                $page - 1,
                $result['pages'] ?? 1,
                $summary['imported'],
                $summary['skipped']
            ));
        }

        $this->log('info', sprintf(
            'Migration complete. Total: %d, Imported: %d, Skipped: %d, Errors: %d',
            $summary['total_fetched'],
            $summary['imported'],
            $summary['skipped'],
            $summary['errors']
        ));

        return $summary;
    }

    /**
     * Import a single price history record.
     *
     * @param array $record The record to import.
     * @return bool|null True on success, false on error, null if skipped.
     */
    private function import_record(array $record): ?bool {
        // Validate required fields.
        if (empty($record['product_id']) || empty($record['price']) || empty($record['date'])) {
            return null; // Skip invalid records.
        }

        // Resolve product ID.
        $local_product_id = $this->resolve_product_id($record);

        if (!$local_product_id) {
            // Product doesn't exist locally - skip.
            return null;
        }

        // Prepare data.
        $price = (float) $record['price'];
        $domain = $record['domain'] ?? '';
        $date = $record['date'];

        // Validate price.
        if ($price <= 0) {
            return null;
        }

        // Dry run - don't actually import.
        if ($this->dry_run) {
            return true;
        }

        // Import with default geo/currency (old data is US only).
        $success = $this->price_history->record_price(
            $local_product_id,
            $price,
            $this->default_currency,
            $domain,
            $this->default_geo,
            $date
        );

        return $success;
    }

    /**
     * Resolve remote product ID to local product ID.
     *
     * @param array $record The record with product_id and optionally product_slug.
     * @return int|null Local product ID or null if not found.
     */
    private function resolve_product_id(array $record): ?int {
        $remote_id = (int) $record['product_id'];
        $slug = $record['product_slug'] ?? null;

        // Try slug first (more reliable across databases).
        if ($slug && isset($this->product_id_map[$slug])) {
            return $this->product_id_map[$slug];
        }

        // Try remote ID as slug fallback.
        if (isset($this->product_id_map[$remote_id])) {
            return $this->product_id_map[$remote_id];
        }

        // Try direct ID match (if IDs are the same).
        $local_exists = get_post_status($remote_id);
        if ($local_exists && get_post_type($remote_id) === 'products') {
            return $remote_id;
        }

        return null;
    }

    /**
     * Build mapping of product slugs to local IDs.
     *
     * @return void
     */
    private function build_product_id_map(): void {
        $this->log('info', 'Building product ID map...');

        $products = get_posts([
            'post_type'      => 'products',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($products as $product_id) {
            $post = get_post($product_id);
            if ($post) {
                $this->product_id_map[$post->post_name] = $product_id;
                $this->product_id_map[$product_id] = $product_id; // Also map by ID
            }
        }

        $this->log('info', sprintf('Mapped %d local products', count($products)));
    }

    /**
     * Get migration log.
     *
     * @return array The log entries.
     */
    public function get_log(): array {
        return $this->log;
    }

    /**
     * Add a log entry.
     *
     * @param string $level Log level (info, warning, error).
     * @param string $message Log message.
     * @return void
     */
    private function log(string $level, string $message): void {
        $entry = [
            'time'    => current_time('mysql'),
            'level'   => $level,
            'message' => $message,
        ];

        $this->log[] = $entry;

        // Also log to error_log for debugging.
        error_log(sprintf('[ERH Price History Migration] [%s] %s', strtoupper($level), $message));
    }

    /**
     * Clear local price history data.
     *
     * Use with caution - this deletes all existing price history!
     *
     * @return int Number of rows deleted.
     */
    public function clear_local_data(): int {
        global $wpdb;

        if ($this->dry_run) {
            $this->log('info', 'Dry run - would clear all price history data');
            return 0;
        }

        $table_name = $wpdb->prefix . ERH_TABLE_PRICE_HISTORY;
        $deleted = $wpdb->query("TRUNCATE TABLE {$table_name}");

        $this->log('info', 'Cleared all local price history data');

        return $deleted !== false ? (int) $deleted : 0;
    }
}
