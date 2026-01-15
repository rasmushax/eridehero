<?php
/**
 * Formatting Helper Functions
 *
 * Price, time, text, and value formatting utilities.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Format price with currency symbol
 *
 * Always shows 2 decimal places for consistency
 * (e.g., $499.99, $500.00).
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
        'CAD' => 'CA$',
        'AUD' => 'A$',
    );

    $symbol = $symbols[ $currency ] ?? '$';

    // Always show 2 decimal places for consistency.
    return $symbol . number_format( $price, 2 );
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
 * Format boolean value for display
 *
 * @param mixed $value The boolean value.
 * @return string 'Yes' or 'No'.
 */
function erh_format_boolean( $value ): string {
    return $value ? 'Yes' : 'No';
}

/**
 * Format tire sizes (front and rear)
 *
 * @param mixed $front Front tire size.
 * @param mixed $rear  Rear tire size.
 * @return string Formatted tire size string.
 */
function erh_format_tire_sizes( $front, $rear ): string {
    if ( empty( $front ) && empty( $rear ) ) {
        return '';
    }

    if ( $front && $rear && $front === $rear ) {
        return $front . '"';
    }

    if ( $front && $rear ) {
        return $front . '" / ' . $rear . '"';
    }

    return ( $front ?: $rear ) . '"';
}

/**
 * Format dimension range (min to max)
 *
 * @param mixed  $min  Minimum value.
 * @param mixed  $max  Maximum value.
 * @param string $unit Unit suffix.
 * @return string Formatted range string.
 */
function erh_format_range( $min, $max, string $unit = '' ): string {
    if ( empty( $min ) && empty( $max ) ) {
        return '';
    }

    if ( $min && $max && $min !== $max ) {
        return $min . '–' . $max . $unit;
    }

    return ( $min ?: $max ) . $unit;
}

/**
 * Format dimensions (L × W × H)
 *
 * @param mixed $length Length value.
 * @param mixed $width  Width value.
 * @param mixed $height Height value.
 * @return string Formatted dimensions string.
 */
function erh_format_dimensions( $length, $width, $height ): string {
    if ( empty( $length ) && empty( $width ) && empty( $height ) ) {
        return '';
    }

    $parts = array_filter( array( $length, $width, $height ), function( $v ) {
        return ! empty( $v );
    } );

    if ( count( $parts ) < 3 ) {
        return '';
    }

    return $length . ' × ' . $width . ' × ' . $height . '"';
}

/**
 * Extract YouTube video ID from URL
 *
 * Supports multiple YouTube URL formats:
 * - https://www.youtube.com/watch?v=VIDEO_ID
 * - https://youtu.be/VIDEO_ID
 * - https://www.youtube.com/embed/VIDEO_ID
 * - https://www.youtube.com/v/VIDEO_ID
 * - Just the video ID
 *
 * @param string $url YouTube URL or video ID
 * @return string|null Video ID or null if not found
 */
function erh_extract_youtube_id( string $url ): ?string {
    if ( empty( $url ) ) {
        return null;
    }

    // Already just the ID (11 alphanumeric characters).
    if ( preg_match( '/^[a-zA-Z0-9_-]{11}$/', $url ) ) {
        return $url;
    }

    // Standard YouTube URL patterns.
    $patterns = array(
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',      // youtube.com/watch?v=
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',        // youtube.com/embed/
        '/youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',            // youtube.com/v/
        '/youtu\.be\/([a-zA-Z0-9_-]{11})/',                  // youtu.be/
        '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',       // youtube.com/shorts/
    );

    foreach ( $patterns as $pattern ) {
        if ( preg_match( $pattern, $url, $matches ) ) {
            return $matches[1];
        }
    }

    return null;
}
