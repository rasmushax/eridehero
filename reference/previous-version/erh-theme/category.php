<?php
/**
 * Category Archive Template
 *
 * Routes category archives to either:
 * 1. Hub layout for product categories (e-scooters, e-bikes, etc.)
 * 2. Standard archive for other categories
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get the current category.
$category = get_queried_object();

if ( ! $category instanceof WP_Term ) {
	// Fallback to index.
	get_template_part( 'index' );
	return;
}

$category_slug = $category->slug;

/**
 * Hub categories that get the special hub layout.
 *
 * Maps category slug to display product type name.
 */
$hub_categories = array(
	'electric-scooters'    => 'Electric Scooter',
	'electric-bikes'       => 'Electric Bike',
	'electric-unicycles'   => 'Electric Unicycle',
	'electric-skateboards' => 'Electric Skateboard',
	'hoverboards'          => 'Hoverboard',
);

$is_hub_category = isset( $hub_categories[ $category_slug ] );

if ( $is_hub_category ) {
	// Render the hub layout for product categories.
	$product_type = $hub_categories[ $category_slug ];

	get_header();
	?>
	<main id="main-content">
		<?php
		get_template_part( 'template-parts/hub/layout', null, array(
			'category'     => $category,
			'product_type' => $product_type,
		) );
		?>
	</main>
	<?php
	get_footer();

} else {
	// Standard category archive for other categories.
	get_template_part( 'template-parts/archive/standard', null, array(
		'category' => $category,
	) );
}
