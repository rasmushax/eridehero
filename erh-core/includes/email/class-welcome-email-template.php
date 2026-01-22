<?php
/**
 * Welcome Email Template - Sent to new users after registration.
 *
 * @package ERH\Email
 */

declare(strict_types=1);

namespace ERH\Email;

/**
 * Generates the welcome email for new user registrations.
 */
class WelcomeEmailTemplate extends EmailBuilder {

    /**
     * Generate the welcome email HTML.
     *
     * @param string $username    User's display name or username.
     * @param string $account_url URL to the user's account page.
     * @return string Complete HTML email.
     */
    public function render(string $username, string $account_url = ''): string {
        if (empty($account_url)) {
            $account_url = $this->get_site_url() . '/account/';
        }

        // Get product count for the database feature.
        $product_count = $this->get_product_count();

        $content = '';

        // Hero section.
        $content .= $this->hero(
            'Welcome to the community',
            'Your ERideHero account is ready',
            'You now have access to the most comprehensive electric ride research tools on the web.'
        );

        // Features card.
        $features_html = '
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="padding-bottom: 20px;">
                        <p style="margin: 0 0 16px 0; font-size: 16px; color: ' . self::COLOR_BODY . '; line-height: 1.6;">
                            Hi ' . esc_html($username) . ',
                        </p>
                        <p style="margin: 0 0 20px 0; font-size: 16px; color: ' . self::COLOR_BODY . '; line-height: 1.6;">
                            Thanks for joining ERideHero. Here\'s what you can do with your free account:
                        </p>
                    </td>
                </tr>
                ' . $this->render_feature_row(
                    $this->get_theme_url() . '/assets/images/icons/search.png',
                    'Product Database',
                    'Filter and compare ' . number_format($product_count) . ' electric rides by specs, price, and performance.'
                ) . '
                ' . $this->render_feature_row(
                    $this->get_theme_url() . '/assets/images/icons/bell.png',
                    'Price Trackers',
                    'Set alerts for your favorite models and get notified when prices drop.'
                ) . '
                ' . $this->render_feature_row(
                    $this->get_theme_url() . '/assets/images/icons/chart.png',
                    'Real Price History',
                    'We track prices daily so you can find actually good deals and avoid inflated "before" prices.'
                ) . '
                ' . $this->render_feature_row(
                    $this->get_theme_url() . '/assets/images/icons/deal.png',
                    'Weekly Deals Digest',
                    'Opt in to receive the best electric ride deals in your inbox.'
                ) . '
                <tr>
                    <td style="padding-top: 8px;">
                        ' . $this->button($account_url, 'Go to Your Account') . '
                    </td>
                </tr>
            </table>';

        $content .= $this->card($features_html);

        // Personal sign-off with headshot.
        $content .= $this->signoff();

        return $this->build([
            'preview_text' => 'Welcome to ERideHero! Your account is ready.',
            'content'      => $content,
            'unsubscribe'  => '',
        ]);
    }

    /**
     * Render a feature row with icon.
     *
     * @param string $icon_url    Icon image URL.
     * @param string $title       Feature title.
     * @param string $description Feature description.
     * @return string HTML table row.
     */
    private function render_feature_row(string $icon_url, string $title, string $description): string {
        return '
            <tr>
                <td style="padding-bottom: 16px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td width="44" valign="top">
                                <div style="width: 36px; height: 36px; background: ' . self::COLOR_BG . '; border-radius: 8px; text-align: center; line-height: 36px;">
                                    <img src="' . esc_url($icon_url) . '" alt="" width="18" height="18" style="display: inline-block; vertical-align: middle;">
                                </div>
                            </td>
                            <td valign="top">
                                <p style="margin: 0 0 2px 0; font-size: 15px; font-weight: 600; color: ' . self::COLOR_DARK . ';">
                                    ' . esc_html($title) . '
                                </p>
                                <p style="margin: 0; font-size: 14px; color: ' . self::COLOR_MUTED . '; line-height: 1.4;">
                                    ' . esc_html($description) . '
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>';
    }

    /**
     * Get the email subject line.
     *
     * @return string Subject line.
     */
    public static function get_subject(): string {
        return 'Welcome to ERideHero!';
    }

    /**
     * Get total published product count.
     *
     * @return int Product count.
     */
    private function get_product_count(): int {
        $count = wp_count_posts('products');
        return (int) ($count->publish ?? 0);
    }
}
