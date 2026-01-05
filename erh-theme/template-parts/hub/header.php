<?php
/**
 * Hub Header
 *
 * Displays the hub page title and navigation pills for section jumping.
 *
 * Expected args:
 * - category (WP_Term): The category term object
 * - product_type (string): Display name (e.g., "Electric Scooter")
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$category     = $args['category'] ?? null;
$product_type = $args['product_type'] ?? '';

if ( ! $category ) {
	return;
}

// Use category name as title, or product_type if provided.
$title = $product_type ? $product_type . 's' : $category->name;

// Navigation sections - anchor links to page sections.
$nav_items = array(
	array(
		'id'    => 'guides',
		'label' => __( 'Buying Guides', 'erh' ),
	),
	array(
		'id'    => 'tools',
		'label' => __( 'Tools', 'erh' ),
	),
	array(
		'id'    => 'deals-section',
		'label' => __( 'Deals', 'erh' ),
	),
	array(
		'id'    => 'reviews',
		'label' => __( 'Reviews', 'erh' ),
	),
	array(
		'id'    => 'articles',
		'label' => __( 'Articles', 'erh' ),
	),
);
?>
<section class="hub-header">
	<div class="container">
		<h1 class="hub-title"><?php echo esc_html( $title ); ?></h1>
		<nav class="hub-nav" aria-label="<?php esc_attr_e( 'Page navigation', 'erh' ); ?>">
			<?php foreach ( $nav_items as $item ) : ?>
				<a href="#<?php echo esc_attr( $item['id'] ); ?>" class="hub-nav-link">
					<?php echo esc_html( $item['label'] ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>
</section>
