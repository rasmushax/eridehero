<?php
/**
 * Template Functions
 *
 * Helper functions for use in templates.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get SVG icon from sprite
 *
 * @param string $icon Icon name (without 'icon-' prefix)
 * @param string $class Additional CSS classes
 * @param array  $attrs Additional attributes
 * @return string SVG use element
 */
function erh_icon( string $icon, string $class = '', array $attrs = array() ): string {
    $class = trim( 'icon ' . $class );

    $attr_string = ' aria-hidden="true"';
    foreach ( $attrs as $key => $value ) {
        $attr_string .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
    }

    // Use inline sprite reference (sprite is inlined in header.php via svg-sprite.php)
    return sprintf(
        '<svg class="%s"%s><use href="#icon-%s"></use></svg>',
        esc_attr( $class ),
        $attr_string,
        esc_attr( $icon )
    );
}

/**
 * Echo SVG icon
 */
function erh_the_icon( string $icon, string $class = '', array $attrs = array() ): void {
    echo erh_icon( $icon, $class, $attrs );
}

/**
 * Get the site logo or site name
 *
 * @param string $class Additional CSS classes for the logo
 * @return string Logo HTML
 */
function erh_get_logo( string $class = '' ): string {
    $custom_logo_id = get_theme_mod( 'custom_logo' );

    if ( $custom_logo_id ) {
        $logo = wp_get_attachment_image( $custom_logo_id, 'full', false, array(
            'class' => 'site-logo ' . $class,
            'alt'   => get_bloginfo( 'name' ),
        ) );
    } else {
        // Fallback to SVG logo file
        $logo_path = ERH_THEME_DIR . '/assets/images/logo.svg';
        if ( file_exists( $logo_path ) ) {
            $logo = sprintf(
                '<img src="%s" alt="%s" class="site-logo %s">',
                esc_url( ERH_THEME_URI . '/assets/images/logo.svg' ),
                esc_attr( get_bloginfo( 'name' ) ),
                esc_attr( $class )
            );
        } else {
            // Text fallback
            $logo = sprintf(
                '<span class="site-name %s">%s</span>',
                esc_attr( $class ),
                esc_html( get_bloginfo( 'name' ) )
            );
        }
    }

    return $logo;
}

/**
 * Get product type label
 *
 * @param int|null $post_id Post ID (optional, uses current post if null)
 * @return string Product type label
 */
function erh_get_product_type( ?int $post_id = null ): string {
    $post_id = $post_id ?? get_the_ID();
    $type = get_field( 'product_type', $post_id );

    return $type ? $type : '';
}

/**
 * Get product type slug for URLs and classes
 *
 * @param string $type Product type label
 * @return string Slugified type
 */
function erh_product_type_slug( string $type ): string {
    $slugs = array(
        'Electric Scooter'    => 'e-scooters',
        'Electric Bike'       => 'e-bikes',
        'Electric Skateboard' => 'e-skateboards',
        'Electric Unicycle'   => 'eucs',
        'Hoverboard'          => 'hoverboards',
    );

    return $slugs[ $type ] ?? sanitize_title( $type );
}

/**
 * Get product type short name for display in badges/cards
 *
 * @param string $type Full product type label
 * @return string Short display name
 */
function erh_get_product_type_short_name( string $type ): string {
    $short_names = array(
        'Electric Scooter'    => 'E-Scooter',
        'Electric Bike'       => 'E-Bike',
        'Electric Skateboard' => 'E-Skateboard',
        'Electric Unicycle'   => 'EUC',
        'Hoverboard'          => 'Hoverboard',
    );

    return $short_names[ $type ] ?? $type;
}

/**
 * Get category short name for display in badges/cards (plural form)
 *
 * Maps WordPress category names to short display names.
 *
 * @param string $category Category name
 * @return string Short display name
 */
function erh_get_category_short_name( string $category ): string {
    $short_names = array(
        // Plural forms (category names)
        'Electric Scooters'    => 'E-Scooters',
        'Electric Bikes'       => 'E-Bikes',
        'Electric Skateboards' => 'E-Skateboards',
        'Electric Unicycles'   => 'EUCs',
        'Hoverboards'          => 'Hoverboards',
        // Singular forms (in case they're used)
        'Electric Scooter'     => 'E-Scooters',
        'Electric Bike'        => 'E-Bikes',
        'Electric Skateboard'  => 'E-Skateboards',
        'Electric Unicycle'    => 'EUCs',
        'Hoverboard'           => 'Hoverboards',
        // Common variations
        'E-Scooter'            => 'E-Scooters',
        'E-Bike'               => 'E-Bikes',
        'EUC'                  => 'EUCs',
    );

    return $short_names[ $category ] ?? $category;
}

/**
 * Format price for display
 *
 * Shows cents only when they exist (e.g., $999.99), otherwise whole number (e.g., $500).
 * This preserves psychological pricing like .99 endings.
 *
 * @param float  $price Price value
 * @param string $currency Currency code
 * @return string Formatted price
 */
function erh_format_price( float $price, string $currency = 'USD' ): string {
    $symbols = array(
        'USD' => '$',
        'GBP' => '£',
        'EUR' => '€',
        'CAD' => 'CA$',
        'AUD' => 'A$',
    );

    $symbol = $symbols[ $currency ] ?? '$';

    // Check if price has meaningful cents (not .00)
    $has_cents = ( $price - floor( $price ) ) >= 0.005;

    if ( $has_cents ) {
        return $symbol . number_format( $price, 2 );
    }

    return $symbol . number_format( $price, 0 );
}

/**
 * Split price into whole and decimal parts
 *
 * @param float $price Price value
 * @return array ['whole' => string, 'decimal' => string]
 */
function erh_split_price( float $price ): array {
    $whole = floor( $price );
    $decimal = round( ( $price - $whole ) * 100 );

    return array(
        'whole'   => number_format( $whole, 0 ),
        'decimal' => str_pad( (string) $decimal, 2, '0', STR_PAD_LEFT ),
    );
}

/**
 * Get time elapsed string (e.g., "2 hours ago")
 *
 * @param string $datetime DateTime string
 * @return string Human-readable time difference
 */
function erh_time_elapsed( string $datetime ): string {
    $time = strtotime( $datetime );

    if ( ! $time ) {
        return '';
    }

    return human_time_diff( $time, current_time( 'timestamp' ) ) . ' ago';
}

/**
 * Truncate text with "show more" support
 *
 * @param string $text Text to truncate
 * @param int    $length Max length
 * @param bool   $show_more Whether to add show more functionality
 * @return string Truncated text with optional show more
 */
function erh_truncate_text( string $text, int $length = 150, bool $show_more = true ): string {
    if ( strlen( $text ) <= $length ) {
        return $text;
    }

    $truncated = substr( $text, 0, $length );
    $truncated = substr( $truncated, 0, strrpos( $truncated, ' ' ) );

    if ( $show_more ) {
        return sprintf(
            '<span class="text-truncated">%s</span><span class="text-full" hidden>%s</span><button type="button" class="show-more-btn">Show more</button>',
            esc_html( $truncated . '...' ),
            esc_html( $text )
        );
    }

    return $truncated . '...';
}

