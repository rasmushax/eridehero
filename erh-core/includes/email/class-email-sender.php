<?php
/**
 * Email sender class for sending branded HTML emails.
 *
 * Uses dedicated template classes for each email type:
 * - WelcomeEmailTemplate for welcome emails
 * - PasswordResetTemplate for password reset emails
 * - PriceAlertTemplate for price drop notifications
 * - DealsDigestTemplate for weekly deals digest
 *
 * @package ERH\Email
 */

declare(strict_types=1);

namespace ERH\Email;

use ERH\CategoryConfig;

/**
 * Handles sending branded HTML emails via wp_mail.
 */
class EmailSender {

    /**
     * Send a welcome email after registration.
     *
     * @param string $to        Recipient email address.
     * @param string $user_name The user's display name.
     * @param string $geo       User's geo region (default US).
     * @return bool True on success, false on failure.
     */
    public function send_welcome(string $to, string $user_name, string $geo = 'US'): bool {
        $template = new WelcomeEmailTemplate($geo);
        $html     = $template->render($user_name);
        $subject  = WelcomeEmailTemplate::get_subject();

        return $this->send_html($to, $subject, $html);
    }

    /**
     * Send a password reset email.
     *
     * @param string $to        Recipient email address.
     * @param string $user_name The user's display name.
     * @param string $reset_url The password reset URL.
     * @param string $geo       User's geo region (default US).
     * @return bool True on success, false on failure.
     */
    public function send_password_reset(string $to, string $user_name, string $reset_url, string $geo = 'US'): bool {
        $template = new PasswordResetTemplate($geo);
        $html     = $template->render($user_name, $reset_url);
        $subject  = PasswordResetTemplate::get_subject();

        return $this->send_html($to, $subject, $html);
    }

    /**
     * Send a price drop notification email.
     *
     * @param string $to        Recipient email address.
     * @param string $user_name The user's display name.
     * @param array  $deals     Array of deal data.
     * @param string $geo       User's geo region (default US).
     * @return bool True on success, false on failure.
     */
    public function send_price_drop_notification(string $to, string $user_name, array $deals, string $geo = 'US'): bool {
        if (empty($deals)) {
            return false;
        }

        $template     = new PriceAlertTemplate($geo);
        $html         = $template->render($user_name, $deals);
        $deal_count   = count($deals);
        $product_name = $deals[0]['product_name'] ?? null;
        $subject      = PriceAlertTemplate::get_subject($deal_count, $product_name);

        return $this->send_html($to, $subject, $html);
    }

    /**
     * Send a multi-category deals digest email.
     *
     * @param string               $to                 Recipient email address.
     * @param array<string, array> $deals_by_category  Deals grouped by category slug (escooter, ebike, etc.).
     * @param string               $geo                User's geo region.
     * @param string               $unsubscribe_url    Unsubscribe URL for footer.
     * @return bool True on success, false on failure.
     */
    public function send_deals_digest(
        string $to,
        array $deals_by_category,
        string $geo = 'US',
        string $unsubscribe_url = ''
    ): bool {
        if (empty($deals_by_category)) {
            return false;
        }

        // Count total deals.
        $total_deals = 0;
        foreach ($deals_by_category as $deals) {
            $total_deals += count($deals);
        }

        if ($total_deals === 0) {
            return false;
        }

        // Generate subject based on categories.
        $category_count = count($deals_by_category);
        if ($category_count === 1) {
            $slug    = array_key_first($deals_by_category);
            $name    = CategoryConfig::get_name($slug, 'name_short') ?: 'Electric Ride';
            $subject = sprintf(__("This Week's Best %s Deals", 'erh-core'), $name);
        } else {
            $subject = __('Your Weekly Deals Roundup', 'erh-core');
        }

        // Generate HTML using template.
        $template = new DealsDigestTemplate($geo);
        $html     = $template->render($deals_by_category, $unsubscribe_url);

        return $this->send_html($to, $subject, $html);
    }

    /**
     * Send an HTML email with standard headers.
     *
     * @param string $to      Recipient email address.
     * @param string $subject Email subject.
     * @param string $html    Complete HTML email body.
     * @param array  $headers Optional additional headers.
     * @return bool True on success, false on failure.
     */
    public function send_html(string $to, string $subject, string $html, array $headers = []): bool {
        $default_headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->get_from_header(),
        ];

        $all_headers = array_merge($default_headers, $headers);

        $sent = wp_mail($to, $subject, $html, $all_headers);

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
     * @param string $to      Recipient email address.
     * @param string $subject Email subject.
     * @param string $content Plain text content.
     * @param array  $headers Optional additional headers.
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
     * Get the From header value.
     *
     * @return string The From header.
     */
    private function get_from_header(): string {
        $site_name   = get_bloginfo('name');
        $admin_email = get_option('admin_email');

        return sprintf('%s <%s>', $site_name, $admin_email);
    }
}
