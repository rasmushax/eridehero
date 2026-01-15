<?php
/**
 * Spec Value Component
 *
 * Renders spec table cell content with optional winner highlighting.
 *
 * SYNC: Must match assets/js/components/compare/renderers.js:renderSpecCell()
 *
 * @package ERideHero
 */

defined( 'ABSPATH' ) || exit;

// Include dependencies.
require_once __DIR__ . '/winner-badge.php';

/**
 * Render a spec value for comparison table cells.
 *
 * Handles different value types:
 * - Numeric with unit
 * - Boolean (yes/no with badge)
 * - Arrays (suspension types, features)
 * - Missing values (em-dash)
 *
 * @param mixed  $value     The raw spec value.
 * @param array  $spec      Spec configuration (label, unit, format, higherBetter, tooltip).
 * @param bool   $is_winner Whether this value is the winner.
 * @return string HTML output for the cell content (not the <td> wrapper).
 */
function erh_spec_value( $value, array $spec, bool $is_winner = false ): string {
    // Handle missing values.
    if ( $value === null || $value === '' ) {
        return '—';
    }

    $format = $spec['format'] ?? '';

    // Boolean formatting.
    if ( $format === 'boolean' ) {
        return erh_spec_value_boolean( $value );
    }

    // Format the display value.
    $formatted = erh_format_spec_value( $value, $spec );

    // Winner cell with badge.
    if ( $is_winner ) {
        return sprintf(
            '<div class="compare-spec-value-inner">%s<span class="compare-spec-value-text">%s</span></div>',
            erh_winner_badge( 'compare-spec-badge' ),
            esc_html( $formatted )
        );
    }

    return esc_html( $formatted );
}

/**
 * Render a boolean spec value with feature badge.
 *
 * @param mixed $value The value to evaluate as boolean.
 * @return string HTML output.
 */
function erh_spec_value_boolean( $value ): string {
    $is_yes = is_bool( $value ) ? $value : ( $value === 'Yes' || $value === 'yes' || $value === '1' || $value === 1 || $value === true );
    $class  = $is_yes ? 'feature-yes' : 'feature-no';
    $icon   = $is_yes ? 'check' : 'x';
    $text   = $is_yes ? 'Yes' : 'No';

    return sprintf(
        '<div class="compare-spec-value-inner %s"><span class="compare-feature-badge">%s</span><span class="compare-feature-text">%s</span></div>',
        esc_attr( $class ),
        erh_icon( $icon ),
        esc_html( $text )
    );
}

/**
 * Format a spec value for display.
 *
 * Mirrors the JS formatSpecValue() function in compare-config.js.
 *
 * @param mixed $value Raw value.
 * @param array $spec  Spec configuration.
 * @return string Formatted display string.
 */
