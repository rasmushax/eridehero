<?php
/**
 * Price History Editor - Meta box for viewing/editing wp_product_daily_prices.
 *
 * Adds a meta box on the product edit screen to view, edit, and delete
 * price history entries. Fixes bad price data without needing phpMyAdmin.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Database\PriceHistory;
use ERH\CacheKeys;

/**
 * Admin meta box for managing product price history.
 */
class PriceHistoryEditor {

    /**
     * AJAX nonce action.
     */
    private const NONCE_ACTION = 'erh_price_history_editor';

    /**
     * Price history database instance.
     *
     * @var PriceHistory
     */
    private PriceHistory $price_history;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->price_history = new PriceHistory();
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers.
        add_action('wp_ajax_erh_phe_get_history', [$this, 'ajax_get_history']);
        add_action('wp_ajax_erh_phe_delete_rows', [$this, 'ajax_delete_rows']);
        add_action('wp_ajax_erh_phe_delete_range', [$this, 'ajax_delete_range']);
        add_action('wp_ajax_erh_phe_update_price', [$this, 'ajax_update_price']);
        add_action('wp_ajax_erh_phe_clear_all', [$this, 'ajax_clear_all']);
    }

    /**
     * Register meta box on products edit screen.
     *
     * @return void
     */
    public function add_meta_box(): void {
        add_meta_box(
            'erh-price-history-editor',
            __('Price History Editor', 'erh-core'),
            [$this, 'render_meta_box'],
            'products',
            'normal',
            'low'
        );
    }

    /**
     * Enqueue assets on product edit screens.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets(string $hook): void {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'products') {
            return;
        }

        $post_id = (int) ($_GET['post'] ?? 0);
        if (!$post_id && $hook === 'post.php') {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));

        wp_enqueue_style(
            'erh-price-history-editor',
            $plugin_url . 'assets/css/price-history-editor.css',
            [],
            ERH_VERSION
        );

        wp_enqueue_script(
            'erh-price-history-editor',
            $plugin_url . 'assets/js/price-history-editor.js',
            [],
            ERH_VERSION,
            true
        );

        // Get available geos for this product.
        $available_geos = $post_id ? $this->price_history->get_available_geos($post_id) : [];

        wp_localize_script('erh-price-history-editor', 'erhPriceHistoryEditor', [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce(self::NONCE_ACTION),
            'productId'     => $post_id,
            'availableGeos' => $available_geos,
        ]);
    }

    /**
     * Render the meta box shell.
     *
     * @param \WP_Post $post Current post object.
     * @return void
     */
    public function render_meta_box(\WP_Post $post): void {
        ?>
        <div id="erh-phe-wrap">
            <div class="erh-phe-toolbar">
                <label for="erh-phe-geo-filter"><?php esc_html_e('Region:', 'erh-core'); ?></label>
                <select id="erh-phe-geo-filter">
                    <option value=""><?php esc_html_e('All Regions', 'erh-core'); ?></option>
                </select>
                <span id="erh-phe-record-count" class="erh-phe-count"></span>
            </div>

            <div id="erh-phe-table-wrap">
                <table class="widefat striped" id="erh-phe-table">
                    <thead>
                        <tr>
                            <th class="erh-phe-col-check"><input type="checkbox" id="erh-phe-check-all"></th>
                            <th><?php esc_html_e('Date', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Price', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Currency', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Domain', 'erh-core'); ?></th>
                            <th><?php esc_html_e('Geo', 'erh-core'); ?></th>
                            <th class="erh-phe-col-actions"></th>
                        </tr>
                    </thead>
                    <tbody id="erh-phe-body">
                        <tr><td colspan="7" class="erh-phe-loading"><?php esc_html_e('Loading...', 'erh-core'); ?></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="erh-phe-actions">
                <button type="button" class="button" id="erh-phe-delete-selected" disabled>
                    <?php esc_html_e('Delete Selected', 'erh-core'); ?>
                </button>
                <span id="erh-phe-selected-count"></span>

                <button type="button" class="button" id="erh-phe-toggle-range">
                    <?php esc_html_e('Bulk Range...', 'erh-core'); ?>
                </button>
                <button type="button" class="button erh-phe-danger" id="erh-phe-clear-all">
                    <?php esc_html_e('Clear All', 'erh-core'); ?>
                </button>
            </div>

            <div id="erh-phe-range-panel" class="erh-phe-range-panel" style="display:none;">
                <label><?php esc_html_e('From:', 'erh-core'); ?>
                    <input type="date" id="erh-phe-range-from">
                </label>
                <label><?php esc_html_e('To:', 'erh-core'); ?>
                    <input type="date" id="erh-phe-range-to">
                </label>
                <label><?php esc_html_e('Geo:', 'erh-core'); ?>
                    <select id="erh-phe-range-geo">
                        <option value=""><?php esc_html_e('All', 'erh-core'); ?></option>
                    </select>
                </label>
                <button type="button" class="button button-primary" id="erh-phe-delete-range">
                    <?php esc_html_e('Delete Range', 'erh-core'); ?>
                </button>
                <button type="button" class="button" id="erh-phe-cancel-range">
                    <?php esc_html_e('Cancel', 'erh-core'); ?>
                </button>
            </div>

            <div id="erh-phe-status" class="erh-phe-status" style="display:none;"></div>
        </div>
        <?php
    }

    /**
     * AJAX: Get price history rows for the product.
     *
     * @return void
     */
    public function ajax_get_history(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $product_id = (int) ($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'erh-core')]);
        }

        $geo = isset($_POST['geo']) && $_POST['geo'] !== '' ? sanitize_text_field($_POST['geo']) : null;

        $rows = $this->price_history->get_history($product_id, 0, $geo, null, 'DESC');

        wp_send_json_success([
            'rows'  => $rows,
            'count' => count($rows),
        ]);
    }

    /**
     * AJAX: Delete specific rows by ID array.
     *
     * @return void
     */
    public function ajax_delete_rows(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $product_id = (int) ($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'erh-core')]);
        }

        $ids = isset($_POST['ids']) ? array_map('intval', (array) $_POST['ids']) : [];
        if (empty($ids)) {
            wp_send_json_error(['message' => __('No rows selected.', 'erh-core')]);
        }

        $deleted = $this->price_history->delete_by_ids($product_id, $ids);

        CacheKeys::clearPriceCaches($product_id);

        wp_send_json_success([
            'deleted' => $deleted,
            'message' => sprintf(
                /* translators: %d: number of rows deleted */
                __('%d row(s) deleted.', 'erh-core'),
                $deleted
            ),
        ]);
    }

    /**
     * AJAX: Delete rows in a date range.
     *
     * @return void
     */
    public function ajax_delete_range(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $product_id = (int) ($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'erh-core')]);
        }

        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');

        if (!$date_from || !$date_to) {
            wp_send_json_error(['message' => __('Both dates are required.', 'erh-core')]);
        }

        // Validate date format.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
            wp_send_json_error(['message' => __('Invalid date format.', 'erh-core')]);
        }

        $geo = isset($_POST['geo']) && $_POST['geo'] !== '' ? sanitize_text_field($_POST['geo']) : null;

        $deleted = $this->price_history->delete_date_range($product_id, $date_from, $date_to, $geo);

        CacheKeys::clearPriceCaches($product_id);

        wp_send_json_success([
            'deleted' => $deleted,
            'message' => sprintf(
                /* translators: %d: number of rows deleted */
                __('%d row(s) deleted.', 'erh-core'),
                $deleted
            ),
        ]);
    }

    /**
     * AJAX: Update a single row's price.
     *
     * @return void
     */
    public function ajax_update_price(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $product_id = (int) ($_POST['product_id'] ?? 0);
        $row_id = (int) ($_POST['row_id'] ?? 0);
        $price = isset($_POST['price']) ? (float) $_POST['price'] : -1;

        if (!$product_id || !$row_id) {
            wp_send_json_error(['message' => __('Invalid parameters.', 'erh-core')]);
        }

        if ($price < 0) {
            wp_send_json_error(['message' => __('Price must be non-negative.', 'erh-core')]);
        }

        $updated = $this->price_history->update_price($product_id, $row_id, $price);

        if (!$updated) {
            wp_send_json_error(['message' => __('Failed to update price.', 'erh-core')]);
        }

        CacheKeys::clearPriceCaches($product_id);

        wp_send_json_success([
            'message' => __('Price updated.', 'erh-core'),
        ]);
    }

    /**
     * AJAX: Clear all price history for the product.
     *
     * @return void
     */
    public function ajax_clear_all(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $product_id = (int) ($_POST['product_id'] ?? 0);
        if (!$product_id) {
            wp_send_json_error(['message' => __('Invalid product ID.', 'erh-core')]);
        }

        $this->price_history->delete_for_product($product_id);

        CacheKeys::clearPriceCaches($product_id);

        wp_send_json_success([
            'message' => __('All price history cleared.', 'erh-core'),
        ]);
    }
}
