<?php
/**
 * Header Navigation Helpers
 *
 * Data retrieval and render functions for the dynamic header nav.
 * Both desktop (header.php) and mobile (mobile-menu.php) menus
 * render from the same data source returned by erh_get_header_nav().
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get header navigation items from ACF options or fallback defaults.
 *
 * @return array<int, array{nav_label: string, nav_type: string, nav_url: string, mega_title?: string, mega_grid_items?: array, mega_footer_links?: array, dropdown_items?: array}>
 */
function erh_get_header_nav(): array {
    if ( function_exists( 'get_field' ) ) {
        $items = get_field( 'header_nav_items', 'option' );
        if ( ! empty( $items ) ) {
            return $items;
        }
    }

    return erh_get_default_header_nav();
}

/**
 * Default navigation matching the current hardcoded structure.
 *
 * @return array
 */
function erh_get_default_header_nav(): array {
    return array(
        // E-scooters (mega)
        array(
            'nav_label'        => 'E-scooters',
            'nav_type'         => 'mega',
            'nav_url'          => home_url( '/e-scooters/' ),
            'mega_title'       => 'Electric scooters',
            'mega_grid_items'  => array(
                array(
                    'icon'        => 'search',
                    'title'       => 'Product finder',
                    'description' => '200+ scooters',
                    'url'         => home_url( '/e-scooters/finder/' ),
                ),
                array(
                    'icon'        => 'star',
                    'title'       => 'Reviews',
                    'description' => '58 in-depth reviews',
                    'url'         => home_url( '/e-scooter-reviews/' ),
                ),
                array(
                    'icon'        => 'grid',
                    'title'       => 'H2H Compare',
                    'description' => 'Side-by-side specs',
                    'url'         => home_url( '/e-scooters/compare/' ),
                ),
                array(
                    'icon'        => 'book',
                    'title'       => 'Buying guides',
                    'description' => 'Expert recommendations',
                    'url'         => home_url( '/buying-guides/e-scooters/' ),
                ),
            ),
            'mega_footer_links' => array(
                array(
                    'icon'  => 'percent',
                    'title' => 'Best deals',
                    'url'   => home_url( '/deals/e-scooters/' ),
                ),
                array(
                    'icon'  => 'tag',
                    'title' => 'Coupon codes',
                    'url'   => home_url( '/coupons/e-scooters/' ),
                ),
            ),
        ),
        // E-bikes (mega)
        array(
            'nav_label'        => 'E-bikes',
            'nav_type'         => 'mega',
            'nav_url'          => home_url( '/e-bikes/' ),
            'mega_title'       => 'Electric bikes',
            'mega_grid_items'  => array(
                array(
                    'icon'        => 'search',
                    'title'       => 'Product finder',
                    'description' => '80+ e-bikes',
                    'url'         => home_url( '/e-bikes/finder/' ),
                ),
                array(
                    'icon'        => 'star',
                    'title'       => 'Reviews',
                    'description' => '12 in-depth reviews',
                    'url'         => home_url( '/e-bike-reviews/' ),
                ),
                array(
                    'icon'        => 'grid',
                    'title'       => 'H2H Compare',
                    'description' => 'Side-by-side specs',
                    'url'         => home_url( '/e-bikes/compare/' ),
                ),
                array(
                    'icon'        => 'book',
                    'title'       => 'Buying guides',
                    'description' => 'Expert recommendations',
                    'url'         => home_url( '/buying-guides/e-bikes/' ),
                ),
            ),
            'mega_footer_links' => array(
                array(
                    'icon'  => 'percent',
                    'title' => 'Best deals',
                    'url'   => home_url( '/deals/e-bikes/' ),
                ),
                array(
                    'icon'  => 'tag',
                    'title' => 'Coupon codes',
                    'url'   => home_url( '/coupons/e-bikes/' ),
                ),
            ),
        ),
        // EUCs (plain link)
        array(
            'nav_label' => 'EUCs',
            'nav_type'  => 'link',
            'nav_url'   => home_url( '/eucs/' ),
        ),
        // More (simple dropdown)
        array(
            'nav_label'      => 'More',
            'nav_type'       => 'dropdown',
            'nav_url'        => '',
            'dropdown_items' => array(
                array(
                    'icon'          => 'hoverboard',
                    'title'         => 'Hoverboards',
                    'description'   => '10 reviews',
                    'url'           => home_url( '/hoverboards/' ),
                    'divider_after' => false,
                ),
                array(
                    'icon'          => 'eskate',
                    'title'         => 'E-Skateboards',
                    'description'   => '8 reviews',
                    'url'           => home_url( '/e-skateboards/' ),
                    'divider_after' => false,
                ),
                array(
                    'icon'          => 'book',
                    'title'         => 'Skating',
                    'description'   => '6 guides',
                    'url'           => home_url( '/skating/' ),
                    'divider_after' => true,
                ),
                array(
                    'icon'          => 'book',
                    'title'         => 'All buying guides',
                    'description'   => '14 guides',
                    'url'           => home_url( '/buying-guides/' ),
                    'divider_after' => false,
                ),
            ),
        ),
    );
}

