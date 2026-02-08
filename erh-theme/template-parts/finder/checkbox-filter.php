<?php
/**
 * Checkbox Filter Template Part
 *
 * Renders a checkbox filter with optional search and show all functionality.
 * Supports two modes:
 * - Standalone: Direct content without collapsible wrapper (e.g., Brands group)
 * - Nested: Wrapped in collapsible filter-item (e.g., Motor Position inside Motor group)
 *
 * @package ERideHero
 *
 * Expected $args:
 * - key:     string Filter key (e.g., 'brand')
 * - config:  array  Filter configuration
 * - options: array  Options with counts [ 'Option' => count ]
 * - standalone: bool Whether this is a standalone group (no collapsible wrapper)
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$key        = $args['key'] ?? '';
$cfg        = $args['config'] ?? [];
$options    = $args['options'] ?? [];
$standalone = $args['standalone'] ?? false;

if ( empty( $key ) || empty( $cfg ) || empty( $options ) ) {
    return;
}

$label         = $cfg['label'] ?? ucfirst( $key );
$field         = $cfg['field'] ?? $key;
$visible_limit = $cfg['visible_limit'] ?? 8;
$searchable    = $cfg['searchable'] ?? false;
$is_open       = $cfg['is_open'] ?? false;

$option_count = count( $options );
$has_overflow = $option_count > $visible_limit;
?>

<?php if ( ! $standalone ) : ?>
<div class="filter-item<?php echo $is_open ? ' is-open' : ''; ?>" data-filter-item>
    <button type="button" class="filter-item-header" data-filter-item-toggle>
        <span class="filter-item-label"><?php echo esc_html( $label ); ?></span>
        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
    </button>
    <div class="filter-item-content">
<?php endif; ?>

<?php if ( $searchable && $has_overflow ) : ?>
    <div class="filter-list-search" data-filter-list-search-container="<?php echo esc_attr( $key ); ?>">
        <?php erh_the_icon( 'search' ); ?>
        <input type="text" placeholder="Search <?php echo esc_attr( strtolower( $label ) ); ?>..." data-filter-list-search="<?php echo esc_attr( $key ); ?>">
        <button type="button" class="filter-list-search-clear" data-filter-list-search-clear="<?php echo esc_attr( $key ); ?>" aria-label="Clear search">
            <?php erh_the_icon( 'x' ); ?>
        </button>
    </div>
<?php endif; ?>

<div class="filter-checkbox-list" data-filter-list="<?php echo esc_attr( $key ); ?>" data-limit="<?php echo esc_attr( $visible_limit ); ?>">
    <?php
    $index = 0;
    foreach ( $options as $option_value => $count ) :
        $is_hidden = $has_overflow && $index >= $visible_limit;
        ?>
        <label class="filter-checkbox<?php echo $is_hidden ? ' is-hidden-by-limit' : ''; ?>">
            <input type="checkbox" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $option_value ); ?>" data-filter="<?php echo esc_attr( $field ); ?>">
            <span class="filter-checkbox-box">
                <?php erh_the_icon( 'check' ); ?>
            </span>
            <span class="filter-checkbox-label"><?php echo esc_html( ucfirst( $option_value ) ); ?></span>
            <span class="filter-checkbox-count"><?php echo esc_html( $count ); ?></span>
        </label>
        <?php
        $index++;
    endforeach;
    ?>
</div>

<?php if ( $has_overflow ) : ?>
    <button type="button" class="filter-show-all" data-filter-show-all="<?php echo esc_attr( $key ); ?>">
        <span data-show-text>Show all <?php echo esc_html( $option_count ); ?></span>
        <span data-hide-text hidden>Show less</span>
        <?php erh_the_icon( 'chevron-down' ); ?>
    </button>
    <p class="filter-no-results" data-filter-no-results="<?php echo esc_attr( $key ); ?>" hidden>No <?php echo esc_html( strtolower( $label ) ); ?> found</p>
<?php endif; ?>

<?php if ( ! $standalone ) : ?>
    </div>
</div>
<?php endif; ?>
