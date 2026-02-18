<?php
/**
 * Archive Filters Template Part
 *
 * Category filter pills for archive pages.
 * Pills are <a> tags with ?category= hrefs for no-JS fallback + SEO.
 * JS enhances with pushState to avoid full reloads.
 *
 * Expected args:
 * - category_counts (array): Associative array of slug => count, with 'all' key
 * - active_categories (array): Array of category objects sorted by count
 * - current_filter (string): Active filter slug (default: 'all')
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$category_counts   = $args['category_counts'] ?? array( 'all' => 0 );
$active_categories = $args['active_categories'] ?? array();
$current_filter    = $args['current_filter'] ?? 'all';
$base_url          = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
?>

<nav class="archive-filters" aria-label="<?php esc_attr_e( 'Filter by category', 'erh' ); ?>" data-archive-filters>
	<a href="<?php echo esc_url( $base_url ); ?>" class="archive-filter<?php echo 'all' === $current_filter ? ' is-active' : ''; ?>" data-filter="all">
		<?php esc_html_e( 'All', 'erh' ); ?>
		<span class="archive-filter-count"><?php echo esc_html( $category_counts['all'] ); ?></span>
	</a>
	<?php foreach ( $active_categories as $cat ) : ?>
		<a href="<?php echo esc_url( $base_url . '?category=' . $cat->slug ); ?>" class="archive-filter<?php echo $cat->slug === $current_filter ? ' is-active' : ''; ?>" data-filter="<?php echo esc_attr( $cat->slug ); ?>">
			<?php echo esc_html( erh_get_category_short_name( $cat->name ) ); ?>
			<span class="archive-filter-count"><?php echo esc_html( $category_counts[ $cat->slug ] ); ?></span>
		</a>
	<?php endforeach; ?>
</nav>
