<?php
/**
 * REST API Listicle Endpoint.
 *
 * Provides specs data for listicle item block AJAX loading.
 *
 * @package ERH\Api
 */

declare(strict_types=1);

namespace ERH\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for listicle block endpoints.
 */
class RestListicle extends WP_REST_Controller {

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
    protected $rest_base = 'listicle';

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Get specs HTML: POST /erh/v1/listicle/specs
        register_rest_route($this->namespace, '/' . $this->rest_base . '/specs', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'get_specs_html'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'product_id' => [
                        'description' => 'Product ID',
                        'type'        => 'integer',
                        'required'    => true,
                    ],
                    'category_key' => [
                        'description' => 'Category key for spec grouping',
                        'type'        => 'string',
                        'default'     => 'escooter',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get specs HTML for a product.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error Response with HTML.
     */
    public function get_specs_html(WP_REST_Request $request) {
        $product_id   = (int) $request->get_param('product_id');
        $category_key = sanitize_key($request->get_param('category_key'));

        // Validate product exists.
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'products') {
            return new WP_Error(
                'product_not_found',
                __('Product not found.', 'erh-core'),
                ['status' => 404]
            );
        }

        // Cache key based on product and category.
        $cache_key = "erh_listicle_specs_{$product_id}_{$category_key}";
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            $response = new WP_REST_Response(['html' => $cached], 200);
            $response->header('X-ERH-Cache', 'HIT');
            return $response;
        }

        // Generate specs HTML.
        $html = $this->render_specs_html($product_id, $category_key);

        // Cache for 6 hours (specs don't change often).
        set_transient($cache_key, $html, 6 * HOUR_IN_SECONDS);

        return new WP_REST_Response(['html' => $html], 200);
    }

    /**
     * Render specs HTML for a product.
     *
     * @param int    $product_id   Product ID.
     * @param string $category_key Category key.
     * @return string HTML content.
     */
    private function render_specs_html(int $product_id, string $category_key): string {
        ob_start();

        $template = ERH_PLUGIN_DIR . 'includes/blocks/listicle-item/template-specs.php';

        if (file_exists($template)) {
            include $template;
        } else {
            echo '<p class="listicle-item-error">' . esc_html__('Specs template not found.', 'erh-core') . '</p>';
        }

        return ob_get_clean();
    }
}
