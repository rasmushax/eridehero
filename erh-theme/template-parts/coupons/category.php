<?php
/**
 * Coupon Category Page
 *
 * Lists active coupon codes for a product category, grouped by retailer.
 * Two-column layout: main content + sidebar.
 * E.g., /coupons/electric-scooters/
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

use ERH\PostTypes\Coupon;

get_header();

// Get category data from query var.
$category = erh_get_coupon_category();

if ( ! $category ) {
	get_footer();
	return;
}

$category_key  = $category['key'];
$category_type = $category['type'];
$category_name = $category['name_short'];
$category_slug = $category['slug'];

// Fetch active coupons for this category.
$coupons        = Coupon::get_by_category( $category_key );
$coupon_count   = count( $coupons );
$grouped        = Coupon::group_by_retailer( $coupons );
$month_year     = date_i18n( 'F Y' );

// Find the most recently modified coupon for "Updated" date.
$latest_modified = 0;
foreach ( $coupons as $c ) {
	if ( $c['modified'] > $latest_modified ) {
		$latest_modified = $c['modified'];
	}
}
$updated_date = $latest_modified ? date_i18n( 'F j, Y', $latest_modified ) : date_i18n( 'F j, Y' );

// Get finder/deals page URLs from product_type taxonomy term.
$finder_page = '';
$deals_page  = '';
$taxonomy_slug_map = [
	'escooter'    => 'electric-scooter',
	'ebike'       => 'electric-bike',
	'eskateboard' => 'electric-skateboard',
	'euc'         => 'electric-unicycle',
	'hoverboard'  => 'hoverboard',
];
$tax_slug = $taxonomy_slug_map[ $category_key ] ?? '';
if ( $tax_slug ) {
	$term = get_term_by( 'slug', $tax_slug, 'product_type' );
	if ( $term && ! is_wp_error( $term ) ) {
		$finder_page = get_field( 'finder_page', 'product_type_' . $term->term_id );
		$deals_page  = get_field( 'deals_page', 'product_type_' . $term->term_id );
	}
}
?>

<main class="coupons-page">

	<!-- Breadcrumb -->
	<div class="container">
		<?php
		erh_breadcrumb( [
			[ 'label' => $category['name'], 'url' => home_url( '/' . $category_slug . '/' ) ],
			[ 'label' => 'Coupons' ],
		] );
		?>
	</div>

	<!-- Header -->
	<section class="coupons-header">
		<div class="container">
			<h1 class="coupons-title"><?php echo esc_html( $category_type ); ?> Coupon Codes for <?php echo esc_html( $month_year ); ?></h1>

			<!-- Affiliate Disclaimer -->
			<div class="coupons-disclaimer">
				<p>These coupon codes are exclusive to ERideHero. We partner directly with retailers to bring you verified discounts.
					ERideHero earns a commission on qualifying purchases at no extra cost to you.
					<a href="<?php echo esc_url( home_url( '/disclaimers/' ) ); ?>">Learn more</a></p>
			</div>

			<p class="coupons-updated">
				Updated <?php echo esc_html( $updated_date ); ?>
				<?php if ( $coupon_count > 0 ) : ?>
					&middot; <?php printf( esc_html( _n( '%d active coupon', '%d active coupons', $coupon_count, 'erh-core' ) ), $coupon_count ); ?>
				<?php endif; ?>
			</p>
		</div>
	</section>

	<!-- Two-column layout -->
	<div class="container">
		<div class="coupons-layout-grid">

			<!-- Main Content -->
			<div class="coupons-main">

				<!-- Intro -->
				<div class="coupons-intro">
					<p>Save on your next <?php echo esc_html( strtolower( $category_name ) ); ?> with exclusive coupon codes and discounts from top retailers.
						We verify every code and update this page regularly so you always have working promo codes.</p>
				</div>

				<!-- Coupon Cards -->
				<div class="coupons-list">
					<?php if ( empty( $grouped ) ) : ?>
						<div class="coupons-empty">
							<p>No active coupon codes for <?php echo esc_html( strtolower( $category['name_plural'] ) ); ?> right now. Check back soon!</p>
						</div>
					<?php else : ?>
						<?php foreach ( $grouped as $group ) :
							$retailer = $group['retailer'];
							$retailer_name = $retailer['name'] ?? 'Unknown';
							$logo_url = $retailer['logo_url'] ?? null;
							$affiliate_url = $retailer['affiliate_url'] ?? '#';
						?>
							<div class="coupon-group">
								<div class="coupon-group-header">
									<?php if ( $logo_url ) : ?>
										<img
											src="<?php echo esc_url( $logo_url ); ?>"
											alt="<?php echo esc_attr( $retailer_name ); ?>"
											class="coupon-group-logo"
											loading="lazy"
											decoding="async"
										>
									<?php endif; ?>
									<h2 class="coupon-group-name"><?php echo esc_html( $retailer_name ); ?></h2>
								</div>

								<div class="coupon-group-cards">
									<?php foreach ( $group['coupons'] as $coupon ) :
										$expires_text = '';
										if ( $coupon['expires'] ) {
											$expires_text = date_i18n( 'M j, Y', strtotime( $coupon['expires'] ) );
										}
										$coupon_affiliate_url = $coupon['retailer']['affiliate_url'] ?? $affiliate_url;
									?>
										<div class="coupon-card">
											<div class="coupon-card-main">
												<div class="coupon-card-info">
													<?php if ( $coupon['description'] ) : ?>
														<p class="coupon-card-description"><?php echo esc_html( $coupon['description'] ); ?></p>
													<?php endif; ?>

													<div class="coupon-card-meta">
														<?php if ( $coupon['type'] === 'percent' && $coupon['value'] ) : ?>
															<span class="coupon-badge coupon-badge--percent"><?php echo esc_html( $coupon['value'] ); ?>% off</span>
														<?php elseif ( $coupon['type'] === 'fixed' && $coupon['value'] ) : ?>
															<span class="coupon-badge coupon-badge--fixed">$<?php echo esc_html( $coupon['value'] ); ?> off</span>
														<?php elseif ( $coupon['type'] === 'extras' ) : ?>
															<span class="coupon-badge coupon-badge--extras">Free extras</span>
														<?php elseif ( $coupon['type'] === 'freebie' ) : ?>
															<span class="coupon-badge coupon-badge--freebie">Freebie</span>
														<?php endif; ?>

														<?php if ( $expires_text ) : ?>
															<span class="coupon-expires">Expires <?php echo esc_html( $expires_text ); ?></span>
														<?php else : ?>
															<span class="coupon-ongoing">Ongoing</span>
														<?php endif; ?>

														<?php if ( $coupon['min_order'] ) : ?>
															<span class="coupon-min-order">Min. $<?php echo esc_html( $coupon['min_order'] ); ?></span>
														<?php endif; ?>
													</div>
												</div>

												<div class="coupon-card-action">
													<button
														type="button"
														class="coupon-get-code-btn"
														data-code="<?php echo esc_attr( $coupon['code'] ); ?>"
														data-url="<?php echo esc_attr( $coupon_affiliate_url ); ?>"
													>
														<?php erh_the_icon( 'copy' ); ?>
														<span class="coupon-get-code-label">Get Code</span>
													</button>
												</div>
											</div>

											<?php if ( $coupon['terms'] ) : ?>
												<div class="coupon-card-terms">
													<details>
														<summary>Terms & conditions</summary>
														<p><?php echo esc_html( $coupon['terms'] ); ?></p>
													</details>
												</div>
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

			</div>

			<!-- Sidebar -->
			<aside class="sidebar">
				<?php
				// Tools section (Finder, Deals, Compare)
				get_template_part( 'template-parts/sidebar/tools', null, array(
					'product_type'  => $category_type,
					'category_name' => $category_name,
					'finder_page'   => $finder_page,
					'deals_page'    => $deals_page,
				) );
				?>

				<hr>

				<?php
				// Head-to-head comparison widget (open mode, no locked product)
				get_template_part( 'template-parts/sidebar/comparison-open', null, array(
					'allowed_categories' => [ $category_key ],
				) );
				?>
			</aside>

		</div>
	</div>

</main>

<?php
// Breadcrumb Schema.
$breadcrumb_schema = [
	'@context'        => 'https://schema.org',
	'@type'           => 'BreadcrumbList',
	'itemListElement' => [
		[
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => $category['name'],
			'item'     => home_url( '/' . $category_slug . '/' ),
		],
		[
			'@type'    => 'ListItem',
			'position' => 2,
			'name'     => 'Coupons',
		],
	],
];
?>
<script type="application/ld+json">
<?php echo wp_json_encode( $breadcrumb_schema, JSON_UNESCAPED_SLASHES ); ?>
</script>

<?php
get_footer();
?>
<script>
// Coupon "Get Code" — copy to clipboard + open retailer in new tab
document.querySelectorAll('.coupon-get-code-btn').forEach(btn => {
	btn.addEventListener('click', () => {
		const code = btn.dataset.code;
		const url = btn.dataset.url;
		const label = btn.querySelector('.coupon-get-code-label');

		// Copy code to clipboard.
		navigator.clipboard.writeText(code).then(() => {
			// Show the code + "Copied!" feedback.
			label.textContent = code;
			btn.classList.add('copied');
			setTimeout(() => {
				label.textContent = code;
				btn.classList.remove('copied');
				btn.classList.add('revealed');
			}, 2000);
		});

		// Open retailer homepage (with affiliate link) in new tab.
		if (url && url !== '#') {
			window.open(url, '_blank', 'noopener');
		}
	});
});
</script>
