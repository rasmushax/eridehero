<?php
/**
 * REST API Comparison Views Endpoint.
 *
 * Handles view tracking for product comparisons and provides
 * popular comparison data for hub pages.
 *
 * @package ERH\Api
 */

declare(strict_types=1);

namespace ERH\Api;

use ERH\Database\ComparisonViews;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for comparison view tracking.
 */
class RestComparisonViews extends WP_REST_Controller {

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
    protected $rest_base = 'compare';

    /**
     * Comparison views database instance.
     *
     * @var ComparisonViews
     */
    private ComparisonViews $views_db;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->views_db = new ComparisonViews();
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Track comparison view: POST /erh/v1/compare/track
        register_rest_route($this->namespace, '/' . $this->rest_base . '/track', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'track_view'],
                'permission_callback' => '__return_true', // Public endpoint.
                'args'                => [
                    'product_ids' => [
                        'required'          => true,
                        'type'              => 'array',
                        'items'             => ['type' => 'integer'],
                        'validate_callback' => function ($param) {
                            return is_array($param) && count($param) >= 2;
                        },
                        'sanitize_callback' => function ($param) {
                            return array_map('absint', (array) $param);
                        },
                    ],
                ],
            ],
        ]);

        // Get popular comparisons: GET /erh/v1/compare/popular
        register_rest_route($this->namespace, '/' . $this->rest_base . '/popular', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_popular'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'category' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'limit' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 10,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        // Get related comparisons: GET /erh/v1/compare/related
        register_rest_route($this->namespace, '/' . $this->rest_base . '/related', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_related'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'products' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'category' => [
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'limit' => [
                        'required'          => false,
                        'type'              => 'integer',
                        'default'           => 4,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);

        // Get comparison advantages: GET /erh/v1/compare/advantages
        register_rest_route($this->namespace, '/' . $this->rest_base . '/advantages', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_advantages'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'products' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'description'       => 'Comma-separated product IDs (exactly 2).',
                    ],
                    'geo' => [
                        'required'          => false,
                        'type'              => 'string',
                        'default'           => 'US',
                        'sanitize_callback' => 'sanitize_text_field',
                        'description'       => 'Geo region code (US, GB, EU, CA, AU).',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Track a comparison view.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function track_view(WP_REST_Request $request): WP_REST_Response {
        $product_ids = $request->get_param('product_ids');
        $user_agent  = $request->get_header('user_agent') ?? '';
        $ip_address  = $this->get_client_ip();

        $tracked = $this->views_db->record_view($product_ids, $user_agent, $ip_address);

        // Probabilistic cleanup (1% chance).
        $this->views_db->maybe_cleanup();

        return new WP_REST_Response([
            'success' => true,
            'tracked' => $tracked,
        ]);
    }

    /**
     * Get client IP address from request headers.
     *
     * Checks proxy headers first, then falls back to REMOTE_ADDR.
     *
     * @return string Client IP address.
     */
    private function get_client_ip(): string {
        // Check common proxy headers (Cloudflare, load balancers).
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare.
            'HTTP_X_FORWARDED_FOR',   // Standard proxy.
            'HTTP_X_REAL_IP',         // Nginx.
            'REMOTE_ADDR',            // Direct connection.
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                // X-Forwarded-For can contain multiple IPs, take first.
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP format.
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Get popular comparisons.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_popular(WP_REST_Request $request): WP_REST_Response {
        $category = $request->get_param('category');
        $limit    = $request->get_param('limit');

        $popular = $this->views_db->get_popular($category, $limit);

        // Enrich with additional data.
        $enriched = array_map(function ($item) {
            $product_1_id = (int) $item['product_1_id'];
            $product_2_id = (int) $item['product_2_id'];

            // Check for curated comparison.
            $curated_id = $this->views_db->get_curated_comparison($product_1_id, $product_2_id);

            return [
                'product_1' => [
                    'id'        => $product_1_id,
                    'name'      => $item['product_1_name'],
                    'thumbnail' => get_the_post_thumbnail_url($product_1_id, 'thumbnail'),
                ],
                'product_2' => [
                    'id'        => $product_2_id,
                    'name'      => $item['product_2_name'],
                    'thumbnail' => get_the_post_thumbnail_url($product_2_id, 'thumbnail'),
                ],
                'view_count'     => (int) $item['view_count'],
                'weighted_score' => (float) $item['weighted_score'],
                'curated_id'     => $curated_id,
                'url'            => $this->get_comparison_url($product_1_id, $product_2_id, $curated_id),
            ];
        }, $popular);

        return new WP_REST_Response([
            'success' => true,
            'data'    => $enriched,
        ]);
    }

    /**
     * Get related comparisons for a set of products.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_related(WP_REST_Request $request): WP_REST_Response {
        $products_param = $request->get_param('products');
        $category       = $request->get_param('category');
        $limit          = $request->get_param('limit');

        // Parse product IDs from comma-separated string.
        $product_ids = array_filter(array_map('absint', explode(',', $products_param)));

        if (count($product_ids) < 2) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'At least 2 product IDs required.',
            ], 400);
        }

        // Get comparisons sharing any of these products.
        $related = [];
        foreach ($product_ids as $product_id) {
            $comparisons = $this->views_db->get_comparisons_for_product($product_id, 10);
            foreach ($comparisons as $comp) {
                $other_id = (int) $comp['other_product_id'];

                // Skip if it's one of the original products.
                if (in_array($other_id, $product_ids, true)) {
                    continue;
                }

                // Skip if already in results.
                $key = min($product_id, $other_id) . '-' . max($product_id, $other_id);
                if (isset($related[$key])) {
                    continue;
                }

                // Check category match if filtering.
                if ($category) {
                    $other_type = get_field('product_type', $other_id);
                    $other_category = $this->get_category_from_type($other_type);
                    if ($other_category !== $category) {
                        continue;
                    }
                }

                $related[$key] = [
                    'product_1' => [
                        'id'        => $product_id,
                        'name'      => get_the_title($product_id),
                        'thumbnail' => get_the_post_thumbnail_url($product_id, 'thumbnail'),
                    ],
                    'product_2' => [
                        'id'        => $other_id,
                        'name'      => get_the_title($other_id),
                        'thumbnail' => get_the_post_thumbnail_url($other_id, 'thumbnail'),
                    ],
                    'view_count' => (int) $comp['view_count'],
                    'url'        => $this->get_comparison_url($product_id, $other_id),
                ];
            }
        }

        // Sort by view count and limit.
        usort($related, function ($a, $b) {
            return $b['view_count'] - $a['view_count'];
        });

        $related = array_slice(array_values($related), 0, $limit);

        return new WP_REST_Response([
            'success' => true,
            'data'    => $related,
        ]);
    }

    /**
     * Get spec-based advantages for products.
     *
     * Returns calculated advantages for each product showing why they win
     * specific specs (e.g., "Better suspension", "7% Faster").
     *
     * Uses product-type-specific calculators via AdvantageCalculatorFactory.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response with advantages array.
     */
    public function get_advantages(WP_REST_Request $request): WP_REST_Response {
        $products_param = $request->get_param('products');
        $geo            = $request->get_param('geo');

        // Parse and validate product IDs.
        $product_ids = array_filter(array_map('absint', explode(',', $products_param)));
        $count       = count($product_ids);

        if ($count < 1) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'At least 1 product ID required.',
            ], 400);
        }

        // Validate geo.
        $valid_geos = ['US', 'GB', 'EU', 'CA', 'AU'];
        if (!in_array($geo, $valid_geos, true)) {
            $geo = 'US';
        }

        // Load the compare helpers if not already loaded.
        if (!function_exists('erh_get_compare_products')) {
            $theme_path = get_template_directory() . '/inc/functions/compare-helpers.php';
            if (file_exists($theme_path)) {
                require_once $theme_path;
            } else {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Compare helpers not available.',
                ], 500);
            }
        }

        // Get products data.
        $products      = erh_get_compare_products($product_ids, $geo);
        $product_names = array_map(fn($p) => $p['name'] ?? '', $products);

        // Detect product type from first product.
        $product_type = $products[0]['product_type'] ?? 'escooter';

        // Use the factory to calculate advantages.
        $advantages = \ERH\Comparison\AdvantageCalculatorFactory::calculate($products, $product_type);

        // Determine mode for response.
        if ($count === 1) {
            return new WP_REST_Response([
                'success'    => true,
                'mode'       => 'single',
                'advantages' => $advantages[0] ?? null,
                'products'   => $product_names,
            ]);
        }

        if ($count === 2) {
            return new WP_REST_Response([
                'success'    => true,
                'mode'       => 'head_to_head',
                'advantages' => $advantages,
                'products'   => $product_names,
            ]);
        }

        // Multi-product comparison mode (3+ products).
        return new WP_REST_Response([
            'success'    => true,
            'mode'       => 'multi',
            'advantages' => $advantages,
            'products'   => $product_names,
        ]);
    }

    /**
     * Get comparison URL (curated or dynamic).
     *
     * @param int      $product_1_id First product ID.
     * @param int      $product_2_id Second product ID.
     * @param int|null $curated_id   Curated comparison ID if exists.
     * @return string Comparison URL.
     */
    private function get_comparison_url(int $product_1_id, int $product_2_id, ?int $curated_id = null): string {
        if ($curated_id) {
            return get_permalink($curated_id);
        }

        // Generate dynamic URL.
        if (function_exists('erh_get_compare_url')) {
            return erh_get_compare_url([$product_1_id, $product_2_id]);
        }

        // Fallback to query string format.
        $slug_1 = get_post_field('post_name', $product_1_id);
        $slug_2 = get_post_field('post_name', $product_2_id);

        if ($slug_1 && $slug_2) {
            return home_url("/compare/{$slug_1}-vs-{$slug_2}/");
        }

        return home_url("/compare/?products={$product_1_id},{$product_2_id}");
    }

    /**
     * Get category key from product type.
     *
     * @param string|null $product_type The product type field value.
     * @return string Category key.
     */
    private function get_category_from_type(?string $product_type): string {
        if (!$product_type) {
            return 'escooter';
        }

        $map = [
            'Electric Scooter'    => 'escooter',
            'Electric Bike'       => 'ebike',
            'Electric Skateboard' => 'eskateboard',
            'Electric Unicycle'   => 'euc',
            'Hoverboard'          => 'hoverboard',
        ];

        return $map[$product_type] ?? 'escooter';
    }
}
