<?php
/**
 * Homepage As Seen On Section
 *
 * Displays a scrolling marquee of publication logos.
 * Logos can be managed via ACF Options page (Settings > Theme Settings > As Seen On)
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get logos from ACF options, or use defaults
$section_label = get_field( 'as_seen_on_label', 'option' ) ?: __( 'Our Work Has Been Featured In', 'erh' );
$logos         = get_field( 'as_seen_on_logos', 'option' );

// Default logos if ACF not set up or empty
if ( empty( $logos ) ) {
    $logos = array(
        array( 'name' => 'Forbes', 'logo' => ERH_THEME_URI . '/assets/images/logos/forbes.svg' ),
        array( 'name' => 'Wired', 'logo' => ERH_THEME_URI . '/assets/images/logos/wired.svg' ),
        array( 'name' => 'Axios', 'logo' => ERH_THEME_URI . '/assets/images/logos/axios.svg' ),
        array( 'name' => 'Yahoo Finance', 'logo' => ERH_THEME_URI . '/assets/images/logos/yahoo-finance.svg' ),
        array( 'name' => 'MSN', 'logo' => ERH_THEME_URI . '/assets/images/logos/msn.svg' ),
        array( 'name' => 'The Seattle Times', 'logo' => ERH_THEME_URI . '/assets/images/logos/the-seattle-times.svg' ),
        array( 'name' => 'Fox NY', 'logo' => ERH_THEME_URI . '/assets/images/logos/fox-ny.svg' ),
        array( 'name' => 'RetailMeNot', 'logo' => ERH_THEME_URI . '/assets/images/logos/retailmenot.svg' ),
    );
}

// Don't render if no logos
if ( empty( $logos ) ) {
    return;
}
?>
<section class="as-seen-on">
    <div class="container">
        <div class="as-seen-on-label"><?php echo esc_html( $section_label ); ?></div>
        <div class="logo-marquee">
            <div class="logo-marquee-track">
                <?php
                // Output logos twice for seamless infinite scroll
                for ( $i = 0; $i < 2; $i++ ) :
                    foreach ( $logos as $item ) :
                        // Handle both ACF format (image array) and default format (URL string)
                        $logo_url  = is_array( $item['logo'] ) ? $item['logo']['url'] : $item['logo'];
                        $logo_name = $item['name'] ?? '';
                        ?>
                        <span class="logo-marquee-item">
                            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $logo_name ); ?>">
                        </span>
                        <?php
                    endforeach;
                endfor;
                ?>
            </div>
        </div>
    </div>
</section>
