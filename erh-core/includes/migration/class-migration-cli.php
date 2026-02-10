<?php
/**
 * WP-CLI commands for product migration.
 *
 * Wraps the ProductMigrator for command-line usage during staging setup.
 *
 * @package ERH\Migration
 */

declare(strict_types=1);

namespace ERH\Migration;

/**
 * WP-CLI commands for ERH migration tasks.
 */
class MigrationCli {

    /**
     * Register WP-CLI commands.
     *
     * @return void
     */
    public static function register(): void {
        if (!defined('WP_CLI') || !\WP_CLI) {
            return;
        }

        \WP_CLI::add_command('erh migrate-products', [new self(), 'run_migration']);
        \WP_CLI::add_command('erh migrate-single', [new self(), 'run_single']);
        \WP_CLI::add_command('erh migrate-social', [new self(), 'run_social_migration']);
    }

    /**
     * Run full product migration from production.
     *
     * Fetches products from the source site REST API and restructures
     * ACF fields from the old flat format to the new nested format.
     * When products already exist (by ID), updates them in place.
     *
     * ## OPTIONS
     *
     * [--source=<url>]
     * : Source site URL.
     * ---
     * default: https://eridehero.com
     * ---
     *
     * [--batch-size=<number>]
     * : Products per batch.
     * ---
     * default: 50
     * ---
     *
     * [--dry-run]
     * : Preview changes without writing.
     *
     * ## EXAMPLES
     *
     *     wp erh migrate --source=https://eridehero.com --batch-size=50
     *     wp erh migrate --dry-run
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     * @return void
     */
    public function run_migration(array $args, array $assoc_args): void {
        $source     = $assoc_args['source'] ?? 'https://eridehero.com';
        $batch_size = (int) ($assoc_args['batch-size'] ?? 50);
        $dry_run    = isset($assoc_args['dry-run']);

        $migrator = new ProductMigrator($source);
        $migrator->set_dry_run($dry_run);

        if ($dry_run) {
            \WP_CLI::log('DRY RUN - no changes will be made.');
        }

        \WP_CLI::log("Source: {$source}");
        \WP_CLI::log("Batch size: {$batch_size}");
        \WP_CLI::log('Starting migration...');
        \WP_CLI::log('');

        $start   = microtime(true);
        $summary = $migrator->run_remote_migration($batch_size);
        $elapsed = round(microtime(true) - $start, 1);

        \WP_CLI::log('');
        \WP_CLI::log('--- Migration Summary ---');
        \WP_CLI::log("Total products: {$summary['total']}");
        \WP_CLI::log("Migrated: {$summary['migrated']}");
        \WP_CLI::log("Skipped: {$summary['skipped']}");
        \WP_CLI::log("Errors: {$summary['errors']}");
        \WP_CLI::log("Time: {$elapsed}s");

        if ($summary['errors'] > 0) {
            \WP_CLI::warning("{$summary['errors']} products had errors. Check debug.log for details.");
        } else {
            \WP_CLI::success('Migration complete.');
        }
    }

    /**
     * Migrate a single product by ID or slug.
     *
     * ## OPTIONS
     *
     * <identifier>
     * : Product ID (numeric) or slug.
     *
     * [--source=<url>]
     * : Source site URL.
     * ---
     * default: https://eridehero.com
     * ---
     *
     * [--dry-run]
     * : Preview changes without writing.
     *
     * ## EXAMPLES
     *
     *     wp erh migrate single 123
     *     wp erh migrate single niu-kqi-300x --dry-run
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     * @return void
     */
    public function run_single(array $args, array $assoc_args): void {
        if (empty($args[0])) {
            \WP_CLI::error('Please provide a product ID or slug.');
            return;
        }

        $identifier = $args[0];
        $source     = $assoc_args['source'] ?? 'https://eridehero.com';
        $dry_run    = isset($assoc_args['dry-run']);

        $migrator = new ProductMigrator($source);
        $migrator->set_dry_run($dry_run);

        if ($dry_run) {
            \WP_CLI::log('DRY RUN - no changes will be made.');
        }

        \WP_CLI::log("Migrating product: {$identifier}");

        $result = $migrator->migrate_single_product($identifier);

        if ($result['success']) {
            \WP_CLI::success("Migrated: {$result['product_name']}");
        } else {
            \WP_CLI::error($result['reason']);
        }
    }

