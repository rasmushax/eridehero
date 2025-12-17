<?php
/**
 * Template Name: Articles
 *
 * Archive page for all articles (news, guides, etc.).
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Pagination
$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
$posts_per_page = 12;

// Get all posts (excluding buying guides if needed)
$articles_query = new WP_Query( array(
    'post_type'      => 'post',
    'posts_per_page' => $posts_per_page,
    'paged'          => $paged,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

// Build category counts from ALL posts (not just current page)
$count_query = new WP_Query( array(
    'post_type'      => 'post',
    'posts_per_page' => -1,
    'post_status'    => 'publish',
    'fields'         => 'ids',
) );

$category_counts = array(
    'all' => $count_query->found_posts,
);

if ( $count_query->have_posts() ) {
    foreach ( $count_query->posts as $post_id ) {
        $categories = get_the_category( $post_id );
        foreach ( $categories as $cat ) {
            $slug = $cat->slug;
            if ( ! isset( $category_counts[ $slug ] ) ) {
                $category_counts[ $slug ] = 0;
            }
            $category_counts[ $slug ]++;
        }
    }
}
wp_reset_postdata();

// Get active categories sorted by count
$all_categories = get_categories( array( 'hide_empty' => true ) );
$active_categories = array();
foreach ( $all_categories as $cat ) {
    if ( isset( $category_counts[ $cat->slug ] ) && $category_counts[ $cat->slug ] > 0 ) {
        $active_categories[] = $cat;
    }
}

usort( $active_categories, function( $a, $b ) use ( $category_counts ) {
    return $category_counts[ $b->slug ] - $category_counts[ $a->slug ];
} );

/**
 * Estimate reading time for a post.
 *
 * @param int $post_id Post ID.
 * @return int Minutes to read.
 */
function erh_get_reading_time( $post_id ) {
    $content = get_post_field( 'post_content', $post_id );
    $word_count = str_word_count( strip_tags( $content ) );
    $reading_time = ceil( $word_count / 200 ); // 200 words per minute
    return max( 1, $reading_time );
}
?>

    <!-- ARCHIVE HEADER -->
    <section class="archive-header">
        <div class="container">
            <h1 class="archive-title"><?php esc_html_e( 'Articles', 'erh' ); ?></h1>
            <p class="archive-subtitle"><?php esc_html_e( 'News, guides, and insights from the world of electric mobility', 'erh' ); ?></p>

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

    <!-- ARTICLES GRID -->
    <section class="section archive-content">
        <div class="container">
            <?php if ( $articles_query->have_posts() ) : ?>
                <div class="archive-grid" data-archive-grid>
                    <?php while ( $articles_query->have_posts() ) : $articles_query->the_post();
                        $post_id = get_the_ID();
                        $categories = get_the_category( $post_id );
                        $category_slugs = array_map( function( $cat ) { return $cat->slug; }, $categories );
                        $category_slugs_str = implode( ' ', $category_slugs );

                        // Get first category for display
                        $category_name = '';
                        if ( ! empty( $categories ) ) {
                            $category_name = erh_get_category_short_name( $categories[0]->name );
                        }

                        // Get excerpt
                        $excerpt = get_the_excerpt();
                        if ( empty( $excerpt ) ) {
                            $excerpt = wp_trim_words( get_the_content(), 20, '...' );
                        }

                        // Get reading time
                        $reading_time = erh_get_reading_time( $post_id );
                    ?>
                        <a href="<?php the_permalink(); ?>" class="archive-card archive-card--article" data-category="<?php echo esc_attr( $category_slugs_str ); ?>">
                            <div class="archive-card-img">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?>
                                <?php else : ?>
                                    <img src="<?php echo esc_url( ERH_THEME_URI . '/assets/images/placeholder.jpg' ); ?>" alt="" loading="lazy">
                                <?php endif; ?>
                                <?php if ( $category_name ) : ?>
                                    <span class="archive-card-tag"><?php echo esc_html( $category_name ); ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="archive-card-title"><?php the_title(); ?></h3>
                            <p class="archive-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
                            <div class="archive-card-meta">
                                <span><?php echo esc_html( get_the_date( 'M j, Y' ) ); ?></span>
                                <span class="archive-card-meta-sep">&middot;</span>
                                <span><?php printf( esc_html__( '%d min read', 'erh' ), $reading_time ); ?></span>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>

                <!-- Empty State (hidden by default, shown via JS when filtering) -->
                <div class="archive-empty" data-archive-empty hidden>
                    <p><?php esc_html_e( 'No articles found in this category yet.', 'erh' ); ?></p>
                </div>

                <?php
                // Pagination
                $total_pages = $articles_query->max_num_pages;
                if ( $total_pages > 1 ) :
                ?>
                    <nav class="pagination" aria-label="<?php esc_attr_e( 'Pagination', 'erh' ); ?>">
                        <?php
                        $prev_disabled = ( $paged <= 1 ) ? 'aria-disabled="true"' : '';
                        $prev_href = ( $paged > 1 ) ? get_pagenum_link( $paged - 1 ) : '#';
                        ?>
                        <a href="<?php echo esc_url( $prev_href ); ?>" class="pagination-btn pagination-prev" <?php echo $prev_disabled; ?>>
                            <?php erh_the_icon( 'chevron-left', 'icon' ); ?>
                            <span><?php esc_html_e( 'Previous', 'erh' ); ?></span>
                        </a>

                        <div class="pagination-pages">
                            <?php
                            // Show page numbers with ellipsis
                            $range = 2; // Pages to show on each side of current

                            for ( $i = 1; $i <= $total_pages; $i++ ) :
                                if ( $i == 1 || $i == $total_pages || ( $i >= $paged - $range && $i <= $paged + $range ) ) :
                                    $is_current = ( $i == $paged );
                                    ?>
                                    <a href="<?php echo esc_url( get_pagenum_link( $i ) ); ?>" class="pagination-page <?php echo $is_current ? 'is-active' : ''; ?>" <?php echo $is_current ? 'aria-current="page"' : ''; ?>>
                                        <?php echo esc_html( $i ); ?>
                                    </a>
                                    <?php
                                elseif ( $i == $paged - $range - 1 || $i == $paged + $range + 1 ) :
                                    ?>
                                    <span class="pagination-ellipsis">...</span>
                                    <?php
                                endif;
                            endfor;
                            ?>
                        </div>

                        <?php
                        $next_disabled = ( $paged >= $total_pages ) ? 'aria-disabled="true"' : '';
                        $next_href = ( $paged < $total_pages ) ? get_pagenum_link( $paged + 1 ) : '#';
                        ?>
                        <a href="<?php echo esc_url( $next_href ); ?>" class="pagination-btn pagination-next" <?php echo $next_disabled; ?>>
                            <span><?php esc_html_e( 'Next', 'erh' ); ?></span>
                            <?php erh_the_icon( 'chevron-right', 'icon' ); ?>
                        </a>
                    </nav>
                <?php endif; ?>

            <?php else : ?>
                <div class="archive-empty">
                    <p><?php esc_html_e( 'No articles available yet.', 'erh' ); ?></p>
                </div>
            <?php endif; ?>
            <?php wp_reset_postdata(); ?>
        </div>
    </section>

<?php
get_footer();
