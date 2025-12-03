<?php
/**
 * Improved Product Comparison Plugin
 * 
 * Security improvements:
 * - SQL injection prevention with prepared statements
 * - Proper input sanitization
 * - Output escaping
 * - Better error handling
 * - Code organization and reusability
 * 
 * Dependencies:
 * - Requires getPrices() function to be available globally
 * - Requires afflink() function for affiliate link processing
 */

class ProductComparisonPlugin {
    
    private static $instance = null;
    private $convert = array(
        "mile-km"   => 1.609344,
        "lb-kg"     => 0.45359237,
        "in-cm"     => 2.54,
        "fps-mph"   => 0.6818,
        "ft-m"      => 0.3048,
        "ft-cm"     => 30.48
    );
    
    /**
     * Singleton pattern
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Register all shortcodes
     */
    private function __construct() {
        add_shortcode('graph', array($this, 'graph_func'));
        add_shortcode('stat', array($this, 'getstat_func'));
        add_shortcode('speedcomp', array($this, 'speedcomp_func'));
        add_shortcode('rangetest', array($this, 'rangetest_func'));
        add_shortcode('acceltest', array($this, 'acceltest_func'));
        add_shortcode('accelcomp', array($this, 'accelcomp_func'));
        add_shortcode('hillcomp', array($this, 'hillcomp_func'));
        add_shortcode('rangevsweight', array($this, 'rangevsweight_func'));
        add_shortcode('ipcomp', array($this, 'ipcomp_func'));
        add_shortcode('batcapcomp', array($this, 'batcapcomp_func'));
        add_shortcode('rangecomp', array($this, 'rangecomp_func'));
        add_shortcode('weight', array($this, 'weight_func'));
        add_shortcode('braking', array($this, 'braking_func'));
        add_shortcode('batval', array($this, 'batval_func'));
    }
    
    /**
     * Helper: Convert units safely
     */
    private function convert($value, $multiplier, $decimals = 1) {
        $value = floatval($value);
        $multiplier = floatval($multiplier);
        $decimals = intval($decimals);
        return round($value * $multiplier, $decimals);
    }
    
