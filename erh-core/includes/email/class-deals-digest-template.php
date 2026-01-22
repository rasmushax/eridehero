<?php
/**
 * Deals Digest Email Template - Modern branded email design.
 *
 * Extends EmailBuilder for consistent branding across all emails.
 *
 * @package ERH\Email
 */

declare(strict_types=1);

namespace ERH\Email;

use ERH\CategoryConfig;

/**
 * Generates the deals digest HTML email template.
 *
 * Supports multi-category deals with dynamic scaling:
 * - Single category: up to 20 deals
 * - Multiple categories: up to 8 deals per category
 */
class DealsDigestTemplate extends EmailBuilder {

    /**
     * Max deals per category when multiple categories exist.
     *
     * @var int
     */
    private const MULTI_CATEGORY_LIMIT = 8;

    /**
     * Max deals for single category.
     *
     * @var int
     */
    private const SINGLE_CATEGORY_LIMIT = 20;

    /**
     * Get category info from CategoryConfig.
     *
     * @param string $category_slug Category slug (escooter, ebike, etc.).
     * @return array{name: string, deals_page: string}
     */
    private function get_category_info(string $category_slug): array {
        $category = CategoryConfig::get_by_key($category_slug);

        if ($category) {
            return [
                'name'       => $category['name_short'],
                'deals_page' => '/deals/' . $category['slug'] . '/',
            ];
        }

        // Fallback for unknown categories.
        return [
            'name'       => ucfirst($category_slug),
            'deals_page' => '/deals/',
        ];
    }

    /**
     * Generate the full email HTML.
     *
     * @param array<string, array> $deals_by_category Deals grouped by category slug.
     * @param string               $unsubscribe_url   Unsubscribe URL for footer.
     * @return string Complete HTML email.
     */
    public function render(array $deals_by_category, string $unsubscribe_url = ''): string {
        // Calculate total deals and max discount across all categories.
        $total_deals  = 0;
        $max_discount = 0;

        foreach ($deals_by_category as $deals) {
            $total_deals += count($deals);
            foreach ($deals as $deal) {
                $discount = abs($deal['deal_analysis']['discount_percent'] ?? 0);
                if ($discount > $max_discount) {
                    $max_discount = $discount;
                }
            }
        }

        // Determine max deals per category based on subscription count.
        $category_count   = count($deals_by_category);
        $max_per_category = $category_count === 1 ? self::SINGLE_CATEGORY_LIMIT : self::MULTI_CATEGORY_LIMIT;

        // Determine title based on single vs multi category.
        $category_slugs = array_keys($deals_by_category);
        if ($category_count === 1) {
            $slug = reset($category_slugs);
            $category_info = $this->get_category_info($slug);
            $title = 'The Biggest ' . $category_info['name'] . ' Deals This Week';
        } else {
            $title = 'This Week\'s Biggest Electric Ride Deals';
        }

        // Build content sections.
        $content = '';

        // Hero section with dynamic eyebrow showing max discount.
        $content .= $this->hero(
            'Save up to ' . round($max_discount) . '%',
            $title,
            $total_deals . ' deals based on real 6-month price tracking. No inflated "before" prices.'
        );

        // Render each category section.
        foreach ($deals_by_category as $category_slug => $deals) {
            $limited_deals = array_slice($deals, 0, $max_per_category);
            $content .= $this->render_category_section($category_slug, $limited_deals, count($deals));
        }

        // Price tracker promo.
        $content .= $this->promo_box(
            $this->get_theme_url() . '/assets/images/icons/bell.png',
            'Watching Something Specific?',
            'Set a price alert and we\'ll email you the moment it drops to your target price.',
            $this->get_site_url() . '/account/#trackers',
            'Set Up Price Alerts'
        );

        // Personal sign-off with headshot.
        $content .= $this->signoff();

        // Build complete email using parent's build method.
        return $this->build([
            'preview_text' => $total_deals . ' deals based on real price history.',
            'content'      => $content,
            'unsubscribe'  => $unsubscribe_url,
        ]);
    }

    /**
     * Render a category section with deals grid.
     *
     * @param string $category_slug Category slug (e.g., 'escooter').
     * @param array  $deals         Array of deal products.
     * @param int    $total_count   Total deals in this category (may be more than shown).
     * @return string
     */
    private function render_category_section(string $category_slug, array $deals, int $total_count): string {
        if (empty($deals)) {
            return '';
        }

        $category_info = $this->get_category_info($category_slug);

        $html = '
                    <tr>
                        <td style="padding-top: 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background: ' . self::COLOR_WHITE . '; border-radius: 16px; border: 1px solid ' . self::COLOR_BORDER . '; overflow: hidden; box-shadow: 0 10px 30px rgba(33, 39, 58, 0.08);">
                                <!-- Section Header -->
                                <tr>
                                    <td class="content-padding" style="padding: 24px 32px 20px 32px;">
                                        ' . $this->section_header(
                                            $category_info['name'] . 's',
                                            $total_count . ' on sale'
                                        ) . '
                                    </td>
                                </tr>

