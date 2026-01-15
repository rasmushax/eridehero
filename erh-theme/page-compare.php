<?php
/**
 * Template Name: Product Comparison
 *
 * Multi-purpose comparison page template:
 * - Hub page (no products): Shows hero, categories, featured, popular
 * - Comparison page (with products): Shows head-to-head comparison
 * - Curated comparisons: Same as above + intro text and verdict
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

use ERH\CategoryConfig;

get_header();

// =============================================================================
// Detect Curated Comparison (CPT)
// =============================================================================
$is_curated     = false;
$comparison_id  = 0;
$intro_text     = '';
$verdict_winner = '';
$verdict_text   = '';

// Check if we're on a curated comparison CPT.
if ( is_singular( 'comparison' ) ) {
	$is_curated     = true;
	$comparison_id  = get_the_ID();
	$intro_text     = get_field( 'intro_text' );
	$verdict_winner = get_field( 'verdict_winner' );
	$verdict_text   = get_field( 'verdict_text' );

	// Get product IDs from ACF relationship fields.
	$product_1_raw = get_field( 'product_1' );
	$product_2_raw = get_field( 'product_2' );
	$product_1_id  = is_array( $product_1_raw ) ? ( $product_1_raw[0] ?? null ) : $product_1_raw;
	$product_2_id  = is_array( $product_2_raw ) ? ( $product_2_raw[0] ?? null ) : $product_2_raw;

	if ( $product_1_id && $product_2_id ) {
		$product_ids = [ (int) $product_1_id, (int) $product_2_id ];
	} else {
		// Invalid curated comparison - show 404.
		get_template_part( '404' );
		return;
	}
} else {
	// Parse product IDs from URL (SEO slugs or query string).
	$product_ids = erh_get_compare_product_ids();
}

$has_products = count( $product_ids ) >= 2;
$is_hub_page  = ! $has_products;

// =============================================================================
// Category Detection
// =============================================================================
$category      = 'escooter';
$category_name = 'E-Scooters';
$category_slug = 'escooter';

if ( ! empty( $product_ids[0] ) ) {
	$product_type = get_field( 'product_type', $product_ids[0] );
	if ( $product_type ) {
		$category_data = erh_get_category_from_type( $product_type );
		$category      = $category_data['key'];
		$category_name = $category_data['name'];
		$category_slug = $category_data['slug'];
	}
}

// Build page title from product names.
$product_names = array_map( 'get_the_title', $product_ids );
$page_title    = $has_products
	? implode( ' vs ', array_slice( $product_names, 0, 3 ) ) . ( count( $product_names ) > 3 ? ' & more' : '' )
	: 'Compare Products';

// =============================================================================
// SSR: Fetch Products
// =============================================================================
$compare_products = array();
$geo              = isset( $_COOKIE['erh_geo'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['erh_geo'] ) ) : 'US';
$currency_symbol  = erh_get_currency_symbol( $geo );

if ( $has_products ) {
	$compare_products = erh_get_compare_products( $product_ids, $geo );
	// If not enough products found in cache, fall back to hub page.
	if ( count( $compare_products ) < 2 ) {
		$has_products = false;
		$is_hub_page  = true;
	}
}

// =============================================================================
// Page Setup
// =============================================================================
$product_count      = count( $product_ids );
$product_count_attr = $has_products ? ' data-product-count="' . $product_count . '" style="--product-count: ' . $product_count . ';"' : '';
$is_full_width      = $has_products && $product_count >= 5;
$page_classes       = 'compare-page';
$page_classes      .= $is_hub_page ? ' compare-page--hub' : '';
$page_classes      .= $is_curated ? ' compare-page--curated' : '';
$page_classes      .= $is_full_width ? ' compare-page--full-width' : '';

// Extra data attributes for curated.
$curated_attrs = $is_curated ? ' data-comparison-id="' . esc_attr( $comparison_id ) . '"' : '';
?>
<main class="<?php echo esc_attr( $page_classes ); ?>" data-compare-page data-category="<?php echo esc_attr( $category ); ?>"<?php echo $product_count_attr . $curated_attrs; ?>>

	<?php if ( $is_hub_page ) : ?>
		<!-- Hub Page: Compare Center -->
		<?php get_template_part( 'template-parts/compare/hub-hero' ); ?>
		<?php get_template_part( 'template-parts/compare/hub-categories' ); ?>
		<?php get_template_part( 'template-parts/compare/hub-featured' ); ?>
		<?php get_template_part( 'template-parts/compare/hub-popular' ); ?>

	<?php else : ?>
		<!-- SSR Comparison Content -->
		<div data-ssr-rendered>
			<div class="container">
				<?php
				erh_breadcrumb( [
					[ 'label' => 'Compare', 'url' => home_url( '/compare/' ) ],
					[ 'label' => $category_name, 'url' => erh_get_compare_category_url( $category ) ],
				] );
				?>
			</div>

			<?php if ( $is_curated ) : ?>
			<!-- Curated: Intro Section with H1 -->
			<section class="compare-intro">
				<div class="container">
					<h1 class="compare-intro-title"><?php echo esc_html( $page_title ); ?></h1>
					<?php if ( $intro_text ) : ?>
						<p class="compare-intro-text"><?php echo esc_html( $intro_text ); ?></p>
					<?php endif; ?>
				</div>
			</section>
			<?php endif; ?>

			<!-- SSR: Product Hero Cards -->
			<?php
			get_template_part( 'template-parts/compare/header-products', null, [
				'products' => $compare_products,
				'geo'      => $geo,
			] );
			?>

			<!-- Sticky Section Nav -->
			<?php
			$spec_groups = erh_get_compare_spec_groups( $category );
			?>
			<nav class="compare-nav" data-compare-nav>
				<div class="container">
					<div class="compare-nav-links" data-nav-links>
						<a href="#overview" class="compare-nav-link is-active" data-nav-link="overview">Overview</a>
						<?php foreach ( $spec_groups as $group_name => $group ) : ?>
							<a href="#<?php echo esc_attr( sanitize_title( $group_name ) ); ?>"
							   class="compare-nav-link"
							   data-nav-link="<?php echo esc_attr( sanitize_title( $group_name ) ); ?>">
								<?php echo esc_html( $group_name ); ?>
							</a>
						<?php endforeach; ?>
						<?php if ( $is_curated && $verdict_text ) : ?>
						<a href="#verdict" class="compare-nav-link" data-nav-link="verdict">Verdict</a>
						<?php endif; ?>
					</div>
				</div>
			</nav>

			<!-- SSR: Overview Section -->
			<div class="compare-content">
				<div class="container">
					<section id="overview" class="compare-section" data-section="overview">
						<h2 class="compare-section-title">Overview</h2>
						<div class="compare-overview" data-compare-overview>
							<?php
							get_template_part( 'template-parts/compare/overview', null, [
								'products' => $compare_products,
								'category' => $category,
							] );
							?>
						</div>
					</section>
				</div>
			</div>

			<!-- SSR: Specifications Section -->
			<section id="specifications" class="compare-section compare-section--specs" data-section="specs">
				<div class="container">
					<h2 class="compare-section-title">Specifications</h2>
					<div class="compare-specs" data-compare-specs>
						<?php
						// Render mini-header.
						get_template_part( 'template-parts/compare/mini-header', null, [
							'products'        => $compare_products,
							'geo'             => $geo,
							'currency_symbol' => $currency_symbol,
						] );

						// Check if any product has pricing in any geo (for Value Analysis).
						$any_has_pricing = erh_any_product_has_pricing( $product_ids );

						// Render each spec category table.
						foreach ( $spec_groups as $group_name => $group ) {
							$is_value_section = ! empty( $group['isValueSection'] );

							// Skip Value Analysis entirely if no product has any pricing.
							if ( $is_value_section && ! $any_has_pricing ) {
								continue;
							}

							$specs = $group['specs'] ?? array();

							// For Value Analysis, render with empty cells (JS will hydrate).
							if ( $is_value_section ) {
								get_template_part( 'template-parts/compare/specs-table-value', null, [
									'group_name'      => $group_name,
									'group_slug'      => sanitize_title( $group_name ),
									'specs'           => $specs,
									'products'        => $compare_products,
									'currency_symbol' => $currency_symbol,
								] );
								continue;
							}

							$rows = erh_build_compare_spec_rows( $compare_products, $specs, $geo, $currency_symbol );

							if ( empty( $rows ) ) {
								continue;
							}

							get_template_part( 'template-parts/compare/specs-table', null, [
								'group_name'      => $group_name,
								'group_slug'      => sanitize_title( $group_name ),
								'rows'            => $rows,
								'products'        => $compare_products,
								'currency_symbol' => $currency_symbol,
							] );
						}
						?>
					</div>
				</div>
			</section>

			<?php if ( $is_curated && $verdict_text ) : ?>
			<!-- Curated: Verdict Section -->
			<section id="verdict" class="compare-section compare-section--verdict" data-section="verdict">
				<div class="container">
					<h2 class="compare-section-title">Our Verdict</h2>
					<div class="compare-verdict">
						<div class="compare-verdict-card">
							<?php if ( $verdict_winner && $verdict_winner !== 'tie' && $verdict_winner !== 'depends' ) : ?>
								<?php
								$winner_name  = $verdict_winner === 'product_1' ? $product_names[0] : $product_names[1];
								$winner_id    = $verdict_winner === 'product_1' ? $product_ids[0] : $product_ids[1];
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
				</div>
			</section>
			<?php endif; ?>

			<?php if ( $is_curated ) : ?>
			<!-- Curated: Related Comparisons -->
			<section class="compare-related">
				<div class="container">
					<h2 class="compare-section-title">Related Comparisons</h2>
					<div class="compare-related-grid" data-related-comparisons></div>
				</div>
			</section>
			<?php endif; ?>
		</div>

	<?php endif; ?>

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

// Page-specific data must come AFTER get_footer() so it runs after wp_localize_script output.
?>
<script>
// Extend erhData with page-specific config (erhData set by wp_localize_script in footer)
window.erhData = window.erhData || {};
window.erhData.compareConfig = {
	productIds: <?php echo wp_json_encode( array_map( 'intval', $product_ids ) ); ?>,
	category: <?php echo wp_json_encode( $category ); ?>,
	categoryName: <?php echo wp_json_encode( $category_name ); ?>,
	categorySlug: <?php echo wp_json_encode( $category_slug ); ?>,
	geo: <?php echo wp_json_encode( $geo ); ?>,
	currencySymbol: <?php echo wp_json_encode( $currency_symbol ); ?>,
	titleData: <?php echo wp_json_encode( erh_get_compare_title_data() ); ?>,
	isCurated: <?php echo $is_curated ? 'true' : 'false'; ?>,
	comparisonId: <?php echo (int) $comparison_id; ?>,
	verdictWinner: <?php echo wp_json_encode( $verdict_winner ); ?>
};
// Inject spec config from PHP (single source of truth).
window.erhData.specConfig = <?php echo wp_json_encode( \ERH\Config\SpecConfig::export_compare_config( $category ) ); ?>;
</script>
<?php if ( ! empty( $compare_products ) ) : ?>
<!-- Products JSON for JS hydration -->
<script type="application/json" data-products-json>
<?php echo wp_json_encode( $compare_products ); ?>
</script>
<?php endif; ?>
<?php

/**
 * Get product IDs from URL (SEO slugs or query string).
 *
 * @return int[] Array of product post IDs.
 */
