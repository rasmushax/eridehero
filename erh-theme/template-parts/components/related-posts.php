<?php
/**
 * Related Posts Component
 *
 * Displays related articles or guides based on shared categories/tags.
 * Uses same card layout as related-reviews for visual consistency.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'title'        => string - Section title (default: 'Related Articles')
 *   'post_type'    => string - 'article' or 'buying-guide' (default: same as current)
 *   'count'        => int    - Number of posts to show (default: 3)
 *   'view_all_url' => string - URL for "View all" button (optional)
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_post_id  = get_the_ID();
$title            = $args['title'] ?? __( 'Related Articles', 'erh' );
$count            = $args['count'] ?? 3;
$post_type_filter = $args['post_type'] ?? null;
$view_all_url     = $args['view_all_url'] ?? '';

// Build query args.
$query_args = array(
    'post_type'      => 'post',
    'posts_per_page' => $count,
    'post__not_in'   => array( $current_post_id ),
    'orderby'        => 'modified',
    'order'          => 'DESC',
);

// Filter by article or guide tag.
if ( $post_type_filter === 'article' ) {
    $query_args['tag_slug__not_in'] = array( 'review', 'buying-guide' );
} elseif ( $post_type_filter === 'buying-guide' ) {
    $query_args['tag_slug__in'] = array( 'buying-guide' );
} else {
    $query_args['tag_slug__not_in'] = array( 'review' );
}

// Get current post's categories for relevance.
$categories = wp_get_post_categories( $current_post_id, array( 'fields' => 'ids' ) );

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
    $related_query->posts      = array_merge( $related_query->posts, $fallback_query->posts );
    $related_query->post_count = count( $related_query->posts );
}

// Bail if no results.
if ( ! $related_query->have_posts() ) {
    return;
}
?>

<section class="related-posts content-section">
    <div class="section-header">
        <h2 class="section-title"><?php echo esc_html( $title ); ?></h2>
        <?php if ( ! empty( $view_all_url ) ) : ?>
            <a href="<?php echo esc_url( $view_all_url ); ?>" class="btn btn-secondary btn-sm">
                View all
                <?php erh_the_icon( 'arrow-right' ); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="related-grid">
        <?php
        while ( $related_query->have_posts() ) :
            $related_query->the_post();
            ?>
            <a href="<?php the_permalink(); ?>" class="content-card">
                <div class="content-card-img">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'medium_large' ); ?>
                    <?php else : ?>
                        <img src="<?php echo esc_url( ERH_THEME_URI . '/assets/images/placeholder.jpg' ); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="content-card-content">
                    <h3 class="content-card-title"><?php the_title(); ?></h3>
                    <span class="content-card-date"><?php echo esc_html( get_the_modified_date( 'M j, Y' ) ); ?></span>
                </div>
            </a>
        <?php endwhile; ?>
    </div>
</section>

<?php
wp_reset_postdata();
