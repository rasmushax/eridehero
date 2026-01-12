<?php
/**
 * Plugin Name: ERideHero Core
 * Plugin URI: https://eridehero.com
 * Description: Core functionality for ERideHero - product management, pricing, reviews, and user system.
 * Version: 1.0.0
 * Author: ERideHero
 * Author URI: https://eridehero.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: erh-core
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package ERH
 */

declare(strict_types=1);

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants.
define('ERH_VERSION', '1.0.0');
define('ERH_PLUGIN_FILE', __FILE__);
define('ERH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ERH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ERH_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Database table names (without prefix).
define('ERH_TABLE_PRODUCT_DATA', 'product_data');
define('ERH_TABLE_PRICE_HISTORY', 'product_daily_prices');
define('ERH_TABLE_PRICE_TRACKERS', 'price_trackers');
define('ERH_TABLE_PRODUCT_VIEWS', 'product_views');

/**
 * Load Composer autoloader.
 */
$autoloader = ERH_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

/**
 * Manual autoloader fallback if Composer autoload is not available.
 * This is useful during development before running composer dump-autoload.
 *
 * @param string $class The fully-qualified class name.
 */
spl_autoload_register(function (string $class): void {
    // Only autoload classes in the ERH namespace.
    $prefix = 'ERH\\';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name.
    $relative_class = substr($class, $len);

    // Convert namespace separators to directory separators.
    // Convert CamelCase to kebab-case for file names.
    $parts = explode('\\', $relative_class);
    $class_file = array_pop($parts);

    // Convert class name to file name (e.g., PriceFetcher -> class-price-fetcher.php).
    $file_name = 'class-' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $class_file)) . '.php';

    // Build the directory path.
    $subdir = '';
    if (!empty($parts)) {
        $subdir = implode('/', array_map(function ($part) {
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $part));
        }, $parts)) . '/';
    }

    $file = ERH_PLUGIN_DIR . 'includes/' . $subdir . $file_name;

    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Check plugin dependencies at runtime (after plugins loaded).
 *
 * @return bool True if all dependencies are met.
 */
function erh_check_dependencies(): bool {
    $errors = [];

    // Check for ACF Pro (only at runtime, not during activation).
    if (!class_exists('ACF')) {
        $errors[] = __('Advanced Custom Fields Pro is required for full functionality.', 'erh-core');
    }

    // Check PHP version.
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $errors[] = sprintf(
            __('PHP 7.4 or higher is required. Current version: %s', 'erh-core'),
            PHP_VERSION
        );
    }

    if (!empty($errors)) {
        add_action('admin_notices', function () use ($errors) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('ERideHero Core:', 'erh-core') . '</strong></p><ul>';
            foreach ($errors as $error) {
                echo '<li>' . esc_html($error) . '</li>';
            }
            echo '</ul></div>';
        });
        return false;
    }

    return true;
}

/**
 * Check minimum requirements for activation.
 * Only checks things that can be verified before other plugins load.
 *
 * @return bool True if minimum requirements are met.
 */
function erh_check_activation_requirements(): bool {
    $errors = [];

    // Check PHP version.
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $errors[] = sprintf(
            __('PHP 7.4 or higher is required. Current version: %s', 'erh-core'),
            PHP_VERSION
        );
    }

    // Check if ACF plugin file exists (doesn't require it to be loaded).
    $acf_paths = [
        WP_PLUGIN_DIR . '/advanced-custom-fields-pro/acf.php',
        WP_PLUGIN_DIR . '/advanced-custom-fields/acf.php',
    ];

    $acf_found = false;
    foreach ($acf_paths as $path) {
        if (file_exists($path)) {
            $acf_found = true;
            break;
        }
    }

    if (!$acf_found) {
        // This is a warning, not a blocker - ACF might be in a different location.
        // We'll check again at runtime.
    }

    if (!empty($errors)) {
        return false;
    }

    return true;
}

/**
 * Initialize the plugin.
 */
function erh_init(): void {
    if (!erh_check_dependencies()) {
        return;
    }

    // Initialize the main plugin class.
    $core = new ERH\Core();
    $core->init();
}
add_action('plugins_loaded', 'erh_init');

/**
 * Plugin activation hook.
 */
function erh_activate(): void {
    // Check minimum requirements before activation.
    if (!erh_check_activation_requirements()) {
        wp_die(
            esc_html__('ERideHero Core cannot be activated. PHP 7.4 or higher is required.', 'erh-core'),
            esc_html__('Plugin Activation Error', 'erh-core'),
            ['back_link' => true]
        );
    }

    // Create database tables.
    require_once ERH_PLUGIN_DIR . 'includes/database/class-schema.php';
    $schema = new ERH\Database\Schema();
    $schema->create_tables();

    // Flush rewrite rules for CPTs.
    flush_rewrite_rules();

    // Set plugin version in options.
    update_option('erh_version', ERH_VERSION);
}
register_activation_hook(__FILE__, 'erh_activate');

/**
 * Plugin deactivation hook.
 */
function erh_deactivate(): void {
    // Clear scheduled cron jobs.
    wp_clear_scheduled_hook('erh_daily_price_update');
    wp_clear_scheduled_hook('erh_cache_rebuild');
    wp_clear_scheduled_hook('erh_search_json_update');
    wp_clear_scheduled_hook('erh_price_tracker_check');
    wp_clear_scheduled_hook('erh_deals_email');

    // Flush rewrite rules.
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'erh_deactivate');

/**
 * Plugin uninstall hook is handled in uninstall.php.
 */
