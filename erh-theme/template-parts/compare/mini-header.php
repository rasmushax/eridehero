<?php
/**
 * Compare Mini Header - Sticky header in specs section.
 *
 * Uses table structure to align with spec tables.
 * Structure matches JS renderMiniHeader() in compare-results.js for CSS compatibility.
 *
 * Prices are hydrated by JS via hydrateMiniHeader() to ensure correct geo-aware
 * pricing (LSCache serves same HTML to all users, JS loads geo-specific prices).
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type array[] $products          Products from erh_get_compare_products().
 *     @type bool    $is_single_product Whether this is a single-product compare view.
 * }
 */

defined( 'ABSPATH' ) || exit;

$products          = $args['products'] ?? array();
$is_single_product = $args['is_single_product'] ?? false;

if ( empty( $products ) ) {
	return;
}
?>

<div class="compare-mini-header">
	<table class="compare-mini-table">
		<colgroup>
			<col class="compare-spec-col-label">
			<?php foreach ( $products as $product ) : ?>
				<col>
			<?php endforeach; ?>
			<?php if ( $is_single_product ) : ?>
				<col class="compare-spec-col-placeholder">
			<?php endif; ?>
		</colgroup>
		<tr>
			<td class="compare-mini-label">
				<label class="compare-diff-toggle">
					<input type="checkbox" data-diff-toggle>
					<span class="compare-diff-toggle-switch"></span>
					<span class="compare-diff-toggle-label">Differences only</span>
				</label>
			</td>
			<?php foreach ( $products as $product ) : ?>
				<?php
				$score    = $product['rating'] ?? null;
				$buy_link = $product['buy_link'] ?? $product['url'];
				?>
				<td>
					<div class="compare-mini-product">
						<div class="compare-mini-thumb-wrap">
							<?php if ( ! empty( $product['thumbnail'] ) ) : ?>
								<img src="<?php echo esc_url( $product['thumbnail'] ); ?>"
									 alt=""
									 class="compare-mini-thumb">
							<?php endif; ?>
						</div>
						<?php erh_the_score_ring( $score, 'sm' ); ?>
						<div class="compare-mini-info">
							<span class="compare-mini-name"><?php echo esc_html( $product['name'] ); ?></span>
							<span class="compare-mini-price"
								  data-mini-price-placeholder
								  data-product-id="<?php echo esc_attr( $product['id'] ); ?>"
								  data-buy-link="<?php echo esc_url( $buy_link ); ?>">
								<?php // JS will hydrate with price link or hide if no price. ?>
							</span>
						</div>
					</div>
				</td>
			<?php endforeach; ?>
			<?php if ( $is_single_product ) : ?>
				<td>
					<div class="compare-mini-product compare-mini-product--placeholder">
						<button class="compare-mini-add-btn" data-open-add-modal>
							<svg class="icon" width="16" height="16" aria-hidden="true"><use href="#icon-plus"></use></svg>
							<span>Add product</span>
						</button>
					</div>
				</td>
			<?php endif; ?>
		</tr>
	</table>
</div>