    /**
     * Migrate social login data from NSL wp_social_users table to ERH user meta.
     *
     * Reads from wp_social_users (imported from production) and writes
     * to wp_usermeta as erh_google_id, erh_facebook_id, erh_reddit_id,
     * and social_login_provider.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Preview changes without writing.
     *
     * ## EXAMPLES
     *
     *     wp erh migrate social
     *     wp erh migrate social --dry-run
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Named arguments.
     * @return void
     */
    public function run_social_migration(array $args, array $assoc_args): void {
        global $wpdb;

        $dry_run    = isset($assoc_args['dry-run']);
        $table_name = $wpdb->prefix . 'social_users';

        // Check if source table exists.
        $table_exists = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table_name)
        );

        if (!$table_exists) {
            \WP_CLI::error("Table {$table_name} not found. Import it from production first.");
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        \WP_CLI::log("Found {$total} social login records in {$table_name}.");

        if ($total === 0) {
            \WP_CLI::warning('No records to migrate.');
            return;
        }

        if ($dry_run) {
            \WP_CLI::log('DRY RUN - no changes will be made.');
        }

        // Map NSL types to ERH meta keys.
        $type_map = [
            'google'   => 'erh_google_id',
            'facebook' => 'erh_facebook_id',
            'fb'       => 'erh_facebook_id',
            'reddit'   => 'erh_reddit_id',
        ];

        $provider_label_map = [
            'google'   => 'google',
            'facebook' => 'facebook',
            'fb'       => 'facebook',
            'reddit'   => 'reddit',
        ];

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $records = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY ID ASC", ARRAY_A);

        $migrated   = 0;
        $skipped    = 0;
        $errors     = 0;
        $providers  = []; // Track latest provider per user for social_login_provider.

        foreach ($records as $record) {
            $user_id    = (int) ($record['ID'] ?? 0);
            $type       = strtolower(trim($record['type'] ?? ''));
            $identifier = $record['identifier'] ?? '';

            if (!$user_id || empty($type) || empty($identifier)) {
                \WP_CLI::warning("Skipping invalid record: user_id={$user_id}, type={$type}");
                $skipped++;
                continue;
            }

            // Check user exists.
            $user = get_user_by('id', $user_id);
            if (!$user) {
                \WP_CLI::warning("User #{$user_id} not found, skipping.");
                $skipped++;
                continue;
            }

            $meta_key = $type_map[$type] ?? null;
            if (!$meta_key) {
                \WP_CLI::warning("Unknown social type '{$type}' for user #{$user_id}, skipping.");
                $skipped++;
                continue;
            }

            // Check if already migrated.
            $existing = get_user_meta($user_id, $meta_key, true);
            if (!empty($existing)) {
                \WP_CLI::log("User #{$user_id} already has {$meta_key}, skipping.");
                $skipped++;
                continue;
            }

            if (!$dry_run) {
                $result = update_user_meta($user_id, $meta_key, $identifier);
                if ($result === false) {
                    \WP_CLI::warning("Failed to set {$meta_key} for user #{$user_id}.");
                    $errors++;
                    continue;
                }
            }

            \WP_CLI::log("Set {$meta_key} for user #{$user_id}");
            $migrated++;

            // Track the provider (use login_date if available for most recent).
            $login_date = $record['login_date'] ?? '';
            $provider   = $provider_label_map[$type] ?? $type;

            if (!isset($providers[$user_id]) || $login_date > $providers[$user_id]['date']) {
                $providers[$user_id] = [
                    'provider' => $provider,
                    'date'     => $login_date,
                ];
            }
        }

        // Set social_login_provider for each user (most recent provider).
        $provider_count = 0;
        foreach ($providers as $user_id => $info) {
            $existing = get_user_meta($user_id, 'social_login_provider', true);
            if (!empty($existing)) {
                continue;
            }

            if (!$dry_run) {
                update_user_meta($user_id, 'social_login_provider', $info['provider']);
            }
            $provider_count++;
        }

        \WP_CLI::log('');
        \WP_CLI::log('--- Social Login Migration Summary ---');
        \WP_CLI::log("Migrated: {$migrated}");
        \WP_CLI::log("Skipped: {$skipped}");
        \WP_CLI::log("Errors: {$errors}");
        \WP_CLI::log("Primary providers set: {$provider_count}");

        if ($errors > 0) {
            \WP_CLI::warning("{$errors} records had errors.");
        } else {
            \WP_CLI::success('Social login migration complete.');
        }
    }
}
