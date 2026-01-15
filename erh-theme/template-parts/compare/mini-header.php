<?php
/**
 * Compare Mini Header - Sticky header in specs section.
 *
 * Uses table structure to align with spec tables.
 * Structure matches JS renderMiniHeader() in compare-results.js for CSS compatibility.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type array[] $products        Products from erh_get_compare_products().
 *     @type string  $geo             Geo region.
 *     @type string  $currency_symbol Currency symbol.
 * }
 */

defined( 'ABSPATH' ) || exit;

$products = $args['products'] ?? array();
$geo      = $args['geo'] ?? 'US';
$symbol   = $args['currency_symbol'] ?? erh_get_currency_symbol( $geo );

if ( empty( $products ) ) {
	return;
}

// Score ring calculation constants (0-100 scale) - matches JS.
$radius        = 15;
$circumference = 2 * M_PI * $radius;
?>

<div class="compare-mini-header">
	<table class="compare-mini-table">
		<colgroup>
			<col class="compare-spec-col-label">
			<?php foreach ( $products as $product ) : ?>
				<col>
			<?php endforeach; ?>
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
				$score        = $product['rating'] ?? null;
				$has_price    = ! empty( $product['current_price'] );
				$buy_link     = $product['buy_link'] ?? $product['url'];
				$has_retailer = $product['retailer'] && $buy_link;
				$price        = $has_price ? $symbol . number_format( $product['current_price'] ) : '';

				// Calculate score ring offset.
				$score_percent = $score ? min( 100, max( 0, $score ) ) : 0;
				$offset        = $circumference - ( $score_percent / 100 ) * $circumference;
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
						<?php if ( $score ) : ?>
							<div class="compare-mini-score" title="<?php echo esc_attr( $score ); ?> points">
								<svg class="compare-mini-score-ring" viewBox="0 0 36 36">
									<circle class="compare-mini-score-track" cx="18" cy="18" r="<?php echo esc_attr( $radius ); ?>" />
									<circle class="compare-mini-score-progress" cx="18" cy="18" r="<?php echo esc_attr( $radius ); ?>"
											style="stroke-dasharray: <?php echo esc_attr( $circumference ); ?>; stroke-dashoffset: <?php echo esc_attr( $offset ); ?>;" />
								</svg>
								<span class="compare-mini-score-value"><?php echo esc_html( round( $score ) ); ?></span>
							</div>
						<?php endif; ?>
						<div class="compare-mini-info">
							<span class="compare-mini-name"><?php echo esc_html( $product['name'] ); ?></span>
							<?php if ( $has_retailer ) : ?>
								<a href="<?php echo esc_url( $buy_link ); ?>"
								   class="compare-mini-price"
								   target="_blank"
								   rel="noopener">
									<?php echo esc_html( $price ); ?>
									<?php erh_the_icon( 'external-link', '', array( 'width' => '12', 'height' => '12' ) ); ?>
								</a>
							<?php endif; ?>
						</div>
					</div>
				</td>
			<?php endforeach; ?>
		</tr>
	</table>
</div>
