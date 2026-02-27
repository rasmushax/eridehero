<?php
/**
 * Template Name: Login
 *
 * Full-page authentication with sign-in, sign-up, and forgot password states.
 * Standalone page (no header/footer) matching the split-layout reference design.
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

// Sanitize redirect param for post-auth redirect.
$redirect = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : '';

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

<div class="auth-layout" data-auth-page<?php echo $redirect ? ' data-redirect="' . esc_attr( $redirect ) . '"' : ''; ?>>
	<!-- Left: Form -->
	<div class="auth-form-wrapper">
		<div class="auth-form-inner">
			<!-- Logo -->
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="auth-logo">
				<?php echo erh_get_logo( 'auth-logo-img' ); ?>
			</a>

			<!-- Sign In State (default) -->
			<div class="auth-state" id="auth-signin">
				<div class="auth-header">
					<h1 class="auth-title"><?php esc_html_e( 'Welcome back', 'erh' ); ?></h1>
					<p class="auth-subtitle"><?php esc_html_e( 'Sign in to your account to continue', 'erh' ); ?></p>
				</div>

				<div class="auth-social">
					<button type="button" class="auth-social-btn" data-social-provider="google">
						<svg class="auth-social-icon" viewBox="0 0 24 24">
							<path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
							<path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
							<path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
							<path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
						</svg>
						<?php esc_html_e( 'Continue with Google', 'erh' ); ?>
					</button>
					<button type="button" class="auth-social-btn" data-social-provider="facebook">
						<svg class="auth-social-icon" viewBox="0 0 24 24" fill="#1877F2">
							<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
						</svg>
						<?php esc_html_e( 'Continue with Facebook', 'erh' ); ?>
					</button>
					<button type="button" class="auth-social-btn" data-social-provider="reddit">
						<svg class="auth-social-icon" viewBox="0 0 24 24" fill="#FF4500">
							<path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/>
						</svg>
						<?php esc_html_e( 'Continue with Reddit', 'erh' ); ?>
					</button>
				</div>

				<div class="auth-divider">
					<span><?php esc_html_e( 'or continue with email', 'erh' ); ?></span>
				</div>

				<form class="auth-form" data-auth-form="signin">
					<div class="auth-field">
						<label for="signin-email" class="auth-label"><?php esc_html_e( 'Email address', 'erh' ); ?></label>
						<input type="email" id="signin-email" name="email" class="auth-input" placeholder="you@example.com" required>
					</div>

					<div class="auth-field">
						<div class="auth-label-row">
							<label for="signin-password" class="auth-label"><?php esc_html_e( 'Password', 'erh' ); ?></label>
							<a href="#forgot" class="auth-forgot" data-auth-switch="forgot"><?php esc_html_e( 'Forgot password?', 'erh' ); ?></a>
						</div>
						<input type="password" id="signin-password" name="password" class="auth-input" placeholder="<?php esc_attr_e( 'Enter your password', 'erh' ); ?>" required>
					</div>

					<div class="auth-error" data-auth-error hidden></div>

					<button type="submit" class="btn btn-primary auth-submit">
						<span class="auth-submit-text"><?php esc_html_e( 'Sign in', 'erh' ); ?></span>
						<span class="auth-submit-loading" hidden>
							<svg class="spinner" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
						</span>
					</button>
				</form>

				<p class="auth-toggle">
					<?php esc_html_e( "Don't have an account?", 'erh' ); ?> <a href="#register" data-auth-switch="signup"><?php esc_html_e( 'Create one for free', 'erh' ); ?></a>
				</p>
			</div>

			<!-- Sign Up State -->
			<div class="auth-state" id="auth-signup" hidden>
				<a href="#" class="auth-back" data-auth-switch="signin">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
						<path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<?php esc_html_e( 'Back to sign in', 'erh' ); ?>
				</a>

				<div class="auth-header">
					<h1 class="auth-title"><?php esc_html_e( 'Create your account', 'erh' ); ?></h1>
					<p class="auth-subtitle"><?php esc_html_e( 'Join 1,200+ members – it\'s free forever', 'erh' ); ?></p>
				</div>

				<div class="auth-social">
					<button type="button" class="auth-social-btn" data-social-provider="google">
						<svg class="auth-social-icon" viewBox="0 0 24 24">
							<path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
							<path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
							<path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
							<path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
						</svg>
						<?php esc_html_e( 'Continue with Google', 'erh' ); ?>
					</button>
					<button type="button" class="auth-social-btn" data-social-provider="facebook">
						<svg class="auth-social-icon" viewBox="0 0 24 24" fill="#1877F2">
							<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
						</svg>
						<?php esc_html_e( 'Continue with Facebook', 'erh' ); ?>
					</button>
					<button type="button" class="auth-social-btn" data-social-provider="reddit">
						<svg class="auth-social-icon" viewBox="0 0 24 24" fill="#FF4500">
							<path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/>
						</svg>
						<?php esc_html_e( 'Continue with Reddit', 'erh' ); ?>
					</button>
				</div>

				<div class="auth-divider">
					<span><?php esc_html_e( 'or continue with email', 'erh' ); ?></span>
				</div>

				<form class="auth-form" data-auth-form="signup">
					<div class="auth-field">
						<label for="signup-email" class="auth-label"><?php esc_html_e( 'Email address', 'erh' ); ?></label>
						<input type="email" id="signup-email" name="email" class="auth-input" placeholder="you@example.com" required>
					</div>

					<div class="auth-field">
						<label for="signup-password" class="auth-label"><?php esc_html_e( 'Create password', 'erh' ); ?></label>
						<input type="password" id="signup-password" name="password" class="auth-input" placeholder="<?php esc_attr_e( 'At least 8 characters', 'erh' ); ?>" minlength="8" required>
					</div>

					<div class="auth-error" data-auth-error hidden></div>

					<button type="submit" class="btn btn-primary auth-submit">
						<span class="auth-submit-text"><?php esc_html_e( 'Create account', 'erh' ); ?></span>
						<span class="auth-submit-loading" hidden>
							<svg class="spinner" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
						</span>
					</button>
				</form>
			</div>

			<!-- Forgot Password State -->
			<div class="auth-state" id="auth-forgot" hidden>
				<a href="#" class="auth-back" data-auth-switch="signin">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none">
						<path d="M12.5 15L7.5 10L12.5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
					</svg>
					<?php esc_html_e( 'Back to sign in', 'erh' ); ?>
				</a>

				<div class="auth-header">
					<h1 class="auth-title"><?php esc_html_e( 'Reset your password', 'erh' ); ?></h1>
					<p class="auth-subtitle"><?php esc_html_e( "Enter your email and we'll send you a reset link", 'erh' ); ?></p>
				</div>

				<form class="auth-form" data-auth-form="forgot">
					<div class="auth-field">
						<label for="forgot-email" class="auth-label"><?php esc_html_e( 'Email address', 'erh' ); ?></label>
						<input type="email" id="forgot-email" name="email" class="auth-input" placeholder="you@example.com" required>
					</div>

					<div class="auth-error" data-auth-error hidden></div>

					<button type="submit" class="btn btn-primary auth-submit">
						<span class="auth-submit-text"><?php esc_html_e( 'Send reset link', 'erh' ); ?></span>
						<span class="auth-submit-loading" hidden>
							<svg class="spinner" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4" stroke-linecap="round"/></svg>
						</span>
					</button>
				</form>
			</div>

			<!-- Forgot Password Sent State -->
			<div class="auth-state" id="auth-forgot-sent" hidden>
				<div class="auth-success">
					<div class="auth-success-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
							<polyline points="22 4 12 14.01 9 11.01"></polyline>
						</svg>
					</div>
					<h1 class="auth-title"><?php esc_html_e( 'Check your email', 'erh' ); ?></h1>
					<p class="auth-subtitle"><?php esc_html_e( "We've sent a password reset link to your email address. The link will expire in 12 hours.", 'erh' ); ?></p>
					<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="btn btn-secondary"><?php esc_html_e( 'Back to sign in', 'erh' ); ?></a>
				</div>
			</div>

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
