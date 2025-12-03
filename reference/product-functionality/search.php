<?php
/**
 * Generate Search Items JSON
 *
 * This script generates a JSON file containing search items for all posts, products, and tools.
 * It's designed to run as a WordPress cron job twice a day.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function generate_search_items_json() {
    global $wpdb;

    $results = $wpdb->get_results(
        "SELECT ID, post_title, post_type 
        FROM $wpdb->posts 
        WHERE post_type IN ('post', 'products', 'tool')
        AND post_status = 'publish'"
    );

    $search_items = array_map(function($result) {
        $thumbnail_url = 'https://eridehero.com/wp-content/uploads/2024/07/kick-scooter-1.svg'; // Default fallback image
        $type = '';
        $product_type = '';

        switch ($result->post_type) {
            case 'products':
                $type = "Product";
                $big_thumbnail = get_field('big_thumbnail', $result->ID);
                if ($big_thumbnail) {
                    $thumbnail_url = wp_get_attachment_image_url($big_thumbnail, array(50, 50));
                }
                $product_type = get_field('product_type', $result->ID); // Assuming 'product_type' is an ACF field
                break;
            case 'tool':
                $type = "Tool";
                if (has_post_thumbnail($result->ID)) {
                    $thumbnail_url = get_the_post_thumbnail_url($result->ID, array(50, 50));
                }
                break;
            case 'post':
                $type = "Article";
                if (has_post_thumbnail($result->ID)) {
                    $thumbnail_url = get_the_post_thumbnail_url($result->ID, array(50, 50));
                }
                break;
        }

        $item = array(
            'id' => $result->ID,
            'title' => $result->post_title,
            'url' => get_permalink($result->ID),
            'type' => $type,
            'thumbnail' => $thumbnail_url
        );

        if ($product_type) {
            $item['product_type'] = $product_type;
        }

        return $item;
    }, $results);

    // Get the upload directory
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/search_items.json';

    // Encode the array to JSON and save it to the file
    $json_data = json_encode($search_items, JSON_PRETTY_PRINT);
    file_put_contents($file_path, $json_data);

    // Log the update (optional)
    error_log('Search items JSON updated at ' . current_time('mysql'));
}

// Schedule the cron job
if (!wp_next_scheduled('generate_search_items_json_hook')) {
    wp_schedule_event(time(), 'twicedaily', 'generate_search_items_json_hook');
}

add_action('generate_search_items_json_hook', 'generate_search_items_json');