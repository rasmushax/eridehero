<?php
/**
 * Author Archive Header Template Part
 *
 * Displays author information for archive pages.
 * Larger variant of author-box.
 *
 * Expected args:
 * - author_id (int): The author user ID
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$author_id = $args['author_id'] ?? 0;

if ( ! $author_id ) {
	return;
}

// Get author data.
$author_name = get_the_author_meta( 'display_name', $author_id );
$author_bio  = get_the_author_meta( 'description', $author_id );
$author_email = get_the_author_meta( 'user_email', $author_id );

// Get author role from ACF field (user_title).
$author_role = get_field( 'user_title', 'user_' . $author_id );

// Get social links from Rank Math.
$socials     = erh_get_author_socials( $author_id );
$has_socials = erh_author_has_socials( $author_id ) || $author_email;

// Get avatar - prefer ACF profile_image, fallback to Gravatar.
$profile_image = get_field( 'profile_image', 'user_' . $author_id );
if ( $profile_image && ! empty( $profile_image['url'] ) ) {
	$avatar_url = $profile_image['sizes']['medium'] ?? $profile_image['url'];
} else {
	$avatar_url = get_avatar_url( $author_id, array( 'size' => 200 ) );
}
?>

<header class="author-header">
	<div class="author-header-avatar">
		<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $author_name ); ?>" width="120" height="120">
	</div>
	<div class="author-header-content">
		<div class="author-header-top">
			<div class="author-header-info">
				<h1 class="author-header-name"><?php echo esc_html( $author_name ); ?></h1>
				<?php if ( $author_role ) : ?>
					<span class="author-header-role"><?php echo esc_html( $author_role ); ?></span>
				<?php endif; ?>
			</div>
			<?php if ( $has_socials ) : ?>
				<div class="author-header-socials">
					<?php if ( $socials['linkedin'] ) : ?>
						<a href="<?php echo esc_url( $socials['linkedin'] ); ?>" class="author-header-social" aria-label="LinkedIn" target="_blank" rel="noopener">
							<?php erh_the_icon( 'linkedin' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $socials['facebook'] ) : ?>
						<a href="<?php echo esc_url( $socials['facebook'] ); ?>" class="author-header-social" aria-label="Facebook" target="_blank" rel="noopener">
							<?php erh_the_icon( 'facebook' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $socials['instagram'] ) : ?>
						<a href="<?php echo esc_url( $socials['instagram'] ); ?>" class="author-header-social" aria-label="Instagram" target="_blank" rel="noopener">
							<?php erh_the_icon( 'instagram' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $socials['twitter'] ) : ?>
						<a href="<?php echo esc_url( $socials['twitter'] ); ?>" class="author-header-social" aria-label="X" target="_blank" rel="noopener">
							<?php erh_the_icon( 'twitter' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $socials['youtube'] ) : ?>
						<a href="<?php echo esc_url( $socials['youtube'] ); ?>" class="author-header-social" aria-label="YouTube" target="_blank" rel="noopener">
							<?php erh_the_icon( 'youtube' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $author_email ) : ?>
						<a href="mailto:<?php echo esc_attr( $author_email ); ?>" class="author-header-social" aria-label="Email">
							<?php erh_the_icon( 'mail' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php if ( $author_bio ) : ?>
			<p class="author-header-bio"><?php echo esc_html( $author_bio ); ?></p>
		<?php endif; ?>
	</div>
</header>
