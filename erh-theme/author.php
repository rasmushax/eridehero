<?php
/**
 * Author Archive Template
 *
 * Displays author bio and their posts with type filters.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get the author.
$author = get_queried_object();

if ( ! $author instanceof WP_User ) {
	get_template_part( 'index' );
	return;
}

$author_id = $author->ID;

// Only allow author pages for users who can publish content.
// This prevents subscribers/customers from having public author archives.
$allowed_roles = array( 'administrator', 'editor', 'author' );
$user_roles    = (array) $author->roles;
$has_allowed_role = ! empty( array_intersect( $allowed_roles, $user_roles ) );

if ( ! $has_allowed_role ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	get_template_part( '404' );
	return;
}

// Check if author has published posts.
$published_count = count_user_posts( $author_id, 'post', true );

if ( $published_count < 1 ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	get_template_part( '404' );
	return;
}

// Pagination.
$paged          = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;
$posts_per_page = 12;

// Query author's posts.
$author_query = new WP_Query( array(
	'author'         => $author_id,
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => $posts_per_page,
	'paged'          => $paged,
	'orderby'        => 'date',
	'order'          => 'DESC',
) );

// Get ALL author posts for counting (not just current page).
$count_query = new WP_Query( array(
	'author'         => $author_id,
	'post_type'      => 'post',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'fields'         => 'ids',
) );

$total_posts = $count_query->found_posts;

// Build type counts.
$type_counts = array(
	'all'          => $total_posts,
	'review'       => 0,
	'buying-guide' => 0,
	'article'      => 0,
);

if ( $count_query->have_posts() ) {
	foreach ( $count_query->posts as $post_id ) {
		$tags = wp_get_post_tags( $post_id, array( 'fields' => 'slugs' ) );

		if ( in_array( 'review', $tags, true ) ) {
			$type_counts['review']++;
		} elseif ( in_array( 'buying-guide', $tags, true ) ) {
			$type_counts['buying-guide']++;
		} else {
			$type_counts['article']++;
		}
	}
}
wp_reset_postdata();

// Build filter categories for the filters template.
$active_types = array();

$type_labels = array(
	'review'       => __( 'Reviews', 'erh' ),
	'buying-guide' => __( 'Buying Guides', 'erh' ),
	'article'      => __( 'Articles', 'erh' ),
);

foreach ( $type_labels as $slug => $label ) {
	if ( $type_counts[ $slug ] > 0 ) {
		$active_types[] = (object) array(
			'slug'  => $slug,
			'name'  => $label,
			'count' => $type_counts[ $slug ],
		);
	}
}

// Sort by count descending.
usort( $active_types, function( $a, $b ) {
	return $b->count - $a->count;
} );

/**
 * Get post type info for card rendering and filtering.
 *
 * @param int $post_id Post ID.
 * @return array { card_type: string, filter_slug: string }
 */
$get_post_type_info = function( $post_id ) {
	$tags = wp_get_post_tags( $post_id, array( 'fields' => 'slugs' ) );

	if ( in_array( 'review', $tags, true ) ) {
		return array(
			'card_type'   => 'review',
			'filter_slug' => 'review',
		);
	}

	if ( in_array( 'buying-guide', $tags, true ) ) {
		return array(
			'card_type'   => 'guide',
			'filter_slug' => 'buying-guide',
		);
	}

	return array(
		'card_type'   => 'article',
		'filter_slug' => 'article',
	);
};

get_header();
?>

<main id="main-content" class="archive-main author-archive">
	<div class="container">

		<?php
		get_template_part( 'template-parts/archive/author-header', null, array(
			'author_id' => $author_id,
		) );
		?>

		<?php if ( count( $active_types ) > 1 ) : ?>
			<nav class="archive-filters" aria-label="<?php esc_attr_e( 'Filter by type', 'erh' ); ?>" data-archive-filters>
				<button type="button" class="archive-filter is-active" data-filter="all">
					<?php esc_html_e( 'All', 'erh' ); ?>
					<span class="archive-filter-count"><?php echo esc_html( $type_counts['all'] ); ?></span>
				</button>
				<?php foreach ( $active_types as $type ) : ?>
					<button type="button" class="archive-filter" data-filter="<?php echo esc_attr( $type->slug ); ?>">
						<?php echo esc_html( $type->name ); ?>
						<span class="archive-filter-count"><?php echo esc_html( $type->count ); ?></span>
					</button>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( $author_query->have_posts() ) : ?>

			<section class="section archive-content">
				<div class="archive-grid" data-archive-grid>
					<?php
					while ( $author_query->have_posts() ) :
						$author_query->the_post();
						$post_id     = get_the_ID();
						$type_info   = $get_post_type_info( $post_id );
						$filter_slug = $type_info['filter_slug'];

						// Type labels for display.
						$type_labels = array(
							'review'       => __( 'Review', 'erh' ),
							'guide'        => __( 'Buying Guide', 'erh' ),
							'article'      => __( 'Article', 'erh' ),
						);
						$type_label = $type_labels[ $type_info['card_type'] ] ?? __( 'Article', 'erh' );

						// Consistent card: type label, title, date only.
						// Use 'review' type which shows tag + title + date (no excerpt).
						get_template_part( 'template-parts/archive/card', null, array(
							'type'           => 'review',
							'post_id'        => $post_id,
							'category_slugs' => $filter_slug,
							'category_name'  => $type_label,
						) );
					endwhile;
					?>
				</div>

				<!-- Empty State (hidden by default, shown via JS when filtering) -->
				<div class="archive-empty" data-archive-empty hidden>
					<p><?php esc_html_e( 'No posts found for this filter.', 'erh' ); ?></p>
				</div>
			</section>

			<?php
			get_template_part( 'template-parts/archive/pagination', null, array(
				'paged'       => $paged,
				'total_pages' => $author_query->max_num_pages,
			) );
			?>

		<?php else : ?>

			<div class="empty-state">
				<?php erh_the_icon( 'book' ); ?>
				<h3><?php esc_html_e( 'No posts yet', 'erh' ); ?></h3>
				<p><?php esc_html_e( 'This author hasn\'t published any posts yet.', 'erh' ); ?></p>
			</div>

		<?php endif; ?>

		<?php wp_reset_postdata(); ?>

	</div>
</main>

<?php get_footer(); ?>
