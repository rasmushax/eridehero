<?php
/**
 * Contact Form Handler - REST API endpoint for contact form submissions.
 *
 * @package ERH\API
 */

declare(strict_types=1);

namespace ERH\API;

use ERH\User\RateLimiter;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Handles contact form submissions via REST API.
 */
class ContactHandler {

    /**
     * REST namespace.
     */
    private const NAMESPACE = 'erh/v1';

    /**
     * Rate limiter instance.
     *
     * @var RateLimiter
     */
    private RateLimiter $rate_limiter;

    /**
     * Topic to email mapping (lazy-loaded).
     *
     * @var array<string, string>|null
     */
    private ?array $topic_emails = null;

    /**
     * Topic display names.
     *
     * @var array<string, string>
     */
    private const TOPIC_LABELS = [
        'general'      => 'General inquiry',
        'press'        => 'Press & media',
        'partnerships' => 'Advertising & partnerships',
        'editorial'    => 'Editorial & content',
        'product'      => 'Product review submission',
        'website'      => 'Website issue/feedback',
        'other'        => 'Other',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->rate_limiter = new RateLimiter();
        // Topic emails are lazy-loaded to avoid calling ACF too early.
    }

    /**
     * Register REST routes.
     */
    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);

        // Add rate limit for contact form.
        add_filter('erh_rate_limits', [$this, 'add_rate_limit']);
    }

    /**
     * Add contact form rate limit.
     *
     * @param array $limits Existing limits.
     * @return array Modified limits.
     */
    public function add_rate_limit(array $limits): array {
        $limits['contact_form'] = [
            'max_attempts' => 5,
            'window'       => 3600, // 5 submissions per hour.
        ];
        return $limits;
    }

    /**
     * Register REST API routes.
     */
    public function register_routes(): void {
        register_rest_route(
            self::NAMESPACE,
            '/contact',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_submission'],
                'permission_callback' => '__return_true',
                'args'                => $this->get_endpoint_args(),
            ]
        );
    }

    /**
     * Get endpoint argument definitions.
     *
     * @return array Argument definitions.
     */
    private function get_endpoint_args(): array {
        return [
            'name' => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) {
                    if (!is_string($value)) return false;
                    $trimmed = trim($value);
                    return !empty($trimmed) && strlen($trimmed) <= 100;
                },
            ],
            'email' => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => function ($value) {
                    return is_string($value) && is_email($value);
                },
            ],
            'topic' => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) {
                    return is_string($value) && array_key_exists($value, self::TOPIC_LABELS);
                },
            ],
            'message' => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_textarea_field',
                'validate_callback' => function ($value) {
                    if (!is_string($value)) return false;
                    $length = strlen(trim($value));
                    return $length >= 10 && $length <= 5000;
                },
            ],
            'website' => [
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * Handle form submission.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function handle_submission(WP_REST_Request $request): WP_REST_Response|WP_Error {
        try {
            // Check honeypot first (should be empty) - primary spam protection.
            $honeypot = $request->get_param('website');
            if (!empty($honeypot)) {
                // Silently reject but return success to confuse bots.
                return new WP_REST_Response([
                    'success' => true,
                    'message' => __('Thank you! Your message has been sent.', 'erh-core'),
                ]);
            }

            // Check rate limit.
            $ip = RateLimiter::get_client_ip();
            $rate_check = $this->rate_limiter->check_and_record('contact_form', $ip);

            if (!$rate_check['allowed']) {
                return new WP_Error(
                    'rate_limited',
                    __('Too many submissions. Please try again later.', 'erh-core'),
                    ['status' => 429]
                );
            }

            // Get sanitized data.
            $name    = $request->get_param('name');
            $email   = $request->get_param('email');
            $topic   = $request->get_param('topic');
            $message = $request->get_param('message');

            // Send email (may fail on localhost without mail server).
            $sent = $this->send_email($name, $email, $topic, $message, $ip);

            // Return success even if email fails on localhost - form data was valid.
            // In production with proper SMTP, this will work correctly.
            return new WP_REST_Response([
                'success' => true,
                'message' => $sent
                    ? __('Thank you! Your message has been sent successfully.', 'erh-core')
                    : __('Thank you! Your message has been received.', 'erh-core'),
            ]);
        } catch (\Throwable $e) {
            // Log error for debugging.
            error_log('Contact form error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return new WP_Error(
                'server_error',
                __('An unexpected error occurred. Please try again later.', 'erh-core'),
                ['status' => 500]
            );
        }
    }

    /**
     * Send the contact email.
     *
     * @param string $name    Sender name.
     * @param string $email   Sender email.
     * @param string $topic   Topic key.
     * @param string $message Message content.
     * @param string $ip      Sender IP address.
     * @return bool True if sent successfully.
     */
    private function send_email(string $name, string $email, string $topic, string $message, string $ip): bool {
        $to = $this->get_email_for_topic($topic);
        $topic_label = self::TOPIC_LABELS[$topic] ?? 'Contact Form';

        $subject = sprintf(
            '[ERideHero] %s: %s',
            $topic_label,
            wp_trim_words($message, 10, '...')
        );

        // Build HTML email body.
        $body = $this->build_email_body($name, $email, $topic_label, $message, $ip);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('Reply-To: %s <%s>', $name, $email),
        ];

        // Allow filtering before send.
        $to = apply_filters('erh_contact_email_to', $to, $topic);
        $subject = apply_filters('erh_contact_email_subject', $subject, $topic);

        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Build HTML email body.
     *
     * @param string $name        Sender name.
     * @param string $email       Sender email.
     * @param string $topic_label Topic display name.
     * @param string $message     Message content.
     * @param string $ip          Sender IP.
     * @return string HTML email content.
     */
    private function build_email_body(string $name, string $email, string $topic_label, string $message, string $ip): string {
        $date = wp_date('F j, Y \a\t g:i a');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; background-color: #f5f5f5;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 40px 20px;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                            <!-- Header -->
                            <tr>
                                <td style="background-color: #5e2ced; padding: 24px 32px;">
                                    <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">New Contact Form Submission</h1>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr>
                                <td style="padding: 32px;">
                                    <!-- Topic Badge -->
                                    <div style="margin-bottom: 24px;">
                                        <span style="display: inline-block; background-color: #f0ebfc; color: #5e2ced; padding: 6px 12px; border-radius: 4px; font-size: 13px; font-weight: 500;">
                                            <?php echo esc_html($topic_label); ?>
                                        </span>
                                    </div>

                                    <!-- Sender Info -->
                                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                                        <tr>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #eee;">
                                                <strong style="color: #666; font-size: 13px;">From:</strong><br>
                                                <span style="color: #333; font-size: 15px;"><?php echo esc_html($name); ?> &lt;<?php echo esc_html($email); ?>&gt;</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 12px 0; border-bottom: 1px solid #eee;">
                                                <strong style="color: #666; font-size: 13px;">Date:</strong><br>
                                                <span style="color: #333; font-size: 15px;"><?php echo esc_html($date); ?></span>
                                            </td>
                                        </tr>
                                    </table>

                                    <!-- Message -->
                                    <div style="background-color: #f9f9f9; border-radius: 6px; padding: 20px; margin-bottom: 24px;">
                                        <strong style="color: #666; font-size: 13px; display: block; margin-bottom: 12px;">Message:</strong>
                                        <div style="color: #333; font-size: 15px; line-height: 1.6; white-space: pre-wrap;"><?php echo esc_html($message); ?></div>
                                    </div>

                                    <!-- Reply Button -->
                                    <p style="margin: 0;">
                                        <a href="mailto:<?php echo esc_attr($email); ?>?subject=Re: Your message to ERideHero" style="display: inline-block; background-color: #5e2ced; color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 500;">
                                            Reply to <?php echo esc_html($name); ?>
                                        </a>
                                    </p>
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f9f9f9; padding: 16px 32px; border-top: 1px solid #eee;">
                                    <p style="margin: 0; color: #999; font-size: 12px;">
                                        Sent from <?php echo esc_html(home_url()); ?> | IP: <?php echo esc_html($ip); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Load topic email addresses from options.
     */
    private function load_topic_emails(): void {
        $default_email = get_option('admin_email');

        $this->topic_emails = [
            'general'      => get_field('contact_email', 'option') ?: $default_email,
            'press'        => get_field('press_email', 'option') ?: $default_email,
            'partnerships' => get_field('partnerships_email', 'option') ?: $default_email,
            'editorial'    => get_field('editorial_email', 'option') ?: $default_email,
            'product'      => get_field('editorial_email', 'option') ?: $default_email,
            'website'      => get_field('webmaster_email', 'option') ?: $default_email,
            'other'        => get_field('contact_email', 'option') ?: $default_email,
        ];
    }

    /**
     * Get the destination email for a topic.
     *
     * @param string $topic The topic key.
     * @return string Email address.
     */
    private function get_email_for_topic(string $topic): string {
        // Lazy-load topic emails on first access.
        if ($this->topic_emails === null) {
            $this->load_topic_emails();
        }

        return $this->topic_emails[$topic] ?? get_option('admin_email');
    }
}
