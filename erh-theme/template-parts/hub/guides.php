<?php
/**
 * Hub Buying Guides Section
 *
 * Displays buying guides with featured row (2 large cards) and grid (4 regular cards).
 * Guides are posts tagged with 'buying-guide' and in the current category.
 *
 * Expected args:
 * - category (WP_Term): The category term object
 * - category_slug (string): Category slug for queries
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$category      = $args['category'] ?? null;
$category_slug = $args['category_slug'] ?? '';

if ( ! $category_slug ) {
	return;
}

// Get the buying-guide tag.
$buying_guide_tag = get_term_by( 'slug', 'buying-guide', 'post_tag' );

if ( ! $buying_guide_tag ) {
	return;
}

// Query featured guides (is_featured_guide = true, max 2).
$featured_args = array(
	'post_type'      => 'post',
	'posts_per_page' => 2,
	'post_status'    => 'publish',
	'category_name'  => $category_slug,
	'tag_id'         => $buying_guide_tag->term_id,
	'meta_query'     => array(
		array(
			'key'     => 'is_featured_guide',
			'value'   => '1',
			'compare' => '=',
		),
	),
	'meta_key'       => 'guide_order',
	'orderby'        => 'meta_value_num',
	'order'          => 'ASC',
);

$featured_guides = get_posts( $featured_args );
$featured_ids    = wp_list_pluck( $featured_guides, 'ID' );

// Query regular guides (not featured, max 4).
$regular_args = array(
	'post_type'      => 'post',
	'posts_per_page' => 4,
	'post_status'    => 'publish',
	'category_name'  => $category_slug,
	'tag_id'         => $buying_guide_tag->term_id,
	'post__not_in'   => $featured_ids,
	'meta_key'       => 'guide_order',
	'orderby'        => array(
		'meta_value_num' => 'ASC',
		'date'           => 'DESC',
	),
);

$regular_guides = get_posts( $regular_args );

// If no guides at all, don't render section.
if ( empty( $featured_guides ) && empty( $regular_guides ) ) {
	return;
}
?>
<section class="section hub-guides" id="guides">
	<div class="container">
		<div class="section-header">
			<h2><?php esc_html_e( 'Buying Guides', 'erh' ); ?></h2>
		</div>

		<?php if ( ! empty( $featured_guides ) ) : ?>
		<!-- Featured guides row (2 large cards) -->
		<div class="hub-guides-featured-row">
			<?php foreach ( $featured_guides as $guide ) :
				$badge = get_field( 'guide_badge', $guide->ID );
			?>
				<a href="<?php echo esc_url( get_permalink( $guide->ID ) ); ?>" class="hub-guide-featured">
					<div class="hub-guide-featured-img">
						<?php if ( has_post_thumbnail( $guide->ID ) ) : ?>
							<?php echo get_the_post_thumbnail( $guide->ID, 'large', array( 'alt' => get_the_title( $guide->ID ) ) ); ?>
						<?php else : ?>
							<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholder-guide.jpg' ); ?>" alt="<?php echo esc_attr( get_the_title( $guide->ID ) ); ?>">
						<?php endif; ?>
						<?php if ( $badge ) : ?>
							<span class="hub-guide-badge"><?php echo esc_html( $badge ); ?></span>
						<?php endif; ?>
					</div>
					<div class="hub-guide-featured-content">
						<h3 class="hub-guide-featured-title"><?php echo esc_html( get_the_title( $guide->ID ) ); ?></h3>
						<?php if ( has_excerpt( $guide->ID ) ) : ?>
							<p class="hub-guide-featured-excerpt"><?php echo esc_html( get_the_excerpt( $guide->ID ) ); ?></p>
						<?php endif; ?>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $regular_guides ) ) : ?>
		<!-- Regular guides grid (4 cards) -->
		<div class="hub-guides-grid">
			<?php foreach ( $regular_guides as $guide ) : ?>
				<a href="<?php echo esc_url( get_permalink( $guide->ID ) ); ?>" class="hub-guide-card">
					<div class="hub-guide-card-img">
						<?php if ( has_post_thumbnail( $guide->ID ) ) : ?>
							<?php echo get_the_post_thumbnail( $guide->ID, 'medium_large', array( 'alt' => get_the_title( $guide->ID ) ) ); ?>
						<?php else : ?>
							<img src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/placeholder-guide.jpg' ); ?>" alt="<?php echo esc_attr( get_the_title( $guide->ID ) ); ?>">
						<?php endif; ?>
					</div>
					<div class="hub-guide-card-content">
						<h3 class="hub-guide-card-title"><?php echo esc_html( get_the_title( $guide->ID ) ); ?></h3>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
</section>
