<?php
/**
 * Template Functions
 *
 * Helper functions for use in templates.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get SVG icon from sprite
 *
 * @param string $icon Icon name (without 'icon-' prefix)
 * @param string $class Additional CSS classes
 * @param array  $attrs Additional attributes
 * @return string SVG use element
 */
function erh_icon( string $icon, string $class = '', array $attrs = array() ): string {
    $class = trim( 'icon ' . $class );

    $attr_string = '';
    foreach ( $attrs as $key => $value ) {
        $attr_string .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
    }

    return sprintf(
        '<svg class="%s"%s><use href="%s/assets/icons/sprite.svg#icon-%s"></use></svg>',
        esc_attr( $class ),
        $attr_string,
        esc_url( ERH_THEME_URI ),
        esc_attr( $icon )
    );
}

/**
 * Echo SVG icon
 */
function erh_the_icon( string $icon, string $class = '', array $attrs = array() ): void {
    echo erh_icon( $icon, $class, $attrs );
}

/**
 * Get the site logo or site name
 *
 * @param string $class Additional CSS classes for the logo
 * @return string Logo HTML
 */
function erh_get_logo( string $class = '' ): string {
    $custom_logo_id = get_theme_mod( 'custom_logo' );

    if ( $custom_logo_id ) {
        $logo = wp_get_attachment_image( $custom_logo_id, 'full', false, array(
            'class' => 'site-logo ' . $class,
            'alt'   => get_bloginfo( 'name' ),
        ) );
    } else {
        // Fallback to SVG logo file
        $logo_path = ERH_THEME_DIR . '/assets/images/logo.svg';
        if ( file_exists( $logo_path ) ) {
            $logo = sprintf(
                '<img src="%s" alt="%s" class="site-logo %s">',
                esc_url( ERH_THEME_URI . '/assets/images/logo.svg' ),
                esc_attr( get_bloginfo( 'name' ) ),
                esc_attr( $class )
            );
        } else {
            // Text fallback
            $logo = sprintf(
                '<span class="site-name %s">%s</span>',
                esc_attr( $class ),
                esc_html( get_bloginfo( 'name' ) )
            );
        }
    }

    return $logo;
}

/**
 * Get product type label
 *
 * @param int|null $post_id Post ID (optional, uses current post if null)
 * @return string Product type label
 */
function erh_get_product_type( ?int $post_id = null ): string {
    $post_id = $post_id ?? get_the_ID();
    $type = get_field( 'product_type', $post_id );

    return $type ? $type : '';
}

/**
 * Get product type slug for URLs and classes
 *
 * @param string $type Product type label
 * @return string Slugified type
 */
function erh_product_type_slug( string $type ): string {
    $slugs = array(
        'Electric Scooter'    => 'e-scooters',
        'Electric Bike'       => 'e-bikes',
        'Electric Skateboard' => 'e-skateboards',
        'Electric Unicycle'   => 'eucs',
        'Hoverboard'          => 'hoverboards',
    );

    return $slugs[ $type ] ?? sanitize_title( $type );
}

/**
 * Format price for display
 *
 * @param float  $price Price value
 * @param string $currency Currency code
 * @return string Formatted price
 */
function erh_format_price( float $price, string $currency = 'USD' ): string {
    $symbols = array(
        'USD' => '$',
        'GBP' => '£',
        'EUR' => '€',
    );

    $symbol = $symbols[ $currency ] ?? '$';

    return $symbol . number_format( $price, 0 );
}

/**
 * Split price into whole and decimal parts
 *
 * @param float $price Price value
 * @return array ['whole' => string, 'decimal' => string]
 */
function erh_split_price( float $price ): array {
    $whole = floor( $price );
    $decimal = round( ( $price - $whole ) * 100 );

    return array(
        'whole'   => number_format( $whole, 0 ),
        'decimal' => str_pad( (string) $decimal, 2, '0', STR_PAD_LEFT ),
    );
}

/**
 * Get time elapsed string (e.g., "2 hours ago")
 *
 * @param string $datetime DateTime string
 * @return string Human-readable time difference
 */
function erh_time_elapsed( string $datetime ): string {
    $time = strtotime( $datetime );

    if ( ! $time ) {
        return '';
    }

    return human_time_diff( $time, current_time( 'timestamp' ) ) . ' ago';
}

