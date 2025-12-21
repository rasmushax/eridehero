<?php
/**
 * Template Name: Product Finder
 *
 * Product finder with filters and comparison.
 * Loads from pre-generated JSON files for performance.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Get product type from query var or page slug
$product_type_slug = get_query_var( 'product_type', '' );
if ( empty( $product_type_slug ) ) {
    // Try to get from page slug (e.g., escooter-finder → Electric Scooter)
    $page_slug = get_post_field( 'post_name', get_the_ID() );
    $slug_map = array(
        'escooter-finder'    => 'escooter',
        'ebike-finder'       => 'ebike',
        'skateboard-finder'  => 'skateboard',
        'euc-finder'         => 'euc',
        'hoverboard-finder'  => 'hoverboard',
    );
    $json_type = $slug_map[ $page_slug ] ?? 'escooter';
} else {
    $type_map = array(
        'e-scooters'    => 'escooter',
        'e-bikes'       => 'ebike',
        'e-skateboards' => 'skateboard',
        'eucs'          => 'euc',
        'hoverboards'   => 'hoverboard',
    );
    $json_type = $type_map[ $product_type_slug ] ?? 'escooter';
}

// Page config based on product type
$page_config = array(
    'escooter' => array(
        'title'        => 'Electric Scooter Database',
        'subtitle'     => 'Find your perfect electric scooter from our database of 100+ models',
        'short'        => 'E-Scooter',
        'product_type' => 'Electric Scooter',
    ),
    'ebike' => array(
        'title'        => 'Electric Bike Database',
        'subtitle'     => 'Compare electric bikes with detailed specs and pricing',
        'short'        => 'E-Bike',
        'product_type' => 'Electric Bike',
    ),
    'skateboard' => array(
        'title'        => 'Electric Skateboard Database',
        'subtitle'     => 'Find the perfect electric skateboard for your riding style',
        'short'        => 'E-Skateboard',
        'product_type' => 'Electric Skateboard',
    ),
    'euc' => array(
        'title'        => 'Electric Unicycle Database',
        'subtitle'     => 'Compare EUCs with detailed specs and real-world testing',
        'short'        => 'EUC',
        'product_type' => 'Electric Unicycle',
    ),
    'hoverboard' => array(
        'title'        => 'Hoverboard Database',
        'subtitle'     => 'Compare hoverboards with detailed specs and pricing',
        'short'        => 'Hoverboard',
        'product_type' => 'Hoverboard',
    ),
);

$page_info = $page_config[ $json_type ] ?? $page_config['escooter'];

// Load products from JSON file
$json_file = WP_CONTENT_DIR . '/uploads/finder_' . $json_type . '.json';
$products = array();

if ( file_exists( $json_file ) ) {
    $json_content = file_get_contents( $json_file );
    $products = json_decode( $json_content, true ) ?: array();
}

// Get user's geo for pricing (default to US)
// TODO: Integrate with geo detection service
$user_geo = 'US';

// Extract filter options from products
$brands = array();
$motor_positions = array();
$battery_types = array();
$price_max = 0;
$speed_max = 0;
$range_max = 0;
$weight_max = 0;
$weight_limit_max = 0;
$battery_max = 0;
$voltage_max = 0;
$amphours_max = 0;
$charging_time_max = 0;
$motor_power_max = 0;
$motor_peak_max = 0;

// Helper to extract nested spec value
$get_spec = function( $specs, ...$paths ) {
    foreach ( $paths as $path ) {
        // Handle dot notation for nested paths (e.g., 'dimensions.weight')
        $parts = explode( '.', $path );
        $value = $specs;
        foreach ( $parts as $part ) {
            if ( ! is_array( $value ) || ! isset( $value[ $part ] ) ) {
                $value = null;
                break;
            }
            $value = $value[ $part ];
        }
        if ( $value !== null && $value !== '' ) {
            return $value;
        }
    }
    return null;
};

foreach ( $products as &$product ) {
    $specs = $product['specs'] ?? array();

    // Extract brand from product name (first word before space) or model field
    // e.g., "NIU KQi 300X" → "NIU"
    $brand = '';
    $name = $product['name'] ?? '';
    if ( $name ) {
        $name_parts = explode( ' ', $name );
        $brand = $name_parts[0] ?? '';
    }
    if ( $brand ) {
        $brands[ $brand ] = ( $brands[ $brand ] ?? 0 ) + 1;
    }
    $product['brand'] = $brand;

    // Get pricing for user's geo (fallback to US)
    $pricing = $product['pricing'][ $user_geo ] ?? $product['pricing']['US'] ?? array();
    $current_price = $pricing['current_price'] ?? null;
    $product['current_price'] = $current_price;
    $product['price_formatted'] = $current_price ? erh_format_price( $current_price, $pricing['currency'] ?? 'USD' ) : null;
    $product['in_stock'] = $pricing['instock'] ?? false;
    $product['best_link'] = $pricing['bestlink'] ?? null;

    // Calculate price indicator (% vs 3-month average)
    $avg_price = $pricing['avg_3m'] ?? null;
    $product['price_indicator'] = null;
    if ( $current_price && $avg_price && $avg_price > 0 ) {
        $product['price_indicator'] = round( ( ( $current_price - $avg_price ) / $avg_price ) * 100 );
    }

    // Extract specs from nested structure
    $top_speed = $get_spec( $specs, 'manufacturer_top_speed', 'tested_top_speed' );
    $range = $get_spec( $specs, 'manufacturer_range', 'tested_range_regular' );
    $weight = $get_spec( $specs, 'dimensions.weight', 'weight' );
    $weight_limit = $get_spec( $specs, 'dimensions.max_load', 'max_load', 'max_weight_capacity' );

    // Battery specs
    $battery = $get_spec( $specs, 'battery.capacity', 'battery_capacity' );
    $voltage = $get_spec( $specs, 'battery.voltage', 'voltage' );
    $amphours = $get_spec( $specs, 'battery.amphours', 'amphours' );
    $charging_time = $get_spec( $specs, 'battery.charging_time', 'charging_time' );
    $battery_type = $get_spec( $specs, 'battery.battery_type', 'battery_type' );

    // Motor specs
    $motor_power = $get_spec( $specs, 'motor.power_nominal', 'nominal_motor_wattage' );
    $motor_peak = $get_spec( $specs, 'motor.power_peak', 'peak_motor_wattage' );
    $motor_position = $get_spec( $specs, 'motor.motor_position', 'motor_position' );

    // Store extracted values for JS
    $product['top_speed'] = $top_speed ? floatval( $top_speed ) : null;
    $product['range'] = $range ? floatval( $range ) : null;
    $product['weight'] = $weight ? floatval( $weight ) : null;
    $product['weight_limit'] = $weight_limit ? floatval( $weight_limit ) : null;
    $product['battery'] = $battery ? floatval( $battery ) : null;
    $product['voltage'] = $voltage ? floatval( $voltage ) : null;
    $product['amphours'] = $amphours ? floatval( $amphours ) : null;
    $product['charging_time'] = $charging_time ? floatval( $charging_time ) : null;
    $product['battery_type'] = $battery_type ?: null;
    $product['motor_power'] = $motor_power ? floatval( $motor_power ) : null;
    $product['motor_peak'] = $motor_peak ? floatval( $motor_peak ) : null;
    $product['motor_position'] = $motor_position ?: null;

    // Track motor positions for filter checkboxes
    if ( $motor_position ) {
        $motor_positions[ $motor_position ] = ( $motor_positions[ $motor_position ] ?? 0 ) + 1;
    }

    // Track battery types for filter checkboxes
    if ( $battery_type ) {
        $battery_types[ $battery_type ] = ( $battery_types[ $battery_type ] ?? 0 ) + 1;
    }

    // Track filter max ranges
    if ( $current_price && $current_price > 0 ) {
        $price_max = max( $price_max, $current_price );
    }
    if ( $top_speed && $top_speed > 0 ) {
        $speed_max = max( $speed_max, floatval( $top_speed ) );
    }
    if ( $range && $range > 0 ) {
        $range_max = max( $range_max, floatval( $range ) );
    }
    if ( $weight && $weight > 0 ) {
        $weight_max = max( $weight_max, floatval( $weight ) );
    }
    if ( $weight_limit && $weight_limit > 0 ) {
        $weight_limit_max = max( $weight_limit_max, floatval( $weight_limit ) );
    }
    if ( $battery && $battery > 0 ) {
        $battery_max = max( $battery_max, floatval( $battery ) );
    }
    if ( $voltage && $voltage > 0 ) {
        $voltage_max = max( $voltage_max, floatval( $voltage ) );
    }
    if ( $amphours && $amphours > 0 ) {
        $amphours_max = max( $amphours_max, floatval( $amphours ) );
    }
    if ( $charging_time && $charging_time > 0 ) {
        $charging_time_max = max( $charging_time_max, floatval( $charging_time ) );
    }
    if ( $motor_power && $motor_power > 0 ) {
        $motor_power_max = max( $motor_power_max, floatval( $motor_power ) );
    }
    if ( $motor_peak && $motor_peak > 0 ) {
        $motor_peak_max = max( $motor_peak_max, floatval( $motor_peak ) );
    }
}
unset( $product ); // Break reference

// Sort brands by count
arsort( $brands );

// Set sensible defaults if no data
if ( $price_max === 0 ) $price_max = 5000;
if ( $speed_max === 0 ) $speed_max = 50;
if ( $range_max === 0 ) $range_max = 100;
if ( $weight_max === 0 ) $weight_max = 150;
if ( $weight_limit_max === 0 ) $weight_limit_max = 400;
if ( $battery_max === 0 ) $battery_max = 2000;
if ( $voltage_max === 0 ) $voltage_max = 72;
if ( $amphours_max === 0 ) $amphours_max = 50;
if ( $charging_time_max === 0 ) $charging_time_max = 12;
if ( $motor_power_max === 0 ) $motor_power_max = 2000;
if ( $motor_peak_max === 0 ) $motor_peak_max = 5000;

// Calculate distributions for all range filters (before rounding max values)
$num_buckets = 10;

/**
 * Helper to calculate distribution histogram
 */
