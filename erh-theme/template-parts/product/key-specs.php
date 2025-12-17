<?php
/**
 * Product Key Specs
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get key specs based on product type
$product_type = erh_get_product_type();
$specs = array();

// Common specs for e-scooters
if ( $product_type === 'Electric Scooter' ) {
    $specs = array(
        array(
            'label' => __( 'Top speed', 'erh' ),
            'value' => get_field( 'manufacturer_top_speed' ),
            'unit'  => 'mph',
        ),
        array(
            'label' => __( 'Range', 'erh' ),
            'value' => get_field( 'manufacturer_range' ),
            'unit'  => 'mi',
        ),
        array(
            'label' => __( 'Weight', 'erh' ),
            'value' => get_field( 'weight' ),
            'unit'  => 'lbs',
        ),
        array(
            'label' => __( 'Battery', 'erh' ),
            'value' => get_field( 'battery_capacity' ),
            'unit'  => 'Wh',
        ),
    );
}

// Filter out empty specs
$specs = array_filter( $specs, function( $spec ) {
    return ! empty( $spec['value'] );
} );

if ( empty( $specs ) ) {
    return;
}
?>
<section class="key-specs">
    <h2 class="key-specs-title"><?php esc_html_e( 'Key specs', 'erh' ); ?></h2>
    <div class="key-specs-grid">
        <?php foreach ( $specs as $spec ) : ?>
            <div class="key-spec-card">
                <span class="key-spec-label"><?php echo esc_html( $spec['label'] ); ?></span>
                <span class="key-spec-value">
                    <?php echo esc_html( $spec['value'] ); ?>
                    <?php if ( ! empty( $spec['unit'] ) ) : ?>
                        <span class="key-spec-unit"><?php echo esc_html( $spec['unit'] ); ?></span>
                    <?php endif; ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