    /**
     * Helper: Sanitize and validate product IDs
     */
    private function sanitizeProductIds($ids) {
        if (empty($ids)) return array();
        
        $idArray = explode(',', $ids);
        $sanitized = array();
        
        foreach ($idArray as $id) {
            $id = trim($id);
            if ($id === 'this' || (is_numeric($id) && $id > 0)) {
                $sanitized[] = $id;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Helper: Get product fields safely
     */
    private function getProductFields($productId) {
        if ($productId === 'this') {
            $relationship = get_field('relationship');
            if (!empty($relationship) && isset($relationship[0])) {
                return get_fields($relationship[0]);
            }
            return false;
        } elseif (is_numeric($productId) && $productId > 0) {
            return get_fields(intval($productId));
        }
        return false;
    }
    
    /**
     * Helper: Get product ID
     */
    private function getProductId($productId) {
        if ($productId === 'this') {
            $relationship = get_field('relationship');
            return !empty($relationship) && isset($relationship[0]) ? $relationship[0] : false;
        }
        return is_numeric($productId) ? intval($productId) : false;
    }
    
    /**
     * Helper: Get best product price data using the global getPrices function
     */
    private function getProductPrice($productId) {
        if (!$productId) return array('price' => 0, 'url' => '#');
        
        // Use the existing getPrices function
        $prices = getPrices($productId);
        
        if (empty($prices) || !is_array($prices)) {
            return array('price' => 0, 'url' => '#');
        }
        
        // Get the first (best) price from the sorted array
        $bestPrice = $prices[0];
        
        return array(
            'price' => isset($bestPrice['price']) ? floatval($bestPrice['price']) : 0,
            'url' => isset($bestPrice['url']) ? $bestPrice['url'] : '#'
        );
    }
    
    /**
     * Helper: Build HTML table
     */
    private function buildTable($headers, $rows, $caption = '') {
        $html = '<figure class="wp-block-table product minimalistic"><table>';
        
        // Headers
        $html .= '<thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . esc_html($header) . '</th>';
        }
        $html .= '</tr></thead>';
        
        // Body
        $html .= '<tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . $cell . '</td>'; // Already escaped in individual functions
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        if (!empty($caption)) {
            $html .= '<figcaption>' . esc_html($caption) . '</figcaption>';
        }
        
        $html .= '</figure>';
        return $html;
    }
    
    /**
     * Shortcode: [graph]
     */
    public function graph_func($atts) {
        $a = shortcode_atts(array(
            'field' => 'tested_top_speed',
            'products' => '6019,5383,5308,5378',
            'title' => 'Top Speed Comparison',
            'unit' => 'mph',
            'decimals' => 1
        ), $atts);
        
        wp_enqueue_style('Graph-style', plugin_dir_url(__FILE__) . 'assets/css/charts.css');
        
        $productIds = $this->sanitizeProductIds($a['products']);
        if (empty($productIds)) return '';
        
        global $wpdb;
        
        // Prepare SQL with proper escaping
        $field = sanitize_key($a['field']);
        $placeholders = array_fill(0, count($productIds), '%d');
        $sql = $wpdb->prepare(
            "SELECT meta_value, post_id FROM $wpdb->postmeta 
             WHERE meta_key = %s AND post_id IN (" . implode(',', $placeholders) . ")",
            array_merge(array($field), array_map('intval', $productIds))
        );
        
        $productsdata = $wpdb->get_results($sql, ARRAY_A);
        
        if (empty($productsdata)) return '';
        
        $max = max(array_column($productsdata, 'meta_value'));
        if ($max == 0) return '';
        
        $units = array(
            "mph" => array('name' => 'MPH', 'convert_name' => 'KMH', 'convert_val' => 1.609344),
            "miles" => array('name' => 'miles', 'convert_name' => 'km', 'convert_val' => 1.609344),
            "mile" => array('name' => 'mile', 'convert_name' => 'km', 'convert_val' => 1.609344)
        );
        
        $unit = isset($units[$a['unit']]) ? $units[$a['unit']] : $units['mph'];
        $decimals = intval($a['decimals']);
        
        $html = '<div class="graphContainer">';
        $html .= '<div class="graphTitle">' . esc_html($a['title']) . '</div>';
        
        foreach ($productsdata as $prod) {
            $value = floatval($prod['meta_value']);
            $width = ($value / $max) * 100;
            $convertedVal = $this->convert($value, $unit['convert_val'], $decimals);
            $title = get_the_title($prod['post_id']);
            
            $html .= '<div class="graphEntity">';
            $html .= '<div class="graphEntityText">' . esc_html($title) . '</div>';
            $html .= '<div class="graphEntityText">' . esc_html($value) . ' ' . esc_html($unit['name']) . 
                     ' (' . esc_html($convertedVal) . ' ' . esc_html($unit['convert_name']) . ')</div>';
            $html .= '<div class="graphEntityBar">';
            $html .= '<div class="graphEntityBarFill" style="width:' . esc_attr($width) . '%"></div>';
            $html .= '</div></div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Shortcode: [stat]
     */
    public function getstat_func($atts) {
        $productId = $this->getProductId('this');
        
        $a = shortcode_atts(array(
            'stat' => '',
            'products' => $productId,
            'decimals' => 1
        ), $atts);
        
        $fields = $this->getProductFields($a['products']);
        if (!$fields) return '';
        
        $stat = sanitize_key($a['stat']);
        $decimals = intval($a['decimals']);
        
        // Speed stats
        if (in_array($stat, array('manufacturer_top_speed', 'tested_top_speed'))) {
            $value = isset($fields[$stat]) ? floatval($fields[$stat]) : 0;
            if ($value > 0) {
                $km = $this->convert($value, $this->convert['mile-km'], 1);
                return esc_html($value . " MPH (" . $km . " KMH)");
            }
        }
        
        // Range stats
        $rangeStats = array(
            'manufacturer_range' => 'miles',
            'range_fast' => 'tested_range_fast',
            'range_regular' => 'tested_range_regular',
            'range_slow' => 'tested_range_slow'
        );
        
        if (isset($rangeStats[$stat])) {
            $field = $rangeStats[$stat] === 'miles' ? $stat : $rangeStats[$stat];
            $value = isset($fields[$field]) ? floatval($fields[$field]) : 0;
            if ($value > 0) {
                $km = $this->convert($value, $this->convert['mile-km'], 1);
                return esc_html($value . " miles (" . $km . " km)");
            }
        }
        
        // Speed range stats
        $speedRangeStats = array(
            'range_speed_fast' => 'tested_range_avg_speed_fast',
            'range_speed_regular' => 'tested_range_avg_speed_regular',
            'range_speed_slow' => 'tested_range_avg_speed_slow'
        );
        
        if (isset($speedRangeStats[$stat])) {
            $value = isset($fields[$speedRangeStats[$stat]]) ? floatval($fields[$speedRangeStats[$stat]]) : 0;
            if ($value > 0) {
                $km = $this->convert($value, $this->convert['mile-km'], 1);
                return esc_html($value . " MPH (" . $km . " KMH)");
            }
        }
        
        // Acceleration stats
        $accelStats = array(
            'accel_avg_15' => 'acceleration:_0-15_mph',
            'accel_avg_20' => 'acceleration:_0-20_mph',
            'accel_avg_25' => 'acceleration:_0-25_mph',
            'accel_avg_30' => 'acceleration:_0-30_mph',
            'accel_avg_top' => 'acceleration:_0-to-top',
            'accel_15' => 'fastest_0_15',
            'accel_20' => 'fastest_0_20',
            'accel_25' => 'fastest_0_25',
            'accel_30' => 'fastest_0_30',
            'accel_top' => 'fastest_0_top'
        );
        
        if (isset($accelStats[$stat])) {
            $value = isset($fields[$accelStats[$stat]]) ? floatval($fields[$accelStats[$stat]]) : 0;
            if ($value > 0) {
                return esc_html($value . " seconds");
            }
        }
        
        // Weight stats
        if ($stat === 'weight' || $stat === 'maxload') {
            $field = $stat === 'weight' ? 'weight' : 'max_load';
            $value = isset($fields[$field]) ? floatval($fields[$field]) : 0;
            if ($value > 0) {
                $kg = $this->convert($value, $this->convert['lb-kg'], 1);
                return esc_html($value . " lbs (" . $kg . " kg)");
            }
        }
        
        // Battery capacity
        if ($stat === 'wh') {
            $value = isset($fields['battery_capacity']) ? floatval($fields['battery_capacity']) : 0;
            if ($value > 0) {
                return esc_html($value . " Wh");
            }
        }
        
        // Deck dimensions
        if ($stat === 'deck') {
            $length = isset($fields['deck_length']) ? floatval($fields['deck_length']) : 0;
            $width = isset($fields['deck_width']) ? floatval($fields['deck_width']) : 0;
            if ($length > 0 && $width > 0) {
                $lengthCm = $this->convert($length, $this->convert['in-cm'], 1);
                $widthCm = $this->convert($width, $this->convert['in-cm'], 1);
                return esc_html($length . '" x ' . $width . '" (' . $lengthCm . ' cm x ' . $widthCm . ' cm)');
            }
        }
        
        // Ground clearance
        if ($stat === 'ground_clearance') {
            $value = isset($fields['ground_clearance']) ? floatval($fields['ground_clearance']) : 0;
            if ($value > 0) {
                $cm = $this->convert($value, $this->convert['in-cm'], 1);
                return esc_html($value . '" (' . $cm . ' cm)');
            }
        }
        
        // Handlebars
        if ($stat === 'handlebars') {
            $value = isset($fields['handlebar_width']) ? floatval($fields['handlebar_width']) : 0;
            if ($value > 0) {
                $cm = $this->convert($value, $this->convert['in-cm'], 1);
                return esc_html($value . '" (' . $cm . ' cm)');
            }
        }
        
        // Braking
        if ($stat === 'braking') {
            $value = isset($fields['brake_distance']) ? floatval($fields['brake_distance']) : 0;
            if ($value > 0) {
                $m = $this->convert($value, $this->convert['ft-m'], 2);
                return esc_html($value . ' ft (' . $m . ' m)');
            }
        }
        
        // Hill climb
        if ($stat === 'hill_climb' || $stat === 'hill_climb_short') {
            $value = isset($fields['hill_climbing']) ? floatval($fields['hill_climbing']) : 0;
            if ($value > 0) {
                $speed = $this->convert['fps-mph'] * (250 / $value);
                $speedKm = $this->convert($speed, $this->convert['mile-km'], 1);
                
                if ($stat === 'hill_climb') {
                    return esc_html($value . ' seconds with an average speed of ' . 
                                   round($speed, 1) . ' MPH (' . $speedKm . ' KMH)');
                } else {
                    return esc_html($value . ' s (Avg Speed: ' . round($speed, 1) . ' MPH)');
                }
            }
        }
        
        // Dimensions
        if ($stat === 'unfolded' || $stat === 'folded') {
            $prefix = $stat === 'unfolded' ? 'unfolded_' : 'folded_';
            $width = isset($fields[$prefix . 'width']) ? floatval($fields[$prefix . 'width']) : 0;
            $height = isset($fields[$prefix . 'height']) ? floatval($fields[$prefix . 'height']) : 0;
            $depth = isset($fields[$prefix . 'depth']) ? floatval($fields[$prefix . 'depth']) : 0;
            
            if ($width > 0 && $height > 0 && $depth > 0) {
                $widthCm = $this->convert($width, $this->convert['in-cm'], 1);
                $heightCm = $this->convert($height, $this->convert['in-cm'], 1);
                $depthCm = $this->convert($depth, $this->convert['in-cm'], 1);
                
                return esc_html($width . " x " . $height . " x " . $depth . " in (" . 
                               $widthCm . " x " . $heightCm . " x " . $depthCm . " cm)");
            }
        }
        
        return '';
    }
    
    /**
     * Shortcode: [speedcomp]
     */
    public function speedcomp_func($atts) {
        $a = shortcode_atts(array(
            'ids' => "6199,6269,5631",
            'price' => 'no'
        ), $atts);
        
        $productIds = $this->sanitizeProductIds($a['ids']);
        if (empty($productIds)) return '';
        
        $items = array();
        $thisItem = '';
        
        foreach ($productIds as $prodId) {
            $id = $this->getProductId($prodId);
            $fields = $this->getProductFields($prodId);
            
            if (!$fields || !isset($fields['brand']) || !isset($fields['model'])) continue;
            
            $name = $fields['brand'] . ' ' . $fields['model'];
            
            if ($prodId === 'this') {
                $thisItem = $name;
            }
            
            $items[$name] = array(
                'speed' => isset($fields['tested_top_speed']) ? floatval($fields['tested_top_speed']) : 0,
                'id' => $id
            );
            
            if ($a['price'] === 'yes' && $id) {
                $priceData = $this->getProductPrice($id);
                $items[$name]['price'] = $priceData['price'];
                $items[$name]['url'] = $priceData['url'];
            }
        }
        
        if (empty($items)) return '';
        
        $headers = array('Model', 'Top Speed');
        if ($a['price'] === 'yes') {
            $headers[] = '$/Speed';
        }
        
        $rows = array();
        foreach ($items as $name => $item) {
            $row = array();
            
            // Model column
            $modelHtml = ($thisItem === $name) ? '<u>' . esc_html($name) . '</u>' : esc_html($name);
            if ($a['price'] === 'yes' && isset($item['price']) && $item['price'] > 0 && isset($item['url'])) {
                $modelHtml .= '<br/><a href="' . esc_url(afflink($item['url'])) . 
                             '" target="_blank" class="afftrigger" rel="sponsored external noopener">$' . 
                             esc_html(number_format($item['price'], 2)) . ' USD</a>';
            }
            $row[] = $modelHtml;
            
            // Speed column
            if ($item['speed'] > 0) {
                $speedHtml = esc_html($item['speed']) . ' MPH ';
                if ($a['price'] === 'yes') $speedHtml .= '<br />';
                $speedHtml .= '(' . esc_html($this->convert($item['speed'], $this->convert['mile-km'], 1)) . ' KMH)';
            } else {
                $speedHtml = 'N/A';
            }
            $row[] = $speedHtml;
            
            // Price per speed column
            if ($a['price'] === 'yes') {
                if (isset($item['price']) && $item['price'] > 0 && $item['speed'] > 0) {
                    $pricePerSpeed = round($item['price'] / $item['speed'], 2);
                    $row[] = '<a href="' . esc_url(afflink($item['url'])) . 
                            '" target="_blank" class="afftrigger" rel="sponsored external noopener">$' . 
                            esc_html($pricePerSpeed) . '/MPH</a>';
                } else {
                    $row[] = '-';
                }
            }
            
            $rows[] = $row;
        }
        
        return $this->buildTable($headers, $rows);
    }
    
    /**
     * Shortcode: [rangetest]
     */
    public function rangetest_func($atts) {
        $a = shortcode_atts(array(
            'id' => get_the_id()
        ), $atts);
        
        $productId = $this->getProductId('this');
        if (!$productId) return '';
        
        $fields = $this->getProductFields('this');
        if (!$fields) return '';
        
        $headers = array('Test (#)', 'Range', 'Avg. Speed');
        $rows = array();
        
        $tests = array(
            array(
                'name' => '#1: Speed Priority',
                'range_field' => 'tested_range_fast',
                'speed_field' => 'tested_range_avg_speed_fast'
            ),
            array(
                'name' => '#2: Regular',
                'range_field' => 'tested_range_regular',
                'speed_field' => 'tested_range_avg_speed_regular'
            ),
            array(
                'name' => '#3: Range Priority',
                'range_field' => 'tested_range_slow',
                'speed_field' => 'tested_range_avg_speed_slow'
            )
        );
        
        foreach ($tests as $test) {
            $range = isset($fields[$test['range_field']]) ? floatval($fields[$test['range_field']]) : 0;
            $speed = isset($fields[$test['speed_field']]) ? floatval($fields[$test['speed_field']]) : 0;
            
            if ($range > 0 && $speed > 0) {
                $rangeKm = $this->convert($range, $this->convert['mile-km'], 1);
                $speedKm = $this->convert($speed, $this->convert['mile-km'], 1);
                
                $rows[] = array(
                    esc_html($test['name']),
                    esc_html($range . ' miles') . '<br />' . esc_html($rangeKm . ' km'),
                    esc_html($speed . ' MPH') . '<br />' . esc_html($speedKm . ' KMH')
                );
            }
        }
        
        if (empty($rows)) return '';
        
        return $this->buildTable($headers, $rows);
    }
    
    /**
     * Shortcode: [acceltest]
     */
    public function acceltest_func($atts) {
        $a = shortcode_atts(array(
            'id' => get_the_id()
        ), $atts);
        
        $productId = $this->getProductId('this');
        if (!$productId) return '';
        
        $fields = $this->getProductFields('this');
        if (!$fields) return '';
        
        $headers = array('Interval', 'Average');
        
        // Check if we have "Best" times
        $hasBest = false;
        $bestFields = array('fastest_0_15', 'fastest_0_20', 'fastest_0_25', 'fastest_0_30', 'fastest_0_top');
        foreach ($bestFields as $field) {
            if (!empty($fields[$field])) {
                $hasBest = true;
                break;
            }
        }
        
        if ($hasBest) {
            $headers[] = 'Best';
        }
        
        $rows = array();
        
        $tests = array(
            array('interval' => '0-15 MPH (24 KMH)', 'avg' => 'acceleration:_0-15_mph', 'best' => 'fastest_0_15'),
            array('interval' => '0-20 MPH (32.2 KMH)', 'avg' => 'acceleration:_0-20_mph', 'best' => 'fastest_0_20'),
            array('interval' => '0-25 MPH (40.2 KMH)', 'avg' => 'acceleration:_0-25_mph', 'best' => 'fastest_0_25'),
            array('interval' => '0-30 MPH (48.2 KMH)', 'avg' => 'acceleration:_0-30_mph', 'best' => 'fastest_0_30')
        );
        
        foreach ($tests as $test) {
            if (!empty($fields[$test['avg']])) {
                $row = array(
                    esc_html($test['interval']),
                    esc_html($fields[$test['avg']] . ' s')
                );
                
                if ($hasBest) {
                    $row[] = !empty($fields[$test['best']]) ? esc_html($fields[$test['best']] . ' s') : '';
                }
                
                $rows[] = $row;
            }
        }
        
        // Top speed acceleration
        if (!empty($fields['acceleration:_0-to-top']) && !empty($fields['tested_top_speed'])) {
            $topSpeed = floatval($fields['tested_top_speed']);
            $topSpeedKm = $this->convert($topSpeed, $this->convert['mile-km'], 1);
            
            $row = array(
                esc_html('0-' . $topSpeed . ' MPH (' . $topSpeedKm . ' KMH)'),
                esc_html($fields['acceleration:_0-to-top'] . ' s')
            );
            
            if ($hasBest) {
                $row[] = !empty($fields['fastest_0_top']) ? esc_html($fields['fastest_0_top'] . ' s') : '';
            }
            
            $rows[] = $row;
        }
        
        if (empty($rows)) return '';
        
        return $this->buildTable($headers, $rows);
    }
    
    /**
     * Shortcode: [accelcomp]
     */
    public function accelcomp_func($atts) {
        $a = shortcode_atts(array(
            'ids' => "6199,6269,5631",
            'speeds' => ''
        ), $atts);
        
        $productIds = $this->sanitizeProductIds($a['ids']);
        if (empty($productIds)) return '';
        
        $items = array();
        $speedsToShow = array();
        
        // Get data for all products
        foreach ($productIds as $prodId) {
            $fields = $this->getProductFields($prodId);
            if (!$fields || !isset($fields['brand']) || !isset($fields['model'])) continue;
            
            $name = $fields['brand'] . ' ' . $fields['model'];
            
            $speedData = array(
                '15' => isset($fields['acceleration:_0-15_mph']) ? $fields['acceleration:_0-15_mph'] : '',
                '20' => isset($fields['acceleration:_0-20_mph']) ? $fields['acceleration:_0-20_mph'] : '',
                '25' => isset($fields['acceleration:_0-25_mph']) ? $fields['acceleration:_0-25_mph'] : '',
                '30' => isset($fields['acceleration:_0-30_mph']) ? $fields['acceleration:_0-30_mph'] : ''
            );
            
            // Track which speeds have data
            foreach ($speedData as $speed => $value) {
                if (!empty($value)) {
                    $speedsToShow[$speed] = true;
                }
            }
            
            $items[$name] = $speedData;
        }
        
        // Override with specific speeds if provided
        if (!empty($a['speeds'])) {
            $speedsToShow = array();
            $requestedSpeeds = explode(',', $a['speeds']);
            foreach ($requestedSpeeds as $speed) {
                $speed = trim($speed);
                if (is_numeric($speed)) {
                    $speedsToShow[$speed] = true;
                }
            }
        }
        
        if (empty($items) || empty($speedsToShow)) return '';
        
        // Build headers
        $headers = array('Model');
        foreach ($speedsToShow as $speed => $val) {
            $headers[] = '0-' . $speed . ' MPH';
        }
        
        // Build rows
        $rows = array();
        foreach ($items as $name => $speedData) {
            $row = array(esc_html($name));
            
            foreach ($speedsToShow as $speed => $val) {
                $value = isset($speedData[$speed]) && !empty($speedData[$speed]) 
                        ? esc_html($speedData[$speed] . ' s') 
                        : '-';
                $row[] = $value;
            }
            
            $rows[] = $row;
        }
        
        return $this->buildTable($headers, $rows);
    }
    
    /**
     * Shortcode: [hillcomp]
     */
    public function hillcomp_func($atts) {
        $a = shortcode_atts(array(
            'ids' => "6199,6269,5631"
        ), $atts);
        
        $productIds = $this->sanitizeProductIds($a['ids']);
        if (empty($productIds)) return '';
        
        $items = array();
        
        foreach ($productIds as $prodId) {
            $fields = $this->getProductFields($prodId);
            if (!$fields || !isset($fields['brand']) || !isset($fields['model'])) continue;
            
            $name = $fields['brand'] . ' ' . $fields['model'];
            
            if (!empty($fields['hill_climbing'])) {
                $items[$name] = floatval($fields['hill_climbing']);
            }
        }
        
        if (empty($items)) return '';
        
        $headers = array('Model', 'Time', 'Speed');
        $rows = array();
        
        foreach ($items as $name => $time) {
            if ($time > 0) {
                $speed = $this->convert['fps-mph'] * (250 / $time);
                $speedKm = $this->convert($speed, $this->convert['mile-km'], 1);
                
                $rows[] = array(
                    esc_html($name),
                    esc_html($time . ' s'),
                    esc_html(round($speed, 1) . ' MPH (' . $speedKm . ' KMH)')
                );
            }
        }
        
        if (empty($rows)) return '';
        
        return $this->buildTable($headers, $rows);
    }
    
    /**
     * Shortcode: [rangevsweight]
     */
    public function rangevsweight_func($atts) {
        $a = shortcode_atts(array(
            'ids' => "6199,6269,5631"
        ), $atts);
        
        $productIds = $this->sanitizeProductIds($a['ids']);
        if (empty($productIds)) return '';
        
        $items = array();
        
        foreach ($productIds as $prodId) {
            $fields = $this->getProductFields($prodId);
            if (!$fields || !isset($fields['brand']) || !isset($fields['model'])) continue;
            
            $name = $fields['brand'] . ' ' . $fields['model'];
            
            $weight = isset($fields['weight']) ? floatval($fields['weight']) : 0;
            $range = isset($fields['tested_range_regular']) ? floatval($fields['tested_range_regular']) : 0;
            
            if ($weight > 0 && $range > 0) {
                $items[$name] = array(
                    'weight' => $weight,
                    'range' => $range,
                    'ratio' => round($range / $weight, 2)
                );
            }
        }
        
        if (empty($items)) return '';
        
        $headers = array('Model', 'Range', 'Weight', 'Ratio');
        $rows = array();
        
        foreach ($items as $name => $data) {
            $rows[] = array(
                esc_html($name),
                esc_html($data['range'] . ' miles'),
                esc_html($data['weight'] . ' lbs'),
                esc_html($data['ratio'] . ' miles/lb')
            );
        }
        
        return $this->buildTable($headers, $rows);
    }
    
    /**
     * Shortcode: [ipcomp]
     */
    public function ipcomp_func($atts) {
        $a = shortcode_atts(array(
            'ids' => "6199,6269,5631"
        ), $atts);
        
        $productIds = $this->sanitizeProductIds($a['ids']);
        if (empty($productIds)) return '';
        
        $items = array();
        
        foreach ($productIds as $prodId) {
            $fields = $this->getProductFields($prodId);
            if (!$fields || !isset($fields['brand']) || !isset($fields['model'])) continue;
            
            $name = $fields['brand'] . ' ' . $fields['model'];
            
            if (!empty($fields['weather_resistance']) && is_array($fields['weather_resistance'])) {
                $items[$name] = $fields['weather_resistance'][0];
            }
        }
        
        if (empty($items)) return '';
        
        $headers = array('Model', 'IP Rating');
        $rows = array();
        
        foreach ($items as $name => $rating) {
            if (!empty($rating)) {
                $rows[] = array(
                    esc_html($name),
                    esc_html($rating)
                );
            }
        }
        
        if (empty($rows)) return '';
        
        return $this->buildTable($headers, $rows);
    }
    
    /**
     * Shortcode: [batcapcomp]
     */
    public function batcapcomp_func($atts) {
        $a = shortcode_atts(array(
            'ids' => "6199,6269,5631",
            'price' => 'no'
        ), $atts);
        
        $productIds = $this->sanitizeProductIds($a['ids']);
        if (empty($productIds)) return '';
        
        $items = array();
        $showPrice = ($a['price'] === 'yes');
        
        foreach ($productIds as $prodId) {
            $id = $this->getProductId($prodId);
            $fields = $this->getProductFields($prodId);
            
            if (!$fields || !isset($fields['brand']) || !isset($fields['model'])) continue;
            
            $name = $fields['brand'] . ' ' . $fields['model'];
            
            $items[$name] = array(
                'wh' => isset($fields['battery_capacity']) ? floatval($fields['battery_capacity']) : 0,
                'ah' => isset($fields['battery_amphours']) ? $fields['battery_amphours'] : 0,
                'V' => isset($fields['battery_voltage']) ? $fields['battery_voltage'] : 0
            );
            
            if ($showPrice && $id) {
                $priceData = $this->getProductPrice($id);
                $items[$name]['price'] = $priceData['price'];
                $items[$name]['url'] = $priceData['url'];
            }
        }
        
        if (empty($items)) return '';
        
        // Check if all items have prices
        $allHavePrices = true;
        if ($showPrice) {
            foreach ($items as $item) {
                if (empty($item['price'])) {
                    $allHavePrices = false;
                    break;
                }
            }
        }
        
        $headers = array('Model', 'Battery Capacity');
        if ($showPrice && $allHavePrices) {
            $headers[] = '$/Wh';
        }
        
        $rows = array();
        foreach ($items as $name => $data) {
            $row = array();
            
            // Model column
            $modelHtml = esc_html($name);
            if ($showPrice && $allHavePrices && $data['price'] > 0) {
                $modelHtml .= '<br /><a title="Go to the ' . esc_attr($name) . ' external product page" href="' . 
                            esc_url($data['url']) . '" target="_blank" class="afftrigger" rel="sponsored external noopener">($' . 
                            esc_html(number_format($data['price'], 2)) . ' USD)</a>';
            }
            $row[] = $modelHtml;
            
            // Battery capacity column
            if ($data['wh'] > 0) {
                $batteryHtml = esc_html($data['wh'] . ' Wh');
                if ($data['V'] > 0 && $data['ah'] > 0) {
                    $batteryHtml .= '<br />(' . esc_html($data['V'] . 'V, ' . $data['ah'] . 'Ah') . ')';
                }
            } else {
                $batteryHtml = '-';
            }
            $row[] = $batteryHtml;
            
            // Price per Wh column
            if ($showPrice && $allHavePrices) {
                if ($data['price'] > 0 && $data['wh'] > 0) {
                    $pricePerWh = number_format($data['price'] / $data['wh'], 2);
                    $row[] = '<a title="Go to the ' . esc_attr($name) . ' external product page" href="' . 
                            esc_url($data['url']) . '" target="_blank" class="afftrigger" rel="sponsored external noopener">$' . 
                            esc_html($pricePerWh) . '/Wh</a>';
                } else {
                    $row[] = '-';
                }
            }
            
            $rows[] = $row;
        }
        
        $caption = ($showPrice && $allHavePrices) ? 'Based on current best prices (updated every 24 hours)' : '';
        
        return $this->buildTable($headers, $rows, $caption);
    }
    
    /**
     * Shortcode: [rangecomp]
     */
    public function rangecomp_func($atts) {
        $a = shortcode_atts(array(
            'ids' => "6199,6269,5631",
            'price' => 'no',
            'test' => '2'
        ), $atts);
        
        $productIds = $this->sanitizeProductIds($a['ids']);
        if (empty($productIds)) return '';
        
        $testNum = intval($a['test']);
        if ($testNum < 1 || $testNum > 3) $testNum = 2;
        
        $testFields = array(
            1 => array('range' => 'tested_range_fast', 'speed' => 'tested_range_avg_speed_fast'),
            2 => array('range' => 'tested_range_regular', 'speed' => 'tested_range_avg_speed_regular'),
            3 => array('range' => 'tested_range_slow', 'speed' => 'tested_range_avg_speed_slow')
        );
        
        $items = array();
        
        foreach ($productIds as $prodId) {
            $fields = $this->getProductFields($prodId);
            if (!$fields || !isset($fields['brand']) || !isset($fields['model'])) continue;
            
            $name = $fields['brand'] . ' ' . $fields['model'];
            
            $range = isset($fields[$testFields[$testNum]['range']]) ? floatval($fields[$testFields[$testNum]['range']]) : 0;
            $speed = isset($fields[$testFields[$testNum]['speed']]) ? floatval($fields[$testFields[$testNum]['speed']]) : 0;
            
            $items[$name] = array('range' => $range, 'speed' => $speed);
        }
        
        if (empty($items)) return '';
        
        $headers = array('Model', 'Range', 'Avg. Speed');
        $rows = array();
        
        foreach ($items as $name => $data) {
            $row = array(esc_html($name));
            
            if ($data['range'] > 0) {
                $rangeKm = $this->convert($data['range'], $this->convert['mile-km'], 1);
                $row[] = esc_html($data['range'] . ' miles') . '<br />(' . esc_html($rangeKm . ' km') . ')';
            } else {
                $row[] = '-';
            }
            
            if ($data['speed'] > 0) {
                $speedKm = $this->convert($data['speed'], $this->convert['mile-km'], 1);
                $row[] = esc_html($data['speed'] . ' MPH') . '<br />(' . esc_html($speedKm . ' KMH') . ')';
            } else {
                $row[] = '-';
            }
            
            $rows[] = $row;
        }
        
        $captions = array(
            1 => 'Test #1 (Speed Priority), 175 lbs (80 kg) rider',
            2 => 'Test #2 (Regular Speed), 175 lbs (80 kg) rider',
            3 => 'Test #3 (Range Priority), 175 lbs (80 kg) rider'
        );
        
        return $this->buildTable($headers, $rows, $captions[$testNum]);
    }
    
    /**
     * Shortcode: [weight]
     */
    public function weight_func($atts) {
        $a = shortcode_atts(array(
            'exclude' => "false",
            'highlight' => 0,
            'ids' => "6199,6269,5631"
        ), $atts);
        
        $productIds = $this->sanitizeProductIds($a['ids']);
        if (empty($productIds)) return '';
        
        $items = array();
        
        foreach ($productIds as $prodId) {
            $fields = $this->getProductFields($prodId);
            if (!$fields || !isset($fields['brand']) || !isset($fields['model'])) continue;
            
            $name = $fields['brand'] . ' ' . $fields['model'];
            $weight = isset($fields['weight']) ? floatval($fields['weight']) : 0;
            
            if ($weight > 0) {
                $items[$name] = $weight;
            }
        }
        
        if (empty($items)) return '';
        
        $headers = array('Model', 'Weight (lbs)', 'Weight (kg)');
        $rows = array();
        
        foreach ($items as $name => $weight) {
            $weightKg = $this->convert($weight, $this->convert['lb-kg'], 1);
            $rows[] = array(
                esc_html($name),
                esc_html($weight . ' lbs'),
                esc_html($weightKg . ' kg')
            );
        }
        
        return $this->buildTable($headers, $rows, 'Based on our own high-precision weight measurements.');
    }
    
    /**
     * Shortcode: [braking]
     */
    public function braking_func($atts) {
        $a = shortcode_atts(array(
            'exclude' => "false",
            'highlight' => 0,
            'ids' => "6199,6269,5631"
        ), $atts);
        
        $productIds = $this->sanitizeProductIds($a['ids']);
        if (empty($productIds)) return '';
        
        $items = array();
        
        foreach ($productIds as $prodId) {
            $fields = $this->getProductFields($prodId);
            if (!$fields || !isset($fields['brand']) || !isset($fields['model'])) continue;
            
            $name = $fields['brand'] . ' ' . $fields['model'];
            $distance = isset($fields['brake_distance']) ? floatval($fields['brake_distance']) : 0;
            
            if ($distance > 0) {
                $items[$name] = $distance;
            }
        }
        
        if (empty($items)) return '';
        
        $headers = array('Model', 'Braking Distance');
        $rows = array();
        
        foreach ($items as $name => $distance) {
            $distanceM = $this->convert($distance, $this->convert['ft-m'], 1);
            $rows[] = array(
                esc_html($name),
                esc_html($distance . ' ft (' . $distanceM . ' m)')
            );
        }
        
        return $this->buildTable($headers, $rows, 'Braking from 15 MPH (24.2 KMH).');
    }
    
    /**
     * Shortcode: [batval]
     */
    public function batval_func($atts) {
        $a = shortcode_atts(array(
            'id' => "this"
        ), $atts);
        
        $id = $this->getProductId($a['id']);
        if (!$id) return "No product selected";
        
        $fields = $this->getProductFields($a['id']);
        if (!$fields) return "Product not found";
        
        $batteryCapacity = isset($fields['battery_capacity']) ? floatval($fields['battery_capacity']) : 0;
        if ($batteryCapacity <= 0) return "Battery capacity not available";
        
        $priceData = $this->getProductPrice($id);
        if ($priceData['price'] <= 0) return "Price not available";
        
        $valuePerWh = round($priceData['price'] / $batteryCapacity, 2);
        return esc_html('$' . $valuePerWh . '/Wh');
    }
}

// Initialize the plugin
add_action('init', array('ProductComparisonPlugin', 'getInstance'));
