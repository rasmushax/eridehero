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
        </div>
    </section>

    <!-- Tools Grid -->
    <section class="section archive-content">
        <div class="container">
            <?php if ( $tools_query->have_posts() ) : ?>
                <div class="archive-grid archive-grid--3col">
                    <?php
                    while ( $tools_query->have_posts() ) :
                        $tools_query->the_post();
                        $post_id   = get_the_ID();
                        $permalink = get_permalink();
                        $title     = get_the_title();
                        $excerpt   = get_the_excerpt();
                        $thumbnail = get_the_post_thumbnail_url( $post_id, 'medium_large' );
                    ?>
                        <a href="<?php echo esc_url( $permalink ); ?>" class="archive-card archive-card--tool">
                            <div class="archive-card-img archive-card-img--tool">
                                <?php if ( $thumbnail ) : ?>
                                    <img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
                                <?php else : ?>
                                    <div class="archive-card-icon">
                                        <?php erh_the_icon( 'calculator' ); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="archive-card-tag"><?php esc_html_e( 'Tool', 'erh' ); ?></span>
                            </div>
                            <h3 class="archive-card-title"><?php echo esc_html( $title ); ?></h3>
                            <?php if ( $excerpt ) : ?>
                                <p class="archive-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
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

    <?php
    // CTA Section.
    get_template_part( 'template-parts/sections/cta' );
    ?>

</main>

<?php get_footer(); ?>