// =============================================================================
// DESKTOP RENDER FUNCTIONS
// =============================================================================

/**
 * Render a mega dropdown nav item.
 *
 * @param array $item Nav item data.
 * @param int   $index Item index (for unique IDs).
 */
function erh_render_mega_dropdown( array $item, int $index ): void {
    $dropdown_id = 'dropdown-mega-' . $index;
    $label       = $item['nav_label'] ?? '';
    $title       = $item['mega_title'] ?? $label;
    $view_all    = $item['nav_url'] ?? '';
    $grid_items  = $item['mega_grid_items'] ?? array();
    $footer      = $item['mega_footer_links'] ?? array();
    ?>
    <div class="nav-item" data-dropdown>
        <button type="button" class="nav-link" aria-expanded="false" aria-haspopup="true" aria-controls="<?php echo esc_attr( $dropdown_id ); ?>">
            <?php echo esc_html( $label ); ?>
            <?php erh_the_icon( 'chevron-down', 'chevron' ); ?>
        </button>
        <div class="dropdown mega-dropdown" id="<?php echo esc_attr( $dropdown_id ); ?>" role="menu" aria-label="<?php echo esc_attr( $label . ' submenu' ); ?>">
            <div class="mega-dropdown-header">
                <span class="mega-dropdown-title"><?php echo esc_html( $title ); ?></span>
                <?php if ( $view_all ) : ?>
                    <a href="<?php echo esc_url( $view_all ); ?>" role="menuitem">
                        <?php esc_html_e( 'View all', 'erh' ); ?>
                        <?php erh_the_icon( 'arrow-right' ); ?>
                    </a>
                <?php endif; ?>
            </div>
            <?php if ( $grid_items ) : ?>
                <div class="mega-grid" role="group" aria-label="<?php echo esc_attr( $label . ' pages' ); ?>">
                    <?php foreach ( $grid_items as $gi ) : ?>
                        <a href="<?php echo esc_url( $gi['url'] ?? '' ); ?>" class="mega-item" role="menuitem">
                            <div class="mega-icon" aria-hidden="true">
                                <?php erh_the_icon( $gi['icon'] ?? 'star' ); ?>
                            </div>
                            <div class="mega-content">
                                <span class="mega-item-title"><?php echo esc_html( $gi['title'] ?? '' ); ?></span>
                                <span><?php echo esc_html( $gi['description'] ?? '' ); ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ( $footer ) : ?>
                <div class="mega-footer" role="group" aria-label="<?php esc_attr_e( 'Quick links', 'erh' ); ?>">
                    <?php foreach ( $footer as $fl ) : ?>
                        <a href="<?php echo esc_url( $fl['url'] ?? '' ); ?>" class="mega-footer-link" role="menuitem">
                            <?php erh_the_icon( $fl['icon'] ?? 'star' ); ?>
                            <?php echo esc_html( $fl['title'] ?? '' ); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Render a simple dropdown nav item.
 *
 * @param array $item Nav item data.
 * @param int   $index Item index (for unique IDs).
 */
function erh_render_simple_dropdown( array $item, int $index ): void {
    $dropdown_id = 'dropdown-simple-' . $index;
    $label       = $item['nav_label'] ?? '';
    $items       = $item['dropdown_items'] ?? array();
    ?>
    <div class="nav-item" data-dropdown>
        <button type="button" class="nav-link" aria-expanded="false" aria-haspopup="true" aria-controls="<?php echo esc_attr( $dropdown_id ); ?>">
            <?php echo esc_html( $label ); ?>
            <?php erh_the_icon( 'chevron-down', 'chevron' ); ?>
        </button>
        <div class="dropdown" id="<?php echo esc_attr( $dropdown_id ); ?>" role="menu" aria-label="<?php echo esc_attr( $label . ' options' ); ?>">
            <?php foreach ( $items as $di ) : ?>
                <a href="<?php echo esc_url( $di['url'] ?? '' ); ?>" class="dropdown-item" role="menuitem">
                    <div class="dropdown-icon" aria-hidden="true">
                        <?php erh_the_icon( $di['icon'] ?? 'star' ); ?>
                    </div>
                    <div class="dropdown-content">
                        <span class="dropdown-item-title"><?php echo esc_html( $di['title'] ?? '' ); ?></span>
                        <span><?php echo esc_html( $di['description'] ?? '' ); ?></span>
                    </div>
                </a>
                <?php if ( ! empty( $di['divider_after'] ) ) : ?>
                    <div class="dropdown-divider" role="separator"></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}

// =============================================================================
// MOBILE RENDER FUNCTIONS
// =============================================================================

/**
 * Render a single mobile nav item (any type).
 *
 * @param array $item Nav item data.
 */
function erh_render_mobile_nav_item( array $item ): void {
    $type  = $item['nav_type'] ?? 'link';
    $label = $item['nav_label'] ?? '';

    if ( 'link' === $type ) {
        ?>
        <div class="mobile-nav-item">
            <a href="<?php echo esc_url( $item['nav_url'] ?? '' ); ?>" class="mobile-nav-link">
                <?php echo esc_html( $label ); ?>
            </a>
        </div>
        <?php
        return;
    }

    if ( 'mega' === $type ) {
        erh_render_mobile_mega_item( $item );
        return;
    }

    if ( 'dropdown' === $type ) {
        erh_render_mobile_dropdown_item( $item );
    }
}

/**
 * Render a mega-type mobile nav item with accordion submenu.
 *
 * @param array $item Nav item data.
 */
function erh_render_mobile_mega_item( array $item ): void {
    $label      = $item['nav_label'] ?? '';
    $view_all   = $item['nav_url'] ?? '';
    $title      = $item['mega_title'] ?? $label;
    $grid_items = $item['mega_grid_items'] ?? array();
    $footer     = $item['mega_footer_links'] ?? array();
    ?>
    <div class="mobile-nav-item" data-has-submenu>
        <button class="mobile-nav-link" aria-expanded="false">
            <?php echo esc_html( $label ); ?>
            <?php erh_the_icon( 'chevron-down' ); ?>
        </button>
        <div class="mobile-submenu">
            <div class="mobile-submenu-inner">
                <?php if ( $view_all ) : ?>
                    <a href="<?php echo esc_url( $view_all ); ?>" class="mobile-submenu-viewall">
                        <?php
                        /* translators: %s: navigation section title */
                        printf( esc_html__( 'View all %s', 'erh' ), esc_html( strtolower( $title ) ) );
                        ?>
                        <?php erh_the_icon( 'arrow-right' ); ?>
                    </a>
                <?php endif; ?>
                <?php foreach ( $grid_items as $gi ) : ?>
                    <a href="<?php echo esc_url( $gi['url'] ?? '' ); ?>" class="mobile-submenu-item">
                        <div class="mobile-submenu-icon">
                            <?php erh_the_icon( $gi['icon'] ?? 'star' ); ?>
                        </div>
                        <div class="mobile-submenu-content">
                            <span class="mobile-submenu-title"><?php echo esc_html( $gi['title'] ?? '' ); ?></span>
                            <span><?php echo esc_html( $gi['description'] ?? '' ); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
                <?php if ( $footer ) : ?>
                    <div class="mobile-submenu-secondary">
                        <?php foreach ( $footer as $fl ) : ?>
                            <a href="<?php echo esc_url( $fl['url'] ?? '' ); ?>" class="mobile-submenu-tag">
                                <?php erh_the_icon( $fl['icon'] ?? 'star' ); ?>
                                <?php echo esc_html( $fl['title'] ?? '' ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Render a dropdown-type mobile nav item with accordion submenu.
 *
 * @param array $item Nav item data.
 */
function erh_render_mobile_dropdown_item( array $item ): void {
    $label = $item['nav_label'] ?? '';
    $items = $item['dropdown_items'] ?? array();
    ?>
    <div class="mobile-nav-item" data-has-submenu>
        <button class="mobile-nav-link" aria-expanded="false">
            <?php echo esc_html( $label ); ?>
            <?php erh_the_icon( 'chevron-down' ); ?>
        </button>
        <div class="mobile-submenu">
            <div class="mobile-submenu-inner">
                <?php foreach ( $items as $di ) : ?>
                    <a href="<?php echo esc_url( $di['url'] ?? '' ); ?>" class="mobile-submenu-item">
                        <div class="mobile-submenu-icon">
                            <?php erh_the_icon( $di['icon'] ?? 'star' ); ?>
                        </div>
                        <div class="mobile-submenu-content">
                            <span class="mobile-submenu-title"><?php echo esc_html( $di['title'] ?? '' ); ?></span>
                            <span><?php echo esc_html( $di['description'] ?? '' ); ?></span>
                        </div>
                    </a>
                    <?php if ( ! empty( $di['divider_after'] ) ) : ?>
                        <div class="mobile-submenu-divider"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}
