<?php
/**
 * Compare Overview Section.
 *
 * Renders skeleton loading state for radar chart (JS renders actual chart).
 * Advantages are fully SSR using spec-based comparison (Versus.com style).
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type array[] $products Products from erh_get_compare_products().
 *     @type string  $category Product category (escooter, ebike, etc.).
 * }
 */

defined( 'ABSPATH' ) || exit;

$products = $args['products'] ?? array();
$category = $args['category'] ?? 'escooter';

if ( count( $products ) < 2 ) {
	return;
}

// Calculate spec-based advantages (independent winners per spec).
$spec_advantages = erh_calculate_spec_advantages( $products );
?>

<div class="compare-overview-grid">
	<!-- Radar Chart Container -->
	<div class="compare-radar" data-radar-container>
		<h3 class="compare-radar-title">Category Scores</h3>
		<!-- Chart rendered by JS -->
		<div class="compare-radar-chart" data-radar-chart></div>
		<!-- Loading skeleton (hidden when JS renders chart via CSS :not(:empty)) -->
		<div class="compare-radar-loading" data-radar-loading>
			<div class="skeleton skeleton--circle compare-radar-skeleton-chart"></div>
			<div class="compare-radar-legend-skeleton">
				<?php foreach ( $products as $product ) : ?>
					<div class="skeleton skeleton--pill"></div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>

	<!-- Advantages (spec-based, Versus.com style) -->
	<div class="compare-advantages">
		<?php foreach ( $products as $idx => $product ) :
			$advantages = $spec_advantages[ $idx ] ?? array();
			?>
			<div class="compare-advantage">
				<?php if ( empty( $advantages ) ) : ?>
					<h4 class="compare-advantage-title">Where <?php echo esc_html( $product['name'] ); ?> wins</h4>
					<p class="compare-advantage-empty">No clear advantages</p>
				<?php else : ?>
					<h4 class="compare-advantage-title">Where <?php echo esc_html( $product['name'] ); ?> wins</h4>
					<ul class="compare-advantage-list">
						<?php foreach ( $advantages as $adv ) : ?>
							<li class="compare-advantage-item">
								<span class="compare-advantage-check">
									<?php erh_the_icon( 'check', '', array( 'width' => '16', 'height' => '16' ) ); ?>
								</span>
								<div class="compare-advantage-content">
									<span class="compare-advantage-text">
										<?php echo esc_html( $adv['text'] ); ?>
										<?php if ( ! empty( $adv['tooltip'] ) ) : ?>
											<span class="info-trigger" data-tooltip="<?php echo esc_attr( $adv['tooltip'] ); ?>" data-tooltip-trigger="click">
												<?php erh_the_icon( 'info', '', array( 'width' => '14', 'height' => '14' ) ); ?>
											</span>
										<?php endif; ?>
									</span>
									<?php if ( ! empty( $adv['comparison'] ) ) : ?>
										<span class="compare-advantage-values"><?php echo esc_html( $adv['comparison'] ); ?></span>
									<?php endif; ?>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
