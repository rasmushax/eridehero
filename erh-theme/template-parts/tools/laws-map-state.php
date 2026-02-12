<?php
/**
 * Laws Map - Single State Detail Card
 *
 * Renders one state's detail section for the laws map.
 * PHP renders basic text values for SEO; JS enhances with icons/formatting.
 *
 * @package ERideHero
 *
 * @param array $args {
 *     @type string $state_id   Two-letter state code (e.g. 'CA').
 *     @type array  $state_data State law data from laws.json.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$state_id   = $args['state_id'] ?? '';
$state_data = $args['state_data'] ?? [];

if ( empty( $state_id ) || empty( $state_data ) ) {
    return;
}

$name = esc_html( $state_data['name'] ?? $state_id );

// Classification → display label mapping (must match JS CLASSIFICATION_MAP).
$classification_map = [
    'specific_escooter' => [ 'label' => 'Legal',              'icon' => 'check-circle',  'color' => 'icon-green' ],
    'local_rule'        => [ 'label' => 'Varies by Location', 'icon' => 'interrogation', 'color' => 'icon-orange' ],
    'unclear_or_local'  => [ 'label' => 'Unclear / Local',    'icon' => 'interrogation', 'color' => 'icon-orange' ],
    'prohibited'        => [ 'label' => 'Prohibited',         'icon' => 'cross-circle',  'color' => 'icon-red' ],
];

$positive_values = [ 'allowed', 'required', 'yes' ];
$negative_values = [ 'prohibited', 'not_allowed', 'none', 'not_required' ];

/**
 * Inline SVG status icon.
 */
$status_icon = function ( string $icon, string $color_class ): string {
    return '<svg class="status-icon ' . esc_attr( $color_class ) . '" aria-hidden="true"><use xlink:href="#' . esc_attr( $icon ) . '"></use></svg>';
};

/**
 * Format classification with mapped label + icon.
 */
$format_classification = function ( ?string $value ) use ( $classification_map, $status_icon ): string {
    if ( $value === null || ! isset( $classification_map[ $value ] ) ) {
        return '<span class="laws-map-na">N/A</span>';
    }
    $c = $classification_map[ $value ];
    return $status_icon( $c['icon'], $c['color'] ) . ' ' . esc_html( $c['label'] );
};

/**
 * Format any field value with appropriate status icon.
 * Handles booleans, numeric+unit, and string enum values.
 */
