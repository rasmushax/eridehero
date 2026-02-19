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

$has_products      = count( $product_ids ) >= 1;
$is_hub_page       = ! $has_products;
$is_single_product = count( $product_ids ) === 1;

// =============================================================================
// Category Detection
// =============================================================================
$category      = 'escooter';
$category_name = 'E-Scooters';
$category_slug = 'escooter';
$finder_key    = 'escooter';

if ( ! empty( $product_ids[0] ) ) {
	// Try ACF field first.
	$product_type = get_field( 'product_type', $product_ids[0] );

	// Fallback to product_type taxonomy if ACF field is empty.
	if ( empty( $product_type ) ) {
		$product_type_terms = get_the_terms( $product_ids[0], 'product_type' );
		if ( ! empty( $product_type_terms ) && ! is_wp_error( $product_type_terms ) ) {
			$product_type = $product_type_terms[0]->name;
		}
	}

	if ( $product_type ) {
		$category_data = erh_get_category_from_type( $product_type );
		$category      = $category_data['key'];
		$category_name = $category_data['name'];
		$category_slug = $category_data['slug'];
		$finder_key    = $category_data['finderKey'];
	}
}

// Build page title from product names.
$product_names = array_map( 'get_the_title', $product_ids );
if ( $is_single_product ) {
	$page_title = $product_names[0];
} elseif ( $has_products ) {
	$page_title = implode( ' vs ', array_slice( $product_names, 0, 3 ) ) . ( count( $product_names ) > 3 ? ' & more' : '' );
} else {
	$page_title = 'Compare Products';
}

// =============================================================================
// SSR: Fetch Products
// =============================================================================
$compare_products = array();
$geo_raw          = isset( $_COOKIE['erh_geo'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['erh_geo'] ) ) : 'US';
$geo              = \ERH\GeoConfig::is_valid_region( $geo_raw ) ? strtoupper( $geo_raw ) : 'US';
$currency_symbol  = erh_get_currency_symbol( $geo );

