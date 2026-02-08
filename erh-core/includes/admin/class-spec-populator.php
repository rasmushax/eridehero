<?php
/**
 * Spec Populator - AI-powered bulk spec population admin page.
 *
 * Provides two interfaces:
 * 1. Bulk admin page (Products > Spec Populator) for processing multiple products.
 * 2. Single-product modal on product edit screens.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\CategoryConfig;

/**
 * Admin page for AI-powered spec population.
 */
class SpecPopulator {

    /**
     * Page slug.
     */
    public const PAGE_SLUG = 'erh-spec-populator';

    /**
     * AJAX nonce action.
     */
    private const NONCE_ACTION = 'erh_spec_populator';

    /**
     * Handler instance.
     *
     * @var SpecPopulatorHandler
     */
    private SpecPopulatorHandler $handler;

    /**
     * Perplexity client for config checks.
     *
     * @var PerplexityClient
     */
    private PerplexityClient $perplexity;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->handler = new SpecPopulatorHandler();
        $this->perplexity = new PerplexityClient();
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_bulk_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_modal_assets']);

        // AJAX handlers.
        add_action('wp_ajax_erh_sp_get_products', [$this, 'ajax_get_products']);
        add_action('wp_ajax_erh_sp_fetch_specs', [$this, 'ajax_fetch_specs']);
        add_action('wp_ajax_erh_sp_save_specs', [$this, 'ajax_save_specs']);
    }

    /**
     * Add the admin menu page under Products.
     *
     * @return void
     */
    public function add_menu_page(): void {
        add_submenu_page(
            'edit.php?post_type=products',
            __('Spec Populator', 'erh-core'),
            __('Spec Populator', 'erh-core'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue bulk page assets.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_bulk_assets(string $hook): void {
        if ($hook !== 'products_page_' . self::PAGE_SLUG) {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));

        wp_enqueue_style(
            'erh-spec-populator',
            $plugin_url . 'assets/css/spec-populator.css',
            [],
            ERH_VERSION
        );

        wp_enqueue_script(
            'erh-spec-populator',
            $plugin_url . 'assets/js/spec-populator.js',
            [],
            ERH_VERSION,
            true
        );

        wp_localize_script('erh-spec-populator', 'erhSpecPopulator', [
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce(self::NONCE_ACTION),
            'isConfigured' => $this->perplexity->is_configured(),
            'productTypes' => $this->get_product_types_for_js(),
        ]);
    }

    /**
     * Enqueue modal assets on product edit screens.
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_modal_assets(string $hook): void {
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
            'erh-spec-populator-modal',
            $plugin_url . 'assets/css/spec-populator-modal.css',
            [],
            ERH_VERSION
        );

        wp_enqueue_script(
            'erh-spec-populator-modal',
            $plugin_url . 'assets/js/spec-populator-modal.js',
            [],
            ERH_VERSION,
            true
        );

        // Get product context.
        $product_name = $post_id ? get_the_title($post_id) : '';
        $brand = '';
        $product_type = '';

        if ($post_id) {
            $brand_terms = get_the_terms($post_id, 'brand');
            $brand = ($brand_terms && !is_wp_error($brand_terms)) ? $brand_terms[0]->name : '';

            $type_terms = get_the_terms($post_id, 'product_type');
            $type_label = ($type_terms && !is_wp_error($type_terms)) ? $type_terms[0]->name : '';
            $product_type = $type_label ? CategoryConfig::normalize_key($type_label) : '';
        }

        wp_localize_script('erh-spec-populator-modal', 'erhSpecPopulatorModal', [
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce(self::NONCE_ACTION),
            'productId'    => $post_id,
            'productName'  => $product_name,
            'brand'        => $brand,
            'productType'  => $product_type,
            'isConfigured' => $this->perplexity->is_configured(),
        ]);
    }

    /**
     * Render the bulk admin page.
     *
     * @return void
     */
    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $is_configured = $this->perplexity->is_configured();
        $product_types = $this->get_product_types_for_js();

        ?>
        <div class="wrap erh-spec-populator">
            <h1><?php esc_html_e('Spec Populator', 'erh-core'); ?></h1>
            <p class="description">
                <?php esc_html_e('Use Perplexity AI to automatically populate product specification fields.', 'erh-core'); ?>
            </p>

            <?php if (!$is_configured) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        printf(
                            esc_html__('Perplexity API key not configured. %s to set it up.', 'erh-core'),
                            '<a href="' . esc_url(admin_url('options-general.php?page=erh-settings&tab=apis')) . '">' .
                            esc_html__('Go to API Settings', 'erh-core') . '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php else : ?>

            <div class="erh-sp-container">
                <!-- Step 1: Select Product Type -->
                <div class="erh-sp-step" data-step="1">
                    <h2><?php esc_html_e('Step 1: Select Product Type', 'erh-core'); ?></h2>
                    <div class="erh-sp-type-tabs">
                        <?php foreach ($product_types as $key => $label) : ?>
                            <button type="button"
                                    class="erh-sp-type-tab"
                                    data-type="<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($label); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Step 2: Select Products -->
                <div class="erh-sp-step" data-step="2" style="display: none;">
                    <h2><?php esc_html_e('Step 2: Select Products', 'erh-core'); ?></h2>
                    <div class="erh-sp-filters">
                        <input type="text"
                               id="erh-sp-search"
                               placeholder="<?php esc_attr_e('Search products...', 'erh-core'); ?>"
                               class="erh-sp-search">
                        <select id="erh-sp-brand" class="erh-sp-brand-filter">
                            <option value=""><?php esc_html_e('All Brands', 'erh-core'); ?></option>
                        </select>
                        <select id="erh-sp-status" class="erh-sp-status-filter">
                            <option value="needs-specs"><?php esc_html_e('Needs Specs', 'erh-core'); ?></option>
                            <option value="has-specs"><?php esc_html_e('Has Specs', 'erh-core'); ?></option>
                            <option value="all"><?php esc_html_e('All Products', 'erh-core'); ?></option>
                        </select>
                    </div>
                    <div class="erh-sp-actions-top">
                        <button type="button" class="button" id="erh-sp-check-all">
                            <?php esc_html_e('Check All Visible', 'erh-core'); ?>
                        </button>
                        <button type="button" class="button" id="erh-sp-uncheck-all">
                            <?php esc_html_e('Uncheck All', 'erh-core'); ?>
                        </button>
                        <span class="erh-sp-selected-count">
                            <?php esc_html_e('Selected:', 'erh-core'); ?> <strong>0</strong>
                        </span>
                    </div>
                    <div class="erh-sp-products-list" id="erh-sp-products">
                        <p class="erh-sp-loading"><?php esc_html_e('Select a product type to load products...', 'erh-core'); ?></p>
                    </div>
                </div>

                <!-- Step 3: Configure & Fetch -->
                <div class="erh-sp-step" data-step="3" style="display: none;">
                    <h2><?php esc_html_e('Step 3: Fetch Specs', 'erh-core'); ?></h2>
                    <div class="erh-sp-options">
                        <label class="erh-sp-toggle">
                            <input type="checkbox" id="erh-sp-overwrite">
                            <span><?php esc_html_e('Include fields with existing values', 'erh-core'); ?></span>
                        </label>
                    </div>
                    <button type="button" class="button button-primary" id="erh-sp-fetch">
                        <?php esc_html_e('Fetch Specs with AI', 'erh-core'); ?>
                    </button>
                    <div class="erh-sp-progress" style="display: none;">
                        <div class="erh-sp-progress-bar">
                            <div class="erh-sp-progress-fill"></div>
                        </div>
                        <span class="erh-sp-progress-text"></span>
                    </div>
                </div>

                <!-- Step 4: Review & Save -->
                <div class="erh-sp-step" data-step="4" style="display: none;">
                    <h2><?php esc_html_e('Step 4: Review & Save', 'erh-core'); ?></h2>
                    <div id="erh-sp-results"></div>
                    <div class="erh-sp-actions-bottom">
                        <button type="button" class="button button-primary" id="erh-sp-save" disabled>
                            <?php esc_html_e('Save Selected Specs', 'erh-core'); ?>
                        </button>
                        <span class="erh-sp-save-status"></span>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX: Get products for a product type.
     *
     * @return void
     */
    public function ajax_get_products(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $product_type = isset($_POST['product_type']) ? sanitize_text_field($_POST['product_type']) : '';
        if (empty($product_type)) {
            wp_send_json_error(['message' => __('Product type is required.', 'erh-core')]);
        }

        $normalized = CategoryConfig::normalize_key($product_type);
        if (!isset(CategoryConfig::CATEGORIES[$normalized])) {
            wp_send_json_error(['message' => __('Invalid product type.', 'erh-core')]);
        }

        $type_label = CategoryConfig::CATEGORIES[$normalized]['type'];

        // Query all published products of this type (product_type is a taxonomy).
        $query = new \WP_Query([
            'post_type'      => 'products',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'name',
                    'terms'    => $type_label,
                ],
            ],
        ]);

        $products = [];
        $brands = [];

        foreach ($query->posts as $post) {
            $brand_terms = get_the_terms($post->ID, 'brand');
            $brand = ($brand_terms && !is_wp_error($brand_terms)) ? $brand_terms[0]->name : '';

            if ($brand && !in_array($brand, $brands, true)) {
                $brands[] = $brand;
            }

            $counts = $this->handler->get_field_counts($post->ID, $normalized);

            $products[] = [
                'id'           => $post->ID,
                'name'         => $post->post_title,
                'brand'        => $brand,
                'total_fields' => $counts['total'],
                'empty_fields' => $counts['empty'],
            ];
        }

        sort($brands);

        wp_send_json_success([
            'products' => $products,
            'brands'   => $brands,
        ]);
    }

    /**
     * AJAX: Fetch AI-generated specs for a product.
     *
     * @return void
     */
    public function ajax_fetch_specs(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $product_type = isset($_POST['product_type']) ? sanitize_text_field($_POST['product_type']) : '';
        $overwrite = !empty($_POST['overwrite_existing']);

        if (!$product_id || empty($product_type)) {
            wp_send_json_error(['message' => __('Missing required parameters.', 'erh-core')]);
        }

        $result = $this->handler->fetch_specs($product_id, $product_type, $overwrite);

        if (!$result['success'] && !isset($result['empty'])) {
            wp_send_json_error([
                'message'    => $result['error'] ?? __('Unknown error.', 'erh-core'),
                'product_id' => $product_id,
            ]);
        }

        wp_send_json_success(array_merge($result, ['product_id' => $product_id]));
    }

    /**
     * AJAX: Save validated specs to a product.
     *
     * @return void
     */
    public function ajax_save_specs(): void {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $product_type = isset($_POST['product_type']) ? sanitize_text_field($_POST['product_type']) : '';
        $specs_json = isset($_POST['specs']) ? stripslashes($_POST['specs']) : '';

        if (!$product_id || empty($product_type) || empty($specs_json)) {
            wp_send_json_error(['message' => __('Missing required parameters.', 'erh-core')]);
        }

        $specs = json_decode($specs_json, true);
        if (!is_array($specs)) {
            wp_send_json_error(['message' => __('Invalid specs data.', 'erh-core')]);
        }

        $result = $this->handler->save_specs($product_id, $specs, $product_type);

        wp_send_json_success([
            'product_id' => $product_id,
            'saved'      => $result['saved'],
            'errors'     => $result['errors'],
        ]);
    }

    /**
     * Get product types formatted for JavaScript.
     *
     * @return array<string, string> Key => label mapping.
     */
    private function get_product_types_for_js(): array {
        $types = [];
        foreach (CategoryConfig::CATEGORIES as $key => $config) {
            $types[$key] = $config['name'];
        }
        return $types;
    }
}
