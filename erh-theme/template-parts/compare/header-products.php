<?php
/**
 * Compare Header Products - Hero product cards for SSR.
 *
 * Structure matches JS renderProducts() in compare-results.js for CSS compatibility.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type array[] $products Products from erh_get_compare_products().
 *     @type string  $geo      Geo region.
 * }
 */

defined( 'ABSPATH' ) || exit;

$products = $args['products'] ?? array();
$geo      = $args['geo'] ?? 'US';
$symbol   = erh_get_currency_symbol( $geo );

if ( empty( $products ) ) {
	return;
}
?>

<header class="compare-header" data-compare-header>
	<div class="container">
		<div class="compare-header-products" data-compare-products>
			<?php foreach ( $products as $product ) : ?>
				<?php $score = $product['rating'] ?? null; ?>
				<article class="compare-product" data-product-id="<?php echo esc_attr( $product['id'] ); ?>">
					<!-- Score ring (top-left) -->
					<?php erh_the_score_ring( $score, 'md' ); ?>

					<!-- Actions: remove always visible, track button added by JS when price loads -->
					<div class="compare-product-actions">
						<button class="compare-product-remove" data-remove="<?php echo esc_attr( $product['id'] ); ?>" aria-label="Remove from comparison">
							<?php erh_the_icon( 'x', '', array( 'width' => '14', 'height' => '14' ) ); ?>
						</button>
						<!-- Price track button injected by JS -->
					</div>

					<a href="<?php echo esc_url( $product['url'] ); ?>" class="compare-product-link">
						<div class="compare-product-image">
							<?php if ( ! empty( $product['thumbnail'] ) ) : ?>
								<img src="<?php echo esc_url( $product['thumbnail'] ); ?>"
									 alt="<?php echo esc_attr( $product['name'] ); ?>"
									 loading="lazy">
							<?php endif; ?>
							<!-- Price overlay injected by JS -->
						</div>
					</a>

					<div class="compare-product-content">
						<a href="<?php echo esc_url( $product['url'] ); ?>" class="compare-product-name">
							<?php echo esc_html( $product['name'] ); ?>
						</a>
						<!-- CTA placeholder with spinner - JS replaces with actual link or hides if no price -->
						<div class="compare-product-cta compare-product-cta--loading" data-cta-placeholder>
							<svg class="spinner spinner-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
								<path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
							</svg>
						</div>
					</div>
				</article>
			<?php endforeach; ?>

			<div class="compare-product compare-product--add-wrap">
				<button class="compare-product-add-btn" data-open-add-modal>
					<?php erh_the_icon( 'plus', '', array( 'width' => '24', 'height' => '24' ) ); ?>
					<span>Add</span>
				</button>
			</div>
		</div>
	</div>
</header>
