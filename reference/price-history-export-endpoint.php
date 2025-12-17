<?php
/**
 * Price History Export Endpoint
 *
 * Add this to the production site (as mu-plugin) to expose price history data
 * for migration to the new system.
 *
 * Usage:
 *   1. Copy to wp-content/mu-plugins/price-history-export.php on production
 *   2. Access via: /wp-json/erh-migration/v1/price-history?page=1&per_page=1000
 *   3. After migration, delete the file
 *
 * @package ERH-Migration
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

add_action('rest_api_init', function() {
    register_rest_route('erh-migration/v1', '/price-history', [
        'methods'             => 'GET',
        'callback'            => 'erh_export_price_history',
        'permission_callback' => function() {
            // Require admin for security, or use a secret key
            // return current_user_can('manage_options');

            // Alternative: Use a secret key (more convenient for automated migration)
            $secret = isset($_GET['secret']) ? sanitize_text_field($_GET['secret']) : '';
            return $secret === 'your-secret-key-here'; // Change this!
        },
        'args' => [
            'page' => [
                'default'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'default'           => 1000,
                'sanitize_callback' => 'absint',
            ],
            'product_id' => [
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    // Endpoint to get total count
    register_rest_route('erh-migration/v1', '/price-history/count', [
        'methods'             => 'GET',
        'callback'            => 'erh_export_price_history_count',
        'permission_callback' => function() {
            $secret = isset($_GET['secret']) ? sanitize_text_field($_GET['secret']) : '';
            return $secret === 'your-secret-key-here'; // Change this!
        },
    ]);
});

/**
 * Export price history data.
 */
function erh_export_price_history(WP_REST_Request $request) {
    global $wpdb;

    $page = $request->get_param('page');
    $per_page = min($request->get_param('per_page'), 5000); // Cap at 5000
    $product_id = $request->get_param('product_id');
    $offset = ($page - 1) * $per_page;

    $table_name = $wpdb->prefix . 'product_daily_prices';

    // Check if table exists.
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
    if (!$table_exists) {
        return new WP_Error('no_table', 'Price history table not found', ['status' => 404]);
    }

    // Build query.
    $where = '';
    $params = [];

    if ($product_id > 0) {
        $where = 'WHERE product_id = %d';
        $params[] = $product_id;
    }

    // Get total count.
    $count_sql = "SELECT COUNT(*) FROM {$table_name} {$where}";
    if (!empty($params)) {
        $count_sql = $wpdb->prepare($count_sql, $params);
    }
    $total = (int) $wpdb->get_var($count_sql);

    // Get data.
    $sql = "SELECT id, product_id, price, domain, date
            FROM {$table_name}
            {$where}
            ORDER BY date ASC, product_id ASC
            LIMIT %d OFFSET %d";

    $query_params = $params;
    $query_params[] = $per_page;
    $query_params[] = $offset;

    $results = $wpdb->get_results(
        $wpdb->prepare($sql, $query_params),
        ARRAY_A
    );

    // Also include product slug mapping for product_id resolution.
    $product_ids = array_unique(array_column($results, 'product_id'));
    $product_map = [];

    if (!empty($product_ids)) {
        $placeholders = implode(',', array_fill(0, count($product_ids), '%d'));
        $products = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_name FROM {$wpdb->posts}
                 WHERE ID IN ({$placeholders}) AND post_type = 'products'",
                $product_ids
            ),
            ARRAY_A
        );

        foreach ($products as $product) {
            $product_map[$product['ID']] = $product['post_name'];
        }
    }

    // Enrich results with product slugs.
    foreach ($results as &$row) {
        $row['product_slug'] = $product_map[$row['product_id']] ?? null;
    }

    return new WP_REST_Response([
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per_page,
        'pages'    => ceil($total / $per_page),
        'data'     => $results,
    ], 200);
}

/**
 * Get total count of price history records.
 */
function erh_export_price_history_count(WP_REST_Request $request) {
    global $wpdb;

    $table_name = $wpdb->prefix . 'product_daily_prices';

    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
    if (!$table_exists) {
        return new WP_Error('no_table', 'Price history table not found', ['status' => 404]);
    }

    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    $products = (int) $wpdb->get_var("SELECT COUNT(DISTINCT product_id) FROM {$table_name}");
    $date_range = $wpdb->get_row(
        "SELECT MIN(date) as oldest, MAX(date) as newest FROM {$table_name}",
        ARRAY_A
    );

    return new WP_REST_Response([
        'total_records'    => $total,
        'unique_products'  => $products,
        'oldest_date'      => $date_range['oldest'],
        'newest_date'      => $date_range['newest'],
    ], 200);
}
