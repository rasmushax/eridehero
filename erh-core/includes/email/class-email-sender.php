<?php
/**
 * Email sender class for sending branded HTML emails.
 *
 * @package ERH\Email
 */

declare(strict_types=1);

namespace ERH\Email;

/**
 * Handles sending branded HTML emails via wp_mail.
 */
class EmailSender {

    /**
     * Email template instance.
     *
     * @var EmailTemplate
     */
    private EmailTemplate $template;

    /**
     * Constructor.
     *
     * @param EmailTemplate $template The email template instance.
     */
    public function __construct(EmailTemplate $template) {
        $this->template = $template;
    }

    /**
     * Send an HTML email using the branded template.
     *
     * @param string $to Recipient email address.
     * @param string $subject Email subject.
     * @param string $content Email content (will be wrapped in template).
     * @param array $headers Optional additional headers.
     * @return bool True on success, false on failure.
     */
    public function send(string $to, string $subject, string $content, array $headers = []): bool {
        // Wrap content in branded template.
        $html_content = $this->template->wrap($content);

        // Set default headers for HTML email.
        $default_headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_from_header(),
        ];

        $all_headers = array_merge($default_headers, $headers);

        // Send the email.
        $sent = wp_mail($to, $subject, $html_content, $all_headers);

        // Log failures for debugging.
        if (!$sent) {
            error_log(sprintf(
                '[ERH Email] Failed to send email to %s with subject: %s',
                $to,
                $subject
            ));
        }

