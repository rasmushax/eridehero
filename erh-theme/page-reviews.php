<?php
/**
 * Template Name: Reviews
 *
 * Archive page for all reviews (posts tagged with 'review').
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// SSR filter: read from query param.
$current_filter = isset( $_GET['category'] ) ? sanitize_key( $_GET['category'] ) : 'all';

// Query ALL posts tagged "review" (single query — replaces separate paginated + count queries).
$reviews_query = new WP_Query( array(
	'post_type'      => 'post',
	'tag'            => 'review',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
	'orderby'        => 'modified',
	'order'          => 'DESC',
) );

// Build category counts and collect post data in one pass.
$category_counts = array( 'all' => 0 );
$reviews_data    = array();

if ( $reviews_query->have_posts() ) {
	while ( $reviews_query->have_posts() ) {
		$reviews_query->the_post();
		$post_id    = get_the_ID();
		$categories = get_the_category( $post_id );

		// Build category slugs.
		$category_slugs_arr = array();
		foreach ( $categories as $cat ) {
			$category_slugs_arr[] = $cat->slug;
			if ( ! isset( $category_counts[ $cat->slug ] ) ) {
				$category_counts[ $cat->slug ] = 0;
			}
			$category_counts[ $cat->slug ]++;
		}

		$category_name = ! empty( $categories )
			? erh_get_category_short_name( $categories[0]->name )
			: '';

		// Get rating from linked product.
		$rating     = null;
		$product_id = get_field( 'review_product', $post_id );
		if ( $product_id ) {
			$editor_rating = get_field( 'editor_rating', $product_id );
			if ( $editor_rating ) {
				$rating = floatval( $editor_rating );
			}
		}

		$reviews_data[] = array(
			'post_id'        => $post_id,
			'category_slugs' => implode( ' ', $category_slugs_arr ),
			'category_name'  => $category_name,
			'rating'         => $rating,
		);

		$category_counts['all']++;
	}
	wp_reset_postdata();
}

// Get active categories sorted by count.
$all_categories    = get_categories( array( 'hide_empty' => true ) );
$active_categories = array();
foreach ( $all_categories as $cat ) {
	if ( isset( $category_counts[ $cat->slug ] ) && $category_counts[ $cat->slug ] > 0 ) {
		$active_categories[] = $cat;
	}
}

usort( $active_categories, function( $a, $b ) use ( $category_counts ) {
	return $category_counts[ $b->slug ] - $category_counts[ $a->slug ];
} );

// Validate that current_filter is a real category (or 'all').
if ( 'all' !== $current_filter ) {
	$valid = false;
	foreach ( $active_categories as $cat ) {
		if ( $cat->slug === $current_filter ) {
			$valid = true;
			break;
		}
	}
	if ( ! $valid ) {
		$current_filter = 'all';
	}
}
?>

<!-- Archive Header -->
<section class="archive-header">
	<div class="container">
		<h1 class="archive-title"><?php the_title(); ?></h1>
		<div class="archive-subtitle"><?php the_content(); ?></div>

		<?php
		get_template_part( 'template-parts/archive/filters', null, array(
			'category_counts'   => $category_counts,
			'active_categories' => $active_categories,
			'current_filter'    => $current_filter,
		) );
		?>
	</div>
</section>

<!-- Reviews Grid -->
<section class="section archive-content">
	<div class="container">
		<?php if ( ! empty( $reviews_data ) ) : ?>
			<div class="archive-grid" data-archive-grid data-archive-paginate="12">
				<?php
				foreach ( $reviews_data as $review ) :
					// SSR: hide cards that don't match the current filter.
					$matches_filter = ( 'all' === $current_filter )
						|| in_array( $current_filter, explode( ' ', $review['category_slugs'] ), true );

					get_template_part( 'template-parts/archive/card', null, array(
						'type'           => 'review',
						'post_id'        => $review['post_id'],
						'category_slugs' => $review['category_slugs'],
						'category_name'  => $review['category_name'],
						'rating'         => $review['rating'],
						'hidden'         => ! $matches_filter,
					) );
				endforeach;
				?>
			</div>

			<!-- Empty State (hidden by default, shown via JS when filtering) -->
			<div class="archive-empty" data-archive-empty hidden>
				<p><?php esc_html_e( 'No reviews found in this category yet.', 'erh' ); ?></p>
			</div>

			<!-- Client-side pagination container -->
			<div data-archive-pagination></div>
			<noscript>
				<p class="archive-noscript"><?php esc_html_e( 'Enable JavaScript for pagination and filtering.', 'erh' ); ?></p>
			</noscript>

		<?php else : ?>
			<div class="archive-empty">
				<p><?php esc_html_e( 'No reviews available yet.', 'erh' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
