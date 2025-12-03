<?php

function product_data_cron_job() {
    global $wpdb;

    $product_types = array('Electric Scooter','Electric Bike','Electric Skateboard'); // Add more product types as needed
    $table_name = $wpdb->prefix . 'product_data';

    // Empty the table
    $wpdb->query("TRUNCATE TABLE $table_name");

    foreach ($product_types as $product_type) {
        $args = array(
            'post_type' => 'products',
            'meta_query' => array(
                array(
                    'key' => 'product_type',
                    'value' => $product_type,
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1
        );

        $products = new WP_Query($args);

        if ($products->have_posts()) {
            while ($products->have_posts()) {
                $products->the_post();
                $product_id = get_the_ID();

                $product_data = array(
                    'id' => $product_id,
                    'product_type' => $product_type,
                    'name' => get_the_title(),
                    'specs' => get_fields(),
                    'price' => null,
                    'instock' => '',
                    'permalink' => get_permalink(),
                    'image_url' => '',
                    'rating' => null,
					'popularity_score' => 0,
					'bestlink' => ''
                );

                $prices = getPrices($product_id);
                $product_data['price'] = (isset($prices[0]['price']) && is_numeric($prices[0]['price']) && $prices[0]['price'] > 0) ? $prices[0]['price'] : null;
                $product_data['instock'] = isset($prices[0]['stock_status']) ? ($prices[0]['stock_status'] == 1 ? 1 : 0) : '';
				$product_data['bestlink'] = isset($prices[0]['url']) ? $prices[0]['url'] : '';
				
				if($product_data['instock'] == 1){
					$product_data['popularity_score'] += 5;
				}

                $image_id = get_field('big_thumbnail', $product_id);
				if ($image_id) {
					$product_data['image_url'] = wp_get_attachment_image_url($image_id, 'thumbnail-150') 
						?: wp_get_attachment_image_url($image_id, 'thumbnail');
				}

                // Price history calculation
				$price_history = $wpdb->get_results($wpdb->prepare(
					"SELECT price, date 
					FROM {$wpdb->prefix}product_daily_prices 
					WHERE product_id = %d 
					ORDER BY date DESC",
					$product_id
				), ARRAY_A);

				if ($price_history) {
					// Get current date for comparison
					$now = new DateTime();
					
					// Initialize arrays for different time periods
					$prices_all = [];
					$prices_12m = [];
					$prices_6m = [];
					$prices_3m = [];
					
					// Single loop to categorize prices by time period
					foreach ($price_history as $entry) {
						if (is_numeric($entry['price'])) {
							$price = floatval($entry['price']);
							$prices_all[] = $price;
							
							// Calculate date difference
							$entry_date = new DateTime($entry['date']);
							$diff_days = $now->diff($entry_date)->days;
							
							// Add to appropriate time period arrays
							if ($diff_days <= 365) {
								$prices_12m[] = $price;
								if ($diff_days <= 180) {
									$prices_6m[] = $price;
									if ($diff_days <= 90) {
										$prices_3m[] = $price;
									}
								}
							}
						}
					}
					
					if (!empty($prices_all)) {
						// Calculate all-time statistics
						$mean = round(array_sum($prices_all) / count($prices_all), 2);
						$variance = round(array_sum(array_map(function($x) use ($mean) { 
							return pow($x - $mean, 2); 
						}, $prices_all)) / count($prices_all), 2);
						$std_dev = round(sqrt($variance), 2);
						
						$product_data['price_history'] = array(
							'average_price' => $mean,
							'lowest_price' => strval(round(min($prices_all), 2)),
							'highest_price' => strval(round(max($prices_all), 2)),
							'std_dev' => $std_dev
						);
						
						// Add time-period averages if data exists
						if (!empty($prices_12m)) {
							$product_data['price_history']['average_price_12m'] = round(array_sum($prices_12m) / count($prices_12m), 2);
						}
						
						if (!empty($prices_6m)) {
							$product_data['price_history']['average_price_6m'] = round(array_sum($prices_6m) / count($prices_6m), 2);
						}
						
						if (!empty($prices_3m)) {
							$product_data['price_history']['average_price_3m'] = round(array_sum($prices_3m) / count($prices_3m), 2);
						}
						
						$current_price = $product_data['price'];
						
						if ($std_dev > 0) {
							$z_score = round(($current_price - $mean) / $std_dev, 2);
							$product_data['price_history']['z_score'] = $z_score;
						} else {
							$product_data['price_history']['z_score'] = 0; // No variation in price
						}
						
						$product_data['price_history']['price_difference'] = round(($mean - $current_price), 2);
						$product_data['price_history']['price_difference_percentage'] = round((($current_price - $mean) / $mean) * 100, 2);
					}
				}

                // Advanced comparisons calculation
                $specs = $product_data['specs'];
                
                // Check if this is an e-bike and extract nested fields if so
                if ($product_type === 'Electric Bike' && isset($specs['e-bikes'])) {
                    $ebike_data = $specs['e-bikes'];
                    
                    if (!empty($product_data['price'])) {
                        $price = floatval($product_data['price']);
                        
                        // E-bike specific price comparisons
                        
                        // Price vs Motor Power (Nominal)
                        if (isset($ebike_data['motor']['power_nominal']) && is_numeric($ebike_data['motor']['power_nominal'])) {
                            $power_nominal = floatval($ebike_data['motor']['power_nominal']);
                            $product_data['specs']['price_per_watt_nominal'] = $power_nominal > 0 ? round($price / $power_nominal, 2) : null;
                        }
                        
                        // Price vs Motor Power (Peak)
                        if (isset($ebike_data['motor']['power_peak']) && is_numeric($ebike_data['motor']['power_peak'])) {
                            $power_peak = floatval($ebike_data['motor']['power_peak']);
                            $product_data['specs']['price_per_watt_peak'] = $power_peak > 0 ? round($price / $power_peak, 2) : null;
                        }
                        
                        // Price vs Torque
                        if (isset($ebike_data['motor']['torque']) && is_numeric($ebike_data['motor']['torque'])) {
                            $torque = floatval($ebike_data['motor']['torque']);
                            $product_data['specs']['price_per_nm_torque'] = $torque > 0 ? round($price / $torque, 2) : null;
                        }
                        
                        // Price vs Battery Capacity
                        if (isset($ebike_data['battery']['battery_capacity']) && is_numeric($ebike_data['battery']['battery_capacity'])) {
                            $battery_capacity = floatval($ebike_data['battery']['battery_capacity']);
                            $product_data['specs']['price_per_wh_battery'] = $battery_capacity > 0 ? round($price / $battery_capacity, 2) : null;
                        }
                        
                        // Price vs Range
                        if (isset($ebike_data['battery']['range']) && is_numeric($ebike_data['battery']['range'])) {
                            $range = floatval($ebike_data['battery']['range']);
                            $product_data['specs']['price_per_mile_range'] = $range > 0 ? round($price / $range, 2) : null;
                        }
                        
                        // Price vs Weight
                        if (isset($ebike_data['weight_and_capacity']['weight']) && is_numeric($ebike_data['weight_and_capacity']['weight'])) {
                            $weight = floatval($ebike_data['weight_and_capacity']['weight']);
                            $product_data['specs']['price_per_lb'] = $weight > 0 ? round($price / $weight, 2) : null;
                        }
                        
                        // Price vs Weight Limit
                        if (isset($ebike_data['weight_and_capacity']['weight_limit']) && is_numeric($ebike_data['weight_and_capacity']['weight_limit'])) {
                            $weight_limit = floatval($ebike_data['weight_and_capacity']['weight_limit']);
                            $product_data['specs']['price_per_lb_capacity'] = $weight_limit > 0 ? round($price / $weight_limit, 2) : null;
                        }
                        
                        // Price vs Gears
                        if (isset($ebike_data['drivetrain']['gears']) && is_numeric($ebike_data['drivetrain']['gears'])) {
                            $gears = floatval($ebike_data['drivetrain']['gears']);
                            $product_data['specs']['price_per_gear'] = $gears > 0 ? round($price / $gears, 2) : null;
                        }
                        
                        // Price vs Top Assist Speed
                        if (isset($ebike_data['speed_and_class']['top_assist_speed']) && is_numeric($ebike_data['speed_and_class']['top_assist_speed'])) {
                            $top_assist_speed = floatval($ebike_data['speed_and_class']['top_assist_speed']);
                            $product_data['specs']['price_per_mph_assist'] = $top_assist_speed > 0 ? round($price / $top_assist_speed, 2) : null;
                        }
                    }
                    
                    // E-bike weight-based comparisons (if weight exists)
                    if (isset($ebike_data['weight_and_capacity']['weight']) && is_numeric($ebike_data['weight_and_capacity']['weight'])) {
                        $weight = floatval($ebike_data['weight_and_capacity']['weight']);
                        
                        // Range per pound
                        if (isset($ebike_data['battery']['range']) && is_numeric($ebike_data['battery']['range'])) {
                            $range = floatval($ebike_data['battery']['range']);
                            $product_data['specs']['range_per_lb'] = $weight > 0 ? round($range / $weight, 2) : null;
                        }
                        
                        // Battery capacity per pound
                        if (isset($ebike_data['battery']['battery_capacity']) && is_numeric($ebike_data['battery']['battery_capacity'])) {
                            $battery_capacity = floatval($ebike_data['battery']['battery_capacity']);
                            $product_data['specs']['wh_per_lb'] = $weight > 0 ? round($battery_capacity / $weight, 2) : null;
                        }
                        
                        // Power per pound
                        if (isset($ebike_data['motor']['power_nominal']) && is_numeric($ebike_data['motor']['power_nominal'])) {
                            $power_nominal = floatval($ebike_data['motor']['power_nominal']);
                            $product_data['specs']['watts_per_lb'] = $weight > 0 ? round($power_nominal / $weight, 2) : null;
                        }
                    }
                    
                } else {
                    // Original e-scooter/skateboard calculations
                    if (!empty($product_data['price'])) {
                        $price = floatval($product_data['price']);
                        
                        // Price vs Weight
                        if (isset($specs['weight']) && is_numeric($specs['weight'])) {
                            $weight = floatval($specs['weight']);
                            $product_data['specs']['price_per_lb'] = $weight > 0 ? round($price / $weight, 2) : null;
                        }
                        
                        // Price vs Manufacturer Top Speed
                        if (isset($specs['manufacturer_top_speed']) && is_numeric($specs['manufacturer_top_speed'])) {
                            $top_speed = floatval($specs['manufacturer_top_speed']);
                            $product_data['specs']['price_per_mph'] = $top_speed > 0 ? round($price / $top_speed, 2) : null;
                        }
                        
                        // Price vs Manufacturer Range
                        if (isset($specs['manufacturer_range']) && is_numeric($specs['manufacturer_range'])) {
                            $range = floatval($specs['manufacturer_range']);
                            $product_data['specs']['price_per_mile_range'] = $range > 0 ? round($price / $range, 2) : null;
                        }
                        
                        // Price vs Battery Capacity
                        if (isset($specs['battery_capacity']) && is_numeric($specs['battery_capacity'])) {
                            $battery_capacity = floatval($specs['battery_capacity']);
                            $product_data['specs']['price_per_wh'] = $battery_capacity > 0 ? round($price / $battery_capacity, 2) : null;
                        }

                        // Price vs Motor Power
                        if (isset($specs['nominal_motor_wattage']) && is_numeric($specs['nominal_motor_wattage'])) {
                            $motor_power = floatval($specs['nominal_motor_wattage']);
                            $product_data['specs']['price_per_watt'] = $motor_power > 0 ? round($price / $motor_power, 2) : null;
                        }

                        // Price vs Payload Capacity
                        if (isset($specs['max_load']) && is_numeric($specs['max_load'])) {
                            $max_weight = floatval($specs['max_load']);
                            $product_data['specs']['price_per_lb_capacity'] = $max_weight > 0 ? round($price / $max_weight, 2) : null;
                        }
                        
                        // Price vs Tested Range Regular
                        if (isset($specs['tested_range_regular']) && is_numeric($specs['tested_range_regular'])) {
                            $tested_range = floatval($specs['tested_range_regular']);
                            $product_data['specs']['price_per_tested_mile'] = $tested_range > 0 ? round($price / $tested_range, 2) : null;
                        }

                        // Price vs Tested Top Speed
                        if (isset($specs['tested_top_speed']) && is_numeric($specs['tested_top_speed'])) {
                            $tested_speed = floatval($specs['tested_top_speed']);
                            $product_data['specs']['price_per_tested_mph'] = $tested_speed > 0 ? round($price / $tested_speed, 2) : null;
                        }

                        // Price vs Brake Distance
                        if (isset($specs['brake_distance']) && is_numeric($specs['brake_distance'])) {
                            $brake_distance = floatval($specs['brake_distance']);
                            $product_data['specs']['price_per_brake_ft'] = $brake_distance > 0 ? round($price / $brake_distance, 2) : null;
                        }

                        // Price vs Hill Climbing
                        if (isset($specs['hill_climbing']) && is_numeric($specs['hill_climbing'])) {
                            $hill_climbing = floatval($specs['hill_climbing']);
                            $product_data['specs']['price_per_hill_degree'] = $hill_climbing > 0 ? round($price / $hill_climbing, 2) : null;
                        }

                        // Price vs Acceleration
                        $acceleration_speeds = ['0-15', '0-20', '0-25', '0-30'];
                        foreach ($acceleration_speeds as $speed) {
                            $original_field_key = "acceleration:_{$speed}_mph";
                            
                            if (isset($specs[$original_field_key]) && is_numeric($specs[$original_field_key])) {
                                $acceleration = floatval($specs[$original_field_key]);
                                $product_data['specs']["price_per_acc_{$speed}_mph"] = $acceleration > 0 ? round($price / $acceleration, 2) : null;
                            }
                        }
                        
                        // Handle acceleration_0-to-top separately
                        if (isset($specs["acceleration:_0-to-top"]) && is_numeric($specs["acceleration:_0-to-top"])) {
                            $acceleration = floatval($specs["acceleration:_0-to-top"]);
                            $product_data['specs']["price_per_acc_0-to-top"] = $acceleration > 0 ? round($price / $acceleration, 2) : null;
                        }
                    }
                    
                    // Weight-based comparisons for scooters
                    if (isset($specs['weight']) && is_numeric($specs['weight'])) {
                        $weight = floatval($specs['weight']);

                        // Weight vs Speed
                        if (isset($specs['manufacturer_top_speed']) && is_numeric($specs['manufacturer_top_speed'])) {
                            $top_speed = floatval($specs['manufacturer_top_speed']);
                            $product_data['specs']['speed_per_lb'] = $weight > 0 ? round($top_speed / $weight, 2) : null;
                        }

                        // Weight vs Range
                        if (isset($specs['manufacturer_range']) && is_numeric($specs['manufacturer_range'])) {
                            $range = floatval($specs['manufacturer_range']);
                            $product_data['specs']['range_per_lb'] = $weight > 0 ? round($range / $weight, 2) : null;
                        }
                        if (isset($specs['tested_range_regular']) && is_numeric($specs['tested_range_regular'])) {
                            $tested_range = floatval($specs['tested_range_regular']);
                            $product_data['specs']['tested_range_per_lb'] = $weight > 0 ? round($tested_range / $weight, 2) : null;
                        }
                    }

                    // Weight limit comparisons for scooters
                    if (isset($specs['max_weight_capacity']) && is_numeric($specs['max_weight_capacity'])) {
                        $weight_limit = floatval($specs['max_weight_capacity']);

                        // Weight Limit vs Speed
                        if (isset($specs['manufacturer_top_speed']) && is_numeric($specs['manufacturer_top_speed'])) {
                            $top_speed = floatval($specs['manufacturer_top_speed']);
                            $product_data['specs']['speed_per_lb_capacity'] = $weight_limit > 0 ? round($top_speed / $weight_limit, 2) : null;
                        }

                        // Weight Limit vs Range
                        if (isset($specs['manufacturer_range']) && is_numeric($specs['manufacturer_range'])) {
                            $range = floatval($specs['manufacturer_range']);
                            $product_data['specs']['range_per_lb_capacity'] = $weight_limit > 0 ? round($range / $weight_limit, 2) : null;
                        }
                    }
                }

                // Clean acceleration field names (remove colons)
                $acceleration_fields_map = [
                    'acceleration:_0-15_mph' => 'acceleration_0-15_mph',
                    'acceleration:_0-20_mph' => 'acceleration_0-20_mph',
                    'acceleration:_0-25_mph' => 'acceleration_0-25_mph',
                    'acceleration:_0-30_mph' => 'acceleration_0-30_mph',
                    'acceleration:_0-to-top' => 'acceleration_0-to-top'
                ];

                foreach ($acceleration_fields_map as $old_key => $new_key) {
                    if (isset($product_data['specs'][$old_key])) {
                        $product_data['specs'][$new_key] = $product_data['specs'][$old_key];
                        unset($product_data['specs'][$old_key]);
                    }
                }
				
                // Get average rating
                $reviews = getReviews($product_id);
                if (isset($reviews['ratings_distribution']['average_rating']) && 
                    is_numeric($reviews['ratings_distribution']['average_rating'])) {
                    $rating = $reviews['ratings_distribution']['average_rating'];
                    $product_data['rating'] = $rating > 0 ? $rating : null;
                    
                    $avg_rating = $reviews['ratings_distribution']['average_rating'];
                    // Check if ratings array exists and is an array before using array_sum
                    if (isset($reviews['ratings_distribution']['ratings']) && is_array($reviews['ratings_distribution']['ratings'])) {
                        $review_count = array_sum($reviews['ratings_distribution']['ratings']);
                        $product_data['popularity_score'] += $avg_rating + $review_count * 2;
                    }
                }

                $tracker_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}price_trackers WHERE product_id = %d",
                        $product_id
                    ));
                    $product_data['popularity_score'] += $tracker_count * 2;
                    
                // 3. Release year freshness boost
                $release_year = get_field('release_year', $product_id);
				$current_year = (int) date('Y');
				$last_year = $current_year - 1;
				
				$views_30d = $wpdb->get_var($wpdb->prepare(
					"SELECT COUNT(DISTINCT ip_hash) FROM {$wpdb->prefix}product_views 
					WHERE product_id = %d 
					AND view_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
					$product_id
				));

				if ($views_30d > 0) {
					$view_boost = log($views_30d + 1) * 3.5;
					$product_data['popularity_score'] += round($view_boost, 1);
				}

				if ($release_year == $current_year) {
					$product_data['popularity_score'] += 10;
				} elseif ($release_year == $last_year) {
					$product_data['popularity_score'] += 5;
				}

                // Insert or update the product data in the database
                insert_or_update_product_data($product_data);
            }
        }

        wp_reset_postdata();
    }
    
    // Update modified date for specific posts after all product data is processed
    $posts_to_update = array(14781, 14699, 14641,17310,17249);
    
    foreach ($posts_to_update as $post_id) {
        // Check if the post exists
        if (get_post($post_id)) {
            // Update the post's modified date to current time
            wp_update_post(array(
                'ID' => $post_id,
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => current_time('mysql', 1)
            ));
            
        }
    }
}



function insert_or_update_product_data($product_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'product_data';
	
    $existing_product = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE product_id = %d", $product_data['id']));
    $data_to_update = array(
        'product_type' => $product_data['product_type'],
        'name' => $product_data['name'],
        'specs' => maybe_serialize($product_data['specs']),
        'price' => $product_data['price'],
        'instock' => $product_data['instock'],
        'permalink' => $product_data['permalink'],
        'image_url' => $product_data['image_url'],
        'last_updated' => current_time('mysql'),
        'price_history' => maybe_serialize($product_data['price_history']),
        'popularity_score' => $product_data['popularity_score'],
		'bestlink' => $product_data['bestlink']
    );
    // Only include rating if it's not null
    if ($product_data['rating'] !== null) {
        $data_to_update['rating'] = $product_data['rating'];
    }
    if ($existing_product) {
        $wpdb->update(
            $table_name,
            $data_to_update,
            array('product_id' => $product_data['id'])
        );
    } else {
        $data_to_update['product_id'] = $product_data['id'];
        $wpdb->insert($table_name, $data_to_update);
    }
}