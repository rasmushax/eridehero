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

    // Check if we have split production builds.
    $dist_base = ERH_THEME_DIR . '/assets/css/dist/base.min.css';
    $use_split = file_exists( $dist_base );

    // Fallback: check for legacy single bundle.
    $dist_css  = ERH_THEME_DIR . '/assets/css/dist/style.min.css';
    $use_dist  = ! $use_split && file_exists( $dist_css );

    // =========================================
    // STYLES
    // =========================================

    // Self-hosted Figtree font.
    wp_enqueue_style(
        'erh-fonts',
        ERH_THEME_URI . '/assets/fonts/figtree.css',
        array(),
        $version
    );

    if ( $use_split ) {
        // Production: split bundles.
        wp_enqueue_style(
            'erh-base',
            ERH_THEME_URI . '/assets/css/dist/base.min.css',
            array( 'erh-fonts' ),
            filemtime( $dist_base )
        );

        // Page-specific bundle.
        $page_bundle = erh_get_page_css_bundle();
        if ( $page_bundle ) {
            $bundle_path = ERH_THEME_DIR . '/assets/css/dist/' . $page_bundle;
            if ( file_exists( $bundle_path ) ) {
                wp_enqueue_style(
                    'erh-page',
                    ERH_THEME_URI . '/assets/css/dist/' . $page_bundle,
                    array( 'erh-base' ),
                    filemtime( $bundle_path )
                );
            }
        }
    } elseif ( $use_dist ) {
        // Fallback: legacy single bundle.
        wp_enqueue_style(
            'erh-style',
            ERH_THEME_URI . '/assets/css/dist/style.min.css',
            array( 'erh-fonts' ),
            filemtime( $dist_css )
        );
    } else {
        // Development: load all-in-one source file.
        $css_dir   = ERH_THEME_DIR . '/assets/css';
        $css_mtime = max( array_map( 'filemtime', glob( $css_dir . '/*.css' ) ) );

        wp_enqueue_style(
            'erh-style',
            ERH_THEME_URI . '/assets/css/style.css',
            array( 'erh-fonts' ),
            $css_mtime
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

        // Defer execution so page-specific data injected after get_footer()
        // (e.g. finderProducts, compareConfig) is available when the bundle runs.
        // ES modules are implicitly deferred; IIFE bundles need this explicitly.
        add_filter( 'script_loader_tag', 'erh_add_defer_attr', 10, 3 );
    } else {
        // Development: Use ES modules (filemtime for cache busting)
        wp_enqueue_script(
            'erh-app',
            ERH_THEME_URI . '/assets/js/app.js',
            array(),
            filemtime( ERH_THEME_DIR . '/assets/js/app.js' ),
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

    // Add centralized tooltips (single source of truth for JS).
    if ( class_exists( 'ERH\Config\SpecConfig' ) ) {
        $erh_data['tooltips'] = \ERH\Config\SpecConfig::export_tooltips();
    }

    // Add user data for logged-in users (geo preference, email preferences).
    if ( is_user_logged_in() && class_exists( '\ERH\User\UserRepository' ) ) {
        $user_repo = new \ERH\User\UserRepository();
        $user_id   = get_current_user_id();

        // Ensure geo preference is set (handles imported users on first visit).
        $user_geo = $user_repo->ensure_geo_preference( $user_id );

        // Get email preferences for confirmation logic (e.g., deals digest confirmation).
        $prefs = $user_repo->get_preferences( $user_id );

        $erh_data['user'] = array(
            'id'          => $user_id,
            'geo'         => $user_geo,
            'preferences' => array(
                'sales_roundup_emails' => (bool) ( $prefs['sales_roundup_emails'] ?? false ),
            ),
        );
    }

    // Add product data for single product pages (avoids loading finder JSON).
    if ( is_singular( 'products' ) ) {
        $product_data = erh_get_inline_product_data( get_the_ID() );
        if ( $product_data ) {
            $erh_data['productData'] = $product_data;
        }
    }

    // Localize script with data the JS might need.
    wp_localize_script( 'erh-app', 'erhData', $erh_data );

    // IPInfo token is now handled server-side via /erh/v1/geo endpoint (class-rest-geo.php).

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
 * Add type="module" to ES module scripts (development)
 */
function erh_add_module_type( string $tag, string $handle, string $src ): string {
    if ( 'erh-app' === $handle ) {
        $tag = str_replace( '<script ', '<script type="module" ', $tag );
    }

    return $tag;
}

/**
 * Add defer to IIFE bundle (production)
 *
 * Matches the implicit defer behavior of ES modules so that page-specific
 * data injected after get_footer() is available when the script executes.
 */
function erh_add_defer_attr( string $tag, string $handle, string $src ): string {
    if ( 'erh-app' === $handle ) {
        if ( strpos( $tag, 'defer' ) === false ) {
            $tag = str_replace( '<script ', '<script defer ', $tag );
        }
        // Prevent LiteSpeed from combining/deferring/rewriting this script.
        $tag = str_replace( '<script ', '<script data-no-optimize="1" ', $tag );
    }

    return $tag;
}

/**
 * Preload critical fonts
 */
function erh_preload_fonts(): void {
    $font_path = ERH_THEME_URI . '/assets/fonts/';
    ?>
    <link rel="preload" href="<?php echo esc_url( $font_path . 'Figtree-VariableFont_wght.ttf' ); ?>" as="font" type="font/ttf" crossorigin>
    <?php
}
add_action( 'wp_head', 'erh_preload_fonts', 1 );

/**
 * Preload LCP hero image on singular pages.
 *
 * Outputs a <link rel="preload"> so the browser fetches the hero image
 * in parallel with render-blocking CSS instead of waiting to discover
 * the <img> tag in the body.
 */
function erh_preload_lcp_image(): void {
    if ( ! is_singular() ) {
        return;
    }

    $image_id = null;
    $size     = 'large';

    if ( is_singular( 'products' ) ) {
        // Product hero: featured image → big_thumbnail ACF fallback.
        $image_id = get_post_thumbnail_id();
        if ( ! $image_id ) {
            $image_id = get_field( 'big_thumbnail', get_the_ID() );
        }
        // erh-product-lg isn't a registered size; WP falls back to full.
        $size = 'full';
    } elseif ( is_singular( array( 'post', 'page' ) ) && has_post_thumbnail() ) {
        // Articles, guides, generic pages: featured image at 'large'.
        $image_id = get_post_thumbnail_id();
        $size     = 'large';
    }

    if ( ! $image_id ) {
        return;
    }

    $src    = wp_get_attachment_image_url( $image_id, $size );
    $srcset = wp_get_attachment_image_srcset( $image_id, $size );
    $sizes  = wp_get_attachment_image_sizes( $image_id, $size );

    if ( ! $src ) {
        return;
    }

    echo '<link rel="preload" as="image" href="' . esc_url( $src ) . '"';
    if ( $srcset ) {
        echo ' imagesrcset="' . esc_attr( $srcset ) . '"';
    }
    if ( $sizes ) {
        echo ' imagesizes="' . esc_attr( $sizes ) . '"';
    }
    echo ">\n";
}
add_action( 'wp_head', 'erh_preload_lcp_image', 2 );

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

/**
 * Determine which page-specific CSS bundle to load.
 *
 * @return string|null Bundle filename (e.g. 'product.min.css') or null for base-only pages.
 */
function erh_get_page_css_bundle(): ?string {
    // Homepage.
    if ( is_front_page() ) {
        return 'home.min.css';
    }

    // Single product (CPT).
    if ( is_singular( 'products' ) ) {
        return 'product.min.css';
    }

    // Single post (reviews, guides, articles).
    if ( is_singular( 'post' ) ) {
        if ( has_tag( 'review' ) ) {
            return 'product.min.css';
        }
        return 'archive.min.css';
    }

    // Single tool.
    if ( is_singular( 'tool' ) ) {
        return 'tools.min.css';
    }

    // Curated comparison CPT.
    if ( is_singular( 'comparison' ) ) {
        return 'compare.min.css';
    }

    // Virtual routes (compare, coupons, custom archives).
    // These use template_include filters and bypass is_page_template().
    if ( function_exists( 'erh_is_compare_page' ) && erh_is_compare_page() ) {
        return 'compare.min.css';
    }

    if ( function_exists( 'erh_is_coupon_page' ) && erh_is_coupon_page() ) {
        return 'product.min.css';
    }

    if ( function_exists( 'erh_is_custom_archive' ) && erh_is_custom_archive() ) {
        return 'archive.min.css';
    }

    // Page templates (explicitly assigned in WP admin → is_page_template works).
    if ( is_page_template( 'page-finder.php' ) ) {
        return 'finder.min.css';
    }

    if ( is_page_template( 'page-deals.php' ) || is_page_template( 'page-deals-category.php' ) ) {
        return 'deals.min.css';
    }

    if ( is_page_template( 'page-search.php' )
        || is_page_template( 'page-articles.php' )
        || is_page_template( 'page-buying-guides.php' )
        || is_page_template( 'page-reviews.php' )
    ) {
        return 'archive.min.css';
    }

    if ( is_page_template( 'page-about.php' ) ) {
        return 'home.min.css';
    }

    // Slug-based pages (use page-{slug}.php without explicit WP admin assignment).
    // is_page_template() does NOT work for these — use is_page(slug) instead.
    if ( is_page( 'contact' ) ) {
        return 'account.min.css';
    }

    if ( is_page( array( 'account', 'my-account', 'login', 'email-preferences', 'complete-profile', 'reset-password' ) ) ) {
        return 'account.min.css';
    }

    // Category archives (hub pages like /electric-scooters/).
    if ( is_category() ) {
        return 'home.min.css';
    }

    // Tools archive (/tools/).
    if ( is_post_type_archive( 'tool' ) ) {
        return 'tools.min.css';
    }

    // Author archives.
    if ( is_author() ) {
        return 'archive.min.css';
    }

    // Deals category pages (children of /deals/ page, may not have template explicitly assigned).
    if ( is_page() ) {
        $current = get_queried_object();
        if ( $current && $current->post_parent ) {
            $parent = get_post( $current->post_parent );
            if ( $parent && 'deals' === $parent->post_name ) {
                return 'deals.min.css';
            }
        }
    }

    // Generic pages (editorial, privacy, how-we-test, etc.).
    if ( is_page() ) {
        return 'archive.min.css';
    }

    // 404, index — base only.
    return null;
}
