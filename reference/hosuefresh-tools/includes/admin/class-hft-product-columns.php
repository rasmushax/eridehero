<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Product_Columns' ) ) {
	/**
	 * Class HFT_Product_Columns.
	 *
	 * Manages custom admin columns for product post types.
	 * Supports configurable post types via the hft_product_post_types filter.
	 */
	class HFT_Product_Columns {

		/**
		 * Cached product data to avoid N+1 queries.
		 *
		 * @var array
		 */
		private array $cached_product_data = [];

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Register column hooks for all configured product post types
			// Deferred to 'init' to allow other plugins to register hft_product_post_types filter
			add_action( 'init', [ $this, 'register_column_hooks' ] );

			// Pre-fetch data for all products to avoid N+1 queries
			add_action( 'load-edit.php', [ $this, 'prefetch_product_data' ] );

			// Handle sorting
			add_action( 'pre_get_posts', [ $this, 'handle_column_sorting' ] );

			// Add row actions
			add_filter( 'post_row_actions', [ $this, 'add_row_actions' ], 10, 2 );

			// Handle force scrape action
			add_action( 'admin_init', [ $this, 'handle_force_scrape' ] );

			// Add admin styles and scripts
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

			// Add AJAX handler for expanding tracking links
			add_action( 'wp_ajax_hft_get_tracking_links', [ $this, 'ajax_get_tracking_links' ] );

			// Display admin notices
			add_action( 'admin_notices', [ $this, 'display_admin_notices' ] );
		}

		/**
		 * Get product post types.
		 *
		 * @return array Array of product post type slugs.
		 */
		private function get_product_post_types(): array {
			return class_exists( 'HFT_Post_Type_Helper' )
				? HFT_Post_Type_Helper::get_product_post_types()
				: ['hf_product'];
		}

		/**
		 * Check if a post type is a product post type.
		 *
		 * @param string $post_type The post type to check.
		 * @return bool True if it's a product post type.
		 */
		private function is_product_post_type( string $post_type ): bool {
			return class_exists( 'HFT_Post_Type_Helper' )
				? HFT_Post_Type_Helper::is_product_post_type( $post_type )
				: ( 'hf_product' === $post_type );
		}

		/**
		 * Register column-related hooks for all configured product post types.
		 */
		public function register_column_hooks(): void {
			foreach ( $this->get_product_post_types() as $post_type ) {
				// Add custom columns
				add_filter( 'manage_' . $post_type . '_posts_columns', [ $this, 'add_custom_columns' ] );
				add_action( 'manage_' . $post_type . '_posts_custom_column', [ $this, 'render_custom_columns' ], 10, 2 );

				// Make columns sortable
				add_filter( 'manage_edit-' . $post_type . '_sortable_columns', [ $this, 'make_columns_sortable' ] );
			}
		}

		/**
		 * Add custom columns to the product listing.
		 *
		 * @param array $columns Existing columns.
		 * @return array Modified columns.
		 */
		public function add_custom_columns( array $columns ): array {
			$new_columns = [];

			// Add columns after title
			foreach ( $columns as $key => $value ) {
				$new_columns[ $key ] = $value;

				if ( $key === 'title' ) {
					$new_columns['housefresh_score'] = __( 'Score', 'housefresh-tools' );
					$new_columns['tracking_links'] = __( 'Tracking Links', 'housefresh-tools' );
					$new_columns['prices'] = __( 'Prices', 'housefresh-tools' );
					$new_columns['stock_status'] = __( 'Stock Status', 'housefresh-tools' );
					$new_columns['last_scraped'] = __( 'Last Scraped', 'housefresh-tools' );
					$new_columns['scraping_health'] = __( 'Health', 'housefresh-tools' );
				}
			}

			return $new_columns;
		}

		/**
		 * Pre-fetch all product data in a single query to avoid N+1 problem.
		 * Called on load-edit.php for product post types.
		 */
		public function prefetch_product_data(): void {
			global $wpdb;

			// Only run on product list screen
			$current_post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
			if ( empty( $current_post_type ) || ! $this->is_product_post_type( $current_post_type ) ) {
				return;
			}

			// Get all product IDs that will be displayed on current page
			$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
			$per_page = get_option( 'posts_per_page', 20 );
			$offset = ( $paged - 1 ) * $per_page;

			// Get product IDs for current page
			$product_ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = %s
				AND post_status IN ('publish', 'draft', 'pending', 'future', 'private')
				ORDER BY post_date DESC
				LIMIT %d OFFSET %d",
				$current_post_type,
				$per_page,
				$offset
			) );

			if ( empty( $product_ids ) ) {
				return;
			}

			// Fetch all tracking links data in one query
			$placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
			$table_name = $wpdb->prefix . 'hft_tracked_links';
			$scrapers_table = $wpdb->prefix . 'hft_scrapers';

			$all_links = $wpdb->get_results( $wpdb->prepare(
				"SELECT
					tl.product_post_id,
					tl.geo_target,
					tl.current_price,
					tl.current_currency,
					tl.current_status,
					tl.parser_identifier,
					tl.last_scraped_at,
					tl.last_scrape_successful,
					tl.consecutive_failures,
					s.geos as scraper_geos
				FROM {$table_name} tl
				LEFT JOIN {$scrapers_table} s ON s.domain = tl.parser_identifier
				WHERE tl.product_post_id IN ($placeholders)
				ORDER BY tl.product_post_id ASC, tl.geo_target ASC",
				...$product_ids
			), ARRAY_A );

			// Organize data by product ID
			foreach ( $all_links as $link ) {
				$product_id = (int) $link['product_post_id'];

				if ( ! isset( $this->cached_product_data[ $product_id ] ) ) {
					$this->cached_product_data[ $product_id ] = [
						'links' => [],
						'last_scraped' => null,
						'failure_stats' => [
							'failed_count' => 0,
							'total_count' => 0,
							'max_failures' => 0
						]
					];
				}

				// Store link data
				$this->cached_product_data[ $product_id ]['links'][] = $link;

				// Track last scraped
				if ( $link['last_scraped_at'] &&
					 ( ! $this->cached_product_data[ $product_id ]['last_scraped'] ||
					   $link['last_scraped_at'] > $this->cached_product_data[ $product_id ]['last_scraped'] ) ) {
					$this->cached_product_data[ $product_id ]['last_scraped'] = $link['last_scraped_at'];
				}

				// Track failure stats
				$this->cached_product_data[ $product_id ]['failure_stats']['total_count']++;
				if ( $link['last_scrape_successful'] == 0 ) {
					$this->cached_product_data[ $product_id ]['failure_stats']['failed_count']++;
				}
				$consecutive = (int) $link['consecutive_failures'];
				if ( $consecutive > $this->cached_product_data[ $product_id ]['failure_stats']['max_failures'] ) {
					$this->cached_product_data[ $product_id ]['failure_stats']['max_failures'] = $consecutive;
				}
			}
		}

		/**
		 * Render custom column content.
		 *
		 * @param string $column Column name.
		 * @param int $post_id Post ID.
		 */
		public function render_custom_columns( string $column, int $post_id ): void {
			switch ( $column ) {
				case 'housefresh_score':
					$this->render_score_column( $post_id );
					break;

				case 'tracking_links':
					$this->render_tracking_links_column( $post_id );
					break;

				case 'prices':
					$this->render_prices_column( $post_id );
					break;

				case 'stock_status':
					$this->render_stock_status_column( $post_id );
					break;

				case 'last_scraped':
					$this->render_last_scraped_column( $post_id );
					break;

				case 'scraping_health':
					$this->render_scraping_health_column( $post_id );
					break;
			}
		}

		/**
		 * Render HouseFresh Score column.
		 */
		private function render_score_column( int $post_id ): void {
			if ( function_exists( 'get_field' ) ) {
				$score = get_field( 'housefresh_score', $post_id );
				if ( $score && is_numeric( $score ) ) {
					$score_class = '';
					if ( $score >= 8 ) {
						$score_class = 'hft-score-high';
					} elseif ( $score >= 6 ) {
						$score_class = 'hft-score-medium';
					} else {
						$score_class = 'hft-score-low';
					}

					echo '<span class="hft-score ' . esc_attr( $score_class ) . '">' . esc_html( number_format( (float) $score, 1 ) ) . '</span>';
				} else {
					echo '<span class="hft-score hft-score-empty">—</span>';
				}
			} else {
				echo '<span class="hft-score hft-score-empty">—</span>';
			}
		}

		/**
		 * Render Tracking Links column.
		 */
		private function render_tracking_links_column( int $post_id ): void {
			global $wpdb;
			$table_name = $wpdb->prefix . 'hft_tracked_links';

			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE product_post_id = %d",
				$post_id
			) );

			if ( $count > 0 ) {
				echo '<div class="hft-tracking-links">';
				echo '<button type="button" class="hft-expand-links" data-product-id="' . esc_attr( $post_id ) . '">';
				echo '<span class="hft-link-count">' . esc_html( $count ) . ' ' . _n( 'link', 'links', $count, 'housefresh-tools' ) . '</span>';
				echo '<span class="dashicons dashicons-arrow-down-alt2"></span>';
				echo '</button>';
				echo '<div class="hft-links-details" id="hft-links-' . esc_attr( $post_id ) . '" style="display:none;"></div>';
				echo '</div>';
			} else {
				echo '<span class="hft-no-links">No links</span>';
			}
		}

		/**
		 * Render Prices column.
		 */
		private function render_prices_column( int $post_id ): void {
			// Use cached data if available
			$all_links = $this->cached_product_data[ $post_id ]['links'] ?? [];

			// Filter to links with prices
			$links = array_filter( $all_links, function( $link ) {
				return ! empty( $link['current_price'] );
			} );

			if ( ! empty( $links ) ) {
				echo '<div class="hft-price-pills">';

				$price_by_geo = [];

				foreach ( $links as $link ) {
					// For Amazon links, use the stored geo_target
					if ( strtolower( $link['parser_identifier'] ) === 'amazon' && ! empty( $link['geo_target'] ) ) {
						$geos = [ strtoupper( $link['geo_target'] ) ];
					}
					// For other scrapers, use the scraper's configured geos
					elseif ( ! empty( $link['scraper_geos'] ) ) {
						$geos = array_map( 'trim', explode( ',', $link['scraper_geos'] ) );
						$geos = array_map( 'strtoupper', $geos );
					}
					// Default to US if no geo info
					else {
						$geos = [ 'US' ];
					}

					// Map currency to likely geo (for display purposes when scraper has multiple geos)
					$currency_to_geo = [
						'USD' => 'US',
						'GBP' => 'GB',
						'EUR' => 'EU',
						'CAD' => 'CA',
						'AUD' => 'AU'
					];

					$display_geo = $currency_to_geo[ $link['current_currency'] ] ?? $geos[0];

					// Store the best price for each geo
					if ( ! isset( $price_by_geo[ $display_geo ] ) ||
						 (float) $link['current_price'] < (float) $price_by_geo[ $display_geo ]['price'] ) {
						$price_by_geo[ $display_geo ] = [
							'price' => $link['current_price'],
							'currency' => $link['current_currency'],
							'status' => $link['current_status']
						];
					}
				}

				// Display sorted by geo
				ksort( $price_by_geo );

				foreach ( $price_by_geo as $geo => $data ) {
					$price = number_format( (float) $data['price'], 2 );
					$currency = esc_html( $data['currency'] ?? 'USD' );

					// Currency symbols
					$currency_symbols = [
						'USD' => '$',
						'GBP' => '£',
						'EUR' => '€',
						'CAD' => 'C$',
						'AUD' => 'A$'
					];
					$symbol = $currency_symbols[ $currency ] ?? $currency;

					// Status class
					$status_class = ( $data['status'] && strtolower( $data['status'] ) === 'available' ) ? 'available' : 'unavailable';

					echo '<span class="hft-price-pill hft-price-' . esc_attr( $status_class ) . '">';
					echo esc_html( $geo ) . ': ' . esc_html( $symbol ) . esc_html( $price );
					echo '</span>';
				}

				echo '</div>';
			} else {
				echo '<span class="hft-no-prices">—</span>';
			}
		}

		/**
		 * Render Stock Status column.
		 */
		private function render_stock_status_column( int $post_id ): void {
			// Use cached data
			$links = $this->cached_product_data[ $post_id ]['links'] ?? [];

			if ( ! empty( $links ) ) {
				echo '<div class="hft-stock-pills">';

				$status_by_geo = [];

				foreach ( $links as $link ) {
					// For Amazon links, use the stored geo_target
					if ( strtolower( $link['parser_identifier'] ) === 'amazon' && ! empty( $link['geo_target'] ) ) {
						$geos = [ strtoupper( $link['geo_target'] ) ];
					}
					// For other scrapers, use the scraper's configured geos
					elseif ( ! empty( $link['scraper_geos'] ) ) {
						$geos = array_map( 'trim', explode( ',', $link['scraper_geos'] ) );
						$geos = array_map( 'strtoupper', $geos );
					}
					// Default to US if no geo info
					else {
						$geos = [ 'US' ];
					}

					// Map currency to likely geo (for display purposes when scraper has multiple geos)
					$currency_to_geo = [
						'USD' => 'US',
						'GBP' => 'GB',
						'EUR' => 'EU',
						'CAD' => 'CA',
						'AUD' => 'AU'
					];

					$display_geo = $currency_to_geo[ $link['current_currency'] ] ?? $geos[0];

					// Store status for each geo (prefer available status)
					if ( ! isset( $status_by_geo[ $display_geo ] ) ||
						 ( strtolower( $link['current_status'] ) === 'available' &&
						   strtolower( $status_by_geo[ $display_geo ] ) !== 'available' ) ) {
						$status_by_geo[ $display_geo ] = $link['current_status'];
					}
				}

				// Display sorted by geo
				ksort( $status_by_geo );

				foreach ( $status_by_geo as $geo => $status ) {
					$status = strtolower( $status ?? 'unknown' );
					$is_available = $status === 'available' || $status === 'in stock';

					$color_class = $is_available ? 'hft-stock-available' : 'hft-stock-unavailable';
					$tooltip = $geo . ': ' . ( $is_available ? 'In Stock' : 'Out of Stock' );

					echo '<span class="hft-stock-pill ' . esc_attr( $color_class ) . '" title="' . esc_attr( $tooltip ) . '">';
					echo esc_html( $geo ) . ': ';
					echo '<svg class="hft-cart-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
					echo '<circle cx="9" cy="21" r="1"></circle>';
					echo '<circle cx="20" cy="21" r="1"></circle>';
					echo '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>';
					echo '</svg>';
					echo '</span>';
				}

				echo '</div>';
			} else {
				echo '<span class="hft-no-stock">—</span>';
			}
		}

		/**
		 * Render Last Scraped column.
		 */
		private function render_last_scraped_column( int $post_id ): void {
			// Use cached data if available
			$last_scraped = $this->cached_product_data[ $post_id ]['last_scraped'] ?? null;

			// If not in cache, query database directly (handles sorting, filtering, pagination edge cases)
			if ( null === $last_scraped ) {
				global $wpdb;
				$table_name = $wpdb->prefix . 'hft_tracked_links';

				$last_scraped = $wpdb->get_var( $wpdb->prepare(
					"SELECT MAX(last_scraped_at) FROM {$table_name} WHERE product_post_id = %d",
					$post_id
				) );
			}

			if ( $last_scraped ) {
				$time_diff = human_time_diff( strtotime( $last_scraped ), current_time( 'timestamp', true ) );
				$days_ago = ( current_time( 'timestamp', true ) - strtotime( $last_scraped ) ) / DAY_IN_SECONDS;

				$class = '';
				if ( $days_ago > 7 ) {
					$class = 'hft-scraped-old';
				} elseif ( $days_ago > 3 ) {
					$class = 'hft-scraped-warning';
				} else {
					$class = 'hft-scraped-recent';
				}

				echo '<span class="hft-last-scraped ' . esc_attr( $class ) . '">';
				echo esc_html( $time_diff ) . ' ago';
				echo '</span>';
			} else {
				echo '<span class="hft-last-scraped hft-scraped-never">Never</span>';
			}
		}

		/**
		 * Render Scraping Health column.
		 */
		private function render_scraping_health_column( int $post_id ): void {
			// Use cached failure stats
			$stats = $this->cached_product_data[ $post_id ]['failure_stats'] ?? null;

			if ( $stats && $stats['total_count'] > 0 ) {
				$success_rate = ( ( $stats['total_count'] - $stats['failed_count'] ) / $stats['total_count'] ) * 100;

				$health_class = '';
				if ( $success_rate >= 80 ) {
					$health_class = 'hft-health-good';
					$icon = '✓';
				} elseif ( $success_rate >= 50 ) {
					$health_class = 'hft-health-warning';
					$icon = '!';
				} else {
					$health_class = 'hft-health-bad';
					$icon = '✗';
				}

				$tooltip = sprintf(
					'Success rate: %d%% | Failed: %d/%d',
					$success_rate,
					$stats['failed_count'],
					$stats['total_count']
				);

				if ( $stats['max_failures'] > 0 ) {
					$tooltip .= sprintf( ' | Max consecutive failures: %d', $stats['max_failures'] );
				}

				echo '<span class="hft-health ' . esc_attr( $health_class ) . '" title="' . esc_attr( $tooltip ) . '">';
				echo '<span class="hft-health-icon">' . esc_html( $icon ) . '</span> ';
				echo esc_html( round( $success_rate ) ) . '%';
				echo '</span>';
			} else {
				echo '<span class="hft-health hft-health-empty">—</span>';
			}
		}

		/**
		 * Make columns sortable.
		 */
		public function make_columns_sortable( array $columns ): array {
			$columns['housefresh_score'] = 'housefresh_score';
			$columns['last_scraped'] = 'last_scraped';
			return $columns;
		}

		/**
		 * Handle column sorting.
		 */
		public function handle_column_sorting( WP_Query $query ): void {
			if ( ! is_admin() || ! $query->is_main_query() ) {
				return;
			}

			$post_type = $query->get( 'post_type' );
			if ( ! $this->is_product_post_type( $post_type ) ) {
				return;
			}

			$orderby = $query->get( 'orderby' );

			if ( $orderby === 'housefresh_score' ) {
				$query->set( 'meta_key', 'housefresh_score' );
				$query->set( 'orderby', 'meta_value_num' );
			} elseif ( $orderby === 'last_scraped' ) {
				// This would require a custom query - leaving as is for now
			}
		}

		/**
		 * Add row actions.
		 */
		public function add_row_actions( array $actions, WP_Post $post ): array {
			if ( ! $this->is_product_post_type( $post->post_type ) ) {
				return $actions;
			}

			if ( current_user_can( 'edit_post', $post->ID ) ) {
				$url = wp_nonce_url(
					admin_url( 'admin.php?action=hft_force_scrape&product_id=' . $post->ID ),
					'hft_force_scrape_' . $post->ID
				);

				$actions['force_scrape'] = '<a href="' . esc_url( $url ) . '" class="hft-force-scrape">' .
					__( 'Force Scrape', 'housefresh-tools' ) . '</a>';
			}

			return $actions;
		}

		/**
		 * Handle force scrape action.
		 */
		public function handle_force_scrape(): void {
			if ( ! isset( $_GET['action'] ) || $_GET['action'] !== 'hft_force_scrape' ) {
				return;
			}

			$product_id = isset( $_GET['product_id'] ) ? (int) $_GET['product_id'] : 0;

			if ( ! $product_id || ! current_user_can( 'edit_post', $product_id ) ) {
				wp_die( __( 'You do not have permission to perform this action.', 'housefresh-tools' ) );
			}

			check_admin_referer( 'hft_force_scrape_' . $product_id );

			// Load scraper manager
			$scraper_manager_file = HFT_PLUGIN_PATH . 'includes/class-hft-scraper-manager.php';
			if ( file_exists( $scraper_manager_file ) ) {
				require_once $scraper_manager_file;

				if ( class_exists( 'HFT_Scraper_Manager' ) ) {
					$scraper_manager = new HFT_Scraper_Manager();

					// Get all tracked links for this product
					global $wpdb;
					$table_name = $wpdb->prefix . 'hft_tracked_links';
					$link_ids = $wpdb->get_col( $wpdb->prepare(
						"SELECT id FROM {$table_name} WHERE product_post_id = %d",
						$product_id
					) );

					$success_count = 0;
					foreach ( $link_ids as $link_id ) {
						$result = $scraper_manager->scrape_link( (int) $link_id );
						if ( $result && ! is_wp_error( $result ) ) {
							$success_count++;
						}
					}

					$message = sprintf(
						__( 'Force scrape completed. %d of %d links scraped successfully.', 'housefresh-tools' ),
						$success_count,
						count( $link_ids )
					);

					// Set admin notice
					set_transient( 'hft_admin_notice_' . get_current_user_id(), [
						'message' => $message,
						'type' => $success_count > 0 ? 'success' : 'error'
					], 30 );
				}
			}

			// Redirect back to products list (use the post type from the request or default)
			$post_type = get_post_type( $product_id );
			if ( ! $post_type || ! $this->is_product_post_type( $post_type ) ) {
				$post_type = class_exists( 'HFT_Post_Type_Helper' )
					? HFT_Post_Type_Helper::get_primary_post_type()
					: 'hf_product';
			}
			wp_redirect( admin_url( 'edit.php?post_type=' . $post_type ) );
			exit;
		}

		/**
		 * AJAX handler for getting tracking links.
		 */
		public function ajax_get_tracking_links(): void {
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( -1 );
			}

			$product_id = isset( $_POST['product_id'] ) ? (int) $_POST['product_id'] : 0;

			if ( ! $product_id ) {
				wp_die( -1 );
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'hft_tracked_links';

			$links = $wpdb->get_results( $wpdb->prepare(
				"SELECT parser_identifier, tracking_url, current_price, current_currency, current_status, last_scraped_at
				FROM {$table_name}
				WHERE product_post_id = %d
				ORDER BY parser_identifier ASC",
				$product_id
			), ARRAY_A );

			if ( empty( $links ) ) {
				echo '<p>' . __( 'No tracking links found.', 'housefresh-tools' ) . '</p>';
				wp_die();
			}

			echo '<ul class="hft-link-list">';
			foreach ( $links as $link ) {
				$parser_name = $this->get_retailer_name( $link['parser_identifier'] );
				$price_display = '—';

				if ( $link['current_price'] && $link['current_currency'] ) {
					$price_display = $this->format_price( $link['current_price'], $link['current_currency'] );
				}

				echo '<li>';
				echo '<strong>' . esc_html( $parser_name ) . '</strong>: ';
				echo esc_html( $price_display ) . ' ';
				echo '(' . esc_html( $link['current_status'] ?? 'Unknown' ) . ')';
				echo '<br><small class="hft-link-url">' . esc_html( $link['tracking_url'] ) . '</small>';
				echo '</li>';
			}
			echo '</ul>';

			wp_die();
		}

		/**
		 * Get retailer name from parser identifier.
		 */
		private function get_retailer_name( string $parser_id ): string {
			// Clean up parser identifier for display
			$name = str_replace( [ '-', '_', '.' ], ' ', $parser_id );
			$name = ucwords( $name );
			$name = str_replace( [ 'Com', 'Co Uk', 'Co' ], '', $name );
			return trim( $name );
		}

		/**
		 * Format price with currency.
		 */
		private function format_price( $price, string $currency ): string {
			$currency_symbols = [
				'USD' => '$',
				'GBP' => '£',
				'EUR' => '€',
				'CAD' => 'C$',
				'AUD' => 'A$'
			];

			$symbol = $currency_symbols[ $currency ] ?? $currency . ' ';
			return $symbol . number_format( (float) $price, 2 );
		}

		/**
		 * Enqueue admin assets.
		 */
		public function enqueue_admin_assets( string $hook ): void {
			$current_post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
			if ( $hook !== 'edit.php' || empty( $current_post_type ) || ! $this->is_product_post_type( $current_post_type ) ) {
				return;
			}

			// Add inline styles
			wp_add_inline_style( 'wp-admin', $this->get_admin_styles() );

			// Add inline script for expanding links
			wp_add_inline_script( 'jquery', $this->get_admin_script() );
		}

		/**
		 * Get admin styles.
		 */
		private function get_admin_styles(): string {
			return '
				/* HouseFresh Score */
				.hft-score {
					display: inline-block;
					padding: 2px 8px;
					border-radius: 3px;
					font-weight: bold;
					font-size: 14px;
				}
				.hft-score-high { background: #d4f4dd; color: #135e26; }
				.hft-score-medium { background: #fcf4dd; color: #8c6d1f; }
				.hft-score-low { background: #fdd4d4; color: #cc1818; }
				.hft-score-empty { color: #999; }

				/* Tracking Links */
				.hft-tracking-links { position: relative; }
				.hft-expand-links {
					background: none;
					border: none;
					color: #2271b1;
					cursor: pointer;
					padding: 0;
					text-decoration: underline;
					display: inline-flex;
					align-items: center;
					gap: 5px;
				}
				.hft-expand-links:hover { color: #135e96; }
				.hft-expand-links .dashicons { font-size: 16px; width: 16px; height: 16px; }
				.hft-expand-links.expanded .dashicons { transform: rotate(180deg); }
				.hft-links-details {
					position: absolute;
					top: 100%;
					left: 0;
					background: #fff;
					border: 1px solid #ddd;
					border-radius: 3px;
					box-shadow: 0 2px 5px rgba(0,0,0,0.1);
					padding: 10px;
					margin-top: 5px;
					z-index: 100;
					min-width: 300px;
					max-width: 500px;
				}
				.hft-link-list {
					margin: 0;
					padding: 0;
					list-style: none;
				}
				.hft-link-list li {
					margin-bottom: 10px;
					padding-bottom: 10px;
					border-bottom: 1px solid #eee;
				}
				.hft-link-list li:last-child {
					margin-bottom: 0;
					padding-bottom: 0;
					border-bottom: none;
				}
				.hft-link-url {
					color: #666;
					word-break: break-all;
				}
				.hft-no-links { color: #999; }

				/* Price Pills */
				.hft-price-pills {
					display: flex;
					flex-wrap: wrap;
					gap: 4px;
				}
				.hft-price-pill {
					display: inline-block;
					padding: 2px 8px;
					border-radius: 12px;
					font-size: 12px;
					white-space: nowrap;
				}
				.hft-price-available {
					background: #e6f4ea;
					color: #1e7e34;
				}
				.hft-price-unavailable {
					background: #fce4e4;
					color: #cc1818;
				}
				.hft-no-prices { color: #999; }

				/* Stock Pills */
				.hft-stock-pills {
					display: flex;
					flex-wrap: wrap;
					gap: 4px;
				}
				.hft-stock-pill {
					display: inline-flex;
					align-items: center;
					gap: 3px;
					padding: 2px 8px;
					border-radius: 12px;
					font-size: 12px;
					white-space: nowrap;
				}
				.hft-cart-icon {
					width: 14px;
					height: 14px;
				}
				.hft-stock-available {
					background: #e6f4ea;
					color: #1e7e34;
				}
				.hft-stock-unavailable {
					background: #fce4e4;
					color: #cc1818;
				}
				.hft-no-stock { color: #999; }

				/* Last Scraped */
				.hft-last-scraped {
					display: inline-block;
					padding: 2px 6px;
					border-radius: 3px;
					font-size: 12px;
				}
				.hft-scraped-recent { background: #e6f4ea; color: #1e7e34; }
				.hft-scraped-warning { background: #fcf4dd; color: #8c6d1f; }
				.hft-scraped-old { background: #fdd4d4; color: #cc1818; }
				.hft-scraped-never { color: #999; }

				/* Scraping Health */
				.hft-health {
					display: inline-flex;
					align-items: center;
					gap: 3px;
					padding: 2px 8px;
					border-radius: 3px;
					font-size: 12px;
					font-weight: bold;
					cursor: help;
				}
				.hft-health-icon { font-size: 14px; }
				.hft-health-good { background: #d4f4dd; color: #135e26; }
				.hft-health-warning { background: #fcf4dd; color: #8c6d1f; }
				.hft-health-bad { background: #fdd4d4; color: #cc1818; }
				.hft-health-empty { color: #999; }

				/* Force Scrape Action */
				.hft-force-scrape { color: #2271b1; }
				.hft-force-scrape:hover { color: #135e96; }

				/* Column widths */
				.column-housefresh_score { width: 60px; }
				.column-tracking_links { width: 100px; }
				.column-prices { width: 180px; }
				.column-stock_status { width: 140px; }
				.column-last_scraped { width: 100px; }
				.column-scraping_health { width: 80px; }
			';
		}

		/**
		 * Display admin notices.
		 */
		public function display_admin_notices(): void {
			$notice = get_transient( 'hft_admin_notice_' . get_current_user_id() );

			if ( $notice ) {
				$class = 'notice notice-' . ( $notice['type'] ?? 'info' ) . ' is-dismissible';
				printf(
					'<div class="%1$s"><p>%2$s</p></div>',
					esc_attr( $class ),
					esc_html( $notice['message'] )
				);

				delete_transient( 'hft_admin_notice_' . get_current_user_id() );
			}
		}

		/**
		 * Get admin script.
		 */
		private function get_admin_script(): string {
			return "
				jQuery(document).ready(function($) {
					// Handle expanding tracking links
					$('.hft-expand-links').on('click', function(e) {
						e.preventDefault();

						var button = $(this);
						var productId = button.data('product-id');
						var details = $('#hft-links-' + productId);

						if (button.hasClass('expanded')) {
							details.slideUp();
							button.removeClass('expanded');
						} else {
							// Load links if not already loaded
							if (details.is(':empty')) {
								details.html('<p>Loading...</p>');

								$.post(ajaxurl, {
									action: 'hft_get_tracking_links',
									product_id: productId
								}, function(response) {
									details.html(response);
								});
							}

							details.slideDown();
							button.addClass('expanded');
						}
					});

					// Close details when clicking outside
					$(document).on('click', function(e) {
						if (!$(e.target).closest('.hft-tracking-links').length) {
							$('.hft-links-details').slideUp();
							$('.hft-expand-links').removeClass('expanded');
						}
					});
				});
			";
		}
	}
}
