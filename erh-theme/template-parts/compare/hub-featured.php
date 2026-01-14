<?php
/**
 * Compare Hub Featured Section
 *
 * Shows featured curated comparisons (is_featured = true).
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

// Query featured curated comparisons.
$featured_comparisons = new WP_Query( [
    'post_type'      => 'comparison',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'meta_query'     => [
        [
            'key'     => 'is_featured',
            'value'   => '1',
            'compare' => '=',
        ],
    ],
] );

if ( ! $featured_comparisons->have_posts() ) {
    return;
}
?>

<section class="compare-hub-featured">
    <div class="container">
        <div class="compare-hub-section-header">
            <h2 class="compare-hub-section-title">Editor's Picks</h2>
        </div>

        <div class="compare-featured-grid">
            <?php
            while ( $featured_comparisons->have_posts() ) :
                $featured_comparisons->the_post();

                // Get product IDs (ACF relationship returns array even with max=1).
                $product_1_raw = get_field( 'product_1' );
                $product_2_raw = get_field( 'product_2' );
                $product_1_id  = is_array( $product_1_raw ) ? ( $product_1_raw[0] ?? null ) : $product_1_raw;
                $product_2_id  = is_array( $product_2_raw ) ? ( $product_2_raw[0] ?? null ) : $product_2_raw;

                if ( ! $product_1_id || ! $product_2_id ) {
                    continue;
                }

                $product_1_name  = get_the_title( $product_1_id );
                $product_2_name  = get_the_title( $product_2_id );
                $product_1_thumb = get_the_post_thumbnail_url( $product_1_id, 'medium' );
                $product_2_thumb = get_the_post_thumbnail_url( $product_2_id, 'medium' );
                $verdict_winner  = get_field( 'verdict_winner' );

                // Get category from product type.
                $product_type = get_field( 'product_type', $product_1_id );
                $category     = erh_get_category_from_type( $product_type );
                ?>
                <a href="<?php the_permalink(); ?>" class="compare-card">
                    <div class="compare-card-images">
                        <div class="compare-card-product<?php echo $verdict_winner === 'product_1' ? ' is-winner' : ''; ?>">
                            <?php if ( $verdict_winner === 'product_1' ) : ?>
                                <span class="compare-card-crown" title="<?php esc_attr_e( 'Winner', 'erh' ); ?>">
                                    <?php erh_the_icon( 'crown' ); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ( $product_1_thumb ) : ?>
                                <img src="<?php echo esc_url( $product_1_thumb ); ?>" alt="<?php echo esc_attr( $product_1_name ); ?>">
                            <?php else : ?>
                                <div class="compare-card-placeholder"><?php erh_the_icon( 'image' ); ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="compare-card-vs">VS</span>
                        <div class="compare-card-product<?php echo $verdict_winner === 'product_2' ? ' is-winner' : ''; ?>">
                            <?php if ( $verdict_winner === 'product_2' ) : ?>
                                <span class="compare-card-crown" title="<?php esc_attr_e( 'Winner', 'erh' ); ?>">
                                    <?php erh_the_icon( 'crown' ); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ( $product_2_thumb ) : ?>
                                <img src="<?php echo esc_url( $product_2_thumb ); ?>" alt="<?php echo esc_attr( $product_2_name ); ?>">
                            <?php else : ?>
                                <div class="compare-card-placeholder"><?php erh_the_icon( 'image' ); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="compare-card-body">
                        <h3 class="compare-card-title"><?php echo esc_html( $product_1_name ); ?> vs <?php echo esc_html( $product_2_name ); ?></h3>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
</section>

<?php wp_reset_postdata(); ?>
