<?php
/**
 * Perplexity Client - Wrapper for Perplexity AI API.
 *
 * @package ERH\Admin
 */

declare(strict_types=1);

namespace ERH\Admin;

/**
 * Handles communication with the Perplexity AI API.
 */
class PerplexityClient {

    /**
     * Perplexity API endpoint.
     */
    private const API_URL = 'https://api.perplexity.ai/chat/completions';

    /**
     * Model to use for requests.
     * sonar-pro has web search and better structured output accuracy.
     * See: https://docs.perplexity.ai/getting-started/models
     */
    private const MODEL = 'sonar-pro';

    /**
     * Option name for API key.
     */
    private const OPTION_KEY = 'erh_perplexity_api_key';

    /**
     * Request timeout in seconds.
     */
    private const TIMEOUT = 30;

    /**
     * Get the API key from options.
     *
     * @return string The API key.
     */
    private function get_api_key(): string {
        return get_option(self::OPTION_KEY, '');
    }

    /**
     * Check if the API is configured.
     *
     * @return bool True if API key is set.
     */
    public function is_configured(): bool {
        return !empty($this->get_api_key());
    }

    /**
     * Find a product URL on a specific domain.
     *
     * @param string $product_name The product name to search for.
     * @param string $domain The domain to search on (e.g., "shop.niu.com").
     * @return array{success: bool, url: string|null, error: string|null}
     */
    public function find_product_url(string $product_name, string $domain): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'url'     => null,
                'error'   => 'Perplexity API key not configured.',
            ];
        }

        $prompt = $this->build_prompt($product_name, $domain);

        $response = wp_remote_post(self::API_URL, [
            'timeout' => self::TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->get_api_key(),
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'    => self::MODEL,
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'You are a precise URL finder. Return ONLY the exact product page URL, nothing else. If you cannot find the product, return exactly: NOT_FOUND',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens'  => 200,
                'temperature' => 0.1,
            ]),
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'url'     => null,
                'error'   => $response->get_error_message(),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 429) {
            return [
                'success' => false,
                'url'     => null,
                'error'   => 'Rate limit exceeded. Please wait before retrying.',
            ];
        }

        if ($code !== 200) {
            $error = $body['error']['message'] ?? 'Unknown API error (HTTP ' . $code . ')';
            return [
                'success' => false,
                'url'     => null,
                'error'   => $error,
            ];
        }

        // Extract the URL from the response.
        $content = $body['choices'][0]['message']['content'] ?? '';
        $url = $this->extract_url($content, $domain);

        if ($url === 'NOT_FOUND' || $url === null) {
            return [
                'success' => true,
                'url'     => null,
                'error'   => 'Product not found on this domain.',
            ];
        }

        return [
            'success' => true,
            'url'     => $url,
            'error'   => null,
        ];
    }

    /**
     * Build the prompt for finding a product URL.
     *
     * @param string $product_name The product name.
     * @param string $domain The domain to search.
     * @return string The prompt.
     */
    private function build_prompt(string $product_name, string $domain): string {
        return sprintf(
            'Find the exact product page URL for "%s" on the website %s. ' .
            'Return ONLY the full URL starting with https:// (no explanation, no markdown, just the URL). ' .
            'If you cannot find this exact product on that website, return exactly: NOT_FOUND',
            $product_name,
            $domain
        );
    }

    /**
     * Extract a valid URL from the API response.
     *
     * @param string $content The API response content.
     * @param string $domain The expected domain.
     * @return string|null The extracted URL or null.
     */
    private function extract_url(string $content, string $domain): ?string {
        $content = trim($content);

        // Check for NOT_FOUND response.
        if (stripos($content, 'NOT_FOUND') !== false) {
            return 'NOT_FOUND';
        }

        // Try to extract URL with regex.
        if (preg_match('/(https?:\/\/[^\s\)\"\'<>]+)/i', $content, $matches)) {
            $url = rtrim($matches[1], '.,;:!?)');

            // Validate URL.
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            // Check if URL is on the expected domain.
            $url_host = wp_parse_url($url, PHP_URL_HOST);
            if ($url_host && (
                $url_host === $domain ||
                strpos($url_host, $domain) !== false ||
                strpos($domain, $url_host) !== false
            )) {
                return $url;
            }

            // Domain doesn't match exactly but we found a URL.
            // Return it anyway - the verifier will check if it's valid.
            return $url;
        }

        return null;
    }

    /**
     * Send a generic request to the Perplexity API.
     *
     * Returns the raw content string from the API response.
     * Used by consumers that need custom prompts (e.g., spec population).
     *
     * Uses Pro Search (streaming) for multi-step reasoning across multiple
     * sources. Requires curl for SSE stream handling since wp_remote_post
     * doesn't support streaming.
     *
     * @param string $system_prompt System prompt for the AI.
     * @param string $user_prompt   User prompt with the actual request.
     * @param int    $max_tokens    Maximum tokens in the response.
     * @param float  $temperature   Temperature for response randomness.
     * @param int    $timeout       Request timeout in seconds.
     * @return array{success: bool, content: string|null, error: string|null}
     */
    public function send_request(
        string $system_prompt,
        string $user_prompt,
        int $max_tokens = 4000,
        float $temperature = 0.1,
        int $timeout = 120
    ): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'content' => null,
                'error'   => 'Perplexity API key not configured.',
            ];
        }

        $payload = wp_json_encode([
            'model'    => self::MODEL,
            'stream'   => true,
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => $system_prompt,
                ],
                [
                    'role'    => 'user',
                    'content' => $user_prompt,
                ],
            ],
            'max_tokens'         => $max_tokens,
            'temperature'        => $temperature,
            'web_search_options' => [
                'search_type' => 'pro',
            ],
        ]);

        error_log('[ERH PerplexityClient] Sending Pro Search request');

        return $this->send_streaming_request($payload, $timeout);
    }

    /**
     * Send a streaming request via curl and collect SSE content deltas.
     *
     * @param string $payload JSON request body.
     * @param int    $timeout Request timeout in seconds.
     * @return array{success: bool, content: string|null, error: string|null}
     */
    private function send_streaming_request(string $payload, int $timeout): array {
        $ch = curl_init(self::API_URL);

        if ($ch === false) {
            return [
                'success' => false,
                'content' => null,
                'error'   => 'Failed to initialize curl.',
            ];
        }

        $content_parts = [];
        $http_code = 0;
        $error_body = '';
        $line_buffer = '';

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->get_api_key(),
                'Content-Type: application/json',
                'Accept: text/event-stream',
            ],
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER         => false,
            CURLOPT_WRITEFUNCTION  => function ($ch, $data) use (&$content_parts, &$http_code, &$error_body, &$line_buffer) {
                // Capture HTTP code on first callback.
                if ($http_code === 0) {
                    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                }

                // If non-200, collect raw body for error parsing.
                if ($http_code !== 0 && $http_code !== 200) {
                    $error_body .= $data;
                    return strlen($data);
                }

                // Buffer data — SSE lines can be split across TCP chunks.
                $line_buffer .= $data;

                // Process only complete lines (ending with \n).
                while (($newline_pos = strpos($line_buffer, "\n")) !== false) {
                    $line = trim(substr($line_buffer, 0, $newline_pos));
                    $line_buffer = substr($line_buffer, $newline_pos + 1);

                    if ($line === '' || $line === 'data: [DONE]') {
                        continue;
                    }

                    if (strpos($line, 'data: ') === 0) {
                        $json_str = substr($line, 6);
                        $chunk = json_decode($json_str, true);

                        if (is_array($chunk)) {
                            $delta = $chunk['choices'][0]['delta']['content'] ?? null;
                            if ($delta !== null && $delta !== '') {
                                $content_parts[] = $delta;
                            }
                        }
                    }
                }

                return strlen($data);
            },
        ]);

        curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curl_error) {
            return [
                'success' => false,
                'content' => null,
                'error'   => 'Curl error: ' . $curl_error,
            ];
        }

        if ($http_code === 429) {
            return [
                'success' => false,
                'content' => null,
                'error'   => 'Rate limit exceeded. Please wait before retrying.',
            ];
        }

        if ($http_code !== 200) {
            $error_data = json_decode($error_body, true);
            $error = $error_data['error']['message'] ?? ('Unknown API error (HTTP ' . $http_code . ')');
            error_log('[ERH PerplexityClient] API error: ' . $error);
            return [
                'success' => false,
                'content' => null,
                'error'   => $error,
            ];
        }

        $content = implode('', $content_parts);

        if (empty($content)) {
            return [
                'success' => false,
                'content' => null,
                'error'   => 'No content in streaming response.',
            ];
        }

        error_log('[ERH PerplexityClient] Pro Search complete, content length: ' . strlen($content));

        return [
            'success' => true,
            'content' => $content,
            'error'   => null,
        ];
    }

    /**
     * Find URLs for multiple products in batch.
     * Processes sequentially with a delay to avoid rate limiting.
     *
     * @param array $products Array of ['id' => int, 'name' => string].
     * @param string $domain The domain to search.
     * @param int $delay_ms Delay between requests in milliseconds.
     * @return array Array keyed by product ID with results.
     */
    public function find_product_urls_batch(array $products, string $domain, int $delay_ms = 3000): array {
        $results = [];

        foreach ($products as $index => $product) {
            $results[$product['id']] = $this->find_product_url($product['name'], $domain);

            // Delay between requests (except for last one).
            if ($index < count($products) - 1 && $delay_ms > 0) {
                usleep($delay_ms * 1000);
            }
        }

        return $results;
    }
}
