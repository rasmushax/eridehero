<?php
/**
 * Template Name: Articles
 *
 * Archive page for all articles (news, guides, etc.).
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

// Query ALL posts tagged "article" (single query — replaces separate paginated + count queries).
$articles_query = new WP_Query( array(
	'post_type'      => 'post',
	'tag'            => 'article',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
	'orderby'        => 'modified',
	'order'          => 'DESC',
) );

// Build category counts and collect post data in one pass.
$category_counts = array( 'all' => 0 );
$articles_data   = array();

if ( $articles_query->have_posts() ) {
	while ( $articles_query->have_posts() ) {
		$articles_query->the_post();
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

		// Get excerpt.
		$excerpt = get_the_excerpt();
		if ( empty( $excerpt ) ) {
			$excerpt = wp_trim_words( get_the_content(), 20, '...' );
		}

		$articles_data[] = array(
			'post_id'        => $post_id,
			'category_slugs' => implode( ' ', $category_slugs_arr ),
			'category_name'  => $category_name,
			'excerpt'        => $excerpt,
			'reading_time'   => erh_get_reading_time( $post_id ),
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

<!-- Articles Grid -->
<section class="section archive-content">
	<div class="container">
		<?php if ( ! empty( $articles_data ) ) : ?>
			<div class="archive-grid" data-archive-grid data-archive-paginate="12">
				<?php
				foreach ( $articles_data as $article ) :
					// SSR: hide cards that don't match the current filter.
					$matches_filter = ( 'all' === $current_filter )
						|| in_array( $current_filter, explode( ' ', $article['category_slugs'] ), true );

					get_template_part( 'template-parts/archive/card', null, array(
						'type'           => 'article',
						'post_id'        => $article['post_id'],
						'category_slugs' => $article['category_slugs'],
						'category_name'  => $article['category_name'],
						'excerpt'        => $article['excerpt'],
						'reading_time'   => $article['reading_time'],
						'hidden'         => ! $matches_filter,
					) );
				endforeach;
				?>
			</div>

			<!-- Empty State (hidden by default, shown via JS when filtering) -->
			<div class="archive-empty" data-archive-empty hidden>
				<p><?php esc_html_e( 'No articles found in this category yet.', 'erh' ); ?></p>
			</div>

			<!-- Client-side pagination container -->
			<div data-archive-pagination></div>
			<noscript>
				<p class="archive-noscript"><?php esc_html_e( 'Enable JavaScript for pagination and filtering.', 'erh' ); ?></p>
			</noscript>

		<?php else : ?>
			<div class="archive-empty">
				<p><?php esc_html_e( 'No articles available yet.', 'erh' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
