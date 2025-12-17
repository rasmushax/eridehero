<?php
/**
 * Product Pros and Cons
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$pros = get_field( 'pros' );
$cons = get_field( 'cons' );

if ( empty( $pros ) && empty( $cons ) ) {
    return;
}
?>
<div class="pros-cons">
    <?php if ( ! empty( $pros ) ) : ?>
        <div class="pros">
            <h3 class="pros-title">
                <?php erh_the_icon( 'check' ); ?>
                <?php esc_html_e( 'Pros', 'erh' ); ?>
            </h3>
            <ul class="pros-list">
                <?php foreach ( $pros as $pro ) : ?>
                    <li><?php echo esc_html( $pro['item'] ?? $pro ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $cons ) ) : ?>
        <div class="cons">
            <h3 class="cons-title">
                <?php erh_the_icon( 'x' ); ?>
                <?php esc_html_e( 'Cons', 'erh' ); ?>
            </h3>
            <ul class="cons-list">
                <?php foreach ( $cons as $con ) : ?>
                    <li><?php echo esc_html( $con['item'] ?? $con ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
