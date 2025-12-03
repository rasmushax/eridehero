<?php
// Add meta box to product edit page
function add_clear_price_history_button() {
    add_meta_box(
        'clear_price_history_button',
        'Clear Price History',
        'render_clear_price_history_button',
        'products',
        'side',
        'low'
    );
}
add_action('add_meta_boxes', 'add_clear_price_history_button');

// Render the clear price history button
function render_clear_price_history_button($post) {
    ?>
    <button type="button" id="clear-price-history" class="button">Clear Price History</button>
    <script>
    jQuery(document).ready(function($) {
        $('#clear-price-history').on('click', function() {
            if (confirm('Are you sure you want to clear the price history for this product?')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'clear_price_history',
                        product_id: <?php echo $post->ID; ?>,
                        nonce: '<?php echo wp_create_nonce('clear_price_history_nonce'); ?>'
                    },
                    success: function(response) {
                        alert(response);
                    }
                });
            }
        });
    });
    </script>
    <?php
}

// Handle the AJAX request to clear price history
function handle_clear_price_history() {
    check_ajax_referer('clear_price_history_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_die('You do not have permission to perform this action.');
    }

    $product_id = intval($_POST['product_id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'product_daily_prices';

    $result = $wpdb->delete(
        $table_name,
        array('product_id' => $product_id),
        array('%d')
    );

    if ($result !== false) {
        echo 'Price history cleared successfully.';
    } else {
        echo 'An error occurred while clearing the price history.';
    }

    wp_die();
}
add_action('wp_ajax_clear_price_history', 'handle_clear_price_history');