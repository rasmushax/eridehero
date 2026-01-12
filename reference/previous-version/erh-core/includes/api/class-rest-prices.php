<?php
/**
 * REST API Prices Endpoint.
 *
 * Provides geo-aware price data for frontend components.
 *
 * @package ERH\Api
 */

declare(strict_types=1);

namespace ERH\Api;

use ERH\Pricing\PriceFetcher;
use ERH\Pricing\ExchangeRateService;
use ERH\Database\PriceHistory;
use ERH\CacheKeys;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for price endpoints.
 */
class RestPrices extends WP_REST_Controller {

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
    protected $rest_base = 'prices';

    /**
     * Price fetcher instance.
     *
     * @var PriceFetcher
     */
    private PriceFetcher $price_fetcher;

    /**
     * Exchange rate service instance.
     *
     * @var ExchangeRateService
     */
    private ExchangeRateService $exchange_service;

    /**
     * Price history database instance.
     *
     * @var PriceHistory
     */
    private PriceHistory $price_history;

    /**
     * Constructor.
     *
     * @param PriceFetcher|null        $price_fetcher    Optional PriceFetcher instance.
     * @param ExchangeRateService|null $exchange_service Optional ExchangeRateService instance.
     * @param PriceHistory|null        $price_history    Optional PriceHistory instance.
     */
    public function __construct(
        ?PriceFetcher $price_fetcher = null,
        ?ExchangeRateService $exchange_service = null,
        ?PriceHistory $price_history = null
    ) {
        $this->price_fetcher = $price_fetcher ?? new PriceFetcher();
        $this->exchange_service = $exchange_service ?? new ExchangeRateService();
        $this->price_history = $price_history ?? new PriceHistory();
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Batch prices endpoint: GET /erh/v1/prices?ids=1,2,3&geo=US
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_prices'],
                'permission_callback' => '__return_true',
                'args'                => $this->get_collection_params(),
            ],
        ]);

        // Single product prices: GET /erh/v1/prices/123?geo=US
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_product_prices'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id'  => [
                        'description' => 'Product ID',
                        'type'        => 'integer',
                        'required'    => true,
                    ],
                    'geo' => [
                        'description' => 'Geo target (e.g., US, GB, DE)',
                        'type'        => 'string',
                        'default'     => null,
                    ],
                    'convert_to' => [
                        'description' => 'Convert prices to this currency',
                        'type'        => 'string',
                        'default'     => null,
                    ],
                ],
            ],
        ]);

        // Best prices for multiple products: GET /erh/v1/prices/best?ids=1,2,3&geo=US
        register_rest_route($this->namespace, '/' . $this->rest_base . '/best', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_best_prices'],
                'permission_callback' => '__return_true',
                'args'                => $this->get_collection_params(),
            ],
        ]);

        // Price history for a product: GET /erh/v1/prices/123/history?geo=US
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/history', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_price_history'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id'  => [
                        'description' => 'Product ID',
                        'type'        => 'integer',
                        'required'    => true,
                    ],
                    'geo' => [
                        'description' => 'Geo target (e.g., US, GB, EU)',
                        'type'        => 'string',
                        'default'     => 'US',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get collection parameters for batch requests.
     *
     * @return array Collection parameters.
     */
    public function get_collection_params(): array {
        return [
            'ids' => [
                'description'       => 'Comma-separated list of product IDs',
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'geo' => [
                'description' => 'Geo target (e.g., US, GB, DE)',
                'type'        => 'string',
                'default'     => null,
            ],
            'convert_to' => [
                'description' => 'Convert all prices to this currency',
                'type'        => 'string',
                'default'     => null,
            ],
        ];
    }

    /**
     * Get prices for multiple products.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function get_prices(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $ids_string = $request->get_param('ids');
        $geo = $request->get_param('geo');
        $convert_to = $request->get_param('convert_to');

        // Parse product IDs.
        $product_ids = array_map('intval', array_filter(explode(',', $ids_string)));

        if (empty($product_ids)) {
            return new WP_Error(
                'invalid_ids',
                'No valid product IDs provided',
                ['status' => 400]
            );
        }

        // Limit to prevent abuse.
        if (count($product_ids) > 50) {
            return new WP_Error(
                'too_many_ids',
                'Maximum 50 products per request',
                ['status' => 400]
            );
        }

        // Fetch prices.
        $prices = $this->price_fetcher->get_prices_bulk($product_ids, $geo);

        // Convert currencies if requested.
        if ($convert_to) {
            $prices = $this->convert_prices($prices, $convert_to);
        }

        return new WP_REST_Response([
            'prices' => $prices,
            'geo'    => $geo,
            'count'  => count($prices),
        ], 200);
    }

    /**
     * Get all prices for a single product (retailers + price history).
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function get_product_prices(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $product_id = (int) $request->get_param('id');
        $geo = $request->get_param('geo');
        $convert_to = $request->get_param('convert_to');

        if ($product_id <= 0) {
            return new WP_Error(
                'invalid_id',
                'Invalid product ID',
                ['status' => 400]
            );
        }

        // Verify product exists.
        if (get_post_type($product_id) !== 'products') {
            return new WP_Error(
                'not_found',
                'Product not found',
                ['status' => 404]
            );
        }

        // Normalize geo for cache key.
        $geo_normalized = $geo ? strtoupper($geo) : 'US';

        // Check transient cache (6 hours per product+geo, invalidated by HFT on price update).
        $cache_key = CacheKeys::priceIntel($product_id, $geo_normalized);
        $cached = get_transient($cache_key);

        if ($cached !== false && !$convert_to) {
            return new WP_REST_Response($cached, 200);
        }

        // Fetch retailer prices.
        $prices = $this->price_fetcher->get_prices($product_id, $geo);

        // Convert currencies if requested.
        if ($convert_to && !empty($prices)) {
            $prices = $this->convert_price_array($prices, $convert_to);
        }

        // Get best price.
        $best = !empty($prices) ? $prices[0] : null;

        // Get currency for geo.
        $currency = $this->get_currency_for_geo($geo_normalized);
        $currency_symbol = $this->exchange_service->get_symbol($currency);

        // Get price history.
        $history = $this->price_history->get_history($product_id, 0, $geo_normalized, $currency, 'ASC');

        // If no history for requested geo, try US fallback.
        $history_used_fallback = false;
        $history_geo = $geo_normalized;
        $history_currency = $currency;
        if (empty($history) && $geo_normalized !== 'US') {
            $history = $this->price_history->get_history($product_id, 0, 'US', 'USD', 'ASC');
            if (!empty($history)) {
                $history_used_fallback = true;
                $history_geo = 'US';
                $history_currency = 'USD';
            }
        }

        // Format history for chart.
        $chart_data = [];
        foreach ($history as $record) {
            $chart_data[] = [
                'x'      => $record['date'],
                'y'      => $record['price'],
                'domain' => $record['domain'],
            ];
        }

        // Get statistics (180 days = 6 months - matches deal detection).
        $stats = $this->price_history->get_statistics($product_id, 180, $history_geo, $history_currency);

        // Get available geos.
        $available_geos = $this->price_history->get_available_geos($product_id);

        // Get product image.
        $product_image_id  = get_post_thumbnail_id($product_id);
        $product_image_url = $product_image_id ? wp_get_attachment_image_url($product_image_id, 'medium') : '';

        $response_data = [
            'product_id'    => $product_id,
            'product_image' => $product_image_url,
            'geo'           => $geo_normalized,
            'currency'      => $currency,
            'currency_symbol' => $currency_symbol,
            // Retailers.
            'best'       => $best,
            'offers'     => $prices,
            'count'      => count($prices),
            // Price history.
            'history' => [
                'geo'             => $history_geo,
                'currency'        => $history_currency,
                'currency_symbol' => $this->exchange_service->get_symbol($history_currency),
                'used_fallback'   => $history_used_fallback,
                'available_geos'  => $available_geos,
                'data_points'     => count($chart_data),
                'data'            => $chart_data,
                'statistics'      => $stats ? [
                    'average'      => $stats['average_price'],
                    'lowest'       => $stats['lowest_price']['amount'],
                    'lowest_date'  => $stats['lowest_price']['date'],
                    'lowest_store' => $stats['lowest_price']['domain'],
                    'highest'      => $stats['highest_price']['amount'],
                    'highest_date' => $stats['highest_price']['date'],
                    'start_date'   => $stats['tracking_start_date'],
                ] : null,
            ],
        ];

        // Cache for 6 hours (skip if currency conversion was requested).
        // Cache is invalidated by HFT via hft_price_updated action.
        if (!$convert_to) {
            set_transient($cache_key, $response_data, 6 * HOUR_IN_SECONDS);
        }

        return new WP_REST_Response($response_data, 200);
    }

    /**
     * Get best prices for multiple products.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function get_best_prices(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $ids_string = $request->get_param('ids');
        $geo = $request->get_param('geo');
        $convert_to = $request->get_param('convert_to');

        // Parse product IDs.
        $product_ids = array_map('intval', array_filter(explode(',', $ids_string)));

        if (empty($product_ids)) {
            return new WP_Error(
                'invalid_ids',
                'No valid product IDs provided',
                ['status' => 400]
            );
        }

        // Limit to prevent abuse.
        if (count($product_ids) > 100) {
            return new WP_Error(
                'too_many_ids',
                'Maximum 100 products per request',
                ['status' => 400]
            );
        }

        // Fetch all prices.
        $all_prices = $this->price_fetcher->get_prices_bulk($product_ids, $geo);

        // Extract best price per product.
        $best_prices = [];
        foreach ($product_ids as $pid) {
            if (isset($all_prices[$pid]) && !empty($all_prices[$pid])) {
                $best_prices[$pid] = $all_prices[$pid][0]; // First is best (sorted).
            } else {
                $best_prices[$pid] = null;
            }
        }

        // Convert currencies if requested.
        if ($convert_to) {
            foreach ($best_prices as $pid => $price) {
                if ($price !== null) {
                    $best_prices[$pid] = $this->convert_single_price($price, $convert_to);
                }
            }
        }

        return new WP_REST_Response([
            'prices' => $best_prices,
            'geo'    => $geo,
            'count'  => count(array_filter($best_prices)),
        ], 200);
    }

    /**
     * Convert prices in bulk result to target currency.
     *
     * @param array  $prices_by_product Prices indexed by product ID.
     * @param string $target_currency   Target currency code.
     * @return array Converted prices.
     */
    private function convert_prices(array $prices_by_product, string $target_currency): array {
        foreach ($prices_by_product as $pid => $prices) {
            $prices_by_product[$pid] = $this->convert_price_array($prices, $target_currency);
        }
        return $prices_by_product;
    }

    /**
     * Convert an array of prices to target currency.
     *
     * @param array  $prices          Array of price data.
     * @param string $target_currency Target currency code.
     * @return array Converted prices.
     */
    private function convert_price_array(array $prices, string $target_currency): array {
        return array_map(function ($price) use ($target_currency) {
            return $this->convert_single_price($price, $target_currency);
        }, $prices);
    }

    /**
     * Convert a single price to target currency.
     *
     * @param array  $price           Price data.
     * @param string $target_currency Target currency code.
     * @return array Converted price data.
     */
    private function convert_single_price(array $price, string $target_currency): array {
        $original_currency = $price['currency'] ?? 'USD';

        if (strtoupper($original_currency) === strtoupper($target_currency)) {
            $price['converted'] = false;
            return $price;
        }

        $converted = $this->exchange_service->convert(
            (float) $price['price'],
            $original_currency,
            $target_currency
        );

        if ($converted !== null) {
            $price['original_price'] = $price['price'];
            $price['original_currency'] = $original_currency;
            $price['original_symbol'] = $price['currency_symbol'];
            $price['price'] = round($converted, 2);
            $price['currency'] = strtoupper($target_currency);
            $price['currency_symbol'] = $this->exchange_service->get_symbol($target_currency);
            $price['converted'] = true;
        } else {
            $price['converted'] = false;
        }

        return $price;
    }

    /**
     * Get price history for a product.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function get_price_history(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $product_id = (int) $request->get_param('id');
        $geo = strtoupper($request->get_param('geo') ?? 'US');

        if ($product_id <= 0) {
            return new WP_Error(
                'invalid_id',
                'Invalid product ID',
                ['status' => 400]
            );
        }

        // Verify product exists.
        if (get_post_type($product_id) !== 'products') {
            return new WP_Error(
                'not_found',
                'Product not found',
                ['status' => 404]
            );
        }

        // Check transient cache (6 hours per product+geo, invalidated by HFT on price update).
        $cache_key = CacheKeys::priceHistory($product_id, $geo);
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return new WP_REST_Response($cached, 200);
        }

        // Map geo to expected currency for filtering.
        $currency = $this->get_currency_for_geo($geo);

        // Get all price history from our table (days=0 means all).
        $history = $this->price_history->get_history($product_id, 0, $geo, $currency, 'ASC');

        // If no data for requested geo, try US fallback.
        $used_fallback = false;
        if (empty($history) && $geo !== 'US') {
            $history = $this->price_history->get_history($product_id, 0, 'US', 'USD', 'ASC');
            if (!empty($history)) {
                $used_fallback = true;
                $geo = 'US';
                $currency = 'USD';
            }
        }

        // Get statistics (180 days = 6 months - matches deal detection).
        $stats = $this->price_history->get_statistics($product_id, 180, $geo, $currency);

        // Get available geos for this product.
        $available_geos = $this->price_history->get_available_geos($product_id);

        // Format for chart (matching what price-intel.js expects).
        $chart_data = [];
        foreach ($history as $record) {
            $chart_data[] = [
                'x'      => $record['date'],
                'y'      => $record['price'],
                'domain' => $record['domain'],
            ];
        }

        // Get currency symbol.
        $currency_symbol = $this->exchange_service->get_symbol($currency);

        $response_data = [
            'product_id'      => $product_id,
            'geo'             => $geo,
            'currency'        => $currency,
            'currency_symbol' => $currency_symbol,
            'used_fallback'   => $used_fallback,
            'available_geos'  => $available_geos,
            'data_points'     => count($chart_data),
            'history'         => $chart_data,
            'statistics'      => $stats ? [
                'average'      => $stats['average_price'],
                'lowest'       => $stats['lowest_price']['amount'],
                'lowest_date'  => $stats['lowest_price']['date'],
                'lowest_store' => $stats['lowest_price']['domain'],
                'highest'      => $stats['highest_price']['amount'],
                'highest_date' => $stats['highest_price']['date'],
                'start_date'   => $stats['tracking_start_date'],
            ] : null,
        ];

        // Cache for 6 hours (invalidated by HFT via hft_price_updated action).
        set_transient($cache_key, $response_data, 6 * HOUR_IN_SECONDS);

        return new WP_REST_Response($response_data, 200);
    }

    /**
     * Get expected currency for a geo region.
     *
     * @param string $geo The geo code.
     * @return string The expected currency.
     */
    private function get_currency_for_geo(string $geo): string {
        $geo_currencies = [
            'US' => 'USD',
            'GB' => 'GBP',
            'EU' => 'EUR',
            'CA' => 'CAD',
            'AU' => 'AUD',
            // European countries map to EUR.
            'DE' => 'EUR',
            'FR' => 'EUR',
            'IT' => 'EUR',
            'ES' => 'EUR',
            'NL' => 'EUR',
        ];

        return $geo_currencies[strtoupper($geo)] ?? 'USD';
    }
}
