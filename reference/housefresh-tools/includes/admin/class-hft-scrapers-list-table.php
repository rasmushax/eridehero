<?php
declare(strict_types=1);

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Load WP_List_Table if not already loaded
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List table for displaying scrapers.
 */
class HFT_Scrapers_List_Table extends WP_List_Table {
    
    /**
     * @var HFT_Scraper_Repository
     */
    private HFT_Scraper_Repository $repository;
    
    /**
     * Constructor.
     *
     * @param HFT_Scraper_Repository $repository
     */
    public function __construct(HFT_Scraper_Repository $repository) {
        $this->repository = $repository;
        
        parent::__construct([
            'singular' => 'scraper',
            'plural' => 'scrapers',
            'ajax' => false
        ]);
    }
    
    /**
     * Get columns.
     *
     * @return array
     */
    public function get_columns(): array {
        return [
            'cb' => '<input type="checkbox" />',
            'logo' => 'Logo',
            'domain' => 'Domain',
            'name' => 'Name',
            'rules' => 'Rules',
            'base_parser' => 'Base Parser',
            'last_used' => 'Last Used',
            'success_rate' => 'Success Rate',
        ];
    }
    
    /**
     * Get sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns(): array {
        return [
            'domain' => ['domain', false],
            'name' => ['name', false],
            'last_used' => ['last_used', false],
            'success_rate' => ['success_rate', false],
        ];
    }
    
    /**
     * Get default primary column name.
     *
     * @return string
     */
    protected function get_default_primary_column_name(): string {
        return 'domain';
    }
    
    /**
     * Render checkbox column.
     *
     * @param HFT_Scraper $item
     * @return string
     */
    public function column_cb($item): string {
        return sprintf(
            '<input type="checkbox" name="scrapers[]" value="%s" />',
            $item->id
        );
    }

    /**
     * Render logo column.
     *
     * @param HFT_Scraper $item
     * @return string
     */
    public function column_logo($item): string {
        if (!empty($item->logo_attachment_id)) {
            $logo_url = wp_get_attachment_image_url($item->logo_attachment_id, 'thumbnail');
            if ($logo_url) {
                return sprintf(
                    '<img src="%s" alt="%s" class="hft-scraper-logo" style="width: 32px; height: 32px; object-fit: contain; border-radius: 4px;">',
                    esc_url($logo_url),
                    esc_attr($item->name)
                );
            }
        }

        // Return a placeholder icon if no logo
        return '<span class="dashicons dashicons-store" style="color: #999; font-size: 24px; width: 32px; height: 32px; line-height: 32px;"></span>';
    }

