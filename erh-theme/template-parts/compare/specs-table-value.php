<?php
/**
 * Compare Specs Table - Value Analysis section with empty cells for JS hydration.
 *
 * Prices are geo-dependent and can't be cached, so PHP renders empty cells
 * and JS populates them based on user's actual geo.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type string  $group_name      Category name ("Value Analysis").
 *     @type string  $group_slug      Slug for ID.
 *     @type array[] $specs           Spec definitions.
 *     @type array[] $products        Products array.
 *     @type string  $currency_symbol Default currency symbol (fallback).
 * }
 */

defined( 'ABSPATH' ) || exit;

$group_name = $args['group_name'] ?? 'Value Analysis';
$group_slug = $args['group_slug'] ?? 'value-analysis';
$specs      = $args['specs'] ?? array();
$products   = $args['products'] ?? array();

if ( empty( $specs ) || empty( $products ) ) {
	return;
}
?>

<div id="<?php echo esc_attr( $group_slug ); ?>" class="compare-spec-category" data-section="<?php echo esc_attr( $group_slug ); ?>" data-value-analysis>
	<h3 class="compare-spec-category-title"><?php echo esc_html( $group_name ); ?></h3>

	<table class="compare-spec-table">
		<colgroup>
			<col class="compare-spec-col-label">
			<?php foreach ( $products as $product ) : ?>
				<col>
			<?php endforeach; ?>
		</colgroup>
		<tbody>
			<?php foreach ( $specs as $spec ) :
				// Use placeholder label - JS will replace with geo-aware symbol.
				$label = str_replace( '{symbol}', '$', $spec['label'] ?? '' );
				$key   = $spec['key'] ?? '';
				?>
				<tr data-spec-key="<?php echo esc_attr( $key ); ?>">
					<td>
						<div class="compare-spec-label" data-label-template="<?php echo esc_attr( $spec['label'] ?? '' ); ?>">
							<?php echo esc_html( $label ); ?>
							<?php if ( ! empty( $spec['tooltip'] ) ) : ?>
								<span class="info-trigger" data-tooltip="<?php echo esc_attr( $spec['tooltip'] ); ?>" data-tooltip-trigger="click">
									<?php erh_the_icon( 'info', '', [ 'width' => '14', 'height' => '14' ] ); ?>
								</span>
							<?php endif; ?>
						</div>
					</td>
					<?php foreach ( $products as $product ) : ?>
						<td data-product-id="<?php echo esc_attr( $product['id'] ); ?>">—</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Mobile stacked cards (JS hydrates with geo-aware pricing) -->
	<div class="compare-spec-cards" data-value-analysis-mobile>
		<?php foreach ( $specs as $spec ) :
			$label = str_replace( '{symbol}', '$', $spec['label'] ?? '' );
			$key   = $spec['key'] ?? '';
			?>
			<div class="compare-spec-card" data-spec-key="<?php echo esc_attr( $key ); ?>">
				<div class="compare-spec-card-label" data-label-template="<?php echo esc_attr( $spec['label'] ?? '' ); ?>">
					<?php echo esc_html( $label ); ?>
					<?php if ( ! empty( $spec['tooltip'] ) ) : ?>
						<span class="info-trigger" data-tooltip="<?php echo esc_attr( $spec['tooltip'] ); ?>" data-tooltip-trigger="click">
							<?php erh_the_icon( 'info', '', [ 'width' => '14', 'height' => '14' ] ); ?>
						</span>
					<?php endif; ?>
				</div>
				<div class="compare-spec-card-values">
					<?php foreach ( $products as $product ) :
						$thumb = $product['thumbnail'] ?? '';
						$name  = $product['name'] ?? '';
						$img   = $thumb ? sprintf( '<img src="%s" alt="">', esc_url( $thumb ) ) : '';
						?>
						<div class="compare-spec-card-value" data-product-id="<?php echo esc_attr( $product['id'] ); ?>">
							<span class="compare-spec-card-product"><?php echo $img . esc_html( $name ); ?></span>
							<span class="compare-spec-card-data">
								<span class="compare-spec-card-text">—</span>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
