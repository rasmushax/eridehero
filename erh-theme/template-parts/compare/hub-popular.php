<?php
/**
 * Compare Hub Popular Section
 *
 * Shows top 4 popular comparisons per category based on view tracking.
 * Uses a 2-column layout: category name on left, 2-col grid of compare-cards on right.
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

use ERH\CategoryConfig;

// Build categories array from config (excluding hoverboard for now).
$categories = [];
foreach ( CategoryConfig::get_all() as $key => $cat ) {
	if ( $key === 'hoverboard' ) {
		continue; // Skip hoverboards for popular section.
	}
	$categories[ $key ] = [
		'name' => $cat['name'],
		'type' => $cat['type'],
		'slug' => $cat['slug'],
	];
}

// Get comparison views DB instance.
$views_db = null;
if ( class_exists( 'ERH\\Database\\ComparisonViews' ) ) {
	$views_db = new ERH\Database\ComparisonViews();
}

// Check if we have any popular data at all.
$has_any_popular = false;
$popular_by_category = [];

if ( $views_db ) {
	foreach ( $categories as $key => $cat ) {
		$popular = $views_db->get_popular( $key, 4 );
		if ( ! empty( $popular ) ) {
			$has_any_popular = true;
			$popular_by_category[ $key ] = $popular;
		}
	}
}

// If no tracked data yet, don't show this section.
if ( ! $has_any_popular ) {
	return;
}
?>

<section class="compare-hub-popular">
	<div class="container">
		<div class="compare-hub-section-header">
			<h2 class="compare-hub-section-title">Most Compared</h2>
			<p class="compare-hub-section-subtitle">See what others are comparing</p>
		</div>

		<div class="compare-popular-categories">
			<?php foreach ( $popular_by_category as $category_key => $popular_items ) : ?>
				<?php $category_data = $categories[ $category_key ]; ?>

				<div class="compare-popular-category">
					<div class="compare-popular-category-info">
						<h3 class="compare-popular-category-title"><?php echo esc_html( $category_data['name'] ); ?></h3>
						<a href="<?php echo esc_url( home_url( '/compare/' . $category_data['slug'] . '/' ) ); ?>" class="compare-popular-category-link">
							View all
							<?php erh_the_icon( 'arrow-right' ); ?>
						</a>
					</div>

					<div class="compare-popular-grid">
						<?php foreach ( $popular_items as $item ) : ?>
							<?php
							$product_1_id    = (int) $item['product_1_id'];
							$product_2_id    = (int) $item['product_2_id'];
							$product_1_name  = $item['product_1_name'] ?? get_the_title( $product_1_id );
							$product_2_name  = $item['product_2_name'] ?? get_the_title( $product_2_id );
							$product_1_thumb = get_the_post_thumbnail_url( $product_1_id, 'medium' );
							$product_2_thumb = get_the_post_thumbnail_url( $product_2_id, 'medium' );

							// Check for curated comparison.
							$curated_id = null;
							if ( $views_db && method_exists( $views_db, 'get_curated_comparison' ) ) {
								$curated_id = $views_db->get_curated_comparison( $product_1_id, $product_2_id );
							}

							// Generate URL.
							$compare_url = $curated_id
								? get_permalink( $curated_id )
								: erh_get_compare_url( [ $product_1_id, $product_2_id ] );
							?>

							<a href="<?php echo esc_url( $compare_url ); ?>" class="compare-card">
								<div class="compare-card-images">
									<div class="compare-card-product">
										<?php if ( $product_1_thumb ) : ?>
											<img src="<?php echo esc_url( $product_1_thumb ); ?>" alt="<?php echo esc_attr( $product_1_name ); ?>">
										<?php else : ?>
											<div class="compare-card-placeholder"><?php erh_the_icon( 'image' ); ?></div>
										<?php endif; ?>
									</div>
									<span class="compare-card-vs">VS</span>
									<div class="compare-card-product">
										<?php if ( $product_2_thumb ) : ?>
											<img src="<?php echo esc_url( $product_2_thumb ); ?>" alt="<?php echo esc_attr( $product_2_name ); ?>">
										<?php else : ?>
											<div class="compare-card-placeholder"><?php erh_the_icon( 'image' ); ?></div>
										<?php endif; ?>
									</div>
								</div>
								<div class="compare-card-body">
									<h4 class="compare-card-title"><?php echo esc_html( $product_1_name ); ?> vs <?php echo esc_html( $product_2_name ); ?></h4>
								</div>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
