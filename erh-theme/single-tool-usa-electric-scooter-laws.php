<?php
/**
 * Single Tool Template - E-Scooter Laws Map
 *
 * Custom full-width template for the interactive US e-scooter laws map.
 * WordPress auto-selects this over single-tool.php for slug "escooter-laws-map".
 *
 * @package ERideHero
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$post_id   = get_the_ID();
$tool_slug = get_post_field( 'post_name', $post_id );

// Load and sort laws data
$json_path = get_theme_file_path( 'assets/data/laws.json' );
$laws_data = [];

if ( file_exists( $json_path ) ) {
    $json_contents = file_get_contents( $json_path );
    $laws_data     = json_decode( $json_contents, true ) ?: [];
    uasort( $laws_data, fn( $a, $b ) => strcmp( $a['name'] ?? '', $b['name'] ?? '' ) );

    // Compute stats for summary
    $classification_counts = [
        'specific_escooter' => 0,
        'local_rule'        => 0,
        'unclear_or_local'  => 0,
        'prohibited'        => 0,
    ];
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
}
?>

<main id="main-content" class="tool-page">
    <div class="tool-layout">
        <div class="container">
            <!-- Title Row -->
            <div class="tool-title-row">
                <div class="tool-title-content">
                    <?php
                    erh_breadcrumb( [
                        [ 'label' => 'Tools', 'url' => get_post_type_archive_link( 'tool' ) ],
                        [ 'label' => get_the_title() ],
                    ] );
                    ?>
                    <h1 class="tool-title"><?php the_title(); ?></h1>
                    <?php if ( has_excerpt() ) : ?>
                        <p class="tool-description"><?php echo esc_html( get_the_excerpt() ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Calculator container - triggers JS dispatcher -->
            <div class="laws-map" data-calculator="<?php echo esc_attr( $tool_slug ); ?>">

                <!-- Search -->
                <div class="search-container">
                    <input type="text" id="state-search" placeholder="Enter state name or abbreviation..." autocomplete="off">
                    <div id="suggestions-box"></div>
                </div>

                <!-- Map + Info Box -->
                <div id="map-container">
                    <div class="map-legend">
                        <div class="legend-item state-legal">
                            <span class="legend-color"></span>
                            <span class="legend-label">Legal</span>
                        </div>
                        <div class="legend-item state-conditional">
                            <span class="legend-color"></span>
                            <span class="legend-label">Varies by location</span>
                        </div>
                        <div class="legend-item state-prohibited">
                            <span class="legend-color"></span>
                            <span class="legend-label">Prohibited</span>
                        </div>
                    </div>

                    <?php
                    // Server-side state colors — prevents grey flash before JS runs.
                    $color_map = [
                        'specific_escooter' => '#a3d9a3',
                        'local_rule'        => '#ffe0b3',
                        'unclear_or_local'  => '#ffe0b3',
                        'prohibited'        => '#ff9999',
                    ];
                    $groups = [];
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

                    <p class="map-hint" id="map-hint">Click or tap a state to view its e-scooter laws</p>

                    <div id="info-box"></div>
                </div>

                <!-- Stats Summary -->
                <div class="map-stats">
                    <div class="stat-item stat-legal">
                        <span class="stat-count"><?php echo (int) $classification_counts['specific_escooter']; ?></span>
                        <span class="stat-label">States with E-Scooter Laws</span>
                    </div>
                    <div class="stat-item stat-conditional">
                        <span class="stat-count"><?php echo (int) ( $classification_counts['local_rule'] + $classification_counts['unclear_or_local'] ); ?></span>
                        <span class="stat-label">Varies / Unclear</span>
                    </div>
                    <div class="stat-item stat-prohibited">
                        <span class="stat-count"><?php echo (int) $classification_counts['prohibited']; ?></span>
                        <span class="stat-label">Prohibited</span>
                    </div>
                    <div class="stat-item stat-total">
                        <span class="stat-count"><?php echo count( $laws_data ); ?></span>
                        <span class="stat-label">States + DC</span>
                    </div>
                </div>
                <?php if ( $latest_verified ) : ?>
                    <p class="map-last-updated">Data last verified: <?php echo esc_html( date( 'F j, Y', strtotime( $latest_verified ) ) ); ?></p>
                <?php endif; ?>

                <!-- State Details -->
                <div id="details-container">
                    <div class="details-content-column">
                        <?php foreach ( $laws_data as $state_id => $state_data ) : ?>
                            <?php
                            get_template_part( 'template-parts/tools/laws-map-state', null, [
                                'state_id'   => $state_id,
                                'state_data' => $state_data,
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

                <?php the_content(); ?>
            </div>
        </div>
    </div>
</main>

<?php
get_footer();
?>
<script>
window.erhData = window.erhData || {};
window.erhData.toolSlug = <?php echo wp_json_encode( $tool_slug ); ?>;
window.erhData.lawsData = <?php echo wp_json_encode( $laws_data ); ?>;
</script>