/**
 * Truncate text with "show more" support
 *
 * @param string $text Text to truncate
 * @param int    $length Max length
 * @param bool   $show_more Whether to add show more functionality
 * @return string Truncated text with optional show more
 */
function erh_truncate_text( string $text, int $length = 150, bool $show_more = true ): string {
    if ( strlen( $text ) <= $length ) {
        return $text;
    }

    $truncated = substr( $text, 0, $length );
    $truncated = substr( $truncated, 0, strrpos( $truncated, ' ' ) );

    if ( $show_more ) {
        return sprintf(
            '<span class="text-truncated">%s</span><span class="text-full" hidden>%s</span><button type="button" class="show-more-btn">Show more</button>',
            esc_html( $truncated . '...' ),
            esc_html( $text )
        );
    }

    return $truncated . '...';
}

/**
 * Get breadcrumb items for current page
 *
 * @return array Array of breadcrumb items with 'label' and 'url' keys
 */
function erh_get_breadcrumbs(): array {
    $breadcrumbs = array();

    // Home
    $breadcrumbs[] = array(
        'label' => 'Home',
        'url'   => home_url( '/' ),
    );

    if ( is_singular( 'products' ) ) {
        $type = erh_get_product_type();
        if ( $type ) {
            $slug = erh_product_type_slug( $type );
            $breadcrumbs[] = array(
                'label' => $type . ' Reviews',
                'url'   => home_url( '/' . $slug . '-reviews/' ),
            );
        }
        $breadcrumbs[] = array(
            'label' => get_the_title(),
            'url'   => '',
        );
    } elseif ( is_singular( 'post' ) ) {
        $breadcrumbs[] = array(
            'label' => 'Articles',
            'url'   => home_url( '/articles/' ),
        );
        $breadcrumbs[] = array(
            'label' => get_the_title(),
            'url'   => '',
        );
    } elseif ( is_page() ) {
        $breadcrumbs[] = array(
            'label' => get_the_title(),
            'url'   => '',
        );
    } elseif ( is_archive() ) {
        $breadcrumbs[] = array(
            'label' => get_the_archive_title(),
            'url'   => '',
        );
    }

    return $breadcrumbs;
}

/**
 * Render breadcrumbs
 */
function erh_the_breadcrumbs(): void {
    $items = erh_get_breadcrumbs();

    if ( empty( $items ) ) {
        return;
    }

    echo '<nav class="breadcrumb" aria-label="Breadcrumb">';
    echo '<ol class="breadcrumb-list">';

    $count = count( $items );
    foreach ( $items as $index => $item ) {
        $is_last = ( $index === $count - 1 );

        echo '<li class="breadcrumb-item">';

        if ( ! $is_last && ! empty( $item['url'] ) ) {
            printf(
                '<a href="%s" class="breadcrumb-link">%s</a>',
                esc_url( $item['url'] ),
                esc_html( $item['label'] )
            );
        } else {
            printf(
                '<span class="breadcrumb-current" aria-current="page">%s</span>',
                esc_html( $item['label'] )
            );
        }

        if ( ! $is_last ) {
            echo erh_icon( 'chevron-right', 'breadcrumb-separator' );
        }

        echo '</li>';
    }

    echo '</ol>';
    echo '</nav>';
}

/**
 * Check if ERH Core plugin is active
 *
 * @return bool
 */
function erh_is_core_active(): bool {
    return defined( 'ERH_CORE_VERSION' ) && class_exists( 'ERH_Core' );
}

/**
 * Get template part with data
 *
 * Like get_template_part() but allows passing data to the template.
 *
 * @param string $slug Template slug
 * @param string $name Template name (optional)
 * @param array  $data Data to pass to template
 */
function erh_get_template_part( string $slug, string $name = '', array $data = array() ): void {
    // Make data available in the template
    if ( ! empty( $data ) ) {
        extract( $data, EXTR_SKIP );
    }

    // Build template path
    $templates = array();
    if ( $name ) {
        $templates[] = "{$slug}-{$name}.php";
    }
    $templates[] = "{$slug}.php";

    // Locate and include template
    $located = locate_template( $templates, false, false );

    if ( $located ) {
        include $located;
    }
}
