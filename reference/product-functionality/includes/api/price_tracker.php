<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

function pf_check_price_data() {
    check_ajax_referer('pf_nonce', '_wpnonce');

    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    if ($product_id === 0) {
        wp_send_json_error('Invalid product ID');
        return;
    }

    $prices = getPrices($product_id);

    if (is_array($prices) && count($prices) > 0 && $prices[0]['price'] != 0) {
        wp_send_json_success(array(
            'has_price_data' => true,
            'current_price' => $prices[0]['price']
        ));
    } else {
        wp_send_json_success(array(
            'has_price_data' => false
        ));
    }
}
add_action('wp_ajax_pf_check_price_data', 'pf_check_price_data');

function pf_check_price_tracker() {
    check_ajax_referer('pf_nonce', '_wpnonce');

    $user_id = get_current_user_id();
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    if ($product_id === 0) {
        wp_send_json_error('Invalid product ID');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'price_trackers';

    $tracker = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d AND product_id = %d",
        $user_id,
        $product_id
    ));

    // Check if the user has enabled email alerts
    $price_trackers_emails = get_user_meta($user_id, 'price_trackers_emails', true);
    $email_alerts_enabled = $price_trackers_emails === '1';

    if ($tracker) {
        wp_send_json_success(array(
            'has_tracker' => true,
            'tracker' => $tracker,
            'email_alerts_enabled' => $email_alerts_enabled
        ));
    } else {
        wp_send_json_success(array(
            'has_tracker' => false,
            'email_alerts_enabled' => $email_alerts_enabled
        ));
    }
}
add_action('wp_ajax_pf_check_price_tracker', 'pf_check_price_tracker');

function pf_set_price_tracker() {
    try {
        check_ajax_referer('pf_nonce', '_wpnonce');

        $user_id = get_current_user_id();
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $current_price = isset($_POST['current_price']) ? floatval($_POST['current_price']) : 0;
        $tracker_type = isset($_POST['tracker_type']) ? sanitize_text_field($_POST['tracker_type']) : '';
        $target_price = $tracker_type === 'target_price' ? floatval($_POST['target_price']) : null;
        $price_drop = $tracker_type === 'price_drop' ? floatval($_POST['price_drop']) : null;
        $update_email_preference = isset($_POST['tracker_email_permission']) ? 1 : 0;

        error_log('Received data: ' . print_r($_POST, true)." for user ".$user_id);

        if ($product_id === 0 || $current_price === 0) {
            wp_send_json_error('Invalid product ID or current price');
            return;
        }

        if ($tracker_type === 'target_price' && $target_price >= $current_price) {
            wp_send_json_error('Target price must be lower than the current price.');
            return;
        }

        if ($tracker_type === 'price_drop' && $price_drop >= $current_price) {
            wp_send_json_error('Price drop must be lower than the current price.');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'price_trackers';

        $existing_tracker = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND product_id = %d",
            $user_id,
            $product_id
        ));

        $data = array(
            'user_id' => $user_id,
            'product_id' => $product_id,
            'current_price' => $current_price,
            'target_price' => $target_price,
            'price_drop' => $price_drop,
            'updated_at' => current_time('mysql', 1)
        );

        if ($existing_tracker) {
            $result = $wpdb->update(
                $table_name,
                $data,
                array('id' => $existing_tracker->id)
            );
        } else {
            $data['created_at'] = current_time('mysql', 1);
            $data['start_price'] = $current_price;
            $result = $wpdb->insert($table_name, $data);
        }

        if ($result === false) {
            error_log('Database operation failed: ' . $wpdb->last_error);
            wp_send_json_error('Failed to set price tracker');
            return;
        }

        if ($update_email_preference) {
            update_user_meta($user_id, 'price_trackers_emails', '1');
			error_log("Updated user $user_id price_trackers_emails preference to 1");
        }

        wp_send_json_success('Price tracker set successfully');
    } catch (Exception $e) {
        error_log('Error in pf_set_price_tracker: ' . $e->getMessage());
        wp_send_json_error('An error occurred while setting the price tracker');
    }
}
add_action('wp_ajax_pf_set_price_tracker', 'pf_set_price_tracker');

function pf_delete_price_tracker() {
    check_ajax_referer('pf_nonce', '_wpnonce');

    $user_id = get_current_user_id();
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    if ($product_id === 0) {
        wp_send_json_error('Invalid product ID');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'price_trackers';

    $result = $wpdb->delete(
        $table_name,
        array(
            'user_id' => $user_id,
            'product_id' => $product_id
        ),
        array('%d', '%d')
    );

    if ($result !== false) {
        wp_send_json_success('Price tracker deleted successfully');
    } else {
        wp_send_json_error('Failed to delete price tracker');
    }
}
add_action('wp_ajax_pf_delete_price_tracker', 'pf_delete_price_tracker');