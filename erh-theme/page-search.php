<?php
/**
 * Template Name: Search
 *
 * Search results page with filters by content type.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Get search query from URL.
$search_query = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

// SSR: Query results server-side for initial render.
$ssr_results = [];
if ( $search_query && strlen( $search_query ) >= 2 ) {
    $ssr_results = get_posts( [
        'post_type'      => [ 'products', 'post', 'tool' ],
        'post_status'    => 'publish',
        'posts_per_page' => 60,
        's'              => $search_query,
    ] );
}
?>

<main class="search-page" data-search-page>

    <!-- Search Header -->
    <section class="archive-header">
        <div class="container">
            <h1 class="archive-title"><?php esc_html_e( 'Search', 'erh' ); ?></h1>

            <!-- Search Input -->
            <div class="search-page-input-wrapper">
                <?php erh_the_icon( 'search' ); ?>
                <input
                    type="text"
                    class="search-page-input"
                    placeholder="<?php esc_attr_e( 'Search products, reviews, guides...', 'erh' ); ?>"
                    value="<?php echo esc_attr( $search_query ); ?>"
                    data-search-input
                    autofocus
                >
                <button type="button" class="search-page-clear" data-search-clear aria-label="<?php esc_attr_e( 'Clear search', 'erh' ); ?>" hidden>
                    <?php erh_the_icon( 'x' ); ?>
                </button>
            </div>

            <!-- Type Filters (only shown when there are results) -->
            <div class="archive-filters" data-search-filters hidden>
                <button type="button" class="archive-filter is-active" data-filter="all" aria-pressed="true">
                    <?php esc_html_e( 'All', 'erh' ); ?>
                    <span class="archive-filter-count" data-count-all></span>
                </button>
                <button type="button" class="archive-filter" data-filter="Product" aria-pressed="false" hidden>
                    <?php esc_html_e( 'Products', 'erh' ); ?>
                    <span class="archive-filter-count" data-count-product></span>
                </button>
                <button type="button" class="archive-filter" data-filter="Article" aria-pressed="false" hidden>
                    <?php esc_html_e( 'Articles', 'erh' ); ?>
                    <span class="archive-filter-count" data-count-article></span>
                </button>
                <button type="button" class="archive-filter" data-filter="Tool" aria-pressed="false" hidden>
                    <?php esc_html_e( 'Tools', 'erh' ); ?>
                    <span class="archive-filter-count" data-count-tool></span>
                </button>
            </div>
        </div>
    </section>

    <!-- Search Results -->
    <section class="section archive-content">
        <div class="container">

            <!-- Results Grid -->
            <div class="archive-grid" data-search-results <?php echo empty( $ssr_results ) ? 'hidden' : ''; ?>>
                <?php foreach ( $ssr_results as $ssr_post ) :
                    $is_product = $ssr_post->post_type === 'products';
                    $type_label = match( $ssr_post->post_type ) {
                        'products' => get_field( 'product_type', $ssr_post->ID ) ?: 'Product',
                        'tool'     => 'Tool',
                        default    => 'Article',
                    };
                    $thumb_url = get_the_post_thumbnail_url( $ssr_post->ID, $is_product ? 'large' : 'erh-card' );
                ?>
                    <a class="archive-card" href="<?php echo esc_url( get_permalink( $ssr_post->ID ) ); ?>">
                        <div class="archive-card-img<?php echo $is_product ? ' archive-card-img--product' : ''; ?>">
                            <?php if ( $thumb_url ) : ?>
                                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
                            <?php endif; ?>
                            <span class="archive-card-tag"><?php echo esc_html( $type_label ); ?></span>
                        </div>
                        <h3 class="archive-card-title"><?php echo esc_html( $ssr_post->post_title ); ?></h3>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Empty State -->
            <div class="archive-empty" data-search-empty <?php echo ! empty( $ssr_results ) ? 'hidden' : ''; ?>>
                <p data-empty-text>
                    <?php if ( $search_query && empty( $ssr_results ) ) : ?>
                        <?php printf( esc_html__( 'No results found for "%s"', 'erh' ), esc_html( $search_query ) ); ?>
                    <?php else : ?>
                        <?php esc_html_e( 'Start typing to search products, reviews, and guides.', 'erh' ); ?>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Loading State -->
            <div class="search-page-loading" data-search-loading hidden>
                <span class="search-loading-spinner"></span>
                <span><?php esc_html_e( 'Searching...', 'erh' ); ?></span>
            </div>

        </div>
    </section>

</main>

<?php
get_footer();
?>
<script>
window.erhData = window.erhData || {};
window.erhData.searchQuery = <?php echo wp_json_encode( $search_query ); ?>;
</script>
