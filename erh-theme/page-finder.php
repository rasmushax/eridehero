<?php
/**
 * Template Name: Product Finder
 *
 * Product finder with filters and comparison.
 * Loads from pre-generated JSON files for performance.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load finder configuration.
require_once get_template_directory() . '/inc/finder-config.php';

get_header();

// Get product type from query var or page slug.
$product_type_slug = get_query_var( 'product_type', '' );
if ( empty( $product_type_slug ) ) {
    // Try to get from page slug (e.g., escooter-finder → escooter).
    $page_slug = get_post_field( 'post_name', get_the_ID() );
    $slug_map  = erh_get_finder_slug_map();
    $json_type = $slug_map[ $page_slug ] ?? 'escooter';
} else {
    $type_map  = erh_get_finder_type_map();
    $json_type = $type_map[ $product_type_slug ] ?? 'escooter';
}

// Get page configuration.
$page_config = erh_get_finder_page_config();
$page_info   = $page_config[ $json_type ] ?? $page_config['escooter'];

// Load products from JSON file.
$json_file = WP_CONTENT_DIR . '/uploads/finder_' . $json_type . '.json';
$products  = [];

if ( file_exists( $json_file ) ) {
    $json_content = file_get_contents( $json_file );
    $products     = json_decode( $json_content, true ) ?: [];
}

// Default geo for server-side processing. JS handles geo-aware pricing client-side
// via getUserGeo() which detects region from IPInfo and updates prices dynamically.
$user_geo = 'US';

// Process products and extract filter data.
$processed = erh_process_finder_products( $products, $user_geo );
$products         = $processed['products'];
$filter_max       = $processed['filter_max'];
$filter_min       = $processed['filter_min'];
$filter_dist      = $processed['filter_dist'];
$checkbox_options = $processed['checkbox_options'];
$tristate_counts  = $processed['tristate_counts'];
$preset_counts    = $processed['preset_counts'];

// Get filter configurations (pass product type for type-aware config).
$range_config    = erh_get_range_filter_config( $json_type );
$checkbox_config = erh_get_checkbox_filter_config();
$tristate_config = erh_get_tristate_filter_config();
$group_config    = erh_get_filter_group_config( $json_type );

$product_count = count( $products );
?>

<main class="finder-page" data-finder-page data-product-type="<?php echo esc_attr( $page_info['product_type'] ); ?>">

    <!-- Page Header -->
    <section class="finder-page-header">
        <div class="container">
            <h1 class="finder-page-title"><?php echo esc_html( $page_info['title'] ); ?></h1>
            <p class="finder-page-subtitle"><?php echo esc_html( $page_info['subtitle'] ); ?></p>
        </div>
    </section>

    <!-- Finder Content -->
    <section class="finder-section">
        <div class="container">
            <!-- Filter Overlay (mobile) -->
            <div class="finder-filter-overlay" data-filter-overlay></div>

            <div class="finder-layout">

                <!-- Sidebar Filters -->
                <aside class="finder-sidebar" data-finder-sidebar>
                    <!-- Fixed header area -->
                    <div class="finder-sidebar-header">
                        <div class="finder-filters-header">
                            <h2 class="finder-filters-title">Filters</h2>
                            <button type="button" class="finder-sidebar-close" data-filter-close aria-label="Close filters">
                                <?php erh_the_icon( 'x' ); ?>
                            </button>
                        </div>

                        <!-- Filter Search -->
                        <div class="finder-filters-search" data-filter-search-container>
                            <?php erh_the_icon( 'search' ); ?>
                            <input type="text" placeholder="Find filter..." data-filter-search>
                            <button type="button" class="filter-search-clear" data-filter-search-clear aria-label="Clear search">
                                <?php erh_the_icon( 'x' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Scrollable filters area -->
                    <div class="finder-sidebar-scroll">
                        <!-- Filters Container -->
                        <div class="finder-filters" data-finder-filters>

                            <?php
                            // Shared template args for all filter rendering.
                            $filter_template_args = [
                                'range_config'     => $range_config,
                                'checkbox_config'  => $checkbox_config,
                                'tristate_config'  => $tristate_config,
                                'filter_max'       => $filter_max,
                                'filter_min'       => $filter_min,
                                'filter_dist'      => $filter_dist,
                                'checkbox_options' => $checkbox_options,
                                'tristate_counts'  => $tristate_counts,
                                'preset_counts'    => $preset_counts,
                            ];

                            foreach ( $group_config as $group_key => $group ) :
                                // Handle quick group with subgroups.
                                if ( $group['is_quick'] ?? false ) :
                                    ?>
                                    <div class="filter-group filter-group--quick" data-filter-group="<?php echo esc_attr( $group_key ); ?>">
                                        <div class="filter-group-content">
                                            <?php
                                            foreach ( $group['subgroups'] as $subgroup_key => $subgroup ) :
                                                // Check if subgroup has content.
                                                $has_content = erh_group_has_content( $subgroup, $filter_max, $checkbox_options );
                                                if ( ! $has_content ) {
                                                    continue;
                                                }
                                                ?>
                                                <div class="filter-item is-open" data-filter-item>
                                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                                        <span class="filter-item-label"><?php echo esc_html( $subgroup['title'] ); ?></span>
                                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                                    </button>
                                                    <div class="filter-item-content">
                                                        <?php
                                                        get_template_part(
                                                            'template-parts/finder/group-filters',
                                                            null,
                                                            array_merge( $filter_template_args, [
                                                                'group'          => $subgroup,
                                                                'is_collapsible' => true,
                                                            ] )
                                                        );
                                                        ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php
                                    continue;
                                endif;

                                // Check if this group has any content to show.
                                if ( ! erh_group_has_content( $group, $filter_max, $checkbox_options ) ) {
                                    continue;
                                }
                                ?>

                                <div class="filter-group" data-filter-group="<?php echo esc_attr( $group_key ); ?>">
                                    <h3 class="filter-group-title"><?php echo esc_html( $group['title'] ); ?></h3>
                                    <div class="filter-group-content">
                                        <?php
                                        get_template_part(
                                            'template-parts/finder/group-filters',
                                            null,
                                            array_merge( $filter_template_args, [
                                                'group'          => $group,
                                                'is_collapsible' => false,
                                            ] )
                                        );
                                        ?>
                                    </div>
                                </div>

                            <?php endforeach; ?>

                        </div><!-- /.finder-filters -->
                    </div><!-- /.finder-sidebar-scroll -->
                </aside>

                <!-- Main Content -->
                <div class="finder-main">

                    <!-- Toolbar -->
                    <div class="finder-toolbar">
                        <div class="finder-toolbar-left">
                            <button type="button" class="finder-filter-toggle" data-filter-toggle>
                                <?php erh_the_icon( 'sliders' ); ?>
                                Filters
                            </button>
                            <span class="finder-results-count" data-results-count>
                                <strong><?php echo esc_html( $product_count ); ?></strong> <?php echo esc_html( $page_info['short'] ); ?>s
                            </span>
                        </div>
                        <div class="finder-toolbar-center">
                            <!-- Product Search -->
                            <div class="finder-product-search" data-product-search>
                                <?php erh_the_icon( 'search' ); ?>
                                <input type="text" class="finder-search-input" placeholder="Search <?php echo esc_attr( strtolower( $page_info['short'] ) ); ?>s..." data-product-search-input aria-label="Search products">
                                <button type="button" class="finder-search-clear" data-product-search-clear aria-label="Clear search">
                                    <?php erh_the_icon( 'x' ); ?>
                                </button>
                            </div>
                        </div>
                        <div class="finder-toolbar-right">
                            <!-- Sort -->
                            <div class="finder-sort">
                                <span class="finder-sort-label" id="finder-sort-label">Sort by</span>
                                <select id="finder-sort" class="custom-select--inline custom-select--align-right" data-custom-select data-finder-sort aria-labelledby="finder-sort-label">
                                    <option value="popularity">Most popular</option>
                                    <option value="price-asc">Price: Low to High</option>
                                    <option value="price-desc">Price: High to Low</option>
                                    <option value="deals">Best deals</option>
                                    <option value="name">Name A-Z</option>
                                </select>
                            </div>

                            <!-- View Toggle -->
                            <div class="view-toggle" role="radiogroup" aria-label="View mode">
                                <button class="view-toggle-btn is-active" data-view="grid" aria-label="Grid view" aria-pressed="true">
                                    <?php erh_the_icon( 'grid' ); ?>
                                </button>
                                <button class="view-toggle-btn" data-view="table" aria-label="Table view" aria-pressed="false">
                                    <?php erh_the_icon( 'list' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Active Filters Bar -->
                    <div class="finder-active-bar" data-active-filters hidden></div>

                    <!-- Product Grid (populated by JavaScript) -->
                    <div class="finder-grid" data-finder-grid>
                        <!-- Products rendered via JS for progressive loading -->
                    </div>

                    <!-- Table View (hidden by default, shown when table view selected) -->
                    <div class="finder-table-view" data-finder-table-view hidden>
                        <!-- Filter Cards Row (Add Column button appears inline after cards) -->
                        <div class="finder-table-filters" data-active-columns>
                            <!-- Active filter cards populated by JS -->
                            <!-- Add Column button (rendered by JS at end of cards) -->
                        </div>

                        <!-- Table Container -->
                        <div class="finder-table-container">
                            <table class="finder-table" data-finder-table>
                                <thead data-table-head>
                                    <!-- Headers populated by JS -->
                                </thead>
                                <tbody data-table-body>
                                    <!-- Rows populated by JS -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Table Load More -->
                        <div class="finder-table-footer" data-table-footer>
                            <button type="button" class="finder-load-more-btn" data-table-load-more hidden>
                                Load more
                            </button>
                        </div>
                    </div>

                    <!-- Load More Button (Grid View) -->
                    <div class="finder-load-more" data-load-more hidden>
                        <button type="button" class="finder-load-more-btn" data-load-more-btn>
                            Load more
                        </button>
                    </div>

                    <!-- Empty State (shown via JS when filters result in no matches) -->
                    <div class="finder-empty" data-finder-empty hidden>
                        <p>No products match your filters.</p>
                        <button type="button" class="btn btn-secondary" data-clear-filters>Clear all filters</button>
                    </div>

                </div>

            </div>
        </div>
    </section>

    <?php get_template_part( 'template-parts/components/comparison-bar' ); ?>

    <!-- Column Selector Modal (Table View) - Uses standard modal system -->
    <div class="modal" id="columns-modal" data-modal aria-hidden="true">
        <div class="modal-content modal-content--md">
            <button class="modal-close" data-modal-close aria-label="Close modal">
                <?php erh_the_icon( 'x' ); ?>
            </button>
            <h2 class="modal-title">Add Columns</h2>
            <div class="modal-body">
                <!-- Column search -->
                <div class="finder-columns-search">
                    <?php erh_the_icon( 'search' ); ?>
                    <input type="text" placeholder="Search specs..." data-columns-search autofocus>
                </div>
                <!-- Column groups -->
                <div class="finder-columns-groups" data-columns-groups>
                    <!-- Populated by JS from filterConfig.columnGroups -->
                </div>
            </div>
        </div>
    </div>

</main>

<?php
// Prepare products data for JavaScript.
$js_products = erh_prepare_js_products( $products );

// Build ranges with actual min/max values from data.
$js_ranges = [];
foreach ( $range_config as $key => $cfg ) {
    $js_ranges[ $key ] = [
        'min' => $filter_min[ $key ] ?? 0,
        'max' => $filter_max[ $key ] ?? 0,
    ];
}

// Get unified filter config (single source of truth).
$js_filter_config = erh_get_js_filter_config( $json_type );

get_footer();

// Page-specific data must come AFTER get_footer() so it runs after wp_localize_script output.
?>
<script>
// Extend erhData with finder-specific config (erhData set by wp_localize_script in footer)
window.erhData = window.erhData || {};
window.erhData.finderProducts = <?php echo wp_json_encode( $js_products ); ?>;
window.erhData.finderConfig = {
    productType: <?php echo wp_json_encode( $page_info['product_type'] ); ?>,
    shortName: <?php echo wp_json_encode( $page_info['short'] ); ?>,
    userGeo: <?php echo wp_json_encode( $user_geo ); ?>,
    ranges: <?php echo wp_json_encode( $js_ranges ); ?>
};
window.erhData.filterConfig = <?php echo wp_json_encode( $js_filter_config ); ?>;
</script>
