<?php
/**
 * REST API Deals Endpoint.
 *
 * Provides deal data for frontend components.
 *
 * @package ERH\Api
 */

declare(strict_types=1);

namespace ERH\Api;

use ERH\Pricing\DealsFinder;
use ERH\CacheKeys;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for deals endpoints.
 */
class RestDeals extends WP_REST_Controller {

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
    protected $rest_base = 'deals';

    /**
     * Deals finder instance.
     *
     * @var DealsFinder
     */
    private DealsFinder $deals_finder;

    /**
     * Category slug to product type mapping.
     *
     * @var array<string, string>
     */
    private array $category_map = [
        'all'        => '',
        'escooter'   => 'Electric Scooter',
        'ebike'      => 'Electric Bike',
        'eskate'     => 'Electric Skateboard',
        'euc'        => 'Electric Unicycle',
        'hoverboard' => 'Hoverboard',
    ];

    /**
     * Constructor.
     *
     * @param DealsFinder|null $deals_finder Optional DealsFinder instance.
     */
    public function __construct(?DealsFinder $deals_finder = null) {
        $this->deals_finder = $deals_finder ?? new DealsFinder();
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Get deals: GET /erh/v1/deals?category=escooter&limit=10&geo=US&period=6m
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_deals'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'category' => [
                        'description' => 'Product category (escooter, ebike, eskate, euc, hoverboard, or all)',
                        'type'        => 'string',
                        'default'     => 'all',
                        'enum'        => array_keys($this->category_map),
                    ],
                    'limit' => [
                        'description' => 'Maximum number of deals to return',
                        'type'        => 'integer',
                        'default'     => 12,
                        'minimum'     => 1,
                        'maximum'     => 50,
                    ],
                    'threshold' => [
                        'description' => 'Minimum discount percentage (e.g., -5 for 5% below average)',
                        'type'        => 'number',
                        'default'     => -5.0,
                    ],
                    'geo' => [
                        'description' => 'Geo code for pricing (e.g., US, GB, DE)',
                        'type'        => 'string',
                        'default'     => DealsFinder::DEFAULT_GEO,
                    ],
                    'period' => [
                        'description' => 'Average period to compare against (3m, 6m, 12m)',
                        'type'        => 'string',
                        'default'     => DealsFinder::DEFAULT_PERIOD,
                        'enum'        => DealsFinder::PERIODS,
                    ],
                ],
            ],
        ]);

        // Get deal counts: GET /erh/v1/deals/counts?geo=US&period=6m
        register_rest_route($this->namespace, '/' . $this->rest_base . '/counts', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_deal_counts'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'threshold' => [
                        'description' => 'Minimum discount percentage',
                        'type'        => 'number',
                        'default'     => -5.0,
                    ],
                    'geo' => [
                        'description' => 'Geo code for pricing',
                        'type'        => 'string',
                        'default'     => DealsFinder::DEFAULT_GEO,
                    ],
                    'period' => [
                        'description' => 'Average period (3m, 6m, 12m)',
                        'type'        => 'string',
                        'default'     => DealsFinder::DEFAULT_PERIOD,
                        'enum'        => DealsFinder::PERIODS,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get deals.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response Response with deals.
     */
    public function get_deals(WP_REST_Request $request): WP_REST_Response {
        $category = $request->get_param('category');
        $limit = (int) $request->get_param('limit');
        $threshold = (float) $request->get_param('threshold');
        $geo = strtoupper($request->get_param('geo'));
        $period = $request->get_param('period');

        // Check transient cache (1 hour - deals only change when cron runs every 2 hours).
        // Use integer threshold (multiply by 10) for clean cache key, matching DealsFinder pattern.
        $threshold_int = (int) ($threshold * 10);
        $cache_key = CacheKeys::deals($category, $limit, $geo, $period, $threshold_int);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            $response = new WP_REST_Response($cached, 200);
            $response->header('X-ERH-Cache', 'HIT');
            return $response;
        }

        $deals = [];

        if ($category === 'all') {
            // Get deals from all categories.
            $all_deals = $this->deals_finder->get_all_deals($threshold, $limit, $geo, $period);

            // Flatten and mix all deals, then sort by discount.
            foreach ($all_deals as $type => $type_deals) {
                foreach ($type_deals as $deal) {
                    $deal['category_slug'] = $this->get_category_slug($type);
                    $deals[] = $deal;
                }
            }

            // Sort by best discount.
            usort($deals, function ($a, $b) {
                return $a['deal_analysis']['discount_percent'] <=> $b['deal_analysis']['discount_percent'];
            });

            // Limit total.
            $deals = array_slice($deals, 0, $limit);
        } else {
            // Get deals for specific category.
            $product_type = $this->category_map[$category] ?? '';

            if ($product_type) {
                $deals = $this->deals_finder->get_deals($product_type, $threshold, $limit, $geo, $period);
                foreach ($deals as &$deal) {
                    $deal['category_slug'] = $category;
                }
            }
        }

        // Transform deals for frontend.
        $transformed = array_map([$this, 'transform_deal'], $deals);

        // Include counts in response to avoid separate API call.
        $counts = $this->deals_finder->get_deal_counts($threshold, $geo, $period);
        $transformed_counts = [];
        foreach ($counts as $type => $count) {
            $slug = $this->get_category_slug($type);
            $transformed_counts[$slug] = $count;
        }
        $transformed_counts['all'] = array_sum($counts);

        $response_data = [
            'deals'    => $transformed,
            'category' => $category,
            'geo'      => $geo,
            'period'   => $period,
            'count'    => count($transformed),
            'counts'   => $transformed_counts,
        ];

        // Cache for 1 hour (deals only change when cron runs every 2 hours).
        set_transient($cache_key, $response_data, HOUR_IN_SECONDS);

        $response = new WP_REST_Response($response_data, 200);
        $response->header('X-ERH-Cache', 'MISS');
        return $response;
    }

    /**
     * Get deal counts per category.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response Response with counts.
     */
    public function get_deal_counts(WP_REST_Request $request): WP_REST_Response {
        $threshold = (float) $request->get_param('threshold');
        $geo = $request->get_param('geo');
        $period = $request->get_param('period');

        $counts = $this->deals_finder->get_deal_counts($threshold, $geo, $period);

        // Transform to use slugs.
        $transformed = [];
        foreach ($counts as $type => $count) {
            $slug = $this->get_category_slug($type);
            $transformed[$slug] = $count;
        }

        // Add total.
        $transformed['all'] = array_sum($counts);

        return new WP_REST_Response([
            'counts' => $transformed,
            'geo'    => $geo,
            'period' => $period,
        ], 200);
    }

    /**
     * Transform a deal for frontend consumption.
     *
     * @param array $deal Raw deal data.
     * @return array Transformed deal.
     */
    private function transform_deal(array $deal): array {
        $analysis = $deal['deal_analysis'] ?? [];

        // Get thumbnail.
        $thumbnail = '';
        if (!empty($deal['image_url'])) {
            $thumbnail = $deal['image_url'];
        } elseif (!empty($deal['product_id'])) {
            $thumb_id = get_post_thumbnail_id((int) $deal['product_id']);
            if ($thumb_id) {
                $thumb_data = wp_get_attachment_image_src($thumb_id, 'medium');
                $thumbnail = $thumb_data[0] ?? '';
            }
        }

        return [
            'id'               => (int) ($deal['product_id'] ?? $deal['id'] ?? 0),
            'name'             => $deal['name'] ?? '',
            'url'              => $deal['permalink'] ?? '',
            'thumbnail'        => $thumbnail,
            'category'         => $deal['category_slug'] ?? '',
            'category_label'   => $deal['product_type'] ?? '',
            'geo'              => $deal['geo'] ?? DealsFinder::DEFAULT_GEO,
            // Pricing (geo-specific from cache).
            'current_price'    => $analysis['current_price'] ?? 0,
            'currency'         => $analysis['currency'] ?? 'USD',
            'retailer'         => $analysis['retailer'] ?? null,
            'tracked_url'      => $analysis['tracked_url'] ?? null,
            'instock'          => $analysis['instock'] ?? false,
            // Deal analysis.
            'discount_percent' => abs($analysis['discount_percent'] ?? 0),
            'savings_amount'   => $analysis['savings_amount'] ?? 0,
            'avg_period'       => $analysis['avg_period'] ?? '6m',
            'avg_price'        => $analysis['avg_price'] ?? 0,
            'avg_price_3m'     => $analysis['avg_price_3m'] ?? null,
            'avg_price_6m'     => $analysis['avg_price_6m'] ?? null,
            'avg_price_12m'    => $analysis['avg_price_12m'] ?? null,
            'lowest_price'     => $analysis['lowest_price'] ?? null,
            'highest_price'    => $analysis['highest_price'] ?? null,
        ];
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
}
