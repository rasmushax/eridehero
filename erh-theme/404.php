<?php
/**
 * 404 Page Template
 *
 * Displayed when no content matches the requested URL.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="main-content" class="error-404">
	<div class="container">

		<div class="empty-state">
			<?php erh_the_icon( 'search' ); ?>
			<h1><?php esc_html_e( 'Page not found', 'erh' ); ?></h1>
			<p><?php esc_html_e( "The page you're looking for doesn't exist or has been moved.", 'erh' ); ?></p>
		</div>

		<div class="error-404-search">
			<form action="<?php echo esc_url( home_url( '/search/' ) ); ?>" method="get" class="error-404-search-form">
				<?php erh_the_icon( 'search' ); ?>
				<input type="text" name="q" placeholder="<?php esc_attr_e( 'Search products, reviews, guides...', 'erh' ); ?>" class="error-404-search-input" autofocus>
			</form>
		</div>

	</div>
</main>

<?php
get_footer();
