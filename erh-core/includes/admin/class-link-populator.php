<?php
/**
 * Link Populator - Bulk add tracked links to HFT using Perplexity AI and Amazon PA-API.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

/**
 * Admin page for bulk-populating HFT tracked links.
 */
class LinkPopulator {

    /**
     * Page slug.
     */
    public const PAGE_SLUG = 'erh-link-populator';

    /**
     * Perplexity client instance (for scraper URLs).
     *
     * @var PerplexityClient
     */
    private PerplexityClient $perplexity;

    /**
     * Amazon API client instance (for ASIN searches).
     *
     * @var AmazonApiClient
     */
    private AmazonApiClient $amazon;

    /**
     * URL verifier instance.
     *
     * @var UrlVerifier
     */
    private UrlVerifier $verifier;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->perplexity = new PerplexityClient();
        $this->amazon = new AmazonApiClient();
        $this->verifier = new UrlVerifier();
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        // Only register if HFT plugin is active.
        if (!defined('HFT_VERSION')) {
            return;
        }

        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers.
        add_action('wp_ajax_erh_lp_get_scrapers', [$this, 'ajax_get_scrapers']);
        add_action('wp_ajax_erh_lp_get_products', [$this, 'ajax_get_products']);
        add_action('wp_ajax_erh_lp_find_urls', [$this, 'ajax_find_urls']);
        add_action('wp_ajax_erh_lp_verify_urls', [$this, 'ajax_verify_urls']);
        add_action('wp_ajax_erh_lp_add_links', [$this, 'ajax_add_links']);

