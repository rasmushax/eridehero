<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

check_ajax_referer('pf_nonce', '_wpnonce');

$user_id = get_current_user_id();
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

$user_status = array(
    'isLoggedIn' => ($user_id !== 0),
    'hasPendingReview' => false,
    'hasPublishedReview' => false
);

if ($user_id && $product_id) {
    $existing_reviews = get_posts(array(
        'post_type' => 'review',
        'post_status' => array('pending', 'publish'),
        'author' => $user_id,
        'meta_query' => array(
            array(
                'key' => 'product',
                'value' => $product_id,
                'compare' => '='
            )
        ),
        'posts_per_page' => -1
    ));

    foreach ($existing_reviews as $review) {
        if ($review->post_status === 'pending') {
            $user_status['hasPendingReview'] = true;
        } elseif ($review->post_status === 'publish') {
            $user_status['hasPublishedReview'] = true;
        }
    }
}

wp_send_json($user_status);