<?php
/**
 * URL Verifier - HTTP HEAD verification for URLs.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

/**
 * Verifies URLs by making HTTP HEAD requests.
 */
class UrlVerifier {

    /**
     * Request timeout in seconds.
     */
    private const TIMEOUT = 10;

    /**
     * User agent for requests.
     */
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * HTTP status codes considered successful.
     */
    private const SUCCESS_CODES = [200, 201, 301, 302, 303, 307, 308];

    /**
     * Verify a single URL.
     *
     * @param string $url The URL to verify.
     * @return array{success: bool, http_code: int|null, error: string|null, final_url: string|null}
     */
    public function verify(string $url): array {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success'   => false,
                'http_code' => null,
                'error'     => 'Invalid URL format.',
                'final_url' => null,
            ];
        }

        // Try HEAD request first (faster, less bandwidth).
        $response = wp_remote_head($url, [
            'timeout'     => self::TIMEOUT,
            'redirection' => 5,
            'user-agent'  => self::USER_AGENT,
            'sslverify'   => false,
        ]);

        // Some servers don't support HEAD, fallback to GET.
        if (is_wp_error($response)) {
            $response = wp_remote_get($url, [
                'timeout'     => self::TIMEOUT,
                'redirection' => 5,
                'user-agent'  => self::USER_AGENT,
                'sslverify'   => false,
            ]);
        }

        if (is_wp_error($response)) {
            return [
                'success'   => false,
                'http_code' => null,
                'error'     => $response->get_error_message(),
                'final_url' => null,
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $headers = wp_remote_retrieve_headers($response);
        $final_url = $headers['location'] ?? $url;

        // Check for soft 404s (pages that return 200 but are error pages).
        $body = wp_remote_retrieve_body($response);
        if ($code === 200 && $this->is_soft_404($body)) {
            return [
                'success'   => false,
                'http_code' => 404,
                'error'     => 'Soft 404 detected.',
                'final_url' => $final_url,
            ];
        }

        $is_success = in_array($code, self::SUCCESS_CODES, true);

        return [
            'success'   => $is_success,
            'http_code' => $code,
            'error'     => $is_success ? null : 'HTTP ' . $code,
            'final_url' => $final_url,
        ];
    }

    /**
     * Verify multiple URLs in batch.
     *
     * @param array $urls Array of URLs to verify.
     * @param int $delay_ms Delay between requests in milliseconds.
     * @return array Array keyed by URL with verification results.
     */
    public function verify_batch(array $urls, int $delay_ms = 100): array {
        $results = [];

        foreach ($urls as $index => $url) {
            $results[$url] = $this->verify($url);

            // Delay between requests (except for last one).
            if ($index < count($urls) - 1 && $delay_ms > 0) {
                usleep($delay_ms * 1000);
            }
        }

        return $results;
    }

    /**
     * Check if response body indicates a soft 404.
     *
     * @param string $body Response body.
     * @return bool True if soft 404 detected.
     */
    private function is_soft_404(string $body): bool {
        if (empty($body)) {
            return false;
        }

        // Common soft 404 indicators.
        $indicators = [
            'page not found',
            'product not found',
            'item not found',
            'no longer available',
            'has been removed',
            'doesn\'t exist',
            'does not exist',
            '404 error',
            'error 404',
        ];

        $body_lower = strtolower($body);

        foreach ($indicators as $indicator) {
            if (strpos($body_lower, $indicator) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a URL is likely a product page based on URL structure.
     *
     * @param string $url The URL to check.
     * @return bool True if URL looks like a product page.
     */
    public function looks_like_product_page(string $url): bool {
        $path = wp_parse_url($url, PHP_URL_PATH) ?? '';
        $path_lower = strtolower($path);

        // Common product URL patterns.
        $product_patterns = [
            '/product/',
            '/products/',
            '/shop/',
            '/item/',
            '/p/',
            '/dp/',      // Amazon.
            '/gp/product/', // Amazon.
            '-p-',       // Some e-commerce sites.
            '/detail/',
        ];

        foreach ($product_patterns as $pattern) {
            if (strpos($path_lower, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
