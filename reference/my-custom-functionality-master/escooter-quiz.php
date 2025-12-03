<?php
/**
 * ERideHero E-Scooter Quiz
 * 
 * A complete interactive quiz to help users find their perfect electric scooter
 * based on their preferences, riding style, and budget.
 * 
 * This file should be placed in your custom functionality plugin.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main class for the E-Scooter Quiz functionality
 */
class ERideHero_Scooter_Quiz {
    
    /**
     * Initialize the quiz functionality
     */
    public function __construct() {
        // Register scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
        
        // Register shortcode
        add_shortcode('escooter_quiz', array($this, 'render_quiz_shortcode'));
        
        // Register AJAX handlers
        add_action('wp_ajax_get_quiz_results', array($this, 'get_quiz_results'));
        add_action('wp_ajax_nopriv_get_quiz_results', array($this, 'get_quiz_results'));
        
        // Register AJAX handlers for email saving
        add_action('wp_ajax_save_quiz_results', array($this, 'save_quiz_results'));
        add_action('wp_ajax_nopriv_save_quiz_results', array($this, 'save_quiz_results'));
    }
    
    /**
     * Register and enqueue all required scripts and styles
     */
    public function register_scripts() {
        // Get the plugin directory URL
        $plugin_url = plugin_dir_url(__FILE__);
        
        // Register styles
        wp_register_style(
            'eridehero-quiz-styles',
            $plugin_url . 'escooter-quiz/css/quiz.css',
            array(),
            '1.0.0'
        );
        
        // Register scripts
        wp_register_script(
            'eridehero-quiz-js',
            $plugin_url . 'escooter-quiz/js/quiz.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Pass data to the script
        wp_localize_script(
            'eridehero-quiz-js',
            'ERideHeroQuiz',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('eridehero_quiz_nonce'),
                'siteurl' => site_url(),
                'pluginurl' => $plugin_url,
            )
        );
    }
    
    /**
     * Render the quiz shortcode
     * 
     * @return string HTML output for the quiz
     */
    public function render_quiz_shortcode($atts) {
        // Enqueue required assets
        wp_enqueue_style('eridehero-quiz-styles');
        wp_enqueue_script('eridehero-quiz-js');
        
        // Start output buffering
        ob_start();
        
        // Include the quiz template
        include_once(plugin_dir_path(__FILE__) . 'escooter-quiz/templates/quiz-template.php');
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * Process quiz answers and return results via AJAX
     */
    public function get_quiz_results() {
        // Check nonce for security
        check_ajax_referer('eridehero_quiz_nonce', 'nonce');
        
        // Get quiz answers from AJAX request
        $quiz_answers = isset($_POST['quiz_answers']) ? $_POST['quiz_answers'] : array();
        
        // Get recommendations based on answers
        $recommendations = $this->calculate_recommendations($quiz_answers);
        
        // Return results as JSON
        wp_send_json_success(array(
            'recommendations' => $recommendations
        ));
    }
    
    /**
     * Save user's email and quiz results
     */
    public function save_quiz_results() {
        // Check nonce for security
        check_ajax_referer('eridehero_quiz_nonce', 'nonce');
        
        // Get email from request
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $quiz_answers = isset($_POST['quiz_answers']) ? $_POST['quiz_answers'] : array();
        $recommendations = isset($_POST['recommendations']) ? $_POST['recommendations'] : array();
        
        // Validate email
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address'));
            return;
        }
        
        // Here you would typically save to your email marketing system
        // For example, add to a custom table or send to a CRM/ESP
        // This is a placeholder for your actual implementation
        
        // Example for storing in WordPress options (for development/testing)
        $stored_results = get_option('eridehero_quiz_results', array());
        $stored_results[] = array(
            'email' => $email,
            'answers' => $quiz_answers,
            'recommendations' => $recommendations,
            'timestamp' => current_time('mysql'),
            'ip' => $_SERVER['REMOTE_ADDR'],
        );
        update_option('eridehero_quiz_results', $stored_results);
        
        // Send email to user with results
        $this->send_quiz_results_email($email, $recommendations);
        
        wp_send_json_success(array('message' => 'Results saved successfully'));
    }
    
