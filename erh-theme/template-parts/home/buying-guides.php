<?php
/**
 * Homepage Buying Guides Section
 *
 * Displays 4 featured buying guide posts in a grid.
 * Posts are selected via ACF options page, or falls back to latest posts
 * tagged with 'buying-guide'.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get ACF options with defaults
$section_title   = get_field( 'buying_guides_title', 'option' ) ?: __( 'Buying guides', 'erh' );
$link_text       = get_field( 'buying_guides_link_text', 'option' ) ?: __( 'View all guides', 'erh' );
$link_url        = get_field( 'buying_guides_link_url', 'option' ) ?: home_url( '/buying-guides/' );
$selected_posts  = get_field( 'buying_guides_posts', 'option' );

// Build query args
if ( ! empty( $selected_posts ) ) {
    // Use manually selected posts
    $query_args = array(
        'post_type'      => 'post',
        'post__in'       => $selected_posts,
        'orderby'        => 'post__in',
        'posts_per_page' => 4,
        'post_status'    => 'publish',
    );
} else {
    // Fallback: latest posts with 'buying-guide' tag
    $query_args = array(
        'post_type'      => 'post',
        'tag'            => 'buying-guide',
        'posts_per_page' => 4,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
}

$guides_query = new WP_Query( $query_args );
?>

<section class="section buying-guides">
    <div class="container">
        <div class="section-header">
            <h2><?php echo esc_html( $section_title ); ?></h2>
            <a href="<?php echo esc_url( $link_url ); ?>" class="btn btn-secondary">
                <?php echo esc_html( $link_text ); ?>
                <?php erh_the_icon( 'arrow-right' ); ?>
            </a>
        </div>

        <?php if ( $guides_query->have_posts() ) : ?>
            <div class="grid-4">
                <?php while ( $guides_query->have_posts() ) : $guides_query->the_post(); ?>
                    <a href="<?php the_permalink(); ?>" class="content-card guide-card">
                        <div class="content-card-img">
                            <?php if ( has_post_thumbnail() ) : ?>
                                <?php the_post_thumbnail( 'medium_large', array( 'alt' => get_the_title() ) ); ?>
                            <?php else : ?>
                                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholder-guide.jpg' ); ?>" alt="<?php the_title_attribute(); ?>">
                            <?php endif; ?>
                        </div>
                        <h3 class="content-card-title guide-card-title"><?php the_title(); ?></h3>
                    </a>
                <?php endwhile; ?>
            </div>
            <?php wp_reset_postdata(); ?>
        <?php else : ?>
            <div class="empty-state">
                <p><?php esc_html_e( 'No buying guides found. Add posts with the "buying-guide" tag or select guides in Theme Settings > Homepage.', 'erh' ); ?></p>
            </div>
        <?php endif; ?>
    </div>
</section>
