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
 * @param float  $price Price value
 * @param string $currency Currency code
 * @return string Formatted price
 */
function erh_format_price( float $price, string $currency = 'USD' ): string {
    $symbols = array(
        'USD' => '$',
        'GBP' => '£',
        'EUR' => '€',
    );

    $symbol = $symbols[ $currency ] ?? '$';

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
