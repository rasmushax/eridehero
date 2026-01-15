<?php
/**
 * Score Helper Functions
 *
 * Rating labels, score formatting, and product score retrieval.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get score label from numeric rating
 *
 * @param float $score The score value (0-10)
 * @return string The label (e.g., 'Excellent', 'Great', 'Good')
 */
function erh_get_score_label( float $score ): string {
    if ( $score >= 9.0 ) {
        return 'Excellent';
    } elseif ( $score >= 8.0 ) {
        return 'Great';
    } elseif ( $score >= 7.0 ) {
        return 'Good';
    } elseif ( $score >= 6.0 ) {
        return 'Average';
    } else {
        return 'Poor';
    }
}

/**
 * Get score data attribute from numeric rating
 *
 * Used for CSS styling variants.
 *
 * @param float $score The score value (0-10)
 * @return string The data attribute value
 */
function erh_get_score_attr( float $score ): string {
    if ( $score >= 9.0 ) {
        return 'excellent';
    } elseif ( $score >= 8.0 ) {
        return 'great';
    } elseif ( $score >= 7.0 ) {
        return 'good';
    } elseif ( $score >= 6.0 ) {
        return 'average';
    } else {
        return 'poor';
    }
}

/**
 * Get product score from editor rating
 *
 * Returns editor rating (scaled to 0-100) or null if not available.
 * This is used as the main score badge on product pages.
 *
 * @param int $product_id Product ID.
 * @return int|null Score 0-100 or null.
 */
function erh_get_product_score( int $product_id ): ?int {
    $rating = get_field( 'editor_rating', $product_id );

    if ( empty( $rating ) ) {
        return null;
    }

    // Editor rating is 0-10, scale to 0-100.
    return (int) round( (float) $rating * 10 );
}

/**
 * Get score label for 0-100 scale score
 *
 * Maps numeric score to human-readable label.
 *
 * @param int $score Score 0-100.
 * @return string Label like 'Excellent', 'Great', etc.
 */
function erh_get_score_label_100( int $score ): string {
    if ( $score >= 90 ) {
        return 'Excellent';
    } elseif ( $score >= 80 ) {
        return 'Great';
    } elseif ( $score >= 70 ) {
        return 'Good';
    } elseif ( $score >= 60 ) {
        return 'Average';
    } else {
        return 'Below Average';
    }
}
