<?php
/**
 * Archive Pagination Template Part
 *
 * Pagination nav for archive pages.
 *
 * Expected args:
 * - paged (int): Current page number
 * - total_pages (int): Total number of pages
 * - base_url (string): Optional custom base URL for pagination links
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$paged       = $args['paged'] ?? 1;
$total_pages = $args['total_pages'] ?? 1;
$base_url    = $args['base_url'] ?? '';

// Don't render if only one page.
if ( $total_pages <= 1 ) {
	return;
}

/**
 * Get pagination link URL.
 *
 * @param int    $page     Page number.
 * @param string $base_url Custom base URL.
 * @return string URL.
 */
$get_page_url = function( $page ) use ( $base_url ) {
	if ( $base_url ) {
		if ( $page <= 1 ) {
			return trailingslashit( $base_url );
		}
		return trailingslashit( $base_url ) . 'page/' . $page . '/';
	}
	return get_pagenum_link( $page );
};

$prev_disabled = ( $paged <= 1 ) ? 'aria-disabled="true"' : '';
$prev_href     = ( $paged > 1 ) ? $get_page_url( $paged - 1 ) : '#';
$next_disabled = ( $paged >= $total_pages ) ? 'aria-disabled="true"' : '';
$next_href     = ( $paged < $total_pages ) ? $get_page_url( $paged + 1 ) : '#';

$range = 2; // Pages to show on each side of current.
?>

<nav class="pagination" aria-label="<?php esc_attr_e( 'Pagination', 'erh' ); ?>">
	<a href="<?php echo esc_url( $prev_href ); ?>" class="pagination-btn pagination-prev" <?php echo $prev_disabled; ?>>
		<?php erh_the_icon( 'chevron-left', 'icon' ); ?>
		<span><?php esc_html_e( 'Previous', 'erh' ); ?></span>
	</a>

	<div class="pagination-pages">
		<?php
		for ( $i = 1; $i <= $total_pages; $i++ ) :
			if ( $i === 1 || $i === $total_pages || ( $i >= $paged - $range && $i <= $paged + $range ) ) :
				$is_current = ( $i === $paged );
				?>
				<a href="<?php echo esc_url( $get_page_url( $i ) ); ?>" class="pagination-page <?php echo $is_current ? 'is-active' : ''; ?>" <?php echo $is_current ? 'aria-current="page"' : ''; ?>>
					<?php echo esc_html( $i ); ?>
				</a>
				<?php
			elseif ( $i === $paged - $range - 1 || $i === $paged + $range + 1 ) :
				?>
				<span class="pagination-ellipsis">...</span>
				<?php
			endif;
		endfor;
		?>
	</div>

	<a href="<?php echo esc_url( $next_href ); ?>" class="pagination-btn pagination-next" <?php echo $next_disabled; ?>>
		<span><?php esc_html_e( 'Next', 'erh' ); ?></span>
		<?php erh_the_icon( 'chevron-right', 'icon' ); ?>
	</a>
</nav>
