<?php
/**
 * CTA Section - Sign Up Prompt
 *
 * Reusable call-to-action section for user signup.
 *
 * @package ERideHero
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get member count (could be dynamic in future).
$member_count = '1,200+';
?>
<section class="cta-section">
	<div class="container">
		<div class="cta-card">
			<div class="cta-orb" aria-hidden="true"></div>
			<div class="cta-content">
				<div class="cta-text">
					<div class="cta-header">
						<h2><?php esc_html_e( 'Unlock all ERideHero features for free', 'erh' ); ?></h2>
						<span class="cta-pill"><?php echo esc_html( $member_count ); ?> <?php esc_html_e( 'members', 'erh' ); ?></span>
					</div>
					<ul class="cta-benefits">
						<li>
							<?php erh_the_icon( 'check' ); ?>
							<?php esc_html_e( 'Best deals weekly', 'erh' ); ?>
						</li>
						<li>
							<?php erh_the_icon( 'check' ); ?>
							<?php esc_html_e( 'Price drop alerts', 'erh' ); ?>
						</li>
						<li>
							<?php erh_the_icon( 'check' ); ?>
							<?php esc_html_e( 'Member-only discounts', 'erh' ); ?>
						</li>
					</ul>
				</div>
				<div class="cta-action">
					<a href="<?php echo esc_url( home_url( '/signup/' ) ); ?>" class="btn btn-primary btn-lg">
						<?php esc_html_e( 'Sign up free', 'erh' ); ?>
						<?php erh_the_icon( 'arrow-right' ); ?>
					</a>
				</div>
			</div>
		</div>
	</div>
</section>
