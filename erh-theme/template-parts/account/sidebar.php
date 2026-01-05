<?php
/**
 * Account Sidebar Component
 *
 * Displays user avatar, name, navigation, and logout button.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'user' => WP_User object
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user = $args['user'] ?? wp_get_current_user();

// Get user display name.
$display_name = $user->display_name ?: $user->user_login;

// Get avatar - use Gravatar or first initial.
$avatar_url = get_avatar_url( $user->ID, array( 'size' => 96 ) );
$initial    = strtoupper( substr( $display_name, 0, 1 ) );
?>

<aside class="account-sidebar">
	<div class="account-profile">
		<?php if ( $avatar_url ) : ?>
			<img
				src="<?php echo esc_url( $avatar_url ); ?>"
				alt="<?php echo esc_attr( $display_name ); ?>"
				class="account-avatar"
				width="72"
				height="72"
			>
		<?php else : ?>
			<div class="account-avatar account-avatar--initial">
				<?php echo esc_html( $initial ); ?>
			</div>
		<?php endif; ?>

		<div class="account-user-name"><?php echo esc_html( $display_name ); ?></div>
	</div>

	<nav class="account-nav" aria-label="Account navigation">
		<a href="#trackers" class="account-nav-link is-active" data-account-nav="trackers">
			<?php erh_the_icon( 'bell', 'account-nav-icon' ); ?>
			<span>Price Trackers</span>
		</a>
		<a href="#settings" class="account-nav-link" data-account-nav="settings">
			<?php erh_the_icon( 'settings', 'account-nav-icon' ); ?>
			<span>Settings</span>
		</a>
	</nav>

	<div class="account-logout-wrapper">
		<button type="button" class="account-logout" data-account-logout>
			<?php erh_the_icon( 'log-out', 'account-nav-icon' ); ?>
			<span>Log Out</span>
		</button>
	</div>
</aside>
