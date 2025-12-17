<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HFT_Rate_Limiter
 *
 * Implements rate limiting for REST API endpoints using WordPress transients.
 * Supports both IP-based and user-based rate limiting with configurable limits.
 *
 * Features:
 * - Sliding window rate limiting
 * - Proper HTTP 429 responses with Retry-After headers
 * - X-RateLimit-* headers for client information
 * - Configurable per-endpoint limits
 * - Support for proxy headers (X-Forwarded-For)
 * - Logging of rate limit violations
 *
 * @since 1.0.0
 */
class HFT_Rate_Limiter {

	/**
	 * Default rate limit configurations per endpoint
	 *
	 * @var array
	 */
	private const DEFAULT_LIMITS = [
		'get-affiliate-link'      => [ 'requests' => 60, 'period' => 60 ],  // 60 requests per minute
		'detect-geo'              => [ 'requests' => 30, 'period' => 60 ],  // 30 requests per minute
		'price-history-chart'     => [ 'requests' => 30, 'period' => 60 ],  // 30 requests per minute
		'default'                 => [ 'requests' => 100, 'period' => 60 ], // Default: 100 per minute
	];

	/**
	 * Check if the current request exceeds rate limits
	 *
	 * @param string $endpoint The endpoint identifier (e.g., 'get-affiliate-link')
	 * @param int|null $user_id Optional user ID for user-based limiting
	 * @return array Array with 'allowed' (bool), 'limit' (int), 'remaining' (int), 'reset' (int)
	 */
	public function check_rate_limit( string $endpoint, ?int $user_id = null ): array {
		$limits = $this->get_endpoint_limits( $endpoint );
		$identifier = $this->get_rate_limit_identifier( $endpoint, $user_id );

		// Get current request count from transient
		$transient_key = $this->get_transient_key( $identifier );
		$request_data = get_transient( $transient_key );

		$current_time = time();
		$window_start = $current_time - $limits['period'];

		// Initialize or filter request timestamps
		if ( false === $request_data || ! is_array( $request_data ) ) {
			$request_data = [
				'requests' => [],
				'blocked_until' => 0,
			];
		}

		// Check if currently blocked
		if ( isset( $request_data['blocked_until'] ) && $request_data['blocked_until'] > $current_time ) {
			$retry_after = $request_data['blocked_until'] - $current_time;
			return [
				'allowed'    => false,
				'limit'      => $limits['requests'],
				'remaining'  => 0,
				'reset'      => $request_data['blocked_until'],
				'retry_after' => $retry_after,
			];
		}

		// Filter out requests outside the current window (sliding window)
		$request_data['requests'] = array_filter(
			$request_data['requests'] ?? [],
			function( $timestamp ) use ( $window_start ) {
				return $timestamp > $window_start;
			}
		);

		$request_count = count( $request_data['requests'] );
		$remaining = max( 0, $limits['requests'] - $request_count );
		$reset_time = $current_time + $limits['period'];

		// Check if limit exceeded
		if ( $request_count >= $limits['requests'] ) {
			// Block for the remainder of the period
			$oldest_request = min( $request_data['requests'] );
			$blocked_until = $oldest_request + $limits['period'];
			$request_data['blocked_until'] = $blocked_until;

			// Save blocked state
			set_transient( $transient_key, $request_data, $limits['period'] + 60 );

			// Log the rate limit violation
			$this->log_rate_limit_violation( $endpoint, $identifier, $request_count, $limits['requests'] );

			$retry_after = $blocked_until - $current_time;

			return [
				'allowed'     => false,
				'limit'       => $limits['requests'],
				'remaining'   => 0,
				'reset'       => $blocked_until,
				'retry_after' => $retry_after,
			];
		}

		// Add current request timestamp
		$request_data['requests'][] = $current_time;
		$request_data['blocked_until'] = 0;

		// Save updated request data
		set_transient( $transient_key, $request_data, $limits['period'] + 60 );

		return [
			'allowed'   => true,
			'limit'     => $limits['requests'],
			'remaining' => $remaining - 1, // Subtract 1 for current request
			'reset'     => $reset_time,
		];
	}

	/**
	 * Get rate limit configuration for an endpoint
	 *
	 * @param string $endpoint The endpoint identifier
	 * @return array Array with 'requests' and 'period' keys
	 */
	private function get_endpoint_limits( string $endpoint ): array {
		$limits = self::DEFAULT_LIMITS[ $endpoint ] ?? self::DEFAULT_LIMITS['default'];

		// Allow filtering via WordPress filter
		$limits = apply_filters( 'hft_rate_limit_config', $limits, $endpoint );
		$limits = apply_filters( "hft_rate_limit_config_{$endpoint}", $limits );

		// Validate limits
		$limits['requests'] = max( 1, absint( $limits['requests'] ) );
		$limits['period'] = max( 1, absint( $limits['period'] ) );

		return $limits;
	}

	/**
	 * Generate a unique identifier for rate limiting
	 *
	 * Uses IP address by default, or user ID if provided
	 *
	 * @param string $endpoint The endpoint identifier
	 * @param int|null $user_id Optional user ID
	 * @return string Unique identifier
	 */
	private function get_rate_limit_identifier( string $endpoint, ?int $user_id = null ): string {
		if ( $user_id ) {
			return "user_{$user_id}_{$endpoint}";
		}

		$ip_address = $this->get_client_ip();
		$sanitized_ip = $this->sanitize_ip_for_key( $ip_address );

		return "ip_{$sanitized_ip}_{$endpoint}";
	}

