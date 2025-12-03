<?php
// Don't allow direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'product_daily_prices';

function check_product_daily_prices_table_exists() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_daily_prices';
    return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
}

// Create the table if it doesn't exist
function create_product_daily_prices_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_daily_prices';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        product_id bigint(20) NOT NULL,
        price decimal(10,2) NOT NULL,
        domain varchar(255) NOT NULL,
        date date NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY product_date (product_id, date)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Run table creation on plugin activation
register_activation_hook(__FILE__, 'create_product_daily_prices_table');

// Main function to update product prices
function update_product_daily_prices() {
    // Only run this function when called by cron or WP-CLI
    if (!defined('DOING_CRON') && !defined('WP_CLI')) {
        return;
    }
	
	// Check if table exists, create if it doesn't
    if (!check_product_daily_prices_table_exists()) {
        create_product_daily_prices_table();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'product_daily_prices';
    $today = current_time('Y-m-d');

    // Get all products
    $products = get_posts(array(
        'post_type' => 'products',
        'numberposts' => -1,
        'post_status' => 'publish'
    ));

    $updated_count = 0;
    $skipped_count = 0;
    $anomaly_count = 0;

    foreach ($products as $product) {
        $product_id = $product->ID;
        $price_info = getPrices($product_id);

        // Skip if no price info available
        if (empty($price_info)) {
            $skipped_count++;
            update_post_meta($product_id, 'needs_price_review', 'No price information available');
            continue;
        }

        $current_price = $price_info[0]['price'];
        $domain = $price_info[0]['domain'];

        // Check for price anomalies
        if (!is_numeric($current_price)) {
            update_post_meta($product_id, 'needs_price_review', "Price isn't a number");
            $anomaly_count++;
            continue;
        }

        if ($current_price == 0) {
            update_post_meta($product_id, 'needs_price_review', "Price is 0");
            $anomaly_count++;
            continue;
        }

        // If we've reached here, the price is valid, so remove any existing 'needs_price_review' flag
        delete_post_meta($product_id, 'needs_price_review');

        // Check if an entry for today already exists
        $existing_entry = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE product_id = %d AND date = %s",
            $product_id, $today
        ));

        if ($existing_entry) {
            // Entry exists, update it
            $wpdb->update(
                $table_name,
                array(
                    'price' => $current_price,
                    'domain' => $domain
                ),
                array('id' => $existing_entry),
                array('%f', '%s'),
                array('%d')
            );
        } else {
            // No entry exists, insert new one
            $wpdb->insert(
                $table_name,
                array(
                    'product_id' => $product_id,
                    'price' => $current_price,
                    'domain' => $domain,
                    'date' => $today
                ),
                array('%d', '%f', '%s', '%s')
            );
        }

        $updated_count++;
    }

    // Log the results
    error_log("Daily price update completed. Updated: $updated_count, Skipped: $skipped_count, Anomalies: $anomaly_count");

    // Run cleanup after update
    cleanup_old_price_entries();
}

// Cleanup function to remove entries older than 2 years
function cleanup_old_price_entries() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_daily_prices';
    
    $two_years_ago = date('Y-m-d', strtotime('-2 years'));
    
    $deleted = $wpdb->query(
        $wpdb->prepare("DELETE FROM $table_name WHERE date < %s", $two_years_ago)
    );

    error_log("Cleanup completed. Deleted $deleted old price entries.");
}

// Set up the cron job
function setup_price_tracker_cron() {
    if (!wp_next_scheduled('update_product_daily_prices_cron')) {
        wp_schedule_event(time(), 'daily', 'update_product_daily_prices_cron');
    }
}
register_activation_hook(__FILE__, 'setup_price_tracker_cron');

// Remove the cron job on deactivation
function remove_price_tracker_cron() {
    wp_clear_scheduled_hook('update_product_daily_prices_cron');
}
register_deactivation_hook(__FILE__, 'remove_price_tracker_cron');

// Hook the main function to the cron event
add_action('update_product_daily_prices_cron', 'update_product_daily_prices');

// Function to list products needing price review
function list_products_needing_price_review() {
    $args = array(
        'post_type' => 'products',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'needs_price_review',
                'compare' => 'EXISTS',
            ),
        ),
    );

    $products_to_review = get_posts($args);

    foreach ($products_to_review as $product) {
        $reason = get_post_meta($product->ID, 'needs_price_review', true);
        echo "Product ID: {$product->ID}, Title: {$product->post_title}, Reason: $reason\n";
    }
}

// WP-CLI commands
if (defined('WP_CLI') && WP_CLI) {
    // Command to update product prices
    WP_CLI::add_command('update_product_prices', function() {
        update_product_daily_prices();
        WP_CLI::success('Product prices updated successfully.');
    });

    // Command to list products needing price review
    WP_CLI::add_command('list_price_review_products', 'list_products_needing_price_review');
}