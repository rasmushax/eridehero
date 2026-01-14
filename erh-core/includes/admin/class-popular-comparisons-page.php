<?php
/**
 * Popular Comparisons Admin Page.
 *
 * Full admin page showing all tracked comparison pairs with view counts,
 * curated status, and action buttons.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Database\ComparisonViews;

/**
 * Handles the Popular Comparisons admin page.
 */
class PopularComparisonsPage {

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
        add_action('admin_menu', [$this, 'add_submenu_page']);
    }

    /**
     * Add submenu page under Comparisons CPT.
     *
     * @return void
     */
    public function add_submenu_page(): void {
        add_submenu_page(
            'edit.php?post_type=comparison',
            'Popular Comparisons',
            'Popular',
            'edit_posts',
            'popular-comparisons',
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
        $category    = isset($_GET['category']) ? sanitize_text_field(wp_unslash($_GET['category'])) : '';
        $per_page    = 50;
        $paged       = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        // Get popular comparisons (using large limit for now - could add proper pagination later).
        $popular = $this->views_db->get_popular($category ?: null, 200);
        $total   = count($popular);

        // Paginate results.
        $start = ($paged - 1) * $per_page;
        $items = array_slice($popular, $start, $per_page);

        // Stats.
        $total_views = $this->views_db->get_total_views();
        $pair_count  = $this->views_db->get_pair_count();

        // Categories for filter.
        $categories = [
            ''            => 'All Categories',
            'escooter'    => 'Electric Scooters',
            'ebike'       => 'E-Bikes',
            'euc'         => 'Electric Unicycles',
            'eskateboard' => 'E-Skateboards',
            'hoverboard'  => 'Hoverboards',
        ];

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Popular Comparisons</h1>
            <hr class="wp-header-end">

            <!-- Stats Cards -->
            <div class="erh-stats-cards">
                <div class="erh-stat-card">
                    <span class="erh-stat-value"><?php echo esc_html(number_format($total_views)); ?></span>
                    <span class="erh-stat-label">Total Views</span>
                </div>
                <div class="erh-stat-card">
                    <span class="erh-stat-value"><?php echo esc_html(number_format($pair_count)); ?></span>
                    <span class="erh-stat-label">Unique Pairs</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="post_type" value="comparison">
                    <input type="hidden" name="page" value="popular-comparisons">

                    <div class="alignleft actions">
                        <select name="category">
                            <?php foreach ($categories as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($category, $value); ?>>
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
                    $total_pages = ceil($total / $per_page);
                    if ($total_pages > 1) {
                        $base_url = admin_url('edit.php?post_type=comparison&page=popular-comparisons');
                        if ($category) {
                            $base_url .= '&category=' . urlencode($category);
                        }

                        echo '<span class="pagination-links">';

                        // First page.
                        if ($paged > 1) {
                            echo '<a class="first-page button" href="' . esc_url($base_url) . '"><span class="screen-reader-text">First page</span><span aria-hidden="true">&laquo;</span></a>';
                            echo '<a class="prev-page button" href="' . esc_url($base_url . '&paged=' . ($paged - 1)) . '"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">&lsaquo;</span></a>';
                        }

                        echo '<span class="paging-input">';
                        echo '<span class="tablenav-paging-text"> ' . $paged . ' of <span class="total-pages">' . $total_pages . '</span></span>';
                        echo '</span>';

                        // Last page.
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
                    <p>No comparison data yet. Views will appear here once users start comparing products.</p>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-products">Products</th>
                            <th scope="col" class="manage-column column-category" style="width: 120px;">Category</th>
                            <th scope="col" class="manage-column column-views" style="width: 80px; text-align: center;">Views</th>
                            <th scope="col" class="manage-column column-last-viewed" style="width: 120px;">Last Viewed</th>
                            <th scope="col" class="manage-column column-status" style="width: 100px; text-align: center;">Status</th>
                            <th scope="col" class="manage-column column-actions" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item) : ?>
                            <?php
                            $product_1_id   = (int) $item['product_1_id'];
                            $product_2_id   = (int) $item['product_2_id'];
                            $product_1_name = $item['product_1_name'] ?? get_the_title($product_1_id);
                            $product_2_name = $item['product_2_name'] ?? get_the_title($product_2_id);
                            $view_count     = (int) ($item['view_count'] ?? 0);
                            $last_viewed    = $item['last_viewed'] ?? '';

                            // Get category from first product (taxonomy).
                            $terms = wp_get_post_terms($product_1_id, 'product_type', ['fields' => 'names']);
                            $cat_label = ! empty($terms) && ! is_wp_error($terms) ? $terms[0] : 'Unknown';

                            // Check for curated comparison.
                            $curated_id = $this->views_db->get_curated_comparison($product_1_id, $product_2_id);

                            // Generate URLs.
                            $product_1_url = get_edit_post_link($product_1_id);
                            $product_2_url = get_edit_post_link($product_2_id);
                            $compare_url   = home_url('/compare/' . get_post_field('post_name', $product_1_id) . '-vs-' . get_post_field('post_name', $product_2_id) . '/');
                            ?>
                            <tr>
                                <td class="column-products">
                                    <strong>
                                        <a href="<?php echo esc_url($product_1_url); ?>"><?php echo esc_html($product_1_name); ?></a>
                                    </strong>
                                    <span style="color: #999; margin: 0 5px;">vs</span>
                                    <strong>
                                        <a href="<?php echo esc_url($product_2_url); ?>"><?php echo esc_html($product_2_name); ?></a>
                                    </strong>
                                </td>
                                <td class="column-category">
                                    <span class="erh-category-badge"><?php echo esc_html($cat_label); ?></span>
                                </td>
                                <td class="column-views" style="text-align: center;">
                                    <span class="erh-view-count"><?php echo esc_html(number_format($view_count)); ?></span>
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
                                <td class="column-status" style="text-align: center;">
                                    <?php if ($curated_id) : ?>
                                        <span class="erh-badge erh-badge--curated">
                                            <span class="dashicons dashicons-yes-alt"></span> Curated
                                        </span>
                                    <?php else : ?>
                                        <span class="erh-badge erh-badge--dynamic">Dynamic</span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-actions">
                                    <a href="<?php echo esc_url($compare_url); ?>" class="button button-small" target="_blank">View</a>
                                    <?php if ($curated_id) : ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($curated_id)); ?>" class="button button-small">Edit</a>
                                    <?php else : ?>
                                        <?php
                                        $create_url = admin_url('post-new.php?post_type=comparison&product_1=' . $product_1_id . '&product_2=' . $product_2_id);
                                        ?>
                                        <a href="<?php echo esc_url($create_url); ?>" class="button button-small button-primary">Create</a>
                                    <?php endif; ?>
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
            .erh-category-badge {
                background: #f0f0f1;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
            }
            .erh-view-count {
                background: #f0f0f1;
                padding: 3px 10px;
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
            .erh-badge--dynamic {
                background: #f0f6fc;
                color: #0073aa;
            }
            .column-actions .button {
                margin-right: 5px;
            }
        </style>
        <?php
    }
}
