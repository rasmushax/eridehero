<?php
/**
 * Compare Hub Categories Section
 *
 * Category cards linking to category-specific comparison pages.
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

use ERH\CategoryConfig;

// Category data from centralized config.
$categories = CategoryConfig::get_hub_categories();

// Get product counts for each category from the product_data cache table.
global $wpdb;
$table_name = $wpdb->prefix . 'product_data';

// Get counts for all types in a single query.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$counts = $wpdb->get_results(
    "SELECT product_type, COUNT(*) as count FROM {$table_name} GROUP BY product_type",
    OBJECT_K
);

foreach ( $categories as &$cat ) {
    $cat['count'] = isset( $counts[ $cat['product_type'] ] ) ? (int) $counts[ $cat['product_type'] ]->count : 0;
}
unset( $cat );
?>

<section class="compare-hub-categories">
    <div class="container">
        <h2 class="compare-hub-section-title">Browse by Category</h2>

        <div class="compare-category-grid">
            <?php foreach ( $categories as $cat ) : ?>
                <a href="<?php echo esc_url( home_url( '/compare/' . $cat['slug'] . '/' ) ); ?>" class="compare-category-card">
                    <div class="compare-category-card-icon">
                        <?php erh_the_icon( $cat['icon'], $cat['icon_class'] ); ?>
                    </div>
                    <div class="compare-category-card-content">
                        <h3 class="compare-category-card-title"><?php echo esc_html( $cat['name'] ); ?></h3>
                        <p class="compare-category-card-desc"><?php echo esc_html( $cat['count'] ); ?> products</p>
                    </div>
                    <span class="compare-category-card-arrow">
                        <?php erh_the_icon( 'chevron-right' ); ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
