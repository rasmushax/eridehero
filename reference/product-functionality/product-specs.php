<?php
function getSpecs($id) {
    // Retrieve all the fields for the given product ID
    $fields = get_fields($id);
    $prices = getPrices($id);
    $price = (isset($prices[0]['price']) && $prices[0]['price'] !== '' && is_numeric($prices[0]['price'])) ? $prices[0]['price'] : null;

    // Check product type
    if (!isset($fields['product_type'])) {
        return [];
    }
    
    $product_type = $fields['product_type'];
    
    // Route to appropriate handler based on product type
    if ($product_type === 'Electric Scooter') {
        return getScooterSpecs($fields, $price);
    } elseif ($product_type === 'Electric Bike') {
        return getEbikeSpecs($fields, $price);
    }
    
    return [];
}

function getScooterSpecs($fields, $price) {
    // Initialize the specs array with new categories
    $specs = [
        'Product Information' => [],
        'Motor' => [],
        'Battery' => [],
        'Dimensions & Weight' => [],
        'Ride Characteristics' => [],
        'Safety & Durability' => [],
        'Features' => [],
        'ERideHero Tests' => [],
        'Advanced Comparison' => []
    ];

    // Product Information
    if (!empty($fields['brand'])) {
        $specs['Product Information']['Brand'] = [
            'title' => 'Brand',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['brand']
        ];
    }

    if (!empty($fields['model'])) {
        $specs['Product Information']['Name'] = [
            'title' => 'Name',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['model']
        ];
    }

    // Add price if available
    if ($price !== null) {
        $specs['Product Information']['Price'] = [
            'title' => 'Price',
            'type' => 'number',
            'prefix' => '$',
            'suffix' => null,
            'value' => $price
        ];
    }

    if (!empty($fields['release_year'])) {
        $specs['Product Information']['Release Year'] = [
            'title' => 'Release Year',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['release_year']
        ];
    }

    if (!empty($fields['release_quarter'])) {
        $specs['Product Information']['Release Quarter'] = [
            'title' => 'Release Quarter',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['release_quarter']
        ];
    }

    // Motor specs
    if (!empty($fields['manufacturer_top_speed'])) {
        $specs['Motor']['Top Speed'] = [
            'title' => 'Top Speed',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' MPH',
            'value' => $fields['manufacturer_top_speed']
        ];
    }

    if (isset($fields['motors']) && is_array($fields['motors']) && !empty($fields['motors'])) {
        $specs['Motor']['Motor(s)'] = [
            'title' => 'Motor(s)',
            'type' => 'checkbox',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['motors'][0]
        ];
    }

    if (!empty($fields['nominal_motor_wattage'])) {
        $specs['Motor']['Watts (nominal)'] = [
            'title' => 'Watts (nominal)',
            'type' => 'number',
            'prefix' => null,
            'suffix' => 'W',
            'value' => $fields['nominal_motor_wattage']
        ];
    }

    if (!empty($fields['total_peak_wattage'])) {
        $specs['Motor']['Watts (peak)'] = [
            'title' => 'Watts (peak)',
            'type' => 'number',
            'prefix' => null,
            'suffix' => 'W',
            'value' => $fields['total_peak_wattage']
        ];
    }

    if (!empty($fields['max_incline'])) {
        $specs['Motor']['Max Incline'] = [
            'title' => 'Max Incline',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '°',
            'value' => $fields['max_incline']
        ];
    }

    if (!empty($fields['ideal_incline'])) {
        $specs['Motor']['Ideal Incline'] = [
            'title' => 'Ideal Incline',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '°',
            'value' => $fields['ideal_incline']
        ];
    }
    
    // Battery specs
    if (!empty($fields['manufacturer_range'])) {
        $specs['Battery']['Max Range'] = [
            'title' => 'Max Range',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' miles',
            'value' => $fields['manufacturer_range']
        ];
    }

    if (!empty($fields['battery_type'])) {
        $specs['Battery']['Battery Type'] = [
            'title' => 'Battery Type',
            'type' => 'select',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['battery_type']
        ];
    }

    if (!empty($fields['battery_voltage'])) {
        $specs['Battery']['Voltage'] = [
            'title' => 'Voltage',
            'type' => 'number',
            'prefix' => null,
            'suffix' => 'V',
            'value' => $fields['battery_voltage']
        ];
    }

    if (!empty($fields['battery_amphours'])) {
        $specs['Battery']['Amp-hours'] = [
            'title' => 'Amp-hours',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' Ah',
            'value' => $fields['battery_amphours']
        ];
    }

    if (!empty($fields['battery_capacity'])) {
        $specs['Battery']['Battery Capacity'] = [
            'title' => 'Battery Capacity',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' Wh',
            'value' => $fields['battery_capacity']
        ];
    }

    if (!empty($fields['charging_time'])) {
        $specs['Battery']['Charge Time'] = [
            'title' => 'Charge Time',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' hrs',
            'value' => $fields['charging_time']
        ];
    }

    if (!empty($fields['battery_brand'])) {
        $specs['Battery']['Cell Brand'] = [
            'title' => 'Cell Brand',
            'type' => 'select',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['battery_brand']
        ];
    }
    
    // Dimensions & Weight
    if (!empty($fields['deck_length'])) {
        $specs['Dimensions & Weight']['Deck Length'] = [
            'title' => 'Deck Length',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['deck_length']
        ];
    }

    if (!empty($fields['deck_width'])) {
        $specs['Dimensions & Weight']['Deck Width'] = [
            'title' => 'Deck Width',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['deck_width']
        ];
    }

    if (!empty($fields['ground_clearance'])) {
        $specs['Dimensions & Weight']['Ground Clearance'] = [
            'title' => 'Ground Clearance',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['ground_clearance']
        ];
    }
    
    if (!empty($fields['handlebar_width'])) {
        $specs['Dimensions & Weight']['Handlebar Width'] = [
            'title' => 'Handlebar Width',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['handlebar_width']
        ];
    }
    
    if (!empty($fields['unfolded_width'])) {
        $specs['Dimensions & Weight']['Unfolded Width'] = [
            'title' => 'Unfolded Width',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['unfolded_width']
        ];
    }

    if (!empty($fields['unfolded_height'])) {
        $specs['Dimensions & Weight']['Unfolded Height'] = [
            'title' => 'Unfolded Height',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['unfolded_height']
        ];
    }

    if (!empty($fields['unfolded_depth'])) {
        $specs['Dimensions & Weight']['Unfolded Depth'] = [
            'title' => 'Unfolded Depth',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['unfolded_depth']
        ];
    }

    if (!empty($fields['folded_width'])) {
        $specs['Dimensions & Weight']['Folded Width'] = [
            'title' => 'Folded Width',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['folded_width']
        ];
    }

    if (!empty($fields['folded_height'])) {
        $specs['Dimensions & Weight']['Folded Height'] = [
            'title' => 'Folded Height',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['folded_height']
        ];
    }

    if (!empty($fields['folded_depth'])) {
        $specs['Dimensions & Weight']['Folded Depth'] = [
            'title' => 'Folded Depth',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['folded_depth']
        ];
    }
    
    // Handlebar height logic
    $min_height = $fields['deck_to_handlebar_min'] ?? null;
    $max_height = $fields['deck_to_handlebar_max'] ?? null;

    $is_adjustable = false;
    $handlebar_height = null;

    if ($min_height !== null && $max_height !== null) {
        if ($min_height != $max_height) {
            $is_adjustable = true;
        } else {
            $handlebar_height = $min_height;
        }
    } elseif ($min_height !== null) {
        $handlebar_height = $min_height;
    } elseif ($max_height !== null) {
        $handlebar_height = $max_height;
    }

    // Add adjustable handlebar height boolean
    $specs['Dimensions & Weight']['Adjustable Handlebar'] = [
        'title' => 'Adjustable Handlebar',
        'type' => 'boolean',
        'prefix' => null,
        'suffix' => null,
        'value' => $is_adjustable ? 1 : 0
    ];

    // Add handlebar height information
    if ($is_adjustable) {
        $specs['Dimensions & Weight']['Deck-to-Handlebar Height (Min)'] = [
            'title' => 'Deck-to-Handlebar Height (Min)',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $min_height
        ];
        $specs['Dimensions & Weight']['Deck-to-Handlebar Height (Max)'] = [
            'title' => 'Deck-to-Handlebar Height (Max)',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $max_height
        ];
    } elseif ($handlebar_height !== null) {
        $specs['Dimensions & Weight']['Deck-to-Handlebar Height'] = [
            'title' => 'Deck-to-Handlebar Height',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $handlebar_height
        ];
    }

    if (!empty($fields['weight'])) {
        $specs['Dimensions & Weight']['Weight'] = [
            'title' => 'Weight',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' lbs',
            'value' => $fields['weight']
        ];
    }

    if (!empty($fields['max_load'])) {
        $specs['Dimensions & Weight']['Weight Limit'] = [
            'title' => 'Weight Limit',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' lbs',
            'value' => $fields['max_load']
        ];
    }

    // Ride Characteristics
    if (!empty($fields['terrain'])) {
        $specs['Ride Characteristics']['Terrain'] = [
            'title' => 'Terrain',
            'type' => 'multiselect',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['terrain']
        ];
    }
    
    if (isset($fields['footrest'])) {
        $specs['Ride Characteristics']['Footrest'] = [
            'title' => 'Footrest',
            'type' => 'boolean',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['footrest'] ? 1 : 0
        ];
    }

    if (isset($fields['tires']) && is_array($fields['tires']) && !empty($fields['tires'])) {
        $specs['Ride Characteristics']['Tire Type'] = [
            'title' => 'Tire Type',
            'type' => 'multiselect',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $fields['tires'])
        ];
    }

    if (!empty($fields['pneumatic_type'])) {
        $specs['Ride Characteristics']['Pneumatic Type'] = [
            'title' => 'Pneumatic Type',
            'type' => 'multiselect',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['pneumatic_type']
        ];
    }
    
    if (!empty($fields['tire_size_front'])) {
        $specs['Ride Characteristics']['Tire Size (Front)'] = [
            'title' => 'Tire Size (Front)',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['tire_size_front']
        ];
    }
    
    if (!empty($fields['tire_size_rear'])) {
        $specs['Ride Characteristics']['Tire Size (Rear)'] = [
            'title' => 'Tire Size (Rear)',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['tire_size_rear']
        ];
    } elseif (!empty($fields['tire_size_front'])) {
        $specs['Ride Characteristics']['Tire Size (Rear)'] = [
            'title' => 'Tire Size (Rear)',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['tire_size_front']
        ];
    }
    
    if (!empty($fields['tire_width'])) {
        $specs['Ride Characteristics']['Tire Width'] = [
            'title' => 'Tire Width',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $fields['tire_width']
        ];
    }

    if (isset($fields['suspension']) && is_array($fields['suspension']) && !empty($fields['suspension'])) {
        $specs['Ride Characteristics']['Suspension'] = [
            'title' => 'Suspension',
            'type' => 'multiselect',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $fields['suspension'])
        ];
    }

    // Safety & Durability
    if (isset($fields['brakes']) && is_array($fields['brakes']) && !empty($fields['brakes'])) {
        $specs['Safety & Durability']['Brakes'] = [
            'title' => 'Brakes',
            'type' => 'multiselect',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $fields['brakes'])
        ];
    }

    if (!empty($fields['fold_location'])) {
        $specs['Safety & Durability']['Fold Location'] = [
            'title' => 'Fold Location',
            'type' => 'multiselect',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['fold_location']
        ];
    }

    if (isset($fields['lights']) && is_array($fields['lights']) && !empty($fields['lights'])) {
        $specs['Safety & Durability']['Lights'] = [
            'title' => 'Lights',
            'type' => 'multiselect',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $fields['lights'])
        ];
    }

    if (isset($fields['weather_resistance']) && is_array($fields['weather_resistance']) && !empty($fields['weather_resistance'])) {
        $specs['Safety & Durability']['IP Rating'] = [
            'title' => 'IP Rating',
            'type' => 'multiselect',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $fields['weather_resistance'])
        ];
    }
    
    // Features
    $all_features = [
        'App',
        'Speed Modes',
        'Cruise Control',
        'Quick-Swap Battery',
        'Folding Mechanism',
        'Seat Add-On',
        'Foldable Handlebars',
        'Zero-Start',
        'Push-To-Start',
        'Turn Signals',
        'Brake Curve Adjustment',
        'Acceleration Adjustment',
        'Self-healing tires',
        'Speed limiting',
        'Over-the-air firmware updates',
        'Adjustable suspension',
        'Steering dampener',
        'Location tracking',
        'Electronic horn',
        'NFC Unlock'
    ];

    foreach ($all_features as $feature) {
        $specs['Features'][$feature] = [
            'title' => $feature,
            'type' => 'boolean',
            'prefix' => null,
            'suffix' => null,
            'value' => in_array($feature, $fields['features'] ?? []) ? 1 : 0
        ];
    }

    // ERideHero Tests
    $erideHeroTests = [
        'tested_top_speed' => ['Top Speed Test', 'MPH'],
        'acceleration_0-15_mph' => ['Accel. Avg. (0-15 MPH)', 's'],
        'acceleration_0-20_mph' => ['Accel. Avg. (0-20 MPH)', 's'],
        'acceleration_0-25_mph' => ['Accel. Avg. (0-25 MPH)', 's'],
        'acceleration_0-30_mph' => ['Accel. Avg. (0-30 MPH)', 's'],
        'acceleration_0-to-top' => ['Accel. Avg. (0-Top)', 's'],
        'fastest_0_15' => ['Accel. Fastest (0-15 MPH)', 's'],
        'fastest_0_20' => ['Accel. Fastest (0-20 MPH)', 's'],
        'fastest_0_25' => ['Accel. Fastest (0-25 MPH)', 's'],
        'fastest_0_30' => ['Accel. Fastest (0-30 MPH)', 's'],
        'fastest_0_top' => ['Accel. Fastest (0-Top)', 's'],
        'tested_range_fast' => ['Range Test (Fast)', 'miles'],
        'tested_range_regular' => ['Range Test (Regular)', 'miles'],
        'tested_range_slow' => ['Range Test (Slow)', 'miles'],
        'brake_distance' => ['Brake Distance (15 MPH)', 'ft'],
        'hill_climbing' => ['Hill Climbing', 's']
    ];

    foreach ($erideHeroTests as $key => $value) {
		$field_value = null;
		
		// Check the standard field name first
		if (!empty($fields[$key])) {
			$field_value = $fields[$key];
		}
		// For acceleration fields, also check the old format with colon
		elseif (strpos($key, 'acceleration_') === 0) {
			$old_key = str_replace('acceleration_', 'acceleration:_', $key);
			if (!empty($fields[$old_key])) {
				$field_value = $fields[$old_key];
			}
		}
		
		if ($field_value !== null) {
			$specs['ERideHero Tests'][$value[0]] = [
				'title' => $value[0],
				'type' => 'number',
				'prefix' => null,
				'suffix' => ' ' . $value[1],
				'value' => $field_value
			];
		}
	}

    // Advanced Comparison
    if ($price !== null) {
        $advancedComparisons = [
            'price_per_lb' => ['Price vs. Weight', '$/lb', $fields['weight'] ?? null],
            'price_per_mph' => ['Price vs. Speed', '$/MPH', $fields['manufacturer_top_speed'] ?? null],
            'price_per_mile_range' => ['Price vs. Range', '$/mile', $fields['manufacturer_range'] ?? null],
            'price_per_wh' => ['Price vs. Wh', '$/Wh', $fields['battery_capacity'] ?? null],
            'price_per_watt' => ['Price vs. Watt', '$/W', $fields['nominal_motor_wattage'] ?? null],
            'price_per_lb_capacity' => ['Price vs. Max Load', '$/lb', $fields['max_load'] ?? null],
            'price_per_tested_mile' => ['Price vs. Tested Range', '$/mile', $fields['tested_range_regular'] ?? null],
            'price_per_tested_mph' => ['Price vs. Tested Speed', '$/MPH', $fields['tested_top_speed'] ?? null],
            'price_per_brake_ft' => ['Price vs. Brake Distance', '$/ft', $fields['brake_distance'] ?? null],
            'price_per_hill_degree' => ['Price vs. Hill Test', '$/s', $fields['hill_climbing'] ?? null],
            'price_per_acc_0-15_mph' => ['Price vs. Accel (15 MPH)', '$/s', $fields['acceleration:_0-15_mph'] ?? null],
            'price_per_acc_0-20_mph' => ['Price vs. Accel (20 MPH)', '$/s', $fields['acceleration:_0-20_mph'] ?? null],
            'price_per_acc_0-25_mph' => ['Price vs. Accel (25 MPH)', '$/s', $fields['acceleration:_0-25_mph'] ?? null],
            'price_per_acc_0-30_mph' => ['Price vs. Accel (30 MPH)', '$/s', $fields['acceleration:_0-30_mph'] ?? null],
        ];

        foreach ($advancedComparisons as $key => $value) {
            if ($value[2] !== null && $value[2] > 0) {
                $calculatedValue = $price / $value[2];
                $specs['Advanced Comparison'][$value[0]] = [
                    'title' => $value[0],
                    'type' => 'number',
                    'prefix' => null,
                    'suffix' => ' ' . $value[1],
                    'value' => round($calculatedValue, 2)
                ];
            }
        }
    }

    // Non-price based comparisons
    $nonPriceComparisons = [
        'speed_per_lb' => ['Speed vs. Weight', 'MPH/lb', $fields['manufacturer_top_speed'] ?? null, $fields['weight'] ?? null],
        'range_per_lb' => ['Range vs. Weight', 'mile/lb', $fields['manufacturer_range'] ?? null, $fields['weight'] ?? null],
        'tested_range_per_lb' => ['Tested Range vs. Weight', 'mile/lb', $fields['tested_range_regular'] ?? null, $fields['weight'] ?? null],
        'speed_per_lb_capacity' => ['Speed vs. Max Load', 'MPH/lb', $fields['manufacturer_top_speed'] ?? null, $fields['max_load'] ?? null],
        'range_per_lb_capacity' => ['Range vs. Max Load', 'mile/lb', $fields['manufacturer_range'] ?? null, $fields['max_load'] ?? null]
    ];

    foreach ($nonPriceComparisons as $key => $value) {
        if (is_numeric($value[2]) && is_numeric($value[3]) && $value[2] !== null && $value[3] !== null && $value[3] > 0) {
            $calculatedValue = $value[2] / $value[3];
            $specs['Advanced Comparison'][$value[0]] = [
                'title' => $value[0],
                'type' => 'number',
                'prefix' => null,
                'suffix' => ' ' . $value[1],
                'value' => round($calculatedValue, 2)
            ];
        }
    }

    // After populating all specs, remove empty categories
    foreach ($specs as $category => $items) {
        if (empty($items)) {
            unset($specs[$category]);
        }
    }

    return $specs;
}

