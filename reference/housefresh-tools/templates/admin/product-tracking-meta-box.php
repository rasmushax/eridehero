<?php
/**
 * Template for the Product Tracking Links Meta Box.
 *
 * @var WP_Post $post The current post object.
 * @var array   $tracking_links Existing tracking links data for this post.
 */

// Ensure this file is loaded within WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Nonce is typically output by the calling function in HFT_Meta_Boxes, but ensure it's there.
// wp_nonce_field( 'hft_save_tracking_links_meta', 'hft_tracking_links_nonce' );

// For JS to access plugin path or other localized data if needed.
// $localized_data = [
// 'ajax_url' => admin_url( 'admin-ajax.php' ),
// 'nonce'    => wp_create_nonce( 'hft_ajax_nonce' ), // For AJAX actions
// ];
// wp_localize_script( 'hft-admin-scripts', 'hft_meta_box_data', $localized_data );

// Example: Load existing links (this logic will be more robust in HFT_Meta_Boxes::render_tracking_links_meta_box_html)
// global $wpdb;
// $product_post_id = $post->ID;
// $table_name_tracked_links = $wpdb->prefix . 'hft_tracked_links';
// $existing_links = $wpdb->get_results(
// $wpdb->prepare("SELECT * FROM {$table_name_tracked_links} WHERE product_post_id = %d ORDER BY id ASC", $product_post_id),
// ARRAY_A
// );
// if (null === $existing_links) { $existing_links = []; } // Ensure it's an array

// For now, let's assume $existing_links is passed to this template by the calling method.
// The actual data loading will be in HFT_Meta_Boxes class (Task 2.6)
$existing_links = isset($tracking_links) && is_array($tracking_links) ? $tracking_links : [];

?>
<div id="hft-tracking-links-container" class="hft-meta-box-container">
	<div id="hft-tracking-links-repeater">
		<?php if ( empty( $existing_links ) ) : ?>
			<?php // Display one empty row by default if no links exist ?>
            <?php 
            // Prepare empty data for the template rendering helper function
            $link_data_empty = ['id' => '', 'source_type' => 'website_url', 'tracking_url' => '', 'geo_target' => '', 'affiliate_link_override' => ''];
            $index_empty = 0;
            // Call a function or include a partial to render the row to avoid repetition
            hft_render_meta_box_row($index_empty, $link_data_empty, false, $available_parsers);
            ?>
		<?php else : ?>
			<?php foreach ( $existing_links as $index => $link_data ) : ?>
                 <?php hft_render_meta_box_row($index, $link_data, false, $available_parsers); ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<button type="button" id="hft-add-tracking-link-button" class="button button-primary">
		<span class="dashicons dashicons-plus-alt" style="vertical-align: text-bottom;"></span> <?php esc_html_e( 'Add New Tracking Link', 'housefresh-tools' ); ?>
	</button>
</div>

<!-- Cloned Row Template (for JS) -->
<script type="text/html" id="hft-repeater-row-template">
    <?php 
    // Use the same rendering function for the template, passing placeholder values
    $link_data_template = ['id' => '', 'source_type' => 'website_url', 'tracking_url' => '', 'geo_target' => '', 'affiliate_link_override' => ''];
    $index_template = '{index}'; // JS will replace this
    hft_render_meta_box_row($index_template, $link_data_template, true, $available_parsers);
    ?>
</script>

<?php
/**
 * Helper function to render a single row in the meta box repeater (Compact Grid Layout with Select GEO).
 *
 * @param int|string $index The row index or JS placeholder.
 * @param array      $link_data The data for the link.
 * @param bool       $is_template Whether this is the JS template.
 * @param array      $available_parsers Available parsers for dropdown.
 */
