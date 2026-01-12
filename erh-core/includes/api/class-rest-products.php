<?php
/**
 * REST API Products Endpoint.
 *
 * Provides product data for frontend components including similar products.
 *
 * @package ERH\Api
 */

declare(strict_types=1);

namespace ERH\Api;

use ERH\CacheKeys;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for products endpoints.
 */
class RestProducts extends WP_REST_Controller {

    /**
     * Debug mode flag.
     *
     * @var bool
     */
    private bool $debug = false;

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
    protected $rest_base = 'products';

    /**
     * Supported regions for geo pricing.
     *
     * @var array<string>
     */
    private const SUPPORTED_REGIONS = ['US', 'GB', 'EU', 'CA', 'AU'];

    /**
     * Category slug to product type mapping.
     *
     * @var array<string, string>
     */
    private array $category_map = [
        'escooter'   => 'Electric Scooter',
        'ebike'      => 'Electric Bike',
        'eskate'     => 'Electric Skateboard',
        'euc'        => 'Electric Unicycle',
        'hoverboard' => 'Hoverboard',
    ];

    /**
     * Constructor.
     */
    public function __construct() {
        // Enable debug via constant or query param.
        $this->debug = defined('ERH_GEO_DEBUG') && ERH_GEO_DEBUG;
    }

    /**
     * Log debug message.
     *
     * @param string $message Log message.
     * @param array  $context Optional context data.
     * @return void
     */
    private function log(string $message, array $context = []): void {
        if (!$this->debug) {
            return;
        }
        $log_entry = '[RestProducts] ' . $message;
        if (!empty($context)) {
            $log_entry .= ' | ' . wp_json_encode($context);
        }
        error_log($log_entry);
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Get similar products: GET /erh/v1/products/{id}/similar?limit=10&geo=US
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/similar', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_similar_products'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id' => [
                        'description' => 'Product ID',
                        'type'        => 'integer',
                        'required'    => true,
                    ],
                    'limit' => [
                        'description' => 'Maximum number of similar products to return',
                        'type'        => 'integer',
                        'default'     => 10,
                        'minimum'     => 1,
                        'maximum'     => 20,
                    ],
                    'geo' => [
                        'description' => 'Region code for pricing (US, GB, EU, CA, AU)',
                        'type'        => 'string',
                        'default'     => 'US',
                        'enum'        => self::SUPPORTED_REGIONS,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get similar products.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error Response with similar products.
     */
    public function get_similar_products(WP_REST_Request $request) {
        $product_id = (int) $request->get_param('id');
        $limit = (int) $request->get_param('limit');
        $geo = strtoupper($request->get_param('geo'));

        $this->log('get_similar_products called', [
            'product_id' => $product_id,
            'limit'      => $limit,
            'geo'        => $geo,
        ]);

        // Validate product exists.
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'products') {
            $this->log('Product not found', ['product_id' => $product_id]);
            return new WP_Error(
                'product_not_found',
                __('Product not found.', 'erh-core'),
                ['status' => 404]
            );
        }

        // Check transient cache (2 hours - aligned with finder JSON rebuild).
        $cache_key = CacheKeys::similarProducts($product_id, $limit, $geo);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $this->log('Cache HIT', ['cache_key' => $cache_key]);
            $response = new WP_REST_Response($cached, 200);
            $response->header('X-ERH-Cache', 'HIT');
            return $response;
        }

        $this->log('Cache MISS, fetching similar products', ['cache_key' => $cache_key]);

        // Get similar products using existing theme function.
        if (!function_exists('erh_get_similar_products')) {
            $this->log('erh_get_similar_products function not available');
            return new WP_Error(
                'function_not_found',
                __('Similar products function not available.', 'erh-core'),
                ['status' => 500]
            );
        }

        $similar = erh_get_similar_products($product_id, $limit, true);
        $this->log('Got raw similar products', ['count' => count($similar)]);

        // Transform for frontend with geo-specific pricing.
        $transformed = array_map(function ($item) use ($geo) {
            return $this->transform_product($item, $geo);
        }, $similar);

        // Log pricing summary.
        $with_price = count(array_filter($transformed, fn($p) => $p['price'] !== null));
        $with_tracked = count(array_filter($transformed, fn($p) => $p['tracked_url'] !== null));
        $this->log('Transformed products', [
            'total'        => count($transformed),
            'with_price'   => $with_price,
            'with_tracked' => $with_tracked,
            'geo'          => $geo,
        ]);

        $response_data = [
            'products'   => $transformed,
            'product_id' => $product_id,
            'geo'        => $geo,
            'count'      => count($transformed),
        ];

        // Cache for 2 hours.
        set_transient($cache_key, $response_data, 2 * HOUR_IN_SECONDS);

