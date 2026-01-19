<?php
/**
 * Compare Category Landing Page
 *
 * Category-specific comparison hub (e.g., /compare/electric-scooters/).
 * Shows curated comparisons, popular comparisons, and quick compare widget.
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Get category data from query var.
$category_data = erh_get_compare_category();

if ( ! $category_data ) {
    // Fallback to hub page if no valid category.
    get_template_part( 'page-compare' );
    return;
}

$category_key   = $category_data['key'];
$category_name  = $category_data['name'];
$category_type  = $category_data['type'];
$category_slug  = get_query_var( 'compare_category' );

// Get the JSON file path and URL (same pattern as home/comparison.php).
$upload_dir = wp_upload_dir();
$json_path  = $upload_dir['basedir'] . '/comparison_products.json';
$json_url   = $upload_dir['baseurl'] . '/comparison_products.json';

// Check if JSON exists - widget won't work without it.
$has_json = file_exists( $json_path );

// Get comparison views DB instance.
$views_db = null;
if ( class_exists( 'ERH\\Database\\ComparisonViews' ) ) {
    $views_db = new ERH\Database\ComparisonViews();
}

// Get popular comparisons for this category.
$popular_comparisons = [];
if ( $views_db ) {
    $popular_comparisons = $views_db->get_popular( $category_key, 8 );
}

// Query featured curated comparisons for this category.
$featured_comparisons = new WP_Query( [
    'post_type'      => 'comparison',
    'post_status'    => 'publish',
    'posts_per_page' => 4,
    'meta_query'     => [
        'relation' => 'AND',
        [
            'key'     => 'is_featured',
            'value'   => '1',
            'compare' => '=',
        ],
        [
            'key'     => 'category',
            'value'   => $category_key,
            'compare' => '=',
        ],
    ],
] );

// If no featured with category, try getting any featured.
if ( ! $featured_comparisons->have_posts() ) {
    wp_reset_postdata();
    $featured_comparisons = new WP_Query( [
        'post_type'      => 'comparison',
        'post_status'    => 'publish',
        'posts_per_page' => 4,
        'meta_query'     => [
            [
                'key'     => 'is_featured',
                'value'   => '1',
                'compare' => '=',
            ],
        ],
    ] );
}
?>

<main class="compare-page compare-page--category" data-compare-page data-category="<?php echo esc_attr( $category_key ); ?>">

    <!-- Breadcrumb: Just "Compare" link on category pages -->
    <div class="container">
        <?php
        erh_breadcrumb( [
            [ 'label' => 'Compare', 'url' => home_url( '/compare/' ), 'is_link' => true ],
        ] );
        ?>
    </div>

    <!-- Category Hero -->
    <section class="compare-category-hero">
        <div class="container">
            <h1 class="compare-category-title">Compare <?php echo esc_html( $category_name ); ?></h1>
            <p class="compare-category-subtitle">Find the best <?php echo esc_html( strtolower( $category_name ) ); ?> by comparing specs, prices, and features.</p>
        </div>
    </section>

    <!-- Quick Compare Widget -->
    <section class="compare-category-quick">
        <div class="container">
            <?php if ( $has_json ) : ?>
            <div class="comparison-section" id="category-comparison-container" data-category-filter="<?php echo esc_attr( $category_key ); ?>" data-json-url="<?php echo esc_url( $json_url ); ?>">
                <div class="comparison-card">
                    <div class="comparison-card-bg">
                        <div class="comparison-orb"></div>
                    </div>
                    <div class="comparison-content">
                        <div class="comparison-header">
                            <h2>Start Your Comparison</h2>
                            <span class="comparison-category-pill visible">
                                <?php echo esc_html( $category_name ); ?>
                            </span>
                        </div>
                        <div class="comparison-row-main">
                            <div class="comparison-column-left">
                                <div class="comparison-input-wrapper">
                                    <input
                                        type="text"
                                        class="comparison-input"
                                        placeholder="Search <?php echo esc_attr( strtolower( $category_name ) ); ?>..."
                                        autocomplete="off"
                                        data-slot="0"
                                    >
                                    <img class="comparison-input-thumb" src="" alt="" aria-hidden="true">
                                    <button type="button" class="comparison-input-clear" aria-label="Clear selection">
                                        <?php erh_the_icon( 'x' ); ?>
                                    </button>
                                    <div class="comparison-results"></div>
                                </div>
                            </div>
                            <span class="comparison-vs">VS</span>
                            <div class="comparison-column-right" id="category-comparison-right">
                                <div class="comparison-input-wrapper">
                                    <input
                                        type="text"
                                        class="comparison-input"
                                        placeholder="Search <?php echo esc_attr( strtolower( $category_name ) ); ?>..."
                                        autocomplete="off"
                                        data-slot="1"
                                    >
                                    <img class="comparison-input-thumb" src="" alt="" aria-hidden="true">
                                    <button type="button" class="comparison-input-clear" aria-label="Clear selection">
                                        <?php erh_the_icon( 'x' ); ?>
                                    </button>
                                    <div class="comparison-results"></div>
                                </div>
                            </div>
                            <div class="comparison-actions">
                                <button type="button" class="comparison-btn" id="category-comparison-submit" disabled>
                                    <span>Compare</span>
                                    <?php erh_the_icon( 'arrow-right' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Screen reader announcements -->
                <div id="category-comparison-announcer" class="sr-only" aria-live="polite" aria-atomic="true"></div>
            </div>
            <?php else : ?>
            <!-- Fallback message when JSON not yet generated -->
            <div class="comparison-section comparison-section--loading">
                <div class="comparison-card">
                    <div class="comparison-card-bg">
                        <div class="comparison-orb"></div>
                    </div>
                    <div class="comparison-content">
                        <p class="comparison-loading-msg">Comparison tool loading...</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ( $featured_comparisons->have_posts() ) : ?>
    <!-- Featured Curated Comparisons -->
    <section class="compare-category-featured">
        <div class="container">
            <h2 class="compare-hub-section-title">Editor's Picks</h2>

            <div class="compare-featured-grid">
                <?php
                while ( $featured_comparisons->have_posts() ) :
                    $featured_comparisons->the_post();

                    // ACF relationship fields return arrays even with max=1.
                    $product_1_raw = get_field( 'product_1' );
                    $product_2_raw = get_field( 'product_2' );
                    $product_1_id  = is_array( $product_1_raw ) ? ( $product_1_raw[0] ?? null ) : $product_1_raw;
                    $product_2_id  = is_array( $product_2_raw ) ? ( $product_2_raw[0] ?? null ) : $product_2_raw;

                    if ( ! $product_1_id || ! $product_2_id ) {
                        continue;
                    }

                    $product_1_name  = get_the_title( $product_1_id );
                    $product_2_name  = get_the_title( $product_2_id );
                    $product_1_thumb = get_the_post_thumbnail_url( $product_1_id, 'medium' );
                    $product_2_thumb = get_the_post_thumbnail_url( $product_2_id, 'medium' );
                    $verdict_winner  = get_field( 'verdict_winner' );
                    ?>
                    <a href="<?php the_permalink(); ?>" class="compare-card">
                        <div class="compare-card-images">
                            <div class="compare-card-product<?php echo $verdict_winner === 'product_1' ? ' is-winner' : ''; ?>">
                                <?php if ( $verdict_winner === 'product_1' ) : ?>
                                    <span class="compare-card-crown" title="<?php esc_attr_e( 'Winner', 'erh' ); ?>">
                                        <?php erh_the_icon( 'crown' ); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ( $product_1_thumb ) : ?>
                                    <img src="<?php echo esc_url( $product_1_thumb ); ?>" alt="<?php echo esc_attr( $product_1_name ); ?>">
                                <?php else : ?>
                                    <div class="compare-card-placeholder"><?php erh_the_icon( 'image' ); ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="compare-card-vs">VS</span>
                            <div class="compare-card-product<?php echo $verdict_winner === 'product_2' ? ' is-winner' : ''; ?>">
                                <?php if ( $verdict_winner === 'product_2' ) : ?>
                                    <span class="compare-card-crown" title="<?php esc_attr_e( 'Winner', 'erh' ); ?>">
                                        <?php erh_the_icon( 'crown' ); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ( $product_2_thumb ) : ?>
                                    <img src="<?php echo esc_url( $product_2_thumb ); ?>" alt="<?php echo esc_attr( $product_2_name ); ?>">
                                <?php else : ?>
                                    <div class="compare-card-placeholder"><?php erh_the_icon( 'image' ); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="compare-card-body">
                            <h3 class="compare-card-title"><?php echo esc_html( $product_1_name ); ?> vs <?php echo esc_html( $product_2_name ); ?></h3>
                        </div>
                    </a>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
    <?php wp_reset_postdata(); ?>
    <?php endif; ?>

    <?php if ( ! empty( $popular_comparisons ) ) : ?>
    <!-- Popular Comparisons -->
    <section class="compare-category-popular">
        <div class="container">
            <h2 class="compare-hub-section-title">Most Compared <?php echo esc_html( $category_name ); ?></h2>

            <div class="compare-popular-grid compare-popular-grid--full">
                <?php foreach ( $popular_comparisons as $item ) : ?>
                    <?php
                    $product_1_id    = (int) $item['product_1_id'];
                    $product_2_id    = (int) $item['product_2_id'];
                    $product_1_name  = $item['product_1_name'] ?? get_the_title( $product_1_id );
                    $product_2_name  = $item['product_2_name'] ?? get_the_title( $product_2_id );
                    $product_1_thumb = get_the_post_thumbnail_url( $product_1_id, 'thumbnail' );
                    $product_2_thumb = get_the_post_thumbnail_url( $product_2_id, 'thumbnail' );
                    $view_count      = (int) ( $item['view_count'] ?? 0 );

                    // Check for curated comparison.
                    $curated_id = null;
                    if ( $views_db && method_exists( $views_db, 'get_curated_comparison' ) ) {
                        $curated_id = $views_db->get_curated_comparison( $product_1_id, $product_2_id );
                    }

                    // Generate URL.
                    $compare_url = $curated_id
                        ? get_permalink( $curated_id )
                        : erh_get_compare_url( [ $product_1_id, $product_2_id ] );
                    ?>

                    <a href="<?php echo esc_url( $compare_url ); ?>" class="compare-popular-card<?php echo $curated_id ? ' is-curated' : ''; ?>">
                        <?php if ( $curated_id ) : ?>
                            <span class="compare-popular-card-badge" title="Expert comparison">
                                <?php erh_the_icon( 'check-circle' ); ?>
                                Curated
                            </span>
                        <?php endif; ?>

                        <div class="compare-popular-card-products">
                            <div class="compare-popular-card-product">
                                <?php if ( $product_1_thumb ) : ?>
                                    <img src="<?php echo esc_url( $product_1_thumb ); ?>" alt="<?php echo esc_attr( $product_1_name ); ?>" class="compare-popular-card-thumb">
                                <?php else : ?>
                                    <div class="compare-popular-card-thumb compare-popular-card-thumb--placeholder">
                                        <?php erh_the_icon( 'image' ); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="compare-popular-card-name"><?php echo esc_html( $product_1_name ); ?></span>
                            </div>

                            <span class="compare-popular-card-vs">vs</span>

                            <div class="compare-popular-card-product">
                                <?php if ( $product_2_thumb ) : ?>
                                    <img src="<?php echo esc_url( $product_2_thumb ); ?>" alt="<?php echo esc_attr( $product_2_name ); ?>" class="compare-popular-card-thumb">
                                <?php else : ?>
                                    <div class="compare-popular-card-thumb compare-popular-card-thumb--placeholder">
                                        <?php erh_the_icon( 'image' ); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="compare-popular-card-name"><?php echo esc_html( $product_2_name ); ?></span>
                            </div>
                        </div>

                        <?php if ( $view_count > 0 ) : ?>
                            <div class="compare-popular-card-meta">
                                <span class="compare-popular-card-views">
                                    <?php erh_the_icon( 'eye' ); ?>
                                    <?php echo esc_html( number_format( $view_count ) ); ?> views
                                </span>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

</main>

<?php
get_footer();
?>

<script>
// Extend erhData with page-specific config.
window.erhData = window.erhData || {};
window.erhData.compareConfig = {
    productIds: [],
    category: <?php echo wp_json_encode( $category_key, JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
    categoryName: <?php echo wp_json_encode( $category_name, JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
    categorySlug: <?php echo wp_json_encode( $category_slug, JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
    isCategoryPage: true,
    titleData: <?php echo wp_json_encode( erh_get_compare_title_data(), JSON_HEX_TAG | JSON_HEX_AMP ); ?>
};
// Inject spec config from PHP (single source of truth).
window.erhData.specConfig = <?php echo wp_json_encode( \ERH\Config\SpecConfig::export_compare_config( $category_key ), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
</script>

<?php
// Schema.org BreadcrumbList for category landing pages.
$breadcrumb_schema = [
    '@context'        => 'https://schema.org',
    '@type'           => 'BreadcrumbList',
    'itemListElement' => [
        [
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => 'Compare',
            'item'     => home_url( '/compare/' ),
        ],
        [
            '@type'    => 'ListItem',
            'position' => 2,
            'name'     => $category_name,
        ],
    ],
];
?>
<script type="application/ld+json">
<?php echo wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT ); ?>
</script>
