<?php
/**
 * Theme Header
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php get_template_part( 'template-parts/components/svg-sprite' ); ?>

<!-- Skip to main content link for keyboard users -->
<a href="#main-content" class="skip-link"><?php esc_html_e( 'Skip to main content', 'erh' ); ?></a>

<!-- HEADER -->
<header class="header" role="banner">
    <div class="header-inner">
        <!-- Logo -->
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> - Go to homepage">
            <?php echo erh_get_logo( 'logo-img' ); ?>
            <span class="sr-only"><?php bloginfo( 'name' ); ?></span>
        </a>

        <!-- Main Navigation -->
        <nav class="main-nav" role="navigation" aria-label="<?php esc_attr_e( 'Main navigation', 'erh' ); ?>">
            <?php get_template_part( 'template-parts/header/nav-escooters' ); ?>
            <?php get_template_part( 'template-parts/header/nav-ebikes' ); ?>

            <!-- EUCs -->
            <div class="nav-item">
                <a href="<?php echo esc_url( home_url( '/eucs/' ) ); ?>" class="nav-link"><?php esc_html_e( 'EUCs', 'erh' ); ?></a>
            </div>

            <?php get_template_part( 'template-parts/header/nav-more' ); ?>
        </nav>

        <!-- Header Right -->
        <div class="header-right">
            <?php get_template_part( 'template-parts/header/search' ); ?>

            <?php if ( is_user_logged_in() ) : ?>
                <a href="<?php echo esc_url( home_url( '/account/' ) ); ?>" class="btn-login">
                    <?php erh_the_icon( 'user' ); ?>
                    <?php esc_html_e( 'Account', 'erh' ); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( home_url( '/login/' ) ); ?>" class="btn-login">
                    <?php erh_the_icon( 'user' ); ?>
                    <?php esc_html_e( 'Log in', 'erh' ); ?>
                </a>
                <a href="<?php echo esc_url( home_url( '/register/' ) ); ?>" class="btn-signup">
                    <?php esc_html_e( 'Sign up free', 'erh' ); ?>
                </a>
            <?php endif; ?>

            <!-- Mobile menu toggle -->
            <button class="menu-toggle" aria-label="<?php esc_attr_e( 'Open menu', 'erh' ); ?>" aria-expanded="false" aria-controls="mobile-menu">
                <div class="menu-toggle-inner">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </button>
        </div>
    </div>
</header>

<?php get_template_part( 'template-parts/header/mobile-menu' ); ?>

<main id="main-content" class="site-main">
