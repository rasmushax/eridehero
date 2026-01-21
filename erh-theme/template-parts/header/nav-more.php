<?php
/**
 * More Dropdown Navigation
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!-- More Dropdown -->
<div class="nav-item" data-dropdown>
    <button type="button" class="nav-link" aria-expanded="false" aria-haspopup="true" aria-controls="dropdown-more">
        <?php esc_html_e( 'More', 'erh' ); ?>
        <?php erh_the_icon( 'chevron-down', 'chevron' ); ?>
    </button>
    <div class="dropdown" id="dropdown-more" role="menu" aria-label="<?php esc_attr_e( 'More options', 'erh' ); ?>">
        <a href="<?php echo esc_url( home_url( '/hoverboards/' ) ); ?>" class="dropdown-item" role="menuitem">
            <div class="dropdown-icon" aria-hidden="true">
                <?php erh_the_icon( 'hoverboard' ); ?>
            </div>
            <div class="dropdown-content">
                <span class="dropdown-item-title"><?php esc_html_e( 'Hoverboards', 'erh' ); ?></span>
                <span><?php esc_html_e( '10 reviews', 'erh' ); ?></span>
            </div>
        </a>
        <a href="<?php echo esc_url( home_url( '/e-skateboards/' ) ); ?>" class="dropdown-item" role="menuitem">
            <div class="dropdown-icon" aria-hidden="true">
                <?php erh_the_icon( 'eskate' ); ?>
            </div>
            <div class="dropdown-content">
                <span class="dropdown-item-title"><?php esc_html_e( 'E-Skateboards', 'erh' ); ?></span>
                <span><?php esc_html_e( '8 reviews', 'erh' ); ?></span>
            </div>
        </a>
        <a href="<?php echo esc_url( home_url( '/skating/' ) ); ?>" class="dropdown-item" role="menuitem">
            <div class="dropdown-icon" aria-hidden="true">
                <?php erh_the_icon( 'book' ); ?>
            </div>
            <div class="dropdown-content">
                <span class="dropdown-item-title"><?php esc_html_e( 'Skating', 'erh' ); ?></span>
                <span><?php esc_html_e( '6 guides', 'erh' ); ?></span>
            </div>
        </a>
        <div class="dropdown-divider" role="separator"></div>
        <a href="<?php echo esc_url( home_url( '/buying-guides/' ) ); ?>" class="dropdown-item" role="menuitem">
            <div class="dropdown-icon" aria-hidden="true">
                <?php erh_the_icon( 'book' ); ?>
            </div>
            <div class="dropdown-content">
                <span class="dropdown-item-title"><?php esc_html_e( 'All buying guides', 'erh' ); ?></span>
                <span><?php esc_html_e( '14 guides', 'erh' ); ?></span>
            </div>
        </a>
    </div>
</div>
