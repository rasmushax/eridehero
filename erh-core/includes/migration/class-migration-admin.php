<?php
/**
 * Migration Admin Page
 *
 * @package ERH\Migration
 */

namespace ERH\Migration;

/**
 * Admin page for running product migrations.
 */
class MigrationAdmin {

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_post_erh_run_migration', [$this, 'handle_migration']);
        add_action('admin_post_erh_test_migration', [$this, 'handle_test_connection']);
    }

    /**
     * Add admin menu page.
     *
     * @return void
     */
    public function add_menu_page(): void {
        add_submenu_page(
            'options-general.php',
            'ERH Product Migration',
            'ERH Migration',
            'manage_options',
            'erh-migration',
            [$this, 'render_page']
        );
    }

    /**
     * Render the admin page.
     *
     * @return void
     */
    public function render_page(): void {
        $message = '';
        $message_type = '';

        // Check for messages from redirects.
        if (isset($_GET['migrated'])) {
            $migrated = (int) $_GET['migrated'];
            $skipped = (int) ($_GET['skipped'] ?? 0);
            $message = "Migration complete! Migrated: {$migrated}, Skipped: {$skipped}";
            $message_type = 'success';
        } elseif (isset($_GET['error'])) {
            $message = sanitize_text_field($_GET['error']);
            $message_type = 'error';
        } elseif (isset($_GET['test'])) {
            $test = sanitize_text_field($_GET['test']);
            if ($test === 'success') {
                $count = (int) ($_GET['count'] ?? 0);
                $message = "Connection successful! Found {$count} products.";
                $message_type = 'success';
            } else {
                $message = "Connection test failed: " . sanitize_text_field($_GET['reason'] ?? 'Unknown error');
                $message_type = 'error';
            }
        }

        ?>
        <div class="wrap">
            <h1>ERH Product Migration</h1>

            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 600px; padding: 20px;">
                <h2>Import Products from Remote Site</h2>
                <p>This tool will fetch products from the source site and import them with the new ACF structure.</p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('erh_migration', 'erh_migration_nonce'); ?>
                    <input type="hidden" name="action" value="erh_run_migration">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="source_url">Source Site URL</label>
                            </th>
                            <td>
                                <input type="url"
                                       name="source_url"
                                       id="source_url"
                                       class="regular-text"
                                       value="https://eridehero.com"
                                       placeholder="https://eridehero.com"
                                       required>
                                <p class="description">The source site must have REST API enabled for products.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="batch_size">Batch Size</label>
                            </th>
                            <td>
                                <input type="number"
                                       name="batch_size"
                                       id="batch_size"
                                       value="50"
                                       min="10"
                                       max="100"
                                       class="small-text">
                                <p class="description">Products to process per request (10-100).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Options</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="dry_run" value="1">
                                    Dry Run (preview only, don't import)
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit"
                                name="test_connection"
                                class="button button-secondary"
                                formaction="<?php echo esc_url(admin_url('admin-post.php?action=erh_test_migration')); ?>">
                            Test Connection
                        </button>
                        <button type="submit" class="button button-primary">
                            Run Migration
                        </button>
                    </p>
                </form>
            </div>

            <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                <h2>Migration Notes</h2>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><strong>Brand</strong>: Text field → <code>brands</code> taxonomy</li>
                    <li><strong>Product Type</strong>: Select field → <code>product_type</code> taxonomy</li>
                    <li><strong>Image</strong>: Uses <code>big_thumbnail</code>, falls back to <code>thumbnail</code></li>
                    <li><strong>Rating</strong>: Uses <code>ratings.overall</code> or averages individual ratings</li>
                    <li><strong>Existing products</strong>: Matched by slug and updated</li>
                </ul>

                <h3>Field Mapping</h3>
                <p>Old flat fields are mapped to new nested groups:</p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><code>acceleration:_0-15_mph</code> → <code>acceleration.to_15</code></li>
                    <li><code>tested_range_fast</code> → <code>range_tests.fast</code></li>
                    <li><code>e-bikes.motor.*</code> → <code>ebike_motor.*</code></li>
                </ul>
            </div>

            <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                <h2>Before Migrating</h2>
                <ol style="margin-left: 20px;">
                    <li>Import the new ACF field structure via <strong>ACF → Tools → Import</strong></li>
                    <li>File: <code>acf-import-new-structure.json</code></li>
                    <li>On the <strong>source site</strong>, ensure ACF fields are exposed to REST API:
                        <ul style="list-style: circle; margin-left: 20px; margin-top: 5px;">
                            <li>ACF field groups need <code>Show in REST API</code> enabled</li>
                            <li>Or install the "ACF to REST API" plugin</li>
                        </ul>
                    </li>
                    <li>Run a <strong>Dry Run</strong> first to verify</li>
                </ol>

                <h3 style="margin-top: 15px;">If ACF Fields Are Empty</h3>
                <p>The migrator will try to <strong>infer product type</strong> from the product name (e.g., "eRide" → Electric Bike, "Kaabo" → Electric Scooter).</p>
                <p>Check <code>wp-content/debug.log</code> for details.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Handle migration form submission.
     *
     * @return void
     */
    public function handle_migration(): void {
        // Verify nonce.
        if (!isset($_POST['erh_migration_nonce']) ||
            !wp_verify_nonce($_POST['erh_migration_nonce'], 'erh_migration')) {
            wp_die('Security check failed');
        }

        // Check capabilities.
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $source_url = esc_url_raw($_POST['source_url'] ?? '');
        $batch_size = (int) ($_POST['batch_size'] ?? 50);
        $dry_run = !empty($_POST['dry_run']);

        if (empty($source_url)) {
            wp_redirect(add_query_arg('error', 'Source URL is required', admin_url('options-general.php?page=erh-migration')));
            exit;
        }

        // Run migration.
        $migrator = new ProductMigrator($source_url);
        $migrator->set_dry_run($dry_run);

        // Increase time limit for large migrations.
        set_time_limit(600);

        $summary = $migrator->run_remote_migration($batch_size);

        // Redirect with results.
        $redirect_url = add_query_arg([
            'migrated' => $summary['migrated'],
            'skipped'  => $summary['skipped'],
        ], admin_url('options-general.php?page=erh-migration'));

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Handle test connection.
     *
     * @return void
     */
    public function handle_test_connection(): void {
        // Verify nonce.
        if (!isset($_POST['erh_migration_nonce']) ||
            !wp_verify_nonce($_POST['erh_migration_nonce'], 'erh_migration')) {
            wp_die('Security check failed');
        }

        // Check capabilities.
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $source_url = esc_url_raw($_POST['source_url'] ?? '');

        if (empty($source_url)) {
            wp_redirect(add_query_arg([
                'test'   => 'failed',
                'reason' => 'Source URL is required',
            ], admin_url('options-general.php?page=erh-migration')));
            exit;
        }

        // Test connection.
        $migrator = new ProductMigrator($source_url);
        $result = $migrator->fetch_remote_products(1, 1);

        if ($result) {
            wp_redirect(add_query_arg([
                'test'  => 'success',
                'count' => $result['total'],
            ], admin_url('options-general.php?page=erh-migration')));
        } else {
            wp_redirect(add_query_arg([
                'test'   => 'failed',
                'reason' => 'Could not fetch products',
            ], admin_url('options-general.php?page=erh-migration')));
        }
        exit;
    }
}