/**
 * Get breadcrumb items for current page
 *
 * @return array Array of breadcrumb items with 'label' and 'url' keys
 */
function erh_get_breadcrumbs(): array {
    $breadcrumbs = array();

    // Home
    $breadcrumbs[] = array(
        'label' => 'Home',
        'url'   => home_url( '/' ),
    );

    if ( is_singular( 'products' ) ) {
        $type = erh_get_product_type();
        if ( $type ) {
            $slug = erh_product_type_slug( $type );
            $breadcrumbs[] = array(
                'label' => $type . ' Reviews',
                'url'   => home_url( '/' . $slug . '-reviews/' ),
            );
        }
        $breadcrumbs[] = array(
            'label' => get_the_title(),
            'url'   => '',
        );
    } elseif ( is_singular( 'post' ) ) {
        $breadcrumbs[] = array(
            'label' => 'Articles',
            'url'   => home_url( '/articles/' ),
        );
        $breadcrumbs[] = array(
            'label' => get_the_title(),
            'url'   => '',
        );
    } elseif ( is_page() ) {
        $breadcrumbs[] = array(
            'label' => get_the_title(),
            'url'   => '',
        );
    } elseif ( is_archive() ) {
        $breadcrumbs[] = array(
            'label' => get_the_archive_title(),
            'url'   => '',
        );
    }

    return $breadcrumbs;
}

/**
 * Render breadcrumbs
 */
function erh_the_breadcrumbs(): void {
    $items = erh_get_breadcrumbs();

    if ( empty( $items ) ) {
        return;
    }

    echo '<nav class="breadcrumb" aria-label="Breadcrumb">';
    echo '<ol class="breadcrumb-list">';

    $count = count( $items );
    foreach ( $items as $index => $item ) {
        $is_last = ( $index === $count - 1 );

        echo '<li class="breadcrumb-item">';

        if ( ! $is_last && ! empty( $item['url'] ) ) {
            printf(
                '<a href="%s" class="breadcrumb-link">%s</a>',
                esc_url( $item['url'] ),
                esc_html( $item['label'] )
            );
        } else {
            printf(
                '<span class="breadcrumb-current" aria-current="page">%s</span>',
                esc_html( $item['label'] )
            );
        }

        if ( ! $is_last ) {
            echo erh_icon( 'chevron-right', 'breadcrumb-separator' );
        }

        echo '</li>';
    }

    echo '</ol>';
    echo '</nav>';
}

/**
 * Check if ERH Core plugin is active
 *
 * @return bool
 */
function erh_is_core_active(): bool {
    return defined( 'ERH_CORE_VERSION' ) && class_exists( 'ERH_Core' );
}

/**
 * Check if a product has current price data
 *
 * Uses the PriceFetcher from ERH Core to check if any retailer
 * has pricing for the product. Used to conditionally render
 * the price intelligence section.
 *
 * @param int $product_id The product ID.
 * @return bool True if product has price data.
 */
function erh_product_has_prices( int $product_id ): bool {
    // Check if ERH Core is active and PriceFetcher exists
    if ( ! class_exists( '\\ERH\\Pricing\\PriceFetcher' ) ) {
        return false;
    }

    // Use static cache to avoid repeated DB queries on same page
    static $cache = array();

    if ( ! isset( $cache[ $product_id ] ) ) {
        $fetcher = new \ERH\Pricing\PriceFetcher();
        $cache[ $product_id ] = $fetcher->has_price_data( $product_id );
    }

    return $cache[ $product_id ];
}

/**
 * Get template part with data
 *
 * Like get_template_part() but allows passing data to the template.
 *
 * @param string $slug Template slug
 * @param string $name Template name (optional)
 * @param array  $data Data to pass to template
 */
function erh_get_template_part( string $slug, string $name = '', array $data = array() ): void {
    // Make data available in the template
    if ( ! empty( $data ) ) {
        extract( $data, EXTR_SKIP );
    }

    // Build template path
    $templates = array();
    if ( $name ) {
        $templates[] = "{$slug}-{$name}.php";
    }
    $templates[] = "{$slug}.php";

    // Locate and include template
    $located = locate_template( $templates, false, false );

    if ( $located ) {
        include $located;
    }
}

/**
 * Render review breadcrumb
 *
 * Custom breadcrumb for review posts: Category > Reviews > Post Title
 *
 * @param string $category_slug Category slug (e.g., 'e-scooters')
 * @param string $category_name Category display name (e.g., 'E-Scooters')
 */
function erh_review_breadcrumb( string $category_slug, string $category_name ): void {
    $reviews_url = home_url( '/' . $category_slug . '-reviews/' );
    $hub_url     = home_url( '/' . $category_slug . '/' );
    ?>
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?php echo esc_url( $hub_url ); ?>"><?php echo esc_html( $category_name ); ?></a>
        <span class="breadcrumb-sep">/</span>
        <a href="<?php echo esc_url( $reviews_url ); ?>">Reviews</a>
        <span class="breadcrumb-sep breadcrumb-current-sep">/</span>
        <span class="breadcrumb-current"><?php the_title(); ?></span>
    </nav>
    <?php
}

/**
 * Get score label from numeric rating
 *
 * @param float $score The score value (0-10)
 * @return string The label (e.g., 'Excellent', 'Great', 'Good')
 */
function erh_get_score_label( float $score ): string {
    if ( $score >= 9.0 ) {
        return 'Excellent';
    } elseif ( $score >= 8.0 ) {
        return 'Great';
    } elseif ( $score >= 7.0 ) {
        return 'Good';
    } elseif ( $score >= 6.0 ) {
        return 'Average';
    } else {
        return 'Poor';
    }
}

/**
 * Get score data attribute from numeric rating
 *
 * Used for CSS styling variants.
 *
 * @param float $score The score value (0-10)
 * @return string The data attribute value
 */
function erh_get_score_attr( float $score ): string {
    if ( $score >= 9.0 ) {
        return 'excellent';
    } elseif ( $score >= 8.0 ) {
        return 'great';
    } elseif ( $score >= 7.0 ) {
        return 'good';
    } elseif ( $score >= 6.0 ) {
        return 'average';
    } else {
        return 'poor';
    }
}

/**
 * Get specification groups for a product
 *
 * Returns organized spec groups with labels and formatted values.
 * Adapts to different product types (e-scooter, e-bike, etc.)
 *
 * @param int    $product_id   The product ID.
 * @param string $product_type The product type.
 * @return array Array of spec groups with 'label' and 'specs' arrays.
 */
