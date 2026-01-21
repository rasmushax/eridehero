<?php
/**
 * REST API Spec Editor Endpoints.
 *
 * Provides endpoints for the Spec Editor admin dashboard:
 * - GET /spec-editor/schema/{type} - Get column schema for a product type
 * - GET /spec-editor/products/{type} - Get products with their specs
 * - POST /spec-editor/update - Update a single spec value
 *
 * @package ERH\Api
 */

declare(strict_types=1);

namespace ERH\Api;

use ERH\CategoryConfig;
use ERH\CacheKeys;
use ERH\Schema\AcfSchemaParser;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for spec editor endpoints.
 */
class RestSpecEditor extends WP_REST_Controller {

    /**
     * Namespace for the REST API.
     *
     * @var string
     */
    protected $namespace = 'erh/v1';

    /**
     * Resource base.
     *
     * @var string
     */
    protected $rest_base = 'spec-editor';

    /**
     * ACF Schema parser instance.
     *
     * @var AcfSchemaParser
     */
    private AcfSchemaParser $schema_parser;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->schema_parser = new AcfSchemaParser();
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Get schema for a product type.
        register_rest_route($this->namespace, '/' . $this->rest_base . '/schema/(?P<type>[a-z]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_schema'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args'                => [
                    'type' => [
                        'description' => 'Product type key (escooter, ebike, etc.)',
                        'type'        => 'string',
                        'required'    => true,
                        'validate_callback' => [$this, 'validate_product_type'],
                    ],
                ],
            ],
        ]);

        // Get products with specs for a product type.
        register_rest_route($this->namespace, '/' . $this->rest_base . '/products/(?P<type>[a-z]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_products'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args'                => [
                    'type' => [
                        'description' => 'Product type key (escooter, ebike, etc.)',
                        'type'        => 'string',
                        'required'    => true,
                        'validate_callback' => [$this, 'validate_product_type'],
                    ],
                    'page' => [
                        'description' => 'Page number for pagination',
                        'type'        => 'integer',
                        'default'     => 1,
                        'minimum'     => 1,
                    ],
                    'per_page' => [
                        'description' => 'Products per page (-1 for all, max 500 otherwise)',
                        'type'        => 'integer',
                        'default'     => 100,
                        'minimum'     => -1,
                        'maximum'     => 500,
                    ],
                    'search' => [
                        'description' => 'Search query for product name',
                        'type'        => 'string',
                        'default'     => '',
                    ],
                ],
            ],
        ]);

        // Update a single spec value.
        register_rest_route($this->namespace, '/' . $this->rest_base . '/update', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'update_spec'],
                'permission_callback' => [$this, 'check_admin_permission'],
                'args'                => [
                    'product_id' => [
                        'description' => 'Product post ID',
                        'type'        => 'integer',
                        'required'    => true,
                        'minimum'     => 1,
                    ],
                    'field_path' => [
                        'description' => 'Dot-notation field path',
                        'type'        => 'string',
                        'required'    => true,
                    ],
                    'value' => [
                        'description' => 'New value for the field',
                        'required'    => true,
                    ],
                    'type' => [
                        'description' => 'Product type for schema validation',
                        'type'        => 'string',
                        'required'    => true,
                    ],
                ],
            ],
        ]);

        // Get available product types.
        register_rest_route($this->namespace, '/' . $this->rest_base . '/types', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_types'],
                'permission_callback' => [$this, 'check_admin_permission'],
            ],
        ]);
    }

    /**
     * Check if user has admin permission.
     *
     * @return bool|WP_Error True if allowed, WP_Error otherwise.
     */
    public function check_admin_permission() {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access the spec editor.', 'erh-core'),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * Validate product type parameter.
     *
     * @param string $value Product type key.
     * @return bool True if valid.
     */
    public function validate_product_type(string $value): bool {
        return CategoryConfig::is_valid_key($value);
    }

    /**
     * Get schema for a product type.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error Response with schema.
     */
    public function get_schema(WP_REST_Request $request) {
        $type = $request->get_param('type');
        $schema = $this->schema_parser->get_schema($type);

        if (empty($schema)) {
            return new WP_Error(
                'no_schema',
                __('No schema found for this product type.', 'erh-core'),
                ['status' => 404]
            );
        }

        // Group columns by their group for frontend display.
        $groups = [];
        foreach ($schema as $column) {
            $group = $column['group'];
            if (!isset($groups[$group])) {
                $groups[$group] = [];
            }
            $groups[$group][] = $column;
        }

        return new WP_REST_Response([
            'type'    => $type,
            'columns' => $schema,
            'groups'  => $groups,
            'count'   => count($schema),
        ], 200);
    }

    /**
     * Get products with specs for a product type.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error Response with products.
     */
    public function get_products(WP_REST_Request $request) {
        $type = $request->get_param('type');
        $page = (int) $request->get_param('page');
        $per_page = (int) $request->get_param('per_page');
        $search = $request->get_param('search');

        // Get schema first.
        $schema = $this->schema_parser->get_schema($type);

        if (empty($schema)) {
            return new WP_Error(
                'no_schema',
                __('No schema found for this product type.', 'erh-core'),
                ['status' => 404]
            );
        }

        // Get the category config for product type taxonomy value.
        $category = CategoryConfig::get_by_key($type);
        if (!$category) {
            return new WP_Error(
                'invalid_type',
                __('Invalid product type.', 'erh-core'),
                ['status' => 400]
            );
        }

        // Build query args.
        // per_page of -1 means get all products (no pagination).
        $args = [
            'post_type'      => 'products',
            'post_status'    => ['publish', 'draft'],
            'posts_per_page' => $per_page === -1 ? -1 : $per_page,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'tax_query'      => [
                [
                    'taxonomy' => 'product_type',
                    'field'    => 'name',
                    'terms'    => $category['type'],
                ],
            ],
        ];

        // Only add paged if using pagination.
        if ($per_page !== -1) {
            $args['paged'] = $page;
        }

        // Add search if provided.
        if (!empty($search)) {
            $args['s'] = $search;
        }

        $query = new \WP_Query($args);

        // Transform products with their spec values.
        $products = [];
        foreach ($query->posts as $post) {
            $product = [
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'status'     => $post->post_status,
                'edit_url'   => get_edit_post_link($post->ID, 'raw'),
                'specs'      => $this->schema_parser->get_all_field_values($post->ID, $schema),
            ];
            $products[] = $product;
        }

        return new WP_REST_Response([
            'type'       => $type,
            'products'   => $products,
            'total'      => (int) $query->found_posts,
            'total_pages'=> (int) $query->max_num_pages,
            'page'       => $page,
            'per_page'   => $per_page,
        ], 200);
    }

    /**
     * Update a single spec value.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error Response with update result.
     */
    public function update_spec(WP_REST_Request $request) {
        $product_id = (int) $request->get_param('product_id');
        $field_path = $request->get_param('field_path');
        $new_value = $request->get_param('value');
        $type = $request->get_param('type');

        // Verify product exists.
        $post = get_post($product_id);
        if (!$post || $post->post_type !== 'products') {
            return new WP_Error(
                'invalid_product',
                __('Product not found.', 'erh-core'),
                ['status' => 404]
            );
        }

        // Get schema for validation.
        $schema = $this->schema_parser->get_schema($type);
        $column = null;
        foreach ($schema as $col) {
            if ($col['key'] === $field_path) {
                $column = $col;
                break;
            }
        }

        if (!$column) {
            return new WP_Error(
                'invalid_field',
                __('Field not found in schema.', 'erh-core'),
                ['status' => 400]
            );
        }

        // Check readonly.
        if (!empty($column['readonly'])) {
            return new WP_Error(
                'readonly_field',
                __('This field cannot be edited.', 'erh-core'),
                ['status' => 400]
            );
        }

        // Normalize value based on type.
        $normalized_value = $this->schema_parser->normalize_value($new_value, $column);

        // Validate value.
        $validation = $this->schema_parser->validate_value($normalized_value, $column);
        if (!$validation['valid']) {
            return new WP_Error(
                'validation_error',
                $validation['message'],
                ['status' => 400]
            );
        }

        // Get old value for response.
        $old_value = $this->schema_parser->get_field_value($product_id, $field_path);

        // Update the value.
        $success = $this->schema_parser->set_field_value($product_id, $field_path, $normalized_value);

        if (!$success) {
            return new WP_Error(
                'update_failed',
                __('Failed to update field value.', 'erh-core'),
                ['status' => 500]
            );
        }

        // Clear relevant caches.
        CacheKeys::clearProduct($product_id);

        // Log the change.
        $this->log_change($product_id, $field_path, $old_value, $normalized_value);

        return new WP_REST_Response([
            'success'    => true,
            'product_id' => $product_id,
            'field_path' => $field_path,
            'old_value'  => $old_value,
            'new_value'  => $normalized_value,
            'timestamp'  => current_time('mysql'),
        ], 200);
    }

    /**
     * Get available product types.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response Response with types.
     */
    public function get_types(WP_REST_Request $request) {
        $types = $this->schema_parser->get_product_types();

        // Add product counts.
        $types_with_counts = [];
        foreach ($types as $key => $label) {
            $category = CategoryConfig::get_by_key($key);
            $count = 0;

            if ($category) {
                $count = (new \WP_Query([
                    'post_type'      => 'products',
                    'post_status'    => ['publish', 'draft'],
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'tax_query'      => [
                        [
                            'taxonomy' => 'product_type',
                            'field'    => 'name',
                            'terms'    => $category['type'],
                        ],
                    ],
                ]))->found_posts;
            }

            $types_with_counts[] = [
                'key'   => $key,
                'label' => $label,
                'count' => (int) $count,
            ];
        }

        return new WP_REST_Response([
            'types' => $types_with_counts,
        ], 200);
    }

    /**
     * Log a spec change for audit trail.
     *
     * @param int    $product_id Product ID.
     * @param string $field_path Field path.
     * @param mixed  $old_value  Old value.
     * @param mixed  $new_value  New value.
     * @return void
     */
    private function log_change(int $product_id, string $field_path, $old_value, $new_value): void {
        // Get current user.
        $user = wp_get_current_user();
        $user_display = $user->display_name ?: $user->user_login;

        // Format values for logging.
        $old_str = is_array($old_value) ? wp_json_encode($old_value) : (string) $old_value;
        $new_str = is_array($new_value) ? wp_json_encode($new_value) : (string) $new_value;

        // Log to PHP error log (can be extended to custom table).
        error_log(sprintf(
            '[ERH Spec Editor] User %s (%d) changed product #%d field "%s": "%s" -> "%s"',
            $user_display,
            $user->ID,
            $product_id,
            $field_path,
            $old_str,
            $new_str
        ));
    }
}
