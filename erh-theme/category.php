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

// Check if this category is linked to a product type (hub page).
$hub_context = erh_get_hub_context( $category );

if ( $hub_context ) {
	// Render the hub layout for product categories.
	get_header();
	?>
	<main id="main-content">
		<?php
		get_template_part( 'template-parts/hub/layout', null, array(
			'category'    => $category,
			'hub_context' => $hub_context,
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