function erh_get_spec_groups( int $product_id, string $product_type ): array {
    $groups = array();

    // Normalize product type
    $type_key = strtolower( str_replace( array( ' ', '-' ), '_', $product_type ) );

    if ( $type_key === 'electric_scooter' ) {
        $groups = erh_get_escooter_spec_groups( $product_id );
    } elseif ( $type_key === 'electric_bike' ) {
        $groups = erh_get_ebike_spec_groups( $product_id );
    } else {
        // Default fallback - try e-scooter structure
        $groups = erh_get_escooter_spec_groups( $product_id );
    }

    // Filter out empty groups
    return array_filter( $groups, function( $group ) {
        return ! empty( $group['specs'] );
    } );
}

/**
 * Get e-scooter specification groups
 *
 * @param int $product_id The product ID.
 * @return array Array of spec groups.
 */
function erh_get_escooter_spec_groups( int $product_id ): array {
    // Get nested e-scooter data
    $escooter = get_field( 'e-scooters', $product_id );

    $groups = array();

    // Claimed Performance (manufacturer specs - NOT tested data)
    $groups['claimed'] = array(
        'label' => 'Claimed performance',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Top speed', 'value' => get_field( 'manufacturer_top_speed', $product_id ), 'unit' => 'mph' ),
            array( 'label' => 'Range', 'value' => get_field( 'manufacturer_range', $product_id ), 'unit' => 'mi' ),
            array( 'label' => 'Max incline', 'value' => get_field( 'max_incline', $product_id ), 'unit' => '°' ),
        ) ),
    );

    // Motor & Power
    $motor = $escooter['motor'] ?? array();
    $groups['motor'] = array(
        'label' => 'Motor & power',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Motor position', 'value' => $motor['motor_position'] ?? '' ),
            array( 'label' => 'Motor type', 'value' => $motor['motor_type'] ?? '' ),
            array( 'label' => 'Voltage', 'value' => $motor['voltage'] ?? '', 'unit' => 'V' ),
            array( 'label' => 'Nominal power', 'value' => $motor['power_nominal'] ?? '', 'unit' => 'W' ),
            array( 'label' => 'Peak power', 'value' => $motor['power_peak'] ?? '', 'unit' => 'W' ),
        ) ),
    );

    // Battery & Charging
    $battery = $escooter['battery'] ?? array();
    $groups['battery'] = array(
        'label' => 'Battery & charging',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Capacity', 'value' => $battery['capacity'] ?? '', 'unit' => 'Wh' ),
            array( 'label' => 'Voltage', 'value' => $battery['voltage'] ?? '', 'unit' => 'V' ),
            array( 'label' => 'Amp hours', 'value' => $battery['amphours'] ?? '', 'unit' => 'Ah' ),
            array( 'label' => 'Battery type', 'value' => $battery['battery_type'] ?? '' ),
            array( 'label' => 'Battery brand', 'value' => $battery['brand'] ?? '' ),
            array( 'label' => 'Charging time', 'value' => $battery['charging_time'] ?? '', 'unit' => 'hrs' ),
        ) ),
    );

    // Brakes
    $brakes = $escooter['brakes'] ?? array();
    $groups['brakes'] = array(
        'label' => 'Brakes',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Front brake', 'value' => $brakes['front'] ?? '' ),
            array( 'label' => 'Rear brake', 'value' => $brakes['rear'] ?? '' ),
            array( 'label' => 'Regenerative braking', 'value' => erh_format_boolean( $brakes['regenerative'] ?? false ) ),
        ) ),
    );

    // Wheels & Tires
    $wheels = $escooter['wheels'] ?? array();
    $tire_size = erh_format_tire_sizes( $wheels['tire_size_front'] ?? '', $wheels['tire_size_rear'] ?? '' );
    $groups['wheels'] = array(
        'label' => 'Wheels & tires',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Tire size', 'value' => $tire_size ),
            array( 'label' => 'Tire width', 'value' => $wheels['tire_width'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Tire type', 'value' => $wheels['tire_type'] ?? '' ),
            array( 'label' => 'Pneumatic type', 'value' => $wheels['pneumatic_type'] ?? '' ),
            array( 'label' => 'Self-healing tires', 'value' => erh_format_boolean( $wheels['self_healing'] ?? false ) ),
        ) ),
    );

    // Suspension
    $suspension = $escooter['suspension'] ?? array();
    $suspension_type = $suspension['type'] ?? array();
    $groups['suspension'] = array(
        'label' => 'Suspension',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Suspension type', 'value' => is_array( $suspension_type ) ? implode( ', ', $suspension_type ) : $suspension_type ),
            array( 'label' => 'Adjustable', 'value' => erh_format_boolean( $suspension['adjustable'] ?? false ) ),
        ) ),
    );

    // Dimensions & Weight
    $dims = $escooter['dimensions'] ?? array();
    $handlebar_height = erh_format_range( $dims['handlebar_height_min'] ?? '', $dims['handlebar_height_max'] ?? '', '"' );
    $groups['dimensions'] = array(
        'label' => 'Dimensions & weight',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Weight', 'value' => $dims['weight'] ?? '', 'unit' => 'lbs' ),
            array( 'label' => 'Max load', 'value' => $dims['max_load'] ?? '', 'unit' => 'lbs' ),
            array( 'label' => 'Deck length', 'value' => $dims['deck_length'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Deck width', 'value' => $dims['deck_width'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Ground clearance', 'value' => $dims['ground_clearance'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Handlebar height', 'value' => $handlebar_height ),
            array( 'label' => 'Handlebar width', 'value' => $dims['handlebar_width'] ?? '', 'unit' => '"' ),
            array( 'label' => 'Unfolded (L×W×H)', 'value' => erh_format_dimensions( $dims['unfolded_length'] ?? '', $dims['unfolded_width'] ?? '', $dims['unfolded_height'] ?? '' ) ),
            array( 'label' => 'Folded (L×W×H)', 'value' => erh_format_dimensions( $dims['folded_length'] ?? '', $dims['folded_width'] ?? '', $dims['folded_height'] ?? '' ) ),
            array( 'label' => 'Foldable handlebars', 'value' => erh_format_boolean( $dims['foldable_handlebars'] ?? false ) ),
        ) ),
    );

    // Lighting
    $lighting = $escooter['lighting'] ?? array();
    $lights = $lighting['lights'] ?? array();
    $groups['lighting'] = array(
        'label' => 'Lighting',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Lights', 'value' => is_array( $lights ) ? implode( ', ', $lights ) : $lights ),
            array( 'label' => 'Turn signals', 'value' => erh_format_boolean( $lighting['turn_signals'] ?? false ) ),
        ) ),
    );

    // Controls & Other
    $other = $escooter['other'] ?? array();
    $groups['other'] = array(
        'label' => 'Controls & other',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Throttle type', 'value' => $other['throttle_type'] ?? '' ),
            array( 'label' => 'Display type', 'value' => $other['display_type'] ?? '' ),
            array( 'label' => 'Fold location', 'value' => $other['fold_location'] ?? '' ),
            array( 'label' => 'Terrain', 'value' => $other['terrain'] ?? '' ),
            array( 'label' => 'IP rating', 'value' => $other['ip_rating'] ?? '' ),
            array( 'label' => 'Kickstand', 'value' => erh_format_boolean( $other['kickstand'] ?? false ) ),
            array( 'label' => 'Footrest', 'value' => erh_format_boolean( $other['footrest'] ?? false ) ),
        ) ),
    );

    // Features
    $features = $escooter['features'] ?? array();
    if ( ! empty( $features ) && is_array( $features ) ) {
        $groups['features'] = array(
            'label' => 'Features',
            'specs' => array(
                array( 'label' => 'Included features', 'value' => implode( ', ', $features ) ),
            ),
        );
    }

    return $groups;
}

