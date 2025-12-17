<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Automated alerts for scraper issues
 */
class HFT_Scraper_Alerts {
    
    /**
     * Check for scrapers that need attention
     */
    public static function check_scraper_health(): void {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'hft_scraper_logs';
        $tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
        
        // Find scrapers with high recent failure rates
        $problem_scrapers = $wpdb->get_results("
            SELECT 
                s.id,
                s.name,
                s.domain,
                COUNT(l.id) as recent_attempts,
                SUM(NOT l.success) as recent_failures,
                (SUM(NOT l.success) / COUNT(l.id)) * 100 as failure_rate
            FROM {$wpdb->prefix}hft_scrapers s
            INNER JOIN {$logs_table} l ON s.id = l.scraper_id
            WHERE l.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY s.id
            HAVING recent_attempts >= 5 AND failure_rate > 50
        ");
        
        if (!empty($problem_scrapers)) {
            // Store alert in transient for admin notice
            set_transient('hft_scraper_alerts', $problem_scrapers, DAY_IN_SECONDS);
        }
        
        // Find links with many consecutive failures
        $failing_links = $wpdb->get_results("
            SELECT 
                tl.*,
                p.post_title,
                s.name as scraper_name
            FROM {$tracked_links_table} tl
            LEFT JOIN {$wpdb->posts} p ON tl.product_post_id = p.ID
            LEFT JOIN {$wpdb->prefix}hft_scrapers s ON tl.scraper_id = s.id
            WHERE tl.consecutive_failures >= 10
        ");
        
        if (!empty($failing_links)) {
            set_transient('hft_failing_links', $failing_links, DAY_IN_SECONDS);
        }
    }
    
    /**
     * Display admin notices for alerts
     */
    public static function show_admin_notices(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Show scraper alerts
        $scraper_alerts = get_transient('hft_scraper_alerts');
        if ($scraper_alerts) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php _e('Scraper Alert:', 'housefresh-tools'); ?></strong>
                    <?php
                    printf(
                        _n(
                            '%d scraper is experiencing high failure rates.',
                            '%d scrapers are experiencing high failure rates.',
                            count($scraper_alerts),
                            'housefresh-tools'
                        ),
                        count($scraper_alerts)
                    );
                    ?>
                    <a href="<?php echo admin_url('admin.php?page=hft-scraper-logs'); ?>">
                        <?php _e('View Logs', 'housefresh-tools'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
        
        // Show failing links alert
        $failing_links = get_transient('hft_failing_links');
        if ($failing_links) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <strong><?php _e('Tracking Alert:', 'housefresh-tools'); ?></strong>
                    <?php
                    printf(
                        _n(
                            '%d product tracking link has failed multiple times.',
                            '%d product tracking links have failed multiple times.',
                            count($failing_links),
                            'housefresh-tools'
                        ),
                        count($failing_links)
                    );
                    ?>
                    <?php _e('These products may have outdated prices.', 'housefresh-tools'); ?>
                </p>
            </div>
            <?php
        }
    }
}

// Hook into admin notices
add_action('admin_notices', ['HFT_Scraper_Alerts', 'show_admin_notices']);

// Schedule health check
add_action('hft_scraper_health_check', ['HFT_Scraper_Alerts', 'check_scraper_health']);

// Ensure health check is scheduled (check only once per hour to avoid performance overhead)
add_action('admin_init', function() {
    $transient_key = 'hft_health_cron_check_done';
    if (get_transient($transient_key)) {
        return; // Already checked recently
    }

    // Set transient to prevent checking again for 1 hour
    set_transient($transient_key, true, HOUR_IN_SECONDS);

    // Schedule if not already scheduled
    if (!wp_next_scheduled('hft_scraper_health_check')) {
        wp_schedule_event(time(), 'twicedaily', 'hft_scraper_health_check');
    }
});