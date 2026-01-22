<?php
/**
 * Email Builder - Base class for consistent branded email design.
 *
 * Provides reusable components for all email templates:
 * - Doctype, head, and styles
 * - Header with logo
 * - Footer with links and unsubscribe
 * - Main container wrapper
 *
 * @package ERH\Email
 */

declare(strict_types=1);

namespace ERH\Email;

use ERH\CategoryConfig;
use ERH\GeoConfig;

/**
 * Base email builder providing consistent branded components.
 *
 * Usage:
 * ```php
 * $builder = new EmailBuilder('US');
 * $html = $builder->build([
 *     'preview_text' => 'Check out this week\'s deals',
 *     'content'      => $your_content_html,
 *     'unsubscribe'  => $unsubscribe_url,
 * ]);
 * ```
 */
class EmailBuilder {

    /**
     * Brand colors - centralized for consistency.
     */
    public const COLOR_PRIMARY   = '#5e2ced';
    public const COLOR_DARK      = '#21273a';
    public const COLOR_BODY      = '#3d4668';
    public const COLOR_MUTED     = '#6f768f';
    public const COLOR_SUCCESS   = '#00b572';
    public const COLOR_BG        = '#f9fafe';
    public const COLOR_BORDER    = '#f3f2f5';
    public const COLOR_WHITE     = '#ffffff';

    /**
     * Production site URL for emails.
     * Images must use production URLs (localhost won't work in email clients).
     */
    public const PRODUCTION_URL = 'https://eridehero.com';

    /**
     * Site URL for links.
     *
     * @var string
     */
    protected string $site_url;

    /**
     * Theme URL for assets (production, for email images).
     *
     * @var string
     */
    protected string $theme_url;

    /**
     * User's geo region.
     *
     * @var string
     */
    protected string $geo;

    /**
     * Currency symbol for geo.
     *
     * @var string
     */
    protected string $currency_symbol;

    /**
     * Constructor.
     *
     * @param string $geo User's geo region (US, GB, EU, CA, AU).
     */
    public function __construct(string $geo = 'US') {
        $this->site_url        = home_url();
        $this->geo             = GeoConfig::is_valid_region($geo) ? $geo : 'US';
        $this->currency_symbol = GeoConfig::get_symbol(GeoConfig::get_currency($this->geo));

        // Always use production URL for theme assets in emails.
        // Email clients can't load images from localhost.
        $this->theme_url = self::PRODUCTION_URL . '/wp-content/themes/erh-theme';
    }

