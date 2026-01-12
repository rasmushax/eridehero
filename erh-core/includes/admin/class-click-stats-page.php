<?php
/**
 * Click Stats Admin Page - Displays click tracking statistics.
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
        $summary = $this->stats->get_summary($days, $exclude_bots);
        $bot_stats = $this->stats->get_bot_stats($days);
        $top_referrers = $this->stats->get_top_referrers($days, 15, $exclude_bots);
        $top_products = $this->stats->get_product_clicks($days, 15, $exclude_bots);
        $retailer_clicks = $this->stats->get_retailer_clicks($days, 15, $exclude_bots);
        $geo_clicks = $this->stats->get_geo_clicks($days, $exclude_bots);
        $daily_trend = $this->stats->get_daily_trend($days, $exclude_bots);
        $device_dist = $this->stats->get_device_distribution($days, $exclude_bots);

        // Prepare chart data.
        $chart_labels = [];
        $chart_data = [];
        foreach ($daily_trend as $day) {
            $chart_labels[] = gmdate('M j', strtotime($day['date']));
            $chart_data[] = (int) $day['click_count'];
        }

        ?>
        <div class="wrap erh-click-stats">
            <h1><?php esc_html_e('Click Statistics', 'erh-core'); ?></h1>

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

            <!-- Summary Cards -->
            <div class="erh-cs-summary">
                <div class="erh-cs-card">
                    <span class="erh-cs-card-value"><?php echo esc_html(number_format($summary['total_clicks'])); ?></span>
                    <span class="erh-cs-card-label"><?php esc_html_e('Total Clicks', 'erh-core'); ?></span>
                </div>
                <div class="erh-cs-card">
                    <span class="erh-cs-card-value"><?php echo esc_html(number_format($summary['unique_products'])); ?></span>
                    <span class="erh-cs-card-label"><?php esc_html_e('Products Clicked', 'erh-core'); ?></span>
                </div>
                <div class="erh-cs-card">
                    <span class="erh-cs-card-value"><?php echo esc_html($summary['mobile_percent']); ?>%</span>
                    <span class="erh-cs-card-label"><?php esc_html_e('Mobile/Tablet', 'erh-core'); ?></span>
                </div>
                <div class="erh-cs-card">
                    <span class="erh-cs-card-value"><?php echo esc_html(number_format($summary['unique_pages'])); ?></span>
                    <span class="erh-cs-card-label"><?php esc_html_e('Referrer Pages', 'erh-core'); ?></span>
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

            <div class="erh-cs-grid">
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

                <!-- Top Products -->
                <div class="erh-cs-section">
                    <h2><?php esc_html_e('Top Products', 'erh-core'); ?></h2>
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
