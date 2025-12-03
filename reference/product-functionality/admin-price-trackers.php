<?php
// Add this to your existing plugin file or include it using require_once

// Add menu item under Tools in WordPress admin
function add_price_tracker_tools_page() {
    add_management_page(
        'Price Trackers',
        'Price Trackers',
        'manage_options',
        'price-trackers',
        'display_price_trackers'
    );
}
add_action('admin_menu', 'add_price_tracker_tools_page');

// Display price trackers
function display_price_trackers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'price_trackers';

    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $wpdb->delete($table_name, array('id' => $id), array('%d'));
    }

    // Handle edit action
    if (isset($_POST['edit_tracker'])) {
        $id = intval($_POST['tracker_id']);
        $data = array(
            'start_price' => !empty($_POST['start_price']) ? floatval($_POST['start_price']) : null,
            'current_price' => !empty($_POST['current_price']) ? floatval($_POST['current_price']) : null,
            'target_price' => !empty($_POST['target_price']) ? floatval($_POST['target_price']) : null,
            'price_drop' => !empty($_POST['price_drop']) ? floatval($_POST['price_drop']) : null,
            'last_notified_price' => !empty($_POST['last_notified_price']) ? floatval($_POST['last_notified_price']) : null,
            'updated_at' => current_time('mysql')
        );
        $formats = array('%f', '%f', '%f', '%f', '%f', '%s');
        $wpdb->update($table_name, $data, array('id' => $id), $formats);
    }

    // Get all trackers
    $price_trackers = $wpdb->get_results("SELECT * FROM $table_name");
    $total_trackers = count($price_trackers);

    ?>
    <div class="wrap">
        <h1>Price Trackers <span class="tracker-count">(<?php echo $total_trackers; ?> total)</span></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Product</th>
                    <th>Start Price</th>
                    <th>Current Price</th>
                    <th>Target Price</th>
                    <th>Price Drop</th>
                    <th>Last Notified Price</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                    <th>Last Notification Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($price_trackers as $tracker): 
                    $user = get_user_by('id', $tracker->user_id);
                    $username = $user ? $user->user_login : 'Unknown';
                    $profile_url = $user ? get_edit_user_link($tracker->user_id) : '#';
                    
                    // Get post information
                    $post = get_post($tracker->product_id);
                    $post_title = $post ? $post->post_title : 'Unknown Product';
                    $edit_post_link = $post ? get_edit_post_link($tracker->product_id) : '#';
                ?>
                <tr>
                    <td><?php echo esc_html($tracker->id); ?></td>
                    <td><a href="<?php echo esc_url($profile_url); ?>"><?php echo esc_html($username); ?></a></td>
                    <td><a href="<?php echo esc_url($edit_post_link); ?>"><?php echo esc_html($post_title); ?></a></td>
                    <td><?php echo is_null($tracker->start_price) ? '' : esc_html($tracker->start_price); ?></td>
                    <td><?php echo is_null($tracker->current_price) ? '' : esc_html($tracker->current_price); ?></td>
                    <td><?php echo is_null($tracker->target_price) ? '' : esc_html($tracker->target_price); ?></td>
                    <td><?php echo is_null($tracker->price_drop) ? '' : esc_html($tracker->price_drop); ?></td>
                    <td><?php echo is_null($tracker->last_notified_price) ? '' : esc_html($tracker->last_notified_price); ?></td>
                    <td><?php echo esc_html($tracker->created_at); ?></td>
                    <td><?php echo esc_html($tracker->updated_at); ?></td>
                    <td><?php echo esc_html($tracker->last_notification_time); ?></td>
                    <td>
                        <a href="#" class="edit-tracker" data-id="<?php echo $tracker->id; ?>">Edit</a> |
                        <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'delete', 'id' => $tracker->id)), 'delete_tracker'); ?>" onclick="return confirm('Are you sure you want to delete this tracker?');">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Edit Tracker Modal -->
    <div id="edit-tracker-modal" style="display: none;">
        <form id="edit-tracker-form" method="post">
            <input type="hidden" name="tracker_id" id="edit-tracker-id">
            <p>
                <label for="edit-start-price">Start Price:</label>
                <input type="number" step="0.01" name="start_price" id="edit-start-price" required>
            </p>
            <p>
                <label for="edit-current-price">Current Price:</label>
                <input type="number" step="0.01" name="current_price" id="edit-current-price" required>
            </p>
            <p>
                <label for="edit-target-price">Target Price:</label>
                <input type="number" step="0.01" name="target_price" id="edit-target-price">
            </p>
            <p>
                <label for="edit-price-drop">Price Drop:</label>
                <input type="number" step="0.01" name="price_drop" id="edit-price-drop" required>
            </p>
            <p>
                <label for="edit-last-notified-price">Last Notified Price:</label>
                <input type="number" step="0.01" name="last_notified_price" id="edit-last-notified-price">
            </p>
            <p>
                <input type="submit" name="edit_tracker" value="Update Tracker" class="button button-primary">
                <button type="button" class="button cancel-edit">Cancel</button>
            </p>
        </form>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('.edit-tracker').click(function(e) {
            e.preventDefault();
            var id = $(this).data('id');
            var row = $(this).closest('tr');
            var startPrice = row.find('td:eq(3)').text();
            var currentPrice = row.find('td:eq(4)').text();
            var targetPrice = row.find('td:eq(5)').text();
            var priceDrop = row.find('td:eq(6)').text();
            var lastNotifiedPrice = row.find('td:eq(7)').text();

            $('#edit-tracker-id').val(id);
            $('#edit-start-price').val(startPrice);
            $('#edit-current-price').val(currentPrice);
            $('#edit-target-price').val(targetPrice);
            $('#edit-price-drop').val(priceDrop);
            $('#edit-last-notified-price').val(lastNotifiedPrice);

            $('#edit-tracker-modal').show();
        });

        $('.cancel-edit').click(function() {
            $('#edit-tracker-modal').hide();
        });
    });
    </script>
    <?php
}

