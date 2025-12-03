<?php
/**
 * Listicle Item Block Template.
 *
 * @param   array $block The block settings and attributes.
 * @param   string $content The block inner HTML (empty).
 * @param   int $post_id The post ID the block is rendering content against.
 * @param   array $context The context provided to the block by the post or it's parent block.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Helper functions assumed loaded: getPrices(), prettydomain(), render_textarea_list(), getSpecs(), format_spec_value() ---

// Get ACF fields for the block
$label                = get_field('label') ?: '';
$product_id           = get_field('product_relationship');
$item_image           = get_field('item_image');
$quick_take           = get_field('quick_take');
$what_i_like          = get_field('what_i_like');
$what_i_dont_like     = get_field('what_i_dont_like');
$body_text            = get_field('body_text');

// Generate unique IDs
$block_id = 'listicle-item-' . $block['id'];
$overview_tab_id = $block_id . '-overview-tab';
$overview_panel_id = $block_id . '-overview-panel';
$specs_tab_id = $block_id . '-specs-tab';
$specs_panel_id = $block_id . '-specs-panel';
$price_history_tab_id = $block_id . '-price-history-tab';
$price_history_panel_id = $block_id . '-price-history-panel';

// IDs for Price History AJAX elements
$price_chart_canvas_id = 'priceChart-' . $block_id;
$chart_tracking_info_id = 'chart-tracking-info-' . $block_id;
$chart_summary_stats_id = 'chart-summary-stats-' . $block_id;
$chart_spinner_container_id = 'chart-spinner-container-' . $block_id;

// *** NEW: IDs for Specifications AJAX elements ***
$specs_spinner_container_id = 'specs-spinner-container-' . $block_id;
$specs_error_message_id = 'specs-error-message-' . $block_id;
$specs_content_target_id = 'specs-content-target-' . $block_id;


// --- Initialize Variables ---
$product_post = null;
$product_title = '';
$best_price_info = null;
$price_diff_percent = null;
$product_rating_overall = null;
$product_ratings = null;
$key_specs = [];
$review_permalink = null;
$youtube_url = null;
$applicable_coupon = null;
$coupon_retailer = null;

// --- Get Data from Related Product ---
if ( $product_id && is_numeric($product_id) ) {
    $product_post = get_post( $product_id );

    if ( $product_post ) {
        $product_title = get_the_title( $product_id );
		
		// --- Fetch Price History Data from wp_product_data ---
        global $wpdb;
        $table_name = $wpdb->prefix . 'product_data';
		$product_data = $wpdb->get_row( $wpdb->prepare(
			"SELECT price, price_history FROM {$table_name} WHERE product_id = %d",
			$product_id
		) );

        if ( $product_data ) {
			$price_history_data = unserialize( $product_data->price_history );
			// Calculate 6-month price difference
			if ( is_array( $price_history_data ) && isset( $price_history_data['average_price_6m'] ) && $price_history_data['average_price_6m'] > 0 ) {
				$price_diff_percent = (($product_data->price - $price_history_data['average_price_6m']) / $price_history_data['average_price_6m']) * 100;
			}
		}

        // --- Get Prices ---
        $product_prices = getPrices( $product_id );
        if ( is_array( $product_prices ) && count($product_prices) > 0 ) {
             $first_price_item = $product_prices[0];
             if (!empty($first_price_item['url'])) {
                 $first_price_item['pretty_domain'] = prettydomain(extractDomain($first_price_item['url']));
             } else {
                  $first_price_item['pretty_domain'] = 'View';
             }
             $best_price_info = $first_price_item;
             $best_price_info['is_available'] = isset($first_price_item['price']) && $first_price_item['price'] > 0 && isset($first_price_item['stock_status']) && $first_price_item['stock_status'] != -1;
             
             // --- Get Applicable Coupon ---
             if ($best_price_info['is_available'] && !empty($first_price_item['url'])) {
                 $domain = extractDomain($first_price_item['url']);
                 $normalized_domain = str_replace('www.', '', strtolower($domain));
                 
                 // Get all coupons
                 $coupon_args = array(
                     'post_type' => 'coupon',
                     'posts_per_page' => -1,
                     'post_status' => 'publish',
                     'meta_query' => array(
                         array(
                             'key' => 'coupon_code',
                             'compare' => 'EXISTS'
                         )
                     )
                 );
                 $all_coupons = get_posts($coupon_args);
                 
                 // Find matching coupon
                 foreach ($all_coupons as $coupon) {
                     $coupon_retailer_check = strtolower(trim($coupon->post_title));
                     
                     // Check if this coupon is for the current product's retailer
                     if ($coupon_retailer_check === $normalized_domain) {
                         $coupon_code = get_field('coupon_code', $coupon->ID);
                         $discount_amount = get_field('discount_amount', $coupon->ID);
                         $discount_type = get_field('discount_type', $coupon->ID);
                         $description = get_field('description', $coupon->ID);
                         $included_products = get_field('included_products', $coupon->ID);
                         $excluded_products = get_field('excluded_products', $coupon->ID);
                         
                         // Check exclusions
                         if ($excluded_products && is_array($excluded_products) && in_array($product_id, $excluded_products)) {
                             continue;
                         }
                         
                         // Check inclusions
                         $coupon_applies = false;
                         if ($included_products && is_array($included_products) && !empty($included_products)) {
                             if (in_array($product_id, $included_products)) {
                                 $coupon_applies = true;
                             }
                         } else {
                             // No specific products, so it applies to all from this retailer
                             $coupon_applies = true;
                         }
                         
                         if ($coupon_applies && $coupon_code) {
                             $applicable_coupon = array(
                                 'code' => $coupon_code,
                                 'discount_amount' => $discount_amount,
                                 'discount_type' => $discount_type,
                                 'description' => $description ?: ($discount_type === 'Percentage' ? $discount_amount . '% off' : '$' . $discount_amount . ' off'),
                                 'url' => $first_price_item['url']
                             );
                             $coupon_retailer = prettydomain($domain);
                             break; // Use first matching coupon
                         }
                     }
                 }
             }
        }

        // --- Get Ratings ---
        $product_ratings = get_field('ratings', $product_id);
        if ( !empty($product_ratings['overall']) ) {
            $product_rating_overall = floatval($product_ratings['overall']);
        }

        // --- Get Key Specs ---
        $spec_fields = [
            'tested_top_speed' => 'Tested Speed',
            'tested_range_regular' => 'Tested Range',
            'weight' => 'Weight',
            'max_load' => 'Max Load',
            'battery_capacity' => 'Battery Capacity',
            'nominal_motor_wattage' => 'Nominal Power',
        ];

        // *** Define SVG icons for each spec key ***
        $default_icon = '<svg class="spec-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>'; // Default placeholder
        $spec_icons = [
            'tested_top_speed' => '<svg version="1.1" class="spec-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 32 32"> <title>dashboard</title> <path d="M23.525 12.964c-0.369-0.369-0.967-0.369-1.336 0l-5.096 5.096c-0.334-0.151-0.704-0.236-1.093-0.236-1.469 0-2.664 1.196-2.664 2.665 0 1.47 1.195 2.665 2.664 2.665s2.665-1.195 2.665-2.665c0-0.389-0.084-0.759-0.236-1.093l5.096-5.097c0.369-0.368 0.369-0.966 0-1.335zM16 11.736c-4.826 0-8.752 3.927-8.752 8.752 0 0.522-0.423 0.945-0.945 0.945s-0.944-0.423-0.944-0.945c0-5.868 4.774-10.642 10.642-10.642 1.262 0 2.497 0.219 3.671 0.65 0.49 0.18 0.74 0.723 0.561 1.212s-0.723 0.741-1.213 0.561c-0.964-0.354-1.98-0.534-3.019-0.534zM28.569 23.533c-0.131 0.543-0.595 0.908-1.153 0.908h-22.831c-0.558 0-1.022-0.364-1.153-0.908-0.24-0.991-0.361-2.015-0.361-3.044 0-7.129 5.8-12.93 12.929-12.93s12.93 5.801 12.93 12.93c0 1.029-0.121 2.053-0.361 3.044zM26.479 10.011c-2.799-2.799-6.52-4.341-10.479-4.341s-7.679 1.541-10.478 4.341c-2.799 2.799-4.341 6.52-4.341 10.478 0 1.179 0.139 2.352 0.414 3.489 0.335 1.385 1.564 2.353 2.99 2.353h22.831c1.425 0 2.654-0.968 2.989-2.353 0.275-1.137 0.414-2.311 0.414-3.489 0-3.958-1.541-7.679-4.34-10.478z"></path> </svg>', // Simple gauge/speedometer placeholder
            'tested_range_regular' => '<svg class="strokes spec-icon" xmlns="http://www.w3.org/2000/svg" version="1.1" width="24" height="24" viewBox="0 0 32 32"><title>range</title><path fill="none" stroke-linejoin="miter" stroke-linecap="butt" stroke-miterlimit="10" stroke-width="1.8638" d="M24.363 18.501h2.393c1.045 0 1.892 0.847 1.892 1.892v0c0 1.045-0.847 1.892-1.892 1.892h-7.304c-1.072 0-1.941 0.869-1.941 1.941v0c0 1.072 0.869 1.941 1.941 1.941h3.556c0.978 0 1.77 0.793 1.77 1.77v0c0 0.978-0.793 1.77-1.77 1.77h-15.247"></path><path fill="none" stroke-linejoin="miter" stroke-linecap="butt" stroke-miterlimit="10" stroke-width="1.8638" d="M7.761 29.57c0 0-6.829-4.059-6.829-9.263 0-3.766 3.063-6.829 6.829-6.829s6.829 3.063 6.829 6.829c0 5.204-6.829 9.263-6.829 9.263z"></path><path fill="none" stroke-linejoin="miter" stroke-linecap="butt" stroke-miterlimit="10" stroke-width="1.8638" d="M10.406 20.228c0 1.461-1.184 2.645-2.645 2.645s-2.645-1.184-2.645-2.645c0-1.461 1.184-2.645 2.645-2.645s2.645 1.184 2.645 2.645z"></path><path fill="none" stroke-linejoin="miter" stroke-linecap="butt" stroke-miterlimit="10" stroke-width="1.8638" d="M24.239 18.355c0 0-6.829-4.045-6.829-9.248 0-3.766 3.063-6.829 6.829-6.829s6.829 3.063 6.829 6.829c0 5.204-6.829 9.248-6.829 9.248z"></path><path fill="none" stroke-linejoin="miter" stroke-linecap="butt" stroke-miterlimit="10" stroke-width="1.8638" d="M26.884 9.028c0 1.461-1.184 2.645-2.645 2.645s-2.645-1.184-2.645-2.645c0-1.461 1.184-2.645 2.645-2.645s2.645 1.184 2.645 2.645z"></path></svg>', // Simple road/distance placeholder
            'weight' => '<svg version="1.1" class="spec-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 32 32"> <title>weight</title> <path d="M5.535 12.363c0.445-1.781 2.045-3.030 3.881-3.030h13.169c1.835 0 3.435 1.249 3.881 3.030l3.333 13.333c0.631 2.525-1.278 4.97-3.881 4.97h-19.836c-2.602 0-4.512-2.446-3.881-4.97zM9.416 12c-0.612 0-1.145 0.416-1.294 1.010l-3.333 13.333c-0.21 0.841 0.426 1.657 1.294 1.657h19.836c0.867 0 1.504-0.815 1.294-1.657l-3.333-13.333c-0.148-0.594-0.682-1.010-1.294-1.010z"></path> <path d="M16 4c-1.473 0-2.667 1.194-2.667 2.667s1.194 2.667 2.667 2.667 2.667-1.194 2.667-2.667-1.194-2.667-2.667-2.667zM10.667 6.667c0-2.946 2.388-5.333 5.333-5.333s5.333 2.388 5.333 5.333-2.388 5.333-5.333 5.333c-2.946 0-5.333-2.388-5.333-5.333z"></path> </svg>', // Simple scale placeholder
            'max_load' => '<svg version="1.1" class="spec-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 32 32"> <title>weight scale</title> <path d="M25 2h-18c-2.761 0-5 2.239-5 5v0 18c0 2.761 2.239 5 5 5v0h18c2.761 0 5-2.239 5-5v0-18c0-2.761-2.239-5-5-5v0zM28 25c0 1.657-1.343 3-3 3v0h-18c-1.657 0-3-1.343-3-3v0-18c0-1.657 1.343-3 3-3v0h18c1.657 0 3 1.343 3 3v0zM25.78 10.82c-1.374-1.6-3.060-2.889-4.969-3.782l-0.091-0.038c-1.393-0.613-3.017-0.969-4.725-0.969s-3.332 0.357-4.802 1l0.077-0.030c-1.977 0.948-3.643 2.245-4.982 3.828l-0.018 0.022c-0.135 0.169-0.217 0.386-0.217 0.622 0 0.042 0.003 0.083 0.007 0.123l-0-0.005c0.031 0.272 0.168 0.508 0.368 0.668l0.002 0.002 4.5 3.55c0.162 0.136 0.373 0.219 0.603 0.219 0.066 0 0.131-0.007 0.193-0.020l-0.006 0.001c0.289-0.051 0.531-0.221 0.678-0.456l0.002-0.004c0.721-1.288 2.052-2.159 3.592-2.22l0.008-0c1.562 0.036 2.914 0.9 3.639 2.169l0.011 0.021c0.149 0.239 0.391 0.409 0.674 0.459l0.006 0.001c0.026 0.005 0.055 0.008 0.085 0.008s0.059-0.003 0.088-0.008l-0.003 0c0.236-0.001 0.452-0.084 0.622-0.222l-0.002 0.002 4.5-3.55c0.202-0.162 0.339-0.398 0.37-0.665l0-0.005c0.003-0.030 0.005-0.065 0.005-0.1 0-0.235-0.081-0.451-0.217-0.622l0.002 0.002zM20.69 13.57c-0.918-1.1-2.197-1.872-3.652-2.134l-0.038-0.006v-1.43c0-0.552-0.448-1-1-1s-1 0.448-1 1v0 1.43c-1.493 0.268-2.772 1.040-3.682 2.13l-0.008 0.010-2.85-2.25c1.022-1.012 2.215-1.854 3.532-2.477l0.078-0.033c1.159-0.509 2.51-0.805 3.93-0.805s2.771 0.296 3.994 0.83l-0.064-0.025c1.395 0.656 2.588 1.498 3.611 2.511l-0.001-0.001z"></path> </svg>', // Simple person/weight placeholder
            'battery_capacity' => '<svg version="1.1" class="spec-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><title>battery-charging</title><path d="M5 17h-2c-0.276 0-0.525-0.111-0.707-0.293s-0.293-0.431-0.293-0.707v-8c0-0.276 0.111-0.525 0.293-0.707s0.431-0.293 0.707-0.293h3.19c0.552 0 1-0.448 1-1s-0.448-1-1-1h-3.19c-0.828 0-1.58 0.337-2.121 0.879s-0.879 1.293-0.879 2.121v8c0 0.828 0.337 1.58 0.879 2.121s1.293 0.879 2.121 0.879h2c0.552 0 1-0.448 1-1s-0.448-1-1-1zM15 7h2c0.276 0 0.525 0.111 0.707 0.293s0.293 0.431 0.293 0.707v8c0 0.276-0.111 0.525-0.293 0.707s-0.431 0.293-0.707 0.293h-3.19c-0.552 0-1 0.448-1 1s0.448 1 1 1h3.19c0.828 0 1.58-0.337 2.121-0.879s0.879-1.293 0.879-2.121v-8c0-0.828-0.337-1.58-0.879-2.121s-1.293-0.879-2.121-0.879h-2c-0.552 0-1 0.448-1 1s0.448 1 1 1zM24 13v-2c0-0.552-0.448-1-1-1s-1 0.448-1 1v2c0 0.552 0.448 1 1 1s1-0.448 1-1zM10.168 5.445l-4 6c-0.306 0.46-0.182 1.080 0.277 1.387 0.172 0.115 0.367 0.169 0.555 0.168h4.131l-2.964 4.445c-0.306 0.46-0.182 1.080 0.277 1.387s1.080 0.182 1.387-0.277l4-6c0.106-0.156 0.169-0.348 0.169-0.555 0-0.552-0.448-1-1-1h-4.131l2.964-4.445c0.306-0.46 0.182-1.080-0.277-1.387s-1.080-0.182-1.387 0.277z"></path></svg>', // Simple battery placeholder
            'nominal_motor_wattage' => '<svg version="1.1" class="spec-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 32 32"> <title>Motor</title> <path d="M18.35 2.35h-12.613c-0.573 0-1.037 0.464-1.037 1.037s0.464 1.037 1.037 1.037h5.389c-1.137 0.712-2.162 1.587-3.043 2.591h-7.047c-0.573 0-1.037 0.464-1.037 1.037s0.464 1.037 1.037 1.037h5.547c-1.196 2.028-1.884 4.391-1.884 6.912 0 2.933 0.93 5.652 2.51 7.88h-2.942c-0.573 0-1.037 0.464-1.037 1.037s0.464 1.037 1.037 1.037h4.751c0.646 0.606 1.352 1.151 2.106 1.623h-9.982c-0.573 0-1.037 0.464-1.037 1.037s0.464 1.037 1.037 1.037h17.206c7.527 0 13.65-6.123 13.65-13.65s-6.123-13.65-13.65-13.65zM18.35 27.576c-6.383 0-11.576-5.193-11.576-11.576s5.193-11.576 11.576-11.576 11.576 5.193 11.576 11.576-5.193 11.577-11.576 11.577z"></path> <path d="M18.35 7.532c-4.669 0-8.468 3.799-8.468 8.468 0 2.785 1.351 5.259 3.432 6.804 0.019 0.016 0.038 0.032 0.059 0.047 0.023 0.017 0.047 0.032 0.071 0.047 1.385 0.988 3.079 1.57 4.907 1.57s3.521-0.582 4.907-1.57c0.024-0.015 0.048-0.030 0.071-0.047 0.020-0.015 0.040-0.031 0.059-0.047 2.081-1.544 3.432-4.019 3.432-6.804 0-4.669-3.799-8.468-8.468-8.468zM19.387 9.69c2.023 0.331 3.73 1.615 4.643 3.375l-2.685 0.872c-0.466-0.675-1.154-1.185-1.958-1.424v-2.823zM19.915 16c0 0.863-0.702 1.565-1.565 1.565s-1.565-0.702-1.565-1.565c0-0.863 0.702-1.565 1.565-1.565s1.565 0.702 1.565 1.565zM17.313 9.69v2.823c-0.804 0.239-1.492 0.749-1.958 1.424l-2.685-0.872c0.913-1.76 2.62-3.043 4.643-3.375zM13.804 20.493c-1.142-1.156-1.849-2.743-1.849-4.493 0-0.328 0.025-0.65 0.073-0.964l2.686 0.873c-0.001 0.030-0.002 0.061-0.002 0.091 0 0.831 0.281 1.597 0.751 2.211l-1.658 2.282zM18.35 22.395c-1.031 0-2.006-0.246-2.87-0.681l1.658-2.283c0.379 0.134 0.787 0.208 1.211 0.208s0.832-0.074 1.211-0.208l1.659 2.283c-0.863 0.435-1.838 0.681-2.87 0.681zM22.896 20.493l-1.658-2.282c0.471-0.613 0.751-1.38 0.751-2.211 0-0.031-0.002-0.061-0.002-0.091l2.686-0.873c0.048 0.315 0.073 0.636 0.073 0.964 0 1.75-0.706 3.337-1.849 4.493z"></path> </svg>', // Simple power/bolt placeholder
            // Add more icons here if needed
        ];

        foreach ( $spec_fields as $field_key => $field_label ) {
            $value = get_field( $field_key, $product_id );
            if ( $value !== null && $value !== '' ) {
                $unit = '';
                 switch ($field_key) {
                    case 'tested_top_speed': $unit = ' MPH'; break;
                    case 'tested_range_regular': $unit = ' miles'; break;
                    case 'weight': $unit = ' lbs'; break;
                    case 'max_load': $unit = ' lbs'; break;
                    case 'battery_capacity': $unit = ' Wh'; break;
                    case 'nominal_motor_wattage': $unit = 'W'; break;
                }
                // *** Use the specific icon from the array, or the default if not found ***
                $icon_svg = $spec_icons[$field_key] ?? $default_icon;

                $key_specs[] = [
                    'label' => $field_label,
                    'value' => esc_html( $value ) . $unit,
                    'icon'  => $icon_svg // Assign the retrieved SVG
                ];
            }
        }

        // --- Get Review Data ---
        $review_data = get_field('review', $product_id);
        if ( !empty($review_data) && is_array($review_data) ) {
            $review_post_object_or_id = $review_data['review_post'] ?? null;
            if ( $review_post_object_or_id ) $review_permalink = get_permalink( $review_post_object_or_id );
            $temp_youtube_url = $review_data['youtube_video'] ?? null;
            if ( !empty($temp_youtube_url) && filter_var($temp_youtube_url, FILTER_VALIDATE_URL) ) $youtube_url = $temp_youtube_url;
        }

    } // End check if $product_post is valid (after get_post)
} // End check for $product_id


// --- Prepare Classes ---
$classes = ['listicle-item-block'];
if ( ! empty( $block['className'] ) ) {
    $classes[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
    $classes[] = 'align' . $block['align'];
}

// Determine if the bottom row should be shown
$show_bottom_row = !empty($review_permalink) || !empty($youtube_url) || ($best_price_info && $best_price_info['is_available']);

$rating_labels = [ // Define rating labels here or load from elsewhere
    'speed' => 'Speed',
    'acceleration_hills' => 'Acceleration & Hills',
    'range' => 'Range',
    'portability' => 'Portability',
    'ride_quality' => 'Ride Quality',
    'build_quality' => 'Build Quality',
    'safety' => 'Safety',
    'features' => 'Features',
    'value' => 'Value',
    'overall' => 'Overall',
];

?>
<div id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">

    <div class="listicle-item-header">
		<div class="listicle-header-left">
			<?php if ( $label ) : ?>
				<span class="listicle-item-label"><?php echo esc_html( $label ); ?></span>
			<?php endif; ?>
			<?php if ( $product_title ) : ?>
				<h3 class="listicle-item-title wp-block-heading" data-heading-label="<?php echo esc_html( $label ); ?>"><?php echo esc_html( $product_title ); ?></h3>
			<?php endif; ?>
		</div>

        <?php if ( $best_price_info ) : ?>
            <a href="<?php echo esc_url( $best_price_info['url'] ?? '#' ); ?>" class="listicle-item-price-button afftrigger <?php echo $best_price_info['is_available'] ? '' : 'is-unavailable'; ?>" target="_blank" rel="noopener sponsored">
                 <?php if ( $best_price_info['is_available'] ): ?>
                     <span class="price-cta">
                        $<?php echo esc_html( number_format( $best_price_info['price'], 2 ) ); ?>
                        at <?php echo esc_html( $best_price_info['pretty_domain'] ?? 'View' ); ?>
                     </span>
                 <?php else: ?>
                     <span class="price-unavailable">Check Price</span>
                 <?php endif; ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="listicle-item-image-wrapper">
        <?php if ( $item_image ) : ?>
            <?php
                $image_id = is_numeric($item_image) ? $item_image : (isset($item_image['ID']) ? $item_image['ID'] : null);
                if ($image_id) {
                    echo wp_get_attachment_image( $image_id, 'large', false, ['class' => 'listicle-item-image'] );
                }
            ?>
        <?php endif; ?>

        <?php if ( $product_rating_overall ) : ?>
			<div class="listicle-item-rating-overlay">
				<div class="rating-circle">
					<svg viewBox="0 0 36 36" class="rating-circle-svg">
						<defs>
							<linearGradient id="rating-gradient" x1="0%" y1="0%" x2="0%" y2="100%">
								<stop offset="0%" stop-color="#5e2ced"></stop>
								<stop offset="100%" stop-color="#a32ded"></stop>
							</linearGradient>
						</defs>
						<path class="rating-circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"></path>
						<path class="rating-circle-progress" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" 
							stroke="url(#rating-gradient)"
							stroke-dasharray="<?php echo esc_attr($product_rating_overall * 10); ?>, 100"></path>
					</svg>
					<span class="rating-value"><?php echo esc_html(number_format($product_rating_overall, 1)); ?></span>
				</div>
			</div>
		<?php endif; ?>
    </div>

    <?php // Main content tabs - only show if product post was loaded ?>
    <?php if ($product_post): ?>
        <div class="listicle-item-tabs">
            <div class="tab-list" role="tablist" aria-label="Product Information">
                <button type="button" class="tab-button is-active" role="tab" aria-selected="true" aria-controls="<?php echo esc_attr( $overview_panel_id ); ?>" id="<?php echo esc_attr( $overview_tab_id ); ?>">
                    Overview
                </button>
                <button
                    type="button"
                    class="tab-button"
                    role="tab"
                    aria-selected="false"
                    aria-controls="<?php echo esc_attr( $specs_panel_id ); ?>"
                    id="<?php echo esc_attr( $specs_tab_id ); ?>"
                    data-product-id="<?php echo esc_attr( $product_id ); ?>"
                    data-spinner-id="<?php echo esc_attr( $specs_spinner_container_id ); ?>"
                    data-error-id="<?php echo esc_attr( $specs_error_message_id ); ?>"
                    data-target-id="<?php echo esc_attr( $specs_content_target_id ); ?>"
                    data-loaded="false"
                >
                    Specs & Tests
                </button>
                 <button
                    type="button"
                    class="tab-button"
                    role="tab"
                    aria-selected="false"
                    aria-controls="<?php echo esc_attr( $price_history_panel_id ); ?>"
                    id="<?php echo esc_attr( $price_history_tab_id ); ?>"
                    data-product-id="<?php echo esc_attr( $product_id ); ?>"
                    data-chart-canvas-id="<?php echo esc_attr( $price_chart_canvas_id ); ?>"
                    data-tracking-info-id="<?php echo esc_attr( $chart_tracking_info_id ); ?>"
                    data-summary-stats-id="<?php echo esc_attr( $chart_summary_stats_id ); ?>"
                    data-spinner-id="<?php echo esc_attr( $chart_spinner_container_id ); ?>"
                    data-loaded="false"
                 >
                    Price History
					
                    <?php
                    // Display the price difference indicator if applicable
                    if ( $price_diff_percent !== null && $price_diff_percent != 0 && $price_diff_percent != -100 && $price_diff_percent != 100 ) :
                        $diff_class = $price_diff_percent > 0 ? 'is-positive' : 'is-negative'; // Positive (more expensive) = red, Negative (cheaper) = green
                        $formatted_percent = ($price_diff_percent > 0 ? '+' : '') . number_format( $price_diff_percent, 1 ) . '%';
                        ?>
                        <span class="price-diff-indicator <?php echo esc_attr( $diff_class ); ?>">
                            <?php echo esc_html( $formatted_percent ); ?>
                        </span>
                    <?php endif; ?>
                </button>
            </div>

            <div class="tab-panels">
                <?php // --- Overview Panel --- ?>
                <div class="tab-panel is-active" id="<?php echo esc_attr( $overview_panel_id ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $overview_tab_id ); ?>" tabindex="0">
                    <?php if ( $quick_take ) : ?>
                        <div class="listicle-item-quick-take">
                            <p><?php echo nl2br( esc_html( $quick_take ) ); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $key_specs ) ) : ?>
                        <div class="listicle-item-key-specs">
                            <?php foreach ( $key_specs as $spec ) : ?>
                                <div class="key-spec-item">
									<?php echo $spec['icon']; ?>
                                    <div class="spec-text">
										<span class="spec-title"><?php echo esc_html( $spec['label'] ); ?></span>
										<span class="spec-value"><?php echo $spec['value']; ?></span>
									</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="listicle-item-pros-cons">
                        <?php if ( $what_i_like ): ?>
                        <div class="pros-section">
                            <h4>What I like</h4>
                            <?php echo render_textarea_list( $what_i_like, 'pros-list', 'pros-item', 'pros' ); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ( $what_i_dont_like ): ?>
                        <div class="cons-section">
                            <h4>What I don't like</h4>
                             <?php echo render_textarea_list( $what_i_dont_like, 'cons-list', 'cons-item', 'cons' ); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ( $body_text ) : ?>
						<div class="listicle-item-body-text">
							<?php echo wpautop( wp_kses_post( $body_text ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $applicable_coupon ) : ?>
						<div class="listicle-coupon-inline">
							<div class="coupon-code-wrapper">
								<button class="coupon-code-button" onclick="copyListicleCoupon('<?php echo esc_js($applicable_coupon['code']); ?>', this)">
									<svg class="coupon-copy-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
										<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
									</svg>
									<span class="coupon-code-text"><?php echo esc_html($applicable_coupon['code']); ?></span>
								</button>
								<span class="coupon-description"><?php echo esc_html($applicable_coupon['description']); ?></span>
								<?php if ( $coupon_retailer && $applicable_coupon['url'] ) : ?>
									<a href="<?php echo esc_url($applicable_coupon['url']); ?>" class="coupon-use-link afftrigger" target="_blank" rel="noopener sponsored">
										Use at <?php echo esc_html($coupon_retailer); ?> →
									</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
                </div>
				
				
				<?php // --- Specifications Panel (Now with placeholders for AJAX) --- ?>
                <div class="tab-panel specs-panel" id="<?php echo esc_attr( $specs_panel_id ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $specs_tab_id ); ?>" tabindex="0" hidden>
                    <div class="chart-spinner-container" id="<?php echo esc_attr($specs_spinner_container_id); ?>" style="display: none;"> <?php /* Reusing chart spinner style */ ?>
                        <div class="chart-spinner"></div>
                    </div>
                    <div class="chart-error-message" id="<?php echo esc_attr($specs_error_message_id); ?>" style="display: none;"></div> <?php /* Reusing chart error style */ ?>
                    <div class="specs-content-target" id="<?php echo esc_attr($specs_content_target_id); ?>">
                        <?php // Content will be loaded here by JS ?>
                    </div>
                </div>
				
				
                <div class="tab-panel price-history-panel" id="<?php echo esc_attr( $price_history_panel_id ); ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( $price_history_tab_id ); ?>" tabindex="0" hidden>
                    <div class="chart-spinner-container" id="<?php echo esc_attr($chart_spinner_container_id); ?>" style="display: none;">
                        <div class="chart-spinner"></div>
                    </div>
                    <div class="chart-error-message" style="display: none; text-align: center; padding: 20px; color: red;"></div>
                    <div class="chart-tracking-info" id="<?php echo esc_attr($chart_tracking_info_id); ?>"></div>
                    <div class="chart-container" style="position: relative; height:300px; width:100%; display: none;">
                        <canvas id="<?php echo esc_attr( $price_chart_canvas_id ); ?>"></canvas>
                    </div>
                    <div class="chart-summary-stats" id="<?php echo esc_attr($chart_summary_stats_id); ?>"></div>
                </div>
            </div>
        </div>
    <?php endif; // End check for $product_post ?>

    <?php // --- Bottom Row for Review Links --- ?>
    <?php if ($show_bottom_row): ?>
        <div class="listicle-item-bottom-row">
            <?php if ($review_permalink): ?>
                <a href="<?php echo esc_url($review_permalink); ?>" class="review-link full-review-link" target="_blank" rel="noopener">
                    <svg class="listicle-item-bottom-row-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-file-text"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
					Full Review
                </a>
            <?php endif; ?>
            <?php if ($youtube_url): ?>
				<button class="review-link video-review-link" data-youtube-url="<?php echo esc_url($youtube_url); ?>" data-modal-id="video-modal-<?php echo esc_attr( $block_id ); ?>">
					 <svg class="listicle-item-bottom-row-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-youtube"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"></path><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"></polygon></svg>
					 Video Review
				</button>
			<?php endif; ?>
			
			<?php
            // --- Add the Best Price Link to the right ---
            if ( $best_price_info && $best_price_info['is_available'] ):
                $bottom_link_text = sprintf(
                    '$%s at %s',
                    esc_html( number_format( $best_price_info['price'], 2 ) ),
                    esc_html( $best_price_info['pretty_domain'] ?? 'View' ) // Use pretty domain if available
                );
            ?>
                <a href="<?php echo esc_url( $best_price_info['url'] ?? '#' ); ?>" class="review-link bottom-price-link afftrigger" target="_blank" rel="noopener sponsored">
                    <?php echo $bottom_link_text; ?>
                    <svg class="listicle-item-bottom-row-icon icon-external-link" viewBox="0 0 24 24"><use xlink:href="#icon-external-link"></use></svg>
                </a>
            <?php endif; ?>
			
        </div>
    <?php endif; // End check for $show_bottom_row ?>
	
	<!-- Video Modal -->
	<div id="video-modal-<?php echo esc_attr( $block_id ); ?>" class="video-modal">
		<div class="video-modal-content">
			<button type="button" class="video-modal-close">&times;</button>
			<div class="video-container">
				<!-- Video iframe will be inserted here via JavaScript -->
			</div>
		</div>
	</div>

</div>
