<?php
/**
 * Plugin Name:       Housefresh Tools
 * Plugin URI:        https://housefresh.com
 * Description:       Tools for managing Housefresh affiliate products, including price tracking.
 * Version:           0.1.0
 * Author:            Rasmus Haxholm
 * Author URI:        https://haxholmweb.dk
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       housefresh-tools
 * Domain Path:       /languages
 */

declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define HFT_VERSION.
if ( ! defined( 'HFT_VERSION' ) ) {
	define( 'HFT_VERSION', '0.1.0' );
}

// Define HFT_PLUGIN_FILE.
if ( ! defined( 'HFT_PLUGIN_FILE' ) ) {
	define( 'HFT_PLUGIN_FILE', __FILE__ );
}

// Define HFT_PLUGIN_PATH.
if ( ! defined( 'HFT_PLUGIN_PATH' ) ) {
	define( 'HFT_PLUGIN_PATH', plugin_dir_path( HFT_PLUGIN_FILE ) );
}

// Define HFT_PLUGIN_URL.
if ( ! defined( 'HFT_PLUGIN_URL' ) ) {
	define( 'HFT_PLUGIN_URL', plugin_dir_url( HFT_PLUGIN_FILE ) );
}

// Load Composer autoloader if it exists.
if ( file_exists( HFT_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
	require_once HFT_PLUGIN_PATH . 'vendor/autoload.php';
} else {
	// It's a good idea to add an admin notice if Composer dependencies are missing.
	add_action( 'admin_notices', function() {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'Housefresh Tools plugin is missing its Composer dependencies. Please run `composer install` in the plugin directory.', 'housefresh-tools' );
		echo '</p></div>';
	});
	// Optionally, prevent further plugin execution if dependencies are critical.
	// return;
}

// Include the main loader class.
$hft_loader_file = HFT_PLUGIN_PATH . 'includes/class-hft-loader.php';
if ( file_exists( $hft_loader_file ) ) {
	require_once $hft_loader_file;
} else {
	// Optionally, add some error handling here if the file is critical and not found.
	// For example, trigger_error( 'Housefresh Tools: Loader class not found.', E_USER_ERROR );
	return; // Stop further execution if loader is missing.
}

// Get an instance of the loader and initialize.
if ( class_exists( 'HFT_Loader' ) ) {
	$hft_loader = HFT_Loader::get_instance();
	$hft_loader->init();

	// Register activation and deactivation hooks.
	register_activation_hook( HFT_PLUGIN_FILE, [ 'HFT_Loader', 'activate' ] );
	register_deactivation_hook( HFT_PLUGIN_FILE, [ 'HFT_Loader', 'deactivate' ] );
}
