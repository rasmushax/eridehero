<?php
/**
 * Icon Helper Functions
 *
 * SVG icon and logo rendering utilities.
 *
 * @package ERideHero
 */

// Prevent direct access.
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

    $attr_string = ' aria-hidden="true"';
    foreach ( $attrs as $key => $value ) {
        $attr_string .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
    }

    // Use inline sprite reference (sprite is inlined in header.php via svg-sprite.php).
    return sprintf(
        '<svg class="%s"%s><use href="#icon-%s"></use></svg>',
        esc_attr( $class ),
        $attr_string,
        esc_attr( $icon )
    );
}

/**
 * Echo SVG icon
 *
 * @param string $icon Icon name (without 'icon-' prefix)
 * @param string $class Additional CSS classes
 * @param array  $attrs Additional attributes
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
        // Fallback to SVG logo file.
        $logo_path = ERH_THEME_DIR . '/assets/images/logo.svg';
        if ( file_exists( $logo_path ) ) {
            $logo = sprintf(
                '<img src="%s" alt="%s" class="site-logo %s">',
                esc_url( ERH_THEME_URI . '/assets/images/logo.svg' ),
                esc_attr( get_bloginfo( 'name' ) ),
                esc_attr( $class )
            );
        } else {
            // Text fallback.
            $logo = sprintf(
                '<span class="site-name %s">%s</span>',
                esc_attr( $class ),
                esc_html( get_bloginfo( 'name' ) )
            );
        }
    }

    return $logo;
}
