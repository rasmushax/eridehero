<?php
/**
 * E-Scooter Reviews Archive
 *
 * Dedicated reviews archive for electric scooters.
 * URL: /electric-scooters/reviews/
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Pagination.
$paged = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

// Build query for e-scooter reviews.
$query_args = array(
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'tag'            => 'review',
	'category_name'  => 'electric-scooters',
	'posts_per_page' => 12,
	'paged'          => $paged,
	'orderby'        => 'modified',
	'order'          => 'DESC',
);

$reviews_query = new WP_Query( $query_args );
$total_reviews = $reviews_query->found_posts;

get_header();
?>

<main id="main-content">

	<!-- Archive Header -->
	<section class="archive-header">
		<div class="container">
			<div class="archive-header-row">
				<div class="archive-header-text">
					<h1 class="archive-title"><?php esc_html_e( 'Electric scooter reviews', 'erh' ); ?></h1>
					<p class="archive-subtitle"><?php esc_html_e( 'Hands-on reviews from scooters we\'ve actually tested', 'erh' ); ?></p>
				</div>
				<div class="archive-header-controls">
					<span class="archive-count">
						<?php
						/* translators: %d: number of reviews */
						printf( esc_html( _n( '%d review', '%d reviews', $total_reviews, 'erh' ) ), $total_reviews );
						?>
					</span>
					<div class="archive-sort">
						<label for="sort-select" class="sr-only"><?php esc_html_e( 'Sort by', 'erh' ); ?></label>
						<?php
						erh_custom_select( [
							'name'     => 'sort',
							'id'       => 'sort-select',
							'variant'  => 'sm',
							'selected' => 'newest',
							'options'  => [
								'rating' => __( 'Highest rated', 'erh' ),
								'newest' => __( 'Newest first', 'erh' ),
								'oldest' => __( 'Oldest first', 'erh' ),
							],
							'attrs'    => [ 'data-archive-sort' => true ],
						] );
						?>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Reviews Grid -->
	<section class="section archive-content">
		<div class="container">
			<?php if ( $reviews_query->have_posts() ) : ?>
				<div class="archive-grid archive-grid--3col" data-archive-grid>
					<?php
					while ( $reviews_query->have_posts() ) :
						$reviews_query->the_post();
						$post_id = get_the_ID();

						// Get linked product for rating.
						$product_id = get_field( 'review_product' );
						$rating     = null;

						if ( $product_id ) {
							$editor_rating = get_field( 'editor_rating', $product_id );
							if ( $editor_rating ) {
								$rating = floatval( $editor_rating );
							}
						}

						// Format date for data attribute (used by JS sort).
						$date_raw = get_the_modified_date( 'Y-m-d' );

						get_template_part( 'template-parts/archive/card', null, array(
							'type'           => 'review',
							'post_id'        => $post_id,
							'category_slugs' => 'electric-scooters',
							'category_name'  => '',
							'rating'         => $rating,
						) );
					endwhile;
					?>
				</div>

				<?php
				get_template_part( 'template-parts/archive/pagination', null, array(
					'paged'       => $paged,
					'total_pages' => $reviews_query->max_num_pages,
					'base_url'    => home_url( '/electric-scooters/reviews/' ),
				) );
				?>

				<?php wp_reset_postdata(); ?>

			<?php else : ?>
				<div class="archive-empty empty-state">
					<?php erh_the_icon( 'star' ); ?>
					<h3><?php esc_html_e( 'No reviews yet', 'erh' ); ?></h3>
					<p><?php esc_html_e( 'We\'re working on testing more electric scooters. Check back soon!', 'erh' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	</section>

</main>

<?php get_footer(); ?>
