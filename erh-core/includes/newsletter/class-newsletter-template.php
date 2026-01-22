<?php
/**
 * Newsletter Template - Renders newsletter content blocks to HTML.
 *
 * @package ERH\Newsletter
 */

declare(strict_types=1);

namespace ERH\Newsletter;

use ERH\Email\EmailBuilder;

/**
 * Renders ACF newsletter blocks to branded HTML email.
 *
 * Extends EmailBuilder to use its design system and components.
 */
class NewsletterTemplate extends EmailBuilder {

    /**
     * Render a complete newsletter to HTML.
     *
     * @param int $newsletter_id The newsletter post ID.
     * @return string Complete HTML email.
     */
    public function render(int $newsletter_id): string {
        $blocks          = get_field('newsletter_blocks', $newsletter_id) ?: [];
        $preview_text    = get_field('newsletter_preview_text', $newsletter_id) ?: '';
        $include_signoff = get_field('newsletter_include_signoff', $newsletter_id);

        // Build inner content from blocks.
        $inner_content = '';
        foreach ($blocks as $block) {
            $inner_content .= $this->render_block($block);
        }

        // Add sign-off if enabled (inside the card).
        if ($include_signoff) {
            $inner_content .= $this->render_signoff_inline();
        }

        // Wrap all content in a single card.
        $content = $this->wrap_in_card($inner_content);

        // Build unsubscribe URL.
        $unsubscribe_url = $this->get_site_url() . '/account/#settings';

        // Wrap in email template.
        return $this->build([
            'preview_text' => $preview_text,
            'content'      => $content,
            'unsubscribe'  => $unsubscribe_url,
        ]);
    }

    /**
     * Wrap content in a single card container.
     *
     * @param string $content Inner HTML content.
     * @return string Card-wrapped content.
     */
    private function wrap_in_card(string $content): string {
        return '
                    <tr>
                        <td>
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: ' . self::COLOR_WHITE . '; border-radius: 16px; border: 1px solid ' . self::COLOR_BORDER . '; overflow: hidden;">
                                ' . $content . '
                            </table>
                        </td>
                    </tr>';
    }

    /**
     * Render a single content block.
     *
     * @param array $block Block data from ACF repeater.
     * @return string Rendered HTML.
     */
    private function render_block(array $block): string {
        $type = $block['block_type'] ?? '';

        return match ($type) {
            'hero'    => $this->render_hero_block($block),
            'text'    => $this->render_text_block($block),
            'image'   => $this->render_image_block($block),
            'button'  => $this->render_button_block($block),
            'divider' => $this->render_divider_block(),
            default   => '',
        };
    }

    /**
     * Render a hero section block (white background with border-bottom).
     *
     * @param array $block Block data.
     * @return string Rendered HTML.
     */
    private function render_hero_block(array $block): string {
        $badge    = $block['hero_badge'] ?? '';
        $title    = $block['hero_title'] ?? '';
        $subtitle = $block['hero_subtitle'] ?? '';

        if (empty($title)) {
            return '';
        }

        $html = '<tr>
                    <td style="padding: 32px 32px 24px; border-bottom: 1px solid ' . self::COLOR_BORDER . ';">';

        // Badge/eyebrow.
        if ($badge) {
            $html .= '
                        <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 600; color: ' . self::COLOR_PRIMARY . '; text-transform: uppercase; letter-spacing: 0.5px;">' . esc_html($badge) . '</p>';
        }

        $html .= '
                        <h1 style="margin: 0; font-size: 28px; font-weight: 700; color: ' . self::COLOR_DARK . '; line-height: 1.2; letter-spacing: -0.5px;">' . esc_html($title) . '</h1>';

        // Subtitle.
        if ($subtitle) {
            $html .= '
                        <p style="margin: 12px 0 0; font-size: 16px; color: ' . self::COLOR_BODY . '; line-height: 1.5;">' . esc_html($subtitle) . '</p>';
        }

        $html .= '
                    </td>
                </tr>';

        return $html;
    }

    /**
     * Render a text content block.
     *
     * @param array $block Block data.
     * @return string Rendered HTML.
     */
    private function render_text_block(array $block): string {
        $content = $block['text_content'] ?? '';

        if (empty($content)) {
            return '';
        }

        // Process content - wpautop for formatting.
        $content = wpautop($content);
        $content = $this->format_text_content($content);

        return '<tr>
                    <td class="content-padding" style="padding: 24px 32px 0;">
                        ' . $content . '
                    </td>
                </tr>';
    }