function erh_format_spec_value( $value, array $spec ): string {
    if ( $value === null || $value === '' ) {
        return '—';
    }

    $format = $spec['format'] ?? '';

    // Handle arrays (like suspension.type or features).
    if ( is_array( $value ) ) {
        if ( empty( $value ) ) {
            return '—';
        }

        // Suspension array format.
        if ( $format === 'suspensionArray' ) {
            $filtered = array_filter( $value, fn( $v ) => $v && $v !== 'None' );
            return empty( $filtered ) ? 'None' : implode( ', ', $filtered );
        }

        // Feature array format - show count and first few.
        if ( $format === 'featureArray' ) {
            if ( count( $value ) <= 3 ) {
                return implode( ', ', $value );
            }
            return implode( ', ', array_slice( $value, 0, 3 ) ) . ' +' . ( count( $value ) - 3 ) . ' more';
        }

        // Generic array.
        return implode( ', ', $value );
    }

    // Handle objects (like suspension with front/rear).
    if ( is_object( $value ) || ( is_array( $value ) && ! isset( $value[0] ) ) ) {
        $value = (array) $value;
        if ( isset( $value['front'] ) || isset( $value['rear'] ) ) {
            $front = $value['front'] ?? 'None';
            $rear  = $value['rear'] ?? 'None';
            if ( $front === $rear ) {
                return $front;
            }
            if ( $front === 'None' ) {
                return 'Rear: ' . $rear;
            }
            if ( $rear === 'None' ) {
                return 'Front: ' . $front;
            }
            return 'F: ' . $front . ', R: ' . $rear;
        }
        if ( isset( $value['type'] ) ) {
            if ( is_array( $value['type'] ) ) {
                $filtered = array_filter( $value['type'], fn( $v ) => $v && $v !== 'None' );
                return empty( $filtered ) ? 'None' : implode( ', ', $filtered );
            }
            return (string) $value['type'];
        }
    }

    // Boolean formatting.
    if ( $format === 'boolean' ) {
        if ( $value === true || $value === 'Yes' || $value === 'yes' || $value === 1 ) {
            return 'Yes';
        }
        if ( $value === false || $value === 'No' || $value === 'no' || $value === 0 ) {
            return 'No';
        }
        return (string) $value;
    }

    // IP rating formatting.
    if ( $format === 'ip' ) {
        return strtoupper( (string) $value );
    }

    // Currency formatting (value metrics like $24.22/Wh).
    if ( $format === 'currency' ) {
        $num = (float) $value;
        if ( ! is_nan( $num ) ) {
            $symbol = $spec['currencySymbol'] ?? '$';
            $unit   = $spec['valueUnit'] ?? '';
            return $symbol . number_format( $num, 2 ) . $unit;
        }
        return '—';
    }

    // Decimal formatting (efficiency metrics like 0.45 mph/lb).
    if ( $format === 'decimal' ) {
        $num = (float) $value;
        if ( ! is_nan( $num ) ) {
            $unit = isset( $spec['valueUnit'] ) ? ' ' . $spec['valueUnit'] : '';
            return number_format( $num, 2 ) . $unit;
        }
        return '—';
    }

    // Numeric with unit.
    if ( ! empty( $spec['unit'] ) && is_numeric( $value ) ) {
        $num = (float) $value;
        if ( floor( $num ) == $num ) {
            return (int) $num . ' ' . $spec['unit'];
        }
        return number_format( $num, 1 ) . ' ' . $spec['unit'];
    }

    return (string) $value;
}

/**
 * Render a complete table cell (<td>) with spec value.
 *
 * @param mixed $value     The raw spec value.
 * @param array $spec      Spec configuration.
 * @param bool  $is_winner Whether this value is the winner.
 * @return string HTML output for the <td> element.
 */
function erh_spec_cell( $value, array $spec, bool $is_winner = false ): string {
    $format = $spec['format'] ?? '';

    // Boolean cells get special class.
    if ( $format === 'boolean' ) {
        $is_yes = is_bool( $value ) ? $value : ( $value === 'Yes' || $value === 'yes' || $value === '1' || $value === 1 || $value === true );
        $class  = $is_yes ? 'feature-yes' : 'feature-no';
        return sprintf(
            '<td class="%s">%s</td>',
            esc_attr( $class ),
            erh_spec_value( $value, $spec, false )
        );
    }

    // Winner cells.
    if ( $is_winner ) {
        return sprintf(
            '<td class="is-winner">%s</td>',
            erh_spec_value( $value, $spec, true )
        );
    }

    // Standard cells.
    return sprintf( '<td>%s</td>', erh_spec_value( $value, $spec, false ) );
}

/**
 * Echo a spec value.
 *
 * @param mixed $value     The raw spec value.
 * @param array $spec      Spec configuration.
 * @param bool  $is_winner Whether this value is the winner.
 */
function erh_the_spec_value( $value, array $spec, bool $is_winner = false ): void {
    echo erh_spec_value( $value, $spec, $is_winner );
}

/**
 * Echo a spec cell.
 *
 * @param mixed $value     The raw spec value.
 * @param array $spec      Spec configuration.
 * @param bool  $is_winner Whether this value is the winner.
 */
function erh_the_spec_cell( $value, array $spec, bool $is_winner = false ): void {
    echo erh_spec_cell( $value, $spec, $is_winner );
}
