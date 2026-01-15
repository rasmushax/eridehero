<?php
/**
 * Compare Specs Table - Single spec category table.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type string  $group_name      Category name (e.g., "Motor Performance").
 *     @type string  $group_slug      Slug for ID (e.g., "motor-performance").
 *     @type array[] $rows            Rows from erh_build_compare_spec_rows().
 *     @type array[] $products        Products array.
 *     @type string  $currency_symbol Currency symbol.
 * }
 */

defined( 'ABSPATH' ) || exit;

$group_name = $args['group_name'] ?? '';
$group_slug = $args['group_slug'] ?? sanitize_title( $group_name );
$rows       = $args['rows'] ?? array();
$products   = $args['products'] ?? array();
$symbol     = $args['currency_symbol'] ?? '$';

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
		</colgroup>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<?php erh_render_compare_spec_row( $row, $products, $symbol ); ?>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