function erh_get_compare_product_ids(): array {
	$compare_slugs = get_query_var( 'compare_slugs', '' );

	if ( ! empty( $compare_slugs ) ) {
		$slugs = array_filter( explode( ',', $compare_slugs ) );
		if ( empty( $slugs ) ) {
			return [];
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array_map( 'intval', $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				 WHERE post_type = 'products'
				 AND post_status = 'publish'
				 AND post_name IN ({$placeholders})
				 ORDER BY FIELD(post_name, {$placeholders})",
				...array_merge( $slugs, $slugs )
			)
		) );
	}

	// Query string fallback: ?products=123,456
	$products_param = isset( $_GET['products'] ) ? sanitize_text_field( wp_unslash( $_GET['products'] ) ) : '';
	if ( ! empty( $products_param ) ) {
		return array_filter( array_map( 'absint', explode( ',', $products_param ) ) );
	}

	return [];
}

/**
 * Get category data from product type.
 *
 * @param string|null $product_type The product type field value.
 * @return array{key: string, name: string, slug: string}
 */
function erh_get_category_from_type( ?string $product_type ): array {
	$category = CategoryConfig::get_by_type( $product_type ?? '' );
	if ( $category ) {
		return [
			'key'  => $category['key'],
			'name' => $category['name'],
			'slug' => $category['key'], // Note: uses key for internal slug, not URL slug.
		];
	}
	// Default to escooter.
	return [ 'key' => 'escooter', 'name' => 'E-Scooters', 'slug' => 'escooter' ];
}
