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

    // Build erhData object.
    $upload_dir = wp_upload_dir();
    $search_json_path = $upload_dir['basedir'] . '/search_items.json';
    $search_json_version = file_exists( $search_json_path ) ? filemtime( $search_json_path ) : '';
    $erh_data = array(
        'siteUrl'       => home_url(),
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'restUrl'       => rest_url( 'erh/v1/' ),
        'hftRestUrl'    => rest_url( 'housefresh-tools/v1/' ),
        'nonce'         => wp_create_nonce( 'wp_rest' ), // REST API nonce
        'ajaxNonce'     => wp_create_nonce( 'erh_nonce' ), // Legacy AJAX nonce
        'themeUrl'      => ERH_THEME_URI,
        'isLoggedIn'    => is_user_logged_in(),
        'searchJsonUrl' => $upload_dir['baseurl'] . '/search_items.json' . ( $search_json_version ? '?v=' . $search_json_version : '' ),
    );

    // Add product data for single product pages (avoids loading finder JSON).
    if ( is_singular( 'products' ) ) {
        $product_data = erh_get_inline_product_data( get_the_ID() );
        if ( $product_data ) {
            $erh_data['productData'] = $product_data;
        }
    }

    // Localize script with data the JS might need.
    wp_localize_script( 'erh-app', 'erhData', $erh_data );

    // Add erhConfig with IPInfo token for geo detection.
    $hft_settings = get_option( 'hft_settings', array() );
    $ipinfo_token = $hft_settings['ipinfo_api_token'] ?? '';
    if ( ! empty( $ipinfo_token ) ) {
        wp_add_inline_script(
            'erh-app',
            'window.erhConfig = window.erhConfig || {}; window.erhConfig.ipinfoToken = ' . wp_json_encode( $ipinfo_token ) . ';',
            'before'
        );
    }

    // OAuth popup handler: If we're in a popup and user is logged in, notify parent
    if ( is_user_logged_in() && class_exists( '\ERH\User\UserRepository' ) ) {
        $user_repo = new \ERH\User\UserRepository();
        $needs_onboarding = ! $user_repo->has_preferences_set( get_current_user_id() );

        wp_add_inline_script(
            'erh-app',
            'if (window.opener && !window.opener.closed) {
                window.opener.postMessage({
                    type: "auth-success",
                    needsOnboarding: ' . ( $needs_onboarding ? 'true' : 'false' ) . '
                }, window.location.origin);
                window.close();
            }',
            'after'
        );
    }

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
