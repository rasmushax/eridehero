<?php
/**
 * Product Quick Take
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$quick_take = get_field( 'quick_take' );

if ( ! $quick_take ) {
    return;
}
?>
<div class="quick-take">
    <h2 class="quick-take-title"><?php esc_html_e( 'Quick take', 'erh' ); ?></h2>
    <div class="quick-take-content">
        <?php echo wp_kses_post( $quick_take ); ?>
    </div>
</div>