/**
 * Get e-bike specification groups
 *
 * @param int $product_id The product ID.
 * @return array Array of spec groups.
 */
function erh_get_ebike_spec_groups( int $product_id ): array {
    // Get nested e-bike data
    $ebike = get_field( 'e-bikes', $product_id );

    $groups = array();

    // Claimed Performance (manufacturer specs)
    $groups['claimed'] = array(
        'label' => 'Claimed performance',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Top speed', 'value' => get_field( 'manufacturer_top_speed', $product_id ), 'unit' => 'mph' ),
            array( 'label' => 'Range', 'value' => get_field( 'manufacturer_range', $product_id ), 'unit' => 'mi' ),
        ) ),
    );

    // Motor
    $motor = $ebike['motor'] ?? array();
    $groups['motor'] = array(
        'label' => 'Motor & power',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Motor type', 'value' => $motor['type'] ?? '' ),
            array( 'label' => 'Motor position', 'value' => $motor['position'] ?? '' ),
            array( 'label' => 'Nominal power', 'value' => $motor['power_nominal'] ?? '', 'unit' => 'W' ),
            array( 'label' => 'Peak power', 'value' => $motor['power_peak'] ?? '', 'unit' => 'W' ),
            array( 'label' => 'Torque', 'value' => $motor['torque'] ?? '', 'unit' => 'Nm' ),
        ) ),
    );

    // Battery
    $battery = $ebike['battery'] ?? array();
    $groups['battery'] = array(
        'label' => 'Battery & charging',
        'specs' => erh_filter_specs( array(
            array( 'label' => 'Capacity', 'value' => $battery['capacity'] ?? '', 'unit' => 'Wh' ),
            array( 'label' => 'Voltage', 'value' => $battery['voltage'] ?? '', 'unit' => 'V' ),
            array( 'label' => 'Range (claimed)', 'value' => $battery['range_claimed'] ?? '', 'unit' => 'mi' ),
            array( 'label' => 'Charging time', 'value' => $battery['charging_time'] ?? '', 'unit' => 'hrs' ),
            array( 'label' => 'Removable', 'value' => erh_format_boolean( $battery['removable'] ?? false ) ),
        ) ),
    );

    // Add more e-bike groups as needed...

    return $groups;
}

/**
 * Filter out specs with empty values
 *
 * @param array $specs Array of specs with label, value, and optional unit.
 * @return array Filtered specs with formatted values.
 */
function erh_filter_specs( array $specs ): array {
    $filtered = array();

    foreach ( $specs as $spec ) {
        $value = $spec['value'] ?? '';

        // Skip empty values
        if ( $value === '' || $value === null || $value === 'Unknown' || $value === 'None' ) {
            continue;
        }

        // Skip "No" for boolean fields (only show if "Yes")
        if ( $value === 'No' ) {
            continue;
        }

        // Format value with unit
        $formatted_value = $value;
        if ( isset( $spec['unit'] ) && is_numeric( $value ) ) {
            $formatted_value = $value . ' ' . $spec['unit'];
        } elseif ( isset( $spec['unit'] ) && $spec['unit'] === '"' && is_numeric( $value ) ) {
            $formatted_value = $value . '"';
        }

        $filtered[] = array(
            'label' => $spec['label'],
            'value' => $formatted_value,
        );
    }

    return $filtered;
}

/**
 * Format boolean value for display
 *
 * @param mixed $value The boolean value.
 * @return string 'Yes' or 'No'.
 */
function erh_format_boolean( $value ): string {
    return $value ? 'Yes' : 'No';
}

/**
 * Format tire sizes (front and rear)
 *
 * @param mixed $front Front tire size.
 * @param mixed $rear  Rear tire size.
 * @return string Formatted tire size string.
 */
function erh_format_tire_sizes( $front, $rear ): string {
    if ( empty( $front ) && empty( $rear ) ) {
        return '';
    }

    if ( $front && $rear && $front === $rear ) {
        return $front . '"';
    }

    if ( $front && $rear ) {
        return $front . '" / ' . $rear . '"';
    }

    return ( $front ?: $rear ) . '"';
}

/**
 * Format dimension range (min to max)
 *
 * @param mixed  $min  Minimum value.
 * @param mixed  $max  Maximum value.
 * @param string $unit Unit suffix.
 * @return string Formatted range string.
 */
function erh_format_range( $min, $max, string $unit = '' ): string {
    if ( empty( $min ) && empty( $max ) ) {
        return '';
    }

    if ( $min && $max && $min !== $max ) {
        return $min . '–' . $max . $unit;
    }

    return ( $min ?: $max ) . $unit;
}

/**
 * Format dimensions (L × W × H)
 *
 * @param mixed $length Length value.
 * @param mixed $width  Width value.
 * @param mixed $height Height value.
 * @return string Formatted dimensions string.
 */
function erh_format_dimensions( $length, $width, $height ): string {
    if ( empty( $length ) && empty( $width ) && empty( $height ) ) {
        return '';
    }

    $parts = array_filter( array( $length, $width, $height ), function( $v ) {
        return ! empty( $v );
    } );

    if ( count( $parts ) < 3 ) {
        return '';
    }

    return $length . ' × ' . $width . ' × ' . $height . '"';
}

/**
 * Extract YouTube video ID from URL
 *
 * Supports multiple YouTube URL formats:
 * - https://www.youtube.com/watch?v=VIDEO_ID
 * - https://youtu.be/VIDEO_ID
 * - https://www.youtube.com/embed/VIDEO_ID
 * - https://www.youtube.com/v/VIDEO_ID
 * - Just the video ID
 *
 * @param string $url YouTube URL or video ID
 * @return string|null Video ID or null if not found
 */
function erh_extract_youtube_id( string $url ): ?string {
    if ( empty( $url ) ) {
        return null;
    }

    // Already just the ID (11 alphanumeric characters)
    if ( preg_match( '/^[a-zA-Z0-9_-]{11}$/', $url ) ) {
        return $url;
    }

    // Standard YouTube URL patterns
    $patterns = array(
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',      // youtube.com/watch?v=
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',        // youtube.com/embed/
        '/youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',            // youtube.com/v/
        '/youtu\.be\/([a-zA-Z0-9_-]{11})/',                  // youtu.be/
        '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',       // youtube.com/shorts/
    );

    foreach ( $patterns as $pattern ) {
        if ( preg_match( $pattern, $url, $matches ) ) {
            return $matches[1];
        }
    }

    return null;
}

