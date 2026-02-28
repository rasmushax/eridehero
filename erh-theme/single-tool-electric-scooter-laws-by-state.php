<?php
/**
 * Single Tool Template - E-Scooter Laws Map
 *
 * Custom full-width template for the interactive US e-scooter laws map.
 * WordPress auto-selects this over single-tool.php for the matching slug.
 *
 * @package ERideHero
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$laws_config = [
    'vehicle_type'    => 'escooter',
    'data_file'       => 'laws_escooters.json',
    'state_template'  => 'template-parts/tools/laws-map-state',
    'map_hint'        => 'Click or tap a state to view its e-scooter laws',
    'classifications' => [
        'specific_escooter' => [ 'label' => 'Legal',              'icon' => 'check-circle',  'color' => 'icon-green',  'css_class' => 'state-legal' ],
        'local_rule'        => [ 'label' => 'Varies by Location', 'icon' => 'interrogation', 'color' => 'icon-orange', 'css_class' => 'state-conditional' ],
        'unclear_or_local'  => [ 'label' => 'Unclear / Local',    'icon' => 'interrogation', 'color' => 'icon-orange', 'css_class' => 'state-conditional' ],
        'prohibited'        => [ 'label' => 'Prohibited',         'icon' => 'cross-circle',  'color' => 'icon-red',    'css_class' => 'state-prohibited' ],
    ],
    'legend' => [
        [ 'css_class' => 'state-legal',       'label' => 'Legal' ],
        [ 'css_class' => 'state-conditional',  'label' => 'Varies by location' ],
        [ 'css_class' => 'state-prohibited',   'label' => 'Prohibited' ],
    ],
    'stats' => [
        [ 'keys' => [ 'specific_escooter' ],               'css_class' => 'stat-legal',       'label' => 'States with E-Scooter Laws' ],
        [ 'keys' => [ 'local_rule', 'unclear_or_local' ],  'css_class' => 'stat-conditional', 'label' => 'Varies / Unclear' ],
        [ 'keys' => [ 'prohibited' ],                      'css_class' => 'stat-prohibited',  'label' => 'Prohibited' ],
    ],
    'color_map' => [
        'specific_escooter' => '#a3d9a3',
        'local_rule'        => '#ffe0b3',
        'unclear_or_local'  => '#ffe0b3',
        'prohibited'        => '#ff9999',
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
<script data-no-optimize="1">
window.erhData = window.erhData || {};
window.erhData.toolSlug = <?php echo wp_json_encode( get_post_field( 'post_name', get_the_ID() ) ); ?>;
window.erhData.lawsData = <?php echo wp_json_encode( $laws_data ); ?>;
</script>