        $response = new WP_REST_Response($response_data, 200);
        $response->header('X-ERH-Cache', 'MISS');
        return $response;
    }

    /**
     * Transform a product for frontend consumption.
     *
     * @param array  $product Raw product data from erh_get_similar_products.
     * @param string $geo     Region code for pricing.
     * @return array Transformed product.
     */
    private function transform_product(array $product, string $geo): array {
        $pricing = $product['pricing'] ?? [];
        $geo_pricing = $pricing[$geo] ?? [];
        $us_pricing = $pricing['US'] ?? [];

        $product_name = $product['name'] ?? 'Unknown';

        // Determine which pricing to use (geo-specific or US fallback).
        $using_geo = !empty($geo_pricing) && isset($geo_pricing['current_price']);
        $active_pricing = $using_geo ? $geo_pricing : $us_pricing;
        $active_region = $using_geo ? $geo : 'US';

        // Log raw pricing data for this product.
        $this->log("transform_product: {$product_name}", [
            'requested_geo' => $geo,
            'using_geo'     => $using_geo,
            'active_region' => $active_region,
            'price'         => $active_pricing['current_price'] ?? null,
            'avg_6m'        => $active_pricing['avg_6m'] ?? null,
            'tracked_url'   => $active_pricing['tracked_url'] ?? null,
        ]);

        // Extract values from active pricing (NO cross-region fallbacks for averages).
        $current_price = $active_pricing['current_price'] ?? null;
        $currency = $this->get_currency_for_geo($active_region);
        $avg_6m = $active_pricing['avg_6m'] ?? null;
        $instock = $active_pricing['instock'] ?? false;
        $tracked_url = $active_pricing['tracked_url'] ?? null;

        // Calculate price indicator ONLY if we have same-region average.
        // Never compare geo price to US average (different currencies).
        $price_indicator = null;
        if ($current_price && $avg_6m && $avg_6m > 0) {
            $price_indicator = round((($current_price - $avg_6m) / $avg_6m) * 100);
        }

        // Get category slug from product type.
        $product_type = $this->get_product_type($product['product_id']);
        $category = $this->get_category_slug($product_type);

        // Extract key specs for card display.
        $specs = $product['specs'] ?? [];
        $specs_line = $this->format_specs_line($specs, $product_type);

        return [
            'id'              => (int) $product['product_id'],
            'name'            => $product['name'] ?? '',
            'url'             => $product['permalink'] ?? '',
            'thumbnail'       => $product['image_url'] ?? '',
            'category'        => $category,
            'category_label'  => $product_type,
            'rating'          => $product['rating'] ?? null,
            'popularity'      => $product['popularity_score'] ?? 0,
            'similarity'      => round($product['similarity_score'] ?? 0, 2),
            'specs_line'      => $specs_line,
            // Pricing (geo-aware, no cross-region fallbacks for averages).
            'price'           => $current_price,
            'currency'        => $currency,
            'instock'         => $instock,
            'tracked_url'     => $tracked_url,
            'price_indicator' => $price_indicator,
            'avg_6m'          => $avg_6m,
            // Flag if using fallback region pricing.
            'price_is_fallback' => !$using_geo && !empty($us_pricing),
        ];
    }

    /**
     * Get product type for a product.
     *
     * @param int $product_id Product ID.
     * @return string Product type label.
     */
    private function get_product_type(int $product_id): string {
        $terms = get_the_terms($product_id, 'product_type');
        if ($terms && !is_wp_error($terms)) {
            return $terms[0]->name;
        }
        return '';
    }

    /**
     * Get category slug from product type.
     *
     * @param string $product_type Product type name.
     * @return string Category slug.
     */
    private function get_category_slug(string $product_type): string {
        $reverse_map = array_flip($this->category_map);
        return $reverse_map[$product_type] ?? 'other';
    }

    /**
     * Get currency for a region.
     *
     * @param string $geo Region code.
     * @return string Currency code.
     */
    private function get_currency_for_geo(string $geo): string {
        $currencies = [
            'US' => 'USD',
            'GB' => 'GBP',
            'EU' => 'EUR',
            'CA' => 'CAD',
            'AU' => 'AUD',
        ];
        return $currencies[$geo] ?? 'USD';
    }

    /**
     * Format specs line for card display.
     *
     * @param array  $specs        Product specs.
     * @param string $product_type Product type.
     * @return string Formatted specs line.
     */
    private function format_specs_line(array $specs, string $product_type): string {
        $parts = [];

        // Use existing theme function if available.
        if (function_exists('erh_format_card_specs')) {
            return erh_format_card_specs($specs);
        }

        // Fallback: extract key specs based on product type.
        switch ($product_type) {
            case 'Electric Scooter':
                if (!empty($specs['manufacturer_top_speed'])) {
                    $parts[] = $specs['manufacturer_top_speed'] . ' mph';
                }
                if (!empty($specs['manufacturer_range'])) {
                    $parts[] = $specs['manufacturer_range'] . ' mi';
                }
                if (!empty($specs['weight'])) {
                    $parts[] = $specs['weight'] . ' lbs';
                }
                break;

            case 'Electric Bike':
                $ebike = $specs['e-bikes'] ?? [];
                if (!empty($ebike['motor']['power_nominal'])) {
                    $parts[] = $ebike['motor']['power_nominal'] . 'W';
                }
                if (!empty($ebike['battery']['range_claimed'])) {
                    $parts[] = $ebike['battery']['range_claimed'] . ' mi';
                }
                break;

            default:
                // Generic fallback.
                if (!empty($specs['manufacturer_top_speed'])) {
                    $parts[] = $specs['manufacturer_top_speed'] . ' mph';
                }
                if (!empty($specs['manufacturer_range'])) {
                    $parts[] = $specs['manufacturer_range'] . ' mi';
                }
        }

        return implode(' · ', array_slice($parts, 0, 3));
    }
}
