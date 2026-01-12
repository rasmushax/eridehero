<?php
/**
 * Tristate Filter Template Part
 *
 * Renders a three-state filter (Any / Yes / No) as a segmented control.
 *
 * @package ERideHero
 *
 * Expected $args:
 * - key:    string Filter key (e.g., 'foldable')
 * - config: array  Filter configuration
 * - counts: array  Counts [ 'yes' => N, 'no' => N ]
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$key    = $args['key'] ?? '';
$cfg    = $args['config'] ?? [];
$counts = $args['counts'] ?? [ 'yes' => 0, 'no' => 0 ];

if ( empty( $key ) || empty( $cfg ) ) {
    return;
}

$label     = $cfg['label'] ?? ucfirst( $key );
$field     = $cfg['field'] ?? $key;
$yes_count = $counts['yes'] ?? 0;
$no_count  = $counts['no'] ?? 0;
?>

<div class="filter-tristate" data-tristate-filter="<?php echo esc_attr( $key ); ?>">
    <span class="filter-tristate-label"><?php echo esc_html( $label ); ?></span>
    <div class="filter-tristate-control" role="radiogroup" aria-label="<?php echo esc_attr( $label ); ?>">
        <button type="button"
                class="filter-tristate-btn is-active"
                data-value="any"
                role="radio"
                aria-checked="true">
            Any
        </button>
        <button type="button"
                class="filter-tristate-btn"
                data-value="yes"
                role="radio"
                aria-checked="false"
                title="<?php echo esc_attr( $yes_count ); ?> products">
            Yes
        </button>
        <button type="button"
                class="filter-tristate-btn"
                data-value="no"
                role="radio"
                aria-checked="false"
                title="<?php echo esc_attr( $no_count ); ?> products">
            No
        </button>
    </div>
    <input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="any" data-tristate-input>
</div>
