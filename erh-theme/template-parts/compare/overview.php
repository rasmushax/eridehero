<?php
/**
 * Compare Overview Section.
 *
 * Renders skeleton loading state for radar chart (JS renders actual chart).
 * Advantages are fully SSR.
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

// Category score keys mapping (PHP snake_case to display name).
$score_categories = array(
	'escooter' => array(
		'motor_performance' => 'Motor Performance',
		'range_battery'     => 'Range & Battery',
		'ride_quality'      => 'Ride Quality',
		'portability'       => 'Portability',
		'safety'            => 'Safety',
		'features'          => 'Features',
		'maintenance'       => 'Maintenance',
	),
	'ebike' => array(
		'motor_power'       => 'Motor & Power',
		'range_battery'     => 'Range & Battery',
		'speed_performance' => 'Speed & Performance',
		'build_frame'       => 'Build & Frame',
		'components'        => 'Components',
	),
);

$categories = $score_categories[ $category ] ?? $score_categories['escooter'];

// Calculate advantages for each product.
$product_advantages = erh_calculate_product_advantages( $products, $categories );
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

	<!-- Advantages (fully SSR) -->
	<div class="compare-advantages">
		<?php foreach ( $products as $idx => $product ) :
			$advantages = $product_advantages[ $idx ] ?? array();
			?>
			<div class="compare-advantage">
				<?php if ( empty( $advantages ) ) : ?>
					<h4 class="compare-advantage-title"><?php echo esc_html( $product['name'] ); ?></h4>
					<p class="compare-advantage-empty">No clear advantages</p>
				<?php else : ?>
					<h4 class="compare-advantage-title">Why <?php echo esc_html( $product['name'] ); ?> wins</h4>
					<ul class="compare-advantage-list">
						<?php foreach ( $advantages as $adv ) : ?>
							<li>
								<?php erh_the_icon( 'check', '', array( 'width' => '16', 'height' => '16' ) ); ?>
								<span>
									<strong><?php echo esc_html( $adv['diff'] ); ?>%</strong>
									<?php echo esc_html( $adv['direction'] ); ?>
									<?php echo esc_html( strtolower( $adv['label'] ) ); ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
