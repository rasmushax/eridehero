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
 * @return string Product type label (e.g., "Electric Scooter")
 */
function erh_get_product_type( ?int $post_id = null ): string {
    $post_id = $post_id ?? get_the_ID();

    // Product type is stored as a taxonomy, not an ACF field
    $terms = get_the_terms( $post_id, 'product_type' );

    if ( $terms && ! is_wp_error( $terms ) ) {
        // Return the first term's name
        return $terms[0]->name;
    }

    return '';
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
 * Get finder page URL for a product type
 *
 * Returns the ACF finder_page URL if set on the taxonomy term,
 * otherwise returns null.
 *
 * @param int|null $post_id Post ID (optional, uses current post if null)
 * @return string|null Finder page URL or null if not set
 */
function erh_get_finder_url( ?int $post_id = null ): ?string {
    $post_id = $post_id ?? get_the_ID();

    $terms = get_the_terms( $post_id, 'product_type' );

    if ( ! $terms || is_wp_error( $terms ) ) {
        return null;
    }

    $term = $terms[0];

    // Get the finder_page ACF field from the taxonomy term
    $finder_url = get_field( 'finder_page', 'product_type_' . $term->term_id );

    return $finder_url ?: null;
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
 * Get key specs for hero section display
 *
 * Returns an array of formatted spec strings for the product hero summary line.
 * Specs are pulled based on product type and formatted for display.
 *
 * @param int    $product_id   Product post ID
 * @param string $product_type Product type label
 * @return array Array of formatted spec strings
 */
function erh_get_hero_key_specs( int $product_id, string $product_type ): array {
    $result = array();

    // Get category key and nested wrapper
    $category_key   = erh_get_category_key( $product_type );
    $nested_wrapper = erh_get_specs_wrapper_key( $category_key );

    // Get product data from cache (more reliable than direct ACF calls)
    $product_data = erh_get_product_cache_data( $product_id );
    $specs        = array();

    if ( $product_data && ! empty( $product_data['specs'] ) ) {
        $specs = is_array( $product_data['specs'] )
            ? $product_data['specs']
            : maybe_unserialize( $product_data['specs'] );
    }

    // If no cache data, fall back to ACF for nested groups
    if ( empty( $specs ) || ! is_array( $specs ) ) {
        $specs = array();
        // Try to get the nested group directly
        $nested_data = get_field( $nested_wrapper, $product_id );
        if ( is_array( $nested_data ) ) {
            $specs[ $nested_wrapper ] = $nested_data;
        }
        // Also add root-level performance fields
        $specs['manufacturer_top_speed'] = get_field( 'manufacturer_top_speed', $product_id );
        $specs['manufacturer_range']     = get_field( 'manufacturer_range', $product_id );
    }

    // Helper to get spec
    $get = function( $key ) use ( $specs, $nested_wrapper ) {
        return erh_get_spec_from_cache( $specs, $key, $nested_wrapper );
    };

    // Define key specs per product type
    switch ( $category_key ) {
        case 'escooter':
            // 1. Product type
            $result[] = 'Electric Scooter';

            // 2. Top speed
            $speed = $get( 'manufacturer_top_speed' );
            if ( $speed ) {
                $result[] = $speed . ' mph';
            }

            // 3. Range (in parenthesis style - will be formatted in output)
            $range = $get( 'manufacturer_range' );
            if ( $range ) {
                $result[] = '(' . $range . ' mi range)';
            }

            // 4. Motor - e.g., "500W rear motor (1000W peak)" or "dual 1000W motors (2000W peak)"
            $motor_nominal  = $get( 'motor.power_nominal' );
            $motor_peak     = $get( 'motor.power_peak' );
            $motor_position = $get( 'motor.motor_position' );
            if ( $motor_nominal ) {
                if ( strtolower( $motor_position ) === 'dual' ) {
                    $motor_str = 'dual ' . $motor_nominal . 'W motors';
                } else {
                    $position  = $motor_position ? strtolower( $motor_position ) . ' ' : '';
                    $motor_str = $motor_nominal . 'W ' . $position . 'motor';
                }
                if ( $motor_peak && $motor_peak > $motor_nominal ) {
                    $motor_str .= ' (' . $motor_peak . 'W peak)';
                }
                $result[] = $motor_str;
            }

            // 5. Battery - e.g., "551Wh 48V battery"
            $battery_wh = $get( 'battery.capacity' );
            $battery_v  = $get( 'battery.voltage' );
            if ( $battery_wh ) {
                $battery_str = $battery_wh . 'Wh';
                if ( $battery_v ) {
                    $battery_str .= ' ' . $battery_v . 'V';
                }
                $result[] = $battery_str . ' battery';
            }

            // 6. Weight
            $weight = $get( 'dimensions.weight' );
            if ( $weight ) {
                $result[] = $weight . ' lbs';
            }

            // 7. Max load
            $max_load = $get( 'dimensions.max_load' );
            if ( $max_load ) {
                $result[] = $max_load . ' lbs max load';
            }

            // 8. Tires - e.g., '10" pneumatic tires', '8.5"/8" solid tires'
            $tire_front = $get( 'wheels.tire_size_front' );
            $tire_rear  = $get( 'wheels.tire_size_rear' );
            $tire_type  = $get( 'wheels.tire_type' );
            $tubeless   = $get( 'wheels.tubeless' );
            if ( $tire_front ) {
                // Build size string
                if ( $tire_rear && $tire_rear != $tire_front ) {
                    $size_str = $tire_front . '"/' . $tire_rear . '"';
                } else {
                    $size_str = $tire_front . '"';
                }
                // Build type string
                $type_str = '';
                if ( $tire_type ) {
                    $type_lower = strtolower( $tire_type );
                    if ( $type_lower === 'pneumatic' ) {
                        $type_str = $tubeless ? 'tubeless' : 'pneumatic';
                    } elseif ( $type_lower === 'solid' ) {
                        $type_str = 'solid';
                    } elseif ( $type_lower === 'mixed' ) {
                        $type_str = 'mixed';
                    }
                }
                $result[] = $size_str . ( $type_str ? ' ' . $type_str : '' ) . ' tires';
            }

            // 9. Suspension
            $suspension_type = $get( 'suspension.type' );
            if ( $suspension_type && is_array( $suspension_type ) && ! empty( $suspension_type ) ) {
                // Check if front and rear
                $has_front = false;
                $has_rear  = false;
                foreach ( $suspension_type as $susp ) {
                    if ( stripos( $susp, 'front' ) !== false ) {
                        $has_front = true;
                    }
                    if ( stripos( $susp, 'rear' ) !== false ) {
                        $has_rear = true;
                    }
                }
                if ( $has_front && $has_rear ) {
                    $result[] = 'dual suspension';
                } elseif ( $has_front ) {
                    $result[] = 'front suspension';
                } elseif ( $has_rear ) {
                    $result[] = 'rear suspension';
                }
            }

            // 10. Brakes - e.g., "dual disc brakes" or "front drum, rear disc"
            $brake_front = $get( 'brakes.front' );
            $brake_rear  = $get( 'brakes.rear' );
            if ( $brake_front && $brake_rear ) {
                $front_lower = strtolower( $brake_front );
                $rear_lower  = strtolower( $brake_rear );
                if ( $front_lower === $rear_lower && $front_lower !== 'none' ) {
                    $result[] = 'dual ' . $front_lower . ' brakes';
                } elseif ( $front_lower !== 'none' && $rear_lower !== 'none' ) {
                    $result[] = 'front ' . $front_lower . ', rear ' . $rear_lower;
                } elseif ( $front_lower !== 'none' ) {
                    $result[] = 'front ' . $front_lower . ' brake';
                } elseif ( $rear_lower !== 'none' ) {
                    $result[] = 'rear ' . $rear_lower . ' brake';
                }
            }

            // 11. IP rating (if not none/unknown)
            $ip_rating = $get( 'build.ip_rating' );
            if ( $ip_rating && ! in_array( strtolower( $ip_rating ), array( 'none', 'unknown', '' ), true ) ) {
                $result[] = $ip_rating;
            }
            break;

        case 'ebike':
            // Product type
            $result[] = 'Electric Bike';

            // Motor power
            $motor = $get( 'motor.power_nominal' );
            if ( $motor ) {
                $result[] = $motor . 'W motor';
            }

            // Range
            $range = $get( 'battery.range_claimed' );
            if ( $range ) {
                $result[] = '(' . $range . ' mi range)';
            }

            // Battery capacity
            $battery = $get( 'battery.capacity' );
            if ( $battery ) {
                $result[] = $battery . 'Wh battery';
            }

            // Weight
            $weight = $get( 'frame.weight' );
            if ( $weight ) {
                $result[] = $weight . ' lbs';
            }

            // Class
            $class = $get( 'motor.class' );
            if ( $class ) {
                $result[] = 'Class ' . $class;
            }
            break;

        default:
            // Generic fallback
            $result[] = $product_type ?: 'Electric Vehicle';

            $motor = $get( 'motor.power_nominal' );
            if ( $motor ) {
                $result[] = $motor . 'W motor';
            }

            $range = $get( 'manufacturer_range' );
            if ( $range ) {
                $result[] = '(' . $range . ' mi range)';
            }

            $weight = $get( 'dimensions.weight' );
            if ( $weight ) {
                $result[] = $weight . ' lbs';
            }
            break;
    }

    return $result;
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
					array( 'key' => 'other.display_type', 'label' => 'Display' ),
					array( 'key' => 'other.throttle_type', 'label' => 'Throttle' ),
					array( 'key' => 'other.kickstand', 'label' => 'Kickstand', 'format' => 'boolean' ),
					// Individual feature checkboxes - rendered with Yes/No indicators
					array( 'key' => 'features', 'label' => 'App', 'format' => 'feature_check', 'feature_value' => 'App' ),
					array( 'key' => 'features', 'label' => 'Speed Modes', 'format' => 'feature_check', 'feature_value' => 'Speed Modes' ),
					array( 'key' => 'features', 'label' => 'Cruise Control', 'format' => 'feature_check', 'feature_value' => 'Cruise Control' ),
					array( 'key' => 'features', 'label' => 'Folding', 'format' => 'feature_check', 'feature_value' => 'Folding' ),
					array( 'key' => 'features', 'label' => 'Push-To-Start', 'format' => 'feature_check', 'feature_value' => 'Push-To-Start' ),
					array( 'key' => 'features', 'label' => 'Zero-Start', 'format' => 'feature_check', 'feature_value' => 'Zero-Start' ),
					array( 'key' => 'features', 'label' => 'Brake Adjustment', 'format' => 'feature_check', 'feature_value' => 'Brake Curve Adjustment' ),
					array( 'key' => 'features', 'label' => 'Accel Adjustment', 'format' => 'feature_check', 'feature_value' => 'Acceleration Adjustment' ),
					array( 'key' => 'features', 'label' => 'Speed Limiting', 'format' => 'feature_check', 'feature_value' => 'Speed Limiting' ),
					array( 'key' => 'features', 'label' => 'OTA Updates', 'format' => 'feature_check', 'feature_value' => 'OTA Updates' ),
					array( 'key' => 'features', 'label' => 'Location Tracking', 'format' => 'feature_check', 'feature_value' => 'Location Tracking' ),
					array( 'key' => 'features', 'label' => 'Quick-Swap Battery', 'format' => 'feature_check', 'feature_value' => 'Quick-Swap Battery' ),
					array( 'key' => 'features', 'label' => 'Steering Damper', 'format' => 'feature_check', 'feature_value' => 'Steering Damper' ),
					array( 'key' => 'features', 'label' => 'Electronic Horn', 'format' => 'feature_check', 'feature_value' => 'Electronic Horn' ),
					array( 'key' => 'features', 'label' => 'NFC Unlock', 'format' => 'feature_check', 'feature_value' => 'NFC Unlock' ),
					array( 'key' => 'features', 'label' => 'Seat Option', 'format' => 'feature_check', 'feature_value' => 'Seat Option' ),
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
 * Get spec value from product_data specs array.
 *
 * Handles the database structure where:
 * - Flat keys (tested_top_speed, etc.) are at root level
 * - Nested specs are under product type key (e-scooters, e-bikes, etc.)
 *
 * @param array  $specs          Full specs array from product_data.
 * @param string $key            Spec key (may be dot-notation like 'motor.power_nominal').
 * @param string $nested_wrapper The wrapper key for nested specs ('e-scooters', 'e-bikes', etc.).
 * @return mixed|null Spec value or null.
 */
function erh_get_spec_from_cache( array $specs, string $key, string $nested_wrapper ) {
	// First try direct/flat key at root level (e.g., 'tested_top_speed')
	if ( strpos( $key, '.' ) === false ) {
		if ( isset( $specs[ $key ] ) && $specs[ $key ] !== '' && $specs[ $key ] !== null ) {
			return $specs[ $key ];
		}
	}

	// For nested keys (e.g., 'motor.power_nominal'), look inside the wrapper
	if ( ! empty( $specs[ $nested_wrapper ] ) && is_array( $specs[ $nested_wrapper ] ) ) {
		$value = erh_get_nested_value( $specs[ $nested_wrapper ], $key );
		if ( $value !== null && $value !== '' ) {
			return $value;
		}
	}

	// Also try at root level for nested keys (in case data structure varies)
	$value = erh_get_nested_value( $specs, $key );
	if ( $value !== null && $value !== '' ) {
		return $value;
	}

	return null;
}

/**
 * Get the nested wrapper key for a category.
 *
 * Returns the key used in product_data.specs for nested ACF fields.
 *
 * @param string $category Category key ('escooter', 'ebike', etc.).
 * @return string Wrapper key ('e-scooters', 'e-bikes', etc.).
 */
function erh_get_specs_wrapper_key( string $category ): string {
	$wrappers = array(
		'escooter'    => 'e-scooters',
		'ebike'       => 'e-bikes',
		'eskateboard' => 'e-skateboards',
		'euc'         => 'eucs',
		'hoverboard'  => 'hoverboards',
	);

	return $wrappers[ $category ] ?? 'e-scooters';
}

/**
 * Render specs HTML from wp_product_data table.
 *
 * Simple flat table layout with category headers - no expand/collapse.
 * Matches the compare tool's spec groupings.
 *
 * @param int    $product_id  Product ID.
 * @param string $category    Category key ('escooter', 'ebike', etc.).
 * @return string HTML for specs table.
 */
function erh_render_specs_from_cache( int $product_id, string $category ): string {
	// Get product data from wp_product_data cache table
	$product_data = erh_get_product_cache_data( $product_id );

	if ( ! $product_data || empty( $product_data['specs'] ) ) {
		return '<p class="specs-empty">Specifications not available.</p>';
	}

	$specs = $product_data['specs'];

	// Ensure specs is an array (it should be unserialized by erh_get_product_cache_data)
	if ( ! is_array( $specs ) ) {
		$specs = maybe_unserialize( $specs );
	}

	if ( ! is_array( $specs ) ) {
		return '<p class="specs-empty">Specifications not available.</p>';
	}

	$spec_groups = erh_get_spec_groups_config( $category );

	if ( empty( $spec_groups ) ) {
		return '<p class="specs-empty">Specifications not available for this product type.</p>';
	}

	// Get the wrapper key for nested specs (e.g., 'e-scooters' for escooter)
	$nested_wrapper = erh_get_specs_wrapper_key( $category );

	$html = '<div class="specs-table">';

	foreach ( $spec_groups as $category_name => $category_config ) {
		// Skip Value Analysis section on product page
		if ( ! empty( $category_config['is_value_section'] ) ) {
			continue;
		}

		$spec_defs = $category_config['specs'] ?? array();

		// Build spec rows
		$rows_html = '';
		foreach ( $spec_defs as $spec_def ) {
			// Special handling for feature_check format
			if ( ( $spec_def['format'] ?? '' ) === 'feature_check' ) {
				$features_array = erh_get_spec_from_cache( $specs, $spec_def['key'], $nested_wrapper );
				$has_feature    = is_array( $features_array ) && in_array( $spec_def['feature_value'], $features_array, true );
				$rows_html     .= erh_render_feature_check_row( $spec_def['label'], $has_feature );
				continue;
			}

			$value     = erh_get_spec_from_cache( $specs, $spec_def['key'], $nested_wrapper );
			$formatted = erh_format_spec_value( $value, $spec_def );

			// Skip empty values
			if ( $formatted === '' || $formatted === 'No' ) {
				continue;
			}

			$rows_html .= sprintf(
				'<tr><td class="specs-label">%s</td><td class="specs-value">%s</td></tr>',
				esc_html( $spec_def['label'] ),
				esc_html( $formatted )
			);
		}

		// Skip categories with no specs
		if ( empty( $rows_html ) ) {
			continue;
		}

		// Category header + rows
		$html .= sprintf(
			'<table class="specs-group">
				<thead><tr><th colspan="2" class="specs-group-header">%s</th></tr></thead>
				<tbody>%s</tbody>
			</table>',
			esc_html( $category_name ),
			$rows_html
		);
	}

	$html .= '</div>';

	return $html;
}

/**
 * Render a feature check row with Yes/No indicator.
 *
 * Displays a circle background with check/x icon from the sprite.
 *
 * @param string $label       Feature label.
 * @param bool   $has_feature Whether the product has this feature.
 * @return string HTML for the table row.
 */
function erh_render_feature_check_row( string $label, bool $has_feature ): string {
	$status_class = $has_feature ? 'feature-yes' : 'feature-no';
	$status_text  = $has_feature ? 'Yes' : 'No';
	$icon_name    = $has_feature ? 'check' : 'x';

	// Use icon from sprite with circle background
	$icon = sprintf(
		'<span class="feature-badge"><svg class="icon" aria-hidden="true"><use href="#icon-%s"></use></svg></span>',
		$icon_name
	);

	return sprintf(
		'<tr class="%s"><td class="specs-label">%s</td><td class="specs-value specs-feature-value">%s<span class="feature-text">%s</span></td></tr>',
		esc_attr( $status_class ),
		esc_html( $label ),
		$icon,
		esc_html( $status_text )
	);
}

/**
 * Get SEO-friendly spec groups for product page.
 *
 * Unlike the comparison tool's score-based groupings, this uses
 * logical groupings that match how users search for specs.
 *
 * @param string $category Category key ('escooter', 'ebike', etc.).
 * @return array Spec groups configuration with tooltips.
 */
function erh_get_product_spec_groups_config( string $category ): array {
	// Tooltips for specs that need clarification
	$tooltips = array(
		'nominal_power'     => 'Continuous power the motor can sustain. Higher = more consistent performance.',
		'peak_power'        => 'Maximum burst power for hills and acceleration. Used briefly to avoid overheating.',
		'battery_capacity'  => 'Total energy storage in Watt-hours. Larger battery = longer range but more weight.',
		'voltage'           => 'Higher voltage typically means more power and efficiency.',
		'charge_time'       => 'Time to charge from empty to full using the included charger.',
		'ip_rating'         => 'Ingress Protection rating. First digit = dust, second = water. IP54 = splash resistant, IP67 = submersible.',
		'regenerative'      => 'Recovers energy when braking to extend range slightly.',
		'tested_top_speed'  => 'GPS-verified max speed on flat ground. 175 lb rider, 80%+ battery.',
		'tested_range'      => 'Real-world range on mixed terrain. 175 lb rider, three tests at different intensities.',
		'brake_distance'    => 'Stopping distance from 15 mph with max braking. Average of 10+ runs.',
		'hill_climb'        => 'Average speed climbing 250 ft at 8% grade. 175 lb rider.',
		'acceleration'      => 'Median time from standstill to target speed over 10+ runs.',
		'suspension_type'   => 'Spring = traditional coil, Hydraulic = oil-damped for smoother ride.',
		'tire_type'         => 'Pneumatic = air-filled (comfort, grip). Solid = puncture-proof (less comfort).',
		'self_healing'      => 'Tires contain sealant that automatically plugs small punctures.',
		'ground_clearance'  => 'Height from ground to lowest point. Important for curbs and obstacles.',
		'max_load'          => 'Maximum recommended rider weight. Exceeding may void warranty.',
	);

	$configs = array(
		'escooter' => array(
			'Motor & Power' => array(
				'icon'  => 'zap',
				'specs' => array(
					array( 'key' => 'motor.power_nominal', 'label' => 'Nominal Power', 'unit' => 'W', 'tooltip' => $tooltips['nominal_power'] ),
					array( 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'tooltip' => $tooltips['peak_power'] ),
					array( 'key' => 'motor.voltage', 'label' => 'Voltage', 'unit' => 'V', 'tooltip' => $tooltips['voltage'] ),
					array( 'key' => 'motor.motor_position', 'label' => 'Motor Configuration' ),
					array( 'key' => 'motor.motor_type', 'label' => 'Motor Type' ),
				),
			),
			'Battery & Charging' => array(
				'icon'  => 'battery',
				'specs' => array(
					array( 'key' => 'battery.capacity', 'label' => 'Battery Capacity', 'unit' => 'Wh', 'tooltip' => $tooltips['battery_capacity'] ),
					array( 'key' => 'battery.voltage', 'label' => 'Battery Voltage', 'unit' => 'V' ),
					array( 'key' => 'battery.amphours', 'label' => 'Amp Hours', 'unit' => 'Ah' ),
					array( 'key' => 'battery.battery_type', 'label' => 'Battery Type' ),
					array( 'key' => 'battery.brand', 'label' => 'Battery Brand' ),
					array( 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'hrs', 'tooltip' => $tooltips['charge_time'] ),
				),
			),
			'Claimed Performance' => array(
				'icon'  => 'gauge',
				'specs' => array(
					array( 'key' => 'manufacturer_top_speed', 'label' => 'Top Speed (Claimed)', 'unit' => 'mph' ),
					array( 'key' => 'manufacturer_range', 'label' => 'Range (Claimed)', 'unit' => 'mi' ),
					array( 'key' => 'max_incline', 'label' => 'Max Hill Grade', 'unit' => '°' ),
				),
			),
			'ERideHero Test Results' => array(
				'icon'  => 'clipboard-check',
				'specs' => array(
					array( 'key' => 'tested_top_speed', 'label' => 'Top Speed (Tested)', 'unit' => 'mph', 'tooltip' => $tooltips['tested_top_speed'] ),
					array( 'key' => 'tested_range_regular', 'label' => 'Range (Regular Riding)', 'unit' => 'mi', 'tooltip' => $tooltips['tested_range'] ),
					array( 'key' => 'tested_range_fast', 'label' => 'Range (Fast Riding)', 'unit' => 'mi' ),
					array( 'key' => 'tested_range_slow', 'label' => 'Range (Eco Mode)', 'unit' => 'mi' ),
					array( 'key' => 'acceleration_0_15_mph', 'label' => '0–15 mph', 'unit' => 's', 'tooltip' => $tooltips['acceleration'] ),
					array( 'key' => 'acceleration_0_20_mph', 'label' => '0–20 mph', 'unit' => 's' ),
					array( 'key' => 'acceleration_0_25_mph', 'label' => '0–25 mph', 'unit' => 's' ),
					array( 'key' => 'acceleration_0_30_mph', 'label' => '0–30 mph', 'unit' => 's' ),
					array( 'key' => 'hill_climbing', 'label' => 'Hill Climb Speed', 'unit' => 'mph', 'tooltip' => $tooltips['hill_climb'] ),
					array( 'key' => 'brake_distance', 'label' => 'Braking Distance', 'unit' => 'ft', 'tooltip' => $tooltips['brake_distance'] ),
				),
			),
			'Weight & Dimensions' => array(
				'icon'  => 'box',
				'specs' => array(
					array( 'key' => 'dimensions.weight', 'label' => 'Weight', 'unit' => 'lbs' ),
					array( 'key' => 'dimensions.max_load', 'label' => 'Max Rider Weight', 'unit' => 'lbs', 'tooltip' => $tooltips['max_load'] ),
					array( 'key' => 'dimensions.deck_length', 'label' => 'Deck Length', 'unit' => '"' ),
					array( 'key' => 'dimensions.deck_width', 'label' => 'Deck Width', 'unit' => '"' ),
					array( 'key' => 'dimensions.ground_clearance', 'label' => 'Ground Clearance', 'unit' => '"', 'tooltip' => $tooltips['ground_clearance'] ),
					array( 'key' => 'dimensions.handlebar_height_min', 'label' => 'Handlebar Height (Min)', 'unit' => '"' ),
					array( 'key' => 'dimensions.handlebar_height_max', 'label' => 'Handlebar Height (Max)', 'unit' => '"' ),
					array( 'key' => 'dimensions.handlebar_width', 'label' => 'Handlebar Width', 'unit' => '"' ),
					array( 'key' => 'dimensions.unfolded_length', 'label' => 'Length (Unfolded)', 'unit' => '"' ),
					array( 'key' => 'dimensions.unfolded_width', 'label' => 'Width (Unfolded)', 'unit' => '"' ),
					array( 'key' => 'dimensions.unfolded_height', 'label' => 'Height (Unfolded)', 'unit' => '"' ),
					array( 'key' => 'dimensions.folded_length', 'label' => 'Length (Folded)', 'unit' => '"' ),
					array( 'key' => 'dimensions.folded_width', 'label' => 'Width (Folded)', 'unit' => '"' ),
					array( 'key' => 'dimensions.folded_height', 'label' => 'Height (Folded)', 'unit' => '"' ),
					array( 'key' => 'dimensions.foldable_handlebars', 'label' => 'Foldable Handlebars', 'format' => 'feature_check', 'feature_value' => true ),
				),
			),
			'Wheels & Suspension' => array(
				'icon'  => 'circle',
				'specs' => array(
					array( 'key' => 'wheels.tire_type', 'label' => 'Tire Type', 'tooltip' => $tooltips['tire_type'] ),
					array( 'key' => 'wheels.tire_size_front', 'label' => 'Front Tire Size', 'unit' => '"' ),
					array( 'key' => 'wheels.tire_size_rear', 'label' => 'Rear Tire Size', 'unit' => '"' ),
					array( 'key' => 'wheels.tire_width', 'label' => 'Tire Width', 'unit' => '"' ),
					array( 'key' => 'wheels.pneumatic_type', 'label' => 'Pneumatic Type' ),
					array( 'key' => 'wheels.self_healing', 'label' => 'Self-Healing Tires', 'format' => 'feature_check', 'feature_value' => true, 'tooltip' => $tooltips['self_healing'] ),
					array( 'key' => 'suspension.type', 'label' => 'Suspension', 'format' => 'array', 'tooltip' => $tooltips['suspension_type'] ),
					array( 'key' => 'suspension.adjustable', 'label' => 'Adjustable Suspension', 'format' => 'feature_check', 'feature_value' => true ),
				),
			),
			'Brakes & Safety' => array(
				'icon'  => 'shield',
				'specs' => array(
					array( 'key' => 'brakes.front', 'label' => 'Front Brake' ),
					array( 'key' => 'brakes.rear', 'label' => 'Rear Brake' ),
					array( 'key' => 'brakes.regenerative', 'label' => 'Regenerative Braking', 'format' => 'feature_check', 'feature_value' => true, 'tooltip' => $tooltips['regenerative'] ),
					array( 'key' => 'lighting.lights', 'label' => 'Lights', 'format' => 'array' ),
					array( 'key' => 'lighting.turn_signals', 'label' => 'Turn Signals', 'format' => 'feature_check', 'feature_value' => true ),
					array( 'key' => 'other.ip_rating', 'label' => 'IP Rating', 'tooltip' => $tooltips['ip_rating'] ),
				),
			),
			'Features' => array(
				'icon'  => 'settings',
				'specs' => array(
					array( 'key' => 'other.display_type', 'label' => 'Display Type' ),
					array( 'key' => 'other.throttle_type', 'label' => 'Throttle Type' ),
					array( 'key' => 'other.terrain', 'label' => 'Terrain Type' ),
					array( 'key' => 'other.kickstand', 'label' => 'Kickstand', 'format' => 'feature_check', 'feature_value' => true ),
					array( 'key' => 'other.footrest', 'label' => 'Footrest', 'format' => 'feature_check', 'feature_value' => true ),
					// Individual feature checkboxes
					array( 'key' => 'features', 'label' => 'App Connectivity', 'format' => 'feature_check', 'feature_value' => 'App' ),
					array( 'key' => 'features', 'label' => 'Speed Modes', 'format' => 'feature_check', 'feature_value' => 'Speed Modes' ),
					array( 'key' => 'features', 'label' => 'Cruise Control', 'format' => 'feature_check', 'feature_value' => 'Cruise Control' ),
					array( 'key' => 'features', 'label' => 'Folding', 'format' => 'feature_check', 'feature_value' => 'Folding' ),
					array( 'key' => 'features', 'label' => 'Push-To-Start', 'format' => 'feature_check', 'feature_value' => 'Push-To-Start' ),
					array( 'key' => 'features', 'label' => 'Zero-Start', 'format' => 'feature_check', 'feature_value' => 'Zero-Start' ),
					array( 'key' => 'features', 'label' => 'Brake Curve Adjustment', 'format' => 'feature_check', 'feature_value' => 'Brake Curve Adjustment' ),
					array( 'key' => 'features', 'label' => 'Acceleration Adjustment', 'format' => 'feature_check', 'feature_value' => 'Acceleration Adjustment' ),
					array( 'key' => 'features', 'label' => 'Speed Limiting', 'format' => 'feature_check', 'feature_value' => 'Speed Limiting' ),
					array( 'key' => 'features', 'label' => 'OTA Updates', 'format' => 'feature_check', 'feature_value' => 'OTA Updates' ),
					array( 'key' => 'features', 'label' => 'Location Tracking', 'format' => 'feature_check', 'feature_value' => 'Location Tracking' ),
					array( 'key' => 'features', 'label' => 'Quick-Swap Battery', 'format' => 'feature_check', 'feature_value' => 'Quick-Swap Battery' ),
					array( 'key' => 'features', 'label' => 'Steering Damper', 'format' => 'feature_check', 'feature_value' => 'Steering Damper' ),
					array( 'key' => 'features', 'label' => 'Electronic Horn', 'format' => 'feature_check', 'feature_value' => 'Electronic Horn' ),
					array( 'key' => 'features', 'label' => 'NFC Unlock', 'format' => 'feature_check', 'feature_value' => 'NFC Unlock' ),
					array( 'key' => 'features', 'label' => 'Seat Option', 'format' => 'feature_check', 'feature_value' => 'Seat Option' ),
				),
			),
		),
		'ebike' => array(
			'Motor & Power' => array(
				'icon'  => 'zap',
				'specs' => array(
					array( 'key' => 'motor.power_nominal', 'label' => 'Nominal Power', 'unit' => 'W', 'tooltip' => $tooltips['nominal_power'] ),
					array( 'key' => 'motor.power_peak', 'label' => 'Peak Power', 'unit' => 'W', 'tooltip' => $tooltips['peak_power'] ),
					array( 'key' => 'motor.torque', 'label' => 'Torque', 'unit' => 'Nm' ),
					array( 'key' => 'motor.type', 'label' => 'Motor Type' ),
					array( 'key' => 'motor.position', 'label' => 'Motor Position' ),
				),
			),
			'Battery & Range' => array(
				'icon'  => 'battery',
				'specs' => array(
					array( 'key' => 'battery.capacity', 'label' => 'Battery Capacity', 'unit' => 'Wh', 'tooltip' => $tooltips['battery_capacity'] ),
					array( 'key' => 'battery.voltage', 'label' => 'Voltage', 'unit' => 'V' ),
					array( 'key' => 'battery.range_claimed', 'label' => 'Range (Claimed)', 'unit' => 'mi' ),
					array( 'key' => 'battery.charging_time', 'label' => 'Charge Time', 'unit' => 'hrs', 'tooltip' => $tooltips['charge_time'] ),
					array( 'key' => 'battery.removable', 'label' => 'Removable Battery', 'format' => 'boolean' ),
				),
			),
			'Speed & Performance' => array(
				'icon'  => 'gauge',
				'specs' => array(
					array( 'key' => 'performance.top_speed', 'label' => 'Top Speed', 'unit' => 'mph' ),
					array( 'key' => 'performance.class', 'label' => 'E-Bike Class' ),
					array( 'key' => 'performance.pedal_assist_levels', 'label' => 'Pedal Assist Levels' ),
					array( 'key' => 'performance.throttle', 'label' => 'Throttle', 'format' => 'boolean' ),
				),
			),
			'Frame & Build' => array(
				'icon'  => 'box',
				'specs' => array(
					array( 'key' => 'frame.weight', 'label' => 'Weight', 'unit' => 'lbs' ),
					array( 'key' => 'frame.max_load', 'label' => 'Max Load', 'unit' => 'lbs', 'tooltip' => $tooltips['max_load'] ),
					array( 'key' => 'frame.material', 'label' => 'Frame Material' ),
					array( 'key' => 'frame.type', 'label' => 'Frame Type' ),
					array( 'key' => 'frame.suspension', 'label' => 'Suspension' ),
					array( 'key' => 'frame.foldable', 'label' => 'Foldable', 'format' => 'boolean' ),
				),
			),
			'Components' => array(
				'icon'  => 'settings',
				'specs' => array(
					array( 'key' => 'components.gears', 'label' => 'Gears' ),
					array( 'key' => 'components.brakes', 'label' => 'Brakes' ),
					array( 'key' => 'components.wheel_size', 'label' => 'Wheel Size', 'unit' => '"' ),
					array( 'key' => 'components.tire_type', 'label' => 'Tire Type' ),
					array( 'key' => 'components.display', 'label' => 'Display' ),
					array( 'key' => 'components.lights', 'label' => 'Lights' ),
				),
			),
		),
	);

	return $configs[ $category ] ?? array();
}

/**
 * Render product specs HTML with bordered sections.
 *
 * Uses SEO-friendly groupings with heading + bordered table per group.
 * Similar styling to the tested-performance section.
 *
 * @param int    $product_id Product ID.
 * @param string $category   Category key ('escooter', 'ebike', etc.).
 * @return string HTML for specs sections.
 */
function erh_render_product_specs( int $product_id, string $category ): string {
	// Get product data from wp_product_data cache table
	$product_data = erh_get_product_cache_data( $product_id );

	if ( ! $product_data || empty( $product_data['specs'] ) ) {
		return '<p class="specs-empty">Specifications not available.</p>';
	}

	$specs = $product_data['specs'];

	// Ensure specs is an array
	if ( ! is_array( $specs ) ) {
		$specs = maybe_unserialize( $specs );
	}

	if ( ! is_array( $specs ) ) {
		return '<p class="specs-empty">Specifications not available.</p>';
	}

	$spec_groups = erh_get_product_spec_groups_config( $category );

	if ( empty( $spec_groups ) ) {
		return '<p class="specs-empty">Specifications not available for this product type.</p>';
	}

	// Get the wrapper key for nested specs
	$nested_wrapper = erh_get_specs_wrapper_key( $category );

	$html = '<div class="product-specs">';

	foreach ( $spec_groups as $group_name => $group_config ) {
		$spec_defs = $group_config['specs'] ?? array();
		$icon      = $group_config['icon'] ?? 'info';

		// Build spec rows
		$rows_html     = '';
		$has_any_specs = false;

		foreach ( $spec_defs as $spec_def ) {
			// Special handling for feature_check format
			if ( ( $spec_def['format'] ?? '' ) === 'feature_check' ) {
				$feature_value = $spec_def['feature_value'] ?? '';
				$raw_value     = erh_get_spec_from_cache( $specs, $spec_def['key'], $nested_wrapper );

				// Determine if feature is present
				if ( $feature_value === true ) {
					// Boolean field - check if truthy
					$has_feature = ! empty( $raw_value ) && $raw_value !== 'No' && $raw_value !== 'no' && $raw_value !== '0';
				} else {
					// Array field - check if value is in array
					$has_feature = is_array( $raw_value ) && in_array( $feature_value, $raw_value, true );
				}

				$rows_html .= erh_render_spec_row_with_tooltip(
					$spec_def['label'],
					$has_feature ? 'Yes' : 'No',
					$spec_def['tooltip'] ?? '',
					$has_feature ? 'feature-yes' : 'feature-no',
					true
				);
				$has_any_specs = true;
				continue;
			}

			$value     = erh_get_spec_from_cache( $specs, $spec_def['key'], $nested_wrapper );
			$formatted = erh_format_spec_value( $value, $spec_def );

			// Skip empty values and "No" for booleans
			if ( $formatted === '' || $formatted === 'No' ) {
				continue;
			}

			$rows_html .= erh_render_spec_row_with_tooltip(
				$spec_def['label'],
				$formatted,
				$spec_def['tooltip'] ?? ''
			);
			$has_any_specs = true;
		}

		// Skip groups with no specs
		if ( ! $has_any_specs ) {
			continue;
		}

		// Render group section
		// Special handling for ERideHero Test Results - add "How we test" popover
		if ( $group_name === 'ERideHero Test Results' ) {
			$html .= sprintf(
				'<section class="product-specs-section">
					<div class="product-specs-header">
						<h3 class="product-specs-title">%s</h3>
						<div class="popover-wrapper">
							<button type="button" class="btn btn-link btn-sm" data-popover-trigger="how-we-test-popover-product">
								<svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg>
								How we test
							</button>
							<div id="how-we-test-popover-product" class="popover popover--top" aria-hidden="true">
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
					<div class="product-specs-box">
						<table class="product-specs-table">
							<tbody>%s</tbody>
						</table>
					</div>
				</section>',
				esc_html( $group_name ),
				$rows_html
			);
		} else {
			$html .= sprintf(
				'<section class="product-specs-section">
					<h3 class="product-specs-title">%s</h3>
					<div class="product-specs-box">
						<table class="product-specs-table">
							<tbody>%s</tbody>
						</table>
					</div>
				</section>',
				esc_html( $group_name ),
				$rows_html
			);
		}
	}

	$html .= '</div>';

	return $html;
}

/**
 * Render a spec row with optional tooltip.
 *
 * @param string $label       Spec label.
 * @param string $value       Formatted value.
 * @param string $tooltip     Tooltip text (optional).
 * @param string $row_class   Additional row class (optional).
 * @param bool   $is_feature  Whether this is a feature check row.
 * @return string HTML for the table row.
 */
function erh_render_spec_row_with_tooltip( string $label, string $value, string $tooltip = '', string $row_class = '', bool $is_feature = false ): string {
	// Build label - wrap in flex container if tooltip present
	if ( ! empty( $tooltip ) ) {
		$label_html = sprintf(
			'<div class="product-specs-label-inner">%s<span class="info-trigger" data-tooltip="%s" data-tooltip-position="top"><svg class="icon" aria-hidden="true"><use href="#icon-info"></use></svg></span></div>',
			esc_html( $label ),
			esc_attr( $tooltip )
		);
	} else {
		$label_html = esc_html( $label );
	}

	// Build value HTML - wrap in flex container if feature check
	if ( $is_feature ) {
		$is_yes    = $value === 'Yes';
		$icon_name = $is_yes ? 'check' : 'x';
		$value_html = sprintf(
			'<div class="product-specs-value-inner"><span class="feature-badge"><svg class="icon" aria-hidden="true"><use href="#icon-%s"></use></svg></span><span class="feature-text">%s</span></div>',
			$icon_name,
			esc_html( $value )
		);
		$value_class = 'product-specs-value';
	} else {
		$value_html  = esc_html( $value );
		$value_class = 'product-specs-value';
	}

	return sprintf(
		'<tr class="%s"><td class="product-specs-label">%s</td><td class="%s">%s</td></tr>',
		esc_attr( $row_class ),
		$label_html,
		$value_class,
		$value_html
	);
}
