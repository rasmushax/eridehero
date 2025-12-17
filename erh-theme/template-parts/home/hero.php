<?php
/**
 * Homepage Hero Section
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<section class="hero">
    <div class="container">
        <div class="hero-grid">
            <div class="hero-content">
                <span class="hero-eyebrow"><?php esc_html_e( 'The electric mobility hub', 'erh' ); ?></span>
                <h1 class="hero-title"><?php esc_html_e( 'Find your perfect ride', 'erh' ); ?></h1>
                <p class="hero-subtitle"><?php esc_html_e( 'Reviews, guides and deals for e-scooters, e-bikes, and more.', 'erh' ); ?></p>

                <div class="hero-links">
                    <a href="<?php echo esc_url( home_url( '/e-scooters/' ) ); ?>" class="hero-link">
                        <?php erh_the_icon( 'escooter' ); ?>
                        <span><?php esc_html_e( 'E-scooters', 'erh' ); ?></span>
                        <?php erh_the_icon( 'arrow-right' ); ?>
                    </a>
                    <a href="<?php echo esc_url( home_url( '/e-bikes/' ) ); ?>" class="hero-link">
                        <?php erh_the_icon( 'ebike' ); ?>
                        <span><?php esc_html_e( 'E-bikes', 'erh' ); ?></span>
                        <?php erh_the_icon( 'arrow-right' ); ?>
                    </a>
                    <a href="<?php echo esc_url( home_url( '/eucs/' ) ); ?>" class="hero-link">
                        <?php erh_the_icon( 'euc' ); ?>
                        <span><?php esc_html_e( 'EUCs', 'erh' ); ?></span>
                        <?php erh_the_icon( 'arrow-right' ); ?>
                    </a>
                </div>
            </div>

            <div class="hero-finder">
                <?php get_template_part( 'template-parts/components/quick-finder' ); ?>
            </div>
        </div>
    </div>
    <div class="hero-orbs" aria-hidden="true"></div>
</section>