        // Amazon-specific AJAX handlers.
        add_action('wp_ajax_erh_lp_get_amazon_products', [$this, 'ajax_get_amazon_products']);
        add_action('wp_ajax_erh_lp_find_amazon_urls', [$this, 'ajax_find_amazon_urls']);
        add_action('wp_ajax_erh_lp_add_amazon_links', [$this, 'ajax_add_amazon_links']);
    }

    /**
     * Add the admin menu page.
     *
     * @return void
     */
    public function add_menu_page(): void {
        add_submenu_page(
            'options-general.php',
            __('Link Populator', 'erh-core'),
            __('Link Populator', 'erh-core'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook The current admin page hook.
     * @return void
     */
    public function enqueue_assets(string $hook): void {
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));

        wp_enqueue_style(
            'erh-link-populator',
            $plugin_url . 'assets/css/link-populator.css',
            [],
            defined('ERH_VERSION') ? ERH_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'erh-link-populator',
            $plugin_url . 'assets/js/link-populator.js',
            [],
            defined('ERH_VERSION') ? ERH_VERSION : '1.0.0',
            true
        );

        wp_localize_script('erh-link-populator', 'erhLinkPopulator', [
            'ajaxUrl'             => admin_url('admin-ajax.php'),
            'adminUrl'            => admin_url(),
            'nonce'               => wp_create_nonce('erh_link_populator'),
            'amazonLocales'       => $this->amazon->get_available_locales(),
            'amazonConfigured'    => $this->amazon->is_configured(),
            'perplexityConfigured'=> $this->perplexity->is_configured(),
            'i18n'                => [
                'loading'       => __('Loading...', 'erh-core'),
                'findingUrls'   => __('Finding URLs...', 'erh-core'),
                'findingAsins'  => __('Finding ASINs...', 'erh-core'),
                'verifying'     => __('Verifying...', 'erh-core'),
                'adding'        => __('Adding links...', 'erh-core'),
                'noProducts'    => __('No products selected.', 'erh-core'),
                'noResults'     => __('No valid URLs found.', 'erh-core'),
                'success'       => __('Links added successfully!', 'erh-core'),
                'error'         => __('An error occurred.', 'erh-core'),
                'confirmAdd'    => __('Add selected links to HFT?', 'erh-core'),
                'productOf'     => __('Product %1$d of %2$d', 'erh-core'),
            ],
        ]);
    }

    /**
     * Render the admin page.
     *
     * @return void
     */
    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if Perplexity is configured.
        $is_configured = $this->perplexity->is_configured();

        ?>
        <div class="wrap erh-link-populator">
            <h1><?php esc_html_e('Link Populator', 'erh-core'); ?></h1>
            <p class="description">
                <?php esc_html_e('Use Perplexity AI to find product URLs and bulk-add them to HFT tracked links.', 'erh-core'); ?>
            </p>

            <?php if (!$is_configured) : ?>
                <div class="notice notice-warning">
                    <p>
                        <?php
                        printf(
                            /* translators: %s: settings page URL */
                            esc_html__('Perplexity API key not configured. %s to set it up.', 'erh-core'),
                            '<a href="' . esc_url(admin_url('options-general.php?page=erh-settings&tab=apis')) . '">' .
                            esc_html__('Go to API Settings', 'erh-core') . '</a>'
                        );
                        ?>
                    </p>
                </div>
            <?php else : ?>

            <div class="erh-lp-container">
                <!-- Step 1: Select Source -->
                <div class="erh-lp-step" data-step="1">
                    <h2><?php esc_html_e('Step 1: Select Source', 'erh-core'); ?></h2>

                    <!-- Mode Selector -->
                    <div class="erh-lp-mode-selector">
                        <label class="erh-lp-mode-option">
                            <input type="radio" name="erh-lp-mode" value="scraper" checked>
                            <span class="erh-lp-mode-label"><?php esc_html_e('Scraper', 'erh-core'); ?></span>
                            <span class="erh-lp-mode-desc"><?php esc_html_e('Use existing HFT scraper', 'erh-core'); ?></span>
                        </label>
                        <label class="erh-lp-mode-option">
                            <input type="radio" name="erh-lp-mode" value="amazon">
                            <span class="erh-lp-mode-label"><?php esc_html_e('Amazon', 'erh-core'); ?></span>
                            <span class="erh-lp-mode-desc"><?php esc_html_e('Find ASINs with Perplexity Pro', 'erh-core'); ?></span>
                        </label>
                    </div>

                    <!-- Scraper Select (shown when mode=scraper) -->
                    <div class="erh-lp-field erh-lp-scraper-field">
                        <select id="erh-lp-scraper" class="erh-lp-select">
                            <option value=""><?php esc_html_e('Loading scrapers...', 'erh-core'); ?></option>
                        </select>
                    </div>

                    <!-- Amazon Locale Select (shown when mode=amazon) -->
                    <div class="erh-lp-field erh-lp-amazon-field" style="display: none;">
                        <?php if ($this->amazon->is_configured()) : ?>
                            <select id="erh-lp-amazon-locale" class="erh-lp-select">
                                <?php foreach ($this->amazon->get_available_locales() as $code => $domain) : ?>
                                    <option value="<?php echo esc_attr($code); ?>">
                                        <?php echo esc_html($code . ' - ' . $domain); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Uses Amazon Creators API SearchItems endpoint.', 'erh-core'); ?>
                            </p>
                        <?php else : ?>
                            <div class="notice notice-warning inline" style="margin: 0;">
                                <p>
                                    <?php echo esc_html($this->amazon->get_configuration_error()); ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=housefresh-tools-settings')); ?>">
                                        <?php esc_html_e('Configure in HFT Settings', 'erh-core'); ?>
                                    </a>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Step 2: Select Products -->
                <div class="erh-lp-step" data-step="2" style="display: none;">
                    <h2><?php esc_html_e('Step 2: Select Products', 'erh-core'); ?></h2>
                    <div class="erh-lp-filters">
                        <input type="text"
                               id="erh-lp-search"
                               placeholder="<?php esc_attr_e('Search products...', 'erh-core'); ?>"
                               class="erh-lp-search">
                        <select id="erh-lp-brand" class="erh-lp-brand-filter">
                            <option value=""><?php esc_html_e('All Brands', 'erh-core'); ?></option>
                        </select>
                        <select id="erh-lp-status" class="erh-lp-status-filter">
                            <option value="no-link"><?php esc_html_e('Without Links', 'erh-core'); ?></option>
                            <option value="has-link"><?php esc_html_e('With Links', 'erh-core'); ?></option>
                            <option value="all"><?php esc_html_e('All Products', 'erh-core'); ?></option>
                        </select>
                    </div>
                    <div class="erh-lp-actions-top">
                        <button type="button" class="button" id="erh-lp-check-all">
                            <?php esc_html_e('Check All Visible', 'erh-core'); ?>
                        </button>
                        <button type="button" class="button" id="erh-lp-uncheck-all">
                            <?php esc_html_e('Uncheck All', 'erh-core'); ?>
                        </button>
                        <span class="erh-lp-selected-count">
                            <?php esc_html_e('Selected:', 'erh-core'); ?> <strong>0</strong>
                        </span>
                    </div>
                    <div class="erh-lp-products-list" id="erh-lp-products">
                        <p class="erh-lp-loading"><?php esc_html_e('Select a scraper to load products...', 'erh-core'); ?></p>
                    </div>
                </div>

                <!-- Step 3: Find URLs -->
                <div class="erh-lp-step" data-step="3" style="display: none;">
                    <h2><?php esc_html_e('Step 3: Find URLs', 'erh-core'); ?></h2>
                    <button type="button" class="button button-primary" id="erh-lp-find-urls">
                        <?php esc_html_e('Find URLs with Perplexity', 'erh-core'); ?>
                    </button>
                    <div class="erh-lp-progress" style="display: none;">
                        <div class="erh-lp-progress-bar">
                            <div class="erh-lp-progress-fill"></div>
                        </div>
                        <span class="erh-lp-progress-text"></span>
                    </div>
                </div>

                <!-- Step 4: Review & Add -->
                <div class="erh-lp-step" data-step="4" style="display: none;">
                    <h2><?php esc_html_e('Step 4: Review & Add', 'erh-core'); ?></h2>
                    <table class="wp-list-table widefat fixed striped" id="erh-lp-results">
                        <thead>
                            <tr>
                                <th class="check-column"><input type="checkbox" id="erh-lp-check-all-results"></th>
                                <th><?php esc_html_e('Product', 'erh-core'); ?></th>
                                <th><?php esc_html_e('URL Found', 'erh-core'); ?></th>
                                <th><?php esc_html_e('Status', 'erh-core'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <div class="erh-lp-actions-bottom">
                        <button type="button" class="button button-primary" id="erh-lp-add-links" disabled>
                            <?php esc_html_e('Add Links to HFT', 'erh-core'); ?>
                        </button>
                        <span class="erh-lp-add-count"></span>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX: Get scrapers list.
     *
     * @return void
     */
    public function ajax_get_scrapers(): void {
        check_ajax_referer('erh_link_populator', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        global $wpdb;

        $scrapers = $wpdb->get_results(
            "SELECT id, domain, name, currency, geos
             FROM {$wpdb->prefix}hft_scrapers
             WHERE is_active = 1
             ORDER BY name ASC",
            ARRAY_A
        );

        if (!$scrapers) {
            wp_send_json_error(['message' => __('No active scrapers found.', 'erh-core')]);
        }

        // Format scrapers for display.
        $formatted = [];
        foreach ($scrapers as $scraper) {
            $formatted[] = [
                'id'       => (int) $scraper['id'],
                'domain'   => $scraper['domain'],
                'name'     => $scraper['name'],
                'currency' => $scraper['currency'] ?: 'USD',
                'geos'     => $scraper['geos'] ?: 'US',
                'label'    => sprintf(
                    '%s (%s, %s)',
                    $scraper['name'],
                    $scraper['geos'] ?: 'US',
                    $scraper['currency'] ?: 'USD'
                ),
            ];
        }

        wp_send_json_success(['scrapers' => $formatted]);
    }

    /**
     * AJAX: Get products for a scraper.
     *
     * @return void
     */
    public function ajax_get_products(): void {
        check_ajax_referer('erh_link_populator', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $scraper_id = isset($_POST['scraper_id']) ? (int) $_POST['scraper_id'] : 0;

        if (!$scraper_id) {
            wp_send_json_error(['message' => __('Invalid scraper ID.', 'erh-core')]);
        }

        global $wpdb;

        // Get scraper details.
        $scraper = $wpdb->get_row($wpdb->prepare(
            "SELECT domain FROM {$wpdb->prefix}hft_scrapers WHERE id = %d",
            $scraper_id
        ));

        if (!$scraper) {
            wp_send_json_error(['message' => __('Scraper not found.', 'erh-core')]);
        }

        // Build domain variations to match (shop.niu.com -> also match niu.com).
        $domain = $scraper->domain;
        $domain_parts = explode('.', $domain);
        $base_domain = count($domain_parts) > 2
            ? implode('.', array_slice($domain_parts, -2))
            : $domain;

        // Get products that already have links for this scraper with their link IDs.
        // Check both scraper_id (newer) and parser_identifier (older links).
        $existing_links_data = $wpdb->get_results($wpdb->prepare(
            "SELECT product_post_id, id as link_id, tracking_url
             FROM {$wpdb->prefix}hft_tracked_links
             WHERE scraper_id = %d
                OR parser_identifier = %s
                OR parser_identifier = %s",
            $scraper_id,
            $domain,
            $base_domain
        ), ARRAY_A);

        // Index by product_id for quick lookup.
        $existing_links = [];
        foreach ($existing_links_data as $link) {
            $existing_links[(int) $link['product_post_id']] = [
                'link_id' => (int) $link['link_id'],
                'url'     => $link['tracking_url'],
            ];
        }

        // Get all products.
        $products_query = new \WP_Query([
            'post_type'      => 'products',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $products = [];
        $brands = [];

        foreach ($products_query->posts as $post) {
            $brand_terms = get_the_terms($post->ID, 'brand');
            $brand = $brand_terms && !is_wp_error($brand_terms) ? $brand_terms[0]->name : '';

            if ($brand && !in_array($brand, $brands, true)) {
                $brands[] = $brand;
            }

            $has_link = isset($existing_links[$post->ID]);
            $products[] = [
                'id'           => $post->ID,
                'name'         => $post->post_title,
                'brand'        => $brand,
                'has_link'     => $has_link,
                'existing_url' => $has_link ? $existing_links[$post->ID]['url'] : null,
                'link_id'      => $has_link ? $existing_links[$post->ID]['link_id'] : null,
            ];
        }

        sort($brands);

        wp_send_json_success([
            'products' => $products,
            'brands'   => $brands,
        ]);
    }

    /**
     * AJAX: Find URLs using Perplexity.
     *
     * @return void
     */
    public function ajax_find_urls(): void {
        check_ajax_referer('erh_link_populator', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $product_name = isset($_POST['product_name']) ? sanitize_text_field($_POST['product_name']) : '';
        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';

        if (!$product_id || !$product_name || !$domain) {
            wp_send_json_error(['message' => __('Missing required parameters.', 'erh-core')]);
        }

        $result = $this->perplexity->find_product_url($product_name, $domain);

        wp_send_json_success([
            'product_id' => $product_id,
            'result'     => $result,
        ]);
    }

    /**
     * AJAX: Verify URLs.
     *
     * @return void
     */
    public function ajax_verify_urls(): void {
        check_ajax_referer('erh_link_populator', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';

        if (!$url) {
            wp_send_json_error(['message' => __('No URL provided.', 'erh-core')]);
        }

        $result = $this->verifier->verify($url);

        wp_send_json_success(['result' => $result]);
    }

    /**
     * AJAX: Add links to HFT.
     *
     * @return void
     */
    public function ajax_add_links(): void {
        check_ajax_referer('erh_link_populator', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $links = isset($_POST['links']) ? json_decode(stripslashes($_POST['links']), true) : [];
        $scraper_id = isset($_POST['scraper_id']) ? (int) $_POST['scraper_id'] : 0;

        if (empty($links) || !$scraper_id) {
            wp_send_json_error(['message' => __('Invalid data.', 'erh-core')]);
        }

        global $wpdb;

        // Get scraper info.
        $scraper = $wpdb->get_row($wpdb->prepare(
            "SELECT domain, currency, geos FROM {$wpdb->prefix}hft_scrapers WHERE id = %d",
            $scraper_id
        ));

        if (!$scraper) {
            wp_send_json_error(['message' => __('Scraper not found.', 'erh-core')]);
        }

        $added = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($links as $link) {
            $product_id = isset($link['product_id']) ? (int) $link['product_id'] : 0;
            $url = isset($link['url']) ? esc_url_raw($link['url']) : '';
            $link_id = isset($link['link_id']) ? (int) $link['link_id'] : 0;

            if (!$product_id || !$url) {
                $skipped++;
                continue;
            }

            // Check if link already exists (by scraper_id or domain).
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}hft_tracked_links
                 WHERE product_post_id = %d AND (scraper_id = %d OR parser_identifier = %s)",
                $product_id,
                $scraper_id,
                $scraper->domain
            ));

            if ($existing_id) {
                // Update existing link with new URL and ensure scraper_id is set.
                $result = $wpdb->update(
                    $wpdb->prefix . 'hft_tracked_links',
                    [
                        'tracking_url'      => $url,
                        'scraper_id'        => $scraper_id,
                        'parser_identifier' => $scraper->domain,
                        'geo_target'        => $scraper->geos,
                        'current_currency'  => $scraper->currency,
                        // Reset scrape status so it gets re-scraped.
                        'current_price'     => null,
                        'current_status'    => null,
                        'last_scraped_at'   => null,
                    ],
                    ['id' => $existing_id],
                    ['%s', '%d', '%s', '%s', '%s', null, null, null],
                    ['%d']
                );

                if ($result !== false) {
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                // Insert new link with scraper_id (new system) and parser_identifier (backwards compat).
                $result = $wpdb->insert(
                    $wpdb->prefix . 'hft_tracked_links',
                    [
                        'product_post_id'   => $product_id,
                        'tracking_url'      => $url,
                        'parser_identifier' => $scraper->domain,
                        'scraper_id'        => $scraper_id,
                        'geo_target'        => $scraper->geos,
                        'current_currency'  => $scraper->currency,
                        'created_at'        => current_time('mysql', true),
                    ],
                    ['%d', '%s', '%s', '%d', '%s', '%s', '%s']
                );

                if ($result) {
                    $added++;
                } else {
                    $skipped++;
                }
            }
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %1$d: added count, %2$d: updated count, %3$d: skipped count */
                __('Added %1$d, updated %2$d, skipped %3$d.', 'erh-core'),
                $added,
                $updated,
                $skipped
            ),
            'added'   => $added,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    /**
     * AJAX: Get products for Amazon mode.
     * Returns products with Amazon link status (checks parser_identifier = 'amazon').
     *
     * @return void
     */
    public function ajax_get_amazon_products(): void {
        check_ajax_referer('erh_link_populator', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $locale = isset($_POST['locale']) ? sanitize_text_field($_POST['locale']) : 'US';

        global $wpdb;

        // Get products that already have Amazon links for this locale.
        $existing_links_data = $wpdb->get_results($wpdb->prepare(
            "SELECT product_post_id, id as link_id, tracking_url
             FROM {$wpdb->prefix}hft_tracked_links
             WHERE parser_identifier = 'amazon'
               AND geo_target = %s",
            $locale
        ), ARRAY_A);

        // Index by product_id for quick lookup.
        $existing_links = [];
        foreach ($existing_links_data as $link) {
            $existing_links[(int) $link['product_post_id']] = [
                'link_id' => (int) $link['link_id'],
                'asin'    => $link['tracking_url'], // ASIN is stored in tracking_url.
            ];
        }

        // Get all products.
        $products_query = new \WP_Query([
            'post_type'      => 'products',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $products = [];
        $brands = [];

        foreach ($products_query->posts as $post) {
            $brand_terms = get_the_terms($post->ID, 'brand');
            $brand = $brand_terms && !is_wp_error($brand_terms) ? $brand_terms[0]->name : '';

            if ($brand && !in_array($brand, $brands, true)) {
                $brands[] = $brand;
            }

            $has_link = isset($existing_links[$post->ID]);
            $products[] = [
                'id'           => $post->ID,
                'name'         => $post->post_title,
                'brand'        => $brand,
                'has_link'     => $has_link,
                'existing_asin'=> $has_link ? $existing_links[$post->ID]['asin'] : null,
                'link_id'      => $has_link ? $existing_links[$post->ID]['link_id'] : null,
            ];
        }

        sort($brands);

        wp_send_json_success([
            'products' => $products,
            'brands'   => $brands,
        ]);
    }

    /**
     * AJAX: Find Amazon ASINs using PA-API SearchItems.
     *
     * @return void
     */
    public function ajax_find_amazon_urls(): void {
        check_ajax_referer('erh_link_populator', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
        $product_name = isset($_POST['product_name']) ? sanitize_text_field($_POST['product_name']) : '';
        $locale = isset($_POST['locale']) ? sanitize_text_field($_POST['locale']) : 'US';

        if (!$product_id || !$product_name) {
            wp_send_json_error(['message' => __('Missing required parameters.', 'erh-core')]);
        }

        if (!$this->amazon->is_configured()) {
            wp_send_json_error(['message' => $this->amazon->get_configuration_error()]);
        }

        $result = $this->amazon->search_product($product_name, $locale);

        wp_send_json_success([
            'product_id' => $product_id,
            'result'     => $result,
        ]);
    }

    /**
     * AJAX: Add Amazon links to HFT.
     * Uses parser_identifier = 'amazon' and stores ASIN in tracking_url.
     *
     * @return void
     */
    public function ajax_add_amazon_links(): void {
        check_ajax_referer('erh_link_populator', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'erh-core')]);
        }

        $links = isset($_POST['links']) ? json_decode(stripslashes($_POST['links']), true) : [];
        $locale = isset($_POST['locale']) ? sanitize_text_field($_POST['locale']) : 'US';

        if (empty($links)) {
            wp_send_json_error(['message' => __('Invalid data.', 'erh-core')]);
        }

        global $wpdb;

        // Map locale to currency.
        $currency_map = [
            'US' => 'USD',
            'GB' => 'GBP',
            'DE' => 'EUR',
            'FR' => 'EUR',
            'IT' => 'EUR',
            'ES' => 'EUR',
            'CA' => 'CAD',
            'AU' => 'AUD',
        ];
        $currency = $currency_map[$locale] ?? 'USD';

        $added = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($links as $link) {
            $product_id = isset($link['product_id']) ? (int) $link['product_id'] : 0;
            $asin = isset($link['asin']) ? sanitize_text_field($link['asin']) : '';

            if (!$product_id || !$asin) {
                $skipped++;
                continue;
            }

            // Check if Amazon link already exists for this product and locale.
            $existing_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}hft_tracked_links
                 WHERE product_post_id = %d
                   AND parser_identifier = 'amazon'
                   AND geo_target = %s",
                $product_id,
                $locale
            ));

            if ($existing_id) {
                // Update existing link.
                $result = $wpdb->update(
                    $wpdb->prefix . 'hft_tracked_links',
                    [
                        'tracking_url'      => $asin,
                        'current_currency'  => $currency,
                        // Reset scrape status so it gets re-scraped.
                        'current_price'     => null,
                        'current_status'    => null,
                        'last_scraped_at'   => null,
                    ],
                    ['id' => $existing_id],
                    ['%s', '%s', null, null, null],
                    ['%d']
                );

                if ($result !== false) {
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                // Insert new Amazon link.
                $result = $wpdb->insert(
                    $wpdb->prefix . 'hft_tracked_links',
                    [
                        'product_post_id'   => $product_id,
                        'tracking_url'      => $asin,
                        'parser_identifier' => 'amazon',
                        'scraper_id'        => null, // Amazon doesn't use scrapers.
                        'geo_target'        => $locale,
                        'current_currency'  => $currency,
                        'created_at'        => current_time('mysql', true),
                    ],
                    ['%d', '%s', '%s', null, '%s', '%s', '%s']
                );

                if ($result) {
                    $added++;
                } else {
                    $skipped++;
                }
            }
        }

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %1$d: added count, %2$d: updated count, %3$d: skipped count */
                __('Added %1$d, updated %2$d, skipped %3$d.', 'erh-core'),
                $added,
                $updated,
                $skipped
            ),
            'added'   => $added,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }
}
