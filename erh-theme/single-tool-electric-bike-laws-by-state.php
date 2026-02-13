<?php
/**
 * Single Tool Template - E-Bike Laws Map
 *
 * Custom full-width template for the interactive US e-bike laws map.
 * WordPress auto-selects this over single-tool.php for the matching slug.
 *
 * @package ERideHero
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$laws_config = [
    'vehicle_type'    => 'ebike',
    'data_file'       => 'laws_ebikes.json',
    'state_template'  => 'template-parts/tools/laws-map-state-ebike',
    'map_hint'        => 'Click or tap a state to view its e-bike laws',
    'classifications' => [
        'three_class'        => [ 'label' => 'Three-Class System', 'icon' => 'check-circle',  'color' => 'icon-green',  'css_class' => 'state-legal' ],
        'bicycle_equivalent' => [ 'label' => 'Bicycle Equivalent', 'icon' => 'check-circle',  'color' => 'icon-green',  'css_class' => 'state-legal' ],
        'custom_definition'  => [ 'label' => 'Custom Definition',  'icon' => 'interrogation', 'color' => 'icon-orange', 'css_class' => 'state-conditional' ],
        'no_specific_law'    => [ 'label' => 'No Specific Law',    'icon' => 'cross-circle',  'color' => 'icon-muted',  'css_class' => 'state-no-law' ],
    ],
    'legend' => [
        [ 'css_class' => 'state-legal',       'label' => 'Legally defined' ],
        [ 'css_class' => 'state-conditional',  'label' => 'Custom rules' ],
        [ 'css_class' => 'state-no-law',       'label' => 'No specific law' ],
    ],
    'stats' => [
        [ 'keys' => [ 'three_class' ],                       'css_class' => 'stat-legal',       'label' => 'Three-Class System' ],
        [ 'keys' => [ 'custom_definition', 'bicycle_equivalent' ], 'css_class' => 'stat-conditional', 'label' => 'Custom / Other' ],
        [ 'keys' => [ 'no_specific_law' ],                   'css_class' => 'stat-no-law',      'label' => 'No Specific Law' ],
    ],
    'color_map' => [
        'three_class'        => '#a3d9a3',
        'bicycle_equivalent' => '#a3d9a3',
        'custom_definition'  => '#ffe0b3',
        'no_specific_law'    => '#D3D3D3',
    ],
];

// Load and sort laws data once — used for both SSR and JS injection.
$json_path = get_theme_file_path( 'assets/data/' . $laws_config['data_file'] );
$laws_data = [];
if ( file_exists( $json_path ) ) {
    $laws_data = json_decode( file_get_contents( $json_path ), true ) ?: [];
    uasort( $laws_data, fn( $a, $b ) => strcmp( $a['name'] ?? '', $b['name'] ?? '' ) );
}

get_header();
?>

<main id="main-content" class="tool-page">
    <div class="tool-layout">
        <div class="container">
            <div class="tool-title-row">
                <div class="tool-title-content">
                    <?php
                    erh_breadcrumb( [
                        [ 'label' => 'Tools', 'url' => get_post_type_archive_link( 'tool' ) ],
                        [ 'label' => get_the_title() ],
                    ] );
                    ?>
                    <h1 class="tool-title"><?php the_title(); ?></h1>
                    <div class="tool-description"><?php the_content(); ?></div>
                </div>
            </div>

            <?php
            get_template_part( 'template-parts/tools/laws-map-body', null, [
                'laws_config' => $laws_config,
                'laws_data'   => $laws_data,
            ] );
            ?>
        </div>
    </div>
</main>

<?php
get_footer();
?>
<script>
window.erhData = window.erhData || {};
window.erhData.toolSlug = <?php echo wp_json_encode( get_post_field( 'post_name', get_the_ID() ) ); ?>;
window.erhData.lawsData = <?php echo wp_json_encode( $laws_data ); ?>;
</script>
