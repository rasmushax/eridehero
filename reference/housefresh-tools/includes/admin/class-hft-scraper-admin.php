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
            'strings' => [
                'testInProgress' => __('Testing...', 'housefresh-tools'),
                'testComplete' => __('Test Complete', 'housefresh-tools'),
                'testError' => __('Test failed. Please check your settings.', 'housefresh-tools'),
                'enterUrl' => __('Please enter a test URL', 'housefresh-tools'),
                'testButton' => __('Test Scraper', 'housefresh-tools'),
                'selectLogo' => __('Select Logo', 'housefresh-tools'),
                'useLogo' => __('Use this logo', 'housefresh-tools'),
                'removeLogo' => __('Remove', 'housefresh-tools'),
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
                                if ($scraper && !empty($scraper->geos)) {
                                    $geos_value = $scraper->geos;
                                }
                                ?>
                                <input type="text" name="geos" id="geos" class="hft-geos-tagify regular-text" 
                                       value="<?php echo esc_attr($geos_value); ?>"
                                       placeholder="Type or select GEOs">
                                <p class="description">Geographic regions where affiliate links should be generated (e.g., US, GB, CA)</p>
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
                <p class="description">Define XPath selectors to extract product data. Leave fields empty to skip extraction.</p>
                
                <div class="hft-scraper-rules">
                    <?php
                    $field_types = ['price', 'status', 'shipping'];
                    foreach ($field_types as $field_type):
                        $rule = $this->get_rule_by_type($rules, $field_type);
                    ?>
                        <div class="hft-scraper-rule">
                            <h3><?php echo ucfirst($field_type); ?></h3>
                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="rule_<?php echo $field_type; ?>_xpath">XPath Selector</label></th>
                                        <td>
                                            <input type="text" name="rules[<?php echo $field_type; ?>][xpath]" 
                                                   id="rule_<?php echo $field_type; ?>_xpath" class="large-text"
                                                   value="<?php echo $rule ? esc_attr($rule->xpath_selector) : ''; ?>">
                                            <p class="description">XPath expression to select the element</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="rule_<?php echo $field_type; ?>_attribute">Attribute</label></th>
                                        <td>
                                            <input type="text" name="rules[<?php echo $field_type; ?>][attribute]" 
                                                   id="rule_<?php echo $field_type; ?>_attribute" class="regular-text"
                                                   value="<?php echo $rule ? esc_attr($rule->attribute ?? '') : ''; ?>">
                                            <p class="description">Optional: Extract from attribute instead of text content</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Post-processing</th>
                                        <td>
                                            <?php $post_processing = $rule ? $rule->post_processing ?? [] : []; ?>
                                            <label>
                                                <input type="checkbox" name="rules[<?php echo $field_type; ?>][post_processing][]" 
                                                       value="trim" <?php checked(in_array('trim', $post_processing)); ?>>
                                                Trim whitespace
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="rules[<?php echo $field_type; ?>][post_processing][]" 
                                                       value="remove_currency" <?php checked(in_array('remove_currency', $post_processing)); ?>>
                                                Remove currency symbols
                                            </label><br>
                                            <label>
                                                <input type="checkbox" name="rules[<?php echo $field_type; ?>][post_processing][]" 
                                                       value="regex_replace" <?php checked(in_array('regex_replace', $post_processing)); ?>
                                                       class="hft-regex-toggle" data-field="<?php echo $field_type; ?>">
                                                Regex replace
                                            </label>
                                            <div class="hft-regex-options" id="regex_options_<?php echo $field_type; ?>" 
                                                 style="<?php echo in_array('regex_replace', $post_processing) ? '' : 'display:none;'; ?>">
                                                <input type="text" name="rules[<?php echo $field_type; ?>][regex_pattern]" 
                                                       placeholder="Pattern" class="regular-text"
                                                       value="<?php echo isset($post_processing['regex_pattern']) ? esc_attr($post_processing['regex_pattern']) : ''; ?>">
                                                <input type="text" name="rules[<?php echo $field_type; ?>][regex_replacement]" 
                                                       placeholder="Replacement" class="regular-text"
                                                       value="<?php echo isset($post_processing['regex_replacement']) ? esc_attr($post_processing['regex_replacement']) : ''; ?>">
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"></th>
                                        <td>
                                            <button type="button" class="button test-selector">
                                                <?php _e('Test This Selector', 'housefresh-tools'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
                
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

            // Process GEOs from Tagify format
            $geos_input = $_POST['geos'] ?? '';
            if (!empty($geos_input) && $geos_input[0] === '[') {
                // It's JSON from Tagify
                $geos_array = json_decode(stripslashes($geos_input), true);
                if (is_array($geos_array)) {
                    $geo_values = array_column($geos_array, 'value');
                    $scraper->geos = implode(',', $geo_values);
                } else {
                    $scraper->geos = '';
                }
            } else {
                // It's already a comma-separated string
                $scraper->geos = sanitize_text_field($geos_input);
            }

            $scraper->affiliate_link_format = sanitize_text_field($_POST['affiliate_link_format'] ?? '');
            $scraper->test_url = esc_url_raw($_POST['test_url'] ?? '') ?: null;
            $scraper->use_base_parser = $use_base_parser;
            $scraper->use_curl = $use_curl;
            $scraper->use_scrapingrobot = $use_scrapingrobot;
            $scraper->scrapingrobot_render_js = $scrapingrobot_render_js;

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

            // Process GEOs from Tagify format
            $geos_input = $_POST['geos'] ?? '';
            if (!empty($geos_input) && $geos_input[0] === '[') {
                // It's JSON from Tagify
                $geos_array = json_decode(stripslashes($geos_input), true);
                if (is_array($geos_array)) {
                    $geo_values = array_column($geos_array, 'value');
                    $scraper->geos = implode(',', $geo_values);
                } else {
                    $scraper->geos = '';
                }
            } else {
                // It's already a comma-separated string
                $scraper->geos = sanitize_text_field($geos_input);
            }

            $scraper->affiliate_link_format = sanitize_text_field($_POST['affiliate_link_format'] ?? '');
            $scraper->test_url = esc_url_raw($_POST['test_url'] ?? '') ?: null;
            $scraper->use_base_parser = $use_base_parser;
            $scraper->use_curl = $use_curl;
            $scraper->use_scrapingrobot = $use_scrapingrobot;
            $scraper->scrapingrobot_render_js = $scrapingrobot_render_js;
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
     * Save scraper rules.
     *
     * @param int $scraper_id
     */
    private function save_scraper_rules(int $scraper_id): void {
        $rules = $_POST['rules'] ?? [];
        
        foreach ($rules as $field_type => $rule_data) {
            // Process XPath without escaping quotes but with basic sanitization
            $xpath_raw = $rule_data['xpath'] ?? '';
            $xpath = wp_unslash(trim($xpath_raw));
            
            // Basic security: remove any script tags and dangerous characters while preserving XPath syntax
            $xpath = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $xpath);
            $xpath = str_replace(['<script', '</script>', 'javascript:', 'vbscript:'], '', $xpath);
            
            // Skip if no xpath provided
            if (empty($xpath)) {
                // Delete existing rule if any
                $this->repository->delete_rule_by_scraper_and_field($scraper_id, $field_type);
                continue;
            }
            
            // Prepare post-processing
            $post_processing = [];
            if (isset($rule_data['post_processing']) && is_array($rule_data['post_processing'])) {
                $post_processing = $rule_data['post_processing'];
                
                // Add regex pattern/replacement if regex_replace is selected
                if (in_array('regex_replace', $post_processing)) {
                    $post_processing['regex_pattern'] = sanitize_text_field($rule_data['regex_pattern'] ?? '');
                    $post_processing['regex_replacement'] = sanitize_text_field($rule_data['regex_replacement'] ?? '');
                }
            }
            
            $rule = new HFT_Scraper_Rule();
            $rule->scraper_id = $scraper_id;
            $rule->field_type = $field_type;
            $rule->xpath_selector = $xpath;
            $rule->attribute = sanitize_text_field($rule_data['attribute'] ?? '') ?: null;
            $rule->post_processing = $post_processing;
            $rule->is_active = true;
            
            // Check if rule exists
            $existing_rule = $this->repository->find_rule_by_scraper_and_field($scraper_id, $field_type);
            if ($existing_rule) {
                $rule->id = $existing_rule->id;
                $this->repository->update_rule($rule);
            } else {
                $this->repository->create_rule($rule);
            }
        }
    }
    
    
    /**
     * Get rule by field type.
     *
     * @param array $rules
     * @param string $field_type
     * @return HFT_Scraper_Rule|null
     */
    private function get_rule_by_type(array $rules, string $field_type): ?HFT_Scraper_Rule {
        foreach ($rules as $rule) {
            if ($rule->field_type === $field_type) {
                return $rule;
            }
        }
        return null;
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
     *
     * @return array
     */
    private function get_geo_whitelist(): array {
        return [
            ['value' => 'US', 'name' => 'United States'],
            ['value' => 'GB', 'name' => 'United Kingdom'],
            ['value' => 'CA', 'name' => 'Canada'],
            ['value' => 'DE', 'name' => 'Germany'],
            ['value' => 'FR', 'name' => 'France'],
            ['value' => 'ES', 'name' => 'Spain'],
            ['value' => 'IT', 'name' => 'Italy'],
            ['value' => 'AU', 'name' => 'Australia'],
            ['value' => 'BR', 'name' => 'Brazil'],
            ['value' => 'IN', 'name' => 'India'],
            ['value' => 'JP', 'name' => 'Japan'],
            ['value' => 'MX', 'name' => 'Mexico'],
            ['value' => 'NL', 'name' => 'Netherlands'],
            ['value' => 'SE', 'name' => 'Sweden'],
            ['value' => 'PL', 'name' => 'Poland'],
            ['value' => 'BE', 'name' => 'Belgium'],
            ['value' => 'SG', 'name' => 'Singapore'],
            ['value' => 'AE', 'name' => 'United Arab Emirates'],
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