    /**
     * Send email with quiz results to the user
     * 
     * @param string $email User's email address
     * @param array $recommendations Scooter recommendations
     */
    private function send_quiz_results_email($email, $recommendations) {
        $subject = 'Your ERideHero Electric Scooter Recommendations';
        
        // Build email content
        $message = '<html><body>';
        $message .= '<h1>Your Perfect Electric Scooters</h1>';
        $message .= '<p>Based on your preferences, here are our top recommendations:</p>';
        
        foreach ($recommendations as $index => $scooter) {
            $match_percentage = $scooter['match_percentage'];
            $message .= '<div style="margin-bottom: 20px; padding: 15px; border: 1px solid #e0e0e0; border-radius: 8px;">';
            $message .= '<h2>' . esc_html($scooter['name']) . ' - ' . $match_percentage . '% Match</h2>';
            $message .= '<p><strong>Price:</strong> $' . esc_html($scooter['price']) . '</p>';
            $message .= '<p><strong>Why this works for you:</strong></p>';
            $message .= '<ul>';
            
            foreach ($scooter['features'] as $feature) {
                $message .= '<li>' . esc_html($feature) . '</li>';
            }
            
            $message .= '</ul>';
            $message .= '<p><a href="' . esc_url($scooter['url']) . '" style="background-color: #6c5ce7; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; display: inline-block;">See Details</a></p>';
            $message .= '</div>';
        }
        
        $message .= '<p>Thank you for using our E-Scooter Finder Quiz!</p>';
        $message .= '<p>- The ERideHero Team</p>';
        $message .= '</body></html>';
        
        // Set headers for HTML email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ERideHero <noreply@eridehero.com>'
        );
        
