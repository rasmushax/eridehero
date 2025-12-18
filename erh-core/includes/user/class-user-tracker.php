<?php
/**
 * User Tracker - Handles price tracker CRUD operations.
 *
 * @package ERH\User
 */

declare(strict_types=1);

namespace ERH\User;

use ERH\Pricing\PriceFetcher;

/**
 * Manages user price trackers via REST API.
 */
class UserTracker {

    /**
     * WordPress database instance.
     *
     * @var \wpdb
     */
    private \wpdb $wpdb;

    /**
     * Price trackers table name.
     *
     * @var string
     */
    private string $table_name;

    /**
     * Rate limiter instance.
     *
     * @var RateLimiter
     */
    private RateLimiter $rate_limiter;

    /**
     * User repository instance.
     *
     * @var UserRepository
     */
    private UserRepository $user_repo;

    /**
     * Price fetcher instance.
     *
     * @var PriceFetcher|null
     */
    private ?PriceFetcher $price_fetcher = null;

    /**
     * REST API namespace.
     */
    public const REST_NAMESPACE = 'erh/v1';

    /**
     * Constructor.
     *
     * @param RateLimiter|null    $rate_limiter Optional rate limiter instance.
     * @param UserRepository|null $user_repo    Optional user repository instance.
     */
    public function __construct(?RateLimiter $rate_limiter = null, ?UserRepository $user_repo = null) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'price_trackers';
        $this->rate_limiter = $rate_limiter ?? new RateLimiter();
        $this->user_repo = $user_repo ?? new UserRepository();
    }

    /**
     * Register hooks and REST routes.
     *
     * @return void
     */
    public function register(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Get all user trackers.
        register_rest_route(self::REST_NAMESPACE, '/user/trackers', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_user_trackers'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // Create tracker.
        register_rest_route(self::REST_NAMESPACE, '/user/trackers', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_tracker'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'product_id'   => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'tracker_type' => [
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => ['target_price', 'price_drop'],
                ],
                'target_price' => [
                    'type'              => 'number',
                    'sanitize_callback' => fn($value) => (float) $value,
                ],
                'price_drop'   => [
                    'type'              => 'number',
                    'sanitize_callback' => fn($value) => (float) $value,
                ],
                'geo' => [
                    'type'              => 'string',
                    'default'           => 'US',
                    'enum'              => ['US', 'GB', 'EU', 'CA', 'AU'],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'currency' => [
                    'type'              => 'string',
                    'default'           => 'USD',
                    'enum'              => ['USD', 'GBP', 'EUR', 'CAD', 'AUD'],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'enable_emails' => [
                    'type'    => 'boolean',
                    'default' => false,
                ],
            ],
        ]);

        // Get tracker for specific product.
        register_rest_route(self::REST_NAMESPACE, '/products/(?P<product_id>\d+)/tracker', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_product_tracker'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Update tracker.
        register_rest_route(self::REST_NAMESPACE, '/user/trackers/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'update_tracker'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'id'           => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'tracker_type' => [
                    'type' => 'string',
                    'enum' => ['target_price', 'price_drop'],
                ],
                'target_price' => [
                    'type'              => 'number',
                    'sanitize_callback' => fn($value) => (float) $value,
                ],
                'price_drop'   => [
                    'type'              => 'number',
                    'sanitize_callback' => fn($value) => (float) $value,
                ],
            ],
        ]);

        // Delete tracker.
        register_rest_route(self::REST_NAMESPACE, '/user/trackers/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete_tracker'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Delete tracker by product ID.
        register_rest_route(self::REST_NAMESPACE, '/products/(?P<product_id>\d+)/tracker', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete_product_tracker'],
            'permission_callback' => 'is_user_logged_in',
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Check if product has price data.
        register_rest_route(self::REST_NAMESPACE, '/products/(?P<product_id>\d+)/price-data', [
            'methods'             => 'GET',
            'callback'            => [$this, 'check_price_data'],
            'permission_callback' => '__return_true',
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
                'geo' => [
                    'type'              => 'string',
                    'default'           => 'US',
                    'enum'              => ['US', 'GB', 'EU', 'CA', 'AU'],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Get all trackers for the current user.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response.
     */
    public function get_user_trackers(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = get_current_user_id();

        $trackers = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT t.*, p.post_title as product_name
             FROM {$this->table_name} t
             LEFT JOIN {$this->wpdb->posts} p ON t.product_id = p.ID
             WHERE t.user_id = %d
             ORDER BY t.created_at DESC",
            $user_id
        ), ARRAY_A);

        // Enrich with current prices and product data.
        $enriched_trackers = array_map([$this, 'enrich_tracker'], $trackers);

        // Get email alerts status.
        $email_alerts_enabled = $this->user_repo->get_preferences($user_id)['price_tracker_emails'];

        return new \WP_REST_Response([
            'success'              => true,
            'trackers'             => $enriched_trackers,
            'email_alerts_enabled' => $email_alerts_enabled,
        ], 200);
    }

    /**
     * Get tracker for a specific product.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response.
     */
    public function get_product_tracker(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = get_current_user_id();
        $product_id = (int) $request->get_param('product_id');

        $tracker = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d AND product_id = %d",
            $user_id,
            $product_id
        ), ARRAY_A);

        $email_alerts_enabled = $this->user_repo->get_preferences($user_id)['price_tracker_emails'];

        if (!$tracker) {
            return new \WP_REST_Response([
                'success'              => true,
                'has_tracker'          => false,
                'tracker'              => null,
                'email_alerts_enabled' => $email_alerts_enabled,
            ], 200);
        }

        return new \WP_REST_Response([
            'success'              => true,
            'has_tracker'          => true,
            'tracker'              => $this->enrich_tracker($tracker),
            'email_alerts_enabled' => $email_alerts_enabled,
        ], 200);
    }

    /**
     * Create a new tracker.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function create_tracker(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $ip = RateLimiter::get_client_ip();

        // Check rate limit.
        $rate_check = $this->rate_limiter->check_and_record('tracker_create', $ip);
        if (!$rate_check['allowed']) {
            return new \WP_Error(
                'rate_limited',
                $rate_check['message'],
                ['status' => 429]
            );
        }

        $product_id = (int) $request->get_param('product_id');
        $tracker_type = $request->get_param('tracker_type');
        $target_price = $request->get_param('target_price');
        $price_drop = $request->get_param('price_drop');
        $enable_emails = (bool) $request->get_param('enable_emails');
        $geo = $request->get_param('geo') ?: 'US';
        $currency = $request->get_param('currency') ?: 'USD';

        // Verify product exists.
        $product = get_post($product_id);
        if (!$product || $product->post_type !== 'products') {
            return new \WP_Error(
                'invalid_product',
                'Product not found.',
                ['status' => 404]
            );
        }

        // Get current price for the user's geo.
        $price_fetcher = $this->get_price_fetcher();
        $best_price = $price_fetcher->get_best_price($product_id, $geo);

        if (!$best_price) {
            return new \WP_Error(
                'no_price_data',
                'This product has no price data available.',
                ['status' => 400]
            );
        }

        $current_price = $best_price['price'];

        // Validate target/drop values.
        if ($tracker_type === 'target_price') {
            if (!$target_price || $target_price >= $current_price) {
                return new \WP_Error(
                    'invalid_target',
                    'Target price must be lower than the current price.',
                    ['status' => 400]
                );
            }
            $price_drop = null;
        } else {
            if (!$price_drop || $price_drop >= $current_price) {
                return new \WP_Error(
                    'invalid_drop',
                    'Price drop value must be lower than the current price.',
                    ['status' => 400]
                );
            }
            $target_price = null;
        }

        // Check if tracker already exists.
        $existing = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE user_id = %d AND product_id = %d",
            $user_id,
            $product_id
        ));

        if ($existing) {
            // Update existing tracker.
            $result = $this->wpdb->update(
                $this->table_name,
                [
                    'current_price' => $current_price,
                    'target_price'  => $target_price,
                    'price_drop'    => $price_drop,
                    'updated_at'    => current_time('mysql', true),
                ],
                ['id' => $existing]
            );

            $tracker_id = (int) $existing;
        } else {
            // Create new tracker with geo/currency.
            $result = $this->wpdb->insert(
                $this->table_name,
                [
                    'user_id'       => $user_id,
                    'product_id'    => $product_id,
                    'geo'           => $geo,
                    'currency'      => $currency,
                    'start_price'   => $current_price,
                    'current_price' => $current_price,
                    'target_price'  => $target_price,
                    'price_drop'    => $price_drop,
                    'created_at'    => current_time('mysql', true),
                    'updated_at'    => current_time('mysql', true),
                ]
            );

            $tracker_id = (int) $this->wpdb->insert_id;
        }

        if ($result === false) {
            return new \WP_Error(
                'create_failed',
                'Failed to create price tracker.',
                ['status' => 500]
            );
        }

        // Enable email notifications if requested.
        if ($enable_emails) {
            $this->user_repo->update_preferences($user_id, ['price_tracker_emails' => true]);
        }

        // Get the created/updated tracker.
        $tracker = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $tracker_id
        ), ARRAY_A);

        return new \WP_REST_Response([
            'success' => true,
            'message' => $existing ? 'Price tracker updated.' : 'Price tracker created.',
            'tracker' => $this->enrich_tracker($tracker),
        ], $existing ? 200 : 201);
    }

    /**
     * Update an existing tracker.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function update_tracker(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $tracker_id = (int) $request->get_param('id');

        // Verify ownership.
        $tracker = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d AND user_id = %d",
            $tracker_id,
            $user_id
        ), ARRAY_A);

        if (!$tracker) {
            return new \WP_Error(
                'not_found',
                'Tracker not found.',
                ['status' => 404]
            );
        }

        // Get current price for the tracker's geo.
        $tracker_geo = $tracker['geo'] ?? 'US';
        $price_fetcher = $this->get_price_fetcher();
        $best_price = $price_fetcher->get_best_price((int) $tracker['product_id'], $tracker_geo);
        $current_price = $best_price ? $best_price['price'] : (float) $tracker['current_price'];

        // Build update data.
        $update_data = ['updated_at' => current_time('mysql', true)];

        $tracker_type = $request->get_param('tracker_type');
        $target_price = $request->get_param('target_price');
        $price_drop = $request->get_param('price_drop');

        if ($tracker_type === 'target_price' && $target_price !== null) {
            if ($target_price >= $current_price) {
                return new \WP_Error(
                    'invalid_target',
                    'Target price must be lower than the current price.',
                    ['status' => 400]
                );
            }
            $update_data['target_price'] = $target_price;
            $update_data['price_drop'] = null;
        } elseif ($tracker_type === 'price_drop' && $price_drop !== null) {
            if ($price_drop >= $current_price) {
                return new \WP_Error(
                    'invalid_drop',
                    'Price drop value must be lower than the current price.',
                    ['status' => 400]
                );
            }
            $update_data['price_drop'] = $price_drop;
            $update_data['target_price'] = null;
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $update_data,
            ['id' => $tracker_id]
        );

        if ($result === false) {
            return new \WP_Error(
                'update_failed',
                'Failed to update tracker.',
                ['status' => 500]
            );
        }

        // Get updated tracker.
        $tracker = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $tracker_id
        ), ARRAY_A);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Tracker updated successfully.',
            'tracker' => $this->enrich_tracker($tracker),
        ], 200);
    }

    /**
     * Delete a tracker by ID.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function delete_tracker(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $tracker_id = (int) $request->get_param('id');

        $result = $this->wpdb->delete(
            $this->table_name,
            [
                'id'      => $tracker_id,
                'user_id' => $user_id,
            ],
            ['%d', '%d']
        );

        if ($result === false || $result === 0) {
            return new \WP_Error(
                'delete_failed',
                'Tracker not found or could not be deleted.',
                ['status' => 404]
            );
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Tracker deleted successfully.',
        ], 200);
    }

    /**
     * Delete a tracker by product ID.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response|\WP_Error Response or error.
     */
    public function delete_product_tracker(\WP_REST_Request $request) {
        $user_id = get_current_user_id();
        $product_id = (int) $request->get_param('product_id');

        $result = $this->wpdb->delete(
            $this->table_name,
            [
                'product_id' => $product_id,
                'user_id'    => $user_id,
            ],
            ['%d', '%d']
        );

        if ($result === false || $result === 0) {
            return new \WP_Error(
                'delete_failed',
                'Tracker not found or could not be deleted.',
                ['status' => 404]
            );
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Tracker deleted successfully.',
        ], 200);
    }

    /**
     * Check if a product has price data.
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response Response.
     */
    public function check_price_data(\WP_REST_Request $request): \WP_REST_Response {
        $product_id = (int) $request->get_param('product_id');
        $geo = $request->get_param('geo') ?: 'US';

        $price_fetcher = $this->get_price_fetcher();
        $best_price = $price_fetcher->get_best_price($product_id, $geo);

        if (!$best_price) {
            return new \WP_REST_Response([
                'success'        => true,
                'has_price_data' => false,
                'current_price'  => null,
                'geo'            => $geo,
            ], 200);
        }

        return new \WP_REST_Response([
            'success'        => true,
            'has_price_data' => true,
            'current_price'  => $best_price['price'],
            'currency'       => $best_price['currency'],
            'in_stock'       => $best_price['in_stock'],
            'geo'            => $geo,
        ], 200);
    }

    /**
     * Enrich a tracker with additional data.
     *
     * @param array<string, mixed> $tracker The tracker data.
     * @return array<string, mixed> Enriched tracker data.
     */
    private function enrich_tracker(array $tracker): array {
        $product_id = (int) $tracker['product_id'];
        $tracker_geo = $tracker['geo'] ?? 'US';
        $tracker_currency = $tracker['currency'] ?? 'USD';

        // Get product data.
        $product = get_post($product_id);
        $tracker['product_name'] = $product ? $product->post_title : 'Unknown Product';
        $tracker['product_url'] = $product ? get_permalink($product_id) : '';
        $tracker['product_thumbnail'] = get_the_post_thumbnail_url($product_id, 'thumbnail') ?: '';

        // Ensure geo/currency are set on the tracker.
        $tracker['geo'] = $tracker_geo;
        $tracker['currency'] = $tracker_currency;

        // Get current price for the tracker's geo region.
        $price_fetcher = $this->get_price_fetcher();
        $best_price = $price_fetcher->get_best_price($product_id, $tracker_geo);

        if ($best_price) {
            $tracker['live_price'] = $best_price['price'];
            $tracker['live_currency'] = $best_price['currency'];
            $tracker['in_stock'] = $best_price['in_stock'];
            $tracker['retailer'] = $best_price['retailer'];

            // Calculate if target is met.
            if (!empty($tracker['target_price'])) {
                $tracker['target_met'] = $best_price['price'] <= (float) $tracker['target_price'];
            } elseif (!empty($tracker['price_drop'])) {
                $tracker['target_met'] = $best_price['price'] <= (float) $tracker['price_drop'];
            } else {
                $tracker['target_met'] = false;
            }
        } else {
            $tracker['live_price'] = null;
            $tracker['live_currency'] = $tracker_currency;
            $tracker['in_stock'] = false;
            $tracker['retailer'] = null;
            $tracker['target_met'] = false;
        }

        // Calculate price change since tracking started.
        if (!empty($tracker['start_price']) && !empty($tracker['live_price'])) {
            $start = (float) $tracker['start_price'];
            $current = (float) $tracker['live_price'];
            $tracker['price_change'] = $current - $start;
            $tracker['price_change_percent'] = $start > 0 ? round((($current - $start) / $start) * 100, 1) : 0;
        } else {
            $tracker['price_change'] = 0;
            $tracker['price_change_percent'] = 0;
        }

        // Format tracker type for display.
        $tracker['tracker_type'] = !empty($tracker['target_price']) ? 'target_price' : 'price_drop';
        $tracker['target_value'] = !empty($tracker['target_price'])
            ? (float) $tracker['target_price']
            : (float) $tracker['price_drop'];

        return $tracker;
    }

    /**
     * Get price fetcher instance.
     *
     * @return PriceFetcher The price fetcher.
     */
    private function get_price_fetcher(): PriceFetcher {
        if ($this->price_fetcher === null) {
            $this->price_fetcher = new PriceFetcher();
        }
        return $this->price_fetcher;
    }
}
