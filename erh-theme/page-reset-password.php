<?php
/**
 * Template Name: Reset Password
 *
 * Password reset page for users who clicked the email link.
 * Standalone page (no header/footer) matching the auth layout.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Redirect logged-in users to account page.
if ( is_user_logged_in() ) {
	wp_redirect( home_url( '/account/' ) );
	exit;
}

// Get reset key and login from URL.
$reset_key   = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$reset_login = isset( $_GET['login'] ) ? sanitize_user( wp_unslash( $_GET['login'] ) ) : '';

// Get auth page image from ACF.
$auth_image = get_field( 'auth_page_image', 'option' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body class="auth-page">

<div class="auth-layout" data-reset-password-page data-key="<?php echo esc_attr( $reset_key ); ?>" data-login="<?php echo esc_attr( $reset_login ); ?>">
	<!-- Left: Form -->
	<div class="auth-form-wrapper">
		<div class="auth-form-inner">
			<!-- Logo -->
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="auth-logo">
				<?php echo erh_get_logo( 'auth-logo-img' ); ?>
			</a>

			<?php if ( empty( $reset_key ) || empty( $reset_login ) ) : ?>
				<!-- Missing Params State -->
				<div class="auth-state">
					<div class="auth-header">
						<h1 class="auth-title"><?php esc_html_e( 'Invalid reset link', 'erh' ); ?></h1>
						<p class="auth-subtitle"><?php esc_html_e( 'This password reset link is invalid or has expired. Please request a new one.', 'erh' ); ?></p>
					</div>
					<a href="<?php echo esc_url( home_url( '/login/#forgot' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Request new link', 'erh' ); ?></a>
				</div>
			<?php else : ?>
				<!-- Reset Form State -->
				<div class="auth-state" id="reset-form">
					<div class="auth-header">
						<h1 class="auth-title"><?php esc_html_e( 'Set new password', 'erh' ); ?></h1>
						<p class="auth-subtitle"><?php esc_html_e( 'Enter your new password below', 'erh' ); ?></p>
					</div>

					<form class="auth-form" data-auth-form="reset-password">
						<div class="auth-field">
							<label for="reset-password" class="auth-label"><?php esc_html_e( 'New password', 'erh' ); ?></label>
							<input type="password" id="reset-password" name="password" class="auth-input" placeholder="<?php esc_attr_e( 'At least 8 characters', 'erh' ); ?>" minlength="8" required>
						</div>

						<div class="auth-field">
							<label for="reset-password-confirm" class="auth-label"><?php esc_html_e( 'Confirm password', 'erh' ); ?></label>
							<input type="password" id="reset-password-confirm" name="password_confirm" class="auth-input" placeholder="<?php esc_attr_e( 'Re-enter your password', 'erh' ); ?>" minlength="8" required>
						</div>

						<div class="auth-error" data-auth-error hidden></div>

						<button type="submit" class="btn btn-primary auth-submit">
							<span class="auth-submit-text"><?php esc_html_e( 'Reset password', 'erh' ); ?></span>
							<span class="auth-submit-loading" hidden>
								<svg class="spinner" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
							</span>
						</button>
					</form>
				</div>

				<!-- Reset Success State -->
				<div class="auth-state" id="reset-success" hidden>
					<div class="auth-success">
						<div class="auth-success-icon">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
								<polyline points="22 4 12 14.01 9 11.01"></polyline>
							</svg>
						</div>
						<h1 class="auth-title"><?php esc_html_e( 'Password updated', 'erh' ); ?></h1>
						<p class="auth-subtitle"><?php esc_html_e( 'Your password has been reset successfully. You can now sign in with your new password.', 'erh' ); ?></p>
						<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="btn btn-primary"><?php esc_html_e( 'Sign in', 'erh' ); ?></a>
					</div>
				</div>
			<?php endif; ?>

			<!-- Legal -->
			<p class="auth-legal">
				<?php
				printf(
					/* translators: %1$s: terms link, %2$s: privacy link */
					esc_html__( 'By continuing, you agree to our %1$s and %2$s.', 'erh' ),
					'<a href="' . esc_url( home_url( '/terms/' ) ) . '">' . esc_html__( 'Terms of Service', 'erh' ) . '</a>',
					'<a href="' . esc_url( home_url( '/privacy/' ) ) . '">' . esc_html__( 'Privacy Policy', 'erh' ) . '</a>'
				);
				?>
			</p>
		</div>
	</div>

	<!-- Right: Image -->
	<div class="auth-image">
		<?php if ( $auth_image ) : ?>
			<img src="<?php echo esc_url( $auth_image['url'] ); ?>" alt="<?php echo esc_attr( $auth_image['alt'] ?: '' ); ?>" loading="eager">
		<?php endif; ?>
	</div>
</div>

<?php wp_footer(); ?>
</body>
</html>
