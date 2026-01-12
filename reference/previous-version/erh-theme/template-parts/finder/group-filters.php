<?php
/**
 * Group Filters Template Part
 *
 * Renders all filters for a group (range, checkbox, tristate).
 * Can be used for both quick group subgroups and regular groups.
 *
 * @package ERideHero
 *
 * Expected $args:
 * - group:            array  Group/subgroup configuration
 * - range_config:     array  Range filter configurations
 * - checkbox_config:  array  Checkbox filter configurations
 * - tristate_config:  array  Tristate filter configurations
 * - filter_max:       array  Max values for range filters
 * - filter_min:       array  Min values for range filters
 * - filter_dist:      array  Distribution data for range filters
 * - checkbox_options: array  Options for checkbox filters
 * - tristate_counts:  array  Counts for tristate filters
 * - is_collapsible:   bool   Whether to wrap in collapsible filter-item (default: false)
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$group            = $args['group'] ?? [];
$range_config     = $args['range_config'] ?? [];
$checkbox_config  = $args['checkbox_config'] ?? [];
$tristate_config  = $args['tristate_config'] ?? [];
$filter_max       = $args['filter_max'] ?? [];
$filter_min       = $args['filter_min'] ?? [];
$filter_dist      = $args['filter_dist'] ?? [];
$checkbox_options = $args['checkbox_options'] ?? [];
$tristate_counts  = $args['tristate_counts'] ?? [];
$preset_counts    = $args['preset_counts'] ?? [];
$is_collapsible   = $args['is_collapsible'] ?? false;

// Render range filters.
foreach ( $group['range_filters'] ?? [] as $filter_key ) :
    $max = $filter_max[ $filter_key ] ?? 0;
    $cfg = $range_config[ $filter_key ] ?? [];
    $min = $filter_min[ $filter_key ] ?? ( $cfg['default_min'] ?? 0 );
    if ( $max > 0 ) :
        get_template_part(
            'template-parts/finder/range-filter',
            null,
            [
                'key'           => $filter_key,
                'config'        => $cfg,
                'min'           => $min,
                'max'           => $max,
                'distribution'  => $filter_dist[ $filter_key ] ?? [],
                'preset_counts' => $preset_counts[ $filter_key ] ?? [],
                'bare'          => $is_collapsible, // Skip wrapper in quick groups.
            ]
        );
    endif;
endforeach;

// Render checkbox filters.
foreach ( $group['checkbox_filters'] ?? [] as $filter_key ) :
    $options = $checkbox_options[ $filter_key ] ?? [];
    if ( ! empty( $options ) ) :
        get_template_part(
            'template-parts/finder/checkbox-filter',
            null,
            [
                'key'        => $filter_key,
                'config'     => $checkbox_config[ $filter_key ],
                'options'    => $options,
                'standalone' => $is_collapsible,
            ]
        );
    endif;
endforeach;

// Render tristate filters last.
foreach ( $group['tristate_filters'] ?? [] as $filter_key ) :
    if ( isset( $tristate_config[ $filter_key ] ) ) :
        get_template_part(
            'template-parts/finder/tristate-filter',
            null,
            [
                'key'    => $filter_key,
                'config' => $tristate_config[ $filter_key ],
                'counts' => $tristate_counts[ $filter_key ] ?? [ 'yes' => 0, 'no' => 0 ],
            ]
        );
    endif;
endforeach;

// Render in-stock toggle if configured.
if ( $group['has_in_stock'] ?? false ) :
    ?>
    <label class="filter-toggle" style="margin-top: var(--space-4);">
        <input type="checkbox" name="in_stock" value="1" data-filter="in_stock">
        <span class="filter-toggle-track">
            <span class="filter-toggle-thumb"></span>
        </span>
        <span class="filter-toggle-label">In stock only</span>
    </label>
    <?php
endif;
