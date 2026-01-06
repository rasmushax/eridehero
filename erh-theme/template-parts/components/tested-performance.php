<?php
/**
 * Tested Performance Component
 *
 * Displays GPS-verified performance test data for products.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int $product_id The product ID to display performance data for.
 * }
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_id = $args['product_id'] ?? 0;

if ( ! $product_id ) {
    return;
}

// Get all performance fields.
$tested_top_speed       = get_field( 'tested_top_speed', $product_id );
$accel_0_15             = get_field( 'acceleration_0_15_mph', $product_id );
$accel_0_20             = get_field( 'acceleration_0_20_mph', $product_id );
$accel_0_25             = get_field( 'acceleration_0_25_mph', $product_id );
$accel_0_30             = get_field( 'acceleration_0_30_mph', $product_id );
$range_slow             = get_field( 'tested_range_slow', $product_id );
$range_regular          = get_field( 'tested_range_regular', $product_id );
$range_fast             = get_field( 'tested_range_fast', $product_id );
$range_avg_speed_slow   = get_field( 'tested_range_avg_speed_slow', $product_id );
$range_avg_speed_regular = get_field( 'tested_range_avg_speed_regular', $product_id );
$range_avg_speed_fast   = get_field( 'tested_range_avg_speed_fast', $product_id );
$brake_distance         = get_field( 'brake_distance', $product_id );
$hill_climbing          = get_field( 'hill_climbing', $product_id );

// Check if we have any tested data to show.
$has_data = $tested_top_speed || $accel_0_15 || $accel_0_20 || $accel_0_25 || $accel_0_30
         || $range_slow || $range_regular || $range_fast
         || $brake_distance || $hill_climbing;

if ( ! $has_data ) {
    return;
}

// Check if we have range data.
$has_range = $range_slow || $range_regular || $range_fast;

// Check if we have acceleration data.
$has_accel = $accel_0_15 || $accel_0_20 || $accel_0_25 || $accel_0_30;

// Tooltips for test methodology.
$tooltips = array(
    'top_speed'   => 'GPS-verified max speed on flat ground, averaged between two opposite runs. 175 lb rider, 80%+ battery.',
    'range'       => 'Real-world range on mixed terrain (city, country, minor hills). Three tests at different riding intensities from 100% to empty. 175 lb rider.',
    'accel'       => 'Median time from standstill to target speed over 10+ runs. Max acceleration setting. 175 lb rider, 80%+ battery.',
    'hill'        => 'Avg speed climbing 250 ft at 8% grade from kick-off start. Average of 5+ runs. 175 lb rider, 80%+ battery.',
    'braking'     => 'Avg stopping distance from 15 mph with all brakes applied (max force, no lockup). Average of 10+ runs. 175 lb rider.',
);
?>

<section class="content-section" id="tested-performance">
    <div class="section-header">
        <h2 class="section-title">Tested performance</h2>
        <div class="popover-wrapper">
            <button type="button" class="btn btn-link btn-sm" data-popover-trigger="how-we-test-popover">
                <svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg>
                How we test
            </button>
            <div id="how-we-test-popover" class="popover popover--top" aria-hidden="true">
                <div class="popover-arrow"></div>
                <h4 class="popover-title">Data-driven testing</h4>
                <p class="popover-text">All performance data is captured using a VBox Sport GPS logger — professional-grade equipment for precise vehicle measurements. Tests follow strict protocols with a 175 lb rider under controlled conditions.</p>
                <a href="/how-we-test/" class="popover-link">
                    Full methodology
                    <svg class="icon" aria-hidden="true"><use href="#icon-arrow-right"></use></svg>
                </a>
            </div>
        </div>
    </div>

    <dl class="perf-list">
        <?php if ( $tested_top_speed ) : ?>
            <div class="perf-list-item">
                <dt>
                    Top speed
                    <span class="info-trigger" data-tooltip="<?php echo esc_attr( $tooltips['top_speed'] ); ?>" data-tooltip-position="top">
                        <svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg>
                    </span>
                </dt>
                <dd><?php echo esc_html( $tested_top_speed ); ?> mph</dd>
            </div>
        <?php endif; ?>

        <?php if ( $has_range ) : ?>
            <div class="perf-list-item perf-list-item--parent">
                <dt>
                    Range
                    <span class="info-trigger" data-tooltip="<?php echo esc_attr( $tooltips['range'] ); ?>" data-tooltip-position="top">
                        <svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg>
                    </span>
                </dt>
            </div>
            <?php if ( $range_slow ) : ?>
                <div class="perf-list-item perf-list-item--child">
                    <dt>
                        Range priority
                        <?php if ( $range_avg_speed_slow ) : ?>
                            <span class="perf-meta">@ <?php echo esc_html( $range_avg_speed_slow ); ?> mph avg</span>
                        <?php endif; ?>
                    </dt>
                    <dd><?php echo esc_html( $range_slow ); ?> mi</dd>
                </div>
            <?php endif; ?>
            <?php if ( $range_regular ) : ?>
                <div class="perf-list-item perf-list-item--child">
                    <dt>
                        Regular
                        <?php if ( $range_avg_speed_regular ) : ?>
                            <span class="perf-meta">@ <?php echo esc_html( $range_avg_speed_regular ); ?> mph avg</span>
                        <?php endif; ?>
                    </dt>
                    <dd><?php echo esc_html( $range_regular ); ?> mi</dd>
                </div>
            <?php endif; ?>
            <?php if ( $range_fast ) : ?>
                <div class="perf-list-item perf-list-item--child">
                    <dt>
                        Speed priority
                        <?php if ( $range_avg_speed_fast ) : ?>
                            <span class="perf-meta">@ <?php echo esc_html( $range_avg_speed_fast ); ?> mph avg</span>
                        <?php endif; ?>
                    </dt>
                    <dd><?php echo esc_html( $range_fast ); ?> mi</dd>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ( $has_accel ) : ?>
            <div class="perf-list-item perf-list-item--parent">
                <dt>
                    Acceleration
                    <span class="info-trigger" data-tooltip="<?php echo esc_attr( $tooltips['accel'] ); ?>" data-tooltip-position="top">
                        <svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg>
                    </span>
                </dt>
            </div>
            <?php if ( $accel_0_15 ) : ?>
                <div class="perf-list-item perf-list-item--child">
                    <dt>0–15 mph</dt>
                    <dd><?php echo esc_html( $accel_0_15 ); ?> sec</dd>
                </div>
            <?php endif; ?>
            <?php if ( $accel_0_20 ) : ?>
                <div class="perf-list-item perf-list-item--child">
                    <dt>0–20 mph</dt>
                    <dd><?php echo esc_html( $accel_0_20 ); ?> sec</dd>
                </div>
            <?php endif; ?>
            <?php if ( $accel_0_25 ) : ?>
                <div class="perf-list-item perf-list-item--child">
                    <dt>0–25 mph</dt>
                    <dd><?php echo esc_html( $accel_0_25 ); ?> sec</dd>
                </div>
            <?php endif; ?>
            <?php if ( $accel_0_30 ) : ?>
                <div class="perf-list-item perf-list-item--child">
                    <dt>0–30 mph</dt>
                    <dd><?php echo esc_html( $accel_0_30 ); ?> sec</dd>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ( $hill_climbing ) : ?>
            <div class="perf-list-item">
                <dt>
                    Hill climb
                    <span class="info-trigger" data-tooltip="<?php echo esc_attr( $tooltips['hill'] ); ?>" data-tooltip-position="top">
                        <svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg>
                    </span>
                    <span class="perf-meta">8% grade</span>
                </dt>
                <dd><?php echo esc_html( $hill_climbing ); ?> mph avg</dd>
            </div>
        <?php endif; ?>

        <?php if ( $brake_distance ) : ?>
            <div class="perf-list-item">
                <dt>
                    Braking
                    <span class="info-trigger" data-tooltip="<?php echo esc_attr( $tooltips['braking'] ); ?>" data-tooltip-position="top">
                        <svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg>
                    </span>
                    <span class="perf-meta">15–0 mph</span>
                </dt>
                <dd><?php echo esc_html( $brake_distance ); ?> ft</dd>
            </div>
        <?php endif; ?>
    </dl>
</section>
