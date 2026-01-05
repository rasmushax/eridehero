<?php
/**
 * Template Name: Buying Guides
 *
 * Archive page for buying guide posts.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// Query all posts tagged "buying-guide".
$guides_query = new WP_Query( array(
	'post_type'      => 'post',
	'tag'            => 'buying-guide',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
	'orderby'        => 'date',
	'order'          => 'DESC',
) );

// Build category counts and collect post data.
$category_counts = array( 'all' => 0 );
$guides_data     = array();

if ( $guides_query->have_posts() ) {
	while ( $guides_query->have_posts() ) {
		$guides_query->the_post();
		$post_id    = get_the_ID();
		$categories = get_the_category( $post_id );

		// Count categories.
		$category_slugs_arr = array();
		foreach ( $categories as $cat ) {
			$category_slugs_arr[] = $cat->slug;
			if ( ! isset( $category_counts[ $cat->slug ] ) ) {
				$category_counts[ $cat->slug ] = 0;
			}
			$category_counts[ $cat->slug ]++;
		}

		// Get first category name for display.
		$category_name = '';
		if ( ! empty( $categories ) ) {
			$category_name = erh_get_category_short_name( $categories[0]->name );
		}

		// Store data for rendering.
		$guides_data[] = array(
			'post_id'        => $post_id,
			'category_slugs' => implode( ' ', $category_slugs_arr ),
			'category_name'  => $category_name,
			'custom_title'   => get_field( 'buying_guide_card_title', $post_id ) ?: '',
		);

		$category_counts['all']++;
	}
	wp_reset_postdata();
}

// Get active categories sorted by count.
$all_categories    = get_categories( array( 'hide_empty' => false ) );
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

<!-- Guides Grid -->
<section class="section archive-content">
	<div class="container">
		<?php if ( ! empty( $guides_data ) ) : ?>
			<div class="archive-grid" data-archive-grid>
				<?php
				foreach ( $guides_data as $guide ) :
					get_template_part( 'template-parts/archive/card', null, array(
						'type'           => 'guide',
						'post_id'        => $guide['post_id'],
						'category_slugs' => $guide['category_slugs'],
						'category_name'  => $guide['category_name'],
						'custom_title'   => $guide['custom_title'],
					) );
				endforeach;
				?>
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

<?php get_footer(); ?>