$calc_distribution = function( $products, $field, $max_val ) use ( $num_buckets ) {
    if ( $max_val <= 0 ) {
        return array_fill( 0, $num_buckets, 0 );
    }

    $distribution = array_fill( 0, $num_buckets, 0 );
    $bucket_size = $max_val / $num_buckets;

    foreach ( $products as $product ) {
        $value = $product[ $field ] ?? null;
        if ( $value && $value > 0 ) {
            $bucket_index = min( floor( $value / $bucket_size ), $num_buckets - 1 );
            $distribution[ (int) $bucket_index ]++;
        }
    }

    // Normalize to percentages (0-100)
    $max_count = max( $distribution ) ?: 1;
    return array_map( function( $count ) use ( $max_count ) {
        return round( ( $count / $max_count ) * 100 );
    }, $distribution );
};

// Calculate all distributions
$dist_price         = $calc_distribution( $products, 'current_price', $price_max );
$dist_speed         = $calc_distribution( $products, 'top_speed', $speed_max );
$dist_range         = $calc_distribution( $products, 'range', $range_max );
$dist_weight        = $calc_distribution( $products, 'weight', $weight_max );
$dist_weight_limit  = $calc_distribution( $products, 'weight_limit', $weight_limit_max );
$dist_battery       = $calc_distribution( $products, 'battery', $battery_max );
$dist_voltage       = $calc_distribution( $products, 'voltage', $voltage_max );
$dist_amphours      = $calc_distribution( $products, 'amphours', $amphours_max );
$dist_charging_time = $calc_distribution( $products, 'charging_time', $charging_time_max );
$dist_motor_power   = $calc_distribution( $products, 'motor_power', $motor_power_max );
$dist_motor_peak    = $calc_distribution( $products, 'motor_peak', $motor_peak_max );