/**
 * Check if a product has tested performance data
 *
 * @param int $product_id The product ID.
 * @return bool True if product has any tested performance data.
 */
function erh_product_has_performance_data( int $product_id ): bool {
    // Use static cache
    static $cache = array();

    if ( isset( $cache[ $product_id ] ) ) {
        return $cache[ $product_id ];
    }

    // Check the same fields as tested-performance.php
    $has_data = get_field( 'tested_top_speed', $product_id )
             || get_field( 'acceleration_0_15_mph', $product_id )
             || get_field( 'acceleration_0_20_mph', $product_id )
             || get_field( 'acceleration_0_25_mph', $product_id )
             || get_field( 'acceleration_0_30_mph', $product_id )
             || get_field( 'tested_range_slow', $product_id )
             || get_field( 'tested_range_regular', $product_id )
             || get_field( 'tested_range_fast', $product_id )
             || get_field( 'brake_distance', $product_id )
             || get_field( 'hill_climbing', $product_id );

    $cache[ $product_id ] = (bool) $has_data;

    return $cache[ $product_id ];
}

/**
 * Build table of contents items for a review post
 *
 * Generates a structured array of TOC items including:
 * - Static sections (Quick take, Pros/cons, Prices, Tested performance, Full specs)
 * - Dynamic content headings (H2s with nested H3s from post content)
 *
 * @param int   $product_id      The product ID (for checking performance data, etc.).
 * @param array $options         Options for conditional sections.
 *                               'has_prices'      => bool - Show "Where to buy" section
 *                               'has_performance' => bool - Show "Tested performance" section
 *                               'content_post_id' => int  - Post ID to extract headings from (defaults to current post)
 * @return array Array of TOC items with 'id', 'label', and optional 'children'.
 */
function erh_get_toc_items( int $product_id, array $options = array() ): array {
    $has_prices       = $options['has_prices'] ?? false;
    $has_performance  = $options['has_performance'] ?? false;
    $content_post_id  = $options['content_post_id'] ?? get_the_ID();

    $items = array();

    // Static section: Quick take
    $items[] = array(
        'id'    => 'quick-take',
        'label' => 'Quick take',
    );

    // Static section: Pros & cons
    $items[] = array(
        'id'    => 'pros-cons',
        'label' => 'Pros & cons',
    );

    // Conditional section: Where to buy (only if product has prices)
    if ( $has_prices ) {
        $items[] = array(
            'id'    => 'prices',
            'label' => 'Where to buy',
        );
    }

    // Conditional section: Tested performance
    if ( $has_performance ) {
        $items[] = array(
            'id'    => 'tested-performance',
            'label' => 'Tested performance',
        );
    }

    // Dynamic sections: H2s from content become top-level items (with H3s as children)
    // Use content_post_id (the review post) not product_id
    $content_headings = erh_extract_content_headings( $content_post_id );
    foreach ( $content_headings as $heading ) {
        $items[] = $heading;
    }

    // Static section: Full specifications
    $items[] = array(
        'id'    => 'full-specs',
        'label' => 'Full specifications',
    );

    return $items;
}

/**
 * Extract headings from post content
 *
 * Parses H2 and H3 headings from post content.
 * H3s are nested as children of the preceding H2.
 * Handles duplicate IDs by appending -2, -3, etc.
 *
 * @param int $post_id The post ID.
 * @return array Array of heading items with 'id', 'label', and optional 'children'.
 */
function erh_extract_content_headings( int $post_id ): array {
    $post = get_post( $post_id );
    if ( ! $post || empty( $post->post_content ) ) {
        return array();
    }

    $content  = $post->post_content;
    $items    = array();
    $used_ids = array(); // Track used IDs to handle duplicates

    // Match all H2 and H3 headings
    // Pattern matches: <h2...>text</h2> or <h3...>text</h3>
    // Captures: 1=heading level (2|3), 2=attributes, 3=heading text
    $pattern = '/<h([23])([^>]*)>(.+?)<\/h\1>/is';

    if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
        return array();
    }

    $current_h2 = null;

    foreach ( $matches as $match ) {
        $level = (int) $match[1];
        $attrs = $match[2];
        $text  = wp_strip_all_tags( $match[3] );

        // Extract existing ID from attributes or generate from text
        $base_id = '';
        if ( preg_match( '/id=["\']([^"\']+)["\']/', $attrs, $id_match ) ) {
            $base_id = $id_match[1];
        } else {
            $base_id = sanitize_title( $text );
        }

        if ( empty( $base_id ) || empty( $text ) ) {
            continue;
        }

        // Handle duplicate IDs
        $id = $base_id;
        if ( isset( $used_ids[ $base_id ] ) ) {
            $used_ids[ $base_id ]++;
            $id = $base_id . '-' . $used_ids[ $base_id ];
        } else {
            $used_ids[ $base_id ] = 1;
        }

        if ( $level === 2 ) {
            // Save previous H2 if it exists
            if ( $current_h2 !== null ) {
                $items[] = $current_h2;
            }

            // Start new H2
            $current_h2 = array(
                'id'       => $id,
                'label'    => $text,
                'children' => array(),
            );
        } elseif ( $level === 3 && $current_h2 !== null ) {
            // Add H3 as child of current H2
            $current_h2['children'][] = array(
                'id'    => $id,
                'label' => $text,
            );
        }
    }

    // Add last H2
    if ( $current_h2 !== null ) {
        $items[] = $current_h2;
    }

    // Clean up empty children arrays
    foreach ( $items as &$item ) {
        if ( empty( $item['children'] ) ) {
            unset( $item['children'] );
        }
    }

    return $items;
}

/**
 * Add IDs to headings in content that don't have them
 *
 * Filter for 'the_content' to ensure all H2/H3 headings have IDs for TOC linking.
 * Runs on all singular posts/pages/products for consistent TOC support.
 * Handles duplicates by appending -2, -3, etc.
 *
 * @param string $content The post content.
 * @return string Content with IDs added to headings.
 */
function erh_add_heading_ids( string $content ): string {
    // Only process on singular pages (posts, pages, products, etc.)
    if ( ! is_singular() ) {
        return $content;
    }

    // Track used IDs to handle duplicates
    $used_ids = array();

    // First pass: collect existing IDs
    if ( preg_match_all( '/<h[23][^>]*\bid=["\']([^"\']+)["\'][^>]*>/is', $content, $existing_ids ) ) {
        foreach ( $existing_ids[1] as $existing_id ) {
            $used_ids[ $existing_id ] = 1;
        }
    }

    // Pattern matches headings without an id attribute
    $pattern = '/<h([23])(?![^>]*\bid=)([^>]*)>(.+?)<\/h\1>/is';

    $content = preg_replace_callback( $pattern, function( $match ) use ( &$used_ids ) {
        $level   = $match[1];
        $attrs   = $match[2];
        $text    = $match[3];
        $base_id = sanitize_title( wp_strip_all_tags( $text ) );

        // Skip if we couldn't generate an ID
        if ( empty( $base_id ) ) {
            return $match[0];
        }

        // Handle duplicate IDs
        $id = $base_id;
        if ( isset( $used_ids[ $base_id ] ) ) {
            $used_ids[ $base_id ]++;
            $id = $base_id . '-' . $used_ids[ $base_id ];
        } else {
            $used_ids[ $base_id ] = 1;
        }

        return sprintf( '<h%s id="%s"%s>%s</h%s>', $level, esc_attr( $id ), $attrs, $text, $level );
    }, $content );

    return $content;
}
add_filter( 'the_content', 'erh_add_heading_ids', 5 ); // Early priority so IDs exist for other filters

