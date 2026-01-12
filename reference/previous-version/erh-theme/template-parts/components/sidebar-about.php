<?php
/**
 * Sidebar - About ERideHero Card
 *
 * Displays the About card with author info in horizontal layout.
 * Content is managed via Theme Settings > Homepage.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get ACF options with defaults
$author_photo = get_field( 'about_author_photo', 'option' );
$author_name  = get_field( 'about_author_name', 'option' ) ?: __( 'Rasmus Barslund', 'erh' );
$author_role  = get_field( 'about_author_role', 'option' ) ?: __( 'Founder & Lead Reviewer', 'erh' );
$title        = get_field( 'about_title', 'option' ) ?: __( 'About ERideHero', 'erh' );
$text         = get_field( 'about_text', 'option' ) ?: __( 'The independent, data-driven guide to electric rides. Reviews, guides, and tools built on 120+ hands-on tests to help you ride smarter.', 'erh' );
$link_text    = get_field( 'about_link_text', 'option' ) ?: __( 'Learn more about us', 'erh' );
$link_page    = get_field( 'about_link_page', 'option' );
$link_url     = $link_page ? get_permalink( $link_page ) : home_url( '/about/' );
?>

<aside class="sidebar-card sidebar-card-horizontal">
    <div class="sidebar-card-author">
        <?php if ( $author_photo ) :
            // Handle both array (image object) and string (URL) return formats
            $photo_url = is_array( $author_photo ) ? ( $author_photo['sizes']['thumbnail'] ?? $author_photo['url'] ) : $author_photo;
        ?>
            <img src="<?php echo esc_url( $photo_url ); ?>"
                 alt="<?php echo esc_attr( $author_name ); ?>"
                 class="sidebar-card-avatar">
        <?php endif; ?>
        <div>
            <span class="sidebar-card-name"><?php echo esc_html( $author_name ); ?></span>
            <span class="sidebar-card-role"><?php echo esc_html( $author_role ); ?></span>
        </div>
    </div>
    <div class="sidebar-card-content">
        <h3 class="sidebar-card-title"><?php echo esc_html( $title ); ?></h3>
        <p class="sidebar-card-text"><?php echo esc_html( $text ); ?></p>
        <a href="<?php echo esc_url( $link_url ); ?>" class="sidebar-card-link">
            <?php echo esc_html( $link_text ); ?>
            <?php erh_the_icon( 'arrow-right' ); ?>
        </a>
    </div>
</aside>
