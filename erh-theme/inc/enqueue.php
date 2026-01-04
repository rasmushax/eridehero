<?php
/**
 * Enqueue Scripts and Styles
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue theme styles and scripts
 */
function erh_enqueue_assets(): void {
    $version = ERH_THEME_VERSION;

    // Check if we have a production build
    $dist_css = ERH_THEME_DIR . '/assets/css/dist/style.min.css';
    $use_dist = file_exists( $dist_css );

    // =========================================
    // STYLES
    // =========================================

    // Self-hosted Figtree font
    wp_enqueue_style(
        'erh-fonts',
        ERH_THEME_URI . '/assets/fonts/figtree.css',
        array(),
        $version
    );

    if ( $use_dist ) {
        // Production: Use minified, concatenated CSS
        wp_enqueue_style(
            'erh-style',
            ERH_THEME_URI . '/assets/css/dist/style.min.css',
            array( 'erh-fonts' ),
            filemtime( $dist_css )
        );
    } else {
        // Development: Use main CSS file with imports
        wp_enqueue_style(
            'erh-style',
            ERH_THEME_URI . '/assets/css/style.css',
            array( 'erh-fonts' ),
            $version
        );
    }

    // =========================================
    // SCRIPTS
    // =========================================

    // Check for production JS bundle
    $dist_js = ERH_THEME_DIR . '/assets/js/dist/app.min.js';
    $use_dist_js = file_exists( $dist_js );

    if ( $use_dist_js ) {
        // Production: Use bundled JS
        wp_enqueue_script(
            'erh-app',
            ERH_THEME_URI . '/assets/js/dist/app.min.js',
            array(),
            filemtime( $dist_js ),
            true
        );
    } else {
        // Development: Use ES modules
        wp_enqueue_script(
            'erh-app',
            ERH_THEME_URI . '/assets/js/app.js',
            array(),
            $version,
            true
        );

        // Add module type attribute
        add_filter( 'script_loader_tag', 'erh_add_module_type', 10, 3 );
    }

    // Localize script with data the JS might need
    wp_localize_script( 'erh-app', 'erhData', array(
        'siteUrl'    => home_url(),
        'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
        'restUrl'    => rest_url( 'erh/v1/' ),
        'hftRestUrl' => rest_url( 'housefresh-tools/v1/' ),
        'nonce'      => wp_create_nonce( 'wp_rest' ), // REST API nonce
        'ajaxNonce'  => wp_create_nonce( 'erh_nonce' ), // Legacy AJAX nonce
        'themeUrl'   => ERH_THEME_URI,
        'isLoggedIn' => is_user_logged_in(),
    ) );

    // Comment reply script (only on singular with comments)
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}
add_action( 'wp_enqueue_scripts', 'erh_enqueue_assets' );

/**
 * Add type="module" to ES module scripts
 */
function erh_add_module_type( string $tag, string $handle, string $src ): string {
    $module_handles = array( 'erh-app' );

    if ( in_array( $handle, $module_handles, true ) ) {
        $tag = str_replace( '<script ', '<script type="module" ', $tag );
    }

    return $tag;
}

/**
 * Preload critical fonts
 */
function erh_preload_fonts(): void {
    $font_path = ERH_THEME_URI . '/assets/fonts/';
    ?>
    <link rel="preload" href="<?php echo esc_url( $font_path . 'Figtree-VariableFont_wght.woff2' ); ?>" as="font" type="font/woff2" crossorigin>
    <?php
}
add_action( 'wp_head', 'erh_preload_fonts', 1 );

/**
 * Add preconnect for any external resources
 */
function erh_resource_hints( array $hints, string $relation_type ): array {
    if ( 'preconnect' === $relation_type ) {
        // Add any external domains we connect to
        // Currently none since fonts are self-hosted
    }

    return $hints;
}
add_filter( 'wp_resource_hints', 'erh_resource_hints', 10, 2 );

/**
 * Remove unnecessary default styles
 */
function erh_dequeue_unnecessary(): void {
    // Remove WordPress block library CSS if not using blocks
    if ( ! is_admin() ) {
        // Keep block styles for now in case we use blocks
        // wp_dequeue_style( 'wp-block-library' );
        // wp_dequeue_style( 'wp-block-library-theme' );

        // Remove classic theme styles
        wp_dequeue_style( 'classic-theme-styles' );

        // Remove global styles if not needed
        // wp_dequeue_style( 'global-styles' );
    }
}
add_action( 'wp_enqueue_scripts', 'erh_dequeue_unnecessary', 100 );

/**
 * Admin-specific styles
 */
function erh_admin_styles(): void {
    wp_enqueue_style(
        'erh-admin',
        ERH_THEME_URI . '/assets/css/admin.css',
        array(),
        ERH_THEME_VERSION
    );
}
add_action( 'admin_enqueue_scripts', 'erh_admin_styles' );