/**
 * Estimate reading time for a post
 *
 * @param int $post_id Post ID.
 * @return int Minutes to read.
 */
function erh_get_reading_time( int $post_id ): int {
	$content    = get_post_field( 'post_content', $post_id );
	$word_count = str_word_count( wp_strip_all_tags( $content ) );
	$reading_time = ceil( $word_count / 200 ); // 200 words per minute

	return max( 1, (int) $reading_time );
}

/**
 * Get category key from product type
 *
 * Returns the lowercase key used in JS configs (compare-config.js).
 *
 * @param string $product_type Product type label (e.g., 'Electric Scooter').
 * @return string Category key (e.g., 'escooter').
 */
function erh_get_category_key( string $product_type ): string {
	$keys = array(
		'Electric Scooter'    => 'escooter',
		'Electric Bike'       => 'ebike',
		'Electric Skateboard' => 'eskateboard',
		'Electric Unicycle'   => 'euc',
		'Hoverboard'          => 'hoverboard',
	);

	return $keys[ $product_type ] ?? 'escooter';
}

/**
 * Extract brand name from product title
 *
 * Assumes brand is the first word of the product name.
 * Handles compound brand names like "Segway Ninebot".
 *
 * @param string $title Product title.
 * @return string Brand name.
 */
function erh_extract_brand_from_title( string $title ): string {
	// Known compound brand names to check first
	$compound_brands = array(
		'Segway Ninebot',
		'Zero Motorcycles',
		'Xiaomi Mi',
		'Hiboy S',
		'Gotrax G',
		'Inokim Light',
		'Inokim Quick',
		'Inokim Ox',
		'Apollo City',
		'Apollo Ghost',
		'Apollo Phantom',
		'Niu KQi',
		'Mantis King',
		'Wolf King',
		'Wolf Warrior',
	);

	// Check for compound brands first
	foreach ( $compound_brands as $brand ) {
		if ( stripos( $title, $brand ) === 0 ) {
			return $brand;
		}
	}

	// Fall back to first word
	$parts = explode( ' ', trim( $title ) );
	return $parts[0] ?? '';
}

/**
 * Get overall score for a product
 *
 * Returns editor rating (scaled to 0-100) or null if not available.
 * This is used as the main score badge on product pages.
 *
 * @param int $product_id Product ID.
 * @return int|null Score 0-100 or null.
 */
function erh_get_product_score( int $product_id ): ?int {
	$rating = get_field( 'editor_rating', $product_id );

	if ( empty( $rating ) ) {
		return null;
	}

	// Editor rating is 0-10, scale to 0-100
	return (int) round( (float) $rating * 10 );
}

/**
 * Get score label for 0-100 scale score
 *
 * Maps numeric score to human-readable label.
 *
 * @param int $score Score 0-100.
 * @return string Label like 'Excellent', 'Great', etc.
 */
function erh_get_score_label_100( int $score ): string {
	if ( $score >= 90 ) {
		return 'Excellent';
	} elseif ( $score >= 80 ) {
		return 'Great';
	} elseif ( $score >= 70 ) {
		return 'Good';
	} elseif ( $score >= 60 ) {
		return 'Average';
	} else {
		return 'Below Average';
	}
}

/**
 * Get product data from the wp_product_data cache table.
 *
 * @param int $product_id Product ID.
 * @return array|null Product data or null if not found.
 */
function erh_get_product_cache_data( int $product_id ): ?array {
	global $wpdb;

	$table = $wpdb->prefix . 'product_data';
	$row   = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE product_id = %d",
			$product_id
		),
		ARRAY_A
	);

	if ( ! $row ) {
		return null;
	}

	// Unserialize specs and price_history
	if ( isset( $row['specs'] ) ) {
		$row['specs'] = maybe_unserialize( $row['specs'] );
	}
	if ( isset( $row['price_history'] ) ) {
		$row['price_history'] = maybe_unserialize( $row['price_history'] );
	}

	return $row;
}

/**
 * Get spec groups configuration for a product category.
 *
 * Returns structured spec groups matching the SPEC_GROUPS config in compare-config.js.
 * Used for rendering specs from the product_data cache table.
 *
 * @param string $category Category key ('escooter', 'ebike', etc.).
 * @return array Spec groups configuration.
 */
