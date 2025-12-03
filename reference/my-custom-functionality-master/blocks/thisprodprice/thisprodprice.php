<?php
// Determine the product ID
global $post;
global $wpdb;
if (get_field('use_current_post_linked_product') == 1) {
    $product_id = get_field('relationship', $post->ID)[0];
} else {
    $product_id = get_field('product_to_show')[0]->ID;
}

// If product ID isn't found, output nothing and exit
if (!$product_id) {
    return;
}

// Get product data
$product_data = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM wp_product_data WHERE product_id = %d",
    $product_id
));

if (!$product_data) {
    return;
}

// Get product fields
$fields = get_fields($product_id);

// Get prices
$prices = getPrices($product_id);

// Get all active coupons for matching
$coupon_args = array(
    'post_type' => 'coupon',
    'posts_per_page' => -1,
    'post_status' => 'publish'
);
$all_coupons = get_posts($coupon_args);

// Find applicable coupon inline - using domain field directly
$coupon = null;
if (!empty($prices[0]['domain'])) {
    // Use domain directly from prices array (already normalized)
    $normalized_domain = strtolower($prices[0]['domain']);
    
    foreach ($all_coupons as $coupon_post) {
        // Get coupon retailer from post title
        $coupon_retailer = strtolower(trim($coupon_post->post_title));
        
        // Get coupon fields
        $coupon_code = get_field('coupon_code', $coupon_post->ID);
        $discount_amount = get_field('discount_amount', $coupon_post->ID);
        $discount_type = get_field('discount_type', $coupon_post->ID);
        $description = get_field('description', $coupon_post->ID);
        $included_products = get_field('included_products', $coupon_post->ID);
        $excluded_products = get_field('excluded_products', $coupon_post->ID);
        
        // Check if product is excluded
        if ($excluded_products && is_array($excluded_products) && in_array($product_id, $excluded_products)) {
            continue;
        }
        
        // Determine if coupon applies
        $coupon_applies = false;
        
        // Case 1: Specific products are included
        if ($included_products && is_array($included_products) && !empty($included_products)) {
            if (in_array($product_id, $included_products)) {
                $coupon_applies = true;
            }
        }
        // Case 2: No specific products - check retailer match
        else {
            if ($coupon_retailer === $normalized_domain) {
                $coupon_applies = true;
            }
        }
        
        if ($coupon_applies && $coupon_code) {
            $coupon = array(
                'code' => $coupon_code,
                'discount_amount' => $discount_amount,
                'discount_type' => $discount_type,
                'description' => $description
            );
            break; // Found a coupon, stop looking
        }
    }
}

// Start product block wrapper
echo '<div class="product-block-wrapper">';

// Main product block
echo '<div class="product-block">';

// Left side
$product_types = ['Electric Scooter', 'Electric Bike', 'Hoverboard'];
$is_linkable = in_array($product_data->product_type, $product_types);

$left_content = '<div class="product-left">';

// Image
if (!empty($fields['big_thumbnail'])) {
    $image = wp_get_attachment_image_src($fields['big_thumbnail'], array(50, 50));
    $image_url = $image[0];
} elseif (!empty($fields['thumbnail'])) {
    $image = wp_get_attachment_image_src($fields['thumbnail'], array(50, 50));
    $image_url = $image[0];
} else {
    $image_url = 'https://eridehero.com/wp-content/uploads/2024/07/kick-scooter-1.svg';
}

$title = get_the_title($product_id);

$left_content .= '<div class="product-image"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '" style="width: 50px; height: 50px;"></div>';

// Product name
$left_content .= '<div class="product-name">' . esc_html($title) . '</div>';

$left_content .= '</div>';

if ($is_linkable) {
    echo '<a href="' . esc_url(get_permalink($product_id)) . '" class="product-left">' . $left_content . '</a>';
} else {
    echo '<div class="product-left">' . $left_content . '</div>';
}

// Right side
echo '<div class="product-right">';

// Check if the product is obsolete/outdated
$is_obsolete = isset($fields['obsolete']['is_product_obsolete']) && $fields['obsolete']['is_product_obsolete'] == 1;

if ($is_obsolete) {
    echo '<div class="product-obsolete-notice">';
    
    $has_been_superseded = isset($fields['obsolete']['has_the_product_been_superseded']) && $fields['obsolete']['has_the_product_been_superseded'] == 1;
    if ($has_been_superseded) {
        $new_product_id = $fields['obsolete']['new_product'];
        $new_product = get_post($new_product_id);
        if ($new_product) {
            echo '<p class="product-superseded-text">This product has been superseeded by <a href="' . get_permalink($new_product_id) . '" class="product-superseded-link">' . $new_product->post_title . '</a></p>';
        }
    } else {
        echo '<p class="product-obsolete-text">This product has been discontinued.</p>';
    }
    echo '</div>';
} else {
    echo '<div class="product-price-container">'; // New container for price information
    
    // Price history
    $price_history = maybe_unserialize($product_data->price_history);
    // Calculate 6-month price difference
    $price_diff_6m = null;
    if (isset($price_history['average_price_6m']) && $price_history['average_price_6m'] > 0 && isset($prices[0]['price'])) {
        $price_diff_6m = $prices[0]['price'] - $price_history['average_price_6m'];
        echo '<div class="product-price-history">6M Avg: $' . number_format($price_history['average_price_6m'], 2) . '</div>';
    }
    
    // Current price
    if (isset($prices[0]['price']) && !empty($prices[0]['price'])) {
        $price = split_money($prices[0]['price']);
        if ($price_diff_6m !== null && $price_diff_6m < 0) {
            echo '<div class="product-current-price"><svg class="product-price-icon product-price-icon-down"><use xlink:href="#icon-trending-down"></use></svg>$<span>'.$price['whole'].'</span>'.$price['fractional'].'</div>';
        } elseif($price_diff_6m !== null && $price_diff_6m > 0) {
            echo '<div class="product-current-price"><svg class="product-price-icon product-price-icon-up"><use xlink:href="#icon-trending-up"></use></svg>$<span>'.$price['whole'].'</span>'.$price['fractional'].'</div>';
        } else {
            echo '<div class="product-current-price">$<span>'.$price['whole'].'</span>'.$price['fractional'].'</div>';
        }
    }
    
    echo '</div>'; // Close product-price-container
    
    // Affiliate link
    if (!empty($prices[0]['url'])) {
        echo '<a href="' . esc_url(afflink($prices[0]['url'],$product_id)) . '" class="product-aff-link afftrigger">Get Deal<svg><use xlink:href="#icon-external-link"></use></svg></a>';
    }
}
echo '</div>'; // Close product-right

echo '</div>'; // Close product-block

// Display coupon if available
if ($coupon) {
    echo '<div class="product-block-coupon-row">';
    echo '<div class="product-block-coupon">';
    echo '<span class="product-block-coupon-label">CODE:</span>';
    echo '<span class="product-block-coupon-code">' . esc_html($coupon['code']) . '</span>';
    echo '</div>';
    if (!empty($coupon['description'])) {
        echo '<span class="product-block-coupon-desc">' . esc_html($coupon['description']) . '</span>';
    }
    echo '</div>';
}

echo '</div>'; // Close product-block-wrapper
?>