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

// Sort groups alphabetically by retailer name.
usort( $grouped, function ( $a, $b ) {
	return strcasecmp( $a['retailer']['name'] ?? '', $b['retailer']['name'] ?? '' );
} );

// Get "last verified" date using freshness-aware helper.
$latest_modified = 0;
foreach ( $coupons as $c ) {
	if ( $c['modified'] > $latest_modified ) {
		$latest_modified = $c['modified'];
	}
}
$verified_ts   = erh_coupon_verified_timestamp( $category_key, $latest_modified );
$verified_date = date_i18n( 'F j, Y', $verified_ts );

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
			<p class="coupons-updated">
				Last verified <?php echo esc_html( $verified_date ); ?>
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

				<!-- Affiliate Disclaimer -->
				<div class="coupons-disclaimer">
					<p>We partner with retailers to bring you verified, exclusive coupon codes. ERideHero earns a commission at no extra cost to you.
						<a href="<?php echo esc_url( home_url( '/disclaimers/' ) ); ?>">Learn more</a></p>
				</div>

				<!-- Intro -->
				<div class="coupons-intro">
					<p>Save money on your next <?php echo esc_html( strtolower( $category_type ) ); ?> with verified coupon codes and exclusive discounts for <?php echo esc_html( $month_year ); ?>.
						We update this page regularly so you always have working codes from trusted retailers.</p>
				</div>

				<?php if ( count( $grouped ) > 1 ) : ?>
					<!-- Retailer Quick Jump -->
					<nav class="coupons-jumplinks" aria-label="Jump to retailer">
						<span class="coupons-jumplinks-label">Retailers:</span>
						<ul class="coupons-jumplinks-list">
							<?php foreach ( $grouped as $group ) :
								$name = $group['retailer']['name'] ?? 'Unknown';
								$anchor = sanitize_title( $name );
							?>
								<li><a href="#<?php echo esc_attr( $anchor ); ?>" class="coupons-jumplink"><?php echo esc_html( $name ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</nav>
				<?php endif; ?>

				<!-- Coupon Groups -->
				<div class="coupons-list">
					<?php if ( empty( $grouped ) ) : ?>
						<div class="coupons-empty">
							<p>No active coupon codes for <?php echo esc_html( strtolower( $category['name_plural'] ) ); ?> right now. Check back soon!</p>
						</div>
					<?php else : ?>
						<?php foreach ( $grouped as $group ) :
							$retailer       = $group['retailer'];
							$retailer_name  = $retailer['name'] ?? 'Unknown';
							$retailer_anchor = sanitize_title( $retailer_name );
							$logo_url       = $retailer['logo_url'] ?? null;
							$affiliate_url  = $retailer['affiliate_url'] ?? '#';
							$domain         = $retailer['domain'] ?? '';
							$group_count    = count( $group['coupons'] );
						?>
							<div class="coupon-group" id="<?php echo esc_attr( $retailer_anchor ); ?>">
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
									<div class="coupon-group-info">
										<h2 class="coupon-group-name"><?php echo esc_html( $retailer_name ); ?></h2>
										<?php if ( $domain ) : ?>
											<a href="<?php echo esc_url( $affiliate_url ); ?>" class="coupon-group-domain" target="_blank" rel="sponsored noopener">
												<?php echo esc_html( $domain ); ?>
												<?php erh_the_icon( 'external-link' ); ?>
											</a>
										<?php endif; ?>
									</div>
									<span class="coupon-group-count"><?php printf( esc_html( _n( '%d code', '%d codes', $group_count, 'erh-core' ) ), $group_count ); ?></span>
								</div>

								<div class="coupon-group-items">
									<?php foreach ( $group['coupons'] as $coupon ) :
										$expires_text = '';
										if ( $coupon['expires'] ) {
											$expires_text = date_i18n( 'M j, Y', strtotime( $coupon['expires'] ) );
										}
										$coupon_affiliate_url = $coupon['retailer']['affiliate_url'] ?? $affiliate_url;
									?>
										<div class="coupon-item">
											<div class="coupon-item-main">
												<div class="coupon-item-info">
													<?php if ( $coupon['description'] ) : ?>
														<p class="coupon-item-description"><?php echo esc_html( $coupon['description'] ); ?></p>
													<?php endif; ?>

													<div class="coupon-item-meta">
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

												<div class="coupon-item-action">
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
												<div class="coupon-item-terms">
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
$breadcrumb_items = [
	[ 'label' => $category['name'], 'url' => home_url( '/' . $category_slug . '/' ) ],
	[ 'label' => 'Coupons' ],
];
erh_breadcrumb_schema( $breadcrumb_items );

get_footer();
?>
<script>
// Coupon "Get Code" — reveal code, copy to clipboard, open retailer in new tab.
document.querySelectorAll('.coupon-get-code-btn').forEach(btn => {
	btn.addEventListener('click', () => {
		if (btn.classList.contains('revealed')) return;

		const code = btn.dataset.code;
		const url = btn.dataset.url;
		const label = btn.querySelector('.coupon-get-code-label');

		// Reveal the code immediately.
		label.textContent = code;
		btn.classList.add('revealed');

		// Copy to clipboard silently.
		navigator.clipboard.writeText(code);

		// Open retailer in new tab.
		if (url && url !== '#') {
			window.open(url, '_blank', 'noopener');
		}
	});
});
</script>
