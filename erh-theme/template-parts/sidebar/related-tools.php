<?php
/**
 * Related Tools Sidebar Component
 *
 * Shows links to other calculator tools.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'current_id' => int - Current tool post ID (to exclude from list)
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_id = $args['current_id'] ?? 0;

// Get all other tools
$tools = get_posts( [
    'post_type'      => 'tool',
    'post_status'    => 'publish',
    'posts_per_page' => 5,
    'exclude'        => [ $current_id ],
    'orderby'        => 'title',
    'order'          => 'ASC',
] );

// Bail if no other tools
if ( empty( $tools ) ) {
    return;
}
?>

<div class="related-tools">
    <h4 class="related-tools-title"><?php esc_html_e( 'More Tools', 'erh' ); ?></h4>
    <div class="related-tools-list">
        <?php foreach ( $tools as $tool ) : ?>
            <a href="<?php echo esc_url( get_permalink( $tool->ID ) ); ?>" class="related-tool-link">
                <?php erh_the_icon( 'calculator' ); ?>
                <span><?php echo esc_html( $tool->post_title ); ?></span>
            </a>
        <?php endforeach; ?>

        <a href="<?php echo esc_url( get_post_type_archive_link( 'tool' ) ); ?>" class="related-tool-link">
            <?php erh_the_icon( 'grid' ); ?>
            <span><?php esc_html_e( 'View All Tools', 'erh' ); ?></span>
        </a>
    </div>
</div>
