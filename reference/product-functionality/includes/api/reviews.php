<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Dummy data - replace with actual database query
$reviews = array(
    array(
        'author' => 'John Doe',
        'date' => '2023-06-01',
        'rating' => 4,
        'text' => 'Great product! Exactly what I was looking for.'
    ),
    array(
        'author' => 'Jane Smith',
        'date' => '2023-05-15',
        'rating' => 5,
        'text' => 'Excellent quality and fast shipping. Highly recommended!'
    ),
    array(
        'author' => 'Bob Johnson',
        'date' => '2023-05-02',
        'rating' => 3,
        'text' => 'Good product but arrived later than expected.'
    )
);

wp_send_json(array('success' => true, 'reviews' => $reviews));