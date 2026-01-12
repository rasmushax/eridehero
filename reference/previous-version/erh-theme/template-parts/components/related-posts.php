<?php
/**
 * Related Posts Component
 *
 * Displays related articles or guides based on shared categories/tags.
 * Flexible component for both articles and buying guides.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'title'        => string - Section title (default: 'Related Articles')
 *   'post_type'    => string - 'article' or 'buying-guide' (default: same as current)
 *   'count'        => int    - Number of posts to show (default: 3)
 *   'exclude_tags' => array  - Tag slugs to exclude from matching (e.g., ['review'])
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_post_id = get_the_ID();
$title           = $args['title'] ?? __( 'Related Articles', 'erh' );
$count           = $args['count'] ?? 3;
$exclude_tags    = $args['exclude_tags'] ?? array( 'review' );
$post_type_filter = $args['post_type'] ?? null;

// Get current post's categories and tags.
$categories = wp_get_post_categories( $current_post_id, array( 'fields' => 'ids' ) );
$tags       = wp_get_post_tags( $current_post_id, array( 'fields' => 'ids' ) );

// Build query args.
$query_args = array(
    'post_type'      => 'post',
    'posts_per_page' => $count,
    'post__not_in'   => array( $current_post_id ),
    'orderby'        => 'date',
    'order'          => 'DESC',
);

// If filtering by article or guide, use tag_slug__in / tag_slug__not_in.
if ( $post_type_filter === 'article' ) {
    // Articles = posts without review or buying-guide tags.
    $query_args['tag_slug__not_in'] = array( 'review', 'buying-guide' );
} elseif ( $post_type_filter === 'buying-guide' ) {
    // Guides = posts with buying-guide tag.
    $query_args['tag_slug__in'] = array( 'buying-guide' );
} else {
    // Default: exclude reviews.
    $query_args['tag_slug__not_in'] = array( 'review' );
}

// Prioritize same category.
if ( ! empty( $categories ) ) {
    $query_args['category__in'] = $categories;
}

// Run query.
$related_query = new WP_Query( $query_args );

// If not enough results with category filter, try without.
if ( $related_query->post_count < $count && ! empty( $categories ) ) {
    $fallback_args = $query_args;
    unset( $fallback_args['category__in'] );
    $fallback_args['posts_per_page'] = $count - $related_query->post_count;
    $fallback_args['post__not_in']   = array_merge(
        array( $current_post_id ),
        wp_list_pluck( $related_query->posts, 'ID' )
    );

    $fallback_query = new WP_Query( $fallback_args );
    $related_query->posts = array_merge( $related_query->posts, $fallback_query->posts );
    $related_query->post_count = count( $related_query->posts );
}

// Bail if no results.
if ( ! $related_query->have_posts() ) {
    return;
}
?>

<section class="related-posts">
    <div class="container">
        <h2 class="related-posts-title"><?php echo esc_html( $title ); ?></h2>

        <div class="related-posts-grid">
            <?php
            while ( $related_query->have_posts() ) :
                $related_query->the_post();
                ?>
                <article class="related-post-card">
                    <a href="<?php the_permalink(); ?>" class="related-post-card-link">
                        <?php if ( has_post_thumbnail() ) : ?>
                            <div class="related-post-card-image">
                                <?php the_post_thumbnail( 'medium', array( 'class' => 'related-post-card-img' ) ); ?>
                            </div>
                        <?php endif; ?>

                        <div class="related-post-card-content">
                            <?php
                            $post_categories = get_the_category();
                            if ( ! empty( $post_categories ) ) :
                                $category = $post_categories[0];
                                ?>
                                <span class="related-post-card-category"><?php echo esc_html( $category->name ); ?></span>
                            <?php endif; ?>

                            <h3 class="related-post-card-title"><?php the_title(); ?></h3>

                            <time class="related-post-card-date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                                <?php echo esc_html( get_the_date() ); ?>
                            </time>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<?php
wp_reset_postdata();
