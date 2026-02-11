<?php
declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin functionality for managing scrapers.
 */
class HFT_Scraper_Admin {
    
    /**
     * @var HFT_Scraper_Repository
     */
    private HFT_Scraper_Repository $repository;
    
    /**
     * @var string|false Admin page hook suffix for the scrapers list page
     */
    private string|false $scrapers_list_page = false;
    
    /**
     * @var string|false Admin page hook suffix for the add/edit scraper page
     */
    private string|false $scraper_edit_page = false;
    
    /**
     * Constructor.
     *
     * @param HFT_Scraper_Repository $repository
     */
    public function __construct(HFT_Scraper_Repository $repository) {
        $this->repository = $repository;
        
        // Add admin notice for migration
        add_action('admin_notices', [$this, 'show_migration_notice']);
    }
    
    /**
     * Add admin menu items.
     */
    public function add_admin_menu(): void {
        // Add scrapers submenu under Housefresh Tools
        $this->scrapers_list_page = add_submenu_page(
            'housefresh-tools-main-menu',
            __('Scrapers', 'housefresh-tools'),
            __('Scrapers', 'housefresh-tools'),
            'manage_options',
            'hft-scrapers',
            [$this, 'render_scrapers_list_page']
        );
        
        // Hidden submenu for add/edit scraper
        $this->scraper_edit_page = add_submenu_page(
            '', // Parent slug - empty string to hide from menu
            __('Edit Scraper', 'housefresh-tools'),
            __('Edit Scraper', 'housefresh-tools'),
            'manage_options',
            'hft-scraper-edit',
            [$this, 'render_scraper_edit_page']
        );
        
        // Hook to load list table (only if page was successfully created)
        if ($this->scrapers_list_page !== false) {
            add_action('load-' . $this->scrapers_list_page, [$this, 'load_scrapers_list_table']);
        }
    }
    
    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook_suffix
     */
    public function enqueue_scripts(string $hook_suffix): void {
        // Skip if page hooks haven't been set yet
        if (empty($this->scrapers_list_page) && empty($this->scraper_edit_page)) {
            return;
        }

        // Only enqueue on our admin pages
        $is_scrapers_list = strpos($hook_suffix, 'hft-scrapers') !== false;
        $is_scraper_edit = strpos($hook_suffix, 'hft-scraper-edit') !== false;

        if (!$is_scrapers_list && !$is_scraper_edit) {
            return;
        }
        
        wp_enqueue_style(
            'hft-scraper-admin',
            HFT_PLUGIN_URL . 'admin/css/hft-scraper-admin.css',
            [],
            HFT_VERSION
        );
        
        // Enqueue Tagify for GEO selection and WordPress media uploader
        if ($is_scraper_edit) {
            // WordPress media uploader
            wp_enqueue_media();

            wp_enqueue_style(
                'tagify',
                HFT_PLUGIN_URL . 'assets/css/tagify.css',
                [],
                HFT_VERSION
            );

            wp_enqueue_script(
                'tagify',
                HFT_PLUGIN_URL . 'assets/js/tagify.min.js',
                [],
                HFT_VERSION,
                true
            );
        }
        
        $deps = ['jquery'];
        if ($is_scraper_edit) {
            $deps[] = 'tagify';
        }
        
        wp_enqueue_script(
            'hft-scraper-admin',
            HFT_PLUGIN_URL . 'admin/js/hft-scraper-admin.js',
            $deps,
            HFT_VERSION,
            true
        );

        wp_localize_script('hft-scraper-admin', 'hftScraperAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hft_scraper_admin'),
            'confirmDelete' => __('Are you sure you want to delete this scraper?', 'housefresh-tools'),
            'geoWhitelist' => $this->get_geo_whitelist(),
            'geoGroups' => class_exists('HFT_Geo_Groups') ? HFT_Geo_Groups::get_groups_for_js() : [],
            'strings' => [
                'testInProgress' => __('Testing...', 'housefresh-tools'),
                'testComplete' => __('Test Complete', 'housefresh-tools'),
                'testError' => __('Test failed. Please check your settings.', 'housefresh-tools'),
                'enterUrl' => __('Please enter a test URL', 'housefresh-tools'),
                'testButton' => __('Test Scraper', 'housefresh-tools'),
                'selectLogo' => __('Select Logo', 'housefresh-tools'),
                'useLogo' => __('Use this logo', 'housefresh-tools'),
                'removeLogo' => __('Remove', 'housefresh-tools'),
                'detectingMarkets' => __('Detecting markets...', 'housefresh-tools'),
                'detectMarkets' => __('Detect Markets', 'housefresh-tools'),
                'detectMarketsNoUrl' => __('Please enter a Test URL first.', 'housefresh-tools'),
                'detectMarketsNoGeos' => __('Please configure GEOs first.', 'housefresh-tools'),
                'detectMarketsFailed' => __('Market detection failed.', 'housefresh-tools'),
            ],
        ]);
    }
    
    /**
     * Process admin actions early before any output.
     * This runs on admin_init to handle deletes and other actions that need redirects.
     */
    public function process_admin_actions(): void {
        // Set page title early to prevent strip_tags() deprecation warning
        $this->set_page_title_early();
        
        // Only process on our admin pages
        if (!isset($_GET['page'])) {
            return;
        }
        
        $page = sanitize_text_field($_GET['page']);
        
        // Handle scrapers list page actions
        if ($page === 'hft-scrapers' && isset($_GET['action'])) {
            $this->handle_list_actions_early();
        }
        
        // Handle scraper edit page form submission
        if ($page === 'hft-scraper-edit' && isset($_POST['submit'])) {
            $this->handle_edit_form_early();
        }
    }
    
    /**
     * Set page title early to prevent strip_tags() null deprecation warning.
     * This runs before admin-header.php processes the title.
     */
    private function set_page_title_early(): void {
        global $title, $pagenow;
        
        // Only set title for our hidden admin page
        if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'hft-scraper-edit') {
            $scraper_id = isset($_GET['scraper_id']) ? absint($_GET['scraper_id']) : 0;
            
            if ($scraper_id > 0) {
                $title = __('Edit Scraper', 'housefresh-tools');
            } else {
                $title = __('Add New Scraper', 'housefresh-tools');
            }
        }
    }
    
    /**
     * Handle list actions early (before output).
     */
    private function handle_list_actions_early(): void {
        if (!$this->verify_nonce('hft_scraper_action')) {
            return;
        }
        
        $action = sanitize_text_field($_GET['action']);
        $scraper_id = isset($_GET['scraper_id']) ? absint($_GET['scraper_id']) : 0;
        
        if ($scraper_id === 0) {
            return;
        }
        
        switch ($action) {
            case 'delete':
                if ($this->repository->delete($scraper_id)) {
                    set_transient('hft_scraper_admin_notice', [
                        'type' => 'success',
                        'message' => 'Scraper deleted successfully.'
                    ], 30);
                } else {
                    set_transient('hft_scraper_admin_notice', [
                        'type' => 'error',
                        'message' => 'Failed to delete scraper.'
                    ], 30);
                }
                
                wp_safe_redirect($this->get_scrapers_list_url());
                exit;
        }
    }
    
    /**
     * Handle edit form submission early (before output).
     */
    private function handle_edit_form_early(): void {
        if (!$this->verify_nonce('hft_scraper_edit')) {
            return;
        }
        
        $this->handle_scraper_save();
        // handle_scraper_save() already does the redirect
    }
    
    /**
     * Load the scrapers list table.
     */
    public function load_scrapers_list_table(): void {
        $option = 'per_page';
        $args = [
            'label' => 'Scrapers',
            'default' => 20,
            'option' => 'scrapers_per_page'
        ];
        
        add_screen_option($option, $args);
    }
    
    /**
     * Render the scrapers list page.
     */
    public function render_scrapers_list_page(): void {
        // Create list table instance
        require_once HFT_PLUGIN_PATH . 'includes/admin/class-hft-scrapers-list-table.php';
        $list_table = new HFT_Scrapers_List_Table($this->repository);
        $list_table->prepare_items();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Scrapers</h1>
            <a href="<?php echo esc_url($this->get_add_scraper_url()); ?>" class="page-title-action">Add New</a>
            <hr class="wp-header-end">
            
            <?php $this->display_admin_notices(); ?>
            
            <form method="post">
                <?php
                $list_table->display();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render the scraper edit page.
     */
    public function render_scraper_edit_page(): void {
        $scraper_id = isset($_GET['scraper_id']) ? absint($_GET['scraper_id']) : 0;
        $scraper = null;
        $rules = [];
        
        if ($scraper_id > 0) {
            $scraper = $this->repository->find($scraper_id);
            if (!$scraper) {
                wp_die(__('Scraper not found.', 'housefresh-tools'));
            }
            $rules = $this->repository->find_rules_by_scraper_id($scraper_id);
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo $scraper ? 'Edit Scraper' : 'Add New Scraper'; ?></h1>
            
            <?php $this->display_admin_notices(); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('hft_scraper_edit', 'hft_scraper_edit_nonce'); ?>
                
                <?php if ($scraper): ?>
                    <input type="hidden" name="scraper_id" value="<?php echo esc_attr($scraper->id); ?>">
                <?php endif; ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="domain">Domain</label></th>
                            <td>
                                <input type="text" name="domain" id="domain" class="regular-text" 
                                       value="<?php echo $scraper ? esc_attr($scraper->domain) : ''; ?>" required>
                                <p class="description">Enter the domain without protocol (e.g., example.com)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="name">Name</label></th>
                            <td>
                                <input type="text" name="name" id="name" class="regular-text"
                                       value="<?php echo $scraper ? esc_attr($scraper->name) : ''; ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="logo">Logo</label></th>
                            <td>
                                <div class="hft-logo-upload-wrapper">
                                    <?php
                                    $logo_id = $scraper ? $scraper->logo_attachment_id : null;
                                    $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'thumbnail') : '';
                                    ?>
                                    <input type="hidden" name="logo_attachment_id" id="logo_attachment_id"
                                           value="<?php echo esc_attr($logo_id ?? ''); ?>">
                                    <div class="hft-logo-preview" id="logo-preview" style="<?php echo $logo_url ? '' : 'display:none;'; ?>">
                                        <img src="<?php echo esc_url($logo_url); ?>" alt="Logo preview" style="max-width: 150px; max-height: 150px;">
                                        <button type="button" class="button hft-remove-logo" id="remove-logo"><?php _e('Remove', 'housefresh-tools'); ?></button>
                                    </div>
                                    <button type="button" class="button hft-upload-logo" id="upload-logo" style="<?php echo $logo_url ? 'display:none;' : ''; ?>">
                                        <?php _e('Select Logo', 'housefresh-tools'); ?>
                                    </button>
                                    <p class="description"><?php _e('Upload or select a logo image for this scraper (recommended: square image, 100x100px or larger).', 'housefresh-tools'); ?></p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="currency">Currency</label></th>
                            <td>
                                <select name="currency" id="currency" class="regular-text">
                                    <?php
                                    $currencies = [
                                        'USD' => 'USD - US Dollar',
                                        'EUR' => 'EUR - Euro',
                                        'GBP' => 'GBP - British Pound',
                                        'CAD' => 'CAD - Canadian Dollar',
                                        'AUD' => 'AUD - Australian Dollar',
                                        'JPY' => 'JPY - Japanese Yen',
                                        'INR' => 'INR - Indian Rupee',
                                        'BRL' => 'BRL - Brazilian Real',
                                        'MXN' => 'MXN - Mexican Peso',
                                        'SEK' => 'SEK - Swedish Krona',
                                        'PLN' => 'PLN - Polish Złoty',
                                        'CZK' => 'CZK - Czech Koruna',
                                    ];
                                    $selected_currency = $scraper ? $scraper->currency : 'USD';
                                    foreach ($currencies as $code => $label) {
                                        echo '<option value="' . esc_attr($code) . '" ' . selected($selected_currency, $code, false) . '>' . esc_html($label) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description">Default currency for products from this site</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="geos">Applicable GEOs</label></th>
                            <td>
                                <?php
                                // Convert comma-separated GEOs to simple format for Tagify
                                $geos_value = '';
                                $geos_input_value = '';
                                if ($scraper && !empty($scraper->geos)) {
                                    $geos_value = $scraper->geos;
                                }
                                if ($scraper && !empty($scraper->geos_input)) {
                                    $geos_input_value = $scraper->geos_input;
                                }

                                // Detect which groups are fully present
                                $detected_groups = [];
                                if (!empty($geos_value) && class_exists('HFT_Geo_Groups')) {
                                    $detected_groups = HFT_Geo_Groups::detect_groups($geos_value);
                                }
                                ?>
                                <div class="hft-geo-groups-wrapper">
                                    <div class="hft-geo-groups-buttons">
                                        <span class="hft-geo-groups-label"><?php _e('Quick Add:', 'housefresh-tools'); ?></span>
                                        <?php
                                        if (class_exists('HFT_Geo_Groups')) {
                                            $groups = HFT_Geo_Groups::get_all_groups();
                                            foreach ($groups as $key => $group) {
                                                $is_active = in_array($key, $detected_groups);
                                                $active_class = $is_active ? ' active' : '';
                                                $country_count = count($group['countries']);
                                                $tooltip = $key === 'GLOBAL'
                                                    ? __('Special flag for all countries', 'housefresh-tools')
                                                    : sprintf(__('%d countries: %s', 'housefresh-tools'), $country_count, implode(', ', $group['countries']));
                                                ?>
                                                <button type="button"
                                                        class="button hft-geo-group-btn<?php echo $active_class; ?>"
                                                        data-group="<?php echo esc_attr($key); ?>"
                                                        title="<?php echo esc_attr($tooltip); ?>">
                                                    <?php echo esc_html($key); ?>
                                                </button>
                                                <?php
                                            }
                                        }
                                        ?>
                                        <button type="button" class="button hft-geo-clear-btn" title="<?php esc_attr_e('Clear all GEOs', 'housefresh-tools'); ?>">
                                            <?php _e('Clear', 'housefresh-tools'); ?>
                                        </button>
                                    </div>
                                    <input type="text" name="geos" id="geos" class="hft-geos-tagify regular-text"
                                           value="<?php echo esc_attr($geos_value); ?>"
                                           placeholder="<?php esc_attr_e('Type or select GEOs', 'housefresh-tools'); ?>">
                                    <input type="hidden" name="geos_input" id="geos_input"
                                           value="<?php echo esc_attr($geos_input_value); ?>">
                                </div>
                                <p class="description"><?php _e('Geographic regions where affiliate links should be generated. Click group buttons to add predefined regions or type individual country codes (e.g., US, GB, CA).', 'housefresh-tools'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="affiliate_link_format">Affiliate Link Format</label></th>
                            <td>
                                <input type="text" name="affiliate_link_format" id="affiliate_link_format" class="large-text"
                                       value="<?php echo $scraper ? esc_attr($scraper->affiliate_link_format ?? '') : ''; ?>"
                                       placeholder="{URL}?ref=5">
                                <p class="description">Format for generating affiliate links. Use {URL} for the scraped URL and {URLE} for URL-encoded version.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="use_base_parser">Use Base Parser</label></th>
                            <td>
                                <input type="checkbox" name="use_base_parser" id="use_base_parser" value="1"
                                       <?php checked($scraper ? $scraper->use_base_parser : true); ?>>
                                <p class="description">Enable to use structured data extraction as fallback</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="use_curl">Use Direct cURL</label></th>
                            <td>
                                <input type="checkbox" name="use_curl" id="use_curl" value="1"
                                       <?php checked($scraper ? $scraper->use_curl : false); ?>>
                                <p class="description">Enable for sites that require special handling (e.g., Dyson, sites with Akamai protection). Uses direct cURL with browser-like headers and cookie support.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="use_scrapingrobot">Use ScrapingRobot</label></th>
                            <td>
                                <input type="checkbox" name="use_scrapingrobot" id="use_scrapingrobot" value="1"
                                       <?php checked($scraper ? $scraper->use_scrapingrobot : false); ?>>
                                <p class="description">Enable for sites that require bot detection bypass. Requires ScrapingRobot API key in settings. This will use API credits.</p>
                            </td>
                        </tr>
                        <tr class="scrapingrobot-js-option" style="<?php echo ($scraper && $scraper->use_scrapingrobot) ? '' : 'display: none;'; ?>">
                            <th scope="row"><label for="scrapingrobot_render_js">Render JavaScript</label></th>
                            <td>
                                <input type="checkbox" name="scrapingrobot_render_js" id="scrapingrobot_render_js" value="1"
                                       <?php checked($scraper ? $scraper->scrapingrobot_render_js : false); ?>>
                                <p class="description">Enable JavaScript rendering for dynamically loaded content. This uses more API credits but is necessary for sites that load prices via JavaScript.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2><?php _e('Shopify Markets', 'housefresh-tools'); ?></h2>
                <table class="form-table" id="hft-shopify-markets-section">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="shopify_markets"><?php _e('Shopify Markets', 'housefresh-tools'); ?></label></th>
                            <td>
                                <input type="checkbox" name="shopify_markets" id="shopify_markets" value="1"
                                       <?php checked($scraper ? $scraper->shopify_markets : false); ?>>
                                <p class="description"><?php _e('Enable for Shopify sites that serve different prices by country. When enabled, each product URL auto-creates one tracked link per currency market.', 'housefresh-tools'); ?></p>
                            </td>
                        </tr>
                        <tr class="hft-shopify-option" style="<?php echo ($scraper && $scraper->shopify_markets) ? '' : 'display: none;'; ?>">
                            <th scope="row"><?php _e('Method', 'housefresh-tools'); ?></th>
                            <td>
                                <?php $shopify_method = $scraper ? ($scraper->shopify_method ?? 'cookie') : 'cookie'; ?>
                                <label>
                                    <input type="radio" name="shopify_method" value="cookie"
                                           <?php checked($shopify_method, 'cookie'); ?>>
                                    <?php _e('Cookie Injection', 'housefresh-tools'); ?>
                                </label>
                                <p class="description" style="margin-left: 24px;"><?php _e('Sets cart_currency and localization cookies. Works for stores like Backfire Boards.', 'housefresh-tools'); ?></p>
                                <label style="display: block; margin-top: 8px;">
                                    <input type="radio" name="shopify_method" value="api"
                                           <?php checked($shopify_method, 'api'); ?>>
                                    <?php _e('Storefront API', 'housefresh-tools'); ?>
                                </label>
                                <p class="description" style="margin-left: 24px;"><?php _e('Uses GraphQL @inContext directive. Works for stores like Aventon.', 'housefresh-tools'); ?></p>
                            </td>
                        </tr>
                        <tr class="hft-shopify-option hft-shopify-api-option" style="<?php echo ($scraper && $scraper->shopify_markets && $shopify_method === 'api') ? '' : 'display: none;'; ?>">
                            <th scope="row"><?php _e('API Settings', 'housefresh-tools'); ?></th>
                            <td>
                                <button type="button" id="hft-autodetect-shopify" class="button button-secondary">
                                    <?php _e('Auto-detect from store', 'housefresh-tools'); ?>
                                </button>
                                <span id="hft-autodetect-status" style="margin-left: 8px;"></span>
                                <p class="description"><?php _e('Fetches the store homepage and extracts the Storefront API token and .myshopify.com domain automatically.', 'housefresh-tools'); ?></p>
                            </td>
                        </tr>
                        <tr class="hft-shopify-option hft-shopify-api-option" style="<?php echo ($scraper && $scraper->shopify_markets && $shopify_method === 'api') ? '' : 'display: none;'; ?>">
                            <th scope="row"><label for="shopify_storefront_token"><?php _e('Storefront API Token', 'housefresh-tools'); ?></label></th>
                            <td>
                                <input type="text" name="shopify_storefront_token" id="shopify_storefront_token" class="regular-text"
                                       value="<?php echo $scraper ? esc_attr($scraper->shopify_storefront_token ?? '') : ''; ?>"
                                       placeholder="<?php esc_attr_e('e.g., d59e266083bdb8dc53e0ec8ad52ef19f', 'housefresh-tools'); ?>">
                                <p class="description"><?php _e('Found in the store\'s theme JavaScript (search for "storefrontAccessToken" in page source).', 'housefresh-tools'); ?></p>
                            </td>
                        </tr>
                        <tr class="hft-shopify-option hft-shopify-api-option" style="<?php echo ($scraper && $scraper->shopify_markets && $shopify_method === 'api') ? '' : 'display: none;'; ?>">
                            <th scope="row"><label for="shopify_shop_domain"><?php _e('Shop Domain', 'housefresh-tools'); ?></label></th>
                            <td>
                                <input type="text" name="shopify_shop_domain" id="shopify_shop_domain" class="regular-text"
                                       value="<?php echo $scraper ? esc_attr($scraper->shopify_shop_domain ?? '') : ''; ?>"
                                       placeholder="<?php esc_attr_e('e.g., mystore.myshopify.com', 'housefresh-tools'); ?>">
                                <p class="description"><?php _e('The .myshopify.com domain (found in page source or Storefront API endpoint).', 'housefresh-tools'); ?></p>
                            </td>
                        </tr>
                        <tr class="hft-shopify-option" style="<?php echo ($scraper && $scraper->shopify_markets) ? '' : 'display: none;'; ?>">
                            <th scope="row"><?php _e('Detect Markets', 'housefresh-tools'); ?></th>
                            <td>
                                <button type="button" id="hft-detect-markets" class="button button-secondary">
                                    <?php _e('Detect Markets', 'housefresh-tools'); ?>
                                </button>
                                <p class="description"><?php _e('Tests which geo markets are available for this store. Requires a Test URL and GEOs to be configured above.', 'housefresh-tools'); ?></p>
                                <div id="hft-detect-markets-results" style="display: none; margin-top: 10px;">
                                    <table class="widefat" id="hft-markets-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 40px;"><?php _e('OK', 'housefresh-tools'); ?></th>
                                                <th><?php _e('Market', 'housefresh-tools'); ?></th>
                                                <th><?php _e('Countries', 'housefresh-tools'); ?></th>
                                                <th><?php _e('Price', 'housefresh-tools'); ?></th>
                                                <th><?php _e('Status', 'housefresh-tools'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
				
				<h2><?php _e('Test Scraper', 'housefresh-tools'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test-url"><?php _e('Test URL', 'housefresh-tools'); ?></label>
                        </th>
                        <td>
                            <input type="url" id="test-url" name="test_url" class="large-text" 
                                   value="<?php echo $scraper ? esc_attr($scraper->test_url ?? '') : ''; ?>"
                                   placeholder="<?php esc_attr_e('https://example.com/product-page', 'housefresh-tools'); ?>">
                            <p class="description"><?php _e('Enter a product URL to test the scraper configuration. This will be saved for future use.', 'housefresh-tools'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button type="button" id="test-scraper" class="button button-secondary">
                                <?php _e('Test Scraper', 'housefresh-tools'); ?>
                            </button>
                            <button type="button" id="view-source" class="button button-secondary" style="margin-left: 10px;">
                                <?php _e('View Source', 'housefresh-tools'); ?>
                            </button>
                            <p class="description" style="margin-top: 10px;"><?php _e('Test Scraper parses the page and shows extracted data. View Source shows the raw HTML that the scraper receives.', 'housefresh-tools'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div id="test-results"></div>
                
                <!-- HTML Source Modal -->
                <div id="hft-source-modal" class="hft-modal" style="display: none;">
                    <div class="hft-modal-content">
                        <div class="hft-modal-header">
                            <h2><?php _e('Page Source', 'housefresh-tools'); ?></h2>
                            <span class="hft-modal-close" id="hft-source-modal-close">&times;</span>
                        </div>
                        <div class="hft-modal-body">
                            <div class="hft-source-meta">
                                <p>
                                    <strong><?php _e('URL:', 'housefresh-tools'); ?></strong> <span id="hft-source-url"></span><br>
                                    <strong><?php _e('Fetch Method:', 'housefresh-tools'); ?></strong> <span id="hft-source-method"></span><br>
                                    <strong><?php _e('Execution Time:', 'housefresh-tools'); ?></strong> <span id="hft-source-time"></span>s<br>
                                    <strong><?php _e('HTML Size:', 'housefresh-tools'); ?></strong> <span id="hft-source-size"></span> bytes
                                </p>
                            </div>
                            <div class="hft-source-actions">
                                <button type="button" id="hft-copy-source" class="button button-secondary">
                                    <?php _e('Copy to Clipboard', 'housefresh-tools'); ?>
                                </button>
                                <button type="button" id="hft-download-source" class="button button-secondary">
                                    <?php _e('Download HTML', 'housefresh-tools'); ?>
                                </button>
                            </div>
                            <pre id="hft-source-content" class="hft-source-code"></pre>
                        </div>
                    </div>
                </div>
                
                <h2>Extraction Rules</h2>
                <p class="description">Define extraction rules for product data. Use the advanced modes for complex sites like those with JSON-embedded prices or dynamic content.</p>

                <div class="hft-scraper-rules">
                    <?php
                    $field_types = ['price' => 'Price', 'status' => 'Stock Status', 'shipping' => 'Shipping Info'];
                    foreach ($field_types as $field_type => $field_label):
                        // Get all rules for this field type (may be multiple with different priorities)
                        $field_rules = $this->get_rules_by_type($rules, $field_type);
                        // Ensure at least one rule form
                        if (empty($field_rules)) {
                            $field_rules = [null];
                        }
                    ?>
                        <div class="hft-scraper-rule-group" data-field="<?php echo $field_type; ?>">
                            <h3><?php echo esc_html($field_label); ?>
                                <button type="button" class="button button-small hft-add-rule" data-field="<?php echo $field_type; ?>">
                                    <?php _e('+ Add Fallback Rule', 'housefresh-tools'); ?>
                                </button>
                            </h3>
                            <p class="description"><?php _e('Add multiple rules in priority order. First successful extraction wins.', 'housefresh-tools'); ?></p>

                            <div class="hft-rules-container" id="rules-<?php echo $field_type; ?>">
                            <?php foreach ($field_rules as $rule_index => $rule): ?>
                                <?php $this->render_rule_form($field_type, $rule, $rule_index); ?>
                            <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Template for new rules -->
                <script type="text/template" id="hft-rule-template">
                    <?php $this->render_rule_form('__FIELD__', null, '__INDEX__'); ?>
                </script>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" 
                           value="<?php echo $scraper ? 'Update Scraper' : 'Add Scraper'; ?>">
                    <a href="<?php echo esc_url($this->get_scrapers_list_url()); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Handle scraper save.
     */
    private function handle_scraper_save(): void {
        $scraper_id = isset($_POST['scraper_id']) ? absint($_POST['scraper_id']) : 0;
        
        // Validate and sanitize input
        $domain = $this->normalize_domain(sanitize_text_field($_POST['domain'] ?? ''));
        $name = sanitize_text_field($_POST['name'] ?? '');
        $use_base_parser = isset($_POST['use_base_parser']);
        $use_curl = isset($_POST['use_curl']);
        $use_scrapingrobot = isset($_POST['use_scrapingrobot']);
        $scrapingrobot_render_js = isset($_POST['scrapingrobot_render_js']);
        $shopify_markets = isset($_POST['shopify_markets']);
        $shopify_method = sanitize_text_field($_POST['shopify_method'] ?? 'cookie');
        $shopify_storefront_token = sanitize_text_field($_POST['shopify_storefront_token'] ?? '');
        $shopify_shop_domain = sanitize_text_field($_POST['shopify_shop_domain'] ?? '');

        if (empty($domain) || empty($name)) {
            $this->add_admin_notice('error', 'Domain and name are required.');
            return;
        }
        
        // Save or update scraper
        if ($scraper_id > 0) {
            $scraper = $this->repository->find($scraper_id);
            if (!$scraper) {
                $this->add_admin_notice('error', 'Scraper not found.');
                return;
            }

            $scraper->domain = $domain;
            $scraper->name = $name;
            $scraper->logo_attachment_id = !empty($_POST['logo_attachment_id']) ? absint($_POST['logo_attachment_id']) : null;
            $scraper->currency = sanitize_text_field($_POST['currency'] ?? 'USD');

            // Process GEOs from Tagify format and expand groups
            $geos_data = $this->process_geos_input($_POST['geos'] ?? '');
            $scraper->geos = $geos_data['expanded'];
            $scraper->geos_input = $geos_data['original'];

            $scraper->affiliate_link_format = sanitize_text_field($_POST['affiliate_link_format'] ?? '');
            $scraper->test_url = esc_url_raw($_POST['test_url'] ?? '') ?: null;
            $scraper->use_base_parser = $use_base_parser;
            $scraper->use_curl = $use_curl;
            $scraper->use_scrapingrobot = $use_scrapingrobot;
            $scraper->scrapingrobot_render_js = $scrapingrobot_render_js;
            $scraper->shopify_markets = $shopify_markets;
            $scraper->shopify_method = $shopify_markets ? $shopify_method : null;
            $scraper->shopify_storefront_token = ($shopify_markets && $shopify_method === 'api') ? $shopify_storefront_token : null;
            $scraper->shopify_shop_domain = ($shopify_markets && $shopify_method === 'api') ? $shopify_shop_domain : null;

            if ($this->repository->update($scraper)) {
                // Store success message in transient for display after redirect
                set_transient('hft_scraper_admin_notice', [
                    'type' => 'success',
                    'message' => 'Scraper updated successfully.'
                ], 30);
            } else {
                $this->add_admin_notice('error', 'Failed to update scraper.');
                return;
            }
        } else {
            $scraper = new HFT_Scraper();
            $scraper->domain = $domain;
            $scraper->name = $name;
            $scraper->logo_attachment_id = !empty($_POST['logo_attachment_id']) ? absint($_POST['logo_attachment_id']) : null;
            $scraper->currency = sanitize_text_field($_POST['currency'] ?? 'USD');

            // Process GEOs from Tagify format and expand groups
            $geos_data = $this->process_geos_input($_POST['geos'] ?? '');
            $scraper->geos = $geos_data['expanded'];
            $scraper->geos_input = $geos_data['original'];

            $scraper->affiliate_link_format = sanitize_text_field($_POST['affiliate_link_format'] ?? '');
            $scraper->test_url = esc_url_raw($_POST['test_url'] ?? '') ?: null;
            $scraper->use_base_parser = $use_base_parser;
            $scraper->use_curl = $use_curl;
            $scraper->use_scrapingrobot = $use_scrapingrobot;
            $scraper->scrapingrobot_render_js = $scrapingrobot_render_js;
            $scraper->shopify_markets = $shopify_markets;
            $scraper->shopify_method = $shopify_markets ? $shopify_method : null;
            $scraper->shopify_storefront_token = ($shopify_markets && $shopify_method === 'api') ? $shopify_storefront_token : null;
            $scraper->shopify_shop_domain = ($shopify_markets && $shopify_method === 'api') ? $shopify_shop_domain : null;
            $scraper->is_active = true; // Default to active

            $scraper_id = $this->repository->create($scraper);
            if (!$scraper_id) {
                $this->add_admin_notice('error', 'Failed to create scraper.');
                return;
            }
            
            // Store success message in transient for display after redirect
            set_transient('hft_scraper_admin_notice', [
                'type' => 'success',
                'message' => 'Scraper created successfully.'
            ], 30);
        }
        
        // Save rules
        $this->save_scraper_rules($scraper_id);
        
        // Redirect to edit page
        wp_safe_redirect($this->get_edit_scraper_url($scraper_id));
        exit;
    }
    
    /**
     * Save scraper rules (supports multiple rules per field).
     *
     * @param int $scraper_id
     */
    private function save_scraper_rules(int $scraper_id): void {
        $rules_data = $_POST['rules'] ?? [];
        $saved_rule_ids = [];

        foreach ($rules_data as $field_type => $field_rules) {
            // field_rules is now an array of rule data indexed by index
            if (!is_array($field_rules)) {
                continue;
            }

            foreach ($field_rules as $index => $rule_data) {
                // Skip template placeholders
                if ($index === '__INDEX__') {
                    continue;
                }

                // Process XPath without escaping quotes but with basic sanitization
                $xpath_raw = $rule_data['xpath'] ?? '';
                $xpath = wp_unslash(trim($xpath_raw));

                // Basic security: remove any script tags and dangerous characters while preserving XPath syntax
                $xpath = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $xpath);
                $xpath = str_replace(['<script', '</script>', 'javascript:', 'vbscript:'], '', $xpath);

                // Skip if no xpath provided
                if (empty($xpath)) {
                    continue;
                }

                // Prepare post-processing
                $post_processing = [];
                if (isset($rule_data['post_processing']) && is_array($rule_data['post_processing'])) {
                    $post_processing = $rule_data['post_processing'];

                    // Add regex pattern/replacement if regex_replace is selected
                    if (in_array('regex_replace', $post_processing)) {
                        $post_processing['regex_pattern'] = wp_unslash($rule_data['post_regex_pattern'] ?? '');
                        $post_processing['regex_replacement'] = wp_unslash($rule_data['post_regex_replacement'] ?? '');
                    }
                }

                // Parse regex fallbacks (one per line)
                $regex_fallbacks = null;
                if (!empty($rule_data['regex_fallbacks'])) {
                    $fallbacks_raw = wp_unslash(trim($rule_data['regex_fallbacks']));
                    $fallbacks = array_filter(array_map('trim', explode("\n", $fallbacks_raw)));
                    if (!empty($fallbacks)) {
                        $regex_fallbacks = $fallbacks;
                    }
                }

                // Parse boolean true values (comma-separated)
                $boolean_true_values = null;
                if (!empty($rule_data['boolean_true_values'])) {
                    $values_raw = sanitize_text_field($rule_data['boolean_true_values']);
                    $values = array_filter(array_map('trim', explode(',', $values_raw)));
                    if (!empty($values)) {
                        $boolean_true_values = $values;
                    }
                }

                // Create or update rule
                $rule = new HFT_Scraper_Rule();
                $rule->scraper_id = $scraper_id;
                $rule->field_type = $field_type;
                $rule->priority = absint($rule_data['priority'] ?? 10);
                $rule->extraction_mode = sanitize_text_field($rule_data['extraction_mode'] ?? 'xpath');
                $rule->xpath_selector = $xpath;
                $rule->attribute = sanitize_text_field($rule_data['attribute'] ?? '') ?: null;
                $rule->regex_pattern = !empty($rule_data['regex_pattern']) ? wp_unslash(trim($rule_data['regex_pattern'])) : null;
                $rule->regex_fallbacks = $regex_fallbacks;
                $rule->return_boolean = !empty($rule_data['return_boolean']);
                $rule->boolean_true_values = $boolean_true_values;
                $rule->post_processing = $post_processing;
                $rule->is_active = true;

                // Check if this is an existing rule (has ID)
                if (!empty($rule_data['id'])) {
                    $rule->id = absint($rule_data['id']);
                    $this->repository->update_rule($rule);
                    $saved_rule_ids[] = $rule->id;
                } else {
                    $new_id = $this->repository->create_rule($rule);
                    if ($new_id) {
                        $saved_rule_ids[] = $new_id;
                    }
                }
            }
        }

        // Delete rules that were removed (not in saved_rule_ids but belong to this scraper)
        $this->cleanup_removed_rules($scraper_id, $saved_rule_ids);
    }

    /**
     * Clean up rules that were removed from the form.
     *
     * @param int $scraper_id
     * @param array $kept_rule_ids
     */
    private function cleanup_removed_rules(int $scraper_id, array $kept_rule_ids): void {
        $existing_rules = $this->repository->find_rules_by_scraper_id($scraper_id);

        foreach ($existing_rules as $rule) {
            if (!in_array($rule->id, $kept_rule_ids)) {
                $this->repository->delete_rule($rule->id);
            }
        }
    }
    
    
    /**
     * Get all rules by field type (supports multiple rules per field).
     *
     * @param array $rules
     * @param string $field_type
     * @return array Array of HFT_Scraper_Rule
     */
    private function get_rules_by_type(array $rules, string $field_type): array {
        $matching = [];
        foreach ($rules as $rule) {
            if ($rule->field_type === $field_type) {
                $matching[] = $rule;
            }
        }
        // Sort by priority
        usort($matching, fn($a, $b) => $a->priority <=> $b->priority);
        return $matching;
    }

    /**
     * Render a single rule form.
     *
     * @param string $field_type
     * @param HFT_Scraper_Rule|null $rule
     * @param int|string $index
     */
    private function render_rule_form(string $field_type, ?HFT_Scraper_Rule $rule, $index): void {
        $name_prefix = "rules[{$field_type}][{$index}]";
        $id_prefix = "rule_{$field_type}_{$index}";
        $extraction_mode = $rule ? $rule->extraction_mode : 'xpath';
        $post_processing = $rule ? ($rule->post_processing ?? []) : [];
        ?>
        <div class="hft-scraper-rule" data-index="<?php echo esc_attr($index); ?>">
            <?php if ($rule && $rule->id): ?>
                <input type="hidden" name="<?php echo $name_prefix; ?>[id]" value="<?php echo esc_attr($rule->id); ?>">
            <?php endif; ?>

            <div class="hft-rule-header">
                <span class="hft-rule-priority">
                    <label><?php _e('Priority:', 'housefresh-tools'); ?></label>
                    <input type="number" name="<?php echo $name_prefix; ?>[priority]"
                           value="<?php echo esc_attr($rule ? $rule->priority : (is_numeric($index) ? (($index + 1) * 10) : 10)); ?>"
                           min="1" max="999" class="small-text">
                </span>
                <span class="hft-rule-mode">
                    <label><?php _e('Mode:', 'housefresh-tools'); ?></label>
                    <select name="<?php echo $name_prefix; ?>[extraction_mode]" class="hft-extraction-mode-select">
                        <option value="xpath" <?php selected($extraction_mode, 'xpath'); ?>><?php _e('XPath', 'housefresh-tools'); ?></option>
                        <option value="xpath_regex" <?php selected($extraction_mode, 'xpath_regex'); ?>><?php _e('XPath + Regex', 'housefresh-tools'); ?></option>
                        <option value="css" <?php selected($extraction_mode, 'css'); ?>><?php _e('CSS Selector', 'housefresh-tools'); ?></option>
                        <option value="json_path" <?php selected($extraction_mode, 'json_path'); ?>><?php _e('JSON Path', 'housefresh-tools'); ?></option>
                    </select>
                </span>
                <button type="button" class="button button-link-delete hft-remove-rule" title="<?php esc_attr_e('Remove this rule', 'housefresh-tools'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>

            <table class="form-table hft-rule-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo $id_prefix; ?>_xpath"><?php _e('Selector', 'housefresh-tools'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $name_prefix; ?>[xpath]"
                                   id="<?php echo $id_prefix; ?>_xpath" class="large-text code"
                                   value="<?php echo $rule ? esc_attr($rule->xpath_selector) : ''; ?>"
                                   placeholder="<?php esc_attr_e('e.g., //span[@class="price"] or //html for full page', 'housefresh-tools'); ?>">
                            <p class="description hft-mode-hint hft-mode-xpath"><?php _e('XPath expression to select the element containing the value.', 'housefresh-tools'); ?></p>
                            <p class="description hft-mode-hint hft-mode-xpath_regex" style="display:none;"><?php _e('XPath to get content (use //html for full page), then apply regex below.', 'housefresh-tools'); ?></p>
                            <p class="description hft-mode-hint hft-mode-css" style="display:none;"><?php _e('CSS selector (e.g., .price, #product-price, span.amount).', 'housefresh-tools'); ?></p>
                            <p class="description hft-mode-hint hft-mode-json_path" style="display:none;"><?php _e('JSON path (e.g., $.product.price or product.offers[0].price).', 'housefresh-tools'); ?></p>
                        </td>
                    </tr>

                    <tr class="hft-attribute-row">
                        <th scope="row">
                            <label for="<?php echo $id_prefix; ?>_attribute"><?php _e('Attribute', 'housefresh-tools'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $name_prefix; ?>[attribute]"
                                   id="<?php echo $id_prefix; ?>_attribute" class="regular-text"
                                   value="<?php echo $rule ? esc_attr($rule->attribute ?? '') : ''; ?>"
                                   placeholder="<?php esc_attr_e('Leave empty for text content', 'housefresh-tools'); ?>">
                            <p class="description"><?php _e('Extract from this attribute instead of text content (e.g., content, data-price).', 'housefresh-tools'); ?></p>
                        </td>
                    </tr>

                    <!-- Regex extraction fields (for xpath_regex mode) -->
                    <tr class="hft-regex-extraction-row" style="<?php echo $extraction_mode === 'xpath_regex' ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label for="<?php echo $id_prefix; ?>_regex_pattern"><?php _e('Regex Pattern', 'housefresh-tools'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="<?php echo $name_prefix; ?>[regex_pattern]"
                                   id="<?php echo $id_prefix; ?>_regex_pattern" class="large-text code"
                                   value="<?php echo $rule ? esc_attr($rule->regex_pattern ?? '') : ''; ?>"
                                   placeholder="<?php esc_attr_e('/\"minimum_price\":\{[^}]*\"value\":([0-9]+\.?[0-9]*)/s', 'housefresh-tools'); ?>">
                            <p class="description"><?php _e('Regex with capture group. First capture group ($1) is returned as the value. For escaped quotes in JSON use \\\".', 'housefresh-tools'); ?></p>
                        </td>
                    </tr>

                    <tr class="hft-regex-extraction-row" style="<?php echo $extraction_mode === 'xpath_regex' ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label for="<?php echo $id_prefix; ?>_regex_fallbacks"><?php _e('Fallback Patterns', 'housefresh-tools'); ?></label>
                        </th>
                        <td>
                            <textarea name="<?php echo $name_prefix; ?>[regex_fallbacks]"
                                      id="<?php echo $id_prefix; ?>_regex_fallbacks" class="large-text code" rows="3"
                                      placeholder="<?php esc_attr_e('One pattern per line', 'housefresh-tools'); ?>"><?php
                                      if ($rule && !empty($rule->regex_fallbacks)) {
                                          echo esc_textarea(implode("\n", $rule->regex_fallbacks));
                                      }
                            ?></textarea>
                            <p class="description"><?php _e('Additional regex patterns to try if the primary pattern fails (one per line).', 'housefresh-tools'); ?></p>
                        </td>
                    </tr>

                    <!-- Boolean mode for stock status -->
                    <?php if ($field_type === 'status'): ?>
                    <tr class="hft-boolean-row" style="<?php echo $extraction_mode === 'xpath_regex' ? '' : 'display:none;'; ?>">
                        <th scope="row">
                            <label><?php _e('Boolean Mode', 'housefresh-tools'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo $name_prefix; ?>[return_boolean]" value="1"
                                       <?php checked($rule ? $rule->return_boolean : false); ?>
                                       class="hft-return-boolean-toggle">
                                <?php _e('Interpret extracted value as boolean (for in-stock checks)', 'housefresh-tools'); ?>
                            </label>
                            <div class="hft-boolean-values" style="<?php echo ($rule && $rule->return_boolean) ? '' : 'display:none;'; ?>">
                                <label for="<?php echo $id_prefix; ?>_boolean_true_values"><?php _e('Values that mean "In Stock":', 'housefresh-tools'); ?></label>
                                <input type="text" name="<?php echo $name_prefix; ?>[boolean_true_values]"
                                       id="<?php echo $id_prefix; ?>_boolean_true_values" class="regular-text"
                                       value="<?php echo $rule && $rule->boolean_true_values ? esc_attr(implode(',', $rule->boolean_true_values)) : 'true,1,yes'; ?>"
                                       placeholder="true,1,yes">
                                <p class="description"><?php _e('Comma-separated values. If extracted value matches any of these, status = "In Stock", otherwise "Out of Stock".', 'housefresh-tools'); ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <tr>
                        <th scope="row"><?php _e('Post-processing', 'housefresh-tools'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo $name_prefix; ?>[post_processing][]"
                                       value="trim" <?php checked(in_array('trim', $post_processing)); ?>>
                                <?php _e('Trim whitespace', 'housefresh-tools'); ?>
                            </label><br>
                            <?php if ($field_type === 'price'): ?>
                            <label>
                                <input type="checkbox" name="<?php echo $name_prefix; ?>[post_processing][]"
                                       value="remove_currency" <?php checked(in_array('remove_currency', $post_processing)); ?>>
                                <?php _e('Remove currency symbols', 'housefresh-tools'); ?>
                            </label><br>
                            <?php endif; ?>
                            <label>
                                <input type="checkbox" name="<?php echo $name_prefix; ?>[post_processing][]"
                                       value="regex_replace" <?php checked(in_array('regex_replace', $post_processing)); ?>
                                       class="hft-post-regex-toggle">
                                <?php _e('Regex replace (post-processing)', 'housefresh-tools'); ?>
                            </label>
                            <div class="hft-post-regex-options" style="<?php echo in_array('regex_replace', $post_processing) ? '' : 'display:none;'; ?>">
                                <input type="text" name="<?php echo $name_prefix; ?>[post_regex_pattern]"
                                       placeholder="<?php esc_attr_e('Pattern', 'housefresh-tools'); ?>" class="regular-text code"
                                       value="<?php echo isset($post_processing['regex_pattern']) ? esc_attr($post_processing['regex_pattern']) : ''; ?>">
                                <input type="text" name="<?php echo $name_prefix; ?>[post_regex_replacement]"
                                       placeholder="<?php esc_attr_e('Replacement', 'housefresh-tools'); ?>" class="regular-text"
                                       value="<?php echo isset($post_processing['regex_replacement']) ? esc_attr($post_processing['regex_replacement']) : ''; ?>">
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button type="button" class="button test-selector" data-field="<?php echo $field_type; ?>" data-index="<?php echo $index; ?>">
                                <?php _e('Test This Rule', 'housefresh-tools'); ?>
                            </button>
                            <span class="hft-test-result"></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Normalize domain.
     *
     * @param string $domain
     * @return string
     */
    private function normalize_domain(string $domain): string {
        // Remove protocol
        $domain = preg_replace('~^https?://~', '', $domain);
        
        // Remove www
        $domain = preg_replace('~^www\.~', '', $domain);
        
        // Remove trailing slash
        $domain = rtrim($domain, '/');
        
        // Convert to lowercase
        return strtolower($domain);
    }
    
    /**
     * Verify nonce.
     *
     * @param string $action
     * @return bool
     */
    private function verify_nonce(string $action): bool {
        $nonce_field = $action . '_nonce';
        return isset($_REQUEST[$nonce_field]) && wp_verify_nonce($_REQUEST[$nonce_field], $action);
    }
    
    /**
     * Add admin notice.
     *
     * @param string $type
     * @param string $message
     */
    private function add_admin_notice(string $type, string $message): void {
        add_settings_error('hft_scraper_admin', 'hft_scraper_' . $type, $message, $type);
    }
    
    /**
     * Display admin notices.
     */
    private function display_admin_notices(): void {
        // Check for transient notices
        $notice = get_transient('hft_scraper_admin_notice');
        if ($notice) {
            ?>
            <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                <p><?php echo esc_html($notice['message']); ?></p>
            </div>
            <?php
            delete_transient('hft_scraper_admin_notice');
        }
        
        // Also display any immediate notices
        settings_errors('hft_scraper_admin');
    }
    
    /**
     * Get scrapers list URL.
     *
     * @return string
     */
    private function get_scrapers_list_url(): string {
        return admin_url('admin.php?page=hft-scrapers');
    }
    
    /**
     * Get add scraper URL.
     *
     * @return string
     */
    private function get_add_scraper_url(): string {
        return admin_url('admin.php?page=hft-scraper-edit');
    }
    
    /**
     * Get edit scraper URL.
     *
     * @param int $scraper_id
     * @return string
     */
    private function get_edit_scraper_url(int $scraper_id): string {
        return add_query_arg('scraper_id', $scraper_id, $this->get_add_scraper_url());
    }
    
    /**
     * Get GEO whitelist for Tagify.
     * Includes all countries from geo groups plus common additional markets.
     *
     * @return array
     */
    private function get_geo_whitelist(): array {
        return [
            // North America
            ['value' => 'US', 'name' => 'United States'],
            ['value' => 'CA', 'name' => 'Canada'],
            ['value' => 'MX', 'name' => 'Mexico'],
            // UK
            ['value' => 'GB', 'name' => 'United Kingdom'],
            // EU Countries
            ['value' => 'AT', 'name' => 'Austria'],
            ['value' => 'BE', 'name' => 'Belgium'],
            ['value' => 'BG', 'name' => 'Bulgaria'],
            ['value' => 'HR', 'name' => 'Croatia'],
            ['value' => 'CY', 'name' => 'Cyprus'],
            ['value' => 'CZ', 'name' => 'Czech Republic'],
            ['value' => 'DK', 'name' => 'Denmark'],
            ['value' => 'EE', 'name' => 'Estonia'],
            ['value' => 'FI', 'name' => 'Finland'],
            ['value' => 'FR', 'name' => 'France'],
            ['value' => 'DE', 'name' => 'Germany'],
            ['value' => 'GR', 'name' => 'Greece'],
            ['value' => 'HU', 'name' => 'Hungary'],
            ['value' => 'IE', 'name' => 'Ireland'],
            ['value' => 'IT', 'name' => 'Italy'],
            ['value' => 'LV', 'name' => 'Latvia'],
            ['value' => 'LT', 'name' => 'Lithuania'],
            ['value' => 'LU', 'name' => 'Luxembourg'],
            ['value' => 'MT', 'name' => 'Malta'],
            ['value' => 'NL', 'name' => 'Netherlands'],
            ['value' => 'PL', 'name' => 'Poland'],
            ['value' => 'PT', 'name' => 'Portugal'],
            ['value' => 'RO', 'name' => 'Romania'],
            ['value' => 'SK', 'name' => 'Slovakia'],
            ['value' => 'SI', 'name' => 'Slovenia'],
            ['value' => 'ES', 'name' => 'Spain'],
            ['value' => 'SE', 'name' => 'Sweden'],
            // Non-EU European
            ['value' => 'NO', 'name' => 'Norway'],
            ['value' => 'IS', 'name' => 'Iceland'],
            ['value' => 'CH', 'name' => 'Switzerland'],
            // Asia-Pacific
            ['value' => 'AU', 'name' => 'Australia'],
            ['value' => 'NZ', 'name' => 'New Zealand'],
            ['value' => 'JP', 'name' => 'Japan'],
            ['value' => 'SG', 'name' => 'Singapore'],
            ['value' => 'IN', 'name' => 'India'],
            // Other Markets
            ['value' => 'BR', 'name' => 'Brazil'],
            ['value' => 'AE', 'name' => 'United Arab Emirates'],
            // Special
            ['value' => 'GLOBAL', 'name' => 'Global (All Countries)'],
        ];
    }

    /**
     * Process GEOs input from Tagify format.
     * Expands any geo group identifiers to their country codes.
     *
     * @param string $input Raw input from Tagify.
     * @return array Array with 'expanded' (for DB storage) and 'original' (for UI restoration).
     */
    private function process_geos_input(string $input): array {
        $geo_values = [];

        if (!empty($input) && $input[0] === '[') {
            // It's JSON from Tagify
            $geos_array = json_decode(stripslashes($input), true);
            if (is_array($geos_array)) {
                $geo_values = array_column($geos_array, 'value');
            }
        } elseif (!empty($input)) {
            // It's already a comma-separated string
            $geo_values = array_map('trim', explode(',', $input));
        }

        // Store the original input (pre-expansion) for UI restoration
        $original = implode(',', array_filter($geo_values));

        // Expand any geo groups to individual country codes
        if (class_exists('HFT_Geo_Groups')) {
            $expanded_values = HFT_Geo_Groups::expand($geo_values);
        } else {
            $expanded_values = $geo_values;
        }

        // Sanitize and deduplicate
        $expanded_values = array_map('sanitize_text_field', $expanded_values);
        $expanded_values = array_unique(array_filter($expanded_values));
        $expanded = implode(',', $expanded_values);

        return [
            'expanded' => $expanded,
            'original' => $original,
        ];
    }

    /**
     * Show migration notice for unmigrated links
     */
    public function show_migration_notice(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if we have unmigrated links
        global $wpdb;
        $table = $wpdb->prefix . 'hft_tracked_links';
        
        $unmigrated = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$table} 
            WHERE parser_identifier != 'amazon' 
            AND parser_identifier IS NOT NULL
            AND scraper_id IS NULL
        ");
        
        if ($unmigrated > 0) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php 
                    printf(
                        __('You have %d tracked links using the old parser system. Consider creating scrapers for them in the new system.', 'housefresh-tools'),
                        $unmigrated
                    );
                    ?>
                    <a href="<?php echo admin_url('admin.php?page=hft-scrapers'); ?>">
                        <?php _e('Manage Scrapers', 'housefresh-tools'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
}