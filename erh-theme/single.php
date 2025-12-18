<?php
/**
 * Single Post Template
 *
 * Handles all single posts. Routes to review template for posts with "review" tag.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

while ( have_posts() ) :
    the_post();

    // Check if this post has the "review" tag (check multiple variations)
    $is_review = has_tag( 'review' ) || has_tag( 'Review' );

    // Also check by getting all tags and looking for 'review' in slug or name
    if ( ! $is_review ) {
        $tags = get_the_tags();
        if ( $tags ) {
            foreach ( $tags as $tag ) {
                if ( strtolower( $tag->slug ) === 'review' || strtolower( $tag->name ) === 'review' ) {
                    $is_review = true;
                    break;
                }
            }
        }
    }

    if ( $is_review ) {
        // Use review template
        get_template_part( 'template-parts/single', 'review' );
    } else {
        // Use standard post template
        get_template_part( 'template-parts/single', 'post' );
    }

endwhile;

get_footer();
