<?php
/**
 * Single Tool Template
 *
 * Template for displaying individual tool pages (calculators, etc.).
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$post_id   = get_the_ID();
$tool_slug = get_post_field( 'post_name', $post_id );

// Build TOC items from content headings
$toc_items = [];
$content   = get_the_content();

// Extract h2 headings for TOC
if ( preg_match_all( '/<h2[^>]*id=["\']([^"\']+)["\'][^>]*>([^<]+)<\/h2>/i', $content, $matches, PREG_SET_ORDER ) ) {
    foreach ( $matches as $match ) {
        $toc_items[] = [
            'id'    => $match[1],
            'label' => $match[2],
        ];
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

            <div class="tool-layout-grid">
                <!-- Left Column: Main Content -->
                <article class="tool-main">
                    <!-- Calculator Container -->
                    <div class="calculator" data-calculator="<?php echo esc_attr( $tool_slug ); ?>">
                        <?php the_content(); ?>
                    </div>
                </article>

                <!-- Sidebar -->
                <aside class="sidebar">
                    <?php
                    // Related tools
                    get_template_part( 'template-parts/sidebar/related-tools', null, [
                        'current_id' => $post_id,
                    ] );

                    // Table of contents (if there are headings)
                    if ( ! empty( $toc_items ) ) :
                    ?>
                        <hr>
                        <?php
                        get_template_part( 'template-parts/sidebar/toc', null, [
                            'items' => $toc_items,
                        ] );
                    endif;
                    ?>
                </aside>
            </div>
        </div>
    </div>
</main>

<?php
get_footer();
?>
<script data-no-optimize="1">
window.erhData = window.erhData || {};
window.erhData.toolSlug = <?php echo wp_json_encode( $tool_slug ); ?>;
</script>
