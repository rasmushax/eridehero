<?php
/**
 * Score Ring Component
 *
 * Renders a circular score indicator with progress ring.
 *
 * SYNC: Must match assets/js/components/compare/renderers.js:renderScoreRing()
 *
 * @package ERideHero
 *
 * @param int|float $score Score value (0-100).
 * @param string    $size  Size variant: 'sm' (mini-header), 'md' (header cards).
 * @param string    $class Additional CSS classes.
 * @return string HTML output.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a score ring SVG component.
 *
 * @param int|float $score Score value (0-100).
 * @param string    $size  Size variant: 'sm' | 'md'.
 * @param string    $class Additional CSS classes.
 * @return string HTML output.
 */
function erh_score_ring( $score, string $size = 'md', string $class = '' ): string {
    if ( $score === null || $score === '' ) {
        return '';
    }

    // Constants matching JS
    $radius        = 15;
    $viewbox       = 36;
    $center        = 18;
    $circumference = 2 * M_PI * $radius;

    // Clamp score to 0-100
    $score_percent = min( 100, max( 0, (float) $score ) );
    $offset        = $circumference - ( $score_percent / 100 ) * $circumference;
    $rounded_score = round( $score );

    // Size-specific class prefix
    $prefix = $size === 'sm' ? 'compare-mini-score' : 'compare-product-score';
    $extra  = $class ? ' ' . esc_attr( $class ) : '';

    return <<<HTML
<div class="{$prefix}{$extra}" title="{$rounded_score} points">
    <svg class="{$prefix}-ring" viewBox="0 0 {$viewbox} {$viewbox}">
        <circle class="{$prefix}-track" cx="{$center}" cy="{$center}" r="{$radius}" />
        <circle class="{$prefix}-progress" cx="{$center}" cy="{$center}" r="{$radius}"
                style="stroke-dasharray: {$circumference}; stroke-dashoffset: {$offset};" />
    </svg>
    <span class="{$prefix}-value">{$rounded_score}</span>
</div>
HTML;
}

/**
 * Echo a score ring.
 *
 * @param int|float $score Score value (0-100).
 * @param string    $size  Size variant.
 * @param string    $class Additional CSS classes.
 */
function erh_the_score_ring( $score, string $size = 'md', string $class = '' ): void {
    echo erh_score_ring( $score, $size, $class );
}
