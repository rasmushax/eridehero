<?php
/**
 * Rate Limiter - Shared rate limiting service using transients.
 *
 * @package ERH\User
 */

declare(strict_types=1);

namespace ERH\User;

/**
 * Provides rate limiting functionality for various actions.
 */
class RateLimiter {

    /**
     * Default rate limit configurations.
     *
     * @var array<string, array{max_attempts: int, window: int}>
     */
    private const DEFAULT_LIMITS = [
        'login'               => ['max_attempts' => 5, 'window' => 900],   // 5 attempts per 15 min per IP
        'login_account'       => ['max_attempts' => 10, 'window' => 900],  // 10 attempts per 15 min per account
        'register'            => ['max_attempts' => 3, 'window' => 3600],  // 3 attempts per hour
        'password_reset'      => ['max_attempts' => 3, 'window' => 3600],  // 3 attempts per hour
        'check_email'         => ['max_attempts' => 5, 'window' => 300],   // 5 attempts per 5 min
        'tracker_create'      => ['max_attempts' => 10, 'window' => 3600], // 10 per hour
        'tracker_unsubscribe' => ['max_attempts' => 20, 'window' => 3600], // 20 per hour
        'review_submit'       => ['max_attempts' => 5, 'window' => 3600],  // 5 per hour
        // Public API endpoints.
        'api_similar'         => ['max_attempts' => 60, 'window' => 60],   // 60 per minute
        'api_analysis'        => ['max_attempts' => 30, 'window' => 60],   // 30 per minute
        'api_best_prices'     => ['max_attempts' => 30, 'window' => 60],   // 30 per minute
        'api_deals'           => ['max_attempts' => 60, 'window' => 60],   // 60 per minute
        'api_geo'             => ['max_attempts' => 10, 'window' => 60],   // 10 per minute
    ];

    /**
     * Check if an action is rate limited.
     *
     * @param string $action     The action being rate limited (e.g., 'login', 'register').
     * @param string $identifier The identifier (IP address, user ID, etc.).
     * @return bool True if the action is allowed, false if rate limited.
     */
    public function is_allowed(string $action, string $identifier): bool {
        $key = $this->get_transient_key($action, $identifier);
        $attempts = (int) get_transient($key);
        $limit = $this->get_limit($action);

        return $attempts < $limit['max_attempts'];
    }

    /**
     * Record an attempt for rate limiting.
     *
     * @param string $action     The action being rate limited.
     * @param string $identifier The identifier.
     * @return int The current attempt count after incrementing.
     */
    public function record_attempt(string $action, string $identifier): int {
        $key = $this->get_transient_key($action, $identifier);
        $attempts = (int) get_transient($key);
        $limit = $this->get_limit($action);

        $attempts++;
        set_transient($key, $attempts, $limit['window']);

        return $attempts;
    }

    /**
     * Get the current attempt count.
     *
     * @param string $action     The action.
     * @param string $identifier The identifier.
     * @return int Current attempt count.
     */
    public function get_attempts(string $action, string $identifier): int {
        $key = $this->get_transient_key($action, $identifier);
        return (int) get_transient($key);
    }

    /**
     * Get remaining attempts before rate limit is hit.
     *
     * @param string $action     The action.
     * @param string $identifier The identifier.
     * @return int Remaining attempts.
     */
    public function get_remaining(string $action, string $identifier): int {
        $limit = $this->get_limit($action);
        $attempts = $this->get_attempts($action, $identifier);
        return max(0, $limit['max_attempts'] - $attempts);
    }

    /**
     * Reset the rate limit for a specific action/identifier.
     *
     * @param string $action     The action.
     * @param string $identifier The identifier.
     * @return bool True on success.
     */
    public function reset(string $action, string $identifier): bool {
        $key = $this->get_transient_key($action, $identifier);
        return delete_transient($key);
    }

    /**
     * Check rate limit and record attempt in one call.
     * Returns result with status and message.
     *
     * @param string $action     The action.
     * @param string $identifier The identifier.
     * @return array{allowed: bool, attempts: int, remaining: int, message: string}
     */
    public function check_and_record(string $action, string $identifier): array {
        if (!$this->is_allowed($action, $identifier)) {
            $limit = $this->get_limit($action);
            $minutes = ceil($limit['window'] / 60);

            return [
                'allowed'   => false,
                'attempts'  => $this->get_attempts($action, $identifier),
                'remaining' => 0,
                'message'   => sprintf(
                    'Too many attempts. Please try again in %d minutes.',
                    $minutes
                ),
            ];
        }

        $attempts = $this->record_attempt($action, $identifier);
        $remaining = $this->get_remaining($action, $identifier);

        return [
            'allowed'   => true,
            'attempts'  => $attempts,
            'remaining' => $remaining,
            'message'   => '',
        ];
    }

    /**
     * Get the client IP address.
     *
     * @return string The IP address.
     */
    public static function get_client_ip(): string {
        $ip = '';

        // Check for various proxy headers.
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // X-Forwarded-For can contain multiple IPs, take the first.
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                break;
            }
        }

        // Validate IP address.
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $ip = '0.0.0.0';
        }

        return $ip;
    }

    /**
     * Get the rate limit configuration for an action.
     *
     * @param string $action The action name.
     * @return array{max_attempts: int, window: int} The limit configuration.
     */
    private function get_limit(string $action): array {
        $limits = apply_filters('erh_rate_limits', self::DEFAULT_LIMITS);

        return $limits[$action] ?? ['max_attempts' => 5, 'window' => 900];
    }

    /**
     * Generate the transient key for storage.
     *
     * @param string $action     The action.
     * @param string $identifier The identifier.
     * @return string The transient key.
     */
    private function get_transient_key(string $action, string $identifier): string {
        // Hash the identifier to keep key length manageable.
        $hash = substr(md5($identifier), 0, 12);
        return "erh_rl_{$action}_{$hash}";
    }
}
