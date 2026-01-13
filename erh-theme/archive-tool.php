<?php
/**
 * Tools Archive Template
 *
 * Displays a grid of all calculator tools.
 * URL: /tools/
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Query all published tools.
$tools_query = new WP_Query( [
    'post_type'      => 'tool',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'menu_order title',
    'order'          => 'ASC',
] );

$total_tools = $tools_query->found_posts;

// Build category counts from tool_category taxonomy.
$tool_categories = get_terms( [
    'taxonomy'   => \ERH\PostTypes\Tool::TAXONOMY,
    'hide_empty' => true,
] );

$category_counts   = [ 'all' => $total_tools ];
$active_categories = [];

if ( ! is_wp_error( $tool_categories ) && ! empty( $tool_categories ) ) {
    foreach ( $tool_categories as $term ) {
        $category_counts[ $term->slug ] = $term->count;
        $active_categories[]            = $term;
    }

    // Sort by count descending.
    usort( $active_categories, function( $a, $b ) {
        return $b->count - $a->count;
    } );
}
?>

<main id="main-content">

    <!-- Archive Header -->
    <section class="archive-header">
        <div class="container">
            <div class="archive-header-row">
                <div class="archive-header-text">
                    <h1 class="archive-title"><?php esc_html_e( 'Tools & Calculators', 'erh' ); ?></h1>
                    <p class="archive-subtitle"><?php esc_html_e( 'Interactive tools to help you make better decisions', 'erh' ); ?></p>
                </div>
                <div class="archive-header-controls">
                    <span class="archive-count">
                        <?php
                        /* translators: %d: number of tools */
                        printf( esc_html( _n( '%d tool', '%d tools', $total_tools, 'erh' ) ), $total_tools );
                        ?>
                    </span>
                </div>
            </div>

            <?php if ( count( $active_categories ) > 1 ) : ?>
                <?php
                get_template_part( 'template-parts/archive/filters', null, [
                    'category_counts'   => $category_counts,
                    'active_categories' => $active_categories,
                ] );
                ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Tools Grid -->
    <section class="section archive-content">
        <div class="container">
            <?php if ( $tools_query->have_posts() ) : ?>
                <div class="archive-grid archive-grid--3col" data-archive-grid>
                    <?php
                    while ( $tools_query->have_posts() ) :
                        $tools_query->the_post();
                        $post_id     = get_the_ID();
                        $permalink   = get_permalink();
                        $title       = get_the_title();
                        $description = \ERH\PostTypes\Tool::get_tool_description( $post_id );
                        $thumbnail   = get_the_post_thumbnail_url( $post_id, 'medium_large' );
                        $icon        = \ERH\PostTypes\Tool::get_tool_icon( $post_id );
                        $terms       = get_the_terms( $post_id, \ERH\PostTypes\Tool::TAXONOMY );
                        $categories  = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms : [];
                        $cat_slugs   = array_map( fn( $t ) => $t->slug, $categories );
                    ?>
                        <a href="<?php echo esc_url( $permalink ); ?>" class="archive-card archive-card--tool" data-category="<?php echo esc_attr( implode( ' ', $cat_slugs ) ); ?>">
                            <div class="archive-card-img archive-card-img--tool">
                                <?php if ( $thumbnail ) : ?>
                                    <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
                                <?php else : ?>
                                    <div class="archive-card-icon">
                                        <?php erh_the_icon( $icon ); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ( ! empty( $categories ) ) : ?>
                                    <div class="archive-card-tags">
                                        <?php foreach ( $categories as $cat ) : ?>
                                            <span class="archive-card-tag"><?php echo esc_html( $cat->name ); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h3 class="archive-card-title"><?php echo esc_html( $title ); ?></h3>
                            <?php if ( $description ) : ?>
                                <p class="archive-card-excerpt"><?php echo esc_html( $description ); ?></p>
                            <?php endif; ?>
                        </a>
                    <?php endwhile; ?>
                </div>
                <?php wp_reset_postdata(); ?>

            <?php else : ?>
                <div class="archive-empty empty-state">
                    <?php erh_the_icon( 'calculator' ); ?>
                    <h3><?php esc_html_e( 'No tools yet', 'erh' ); ?></h3>
                    <p><?php esc_html_e( 'We\'re working on building helpful tools. Check back soon!', 'erh' ); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

</main>

<?php get_footer(); ?>
