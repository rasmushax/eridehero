<?php
/**
 * Winner Badge Component
 *
 * Renders a purple check badge to indicate winning spec values.
 *
 * SYNC: Must match assets/js/components/compare/renderers.js:renderWinnerBadge()
 *
 * @package ERideHero
 *
 * @param string $class Additional CSS classes.
 * @return string HTML output.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a winner badge (check icon in colored circle).
 *
 * @param string $class Additional CSS classes (e.g., 'compare-spec-badge' or 'compare-feature-badge').
 * @return string HTML output.
 */
function erh_winner_badge( string $class = 'compare-spec-badge' ): string {
    return '<span class="' . esc_attr( $class ) . '">' . erh_icon( 'check' ) . '</span>';
}

/**
 * Echo a winner badge.
 *
 * @param string $class Additional CSS classes.
 */
function erh_the_winner_badge( string $class = 'compare-spec-badge' ): void {
    echo erh_winner_badge( $class );
}

/**
 * Render a feature badge (yes/no indicator).
 *
 * @param bool   $is_yes Whether the feature is present/true.
 * @param string $class  Additional CSS classes.
 * @return string HTML output.
 */
function erh_feature_badge( bool $is_yes, string $class = '' ): string {
    $icon       = $is_yes ? 'check' : 'x';
    $base_class = $is_yes ? 'compare-feature-badge feature-yes' : 'compare-feature-badge feature-no';
    $full_class = $class ? $base_class . ' ' . esc_attr( $class ) : $base_class;

    return '<span class="' . esc_attr( $full_class ) . '">' . erh_icon( $icon ) . '</span>';
}

/**
 * Echo a feature badge.
 *
 * @param bool   $is_yes Whether the feature is present/true.
 * @param string $class  Additional CSS classes.
 */
function erh_the_feature_badge( bool $is_yes, string $class = '' ): void {
    echo erh_feature_badge( $is_yes, $class );
}
