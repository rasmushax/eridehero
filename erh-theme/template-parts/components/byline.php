<?php
/**
 * Byline Component
 *
 * Author attribution with avatar, name, role, date, and social sharing.
 * Reusable across review posts, articles, and other content types.
 *
 * @package ERideHero
 *
 * Expected $args:
 *   'post_id'     => int    - Post ID (optional, defaults to current post)
 *   'show_role'   => bool   - Whether to show author role (default: true)
 *   'show_share'  => bool   - Whether to show social share links (default: true)
 *   'date_prefix' => string - Prefix for date (default: 'Updated')
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get arguments with defaults
$post_id     = $args['post_id'] ?? get_the_ID();
$show_role   = $args['show_role'] ?? true;
$show_share  = $args['show_share'] ?? true;
$date_prefix = $args['date_prefix'] ?? 'Updated';

// Get author data
$author_id   = get_post_field( 'post_author', $post_id );
$author_name = get_the_author_meta( 'display_name', $author_id );
$author_url  = get_author_posts_url( $author_id );

// Get ACF user fields
$profile_image = get_field( 'profile_image', 'user_' . $author_id );
$user_title    = get_field( 'user_title', 'user_' . $author_id );

// Fallback to Gravatar if no profile image
if ( empty( $profile_image ) ) {
    $avatar_url = get_avatar_url( $author_id, array( 'size' => 80 ) );
} else {
    // Use erh-avatar size (80x80 for 40px display at 2x retina)
    $avatar_url = $profile_image['sizes']['erh-avatar'] ?? $profile_image['sizes']['thumbnail'] ?? $profile_image['url'];
}

// Get post date (modified date, or published if not modified)
$modified_date  = get_the_modified_date( 'M j, Y', $post_id );
$published_date = get_the_date( 'M j, Y', $post_id );
$display_date   = $modified_date ?: $published_date;

// Build share URLs
$post_url    = rawurlencode( get_permalink( $post_id ) );
$post_title  = rawurlencode( get_the_title( $post_id ) );

$share_urls = array(
    'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$post_url}",
    'twitter'  => "https://twitter.com/intent/tweet?url={$post_url}&text={$post_title}",
    'reddit'   => "https://www.reddit.com/submit?url={$post_url}&title={$post_title}",
);
?>

<div class="byline">
    <div class="byline-author">
        <img
            src="<?php echo esc_url( $avatar_url ); ?>"
            alt="<?php echo esc_attr( $author_name ); ?>"
            class="byline-avatar"
            width="40"
            height="40"
            loading="lazy"
        >
        <div class="byline-info">
            <a href="<?php echo esc_url( $author_url ); ?>" class="byline-name">
                <?php echo esc_html( $author_name ); ?>
            </a>
            <?php if ( $show_role && $user_title ) : ?>
                <span class="byline-role"><?php echo esc_html( $user_title ); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="byline-meta">
        <span class="byline-date">
            <?php echo esc_html( $date_prefix ); ?> <?php echo esc_html( $display_date ); ?>
        </span>

        <?php if ( $show_share ) : ?>
            <div class="byline-share">
                <a
                    href="<?php echo esc_url( $share_urls['facebook'] ); ?>"
                    class="byline-share-link"
                    aria-label="<?php esc_attr_e( 'Share on Facebook', 'erh' ); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <svg class="icon" aria-hidden="true"><use href="#icon-facebook"></use></svg>
                </a>
                <a
                    href="<?php echo esc_url( $share_urls['twitter'] ); ?>"
                    class="byline-share-link"
                    aria-label="<?php esc_attr_e( 'Share on X', 'erh' ); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <svg class="icon" aria-hidden="true"><use href="#icon-twitter"></use></svg>
                </a>
                <a
                    href="<?php echo esc_url( $share_urls['reddit'] ); ?>"
                    class="byline-share-link"
                    aria-label="<?php esc_attr_e( 'Share on Reddit', 'erh' ); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <svg class="icon" aria-hidden="true"><use href="#icon-reddit"></use></svg>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