        return $sent;
    }

    /**
     * Send a plain text email (no template).
     *
     * @param string $to Recipient email address.
     * @param string $subject Email subject.
     * @param string $content Plain text content.
     * @param array $headers Optional additional headers.
     * @return bool True on success, false on failure.
     */
    public function send_plain(string $to, string $subject, string $content, array $headers = []): bool {
        $default_headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->get_from_header(),
        ];

        $all_headers = array_merge($default_headers, $headers);

        return wp_mail($to, $subject, $content, $all_headers);
    }

    /**
     * Send a price drop notification email.
     *
     * @param string $to Recipient email address.
     * @param string $user_name The user's display name.
     * @param array $deals Array of deal data.
     * @return bool True on success, false on failure.
     */
    public function send_price_drop_notification(string $to, string $user_name, array $deals): bool {
        if (empty($deals)) {
            return false;
        }

        $deal_count = count($deals);
        $subject = $deal_count === 1
            ? __('Price Drop Alert: 1 Deal for You!', 'erh-core')
            : sprintf(__('Price Drop Alert: %d New Deals for You!', 'erh-core'), $deal_count);

        // Build email content.
        $content = '';

        $content .= $this->template->paragraph(sprintf(__('Hi %s,', 'erh-core'), esc_html($user_name)));

        $intro_text = $deal_count === 1
            ? __("Great news! We've spotted a hot deal on an item you're tracking:", 'erh-core')
            : sprintf(__("Great news! We've spotted %d hot deals on items you're tracking:", 'erh-core'), $deal_count);

        $content .= $this->template->paragraph($intro_text);

        // Add each deal card.
        foreach ($deals as $deal) {
            $content .= $this->template->price_drop_card($deal);
        }

        $content .= $this->template->divider();

        $content .= $this->template->paragraph(
            __("Hurry, prices can change quickly! These alerts are based on your preferences.", 'erh-core')
        );

        $content .= $this->template->paragraph(__('Ride safe,', 'erh-core'));
        $content .= $this->template->paragraph(__('Rasmus from ERideHero', 'erh-core'));

        $manage_url = home_url('/account/?view=settings');
        $content .= $this->template->paragraph(
            sprintf(
                __('P.S. Manage your alerts or unsubscribe %s.', 'erh-core'),
                $this->template->link($manage_url, __('here', 'erh-core'))
            ),
            ['font-size' => '14px', 'color' => '#6f768f']
        );

        return $this->send($to, $subject, $content);
    }

    /**
     * Send a deals digest email.
     *
     * @param string $to Recipient email address.
     * @param array $deals Array of deal products.
     * @return bool True on success, false on failure.
     */
    public function send_deals_digest(string $to, array $deals): bool {
        if (empty($deals)) {
            return false;
        }

        $deals_count = count($deals);
        $subject = __('The Biggest E-Scooter Deals This Week', 'erh-core');

        // Build email content.
        $content = '';

        $content .= $this->template->heading(__('The Biggest E-Scooter Deals This Week', 'erh-core'), 1);

        $content .= $this->template->paragraph(
            __("These deals are based on real 6-month historical pricing data - no fake deals or inflated 'before' prices, just genuine savings on e-scooters.", 'erh-core')
        );

        $content .= $this->template->heading(
            sprintf(__('Top 20 Deals (out of %d total)', 'erh-core'), $deals_count),
            2
        );

        // Build deals table.
        $content .= '<table cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse: separate; border-spacing: 0; overflow: hidden; border: 1px solid #e3e8ed; border-radius: 10px;">';

        $displayed_deals = array_slice($deals, 0, 20);

        foreach ($displayed_deals as $deal) {
            $content .= $this->build_deal_row($deal);
        }

        $content .= '</table>';

        // View all button if more than 20 deals.
        if ($deals_count > 20) {
            $view_all_url = home_url('/tool/electric-scooter-deals/');
            $content .= $this->template->centered_button($view_all_url, sprintf(__('View All %d Deals', 'erh-core'), $deals_count));
        }

        $content .= $this->template->paragraph(
            sprintf(
                __("Don't want to receive these deals anymore or want to change how often you get them? %s.", 'erh-core'),
                $this->template->link(home_url('/account/?view=settings'), __('Update your preferences in your Account Settings', 'erh-core'))
            ),
            ['margin-top' => '30px', 'padding-top' => '20px', 'border-top' => '1px solid #ddd', 'font-size' => '14px', 'color' => '#6f768f']
        );

        return $this->send($to, $subject, $content);
    }

    /**
     * Send a password reset email.
     *
     * @param string $to Recipient email address.
     * @param string $user_name The user's display name.
     * @param string $reset_url The password reset URL.
     * @return bool True on success, false on failure.
     */
    public function send_password_reset(string $to, string $user_name, string $reset_url): bool {
        $subject = sprintf(__('[%s] Password Reset Request', 'erh-core'), get_bloginfo('name'));

        $content = '';

        $content .= $this->template->paragraph(sprintf(__('Hi %s,', 'erh-core'), esc_html($user_name)));

        $content .= $this->template->paragraph(
            __('Someone requested a password reset for your account. If this was you, click the button below to reset your password:', 'erh-core')
        );

        $content .= $this->template->centered_button($reset_url, __('Reset Password', 'erh-core'));

        $content .= $this->template->paragraph(
            __("If you didn't request this, you can safely ignore this email. Your password will not be changed.", 'erh-core')
        );

        $content .= $this->template->paragraph(
            __('This link will expire in 24 hours.', 'erh-core'),
            ['font-size' => '14px', 'color' => '#6f768f']
        );

        return $this->send($to, $subject, $content);
    }

    /**
     * Send a welcome email after registration.
     *
     * @param string $to Recipient email address.
     * @param string $user_name The user's display name.
     * @return bool True on success, false on failure.
     */
    public function send_welcome(string $to, string $user_name): bool {
        $subject = sprintf(__('Welcome to %s!', 'erh-core'), get_bloginfo('name'));

        $content = '';

        $content .= $this->template->paragraph(sprintf(__('Hi %s,', 'erh-core'), esc_html($user_name)));

        $content .= $this->template->paragraph(
            __("Welcome to ERideHero! We're excited to have you join our community of electric mobility enthusiasts.", 'erh-core')
        );

        $content .= $this->template->paragraph(__('With your account, you can:', 'erh-core'));

        $content .= '<ul style="color: #4b5166; margin: 0 0 16px 0; padding-left: 20px;">';
        $content .= '<li style="margin-bottom: 8px;">' . __('Track prices on your favorite products', 'erh-core') . '</li>';
        $content .= '<li style="margin-bottom: 8px;">' . __('Get notified when prices drop', 'erh-core') . '</li>';
        $content .= '<li style="margin-bottom: 8px;">' . __('Submit reviews and share your experience', 'erh-core') . '</li>';
        $content .= '<li style="margin-bottom: 8px;">' . __('Receive weekly deals digest (optional)', 'erh-core') . '</li>';
        $content .= '</ul>';

        $content .= $this->template->centered_button(home_url('/account/'), __('Go to Your Account', 'erh-core'));

        $content .= $this->template->paragraph(__('Ride safe,', 'erh-core'));
        $content .= $this->template->paragraph(__('The ERideHero Team', 'erh-core'));

        return $this->send($to, $subject, $content);
    }

    /**
     * Build a single deal row for the deals digest table.
     *
     * @param object $deal The deal data.
     * @return string The HTML row.
     */
    private function build_deal_row(object $deal): string {
        $price_history = is_array($deal->price_history) ? $deal->price_history : maybe_unserialize($deal->price_history);

        if (!is_array($price_history) || !isset($price_history['average_price_6m'])) {
            return '';
        }

        $price = $this->split_price((float) $deal->price);
        $avg_price = number_format($price_history['average_price_6m'], 2);
        $discount = isset($deal->price_diff_6m) ? abs((int) round($deal->price_diff_6m)) : 0;
        $image_url = !empty($deal->image_url) ? $deal->image_url : 'https://eridehero.com/wp-content/uploads/2024/09/Placeholder.png';

        ob_start();
        ?>
        <tr>
            <td style="padding: 13px; border-top: 1px solid #e3e8ed;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td width="1px" style="padding: 5px; background: white; border-radius: 5px;">
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($deal->name); ?>" style="max-width: 75px; height: auto;">
                        </td>
                        <td style="padding-left: 20px;">
                            <h2 style="font-size: 18px; color: #21273a; margin: 0 0 10px 0;">
                                <a href="<?php echo esc_url($deal->permalink); ?>" style="color: #21273a; text-decoration: none; font-weight: bold;">
                                    <?php echo esc_html($deal->name); ?>
                                </a>
                            </h2>
                            <p style="font-size: 18px; color: #21273a; font-weight: bold; margin: 0 0 5px 0;">
                                $<?php echo esc_html($price['whole']); ?><sup><?php echo esc_html($price['fractional']); ?></sup>
                                <span style="font-size: 14px; font-weight: 400; padding-left: 20px; color: #6f768f; text-decoration: line-through;">
                                    $<?php echo esc_html($avg_price); ?>
                                </span>
                            </p>
                            <p style="font-size: 14px; color: #2ea961; margin: 0;">
                                <?php echo esc_html($discount); ?>% below 6-month average
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Split a price into whole and fractional parts.
     *
     * @param float $price The price.
     * @return array{whole: string, fractional: string} The split price.
     */
    private function split_price(float $price): array {
        $formatted = number_format($price, 2);
        $parts = explode('.', $formatted);

        return [
            'whole'      => $parts[0],
            'fractional' => $parts[1] ?? '00',
        ];
    }

    /**
     * Get the From header value.
     *
     * @return string The From header.
     */
    private function get_from_header(): string {
        $site_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');

        return sprintf('%s <%s>', $site_name, $admin_email);
    }
}