                                <!-- Products Grid -->
                                <tr>
                                    <td class="content-padding" style="padding: 0 32px 24px 32px;">';

        // Render product rows (3 per row on desktop).
        $rows = array_chunk($deals, 3);
        foreach ($rows as $row_index => $row_deals) {
            $is_last_row = ($row_index === count($rows) - 1);
            $html .= $this->render_product_row($row_deals, $is_last_row);
        }

        $html .= '
                                    </td>
                                </tr>

                                <!-- View All Link -->
                                <tr>
                                    <td class="content-padding" style="padding: 0 32px 24px 32px;">
                                        ' . $this->button(
                                            $this->get_site_url() . $category_info['deals_page'],
                                            'See All ' . $total_count . ' Deals'
                                        ) . '
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>';

        return $html;
    }

    /**
     * Render a row of product cards (up to 4).
     *
     * @param array $deals   Array of deal products (max 4).
     * @param bool  $is_last Whether this is the last row.
     * @return string
     */
    private function render_product_row(array $deals, bool $is_last = false): string {
        $padding_bottom = $is_last ? '0' : '12px';

        $html = '
                                        <table role="presentation" class="product-grid" cellpadding="0" cellspacing="0" border="0" width="100%">
                                            <tr>';

        $count = count($deals);
        foreach ($deals as $index => $deal) {
            $padding_left  = ($index === 0) ? '0' : '6px';
            $padding_right = ($index === $count - 1) ? '0' : '6px';
            $html .= $this->render_product_cell($deal, $padding_left, $padding_right, $padding_bottom);
        }

        // Fill empty cells to maintain grid (if less than 3).
        for ($i = $count; $i < 3; $i++) {
            $html .= '
                                                <td class="product-cell" width="33.33%" valign="top" style="padding: 0;"></td>';
        }

        $html .= '
                                            </tr>
                                        </table>';

        return $html;
    }

    /**
     * Render a single product card cell.
     *
     * @param array  $deal          Deal data.
     * @param string $padding_left  Left padding.
     * @param string $padding_right Right padding.
     * @param string $padding_bottom Bottom padding.
     * @return string
     */
    private function render_product_cell(array $deal, string $padding_left, string $padding_right, string $padding_bottom): string {
        $name      = $deal['name'] ?? 'Product';
        $image_url = $deal['image'] ?? $deal['thumbnail'] ?? '';
        $permalink = $deal['permalink'] ?? '#';
        $analysis  = $deal['deal_analysis'] ?? [];

        $price    = $analysis['current_price'] ?? 0;
        $discount = abs($analysis['discount_percent'] ?? 0);

        // Fallback image.
        if (empty($image_url)) {
            $image_url = 'https://eridehero.com/wp-content/uploads/2024/09/Placeholder.png';
        }

        return '
                                                <td class="product-cell" width="33.33%" valign="top" style="padding: ' . $padding_bottom . ' ' . $padding_right . ' ' . $padding_bottom . ' ' . $padding_left . ';">
                                                    <table role="presentation" class="product-cell-inner" cellpadding="0" cellspacing="0" border="0" width="100%" style="border: 1px solid ' . self::COLOR_BORDER . '; border-radius: 10px; overflow: hidden;">
                                                        <tr>
                                                            <td align="center" style="padding: 12px 8px 8px 8px;">
                                                                <a href="' . esc_url($permalink) . '" style="text-decoration: none;">
                                                                    <img src="' . esc_url($image_url) . '" alt="' . esc_attr($name) . '" width="90" height="68" style="display: block; object-fit: contain;">
                                                                </a>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td align="center" style="padding: 0 10px 12px 10px;">
                                                                <a href="' . esc_url($permalink) . '" style="text-decoration: none; display: block; max-width: 100%;">
                                                                    <p style="margin: 0 0 6px 0; font-size: 13px; font-weight: 600; color: ' . self::COLOR_DARK . '; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;">' . esc_html($name) . '</p>
                                                                </a>
                                                                <p style="margin: 0 0 6px 0; font-size: 15px; font-weight: 700; color: ' . self::COLOR_DARK . ';">' . esc_html($this->get_currency_symbol() . number_format($price, 0)) . '</p>
                                                                <span style="display: inline-block; padding: 2px 6px; background-color: #ebf9f4; color: #00b572; font-size: 11px; font-weight: 600; border-radius: 4px;"><img src="' . esc_url($this->get_theme_url() . '/assets/images/icons/arrow-down.png') . '" alt="" width="12" height="12" style="display: inline; vertical-align: middle; margin-right: 2px;">' . esc_html(round($discount)) . '% below avg</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>';
    }
}