	/**
	 * Get the client's IP address, respecting proxy headers
	 *
	 * Checks headers in order of preference:
	 * 1. HTTP_X_REAL_IP (most trustworthy in common setups)
	 * 2. HTTP_X_FORWARDED_FOR (first IP in chain)
	 * 3. REMOTE_ADDR (fallback)
	 *
	 * @return string The client IP address
	 */
	private function get_client_ip(): string {
		$ip_address = '';

		// Check for X-Real-IP (Nginx, CloudFlare)
		if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		}
		// Check for X-Forwarded-For (proxy chain - use first IP)
		elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ips = array_map( 'trim', explode( ',', $forwarded ) );
			$ip_address = $ips[0];
		}
		// Fallback to REMOTE_ADDR
		elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip_address = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Validate IP address
		if ( ! filter_var( $ip_address, FILTER_VALIDATE_IP ) ) {
			$ip_address = '0.0.0.0'; // Fallback for invalid IPs
		}

		return $ip_address;
	}

	/**
	 * Sanitize IP address for use in transient key
	 *
	 * @param string $ip_address The IP address
	 * @return string Sanitized IP suitable for transient key
	 */
	private function sanitize_ip_for_key( string $ip_address ): string {
		// Replace dots and colons with underscores for transient key safety
		return str_replace( [ '.', ':' ], [ '_', '_' ], $ip_address );
	}

	/**
	 * Generate transient key for rate limit data
	 *
	 * @param string $identifier The rate limit identifier
	 * @return string Transient key
	 */
	private function get_transient_key( string $identifier ): string {
		// Transient keys must be 172 characters or less
		$key = 'hft_rl_' . $identifier;

		if ( strlen( $key ) > 172 ) {
			// Use hash for long identifiers
			$key = 'hft_rl_' . md5( $identifier );
		}

		return $key;
	}

	/**
	 * Log rate limit violation for monitoring
	 *
	 * @param string $endpoint The endpoint identifier
	 * @param string $identifier The rate limit identifier
	 * @param int $request_count Current request count
	 * @param int $limit The rate limit
	 * @return void
	 */
	private function log_rate_limit_violation( string $endpoint, string $identifier, int $request_count, int $limit ): void {
		// Only log if WP_DEBUG is enabled to avoid bloating logs
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$message = sprintf(
			'[HFT Rate Limit] Endpoint: %s, Identifier: %s, Requests: %d/%d',
			$endpoint,
			$identifier,
			$request_count,
			$limit
		);

		error_log( $message );

		// Fire action for custom logging or monitoring
		do_action( 'hft_rate_limit_exceeded', $endpoint, $identifier, $request_count, $limit );
	}

	/**
	 * Add rate limit headers to REST API response
	 *
	 * @param array $rate_limit_result Result from check_rate_limit()
	 * @return void
	 */
	public function add_rate_limit_headers( array $rate_limit_result ): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'X-RateLimit-Limit: ' . $rate_limit_result['limit'] );
		header( 'X-RateLimit-Remaining: ' . $rate_limit_result['remaining'] );
		header( 'X-RateLimit-Reset: ' . $rate_limit_result['reset'] );

		if ( ! $rate_limit_result['allowed'] && isset( $rate_limit_result['retry_after'] ) ) {
			header( 'Retry-After: ' . $rate_limit_result['retry_after'] );
		}
	}

	/**
	 * Create a WP_Error response for rate limit exceeded
	 *
	 * @param array $rate_limit_result Result from check_rate_limit()
	 * @return WP_Error
	 */
	public function create_rate_limit_error( array $rate_limit_result ): WP_Error {
		$retry_after = $rate_limit_result['retry_after'] ?? 60;

		return new WP_Error(
			'rest_rate_limit_exceeded',
			sprintf(
				/* translators: %d: number of seconds */
				__( 'Rate limit exceeded. Please try again in %d seconds.', 'housefresh-tools' ),
				$retry_after
			),
			[
				'status' => 429,
				'limit' => $rate_limit_result['limit'],
				'retry_after' => $retry_after,
			]
		);
	}

	/**
	 * Clear rate limit data for an identifier
	 *
	 * Useful for testing or admin reset functionality
	 *
	 * @param string $endpoint The endpoint identifier
	 * @param int|null $user_id Optional user ID
	 * @return bool True if data was cleared
	 */
	public function clear_rate_limit( string $endpoint, ?int $user_id = null ): bool {
		$identifier = $this->get_rate_limit_identifier( $endpoint, $user_id );
		$transient_key = $this->get_transient_key( $identifier );

		return delete_transient( $transient_key );
	}

	/**
	 * Get current rate limit status without incrementing
	 *
	 * Useful for status checks without affecting the rate limit
	 *
	 * @param string $endpoint The endpoint identifier
	 * @param int|null $user_id Optional user ID
	 * @return array Rate limit status
	 */
	public function get_rate_limit_status( string $endpoint, ?int $user_id = null ): array {
		$limits = $this->get_endpoint_limits( $endpoint );
		$identifier = $this->get_rate_limit_identifier( $endpoint, $user_id );
		$transient_key = $this->get_transient_key( $identifier );
		$request_data = get_transient( $transient_key );

		$current_time = time();
		$window_start = $current_time - $limits['period'];

		if ( false === $request_data || ! is_array( $request_data ) ) {
			return [
				'limit'     => $limits['requests'],
				'remaining' => $limits['requests'],
				'reset'     => $current_time + $limits['period'],
			];
		}

		// Filter requests in current window
		$valid_requests = array_filter(
			$request_data['requests'] ?? [],
			function( $timestamp ) use ( $window_start ) {
				return $timestamp > $window_start;
			}
		);

		$request_count = count( $valid_requests );
		$remaining = max( 0, $limits['requests'] - $request_count );

		return [
			'limit'     => $limits['requests'],
			'remaining' => $remaining,
			'reset'     => $current_time + $limits['period'],
		];
	}
}