function getEbikeSpecs($fields, $price) {
    // Initialize the specs array with e-bike appropriate categories
    $specs = [
        'Product Information' => [],
        'Motor' => [],
        'Battery' => [],
        'Speed & Class' => [],
        'Drivetrain' => [],
        'Brakes' => [],
        'Frame & Geometry' => [],
        'Wheels & Tires' => [],
        'Suspension' => [],
        'Dimensions & Weight' => [],
        'Components' => [],
        'Integrated Features' => [],
        'Safety & Compliance' => [],
        'Advanced Comparison' => []
    ];
    
    // Extract e-bike data
    $ebike = $fields['e-bikes'] ?? [];
    
    // Product Information
    if (!empty($fields['brand'])) {
        $specs['Product Information']['Brand'] = [
            'title' => 'Brand',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['brand']
        ];
    }

    if (!empty($fields['model'])) {
        $specs['Product Information']['Name'] = [
            'title' => 'Name',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['model']
        ];
    }

    if ($price !== null) {
        $specs['Product Information']['Price'] = [
            'title' => 'Price',
            'type' => 'number',
            'prefix' => '$',
            'suffix' => null,
            'value' => $price
        ];
    }

    if (!empty($fields['release_year'])) {
        $specs['Product Information']['Release Year'] = [
            'title' => 'Release Year',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $fields['release_year']
        ];
    }
    
	if (!empty($ebike['category']) && is_array($ebike['category'])) {
		$specs['Product Information']['Category'] = [
			'title' => 'Category',
			'type' => 'text',
			'prefix' => null,
			'suffix' => null,
			'value' => implode(', ', $ebike['category'])
		];
	}
    
    // Motor
    if (!empty($ebike['motor']['motor_type'])) {
        $specs['Motor']['Motor Type'] = [
            'title' => 'Motor Type',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['motor']['motor_type']
        ];
    }
    
    if (!empty($ebike['motor']['motor_position'])) {
        $specs['Motor']['Motor Position'] = [
            'title' => 'Motor Position',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['motor']['motor_position']
        ];
    }
    
    if (!empty($ebike['motor']['motor_brand'])) {
        $specs['Motor']['Motor Brand'] = [
            'title' => 'Motor Brand',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['motor']['motor_brand']
        ];
    }
    
    if (!empty($ebike['motor']['motor_model'])) {
        $specs['Motor']['Motor Model'] = [
            'title' => 'Motor Model',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['motor']['motor_model']
        ];
    }
    
    if (!empty($ebike['motor']['power_nominal'])) {
        $specs['Motor']['Power (Nominal)'] = [
            'title' => 'Power (Nominal)',
            'type' => 'number',
            'prefix' => null,
            'suffix' => 'W',
            'value' => $ebike['motor']['power_nominal']
        ];
    }
    
    if (!empty($ebike['motor']['power_peak'])) {
        $specs['Motor']['Power (Peak)'] = [
            'title' => 'Power (Peak)',
            'type' => 'number',
            'prefix' => null,
            'suffix' => 'W',
            'value' => $ebike['motor']['power_peak']
        ];
    }
    
    if (!empty($ebike['motor']['torque'])) {
        $specs['Motor']['Torque'] = [
            'title' => 'Torque',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' Nm',
            'value' => $ebike['motor']['torque']
        ];
    }
    
    if (!empty($ebike['motor']['assist_levels'])) {
        $specs['Motor']['Assist Levels'] = [
            'title' => 'Assist Levels',
            'type' => 'number',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['motor']['assist_levels']
        ];
    }
    
    if (!empty($ebike['motor']['sensor_type'])) {
        $specs['Motor']['Sensor Type'] = [
            'title' => 'Sensor Type',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['motor']['sensor_type']
        ];
    }
    
    // Battery
    if (!empty($ebike['battery']['battery_capacity'])) {
        $specs['Battery']['Battery Capacity'] = [
            'title' => 'Battery Capacity',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' Wh',
            'value' => $ebike['battery']['battery_capacity']
        ];
    }
    
    if (!empty($ebike['battery']['voltage'])) {
        $specs['Battery']['Voltage'] = [
            'title' => 'Voltage',
            'type' => 'number',
            'prefix' => null,
            'suffix' => 'V',
            'value' => $ebike['battery']['voltage']
        ];
    }
    
    if (!empty($ebike['battery']['amphours'])) {
        $specs['Battery']['Amp-hours'] = [
            'title' => 'Amp-hours',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' Ah',
            'value' => $ebike['battery']['amphours']
        ];
    }
    
    if (!empty($ebike['battery']['battery_position'])) {
        $specs['Battery']['Battery Position'] = [
            'title' => 'Battery Position',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['battery']['battery_position']
        ];
    }
    
    if (isset($ebike['battery']['removable'])) {
        $specs['Battery']['Removable Battery'] = [
            'title' => 'Removable Battery',
            'type' => 'boolean',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['battery']['removable'] ? 1 : 0
        ];
    }
    
    if (!empty($ebike['battery']['range'])) {
        $specs['Battery']['Max Range'] = [
            'title' => 'Max Range',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' miles',
            'value' => $ebike['battery']['range']
        ];
    }
    
    if (!empty($ebike['battery']['charge_time'])) {
        $specs['Battery']['Charge Time'] = [
            'title' => 'Charge Time',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' hrs',
            'value' => $ebike['battery']['charge_time']
        ];
    }
    
    // Speed & Class
    if (!empty($ebike['speed_and_class']['class']) && is_array($ebike['speed_and_class']['class'])) {
        $specs['Speed & Class']['Class'] = [
            'title' => 'Class',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $ebike['speed_and_class']['class'])
        ];
    }
    
    if (!empty($ebike['speed_and_class']['top_assist_speed'])) {
        $specs['Speed & Class']['Top Assist Speed'] = [
            'title' => 'Top Assist Speed',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' MPH',
            'value' => $ebike['speed_and_class']['top_assist_speed']
        ];
    }
    
    if (!empty($ebike['speed_and_class']['throttle_top_speed'])) {
        $specs['Speed & Class']['Throttle Top Speed'] = [
            'title' => 'Throttle Top Speed',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' MPH',
            'value' => $ebike['speed_and_class']['throttle_top_speed']
        ];
    }
    
    if (isset($ebike['speed_and_class']['throttle'])) {
        $specs['Speed & Class']['Throttle'] = [
            'title' => 'Throttle',
            'type' => 'boolean',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['speed_and_class']['throttle'] ? 1 : 0
        ];
    }
    
    // Drivetrain
    if (!empty($ebike['drivetrain']['gears'])) {
        $specs['Drivetrain']['Gears'] = [
            'title' => 'Gears',
            'type' => 'number',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['drivetrain']['gears']
        ];
    }
    
    if (!empty($ebike['drivetrain']['drive_system'])) {
        $specs['Drivetrain']['Drive System'] = [
            'title' => 'Drive System',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['drivetrain']['drive_system']
        ];
    }
    
    if (!empty($ebike['drivetrain']['derailleur'])) {
        $specs['Drivetrain']['Derailleur'] = [
            'title' => 'Derailleur',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['drivetrain']['derailleur']
        ];
    }
    
    if (!empty($ebike['drivetrain']['cassette'])) {
        $specs['Drivetrain']['Cassette'] = [
            'title' => 'Cassette',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['drivetrain']['cassette']
        ];
    }
    
    if (!empty($ebike['drivetrain']['shifter'])) {
        $specs['Drivetrain']['Shifter'] = [
            'title' => 'Shifter',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['drivetrain']['shifter']
        ];
    }
    
    // Brakes
    if (!empty($ebike['brakes']['brake_type']) && is_array($ebike['brakes']['brake_type'])) {
        $specs['Brakes']['Brake Type'] = [
            'title' => 'Brake Type',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $ebike['brakes']['brake_type'])
        ];
    }
    
    if (!empty($ebike['brakes']['brake_brand'])) {
        $specs['Brakes']['Brake Brand'] = [
            'title' => 'Brake Brand',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['brakes']['brake_brand']
        ];
    }
    
    if (!empty($ebike['brakes']['brake_model'])) {
        $specs['Brakes']['Brake Model'] = [
            'title' => 'Brake Model',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['brakes']['brake_model']
        ];
    }
    
    if (!empty($ebike['brakes']['rotor_size_front'])) {
        $specs['Brakes']['Rotor Size (Front)'] = [
            'title' => 'Rotor Size (Front)',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' mm',
            'value' => $ebike['brakes']['rotor_size_front']
        ];
    }
    
    if (!empty($ebike['brakes']['rotor_size_rear'])) {
        $specs['Brakes']['Rotor Size (Rear)'] = [
            'title' => 'Rotor Size (Rear)',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' mm',
            'value' => $ebike['brakes']['rotor_size_rear']
        ];
    }
    
    // Frame & Geometry
    if (!empty($ebike['frame_and_geometry']['frame_material']) && is_array($ebike['frame_and_geometry']['frame_material'])) {
        $specs['Frame & Geometry']['Frame Material'] = [
            'title' => 'Frame Material',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $ebike['frame_and_geometry']['frame_material'])
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['frame_style']) && is_array($ebike['frame_and_geometry']['frame_style'])) {
        $specs['Frame & Geometry']['Frame Style'] = [
            'title' => 'Frame Style',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $ebike['frame_and_geometry']['frame_style'])
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['sizes_available']) && is_array($ebike['frame_and_geometry']['sizes_available'])) {
        $specs['Frame & Geometry']['Sizes Available'] = [
            'title' => 'Sizes Available',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $ebike['frame_and_geometry']['sizes_available'])
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['min_rider_height'])) {
        $specs['Frame & Geometry']['Min Rider Height'] = [
            'title' => 'Min Rider Height',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['frame_and_geometry']['min_rider_height']
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['max_rider_height'])) {
        $specs['Frame & Geometry']['Max Rider Height'] = [
            'title' => 'Max Rider Height',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['frame_and_geometry']['max_rider_height']
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['total_length'])) {
        $specs['Frame & Geometry']['Total Length'] = [
            'title' => 'Total Length',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['frame_and_geometry']['total_length']
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['min_handlebar_height'])) {
        $specs['Frame & Geometry']['Min Handlebar Height'] = [
            'title' => 'Min Handlebar Height',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['frame_and_geometry']['min_handlebar_height']
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['max_handlebar_height'])) {
        $specs['Frame & Geometry']['Max Handlebar Height'] = [
            'title' => 'Max Handlebar Height',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['frame_and_geometry']['max_handlebar_height']
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['min_seat_height'])) {
        $specs['Frame & Geometry']['Min Seat Height'] = [
            'title' => 'Min Seat Height',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['frame_and_geometry']['min_seat_height']
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['max_seat_height'])) {
        $specs['Frame & Geometry']['Max Seat Height'] = [
            'title' => 'Max Seat Height',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['frame_and_geometry']['max_seat_height']
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['wheelbase'])) {
        $specs['Frame & Geometry']['Wheelbase'] = [
            'title' => 'Wheelbase',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['frame_and_geometry']['wheelbase']
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['handlebar_width'])) {
        $specs['Frame & Geometry']['Handlebar Width'] = [
            'title' => 'Handlebar Width',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['frame_and_geometry']['handlebar_width']
        ];
    }
    
    if (!empty($ebike['frame_and_geometry']['standover_height'])) {
        $specs['Frame & Geometry']['Standover Height'] = [
            'title' => 'Standover Height',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['frame_and_geometry']['standover_height']
        ];
    }
    
    // Wheels & Tires
    if (!empty($ebike['wheels_and_tires']['wheel_size'])) {
        $specs['Wheels & Tires']['Wheel Size'] = [
            'title' => 'Wheel Size',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['wheels_and_tires']['wheel_size']
        ];
    }
    
    if (!empty($ebike['wheels_and_tires']['tire_width'])) {
        $specs['Wheels & Tires']['Tire Width'] = [
            'title' => 'Tire Width',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['wheels_and_tires']['tire_width']
        ];
    }
    
    if (!empty($ebike['wheels_and_tires']['tire_brand'])) {
        $specs['Wheels & Tires']['Tire Brand'] = [
            'title' => 'Tire Brand',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['wheels_and_tires']['tire_brand']
        ];
    }
    
    if (!empty($ebike['wheels_and_tires']['tire_model'])) {
        $specs['Wheels & Tires']['Tire Model'] = [
            'title' => 'Tire Model',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['wheels_and_tires']['tire_model']
        ];
    }
    
    if (!empty($ebike['wheels_and_tires']['tire_type'])) {
        $specs['Wheels & Tires']['Tire Type'] = [
            'title' => 'Tire Type',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['wheels_and_tires']['tire_type']
        ];
    }
    
    if (isset($ebike['wheels_and_tires']['puncture_protection'])) {
        $specs['Wheels & Tires']['Puncture Protection'] = [
            'title' => 'Puncture Protection',
            'type' => 'boolean',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['wheels_and_tires']['puncture_protection'] ? 1 : 0
        ];
    }
    
    // Suspension
    if (!empty($ebike['suspension']['front_suspension'])) {
        $specs['Suspension']['Front Suspension'] = [
            'title' => 'Front Suspension',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['suspension']['front_suspension']
        ];
    }
    
    if (!empty($ebike['suspension']['front_travel'])) {
        $specs['Suspension']['Front Travel'] = [
            'title' => 'Front Travel',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' mm',
            'value' => $ebike['suspension']['front_travel']
        ];
    }
    
    if (!empty($ebike['suspension']['rear_suspension'])) {
        $specs['Suspension']['Rear Suspension'] = [
            'title' => 'Rear Suspension',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['suspension']['rear_suspension']
        ];
    }
    
    if (!empty($ebike['suspension']['rear_travel'])) {
        $specs['Suspension']['Rear Travel'] = [
            'title' => 'Rear Travel',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' mm',
            'value' => $ebike['suspension']['rear_travel']
        ];
    }
    
    if (isset($ebike['suspension']['seatpost_suspension'])) {
        $specs['Suspension']['Seatpost Suspension'] = [
            'title' => 'Seatpost Suspension',
            'type' => 'boolean',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['suspension']['seatpost_suspension'] ? 1 : 0
        ];
    }
    
    // Dimensions & Weight
    if (!empty($ebike['weight_and_capacity']['weight'])) {
        $specs['Dimensions & Weight']['Weight'] = [
            'title' => 'Weight',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' lbs',
            'value' => $ebike['weight_and_capacity']['weight']
        ];
    }
    
    if (!empty($ebike['weight_and_capacity']['weight_limit'])) {
        $specs['Dimensions & Weight']['Weight Limit'] = [
            'title' => 'Weight Limit',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' lbs',
            'value' => $ebike['weight_and_capacity']['weight_limit']
        ];
    }
    
    if (!empty($ebike['weight_and_capacity']['rack_capacity'])) {
        $specs['Dimensions & Weight']['Rack Capacity'] = [
            'title' => 'Rack Capacity',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' lbs',
            'value' => $ebike['weight_and_capacity']['rack_capacity']
        ];
    }
    
    // Components
    if (!empty($ebike['components']['display'])) {
        $specs['Components']['Display'] = [
            'title' => 'Display',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['components']['display']
        ];
    }
    
    if (!empty($ebike['components']['display_size'])) {
        $specs['Components']['Display Size'] = [
            'title' => 'Display Size',
            'type' => 'number',
            'prefix' => null,
            'suffix' => '"',
            'value' => $ebike['components']['display_size']
        ];
    }
    
    if (!empty($ebike['components']['connectivity']) && is_array($ebike['components']['connectivity'])) {
        $specs['Components']['Connectivity'] = [
            'title' => 'Connectivity',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $ebike['components']['connectivity'])
        ];
    }
    
    if (isset($ebike['components']['app_compatible'])) {
        $specs['Components']['App Compatible'] = [
            'title' => 'App Compatible',
            'type' => 'boolean',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['components']['app_compatible'] ? 1 : 0
        ];
    }
    
    // Integrated Features
    $integrated_features = [
        'integrated_lights' => 'Integrated Lights',
        'fenders' => 'Fenders',
        'rear_rack' => 'Rear Rack',
        'front_rack' => 'Front Rack',
        'kickstand' => 'Kickstand',
        'chain_guard' => 'Chain Guard',
        'walk_assist' => 'Walk Assist',
        'alarm' => 'Alarm',
        'usb' => 'USB Charging',
        'bottle_cage_mount' => 'Bottle Cage Mount'
    ];
    
    foreach ($integrated_features as $key => $label) {
        if (isset($ebike['integrated_features'][$key])) {
            $specs['Integrated Features'][$label] = [
                'title' => $label,
                'type' => 'boolean',
                'prefix' => null,
                'suffix' => null,
                'value' => $ebike['integrated_features'][$key] ? 1 : 0
            ];
        }
    }
    
    if (!empty($ebike['integrated_features']['front_light_lumens'])) {
        $specs['Integrated Features']['Front Light Lumens'] = [
            'title' => 'Front Light Lumens',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' lumens',
            'value' => $ebike['integrated_features']['front_light_lumens']
        ];
    }
    
    if (!empty($ebike['integrated_features']['rear_light_lumens'])) {
        $specs['Integrated Features']['Rear Light Lumens'] = [
            'title' => 'Rear Light Lumens',
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' lumens',
            'value' => $ebike['integrated_features']['rear_light_lumens']
        ];
    }
    
    // Safety & Compliance
    if (!empty($ebike['safety_and_compliance']['ip_rating'])) {
        $specs['Safety & Compliance']['IP Rating'] = [
            'title' => 'IP Rating',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => $ebike['safety_and_compliance']['ip_rating']
        ];
    }
    
    if (!empty($ebike['safety_and_compliance']['certifications']) && is_array($ebike['safety_and_compliance']['certifications'])) {
        $specs['Safety & Compliance']['Certifications'] = [
            'title' => 'Certifications',
            'type' => 'text',
            'prefix' => null,
            'suffix' => null,
            'value' => implode(', ', $ebike['safety_and_compliance']['certifications'])
        ];
    }
    
    // Advanced Comparison for E-bikes
    if ($price !== null) {
        $advancedComparisons = [];
        
        // Price comparisons
        if (!empty($ebike['weight_and_capacity']['weight'])) {
            $advancedComparisons['Price vs. Weight'] = ['$/lb', $price / $ebike['weight_and_capacity']['weight']];
        }
        
        if (!empty($ebike['motor']['power_nominal'])) {
            $advancedComparisons['Price vs. Power (Nominal)'] = ['$/W', $price / $ebike['motor']['power_nominal']];
        }
        
        if (!empty($ebike['motor']['power_peak'])) {
            $advancedComparisons['Price vs. Power (Peak)'] = ['$/W', $price / $ebike['motor']['power_peak']];
        }
        
        if (!empty($ebike['motor']['torque'])) {
            $advancedComparisons['Price vs. Torque'] = ['$/Nm', $price / $ebike['motor']['torque']];
        }
        
        if (!empty($ebike['battery']['battery_capacity'])) {
            $advancedComparisons['Price vs. Battery'] = ['$/Wh', $price / $ebike['battery']['battery_capacity']];
        }
        
        if (!empty($ebike['battery']['range'])) {
            $advancedComparisons['Price vs. Range'] = ['$/mile', $price / $ebike['battery']['range']];
        }
        
        if (!empty($ebike['speed_and_class']['top_assist_speed'])) {
            $advancedComparisons['Price vs. Assist Speed'] = ['$/MPH', $price / $ebike['speed_and_class']['top_assist_speed']];
        }
        
        if (!empty($ebike['weight_and_capacity']['weight_limit'])) {
            $advancedComparisons['Price vs. Weight Limit'] = ['$/lb', $price / $ebike['weight_and_capacity']['weight_limit']];
        }
        
        if (!empty($ebike['drivetrain']['gears'])) {
            $advancedComparisons['Price vs. Gears'] = ['$/gear', $price / $ebike['drivetrain']['gears']];
        }
        
        foreach ($advancedComparisons as $title => $data) {
            $specs['Advanced Comparison'][$title] = [
                'title' => $title,
                'type' => 'number',
                'prefix' => null,
                'suffix' => ' ' . $data[0],
                'value' => round($data[1], 2)
            ];
        }
    }
    
    // Non-price comparisons for e-bikes
    $nonPriceComparisons = [];
    
    if (!empty($ebike['battery']['range']) && !empty($ebike['weight_and_capacity']['weight'])) {
        $nonPriceComparisons['Range vs. Weight'] = [
            'mile/lb', 
            $ebike['battery']['range'] / $ebike['weight_and_capacity']['weight']
        ];
    }
    
    if (!empty($ebike['battery']['battery_capacity']) && !empty($ebike['weight_and_capacity']['weight'])) {
        $nonPriceComparisons['Battery vs. Weight'] = [
            'Wh/lb',
            $ebike['battery']['battery_capacity'] / $ebike['weight_and_capacity']['weight']
        ];
    }
    
    if (!empty($ebike['motor']['power_nominal']) && !empty($ebike['weight_and_capacity']['weight'])) {
        $nonPriceComparisons['Power vs. Weight'] = [
            'W/lb',
            $ebike['motor']['power_nominal'] / $ebike['weight_and_capacity']['weight']
        ];
    }
    
    if (!empty($ebike['motor']['torque']) && !empty($ebike['weight_and_capacity']['weight'])) {
        $nonPriceComparisons['Torque vs. Weight'] = [
            'Nm/lb',
            $ebike['motor']['torque'] / $ebike['weight_and_capacity']['weight']
        ];
    }
    
    foreach ($nonPriceComparisons as $title => $data) {
        $specs['Advanced Comparison'][$title] = [
            'title' => $title,
            'type' => 'number',
            'prefix' => null,
            'suffix' => ' ' . $data[0],
            'value' => round($data[1], 2)
        ];
    }
    
    // Remove empty categories
    foreach ($specs as $category => $items) {
        if (empty($items)) {
            unset($specs[$category]);
        }
    }
    
    return $specs;
}