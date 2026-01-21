<?php
/**
 * Spec Editor - Admin page for bulk editing product specifications.
 *
 * Provides an Excel-like interface for editing ACF fields across products.
 * Features: inline editing, auto-save, undo/redo, column pinning, sorting.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\CategoryConfig;
use ERH\Schema\AcfSchemaParser;

/**
 * Admin page for the Spec Editor dashboard.
 */
class SpecEditor {

    /**
     * Page slug.
     */
    public const PAGE_SLUG = 'erh-spec-editor';

    /**
     * Schema parser instance.
     *
     * @var AcfSchemaParser
     */
    private AcfSchemaParser $schema_parser;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->schema_parser = new AcfSchemaParser();
    }

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Add the admin menu page.
     *
     * @return void
     */
    public function add_menu_page(): void {
        add_submenu_page(
            'edit.php?post_type=products',
            __('Spec Editor', 'erh-core'),
            __('Spec Editor', 'erh-core'),
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
        if ($hook !== 'products_page_' . self::PAGE_SLUG) {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(dirname(__FILE__)));
        $version = defined('ERH_VERSION') ? ERH_VERSION : '1.0.0';

        // Enqueue CSS.
        wp_enqueue_style(
            'erh-spec-editor',
            $plugin_url . 'assets/css/spec-editor.css',
            [],
            $version
        );

        // Enqueue main JS module.
        wp_enqueue_script(
            'erh-spec-editor',
            $plugin_url . 'assets/js/spec-editor/index.js',
            [],
            $version,
            true
        );

        // Add module type attribute.
        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle === 'erh-spec-editor') {
                return str_replace(' src', ' type="module" src', $tag);
            }
            return $tag;
        }, 10, 2);

        // Get product types for tabs.
        $product_types = [];
        foreach (CategoryConfig::CATEGORIES as $key => $config) {
            $product_types[$key] = [
                'key'   => $key,
                'label' => $config['name'],
                'type'  => $config['type'],
            ];
        }

        // Localize script with configuration.
        wp_localize_script('erh-spec-editor', 'erhSpecEditor', [
            'restUrl'      => rest_url('erh/v1/spec-editor/'),
            'nonce'        => wp_create_nonce('wp_rest'),
            'adminUrl'     => admin_url(),
            'productTypes' => $product_types,
            'defaultType'  => 'escooter',
            'i18n'         => [
                'loading'      => __('Loading...', 'erh-core'),
                'saving'       => __('Saving...', 'erh-core'),
                'saved'        => __('Saved', 'erh-core'),
                'error'        => __('Error', 'erh-core'),
                'noProducts'   => __('No products found.', 'erh-core'),
                'searchPlaceholder' => __('Search products...', 'erh-core'),
                'columns'      => __('Columns', 'erh-core'),
                'undo'         => __('Undo', 'erh-core'),
                'redo'         => __('Redo', 'erh-core'),
                'history'      => __('History', 'erh-core'),
                'showAll'      => __('Show All', 'erh-core'),
                'hideAll'      => __('Hide All', 'erh-core'),
                'reset'        => __('Reset', 'erh-core'),
                'noChanges'    => __('No changes yet', 'erh-core'),
                'changesCount' => __('%d change(s)', 'erh-core'),
                'undoAction'   => __('Undo: %s', 'erh-core'),
                'redoAction'   => __('Redo: %s', 'erh-core'),
                'confirmClear' => __('Clear all change history?', 'erh-core'),
                'page'         => __('Page', 'erh-core'),
                'of'           => __('of', 'erh-core'),
                'products'     => __('products', 'erh-core'),
                'true'         => __('Yes', 'erh-core'),
                'false'        => __('No', 'erh-core'),
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

        ?>
        <div class="wrap erh-spec-editor">
            <h1 class="wp-heading-inline"><?php esc_html_e('Spec Editor', 'erh-core'); ?></h1>
            <p class="description">
                <?php esc_html_e('Bulk edit product specifications. Click any cell to edit, changes save automatically.', 'erh-core'); ?>
            </p>

            <!-- Product Type Tabs -->
            <nav class="erh-se-tabs" role="tablist" aria-label="<?php esc_attr_e('Product Types', 'erh-core'); ?>">
                <!-- Tabs rendered by JavaScript -->
            </nav>

            <!-- Toolbar -->
            <div class="erh-se-toolbar">
                <div class="erh-se-toolbar-left">
                    <input type="search"
                           id="erh-se-search"
                           class="erh-se-search"
                           placeholder="<?php esc_attr_e('Search products...', 'erh-core'); ?>"
                           aria-label="<?php esc_attr_e('Search products', 'erh-core'); ?>">
                </div>
                <div class="erh-se-toolbar-right">
                    <div class="erh-se-column-picker">
                        <button type="button" class="button erh-se-columns-btn" id="erh-se-columns-btn">
                            <?php esc_html_e('Columns', 'erh-core'); ?>
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                        <div class="erh-se-columns-dropdown" id="erh-se-columns-dropdown" style="display: none;">
                            <!-- Column checkboxes rendered by JavaScript -->
                        </div>
                    </div>
                    <div class="erh-se-history-controls">
                        <button type="button" class="button erh-se-undo-btn" id="erh-se-undo" disabled title="<?php esc_attr_e('Undo (Ctrl+Z)', 'erh-core'); ?>">
                            <span class="dashicons dashicons-undo"></span>
                        </button>
                        <button type="button" class="button erh-se-redo-btn" id="erh-se-redo" disabled title="<?php esc_attr_e('Redo (Ctrl+Shift+Z)', 'erh-core'); ?>">
                            <span class="dashicons dashicons-redo"></span>
                        </button>
                        <button type="button" class="button erh-se-history-btn" id="erh-se-history-btn">
                            <?php esc_html_e('History', 'erh-core'); ?>
                            <span class="erh-se-history-count" id="erh-se-history-count">0</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- History Panel (Slide-out) -->
            <div class="erh-se-history-panel" id="erh-se-history-panel" style="display: none;">
                <div class="erh-se-history-header">
                    <h3><?php esc_html_e('Change History', 'erh-core'); ?></h3>
                    <button type="button" class="erh-se-history-close" id="erh-se-history-close" aria-label="<?php esc_attr_e('Close', 'erh-core'); ?>">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                </div>
                <div class="erh-se-history-list" id="erh-se-history-list">
                    <!-- History items rendered by JavaScript -->
                </div>
                <div class="erh-se-history-footer">
                    <button type="button" class="button" id="erh-se-history-clear">
                        <?php esc_html_e('Clear History', 'erh-core'); ?>
                    </button>
                </div>
            </div>

            <!-- Table Container -->
            <div class="erh-se-table-container" id="erh-se-table-container">
                <div class="erh-se-loading" id="erh-se-loading">
                    <span class="spinner is-active"></span>
                    <span><?php esc_html_e('Loading...', 'erh-core'); ?></span>
                </div>
                <!-- Table rendered by JavaScript -->
            </div>

            <!-- Pagination -->
            <div class="erh-se-pagination" id="erh-se-pagination" style="display: none;">
                <div class="erh-se-pagination-info">
                    <span id="erh-se-page-info"></span>
                </div>
                <div class="erh-se-pagination-controls">
                    <button type="button" class="button" id="erh-se-prev" disabled>
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                        <?php esc_html_e('Previous', 'erh-core'); ?>
                    </button>
                    <span class="erh-se-page-numbers" id="erh-se-page-numbers"></span>
                    <button type="button" class="button" id="erh-se-next" disabled>
                        <?php esc_html_e('Next', 'erh-core'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>
            </div>

            <!-- Status Bar -->
            <div class="erh-se-status-bar" id="erh-se-status-bar" style="display: none;">
                <span class="erh-se-status-icon"></span>
                <span class="erh-se-status-text"></span>
            </div>
        </div>
        <?php
    }
}
