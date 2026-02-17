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

// Query ALL buying guides in this category, ordered by date (custom sorting done in PHP).
// Not using meta_key orderby since posts without guide_order would be excluded.
$all_guides_args = array(
	'post_type'      => 'post',
	'posts_per_page' => -1,
	'post_status'    => 'publish',
	'category_name'  => $category_slug,
	'tag_id'         => $buying_guide_tag->term_id,
	'orderby'        => 'date',
	'order'          => 'DESC',
);

$all_guides = get_posts( $all_guides_args );

if ( empty( $all_guides ) ) {
	return;
}

// Separate featured from regular.
$featured_guides = array();
$regular_guides  = array();

foreach ( $all_guides as $guide ) {
	if ( get_field( 'is_featured_guide', $guide->ID ) ) {
		$featured_guides[] = $guide;
	} else {
		$regular_guides[] = $guide;
	}
}

// Sort each group by guide_order (if set), then date.
$sort_by_order = function ( $a, $b ) {
	$order_a = (int) get_field( 'guide_order', $a->ID );
	$order_b = (int) get_field( 'guide_order', $b->ID );

	if ( $order_a !== $order_b ) {
		return $order_a - $order_b;
	}

	return strtotime( $b->post_date ) - strtotime( $a->post_date );
};

usort( $featured_guides, $sort_by_order );
usort( $regular_guides, $sort_by_order );
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
