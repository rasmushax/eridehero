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
use ERH\CategoryConfig;
use ERH\Comparison\AdvantageCalculatorFactory;
use ERH\Comparison\PriceBracketConfig;
use ERH\Database\ProductCache;
use ERH\Database\ViewTracker;
use ERH\User\RateLimiter;
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
     * Rate limiter instance.
     *
     * @var RateLimiter
     */
    private RateLimiter $rate_limiter;

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
     * Constructor.
     *
     * @param RateLimiter|null $rate_limiter Optional rate limiter instance.
     */
    public function __construct(?RateLimiter $rate_limiter = null) {
        $this->debug = defined('ERH_GEO_DEBUG') && ERH_GEO_DEBUG;
        $this->rate_limiter = $rate_limiter ?? new RateLimiter();
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

        // Get product analysis (advantages/weaknesses): GET /erh/v1/products/{id}/analysis?geo=US
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/analysis', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_product_analysis'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id' => [
                        'description' => 'Product ID',
                        'type'        => 'integer',
                        'required'    => true,
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

        // Record product view: POST /erh/v1/products/{id}/view
        // Used for AJAX-based view tracking to bypass page caching.
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/view', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'record_product_view'],
                'permission_callback' => '__return_true', // Public endpoint
                'args'                => [
                    'id' => [
                        'description'       => 'Product ID',
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
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
        $rate = $this->rate_limiter->check_and_record('api_similar', RateLimiter::get_client_ip());
        if (!$rate['allowed']) {
            return new WP_Error('rate_limit_exceeded', $rate['message'], ['status' => 429]);
        }

        $product_id = (int) $request->get_param('id');
        $limit = (int) $request->get_param('limit');
        $geo = strtoupper($request->get_param('geo') ?? 'US');

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
     * Get product analysis (advantages and weaknesses).
     *
     * Compares a single product against others in its price bracket
     * to identify strengths and weaknesses.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error Response with analysis data.
     */
    public function get_product_analysis(WP_REST_Request $request) {
        $rate = $this->rate_limiter->check_and_record('api_analysis', RateLimiter::get_client_ip());
        if (!$rate['allowed']) {
            return new WP_Error('rate_limit_exceeded', $rate['message'], ['status' => 429]);
        }

        $product_id = (int) $request->get_param('id');
        $geo = strtoupper($request->get_param('geo') ?? 'US');

        $this->log('get_product_analysis called', [
            'product_id' => $product_id,
            'geo'        => $geo,
        ]);

        // TEMPORARILY DISABLED: Check transient cache (2 hours).
        // $cache_key = CacheKeys::productAnalysis($product_id, $geo);
        // $cached = get_transient($cache_key);
        // if ($cached !== false) {
        //     $this->log('Cache HIT', ['cache_key' => $cache_key]);
        //     $response = new WP_REST_Response($cached, 200);
        //     $response->header('X-ERH-Cache', 'HIT');
        //     return $response;
        // }

        $this->log('Cache DISABLED for debugging');

        // Get product from ProductCache.
        $cache = new ProductCache();
        $product = $cache->get($product_id);
        if (!$product) {
            $this->log('Product not found in cache', ['product_id' => $product_id]);
            return new WP_Error(
                'product_not_found',
                __('Product not found.', 'erh-core'),
                ['status' => 404]
            );
        }

        // Get product type.
        $product_type = $product['product_type'] ?? '';
        $this->log('Product type', ['type' => $product_type]);

        // Supported product types for analysis.
        $supported_types = ['Electric Scooter', 'Electric Bike', 'Hoverboard', 'Electric Unicycle', 'Electric Skateboard'];
        if (!in_array($product_type, $supported_types, true)) {
            $this->log('Unsupported product type', ['type' => $product_type]);
            return new WP_Error(
                'unsupported_type',
                __('Analysis is not available for this product type.', 'erh-core'),
                ['status' => 400]
            );
        }

        // Get calculator for this product type.
        $calculator = AdvantageCalculatorFactory::get($product_type);
        if (!$calculator) {
            $this->log('No calculator found for type', ['type' => $product_type]);
            return new WP_Error(
                'calculator_not_found',
                __('Unable to analyze this product type.', 'erh-core'),
                ['status' => 500]
            );
        }

        // Calculate analysis.
        $raw_analysis = $calculator->calculate_single($product, $geo);
        if ($raw_analysis === null) {
            $this->log('Calculator returned null');
            return new WP_Error(
                'analysis_failed',
                __('Unable to generate analysis for this product.', 'erh-core'),
                ['status' => 500]
            );
        }

        // Transform to expected response format.
        $analysis = [
            'product' => [
                'id'           => $product_id,
                'name'         => $product['name'] ?? '',
                'product_type' => $product['product_type'] ?? '',
                'scores'       => $product['specs']['scores'] ?? [],
            ],
            'price_context' => [
                'geo'                 => $geo,
                'currency'            => $this->get_currency_for_geo($geo),
                'current_price'       => $product['price_history'][$geo]['current_price'] ?? null,
                'bracket'             => PriceBracketConfig::to_api_format($raw_analysis['bracket']),
                'products_in_bracket' => $raw_analysis['products_in_set'],
                'comparison_mode'     => $raw_analysis['comparison_mode'],
            ],
            'advantages'     => $raw_analysis['advantages'],
            'weaknesses'     => $raw_analysis['weaknesses'],
            'bracket_scores' => $raw_analysis['bracket_scores'] ?? [],
            'fallback'       => $raw_analysis['fallback'],
        ];

        $this->log('Analysis complete', [
            'advantages'  => count($analysis['advantages'] ?? []),
            'weaknesses'  => count($analysis['weaknesses'] ?? []),
            'mode'        => $analysis['price_context']['comparison_mode'] ?? 'unknown',
        ]);

        // TEMPORARILY DISABLED: Cache for 2 hours.
        // set_transient($cache_key, $analysis, 2 * HOUR_IN_SECONDS);

        $response = new WP_REST_Response($analysis, 200);
        $response->header('X-ERH-Cache', 'MISS');
        return $response;
    }

    /**
     * Record a product view.
     *
     * This endpoint is called via AJAX from product pages to track views.
     * AJAX tracking bypasses page caching (LiteSpeed/Cloudflare) to ensure
     * accurate view counts.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response Response with success status.
     */
    public function record_product_view(WP_REST_Request $request): WP_REST_Response {
        $product_id = (int) $request->get_param('id');

        // Verify product exists and is published.
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'products' || $product->post_status !== 'publish') {
            return new WP_REST_Response([
                'success'  => false,
                'recorded' => false,
                'message'  => 'Product not found',
            ], 404);
        }

        // Get client info for deduplication.
        $ip = RateLimiter::get_client_ip();
        $user_agent = $request->get_header('user_agent') ?? '';

        // Record the view.
        $view_tracker = new ViewTracker();
        $recorded = $view_tracker->record_view($product_id, $ip, $user_agent);

        return new WP_REST_Response([
            'success'  => true,
            'recorded' => $recorded, // false if duplicate or bot
        ], 200);
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

        $product_name = $product['name'] ?? 'Unknown';

        // Only use geo-specific pricing - NO fallbacks to other regions.
        // This prevents showing USD prices to EU users (currency mixing).
        $has_geo_pricing = !empty($geo_pricing) && isset($geo_pricing['current_price']);

        // Log raw pricing data for this product.
        $this->log("transform_product: {$product_name}", [
            'requested_geo'   => $geo,
            'has_geo_pricing' => $has_geo_pricing,
            'price'           => $geo_pricing['current_price'] ?? null,
            'avg_6m'          => $geo_pricing['avg_6m'] ?? null,
            'tracked_url'     => $geo_pricing['tracked_url'] ?? null,
        ]);

        // Extract values ONLY from requested geo (no cross-region fallbacks).
        $current_price = $has_geo_pricing ? ($geo_pricing['current_price'] ?? null) : null;
        $currency = $this->get_currency_for_geo($geo);
        $avg_6m = $has_geo_pricing ? ($geo_pricing['avg_6m'] ?? null) : null;
        $instock = $has_geo_pricing ? ($geo_pricing['instock'] ?? false) : false;
        $tracked_url = $has_geo_pricing ? ($geo_pricing['tracked_url'] ?? null) : null;

        // Calculate price indicator ONLY if we have same-region average.
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
            // Pricing (geo-aware, NO cross-region fallbacks).
            'price'           => $current_price,
            'currency'        => $currency,
            'in_stock'        => $instock,
            'tracked_url'     => $tracked_url,
            'price_indicator' => $price_indicator,
            'avg_6m'          => $avg_6m,
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
        return CategoryConfig::type_to_finder_key($product_type) ?: 'other';
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
                // Category.
                if (!empty($ebike['category'])) {
                    $cat = is_array($ebike['category']) ? implode('/', $ebike['category']) : $ebike['category'];
                    if (!empty($cat)) {
                        $parts[] = $cat;
                    }
                }
                // Motor power + type.
                if (!empty($ebike['motor']['power_nominal'])) {
                    $motor_str = $ebike['motor']['power_nominal'] . 'W';
                    $motor_type = $ebike['motor']['motor_type'] ?? '';
                    if ($motor_type && strtolower($motor_type) !== 'unknown') {
                        $motor_str .= ' ' . strtolower($motor_type);
                    }
                    $parts[] = $motor_str;
                }
                // Torque.
                if (!empty($ebike['motor']['torque'])) {
                    $parts[] = $ebike['motor']['torque'] . 'Nm';
                }
                // Battery.
                if (!empty($ebike['battery']['battery_capacity'])) {
                    $parts[] = $ebike['battery']['battery_capacity'] . 'Wh';
                }
                // Weight.
                if (!empty($ebike['weight_and_capacity']['weight'])) {
                    $parts[] = $ebike['weight_and_capacity']['weight'] . ' lbs';
                }
                // Frame (material + style).
                $frame_parts = [];
                if (!empty($ebike['frame_and_geometry']['frame_material'])) {
                    $mat = is_array($ebike['frame_and_geometry']['frame_material'])
                        ? implode('/', $ebike['frame_and_geometry']['frame_material'])
                        : $ebike['frame_and_geometry']['frame_material'];
                    $frame_parts[] = strtolower($mat);
                }
                if (!empty($ebike['frame_and_geometry']['frame_style'])) {
                    $style = is_array($ebike['frame_and_geometry']['frame_style'])
                        ? implode('/', $ebike['frame_and_geometry']['frame_style'])
                        : $ebike['frame_and_geometry']['frame_style'];
                    $frame_parts[] = strtolower($style);
                }
                if (!empty($frame_parts)) {
                    $parts[] = implode(' ', $frame_parts);
                }
                // Wheels (size + width + type).
                if (!empty($ebike['wheels_and_tires']['wheel_size'])) {
                    $wheel_str = $ebike['wheels_and_tires']['wheel_size'] . '"';
                    if (!empty($ebike['wheels_and_tires']['tire_width'])) {
                        $wheel_str .= '×' . $ebike['wheels_and_tires']['tire_width'] . '"';
                    }
                    if (!empty($ebike['wheels_and_tires']['tire_type']) && strtolower($ebike['wheels_and_tires']['tire_type']) !== 'unknown') {
                        $wheel_str .= ' ' . strtolower($ebike['wheels_and_tires']['tire_type']);
                    }
                    $parts[] = $wheel_str;
                }
                break;

            case 'Hoverboard':
                $hb = $specs['hoverboards'] ?? [];
                if (!empty($specs['manufacturer_top_speed'])) {
                    $parts[] = round((float) $specs['manufacturer_top_speed']) . ' MPH';
                }
                if (!empty($hb['battery']['capacity'])) {
                    $parts[] = round((float) $hb['battery']['capacity']) . ' Wh battery';
                }
                if (!empty($hb['motor']['power_nominal'])) {
                    $parts[] = round((float) $hb['motor']['power_nominal']) . 'W motor';
                }
                if (!empty($hb['dimensions']['weight'])) {
                    $parts[] = round((float) $hb['dimensions']['weight']) . ' lbs';
                }
                if (!empty($hb['dimensions']['max_load'])) {
                    $parts[] = round((float) $hb['dimensions']['max_load']) . ' lbs max load';
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

        return implode(', ', $parts);
    }
}