function erh_get_spec_groups_config( string $category ): array {
	$configs = array(
		'escooter' => array(
			'Motor Performance' => array(
				'icon'      => 'zap',
				'score_key' => 'motor_performance',
				'specs'     => array(
					array( 'key' => 'tested_top_speed', 'label' => 'Tested Top Speed', 'unit' => 'mph' ),
					array( 'key' => 'manufacturer_top_speed', 'label' => 'Claimed Top Speed', 'unit' => 'mph' ),
					array( 'key' => 'motor.power_nominal', 'label' => 'Motor Power', 'unit' => 'W' ),
					array( 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W' ),
					array( 'key' => 'motor.voltage', 'label' => 'Voltage', 'unit' => 'V' ),
					array( 'key' => 'motor.motor_position', 'label' => 'Motor Config' ),
					array( 'key' => 'motor.motor_type', 'label' => 'Motor Type' ),
					array( 'key' => 'hill_climbing', 'label' => 'Tested Hill Climb', 'unit' => '°' ),
					array( 'key' => 'max_incline', 'label' => 'Claimed Hill Grade', 'unit' => '°' ),
					array( 'key' => 'fastest_0_15', 'label' => '0-15 mph (tested)', 'unit' => 's' ),
					array( 'key' => 'acceleration_0_15_mph', 'label' => '0-15 mph (claimed)', 'unit' => 's' ),
					array( 'key' => 'fastest_0_20', 'label' => '0-20 mph (tested)', 'unit' => 's' ),
					array( 'key' => 'acceleration_0_20_mph', 'label' => '0-20 mph (claimed)', 'unit' => 's' ),
				),
			),
			'Range & Battery' => array(
				'icon'      => 'battery',
				'score_key' => 'range_battery',
				'specs'     => array(
					array( 'key' => 'tested_range_regular', 'label' => 'Tested Range', 'unit' => 'mi' ),
					array( 'key' => 'tested_range_fast', 'label' => 'Tested Range (fast)', 'unit' => 'mi' ),
					array( 'key' => 'tested_range_slow', 'label' => 'Tested Range (eco)', 'unit' => 'mi' ),
					array( 'key' => 'manufacturer_range', 'label' => 'Claimed Range', 'unit' => 'mi' ),
					array( 'key' => 'battery.capacity', 'label' => 'Battery', 'unit' => 'Wh' ),
					array( 'key' => 'battery.voltage', 'label' => 'Battery Voltage', 'unit' => 'V' ),
					array( 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'h' ),
					array( 'key' => 'battery.battery_type', 'label' => 'Battery Type' ),
				),
			),
			'Ride Quality' => array(
				'icon'      => 'smile',
				'score_key' => 'ride_quality',
				'specs'     => array(
					array( 'key' => 'suspension.type', 'label' => 'Suspension', 'format' => 'array' ),
					array( 'key' => 'suspension.adjustable', 'label' => 'Adjustable Suspension', 'format' => 'boolean' ),
					array( 'key' => 'wheels.tire_type', 'label' => 'Tire Type' ),
					array( 'key' => 'wheels.tire_size_front', 'label' => 'Front Tire Size', 'unit' => '"' ),
					array( 'key' => 'wheels.tire_size_rear', 'label' => 'Rear Tire Size', 'unit' => '"' ),
					array( 'key' => 'wheels.tire_width', 'label' => 'Tire Width', 'unit' => '"' ),
					array( 'key' => 'dimensions.deck_length', 'label' => 'Deck Length', 'unit' => '"' ),
					array( 'key' => 'dimensions.deck_width', 'label' => 'Deck Width', 'unit' => '"' ),
					array( 'key' => 'dimensions.ground_clearance', 'label' => 'Ground Clearance', 'unit' => '"' ),
					array( 'key' => 'dimensions.handlebar_height_min', 'label' => 'Handlebar Height (min)', 'unit' => '"' ),
					array( 'key' => 'dimensions.handlebar_height_max', 'label' => 'Handlebar Height (max)', 'unit' => '"' ),
					array( 'key' => 'dimensions.handlebar_width', 'label' => 'Handlebar Width', 'unit' => '"' ),
					array( 'key' => 'other.footrest', 'label' => 'Footrest', 'format' => 'boolean' ),
					array( 'key' => 'other.terrain', 'label' => 'Terrain Type' ),
				),
			),
			'Portability & Fit' => array(
				'icon'      => 'box',
				'score_key' => 'portability',
				'specs'     => array(
					array( 'key' => 'dimensions.weight', 'label' => 'Weight', 'unit' => 'lbs' ),
					array( 'key' => 'dimensions.max_load', 'label' => 'Max Rider Weight', 'unit' => 'lbs' ),
					array( 'key' => 'dimensions.folded_length', 'label' => 'Folded Length', 'unit' => '"' ),
					array( 'key' => 'dimensions.folded_width', 'label' => 'Folded Width', 'unit' => '"' ),
					array( 'key' => 'dimensions.folded_height', 'label' => 'Folded Height', 'unit' => '"' ),
					array( 'key' => 'dimensions.foldable_handlebars', 'label' => 'Foldable Bars', 'format' => 'boolean' ),
					array( 'key' => 'other.fold_location', 'label' => 'Fold Mechanism' ),
				),
			),
			'Safety' => array(
				'icon'      => 'shield',
				'score_key' => 'safety',
				'specs'     => array(
					array( 'key' => 'brakes.front', 'label' => 'Front Brake' ),
					array( 'key' => 'brakes.rear', 'label' => 'Rear Brake' ),
					array( 'key' => 'brakes.regenerative', 'label' => 'Regen Braking', 'format' => 'boolean' ),
					array( 'key' => 'brake_distance', 'label' => 'Tested Brake Distance', 'unit' => 'ft' ),
					array( 'key' => 'lighting.lights', 'label' => 'Lights', 'format' => 'array' ),
					array( 'key' => 'lighting.turn_signals', 'label' => 'Turn Signals', 'format' => 'boolean' ),
				),
			),
			'Features' => array(
				'icon'      => 'settings',
				'score_key' => 'features',
				'specs'     => array(
					array( 'key' => 'features', 'label' => 'Features', 'format' => 'features' ),
					array( 'key' => 'other.display_type', 'label' => 'Display' ),
					array( 'key' => 'other.throttle_type', 'label' => 'Throttle' ),
					array( 'key' => 'other.kickstand', 'label' => 'Kickstand', 'format' => 'boolean' ),
				),
			),
			'Maintenance' => array(
				'icon'      => 'tool',
				'score_key' => 'maintenance',
				'specs'     => array(
					array( 'key' => 'wheels.tire_type', 'label' => 'Tire Type' ),
					array( 'key' => 'wheels.self_healing', 'label' => 'Self-Healing Tires', 'format' => 'boolean' ),
					array( 'key' => 'other.ip_rating', 'label' => 'IP Rating' ),
				),
			),
		),
		'ebike' => array(
			'Motor & Power' => array(
				'icon'      => 'zap',
				'score_key' => 'motor_performance',
				'specs'     => array(
					array( 'key' => 'e-bikes.motor.power_nominal', 'label' => 'Motor Power', 'unit' => 'W' ),
					array( 'key' => 'e-bikes.motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W' ),
					array( 'key' => 'e-bikes.motor.torque', 'label' => 'Torque', 'unit' => 'Nm' ),
					array( 'key' => 'e-bikes.motor.type', 'label' => 'Motor Type' ),
					array( 'key' => 'e-bikes.motor.position', 'label' => 'Motor Position' ),
				),
			),
			'Range & Battery' => array(
				'icon'      => 'battery',
				'score_key' => 'range_battery',
				'specs'     => array(
					array( 'key' => 'e-bikes.battery.range_claimed', 'label' => 'Claimed Range', 'unit' => 'mi' ),
					array( 'key' => 'e-bikes.battery.capacity', 'label' => 'Battery', 'unit' => 'Wh' ),
					array( 'key' => 'e-bikes.battery.voltage', 'label' => 'Voltage', 'unit' => 'V' ),
					array( 'key' => 'e-bikes.battery.removable', 'label' => 'Removable Battery', 'format' => 'boolean' ),
					array( 'key' => 'e-bikes.battery.charge_time', 'label' => 'Charge Time', 'unit' => 'h' ),
				),
			),
			'Speed & Performance' => array(
				'icon'      => 'gauge',
				'score_key' => null,
				'specs'     => array(
					array( 'key' => 'e-bikes.performance.top_speed', 'label' => 'Top Speed', 'unit' => 'mph' ),
					array( 'key' => 'e-bikes.performance.class', 'label' => 'Class' ),
					array( 'key' => 'e-bikes.performance.pedal_assist_levels', 'label' => 'Assist Levels' ),
					array( 'key' => 'e-bikes.performance.throttle', 'label' => 'Throttle', 'format' => 'boolean' ),
				),
			),
			'Build & Frame' => array(
				'icon'      => 'box',
				'score_key' => null,
				'specs'     => array(
					array( 'key' => 'e-bikes.frame.weight', 'label' => 'Weight', 'unit' => 'lbs' ),
					array( 'key' => 'e-bikes.frame.max_load', 'label' => 'Max Load', 'unit' => 'lbs' ),
					array( 'key' => 'e-bikes.frame.material', 'label' => 'Frame Material' ),
					array( 'key' => 'e-bikes.frame.type', 'label' => 'Frame Type' ),
					array( 'key' => 'e-bikes.frame.suspension', 'label' => 'Suspension' ),
					array( 'key' => 'e-bikes.frame.foldable', 'label' => 'Foldable', 'format' => 'boolean' ),
				),
			),
			'Components' => array(
				'icon'      => 'settings',
				'score_key' => null,
				'specs'     => array(
					array( 'key' => 'e-bikes.components.gears', 'label' => 'Gears' ),
					array( 'key' => 'e-bikes.components.brakes', 'label' => 'Brakes' ),
					array( 'key' => 'e-bikes.components.wheel_size', 'label' => 'Wheel Size', 'unit' => '"' ),
					array( 'key' => 'e-bikes.components.tire_type', 'label' => 'Tire Type' ),
					array( 'key' => 'e-bikes.components.display', 'label' => 'Display' ),
					array( 'key' => 'e-bikes.components.lights', 'label' => 'Lights' ),
				),
			),
		),
	);

	return $configs[ $category ] ?? array();
}

/**
 * Get a nested value from an array using dot notation.
 *
 * @param array  $array The array to traverse.
 * @param string $path  Dot-separated path (e.g., 'motor.power_nominal').
 * @return mixed|null Value at path or null if not found.
 */
function erh_get_nested_value( array $array, string $path ) {
	if ( empty( $path ) ) {
		return null;
	}

	// Direct key access if no dot
	if ( strpos( $path, '.' ) === false ) {
		return $array[ $path ] ?? null;
	}

	// Navigate nested path
	$parts   = explode( '.', $path );
	$current = $array;

	foreach ( $parts as $part ) {
		if ( ! is_array( $current ) || ! isset( $current[ $part ] ) ) {
			return null;
		}
		$current = $current[ $part ];
	}

	return $current;
}

/**
 * Format a spec value for display.
 *
 * @param mixed  $value  The raw value.
 * @param array  $spec   Spec configuration with optional 'unit' and 'format'.
 * @return string Formatted display value.
 */
function erh_format_spec_value( $value, array $spec ): string {
	if ( $value === null || $value === '' ) {
		return '';
	}

	// Handle arrays (suspension, features, lights, etc.)
	if ( is_array( $value ) ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Features array - show count + first few
		if ( ( $spec['format'] ?? '' ) === 'features' ) {
			$filtered = array_filter( $value, function( $v ) {
				return ! empty( $v ) && $v !== 'None';
			} );
			if ( count( $filtered ) <= 3 ) {
				return implode( ', ', $filtered );
			}
			return implode( ', ', array_slice( $filtered, 0, 3 ) ) . ' +' . ( count( $filtered ) - 3 ) . ' more';
		}

		// Generic array format
		$filtered = array_filter( $value, function( $v ) {
			return ! empty( $v ) && $v !== 'None';
		} );
		if ( empty( $filtered ) ) {
			return '';
		}
		return implode( ', ', $filtered );
	}

	// Handle booleans
	if ( ( $spec['format'] ?? '' ) === 'boolean' ) {
		if ( $value === true || $value === 'Yes' || $value === 'yes' || $value === 1 || $value === '1' ) {
			return 'Yes';
		}
		if ( $value === false || $value === 'No' || $value === 'no' || $value === 0 || $value === '0' ) {
			return 'No';
		}
		return (string) $value;
	}

	// Numeric with unit
	if ( isset( $spec['unit'] ) && is_numeric( $value ) ) {
		$formatted = is_float( $value + 0 ) && floor( $value ) != $value
			? number_format( (float) $value, 1 )
			: (string) $value;
		return $formatted . ' ' . $spec['unit'];
	}

	return (string) $value;
}

/**
 * Render specs HTML from product_data cache.
 *
 * @param int    $product_id  Product ID.
 * @param string $category    Category key ('escooter', 'ebike', etc.).
 * @return string HTML for specs categories.
 */
function erh_render_specs_from_cache( int $product_id, string $category ): string {
	// Get cached product data
	$product_data = erh_get_product_cache_data( $product_id );
	if ( ! $product_data || empty( $product_data['specs'] ) ) {
		return '<p class="specs-empty">Specifications not available.</p>';
	}

	$specs       = $product_data['specs'];
	$scores      = $specs['scores'] ?? array();
	$spec_groups = erh_get_spec_groups_config( $category );

	if ( empty( $spec_groups ) ) {
		return '<p class="specs-empty">Specifications not available for this product type.</p>';
	}

	$html = '';

	foreach ( $spec_groups as $category_name => $category_config ) {
		$icon      = $category_config['icon'] ?? 'list';
		$score_key = $category_config['score_key'] ?? null;
		$score     = $score_key && isset( $scores[ $score_key ] ) ? round( $scores[ $score_key ] ) : null;
		$spec_defs = $category_config['specs'] ?? array();

		// Build spec rows
		$rows_html = '';
		foreach ( $spec_defs as $spec_def ) {
			$value     = erh_get_nested_value( $specs, $spec_def['key'] );
			$formatted = erh_format_spec_value( $value, $spec_def );

			// Skip empty values
			if ( $formatted === '' || $formatted === 'No' ) {
				continue;
			}

			$rows_html .= sprintf(
				'<div class="specs-row"><span class="specs-label">%s</span><span class="specs-value">%s</span></div>',
				esc_html( $spec_def['label'] ),
				esc_html( $formatted )
			);
		}

		// Skip categories with no specs
		if ( empty( $rows_html ) ) {
			continue;
		}

		// Build score badge
		$score_html = '';
		if ( $score !== null ) {
			$score_class = $score >= 80 ? 'score-high' : ( $score >= 60 ? 'score-medium' : 'score-low' );
			$score_html  = sprintf(
				'<span class="specs-category-score %s"><span data-score-value>%d</span></span>',
				esc_attr( $score_class ),
				$score
			);
		}

		// Build category HTML
		$html .= sprintf(
			'<div class="specs-category is-open" data-specs-category>
				<button type="button" class="specs-category-header" data-specs-toggle aria-expanded="true">
					<svg class="icon specs-category-icon" aria-hidden="true"><use href="#icon-%s"></use></svg>
					<span class="specs-category-name">%s</span>
					%s
					<svg class="icon specs-category-chevron" aria-hidden="true"><use href="#icon-chevron-down"></use></svg>
				</button>
				<div class="specs-category-body">%s</div>
			</div>',
			esc_attr( $icon ),
			esc_html( $category_name ),
			$score_html,
			$rows_html
		);
	}

	return $html ?: '<p class="specs-empty">No specifications available.</p>';
}
