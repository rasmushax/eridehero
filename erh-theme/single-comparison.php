<?php
/**
 * Single Comparison Template
 *
 * Template for curated comparison CPT posts.
 * Shows the same comparison UI as dynamic but with intro/verdict content.
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Get comparison fields.
// ACF relationship fields return arrays even with max=1.
$product_1_raw   = get_field( 'product_1' );
$product_2_raw   = get_field( 'product_2' );
$product_1_id    = is_array( $product_1_raw ) ? ( $product_1_raw[0] ?? null ) : $product_1_raw;
$product_2_id    = is_array( $product_2_raw ) ? ( $product_2_raw[0] ?? null ) : $product_2_raw;
$intro           = get_field( 'intro_text' );
$verdict_winner  = get_field( 'verdict_winner' );
$verdict_text    = get_field( 'verdict_text' );
$is_featured     = get_field( 'is_featured' );

// Validate products exist.
if ( ! $product_1_id || ! $product_2_id ) {
    get_template_part( '404' );
    return;
}

$product_ids = [ (int) $product_1_id, (int) $product_2_id ];

// Get category from product type.
$product_type  = get_field( 'product_type', $product_1_id );
$category_data = function_exists( 'erh_get_category_from_type' )
    ? erh_get_category_from_type( $product_type )
    : [ 'key' => 'escooter', 'name' => 'E-Scooters', 'slug' => 'escooter' ];

$category      = $category_data['key'];
$category_name = $category_data['name'];
$category_slug = $category_data['slug'];

// Build page title.
$product_1_name = get_the_title( $product_1_id );
$product_2_name = get_the_title( $product_2_id );
$page_title     = $product_1_name . ' vs ' . $product_2_name;

// Get curated comparison ID for view tracking.
$comparison_id = get_the_ID();
?>

<main class="compare-page compare-page--curated" data-compare-page data-category="<?php echo esc_attr( $category ); ?>" data-comparison-id="<?php echo esc_attr( $comparison_id ); ?>">

    <!-- Breadcrumb -->
    <div class="container">
        <?php
        erh_breadcrumb( [
            [ 'label' => 'Compare', 'url' => home_url( '/compare/' ) ],
            [ 'label' => $category_name, 'url' => erh_get_compare_category_url( $category ) ],
        ] );
        ?>
    </div>

    <!-- Intro Section with Title -->
    <section class="compare-intro">
        <div class="container">
            <h1 class="compare-intro-title"><?php echo esc_html( $page_title ); ?></h1>
            <?php if ( $intro ) : ?>
                <p class="compare-intro-text"><?php echo esc_html( $intro ); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Sticky Product Header -->
    <header class="compare-header" data-compare-header>
        <div class="container">
            <div class="compare-header-products" data-compare-products>
                <!-- Product cards rendered by JS with winner highlighting -->
            </div>
        </div>
    </header>

    <!-- Sticky Section Nav -->
    <nav class="compare-nav" data-compare-nav>
        <div class="container">
            <div class="compare-nav-links">
                <a href="#overview" class="compare-nav-link is-active" data-nav-link="overview">Overview</a>
                <a href="#specifications" class="compare-nav-link" data-nav-link="specs">Specifications</a>
                <a href="#pricing" class="compare-nav-link" data-nav-link="pricing">Pricing</a>
                <?php if ( $verdict_text ) : ?>
                <a href="#verdict" class="compare-nav-link" data-nav-link="verdict">Verdict</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- All Sections (Single Scroll) -->
    <div class="compare-content">
        <div class="container">

            <!-- Overview Section -->
            <section id="overview" class="compare-section" data-section="overview">
                <h2 class="compare-section-title">Overview</h2>
                <div class="compare-overview" data-compare-overview>
                    <div class="compare-loading">
                        <span class="compare-loading-spinner"></span>
                        <span>Loading comparison...</span>
                    </div>
                </div>
            </section>

            <!-- Specifications Section -->
            <section id="specifications" class="compare-section" data-section="specs">
                <h2 class="compare-section-title">Specifications</h2>
                <div class="compare-specs" data-compare-specs></div>
            </section>

            <!-- Pricing Section -->
            <section id="pricing" class="compare-section" data-section="pricing">
                <h2 class="compare-section-title">Pricing</h2>
                <div class="compare-pricing" data-compare-pricing></div>
            </section>

            <?php if ( $verdict_text ) : ?>
            <!-- Verdict Section (Curated Content) -->
            <section id="verdict" class="compare-section compare-section--verdict" data-section="verdict">
                <h2 class="compare-section-title">Our Verdict</h2>
                <div class="compare-verdict">
                    <div class="compare-verdict-card">
                        <?php if ( $verdict_winner && $verdict_winner !== 'tie' && $verdict_winner !== 'depends' ) : ?>
                            <?php
                            $winner_name = $verdict_winner === 'product_1' ? $product_1_name : $product_2_name;
                            $winner_id   = $verdict_winner === 'product_1' ? $product_1_id : $product_2_id;
                            $winner_thumb = get_the_post_thumbnail_url( $winner_id, 'thumbnail' );
                            ?>
                            <div class="compare-verdict-winner">
                                <span class="compare-verdict-crown">
                                    <?php erh_the_icon( 'crown' ); ?>
                                </span>
                                <span class="compare-verdict-label">Our Pick</span>
                                <div class="compare-verdict-winner-product">
                                    <?php if ( $winner_thumb ) : ?>
                                        <img src="<?php echo esc_url( $winner_thumb ); ?>" alt="<?php echo esc_attr( $winner_name ); ?>" class="compare-verdict-winner-thumb">
                                    <?php endif; ?>
                                    <span class="compare-verdict-winner-name"><?php echo esc_html( $winner_name ); ?></span>
                                </div>
                            </div>
                        <?php elseif ( $verdict_winner === 'tie' ) : ?>
                            <div class="compare-verdict-tie">
                                <span class="compare-verdict-label">It's a Tie</span>
                            </div>
                        <?php elseif ( $verdict_winner === 'depends' ) : ?>
                            <div class="compare-verdict-depends">
                                <span class="compare-verdict-label">It Depends on Your Needs</span>
                            </div>
                        <?php endif; ?>

                        <p class="compare-verdict-text"><?php echo esc_html( $verdict_text ); ?></p>

                        <?php if ( $verdict_winner && $verdict_winner !== 'tie' && $verdict_winner !== 'depends' ) : ?>
                            <div class="compare-verdict-actions">
                                <a href="<?php echo esc_url( get_permalink( $winner_id ) ); ?>" class="btn btn--primary">
                                    View <?php echo esc_html( $winner_name ); ?>
                                    <?php erh_the_icon( 'arrow-right' ); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Related Comparisons -->
            <section class="compare-related">
                <h2 class="compare-section-title">Related Comparisons</h2>
                <div class="compare-related-grid" data-related-comparisons></div>
            </section>

        </div>
    </div>

</main>

<!-- Add Product Modal -->
<div class="modal" id="compare-add-modal" data-modal aria-hidden="true">
    <div class="modal-content modal-content--md">
        <button class="modal-close" data-modal-close aria-label="Close modal">
            <?php erh_the_icon( 'x' ); ?>
        </button>
        <h2 class="modal-title">Add Product to Compare</h2>
        <div class="modal-body">
            <div class="compare-search">
                <div class="compare-search-field">
                    <?php erh_the_icon( 'search' ); ?>
                    <input type="text" class="compare-search-input" placeholder="Search products..." data-compare-search autocomplete="off">
                </div>
                <div class="compare-search-results" data-compare-results></div>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();
?>

<script>
// Extend erhData with page-specific config.
window.erhData = window.erhData || {};
window.erhData.compareConfig = {
    productIds: <?php echo wp_json_encode( array_map( 'intval', $product_ids ) ); ?>,
    category: <?php echo wp_json_encode( $category ); ?>,
    categoryName: <?php echo wp_json_encode( $category_name ); ?>,
    categorySlug: <?php echo wp_json_encode( $category_slug ); ?>,
    isCurated: true,
    comparisonId: <?php echo (int) $comparison_id; ?>,
    verdictWinner: <?php echo wp_json_encode( $verdict_winner ); ?>,
    titleData: <?php echo wp_json_encode( erh_get_compare_title_data() ); ?>
};
// Inject spec config from PHP (single source of truth).
window.erhData.specConfig = <?php echo wp_json_encode( \ERH\Config\SpecConfig::export_compare_config( $category ) ); ?>;
</script>