$format_field = function ( $value, string $unit = '' ) use ( $status_icon, $positive_values, $negative_values ): string {
    if ( $value === null || $value === '' ) {
        return '<span class="laws-map-na">N/A</span>';
    }
    if ( is_bool( $value ) ) {
        $icon = $value ? $status_icon( 'check-circle', 'icon-green' ) : $status_icon( 'cross-circle', 'icon-red' );
        return $icon . ( $value ? ' Yes' : ' No' );
    }
    if ( is_numeric( $value ) ) {
        return esc_html( $value . $unit );
    }
    // String enum value.
    $label = esc_html( ucwords( str_replace( '_', ' ', $value ) ) );
    if ( in_array( $value, $positive_values, true ) ) {
        return $status_icon( 'check-circle', 'icon-green' ) . ' ' . $label;
    }
    if ( in_array( $value, $negative_values, true ) ) {
        return $status_icon( 'cross-circle', 'icon-red' ) . ' ' . $label;
    }
    return $status_icon( 'interrogation', 'icon-orange' ) . ' ' . $label;
};
?>
<section id="<?php echo esc_attr( $state_id ); ?>-details">
    <h2><?php echo $name; ?> E-Scooter Law Details</h2>

    <!-- Basic data grid -->
    <div class="details-grid">
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#legal"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">State Classification</span>
                <span class="item-value" data-field="classification"><?php echo $format_classification( $state_data['classification'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#age-alt"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Minimum Age</span>
                <span class="item-value" data-field="minAge"><?php echo $format_field( $state_data['minAge'] ?? null, ' years' ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#tachometer-fast"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Maximum Speed</span>
                <span class="item-value" data-field="maxSpeedMph"><?php echo $format_field( $state_data['maxSpeedMph'] ?? null, ' MPH' ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#idbadge"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Driver's License Required</span>
                <span class="item-value" data-field="licenseRequired"><?php echo $format_field( $state_data['licenseRequired'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#memo"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Registration Required</span>
                <span class="item-value" data-field="registrationRequired"><?php echo $format_field( $state_data['registrationRequired'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#biking-mountain"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Bike Lane Riding</span>
                <span class="item-value" data-field="bikeLaneRiding"><?php echo $format_field( $state_data['bikeLaneRiding'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#brightness"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Lights Required</span>
                <span class="item-value" data-field="lightsRequired"><?php echo $format_field( $state_data['lightsRequired'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#brake"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Brakes Required</span>
                <span class="item-value" data-field="brakesRequired"><?php echo $format_field( $state_data['brakesRequired'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#auction"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">DUI Laws Apply</span>
                <span class="item-value" data-field="duiApplies"><?php echo $format_field( $state_data['duiApplies'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#tachometer-fast"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Max Power</span>
                <span class="item-value" data-field="maxPowerWatts"><?php echo $format_field( $state_data['maxPowerWatts'] ?? null, ' Watts' ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#weight"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Max Weight</span>
                <span class="item-value" data-field="maxWeightLbs"><?php echo $format_field( $state_data['maxWeightLbs'] ?? null, ' lbs' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Fields with notes -->
    <div class="notes-section">
        <div class="details-full-width-item">
            <div class="details-full-width-top">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#motorcycle-helmet"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Helmet Required</span>
                    <span class="item-value" data-field="helmetRequired"><?php echo $format_field( $state_data['helmetRequired'] ?? null ); ?></span>
                </div>
            </div>
            <div class="item-note" data-field="helmetNote"><?php echo esc_html( $state_data['helmetNotes'] ?? '' ); ?></div>
        </div>
        <div class="details-full-width-item">
            <div class="details-full-width-top">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#walking"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Sidewalk Riding</span>
                    <span class="item-value" data-field="sidewalkRiding"><?php echo $format_field( $state_data['sidewalkRiding'] ?? null ); ?></span>
                </div>
            </div>
            <div class="item-note" data-field="sidewalkNote"><?php echo esc_html( $state_data['sidewalkNotes'] ?? '' ); ?></div>
        </div>
        <div class="details-full-width-item">
            <div class="details-full-width-top">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#road"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Street Riding</span>
                    <span class="item-value" data-field="streetRiding"><?php echo $format_field( $state_data['streetRiding'] ?? null ); ?></span>
                </div>
            </div>
            <div class="item-note" data-field="streetNote"><?php echo esc_html( $state_data['streetNotes'] ?? '' ); ?></div>
        </div>
        <div class="details-full-width-item">
            <div class="details-full-width-top">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#memo"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Notes</span>
                </div>
            </div>
            <span class="item-value" data-field="generalNotes"><?php echo esc_html( $state_data['generalNotes'] ?? '' ); ?></span>
        </div>
    </div>

    <!-- Footer -->
    <div class="details-footer">
        <div class="source-link">
            <div class="item-content">
                <span data-field="sourceLink">
                    <?php if ( ! empty( $state_data['sourceLink'] ) ) : ?>
                        <a href="<?php echo esc_url( $state_data['sourceLink'] ); ?>" target="_blank" rel="noopener noreferrer">State Statute Link <svg style="width:1em;height:1em;vertical-align:-0.1em;margin-left:2px" aria-hidden="true"><use xlink:href="#chevron"></use></svg></a>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <div class="verification-date">
            <span class="item-label">Last Verified:</span>
            <span data-field="lastVerified"><?php echo esc_html( $state_data['lastVerified'] ?? 'N/A' ); ?></span>
        </div>
    </div>
</section>
