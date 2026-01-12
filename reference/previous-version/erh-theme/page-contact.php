<?php
/**
 * Template Name: Contact
 *
 * Contact page with form and sidebar info.
 *
 * @package ERideHero
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Get ACF fields for contact info (fallback to defaults)
$contact_email     = get_field( 'contact_email', 'option' ) ?: 'contact@eridehero.com';
$press_email       = get_field( 'press_email', 'option' ) ?: 'press@eridehero.com';
$partnerships_email = get_field( 'partnerships_email', 'option' ) ?: 'partnerships@eridehero.com';
$editorial_email   = get_field( 'editorial_email', 'option' ) ?: 'editor@eridehero.com';
$webmaster_email   = get_field( 'webmaster_email', 'option' ) ?: 'webmaster@eridehero.com';

$business_address  = get_field( 'business_address', 'option' ) ?: "Doktorens Gyde 2, 1. 1\n9000 Aalborg\nDenmark";
?>

    <main id="main-content" class="contact-page">
        <div class="container">
            <div class="contact-layout">
                <!-- Left: Form -->
                <div class="contact-form-wrapper">
                    <div class="contact-header">
                        <h1 class="contact-title"><?php esc_html_e( 'Get in touch', 'erh' ); ?></h1>
                        <p class="contact-subtitle"><?php esc_html_e( "Have a question, feedback, or business inquiry? We'd love to hear from you.", 'erh' ); ?></p>
                    </div>

                    <form class="contact-form" id="erh-contact-form" novalidate>
                        <?php wp_nonce_field( 'erh_contact_form', 'erh_contact_nonce' ); ?>

                        <!-- Honeypot field (hidden, bots will fill this) -->
                        <div class="form-group" style="position: absolute; left: -9999px;" aria-hidden="true">
                            <label for="contact-website">Website</label>
                            <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact-name" class="form-label"><?php esc_html_e( 'Name', 'erh' ); ?></label>
                                <input type="text" id="contact-name" name="name" class="form-input" placeholder="<?php esc_attr_e( 'Your name', 'erh' ); ?>" required>
                                <span class="form-error" data-error="name"></span>
                            </div>

                            <div class="form-group">
                                <label for="contact-email" class="form-label"><?php esc_html_e( 'Email', 'erh' ); ?></label>
                                <input type="email" id="contact-email" name="email" class="form-input" placeholder="<?php esc_attr_e( 'you@example.com', 'erh' ); ?>" required>
                                <span class="form-error" data-error="email"></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="contact-topic" class="form-label"><?php esc_html_e( 'Topic', 'erh' ); ?></label>
                            <select id="contact-topic" name="topic" class="form-select" data-custom-select data-placeholder="<?php esc_attr_e( 'Select a topic', 'erh' ); ?>" required>
                                <option value="" disabled selected><?php esc_html_e( 'Select a topic', 'erh' ); ?></option>
                                <option value="general"><?php esc_html_e( 'General inquiry', 'erh' ); ?></option>
                                <option value="press"><?php esc_html_e( 'Press & media', 'erh' ); ?></option>
                                <option value="partnerships"><?php esc_html_e( 'Advertising & partnerships', 'erh' ); ?></option>
                                <option value="editorial"><?php esc_html_e( 'Editorial & content', 'erh' ); ?></option>
                                <option value="product"><?php esc_html_e( 'Submit a product for review', 'erh' ); ?></option>
                                <option value="website"><?php esc_html_e( 'Website issue or feedback', 'erh' ); ?></option>
                                <option value="other"><?php esc_html_e( 'Other', 'erh' ); ?></option>
                            </select>
                            <span class="form-error" data-error="topic"></span>
                        </div>

                        <div class="form-group">
                            <label for="contact-message" class="form-label"><?php esc_html_e( 'Message', 'erh' ); ?></label>
                            <textarea id="contact-message" name="message" class="form-textarea" placeholder="<?php esc_attr_e( 'How can we help?', 'erh' ); ?>" rows="6" required></textarea>
                            <span class="form-error" data-error="message"></span>
                        </div>

                        <button type="submit" class="btn btn-primary" id="contact-submit">
                            <span class="btn-text"><?php esc_html_e( 'Send message', 'erh' ); ?></span>
                            <span class="btn-loading" hidden>
                                <svg class="spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"/>
                                    <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"/>
                                </svg>
                                <?php esc_html_e( 'Sending...', 'erh' ); ?>
                            </span>
                        </button>

                        <!-- Success message -->
                        <div class="form-success" id="contact-success" hidden>
                            <?php erh_the_icon( 'check', 'icon' ); ?>
                            <p><?php esc_html_e( 'Thank you! Your message has been sent successfully. We\'ll get back to you soon.', 'erh' ); ?></p>
                        </div>

                        <!-- Error message -->
                        <div class="form-error-global" id="contact-error" hidden>
                            <p></p>
                        </div>
                    </form>
                </div>

                <!-- Right: Info -->
                <aside class="contact-sidebar">
                    <!-- Direct Email Lines -->
                    <div class="contact-card">
                        <h3 class="contact-card-title"><?php esc_html_e( 'Direct email lines', 'erh' ); ?></h3>
                        <ul class="contact-email-list">
                            <li>
                                <span class="contact-email-label"><?php esc_html_e( 'General inquiries', 'erh' ); ?></span>
                                <a href="mailto:<?php echo esc_attr( $contact_email ); ?>"><?php echo esc_html( $contact_email ); ?></a>
                            </li>
                            <li>
                                <span class="contact-email-label"><?php esc_html_e( 'Press & media', 'erh' ); ?></span>
                                <a href="mailto:<?php echo esc_attr( $press_email ); ?>"><?php echo esc_html( $press_email ); ?></a>
                            </li>
                            <li>
                                <span class="contact-email-label"><?php esc_html_e( 'Partnerships', 'erh' ); ?></span>
                                <a href="mailto:<?php echo esc_attr( $partnerships_email ); ?>"><?php echo esc_html( $partnerships_email ); ?></a>
                            </li>
                            <li>
                                <span class="contact-email-label"><?php esc_html_e( 'Editorial', 'erh' ); ?></span>
                                <a href="mailto:<?php echo esc_attr( $editorial_email ); ?>"><?php echo esc_html( $editorial_email ); ?></a>
                            </li>
                            <li>
                                <span class="contact-email-label"><?php esc_html_e( 'Website', 'erh' ); ?></span>
                                <a href="mailto:<?php echo esc_attr( $webmaster_email ); ?>"><?php echo esc_html( $webmaster_email ); ?></a>
                            </li>
                        </ul>
                    </div>

                    <!-- Address -->
                    <div class="contact-card">
                        <h3 class="contact-card-title"><?php esc_html_e( 'Business address', 'erh' ); ?></h3>
                        <address class="contact-address">
                            <?php echo nl2br( esc_html( $business_address ) ); ?>
                        </address>
                    </div>

                    <!-- Social Links -->
                    <div class="contact-card">
                        <h3 class="contact-card-title"><?php esc_html_e( 'Follow us', 'erh' ); ?></h3>
                        <div class="footer-socials">
                            <?php
                            $youtube_url   = get_field( 'youtube_url', 'option' );
                            $instagram_url = get_field( 'instagram_url', 'option' );
                            $tiktok_url    = get_field( 'tiktok_url', 'option' );
                            $facebook_url  = get_field( 'facebook_url', 'option' );
                            $twitter_url   = get_field( 'twitter_url', 'option' );
                            $linkedin_url  = get_field( 'linkedin_url', 'option' );
                            ?>
                            <?php if ( $youtube_url ) : ?>
                                <a href="<?php echo esc_url( $youtube_url ); ?>" class="footer-social" aria-label="YouTube" target="_blank" rel="noopener">
                                    <?php erh_the_icon( 'youtube', 'icon' ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( $instagram_url ) : ?>
                                <a href="<?php echo esc_url( $instagram_url ); ?>" class="footer-social" aria-label="Instagram" target="_blank" rel="noopener">
                                    <?php erh_the_icon( 'instagram', 'icon' ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( $tiktok_url ) : ?>
                                <a href="<?php echo esc_url( $tiktok_url ); ?>" class="footer-social" aria-label="TikTok" target="_blank" rel="noopener">
                                    <?php erh_the_icon( 'tiktok', 'icon' ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( $facebook_url ) : ?>
                                <a href="<?php echo esc_url( $facebook_url ); ?>" class="footer-social" aria-label="Facebook" target="_blank" rel="noopener">
                                    <?php erh_the_icon( 'facebook', 'icon' ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( $twitter_url ) : ?>
                                <a href="<?php echo esc_url( $twitter_url ); ?>" class="footer-social" aria-label="X (Twitter)" target="_blank" rel="noopener">
                                    <?php erh_the_icon( 'twitter', 'icon' ); ?>
                                </a>
                            <?php endif; ?>
                            <?php if ( $linkedin_url ) : ?>
                                <a href="<?php echo esc_url( $linkedin_url ); ?>" class="footer-social" aria-label="LinkedIn" target="_blank" rel="noopener">
                                    <?php erh_the_icon( 'linkedin', 'icon' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </main>

<?php
get_footer();
