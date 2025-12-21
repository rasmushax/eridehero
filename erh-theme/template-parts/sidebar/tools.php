<?php
/**
 * Sidebar Tools Component
 *
 * Quick links to category-specific tools: Finder, Deals, Compare.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'product_type' => string - Product type (e.g., 'Electric Scooter')
 *   'category_slug' => string - Category slug (e.g., 'e-scooters')
 *   'category_name' => string - Short category name (e.g., 'E-Scooters')
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get arguments
$product_type  = $args['product_type'] ?? '';
$category_slug = $args['category_slug'] ?? '';
$category_name = $args['category_name'] ?? '';

// Bail if no category info
if ( empty( $category_slug ) ) {
    return;
}

// $category_name comes in as singular (e.g., "E-Scooter" from erh_get_product_type_short_name)
$singular_name = $category_name;

// Build tool URLs
$finder_url  = home_url( '/' . $category_slug . '/finder/' );
$deals_url   = home_url( '/' . $category_slug . '/deals/' );
$compare_url = home_url( '/' . $category_slug . '/compare/' );

// Get dynamic product count for this category
$product_count = 0;
if ( ! empty( $product_type ) ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_data';

    // Try cache table first (faster, already indexed by product_type)
    $table_exists = $wpdb->get_var( $wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    ) );

    if ( $table_exists ) {
        $product_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE product_type = %s",
            $product_type
        ) );
    }

    // Fallback to taxonomy term count if cache table is empty
    if ( $product_count === 0 ) {
        // product_type is a taxonomy, convert "Electric Scooter" → "electric-scooter"
        $taxonomy_slug = sanitize_title( $product_type );
        $term          = get_term_by( 'slug', $taxonomy_slug, 'product_type' );

        if ( $term && ! is_wp_error( $term ) ) {
            $product_count = (int) $term->count;
        }
    }
}

// Get dynamic deals count for this category
$deals_count = 0;
if ( ! empty( $product_type ) && class_exists( 'ERH\\Pricing\\DealsFinder' ) ) {
    try {
        $deals_finder = new \ERH\Pricing\DealsFinder();
        $deal_counts  = $deals_finder->get_deal_counts();
        $deals_count  = $deal_counts[ $product_type ] ?? 0;
    } catch ( \Exception $e ) {
        // Silently fail - deals count stays at 0
    }
}

// Build finder description
if ( $product_count > 0 ) {
    $finder_description = sprintf( 'Browse & filter %d+ models', $product_count );
} else {
    $finder_description = 'Browse, filter & sort';
}

// Build deals description
if ( $deals_count > 0 ) {
    $deals_description = sprintf( '%d deal%s live now', $deals_count, $deals_count === 1 ? '' : 's' );
} else {
    $deals_description = "Today's best prices";
}
?>

<div class="sidebar-section">
    <h3 class="sidebar-title">Tools</h3>
    <div class="sidebar-tools">
        <a href="<?php echo esc_url( $finder_url ); ?>" class="sidebar-tool-link">
            <span class="sidebar-tool-icon">
                <?php erh_the_icon( 'search' ); ?>
            </span>
            <span class="sidebar-tool-text">
                <strong><?php echo esc_html( $singular_name ); ?> finder</strong>
                <span><?php echo esc_html( $finder_description ); ?></span>
            </span>
        </a>
        <a href="<?php echo esc_url( $deals_url ); ?>" class="sidebar-tool-link">
            <span class="sidebar-tool-icon">
                <?php erh_the_icon( 'percent' ); ?>
            </span>
            <span class="sidebar-tool-text">
                <strong><?php echo esc_html( $singular_name ); ?> deals</strong>
                <span><?php echo esc_html( $deals_description ); ?></span>
            </span>
        </a>
        <a href="<?php echo esc_url( $compare_url ); ?>" class="sidebar-tool-link">
            <span class="sidebar-tool-icon">
                <?php erh_the_icon( 'grid' ); ?>
            </span>
            <span class="sidebar-tool-text">
                <strong><?php echo esc_html( $singular_name ); ?> compare</strong>
                <span>Head-to-head specs</span>
            </span>
        </a>
    </div>
</div>
