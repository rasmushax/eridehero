<?php
/**
 * Template Name: Account
 *
 * User account dashboard with price trackers and settings.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Redirect logged-out users to home with login modal.
if ( ! is_user_logged_in() ) {
	wp_redirect( home_url( '/?login=1' ) );
	exit;
}

$current_user = wp_get_current_user();

get_header();
?>

<main class="account-page">
	<div class="container">
		<div class="account-layout">
			<?php
			get_template_part( 'template-parts/account/sidebar', null, array(
				'user' => $current_user,
			) );
			?>

			<div class="account-content">
				<!-- Trackers Tab -->
				<div class="account-tab" id="trackers" data-account-tab="trackers">
					<?php get_template_part( 'template-parts/account/trackers' ); ?>
				</div>

				<!-- Settings Tab -->
				<div class="account-tab" id="settings" data-account-tab="settings" hidden>
					<?php
					get_template_part( 'template-parts/account/settings', null, array(
						'user' => $current_user,
					) );
					?>
				</div>
			</div>
		</div>
	</div>
</main>

<?php get_footer(); ?>
