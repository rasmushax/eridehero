<?php
/**
 * Comparison Dashboard Widget.
 *
 * Displays top 10 most viewed comparisons in the WordPress admin dashboard.
 * Shows "Curated" badge for comparisons that have a CPT and "Create Curated"
 * action for those that don't.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Database\ComparisonViews;

/**
 * Handles the Popular Comparisons dashboard widget.
 */
class ComparisonDashboardWidget {

    /**
     * ComparisonViews database instance.
     *
     * @var ComparisonViews
     */
    private ComparisonViews $views_db;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->views_db = new ComparisonViews();
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
            'erh_popular_comparisons',
            'Popular Comparisons',
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
        $popular = $this->views_db->get_popular(null, 10);

        if (empty($popular)) {
            echo '<p class="erh-widget-empty">No comparison data yet. Views will appear here once users start comparing products.</p>';
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

        echo '<table class="widefat striped erh-comparisons-table">';
        echo '<thead><tr>';
        echo '<th>Products</th>';
        echo '<th style="width: 60px; text-align: center;">Views</th>';
        echo '<th style="width: 100px; text-align: center;">Status</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($popular as $item) {
            $product_1_id   = (int) $item['product_1_id'];
            $product_2_id   = (int) $item['product_2_id'];
            $product_1_name = esc_html($item['product_1_name'] ?? get_the_title($product_1_id));
            $product_2_name = esc_html($item['product_2_name'] ?? get_the_title($product_2_id));
            $view_count     = (int) ($item['view_count'] ?? 0);

            // Check for curated comparison.
            $curated_id = $this->views_db->get_curated_comparison($product_1_id, $product_2_id);

            echo '<tr>';

            // Products column.
            echo '<td>';
            echo '<strong>' . $product_1_name . '</strong>';
            echo ' <span style="color: #999;">vs</span> ';
            echo '<strong>' . $product_2_name . '</strong>';
            echo '</td>';

            // Views column.
            echo '<td style="text-align: center;">';
            echo '<span class="erh-view-count">' . number_format($view_count) . '</span>';
            echo '</td>';

            // Status column.
            echo '<td style="text-align: center;">';
            if ($curated_id) {
                $edit_url = get_edit_post_link($curated_id);
                echo '<a href="' . esc_url($edit_url) . '" class="erh-badge erh-badge--curated" title="Edit curated comparison">';
                echo '<span class="dashicons dashicons-yes-alt"></span> Curated';
                echo '</a>';
            } else {
                $create_url = admin_url('post-new.php?post_type=comparison&product_1=' . $product_1_id . '&product_2=' . $product_2_id);
                echo '<a href="' . esc_url($create_url) . '" class="erh-badge erh-badge--create" title="Create curated comparison">';
                echo '<span class="dashicons dashicons-plus-alt2"></span> Create';
                echo '</a>';
            }
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        // Link to full admin page.
        echo '<p style="margin: 10px 0 0; text-align: right;">';
        echo '<a href="' . esc_url(admin_url('edit.php?post_type=comparison&page=popular-comparisons')) . '" class="button button-secondary">View All Popular Comparisons</a>';
        echo '</p>';

        // Widget styles.
        echo '<style>
            .erh-comparisons-table {
                margin: 0;
            }
            .erh-comparisons-table th {
                font-weight: 600;
                font-size: 12px;
            }
            .erh-comparisons-table td {
                font-size: 13px;
                vertical-align: middle;
            }
            .erh-view-count {
                background: #f0f0f1;
                padding: 2px 8px;
                border-radius: 3px;
                font-weight: 500;
            }
            .erh-badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 3px 8px;
                font-size: 11px;
                font-weight: 500;
                border-radius: 3px;
                text-decoration: none;
            }
            .erh-badge .dashicons {
                font-size: 14px;
                width: 14px;
                height: 14px;
            }
            .erh-badge--curated {
                background: #e7f6ec;
                color: #1e7e34;
            }
            .erh-badge--curated:hover {
                background: #d4edd9;
            }
            .erh-badge--create {
                background: #e8f4fc;
                color: #0073aa;
            }
            .erh-badge--create:hover {
                background: #d4e8f7;
            }
        </style>';
    }
}