// Round max to nice numbers
$price_max = ceil( $price_max / 100 ) * 100;
$speed_max = ceil( $speed_max / 5 ) * 5;
$range_max = ceil( $range_max / 10 ) * 10;
$weight_max = ceil( $weight_max / 10 ) * 10;
$weight_limit_max = ceil( $weight_limit_max / 50 ) * 50;
$battery_max = ceil( $battery_max / 100 ) * 100;
$voltage_max = ceil( $voltage_max / 12 ) * 12;
$amphours_max = ceil( $amphours_max / 5 ) * 5;
$charging_time_max = ceil( $charging_time_max / 1 ) * 1;
$motor_power_max = ceil( $motor_power_max / 100 ) * 100;
$motor_peak_max = ceil( $motor_peak_max / 500 ) * 500;

// All ranges start at 0
$price_min = 0;
$speed_min = 0;
$range_min = 0;
$weight_min = 0;
$weight_limit_min = 0;
$battery_min = 0;
$voltage_min = 0;
$amphours_min = 0;
$charging_time_min = 0;
$motor_power_min = 0;
$motor_peak_min = 0;

// Sort products by popularity (descending)
usort( $products, function( $a, $b ) {
    return ( $b['popularity'] ?? 0 ) - ( $a['popularity'] ?? 0 );
});

$product_count = count( $products );
?>

