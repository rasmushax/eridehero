<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scraper logs viewer and analyzer
 */
class HFT_Scraper_Logs {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu'], 99); // Use high priority to ensure parent menu exists
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_hft_get_log_details', [$this, 'ajax_get_log_details']);
        add_action('wp_ajax_hft_clear_old_logs', [$this, 'ajax_clear_old_logs']);
    }
    
    /**
     * Add menu item
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'housefresh-tools-main-menu',
            __('Scraper Logs', 'housefresh-tools'),
            __('Scraper Logs', 'housefresh-tools'),
            'manage_options',
            'hft-scraper-logs',
            [$this, 'render_logs_page']
        );
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook): void {
        if ($hook !== 'housefresh-tools_page_hft-scraper-logs') {
            return;
        }
        
        wp_enqueue_style(
            'hft-scraper-logs',
            HFT_PLUGIN_URL . 'admin/css/hft-scraper-logs.css',
            [],
            HFT_VERSION
        );
        
        wp_enqueue_script(
            'hft-scraper-logs',
            HFT_PLUGIN_URL . 'admin/js/hft-scraper-logs.js',
            ['jquery'],
            HFT_VERSION,
            true
        );
        
        wp_localize_script('hft-scraper-logs', 'hftScraperLogs', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hft_scraper_logs')
        ]);
    }
    
    /**
     * Render logs page
     */
    public function render_logs_page(): void {
        $filter_scraper = isset($_GET['scraper']) ? absint($_GET['scraper']) : 0;
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $filter_days = isset($_GET['days']) ? absint($_GET['days']) : 7;
        
        ?>
        <div class="wrap">
            <h1><?php _e('Scraper Logs', 'housefresh-tools'); ?></h1>
            
            <?php $this->render_summary_cards($filter_days); ?>
            
            <div class="hft-logs-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="hft-scraper-logs">
                    
                    <label>
                        <?php _e('Scraper:', 'housefresh-tools'); ?>
                        <select name="scraper">
                            <option value=""><?php _e('All Scrapers', 'housefresh-tools'); ?></option>
                            <?php
                            $scrapers = (new HFT_Scraper_Repository())->find_all();
                            foreach ($scrapers as $scraper) {
                                printf(
                                    '<option value="%d" %s>%s</option>',
                                    $scraper->id,
                                    selected($filter_scraper, $scraper->id, false),
                                    esc_html($scraper->name)
                                );
                            }
                            ?>
                        </select>
                    </label>
                    
                    <label>
                        <?php _e('Status:', 'housefresh-tools'); ?>
                        <select name="status">
                            <option value=""><?php _e('All', 'housefresh-tools'); ?></option>
                            <option value="success" <?php selected($filter_status, 'success'); ?>><?php _e('Success', 'housefresh-tools'); ?></option>
                            <option value="failed" <?php selected($filter_status, 'failed'); ?>><?php _e('Failed', 'housefresh-tools'); ?></option>
                        </select>
                    </label>
                    
                    <label>
                        <?php _e('Time Period:', 'housefresh-tools'); ?>
                        <select name="days">
                            <option value="1" <?php selected($filter_days, 1); ?>><?php _e('Last 24 hours', 'housefresh-tools'); ?></option>
                            <option value="7" <?php selected($filter_days, 7); ?>><?php _e('Last 7 days', 'housefresh-tools'); ?></option>
                            <option value="30" <?php selected($filter_days, 30); ?>><?php _e('Last 30 days', 'housefresh-tools'); ?></option>
                        </select>
                    </label>
                    
                    <button type="submit" class="button"><?php _e('Filter', 'housefresh-tools'); ?></button>
                    <button type="button" id="clear-old-logs" class="button"><?php _e('Clear Old Logs', 'housefresh-tools'); ?></button>
                </form>
            </div>
            
            <?php $this->render_logs_table($filter_scraper, $filter_status, $filter_days); ?>
            
            <?php $this->render_problem_scrapers($filter_days); ?>
        </div>
        <?php
    }
    
    /**
     * Render summary cards
     */
    private function render_summary_cards(int $days): void {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'hft_scraper_logs';
        
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Get stats
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(success) as successful,
                SUM(NOT success) as failed,
                AVG(execution_time) as avg_time
            FROM {$logs_table}
            WHERE created_at > %s
        ", $since));
        
        $success_rate = $stats->total > 0 ? ($stats->successful / $stats->total) * 100 : 0;
        
        ?>
        <div class="hft-summary-cards">
            <div class="summary-card">
                <h3><?php _e('Total Scrapes', 'housefresh-tools'); ?></h3>
                <div class="stat"><?php echo number_format((int)$stats->total); ?></div>
                <div class="sub-stat"><?php printf(__('Last %d days', 'housefresh-tools'), $days); ?></div>
            </div>
            
            <div class="summary-card <?php echo $success_rate >= 80 ? 'good' : ($success_rate >= 50 ? 'fair' : 'poor'); ?>">
                <h3><?php _e('Success Rate', 'housefresh-tools'); ?></h3>
                <div class="stat"><?php printf('%.1f%%', $success_rate); ?></div>
                <div class="sub-stat">
                    <?php printf(
                        __('%s successful, %s failed', 'housefresh-tools'),
                        number_format((int)$stats->successful),
                        number_format((int)$stats->failed)
                    ); ?>
                </div>
            </div>
            
            <div class="summary-card">
                <h3><?php _e('Avg. Execution Time', 'housefresh-tools'); ?></h3>
                <div class="stat"><?php printf('%.2fs', $stats->avg_time ?: 0); ?></div>
                <div class="sub-stat"><?php _e('Per scrape', 'housefresh-tools'); ?></div>
            </div>
            
            <div class="summary-card">
                <h3><?php _e('Active Scrapers', 'housefresh-tools'); ?></h3>
                <div class="stat">
                    <?php
                    $active_scrapers = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(DISTINCT scraper_id) 
                        FROM {$logs_table} 
                        WHERE created_at > %s
                    ", $since));
                    echo number_format((int)$active_scrapers);
                    ?>
                </div>
                <div class="sub-stat"><?php _e('Used recently', 'housefresh-tools'); ?></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render logs table
     */
    private function render_logs_table(int $scraper_id, string $status, int $days): void {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'hft_scraper_logs';
        
        // Build query
        $where = ['1=1'];
        $where[] = $wpdb->prepare('l.created_at > %s', date('Y-m-d H:i:s', strtotime("-{$days} days")));
        
        if ($scraper_id > 0) {
            $where[] = $wpdb->prepare('scraper_id = %d', $scraper_id);
        }
        
        if ($status === 'success') {
            $where[] = 'success = 1';
        } elseif ($status === 'failed') {
            $where[] = 'success = 0';
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get logs
        $logs = $wpdb->get_results("
            SELECT l.*, s.name as scraper_name, s.domain
            FROM {$logs_table} l
            LEFT JOIN {$wpdb->prefix}hft_scrapers s ON l.scraper_id = s.id
            WHERE {$where_clause}
            ORDER BY l.created_at DESC
            LIMIT 100
        ");
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Time', 'housefresh-tools'); ?></th>
                    <th><?php _e('Scraper', 'housefresh-tools'); ?></th>
                    <th><?php _e('URL', 'housefresh-tools'); ?></th>
                    <th><?php _e('Status', 'housefresh-tools'); ?></th>
                    <th><?php _e('Execution Time', 'housefresh-tools'); ?></th>
                    <th><?php _e('Actions', 'housefresh-tools'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6"><?php _e('No logs found for the selected filters.', 'housefresh-tools'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr class="<?php echo $log->success ? 'success' : 'failed'; ?>">
                            <td><?php echo human_time_diff(strtotime($log->created_at)) . ' ' . __('ago', 'housefresh-tools'); ?></td>
                            <td><?php echo esc_html($log->scraper_name ?: __('Unknown', 'housefresh-tools')); ?></td>
                            <td>
                                <a href="<?php echo esc_url($log->url); ?>" target="_blank" class="log-url">
                                    <?php echo esc_html($this->truncate_url($log->url)); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($log->success): ?>
                                    <span class="status-badge success"><?php _e('Success', 'housefresh-tools'); ?></span>
                                <?php else: ?>
                                    <span class="status-badge failed"><?php _e('Failed', 'housefresh-tools'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $log->execution_time ? sprintf('%.2fs', $log->execution_time) : '-'; ?></td>
                            <td>
                                <button class="button button-small view-details" data-log-id="<?php echo esc_attr($log->id); ?>">
                                    <?php _e('Details', 'housefresh-tools'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Details Modal -->
        <div id="log-details-modal" style="display:none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2><?php _e('Log Details', 'housefresh-tools'); ?></h2>
                <div id="log-details-content"></div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render problem scrapers section
     */
    private function render_problem_scrapers(int $days): void {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'hft_scraper_logs';
        
        // Get scrapers with high failure rates
        $problem_scrapers = $wpdb->get_results($wpdb->prepare("
            SELECT 
                s.id,
                s.name,
                s.domain,
                COUNT(l.id) as total_attempts,
                SUM(l.success) as successful,
                SUM(NOT l.success) as failed,
                (SUM(NOT l.success) / COUNT(l.id)) * 100 as failure_rate,
                MAX(l.created_at) as last_attempt
            FROM {$wpdb->prefix}hft_scrapers s
            INNER JOIN {$logs_table} l ON s.id = l.scraper_id
            WHERE l.created_at > %s
            GROUP BY s.id
            HAVING failure_rate > 20
            ORDER BY failure_rate DESC
            LIMIT 10
        ", date('Y-m-d H:i:s', strtotime("-{$days} days"))));
        
        if (empty($problem_scrapers)) {
            return;
        }
        
        ?>
        <div class="hft-problem-scrapers">
            <h2><?php _e('Scrapers Needing Attention', 'housefresh-tools'); ?></h2>
            <p><?php _e('These scrapers have high failure rates and may need adjustment.', 'housefresh-tools'); ?></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Scraper', 'housefresh-tools'); ?></th>
                        <th><?php _e('Domain', 'housefresh-tools'); ?></th>
                        <th><?php _e('Failure Rate', 'housefresh-tools'); ?></th>
                        <th><?php _e('Attempts', 'housefresh-tools'); ?></th>
                        <th><?php _e('Last Attempt', 'housefresh-tools'); ?></th>
                        <th><?php _e('Actions', 'housefresh-tools'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($problem_scrapers as $scraper): ?>
                        <tr>
                            <td><?php echo esc_html($scraper->name); ?></td>
                            <td><?php echo esc_html($scraper->domain); ?></td>
                            <td>
                                <span class="failure-rate <?php echo $scraper->failure_rate > 50 ? 'critical' : 'warning'; ?>">
                                    <?php printf('%.0f%%', $scraper->failure_rate); ?>
                                </span>
                            </td>
                            <td>
                                <?php printf(
                                    __('%d total (%d failed)', 'housefresh-tools'),
                                    $scraper->total_attempts,
                                    $scraper->failed
                                ); ?>
                            </td>
                            <td><?php echo human_time_diff(strtotime($scraper->last_attempt)) . ' ' . __('ago', 'housefresh-tools'); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=hft-scraper-edit&scraper=' . $scraper->id); ?>" 
                                   class="button button-small">
                                    <?php _e('Edit', 'housefresh-tools'); ?>
                                </a>
                                <a href="<?php echo add_query_arg(['page' => 'hft-scraper-logs', 'scraper' => $scraper->id, 'status' => 'failed'], admin_url('admin.php')); ?>" 
                                   class="button button-small">
                                    <?php _e('View Failures', 'housefresh-tools'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * AJAX: Get log details
     */
    public function ajax_get_log_details(): void {
        check_ajax_referer('hft_scraper_logs', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $log_id = isset($_POST['log_id']) ? absint($_POST['log_id']) : 0;
        if (!$log_id) {
            wp_die();
        }
        
        global $wpdb;
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT l.*, s.name as scraper_name 
            FROM {$wpdb->prefix}hft_scraper_logs l
            LEFT JOIN {$wpdb->prefix}hft_scrapers s ON l.scraper_id = s.id
            WHERE l.id = %d",
            $log_id
        ));
        
        if (!$log) {
            wp_die();
        }
        
        $extracted_data = json_decode($log->extracted_data, true);
        
        ob_start();
        ?>
        <table class="form-table">
            <tr>
                <th><?php _e('Scraper', 'housefresh-tools'); ?></th>
                <td><?php echo esc_html($log->scraper_name ?: __('Unknown', 'housefresh-tools')); ?></td>
            </tr>
            <tr>
                <th><?php _e('URL', 'housefresh-tools'); ?></th>
                <td>
                    <a href="<?php echo esc_url($log->url); ?>" target="_blank">
                        <?php echo esc_html($log->url); ?>
                    </a>
                </td>
            </tr>
            <tr>
                <th><?php _e('Time', 'housefresh-tools'); ?></th>
                <td><?php echo esc_html($log->created_at); ?></td>
            </tr>
            <tr>
                <th><?php _e('Status', 'housefresh-tools'); ?></th>
                <td>
                    <?php if ($log->success): ?>
                        <span class="status-badge success"><?php _e('Success', 'housefresh-tools'); ?></span>
                    <?php else: ?>
                        <span class="status-badge failed"><?php _e('Failed', 'housefresh-tools'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php _e('Execution Time', 'housefresh-tools'); ?></th>
                <td><?php echo $log->execution_time ? sprintf('%.3f seconds', $log->execution_time) : '-'; ?></td>
            </tr>
            <?php if ($log->error_message): ?>
                <tr>
                    <th><?php _e('Error', 'housefresh-tools'); ?></th>
                    <td class="error-message"><?php echo esc_html($log->error_message); ?></td>
                </tr>
            <?php endif; ?>
        </table>
        
        <?php if ($extracted_data): ?>
            <h3><?php _e('Extracted Data', 'housefresh-tools'); ?></h3>
            <pre><?php echo esc_html(json_encode($extracted_data, JSON_PRETTY_PRINT)); ?></pre>
        <?php endif; ?>
        <?php
        
        wp_send_json_success(['html' => ob_get_clean()]);
    }
    
    /**
     * AJAX: Clear old logs
     */
    public function ajax_clear_old_logs(): void {
        check_ajax_referer('hft_scraper_logs', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        global $wpdb;
        $logs_table = $wpdb->prefix . 'hft_scraper_logs';
        
        // Delete logs older than 30 days
        $deleted = $wpdb->query("
            DELETE FROM {$logs_table}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        wp_send_json_success([
            'message' => sprintf(
                __('%d old log entries deleted.', 'housefresh-tools'),
                $deleted
            )
        ]);
    }
    
    /**
     * Truncate URL for display
     */
    private function truncate_url(string $url, int $length = 50): string {
        if (strlen($url) <= $length) {
            return $url;
        }
        
        $parsed = parse_url($url);
        $display = $parsed['host'] ?? '';
        
        if (isset($parsed['path']) && strlen($parsed['path']) > 1) {
            $path = $parsed['path'];
            if (strlen($display . $path) > $length) {
                $path = '...' . substr($path, -($length - strlen($display) - 3));
            }
            $display .= $path;
        }
        
        return $display;
    }
}