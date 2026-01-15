<?php
/**
 * Product Thumbnail Component
 *
 * Renders product thumbnail with name for mobile spec cards.
 *
 * SYNC: Must match assets/js/components/compare/renderers.js:renderProductThumb()
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a product thumbnail with name (for mobile spec cards).
 *
 * @param array  $product Product data with 'thumbnail' and 'name' keys.
 * @param string $class   Additional CSS classes.
 * @return string HTML output.
 */
function erh_product_thumb( array $product, string $class = 'compare-spec-card-product' ): string {
    $thumbnail = $product['thumbnail'] ?? '';
    $name      = $product['name'] ?? '';

    $img = $thumbnail
        ? sprintf( '<img src="%s" alt="">', esc_url( $thumbnail ) )
        : '';

    return sprintf(
        '<span class="%s">%s%s</span>',
        esc_attr( $class ),
        $img,
        esc_html( $name )
    );
}

/**
 * Echo a product thumbnail.
 *
 * @param array  $product Product data.
 * @param string $class   Additional CSS classes.
 */
function erh_the_product_thumb( array $product, string $class = 'compare-spec-card-product' ): void {
    echo erh_product_thumb( $product, $class );
}

/**
 * Render a mobile spec card value (product thumb + data value).
 *
 * Used in mobile comparison cards where each product's value is shown with its thumbnail.
 *
 * @param array  $product   Product data with 'thumbnail' and 'name'.
 * @param mixed  $value     The spec value.
 * @param array  $spec      Spec configuration.
 * @param bool   $is_winner Whether this value is the winner.
 * @return string HTML output.
 */
function erh_mobile_spec_value( array $product, $value, array $spec, bool $is_winner = false ): string {
    $format = $spec['format'] ?? '';

    // Handle boolean values specially.
    if ( $format === 'boolean' ) {
        return erh_mobile_boolean_value( $product, $value );
    }

    $formatted = erh_format_spec_value( $value, $spec );

    if ( $is_winner ) {
        return sprintf(
            '<div class="compare-spec-card-value is-winner">
                %s
                <span class="compare-spec-card-data">
                    <span class="compare-spec-badge">%s</span>
                    <span class="compare-spec-card-text">%s</span>
                </span>
            </div>',
            erh_product_thumb( $product ),
            erh_icon( 'check' ),
            esc_html( $formatted )
        );
    }

    return sprintf(
        '<div class="compare-spec-card-value">
            %s
            <span class="compare-spec-card-data">
                <span class="compare-spec-card-text">%s</span>
            </span>
        </div>',
        erh_product_thumb( $product ),
        esc_html( $formatted )
    );
}

/**
 * Render a mobile boolean value with feature badge.
 *
 * @param array $product Product data.
 * @param mixed $value   The boolean value.
 * @return string HTML output.
 */
function erh_mobile_boolean_value( array $product, $value ): string {
    $is_yes     = $value === true || $value === 'Yes' || $value === 'yes' || $value === 1;
    $has_value  = $value !== null && $value !== '' && $value !== 0;
    $class      = $has_value ? ( $is_yes ? 'feature-yes' : 'feature-no' ) : '';
    $icon       = $is_yes ? 'check' : 'x';
    $text       = $has_value ? ( $is_yes ? 'Yes' : 'No' ) : '—';

    $badge_html = $has_value
        ? sprintf( '<span class="compare-feature-badge">%s</span>', erh_icon( $icon ) )
        : '';

    return sprintf(
        '<div class="compare-spec-card-value %s">
            %s
            <span class="compare-spec-card-data">%s%s</span>
        </div>',
        esc_attr( $class ),
        erh_product_thumb( $product ),
        $badge_html,
        esc_html( $text )
    );
}

/**
 * Echo a mobile spec value.
 *
 * @param array  $product   Product data.
 * @param mixed  $value     The spec value.
 * @param array  $spec      Spec configuration.
 * @param bool   $is_winner Whether this value is the winner.
 */
function erh_the_mobile_spec_value( array $product, $value, array $spec, bool $is_winner = false ): void {
    echo erh_mobile_spec_value( $product, $value, $spec, $is_winner );
}