        // Send the email
        wp_mail($email, $subject, $message, $headers);
    }
    
    /**
     * Calculate scooter recommendations based on quiz answers
     * 
     * @param array $answers User's quiz answers
     * @return array Scooter recommendations with match percentages
     */
    private function calculate_recommendations($answers) {
        // Get scooter database
        $scooters = $this->get_scooter_database();
        
        // Define weights for different factors
        $weights = array(
            'primary_use' => 5,
            'budget' => 5,
            'rider_weight' => 4,
            'portability' => 3,
            'commute_distance' => 4,
            'terrain' => 4,
            'priority_features' => 4
        );
        
        // Initialize scores for each scooter
        $scores = array();
        foreach ($scooters as $id => $scooter) {
            $scores[$id] = array(
                'score' => 0,
                'max_possible' => 0,
                'scooter' => $scooter
            );
        }
        
        // Process primary use
        if (isset($answers['primary_use'])) {
            $primary_use = $answers['primary_use'];
            $weight = $weights['primary_use'];
            
            foreach ($scooters as $id => $scooter) {
                $max_score = 10 * $weight;
                $scores[$id]['max_possible'] += $max_score;
                
                switch ($primary_use) {
                    case 'commuting':
                        if ($scooter['category'] === 'commuter') {
                            $scores[$id]['score'] += $max_score;
                        } elseif ($scooter['category'] === 'mid-range' || $scooter['category'] === 'lightweight') {
                            $scores[$id]['score'] += $max_score * 0.8;
                        } elseif ($scooter['category'] === 'budget') {
                            $scores[$id]['score'] += $max_score * 0.6;
                        } else {
                            $scores[$id]['score'] += $max_score * 0.4;
                        }
                        break;
                        
                    case 'recreational':
                        if ($scooter['category'] === 'mid-range') {
                            $scores[$id]['score'] += $max_score;
                        } elseif ($scooter['category'] === 'commuter' || $scooter['category'] === 'hyperscooter') {
                            $scores[$id]['score'] += $max_score * 0.7;
                        } elseif ($scooter['category'] === 'budget') {
                            $scores[$id]['score'] += $max_score * 0.6;
                        } else {
                            $scores[$id]['score'] += $max_score * 0.5;
                        }
                        break;
                        
                    case 'mixed':
                        if ($scooter['category'] === 'mid-range' || $scooter['category'] === 'commuter') {
                            $scores[$id]['score'] += $max_score;
                        } elseif ($scooter['category'] === 'hyperscooter') {
                            $scores[$id]['score'] += $max_score * 0.7;
                        } elseif ($scooter['category'] === 'budget') {
                            $scores[$id]['score'] += $max_score * 0.5;
                        } else {
                            $scores[$id]['score'] += $max_score * 0.4;
                        }
                        break;
                        
                    case 'offroad':
                        if ($scooter['is_offroad']) {
                            $scores[$id]['score'] += $max_score;
                        } elseif ($scooter['category'] === 'hyperscooter') {
                            $scores[$id]['score'] += $max_score * 0.6;
                        } elseif ($scooter['category'] === 'mid-range') {
                            $scores[$id]['score'] += $max_score * 0.3;
                        } else {
                            $scores[$id]['score'] += $max_score * 0.1;
                        }
                        break;
                }
            }
        }
        
        // Process budget
        if (isset($answers['budget'])) {
            $budget_range = $this->get_budget_range($answers['budget']);
            $weight = $weights['budget'];
            
            foreach ($scooters as $id => $scooter) {
                $max_score = 10 * $weight;
                $scores[$id]['max_possible'] += $max_score;
                
                // Perfect match if within budget range
                if ($scooter['price'] >= $budget_range[0] && $scooter['price'] <= $budget_range[1]) {
                    $scores[$id]['score'] += $max_score;
                } 
                // Close match if slightly below budget (perceived value)
                elseif ($scooter['price'] < $budget_range[0] && $scooter['price'] >= $budget_range[0] * 0.8) {
                    $scores[$id]['score'] += $max_score * 0.9;
                }
                // Close match if slightly above budget (might stretch)
                elseif ($scooter['price'] > $budget_range[1] && $scooter['price'] <= $budget_range[1] * 1.2) {
                    $scores[$id]['score'] += $max_score * 0.7;
                }
                // Way below budget (might feel cheap)
                elseif ($scooter['price'] < $budget_range[0] * 0.8) {
                    $scores[$id]['score'] += $max_score * 0.5;
                }
                // Way above budget (likely unaffordable)
                else {
                    $scores[$id]['score'] += $max_score * 0.1;
                }
            }
        }
        
        // Process rider weight
        if (isset($answers['rider_weight'])) {
            $rider_weight = $this->get_rider_weight_range($answers['rider_weight']);
            $weight = $weights['rider_weight'];
            
            foreach ($scooters as $id => $scooter) {
                $max_score = 10 * $weight;
                $scores[$id]['max_possible'] += $max_score;
                
                if ($rider_weight[1] <= $scooter['max_load']) {
                    // Rider weight is within the scooter's capacity
                    $scores[$id]['score'] += $max_score;
                } else {
                    // Rider weight exceeds the scooter's capacity
                    $scores[$id]['score'] += $max_score * 0.1; // Major penalty
                }
            }
        }
        
        // Process portability preference
        if (isset($answers['portability'])) {
            $portability_factor = $this->get_portability_factor($answers['portability']);
            $weight = $weights['portability'];
            
            foreach ($scooters as $id => $scooter) {
                $max_score = 10 * $weight;
                $scores[$id]['max_possible'] += $max_score;
                
                if ($portability_factor === 3) { // Extremely important
                    if ($scooter['weight'] < 30) {
                        $scores[$id]['score'] += $max_score;
                    } elseif ($scooter['weight'] < 45) {
                        $scores[$id]['score'] += $max_score * 0.6;
                    } elseif ($scooter['weight'] < 60) {
                        $scores[$id]['score'] += $max_score * 0.3;
                    } else {
                        $scores[$id]['score'] += $max_score * 0.1;
                    }
                } elseif ($portability_factor === 2) { // Somewhat important
                    if ($scooter['weight'] < 40) {
                        $scores[$id]['score'] += $max_score;
                    } elseif ($scooter['weight'] < 55) {
                        $scores[$id]['score'] += $max_score * 0.8;
                    } elseif ($scooter['weight'] < 70) {
                        $scores[$id]['score'] += $max_score * 0.5;
                    } else {
                        $scores[$id]['score'] += $max_score * 0.2;
                    }
                } else { // Not important
                    // Weight doesn't matter much, but still give a small bonus to lighter scooters
                    if ($scooter['weight'] < 50) {
                        $scores[$id]['score'] += $max_score * 0.7;
                    } else {
                        $scores[$id]['score'] += $max_score;
                    }
                }
            }
        }
        
        // Process commute distance
        if (isset($answers['commute_distance']) && in_array($answers['primary_use'], array('commuting', 'mixed'))) {
            $distance_range = $this->get_distance_range($answers['commute_distance']);
            $weight = $weights['commute_distance'];
            
            foreach ($scooters as $id => $scooter) {
                $max_score = 10 * $weight;
                $scores[$id]['max_possible'] += $max_score;
                
                // Minimum required range = commute distance * 2 (round trip) * 1.5 (safety buffer)
                $required_range = $distance_range[1] * 2 * 1.5;
                
                if ($scooter['range'] >= $required_range) {
                    $scores[$id]['score'] += $max_score;
                } elseif ($scooter['range'] >= $distance_range[1] * 2) {
                    // Enough for round trip but without much buffer
                    $scores[$id]['score'] += $max_score * 0.7;
                } elseif ($scooter['range'] >= $distance_range[1]) {
                    // Only enough for one-way trip
                    $scores[$id]['score'] += $max_score * 0.3;
                } else {
                    // Not enough even for one-way
                    $scores[$id]['score'] += $max_score * 0.1;
                }
            }
        }
        
        // Process terrain preference
        if (isset($answers['terrain'])) {
            $terrain_factor = $this->get_terrain_factor($answers['terrain']);
            $weight = $weights['terrain'];
            
            foreach ($scooters as $id => $scooter) {
                $max_score = 10 * $weight;
                $scores[$id]['max_possible'] += $max_score;
                
                if ($terrain_factor === 1) { // Smooth surfaces
                    // Most scooters handle smooth surfaces well
                    $scores[$id]['score'] += $max_score;
                } elseif ($terrain_factor === 2) { // Mixed terrain
                    if ($scooter['has_suspension']) {
                        $scores[$id]['score'] += $max_score;
                    } elseif ($scooter['tire_size'] >= 10) {
                        $scores[$id]['score'] += $max_score * 0.8;
                    } else {
                        $scores[$id]['score'] += $max_score * 0.5;
                    }
                } elseif ($terrain_factor === 3) { // Rough terrain
                    if ($scooter['has_suspension'] && $scooter['tire_size'] >= 10) {
                        $scores[$id]['score'] += $max_score;
                    } elseif ($scooter['has_suspension']) {
                        $scores[$id]['score'] += $max_score * 0.7;
                    } elseif ($scooter['tire_size'] >= 10) {
                        $scores[$id]['score'] += $max_score * 0.5;
                    } else {
                        $scores[$id]['score'] += $max_score * 0.2;
                    }
                } elseif ($terrain_factor === 4) { // Off-road
                    if ($scooter['is_offroad']) {
                        $scores[$id]['score'] += $max_score;
                    } elseif ($scooter['has_suspension'] && $scooter['tire_size'] >= 10) {
                        $scores[$id]['score'] += $max_score * 0.6;
                    } elseif ($scooter['has_suspension']) {
                        $scores[$id]['score'] += $max_score * 0.3;
                    } else {
                        $scores[$id]['score'] += $max_score * 0.1;
                    }
                }
            }
        }
        
        // Process priority features
        if (isset($answers['priority_features']) && is_array($answers['priority_features'])) {
            $priorities = $answers['priority_features'];
            $weight = $weights['priority_features'];
            
            foreach ($scooters as $id => $scooter) {
                $max_score = 10 * $weight;
                $scores[$id]['max_possible'] += $max_score;
                
                $feature_score = 0;
                
                foreach ($priorities as $priority) {
                    switch ($priority) {
                        case 'range':
                            // Score based on range percentile within all scooters
                            $range_percentile = $this->get_percentile($scooter['range'], array_column($scooters, 'range'));
                            $feature_score += $range_percentile * 5; // Max 5 points for this feature
                            break;
                            
                        case 'speed':
                            // Score based on speed percentile within all scooters
                            $speed_percentile = $this->get_percentile($scooter['speed'], array_column($scooters, 'speed'));
                            $feature_score += $speed_percentile * 5;
                            break;
                            
                        case 'comfort':
                            if ($scooter['has_suspension'] && $scooter['tire_size'] >= 10) {
                                $feature_score += 5;
                            } elseif ($scooter['has_suspension'] || $scooter['tire_size'] >= 10) {
                                $feature_score += 3;
                            } else {
                                $feature_score += 1;
                            }
                            break;
                            
                        case 'weather':
                            // Score based on IP rating
                            if ($scooter['ip_rating'] >= 65) {
                                $feature_score += 5;
                            } elseif ($scooter['ip_rating'] >= 55) {
                                $feature_score += 4;
                            } elseif ($scooter['ip_rating'] >= 54) {
                                $feature_score += 3;
                            } else {
                                $feature_score += 1;
                            }
                            break;
                            
                        case 'build':
                            // Use build quality rating
                            $feature_score += $scooter['build_quality'] * 5;
                            break;
                            
                        case 'portability':
                            // Inverse relationship with weight
                            if ($scooter['weight'] < 30) {
                                $feature_score += 5;
                            } elseif ($scooter['weight'] < 40) {
                                $feature_score += 4;
                            } elseif ($scooter['weight'] < 50) {
                                $feature_score += 3;
                            } elseif ($scooter['weight'] < 60) {
                                $feature_score += 2;
                            } else {
                                $feature_score += 1;
                            }
                            break;
                    }
                }
                
                // Normalize feature score to max_score
                $normalized_score = ($feature_score / (count($priorities) * 5)) * $max_score;
                $scores[$id]['score'] += $normalized_score;
            }
        }
        
        // Calculate match percentages
        $matches = array();
        foreach ($scores as $id => $data) {
            if ($data['max_possible'] > 0) {
                $match_percentage = round(($data['score'] / $data['max_possible']) * 100);
                
                $matches[$id] = array(
                    'id' => $id,
                    'name' => $data['scooter']['name'],
                    'match_percentage' => $match_percentage,
                    'price' => $data['scooter']['price'],
                    'category' => $data['scooter']['category'],
                    'speed' => $data['scooter']['speed'],
                    'range' => $data['scooter']['range'],
                    'weight' => $data['scooter']['weight'],
                    'image' => $data['scooter']['image'],
                    'features' => $this->get_feature_highlights($data['scooter'], $answers),
                    'url' => $data['scooter']['url'],
                    'tagline' => $data['scooter']['tagline']
                );
            }
        }
        
        // Sort by match percentage (highest first)
        uasort($matches, function($a, $b) {
            return $b['match_percentage'] - $a['match_percentage'];
        });
        
        // Return top 3 matches
        return array_slice($matches, 0, 3);
    }
    
    /**
     * Generate personalized feature highlights based on user preferences
     * 
     * @param array $scooter Scooter data
     * @param array $answers User's quiz answers
     * @return array Feature highlights
     */
    private function get_feature_highlights($scooter, $answers) {
        $highlights = array();
        
        // Highlight relevant features based on user's answers
        if (isset($answers['primary_use'])) {
            switch ($answers['primary_use']) {
                case 'commuting':
                    if ($scooter['range'] > 25) {
                        $highlights[] = "Great {$scooter['range']} mile range for daily commuting";
                    }
                    if ($scooter['has_suspension']) {
                        $highlights[] = "Comfortable ride for daily use";
                    }
                    break;
                    
                case 'recreational':
                    if ($scooter['speed'] > 25) {
                        $highlights[] = "Exciting {$scooter['speed']} MPH top speed for weekend fun";
                    }
                    break;
                    
                case 'offroad':
                    if ($scooter['is_offroad']) {
                        $highlights[] = "Built for off-road adventures";
                    }
                    break;
            }
        }
        
        if (isset($answers['rider_weight'])) {
            $rider_weight = $this->get_rider_weight_range($answers['rider_weight']);
            if ($rider_weight[1] > 220 && $scooter['max_load'] >= $rider_weight[1]) {
                $highlights[] = "Supports up to {$scooter['max_load']} lbs rider weight";
            }
        }
        
        if (isset($answers['portability']) && $answers['portability'] === 'portability-very') {
            if ($scooter['weight'] < 30) {
                $highlights[] = "Ultra-lightweight at only {$scooter['weight']} lbs";
            } elseif ($scooter['weight'] < 40) {
                $highlights[] = "Relatively portable at {$scooter['weight']} lbs";
            }
        }
        
        if (isset($answers['terrain']) && ($answers['terrain'] === 'terrain-mixed' || $answers['terrain'] === 'terrain-rough')) {
            if ($scooter['has_suspension']) {
                $highlights[] = "Suspension system smooths out bumpy roads";
            }
            if ($scooter['tire_size'] >= 10) {
                $highlights[] = "{$scooter['tire_size']}-inch tubeless tires absorb shocks well";
            }
        }
        
        if (isset($answers['priority_features'])) {
            if (in_array('range', $answers['priority_features']) && $scooter['range'] > 30) {
                $highlights[] = "Excellent {$scooter['range']} mile range on a single charge";
            }
            
            if (in_array('speed', $answers['priority_features']) && $scooter['speed'] > 25) {
                $highlights[] = "Fast {$scooter['speed']} MPH top speed and quick acceleration";
            }
            
            if (in_array('weather', $answers['priority_features']) && $scooter['ip_rating'] >= 54) {
                $highlights[] = "Good water resistance with IP{$scooter['ip_rating']} rating";
            }
        }
        
        // Add general highlights if we don't have enough specific ones
        if (count($highlights) < 3) {
            if ($scooter['speed'] > 20 && !str_contains(implode(' ', $highlights), 'speed')) {
                $highlights[] = "{$scooter['speed']} MPH top speed";
            }
            
            if ($scooter['range'] > 20 && !str_contains(implode(' ', $highlights), 'range')) {
                $highlights[] = "{$scooter['range']} mile real-world range";
            }
            
            if ($scooter['has_suspension'] && !str_contains(implode(' ', $highlights), 'suspension')) {
                $highlights[] = "Equipped with suspension for a smoother ride";
            }
        }
        
        // Ensure we have exactly 3 highlights
        while (count($highlights) < 3) {
            $highlights[] = $scooter['selling_points'][count($highlights) % count($scooter['selling_points'])];
        }
        
        return array_slice($highlights, 0, 3);
    }
    
    /**
     * Helper function to get budget range from answer
     */
    private function get_budget_range($budget_answer) {
        switch ($budget_answer) {
            case 'budget-entry':
                return array(399, 699);
            case 'budget-mid':
                return array(700, 1299);
            case 'budget-high':
                return array(1300, 2999);
            case 'budget-premium':
                return array(3000, 10000);
            default:
                return array(0, 10000);
        }
    }
    
    /**
     * Helper function to get rider weight range from answer
     */
    private function get_rider_weight_range($weight_answer) {
        switch ($weight_answer) {
            case 'weight-light':
                return array(0, 175);
            case 'weight-medium':
                return array(175, 220);
            case 'weight-heavy':
                return array(220, 265);
            case 'weight-very-heavy':
                return array(265, 350);
            default:
                return array(0, 220);
        }
    }
    
    /**
     * Helper function to get portability factor from answer
     */
    private function get_portability_factor($portability_answer) {
        switch ($portability_answer) {
            case 'portability-very':
                return 3;
            case 'portability-somewhat':
                return 2;
            case 'portability-not':
                return 1;
            default:
                return 2;
        }
    }
    
    /**
     * Helper function to get terrain factor from answer
     */
    private function get_terrain_factor($terrain_answer) {
        switch ($terrain_answer) {
            case 'terrain-smooth':
                return 1;
            case 'terrain-mixed':
                return 2;
            case 'terrain-rough':
                return 3;
            case 'terrain-offroad':
                return 4;
            default:
                return 1;
        }
    }
    
    /**
     * Helper function to get commute distance range from answer
     */
    private function get_distance_range($distance_answer) {
        switch ($distance_answer) {
            case 'distance-short':
                return array(0, 5);
            case 'distance-medium':
                return array(5, 10);
            case 'distance-long':
                return array(10, 20);
            default:
                return array(0, 5);
        }
    }
    
    /**
     * Calculate percentile of a value within an array
     * 
     * @param float $value The value to find the percentile for
     * @param array $array Array of values
     * @return float Percentile (0 to 1)
     */
    private function get_percentile($value, $array) {
        $count = count($array);
        if ($count === 0) {
            return 0;
        }
        
        // Sort array
        sort($array);
        
        // Find position
        $position = 0;
        foreach ($array as $i => $v) {
            if ($value <= $v) {
                $position = $i;
                break;
            }
            $position = $count;
        }
        
        return $position / $count;
    }
    
    /**
     * Get the scooter database
     * This contains all the scooters from your review article
     * 
     * @return array Array of scooter data
     */
    private function get_scooter_database() {
        return array(
            'vmax-vx5-pro-gt' => array(
                'name' => 'Vmax VX5 Pro GT',
                'tagline' => 'Best for beginners',
                'category' => 'budget',
                'price' => 499.00,
                'speed' => 17.5,
                'range' => 20,
                'weight' => 36.8,
                'max_load' => 265,
                'battery' => 374.4,
                'power' => 400,
                'has_suspension' => false,
                'tire_size' => 8.5,
                'ip_rating' => 56,
                'build_quality' => 0.8,
                'is_offroad' => false,
                'image' => 'vmax-vx5-pro-gt.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#vmax-vx5-pro-gt',
                'selling_points' => [
                    'Strong performance (speed, acceleration, hills) for the price',
                    'Excellent ~20-mile real-world range',
                    'Class-leading IPX6 water resistance',
                    'Includes turn signals & app support',
                    'Impressive braking power'
                ]
            ),
            'niu-kqi2-pro' => array(
                'name' => 'NIU KQi2 Pro',
                'tagline' => 'Best when cheaper than VX5',
                'category' => 'budget',
                'price' => 399.00,
                'speed' => 17.3,
                'range' => 19.8,
                'weight' => 40.6,
                'max_load' => 220,
                'battery' => 365,
                'power' => 300,
                'has_suspension' => false,
                'tire_size' => 10,
                'ip_rating' => 54,
                'build_quality' => 0.9,
                'is_offroad' => false,
                'image' => 'niu-kqi2-pro.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#niu-kqi2-pro',
                'selling_points' => [
                    'Proven reliability & solid build quality',
                    'Consistent power delivery (48V system)',
                    'Good ~20-mile real-world range',
                    'Smooth ride via 10" tubeless tires'
                ]
            ),
            'segway-ninebot-max-g2' => array(
                'name' => 'Segway Ninebot Max G2',
                'tagline' => 'Best ride quality (commuter)',
                'category' => 'commuter',
                'price' => 999.99,
                'speed' => 22.4,
                'range' => 29.8,
                'weight' => 53.5,
                'max_load' => 265,
                'battery' => 551,
                'power' => 450,
                'has_suspension' => true,
                'tire_size' => 10,
                'ip_rating' => 55,
                'build_quality' => 0.9,
                'is_offroad' => false,
                'image' => 'segway-ninebot-max-g2.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#segway-ninebot-max-g2',
                'selling_points' => [
                    'Superb ride comfort (adjustable triple suspension)',
                    'Robust build quality',
                    'Good safety features (signals, TCS)',
                    '~30-mile real-world range',
                    'Self-healing tires reduce flat risk'
                ]
            ),
            'vmax-vx2-pro-gt' => array(
                'name' => 'Vmax VX2 Pro GT',
                'tagline' => 'Best performance value (commuter)',
                'category' => 'commuter',
                'price' => 899.00,
                'speed' => 23.9,
                'range' => 39.6,
                'weight' => 46.5,
                'max_load' => 287,
                'battery' => 768,
                'power' => 500,
                'has_suspension' => false,
                'tire_size' => 10,
                'ip_rating' => 56,
                'build_quality' => 0.85,
                'is_offroad' => false,
                'image' => 'vmax-vx2-pro-gt.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#vmax-vx2-pro-gt',
                'selling_points' => [
                    'Record-breaking ~40-mile real-world range',
                    'Class-leading speed & acceleration (48V system)',
                    'Excellent hill climbing ability',
                    'Superior IPX6 water resistance',
                    'High 287 lbs max load'
                ]
            ),
            'niu-kqi-300x' => array(
                'name' => 'NIU KQi 300X',
                'tagline' => 'Best balanced commuter',
                'category' => 'commuter',
                'price' => 1198.00,
                'speed' => 23.6,
                'range' => 26.8,
                'weight' => 48.7,
                'max_load' => 265,
                'battery' => 608,
                'power' => 500,
                'has_suspension' => true,
                'tire_size' => 10,
                'ip_rating' => 66,
                'build_quality' => 0.95,
                'is_offroad' => false,
                'image' => 'niu-kqi-300x.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#niu-kqi-300x',
                'selling_points' => [
                    'Strong acceleration & hill climbing (48V system)',
                    'Improved ride comfort via front suspension',
                    'Excellent build quality & folding mechanism',
                    'Top-tier IP66 water resistance',
                    'Powerful dual disc brakes'
                ]
            ),
            'niu-kqi-air' => array(
                'name' => 'NIU KQi Air',
                'tagline' => 'Best lightweight',
                'category' => 'lightweight',
                'price' => 999.00,
                'speed' => 20.1,
                'range' => 24.2,
                'weight' => 26.4,
                'max_load' => 265,
                'battery' => 351.2,
                'power' => 350,
                'has_suspension' => false,
                'tire_size' => 9.5,
                'ip_rating' => 55,
                'build_quality' => 0.9,
                'is_offroad' => false,
                'image' => 'niu-kqi-air.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#niu-kqi-air',
                'selling_points' => [
                    'Incredibly lightweight (26.4 lbs) makes carrying effortless',
                    'Superb ride quality for its weight via 9.5" tubeless tires',
                    'Excellent ~24-mile real-world range',
                    'Premium build, IP55 rating, turn signals'
                ]
            ),
            'fluid-mosquito' => array(
                'name' => 'fluid Mosquito',
                'tagline' => 'Most compact power',
                'category' => 'lightweight',
                'price' => 899.00,
                'speed' => 25.6,
                'range' => 18.9,
                'weight' => 29,
                'max_load' => 265,
                'battery' => 461,
                'power' => 500,
                'has_suspension' => true,
                'tire_size' => 8,
                'ip_rating' => 54,
                'build_quality' => 0.8,
                'is_offroad' => false,
                'image' => 'fluid-mosquito.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#fluid-mosquito',
                'selling_points' => [
                    'Class-leading speed (~25.6 MPH) & acceleration for under 30 lbs',
                    'Extremely compact when folded due to foldable handlebars',
                    'Very lightweight (29 lbs) and easy to carry/store',
                    'Potent 500W motor handles hills well'
                ]
            ),
            'apollo-city-2024' => array(
                'name' => 'Apollo City 2024',
                'tagline' => 'Best ride quality (mid-range)',
                'category' => 'mid-range',
                'price' => 1399.00,
                'speed' => 32.3,
                'range' => 29.8,
                'weight' => 65,
                'max_load' => 265,
                'battery' => 960,
                'power' => 1000,
                'has_suspension' => true,
                'tire_size' => 10,
                'ip_rating' => 66,
                'build_quality' => 0.9,
                'is_offroad' => false,
                'image' => 'apollo-city-2024.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#apollo-city-2024',
                'selling_points' => [
                    'Superb suspension comfort smooths out rough roads',
                    'Very quick dual-motor acceleration',
                    'Solid, premium build quality with IP66 rating',
                    'Effective drum + regen brakes (low maintenance)',
                    'Self-healing 10" tubeless tires'
                ]
            ),
            'vmax-vx4-gt' => array(
                'name' => 'Vmax VX4 GT',
                'tagline' => 'Longest range in its class',
                'category' => 'mid-range',
                'price' => 1299.00,
                'speed' => 25,
                'range' => 42.5,
                'weight' => 63.9,
                'max_load' => 330,
                'battery' => 1113,
                'power' => 500,
                'has_suspension' => true,
                'tire_size' => 10,
                'ip_rating' => 56,
                'build_quality' => 0.85,
                'is_offroad' => false,
                'image' => 'vmax-vx4-gt.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#vmax-vx4-gt',
                'selling_points' => [
                    'Excellent ~42.5-mile real-world range',
                    'High 330 lbs max load capacity',
                    'Solid and dependable build quality',
                    'Decent comfort (front suspension, 10" tires)',
                    'Large 1113Wh battery'
                ]
            ),
            'kugoo-kukirin-g4' => array(
                'name' => 'Kugoo Kukirin G4',
                'tagline' => 'Highest speed for price',
                'category' => 'mid-range',
                'price' => 899.00,
                'speed' => 42.6,
                'range' => 28.5,
                'weight' => 81.5,
                'max_load' => 265,
                'battery' => 1200,
                'power' => 2000,
                'has_suspension' => true,
                'tire_size' => 11,
                'ip_rating' => 54,
                'build_quality' => 0.75,
                'is_offroad' => false,
                'image' => 'kugoo-kukirin-g4.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#kugoo-kukirin-g4',
                'selling_points' => [
                    'Incredible speed & acceleration for the money',
                    'Powerful 2000W motor & 60V system',
                    'Plush suspension handles speed well',
                    'Large 11" tubeless tires'
                ]
            ),
            'punk-rider-pro' => array(
                'name' => 'Punk Rider Pro',
                'tagline' => 'Best all-rounder (mid-range)',
                'category' => 'mid-range',
                'price' => 1299.00, // Using estimated price since "Check Price" was shown
                'speed' => 31.5,
                'range' => 23.2,
                'weight' => 69,
                'max_load' => 260,
                'battery' => 936,
                'power' => 1200,
                'has_suspension' => true,
                'tire_size' => 10,
                'ip_rating' => 54,
                'build_quality' => 0.85,
                'is_offroad' => false,
                'image' => 'punk-rider-pro.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#punk-rider-pro',
                'selling_points' => [
                    'Strong dual-motor acceleration and speed',
                    'Distinctive design with good lighting',
                    'Solid build quality feel',
                    'Self-healing 10" tubeless tires',
                    'Capable dual suspension'
                ]
            ),
            'splach-twin-plus' => array(
                'name' => 'Splach Twin Plus',
                'tagline' => 'Most portable dual-motor',
                'category' => 'mid-range',
                'price' => 999.00,
                'speed' => 28.6,
                'range' => 25.7,
                'weight' => 52,
                'max_load' => 265,
                'battery' => 748,
                'power' => 1200,
                'has_suspension' => true,
                'tire_size' => 8.5,
                'ip_rating' => 54,
                'build_quality' => 0.8,
                'is_offroad' => false,
                'image' => 'splach-twin-plus.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#splach-twin-plus',
                'selling_points' => [
                    'Very quick dual-motor acceleration',
                    'Lightest dual-motor scooter (52 lbs)',
                    'Highly portable (folds very compactly)',
                    'Strong hill climbing ability',
                    'Good value for portable performance'
                ]
            ),
            'nami-burn-e-2-max' => array(
                'name' => 'Nami Burn-E 2 Max',
                'tagline' => 'Best street hyperscooter',
                'category' => 'hyperscooter',
                'price' => 3999.00,
                'speed' => 61.2,
                'range' => 59.8,
                'weight' => 103,
                'max_load' => 265,
                'battery' => 2880,
                'power' => 3000,
                'has_suspension' => true,
                'tire_size' => 11,
                'ip_rating' => 55,
                'build_quality' => 0.95,
                'is_offroad' => false,
                'image' => 'nami-burn-e-2-max.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#nami-burn-e-2-max',
                'selling_points' => [
                    'Phenomenal acceleration and 60+ MPH top speed',
                    'Massive 2880Wh battery',
                    'Highly tunable hydraulic suspension',
                    'Excellent stability at speed (steering damper)',
                    'Refined build quality and powerful 72V system'
                ]
            ),
            'kaabo-wolf-king-gtr' => array(
                'name' => 'Kaabo Wolf King GTR',
                'tagline' => 'Best off-road hyperscooter',
                'category' => 'hyperscooter',
                'price' => 3895.00,
                'speed' => 66,
                'range' => 48.2,
                'weight' => 137,
                'max_load' => 330,
                'battery' => 2520,
                'power' => 4000,
                'has_suspension' => true,
                'tire_size' => 12,
                'ip_rating' => 54,
                'build_quality' => 0.9,
                'is_offroad' => true,
                'image' => 'kaabo-wolf-king-gtr.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#kaabo-wolf-king-gtr',
                'selling_points' => [
                    'Unrivaled off-road capability (suspension travel, clearance, 12" tires)',
                    'Highest top speed (66 MPH) and immense power',
                    'Smooth throttle control, great for technical terrain',
                    'Extremely durable build quality (double stem)',
                    'Excellent 330 lbs max load capacity'
                ]
            ),
            'segway-gt2' => array(
                'name' => 'Segway GT2',
                'tagline' => 'Best "entry" hyperscooter',
                'category' => 'hyperscooter',
                'price' => 4998.00,
                'speed' => 41.5,
                'range' => 39.4,
                'weight' => 116,
                'max_load' => 331,
                'battery' => 1512,
                'power' => 3000,
                'has_suspension' => true,
                'tire_size' => 11,
                'ip_rating' => 55,
                'build_quality' => 0.95,
                'is_offroad' => false,
                'image' => 'segway-gt2.jpg',
                'url' => 'https://eridehero.com/best-electric-scooters/#segway-gt2',
                'selling_points' => [
                    'Excellent stability and handling at speed (low CoG, TCS)',
                    'Superb adjustable dual suspension system',
                    'Outstanding design, build quality, and fantastic OLED display',
                    'Strong dual-motor performance',
                    'High 331 lbs max load capacity'
                ]
            )
        );
    }
}

// Initialize the quiz
new ERideHero_Scooter_Quiz();