<?php
/**
 * Range Filter Template Part
 *
 * Renders a range slider filter with inputs, distribution histogram, and optional presets.
 *
 * @package ERideHero
 *
 * Expected $args:
 * - key:          string Filter key (e.g., 'speed')
 * - config:       array  Filter configuration
 * - min:          int    Minimum value
 * - max:          int    Maximum value
 * - distribution: array  Distribution histogram (10 values, 0-100)
 * - bare:         bool   If true, skip filter-item wrapper (for use in quick groups)
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$key           = $args['key'] ?? '';
$cfg           = $args['config'] ?? [];
$min           = $args['min'] ?? 0;
$max           = $args['max'] ?? 100;
$distribution  = $args['distribution'] ?? [];
$preset_counts = $args['preset_counts'] ?? [];
$bare          = $args['bare'] ?? false;

if ( empty( $key ) || empty( $cfg ) || $max <= 0 ) {
    return;
}

$label       = $cfg['label'] ?? ucfirst( $key );
$prefix      = $cfg['prefix'] ?? '';
$suffix      = $cfg['suffix'] ?? '';
$presets     = $cfg['presets'] ?? [];
$filter_mode = $cfg['filter_mode'] ?? 'range';
$slider_mode = $cfg['slider_mode'] ?? 'range'; // 'range' (dual thumb) or 'single'
$input_type  = $cfg['input_type'] ?? 'default'; // 'default', 'height' (ft/in), 'height_metric' (cm)
$is_open     = $cfg['is_open'] ?? false;

$is_contains     = $filter_mode === 'contains';
$is_single_thumb = $slider_mode === 'single';
$is_height_input = $input_type === 'height';

// Step value for inputs (use round_factor, default to 1 for integers).
$step = $cfg['round_factor'] ?? 1;

// Range slider inner content (reused in both modes).
$render_range_content = function () use ( $key, $min, $max, $prefix, $suffix, $distribution, $presets, $preset_counts, $filter_mode, $slider_mode, $input_type, $label, $is_contains, $is_single_thumb, $is_height_input, $step ) {
    ?>
    <div class="filter-range" data-range-filter="<?php echo esc_attr( $key ); ?>" data-min="<?php echo esc_attr( $min ); ?>" data-max="<?php echo esc_attr( $max ); ?>" data-step="<?php echo esc_attr( $step ); ?>"<?php
        if ( $is_contains ) echo ' data-filter-mode="contains"';
        if ( $is_single_thumb ) echo ' data-slider-mode="single"';
    ?>>

        <?php if ( $is_contains ) : ?>
            <?php if ( $is_height_input ) : ?>
                <!-- Height input: feet and inches -->
                <div class="filter-height-input" data-height-input>
                    <div class="filter-height-group">
                        <input type="number" class="filter-height-feet" data-height-feet
                               min="4" max="7" placeholder="5" inputmode="numeric">
                        <span class="filter-height-label">ft</span>
                    </div>
                    <div class="filter-height-group">
                        <input type="number" class="filter-height-inches" data-height-inches
                               min="0" max="11" placeholder="10" inputmode="numeric">
                        <span class="filter-height-label">in</span>
                    </div>
                    <!-- Hidden total inches field for filter logic -->
                    <input type="hidden" data-range-value value="">
                </div>
            <?php else : ?>
                <!-- Contains mode: single value input -->
                <div class="filter-range-inputs filter-range-inputs--single">
                    <div class="filter-range-input-group">
                        <?php if ( $prefix ) : ?>
                            <span class="filter-range-prefix"><?php echo esc_html( $prefix ); ?></span>
                        <?php endif; ?>
                        <input type="number" class="filter-range-input" data-range-value value="" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" placeholder="Enter value">
                        <?php if ( $suffix ) : ?>
                            <span class="filter-range-suffix"><?php echo esc_html( $suffix ); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <!-- Range mode: min/max inputs -->
            <div class="filter-range-inputs">
                <div class="filter-range-input-group">
                    <?php if ( $prefix ) : ?>
                        <span class="filter-range-prefix"><?php echo esc_html( $prefix ); ?></span>
                    <?php endif; ?>
                    <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $min ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>">
                    <?php if ( $suffix ) : ?>
                        <span class="filter-range-suffix"><?php echo esc_html( $suffix ); ?></span>
                    <?php endif; ?>
                </div>
                <span class="filter-range-separator">–</span>
                <div class="filter-range-input-group">
                    <?php if ( $prefix ) : ?>
                        <span class="filter-range-prefix"><?php echo esc_html( $prefix ); ?></span>
                    <?php endif; ?>
                    <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $max ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>">
                    <?php if ( $suffix ) : ?>
                        <span class="filter-range-suffix"><?php echo esc_html( $suffix ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $distribution ) && ! $is_contains ) : ?>
            <div class="filter-range-distribution" aria-hidden="true">
                <?php foreach ( $distribution as $height ) : ?>
                    <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ( ! $is_contains || $is_single_thumb ) : ?>
            <!-- Slider: dual-thumb for range mode, single-thumb for contains mode -->
            <div class="filter-range-slider<?php echo $is_single_thumb ? ' filter-range-slider--single' : ''; ?>" data-range-slider>
                <div class="filter-range-track"></div>
                <div class="filter-range-fill" style="<?php echo $is_single_thumb ? '--pos: 0.5;' : '--min: 0; --max: 1;'; ?>"></div>
                <?php if ( ! $is_single_thumb ) : ?>
                    <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                <?php endif; ?>
                <div class="filter-range-handle" data-handle="<?php echo $is_single_thumb ? 'value' : 'max'; ?>" style="--pos: <?php echo $is_single_thumb ? '0.5' : '1'; ?>;"></div>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $presets ) ) : ?>
            <!-- Presets: styled radio group below slider -->
            <div class="filter-presets" role="radiogroup" aria-label="<?php echo esc_attr( $label ); ?> presets">
                <?php foreach ( $presets as $index => $preset ) :
                    // Presets can have min/max (for range mode) or value (for contains mode).
                    $preset_value = '';
                    if ( isset( $preset['value'] ) ) {
                        $preset_value = $preset['value'];
                    } elseif ( isset( $preset['min'] ) || isset( $preset['max'] ) ) {
                        $preset_value = ( $preset['min'] ?? '' ) . '-' . ( $preset['max'] ?? '' );
                    }
                    // Get count from preset_counts array by index.
                    $preset_count = $preset_counts[ $index ] ?? null;
                    ?>
                    <label class="filter-preset">
                        <input type="radio" name="<?php echo esc_attr( $key ); ?>_preset" value="<?php echo esc_attr( $preset_value ); ?>" data-preset<?php
                            if ( isset( $preset['min'] ) ) echo ' data-preset-min="' . esc_attr( $preset['min'] ) . '"';
                            if ( isset( $preset['max'] ) ) echo ' data-preset-max="' . esc_attr( $preset['max'] ) . '"';
                            if ( isset( $preset['value'] ) ) echo ' data-preset-value="' . esc_attr( $preset['value'] ) . '"';
                        ?>>
                        <span class="filter-preset-radio"></span>
                        <span class="filter-preset-label"><?php echo esc_html( $preset['label'] ); ?></span>
                        <?php if ( $preset_count !== null ) : ?>
                            <span class="filter-preset-count"><?php echo esc_html( $preset_count ); ?></span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
};

// Bare mode: just the range content (for quick groups with existing wrapper).
if ( $bare ) :
    $render_range_content();
    return;
endif;

// Standard mode: wrapped in collapsible filter-item.
?>
<div class="filter-item<?php echo $is_open ? ' is-open' : ''; ?>" data-filter-item>
    <button type="button" class="filter-item-header" data-filter-item-toggle>
        <span class="filter-item-label"><?php echo esc_html( $label ); ?></span>
        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
    </button>
    <div class="filter-item-content">
        <?php $render_range_content(); ?>
    </div>
</div>
