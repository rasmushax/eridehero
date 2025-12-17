<?php
/**
 * Template Name: Buying Guides
 *
 * Archive page for buying guide posts.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Get all posts tagged "buying-guide"
$guides_query = new WP_Query( array(
    'post_type'      => 'post',
    'tag'            => 'buying-guide',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

// Build category counts from posts
$category_counts = array(
    'all' => 0,
);
$posts_by_category = array();

if ( $guides_query->have_posts() ) {
    while ( $guides_query->have_posts() ) {
        $guides_query->the_post();
        $post_id = get_the_ID();

        // Get categories for this post
        $categories = get_the_category( $post_id );
        $post_categories = array();

        foreach ( $categories as $cat ) {
            $slug = $cat->slug;
            $post_categories[] = $slug;

            if ( ! isset( $category_counts[ $slug ] ) ) {
                $category_counts[ $slug ] = 0;
            }
            $category_counts[ $slug ]++;
        }

        // Store post data with its categories
        $posts_by_category[] = array(
            'id'         => $post_id,
            'title'      => get_field( 'buying_guide_card_title', $post_id ) ?: get_the_title(),
            'permalink'  => get_permalink(),
            'thumbnail'  => get_the_post_thumbnail_url( $post_id, 'medium_large' ),
            'categories' => $post_categories,
        );

        $category_counts['all']++;
    }
    wp_reset_postdata();
}

// Get all categories that have buying guides
$guide_categories = get_categories( array(
    'hide_empty' => false,
) );

// Filter to only categories that have guides
$active_categories = array();
foreach ( $guide_categories as $cat ) {
    if ( isset( $category_counts[ $cat->slug ] ) && $category_counts[ $cat->slug ] > 0 ) {
        $active_categories[] = $cat;
    }
}

// Sort categories by count (highest first)
usort( $active_categories, function( $a, $b ) use ( $category_counts ) {
    return $category_counts[ $b->slug ] - $category_counts[ $a->slug ];
} );
?>

    <!-- ARCHIVE HEADER -->
    <section class="archive-header">
        <div class="container">
            <h1 class="archive-title"><?php esc_html_e( 'Buying Guides', 'erh' ); ?></h1>
            <p class="archive-subtitle"><?php esc_html_e( 'Expert guides to help you find the perfect electric ride', 'erh' ); ?></p>

            <nav class="archive-filters" aria-label="<?php esc_attr_e( 'Filter by category', 'erh' ); ?>" data-archive-filters>
                <button type="button" class="archive-filter is-active" data-filter="all">
                    <?php esc_html_e( 'All', 'erh' ); ?>
                    <span class="archive-filter-count"><?php echo esc_html( $category_counts['all'] ); ?></span>
                </button>
                <?php foreach ( $active_categories as $cat ) : ?>
                    <button type="button" class="archive-filter" data-filter="<?php echo esc_attr( $cat->slug ); ?>">
                        <?php echo esc_html( erh_get_category_short_name( $cat->name ) ); ?>
                        <span class="archive-filter-count"><?php echo esc_html( $category_counts[ $cat->slug ] ); ?></span>
                    </button>
                <?php endforeach; ?>
            </nav>
        </div>
    </section>

    <!-- GUIDES GRID -->
    <section class="section archive-content">
        <div class="container">
            <?php if ( ! empty( $posts_by_category ) ) : ?>
                <div class="archive-grid" data-archive-grid>
                    <?php foreach ( $posts_by_category as $guide ) :
                        $category_slugs = implode( ' ', $guide['categories'] );
                        $first_category = ! empty( $guide['categories'] ) ? $guide['categories'][0] : '';

                        // Get category name for display (use short name)
                        $category_name = '';
                        if ( $first_category ) {
                            $cat_obj = get_category_by_slug( $first_category );
                            if ( $cat_obj ) {
                                $category_name = erh_get_category_short_name( $cat_obj->name );
                            }
                        }
                    ?>
                        <a href="<?php echo esc_url( $guide['permalink'] ); ?>" class="archive-card" data-category="<?php echo esc_attr( $category_slugs ); ?>">
                            <div class="archive-card-img">
                                <?php if ( $guide['thumbnail'] ) : ?>
                                    <img src="<?php echo esc_url( $guide['thumbnail'] ); ?>" alt="<?php echo esc_attr( $guide['title'] ); ?>" loading="lazy">
                                <?php else : ?>
                                    <img src="<?php echo esc_url( ERH_THEME_URI . '/assets/images/placeholder.jpg' ); ?>" alt="" loading="lazy">
                                <?php endif; ?>
                                <?php if ( $category_name ) : ?>
                                    <span class="archive-card-tag"><?php echo esc_html( $category_name ); ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="archive-card-title"><?php echo esc_html( $guide['title'] ); ?></h3>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Empty State (hidden by default, shown via JS) -->
                <div class="archive-empty" data-archive-empty hidden>
                    <p><?php esc_html_e( 'No guides found in this category yet.', 'erh' ); ?></p>
                </div>
            <?php else : ?>
                <div class="archive-empty">
                    <p><?php esc_html_e( 'No buying guides available yet.', 'erh' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

<?php
get_footer();
