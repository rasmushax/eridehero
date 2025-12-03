<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (!check_ajax_referer('pf_nonce', '_wpnonce', false)) {
    error_log('Nonce check failed in submit_review');
    wp_send_json_error('Invalid nonce');
    return;
}

// Check if user is logged in
if (!is_user_logged_in()) {
    wp_send_json_error('You must be logged in to submit a review.', 403);
    exit;
}

$user_id = get_current_user_id();
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$review_text = isset($_POST['review']) ? sanitize_textarea_field($_POST['review']) : '';

// Validate inputs
if (!$product_id) {
    wp_send_json_error('Invalid product ID.', 400);
    exit;
}

if (!in_array($rating, [1, 2, 3, 4, 5])) {
    wp_send_json_error('Invalid rating. Please select a whole number between 1 and 5.', 400);
    exit;
}

if (empty($review_text)) {
    wp_send_json_error('Please enter your review.', 400);
    exit;
}

if (strlen($review_text) > 5000) {
    wp_send_json_error('Your review is too long. Please limit it to 5000 characters.', 400);
    exit;
}

// Check if user has already reviewed this product
$existing_review = get_posts(array(
    'post_type' => 'review',
    'author' => $user_id,
    'meta_query' => array(
        array(
            'key' => 'product',
            'value' => $product_id,
            'compare' => '='
        )
    ),
    'posts_per_page' => 1
));

if (!empty($existing_review)) {
    wp_send_json_error('You have already submitted a review for this product.', 400);
    exit;
}

// Handle image upload
$image_id = 0;
if (!empty($_FILES['review-image']['name'])) {
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $allowed_types = array('image/jpeg', 'image/png');
    $max_size = 5 * 1024 * 1024; // 5MB

    if (!in_array($_FILES['review-image']['type'], $allowed_types)) {
        wp_send_json_error('Invalid file type. Please upload a JPEG, PNG image.', 400);
        exit;
    }

    if ($_FILES['review-image']['size'] > $max_size) {
        wp_send_json_error('File size exceeds the limit of 5MB.', 400);
        exit;
    }

    $image_id = media_handle_upload('review-image', 0);

    if (is_wp_error($image_id)) {
        wp_send_json_error('Failed to upload image. Please try again.', 500);
        exit;
    }
}

// Prepare review content
$review_content = wp_kses($review_text, array('br' => array()));

// Create the review post
$review_data = array(
    'post_title'    => 'Review for Product ' . $product_id,
    'post_content'  => '',  // We'll store the content in a meta field
    'post_status'   => 'pending', // Set to 'publish' if you don't want to moderate reviews
    'post_type'     => 'review',
    'post_author'   => $user_id,
);

$review_id = wp_insert_post($review_data);

if (is_wp_error($review_id)) {
    if ($image_id) {
        wp_delete_attachment($image_id, true);
    }
    wp_send_json_error('Failed to submit review. Please try again later.', 500);
    exit;
}

// Add meta data
add_post_meta($review_id, 'product', $product_id);
add_post_meta($review_id, 'score', $rating);
add_post_meta($review_id, 'text', $review_content);

if ($image_id) {
    add_post_meta($review_id, 'review_image', $image_id);
    // Set the uploaded image as the featured image of the review
    set_post_thumbnail($review_id, $image_id);
}

wp_send_json_success('Your review has been submitted successfully and is awaiting moderation.');