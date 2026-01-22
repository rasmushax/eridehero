<?php
/**
 * Product Popularity Admin Page.
 *
 * Full admin page showing product view statistics with time period and
 * product type filtering.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Database\ViewTracker;
use ERH\Database\PriceTracker;

/**
 * Handles the Product Popularity admin page.
 */
class ProductPopularityPage {

    /**
     * ViewTracker database instance.
     *
     * @var ViewTracker
     */
    private ViewTracker $view_tracker;

    /**
     * PriceTracker database instance.
     *
     * @var PriceTracker
     */
    private PriceTracker $price_tracker;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->view_tracker = new ViewTracker();
        $this->price_tracker = new PriceTracker();
    }

    /**
     * Initialize hooks.
     *
     * @return void
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'add_submenu_page']);
    }

    /**
     * Add submenu page under Products CPT.
     *
     * @return void
     */
    public function add_submenu_page(): void {
        add_submenu_page(
            'edit.php?post_type=products',
            'Popular Products',
            'Popular',
            'edit_posts',
            'popular-products',
            [$this, 'render_page']
        );
    }

    /**
     * Render the admin page.
     *
     * @return void
     */
    public function render_page(): void {
        // Get filter parameters.
        $days        = isset($_GET['days']) ? absint($_GET['days']) : 30;
        $product_type = isset($_GET['product_type']) ? sanitize_text_field(wp_unslash($_GET['product_type'])) : '';
        $per_page    = 50;
        $paged       = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset      = ($paged - 1) * $per_page;

        // Validate days (only allow 7, 30, 90).
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        // Get popular products with pagination.
        $items = $this->view_tracker->get_popular_products(
            $days,
            $product_type ?: null,
            $per_page,
            $offset
        );

        // Get total count for pagination.
        $total = $this->view_tracker->get_popular_products_count($days, $product_type ?: null);

        // Stats for the selected period.
        $total_views = $this->view_tracker->get_total_views($days);
        $viewed_count = $this->view_tracker->get_viewed_product_count($days);
        $avg_views = $viewed_count > 0 ? round($total_views / $viewed_count, 1) : 0;

        // Product type options for filter.
        $product_types = [
            ''                   => 'All Types',
            'Electric Scooter'   => 'Electric Scooters',
            'Electric Bike'      => 'E-Bikes',
            'Electric Unicycle'  => 'Electric Unicycles',
            'Electric Skateboard' => 'E-Skateboards',
            'Hoverboard'         => 'Hoverboards',
        ];

        // Time period options.
        $periods = [
            7  => 'Last 7 days',
            30 => 'Last 30 days',
            90 => 'Last 90 days',
        ];

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Popular Products</h1>
            <hr class="wp-header-end">

            <!-- Stats Cards -->
            <div class="erh-stats-cards">
                <div class="erh-stat-card">
                    <span class="erh-stat-value"><?php echo esc_html(number_format($total_views)); ?></span>
                    <span class="erh-stat-label">Total Views (<?php echo esc_html($periods[$days]); ?>)</span>
                </div>
                <div class="erh-stat-card">
                    <span class="erh-stat-value"><?php echo esc_html(number_format($viewed_count)); ?></span>
                    <span class="erh-stat-label">Products Viewed</span>
                </div>
                <div class="erh-stat-card">
                    <span class="erh-stat-value"><?php echo esc_html(number_format($avg_views, 1)); ?></span>
                    <span class="erh-stat-label">Avg Views/Product</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="post_type" value="products">
                    <input type="hidden" name="page" value="popular-products">

                    <div class="alignleft actions">
                        <select name="days">
                            <?php foreach ($periods as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($days, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="product_type">
                            <?php foreach ($product_types as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($product_type, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="submit" class="button" value="Filter">
                    </div>
                </form>

                <div class="tablenav-pages">
                    <span class="displaying-num"><?php echo esc_html(number_format($total)); ?> items</span>
                    <?php
                    $total_pages = (int) ceil($total / $per_page);
                    if ($total_pages > 1) {
                        $base_url = admin_url('edit.php?post_type=products&page=popular-products');
                        $base_url .= '&days=' . $days;
                        if ($product_type) {
                            $base_url .= '&product_type=' . urlencode($product_type);
                        }

                        echo '<span class="pagination-links">';

                        // First/prev page.
                        if ($paged > 1) {
                            echo '<a class="first-page button" href="' . esc_url($base_url) . '"><span class="screen-reader-text">First page</span><span aria-hidden="true">&laquo;</span></a>';
                            echo '<a class="prev-page button" href="' . esc_url($base_url . '&paged=' . ($paged - 1)) . '"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">&lsaquo;</span></a>';
                        }

                        echo '<span class="paging-input">';
                        echo '<span class="tablenav-paging-text"> ' . esc_html($paged) . ' of <span class="total-pages">' . esc_html($total_pages) . '</span></span>';
                        echo '</span>';

                        // Next/last page.
                        if ($paged < $total_pages) {
                            echo '<a class="next-page button" href="' . esc_url($base_url . '&paged=' . ($paged + 1)) . '"><span class="screen-reader-text">Next page</span><span aria-hidden="true">&rsaquo;</span></a>';
                            echo '<a class="last-page button" href="' . esc_url($base_url . '&paged=' . $total_pages) . '"><span class="screen-reader-text">Last page</span><span aria-hidden="true">&raquo;</span></a>';
                        }

                        echo '</span>';
                    }
                    ?>
                </div>
                <br class="clear">
            </div>

            <!-- Table -->
            <?php if (empty($items)) : ?>
                <div class="notice notice-info">
                    <p>No product view data yet. Views will appear here once users start viewing product pages.</p>
                </div>
            <?php else : ?>
                <?php
                // Bulk fetch tracker counts to avoid N+1 queries.
                $product_ids = array_map(fn($item) => (int) $item['product_id'], $items);
                $tracker_counts = $this->price_tracker->get_counts_bulk($product_ids);
                ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-product">Product</th>
                            <th scope="col" class="manage-column column-type" style="width: 140px;">Type</th>
                            <th scope="col" class="manage-column column-views" style="width: 100px; text-align: center;">Views</th>
                            <th scope="col" class="manage-column column-trackers" style="width: 80px; text-align: center;">Trackers</th>
                            <th scope="col" class="manage-column column-last-viewed" style="width: 140px;">Last Viewed</th>
                            <th scope="col" class="manage-column column-actions" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item) : ?>
                            <?php
                            $product_id   = (int) $item['product_id'];
                            $product_name = $item['product_name'] ?? get_the_title($product_id);
                            $item_type    = $item['product_type'] ?? '';
                            $view_count   = (int) ($item['view_count'] ?? 0);
                            $last_viewed  = $item['last_viewed'] ?? '';

                            // Get tracker count from bulk-fetched data.
                            $tracker_count = $tracker_counts[$product_id] ?? 0;

                            // Get type label.
                            $type_label = $this->get_type_label($item_type);

                            // Generate URLs.
                            $edit_url = get_edit_post_link($product_id);
                            $view_url = get_permalink($product_id);
                            ?>
                            <tr>
                                <td class="column-product">
                                    <strong>
                                        <a href="<?php echo esc_url($edit_url); ?>"><?php echo esc_html($product_name); ?></a>
                                    </strong>
                                </td>
                                <td class="column-type">
                                    <span class="erh-type-badge erh-type-badge--<?php echo esc_attr(sanitize_title($item_type)); ?>">
                                        <?php echo esc_html($type_label); ?>
                                    </span>
                                </td>
                                <td class="column-views" style="text-align: center;">
                                    <span class="erh-view-count"><?php echo esc_html(number_format($view_count)); ?></span>
                                </td>
                                <td class="column-trackers" style="text-align: center;">
                                    <?php if ($tracker_count > 0) : ?>
                                        <span class="erh-tracker-count"><?php echo esc_html(number_format($tracker_count)); ?></span>
                                    <?php else : ?>
                                        <span class="erh-tracker-count erh-tracker-count--zero">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-last-viewed">
                                    <?php
                                    if ($last_viewed) {
                                        echo esc_html(human_time_diff(strtotime($last_viewed)) . ' ago');
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo esc_url($view_url); ?>" class="button button-small" target="_blank">View</a>
                                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-small">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <style>
            .erh-stats-cards {
                display: flex;
                gap: 20px;
                margin: 20px 0;
            }
            .erh-stat-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                padding: 20px 30px;
                text-align: center;
            }
            .erh-stat-value {
                display: block;
                font-size: 32px;
                font-weight: 600;
                color: #1d2327;
                line-height: 1.2;
            }
            .erh-stat-label {
                display: block;
                font-size: 13px;
                color: #646970;
                margin-top: 5px;
            }
            .erh-type-badge {
                display: inline-block;
                background: #f0f0f1;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                white-space: nowrap;
            }
            .erh-type-badge--electric-scooter {
                background: #e8f4fc;
                color: #0073aa;
            }
            .erh-type-badge--electric-bike {
                background: #e7f6ec;
                color: #1e7e34;
            }
            .erh-type-badge--electric-unicycle {
                background: #fef3e7;
                color: #996800;
            }
            .erh-type-badge--electric-skateboard {
                background: #f3e8fc;
                color: #7e1e9e;
            }
            .erh-type-badge--hoverboard {
                background: #fce8ec;
                color: #9e1e3a;
            }
            .erh-view-count {
                background: #f0f0f1;
                padding: 3px 10px;
                border-radius: 3px;
                font-weight: 500;
            }
            .erh-tracker-count {
                background: #e7f6ec;
                color: #1e7e34;
                padding: 3px 10px;
                border-radius: 3px;
                font-weight: 500;
            }
            .erh-tracker-count--zero {
                background: #f0f0f1;
                color: #646970;
            }
            .column-actions .button {
                margin-right: 5px;
            }
            .tablenav .actions select {
                margin-right: 6px;
            }
        </style>
        <?php
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
