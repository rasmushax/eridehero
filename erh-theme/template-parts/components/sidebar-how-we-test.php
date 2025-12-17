<?php
/**
 * Sidebar - How We Test Card
 *
 * Displays the "How We Test" sidebar card with image, stats, and link.
 * Content is managed via Theme Settings > Homepage.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get ACF options with defaults
$image     = get_field( 'how_we_test_image', 'option' );
$title     = get_field( 'how_we_test_title', 'option' ) ?: __( 'How we test', 'erh' );
$text      = get_field( 'how_we_test_text', 'option' ) ?: __( 'We measure real-world range, top speed, acceleration, and hill climbing. 30+ data-driven tests on every vehicle.', 'erh' );
$stats     = get_field( 'how_we_test_stats', 'option' );
$link_text = get_field( 'how_we_test_link_text', 'option' ) ?: __( 'Learn about our process', 'erh' );
$link_url  = get_field( 'how_we_test_link_url', 'option' ) ?: home_url( '/how-we-test/' );

// Default stats if none configured
if ( empty( $stats ) ) {
    $stats = array(
        array( 'value' => '120+', 'label' => 'rides tested' ),
        array( 'value' => '12,000+', 'label' => 'miles ridden' ),
        array( 'value' => '2019', 'label' => 'founded' ),
    );
}
?>

<aside class="sidebar-card sidebar-card-with-image">
    <?php if ( $image ) :
        // Handle both array (image object) and string (URL) return formats
        $image_url = is_array( $image ) ? ( $image['sizes']['medium_large'] ?? $image['url'] ) : $image;
        $image_alt = is_array( $image ) ? ( $image['alt'] ?: $title ) : $title;
    ?>
        <img src="<?php echo esc_url( $image_url ); ?>"
             alt="<?php echo esc_attr( $image_alt ); ?>"
             class="sidebar-card-image">
    <?php endif; ?>

    <h3 class="sidebar-card-title"><?php echo esc_html( $title ); ?></h3>

    <p class="sidebar-card-text"><?php echo esc_html( $text ); ?></p>

    <?php if ( ! empty( $stats ) ) : ?>
        <div class="sidebar-card-stats-stacked">
            <?php foreach ( $stats as $stat ) : ?>
                <div class="stat-item">
                    <span class="stat-value"><?php echo esc_html( $stat['value'] ); ?></span>
                    <span class="stat-label"><?php echo esc_html( $stat['label'] ); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <a href="<?php echo esc_url( $link_url ); ?>" class="sidebar-card-link">
        <?php echo esc_html( $link_text ); ?>
        <?php erh_the_icon( 'arrow-right' ); ?>
    </a>
</aside>