    /**
     * Format text content for email.
     *
     * @param string $content HTML content from wysiwyg.
     * @return string Formatted content with inline styles.
     */
    private function format_text_content(string $content): string {
        // Style paragraphs - remove bottom margin on last one.
        $content = preg_replace(
            '/<p>/i',
            '<p style="margin: 0 0 16px 0; font-size: 16px; color: ' . self::COLOR_BODY . '; line-height: 1.6;">',
            $content
        );

        // Remove margin from last paragraph.
        $content = preg_replace(
            '/<p style="([^"]*)">((?:(?!<\/p>).)*)<\/p>\s*$/is',
            '<p style="$1 margin-bottom: 0;">$2</p>',
            $content
        );

        // Style links.
        $content = preg_replace(
            '/<a /i',
            '<a style="color: ' . self::COLOR_PRIMARY . '; text-decoration: underline;" ',
            $content
        );

        // Style strong/bold.
        $content = preg_replace(
            '/<strong>/i',
            '<strong style="color: ' . self::COLOR_DARK . '; font-weight: 600;">',
            $content
        );

        // Style lists.
        $content = preg_replace(
            '/<ul>/i',
            '<ul style="margin: 0 0 16px 0; padding-left: 20px; font-size: 16px; color: ' . self::COLOR_BODY . '; line-height: 1.6;">',
            $content
        );
        $content = preg_replace(
            '/<ol>/i',
            '<ol style="margin: 0 0 16px 0; padding-left: 20px; font-size: 16px; color: ' . self::COLOR_BODY . '; line-height: 1.6;">',
            $content
        );
        $content = preg_replace(
            '/<li>/i',
            '<li style="margin-bottom: 8px;">',
            $content
        );

        // Style headings.
        $content = preg_replace(
            '/<h2>/i',
            '<h2 style="margin: 0 0 16px 0; font-size: 22px; font-weight: 700; color: ' . self::COLOR_DARK . ';">',
            $content
        );
        $content = preg_replace(
            '/<h3>/i',
            '<h3 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 600; color: ' . self::COLOR_DARK . ';">',
            $content
        );

        return $content;
    }

    /**
     * Render an image block (full width, rounded corners).
     *
     * @param array $block Block data.
     * @return string Rendered HTML.
     */
    private function render_image_block(array $block): string {
        $image = $block['image'] ?? null;

        if (empty($image) || empty($image['url'])) {
            return '';
        }

        $url  = $image['url'];
        $alt  = $block['image_alt'] ?: ($image['alt'] ?: ($image['title'] ?: 'Newsletter image'));
        $link = $block['image_link'] ?? '';

        // Build the image HTML - full width, rounded corners.
        $img_html = sprintf(
            '<img src="%s" alt="%s" width="100%%" style="display: block; width: 100%%; height: auto; border-radius: 12px;">',
            esc_url($url),
            esc_attr($alt)
        );

        // Wrap in link if provided.
        if ($link) {
            $img_html = sprintf(
                '<a href="%s" style="display: block; text-decoration: none;">%s</a>',
                esc_url($link),
                $img_html
            );
        }

        return '<tr>
                    <td style="padding: 24px 32px 0;">
                        ' . $img_html . '
                    </td>
                </tr>';
    }

    /**
     * Render a button block.
     *
     * @param array $block Block data.
     * @return string Rendered HTML.
     */
    private function render_button_block(array $block): string {
        $text = $block['button_text'] ?? '';
        $url  = $block['button_url'] ?? '';

        if (empty($text) || empty($url)) {
            return '';
        }

        return '<tr>
                    <td style="padding: 24px 32px 0;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td align="center">
                                    <a href="' . esc_url($url) . '" style="display: inline-block; padding: 14px 28px; background-color: ' . self::COLOR_PRIMARY . '; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 8px;">' . esc_html($text) . '</a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>';
    }

    /**
     * Render a divider block.
     *
     * @return string Rendered HTML.
     */
    private function render_divider_block(): string {
        return '<tr>
                    <td style="padding: 24px 32px 0;">
                        <div style="height: 1px; background-color: ' . self::COLOR_BORDER . ';"></div>
                    </td>
                </tr>';
    }

    /**
     * Render sign-off inline (inside the card).
     *
     * @return string Rendered HTML.
     */
    private function render_signoff_inline(): string {
        $headshot_url = $this->get_theme_url() . '/assets/images/rasmus-barslund.jpg';

        return '<tr>
                    <td style="padding: 24px 32px 32px; border-top: 1px solid ' . self::COLOR_BORDER . '; margin-top: 24px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                            <tr>
                                <td width="56" valign="top" style="padding-right: 16px;">
                                    <img src="' . esc_url($headshot_url) . '" alt="Rasmus Barslund" width="48" height="48" style="display: block; border-radius: 50%; object-fit: cover;">
                                </td>
                                <td valign="middle">
                                    <p style="margin: 0 0 4px 0; font-size: 15px; color: ' . self::COLOR_BODY . '; line-height: 1.5;">Ride safe,</p>
                                    <p style="margin: 0; font-size: 15px; color: ' . self::COLOR_DARK . '; line-height: 1.5;">
                                        <strong>Rasmus Barslund</strong>
                                        <span style="color: ' . self::COLOR_MUTED . ';"> · Founder, ERideHero</span>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>';
    }
}
