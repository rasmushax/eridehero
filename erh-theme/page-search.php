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
            <div class="archive-grid" data-search-results hidden>
                <!-- Populated by JavaScript -->
            </div>

            <!-- Empty State -->
            <div class="archive-empty" data-search-empty>
                <p data-empty-text><?php esc_html_e( 'Start typing to search products, reviews, and guides.', 'erh' ); ?></p>
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
