<?php
/*
Plugin Name: Product Functionality
Plugin URI: https://yourwebsite.com/product-functionality-plugin
Description: Handles various product features including reviews with modal functionality
Version: 1.0
Author: Your Name
Author URI: https://yourwebsite.com
License: GPL2
*/

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Define plugin constants
define('PF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PF_PLUGIN_VERSION', '1.0');

// Include the main plugin class
require_once PF_PLUGIN_DIR . 'includes/class-product-functionality.php';
require_once PF_PLUGIN_DIR . 'product-data-cron.php';
require_once PF_PLUGIN_DIR . 'product-clear-history.php';
require_once PF_PLUGIN_DIR . 'product-specs.php';
require_once PF_PLUGIN_DIR . 'admin-price-trackers.php';
require_once PF_PLUGIN_DIR . 'search.php';
require_once PF_PLUGIN_DIR . 'getdeals.php';
require_once PF_PLUGIN_DIR . 'viewtracking.php';

// Initialize the plugin
function pf_init() {
    $product_functionality = new Product_Functionality();
    $product_functionality->init();
}
add_action('plugins_loaded', 'pf_init');

// Activation hook
function pf_activate() {
    // Activation code here (if any)
}
register_activation_hook(__FILE__, 'pf_activate');

// Deactivation hook
function pf_deactivate() {
	wp_clear_scheduled_hook('pf_price_tracker_cron');
    // Deactivation code here (if any)
}
register_deactivation_hook(__FILE__, 'pf_deactivate');

// Uninstall hook
function pf_uninstall() {
    // Uninstall code here (if any)
}
register_uninstall_hook(__FILE__, 'pf_uninstall');

function truncate_review($text, $max_length = 150) {
    $escaped_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text_with_breaks = nl2br($escaped_text);
    
    if (strlen($text) <= $max_length) return $text_with_breaks;
    
    $truncated = substr($text, 0, $max_length);
    $last_space = strrpos($truncated, ' ');
    if ($last_space !== false) {
        $truncated = substr($truncated, 0, $last_space);
    }
    $escaped_truncated = htmlspecialchars($truncated, ENT_QUOTES, 'UTF-8');
    
    return '
        <span class="pf-truncated-text">' . $escaped_truncated . '...</span>
        <span class="pf-full-text" style="display:none;">' . $text_with_breaks . '</span>
        <a href="#" class="pf-show-more">Show more</a>
    ';
}

function restrict_admin_access() {
    if (is_admin() && !current_user_can('administrator') && 
        !(defined('DOING_AJAX') && DOING_AJAX)) {
        wp_redirect(home_url());
        exit;
    }
}
add_action('init', 'restrict_admin_access');

function require_login_and_redirect() {
    if (!is_user_logged_in()) {
        // Get the current page URL
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        
        // Encode the current URL to use as a redirect parameter
        $redirect = urlencode($current_url);
        
        // Construct the login/register URL with the redirect parameter
        $login_url = home_url('/login-register/?redirect=' . $redirect);
        
        // Perform the redirect
        wp_safe_redirect($login_url);
        exit;
    }
}

function register_custom_image_sizes() {
    add_image_size( 'thumbnail-150', 150, 0, false );
}
add_action( 'after_setup_theme', 'register_custom_image_sizes' );
		

