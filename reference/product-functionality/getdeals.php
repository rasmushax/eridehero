<?php

function getDeals($product_type = 'Electric Scooter', $price_difference_threshold = -0.6) {
    global $wpdb;
    
    // First get all products with price history
    $query = $wpdb->prepare("
        SELECT *
        FROM {$wpdb->prefix}product_data
        WHERE product_type = %s
        AND price IS NOT NULL
		AND instock = 1
        AND price_history IS NOT NULL
        AND price_history != ''
    ", $product_type); // Get more initially since we'll filter
    
    $results = $wpdb->get_results($query);
    
    // Filter and sort by 6-month price difference
    $filtered_deals = [];
    foreach ($results as $product) {
        $price_history = maybe_unserialize($product->price_history);
        if (!is_array($price_history)) continue;
        
        // Calculate 6-month price difference if available
        if (isset($price_history['average_price_6m']) && $price_history['average_price_6m'] > 0) {
            $price_diff_6m = (($product->price - $price_history['average_price_6m']) / $price_history['average_price_6m']) * 100;
            
            if ($price_diff_6m < $price_difference_threshold) {
                $product->price_diff_6m = $price_diff_6m;
                $filtered_deals[] = $product;
            }
        }
    }
    
    // Sort by 6-month price difference (best deals first)
    usort($filtered_deals, function($a, $b) {
        return $a->price_diff_6m <=> $b->price_diff_6m;
    });
    
    return $filtered_deals;
}

function splitPrice($price) {
    $wholePart = intval($price);
    $fractionalPart = round(($price - $wholePart) * 100);
    $fractionalPart = str_pad($fractionalPart, 2, '0', STR_PAD_LEFT);
    
    return [
        'whole' => number_format($wholePart),
        'fractional' => $fractionalPart
    ];
}