<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handlers for scraper functionality
 */
class HFT_Scraper_Ajax {
    
    public function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks(): void {
        // Admin AJAX actions
        add_action('wp_ajax_hft_test_scraper', [$this, 'handle_test_scraper']);
        add_action('wp_ajax_hft_test_selector', [$this, 'handle_test_selector']);
        add_action('wp_ajax_hft_view_source', [$this, 'handle_view_source']);
        add_action('wp_ajax_hft_quick_test_scraper', [$this, 'handle_quick_test']);
    }
    
    /**
     * Handle full scraper test
     */
    public function handle_test_scraper(): void {
        // Check nonce
        if (!check_ajax_referer('hft_scraper_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'housefresh-tools')]);
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'housefresh-tools')]);
        }
        
        // Get parameters
        $scraper_id = isset($_POST['scraper_id']) ? absint($_POST['scraper_id']) : 0;
        $test_url = isset($_POST['test_url']) ? esc_url_raw($_POST['test_url']) : '';
        
        if (!$scraper_id || !$test_url) {
            wp_send_json_error(['message' => __('Missing required parameters.', 'housefresh-tools')]);
        }
        
        // Validate URL
        if (!filter_var($test_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => __('Invalid URL provided.', 'housefresh-tools')]);
        }
        
        // Start timing
        $start_time = microtime(true);
        
        try {
            // Get scraper
            $repository = new HFT_Scraper_Repository();
            $scraper = $repository->find($scraper_id);
            
            if (!$scraper) {
                wp_send_json_error(['message' => __('Scraper not found.', 'housefresh-tools')]);
            }
            
            // Create parser
            $parser = new HFT_Dynamic_Parser($scraper);
            
            // Parse URL
            $result = $parser->parse($test_url);
            
            // Calculate execution time
            $execution_time = microtime(true) - $start_time;
            
            // Prepare response
            $response = [
                'success' => empty($result['error']),
                'data' => $result,
                'execution_time' => round($execution_time, 3),
                'scraper_name' => $scraper->name,
                'timestamp' => current_time('mysql')
            ];
            
            // Log test
            $this->log_test($scraper_id, $test_url, $response);
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Test failed:', 'housefresh-tools') . ' ' . $e->getMessage(),
                'execution_time' => round(microtime(true) - $start_time, 3)
            ]);
        }
    }
    
    /**
     * Handle individual selector test
     */
    public function handle_test_selector(): void {
        // Check nonce
        if (!check_ajax_referer('hft_scraper_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'housefresh-tools')]);
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'housefresh-tools')]);
        }
        
        // Get parameters
        $test_url = isset($_POST['test_url']) ? esc_url_raw($_POST['test_url']) : '';
        $xpath_raw = $_POST['xpath'] ?? '';
        $xpath = stripslashes(trim($xpath_raw)); // Remove escaping and trim whitespace
        $attribute = isset($_POST['attribute']) ? sanitize_text_field($_POST['attribute']) : '';
        $field_type = isset($_POST['field_type']) ? sanitize_text_field($_POST['field_type']) : '';
        $scraper_id = isset($_POST['scraper_id']) ? absint($_POST['scraper_id']) : 0;

        if (!$test_url || !$xpath) {
            wp_send_json_error(['message' => __('Missing required parameters.', 'housefresh-tools')]);
        }

        // Validate XPath to prevent injection attacks
        if (!$this->is_valid_xpath($xpath)) {
            wp_send_json_error(['message' => __('Invalid or potentially dangerous XPath expression.', 'housefresh-tools')]);
        }
        
        try {
            // Get scraper settings for proper fetching method
            $use_curl = false;
            $use_scrapingrobot = false;
            if ($scraper_id > 0) {
                $repository = new HFT_Scraper_Repository();
                $scraper = $repository->find($scraper_id);
                if ($scraper) {
                    $use_curl = $scraper->use_curl;
                    $use_scrapingrobot = $scraper->use_scrapingrobot;
                }
            }
            
            if ($use_scrapingrobot) {
                // Use ScrapingRobot for JavaScript-rendered sites
                $settings = get_option('hft_settings');
                $api_key = $settings['scrapingrobot_api_key'] ?? '';
                
                if (empty($api_key)) {
                    wp_send_json_error(['message' => __('ScrapingRobot API key not configured.', 'housefresh-tools')]);
                }
                
                $api_url = add_query_arg([
                    'token' => $api_key,
                    'render' => 'true',
                    'url' => $test_url
                ], 'https://api.scrapingrobot.com/');
                
                $response = wp_remote_get($api_url, [
                    'timeout' => 60,
                    'headers' => ['Accept' => 'application/json']
                ]);
                
                if (is_wp_error($response)) {
                    wp_send_json_error(['message' => 'ScrapingRobot: ' . $response->get_error_message()]);
                }
                
                $body = wp_remote_retrieve_body($response);
                $json_response = json_decode($body, true);
                
                if (!isset($json_response['status']) || $json_response['status'] !== 'SUCCESS' || !isset($json_response['result'])) {
                    wp_send_json_error(['message' => __('ScrapingRobot failed to fetch content.', 'housefresh-tools')]);
                }
                
                $html = $json_response['result'];
                
            } elseif ($use_curl && function_exists('curl_init')) {
                // Use cURL for Dyson and similar sites
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $test_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
                
                // Cookie handling
                $cookie_file = wp_upload_dir()['basedir'] . '/hft-test-cookies-' . md5($test_url) . '.txt';
                curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
                curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
                
                // Headers
                $headers = [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    'Accept-Encoding: gzip, deflate',
                    'Cache-Control: no-cache',
                    'Pragma: no-cache',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                    'Sec-Fetch-Dest: document',
                    'Sec-Fetch-Mode: navigate',
                    'Sec-Fetch-Site: none',
                    'Sec-Fetch-User: ?1'
                ];
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_ENCODING, '');
                
                $html = curl_exec($ch);
                $errno = curl_errno($ch);
                $error = curl_error($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                // Clean up cookie file
                if (file_exists($cookie_file) && filemtime($cookie_file) < time() - 3600) {
                    @unlink($cookie_file);
                }
                
                if ($errno) {
                    wp_send_json_error(['message' => sprintf(__('cURL Error %d: %s', 'housefresh-tools'), $errno, $error)]);
                }
                
                if ($http_code >= 400) {
                    wp_send_json_error(['message' => sprintf(__('HTTP Error %d', 'housefresh-tools'), $http_code)]);
                }
            } else {
                // Use wp_remote_get for other sites
                $args = [
                    'timeout' => 30,
                    'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'headers' => [
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.9',
                        'Accept-Encoding' => 'gzip, deflate',
                        'Cache-Control' => 'no-cache',
                        'Pragma' => 'no-cache',
                        'Connection' => 'keep-alive',
                        'Upgrade-Insecure-Requests' => '1',
                        'Sec-Fetch-Dest' => 'document',
                        'Sec-Fetch-Mode' => 'navigate',
                        'Sec-Fetch-Site' => 'none',
                        'Sec-Fetch-User' => '?1',
                    ],
                    'compress' => true,
                    'decompress' => true,
                    'sslverify' => true,
                    'cookies' => [],
                ];
                
                $response = wp_remote_get($test_url, $args);
                
                if (is_wp_error($response)) {
                    wp_send_json_error(['message' => $response->get_error_message()]);
                }
                
                $html = wp_remote_retrieve_body($response);
            }
            
            if (empty($html)) {
                wp_send_json_error(['message' => __('Failed to fetch page content.', 'housefresh-tools')]);
            }
            
            // Create extractor
            $extractor = new HFT_XPath_Extractor($html);
            
            // Test selector
            $test_result = $extractor->test($xpath);
            
            if (!$test_result['valid']) {
                wp_send_json_error([
                    'message' => __('Invalid XPath:', 'housefresh-tools') . ' ' . $test_result['error']
                ]);
            }
            
            // Extract value
            $value = $extractor->extract($xpath, $attribute ?: null);
            
            // Apply post-processing if we have a field type
            if ($value && $field_type) {
                $processor = new HFT_Post_Processor();

                // Get post-processing rules from POST with proper sanitization
                $post_processing = [];
                if (!empty($_POST['post_processing']) && is_array($_POST['post_processing'])) {
                    // Sanitize the post_processing array recursively
                    $post_processing = $this->sanitize_post_processing_array($_POST['post_processing']);
                }

                $value = $processor->process($value, $post_processing, $field_type);
            }
            
            // Get all matches for context
            $all_values = $extractor->extractAll($xpath, $attribute ?: null);
            
            wp_send_json_success([
                'value' => $value,
                'raw_value' => $extractor->extract($xpath, $attribute ?: null),
                'match_count' => $test_result['count'],
                'all_values' => array_slice($all_values, 0, 5), // First 5 matches
                'selector_valid' => true
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Test failed:', 'housefresh-tools') . ' ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle view source request
     */
    public function handle_view_source(): void {
        // Check nonce
        if (!check_ajax_referer('hft_scraper_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'housefresh-tools')]);
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'housefresh-tools')]);
        }
        
        // Get parameters
        $test_url = isset($_POST['test_url']) ? esc_url_raw($_POST['test_url']) : '';
        $scraper_id = isset($_POST['scraper_id']) ? absint($_POST['scraper_id']) : 0;
        
        if (!$test_url) {
            wp_send_json_error(['message' => __('Missing test URL.', 'housefresh-tools')]);
        }
        
        // Validate URL
        if (!filter_var($test_url, FILTER_VALIDATE_URL)) {
            wp_send_json_error(['message' => __('Invalid URL provided.', 'housefresh-tools')]);
        }
        
        // Start timing
        $start_time = microtime(true);
        
        try {
            // Get scraper settings for proper fetching method
            $use_curl = false;
            $use_scrapingrobot = false;
            $scraper_name = 'Unknown';
            
            if ($scraper_id > 0) {
                $repository = new HFT_Scraper_Repository();
                $scraper = $repository->find($scraper_id);
                if ($scraper) {
                    $use_curl = $scraper->use_curl;
                    $use_scrapingrobot = $scraper->use_scrapingrobot;
                    $scraper_name = $scraper->name;
                }
            }
            
            // Fetch HTML using the same logic as test_selector
            $html = $this->fetch_html($test_url, $use_curl, $use_scrapingrobot);
            
            // Calculate execution time
            $execution_time = microtime(true) - $start_time;
            
            // Prepare response with HTML source
            $response = [
                'html' => $html,
                'url' => $test_url,
                'scraper_name' => $scraper_name,
                'fetch_method' => $use_scrapingrobot ? 'ScrapingRobot' : ($use_curl ? 'cURL' : 'WP Remote'),
                'execution_time' => round($execution_time, 3),
                'html_size' => strlen($html),
                'timestamp' => current_time('mysql')
            ];
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Failed to fetch source:', 'housefresh-tools') . ' ' . $e->getMessage(),
                'execution_time' => round(microtime(true) - $start_time, 3)
            ]);
        }
    }
    
    /**
     * Fetch HTML content using the appropriate method
     */
    private function fetch_html(string $test_url, bool $use_curl = false, bool $use_scrapingrobot = false): string {
        if ($use_scrapingrobot) {
            // Use ScrapingRobot for JavaScript-rendered sites
            $settings = get_option('hft_settings');
            $api_key = $settings['scrapingrobot_api_key'] ?? '';
            
            if (empty($api_key)) {
                throw new Exception(__('ScrapingRobot API key not configured.', 'housefresh-tools'));
            }
            
            $api_url = add_query_arg([
                'token' => $api_key,
                'render' => 'true',
                'url' => $test_url
            ], 'https://api.scrapingrobot.com/');
            
            $response = wp_remote_get($api_url, [
                'timeout' => 60,
                'headers' => ['Accept' => 'application/json']
            ]);
            
            if (is_wp_error($response)) {
                throw new Exception('ScrapingRobot: ' . $response->get_error_message());
            }
            
            $body = wp_remote_retrieve_body($response);
            $json_response = json_decode($body, true);
            
            if (!isset($json_response['status']) || $json_response['status'] !== 'SUCCESS' || !isset($json_response['result'])) {
                throw new Exception(__('ScrapingRobot failed to fetch content.', 'housefresh-tools'));
            }
            
            return $json_response['result'];
            
        } elseif ($use_curl && function_exists('curl_init')) {
            // Use cURL for sites that need special handling
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $test_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
            
            // Cookie handling
            $cookie_file = wp_upload_dir()['basedir'] . '/hft-test-cookies-' . md5($test_url) . '.txt';
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
            
            // Headers
            $headers = [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?1'
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            
            $html = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Clean up cookie file
            if (file_exists($cookie_file) && filemtime($cookie_file) < time() - 3600) {
                @unlink($cookie_file);
            }
            
            if ($errno) {
                throw new Exception(sprintf(__('cURL Error %d: %s', 'housefresh-tools'), $errno, $error));
            }
            
            if ($http_code >= 400) {
                throw new Exception(sprintf(__('HTTP Error %d', 'housefresh-tools'), $http_code));
            }
            
            return $html;
            
        } else {
            // Use wp_remote_get for standard sites
            $args = [
                'timeout' => 30,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Cache-Control' => 'no-cache',
                    'Pragma' => 'no-cache',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Sec-Fetch-User' => '?1',
                ],
                'compress' => true,
                'decompress' => true,
                'sslverify' => true,
                'cookies' => [],
            ];
            
            $response = wp_remote_get($test_url, $args);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $html = wp_remote_retrieve_body($response);
            
            if (empty($html)) {
                throw new Exception(__('Failed to fetch page content.', 'housefresh-tools'));
            }
            
            return $html;
        }
    }
    
    /**
     * Handle quick test from list page
     */
    public function handle_quick_test(): void {
        // Check nonce
        if (!check_ajax_referer('hft_scraper_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'housefresh-tools')]);
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'housefresh-tools')]);
        }
        
        $scraper_id = isset($_POST['scraper_id']) ? absint($_POST['scraper_id']) : 0;
        
        if (!$scraper_id) {
            wp_send_json_error(['message' => __('Invalid scraper ID.', 'housefresh-tools')]);
        }
        
        // For quick test, we need a sample URL
        wp_send_json_success([
            'need_url' => true,
            'message' => __('Please provide a test URL.', 'housefresh-tools')
        ]);
    }
    
    /**
     * Log test attempt
     */
    private function log_test(int $scraper_id, string $url, array $response): void {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'hft_scraper_logs';

        $wpdb->insert(
            $logs_table,
            [
                'scraper_id' => $scraper_id,
                'url' => $url,
                'success' => $response['success'],
                'extracted_data' => json_encode($response['data']),
                'error_message' => $response['data']['error'] ?? null,
                'execution_time' => $response['execution_time'] ?? null,
                'created_at' => current_time('mysql', true)
            ],
            ['%d', '%s', '%d', '%s', '%s', '%f', '%s']
        );
    }

    /**
     * Sanitize post-processing array recursively
     *
     * @param array $array The array to sanitize
     * @return array Sanitized array
     */
    private function sanitize_post_processing_array(array $array): array {
        $sanitized = [];

        foreach ($array as $key => $value) {
            // Sanitize the key
            $sanitized_key = sanitize_key($key);

            if (is_array($value)) {
                // Recursively sanitize nested arrays
                $sanitized[$sanitized_key] = $this->sanitize_post_processing_array($value);
            } else {
                // Sanitize scalar values
                $sanitized[$sanitized_key] = sanitize_text_field((string)$value);
            }
        }

        return $sanitized;
    }

    /**
     * Validate XPath expression to prevent injection attacks
     *
     * @param string $xpath The XPath expression to validate
     * @return bool True if valid, false otherwise
     */
    private function is_valid_xpath(string $xpath): bool {
        // Reject empty XPath
        if (empty($xpath)) {
            return false;
        }

        // Maximum reasonable length for XPath expression (prevent DoS)
        if (strlen($xpath) > 1000) {
            return false;
        }

        // Check for dangerous patterns that could indicate XPath injection
        $dangerous_patterns = [
            '/\bor\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?/i', // or '1'='1' style injection
            '/\band\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+[\'"]?/i', // and '1'='1' style injection
            '/union\s+select/i',                                // SQL-style union
            '/;\s*drop\s+/i',                                   // SQL injection attempts
            '/\bexec\s*\(/i',                                   // Function execution attempts
            '/\beval\s*\(/i',                                   // Eval attempts
        ];

        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $xpath)) {
                return false;
            }
        }

        // Try to validate XPath syntax using DOMXPath (basic validation)
        try {
            $dom = new DOMDocument();
            $dom->loadHTML('<html><body><div>test</div></body></html>');
            $domxpath = new DOMXPath($dom);

            // Suppress errors for invalid XPath and test if it can be queried
            @$domxpath->query($xpath);

            // If we got here without exception, it's at least valid XPath syntax
            return true;
        } catch (Exception $e) {
            // Invalid XPath syntax
            return false;
        }
    }
}