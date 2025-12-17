<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Meta_Boxes' ) ) {
	/**
	 * Class HFT_Meta_Boxes.
	 *
	 * Handles the registration and rendering of meta boxes for the plugin.
	 */
	class HFT_Meta_Boxes {

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Hooks will be added by the loader.
			// Add AJAX handler for fetching Amazon GEOs
			add_action( 'wp_ajax_hft_get_amazon_geos', [ $this, 'ajax_get_amazon_geos' ] );
		}

		/**
		 * Register meta boxes for product CPTs.
		 * Supports configurable post types via the hft_product_post_types filter.
		 */
		public function add_meta_boxes(): void {
			// Get all configured product post types
			$post_types = class_exists( 'HFT_Post_Type_Helper' )
				? HFT_Post_Type_Helper::get_product_post_types()
				: ['hf_product'];

			foreach ( $post_types as $post_type ) {
				add_meta_box(
					'hft_product_tracking_sources',
					__( 'Tracking Sources & Affiliate Links', 'housefresh-tools' ),
					[ $this, 'render_tracking_links_meta_box_html' ],
					$post_type, // Screen: The CPT slug.
					'advanced',   // Context: where the meta box should be displayed (normal, side, advanced).
					'high'        // Priority: within the context where the meta box should show.
				);
			}
		}

		/**
		 * Render the HTML for the Tracking Links meta box.
		 *
		 * @param WP_Post $post The post object.
		 */
		public function render_tracking_links_meta_box_html( WP_Post $post ): void {
			// Nonce for security.
			wp_nonce_field( 'hft_save_tracking_links_meta', 'hft_tracking_links_nonce' );

			global $wpdb;
			$table_name = $wpdb->prefix . 'hft_tracked_links';
			$tracking_links_raw = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table_name} WHERE product_post_id = %d ORDER BY id ASC", $post->ID ),
				ARRAY_A
			);

			$tracking_links = [];
			if ( ! empty( $tracking_links_raw ) ) {
                $amazon_parser = null;
                // Conditionally load and instantiate Amazon parser if Amazon links exist
                foreach ($tracking_links_raw as $temp_link_check) {
                    if (isset($temp_link_check['parser_identifier']) && $temp_link_check['parser_identifier'] === 'amazon') {
                        if (!class_exists('HFT_Amazon_Api_Parser')) {
                            $parser_file = HFT_PLUGIN_PATH . 'includes/parsers/class-hft-amazon-api-parser.php';
                            if (file_exists($parser_file)) {
                                require_once $parser_file;
                            }
                        }
                        if (class_exists('HFT_Amazon_Api_Parser')) {
                            $amazon_parser = new HFT_Amazon_Api_Parser();
                        }
                        break;
                    }
                }

				foreach ( $tracking_links_raw as $link_data ) {
					// Prepare geo_target for display in the text input (comma-separated string)
					$geo_target_array = !empty($link_data['geo_target']) ? json_decode($link_data['geo_target'], true) : [];
					$link_data['geo_target_input'] = is_array($geo_target_array) ? implode(', ', $geo_target_array) : '';

					// Determine source type for the template
                    // If parser is amazon, assume it was saved via ASIN, otherwise URL.
                    $link_data['source_type'] = (isset($link_data['parser_identifier']) && $link_data['parser_identifier'] === 'amazon') ? 'amazon_asin' : 'website_url';

                    // Generate Product Page URL
                    $product_page_url = '';
                    if ($link_data['source_type'] === 'amazon_asin') {
                        $asin = $link_data['tracking_url']; // ASIN is stored in tracking_url for amazon source
                        $geo_target = strtoupper(trim($link_data['geo_target'] ?? 'US')); // Default to US if null or empty

                        $geo_to_marketplace_domain = [
                            'US' => 'www.amazon.com',
                            'CA' => 'www.amazon.ca',
                            'GB' => 'www.amazon.co.uk',
                            'DE' => 'www.amazon.de',
                            'FR' => 'www.amazon.fr',
                            'ES' => 'www.amazon.es',
                            'IT' => 'www.amazon.it',
                            'AU' => 'www.amazon.com.au',
                            'BR' => 'www.amazon.com.br',
                            'IN' => 'www.amazon.in',
                            'JP' => 'www.amazon.co.jp',
                            'MX' => 'www.amazon.com.mx',
                            // Add others as needed
                        ];

                        $amazon_domain = $geo_to_marketplace_domain[$geo_target] ?? 'www.amazon.com'; // Fallback to .com

                        if (!empty($asin)) {
                            $product_page_url = "https://{$amazon_domain}/dp/{$asin}";
                        }
                    } elseif ($link_data['source_type'] === 'website_url') {
                        $product_page_url = $link_data['tracking_url'];
                    }
                    $link_data['product_page_url'] = $product_page_url;
                    $link_data['product_page_link_text'] = __( 'Product Page', 'housefresh-tools' );
                    
                    // Add parser key for the template
                    $link_data['parser_key'] = $this->get_parser_key($link_data);

					$tracking_links[] = $link_data;
				}
			}

			// Get available parsers before loading template
			$available_parsers = HFT_Tracked_Links_Integration::get_available_parsers();

			$template_path = HFT_PLUGIN_PATH . 'templates/admin/product-tracking-meta-box.php';
			if ( file_exists( $template_path ) ) {
				// Pass $post, $tracking_links and $available_parsers to the template.
				include $template_path;
			} else {
				echo '<p>' . esc_html__( 'Error: Meta box template not found at ', 'housefresh-tools' ) . esc_html($template_path) . '</p>';
			}

			// Localize script data for the admin scripts, including nonce for AJAX actions
			$localized_data = [
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'hft_ajax_nonce' ), // Use the general AJAX nonce name
				'confirm_delete_row' => __( 'Are you sure you want to delete this row?', 'housefresh-tools' ),
                'tracking_link_title' => __( 'Tracking Link', 'housefresh-tools' ),
				'available_parsers' => $available_parsers,
				// Add other localized strings or data as needed
			];
			wp_localize_script( 'hft-admin-scripts', 'hft_admin_meta_box_l10n', $localized_data );
		}

		/**
		 * Get parser key from link data
		 *
		 * @param array $link Link data
		 * @return string Parser key
		 */
		private function get_parser_key(array $link): string {
			if (!empty($link['scraper_id'])) {
				return 'scraper_' . $link['scraper_id'];
			}
			return $link['parser_identifier'] ?? '';
		}

		/**
		 * Save the meta box data for tracking links.
		 *
		 * @param int $post_id The ID of the post being saved.
		 */
		public function save_tracking_links_meta_data( int $post_id ): void {
			// Check if our nonce is set.
			if ( ! isset( $_POST['hft_tracking_links_nonce'] ) ) {
				return;
			}

			// Verify that the nonce is valid.
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hft_tracking_links_nonce'] ) ), 'hft_save_tracking_links_meta' ) ) {
				return;
			}

			// If this is an autosave, our form has not been submitted, so we don't want to do anything.
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			// Check the user's permissions.
			// The hook 'save_post_hf_product' already implies the post type, but an extra check is fine.
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'hft_tracked_links';
			$submitted_links_data = isset( $_POST['hft_tracking_links'] ) && is_array( $_POST['hft_tracking_links'] ) ? $_POST['hft_tracking_links'] : [];

			$existing_link_ids_in_db = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE product_post_id = %d", $post_id ) );
			$submitted_link_ids = [];

			foreach ( $submitted_links_data as $row_key => $link_row ) {
				// Parse parser selection
				$parser_key = sanitize_text_field($link_row['parser'] ?? '');
				$scraper_id = null;
				$parser_identifier = null;
				
				if (strpos($parser_key, 'scraper_') === 0) {
					// New scraper system
					$scraper_id = (int) str_replace('scraper_', '', $parser_key);
				} else {
					// Old system (Amazon or file-based)
					$parser_identifier = $parser_key;
				}

                $source_type = isset( $link_row['source_type'] ) ? sanitize_key( $link_row['source_type'] ) : 'website_url'; // Default to website_url
				$identifier_input = ''; // This will hold ASIN or URL
                $parser_slug_to_save = $parser_identifier; // This will be 'amazon' or the hostname
                $geo_target_to_save = null;

                if ( 'amazon_asin' === $source_type ) {
                    $asin_input = isset( $link_row['amazon_asin'] ) ? sanitize_text_field( strtoupper( trim( $link_row['amazon_asin'] ) ) ) : '';
                    if ( preg_match('/^[A-Z0-9]{10}$/', $asin_input) ) {
                        $identifier_input = $asin_input;
                        $parser_slug_to_save = 'amazon';

                        // Process SINGLE GEO Target from select dropdown
                        $geo_target_from_form = isset( $link_row['geo_target'] ) ? trim( wp_unslash( $link_row['geo_target'] ) ) : '';
                        if ( !empty($geo_target_from_form) ) {
                            $geo_target_candidate = strtoupper($geo_target_from_form);
                            // Basic validation: ensure it matches expected format (e.g., 2-3 uppercase letters)
                            if (preg_match('/^[A-Z]{2,3}$/', $geo_target_candidate)) {
                                 $geo_target_to_save = $geo_target_candidate; // Save the validated, uppercase string
                            } else {
                                 // Invalid format from select - should ideally not happen if options are controlled
                                 // error_log("HFT Save Meta: Invalid GEO Target format received from form - " . $geo_target_candidate);
                                 $geo_target_to_save = null; 
                            }
                        } else {
                             $geo_target_to_save = null; // Empty value from form (e.g. "-- Select --"), save as null
                        }
                       
                    } else {
                        // error_log("HFT Save Meta: Invalid ASIN skipped - " . $asin_input);
                        continue; // Invalid ASIN, skip
                    }
                } elseif ( 'website_url' === $source_type ) {
                    $url_input = isset( $link_row['tracking_url'] ) ? sanitize_url( $link_row['tracking_url'] ) : '';
                    if (!empty($url_input)) {
                        $identifier_input = $url_input;
                        $url_parts = wp_parse_url( $url_input ); 
                        $host = isset( $url_parts['host'] ) ? strtolower( $url_parts['host'] ) : 'unknown_host';
                        
                        // Normalize to base domain: remove www., shop., etc. then take last two parts for common TLDs
                        // More robust base domain extraction might be needed for complex TLDs (e.g. .co.uk)
                        $host_parts = explode('.', $host);
                        if (count($host_parts) > 2) {
                            // Check for common TLDs like .co.uk, .com.au, etc.
                            if (in_array($host_parts[count($host_parts)-2], ['co', 'com', 'org', 'net', 'gov', 'edu']) && count($host_parts) > 2) {
                                $base_domain = $host_parts[count($host_parts)-3] . '.' . $host_parts[count($host_parts)-2] . '.' . $host_parts[count($host_parts)-1];
                            } else {
                                $base_domain = $host_parts[count($host_parts)-2] . '.' . $host_parts[count($host_parts)-1];
                            }
                        } else {
                            $base_domain = $host; // Already a base domain or too short to tell
                        }
                        // Remove leading 'www.' if it's still there after potential subdomain stripping for base domain logic
                        if (strpos($base_domain, 'www.') === 0) {
                            $base_domain = substr($base_domain, 4);
                        }

                        $parser_slug_to_save = $base_domain; 
                        $geo_target_to_save = null;
                    } else {
                        continue; // Skip if URL is empty
                    }
                } else {
                    // error_log("HFT Save Meta: Unknown source type skipped - " . $source_type);
                    continue; // Unknown source type, skip
                }

				$affiliate_link_override = isset( $link_row['affiliate_link_override'] ) ? sanitize_url( $link_row['affiliate_link_override'] ) : '';
                $tracked_link_id = str_starts_with((string)$row_key, 'rule_') ? absint(substr((string)$row_key, 5)) : 0;

				if ( empty( $identifier_input ) ) {
                    // error_log("HFT Save Meta: Empty identifier skipped");
					continue;
				}

				$data = [
					'product_post_id'         => $post_id,
					'tracking_url'            => $identifier_input, 
					'parser_identifier'       => $parser_slug_to_save, 
					'scraper_id'              => $scraper_id,
					'geo_target'              => $geo_target_to_save, // Save single string or NULL
					'affiliate_link_override' => !empty($affiliate_link_override) ? $affiliate_link_override : null,
				];
				$format = ['%d', '%s', '%s', '%d', '%s', '%s']; // Added %d for scraper_id

				if ( $tracked_link_id > 0 && in_array( (string) $tracked_link_id, $existing_link_ids_in_db, true ) ) {
					$wpdb->update( $table_name, $data, [ 'id' => $tracked_link_id ], $format, ['%d'] );
					$submitted_link_ids[] = (string) $tracked_link_id;
				} elseif (str_starts_with((string)$row_key, 'new_')) {
                    $data['created_at'] = current_time( 'mysql', true );
                    $format[] = '%s';
					$wpdb->insert( $table_name, $data, $format );
					$new_id = $wpdb->insert_id;
					if ($new_id) {
					    $submitted_link_ids[] = (string) $new_id;
					}
				}
			}

			// Delete rows that were removed from the repeater
			$ids_to_delete = array_diff( $existing_link_ids_in_db, $submitted_link_ids );
			if ( ! empty( $ids_to_delete ) ) {
				// Safely delete using wpdb->delete() to avoid SQL injection risks
				// Validates each ID belongs to this product for additional security
				foreach ( $ids_to_delete as $id_to_delete ) {
					$wpdb->delete(
						$table_name,
						[
							'id'              => (int) $id_to_delete,
							'product_post_id' => $post_id
						],
						['%d', '%d']
					);
				}
			}
		}

		/**
		 * AJAX handler to fetch available Amazon GEOs.
		 */
		public function ajax_get_amazon_geos(): void {
			check_ajax_referer( 'hft_ajax_nonce', 'nonce' ); // Expect the general AJAX nonce

			$hft_settings = get_option( 'hft_settings', [] );
			$amazon_tags = $hft_settings['amazon_associate_tags'] ?? [];
			$amazon_geo_options = [];

			if ( is_array( $amazon_tags ) ) {
				foreach ( $amazon_tags as $tag_data ) {
					if ( isset( $tag_data['geo'] ) && ! empty( $tag_data['geo'] ) ) {
						$amazon_geo_options[] = strtoupper( trim( $tag_data['geo'] ) );
					}
				}
				$amazon_geo_options = array_values( array_unique( array_filter( $amazon_geo_options ) ) );
				sort( $amazon_geo_options );
			}

			if ( ! empty( $amazon_geo_options ) ) {
				wp_send_json_success( $amazon_geo_options );
			} else {
				wp_send_json_error( __( 'No Amazon GEOs configured in settings.', 'housefresh-tools' ) );
			}
		}
	}
} 