<?php
declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'HFT_Ajax' ) ) {
    /**
     * Class HFT_Ajax.
     *
     * Handles AJAX requests for the plugin.
     */
    class HFT_Ajax {

        /**
         * Constructor.
         * Actions should be registered by the loader.
         */
        public function __construct() {
            // add_action( 'wp_ajax_hft_force_scrape_product', [ $this, 'ajax_force_scrape_product' ] );
            // Corrected: Register the AJAX hook
            add_action( 'wp_ajax_hft_force_scrape_product', [ $this, 'ajax_force_scrape_product' ] );
        }

        /**
         * Handles the AJAX request to force scrape a product link from the CPT meta box.
         */
        public function ajax_force_scrape_product(): void {
            check_ajax_referer( 'hft_ajax_nonce', 'nonce' );

            // product_id is still useful for permission checks and context
            if ( ! isset( $_POST['product_id'] ) || ! current_user_can( 'edit_post', (int) $_POST['product_id'] ) ) {
                wp_send_json_error( [ 'message' => __( 'Invalid Product ID or insufficient permissions.', 'housefresh-tools' ) ] );
            }
            // $product_id = absint( $_POST['product_id'] ); // We have it if needed for context

            if ( ! isset( $_POST['tracked_link_id'] ) || empty( $_POST['tracked_link_id'] ) ) {
                wp_send_json_error( [ 'message' => __( 'Tracked Link ID not provided.', 'housefresh-tools' ) ] );
            }
            $tracked_link_id = absint( $_POST['tracked_link_id'] );

            $scraper_manager_file = HFT_PLUGIN_PATH . 'includes/class-hft-scraper-manager.php';
            if ( ! file_exists( $scraper_manager_file ) ) {
                wp_send_json_error( [ 'message' => __( 'Scraper Manager file not found.', 'housefresh-tools' ) ] );
            }
            require_once $scraper_manager_file;

            if ( ! class_exists('HFT_Scraper_Manager') ) {
                 wp_send_json_error( [ 'message' => __( 'Scraper Manager class not found.', 'housefresh-tools' ) ] );
            }

            $scraper_manager = new HFT_Scraper_Manager();
            $scrape_result = $scraper_manager->scrape_link( $tracked_link_id ); // Use scrape_link directly with tracked_link_id

            if ( $scrape_result && ! is_wp_error( $scrape_result ) ) {
                $updated_data = $this->get_updated_link_meta_for_ajax( $tracked_link_id ); // Changed helper name and param
                wp_send_json_success( [
                    'message' => __( 'Scrape successfully triggered.', 'housefresh-tools' ),
                    'updated_display' => $updated_data
                ] );
            } else {
                $error_message = is_wp_error( $scrape_result ) ? $scrape_result->get_error_message() : __( 'Unknown error during scrape.', 'housefresh-tools' );
                wp_send_json_error( [ 'message' => $error_message ] );
            }
        }

        /**
         * Helper to get updated display data for the meta box after a scrape for a specific tracked link.
         *
         * @param int $tracked_link_id
         * @return array
         */
        private function get_updated_link_meta_for_ajax( int $tracked_link_id ): array { // Renamed and changed param
            global $wpdb;
            $tracked_link_table = $wpdb->prefix . 'hft_tracked_links';
            $link_details = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT parser_identifier, current_price, current_currency, current_status, last_scraped_at 
                     FROM {$tracked_link_table} 
                     WHERE id = %d", // Query by tracked_link_id
                    $tracked_link_id
                ),
                ARRAY_A
            );

            $formatted_price = '-';
            if (isset($link_details['current_price']) && $link_details['current_price'] !== null) {
                $formatted_price = number_format((float) $link_details['current_price'], 2) . ' ' . esc_html($link_details['current_currency'] ?? '');
            }

            return [
                // 'parser_identifier' => esc_html($link_details['parser_identifier'] ?? 'N/A'), // JS currently doesn't update this specific field by ID
                'current_price_display' => $formatted_price,
                'current_status'    => esc_html($link_details['current_status'] ?? 'N/A'),
                'last_scraped_at'   => isset($link_details['last_scraped_at']) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $link_details['last_scraped_at'] ) ) : '-',
            ];
        }

    }
} 