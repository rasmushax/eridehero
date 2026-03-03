<?php
/**
 * Click Stats Admin Page - Business intelligence dashboard for affiliate clicks.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

use ERH\Tracking\ClickStats;

/**
 * Admin page for viewing click tracking statistics.
 */
class ClickStatsPage {

    /**
     * Page slug.
     */
    public const PAGE_SLUG = 'erh-click-stats';

    /**
     * Click stats instance.
     *
     * @var ClickStats
     */
    private ClickStats $stats;

    /**
     * Available time periods.
     *
     * @var array
     */
    private const TIME_PERIODS = [
        7   => 'Last 7 days',
        14  => 'Last 14 days',
        30  => 'Last 30 days',
        90  => 'Last 90 days',
        180 => 'Last 6 months',
        365 => 'Last year',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        $this->stats = new ClickStats();
    }

    /**
     * Register the admin page.
     *
     * @return void
     */
    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Add menu page.
     *
     * @return void
     */
    public function add_menu_page(): void {
        add_menu_page(
            __('Click Stats', 'erh-core'),
            __('Click Stats', 'erh-core'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page'],
            'dashicons-chart-bar',
            30
        );
    }

    /**
     * Enqueue page assets.
     *
     * @param string $hook The current admin page.
     * @return void
     */
    public function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }

