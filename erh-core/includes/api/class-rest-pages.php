<?php
/**
 * REST Pages API - Page view tracking endpoint.
 *
 * @package ERH\Api
 */

declare(strict_types=1);

namespace ERH\Api;

use ERH\Database\PageViewTracker;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Handles REST API endpoints for page view tracking.
 */
class RestPages {

    /**
     * API namespace.
     *
     * @var string
     */
    private string $namespace = 'erh/v1';

    /**
     * Valid page types.
     *
     * @var array
     */
    private const VALID_PAGE_TYPES = [
        'listicle', 'compare', 'deals', 'category', 'finder', 'homepage', 'tool',
    ];

    /**
     * Register REST routes.
     *
     * @return void
     */
    public function register_routes(): void {
        register_rest_route($this->namespace, '/pages/view', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'record_page_view'],
                'permission_callback' => '__return_true', // Public endpoint.
                'args'                => [
                    'path' => [
                        'description'       => 'The page path',
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'type' => [
                        'description'       => 'The page type',
                        'type'              => 'string',
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                        'validate_callback' => function ($value) {
                            return in_array($value, self::VALID_PAGE_TYPES, true);
                        },
                    ],
                ],
            ],
        ]);
    }

    /**
     * Record a page view.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response Response.
     */
    public function record_page_view(WP_REST_Request $request): WP_REST_Response {
        $path = $request->get_param('path');
        $type = $request->get_param('type');

        // Get IP.
        $ip = $this->get_client_ip();
        if (!$ip) {
            return new WP_REST_Response(['success' => false], 200);
        }

        // Get user agent.
        $ua = isset($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : '';

        $tracker = new PageViewTracker();
        $recorded = $tracker->record_view($path, $type, $ip, $ua);

        return new WP_REST_Response([
            'success'  => true,
            'recorded' => $recorded,
        ], 200);
    }

    /**
     * Get client IP address.
     *
     * @return string|null The IP address or null.
     */
    private function get_client_ip(): ?string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }
}