// Enqueue admin styles
function price_tracker_admin_styles($hook) {
    if($hook != 'tools_page_price-trackers') {
        return;
    }
    wp_enqueue_style('price-tracker-admin-css', plugins_url('css/admin-style.css', __FILE__));
    wp_enqueue_script('jquery');
}
add_action('admin_enqueue_scripts', 'price_tracker_admin_styles');

// Add the Email Preferences column to the users list
function add_email_preferences_column($columns) {
    $columns['email_preferences'] = 'Email Preferences';
    return $columns;
}
add_filter('manage_users_columns', 'add_email_preferences_column');

// Populate the Email Preferences column with data
function populate_email_preferences_column($value, $column_name, $user_id) {
    if ($column_name === 'email_preferences') {
        // Get user meta values
        $price_trackers_emails = get_user_meta($user_id, 'price_trackers_emails', true);
        $sales_roundup_emails = get_user_meta($user_id, 'sales_roundup_emails', true);
        $sales_roundup_frequency = get_user_meta($user_id, 'sales_roundup_frequency', true);
        $newsletter_subscription = get_user_meta($user_id, 'newsletter_subscription', true);

        // Format the display string
        $price_trackers = $price_trackers_emails == '1' ? 'On' : 'Off';
        $sales_roundup = $sales_roundup_emails == '1' ? ucfirst($sales_roundup_frequency) : 'Off';
        $newsletter = $newsletter_subscription == '1' ? 'On' : 'Off';

        return "{$price_trackers}-{$sales_roundup}-{$newsletter}";
    }
    return $value;
}
add_filter('manage_users_custom_column', 'populate_email_preferences_column', 10, 3);

// Make the column sortable (optional)
function make_email_preferences_column_sortable($columns) {
    $columns['email_preferences'] = 'email_preferences';
    return $columns;
}
add_filter('manage_users_sortable_columns', 'make_email_preferences_column_sortable');

// Handle sorting (optional)
function email_preferences_column_orderby($query) {
    if (!is_admin()) {
        return;
    }

    $orderby = $query->get('orderby');

    if ('email_preferences' === $orderby) {
        // You might want to adjust this depending on which field you want to sort by
        $query->set('meta_key', 'price_trackers_emails');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_users', 'email_preferences_column_orderby');