<?php
/**
 * Hub Tools Section
 *
 * Displays two tool cards side-by-side:
 * 1. Product Finder - Mini form with 4 dropdowns
 * 2. H2H Comparison - Two search inputs for side-by-side comparison
 *
 * Expected args:
 * - category (WP_Term): The category term object
 * - product_type (string): Display name (e.g., "Electric Scooter")
 * - product_type_key (string): Type key for filtering (e.g., "escooter")
 * - short_name (string): Short display name from ACF (e.g., "e-scooters")
 * - finder_url (string): Finder page URL from ACF
 * - product_count (int): Actual product count from DB
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$category         = $args['category'] ?? null;
$product_type     = $args['product_type'] ?? 'Electric Scooter';
$product_type_key = $args['product_type_key'] ?? 'escooter';
$short_name       = $args['short_name'] ?? 'products';
$finder_url       = $args['finder_url'] ?? '';
$product_count    = $args['product_count'] ?? 0;

// JSON URL for comparison.
$upload_dir = wp_upload_dir();
$json_url   = $upload_dir['baseurl'] . '/comparison_products.json';
?>
<section class="section hub-tools" id="tools">
	<div class="container">
		<div class="hub-tools-grid">

			<!-- Product Finder Widget -->
			<div class="hub-tool-card">
				<div class="hub-tool-header">
					<div class="icon-box icon-box-gradient">
						<?php erh_the_icon( 'search' ); ?>
					</div>
					<div>
						<h3 class="hub-tool-title"><?php esc_html_e( 'Product Finder', 'erh' ); ?></h3>
						<p class="hub-tool-subtitle">
							<?php
							/* translators: %1$s: product count, %2$s: product short name (e.g., "e-scooters") */
							printf( esc_html__( 'Browse %1$s+ %2$s', 'erh' ), esc_html( $product_count ), esc_html( $short_name ) );
							?>
						</p>
					</div>
				</div>

				<form class="hub-finder-form" action="<?php echo esc_url( $finder_url ); ?>" method="get">
					<div class="hub-finder-row">
						<div class="hub-finder-field">
							<label class="form-label" for="finder-budget"><?php esc_html_e( 'Budget', 'erh' ); ?></label>
							<select id="finder-budget" name="price_max" data-custom-select data-placeholder="<?php esc_attr_e( 'Any price', 'erh' ); ?>">
								<option value=""><?php esc_html_e( 'Any price', 'erh' ); ?></option>
								<option value="500"><?php esc_html_e( 'Under $500', 'erh' ); ?></option>
								<option value="1000"><?php esc_html_e( 'Under $1,000', 'erh' ); ?></option>
								<option value="1500"><?php esc_html_e( 'Under $1,500', 'erh' ); ?></option>
								<option value="2000"><?php esc_html_e( 'Under $2,000', 'erh' ); ?></option>
								<option value="3000"><?php esc_html_e( 'Under $3,000', 'erh' ); ?></option>
							</select>
						</div>
						<div class="hub-finder-field">
							<label class="form-label" for="finder-usecase"><?php esc_html_e( 'Use Case', 'erh' ); ?></label>
							<select id="finder-usecase" name="use_case" data-custom-select data-placeholder="<?php esc_attr_e( 'Any use', 'erh' ); ?>">
								<option value=""><?php esc_html_e( 'Any use', 'erh' ); ?></option>
								<option value="commute"><?php esc_html_e( 'Commuting', 'erh' ); ?></option>
								<option value="offroad"><?php esc_html_e( 'Off-road', 'erh' ); ?></option>
								<option value="performance"><?php esc_html_e( 'Performance', 'erh' ); ?></option>
								<option value="portable"><?php esc_html_e( 'Portable', 'erh' ); ?></option>
							</select>
						</div>
					</div>
					<div class="hub-finder-row">
						<div class="hub-finder-field">
							<label class="form-label" for="finder-range"><?php esc_html_e( 'Range', 'erh' ); ?></label>
							<select id="finder-range" name="range_min" data-custom-select data-placeholder="<?php esc_attr_e( 'Any range', 'erh' ); ?>">
								<option value=""><?php esc_html_e( 'Any range', 'erh' ); ?></option>
								<option value="15"><?php esc_html_e( '15+ miles', 'erh' ); ?></option>
								<option value="25"><?php esc_html_e( '25+ miles', 'erh' ); ?></option>
								<option value="40"><?php esc_html_e( '40+ miles', 'erh' ); ?></option>
								<option value="60"><?php esc_html_e( '60+ miles', 'erh' ); ?></option>
							</select>
						</div>
						<div class="hub-finder-field">
							<label class="form-label" for="finder-speed"><?php esc_html_e( 'Top Speed', 'erh' ); ?></label>
							<select id="finder-speed" name="speed_min" data-custom-select data-placeholder="<?php esc_attr_e( 'Any speed', 'erh' ); ?>">
								<option value=""><?php esc_html_e( 'Any speed', 'erh' ); ?></option>
								<option value="15"><?php esc_html_e( '15+ mph', 'erh' ); ?></option>
								<option value="25"><?php esc_html_e( '25+ mph', 'erh' ); ?></option>
								<option value="35"><?php esc_html_e( '35+ mph', 'erh' ); ?></option>
								<option value="50"><?php esc_html_e( '50+ mph', 'erh' ); ?></option>
							</select>
						</div>
					</div>
					<button type="submit" class="btn btn-primary btn-lg hub-finder-submit">
						<?php
						/* translators: %s: product short name (e.g., "scooters") */
						printf( esc_html__( 'Find %s', 'erh' ), esc_html( $short_name ) );
						?>
						<?php erh_the_icon( 'arrow-right' ); ?>
					</button>
				</form>

				<div class="hub-tool-footer">
					<a href="<?php echo esc_url( $finder_url ); ?>">
						<?php
						/* translators: %s: product count (e.g., "200+") */
						printf( esc_html__( 'Or browse all %s %s', 'erh' ), esc_html( $product_count ), esc_html( $short_name ) );
						?>
						<?php erh_the_icon( 'arrow-right' ); ?>
					</a>
				</div>
			</div>

			<!-- H2H Compare Widget (Light Theme) -->
			<div class="hub-tool-card">
				<div class="hub-tool-header">
					<div class="icon-box icon-box-gradient">
						<?php erh_the_icon( 'grid' ); ?>
					</div>
					<div>
						<h3 class="hub-tool-title"><?php esc_html_e( 'Compare Head-to-Head', 'erh' ); ?></h3>
						<p class="hub-tool-subtitle"><?php esc_html_e( 'Side-by-side specs comparison', 'erh' ); ?></p>
					</div>
				</div>

				<div class="hub-compare-form"
				     id="hub-comparison-container"
				     data-json-url="<?php echo esc_url( $json_url ); ?>"
				     data-category-filter="<?php echo esc_attr( $product_type_key ); ?>">

					<div class="hub-compare-inputs" id="hub-compare-inputs">
						<!-- First product input -->
						<div class="comparison-input-wrapper comparison-light">
							<input type="text"
							       class="comparison-input"
							       placeholder="<?php
							           /* translators: %s: product short name without trailing 's' */
							           printf( esc_attr__( 'Search %s...', 'erh' ), esc_attr( rtrim( $short_name, 's' ) ) );
							       ?>"
							       autocomplete="off"
							       data-slot="0">
							<button type="button" class="comparison-input-clear" aria-label="<?php esc_attr_e( 'Clear selection', 'erh' ); ?>">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<line x1="18" y1="6" x2="6" y2="18"></line>
									<line x1="6" y1="6" x2="18" y2="18"></line>
								</svg>
							</button>
							<img class="comparison-input-thumb" src="" alt="" aria-hidden="true">
							<div class="comparison-results"></div>
						</div>

						<div class="hub-compare-vs"><?php esc_html_e( 'vs', 'erh' ); ?></div>

						<!-- Second product input -->
						<div class="comparison-input-wrapper comparison-light">
							<input type="text"
							       class="comparison-input"
							       placeholder="<?php
							           /* translators: %s: product short name without trailing 's' */
							           printf( esc_attr__( 'Search %s...', 'erh' ), esc_attr( rtrim( $short_name, 's' ) ) );
							       ?>"
							       autocomplete="off"
							       data-slot="1">
							<button type="button" class="comparison-input-clear" aria-label="<?php esc_attr_e( 'Clear selection', 'erh' ); ?>">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<line x1="18" y1="6" x2="6" y2="18"></line>
									<line x1="6" y1="6" x2="18" y2="18"></line>
								</svg>
							</button>
							<img class="comparison-input-thumb" src="" alt="" aria-hidden="true">
							<div class="comparison-results"></div>
						</div>
					</div>

					<button type="button" class="btn btn-primary btn-lg hub-compare-submit" id="hub-comparison-submit" disabled>
						<?php esc_html_e( 'Compare specs', 'erh' ); ?>
						<?php erh_the_icon( 'arrow-right' ); ?>
					</button>
				</div>
			</div>

		</div>
	</div>
</section>