if ( $has_products ) {
	$compare_products = erh_get_compare_products( $product_ids, $geo );
	// If no products found in cache, fall back to hub page.
	if ( count( $compare_products ) < 1 ) {
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
$page_classes      .= $is_single_product ? ' compare-page--single' : '';
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
				// Both items are links on comparison pages (no "current page" indicator)
				erh_breadcrumb( [
					[ 'label' => 'Compare', 'url' => home_url( '/compare/' ) ],
					[ 'label' => $category_name, 'url' => erh_get_compare_category_url( $category ), 'is_link' => true ],
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
				'products'          => $compare_products,
				'geo'               => $geo,
				'is_single_product' => $is_single_product,
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
								'products'          => $compare_products,
								'category'          => $category,
								'is_single_product' => $is_single_product,
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
						// Render mini-header (outside scroll wrapper for sticky positioning).
						get_template_part( 'template-parts/compare/mini-header', null, [
							'products'          => $compare_products,
							'geo'               => $geo,
							'currency_symbol'   => $currency_symbol,
							'is_single_product' => $is_single_product,
						] );

						// Check if any product has pricing in any geo (for Value Analysis).
						$any_has_pricing = erh_any_product_has_pricing( $product_ids );
						?>
						<!-- Scroll wrapper for horizontal scroll sync with mini-header in full-width mode.
						     CSS only enables overflow when parent has .compare-section--full class. -->
						<div class="compare-specs-scroll">
						<?php
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
									'group_name'        => $group_name,
									'group_slug'        => sanitize_title( $group_name ),
									'specs'             => $specs,
									'products'          => $compare_products,
									'currency_symbol'   => $currency_symbol,
									'is_single_product' => $is_single_product,
								] );
								continue;
							}

							$rows = erh_build_compare_spec_rows( $compare_products, $specs, $geo, $currency_symbol );

							if ( empty( $rows ) ) {
								continue;
							}

							get_template_part( 'template-parts/compare/specs-table', null, [
								'group_name'        => $group_name,
								'group_slug'        => sanitize_title( $group_name ),
								'rows'              => $rows,
								'products'          => $compare_products,
								'currency_symbol'   => $currency_symbol,
								'is_single_product' => $is_single_product,
							] );
						}
						?>

						<!-- Buy Row (JS hydrates with geo-aware pricing) -->
						<table class="compare-spec-table compare-buy-table" data-compare-buy-row>
							<colgroup>
								<col class="compare-spec-col-label">
								<?php foreach ( $compare_products as $product ) : ?>
									<col>
								<?php endforeach; ?>
								<?php if ( $is_single_product ) : ?>
									<col class="compare-spec-col-placeholder">
								<?php endif; ?>
							</colgroup>
							<tbody>
								<tr class="compare-buy-row">
									<td></td>
									<?php foreach ( $compare_products as $product ) : ?>
									<td class="compare-buy-cell" data-buy-cell="<?php echo esc_attr( $product['id'] ); ?>">
										<span class="compare-buy-loading">
											<svg class="spinner spinner-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
												<circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
												<path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
											</svg>
										</span>
									</td>
									<?php endforeach; ?>
									<?php if ( $is_single_product ) : ?>
									<td class="compare-spec-placeholder"></td>
									<?php endif; ?>
								</tr>
							</tbody>
						</table>

						</div>
					</div>
				</div>
			</section>

			<?php if ( $is_curated && $verdict_text ) :
			// Get author info for verdict.
			$author_id    = get_post_field( 'post_author', $comparison_id );
			$author_name  = get_the_author_meta( 'display_name', $author_id );
			$author_role  = get_field( 'user_title', 'user_' . $author_id );
			$profile_img  = get_field( 'profile_image', 'user_' . $author_id );
			$author_url   = get_author_posts_url( $author_id );

			if ( $profile_img && ! empty( $profile_img['url'] ) ) {
				$avatar_url = $profile_img['sizes']['thumbnail'] ?? $profile_img['url'];
			} else {
				$avatar_url = get_avatar_url( $author_id, array( 'size' => 80 ) );
			}

			// Determine winner display text.
			$winner_display = '';
			if ( $verdict_winner === 'product_1' ) {
				$winner_display = $product_names[0];
			} elseif ( $verdict_winner === 'product_2' ) {
				$winner_display = $product_names[1];
			} elseif ( $verdict_winner === 'tie' ) {
				$winner_display = 'It\'s a tie';
			} elseif ( $verdict_winner === 'depends' ) {
				$winner_display = 'Depends on your use case';
			}

			// Get "Choose X if" reasons for depends case.
			$choose_1_reasons = array();
			$choose_2_reasons = array();
			if ( $verdict_winner === 'depends' ) {
				$choose_1_raw = get_field( 'choose_product_1_reasons' );
				$choose_2_raw = get_field( 'choose_product_2_reasons' );
				if ( $choose_1_raw ) {
					$choose_1_reasons = array_filter( array_map( 'trim', explode( "\n", $choose_1_raw ) ) );
				}
				if ( $choose_2_raw ) {
					$choose_2_reasons = array_filter( array_map( 'trim', explode( "\n", $choose_2_raw ) ) );
				}
			}
			?>
			<!-- Curated: Verdict Section -->
			<section id="verdict" class="compare-section compare-section--verdict" data-section="verdict">
				<div class="container">
					<h2 class="compare-section-title">Our Verdict</h2>
					<div class="compare-verdict">
						<!-- Author info -->
						<div class="compare-verdict-author">
							<a href="<?php echo esc_url( $author_url ); ?>" class="compare-verdict-author-avatar">
								<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $author_name ); ?>">
							</a>
							<div class="compare-verdict-author-info">
								<a href="<?php echo esc_url( $author_url ); ?>" class="compare-verdict-author-name"><?php echo esc_html( $author_name ); ?></a>
								<?php if ( $author_role ) : ?>
									<span class="compare-verdict-author-role"><?php echo esc_html( $author_role ); ?></span>
								<?php endif; ?>
							</div>
						</div>

						<hr class="compare-verdict-divider">

						<?php if ( $winner_display ) : ?>
						<!-- Winner line -->
						<div class="compare-verdict-winner">
							<span class="compare-verdict-winner-label">Winner:</span>
							<span class="compare-verdict-winner-value"><?php echo esc_html( $winner_display ); ?></span>
						</div>
						<?php endif; ?>

						<!-- Verdict text -->
						<div class="compare-verdict-text"><?php echo wp_kses_post( wpautop( $verdict_text ) ); ?></div>

						<!-- Product cards with dynamic pricing -->
						<div class="compare-verdict-products">
							<?php foreach ( $product_ids as $idx => $pid ) :
								$pname  = $product_names[ $idx ] ?? get_the_title( $pid );
								$pthumb = get_the_post_thumbnail_url( $pid, 'medium' );
								$purl   = get_permalink( $pid );

								// Get algo score from compare products data (not editor rating).
								$product_data = array_filter( $compare_products, fn( $p ) => $p['id'] === $pid );
								$product_data = reset( $product_data );
								$score        = $product_data['rating'] ?? null;

								// Get review and video links.
								$review_group = get_field( 'review', $pid );
								$review_post  = null;
								$video_url    = '';
								if ( $review_group ) {
									$review_post_id = $review_group['review_post'] ?? null;
									if ( $review_post_id ) {
										$review_post = get_post( $review_post_id );
									}
									$video_url = $review_group['youtube_video'] ?? '';
								}
								$video_id = $video_url ? erh_extract_youtube_id( $video_url ) : '';

								// Get choose reasons for this product (only for "depends" verdict).
								$choose_reasons = null;
								if ( $verdict_winner === 'depends' ) {
									$choose_reasons = $idx === 0 ? $choose_1_reasons : $choose_2_reasons;
								}
							?>
							<article class="compare-verdict-product" data-verdict-product="<?php echo esc_attr( $pid ); ?>">
								<!-- Top row: Image + Info -->
								<div class="compare-verdict-product-main">
									<a href="<?php echo esc_url( $purl ); ?>" class="compare-verdict-product-image">
										<?php erh_the_score_ring( $score, 'verdict' ); ?>
										<?php if ( $pthumb ) : ?>
											<img src="<?php echo esc_url( $pthumb ); ?>" alt="<?php echo esc_attr( $pname ); ?>" loading="lazy">
										<?php endif; ?>
									</a>

									<div class="compare-verdict-product-content">
										<a href="<?php echo esc_url( $purl ); ?>" class="compare-verdict-product-name"><?php echo esc_html( $pname ); ?></a>

										<div class="compare-verdict-product-price-row" data-verdict-price-row="<?php echo esc_attr( $pid ); ?>">
											<span class="compare-verdict-product-price" data-verdict-price="<?php echo esc_attr( $pid ); ?>">
												<span class="skeleton skeleton-text" style="width: 70px; height: 20px;"></span>
											</span>
										</div>

										<div class="compare-verdict-product-cta" data-verdict-cta="<?php echo esc_attr( $pid ); ?>">
											<span class="skeleton skeleton-text" style="width: 100%; height: 40px;"></span>
										</div>
									</div>
								</div>

								<?php if ( ! empty( $choose_reasons ) || $review_post || $video_id ) : ?>
								<!-- Footer row: Choose if + Links -->
								<div class="compare-verdict-product-footer">
									<?php if ( ! empty( $choose_reasons ) ) : ?>
									<div class="compare-verdict-choose-list">
										<h4 class="compare-verdict-choose-title">Choose <?php echo esc_html( $pname ); ?> if:</h4>
										<ul>
											<?php foreach ( $choose_reasons as $reason ) : ?>
											<li>
												<span class="compare-verdict-choose-icon">
													<?php erh_the_icon( 'check' ); ?>
												</span>
												<span><?php echo esc_html( $reason ); ?></span>
											</li>
											<?php endforeach; ?>
										</ul>
										<!-- Mobile CTA (shown at 450px) -->
										<div class="compare-verdict-product-cta compare-verdict-product-cta--mobile" data-verdict-cta-mobile="<?php echo esc_attr( $pid ); ?>">
											<span class="skeleton skeleton-text" style="width: 100%; height: 40px;"></span>
										</div>
									</div>
									<?php endif; ?>

									<?php if ( $review_post || $video_id ) : ?>
									<div class="compare-verdict-product-links">
										<?php if ( $review_post ) : ?>
										<a href="<?php echo esc_url( get_permalink( $review_post ) ); ?>" class="compare-verdict-product-link">
											<?php erh_the_icon( 'review' ); ?>
											<span>Full review</span>
										</a>
										<?php endif; ?>
										<?php if ( $video_id ) : ?>
										<a href="<?php echo esc_url( 'https://www.youtube.com/watch?v=' . $video_id ); ?>" class="compare-verdict-product-link" target="_blank" rel="noopener">
											<?php erh_the_icon( 'youtube' ); ?>
											<span>Video review</span>
										</a>
										<?php endif; ?>
									</div>
									<?php endif; ?>
								</div>
								<?php endif; ?>
							</article>
							<?php endforeach; ?>
						</div>
					</div>
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
	productIds: <?php echo wp_json_encode( array_map( 'intval', $product_ids ), JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
	category: <?php echo wp_json_encode( $category, JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
	finderKey: <?php echo wp_json_encode( $finder_key, JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
	categoryName: <?php echo wp_json_encode( $category_name, JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
	categorySlug: <?php echo wp_json_encode( $category_slug, JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
	geo: <?php echo wp_json_encode( $geo, JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
	currencySymbol: <?php echo wp_json_encode( $currency_symbol, JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
	titleData: <?php echo wp_json_encode( erh_get_compare_title_data(), JSON_HEX_TAG | JSON_HEX_AMP ); ?>,
	isCurated: <?php echo $is_curated ? 'true' : 'false'; ?>,
	isSingleProduct: <?php echo $is_single_product ? 'true' : 'false'; ?>,
	comparisonId: <?php echo (int) $comparison_id; ?>
};
// Inject spec config from PHP (single source of truth).
window.erhData.specConfig = <?php echo wp_json_encode( \ERH\Config\SpecConfig::export_compare_config( $category ), JSON_HEX_TAG | JSON_HEX_AMP ); ?>;
</script>
<?php if ( ! empty( $compare_products ) ) : ?>
<!-- Products JSON for JS hydration -->
<script type="application/json" data-products-json>
<?php echo wp_json_encode( $compare_products, JSON_HEX_TAG | JSON_HEX_AMP ); ?>
</script>
<?php endif; ?>
<?php
// Schema.org JSON-LD for curated comparisons only (indexed pages).
// Uses US prices since Google crawls as US user.
if ( $is_curated && ! empty( $compare_products ) ) :
	$schema_items = array_map( function( $product, $index ) use ( $compare_products ) {
		$product_id     = (int) $product['id'];
		$product_schema = [
			'@type' => 'Product',
			'name'  => $product['name'] ?? '',
			'image' => $product['thumbnail'] ?? '',
			'url'   => $product['url'] ?? '',
		];

		// Add brand from taxonomy.
		$brand_terms = get_the_terms( $product_id, 'brand' );
		if ( $brand_terms && ! is_wp_error( $brand_terms ) ) {
			$product_schema['brand'] = [
				'@type' => 'Brand',
				'name'  => $brand_terms[0]->name,
			];
		}

		// Add category.
		if ( ! empty( $product['product_type'] ) ) {
			$product_schema['category'] = $product['product_type'];
		}

		// Add key specs as additionalProperty.
		if ( ! empty( $product['specs'] ) ) {
			$properties = erh_get_key_specs_for_schema( $product['specs'] );
			if ( ! empty( $properties ) ) {
				$product_schema['additionalProperty'] = $properties;
			}
		}

		// Add isSimilarTo referencing other products in the comparison.
		$similar = [];
		foreach ( $compare_products as $other_idx => $other ) {
			if ( $other_idx !== $index ) {
				$similar[] = [
					'@type' => 'Product',
					'name'  => $other['name'] ?? '',
					'url'   => $other['url'] ?? '',
				];
			}
		}
		if ( ! empty( $similar ) ) {
			$product_schema['isSimilarTo'] = count( $similar ) === 1 ? $similar[0] : $similar;
		}

		// Get all US offers from HFT for schema.
		$offers = erh_get_product_offers_for_schema( $product_id );

		if ( ! empty( $offers ) ) {
			if ( count( $offers ) === 1 ) {
				$product_schema['offers'] = $offers[0];
			} else {
				// Multiple offers - use AggregateOffer.
				$prices = array_column( $offers, 'price' );
				$product_schema['offers'] = [
					'@type'         => 'AggregateOffer',
					'lowPrice'      => min( $prices ),
					'highPrice'     => max( $prices ),
					'priceCurrency' => 'USD',
					'offerCount'    => count( $offers ),
					'offers'        => $offers,
				];
			}
		}

		return [
			'@type'    => 'ListItem',
			'position' => $index + 1,
			'item'     => $product_schema,
		];
	}, $compare_products, array_keys( $compare_products ) );

	// Build comparison title.
	$schema_title = implode( ' vs ', array_column( $compare_products, 'name' ) ) . ' comparison';

	// ItemList schema for the comparison.
	$item_list_schema = [
		'@context'        => 'https://schema.org',
		'@type'           => 'ItemList',
		'name'            => $schema_title,
		'description'     => $intro_text ?: 'Head-to-head comparison of ' . implode( ' and ', array_column( $compare_products, 'name' ) ),
		'itemListElement' => $schema_items,
	];

	// BreadcrumbList schema for hierarchy.
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
				'item'     => erh_get_compare_category_url( $category ),
			],
			[
				'@type'    => 'ListItem',
				'position' => 3,
				'name'     => $schema_title,
			],
		],
	];

	// Author/Person schema for curated comparisons.
	$schema_author_id = get_post_field( 'post_author', $comparison_id );
	$author_schema    = null;

	if ( $schema_author_id ) {
		$author_name   = get_the_author_meta( 'display_name', $schema_author_id );
		$author_bio    = get_the_author_meta( 'description', $schema_author_id );
		$author_url    = get_author_posts_url( $schema_author_id );
		$author_role   = get_field( 'user_title', 'user_' . $schema_author_id );
		$profile_img   = get_field( 'profile_image', 'user_' . $schema_author_id );
		$author_avatar = $profile_img['url'] ?? get_avatar_url( $schema_author_id, [ 'size' => 200 ] );

		// Collect sameAs social links from Rank Math.
		$same_as = erh_get_author_sameas_urls( $schema_author_id );

		$author_schema = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Person',
			'name'        => $author_name,
			'url'         => $author_url,
			'image'       => $author_avatar,
			'description' => $author_bio ?: null,
			'jobTitle'    => $author_role ?: null,
		];

		if ( ! empty( $same_as ) ) {
			$author_schema['sameAs'] = $same_as;
		}

		// Remove null values.
		$author_schema = array_filter( $author_schema, fn( $v ) => $v !== null );
	}

	$schemas = [ $item_list_schema, $breadcrumb_schema ];
	if ( $author_schema ) {
		$schemas[] = $author_schema;
	}
?>
<script type="application/ld+json">
<?php echo wp_json_encode( $schemas, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_PRETTY_PRINT ); ?>
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
			'key'       => $category['key'],
			'name'      => $category['name'],
			'slug'      => $category['key'], // Note: uses key for internal slug, not URL slug.
			'finderKey' => $category['finder_key'],
		];
	}
	// Default to escooter.
	return [ 'key' => 'escooter', 'name' => 'E-Scooters', 'slug' => 'escooter', 'finderKey' => 'escooter' ];
}
