<?php
/**
 * Laws Map - E-Bike State Detail Card
 *
 * Renders one state's detail section for the e-bike laws map.
 * Handles both three-class system states and custom/general rule states.
 *
 * @package ERideHero
 *
 * @param array $args {
 *     @type string $state_id   Two-letter state code (e.g. 'CA').
 *     @type array  $state_data State law data from laws_ebikes.json.
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

$classification_map = [
    'three_class'        => [ 'label' => 'Three-Class System', 'icon' => 'check-circle',  'color' => 'icon-green' ],
    'bicycle_equivalent' => [ 'label' => 'Bicycle Equivalent', 'icon' => 'check-circle',  'color' => 'icon-green' ],
    'custom_definition'  => [ 'label' => 'Custom Definition',  'icon' => 'interrogation', 'color' => 'icon-orange' ],
    'no_specific_law'    => [ 'label' => 'No Specific Law',    'icon' => 'cross-circle',  'color' => 'icon-muted' ],
];

$positive_values = [ 'allowed', 'required', 'yes', 'all_ages', 'after_dark' ];
$negative_values = [ 'prohibited', 'not_allowed', 'none', 'not_required' ];

$status_icon = function ( string $icon, string $color_class ): string {
    return '<svg class="status-icon ' . esc_attr( $color_class ) . '" aria-hidden="true"><use xlink:href="#' . esc_attr( $icon ) . '"></use></svg>';
};

$format_classification = function ( ?string $value ) use ( $classification_map, $status_icon ): string {
    if ( $value === null || ! isset( $classification_map[ $value ] ) ) {
        return '<span class="laws-map-na">N/A</span>';
    }
    $c = $classification_map[ $value ];
    return $status_icon( $c['icon'], $c['color'] ) . ' ' . esc_html( $c['label'] );
};

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
    $label = esc_html( ucwords( str_replace( '_', ' ', $value ) ) );
    if ( in_array( $value, $positive_values, true ) ) {
        return $status_icon( 'check-circle', 'icon-green' ) . ' ' . $label;
    }
    if ( in_array( $value, $negative_values, true ) ) {
        return $status_icon( 'cross-circle', 'icon-red' ) . ' ' . $label;
    }
    return $status_icon( 'interrogation', 'icon-orange' ) . ' ' . $label;
};

$format_access = function ( ?string $value ) use ( $status_icon ): string {
    if ( $value === null ) {
        return '<span class="laws-map-na">N/A</span>';
    }
    $map = [
        'allowed'    => [ 'icon' => 'check-circle', 'color' => 'icon-green',  'label' => 'Allowed' ],
        'restricted' => [ 'icon' => 'interrogation', 'color' => 'icon-orange', 'label' => 'Restricted' ],
        'prohibited' => [ 'icon' => 'cross-circle',  'color' => 'icon-red',    'label' => 'Prohibited' ],
        'local_rule' => [ 'icon' => 'interrogation', 'color' => 'icon-orange', 'label' => 'Local Rule' ],
    ];
    $cfg = $map[ $value ] ?? [ 'icon' => 'interrogation', 'color' => 'icon-orange', 'label' => ucwords( str_replace( '_', ' ', $value ) ) ];
    return $status_icon( $cfg['icon'], $cfg['color'] ) . ' ' . esc_html( $cfg['label'] );
};

$has_classes = ! empty( $state_data['class1'] ) || ! empty( $state_data['class2'] ) || ! empty( $state_data['class3'] );
$has_general = ! empty( $state_data['general'] );
$classes     = [];
if ( $has_classes ) {
    foreach ( [ 'class1' => 'Class 1', 'class2' => 'Class 2', 'class3' => 'Class 3' ] as $key => $label ) {
        if ( ! empty( $state_data[ $key ] ) ) {
            $classes[ $key ] = [ 'label' => $label, 'data' => $state_data[ $key ] ];
        }
    }
}
?>
<section id="<?php echo esc_attr( $state_id ); ?>-details">
    <h2><?php echo $name; ?> E-Bike Law Details</h2>

    <!-- Basic data grid -->
    <div class="details-grid">
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#legal"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">State Classification</span>
                <span class="item-value"><?php echo $format_classification( $state_data['classification'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#tachometer-fast"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Max Motor Power</span>
                <span class="item-value"><?php echo $format_field( $state_data['maxPowerWatts'] ?? null, ' W' ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#idbadge"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">License Required</span>
                <span class="item-value"><?php echo $format_field( $state_data['licenseRequired'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#memo"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Registration Required</span>
                <span class="item-value"><?php echo $format_field( $state_data['registrationRequired'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#memo"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Insurance Required</span>
                <span class="item-value"><?php echo $format_field( $state_data['insuranceRequired'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#auction"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">DUI Laws Apply</span>
                <span class="item-value"><?php echo $format_field( $state_data['duiApplies'] ?? null ); ?></span>
            </div>
        </div>
        <div class="details-grid-item">
            <div class="item-icon">
                <svg class="info-icon"><use xlink:href="#brightness"></use></svg>
            </div>
            <div class="item-content">
                <span class="item-label">Lights Required</span>
                <span class="item-value"><?php echo $format_field( $state_data['lightsRequired'] ?? null ); ?></span>
            </div>
        </div>
    </div>

    <?php if ( $has_classes ) : ?>
    <!-- Class-by-class comparison table -->
    <div class="ebike-class-table-wrapper">
        <table class="ebike-class-table">
            <thead>
                <tr>
                    <th></th>
                    <?php foreach ( $classes as $cls ) : ?>
                        <th><?php echo esc_html( $cls['label'] ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="row-label">Max Speed</td>
                    <?php foreach ( $classes as $cls ) : ?>
                        <td><?php echo $cls['data']['maxSpeedMph'] !== null ? esc_html( $cls['data']['maxSpeedMph'] . ' MPH' ) : '<span class="laws-map-na">N/A</span>'; ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="row-label">Pedal Assist Only</td>
                    <?php foreach ( $classes as $cls ) : ?>
                        <td><?php echo $format_field( $cls['data']['pedalAssistOnly'] ?? null ); ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="row-label">Min Age</td>
                    <?php foreach ( $classes as $cls ) : ?>
                        <td><?php echo $cls['data']['minAge'] !== null ? esc_html( $cls['data']['minAge'] . ' yrs' ) : '<span class="laws-map-na">None</span>'; ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="row-label">Helmet Required</td>
                    <?php foreach ( $classes as $cls ) : ?>
                        <td><?php echo $format_field( $cls['data']['helmetRequired'] ?? null ); ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="row-label">Road Access</td>
                    <?php foreach ( $classes as $cls ) : ?>
                        <td><?php echo $format_access( $cls['data']['roadAccess'] ?? null ); ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="row-label">Bike Lane</td>
                    <?php foreach ( $classes as $cls ) : ?>
                        <td><?php echo $format_access( $cls['data']['bikeLaneAccess'] ?? null ); ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="row-label">Bike Path</td>
                    <?php foreach ( $classes as $cls ) : ?>
                        <td><?php echo $format_access( $cls['data']['bikePathAccess'] ?? null ); ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="row-label">Sidewalk</td>
                    <?php foreach ( $classes as $cls ) : ?>
                        <td><?php echo $format_access( $cls['data']['sidewalkAccess'] ?? null ); ?></td>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <td class="row-label">Trail Access</td>
                    <?php foreach ( $classes as $cls ) : ?>
                        <td><?php echo $format_access( $cls['data']['trailAccess'] ?? null ); ?></td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ( $has_general ) :
        $gen = $state_data['general'];
    ?>
    <!-- General rules (non-class-system states) -->
    <div class="ebike-general-rules">
        <h3>General Rules</h3>
        <div class="details-grid">
            <div class="details-grid-item">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#tachometer-fast"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Max Speed</span>
                    <span class="item-value"><?php echo $gen['maxSpeedMph'] !== null ? esc_html( $gen['maxSpeedMph'] . ' MPH' ) : '<span class="laws-map-na">N/A</span>'; ?></span>
                </div>
            </div>
            <div class="details-grid-item">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#age-alt"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Min Age</span>
                    <span class="item-value"><?php echo $format_field( $gen['minAge'] ?? null, ' years' ); ?></span>
                </div>
            </div>
            <div class="details-grid-item">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#motorcycle-helmet"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Helmet Required</span>
                    <span class="item-value"><?php echo $format_field( $gen['helmetRequired'] ?? null ); ?></span>
                </div>
            </div>
            <div class="details-grid-item">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#road"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Road Access</span>
                    <span class="item-value"><?php echo $format_access( $gen['roadAccess'] ?? null ); ?></span>
                </div>
            </div>
            <div class="details-grid-item">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#biking-mountain"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Bike Lane</span>
                    <span class="item-value"><?php echo $format_access( $gen['bikeLaneAccess'] ?? null ); ?></span>
                </div>
            </div>
            <div class="details-grid-item">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#biking-mountain"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Bike Path</span>
                    <span class="item-value"><?php echo $format_access( $gen['bikePathAccess'] ?? null ); ?></span>
                </div>
            </div>
            <div class="details-grid-item">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#walking"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Sidewalk</span>
                    <span class="item-value"><?php echo $format_access( $gen['sidewalkAccess'] ?? null ); ?></span>
                </div>
            </div>
            <div class="details-grid-item">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#biking-mountain"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Trail Access</span>
                    <span class="item-value"><?php echo $format_access( $gen['trailAccess'] ?? null ); ?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Notes section -->
    <div class="notes-section">
        <?php if ( ! empty( $state_data['helmetNotes'] ) ) : ?>
        <div class="details-full-width-item">
            <div class="details-full-width-top">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#motorcycle-helmet"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Helmet Details</span>
                </div>
            </div>
            <div class="item-note"><?php echo esc_html( $state_data['helmetNotes'] ); ?></div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $state_data['accessNotes'] ) ) : ?>
        <div class="details-full-width-item">
            <div class="details-full-width-top">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#road"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Access Details</span>
                </div>
            </div>
            <div class="item-note"><?php echo esc_html( $state_data['accessNotes'] ); ?></div>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $state_data['generalNotes'] ) ) : ?>
        <div class="details-full-width-item">
            <div class="details-full-width-top">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#memo"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Notes</span>
                </div>
            </div>
            <span class="item-value" data-field="generalNotes"><?php echo esc_html( $state_data['generalNotes'] ); ?></span>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $state_data['recentChanges'] ) ) : ?>
        <div class="details-full-width-item">
            <div class="details-full-width-top">
                <div class="item-icon">
                    <svg class="info-icon"><use xlink:href="#legal"></use></svg>
                </div>
                <div class="item-content">
                    <span class="item-label">Recent Changes</span>
                </div>
            </div>
            <div class="item-note"><?php echo esc_html( $state_data['recentChanges'] ); ?></div>
        </div>
        <?php endif; ?>
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