<main class="finder-page" data-finder-page data-product-type="<?php echo esc_attr( $page_info['product_type'] ); ?>">

    <!-- Page Header -->
    <section class="finder-page-header">
        <div class="container">
            <h1 class="finder-page-title"><?php echo esc_html( $page_info['title'] ); ?></h1>
            <p class="finder-page-subtitle"><?php echo esc_html( $page_info['subtitle'] ); ?></p>
        </div>
    </section>

    <!-- Finder Content -->
    <section class="finder-section">
        <div class="container">
            <div class="finder-layout">

                <!-- Sidebar Filters -->
                <aside class="finder-sidebar" data-finder-sidebar>
                    <!-- Fixed header area -->
                    <div class="finder-sidebar-header">
                        <div class="finder-filters-header">
                            <h2 class="finder-filters-title">Filters</h2>
                        </div>

                        <!-- Filter Search -->
                        <div class="finder-filters-search">
                            <?php erh_the_icon( 'search' ); ?>
                            <input type="text" placeholder="Find filter..." data-filter-search>
                        </div>
                    </div>

                    <!-- Scrollable filters area -->
                    <div class="finder-sidebar-scroll">
                        <!-- Filters Container -->
                        <div class="finder-filters" data-finder-filters>

                        <!-- Price & Availability -->
                        <div class="filter-group" data-filter-group="price">
                            <h3 class="filter-group-title">Price</h3>
                            <div class="filter-group-content">
                                <div class="filter-range" data-range-filter="price" data-min="<?php echo esc_attr( $price_min ); ?>" data-max="<?php echo esc_attr( $price_max ); ?>">
                                    <div class="filter-range-inputs">
                                        <div class="filter-range-input-group">
                                            <span class="filter-range-prefix">$</span>
                                            <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $price_min ); ?>" min="<?php echo esc_attr( $price_min ); ?>" max="<?php echo esc_attr( $price_max ); ?>">
                                        </div>
                                        <span class="filter-range-separator">–</span>
                                        <div class="filter-range-input-group">
                                            <span class="filter-range-prefix">$</span>
                                            <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $price_max ); ?>" min="<?php echo esc_attr( $price_min ); ?>" max="<?php echo esc_attr( $price_max ); ?>">
                                        </div>
                                    </div>
                                    <!-- Price distribution histogram -->
                                    <div class="filter-range-distribution" aria-hidden="true">
                                        <?php foreach ( $dist_price as $height ) : ?>
                                            <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="filter-range-slider" data-range-slider>
                                        <div class="filter-range-track"></div>
                                        <div class="filter-range-fill" style="--min: 0; --max: 1;"></div>
                                        <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                                        <div class="filter-range-handle" data-handle="max" style="--pos: 1;"></div>
                                    </div>
                                </div>
                                <label class="filter-checkbox" style="margin-top: var(--space-4);">
                                    <input type="checkbox" name="in_stock" value="1" data-filter="in_stock">
                                    <span class="filter-checkbox-box">
                                        <?php erh_the_icon( 'check' ); ?>
                                    </span>
                                    <span class="filter-checkbox-label">In stock only</span>
                                </label>
                            </div>
                        </div>

                        <!-- Brands -->
                        <?php if ( ! empty( $brands ) ) :
                            $brand_count = count( $brands );
                            $visible_limit = 8;
                            $has_overflow = $brand_count > $visible_limit;
                            $brand_index = 0;
                        ?>
                        <div class="filter-group" data-filter-group="brands">
                            <h3 class="filter-group-title">Brands</h3>
                            <div class="filter-group-content">
                                <?php if ( $has_overflow ) : ?>
                                <div class="filter-list-search" data-filter-list-search-container="brands">
                                    <?php erh_the_icon( 'search' ); ?>
                                    <input type="text" placeholder="Search brands..." data-filter-list-search="brands">
                                    <button type="button" class="filter-list-search-clear" data-filter-list-search-clear="brands" aria-label="Clear search">
                                        <?php erh_the_icon( 'x' ); ?>
                                    </button>
                                </div>
                                <?php endif; ?>
                                <div class="filter-checkbox-list" data-filter-list="brands" data-limit="<?php echo esc_attr( $visible_limit ); ?>">
                                    <?php foreach ( $brands as $brand_name => $count ) :
                                        $is_hidden = $has_overflow && $brand_index >= $visible_limit;
                                    ?>
                                        <label class="filter-checkbox<?php echo $is_hidden ? ' is-hidden-by-limit' : ''; ?>">
                                            <input type="checkbox" name="brand" value="<?php echo esc_attr( $brand_name ); ?>" data-filter="brand">
                                            <span class="filter-checkbox-box">
                                                <?php erh_the_icon( 'check' ); ?>
                                            </span>
                                            <span class="filter-checkbox-label"><?php echo esc_html( $brand_name ); ?></span>
                                            <span class="filter-checkbox-count"><?php echo esc_html( $count ); ?></span>
                                        </label>
                                    <?php
                                        $brand_index++;
                                    endforeach; ?>
                                </div>
                                <?php if ( $has_overflow ) : ?>
                                <button type="button" class="filter-show-all" data-filter-show-all="brands">
                                    <span data-show-text>Show all <?php echo esc_html( $brand_count ); ?></span>
                                    <span data-hide-text hidden>Show less</span>
                                    <?php erh_the_icon( 'chevron-down' ); ?>
                                </button>
                                <p class="filter-no-results" data-filter-no-results="brands" hidden>No brands found</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Motor -->
                        <?php if ( ! empty( $motor_positions ) || $motor_power_max > 0 || $motor_peak_max > 0 ) : ?>
                        <div class="filter-group" data-filter-group="motor">
                            <h3 class="filter-group-title">Motor</h3>
                            <div class="filter-group-content">

                                <!-- Motor Position (checkboxes) -->
                                <?php if ( ! empty( $motor_positions ) ) : ?>
                                <div class="filter-item is-open" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Position</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <?php foreach ( $motor_positions as $position => $count ) : ?>
                                            <label class="filter-checkbox">
                                                <input type="checkbox" name="motor_position" value="<?php echo esc_attr( $position ); ?>" data-filter="motor_position">
                                                <span class="filter-checkbox-box">
                                                    <?php erh_the_icon( 'check' ); ?>
                                                </span>
                                                <span class="filter-checkbox-label"><?php echo esc_html( ucfirst( $position ) ); ?></span>
                                                <span class="filter-checkbox-count"><?php echo esc_html( $count ); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Nominal Power -->
                                <?php if ( $motor_power_max > 0 ) : ?>
                                <div class="filter-item" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Nominal Power</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <div class="filter-range" data-range-filter="motor_power" data-min="<?php echo esc_attr( $motor_power_min ); ?>" data-max="<?php echo esc_attr( $motor_power_max ); ?>">
                                            <div class="filter-range-inputs">
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $motor_power_min ); ?>">
                                                    <span class="filter-range-suffix">W</span>
                                                </div>
                                                <span class="filter-range-separator">–</span>
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $motor_power_max ); ?>">
                                                    <span class="filter-range-suffix">W</span>
                                                </div>
                                            </div>
                                            <div class="filter-range-distribution" aria-hidden="true">
                                                <?php foreach ( $dist_motor_power as $height ) : ?>
                                                    <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="filter-range-slider" data-range-slider>
                                                <div class="filter-range-track"></div>
                                                <div class="filter-range-fill" style="--min: 0; --max: 1;"></div>
                                                <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                                                <div class="filter-range-handle" data-handle="max" style="--pos: 1;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Peak Power -->
                                <?php if ( $motor_peak_max > 0 ) : ?>
                                <div class="filter-item" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Peak Power</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <div class="filter-range" data-range-filter="motor_peak" data-min="<?php echo esc_attr( $motor_peak_min ); ?>" data-max="<?php echo esc_attr( $motor_peak_max ); ?>">
                                            <div class="filter-range-inputs">
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $motor_peak_min ); ?>">
                                                    <span class="filter-range-suffix">W</span>
                                                </div>
                                                <span class="filter-range-separator">–</span>
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $motor_peak_max ); ?>">
                                                    <span class="filter-range-suffix">W</span>
                                                </div>
                                            </div>
                                            <div class="filter-range-distribution" aria-hidden="true">
                                                <?php foreach ( $dist_motor_peak as $height ) : ?>
                                                    <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="filter-range-slider" data-range-slider>
                                                <div class="filter-range-track"></div>
                                                <div class="filter-range-fill" style="--min: 0; --max: 1;"></div>
                                                <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                                                <div class="filter-range-handle" data-handle="max" style="--pos: 1;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Battery -->
                        <?php if ( ! empty( $battery_types ) || $battery_max > 0 || $voltage_max > 0 || $amphours_max > 0 || $charging_time_max > 0 ) : ?>
                        <div class="filter-group" data-filter-group="battery">
                            <h3 class="filter-group-title">Battery</h3>
                            <div class="filter-group-content">

                                <!-- Battery Type (checkboxes) -->
                                <?php if ( ! empty( $battery_types ) ) : ?>
                                <div class="filter-item is-open" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Type</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <?php foreach ( $battery_types as $type => $count ) : ?>
                                            <label class="filter-checkbox">
                                                <input type="checkbox" name="battery_type" value="<?php echo esc_attr( $type ); ?>" data-filter="battery_type">
                                                <span class="filter-checkbox-box">
                                                    <?php erh_the_icon( 'check' ); ?>
                                                </span>
                                                <span class="filter-checkbox-label"><?php echo esc_html( $type ); ?></span>
                                                <span class="filter-checkbox-count"><?php echo esc_html( $count ); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Capacity -->
                                <?php if ( $battery_max > 0 ) : ?>
                                <div class="filter-item" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Capacity</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <div class="filter-range" data-range-filter="battery" data-min="<?php echo esc_attr( $battery_min ); ?>" data-max="<?php echo esc_attr( $battery_max ); ?>">
                                            <div class="filter-range-inputs">
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $battery_min ); ?>">
                                                    <span class="filter-range-suffix">Wh</span>
                                                </div>
                                                <span class="filter-range-separator">–</span>
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $battery_max ); ?>">
                                                    <span class="filter-range-suffix">Wh</span>
                                                </div>
                                            </div>
                                            <div class="filter-range-distribution" aria-hidden="true">
                                                <?php foreach ( $dist_battery as $height ) : ?>
                                                    <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="filter-range-slider" data-range-slider>
                                                <div class="filter-range-track"></div>
                                                <div class="filter-range-fill" style="--min: 0; --max: 1;"></div>
                                                <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                                                <div class="filter-range-handle" data-handle="max" style="--pos: 1;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Voltage -->
                                <?php if ( $voltage_max > 0 ) : ?>
                                <div class="filter-item" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Voltage</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <div class="filter-range" data-range-filter="voltage" data-min="<?php echo esc_attr( $voltage_min ); ?>" data-max="<?php echo esc_attr( $voltage_max ); ?>">
                                            <div class="filter-range-inputs">
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $voltage_min ); ?>">
                                                    <span class="filter-range-suffix">V</span>
                                                </div>
                                                <span class="filter-range-separator">–</span>
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $voltage_max ); ?>">
                                                    <span class="filter-range-suffix">V</span>
                                                </div>
                                            </div>
                                            <div class="filter-range-distribution" aria-hidden="true">
                                                <?php foreach ( $dist_voltage as $height ) : ?>
                                                    <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="filter-range-slider" data-range-slider>
                                                <div class="filter-range-track"></div>
                                                <div class="filter-range-fill" style="--min: 0; --max: 1;"></div>
                                                <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                                                <div class="filter-range-handle" data-handle="max" style="--pos: 1;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Amp Hours -->
                                <?php if ( $amphours_max > 0 ) : ?>
                                <div class="filter-item" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Amp Hours</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <div class="filter-range" data-range-filter="amphours" data-min="<?php echo esc_attr( $amphours_min ); ?>" data-max="<?php echo esc_attr( $amphours_max ); ?>">
                                            <div class="filter-range-inputs">
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $amphours_min ); ?>">
                                                    <span class="filter-range-suffix">Ah</span>
                                                </div>
                                                <span class="filter-range-separator">–</span>
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $amphours_max ); ?>">
                                                    <span class="filter-range-suffix">Ah</span>
                                                </div>
                                            </div>
                                            <div class="filter-range-distribution" aria-hidden="true">
                                                <?php foreach ( $dist_amphours as $height ) : ?>
                                                    <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="filter-range-slider" data-range-slider>
                                                <div class="filter-range-track"></div>
                                                <div class="filter-range-fill" style="--min: 0; --max: 1;"></div>
                                                <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                                                <div class="filter-range-handle" data-handle="max" style="--pos: 1;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Charging Time -->
                                <?php if ( $charging_time_max > 0 ) : ?>
                                <div class="filter-item" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Charging Time</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <div class="filter-range" data-range-filter="charging_time" data-min="<?php echo esc_attr( $charging_time_min ); ?>" data-max="<?php echo esc_attr( $charging_time_max ); ?>">
                                            <div class="filter-range-inputs">
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $charging_time_min ); ?>">
                                                    <span class="filter-range-suffix">hrs</span>
                                                </div>
                                                <span class="filter-range-separator">–</span>
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $charging_time_max ); ?>">
                                                    <span class="filter-range-suffix">hrs</span>
                                                </div>
                                            </div>
                                            <div class="filter-range-distribution" aria-hidden="true">
                                                <?php foreach ( $dist_charging_time as $height ) : ?>
                                                    <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="filter-range-slider" data-range-slider>
                                                <div class="filter-range-track"></div>
                                                <div class="filter-range-fill" style="--min: 0; --max: 1;"></div>
                                                <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                                                <div class="filter-range-handle" data-handle="max" style="--pos: 1;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Performance -->
                        <div class="filter-group" data-filter-group="performance">
                            <h3 class="filter-group-title">Performance</h3>
                            <div class="filter-group-content">

                                <!-- Top Speed -->
                                <div class="filter-item is-open" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Top Speed</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <div class="filter-range" data-range-filter="speed" data-min="<?php echo esc_attr( $speed_min ); ?>" data-max="<?php echo esc_attr( $speed_max ); ?>">
                                            <div class="filter-range-inputs">
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $speed_min ); ?>">
                                                    <span class="filter-range-suffix">mph</span>
                                                </div>
                                                <span class="filter-range-separator">–</span>
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $speed_max ); ?>">
                                                    <span class="filter-range-suffix">mph</span>
                                                </div>
                                            </div>
                                            <div class="filter-range-distribution" aria-hidden="true">
                                                <?php foreach ( $dist_speed as $height ) : ?>
                                                    <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="filter-range-slider" data-range-slider>
                                                <div class="filter-range-track"></div>
                                                <div class="filter-range-fill" style="--min: 0; --max: 1;"></div>
                                                <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                                                <div class="filter-range-handle" data-handle="max" style="--pos: 1;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Range -->
                                <div class="filter-item" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Range</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <div class="filter-range" data-range-filter="range" data-min="<?php echo esc_attr( $range_min ); ?>" data-max="<?php echo esc_attr( $range_max ); ?>">
                                            <div class="filter-range-inputs">
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $range_min ); ?>">
                                                    <span class="filter-range-suffix">mi</span>
                                                </div>
                                                <span class="filter-range-separator">–</span>
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $range_max ); ?>">
                                                    <span class="filter-range-suffix">mi</span>
                                                </div>
                                            </div>
                                            <div class="filter-range-distribution" aria-hidden="true">
                                                <?php foreach ( $dist_range as $height ) : ?>
                                                    <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="filter-range-slider" data-range-slider>
                                                <div class="filter-range-track"></div>
                                                <div class="filter-range-fill" style="--min: 0; --max: 1;"></div>
                                                <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                                                <div class="filter-range-handle" data-handle="max" style="--pos: 1;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Weight -->
                                <div class="filter-item" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Weight</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <div class="filter-range" data-range-filter="weight" data-min="<?php echo esc_attr( $weight_min ); ?>" data-max="<?php echo esc_attr( $weight_max ); ?>">
                                            <div class="filter-range-inputs">
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $weight_min ); ?>">
                                                    <span class="filter-range-suffix">lbs</span>
                                                </div>
                                                <span class="filter-range-separator">–</span>
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $weight_max ); ?>">
                                                    <span class="filter-range-suffix">lbs</span>
                                                </div>
                                            </div>
                                            <div class="filter-range-distribution" aria-hidden="true">
                                                <?php foreach ( $dist_weight as $height ) : ?>
                                                    <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="filter-range-slider" data-range-slider>
                                                <div class="filter-range-track"></div>
                                                <div class="filter-range-fill" style="--min: 0; --max: 1;"></div>
                                                <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                                                <div class="filter-range-handle" data-handle="max" style="--pos: 1;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Weight Limit -->
                                <div class="filter-item" data-filter-item>
                                    <button type="button" class="filter-item-header" data-filter-item-toggle>
                                        <span class="filter-item-label">Max Load</span>
                                        <?php erh_the_icon( 'chevron-down', 'filter-item-icon' ); ?>
                                    </button>
                                    <div class="filter-item-content">
                                        <div class="filter-range" data-range-filter="weight_limit" data-min="<?php echo esc_attr( $weight_limit_min ); ?>" data-max="<?php echo esc_attr( $weight_limit_max ); ?>">
                                            <div class="filter-range-inputs">
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-min value="<?php echo esc_attr( $weight_limit_min ); ?>">
                                                    <span class="filter-range-suffix">lbs</span>
                                                </div>
                                                <span class="filter-range-separator">–</span>
                                                <div class="filter-range-input-group">
                                                    <input type="number" class="filter-range-input" data-range-max value="<?php echo esc_attr( $weight_limit_max ); ?>">
                                                    <span class="filter-range-suffix">lbs</span>
                                                </div>
                                            </div>
                                            <div class="filter-range-distribution" aria-hidden="true">
                                                <?php foreach ( $dist_weight_limit as $height ) : ?>
                                                    <div class="filter-range-bar" style="--height: <?php echo esc_attr( $height ); ?>"></div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="filter-range-slider" data-range-slider>
                                                <div class="filter-range-track"></div>
                                                <div class="filter-range-fill" style="--min: 0; --max: 1;"></div>
                                                <div class="filter-range-handle" data-handle="min" style="--pos: 0;"></div>
                                                <div class="filter-range-handle" data-handle="max" style="--pos: 1;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        </div><!-- /.finder-filters -->
                    </div><!-- /.finder-sidebar-scroll -->
                </aside>

                <!-- Main Content -->
                <div class="finder-main">

                    <!-- Toolbar -->
                    <div class="finder-toolbar">
                        <div class="finder-toolbar-left">
                            <span class="finder-results-count" data-results-count>
                                <strong><?php echo esc_html( $product_count ); ?></strong> <?php echo esc_html( $page_info['short'] ); ?>s
                            </span>
                        </div>
                        <div class="finder-toolbar-right">
                            <!-- Sort -->
                            <div class="finder-sort">
                                <label class="finder-sort-label" for="finder-sort">Sort by:</label>
                                <select id="finder-sort" class="finder-sort-select" data-finder-sort>
                                    <option value="popularity">Most popular</option>
                                    <option value="price-asc">Price: Low to High</option>
                                    <option value="price-desc">Price: High to Low</option>
                                    <option value="rating">Highest rated</option>
                                    <option value="speed">Top speed</option>
                                    <option value="range">Range</option>
                                    <option value="weight">Weight (lightest)</option>
                                    <option value="name">Name A-Z</option>
                                </select>
                            </div>

                            <!-- View Toggle -->
                            <div class="view-toggle" role="radiogroup" aria-label="View mode">
                                <button class="view-toggle-btn is-active" data-view="grid" aria-label="Grid view" aria-pressed="true">
                                    <?php erh_the_icon( 'grid' ); ?>
                                </button>
                                <button class="view-toggle-btn" data-view="table" aria-label="Table view" aria-pressed="false">
                                    <?php erh_the_icon( 'list' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Active Filters Bar -->
                    <div class="finder-active-bar" data-active-filters hidden></div>

                    <!-- Product Grid (populated by JavaScript) -->
                    <div class="finder-grid" data-finder-grid data-view="grid">
                        <!-- Products rendered via JS for progressive loading -->
                    </div>

                    <!-- Load More Button -->
                    <div class="finder-load-more" data-load-more hidden>
                        <button type="button" class="finder-load-more-btn" data-load-more-btn>
                            Load more
                        </button>
                    </div>

                    <!-- Empty State (shown via JS when filters result in no matches) -->
                    <div class="finder-empty" data-finder-empty hidden>
                        <p>No products match your filters.</p>
                        <button type="button" class="btn btn-secondary" data-clear-filters>Clear all filters</button>
                    </div>

                </div>

            </div>
        </div>
    </section>

    <!-- Comparison Bar (fixed at bottom when products selected) -->
    <div class="comparison-bar" data-comparison-bar hidden>
        <div class="container">
            <div class="comparison-bar-inner">
                <div class="comparison-bar-products" data-comparison-products></div>
                <div class="comparison-bar-actions">
                    <span class="comparison-bar-count"><span data-comparison-count>0</span> selected</span>
                    <button type="button" class="btn btn-secondary btn-sm" data-comparison-clear>Clear</button>
                    <a href="#" class="btn btn-primary btn-sm" data-comparison-link>Compare</a>
                </div>
            </div>
        </div>
    </div>

</main>

<?php
// Prepare products data for JavaScript (simpler structure for rendering)
$js_products = array();
foreach ( $products as $product ) {
    $js_products[] = array(
        'id'              => $product['id'],
        'name'            => $product['name'],
        'url'             => $product['url'],
        'thumbnail'       => $product['thumbnail'] ?: get_template_directory_uri() . '/assets/images/placeholder-product.png',
        'brand'           => $product['brand'],
        'price'           => $product['current_price'],
        'in_stock'        => $product['in_stock'],
        'price_indicator' => $product['price_indicator'],
        'rating'          => $product['rating'] ?? null,
        'popularity'      => $product['popularity'] ?? 0,
        'top_speed'       => $product['top_speed'],
        'range'           => $product['range'],
        'weight'          => $product['weight'],
        'weight_limit'    => $product['weight_limit'],
        'battery'         => $product['battery'],
        'voltage'         => $product['voltage'],
        'amphours'        => $product['amphours'],
        'charging_time'   => $product['charging_time'],
        'battery_type'    => $product['battery_type'],
        'motor_power'     => $product['motor_power'],
        'motor_peak'      => $product['motor_peak'],
        'motor_position'  => $product['motor_position'],
    );
}
?>
<script>
window.ERideHero = window.ERideHero || {};
window.ERideHero.finderProducts = <?php echo wp_json_encode( $js_products ); ?>;
window.ERideHero.finderConfig = {
    productType: <?php echo wp_json_encode( $page_info['product_type'] ); ?>,
    shortName: <?php echo wp_json_encode( $page_info['short'] ); ?>,
    userGeo: <?php echo wp_json_encode( $user_geo ); ?>,
    ranges: {
        price: { min: <?php echo $price_min; ?>, max: <?php echo $price_max; ?> },
        speed: { min: <?php echo $speed_min; ?>, max: <?php echo $speed_max; ?> },
        range: { min: <?php echo $range_min; ?>, max: <?php echo $range_max; ?> },
        weight: { min: <?php echo $weight_min; ?>, max: <?php echo $weight_max; ?> },
        weight_limit: { min: <?php echo $weight_limit_min; ?>, max: <?php echo $weight_limit_max; ?> },
        battery: { min: <?php echo $battery_min; ?>, max: <?php echo $battery_max; ?> },
        voltage: { min: <?php echo $voltage_min; ?>, max: <?php echo $voltage_max; ?> },
        amphours: { min: <?php echo $amphours_min; ?>, max: <?php echo $amphours_max; ?> },
        charging_time: { min: <?php echo $charging_time_min; ?>, max: <?php echo $charging_time_max; ?> },
        motor_power: { min: <?php echo $motor_power_min; ?>, max: <?php echo $motor_power_max; ?> },
        motor_peak: { min: <?php echo $motor_peak_min; ?>, max: <?php echo $motor_peak_max; ?> }
    }
};
</script>

<?php
get_footer();