    /**
     * Render domain column.
     *
     * @param HFT_Scraper $item
     * @return string
     */
    public function column_domain($item): string {
        $edit_url = add_query_arg([
            'page' => 'hft-scraper-edit',
            'scraper_id' => $item->id
        ], admin_url('admin.php'));
        
        $actions = [
            'edit' => sprintf('<a href="%s">Edit</a>', esc_url($edit_url)),
            'test' => sprintf(
                '<a href="#" class="hft-test-scraper" data-scraper-id="%d">Test</a>',
                $item->id
            ),
            'delete' => sprintf(
                '<a href="%s" onclick="return confirm(\'%s\');">Delete</a>',
                wp_nonce_url(
                    add_query_arg([
                        'page' => 'hft-scrapers',
                        'action' => 'delete',
                        'scraper_id' => $item->id
                    ], admin_url('admin.php')),
                    'hft_scraper_action',
                    'hft_scraper_action_nonce'
                ),
                esc_js('Are you sure you want to delete this scraper?')
            ),
        ];
        
        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url($edit_url),
            esc_html($item->domain),
            $this->row_actions($actions)
        );
    }
    
    /**
     * Render name column.
     *
     * @param HFT_Scraper $item
     * @return string
     */
    public function column_name($item): string {
        return esc_html($item->name);
    }
    
    /**
     * Render rules column.
     *
     * @param HFT_Scraper $item
     * @return string
     */
    public function column_rules($item): string {
        $rules = $this->repository->find_rules_by_scraper_id($item->id);
        $active_rules = array_filter($rules, fn($rule) => $rule->is_active);
        
        return sprintf(
            '<span class="hft-rules-count">%d</span>',
            count($active_rules)
        );
    }
    
    /**
     * Render base parser column.
     *
     * @param HFT_Scraper $item
     * @return string
     */
    public function column_base_parser($item): string {
        return $item->use_base_parser 
            ? '<span class="dashicons dashicons-yes-alt" title="Enabled"></span>'
            : '<span class="dashicons dashicons-dismiss" title="Disabled"></span>';
    }
    
    /**
     * Render last used column.
     *
     * @param HFT_Scraper $item
     * @return string
     */
    public function column_last_used($item): string {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hft_scraper_logs';
        $last_used = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$table} WHERE scraper_id = %d ORDER BY created_at DESC LIMIT 1",
            $item->id
        ));
        
        if (!$last_used) {
            return '<em>Never</em>';
        }
        
        $timestamp = strtotime($last_used);
        $diff = time() - $timestamp;
        
        if ($diff < HOUR_IN_SECONDS) {
            return sprintf('%d minutes ago', round($diff / MINUTE_IN_SECONDS));
        } elseif ($diff < DAY_IN_SECONDS) {
            return sprintf('%d hours ago', round($diff / HOUR_IN_SECONDS));
        } elseif ($diff < WEEK_IN_SECONDS) {
            return sprintf('%d days ago', round($diff / DAY_IN_SECONDS));
        } else {
            return date_i18n(get_option('date_format'), $timestamp);
        }
    }
    
    /**
     * Render success rate column.
     *
     * @param HFT_Scraper $item
     * @return string
     */
    public function column_success_rate($item): string {
        global $wpdb;
        
        $table = $wpdb->prefix . 'hft_scraper_logs';
        
        // Check if scraper has a recent health reset
        $health_reset_date = null;
        if (!empty($item->health_reset_at)) {
            $health_reset_timestamp = strtotime($item->health_reset_at);
            $thirty_days_ago = strtotime('-30 days');
            
            // Only use reset date if it's within the last 30 days
            if ($health_reset_timestamp > $thirty_days_ago) {
                $health_reset_date = $item->health_reset_at;
            }
        }
        
        // Build query with appropriate date filter
        $date_filter = $health_reset_date 
            ? $wpdb->prepare("created_at > %s", $health_reset_date)
            : "created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total,
                SUM(success) as successful
            FROM {$table} 
            WHERE scraper_id = %d 
            AND {$date_filter}",
            $item->id
        ));
        
        if (!$stats || $stats->total == 0) {
            return '<em>No data</em>';
        }
        
        $rate = ($stats->successful / $stats->total) * 100;
        $class = '';
        
        if ($rate >= 90) {
            $class = 'hft-success-rate-high';
        } elseif ($rate >= 70) {
            $class = 'hft-success-rate-medium';
        } else {
            $class = 'hft-success-rate-low';
        }
        
        $output = sprintf(
            '<span class="hft-success-rate %s">%.1f%%</span> <small>(%d/%d)</small>',
            $class,
            $rate,
            $stats->successful,
            $stats->total
        );
        
        // Show "Recently Reset" badge if health was reset within 7 days
        if ($health_reset_date && strtotime($health_reset_date) > strtotime('-7 days')) {
            $output .= '<br><small class="hft-health-reset-badge">Recently Reset</small>';
        }
        
        return $output;
    }
    
    /**
     * Prepare items for display.
     */
    public function prepare_items(): void {
        $per_page = $this->get_items_per_page('scrapers_per_page', 20);
        $current_page = $this->get_pagenum();
        
        // Get sorting parameters
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'domain';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';
        
        // Get total count
        $total_items = $this->repository->count();
        
        // Get items
        $this->items = $this->repository->find_all($per_page, ($current_page - 1) * $per_page, $orderby, $order);
        
        // Set pagination
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
        
        // Set column headers
        $this->_column_headers = [
            $this->get_columns(),
            [], // hidden columns
            $this->get_sortable_columns()
        ];
    }
    
    /**
     * Message to display when there are no items.
     */
    public function no_items(): void {
        echo 'No scrapers found. <a href="' . esc_url(admin_url('admin.php?page=hft-scraper-edit')) . '">Add a new scraper</a>.';
    }
}