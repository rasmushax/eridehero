<?php
/**
 * REST API Geo Detection Endpoint.
 *
 * Server-side proxy for IPInfo geo detection. Keeps the API token
 * private (never exposed to the browser).
 *
 * @package ERH\Api
 */

declare(strict_types=1);

namespace ERH\Api;

use ERH\GeoConfig;
use ERH\User\RateLimiter;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST controller for geo detection endpoint.
 */
class RestGeo extends WP_REST_Controller {

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
    protected $rest_base = 'geo';

    /**
     * Rate limiter instance.
     *
     * @var RateLimiter
     */
    private RateLimiter $rate_limiter;

    /**
     * Constructor.
     *
     * @param RateLimiter|null $rate_limiter Optional rate limiter instance.
     */
    public function __construct(?RateLimiter $rate_limiter = null) {
        $this->rate_limiter = $rate_limiter ?? new RateLimiter();
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'detect_geo'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    /**
     * Detect user geo via IPInfo API (server-side).
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response|WP_Error Response with geo data.
     */
    public function detect_geo(WP_REST_Request $request) {
        $client_ip = RateLimiter::get_client_ip();

        $rate = $this->rate_limiter->check_and_record('api_geo', $client_ip);
        if (!$rate['allowed']) {
            return new WP_Error('rate_limit_exceeded', $rate['message'], ['status' => 429]);
        }

        // Get IPInfo token from HFT settings.
        $hft_settings = get_option('hft_settings', []);
        $token = $hft_settings['ipinfo_api_token'] ?? '';

        if (empty($token)) {
            // No token configured — return default region.
            return new WP_REST_Response([
                'country'  => null,
                'region'   => 'US',
                'detected' => false,
            ], 200);
        }

        // Call IPInfo API using the client's real IP.
        $url = sprintf('https://ipinfo.io/%s/json?token=%s', $client_ip, $token);
        $response = wp_remote_get($url, [
            'timeout' => 5,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'country'  => null,
                'region'   => 'US',
                'detected' => false,
            ], 200);
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $country = $data['country'] ?? null;
        $region = $country ? GeoConfig::get_region($country) : 'US';

        return new WP_REST_Response([
            'country'  => $country,
            'region'   => $region,
            'detected' => $country !== null,
        ], 200);
    }
}
