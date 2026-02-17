<?php
/**
 * Theme Footer
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
</main><!-- #main-content -->

<?php if ( ! is_user_logged_in() ) :
    // Get CTA section settings
    $cta_title       = get_field( 'cta_title', 'option' ) ?: 'Unlock all ERideHero features for free';
    $cta_pill        = get_field( 'cta_pill', 'option' ) ?: '1,200+ members';
    $cta_benefits    = get_field( 'cta_benefits', 'option' );
    $cta_button_text = get_field( 'cta_button_text', 'option' ) ?: 'Sign up free';
    $cta_button_page = get_field( 'cta_button_page', 'option' );
    $cta_button_url  = $cta_button_page ? get_permalink( $cta_button_page ) : home_url( '/login/' ) . '#register';

    // Default benefits if none configured or not an array
    if ( empty( $cta_benefits ) || ! is_array( $cta_benefits ) ) {
        $cta_benefits = array(
            array( 'text' => 'Best deals weekly' ),
            array( 'text' => 'Price drop alerts' ),
            array( 'text' => 'Member-only discounts' ),
        );
    }
?>
<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="cta-card">
            <div class="cta-orb" aria-hidden="true"></div>
            <div class="cta-content">
                <div class="cta-text">
                    <div class="cta-header">
                        <h2><?php echo esc_html( $cta_title ); ?></h2>
                        <span class="cta-pill"><?php echo esc_html( $cta_pill ); ?></span>
                    </div>
                    <ul class="cta-benefits">
                        <?php foreach ( $cta_benefits as $benefit ) : ?>
                            <li>
                                <?php erh_the_icon( 'check' ); ?>
                                <?php echo esc_html( $benefit['text'] ); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="cta-action">
                    <a href="<?php echo esc_url( $cta_button_url ); ?>" class="btn btn-primary btn-lg">
                        <?php echo esc_html( $cta_button_text ); ?>
                        <?php erh_the_icon( 'arrow-right' ); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<footer class="footer" role="contentinfo">
    <div class="container">
        <div class="footer-top">
            <?php
            wp_nav_menu( array(
                'theme_location'    => 'footer-categories',
                'container'         => 'nav',
                'container_class'   => 'footer-categories',
                'container_aria_label' => __( 'Product categories', 'erh' ),
                'items_wrap'        => '%3$s',
                'depth'             => 1,
                'walker'            => new ERH_Flat_Menu_Walker(),
                'fallback_cb'       => false,
            ) );
            ?>
            <div class="footer-socials">
                <?php
                $socials = erh_get_social_links();

                foreach ( $socials as $platform => $url ) :
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="footer-social" aria-label="<?php echo esc_attr( ucfirst( $platform ) ); ?>" target="_blank" rel="noopener noreferrer">
                        <?php erh_the_icon( $platform ); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-left">
                <span class="footer-copyright">&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></span>
                <span class="footer-separator">&middot;</span>
                <?php
                wp_nav_menu( array(
                    'theme_location'    => 'footer-legal',
                    'container'         => 'nav',
                    'container_class'   => 'footer-legal',
                    'container_aria_label' => __( 'Legal links', 'erh' ),
                    'items_wrap'        => '%3$s',
                    'depth'             => 1,
                    'walker'            => new ERH_Flat_Menu_Walker(),
                    'fallback_cb'       => false,
                ) );
                ?>
            </div>
            <div class="footer-right">
                <?php
                // Region picker - available for all users
                $flag_base = ERH_THEME_URI . '/assets/images/countries/';
                $regions   = array(
                    'US' => array(
                        'label' => 'United States (USD)',
                        'flag'  => 'united-states.svg',
                    ),
                    'GB' => array(
                        'label' => 'United Kingdom (GBP)',
                        'flag'  => 'united-kingdom.svg',
                    ),
                    'EU' => array(
                        'label' => 'Europe (EUR)',
                        'flag'  => 'european-union.svg',
                    ),
                    'CA' => array(
                        'label' => 'Canada (CAD)',
                        'flag'  => 'canada.svg',
                    ),
                    'AU' => array(
                        'label' => 'Australia (AUD)',
                        'flag'  => 'australia.svg',
                    ),
                );
                ?>
                <?php
                wp_nav_menu( array(
                    'theme_location'    => 'footer',
                    'container'         => 'nav',
                    'container_class'   => 'footer-links',
                    'container_aria_label' => __( 'Footer navigation', 'erh' ),
                    'items_wrap'        => '%3$s',
                    'depth'             => 1,
                    'walker'            => new ERH_Flat_Menu_Walker(),
                    'fallback_cb'       => false,
                ) );
                ?>

                <div class="footer-region" data-region-picker-wrapper>
                    <button type="button" class="footer-region-trigger" data-region-picker-trigger aria-expanded="false" aria-haspopup="listbox">
                        <img src="<?php echo esc_url( $flag_base . 'united-states.svg' ); ?>" alt="" class="footer-region-flag" data-current-flag width="15" height="15">
                        <span class="footer-region-label" data-current-region>United States (USD)</span>
                        <?php erh_the_icon( 'chevron-down', 'footer-region-chevron' ); ?>
                    </button>

                    <div class="footer-region-dropdown" data-region-picker role="listbox" hidden>
                        <?php foreach ( $regions as $code => $region ) : ?>
                        <button type="button" class="footer-region-option" data-region="<?php echo esc_attr( $code ); ?>" role="option">
                            <img src="<?php echo esc_url( $flag_base . $region['flag'] ); ?>" alt="" class="footer-region-flag" width="15" height="15">
                            <span><?php echo esc_html( $region['label'] ); ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
