<?php
/**
 * Theme Setup
 *
 * Register theme supports, menus, and sidebars.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function erh_theme_setup(): void {
    // Add default posts and comments RSS feed links to head
    add_theme_support( 'automatic-feed-links' );

    // Let WordPress manage the document title
    add_theme_support( 'title-tag' );

    // Enable support for Post Thumbnails
    add_theme_support( 'post-thumbnails' );

    // Custom image sizes
    add_image_size( 'erh-card', 400, 267, true );          // 3:2 ratio for cards
    add_image_size( 'erh-card-large', 600, 400, true );    // 3:2 ratio larger
    add_image_size( 'erh-hero', 800, 600, true );          // 4:3 ratio for hero
    add_image_size( 'erh-gallery', 800, 533, true );       // 3:2 ratio for gallery
    add_image_size( 'erh-thumbnail', 100, 100, true );     // Square thumbnails

    // Optimized UI component sizes (2x for retina)
    add_image_size( 'erh-avatar', 80, 80, true );           // 40px avatar (byline), hard crop 1:1
    add_image_size( 'erh-product-xs', 9999, 64, false );    // 32px height (price intel)
    add_image_size( 'erh-product-sm', 9999, 80, false );    // 40px height (homepage cards)
    add_image_size( 'erh-product-md', 9999, 160, false );   // 80px height (best deals)
    add_image_size( 'erh-logo-small', 9999, 48, false );    // 24px height (retailer logos)

    // Register navigation menus
    register_nav_menus( array(
        'primary'      => __( 'Primary Menu', 'erh' ),
        'footer'       => __( 'Footer Menu', 'erh' ),
        'footer-legal' => __( 'Footer Legal Menu', 'erh' ),
    ) );

    // HTML5 support for various elements
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ) );

    // Add support for responsive embeds
    add_theme_support( 'responsive-embeds' );

    // Disable core block patterns (we'll use our own)
    remove_theme_support( 'core-block-patterns' );

    // Custom logo support
    add_theme_support( 'custom-logo', array(
        'height'      => 40,
        'width'       => 160,
        'flex-height' => true,
        'flex-width'  => true,
    ) );
}
add_action( 'after_setup_theme', 'erh_theme_setup' );

/**
 * Register widget areas / sidebars
 */
function erh_widgets_init(): void {
    register_sidebar( array(
        'name'          => __( 'Product Sidebar', 'erh' ),
        'id'            => 'sidebar-product',
        'description'   => __( 'Sidebar for single product pages.', 'erh' ),
        'before_widget' => '<div id="%1$s" class="sidebar-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="sidebar-widget-title">',
        'after_title'   => '</h3>',
    ) );

    register_sidebar( array(
        'name'          => __( 'Blog Sidebar', 'erh' ),
        'id'            => 'sidebar-blog',
        'description'   => __( 'Sidebar for blog and article pages.', 'erh' ),
        'before_widget' => '<div id="%1$s" class="sidebar-widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="sidebar-widget-title">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'widgets_init', 'erh_widgets_init' );

/**
 * Add custom body classes
 */
function erh_body_classes( array $classes ): array {
    // Add class for pages with transparent header
    if ( is_front_page() || is_page_template( 'page-about.php' ) ) {
        $classes[] = 'transparent-header';
    }

    // Add class for single products
    if ( is_singular( 'products' ) ) {
        $classes[] = 'single-product-page';
    }

    // Add class if no sidebar
    if ( ! is_active_sidebar( 'sidebar-product' ) && ! is_active_sidebar( 'sidebar-blog' ) ) {
        $classes[] = 'no-sidebar';
    }

    return $classes;
}
add_filter( 'body_class', 'erh_body_classes' );

/**
 * Modify excerpt length
 */
function erh_excerpt_length( int $length ): int {
    return 20;
}
add_filter( 'excerpt_length', 'erh_excerpt_length' );

/**
 * Modify excerpt more text
 */
function erh_excerpt_more( string $more ): string {
    return '&hellip;';
}
add_filter( 'excerpt_more', 'erh_excerpt_more' );

/**
 * Hide admin bar for regular users (subscribers).
 * Only show for editors, administrators, and above.
 */
function erh_hide_admin_bar_for_subscribers(): void {
    if ( ! current_user_can( 'edit_posts' ) ) {
        show_admin_bar( false );
    }
}
add_action( 'after_setup_theme', 'erh_hide_admin_bar_for_subscribers' );
