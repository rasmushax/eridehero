<?php
/**
 * Product Popularity Dashboard Widget.
 *
 * Displays top 10 most viewed products in the WordPress admin dashboard.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Database\ViewTracker;

/**
 * Handles the Popular Products dashboard widget.
 */
class ProductPopularityWidget {

    /**
     * ViewTracker database instance.
     *
     * @var ViewTracker
     */
    private ViewTracker $view_tracker;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->view_tracker = new ViewTracker();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    public function init(): void {
        add_action('wp_dashboard_setup', [$this, 'register_widget']);
    }

    /**
     * Register the dashboard widget.
     *
     * @return void
     */
    public function register_widget(): void {
        wp_add_dashboard_widget(
            'erh_popular_products',
            'Popular Products',
            [$this, 'render_widget'],
            null,
            null,
            'normal',
            'high'
        );
    }

    /**
     * Render the widget content.
     *
     * @return void
     */
    public function render_widget(): void {
        $popular = $this->view_tracker->get_popular_products(30, null, 10);

        if (empty($popular)) {
            echo '<p class="erh-widget-empty">No product view data yet. Views will appear here once users start viewing product pages.</p>';
            echo '<style>
                .erh-widget-empty {
                    color: #666;
                    font-style: italic;
                    margin: 0;
                    padding: 10px 0;
                }
            </style>';
            return;
        }

        echo '<table class="widefat striped erh-products-table">';
        echo '<thead><tr>';
        echo '<th>Product</th>';
        echo '<th style="width: 90px;">Type</th>';
        echo '<th style="width: 60px; text-align: center;">Views</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($popular as $item) {
            $product_id   = (int) $item['product_id'];
            $product_name = esc_html($item['product_name'] ?? get_the_title($product_id));
            $product_type = $item['product_type'] ?? '';
            $view_count   = (int) ($item['view_count'] ?? 0);

            // Get short type label.
            $type_label = $this->get_type_label($product_type);

            // Edit URL.
            $edit_url = get_edit_post_link($product_id);

            echo '<tr>';

            // Product column.
            echo '<td>';
            echo '<a href="' . esc_url($edit_url) . '"><strong>' . $product_name . '</strong></a>';
            echo '</td>';

            // Type column.
            echo '<td>';
            echo '<span class="erh-type-badge">' . esc_html($type_label) . '</span>';
            echo '</td>';

            // Views column.
            echo '<td style="text-align: center;">';
            echo '<span class="erh-view-count">' . number_format($view_count) . '</span>';
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        // Link to full admin page.
        echo '<p style="margin: 10px 0 0; text-align: right;">';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=products&page=popular-products')) . '" class="button button-secondary">View All Popular Products</a>';
        echo '</p>';

        // Widget styles.
        echo '<style>
            .erh-products-table {
                margin: 0;
            }
            .erh-products-table th {
                font-weight: 600;
                font-size: 12px;
            }
            .erh-products-table td {
                font-size: 13px;
                vertical-align: middle;
            }
            .erh-type-badge {
                display: inline-block;
                background: #f0f0f1;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
                white-space: nowrap;
            }
            .erh-view-count {
                background: #f0f0f1;
                padding: 2px 8px;
                border-radius: 3px;
                font-weight: 500;
            }
        </style>';
    }

    /**
     * Get short type label for display.
     *
     * @param string $product_type Full product type name.
     * @return string Short label.
     */
    private function get_type_label(string $product_type): string {
        $labels = [
            'Electric Scooter'   => 'E-Scooter',
            'Electric Bike'      => 'E-Bike',
            'Electric Unicycle'  => 'EUC',
            'Electric Skateboard' => 'E-Skate',
            'Hoverboard'         => 'Hoverboard',
        ];
        return $labels[$product_type] ?? $product_type;
    }
}
