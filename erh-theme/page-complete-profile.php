<?php
/**
 * Template Name: Complete Profile
 *
 * Collects email when OAuth provider (Reddit) doesn't provide one.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get state and provider from URL.
$state    = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
$provider = isset( $_GET['provider'] ) ? sanitize_text_field( wp_unslash( $_GET['provider'] ) ) : '';

// Validate required params.
if ( empty( $state ) || empty( $provider ) ) {
	wp_safe_redirect( home_url( '/login/?error=invalid_request' ) );
	exit;
}

// Verify pending OAuth state exists.
$pending_data = get_transient( 'erh_oauth_pending_' . $state );
if ( ! $pending_data ) {
	wp_safe_redirect( home_url( '/login/?error=session_expired' ) );
	exit;
}

// Already logged in? Shouldn't happen but handle gracefully.
if ( is_user_logged_in() ) {
	wp_safe_redirect( home_url() );
	exit;
}

$provider_name = ucfirst( $provider );

get_header();
?>

<main class="complete-profile-page">
	<div class="container container--narrow">
		<div class="onboarding-card" data-complete-profile>
			<div class="onboarding-header">
				<div class="onboarding-icon">
					<?php erh_the_icon( $provider ); ?>
				</div>
				<h1 class="onboarding-title"><?php esc_html_e( 'Almost there!', 'erh' ); ?></h1>
				<p class="onboarding-subtitle">
					<?php
					printf(
						/* translators: %s: Provider name (e.g., Reddit) */
						esc_html__( 'Enter your email to complete your %s signup.', 'erh' ),
						esc_html( $provider_name )
					);
					?>
				</p>
			</div>

			<form class="onboarding-form" data-complete-profile-form>
				<input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>">
				<input type="hidden" name="provider" value="<?php echo esc_attr( $provider ); ?>">

				<div class="form-field">
					<label class="form-label" for="email"><?php esc_html_e( 'Email address', 'erh' ); ?></label>
					<input
						type="email"
						id="email"
						name="email"
						class="form-input"
						required
						placeholder="you@example.com"
						autocomplete="email"
						data-email-input
					>
				</div>

				<div class="form-field" data-password-field hidden>
					<label class="form-label" for="password">
						<?php
						printf(
							/* translators: %s: Provider name */
							esc_html__( 'Password (to link your %s account)', 'erh' ),
							esc_html( $provider_name )
						);
						?>
					</label>
					<input
						type="password"
						id="password"
						name="password"
						class="form-input"
						placeholder="<?php esc_attr_e( 'Enter your existing password', 'erh' ); ?>"
					>
					<p class="form-hint">
						<?php
						printf(
							/* translators: %s: Provider name */
							esc_html__( 'An account with this email exists. Enter your password to link %s.', 'erh' ),
							esc_html( $provider_name )
						);
						?>
					</p>
				</div>

				<div class="form-error" data-error hidden></div>

				<button type="submit" class="btn btn-primary btn-lg btn-block" data-submit-btn>
					<span class="btn-text"><?php esc_html_e( 'Continue', 'erh' ); ?></span>
					<span class="btn-loading" hidden>
						<?php erh_the_icon( 'loader' ); ?>
					</span>
				</button>
			</form>

			<p class="onboarding-skip">
				<a href="<?php echo esc_url( home_url( '/login/' ) ); ?>">
					<?php esc_html_e( 'Cancel and go back', 'erh' ); ?>
				</a>
			</p>
		</div>
	</div>
</main>

<?php get_footer(); ?>