        // Chart.js from CDN (admin only, performance not critical here).
        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
            [],
            '4.4.1',
            true
        );

        wp_enqueue_style(
            'erh-click-stats',
            ERH_PLUGIN_URL . 'assets/css/click-stats.css',
            [],
            ERH_VERSION
        );
    }

    /**
     * Render the stats page.
     *
     * @return void
     */
    public function render_page(): void {
        // Get parameters.
        $days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
        $days = array_key_exists($days, self::TIME_PERIODS) ? $days : 30;

        $exclude_bots = !isset($_GET['include_bots']) || $_GET['include_bots'] !== '1';

        // Get all stats data.
        $summary          = $this->stats->get_summary($days, $exclude_bots);
        $prev_summary     = $this->stats->get_previous_period_summary($days, $exclude_bots);
        $bot_stats        = $this->stats->get_bot_stats($days);
        $velocity         = $this->stats->get_click_velocity($days, $exclude_bots);
        $avg_ctr          = $this->stats->get_average_product_ctr($days, $exclude_bots);
        $top_referrers    = $this->stats->get_top_referrers($days, 15, $exclude_bots);
        $top_products     = $this->stats->get_product_clicks($days, 15, $exclude_bots);
        $retailer_clicks  = $this->stats->get_retailer_clicks($days, 15, $exclude_bots);
        $geo_clicks       = $this->stats->get_geo_clicks($days, $exclude_bots);
        $daily_trend      = $this->stats->get_daily_trend($days, $exclude_bots);
        $device_dist      = $this->stats->get_device_distribution($days, $exclude_bots);
        $content_types    = $this->stats->get_content_type_clicks($days, $exclude_bots);
        $conversion_funnel = $this->stats->get_product_conversion_funnel($days, 15, $exclude_bots);
        $leaky_buckets    = $this->stats->get_leaky_buckets($days, 10, $exclude_bots);
        $retailer_pref    = $this->stats->get_retailer_preference($days, 10, $exclude_bots);

        // Calculate deltas.
        $clicks_delta   = $this->calculate_delta($summary['total_clicks'], $prev_summary['total_clicks']);
        $products_delta = $this->calculate_delta($summary['unique_products'], $prev_summary['unique_products']);
        $velocity_delta = $this->calculate_delta($velocity['current_daily_avg'], $velocity['previous_daily_avg']);

        // Prepare chart data.
        $chart_labels = [];
        $chart_data = [];
        foreach ($daily_trend as $day) {
            $chart_labels[] = gmdate('M j', strtotime($day['date']));
            $chart_data[] = (int) $day['click_count'];
        }

        ?>
        <div class="wrap erh-click-stats">
            <h1><?php esc_html_e('Click Intelligence', 'erh-core'); ?></h1>

            <div class="erh-cs-filters">
                <div class="erh-cs-filter-group">
                    <label for="days-filter"><?php esc_html_e('Time Period:', 'erh-core'); ?></label>
                    <select id="days-filter" onchange="erhUpdateFilters()">
                        <?php foreach (self::TIME_PERIODS as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($days, $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="erh-cs-filter-group">
                    <label>
                        <input type="checkbox" id="include-bots" <?php checked(!$exclude_bots); ?> onchange="erhUpdateFilters()">
                        <?php esc_html_e('Include bot traffic', 'erh-core'); ?>
                    </label>
                    <?php if ($bot_stats['bot_count'] > 0) : ?>
                        <span class="erh-cs-bot-info" title="<?php echo esc_attr(sprintf('%d bot clicks detected (%s%%)', $bot_stats['bot_count'], $bot_stats['bot_percent'])); ?>">
                            (<?php echo esc_html($bot_stats['bot_count']); ?> bots filtered)
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Summary Cards with Deltas -->
            <div class="erh-cs-summary">
                <div class="erh-cs-card">
                    <span class="erh-cs-card-value"><?php echo esc_html(number_format($summary['total_clicks'])); ?></span>
                    <?php $this->render_delta_badge($clicks_delta); ?>
                    <span class="erh-cs-card-label"><?php esc_html_e('Total Clicks', 'erh-core'); ?></span>
                </div>
                <div class="erh-cs-card">
                    <span class="erh-cs-card-value"><?php echo esc_html(number_format($summary['unique_products'])); ?></span>
                    <?php $this->render_delta_badge($products_delta); ?>
                    <span class="erh-cs-card-label"><?php esc_html_e('Products Clicked', 'erh-core'); ?></span>
                </div>
                <div class="erh-cs-card">
                    <span class="erh-cs-card-value"><?php echo esc_html($avg_ctr); ?>%</span>
                    <span class="erh-cs-card-label"><?php esc_html_e('Avg Product CTR', 'erh-core'); ?></span>
                    <span class="erh-cs-card-hint"><?php esc_html_e('Views to clicks', 'erh-core'); ?></span>
                </div>
                <div class="erh-cs-card">
                    <span class="erh-cs-card-value"><?php echo esc_html($velocity['current_daily_avg']); ?></span>
                    <?php $this->render_delta_badge($velocity_delta); ?>
                    <span class="erh-cs-card-label"><?php esc_html_e('Clicks/Day', 'erh-core'); ?></span>
                </div>
                <div class="erh-cs-card">
                    <span class="erh-cs-card-value"><?php echo esc_html($summary['mobile_percent']); ?>%</span>
                    <span class="erh-cs-card-label"><?php esc_html_e('Mobile/Tablet', 'erh-core'); ?></span>
                </div>
                <div class="erh-cs-card">
                    <?php
                    $top_product = !empty($conversion_funnel) ? $conversion_funnel[0] : null;
                    if ($top_product) :
                    ?>
                        <span class="erh-cs-card-value"><?php echo esc_html($top_product['ctr']); ?>%</span>
                        <span class="erh-cs-card-label"><?php esc_html_e('Best CTR', 'erh-core'); ?></span>
                        <span class="erh-cs-card-hint"><?php echo esc_html($top_product['product_name']); ?></span>
                    <?php else : ?>
                        <span class="erh-cs-card-value">-</span>
                        <span class="erh-cs-card-label"><?php esc_html_e('Best CTR', 'erh-core'); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Daily Trend Chart -->
            <div class="erh-cs-section erh-cs-section-full erh-cs-chart-section">
                <h2><?php esc_html_e('Daily Trend', 'erh-core'); ?></h2>
                <?php if (empty($daily_trend)) : ?>
                    <p class="erh-cs-empty"><?php esc_html_e('No click data yet.', 'erh-core'); ?></p>
                <?php else : ?>
                    <div class="erh-cs-chart-container">
                        <canvas id="erh-daily-trend-chart"></canvas>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Product Conversion Funnel -->
            <div class="erh-cs-section erh-cs-section-full">
                <h2>
                    <?php esc_html_e('Product Conversion Funnel', 'erh-core'); ?>
                    <span class="erh-cs-section-hint"><?php esc_html_e('Which products convert page views into affiliate clicks', 'erh-core'); ?></span>
                </h2>
                <?php if (empty($conversion_funnel)) : ?>
                    <p class="erh-cs-empty"><?php esc_html_e('Not enough view data yet. Products need at least 5 views to appear.', 'erh-core'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Product', 'erh-core'); ?></th>
                                <th class="num"><?php esc_html_e('Views', 'erh-core'); ?></th>
                                <th class="num"><?php esc_html_e('Clicks', 'erh-core'); ?></th>
                                <th class="num"><?php esc_html_e('CTR', 'erh-core'); ?></th>
                                <th class="erh-cs-bar-col"><?php esc_html_e('Conversion', 'erh-core'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $max_ctr = max(array_column($conversion_funnel, 'ctr'));
                            foreach ($conversion_funnel as $row) :
                                $bar_width = $max_ctr > 0 ? ((float) $row['ctr'] / $max_ctr) * 100 : 0;
                                $ctr_class = $this->get_ctr_class((float) $row['ctr'], $avg_ctr);
                            ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo esc_url(get_edit_post_link((int) $row['product_id'])); ?>">
                                            <?php echo esc_html($row['product_name'] ?: 'Product #' . $row['product_id']); ?>
                                        </a>
                                    </td>
                                    <td class="num"><?php echo esc_html(number_format((int) $row['views'])); ?></td>
                                    <td class="num"><?php echo esc_html(number_format((int) $row['clicks'])); ?></td>
                                    <td class="num erh-cs-ctr <?php echo esc_attr($ctr_class); ?>"><?php echo esc_html($row['ctr']); ?>%</td>
                                    <td class="erh-cs-bar-col">
                                        <div class="erh-cs-bar-bg">
                                            <div class="erh-cs-bar <?php echo esc_attr($ctr_class); ?>" style="width: <?php echo esc_attr($bar_width); ?>%"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Leaky Buckets + Content Type (side by side) -->
            <div class="erh-cs-grid">
                <!-- Leaky Bucket Alert -->
                <div class="erh-cs-section">
                    <h2>
                        <?php esc_html_e('Leaky Buckets', 'erh-core'); ?>
                        <span class="erh-cs-section-hint"><?php esc_html_e('High traffic, low clicks — fix these first', 'erh-core'); ?></span>
                    </h2>
                    <?php if (empty($leaky_buckets['products'])) : ?>
                        <p class="erh-cs-empty"><?php esc_html_e('No leaky buckets detected. All products are converting at or above average.', 'erh-core'); ?></p>
                    <?php else : ?>
                        <p class="erh-cs-context">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %s: average CTR */
                                __('Avg CTR: %s%%. These products are below average:', 'erh-core'),
                                $leaky_buckets['avg_ctr']
                            ));
                            ?>
                        </p>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Product', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Views', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('CTR', 'erh-core'); ?></th>
                                    <th class="num" title="<?php esc_attr_e('Estimated clicks missed vs average CTR', 'erh-core'); ?>"><?php esc_html_e('Missed', 'erh-core'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaky_buckets['products'] as $row) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link((int) $row['product_id'])); ?>">
                                                <?php echo esc_html($row['product_name'] ?: 'Product #' . $row['product_id']); ?>
                                            </a>
                                        </td>
                                        <td class="num"><?php echo esc_html(number_format((int) $row['views'])); ?></td>
                                        <td class="num erh-cs-ctr erh-cs-ctr-low"><?php echo esc_html($row['ctr']); ?>%</td>
                                        <td class="num erh-cs-missed">~<?php echo esc_html(number_format((int) $row['missed_clicks'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Content Type Performance -->
                <div class="erh-cs-section">
                    <h2>
                        <?php esc_html_e('Content Type Performance', 'erh-core'); ?>
                        <span class="erh-cs-section-hint"><?php esc_html_e('Which content formats drive the most clicks', 'erh-core'); ?></span>
                    </h2>
                    <?php if (empty($content_types)) : ?>
                        <p class="erh-cs-empty"><?php esc_html_e('No click data yet.', 'erh-core'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Type', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Clicks', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Pages', 'erh-core'); ?></th>
                                    <th class="num" title="<?php esc_attr_e('Average clicks per page of this type', 'erh-core'); ?>"><?php esc_html_e('Clicks/Page', 'erh-core'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_content_clicks = array_sum(array_column($content_types, 'clicks'));
                                foreach ($content_types as $row) :
                                    $share = $total_content_clicks > 0 ? round(($row['clicks'] / $total_content_clicks) * 100) : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html($row['label']); ?>
                                            <span class="erh-cs-share"><?php echo esc_html($share); ?>%</span>
                                        </td>
                                        <td class="num"><?php echo esc_html(number_format($row['clicks'])); ?></td>
                                        <td class="num"><?php echo esc_html(number_format($row['pages'])); ?></td>
                                        <td class="num"><strong><?php echo esc_html($row['clicks_per_page']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Retailer Preference + Top Referrer Pages -->
            <div class="erh-cs-grid">
                <!-- Retailer Preference -->
                <div class="erh-cs-section">
                    <h2>
                        <?php esc_html_e('Retailer Preference', 'erh-core'); ?>
                        <span class="erh-cs-section-hint"><?php esc_html_e('When users have choices, who wins', 'erh-core'); ?></span>
                    </h2>
                    <?php if (empty($retailer_pref)) : ?>
                        <p class="erh-cs-empty"><?php esc_html_e('Not enough multi-retailer click data yet.', 'erh-core'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Retailer', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Clicks', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Share', 'erh-core'); ?></th>
                                    <th class="erh-cs-bar-col"><?php esc_html_e('Preference', 'erh-core'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $max_share = !empty($retailer_pref) ? max(array_column($retailer_pref, 'share')) : 0;
                                foreach ($retailer_pref as $row) :
                                    $bar_width = $max_share > 0 ? ($row['share'] / $max_share) * 100 : 0;
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($row['retailer']); ?></td>
                                        <td class="num"><?php echo esc_html(number_format($row['clicks'])); ?></td>
                                        <td class="num"><?php echo esc_html($row['share']); ?>%</td>
                                        <td class="erh-cs-bar-col">
                                            <div class="erh-cs-bar-bg">
                                                <div class="erh-cs-bar erh-cs-bar-blue" style="width: <?php echo esc_attr($bar_width); ?>%"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Top Referrer Pages -->
                <div class="erh-cs-section">
                    <h2><?php esc_html_e('Top Referrer Pages', 'erh-core'); ?></h2>
                    <?php if (empty($top_referrers)) : ?>
                        <p class="erh-cs-empty"><?php esc_html_e('No click data yet.', 'erh-core'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Page', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Clicks', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Products', 'erh-core'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_referrers as $row) : ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $link_url = set_url_scheme('//' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $row['referrer_path']);
                                            $display_path = $this->get_relative_path($row['referrer_path']);
                                            ?>
                                            <a href="<?php echo esc_url($link_url); ?>" target="_blank">
                                                <?php echo esc_html($display_path); ?>
                                            </a>
                                        </td>
                                        <td class="num"><?php echo esc_html(number_format((int) $row['click_count'])); ?></td>
                                        <td class="num"><?php echo esc_html(number_format((int) $row['unique_products'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top Products + Retailers + Geo + Device -->
            <div class="erh-cs-grid">
                <!-- Top Products -->
                <div class="erh-cs-section">
                    <h2><?php esc_html_e('Top Products by Clicks', 'erh-core'); ?></h2>
                    <?php if (empty($top_products)) : ?>
                        <p class="erh-cs-empty"><?php esc_html_e('No click data yet.', 'erh-core'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Product', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Clicks', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Retailers', 'erh-core'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $row) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(get_edit_post_link($row['product_id'])); ?>">
                                                <?php echo esc_html($row['product_name'] ?: 'Product #' . $row['product_id']); ?>
                                            </a>
                                        </td>
                                        <td class="num"><?php echo esc_html(number_format((int) $row['click_count'])); ?></td>
                                        <td class="num"><?php echo esc_html($row['unique_retailers']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Retailers -->
                <div class="erh-cs-section">
                    <h2><?php esc_html_e('By Retailer', 'erh-core'); ?></h2>
                    <?php if (empty($retailer_clicks)) : ?>
                        <p class="erh-cs-empty"><?php esc_html_e('No click data yet.', 'erh-core'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Retailer', 'erh-core'); ?></th>
                                    <th><?php esc_html_e('Geo', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Clicks', 'erh-core'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($retailer_clicks as $row) : ?>
                                    <tr>
                                        <td><?php echo esc_html($row['retailer']); ?></td>
                                        <td><?php echo esc_html($row['geo_target'] ?: '-'); ?></td>
                                        <td class="num"><?php echo esc_html(number_format((int) $row['click_count'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Geo Distribution -->
                <div class="erh-cs-section erh-cs-section-small">
                    <h2><?php esc_html_e('By User Geo', 'erh-core'); ?></h2>
                    <?php if (empty($geo_clicks)) : ?>
                        <p class="erh-cs-empty"><?php esc_html_e('No click data yet.', 'erh-core'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Region', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Clicks', 'erh-core'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($geo_clicks as $row) : ?>
                                    <tr>
                                        <td><?php echo esc_html($this->get_geo_label($row['geo'])); ?></td>
                                        <td class="num"><?php echo esc_html(number_format((int) $row['click_count'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Device Distribution -->
                <div class="erh-cs-section erh-cs-section-small">
                    <h2><?php esc_html_e('By Device', 'erh-core'); ?></h2>
                    <?php if (empty($device_dist)) : ?>
                        <p class="erh-cs-empty"><?php esc_html_e('No click data yet.', 'erh-core'); ?></p>
                    <?php else : ?>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Device', 'erh-core'); ?></th>
                                    <th class="num"><?php esc_html_e('Clicks', 'erh-core'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($device_dist as $row) : ?>
                                    <tr>
                                        <td><?php echo esc_html(ucfirst($row['device_type'])); ?></td>
                                        <td class="num"><?php echo esc_html(number_format((int) $row['click_count'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        function erhUpdateFilters() {
            const days = document.getElementById('days-filter').value;
            const includeBots = document.getElementById('include-bots').checked;
            let url = '?page=<?php echo esc_js(self::PAGE_SLUG); ?>&days=' + days;
            if (includeBots) {
                url += '&include_bots=1';
            }
            window.location.href = url;
        }

        <?php if (!empty($daily_trend)) : ?>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('erh-daily-trend-chart');
            if (!ctx || typeof Chart === 'undefined') return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo wp_json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Clicks',
                        data: <?php echo wp_json_encode($chart_data); ?>,
                        borderColor: '#2271b1',
                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y.toLocaleString() + ' clicks';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });
        });
        <?php endif; ?>
        </script>
        <?php
    }

    /**
     * Calculate percentage delta between current and previous period.
     *
     * @param float|int $current  Current period value.
     * @param float|int $previous Previous period value.
     * @return array Delta info with percent and direction.
     */
    private function calculate_delta($current, $previous): array {
        if ($previous == 0) {
            return [
                'percent'   => $current > 0 ? 100 : 0,
                'direction' => $current > 0 ? 'up' : 'neutral',
                'show'      => $current > 0,
            ];
        }

        $percent = round((($current - $previous) / $previous) * 100);

        return [
            'percent'   => abs($percent),
            'direction' => $percent > 0 ? 'up' : ($percent < 0 ? 'down' : 'neutral'),
            'show'      => true,
        ];
    }

    /**
     * Render a delta badge on a summary card.
     *
     * @param array $delta Delta data from calculate_delta().
     * @return void
     */
    private function render_delta_badge(array $delta): void {
        if (!$delta['show']) {
            return;
        }

        $arrow = $delta['direction'] === 'up' ? '&#9650;' : ($delta['direction'] === 'down' ? '&#9660;' : '');
        $class = 'erh-cs-delta erh-cs-delta-' . $delta['direction'];
        ?>
        <span class="<?php echo esc_attr($class); ?>">
            <?php echo $arrow; // Already escaped HTML entity. ?>
            <?php echo esc_html($delta['percent']); ?>%
            <span class="erh-cs-delta-label"><?php esc_html_e('vs prev period', 'erh-core'); ?></span>
        </span>
        <?php
    }

    /**
     * Get CSS class for CTR value (color coding relative to average).
     *
     * @param float $ctr     The CTR value.
     * @param float $avg_ctr The average CTR for comparison.
     * @return string CSS class name.
     */
    private function get_ctr_class(float $ctr, float $avg_ctr): string {
        if ($avg_ctr <= 0) {
            return '';
        }

        if ($ctr >= $avg_ctr * 1.5) {
            return 'erh-cs-ctr-high';
        }

        if ($ctr >= $avg_ctr) {
            return 'erh-cs-ctr-good';
        }

        if ($ctr >= $avg_ctr * 0.5) {
            return 'erh-cs-ctr-mid';
        }

        return 'erh-cs-ctr-low';
    }

    /**
     * Get path relative to WordPress home (strips subdirectory if present).
     *
     * @param string $path The full path.
     * @return string Path relative to site root.
     */
    private function get_relative_path(string $path): string {
        $home_path = wp_parse_url(home_url(), PHP_URL_PATH) ?: '';

        if ($home_path && strpos($path, $home_path) === 0) {
            $path = substr($path, strlen($home_path));
        }

        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }

    /**
     * Get human-readable geo label.
     *
     * @param string|null $geo The geo code.
     * @return string The label.
     */
    private function get_geo_label(?string $geo): string {
        $labels = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'EU' => 'Europe',
            'CA' => 'Canada',
            'AU' => 'Australia',
        ];

        return $labels[$geo] ?? ($geo ?: 'Unknown');
    }
}
