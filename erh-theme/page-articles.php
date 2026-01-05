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

// Pagination.
$paged          = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
$posts_per_page = 12;

// Query posts tagged "articles".
$articles_query = new WP_Query( array(
	'post_type'      => 'post',
	'tag'            => 'article',
	'posts_per_page' => $posts_per_page,
	'paged'          => $paged,
	'post_status'    => 'publish',
	'orderby'        => 'date',
	'order'          => 'DESC',
) );

// Build category counts from ALL articles (not just current page).
$count_query = new WP_Query( array(
	'post_type'      => 'post',
	'tag'            => 'article',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
	'fields'         => 'ids',
) );

$category_counts = array( 'all' => $count_query->found_posts );

if ( $count_query->have_posts() ) {
	foreach ( $count_query->posts as $post_id ) {
		$categories = get_the_category( $post_id );
		foreach ( $categories as $cat ) {
			if ( ! isset( $category_counts[ $cat->slug ] ) ) {
				$category_counts[ $cat->slug ] = 0;
			}
			$category_counts[ $cat->slug ]++;
		}
	}
}
wp_reset_postdata();

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
		) );
		?>
	</div>
</section>

<!-- Articles Grid -->
<section class="section archive-content">
	<div class="container">
		<?php if ( $articles_query->have_posts() ) : ?>
			<div class="archive-grid" data-archive-grid>
				<?php
				while ( $articles_query->have_posts() ) :
					$articles_query->the_post();
					$post_id    = get_the_ID();
					$categories = get_the_category( $post_id );

					// Build category data.
					$category_slugs = implode( ' ', array_map( function( $cat ) {
						return $cat->slug;
					}, $categories ) );

					$category_name = ! empty( $categories )
						? erh_get_category_short_name( $categories[0]->name )
						: '';

					// Get excerpt.
					$excerpt = get_the_excerpt();
					if ( empty( $excerpt ) ) {
						$excerpt = wp_trim_words( get_the_content(), 20, '...' );
					}

					// Get reading time.
					$reading_time = erh_get_reading_time( $post_id );

					get_template_part( 'template-parts/archive/card', null, array(
						'type'           => 'article',
						'post_id'        => $post_id,
						'category_slugs' => $category_slugs,
						'category_name'  => $category_name,
						'excerpt'        => $excerpt,
						'reading_time'   => $reading_time,
					) );
				endwhile;
				?>
			</div>

			<!-- Empty State (hidden by default, shown via JS when filtering) -->
			<div class="archive-empty" data-archive-empty hidden>
				<p><?php esc_html_e( 'No articles found in this category yet.', 'erh' ); ?></p>
			</div>

			<?php
			get_template_part( 'template-parts/archive/pagination', null, array(
				'paged'       => $paged,
				'total_pages' => $articles_query->max_num_pages,
			) );
			?>

		<?php else : ?>
			<div class="archive-empty">
				<p><?php esc_html_e( 'No articles available yet.', 'erh' ); ?></p>
			</div>
		<?php endif; ?>
		<?php wp_reset_postdata(); ?>
	</div>
</section>

<?php get_footer(); ?>