    /**
     * Build a complete email from content.
     *
     * @param array $args {
     *     @type string $preview_text Preview text shown in email clients.
     *     @type string $content      Main email content HTML.
     *     @type string $unsubscribe  Unsubscribe URL for footer.
     *     @type bool   $show_header  Whether to show header (default true).
     *     @type bool   $show_footer  Whether to show footer (default true).
     * }
     * @return string Complete HTML email.
     */
    public function build(array $args): string {
        $defaults = [
            'preview_text' => '',
            'content'      => '',
            'unsubscribe'  => '',
            'show_header'  => true,
            'show_footer'  => true,
        ];

        $args = array_merge($defaults, $args);

        $html = $this->get_doctype();
        $html .= $this->get_head();
        $html .= '<body class="body-text" style="margin: 0; padding: 0; background-color: ' . self::COLOR_BG . '; -webkit-font-smoothing: antialiased;">';

        if ($args['preview_text']) {
            $html .= $this->get_preview_text($args['preview_text']);
        }

        $html .= $this->get_main_container_start();

        if ($args['show_header']) {
            $html .= $this->get_header();
        }

        $html .= $args['content'];

        if ($args['show_footer']) {
            $html .= $this->get_footer($args['unsubscribe']);
        }

        $html .= $this->get_main_container_end();
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Get DOCTYPE and opening HTML tag.
     *
     * @return string
     */
    public function get_doctype(): string {
        return '<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">';
    }

    /**
     * Get the <head> section with styles.
     *
     * @return string
     */
    public function get_head(): string {
        return '
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>ERideHero</title>

    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:AllowPNG/>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->

    <!--[if !mso]><!-->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!--<![endif]-->

    <style>
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
            border-collapse: collapse !important;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: ' . self::COLOR_BG . ';
        }
        .body-text {
            font-family: "Figtree", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }
        a {
            color: ' . self::COLOR_PRIMARY . ';
            text-decoration: none;
        }
        /* Tablet: 2 products per row */
        @media only screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                padding-left: 16px !important;
                padding-right: 16px !important;
            }
            .content-padding {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
            .hero-title {
                font-size: 26px !important;
                line-height: 1.2 !important;
            }
            .section-title {
                font-size: 20px !important;
            }
            .product-cell {
                width: 50% !important;
                display: inline-block !important;
                vertical-align: top !important;
            }
            .product-cell-inner {
                width: 100% !important;
            }
        }
        /* Mobile: 1 product per row */
        @media only screen and (max-width: 480px) {
            .hero-title {
                font-size: 22px !important;
            }
            .hero-padding {
                padding: 32px 24px !important;
            }
            .content-padding {
                padding-left: 16px !important;
                padding-right: 16px !important;
            }
            .product-grid {
                display: block !important;
            }
            .product-cell {
                display: block !important;
                width: 100% !important;
                padding: 0 0 16px 0 !important;
            }
            .product-cell:last-child {
                padding-bottom: 0 !important;
            }
        }
    </style>
</head>';
    }

    /**
     * Get hidden preview text.
     *
     * @param string $text Preview text.
     * @return string
     */
    public function get_preview_text(string $text): string {
        return '
    <div style="display: none; font-size: 1px; color: ' . self::COLOR_BG . '; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
        ' . esc_html($text) . '
        &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
    </div>';
    }

    /**
     * Get main container start.
     *
     * @return string
     */
    public function get_main_container_start(): string {
        return '
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color: ' . self::COLOR_BG . ';">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <table role="presentation" class="container" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width: 600px;">';
    }

    /**
     * Get main container end.
     *
     * @return string
     */
    public function get_main_container_end(): string {
        return '
                </table>
            </td>
        </tr>
    </table>';
    }

    /**
     * Get email header with logo.
     *
     * @return string
     */
    public function get_header(): string {
        // Use PNG for email compatibility (SVG not widely supported in email clients).
        $logo_url = $this->theme_url . '/assets/images/logo.png';

        return '
                    <tr>
                        <td style="padding-bottom: 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <a href="' . esc_url($this->site_url) . '" style="text-decoration: none;">
                                            <img src="' . esc_url($logo_url) . '" alt="ERideHero" width="140" style="display: block; height: auto;">
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>';
    }

    /**
     * Get email footer.
     *
     * @param string $unsubscribe_url Unsubscribe URL.
     * @return string
     */
    public function get_footer(string $unsubscribe_url = ''): string {
        $year         = gmdate('Y');
        $settings_url = $this->site_url . '/account/#settings';

        // Fallback unsubscribe URL.
        if (empty($unsubscribe_url)) {
            $unsubscribe_url = $settings_url;
        }

        // Get category links from CategoryConfig.
        $escooter = CategoryConfig::get_by_key('escooter');
        $ebike    = CategoryConfig::get_by_key('ebike');
        $euc      = CategoryConfig::get_by_key('euc');

        return '
                    <tr>
                        <td style="padding-top: 40px; padding-bottom: 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                <!-- Category Links -->
                                <tr>
                                    <td align="center" style="padding-bottom: 24px;">
                                        <p style="margin: 0; font-size: 13px; color: ' . self::COLOR_MUTED . '; line-height: 2;">
                                            <a href="' . esc_url($this->site_url . '/' . $escooter['archive_slug'] . '/') . '" style="color: ' . self::COLOR_DARK . '; text-decoration: none; font-weight: 500;">' . esc_html($escooter['name']) . '</a>
                                            &nbsp;&nbsp;&bull;&nbsp;&nbsp;
                                            <a href="' . esc_url($this->site_url . '/' . $ebike['archive_slug'] . '/') . '" style="color: ' . self::COLOR_DARK . '; text-decoration: none; font-weight: 500;">' . esc_html($ebike['name']) . '</a>
                                            &nbsp;&nbsp;&bull;&nbsp;&nbsp;
                                            <a href="' . esc_url($this->site_url . '/' . $euc['archive_slug'] . '/') . '" style="color: ' . self::COLOR_DARK . '; text-decoration: none; font-weight: 500;">' . esc_html($euc['name_short']) . '</a>
                                            &nbsp;&nbsp;&bull;&nbsp;&nbsp;
                                            <a href="' . esc_url($this->site_url . '/deals/') . '" style="color: ' . self::COLOR_DARK . '; text-decoration: none; font-weight: 500;">All Deals</a>
                                        </p>
                                    </td>
                                </tr>

                                <!-- Divider -->
                                <tr>
                                    <td style="padding-bottom: 24px;">
                                        <div style="height: 1px; background-color: ' . self::COLOR_BORDER . ';"></div>
                                    </td>
                                </tr>

                                <!-- Legal -->
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0 0 8px 0; font-size: 12px; color: ' . self::COLOR_MUTED . ';">
                                            &copy; ' . esc_html($year) . ' ERideHero. All rights reserved.
                                        </p>
                                        <p style="margin: 0 0 16px 0; font-size: 12px; color: ' . self::COLOR_MUTED . '; line-height: 1.8;">
                                            <a href="' . esc_url($this->site_url . '/privacy/') . '" style="color: ' . self::COLOR_MUTED . '; text-decoration: underline;">Privacy Policy</a>
                                            &nbsp;&nbsp;&bull;&nbsp;&nbsp;
                                            <a href="' . esc_url($this->site_url . '/terms/') . '" style="color: ' . self::COLOR_MUTED . '; text-decoration: underline;">Terms</a>
                                            &nbsp;&nbsp;&bull;&nbsp;&nbsp;
                                            <a href="' . esc_url($unsubscribe_url) . '" style="color: ' . self::COLOR_MUTED . '; text-decoration: underline;">Unsubscribe</a>
                                        </p>
                                        <p style="margin: 0; font-size: 11px; color: ' . self::COLOR_MUTED . '; line-height: 1.6;">
                                            You\'re receiving this because you subscribed to updates.<br>
                                            <a href="' . esc_url($settings_url) . '" style="color: ' . self::COLOR_PRIMARY . '; text-decoration: none;">Update your preferences</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>';
    }

    // =========================================================================
    // HELPER COMPONENTS
    // Reusable building blocks for email content
    // =========================================================================

    /**
     * Create a hero section with dark gradient background.
     *
     * @param string $badge_text Text for the badge (e.g., "Weekly Deals Digest").
     * @param string $title      Main headline.
     * @param string $subtitle   Subtitle text (supports basic HTML like <strong>).
     * @return string
     */
    public function hero(string $badge_text, string $title, string $subtitle = ''): string {
        $html = '
                    <tr>
                        <td>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, ' . self::COLOR_DARK . ' 0%, #2d3554 100%); border-radius: 20px; overflow: hidden;">
                                <tr>
                                    <td class="hero-padding" style="padding: 48px 40px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">';

        // Badge.
        if ($badge_text) {
            $html .= '
                                            <tr>
                                                <td>
                                                    <span style="display: inline-block; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.18); border-radius: 8px; padding: 4px 14px; font-size: 13px; font-weight: 600; color: #ffffff;">' . esc_html($badge_text) . '</span>
                                                </td>
                                            </tr>';
        }

        // Title.
        $html .= '
                                            <tr>
                                                <td style="padding-top: 16px;">
                                                    <h1 class="hero-title" style="margin: 0; font-size: 32px; font-weight: 700; color: #ffffff; line-height: 1.15; letter-spacing: -0.5px;">
                                                        ' . esc_html($title) . '
                                                    </h1>
                                                </td>
                                            </tr>';

        // Subtitle.
        if ($subtitle) {
            $html .= '
                                            <tr>
                                                <td style="padding-top: 12px;">
                                                    <p style="margin: 0; font-size: 15px; color: rgba(255,255,255,0.75); line-height: 1.6;">
                                                        ' . $subtitle . '
                                                    </p>
                                                </td>
                                            </tr>';
        }

        $html .= '
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>';

        return $html;
    }

    /**
     * Create a white card section with border.
     *
     * @param string $content Inner HTML content.
     * @param string $padding_top Top padding (default 32px).
     * @return string
     */
    public function card(string $content, string $padding_top = '32px'): string {
        return '
                    <tr>
                        <td style="padding-top: ' . esc_attr($padding_top) . ';">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: ' . self::COLOR_WHITE . '; border-radius: 16px; border: 1px solid ' . self::COLOR_BORDER . '; overflow: hidden; box-shadow: 0 10px 30px rgba(33, 39, 58, 0.08);">
                                <tr>
                                    <td class="content-padding" style="padding: 24px 32px;">
                                        ' . $content . '
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>';
    }

    /**
     * Create a section header row.
     *
     * @param string      $title       Section title.
     * @param string|null $right_text  Optional right-aligned text (e.g., "16 deals").
     * @return string
     */
    public function section_header(string $title, ?string $right_text = null): string {
        $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
            <tr>
                <td>
                    <h2 class="section-title" style="margin: 0; font-size: 22px; font-weight: 700; color: ' . self::COLOR_DARK . ';">
                        ' . esc_html($title) . '
                    </h2>
                </td>';

        if ($right_text) {
            $html .= '
                <td align="right" valign="middle">
                    <span style="font-size: 14px; font-weight: 500; color: ' . self::COLOR_MUTED . ';">' . esc_html($right_text) . '</span>
                </td>';
        }

        $html .= '
            </tr>
        </table>';

        return $html;
    }

    /**
     * Create a primary button.
     *
     * @param string $url   Button URL.
     * @param string $text  Button text.
     * @param string $color Button background color (default primary).
     * @return string
     */
    public function button(string $url, string $text, string $color = ''): string {
        if (empty($color)) {
            $color = self::COLOR_DARK;
        }

        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
            <tr>
                <td align="center">
                    <a href="' . esc_url($url) . '" style="display: inline-block; padding: 12px 24px; background-color: ' . esc_attr($color) . '; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                        ' . esc_html($text) . '
                    </a>
                </td>
            </tr>
        </table>';
    }

    /**
     * Create a promo box with icon.
     *
     * @param string $icon_url    Icon image URL.
     * @param string $title       Promo title.
     * @param string $description Promo description.
     * @param string $button_url  CTA button URL.
     * @param string $button_text CTA button text.
     * @return string
     */
    public function promo_box(string $icon_url, string $title, string $description, string $button_url, string $button_text): string {
        return '
                    <tr>
                        <td style="padding-top: 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: linear-gradient(135deg, rgba(94, 44, 237, 0.1) 0%, rgba(94, 44, 237, 0.04) 100%); border-radius: 16px;">
                                <tr>
                                    <td style="padding: 32px; text-align: center;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>
                                                <td align="center">
                                                    <div style="width: 48px; height: 48px; background: rgba(94, 44, 237, 0.12); border-radius: 50%; margin: 0 auto 16px; text-align: center;">
                                                        <img src="' . esc_url($icon_url) . '" alt="" width="24" height="24" style="display: inline-block; margin-top: 12px;">
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td align="center">
                                                    <h3 style="margin: 0 0 8px 0; font-size: 18px; font-weight: 700; color: ' . self::COLOR_DARK . ';">
                                                        ' . esc_html($title) . '
                                                    </h3>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td align="center" style="padding-bottom: 20px;">
                                                    <p style="margin: 0; font-size: 14px; color: ' . self::COLOR_BODY . '; line-height: 1.6; max-width: 380px;">
                                                        ' . esc_html($description) . '
                                                    </p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td align="center">
                                                    <a href="' . esc_url($button_url) . '" style="display: inline-block; padding: 12px 24px; background-color: ' . self::COLOR_PRIMARY . '; color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; border-radius: 8px;">
                                                        ' . esc_html($button_text) . '
                                                    </a>
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
     * Create a paragraph.
     *
     * @param string $text  Paragraph text.
     * @param string $style Additional inline styles.
     * @return string
     */
    public function paragraph(string $text, string $style = ''): string {
        $default_style = 'margin: 0 0 16px 0; font-size: 16px; color: ' . self::COLOR_BODY . '; line-height: 1.6;';
        if ($style) {
            $default_style .= ' ' . $style;
        }

        return '<p style="' . esc_attr($default_style) . '">' . $text . '</p>';
    }

    /**
     * Create a personal sign-off with headshot.
     *
     * Used across welcome, price alerts, deals digest, and newsletter emails
     * for a personal touch from the founder.
     *
     * @param bool $show_headshot Whether to show the headshot image (default true).
     * @return string
     */
    public function signoff(bool $show_headshot = true): string {
        $headshot_url = $this->get_theme_url() . '/assets/images/rasmus-barslund.jpg';

        $html = '
                    <tr>
                        <td style="padding-top: 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: ' . self::COLOR_WHITE . '; border-radius: 16px; border: 1px solid ' . self::COLOR_BORDER . '; overflow: hidden;">
                                <tr>
                                    <td class="content-padding" style="padding: 24px 32px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>';

        if ($show_headshot) {
            $html .= '
                                                <td width="56" valign="top" style="padding-right: 16px;">
                                                    <img src="' . esc_url($headshot_url) . '" alt="Rasmus Barslund" width="48" height="48" style="display: block; border-radius: 50%; object-fit: cover;">
                                                </td>';
        }

        $html .= '
                                                <td valign="middle">
                                                    <p style="margin: 0 0 4px 0; font-size: 15px; color: ' . self::COLOR_BODY . '; line-height: 1.5;">
                                                        Ride safe,
                                                    </p>
                                                    <p style="margin: 0; font-size: 15px; color: ' . self::COLOR_DARK . '; line-height: 1.5;">
                                                        <strong>Rasmus Barslund</strong>
                                                        <span style="color: ' . self::COLOR_MUTED . ';"> · Founder, ERideHero</span>
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>';

        return $html;
    }

    /**
     * Get site URL.
     *
     * @return string
     */
    public function get_site_url(): string {
        return $this->site_url;
    }

    /**
     * Get theme URL.
     *
     * @return string
     */
    public function get_theme_url(): string {
        return $this->theme_url;
    }

    /**
     * Get currency symbol.
     *
     * @return string
     */
    public function get_currency_symbol(): string {
        return $this->currency_symbol;
    }

    /**
     * Get user geo.
     *
     * @return string
     */
    public function get_geo(): string {
        return $this->geo;
    }
}
