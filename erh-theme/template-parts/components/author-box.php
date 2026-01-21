<?php
/**
 * Author Box Component
 *
 * Displays author information with avatar, bio, role, and social links.
 *
 * @package ERideHero
 *
 * @var array $args {
 *     @type int $post_id The post ID to get author from.
 * }
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_id = $args['post_id'] ?? get_the_ID();

// Get the post author.
$author_id = get_post_field( 'post_author', $post_id );

if ( ! $author_id ) {
    return;
}

// Get author data.
$author_name  = get_the_author_meta( 'display_name', $author_id );
$author_bio   = get_the_author_meta( 'description', $author_id );
$author_url   = get_author_posts_url( $author_id );
$author_email = get_the_author_meta( 'user_email', $author_id );

// Get author role from ACF field (user_title).
$author_role = get_field( 'user_title', 'user_' . $author_id );

// Get social links from Rank Math.
$socials     = erh_get_author_socials( $author_id );
$has_socials = erh_author_has_socials( $author_id ) || $author_email;

// Get avatar - prefer ACF profile_image, fallback to Gravatar.
$profile_image = get_field( 'profile_image', 'user_' . $author_id );
if ( $profile_image && ! empty( $profile_image['url'] ) ) {
    $avatar = sprintf(
        '<img src="%s" alt="%s" class="author-box-avatar" width="80" height="80">',
        esc_url( $profile_image['sizes']['thumbnail'] ?? $profile_image['url'] ),
        esc_attr( $author_name )
    );
} else {
    $avatar = get_avatar( $author_id, 160, '', $author_name, array( 'class' => 'author-box-avatar' ) );
}
?>

<section class="author-box">
    <?php echo $avatar; ?>
    <div class="author-box-content">
        <div class="author-box-header">
            <div class="author-box-info">
                <a href="<?php echo esc_url( $author_url ); ?>" class="author-box-name"><?php echo esc_html( $author_name ); ?></a>
                <?php if ( $author_role ) : ?>
                    <span class="author-box-role"><?php echo esc_html( $author_role ); ?></span>
                <?php endif; ?>
            </div>
            <?php if ( $has_socials ) : ?>
                <div class="author-box-socials">
                    <?php if ( $socials['linkedin'] ) : ?>
                        <a href="<?php echo esc_url( $socials['linkedin'] ); ?>" class="author-box-social" aria-label="LinkedIn" target="_blank" rel="noopener">
                            <svg class="icon" aria-hidden="true"><use href="#icon-linkedin"></use></svg>
                        </a>
                    <?php endif; ?>
                    <?php if ( $socials['facebook'] ) : ?>
                        <a href="<?php echo esc_url( $socials['facebook'] ); ?>" class="author-box-social" aria-label="Facebook" target="_blank" rel="noopener">
                            <svg class="icon" aria-hidden="true"><use href="#icon-facebook"></use></svg>
                        </a>
                    <?php endif; ?>
                    <?php if ( $socials['instagram'] ) : ?>
                        <a href="<?php echo esc_url( $socials['instagram'] ); ?>" class="author-box-social" aria-label="Instagram" target="_blank" rel="noopener">
                            <svg class="icon" aria-hidden="true"><use href="#icon-instagram"></use></svg>
                        </a>
                    <?php endif; ?>
                    <?php if ( $socials['twitter'] ) : ?>
                        <a href="<?php echo esc_url( $socials['twitter'] ); ?>" class="author-box-social" aria-label="X" target="_blank" rel="noopener">
                            <svg class="icon" aria-hidden="true"><use href="#icon-twitter"></use></svg>
                        </a>
                    <?php endif; ?>
                    <?php if ( $socials['youtube'] ) : ?>
                        <a href="<?php echo esc_url( $socials['youtube'] ); ?>" class="author-box-social" aria-label="YouTube" target="_blank" rel="noopener">
                            <svg class="icon" aria-hidden="true"><use href="#icon-youtube"></use></svg>
                        </a>
                    <?php endif; ?>
                    <?php if ( $author_email ) : ?>
                        <a href="mailto:<?php echo esc_attr( $author_email ); ?>" class="author-box-social" aria-label="Email">
                            <svg class="icon" aria-hidden="true"><use href="#icon-mail"></use></svg>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ( $author_bio ) : ?>
            <p class="author-box-bio"><?php echo esc_html( $author_bio ); ?></p>
        <?php endif; ?>
    </div>
</section>
