<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shows scraper information on product pages
 */
class HFT_Product_Scraper_Info {
    
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
    }
    
    /**
     * Add meta box to all product post types.
     */
    public function add_meta_box(): void {
        // Get all configured product post types
        $post_types = class_exists( 'HFT_Post_Type_Helper' )
            ? HFT_Post_Type_Helper::get_product_post_types()
            : ['hf_product'];

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'hft_scraper_info',
                __('Scraper Information', 'housefresh-tools'),
                [$this, 'render_meta_box'],
                $post_type,
                'side',
                'low'
            );
        }
    }
    
    /**
     * Render meta box
     */
    public function render_meta_box(WP_Post $post): void {
        global $wpdb;
        
        $tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
        $scraper_logs_table = $wpdb->prefix . 'hft_scraper_logs';
        
        // Get tracked links for this product
        $links = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$tracked_links_table} WHERE product_post_id = %d",
            $post->ID
        ), ARRAY_A);
        
        if (empty($links)) {
            echo '<p>' . __('No tracked links configured.', 'housefresh-tools') . '</p>';
            return;
        }
        
        echo '<div class="hft-scraper-info">';
        
        foreach ($links as $link) {
            $parser_name = HFT_Tracked_Links_Integration::get_parser_display_name($link);
            
            echo '<div class="scraper-info-block">';
            echo '<strong>' . esc_html($parser_name) . '</strong>';
            
            // Show last scrape info
            if ($link['last_scraped_at']) {
                $time_ago = human_time_diff(strtotime($link['last_scraped_at'])) . ' ' . __('ago', 'housefresh-tools');
                $status_class = $link['last_scrape_successful'] ? 'success' : 'error';
                
                echo '<p class="last-scrape ' . $status_class . '">';
                echo sprintf(__('Last scraped: %s', 'housefresh-tools'), $time_ago);
                echo '</p>';
                
                if ($link['consecutive_failures'] > 0) {
                    echo '<p class="failures">';
                    echo sprintf(
                        _n('%d consecutive failure', '%d consecutive failures', $link['consecutive_failures'], 'housefresh-tools'),
                        $link['consecutive_failures']
                    );
                    echo '</p>';
                }
            } else {
                echo '<p class="never-scraped">' . __('Never scraped', 'housefresh-tools') . '</p>';
            }
            
            // Show scraper health if using new system
            if (!empty($link['scraper_id'])) {
                $scraper_id = (int) $link['scraper_id'];
                $success_rate = $this->get_scraper_success_rate($scraper_id);
                if ($success_rate !== null) {
                    $rate_class = $success_rate >= 80 ? 'good' : ($success_rate >= 50 ? 'fair' : 'poor');
                    echo '<p class="success-rate ' . $rate_class . '">';
                    echo sprintf(__('Success rate: %.0f%%', 'housefresh-tools'), $success_rate);
                    echo '</p>';
                }
                
                // Link to edit scraper
                $edit_url = admin_url('admin.php?page=hft-scraper-edit&scraper=' . $scraper_id);
                echo '<p><a href="' . esc_url($edit_url) . '" target="_blank">' . __('Edit Scraper', 'housefresh-tools') . '</a></p>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add CSS
        ?>
        <style>
            .hft-scraper-info .scraper-info-block {
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #ddd;
            }
            .hft-scraper-info .scraper-info-block:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            .hft-scraper-info .last-scrape {
                margin: 5px 0;
                font-size: 12px;
            }
            .hft-scraper-info .last-scrape.success {
                color: #46b450;
            }
            .hft-scraper-info .last-scrape.error {
                color: #dc3232;
            }
            .hft-scraper-info .failures {
                color: #dc3232;
                font-size: 12px;
                margin: 5px 0;
            }
            .hft-scraper-info .success-rate {
                font-size: 12px;
                font-weight: bold;
                margin: 5px 0;
            }
            .hft-scraper-info .success-rate.good {
                color: #46b450;
            }
            .hft-scraper-info .success-rate.fair {
                color: #ffb900;
            }
            .hft-scraper-info .success-rate.poor {
                color: #dc3232;
            }
        </style>
        <?php
    }
    
    /**
     * Get scraper success rate
     */
    private function get_scraper_success_rate(int $scraper_id): ?float {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'hft_scraper_logs';
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(success) as successful
            FROM {$logs_table}
            WHERE scraper_id = %d
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $scraper_id
        ));
        
        if (!$stats || $stats->total == 0) {
            return null;
        }
        
        return ($stats->successful / $stats->total) * 100;
    }
}