function hft_render_meta_box_row( $index, array $link_data, bool $is_template = false, array $available_parsers = [] ): void {
    $row_id_prefix = $is_template ? $index : (is_numeric($link_data['id']) && $link_data['id'] > 0 ? 'rule_' . $link_data['id'] : 'new_'. $index);
    $row_unique_id = $is_template ? $index : ($link_data['id'] ?? 'new_' . $index);
    $source_type = $link_data['source_type'] ?? 'website_url';
    $link_id_value = $is_template ? '' : ($link_data['id'] ?? '');
    $is_amazon = ('amazon_asin' === $source_type);
    $tracking_input_value = $is_amazon ? ($link_data['tracking_url'] ?? '') : ''; // ASIN stored in tracking_url
    $url_input_value = !$is_amazon ? ($link_data['tracking_url'] ?? '') : '';

    // --- Prepare GEO Target Options (only for non-template rows initially) ---
    $amazon_geo_options = [];
    if (!$is_template) {
        $hft_settings = get_option('hft_settings', []);
        $amazon_tags = $hft_settings['amazon_associate_tags'] ?? []; // Assumes format [ 0 => ['geo' => 'US', 'tag' => '...'], 1 => ... ]
        if (is_array($amazon_tags)) {
            foreach ($amazon_tags as $tag_data) {
                if (isset($tag_data['geo']) && !empty($tag_data['geo'])) {
                    $amazon_geo_options[] = strtoupper(trim($tag_data['geo']));
                }
            }
            $amazon_geo_options = array_values(array_unique(array_filter($amazon_geo_options)));
            sort($amazon_geo_options);
        }
    }
    $saved_geo_target = $link_data['geo_target'] ?? ''; // Expecting single string like 'US'

    // --- Readonly data formatting ---
    $formatted_last_scraped = $is_template ? 'N/A' : (!empty($link_data['last_scraped_at']) ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($link_data['last_scraped_at'])) . ' UTC' : 'N/A');
    $formatted_price = $is_template ? 'N/A' : (!empty($link_data['current_price']) ? number_format((float)$link_data['current_price'], 2) . ' ' . esc_html($link_data['current_currency'] ?? '') : 'N/A');
    $formatted_status = $is_template ? 'N/A' : (!empty($link_data['current_status']) ? $link_data['current_status'] : 'N/A');
    $last_error = $is_template ? '' : ($link_data['last_error_message'] ?? '');
    ?>
	<div class="hft-tracking-link-row hft-compact-row" data-id="<?php echo esc_attr( (string) $row_unique_id ); ?>" data-row-index="<?php echo esc_attr( is_numeric($index) ? (string) $index : 'template' ); ?>">
        <input type="hidden" name="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][id]" value="<?php echo esc_attr( (string) $link_id_value ); ?>">
        
        <div class="hft-row-header">
            <h4 class="hft-row-title"><?php echo esc_html( sprintf( __( 'Tracking Link %s', 'housefresh-tools' ), $is_template ? '{rowIndexPlus1}' : $index + 1 ) ); ?></h4>
             <div class="hft-row-actions">
                <button type="button" class="button button-link hft-force-scrape-button" title="<?php esc_attr_e( 'Force Scrape', 'housefresh-tools' ); ?>" data-tracked-link-id="<?php echo esc_attr( (string) $link_id_value ); ?>" <?php echo empty($link_id_value) ? 'disabled' : ''; ?>>
                    <span class="dashicons dashicons-update-alt"></span>
                    <span class="spinner"></span>
                </button>
                <button type="button" class="button button-link hft-delete-row-button" title="<?php esc_attr_e( 'Delete Row', 'housefresh-tools' ); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
                <div class="hft-scrape-row-ajax-message"></div>
            </div>
        </div>

		<div class="hft-row-content-grid"> 
            
            <!-- Column 1: Parser Selection -->
            <div class="hft-grid-col hft-col-source">
                <label for="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][parser]"><?php esc_html_e( 'Parser', 'housefresh-tools' ); ?></label>
                <select name="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][parser]" id="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][parser]" class="hft-parser-select" required>
                    <option value=""><?php _e('Select Parser', 'housefresh-tools'); ?></option>
                    <?php 
                    $parser_key = $link_data['parser_key'] ?? '';
                    // $available_parsers should be passed from the template caller
                    if (!empty($available_parsers) && is_array($available_parsers)):
                        foreach ($available_parsers as $key => $label): ?>
                            <option value="<?php echo esc_attr($key); ?>" 
                                <?php selected($parser_key, $key); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach;
                    endif; ?>
                </select>
                <!-- Hidden fields to maintain compatibility -->
                <input type="hidden" name="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][source_type]" value="<?php echo esc_attr($source_type); ?>" class="hft-source-type-hidden">
            </div>

            <!-- Column 2: Identifier (URL or ASIN + GEO Select) -->
            <div class="hft-grid-col hft-col-identifier">
                <div class="hft-url-input-group" <?php echo $is_amazon ? 'style="display: none;"' : ''; ?>>
                    <label for="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][tracking_url]"><?php esc_html_e( 'Tracking URL', 'housefresh-tools' ); ?></label>
                    <input type="url" id="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][tracking_url]" name="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][tracking_url]" value="<?php echo esc_url( $url_input_value ); ?>" class="widefat" placeholder="https://..." <?php echo $is_amazon ? 'disabled' : ''; ?>>
                </div>
                 <div class="hft-asin-input-group" <?php echo $is_amazon ? '' : 'style="display: none;"'; ?>>
                    <div class="hft-asin-part">
                        <label for="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][amazon_asin]"><?php esc_html_e( 'Amazon ASIN', 'housefresh-tools' ); ?></label>
                        <input type="text" id="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][amazon_asin]" name="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][amazon_asin]" value="<?php echo esc_attr( $tracking_input_value ); ?>" class="regular-text" placeholder="B00XXXXXXX" <?php echo !$is_amazon ? 'disabled' : ''; ?>>
                    </div>
                    <div class="hft-geo-target-subfield">
                        <label for="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][geo_target]"><?php esc_html_e( 'GEO Target', 'housefresh-tools' ); ?></label>
                        <select name="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][geo_target]" id="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][geo_target]" class="hft-geo-target-selector" <?php echo !$is_amazon ? 'disabled' : ''; ?>>
                            <option value=""><?php esc_html_e( '-- Select --', 'housefresh-tools' ); ?></option>
                            <?php // Options populated dynamically for non-template rows by PHP ?>
                            <?php if (!$is_template): ?>
                                <?php foreach ($amazon_geo_options as $geo_code): ?>
                                    <option value="<?php echo esc_attr($geo_code); ?>" <?php selected($saved_geo_target, $geo_code); ?>>
                                        <?php echo esc_html($geo_code); ?>
                                    </option>
                                <?php endforeach; ?>
                             <?php else: ?>
                                 <?php // Template row - JS might populate this later if needed, or leave placeholder ?>
                             <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Column 3: Affiliate Override -->
            <div class="hft-grid-col hft-col-override">
                <label for="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][affiliate_link_override]"><?php esc_html_e( 'Affiliate Override', 'housefresh-tools' ); ?></label>
                <input type="url" id="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][affiliate_link_override]" name="hft_tracking_links[<?php echo esc_attr($row_id_prefix); ?>][affiliate_link_override]" value="<?php echo esc_url( $link_data['affiliate_link_override'] ?? '' ); ?>" class="widefat" placeholder="Optional override...">
            </div>

            <!-- Column 4: Status -->
            <div class="hft-grid-col hft-col-status">
                <label><?php esc_html_e( 'Current Status', 'housefresh-tools' ); ?></label>
                <div class="hft-status-display">
                    <span class="hft-current-price">P: <span class="value"><?php echo esc_html($formatted_price); ?></span></span> | 
                    <span class="hft-current-status">S: <span class="value"><?php echo esc_html($formatted_status); ?></span></span> | 
                    <span class="hft-last-scraped">Last: <span class="value"><?php echo esc_html($formatted_last_scraped); ?></span></span>
                    <?php if ( !$is_template && ! empty( $link_data['product_page_url'] ) ) : ?>
                        | <span class="hft-product-page-link-inline">
                            <a href="<?php echo esc_url( $link_data['product_page_url'] ); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html( $link_data['product_page_link_text'] ); ?>
                            </a>
                        </span>
                    <?php endif; ?>
                    <span class="hft-last-error" style="<?php echo empty($last_error) ? 'display:none;' : ''; ?>">Error: <span class="value"><?php echo esc_html( $last_error ); ?></span></span>
                </div>
            </div>
		</div> 
	</div>
    <?php
}
?> 