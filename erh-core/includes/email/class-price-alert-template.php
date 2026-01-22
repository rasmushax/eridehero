<?php
/**
 * Price Alert Email Template - Sent when tracked products drop in price.
 *
 * @package ERH\Email
 */

declare(strict_types=1);

namespace ERH\Email;

use ERH\GeoConfig;

/**
 * Generates price drop alert emails for user's tracked products.
 */
class PriceAlertTemplate extends EmailBuilder {

    /**
     * Generate the price alert email HTML.
     *
     * @param string $username User's display name.
     * @param array  $deals    Array of deal data from price trackers.
     * @return string Complete HTML email.
     */
    public function render(string $username, array $deals): string {
        if (empty($deals)) {
            return '';
        }

        $deal_count = count($deals);
        $first_deal = $deals[0] ?? [];

        $content = '';

        // Hero section - personalized for single product.
        if ($deal_count === 1) {
            $product_name = $first_deal['product_name'] ?? 'A product';
            $eyebrow = 'Price Drop Alert';
            $title = $product_name . ' just dropped';
        } else {
            $eyebrow = $deal_count . ' Price Drops';
            $title = 'Products you\'re tracking just dropped';
        }

        $content .= $this->hero(
            $eyebrow,
            $title,
            'Prices can change quickly. Here\'s what we found for you.'
        );

        // Deals card.
        $deals_html = '
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="padding-bottom: 8px;">
                        <p style="margin: 0 0 20px 0; font-size: 16px; color: ' . self::COLOR_BODY . '; line-height: 1.6;">
                            Hi ' . esc_html($username) . ',
                        </p>
                    </td>
                </tr>';

        foreach ($deals as $index => $deal) {
            $is_last = ($index === count($deals) - 1);
            $deals_html .= $this->render_deal_card($deal, $is_last);
        }

        $deals_html .= '
            </table>';

        $content .= $this->card($deals_html);

        // Manage alerts promo.
        $content .= $this->promo_box(
            $this->get_theme_url() . '/assets/images/icons/bell.png',
            'Manage Your Alerts',
            'View all your tracked products, adjust target prices, or add new ones.',
            $this->get_site_url() . '/account/#trackers',
            'View Your Trackers'
        );

        // Personal sign-off with headshot.
        $content .= $this->signoff();

        // Preview text - personalized for single product.
        if ($deal_count === 1) {
            $product_name = $first_deal['product_name'] ?? 'A product';
            $preview_text = $product_name . ' just dropped in price!';
        } else {
            $preview_text = $deal_count . ' price drops on products you\'re tracking!';
        }

        return $this->build([
            'preview_text' => $preview_text,
            'content'      => $content,
            'unsubscribe'  => $this->get_site_url() . '/account/#settings',
        ]);
    }

    /**
     * Render a single deal card.
     *
     * @param array $deal    Deal data.
     * @param bool  $is_last Whether this is the last card (no border).
     * @return string HTML for the deal card.
     */
    private function render_deal_card(array $deal, bool $is_last = false): string {
        $name = $deal['product_name'] ?? 'Product';
        $image_url = $deal['image_url'] ?? '';
        $current_price = $deal['current_price'] ?? 0;
        $compare_price = $deal['compare_price'] ?? 0;
        $percent_below_avg = $deal['percent_below_avg'] ?? null;
        $url = $deal['url'] ?? '#';
        $tracking_users = $deal['tracking_users'] ?? 0;
        $currency = $deal['currency'] ?? 'USD';

        $currency_symbol = GeoConfig::get_symbol($currency);

        // Fallback image.
        if (empty($image_url)) {
            $image_url = 'https://eridehero.com/wp-content/uploads/2024/09/Placeholder.png';
        }

        $border_style = $is_last ? '' : 'border-bottom: 1px solid ' . self::COLOR_BORDER . ';';

        // Build the "below avg" pill if we have the data.
        $below_avg_html = '';
        if ($percent_below_avg !== null && $percent_below_avg > 0) {
            $arrow_url = $this->get_theme_url() . '/assets/images/icons/arrow-down.png';
            $below_avg_html = '
                                    <tr>
                                        <td style="padding-top: 6px;">
                                            <span style="display: inline-block; padding: 3px 8px; background-color: #ebf9f4; color: #00b572; font-size: 12px; font-weight: 600; border-radius: 4px;"><img src="' . esc_url($arrow_url) . '" alt="" width="12" height="12" style="display: inline; vertical-align: middle; margin-right: 2px;">' . esc_html(round($percent_below_avg)) . '% below avg</span>
                                        </td>
                                    </tr>';
        }

        return '
            <tr>
                <td style="padding: 20px 0; ' . $border_style . '">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                        <tr>
                            <td width="100" valign="top" style="padding-right: 16px;">
                                <a href="' . esc_url($url) . '" style="text-decoration: none;">
                                    <img src="' . esc_url($image_url) . '" alt="' . esc_attr($name) . '" width="100" style="display: block; border-radius: 8px; max-width: 100px;">
                                </a>
                            </td>
                            <td valign="top">
                                <a href="' . esc_url($url) . '" style="text-decoration: none;">
                                    <p style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: ' . self::COLOR_DARK . '; line-height: 1.3;">
                                        ' . esc_html($name) . '
                                    </p>
                                </a>
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td>
                                            <span style="font-size: 20px; font-weight: 700; color: ' . self::COLOR_DARK . ';">' . esc_html($currency_symbol . number_format($current_price, 0)) . '</span>
                                            <span style="font-size: 14px; color: ' . self::COLOR_MUTED . '; text-decoration: line-through; padding-left: 8px;">was ' . esc_html($currency_symbol . number_format($compare_price, 0)) . '</span>
                                        </td>
                                    </tr>
                                    ' . $below_avg_html . '
                                    ' . ($tracking_users > 5 ? '
                                    <tr>
                                        <td style="padding-top: 8px;">
                                            <span style="font-size: 12px; color: ' . self::COLOR_MUTED . ';">' . esc_html($tracking_users - 1) . ' others tracking this</span>
                                        </td>
                                    </tr>' : '') . '
                                </table>
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top: 12px;">
                                    <tr>
                                        <td>
                                            <a href="' . esc_url($url) . '" style="display: inline-block; padding: 8px 16px; background-color: ' . self::COLOR_DARK . '; color: #ffffff; font-size: 13px; font-weight: 600; text-decoration: none; border-radius: 6px;">View Deal</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>';
    }

    /**
     * Get the email subject line.
     *
     * @param int         $deal_count   Number of deals.
     * @param string|null $product_name Product name for single-product alerts.
     * @return string Subject line.
     */
    public static function get_subject(int $deal_count = 1, ?string $product_name = null): string {
        if ($deal_count === 1 && $product_name) {
            return 'Price Drop: ' . $product_name . ' just dropped!';
        }
        if ($deal_count === 1) {
            return 'Price Drop Alert: A product you\'re tracking just dropped!';
        }
        return sprintf('Price Drop Alert: %d products you\'re tracking just dropped!', $deal_count);
    }
}
