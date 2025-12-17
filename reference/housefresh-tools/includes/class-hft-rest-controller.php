<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_REST_Controller' ) ) {
	/**
	 * Class HFT_REST_Controller.
	 *
	 * Manages the plugin's REST API endpoints.
	 */
	class HFT_REST_Controller {

		private string $namespace = 'housefresh-tools/v1';
		private ?HFT_Rate_Limiter $rate_limiter = null;

		public function __construct() {
			add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		}

		/**
		 * Get or initialize the rate limiter instance
		 *
		 * @return HFT_Rate_Limiter
		 */
		private function get_rate_limiter(): HFT_Rate_Limiter {
			if ( null === $this->rate_limiter ) {
				// Ensure rate limiter class is loaded
				if ( ! class_exists( 'HFT_Rate_Limiter' ) ) {
					require_once HFT_PLUGIN_PATH . 'includes/class-hft-rate-limiter.php';
				}
				$this->rate_limiter = new HFT_Rate_Limiter();
			}
			return $this->rate_limiter;
		}

		public function register_routes(): void {
			register_rest_route(
				$this->namespace,
				'/get-affiliate-link',
				[
					'methods'             => WP_REST_Server::READABLE, // GET request
					'callback'            => [ $this, 'get_affiliate_link_by_id_callback' ],
					'permission_callback' => [ $this, 'check_rate_limit_get_affiliate_link' ],
					'args'                => [
						'product_id' => [
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function( $param, $request, $key ) {
								return is_numeric( $param ) && $param > 0;
							},
							'sanitize_callback' => 'absint',
						],
						'target_geo' => [
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'default'           => 'US',
						],
					],
				]
			);

			// New route for fetching products for SelectControl
			register_rest_route(
				$this->namespace,
				'/products-for-select',
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_products_for_select_callback' ],
					'permission_callback' => function() {
						// Require manage_options capability for admin-level product access
					// This prevents regular editors from enumerating all products
					$required_capability = apply_filters('hft_products_select_capability', 'manage_options');
					return current_user_can( $required_capability );
					}
					// No specific args needed for fetching all, but could add search/pagination later
				]
			);

			// New route for GEO IP detection
			register_rest_route(
				$this->namespace,
				'/detect-geo',
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'detect_geo_callback' ],
					'permission_callback' => [ $this, 'check_rate_limit_detect_geo' ],
					'args'                => [
						'test_ip' => [
							'required'          => false,
							'type'              => 'string',
							'validate_callback' => function( $param, $request, $key ) {
								return filter_var( $param, FILTER_VALIDATE_IP );
							},
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				]
			);

			$route_registered = register_rest_route(
				$this->namespace,
				'/product/(?P<product_id>\d+)/price-history',
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_product_price_history_callback' ],
					'permission_callback' => function( WP_REST_Request $request ) { // Add type hint for $request
						$product_id = (int) $request->get_param('product_id');
						return current_user_can( 'edit_post', $product_id );
					},
					'args'                => [
						'product_id' => [
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function( $param, $request, $key ) {
								return is_numeric( $param ) && $param > 0;
							},
							'sanitize_callback' => 'absint',
						],
					],
				]
			);
			if ( $route_registered === true ) {
			} else {
				// You could also log the WP_Error object if register_rest_route returns one on failure, 
				// though docs say it returns bool. $route_registered might be a WP_Error object here.
				if (is_wp_error($route_registered)) {
				}
			}

			// GEO-targeted price history endpoint for frontend blocks
			$chart_route_registered = register_rest_route(
				$this->namespace,
				'/product/(?P<product_id>\d+)/price-history-chart',
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_product_price_history_chart_callback' ],
					'permission_callback' => [ $this, 'check_rate_limit_price_history_chart' ],
					'args'                => [
						'product_id' => [
							'required'          => true,
							'type'              => 'integer',
							'validate_callback' => function( $param, $request, $key ) {
								return is_numeric( $param ) && $param > 0;
							},
							'sanitize_callback' => 'absint',
						],
						'target_geo' => [
							'required'          => false,
							'type'              => 'string',
							'default'           => 'US',
							'validate_callback' => function( $param, $request, $key ) {
								return is_string( $param ) && strlen( $param ) <= 5;
							},
							'sanitize_callback' => function( $param, $request, $key ) {
								return strtoupper( sanitize_text_field( $param ) );
							},
						],
					],
				]
			);
			if ( $chart_route_registered === true ) {
			} else {
				if (is_wp_error($chart_route_registered)) {
				}
			}
		}

		/**
		 * Rate limit check for get-affiliate-link endpoint
		 *
		 * @param WP_REST_Request $request The request object
		 * @return bool|WP_Error True if allowed, WP_Error if rate limited
		 */
		public function check_rate_limit_get_affiliate_link( WP_REST_Request $request ) {
			return $this->check_rate_limit_for_endpoint( 'get-affiliate-link', $request );
		}

		/**
		 * Rate limit check for detect-geo endpoint
		 *
		 * @param WP_REST_Request $request The request object
		 * @return bool|WP_Error True if allowed, WP_Error if rate limited
		 */
		public function check_rate_limit_detect_geo( WP_REST_Request $request ) {
			return $this->check_rate_limit_for_endpoint( 'detect-geo', $request );
		}

		/**
		 * Rate limit check for price-history-chart endpoint
		 *
		 * @param WP_REST_Request $request The request object
		 * @return bool|WP_Error True if allowed, WP_Error if rate limited
		 */
		public function check_rate_limit_price_history_chart( WP_REST_Request $request ) {
			return $this->check_rate_limit_for_endpoint( 'price-history-chart', $request );
		}

		/**
		 * Generic rate limit check for any endpoint
		 *
		 * @param string $endpoint The endpoint identifier
		 * @param WP_REST_Request $request The request object
		 * @return bool|WP_Error True if allowed, WP_Error if rate limited
		 */
		private function check_rate_limit_for_endpoint( string $endpoint, WP_REST_Request $request ) {
			$rate_limiter = $this->get_rate_limiter();
			$user_id = get_current_user_id();

			// Check rate limit
			$rate_limit_result = $rate_limiter->check_rate_limit( $endpoint, $user_id ?: null );

			// Add rate limit headers
			$rate_limiter->add_rate_limit_headers( $rate_limit_result );

			// If rate limit exceeded, return error
			if ( ! $rate_limit_result['allowed'] ) {
				return $rate_limiter->create_rate_limit_error( $rate_limit_result );
			}

			// Store rate limit result for use in callback (for headers)
			$request->set_param( '__rate_limit_result', $rate_limit_result );

			return true;
		}

		public function get_affiliate_link_by_id_callback( WP_REST_Request $request ): WP_REST_Response {
			global $wpdb;
			$product_id = $request->get_param('product_id');
			$user_target_geo = strtoupper($request->get_param('target_geo')); // User's originally detected GEO

			// Implement aggressive caching for affiliate links
			$cache_key = 'hft_aff_links_' . $product_id . '_' . $user_target_geo;
			$cache_group = 'hft_frontend';
			
			// Try object cache first (if available)
			$cached_response = wp_cache_get( $cache_key, $cache_group );
			if ( false !== $cached_response ) {
				return new WP_REST_Response( $cached_response, 200 );
			}
			
			// Try transient cache as fallback
			$cached_response = get_transient( $cache_key );
			if ( false !== $cached_response ) {
				// Also set in object cache for next request
				wp_cache_set( $cache_key, $cached_response, $cache_group, HOUR_IN_SECONDS );
				return new WP_REST_Response( $cached_response, 200 );
			}

			$table_name = $wpdb->prefix . 'hft_tracked_links';
			$query = $wpdb->prepare(
				"SELECT id, tracking_url, parser_identifier, current_price, current_currency, geo_target, affiliate_link_override 
				 FROM {$table_name} 
				 WHERE product_post_id = %d 
				 ORDER BY id ASC",
				$product_id
			);
			$all_tracked_links_for_product = $wpdb->get_results( $query, ARRAY_A );

			$response_links = [];
			$processed_geo_for_response = $user_target_geo; 
			$is_fallback_to_us = false;

			// Ensure required classes are loaded once
			if ( ! class_exists('HFT_Affiliate_Link_Generator') ) {
			    $generator_file = HFT_PLUGIN_PATH . 'includes/class-hft-affiliate-link-generator.php';
			    if (file_exists($generator_file)) { require_once $generator_file; } 
			    else { return new WP_REST_Response( [ 'error' => 'Affiliate link generator class not found.' ], 500 ); }
			}
			// Load scraper configs from database instead of options
			$scraper_configs = $this->load_scraper_configs_from_db();

			$geos_to_attempt = [$user_target_geo];
			if ($user_target_geo !== 'US') {
			    $geos_to_attempt[] = 'US';
			}

			foreach ($geos_to_attempt as $current_geo_to_process) {
			    if (!empty($response_links) && $current_geo_to_process === 'US' && $user_target_geo !== 'US') { 
			        // If we found links for the user's primary GEO, and this iteration is for the US fallback, skip US.
			        break;
			    }
			    $processed_geo_for_response = $current_geo_to_process;
			    $is_fallback_to_us = ($current_geo_to_process === 'US' && $user_target_geo !== 'US');

			    if (!empty($all_tracked_links_for_product)) {
			        foreach ($all_tracked_links_for_product as $db_row) {
			            $is_row_for_target_geo = false;
			            $parser_id_from_db = $db_row['parser_identifier'];
			            $original_url_from_db = $db_row['tracking_url'];
			            $affiliate_link_override_from_db = $db_row['affiliate_link_override'] ?? null;

			            if (strtolower($parser_id_from_db) === 'amazon') {
			                $db_row_geo = !empty($db_row['geo_target']) ? strtoupper(trim($db_row['geo_target'])) : null;
			                if ($db_row_geo === $current_geo_to_process) {
			                    $is_row_for_target_geo = true;
			                }
			            } else {
			                $scraper_config_key_to_try = $parser_id_from_db;
                                $alt_config_key = str_replace('.', '-', $parser_id_from_db);
			                if (!isset($scraper_configs[$scraper_config_key_to_try]) && isset($scraper_configs[$alt_config_key])) {
			                    $scraper_config_key_to_try = $alt_config_key;
			                }

			                if (isset($scraper_configs[$scraper_config_key_to_try])) {
			                    $config = $scraper_configs[$scraper_config_key_to_try];
			                    $geos_json = $config['geos'] ?? '[]';
			                    $allowed_geos_for_format = [];
			                    $configured_geos_objects = json_decode($geos_json, true);

			                    if (is_array($configured_geos_objects)) {
			                        foreach ($configured_geos_objects as $geo_obj) {
			                            if (is_array($geo_obj) && isset($geo_obj['value'])) {
			                                $allowed_geos_for_format[] = strtoupper(trim($geo_obj['value']));
			                            }
			                        }
			                    }
			                    if (!empty($allowed_geos_for_format) && in_array($current_geo_to_process, $allowed_geos_for_format, true)) {
			                        $is_row_for_target_geo = true;
			                    } else {
                                        }
			                } else {
                                        $alt_config_key_for_log = str_replace('.', '-', $parser_id_from_db);
                                    }
			            }

			            if ($is_row_for_target_geo) {
			                $product_id_override_for_generator = null;
			                if (strtolower($parser_id_from_db) === 'amazon') {
			                    $product_id_override_for_generator = HFT_Affiliate_Link_Generator::extract_asin_from_url($original_url_from_db);
			                    if (!$product_id_override_for_generator && HFT_Affiliate_Link_Generator::is_valid_asin($original_url_from_db)) {
			                        $product_id_override_for_generator = $original_url_from_db;
			                    }
			                }

                            $final_affiliate_url = null;
                            if (!empty($affiliate_link_override_from_db) && filter_var($affiliate_link_override_from_db, FILTER_VALIDATE_URL)) {
                                $final_affiliate_url = $affiliate_link_override_from_db;
                            } else if (strtolower($parser_id_from_db) === 'amazon') {
                                // For Amazon, use the existing affiliate link generator
                                $final_affiliate_url = HFT_Affiliate_Link_Generator::get_affiliate_link(
                                    $original_url_from_db,
                                    $current_geo_to_process, 
                                    $product_id_override_for_generator
                                );
                            } else {
                                // For custom scrapers, use the affiliate link format from the scraper config
                                if (isset($scraper_configs[$scraper_config_key_to_try]) && !empty($scraper_configs[$scraper_config_key_to_try]['affiliate_link_format'])) {
                                    $affiliate_format = $scraper_configs[$scraper_config_key_to_try]['affiliate_link_format'];
                                    $final_affiliate_url = str_replace('{URL}', $original_url_from_db, $affiliate_format);
                                    $final_affiliate_url = str_replace('{URLE}', urlencode($original_url_from_db), $final_affiliate_url);
                                } else {
                                    // No affiliate format defined, use original URL
                                    $final_affiliate_url = $original_url_from_db;
                                }
                            }

			                if ($final_affiliate_url !== null) {
			                    $retailer_name = $this->get_retailer_name_from_parser_id($parser_id_from_db);
			                    $price_string = '';
			                    if (!empty($db_row['current_price'])) {
			                        $price = number_format((float)$db_row['current_price'], 2);
			                        $currency_symbol = $this->get_currency_symbol($db_row['current_currency']);
			                        $price_string = $currency_symbol . $price;
			                    } else {
			                        $price_string = sprintf( __('View at %s', 'housefresh-tools'), $retailer_name );
			                    }

			                    $response_links[] = [
			                        'url'             => $final_affiliate_url,
			                        'retailer_name'   => $retailer_name,
			                        'price_string'    => $price_string,
			                        'parser_identifier' => $parser_id_from_db,
			                        'original_url_used' => $original_url_from_db,
                                        'link_geo'        => $current_geo_to_process, 
                                        'is_primary_geo'  => ($current_geo_to_process === $user_target_geo)
			                    ];
			                }
			            }
			        } // end foreach $all_tracked_links_for_product
			    } // end if !empty($all_tracked_links_for_product)
			} // end foreach $geos_to_attempt

			// Last resort: if still no links from DB rows, try permalink fallback for US GEO only
			if (empty($response_links)) {
			    $fallback_permalink = get_permalink($product_id) ?: '';
			    if ( !empty($fallback_permalink) && class_exists('HFT_Affiliate_Link_Generator') && HFT_Affiliate_Link_Generator::is_amazon_url($fallback_permalink) ) {
			        $asin = HFT_Affiliate_Link_Generator::extract_asin_from_url($fallback_permalink);
			        if ($asin) {
			            $affiliate_link = HFT_Affiliate_Link_Generator::get_affiliate_link($fallback_permalink, 'US', $asin);
			            if ($affiliate_link !== null) {
			                $response_links[] = [
			                    'url'             => $affiliate_link,
			                    'retailer_name'   => 'Amazon',
			                    'price_string'    => sprintf( __('View at %s', 'housefresh-tools'), 'Amazon' ),
			                    'parser_identifier' => 'amazon',
			                    'original_url_used' => $fallback_permalink,
			                    'link_geo'        => 'US',
			                    'is_primary_geo'  => ($user_target_geo === 'US'),
                                        'is_fallback_type' => 'permalink_us' 
			                ];
			                $processed_geo_for_response = 'US'; 
                                        $is_fallback_to_us = ($user_target_geo !== 'US'); // This indicates a full fallback to US permalink
			            }
			        }
			    }
			}

			$message = '';
			if (empty($response_links)) {
			    // If response_links is empty, message remains an empty string.
                // The JS will interpret an empty message + empty links as "display nothing".
                // $message = sprintf(
                //     __('Sorry, no prices were found for this product in your region (%s) or the US.', 'housefresh-tools'), 
                //     esc_html($user_target_geo)
                // );
			} elseif ($is_fallback_to_us && $processed_geo_for_response === 'US') {
                // This means user_geo links were not found (or user_geo was not US), and we are now providing US links.
                $message = sprintf(
                    __('Prices for your region (%s) were not available. Showing US options instead.', 'housefresh-tools'), 
                    esc_html($user_target_geo)
                );
            } 
            // If $response_links is not empty AND !$is_fallback_to_us, it means we found links for the user's primary GEO, so no special message.

			// Cache the response data
			$response_data = [ 'links' => $response_links, 'message' => $message, 'processed_geo' => $processed_geo_for_response ];
			
			// Store in both transient and object cache
			set_transient( $cache_key, $response_data, HOUR_IN_SECONDS );
			wp_cache_set( $cache_key, $response_data, $cache_group, HOUR_IN_SECONDS );
			
			return new WP_REST_Response( $response_data, 200 );
		}

		/**
		 * Helper function to get a displayable retailer name from parser_identifier.
		 *
		 * @param string $parser_id The parser identifier.
		 * @return string The displayable retailer name.
		 */
		private function get_retailer_name_from_parser_id(string $parser_id): string {
			// This can be expanded with a more sophisticated mapping or from parser settings
			if (strtolower($parser_id) === 'amazon') {
				return 'Amazon';
			}

			// Try to get from scraper configs
			$scraper_configs = $this->load_scraper_configs_from_db();
			if (isset($scraper_configs[$parser_id]['name'])) {
				return $scraper_configs[$parser_id]['name'];
			}

			// Fallback: clean up the ID
			$name = str_replace(['-', '_', '.'], ' ', $parser_id);
			$name = ucwords($name);
			$name = str_replace(['Direct', 'Com', 'Co Uk', 'Co'], '', $name); // Common suffixes
			return trim($name);
		}

		/**
		 * Helper function to get currency symbol.
		 *
		 * @param string|null $currency_code The 3-letter currency code.
		 * @return string The currency symbol or empty string.
		 */
		private function get_currency_symbol(?string $currency_code): string {
			if ($currency_code === null) return '';
			$symbols = [
				'USD' => '$',
				'CAD' => '$',
				'AUD' => '$',
				'GBP' => '£',
				'EUR' => '€',
				'JPY' => '¥',
				// Add more as needed
			];
			return $symbols[strtoupper($currency_code)] ?? $currency_code . ' '; // Fallback to code itself
		}

		public function get_products_for_select_callback( WP_REST_Request $request ): WP_REST_Response {
			// Use configurable post types for flexibility across different installations
			$post_types = class_exists( 'HFT_Post_Type_Helper' )
				? HFT_Post_Type_Helper::get_product_post_types()
				: ['hf_product'];

			$args = [
				'post_type'      => $post_types,
				'posts_per_page' => -1, // Get all products
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish', // Or any other relevant statuses
			];

			$product_posts = get_posts( $args );
			$options = [];

			if ( empty( $product_posts ) ) {
				return new WP_REST_Response( $options, 200 );
			}

			foreach ( $product_posts as $post ) {
				$options[] = [
					'value'        => $post->ID,
					'label'        => esc_html( $post->post_title ),
					'original_url' => get_permalink( $post->ID ),
				];
			}

			return new WP_REST_Response( $options, 200 );
		}

		/**
		 * Callback for the /detect-geo endpoint.
		 * Fetches GEO information based on the requester's IP address.
		 *
		 * @param WP_REST_Request $request The request object.
		 * @return WP_REST_Response The response object.
		 */
		public function detect_geo_callback( WP_REST_Request $request ): WP_REST_Response {
			// Attempt to get the user's IP address
			$user_ip = '';
			$ip_source_note = '';
			$default_country_code = 'US'; // Default country code

			$test_ip_param = $request->get_param('test_ip');

			if ( ! empty( $test_ip_param ) ) {
				$user_ip = $test_ip_param; // Already validated and sanitized by REST API args
				$ip_source_note = ' (using test_ip parameter)';
			} else {
				if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
					$user_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
				} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
					$ips     = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
					$user_ip = trim( $ips[0] );
				} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
					$user_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
				}
			}

			if ( empty( $user_ip ) || ! filter_var( $user_ip, FILTER_VALIDATE_IP ) ) {
				return new WP_REST_Response( [ 'country_code' => $default_country_code, 'error' => 'Could not determine client IP.' . $ip_source_note, 'ip' => $user_ip ], 200 );
			}

			// Prevent lookups for localhost or private IPs
			if ( filter_var( $user_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
					return new WP_REST_Response( [ 'country_code' => $default_country_code, 'ip' => $user_ip, 'note' => 'Private/Reserved IP, defaulted.' . $ip_source_note ], 200 );
			}

			// --- Enhanced Caching Logic with Object Cache ---
			// Sanitize IP for use in cache key
			$sanitized_ip_for_key = str_replace( ['.', ':'], ['_', '_'], $user_ip );
			$cache_key = 'hft_geoip_' . $sanitized_ip_for_key;
			$cache_group = 'hft_frontend';
			
			// Try object cache first
			$cached_country_code = wp_cache_get( $cache_key, $cache_group );
			if ( false !== $cached_country_code && preg_match( '/^[A-Z]{2}$/', $cached_country_code ) ) {
				return new WP_REST_Response( [ 'country_code' => $cached_country_code, 'ip' => $user_ip . $ip_source_note, 'source' => 'object_cache' ], 200 );
			}
			
			// Try transient cache
			$cached_country_code = get_transient( $cache_key );
			if ( false !== $cached_country_code && preg_match( '/^[A-Z]{2}$/', $cached_country_code ) ) {
				// Also set in object cache for next request
				wp_cache_set( $cache_key, $cached_country_code, $cache_group, DAY_IN_SECONDS );
				return new WP_REST_Response( [ 'country_code' => $cached_country_code, 'ip' => $user_ip . $ip_source_note, 'source' => 'transient_cache' ], 200 );
			}
			// --- End Enhanced Caching Logic ---
			
			// Use IPInfo service for geolocation
			$ipinfo_service = new HFT_IPInfo_Service();
			$geo_result = $ipinfo_service->get_country_code( $user_ip );
			
			// Cache the result for 24 hours if successful
			if ( ! empty( $geo_result['country_code'] ) && ! isset( $geo_result['error'] ) ) {
				set_transient( $cache_key, $geo_result['country_code'], DAY_IN_SECONDS );
				wp_cache_set( $cache_key, $geo_result['country_code'], $cache_group, DAY_IN_SECONDS );
			}
			
			// Add IP source note to response
			$geo_result['ip'] = $user_ip . $ip_source_note;
			
			return new WP_REST_Response( $geo_result, 200 );
		}

		/**
		 * Callback for the /product/{product_id}/price-history endpoint.
		 * Fetches price history for all tracked links associated with a given product ID.
		 *
		 * @param WP_REST_Request $request The request object.
		 * @return WP_REST_Response The response object.
		 */
		public function get_product_price_history_callback( WP_REST_Request $request ): WP_REST_Response {
			global $wpdb;
			$product_id = $request->get_param('product_id');

			$tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
			$price_history_table = $wpdb->prefix . 'hft_price_history';

			$product_tracked_links_data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, tracking_url, parser_identifier, current_price, current_currency 
					 FROM {$tracked_links_table} 
					 WHERE product_post_id = %d 
					 ORDER BY id ASC",
					$product_id
				),
				ARRAY_A
			);

			if (empty($product_tracked_links_data)) {
				return new WP_REST_Response( [ 'product_id' => $product_id, 'links' => [], 'message' => 'No tracked links found for this product.' ], 200 );
			}

			$response_data = [
				'productId' => $product_id,
				'links'     => [],
			];

			$all_time_lowest = null;
			$all_time_highest = null;
			$total_sum_prices = 0;
			$total_price_points = 0;

		// Optimize: Fetch all price history in a single query to avoid N+1 problem
		$tracked_link_ids = array_column($product_tracked_links_data, 'id');
		$history_by_link = [];

		if (!empty($tracked_link_ids)) {
			$placeholders = implode(',', array_fill(0, count($tracked_link_ids), '%d'));
			$all_price_history = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tracked_link_id, price, currency, status, scraped_at
					 FROM {$price_history_table}
					 WHERE tracked_link_id IN ($placeholders)
					 ORDER BY tracked_link_id ASC, scraped_at ASC",
					...$tracked_link_ids
				),
				ARRAY_A
			);

			// Group price history by tracked_link_id for efficient lookup
			foreach ($all_price_history as $history_entry) {
				$link_id = (int)$history_entry['tracked_link_id'];
				if (!isset($history_by_link[$link_id])) {
					$history_by_link[$link_id] = [];
				}
				$history_by_link[$link_id][] = $history_entry;
			}
		}

			foreach ( $product_tracked_links_data as $link_meta ) {
				$tracked_link_id = (int) $link_meta['id'];
			// Use pre-fetched history instead of querying in loop
			$link_history_raw = $history_by_link[$tracked_link_id] ?? [];

				$history_for_chart = [];
				$link_lowest = null;
				$link_highest = null;
				$link_sum_prices = 0;
				$link_price_points = 0;

				foreach ($link_history_raw as $history_entry) {
					$price = (float) $history_entry['price'];
					$history_for_chart[] = [
						'scraped_at' => $history_entry['scraped_at'], // Keep full datetime for chart JS
						'price'      => $price,
						// 'status'     => $history_entry['status'] // Optional: include status if chart needs it
					];

					// Link-specific summary
					if ($link_lowest === null || $price < $link_lowest['price']) {
						$link_lowest = ['price' => $price, 'date' => $history_entry['scraped_at']];
					}
					if ($link_highest === null || $price > $link_highest['price']) {
						$link_highest = ['price' => $price, 'date' => $history_entry['scraped_at']];
					}
					$link_sum_prices += $price;
					$link_price_points++;

                    // Overall summary (assuming same currency for simplicity in overall calculation for now)
                    if ($all_time_lowest === null || $price < $all_time_lowest['price']) {
                        $all_time_lowest = ['price' => $price, 'date' => $history_entry['scraped_at'], 'source_id' => $tracked_link_id];
                    }
                    if ($all_time_highest === null || $price > $all_time_highest['price']) {
                        $all_time_highest = ['price' => $price, 'date' => $history_entry['scraped_at'], 'source_id' => $tracked_link_id];
                    }
                    $total_sum_prices += $price;
                    $total_price_points++;
				}

				$link_average_price = ($link_price_points > 0) ? round($link_sum_prices / $link_price_points, 2) : null;
				$identifier_display = $link_meta['parser_identifier'];
				if ($link_meta['parser_identifier'] === 'amazon' && !empty($link_meta['tracking_url'])) {
				    $identifier_display .= ' (' . esc_html($link_meta['tracking_url']) . ')'; // Add ASIN
				} elseif ($link_meta['parser_identifier'] !== 'amazon') {
                    $url_parts = parse_url($link_meta['tracking_url']);
                    $identifier_display = $url_parts['host'] ?? $link_meta['tracking_url'];
                }

				$response_data['links'][] = [
					'trackedLinkId'    => $tracked_link_id,
					'identifier'       => $identifier_display,
					'currencySymbol'   => $this->get_currency_symbol($link_meta['current_currency']),
					'history'          => $history_for_chart,
					'summary'          => [
						'currentPrice'   => (float) $link_meta['current_price'],
						'lowestPrice'    => $link_lowest, // ['price' => X, 'date' => Y]
						'highestPrice'   => $link_highest, // ['price' => X, 'date' => Y]
						'averagePrice'   => $link_average_price,
					]
				];
			}

            // Prepare overall summary (basic version)
            // Note: This basic overall average assumes all links share the same currency or conversion is handled client-side.
            // For a robust overall summary with mixed currencies, currency conversion would be needed here.
            $overall_average_price = ($total_price_points > 0) ? round($total_sum_prices / $total_price_points, 2) : null;
            $response_data['overallSummary'] = [
                'lowestPrice'  => $all_time_lowest, // ['price' => X, 'date' => Y, 'source_id' => Z]
                'highestPrice' => $all_time_highest, // ['price' => X, 'date' => Y, 'source_id' => Z]
                'averagePrice' => $overall_average_price,
                'totalDataPoints' => $total_price_points
            ];

			return new WP_REST_Response( $response_data, 200 );
		}

		/**
		 * Callback for the /product/{product_id}/price-history-chart endpoint.
		 * Fetches GEO-targeted price history for a product, similar to affiliate links.
		 *
		 * @param WP_REST_Request $request The request object.
		 * @return WP_REST_Response The response object.
		 */
		public function get_product_price_history_chart_callback( WP_REST_Request $request ): WP_REST_Response {
			global $wpdb;
			$product_id = $request->get_param('product_id');
			$user_target_geo = strtoupper($request->get_param('target_geo')); // User's originally detected GEO

			// Implement caching for price history charts
			$cache_key = 'hft_price_chart_' . $product_id . '_' . $user_target_geo;
			$cache_group = 'hft_frontend';
			
			// Try object cache first
			$cached_response = wp_cache_get( $cache_key, $cache_group );
			if ( false !== $cached_response ) {
				return new WP_REST_Response( $cached_response, 200 );
			}
			
			// Try transient cache
			$cached_response = get_transient( $cache_key );
			if ( false !== $cached_response ) {
				wp_cache_set( $cache_key, $cached_response, $cache_group, 30 * MINUTE_IN_SECONDS );
				return new WP_REST_Response( $cached_response, 200 );
			}

			$tracked_links_table = $wpdb->prefix . 'hft_tracked_links';
			$price_history_table = $wpdb->prefix . 'hft_price_history';

			// Get all tracked links for this product
			$all_tracked_links_for_product = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, tracking_url, parser_identifier, current_price, current_currency, geo_target 
					 FROM {$tracked_links_table} 
					 WHERE product_post_id = %d 
					 ORDER BY id ASC",
					$product_id
				),
				ARRAY_A
			);

			if (empty($all_tracked_links_for_product)) {
				return new WP_REST_Response( [ 'product_id' => $product_id, 'links' => [], 'message' => 'No tracked links found for this product.' ], 200 );
			}

			// Load scraper configs for GEO filtering (same logic as affiliate links)
			// Load scraper configs from database instead of options
			$scraper_configs = $this->load_scraper_configs_from_db();

			$geos_to_attempt = [$user_target_geo];
			if ($user_target_geo !== 'US') {
			    $geos_to_attempt[] = 'US';
			}

			$response_data = [
				'productId' => $product_id,
				'targetGeo' => $user_target_geo,
				'links'     => [],
			];
		// Optimize: Fetch all price history in a single query to avoid N+1 problem
		$tracked_link_ids = array_column($all_tracked_links_for_product, 'id');
		$history_by_link = [];

		if (!empty($tracked_link_ids)) {
			$placeholders = implode(',', array_fill(0, count($tracked_link_ids), '%d'));
			$all_price_history = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tracked_link_id, price, currency, status, scraped_at
					 FROM {$price_history_table}
					 WHERE tracked_link_id IN ($placeholders)
					 ORDER BY tracked_link_id ASC, scraped_at ASC",
					...$tracked_link_ids
				),
				ARRAY_A
			);

			// Group price history by tracked_link_id for efficient lookup
			foreach ($all_price_history as $history_entry) {
				$link_id = (int)$history_entry['tracked_link_id'];
				if (!isset($history_by_link[$link_id])) {
					$history_by_link[$link_id] = [];
				}
				$history_by_link[$link_id][] = $history_entry;
			}
		}


			foreach ($geos_to_attempt as $current_geo_to_process) {
				// If we already found links for primary GEO, skip US fallback
				if (!empty($response_data['links']) && $current_geo_to_process === 'US' && $user_target_geo !== 'US') { 
					break;
				}

				foreach ($all_tracked_links_for_product as $db_row) {
					$is_row_for_target_geo = false;
					$parser_id_from_db = $db_row['parser_identifier'];

					// Same GEO filtering logic as affiliate links
					if (strtolower($parser_id_from_db) === 'amazon') {
						$db_row_geo = !empty($db_row['geo_target']) ? strtoupper(trim($db_row['geo_target'])) : null;
						if ($db_row_geo === $current_geo_to_process) {
							$is_row_for_target_geo = true;
						}
					} else {
						$scraper_config_key_to_try = $parser_id_from_db;
						$alt_config_key = str_replace('.', '-', $parser_id_from_db);
						if (!isset($scraper_configs[$scraper_config_key_to_try]) && isset($scraper_configs[$alt_config_key])) {
							$scraper_config_key_to_try = $alt_config_key;
						}

						if (isset($scraper_configs[$scraper_config_key_to_try])) {
							$config = $scraper_configs[$scraper_config_key_to_try];
							$geos_json = $config['geos'] ?? '[]';
							$allowed_geos_for_format = [];
							$configured_geos_objects = json_decode($geos_json, true);

							if (is_array($configured_geos_objects)) {
								foreach ($configured_geos_objects as $geo_obj) {
									if (is_array($geo_obj) && isset($geo_obj['value'])) {
										$allowed_geos_for_format[] = strtoupper(trim($geo_obj['value']));
									}
								}
							}
							if (!empty($allowed_geos_for_format) && in_array($current_geo_to_process, $allowed_geos_for_format, true)) {
								$is_row_for_target_geo = true;
							}
						}
					}

					if ($is_row_for_target_geo) {
						$tracked_link_id = (int) $db_row['id'];
						
						// Get price history for this tracked link
					// Use pre-fetched history instead of querying in loop
					$link_history_raw = $history_by_link[$tracked_link_id] ?? [];

						// Skip if no price history available
						if (empty($link_history_raw)) {
							continue;
						}

						$history_for_chart = [];
						foreach ($link_history_raw as $history_entry) {
							$history_for_chart[] = [
								'x' => $history_entry['scraped_at'], // Chart.js expects 'x' for time
								'y' => (float) $history_entry['price'], // Chart.js expects 'y' for value
								'date' => $history_entry['scraped_at'], // For tooltip
								'status' => $history_entry['status'], // For tooltip
							];
						}

						// Create retailer name for display
						$retailer_name = $this->get_retailer_name_from_parser_id($parser_id_from_db);
						if ($parser_id_from_db === 'amazon' && !empty($db_row['tracking_url'])) {
							$retailer_name .= ' (' . esc_html($db_row['tracking_url']) . ')'; // Add ASIN
						}

						$response_data['links'][] = [
							'trackedLinkId'    => $tracked_link_id,
							'retailerName'     => $retailer_name,
							'parserIdentifier' => $parser_id_from_db,
							'currencySymbol'   => $this->get_currency_symbol($db_row['current_currency']),
							'history'          => $history_for_chart,
							'geo'              => $current_geo_to_process,
						];
					}
				}
			}

			// Return empty if no price history found for the target GEO
			if (empty($response_data['links'])) {
				return new WP_REST_Response( [ 
					'product_id' => $product_id, 
					'targetGeo' => $user_target_geo,
					'links' => [], 
					'message' => 'No price history data available for the target GEO.' 
				], 200 );
			}

			// Cache the response data
			set_transient( $cache_key, $response_data, 30 * MINUTE_IN_SECONDS );
			wp_cache_set( $cache_key, $response_data, $cache_group, 30 * MINUTE_IN_SECONDS );
			
			return new WP_REST_Response( $response_data, 200 );
		}

		/**
		 * Load scraper configurations from database.
		 * Returns an array formatted like the old options system for compatibility.
		 *
		 * @return array Scraper configurations indexed by domain
		 */
		private function load_scraper_configs_from_db(): array {
			global $wpdb;
			
			// Cache scraper configurations
			$cache_key = 'hft_scraper_configs_active';
			$cache_group = 'hft_frontend';
			
			// Try object cache first
			$scrapers = wp_cache_get( $cache_key, $cache_group );
			if ( false !== $scrapers ) {
				return $scrapers;
			}
			
			// Try transient cache
			$cached_configs = get_transient( $cache_key );
			if ( false !== $cached_configs ) {
				wp_cache_set( $cache_key, $cached_configs, $cache_group, HOUR_IN_SECONDS );
				return $cached_configs;
			}
			
			$scrapers_table = $wpdb->prefix . 'hft_scrapers';
			$scrapers = $wpdb->get_results("
				SELECT id, domain, name, currency, geos, affiliate_link_format, is_active 
				FROM {$scrapers_table} 
				WHERE is_active = 1
			", ARRAY_A);
			
			$configs = [];
			
			foreach ($scrapers as $scraper) {
				// Convert comma-separated GEOs to JSON array format
				$geos_array = [];
				if (!empty($scraper['geos'])) {
					$geo_codes = array_map('trim', explode(',', $scraper['geos']));
					foreach ($geo_codes as $geo_code) {
						$geos_array[] = ['value' => strtoupper($geo_code)];
					}
				}
				
				// Use domain as the key (parser_identifier)
				$domain = $scraper['domain'];
				
				// Also create an entry with domain having dots replaced with hyphens
				// This handles cases where parser_identifier might have hyphens instead of dots
				$domain_with_hyphens = str_replace('.', '-', $domain);
				
				$config = [
					'name' => $scraper['name'],
					'currency' => $scraper['currency'],
					'geos' => json_encode($geos_array),
					'affiliate_link_format' => $scraper['affiliate_link_format'],
					'scraper_id' => $scraper['id'],
					'is_active' => $scraper['is_active']
				];
				
				// Store config under both domain variations
				$configs[$domain] = $config;
				if ($domain !== $domain_with_hyphens) {
					$configs[$domain_with_hyphens] = $config;
				}
			}
			
			// Cache the processed configs
			set_transient( $cache_key, $configs, HOUR_IN_SECONDS );
			wp_cache_set( $cache_key, $configs, $cache_group, HOUR_IN_SECONDS );
			
			return $configs;
		}
	}
} 