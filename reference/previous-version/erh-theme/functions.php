<?php
/**
 * ERideHero Theme Functions
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Theme version - update when making changes for cache busting
define( 'ERH_THEME_VERSION', '1.0.0' );
define( 'ERH_THEME_DIR', get_template_directory() );
define( 'ERH_THEME_URI', get_template_directory_uri() );

/**
 * Include theme files
 */
require_once ERH_THEME_DIR . '/inc/theme-setup.php';
require_once ERH_THEME_DIR . '/inc/enqueue.php';
require_once ERH_THEME_DIR . '/inc/template-functions.php';
require_once ERH_THEME_DIR . '/inc/acf-options.php';
require_once ERH_THEME_DIR . '/inc/compare-routes.php';
require_once ERH_THEME_DIR . '/inc/archive-routes.php';
