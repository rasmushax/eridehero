<?php
/**
 * Laws Map - Shared Template Body
 *
 * Renders the interactive US map, search, legend, stats, state details,
 * and floating buttons. Vehicle-type agnostic — driven by $laws_config.
 *
 * @package ERideHero
 *
 * @param array $args {
 *     @type array  $laws_config Vehicle-type configuration.
 *     @type array  $laws_data   Pre-loaded and sorted state data from JSON.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$laws_config = $args['laws_config'] ?? [];
$laws_data   = $args['laws_data'] ?? [];

if ( empty( $laws_config ) || empty( $laws_data ) ) {
    return;
}

$tool_slug = get_post_field( 'post_name', get_the_ID() );

// Build classification counts and find latest verified date.
$classification_counts = [];
foreach ( array_keys( $laws_config['classifications'] ) as $cls_key ) {
    $classification_counts[ $cls_key ] = 0;
}
$latest_verified = '';

foreach ( $laws_data as $sdata ) {
    $cls = $sdata['classification'] ?? '';
    if ( isset( $classification_counts[ $cls ] ) ) {
        $classification_counts[ $cls ]++;
    }
    if ( ! empty( $sdata['lastVerified'] ) && $sdata['lastVerified'] > $latest_verified ) {
        $latest_verified = $sdata['lastVerified'];
    }
}
?>

<!-- Calculator container - triggers JS dispatcher -->
<div class="laws-map" data-calculator="<?php echo esc_attr( $tool_slug ); ?>">

    <!-- Search -->
    <div class="search-container">
        <?php erh_the_icon( 'search' ); ?>
        <input type="text" id="state-search" placeholder="Search by state name or abbreviation..." autocomplete="off" aria-label="Search states">
        <div id="suggestions-box"></div>
    </div>
    <?php if ( ! empty( $latest_verified ) ) : ?>
        <p class="map-last-updated">Data last verified: <?php echo esc_html( date( 'F j, Y', strtotime( $latest_verified ) ) ); ?></p>
    <?php endif; ?>

    <!-- Map + Info Box -->
    <div id="map-container">
        <div class="map-legend">
            <?php foreach ( $laws_config['legend'] as $legend_item ) : ?>
                <div class="legend-item <?php echo esc_attr( $legend_item['css_class'] ); ?>">
                    <span class="legend-color"></span>
                    <span class="legend-label"><?php echo esc_html( $legend_item['label'] ); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php
        // Server-side state colors — prevents grey flash before JS runs.
        $color_map = $laws_config['color_map'];
        $groups    = [];
        foreach ( $laws_data as $sid => $sdata ) {
            $c = $color_map[ $sdata['classification'] ] ?? null;
            if ( $c ) {
                $groups[ $c ][] = $sid;
            }
        }
        if ( $groups ) :
        ?>
        <style>
            <?php foreach ( $groups as $color => $ids ) :
                $selectors = array_map(
                    fn( $id ) => $id === 'DC'
                        ? "#us-map [id=\"DC\"] path, #us-map [id=\"DC\"] circle"
                        : "#us-map [id=\"{$id}\"]",
                    $ids
                );
                echo implode( ",\n", $selectors ) . " { fill: {$color}; }\n";
            endforeach; ?>
        </style>
        <?php endif; ?>

        <?php get_template_part( 'template-parts/tools/laws-map-svg' ); ?>

        <p class="map-hint" id="map-hint"><?php echo esc_html( $laws_config['map_hint'] ); ?></p>

        <div id="info-box"></div>
    </div>

    <!-- Stats Summary -->
    <div class="map-stats">
        <?php foreach ( $laws_config['stats'] as $stat ) :
            $count = 0;
            foreach ( $stat['keys'] as $key ) {
                $count += $classification_counts[ $key ] ?? 0;
            }
        ?>
            <div class="stat-item <?php echo esc_attr( $stat['css_class'] ); ?>">
                <span class="stat-count"><?php echo (int) $count; ?></span>
                <span class="stat-label"><?php echo esc_html( $stat['label'] ); ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- State Details -->
    <div id="details-container">
        <div class="details-content-column">
            <?php foreach ( $laws_data as $state_id => $state_data ) : ?>
                <?php
                get_template_part( $laws_config['state_template'], null, [
                    'state_id'    => $state_id,
                    'state_data'  => $state_data,
                    'laws_config' => $laws_config,
                ] );
                ?>
            <?php endforeach; ?>
        </div>

        <aside class="details-toc-column">
            <h3>State Details Index</h3>
            <ul id="desktop-toc-list">
                <?php foreach ( $laws_data as $sid => $sdata ) : ?>
                    <li><a href="#<?php echo esc_attr( $sid ); ?>-details" class="toc-link" data-state="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $sdata['name'] ); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </aside>
    </div>

    <!-- Floating Buttons -->
    <button id="back-to-top-btn" class="floaty-btn" title="Go to top" aria-label="Go to top">
        <svg><use xlink:href="#chevron"></use></svg>
    </button>
    <button id="toc-mobile-btn" class="floaty-btn" title="Open State Details Index" aria-label="Open State Details Index">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"></path></svg>
    </button>

    <!-- Mobile ToC Popup -->
    <div id="mobile-toc-popup" role="dialog" aria-modal="true" aria-labelledby="mobile-toc-title">
        <div class="mobile-toc-header">
            <h3 id="mobile-toc-title">State Details Index</h3>
            <button class="close-mobile-toc" aria-label="Close index">&times;</button>
        </div>
        <ul id="mobile-toc-list">
            <?php foreach ( $laws_data as $sid => $sdata ) : ?>
                <li><a href="#<?php echo esc_attr( $sid ); ?>-details" class="toc-link" data-state="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $sdata['name'] ); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php get_template_part( 'template-parts/tools/laws-map-icons' ); ?>
</div>
