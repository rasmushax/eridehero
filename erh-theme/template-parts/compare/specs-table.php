<?php
/**
 * Compare Specs Table - Single spec category table.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type string  $group_name        Category name (e.g., "Motor Performance").
 *     @type string  $group_slug        Slug for ID (e.g., "motor-performance").
 *     @type array[] $rows              Rows from erh_build_compare_spec_rows().
 *     @type array[] $products          Products array.
 *     @type string  $currency_symbol   Currency symbol.
 *     @type bool    $is_single_product Whether this is a single-product compare view.
 * }
 */

defined( 'ABSPATH' ) || exit;

$group_name        = $args['group_name'] ?? '';
$group_slug        = $args['group_slug'] ?? sanitize_title( $group_name );
$rows              = $args['rows'] ?? array();
$products          = $args['products'] ?? array();
$symbol            = $args['currency_symbol'] ?? '$';
$is_single_product = $args['is_single_product'] ?? false;
$placeholder_cell  = $is_single_product ? '<td class="compare-spec-placeholder">&mdash;</td>' : '';

if ( empty( $rows ) || empty( $products ) ) {
	return;
}
?>

<div id="<?php echo esc_attr( $group_slug ); ?>" class="compare-spec-category" data-section="<?php echo esc_attr( $group_slug ); ?>">
	<h3 class="compare-spec-category-title"><?php echo esc_html( $group_name ); ?></h3>

	<table class="compare-spec-table">
		<colgroup>
			<col class="compare-spec-col-label">
			<?php foreach ( $products as $product ) : ?>
				<col>
			<?php endforeach; ?>
			<?php if ( $is_single_product ) : ?>
				<col class="compare-spec-col-placeholder">
			<?php endif; ?>
		</colgroup>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<?php erh_render_compare_spec_row( $row, $products, $symbol, $placeholder_cell ); ?>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Mobile stacked cards -->
	<div class="compare-spec-cards">
		<?php foreach ( $rows as $row ) : ?>
			<?php erh_render_mobile_spec_card( $row, $products, $symbol ); ?>
		<?php endforeach; ?>
	</div>
</div>
