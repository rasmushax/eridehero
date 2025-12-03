<?php
/**
 * Email template class for generating branded HTML emails.
 *
 * @package ERH\Email
 */

declare(strict_types=1);

namespace ERH\Email;

/**
 * Generates branded HTML email templates.
 */
class EmailTemplate {

    /**
     * Brand colors.
     */
    private const COLOR_PRIMARY = '#5e2ced';
    private const COLOR_DARK = '#21273a';
    private const COLOR_BODY = '#4b5166';
    private const COLOR_MUTED = '#9a9ea6';
    private const COLOR_GREEN = '#2ea961';
    private const COLOR_BACKGROUND = '#f4f5f6';

    /**
     * Wrap content in the branded email template.
     *
     * @param string $content The email content.
     * @return string The complete HTML email.
     */
    public function wrap(string $content): string {
        $logo_url = 'https://eridehero.com/wp-content/uploads/2021/09/logo.png';
        $site_url = home_url();

        ob_start();
        ?>
        <!doctype html>
        <html lang="en">
        <head>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
            <title><?php echo esc_html(get_bloginfo('name')); ?></title>
            <style>
                .email-btn:hover {
                    background: <?php echo self::COLOR_DARK; ?> !important;
                }
            </style>
        </head>
        <body style="font-family: Helvetica, sans-serif; -webkit-font-smoothing: antialiased; font-size: 16px; line-height: 1.5; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; background-color: <?php echo self::COLOR_BACKGROUND; ?>; margin: 0; padding: 0;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: separate; background-color: <?php echo self::COLOR_BACKGROUND; ?>; width: 100%;">
                <tr>
                    <td align="center" style="vertical-align: top; padding: 24px;">
                        <!-- Main Content -->
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: separate; max-width: 600px;">
                            <tr>
                                <td style="background-color: #ffffff; border-radius: 8px; padding: 32px;">
                                    <?php echo $content; ?>
                                </td>
                            </tr>
                        </table>

                        <!-- Footer -->
                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="border-collapse: separate; max-width: 600px;">
                            <tr>
                                <td align="center" style="padding-top: 24px;">
                                    <a href="<?php echo esc_url($site_url); ?>" style="text-decoration: none;">
                                        <img src="<?php echo esc_url($logo_url); ?>" alt="ERideHero" style="width: 145px; height: auto;">
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding-top: 16px; color: <?php echo self::COLOR_MUTED; ?>; font-size: 14px;">
                                    The consumer-first, data-driven guide to micromobility
                                </td>
                            </tr>
                            <tr>
                                <td align="center" style="padding-top: 12px; color: <?php echo self::COLOR_MUTED; ?>; font-size: 12px;">
                                    <a href="<?php echo esc_url($site_url); ?>/account/?view=settings" style="color: <?php echo self::COLOR_MUTED; ?>; text-decoration: underline;">
                                        Manage email preferences
                                    </a>
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
     * Generate a paragraph element.
     *
     * @param string $text The paragraph text.
     * @param array $style Optional style overrides.
     * @return string The HTML paragraph.
     */
    public function paragraph(string $text, array $style = []): string {
        $default_style = [
            'font-size'     => '16px',
            'color'         => self::COLOR_BODY,
            'margin'        => '0 0 16px 0',
            'line-height'   => '1.5',
        ];

        $merged_style = array_merge($default_style, $style);
        $style_string = $this->array_to_style($merged_style);

        return sprintf('<p style="%s">%s</p>', esc_attr($style_string), $text);
    }

    /**
     * Generate a heading element.
     *
     * @param string $text The heading text.
     * @param int $level The heading level (1-6).
     * @param array $style Optional style overrides.
     * @return string The HTML heading.
     */
    public function heading(string $text, int $level = 1, array $style = []): string {
        $level = max(1, min(6, $level));

        $font_sizes = [
            1 => '24px',
            2 => '20px',
            3 => '18px',
            4 => '16px',
            5 => '14px',
            6 => '12px',
        ];

        $default_style = [
            'font-size'     => $font_sizes[$level],
            'color'         => self::COLOR_DARK,
            'margin'        => '0 0 16px 0',
            'font-weight'   => 'bold',
            'line-height'   => '1.3',
        ];

        $merged_style = array_merge($default_style, $style);
        $style_string = $this->array_to_style($merged_style);

        return sprintf('<h%d style="%s">%s</h%d>', $level, esc_attr($style_string), esc_html($text), $level);
    }

    /**
     * Generate a button element.
     *
     * @param string $url The button URL.
     * @param string $text The button text.
     * @param array $style Optional style overrides.
     * @return string The HTML button.
     */
    public function button(string $url, string $text, array $style = []): string {
        $default_style = [
            'display'           => 'inline-block',
            'background-color'  => self::COLOR_PRIMARY,
            'color'             => '#ffffff',
            'padding'           => '12px 24px',
            'border-radius'     => '6px',
            'text-decoration'   => 'none',
            'font-weight'       => 'bold',
            'font-size'         => '16px',
            'text-align'        => 'center',
        ];

        $merged_style = array_merge($default_style, $style);
        $style_string = $this->array_to_style($merged_style);

        return sprintf(
            '<a href="%s" class="email-btn" style="%s">%s</a>',
            esc_url($url),
            esc_attr($style_string),
            esc_html($text)
        );
    }

    /**
     * Generate a centered button wrapper.
     *
     * @param string $url The button URL.
     * @param string $text The button text.
     * @return string The HTML button with centering.
     */
    public function centered_button(string $url, string $text): string {
        return sprintf(
            '<table border="0" cellpadding="0" cellspacing="0" width="100%%" style="margin: 24px 0;"><tr><td align="center">%s</td></tr></table>',
            $this->button($url, $text)
        );
    }

    /**
     * Generate a link element.
     *
     * @param string $url The link URL.
     * @param string $text The link text.
     * @param array $style Optional style overrides.
     * @return string The HTML link.
     */
    public function link(string $url, string $text, array $style = []): string {
        $default_style = [
            'color'             => self::COLOR_PRIMARY,
            'text-decoration'   => 'underline',
        ];

        $merged_style = array_merge($default_style, $style);
        $style_string = $this->array_to_style($merged_style);

        return sprintf(
            '<a href="%s" style="%s">%s</a>',
            esc_url($url),
            esc_attr($style_string),
            esc_html($text)
        );
    }

    /**
     * Generate a divider element.
     *
     * @return string The HTML divider.
     */
    public function divider(): string {
        return '<div style="width: 100%; height: 1px; background-color: #e3e8ed; margin: 24px 0;"></div>';
    }

    /**
     * Generate a product card for emails.
     *
     * @param array $product Product data with keys: name, image_url, price, compare_price, discount, url.
     * @return string The HTML product card.
     */
    public function product_card(array $product): string {
        $image_url = $product['image_url'] ?? 'https://eridehero.com/wp-content/uploads/2024/09/Placeholder.png';
        $name = $product['name'] ?? 'Product';
        $price = $product['price'] ?? 0;
        $compare_price = $product['compare_price'] ?? null;
        $discount = $product['discount'] ?? null;
        $url = $product['url'] ?? '#';

        ob_start();
        ?>
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e3e8ed;">
            <tr>
                <td width="80" valign="top" style="padding: 5px; background: white; border-radius: 5px;">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($name); ?>" style="max-width: 75px; height: auto;">
                </td>
                <td style="padding-left: 20px;" valign="top">
                    <h3 style="font-size: 18px; color: <?php echo self::COLOR_DARK; ?>; margin: 0 0 10px 0;">
                        <a href="<?php echo esc_url($url); ?>" style="color: <?php echo self::COLOR_DARK; ?>; text-decoration: none; font-weight: bold;">
                            <?php echo esc_html($name); ?>
                        </a>
                    </h3>
                    <p style="font-size: 18px; color: <?php echo self::COLOR_DARK; ?>; font-weight: bold; margin: 0 0 5px 0;">
                        $<?php echo esc_html(number_format($price, 2)); ?>
                        <?php if ($compare_price): ?>
                            <span style="font-size: 14px; font-weight: 400; padding-left: 15px; color: #6f768f; text-decoration: line-through;">
                                $<?php echo esc_html(number_format($compare_price, 2)); ?>
                            </span>
                        <?php endif; ?>
                    </p>
                    <?php if ($discount): ?>
                        <p style="font-size: 14px; color: <?php echo self::COLOR_GREEN; ?>; margin: 0;">
                            <?php echo esc_html($discount); ?>% below 6-month average
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate a price drop notification card.
     *
     * @param array $deal Deal data with keys: product_name, image_url, current_price, compare_price, savings, savings_percent, url, tracking_users.
     * @return string The HTML deal card.
     */
    public function price_drop_card(array $deal): string {
        $image_url = $deal['image_url'] ?? 'https://eridehero.com/wp-content/uploads/2024/09/Placeholder.png';
        $name = $deal['product_name'] ?? 'Product';
        $current_price = $deal['current_price'] ?? 0;
        $compare_price = $deal['compare_price'] ?? 0;
        $savings = $deal['savings'] ?? 0;
        $savings_percent = $deal['savings_percent'] ?? 0;
        $url = $deal['url'] ?? '#';
        $tracking_users = $deal['tracking_users'] ?? 0;

        ob_start();
        ?>
        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px; margin-top: 20px; padding-top: 24px; border-top: 1px solid #f4f4f4;">
            <tr>
                <td width="30%" valign="top">
                    <?php if ($image_url): ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($name); ?>" style="max-width: 100%; height: auto; margin-bottom: 10px;">
                    <?php endif; ?>
                </td>
                <td width="70%" valign="top" style="padding-left: 20px;">
                    <?php echo $this->paragraph('<strong>' . esc_html($name) . '</strong>', ['margin' => '0 0 10px 0']); ?>
                    <?php echo $this->paragraph(
                        sprintf('Price dropped from $%s to $%s', number_format($compare_price, 2), number_format($current_price, 2)),
                        ['margin' => '0 0 8px 0']
                    ); ?>
                    <?php echo $this->paragraph(
                        sprintf('You save: $%s (%d%% off!)', number_format($savings, 2), $savings_percent),
                        ['margin' => '0 0 16px 0', 'color' => self::COLOR_GREEN]
                    ); ?>
                    <?php echo $this->button($url, 'View Deal Now'); ?>
                    <?php if ($tracking_users > 5): ?>
                        <?php echo $this->paragraph(
                            sprintf('%d other users are tracking this item.', $tracking_users - 1),
                            ['margin' => '16px 0 0 0', 'font-size' => '14px', 'color' => self::COLOR_MUTED]
                        ); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Convert a style array to a CSS string.
     *
     * @param array $styles The style array.
     * @return string The CSS string.
     */
    private function array_to_style(array $styles): string {
        $parts = [];
        foreach ($styles as $property => $value) {
            $parts[] = $property . ': ' . $value;
        }
        return implode('; ', $parts);
    }
}
