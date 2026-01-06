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

        // Validate product exists.
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'products') {
            return new WP_Error(
                'product_not_found',
                __('Product not found.', 'erh-core'),
                ['status' => 404]
            );
        }

        // Check transient cache (2 hours - aligned with finder JSON rebuild).
        $cache_key = "erh_similar_{$product_id}_{$limit}_{$geo}";
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $response = new WP_REST_Response($cached, 200);
            $response->header('X-ERH-Cache', 'HIT');
            return $response;
        }

        // Get similar products using existing theme function.
        if (!function_exists('erh_get_similar_products')) {
            return new WP_Error(
                'function_not_found',
                __('Similar products function not available.', 'erh-core'),
                ['status' => 500]
            );
        }

        $similar = erh_get_similar_products($product_id, $limit, true);

        // Transform for frontend with geo-specific pricing.
        $transformed = array_map(function ($item) use ($geo) {
            return $this->transform_product($item, $geo);
        }, $similar);

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

        // Use geo pricing if available, fallback to US.
        $current_price = $geo_pricing['current_price'] ?? $us_pricing['current_price'] ?? null;
        $currency = $this->get_currency_for_geo($geo_pricing ? $geo : 'US');
        $avg_6m = $geo_pricing['avg_6m'] ?? $us_pricing['avg_6m'] ?? null;
        $instock = $geo_pricing['instock'] ?? $us_pricing['instock'] ?? false;
        $bestlink = $geo_pricing['bestlink'] ?? $us_pricing['bestlink'] ?? null;

        // Calculate price indicator (% vs 6-month average).
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
            // Pricing (geo-aware with fallback).
            'price'           => $current_price,
            'currency'        => $currency,
            'instock'         => $instock,
            'bestlink'        => $bestlink,
            'price_indicator' => $price_indicator,
            'avg_6m'          => $avg_6m,
            // Flag if using fallback pricing.
            'price_is_fallback' => empty($geo_pricing) && !empty($us_pricing),
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
