<?php
/**
 * Archive Card Template Part
 *
 * Unified card component for archive pages with type-specific variations.
 *
 * Expected args:
 * - type (string): Card type - 'article', 'review', 'guide'
 * - post_id (int): Post ID
 * - category_slugs (string): Space-separated category slugs for data attribute
 * - category_name (string): Display name of primary category
 *
 * Type-specific args:
 * - excerpt (string): Post excerpt (article only)
 * - reading_time (int): Minutes to read (article only)
 * - rating (float|null): Rating score (review only)
 * - custom_title (string): Custom card title (guide only)
 * - hidden (bool): SSR filter — hide card on initial render (default false)
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$type           = $args['type'] ?? 'article';
$post_id        = $args['post_id'] ?? get_the_ID();
$category_slugs = $args['category_slugs'] ?? '';
$category_name  = $args['category_name'] ?? '';
$permalink      = get_permalink( $post_id );
$title          = get_the_title( $post_id );
$thumbnail      = get_the_post_thumbnail_url( $post_id, 'medium_large' );
$placeholder    = ERH_THEME_URI . '/assets/images/placeholder.jpg';

// Type-specific data.
$excerpt      = $args['excerpt'] ?? '';
$reading_time = $args['reading_time'] ?? 0;
$rating       = $args['rating'] ?? null;
$custom_title = $args['custom_title'] ?? '';
$is_hidden    = ! empty( $args['hidden'] );

// Card class modifier.
$card_class = 'archive-card';
if ( 'article' === $type ) {
	$card_class .= ' archive-card--article';
}

// Use custom title for guides if provided.
if ( 'guide' === $type && $custom_title ) {
	$title = $custom_title;
}

// Date for sorting (reviews).
$date_raw = get_the_date( 'Y-m-d', $post_id );

// Build data attributes.
$data_attrs = 'data-category="' . esc_attr( $category_slugs ) . '"';
if ( 'review' === $type ) {
	$data_attrs .= ' data-date="' . esc_attr( $date_raw ) . '"';
	if ( $rating ) {
		$data_attrs .= ' data-rating="' . esc_attr( $rating ) . '"';
	}
}
?>

<a href="<?php echo esc_url( $permalink ); ?>" class="<?php echo esc_attr( $card_class ); ?>" <?php echo $data_attrs; ?><?php echo $is_hidden ? ' hidden' : ''; ?>>
	<div class="archive-card-img">
		<?php if ( $thumbnail ) : ?>
			<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
		<?php else : ?>
			<img src="<?php echo esc_url( $placeholder ); ?>" alt="" loading="lazy">
		<?php endif; ?>

		<?php if ( $category_name ) : ?>
			<span class="archive-card-tag"><?php echo esc_html( $category_name ); ?></span>
		<?php endif; ?>

		<?php if ( 'review' === $type && $rating ) : ?>
			<span class="archive-card-score"><?php echo esc_html( number_format( $rating, 1 ) ); ?></span>
		<?php endif; ?>
	</div>

	<h2 class="archive-card-title"><?php echo esc_html( $title ); ?></h2>

	<?php if ( 'article' === $type && $excerpt ) : ?>
		<p class="archive-card-excerpt"><?php echo esc_html( $excerpt ); ?></p>
	<?php elseif ( 'review' === $type ) :
		$tldr = get_field( 'review_tldr', $post_id );
		if ( $tldr ) : ?>
		<p class="archive-card-excerpt"><?php echo esc_html( $tldr ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( 'article' === $type ) : ?>
		<div class="archive-card-meta">
			<span><?php echo esc_html( get_the_date( 'M j, Y', $post_id ) ); ?></span>
			<?php if ( $reading_time ) : ?>
				<span class="archive-card-meta-sep">&middot;</span>
				<span><?php printf( esc_html__( '%d min read', 'erh' ), $reading_time ); ?></span>
			<?php endif; ?>
		</div>
	<?php elseif ( 'review' === $type ) : ?>
		<div class="archive-card-meta">
			<span><?php echo esc_html( get_the_date( 'M j, Y', $post_id ) ); ?></span>
		</div>
	<?php endif; ?>
</a>
