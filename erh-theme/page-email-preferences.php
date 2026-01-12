<?php
/**
 * Template Name: Email Preferences
 *
 * Onboarding page for new users to set email preferences after registration.
 * Users are redirected here if they haven't completed preference setup.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Must be logged in
if ( ! is_user_logged_in() ) {
    wp_redirect( home_url( '/?login=1' ) );
    exit;
}

// If preferences already set, redirect away
$user_id = get_current_user_id();
$preferences_set = get_user_meta( $user_id, 'email_preferences_set', true ) === '1';

if ( $preferences_set ) {
    $redirect = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : home_url();
    wp_safe_redirect( $redirect );
    exit;
}

// Capture redirect URL for after save
$redirect_url = isset( $_GET['redirect'] ) ? esc_url( wp_unslash( $_GET['redirect'] ) ) : home_url();

get_header();
?>

<main class="onboarding-page">
    <div class="container container--narrow">
        <div class="onboarding-card" data-onboarding>
            <!-- Header -->
            <div class="onboarding-header">
                <div class="onboarding-icon">
                    <?php erh_the_icon( 'bell' ); ?>
                </div>
                <h1 class="onboarding-title"><?php esc_html_e( "You're almost ready to ride", 'erh' ); ?></h1>
                <p class="onboarding-subtitle">
                    <?php esc_html_e( 'Choose how you\'d like to hear from us. You can update these anytime in your account settings.', 'erh' ); ?>
                </p>
            </div>

            <!-- Form -->
            <form class="onboarding-form" data-onboarding-form>
                <input type="hidden" name="redirect" value="<?php echo esc_attr( $redirect_url ); ?>">

                <?php get_template_part( 'template-parts/onboarding/preferences' ); ?>

                <div class="form-error" data-onboarding-error hidden></div>

                <button type="submit" class="btn btn-primary btn-lg btn-block" data-onboarding-submit>
                    <span class="btn-text"><?php esc_html_e( 'Save & Start Riding', 'erh' ); ?></span>
                    <span class="btn-loading" hidden>
                        <?php erh_the_icon( 'loader' ); ?>
                    </span>
                </button>
            </form>

            <p class="onboarding-skip">
                <a href="<?php echo esc_url( $redirect_url ); ?>" data-skip-preferences>
                    <?php esc_html_e( 'Skip for now', 'erh' ); ?>
                </a>
            </p>
        </div>
    </div>
</main>

<?php get_footer(); ?>
