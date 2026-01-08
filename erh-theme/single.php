<?php
/**
 * Single Post Template
 *
 * Routes single posts to appropriate template based on tags:
 * - "review" tag → single-review.php (product reviews)
 * - "buying-guide" tag → single-guide.php (buying guides)
 * - else → single-article.php (articles)
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

while ( have_posts() ) :
    the_post();

    // Get all tags for this post.
    $tags = get_the_tags();
    $tag_slugs = array();

    if ( $tags ) {
        foreach ( $tags as $tag ) {
            $tag_slugs[] = strtolower( $tag->slug );
        }
    }

    // Determine post type by tag.
    $is_review = in_array( 'review', $tag_slugs, true );
    $is_guide  = in_array( 'buying-guide', $tag_slugs, true );

    if ( $is_review ) {
        // Product review template.
        get_template_part( 'template-parts/single', 'review' );
    } elseif ( $is_guide ) {
        // Buying guide template.
        get_template_part( 'template-parts/single', 'guide' );
    } else {
        // Article template (default).
        get_template_part( 'template-parts/single', 'article' );
    }

endwhile;

get_footer();
