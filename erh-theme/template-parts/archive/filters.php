<?php
/**
 * Archive Filters Template Part
 *
 * Category filter pills for archive pages.
 *
 * Expected args:
 * - category_counts (array): Associative array of slug => count, with 'all' key
 * - active_categories (array): Array of category objects sorted by count
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$category_counts   = $args['category_counts'] ?? array( 'all' => 0 );
$active_categories = $args['active_categories'] ?? array();
?>

<nav class="archive-filters" aria-label="<?php esc_attr_e( 'Filter by category', 'erh' ); ?>" data-archive-filters>
	<button type="button" class="archive-filter is-active" data-filter="all">
		<?php esc_html_e( 'All', 'erh' ); ?>
		<span class="archive-filter-count"><?php echo esc_html( $category_counts['all'] ); ?></span>
	</button>
	<?php foreach ( $active_categories as $cat ) : ?>
		<button type="button" class="archive-filter" data-filter="<?php echo esc_attr( $cat->slug ); ?>">
			<?php echo esc_html( erh_get_category_short_name( $cat->name ) ); ?>
			<span class="archive-filter-count"><?php echo esc_html( $category_counts[ $cat->slug ] ); ?></span>
		</button>
	<?php endforeach; ?>
</nav>
