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

    // Check if this post has the "review" tag
    if ( has_tag( 'review' ) ) {
        // Use review template
        get_template_part( 'template-parts/single', 'review' );
    } else {
        // Use standard post template
        get_template_part( 'template-parts/single', 'post' );
    }

endwhile;

get_footer();