function create_product_data_table() {
    global $wpdb;
    global $table_name;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        product_id bigint(20) unsigned NOT NULL,
        product_type varchar(50) NOT NULL,
        name varchar(255) NOT NULL,
        specs longtext,
        price decimal(10,2),
        rating decimal(3,1),
        popularity_score int(8),
        instock tinyint(1),
        permalink varchar(255),
        image_url varchar(255),
        price_history longtext,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY product_id (product_id),
        KEY product_type (product_type)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

global $wpdb;
$table_name = $wpdb->prefix . 'product_data';




function get_all_product_data($product_type = null) {
    global $wpdb;
    global $table_name;

    $query = "SELECT * FROM $table_name";
    if ($product_type) {
        $query .= $wpdb->prepare(" WHERE product_type = %s", $product_type);
    }

    $results = $wpdb->get_results($query, ARRAY_A);

    foreach ($results as &$row) {
        $row['specs'] = maybe_unserialize($row['specs']);
        $row['price_history'] = maybe_unserialize($row['price_history']);
    }

    return $results;
}

// Remove the existing daily schedule (if it exists)
wp_clear_scheduled_hook('product_data_cron_job');

// Schedule the cron job to run twice daily
if (!wp_next_scheduled('product_data_cron_job')) {
    wp_schedule_event(time(), 'twicedaily', 'product_data_cron_job');
}

// Hook the function to the cron event
add_action('product_data_cron_job', 'product_data_cron_job');

// Optional: Add a custom twice daily interval if not already available
function add_twice_daily_cron_interval($schedules) {
    $schedules['twicedaily'] = array(
        'interval' => 12 * HOUR_IN_SECONDS,
        'display' => __('Twice Daily')
    );
    return $schedules;
}
add_filter('cron_schedules', 'add_twice_daily_cron_interval');

// Function to manually trigger the cron job (for testing or manual updates)
function trigger_product_data_cron_job() {
    do_action('product_data_cron_job');
}

// Optional: Add an admin menu item to manually trigger the cron job
function add_trigger_cron_menu_item() {
    add_management_page(
        'Trigger Product Data Update', 
        'Trigger Product Data Update', 
        'manage_options', 
        'trigger-product-data-update', 
        'trigger_product_data_update_page'
    );
}
add_action('admin_menu', 'add_trigger_cron_menu_item');

function trigger_product_data_update_page() {
    if (isset($_POST['trigger_update'])) {
        trigger_product_data_cron_job();
        echo '<div class="updated"><p>Product data update has been triggered.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Trigger Product Data Update</h1>
        <form method="post">
            <?php submit_button('Trigger Update', 'primary', 'trigger_update'); ?>
        </form>
    </div>
    <?php
}

function get_related_electric_scooters($limit = 5, $product_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_data';

    // If no product ID is provided, use get_the_ID()
    if ($product_id === null) {
        $product_id = get_the_ID();
    }

    // Fetch the current scooter data
    $current_scooter = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE product_id = %d",
        $product_id
    ));

    if (!$current_scooter) {
        return []; // Current scooter not found
    }

    // Fetch all other scooters of the same product type
    $all_scooters = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE product_type = %s AND product_id != %d",
        $current_scooter->product_type,
        $product_id
    ));

    $related_scooters = [];

    // Helper function to safely compare numeric values
    function safe_numeric_compare($val1, $val2, $threshold) {
        if (!is_numeric($val1) || !is_numeric($val2)) {
            return false;
        }
        return abs(floatval($val1) - floatval($val2)) <= $threshold;
    }

    foreach ($all_scooters as $scooter) {
        $similarity_score = 0;
        
        // Prioritize in-stock items
        if ($scooter->instock == 1) {
            $similarity_score += 5; // Give a significant boost to in-stock items
        }
        
        // Compare price (within 20% range)
        if (is_numeric($scooter->price) && is_numeric($current_scooter->price) && $current_scooter->price != 0) {
            if (abs($scooter->price - $current_scooter->price) / $current_scooter->price <= 0.2) {
                $similarity_score += 3;
            }
        }
        
        // Unserialize the specs
        $current_specs = maybe_unserialize($current_scooter->specs);
        $scooter_specs = maybe_unserialize($scooter->specs);
        
        // Compare manufacturer top speed
        if (safe_numeric_compare($scooter_specs['manufacturer_top_speed'], $current_specs['manufacturer_top_speed'], 3)) {
            $similarity_score += 2;
        }
        
        // Compare manufacturer range
        if (safe_numeric_compare($scooter_specs['manufacturer_range'], $current_specs['manufacturer_range'], 5)) {
            $similarity_score += 2;
        }
        
        // Compare weight
        if (safe_numeric_compare($scooter_specs['weight'], $current_specs['weight'], 5)) {
            $similarity_score += 1;
        }
        
        // Compare battery capacity
        if (safe_numeric_compare($scooter_specs['battery_capacity'], $current_specs['battery_capacity'], 50)) {
            $similarity_score += 1;
        }
        
        // Compare motor wattage
        if (safe_numeric_compare($scooter_specs['nominal_motor_wattage'], $current_specs['nominal_motor_wattage'], 100)) {
            $similarity_score += 1;
        }
        
        // Check for same brand
        if (isset($scooter_specs['brand']) && isset($current_specs['brand']) && $scooter_specs['brand'] === $current_specs['brand']) {
            $similarity_score += 1;
        }
        
        // Check for same tire size
        if (isset($scooter_specs['tire_size_front']) && isset($current_specs['tire_size_front']) && $scooter_specs['tire_size_front'] === $current_specs['tire_size_front']) {
            $similarity_score += 1;
        }
        
        $related_scooters[] = [
            'scooter' => $scooter,
            'similarity_score' => $similarity_score
        ];
    }

    // Sort related scooters by similarity score (descending)
    usort($related_scooters, function($a, $b) {
        return $b['similarity_score'] - $a['similarity_score'];
    });

    // Return top N related scooters
    return array_slice($related_scooters, 0, $limit);
}

function esc_smart($content) {
    // First, replace WordPress-specific entities
	$content = esc_html($content);
    $content = str_replace('&#039;', "'", $content);
    $content = str_replace('&quot;', '"', $content);
    
    // Then use html_entity_decode for any remaining entities
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return $content;
}

function custom_search_where($where) {
    global $wpdb;
    if (is_search()) {
        $where = preg_replace(
            "/\(\s*".$wpdb->posts.".post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
            "(".$wpdb->posts.".post_title LIKE $1) AND ".$wpdb->posts.".post_type IN ('post', 'products', 'tool')", $where);
    }
    return $where;
}
add_filter('posts_where', 'custom_search_where');

function custom_search_distinct($distinct) {
    return "DISTINCT";
}
add_filter('posts_distinct', 'custom_search_distinct');