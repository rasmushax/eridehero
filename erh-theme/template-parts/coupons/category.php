<?php
/**
 * Coupon Category Page
 *
 * Lists active coupon codes for a product category, grouped by retailer.
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
			<h1 class="coupons-title"><?php echo esc_html( $category_name ); ?> Coupon Codes &mdash; <?php echo esc_html( $month_year ); ?></h1>
			<p class="coupons-subtitle">
				Updated <?php echo esc_html( $updated_date ); ?>
				<?php if ( $coupon_count > 0 ) : ?>
					&middot; <?php printf( esc_html( _n( '%d active coupon', '%d active coupons', $coupon_count, 'erh-core' ) ), $coupon_count ); ?>
				<?php endif; ?>
			</p>
		</div>
	</section>

	<!-- Affiliate Disclaimer -->
	<section class="coupons-disclaimer">
		<div class="container">
			<p>These coupon codes are exclusive to ERideHero. We partner directly with retailers to bring you verified discounts.
				ERideHero earns a commission on qualifying purchases at no extra cost to you.
				<a href="<?php echo esc_url( home_url( '/disclaimers/' ) ); ?>">Learn more</a></p>
		</div>
	</section>

	<!-- Intro -->
	<section class="coupons-intro">
		<div class="container">
			<p>Save on your next <?php echo esc_html( strtolower( $category_name ) ); ?> with exclusive coupon codes and discounts from top retailers.
				We verify every code and update this page regularly so you always have working promo codes.</p>
		</div>
	</section>

	<!-- Coupon Cards -->
	<section class="coupons-list">
		<div class="container">
			<?php if ( empty( $grouped ) ) : ?>
				<div class="coupons-empty">
					<p>No active coupon codes for <?php echo esc_html( strtolower( $category['name_plural'] ) ); ?> right now. Check back soon!</p>
				</div>
			<?php else : ?>
				<?php foreach ( $grouped as $group ) :
					$retailer = $group['retailer'];
					$retailer_name = $retailer['name'] ?? 'Unknown';
					$logo_url = $retailer['logo_url'] ?? null;
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
								$has_url = ! empty( $coupon['url'] );
								$expires_text = '';
								if ( $coupon['expires'] ) {
									$expires_text = date_i18n( 'M j, Y', strtotime( $coupon['expires'] ) );
								}
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
											<div class="coupon-code-box" data-code="<?php echo esc_attr( $coupon['code'] ); ?>">
												<code class="coupon-code-text"><?php echo esc_html( $coupon['code'] ); ?></code>
												<button type="button" class="coupon-copy-btn" aria-label="Copy coupon code">
													<?php erh_the_icon( 'copy' ); ?>
													<span class="coupon-copy-label">Copy</span>
												</button>
											</div>
											<?php if ( $has_url ) : ?>
												<a href="<?php echo esc_url( $coupon['url'] ); ?>" class="coupon-get-deal" target="_blank" rel="sponsored noopener">Get Deal</a>
											<?php endif; ?>
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
	</section>

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
// Coupon copy-to-clipboard
document.querySelectorAll('.coupon-code-box').forEach(box => {
	const btn = box.querySelector('.coupon-copy-btn');
	const label = box.querySelector('.coupon-copy-label');
	if (!btn) return;

	btn.addEventListener('click', () => {
		const code = box.dataset.code;
		navigator.clipboard.writeText(code).then(() => {
			label.textContent = 'Copied!';
			btn.classList.add('copied');
			setTimeout(() => {
				label.textContent = 'Copy';
				btn.classList.remove('copied');
			}, 2000);
		});
	});
});
</script>
